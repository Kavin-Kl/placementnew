<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}
include("config.php");
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $projectRoot = dirname($scriptName);
    define('BASE_URL', rtrim($protocol . $host . $projectRoot, '/') . '/');
}

use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Color;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_application'], $_POST['edit_application_id'])) {
    $appId = intval($_POST['edit_application_id']);
    $studentData = $_POST['student_data'] ?? [];

    // Fetch existing data from DB (resume_file removed)
    $stmt = $conn->prepare("SELECT student_data FROM applications WHERE application_id = ?");
    $stmt->bind_param("i", $appId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingData = $result->fetch_assoc();
    $stmt->close();

    $existingStudentData = [];
    if (!empty($existingData['student_data'])) {
        $existingStudentData = json_decode($existingData['student_data'], true);
    }

    // Handle deleted files (other than Resume)
    if (!empty($_POST['delete_files'])) {
        foreach ($_POST['delete_files'] as $fieldName => $filesToDelete) {
            if (!isset($existingStudentData[$fieldName])) continue;

            $existingField = $existingStudentData[$fieldName];

            if (is_array($existingField)) {
                foreach ($filesToDelete as $delFile) {
                    $key = array_search($delFile, $existingField);
                    if ($key !== false) {
                        if (file_exists($delFile)) unlink($delFile);
                        unset($existingField[$key]);
                    }
                }
                $existingStudentData[$fieldName] = array_values($existingField);
                if (empty($existingStudentData[$fieldName])) {
                    $existingStudentData[$fieldName] = [];
                }
            } else {
                if (in_array($existingField, $filesToDelete)) {
                    if (file_exists($existingField)) unlink($existingField);
                    $existingStudentData[$fieldName] = [];
                }
            }
        }
    }

    // Handle Resume deletion (now inside student_data)
    if (!empty($_POST['delete_files']['Resume']) && isset($existingStudentData['Resume'])) {
        foreach ($_POST['delete_files']['Resume'] as $fileToDelete) {
            if ($fileToDelete === $existingStudentData['Resume'] && file_exists($fileToDelete)) {
                unlink($fileToDelete);
                $existingStudentData['Resume'] = [];
            }
        }
    }

    // Handle file uploads and merge with existing files (except Resume)
    if (!empty($_FILES['student_data_files'])) {
        foreach ($_FILES['student_data_files']['name'] as $fieldName => $fileArray) {
            if ($fieldName === 'Resume') continue;
            $uploadedFiles = [];
            $filesCount = count($fileArray);
            for ($i = 0; $i < $filesCount; $i++) {
                if ($_FILES['student_data_files']['error'][$fieldName][$i] === 0) {
                    $tmpName = $_FILES['student_data_files']['tmp_name'][$fieldName][$i];
                    $origName = basename($fileArray[$i]);
                    $targetPath = 'uploads/' . uniqid() . '_' . $origName;
                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $uploadedFiles[] = $targetPath;
                    }
                }
            }
            if (count($uploadedFiles) > 0) {
                if (!empty($existingStudentData[$fieldName]) && is_array($existingStudentData[$fieldName])) {
                    $studentData[$fieldName] = array_merge($existingStudentData[$fieldName], $uploadedFiles);
                } else {
                    $studentData[$fieldName] = $uploadedFiles;
                }
            } else {
                if (!empty($existingStudentData[$fieldName])) {
                    $studentData[$fieldName] = $existingStudentData[$fieldName];
                }
            }
        }
    } else {
        foreach ($existingStudentData as $key => $val) {
            if (!isset($studentData[$key]) && $key !== 'Resume') {
                $studentData[$key] = $val;
            }
        }
    }

    // Handle Resume file upload (only 1 file expected)
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === 0) {
        $fileType = mime_content_type($_FILES['resume']['tmp_name']);
        $allowedTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];

        if (!in_array($fileType, $allowedTypes)) {
            $errorField = "❌ Only PDF, DOC, and DOCX files are allowed for Resume.";
        } else {
            $ext = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
            $resumeName = preg_replace("/[^a-zA-Z0-9_-]/", "_", $studentData['Name'] ?? 'student') . "_resume_" . uniqid() . "." . $ext;
            $targetPath = "uploads/" . $resumeName;

            if (move_uploaded_file($_FILES['resume']['tmp_name'], $targetPath)) {
                // Delete old resume if exists
                if (!empty($existingStudentData['Resume']) && $existingStudentData['Resume'] !== $targetPath && file_exists($existingStudentData['Resume'])) {
                    unlink($existingStudentData['Resume']);
                }
                $studentData['Resume'] = $targetPath;
            } else {
                error_log("Failed to move uploaded Resume file: " . $_FILES['resume']['name']);
                if (!empty($existingStudentData['Resume'])) {
                    $studentData['Resume'] = $existingStudentData['Resume'];
                }
            }
        }
    } else {
        if (!isset($studentData['Resume']) && !empty($existingStudentData['Resume'])) {
            $studentData['Resume'] = $existingStudentData['Resume'];
        }
    }

    // Copy other missing fields from existing data
    foreach ($existingStudentData as $key => $val) {
        if (!isset($studentData[$key])) {
            $studentData[$key] = $val;
        }
    }

    $studentDataJson = json_encode($studentData);

    // Update DB (resume_file column removed)
    $stmt = $conn->prepare("UPDATE applications SET student_data = ? WHERE application_id = ?");
    $stmt->bind_param("si", $studentDataJson, $appId);
    $stmt->execute();
    $stmt->close();

    // Also update all other applications of the same student in the same drive
    $regNo = $studentData['Register No'] ?? null;
    if ($regNo) {
        $stmtDrive = $conn->prepare("SELECT drive_id FROM applications WHERE application_id = ?");
        $stmtDrive->bind_param("i", $appId);
        $stmtDrive->execute();
        $result = $stmtDrive->get_result();
        $driveRow = $result->fetch_assoc();
        $stmtDrive->close();

        if ($driveRow) {
            $driveId = $driveRow['drive_id'];
            $stmtUpdateOthers = $conn->prepare("
                UPDATE applications
                SET student_data = ?
                WHERE reg_no = ? AND drive_id = ? AND application_id != ?
            ");
            $stmtUpdateOthers->bind_param("ssii", $studentDataJson, $regNo, $driveId, $appId);
            $stmtUpdateOthers->execute();
            $stmtUpdateOthers->close();
        }
    }

    $_SESSION['msg'] = "Application data updated successfully.";
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_application'], $_POST['delete_application_id'])) {
    $deleteId = intval($_POST['delete_application_id']);

    // Step 1: Get reg_no and drive_id from applications, then get company_name from drives
    $stmt = $conn->prepare("
        SELECT a.reg_no, a.drive_id, d.company_name
        FROM applications a
        JOIN drives d ON a.drive_id = d.drive_id
        WHERE a.application_id = ?
    ");
    if (!$stmt) {
        die("Prepare failed (SELECT): (" . $conn->errno . ") " . $conn->error);
    }

    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->bind_result($regNo, $driveId, $companyName);

    if ($stmt->fetch()) {
        $stmt->close();

        // Step 2: Delete all applications for this student in the same company and drive
        $delStmt = $conn->prepare("
            DELETE FROM applications 
            WHERE reg_no = ? AND drive_id = ?
        ");
        if (!$delStmt) {
            die("Prepare failed (DELETE): (" . $conn->errno . ") " . $conn->error);
        }

        $delStmt->bind_param("si", $regNo, $driveId);

        if ($delStmt->execute()) {
            $_SESSION['msg'] = " Deleted all applications of student <strong>$regNo</strong> for <strong>$companyName</strong>.";

        } else {
            $_SESSION['msg'] = " Failed to delete applications.";
        }

        $delStmt->close();
    } else {
        $_SESSION['msg'] = "Application not found.";
        $stmt->close();
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}


// ✅ Manual Role Completion Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_finished'], $_POST['role_id'], $_POST['close_date'])) {
    $roleId = $_POST['role_id'];
    $closeDate = $_POST['close_date'];

    $stmt = $conn->prepare("UPDATE drive_roles SET is_finished = 1, close_date = ? WHERE role_id = ?");
    $stmt->bind_param("si", $closeDate, $roleId);
    $stmt->execute();
}

// Handle reset action
if (isset($_GET['reset'])) {
    unset($_GET['company']);
    unset($_GET['from_dashboard']);
    header("Location: enrolled_students");
    exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require 'vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'], $_POST['status'], $_POST['update_status'])) {
    $applicationId = $_POST['application_id'];
    $status = $_POST['status'];
    $comment = $_POST['comment'];
    
    $statusToSet = trim(strtolower($status));
    if ($statusToSet === 'not placed') {
        $statusToSet = 'not_placed';
    }

    // Get allow_reapply for this student
    $getStudent = $conn->prepare("SELECT allow_reapply FROM students WHERE upid = (SELECT upid FROM applications WHERE application_id = ?)");
    $getStudent->bind_param("i", $applicationId);
    $getStudent->execute();
    $studentResult = $getStudent->get_result();
    $studentRow = $studentResult->fetch_assoc();
    $studentAllowReapply = $studentRow['allow_reapply'] ?? 'no';

    $batch = ($studentAllowReapply === 'yes') ? 'reapplied' : 'original';

    // ✅ Update with batch
    $update = $conn->prepare("UPDATE applications SET status = ?, comments = ?, placement_batch = ? WHERE application_id = ?");
    $update->bind_param("sssi", $statusToSet, $comment, $batch, $applicationId);
    $update->execute();

    // Step 2: Get the UPID of that application
    $getUpid = $conn->prepare("SELECT upid FROM applications WHERE application_id = ?");
    $getUpid->bind_param("i", $applicationId);
    $getUpid->execute();
    $result = $getUpid->get_result();

    if ($row = $result->fetch_assoc()) {
        $upid = $row['upid'];

        // Step 3: Recalculate final status from all applications of this student
        $checkFinalStatus = $conn->prepare("
            SELECT 
                SUM(CASE WHEN TRIM(LOWER(status)) = 'placed' THEN 1 ELSE 0 END) AS placed_count,
                SUM(CASE WHEN TRIM(LOWER(status)) = 'blocked' THEN 1 ELSE 0 END) AS blocked_count
            FROM applications 
            WHERE upid = ?
        ");
        $checkFinalStatus->bind_param("s", $upid);
        $checkFinalStatus->execute();
        $res = $checkFinalStatus->get_result();

        if ($statusRow = $res->fetch_assoc()) {
            if ($statusRow['placed_count'] > 0) {
                $finalStatus = 'placed';
            } elseif ($statusRow['blocked_count'] > 0) {
                $finalStatus = 'blocked';
            } else {
                $finalStatus = str_replace(' ', '_', strtolower($statusToSet));
            }

            // Update the student's placed_status
            // Get the latest company, role, ctc for this application
            $getDetails = $conn->prepare("
              SELECT d.company_name, r.designation_name, r.ctc
              FROM applications a
              JOIN drives d ON a.drive_id = d.drive_id
              JOIN drive_roles r ON a.role_id = r.role_id
              WHERE a.application_id = ?
            ");
            $getDetails->bind_param("i", $applicationId);
            $getDetails->execute();
            $details = $getDetails->get_result()->fetch_assoc();

            // Now update students table with placed_status + latest company details
            $updateStudent = $conn->prepare("
              UPDATE students 
              SET placed_status = ?, comment = ?, company_name = ?, role = ?, ctc = ? 
              WHERE upid = ?
            ");
            $updateStudent->bind_param(
              "ssssss",
              $finalStatus,
              $comment,
              $details['company_name'],
              $details['designation_name'],
              $details['ctc'],
              $upid
            );

            $updateStudent->execute();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update'], $_POST['role_id'], $_POST['bulk_status'])) {
    $roleId = $_POST['role_id'];
    $statusToSet = $_POST['bulk_status'];
    $bulkComment = $_POST['bulk_comment'] ?? null;
    $company = $_POST['company'];

    // ✅ Get all unique upids for this role
    $upidQuery = $conn->prepare("SELECT DISTINCT upid FROM applications WHERE role_id = ?");
    $upidQuery->bind_param("i", $roleId);
    $upidQuery->execute();
    $res = $upidQuery->get_result();

    while ($row = $res->fetch_assoc()) {
        $upid = $row['upid'];

        // ✅ Get student allow_reapply
        $studentQ = $conn->prepare("SELECT allow_reapply FROM students WHERE upid = ?");
        $studentQ->bind_param("s", $upid);
        $studentQ->execute();
        $studentRow = $studentQ->get_result()->fetch_assoc();
        $studentAllowReapply = $studentRow['allow_reapply'] ?? 'no';
        $batch = ($studentAllowReapply === 'yes') ? 'reapplied' : 'original';

        // ✅ Update all relevant applications for this student+role
        if ($bulkComment) {
            $bulkStmt = $conn->prepare("
                UPDATE applications 
                SET status = ?, comments = ?, placement_batch = ?
                WHERE role_id = ? AND upid = ? AND TRIM(LOWER(status)) NOT IN ('placed', 'blocked')
            ");
            $bulkStmt->bind_param("sssis", $statusToSet, $bulkComment, $batch, $roleId, $upid);
        } else {
            $bulkStmt = $conn->prepare("
                UPDATE applications 
                SET status = ?, placement_batch = ?
                WHERE role_id = ? AND upid = ? AND TRIM(LOWER(status)) NOT IN ('placed', 'blocked')
            ");
            $bulkStmt->bind_param("ssis", $statusToSet, $batch, $roleId, $upid);
        }
        $bulkStmt->execute();

        // ✅ Recheck student final status
        $checkStatus = $conn->prepare("
            SELECT 
                SUM(CASE WHEN TRIM(LOWER(status)) = 'placed' THEN 1 ELSE 0 END) AS placed_count,
                SUM(CASE WHEN TRIM(LOWER(status)) = 'blocked' THEN 1 ELSE 0 END) AS blocked_count
            FROM applications WHERE upid = ?
        ");
        $checkStatus->bind_param("s", $upid);
        $checkStatus->execute();
        $statusRes = $checkStatus->get_result()->fetch_assoc();

        if ($statusRes['placed_count'] > 0) {
            $finalStatus = 'placed';
        } elseif ($statusRes['blocked_count'] > 0) {
            $finalStatus = 'blocked';
        } else {
            $finalStatus = str_replace(' ', '_', strtolower($statusToSet));
        }

        // Get latest company, role, ctc for this UPID & Role
        $getDetails = $conn->prepare("
          SELECT d.company_name, r.designation_name, r.ctc
          FROM applications a
          JOIN drives d ON a.drive_id = d.drive_id
          JOIN drive_roles r ON a.role_id = r.role_id
          WHERE a.role_id = ? AND a.upid = ? AND a.status = 'placed'
          ORDER BY a.application_id DESC LIMIT 1
        ");
        $getDetails->bind_param("is", $roleId, $upid);
        $getDetails->execute();
        $details = $getDetails->get_result()->fetch_assoc();

        // Update students table
        $updateStudent = $conn->prepare("
          UPDATE students 
          SET placed_status = ?, comment = ?, company_name = ?, role = ?, ctc = ? 
          WHERE upid = ?
        ");
        $updateStudent->bind_param(
          "ssssss",
          $finalStatus,
          $comment,
          $details['company_name'],
          $details['designation_name'],
          $details['ctc'],
          $upid
        );

        $updateStudent->execute();
    }

    $_SESSION['bulk_success'] = "$statusToSet status updated for all students in $company - $roleId";
    header("Location: enrolled_students");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_selected_bulk'], $_POST['selected_ids'])) {
    $selectedIds = $_POST['selected_ids'];
    $statusToSet = $_POST['bulk_selected_status'];
    $statusToSet = trim(strtolower($statusToSet));
    if ($statusToSet === 'not placed') {
       $statusToSet = 'not_placed';
    }

    $comment = $_POST['bulk_selected_comment'] ?? '';

    foreach ($selectedIds as $applicationId) {
        // Update individual application
        // Get batch
        $upidQuery = $conn->prepare("SELECT upid FROM applications WHERE application_id = ?");
        $upidQuery->bind_param("i", $applicationId);
        $upidQuery->execute();
        $res = $upidQuery->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $upid = $row['upid'];

            $studentQ = $conn->prepare("SELECT allow_reapply FROM students WHERE upid = ?");
            $studentQ->bind_param("s", $upid);
            $studentQ->execute();
            $studentRow = $studentQ->get_result()->fetch_assoc();
            $studentAllowReapply = $studentRow['allow_reapply'] ?? 'no';
            $batch = ($studentAllowReapply === 'yes') ? 'reapplied' : 'original';

            // Prepare dynamic update query parts
$updateFields = [];
$params = [];
$paramTypes = '';

if (!empty($statusToSet)) {
    $updateFields[] = "status = ?";
    $params[] = $statusToSet;
    $paramTypes .= 's';
}

if ($comment !== '') { // allow empty string comment? If yes, keep it, else use !empty($comment)
    $updateFields[] = "comments = ?";
    $params[] = $comment;
    $paramTypes .= 's';
}

$updateFields[] = "placement_batch = ?";
$params[] = $batch;
$paramTypes .= 's';

$updateFieldsStr = implode(', ', $updateFields);

if ($updateFieldsStr) {
    $updateQuery = "UPDATE applications SET $updateFieldsStr WHERE application_id = ?";
    $params[] = $applicationId;
    $paramTypes .= 'i';

    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $stmt->close();
}
        }

        // Fetch upid
        $upidQuery = $conn->prepare("SELECT upid FROM applications WHERE application_id = ?");
        $upidQuery->bind_param("i", $applicationId);
        $upidQuery->execute();
        $res = $upidQuery->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $upid = $row['upid'];

            // Recalculate final status
            $statusCheck = $conn->prepare("
                SELECT 
                    SUM(CASE WHEN TRIM(LOWER(status)) = 'placed' THEN 1 ELSE 0 END) AS placed_count,
                    SUM(CASE WHEN TRIM(LOWER(status)) = 'blocked' THEN 1 ELSE 0 END) AS blocked_count
                FROM applications 
                WHERE upid = ?
            ");
            $statusCheck->bind_param("s", $upid);
            $statusCheck->execute();
            $counts = $statusCheck->get_result()->fetch_assoc();

            if ($counts['placed_count'] > 0) {
                $finalStatus = 'placed';
            } elseif ($counts['blocked_count'] > 0) {
                $finalStatus = 'blocked';
            } else {
              $finalStatus = str_replace(' ', '_', strtolower($statusToSet));
            }

            $getDetails = $conn->prepare("
              SELECT d.company_name, r.designation_name, r.ctc
              FROM applications a
              JOIN drives d ON a.drive_id = d.drive_id
              JOIN drive_roles r ON a.role_id = r.role_id
              WHERE a.application_id = ?
            ");
            $getDetails->bind_param("i", $applicationId);
            $getDetails->execute();
            $details = $getDetails->get_result()->fetch_assoc();

            $updateStudent = $conn->prepare("
              UPDATE students 
              SET placed_status = ?, comment = ?, company_name = ?, role = ?, ctc = ? 
              WHERE upid = ?
            ");
            $updateStudent->bind_param(
              "ssssss",
              $finalStatus,
              $comment,
              $details['company_name'],
              $details['designation_name'],
              $details['ctc'],
              $upid
            );

            $updateStudent->execute();
            $updateStudent->close();
        }
    }

$_SESSION['bulk_success'] = "Status '$statusToSet' updated for selected students.";

// Preserve filter parameters
$queryParams = http_build_query([
    'tab' => $_POST['tab'] ?? '',
    'upid' => $_POST['upid'] ?? '',
    'reg_no' => $_POST['reg_no'] ?? '',
    'placed_status' => $_POST['placed_status'] ?? '',
    'company' => $_POST['company'] ?? '',
    'role_name' => $_POST['role_name'] ?? '',
]);

header("Location: enrolled_students?$queryParams");
exit;

}




use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Handle export
// Handle exportuse PhpOffice\PhpSpreadsheet\Spreadsheet;


if (isset($_POST['export_all']) || isset($_POST['export_selected'])) {
    $company = $_POST['company'];
 $roleIds = json_decode($_POST['role_id'], true);
if (!is_array($roleIds)) {
    $roleIds = [$_POST['role_id']];
}

    $fields = $_POST['fields'] ?? [];
  // Add this block right here
    $roleNameQuery = $conn->prepare("SELECT designation_name FROM drive_roles WHERE role_id = ?");
    $roleNameQuery->bind_param("i", $roleId);
    $roleNameQuery->execute();
    $roleNameResult = $roleNameQuery->get_result();
    $roleName = $roleNameResult->fetch_assoc()['designation_name'] ?? 'Role_' . $roleId;


 $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
$types = str_repeat('i', count($roleIds)); // one 'i' for each role_id
$params = array_merge([$company], $roleIds);

$sql = "
    SELECT a.*, r.designation_name, r.ctc, r.stipend, s.student_name, s.program_type, s.program, s.course as student_course, d.drive_no
    FROM applications a
    LEFT JOIN drive_roles r ON a.role_id = r.role_id
    LEFT JOIN drives d ON a.drive_id = d.drive_id
    LEFT JOIN students s ON a.upid = s.upid
    WHERE d.company_name = ? AND a.role_id IN ($placeholders)
";

$exportQuery = $conn->prepare($sql);
$exportQuery->bind_param('s' . $types, ...$params);

    $exportQuery->execute();
    $result = $exportQuery->get_result();

    $fileLabelMap = [
    "Upload Photo" => "Photo",
    "Upload Portfolio" => "Portfolio",
    "Upload Cover Letter" => "Cover Letter",
    "Certifications Upload" => "Certificate",
    "Upload Academic Certificates" => "Certificate",
    "Upload ID Proof" => "ID Proof",
    "Upload Signature" => "Signature",
    "Additional Documents (You can upload multiple files Ex: Project, Portfolio)" => "Additional Docs",
    "Certificate of Internship" => "Internship Cert",
    "Resume" => "Resume",
];

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();


   $allStaticFields = [
    "Sl. No" => "Sl. No",
    "Placement ID" => "Placement ID",
    "Register No" => "Register No",
    "Student Name" => "Student Name",
    "Current Course" => "Current Course",
    "Percentage" => "Percentage",
    "Role and Priority" => "Role and Priority",
    "CTC" => "CTC",
    "Stipend" => "Stipend",
    "Placed status" => "Placed status",
    "Comment" => "Comment"
];

// ✅ Start header empty, and fill only what user selected
$header = [];

// ✅ Always include Sl. No as first column
$header = ["Sl. No"];

// Add remaining static fields based on user selection
if (!empty($fields)) {
    foreach ($fields as $f) {
        if ($f !== "Sl. No" && isset($allStaticFields[$f])) {
            $header[] = $allStaticFields[$f];
        }
    }
} else {
    // If nothing selected, include all static fields except Sl. No (already added)
    foreach ($allStaticFields as $key => $val) {
        if ($key !== "Sl. No") {
            $header[] = $val;
        }
    }
}


$dynamicFields = [];

// Collect all dynamic fields across ALL students
// Step 1: Gather ALL dynamic fields from all students
$allDynamicFields = [];

$result->data_seek(0);
while ($row = $result->fetch_assoc()) {
    $studentData = json_decode($row['student_data'], true) ?? [];
    $keys = array_diff(array_keys($studentData), ['Priority', "UPID", "Register No", "Student Name", "Course", "Percentage"]);
    $allDynamicFields = array_unique(array_merge($allDynamicFields, $keys));
}
$result->data_seek(0);

// Step 2: Add them to header
if (!empty($fields)) {
    $selectedFields = array_values(array_intersect($allDynamicFields, $fields));
    $header = array_merge($header, $selectedFields);
} else {
    $header = array_merge($header, $allDynamicFields);
}


    // Write header row
    $col = 1;
    foreach ($header as $heading) {
        $sheet->setCellValueByColumnAndRow($col++, 1, $heading);
    }

    // Write data rows
// Group data by student (avoid duplicates)
$studentsData = [];

while ($row = $result->fetch_assoc()) {
    $studentData = json_decode($row['student_data'], true) ?? [];
    $priority = $studentData['Priority'] ?? '';
    $upid = $row['upid'];

    // If not already created, initialize student record
    if (!isset($studentsData[$upid])) {
        $studentsData[$upid] = [
            'placement_id' => $row['upid'],
            'register_no' => $row['reg_no'],
            'student_name' => $row['student_name'] ?? '',
            'course' => $row['course'],
            'percentage' => $row['percentage'],
            'roles_priorities' => [],
            'ctc' => $row['ctc'],
            'stipend' => $row['stipend'],
            'status' => $row['status'],
            'comments' => $row['comments'],
            'extra_fields' => [],
            'drive_id' => $row['drive_id'],
        ];
    }

    // Combine roles + priority
    $studentsData[$upid]['roles_priorities'][] =
        trim($row['designation_name']) . " - priority " . trim($priority);

    // Capture extra fields (resume, photo, etc.)
$extraFields = !empty($fields)
    ? array_values(array_intersect($allDynamicFields, $fields))
    : $allDynamicFields;


    foreach ($extraFields as $f) {
        $studentsData[$upid]['extra_fields'][$f] = $studentData[$f] ?? '';
    }
}

// Now write data to Excel (1 row per student)
$rowIndex = 2;
$serialNo = 1;
foreach ($studentsData as $student) {
    $line = [];

    // ✅ First always add Sl. No
    $line[] = $serialNo++;

    // Prepare static field data (excluding Sl. No)
    $staticFieldMap = [
        "Placement ID" => $student['placement_id'] ?? '',
        "Register No" => $student['register_no'] ?? '',
        "Student Name" => $student['student_name'] ?? '',
        "Current Course" => $student['course'] ?? '',
        "Percentage" => $student['percentage'] ?? '',
       "Role and Priority" => implode(",\n", $student['roles_priorities']),


        "CTC" => $student['ctc'] ?? '',
        "Stipend" => $student['stipend'] ?? '',
        "Placed status" => $student['status'] ?? '',
        "Comment" => $student['comments'] ?? '',
    ];

    // ✅ Add only selected static fields (excluding Sl. No)
    if (!empty($fields)) {
        foreach ($fields as $f) {
            if ($f !== "Sl. No" && isset($staticFieldMap[$f])) {
                $line[] = $staticFieldMap[$f];
            }
        }
    } else {
        foreach ($staticFieldMap as $val) {
            $line[] = $val;
        }
    }


    // Add extra dynamic fields (files, etc.)
    foreach ($student['extra_fields'] as $f => $val) {
        $studentName = $student['student_name'] ?? 'Student';
        $label = $fileLabelMap[$f] ?? 'File';

        if (is_array($val)) {
            $isFileArray = !empty($val) && array_reduce($val, function ($carry, $item) {
                return $carry && is_string($item) && strpos($item, 'uploads/') === 0;
            }, true);

            if ($isFileArray) {
                $viewUrl = BASE_URL . "view_files.php?upid={$student['placement_id']}&field=" . urlencode($f) . "&drive_id={$student['drive_id']}";
                $displayName = $studentName . ' - ' . $label . " (View All)";
                $line[] = ['url' => $viewUrl, 'name' => $displayName];
            } else {
                $line[] = implode(", ", $val);
            }
        } elseif (is_string($val) && strpos($val, 'uploads/') === 0) {
            $url = BASE_URL . ltrim($val, '/');
            $displayName = $studentName . ' - ' . $label;
            $line[] = ['url' => $url, 'name' => $displayName];
        } else {
            $line[] = $val;
        }
    }

    // Write to Excel
    $col = 1;
    foreach ($line as $cell) {
        $colLetter = Coordinate::stringFromColumnIndex($col);
        $cellCoordinate = $colLetter . $rowIndex;

        if (is_array($cell) && isset($cell['url'])) {
            $sheet->setCellValue($cellCoordinate, $cell['name']);
            $sheet->getCell($cellCoordinate)->getHyperlink()->setUrl($cell['url']);
            $sheet->getStyle($cellCoordinate)->getFont()->getColor()->setARGB(Color::COLOR_BLUE);
            $sheet->getStyle($cellCoordinate)->getFont()->setUnderline(true);
        } else {
            $sheet->setCellValue($cellCoordinate, $cell);
        }

        $col++;
    }

    $rowIndex++;
}


    // Set column width (fixed size: 20) and wrap text
      // Auto-size
    $highestColumn = $sheet->getHighestColumn();
    $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
    for ($col = 1; $col <= $highestColumnIndex; $col++) {
        $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        $colLetter = Coordinate::stringFromColumnIndex($col);
        $sheet->getStyle($colLetter)->getAlignment()->setWrapText(true);
    }

    // Output Excel file


$sanitizedRoleName = preg_replace('/[^a-zA-Z0-9-_]/', '_', $roleName);
$filename = strtolower($company) . '_ApplicationList.xlsx';


    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}


include("header.php");

$tab = $_GET['tab'] ?? 'current';

// === Filters ===
$filterCompany = $_GET['company'] ?? '';
$filterDriveNo = $_GET['drive_no'] ?? '';
$fromDashboard = isset($_GET['from_dashboard']) ? $_GET['from_dashboard'] : 0;
$driveStatus = $_GET['status'] ?? '';

$filterUpid = $_GET['upid'] ?? '';
$filterRoleName = $_GET['role_name'] ?? '';
$filterRegNo = $_GET['reg_no'] ?? '';
$filterPlacedStatus = $_GET['placed_status'] ?? '';

// === Fetch Filter Options and Applications ===
$companyList = [];
$courseList = [];
$upidList = [];
$nameList = [];

// === Define base query ===
$query = "
   SELECT a.*, s.student_name, d.company_name, d.drive_no, 
          r.designation_name, r.role_id, r.ctc, r.stipend, 
          r.is_finished, r.close_date, d.created_at
   FROM applications a
   LEFT JOIN students s ON a.upid = s.upid
   LEFT JOIN drives d ON a.drive_id = d.drive_id
   LEFT JOIN drive_roles r ON a.role_id = r.role_id
";

// === FROM DASHBOARD ===
if ($fromDashboard) {
    $conditions = [];
    $params = [];
    $types = '';

    // Add academic year filter (from header.php year selector)
    if (isset($_SESSION['selected_academic_year'])) {
        $conditions[] = "d.academic_year = ?";
        $params[] = $_SESSION['selected_academic_year'];
        $types .= 's';
    }

    if (!empty($filterCompany)) {
        $conditions[] = "d.company_name = ?";
        $params[] = $filterCompany;
        $types .= 's';
    }

    if (!empty($filterDriveNo)) {
        $conditions[] = "d.drive_no = ?";
        $params[] = $filterDriveNo;
        $types .= 's';
    }

    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $allData = $stmt->get_result();
    
    // Don't force the tab based on drive status - let it show in both current/finished
    // $tab will be determined by the is_finished flag of the role
} 
// === ELSE: REGULAR FILTERS ===
else {
    $where = [];
    $params = [];
    $types = '';

    if (!empty($filterCompany)) {
        $where[] = "d.company_name LIKE ?";
        $params[] = "%$filterCompany%";
        $types .= 's';
    }
    
    if (!empty($filterRoleName)) {
        $where[] = "r.designation_name LIKE ?";
        $params[] = "%$filterRoleName%";
        $types .= 's';
    }
    
    if (!empty($filterUpid)) {
        $where[] = "a.upid LIKE ?";
        $params[] = "%$filterUpid%";
        $types .= 's';
    }

    if (!empty($filterRegNo)) {
        $where[] = "a.reg_no LIKE ?";
        $params[] = "%$filterRegNo%";
        $types .= 's';
    }

    if (!empty($filterPlacedStatus)) {
        $where[] = "a.status = ?";
        $params[] = $filterPlacedStatus;
        $types .= 's';
    }

    if (!empty($where)) {
        $query .= " WHERE " . implode(" AND ", $where);
    }

    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        die("❌ Prepare failed: " . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $allData = $stmt->get_result();
}

if (!$allData) {
    die("❌ SQL Error: " . $conn->error);
}

// === ROLE DATA FOR UI / PRE-FILLING ===
$roleData = $conn->query("
   SELECT d.company_name, d.drive_no, r.designation_name, r.role_id
   FROM drives d
   JOIN drive_roles r ON d.drive_id = r.drive_id
");

$rolesByCompany = [];
while ($r = $roleData->fetch_assoc()) {
    $company = $r['company_name'];
    $driveNo = $r['drive_no'];
    $role = $r['designation_name'];
    $roleId = $r['role_id'];

    $rolesByCompany[$company][$driveNo][$role] = $roleId;

    // If role is not in applications, initialize it
    if (!isset($applications[$company][$role])) {
        $applications[$company][$role] = [];  // Empty list
    }
}

$applications = ['current' => [], 'finished' => []]; // Split by status
$grouped = [];

while ($row = $allData->fetch_assoc()) {
    $companyList[$row['company_name']] = true;
    $courseList[$row['course']] = true;
    $upidList[$row['upid']] = true;

    $data = json_decode($row['student_data'], true);
    $fullName = $data['Full Name'] ?? '';
    $nameList[$fullName] = true;

    $company = $row['company_name'];
    $driveNo = $row['drive_no'];
    $role = $row['designation_name'];

    $grouped[$company][$driveNo][$role][] = $row;
}

// Now classify each role as finished or current based on is_finished flag
foreach ($grouped as $company => $drives) {
    foreach ($drives as $driveNo => $roles) {
        foreach ($roles as $roleName => $roleAppList) {
            // Use the first row's is_finished value (since same for all in a role)
            $isFinishedFlag = $roleAppList[0]['is_finished'] ?? 0;
            
            // If coming from dashboard, don't force the tab - respect the is_finished flag
            $key = ($isFinishedFlag == 1) ? 'finished' : 'current';
            
            $applications[$key][$company][$driveNo][$roleName] = $roleAppList;
        }
    }
}

?>

<?php if (isset($_SESSION['msg'])): ?>
    <div style="
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: #4CAF50;
        color: white;
        padding: 15px 25px;
        border-radius: 8px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        z-index: 99999;
        text-align: center;
        font-size: 16px;
        animation: fadeInOut 3s forwards;
    ">
        <?= $_SESSION['msg'] ?>
    </div>
    <script>
        setTimeout(() => {
            document.querySelectorAll('div[style*="position: fixed"]').forEach(el => el.remove());
        }, 3000);
    </script>
    <style>
        @keyframes fadeInOut {
            0% { opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { opacity: 0; }
        }
    </style>
    <?php unset($_SESSION['msg']); ?>
<?php endif; ?>





<h2 class="headings">Company-wise Enrolled Students List</h2>
<p>View and manage all student applications submitted for each company's placement drive.</p>

<div id="editModal" style="display:none; position:fixed; top:10%; left:50%; transform:translateX(-50%); background:#fff; padding:20px; border-radius:8px; box-shadow:0 0 10px rgba(0,0,0,0.25); z-index:9999; max-height: 80vh; overflow-y: auto;">
 

  <!-- Top right buttons -->
   
  <div class="edit-modal-buttons" style="position:absolute; top:10px; right:10px; display:flex; gap:10px;">

  <button type="submit" form="editForm" name="update_application" class="btn-save">Save</button>
  <button type="button" onclick="closeEditModal()" class="btn-cancel">Cancel</button>
</div>



<h5 style="font-size: 18px; font-weight: 500; margin-bottom: 12px;">Edit Student Data</h5>

 
  <form id="editForm" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="edit_application_id" id="edit_application_id" value="">
    
    <div id="studentDataFields">
      <!-- JS will generate inputs for each student_data key here -->
    </div>
<div class="form-group">
    <label>Upload New Resume (PDF):</label>
    <input type="file" name="resume" accept=".pdf, .doc, .docx">
</div>


  </form>
</div>





<?php if ($fromDashboard): ?>
    <div style="margin-bottom: 15px;">
        <a href="dashboard" class="back-btn">← Back to Dashboard</a>
         <a href="enrolled_students?reset=1" class="back-btn" style="margin-left: 10px; background-color: #eee1ca; color:black;border: 2px solid #650000; ">Show All Companies</a>
     </div>
<?php endif; ?>
<!-- Universal Search Input -->

<div class="filter-btn-container" style="display: flex; align-items: center; gap: 10px;">

 <!-- Search input aligned left -->
  <input 
    type="text" 
    id="globalSearchInput" 
     class="styled-search"
    placeholder="Search Company, Drive, and Role." 
    oninput="applyUniversalSearch()"
    />
<!--removed !dashboard -->
 
    <?php if (isset($_SESSION['msg'])): ?>
      <div class="alert alert-success"><?= $_SESSION['msg'] ?></div>
      <?php unset($_SESSION['msg']); ?>
    <?php endif; ?>
    
    <button type="button" id="filterBtn" class="filter-button">
      <i class="fas fa-filter"></i> Filters
    </button>
    <button type="button" id="resetBtn" class="reset-button" onclick="resetFilterToSameTab();" style="margin-left: 2px;">
      <i class="fas fa-undo"></i> Reset</span>
    </button>
    <!--removed !dashboard close tag-->
 
</div>
<!-- Universal Search Input -->



<div style="margin-bottom: 20px;">
  <a href="#"
     onclick="switchTab('current')"
     style="margin-right: 15px; padding: 6px 12px; border-radius: 6px;
     <?= $tab === 'current' 
         ? 'background: #650000; color: white; font-weight: bold; text-decoration: none;' 
         : 'color: #007BFF; text-decoration: none;' ?>">
     Current
  </a>

  <a href="#"
     onclick="switchTab('finished')"
     style="padding: 6px 12px; border-radius: 6px;
     <?= $tab === 'finished' 
         ? 'background: #650000; color: white; font-weight: bold; text-decoration: none;' 
         : 'color:  #198754; text-decoration: none;' ?>">
     Finished
  </a>
</div>
<?php
function generateDisplayFileName($studentName, $regNo, $companyName, $columnName, $originalPath) {
    $column = strtolower(trim($columnName));
    $column = preg_replace('/upload|document|file|additional|copy|photo| - /i', '', $column);
    $column = preg_replace('/[^a-z]/i', ' ', $column); // remove special chars
    $column = ucwords(trim($column));
    $column = str_replace(' ', '', $column);

    $ext = pathinfo($originalPath, PATHINFO_EXTENSION);
    $safeStudent = preg_replace('/[^a-zA-Z0-9]/', '', $studentName);
    $safeRegNo = preg_replace('/[^a-zA-Z0-9]/', '', $regNo);
    $safeCompany = preg_replace('/[^a-zA-Z0-9]/', '', $companyName);

    return "{$safeStudent}_{$safeRegNo}_{$safeCompany}_{$column}.{$ext}";
}
?>

<?php if (empty($applications[$tab])): ?>
  <div style="
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin: 40px auto;
    max-width: 600px;
    text-align: center;
  ">
    <i class="fas fa-inbox" style="font-size: 80px; color: #ccc; margin-bottom: 20px;"></i>
    <h3 style="color: #650000; margin-bottom: 10px;">No Applications Yet</h3>
    <p style="color: #666; font-size: 16px; margin-bottom: 20px;">
      There are no student applications for <?= $tab === 'current' ? 'current' : 'finished' ?> drives yet.
    </p>
    <p style="color: #999; font-size: 14px;">
      Students need to apply through the student portal for their applications to appear here.
    </p>
  </div>
<?php else: ?>

<?php foreach ($applications[$tab] as $company => $drives): ?>
  <?php 
    // ✅ Sort drives by number in DESC order
    uksort($drives, function($a, $b) {
      preg_match('/\d+/', $a, $aMatches);
      preg_match('/\d+/', $b, $bMatches);
      $aNum = isset($aMatches[0]) ? (int)$aMatches[0] : 0;
      $bNum = isset($bMatches[0]) ? (int)$bMatches[0] : 0;
      return $bNum - $aNum;
    });
  ?>


<div class="company-section">
    <div class="company-title">
      <strong>Company Name:</strong> <?= htmlspecialchars($company) ?>
    </div>

    <?php foreach ($drives as $driveNo => $roles): ?>
      <div class="drive-title" style=" font-size:15px; color:#650000">
       <strong> Drive Number: </strong><?= htmlspecialchars($driveNo) ?>
      </div>
   <!-- Full Excel Export -->
          
          <!-- Export Selected Fields -->
<?php
// ✅ Collect all file fields for this drive (across all roles)
$availableFileFields = [];
$roleIds = [];

foreach ($roles as $roleName => $appList) {
    if (isset($appList[0]['role_id'])) {
        $roleIds[] = $appList[0]['role_id'];
    }


    foreach ($appList as $row) {
        $data = json_decode($row['student_data'], true);
        if ($data) {
            foreach ($data as $key => $val) {
                if (
                    stripos($key, 'upload') !== false ||
                    stripos($key, 'documents') !== false ||
                    $key === 'Certificate of Internship'
                ) {
                    $availableFileFields[$key] = true;
                }
            }
        }
    }
}
$availableKeys = json_encode(array_keys($availableFileFields));
?>

<!-- ✅ Export buttons per drive -->
<div style="margin: 5px 0 5px 5px;">
  <button 
    type="button"
  onclick="showFieldPopup(
  '<?= md5($company.$driveNo) ?>',
  '<?= htmlspecialchars($company) ?>',
  '<?= htmlspecialchars(json_encode($roleIds)) ?>',
  '<?= htmlspecialchars($driveNo) ?>'
)"

    class="export-btn">
    <i class="fas fa-file-export" style="font-size:14px; margin-right:4px;"></i> Export Selected Fields
  </button>

 <button 
  type="button"
  class="export-btn"
  onclick="openExportFilesModalCompanyWise(
    '<?= htmlspecialchars($company) ?>',
    '<?= htmlspecialchars(json_encode($roleIds)) ?>',
    '<?= htmlspecialchars($availableKeys) ?>'
  )">
  <i class="fas fa-file-archive"></i> 
  <span style="margin-left: 5px;">Export Files</span>
</button>

</div>


      <?php 
        $roleCount = 1;
        foreach ($roles as $roleName => $appList):
      ?>
      <br>
        <h6 style="margin-left:10px;">
          <strong>Role <?= $roleCount++ ?>:</strong> <?= htmlspecialchars($roleName) ?>
        </h6>

        <?php if (empty($appList)): ?>
          <div style="color: red; margin-left:40px;">No applications for this role.</div>
          <br>
          <?php continue; ?>
        <?php endif; ?>
        
        <form id="exportForm_<?= $appList[0]['role_id'] ?>" method="POST" onsubmit="return handleFilteredExport(this)"  style="
          display: flex;
          flex-wrap: wrap;
          gap: 10px;
          margin: 10px 0;
          width: 100%;
          background: #fff;
          padding: 15px;
          border: 1px solid #ccc;
          box-sizing: border-box;
          border-radius: 12px;
        ">
          <input type="hidden" name="company" value="<?= htmlspecialchars($company) ?>">
          <input type="hidden" name="role_id" value="<?= htmlspecialchars($appList[0]['role_id']) ?>">
<input type="hidden" name="drive_id" value="<?= htmlspecialchars($appList[0]['drive_id']) ?>">

          <!-- Full Excel Export -->
          
         



          <?php
          // ✅ Find all actual uploaded file keys for THIS role's students
          $availableFileFields = [];
          foreach ($appList as $row) {
            $data = json_decode($row['student_data'], true);
            if ($data) {
              foreach ($data as $key => $val) {
                // Only keep likely file upload fields
               if (stripos($key, 'upload') !== false || stripos($key, 'documents') !== false || $key === 'Certificate of Internship') {
  $availableFileFields[$key] = true;
}

                }
              }
            }
          
          // ✅ Also check direct resume_file column
         foreach ($appList as $row) {
    $data = json_decode($row['student_data'], true);
    if (!empty($data['Resume'])) {
        $availableFileFields['Resume'] = true;
        break;
    }
}

         
          $availableKeys = json_encode(array_keys($availableFileFields));

          ?>

          <!-- Export Files ZIP -->
          
          
          <?php if (empty($appList[0]['is_finished'])): ?>
            <form method="POST" class="d-flex align-items-center gap-2 flex-wrap" style="margin: 10px 30px;">
              
                <input type="hidden" name="mark_finished" value="1">
                <input type="hidden" name="role_id" value="<?= $appList[0]['role_id'] ?>">
                <label for="close_date" style=" font-size: 14px; margin-top:5px;">Role Close Date:</label>
              <input type="date" id="close_date_<?= $appList[0]['role_id'] ?>" name="close_date" style="width: 140px; padding: 3px 6px; font-size: 13px;">
                <button 
                  type="submit" 
                  name="mark_finished_submit"
                  class="btn btn-success"
                  style="padding: 2px 8px; font-size: 13px; line-height: 1.2;"
                  onclick="return confirmMarkFinished(this);">
                   Mark as Finished
                </button>
            </form>
          <?php else: ?>
          <?php
  $rawDate = $appList[0]['close_date'];
  $formattedDate = date('d-m-Y', strtotime($rawDate));
?>
<p style=" color: green; margin-top:6px; font-size:14px;">
    ✔️ This role is marked as <strong>Finished</strong> on <?= htmlspecialchars($formattedDate) ?>
</p>
          <?php endif; ?>
        </form>

       <form method="POST" class="bulkUpdateForm" 
         style="
            margin-top: 20px;
            margin-bottom: 20px;
            display: flex; 
            flex-wrap: wrap; 
            align-items: center; 
            gap: 10px;
            background: #fff;
            padding: 15px;
            border: 1px solid #ccc;
            box-sizing: border-box;
            width: 100%;
            border-radius: 12px;
          ">
          <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
          <input type="hidden" name="upid" value="<?= htmlspecialchars($_GET['upid'] ?? '') ?>">
<input type="hidden" name="reg_no" value="<?= htmlspecialchars($_GET['reg_no'] ?? '') ?>">
<input type="hidden" name="placed_status" value="<?= htmlspecialchars($_GET['placed_status'] ?? '') ?>">
<input type="hidden" name="company" value="<?= htmlspecialchars($_GET['company'] ?? '') ?>">
<input type="hidden" name="role_name" value="<?= htmlspecialchars($_GET['role_name'] ?? '') ?>">

          <input 
            type="text" 
            class="role-search-input"
            placeholder="Search in this role..."
            style="height: 38px; padding: 0 8px; font-size: 12px; border: 1px solid #ccc; border-radius: 6px; margin: 10px 0; width: 200px;"
          >
          <select name="bulk_selected_status"  style="height: 38px; padding: 4px 8px; font-size:12px; min-width: 80px; width: 150px; margin-left:150px;">
            <option value="">-- Set Status --</option>
            
            <option value="placed">Placed</option>
            <option value="not_placed">Not Placed</option>
            <option value="rejected">Rejected</option>
            <option value="pending">Pending</option>
            <option value="blocked">Blocked</option>
          </select>

          <input type="text" name="bulk_selected_comment" placeholder="Optional comment" style="height: 36px;font-size:12px; width:200px; padding: 4px 8px;">

          <button type="submit" name="update_selected_bulk" class="update-selected-btn">Update Selected</button>

          <input type="hidden" name="company" value="<?= htmlspecialchars($company) ?>">
          <input type="hidden" name="role_id" value="<?= htmlspecialchars($appList[0]['role_id']) ?>">

          <div class="table-container" style="margin-top: 15px;">
              <table class="enrolled-table">
                  <?php
                  $showCTC = false;
                  $showStipend = false;
                  foreach ($appList as $row) {
                      if (!empty($row['ctc'])) $showCTC = true;
                      if (!empty($row['stipend'])) $showStipend = true;
                  }
                  ?>

                  <thead>
                      <tr>
                        <th>
  <input type="checkbox" onclick="toggleAll(this)" />
</th>
                          <th style="width: 30px; text-align: center;">Sl. No.</th>
                          
                          <th>Placement ID</th>
                          <th>Reg No</th>
                          <th>Student Name</th>
                          <th>Current Course</th>
                          <th>Role</th>
                          <th>Priority</th>
                            <th>Placed Status</th>
                          <th>Comment</th>
                          <?php if ($showCTC): ?>
                              <th>CTC</th>
                          <?php endif; ?>
                          <?php if ($showStipend): ?>
                              <th>Stipend</th>
                          <?php endif; ?>

                          <?php
                          $allFields = [];
                          $excludeFields = ['UPID', 'Reg No', 'Student Name', 'Percentage','Priority','Course'];

                          foreach ($appList as $row) {
                              $data = json_decode($row['student_data'], true);
                              if ($data) {
                                  foreach ($data as $key => $val) {
                                      if (!in_array($key, $excludeFields)) {
                                          $allFields[$key] = true;
                                      }
                                  }
                              }
                          }

                          foreach (array_keys($allFields) as $f) {
                              if (strtolower(trim($f)) === "register no") continue; 
                              echo "<th>" . htmlspecialchars($f) . "</th>";
                          }
                          ?>
                          
                     
                          <th></th>
                      </tr>

                      
                  </thead>
                  <tbody>
                      <?php 
                      $slno = 1;
                      foreach ($appList as $row):
                      
                      $data = json_decode($row['student_data'], true);
?>


                          <tr>
                            <td>
  <input type="checkbox" name="selected_ids[]" value="<?= $row['application_id'] ?>" data-role-id="<?= $appList[0]['role_id'] ?>">
</td>
                              <td style="text-align: center;"><?= $slno++ ?></td>
                              <td class="upid-cell"><?= htmlspecialchars($row['upid']) ?></td>
                              <td class="regno-cell"><?= htmlspecialchars($row['reg_no']) ?></td>
                              <td><?= htmlspecialchars($row['student_name'] ?? '') ?></td>

                              <td><?= htmlspecialchars($row['student_course'] ?? $row['course'] ?? '') ?></td>
                              <td class="role-cell"><?= htmlspecialchars($row['designation_name'] ?? '') ?></td>

<td><?= htmlspecialchars($data['Priority'] ?? '') ?></td>

<td class="status-cell"><?= htmlspecialchars($row['status']) ?></td>
<td><?= htmlspecialchars($row['comments'] ?? '') ?></td>

                              <?php if ($showCTC): ?>
                                  <td><?= htmlspecialchars($row['ctc']) ?></td>
                              <?php endif; ?>
                              <?php if ($showStipend): ?>
                                  <td><?= htmlspecialchars($row['stipend']) ?></td>
                              <?php endif; ?>

                              
                              <?php
                             
                              foreach (array_keys($allFields) as $f) {
                                  if (in_array($f, $excludeFields) || strtolower(trim($f)) === "register no") continue;
                                  $value = $data[$f] ?? '';
                                  $links = '';

                                    // ADD THIS BLOCK to handle "Languages Known (Read/Write/Speak)" specifically:
    if ($f === "Languages Known (Read/Write/Speak)") {
        if (is_array($value)) {
            $cellOutput = htmlspecialchars(implode(", ", $value));
        } else {
            $cellOutput = htmlspecialchars($value);
        }
        echo "<td>$cellOutput</td>";
        continue;  // Skip rest of this iteration, go to next field
    }
                                if ($f === 'Resume' && !empty($value)) {
    $formName = $row['student_name'] ?? 'Unknown';
    $safeName = preg_replace('/[^a-zA-Z0-9]/', '_', $formName);
    $resumeFileName = $safeName . "_MountCarmelCollege";
    $resumePath = is_array($value) ? ($value['path'] ?? '#') : $value;
    echo "<td><a href='" . htmlspecialchars($resumePath) . "' target='_blank' style='color:blue; text-decoration:underline;'>$resumeFileName</a></td>";
}

                               
                               
elseif (stripos($f, 'Additional Documents') !== false && !empty($value)) {
    $links = '';

    if (is_array($value)) {
        foreach ($value as $file) {
            if (is_array($file) && isset($file['path'])) {
                $displayName = generateDisplayFileName(
                    $row['student_name'],
                    $row['reg_no'],
                    $row['company_name'],
                    $f,       // field/column name
                    $file['path']
                );
                $links .= "<a href='" . htmlspecialchars($file['path']) . "' target='_blank' style='color:green; display:block; text-decoration:underline;'>" . htmlspecialchars($displayName) . "</a>";
            } elseif (is_string($file)) {
                $displayName = generateDisplayFileName(
                    $row['student_name'],
                    $row['reg_no'],
                    $row['company_name'],
                    $f,
                    $file
                );
                $links .= "<a href='" . htmlspecialchars($file) . "' target='_blank' style='color:green; display:block; text-decoration:underline;'>" . htmlspecialchars($displayName) . "</a>";
            }
        }
    } elseif (is_string($value)) {
        // Try to decode JSON string first
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            foreach ($decoded as $file) {
                if (is_array($file) && isset($file['path'])) {
                    $displayName = generateDisplayFileName(
                        $row['student_name'],
                        $row['reg_no'],
                        $row['company_name'],
                        $f,
                        $file['path']
                    );
                    $links .= "<a href='" . htmlspecialchars($file['path']) . "' target='_blank' style='color:green; display:block; text-decoration:underline;'>" . htmlspecialchars($displayName) . "</a>";
                } elseif (is_string($file)) {
                    $displayName = generateDisplayFileName(
                        $row['student_name'],
                        $row['reg_no'],
                        $row['company_name'],
                        $f,
                        $file
                    );
                    $links .= "<a href='" . htmlspecialchars($file) . "' target='_blank' style='color:green; display:block; text-decoration:underline;'>" . htmlspecialchars($displayName) . "</a>";
                }
            }
        } else {
            // Fallback: treat as CSV
            $docs = explode(',', $value);
            foreach ($docs as $docPath) {
                $docPath = trim($docPath);
                if ($docPath) {
                    $displayName = generateDisplayFileName(
                        $row['student_name'],
                        $row['reg_no'],
                        $row['company_name'],
                        $f,
                        $docPath
                    );
                    $links .= "<a href='" . htmlspecialchars($docPath) . "' target='_blank' style='color:green; display:block; text-decoration:underline;'>" . htmlspecialchars($displayName) . "</a>";
                }
            }
        }
    }

    echo "<td>$links</td>";
}


                            else {
    $cellOutput = '';

    if (is_array($value)) {
        foreach ($value as $file) {
            if (is_array($file)) {
                $filePath = $file['path'] ?? '';
                if ($filePath) {
                    $displayName = generateDisplayFileName(
                        $row['student_name'],
                        $row['reg_no'],
                        $row['company_name'],
                        $f,  // Column name
                        $filePath
                    );
                    $cellOutput .= "<a href='" . htmlspecialchars($filePath) . "' target='_blank' style='color:green; display:block; text-decoration:underline;'>"
                        . htmlspecialchars($displayName) . "</a>";
                }
            } elseif (is_string($file)) {
                $displayName = generateDisplayFileName(
                    $row['student_name'],
                    $row['reg_no'],
                    $row['company_name'],
                    $f,
                    $file
                );
                $cellOutput .= "<a href='" . htmlspecialchars($file) . "' target='_blank' style='color:green; display:block; text-decoration:underline;'>"
                    . htmlspecialchars($displayName) . "</a>";
            }
        }
    }
elseif (is_string($value) && preg_match('/\.(pdf|docx?|zip|png|jpg|jpeg)$/i', $value)) {

  
    $files = explode(',', $value);
    foreach ($files as $file) {
        $file = trim($file);
        if ($file) {
            // ✅ Handle both old and new style storage
            if (strpos($file, 'uploads/') === false) {
                $filePath = 'uploads/' . $file;
            } else {
                $filePath = $file;
            }

            // ✅ Clean name display
            $fileName = basename($filePath);

            $cellOutput .= "<a href='" . htmlspecialchars($filePath) . "' target='_blank' style='color:green; display:block; text-decoration:underline;'>"
                . htmlspecialchars($fileName) . "</a>";
        }
    }
}
else {
                                          $cellOutput = htmlspecialchars($value);
                                      }

                                      echo "<td>$cellOutput</td>";
                                  }
                              }
                              ?>
                              
                             
                        <td>
  <!-- Edit icon button -->
  <button 
    type="button" 
    class="edit-btn icon-btn" 
    title="Edit" 
    data-id="<?= $row['application_id'] ?>" 
    data-studentdata='<?= htmlspecialchars(json_encode(json_decode($row['student_data']), JSON_HEX_APOS|JSON_HEX_QUOT)) ?>'
    data-studentname="<?= htmlspecialchars($row['student_name']) ?>"
  data-regno="<?= htmlspecialchars($row['reg_no']) ?>"
  data-company="<?= htmlspecialchars($row['company_name']) ?>"
  >
    <i class="fas fa-edit"></i>
  </button>

  <!-- Delete icon button -->
<!-- Delete icon button -->
<button 
  type="button" 
  class="delete-btn icon-btn" 
  title="Delete"
  onclick="confirmAndDelete(<?= $row['application_id'] ?>)">
  <i class="fas fa-trash-alt"></i>
</button>

  
</td>



                          </tr>
                      <?php endforeach; ?>
                  </tbody>
              </table>
          </div>
        </form>
                            </form>
<form id="hiddenDeleteForm" method="POST" style="display: none;">
  <input type="hidden" name="delete_application_id" id="hiddenDeleteId">
  <input type="hidden" name="delete_application" value="1">
</form>

<!-- Export Field Popup -->
<?php
// ✅ Include all static fields you want to appear in popup
$staticFields = [
    'Placement ID',
    'Register No',
    'Student Name',
    'Current Course',
    'Percentage',
    'Role and Priority',
    'CTC',
    'Stipend',
    'Placed status',
    'Comment',
    'Priority',
    'Resume'
];

// ✅ Fetch all unique dynamic fields (from all students’ student_data)
$allFields = [];
$allFieldQuery = $conn->prepare("
    SELECT student_data 
    FROM applications a
    LEFT JOIN drives d ON a.drive_id = d.drive_id
    WHERE d.company_name = ?
");
$allFieldQuery->bind_param("s", $company);
$allFieldQuery->execute();
$fieldResult = $allFieldQuery->get_result();

while ($r = $fieldResult->fetch_assoc()) {
    $data = json_decode($r['student_data'], true) ?? [];
    $allFields = array_unique(array_merge($allFields, array_keys($data)));
}

// ✅ Prepare dynamic fields after collecting all student_data fields
$dynamicFields = [];
if (!empty($allFields)) {
    $dynamicFields = array_diff($allFields, $staticFields);
    sort($dynamicFields);
}

// ✅ Keep dynamic fields separate so static ones only appear once in popup
$exportableFields = $dynamicFields;
?>

<div id="fieldPopup_<?= md5($company.$driveNo) ?>" class="field-popup" style="display:none; width: 550px; max-height: 500px; overflow-y: auto; padding: 20px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 10px; background: #fff; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h4 style="margin: 0; font-size:17px;">Select Fields to Export for <?= htmlspecialchars($company) ?> - <?= htmlspecialchars($roleName) ?></h4>
      <button type="button" onclick="hideFieldPopup('<?= md5($company.$driveNo) ?>')" 
        style="margin-left:30px; color: black; border: none; padding: 2px 8px; cursor: pointer; font-size: 12px; border-radius: 4px;">✕</button>

    </div>

    <form method="POST" onsubmit="addFilteredIdsToForm(this)">
       <input type="hidden" name="company" value="">
<input type="hidden" name="role_id" value="">

        <!-- Select All Checkbox -->



        <div style="display: inline-flex; align-items: center; margin-bottom: 12px; white-space: nowrap;">
             <input type="checkbox" id="selectAllCheckbox_<?= md5($company.$roleName) ?>"  style="margin-right: 8px; margin-left: 12px; margin-top: 10px;">
            <label for="selectAllCheckbox_<?= md5($company.$roleName) ?>" style="margin: 0; margin-top: 10px; font-weight: bold; cursor: pointer;">Select All Fields</label>
        </div>

        <div id="fieldCheckboxes" style="display: flex; flex-direction: column; align-items: flex-start;">
          
  <?php
// Paste this just above the popup code or at the top of this PHP file
$fields = [
  'personal' => [
    "Full Name", "First Name", "Middle Name", "Last Name",
    "Phone No", "Alternate Phone No", 
    "Email ID (please recheck the email before submitting)", "Alternate Email ID",
    "Gender", "DOB",
    "Hometown", "State", "District",
    "City (Currently Residing In)", "Pincode",
    "Current Address", "Permanent Address",
    "PAN No", "Aadhar No", "Passport No",
    "Nationality", "Category (General/OBC/SC/ST)", "Religion", "Blood Group",
    "Marital Status", "Father’s Name", "Mother’s Name",
    "Emergency Contact Name", "Emergency Contact Number"
  ],
  'education' => [
    "--- 10th Details ---",
    "10th grade %", "10th Board Name", "10th Year Of Passing", "10th School Name", "10th School Location",

    "--- 12th Details ---",
    "12th grade/PUC %", "12th Board Name", "12th Year Of Passing", "12th/PUC School Name", "12th/PUC Location", "12th Stream",

    "--- Diploma Details (If any) ---",
    "Diploma %", "Diploma Year Of Passing", "Diploma Specialization", "Diploma College Name", "Diploma University Name",

    "--- UG Details ---",
    "UG Degree", "UG Course Name", "UG %/CGPA", "UG Year of Passing", 
    "UG Stream/Specialization", "UG College Name", "UG College Location", 
    "UG University Name", "Active Backlogs (UG)?", "No. of Backlogs (UG)",

    "--- PG Details (If any) ---",
    "PG Degree", "PG Course Name", "PG %/CGPA", "PG Year of Passing", 
    "PG Stream/Specialization", "PG College Name", "PG College Location", 
    "PG University Name", "Active Backlogs (PG)?", "No. of Backlogs (PG)"
  ],
  'work' => [
    "--- Internship Details ---",
    "Have you completed any internship?", "No. of Months of Internship", "Name of Organization", 
    "Internship Role", "Internship Project Details", "Internship Location",
    "Certificate of Internship",

    "--- Full-time Experience ---",
    "Have Prior Full-time Experience?", "Company Name", "Designation", "Duration (Months)",
    "Job Role Description", "Last Drawn Salary (If any)", "Reason for Leaving (If any)",

    "--- Projects ---",
    "Project Title", "Project Description", "Technologies Used", 
    "GitHub/Project URL", "Upload Portfolio", "Upload Cover Letter",
    "LinkedIn Profile", "Dribbble/Behance Link",
  
     "--- Preferences ---",
    "Are you available in person for interview?", "Are you ok with relocation?",
    "Are you ok with shifts?","Willing to join Immediately ?", "Preferred Work Locations?",
    "Preferred Industry", "Preferred Job Role"
  ],
  'others' => [
    "--- Skillset & Certifications ---",
    "Key Skills", "Name of Certifications Completed", "Certifications Upload",
    "Technical Skills", "Programming Languages Known",
    "Languages Known (Read/Write/Speak)",

    "--- Documents Uploads ---",
    "Upload Photo", "Upload Academic Certificates",
    "Upload ID Proof", "Upload Signature",  "Additional Documents (You can upload multiple files Ex: Project, Portfolio)",
 
    "--- Declarations ---",
    "I hereby undertake that I will appear for the complete recruitment process of the above mentioned organization. In case I fail to appear for the same, my candidature for next companies stands cancelled. I also confirm that I have not been placed so far.",
    "I hereby declare that the above information is correct.",
    "Declaration of Authenticity",
    "Agree to Terms and Conditions"
  ]
];

// Prepare all available fields for this company/role
// ✅ Remove static fields so they don’t appear again below
$availableFields = array_diff($exportableFields, $staticFields);
 // Your existing array of all fields

// Remove these from popup (same as before)
// Remove only unnecessary fields (keep Placement ID, Register No, Comment visible)
$removeFromPopup = ['Role', 'Priority'];

$availableFields = array_diff($availableFields, $removeFromPopup);

// ✅ Static Fields section on top
// ✅ Static Fields section on top
echo "<div style='font-weight:bold; margin: 10px 0 5px;'>Static Fields</div>";

$staticTopFields = [
    'Placement ID',
    'Register No',
    'Student Name',
    'Current Course',
    'Percentage',
    'Role and Priority',
    'CTC',
    'Stipend',
    'Placed status',
    'Comment',
    'Resume'
];



// Directly show all static fields — no need for if condition
foreach ($staticTopFields as $f) {
    ?>
    <div style="display: inline-flex; align-items: center; margin-bottom: 8px; white-space: nowrap;">
        <input type="checkbox" name="fields[]" value="<?= htmlspecialchars($f) ?>" class="fieldCheckbox" style="margin-right: 8px; margin-left: 12px; margin-top: 10px;">
        <label style="margin: 0; margin-top: 10px;"><?= htmlspecialchars($f) ?></label>
    </div>
    <?php
}


// Now output fields in the order of $fields groups (only if they exist in $availableFields)
foreach ($fields as $group => $fieldList) {
    echo "<div style='font-weight:bold; margin: 10px 0 5px;'>".ucfirst($group)." Fields</div>";
    foreach ($fieldList as $f) {
        if (in_array($f, $availableFields)) {
            ?>
            <div style="display: inline-flex; align-items: center; margin-bottom: 8px; white-space: nowrap;">
                <input type="checkbox" name="fields[]" value="<?= htmlspecialchars($f) ?>" class="fieldCheckbox" style="margin-right: 8px; margin-left: 12px; margin-top: 10px;">
                <label style="margin: 0; margin-top: 10px;"><?= htmlspecialchars($f) ?></label>
            </div>
            <?php
        }
    }
}

// Then output any remaining fields that were not in your $fields array (custom fields), in alphabetical order:
$remainingFields = array_diff($availableFields, array_merge(...array_values($fields)));
sort($remainingFields);
foreach ($remainingFields as $f) {
    ?>
    <div style="display: inline-flex; align-items: center; margin-bottom: 8px; white-space: nowrap;">
        <input type="checkbox" name="fields[]" value="<?= htmlspecialchars($f) ?>" class="fieldCheckbox" style="margin-right: 8px; margin-left: 12px; margin-top: 10px;">
        <label style="margin: 0; margin-top: 10px;"><?= htmlspecialchars($f) ?></label>
    </div>
    <?php
}
?>





        </div>

        <br>
        <button type="submit" name="export_selected" class="export-files-btn" style="width:300px; margin-left:100px;">
            <i class="fas fa-file-archive" style="font-size:14px; margin-right:4px;"></i> Export Selected Fields
        </button>
    </form>
</div>




      <?php endforeach; // roles ?>
    <?php endforeach; // drives ?>
</div>
<?php endforeach; // companies ?>
<?php endif; // empty applications check ?>

<!-- 🌐 Filter Modal -->
<div id="filterModal" class="filter-modal">
  <div class="modal-content">
    <span class="close" onclick="closeFilterModal()">&times;</span>
    <h3 style="font-size: 16px; font-weight: bold; color:#0F172A;">Filter Enrolled Students</h3>
    <form method="GET" id="filterForm">
      <input type="hidden" name="tab" id="filter_tab" value="<?= htmlspecialchars($tab) ?>">

      <div class="filter-grid">
        <label>Company Name:
          <input type="text" name="company" placeholder="Enter company name" value="<?= htmlspecialchars($filterCompany) ?>">
        </label>

        <label>Role Name:
          <input type="text" name="role_name" placeholder="Enter role name" value="<?= htmlspecialchars($_GET['role_name'] ?? '') ?>">
        </label>

        <label>Placement ID:
          <input type="text" name="upid" placeholder="Enter UPID" value="<?= htmlspecialchars($filterUpid) ?>">
        </label>

        <label>Register No:
          <input type="text" name="reg_no" placeholder="Enter Reg No" value="<?= htmlspecialchars($filterRegNo) ?>">
        </label>

        <label>Placed Status:
          <select name="placed_status">
            <option value="">-- All --</option>
            <option value="applied" <?= ($filterPlacedStatus == 'applied') ? 'selected' : '' ?>>Applied</option>
            <option value="placed" <?= ($filterPlacedStatus == 'placed') ? 'selected' : '' ?>>Placed</option>
            <option value="not_placed" <?= ($filterPlacedStatus == 'not_placed') ? 'selected' : '' ?>>Not Placed</option>
            <option value="rejected" <?= ($filterPlacedStatus == 'rejected') ? 'selected' : '' ?>>Rejected</option>
            <option value="pending" <?= ($filterPlacedStatus == 'pending') ? 'selected' : '' ?>>Pending</option>
            <option value="blocked" <?= ($filterPlacedStatus == 'blocked') ? 'selected' : '' ?>>Blocked</option>
          </select>
        </label>
      </div>

      <div class="filter-actions">
        <button type="submit">Apply Filter</button>
        <button type="button" class="clear-button" onclick="resetFilterForm()">Clear Filters</button>
      </div>
    </form>
  </div>
</div>

<!-- ✅ Export Files Modal -->
<div id="exportFilesModal" class="export-files-modal" style="display:none;">
  <div class="export-files-modal-content">
    <span class="export-files-close" onclick="closeExportFilesModal()">&times;</span>
    <h3>Select which files to export:</h3>
    <div id="exportFilesButtonsContainer"></div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
  const filterBtn = document.getElementById("filterBtn");
  if (filterBtn) {
    filterBtn.addEventListener("click", function() {
      document.getElementById('filterModal').style.display = 'flex';
    });
  }
});

function closeFilterModal() {
  document.getElementById('filterModal').style.display = 'none';
}

function closeExportFilesModal() {
  document.getElementById('exportFilesModal').style.display = 'none';
}

function toggleAll(masterCheckbox) {
    const table = masterCheckbox.closest('table');
    const checkboxes = table.querySelectorAll('input[name="selected_ids[]"]');
    checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
}

document.addEventListener("DOMContentLoaded", function() {
  const roleSearchInputs = document.querySelectorAll(".role-search-input");

  roleSearchInputs.forEach(input => {
    input.addEventListener("input", function() {
      const term = this.value.toLowerCase();
      const parentForm = this.closest('form');
      const table = parentForm ? parentForm.querySelector("table") : null;

      if (!table) return;

      const rows = table.querySelectorAll("tbody tr");
      rows.forEach(row => {
        const rowText = row.textContent.toLowerCase();
        row.style.display = rowText.includes(term) ? "" : "none";
      });
    });
  });
});

window.openExportFilesModal = function(company, roleId, availableKeysCSV) {
  const container = document.getElementById('exportFilesButtonsContainer');
  container.innerHTML = '';

const availableKeys = JSON.parse(availableKeysCSV);


 const fileFields = [
  { key: 'Certificate of Internship', label: 'Download Certificate of Internship' },
  { key: 'Upload Photo', label: 'Download Photo' },
  { key: 'Resume', label: 'Download Resume' },
  { key: 'Upload Cover Letter', label: 'Download Cover Letter' },
  { key: 'Certifications Upload', label: 'Download Certifications' },
  { key: 'Upload Academic Certificates', label: 'Download Academic Certificates' },
  { key: 'Upload ID Proof', label: 'Download ID Proof' },
  { key: 'Upload Signature', label: 'Download Signature' },
  { key: 'Upload Portfolio', label: 'Download Portfolio' },
  { key: 'Additional Documents (You can upload multiple files Ex: Project, Portfolio)', label: 'Download Additional Documents' }
];

fileFields.forEach(field => {
  if (
    field.key === 'Resume' || 
    availableKeys.some(k => k.toLowerCase().includes(field.key.toLowerCase()))
  ) {


      const btn = document.createElement('a');
      btn.href = `export_enrolled_students_file?company=${encodeURIComponent(company)}&role_id=${encodeURIComponent(roleId)}&field=${field.key}`;
      btn.className = 'export-files-btn'; 
      btn.style.display = 'inline-flex';
      btn.style.alignItems = 'center';
      btn.style.gap = '8px';
      btn.style.margin = '5px 0';

      const icon = document.createElement('i');
      let iconClass = 'fas fa-file';

      if (field.key.includes('Photo')) {
        iconClass = 'fas fa-image';
      } else if (field.key.includes('resume') || field.key.includes('Resume')) {
        iconClass = 'fas fa-file-alt';
      } else if (field.key.includes('Cover Letter')) {
        iconClass = 'fas fa-envelope-open-text';
      } else if (field.key.includes('Certifications')) {
        iconClass = 'fas fa-certificate';
      } else if (field.key.includes('Academic')) {
        iconClass = 'fas fa-graduation-cap';
      } else if (field.key.includes('ID Proof')) {
        iconClass = 'fas fa-id-card';
      } else if (field.key.includes('Signature')) {
        iconClass = 'fas fa-pen-fancy';
      } else if (field.key.includes('Portfolio')) {
        iconClass = 'fas fa-briefcase';
      } else if (field.key.includes('Additional')) {
        iconClass = 'fas fa-folder-plus';
      }

      icon.className = iconClass;
      btn.appendChild(icon);

      const label = document.createElement('span');
      label.textContent = field.label;
      btn.appendChild(label);

      container.appendChild(btn);
    }
  });

  document.getElementById('exportFilesModal').style.display = 'flex';
};

window.openExportFilesModalCompanyWise = function(company, roleIdsJSON, availableKeysCSV) {
  const container = document.getElementById('exportFilesButtonsContainer');
  container.innerHTML = '';

  const availableKeys = JSON.parse(availableKeysCSV);
  const roleIds = JSON.parse(roleIdsJSON);

  const fileFields = [
    { key: 'Certificate of Internship', label: 'Download Certificate of Internship' },
    { key: 'Upload Photo', label: 'Download Photo' },
    { key: 'Resume', label: 'Download Resume' },
    { key: 'Upload Cover Letter', label: 'Download Cover Letter' },
    { key: 'Certifications Upload', label: 'Download Certifications' },
    { key: 'Upload Academic Certificates', label: 'Download Academic Certificates' },
    { key: 'Upload ID Proof', label: 'Download ID Proof' },
    { key: 'Upload Signature', label: 'Download Signature' },
    { key: 'Upload Portfolio', label: 'Download Portfolio' },
    { key: 'Additional Documents (You can upload multiple files Ex: Project, Portfolio)', label: 'Download Additional Documents' }
  ];

fileFields.forEach(field => {
  if (
    field.key === 'Resume' || 
    availableKeys.some(k => k.toLowerCase().includes(field.key.toLowerCase()))
  ) {

      const btn = document.createElement('a');
      btn.href = `export_enrolled_students_file.php?company=${encodeURIComponent(company)}&role_ids=${encodeURIComponent(JSON.stringify(roleIds))}&field=${encodeURIComponent(field.key)}`;
      btn.className = 'export-files-btn'; 
      btn.style.display = 'inline-flex';
      btn.style.alignItems = 'center';
      btn.style.gap = '8px';
      btn.style.margin = '5px 0';

      const icon = document.createElement('i');
      let iconClass = 'fas fa-file';
      if (field.key.includes('Photo')) iconClass = 'fas fa-image';
      else if (field.key.includes('Resume')) iconClass = 'fas fa-file-alt';
      else if (field.key.includes('Cover Letter')) iconClass = 'fas fa-envelope-open-text';
      else if (field.key.includes('Certifications')) iconClass = 'fas fa-certificate';
      else if (field.key.includes('Academic')) iconClass = 'fas fa-graduation-cap';
      else if (field.key.includes('ID Proof')) iconClass = 'fas fa-id-card';
      else if (field.key.includes('Signature')) iconClass = 'fas fa-pen-fancy';
      else if (field.key.includes('Portfolio')) iconClass = 'fas fa-briefcase';
      else if (field.key.includes('Additional')) iconClass = 'fas fa-folder-plus';
      icon.className = iconClass;

      btn.appendChild(icon);
      const label = document.createElement('span');
      label.textContent = field.label;
      btn.appendChild(label);

      container.appendChild(btn);
    }
  });

  document.getElementById('exportFilesModal').style.display = 'flex';
};


function confirmMarkFinished(button) {
  const form = button.closest("form");
  if (!form) {
    alert("Form not found!");
    return false;
  }

  const dateInput = form.querySelector("input[name='close_date']");
  if (!dateInput || !dateInput.value || new Date(dateInput.value).toString() === 'Invalid Date') {
    alert("Please select a valid close date before marking as finished.");
    return false;
  }

  return confirm("Are you sure you want to mark this role as finished?");
}


function resetFilterForm() {
  const form = document.getElementById("filterForm");

  if (form) {
    form.querySelectorAll("input, select").forEach(el => {
      if (el.name !== "tab") {  // Keep tab input intact
        if (el.tagName === "SELECT") {
          el.selectedIndex = 0;
        } else {
          el.value = "";
        }
      }
    });

    // ✅ Remove empty values from URL to prevent PHP error
    const url = new URL(window.location.href);
    url.search = ""; // Clear all GET params
    history.replaceState({}, '', url);
  }

  // Optionally reset UI — this part is safe to leave as-is
  document.querySelectorAll('.enrolled-role-card').forEach(card => {
    card.style.display = '';
    card.querySelectorAll('tbody tr').forEach(row => row.style.display = '');
  });
}


function switchTab(targetTab) {
  const params = new URLSearchParams(window.location.search);
  params.set('tab', targetTab);

  ['filter_company', 'filter_role_name', 'filter_upid', 'filter_reg_no', 'filter_placed_status'].forEach(key => {
    const el = document.getElementById(key);
    if (el && el.value) {
      params.set(key, el.value);
    }
  });

  window.location.href = 'enrolled_students?' + params.toString();
}

function resetFilterToSameTab() {
  // Clear universal search stored in localStorage
  localStorage.removeItem("universalSearch");

  // Clear global search bar
  const globalInput = document.getElementById("globalSearchInput");
  if (globalInput) globalInput.value = "";

  // Clear all per-role search inputs
  document.querySelectorAll(".role-search-input").forEach(input => {
    input.value = "";
  });

  // Clear filter form values
  const filterIds = [
    'filter_company', 
    'filter_role_name', 
    'filter_upid', 
    'filter_reg_no', 
    'filter_placed_status'
  ];
  filterIds.forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = "";
  });

  // Get current tab and reload page without filters
  const url = new URL(window.location.href);
  const tab = url.searchParams.get("tab") || "current";

  // Reload page with only tab param (remove all filters/search)
  window.location.href = window.location.pathname + "?tab=" + tab;
}


function addFilteredIdsToForm(form) {
  // Remove existing hidden inputs
  form.querySelectorAll('input[name="filtered_ids[]"]').forEach(el => el.remove());

  // Find the related enrolled table
  const section = form.closest('.company-section');
  if (!section) return;

  const table = section.querySelector("table.enrolled-table");
  if (!table) return;

  const visibleRows = table.querySelectorAll("tbody tr:not([style*='display: none'])");

  visibleRows.forEach(row => {
    const checkbox = row.querySelector('input[name="selected_ids[]"]');
    if (checkbox) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'filtered_ids[]';
      input.value = checkbox.value;
      form.appendChild(input);
    }
  });
}




document.addEventListener('DOMContentLoaded', function () {
  const urlParams = new URLSearchParams(window.location.search);

  const filterMap = {
    'filter_company': 'filter_company',
    'filter_role_name': 'filter_role_name',
    'filter_upid': 'filter_upid',
    'filter_reg_no': 'filter_reg_no',
    'filter_placed_status': 'filter_placed_status'
  };

  for (const param in filterMap) {
    const value = urlParams.get(param);
    if (value !== null) {
      const el = document.getElementById(filterMap[param]);
      if (el) el.value = value;
    }
  }

  const anyFilter = ['filter_company', 'filter_role_name', 'filter_upid', 'filter_reg_no', 'filter_placed_status']
    .some(key => urlParams.get(key));
  if (anyFilter) {
    applyEnrolledFilter();
  }
});
</script>

<?php if (!empty($_SESSION['bulk_success'])): ?>
  <div id="toast-message" class="toast-success"><?= htmlspecialchars($_SESSION['bulk_success']) ?></div>
  <?php unset($_SESSION['bulk_success']); ?>
<?php endif; ?>

<script>
  document.addEventListener("DOMContentLoaded", function() {
    const toast = document.getElementById("toast-message");
    if (toast) {
      toast.classList.add("show");
      setTimeout(() => {
        toast.classList.remove("show");
      }, 3000);
    }
  });









document.querySelectorAll('.edit-btn').forEach(button => {
  button.addEventListener('click', () => {
    const appId = button.getAttribute('data-id');
    const studentDataJson = button.getAttribute('data-studentdata');

    // Grab the student info from data attributes instead of studentData JSON
    const studentName = button.getAttribute('data-studentname') || 'Student';
    const regNo = button.getAttribute('data-regno') || 'NA';
    const company = button.getAttribute('data-company') || '';

    openEditModal(appId, studentDataJson, { studentName, regNo, company });
  });
});

function openEditModal(applicationId, studentDataJson, extraData) {
const fileNameFieldMap = {
  "Upload Photo": "Photo",
  "Upload Portfolio": "Portfolio",
  "Upload Cover Letter": "Cover Letter",
  "Certificate of Internship": "Internship Certificate",
  "Certifications Upload": "Certifications",
  "Upload Academic Certificates": "Academic Certificate",
  "Upload ID Proof": "ID Proof",
  "Upload Signature": "Signature",
  "Additional Documents (You can upload multiple files Ex: Project, Portfolio)": "Additional Documents"
  // Add more as needed
};



  const modal = document.getElementById('editModal');
  const form = document.getElementById('editForm');
  const container = document.getElementById('studentDataFields');

  container.innerHTML = ''; // clear previous fields

  const studentData = JSON.parse(studentDataJson);

  const fieldTypePresets = {
    "DOB": { type: "date" },
    "Religion": { type: "dropdown", options: ["Hindu", "Christian", "Muslim", "Sikh", "Jain", "Other"] },
    "Blood Group": { type: "dropdown", options: ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"] },
    "Preferred Industry": { type: "dropdown", options: [
        "Information Technology",
        "Artificial Intelligence & Machine Learning",
        "Software Development",
        "Cybersecurity",
        "Healthcare & Life Sciences",
        "Pharmaceuticals & Biotechnology",
        "Finance & Banking",
        "Investment Banking",
        "Fintech",
        "Insurance",
        "Accounting & Auditing",
        "Education & EdTech",
        "Research & Development",
        "Manufacturing",
        "Automotive",
        "Aerospace & Defense",
        "Energy & Power",
        "Renewable Energy & Sustainability",
        "Oil & Gas",
        "Construction & Infrastructure",
        "Real Estate",
        "Retail & E-commerce",
        "Consumer Goods",
        "FMCG",
        "Logistics & Supply Chain",
        "Transport & Shipping",
        "Travel & Hospitality",
        "Tourism",
        "Telecommunications",
        "Media & Entertainment",
        "Advertising & Branding",
        "Public Relations & Communications",
        "Market Research",
        "Legal Services",
        "Government & Public Sector",
        "NGO & Social Work",
        "Agriculture & AgroTech",
        "Food & Beverage",
        "Sports & Fitness",
        "Event Management",
        "Fashion & Apparel",
        "Interior Design",
        "Architecture & Urban Planning",
        "Consulting",
        "Human Resources & Recruitment",
        "Business Analytics",
        "Data Science & Big Data",
        "Blockchain & Web3",
        "Cloud Computing",
        "Gaming & Animation",
        "Space Technology",
        "Robotics",
        "Environmental Services",
        "Waste Management",
        "Marine & Oceanography",
        "Defense & Homeland Security",
        "Printing & Publishing"] },
    "Languages Known (Read/Write/Speak)": {
      type: "checkbox",
      options: ["English", "Hindi", "Kannada", "Tamil", "Telugu", "Malayalam", "Others"]
    },
    "Category (General/OBC/SC/ST)": { type: "dropdown", options: ["General", "OBC", "SC", "ST"] },
    
    "Marital Status": { type: "dropdown", options: ["Single", "Married", "Divorced", "Widowed"] },
    "Gender": { type: "radio", options: ["Male", "Female", "Other"] },
    "Active Backlogs (UG)?": { type: "radio", options: ["Yes", "No"] },
    "Active Backlogs (PG)?": { type: "radio", options: ["Yes", "No"] },
    "Have you completed any internship?": { type: "radio", options: ["Yes", "No"] },
    "Have Prior Full-time Experience?": { type: "radio", options: ["Yes", "No"] },
    "Are you available in person for interview?": { type: "radio", options: ["Yes", "No"] },
    "Are you ok with relocation?": { type: "radio", options: ["Yes", "No"] },
    "Are you ok with shifts?": { type: "radio", options: ["Yes", "No"] },
    "Willing to join Immediately ?": { type: "radio", options: ["Yes", "No"] },
    "I hereby undertake that I will appear for the complete recruitment process of the above mentioned organization. In case I fail to appear for the same, my candidature for next companies stands cancelled. I also confirm that I have not been placed so far.": { type: "checkbox" },
    "Declaration of Authenticity": { type: "checkbox" },
     "I hereby declare that the above information is correct.": { type: "checkbox" },
    "Agree to Terms and Conditions": { type: "checkbox" },

    "Certificate of Internship": { type: "file", multiple: true },
    "Upload Portfolio": { type: "file", multiple: true },
    "Upload Cover Letter": { type: "file", multiple: true },
    "Certifications Upload": { type: "file", multiple: true },
    "Upload Photo": { type: "file", multiple: true },
    "Upload Academic Certificates": { type: "file", multiple: true },
    "Upload ID Proof": { type: "file", multiple: true },
    "Upload Signature": { type: "file", multiple: true },
   
 "Additional Documents (You can upload multiple files Ex: Project, Portfolio)": { type: "file", multiple: true }

  };




  

  for (const [fieldName, fieldValue] of Object.entries(studentData)) {
     // 🔽 Skip certain fields from rendering in the popup
  if (["UPID", "Register No", "Course", "Percentage","Resume"].includes(fieldName)) {
    continue;
  }
    const preset = fieldTypePresets[fieldName] || { type: "text" }; // default text if no preset
    const fieldDiv = document.createElement('div');
    fieldDiv.classList.add('field-group');
    fieldDiv.style.marginBottom = "15px";

    // For single checkbox fields, label after input; else label above input
if (preset.type === 'checkbox' && !preset.options) {
    // Single checkbox with text directly after it
    const wrapper = document.createElement('label');
    wrapper.style.display = 'flex';
 wrapper.style.alignItems = 'center';
    wrapper.style.gap = '6px';
    wrapper.style.cursor = 'pointer';
     wrapper.style.justifyContent = 'flex-start'; // ensure left alignment
wrapper.style.width = "100%";
    const checkbox = document.createElement('input');
    checkbox.style.marginTop = '2px'; // adjust spacing so box lines up with first line
checkbox.style.verticalAlign = 'top';
    checkbox.type = 'checkbox';
    checkbox.name = `student_data[${fieldName}]`;
    checkbox.id = `field_${fieldName}`;
    checkbox.checked = fieldValue === true || fieldValue === "true" || fieldValue === 1 || fieldValue === "on";
   checkbox.style.marginTop = '0';

    const labelText = document.createElement('span');
    labelText.textContent = fieldName;
    labelText.style.lineHeight = '1.4';
 

    wrapper.appendChild(labelText);
   wrapper.appendChild(checkbox);
   
    fieldDiv.appendChild(wrapper);
    container.appendChild(fieldDiv);
    continue;
}
// skip rest of loop for this case
else {
      // Label above inputs
      const label = document.createElement('label');
      label.textContent = fieldName;
      label.htmlFor = `field_${fieldName}`;
      label.style.display = "block";
      label.style.fontWeight = "bold";
      label.style.marginBottom = "5px";
      fieldDiv.appendChild(label);
    }

    // Text, email, tel, date inputs
    if (["text", "email", "tel", "date"].includes(preset.type)) {
      const input = document.createElement('input');
      input.type = preset.type;
      input.name = `student_data[${fieldName}]`;
      input.id = `field_${fieldName}`;
      input.value = fieldValue || '';
      if (preset.pattern) input.pattern = preset.pattern;
      if (preset.placeholder) input.placeholder = preset.placeholder;
      input.style.width = "100%";
      input.style.padding = "6px";
      fieldDiv.appendChild(input);
    }
    // Dropdown select
    else if (preset.type === 'dropdown') {
      const select = document.createElement('select');
      select.name = `student_data[${fieldName}]`;
      select.id = `field_${fieldName}`;
      select.style.width = "100%";
      select.style.padding = "6px";

      for (const option of preset.options) {
        const opt = document.createElement('option');
        opt.value = option;
        opt.textContent = option;
        if (option === fieldValue) opt.selected = true;
        select.appendChild(opt);
      }
      fieldDiv.appendChild(select);
    }
    // Radio buttons
    else if (preset.type === 'radio') {
      for (const option of preset.options) {
        const wrapper = document.createElement('div');
        wrapper.style.display = 'inline-flex';
        wrapper.style.alignItems = 'center';
        wrapper.style.marginRight = '15px';

        const radioId = `field_${fieldName}_${option}`;
        const radio = document.createElement('input');
        radio.type = 'radio';
        radio.name = `student_data[${fieldName}]`;
        radio.id = radioId;
        radio.value = option;
        if (option === fieldValue) radio.checked = true;

        const radioLabel = document.createElement('label');
        radioLabel.htmlFor = radioId;
        radioLabel.textContent = option;
        radioLabel.style.marginLeft = '4px';

        wrapper.appendChild(radio);
        wrapper.appendChild(radioLabel);

        fieldDiv.appendChild(wrapper);
      }
    }
    // Multiple checkboxes
    else if (preset.type === 'checkbox') {
      if (preset.options && Array.isArray(preset.options)) {
        let selectedValues = [];
        if (typeof fieldValue === 'string') selectedValues = fieldValue.split(',').map(v => v.trim());
        else if (Array.isArray(fieldValue)) selectedValues = fieldValue;

        for (const option of preset.options) {
          const wrapper = document.createElement('div');
          wrapper.style.display = 'inline-flex';
          wrapper.style.alignItems = 'center';
          wrapper.style.marginRight = '15px';

          const cbId = `field_${fieldName}_${option}`;
          const checkbox = document.createElement('input');
          checkbox.type = 'checkbox';
          checkbox.name = `student_data[${fieldName}][]`;
          checkbox.id = cbId;
          checkbox.value = option;
          if (selectedValues.includes(option)) checkbox.checked = true;

          const cbLabel = document.createElement('label');
          cbLabel.htmlFor = cbId;
          cbLabel.textContent = option;
          cbLabel.style.marginLeft = '4px';

          wrapper.appendChild(checkbox);
          wrapper.appendChild(cbLabel);

          fieldDiv.appendChild(wrapper);
        }
      }
    }
    // File uploads
    else if (preset.type === 'file') {
      if (fieldValue) {
        const files = Array.isArray(fieldValue) ? fieldValue : [fieldValue];
        const fileList = document.createElement('div');
        fileList.textContent = 'Existing files: ';
        fileList.style.marginBottom = '8px';

        files.forEach((fileItem) => {
          let fileName = '';
          let fileUrl = '';

          if (typeof fileItem === 'string') {
            fileUrl = fileItem;
            // Extract file extension from original URL
const fileExt = fileUrl.split('.').pop();

// Clean name parts
const cleanStudentName = (extraData.studentName || 'Student').replace(/[^a-zA-Z0-9]/g, '');
const cleanRegNo = (extraData.regNo || 'NA').replace(/[^a-zA-Z0-9]/g, '');
const cleanCompany = (extraData.company || '').replace(/[^a-zA-Z0-9]/g, '');
const mappedName = fileNameFieldMap[fieldName] || fieldName;
const cleanField = mappedName.replace(/[^a-zA-Z0-9]/g, '');


fileName = `${cleanStudentName}_${cleanRegNo}_${cleanCompany}_${cleanField}.${fileExt}`;


          } else if (typeof fileItem === 'object' && fileItem !== null) {
            fileUrl = fileItem.path || '';
            fileName = fileItem.name || fileUrl.split('/').pop() || 'file';
          }

          if (!fileUrl) return; // skip if no URL

          const fileWrapper = document.createElement('div');
          fileWrapper.style.marginBottom = '5px';
          fileWrapper.style.display = 'flex';
          fileWrapper.style.alignItems = 'center';
          fileWrapper.style.gap = '6px';

          const a = document.createElement('a');
          a.href = fileUrl;
          a.target = '_blank';
          a.textContent = fileName;
          a.style.flexGrow = '1';
          fileWrapper.appendChild(a);

          // Red X icon for delete
          const delIcon = document.createElement('span');
          delIcon.textContent = '✖'; // Unicode cross symbol (X)
          delIcon.style.color = 'red';
          delIcon.style.cursor = 'pointer';
          delIcon.style.fontWeight = 'bold';
          delIcon.style.fontSize = '16px';
          delIcon.title = 'Click to mark for deletion';

          // Hidden checkbox to track deletion
          const delCheckbox = document.createElement('input');
          delCheckbox.type = 'checkbox';
          delCheckbox.name = `delete_files[${fieldName}][]`;
          delCheckbox.value = fileUrl;
          delCheckbox.style.display = 'none'; // hide checkbox

          delIcon.addEventListener('click', () => {
            delCheckbox.checked = !delCheckbox.checked;
            if (delCheckbox.checked) {
              delIcon.style.textDecoration = 'line-through';
              delIcon.style.opacity = '0.5';
            } else {
              delIcon.style.textDecoration = 'none';
              delIcon.style.opacity = '1';
            }
          });

          fileWrapper.appendChild(delCheckbox);
          fileWrapper.appendChild(delIcon);

          fileList.appendChild(fileWrapper);
        });

        fieldDiv.appendChild(fileList);
      }

      // New file input
      const fileInput = document.createElement('input');
      fileInput.type = 'file';
      fileInput.name = `student_data_files[${fieldName}][]`;
      fileInput.multiple = !!preset.multiple;
      fileInput.style.display = 'block';
      fieldDiv.appendChild(fileInput);
    }
    // Default fallback input (text)
    else {
      const input = document.createElement('input');
      input.type = 'text';
      input.name = `student_data[${fieldName}]`;
      input.id = `field_${fieldName}`;
      input.value = fieldValue || '';
      input.style.width = "100%";
      input.style.padding = "6px";
      fieldDiv.appendChild(input);
    }

    container.appendChild(fieldDiv);
  }

  document.getElementById('edit_application_id').value = applicationId;
  modal.style.display = 'block';
}

function closeEditModal() {
  document.getElementById('editModal').style.display = 'none';
}


// Store scroll position before submit
document.querySelector('#editForm').addEventListener('submit', function() {
    localStorage.setItem('scrollPosition', window.scrollY);
});

// Restore scroll position after reload
window.addEventListener('load', function() {
    if (localStorage.getItem('scrollPosition') !== null) {
        window.scrollTo(0, localStorage.getItem('scrollPosition'));
        localStorage.removeItem('scrollPosition');
    }
});
// For delete buttons or links
document.querySelectorAll('.delete-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        localStorage.setItem('scrollPosition', window.scrollY);
    });
});

</script>



<script>
function confirmAndDelete(applicationId) {
  if (confirm("This will delete this student's application for all roles under the same company. Do you want to continue?")) {
    document.getElementById("hiddenDeleteId").value = applicationId;
    document.getElementById("hiddenDeleteForm").submit();
  }
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const forms = document.querySelectorAll('.bulkUpdateForm');

  forms.forEach(form => {
    form.addEventListener('submit', function(e) {
      const checkboxes = form.querySelectorAll('input[name="selected_ids[]"]');
      const anyChecked = Array.from(checkboxes).some(cb => cb.checked);

      if (!anyChecked) {
        alert("Please select at least one student to update.");
        e.preventDefault();
        return;
      }

      const status = form.querySelector('select[name="bulk_selected_status"]')?.value.trim();
      const comment = form.querySelector('input[name="bulk_selected_comment"]')?.value.trim();

      if (!status && !comment) {
        alert("Please select a status or enter a comment to update.");
        e.preventDefault();
        return;
      }

      const confirmed = confirm("Are you sure you want to update selected students?");
      if (!confirmed) {
        e.preventDefault();
      }
    });
  });
});
</script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const fieldCheckboxes = document.querySelectorAll('#fieldCheckboxes .fieldCheckbox');

        selectAllCheckbox.addEventListener('change', () => {
            fieldCheckboxes.forEach(cb => {
                cb.checked = selectAllCheckbox.checked;
            });
        });

        // Optional: If all checkboxes manually checked/unchecked, update Select All checkbox state
        fieldCheckboxes.forEach(cb => {
            cb.addEventListener('change', () => {
                const allChecked = [...fieldCheckboxes].every(cb => cb.checked);
                selectAllCheckbox.checked = allChecked;
            });
        });
    });



    document.querySelectorAll('input[id^="selectAllCheckbox_"]').forEach(selectAll => {
  selectAll.addEventListener('change', function() {
    const form = this.closest('form');
    const checkboxes = form.querySelectorAll('input.fieldCheckbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
  });
});

function validateFieldSelection(form) {
  const checkboxes = form.querySelectorAll('input[type="checkbox"][name="fields[]"]:checked');
  if (checkboxes.length === 0) {
    alert("Please select at least one field to export.");
    return false;
  }
  return true;
}

function toggleAll(masterCheckbox) {
  const roleId = masterCheckbox.closest('form').querySelector('input[name="role_id"]').value;
  const checkboxes = document.querySelectorAll(`input[name="selected_ids[]"][data-role-id="${roleId}"]`);
  checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
}





</script>
</script><script>
// Universal search logic for filtering all text inside .company-section
function applyUniversalSearch() {
  const input = document.getElementById("globalSearchInput");
const searchValue = input.value.trim().toLowerCase();

  localStorage.setItem("universalSearch", searchValue);

  const sections = document.querySelectorAll(".company-section");

  sections.forEach(section => {
    // Get *all* text content inside this section
    const sectionText = section.textContent || "";

    // Check if the section contains the search term
    const sectionMatches = sectionText.toLowerCase().includes(searchValue);


    // Show or hide the section
    section.style.display = sectionMatches ? "" : "none";
  });
}

// Load from storage if available
function loadSearchFromStorage() {
  const input = document.getElementById("globalSearchInput");
  const stored = localStorage.getItem("universalSearch");
  if (stored !== null) {
    input.value = stored;
    applyUniversalSearch();
  }
}

// Run on page load
window.addEventListener("DOMContentLoaded", loadSearchFromStorage);

// Patch switchTab to restore search after switching
const originalSwitchTab = window.switchTab;
window.switchTab = function(tab) {
  const searchValue = localStorage.getItem("universalSearch") || "";

  // Immediately hide all sections before switching tab
  const allSections = document.querySelectorAll(".company-section");
  allSections.forEach(section => section.style.display = "none");

  originalSwitchTab(tab);

  // Wait just long enough for new content to render, then apply search
  requestAnimationFrame(() => {
    setTimeout(() => {
      const input = document.getElementById("globalSearchInput");
      if (input) input.value = searchValue;
      applyUniversalSearch();
    }, 0);
  });
};
</script>
<script>
// Save scroll position before any action that causes a reload
window.addEventListener("beforeunload", function () {
  localStorage.setItem("scrollY", window.scrollY);
localStorage.removeItem("universalSearch"); // Clear search too

});

// Restore scroll position after reload
window.addEventListener("load", function () {
  const scrollY = localStorage.getItem("scrollY");
  if (scrollY !== null) {
    window.scrollTo(0, parseInt(scrollY));
    localStorage.removeItem("scrollY"); // Optional: clear it so it's not reused
  }
});
</script>













<script>
function showFieldPopup(uniqueId, company, roleIdsJSON, driveNo) {
  // Hide any other open popups first
  document.querySelectorAll('.field-popup').forEach(p => p.style.display = 'none');

  const popup = document.getElementById('fieldPopup_' + uniqueId);
  if (!popup) {
    alert("Popup not found for this company/drive.");
    return;
  }

  const companyInput = popup.querySelector('input[name="company"]');
  const roleInput = popup.querySelector('input[name="role_id"]');
  if (companyInput) companyInput.value = company;

  // Store all role IDs as JSON string
  if (roleInput) roleInput.value = roleIdsJSON;

  popup.style.position = "fixed";
  popup.style.top = "50%";
  popup.style.left = "50%";
  popup.style.transform = "translate(-50%, -50%)";
  popup.style.zIndex = "9999";
  popup.style.display = "block";

  // Auto-check all field checkboxes by default
  setTimeout(() => {
    const allCheckboxes = popup.querySelectorAll('input[type="checkbox"]');
    allCheckboxes.forEach(cb => {
      if (cb.id !== 'selectAllFields') { // Don't auto-check the "Select All" checkbox
        cb.checked = true;
      }
    });
    // Update "Select All" checkbox state
    const selectAllCheckbox = popup.querySelector('#selectAllFields');
    if (selectAllCheckbox) {
      selectAllCheckbox.checked = true;
    }
  }, 50);

  let overlay = document.createElement('div');
  overlay.id = "popupOverlay";
  overlay.style.position = "fixed";
  overlay.style.top = "0";
  overlay.style.left = "0";
  overlay.style.width = "100%";
  overlay.style.height = "100%";
  overlay.style.background = "rgba(0,0,0,0.4)";
  overlay.style.zIndex = "9998";
  overlay.onclick = function() {
    hideFieldPopup(uniqueId);
  };
  document.body.appendChild(overlay);
}

</script>
<script>
function hideFieldPopup(uniqueId) {
  const popup = document.getElementById('fieldPopup_' + uniqueId);
  if (popup) popup.style.display = 'none';

  const overlay = document.getElementById('popupOverlay');
  if (overlay) overlay.remove();
}
</script>
<script>
function toggleSection(sectionClass, sourceCheckbox) {
  const checkboxes = document.querySelectorAll('.' + sectionClass + ' input[type="checkbox"]');
  checkboxes.forEach(cb => cb.checked = sourceCheckbox.checked);
}
</script>


<?php include("footer.php"); ?>
