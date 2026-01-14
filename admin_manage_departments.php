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
$edit_mode = false;
$department_to_edit = ['dept_id' => '', 'dept_name' => ''];

// --- Handle Add Department ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_department'])) {
    $dept_name = $conn->real_escape_string($_POST['dept_name']);
    if (!empty($dept_name)) {
        // Check for existing department, including inactive ones to prevent duplicates
        $check_stmt = $conn->prepare("SELECT dept_id FROM tbl_department WHERE dept_name = ?");
        $check_stmt->bind_param("s", $dept_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows > 0) {
            $errorMessage = "This department already exists.";
        } else {
            $stmt = $conn->prepare("INSERT INTO tbl_department (dept_name, status) VALUES (?, 'active')");
            $stmt->bind_param("s", $dept_name);
            if ($stmt->execute()) {
                $successMessage = "Department added successfully!";
            } else {
                $errorMessage = "Error adding department: " . $stmt->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    } else {
        $errorMessage = "Department name cannot be empty.";
    }
}

// --- Handle Update Department ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_department'])) {
    $dept_id = (int)$_POST['dept_id'];
    $dept_name = $conn->real_escape_string($_POST['dept_name']);
    if (!empty($dept_name) && $dept_id > 0) {
        $stmt = $conn->prepare("UPDATE tbl_department SET dept_name = ? WHERE dept_id = ?");
        $stmt->bind_param("si", $dept_name, $dept_id);
        if ($stmt->execute()) {
            $successMessage = "Department updated successfully!";
        } else {
            $errorMessage = "Error updating department: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $errorMessage = "Department Name and ID are required.";
    }
}

// --- Handle Deactivation (Soft Delete) with cascading effect ---
if (isset($_GET['action']) && $_GET['action'] == 'deactivate' && isset($_GET['dept_id'])) {
    $dept_id = (int)$_GET['dept_id'];
    
    // Start a transaction to ensure all updates are successful
    $conn->begin_transaction();
    try {
        // 1. Deactivate the department itself
        $stmt1 = $conn->prepare("UPDATE tbl_department SET status = 'inactive' WHERE dept_id = ?");
        $stmt1->bind_param("i", $dept_id);
        $stmt1->execute();

        // 2. Deactivate all programs in this department
        $stmt2 = $conn->prepare("UPDATE tbl_program SET status = 'inactive' WHERE dept_id = ?");
        $stmt2->bind_param("i", $dept_id);
        $stmt2->execute();

        // 3. Deactivate all students in this department
        $stmt3 = $conn->prepare("UPDATE tbl_student SET status = 'inactive' WHERE dept_id = ?");
        $stmt3->bind_param("i", $dept_id);
        $stmt3->execute();

        $conn->commit();
        $successMessage = "Department and all associated programs and students deactivated successfully.";

    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $errorMessage = "Error deactivating department: " . $exception->getMessage();
    }
}


// --- Handle Edit mode ---
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['dept_id'])) {
    $dept_id = (int)$_GET['dept_id'];
    $stmt = $conn->prepare("SELECT dept_id, dept_name FROM tbl_department WHERE dept_id = ? AND status = 'active'");
    $stmt->bind_param("i", $dept_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $department_to_edit = $result->fetch_assoc();
        $edit_mode = true;
    } else {
        $errorMessage = "Department not found or is inactive.";
    }
    $stmt->close();
}

// Fetch only ACTIVE departments for display
$departments_result = $conn->query("SELECT * FROM tbl_department WHERE status = 'active' ORDER BY dept_name");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments - Admin Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Manage Departments</h1>
            <p>Add, edit, or deactivate departments.</p>
            
            <?php if ($successMessage): ?><div class="success-message"><?php echo $successMessage; ?></div><?php endif; ?>
            <?php if ($errorMessage): ?><div class="error-message"><?php echo $errorMessage; ?></div><?php endif; ?>
            
            <div class="card">
                <h2><?php echo $edit_mode ? 'Edit Department' : 'Add New Department'; ?></h2>
                <form action="admin_manage_departments.php" method="POST">
                    <input type="hidden" name="dept_id" value="<?php echo htmlspecialchars($department_to_edit['dept_id']); ?>">
                    <div class="form-group">
                        <label for="dept_name">Department Name:</label>
                        <input type="text" id="dept_name" name="dept_name" value="<?php echo htmlspecialchars($department_to_edit['dept_name']); ?>" required>
                    </div>
                    <?php if ($edit_mode): ?>
                    <button type="submit" name="update_department" class="btn btn-primary">Update Department</button>
                    <a href="admin_manage_departments.php" class="btn btn-secondary">Cancel Edit</a>
                    <?php else: ?>
                    <button type="submit" name="add_department" class="btn btn-primary">Add Department</button>
                    <?php endif; ?>
                </form>
            </div>

            <div class="card mt-30">
                <h2>Current Departments</h2>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($departments_result->num_rows > 0): ?>
                                <?php while($dept = $departments_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $dept['dept_id']; ?></td>
                                    <td><?php echo htmlspecialchars($dept['dept_name']); ?></td>
                                    <td class="action-buttons">
                                        <a href="admin_manage_departments.php?action=edit&dept_id=<?php echo $dept['dept_id']; ?>" class="btn-action approve-btn" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="admin_manage_departments.php?action=deactivate&dept_id=<?php echo $dept['dept_id']; ?>" class="btn-action reject-btn" onclick="return confirm('Are you sure you want to deactivate this department and all associated programs and students?');" title="Deactivate"><i class="fas fa-user-slash"></i></a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3">No active departments found. Add one above to get started.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>