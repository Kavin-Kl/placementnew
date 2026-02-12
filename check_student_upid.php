<?php
session_start();
include "config.php";

header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upid'])) {
    $upid = trim(strtoupper($_POST['upid'])); // Convert to uppercase for consistency

    if (empty($upid)) {
        echo json_encode(['success' => false, 'message' => 'UPID is required']);
        exit;
    }

    // Check if UPID exists and hasn't registered yet (case-insensitive)
    $stmt = $conn->prepare("SELECT * FROM students WHERE UPPER(upid) = ?");
    $stmt->bind_param("s", $upid);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'This UPID is not registered with the placement cell. Please contact the administrator to get registered first.'
        ]);
        exit;
    }

    $student = $result->fetch_assoc();

    // Check if already registered (has password)
    if (!empty($student['password_hash'])) {
        echo json_encode([
            'success' => false,
            'message' => 'This UPID has already been registered. Please login instead.',
            'redirect' => 'student_login.php'
        ]);
        exit;
    }

    // Return pre-filled data
    echo json_encode([
        'success' => true,
        'message' => 'UPID verified! Your details have been pre-filled.',
        'data' => [
            'upid' => $student['upid'],
            'student_name' => $student['student_name'] ?? '',
            'email' => $student['email'] ?? '',
            'phone_no' => $student['phone_no'] ?? '',
            'program_type' => $student['program_type'] ?? '',
            'program' => $student['program'] ?? '',
            'course' => $student['course'] ?? '',
            'reg_no' => $student['reg_no'] ?? '',
            'percentage' => $student['percentage'] ?? ''
        ]
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>
