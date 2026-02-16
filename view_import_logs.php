<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: index");
    exit;
}

$logFile = __DIR__ . '/logs/import_log.txt';
$logExists = file_exists($logFile);
$logContent = $logExists ? file_get_contents($logFile) : 'No logs found.';
$logLines = $logExists ? explode("\n", $logContent) : [];
$logLines = array_reverse(array_filter($logLines)); // Show newest first

// Get only last 500 lines
$logLines = array_slice($logLines, 0, 500);

// Clear logs functionality
if (isset($_POST['clear_logs'])) {
    file_put_contents($logFile, '');
    header("Location: view_import_logs.php");
    exit;
}

// Download logs functionality
if (isset($_GET['download'])) {
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="import_log_' . date('Y-m-d_H-i-s') . '.txt"');
    echo $logContent;
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Logs - Placement Cell</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .log-container {
            background: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            padding: 20px;
            border-radius: 5px;
            max-height: 600px;
            overflow-y: auto;
            margin: 20px 0;
            border: 1px solid #444;
        }

        .log-line {
            padding: 4px 0;
            border-bottom: 1px solid #2d2d2d;
        }

        .log-line:hover {
            background: #2d2d2d;
        }

        .log-timestamp {
            color: #858585;
            margin-right: 10px;
        }

        .log-error {
            color: #f48771;
            font-weight: bold;
        }

        .log-success {
            color: #89d185;
        }

        .log-warning {
            color: #e5c07b;
        }

        .log-info {
            color: #61afef;
        }

        .controls {
            display: flex;
            gap: 10px;
            margin: 20px 0;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #650000;
            color: white;
        }

        .btn-primary:hover {
            background: #520000;
        }

        .btn-secondary {
            background: #444;
            color: white;
        }

        .btn-secondary:hover {
            background: #555;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #650000;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="heading-container">
        <h3 class="headings">Import Logs</h3>
        <p>View detailed logs from Excel/CSV import operations</p>
    </div>

    <?php if ($logExists):
        $totalLines = count($logLines);
        $errorCount = count(array_filter($logLines, function($line) { return strpos($line, 'ERROR') !== false; }));
        $successCount = substr_count($logContent, 'IMPORT COMPLETED SUCCESSFULLY');
    ?>

    <div class="stats">
        <div class="stat-card">
            <div class="stat-value"><?= $totalLines ?></div>
            <div class="stat-label">Total Log Entries (Last 500)</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $successCount ?></div>
            <div class="stat-label">Successful Imports</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $errorCount ?></div>
            <div class="stat-label">Errors</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= round(filesize($logFile) / 1024, 2) ?> KB</div>
            <div class="stat-label">Log File Size</div>
        </div>
    </div>

    <?php endif; ?>

    <div class="controls">
        <a href="view_import_logs.php" class="btn btn-primary">
            <i class="fas fa-sync-alt"></i> Refresh
        </a>
        <a href="view_import_logs.php?download=1" class="btn btn-secondary">
            <i class="fas fa-download"></i> Download Logs
        </a>
        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to clear all logs?');">
            <button type="submit" name="clear_logs" class="btn btn-danger">
                <i class="fas fa-trash"></i> Clear Logs
            </button>
        </form>
        <a href="vantage_registered_students" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Import
        </a>
    </div>

    <div class="log-container">
        <?php if (empty($logLines)): ?>
            <div style="color: #858585; text-align: center; padding: 20px;">
                No log entries found. Logs will appear here after importing files.
            </div>
        <?php else: ?>
            <?php foreach ($logLines as $line):
                $class = '';
                if (strpos($line, 'ERROR') !== false) {
                    $class = 'log-error';
                } elseif (strpos($line, 'SUCCESS') !== false || strpos($line, 'COMPLETED') !== false) {
                    $class = 'log-success';
                } elseif (strpos($line, 'WARNING') !== false || strpos($line, 'Skipped') !== false) {
                    $class = 'log-warning';
                } elseif (strpos($line, 'Starting') !== false || strpos($line, 'Found') !== false) {
                    $class = 'log-info';
                }
            ?>
                <div class="log-line <?= $class ?>">
                    <?= htmlspecialchars($line) ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; border-left: 4px solid #650000;">
        <strong>Legend:</strong><br>
        <span class="log-error">■ ERROR</span> - Critical errors that prevented import<br>
        <span class="log-success">■ SUCCESS</span> - Successful operations<br>
        <span class="log-warning">■ WARNING</span> - Skipped rows or warnings<br>
        <span class="log-info">■ INFO</span> - General information
    </div>

    <?php include("footer.php"); ?>
</body>
</html>
