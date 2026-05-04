<?php
session_start();
include("config.php");

$zip = new ZipArchive();
$filename = "uploads/Over_all_Placed_Students_all_photos.zip";

if (!$zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Failed to create ZIP archive.";
    exit;
}

$result = $conn->query("SELECT photo_path FROM on_off_campus_students WHERE photo_path IS NOT NULL AND photo_path != ''");
$added = 0;
$missing = [];
while ($row = $result->fetch_assoc()) {
    $file = trim($row['photo_path']);
    if ($file === '') continue;
    if (file_exists($file)) {
        $zip->addFile($file, basename($file));
        $added++;
    } else {
        $missing[] = $file;
    }
}

$zip->close();

if ($added === 0) {
    @unlink($filename);
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    $msg = "No photos found to download.";
    if (!empty($missing)) {
        $msg .= " (" . count($missing) . " referenced file(s) missing on disk: " . htmlspecialchars(implode(', ', array_slice($missing, 0, 3))) . (count($missing) > 3 ? ", ..." : "") . ")";
    }
    echo "<script>alert(" . json_encode($msg) . "); window.history.back();</script>";
    exit;
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Content-Length: ' . filesize($filename));
flush();
readfile($filename);
unlink($filename);
exit;
