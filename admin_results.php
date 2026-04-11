<?php
// admin_results.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_role(['admin', 'dean', 'hod', 'lecturer']);

// Fetch all results for all students
$sql = "SELECT er.*, ep.paper_code, ep.exam_type, ep.total_marks as max_total, c.code as course_code, c.title as course_title,
        u.name as student_name, u.email as student_email
        FROM exam_results er
        JOIN exam_papers ep ON er.exam_paper_id = ep.id
        JOIN courses c ON ep.course_id = c.id
        JOIN users u ON er.student_id = u.id
        ORDER BY er.created_at DESC";

$results = [];
$res = mysqli_query($conn, $sql);
if ($res) {
    while($row = mysqli_fetch_assoc($res)) {
        $results[] = $row;
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="row align-items-center mb-4">
    <div class="col-md-8">
        <h2><i class="bi bi-award"></i> All Student Results</h2>
        <p class="text-muted">Oversee academic performance across all completed examinations.</p>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-4 py-3">Student</th>
                        <th class="py-3">Course / Exam</th>
                        <th class="py-3">Date Completed</th>
                        <th class="py-3">Status</th>
                        <th class="px-4 py-3 text-end">Final Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($results)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No exams have been completed yet.</td></tr>
                    <?php else: ?>
                        <?php foreach($results as $r): 
                            $bg_class = 'text-primary';
                            $percentage = ($r['max_total'] > 0) ? ($r['total_score'] / $r['max_total']) * 100 : 0;
                            if ($r['grading_status'] === 'graded') {
                                if ($percentage >= 70) $bg_class = 'text-success';
                                elseif ($percentage < 50) $bg_class = 'text-danger';
                            }
                        ?>
                            <tr>
                                <td class="px-4">
                                    <strong><?php echo htmlspecialchars($r['student_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($r['student_email']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($r['course_code']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($r['paper_code']); ?> - <?php echo $r['exam_type']; ?></small>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($r['created_at'])); ?></td>
                                <td>
                                    <?php if ($r['grading_status'] === 'graded'): ?>
                                        <span class="badge bg-success rounded-pill px-3"><i class="bi bi-check-all"></i> Fully Graded</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning rounded-pill text-dark px-3"><i class="bi bi-hourglass-split"></i> Awaiting Grading</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 text-end">
                                    <?php if ($r['grading_status'] === 'graded'): ?>
                                        <h4 class="mb-0 fw-bold <?php echo $bg_class; ?>">
                                            <?php echo $r['total_score']; ?> <small class="text-muted fs-6">/ <?php echo $r['max_total']; ?></small>
                                        </h4>
                                    <?php else: ?>
                                        <div class="text-muted">
                                            <strong><?php echo $r['total_score']; ?></strong> pts <br>
                                            <small>(Objective only)</small>
                                        </div>
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

<?php require_once 'includes/footer.php'; ?>
