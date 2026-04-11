<?php
// study_units.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_role(['admin', 'hod', 'dean', 'lecturer']);
$role = $_SESSION['role'];

// Handle Actions (Only Admin/HOD/Dean can edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($role, ['admin', 'hod', 'dean'])) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $unit_code = sanitize_input($conn, $_POST['unit_code']);
        $title = sanitize_input($conn, $_POST['title']);
        $description = sanitize_input($conn, $_POST['description'] ?? '');
        $course_id = (int)$_POST['course_id'];
        
        if (!empty($unit_code) && !empty($title) && $course_id > 0) {
            $stmt = mysqli_prepare($conn, "INSERT INTO study_units (unit_code, title, description, course_id) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssi", $unit_code, $title, $description, $course_id);
            if (mysqli_stmt_execute($stmt)) {
                set_flash_message('success', 'Study unit added successfully.');
            } else {
                set_flash_message('danger', 'Error adding study unit.');
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $unit_code = sanitize_input($conn, $_POST['unit_code']);
        $title = sanitize_input($conn, $_POST['title']);
        $description = sanitize_input($conn, $_POST['description'] ?? '');
        $course_id = (int)$_POST['course_id'];
        
        if (!empty($unit_code) && !empty($title) && $id > 0 && $course_id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE study_units SET unit_code = ?, title = ?, description = ?, course_id = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssii", $unit_code, $title, $description, $course_id, $id);
            if (mysqli_stmt_execute($stmt)) {
                set_flash_message('success', 'Study unit updated successfully.');
            } else {
                set_flash_message('danger', 'Error updating study unit.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE study_units SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                set_flash_message('success', 'Study unit deleted successfully.');
            } else {
                set_flash_message('danger', 'Error deleting study unit.');
            }
        }
    }
    
    header("Location: study_units.php");
    exit();
}

$courses = [];
$q_courses = mysqli_query($conn, "SELECT id, code, title FROM courses WHERE deleted_at IS NULL ORDER BY code");
if ($q_courses) {
    while($row = mysqli_fetch_assoc($q_courses)) {
        $courses[] = $row;
    }
}

// Fetch Study Units
$study_units = [];
$c_query = "SELECT s.*, c.code as course_code, c.title as course_title 
            FROM study_units s 
            LEFT JOIN courses c ON s.course_id = c.id 
            WHERE s.deleted_at IS NULL ORDER BY s.unit_code";

$result = mysqli_query($conn, $c_query);
if ($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $study_units[] = $row;
    }
}
$can_edit = in_array($role, ['admin', 'hod', 'dean']);
?>

<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-list-columns-reverse"></i> Manage Study Units</h2>
    <?php if ($can_edit): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle"></i> Add Study Unit
    </button>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-4 py-3">Unit Code</th>
                        <th class="py-3">Title</th>
                        <th class="py-3">Course</th>
                        <?php if ($can_edit): ?><th class="px-4 py-3 text-end">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($study_units)): ?>
                        <tr><td colspan="<?php echo $can_edit ? '4' : '3'; ?>" class="text-center py-4">No study units found.</td></tr>
                    <?php else: ?>
                        <?php foreach($study_units as $s): ?>
                            <tr>
                                <td class="px-4 fw-bold text-primary"><?php echo htmlspecialchars($s['unit_code']); ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($s['title']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($s['course_code']); ?></span> <?php echo htmlspecialchars($s['course_title']); ?></td>
                                <?php if ($can_edit): ?>
                                <td class="px-4 text-end">
                                    <button class="btn btn-sm btn-outline-primary me-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editModal" 
                                            data-id="<?php echo $s['id']; ?>"
                                            data-code="<?php echo htmlspecialchars($s['unit_code']); ?>"
                                            data-title="<?php echo htmlspecialchars($s['title']); ?>"
                                            data-desc="<?php echo htmlspecialchars($s['description']); ?>"
                                            data-course="<?php echo $s['course_id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteModal" 
                                            data-id="<?php echo $s['id']; ?>"
                                            data-code="<?php echo htmlspecialchars($s['unit_code']); ?>">
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
                <h5 class="modal-title">Add New Unit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Course</label>
                        <select class="form-select" name="course_id" required>
                            <option value="">-- Select Course --</option>
                            <?php foreach($courses as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['code'] . ' - ' . $c['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Unit Code</label>
                            <input type="text" class="form-control" name="unit_code" placeholder="U1" required>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Unit Title</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description (Optional)</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Unit</button>
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
                <h5 class="modal-title">Edit Study Unit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">Course</label>
                        <select class="form-select" name="course_id" id="edit_course_id" required>
                            <?php foreach($courses as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['code'] . ' - ' . $c['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Unit Code</label>
                            <input type="text" class="form-control" name="unit_code" id="edit_unit_code" required>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Unit Title</label>
                            <input type="text" class="form-control" name="title" id="edit_title" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description (Optional)</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Unit</button>
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
                    <p>Are you sure you want to delete unit <strong id="delete_code"></strong>?</p>
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
    var editModal = document.getElementById('editModal');
    if(editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            editModal.querySelector('#edit_id').value = button.getAttribute('data-id');
            editModal.querySelector('#edit_unit_code').value = button.getAttribute('data-code');
            editModal.querySelector('#edit_title').value = button.getAttribute('data-title');
            editModal.querySelector('#edit_description').value = button.getAttribute('data-desc');
            editModal.querySelector('#edit_course_id').value = button.getAttribute('data-course');
        });
    }

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
