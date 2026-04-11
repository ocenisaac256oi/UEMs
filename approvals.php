<?php
// approvals.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_role(['admin', 'hod', 'dean']);
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Handle Approvals
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paper_id = (int)$_POST['paper_id'];
    $action = $_POST['action'] ?? ''; // 'approve' or 'reject'
    
    // Determine the current state and new state based on role
    // Flow: submitted -> hod_approved -> dean_approved -> ready_for_print
    if ($paper_id > 0) {
        // fetch paper
        $pq = mysqli_query($conn, "SELECT status FROM exam_papers WHERE id = $paper_id");
        if ($pq && mysqli_num_rows($pq) > 0) {
            $p = mysqli_fetch_assoc($pq);
            $new_status = '';
            
            if ($action === 'approve') {
                if ($role === 'hod' && $p['status'] === 'submitted') {
                    $new_status = 'published';
                } elseif ($role === 'dean' && $p['status'] === 'hod_approved') {
                    $new_status = 'published';
                }
            } elseif ($action === 'reject') {
                $new_status = 'draft';
            }
            
            if (!empty($new_status)) {
                $sql_update = "UPDATE exam_papers SET status = '$new_status'";
                if ($new_status === 'ready_for_print') {
                    $sql_update .= ", dean_approved_at = CURRENT_TIMESTAMP";
                } elseif ($new_status === 'hod_approved') {
                    $sql_update .= ", hod_approved_at = CURRENT_TIMESTAMP";
                }
                
                $sql_update .= " WHERE id = $paper_id";
                mysqli_query($conn, $sql_update);
                
                // Add workflow history
                $action_log = $action === 'approve' ? 'approved' : 'rejected';
                mysqli_query($conn, "INSERT INTO workflow_history (exam_paper_id, action, from_status, to_status, actor_id, actor_role) 
                                     VALUES ($paper_id, '$action_log', '{$p['status']}', '$new_status', $user_id, '$role')");
                                     
                set_flash_message('success', "Paper successfully $action_log.");
            } else {
                set_flash_message('danger', "Invalid state transition.");
            }
        }
    }
    header("Location: approvals.php");
    exit();
}

// Fetch papers needing approval
$query_where = "ep.deleted_at IS NULL AND ";

if ($role === 'hod') {
    // For testing purposes, allow ANY Head of Department to see EVERY submitted paper across all departments
    $query_where .= "ep.status = 'submitted'";
} elseif ($role === 'dean') {
    $col_id = (int)($_SESSION['college_id'] ?? 0);
    $query_where .= "(ep.dean_id = $user_id OR c.college_id = $col_id) AND ep.status IN ('submitted', 'hod_approved')";
} else { // admin
    $query_where .= "0 = 1"; // Admins should not see pending approvals as they aren't allowed to approve
}

$sql = "SELECT ep.*, c.code as course_code, c.title as course_title,
        CONCAT(u.first_name, ' ', u.last_name) as creator_name
        FROM exam_papers ep
        JOIN courses c ON ep.course_id = c.id
        LEFT JOIN users u ON ep.created_by = u.id
        WHERE $query_where ORDER BY ep.created_at ASC";

$papers = [];
$res = mysqli_query($conn, $sql);
if ($res) {
    while($row = mysqli_fetch_assoc($res)) {
        $papers[] = $row;
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-check2-square"></i> Pending Approvals</h2>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-4 py-3">Paper Code</th>
                        <th class="py-3">Course</th>
                        <th class="py-3">Current Status</th>
                        <th class="py-3">Submitted By</th>
                        <th class="px-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($papers)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No papers currently require your approval.</td></tr>
                    <?php else: ?>
                        <?php foreach($papers as $p): ?>
                            <tr>
                                <td class="px-4 fw-bold text-primary"><?php echo htmlspecialchars($p['paper_code']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($p['course_code']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($p['course_title']); ?></small>
                                </td>
                                <td>
                                    <span class="badge <?php echo get_status_badge_class($p['status']); ?> rounded-pill px-3 py-2">
                                        <?php echo strtoupper(str_replace('_', ' ', $p['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($p['creator_name']); ?></td>
                                <td class="px-4 text-end text-nowrap">
                                    <a href="view_paper.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-eye"></i> Review Paper</a>
                                    
                                    <form method="POST" class="d-inline ms-1">
                                        <input type="hidden" name="paper_id" value="<?php echo $p['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this paper?')"><i class="bi bi-check-lg"></i> Approve</button>
                                    </form>
                                    
                                    <button class="btn btn-sm btn-danger ms-1" data-bs-toggle="modal" data-bs-target="#rejectModal" data-id="<?php echo $p['id']; ?>"><i class="bi bi-x-lg"></i> Reject</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Reject Paper</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="paper_id" id="reject_id">
                    <p>Are you sure you want to reject this paper and send it back to draft?</p>
                    <div class="mb-3">
                        <label class="form-label">Feedback / Reason (Optional)</label>
                        <textarea class="form-control" name="feedback" rows="3" placeholder="Explain what needs to be changed..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Paper</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var rejectModal = document.getElementById('rejectModal');
    if(rejectModal) {
        rejectModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            rejectModal.querySelector('#reject_id').value = button.getAttribute('data-id');
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
