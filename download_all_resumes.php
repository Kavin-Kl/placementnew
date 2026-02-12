<?php
session_start();
include("config.php");

$zip = new ZipArchive();
$filename = "uploads/all_resumes_" . time() . ".zip";

if ($zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
    $result = $conn->query("SELECT resume_file FROM applications WHERE resume_file IS NOT NULL");
    while ($row = $result->fetch_assoc()) {
        $file = $row['resume_file'];
        if (file_exists($file)) {
            $zip->addFile($file, basename($file));
        }
    }
    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    readfile($filename);
    unlink($filename);
    exit;
} else {
    echo "Failed to create ZIP.";
}
