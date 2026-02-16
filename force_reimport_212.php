<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    die("<h1>Error: Admin login required</h1><p>Please <a href='index.php'>login</a> first.</p>");
}

include("config.php");
require 'vendor/autoload.php';

set_time_limit(600);
ini_set('memory_limit', '2048M');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Force Reimport All 212 Placed Students</title>
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
        .stats-box {
            background: #650000;
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .stats-box h2 {
            margin: 0 0 10px 0;
            color: white;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Force Reimport All 212 Placed Students</h1>

<?php

$file = 'C:/Users/Kavin/Downloads/placement_backup_all (1).xlsx';

if (!file_exists($file)) {
    echo "<div class='log-line error'>ERROR: File not found at $file</div>";
    echo "<p>Please ensure the backup file exists at this location.</p>";
    exit;
}

try {
    echo "<div class='log-line info'>📁 File found: $file</div>";
    echo "<div class='log-line info'>📊 File size: " . round(filesize($file)/1024, 2) . " KB</div>";
    echo "<br>";

    // STEP 1: Clear existing placed_students table
    echo "<div class='log-line warning'>⚠️ STEP 1: Clearing placed_students table...</div>";

    $deleteResult = $conn->query("DELETE FROM placed_students");
    if ($deleteResult) {
        $deletedRows = $conn->affected_rows;
        echo "<div class='log-line success'>✓ Successfully deleted $deletedRows existing records</div>";
    } else {
        throw new Exception("Failed to clear table: " . $conn->error);
    }
    echo "<br>";

    // STEP 2: Load Excel file
    echo "<div class='log-line info'>📂 STEP 2: Loading Excel file...</div>";
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

    echo "<div class='log-line success'>✓ Excel loaded successfully</div>";
    echo "<div class='log-line info'>📋 Total rows in file: " . count($rows) . "</div>";
    echo "<br>";

    // Map headers
    $headerMap = [];
    foreach ($header as $index => $colName) {
        $normalized = strtolower(trim($colName));
        $headerMap[$normalized] = $index;
    }

    echo "<div class='log-line info'>🔄 STEP 3: Importing students...</div>";

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

        // Find student_id by placement_id (UPID), fallback to reg_no
        $studentStmt = $conn->prepare("SELECT student_id, upid FROM students WHERE upid = ?");
        $studentStmt->bind_param("s", $placement_id);
        $studentStmt->execute();
        $studentResult = $studentStmt->get_result();

        if ($studentResult->num_rows === 0) {
            // Fallback to reg_no
            $studentStmt->close();
            $studentStmt = $conn->prepare("SELECT student_id, upid FROM students WHERE reg_no = ?");
            $studentStmt->bind_param("s", $reg_no);
            $studentStmt->execute();
            $studentResult = $studentStmt->get_result();

            if ($studentResult->num_rows === 0) {
                $skipped++;
                $skipReasons['student_not_found']++;
                $skippedDetails[] = "Row $rowNumber: Student '$student_name' (UPID: $placement_id, RegNo: $reg_no) not found";
                $studentStmt->close();
                $rowNumber++;
                continue;
            }
        }

        $studentData = $studentResult->fetch_assoc();
        $student_id = $studentData['student_id'];
        $correct_upid = $studentData['upid']; // Use UPID from database
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

        // Insert into placed_students with correct UPID from database
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
            $correct_upid,
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
            if ($inserted % 50 == 0) {
                echo "<div class='log-line info'>⏳ Imported $inserted records...</div>";
                flush();
                ob_flush();
            }
        } else {
            $skipped++;
            $skipReasons['errors']++;
            $skippedDetails[] = "Row $rowNumber: Database error - " . $insertStmt->error;
        }

        $insertStmt->close();
        $rowNumber++;
    }

    echo "<br>";
    echo "<div class='log-line success'>═══ IMPORT COMPLETED ═══</div>";
    echo "<div class='log-line success'>✓ Inserted: $inserted students</div>";

    if ($skipped > 0) {
        echo "<div class='log-line warning'>⚠ Skipped: $skipped students</div>";

        if ($skipReasons['student_not_found'] > 0) {
            echo "<div class='log-line error'>  - Students not found: {$skipReasons['student_not_found']}</div>";
        }
        if ($skipReasons['drive_not_found'] > 0) {
            echo "<div class='log-line error'>  - Drives not found: {$skipReasons['drive_not_found']}</div>";
        }
        if ($skipReasons['role_not_found'] > 0) {
            echo "<div class='log-line error'>  - Roles not found: {$skipReasons['role_not_found']}</div>";
        }
        if ($skipReasons['errors'] > 0) {
            echo "<div class='log-line error'>  - Database errors: {$skipReasons['errors']}</div>";
        }

        if (!empty($skippedDetails)) {
            echo "<br><div class='log-line warning'>📋 Detailed skip reasons (first 20):</div>";
            foreach (array_slice($skippedDetails, 0, 20) as $detail) {
                echo "<div class='log-line warning'>  $detail</div>";
            }
            if (count($skippedDetails) > 20) {
                echo "<div class='log-line warning'>  ... and " . (count($skippedDetails) - 20) . " more</div>";
            }
        }
    }

    // Get final counts
    echo "<br>";
    $totalPlaced = $conn->query("SELECT COUNT(*) AS count FROM placed_students")->fetch_assoc()['count'];
    $uniquePlaced = $conn->query("SELECT COUNT(DISTINCT student_id) AS count FROM placed_students")->fetch_assoc()['count'];

    // Get FTE vs Internship breakdown
    $fteCount = $conn->query("SELECT COUNT(*) AS count FROM placed_students WHERE offer_type != 'Internship' OR offer_type IS NULL")->fetch_assoc()['count'];
    $internshipCount = $conn->query("SELECT COUNT(*) AS count FROM placed_students WHERE offer_type = 'Internship'")->fetch_assoc()['count'];

    echo "<div class='stats-box'>";
    echo "<h2>📊 FINAL DATABASE STATISTICS</h2>";
    echo "<div style='font-size: 18px;'>";
    echo "✓ Total Placement Records: <strong>$totalPlaced</strong><br>";
    echo "✓ Unique Students Placed: <strong>$uniquePlaced</strong><br>";
    echo "✓ FTE Placements: <strong>$fteCount</strong><br>";
    echo "✓ Internship Placements: <strong>$internshipCount</strong><br>";
    echo "</div>";
    echo "</div>";

    echo "<div style='margin: 20px 0;'>";
    echo "<a href='dashboard.php' class='btn'>📈 Go to Dashboard</a>";
    echo "<a href='placed_students.php' class='btn'>👥 View Placed Students</a>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='log-line error'>❌ ERROR: " . $e->getMessage() . "</div>";
    echo "<div class='log-line error'>Stack trace: " . nl2br(htmlspecialchars($e->getTraceAsString())) . "</div>";
}

$conn->close();
?>

    </div>
</body>
</html>
