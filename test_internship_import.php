<?php
// Create test Excel file for internship import
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set headers
$headers = [
    'Placement ID',
    'Program Type',
    'Program',
    'Course',
    'Register Number',
    'Student Name',
    'Student Mail ID',
    'Student Phone No',
    'Percentage'
];

$sheet->fromArray($headers, NULL, 'A1');

// Add test data
$testData = [
    ['TEST_INT_001_2027', 'UG', 'BSc', 'Computer Science', 'REG001', 'Test Student 1', 'test1@example.com', '9876543210', '85'],
    ['TEST_INT_002_2027', 'UG', 'BSc', 'Data Science', 'REG002', 'Test Student 2', 'test2@example.com', '9876543211', '90'],
    ['TEST_INT_003_2027', 'UG', 'BBA', 'Business Analytics', 'REG003', 'Test Student 3', 'test3@example.com', '9876543212', '88'],
];

$sheet->fromArray($testData, NULL, 'A2');

$writer = new Xlsx($spreadsheet);
$filename = 'Test_Internship_Registration_2026-2027.xlsx';
$writer->save($filename);

echo "Test file created: $filename\n";
echo "File size: " . filesize($filename) . " bytes\n";
echo "You can now import this file to test the internship registration import.\n";
?>
