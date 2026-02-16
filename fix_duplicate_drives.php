<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    die("<h1>Error: Admin login required</h1><p>Please <a href='index.php'>login</a> first.</p>");
}

include("config.php");

set_time_limit(600);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fix Duplicate Drives in Company Progress Tracker</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #2d2d2d;
            padding: 30px;
            border-radius: 8px;
        }
        h1 {
            color: #89d185;
            border-bottom: 2px solid #650000;
            padding-bottom: 10px;
        }
        .success { color: #89d185; font-weight: bold; }
        .error { color: #f48771; font-weight: bold; }
        .warning { color: #e5c07b; font-weight: bold; }
        .info { color: #61afef; }
        .log-line {
            padding: 5px 0;
            border-left: 3px solid transparent;
            padding-left: 10px;
            margin: 2px 0;
        }
        .log-line.success { border-left-color: #89d185; }
        .log-line.error { border-left-color: #f48771; }
        .log-line.warning { border-left-color: #e5c07b; }
        .log-line.info { border-left-color: #61afef; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: #1e1e1e;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solid #444;
        }
        th {
            background: #650000;
            color: white;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #650000;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 5px;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #520000;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Fix Duplicate Drives in Company Progress Tracker</h1>

<?php

echo "<div class='log-line info'>🔍 STEP 1: Searching for duplicate entries in drive_data table...</div>";
echo "<br>";

// Find duplicates based on company_name, drive_no, and role
$duplicateQuery = "
    SELECT
        company_name,
        drive_no,
        role,
        COUNT(*) as count,
        GROUP_CONCAT(id ORDER BY id) as ids
    FROM drive_data
    GROUP BY company_name, drive_no, role
    HAVING COUNT(*) > 1
    ORDER BY company_name, drive_no, role
";

$result = $conn->query($duplicateQuery);

if ($result->num_rows === 0) {
    echo "<div class='log-line success'>✓ No duplicates found! The drive_data table is clean.</div>";
} else {
    echo "<div class='log-line warning'>⚠️ Found " . $result->num_rows . " duplicate groups</div>";
    echo "<br>";

    echo "<table>";
    echo "<tr><th>Company</th><th>Drive No</th><th>Role</th><th>Count</th><th>IDs</th><th>Action</th></tr>";

    $duplicates = [];
    while ($row = $result->fetch_assoc()) {
        $duplicates[] = $row;
        $ids = explode(',', $row['ids']);

        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['company_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['drive_no']) . "</td>";
        echo "<td>" . htmlspecialchars($row['role']) . "</td>";
        echo "<td>" . $row['count'] . "</td>";
        echo "<td>" . htmlspecialchars($row['ids']) . "</td>";
        echo "<td><span class='warning'>Will keep ID " . $ids[0] . ", delete others</span></td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<br>";
    echo "<div class='log-line info'>🗑️ STEP 2: Removing duplicate entries (keeping oldest ID)...</div>";
    echo "<br>";

    $totalDeleted = 0;

    foreach ($duplicates as $dup) {
        $ids = explode(',', $dup['ids']);
        $keepId = array_shift($ids); // Keep the first (oldest) ID
        $deleteIds = $ids; // Delete the rest

        if (!empty($deleteIds)) {
            $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
            $deleteQuery = "DELETE FROM drive_data WHERE id IN ($placeholders)";

            $stmt = $conn->prepare($deleteQuery);

            // Bind all IDs
            $types = str_repeat('i', count($deleteIds));
            $stmt->bind_param($types, ...$deleteIds);

            if ($stmt->execute()) {
                $deleted = $stmt->affected_rows;
                $totalDeleted += $deleted;
                echo "<div class='log-line success'>✓ Deleted $deleted duplicate(s) for '{$dup['company_name']}' - '{$dup['role']}' (kept ID $keepId)</div>";
            } else {
                echo "<div class='log-line error'>❌ Failed to delete duplicates for '{$dup['company_name']}' - '{$dup['role']}': " . $stmt->error . "</div>";
            }

            $stmt->close();
        }
    }

    echo "<br>";
    echo "<div class='log-line success'>═══ CLEANUP COMPLETED ═══</div>";
    echo "<div class='log-line success'>✓ Total duplicate entries deleted: $totalDeleted</div>";
}

echo "<br>";

// Get final statistics
$totalDriveData = $conn->query("SELECT COUNT(*) AS count FROM drive_data")->fetch_assoc()['count'];
$uniqueCompanies = $conn->query("SELECT COUNT(DISTINCT company_name) AS count FROM drive_data")->fetch_assoc()['count'];
$uniqueRoles = $conn->query("SELECT COUNT(DISTINCT CONCAT(company_name, '-', role)) AS count FROM drive_data")->fetch_assoc()['count'];

echo "<div style='background: #650000; color: white; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h2 style='margin: 0 0 10px 0; color: white;'>📊 FINAL STATISTICS</h2>";
echo "<div style='font-size: 18px;'>";
echo "✓ Total Drive Data Entries: <strong>$totalDriveData</strong><br>";
echo "✓ Unique Companies: <strong>$uniqueCompanies</strong><br>";
echo "✓ Unique Company-Role Combinations: <strong>$uniqueRoles</strong><br>";
echo "</div>";
echo "</div>";

echo "<div style='margin: 20px 0;'>";
echo "<a href='course_specific_drive_data.php' class='btn'>📊 Go to Company Progress Tracker</a>";
echo "<a href='dashboard.php' class='btn'>📈 Go to Dashboard</a>";
echo "</div>";

$conn->close();
?>

    </div>
</body>
</html>
