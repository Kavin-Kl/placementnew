<?php
require 'vendor/autoload.php';
require_once 'config.php';
require_once 'backup_engine.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Get filter type from UI
$filterType   = $_GET['filter'] ?? '';
$monthFilter  = $_GET['month_filter'] ?? '';
$yearFilter   = $_GET['year_filter'] ?? '';
$batchFilter  = $_GET['batch_filter'] ?? '';
$fromDate     = $_GET['from_date'] ?? '';
$toDate       = $_GET['to_date'] ?? '';

// Validate filter inputs before proceeding
if ($filterType === 'year' && empty($yearFilter)) {
    die('❌ Year filter is selected but no year value was provided.');
}

if ($filterType === 'batch' && empty($batchFilter)) {
    die('❌ Batch filter is selected but no batch year was provided.');
}

if ($filterType === 'range' && (empty($fromDate) || empty($toDate))) {
    die('❌ Date range filter is selected but start or end date is missing.');
}

// === Naming Logic ===
$fileName = "placement_backup";
if ($filterType === "month" && $monthFilter) {
    $monthNum = date('m', strtotime($monthFilter . "-01"));
    $monthName = date('F', strtotime($monthFilter . "-01"));
    $yearName = date('Y', strtotime($monthFilter . "-01"));
    $fileName .= "_{$monthName}_{$yearName}.xlsx";
} elseif ($filterType === "year" && $yearFilter) {
    $fileName .= "_{$yearFilter}.xlsx";
} elseif ($filterType === "batch" && $batchFilter) {
    $fileName .= "_Batch{$batchFilter}.xlsx";
} elseif ($filterType === "range" && $fromDate && $toDate) {
    $fileName .= "_" . str_replace("-", "", $fromDate) . "_to_" . str_replace("-", "", $toDate) . ".xlsx";
} else {
    $fileName .= "_all.xlsx";
}

// === SQL WHERE Filters ===
$whereOnOff   = "1=1";
$whereApp     = "1=1";
$whereDrive   = "1=1";
$wherePlaced  = "1=1";
$whereTracker = "1=1";
$whereRegistered = "1=1";

// Batch filter
if (!empty($batchFilter)) {
    $whereOnOff      .= " AND passing_year = '$batchFilter'";
    $whereApp        .= " AND placement_batch = '$batchFilter'";
    $whereDrive      .= " AND form_fields = '$batchFilter'";
    $wherePlaced     .= " AND placement_batch = '$batchFilter'";
    $whereRegistered .= " AND year_of_passing = '$batchFilter'";
    $whereTracker = "1=0"; // Always false, so no rows are selected

}

// Month-Year filter
if ($filterType === "month" && !empty($monthFilter)) {
    $monthNum = date('m', strtotime($monthFilter . "-01"));
    $yearNum  = date('Y', strtotime($monthFilter . "-01"));

    $whereOnOff      .= " AND MONTH(submitted_at) = '$monthNum' AND YEAR(submitted_at) = '$yearNum'";
    $whereApp        .= " AND MONTH(applied_at) = '$monthNum' AND YEAR(applied_at) = '$yearNum'";
    $whereDrive      .= " AND MONTH(d.created_at) = '$monthNum' AND YEAR(d.created_at) = '$yearNum'";
    $wherePlaced     .= " AND MONTH(placed_date) = '$monthNum' AND YEAR(placed_date) = '$yearNum'";
    $whereTracker    .= " AND MONTH(updated_at) = '$monthNum' AND YEAR(updated_at) = '$yearNum'";
    $whereRegistered .= " AND MONTH(created_at) = '$monthNum' AND YEAR(created_at) = '$yearNum'";
}

// Yearly filter
if ($filterType === "year" && !empty($yearFilter)) {
    $whereOnOff      .= " AND YEAR(submitted_at) = '$yearFilter'";
    $whereApp        .= " AND YEAR(applied_at) = '$yearFilter'";
    $whereDrive      .= " AND YEAR(d.created_at) = '$yearFilter'";
    $wherePlaced     .= " AND YEAR(placed_date) = '$yearFilter'";
    $whereTracker    .= " AND YEAR(updated_at) = '$yearFilter'";
    $whereRegistered .= " AND YEAR(created_at) = '$yearFilter'";
}

// Date Range filter
if ($filterType === "range" && !empty($fromDate) && !empty($toDate)) {
    $whereOnOff      .= " AND DATE(submitted_at) BETWEEN '$fromDate' AND '$toDate'";
    $whereApp        .= " AND DATE(applied_at) BETWEEN '$fromDate' AND '$toDate'";
    $whereDrive      .= " AND DATE(d.created_at) BETWEEN '$fromDate' AND '$toDate'";
    $wherePlaced     .= " AND DATE(placed_date) BETWEEN '$fromDate' AND '$toDate'";
    $whereTracker    .= " AND DATE(updated_at) BETWEEN '$fromDate' AND '$toDate'";
    $whereRegistered .= " AND DATE(created_at) BETWEEN '$fromDate' AND '$toDate'";
}

// === Helper Function ===
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

// === Create Spreadsheet ===
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


// === Output File ===
$spreadsheet->setActiveSheetIndex(0);
// Prevent corrupted Excel files due to unwanted output
if (ob_get_length()) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
