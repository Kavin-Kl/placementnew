<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$file = 'C:\Users\Kavin\Downloads\Registration_2025-2026.xlsx';

if (!file_exists($file)) {
    die("File not found: $file\n");
}

echo "Reading file: $file\n";
echo "File size: " . filesize($file) . " bytes\n\n";

try {
    $spreadsheet = IOFactory::load($file);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();

    echo "Total rows: " . count($rows) . "\n\n";

    // Show header
    echo "HEADERS:\n";
    print_r($rows[0]);

    echo "\nFIRST 3 DATA ROWS:\n";
    for ($i = 1; $i <= min(3, count($rows) - 1); $i++) {
        echo "Row $i:\n";
        print_r($rows[$i]);
        echo "\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
