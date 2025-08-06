<?php
session_start(); // Start the session at the very beginning
include 'db_connect.php';

$loginError = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    $selected_usertype = $_POST['user_type_selected'];

    $user_id = null;
    $user_email = null;
    $actual_usertype = null;
    $user_name = null;

    if ($selected_usertype === 'admin' || $selected_usertype === 'staff') {
        $stmt = $conn->prepare("SELECT login_id, email, password, usertype FROM tbl_login WHERE email = ? AND usertype = ?");
        $stmt->bind_param("ss", $email, $selected_usertype);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $user_id = $user['login_id'];
                $user_email = $user['email'];
                $actual_usertype = $user['usertype'];
                
                // Fetch name for staff/admin from tbl_staff
                $stmt_staff_name = $conn->prepare("SELECT staff_name FROM tbl_staff WHERE email = ?");
                $stmt_staff_name->bind_param("s", $user_email);
                $stmt_staff_name->execute();
                $result_staff_name = $stmt_staff_name->get_result();
                if ($staff_row = $result_staff_name->fetch_assoc()) {
                    $user_name = $staff_row['staff_name'];
                }
                $stmt_staff_name->close();

            } else {
                $loginError = "Invalid email or password for " . ucfirst($selected_usertype) . ".";
            }
        } else {
            $loginError = "No " . ucfirst($selected_usertype) . " found with that email or invalid credentials.";
        }
        $stmt->close();
    } elseif ($selected_usertype === 'student') {
        $stmt_student = $conn->prepare("SELECT student_id, student_name, email, password FROM tbl_student WHERE email = ?");
        $stmt_student->bind_param("s", $email);
        $stmt_student->execute();
        $result_student = $stmt_student->get_result();

        if ($result_student->num_rows == 1) {
            $student = $result_student->fetch_assoc();
            if (password_verify($password, $student['password'])) {
                $user_id = $student['student_id'];
                $user_email = $student['email'];
                $actual_usertype = 'student';
                $user_name = $student['student_name'];
            } else {
                $loginError = "Invalid email or password for Student.";
            }
        } else {
            $loginError = "No Student found with that email or invalid credentials.";
        }
        $stmt_student->close();
    } else {
        $loginError = "Please select a valid user type.";
    }

    if ($user_id !== null) {
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_email'] = $user_email;
        $_SESSION['usertype'] = $actual_usertype;
        $_SESSION['user_name'] = $user_name;

        if ($actual_usertype === 'admin') {
            header("Location: admin_dashboard.php");
            exit();
        } elseif ($actual_usertype === 'staff') {
            header("Location: staff_dashboard.php");
            exit();
        } elseif ($actual_usertype === 'student') {
            header("Location: student_dashboard.php");
            exit();
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
    <title>Online Magazine Portal Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            /* Changed to align content to the end (right) */
            justify-content: flex-end; /* This moves the login box to the right */
            align-items: center;
            min-height: 100vh;
            color: #333;
            background-image: url('images/bg.png'); /* Ensure this path is correct and matches your image file (e.g., .jpg or .png) */
            background-size: cover; /* Ensures image fills the entire window */
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
            /* Added padding to push the login box from the right edge */
            padding-right: 5vw; /* 5% of viewport width from the right */
            padding-left: 0; /* Remove left padding if it was present for the left-aligned version */
        }

        /* Overlay to make text readable over the background image */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4); /* Dark semi-transparent overlay */
            z-index: 0;
        }

        .login-container {
            background-color: rgba(210, 214, 218, 0.95);
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 300px;
            max-width: 90%;
            text-align: center;
            position: relative;
            z-index: 1;
            animation: fadeIn 1s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-container h2 {
            margin-bottom: 25px;
            color: #2c3e50;
            font-size: 1.8em;
            font-weight: 700;
        }

        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #555;
            font-weight: 500;
        }

        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group select {
            width: calc(100% - 24px);
            padding: 11px;
            border: 1px solid #c0c0c0;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 0.95em;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-group input[type="email"]:focus,
        .form-group input[type="password"]:focus,
        .form-group select:focus {
            border-color: #3498db;
            box-shadow: 0 0 8px rgba(52, 152, 219, 0.3);
            outline: none;
        }

        .btn-login {
            background-color: #3498db;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            font-size: 1em;
            font-weight: 700;
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .btn-login:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }

        .registration-link {
            margin-top: 20px;
            font-size: 0.9em;
            color: #555;
        }

        .registration-link a {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .registration-link a:hover {
            color: #2980b9;
            text-decoration: underline;
        }

        .error {
            color: #e74c3c;
            margin-bottom: 18px;
            font-size: 0.9em;
            font-weight: 500;
        }

        /* Media query for smaller screens to center the box */
        @media (max-width: 768px) {
            body {
                justify-content: center; /* Center horizontally on smaller screens */
                padding-right: 0; /* Remove right padding */
            }
            .login-container {
                width: 90%;
                padding: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Online Magazine Portal Login</h2>

        <?php if ($loginError): ?>
            <p class="error"><?php echo $loginError; ?></p>
        <?php endif; ?>

        <form action="index.php" method="POST">
            <div class="form-group">
                <label for="user_type_selected">User Type:</label>
                <select id="user_type_selected" name="user_type_selected" required>
                    <option value="">Select User Type</option>
                    <option value="admin">Admin</option>
                    <option value="staff">Staff</option>
                    <option value="student">Student</option>
                </select>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" name="login" class="btn-login">Login</button>
        </form>
        <div class="registration-link">
            New Student? <a href="student_registration.php">Register Here</a>
        </div>
    </div>
</body>
</html>