<?php
session_start();
include 'db_connect.php';

// Ensure a staff member is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'staff') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

// Ensure content ID is provided
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Content ID not provided']);
    exit();
}

$content_id = (int)$_GET['id'];
$response = [];

// Prepare the query to fetch content details along with student and type information
$stmt = $conn->prepare("
    SELECT
        tc.title,
        tc.contentbody,
        tc.file_path,
        tc.submitted_date,
        ts.student_name,
        tct.type_name
    FROM tbl_content tc
    JOIN tbl_student ts ON tc.student_id = ts.student_id
    JOIN tbl_content_type tct ON tc.type_id = tct.type_id
    WHERE tc.content_id = ?
");
$stmt->bind_param("i", $content_id);

if ($stmt->execute()) {
    $result = $stmt->get_result();
    if ($content = $result->fetch_assoc()) {
        $response = $content;
    } else {
        http_response_code(404);
        $response['error'] = 'Content not found.';
    }
} else {
    http_response_code(500);
    $response['error'] = 'Database query failed.';
}

$stmt->close();
$conn->close();

// Return the data as a JSON object
header('Content-Type: application/json');
echo json_encode($response);
?>