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

// --- Handle Content Approval/Rejection ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['approval_action'])) {
    $content_id = $conn->real_escape_string($_POST['content_id']);
    $action = $conn->real_escape_string($_POST['approval_action']); // 'approve' or 'reject' from modal
    $remarks = $conn->real_escape_string($_POST['remarks']); // Remarks from modal
    
    $status_to_update = ($action === 'approve') ? 'approved' : 'rejected';
    
    // Update tbl_content_approval with status and remarks
    $stmt = $conn->prepare("UPDATE tbl_content_approval SET status = ?, staff_id = ?, remarks = ? WHERE content_id = ?");
    $stmt->bind_param("sisi", $status_to_update, $staff_id, $remarks, $content_id); 

    if ($stmt->execute()) {
        $successMessage = "Content ID " . $content_id . " has been " . $status_to_update . " successfully.";
        
        // If approved, redirect to the publish content page
        if ($action === 'approve') {
            header("Location: staff_publish_content.php?approved_content_id=" . $content_id);
            exit();
        }
        // If rejected, stay on this page and show message
    } else {
        $errorMessage = "Error updating content status: " . $stmt->error;
    }
    $stmt->close();
}

// --- Fetch Unapproved General Content Submissions ---
// This query now explicitly filters for 'general' category and 'pending' status
$general_submissions = [];
$stmt_submissions = $conn->prepare("
    SELECT
        tca.content_id,
        tc.title,
        tc.contentbody,
        tc.file_path,
        tc.submitted_date,
        tca.status,
        ts.student_name,
        tct.type_name AS content_type
    FROM tbl_content_approval tca
    JOIN tbl_content tc ON tca.content_id = tc.content_id
    JOIN tbl_student ts ON tc.student_id = ts.student_id
    JOIN tbl_content_category tcat ON tc.category_id = tcat.category_id 
    JOIN tbl_content_type tct ON tc.type_id = tct.type_id
    WHERE tcat.category_name = 'general' AND tca.status = 'pending'
    ORDER BY tc.submitted_date DESC
");
$stmt_submissions->execute();
$result_submissions = $stmt_submissions->get_result();
while ($row = $result_submissions->fetch_assoc()) {
    $general_submissions[] = $row;
}
$stmt_submissions->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Content Approval - Staff Dashboard</title>
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
            <h1>General Content Approval</h1>
            <p>Review and approve general content submissions from students.</p>

            <?php if ($successMessage): ?>
                <div class="success-message"><?php echo $successMessage; ?></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="error-message"><?php echo $errorMessage; ?></div>
            <?php endif; ?>

            <!-- Pending General Submissions Table -->
            <div class="card mt-30">
                <h2>Pending General Submissions</h2>
                <?php if (empty($general_submissions)): ?>
                    <p>No general content submissions are pending approval.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Student</th>
                                    <th>Type</th>
                                    <th class="clickable-header">Content Preview</th>
                                    <th>Submission Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($general_submissions as $submission): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($submission['content_id']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($submission['title']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($submission['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($submission['content_type']); ?></td>
                                    <td class="content-preview-cell" data-content-id="<?php echo $submission['content_id']; ?>" data-content-type="<?php echo htmlspecialchars($submission['content_type']); ?>">
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
                                        <!-- Button to open the approval/rejection modal -->
                                        <button type="button" class="btn-action open-approval-modal-btn" 
                                                data-content-id="<?php echo $submission['content_id']; ?>" 
                                                title="Approve/Reject with Remarks">
                                            <i class="fas fa-check-circle"></i> Review
                                        </button>
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

    <!-- Modal for Full Text Content (Same as before) -->
    <div id="fullTextModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h3 id="modalTitle">Full Content View</h3>
            <div id="modalContentBody" class="modal-body">
                <div class="loading-spinner" style="display: none; text-align: center; padding: 20px;">
                    <i class="fas fa-spinner fa-spin fa-3x" style="color: #4a90e2;"></i>
                    <p>Loading content...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- NEW Modal for Approval/Rejection with Remarks -->
    <div id="approvalRemarksModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h3>Review Content Submission</h3>
            <form id="approvalRemarksForm" action="staff_general_content.php" method="POST">
                <input type="hidden" name="content_id" id="modalContentId">
                
                <div class="form-group">
                    <label>Action:</label>
                    <div class="radio-group">
                        <label><input type="radio" name="approval_action" value="approve" required> Approve</label>
                        <label><input type="radio" name="approval_action" value="reject"> Reject</label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="remarks">Remarks (Optional):</label>
                    <textarea id="remarks" name="remarks" rows="4" placeholder="Add any comments or reasons for approval/rejection..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Submit Review</button>
            </form>
        </div>
    </div>

    <style>
        /* CSS for Modals (shared styles) */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1001; /* Sit on top */
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
            max-width: 600px; /* Adjusted max-width for remarks modal */
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
        
        /* Specific styles for the remarks modal form */
        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 5px;
            margin-bottom: 15px;
        }
        .radio-group label {
            font-weight: normal;
            display: flex;
            align-items: center;
        }
        .radio-group input[type="radio"] {
            margin-right: 8px;
            width: auto; /* Override general input width */
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
            background-color: var(--color-surface-2, #EDEFF1); /* Light hover effect */
        }
        .clickable-header {
            cursor: default;
        }
        /* Styles for success/error messages, buttons, tables, etc. should be in style.css */
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Elements for Full Text Modal (for content preview)
            const fullTextModal = document.getElementById("fullTextModal");
            const fullTextModalCloseButton = fullTextModal.querySelector(".close-button");
            const fullTextModalTitle = fullTextModal.querySelector("#modalTitle");
            const fullTextModalContentBody = fullTextModal.querySelector("#modalContentBody");
            const loadingSpinner = fullTextModal.querySelector(".loading-spinner"); 
            const contentPreviewCells = document.querySelectorAll(".content-preview-cell");

            // Elements for Approval/Remarks Modal
            const approvalRemarksModal = document.getElementById("approvalRemarksModal");
            const approvalRemarksCloseButton = approvalRemarksModal.querySelector(".close-button");
            const approvalRemarksForm = document.getElementById("approvalRemarksForm");
            const modalContentIdInput = document.getElementById("modalContentId");
            const openApprovalModalButtons = document.querySelectorAll(".open-approval-modal-btn");
            const remarksTextarea = document.getElementById("remarks");
            const approvalRadioButtons = document.querySelectorAll('input[name="approval_action"]');


            // --- Full Text Modal Logic (for content preview) ---
            contentPreviewCells.forEach(cell => {
                cell.addEventListener("click", function(event) {
                    const contentType = this.getAttribute("data-content-type").toLowerCase(); 
                    const contentId = this.getAttribute("data-content-id");

                    if (contentType === 'text') {
                        event.preventDefault(); 
                        event.stopPropagation(); 

                        fullTextModalContentBody.innerHTML = ''; 
                        loadingSpinner.style.display = 'block'; 
                        fullTextModal.style.display = "flex"; 
                        fullTextModalTitle.textContent = "Full Content View";

                        fetch('get_full_content.php?id=' + contentId)
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error(`HTTP error! status: ${response.status}`);
                                }
                                return response.text();
                            })
                            .then(data => {
                                loadingSpinner.style.display = 'none'; 
                                fullTextModalContentBody.innerHTML = data;
                            })
                            .catch(error => {
                                console.error('Error fetching full content:', error);
                                loadingSpinner.style.display = 'none'; 
                                fullTextModalContentBody.innerHTML = '<p style="color: red;">Could not load full content. Error: ' + error.message + '</p>';
                            });
                    }
                });
            });

            fullTextModalCloseButton.addEventListener("click", function() {
                fullTextModal.style.display = "none";
                fullTextModalContentBody.innerHTML = ''; 
                loadingSpinner.style.display = 'none'; 
            });

            window.addEventListener("click", function(event) {
                if (event.target == fullTextModal) {
                    fullTextModal.style.display = "none";
                    fullTextModalContentBody.innerHTML = ''; 
                    loadingSpinner.style.display = 'none'; 
                }
            });

            // --- Approval/Remarks Modal Logic ---
            openApprovalModalButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const contentId = this.getAttribute('data-content-id');
                    modalContentIdInput.value = contentId;
                    remarksTextarea.value = ''; // Clear remarks field on open
                    // Uncheck all radio buttons on open
                    approvalRadioButtons.forEach(radio => radio.checked = false); 
                    approvalRemarksModal.style.display = 'flex';
                });
            });

            approvalRemarksCloseButton.addEventListener('click', function() {
                approvalRemarksModal.style.display = 'none';
            });

            window.addEventListener('click', function(event) {
                if (event.target == approvalRemarksModal) {
                    approvalRemarksModal.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>