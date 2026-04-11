<?php
// courses.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Lecturers can view, but only admins/HODs might be able to create, for now allow admins and HODs
require_role(['admin', 'hod', 'dean', 'lecturer']);
$role = $_SESSION['role'];

// Handle Actions (Only Admin/HOD/Dean can edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($role, ['admin', 'hod', 'dean'])) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $code = sanitize_input($conn, $_POST['code']);
        $title = sanitize_input($conn, $_POST['title']);
        $credits = (int)$_POST['credits'];
        $department_id = (int)$_POST['department_id'];
        $college_id = (int)$_POST['college_id'];
        
        if (!empty($code) && !empty($title) && $department_id > 0 && $college_id > 0) {
            $stmt = mysqli_prepare($conn, "INSERT INTO courses (code, title, credits, department_id, college_id) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssiii", $code, $title, $credits, $department_id, $college_id);
            if (mysqli_stmt_execute($stmt)) {
                set_flash_message('success', 'Course added successfully.');
            } else {
                set_flash_message('danger', 'Error adding course. Check if code already exists.');
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $code = sanitize_input($conn, $_POST['code']);
        $title = sanitize_input($conn, $_POST['title']);
        $credits = (int)$_POST['credits'];
        $department_id = (int)$_POST['department_id'];
        $college_id = (int)$_POST['college_id'];
        
        if (!empty($code) && !empty($title) && $id > 0 && $department_id > 0 && $college_id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE courses SET code = ?, title = ?, credits = ?, department_id = ?, college_id = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssiiii", $code, $title, $credits, $department_id, $college_id, $id);
            if (mysqli_stmt_execute($stmt)) {
                set_flash_message('success', 'Course updated successfully.');
            } else {
                set_flash_message('danger', 'Error updating course.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE courses SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                set_flash_message('success', 'Course deleted successfully.');
            } else {
                set_flash_message('danger', 'Error deleting course.');
            }
        }
    }
    
    header("Location: courses.php");
    exit();
}

// Fetch Dropdown data
$departments = [];
$q_depts = mysqli_query($conn, "SELECT id, name, college_id FROM departments WHERE deleted_at IS NULL ORDER BY name");
if ($q_depts) {
    while($row = mysqli_fetch_assoc($q_depts)) {
        $departments[] = $row;
    }
}

$colleges = [];
$q_colleges = mysqli_query($conn, "SELECT id, name FROM colleges WHERE deleted_at IS NULL ORDER BY name");
if ($q_colleges) {
    while($row = mysqli_fetch_assoc($q_colleges)) {
        $colleges[] = $row;
    }
}

// Fetch Courses based on role
$courses = [];
if ($role === 'admin' || $role === 'exam_master') {
    $c_query = "SELECT c.*, d.name as dept_name, col.name as col_name 
                FROM courses c 
                LEFT JOIN departments d ON c.department_id = d.id 
                LEFT JOIN colleges col ON c.college_id = col.id 
                WHERE c.deleted_at IS NULL ORDER BY c.code";
} elseif ($role === 'dean') {
    $col_id = $_SESSION['college_id'];
    $c_query = "SELECT c.*, d.name as dept_name, col.name as col_name 
                FROM courses c 
                LEFT JOIN departments d ON c.department_id = d.id 
                LEFT JOIN colleges col ON c.college_id = col.id 
                WHERE c.deleted_at IS NULL AND c.college_id = $col_id ORDER BY c.code";
} elseif ($role === 'hod') {
    $dep_id = $_SESSION['department_id'];
    $c_query = "SELECT c.*, d.name as dept_name, col.name as col_name 
                FROM courses c 
                LEFT JOIN departments d ON c.department_id = d.id 
                LEFT JOIN colleges col ON c.college_id = col.id 
                WHERE c.deleted_at IS NULL AND c.department_id = $dep_id ORDER BY c.code";
} else {
    // Lecturer sees all or assigned (for now let's show all for simplicity, or assigned in a robust system)
    $c_query = "SELECT c.*, d.name as dept_name, col.name as col_name 
                FROM courses c 
                LEFT JOIN departments d ON c.department_id = d.id 
                LEFT JOIN colleges col ON c.college_id = col.id 
                WHERE c.deleted_at IS NULL ORDER BY c.code";
}

$result = mysqli_query($conn, $c_query);
if ($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $courses[] = $row;
    }
}
$can_edit = in_array($role, ['admin', 'hod', 'dean']);
?>

<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-journal-bookmark-fill"></i> Manage Courses</h2>
    <?php if ($can_edit): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle"></i> Add Course
    </button>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="py-3">Course Title</th>
                        <th class="py-3">Credits</th>
                        <th class="py-3">Department</th>
                        <th class="py-3">College</th>
                        <?php if ($can_edit): ?><th class="px-4 py-3 text-end">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($courses)): ?>
                        <tr><td colspan="<?php echo $can_edit ? '6' : '5'; ?>" class="text-center py-4">No courses found.</td></tr>
                    <?php else: ?>
                        <?php foreach($courses as $c): ?>
                            <tr>
                                <td class="px-4 fw-bold text-primary"><?php echo htmlspecialchars($c['code']); ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($c['title']); ?></td>
                                <td><?php echo $c['credits']; ?></td>
                                <td><?php echo htmlspecialchars($c['dept_name']); ?></td>
                                <td><?php echo htmlspecialchars($c['col_name']); ?></td>
                                <?php if ($can_edit): ?>
                                <td class="px-4 text-end">
                                    <button class="btn btn-sm btn-outline-primary me-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editModal" 
                                            data-id="<?php echo $c['id']; ?>"
                                            data-code="<?php echo htmlspecialchars($c['code']); ?>"
                                            data-title="<?php echo htmlspecialchars($c['title']); ?>"
                                            data-credits="<?php echo $c['credits']; ?>"
                                            data-dept="<?php echo $c['department_id']; ?>"
                                            data-college="<?php echo $c['college_id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteModal" 
                                            data-id="<?php echo $c['id']; ?>"
                                            data-code="<?php echo htmlspecialchars($c['code']); ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($can_edit): ?>
<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Code</label>
                            <input type="text" class="form-control" name="code" placeholder="CS101" required>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Credits</label>
                        <input type="number" class="form-control" name="credits" min="1" max="10" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">College</label>
                        <select class="form-select" name="college_id" id="add_college" required>
                            <option value="">-- Select College --</option>
                            <?php foreach($colleges as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select class="form-select" name="department_id" id="add_department" required>
                            <option value="">-- Select Department --</option>
                            <?php foreach($departments as $d): ?>
                                <option value="<?php echo $d['id']; ?>" data-college="<?php echo $d['college_id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Code</label>
                            <input type="text" class="form-control" name="code" id="edit_code" required>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" id="edit_title" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Credits</label>
                        <input type="number" class="form-control" name="credits" id="edit_credits" min="1" max="10" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">College</label>
                        <select class="form-select" name="college_id" id="edit_college_id" required>
                            <?php foreach($colleges as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select class="form-select" name="department_id" id="edit_department_id" required>
                            <?php foreach($departments as $d): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    <p>Are you sure you want to delete course <strong id="delete_code"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit Modal Binding
    var editModal = document.getElementById('editModal');
    if(editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            editModal.querySelector('#edit_id').value = button.getAttribute('data-id');
            editModal.querySelector('#edit_code').value = button.getAttribute('data-code');
            editModal.querySelector('#edit_title').value = button.getAttribute('data-title');
            editModal.querySelector('#edit_credits').value = button.getAttribute('data-credits');
            editModal.querySelector('#edit_college_id').value = button.getAttribute('data-college');
            editModal.querySelector('#edit_department_id').value = button.getAttribute('data-dept');
        });
    }

    // Delete Modal Binding
    var deleteModal = document.getElementById('deleteModal');
    if(deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            deleteModal.querySelector('#delete_id').value = button.getAttribute('data-id');
            deleteModal.querySelector('#delete_code').textContent = button.getAttribute('data-code');
        });
    }
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
