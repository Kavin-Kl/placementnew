<?php
session_start();
include("config.php");

$zip = new ZipArchive();
$filename = "uploads/Over_all_Placed_Students_all_photos". ".zip";

if ($zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
    $result = $conn->query("SELECT photo_path FROM on_off_campus_students WHERE photo_path IS NOT NULL AND photo_path != ''");

    while ($row = $result->fetch_assoc()) {
        $file = trim($row['photo_path']);
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
