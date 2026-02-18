<?php
// Test script to debug vantage import
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
$_SESSION['admin_id'] = 1; // Simulate admin session

include("config.php");
require 'vendor/autoload.php';

set_time_limit(600);
ini_set('memory_limit', '2048M');
ini_set('max_execution_time', '600');

$logFile = __DIR__ . '/logs/test_import_log.txt';
if (!file_exists(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0777, true);
}

function logImport($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

logImport("=== TEST IMPORT STARTED ===");

$filePath = 'C:/Users/Kavin/Downloads/Vantage_Registered_List_2025-2026.xlsx';

if (!file_exists($filePath)) {
    logImport("ERROR: File not found at $filePath");
    die("File not found");
}

logImport("File found: $filePath");
logImport("File size: " . round(filesize($filePath)/1024, 2) . " KB");

$fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
logImport("File extension: $fileExt");

// Extract batch year from filename
$fileName = basename($filePath);
if (preg_match('/(\d{4})-(\d{4})/', $fileName, $matches)) {
    $batchYear = $matches[0];
    $yearOfPassing = (int) $matches[2];
    logImport("Batch year extracted: $batchYear (Year of Passing: $yearOfPassing)");
} else {
    logImport("ERROR: Could not extract batch year from filename");
    die("Invalid filename format");
}

try {
    logImport("Creating Excel reader...");
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
    $reader->setReadDataOnly(true);
    $reader->setReadEmptyCells(false);

    logImport("Loading Excel file...");
    $spreadsheet = $reader->load($filePath);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray(null, false, false, false);
    $header = array_shift($rows);

    // Remove empty rows
    $dataRows = array_filter($rows, function($row) {
        return !empty(array_filter($row));
    });

    logImport("Excel file parsed successfully: " . count($dataRows) . " data rows found");
    logImport("Headers found: " . implode(', ', array_filter($header)));

    // Map headers to internal field names
    $headerPatterns = [
        'upid'          => ['placement id', 'upid', 'placement key id', 'Placement Id', 'Placement ID'],
        'program_type'  => ['program type', 'Program Type'],
        'program'       => ['program', 'Program'],
        'course'        => ['course', 'Course'],
        'reg_no'        => ['Student Register Number', 'Register Number', 'Register number', 'Register No', 'reg no', 'register no', 'regno', 'regno:', 'reg no:', 'register no:'],
        'student_name'  => ['Student Name', 'name', 'student name', 'Student name', 'student'],
        'email'         => ['Student Mail ID', 'Student Email ID', 'Student Email', 'Student email', 'email', 'mail', 'mail id', 'email address', 'email id'],
        'phone_no'      => ['Student Phone No', 'Student Mobile No', 'student phone no', 'student mobile no', 'phone', 'mobile', 'mobile no', 'mobile number', 'phone number', 'phone no.', 'mobile no.'],
        'percentage'    => ['percentage', 'Percentage', 'percent', 'score', 'grade', 'cgpa'],
    ];

    $headerMap = [];
    foreach ($header as $index => $colName) {
        $normalized = strtolower(trim($colName));
        $normalized = preg_replace('/[^a-z0-9]/', '', $normalized);

        foreach ($headerPatterns as $field => $patterns) {
            foreach ($patterns as $pattern) {
                $normPattern = preg_replace('/[^a-z0-9]/', '', strtolower($pattern));
                if ($normalized === $normPattern) {
                    $headerMap[$field] = $index;
                    logImport("Mapped column '$colName' (index $index) to field '$field'");
                    break 2;
                }
            }
        }
    }

    logImport("Header mapping completed. Mapped fields: " . implode(', ', array_keys($headerMap)));

    // Check for missing required columns
    $expectedColumns = array_keys($headerPatterns);
    $missingColumns = [];

    foreach ($expectedColumns as $col) {
        if (!isset($headerMap[$col])) {
            $missingColumns[] = $col;
        }
    }

    if (!empty($missingColumns)) {
        logImport("ERROR: Missing required columns: " . implode(', ', $missingColumns));
        die("Missing columns");
    }

    logImport("All required columns found. Starting data validation...");

    // Check first 5 rows
    $rowNumber = 2;
    $checked = 0;
    foreach ($dataRows as $data) {
        if ($checked >= 5) break;

        $upid          = trim($data[$headerMap['upid']] ?? '');
        $program_type  = trim($data[$headerMap['program_type']] ?? '');
        $program       = trim($data[$headerMap['program']] ?? '');
        $course        = trim($data[$headerMap['course']] ?? '');
        $reg_no        = trim($data[$headerMap['reg_no']] ?? '');
        $student_name  = trim($data[$headerMap['student_name']] ?? '');
        $email         = trim($data[$headerMap['email']] ?? '');
        $phone_no      = trim($data[$headerMap['phone_no']] ?? '');
        $percentage    = isset($headerMap['percentage']) && !empty($data[$headerMap['percentage']]) ? (float)$data[$headerMap['percentage']] : null;

        logImport("Row $rowNumber: UPID=$upid, RegNo=$reg_no, Name=$student_name, Email=$email");

        // Check if UPID exists
        $checkStmt = $conn->prepare("SELECT upid FROM students WHERE upid = ?");
        if ($checkStmt) {
            $checkStmt->bind_param('s', $upid);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            if ($result->num_rows > 0) {
                logImport("  -> UPID already exists in database");
            } else {
                logImport("  -> UPID is new, can be inserted");
            }
            $checkStmt->close();
        }

        $rowNumber++;
        $checked++;
    }

    logImport("=== TEST COMPLETED SUCCESSFULLY ===");
    logImport("Total rows to import: " . count($dataRows));

} catch (Exception $e) {
    logImport("ERROR: " . $e->getMessage());
    logImport("Stack trace: " . $e->getTraceAsString());
}

$conn->close();
?>
