<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$successMessage = '';
$errorMessage = '';

// --- Handle Add New Staff ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_staff'])) {
    $staff_name = $conn->real_escape_string($_POST['staff_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $staff_phone = $conn->real_escape_string($_POST['staff_phone']);
    $password = $_POST['password']; // Will be hashed
    $dept_id = (int)$_POST['dept_id'];
    $role = 'staff'; // Default role for new staff

    // Basic validation
    if (empty($staff_name) || empty($email) || empty($password) || empty($dept_id)) {
        $errorMessage = "All fields except phone are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Invalid email format.";
    } else {
        // Hash the password for security
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Use a transaction to ensure data consistency across tbl_staff and tbl_login
        $conn->begin_transaction();

        try {
            // 1. Insert into tbl_staff with an 'active' status
            $stmt1 = $conn->prepare("INSERT INTO tbl_staff (staff_name, email, staff_phone, password, dept_id, role, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $stmt1->bind_param("ssssis", $staff_name, $email, $staff_phone, $password, $dept_id, $role);
            $stmt1->execute();
            
            // 2. Insert into tbl_login
            $stmt2 = $conn->prepare("INSERT INTO tbl_login (email, password, usertype) VALUES (?, ?, ?)");
            $stmt2->bind_param("sss", $email, $hashed_password, $role);
            $stmt2->execute();
            
            // If both queries succeed, commit the transaction
            $conn->commit();
            $successMessage = "New staff member added successfully!";

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            // Check for duplicate email error
            if ($conn->errno == 1062) {
                $errorMessage = "Error: This email address is already registered.";
            } else {
                $errorMessage = "An error occurred: " . $exception->getMessage();
            }
        }
    }
}


// --- Handle User Deactivation (Soft Delete) ---
if (isset($_GET['action']) && $_GET['action'] == 'deactivate') {
    $conn->begin_transaction();
    try {
        // Deactivate a staff member
        if (isset($_GET['staff_id'])) {
            $staff_id = (int)$_GET['staff_id'];
            $stmt_staff = $conn->prepare("UPDATE tbl_staff SET status = 'inactive' WHERE staff_id = ?");
            $stmt_staff->bind_param("i", $staff_id);
            $stmt_staff->execute();
            $successMessage = "Staff member deactivated successfully.";
        }
        
        // Deactivate a student
        if (isset($_GET['student_id'])) {
            $student_id = (int)$_GET['student_id'];
            $stmt = $conn->prepare("UPDATE tbl_student SET status = 'inactive' WHERE student_id = ?");
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $successMessage = "Student deactivated successfully.";
        }
        $conn->commit();
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $errorMessage = "Error deactivating user: " . $exception->getMessage();
    }
}


// --- Fetch Data for Display ---
// Fetch departments for the dropdown
$departments = $conn->query("SELECT dept_id, dept_name FROM tbl_department WHERE status = 'active' ORDER BY dept_name");

// Fetch only ACTIVE staff users
$staff_list = $conn->query("SELECT s.*, d.dept_name FROM tbl_staff s JOIN tbl_department d ON s.dept_id = d.dept_id WHERE s.status = 'active' ORDER BY s.staff_name");

// Fetch only ACTIVE student users
$student_list = $conn->query("SELECT s.*, d.dept_name, p.program_name FROM tbl_student s JOIN tbl_department d ON s.dept_id = d.dept_id JOIN tbl_program p ON s.program_id = p.program_id WHERE s.status = 'active' AND d.status = 'active' AND p.status = 'active' ORDER BY s.student_name");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Admin manage users theme (off-white + coral + teal) */
        .card { background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(245, 245, 245, 0.94)); border: 1px solid rgba(255, 107, 107, 0.18); border-radius: 14px; padding: 25px; box-shadow: 0 12px 28px rgba(0,0,0,0.1); color: var(--color-text, #333333); margin-bottom: 30px; }
        .mt-30 { margin-top: 30px; }
        .card h2 { color: var(--color-primary-2, #FF8E8E); font-family: 'Poppins','Inter',sans-serif; font-size: 1.4em; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .card h2 i { color: var(--color-primary, #FF6B6B); }
        
        /* Form styling */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .form-group label { color: var(--color-primary-2, #FF8E8E); font-weight: 600; display: block; margin-bottom: 8px; }
        .form-group input, .form-group select { width: 100%; padding: 12px; background: var(--color-bg, #FEFDFB); color: var(--color-text, #333333); border: 1px solid #E0E0E0; border-radius: 10px; transition: all 0.2s ease; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--color-primary, #FF6B6B); box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.2); }
        .form-group input:focus, .form-group select:focus { background: var(--color-bg, #FEFDFB); color: var(--color-text, #333333); -webkit-text-fill-color: var(--color-text, #333333); }
        .form-group input::placeholder { color: var(--color-muted, #666666); }
        
        /* Button styling */
        .btn-primary { background: linear-gradient(45deg, var(--color-primary-2, #FF8E8E), var(--color-primary, #FF6B6B)); color: #FEFDFB; padding: 12px 25px; border: none; border-radius: 999px; font-weight: 700; cursor: pointer; transition: transform 0.2s ease, box-shadow 0.2s ease; box-shadow: 0 8px 20px rgba(255, 107, 107, 0.25); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(255, 107, 107, 0.35); }
        
        /* Table styling */
        .table-responsive { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; background: linear-gradient(180deg, var(--color-surface, #F5F5F5), var(--color-bg, #FEFDFB)); border: 1px solid #E0E0E0; border-radius: 14px; overflow: hidden; box-shadow: 0 0 0 1px rgba(255, 107, 107, 0.18), 0 18px 40px rgba(0,0,0,0.1), 0 0 32px rgba(255, 107, 107, 0.08); }
        .data-table thead th { background: linear-gradient(45deg, var(--color-primary-2, #FF8E8E), var(--color-primary, #FF6B6B)); color: #FEFDFB; text-shadow: 0 1px 0 rgba(0,0,0,0.1); padding: 16px 18px; border: 1px solid #E0E0E0; font-weight: 800; letter-spacing: .4px; }
        .data-table tbody td { color: var(--color-text, #333333); padding: 16px 18px; border: 1px solid #E0E0E0; }
        .data-table tbody tr:nth-child(even) { background: var(--color-surface-2, #EEEEEE); }
        .data-table tbody tr:nth-child(odd) { background: var(--color-surface, #F5F5F5); }
        .data-table tbody tr:hover { background: rgba(255, 107, 107, 0.10); }
        
        /* Action buttons */
        .action-buttons { text-align: center; }
        .btn-action { padding: 8px 12px; border-radius: 8px; text-decoration: none; transition: all 0.2s ease; display: inline-block; }
        .reject-btn { background: rgba(255, 107, 107, 0.08); color: var(--color-primary, #FF6B6B); border: 1px solid rgba(255, 107, 107, 0.18); }
        .reject-btn:hover { background: rgba(255, 107, 107, 0.14); transform: translateY(-1px); }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Manage Users</h1>
            <p>Add new staff and manage existing staff and student accounts.</p>
            
            <?php if ($successMessage): ?><div class="success-message"><?php echo $successMessage; ?></div><?php endif; ?>
            <?php if ($errorMessage): ?><div class="error-message"><?php echo $errorMessage; ?></div><?php endif; ?>
            
            <div class="card">
                <h2><i class="fas fa-user-plus"></i> Add New Staff</h2>
                <form action="admin_manage_users.php" method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="staff_name">Full Name:</label>
                            <input type="text" id="staff_name" name="staff_name" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address:</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="staff_phone">Phone Number:</label>
                            <input type="tel" id="staff_phone" name="staff_phone">
                        </div>
                        <div class="form-group">
                            <label for="password">Password:</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label for="dept_id">Department:</label>
                            <select id="dept_id" name="dept_id" required>
                                <option value="">-- Select Department --</option>
                                <?php while($dept = $departments->fetch_assoc()): ?>
                                <option value="<?php echo $dept['dept_id']; ?>"><?php echo htmlspecialchars($dept['dept_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <!-- Role selection removed; role defaults to 'staff' -->
                    </div>
                    <button type="submit" name="add_staff" class="btn btn-primary">Add Staff Member</button>
                </form>
            </div>

            <div class="card mt-30">
                <h2><i class="fas fa-users-cog"></i> Staff Members</h2>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($staff_list->num_rows > 0): ?>
                            <?php while($staff = $staff_list->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $staff['staff_id']; ?></td>
                                <td><?php echo htmlspecialchars($staff['staff_name']); ?></td>
                                <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                <td><?php echo htmlspecialchars($staff['dept_name']); ?></td>
                                <td><?php echo ucfirst(htmlspecialchars($staff['role'])); ?></td>
                                <td class="action-buttons">
                                    <a href="admin_manage_users.php?action=deactivate&staff_id=<?php echo $staff['staff_id']; ?>" class="btn-action reject-btn" onclick="return confirm('Are you sure you want to deactivate this staff member?');" title="Deactivate"><i class="fas fa-user-slash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr><td colspan="6">No staff members found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mt-30">
                <h2><i class="fas fa-user-graduate"></i> Registered Students</h2>
                 <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th>Program</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($student_list->num_rows > 0): ?>
                            <?php while($student = $student_list->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $student['student_id']; ?></td>
                                <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo htmlspecialchars($student['dept_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['program_name']); ?></td>
                                <td class="action-buttons">
                                    <a href="admin_manage_users.php?action=deactivate&student_id=<?php echo $student['student_id']; ?>" class="btn-action reject-btn" onclick="return confirm('Are you sure you want to deactivate this student?');" title="Deactivate"><i class="fas fa-user-slash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr><td colspan="6">No students found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>