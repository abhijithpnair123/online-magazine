<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
include 'db_connect.php';

// Check if ANY user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$selected_category_id = $_GET['competition_id'] ?? null; 

// --- Fetch all competitions for the dropdown ---
$competitions = [];
$competition_names = []; // ADD THIS LINE
$stmt_competitions = $conn->prepare("SELECT category_id, description AS competition_name FROM tbl_content_category WHERE category_name = 'competition' ORDER BY description ASC");
$stmt_competitions->execute();
$result_competitions = $stmt_competitions->get_result();
while ($row = $result_competitions->fetch_assoc()) {
    $competitions[] = $row;
    $competition_names[$row['category_id']] = $row['competition_name']; // ADD THIS LINE
}
$stmt_competitions->close();

// --- Fetch winning content for the selected competition category ---
$winning_content = [];
if ($selected_category_id) {
    // MODIFIED: The SQL query has been updated to filter for published content only.
    $stmt_winning_content = $conn->prepare("
       SELECT
            tc.title AS content_title,
            ts.student_name,
            -- This subquery now calculates upvotes ONLY within the 10-day voting period
            (SELECT COUNT(*) 
             FROM tbl_feedback tf 
             WHERE tf.content_id = tc.content_id 
               AND tf.upvoted = 1
               AND tf.feedback_date BETWEEN tcat.submission_end_datetime AND DATE_ADD(tcat.submission_end_datetime, INTERVAL  15 MINUTE)
            ) AS upvotes_in_period
        FROM tbl_content tc
        JOIN tbl_student ts ON tc.student_id = ts.student_id
        JOIN tbl_content_category tcat ON tc.category_id = tcat.category_id
        JOIN tbl_content_approval tca ON tc.content_id = tca.content_id
        WHERE 
            tc.category_id = ? 
            AND tcat.category_name = 'competition'
            AND tca.status = 'approved'
            AND tc.published_at IS NOT NULL
        -- Order by the newly calculated, time-limited upvote count
        ORDER BY upvotes_in_period DESC, tc.published_at DESC
        LIMIT 10
    ");
    $stmt_winning_content->bind_param("i", $selected_category_id);
    $stmt_winning_content->execute();
    $result_winning_content = $stmt_winning_content->get_result();
    while ($row = $result_winning_content->fetch_assoc()) {
        $winning_content[] = $row;
    }
    $stmt_winning_content->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Competition Results</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>View Competition Results</h1>
            <p>Select a competition to view its top published content based on upvotes.</p>

            <div class="card mt-30">
                <h2>Select Competition</h2>
                <form action="view_competition_results.php" method="GET" class="form-inline">
                    <div class="form-group">
                        <label for="competition_select">Competition:</label>
                        <select id="competition_select" name="competition_id" class="form-control" onchange="this.form.submit()">
                            <option value="">-- Select a Competition --</option>
                            <?php foreach ($competitions as $comp): ?>
                                <option value="<?php echo htmlspecialchars($comp['category_id']); ?>"
                                    <?php echo ($selected_category_id == $comp['category_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($comp['competition_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <?php if ($selected_category_id): ?>
                <div class="card mt-30">
                    <h2>Leaderboard for: <?php
    echo htmlspecialchars($competition_names[$selected_category_id] ?? 'Selected Competition');?></h2>
                    <?php if (empty($winning_content)): ?>
                        <p>No published content has received upvotes for this competition yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table leaderboard-table">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Student Name</th>
                                        <th>Content Title</th>
                                        <th><i class="fas fa-thumbs-up"></i> Upvotes</th>
                                    </tr>
                                </thead>
                                <tbody>
    <?php
    $rank = 0;
    $last_score = -1; // Initialize with a value that can't be an upvote count
    $display_rank = 0;
    $medals = ['🥇', '🥈', '🥉'];
    ?>
    <?php foreach ($winning_content as $content): ?>
        <?php
        $rank++; // This tracks the row number
        // If the score is different from the last one, update the display rank
        if ($content['upvotes_in_period'] != $last_score) {
            $display_rank = $rank;
            $last_score = $content['upvotes_in_period'];
        }
        ?>
        <tr class="rank-<?php echo $display_rank; ?>">
            <td class="rank-cell">
                <?php echo $medals[$display_rank - 1] ?? $display_rank; ?>
            </td>
            <td><?php echo htmlspecialchars($content['student_name']); ?></td>
            <td><?php echo htmlspecialchars($content['content_title']); ?></td>
            <td class="upvotes-cell"><?php echo htmlspecialchars($content['upvotes_in_period']); ?></td>
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

    <style>
        .form-inline { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
        .leaderboard-table .rank-cell { font-size: 1.5em; text-align: center; }
        .leaderboard-table .upvotes-cell { font-weight: bold; text-align: right; }
        .leaderboard-table .rank-1, .leaderboard-table .rank-2, .leaderboard-table .rank-3 { font-weight: bold; }
        .leaderboard-table .rank-1 { background: linear-gradient(135deg, rgba(255, 107, 107, 0.15), rgba(255, 107, 107, 0.05)); }
        .leaderboard-table .rank-2 { background: linear-gradient(135deg, rgba(23, 195, 178, 0.15), rgba(23, 195, 178, 0.05)); }
        .leaderboard-table .rank-3 { background: linear-gradient(135deg, rgba(255, 107, 107, 0.1), rgba(23, 195, 178, 0.1)); }
    </style>
</body>
</html>