<?php
date_default_timezone_set('Asia/Kolkata');
// process_winners.php

// MODIFIED: Include PHPMailer files directly instead of using Composer's autoload
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
include 'db_connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "Starting winner processing script...\n";

// --- 1. Find competitions whose voting period has ended and haven't been processed yet ---
$stmt_finished = $conn->prepare("
    SELECT category_id, description, submission_end_datetime 
    FROM tbl_content_category 
    WHERE category_name = 'competition' 
      AND winners_processed = 0
      AND NOW() > DATE_ADD(submission_end_datetime,INTERVAL 10 MINUTE)
");
$stmt_finished->execute();
$result_finished = $stmt_finished->get_result();

if ($result_finished->num_rows === 0) {
    echo "No competitions to process.\n";
    exit();
}

while ($competition = $result_finished->fetch_assoc()) {
    $competition_id = $competition['category_id'];
    $competition_name = $competition['description'];
    
    echo "Processing competition: " . htmlspecialchars($competition_name) . "\n";

    // --- 2. Find the #1 winner for this competition ---
    $stmt_winner = $conn->prepare("
        SELECT 
            tc.content_id,
            ts.student_id,
            ts.student_name,
            ts.email AS student_email,
            (SELECT COUNT(*) 
             FROM tbl_feedback tf 
             WHERE tf.content_id = tc.content_id 
               AND tf.upvoted = 1
               AND tf.feedback_date BETWEEN ? AND DATE_ADD(?, INTERVAL 10 DAY)
            ) AS upvotes
        FROM tbl_content tc
        JOIN tbl_student ts ON tc.student_id = ts.student_id
        JOIN tbl_content_approval tca ON tc.content_id = tca.content_id
        WHERE tc.category_id = ?
          AND tca.status = 'approved'
          AND tc.published_at IS NOT NULL
        ORDER BY upvotes DESC, tc.published_at DESC
        LIMIT 1
    ");
    $stmt_winner->bind_param("ssi", $competition['submission_end_datetime'], $competition['submission_end_datetime'], $competition_id);
    $stmt_winner->execute();
    $result_winner = $stmt_winner->get_result();
    
    if ($winner = $result_winner->fetch_assoc()) {
        echo "Winner found: " . htmlspecialchars($winner['student_name']) . " with email " . htmlspecialchars($winner['student_email']) . "\n";
        
        // --- 3. Send the congratulatory email ---
        $mail = new PHPMailer(true);

        try {
            // Server settings (replace with your SMTP server details)
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; // Your SMTP host (e.g., smtp.gmail.com)
            $mail->SMTPAuth   = true;
            $mail->Username   = 'magazineoperator@gmail.com'; // Your SMTP username
            $mail->Password   = 'rxaosvryejbydanp';    // Your SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('magazineopertor@gmail.com', 'Online Magazine');
            $mail->addAddress($winner['student_email'], $winner['student_name']);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Congratulations! You Won First Prize!';
            $mail->Body    = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Congratulations</title>
<style>
    body {
        margin: 0;
        padding: 0;
        font-family: Arial, sans-serif;
        background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
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
        font-size: 28px;
        color: #2575fc;
        animation: fadeInDown 1s ease-in-out;
    }
    p {
        font-size: 16px;
        line-height: 1.6;
        color: #444;
        animation: fadeIn 2s ease-in-out;
    }
    strong {
        color: #6a11cb;
    }
    .trophy {
        font-size: 60px;
        margin: 20px 0;
        animation: bounce 2s infinite;
    }
    @keyframes fadeInDown {
        from {opacity: 0; transform: translateY(-30px);}
        to {opacity: 1; transform: translateY(0);}
    }
    @keyframes fadeIn {
        from {opacity: 0;}
        to {opacity: 1;}
    }
    @keyframes bounce {
        0%, 100% {transform: translateY(0);}
        50% {transform: translateY(-12px);}
    }
    .footer {
        margin-top: 20px;
        font-size: 14px;
        color: #888;
    }
</style>
</head>
<body>
    <div class="container">
        <div class="trophy">🏆</div>
        <h1>Congratulations, ' . htmlspecialchars($winner['student_name']) . '!</h1>
        <p>We are thrilled to announce that you have won <strong>First Prize</strong> in the competition:</p>
        <p><strong>"' . htmlspecialchars($competition_name) . '"</strong></p>
        <p>Your hard work and creativity have truly paid off. We will be in touch soon with more details about your prize.</p>
        <p>Best regards,<br><em>The Online Magazine Team</em></p>
        <div class="footer">© ' . date("Y") . ' Online Magazine. All rights reserved.</div>
    </div>
</body>
</html>';

            $mail->send();
            echo 'Email has been sent successfully!' . "\n";

            // --- 4. Mark the competition as processed to prevent re-sending emails ---
            $stmt_update = $conn->prepare("UPDATE tbl_content_category SET winners_processed = 1 WHERE category_id = ?");
            $stmt_update->bind_param("i", $competition_id);
            $stmt_update->execute();
            $stmt_update->close();

        } catch (Exception $e) {
            echo "Email could not be sent. Mailer Error: {$mail->ErrorInfo}\n";
        }
    } else {
        echo "No winner found for this competition.\n";
    }
    $stmt_winner->close();
}

$stmt_finished->close();
$conn->close();