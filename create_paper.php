<?php
// create_paper.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_role(['admin', 'hod', 'lecturer']);
$user_id = $_SESSION['user_id'];

// Fetch Courses for Dropdown
$courses = [];
$c_res = mysqli_query($conn, "SELECT id, code, title FROM courses WHERE deleted_at IS NULL ORDER BY code");
if ($c_res) {
    while ($row = mysqli_fetch_assoc($c_res)) {
        $courses[] = $row;
    }
}

// Output logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = (int)$_POST['course_id'];
    $exam_type = sanitize_input($conn, $_POST['exam_type']);
    $academic_year = (int)$_POST['academic_year'];
    $semester = (int)$_POST['semester'];
    $duration = (int)$_POST['duration'];
    $instructions = sanitize_input($conn, $_POST['instructions'] ?? '');

    if ($course_id > 0 && !empty($exam_type) && $academic_year > 0 && $semester > 0) {
        // Look up course logic
        $c_q = mysqli_query($conn, "SELECT code, department_id, college_id FROM courses WHERE id = $course_id");
        $courseData = mysqli_fetch_assoc($c_q);

        if ($courseData) {
            $paper_code = $courseData['code'] . '-' . $exam_type . '-' . $academic_year . '-S' . $semester;

            // Assume we fetch hod_id and dean_id based on department and college...
            // Standard approach: Let's quickly query the first HOD and DEAN available
            $hod_id = 'NULL';
            $hq = mysqli_query($conn, "SELECT id FROM users WHERE department_id = {$courseData['department_id']} AND role = 'hod' LIMIT 1");
            if ($hq && mysqli_num_rows($hq) > 0) $hod_id = mysqli_fetch_assoc($hq)['id'];

            $dean_id = 'NULL';
            $dq = mysqli_query($conn, "SELECT id FROM users WHERE college_id = {$courseData['college_id']} AND role = 'dean' LIMIT 1");
            if ($dq && mysqli_num_rows($dq) > 0) $dean_id = mysqli_fetch_assoc($dq)['id'];

            // Check if paper_code already exists to prevent fatal unique constraint exception
            $check_q = mysqli_query($conn, "SELECT id FROM exam_papers WHERE paper_code = '$paper_code'");
            if ($check_q && mysqli_num_rows($check_q) > 0) {
                set_flash_message('danger', "Warning: An exam paper using the code <strong>$paper_code</strong> already exists! Please use a different type/semester, or delete the old one first.");
            } else {
                $sql = "INSERT INTO exam_papers (paper_code, course_id, created_by, exam_type, academic_year, semester, duration, instructions, status, hod_id, dean_id) 
                        VALUES ('$paper_code', $course_id, $user_id, '$exam_type', $academic_year, $semester, $duration, '$instructions', 'draft', $hod_id, $dean_id)";

                if (mysqli_query($conn, $sql)) {
                    $new_id = mysqli_insert_id($conn);
                    set_flash_message('success', 'Exam paper draft created! You can now add questions.');
                    header("Location: paper_questions.php?id=" . $new_id);
                    exit();
                } else {
                    set_flash_message('danger', 'Error creating exam paper drafting phase.');
                }
            }
        }
    } else {
        set_flash_message('danger', 'Please fill all required fields properly.');
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-file-earmark-plus"></i> Create Exam Paper</h2>
            <a href="exam_papers.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Papers
            </a>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <form method="POST">

                    <div class="row mb-4">
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Select Course <span class="text-danger">*</span></label>
                            <select class="form-select" name="course_id" required>
                                <option value="">-- Choose a Course --</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['code'] . ' - ' . $c['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Exam Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="exam_type" required>
                                <option value="final">Final Exam</option>
                                <option value="midterm">Midterm</option>
                                <option value="quiz">Quiz / Test</option>
                                <option value="supplementary">Supplementary</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Duration (Minutes) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="duration" value="120" min="30" max="360" required>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Academic Year <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="academic_year" value="<?php echo date('Y'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Semester <span class="text-danger">*</span></label>
                            <select class="form-select" name="semester" required>
                                <option value="1">Semester 1</option>
                                <option value="2">Semester 2</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Candidate Instructions (Optional)</label>
                        <textarea class="form-control" name="instructions" rows="4" placeholder="Enter specific instructions for students (e.g., Do not use calculators)"></textarea>
                    </div>

                    <hr class="my-4">

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-gear"></i> Generate Paper Outline</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>