<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'staff') {
    header("Location: index.php");
    exit();
}

$staff_id = $_SESSION['user_id'];
$message = '';

// Handle approval/rejection/publish actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['approve_content'])) {
        $content_id = (int)$_POST['content_id'];
        $remarks = $conn->real_escape_string($_POST['remarks']);

        // Check if content is already approved/rejected
        $stmt_check_status = $conn->prepare("SELECT status FROM tbl_content_approval WHERE content_id = ?");
        $stmt_check_status->bind_param("i", $content_id);
        $stmt_check_status->execute();
        $result_status = $stmt_check_status->get_result();
        $current_status_row = $result_status->fetch_assoc();
        $stmt_check_status->close();

        if ($current_status_row && $current_status_row['status'] !== 'pending') {
            $message = "<p class='message error'>Content has already been " . htmlspecialchars($current_status_row['status']) . ".</p>";
        } else {
            // Update or Insert into tbl_content_approval
            $stmt_approval = $conn->prepare("
                INSERT INTO tbl_content_approval (content_id, staff_id, status, remarks)
                VALUES (?, ?, 'approved', ?)
                ON DUPLICATE KEY UPDATE staff_id = VALUES(staff_id), status = 'approved', remarks = VALUES(remarks)
            ");
            $stmt_approval->bind_param("iis", $content_id, $staff_id, $remarks);

            if ($stmt_approval->execute()) {
                $message = "<p class='message success'>Content approved successfully!</p>";
            } else {
                $message = "<p class='message error'>Error approving content: " . $stmt_approval->error . "</p>";
            }
            $stmt_approval->close();
        }

    } elseif (isset($_POST['reject_content'])) {
        $content_id = (int)$_POST['content_id'];
        $remarks = $conn->real_escape_string($_POST['remarks']);

        // Check if content is already approved/rejected
        $stmt_check_status = $conn->prepare("SELECT status FROM tbl_content_approval WHERE content_id = ?");
        $stmt_check_status->bind_param("i", $content_id);
        $stmt_check_status->execute();
        $result_status = $stmt_check_status->get_result();
        $current_status_row = $result_status->fetch_assoc();
        $stmt_check_status->close();

        if ($current_status_row && $current_status_row['status'] !== 'pending') {
            $message = "<p class='message error'>Content has already been " . htmlspecialchars($current_status_row['status']) . ".</p>";
        } else {
            // Update or Insert into tbl_content_approval
            $stmt_approval = $conn->prepare("
                INSERT INTO tbl_content_approval (content_id, staff_id, status, remarks)
                VALUES (?, ?, 'rejected', ?)
                ON DUPLICATE KEY UPDATE staff_id = VALUES(staff_id), status = 'rejected', remarks = VALUES(remarks)
            ");
            $stmt_approval->bind_param("iis", $content_id, $staff_id, $remarks);

            if ($stmt_approval->execute()) {
                $message = "<p class='message success'>Content rejected successfully!</p>";
            } else {
                $message = "<p class='message error'>Error rejecting content: " . $stmt_approval->error . "</p>";
            }
            $stmt_approval->close();
        }
    } elseif (isset($_POST['publish_content'])) {
        $content_id = (int)$_POST['content_id'];

        // First, check if the content is approved
        $stmt_check_approved = $conn->prepare("SELECT approval_id, status FROM tbl_content_approval WHERE content_id = ?");
        $stmt_check_approved->bind_param("i", $content_id);
        $stmt_check_approved->execute();
        $result_approved = $stmt_check_approved->get_result();
        $approval_data = $result_approved->fetch_assoc();
        $stmt_check_approved->close();

        if ($approval_data && $approval_data['status'] === 'approved') {
            // Insert into tbl_publish
            $stmt_publish = $conn->prepare("INSERT INTO tbl_publish (content_id, staff_id, status) VALUES (?, ?, 'published')");
            $stmt_publish->bind_param("ii", $content_id, $staff_id);
            if ($stmt_publish->execute()) {
                $message = "<p class='message success'>Content published successfully!</p>";
            } else {
                $message = "<p class='message error'>Error publishing content: " . $stmt_publish->error . "</p>";
            }
            $stmt_publish->close();
        } else {
            $message = "<p class='message error'>Content must be approved before it can be published.</p>";
        }
    }
}

// Fetch content submitted by students for approval (where category is 'general' and not yet published)
$pending_content = [];
$sql = "SELECT
            c.content_id,
            c.title,
            c.contentbody,
            c.file_path,
            c.submitted_date,
            s.student_name,
            d.dept_name,
            p.program_name,
            t.type_name,
            COALESCE(ca.status, 'pending') AS approval_status,
            COALESCE(ca.remarks, '') AS staff_remarks,
            pb.publish_id
        FROM
            tbl_content c
        JOIN
            tbl_student s ON c.student_id = s.student_id
        JOIN
            tbl_department d ON s.dept_id = d.dept_id
        JOIN
            tbl_program p ON s.program_id = p.program_id
        JOIN
            tbl_content_type t ON c.type_id = t.type_id
        LEFT JOIN
            tbl_content_category cc ON c.category_id = cc.category_id
        LEFT JOIN
            tbl_content_approval ca ON c.content_id = ca.content_id
        LEFT JOIN
            tbl_publish pb ON c.content_id = pb.content_id
        WHERE
            (cc.category_name = 'general' OR c.category_id IS NULL) AND pb.publish_id IS NULL -- Only general content that isn't published
        ORDER BY
            c.submitted_date DESC";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pending_content[] = $row;
    }
} else {
    $message .= "<p class='message info'>No general content awaiting approval or publication.</p>";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Content Approval</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .content-item {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .content-item h3 {
            color: #34495e;
            margin-bottom: 10px;
        }
        .content-item p {
            margin-bottom: 8px;
        }
        .content-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .content-actions textarea {
            flex-grow: 1;
        }
        .content-body-display {
            background-color: #eaf2f8;
            border-left: 5px solid #3498db;
            padding: 15px;
            margin-top: 10px;
            margin-bottom: 15px;
            white-space: pre-wrap; /* Preserve whitespace and line breaks */
            max-height: 200px; /* Limit height */
            overflow-y: auto; /* Add scroll if content is too long */
        }
        .file-link {
            display: block;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>General Content Approval</h1>
            <?php echo $message; ?>

            <?php if (!empty($pending_content)): ?>
                <?php foreach ($pending_content as $content): ?>
                    <div class="content-item">
                        <h3><?php echo htmlspecialchars($content['title']); ?></h3>
                        <p><strong>Submitted by:</strong> <?php echo htmlspecialchars($content['student_name']); ?> (<?php echo htmlspecialchars($content['program_name'] . ', ' . $content['dept_name']); ?>)</p>
                        <p><strong>Content Type:</strong> <?php echo htmlspecialchars($content['type_name']); ?></p>
                        <p><strong>Submitted On:</strong> <?php echo date("Y-m-d H:i", strtotime($content['submitted_date'])); ?></p>

                        <h4>Content:</h4>
                        <?php if ($content['contentbody']): ?>
                            <div class="content-body-display"><?php echo htmlspecialchars($content['contentbody']); ?></div>
                        <?php endif; ?>
                        <?php if ($content['file_path']): ?>
                            <p><strong>Attached File:</strong> <a href="<?php echo htmlspecialchars($content['file_path']); ?>" target="_blank" class="file-link">View File</a></p>
                        <?php endif; ?>

                        <p><strong>Current Status:</strong> <span style="font-weight: bold; color: <?php echo ($content['approval_status'] == 'approved') ? 'green' : (($content['approval_status'] == 'rejected') ? 'red' : 'orange'); ?>;"><?php echo htmlspecialchars(ucfirst($content['approval_status'])); ?></span></p>
                        <?php if ($content['staff_remarks']): ?>
                            <p><strong>Staff Remarks:</strong> <?php echo htmlspecialchars($content['staff_remarks']); ?></p>
                        <?php endif; ?>

                        <?php if ($content['publish_id'] === NULL): // Only show options if not yet published ?>
                            <form action="staff_general_content.php" method="POST" class="content-actions">
                                <input type="hidden" name="content_id" value="<?php echo $content['content_id']; ?>">
                                <textarea name="remarks" placeholder="Add remarks (optional)"></textarea>
                                <?php if ($content['approval_status'] === 'pending' || $content['approval_status'] === 'rejected'): ?>
                                    <button type="submit" name="approve_content" class="btn-success">Approve</button>
                                <?php endif; ?>
                                <?php if ($content['approval_status'] === 'pending' || $content['approval_status'] === 'approved'): ?>
                                    <button type="submit" name="reject_content" class="btn-danger">Reject</button>
                                <?php endif; ?>
                                <?php if ($content['approval_status'] === 'approved'): // Only allow publish if approved ?>
                                    <button type="submit" name="publish_content" class="btn-primary">Publish</button>
                                <?php endif; ?>
                            </form>
                        <?php else: ?>
                            <p style="margin-top: 15px; font-weight: bold; color: green;">This content has been published.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No content for general approval.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>