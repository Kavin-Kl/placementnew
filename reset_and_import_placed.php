<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: index");
    exit;
}

include("config.php");
require 'vendor/autoload.php';

// Setup logging
$logFile = __DIR__ . '/logs/import_placed_students_log.txt';
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
    <title>Reset & Import Placed Students</title>
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
        .success { color: #89d185; }
        .error { color: #f48771; }
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
        <h2>Reset & Import All Placed Students</h2>
        <p>Clear all existing placed students and import fresh data from backup</p>

        <?php if ($_SERVER['REQUEST_METHOD'] !== 'POST'): ?>

        <div style="padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107; margin: 20px 0;">
            <h3 style="margin-top: 0; color: #856404;">⚠️ WARNING - THIS ACTION CANNOT BE UNDONE!</h3>
            <p><strong>This will:</strong></p>
            <ol>
                <li>DELETE all existing placed students data (currently <?php
                    $count = $conn->query("SELECT COUNT(*) as count FROM placed_students")->fetch_assoc()['count'];
                    echo $count;
                ?> records)</li>
                <li>Import all 212 fresh records from the backup file</li>
            </ol>
            <p style="color: #856404;"><strong>Make sure you have a backup before proceeding!</strong></p>
        </div>

        <form method="POST" style="margin: 20px 0;">
            <input type="hidden" name="confirm_reset" value="1">

            <label style="display: flex; align-items: center; margin: 20px 0;">
                <input type="checkbox" name="i_understand" value="1" required style="margin-right: 10px; width: 20px; height: 20px;">
                <span style="color: #dc3545; font-weight: bold;">I understand this will delete all existing placed students data</span>
            </label>

            <button type="submit" class="btn btn-danger" onclick="return confirm('⚠️ FINAL WARNING ⚠️\n\nThis will DELETE all ' + <?= $count ?> + ' existing placed student records.\n\nAre you absolutely sure?');">
                <i class="fas fa-trash-restore"></i> Clear & Import All 212 Records
            </button>
            <a href="placed_students.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancel
            </a>
        </form>

        <?php else: ?>

        <div class="log-output">
<?php

if (!isset($_POST['i_understand'])) {
    logImport("<span class='error'>ERROR: Confirmation checkbox not checked</span>");
    exit;
}

logImport("<span class='warning'>=== RESET & IMPORT STARTED ===</span>");
logImport("User: " . ($_SESSION['admin_id'] ?? 'Unknown'));

$file = 'C:/Users/Kavin/Downloads/placement_backup_all (1).xlsx';

if (!file_exists($file)) {
    logImport("<span class='error'>ERROR: File not found at $file</span>");
    exit;
}

try {
    // STEP 1: Clear existing data
    logImport("<span class='warning'>STEP 1: Clearing existing placed_students table...</span>");

    $deleteResult = $conn->query("DELETE FROM placed_students");
    if ($deleteResult) {
        logImport("<span class='success'>✓ Successfully deleted all existing records</span>");
        logImport("<span class='info'>Deleted rows: " . $conn->affected_rows . "</span>");
    } else {
        throw new Exception("Failed to clear table: " . $conn->error);
    }

    // STEP 2: Import all records
    logImport("<span class='info'>STEP 2: Starting import of all records...</span>");
    logImport("<span class='info'>File: $file</span>");
    logImport("<span class='info'>File size: " . round(filesize($file)/1024, 2) . " KB</span>");

    // Read Excel file
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($file);

    $worksheet = $spreadsheet->getSheetByName('On_Campus_Placed_Students');

    if (!$worksheet) {
        throw new Exception("Sheet 'On_Campus_Placed_Students' not found");
    }

    $rows = $worksheet->toArray(null, false, false, false);
    $header = array_shift($rows);

    // Remove empty rows
    $rows = array_filter($rows, function($row) {
        return !empty(array_filter($row));
    });

    logImport("<span class='success'>Excel file loaded successfully</span>");
    logImport("<span class='info'>Total students in file: " . count($rows) . "</span>");

    // Map headers
    $headerMap = [];
    foreach ($header as $index => $colName) {
        $normalized = strtolower(trim($colName));
        $headerMap[$normalized] = $index;
    }

    $inserted = 0;
    $skipped = 0;
    $skipReasons = [
        'student_not_found' => 0,
        'drive_not_found' => 0,
        'role_not_found' => 0,
        'errors' => 0
    ];
    $skippedDetails = [];

    $rowNumber = 2;
    foreach ($rows as $data) {
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
        $offer_letter_accepted = trim($data[$headerMap['offer_letter_accepted']] ?? 'unknown');
        $offer_letter_received = trim($data[$headerMap['offer_letter_received']] ?? 'unknown');
        $joining_status = trim($data[$headerMap['joining_status']] ?? 'unknown');
        $comment = trim($data[$headerMap['comment']] ?? '');
        $filled_on_off_form = trim($data[$headerMap['filled_on_off_form']] ?? 'not filled');
        $offer_type = trim($data[$headerMap['application_type']] ?? 'FTE');

        // Find student_id by placement_id (UPID)
        $studentStmt = $conn->prepare("SELECT student_id FROM students WHERE upid = ?");
        $studentStmt->bind_param("s", $placement_id);
        $studentStmt->execute();
        $studentResult = $studentStmt->get_result();

        if ($studentResult->num_rows === 0) {
            $skipped++;
            $skipReasons['student_not_found']++;
            $skippedDetails[] = "Row $rowNumber: Student with UPID '$placement_id' not found";
            $studentStmt->close();
            $rowNumber++;
            continue;
        }

        $student_id = $studentResult->fetch_assoc()['student_id'];
        $studentStmt->close();

        // Find drive_id by company_name and drive_no
        $driveStmt = $conn->prepare("SELECT drive_id FROM drives WHERE company_name = ? AND drive_no = ?");
        $driveStmt->bind_param("ss", $company_name, $drive_no);
        $driveStmt->execute();
        $driveResult = $driveStmt->get_result();

        if ($driveResult->num_rows === 0) {
            $skipped++;
            $skipReasons['drive_not_found']++;
            $skippedDetails[] = "Row $rowNumber: Drive not found for '$company_name - $drive_no'";
            $driveStmt->close();
            $rowNumber++;
            continue;
        }

        $drive_id = $driveResult->fetch_assoc()['drive_id'];
        $driveStmt->close();

        // Find role_id by drive_id and role name
        $roleStmt = $conn->prepare("SELECT role_id FROM drive_roles WHERE drive_id = ? AND designation_name = ?");
        $roleStmt->bind_param("is", $drive_id, $role);
        $roleStmt->execute();
        $roleResult = $roleStmt->get_result();

        if ($roleResult->num_rows === 0) {
            $skipped++;
            $skipReasons['role_not_found']++;
            $skippedDetails[] = "Row $rowNumber: Role '$role' not found for drive '$company_name'";
            $roleStmt->close();
            $rowNumber++;
            continue;
        }

        $role_id = $roleResult->fetch_assoc()['role_id'];
        $roleStmt->close();

        // Normalize enum values
        if (!in_array($offer_letter_accepted, ['yes', 'no', 'unknown'])) {
            $offer_letter_accepted = 'unknown';
        }
        if (!in_array($offer_letter_received, ['yes', 'no', 'unknown'])) {
            $offer_letter_received = 'unknown';
        }
        if (!in_array($joining_status, ['joined', 'not_joined', 'unknown'])) {
            $joining_status = 'unknown';
        }

        // Insert into placed_students
        $insertStmt = $conn->prepare("
            INSERT INTO placed_students
            (student_id, drive_id, role_id, upid, program_type, program, course, reg_no,
             student_name, email, phone_no, company_name, role, ctc, stipend, drive_no, offer_type,
             offer_letter_accepted, offer_letter_received, joining_status, comment, filled_on_off_form, placement_batch)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'original')
        ");

        $typeString = "iiisssssssssssssssssss";

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
            $offer_type,
            $offer_letter_accepted,
            $offer_letter_received,
            $joining_status,
            $comment,
            $filled_on_off_form
        );

        if ($insertStmt->execute()) {
            $inserted++;
        } else {
            $skipped++;
            $skipReasons['errors']++;
            $skippedDetails[] = "Row $rowNumber: Database error - " . $insertStmt->error;
        }

        $insertStmt->close();
        $rowNumber++;
    }

    logImport("<span class='success'>=== IMPORT COMPLETED ===</span>");
    logImport("<span class='success'>✓ Inserted: $inserted students</span>");

    if ($skipped > 0) {
        logImport("<span class='warning'>⚠ Skipped: $skipped students</span>");

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
            logImport("<span class='error'>  - Database errors: {$skipReasons['errors']}</span>");
        }

        if (!empty($skippedDetails)) {
            logImport("<span class='warning'>\nDetailed skip reasons (first 20):</span>");
            foreach (array_slice($skippedDetails, 0, 20) as $detail) {
                logImport("<span class='warning'>$detail</span>");
            }
            if (count($skippedDetails) > 20) {
                logImport("<span class='warning'>... and " . (count($skippedDetails) - 20) . " more</span>");
            }
        }
    }

    // Get final counts
    $totalPlaced = $conn->query("SELECT COUNT(DISTINCT student_id) AS count FROM placed_students")->fetch_assoc()['count'];
    $totalOffers = $conn->query("SELECT COUNT(*) AS count FROM placed_students")->fetch_assoc()['count'];

    logImport("<span class='info'>\n=== FINAL DATABASE STATS ===</span>");
    logImport("<span class='success'>✓ Total Unique Students Placed: $totalPlaced</span>");
    logImport("<span class='success'>✓ Total Placement Records: $totalOffers</span>");

} catch (Exception $e) {
    logImport("<span class='error'>ERROR: " . $e->getMessage() . "</span>");
}

?>
        </div>

        <div style="margin: 20px 0;">
            <a href="placed_students.php" class="btn">
                <i class="fas fa-arrow-left"></i> Go to Placed Students
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
