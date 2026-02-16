<?php
// Allow CLI execution
if (php_sapi_name() !== 'cli') {
    session_start();
    if (!isset($_SESSION['admin_id'])) {
        die("Error: Admin session required");
    }
}

include("config.php");
require 'vendor/autoload.php';

set_time_limit(600);
ini_set('memory_limit', '2048M');

echo "=== CLEARING AND REIMPORTING ALL 212 PLACED STUDENTS ===\n\n";

$file = 'C:/Users/Kavin/Downloads/placement_backup_all (1).xlsx';

if (!file_exists($file)) {
    die("ERROR: File not found at $file\n");
}

echo "File found: $file\n";
echo "File size: " . round(filesize($file)/1024, 2) . " KB\n\n";

try {
    // STEP 1: Clear existing placed_students table
    echo "STEP 1: Clearing placed_students table...\n";
    $conn->query("DELETE FROM placed_students");
    echo "✓ Cleared all existing records\n";
    echo "Affected rows: " . $conn->affected_rows . "\n\n";

    // STEP 2: Load Excel file
    echo "STEP 2: Loading Excel file...\n";
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

    echo "✓ Excel loaded successfully\n";
    echo "Total rows in file: " . count($rows) . "\n\n";

    // Map headers
    $headerMap = [];
    foreach ($header as $index => $colName) {
        $normalized = strtolower(trim($colName));
        $headerMap[$normalized] = $index;
    }

    echo "STEP 3: Importing students...\n";

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
                $skippedDetails[] = "Row $rowNumber: Student with UPID '$placement_id' or RegNo '$reg_no' not found";
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
            $correct_upid,  // Use correct UPID from database
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
                echo "Imported $inserted records...\n";
            }
        } else {
            $skipped++;
            $skipReasons['errors']++;
            $skippedDetails[] = "Row $rowNumber: Database error - " . $insertStmt->error;
        }

        $insertStmt->close();
        $rowNumber++;
    }

    echo "\n=== IMPORT COMPLETED ===\n";
    echo "✓ Inserted: $inserted students\n";

    if ($skipped > 0) {
        echo "⚠ Skipped: $skipped students\n";

        if ($skipReasons['student_not_found'] > 0) {
            echo "  - Students not found: {$skipReasons['student_not_found']}\n";
        }
        if ($skipReasons['drive_not_found'] > 0) {
            echo "  - Drives not found: {$skipReasons['drive_not_found']}\n";
        }
        if ($skipReasons['role_not_found'] > 0) {
            echo "  - Roles not found: {$skipReasons['role_not_found']}\n";
        }
        if ($skipReasons['errors'] > 0) {
            echo "  - Database errors: {$skipReasons['errors']}\n";
        }

        if (!empty($skippedDetails)) {
            echo "\nDetailed skip reasons (first 20):\n";
            foreach (array_slice($skippedDetails, 0, 20) as $detail) {
                echo "$detail\n";
            }
            if (count($skippedDetails) > 20) {
                echo "... and " . (count($skippedDetails) - 20) . " more\n";
            }
        }
    }

    // Get final counts
    $totalPlaced = $conn->query("SELECT COUNT(*) AS count FROM placed_students")->fetch_assoc()['count'];
    $uniquePlaced = $conn->query("SELECT COUNT(DISTINCT student_id) AS count FROM placed_students")->fetch_assoc()['count'];

    echo "\n=== FINAL DATABASE STATS ===\n";
    echo "✓ Total Placement Records: $totalPlaced\n";
    echo "✓ Unique Students Placed: $uniquePlaced\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

$conn->close();
?>
