<?php
// student_dashboard.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_role(['student']);
$user_id = $_SESSION['user_id'];
$department_id = $_SESSION['department_id'] ?? 0;
$college_id = $_SESSION['college_id'] ?? 0;

// Query live (published/approved) exams matching the student's department or college constraints (if assigned)
$where_clauses = ["ep.status IN ('hod_approved', 'ready_for_print', 'published')", "ep.deleted_at IS NULL"];

if ($department_id > 0) {
    $where_clauses[] = "(c.department_id = $department_id OR c.department_id IS NULL)";
} elseif ($college_id > 0) {
    $where_clauses[] = "(c.college_id = $college_id OR c.college_id IS NULL)";
}

$where_stmt = implode(' AND ', $where_clauses);

// Fetch upcoming exams (where exam_date is >= today)
$sql = "SELECT ep.*, c.code as course_code, c.title as course_title,
        (SELECT status FROM exam_sessions es WHERE es.exam_paper_id = ep.id AND es.student_id = $user_id LIMIT 1) as my_session_status,
        (SELECT total_score FROM exam_results er WHERE er.exam_paper_id = ep.id AND er.student_id = $user_id LIMIT 1) as my_score
        FROM exam_papers ep
        JOIN courses c ON ep.course_id = c.id
        WHERE $where_stmt
        ORDER BY ep.exam_date ASC";

$exams = [];
$res = mysqli_query($conn, $sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $exams[] = $row;
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="row align-items-center mb-4">
    <div class="col-md-8">
        <h2><i class="bi bi-controller"></i> My Examinations</h2>
        <p class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>. Here are your scheduled online exams.</p>
    </div>
</div>

<div class="row g-4">
    <?php if (empty($exams)): ?>
        <div class="col-12 text-center py-5">
            <h4 class="text-muted"><i class="bi bi-emoji-smile"></i> No exams currently scheduled for your department.</h4>
        </div>
    <?php else: ?>
        <?php foreach ($exams as $ex):
            $is_ready = true; // Exam is ready immediately after approval based on new requirement
            $is_past = false;
            $is_today = false; // Prevent undefined variable warning
            $session_status = $ex['my_session_status'];
        ?>
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 h-100 <?php echo $is_today && !$session_status ? 'border-success border-2' : ''; ?>">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between mb-3">
                            <span class="badge bg-primary rounded-pill"><?php echo htmlspecialchars($ex['course_code']); ?></span>
                            <?php if ($session_status === 'completed'): ?>
                                <span class="badge bg-success rounded-pill"><i class="bi bi-check-circle"></i> Completed</span>
                            <?php elseif ($session_status === 'in_progress'): ?>
                                <span class="badge bg-warning rounded-pill text-dark"><i class="bi bi-clock-history"></i> In Progress</span>
                            <?php elseif ($is_ready): ?>
                                <span class="badge bg-danger rounded-pill"><i class="bi bi-record-circle"></i> AVAILABLE</span>
                            <?php elseif ($is_past): ?>
                                <span class="badge bg-secondary rounded-pill">MISSED</span>
                            <?php else: ?>
                                <span class="badge bg-info rounded-pill">UPCOMING</span>
                            <?php endif; ?>
                        </div>

                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($ex['course_title']); ?></h5>
                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($ex['paper_code']); ?> &bull; <?php echo $ex['exam_type']; ?></p>

                        <ul class="list-unstyled small mb-4">
                            <li class="mb-2"><i class="bi bi-calendar3 text-muted me-2"></i> Date: <strong><?php echo date('F d, Y', strtotime($ex['exam_date'])); ?></strong></li>
                            <li class="mb-2"><i class="bi bi-stopwatch text-muted me-2"></i> Duration: <strong><?php echo $ex['duration']; ?> mins</strong></li>
                            <li><i class="bi bi-star text-muted me-2"></i> Total Marks: <strong><?php echo $ex['total_marks']; ?></strong></li>
                        </ul>

                        <?php if ($session_status === 'completed'): ?>
                            <div class="alert alert-success py-2 mb-0 text-center">
                                Score: <strong><?php echo $ex['my_score'] !== null ? $ex['my_score'] : 'Pending Marking'; ?> / <?php echo $ex['total_marks']; ?></strong>
                            </div>
                        <?php elseif ($session_status === 'in_progress'): ?>
                            <a href="take_exam.php?id=<?php echo $ex['id']; ?>" class="btn btn-warning w-100">Resume Exam <i class="bi bi-arrow-right"></i></a>
                        <?php elseif ($is_ready): ?>
                            <form method="POST" action="take_exam.php">
                                <input type="hidden" name="action" value="start">
                                <input type="hidden" name="paper_id" value="<?php echo $ex['id']; ?>">
                                <button type="submit" class="btn btn-success w-100" onclick="return confirm('Ready to start? The timer will begin immediately.')">Start Exam <i class="bi bi-play-fill"></i></button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-light w-100 disabled text-muted">Not Available Yet</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>