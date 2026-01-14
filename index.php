<?php

session_start(); // Start the session at the very beginning
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
include 'db_connect.php';


$loginError = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
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
                $allow_login = true;

                // ONLY check status if the user is a 'staff' member
                if ($user['usertype'] === 'staff') {
                    $stmt_status = $conn->prepare("SELECT status FROM tbl_staff WHERE email = ?");
                    $stmt_status->bind_param("s", $user['email']);
                    $stmt_status->execute();
                    $res_status = $stmt_status->get_result();
                    
                    if ($staff_data = $res_status->fetch_assoc()) {
                        if ($staff_data['status'] === 'inactive') {
                            $allow_login = false; // Block login
                            $loginError = "Your staff account has been deactivated. Please contact the administrator.";

                            $mail = new PHPMailer(true); // Create a new instance

    try {
        // 2. Server Settings (REPLACE with your existing config)
        $mail->isSMTP();                                            
        $mail->Host       = 'smtp.gmail.com';                     // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                   
        $mail->Username   = 'magazineoperator@gmail.com';               // SMTP username
        $mail->Password   = 'rxaosvryejbydanp';                  // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
        $mail->Port       = 587;                                    

        // 3. Recipients
        $mail->setFrom('magazineoperator@gmail.com', 'Admin System');
        $mail->addAddress($user['email']);                        // Send to the staff member trying to login

        // 4. Content
        $mail->isHTML(true);                                  
        $mail->Subject = 'Alert: Login Attempt on Deactivated Account';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd;'>
                <h2 style='color: #d9534f;'>Login Denied</h2>
                <p>Hello,</p>
                <p>We detected a login attempt for your account (<strong>" . htmlspecialchars($user['email']) . "</strong>).</p>
                <p>Your account is currently <strong>Deactivated</strong>. You cannot access the staff dashboard.</p>
                <p>If you believe this is an error, please contact the system administrator.</p>
            </div>";
        $mail->AltBody = 'Your account is currently deactivated. You cannot access the staff dashboard.';

        $mail->send();
    } catch (Exception $e) {
        // Log error if needed, but don't crash the login page
        // error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
                        }
                    }
                    $stmt_status->close();
                }
            // Only proceed if allowed
                if ($allow_login) {
                    $user_id = $user['login_id'];
                    $user_email = $user['email'];
                    $actual_usertype = $user['usertype'];
                    
                    // Fetch name (Standard logic continues...)
                    $stmt_staff_name = $conn->prepare("SELECT staff_name FROM tbl_staff WHERE email = ?");
                    $stmt_staff_name->bind_param("s", $user_email);
                    $stmt_staff_name->execute();
                    if ($staff_row = $stmt_staff_name->get_result()->fetch_assoc()) {
                        $user_name = $staff_row['staff_name'];
                    }
                    $stmt_staff_name->close();
                }

            } else {
                $loginError = "Invalid email or password for " . ucfirst($selected_usertype) . ".";
            }
        } else {
            $loginError = "No " . ucfirst($selected_usertype) . " found with that email or invalid credentials.";
        }
        $stmt->close();
    }
   

    elseif ($selected_usertype === 'student'){ 
        $stmt_student = $conn->prepare("SELECT student_id, student_name, email, password 
                                        FROM tbl_student WHERE email = ?");
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
    }

else {
        $loginError = "Please select a valid user type.";
    }

    if ($user_id !== null) {
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_email'] = $user_email;
        $_SESSION['usertype'] = $actual_usertype;
        $_SESSION['user_name'] = $user_name;

        if ($actual_usertype === 'admin') {
            header("Location: admin_overview.php");
            exit();
        } elseif ($actual_usertype === 'staff') {
            header("Location: home.php");
            exit();
        } elseif ($actual_usertype === 'student') {
            header("Location: home.php");
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            /* Changed to align content to the end (right) */
            justify-content: center; /* Center the login box */
            align-items: center;
            min-height: 100vh;
            background-image: url('1.png'); /* Ensure this path is correct and matches your image file (e.g., .jpg or .png) */
            background-size: cover; /* Ensures image fills the entire window */
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
            /* Added padding to push the login box from the right edge */
            padding-right: 0;
            padding-left: 0;
        }

        /* Overlay to make text readable over the background image */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;/* Dark semi-transparent overlay */
            z-index: 0;
        }

        .login-container {
            position: relative;
            background: rgba(248, 249, 250, 0.14); /* glass */
            padding: 35px;
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255,255,255,0.25);
            width: 320px;
            max-width: 90%;
            text-align: center;
            z-index: 1;
            animation: fadeIn 0.9s ease-out, floatY 6s ease-in-out infinite;
            
            border: 1px solid rgba(255, 255, 255, 0.28);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .login-container::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 16px;
            padding: 1px; /* gradient border */
            background: linear-gradient(135deg, rgba(242, 246, 245, 0.55), rgba(243, 242, 241, 0.93));
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor;
                    mask-composite: exclude;
            pointer-events: none;
        }
        .login-container:hover {
            transform: translateY(-4px) scale(1.01);
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.3), 0 0 0 6px rgba(0,191,165,0.08);
        }
        @keyframes floatY { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-container h2 {
            margin-bottom: 25px;
            color: var(--color-primary, #00BFA5);
            font-size: 1.8em;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: var(--color-primary, #00BFA5);
            font-weight: 500;
        }

        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group select {
            width: calc(100% - 24px);
            padding: 11px;
            border: 1px solid rgba(0,0,0,0.12);
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 0.95em;
            background: rgba(255,255,255,0.5);
            transition: border-color 0.3s ease, box-shadow 0.3s ease, background-color 0.3s ease;
        }

        .form-group input[type="email"]:focus,
        .form-group input[type="password"]:focus,
        .form-group select:focus {
            border-color: var(--color-primary, #00BFA5);
            box-shadow: 0 0 0 4px rgba(0, 191, 165, 0.18);
            background: rgba(255,255,255,0.7);
            outline: none;
        }

        .btn-login {
            background: linear-gradient(45deg, var(--color-primary-2, #33CDB9), var(--color-primary, #00BFA5));
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            font-size: 1em;
            font-weight: 700;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
            box-shadow: 0 10px 24px rgba(0, 191, 165, 0.25);
        }

        .btn-login:hover {
            background: linear-gradient(45deg, var(--color-primary, #00BFA5), var(--color-primary-3, #009985));
            transform: translateY(-2px);
            box-shadow: 0 16px 34px rgba(0, 191, 165, 0.35);
            filter: brightness(1.03);
        }
        .btn-login:active { transform: translateY(0); box-shadow: 0 8px 18px rgba(0, 191, 165, 0.25); }

        .registration-link {
            margin-top: 20px;
            font-size: 0.9em;
            color: var(--color-muted, #000000ff);
        }

        .registration-link a {
            color: var(--color-primary, #00BFA5);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .registration-link a:hover {
            color: var(--color-primary-3, #009985);
            text-decoration: underline;
        }

        .error {
            color: #C0392B;
            margin-bottom: 18px;
            font-size: 0.9em;
            font-weight: 500;
        }

        /* Media query for smaller screens to center the box */
        @media (max-width: 768px) {
            body { justify-content: center; padding-right: 0; }
            .login-container { width: 92%; padding: 28px; }
        }
        .password-wrapper {
    position: relative;
    display: flex;
    align-items: center;
     z-index: 2;
}
.password-wrapper input {
    /* Make space on the right side of the input for the icon */
    padding-right: 40px ; 
}
.password-toggle-icon {
    position: absolute;
    right: 5px;
    cursor: pointer;
    color: --color-primary, #00BFA5 /* A neutral grey color */
}
    </style>
</head>
<body >
    <div class="login-container">
        <h2>Online Magazine Portal Login</h2>

        <?php if ($loginError): ?>
            <p class="error"><?php echo $loginError; ?></p>
        <?php endif; ?>

        <form action="index.php" method="POST">
            <div class="form-group">
                <label for="user_type_selected">User Type</label>
                <select id="user_type_selected" name="user_type_selected" required>
                    <option value="">Select User Type</option>
                    <option value="admin">Admin</option>
                    <option value="staff">Staff</option>
                    <option value="student">Student</option>
                </select>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group password-wrapper">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                 <i class="fas fa-eye password-toggle-icon"></i>
            </div>
            <button type="submit" name="login" class="btn-login">Login</button>
        </form>
        <div class="registration-link">
            New Student? <a href="student_registration.php">Register Here</a>
        </div>
    </div>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleIcon = document.querySelector('.password-toggle-icon');
    if (toggleIcon) {
        toggleIcon.addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                this.classList.remove('fa-eye');
                this.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                this.classList.remove('fa-eye-slash');
                this.classList.add('fa-eye');
            }
        });
    }
});
</script>
</body>
</html>