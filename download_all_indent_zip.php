<?php
session_start();
include("config.php");

$zip = new ZipArchive();
$filename = "uploads/Over_all_Placed_Students_all_intent_letters" . ".zip";

if ($zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
    $result = $conn->query("SELECT intent_letter_file FROM on_off_campus_students WHERE intent_letter_file IS NOT NULL AND intent_letter_file != ''");

    while ($row = $result->fetch_assoc()) {
        $file = trim($row['intent_letter_file']);
        if (file_exists($file)) {
            $zip->addFile($file, basename($file));
        }
    }

    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('Content-Length: ' . filesize($filename));
    flush();
    readfile($filename);
    unlink($filename);
    exit;
} else {
    echo "Failed to create ZIP archive.";
}
?>
