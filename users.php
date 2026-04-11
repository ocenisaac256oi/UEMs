<?php
// users.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Only admins can manage users
require_role('admin');

// Fetch Departments and Colleges for dropdowns
$departments = [];
$dept_query = mysqli_query($conn, "SELECT id, name FROM departments WHERE deleted_at IS NULL ORDER BY name");
if ($dept_query) {
    while($row = mysqli_fetch_assoc($dept_query)){
        $departments[] = $row;
    }
}

$colleges = [];
$coll_query = mysqli_query($conn, "SELECT id, name FROM colleges WHERE deleted_at IS NULL ORDER BY name");
if ($coll_query) {
    while($row = mysqli_fetch_assoc($coll_query)){
        $colleges[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    if ($action === 'toggle_status' && $id > 0) {
        mysqli_query($conn, "UPDATE users SET is_active = NOT is_active WHERE id = $id");
        set_flash_message('success', 'User status toggled successfully.');
    } elseif ($action === 'edit' && $id > 0) {
        $first_name = sanitize_input($conn, $_POST['first_name']);
        $last_name = sanitize_input($conn, $_POST['last_name']);
        $role = sanitize_input($conn, $_POST['role']);
        $reg_num = sanitize_input($conn, $_POST['registration_number'] ?? '');
        $reg_sql_val = empty($reg_num) ? "NULL" : "'$reg_num'";
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : 'NULL';
        $college_id = !empty($_POST['college_id']) ? (int)$_POST['college_id'] : 'NULL';
        
        $sql = "UPDATE users SET first_name = '$first_name', last_name = '$last_name', role = '$role', registration_number = $reg_sql_val, department_id = $department_id, college_id = $college_id WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            set_flash_message('success', 'User updated successfully.');
        } else {
            set_flash_message('danger', 'Failed to update user.');
        }
    } elseif ($action === 'add') {
        $first_name = sanitize_input($conn, $_POST['first_name']);
        $last_name = sanitize_input($conn, $_POST['last_name']);
        $email = sanitize_input($conn, $_POST['email']);
        $password = $_POST['password'];
        $role = sanitize_input($conn, $_POST['role']);
        $reg_num = sanitize_input($conn, $_POST['registration_number'] ?? '');
        $reg_sql_val = empty($reg_num) ? "NULL" : "'$reg_num'";
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : 'NULL';
        $college_id = !empty($_POST['college_id']) ? (int)$_POST['college_id'] : 'NULL';
        
        $check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
        if (mysqli_num_rows($check) > 0) {
            set_flash_message('danger', 'A user with that email already exists.');
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (first_name, last_name, email, password_hash, role, registration_number, department_id, college_id, is_active) 
                      VALUES ('$first_name', '$last_name', '$email', '$hashed', '$role', $reg_sql_val, $department_id, $college_id, 1)";
            if (mysqli_query($conn, $query)) {
                set_flash_message('success', 'New user added successfully.');
            } else {
                set_flash_message('danger', 'Failed to add user.');
            }
        }
    }
    
    header("Location: users.php");
    exit();
}

// Pagination & Filtering
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$role_filter = isset($_GET['role']) ? sanitize_input($conn, $_GET['role']) : '';
$where_clause = "u.deleted_at IS NULL";

if (in_array($role_filter, ['student', 'lecturer', 'hod'])) {
    $where_clause .= " AND u.role = '$role_filter'";
}

// Total count
$count_query = mysqli_query($conn, "SELECT COUNT(id) as total FROM users u WHERE $where_clause");
$total_rows = mysqli_fetch_assoc($count_query)['total'];
$total_pages = ceil($total_rows / $limit);

// Fetch Users
$users = [];
$query = "SELECT u.*, d.name as dept_name, c.name as coll_name 
          FROM users u 
          LEFT JOIN departments d ON u.department_id = d.id 
          LEFT JOIN colleges c ON u.college_id = c.id 
          WHERE $where_clause 
          ORDER BY u.created_at DESC 
          LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $query);
if ($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-people"></i> Manage Users</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-plus-lg"></i> Add New User</button>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-auto">
                <label for="role" class="col-form-label fw-bold">Filter by Role:</label>
            </div>
            <div class="col-md-4 col-sm-6">
                <select name="role" id="role" class="form-select" onchange="this.form.submit()">
                    <option value="">All Roles</option>
                    <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                    <option value="lecturer" <?php echo $role_filter === 'lecturer' ? 'selected' : ''; ?>>Lecturer</option>
                    <option value="hod" <?php echo $role_filter === 'hod' ? 'selected' : ''; ?>>HOD</option>
                </select>
            </div>
            <?php if(!empty($role_filter)): ?>
            <div class="col-auto">
                <a href="users.php" class="btn btn-outline-secondary">Clear</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="py-3">Email</th>
                        <th class="py-3">Role</th>
                        <th class="py-3">Affiliation</th>
                        <th class="py-3">Status</th>
                        <th class="px-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($users)): ?>
                        <tr><td colspan="6" class="text-center py-4">No users found.</td></tr>
                    <?php else: ?>
                        <?php foreach($users as $u): ?>
                            <tr>
                                <td class="px-4 fw-bold">
                                    <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>
                                    <?php if(!empty($u['registration_number'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($u['registration_number']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td>
                                    <span class="badge bg-primary rounded-pill px-3"><?php echo strtoupper(str_replace('_', ' ', $u['role'])); ?></span>
                                </td>
                                <td class="small">
                                    <?php if($u['dept_name']) echo "<div><i class='bi bi-diagram-3'></i> " . htmlspecialchars($u['dept_name']) . "</div>"; ?>
                                    <?php if($u['coll_name']) echo "<div><i class='bi bi-building'></i> " . htmlspecialchars($u['coll_name']) . "</div>"; ?>
                                </td>
                                <td>
                                    <?php if($u['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 text-end">
                                    <button class="btn btn-sm btn-outline-primary me-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editModal" 
                                            data-id="<?php echo $u['id']; ?>"
                                            data-fname="<?php echo htmlspecialchars($u['first_name']); ?>"
                                            data-lname="<?php echo htmlspecialchars($u['last_name']); ?>"
                                            data-reg="<?php echo htmlspecialchars($u['registration_number'] ?? ''); ?>"
                                            data-role="<?php echo htmlspecialchars($u['role']); ?>"
                                            data-dept="<?php echo $u['department_id']; ?>"
                                            data-coll="<?php echo $u['college_id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="btn btn-sm <?php echo $u['is_active'] ? 'btn-outline-danger' : 'btn-outline-success'; ?>" title="Toggle Status">
                                            <i class="bi <?php echo $u['is_active'] ? 'bi-lock' : 'bi-unlock'; ?>"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($total_pages > 1): ?>
<nav aria-label="Page navigation" class="mt-4 mb-4">
    <ul class="pagination justify-content-center">
        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($role_filter) ? '&role='.$role_filter : ''; ?>">Previous</a>
        </li>
        <?php for($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($role_filter) ? '&role='.$role_filter : ''; ?>"><?php echo $i; ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($role_filter) ? '&role='.$role_filter : ''; ?>">Next</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" id="add_role" required onchange="toggleAddRegNumber()">
                                <option value="student">Student</option>
                                <option value="admin">Admin</option>
                                <option value="dean">Dean</option>
                                <option value="hod">HOD</option>
                                <option value="lecturer">Lecturer</option>
                                <option value="exam_master">Exam Master</option>
                            </select>
                        </div>
                        <div class="col-6" id="addRegNumField">
                            <label class="form-label">Reg Number</label>
                            <input class="form-control" type="text" name="registration_number" placeholder="Optional" />
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Department (Optional)</label>
                        <select class="form-select" name="department_id">
                            <option value="">-- None --</option>
                            <?php foreach($departments as $d): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">College (Optional)</label>
                        <select class="form-select" name="college_id">
                            <option value="">-- None --</option>
                            <?php foreach($colleges as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User Info</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" id="edit_fname" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" id="edit_lname" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" id="edit_role" required onchange="toggleEditRegNumber()">
                                <option value="admin">Admin</option>
                                <option value="dean">Dean</option>
                                <option value="hod">HOD</option>
                                <option value="lecturer">Lecturer</option>
                                <option value="student">Student</option>
                                <option value="exam_master">Exam Master</option>
                            </select>
                        </div>
                        <div class="col-6" id="editRegNumField">
                            <label class="form-label">Reg Number</label>
                            <input class="form-control" type="text" name="registration_number" id="edit_reg_num" />
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select class="form-select" name="department_id" id="edit_dept">
                            <option value="">-- None --</option>
                            <?php foreach($departments as $d): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">College</label>
                        <select class="form-select" name="college_id" id="edit_coll">
                            <option value="">-- None --</option>
                            <?php foreach($colleges as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleAddRegNumber() {
    var role = document.getElementById('add_role').value;
    document.getElementById('addRegNumField').style.display = (role === 'student') ? 'block' : 'none';
}

function toggleEditRegNumber() {
    var role = document.getElementById('edit_role').value;
    document.getElementById('editRegNumField').style.display = (role === 'student') ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    var editModal = document.getElementById('editModal');
    if(editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            editModal.querySelector('#edit_id').value = button.getAttribute('data-id');
            editModal.querySelector('#edit_fname').value = button.getAttribute('data-fname');
            editModal.querySelector('#edit_lname').value = button.getAttribute('data-lname');
            editModal.querySelector('#edit_role').value = button.getAttribute('data-role');
            editModal.querySelector('#edit_reg_num').value = button.getAttribute('data-reg') || '';
            editModal.querySelector('#edit_dept').value = button.getAttribute('data-dept');
            editModal.querySelector('#edit_coll').value = button.getAttribute('data-coll');
            
            toggleEditRegNumber();
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
