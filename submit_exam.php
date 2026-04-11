<?php
// submit_exam.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

require_role(['student']);
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['session_id'])) {
    $session_id = (int)$_POST['session_id'];

    // Verify session
    $sq = mysqli_query($conn, "SELECT * FROM exam_sessions WHERE id = $session_id AND student_id = $user_id LIMIT 1");
    if (!$sq || mysqli_num_rows($sq) === 0) {
        set_flash_message('danger', 'Invalid session.');
        header("Location: student_dashboard.php");
        exit();
    }
    $session = mysqli_fetch_assoc($sq);
    $paper_id = $session['exam_paper_id'];
    
    // Save any submitted answers from the form POST (fallback/primary submission)
    foreach ($_POST as $key => $val) {
        if (strpos($key, 'q_') === 0) {
            $q_id = (int)str_replace('q_', '', $key);
            if ($q_id > 0) {
                $clean_answer = mysqli_real_escape_string($conn, trim($val));
                $ans_check = mysqli_query($conn, "SELECT id FROM student_answers WHERE session_id = $session_id AND question_id = $q_id LIMIT 1");
                if ($ans_check && mysqli_num_rows($ans_check) > 0) {
                    mysqli_query($conn, "UPDATE student_answers SET submitted_answer = '$clean_answer' WHERE session_id = $session_id AND question_id = $q_id");
                } else {
                    mysqli_query($conn, "INSERT INTO student_answers (session_id, question_id, submitted_answer) VALUES ($session_id, $q_id, '$clean_answer')");
                }
            }
        }
    }

    // Close session if it isn't already
    if ($session['status'] === 'in_progress') {
        mysqli_query($conn, "UPDATE exam_sessions SET status = 'completed' WHERE id = $session_id");
    }

    // Process Auto-Grading
    // Fetch all answers mapped to questions and paper questions marks
    $query = "SELECT sa.id as answer_id, sa.submitted_answer, 
              q.question_type, q.correct_answer, 
              epq.marks as max_marks
              FROM student_answers sa
              JOIN questions q ON sa.question_id = q.id
              JOIN exam_paper_questions epq ON (epq.question_id = q.id AND epq.exam_paper_id = $paper_id)
              WHERE sa.session_id = $session_id";
              
    $res = mysqli_query($conn, $query);
    
    $total_score = 0;
    $needs_manual_review = false;

    if ($res) {
        while($row = mysqli_fetch_assoc($res)) {
            $type = $row['question_type'];
            $submitted = trim((string)$row['submitted_answer']);
            $correct = trim((string)$row['correct_answer']);
            $max_marks = (int)$row['max_marks'];
            
            $marks_awarded = 0;
            $is_correct = 'NULL';

            if ($type === 'multiple_choice' || $type === 'true_false') {
                if (strcasecmp($submitted, $correct) == 0) {
                    $marks_awarded = $max_marks;
                    $is_correct = 1;
                } else {
                    $is_correct = 0;
                }
                
                // Update specific answer row
                mysqli_query($conn, "UPDATE student_answers SET marks_awarded = $marks_awarded, is_correct = $is_correct WHERE id = {$row['answer_id']}");
                
                $total_score += $marks_awarded;
            } else {
                // Essays, short answers, case studies need human review
                $needs_manual_review = true;
            }
        }
    }

    // Create or update result record
    $grading_status = $needs_manual_review ? 'pending_manual_review' : 'graded';
    
    $r_check = mysqli_query($conn, "SELECT id FROM exam_results WHERE exam_paper_id = $paper_id AND student_id = $user_id");
    if ($r_check && mysqli_num_rows($r_check) > 0) {
        mysqli_query($conn, "UPDATE exam_results SET total_score = $total_score, grading_status = '$grading_status' WHERE exam_paper_id = $paper_id AND student_id = $user_id");
    } else {
        mysqli_query($conn, "INSERT INTO exam_results (exam_paper_id, student_id, total_score, grading_status) VALUES ($paper_id, $user_id, $total_score, '$grading_status')");
    }
    
    set_flash_message('success', 'Your exam has been submitted successfully!');
    header("Location: student_results.php");
    exit();
}

header("Location: student_dashboard.php");
exit();
