<?php
session_start();

// --- Add PHPMailer ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
// --- End PHPMailer ---

include 'db_connect.php';

$registrationError = '';
$registrationSuccess = '';
$show_otp_modal = false;

// STEP 1: Registration request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_student'])) {
    $student_name = $conn->real_escape_string($_POST['student_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $stu_phn = $conn->real_escape_string($_POST['stu_phn']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $dept_id = (int)$_POST['dept_id'];
    $program_id = (int)$_POST['program_id'];
    $gender = $conn->real_escape_string($_POST['gender']);

    // --- Enforce Rajagiri email domain ---
   if (!str_ends_with($email, '@rajagiricollege.edu.in')) {
    $registrationError = "❌ Registration allowed only with @rajagiricollege.edu.in email addresses.";
}

    elseif ($password !== $confirm_password) {
        $registrationError = "❌ Passwords do not match.";
    } else {
        // Check if email already exists
        $stmt_check_email = $conn->prepare("SELECT email FROM tbl_student WHERE email = ?");
        $stmt_check_email->bind_param("s", $email);
        $stmt_check_email->execute();
        $stmt_check_email->store_result();

        if ($stmt_check_email->num_rows > 0) {
            $registrationError = "❌ Email already registered.";
        } else {
            // Generate OTP
            $otp = rand(100000, 999999);
            $_SESSION['registration_otp'] = $otp;
            $_SESSION['registration_data'] = [
                'student_name' => $student_name,
                'email' => $email,
                'stu_phn' => $stu_phn,
                'password' => $password,
                'dept_id' => $dept_id,
                'program_id' => $program_id,
                'gender' => $gender
            ];

            // Send OTP via email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'magazineoperator@gmail.com'; // your Gmail
                $mail->Password   = 'rxaosvryejbydanp'; // Gmail App password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;

                $mail->setFrom('your-email@gmail.com', 'Online Magazine Portal');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'Your OTP for Registration';
                $mail->Body    = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Your OTP Code</title>
<style>
    body {
        margin: 0;
        padding: 0;
        font-family: Arial, sans-serif;
        background: linear-gradient(135deg, #ff512f 0%, #dd2476 100%);
    }
    .container {
        max-width: 600px;
        margin: 40px auto;
        background: #fff;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        padding: 30px;
        text-align: center;
    }
    h1 {
        font-size: 26px;
        color: #dd2476;
        margin-bottom: 20px;
        animation: fadeInDown 1s ease-in-out;
    }
    p {
        font-size: 16px;
        color: #444;
        line-height: 1.5;
        animation: fadeIn 1.5s ease-in-out;
    }
    .otp {
        font-size: 32px;
        font-weight: bold;
        color: #ff512f;
        margin: 20px 0;
        padding: 12px 25px;
        border: 2px dashed #dd2476;
        border-radius: 10px;
        display: inline-block;
        animation: pulse 1.5s infinite;
    }
    @keyframes fadeInDown {
        from {opacity: 0; transform: translateY(-30px);}
        to {opacity: 1; transform: translateY(0);}
    }
    @keyframes fadeIn {
        from {opacity: 0;}
        to {opacity: 1;}
    }
    @keyframes pulse {
        0% {transform: scale(1);}
        50% {transform: scale(1.08);}
        100% {transform: scale(1);}
    }
    .footer {
        margin-top: 25px;
        font-size: 14px;
        color: #888;
    }
</style>
</head>
<body>
    <div class="container">
        <h1>🔐 Secure Verification</h1>
        <p>Use the following One-Time Password (OTP) to complete your login/verification:</p>
        <div class="otp">' . $otp . '</div>
        <p>This code will expire in <strong>5 minutes</strong>. Please do not share it with anyone.</p>
        <div class="footer">© ' . date("Y") . ' Online Magazine. All rights reserved.</div>
    </div>
</body>
</html>';
                $mail->AltBody = "Your One-Time Password (OTP) is: $otp";

                $mail->send();
                $show_otp_modal = true;
            } catch (Exception $e) {
                $registrationError = "❌ OTP could not be sent. Error: {$mail->ErrorInfo}";
            }
        }
        $stmt_check_email->close();
    }
}

// STEP 2: OTP Verification
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['otp_submit'])) {
    $submitted_otp = $_POST['otp'];

    if (isset($_SESSION['registration_otp']) && $submitted_otp == $_SESSION['registration_otp']) {
        $data = $_SESSION['registration_data'];

        // Hash password
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);

        // Insert into database
        $stmt = $conn->prepare("INSERT INTO tbl_student (student_name, dept_id, program_id, gender, email, stu_phn, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("siissss", $data['student_name'], $data['dept_id'], $data['program_id'], $data['gender'], $data['email'], $data['stu_phn'], $hashed_password);

        if ($stmt->execute()) {
            $registrationSuccess = "✅ Registration successful! You can now login.";
            unset($_SESSION['registration_otp']);
            unset($_SESSION['registration_data']);
        } else {
            $registrationError = "❌ Registration failed: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $registrationError = "❌ Invalid OTP. Please try again.";
        $show_otp_modal = true;
    }
}

// Fetch departments and programs (unchanged)
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
        body {
            font-family: 'Roboto', Arial, sans-serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background-image: url('21.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
        }
      
        .registration-container {
            position: relative;
            z-index: 1;
            width: 520px;
            max-width: 92%;
            padding: 30px;
            text-align: center;
            border-radius: 16px;
            background: rgba(248, 249, 250, 0.14);
            box-shadow: 0 20px 50px rgba(0,0,0,0.25), inset 0 1px 0 rgba(255,255,255,0.25);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255,255,255,0.28);
            animation: fadeIn 0.9s ease-out, floatY 6s ease-in-out infinite;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .registration-container::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 16px;
            padding: 1px;
            background: linear-gradient(135deg, rgba(0,191,165,0.55), rgba(255,140,66,0.45));
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor;
                    mask-composite: exclude;
            pointer-events: none;
        }
        .registration-container:hover { transform: translateY(-4px) scale(1.01); box-shadow: 0 24px 60px rgba(0,0,0,0.3), 0 0 0 6px rgba(0,191,165,0.08); }
        @keyframes floatY { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }

        .registration-container h2 { margin-bottom: 20px; color: var(--color-primary, #00BFA5); letter-spacing: 0.3px; }
        .form-group { margin-bottom: 15px; text-align: left; }
        .form-group label { display: block; margin-bottom: 6px; color: var(--color-primary, #070707ff); font-weight: 500; }
        .form-group input[type="text"], .form-group input[type="email"], .form-group input[type="password"],
        .form-group select {
            width: calc(100% - 22px);
            padding: 12px;
            border: 1px solid rgba(0,0,0,0.12);
            border-radius: 8px;
            box-sizing: border-box;
            background: rgba(255, 255, 255, 1);
            transition: border-color 0.3s ease, box-shadow 0.3s ease, background-color 0.3s ease;
        }
        .form-group input[type="text"]:focus, .form-group input[type="email"]:focus, .form-group input[type="password"]:focus,
        .form-group select:focus { border-color: var(--color-primary, #00BFA5); box-shadow: 0 0 0 4px rgba(0,191,165,0.18); background: rgba(255,255,255,0.7); outline: none; }
        .form-group input[type="radio"] { margin-right: 6px; }

        .btn-register {
            background: linear-gradient(45deg, var(--color-primary-2, #33CDB9), var(--color-primary, #00BFA5));
            color: #fff;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            font-weight: 700;
            box-shadow: 0 10px 24px rgba(0,191,165,0.25);
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
        }
        .btn-register:hover { background: linear-gradient(45deg, var(--color-primary, #00BFA5), var(--color-primary-3, #009985)); transform: translateY(-2px); box-shadow: 0 16px 34px rgba(0,191,165,0.35); filter: brightness(1.03); }
        .btn-register:active { transform: translateY(0); box-shadow: 0 8px 18px rgba(0,191,165,0.25); }

        .login-link { margin-top: 15px; font-size: 14px; color: var(--color-muted, #4B5C66); }
        .login-link a { color: var(--color-primary, #00BFA5); text-decoration: none; font-weight: 500; transition: color 0.3s ease; }
        .login-link a:hover { color: var(--color-primary-3, #009985); text-decoration: underline; }

        .error { color: #C0392B; margin-bottom: 15px; }
        .success { color: var(--color-primary-3, #009985); margin-bottom: 15px; }

        @media (max-width: 768px) { .registration-container { width: 94%; padding: 26px; } }
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
<div id="otpModal" class="modal">
    <div class="modal-content">
        <div class="modal-right">
            <h3>Verify Your Email</h3>
            <p>We've sent a 6-digit OTP to your email address. Please enter it below.</p>
            <div id="otpMessage">
              <?php if (!empty($register_message) && $show_otp_modal): ?>
                  <div class="message <?= $register_message_type; ?>">
                      <?= $register_message ?>
                  </div>
              <?php endif; ?>
            </div>
            <form method="POST">
                <input type="tel" name="otp" placeholder="Enter 6-Digit OTP" maxlength="6" required>
                <button type="submit" name="otp_submit">Verify & Complete Registration</button>
            </form>
        </div>
    </div>
</div>
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

        const otpModal = document.getElementById("otpModal");

        function closeAllModals() {
        loginModal.style.display = "none";
        registerModal.style.display = "none";
        if (otpModal) otpModal.style.display = "none";
    }
    
    // Auto-open OTP modal if PHP flag is set
    <?php if ($show_otp_modal): ?>
    document.addEventListener('DOMContentLoaded', function() {
        closeAllModals();
        otpModal.style.display = "flex";
    });
    <?php endif; ?>

    // Auto-open register modal if there's a registration error (but not an OTP error)
    <?php if (!empty($register_message) && !$show_otp_modal): ?>
    document.addEventListener('DOMContentLoaded', function() {
        openRegisterModal();
    });
    <?php endif; ?>

    </script>
</body>
</html>