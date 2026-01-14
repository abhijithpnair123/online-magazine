<?php
// includes/header.php
// This file provides a consistent header for all main pages of the portal.

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fetch user details for display
$user_name_display = '';
if (isset($_SESSION['user_name'])) {
    // Convert name to uppercase for display
    $user_name_display = strtoupper(htmlspecialchars($_SESSION['user_name']));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <header class="main-header">
        <div class="header-content">
            <div class="header-left">
               
                    <span class="portal-title"><b>ONLINE MAGAZINE PORTAL</b></span>
                </a>
            </div>
            
            <div class="header-right">
                <?php if (!empty($user_name_display)): ?>
                    <div class="user-info">
                        <i class="fas fa-user-circle"></i>
                        <span class="welcome-text">Welcome, <?php echo $user_name_display; ?></span>
                    </div>
                <?php endif; ?>
                
                </div>
        </div>
    </header>
</body>
</html>