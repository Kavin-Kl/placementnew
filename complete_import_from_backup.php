<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: index");
    exit;
}

include("config.php");
require 'vendor/autoload.php';

// Setup logging
$logFile = __DIR__ . '/logs/complete_import_log.txt';
if (!file_exists(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0777, true);
}

function logImport($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    echo "$message<br>";
    flush();
}

set_time_limit(600);
ini_set('memory_limit', '2048M');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complete Import from Backup</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .import-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .log-output {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            max-height: 600px;
            overflow-y: auto;
            margin: 20px 0;
        }
        .success { color: #89d185; font-weight: bold; }
        .error { color: #f48771; font-weight: bold; }
        .warning { color: #e5c07b; }
        .info { color: #61afef; }
        .btn {
            padding: 12px 24px;
            background: #650000;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #520000;
        }
        .btn-secondary {
            background: #666;
        }
        .btn-secondary:hover {
            background: #555;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="import-container">
        <h2>Complete Import from Backup</h2>
        <p>Import drives and all placed students from backup file</p>

        <?php if ($_SERVER['REQUEST_METHOD'] !== 'POST'):
            $currentPlaced = $conn->query("SELECT COUNT(*) as count FROM placed_students")->fetch_assoc()['count'];
        ?>

        <div style="padding: 20px; background: #d1ecf1; border-left: 4px solid #0c5460; margin: 20px 0;">
            <h3 style="margin-top: 0; color: #0c5460;">📋 This will import:</h3>
            <ol>
                <li><strong>Drives & Roles</strong> from the backup (creates missing drives/roles)</li>
                <li><strong>All 212 Placed Students</strong> from the backup</li>
            </ol>
            <p><strong>Current placed students:</strong> <?= $currentPlaced ?></p>
        </div>

        <div style="padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107; margin: 20px 0;">
            <h3 style="margin-top: 0; color: #856404;">⚠️ Important</h3>
            <p>This will <strong>clear existing placed_students table</strong> and import fresh data.</p>
            <p>Existing drives will NOT be deleted - only new ones will be added.</p>
        </div>

        <form method="POST" style="margin: 20px 0;">
            <input type="hidden" name="confirm_import" value="1">

            <label style="display: flex; align-items: center; margin: 20px 0;">
                <input type="checkbox" name="i_understand" value="1" required style="margin-right: 10px; width: 20px; height: 20px;">
                <span style="color: #dc3545; font-weight: bold;">I understand this will reset placed students data</span>
            </label>

            <button type="submit" class="btn btn-danger" onclick="return confirm('This will clear placed students and import all data fresh. Continue?');">
                <i class="fas fa-upload"></i> Start Complete Import
            </button>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancel
            </a>
        </form>

        <?php else: ?>

        <div class="log-output">
<?php

if (!isset($_POST['i_understand'])) {
    logImport("<span class='error'>ERROR: Confirmation required</span>");
    exit;
}

logImport("<span class='success'>=== COMPLETE IMPORT STARTED ===</span>");
logImport("User: " . ($_SESSION['admin_id'] ?? 'Unknown'));
logImport("Time: " . date('Y-m-d H:i:s'));

$file = 'C:/Users/Kavin/Downloads/placement_backup_all (1).xlsx';

if (!file_exists($file)) {
    logImport("<span class='error'>ERROR: File not found</span>");
    exit;
}

try {
    // Load Excel file
    logImport("<span class='info'>Loading Excel file...</span>");
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($file);

    // ========================================
    // STEP 1: Import Drives & Roles
    // ========================================
    logImport("<span class='success'>\n========================================</span>");
    logImport("<span class='success'>STEP 1: Importing Drives & Roles</span>");
    logImport("<span class='success'>========================================</span>");

    $drivesSheet = $spreadsheet->getSheetByName('Drives');
    if (!$drivesSheet) {
        throw new Exception("Drives sheet not found");
    }

    $driveRows = $drivesSheet->toArray(null, false, false, false);
    $driveHeader = array_shift($driveRows);

    // Map drive headers
    $driveHeaderMap = [];
    foreach ($driveHeader as $index => $colName) {
        $normalized = strtolower(trim($colName));
        $driveHeaderMap[$normalized] = $index;
    }

    $driveRows = array_filter($driveRows, function($row) {
        return !empty(array_filter($row));
    });

    logImport("<span class='info'>Found " . count($driveRows) . " drives in backup</span>");

    $drivesInserted = 0;
    $drivesSkipped = 0;
    $rolesInserted = 0;

    foreach ($driveRows as $driveData) {
        $company_name = trim($driveData[$driveHeaderMap['company_name']] ?? '');
        $drive_no = trim($driveData[$driveHeaderMap['drive_no']] ?? '');
        $open_date = trim($driveData[$driveHeaderMap['open_date']] ?? '');
        $close_date = trim($driveData[$driveHeaderMap['close_date']] ?? '');
        $created_by = trim($driveData[$driveHeaderMap['created_by']] ?? 'Import');
        $designation_name = trim($driveData[$driveHeaderMap['designation_name']] ?? '');
        $ctc = trim($driveData[$driveHeaderMap['ctc']] ?? '');
        $stipend = trim($driveData[$driveHeaderMap['stipend']] ?? '');
        $offer_type = trim($driveData[$driveHeaderMap['offer_type']] ?? 'FTE');

        if (empty($company_name) || empty($drive_no)) {
            continue;
        }

        // Check if drive exists
        $checkStmt = $conn->prepare("SELECT drive_id FROM drives WHERE company_name = ? AND drive_no = ?");
        $checkStmt->bind_param("ss", $company_name, $drive_no);
        $checkStmt->execute();
        $existingDrive = $checkStmt->get_result();

        if ($existingDrive->num_rows > 0) {
            $drive_id = $existingDrive->fetch_assoc()['drive_id'];
            $drivesSkipped++;
            $checkStmt->close();
        } else {
            $checkStmt->close();

            // Insert drive
            $insertDrive = $conn->prepare("
                INSERT INTO drives (company_name, drive_no, open_date, close_date, created_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $insertDrive->bind_param("sssss", $company_name, $drive_no, $open_date, $close_date, $created_by);

            if ($insertDrive->execute()) {
                $drive_id = $conn->insert_id;
                $drivesInserted++;
            } else {
                $insertDrive->close();
                continue;
            }
            $insertDrive->close();
        }

        // Insert role if designation_name exists
        if (!empty($designation_name) && $drive_id) {
            // Check if role already exists
            $checkRole = $conn->prepare("SELECT role_id FROM drive_roles WHERE drive_id = ? AND designation_name = ?");
            $checkRole->bind_param("is", $drive_id, $designation_name);
            $checkRole->execute();
            $existingRole = $checkRole->get_result();
            $checkRole->close();

            if ($existingRole->num_rows === 0) {
                $insertRole = $conn->prepare("
                    INSERT INTO drive_roles (drive_id, designation_name, ctc, stipend, offer_type)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insertRole->bind_param("issss", $drive_id, $designation_name, $ctc, $stipend, $offer_type);

                if ($insertRole->execute()) {
                    $rolesInserted++;
                }
                $insertRole->close();
            }
        }
    }

    logImport("<span class='success'>✓ Drives imported: $drivesInserted</span>");
    logImport("<span class='warning'>  Drives skipped (existing): $drivesSkipped</span>");
    logImport("<span class='success'>✓ Roles imported: $rolesInserted</span>");

    // ========================================
    // STEP 2: Clear & Import Placed Students
    // ========================================
    logImport("<span class='success'>\n========================================</span>");
    logImport("<span class='success'>STEP 2: Importing Placed Students</span>");
    logImport("<span class='success'>========================================</span>");

    // Clear existing placed students
    $conn->query("DELETE FROM placed_students");
    logImport("<span class='warning'>✓ Cleared existing placed_students table</span>");

    $placedSheet = $spreadsheet->getSheetByName('On_Campus_Placed_Students');
    if (!$placedSheet) {
        throw new Exception("On_Campus_Placed_Students sheet not found");
    }

    $placedRows = $placedSheet->toArray(null, false, false, false);
    $placedHeader = array_shift($placedRows);

    // Map headers
    $headerMap = [];
    foreach ($placedHeader as $index => $colName) {
        $normalized = strtolower(trim($colName));
        $headerMap[$normalized] = $index;
    }

    $placedRows = array_filter($placedRows, function($row) {
        return !empty(array_filter($row));
    });

    logImport("<span class='info'>Found " . count($placedRows) . " placement records in backup</span>");

    $inserted = 0;
    $skipped = 0;
    $skipReasons = [
        'student_not_found' => 0,
        'drive_not_found' => 0,
        'role_not_found' => 0,
        'errors' => 0
    ];

    foreach ($placedRows as $data) {
        $placement_id = trim($data[$headerMap['placement_id']] ?? '');
        $program_type = trim($data[$headerMap['program_type']] ?? '');
        $program = trim($data[$headerMap['program']] ?? '');
        $course = trim($data[$headerMap['course']] ?? '');
        $reg_no = trim($data[$headerMap['reg_no']] ?? '');
        $student_name = trim($data[$headerMap['student_name']] ?? '');
        $email = trim($data[$headerMap['email']] ?? '');
        $phone_no = trim($data[$headerMap['phone_no']] ?? '');
        $drive_no = trim($data[$headerMap['drive_no']] ?? '');
        $company_name = trim($data[$headerMap['company_name']] ?? '');
        $role = trim($data[$headerMap['role']] ?? '');
        $ctc = trim($data[$headerMap['ctc']] ?? '');
        $stipend = trim($data[$headerMap['stipend']] ?? '');
        $offer_type = trim($data[$headerMap['application_type']] ?? 'FTE');

        // Find student_id
        $studentStmt = $conn->prepare("SELECT student_id FROM students WHERE upid = ?");
        $studentStmt->bind_param("s", $placement_id);
        $studentStmt->execute();
        $studentResult = $studentStmt->get_result();

        if ($studentResult->num_rows === 0) {
            $skipped++;
            $skipReasons['student_not_found']++;
            $studentStmt->close();
            continue;
        }

        $student_id = $studentResult->fetch_assoc()['student_id'];
        $studentStmt->close();

        // Find drive_id
        $driveStmt = $conn->prepare("SELECT drive_id FROM drives WHERE company_name = ? AND drive_no = ?");
        $driveStmt->bind_param("ss", $company_name, $drive_no);
        $driveStmt->execute();
        $driveResult = $driveStmt->get_result();

        if ($driveResult->num_rows === 0) {
            $skipped++;
            $skipReasons['drive_not_found']++;
            $driveStmt->close();
            continue;
        }

        $drive_id = $driveResult->fetch_assoc()['drive_id'];
        $driveStmt->close();

        // Find role_id
        $roleStmt = $conn->prepare("SELECT role_id FROM drive_roles WHERE drive_id = ? AND designation_name = ?");
        $roleStmt->bind_param("is", $drive_id, $role);
        $roleStmt->execute();
        $roleResult = $roleStmt->get_result();

        if ($roleResult->num_rows === 0) {
            $skipped++;
            $skipReasons['role_not_found']++;
            $roleStmt->close();
            continue;
        }

        $role_id = $roleResult->fetch_assoc()['role_id'];
        $roleStmt->close();

        // Insert placement record
        $insertStmt = $conn->prepare("
            INSERT INTO placed_students
            (student_id, drive_id, role_id, upid, program_type, program, course, reg_no,
             student_name, email, phone_no, company_name, role, ctc, stipend, drive_no, offer_type, placement_batch)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'original')
        ");

        $typeString = "iiissssssssssssss";

        $insertStmt->bind_param(
            $typeString,
            $student_id,
            $drive_id,
            $role_id,
            $placement_id,
            $program_type,
            $program,
            $course,
            $reg_no,
            $student_name,
            $email,
            $phone_no,
            $company_name,
            $role,
            $ctc,
            $stipend,
            $drive_no,
            $offer_type
        );

        if ($insertStmt->execute()) {
            $inserted++;
        } else {
            $skipped++;
            $skipReasons['errors']++;
        }

        $insertStmt->close();
    }

    logImport("<span class='success'>✓ Placed students imported: $inserted</span>");

    if ($skipped > 0) {
        logImport("<span class='warning'>⚠ Skipped: $skipped</span>");
        if ($skipReasons['student_not_found'] > 0) {
            logImport("<span class='error'>  - Students not found: {$skipReasons['student_not_found']}</span>");
        }
        if ($skipReasons['drive_not_found'] > 0) {
            logImport("<span class='error'>  - Drives not found: {$skipReasons['drive_not_found']}</span>");
        }
        if ($skipReasons['role_not_found'] > 0) {
            logImport("<span class='error'>  - Roles not found: {$skipReasons['role_not_found']}</span>");
        }
        if ($skipReasons['errors'] > 0) {
            logImport("<span class='error'>  - Errors: {$skipReasons['errors']}</span>");
        }
    }

    // Final stats
    $totalPlaced = $conn->query("SELECT COUNT(*) AS count FROM placed_students")->fetch_assoc()['count'];
    $uniqueStudents = $conn->query("SELECT COUNT(DISTINCT student_id) AS count FROM placed_students")->fetch_assoc()['count'];

    logImport("<span class='success'>\n========================================</span>");
    logImport("<span class='success'>✓ IMPORT COMPLETED SUCCESSFULLY!</span>");
    logImport("<span class='success'>========================================</span>");
    logImport("<span class='info'>Total Placement Records: $totalPlaced</span>");
    logImport("<span class='info'>Unique Students Placed: $uniqueStudents</span>");

} catch (Exception $e) {
    logImport("<span class='error'>ERROR: " . $e->getMessage() . "</span>");
}

?>
        </div>

        <div style="margin: 20px 0;">
            <a href="placed_students.php" class="btn">
                <i class="fas fa-eye"></i> View Placed Students
            </a>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Go to Dashboard
            </a>
        </div>

        <?php endif; ?>

    </div>

    <?php include("footer.php"); ?>
</body>
</html>
