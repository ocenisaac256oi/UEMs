<?php
// reports.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_role('admin');

// Simple reporting data
$metrics = [
    'total_papers' => 0,
    'papers_by_status' => [],
    'total_questions' => 0,
    'users_by_role' => []
];

// Total papers
$q_papers = mysqli_query($conn, "SELECT status, COUNT(*) as cnt FROM exam_papers WHERE deleted_at IS NULL GROUP BY status");
if ($q_papers) {
    while($row = mysqli_fetch_assoc($q_papers)) {
        $metrics['papers_by_status'][$row['status']] = $row['cnt'];
        $metrics['total_papers'] += $row['cnt'];
    }
}

// Total questions
$q_qst = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM questions WHERE deleted_at IS NULL");
if ($q_qst) {
    $metrics['total_questions'] = mysqli_fetch_assoc($q_qst)['cnt'];
}

// Users by role
$q_users = mysqli_query($conn, "SELECT role, COUNT(*) as cnt FROM users WHERE deleted_at IS NULL GROUP BY role");
if ($q_users) {
    while($row = mysqli_fetch_assoc($q_users)) {
        $metrics['users_by_role'][$row['role']] = $row['cnt'];
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-bar-chart-line"></i> Admin Reports</h2>
    <button onclick="window.print()" class="btn btn-primary no-print"><i class="bi bi-printer"></i> Print Report</button>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-primary text-white">
            <div class="card-body p-4 text-center">
                <h3 class="display-4 fw-bold"><?php echo $metrics['total_papers']; ?></h3>
                <p class="mb-0 text-white-50 text-uppercase fw-bold">Total Papers Created</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-info text-white">
            <div class="card-body p-4 text-center">
                <h3 class="display-4 fw-bold"><?php echo $metrics['total_questions']; ?></h3>
                <p class="mb-0 text-white-50 text-uppercase fw-bold">Questions in Bank</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-success text-white">
            <div class="card-body p-4 text-center">
                <h3 class="display-4 fw-bold"><?php echo $metrics['papers_by_status']['printed'] ?? 0; ?></h3>
                <p class="mb-0 text-white-50 text-uppercase fw-bold">Papers Printed</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-warning text-dark">
            <div class="card-body p-4 text-center">
                <h3 class="display-4 fw-bold"><?php echo ($metrics['papers_by_status']['draft'] ?? 0) + ($metrics['papers_by_status']['submitted'] ?? 0); ?></h3>
                <p class="mb-0 text-dark-50 text-uppercase fw-bold">Papers In Progress</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-bottom-0 pt-4 px-4">
                <h5 class="fw-bold mb-0">Paper Status Distribution</h5>
            </div>
            <div class="card-body p-4">
                <?php if (empty($metrics['papers_by_status'])): ?>
                    <p class="text-muted">No data available.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach($metrics['papers_by_status'] as $status => $count): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <?php echo strtoupper(str_replace('_', ' ', $status)); ?>
                                <span class="badge bg-primary rounded-pill"><?php echo $count; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-bottom-0 pt-4 px-4">
                <h5 class="fw-bold mb-0">Users Registered by Role</h5>
            </div>
            <div class="card-body p-4">
                <?php if (empty($metrics['users_by_role'])): ?>
                    <p class="text-muted">No data available.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach($metrics['users_by_role'] as $role => $count): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <?php echo strtoupper(str_replace('_', ' ', $role)); ?>
                                <span class="badge bg-dark rounded-pill"><?php echo $count; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print { display: none !important; }
    body { background-color: white; }
    .card { border: 1px solid #ccc !important; box-shadow: none !important; }
}
</style>

<?php require_once 'includes/footer.php'; ?>
