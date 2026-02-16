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
    <title>Import Placed Students</title>
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
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #650000;
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

    <div class="import-container">
        <h2>Import Placed Students from Backup</h2>
        <p>Import placed students data from "placement_backup_all (1).xlsx"</p>

        <?php if ($_SERVER['REQUEST_METHOD'] !== 'POST'): ?>

        <form method="POST" style="margin: 20px 0;">
            <input type="hidden" name="confirm_import" value="1">

            <div style="margin: 20px 0;">
                <label style="display: flex; align-items: center; padding: 15px; background: #f8f9fa; border-radius: 5px; cursor: pointer;">
                    <input type="checkbox" name="skip_duplicates" value="1" checked style="margin-right: 10px; width: 20px; height: 20px;">
                    <span><strong>Skip Duplicates</strong> - Only import students that don't already exist (recommended)</span>
                </label>
                <p style="margin: 10px 0 0 35px; color: #666; font-size: 14px;">
                    Unchecking this will import ALL 212 records, even if they already exist (may create duplicates)
                </p>
            </div>

            <p style="padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; margin: 20px 0;">
                <strong>⚠️ Important:</strong> This will import up to 212 placed students from the backup file.
            </p>
            <button type="submit" class="btn" onclick="return confirm('Are you sure you want to import placed students? This will add new records to the database.');">
                <i class="fas fa-upload"></i> Start Import
            </button>
            <a href="placed_students.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancel
            </a>
        </form>

        <?php else: ?>

        <div class="log-output">
<?php

logImport("=== PLACED STUDENTS IMPORT STARTED ===");
logImport("User: " . ($_SESSION['admin_id'] ?? 'Unknown'));

$file = 'C:/Users/Kavin/Downloads/placement_backup_all (1).xlsx';

if (!file_exists($file)) {
    logImport("<span class='error'>ERROR: File not found at $file</span>");
    exit;
}

logImport("<span class='info'>File found: $file</span>");
logImport("<span class='info'>File size: " . round(filesize($file)/1024, 2) . " KB</span>");

try {
    // Read Excel file
    logImport("<span class='info'>Loading Excel file...</span>");
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
    logImport("<span class='info'>Headers: " . implode(', ', $header) . "</span>");

    // Map headers
    $headerMap = [];
    foreach ($header as $index => $colName) {
        $normalized = strtolower(trim($colName));
        $headerMap[$normalized] = $index;
    }

    logImport("<span class='info'>Starting import process...</span>");

    $skipDuplicates = isset($_POST['skip_duplicates']) && $_POST['skip_duplicates'] == '1';
    logImport("<span class='info'>Skip duplicates mode: " . ($skipDuplicates ? 'ENABLED' : 'DISABLED - Will import all records') . "</span>");

    $inserted = 0;
    $skipped = 0;
    $skipReasons = [
        'student_not_found' => 0,
        'drive_not_found' => 0,
        'role_not_found' => 0,
        'duplicates' => 0,
        'errors' => 0
    ];
    $skippedDetails = [];

    // Get all existing placements to avoid duplicates (only if skip_duplicates is enabled)
    $existingPlacements = [];
    if ($skipDuplicates) {
        $existingQuery = "
            SELECT CONCAT(ps.upid, '-', ps.company_name, '-', ps.role) as placement_key
            FROM placed_students ps
        ";
        $existingResult = $conn->query($existingQuery);
        while ($row = $existingResult->fetch_assoc()) {
            $existingPlacements[$row['placement_key']] = true;
        }
        logImport("<span class='info'>Found " . count($existingPlacements) . " existing placements in database</span>");
    } else {
        logImport("<span class='warning'>Duplicate check disabled - will import all records</span>");
    }

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

        // Check for duplicate (only if skip_duplicates is enabled)
        if ($skipDuplicates) {
            $placementKey = "$placement_id-$company_name-$role";
            if (isset($existingPlacements[$placementKey])) {
                $skipped++;
                $skipReasons['duplicates']++;
                $rowNumber++;
                continue;
            }
        }

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

        // Type string: 3 integers (iii) + 19 strings (19 s's) = 22 parameters
        $typeString = "iiisssssssssssssssssss"; // 22 characters total (3 i's + 19 s's)

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
            if ($skipDuplicates) {
                $placementKey = "$placement_id-$company_name-$role";
                $existingPlacements[$placementKey] = true; // Mark as inserted
            }
        } else {
            $skipped++;
            $skipReasons['errors']++;
            $skippedDetails[] = "Row $rowNumber: Database error - " . $insertStmt->error;
        }

        $insertStmt->close();
        $rowNumber++;
    }

    logImport("<span class='success'>=== IMPORT COMPLETED ===</span>");
    logImport("<span class='success'>Inserted: $inserted students</span>");
    logImport("<span class='warning'>Skipped: $skipped students</span>");

    if ($skipReasons['duplicates'] > 0) {
        logImport("<span class='warning'>  - Duplicates: {$skipReasons['duplicates']}</span>");
    }
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

    // Get updated counts
    $totalPlaced = $conn->query("SELECT COUNT(DISTINCT student_id) AS count FROM placed_students")->fetch_assoc()['count'];
    $totalOffers = $conn->query("SELECT COUNT(DISTINCT place_id) AS count FROM placed_students")->fetch_assoc()['count'];

    logImport("<span class='info'>\nUpdated Database Stats:</span>");
    logImport("<span class='info'>Total Unique Students Placed: $totalPlaced</span>");
    logImport("<span class='info'>Total Offers: $totalOffers</span>");

} catch (Exception $e) {
    logImport("<span class='error'>ERROR: " . $e->getMessage() . "</span>");
    logImport("<span class='error'>Stack trace: " . $e->getTraceAsString() . "</span>");
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
