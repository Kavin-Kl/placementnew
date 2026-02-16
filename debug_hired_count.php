<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    die("Error: Admin login required");
}

include("config.php");

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Debug Hired Count</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #ddd; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; background: white; color: black; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
        th { background: #650000; color: white; }
        h2 { color: #89d185; }
        .error { color: #f48771; }
        .success { color: #89d185; }
        .info { color: #61afef; }
    </style>
</head>
<body>
    <h1>Debug Hired Count for Torque Communications</h1>

<?php

// Get Torque's drive_data entry
echo "<h2>1. Drive Data Entry for Torque</h2>";
$query1 = "SELECT * FROM drive_data WHERE company_name LIKE '%Torque%' LIMIT 1";
$result1 = $conn->query($query1);
$driveData = $result1->fetch_assoc();

echo "<table>";
echo "<tr><th>Field</th><th>Value</th></tr>";
foreach ($driveData as $key => $value) {
    echo "<tr><td>$key</td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
}
echo "</table>";

echo "<p class='info'>drive_data ID: {$driveData['id']}</p>";
echo "<p class='info'>drive_id in drive_data: " . ($driveData['drive_id'] ?? 'NULL') . "</p>";
echo "<p class='info'>role_id in drive_data: " . ($driveData['role_id'] ?? 'NULL') . "</p>";

// Check placed_students for Torque
echo "<h2>2. Placed Students for Torque Communications</h2>";
$query2 = "SELECT place_id, student_name, company_name, role, drive_id, role_id, drive_no FROM placed_students WHERE company_name LIKE '%Torque%'";
$result2 = $conn->query($query2);

if ($result2->num_rows > 0) {
    echo "<p class='success'>Found " . $result2->num_rows . " placed students</p>";
    echo "<table>";
    echo "<tr><th>Place ID</th><th>Student Name</th><th>Company</th><th>Role</th><th>Drive ID</th><th>Role ID</th><th>Drive No</th></tr>";
    while ($row = $result2->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['place_id']}</td>";
        echo "<td>{$row['student_name']}</td>";
        echo "<td>{$row['company_name']}</td>";
        echo "<td>{$row['role']}</td>";
        echo "<td>{$row['drive_id']}</td>";
        echo "<td>{$row['role_id']}</td>";
        echo "<td>{$row['drive_no']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>No placed students found for Torque</p>";
}

// Test the current query
echo "<h2>3. Testing Current Hired Count Query</h2>";
$driveDataId = $driveData['id'];
$query3 = "
    SELECT COUNT(DISTINCT ps.student_id) as count
    FROM placed_students ps
    INNER JOIN drive_data dd ON ps.drive_id = dd.drive_id AND ps.role_id = dd.role_id
    WHERE dd.id = $driveDataId
";
echo "<p class='info'>Query: <code>$query3</code></p>";
$result3 = $conn->query($query3);
$currentCount = $result3->fetch_assoc()['count'];
echo "<p>Current query result: <strong>$currentCount</strong></p>";

// Test alternative query using company_name and role
echo "<h2>4. Testing Alternative Query (by company_name and role)</h2>";
$company = $driveData['company_name'];
$role = $driveData['role'];
$query4 = "
    SELECT COUNT(DISTINCT student_id) as count
    FROM placed_students
    WHERE company_name = '$company' AND role = '$role'
";
echo "<p class='info'>Query: <code>$query4</code></p>";
$result4 = $conn->query($query4);
$altCount = $result4->fetch_assoc()['count'];
echo "<p>Alternative query result: <strong>$altCount</strong></p>";

// Test another alternative using drive_no
echo "<h2>5. Testing Another Alternative (by company_name, drive_no, and role)</h2>";
$driveNo = $driveData['drive_no'];
$query5 = "
    SELECT COUNT(DISTINCT student_id) as count
    FROM placed_students
    WHERE company_name = '$company' AND drive_no = '$driveNo' AND role = '$role'
";
echo "<p class='info'>Query: <code>$query5</code></p>";
$result5 = $conn->query($query5);
$alt2Count = $result5->fetch_assoc()['count'];
echo "<p>Alternative query 2 result: <strong>$alt2Count</strong></p>";

// Check if drive_id and role_id match
echo "<h2>6. Checking ID Matches</h2>";
$driveDataDriveId = $driveData['drive_id'] ?? 'NULL';
$driveDataRoleId = $driveData['role_id'] ?? 'NULL';

$query6 = "SELECT drive_id, role_id FROM placed_students WHERE company_name = '$company' AND role = '$role' LIMIT 5";
$result6 = $conn->query($query6);

echo "<p class='info'>Drive Data has: drive_id={$driveDataDriveId}, role_id={$driveDataRoleId}</p>";
echo "<p class='info'>Placed Students have:</p>";
echo "<table>";
echo "<tr><th>Drive ID</th><th>Role ID</th></tr>";
while ($row = $result6->fetch_assoc()) {
    $match = ($row['drive_id'] == $driveDataDriveId && $row['role_id'] == $driveDataRoleId) ? '✓ MATCH' : '✗ NO MATCH';
    echo "<tr><td>{$row['drive_id']}</td><td>{$row['role_id']}</td><td>$match</td></tr>";
}
echo "</table>";

echo "<h2>7. RECOMMENDATION</h2>";
if ($currentCount == 0 && $altCount > 0) {
    echo "<p class='error'>❌ Current query returns 0 but placed students exist!</p>";
    echo "<p class='success'>✅ Use alternative query matching by company_name and role instead of IDs</p>";
} elseif ($currentCount > 0) {
    echo "<p class='success'>✅ Current query works correctly</p>";
} else {
    echo "<p class='error'>❌ No placed students found at all</p>";
}

$conn->close();
?>

<p><a href="course_specific_drive_data.php" style="color: #89d185;">← Back to Company Progress Tracker</a></p>

</body>
</html>
