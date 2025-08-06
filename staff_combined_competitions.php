<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'staff') {
    header("Location: index.php");
    exit();
}

$staff_id = $_SESSION['user_id'];
$successMessage = '';
$errorMessage = '';

// --- Handle Adding New Competition ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_competition'])) {
    $competition_title = $conn->real_escape_string($_POST['competition_title']);
    $description = $conn->real_escape_string($_POST['description']);
    $competition_date = $conn->real_escape_string($_POST['competition_date']);
    $rules = $conn->real_escape_string($_POST['rules']);
    $allowed_types = isset($_POST['allowed_types']) ? implode(',', $_POST['allowed_types']) : '';
    
    // Basic validation
    if (empty($competition_title) || empty($competition_date)) {
        $errorMessage = "Competition Title and Date are required.";
    } else {
        $category_name = 'competition'; 
        $stmt = $conn->prepare("INSERT INTO tbl_content_category (category_name, staff_id, description, allowed_types, competition_date, rules) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sissss", $category_name, $staff_id, $description, $allowed_types, $competition_date, $rules);

        if ($stmt->execute()) {
            $successMessage = "New competition added successfully!";
            unset($_POST); 
        } else {
            $errorMessage = "Error adding competition: " . $stmt->error;
        }
        $stmt->close();
    }
}

// --- Handle Content Approval/Rejection ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $submission_id = $conn->real_escape_string($_POST['submission_id']);
    $action = $conn->real_escape_string($_POST['action']);
    
    $status_to_update = ($action === 'approve') ? 'approved' : 'rejected';
    
    $stmt = $conn->prepare("UPDATE tbl_content_submission SET status = ? WHERE submission_id = ?");
    $stmt->bind_param("si", $status_to_update, $submission_id);

    if ($stmt->execute()) {
        $successMessage = "Submission ID " . $submission_id . " has been " . $status_to_update . " successfully.";
    } else {
        $errorMessage = "Error updating submission status: " . $stmt->error;
    }
    $stmt->close();
}

// --- Fetch Scheduled Competitions ---
$competitions = [];
$stmt_competitions = $conn->prepare("SELECT category_id, description, allowed_types, competition_date, rules FROM tbl_content_category WHERE category_name = 'competition' ORDER BY competition_date DESC");
$stmt_competitions->execute();
$result_competitions = $stmt_competitions->get_result();
while ($row = $result_competitions->fetch_assoc()) {
    $competitions[] = $row;
}
$stmt_competitions->close();

// --- Fetch Unapproved Competition Submissions ---
$competition_submissions = [];
$stmt_submissions = $conn->prepare("
   SELECT
    tc.content_id,
    tc.title,
    tc.contentbody AS content_data, -- Using contentbody for content data
    tc.submitted_date AS submission_date, -- Renaming submitted_date to submission_date for consistency
    tca.status,
    ts.student_name,
    tcc.description AS competition_name,
    tct.type_name AS content_type
FROM
    tbl_content tc
JOIN
    tbl_student ts ON tc.student_id = ts.student_id
JOIN
    tbl_content_category tcc ON tc.category_id = tcc.category_id
JOIN
    tbl_content_type tct ON tc.type_id = tct.type_id
JOIN
    tbl_content_approval tca ON tc.content_id = tca.content_id
WHERE
    tcc.category_name = 'competition' AND tca.status = 'pending'
ORDER BY
    tc.submitted_date DESC;
");
$stmt_submissions->execute();
$result_submissions = $stmt_submissions->get_result();
while ($row = $result_submissions->fetch_assoc()) {
    $competition_submissions[] = $row;
}
$stmt_submissions->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Competitions & Content - Staff Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Manage Competitions & Content</h1>
            <p>Use this page to add new competitions and approve student submissions.</p>

            <?php if ($successMessage): ?>
                <div class="success-message"><?php echo $successMessage; ?></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="error-message"><?php echo $errorMessage; ?></div>
            <?php endif; ?>

            <!-- Add New Competition Form -->
            <div class="card">
                <h2>Add New Competition</h2>
                <form action="staff_combined_competitions.php" method="POST">
                    <div class="form-group">
                        <label for="competition_title">Competition Title:</label>
                        <input type="text" id="competition_title" name="competition_title" required value="<?php echo htmlspecialchars($_POST['competition_title'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="competition_date">Competition Date:</label>
                        <input type="date" id="competition_date" name="competition_date" required value="<?php echo htmlspecialchars($_POST['competition_date'] ?? date('Y-m-d')); ?>">
                    </div>
                    <div class="form-group">
                        <label for="rules">Rules:</label>
                        <textarea id="rules" name="rules" rows="4"><?php echo htmlspecialchars($_POST['rules'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Allowed Content Types:</label>
                        <div class="checkbox-group">
                            <label><input type="checkbox" name="allowed_types[]" value="text" <?php if(isset($_POST['allowed_types']) && in_array('text', $_POST['allowed_types'])) echo 'checked'; ?>> Text</label>
                            <label><input type="checkbox" name="allowed_types[]" value="image" <?php if(isset($_POST['allowed_types']) && in_array('image', $_POST['allowed_types'])) echo 'checked'; ?>> Image</label>
                            <label><input type="checkbox" name="allowed_types[]" value="video" <?php if(isset($_POST['allowed_types']) && in_array('video', $_POST['allowed_types'])) echo 'checked'; ?>> Video</label>
                        </div>
                    </div>
                    <button type="submit" name="add_competition" class="btn btn-primary">Add Competition</button>
                </form>
            </div>

            <!-- Scheduled Competitions Table -->
            <div class="card mt-30">
                <h2>Scheduled Competitions</h2>
                <?php if (empty($competitions)): ?>
                    <p>No competitions scheduled yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Description</th>
                                    <th>Date</th>
                                    <th>Allowed Types</th>
                                    <th>Rules</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($competitions as $comp): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($comp['category_id']); ?></td>
                                    <td><?php echo htmlspecialchars($comp['description']); ?></td>
                                    <td><?php echo htmlspecialchars($comp['competition_date']); ?></td>
                                    <td><?php echo htmlspecialchars($comp['allowed_types']); ?></td>
                                    <td><?php echo htmlspecialchars($comp['rules'] ?? 'N/A'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pending Submissions Table -->
            <div class="card mt-30">
                <h2>Pending Competition Submissions</h2>
                <?php if (empty($competition_submissions)): ?>
                    <p>No competition content submissions are pending approval.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Student</th>
                                    <th>Competition</th>
                                    <th>Content Type</th>
                                    <th>Submission Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($competition_submissions as $submission): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($submission['submission_id']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($submission['title']); ?></strong>
                                        <p class="small-text">Content: <?php echo htmlspecialchars(substr($submission['content_data'], 0, 50)); ?>...</p>
                                    </td>
                                    <td><?php echo htmlspecialchars($submission['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($submission['competition_name']); ?></td>
                                    <td><?php echo htmlspecialchars($submission['content_type']); ?></td>
                                    <td><?php echo htmlspecialchars($submission['submission_date']); ?></td>
                                    <td class="action-buttons">
                                        <form action="staff_combined_competitions.php" method="POST" style="display:inline-block;">
                                            <input type="hidden" name="submission_id" value="<?php echo $submission['submission_id']; ?>">
                                            <button type="submit" name="action" value="approve" class="btn-action approve-btn" title="Approve"><i class="fas fa-check"></i></button>
                                        </form>
                                        <form action="staff_combined_competitions.php" method="POST" style="display:inline-block;">
                                            <input type="hidden" name="submission_id" value="<?php echo $submission['submission_id']; ?>">
                                            <button type="submit" name="action" value="reject" class="btn-action reject-btn" title="Reject" onclick="return confirm('Are you sure you want to reject this submission?');"><i class="fas fa-times"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>