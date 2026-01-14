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
$program_to_edit = ['program_id' => '', 'program_name' => '', 'dept_id' => ''];

// --- Handle Add Program ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_program'])) {
    $program_name = $conn->real_escape_string($_POST['program_name']);
    $dept_id = (int)$_POST['dept_id'];

    if (!empty($program_name) && $dept_id > 0) {
        $stmt = $conn->prepare("INSERT INTO tbl_program (program_name, dept_id, status) VALUES (?, ?, 'active')");
        $stmt->bind_param("si", $program_name, $dept_id);
        if ($stmt->execute()) {
            $successMessage = "Program added successfully!";
        } else {
            $errorMessage = "Error adding program: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $errorMessage = "Program Name and Department are required.";
    }
}

// --- Handle Update Program ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_program'])) {
    $program_id = (int)$_POST['program_id'];
    $program_name = $conn->real_escape_string($_POST['program_name']);
    $dept_id = (int)$_POST['dept_id'];

    if (!empty($program_name) && $dept_id > 0 && $program_id > 0) {
        $stmt = $conn->prepare("UPDATE tbl_program SET program_name = ?, dept_id = ? WHERE program_id = ?");
        $stmt->bind_param("sii", $program_name, $dept_id, $program_id);
        if ($stmt->execute()) {
            $successMessage = "Program updated successfully!";
        } else {
            $errorMessage = "Error updating program: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $errorMessage = "Program Name, Department, and ID are required.";
    }
}

// --- Handle Deactivation (Soft Delete) with cascading effect ---
if (isset($_GET['action']) && $_GET['action'] == 'deactivate' && isset($_GET['program_id'])) {
    $program_id = (int)$_GET['program_id'];

    // Start a transaction to ensure both updates are successful
    $conn->begin_transaction();
    try {
        // 1. Deactivate the program itself
        $stmt1 = $conn->prepare("UPDATE tbl_program SET status = 'inactive' WHERE program_id = ?");
        $stmt1->bind_param("i", $program_id);
        $stmt1->execute();

        // 2. Deactivate all students in this program
        $stmt2 = $conn->prepare("UPDATE tbl_student SET status = 'inactive' WHERE program_id = ?");
        $stmt2->bind_param("i", $program_id);
        $stmt2->execute();

        $conn->commit();
        $successMessage = "Program and all associated students deactivated successfully.";
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $errorMessage = "Error deactivating program: " . $exception->getMessage();
    }
}

// --- Handle Edit mode ---
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['program_id'])) {
    $program_id = (int)$_GET['program_id'];
    $stmt = $conn->prepare("SELECT program_id, program_name, dept_id FROM tbl_program WHERE program_id = ? AND status = 'active'");
    $stmt->bind_param("i", $program_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $program_to_edit = $result->fetch_assoc();
        $edit_mode = true;
    } else {
        $errorMessage = "Program not found or is inactive.";
    }
    $stmt->close();
}

// Fetch active departments for the dropdown
$departments_result = $conn->query("SELECT dept_id, dept_name FROM tbl_department WHERE status = 'active' ORDER BY dept_name");

// Fetch programs with active departments
$programs_result = $conn->query("SELECT p.*, d.dept_name FROM tbl_program p JOIN tbl_department d ON p.dept_id = d.dept_id WHERE p.status = 'active' AND d.status = 'active' ORDER BY p.program_name");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Programs - Admin Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Manage Programs</h1>
            <p>Add, edit, or deactivate academic programs.</p>

            <?php if ($successMessage): ?><div class="success-message"><?php echo $successMessage; ?></div><?php endif; ?>
            <?php if ($errorMessage): ?><div class="error-message"><?php echo $errorMessage; ?></div><?php endif; ?>

            <div class="card">
                <h2><?php echo $edit_mode ? 'Edit Program' : 'Add New Program'; ?></h2>
                <form action="admin_manage_programs.php" method="POST">
                    <input type="hidden" name="program_id" value="<?php echo htmlspecialchars($program_to_edit['program_id']); ?>">
                    <div class="form-group">
                        <label for="program_name">Program Name:</label>
                        <input type="text" id="program_name" name="program_name" value="<?php echo htmlspecialchars($program_to_edit['program_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="dept_id">Department:</label>
                        <select id="dept_id" name="dept_id" required>
                            <option value="">-- Select Department --</option>
                            <?php while($dept = $departments_result->fetch_assoc()): ?>
                                <option value="<?php echo $dept['dept_id']; ?>" <?php echo ($edit_mode && $program_to_edit['dept_id'] == $dept['dept_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['dept_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php if ($edit_mode): ?>
                    <button type="submit" name="update_program" class="btn btn-primary">Update Program</button>
                    <a href="admin_manage_programs.php" class="btn btn-secondary">Cancel Edit</a>
                    <?php else: ?>
                    <button type="submit" name="add_program" class="btn btn-primary">Add Program</button>
                    <?php endif; ?>
                </form>
            </div>

            <div class="card mt-30">
                <h2>Current Programs</h2>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Program Name</th>
                                <th>Department</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($programs_result->num_rows > 0): ?>
                                <?php while($prog = $programs_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $prog['program_id']; ?></td>
                                    <td><?php echo htmlspecialchars($prog['program_name']); ?></td>
                                    <td><?php echo htmlspecialchars($prog['dept_name']); ?></td>
                                    <td class="action-buttons">
                                        <a href="admin_manage_programs.php?action=edit&program_id=<?php echo $prog['program_id']; ?>" class="btn-action approve-btn" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="admin_manage_programs.php?action=deactivate&program_id=<?php echo $prog['program_id']; ?>" class="btn-action reject-btn" onclick="return confirm('Are you sure you want to deactivate this program and all associated students?');" title="Deactivate"><i class="fas fa-user-slash"></i></a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4">No active programs found. Add one above to get started.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>