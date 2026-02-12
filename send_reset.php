<?php
require 'vendor/autoload.php';  // Load PHPMailer
include 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Build base URL dynamically
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
$host     = $_SERVER['HTTP_HOST'];
$path     = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
$baseUrl  = $protocol . $host . $path;

// Function to send reset email using PHPMailer
function sendResetEmail($email, $token, $baseUrl, $email_config)
 {
    $resetLink = $baseUrl . "reset_password.php?token=" . urlencode($token);

    $mail = new PHPMailer(true);
    try {
        // SMTP Settings (Change these to your email details)
         $mail->isSMTP();
    $mail->Host       = $email_config['smtp_host'];
    $mail->Port       = $email_config['smtp_port'];
    $mail->SMTPSecure = $email_config['smtp_secure'];
    $mail->SMTPAuth   = $email_config['smtp_auth'];
    $mail->Username   = $email_config['smtp_username'];
    $mail->Password   = $email_config['smtp_password'];
    $mail->setFrom($email_config['from_email'], $email_config['from_name']);
    $mail->addAddress($email);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request';
        $mail->Body    = "
            Hi,<br><br>
            Please click this link to reset your password:<br>
            <a href='$resetLink'>$resetLink</a><br><br>
            This link will expire in 1 hour.<br><br>
            If you didn't request this, ignore this email.
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Check for POST email input
if (!isset($_POST['email']) || empty($_POST['email'])) {
    die("No email provided.");
}

$email = trim($_POST['email']);

// Check if user exists in your database
$stmt1 = $conn->prepare("SELECT * FROM admin_users WHERE email = ?");
if (!$stmt1) {
    die("Database error: " . $conn->error);
}
$stmt1->bind_param("s", $email);
$stmt1->execute();
$res = $stmt1->get_result();

if ($res && $res->num_rows > 0) {
    // Generate reset token
    
// Check how many reset requests have been made in the last hour
$stmtCount = $conn->prepare("SELECT COUNT(*) as count FROM password_resets WHERE email = ? AND created_at > (NOW() - INTERVAL 1 HOUR)");
if (!$stmtCount) {
    die("Database error: " . $conn->error);
}
$stmtCount->bind_param("s", $email);
$stmtCount->execute();
$resultCount = $stmtCount->get_result();
$rowCount = $resultCount->fetch_assoc();
$stmtCount->close();

if ($rowCount['count'] >= 3) {
    die("You have exceeded the maximum number of password reset requests allowed per hour. Please try again later.");
}
$token = bin2hex(random_bytes(32));
    // Delete previous tokens
    $stmtDelete = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
if (!$stmtDelete) {
    die("Database error (delete): " . $conn->error);
}
$stmtDelete->bind_param("s", $email);
$stmtDelete->execute();
$stmtDelete->close();

    // Save token to DB
    $stmt2 = $conn->prepare("INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, NOW())");
    if (!$stmt2) {
        die("Database error: " . $conn->error);
    }
    $stmt2->bind_param("ss", $email, $token);
    if (!$stmt2->execute()) {
    die("Database error (insert token): " . $conn->error);
}
$stmt2->close();
    // Send email with reset link
    if (sendResetEmail($email, $token, $baseUrl, $email_config)) {
        echo " Reset link sent to your email.";
    } else {
        echo " Failed to send email.";
    }
} else {
    echo "If this email is registered, you will receive a reset link.";
}
