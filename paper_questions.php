<?php
// paper_questions.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_role(['admin', 'hod', 'lecturer']);
$user_id = $_SESSION['user_id'];
$paper_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($paper_id === 0) {
    header("Location: exam_papers.php");
    exit();
}

// Fetch Paper
$p_q = mysqli_query($conn, "SELECT p.*, c.code as course_code, c.title FROM exam_papers p JOIN courses c ON p.course_id = c.id WHERE p.id = $paper_id");
if (!$p_q || mysqli_num_rows($p_q) === 0) {
    header("Location: exam_papers.php");
    exit();
}
$paper = mysqli_fetch_assoc($p_q);

// Only draft status allowed to be edited usually, and only by author
if ($paper['status'] !== 'draft') {
    set_flash_message('warning', 'Only draft papers can be modified.');
    header("Location: view_paper.php?id=" . $paper_id);
    exit();
}

// Handle Add/Remove
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $question_id = (int)$_POST['question_id'];

    if ($action === 'add' && $question_id > 0) {
        // Calculate next sequence order
        $seq_res = mysqli_query($conn, "SELECT MAX(sequence_order) as max_seq FROM exam_paper_questions WHERE exam_paper_id = $paper_id");
        $max_seq = 0;
        if ($seq_res && $row = mysqli_fetch_assoc($seq_res)) {
            $max_seq = (int)$row['max_seq'];
        }
        $next_seq = $max_seq + 1;
        
        $sql = "INSERT INTO exam_paper_questions (exam_paper_id, question_id, section, sequence_order) VALUES ($paper_id, $question_id, 'A', $next_seq)";
        if (!mysqli_query($conn, $sql)) {
            set_flash_message('danger', 'System failed to attach question: ' . mysqli_error($conn));
            header("Location: paper_questions.php?id=" . $paper_id);
            exit();
        }
        
        // Update total marks explicitly based on question marks
        mysqli_query($conn, "UPDATE exam_papers SET total_marks = (
            SELECT COALESCE(SUM(q.marks), 0) FROM exam_paper_questions epq 
            JOIN questions q ON epq.question_id = q.id 
            WHERE epq.exam_paper_id = $paper_id
        ) WHERE id = $paper_id");
        
    } elseif ($action === 'remove' && $question_id > 0) {
        $sql = "DELETE FROM exam_paper_questions WHERE exam_paper_id = $paper_id AND question_id = $question_id";
        mysqli_query($conn, $sql);
        
        // Update total marks explicitly based on question marks
        mysqli_query($conn, "UPDATE exam_papers SET total_marks = (
            SELECT COALESCE(SUM(q.marks), 0) FROM exam_paper_questions epq 
            JOIN questions q ON epq.question_id = q.id 
            WHERE epq.exam_paper_id = $paper_id
        ) WHERE id = $paper_id");
    }
    
    header("Location: paper_questions.php?id=" . $paper_id);
    exit();
}

// Fetch currently attached questions
$attached = [];
$att_q = mysqli_query($conn, "SELECT epq.id as eq_id, epq.question_id, q.question_text, q.marks, epq.section, epq.sequence_order
                              FROM exam_paper_questions epq 
                              JOIN questions q ON epq.question_id = q.id 
                              WHERE epq.exam_paper_id = $paper_id ORDER BY epq.section, epq.sequence_order");
$attached_ids = [];
if ($att_q) {
    while($row = mysqli_fetch_assoc($att_q)) {
        $attached[] = $row;
        $attached_ids[] = (int)$row['question_id'];
    }
}

// Fetch available questions from Bank for this course
$available = [];
$av_q = mysqli_query($conn, "SELECT id, question_text, marks FROM questions WHERE course_id = {$paper['course_id']} AND deleted_at IS NULL");
if ($av_q) {
    while($row = mysqli_fetch_assoc($av_q)) {
        if (!in_array((int)$row['id'], $attached_ids)) {
            $available[] = $row;
        }
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1"><i class="bi bi-ui-checks-grid"></i> Manage Paper Structure</h2>
                <p class="text-muted mb-0">Adding questions to <?php echo htmlspecialchars($paper['paper_code']); ?> (<?php echo htmlspecialchars($paper['course_code']); ?>)</p>
            </div>
            <div>
                <a href="view_paper.php?id=<?php echo $paper_id; ?>" class="btn btn-primary"><i class="bi bi-eye"></i> Preview Paper</a>
                <a href="exam_papers.php" class="btn btn-outline-secondary">Done</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Selected Questions Column -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between">
                <h5 class="mb-0 fw-bold">Selected Questions</h5>
                <span class="badge bg-primary rounded-pill"><?php echo count($attached); ?> Questions / <?php echo $paper['total_marks']; ?> Marks</span>
            </div>
            <div class="card-body px-4">
                <?php if(empty($attached)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-question-square display-4 border border-2 border-dashed rounded p-3 d-inline-block text-secondary opacity-50 mb-3"></i>
                        <p>No questions selected yet.<br>Add questions from the bank on the right.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach($attached as $index => $q): ?>
                            <div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-bold me-2">Q<?php echo $index + 1; ?>.</span>
                                    <span><?php echo htmlspecialchars(strlen($q['question_text']) > 80 ? substr($q['question_text'],0,80).'...' : $q['question_text']); ?></span>
                                    <span class="badge bg-light text-dark border ms-2"><?php echo $q['marks']; ?> marks</span>
                                    <span class="badge bg-secondary ms-1">Sec: <?php echo $q['section']; ?></span>
                                </div>
                                <form method="POST" class="d-inline m-0 p-0">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="question_id" value="<?php echo $q['question_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Available Questions Column -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 bg-light">
            <div class="card-header bg-transparent border-bottom-0 pt-4 pb-2 px-4">
                <h5 class="mb-0 fw-bold">Question Bank <span class="badge bg-secondary ms-1 fs-6"><?php echo count($available); ?> available</span></h5>
            </div>
            <div class="card-body px-4 pb-4">
                <?php if(empty($available)): ?>
                    <div class="alert alert-info">No available questions in the bank for this course. <a href="add_question.php" class="alert-link">Create one now</a>.</div>
                <?php else: ?>
                    <div style="max-height: 600px; overflow-y: auto;">
                        <div class="list-group drop-shadow-sm">
                            <?php foreach($available as $av): ?>
                                <div class="list-group-item list-group-item-action py-3 border-0 rounded mb-2 shadow-sm">
                                    <div class="d-flex w-100 justify-content-between mb-2">
                                        <b class="text-primary"><?php echo $av['marks']; ?> Marks</b>
                                        <form method="POST" class="d-inline m-0 p-0">
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="question_id" value="<?php echo $av['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">Add <i class="bi bi-plus"></i></button>
                                        </form>
                                    </div>
                                    <p class="mb-1 text-muted small"><?php echo htmlspecialchars($av['question_text']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
