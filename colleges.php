<?php
// colleges.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Only admins can manage colleges
require_role('admin');

// Handle Actions (Create / Update / Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = sanitize_input($conn, $_POST['name']);
        
        if (!empty($name)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO colleges (name) VALUES (?)");
            mysqli_stmt_bind_param($stmt, "s", $name);
            if (mysqli_stmt_execute($stmt)) {
                set_flash_message('success', 'College added successfully.');
            } else {
                set_flash_message('danger', 'Error adding college.');
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $name = sanitize_input($conn, $_POST['name']);
        
        if (!empty($name) && $id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE colleges SET name = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $name, $id);
            if (mysqli_stmt_execute($stmt)) {
                set_flash_message('success', 'College updated successfully.');
            } else {
                set_flash_message('danger', 'Error updating college.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id > 0) {
            // Soft delete
            $stmt = mysqli_prepare($conn, "UPDATE colleges SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                set_flash_message('success', 'College deleted successfully.');
            } else {
                set_flash_message('danger', 'Error deleting college.');
            }
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: colleges.php");
    exit();
}

// Fetch Colleges
$colleges = [];
$query = "SELECT c.*, (SELECT COUNT(*) FROM departments WHERE college_id = c.id AND deleted_at IS NULL) as dept_count FROM colleges c WHERE c.deleted_at IS NULL ORDER BY c.name";
$result = mysqli_query($conn, $query);
if ($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $colleges[] = $row;
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-buildings"></i> Manage Colleges</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle"></i> Add College
    </button>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="py-3">College Name</th>
                        <th class="py-3">Departments</th>
                        <th class="py-3">Created At</th>
                        <th class="px-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($colleges)): ?>
                        <tr><td colspan="5" class="text-center py-4">No colleges found.</td></tr>
                    <?php else: ?>
                        <?php foreach($colleges as $c): ?>
                            <tr>
                                <td class="px-4">#<?php echo $c['id']; ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($c['name']); ?></td>
                                <td>
                                    <span class="badge bg-secondary rounded-pill"><?php echo $c['dept_count']; ?></span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                                <td class="px-4 text-end">
                                    <button class="btn btn-sm btn-outline-primary me-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editModal" 
                                            data-id="<?php echo $c['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($c['name']); ?>">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteModal" 
                                            data-id="<?php echo $c['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($c['name']); ?>">
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
                <h5 class="modal-title">Add New College</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">College Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save College</button>
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
                <h5 class="modal-title">Edit College</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">College Name</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
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
                    <p class="text-danger small">Note: This will not delete the associated departments, but may cause orphaned data.</p>
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
        var id = button.getAttribute('data-id');
        var name = button.getAttribute('data-name');
        
        editModal.querySelector('#edit_id').value = id;
        editModal.querySelector('#edit_name').value = name;
    });

    // Delete Modal Data Binding
    var deleteModal = document.getElementById('deleteModal');
    deleteModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var id = button.getAttribute('data-id');
        var name = button.getAttribute('data-name');
        
        deleteModal.querySelector('#delete_id').value = id;
        deleteModal.querySelector('#delete_name').textContent = name;
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
