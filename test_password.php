<?php
// Password testing tool
$host = "127.0.0.1";
$user = "root";
$pass = "";
$db   = "admin_placement_db";
$port = 3307;

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Admin Password Tester</h2>";

// Test password generation
$test_password = "test123";
$new_hash = password_hash($test_password, PASSWORD_DEFAULT);

echo "<h3>Fresh Hash for 'test123':</h3>";
echo "<code>$new_hash</code><br><br>";

// Get all admin users
$query = "SELECT admin_id, username, email, password_hash FROM admin_users";
$result = $conn->query($query);

echo "<h3>All Admin Accounts:</h3>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Test Password 'test123'</th><th>Action</th></tr>";

while ($row = $result->fetch_assoc()) {
    $verify = password_verify($test_password, $row['password_hash']);
    $status = $verify ? "<span style='color:green'>✅ WORKS</span>" : "<span style='color:red'>❌ DOESN'T WORK</span>";

    echo "<tr>";
    echo "<td>" . $row['admin_id'] . "</td>";
    echo "<td><strong>" . htmlspecialchars($row['username']) . "</strong></td>";
    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
    echo "<td>$status</td>";
    echo "<td><a href='?reset=" . $row['admin_id'] . "'>Reset to 'test123'</a></td>";
    echo "</tr>";
}

echo "</table>";

// Handle password reset
if (isset($_GET['reset'])) {
    $admin_id = intval($_GET['reset']);
    $new_hash = password_hash('test123', PASSWORD_DEFAULT);

    $update = $conn->prepare("UPDATE admin_users SET password_hash = ? WHERE admin_id = ?");
    $update->bind_param("si", $new_hash, $admin_id);

    if ($update->execute()) {
        echo "<br><div style='background: #dfd; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
        echo "<strong>✅ Password reset successful!</strong><br>";
        echo "Account ID $admin_id password is now: <strong>test123</strong><br>";
        echo "<a href='test_password.php'>Refresh page</a> | <a href='index.php'>Try Admin Login</a>";
        echo "</div>";
    }
}

// Create test admin button
echo "<br><hr><h3>Quick Actions:</h3>";
echo "<form method='POST'>";
echo "<button type='submit' name='create_test'>Create New Test Admin (username: 'admin', password: 'test123')</button>";
echo "</form>";

if (isset($_POST['create_test'])) {
    $username = 'admin';
    $email = 'admin@test.com';
    $hash = password_hash('test123', PASSWORD_DEFAULT);

    // Check if admin already exists
    $check = $conn->prepare("SELECT admin_id FROM admin_users WHERE username = ? OR email = ?");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    $exists = $check->get_result();

    if ($exists->num_rows > 0) {
        echo "<div style='background: #fdd; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
        echo "❌ Admin with username 'admin' or email 'admin@test.com' already exists!<br>";
        echo "Click 'Reset to test123' button above to reset the password.";
        echo "</div>";
    } else {
        $insert = $conn->prepare("INSERT INTO admin_users (username, email, password_hash) VALUES (?, ?, ?)");
        $insert->bind_param("sss", $username, $email, $hash);

        if ($insert->execute()) {
            echo "<div style='background: #dfd; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
            echo "<strong>✅ Test admin created successfully!</strong><br>";
            echo "Username: <strong>admin</strong><br>";
            echo "Email: <strong>admin@test.com</strong><br>";
            echo "Password: <strong>test123</strong><br>";
            echo "<a href='test_password.php'>Refresh page</a> | <a href='index.php'>Try Login</a>";
            echo "</div>";
        }
    }
}

$conn->close();
?>

<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    table { border-collapse: collapse; width: 100%; margin-top: 20px; }
    th { background: #581729; color: white; padding: 10px; }
    td { padding: 10px; }
    code { background: #f4f4f4; padding: 5px; display: block; word-break: break-all; }
    button { padding: 10px 20px; background: #581729; color: white; border: none; border-radius: 5px; cursor: pointer; }
    button:hover { background: #3d0f1c; }
    a { color: #581729; text-decoration: none; padding: 5px 10px; border: 1px solid #581729; border-radius: 3px; }
    a:hover { background: #581729; color: white; }
</style>
