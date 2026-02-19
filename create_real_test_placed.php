<?php
// Create test Excel file using REAL students from database
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$headers = [
    'Placement ID', 'Program Type', 'Program', 'Course', 'Register Number',
    'Student Name', 'Student Mail ID', 'Student Phone No', 'Percentage',
    'Job Offer Type', 'Company Drive No', 'Company Name', 'Designation', 'CTC', 'Stipend'
];

$sheet->fromArray($headers, NULL, 'A1');

// Using REAL students that exist in the database
$testData = [
    ['MCC25PLC338', 'UG', 'Bachelor of Commerce', 'B.COM - Corporate Finance', 'REG338', 'MANESHA.A.B.', 'manesha@example.com', '9876543210', '85', 'Internship', 'Drive001', 'Test Company A', 'Intern', '', '10000PM'],
    ['MCC25PLC574', 'UG', 'Bachelor of Computer Applications', 'BACHELOR OF COMPUTER APPLICATIONS', 'REG574', 'SNEHA.A', 'sneha@example.com', '9876543211', '90', 'Internship', 'Drive001', 'Test Company B', 'Data Intern', '', '12000PM'],
    ['MCC25PLC1', 'UG', 'Bachelor of Business Administration', 'BBA - Regular', 'REG001', 'NEHA SHARMA', 'neha@example.com', '9876543212', '88', 'Internship', 'Drive002', 'Test Company C', 'Business Intern', '', '15000PM'],
];

$sheet->fromArray($testData, NULL, 'A2');

$writer = new Xlsx($spreadsheet);
$filename = 'Test_Internship_Placed_REAL.xlsx';
$writer->save($filename);

echo "✓ Real student test file created: $filename (" . filesize($filename) . " bytes)\n";
echo "\nThis file uses REAL students from your database:\n";
echo "- MCC25PLC338 (MANESHA.A.B.)\n";
echo "- MCC25PLC574 (SNEHA.A)\n";
echo "- MCC25PLC1 (NEHA SHARMA)\n";
echo "\nYou can now import this to Internship Placed Students page.\n";
?>
