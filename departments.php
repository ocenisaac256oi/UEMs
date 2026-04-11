<?php
// departments.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Only admins can manage departments
require_role('admin');

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = sanitize_input($conn, $_POST['name']);
        $college_id = (int)$_POST['college_id'];
        
        if (!empty($name) && $college_id > 0) {
            $stmt = mysqli_prepare($conn, "INSERT INTO departments (name, college_id) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "si", $name, $college_id);
            if (mysqli_stmt_execute($stmt)) {
                set_flash_message('success', 'Department added successfully.');
            } else {
                set_flash_message('danger', 'Error adding department.');
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $name = sanitize_input($conn, $_POST['name']);
        $college_id = (int)$_POST['college_id'];
        
        if (!empty($name) && $id > 0 && $college_id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE departments SET name = ?, college_id = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sii", $name, $college_id, $id);
            if (mysqli_stmt_execute($stmt)) {
                set_flash_message('success', 'Department updated successfully.');
            } else {
                set_flash_message('danger', 'Error updating department.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE departments SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                set_flash_message('success', 'Department deleted successfully.');
            } else {
                set_flash_message('danger', 'Error deleting department.');
            }
        }
    }
    
    header("Location: departments.php");
    exit();
}

// Fetch Colleges for dropdowns
$colleges = [];
$q_colleges = mysqli_query($conn, "SELECT id, name FROM colleges WHERE deleted_at IS NULL ORDER BY name");
if ($q_colleges) {
    while($row = mysqli_fetch_assoc($q_colleges)) {
        $colleges[] = $row;
    }
}

// Fetch Departments
$departments = [];
$query = "SELECT d.*, c.name as college_name FROM departments d 
          LEFT JOIN colleges c ON d.college_id = c.id 
          WHERE d.deleted_at IS NULL 
          ORDER BY d.name";
$result = mysqli_query($conn, $query);
if ($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $departments[] = $row;
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-diagram-3"></i> Manage Departments</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle"></i> Add Department
    </button>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="py-3">Department Name</th>
                        <th class="py-3">College</th>
                        <th class="py-3">Created At</th>
                        <th class="px-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($departments)): ?>
                        <tr><td colspan="5" class="text-center py-4">No departments found.</td></tr>
                    <?php else: ?>
                        <?php foreach($departments as $d): ?>
                            <tr>
                                <td class="px-4">#<?php echo $d['id']; ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($d['name']); ?></td>
                                <td><?php echo htmlspecialchars($d['college_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($d['created_at'])); ?></td>
                                <td class="px-4 text-end">
                                    <button class="btn btn-sm btn-outline-primary me-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editModal" 
                                            data-id="<?php echo $d['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($d['name']); ?>"
                                            data-college="<?php echo $d['college_id']; ?>">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteModal" 
                                            data-id="<?php echo $d['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($d['name']); ?>">
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
                <h5 class="modal-title">Add New Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Department Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">College</label>
                        <select class="form-select" name="college_id" required>
                            <option value="">-- Select College --</option>
                            <?php foreach($colleges as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Department</button>
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
                <h5 class="modal-title">Edit Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">Department Name</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">College</label>
                        <select class="form-select" name="college_id" id="edit_college_id" required>
                            <option value="">-- Select College --</option>
                            <?php foreach($colleges as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
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
                    <p>Are you sure you want to delete <strong id="delete_name"></strong>?</p>
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
    editModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        
        editModal.querySelector('#edit_id').value = button.getAttribute('data-id');
        editModal.querySelector('#edit_name').value = button.getAttribute('data-name');
        editModal.querySelector('#edit_college_id').value = button.getAttribute('data-college');
    });

    // Delete Modal Data Binding
    var deleteModal = document.getElementById('deleteModal');
    deleteModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        
        deleteModal.querySelector('#delete_id').value = button.getAttribute('data-id');
        deleteModal.querySelector('#delete_name').textContent = button.getAttribute('data-name');
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
