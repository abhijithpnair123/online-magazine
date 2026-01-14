<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'staff') {
    header("Location: index.php");
    exit();
}

$competition_id = $_GET['id'] ?? null;
$success = false;
$message = '';

if ($competition_id === null) {
    $message = "No competition ID provided for deletion.";
} else {
    $conn->begin_transaction(); // Start transaction

    try {
        // Option 1: Update associated student content to a default category (e.g., 'general')
        // This is safer if you want to preserve student submissions but detach them from the competition.
        // You would need to know the category_id of your 'general' category. Let's assume it's ID 1.
        // Or set to NULL if category_id in tbl_content is nullable.
        
        // Example: Set category_id of associated content to NULL
        $stmt_update_content = $conn->prepare("UPDATE tbl_content SET category_id = NULL WHERE category_id = ?");
        $stmt_update_content->bind_param("i", $competition_id);
        $stmt_update_content->execute();
        $stmt_update_content->close();

        // Example: Delete associated entries from tbl_content_approval
        // This is important as content_id in tbl_content_approval is a foreign key to tbl_content.
        // If content is set to NULL above, these might become orphaned.
        // It's usually better to delete approval entries if the competition/content is gone.
        $stmt_delete_approvals = $conn->prepare("DELETE FROM tbl_content_approval WHERE content_id IN (SELECT content_id FROM tbl_content WHERE category_id IS NULL AND category_id = ?)");
        $stmt_delete_approvals->bind_param("i", $competition_id);
        $stmt_delete_approvals->execute();
        $stmt_delete_approvals->close();


        // Option 2 (Less Safe): Delete associated student content entirely
        // This will remove all student submissions linked to this competition.
        // $stmt_delete_content = $conn->prepare("DELETE FROM tbl_content WHERE category_id = ?");
        // $stmt_delete_content->bind_param("i", $competition_id);
        // $stmt_delete_content->execute();
        // $stmt_delete_content->close();


        // Finally, delete the competition entry from tbl_content_category
        $stmt_delete_comp = $conn->prepare("DELETE FROM tbl_content_category WHERE category_id = ? AND category_name = 'competition'");
        $stmt_delete_comp->bind_param("i", $competition_id);
        
        if ($stmt_delete_comp->execute()) {
            if ($stmt_delete_comp->affected_rows > 0) {
                $success = true;
                $message = "Competition deleted successfully!";
                $conn->commit(); // Commit transaction
            } else {
                $message = "Competition not found or could not be deleted.";
                $conn->rollback(); // Rollback if no rows affected
            }
        } else {
            throw new Exception("Error deleting competition: " . $stmt_delete_comp->error);
        }
        $stmt_delete_comp->close();

    } catch (Exception $e) {
        $conn->rollback(); // Rollback on error
        $message = "Failed to delete competition: " . $e->getMessage();
    }
}

$conn->close();

// Store message in session and redirect back to manage competitions page
$_SESSION['success_message'] = $success ? $message : '';
$_SESSION['error_message'] = !$success ? $message : '';

header("Location: staff_combined_competitions.php?view=manage");
exit();
?>