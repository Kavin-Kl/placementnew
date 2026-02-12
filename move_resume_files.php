<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    die("Unauthorized access. Please <a href='index.php'>login as admin</a>.");
}

echo "<h2>Moving Resume Files</h2>";
echo "<pre>";

// Create resumes directory if not exists
$target_dir = __DIR__ . '/uploads/resumes/';
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
    echo "✓ Created directory: $target_dir\n\n";
}

// Scan uploads directory
$source_dir = __DIR__ . '/uploads/';
$files = scandir($source_dir);

$moved = 0;
$skipped = 0;
$errors = 0;

foreach ($files as $file) {
    // Skip . and .. and directories
    if ($file == '.' || $file == '..' || is_dir($source_dir . $file)) {
        continue;
    }

    // Only move document files
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'doc', 'docx'])) {
        echo "⊘ Skipped: $file (not a document)\n";
        $skipped++;
        continue;
    }

    $source_file = $source_dir . $file;
    $target_file = $target_dir . $file;

    // Check if target already exists
    if (file_exists($target_file)) {
        echo "⊘ Skipped: $file (already exists in resumes/)\n";
        $skipped++;
        continue;
    }

    // Move file
    if (rename($source_file, $target_file)) {
        echo "✓ Moved: $file\n";
        $moved++;
    } else {
        echo "✗ Failed: $file\n";
        $errors++;
    }
}

echo "\n===================\n";
echo "Summary:\n";
echo "- Moved: $moved files\n";
echo "- Skipped: $skipped files\n";
echo "- Errors: $errors files\n";
echo "===================\n";

echo "</pre>";
echo "<p><a href='dashboard.php'>← Back to Dashboard</a></p>";
?>
