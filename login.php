<?php
// login.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// If already logged in, redirect to index
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } else {
        $result = attempt_login($conn, $email, $password);
        if ($result['success']) {
            header("Location: index.php");
            exit();
        } else {
            $error = $result['error'];
        }
    }
}
?>
<?php require_once 'includes/header.php'; ?>

<div class="row justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-lg border-0 rounded-lg">
            <div class="card-header bg-primary text-white text-center py-4">
                <i class="bi bi-shield-lock-fill fs-1"></i>
                <h3 class="font-weight-light my-2">UEMS Login</h3>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="login.php">
                    <div class="form-floating mb-3">
                        <input class="form-control" id="inputEmail" type="email" name="email" placeholder="name@example.com" required autofocus />
                        <label for="inputEmail">Email address</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input class="form-control" id="inputPassword" type="password" name="password" placeholder="Password" required />
                        <label for="inputPassword">Password</label>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mt-4 mb-0">
                        <a class="small text-decoration-none" href="#">Forgot Password?</a>
                        <button type="submit" class="btn btn-primary px-4">Login</button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center py-3 border-0 bg-light">
                <div class="small"><a href="register.php" class="text-decoration-none">Need an account? Sign up!</a></div>
                <div class="mt-2 text-muted small">
                    <p class="mb-0">Default testing credentials:</p>
                    <p class="mb-0">admin@kiu.ac.ug / admin@123</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
