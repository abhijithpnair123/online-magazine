<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
include 'db_connect.php';

// Security Check: Ensure only an admin or staff is logged in
// You might need to adjust this based on your session variable names
if (!isset($_SESSION['user_id']) || ($_SESSION['usertype'] !== 'admin' && $_SESSION['user_type'] !== 'staff')) {
    header("Location: index.php"); // Redirect unauthorized users
    exit();
}

$output = ""; // Variable to store the script's output

// Check if the form has been submitted (i.e., the button was clicked)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_now'])) {
    
    // Start output buffering to capture all `echo` statements from the included script
    ob_start(); 
    
    echo "--- Starting Manual Process at " . date('Y-m-d H:i:s') . " ---\n";
    
    // Include your existing processing script. Its output will be captured.
    include 'process_winners.php'; 
    
    echo "--- Manual Process Finished ---\n";

    // Get the captured output and clean the buffer
    $output = ob_get_clean(); 
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Competition Winners</title>
    <link rel="stylesheet" href="css/style.css"> </head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Process Competition Winners Manually</h1>
            <p>Click the button below to check for any finished competitions, determine the winners, and send congratulatory emails. This action will only process competitions whose voting period has ended.</p>
            
            <div class="card mt-30">
                <h2>Trigger Winner Processing</h2>
                <form action="admin_process_winners.php" method="POST">
                    <button type="submit" name="process_now" class="btn btn-primary">
                        <i class="fas fa-play-circle"></i> Process Winners Now
                    </button>
                </form>
            </div>

            <?php if (!empty($output)): ?>
                <div class="card mt-30">
                    <h2>Processing Log</h2>
                    <pre style="background-color: #f4f4f4; padding: 15px; border-radius: 5px; white-space: pre-wrap; word-wrap: break-word;"><?php echo htmlspecialchars($output); ?></pre>
                </div>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>