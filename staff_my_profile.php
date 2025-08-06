<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'staff') {
    header("Location: index.php");
    exit();
}

$staff_id = $_SESSION['user_id'];
$message = '';

// Fetch current staff details
$staff_details = null;
$stmt = $conn->prepare("SELECT staff_name, email, staff_phone, dept_id FROM tbl_staff WHERE staff_id = ?");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 1) {
    $staff_details = $result->fetch_assoc();
} else {
    // Should not happen if session is valid
    $message = "<p class='message error'>Staff record not found.</p>";
}
$stmt->close();

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $staff_name = $conn->real_escape_string($_POST['staff_name']);
    $staff_phone = $conn->real_escape_string($_POST['staff_phone']);
    $dept_id = (int)$_POST['dept_id'];

    $stmt_update = $conn->prepare("UPDATE tbl_staff SET staff_name = ?, staff_phone = ?, dept_id = ? WHERE staff_id = ?");
    $stmt_update->bind_param("ssii", $staff_name, $staff_phone, $dept_id, $staff_id);

    if ($stmt_update->execute()) {
        $message = "<p class='message success'>Profile updated successfully!</p>";
        // Update session email if it changed (though in this schema, email is not directly updatable here)
        // Refresh details after update
        $staff_details['staff_name'] = $staff_name;
        $staff_details['staff_phone'] = $staff_phone;
        $staff_details['dept_id'] = $dept_id;
    } else {
        $message = "<p class='message error'>Error updating profile: " . $stmt_update->error . "</p>";
    }
    $stmt_update->close();
}

// Handle password update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    // First, verify current password from tbl_login
    $stmt_login_check = $conn->prepare("SELECT password FROM tbl_login WHERE login_id = ?");
    $stmt_login_check->bind_param("i", $_SESSION['user_id']); // Use login_id from session
    $stmt_login_check->execute();
    $result_login_check = $stmt_login_check->get_result();

    if ($result_login_check->num_rows == 1) {
        $login_row = $result_login_check->fetch_assoc();
        if (password_verify($current_password, $login_row['password'])) {
            if ($new_password === $confirm_new_password) {
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_update_login = $conn->prepare("UPDATE tbl_login SET password = ? WHERE login_id = ?");
                $stmt_update_login->bind_param("si", $hashed_new_password, $_SESSION['user_id']);
                if ($stmt_update_login->execute()) {
                    $message = "<p class='message success'>Password updated successfully!</p>";
                } else {
                    $message = "<p class='message error'>Error updating password: " . $stmt_update_login->error . "</p>";
                }
                $stmt_update_login->close();
            } else {
                $message = "<p class='message error'>New passwords do not match.</p>";
            }
        } else {
            $message = "<p class='message error'>Incorrect current password.</p>";
        }
    } else {
        $message = "<p class='message error'>Could not find login record for password update.</p>";
    }
    $stmt_login_check->close();
}

// Fetch departments for dropdown
$departments = [];
$result_dept = $conn->query("SELECT dept_id, dept_name FROM tbl_department");
while ($row = $result_dept->fetch_assoc()) {
    $departments[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Profile</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>My Profile</h1>
            <?php echo $message; ?>

            <h2>Update Profile Details</h2>
            <form action="staff_my_profile.php" method="POST">
                <div class="form-group">
                    <label for="staff_name">Name:</label>
                    <input type="text" id="staff_name" name="staff_name" value="<?php echo htmlspecialchars($staff_details['staff_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="staff_phone">Phone Number:</label>
                    <input type="text" id="staff_phone" name="staff_phone" value="<?php echo htmlspecialchars($staff_details['staff_phone'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="dept_id">Department:</label>
                    <select id="dept_id" name="dept_id" required>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['dept_id']; ?>" <?php echo (isset($staff_details['dept_id']) && $staff_details['dept_id'] == $dept['dept_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['dept_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="update_profile" class="btn-primary">Update Profile</button>
            </form>

            <hr style="margin: 30px 0;">

            <h2>Change Password</h2>
            <form action="staff_my_profile.php" method="POST">
                <div class="form-group">
                    <label for="current_password">Current Password:</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password:</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_new_password">Confirm New Password:</label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" required>
                </div>
                <button type="submit" name="update_password" class="btn-primary">Change Password</button>
            </form>
        </div>
    </div>
</body>
</html>