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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simplebar@latest/dist/simplebar.css"/>

<script src="https://cdn.jsdelivr.net/npm/simplebar@latest/dist/simplebar.min.js"></script>
</head>
<body>
    <header class="main-header">
        <div class="header-content">
            <div class="header-left">
                <button id="sidebar-toggle" style="background:none; border:none; color:#00cec9; font-size:1.5em; cursor:pointer; margin-right: 15px;">
    <i class="fas fa-bars"></i>
</button>
               
                    <span class="portal-title"><b>ONLINE MAGAZINE PORTAL</b></span>
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
   <script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');

    // --- START COLLAPSED BY DEFAULT ---
    if (sidebar && mainContent) {
        sidebar.classList.add('collapsed');
    }
    // --- END ---

    if (sidebarToggle && sidebar && mainContent) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });
    }
});
</script>
</body>
</html>