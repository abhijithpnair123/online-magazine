<?php
// includes/sidebar.php
// This file will be included in admin_dashboard.php, staff_dashboard.php, student_dashboard.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Basic authorization check (more robust checks should be on each page)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['usertype'])) {
    header("Location: index.php"); // Redirect to login if not logged in
    exit();
}

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
    <p>User Type: <?php echo ucfirst($usertype); ?></p>
    <ul>
        <?php if ($usertype === 'admin'): ?>
            <li><a href="admin_dashboard.php">Dashboard</a></li>
            <li><a href="admin_manage_users.php">Manage Users</a></li>
            <li><a href="admin_manage_content_types.php">Manage Content Types</a></li>
            <li><a href="admin_manage_categories.php">Manage Categories</a></li>
            <li><a href="admin_manage_departments.php">Manage Departments</a></li>
            <li><a href="admin_manage_programs.php">Manage Programs</a></li>
            <li><a href="admin_view_all_content.php">View All Content</a></li>
        <?php elseif ($usertype === 'staff'): ?>
            <li><a href="staff_dashboard.php">Dashboard</a></li>
            <li><a href="staff_my_profile.php">My Profile</a></li>
            <li><a href="staff_general_content.php">General Content (Approval)</a></li>
            <!-- Updated both competition links to point to the new combined file -->
            <li><a href="staff_combined_competitions.php">Competition Content (Approval)</a></li>
            <li><a href="staff_combined_competitions.php"><i class="fas fa-trophy"></i> Manage Competitions</a></li>
            <li><a href="staff_manage_events.php">Manage Events</a></li>
            <li><a href="staff_view_all_students.php">View All Students</a></li>
            <li><a href="staff_publish_content.php">Publish Content</a></li>
            <li><a href="staff_manage_winners.php">Manage Winners</a></li>
        <?php elseif ($usertype === 'student'): ?>
            <li><a href="student_dashboard.php">Dashboard</a></li>
            <li><a href="student_my_profile.php">My Profile</a></li>
            <li><a href="student_submit_content.php">Submit Content</a></li>
            <li><a href="student_my_submissions.php">My Submissions</a></li>
            <li><a href="student_view_published_content.php">View Published Content</a></li>
            <li><a href="student_feedback.php">Give Feedback</a></li>
            <li><a href="student_view_competition_results.php">View Competition Results</a></li>
        <?php endif; ?>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</div>