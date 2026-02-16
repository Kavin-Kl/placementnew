<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    die("Error: Admin login required");
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>View Migration Errors</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #ddd; }
        .container { max-width: 1400px; margin: 0 auto; background: #2d2d2d; padding: 30px; border-radius: 8px; }
        h1 { color: #89d185; }
        h2 { color: #61afef; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; background: white; color: black; font-size: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #650000; color: white; position: sticky; top: 0; }
        .error-type { background: #ffcccc; font-weight: bold; }
        .count { background: #fff3cd; text-align: center; font-weight: bold; }
        textarea { width: 100%; height: 400px; font-family: monospace; font-size: 12px; background: #1e1e1e; color: #ddd; padding: 10px; border: 1px solid #650000; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Migration Errors Analysis</h1>

        <form method="POST">
            <p>Paste the error messages from the migration output:</p>
            <textarea name="errors" placeholder="Paste all error messages here..."><?= htmlspecialchars($_POST['errors'] ?? '') ?></textarea>
            <br><br>
            <button type="submit" style="padding: 12px 24px; background: #650000; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">
                📊 Analyze Errors
            </button>
        </form>

<?php
if (isset($_POST['errors']) && !empty($_POST['errors'])) {
    $errorText = $_POST['errors'];
    $errorLines = explode("\n", $errorText);

    $categorized = [
        'student_not_found' => [],
        'drive_not_found' => [],
        'role_not_found' => [],
        'duplicate' => [],
        'prepare_failed' => [],
        'other' => []
    ];

    foreach ($errorLines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        if (stripos($line, 'Student not found') !== false) {
            $categorized['student_not_found'][] = $line;
        } elseif (stripos($line, 'Drive not found') !== false) {
            $categorized['drive_not_found'][] = $line;
        } elseif (stripos($line, 'Role not found') !== false || stripos($line, 'role') !== false) {
            $categorized['role_not_found'][] = $line;
        } elseif (stripos($line, 'already exists') !== false || stripos($line, 'Duplicate') !== false) {
            $categorized['duplicate'][] = $line;
        } elseif (stripos($line, 'prepare') !== false) {
            $categorized['prepare_failed'][] = $line;
        } else {
            $categorized['other'][] = $line;
        }
    }

    echo "<h2>📊 Error Summary</h2>";
    echo "<table>";
    echo "<tr><th>Error Type</th><th>Count</th><th>Description</th></tr>";
    echo "<tr><td class='error-type'>Students Not Found</td><td class='count'>" . count($categorized['student_not_found']) . "</td><td>Students in Excel but not in your database</td></tr>";
    echo "<tr><td class='error-type'>Drives Not Found</td><td class='count'>" . count($categorized['drive_not_found']) . "</td><td>Referenced drives don't exist</td></tr>";
    echo "<tr><td class='error-type'>Roles Not Found</td><td class='count'>" . count($categorized['role_not_found']) . "</td><td>Roles couldn't be matched to drives</td></tr>";
    echo "<tr><td class='error-type'>Duplicates</td><td class='count'>" . count($categorized['duplicate']) . "</td><td>Data already exists (normal, not an error)</td></tr>";
    echo "<tr><td class='error-type'>Prepare Failed</td><td class='count'>" . count($categorized['prepare_failed']) . "</td><td>SQL statement issues</td></tr>";
    echo "<tr><td class='error-type'>Other Errors</td><td class='count'>" . count($categorized['other']) . "</td><td>Miscellaneous errors</td></tr>";
    echo "</table>";

    // Show samples
    foreach ($categorized as $type => $errors) {
        if (empty($errors)) continue;

        $typeLabel = ucwords(str_replace('_', ' ', $type));
        echo "<h2>📋 $typeLabel (First 20)</h2>";
        echo "<table>";
        echo "<tr><th>#</th><th>Error Message</th></tr>";
        foreach (array_slice($errors, 0, 20) as $idx => $error) {
            echo "<tr><td>" . ($idx + 1) . "</td><td>" . htmlspecialchars($error) . "</td></tr>";
        }
        echo "</table>";
        if (count($errors) > 20) {
            echo "<p style='color: #e5c07b;'>... and " . (count($errors) - 20) . " more</p>";
        }
    }

    // Recommendations
    echo "<h2>💡 Recommendations</h2>";
    echo "<div style='background: white; color: black; padding: 20px; border-radius: 5px;'>";

    if (count($categorized['student_not_found']) > 0) {
        echo "<h3 style='color: #dc3545;'>❌ Students Not Found (" . count($categorized['student_not_found']) . ")</h3>";
        echo "<p><strong>Issue:</strong> Applications reference students (by UPID or reg_no) that don't exist in your students table.</p>";
        echo "<p><strong>Solution:</strong> Import the missing students first, or ignore these applications if the students no longer exist.</p>";
    }

    if (count($categorized['drive_not_found']) > 0) {
        echo "<h3 style='color: #dc3545;'>❌ Drives Not Found (" . count($categorized['drive_not_found']) . ")</h3>";
        echo "<p><strong>Issue:</strong> Applications or roles reference drives that weren't imported.</p>";
        echo "<p><strong>Solution:</strong> Check if the Drives sheet has all necessary drives. Re-run migration with correct Drives data.</p>";
    }

    if (count($categorized['role_not_found']) > 0) {
        echo "<h3 style='color: #ffc107;'>⚠️ Roles Not Found (" . count($categorized['role_not_found']) . ")</h3>";
        echo "<p><strong>Issue:</strong> Applications reference roles that don't exist for those drives.</p>";
        echo "<p><strong>Solution:</strong> Applications were created without role_id (will be NULL). This is OK if roles aren't critical.</p>";
    }

    if (count($categorized['duplicate']) > 0) {
        echo "<h3 style='color: #28a745;'>✅ Duplicates (" . count($categorized['duplicate']) . ")</h3>";
        echo "<p><strong>Info:</strong> These records already existed and were skipped. This is normal and not an error.</p>";
    }

    echo "</div>";
}
?>

        <p><a href="migrate_old_website_data.php" style="color: #89d185;">← Back to Migration</a></p>
    </div>
</body>
</html>
