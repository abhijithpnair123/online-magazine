<?php
session_start();
include 'db_connect.php';

// --- Fetch all events from the database ---
$events_result = $conn->query("SELECT event_id, event_name, event_des, event_date, event_time FROM tbl_events");

$events_for_calendar = [];
$now = new DateTime(); // Get current date and time

if ($events_result->num_rows > 0) {
    while($event = $events_result->fetch_assoc()) {
        // Determine if the event is finished or upcoming
        $event_datetime_str = $event['event_date'] . ' ' . ($event['event_time'] ? $event['event_time'] : '23:59:59');
        $event_datetime = new DateTime($event_datetime_str);
        
        $event_color = ($event_datetime < $now) ? '#dc3545' : '#28a745'; // Red for finished, Green for upcoming
        
        // Format the time for the title if it exists
        $time_display = $event['event_time'] ? date("g:i A", strtotime($event['event_time'])) : '';

        $events_for_calendar[] = [
            'title' => $time_display . ' ' . $event['event_name'],
            'start' => $event['event_date'],
            'color' => $event_color,
            'description' => !empty($event['event_des']) ? nl2br(htmlspecialchars($event['event_des'])) : 'No description available.',
            'raw_title' => htmlspecialchars($event['event_name']) // Store original title for modal
        ];
    }
}
$conn->close();

// Convert the PHP array into a JSON string for JavaScript to use
$events_json = json_encode($events_for_calendar);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>College Event Calendar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
     <link rel="stylesheet" href="css/style.css">
    
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>

    <style>
    
        .main-content {
            padding: 20px 30px;
        }
        #calendar {
            max-width: 700px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        /* Style for the calendar events */
        .fc-event {
            cursor: pointer;
            border: none !important;
            padding: 5px;
        }
        /* Style for the modal popup */
        .modal {
            display: none; position: fixed; z-index: 1001; left: 0; top: 0;
            width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6);
        }
        .modal-content {
            background-color:rgb(233, 126, 10); margin: 15% auto; padding: 0;
            border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            width: 90%; max-width: 450px; animation: fadeIn 0.3s;
        }
        @keyframes fadeIn { from {opacity: 0;} to {opacity: 1;} }
        .modal-header {
            padding: 15px 20px; background-color: #34495e; color: white;
            border-top-left-radius: 8px; border-top-right-radius: 8px;
        }
        .modal-header h2 { margin: 0; font-size: 1.2rem; }
        .modal-body { padding: 20px; }
        .close-button { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-button:hover, .close-button:focus { color: black; text-decoration: none; }
    </style>
</head>
<body>
     <?php include 'includes/header.php'; ?>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>College Event Calendar</h1>

            <div id='calendar'></div>
        </div>
    </div>

    <div id="eventModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Event Details</h2>
                <span class="close-button">&times;</span>
            </div>
            <div class="modal-body">
                <p id="modalDescription"></p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var modal = document.getElementById('eventModal');
            var modalTitle = document.getElementById('modalTitle');
            var modalDescription = document.getElementById('modalDescription');
            var closeButton = document.querySelector('.close-button');

            var calendar = new FullCalendar.Calendar(calendarEl, {
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listWeek'
                },
                initialView: 'dayGridMonth',
                  height: 'auto',
                editable: false,
                events: <?php echo $events_json; ?>, // Load events from PHP
                
                // --- Handle event click ---
                eventClick: function(info) {
                    // Populate the modal with event details
                    modalTitle.textContent = info.event.extendedProps.raw_title;
                    modalDescription.innerHTML = info.event.extendedProps.description;
                    
                    // Show the modal
                    modal.style.display = 'block';
                }
            });

            calendar.render();

            // --- Modal close functionality ---
            closeButton.onclick = function() {
                modal.style.display = 'none';
            }
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }
        });
    </script>
</body>
</html>