<?php 
function autoLogAdminVisit() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['admin_id'])) {
        return; // no admin logged in
    }

    global $conn;
    $adminId = $_SESSION['admin_id'];
    $currentPage = basename($_SERVER['PHP_SELF']);

    $username = "Unknown";

    if ($stmt = $conn->prepare("SELECT username FROM admin_users WHERE admin_id = ?")) {
        $stmt->bind_param("i", $adminId);
        $stmt->execute();
        $stmt->bind_result($fetchedUsername);
        if ($stmt->fetch()) {
            $username = $fetchedUsername;
        }
        $stmt->close();
    }

    $logFolder = __DIR__ . '/logs';
    $logFile = $logFolder . '/admin_actions.log';

    if (!file_exists($logFolder)) {
        mkdir($logFolder, 0777, true);
    }

    $newLogLine = date('Y-m-d H:i:s') . " | Admin: $username (ID: $adminId) | Page: $currentPage" . PHP_EOL;

    // Read existing logs
    $lines = [];
    if (file_exists($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    $cutoff = strtotime('-10 days'); // keep last 10 days
    $filteredLines = [];

    foreach ($lines as $line) {
        $dateStr = substr($line, 0, 19);
        $logTime = strtotime($dateStr);
        if ($logTime !== false && $logTime >= $cutoff) {
            $filteredLines[] = $line;
        }
    }

    // Add new log line at the end
    $filteredLines[] = trim($newLogLine);

    // Write back filtered + new logs
    file_put_contents($logFile, implode(PHP_EOL, $filteredLines) . PHP_EOL);
}

autoLogAdminVisit();
?>
