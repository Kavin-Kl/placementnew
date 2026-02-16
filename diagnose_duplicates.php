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
    <title>Diagnose Duplicates</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #ddd; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; background: white; color: black; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #650000; color: white; }
        .duplicate { background: #ffcccc; }
        h2 { color: #89d185; }
    </style>
</head>
<body>
    <h1>Duplicate Diagnosis - Torque Communications</h1>

<?php

// Check drive_data table
echo "<h2>1. Entries in drive_data table for Torque:</h2>";
$query = "SELECT id, company_name, drive_no, role, drive_id, role_id FROM drive_data WHERE company_name LIKE '%Torque%' ORDER BY id";
$result = $conn->query($query);

echo "<table><tr><th>ID</th><th>Company</th><th>Drive No</th><th>Role</th><th>Drive ID</th><th>Role ID</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr><td>{$row['id']}</td><td>{$row['company_name']}</td><td>{$row['drive_no']}</td><td>{$row['role']}</td><td>{$row['drive_id']}</td><td>{$row['role_id']}</td></tr>";
}
echo "</table>";

// Check drives table
echo "<h2>2. Entries in drives table for Torque:</h2>";
$query2 = "SELECT drive_id, company_name, drive_no FROM drives WHERE company_name LIKE '%Torque%' ORDER BY drive_id";
$result2 = $conn->query($query2);

echo "<table><tr><th>Drive ID</th><th>Company</th><th>Drive No</th></tr>";
while ($row = $result2->fetch_assoc()) {
    echo "<tr><td>{$row['drive_id']}</td><td>{$row['company_name']}</td><td>{$row['drive_no']}</td></tr>";
}
echo "</table>";

// Check drive_roles table
echo "<h2>3. Entries in drive_roles for Torque drives:</h2>";
$query3 = "
    SELECT dr.role_id, dr.drive_id, dr.designation_name, d.company_name
    FROM drive_roles dr
    JOIN drives d ON dr.drive_id = d.drive_id
    WHERE d.company_name LIKE '%Torque%'
    ORDER BY dr.role_id
";
$result3 = $conn->query($query3);

echo "<table><tr><th>Role ID</th><th>Drive ID</th><th>Company</th><th>Designation</th></tr>";
while ($row = $result3->fetch_assoc()) {
    echo "<tr><td>{$row['role_id']}</td><td>{$row['drive_id']}</td><td>{$row['company_name']}</td><td>{$row['designation_name']}</td></tr>";
}
echo "</table>";

// Find exact duplicates
echo "<h2>4. Duplicate Analysis (Same Company + Drive No + Role):</h2>";
$query4 = "
    SELECT
        company_name,
        drive_no,
        role,
        COUNT(*) as count,
        GROUP_CONCAT(id ORDER BY id) as ids,
        GROUP_CONCAT(drive_id ORDER BY id) as drive_ids,
        GROUP_CONCAT(role_id ORDER BY id) as role_ids
    FROM drive_data
    WHERE company_name LIKE '%Torque%'
    GROUP BY company_name, drive_no, role
    HAVING COUNT(*) > 1
";
$result4 = $conn->query($query4);

if ($result4->num_rows > 0) {
    echo "<table><tr><th>Company</th><th>Drive No</th><th>Role</th><th>Count</th><th>IDs</th><th>Drive IDs</th><th>Role IDs</th></tr>";
    while ($row = $result4->fetch_assoc()) {
        echo "<tr class='duplicate'>";
        echo "<td>{$row['company_name']}</td>";
        echo "<td>{$row['drive_no']}</td>";
        echo "<td>{$row['role']}</td>";
        echo "<td>{$row['count']}</td>";
        echo "<td>{$row['ids']}</td>";
        echo "<td>{$row['drive_ids']}</td>";
        echo "<td>{$row['role_ids']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: #89d185;'>✓ No duplicates found in drive_data for Torque</p>";
}

// Check if the SQL query in course_specific_drive_data.php is causing the issue
echo "<h2>5. Testing the actual query from course_specific_drive_data.php:</h2>";
$query5 = "
SELECT
  d.*,
  drv.drive_no AS drive_number,
  drv.open_date AS opening_date,
  dr.close_date AS closing_date,
  drv.created_by
FROM drive_data d
LEFT JOIN drives drv ON d.company_name = drv.company_name AND d.drive_no = drv.drive_no
LEFT JOIN drive_roles dr ON drv.drive_id = dr.drive_id AND TRIM(d.role) = TRIM(dr.designation_name)
WHERE d.company_name LIKE '%Torque%'
ORDER BY d.company_name ASC, d.id ASC
";
$result5 = $conn->query($query5);

echo "<p>Rows returned by query: " . $result5->num_rows . "</p>";
echo "<table><tr><th>ID</th><th>Company</th><th>Drive No (data)</th><th>Drive No (joined)</th><th>Role</th><th>Drive ID</th><th>Role ID</th></tr>";
while ($row = $result5->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['company_name']}</td>";
    echo "<td>{$row['drive_no']}</td>";
    echo "<td>{$row['drive_number']}</td>";
    echo "<td>{$row['role']}</td>";
    echo "<td>{$row['drive_id']}</td>";
    echo "<td>{$row['role_id']}</td>";
    echo "</tr>";
}
echo "</table>";

$conn->close();
?>

<p><a href="course_specific_drive_data.php" style="color: #89d185;">← Back to Company Progress Tracker</a></p>

</body>
</html>
