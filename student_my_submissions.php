<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'student') {
    header("Location: index.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// --- Fetch all content submitted by the logged-in student ---
$submissions = [];
// A LEFT JOIN on tbl_content_approval ensures that even newly submitted content (without an approval entry yet) is shown.
$stmt = $conn->prepare("
    SELECT
        tc.content_id,
        tc.title,
        tc.submitted_date,
        tc.category_id,
        tct.type_name,
        tcat.category_name,
        tcat.description AS competition_name,
        tca.status,
        tca.remarks
    FROM tbl_content tc
    JOIN tbl_content_type tct ON tc.type_id = tct.type_id
    JOIN tbl_content_category tcat ON tc.category_id = tcat.category_id
    LEFT JOIN tbl_content_approval tca ON tc.content_id = tca.content_id
    WHERE tc.student_id = ?
    ORDER BY tc.submitted_date DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $submissions[] = $row;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Submissions - Student Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>My Submissions</h1>
            <div class="card">
                <?php if (empty($submissions)): ?>
                    <p>You have not submitted any content yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Submission Date</th>
                                    <th>Type</th>
                                    <th>Competition</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($submissions as $submission): ?>
                                <tr data-competition-id="<?php echo htmlspecialchars($submission['category_id']); ?>">
                                    <td><?php echo htmlspecialchars($submission['title']); ?></td>
                                    <td><?php echo htmlspecialchars($submission['submitted_date']); ?></td>
                                    <td><?php echo htmlspecialchars($submission['type_name']); ?></td>
                                    <td><?php echo htmlspecialchars($submission['competition_name']); ?></td>
                                    <td class="status-cell" data-competition-id="<?php echo htmlspecialchars($submission['category_id']); ?>">
                                        <?php
                                            $status = $submission['status'] ?? 'pending';
                                            $status_class = 'status-' . strtolower($status);
                                            echo '<span class="status-badge ' . $status_class . '">' . htmlspecialchars(ucfirst($status)) . '</span>';
                                        ?>
                                    </td>
                                    <td class="remarks-cell">
                                        <?php
                                            if ($status === 'rejected' && !empty($submission['remarks'])) {
                                                echo htmlspecialchars($submission['remarks']);
                                            } else {
                                                echo '—';
                                            }
                                        ?>
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
    <script>
        function updateCompetitionStatus() {
            fetch('competitions_api.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        data.competitions.forEach(competition => {
                            const statusElement = document.querySelector(`.status-cell[data-competition-id="${competition.id}"]`);
                            if (statusElement) {
                                statusElement.textContent = competition.status;
                                // Optional: Update the class for styling
                                const statusBadge = statusElement.querySelector('.status-badge') || document.createElement('span');
                                statusBadge.textContent = competition.status;
                                statusBadge.className = 'status-badge status-' + competition.status.toLowerCase();
                                if (!statusElement.contains(statusBadge)) {
                                    statusElement.innerHTML = '';
                                    statusElement.appendChild(statusBadge);
                                }
                            }
                        });
                    }
                })
                .catch(error => console.error('Error fetching competition status:', error));
        }

        // Call the function immediately and then every minute
        document.addEventListener('DOMContentLoaded', () => {
            updateCompetitionStatus();
            setInterval(updateCompetitionStatus, 60000); // 60000 milliseconds = 1 minute
        });
    </script>
</body>
</html>