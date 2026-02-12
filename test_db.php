<?php
// Simple database connection test
$host = "127.0.0.1";
$user = "root";
$pass = "";
$db   = "admin_placement_db";
$port = 3307;

echo "<h2>Testing Database Connection...</h2>";
echo "<p><strong>Host:</strong> $host</p>";
echo "<p><strong>Port:</strong> $port</p>";
echo "<p><strong>Database:</strong> $db</p>";
echo "<hr>";

// Test connection
$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    echo "<p style='color: red;'><strong>❌ CONNECTION FAILED:</strong></p>";
    echo "<p style='color: red;'>Error: " . $conn->connect_error . "</p>";
    echo "<p style='color: red;'>Error Code: " . $conn->connect_errno . "</p>";

    // Check if MySQL is running
    echo "<hr>";
    echo "<h3>Troubleshooting:</h3>";
    echo "<ol>";
    echo "<li>Open XAMPP Control Panel</li>";
    echo "<li>Check if MySQL is running (should be GREEN)</li>";
    echo "<li>If not, click START next to MySQL</li>";
    echo "<li>Refresh this page</li>";
    echo "</ol>";
} else {
    echo "<p style='color: green;'><strong>✅ CONNECTION SUCCESSFUL!</strong></p>";

    // Test if tables exist
    $tables_query = "SHOW TABLES";
    $result = $conn->query($tables_query);

    echo "<h3>Tables in database:</h3>";
    echo "<ul>";
    while ($row = $result->fetch_array()) {
        echo "<li>" . $row[0] . "</li>";
    }
    echo "</ul>";

    // Check for student portal tables
    $student_tables = ['student_notifications', 'student_password_resets'];
    echo "<h3>Student Portal Tables:</h3>";
    foreach ($student_tables as $table) {
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check->num_rows > 0) {
            echo "<p style='color: green;'>✅ $table exists</p>";
        } else {
            echo "<p style='color: red;'>❌ $table NOT found</p>";
        }
    }

    $conn->close();

    echo "<hr>";
    echo "<p><strong>Next step:</strong> If everything looks good, try the login pages:</p>";
    echo "<ul>";
    echo "<li><a href='index.php'>Admin Login</a></li>";
    echo "<li><a href='student_login.php'>Student Login</a></li>";
    echo "</ul>";
}
?>
