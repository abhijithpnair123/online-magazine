<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json'); // Respond with JSON

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$student_id = $_SESSION['user_id'];
$content_id = $_POST['content_id'] ?? null;

if ($content_id === null) {
    echo json_encode(['success' => false, 'message' => 'Content ID is required.']);
    exit();
}

$conn->begin_transaction(); // Start transaction for atomicity

try {
    // 1. Check if a feedback entry exists for this student and content
    $stmt_check = $conn->prepare("SELECT upvoted FROM tbl_feedback WHERE content_id = ? AND student_id = ?");
    $stmt_check->bind_param("ii", $content_id, $student_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $existing_feedback = $result_check->fetch_assoc();
    $stmt_check->close();

    if ($existing_feedback) {
        // Feedback entry exists
        if ($existing_feedback['upvoted'] == 1) {
            // Already upvoted, do nothing (client-side button should be disabled)
            $conn->rollback(); // No changes made, so rollback is fine
            echo json_encode(['success' => false, 'message' => 'You have already upvoted this content.']);
            exit();
        } else {
            // Feedback exists, but not upvoted. Update it.
            $stmt_update_feedback = $conn->prepare("UPDATE tbl_feedback SET upvoted = 1, feedback_date = CURRENT_TIMESTAMP WHERE content_id = ? AND student_id = ?");
            $stmt_update_feedback->bind_param("ii", $content_id, $student_id);
            $stmt_update_feedback->execute();
            $stmt_update_feedback->close();
        }
    } else {
        // No feedback entry exists, insert a new one for the upvote
        $stmt_insert_feedback = $conn->prepare("INSERT INTO tbl_feedback (content_id, student_id, upvoted, comment) VALUES (?, ?, 1, NULL)");
        $stmt_insert_feedback->bind_param("ii", $content_id, $student_id);
        $stmt_insert_feedback->execute();
        $stmt_insert_feedback->close();
    }

    // 2. Increment upvotes count in tbl_content (always, as we handle existing upvotes above)
    $stmt_update_content = $conn->prepare("UPDATE tbl_content SET upvotes = upvotes + 1 WHERE content_id = ?");
    $stmt_update_content->bind_param("i", $content_id);
    $stmt_update_content->execute();
    $stmt_update_content->close();

    // 3. Get the new upvote count from tbl_content
    $stmt_get_count = $conn->prepare("SELECT upvotes FROM tbl_content WHERE content_id = ?");
    $stmt_get_count->bind_param("i", $content_id);
    $stmt_get_count->execute();
    $result_count = $stmt_get_count->get_result();
    $row_count = $result_count->fetch_assoc();
    $new_upvote_count = $row_count['upvotes'];
    $stmt_get_count->close();

    $conn->commit(); // Commit transaction
    echo json_encode(['success' => true, 'new_upvote_count' => $new_upvote_count, 'message' => 'Content upvoted successfully!']);

} catch (mysqli_sql_exception $e) {
    $conn->rollback(); // Rollback on error
    error_log("Upvote Error: " . $e->getMessage()); // Log error for debugging
    echo json_encode(['success' => false, 'message' => 'An error occurred during upvoting. Please try again.']);
}

$conn->close();
?>