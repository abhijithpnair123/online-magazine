<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'student') {
    header("Location: index.php");
    exit();
}

// Fetch student details (optional, for display)
$student_id = $_SESSION['user_id'];
$student_details = null;
$stmt_student = $conn->prepare("SELECT student_name, email, stu_phn, gender, dept_name, program_name FROM tbl_student ts JOIN tbl_department td ON ts.dept_id = td.dept_id JOIN tbl_program tp ON ts.program_id = tp.program_id WHERE student_id = ?");
$stmt_student->bind_param("i", $student_id);
$stmt_student->execute();
$result_student = $stmt_student->get_result();
if ($result_student->num_rows == 1) {
    $student_details = $result_student->fetch_assoc();
}
$stmt_student->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Student Dashboard</h1>
            <p>Welcome, <?php echo htmlspecialchars($student_details['student_name'] ?? $_SESSION['user_email']); ?>!</p>
            <p>Use the sidebar to submit content, view your submissions, and more.</p>
        </div>
    </div>
</body>
</html>