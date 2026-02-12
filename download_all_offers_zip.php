<?php
session_start();
include("config.php");

$zip = new ZipArchive();
$filename = "uploads/Overall_Placed_Students_all_offer_letters" . ".zip";

if ($zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
    $result = $conn->query("SELECT offer_letter_file FROM on_off_campus_students WHERE offer_letter_file IS NOT NULL AND offer_letter_file != ''");

    while ($row = $result->fetch_assoc()) {
        $paths = explode(',', $row['offer_letter_file']);
        foreach ($paths as $path) {
            $file = trim($path);
            if (file_exists($file)) {
                $zip->addFile($file, basename($file)); // basename keeps the file name exactly as saved
            }
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
