<?php
// past_papers.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_role(['student']);
$user_id = $_SESSION['user_id'];
$department_id = $_SESSION['department_id'] ?? 0;
$college_id = $_SESSION['college_id'] ?? 0;

// Query past logical exams. Those published to their department.
$where_clauses = ["ep.status IN ('published', 'ready_for_print')", "ep.deleted_at IS NULL", "ep.exam_date <= CURRENT_DATE"];

if ($department_id > 0) {
    $where_clauses[] = "(c.department_id = $department_id OR c.department_id IS NULL)";
} elseif ($college_id > 0) {
    $where_clauses[] = "(c.college_id = $college_id OR c.college_id IS NULL)";
}

$where_stmt = implode(' AND ', $where_clauses);

$sql = "SELECT ep.*, c.code as course_code, c.title as course_title
        FROM exam_papers ep
        JOIN courses c ON ep.course_id = c.id
        WHERE $where_stmt
        ORDER BY ep.exam_date DESC LIMIT 50";

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
    <div class="col-md-8">
        <h2><i class="bi bi-journal-text"></i> Digital Question Bank (Past Papers)</h2>
        <p class="text-muted">Access previous examinations for revision purposes.</p>
    </div>
</div>

<div class="row g-4">
    <?php if(empty($papers)): ?>
        <div class="col-12 text-center py-5">
            <h4 class="text-muted"><i class="bi bi-folder-x"></i> No past papers available for your curriculum yet.</h4>
        </div>
    <?php else: ?>
        <?php foreach($papers as $p): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 h-100 hover-shadow transition-all">
                    <div class="card-body p-4 text-center">
                        <div class="display-6 text-primary mb-3"><i class="bi bi-file-earmark-pdf"></i></div>
                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($p['course_code']); ?></h5>
                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($p['paper_code']); ?></p>
                        <p class="text-secondary small mb-3"><?php echo date('Y', strtotime($p['exam_date'])); ?> | <?php echo htmlspecialchars($p['exam_type']); ?></p>
                        
                        <a href="view_past_paper.php?id=<?php echo $p['id']; ?>" target="_blank" class="btn btn-outline-primary w-100"><i class="bi bi-eye"></i> View Paper</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
.hover-shadow:hover { transform: translateY(-5px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
.transition-all { transition: all 0.3s ease; }
</style>

<?php require_once 'includes/footer.php'; ?>
