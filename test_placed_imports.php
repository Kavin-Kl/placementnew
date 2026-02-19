<?php
// Create test Excel files for placed imports
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// === Internship Placed Test File ===
$spreadsheet1 = new Spreadsheet();
$sheet1 = $spreadsheet1->getActiveSheet();

$headers = [
    'Placement ID', 'Program Type', 'Program', 'Course', 'Register Number',
    'Student Name', 'Student Mail ID', 'Student Phone No', 'Percentage',
    'Job Offer Type', 'Company Drive No', 'Company Name', 'Designation', 'CTC', 'Stipend'
];

$sheet1->fromArray($headers, NULL, 'A1');

$testData1 = [
    ['TEST_INT_001_2027', 'UG', 'BSc', 'Computer Science', 'REG001', 'Test Student 1', 'test1@example.com', '9876543210', '85', 'Internship', 'Drive 1', 'Test Company A', 'Intern', '', '10000PM'],
    ['TEST_INT_002_2027', 'UG', 'BSc', 'Data Science', 'REG002', 'Test Student 2', 'test2@example.com', '9876543211', '90', 'Internship', 'Drive 1', 'Test Company B', 'Data Intern', '', '12000PM'],
];

$sheet1->fromArray($testData1, NULL, 'A2');

$writer1 = new Xlsx($spreadsheet1);
$filename1 = 'Test_Internship_Placed.xlsx';
$writer1->save($filename1);

echo "✓ Internship Placed test file created: $filename1 (" . filesize($filename1) . " bytes)\n";

// === Vantage Placed Test File ===
$spreadsheet2 = new Spreadsheet();
$sheet2 = $spreadsheet2->getActiveSheet();

$sheet2->fromArray($headers, NULL, 'A1');

$testData2 = [
    ['MCC25VAN_TEST001', 'UG', 'BBA', 'Business Analytics', 'VAN001', 'Vantage Test 1', 'vtest1@example.com', '9876543220', '88', 'FTE', 'Drive 2', 'Vantage Corp', 'Analyst', '5 LPA', ''],
    ['MCC25VAN_TEST002', 'UG', 'BBA', 'Marketing', 'VAN002', 'Vantage Test 2', 'vtest2@example.com', '9876543221', '92', 'FTE', 'Drive 2', 'Vantage Corp', 'Associate', '6 LPA', ''],
];

$sheet2->fromArray($testData2, NULL, 'A2');

$writer2 = new Xlsx($spreadsheet2);
$filename2 = 'Test_Vantage_Placed.xlsx';
$writer2->save($filename2);

echo "✓ Vantage Placed test file created: $filename2 (" . filesize($filename2) . " bytes)\n";

echo "\nTest files ready! You can now:\n";
echo "1. Import Test_Internship_Registration_2026-2027.xlsx to Internship Registered Students page\n";
echo "2. Import Test_Internship_Placed.xlsx to Internship Placed Students page\n";
echo "3. Import Test_Vantage_Placed.xlsx to Vantage Placed Students page\n";
?>
