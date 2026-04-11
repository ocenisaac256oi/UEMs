<?php
// view_question.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_role(['admin', 'hod', 'dean', 'lecturer', 'student']);

$question_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($question_id <= 0) {
    set_flash_message('danger', 'Invalid question ID.');
    header("Location: question_bank.php");
    exit();
}

// Fetch question details
$query = "SELECT q.*, c.code as course_code, c.title as course_title, CONCAT(u.first_name, ' ', u.last_name) as creator_name 
          FROM questions q 
          JOIN courses c ON q.course_id = c.id 
          LEFT JOIN users u ON q.created_by = u.id 
          WHERE q.id = $question_id AND (q.deleted_at IS NULL OR q.deleted_at = '0000-00-00 00:00:00')";

$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    set_flash_message('danger', 'Question not found.');
    header("Location: question_bank.php");
    exit();
}

$question = mysqli_fetch_assoc($result);

// Decode options if multiple choice
$options = [];
if ($question['question_type'] == 'multiple_choice' && $question['options']) {
    $options = json_decode($question['options'], true);
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-eye"></i> View Question</h2>
                <div>
                    <a href="question_bank.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Bank
                    </a>
                    <?php if (in_array($_SESSION['role'], ['admin', 'hod', 'dean', 'lecturer'])): ?>
                        <a href="edit_question.php?id=<?php echo $question_id; ?>" class="btn btn-outline-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <!-- Course Info -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="text-muted small text-uppercase">Course</label>
                            <p class="fw-bold h5"><?php echo htmlspecialchars($question['course_code'] . ' - ' . $question['course_title']); ?></p>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small text-uppercase">Question Type</label>
                            <p class="fw-bold">
                                <?php
                                $type_labels = [
                                    'essay' => '📝 Essay / Free Text',
                                    'multiple_choice' => '🔘 Multiple Choice',
                                    'true_false' => '✓ True / False',
                                    'structured' => '📊 Structured'
                                ];
                                echo $type_labels[$question['question_type']] ?? ucfirst($question['question_type']);
                                ?>
                            </p>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small text-uppercase">Marks</label>
                            <p class="fw-bold h5 text-primary"><?php echo $question['marks']; ?> marks</p>
                        </div>
                    </div>

                    <!-- Question Text -->
                    <div class="mb-4">
                        <label class="text-muted small text-uppercase">Question</label>
                        <div class="p-4 bg-light rounded mt-2" style="font-size: 1.1em;">
                            <?php echo nl2br(htmlspecialchars($question['question_text'])); ?>
                        </div>
                    </div>

                    <!-- Options for Multiple Choice -->
                    <?php if ($question['question_type'] == 'multiple_choice' && !empty($options)): ?>
                        <div class="mb-4">
                            <label class="text-muted small text-uppercase">Answer Options</label>
                            <div class="mt-2">
                                <?php foreach ($options as $index => $option): ?>
                                    <div class="p-3 mb-2 bg-light rounded d-flex align-items-center">
                                        <div class="fw-bold me-3" style="width: 30px;"><?php echo chr(65 + $index); ?>.</div>
                                        <div class="flex-grow-1"><?php echo htmlspecialchars($option); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- True/False Options -->
                    <?php if ($question['question_type'] == 'true_false'): ?>
                        <div class="mb-4">
                            <label class="text-muted small text-uppercase">Answer Options</label>
                            <div class="mt-2">
                                <div class="p-3 mb-2 bg-light rounded">🔘 True</div>
                                <div class="p-3 mb-2 bg-light rounded">🔘 False</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Metadata -->
                    <div class="row mt-4 pt-3 border-top">
                        <div class="col-md-6">
                            <label class="text-muted small text-uppercase">Created By</label>
                            <p class="mb-0"><?php echo htmlspecialchars($question['creator_name'] ?? 'Unknown'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small text-uppercase">Created At</label>
                            <p class="mb-0"><?php echo date('F j, Y g:i A', strtotime($question['created_at'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this question? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="delete_question.php?id=<?php echo $question_id; ?>" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>