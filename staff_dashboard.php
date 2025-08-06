<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'staff') {
    header("Location: index.php");
    exit();
}

// Fetch staff details (optional, for display)
$staff_id = $_SESSION['user_id'];
$staff_details = null;
$stmt_staff = $conn->prepare("SELECT staff_name, email, staff_phone, dept_name FROM tbl_staff ts JOIN tbl_department td ON ts.dept_id = td.dept_id WHERE staff_id = ?");
$stmt_staff->bind_param("i", $staff_id);
$stmt_staff->execute();
$result_staff = $stmt_staff->get_result();
if ($result_staff->num_rows == 1) {
    $staff_details = $result_staff->fetch_assoc();
}
$stmt_staff->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Staff Dashboard</h1>
            <p>Welcome, <?php echo htmlspecialchars($staff_details['staff_name'] ?? $_SESSION['user_email']); ?>!</p>
            <p>Use the sidebar to navigate through your options.</p>
        </div>
    </div>
</body>
</html>