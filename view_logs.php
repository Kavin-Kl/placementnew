<?php
session_start();

// Allow only logged-in admins to view logs
if (!isset($_SESSION['admin_id'])) {
    die("Access Denied");
}

$logFile = __DIR__ . '/logs/admin_actions.log';

if (!file_exists($logFile)) {
    die("Log file not found.");
}

$logs = file($logFile);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Activity Logs</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f4f4f4; }
        pre { background: #fff; padding: 10px; border: 1px solid #ccc; white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>
    <h2>Admin Activity Logs</h2>
    <pre><?= htmlspecialchars(implode("", $logs)) ?></pre>
</body>
</html>
