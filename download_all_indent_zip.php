<?php
session_start();
include("config.php");

$base = __DIR__ . DIRECTORY_SEPARATOR;
$zip = new ZipArchive();
$filename = $base . "uploads/Over_all_Placed_Students_all_intent_letters.zip";

if (!$zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Failed to create ZIP archive.";
    exit;
}

$result = $conn->query("SELECT intent_letter_file FROM on_off_campus_students WHERE intent_letter_file IS NOT NULL AND intent_letter_file != ''");
$added = 0;
$missing = [];
while ($row = $result->fetch_assoc()) {
    $rel = trim($row['intent_letter_file']);
    if ($rel === '') continue;
    $file = $base . ltrim($rel, '/\\');
    if (file_exists($file)) {
        $zip->addFile($file, basename($file));
        $added++;
    } else {
        $missing[] = $rel;
    }
}

$zip->close();

if ($added === 0) {
    @unlink($filename);
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    $msg = "No intent letters found to download.";
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
