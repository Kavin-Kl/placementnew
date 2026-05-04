<?php
session_start();
include("config.php");

// Get drive_id from URL
$drive_id = $_GET['drive_id'] ?? null;
$file_index = isset($_GET['index']) ? max(0, (int)$_GET['index']) : 0;

if (!$drive_id) {
    die("Invalid drive ID");
}

// Fetch JD file from database
$stmt = $conn->prepare("SELECT jd_file, company_name FROM drives WHERE drive_id = ?");
$stmt->bind_param("i", $drive_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Drive not found");
}

$drive = $result->fetch_assoc();
$jd_file = $drive['jd_file'];
$company_name = $drive['company_name'];

if (empty($jd_file) || $jd_file === '[]' || $jd_file === 'null') {
    die("No job description available");
}

// Check if it's a JSON array of file paths
$jd_files = json_decode($jd_file, true);
if (is_array($jd_files)) {
    // Drop empty entries — older drives sometimes stored ["", null]
    $jd_files = array_values(array_filter($jd_files, fn($p) => is_string($p) && trim($p) !== ''));
}
if (is_array($jd_files) && count($jd_files) > 0) {
    // Pick the requested file (defaults to first); clamp to a valid index.
    if (!isset($jd_files[$file_index])) {
        $file_index = 0;
    }
    $file_path = $jd_files[$file_index];

    // Check if file exists
    if (file_exists($file_path)) {
        $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        // Set MIME type based on extension
        $mime_types = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png'
        ];

        $mime_type = $mime_types[$file_extension] ?? 'application/octet-stream';

        // Output the file
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    } else {
        die("File not found: " . htmlspecialchars($file_path));
    }
}

// Check if it's base64 encoded (starts with data:)
if (strpos($jd_file, 'data:') === 0) {
    // Extract MIME type and base64 data
    preg_match('/data:([^;]+);base64,(.+)/', $jd_file, $matches);

    if (count($matches) === 3) {
        $mime_type = $matches[1];
        $base64_data = $matches[2];
        $file_data = base64_decode($base64_data);

        // Set appropriate headers
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: inline; filename="' . str_replace(' ', '_', $company_name) . '_JD.pdf"');
        header('Content-Length: ' . strlen($file_data));

        echo $file_data;
        exit;
    }
}

die("Unable to display job description");
?>
