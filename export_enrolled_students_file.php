<?php
session_start();
ob_start();
include("config.php");

// ✅ 1) Get parameters
$company = '';
$field = '';
$roleIds = [];

if (isset($_GET['company'], $_GET['role_ids'], $_GET['field'])) {
    $company = preg_replace('/[^A-Za-z0-9_\-]/', '_', $_GET['company']);
    $field = trim($_GET['field']);
    $roleIds = json_decode($_GET['role_ids'], true);
    if (!is_array($roleIds) || empty($roleIds)) {
        exit("Invalid role IDs.");
    }
} else {
    exit("Missing parameters.");
}

// ✅ 2) Folders
$projectRoot = __DIR__;
$tempDir = $projectRoot . '/exports';
if (!file_exists($tempDir)) {
    mkdir($tempDir, 0777, true);
}

// ✅ 3) Get role
$roleStmt = $conn->prepare("SELECT designation_name FROM drive_roles WHERE role_id = ?");
$roleStmt->bind_param("i", $roleId);
$roleStmt->execute();
$roleRow = $roleStmt->get_result()->fetch_assoc();
$roleStmt->close();
$roleName = $roleRow ? preg_replace('/[^A-Za-z0-9_\-]/', '_', $roleRow['designation_name']) : 'UnknownRole';

// Clean company, roleName, and field for safe filename
$fieldLabels = [
    'Certificate of Internship' => 'Certificate_of_Internship',
    'Upload Photo' => 'Photo',
    'Resume' => 'Resume',
    'Upload Cover Letter' => 'Cover_Letter',
    'Certifications Upload' => 'Certifications',
    'Upload Academic Certificates' => 'Academic_Certificates',
    'Upload ID Proof' => 'ID_Proof',
    'Upload Signature' => 'Signature',
    'Upload Portfolio' => 'Portfolio',
    'Additional Documents (You can upload multiple files Ex: Project, Portfolio)' => 'Additional_Documents'
];

$companyClean = preg_replace('/[^A-Za-z0-9_\-]/', '_', $company);
$roleNameClean = preg_replace('/[^A-Za-z0-9_\-]/', '_', $roleName);
$fieldLabel = isset($fieldLabels[$field]) ? $fieldLabels[$field] : preg_replace('/[^A-Za-z0-9_\-]/', '_', $field);
$zipFilename = $tempDir . '/' . $companyClean . '_' . $fieldLabel . '.zip';


// ✅ 4) Applications
$placeholders = implode(',', array_fill(0, count($roleIds), '?'));
$types = str_repeat('i', count($roleIds));

$sql = "
    SELECT a.*, s.student_name, s.reg_no 
    FROM applications a 
    LEFT JOIN students s ON a.upid = s.upid 
    WHERE a.role_id IN ($placeholders)
";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$roleIds);
$stmt->execute();
$result = $stmt->get_result();


$zip = new ZipArchive();
if ($zip->open($zipFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    exit("Cannot create ZIP file.");
}

function getCustomFileName($studentName, $company, $fieldLabel, $ext) {
    if ($fieldLabel === 'Resume') {
        $collegeName = 'MountCarmentcollege';
        $safeStudentName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $studentName);
        return $safeStudentName . '_' . $collegeName . '.' . $ext;
    }
    return false;
}

$rootFolder = $company . '/';
$addedFiles = 0;

// ✅ 5) Add helper with fix for double uploads
function addToZipSafe($zip, $projectRoot, $filePathRaw, $studentName = '', $regNo = '', $company = '', $fieldLabel = '', $fileIndex = 0)
{
    $filePathRaw = trim($filePathRaw);
    if ($filePathRaw === '') return 0;

    if (strpos($filePathRaw, 'uploads/') === 0) {
        $filePath = $projectRoot . '/' . str_replace('\\', '/', $filePathRaw);
    } else {
        $filePath = $projectRoot . '/uploads/' . ltrim(str_replace('\\', '/', $filePathRaw), '/');
    }

    if (!file_exists($filePath)) return 0;
    if (is_dir($filePath) || !pathinfo($filePath, PATHINFO_EXTENSION)) return 0;

    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
    $baseFilename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $studentName) . '_' .
                    preg_replace('/[^A-Za-z0-9_\-]/', '_', $regNo) . '_' .
                    preg_replace('/[^A-Za-z0-9_\-]/', '_', $company) . '_' .
                    preg_replace('/[^A-Za-z0-9_\-]/', '_', $fieldLabel);

    if ($fileIndex > 1) {
        $baseFilename .= '_(' . $fileIndex . ')';
    }

    $customName = getCustomFileName($studentName, $company, $fieldLabel, $ext);
    $uniqueName = $customName !== false ? $customName : $baseFilename . '.' . $ext;

    $zip->addFile($filePath, $uniqueName);
    return 1;
}

// ✅ 6) Loop students
while ($row = $result->fetch_assoc()) {
    $upid = $row['upid'] ?? 'unknown_upid';
    $studentName = $row['student_name'] ?? 'UnknownStudent';
    $regNo = $row['reg_no'] ?? $upid;
    $studentData = json_decode($row['student_data'], true);

    $studentNameRaw = $studentData['Full Name'] ?? $studentData['Name'] ?? 'Student';
    $studentFolderName = substr(preg_replace('/[^A-Za-z0-9_\-]/', '_', trim($studentNameRaw) . '_' . $upid), 0, 50) . '/';
    $folderInZip = $rootFolder . $studentFolderName;

    if (!$studentData) continue;

    $fieldsToExport = [$field];

    // ✅ Special case: Resume file is stored in a separate column
if ($field === 'Resume' && !empty($row['resume_file'])) {
    $addedFiles += addToZipSafe(
        $zip,
        $projectRoot,
        $row['resume_file'],
        $studentName,
        $regNo,
        $company,
        $fieldLabel
    );
    continue; // Skip normal student_data processing
}

    foreach ($fieldsToExport as $exportKey) {
        if (!isset($studentData[$exportKey])) continue;

        $value = $studentData[$exportKey];

        if (is_array($value) && isset($value['path'])) {
            $studentName = $studentData['Full Name'] ?? $studentData['Name'] ?? 'Student';
            $regNo = $studentData['Registration Number'] ?? $upid;
            $addedFiles += addToZipSafe($zip, $projectRoot, $value['path'], $studentName, $regNo, $company, $fieldLabel);

        } elseif (is_array($value)) {
            $fileCount = 0;
            foreach ($value as $entry) {
                $fileCount++;
                if (is_array($entry) && isset($entry['path'])) {
                    $addedFiles += addToZipSafe($zip, $projectRoot, $entry['path'], $studentName, $regNo, $company, $fieldLabel, $fileCount);
                } elseif (is_string($entry)) {
                    $addedFiles += addToZipSafe($zip, $projectRoot, $entry, $studentName, $regNo, $company, $fieldLabel, $fileCount);
                }
            }

        } elseif (is_string($value)) {
            $fileCount = 0;
            foreach (explode(',', $value) as $filePathRaw) {
                $fileCount++;
                $addedFiles += addToZipSafe($zip, $projectRoot, trim($filePathRaw), $studentName, $regNo, $company, $fieldLabel, $fileCount);
            }
        }
    }

}

$zip->close();

if ($addedFiles === 0) {
    if (file_exists($zipFilename)) {
        unlink($zipFilename);
    }

    $uploadsPath = realpath($projectRoot . '/uploads');
    header('Content-Type: text/plain');
    http_response_code(404);

    echo "Files not found in uploads folder.\n";
    echo "Path: $uploadsPath";
    exit;
}

if (file_exists($zipFilename)) {
    ob_end_clean();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($zipFilename) . '"');
    header('Content-Length: ' . filesize($zipFilename));
    readfile($zipFilename);
    unlink($zipFilename);
    exit;
} else {
    exit("Error creating ZIP file.");
}
?>
