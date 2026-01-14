<?php
include 'db_connect.php';
header('Content-Type: application/json');

$competitions = [];
$sql = "SELECT competition_id, submission_start_time, submission_end_time FROM tbl_competitions WHERE submission_end_time >= NOW()";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $start_time = strtotime($row['submission_start_time']);
        $end_time = strtotime($row['submission_end_time']);
        $current_time = time();
        $status = 'Upcoming';
        if ($current_time >= $start_time && $current_time <= $end_time) {
            $status = 'Open';
        } else if ($current_time > $end_time) {
            $status = 'Closed';
        }
        $competitions[] = ['id' => $row['competition_id'], 'status' => $status];
    }
}
echo json_encode(['success' => true, 'competitions' => $competitions]);
$conn->close();
?>