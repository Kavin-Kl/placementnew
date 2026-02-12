<?php
require 'config.php';

echo "<h2>Student Accounts in Database</h2>";
echo "<p>Use these credentials to test student login:</p>";

$query = "SELECT student_id, upid, reg_no, student_name, email, is_active, password_hash
          FROM students
          WHERE is_active = 1
          LIMIT 10";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr>
            <th>UPID</th>
            <th>Reg No</th>
            <th>Name</th>
            <th>Email</th>
            <th>Has Password?</th>
          </tr>";

    while ($row = $result->fetch_assoc()) {
        $hasPassword = !empty($row['password_hash']) ? 'Yes' : 'No';
        echo "<tr>
                <td>" . htmlspecialchars($row['upid']) . "</td>
                <td>" . htmlspecialchars($row['reg_no']) . "</td>
                <td>" . htmlspecialchars($row['student_name']) . "</td>
                <td>" . htmlspecialchars($row['email']) . "</td>
                <td>" . $hasPassword . "</td>
              </tr>";
    }
    echo "</table>";

    echo "<br><h3>How to Login as Student:</h3>";
    echo "<ol>";
    echo "<li>Go to: <a href='student_login.php'>student_login.php</a></li>";
    echo "<li>Use UPID as username</li>";
    echo "<li>If 'Has Password?' is No, you need to register first</li>";
    echo "</ol>";

} else {
    echo "<p style='color:red;'>No active student accounts found in database.</p>";
    echo "<p>You need to add students first via the admin panel.</p>";
}

echo "<br><hr>";
echo "<h3>Create a Test Student Account</h3>";
echo "<p>Run this SQL in phpMyAdmin to create a test student:</p>";
echo "<pre style='background:#f0f0f0; padding:10px;'>";
echo "INSERT INTO students
(upid, reg_no, student_name, email, phone_no, program_type, program, course,
 batch, year_of_passing, percentage, is_active, password_hash)
VALUES
('TEST001', 'REG2025001', 'Test Student', 'test@student.com', '9876543210',
 'UG', 'BCA', 'Bachelor of Computer Applications', '2025', 2025, 75.5, 1,
 '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');";
echo "</pre>";
echo "<p><strong>Login with:</strong></p>";
echo "<ul>";
echo "<li>Username: TEST001</li>";
echo "<li>Password: password</li>";
echo "</ul>";

$conn->close();
?>

<br><br>
<a href="index.php">Back to Admin Login</a>
