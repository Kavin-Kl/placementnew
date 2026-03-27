<?php
/**
 * Migration Script: Add company_sector column to drives table
 * Run this script once to add the new column
 */

session_start();
include("config.php");

// Check if admin
if (!isset($_SESSION['admin_id'])) {
    die("Unauthorized access");
}

echo "<h2>Migration: Add company_sector to drives table</h2>";

try {
    // Check if column already exists
    $check = $conn->query("SHOW COLUMNS FROM drives LIKE 'company_sector'");

    if ($check->num_rows > 0) {
        echo "<p style='color: orange;'>✓ Column 'company_sector' already exists in drives table.</p>";
    } else {
        // Add the column
        $sql = "ALTER TABLE drives ADD COLUMN company_sector VARCHAR(100) DEFAULT NULL AFTER company_name";

        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✓ Successfully added company_sector column to drives table.</p>";
        } else {
            throw new Exception("Error adding column: " . $conn->error);
        }
    }

    echo "<p><strong>Migration completed successfully!</strong></p>";
    echo "<p><a href='add_drive.php'>Go to Add Drive</a> | <a href='dashboard.php'>Go to Dashboard</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

$conn->close();
?>
