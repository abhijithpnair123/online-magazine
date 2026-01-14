<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'student') {
    header("Location: index.php");
    exit();
}

$student_id = $_SESSION['user_id']; // Current student's ID (if needed for personalized content later)
$successMessage = '';
$errorMessage = '';

// --- Fetch All Published Content ---
$published_content = [];
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
    ORDER BY tc.published_at DESC
");
$stmt_published->execute();
$result_published = $stmt_published->get_result();
while ($row = $result_published->fetch_assoc()) {
    $published_content[] = $row;
}
$stmt_published->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Published Content - Student Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Published Content</h1>
            <p>Explore the latest articles, images, and videos published by our community!</p>

            <?php if ($successMessage): ?>
                <div class="success-message"><?php echo $successMessage; ?></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="error-message"><?php echo $errorMessage; ?></div>
            <?php endif; ?>

            <div class="card mt-30">
                <h2>All Published Content</h2>
                <?php if (empty($published_content)): ?>
                    <p>No content has been published yet. Check back soon!</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>Type</th>
                                    <th>Category</th>
                                    <th class="clickable-header">Content Preview</th>
                                    <th>Published Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($published_content as $content): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($content['title']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($content['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($content['content_type']); ?></td>
                                    <td><?php echo htmlspecialchars($content['category_name']); ?></td>
                                    <td class="content-preview-cell" data-content-id="<?php echo $content['content_id']; ?>" data-content-type="<?php echo htmlspecialchars($content['content_type']); ?>">
                                        <?php if ($content['file_path']): ?>
                                            <?php 
                                                $file_ext = strtolower(pathinfo($content['file_path'], PATHINFO_EXTENSION));
                                                if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])):
                                            ?>
                                                <a href="<?php echo htmlspecialchars($content['file_path']); ?>" target="_blank" title="View Full Image">
                                                    <img src="<?php echo htmlspecialchars($content['file_path']); ?>" alt="Content Image" style="max-width: 100px; max-height: 100px; border-radius: 5px; object-fit: cover;">
                                                </a>
                                            <?php elseif (in_array($file_ext, ['mp4', 'webm', 'ogg'])): ?>
                                                <a href="<?php echo htmlspecialchars($content['file_path']); ?>" target="_blank" title="View Full Video">
                                                    <i class="fas fa-video"></i> View Video
                                                </a>
                                            <?php else: ?>
                                                <a href="<?php echo htmlspecialchars($content['file_path']); ?>" target="_blank" title="Download File">
                                                    <i class="fas fa-file"></i> View File
                                                </a>
                                            <?php endif; ?>
                                        <?php else: /* Text content */ ?>
                                            <div class="text-content-preview">
                                                <?php 
                                                    // Sanitize and format text content for preview
                                                    $preview_text = stripslashes($content['contentbody']);
                                                    $preview_text = str_replace(array('\\r\\n', '\\n', '\\r'), "\n", $preview_text);
                                                    $preview_text = htmlspecialchars(substr($preview_text, 0, 200));
                                                    echo nl2br($preview_text); 
                                                ?>...
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($content['published_at']); ?></td>
                                    <td class="action-buttons">
                                        <button type="button" class="btn-action view-content-btn" data-content-id="<?php echo $content['content_id']; ?>" data-content-type="<?php echo htmlspecialchars($content['content_type']); ?>" title="View Full Content">
                                            <i class="fas fa-eye"></i> View
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

    <!-- Modal for Full Content View (re-used for viewing content) -->
    <div id="fullContentModal" class="modal">
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
            background-color: var(--color-surface, #FFFFFF);
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
        
        /* Table specific styles */
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
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fullContentModal = document.getElementById("fullContentModal");
            const closeButton = fullContentModal.querySelector(".close-button");
            const modalTitle = fullContentModal.querySelector("#modalTitle");
            const modalContentBody = fullContentModal.querySelector("#modalContentBody");
            const loadingSpinner = fullContentModal.querySelector(".loading-spinner"); 
            
            const viewContentButtons = document.querySelectorAll(".view-content-btn");

            viewContentButtons.forEach(button => {
                button.addEventListener("click", function(event) {
                    const contentType = this.getAttribute("data-content-type").toLowerCase(); 
                    const contentId = this.getAttribute("data-content-id");

                    if (contentType === 'text') {
                        event.preventDefault(); 
                        event.stopPropagation(); 

                        modalContentBody.innerHTML = ''; 
                        loadingSpinner.style.display = 'block'; 
                        fullContentModal.style.display = "flex"; 
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
                    } else if (contentType === 'image' || contentType === 'video') {
                        // For image/video, you might want to open the file directly in a new tab
                        // or display it within the modal if you fetch its URL.
                        // For now, we'll just open it in a new tab if file_path is available.
                        const row = this.closest('tr');
                        const previewCell = row.querySelector('.content-preview-cell');
                        const fileLink = previewCell.querySelector('a');
                        if (fileLink) {
                            window.open(fileLink.href, '_blank');
                        } else {
                            alert(`Content ID: ${contentId}, Type: ${contentType}. File not directly linked for full view. You may need to update the query to include file_path.`);
                        }
                    }
                });
            });

            closeButton.addEventListener("click", function() {
                fullContentModal.style.display = "none";
                modalContentBody.innerHTML = ''; 
                loadingSpinner.style.display = 'none'; 
            });

            window.addEventListener("click", function(event) {
                if (event.target == fullContentModal) {
                    fullContentModal.style.display = "none";
                    modalContentBody.innerHTML = ''; 
                    loadingSpinner.style.display = 'none'; 
                }
            });
        });
    </script>
</body>
</html>