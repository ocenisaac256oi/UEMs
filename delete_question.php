<?php
// delete_question.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_role(['admin', 'hod', 'dean', 'lecturer']);

$question_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($question_id > 0) {
    // Only soft delete
    $query = "UPDATE questions SET deleted_at = CURRENT_TIMESTAMP WHERE id = $question_id";
    if (mysqli_query($conn, $query)) {
        set_flash_message('success', 'Question deleted successfully.');
    } else {
        set_flash_message('danger', 'Failed to delete question.');
    }
}

header("Location: question_bank.php");
exit();
?>
