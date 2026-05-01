<?php
// Centralized error / fatal handler — logs warnings & notices, renders a clean
// page on fatals/uncaught exceptions instead of leaving a blank screen.
require_once __DIR__ . '/error_handler.php';

$host = "127.0.0.1";
$user = "root";
$pass = ""; // or "root" based on your XAMPP
$db   = "admin_placement_db";
$port = 3308;

$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
    error_log('[DB CONNECT] ' . $conn->connect_error);
    if (function_exists('_placement_render_error_page')) {
        _placement_render_error_page('Database is currently unreachable',
            'Please try again in a moment. If this persists, contact the placement-cell admin.');
        exit;
    }
    die("Database connection failed: " . $conn->connect_error);
}
@$conn->set_charset('utf8mb4');
$mysqldumpPath = 'C:\xampp\mysql\bin\mysqldump.exe'; 

$email_config = [
    'smtp_host'     => 'smtp.gmail.com',
    'smtp_port'     => 587,
    'smtp_secure'   => 'tls',
    'smtp_auth'     => true,
    'smtp_username' => 'mccplacementdashboard@gmail.com',      // Change to shared email (not personal)
    'smtp_password' => 'nmwj jhvc prcn lbhg',         //  Gmail app password
    'from_email'    => 'mccplacementdashboard@gmail.com',
    'from_name'     => 'Placement Cell',            // Sender name
];
?>
