<?php
// register.php
require_once 'config/database.php';
require_once 'includes/functions.php';

// If already logged in, redirect to index
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

// Fetch departments for the dropdown
$deps = [];
$dep_q = mysqli_query($conn, "SELECT id, name FROM departments WHERE deleted_at IS NULL ORDER BY name ASC");
if ($dep_q) {
    while($row = mysqli_fetch_assoc($dep_q)) {
        $deps[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = mysqli_real_escape_string($conn, trim($_POST['first_name'] ?? ''));
    $last_name = mysqli_real_escape_string($conn, trim($_POST['last_name'] ?? ''));
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
    $role = mysqli_real_escape_string($conn, trim($_POST['role'] ?? 'student')); // Default to student
    $department_id = (int)($_POST['department_id'] ?? 0);
    $registration_number = mysqli_real_escape_string($conn, trim($_POST['registration_number'] ?? ''));
    
    // Basic validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || $department_id <= 0) {
        $error = 'Please fill in all required fields, including department.';
    } elseif (!in_array($role, ['student', 'lecturer'])) {
        $error = 'Invalid role selected. Only Students and Lecturers can self-register.';
    } else {
        // Check if email already exists
        $check_q = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
        if ($check_q && mysqli_num_rows($check_q) > 0) {
            $error = 'An account with this email already exists.';
        } else {
            // Hash password
            $hashed_pw = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user as inactive
            $sql = "INSERT INTO users (first_name, last_name, email, password_hash, role, department_id, phone, registration_number, is_active) 
                    VALUES ('$first_name', '$last_name', '$email', '$hashed_pw', '$role', $department_id, '$phone', '$registration_number', 0)";
                    
            if (mysqli_query($conn, $sql)) {
                $success = 'Registration successful! Your account is currently pending activation by an Administrator. You will be able to log in once approved.';
            } else {
                $error = 'A database error occurred during registration. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - UEMS Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
    </style>
</head>
<body>
<div class="container pb-5">
    <div class="row justify-content-center align-items-center mt-5">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-header bg-success text-white text-center py-4 rounded-top-4">
                    <i class="bi bi-person-plus-fill fs-1"></i>
                    <h3 class="font-weight-light my-2">Create an Account</h3>
                </div>
                <div class="card-body p-5">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
                        <div class="text-center mt-4">
                            <a href="login.php" class="btn btn-outline-success px-4">Back to Login</a>
                        </div>
                    <?php else: ?>
                    
                    <form method="POST" action="register.php">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">First Name *</label>
                                <input class="form-control" type="text" name="first_name" required />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">Last Name *</label>
                                <input class="form-control" type="text" name="last_name" required />
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Email Address *</label>
                            <input class="form-control" type="email" name="email" placeholder="example@kiu.ac.ug" required />
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">Password *</label>
                                <input class="form-control" type="password" name="password" required minlength="6" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">Phone Number</label>
                                <input class="form-control" type="text" name="phone" />
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        <h6 class="text-primary mb-3">Academic Details</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">Role *</label>
                                <select class="form-select" name="role" id="roleSelect" onchange="toggleRegNumber()" required>
                                    <option value="student">Student</option>
                                    <option value="lecturer">Lecturer</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">Department *</label>
                                <select class="form-select" name="department_id" required>
                                    <option value="">Select Department...</option>
                                    <?php foreach($deps as $d): ?>
                                        <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-4" id="regNumGroup">
                            <label class="form-label text-muted small fw-bold">Registration Number (Students Only)</label>
                            <input class="form-control" type="text" name="registration_number" placeholder="e.g. 1153-01024-00000" />
                        </div>
                        
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-success btn-lg">Complete Registration</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center py-3 border-0 bg-light rounded-bottom-4">
                    <div class="small"><a href="login.php" class="text-decoration-none text-success fw-bold">Already have an account? Go to Login</a></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function toggleRegNumber() {
    var role = document.getElementById('roleSelect').value;
    var regGroup = document.getElementById('regNumGroup');
    if (role === 'lecturer') {
        regGroup.style.display = 'none';
        regGroup.querySelector('input').value = '';
    } else {
        regGroup.style.display = 'block';
    }
}
</script>
</body>
</html>
