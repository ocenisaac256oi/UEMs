<?php
// exam_papers.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_role(['admin', 'hod', 'dean', 'lecturer', 'exam_master']);
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Handling Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)$_POST['id'];
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE exam_papers SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND created_by = ?");
        mysqli_stmt_bind_param($stmt, "ii", $id, $user_id); // Basic check: only creator can delete it
        // Admins might need bypass in a real scenario
        if (mysqli_stmt_execute($stmt)) {
            set_flash_message('success', 'Exam paper deleted successfully.');
        } else {
            set_flash_message('danger', 'Error deleting exam paper.');
        }
    }
    header("Location: exam_papers.php");
    exit();
}

// Fetch Filters
$course_filter = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$query_where = "ep.deleted_at IS NULL";

// Role-based visibility rules
if ($role === 'lecturer') {
    $query_where .= " AND ep.created_by = $user_id";
} elseif ($role === 'hod') {
    $dep_id = $_SESSION['department_id'];
    $query_where .= " AND c.department_id = $dep_id";
} elseif ($role === 'dean') {
    $col_id = $_SESSION['college_id'];
    $query_where .= " AND c.college_id = $col_id";
}
// Exam Masters and Admins see all papers that are submitted or later. 
// For simplicity, let's just use the filter for all.

if ($course_filter > 0) {
    $query_where .= " AND ep.course_id = $course_filter";
}

$sql = "SELECT ep.*, c.code as course_code, c.title as course_title,
        CONCAT(u.first_name, ' ', u.last_name) as creator_name
        FROM exam_papers ep
        LEFT JOIN courses c ON ep.course_id = c.id
        LEFT JOIN users u ON ep.created_by = u.id
        WHERE $query_where ORDER BY ep.created_at DESC";

$papers = [];
$res = mysqli_query($conn, $sql);
if ($res) {
    while($row = mysqli_fetch_assoc($res)) {
        $papers[] = $row;
    }
}

// Fetch Courses for filter
$courses = [];
$c_res = mysqli_query($conn, "SELECT id, code, title FROM courses WHERE deleted_at IS NULL ORDER BY code");
if ($c_res) {
    while($row = mysqli_fetch_assoc($c_res)) {
        $courses[] = $row;
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-file-earmark-text"></i> Exam Papers</h2>
    <?php if(in_array($role, ['lecturer', 'hod', 'admin'])): ?>
    <a href="create_paper.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Create New Paper
    </a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-auto">
                <label for="course_id" class="col-form-label fw-bold">Filter by Course:</label>
            </div>
            <div class="col-md-6 col-sm-8">
                <select name="course_id" id="course_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">All Courses</option>
                    <?php foreach($courses as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $course_filter === (int)$c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['code'] . ' - ' . $c['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if($course_filter > 0): ?>
            <div class="col-auto">
                <a href="exam_papers.php" class="btn btn-outline-secondary">Clear</a>
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
                        <th class="px-4 py-3">Paper Code</th>
                        <th class="py-3">Course</th>
                        <th class="py-3">Details</th>
                        <th class="py-3">Status</th>
                        <th class="py-3">Creator</th>
                        <th class="px-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($papers)): ?>
                        <tr><td colspan="6" class="text-center py-4">No exam papers found.</td></tr>
                    <?php else: ?>
                        <?php foreach($papers as $p): ?>
                            <tr>
                                <td class="px-4 fw-bold text-primary"><?php echo htmlspecialchars($p['paper_code']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($p['course_code']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($p['course_title']); ?></small>
                                </td>
                                <td>
                                    <div class="small">
                                        <div><i class="bi bi-calendar"></i> <?php echo $p['academic_year']; ?> - Sem <?php echo $p['semester']; ?></div>
                                        <div><i class="bi bi-clock"></i> <?php echo $p['duration']; ?> mins, <?php echo $p['total_marks']; ?> marks</div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo get_status_badge_class($p['status']); ?> rounded-pill px-3 py-2">
                                        <?php echo strtoupper(str_replace('_', ' ', $p['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($p['creator_name']); ?></td>
                                <td class="px-4 text-end text-nowrap">
                                    <a href="view_paper.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-primary"><i class="bi bi-eye"></i> View</a>
                                    
                                    <?php if ($p['status'] === 'draft' && $p['created_by'] == $user_id): ?>
                                    <a href="paper_questions.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-secondary ms-1"><i class="bi bi-list-check"></i> Add Qs</a>
                                    <button class="btn btn-sm btn-outline-danger ms-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteModal" 
                                            data-id="<?php echo $p['id']; ?>"
                                            data-code="<?php echo htmlspecialchars($p['paper_code']); ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
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
                    <p>Are you sure you want to delete paper <strong id="delete_code"></strong>?</p>
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

<?php require_once 'includes/footer.php'; ?>
