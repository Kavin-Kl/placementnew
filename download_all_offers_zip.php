<?php
session_start();
include("config.php");

$zip = new ZipArchive();
$filename = "uploads/Overall_Placed_Students_all_offer_letters.zip";

if (!$zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Failed to create ZIP archive.";
    exit;
}

$result = $conn->query("SELECT offer_letter_file FROM on_off_campus_students WHERE offer_letter_file IS NOT NULL AND offer_letter_file != ''");
$added = 0;
$missing = [];
while ($row = $result->fetch_assoc()) {
    $paths = explode(',', $row['offer_letter_file']);
    foreach ($paths as $path) {
        $file = trim($path);
        if ($file === '') continue;
        if (file_exists($file)) {
            $zip->addFile($file, basename($file));
            $added++;
        } else {
            $missing[] = $file;
        }
    }
}

$zip->close();

if ($added === 0) {
    @unlink($filename);
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    $msg = "No offer letters found to download.";
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
