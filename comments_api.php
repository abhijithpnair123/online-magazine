<?php
// comments_api.php
// This file handles all comment-related API actions: fetching, adding/editing, and deleting.

session_start();
include 'db_connect.php'; // Ensure your database connection file is included
header('Content-Type: application/json'); // Always respond with JSON

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
    exit();
}

$student_id = $_SESSION['user_id'];
$usertype = $_SESSION['usertype'] ?? null;
$student_name = $_SESSION['user_name'] ?? 'Anonymous Student'; // Assumed to be set in session during login

// --- Handle GET requests (for fetching comments) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? null;

    if ($action === 'get_comments') {
        $content_id = $_GET['content_id'] ?? null;

        if ($content_id === null) {
            echo json_encode(['success' => false, 'message' => 'Content ID is required to fetch comments.']);
            exit();
        }

        $comments = [];
        // Fetch comments from tbl_feedback, including feedback_id and student_id
        // These IDs are crucial for client-side JavaScript to handle edits/deletes
        $stmt = $conn->prepare("
            SELECT
                tf.feedback_id,
                tf.comment AS comment_text,
                tf.feedback_date AS comment_date,
                tf.student_id,
                ts.student_name
            FROM tbl_feedback tf
            JOIN tbl_student ts ON tf.student_id = ts.student_id
            WHERE tf.content_id = ? AND tf.comment IS NOT NULL AND tf.comment != ''
            ORDER BY tf.feedback_date DESC
        ");
        $stmt->bind_param("i", $content_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $comments[] = $row;
        }

        $stmt->close();
        echo json_encode(['success' => true, 'comments' => $comments]);
        exit();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid GET action specified.']);
        exit();
    }
}

// --- Handle POST requests (for adding, editing, and deleting comments) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;

    // Restrict POST actions to students only (or staff if they can comment)
   // Allow admins and staff to moderate, but only students to add/edit their own
$allowed_moderators = ['admin', 'staff'];
$is_moderator = in_array($usertype, $allowed_moderators);

// Block users who are not students AND not moderators from adding/editing
if (($action === 'add_comment' || $action === 'edit_comment') && $usertype !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Only students can add or edit comments.']);
    exit();
}

    $content_id = $_POST['content_id'] ?? null;

    if ($content_id === null) {
        echo json_encode(['success' => false, 'message' => 'Content ID is required for this action.']);
        exit();
    }

    // Start a database transaction for atomicity (all or nothing)
    $conn->begin_transaction();

    try {
        if ($action === 'add_comment' || $action === 'edit_comment') {
            $comment_text = $_POST['comment_text'] ?? null;
            $feedback_id = $_POST['feedback_id'] ?? null; // Will be set for edits

            if (trim($comment_text) === '') {
                throw new Exception('Comment text cannot be empty.');
            }

            $is_new_comment = false; // Flag to indicate if a new comment record was created

            if ($feedback_id) { // This is an existing comment being edited
                // Crucial: Verify the user owns this feedback_id for this content
                $stmt_check_ownership = $conn->prepare("SELECT COUNT(*) FROM tbl_feedback WHERE feedback_id = ? AND content_id = ? AND student_id = ? AND comment IS NOT NULL AND comment != ''");
                $stmt_check_ownership->bind_param("iii", $feedback_id, $content_id, $student_id);
                $stmt_check_ownership->execute();
                $stmt_check_ownership->bind_result($owner_count);
                $stmt_check_ownership->fetch();
                $stmt_check_ownership->close();

                if ($owner_count === 0) {
                    throw new Exception("You do not have permission to edit this comment, or the comment does not exist.");
                }

                // Update the existing feedback record
                $stmt_update_comment = $conn->prepare("UPDATE tbl_feedback SET comment = ?, feedback_date = CURRENT_TIMESTAMP WHERE feedback_id = ?");
                $stmt_update_comment->bind_param("si", $comment_text, $feedback_id);
                $stmt_update_comment->execute();
                $stmt_update_comment->close();

            } else { // This is a new comment submission
                // Check if a feedback entry already exists for this student and content (e.g., if they only upvoted before)
                $stmt_check_existing_feedback = $conn->prepare("SELECT feedback_id FROM tbl_feedback WHERE content_id = ? AND student_id = ?");
                $stmt_check_existing_feedback->bind_param("ii", $content_id, $student_id);
                $stmt_check_existing_feedback->execute();
                $result_existing_feedback = $stmt_check_existing_feedback->get_result();
                $existing_feedback_data = $result_existing_feedback->fetch_assoc();
                $stmt_check_existing_feedback->close();

                if ($existing_feedback_data) {
                    // Feedback entry exists, update it with the new comment
                    $feedback_id = $existing_feedback_data['feedback_id'];
                    $stmt_update_existing = $conn->prepare("UPDATE tbl_feedback SET comment = ?, feedback_date = CURRENT_TIMESTAMP WHERE feedback_id = ?");
                    $stmt_update_existing->bind_param("si", $comment_text, $feedback_id);
                    $stmt_update_existing->execute();
                    $stmt_update_existing->close();
                } else {
                    // No feedback entry exists, insert a new one
                    $is_new_comment = true;
                    $stmt_insert_comment = $conn->prepare("INSERT INTO tbl_feedback (content_id, student_id, comment, feedback_date) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
                    $stmt_insert_comment->bind_param("iis", $content_id, $student_id, $comment_text);
                    $stmt_insert_comment->execute();
                    $feedback_id = $conn->insert_id; // Get the ID of the newly inserted comment
                    $stmt_insert_comment->close();
                }
            }

            // Recalculate comments_count in tbl_content based on all non-NULL/non-empty comments in tbl_feedback
            $stmt_recount_comments = $conn->prepare("
                UPDATE tbl_content tc
                SET tc.comments_count = (SELECT COUNT(*) FROM tbl_feedback tf WHERE tf.content_id = tc.content_id AND tf.comment IS NOT NULL AND tf.comment != '')
                WHERE tc.content_id = ?
            ");
            $stmt_recount_comments->bind_param("i", $content_id);
            $stmt_recount_comments->execute();
            $stmt_recount_comments->close();

            // Get the new comment count to return to the frontend
            $stmt_get_count = $conn->prepare("SELECT comments_count FROM tbl_content WHERE content_id = ?");
            $stmt_get_count->bind_param("i", $content_id);
            $stmt_get_count->execute();
            $result_count = $stmt_get_count->get_result();
            $row_count = $result_count->fetch_assoc();
            $new_comment_count = $row_count['comments_count'];
            $stmt_get_count->close();

            $conn->commit(); // Commit the transaction
            echo json_encode([
                'success' => true,
                'message' => 'Comment ' . ($is_new_comment ? 'posted' : 'updated') . ' successfully!',
                'new_comment_count' => $new_comment_count,
                'feedback_id' => $feedback_id, // Return feedback_id for client-side handling
                'student_name' => $student_name, // Return student name for immediate display
                'comment_date' => date('Y-m-d H:i:s'), // Return current time for display
                'is_new_comment' => $is_new_comment // Indicates if it was a brand new comment or an update
            ]);
            exit();

     } elseif ($action === 'delete_comment') {
    $feedback_id = $_POST['feedback_id'] ?? null;

    if ($feedback_id === null) {
        throw new Exception("Feedback ID is required for deletion.");
    }

    $can_delete = false;

    // If user is admin or staff, they can delete any comment
    if ($usertype === 'admin' || $usertype === 'staff') {
        $can_delete = true;
    } else {
        // Otherwise, check if the student owns the comment
        $stmt_check_ownership = $conn->prepare("SELECT COUNT(*) FROM tbl_feedback WHERE feedback_id = ? AND content_id = ? AND student_id = ?");
        $stmt_check_ownership->bind_param("iii", $feedback_id, $content_id, $student_id);
        $stmt_check_ownership->execute();
        $stmt_check_ownership->bind_result($owner_count);
        $stmt_check_ownership->fetch();
        $stmt_check_ownership->close();
        if ($owner_count > 0) {
            $can_delete = true;
        }
    }

    if (!$can_delete) {
        throw new Exception("You do not have permission to delete this comment.");
    }

    // Proceed with deletion
    $stmt_delete_comment_data = $conn->prepare("UPDATE tbl_feedback SET comment = NULL, feedback_date = NULL WHERE feedback_id = ?");
    $stmt_delete_comment_data->bind_param("i", $feedback_id);
    $stmt_delete_comment_data->execute();
    $stmt_delete_comment_data->close();
;

            // Recalculate comments_count in tbl_content based on current non-NULL comments
            $stmt_recount_comments_after_delete = $conn->prepare("
                UPDATE tbl_content tc
                SET tc.comments_count = (SELECT COUNT(*) FROM tbl_feedback tf WHERE tf.content_id = tc.content_id AND tf.comment IS NOT NULL AND tf.comment != '')
                WHERE tc.content_id = ?
            ");
            $stmt_recount_comments_after_delete->bind_param("i", $content_id);
            if (!$stmt_recount_comments_after_delete->execute()) {
                throw new mysqli_sql_exception($stmt_recount_comments_after_delete->error);
            }
            $stmt_recount_comments_after_delete->close();

            // Fetch the new comments_count to send back to the frontend
            $stmt_get_new_count = $conn->prepare("SELECT comments_count FROM tbl_content WHERE content_id = ?");
            $stmt_get_new_count->bind_param("i", $content_id);
            $stmt_get_new_count->execute();
            $new_comment_count = $stmt_get_new_count->get_result()->fetch_row()[0];
            $stmt_get_new_count->close();

            $conn->commit(); // Commit the transaction
            echo json_encode([
                'success' => true,
                'message' => 'Comment deleted successfully!',
                'new_comment_count' => $new_comment_count
            ]);
            exit();

        } else {
            throw new Exception("Invalid POST action specified.");
        }
    } catch (Exception $e) {
        $conn->rollback(); // Rollback on any error during transaction
        error_log("Comment API Error: " . $e->getMessage()); // Log detailed error for debugging
        echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
        exit();
    }
}

// If neither GET nor POST conditions are met (e.g., direct access without parameters)
echo json_encode(['success' => false, 'message' => 'Invalid request method or missing action.']);

$conn->close();
?>
