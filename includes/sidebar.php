<?php
// includes/sidebar.php
// This file will be included in admin_dashboard.php, staff_dashboard.php, student_dashboard.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Basic authorization check (more robust checks should be on each page)
// Define which user types are allowed to access this page


$usertype = $_SESSION['usertype'];
$welcome_name = '';

// Determine the name to display based on usertype
if ($usertype === 'admin') {
    $welcome_name = 'Admin';
} elseif ($usertype === 'staff') {
    $welcome_name = 'Staff';
} elseif ($usertype === 'student') {
    // For students, we need to fetch their name from tbl_student
    // assuming you stored the student's name in $_SESSION['user_name'] during login
    // OR you can fetch it from the database here if 'user_name' is not in session.
    // Let's assume you store it in $_SESSION['user_name'] for simplicity and efficiency.
    if (isset($_SESSION['user_name'])) {
        $welcome_name = $_SESSION['user_name'];
    } else {
        // Fallback if student name isn't in session (should ideally be set during login)
        $welcome_name = 'Student';
        // You could also fetch it from the database here using $_SESSION['user_id']
        // include 'db_connect.php';
        // $stmt = $conn->prepare("SELECT student_name FROM tbl_student WHERE student_id = ?");
        // $stmt->bind_param("i", $_SESSION['user_id']);
        // $stmt->execute();
        // $result = $stmt->get_result();
        // if ($row = $result->fetch_assoc()) {
        //     $welcome_name = $row['student_name'];
        // }
        // $stmt->close();
    }
}
?>
<div class="sidebar">
    <h3>Welcome, <?php echo htmlspecialchars($welcome_name); ?>!</h3>
    <ul>
        <?php if ($usertype === 'admin'): ?>
            <li><a href="home.php" data-label="Home"><i class="fas fa-home"></i><span class="sidebar-text"> Home</span></a></li>
                    <li><a href="admin_overview.php" data-label="Overview"><i class="fas fa-chart-pie"></i><span class="sidebar-text"> Overview</span></a></li>
            <li><a href="view_events.php" data-label="View Events"><i class="fas fa-calendar-alt"></i><span class="sidebar-text"> View Events</span></a></li>
            <li><a href="admin_manage_users.php" data-label="Manage Users"><i class="fas fa-users-cog"></i><span class="sidebar-text"> Manage Users</span></a></li>
            <li><a href="admin_general_content.php" data-label="Approve General Content"><i class="fas fa-file-alt"></i><span class="sidebar-text"> Approve General Content</span></a></li>
            <li><a href="admin_combined_competitions.php?view=approval" data-label="Approve Competition Content"><i class="fas fa-check-double"></i><span class="sidebar-text"> Approve Competition Content</span></a></li>
            <li><a href="admin_login_history.php" data-label="Login History"><i class="fas fa-history"></i><span class="sidebar-text"> Login History</span></a></li>
            <li><a href="manage_publish.php" data-label="Publish Content"><i class="fas fa-upload"></i><span class="sidebar-text"> Publish Content</span></a></li>
            <li><a href="admin_manage_events.php" data-label="Manage Events"><i class="fas fa-calendar-plus"></i><span class="sidebar-text"> Manage Events</span></a></li>
            <li><a href="admin_combined_competitions.php?view=manage" data-label="Manage Competitions"><i class="fas fa-trophy"></i><span class="sidebar-text"> Manage Competitions</span></a></li>
            <li><a href="admin_manage_departments.php" data-label="Manage Departments"><i class="fas fa-building"></i><span class="sidebar-text"> Manage Departments</span></a></li>
            <li><a href="admin_manage_programs.php" data-label="Manage Programs"><i class="fas fa-graduation-cap"></i><span class="sidebar-text"> Manage Programs</span></a></li>
            <li><a href="view_competition_results.php" data-label="Competition Results"><i class="fas fa-poll"></i><span class="sidebar-text"> Competition Results</span></a></li>
            <li> <a href="admin_process_winners.php" data-label="Process Winners"><i class="fas fa-paper-plane"></i><span class="sidebar-text"> Process Winners</span></a></li>

        <?php elseif ($usertype === 'staff'): ?>
            <li><a href="home.php" data-label="Home"><i class="fas fa-home"></i><span class="sidebar-text"> Home</span></a></li>
            <li><a href="staff_my_profile.php" data-label="My Profile"><i class="fas fa-user-edit"></i><span class="sidebar-text"> My Profile</span></a></li>
            <li><a href="view_events.php" data-label="View Events"><i class="fas fa-calendar-alt"></i><span class="sidebar-text"> View Events</span></a></li>
            <li><a href="staff_general_content.php" data-label="Approve General Content"><i class="fas fa-file-alt"></i><span class="sidebar-text"> Approve General Content</span></a></li>
            <li><a href="staff_combined_competitions.php?view=approval" data-label="Approve Competition Content"><i class="fas fa-check-double"></i><span class="sidebar-text"> Approve Competition Content</span></a></li>
            <li><a href="manage_publish.php" data-label="Publish Content"><i class="fas fa-upload"></i><span class="sidebar-text"> Publish Content</span></a></li>
            <li><a href="staff_combined_competitions.php?view=manage" data-label="Manage Competitions"><i class="fas fa-trophy"></i><span class="sidebar-text"> Manage Competitions</span></a></li>
            <li><a href="staff_view_all_students.php" data-label="View All Students"><i class="fas fa-users"></i><span class="sidebar-text"> View All Students</span></a></li>
            <li><a href="view_competition_results.php" data-label="Competition Results"><i class="fas fa-poll"></i><span class="sidebar-text"> Competition Results</span></a></li>

        <?php elseif ($usertype === 'student'): ?>
            <li><a href="home.php" data-label="Home"><i class="fas fa-home"></i><span class="sidebar-text"> Home</span></a></li>
            <li><a href="student_my_profile.php" data-label="My Profile"><i class="fas fa-user"></i><span class="sidebar-text"> My Profile</span></a></li>
            <li><a href="view_events.php" data-label="View Events"><i class="fas fa-calendar-alt"></i><span class="sidebar-text"> View Events</span></a></li>
            <li><a href="student_submit_content.php" data-label="Submit Content"><i class="fas fa-paper-plane"></i><span class="sidebar-text"> Submit Content</span></a></li>
            <li><a href="student_my_submissions.php" data-label="My Submissions"><i class="fas fa-history"></i><span class="sidebar-text"> My Submissions</span></a></li>
            <li><a href="view_competition_results.php" data-label="Competition Results"><i class="fas fa-poll"></i><span class="sidebar-text"> View Competition Results</span></a></li>
        <?php endif; ?>
        
        <li><a href="logout.php" data-label="Logout"><i class="fas fa-sign-out-alt"></i><span class="sidebar-text"> Logout</span></a></li>
    </ul>

    <style>
    /* Collapsed tooltip: shows label on hover when sidebar is collapsed */
    .sidebar.collapsed ul li a { position: relative; }
    .sidebar.collapsed ul li a::after {
        content: attr(data-label);
        position: absolute;
        left: 72px;
        top: 50%;
        transform: translateY(-50%) translateX(-8px);
        background: var(--color-surface, #FFFFFF);
        color: var(--color-text, #2B3A42);
        border: 1px solid rgba(0, 191, 165, 0.2);
        padding: 6px 10px;
        border-radius: 8px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        white-space: nowrap;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s ease, transform 0.2s ease;
        z-index: 100;
    }
    .sidebar.collapsed ul li a:hover::after {
        opacity: 1;
        transform: translateY(-50%) translateX(0);
    }
    /* Fun icon hover when collapsed */
    .sidebar.collapsed ul li a i { transition: transform 0.2s ease; }
    .sidebar.collapsed ul li a:hover i { transform: scale(1.1) rotate(6deg); }
    </style>
</div>
