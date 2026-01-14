<?php
session_start();
include 'db_connect.php';

// Security: Ensure only logged-in admins or staff can access this
$allowed_roles = ['admin', 'staff'];
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['usertype']), $allowed_roles)) {
    http_response_code(403); // Forbidden
    echo "<p style='color: red;'>Access Denied.</p>";
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400); // Bad Request
    echo "<p style='color: red;'>Invalid Content ID.</p>";
    exit();
}

$content_id = intval($_GET['id']);
$html_response = '';

// This query now fetches all the details we need to display
$stmt = $conn->prepare("
    SELECT 
        tc.title, tc.contentbody, tc.file_path, tc.submitted_date,
        ts.student_name,
        tct.type_name,
        tcat.description AS category_name
    FROM tbl_content tc
    JOIN tbl_student ts ON tc.student_id = ts.student_id
    JOIN tbl_content_type tct ON tc.type_id = tct.type_id
    JOIN tbl_content_category tcat ON tc.category_id = tcat.category_id
    WHERE tc.content_id = ?
");
$stmt->bind_param("i", $content_id);
$stmt->execute();
$result = $stmt->get_result();

if ($content = $result->fetch_assoc()) {
    // Build the HTML response
    $html_response .= '<h3 style="font-family: \'Playfair Display\', serif; font-size: 2em; color: #2c3e50;">' . htmlspecialchars($content['title']) . '</h3>';
    $html_response .= '<p style="font-style: italic; color: #777; margin-bottom: 20px;">By ' . htmlspecialchars($content['student_name']) . ' in ' . htmlspecialchars($content['category_name']) . '</p>';

    $content_type = strtolower($content['type_name']);

    if ($content_type === 'image' && !empty($content['file_path'])) {
        $html_response .= '<img src="' . htmlspecialchars($content['file_path']) . '" style="max-width: 100%; border-radius: 8px; margin-top: 15px;">';
    } elseif ($content_type === 'video' && !empty($content['file_path'])) {
        $html_response .= '<video controls style="max-width: 100%; border-radius: 8px; margin-top: 15px;"><source src="' . htmlspecialchars($content['file_path']) . '">Your browser does not support this video.</video>';
    } else {
        // For text content
       // For text content
// 1. Remove escaping slashes (this fixes the apostrophe issue)
$clean_text = nl2br($content['contentbody']);

// 2. Make the text safe for HTML
$safe_text = htmlspecialchars($clean_text);

// 3. Convert newlines to <br> tags (this fixes the line break issue)
$formatted_body = stripcslashes($safe_text);

$html_response .= '<div style="margin-top: 15px; white-space: pre-wrap; word-wrap: break-word; line-height: 1.7;">' . $formatted_body . '</div>';    
    }
} else {
    $html_response = "<p style='color: red;'>Content not found.</p>";
}

$stmt->close();
$conn->close();

echo $html_response;
?>