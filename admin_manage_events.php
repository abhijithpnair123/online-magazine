<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is an ADMIN
if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Get the admin's staff_id from their email to log who creates an event
$admin_email = $_SESSION['user_email'];
$admin_staff_id_result = $conn->query("SELECT staff_id FROM tbl_staff WHERE email = '{$admin_email}'");
$admin_staff_id = $admin_staff_id_result->fetch_assoc()['staff_id'];


$successMessage = '';
$errorMessage = '';
$edit_mode = false;
$event_to_edit = ['event_id' => '', 'event_name' => '', 'event_des' => '', 'event_date' => '', 'event_time' => ''];

// --- Handle Add Event ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_event'])) {
    $event_name = $conn->real_escape_string($_POST['event_name']);
    $event_des = $conn->real_escape_string($_POST['event_des']);
    $event_date = $conn->real_escape_string($_POST['event_date']);
    $event_time = !empty($_POST['event_time']) ? $conn->real_escape_string($_POST['event_time']) : null;

    if (!empty($event_name) && !empty($event_date)) {
        $stmt = $conn->prepare("INSERT INTO tbl_events (event_name, event_des, event_date, event_time, staff_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $event_name, $event_des, $event_date, $event_time, $admin_staff_id);
        $stmt->execute();
        $stmt->close();
        $successMessage = "Event added successfully!";
    } else {
        $errorMessage = "Event Name and Date are required.";
    }
}

// --- Handle Update Event (Admin can update ANY event) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_event'])) {
    $event_id = (int)$_POST['event_id'];
    $event_name = $conn->real_escape_string($_POST['event_name']);
    $event_des = $conn->real_escape_string($_POST['event_des']);
    $event_date = $conn->real_escape_string($_POST['event_date']);
    $event_time = !empty($_POST['event_time']) ? $conn->real_escape_string($_POST['event_time']) : null;

    if (!empty($event_name) && !empty($event_date) && $event_id > 0) {
        $stmt = $conn->prepare("UPDATE tbl_events SET event_name = ?, event_des = ?, event_date = ?, event_time = ? WHERE event_id = ?");
        $stmt->bind_param("ssssi", $event_name, $event_des, $event_date, $event_time, $event_id);
        $stmt->execute();
        $stmt->close();
        $successMessage = "Event updated successfully!";
    } else {
        $errorMessage = "Invalid data provided for update.";
    }
}

// --- Handle Delete Event (Admin can delete ANY event) ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['event_id'])) {
    $event_id = (int)$_GET['event_id'];
    $stmt = $conn->prepare("DELETE FROM tbl_events WHERE event_id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $stmt->close();
    $successMessage = "Event deleted successfully.";
}

// --- Handle Edit Mode ---
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['event_id'])) {
    $edit_mode = true;
    $event_id = (int)$_GET['event_id'];
    $stmt = $conn->prepare("SELECT * FROM tbl_events WHERE event_id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $event_to_edit = $result->fetch_assoc();
    }
    $stmt->close();
}

// --- Fetch all events ---
$all_events_result = $conn->query("
    SELECT e.*, s.staff_name 
    FROM tbl_events e 
    JOIN tbl_staff s ON e.staff_id = s.staff_id 
    ORDER BY e.event_date DESC, e.event_time DESC
");

// --- Separate events into Upcoming and Finished ---
$upcoming_events = [];
$finished_events = [];
$now = new DateTime(); // Current date and time

if ($all_events_result->num_rows > 0) {
    while($event = $all_events_result->fetch_assoc()) {
        $event_datetime_str = $event['event_date'] . ' ' . ($event['event_time'] ? $event['event_time'] : '23:59:59');
        $event_datetime = new DateTime($event_datetime_str);
        
        if ($event_datetime < $now) {
            $finished_events[] = $event;
        } else {
            $upcoming_events[] = $event;
        }
    }
}
// Sort upcoming events in ascending order to show the nearest first
usort($upcoming_events, function($a, $b) {
    $datetime_a = $a['event_date'] . ' ' . ($a['event_time'] ?? '00:00:00');
    $datetime_b = $b['event_date'] . ' ' . ($b['event_time'] ?? '00:00:00');
    return strtotime($datetime_a) - strtotime($datetime_b);
});


$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - Admin Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Add this style to fade out finished events */
        .event-finished {
            opacity: 0.65;
            background-color: #f9f9f9;
        }
        .event-finished:hover {
            opacity: 1;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Manage Events</h1>
            <p>Add new events and view upcoming and finished events.</p>

            <?php if ($successMessage): ?><div class="success-message"><?php echo $successMessage; ?></div><?php endif; ?>
            <?php if ($errorMessage): ?><div class="error-message"><?php echo $errorMessage; ?></div><?php endif; ?>

            <div class="card">
                <?php if ($edit_mode): ?>
                    <h2><i class="fas fa-edit"></i> Edit Event</h2>
                    <form action="admin_manage_events.php" method="POST">
                        <input type="hidden" name="event_id" value="<?php echo $event_to_edit['event_id']; ?>">
                        <div class="form-group">
                            <label for="event_name">Event Name:</label>
                            <input type="text" id="event_name" name="event_name" value="<?php echo htmlspecialchars($event_to_edit['event_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="event_des">Event Description:</label>
                            <textarea id="event_des" name="event_des" rows="4"><?php echo htmlspecialchars($event_to_edit['event_des']); ?></textarea>
                        </div>
                        <div class="form-grid-col-2">
                            <div class="form-group">
                                <label for="event_date">Event Date:</label>
                                <input type="date" id="event_date" name="event_date" value="<?php echo htmlspecialchars($event_to_edit['event_date']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="event_time">Event Time (Optional):</label>
                                <input type="time" id="event_time" name="event_time" value="<?php echo htmlspecialchars($event_to_edit['event_time']); ?>">
                            </div>
                        </div>
                        <button type="submit" name="update_event" class="btn btn-primary">Update Event</button>
                        <a href="admin_manage_events.php" class="btn btn-secondary">Cancel</a>
                    </form>
                <?php else: ?>
                    <h2><i class="fas fa-plus-circle"></i> Add New Event</h2>
                    <form action="admin_manage_events.php" method="POST">
                        <div class="form-group">
                            <label for="event_name">Event Name:</label>
                            <input type="text" id="event_name" name="event_name" placeholder="e.g., Annual Tech Fest" required>
                        </div>
                        <div class="form-group">
                            <label for="event_des">Event Description:</label>
                            <textarea id="event_des" name="event_des" rows="4" placeholder="Enter details about the event..."></textarea>
                        </div>
                        <div class="form-grid-col-2">
                            <div class="form-group">
                                <label for="event_date">Event Date:</label>
                                <input type="date" id="event_date" name="event_date" required>
                            </div>
                            <div class="form-group">
                                <label for="event_time">Event Time (Optional):</label>
                                <input type="time" id="event_time" name="event_time">
                            </div>
                        </div>
                        <button type="submit" name="add_event" class="btn btn-primary">Add Event</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card mt-30">
                <h2><i class="fas fa-calendar-day"></i> Upcoming Events</h2>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Event Name</th><th>Description</th><th>Date</th><th>Time</th><th>Created By</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($upcoming_events)): ?>
                                <?php foreach ($upcoming_events as $event): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($event['event_name']); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($event['event_des'])); ?></td>
                                    <td><?php echo date("D, M j, Y", strtotime($event['event_date'])); ?></td>
                                    <td><?php echo $event['event_time'] ? date("g:i A", strtotime($event['event_time'])) : 'N/A'; ?></td>
                                    <td><?php echo htmlspecialchars($event['staff_name']); ?></td>
                                    <td class="action-buttons">
                                        <a href="admin_manage_events.php?action=edit&event_id=<?php echo $event['event_id']; ?>" class="btn-action approve-btn" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="admin_manage_events.php?action=delete&event_id=<?php echo $event['event_id']; ?>" class="btn-action reject-btn" onclick="return confirm('Are you sure?');" title="Delete"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6">No upcoming events scheduled.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card mt-30">
                <h2><i class="fas fa-calendar-check"></i> Finished Events</h2>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Event Name</th><th>Description</th><th>Date</th><th>Time</th><th>Created By</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($finished_events)): ?>
                                <?php foreach ($finished_events as $event): ?>
                                <tr class="event-finished">
                                    <td><?php echo htmlspecialchars($event['event_name']); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($event['event_des'])); ?></td>
                                    <td><?php echo date("D, M j, Y", strtotime($event['event_date'])); ?></td>
                                    <td><?php echo $event['event_time'] ? date("g:i A", strtotime($event['event_time'])) : 'N/A'; ?></td>
                                    <td><?php echo htmlspecialchars($event['staff_name']); ?></td>
                                    <td class="action-buttons">
                                        <a href="admin_manage_events.php?action=edit&event_id=<?php echo $event['event_id']; ?>" class="btn-action approve-btn" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="admin_manage_events.php?action=delete&event_id=<?php echo $event['event_id']; ?>" class="btn-action reject-btn" onclick="return confirm('Are you sure?');" title="Delete"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6">No finished events found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>