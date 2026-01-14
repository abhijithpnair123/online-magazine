<?php
// update_upvote.php
if (session_status() === PHP_SESSION_NONE) { // Ensure session is started only once
    session_start();
}
include 'db_connect.php'; // Include your database connection file

header('Content-Type: application/json'); // Respond with JSON

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$student_id = $_SESSION['user_id'];
$content_id = $_POST['content_id'] ?? null;
$action = $_POST['action'] ?? null; // 'upvote' or 'unupvote'

if ($content_id === null || ($action !== 'upvote' && $action !== 'unupvote')) {
    echo json_encode(['success' => false, 'message' => 'Invalid content ID or action.']);
    exit();
}

$conn->begin_transaction(); // Start transaction for atomicity

try {
    // 1. Check if a feedback entry exists for this student and content
    $stmt_check = $conn->prepare("SELECT feedback_id, upvoted, comment FROM tbl_feedback WHERE content_id = ? AND student_id = ?");
    $stmt_check->bind_param("ii", $content_id, $student_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $existing_feedback = $result_check->fetch_assoc();
    $stmt_check->close();

    if ($action === 'upvote') {
        if ($existing_feedback) {
            // Entry exists: update only if not already upvoted
            if ($existing_feedback['upvoted'] != 1) {
                $stmt_update = $conn->prepare("UPDATE tbl_feedback SET upvoted = 1, feedback_date = NOW() WHERE feedback_id = ?");
                $stmt_update->bind_param("i", $existing_feedback['feedback_id']);
                if (!$stmt_update->execute()) {
                    throw new mysqli_sql_exception($stmt_update->error);
                }
                $stmt_update->close();
            }
        } else {
            // No entry: insert a new one for upvote
            // Importantly, we insert `upvoted = 1` and `comment = NULL` initially.
            $stmt_insert = $conn->prepare("INSERT INTO tbl_feedback (content_id, student_id, upvoted, comment, feedback_date) VALUES (?, ?, 1, NULL, NOW())");
            $stmt_insert->bind_param("ii", $content_id, $student_id);
            if (!$stmt_insert->execute()) {
                throw new mysqli_sql_exception($stmt_insert->error);
            }
            $stmt_insert->close();
        }
    } elseif ($action === 'unupvote') {
        if ($existing_feedback && $existing_feedback['upvoted'] == 1) {
            // Entry exists and was upvoted: set upvoted to 0
            $stmt_update = $conn->prepare("UPDATE tbl_feedback SET upvoted = 0, feedback_date = NOW() WHERE feedback_id = ?");
            $stmt_update->bind_param("i", $existing_feedback['feedback_id']);
            if (!$stmt_update->execute()) {
                throw new mysqli_sql_exception($stmt_update->error);
            }
            $stmt_update->close();
        }
        // If no existing feedback or not upvoted, do nothing (idempotent)
    }

    // 2. Recalculate upvotes_count and comments_count for the content from tbl_feedback
    // This is crucial for accuracy.
    
    // Total upvotes
    $stmt_upvotes_count = $conn->prepare("SELECT COUNT(*) FROM tbl_feedback WHERE content_id = ? AND upvoted = 1");
    $stmt_upvotes_count->bind_param("i", $content_id);
    $stmt_upvotes_count->execute();
    $new_upvotes_count = $stmt_upvotes_count->get_result()->fetch_row()[0];
    $stmt_upvotes_count->close();

    // Total comments (counting only non-empty comments)
    $stmt_comments_count = $conn->prepare("SELECT COUNT(*) FROM tbl_feedback WHERE content_id = ? AND comment IS NOT NULL AND comment != ''");
    $stmt_comments_count->bind_param("i", $content_id);
    $stmt_comments_count->execute();
    $new_comments_count = $stmt_comments_count->get_result()->fetch_row()[0];
    $stmt_comments_count->close();

    // 3. Update upvotes_count and comments_count in tbl_content
    $stmt_update_content_counts = $conn->prepare("UPDATE tbl_content SET upvotes = ?, comments_count = ? WHERE content_id = ?");
    $stmt_update_content_counts->bind_param("iii", $new_upvotes_count, $new_comments_count, $content_id);
    if (!$stmt_update_content_counts->execute()) {
        throw new mysqli_sql_exception($stmt_update_content_counts->error);
    }
    $stmt_update_content_counts->close();

    $conn->commit(); // Commit transaction

    echo json_encode([
        'success' => true,
        'new_upvote_count' => $new_upvotes_count,
        'new_comments_count' => $new_comments_count,
        'message' => 'Upvote status updated successfully!'
    ]);

} catch (mysqli_sql_exception $e) {
    $conn->rollback(); // Rollback on SQL error
    error_log("SQL Error in update_upvote.php: " . $e->getMessage()); // Log error for debugging
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    $conn->rollback(); // Rollback on general error
    error_log("General Error in update_upvote.php: " . $e->getMessage()); // Log error for debugging
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
}

$conn->close();
?>
