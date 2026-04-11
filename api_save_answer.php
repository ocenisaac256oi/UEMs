<?php
// api_save_answer.php
require_once 'config/database.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$session_id = (int)($_POST['session_id'] ?? 0);
$question_id = (int)($_POST['question_id'] ?? 0);
$answer = $_POST['answer'] ?? '';

if ($session_id <= 0 || $question_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

// Security Check: Is this session owned by this student and still active?
$s_check = mysqli_query($conn, "SELECT id, status FROM exam_sessions WHERE id = $session_id AND student_id = $user_id LIMIT 1");
if (!$s_check || mysqli_num_rows($s_check) === 0) {
    echo json_encode(['success' => false, 'error' => 'Session not found.']);
    exit();
}
$session = mysqli_fetch_assoc($s_check);
if ($session['status'] !== 'in_progress') {
    echo json_encode(['success' => false, 'error' => 'Exam is no longer active.']);
    exit();
}

$clean_answer = mysqli_real_escape_string($conn, $answer);

// Upsert answer
$ans_check = mysqli_query($conn, "SELECT id FROM student_answers WHERE session_id = $session_id AND question_id = $question_id LIMIT 1");
if ($ans_check && mysqli_num_rows($ans_check) > 0) {
    $sql = "UPDATE student_answers SET submitted_answer = '$clean_answer' WHERE session_id = $session_id AND question_id = $question_id";
} else {
    $sql = "INSERT INTO student_answers (session_id, question_id, submitted_answer) VALUES ($session_id, $question_id, '$clean_answer')";
}

if (mysqli_query($conn, $sql)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
