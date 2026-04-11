<?php
// take_exam.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_role(['student']);
$user_id = $_SESSION['user_id'];

// 1. Determine the paper being taken
$paper_id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'start') {
    $paper_id = (int)$_POST['paper_id'];

    // Check if exam is approved/published
    $p_check = mysqli_query($conn, "SELECT * FROM exam_papers WHERE id = $paper_id AND status IN ('hod_approved', 'ready_for_print', 'published') AND deleted_at IS NULL");
    if (!$p_check || mysqli_num_rows($p_check) === 0) {
        set_flash_message('danger', 'Exam is not currently available to take.');
        header("Location: student_dashboard.php");
        exit();
    }
    $paper = mysqli_fetch_assoc($p_check);

    // Check if session exists
    $s_check = mysqli_query($conn, "SELECT id, status FROM exam_sessions WHERE exam_paper_id = $paper_id AND student_id = $user_id");
    if ($s_check && mysqli_num_rows($s_check) > 0) {
        // Resume existing
        $session = mysqli_fetch_assoc($s_check);
        if ($session['status'] !== 'in_progress') {
            set_flash_message('warning', 'You have already completed this exam.');
            header("Location: student_dashboard.php");
            exit();
        }
    } else {
        // Create new session
        $duration = (int)$paper['duration'];
        mysqli_query($conn, "INSERT INTO exam_sessions (exam_paper_id, student_id, start_time, end_time, status) 
                             VALUES ($paper_id, $user_id, NOW(), DATE_ADD(NOW(), INTERVAL $duration MINUTE), 'in_progress')");
    }

    // Redirect to GET standard
    header("Location: take_exam.php?id=$paper_id");
    exit();
}

$paper_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($paper_id <= 0) {
    header("Location: student_dashboard.php");
    exit();
}

// 2. Load Active Session and Paper Info
$pq = mysqli_query($conn, "SELECT ep.*, c.title as course_title, c.code as course_code FROM exam_papers ep JOIN courses c ON ep.course_id = c.id WHERE ep.id = $paper_id");
$paper = mysqli_fetch_assoc($pq);

$sq = mysqli_query($conn, "SELECT * FROM exam_sessions WHERE exam_paper_id = $paper_id AND student_id = $user_id LIMIT 1");
$session = mysqli_fetch_assoc($sq);

if (!$session || $session['status'] !== 'in_progress') {
    set_flash_message('danger', 'Invalid or expired exam session.');
    header("Location: student_dashboard.php");
    exit();
}

// Calculate remaining seconds
$end_time = strtotime($session['end_time']);
$now = time();
$seconds_left = $end_time - $now;

if ($seconds_left <= 0) {
    // Session expired
    mysqli_query($conn, "UPDATE exam_sessions SET status = 'expired' WHERE id = {$session['id']}");
    set_flash_message('warning', 'Exam time has expired.');
    header("Location: student_dashboard.php");
    exit();
}

// 3. Load Questions
$q_sql = "SELECT epq.*, q.question_text, q.question_type, q.options 
          FROM exam_paper_questions epq 
          JOIN questions q ON epq.question_id = q.id 
          WHERE epq.exam_paper_id = $paper_id 
          ORDER BY epq.sequence_order ASC";
$questions = [];
$res = mysqli_query($conn, $q_sql);
while ($row = mysqli_fetch_assoc($res)) {
    // Also fetch any existing answer
    $ans_q = mysqli_query($conn, "SELECT submitted_answer FROM student_answers WHERE session_id = {$session['id']} AND question_id = {$row['question_id']}");
    $row['saved_answer'] = ($ans_q && mysqli_num_rows($ans_q) > 0) ? mysqli_fetch_assoc($ans_q)['submitted_answer'] : '';
    $questions[] = $row;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($paper['course_code']); ?> Exam - UEMS Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .exam-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, .1);
        }

        .timer-box {
            font-family: monospace;
            font-size: 1.5rem;
            font-weight: bold;
            background: #212529;
            color: #fff;
            padding: 10px 20px;
            border-radius: 8px;
        }

        .timer-danger {
            background: #dc3545;
            animation: pulse 1s infinite alternate;
        }

        @keyframes pulse {
            from {
                opacity: 1;
            }

            to {
                opacity: 0.8;
            }
        }
    </style>
</head>

<body>

    <div class="bg-white exam-header py-3 px-4 mb-4">
        <div class="row align-items-center">
            <div class="col-md-4">
                <h5 class="mb-0 fw-bold text-primary"><?php echo htmlspecialchars($paper['course_code']); ?>: <?php echo htmlspecialchars($paper['course_title']); ?></h5>
                <small class="text-muted"><?php echo htmlspecialchars($paper['paper_code']); ?> - <?php echo $paper['exam_type']; ?></small>
            </div>
            <div class="col-md-4 text-center">
                <div class="timer-box d-inline-block" id="examTimer">--:--:--</div>
            </div>
            <div class="col-md-4 text-end">
                <button type="button" class="btn btn-success px-4 fw-bold" onclick="submitFinalExam()">Finish & Submit Exam</button>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <?php if (!empty($paper['instructions'])): ?>
            <div class="alert alert-info border-0 shadow-sm rounded-4 mb-4">
                <strong><i class="bi bi-info-circle"></i> Instructions:</strong><br>
                <?php echo nl2br(htmlspecialchars($paper['instructions'])); ?>
            </div>
        <?php endif; ?>

        <form id="examForm" method="POST" action="submit_exam.php">
            <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
            <?php foreach ($questions as $index => $q):
                $opts = json_decode($q['options'], true);
            ?>
                <div class="card border-0 shadow-sm rounded-4 mb-4 question-card" id="q_wrapper_<?php echo $q['question_id']; ?>">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between mb-3">
                            <strong class="text-secondary">Question <?php echo $index + 1; ?></strong>
                            <span class="badge bg-light text-dark border"><?php echo $q['marks']; ?> Marks</span>
                        </div>

                        <h5 class="mb-4 lh-base" style="font-size: 1.15rem;"><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></h5>

                        <div class="options-container">
                            <?php if ($q['question_type'] === 'multiple_choice' && is_array($opts)): ?>
                                <?php foreach ($opts as $optKey => $optText): ?>
                                    <div class="form-check custom-radio mb-3 p-3 border rounded bg-light hover-bg-white transition-all">
                                        <input class="form-check-input ms-1 me-2 async-save" type="radio"
                                            name="q_<?php echo $q['question_id']; ?>"
                                            id="q_<?php echo $q['question_id']; ?>_<?php echo $optKey; ?>"
                                            value="<?php echo htmlspecialchars($optText); ?>"
                                            data-qid="<?php echo $q['question_id']; ?>"
                                            <?php echo ($q['saved_answer'] === (string)$optText) ? 'checked' : ''; ?>>
                                        <label class="form-check-label w-100" for="q_<?php echo $q['question_id']; ?>_<?php echo $optKey; ?>" style="cursor:pointer;">
                                            <strong><?php echo chr(65 + (int)$optKey); ?>.</strong> <?php echo htmlspecialchars($optText); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php elseif ($q['question_type'] === 'true_false'): ?>
                                <div class="form-check custom-radio mb-3 p-3 border rounded bg-light">
                                    <input class="form-check-input ms-1 me-2 async-save" type="radio" name="q_<?php echo $q['question_id']; ?>" id="q_<?php echo $q['question_id']; ?>_true" value="True" data-qid="<?php echo $q['question_id']; ?>" <?php echo ($q['saved_answer'] === 'True') ? 'checked' : ''; ?>>
                                    <label class="form-check-label w-100" for="q_<?php echo $q['question_id']; ?>_true" style="cursor:pointer;">True</label>
                                </div>
                                <div class="form-check custom-radio mb-3 p-3 border rounded bg-light">
                                    <input class="form-check-input ms-1 me-2 async-save" type="radio" name="q_<?php echo $q['question_id']; ?>" id="q_<?php echo $q['question_id']; ?>_false" value="False" data-qid="<?php echo $q['question_id']; ?>" <?php echo ($q['saved_answer'] === 'False') ? 'checked' : ''; ?>>
                                    <label class="form-check-label w-100" for="q_<?php echo $q['question_id']; ?>_false" style="cursor:pointer;">False</label>
                                </div>
                            <?php else: ?>
                                <!-- Essay / Short Answer -->
                                <textarea class="form-control async-save bg-light" name="q_<?php echo $q['question_id']; ?>" data-qid="<?php echo $q['question_id']; ?>" rows="6" placeholder="Type your answer here..."><?php echo htmlspecialchars($q['saved_answer']); ?></textarea>
                                <small class="text-muted mt-2 d-inline-block">Your answer will be automatically saved as you type.</small>
                            <?php endif; ?>
                        </div>

                        <div class="text-end mt-2">
                            <small class="text-success save-status d-none" id="status_<?php echo $q['question_id']; ?>"><i class="bi bi-cloud-check"></i> Saved</small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="card border-0 shadow-sm rounded-4 mt-5 mb-4 bg-white">
                <div class="card-body p-4 text-center">
                    <h5 class="mb-3">Finished answering all questions?</h5>
                    <button type="button" class="btn btn-success btn-lg px-5 py-3 fw-bold shadow" onclick="submitFinalExam()">
                        <i class="bi bi-send-check-fill"></i> Finish & Submit Exam
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Timer Logic
        let timeLeft = <?php echo $seconds_left; ?>;
        const timerDisplay = document.getElementById('examTimer');

        function updateTimer() {
            if (timeLeft <= 0) {
                timerDisplay.innerHTML = "00:00:00";
                document.getElementById('examForm').submit();
                return;
            }

            const h = Math.floor(timeLeft / 3600).toString().padStart(2, '0');
            const m = Math.floor((timeLeft % 3600) / 60).toString().padStart(2, '0');
            const s = (timeLeft % 60).toString().padStart(2, '0');

            timerDisplay.innerHTML = `${h}:${m}:${s}`;

            if (timeLeft < 300) { // last 5 minutes
                timerDisplay.classList.add('timer-danger');
            }

            timeLeft--;
            setTimeout(updateTimer, 1000);
        }

        updateTimer();

        // Async Save Logic
        const inputs = document.querySelectorAll('.async-save');
        let timeoutId;

        inputs.forEach(input => {
            const eventType = input.type === 'radio' ? 'change' : 'input';

            input.addEventListener(eventType, function() {
                const qid = this.getAttribute('data-qid');
                const val = this.value;
                const statusEl = document.getElementById('status_' + qid);

                statusEl.classList.remove('d-none', 'text-success', 'text-danger');
                statusEl.classList.add('text-muted');
                statusEl.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Saving...';

                // Debounce for textareas
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => {
                    const formData = new FormData();
                    formData.append('session_id', '<?php echo $session['id']; ?>');
                    formData.append('question_id', qid);
                    formData.append('answer', val);

                    fetch('api_save_answer.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                statusEl.classList.remove('text-muted');
                                statusEl.classList.add('text-success');
                                statusEl.innerHTML = '<i class="bi bi-cloud-check"></i> Saved';
                                setTimeout(() => statusEl.classList.add('d-none'), 2000);
                            } else {
                                statusEl.classList.remove('text-muted');
                                statusEl.classList.add('text-danger');
                                statusEl.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Save Failed';
                            }
                        }).catch(err => {
                            statusEl.classList.add('text-danger');
                            statusEl.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Network Error';
                        });
                }, eventType === 'input' ? 1000 : 0);
            });
        });

        function submitFinalExam() {
            if (confirm("Are you sure you want to finish and submit your exam? You cannot undo this action.")) {
                document.getElementById('examForm').submit();
            }
        }
    </script>
</body>

</html>