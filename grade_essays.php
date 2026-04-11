<?php
// grade_essays.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_role(['lecturer', 'hod', 'dean', 'admin']);
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// If dealing with a specific student's submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grade_submission') {
    $result_id = (int)$_POST['result_id'];
    $added_marks = 0;
    
    foreach ($_POST['marks'] as $answer_id => $marks) {
        $a_id = (int)$answer_id;
        $m = (int)$marks;
        
        mysqli_query($conn, "UPDATE student_answers SET marks_awarded = $m, is_correct = 1 WHERE id = $a_id");
        $added_marks += $m;
    }
    
    // Update total score and finalize grading status
    mysqli_query($conn, "UPDATE exam_results SET total_score = total_score + $added_marks, grading_status = 'graded' WHERE id = $result_id");
    
    set_flash_message('success', 'Essay marks have been successfully added and the exam is fully graded.');
    header("Location: grade_essays.php");
    exit();
}

// Fetch pending manual reviews
$query_where = "er.grading_status = 'pending_manual_review'";
if ($role === 'lecturer') {
    $query_where .= " AND ep.created_by = $user_id";
} elseif ($role === 'hod') {
    $dep_id = $_SESSION['department_id'];
    $query_where .= " AND c.department_id = $dep_id";
}

$sql = "SELECT er.id as result_id, ep.id as paper_id, ep.paper_code, c.code as course_code, 
        u.first_name, u.last_name, u.registration_number,
        er.total_score, ep.total_marks
        FROM exam_results er
        JOIN exam_papers ep ON er.exam_paper_id = ep.id
        JOIN courses c ON ep.course_id = c.id
        JOIN users u ON er.student_id = u.id
        WHERE $query_where ORDER BY er.created_at ASC";

$pending_reviews = [];
$res = mysqli_query($conn, $sql);
if ($res) {
    while($row = mysqli_fetch_assoc($res)) {
        $pending_reviews[] = $row;
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="row align-items-center mb-4">
    <div class="col-md-8">
        <h2><i class="bi bi-pencil-square"></i> Manual Grading (Essays & Short Answers)</h2>
        <p class="text-muted">Review subjective answers from students to finalize their exam marks.</p>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-4 py-3">Student</th>
                        <th class="py-3">Exam Paper</th>
                        <th class="py-3">Current Auto-Score</th>
                        <th class="px-4 py-3 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($pending_reviews)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">No pending manual grading tasks!</td></tr>
                    <?php else: ?>
                        <?php foreach($pending_reviews as $pr): ?>
                            <tr>
                                <td class="px-4">
                                    <strong><?php echo htmlspecialchars($pr['first_name'] . ' ' . $pr['last_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($pr['registration_number'] ?? 'No Reg Num'); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($pr['course_code']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($pr['paper_code']); ?></small>
                                </td>
                                <td><span class="badge bg-secondary"><?php echo $pr['total_score']; ?> / <?php echo $pr['total_marks']; ?> pts</span></td>
                                <td class="px-4 text-end">
                                    <a href="grade_essays.php?review_id=<?php echo $pr['result_id']; ?>" class="btn btn-sm btn-primary"><i class="bi bi-list-check"></i> Grade Answers</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
// Grading Interface
if (isset($_GET['review_id']) && (int)$_GET['review_id'] > 0): 
    $rid = (int)$_GET['review_id'];
    
    // Fetch result details
    $r_q = mysqli_query($conn, "SELECT er.*, ep.paper_code, c.title as course_title, u.first_name, u.last_name, u.registration_number, er.student_id
                                FROM exam_results er
                                JOIN exam_papers ep ON er.exam_paper_id = ep.id
                                JOIN courses c ON ep.course_id = c.id
                                JOIN users u ON er.student_id = u.id
                                WHERE er.id = $rid LIMIT 1");
    if($r_q && mysqli_num_rows($r_q) > 0):
        $r_data = mysqli_fetch_assoc($r_q);
        
        // Fetch essay answers
        $a_sql = "SELECT sa.id as answer_id, sa.submitted_answer, q.question_text, q.marks as max_marks
                  FROM student_answers sa
                  JOIN questions q ON sa.question_id = q.id
                  JOIN exam_sessions es ON sa.session_id = es.id
                  JOIN exam_paper_questions epq ON (epq.question_id = q.id AND epq.exam_paper_id = es.exam_paper_id)
                  WHERE es.student_id = {$r_data['student_id']} AND es.exam_paper_id = {$r_data['exam_paper_id']}
                  AND q.question_type NOT IN ('multiple_choice', 'true_false')";
                  
        $answers = [];
        $ans_res = mysqli_query($conn, $a_sql);
        if($ans_res) {
            while($ar = mysqli_fetch_assoc($ans_res)) {
                $answers[] = $ar;
            }
        }
?>
    <div class="mt-5">
        <h4 class="mb-3">Grading: <?php echo htmlspecialchars($r_data['first_name'] . ' ' . $r_data['last_name']); ?>'s Exam</h4>
        <form method="POST">
            <input type="hidden" name="action" value="grade_submission">
            <input type="hidden" name="result_id" value="<?php echo $rid; ?>">
            
            <?php foreach($answers as $ans): ?>
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-light">
                        <strong>Question:</strong> <?php echo nl2br(htmlspecialchars($ans['question_text'])); ?> (Max: <?php echo $ans['max_marks']; ?> pts)
                    </div>
                    <div class="card-body">
                        <p><strong>Student's Response:</strong></p>
                        <div class="p-3 bg-white border rounded mb-3" style="min-height: 80px;">
                            <?php echo nl2br(htmlspecialchars($ans['submitted_answer'])); ?>
                        </div>
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-success">Marks to Award</label>
                                <div class="input-group">
                                    <input type="number" name="marks[<?php echo $ans['answer_id']; ?>]" class="form-control text-center" min="0" max="<?php echo $ans['max_marks']; ?>" value="0" required>
                                    <span class="input-group-text">/ <?php echo $ans['max_marks']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="text-end">
                <a href="grade_essays.php" class="btn btn-light me-2">Cancel</a>
                <button type="submit" class="btn btn-success px-5 fw-bold"><i class="bi bi-check-all"></i> Finalize Marks</button>
            </div>
        </form>
    </div>
<?php endif; endif; ?>

<?php require_once 'includes/footer.php'; ?>
