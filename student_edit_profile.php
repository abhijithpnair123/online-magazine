<?php
// student_edit_profile.php
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
$successMessage = '';
$errorMessage = '';

// Fetch existing data for pre-filling the form
$student_details = [];
$departments = [];
$programs = [];

// Fetch student details
$stmt_fetch_data = $conn->prepare("
    SELECT 
        ts.student_name, ts.email, ts.stu_phn, ts.gender, ts.dept_id, ts.program_id,
        ts.password
    FROM tbl_student ts
    WHERE ts.student_id = ?
");
$stmt_fetch_data->bind_param("i", $student_id);
$stmt_fetch_data->execute();
$result_fetch_data = $stmt_fetch_data->get_result();

if ($result_fetch_data->num_rows == 1) {
    $fetched_data = $result_fetch_data->fetch_assoc();
    $student_details = [
        'student_name' => $fetched_data['student_name'],
        'email' => $fetched_data['email'],
        'stu_phn' => $fetched_data['stu_phn'],
        'gender' => $fetched_data['gender'],
        'dept_id' => $fetched_data['dept_id'],
        'program_id' => $fetched_data['program_id'],
        'current_password_hash' => $fetched_data['password']
    ];
} else {
    $_SESSION['error_message'] = "Profile data could not be loaded.";
    header("Location: student_my_profile.php");
    exit();
}
$stmt_fetch_data->close();

// Fetch departments for dropdown
$stmt_depts = $conn->prepare("SELECT dept_id, dept_name FROM tbl_department ORDER BY dept_name ASC");
$stmt_depts->execute();
$result_depts = $stmt_depts->get_result();
while ($row = $result_depts->fetch_assoc()) {
    $departments[] = $row;
}
$stmt_depts->close();

// Fetch programs for dropdown
$stmt_programs = $conn->prepare("SELECT program_id, program_name FROM tbl_program ORDER BY program_name ASC");
$stmt_programs->execute();
$result_programs = $stmt_programs->get_result();
while ($row = $result_programs->fetch_assoc()) {
    $programs[] = $row;
}
$stmt_programs->close();


// --- Handle Form Submission (Update Profile) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $new_student_name = $conn->real_escape_string($_POST['student_name']);
    $new_email = $conn->real_escape_string($_POST['email']);
    $new_stu_phn = $conn->real_escape_string($_POST['stu_phn']);
    $new_gender = $conn->real_escape_string($_POST['gender']);
    $new_dept_id = $conn->real_escape_string($_POST['dept_id']);
    $new_program_id = $conn->real_escape_string($_POST['program_id']);
    
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $conn->begin_transaction();

    try {
        // Update tbl_student details
        $stmt_update_student = $conn->prepare("UPDATE tbl_student SET student_name = ?, email = ?, stu_phn = ?, gender = ?, dept_id = ?, program_id = ? WHERE student_id = ?");
        $stmt_update_student->bind_param("ssssiii", $new_student_name, $new_email, $new_stu_phn, $new_gender, $new_dept_id, $new_program_id, $student_id);
        
        if (!$stmt_update_student->execute()) {
            throw new Exception("Error updating student details: " . $stmt_update_student->error);
        }
        $stmt_update_student->close();

        // Update password if provided
        if (!empty($new_password)) {
            if ($new_password !== $confirm_password) {
                throw new Exception("New password and confirm password do not match.");
            }
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt_update_password = $conn->prepare("UPDATE tbl_student SET password = ? WHERE student_id = ?");
            $stmt_update_password->bind_param("si", $hashed_password, $student_id);
            
            if (!$stmt_update_password->execute()) {
                throw new Exception("Error updating password: " . $stmt_update_password->error);
            }
            $stmt_update_password->close();
        }

        $conn->commit();
        $_SESSION['success_message'] = "Profile updated successfully!";
        $_SESSION['user_name'] = $new_student_name; 
        header("Location: student_my_profile.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $errorMessage = "Failed to update profile: " . $e->getMessage();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Student Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Edit My Profile</h1>
            <p>Update your personal details and change your password.</p>

            <?php if ($successMessage): ?>
                <div class="success-message"><?php echo $successMessage; ?></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="error-message"><?php echo $errorMessage; ?></div>
            <?php endif; ?>

            <div class="card profile-edit-card">
                <h2>Update Information</h2>
                <form action="student_edit_profile.php" method="POST">
                    <div class="form-group">
                        <label for="username">Username (Email):</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($student_details['email'] ?? ''); ?>" disabled>
                        <small>Your email is used as your username and cannot be changed here.</small>
                    </div>
                    <div class="form-group">
                        <label for="student_name">Full Name:</label>
                        <input type="text" id="student_name" name="student_name" value="<?php echo htmlspecialchars($student_details['student_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($student_details['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="stu_phn">Phone Number:</label>
                        <input type="text" id="stu_phn" name="stu_phn" value="<?php echo htmlspecialchars($student_details['stu_phn'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender:</label>
                        <select id="gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="male" <?php echo (isset($student_details['gender']) && $student_details['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo (isset($student_details['gender']) && $student_details['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo (isset($student_details['gender']) && $student_details['gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="dept_id">Department:</label>
                        <select id="dept_id" name="dept_id" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['dept_id']); ?>" <?php echo (isset($student_details['dept_id']) && $student_details['dept_id'] == $dept['dept_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['dept_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="program_id">Program:</label>
                        <select id="program_id" name="program_id" required>
                            <option value="">Select Program</option>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?php echo htmlspecialchars($program['program_id']); ?>" <?php echo (isset($student_details['program_id']) && $student_details['program_id'] == $program['program_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($program['program_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                
                    
            <hr class="form-separator">

                    <h2>Change Password</h2>
                    
                    <div class="form-group">
                        <label for="new_password">New Password:</label>
                        <div class="password-wrapper">
                            <input type="password" id="new_password" name="new_password" placeholder="Leave blank to keep current password">
                            <i class="fas fa-eye password-toggle-icon"></i>
                        </div>
                        <small>Minimum 8 characters, include uppercase, lowercase, numbers, and symbols.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password:</label>
                        <div class="password-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password">
                            <i class="fas fa-eye password-toggle-icon"></i>
                        </div>
                    </div>

                    <button type="submit" name="update_profile" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                    <a href="student_my_profile.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>

    <style>
        .profile-edit-card {
            max-width: 700px;
            margin: 20px auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            background-color: #fff;
        }
        /* ... other styles remain the same ... */
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        /* --- CORRECTED: Styles for Password Toggle Icon --- */
       /* --- CORRECTED: Styles for Password Toggle Icon --- */
.password-wrapper {
    position: relative; /* This is the positioning container for the icon */
}

/* This adds space so the text doesn't run under the icon */
.password-wrapper input {
    padding-right: 40px; 
}

.password-toggle-icon {
    position: absolute;
    right: 25px;

    /* These two lines guarantee perfect vertical alignment */
    top: 50%;
    transform: translateY(-50%);
    
    color: #a0aec0; /* A little grey shade */
    cursor: pointer;
    transition: color 0.2s ease;
}

.password-toggle-icon:hover {
    color: #4a5568; /* Darker grey on hover */
}
        }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleIcons = document.querySelectorAll('.password-toggle-icon');

        toggleIcons.forEach(icon => {
            icon.addEventListener('click', function() {
                // The input field is the icon's previous sibling inside the wrapper
                const passwordField = this.previousElementSibling;
                
                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    this.classList.remove('fa-eye');
                    this.classList.add('fa-eye-slash');
                } else {
                    passwordField.type = 'password';
                    this.classList.remove('fa-eye-slash');
                    this.classList.add('fa-eye');
                }
            });
        });
    });
    </script>
</body>
</html>