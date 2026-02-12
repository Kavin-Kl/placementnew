<?php
date_default_timezone_set('Asia/Kolkata');
/*echo "Current Time: " . date("Y-m-d H:i:s");*/
require 'vendor/autoload.php';
require_once 'config.php';
require_once 'backup_engine.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
// ==== AUTO DETECT ====
$currentYear  = date('Y');
$currentMonth = date('m');
$currentMonthName = date('F');

// Detect latest batch from students table
$batchResult = mysqli_query($conn, "SELECT batch FROM students ORDER BY batch DESC LIMIT 1");
$latestBatch = ($batchResult && mysqli_num_rows($batchResult) > 0) ? mysqli_fetch_assoc($batchResult)['batch'] : $currentYear;

// ==== FOLDER SETUP ====
$folder = __DIR__ . "/backups/DATA/$currentYear/$currentMonth";
if (!is_dir($folder)) mkdir($folder, 0700, true);

// File paths
$files = [
    'latest'    => "$folder/placement_backup_latest.xlsx",
];

// ==== Helper: Get selected columns except excluded ====
function getSelectedColumns($table, $exclude = [], $conn, $alias = null) {
    $cols = [];
    $res = mysqli_query($conn, "SHOW COLUMNS FROM $table");
    while ($row = mysqli_fetch_assoc($res)) {
        if (!in_array($row['Field'], $exclude)) {
            $fieldName = ($alias ? "$alias." : "") . $row['Field'];
            $cols[] = $fieldName;
        }
    }
    return implode(',', $cols);
}

// ==== FUNCTION TO GENERATE BACKUP ====
function generateBackup($conn, $filePath, $batch = '', $year = '', $month = '') {
    $whereOnOff   = "1=1";
    $whereApp     = "1=1";
    $whereDrive   = "1=1";
    $wherePlaced  = "1=1";
    $whereTracker = "1=1";
    $whereRegistered = "1=1";

    // ✅ Batch Filter
    if ($batch) {
    // ✅ Use correct batch/year columns for each table
    $whereOnOff       .= " AND passing_year = '$batch'";
    $whereApp         .= " AND placement_batch = '$batch'";
    // $whereDrive has no batch column, so leave it as-is
    $wherePlaced      .= " AND placement_batch = '$batch'";
    $whereRegistered  .= " AND year_of_passing = '$batch'";
    $whereTracker = "1=0"; // Always false, so no rows are selected
}


    // ✅ Year Filter
    if ($year) {
        $whereOnOff   .= " AND YEAR(submitted_at) = '$year'";
        $whereApp     .= " AND YEAR(applied_at) = '$year'";
        $whereDrive   .= " AND YEAR(d.created_at) = '$year'";
        $wherePlaced  .= " AND YEAR(placed_date) = '$year'";
        $whereTracker .= " AND YEAR(updated_at) = '$year'";
        $whereRegistered .= " AND YEAR(created_at) = '$year'";
    }

    // ✅ Month Filter
    if ($month) {
        $whereOnOff   .= " AND MONTH(submitted_at) = '$month'";
        $whereApp     .= " AND MONTH(applied_at) = '$month'";
        $whereDrive   .= " AND MONTH(d.created_at) = '$month'";
        $wherePlaced  .= " AND MONTH(placed_date) = '$month'";
        $whereTracker .= " AND MONTH(updated_at) = '$month'";
        $whereRegistered .= " AND MONTH(created_at) = '$month'";
    }

    $spreadsheet = new Spreadsheet();
    $spreadsheet->removeSheetByIndex(0);


// Drives + Drive Roles
$driveCols     = getSelectedColumns('drives', ['drive_id', 'created_at','jd_file','form_link','extra_details','jd_link'], $conn, 'd');
$driveRoleCols = getSelectedColumns('drive_roles', ['drive_id','role_id','created_at','is_finished','form_fields'], $conn, 'dr');
$query = "SELECT $driveCols, $driveRoleCols
          FROM drives d
          LEFT JOIN drive_roles dr ON d.drive_id = dr.drive_id
          WHERE $whereDrive";
exportSheet($spreadsheet, 'Drives', $query, $conn);



// Applications

$appCols = "
    a.upid AS Placement_id,
    a.reg_no,
    a.course,
    a.percentage,
    d.company_name,
    dr.designation_name,
    a.priority,
    a.status,
    a.comments,
    a.applied_at,
    a.student_data,
    a.resume_file
";

$query = "
    SELECT $appCols
    FROM applications a
    LEFT JOIN drives d ON a.drive_id = d.drive_id
    LEFT JOIN drive_roles dr ON a.role_id = dr.role_id
    WHERE $whereApp
";

exportSheet($spreadsheet, 'Applications', $query, $conn, true);



// Register Students
// Register Students (excluding unwanted columns)
$columns = getSelectedColumns('students', [
    'student_id',     // ❌ remove
    'class',          // ❌ remove
    'applied_date',   // ❌ remove
    'role',           // ❌ remove
    'ctc',            // ❌ remove
    'percentage',     // ❌ remove
    'comments',       // ❌ remove
    'password',       // already excluded
    'photo',          // already excluded
    'created_at'      // already excluded
], $conn);
// Replace 'up_id' with alias 'up_id AS Placement_id'
$columns = str_replace('upid', 'upid AS Placement_id', $columns);
$query = "SELECT $columns FROM students WHERE $whereRegistered";
exportSheet($spreadsheet, 'Register Students', $query, $conn);

// Placed Students
$columns = "
    upid AS Placement_id,
    program_type,
    program,
    course,
    reg_no,
    student_name,
    email,
    phone_no,
    drive_no,
    company_name,
    role,
    ctc,
    stipend,
    offer_letter_accepted,
    offer_letter_received,
    joining_status,
    comment,
    filled_on_off_form,
    placement_batch AS Application_type
";

$query = "SELECT $columns FROM placed_students WHERE $wherePlaced";
exportSheet($spreadsheet, 'On_Campus_Placed_Students', $query, $conn);


// OnOffCampus
$columns = getSelectedColumns('on_off_campus_students', ['external_id', 'submitted_at'], $conn);
$columns = str_replace('upid', 'upid AS Placement_id', $columns); 
$query = "SELECT $columns FROM on_off_campus_students WHERE $whereOnOff";
exportSheet($spreadsheet, 'Overall_Placed_Students', $query, $conn);





// Company Tracker
// Company Tracker with drives join for extra fields
$columns = "
    cp.company_name,
    cp.drive_no,
    cp.role,
    d.created_by,
    cp.offer_type,
    cp.sector,
    cp.eligible_courses,
    d.open_date AS `Form Open Date`,
    d.close_date AS `Role Close Date`,
    cp.spo_name,
    cp.contact_no,
    cp.follow_status,
    cp.final_status,
    cp.hired_count,
    cp.follow_up_person,
    cp.updated_at
";

$query = "
    SELECT $columns
    FROM drive_data cp
    LEFT JOIN drives d ON cp.drive_id = d.drive_id
    WHERE $whereTracker
";

exportSheet($spreadsheet, 'Company Tracker', $query, $conn);


try {
    $writer = new Xlsx($spreadsheet);
    $writer->save($filePath);
} catch (Exception $e) {
    error_log("❌ Excel Save Error: " . $e->getMessage());
}

}
// ==== GENERATE EXCEL FILES ==== 
generateBackup($conn, $files['latest']);
echo "✅ Backups created: Excel + SQL dump ({$currentYear}, {$currentMonthName}, Batch {$latestBatch})";

// ==== FUNCTION TO GENERATE MYSQL DUMP ====
function deleteOldSqlBackups($folder, $daysToKeep = 7) {
    echo "Checking folder for SQL files: $folder\n";

    $files = glob($folder . '/*.sql');
    if (!$files) {
        echo "No SQL files found.\n";
        return;
    }

    $now = time();

    foreach ($files as $file) {
        if (is_file($file)) {
            $fileModified = filemtime($file);
            $ageInDays = ($now - $fileModified) / (60 * 60 * 24);

            echo "Found file: " . basename($file) . " (Age: " . round($ageInDays, 2) . " days)\n";

            if ($ageInDays > $daysToKeep) {
                if (unlink($file)) {
                    echo "[DELETED] " . basename($file) . "\n";
                } else {
                    echo "[FAILED TO DELETE] " . basename($file) . "\n";
                }
            }
        }
    }
}


function generateSqlDump($filePath, $dbHost, $dbUser, $dbPass, $dbName,$mysqldumpPath) {
/*
$mysqldumpPath = findMysqldump();*/
// example Windows path

// Now use $mysqldumpPath in your command

    $dir = dirname($filePath);

    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    // Remove password from command line, use ~/.my.cnf instead (must be set up securely)
    // Current line with password part
$passwordPart = $dbPass !== "" ? "-p\"$dbPass\"" : "";
$command = "\"$mysqldumpPath\" --routines --triggers --events --single-transaction -h {$dbHost} -u {$dbUser} {$passwordPart} {$dbName} > \"{$filePath}\"";
    system($command, $retval);

    $logFile = $dir . "/backup_log.txt";
    if ($retval !== 0) {
        file_put_contents($logFile, "[❌ ERROR] " . date("Y-m-d H:i:s") . "\nCommand: $command\n\n", FILE_APPEND);
    } else {
        file_put_contents($logFile, "[✅ SUCCESS] Dump completed at " . date("Y-m-d H:i:s") . "\nFile: $filePath\n\n", FILE_APPEND);
    }
}

// ==== AUTO-DETECT FILTER CONTEXT ====
// Try to fetch latest batch from DB
$batchFilter = $latestBatch ?? null;
// ==== DYNAMIC FILE NAMING LOGIC ====
// Priority: If batch exists → batch naming; else → monthly; else → all

$fileName = "placement_backup";

if (!empty($latestBatch)) {
    $fileName .= "_Batch_{$latestBatch}.xlsx";
    $batchFilter = $latestBatch;
} elseif (!empty($currentMonth) && !empty($currentYear)) {
    $fileName .= "_{$currentMonthName}_{$currentYear}.xlsx";
    $filterType = 'month';
} else {
    $fileName .= "_all.xlsx";
    $filterType = 'all';
}


// ==== FILE PATHS ====
$backupDir = __DIR__ . "/backups/DATA/$currentYear/$currentMonth";

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0700, true);
}
$dateString = date('d-m-Y');
$sqlFilePath = $backupDir . "/placement_backup_{$dateString}.sql";
$excelFilePath = $backupDir . "/" . $fileName;

// ==== GENERATE SQL DUMP ====
deleteOldSqlBackups($backupDir, 7);
generateSqlDump($sqlFilePath, $host, $user, $pass, $db,$mysqldumpPath);
chmod($sqlFilePath, 0600);
// ==== GENERATE EXCEL BACKUP ====
// You can conditionally pass filters to generateBackup() if needed
// For now, pass everything just in case

// ==== DONE ====
echo "✅ Backup created:\n- SQL: {$sqlFilePath}\n- Excel: {$excelFilePath}";

