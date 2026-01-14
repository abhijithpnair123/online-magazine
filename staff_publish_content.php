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
$approved_content_id_to_publish = null;

// Check if an approved_content_id was passed from staff_general_content.php
if (isset($_GET['approved_content_id'])) {
    $approved_content_id_to_publish = $conn->real_escape_string($_GET['approved_content_id']);
    $successMessage = "Content ID " . $approved_content_id_to_publish . " has been approved and is ready for publishing!";
}

// --- Handle Publishing Content ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['publish_action'])) {
    $content_id = $conn->real_escape_string($_POST['content_id']);
    $publish_option = $conn->real_escape_string($_POST['publish_option']); // 'now' or 'schedule'
    $publish_date = NULL;

    if ($publish_option === 'schedule' && !empty($_POST['scheduled_date'])) {
        $publish_date = $conn->real_escape_string($_POST['scheduled_date']);
    } elseif ($publish_option === 'now') {
        $publish_date = date('Y-m-d H:i:s'); // Current timestamp
    }

    if ($publish_date === NULL) {
        $errorMessage = "Please select a valid publish date or choose 'Publish Now'.";
    } else {
        // Update tbl_content with the publish date
        $stmt_publish = $conn->prepare("UPDATE tbl_content SET published_at = ? WHERE content_id = ?");
        $stmt_publish->bind_param("si", $publish_date, $content_id);

        if ($stmt_publish->execute()) {
            $successMessage = "Content ID " . $content_id . " successfully scheduled for publishing on " . $publish_date . "!";
            // Clear the approved_content_id_to_publish after it's handled
            $approved_content_id_to_publish = null; 
        } else {
            $errorMessage = "Error publishing content: " . $stmt_publish->error;
        }
        $stmt_publish->close();
    }
}

// --- Handle Unpublishing Content ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['unpublish_action'])) {
    $content_id = $conn->real_escape_string($_POST['content_id']);
    
    // Set published_at to NULL to unpublish the content
    $stmt_unpublish = $conn->prepare("UPDATE tbl_content SET published_at = NULL WHERE content_id = ?");
    $stmt_unpublish->bind_param("i", $content_id);

    if ($stmt_unpublish->execute()) {
        $successMessage = "Content ID " . $content_id . " has been successfully unpublished! It is now back in 'Approved Content Ready for Publishing'.";
    } else {
        $errorMessage = "Error unpublishing content: " . $stmt_unpublish->error;
    }
    $stmt_unpublish->close();
}

// --- Handle Removing Content (Permanent Delete) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_action'])) {
    $content_id = $conn->real_escape_string($_POST['content_id']);

    // Start a transaction for atomicity
    $conn->begin_transaction();
    try {
        // 1. Get file_path before deleting content record
        $file_path_to_delete = null;
        $stmt_get_file = $conn->prepare("SELECT file_path FROM tbl_content WHERE content_id = ?");
        $stmt_get_file->bind_param("i", $content_id);
        $stmt_get_file->execute();
        $result_file = $stmt_get_file->get_result();
        if ($row_file = $result_file->fetch_assoc()) {
            $file_path_to_delete = $row_file['file_path'];
        }
        $stmt_get_file->close();

        // 2. Delete from tbl_content_approval first (due to foreign key constraints)
        $stmt_delete_approval = $conn->prepare("DELETE FROM tbl_content_approval WHERE content_id = ?");
        $stmt_delete_approval->bind_param("i", $content_id);
        $stmt_delete_approval->execute();
        $stmt_delete_approval->close();

        // 3. Delete from tbl_content
        $stmt_delete_content = $conn->prepare("DELETE FROM tbl_content WHERE content_id = ?");
        $stmt_delete_content->bind_param("i", $content_id);
        $stmt_delete_content->execute();
        $stmt_delete_content->close();

        // 4. Commit transaction
        $conn->commit();

        // 5. Delete associated file from server if it exists
        if ($file_path_to_delete && file_exists($file_path_to_delete)) {
            unlink($file_path_to_delete);
        }

        $successMessage = "Content ID " . $content_id . " and its associated records have been permanently removed.";

    } catch (mysqli_sql_exception $e) {
        $conn->rollback(); // Rollback on error
        $errorMessage = "Error removing content: " . $e->getMessage();
    }
}


// --- Fetch Approved but Not Yet Published Content ---
$approved_but_unpublished_content = [];
$stmt_approved = $conn->prepare("
    SELECT
        tc.content_id,
        tc.title,
        tc.contentbody,
        tc.file_path,
        tc.submitted_date,
        ts.student_name,
        tct.type_name AS content_type,
        tcat.description AS category_name
    FROM tbl_content tc
    JOIN tbl_content_approval tca ON tc.content_id = tca.content_id
    JOIN tbl_student ts ON tc.student_id = ts.student_id
    JOIN tbl_content_type tct ON tc.type_id = tct.type_id
    JOIN tbl_content_category tcat ON tc.category_id = tcat.category_id
    WHERE tca.status = 'approved' AND tc.published_at IS NULL
    GROUP BY tc.content_id -- Added to prevent duplicates
    ORDER BY tc.submitted_date DESC
");
$stmt_approved->execute();
$result_approved = $stmt_approved->get_result();
while ($row = $result_approved->fetch_assoc()) {
    $approved_but_unpublished_content[] = $row;
}
$stmt_approved->close();

// --- Fetch Published Content Record ---
$published_content_record = [];
$stmt_published = $conn->prepare("
    SELECT
        tc.content_id,
        tc.title,
        tc.contentbody,
        tc.file_path,
        tc.submitted_date,
        tc.published_at,
        ts.student_name,
        tct.type_name AS content_type,
        tcat.description AS category_name
    FROM tbl_content tc
    JOIN tbl_content_approval tca ON tc.content_id = tca.content_id
    JOIN tbl_student ts ON tc.student_id = ts.student_id
    JOIN tbl_content_type tct ON tc.type_id = tct.type_id
    JOIN tbl_content_category tcat ON tc.category_id = tcat.category_id
    WHERE tc.published_at IS NOT NULL AND tca.status = 'approved'
    GROUP BY tc.content_id -- Added to prevent duplicates
    ORDER BY tc.published_at DESC
");
$stmt_published->execute();
$result_published = $stmt_published->get_result();
while ($row = $result_published->fetch_assoc()) {
    $published_content_record[] = $row;
}
$stmt_published->close();

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publish Content - Staff Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Publish Content</h1>
            <p>Manage approved content and schedule it for publication.</p>

            <?php if ($successMessage): ?>
                <div class="success-message"><?php echo $successMessage; ?></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="error-message"><?php echo $errorMessage; ?></div>
            <?php endif; ?>

            <!-- Approved but Not Yet Published Content -->
            <div class="card mt-30">
                <h2>Approved Content Ready for Publishing</h2>
                <?php if (empty($approved_but_unpublished_content)): ?>
                    <p>No content is currently approved and awaiting publication.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Student</th>
                                    <th>Type</th>
                                    <th>Category</th>
                                    <th>Submission Date</th>
                                    <th>Publish Options</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($approved_but_unpublished_content as $content): ?>
                                <tr <?php echo ($content['content_id'] == $approved_content_id_to_publish) ? 'class="highlight-row"' : ''; ?>>
                                    <td><?php echo htmlspecialchars($content['content_id']); ?></td>
                                    <td><?php echo htmlspecialchars($content['title']); ?></td>
                                    <td><?php echo htmlspecialchars($content['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($content['content_type']); ?></td>
                                    <td><?php echo htmlspecialchars($content['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($content['submitted_date']); ?></td>
                                    <td class="action-buttons">
                                        <form action="staff_publish_content.php" method="POST" style="display:inline-block;">
                                            <input type="hidden" name="content_id" value="<?php echo $content['content_id']; ?>">
                                            <input type="hidden" name="publish_option" value="now">
                                            <button type="submit" name="publish_action" class="btn-action publish-now-btn" title="Publish Now" onclick="return confirm('Publish this content immediately?');">
                                                <i class="fas fa-play"></i> Publish Now
                                            </button>
                                        </form>
                                        <button type="button" class="btn-action schedule-publish-btn" data-content-id="<?php echo $content['content_id']; ?>" title="Schedule Publish">
                                            <i class="fas fa-calendar-alt"></i> Schedule
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Modal for Schedule Publish -->
            <div id="schedulePublishModal" class="modal">
                <div class="modal-content">
                    <span class="close-button">&times;</span>
                    <h3>Schedule Content for Publishing</h3>
                    <form id="schedulePublishForm" action="staff_publish_content.php" method="POST">
                        <input type="hidden" name="content_id" id="scheduleContentId">
                        <input type="hidden" name="publish_option" value="schedule">
                        <div class="form-group">
                            <label for="scheduled_date">Select Publish Date & Time:</label>
                            <input type="datetime-local" id="scheduled_date" name="scheduled_date" required>
                        </div>
                        <button type="submit" name="publish_action" class="btn btn-primary">Schedule Publish</button>
                    </form>
                </div>
            </div>

            <!-- Published Content Record -->
            <div class="card mt-30">
                <h2>Published Content Record</h2>
                <?php if (empty($published_content_record)): ?>
                    <p>No content has been published yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Student</th>
                                    <th>Type</th>
                                    <th>Category</th>
                                    <th>Submitted Date</th>
                                    <th>Published Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($published_content_record as $content): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($content['content_id']); ?></td>
                                    <td><?php echo htmlspecialchars($content['title']); ?></td>
                                    <td><?php echo htmlspecialchars($content['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($content['content_type']); ?></td>
                                    <td><?php echo htmlspecialchars($content['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($content['submitted_date']); ?></td>
                                    <td><?php echo htmlspecialchars($content['published_at']); ?></td>
                                    <td class="action-buttons">
                                        <button type="button" class="btn-action view-published-btn" title="View Content">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <form action="staff_publish_content.php" method="POST" style="display:inline-block; margin-left: 5px;">
                                            <input type="hidden" name="content_id" value="<?php echo $content['content_id']; ?>">
                                            <button type="submit" name="unpublish_action" class="btn-action unpublish-btn" title="Unpublish" onclick="return confirm('Are you sure you want to unpublish this content? It will move back to the 'Approved for Publishing' list.');">
                                                <i class="fas fa-undo"></i> Unpublish
                                            </button>
                                        </form>
                                        <form action="staff_publish_content.php" method="POST" style="display:inline-block; margin-left: 5px;">
                                            <input type="hidden" name="content_id" value="<?php echo $content['content_id']; ?>">
                                            <button type="submit" name="remove_action" class="btn-action delete-btn" title="Remove Permanently" onclick="return confirm('WARNING: Are you sure you want to PERMANENTLY REMOVE this content? This action cannot be undone and will delete all associated records and files.');">
                                                <i class="fas fa-trash-alt"></i> Remove
                                            </button>
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

    <!-- Modal for Full Text Content (re-used for viewing published content) -->
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

    <style>
        /* General Modal CSS (can be shared with other pages) */
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
            max-width: 800px;
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
        .highlight-row {
            background-color: rgba(0, 191, 165, 0.12); /* Teal highlight for newly approved content */
            animation: fadeHighlight 2s forwards;
        }
        @keyframes fadeHighlight {
            from { background-color: rgba(0, 191, 165, 0.12); }
            to { background-color: transparent; }
        }

        /* New button style for unpublish */
        .unpublish-btn {
            background-color: var(--color-secondary, #FF8C42); /* Orange */
            color: var(--color-bg, #F8F9FA); /* Light text for contrast */
        }
        .unpublish-btn:hover {
            background-color: var(--color-secondary-3, #E6762D);
        }
        /* Ensure delete-btn (now for remove) remains red */
        .delete-btn {
            background-color: #dc3545; /* Red */
        }
        .delete-btn:hover {
            background-color: #c82333;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Re-use the existing modal elements
            const fullTextModal = document.getElementById("fullTextModal");
            const fullTextModalCloseButton = fullTextModal.querySelector(".close-button");
            const fullTextModalTitle = fullTextModal.querySelector("#modalTitle");
            const fullTextModalContentBody = fullTextModal.querySelector("#modalContentBody");
            const loadingSpinner = fullTextModal.querySelector(".loading-spinner");

            // Schedule Publish Modal specific elements
            const schedulePublishModal = document.getElementById("schedulePublishModal");
            const schedulePublishCloseButton = schedulePublishModal.querySelector(".close-button");
            const scheduleContentIdInput = document.getElementById("scheduleContentId");
            const schedulePublishButtons = document.querySelectorAll(".schedule-publish-btn");

            // Handle opening the schedule modal
            schedulePublishButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const contentId = this.getAttribute('data-content-id');
                    scheduleContentIdInput.value = contentId;
                    schedulePublishModal.style.display = 'flex';
                });
            });

            // Handle closing schedule modal
            schedulePublishCloseButton.addEventListener('click', function() {
                schedulePublishModal.style.display = 'none';
                scheduleContentIdInput.value = ''; // Clear ID
                document.getElementById('scheduled_date').value = ''; // Clear date
            });

            // Close schedule modal if clicking outside
            window.addEventListener('click', function(event) {
                if (event.target == schedulePublishModal) {
                    schedulePublishModal.style.display = 'none';
                    scheduleContentIdInput.value = '';
                    document.getElementById('scheduled_date').value = '';
                }
            });

            // Handle "View Content" for published items (re-uses fullTextModal)
            const viewPublishedButtons = document.querySelectorAll(".view-published-btn");
            viewPublishedButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const row = this.closest('tr');
                    const contentId = row.children[0].textContent; // First cell is ID
                    const contentType = row.children[3].textContent.toLowerCase(); // Type is in 4th cell

                    // For text content, open the modal and fetch
                    if (contentType === 'text') {
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
                    } else if (contentType === 'image' || contentType === 'video') {
                        // For image/video, find the file path (assuming it's not directly in the published table,
                        // but you'd need to fetch it or have it in the row data if you want to open it directly.
                        // For now, we'll just log or show a message, as the published table doesn't have file_path directly.
                        // If file_path is needed, you'd need to add it to the $published_content_record query.
                        alert(`For media content (ID: ${contentId}, Type: ${contentType}), you would typically open the file directly. This demo doesn't fetch file_path for published items. You can add it to the PHP query and link it here.`);
                    }
                });
            });

            // Handle closing full text modal
            fullTextModalCloseButton.addEventListener("click", function() {
                fullTextModal.style.display = "none";
                fullTextModalContentBody.innerHTML = ''; 
                loadingSpinner.style.display = 'none'; 
            });

            // Close full text modal if clicking outside
            window.addEventListener("click", function(event) {
                if (event.target == fullTextModal) {
                    fullTextModal.style.display = "none";
                    fullTextModalContentBody.innerHTML = ''; 
                    loadingSpinner.style.display = 'none'; 
                }
            });

            // Highlight row if coming from approval page
            const highlightRow = document.querySelector('.highlight-row');
            if (highlightRow) {
                setTimeout(() => {
                    highlightRow.classList.remove('highlight-row');
                }, 2000); // Remove highlight after 2 seconds
            }
        });
    </script>
</body>
</html>
