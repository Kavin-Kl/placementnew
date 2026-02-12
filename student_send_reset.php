<?php
session_start();
include("config.php");

// Set JSON header at the top
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);

    if (empty($email)) {
        echo json_encode([
            'success' => false,
            'message' => 'Email is required.'
        ]);
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email format.'
        ]);
        exit;
    }

    // Check if email exists
    $stmt = $conn->prepare("SELECT student_id, student_name, email FROM students WHERE email = ?");
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error. Please try again.'
        ]);
        exit;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No account found with that email address. Please check and try again.'
        ]);
        exit;
    }

    $row = $res->fetch_assoc();

    // Generate unique token
    $token = bin2hex(random_bytes(32));

    // Delete any old tokens for this email
    $delete_stmt = $conn->prepare("DELETE FROM student_password_resets WHERE email = ?");
    $delete_stmt->bind_param("s", $email);
    $delete_stmt->execute();

    // Store new token in database
    $insert_stmt = $conn->prepare("INSERT INTO student_password_resets (email, token) VALUES (?, ?)");
    if (!$insert_stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error. Please try again.'
        ]);
        exit;
    }

    $insert_stmt->bind_param("ss", $email, $token);

    if (!$insert_stmt->execute()) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to generate reset link. Please try again.'
        ]);
        exit;
    }

    // Build reset link
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    $resetLink = $protocol . '://' . $host . $scriptPath . '/student_reset_password.php?token=' . $token;

    // Return JSON response
    echo json_encode([
        'success' => true,
        'message' => 'Password reset link generated for: ' . htmlspecialchars($row['student_name']),
        'reset_link' => $resetLink
    ]);
    exit;
}

// Invalid request
echo json_encode([
    'success' => false,
    'message' => 'Invalid request method.'
]);
?>
