<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'staff') {
    header("Location: index.php");
    exit();
}

$successMessage = '';
$errorMessage = '';
$competition_id = $_GET['id'] ?? null;
$competition_data = null;

// Validate competition ID
if ($competition_id === null) {
    $errorMessage = "No competition ID provided for editing.";
    // Redirect back to manage competitions after a short delay or display error
    header("Refresh: 3; url=staff_combined_competitions.php?view=manage");
} else {
    // Fetch existing competition data
    $stmt_fetch = $conn->prepare("SELECT category_id, description, allowed_types, competition_date, rules FROM tbl_content_category WHERE category_id = ? AND category_name = 'competition'");
    $stmt_fetch->bind_param("i", $competition_id);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();

    if ($result_fetch->num_rows === 1) {
        $competition_data = $result_fetch->fetch_assoc();
        // Convert allowed_types string to array for checkbox checking
        $competition_data['allowed_types_array'] = explode(',', $competition_data['allowed_types']);
    } else {
        $errorMessage = "Competition not found or is not a valid competition category.";
        header("Refresh: 3; url=staff_combined_competitions.php?view=manage");
    }
    $stmt_fetch->close();
}

// --- Handle Form Submission (Update Competition) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_competition']) && $competition_data !== null) {
    $new_description = $conn->real_escape_string($_POST['description']);
    $new_competition_date = $conn->real_escape_string($_POST['competition_date']);
    $new_rules = $conn->real_escape_string($_POST['rules']);
    $new_allowed_types = isset($_POST['allowed_types']) ? implode(',', $_POST['allowed_types']) : '';

    // Basic validation
    if (empty($new_description) || empty($new_competition_date)) {
        $errorMessage = "Description and Date are required.";
    } else {
        $stmt_update = $conn->prepare("UPDATE tbl_content_category SET description = ?, allowed_types = ?, competition_date = ?, rules = ? WHERE category_id = ?");
        $stmt_update->bind_param("ssssi", $new_description, $new_allowed_types, $new_competition_date, $new_rules, $competition_id);

        if ($stmt_update->execute()) {
            $successMessage = "Competition updated successfully!";
            // Redirect back to manage competitions after successful update
            header("Location: staff_combined_competitions.php?view=manage");
            exit();
        } else {
            $errorMessage = "Error updating competition: " . $stmt_update->error;
        }
        $stmt_update->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Competition - Staff Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Specific styles for this page if needed, or rely on style.css */
        .checkbox-group {
            display: flex;
            gap: 20px;
            margin-top: 5px;
        }
        .checkbox-group label {
            display: flex;
            align-items: center;
            font-weight: 400;
        }
        .checkbox-group input[type="checkbox"] {
            margin-right: 8px;
            width: auto; /* Override general input width */
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Edit Competition</h1>
            <p>Modify the details of the selected competition.</p>

            <?php if ($successMessage): ?>
                <div class="success-message"><?php echo $successMessage; ?></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="error-message"><?php echo $errorMessage; ?></div>
            <?php endif; ?>

            <?php if ($competition_data): ?>
                <div class="card">
                    <h2>Editing Competition ID: <?php echo htmlspecialchars($competition_data['category_id']); ?></h2>
                    <form action="edit_competition.php?id=<?php echo htmlspecialchars($competition_data['category_id']); ?>" method="POST">
                        <div class="form-group">
                            <label for="description">Description:</label>
                            <textarea id="description" name="description" rows="4" required><?php echo htmlspecialchars($competition_data['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="competition_date">Competition Date:</label>
                            <input type="date" id="competition_date" name="competition_date" required value="<?php echo htmlspecialchars($competition_data['competition_date'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="rules">Rules:</label>
                            <textarea id="rules" name="rules" rows="4"><?php echo htmlspecialchars($competition_data['rules'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Allowed Content Types:</label>
                            <div class="checkbox-group">
                                <label><input type="checkbox" name="allowed_types[]" value="text" <?php if(in_array('text', $competition_data['allowed_types_array'])) echo 'checked'; ?>> Text</label>
                                <label><input type="checkbox" name="allowed_types[]" value="image" <?php if(in_array('image', $competition_data['allowed_types_array'])) echo 'checked'; ?>> Image</label>
                                <label><input type="checkbox" name="allowed_types[]" value="video" <?php if(in_array('video', $competition_data['allowed_types_array'])) echo 'checked'; ?>> Video</label>
                            </div>
                        </div>
                        <button type="submit" name="update_competition" class="btn btn-primary">Update Competition</button>
                        <a href="staff_combined_competitions.php?view=manage" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>