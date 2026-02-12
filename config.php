<?php
$host = "127.0.0.1";
$user = "root";
$pass = ""; // or "root" based on your XAMPP
$db   = "admin_placement_db";
$port = 3308;

$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
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
