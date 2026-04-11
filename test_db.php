<?php
require_once 'config/database.php';
$output = [];

$res = mysqli_query($conn, 'SELECT id, email, role, department_id, college_id FROM users');
while($row = mysqli_fetch_assoc($res)) $output['users'][] = $row;

$res = mysqli_query($conn, 'SELECT id, course_id, status, hod_id FROM exam_papers');
while($row = mysqli_fetch_assoc($res)) $output['exam_papers'][] = $row;

$res = mysqli_query($conn, 'SELECT id, code, department_id FROM courses');
while($row = mysqli_fetch_assoc($res)) $output['courses'][] = $row;

echo json_encode($output);
?>
