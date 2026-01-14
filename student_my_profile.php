<?php
// student_my_profile.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db_connect.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'student') {
    header("Location: index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$successMessage = $_SESSION['success_message'] ?? '';
$errorMessage = $_SESSION['error_message'] ?? '';

// Clear messages from session after displaying
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

$student_details = null;
$department_name = 'N/A';
$program_name = 'N/A';
// Initialize username to a default, will be replaced by email from tbl_student
$username_display = 'N/A'; 

// Fetch student details
$stmt_student = $conn->prepare("
    SELECT 
        ts.student_name, ts.email, ts.stu_phn, ts.gender, 
        td.dept_name, tp.program_name
        -- No need to select 'password' here as it's not displayed on this page
    FROM tbl_student ts
    LEFT JOIN tbl_department td ON ts.dept_id = td.dept_id
    LEFT JOIN tbl_program tp ON ts.program_id = tp.program_id
    WHERE ts.student_id = ?
");
$stmt_student->bind_param("i", $student_id);
$stmt_student->execute();
$result_student = $stmt_student->get_result();

if ($result_student->num_rows == 1) {
    $student_details = $result_student->fetch_assoc();
    $department_name = htmlspecialchars($student_details['dept_name'] ?? 'N/A');
    $program_name = htmlspecialchars($student_details['program_name'] ?? 'N/A');
    // Use email as the username display since tbl_users is not available
    $username_display = htmlspecialchars($student_details['email'] ?? 'N/A'); 
} else {
    $errorMessage = "Student profile not found.";
}
$stmt_student->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Student Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>My Profile</h1>
            <p>View your personal and academic details.</p>

            <?php if ($successMessage): ?>
                <div class="success-message"><?php echo $successMessage; ?></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="error-message"><?php echo $errorMessage; ?></div>
            <?php endif; ?>

            <?php if ($student_details): ?>
                <div class="card profile-card">
                    <h2>Personal Information</h2>
                    <!-- Student ID removed as it's not editable -->
                    <div class="profile-item">
                        <strong>Username:</strong> <?php echo $username_display; ?>
                    </div>
                    <div class="profile-item">
                        <strong>Name:</strong> <?php echo htmlspecialchars($student_details['student_name'] ?? 'N/A'); ?>
                    </div>
                    <div class="profile-item">
                        <strong>Email:</strong> <?php echo htmlspecialchars($student_details['email'] ?? 'N/A'); ?>
                    </div>
                    <div class="profile-item">
                        <strong>Phone:</strong> <?php echo htmlspecialchars($student_details['stu_phn'] ?? 'N/A'); ?>
                    </div>
                    <div class="profile-item">
                        <strong>Gender:</strong> <?php echo htmlspecialchars(ucfirst($student_details['gender'] ?? 'N/A')); ?>
                    </div>
                    <div class="profile-item">
                        <strong>Department:</strong> <?php echo $department_name; ?>
                    </div>
                    <div class="profile-item">
                        <strong>Program:</strong> <?php echo $program_name; ?>
                    </div>
                    <div class="profile-actions">
                        <a href="student_edit_profile.php" class="btn btn-primary"><i class="fas fa-edit"></i> Edit Profile</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .profile-card {
            max-width: 720px;
            margin: 20px auto;
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 12px 28px rgba(0,0,0,0.1);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(245, 245, 245, 0.94));
            border: 1px solid rgba(255, 107, 107, 0.18);
            color: var(--color-text, #333333);
        }
        .profile-card h2 {
            text-align: center;
            margin-bottom: 25px;
            font-size: 1.8em;
            font-family: 'Poppins','Inter',sans-serif;
            background: linear-gradient(45deg, var(--color-primary-2, #FF8E8E), var(--color-primary, #FF6B6B));
            -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
        }
        .profile-item {
            margin-bottom: 15px;
            padding: 12px 0;
            border-bottom: 1px dashed #E0E0E0;
            font-size: 1.05em;
            color: var(--color-text, #333333);
        }
        .profile-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .profile-item strong {
            color: var(--color-primary-2, #FF8E8E);
            display: inline-block;
            min-width: 120px; /* Align labels */
        }
        .profile-actions {
            text-align: center;
            margin-top: 30px;
        }
        .btn-primary {
            background: linear-gradient(45deg, var(--color-primary-2, #FF8E8E), var(--color-primary, #FF6B6B));
            color: #FEFDFB;
            padding: 12px 25px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 700;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: 1px solid rgba(255, 107, 107, 0.25);
            box-shadow: 0 8px 20px rgba(255, 107, 107, 0.25);
            font-family: 'Poppins','Inter',sans-serif;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(255, 107, 107, 0.35); }
        .success-message, .error-message {
            margin-top: 20px;
        }
    </style>
</body>
</html>