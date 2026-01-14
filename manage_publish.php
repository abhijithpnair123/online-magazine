<?php
// These two lines will show any hidden errors.
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kolkata');

session_start();
include 'db_connect.php';

// Define which user types are allowed to access this page
$allowed_roles = ['admin', 'staff'];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['usertype'], $allowed_roles)) {
    header("Location: index.php");
    exit();
}

$admin_user_id = $_SESSION['user_id'];
$successMessage = '';
$errorMessage = '';
$approved_content_id_to_publish = null;

if (isset($_GET['approved_content_id'])) {
    $approved_content_id_to_publish = $conn->real_escape_string($_GET['approved_content_id']);
    $successMessage = "Content ID " . htmlspecialchars($approved_content_id_to_publish) . " has been approved and is ready for publishing!";
}

// --- Handle Publishing Content ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['publish_action'])) {
    $content_id = $conn->real_escape_string($_POST['content_id']);
    $publish_option = $conn->real_escape_string($_POST['publish_option']);
    $publish_date = NULL;

    if ($publish_option === 'schedule' && !empty($_POST['scheduled_date'])) {
          $scheduled_date_from_form = $_POST['scheduled_date'];
        $datetime_obj = new DateTime($scheduled_date_from_form);
        $publish_date = $datetime_obj->format('Y-m-d H:i:s');
    } elseif ($publish_option === 'now') {
        $publish_date = date('Y-m-d H:i:s');
    }

    if ($publish_date === NULL) {
        $errorMessage = "Please select a valid publish date or choose 'Publish Now'.";
    } else {
        $stmt_publish = $conn->prepare("UPDATE tbl_content SET published_at = ? WHERE content_id = ?");
        $stmt_publish->bind_param("si", $publish_date, $content_id);
        if ($stmt_publish->execute()) {
            $successMessage = "Content ID " . htmlspecialchars($content_id) . " successfully scheduled for publishing!";
        } else {
            $errorMessage = "Error publishing content: " . $stmt_publish->error;
        }
        $stmt_publish->close();
    }
}

// --- NEW: Handle Canceling a Schedule ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_action'])) {
    $content_id = $conn->real_escape_string($_POST['content_id']);
    // This is the same as unpublishing; it just sets the date back to NULL
    $stmt_cancel = $conn->prepare("UPDATE tbl_content SET published_at = NULL WHERE content_id = ?");
    $stmt_cancel->bind_param("i", $content_id);
    if ($stmt_cancel->execute()) {
        $successMessage = "The schedule for Content ID " . htmlspecialchars($content_id) . " has been canceled.";
    } else {
        $errorMessage = "Error canceling schedule: " . $stmt_cancel->error;
    }
    $stmt_cancel->close();
}
// --- MODIFIED: Handle Removing Content (Soft Delete) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_action'])) {
    $content_id = $conn->real_escape_string($_POST['content_id']);
    // Set is_deleted to TRUE and unpublish the content
    $stmt_soft_delete = $conn->prepare("UPDATE tbl_content SET is_deleted = TRUE, published_at = NULL WHERE content_id = ?");
    $stmt_soft_delete->bind_param("i", $content_id);
    if ($stmt_soft_delete->execute()) {
        $successMessage = "Content ID " . htmlspecialchars($content_id) . " has been removed and moved to the archive.";
    } else {
        $errorMessage = "Error removing content: " . $stmt_soft_delete->error;
    }
    $stmt_soft_delete->close();
}

// --- NEW: Handle Restoring Content ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['restore_action'])) {
    $content_id = $conn->real_escape_string($_POST['content_id']);
    // Set is_deleted back to FALSE
    $stmt_restore = $conn->prepare("UPDATE tbl_content SET is_deleted = FALSE WHERE content_id = ?");
    $stmt_restore->bind_param("i", $content_id);
    if ($stmt_restore->execute()) {
        $successMessage = "Content ID " . htmlspecialchars($content_id) . " has been restored from the archive.";
    } else {
        $errorMessage = "Error restoring content: " . $stmt_restore->error;
    }
    $stmt_restore->close();
}

// --- MODIFIED: Fetch Approved but Not Yet Published Content ---
$approved_but_unpublished_content = [];
$stmt_approved = $conn->prepare("
    SELECT tc.content_id, tc.title, ts.student_name, tct.type_name AS content_type, tcat.description AS category_name, tc.submitted_date
    FROM tbl_content tc
    JOIN tbl_content_approval tca ON tc.content_id = tca.content_id
    JOIN tbl_student ts ON tc.student_id = ts.student_id
    JOIN tbl_content_type tct ON tc.type_id = tct.type_id
    JOIN tbl_content_category tcat ON tc.category_id = tcat.category_id
    WHERE tca.status = 'approved' AND tc.published_at IS NULL AND tc.is_deleted = FALSE
    GROUP BY tc.content_id ORDER BY tc.submitted_date DESC
");
$stmt_approved->execute();
$result_approved = $stmt_approved->get_result();
while ($row = $result_approved->fetch_assoc()) {
    $approved_but_unpublished_content[] = $row;
}
$stmt_approved->close();

// --- MODIFIED: Fetch Published Content Record ---
$published_content_record = [];
$stmt_published = $conn->prepare("
    SELECT tc.content_id, tc.title, ts.student_name, tct.type_name AS content_type, tcat.description AS category_name, tc.submitted_date, tc.published_at
    FROM tbl_content tc
    JOIN tbl_content_approval tca ON tc.content_id = tca.content_id
    JOIN tbl_student ts ON tc.student_id = ts.student_id
    JOIN tbl_content_type tct ON tc.type_id = tct.type_id
    JOIN tbl_content_category tcat ON tc.category_id = tcat.category_id
    WHERE tc.published_at IS NOT NULL AND tc.published_at <= NOW() AND tca.status = 'approved' AND tc.is_deleted = FALSE
    GROUP BY tc.content_id ORDER BY tc.published_at DESC
");
$stmt_published->execute();
$result_published = $stmt_published->get_result();
while ($row = $result_published->fetch_assoc()) {
    $published_content_record[] = $row;
}
$stmt_published->close();

// --- NEW: Fetch Scheduled Content ---
$scheduled_content = [];
$stmt_scheduled = $conn->prepare("
    SELECT tc.content_id, tc.title, ts.student_name, tc.published_at
    FROM tbl_content tc
    JOIN tbl_student ts ON tc.student_id = ts.student_id
    WHERE tc.published_at > NOW() AND tc.is_deleted = FALSE
    ORDER BY tc.published_at ASC
");
$stmt_scheduled->execute();
$result_scheduled = $stmt_scheduled->get_result();
while ($row = $result_scheduled->fetch_assoc()) {
    $scheduled_content[] = $row;
}
$stmt_scheduled->close();

// --- NEW: Fetch Archived (Soft-Deleted) Content ---
$archived_content = [];
$stmt_archived = $conn->prepare("
    SELECT tc.content_id, tc.title, ts.student_name, tc.submitted_date
    FROM tbl_content tc
    JOIN tbl_student ts ON tc.student_id = ts.student_id
    WHERE tc.is_deleted = TRUE
    ORDER BY tc.submitted_date DESC
");
$stmt_archived->execute();
$result_archived = $stmt_archived->get_result();
while ($row = $result_archived->fetch_assoc()) {
    $archived_content[] = $row;
}
$stmt_archived->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publish Content - Admin Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style> 
        .modal{display:none;position:fixed;z-index:1001;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,0.6);justify-content:center;align-items:center;padding:20px}.modal-content{background-color:#fefefe;margin:auto;padding:30px;border-radius:10px;box-shadow:0 5px 15px rgba(0,0,0,0.3);width:80%;max-width:800px;position:relative;max-height:90vh;overflow-y:auto}.close-button{color:#aaa;float:right;font-size:28px;font-weight:bold;position:absolute;top:10px;right:20px;cursor:pointer}.close-button:hover,.close-button:focus{color:black;text-decoration:none;cursor:pointer}.modal-body{margin-top:20px;white-space:pre-wrap;word-wrap:break-word}.highlight-row{background-color:#e6ffe6;animation:fadeHighlight 2s forwards}@keyframes fadeHighlight{from{background-color:#e6ffe6}to{background-color:transparent}}.unpublish-btn{background-color:#ffc107;color:#333}.unpublish-btn:hover{background-color:#e0a800}.delete-btn{background-color:#dc3545}.delete-btn:hover{background-color:#c82333} 
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Publish Content</h1>
            <p>Manage approved content and schedule it for publication.</p>

            <?php if ($successMessage): ?><div class="message success"><?php echo $successMessage; ?></div><?php endif; ?>
            <?php if ($errorMessage): ?><div class="message error"><?php echo $errorMessage; ?></div><?php endif; ?>

            <div class="card mt-30">
                <div class="card mt-30">
    <h2>Approved Content Ready for Publishing</h2>
    <?php if (empty($approved_but_unpublished_content)): ?>
        <p>No content is currently approved and awaiting publication.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th><th>Title</th><th>Student</th><th>Type</th><th>Category</th><th>Submission Date</th><th>Publish Options</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($approved_but_unpublished_content as $content): ?>
                    <tr <?php echo ($content['content_id'] == $approved_content_id_to_publish) ? 'class="highlight-row"' : ''; ?>>
                        <td><?php echo htmlspecialchars($content['content_id']); ?></td>
                        <td><?php echo htmlspecialchars($content['title']); ?></td>
                        <td><?php echo htmlspecialchars($content['student_name']); ?></td>
                        <td><?php echo htmlspecialchars($content['content_type']); ?></td>
                        <td><?php echo htmlspecialchars($content['category_name']?? 'General'); ?></td>
                        <td><?php echo htmlspecialchars($content['submitted_date']); ?></td>
                        <td class="action-buttons">
                            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" style="display:inline-block;">
                                <input type="hidden" name="content_id" value="<?php echo $content['content_id']; ?>">
                                <input type="hidden" name="publish_option" value="now">
                                <button type="submit" name="publish_action" class="btn-action publish-now-btn" title="Publish Now" onclick="return confirm('Publish immediately?');">
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
                </div>

                <div class="card mt-30">
    <h2>Scheduled Content</h2>
    <?php if (empty($scheduled_content)): ?>
        <p>No content is currently scheduled for future publication.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Student</th>
                        <th>Scheduled For</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scheduled_content as $content): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($content['content_id']); ?></td>
                        <td><?php echo htmlspecialchars($content['title']); ?></td>
                        <td><?php echo htmlspecialchars($content['student_name']); ?></td>
                        <td><?php echo htmlspecialchars($content['published_at']); ?></td>
                        <td>
                            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" onsubmit="return confirm('Are you sure you want to cancel this schedule?');">
                                <input type="hidden" name="content_id" value="<?php echo $content['content_id']; ?>">
                                <button type="submit" name="cancel_action" class="btn-action unpublish-btn">
                                    <i class="fas fa-ban"></i> Cancel Schedule
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

          <div id="schedulePublishModal" class="modal">
    <div class="modal-content">
        <span class="close-button">&times;</span>
        <h3>Schedule Content for Publishing</h3>
        <form id="schedulePublishForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
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

            <div class="card mt-30">
                <h2>Published Content Record</h2>
                <?php if (empty($published_content_record)): ?>
                    <p>No content has been published yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th><th>Title</th><th>Student</th><th>Type</th><th>Category</th><th>Submitted Date</th><th>Published Date</th><th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($published_content_record as $content): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($content['content_id']); ?></td>
                                    <td><?php echo htmlspecialchars($content['title']); ?></td>
                                    <td><?php echo htmlspecialchars($content['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($content['content_type']); ?></td>
                                    <td><?php echo htmlspecialchars($content['category_name']?? 'General'); ?></td>
                                    <td><?php echo htmlspecialchars($content['submitted_date']); ?></td>
                                    <td><?php echo htmlspecialchars($content['published_at']); ?></td>
                                    <td class="action-buttons">
                                        <button type="button" class="btn-action view-published-btn" title="View Content"><i class="fas fa-eye"></i> View</button>
                                
                                        
                                        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" style="display:inline-block; margin-left: 5px;">
                                            <input type="hidden" name="content_id" value="<?php echo $content['content_id']; ?>">
                                            <button type="submit" name="remove_action" class="btn-action delete-btn" title="Archive Content" onclick="return confirm('Are you sure you want to remove this content? It will be archived.');">
                                                <i class="fas fa-archive"></i> Remove
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
            
            <div class="card mt-30">
                <h2>Archived Content (Removed)</h2>
                <?php if (empty($archived_content)): ?>
                    <p>There is no archived content.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Student</th>
                                    <th>Submission Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($archived_content as $content): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($content['content_id']); ?></td>
                                    <td><?php echo htmlspecialchars($content['title']); ?></td>
                                    <td><?php echo htmlspecialchars($content['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($content['submitted_date']); ?></td>
                                    <td>
                                        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" onsubmit="return confirm('Are you sure you want to restore this content?');">
                                            <input type="hidden" name="content_id" value="<?php echo $content['content_id']; ?>">
                                            <button type="submit" name="restore_action" class="btn-action btn-success">
                                                <i class="fas fa-undo"></i> Restore
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
    
    <div id="fullTextModal" class="modal"> <div class="modal-content">
        <span class="close-button">&times;</span>
        <div id="modalContentBody" class="modal-body">
            </div>
    </div>
   </div>
   <script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Schedule Modal Logic ---
    const schedulePublishModal = document.getElementById("schedulePublishModal");
    const schedulePublishButtons = document.querySelectorAll(".schedule-publish-btn");
    const scheduleContentIdInput = document.getElementById("scheduleContentId");
    const scheduleCloseButton = schedulePublishModal.querySelector(".close-button");

    schedulePublishButtons.forEach(button => {
        button.addEventListener('click', function() {
            const contentId = this.getAttribute('data-content-id');
            scheduleContentIdInput.value = contentId;
            schedulePublishModal.style.display = 'flex';
        });
    });

    scheduleCloseButton.addEventListener('click', function() {
        schedulePublishModal.style.display = 'none';
    });
    
    window.addEventListener('click', function(event) {
        if (event.target == schedulePublishModal) {
            schedulePublishModal.style.display = 'none';
        }
    });

    // --- View Content Modal Logic ---
    const fullTextModal = document.getElementById("fullTextModal");
    if(fullTextModal) {
        const fullTextModalCloseButton = fullTextModal.querySelector(".close-button");
        const fullTextModalContentBody = fullTextModal.querySelector("#modalContentBody");
        const viewPublishedButtons = document.querySelectorAll(".view-published-btn");

        viewPublishedButtons.forEach(button => {
            button.addEventListener('click', function() {
                const row = this.closest('tr');
                const contentId = row.children[0].textContent;

                fullTextModal.style.display = "flex";
                if(fullTextModalContentBody) fullTextModalContentBody.innerHTML = '<p>Loading...</p>';

                fetch('get_full_content.php?id=' + contentId)
                    .then(response => response.ok ? response.text() : Promise.reject('Network response was not ok.'))
                    .then(data => {
                        if(fullTextModalContentBody) fullTextModalContentBody.innerHTML = data;
                    })
                    .catch(error => {
                        console.error('Error fetching content:', error);
                        if(fullTextModalContentBody) fullTextModalContentBody.innerHTML = '<p style="color: red;">Error: Could not load content.</p>';
                    });
            });
        });

        if (fullTextModalCloseButton) {
            fullTextModalCloseButton.addEventListener("click", () => fullTextModal.style.display = "none");
        }
        window.addEventListener("click", event => {
            if (event.target == fullTextModal) fullTextModal.style.display = "none";
        });
    }

        // --- END OF CODE BLOCK TO ADD ---


        // Your other script for viewing content can go here
        
    });
    
</script>
</body>
</html>