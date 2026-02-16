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
    <title>Debug All Hired Counts</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #ddd; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; background: white; color: black; font-size: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #650000; color: white; position: sticky; top: 0; }
        h2 { color: #89d185; }
        .error { background: #ffcccc; }
        .success { background: #ccffcc; }
        .warning { background: #fff3cd; }
        .info { color: #61afef; }
        .match { color: green; font-weight: bold; }
        .nomatch { color: red; font-weight: bold; }
        .summary { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; color: black; }
        .summary h3 { color: #650000; margin-top: 0; }
    </style>
</head>
<body>
    <h1>🔍 Debug All Hired Counts - Complete Analysis</h1>

<?php

echo "<div class='summary'>";
echo "<h3>📊 Summary Statistics</h3>";

// Get total counts
$totalDriveData = $conn->query("SELECT COUNT(*) as count FROM drive_data")->fetch_assoc()['count'];
$totalPlacedStudents = $conn->query("SELECT COUNT(*) as count FROM placed_students")->fetch_assoc()['count'];
$companiesWithZeroHired = 0;
$companiesWithHired = 0;

echo "<p><strong>Total Drive Data Entries:</strong> $totalDriveData</p>";
echo "<p><strong>Total Placed Students:</strong> $totalPlacedStudents</p>";
echo "</div>";

// Get all drive_data entries
$query = "
    SELECT
        dd.id,
        dd.company_name,
        dd.drive_no,
        dd.role,
        dd.drive_id as dd_drive_id,
        dd.role_id as dd_role_id,
        dd.hired_count as stored_hired_count
    FROM drive_data dd
    ORDER BY dd.company_name, dd.drive_no, dd.role
";

$result = $conn->query($query);

echo "<h2>📋 All Companies - Hired Count Analysis</h2>";
echo "<table>";
echo "<tr>";
echo "<th>ID</th>";
echo "<th>Company</th>";
echo "<th>Drive No</th>";
echo "<th>Role</th>";
echo "<th>Drive ID<br>(drive_data)</th>";
echo "<th>Role ID<br>(drive_data)</th>";
echo "<th>Stored<br>Hired Count</th>";
echo "<th>Current Query<br>(by IDs)</th>";
echo "<th>Alternative Query<br>(by company+role)</th>";
echo "<th>Status</th>";
echo "</tr>";

$issues = [];
$fixed = 0;

while ($row = $result->fetch_assoc()) {
    $ddId = $row['id'];
    $company = $row['company_name'];
    $driveNo = $row['drive_no'];
    $role = $row['role'];
    $ddDriveId = $row['dd_drive_id'];
    $ddRoleId = $row['dd_role_id'];
    $storedCount = $row['stored_hired_count'];

    // Test current query (by IDs)
    $currentQuery = $conn->prepare("
        SELECT COUNT(DISTINCT ps.student_id) as count
        FROM placed_students ps
        INNER JOIN drive_data dd ON ps.drive_id = dd.drive_id AND ps.role_id = dd.role_id
        WHERE dd.id = ?
    ");
    $currentQuery->bind_param("i", $ddId);
    $currentQuery->execute();
    $currentCount = $currentQuery->get_result()->fetch_assoc()['count'];
    $currentQuery->close();

    // Test alternative query (by company + role)
    $altQuery = $conn->prepare("
        SELECT COUNT(DISTINCT student_id) as count
        FROM placed_students
        WHERE company_name = ? AND role = ?
    ");
    $altQuery->bind_param("ss", $company, $role);
    $altQuery->execute();
    $altCount = $altQuery->get_result()->fetch_assoc()['count'];
    $altQuery->close();

    // Determine status
    $status = '';
    $rowClass = '';

    if ($currentCount == 0 && $altCount > 0) {
        $status = '❌ BROKEN (IDs mismatch)';
        $rowClass = 'error';
        $companiesWithZeroHired++;
        $issues[] = [
            'company' => $company,
            'role' => $role,
            'drive_no' => $driveNo,
            'expected' => $altCount,
            'current' => $currentCount
        ];
    } elseif ($currentCount > 0) {
        $status = '✅ OK';
        $rowClass = 'success';
        $companiesWithHired++;
    } elseif ($altCount == 0) {
        $status = '⚪ No placements yet';
        $rowClass = '';
    }

    echo "<tr class='$rowClass'>";
    echo "<td>$ddId</td>";
    echo "<td>" . htmlspecialchars($company) . "</td>";
    echo "<td>" . htmlspecialchars($driveNo) . "</td>";
    echo "<td>" . htmlspecialchars($role) . "</td>";
    echo "<td>" . ($ddDriveId ?: '<span style="color:red;">NULL</span>') . "</td>";
    echo "<td>" . ($ddRoleId ?: '<span style="color:red;">NULL</span>') . "</td>";
    echo "<td>$storedCount</td>";
    echo "<td class='" . ($currentCount > 0 ? 'match' : 'nomatch') . "'>$currentCount</td>";
    echo "<td class='" . ($altCount > 0 ? 'match' : '') . "'>$altCount</td>";
    echo "<td>$status</td>";
    echo "</tr>";
}

echo "</table>";

// Summary
echo "<div class='summary'>";
echo "<h3>🎯 Analysis Results</h3>";
echo "<p><strong>Companies Working Correctly:</strong> $companiesWithHired</p>";
echo "<p><strong>Companies with Hired Count = 0 (BROKEN):</strong> $companiesWithZeroHired</p>";

if (count($issues) > 0) {
    echo "<h3 style='color: #dc3545;'>❌ Issues Found</h3>";
    echo "<p><strong>" . count($issues) . " companies have hired students but showing 0!</strong></p>";
    echo "<table style='margin-top: 10px;'>";
    echo "<tr><th>Company</th><th>Role</th><th>Drive No</th><th>Actual Hired</th><th>Showing</th></tr>";
    foreach ($issues as $issue) {
        echo "<tr class='error'>";
        echo "<td>{$issue['company']}</td>";
        echo "<td>{$issue['role']}</td>";
        echo "<td>{$issue['drive_no']}</td>";
        echo "<td class='match'>{$issue['expected']}</td>";
        echo "<td class='nomatch'>{$issue['current']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<h3 style='color: #650000; margin-top: 30px;'>💡 ROOT CAUSE</h3>";
    echo "<p>The query is trying to match <code>placed_students.drive_id</code> and <code>placed_students.role_id</code> with <code>drive_data.drive_id</code> and <code>drive_data.role_id</code>, but these IDs don't match!</p>";

    echo "<h3 style='color: #650000;'>✅ SOLUTION</h3>";
    echo "<p>Change the hired count query to match by <strong>company_name and role</strong> instead of IDs.</p>";
    echo "<p>Alternative: Populate drive_id and role_id in drive_data table correctly.</p>";

    echo "<form method='POST' style='margin-top: 20px;'>";
    echo "<button type='submit' name='fix_all' class='btn' style='padding: 15px 30px; background: #650000; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;'>🔧 Fix All Hired Counts Now</button>";
    echo "</form>";
} else {
    echo "<h3 style='color: green;'>✅ All Good!</h3>";
    echo "<p>All hired counts are working correctly.</p>";
}

echo "</div>";

// Handle fix request
if (isset($_POST['fix_all'])) {
    echo "<div class='summary'>";
    echo "<h3 style='color: #650000;'>🔧 Fixing Hired Counts...</h3>";

    $fixed = 0;
    foreach ($issues as $issue) {
        // Update the query in the code to use company_name + role matching
        echo "<p class='info'>Would fix: {$issue['company']} - {$issue['role']}</p>";
        $fixed++;
    }

    echo "<p class='success'><strong>Found $fixed issues that need code fix.</strong></p>";
    echo "<p>The hired count calculation query needs to be updated in course_specific_drive_data.php</p>";
    echo "</div>";
}

$conn->close();
?>

<style>
.btn:hover { background: #520000; }
</style>

<p><a href="course_specific_drive_data.php" style="color: #89d185;">← Back to Company Progress Tracker</a></p>

</body>
</html>
