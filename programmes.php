<?php
// programmes.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Only admins can manage programmes globally, but let's allow it for now.
require_role('admin');

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $code = sanitize_input($conn, $_POST['code']);
        $name = sanitize_input($conn, $_POST['name']);
        $level = sanitize_input($conn, $_POST['level']);
        $department_id = (int)$_POST['department_id'];

        if (!empty($code) && !empty($name) && $department_id > 0) {
            $stmt = mysqli_prepare($conn, "INSERT INTO programmes (code, name, level, department_id) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssi", $code, $name, $level, $department_id);
            if (mysqli_stmt_execute($stmt)) {
                set_flash_message('success', 'Programme added successfully.');
            } else {
                set_flash_message('danger', 'Error adding programme.');
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $code = sanitize_input($conn, $_POST['code']);
        $name = sanitize_input($conn, $_POST['name']);
        $level = sanitize_input($conn, $_POST['level']);
        $department_id = (int)$_POST['department_id'];

        if (!empty($code) && !empty($name) && $id > 0 && $department_id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE programmes SET code = ?, name = ?, level = ?, department_id = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssii", $code, $name, $level, $department_id, $id);
            if (mysqli_stmt_execute($stmt)) {
                set_flash_message('success', 'Programme updated successfully.');
            } else {
                set_flash_message('danger', 'Error updating programme.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE programmes SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                set_flash_message('success', 'Programme deleted successfully.');
            } else {
                set_flash_message('danger', 'Error deleting programme.');
            }
        }
    }

    header("Location: programmes.php");
    exit();
}

// Fetch Departments for dropdowns
$departments = [];
$q_depts = mysqli_query($conn, "SELECT id, name FROM departments WHERE deleted_at IS NULL ORDER BY name");
if ($q_depts) {
    while ($row = mysqli_fetch_assoc($q_depts)) {
        $departments[] = $row;
    }
}

// Fetch Programmes
$programmes = [];
$query = "SELECT p.*, d.name as department_name FROM programmes p 
          LEFT JOIN departments d ON p.department_id = d.id 
          WHERE p.deleted_at IS NULL 
          ORDER BY p.code";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $programmes[] = $row;
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-book"></i> Manage Programmes</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle"></i> Add Programme
    </button>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="py-3">Programme Name</th>
                        <th class="py-3">Level</th>
                        <th class="py-3">Department</th>
                        <th class="px-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($programmes)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">No programmes found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($programmes as $p): ?>
                            <tr>
                                <td class="px-4 fw-bold text-primary"><?php echo htmlspecialchars($p['code']); ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($p['name']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo ucfirst(htmlspecialchars($p['level'])); ?></span></td>
                                <td><?php echo htmlspecialchars($p['department_name']); ?></td>
                                <td class="px-4 text-end">
                                    <button class="btn btn-sm btn-outline-primary me-1"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editModal"
                                        data-id="<?php echo $p['id']; ?>"
                                        data-code="<?php echo htmlspecialchars($p['code']); ?>"
                                        data-name="<?php echo htmlspecialchars($p['name']); ?>"
                                        data-level="<?php echo htmlspecialchars($p['level']); ?>"
                                        data-dept="<?php echo $p['department_id']; ?>">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteModal"
                                        data-id="<?php echo $p['id']; ?>"
                                        data-code="<?php echo htmlspecialchars($p['code']); ?>">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Programme</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Programme Code</label>
                        <input type="text" class="form-control" name="code" placeholder="e.g. BCS" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Programme Name</label>
                        <input type="text" class="form-control" name="name" placeholder="e.g. Bachelor of Computer Science" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Level</label>
                        <select class="form-select" name="level" required>
                            <option value="bachelors">Bachelors</option>
                            <option value="masters">Masters</option>
                            <option value="phd">PhD</option>
                            <option value="diploma">Diploma</option>
                            <option value="certificate">Certificate</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select class="form-select" name="department_id" required>
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Programme</button>
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
                <h5 class="modal-title">Edit Programme</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">Programme Code</label>
                        <input type="text" class="form-control" name="code" id="edit_code" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Programme Name</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Level</label>
                        <select class="form-select" name="level" id="edit_level" required>
                            <option value="bachelors">Bachelors</option>
                            <option value="masters">Masters</option>
                            <option value="phd">PhD</option>
                            <option value="diploma">Diploma</option>
                            <option value="certificate">Certificate</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select class="form-select" name="department_id" id="edit_department_id" required>
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
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
                    <p>Are you sure you want to delete programme <strong id="delete_code"></strong>?</p>
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
        // Edit Modal Data Binding
        var editModal = document.getElementById('editModal');
        editModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;

            editModal.querySelector('#edit_id').value = button.getAttribute('data-id');
            editModal.querySelector('#edit_code').value = button.getAttribute('data-code');
            editModal.querySelector('#edit_name').value = button.getAttribute('data-name');
            editModal.querySelector('#edit_level').value = button.getAttribute('data-level');
            editModal.querySelector('#edit_department_id').value = button.getAttribute('data-dept');
        });

        // Delete Modal Data Binding
        var deleteModal = document.getElementById('deleteModal');
        deleteModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;

            deleteModal.querySelector('#delete_id').value = button.getAttribute('data-id');
            deleteModal.querySelector('#delete_code').textContent = button.getAttribute('data-code');
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>