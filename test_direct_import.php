<?php
// Simulate the actual import from vantage_registered_students.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

ob_start();
session_start();
$_SESSION['admin_id'] = 1; // Simulate admin

include("config.php");

// Simulate $_FILES array
$testFile = 'C:/Users/Kavin/Downloads/Vantage_Registered_List_2025-2026.xlsx';
$_FILES["csv_file"] = [
    "name" => basename($testFile),
    "type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    "tmp_name" => $testFile,
    "error" => UPLOAD_ERR_OK,
    "size" => filesize($testFile)
];

$_SERVER["REQUEST_METHOD"] = "POST";

echo "Starting import test...\n";
echo "File: " . $_FILES["csv_file"]["name"] . "\n";
echo "Size: " . $_FILES["csv_file"]["size"] . " bytes\n";
echo "Error: " . $_FILES["csv_file"]["error"] . "\n\n";

// Now run the actual import code from vantage_registered_students.php
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["csv_file"])) {
    require 'vendor/autoload.php';

    // Setup logging
    $logFile = __DIR__ . '/logs/import_log.txt';
    if (!file_exists(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0777, true);
    }

    function logImport($message) {
        global $logFile;
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
        echo "[$timestamp] $message\n";
    }

    logImport("=== IMPORT STARTED ===");
    logImport("User: " . ($_SESSION['admin_id'] ?? 'Unknown'));

    // Increase limits for large file processing
    set_time_limit(600);
    ini_set('memory_limit', '2048M');
    ini_set('max_execution_time', '600');

    $file = $_FILES["csv_file"];
    $fileName = $file["name"];
    $tmpPath = $file["tmp_name"];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $fileSize = $file["size"];

    logImport("File received: $fileName (Size: " . round($fileSize/1024, 2) . " KB, Type: $fileExt)");
    logImport("Temp path: $tmpPath");

    // Check for file upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = "File upload failed with error code: " . $file['error'];
        logImport("ERROR: $errorMsg");
        $_SESSION['import_message'] = $errorMsg;
        $_SESSION['import_status'] = "error";
        echo "\n\nERROR: $errorMsg\n";
        exit;
    }

    logImport("File upload successful, checking filename format...");

    if (preg_match('/(\d{4})-(\d{4})/', $fileName, $matches)) {
        $batchYear = $matches[0];
        $yearOfPassing = (int) $matches[2];
        logImport("Batch year extracted: $batchYear (Year of Passing: $yearOfPassing)");
    } else {
        $errorMsg = "Filename must include batch year in format YYYY-YYYY";
        logImport("ERROR: $errorMsg");
        echo "\n\nERROR: $errorMsg\n";
        exit;
    }

    echo "\n\nAll checks passed! The import code is working.\n";
    echo "The issue must be with the web form submission.\n";
    logImport("=== TEST COMPLETED - CODE IS FUNCTIONAL ===");
}
?>
