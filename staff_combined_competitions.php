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

// Determine which view to show: 'manage' or 'approval'
$current_view = isset($_GET['view']) ? $_GET['view'] : 'manage'; // Default to 'manage'

// --- Handle Adding New Competition (Only if in 'manage' view and form submitted) ---
if ($current_view === 'manage' && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_competition'])) {
    $competition_title = $conn->real_escape_string($_POST['competition_title']);
    $description = $conn->real_escape_string($_POST['description']);
    // CHANGED: Get specific submission start and end datetimes
    $submission_start_datetime = $conn->real_escape_string($_POST['submission_start_datetime']);
    $submission_end_datetime = $conn->real_escape_string($_POST['submission_end_datetime']);
    $rules = $conn->real_escape_string($_POST['rules']);
    $allowed_types = isset($_POST['allowed_types']) ? implode(',', $_POST['allowed_types']) : '';
    
    // Basic validation
    if (empty($competition_title) || empty($submission_start_datetime) || empty($submission_end_datetime)) {
        $errorMessage = "Competition Title, Submission Start Date & Time, and Submission End Date & Time are required.";
    } elseif (strtotime($submission_start_datetime) >= strtotime($submission_end_datetime)) {
        $errorMessage = "Submission End Date & Time must be after Submission Start Date & Time.";
    } else {
        $category_name = 'competition'; 

        // UPDATED: INSERT statement to include submission_start_datetime and submission_end_datetime
        $stmt = $conn->prepare("INSERT INTO tbl_content_category (category_name, staff_id, description, allowed_types, submission_start_datetime, submission_end_datetime, rules) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sisssss", $category_name, $staff_id, $description, $allowed_types, $submission_start_datetime, $submission_end_datetime, $rules);

        if ($stmt->execute()) {
            $successMessage = "New competition added successfully!";
            unset($_POST); 
        } else {
            $errorMessage = "Error adding competition: " . $stmt->error;
        }
        $stmt->close();
    }
}

// --- Handle Content Approval/Rejection (Only if in 'approval' view and form submitted) ---
if ($current_view === 'approval' && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $submission_id = $conn->real_escape_string($_POST['submission_id']);
    $action = $conn->real_escape_string($_POST['action']);
    
    $status_to_update = ($action === 'approve') ? 'approved' : 'rejected';
    
    // Update tbl_content_approval
    $stmt = $conn->prepare("UPDATE tbl_content_approval SET status = ?, staff_id = ? WHERE content_id = ?");
    $stmt->bind_param("sii", $status_to_update, $staff_id, $submission_id); 

    if ($stmt->execute()) {
        $successMessage = "Submission ID " . $submission_id . " has been " . $status_to_update . " successfully.";
    } else {
        $errorMessage = "Error updating submission status: " . $stmt->error;
    }
    $stmt->close();
}

// --- Fetch Data based on current_view ---
$competitions = [];
$competition_submissions = [];

if ($current_view === 'manage') {
    // Fetch Scheduled Competitions
    // UPDATED: Select submission_start_datetime and submission_end_datetime
    $stmt_competitions = $conn->prepare("SELECT category_id, description, allowed_types, submission_start_datetime, submission_end_datetime, rules FROM tbl_content_category WHERE category_name = 'competition' ORDER BY submission_start_datetime DESC");
    $stmt_competitions->execute();
    $result_competitions = $stmt_competitions->get_result();
    while ($row = $result_competitions->fetch_assoc()) {
        $competitions[] = $row;
    }
    $stmt_competitions->close();
} elseif ($current_view === 'approval') {
    // Fetch Unapproved Competition Submissions
    $stmt_submissions = $conn->prepare("
        SELECT
            tca.content_id AS submission_id, /* Renamed for clarity in this context */
            tc.title,
            tc.contentbody,
            tc.file_path,
            tc.submitted_date,
            tca.status,
            ts.student_name,
            tcat.description AS competition_name, /* Alias for category description */
            tct.type_name AS content_type
        FROM tbl_content_approval tca
        JOIN tbl_content tc ON tca.content_id = tc.content_id
        JOIN tbl_student ts ON tc.student_id = ts.student_id
        JOIN tbl_content_category tcat ON tc.category_id = tcat.category_id 
        JOIN tbl_content_type tct ON tc.type_id = tct.type_id
        WHERE tcat.category_name = 'competition' AND tca.status = 'pending'
        ORDER BY tc.submitted_date DESC
    ");
    $stmt_submissions->execute();
    $result_submissions = $stmt_submissions->get_result();
    while ($row = $result_submissions->fetch_assoc()) {
        $competition_submissions[] = $row;
    }
    $stmt_submissions->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Competitions & Content</title>
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
            <h1><?php echo ($current_view === 'manage') ? 'Manage Competitions' : 'Competition Content (Approval)'; ?></h1>
            <p>
                <?php echo ($current_view === 'manage') ? 
                    'Use this section to add new competitions and view existing ones.' : 
                    'Review and approve student submissions for competitions.'; 
                ?>
            </p>

            <?php if ($successMessage): ?>
                <div class="success-message"><?php echo $successMessage; ?></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="error-message"><?php echo $errorMessage; ?></div>
            <?php endif; ?>

            <?php if ($current_view === 'manage'): ?>
                <!-- Add New Competition Form -->
                <div class="card">
                    <h2>Add New Competition</h2>
                    <form action="staff_combined_competitions.php?view=manage" method="POST">
                        <div class="form-group">
                            <label for="competition_title">Competition Title:</label>
                            <input type="text" id="competition_title" name="competition_title" required value="<?php echo htmlspecialchars($_POST['competition_title'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="description">Description:</label>
                            <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="submission_start_datetime">Submission Start Date & Time:</label>
                            <input type="datetime-local" id="submission_start_datetime" name="submission_start_datetime" required value="<?php echo htmlspecialchars($_POST['submission_start_datetime'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="submission_end_datetime">Submission End Date & Time:</label>
                            <input type="datetime-local" id="submission_end_datetime" name="submission_end_datetime" required value="<?php echo htmlspecialchars($_POST['submission_end_datetime'] ?? ''); ?>">
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
                                        <th>Submission Start</th>
                                        <th>Submission End</th>
                                        <th>Allowed Types</th>
                                        <th>Rules</th>
                                        <th>Actions</th> <!-- NEW: Actions column -->
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($competitions as $comp): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($comp['category_id']); ?></td>
                                        <td><?php echo htmlspecialchars($comp['description']); ?></td>
                                        <td><?php echo htmlspecialchars($comp['submission_start_datetime']); ?></td>
                                        <td><?php echo htmlspecialchars($comp['submission_end_datetime']); ?></td>
                                        <td><?php echo htmlspecialchars($comp['allowed_types']); ?></td>
                                        <td><?php echo htmlspecialchars($comp['rules'] ?? 'N/A'); ?></td>
                                        <td>
                                            <a href="edit_competition.php?id=<?php echo $comp['category_id']; ?>" class="btn-action edit-btn" title="Edit"><i class="fas fa-edit"></i></a>
                                            <a href="delete_competition.php?id=<?php echo $comp['category_id']; ?>" class="btn-action delete-btn" title="Delete" onclick="return confirm('Are you sure you want to delete this competition? This will also affect student submissions if not handled by database constraints.');"><i class="fas fa-trash-alt"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($current_view === 'approval'): ?>
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
                                        <th>Type</th>
                                        <th class="clickable-header">Content Preview</th> 
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
                                        </td>
                                        <td><?php echo htmlspecialchars($submission['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($submission['competition_name']); ?></td>
                                        <td><?php echo htmlspecialchars($submission['content_type']); ?></td>
                                        <td class="content-preview-cell" data-content-id="<?php echo $submission['submission_id']; ?>" data-content-type="<?php echo htmlspecialchars($submission['content_type']); ?>">
                                            <?php if ($submission['file_path']): ?>
                                                <?php 
                                                    $file_ext = strtolower(pathinfo($submission['file_path'], PATHINFO_EXTENSION));
                                                    if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])):
                                                ?>
                                                    <a href="<?php echo htmlspecialchars($submission['file_path']); ?>" target="_blank" title="View Full Image">
                                                        <img src="<?php echo htmlspecialchars($submission['file_path']); ?>" alt="Content Image" style="max-width: 100px; max-height: 100px; border-radius: 5px; object-fit: cover;">
                                                    </a>
                                                <?php elseif (in_array($file_ext, ['mp4', 'webm', 'ogg'])): ?>
                                                    <a href="<?php echo htmlspecialchars($submission['file_path']); ?>" target="_blank" title="View Full Video">
                                                        <i class="fas fa-video"></i> View Video
                                                    </a>
                                                <?php else: ?>
                                                    <a href="<?php echo htmlspecialchars($submission['file_path']); ?>" target="_blank" title="Download File">
                                                        <i class="fas fa-file"></i> View File
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: /* Text content */ ?>
                                                <div class="text-content-preview">
                                                    <?php echo nl2br(htmlspecialchars(substr($submission['contentbody'], 0, 200))); ?>...
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($submission['submitted_date']); ?></td>
                                        <td class="action-buttons">
                                            <form action="staff_combined_competitions.php?view=approval" method="POST" style="display:inline-block;">
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
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal for Full Text Content -->
    <div id="fullTextModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h3 id="modalTitle">Full Content View</h3>
            <div id="modalContentBody" class="modal-body">
                <!-- Loading spinner or message -->
                <div class="loading-spinner" style="display: none; text-align: center; padding: 20px;">
                    <i class="fas fa-spinner fa-spin fa-3x" style="color: #4a90e2;"></i>
                    <p>Loading content...</p>
                </div>
                <!-- Full text content will be loaded here -->
            </div>
        </div>
    </div>

    <style>
        /* CSS for Modals (shared styles) */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1002; /* Increased z-index */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.6); /* Black w/ opacity */
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            width: 80%; /* Could be responsive */
            max-width: 800px; /* Adjusted max-width for remarks modal */
            position: relative;
            max-height: 90vh; /* Limit height */
            overflow-y: auto; /* Enable scrolling within modal */
        }

        .close-button {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            top: 10px;
            right: 20px;
            cursor: pointer;
        }

        .close-button:hover,
        .close-button:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        .modal-body {
            margin-top: 20px;
            white-space: pre-wrap; /* Preserves whitespace and wraps text */
            word-wrap: break-word; /* Breaks long words */
        }
        .loading-spinner {
            display: none; /* Hidden by default */
        }
        
        /* Existing styles from previous versions */
        .text-content-preview {
            max-height: 100px; /* Limit height of preview */
            overflow: hidden; /* Hide overflow */
            position: relative;
        }

        .content-preview-cell {
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .content-preview-cell:hover {
            background-color: #f5f5f5; /* Light hover effect */
        }
        .data-table {
    table-layout: fixed; /* Prevents columns from expanding to fit long content */
    width: 100%;
}   
     .data-table td {
    word-wrap: break-word; /* Allows long words to be broken and wrapped */
}

        .clickable-header {
            cursor: default;
        }
        /* Styles for success/error messages, buttons, tables, etc. should be in style.css */
    </style>

    <script>
        // JavaScript for Modal functionality
        const modal = document.getElementById("fullTextModal");
        const closeButton = document.querySelector(".close-button");
        const modalTitle = document.getElementById("modalTitle");
        const modalContentBody = document.getElementById("modalContentBody");
        const loadingSpinner = document.querySelector(".loading-spinner"); 
        
        const contentPreviewCells = document.querySelectorAll(".content-preview-cell");

        contentPreviewCells.forEach(cell => {
            cell.addEventListener("click", function(event) {
                const contentType = this.getAttribute("data-content-type").toLowerCase(); 
                const contentId = this.getAttribute("data-content-id");

                if (contentType === 'text') {
                    event.preventDefault(); 
                    event.stopPropagation(); 

                    modalContentBody.innerHTML = ''; 
                    loadingSpinner.style.display = 'block'; 
                    modal.style.display = "flex"; 
                    modalTitle.textContent = "Full Content View";

                    fetch('get_full_content.php?id=' + contentId)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.text();
                        })
                        .then(data => {
                            loadingSpinner.style.display = 'none'; 
                            modalContentBody.innerHTML = data;
                        })
                        .catch(error => {
                            console.error('Error fetching full content:', error);
                            loadingSpinner.style.display = 'none'; 
                            modalContentBody.innerHTML = '<p style="color: red;">Could not load full content. Error: ' + error.message + '</p>';
                        });
                }
            });
        });

        closeButton.addEventListener("click", function() {
            modal.style.display = "none";
            modalContentBody.innerHTML = ''; 
            loadingSpinner.style.display = 'none'; 
        });

        window.addEventListener("click", function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
                modalContentBody.innerHTML = ''; 
                loadingSpinner.style.display = 'none'; 
            }
        });
    </script>
</body>
</html>
