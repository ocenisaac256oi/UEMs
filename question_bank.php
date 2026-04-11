<?php
// question_bank.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_role(['admin', 'hod', 'dean', 'lecturer']);
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Handling Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)$_POST['id'];
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE questions SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            set_flash_message('success', 'Question deleted successfully.');
        } else {
            set_flash_message('danger', 'Error deleting question.');
        }
    }
    header("Location: question_bank.php");
    exit();
}

$course_filter = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$query_where = "q.deleted_at IS NULL";

// If lecturer, they might only see their own questions or questions of courses assigned to them, 
// for simplicity in this PHP port, we will allow them to see all but filter down.
if ($course_filter > 0) {
    $query_where .= " AND q.course_id = $course_filter";
}

$q_sql = "SELECT q.*, c.code as course_code, c.title as course_title, 
          u.first_name, u.last_name,
          0 as sub_questions_count
          FROM questions q
          LEFT JOIN courses c ON q.course_id = c.id
          LEFT JOIN users u ON q.created_by = u.id
          WHERE $query_where ORDER BY q.created_at DESC";

$questions = [];
$res = mysqli_query($conn, $q_sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $questions[] = $row;
    }
}

// Fetch Courses for filter
$courses = [];
$c_res = mysqli_query($conn, "SELECT id, code, title FROM courses WHERE deleted_at IS NULL ORDER BY code");
if ($c_res) {
    while ($row = mysqli_fetch_assoc($c_res)) {
        $courses[] = $row;
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-journal-text"></i> Question Bank</h2>
    <a href="add_question.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Create Question
    </a>
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
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $course_filter === (int)$c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['code'] . ' - ' . $c['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($course_filter > 0): ?>
                <div class="col-auto">
                    <a href="question_bank.php" class="btn btn-outline-secondary">Clear</a>
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
                        <th class="px-4 py-3">Course</th>
                        <th class="py-3">Question Text</th>
                        <th class="py-3">Type</th>
                        <th class="py-3">Marks</th>
                        <th class="py-3">Author</th>
                        <th class="px-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($questions)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">No questions found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($questions as $q): ?>
                            <tr>
                                <td class="px-4 fw-bold text-primary"><?php echo htmlspecialchars($q['course_code']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars(strlen($q['question_text']) > 80 ? substr($q['question_text'], 0, 80) . '...' : $q['question_text']); ?>
                                    <?php if ($q['sub_questions_count'] > 0): ?>
                                        <br><small class="text-muted"><i class="bi bi-diagram-2"></i> <?php echo $q['sub_questions_count']; ?> sub-questions</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark"><?php echo ucwords(str_replace('_', ' ', $q['question_type'])); ?></span>
                                </td>
                                <td><strong><?php echo $q['marks']; ?></strong></td>
                                <td><?php echo htmlspecialchars($q['first_name'] . ' ' . $q['last_name']); ?></td>
                                <td class="px-4 text-end text-nowrap">
                                    <a href="view_question.php?id=<?php echo $q['id']; ?>" class="btn btn-sm btn-light border"><i class="bi bi-eye"></i> View</a>
                                    <?php if ($role === 'admin' || $q['created_by'] == $user_id): ?>
                                        <button class="btn btn-sm btn-outline-danger ms-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteModal"
                                            data-id="<?php echo $q['id']; ?>">
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
                    <p>Are you sure you want to delete this question?</p>
                    <p class="text-danger small">Warning: If this question is attached to an exam paper, it will affect the paper structure.</p>
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
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                deleteModal.querySelector('#delete_id').value = button.getAttribute('data-id');
            });
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>