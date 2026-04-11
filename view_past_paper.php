<?php
// view_past_paper.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_role(['student']);
$user_id = $_SESSION['user_id'];
$paper_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($paper_id === 0) {
    header("Location: past_papers.php");
    exit();
}

// Fetch Paper
$p_q = mysqli_query($conn, "SELECT p.*, c.code as course_code, c.title as course_title, d.name as dept_name, col.name as col_name 
                            FROM exam_papers p 
                            JOIN courses c ON p.course_id = c.id
                            LEFT JOIN departments d ON c.department_id = d.id
                            LEFT JOIN colleges col ON c.college_id = col.id
                            WHERE p.id = $paper_id AND p.status IN ('published', 'ready_for_print') AND p.exam_date <= CURRENT_DATE");

if (!$p_q || mysqli_num_rows($p_q) === 0) {
    set_flash_message('danger', 'Paper not found or it is not yet available as a past paper.');
    header("Location: past_papers.php");
    exit();
}
$paper = mysqli_fetch_assoc($p_q);

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
        <a href="past_papers.php" class="btn btn-light border"><i class="bi bi-arrow-left"></i> Back to Archive</a>
    </div>
    <div class="col-md-6 text-end">
        <button onclick="window.print()" class="btn btn-dark"><i class="bi bi-printer"></i> Print / Download PDF</button>
    </div>
</div>

<div class="row justify-content-center">
    <!-- Main A4 Page Preview -->
    <div class="col-lg-9 col-xl-8">
        <div id="printableContent" class="card shadow-lg border-0 bg-white" style="min-height: 1122px; padding: 50px;">
            <div class="text-center mb-4 border-bottom pb-4">
                <h2 class="text-uppercase font-weight-bold display-6">Kampala International University</h2>
                <?php if (!empty($paper['col_name'])): ?>
                    <h5 class="text-uppercase text-secondary"><?php echo htmlspecialchars($paper['col_name']); ?></h5>
                <?php endif; ?>
                <h4 class="mt-3 text-uppercase font-weight-bold bg-light d-inline-block p-2 rounded">
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
                    </div>
                </div>
            </div>

            <div class="questions-list">
                <?php if (empty($questions)): ?>
                    <div class="text-center text-muted no-print">
                        <p>No questions found in this paper.</p>
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
                            echo "<h5 class='border-bottom border-secondary pb-2 mb-4 text-uppercase fw-bold text-primary mt-4'>SECTION {$current_section}</h5>";
                        }
                        ?>
                        <div class="question-block mb-4 d-flex bg-light p-3 rounded">
                            <div class="fw-bold me-2" style="width: 30px;">Q<?php echo $q_num++; ?>.</div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between mb-2">
                                    <div class="text-justify lh-base" style="font-size: 1.1em;"><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></div>
                                    <div class="fw-bold ms-3 border border-dark rounded px-2 py-1 bg-white shadow-sm" style="white-space: nowrap;"><?php echo $q['marks']; ?> Marks</div>
                                </div>

                                <?php if ($q['question_type'] === 'multiple_choice' && !empty($q['options'])): ?>
                                    <?php
                                    $options = json_decode($q['options'], true);
                                    if (is_array($options)):
                                    ?>
                                        <div class="ms-3 mt-3">
                                            <?php foreach ($options as $idx => $opt): ?>
                                                <div class="mb-2 p-2 border bg-white rounded shadow-sm d-flex align-items-center">
                                                    <span class="badge bg-secondary me-2 rounded-circle"><?php echo chr(65 + $idx); ?></span> 
                                                    <?php echo htmlspecialchars($opt); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="text-center mt-5 pt-4 text-uppercase fw-bold" style="border-top: 2px dashed #000;">
                *** END OF PAPER ***
                <br>
                <small class="text-muted text-lowercase font-weight-normal"><?php echo htmlspecialchars($paper['paper_code']); ?></small>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
