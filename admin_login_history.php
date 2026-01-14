<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Fetch login history, most recent first
$login_history = [];
// MODIFIED: Removed ip_address from the query
$result = $conn->query("SELECT user_email, usertype, login_time FROM tbl_login_history ORDER BY login_time DESC LIMIT 200");
while ($row = $result->fetch_assoc()) {
    $login_history[] = $row;
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login History - Admin Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>User Login History</h1>
            <p>Showing the 200 most recent login events.</p>

            <div class="card mt-30">
                <h2>Login Records</h2>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>User Email</th>
                                <th>User Type</th>
                                <th>Login Time & Date</th>
                                </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($login_history)): ?>
                                <tr><td colspan="3">No login history found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($login_history as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['user_email']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($log['usertype'])); ?></td>
                                    <td><?php echo date("M j, Y, g:i A", strtotime($log['login_time'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>