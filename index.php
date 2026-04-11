<?php
// index.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Protect this route
require_login();

// Fetch basic stats
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

if ($role === 'student') {
    header("Location: student_dashboard.php");
    exit();
}

// Prepare stats based on role
$stats = [
    'my_papers' => 0,
    'pending_approvals' => 0,
    'print_queue' => 0,
    'total_users' => 0
];

if (in_array($role, ['lecturer', 'hod'])) {
    $q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM exam_papers WHERE created_by = $user_id AND deleted_at IS NULL");
    if ($q) $stats['my_papers'] = mysqli_fetch_assoc($q)['cnt'];
}

if (in_array($role, ['hod', 'dean', 'admin'])) {
    $q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM exam_papers WHERE status IN ('submitted', 'hod_review', 'dean_review') AND deleted_at IS NULL");
    if ($q) $stats['pending_approvals'] = mysqli_fetch_assoc($q)['cnt'];
}

if (in_array($role, ['exam_master', 'admin'])) {
    $q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM exam_papers WHERE status IN ('ready_for_print', 'printing') AND deleted_at IS NULL");
    if ($q) $stats['print_queue'] = mysqli_fetch_assoc($q)['cnt'];
}

if ($role === 'admin') {
    $q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users WHERE deleted_at IS NULL");
    if ($q) $stats['total_users'] = mysqli_fetch_assoc($q)['cnt'];
}

// Fetch 5 recent papers
$recent_papers = [];
$rp_where = "p.deleted_at IS NULL";
if ($role === 'lecturer') {
    $rp_where .= " AND p.created_by = $user_id";
} elseif ($role === 'hod') {
    $dep_id = (int)($_SESSION['department_id'] ?? 0);
    $rp_where .= " AND c.department_id = $dep_id";
} elseif ($role === 'dean') {
    $col_id = (int)($_SESSION['college_id'] ?? 0);
    $rp_where .= " AND c.college_id = $col_id";
}

$rp_query = "SELECT p.*, c.code as course_code FROM exam_papers p JOIN courses c ON p.course_id = c.id WHERE $rp_where ORDER BY p.created_at DESC LIMIT 5";
$rp_res = mysqli_query($conn, $rp_query);
if ($rp_res) {
    while($row = mysqli_fetch_assoc($rp_res)){
        $recent_papers[] = $row;
    }
}
?>
<?php require_once 'includes/header.php'; ?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-primary text-white border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-body p-5">
                <h2 class="display-6 fw-bold">Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?>! 👋</h2>
                <p class="lead mb-0 opacity-75">
                    <?php echo date('l, F j, Y'); ?> &mdash; Logged in as <?php echo strtoupper(str_replace('_', ' ', $role)); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4 g-4">
    <?php if (in_array($role, ['lecturer', 'hod'])): ?>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                <div class="display-4 text-primary mb-2"><i class="bi bi-file-earmark-text"></i></div>
                <h3 class="fs-1 fw-bold"><?php echo $stats['my_papers']; ?></h3>
                <p class="text-muted mb-0">My Exam Papers</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (in_array($role, ['hod', 'dean', 'admin'])): ?>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                <div class="display-4 text-warning mb-2"><i class="bi bi-hourglass-split"></i></div>
                <h3 class="fs-1 fw-bold"><?php echo $stats['pending_approvals']; ?></h3>
                <p class="text-muted mb-0">Pending Approvals</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (in_array($role, ['exam_master', 'admin'])): ?>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                <div class="display-4 text-info mb-2"><i class="bi bi-printer"></i></div>
                <h3 class="fs-1 fw-bold"><?php echo $stats['print_queue']; ?></h3>
                <p class="text-muted mb-0">Print Queue</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($role === 'admin'): ?>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                <div class="display-4 text-success mb-2"><i class="bi bi-people"></i></div>
                <h3 class="fs-1 fw-bold"><?php echo $stats['total_users']; ?></h3>
                <p class="text-muted mb-0">Total Users</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Recent Exam Papers</h5>
                <a href="exam_papers.php" class="btn btn-sm btn-outline-primary rounded-pill">View All</a>
            </div>
            <div class="card-body px-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Paper Code</th>
                                <th>Type</th>
                                <th>Marks</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($recent_papers)): ?>
                                <tr><td colspan="5" class="text-center py-4">No papers found.</td></tr>
                            <?php else: ?>
                                <?php foreach($recent_papers as $paper): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($paper['paper_code']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($paper['course_code']); ?></small></td>
                                        <td><?php echo htmlspecialchars($paper['exam_type']); ?></td>
                                        <td><?php echo $paper['total_marks']; ?></td>
                                        <td><span class="badge <?php echo get_status_badge_class($paper['status']); ?> rounded-pill px-3"><?php echo strtoupper(str_replace('_', ' ', $paper['status'])); ?></span></td>
                                        <td><a href="view_paper.php?id=<?php echo $paper['id']; ?>" class="btn btn-sm btn-light border">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                <h5 class="mb-0 fw-bold">Quick Actions</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="d-grid gap-3">
                    <?php if (in_array($role, ['lecturer', 'hod', 'admin'])): ?>
                    <a href="create_paper.php" class="btn btn-primary btn-lg rounded-3 d-flex align-items-center justify-content-between text-start p-3">
                        <div>
                            <div class="fw-bold fs-5">Create Paper</div>
                            <div class="small text-white-50">Draft a new exam paper</div>
                        </div>
                        <i class="bi bi-plus-circle fs-3"></i>
                    </a>
                    
                    <a href="question_bank.php" class="btn btn-light border btn-lg rounded-3 d-flex align-items-center justify-content-between text-start p-3">
                        <div>
                            <div class="fw-bold fs-5 text-dark">Question Bank</div>
                            <div class="small text-muted">Add or manage questions</div>
                        </div>
                        <i class="bi bi-journals fs-3 text-secondary"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (in_array($role, ['exam_master', 'admin'])): ?>
                    <a href="print_queue.php" class="btn btn-info text-white btn-lg rounded-3 d-flex align-items-center justify-content-between text-start p-3">
                        <div>
                            <div class="fw-bold fs-5">Print Queue</div>
                            <div class="small text-white-50">Manage ready papers</div>
                        </div>
                        <i class="bi bi-printer fs-3"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
