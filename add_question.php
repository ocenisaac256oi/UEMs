<?php
// add_question.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_role(['admin', 'hod', 'dean', 'lecturer']);
$user_id = $_SESSION['user_id'];

$courses = [];
$c_res = mysqli_query($conn, "SELECT id, code, title FROM courses WHERE deleted_at IS NULL ORDER BY code");
if ($c_res) {
    while ($row = mysqli_fetch_assoc($c_res)) {
        $courses[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = (int)$_POST['course_id'];
    $question_type = sanitize_input($conn, $_POST['question_type']);
    $question_text = sanitize_input($conn, $_POST['question_text']);
    $marks = (int)$_POST['marks'];
    $options_json = 'NULL';

    if ($question_type === 'multiple_choice' && isset($_POST['mcq_options'])) {
        // Prepare options array
        $opts_array = [];
        foreach ($_POST['mcq_options'] as $opt) {
            if (!empty(trim($opt))) {
                $opts_array[] = trim($opt);
            }
        }
        $options_json = "'" . mysqli_real_escape_string($conn, json_encode($opts_array)) . "'";
    }

    if (!empty($course_id) && !empty($question_type) && !empty($question_text)) {
        $sql = "INSERT INTO questions (course_id, question_text, question_type, marks, options, created_by) 
                VALUES ($course_id, '$question_text', '$question_type', $marks, $options_json, $user_id)";

        if (mysqli_query($conn, $sql)) {
            set_flash_message('success', 'Question created successfully.');
            header("Location: question_bank.php");
            exit();
        } else {
            set_flash_message('danger', 'Error saving question to database.');
        }
    } else {
        set_flash_message('danger', 'Please fill all required fields.');
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-plus-square"></i> Add Question</h2>
            <a href="question_bank.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Bank
            </a>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <form method="POST">

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Course <span class="text-danger">*</span></label>
                            <select class="form-select" name="course_id" required>
                                <option value="">Select a Course</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['code'] . ' - ' . $c['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Question Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="question_type" id="question_type" required onchange="toggleOptionFields()">
                                <option value="essay">Essay / Free Text</option>
                                <option value="multiple_choice">Multiple Choice</option>
                                <option value="true_false">True / False</option>
                                <option value="structured">Structured (Sub-questions)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Question Text <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="question_text" rows="5" required placeholder="Type the question content here..."></textarea>
                    </div>

                    <div id="mcq_fields" class="mb-4 p-4 bg-light rounded" style="display: none;">
                        <label class="form-label fw-bold mb-3"><i class="bi bi-list-check"></i> Multiple Choice Options</label>

                        <div id="options_container">
                            <div class="input-group mb-2 option-row">
                                <span class="input-group-text">A</span>
                                <input type="text" class="form-control" name="mcq_options[]" placeholder="Type first option here...">
                            </div>
                        </div>
                        <div class="text-end mt-2">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="addOption()">+ Add Another Option</button>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Total Marks <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="marks" value="1" min="1" max="100" required>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- SUBMIT BUTTON - ADDED HERE -->
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="question_bank.php" class="btn btn-secondary btn-lg" style="margin-right: 15px; padding: 10px 30px;">
                            <i class="bi bi-x-circle"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success btn-lg" style="background-color: #28a745; padding: 10px 40px; font-size: 18px; font-weight: bold;">
                            <i class="bi bi-check-circle-fill"></i> SUBMIT QUESTION
                        </button>
                    </div>
                    <!-- END OF SUBMIT BUTTON -->

                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Just a trick to get letters A, B, C... natively in JS
    function getNextLetter(index) {
        return String.fromCharCode(65 + index);
    }

    function toggleOptionFields() {
        var type = document.getElementById('question_type').value;
        var mcq = document.getElementById('mcq_fields');
        if (type === 'multiple_choice') {
            mcq.style.display = 'block';
        } else {
            mcq.style.display = 'none';
        }
    }

    function addOption() {
        var container = document.getElementById('options_container');
        var count = container.querySelectorAll('.option-row').length;
        var newLetter = getNextLetter(count);

        var newDiv = document.createElement('div');
        newDiv.className = 'input-group mb-2 option-row';
        newDiv.innerHTML = '<span class="input-group-text">' + newLetter + '</span>' +
            '<input type="text" class="form-control" name="mcq_options[]" placeholder="Option ' + (count + 1) + '">' +
            '<button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove()"><i class="bi bi-x"></i></button>';
        container.appendChild(newDiv);
    }

    // Call onload just in case
    document.addEventListener('DOMContentLoaded', toggleOptionFields);
</script>

<?php require_once 'includes/footer.php'; ?>