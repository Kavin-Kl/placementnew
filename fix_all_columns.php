<?php
// COMPREHENSIVE DATABASE FIX - Add ALL missing columns
$host = "127.0.0.1";
$user = "root";
$pass = "";
$db   = "admin_placement_db";
$port = 3307;

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>🔧 Comprehensive Database Fix</h2>";
echo "<p>Adding ALL missing columns to drives and drive_roles tables...</p>";
echo "<hr>";

// ========================================
// FIX DRIVES TABLE
// ========================================
echo "<h3>Fixing 'drives' table:</h3>";

$result = $conn->query("DESCRIBE drives");
$existing_drives_columns = [];
while ($row = $result->fetch_assoc()) {
    $existing_drives_columns[] = $row['Field'];
}

$drives_columns = [
    'form_fields' => [
        'query' => "ALTER TABLE drives ADD COLUMN form_fields TEXT NULL AFTER form_link",
        'description' => 'Form fields configuration'
    ],
    'company_url' => [
        'query' => "ALTER TABLE drives ADD COLUMN company_url VARCHAR(255) NULL AFTER company_name",
        'description' => 'Company website URL'
    ],
    'graduating_year' => [
        'query' => "ALTER TABLE drives ADD COLUMN graduating_year VARCHAR(50) NULL AFTER extra_details",
        'description' => 'Target graduating year'
    ],
    'work_location' => [
        'query' => "ALTER TABLE drives ADD COLUMN work_location VARCHAR(255) NULL AFTER graduating_year",
        'description' => 'Work location/office location'
    ],
    'jd_link' => [
        'query' => "ALTER TABLE drives ADD COLUMN jd_link TEXT NULL AFTER jd_file",
        'description' => 'Job description link'
    ]
];

$drives_added = 0;
foreach ($drives_columns as $colName => $info) {
    if (!in_array($colName, $existing_drives_columns)) {
        echo "<p style='color: orange;'>⚠️ Column '<strong>$colName</strong>' is missing</p>";
        echo "<p style='margin-left: 20px;'>→ {$info['description']}</p>";

        if ($conn->query($info['query'])) {
            echo "<p style='color: green; margin-left: 20px;'>✅ Added successfully!</p>";
            $drives_added++;
        } else {
            echo "<p style='color: red; margin-left: 20px;'>❌ Error: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: green;'>✅ Column '<strong>$colName</strong>' exists</p>";
    }
}

// ========================================
// FIX DRIVE_ROLES TABLE
// ========================================
echo "<hr><h3>Fixing 'drive_roles' table:</h3>";

$result = $conn->query("DESCRIBE drive_roles");
$existing_roles_columns = [];
while ($row = $result->fetch_assoc()) {
    $existing_roles_columns[] = $row['Field'];
}

$roles_columns = [
    'work_timings' => [
        'query' => "ALTER TABLE drive_roles ADD COLUMN work_timings VARCHAR(255) NULL AFTER stipend",
        'description' => 'Work timings/schedule'
    ],
    'bond_details' => [
        'query' => "ALTER TABLE drive_roles ADD COLUMN bond_details TEXT NULL AFTER work_timings",
        'description' => 'Bond/service agreement details'
    ],
    'selection_process' => [
        'query' => "ALTER TABLE drive_roles ADD COLUMN selection_process TEXT NULL AFTER bond_details",
        'description' => 'Selection process steps'
    ],
    'other_benefits' => [
        'query' => "ALTER TABLE drive_roles ADD COLUMN other_benefits TEXT NULL AFTER selection_process",
        'description' => 'Additional benefits'
    ]
];

$roles_added = 0;
foreach ($roles_columns as $colName => $info) {
    if (!in_array($colName, $existing_roles_columns)) {
        echo "<p style='color: orange;'>⚠️ Column '<strong>$colName</strong>' is missing</p>";
        echo "<p style='margin-left: 20px;'>→ {$info['description']}</p>";

        if ($conn->query($info['query'])) {
            echo "<p style='color: green; margin-left: 20px;'>✅ Added successfully!</p>";
            $roles_added++;
        } else {
            echo "<p style='color: red; margin-left: 20px;'>❌ Error: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: green;'>✅ Column '<strong>$colName</strong>' exists</p>";
    }
}

// ========================================
// SUMMARY
// ========================================
echo "<hr>";
echo "<div style='background: #e3f2fd; padding: 20px; border-radius: 8px; border-left: 5px solid #2196F3;'>";
echo "<h3>📊 Summary:</h3>";
echo "<ul>";
echo "<li><strong>Drives table:</strong> $drives_added column(s) added</li>";
echo "<li><strong>Drive_roles table:</strong> $roles_added column(s) added</li>";
echo "<li><strong>Total:</strong> " . ($drives_added + $roles_added) . " column(s) added</li>";
echo "</ul>";
echo "</div>";

if ($drives_added > 0 || $roles_added > 0) {
    echo "<div style='background: #dfd; padding: 20px; border-radius: 8px; margin-top: 20px;'>";
    echo "<h3>✅ Database Fix Complete!</h3>";
    echo "<p><strong>All missing columns have been added.</strong></p>";
    echo "<p>You can now:</p>";
    echo "<ul>";
    echo "<li>Create placement drives</li>";
    echo "<li>Add roles to drives</li>";
    echo "<li>Manage all drive data</li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div style='background: #fff9e6; padding: 20px; border-radius: 8px; margin-top: 20px;'>";
    echo "<h3>ℹ️ No Changes Needed</h3>";
    echo "<p>All required columns already exist!</p>";
    echo "</div>";
}

// Show final structure
echo "<hr>";
echo "<h3>📋 Final 'drives' Table Structure:</h3>";
$result = $conn->query("DESCRIBE drives");
echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #581729; color: white;'><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
while ($row = $result->fetch_assoc()) {
    $highlight = in_array($row['Field'], array_keys($drives_columns)) ? "style='background: #dfd;'" : "";
    echo "<tr $highlight>";
    echo "<td><strong>{$row['Field']}</strong></td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<div style='text-align: center; margin-top: 30px;'>";
echo "<a href='index.php' style='padding: 12px 24px; background: #581729; color: white; text-decoration: none; border-radius: 5px; margin: 5px;'>Admin Login</a>";
echo "<a href='add_drive.php' style='padding: 12px 24px; background: #2196F3; color: white; text-decoration: none; border-radius: 5px; margin: 5px;'>Create Drive</a>";
echo "<a href='dashboard.php' style='padding: 12px 24px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin: 5px;'>Dashboard</a>";
echo "</div>";

$conn->close();
?>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    padding: 20px;
    background: #f5f5f5;
    max-width: 1200px;
    margin: 0 auto;
}
h2 { color: #581729; }
h3 { color: #333; margin-top: 20px; }
table { background: white; }
th { padding: 12px !important; }
td { padding: 8px !important; }
a:hover { opacity: 0.8; }
</style>
