<?php
// edit_question.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_role(['admin', 'hod', 'dean', 'lecturer']);
$user_id = $_SESSION['user_id'];

$question_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($question_id <= 0) {
    set_flash_message('danger', 'Invalid question ID.');
    header("Location: question_bank.php");
    exit();
}

// Fetch existing question
$q_res = mysqli_query($conn, "SELECT * FROM questions WHERE id = $question_id AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
if (!$q_res || mysqli_num_rows($q_res) === 0) {
    set_flash_message('danger', 'Question not found.');
    header("Location: question_bank.php");
    exit();
}
$question = mysqli_fetch_assoc($q_res);
$existing_options = [];
if ($question['question_type'] === 'multiple_choice' && !empty($question['options'])) {
    $existing_options = json_decode($question['options'], true);
}


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
        $opts_array = [];
        foreach ($_POST['mcq_options'] as $opt) {
            if (!empty(trim($opt))) {
                $opts_array[] = trim($opt);
            }
        }
        if(!empty($opts_array)){
            $options_json = "'" . mysqli_real_escape_string($conn, json_encode($opts_array)) . "'";
        }
    }

    if (!empty($course_id) && !empty($question_type) && !empty($question_text)) {
        $sql = "UPDATE questions SET 
                course_id = $course_id, 
                question_text = '$question_text', 
                question_type = '$question_type', 
                marks = $marks, 
                options = $options_json 
                WHERE id = $question_id";

        if (mysqli_query($conn, $sql)) {
            set_flash_message('success', 'Question updated successfully.');
            header("Location: view_question.php?id=$question_id");
            exit();
        } else {
            set_flash_message('danger', 'Error updating question.');
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
            <h2><i class="bi bi-pencil-square"></i> Edit Question</h2>
            <a href="view_question.php?id=<?php echo $question_id; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Cancel Edit
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
                                    <option value="<?php echo $c['id']; ?>" <?php echo ($c['id'] == $question['course_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['code'] . ' - ' . $c['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Question Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="question_type" id="question_type" required onchange="toggleOptionFields()">
                                <option value="essay" <?php echo ($question['question_type'] == 'essay') ? 'selected' : ''; ?>>Essay / Free Text</option>
                                <option value="multiple_choice" <?php echo ($question['question_type'] == 'multiple_choice') ? 'selected' : ''; ?>>Multiple Choice</option>
                                <option value="true_false" <?php echo ($question['question_type'] == 'true_false') ? 'selected' : ''; ?>>True / False</option>
                                <option value="structured" <?php echo ($question['question_type'] == 'structured') ? 'selected' : ''; ?>>Structured (Sub-questions)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Question Text <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="question_text" rows="5" required placeholder="Type the question content here..."><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                    </div>

                    <div id="mcq_fields" class="mb-4 p-4 bg-light rounded" style="display: <?php echo ($question['question_type'] == 'multiple_choice') ? 'block' : 'none'; ?>;">
                        <label class="form-label fw-bold mb-3"><i class="bi bi-list-check"></i> Multiple Choice Options</label>

                        <div id="options_container">
                            <?php 
                            if (!empty($existing_options) && is_array($existing_options)):
                                foreach ($existing_options as $index => $opt): 
                            ?>
                                <div class="input-group mb-2 option-row">
                                    <span class="input-group-text"><?php echo chr(65 + $index); ?></span>
                                    <input type="text" class="form-control" name="mcq_options[]" value="<?php echo htmlspecialchars($opt); ?>" placeholder="Option <?php echo ($index + 1); ?>">
                                    <?php if ($index >= 4): ?>
                                    <button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove()"><i class="bi bi-x"></i></button>
                                    <?php endif; ?>
                                </div>
                            <?php 
                                endforeach;
                            else: 
                                for ($i = 1; $i <= 4; $i++): 
                            ?>
                                <div class="input-group mb-2 option-row">
                                    <span class="input-group-text"><?php echo chr(64 + $i); ?></span>
                                    <input type="text" class="form-control" name="mcq_options[]" placeholder="Option <?php echo $i; ?>">
                                </div>
                            <?php 
                                endfor;
                            endif; 
                            ?>
                        </div>
                        <div class="text-end mt-2">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="addOption()">+ Add Another Option</button>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Total Marks <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="marks" value="<?php echo $question['marks']; ?>" min="1" max="100" required>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div style="text-align: center; margin-top: 20px;">
                        <button type="submit" class="btn btn-primary btn-lg" style="padding: 10px 40px; font-size: 18px; font-weight: bold;">
                            <i class="bi bi-save"></i> UPDATE QUESTION
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<script>
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
</script>

<?php require_once 'includes/footer.php'; ?>
