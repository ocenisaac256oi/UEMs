<?php
// view_paper.php (aka paper_preview.php)
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_login();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$paper_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($paper_id === 0) {
    header("Location: exam_papers.php");
    exit();
}

// Fetch Paper
$p_q = mysqli_query($conn, "SELECT p.*, c.code as course_code, c.title as course_title, d.name as dept_name, col.name as col_name 
                            FROM exam_papers p 
                            JOIN courses c ON p.course_id = c.id
                            LEFT JOIN departments d ON c.department_id = d.id
                            LEFT JOIN colleges col ON c.college_id = col.id
                            WHERE p.id = $paper_id");
if (!$p_q || mysqli_num_rows($p_q) === 0) {
    header("Location: exam_papers.php");
    exit();
}
$paper = mysqli_fetch_assoc($p_q);

// Strict security check: Lecturer can only view their own paper
if ($role === 'lecturer' && $paper['created_by'] != $user_id) {
    set_flash_message('danger', 'Unauthorized Access: You can only view examination papers that you have authored.');
    header("Location: exam_papers.php");
    exit();
}

// Submit Workflow
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit') {
    if ($paper['status'] === 'draft' && ($role === 'lecturer' || $role === 'admin' || $role === 'hod')) {
        mysqli_query($conn, "UPDATE exam_papers SET status = 'submitted', submitted_at = CURRENT_TIMESTAMP WHERE id = $paper_id");
        set_flash_message('success', 'Paper submitted for HOD review!');
        header("Location: view_paper.php?id=$paper_id");
        exit();
    }
}

// Fetch Questions
$questions = [];
$q_res = mysqli_query($conn, "SELECT epq.section, q.* FROM exam_paper_questions epq JOIN questions q ON epq.question_id = q.id 
                              WHERE epq.exam_paper_id = $paper_id ORDER BY epq.section, epq.sequence_order");
if ($q_res) {
    while ($row = mysqli_fetch_assoc($q_res)) {
        $questions[] = $row;
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<!-- No print CSS -->
<style>
    @media print {
        body * {
            visibility: hidden;
        }

        #printableContent,
        #printableContent * {
            visibility: visible;
        }

        #printableContent {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }

        .no-print {
            display: none !important;
        }
    }
</style>

<div class="row align-items-center mb-4 no-print">
    <div class="col-md-6">
        <a href="exam_papers.php" class="btn btn-light border"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
    <div class="col-md-6 text-end">
        <?php if ($paper['status'] === 'draft'): ?>
            <a href="paper_questions.php?id=<?php echo $paper_id; ?>" class="btn btn-outline-primary me-2"><i class="bi bi-list-check"></i> Edit Questions</a>
            <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="submit">
                <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to submit this paper to the HOD for review? You will not be able to edit it anymore.')"><i class="bi bi-send"></i> Submit for Review</button>
            </form>
        <?php endif; ?>

        <?php if (($paper['status'] === 'submitted' && $role === 'hod') || ($paper['status'] === 'hod_approved' && $role === 'dean')): ?>
            <form method="POST" action="approvals.php" class="d-inline">
                <input type="hidden" name="paper_id" value="<?php echo $paper_id; ?>">
                <input type="hidden" name="action" value="approve">
                <button type="submit" class="btn btn-success" onclick="return confirm('Approve this paper?')"><i class="bi bi-check-lg"></i> Approve</button>
            </form>
            
            <button class="btn btn-danger ms-1" data-bs-toggle="modal" data-bs-target="#rejectModal" data-id="<?php echo $paper_id; ?>"><i class="bi bi-x-lg"></i> Reject</button>
        <?php endif; ?>

        <?php if ($paper['status'] === 'ready_for_print' && in_array($role, ['exam_master', 'admin'])): ?>
            <button onclick="window.print()" class="btn btn-dark"><i class="bi bi-printer"></i> Print Paper</button>
        <?php endif; ?>
    </div>
</div>

<div class="row justify-content-center">
    <!-- Main A4 Page Preview -->
    <div class="col-lg-9 col-xl-8">
        <div id="printableContent" class="card shadow-lg border-0 bg-white" style="min-height: 1122px; padding: 50px;">
            <div class="text-center mb-4">
                <h2 class="text-uppercase font-weight-bold">Kampala International University</h2>
                <?php if (!empty($paper['col_name'])): ?>
                    <h4 class="text-uppercase"><?php echo htmlspecialchars($paper['col_name']); ?></h4>
                <?php endif; ?>
                <h4 class="mt-3 text-uppercase font-weight-bold border-bottom d-inline-block pb-2">
                    <?php echo htmlspecialchars($paper['exam_type']); ?> EXAMINATION <?php echo $paper['academic_year']; ?>
                </h4>
                <div class="mt-3 row justify-content-center">
                    <div class="col-md-8 text-start fw-bold">
                        <div class="row mb-1">
                            <div class="col-5">COURSE CODE:</div>
                            <div class="col-7"><?php echo htmlspecialchars($paper['course_code']); ?></div>
                        </div>
                        <div class="row mb-1">
                            <div class="col-5">COURSE TITLE:</div>
                            <div class="col-7"><?php echo htmlspecialchars($paper['course_title']); ?></div>
                        </div>
                        <div class="row mb-1">
                            <div class="col-5">SEMESTER:</div>
                            <div class="col-7"><?php echo $paper['semester']; ?></div>
                        </div>
                        <div class="row mb-1">
                            <div class="col-5">DURATION:</div>
                            <div class="col-7"><?php echo floor($paper['duration'] / 60); ?> Hours <?php echo $paper['duration'] % 60 > 0 ? ($paper['duration'] % 60) . ' Mins' : ''; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-light p-3 border mb-5 rounded" style="font-size: 0.9em;">
                <p class="mb-1 text-uppercase fw-bold">Instructions to Candidates:</p>
                <?php if (!empty($paper['instructions'])): ?>
                    <?php echo nl2br(htmlspecialchars($paper['instructions'])); ?>
                <?php else: ?>
                    <ol class="mb-0 ps-3">
                        <li>Do not open the examination paper until instructed to do so.</li>
                        <li>Write clearly and legibly.</li>
                    </ol>
                <?php endif; ?>
            </div>

            <div class="questions-list">
                <?php if (empty($questions)): ?>
                    <div class="alert alert-danger text-center no-print py-5 shadow-sm rounded-4 border-2">
                        <i class="bi bi-exclamation-triangle-fill display-4 text-danger mb-3"></i>
                        <h4 class="fw-bold">No Questions Attached</h4>
                        <p class="mb-4">You have generated the exam paper outline, but you have not manually selected any questions from the databank to go into this paper yet.</p>
                        <a href="paper_questions.php?id=<?php echo $paper_id; ?>" class="btn btn-danger btn-lg px-4"><i class="bi bi-list-check"></i> Select Questions Now</a>
                    </div>
                <?php else: ?>
                    <?php
                    $current_section = '';
                    $q_num = 1;
                    ?>
                    <?php foreach ($questions as $q): ?>
                        <?php
                        if ($current_section !== $q['section']) {
                            $current_section = $q['section'];
                            echo "<h5 class='border-bottom border-dark pb-2 mb-4 text-uppercase fw-bold'>SECTION {$current_section}</h5>";
                        }
                        ?>
                        <div class="question-block mb-4 d-flex">
                            <div class="fw-bold me-2" style="width: 30px;">Q<?php echo $q_num++; ?>.</div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between mb-2">
                                    <div class="text-justify lh-base"><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></div>
                                    <div class="fw-bold ms-3" style="white-space: nowrap;">[<?php echo $q['marks']; ?> marks]</div>
                                </div>

                                <?php if ($q['question_type'] === 'multiple_choice' && !empty($q['options'])): ?>
                                    <?php
                                    $options = json_decode($q['options'], true);
                                    if (is_array($options)):
                                    ?>
                                        <div class="ms-3 mt-2">
                                            <?php foreach ($options as $idx => $opt): ?>
                                                <div class="mb-1"><?php echo chr(65 + $idx); ?>. <?php echo htmlspecialchars($opt); ?></div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="text-center mt-5 pt-4 text-uppercase fw-bold" style="border-top: 2px dashed #ccc;">
                *** END OF PAPER ***
                <br>
                <small class="text-muted text-lowercase font-weight-normal"><?php echo htmlspecialchars($paper['paper_code']); ?></small>
            </div>
        </div>
    </div>

    <!-- Sidebar Status Block -->
    <div class="col-lg-3 col-xl-4 no-print mt-4 mt-lg-0">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                <h5 class="mb-0 fw-bold">Paper Status</h5>
            </div>
            <div class="card-body px-4">
                <div class="mb-4">
                    <span class="badge <?php echo get_status_badge_class($paper['status']); ?> w-100 py-2 fs-6 rounded-pill">
                        <?php echo strtoupper(str_replace('_', ' ', $paper['status'])); ?>
                    </span>
                </div>

                <ul class="list-group list-group-flush mb-0 text-sm">
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                        <span class="text-muted">Total Marks</span>
                        <span class="fw-bold"><?php echo $paper['total_marks']; ?></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                        <span class="text-muted">Questions</span>
                        <span class="fw-bold"><?php echo count($questions); ?></span>
                    </li>
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                        <span class="text-muted">Created</span>
                        <span><?php echo date('M d, Y', strtotime($paper['created_at'])); ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Reject Paper</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="approvals.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="paper_id" id="reject_id">
                    <p>Are you sure you want to reject this paper and send it back to draft?</p>
                    <div class="mb-3">
                        <label class="form-label">Feedback / Reason (Optional)</label>
                        <textarea class="form-control" name="feedback" rows="3" placeholder="Explain what needs to be changed..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Paper</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // helper for rendering letter mapping
    function getLetter(index) {
        return String.fromCharCode(65 + index);
    }

    document.addEventListener('DOMContentLoaded', function() {
        var rejectModal = document.getElementById('rejectModal');
        if(rejectModal) {
            rejectModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                rejectModal.querySelector('#reject_id').value = button.getAttribute('data-id');
            });
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>