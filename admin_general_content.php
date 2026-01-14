<?php
session_start();
include 'db_connect.php';

// MODIFIED: Check if user is logged in and is an ADMIN
if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Get the admin's corresponding staff_id for logging the approval action
$admin_email = $_SESSION['user_email'];
$admin_staff_id_result = $conn->query("SELECT staff_id FROM tbl_staff WHERE email = '{$admin_email}'");
$admin_staff_id = $admin_staff_id_result->fetch_assoc()['staff_id'];

$successMessage = '';
$errorMessage = '';

// --- Handle Content Approval/Rejection ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['approval_action'])) {
    $content_id = $conn->real_escape_string($_POST['content_id']);
    $action = $conn->real_escape_string($_POST['approval_action']);
    $remarks = $conn->real_escape_string($_POST['remarks']);
    
    $status_to_update = ($action === 'approve') ? 'approved' : 'rejected';
    
    // Update tbl_content_approval with the admin's staff_id
    $stmt = $conn->prepare("UPDATE tbl_content_approval SET status = ?, staff_id = ?, remarks = ? WHERE content_id = ?");
    $stmt->bind_param("sisi", $status_to_update, $admin_staff_id, $remarks, $content_id); 

    if ($stmt->execute()) {
        if ($action === 'approve') {
            // MODIFIED: Redirect to the ADMIN publish content page
            header("Location: admin_publish_content.php?approved_content_id=" . $content_id);
            exit();
        }
        // If rejected, show success message on this page
        $successMessage = "Content ID " . $content_id . " has been " . $status_to_update . " successfully.";
    } else {
        $errorMessage = "Error updating content status: " . $stmt->error;
    }
    $stmt->close();
}

// --- Fetch Unapproved General Content Submissions ---
$general_submissions = [];
$stmt_submissions = $conn->prepare("
    SELECT
        tca.content_id, tc.title, tc.contentbody, tc.file_path, tc.submitted_date,
        ts.student_name, tct.type_name AS content_type
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
    <title>General Content Approval - Admin Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>General Content Approval</h1>
            <p>Review and approve general content submissions from students.</p>

            <?php if ($successMessage): ?><div class="success-message"><?php echo $successMessage; ?></div><?php endif; ?>
            <?php if ($errorMessage): ?><div class="error-message"><?php echo $errorMessage; ?></div><?php endif; ?>

            <div class="card mt-30">
                <h2>Pending General Submissions</h2>
                <?php if (empty($general_submissions)): ?>
                    <p>No general content submissions are pending approval.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th><th>Title</th><th>Student</th><th>Type</th><th>Content Preview</th><th>Submission Date</th><th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($general_submissions as $submission): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($submission['content_id']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($submission['title']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($submission['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($submission['content_type']); ?></td>
                                    <td class="content-preview-cell" data-content-id="<?php echo $submission['content_id']; ?>" data-content-type="<?php echo htmlspecialchars($submission['content_type']); ?>">
                                        <?php if ($submission['file_path']): ?>
                                            <a href="<?php echo htmlspecialchars($submission['file_path']); ?>" target="_blank"><i class="fas fa-external-link-alt"></i> View Media</a>
                                        <?php else: ?>
                                            <div class="text-content-preview"><?php echo nl2br(htmlspecialchars(substr($submission['contentbody'], 0, 100))); ?>...</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date("M d, Y", strtotime($submission['submitted_date'])); ?></td>
                                    <td class="action-buttons">
                                        <button type="button" class="btn-action open-approval-modal-btn" data-content-id="<?php echo $submission['content_id']; ?>" title="Approve/Reject">
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

    <div id="fullTextModal" class="modal"><div class="modal-content"><span class="close-button">&times;</span><h3 id="modalTitle">Full Content</h3><div id="modalContentBody" class="modal-body"></div></div></div>

    <div id="approvalRemarksModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h3>Review Content Submission</h3>
            <form id="approvalRemarksForm" action="admin_general_content.php" method="POST">
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
                    <textarea id="remarks" name="remarks" rows="4"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Submit Review</button>
            </form>
        </div>
    </div>
    
    <style>.modal{display:none;position:fixed;z-index:1001;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,0.6);justify-content:center;align-items:center;padding:20px}.modal-content{background-color:var(--color-surface,#FFFFFF);margin:auto;padding:30px;border-radius:10px;box-shadow:0 5px 15px rgba(0,0,0,0.3);width:80%;max-width:600px;position:relative;max-height:90vh;overflow-y:auto}.close-button{color:#aaa;float:right;font-size:28px;font-weight:bold;position:absolute;top:10px;right:20px;cursor:pointer}.close-button:hover,.close-button:focus{color:black;text-decoration:none}.modal-body{margin-top:20px;white-space:pre-wrap;word-wrap:break-word}.radio-group{display:flex;gap:20px;margin-top:5px;margin-bottom:15px}.radio-group label{font-weight:normal;display:flex;align-items:center}.radio-group input[type=radio]{margin-right:8px;width:auto}.text-content-preview{max-height:100px;overflow:hidden}.content-preview-cell{cursor:pointer;transition:background-color .2s ease}.content-preview-cell:hover{background-color:var(--color-surface-2,#EDEFF1)}</style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const fullTextModal = document.getElementById("fullTextModal"),
            fullTextModalClose = fullTextModal.querySelector(".close-button"),
            fullTextModalBody = fullTextModal.querySelector("#modalContentBody");
        document.querySelectorAll(".content-preview-cell").forEach(cell => {
            cell.addEventListener("click", function(event) {
                if (this.dataset.contentType.toLowerCase() === 'text') {
                    event.preventDefault();
                    fullTextModal.style.display = "flex";
                    fetch('get_full_content.php?id=' + this.dataset.contentId)
                        .then(res => res.text()).then(data => fullTextModalBody.innerHTML = data)
                        .catch(err => fullTextModalBody.innerHTML = '<p style="color:red;">Could not load content.</p>');
                }
            });
        });
        fullTextModalClose.onclick = () => fullTextModal.style.display = "none";

        const approvalModal = document.getElementById("approvalRemarksModal"),
            approvalModalClose = approvalModal.querySelector(".close-button"),
            modalContentIdInput = document.getElementById("modalContentId");
        document.querySelectorAll(".open-approval-modal-btn").forEach(btn => {
            btn.addEventListener('click', function() {
                modalContentIdInput.value = this.dataset.contentId;
                approvalModal.style.display = 'flex';
            });
        });
        approvalModalClose.onclick = () => approvalModal.style.display = 'none';
        window.onclick = event => {
            if (event.target == fullTextModal) fullTextModal.style.display = "none";
            if (event.target == approvalModal) approvalModal.style.display = "none";
        };
    });
    </script>
</body>
</html>