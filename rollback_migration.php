<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    die("<h1>Error: Admin login required</h1><p>Please <a href='index.php'>login</a> first.</p>");
}

include("config.php");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rollback Migration</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 1000px;
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
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 20px 0;
            color: #856404;
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
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-secondary {
            background: #666;
        }
        .btn-secondary:hover {
            background: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Rollback Migration - Remove Migrated Data</h1>

<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Show confirmation form

    // Get counts of what will be deleted
    $drivesCount = $conn->query("SELECT COUNT(*) as count FROM drives")->fetch_assoc()['count'];
    $rolesCount = $conn->query("SELECT COUNT(*) as count FROM drive_roles")->fetch_assoc()['count'];
    $appsCount = $conn->query("SELECT COUNT(*) as count FROM applications")->fetch_assoc()['count'];
    $driveDataCount = $conn->query("SELECT COUNT(*) as count FROM drive_data")->fetch_assoc()['count'];

    ?>

    <div class="warning-box">
        <h2 style="margin-top: 0; color: #856404;">⚠️ WARNING - THIS ACTION CANNOT BE UNDONE!</h2>
        <p><strong>This will DELETE:</strong></p>
        <ul>
            <li><strong><?= $appsCount ?></strong> Applications</li>
            <li><strong><?= $rolesCount ?></strong> Drive Roles</li>
            <li><strong><?= $driveDataCount ?></strong> Drive Data entries</li>
            <li><strong><?= $drivesCount ?></strong> Drives</li>
        </ul>
        <p style="color: #dc3545; font-weight: bold;">⚠️ This will remove ALL drives, roles, applications, and drive_data!</p>
        <p style="color: #dc3545; font-weight: bold;">⚠️ Your placed_students data will NOT be affected.</p>
        <p style="color: #dc3545; font-weight: bold;">⚠️ Your students data will NOT be affected.</p>
    </div>

    <form method="POST" onsubmit="return confirm('⚠️ FINAL WARNING ⚠️\n\nThis will DELETE:\n- <?= $appsCount ?> Applications\n- <?= $rolesCount ?> Drive Roles\n- <?= $driveDataCount ?> Drive Data entries\n- <?= $drivesCount ?> Drives\n\nAre you absolutely sure?');">
        <input type="hidden" name="confirm_rollback" value="1">

        <label style="display: flex; align-items: center; margin: 20px 0;">
            <input type="checkbox" name="i_understand" value="1" required style="margin-right: 10px; width: 20px; height: 20px;">
            <span style="color: #dc3545; font-weight: bold;">I understand this will delete all drives, roles, and applications data</span>
        </label>

        <button type="submit" class="btn btn-danger">
            🗑️ DELETE ALL MIGRATED DATA
        </button>
        <a href="dashboard.php" class="btn btn-secondary">
            ← Cancel
        </a>
    </form>

    <?php
} else {
    // Perform rollback

    if (!isset($_POST['i_understand'])) {
        echo "<div class='log-line error'>ERROR: Confirmation checkbox not checked</div>";
        exit;
    }

    echo "<div class='log-line warning'>⚠️ ROLLBACK STARTED...</div>";
    echo "<br>";

    $deletedCounts = [
        'applications' => 0,
        'drive_roles' => 0,
        'drive_data' => 0,
        'drives' => 0
    ];

    try {
        // Disable foreign key checks temporarily
        $conn->query("SET FOREIGN_KEY_CHECKS=0");

        // Step 1: Delete applications
        echo "<div class='log-line info'>🗑️ STEP 1: Deleting applications...</div>";
        $result = $conn->query("DELETE FROM applications");
        if ($result) {
            $deletedCounts['applications'] = $conn->affected_rows;
            echo "<div class='log-line success'>✓ Deleted {$deletedCounts['applications']} applications</div>";
        } else {
            throw new Exception("Failed to delete applications: " . $conn->error);
        }

        // Step 2: Delete application_rounds (if exists)
        echo "<div class='log-line info'>🗑️ STEP 2: Deleting application rounds...</div>";
        $result = $conn->query("DELETE FROM application_rounds");
        if ($result) {
            $rounds = $conn->affected_rows;
            echo "<div class='log-line success'>✓ Deleted $rounds application rounds</div>";
        }

        // Step 3: Delete drive_roles
        echo "<div class='log-line info'>🗑️ STEP 3: Deleting drive roles...</div>";
        $result = $conn->query("DELETE FROM drive_roles");
        if ($result) {
            $deletedCounts['drive_roles'] = $conn->affected_rows;
            echo "<div class='log-line success'>✓ Deleted {$deletedCounts['drive_roles']} drive roles</div>";
        } else {
            throw new Exception("Failed to delete drive_roles: " . $conn->error);
        }

        // Step 4: Delete drive_data
        echo "<div class='log-line info'>🗑️ STEP 4: Deleting drive data...</div>";
        $result = $conn->query("DELETE FROM drive_data");
        if ($result) {
            $deletedCounts['drive_data'] = $conn->affected_rows;
            echo "<div class='log-line success'>✓ Deleted {$deletedCounts['drive_data']} drive data entries</div>";
        } else {
            throw new Exception("Failed to delete drive_data: " . $conn->error);
        }

        // Step 5: Delete drives
        echo "<div class='log-line info'>🗑️ STEP 5: Deleting drives...</div>";
        $result = $conn->query("DELETE FROM drives");
        if ($result) {
            $deletedCounts['drives'] = $conn->affected_rows;
            echo "<div class='log-line success'>✓ Deleted {$deletedCounts['drives']} drives</div>";
        } else {
            throw new Exception("Failed to delete drives: " . $conn->error);
        }

        // Re-enable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS=1");

        echo "<br>";
        echo "<div class='log-line success'>═══ ROLLBACK COMPLETED ═══</div>";

        echo "<br>";
        echo "<div style='background: #650000; color: white; padding: 20px; border-radius: 5px;'>";
        echo "<h3 style='margin: 0 0 15px 0; color: white;'>📊 DELETION SUMMARY</h3>";
        echo "<div style='font-size: 16px;'>";
        echo "✓ Applications deleted: <strong>{$deletedCounts['applications']}</strong><br>";
        echo "✓ Drive Roles deleted: <strong>{$deletedCounts['drive_roles']}</strong><br>";
        echo "✓ Drive Data deleted: <strong>{$deletedCounts['drive_data']}</strong><br>";
        echo "✓ Drives deleted: <strong>{$deletedCounts['drives']}</strong><br>";
        echo "</div>";
        echo "</div>";

        // Verify cleanup
        echo "<br>";
        echo "<div class='log-line info'>🔍 Verifying cleanup...</div>";

        $remainingDrives = $conn->query("SELECT COUNT(*) as count FROM drives")->fetch_assoc()['count'];
        $remainingRoles = $conn->query("SELECT COUNT(*) as count FROM drive_roles")->fetch_assoc()['count'];
        $remainingApps = $conn->query("SELECT COUNT(*) as count FROM applications")->fetch_assoc()['count'];
        $remainingDriveData = $conn->query("SELECT COUNT(*) as count FROM drive_data")->fetch_assoc()['count'];

        echo "<div class='log-line info'>Remaining drives: $remainingDrives</div>";
        echo "<div class='log-line info'>Remaining roles: $remainingRoles</div>";
        echo "<div class='log-line info'>Remaining applications: $remainingApps</div>";
        echo "<div class='log-line info'>Remaining drive_data: $remainingDriveData</div>";

        if ($remainingDrives == 0 && $remainingRoles == 0 && $remainingApps == 0 && $remainingDriveData == 0) {
            echo "<br>";
            echo "<div class='log-line success'>✅ All migrated data successfully removed!</div>";
        }

    } catch (Exception $e) {
        echo "<div class='log-line error'>❌ ERROR: " . $e->getMessage() . "</div>";
        // Re-enable foreign key checks even on error
        $conn->query("SET FOREIGN_KEY_CHECKS=1");
    }

    echo "<br>";
    echo "<div style='margin: 20px 0;'>";
    echo "<a href='dashboard.php' class='btn'>📈 Go to Dashboard</a>";
    echo "<a href='migrate_old_website_data.php' class='btn'>🔄 Migrate Again</a>";
    echo "</div>";
}

$conn->close();
?>

    </div>
</body>
</html>
