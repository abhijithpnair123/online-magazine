<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'staff') {
    header("Location: index.php");
    exit();
}

$students = [];
$sql = "SELECT s.student_id, s.student_name, s.email, s.stu_phn, s.gender, d.dept_name, p.program_name
        FROM tbl_student s
        JOIN tbl_department d ON s.dept_id = d.dept_id
        JOIN tbl_program p ON s.program_id = p.program_id
        ORDER BY s.student_name ASC";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
} else {
    $message = "No student records found.";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View All Students</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>All Student Details</h1>
            <?php if (!empty($students)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Gender</th>
                            <th>Department</th>
                            <th>Program</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo htmlspecialchars($student['stu_phn']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($student['gender'])); ?></td>
                                <td><?php echo htmlspecialchars($student['dept_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['program_name']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php echo $message; ?></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>