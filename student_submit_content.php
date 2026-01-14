<?php
// student_submit_content.php
date_default_timezone_set('Asia/Kolkata'); // <-- ADD THIS LINE

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db_connect.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'student') {
    header("Location: index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$successMessage = '';
$errorMessage = '';

// --- Fetch Content Types for Dropdown ---
$content_types = [];
$stmt_types = $conn->prepare("SELECT type_id, type_name FROM tbl_content_type ORDER BY type_name ASC");
$stmt_types->execute();
$result_types = $stmt_types->get_result();
while ($row = $result_types->fetch_assoc()) {
    $content_types[] = $row;
}
$stmt_types->close();

// --- Fetch Categories for Dropdown and competition details, including new dates ---
$categories_for_dropdown = [];
$competition_details = []; // To store details for competition table and JS filtering
$has_competition_category = false; // Flag to check if any competition exists

// We will use this DateTime object for all comparisons in PHP
$current_datetime_php_server = new DateTime(); 

$stmt_categories = $conn->prepare("SELECT category_id, category_name, description, allowed_types, rules, staff_id, submission_start_datetime, submission_end_datetime, voting_start_datetime, voting_end_datetime, winner_announcement_date FROM tbl_content_category ORDER BY category_name ASC");
$stmt_categories->execute();
$result_categories = $stmt_categories->get_result();

while ($row = $result_categories->fetch_assoc()) {
    if ($row['category_name'] === 'general') {
        $categories_for_dropdown[] = [
            'category_id' => $row['category_id'],
            'display_name' => 'General Publishing',
            'category_name_raw' => 'general'
        ];
    } elseif ($row['category_name'] === 'competition') {
        $has_competition_category = true; // Mark that at least one competition exists
        
        // Convert dates to DateTime objects for comparison
        $comp_submission_start = new DateTime($row['submission_start_datetime']);
        $comp_submission_end = new DateTime($row['submission_end_datetime']);
        
        // Determine if the competition is currently open for submission using the PHP server time
        $is_open_for_submission = ($current_datetime_php_server >= $comp_submission_start && $current_datetime_php_server <= $comp_submission_end);

        $competition_details[$row['category_id']] = [
            'description' => $row['description'],
            'allowed_types' => explode(',', $row['allowed_types']),
            'rules' => $row['rules'],
            'staff_id' => $row['staff_id'],
            'submission_start_datetime' => $row['submission_start_datetime'],
            'submission_end_datetime' => $row['submission_end_datetime'],
            'voting_start_datetime' => $row['voting_start_datetime'],
            'voting_end_datetime' => $row['voting_end_datetime'],
            'winner_announcement_date' => $row['winner_announcement_date'],
            'is_open_for_submission' => $is_open_for_submission,
            // Add parsed DateTime objects for debugging output
            'parsed_submission_start' => $comp_submission_start->format('Y-m-d H:i:s A T'),
            'parsed_submission_end' => $comp_submission_end->format('Y-m-d H:i:s A T')
        ];
    }
}
$stmt_categories->close();

// Add a single "Competitions" option to the dropdown if any competition exists
if ($has_competition_category) {
    $categories_for_dropdown[] = [
        'category_id' => 'comp_group', 
        'display_name' => 'Competitions',
        'category_name_raw' => 'competition' 
    ];
}
// Sort categories for dropdown (optional, but good for UX)
usort($categories_for_dropdown, function($a, $b) {
    return strcmp($a['display_name'], $b['display_name']);
});

// Assign to $categories for use in HTML
$categories = $categories_for_dropdown;


// --- Handle Content Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_content'])) {
    $type_id = $conn->real_escape_string($_POST['type_id']);
    $category_id = $conn->real_escape_string($_POST['category_id']); 
    $title = $conn->real_escape_string($_POST['title']);
    $contentbody = $_POST['contentbody']; 

    // IMPORTANT FIX: Convert literal \n and \r to actual newlines
    $contentbody = str_replace(array('\\r\\n', '\\n', '\\r'), "\n", $contentbody);
    $contentbody = $conn->real_escape_string($contentbody); 
    
    $assigned_staff_id = !empty($_POST['assigned_staff_id']) ? $conn->real_escape_string($_POST['assigned_staff_id']) : NULL; 
    $file_path = NULL; 

    // Basic validation
    if (empty($type_id) || empty($category_id) || empty($title)) {
        $errorMessage = "Content Type, Category, and Title are required.";
    } else {
        // --- NEW: Competition Submission Period Validation ---
        $is_competition_submission = false;
        if (isset($competition_details[$category_id])) {
            $is_competition_submission = true;
            $comp_data = $competition_details[$category_id];
            $submission_start = new DateTime($comp_data['submission_start_datetime']);
            $submission_end = new DateTime($comp_data['submission_end_datetime']);
            // Use the same $current_datetime_php_server for consistency
            $current_time = $current_datetime_php_server; 

            if ($current_time < $submission_start) {
                $errorMessage = "This competition is not yet open for submissions. Submissions open on " . date('Y-m-d H:i A', $submission_start->getTimestamp()) . ".";
            } elseif ($current_time > $submission_end) {
                $errorMessage = "The submission period for this competition has ended. Submissions closed on " . date('Y-m-d H:i A', $submission_end->getTimestamp()) . ".";
            }
        }
        // --- END NEW VALIDATION ---

        if (empty($errorMessage)) { // Proceed only if no validation errors
            // Handle file upload if a file is provided
            if (isset($_FILES['content_file']) && $_FILES['content_file']['error'] === UPLOAD_ERR_OK) {
   $selected_type_name = '';
                // Find the name of the type the user selected (e.g., 'image', 'video')
                foreach ($content_types as $type) {
                    if ($type['type_id'] == $type_id) {
                        $selected_type_name = strtolower($type['type_name']);
                        break;
                    }
                }

                // Get the actual MIME type of the uploaded file
                $uploaded_file_mime_type = mime_content_type($_FILES['content_file']['tmp_name']);

                if ($selected_type_name === 'image' && strpos($uploaded_file_mime_type, 'image/') !== 0) {
                    $errorMessage = "Invalid file type. You selected 'Image', but uploaded a different file type.";
                } elseif ($selected_type_name === 'video' && strpos($uploaded_file_mime_type, 'video/') !== 0) {
                    $errorMessage = "Invalid file type. You selected 'Video', but uploaded a different file type.";
                }

                $target_dir = "uploads/student_content/"; 
                $file_extension = pathinfo($_FILES['content_file']['name'], PATHINFO_EXTENSION);
                $new_file_name = uniqid('content_') . '.' . $file_extension;
                $target_file = $target_dir . $new_file_name;

                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0777, true); 
                }

                if (move_uploaded_file($_FILES['content_file']['tmp_name'], $target_file)) {
                    $file_path = $target_file;
                } else {
                    $errorMessage = "Error uploading file. Please try again.";
                }
            }

            if (empty($errorMessage)) { 
                // Insert into tbl_content
                $stmt_content = $conn->prepare("INSERT INTO tbl_content (student_id, type_id, category_id, title, contentbody, file_path) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_content->bind_param("iiisss", $student_id, $type_id, $category_id, $title, $contentbody, $file_path);

                if ($stmt_content->execute()) {
                    $new_content_id = $conn->insert_id; 

                    // Insert into tbl_content_approval with pending status
                    $stmt_approval = $conn->prepare("INSERT INTO tbl_content_approval (content_id, staff_id, status, remarks) VALUES (?, ?, 'pending', NULL)");
                    $stmt_approval->bind_param("ii", $new_content_id, $assigned_staff_id); 

                    if ($stmt_approval->execute()) {
                        $successMessage = "Your content has been submitted successfully and is awaiting approval!";
                        // Clear POST data to reset form
                        $_POST = array(); 
                        $_FILES = array();
                    } else {
                        $errorMessage = "Content submitted, but failed to create approval entry: " . $stmt_approval->error;
                        $conn->query("DELETE FROM tbl_content WHERE content_id = $new_content_id"); 
                    }
                    $stmt_approval->close();
                } else {
                    $errorMessage = "Error submitting content: " . $stmt_content->error;
                }
                $stmt_content->close();
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Content - Student Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
   
    <?php include 'includes/header.php'; ?>

    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Submit New Content</h1>
            <p>Fill out the form below to submit your article, image, or video.</p>

            <?php if ($successMessage): ?>
                <div class="success-message"><?php echo $successMessage; ?></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="error-message"><?php echo $errorMessage; ?></div>
            <?php endif; ?>

            <div class="card">
                <h2>Content Submission Form</h2>
                <form action="student_submit_content.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="category_id_select">Category:</label>
                        <select id="category_id_select" name="temp_category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option
                                    value="<?php echo htmlspecialchars($category['category_id']); ?>"
                                    data-category-name-raw="<?php echo htmlspecialchars($category['category_name_raw']); ?>"
                                    <?php echo (isset($_POST['temp_category_id']) && $_POST['temp_category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['display_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Competition Selection Table (Initially hidden) -->
                    <div id="competition-selection-section" class="form-group" style="display: none;">
                        <h3>Select Competition:</h3>
                        <?php if (empty($competition_details)): ?>
                            <p>No active competitions found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="data-table competition-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Competition Name</th>
                                            <th>Submission Period</th>
                                            <th>Voting Period</th>
                                            <th>Allowed Types</th>
                                            <th>Rules</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
   <tbody>
    <?php foreach ($competition_details as $comp_id => $details): ?>
    <tr 
        data-comp-id="<?php echo htmlspecialchars($comp_id); ?>"
        data-allowed-types="<?php echo htmlspecialchars(implode(',', $details['allowed_types'])); ?>"
        data-staff-id="<?php echo htmlspecialchars($details['staff_id']); ?>"
        data-submission-open="<?php echo $details['is_open_for_submission'] ? 'true' : 'false'; ?>"
        class="<?php echo !$details['is_open_for_submission'] ? 'disabled-row' : ''; ?>"
    >
        <td><?php echo htmlspecialchars($comp_id); ?></td>
        <td><?php echo htmlspecialchars($details['description']); ?></td>
        <td><?php echo htmlspecialchars($details['submission_start_datetime']) . ' to ' . htmlspecialchars($details['submission_end_datetime']); ?></td>
        <td><?php echo htmlspecialchars($details['voting_start_datetime']) . ' to ' . htmlspecialchars($details['voting_end_datetime']); ?></td>
        <td><?php echo htmlspecialchars(implode(', ', $details['allowed_types'])); ?></td>
        <td><?php echo htmlspecialchars(substr($details['rules'], 0, 70)) . (strlen($details['rules']) > 70 ? '...' : ''); ?></td>
        <td>
            <?php if ($details['is_open_for_submission']): ?>
                <span style="color: green; font-weight: bold;">Open</span>
            <?php elseif ($current_datetime_php_server < new DateTime($details['submission_start_datetime'])): ?>
                <span style="color: orange; font-weight: bold;">Upcoming</span>
            <?php else: ?>
                <span style="color: red; font-weight: bold;">Closed</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
</tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        <p class="selected-comp-info" style="margin-top: 10px; font-style: italic; color: #555;">
                            No competition selected.
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="type_id">Content Type:</label>
                        <select id="type_id" name="type_id" required disabled>
                            <option value="">Select Type</option>
                            <!-- Options will be dynamically loaded/filtered by JavaScript -->
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="title">Title:</label>
                        <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                    </div>

                    <!-- Content Body (Text) input - Initially hidden -->
                    <div id="contentbody-group" class="form-group" style="display: none;">
                        <label for="contentbody">Content Body:</label>
                        <textarea id="contentbody" name="contentbody" rows="8"><?php echo htmlspecialchars($_POST['contentbody'] ?? ''); ?></textarea>
                        <small>Enter your text content here.</small>
                    </div>

                    <!-- File Upload input - Initially hidden -->
                    <div id="contentfile-group" class="form-group" style="display: none;">
                        <label for="content_file">Upload File:</label>
                        <input type="file" id="content_file" name="content_file">
                        <small>Upload an image or video file.</small>
                    </div>

                    <!-- Hidden input to store the assigned staff ID -->
                    <input type="hidden" id="assigned_staff_id" name="assigned_staff_id" value="">
                    <!-- Hidden input to store the actual category ID for submission -->
                    <input type="hidden" id="final_category_id" name="category_id" value="">
                    
                    <button type="submit" name="submit_content" class="btn btn-primary">Submit Content</button>
                </form>
            </div>
        </div>
    </div>

    <style>
        /* Add specific styles for the competition table and selected row */
        .competition-table tbody tr {
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .competition-table tbody tr:hover {
            background-color: #e0f7fa; /* Light blue on hover */
        }
        .competition-table tbody tr.selected-row {
            background-color: #b2ebf2; /* A slightly darker blue for selected */
            font-weight: bold;
        }
        .selected-comp-info {
            color: #3498db !important; /* Ensure visibility */
            font-weight: 600;
        }
        .competition-table tbody tr.disabled-row {
    cursor: not-allowed;
    background-color: #f2f2f2;
    color: #999;
}
.competition-table tbody tr.disabled-row:hover {
    background-color: #f2f2f2; /* Prevent hover effect */
}
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const categorySelect = document.getElementById('category_id_select');
            const finalCategoryIdInput = document.getElementById('final_category_id');
            const competitionSelectionSection = document.getElementById('competition-selection-section');
            const competitionTable = document.querySelector('.competition-table');
            const typeSelect = document.getElementById('type_id');
            const titleInput = document.getElementById('title');
            const contentbodyGroup = document.getElementById('contentbody-group');
            const contentbodyTextarea = document.getElementById('contentbody');
            const contentfileGroup = document.getElementById('contentfile-group');
            const contentfileInput = document.getElementById('content_file');
            const assignedStaffIdInput = document.getElementById('assigned_staff_id');
            const selectedCompInfo = document.querySelector('.selected-comp-info');
            const submitButton = document.querySelector('button[name="submit_content"]');

            const allContentTypes = <?php echo json_encode($content_types); ?>;
            const competitionDetails = <?php echo json_encode($competition_details); ?>;
            // The current datetime below is for client-side JS logic, not the PHP validation shown in the table
            const currentDatetime = new Date(); 

            let selectedCompetitionId = null;

            function populateTypeSelect(typesToAllow = null) {
                typeSelect.innerHTML = '<option value="">Select Type</option>';
                const fragment = document.createDocumentFragment();

                allContentTypes.forEach(type => {
                    const typeNameLower = type.type_name.toLowerCase();
                    if (typesToAllow === null || typesToAllow.includes(typeNameLower)) {
                        const option = document.createElement('option');
                        option.value = type.type_id;
                        option.textContent = type.type_name;
                        fragment.appendChild(option);
                    }
                });
                typeSelect.appendChild(fragment);
                typeSelect.disabled = (typesToAllow !== null && typesToAllow.length === 0);
            }

            function toggleContentInputs() {
                const selectedTypeOption = typeSelect.options[typeSelect.selectedIndex];
                const selectedTypeName = selectedTypeOption ? selectedTypeOption.textContent.toLowerCase() : '';

                contentbodyGroup.style.display = 'none';
                contentbodyTextarea.removeAttribute('required');
                contentfileGroup.style.display = 'none';
                contentfileInput.removeAttribute('required');

                if (selectedTypeName === 'text') {
                    contentbodyGroup.style.display = 'block';
                    contentbodyTextarea.setAttribute('required', 'required');
                } else if (selectedTypeName === 'image' || selectedTypeName === 'video') {
                    contentfileGroup.style.display = 'block';
                    contentfileInput.setAttribute('required', 'required');
                }
            }

            function handleCategoryChange() {
                const selectedOption = categorySelect.options[categorySelect.selectedIndex];
                const categoryRawName = selectedOption.getAttribute('data-category-name-raw');
                const categoryIdFromDropdown = selectedOption.value;

                // Reset states
                typeSelect.value = '';
                assignedStaffIdInput.value = '';
                selectedCompetitionId = null;
                selectedCompInfo.textContent = 'No competition selected.';
                selectedCompInfo.style.color = '#555'; // Reset color
                toggleContentInputs();
                typeSelect.disabled = true;
                finalCategoryIdInput.value = '';
                submitButton.disabled = true; // Disable submit button by default for safety

                if (competitionTable) {
                    competitionTable.querySelectorAll('tbody tr').forEach(row => {
                        row.classList.remove('selected-row');
                    });
                }

                if (categoryRawName === 'competition') {
                    competitionSelectionSection.style.display = 'block';
                    // Re-evaluate submit button state based on selected competition if applicable
                    // This logic will be handled by the click event on the competition table rows
                } else {
                    competitionSelectionSection.style.display = 'none';
                    populateTypeSelect(null);
                    typeSelect.disabled = false;
                    finalCategoryIdInput.value = categoryIdFromDropdown;
                    submitButton.disabled = false; // Enable for general content
                }
            }

            if (competitionTable) {
               competitionTable.querySelectorAll('tbody tr').forEach(row => {
    row.addEventListener('click', function() {
        // PREVENT CLICKING ON DISABLED (CLOSED/UPCOMING) ROWS
        if (this.classList.contains('disabled-row')) {
            return; // Stop the function here
        }

        competitionTable.querySelectorAll('tbody tr').forEach(r => r.classList.remove('selected-row'));
        this.classList.add('selected-row');

        // The rest of your existing click logic goes here...
        selectedCompetitionId = this.getAttribute('data-comp-id');
        const allowedTypesString = this.getAttribute('data-allowed-types');
        const staffId = this.getAttribute('data-staff-id');
        const compName = this.children[1].textContent;
        const submissionOpen = this.getAttribute('data-submission-open') === 'true'; 

        assignedStaffIdInput.value = staffId;
        selectedCompInfo.textContent = `Selected: ${compName}`;
        finalCategoryIdInput.value = selectedCompetitionId;

        const allowedTypesArray = allowedTypesString ? allowedTypesString.split(',') : [];
        populateTypeSelect(allowedTypesArray);
        typeSelect.disabled = false;

                        // Enable/disable submit button based on competition submission status from PHP
                        submitButton.disabled = !submissionOpen;
                        if (!submissionOpen) {
                            selectedCompInfo.textContent += ' (Submissions Closed or Upcoming)';
                            selectedCompInfo.style.color = 'red';
                        } else {
                             selectedCompInfo.style.color = '#3498db'; // Reset to a success color
                        }

                        if (allowedTypesArray.length === 1 && allContentTypes.length > 0) {
                            const allowedTypeId = allContentTypes.find(type => type.type_name.toLowerCase() === allowedTypesArray[0])?.type_id;
                            if (allowedTypeId) {
                                typeSelect.value = allowedTypeId;
                                toggleContentInputs();
                            }
                        } else {
                            typeSelect.value = '';
                            toggleContentInputs();
                        }
                    });
                });
            }

            categorySelect.addEventListener('change', handleCategoryChange);
            typeSelect.addEventListener('change', toggleContentInputs);

            // Initial call to set up the form based on default/previous selection
            handleCategoryChange();
            if (categorySelect.value) {
                const selectedOption = categorySelect.options[categorySelect.selectedIndex];
                const categoryRawName = selectedOption.getAttribute('data-category-name-raw');
                if (categoryRawName !== 'competition') {
                    populateTypeSelect(null);
                    typeSelect.disabled = false;
                    toggleContentInputs();
                    submitButton.disabled = false; // For general content, always enable if a type is selected
                }
            }

            // Restore previous selection if form was submitted and failed validation
            <?php if (isset($_POST['temp_category_id']) && $_POST['temp_category_id'] === 'comp_group' && isset($_POST['category_id'])): ?>
                const prevSelectedCompId = '<?php echo htmlspecialchars($_POST["category_id"]); ?>';
                const prevSelectedRow = document.querySelector(`tr[data-comp-id="${prevSelectedCompId}"]`);
                if (prevSelectedRow) {
                    prevSelectedRow.click(); // Simulate click to restore state
                    // The error message from PHP will already be displayed
                }
            <?php endif; ?>
        });
    </script>
</body>
</html>
