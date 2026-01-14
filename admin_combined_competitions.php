<?php
session_start();
include 'db_connect.php';

// MODIFIED: Check if user is logged in and is an ADMIN
if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Get the admin's corresponding staff_id for logging approvals
$admin_email = $_SESSION['user_email'];
$admin_staff_id_result = $conn->query("SELECT staff_id FROM tbl_staff WHERE email = '{$admin_email}'");
$admin_staff_id = $admin_staff_id_result->fetch_assoc()['staff_id'];

$successMessage = '';
$errorMessage = '';

// Determine which view to show: 'manage' or 'approval'
$current_view = isset($_GET['view']) ? $_GET['view'] : 'manage'; // Default to 'manage'

// --- Handle Adding New Competition ---
if ($current_view === 'manage' && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_competition'])) {
    // Note: The 'competition_title' from the form is used as the 'description' in the database table
    $description = $conn->real_escape_string($_POST['competition_title']);
    $competition_date = $conn->real_escape_string($_POST['competition_date']);
    $rules = $conn->real_escape_string($_POST['rules']);
    $allowed_types = isset($_POST['allowed_types']) ? implode(',', $_POST['allowed_types']) : '';
    
    if (empty($description) || empty($competition_date)) {
        $errorMessage = "Competition Title and Date are required.";
    } else {
        $category_name = 'competition'; 
        
        // BUG FIXED: Removed the non-existent 'staff_id' column from the query.
        $stmt = $conn->prepare("INSERT INTO tbl_content_category (category_name, description, allowed_types, competition_date, rules) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $category_name, $description, $allowed_types, $competition_date, $rules);

        if ($stmt->execute()) {
            $successMessage = "New competition added successfully!";
        } else {
            $errorMessage = "Error adding competition: " . $stmt->error;
        }
        $stmt->close();
    }
}

// --- Handle Content Approval/Rejection ---
if ($current_view === 'approval' && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $submission_id = $conn->real_escape_string($_POST['submission_id']);
    $action = $conn->real_escape_string($_POST['action']);
    
    $status_to_update = ($action === 'approve') ? 'approved' : 'rejected';
    
    // Use the admin's staff_id for the approval record
    $stmt = $conn->prepare("UPDATE tbl_content_approval SET status = ?, staff_id = ? WHERE content_id = ?");
    $stmt->bind_param("sii", $status_to_update, $admin_staff_id, $submission_id); 

    if ($stmt->execute()) {
        $successMessage = "Submission ID " . $submission_id . " has been " . $status_to_update . ".";
    } else {
        $errorMessage = "Error updating submission status: " . $stmt->error;
    }
    $stmt->close();
}

// --- Fetch Data based on current_view ---
$competitions = [];
$competition_submissions = [];

if ($current_view === 'manage') {
    $stmt_competitions = $conn->prepare("SELECT category_id, description, allowed_types, competition_date, rules FROM tbl_content_category WHERE category_name = 'competition' ORDER BY competition_date DESC");
    $stmt_competitions->execute();
    $result_competitions = $stmt_competitions->get_result();
    while ($row = $result_competitions->fetch_assoc()) {
        $competitions[] = $row;
    }
    $stmt_competitions->close();
} elseif ($current_view === 'approval') {
    $stmt_submissions = $conn->prepare("
        SELECT
            tca.content_id AS submission_id, tc.title, tc.contentbody, tc.file_path, tc.submitted_date,
            ts.student_name, tcat.description AS competition_name, tct.type_name AS content_type
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
    <title>Admin Competitions & Content</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1><?php echo ($current_view === 'manage') ? 'Manage Competitions' : 'Competition Content (Approval)'; ?></h1>
            <p><?php echo ($current_view === 'manage') ? 'Add new competitions and view existing ones.' : 'Review and approve student submissions.'; ?></p>

            <?php if ($successMessage): ?><div class="success-message"><?php echo $successMessage; ?></div><?php endif; ?>
            <?php if ($errorMessage): ?><div class="error-message"><?php echo $errorMessage; ?></div><?php endif; ?>

            <?php if ($current_view === 'manage'): ?>
                <div class="card">
                    <h2>Add New Competition</h2>
                    <form action="admin_combined_competitions.php?view=manage" method="POST">
                        <div class="form-group">
                            <label for="competition_title">Competition Title:</label>
                            <input type="text" id="competition_title" name="competition_title" required>
                        </div>
                        <div class="form-group">
                            <label for="competition_date">Competition Date:</label>
                            <input type="date" id="competition_date" name="competition_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="rules">Rules:</label>
                            <textarea id="rules" name="rules" rows="4"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Allowed Content Types:</label>
                            <div class="checkbox-group">
                                <label><input type="checkbox" name="allowed_types[]" value="text"> Text</label>
                                <label><input type="checkbox" name="allowed_types[]" value="image"> Image</label>
                                <label><input type="checkbox" name="allowed_types[]" value="video"> Video</label>
                            </div>
                        </div>
                        <button type="submit" name="add_competition" class="btn btn-primary">Add Competition</button>
                    </form>
                </div>

                <div class="card mt-30">
                    <h2>Scheduled Competitions</h2>
                    <?php if (empty($competitions)): ?>
                        <p>No competitions scheduled yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead><tr><th>ID</th><th>Description</th><th>Date</th><th>Allowed Types</th><th>Rules</th></tr></thead>
                                <tbody>
                                    <?php foreach ($competitions as $comp): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($comp['category_id']); ?></td>
                                        <td><?php echo htmlspecialchars($comp['description']); ?></td>
                                        <td><?php echo htmlspecialchars($comp['competition_date']); ?></td>
                                        <td><?php echo htmlspecialchars($comp['allowed_types']); ?></td>
                                        <td><?php echo nl2br(htmlspecialchars($comp['rules'] ?? 'N/A')); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($current_view === 'approval'): ?>
                <div class="card mt-30">
                    <h2>Pending Competition Submissions</h2>
                    <?php if (empty($competition_submissions)): ?>
                        <p>No competition submissions are pending approval.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                             <table class="data-table">
                                <thead><tr><th>ID</th><th>Title</th><th>Student</th><th>Competition</th><th>Type</th><th>Content Preview</th><th>Submission Date</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($competition_submissions as $submission): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($submission['submission_id']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($submission['title']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($submission['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($submission['competition_name']); ?></td>
                                        <td><?php echo htmlspecialchars($submission['content_type']); ?></td>
                                        <td class="content-preview-cell" data-content-id="<?php echo $submission['submission_id']; ?>" data-content-type="<?php echo htmlspecialchars($submission['content_type']); ?>">
                                            <?php if ($submission['file_path']): ?>
                                                <a href="<?php echo htmlspecialchars($submission['file_path']); ?>" target="_blank"><i class="fas fa-external-link-alt"></i> View Media</a>
                                            <?php else: ?>
                                                <div class="text-content-preview"><?php echo nl2br(htmlspecialchars(substr($submission['contentbody'], 0, 100))); ?>...</div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date("M d, Y", strtotime($submission['submitted_date'])); ?></td>
                                        <td class="action-buttons">
                                            <form action="admin_combined_competitions.php?view=approval" method="POST" style="display:inline-block;">
                                                <input type="hidden" name="submission_id" value="<?php echo $submission['submission_id']; ?>">
                                                <button type="submit" name="action" value="approve" class="btn-action approve-btn" title="Approve"><i class="fas fa-check"></i></button>
                                            </form>
                                            <form action="admin_combined_competitions.php?view=approval" method="POST" style="display:inline-block;">
                                                <input type="hidden" name="submission_id" value="<?php echo $submission['submission_id']; ?>">
                                                <button type="submit" name="action" value="reject" class="btn-action reject-btn" title="Reject" onclick="return confirm('Reject this submission?');"><i class="fas fa-times"></i></button>
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

    <div id="fullTextModal" class="modal"><div class="modal-content"><span class="close-button">&times;</span><h3 id="modalTitle">Full Content View</h3><div id="modalContentBody" class="modal-body"></div></div></div>
    <style>
        /* Admin competitions theme (off-white + coral + teal) */
        .card { 
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(245, 245, 245, 0.94)); 
            border: 1px solid rgba(255, 107, 107, 0.18); 
            border-radius: 14px; 
            padding: 25px; 
            box-shadow: 0 12px 28px rgba(0,0,0,0.1); 
            color: var(--color-text, #333333); 
            margin-bottom: 30px; 
        }
        .mt-30 { margin-top: 30px; }
        .card h2 { 
            color: var(--color-primary-2, #FF8E8E); 
            font-family: 'Poppins','Inter',sans-serif; 
            font-size: 1.4em; 
            margin-bottom: 20px; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        .card h2 i { color: var(--color-primary, #FF6B6B); }
        
        /* Form styling */
        .form-group { margin-bottom: 20px; }
        .form-group label { 
            color: var(--color-primary-2, #FF8E8E); 
            font-weight: 600; 
            display: block; 
            margin-bottom: 8px; 
        }
        .form-group input, .form-group textarea, .form-group select { 
            width: 100%; 
            padding: 12px; 
            background: var(--color-bg, #FEFDFB); 
            color: var(--color-text, #333333); 
            border: 1px solid #E0E0E0; 
            border-radius: 10px; 
            transition: all 0.2s ease; 
            box-sizing: border-box;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { 
            outline: none; 
            border-color: var(--color-primary, #FF6B6B); 
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.2); 
        }
        .checkbox-group { display: flex; gap: 15px; flex-wrap: wrap; }
        .checkbox-group label { display: flex; align-items: center; gap: 5px; font-weight: normal; }
        
        /* Button styling */
        .btn-primary { 
            background: linear-gradient(45deg, var(--color-primary-2, #FF8E8E), var(--color-primary, #FF6B6B)); 
            color: #FEFDFB; 
            padding: 12px 25px; 
            border: none; 
            border-radius: 999px; 
            font-weight: 700; 
            cursor: pointer; 
            transition: transform 0.2s ease, box-shadow 0.2s ease; 
            box-shadow: 0 8px 20px rgba(255, 107, 107, 0.25); 
        }
        .btn-primary:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 12px 28px rgba(255, 107, 107, 0.35); 
        }
        
        /* Table styling */
        .table-responsive { overflow-x: auto; }
        .data-table { 
             table-layout: fixed; 
            width: 100%; 
            border-collapse: collapse; 
            background: linear-gradient(180deg, var(--color-surface, #F5F5F5), var(--color-bg, #FEFDFB)); 
            border: 1px solid #E0E0E0; 
            border-radius: 14px; 
            overflow: hidden; 
            box-shadow: 0 0 0 1px rgba(255, 107, 107, 0.18), 0 18px 40px rgba(0,0,0,0.1), 0 0 32px rgba(255, 107, 107, 0.08); 
        }
        .data-table thead th { 
            background: linear-gradient(45deg, var(--color-primary-2, #FF8E8E), var(--color-primary, #FF6B6B)); 
            color: #FEFDFB; 
            text-shadow: 0 1px 0 rgba(0,0,0,0.1); 
            padding: 16px 18px; 
            border: 1px solid #E0E0E0; 
            font-weight: 800; 
            letter-spacing: .4px; 
        }
        .data-table tbody td { 
            word-wrap: break-word;
            color: var(--color-text, #333333); 
            padding: 16px 18px; 
            border: 1px solid #E0E0E0; 
        }
        .data-table tbody tr:nth-child(even) { background: var(--color-surface-2, #EEEEEE); }
        .data-table tbody tr:nth-child(odd) { background: var(--color-surface, #F5F5F5); }
        .data-table tbody tr:hover { background: rgba(255, 107, 107, 0.10); }
        
        /* Action buttons */
        .action-buttons { text-align: center; }
        .btn-action { 
            padding: 8px 12px; 
            border-radius: 8px; 
            text-decoration: none; 
            transition: all 0.2s ease; 
            display: inline-block; 
            border: none;
            cursor: pointer;
            margin: 0 2px;
        }
        .approve-btn { 
            background: rgba(23, 195, 178, 0.08); 
            color: var(--color-secondary, #17C3B2); 
            border: 1px solid rgba(23, 195, 178, 0.18); 
        }
        .approve-btn:hover { 
            background: rgba(23, 195, 178, 0.14); 
            transform: translateY(-1px); 
        }
        .reject-btn { 
            background: rgba(255, 107, 107, 0.08); 
            color: var(--color-primary, #FF6B6B); 
            border: 1px solid rgba(255, 107, 107, 0.18); 
        }
        .reject-btn:hover { 
            background: rgba(255, 107, 107, 0.14); 
            transform: translateY(-1px); 
        }
        
        /* Modal styling */
        .modal{display:none;position:fixed;z-index:1002;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,0.6);justify-content:center;align-items:center;padding:20px}
        .modal-content{background: linear-gradient(180deg, #FFFFFF, #F5F5F5);margin:auto;padding:30px;border-radius:14px;box-shadow:0 18px 42px rgba(0,0,0,0.15);width:80%;max-width:800px;position:relative;max-height:90vh;overflow-y:auto;border: 1px solid rgba(255, 107, 107, 0.2);}
        .close-button{color:#999;float:right;font-size:28px;font-weight:bold;position:absolute;top:10px;right:20px;cursor:pointer;transition: color 0.2s ease, transform 0.2s ease;}
        .close-button:hover,.close-button:focus{color: var(--color-primary, #FF6B6B); transform: scale(1.05); text-decoration:none;}
        .modal-body{margin-top:20px;white-space:pre-wrap;word-wrap:break-word;color: var(--color-text, #333333);}
        .text-content-preview{max-height:100px;overflow:hidden}
        .content-preview-cell{cursor:pointer;transition:background-color .2s ease}
        .content-preview-cell:hover{background-color:rgba(255, 107, 107, 0.05)}
         /* 250px for the sidebar + 20px of space */
}
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById("fullTextModal"),
            closeButton = modal.querySelector(".close-button"),
            modalTitle = modal.querySelector("#modalTitle"),
            modalContentBody = modal.querySelector("#modalContentBody");
        document.querySelectorAll(".content-preview-cell").forEach(cell => {
            cell.addEventListener("click", function(event) {
                if (this.getAttribute("data-content-type").toLowerCase() === 'text') {
                    event.preventDefault();
                    modal.style.display = "flex";
                    fetch('get_full_content.php?id=' + this.getAttribute("data-content-id"))
                        .then(response => response.text())
                        .then(data => modalContentBody.innerHTML = data)
                        .catch(error => modalContentBody.innerHTML = '<p style="color: red;">Could not load content.</p>');
                }
            });
        });
        closeButton.onclick = () => modal.style.display = "none";
        window.onclick = event => { if (event.target == modal) modal.style.display = "none"; };
    });
    </script>
</body>
</html>