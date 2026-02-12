<?php
// Fix drives table - Add missing form_fields column
$host = "127.0.0.1";
$user = "root";
$pass = "";
$db   = "admin_placement_db";
$port = 3307;

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Fixing Drives Table Structure</h2>";

// Check current structure
echo "<h3>Current 'drives' table structure:</h3>";
$result = $conn->query("DESCRIBE drives");
$columns = [];
echo "<table border='1' cellpadding='5'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
    echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
}
echo "</table><br>";

// Define all required columns
$requiredColumns = [
    'form_fields' => "ALTER TABLE drives ADD COLUMN form_fields TEXT NULL AFTER form_link",
    'company_url' => "ALTER TABLE drives ADD COLUMN company_url VARCHAR(255) NULL AFTER company_name"
];

$added = 0;
foreach ($requiredColumns as $colName => $alterQuery) {
    if (!in_array($colName, $columns)) {
        echo "<p style='color: orange;'>⚠️ Column '$colName' is missing!</p>";
        echo "<p>Adding '$colName' column...</p>";

        if ($conn->query($alterQuery)) {
            echo "<p style='color: green;'>✅ Column '$colName' added successfully!</p>";
            $added++;
        } else {
            echo "<p style='color: red;'>❌ Error adding '$colName': " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: green;'>✅ Column '$colName' already exists!</p>";
    }
}

if ($added > 0) {
    echo "<br><p style='background: #dfd; padding: 10px; border-radius: 5px;'><strong>🎉 Added $added column(s) successfully!</strong></p>";
}

// Show updated structure
echo "<br><h3>Updated 'drives' table structure:</h3>";
$result = $conn->query("DESCRIBE drives");
echo "<table border='1' cellpadding='5'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
while ($row = $result->fetch_assoc()) {
    $highlight = ($row['Field'] == 'form_fields') ? "style='background: #dfd;'" : "";
    echo "<tr $highlight><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
}
echo "</table><br>";

echo "<hr>";
echo "<p><strong>✅ Database fix complete!</strong></p>";
echo "<p>You can now try creating a drive again.</p>";
echo "<p><a href='add_drive.php'>Go to Add Drive</a> | <a href='dashboard.php'>Go to Dashboard</a></p>";

$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; }
table { border-collapse: collapse; margin: 10px 0; }
th { background: #581729; color: white; padding: 10px; }
td { padding: 8px; }
a { color: #581729; text-decoration: none; padding: 8px 15px; border: 1px solid #581729; border-radius: 5px; margin: 0 5px; display: inline-block; }
a:hover { background: #581729; color: white; }
</style>
