<?php
// schedule_exams.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_role(['admin', 'exam_master', 'dean']);
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Handle Actions (Schedule actions)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $paper_id = (int)$_POST['paper_id'];
    
    if ($paper_id > 0) {
        if ($action === 'publish_exam') {
            $exam_date = sanitize_input($conn, $_POST['exam_date']);
            
            mysqli_query($conn, "UPDATE exam_papers SET status = 'published', exam_date = '$exam_date', published_at = CURRENT_TIMESTAMP WHERE id = $paper_id");
            
            // Log history
            mysqli_query($conn, "INSERT INTO workflow_history (exam_paper_id, action, from_status, to_status, actor_id, actor_role) 
                                 VALUES ($paper_id, 'published', 'ready_for_print', 'published', $user_id, '$role')");
                                 
            set_flash_message('success', 'Exam successfully published and scheduled for students!');
        } elseif ($action === 'unpublish_exam') {
            mysqli_query($conn, "UPDATE exam_papers SET status = 'ready_for_print', published_at = NULL WHERE id = $paper_id");
            
            // Log history
            mysqli_query($conn, "INSERT INTO workflow_history (exam_paper_id, action, from_status, to_status, actor_id, actor_role) 
                                 VALUES ($paper_id, 'returned', 'published', 'ready_for_print', $user_id, '$role')");
                                 
            set_flash_message('info', 'Exam unpublished and returned to scheduling queue.');
        }
    }
    
    header("Location: schedule_exams.php");
    exit();
}

$status_filter = isset($_GET['status']) ? sanitize_input($conn, $_GET['status']) : 'ready_for_print';

// map 'ready_for_print' to 'pending_schedule' conceptually
$query_where = "ep.deleted_at IS NULL AND ep.status = '$status_filter'";

// Dean can only see their college, Exam Master/Admin sees all
if ($role === 'dean') {
    $col_id = $_SESSION['college_id'];
    $query_where .= " AND c.college_id = $col_id";
}

$sql = "SELECT ep.*, c.code as course_code, c.title as course_title
        FROM exam_papers ep
        JOIN courses c ON ep.course_id = c.id
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

<div class="row align-items-center mb-4">
    <div class="col-md-6">
        <h2><i class="bi bi-calendar-event"></i> Schedule Online Exams</h2>
    </div>
    <div class="col-md-6 text-md-end">
        <div class="btn-group" role="group">
            <a href="schedule_exams.php?status=ready_for_print" class="btn <?php echo $status_filter === 'ready_for_print' ? 'btn-primary' : 'btn-outline-primary'; ?>">Pending Schedule</a>
            <a href="schedule_exams.php?status=published" class="btn <?php echo $status_filter === 'published' ? 'btn-primary' : 'btn-outline-primary'; ?>">Published (Live)</a>
        </div>
    </div>
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i> Once an exam is <strong>Published</strong>, it will automatically become available to students on the specified Exam Date.
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-4 py-3">Paper Code</th>
                        <th class="py-3">Course</th>
                        <th class="py-3">Status</th>
                        <?php if($status_filter === 'ready_for_print'): ?>
                            <th class="py-3">Suggested Date</th>
                        <?php elseif($status_filter === 'published'): ?>
                            <th class="py-3">Live Date</th>
                            <th class="py-3">Published At</th>
                        <?php endif; ?>
                        <th class="px-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($papers)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No exams in this queue.</td></tr>
                    <?php else: ?>
                        <?php foreach($papers as $p): ?>
                            <tr>
                                <td class="px-4 fw-bold text-primary"><?php echo htmlspecialchars($p['paper_code']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($p['course_code']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($p['course_title']); ?></small>
                                </td>
                                <td>
                                    <?php if($p['status'] == 'ready_for_print'): ?>
                                        <span class="badge bg-warning rounded-pill px-3 py-2 text-dark">PENDING SCHEDULE</span>
                                    <?php else: ?>
                                        <span class="badge bg-success rounded-pill px-3 py-2">PUBLISHED</span>
                                    <?php endif; ?>
                                </td>
                                
                                <?php if($status_filter === 'ready_for_print'): ?>
                                    <td><?php echo $p['exam_date'] ? date('M d, Y', strtotime($p['exam_date'])) : '<span class="text-muted">Not set</span>'; ?></td>
                                <?php elseif($status_filter === 'published'): ?>
                                    <td class="fw-bold text-success"><?php echo date('M d, Y', strtotime($p['exam_date'])); ?></td>
                                    <td><?php echo date('M d, g:i A', strtotime($p['published_at'])); ?></td>
                                <?php endif; ?>
                                
                                <td class="px-4 text-end text-nowrap">
                                    <a href="view_paper.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-eye"></i> View</a>
                                    
                                    <?php if($p['status'] === 'ready_for_print'): ?>
                                        <button class="btn btn-sm btn-success ms-1" data-bs-toggle="modal" data-bs-target="#publishModal" data-id="<?php echo $p['id']; ?>" data-date="<?php echo $p['exam_date']; ?>"><i class="bi bi-calendar-check"></i> Set Live Date</button>
                                    <?php elseif($p['status'] === 'published'): ?>
                                        <form method="POST" class="d-inline ms-1" onsubmit="return confirm('Are you sure you want to unpublish this exam? Students will immediately lose access!');">
                                            <input type="hidden" name="paper_id" value="<?php echo $p['id']; ?>">
                                            <input type="hidden" name="action" value="unpublish_exam">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-x-circle"></i> Unpublish</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Publish Modal -->
<div class="modal fade" id="publishModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Publish Exam Online</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="publish_exam">
                    <input type="hidden" name="paper_id" id="publish_id">
                    <p>Select the exact date this exam should become available for students to take.</p>
                    <div class="mb-3">
                        <label class="form-label">Official Exam Date</label>
                        <input type="date" class="form-control" name="exam_date" id="publish_date" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-globe"></i> Publish Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var publishModal = document.getElementById('publishModal');
    if(publishModal) {
        publishModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            publishModal.querySelector('#publish_id').value = button.getAttribute('data-id');
            var proposedDate = button.getAttribute('data-date');
            if (proposedDate) {
                publishModal.querySelector('#publish_date').value = proposedDate;
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
