<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    die("Unauthorized");
}

include("config.php");

// Run sync
include_once 'sync_placed_students.php';
$sync = sync_placed_students($conn);

echo "<h2>Fix Hired Counts</h2>";
echo "<h3>Sync Result:</h3>";
echo "Inserted: {$sync['inserted']}<br>";
echo "Updated: {$sync['updated']}<br>";
echo "Deleted: {$sync['deleted']}<br>";

if (!empty($sync['errors'])) {
    echo "<p style='color:red'>Errors: " . implode(', ', $sync['errors']) . "</p>";
}

echo "<h3>Current Hired Counts:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Drive ID</th><th>Role ID</th><th>Company</th><th>Drive No</th><th>Hired</th></tr>";

$result = $conn->query("
    SELECT
        d.drive_id,
        dr.role_id,
        d.company_name,
        d.drive_no,
        COUNT(DISTINCT ps.student_id) as hired_count
    FROM drives d
    CROSS JOIN drive_roles dr ON d.drive_id = dr.drive_id
    LEFT JOIN placed_students ps ON d.drive_id = ps.drive_id AND dr.role_id = ps.role_id
    GROUP BY d.drive_id, dr.role_id, d.company_name, d.drive_no
    HAVING hired_count > 0
    ORDER BY d.drive_no DESC
");

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['drive_id']}</td>";
    echo "<td>{$row['role_id']}</td>";
    echo "<td>{$row['company_name']}</td>";
    echo "<td>{$row['drive_no']}</td>";
    echo "<td style='color:green; font-weight:bold;'>{$row['hired_count']}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<p><a href='dashboard.php'>Back to Dashboard</a></p>";

// Clear opcode cache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "<p>✓ Cache cleared</p>";
}
?>
