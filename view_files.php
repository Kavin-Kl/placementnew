<?php
require_once "config.php";

// Helper function to check if an array is associative
function is_assoc(array $arr) {
    if ([] === $arr) return false;
    return array_keys($arr) !== range(0, count($arr) - 1);
}

// Simplify type names - full mapping based on your Excel columns
function simplify_type_name($type) {
    $map = [
        'Certificate of Internship' => 'InternshipCertificate',
        'Upload Portfolio' => 'Portfolio',
        'Upload Cover Letter' => 'CoverLetter',
        'Certifications Upload' => 'Certifications',
        'Upload Photo' => 'Photo',
        'Upload Academic Certificates' => 'AcademicCertificates',
        'Upload ID Proof' => 'IDProof',
        'Upload Signature' => 'Signature',
        'Additional Documents (You can upload multiple files Ex: Project, Portfolio)' => 'AdditionalDocs',
        'Resume' => 'Resume'
    ];
    return $map[$type] ?? str_replace(' ', '_', $type);
}

// Build base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
define('BASE_URL', $protocol . $host . $basePath);

// Get inputs
$upid     = $_GET['upid'] ?? '';
$type     = $_GET['field'] ?? '';
$drive_id = $_GET['drive_id'] ?? '';
$role_id  = $_GET['role_id'] ?? '';

// ✅ Make role_id optional for company-wise exports
if (!$upid || !$type || !$drive_id) {
    die("Invalid parameters. Please make sure 'upid', 'field', and 'drive_id' are provided.");
}


// Use all 3 filters now
// ✅ Modify query to handle cases where role_id might not be passed
if (!empty($role_id)) {
    $stmt = $conn->prepare("SELECT student_data FROM applications WHERE upid = ? AND drive_id = ? AND role_id = ?");
    $stmt->bind_param("sii", $upid, $drive_id, $role_id);
} else {
    // Company-wise export — ignore role_id
    $stmt = $conn->prepare("SELECT student_data FROM applications WHERE upid = ? AND drive_id = ? LIMIT 1");
    $stmt->bind_param("si", $upid, $drive_id);
}

$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res) {
    die("No data found for this combination.");
}

$studentData = json_decode($res['student_data'], true);
if ($studentData === null) {
    die("Failed to decode student data.");
}

$files = $studentData[$type] ?? [];

echo "<h2>Files for $upid - $type</h2>";

if (empty($files)) {
    echo "<p>No files found.</p>";
} else {
    if (!is_array($files)) {
        $files = [$files];
    }

    $simpleType = simplify_type_name($type);
    $count = 1;

    foreach ($files as $file) {
        if (is_array($file)) {
            $path = $file['path'] ?? ($file['name'] ?? '');
            $name = $file['name'] ?? basename($path);
        } else {
            $path = $file;
            $name = basename($path);
        }

        if ($path === '') continue;

        // Get extension without dot
        $ext = pathinfo($path, PATHINFO_EXTENSION);

        // Compose display name: UPID + simplified type + optional number + extension
        $displayName = $upid . '_' . $simpleType;
        if (count($files) > 1) {
            $displayName .= $count;
        }
        $displayName .= '.' . $ext;

        $count++;

        // Generate full URL only if path starts with 'uploads/'
        $fileUrl = (strpos($path, 'uploads/') === 0) ? BASE_URL . ltrim($path, '/') : $path;

        echo "<p><a href='" . htmlspecialchars($fileUrl, ENT_QUOTES) . "' target='_blank'>" . htmlspecialchars($displayName) . "</a></p>";
    }
}
?>
