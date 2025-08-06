<?php
include 'db_connect.php';

$registrationError = '';
$registrationSuccess = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_student'])) {
    $student_name = $conn->real_escape_string($_POST['student_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $stu_phn = $conn->real_escape_string($_POST['stu_phn']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $dept_id = (int)$_POST['dept_id'];
    $program_id = (int)$_POST['program_id'];
    $gender = $conn->real_escape_string($_POST['gender']);

    if ($password !== $confirm_password) {
        $registrationError = "Passwords do not match.";
    } else {
        // Hash the password securely
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Check if email already exists
        $stmt_check_email = $conn->prepare("SELECT email FROM tbl_student WHERE email = ?");
        $stmt_check_email->bind_param("s", $email);
        $stmt_check_email->execute();
        $stmt_check_email->store_result();

        if ($stmt_check_email->num_rows > 0) {
            $registrationError = "Email already registered.";
        } else {
            // Insert into tbl_student
            $stmt = $conn->prepare("INSERT INTO tbl_student (student_name, dept_id, program_id, gender, email, stu_phn, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("siissss", $student_name, $dept_id, $program_id, $gender, $email, $stu_phn, $hashed_password);

            if ($stmt->execute()) {
                $registrationSuccess = "Registration successful! You can now login.";
                // Clear form fields after successful registration
                $_POST = array(); // Clear all POST data
            } else {
                $registrationError = "Error during registration: " . $stmt->error;
            }
            $stmt->close();
        }
        $stmt_check_email->close();
    }
}

// Fetch departments and programs for dropdowns
$departments = [];
$programs = [];

$result_dept = $conn->query("SELECT dept_id, dept_name FROM tbl_department");
while ($row = $result_dept->fetch_assoc()) {
    $departments[] = $row;
}

$result_program = $conn->query("SELECT program_id, program_name, dept_id FROM tbl_program");
while ($row = $result_program->fetch_assoc()) {
    $programs[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .registration-container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); width: 450px; text-align: center; }
        .registration-container h2 { margin-bottom: 20px; color: #333; }
        .form-group { margin-bottom: 15px; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; color: #555; }
        .form-group input[type="text"], .form-group input[type="email"], .form-group input[type="password"],
        .form-group select {
            width: calc(100% - 22px);
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group input[type="radio"] { margin-right: 5px; }
        .btn-register { background-color: #28a745; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-size: 16px; transition: background-color 0.3s ease; }
        .btn-register:hover { background-color: #218838; }
        .login-link { margin-top: 15px; font-size: 14px; }
        .login-link a { color: #007bff; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }
        .error { color: red; margin-bottom: 15px; }
        .success { color: green; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="registration-container">
        <h2>Student Registration</h2>

        <?php if ($registrationError): ?>
            <p class="error"><?php echo $registrationError; ?></p>
        <?php endif; ?>
        <?php if ($registrationSuccess): ?>
            <p class="success"><?php echo $registrationSuccess; ?></p>
        <?php endif; ?>

        <form action="student_registration.php" method="POST">
            <div class="form-group">
                <label for="student_name">Full Name:</label>
                <input type="text" id="student_name" name="student_name" value="<?php echo htmlspecialchars($_POST['student_name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="stu_phn">Phone Number:</label>
                <input type="text" id="stu_phn" name="stu_phn" value="<?php echo htmlspecialchars($_POST['stu_phn'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <div class="form-group">
                <label for="dept_id">Department:</label>
                <select id="dept_id" name="dept_id" required>
                    <option value="">Select Department</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['dept_id']; ?>" <?php echo (isset($_POST['dept_id']) && $_POST['dept_id'] == $dept['dept_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['dept_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="program_id">Program:</label>
                <select id="program_id" name="program_id" required>
                    <option value="">Select Program</option>
                    <?php foreach ($programs as $program): ?>
                        <option value="<?php echo $program['program_id']; ?>" data-dept="<?php echo $program['dept_id']; ?>" <?php echo (isset($_POST['program_id']) && $_POST['program_id'] == $program['program_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($program['program_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Gender:</label><br>
                <input type="radio" id="male" name="gender" value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'male') ? 'checked' : ''; ?> required>
                <label for="male">Male</label>
                <input type="radio" id="female" name="gender" value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'female') ? 'checked' : ''; ?> required>
                <label for="female">Female</label>
                <input type="radio" id="other" name="gender" value="other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'other') ? 'checked' : ''; ?> required>
                <label for="other">Other</label>
            </div>
            <button type="submit" name="register_student" class="btn-register">Register</button>
        </form>
        <div class="login-link">
            Already have an account? <a href="index.php">Login Here</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const deptSelect = document.getElementById('dept_id');
            const programSelect = document.getElementById('program_id');
            const allPrograms = Array.from(programSelect.options); // Keep all options

            function filterPrograms() {
                const selectedDeptId = deptSelect.value;
                programSelect.innerHTML = '<option value="">Select Program</option>'; // Clear existing options

                allPrograms.forEach(option => {
                    if (option.value === "") return; // Skip the "Select Program" option
                    if (option.dataset.dept === selectedDeptId || selectedDeptId === "") {
                        programSelect.appendChild(option.cloneNode(true)); // Add back relevant options
                    }
                });
            }

            deptSelect.addEventListener('change', filterPrograms);

            // Initial filter when page loads, useful if there's a pre-selected department
            filterPrograms();
        });
    </script>
</body>
</html>