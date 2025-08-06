<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Admin specific data fetching (example)
$total_students = $conn->query("SELECT COUNT(*) FROM tbl_student")->fetch_row()[0];
$total_staff = $conn->query("SELECT COUNT(*) FROM tbl_staff WHERE role = 'staff'")->fetch_row()[0];
$pending_approvals = $conn->query("SELECT COUNT(*) FROM tbl_content_approval WHERE status = 'pending'")->fetch_row()[0];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="css/style.css"> </head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Admin Dashboard</h1>
            <p>Welcome, Admin!</p>
            <div class="dashboard-stats">
                <div class="stat-box">
                    <h3>Total Students</h3>
                    <p><?php echo $total_students; ?></p>
                </div>
                <div class="stat-box">
                    <h3>Total Staff</h3>
                    <p><?php echo $total_staff; ?></p>
                </div>
                <div class="stat-box">
                    <h3>Pending Content Approvals</h3>
                    <p><?php echo $pending_approvals; ?></p>
                </div>
            </div>
            </div>
    </div>
</body>
</html>