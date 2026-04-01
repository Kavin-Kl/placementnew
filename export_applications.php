<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

include("config.php");
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Get filter parameters
$company_filter = $_GET['company'] ?? '';
$role_filter = $_GET['role'] ?? '';
$upid_filter = $_GET['upid'] ?? '';
$reg_no_filter = $_GET['reg_no'] ?? '';
$status_filter = $_GET['status'] ?? '';
$academic_year = $_SESSION['selected_academic_year'] ?? '';

// Build query with filters
$sql = "SELECT
    a.application_id,
    a.upid,
    a.reg_no,
    s.student_name,
    s.email,
    s.phone_no,
    s.course,
    d.company_name,
    d.drive_no,
    r.designation_name as role,
    r.offer_type,
    r.ctc,
    r.stipend,
    a.status,
    a.comments,
    a.placement_batch,
    a.status_changed,
    a.applied_at
FROM applications a
LEFT JOIN students s ON a.upid = s.upid
LEFT JOIN drives d ON a.drive_id = d.drive_id
LEFT JOIN drive_roles r ON a.role_id = r.role_id
WHERE 1=1";

$params = [];
$types = '';

// Apply filters
if (!empty($company_filter)) {
    $sql .= " AND d.company_name LIKE ?";
    $params[] = '%' . $company_filter . '%';
    $types .= 's';
}

if (!empty($role_filter)) {
    $sql .= " AND r.designation_name LIKE ?";
    $params[] = '%' . $role_filter . '%';
    $types .= 's';
}

if (!empty($upid_filter)) {
    $sql .= " AND a.upid LIKE ?";
    $params[] = '%' . $upid_filter . '%';
    $types .= 's';
}

if (!empty($reg_no_filter)) {
    $sql .= " AND a.reg_no LIKE ?";
    $params[] = '%' . $reg_no_filter . '%';
    $types .= 's';
}

if (!empty($status_filter)) {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($academic_year)) {
    $sql .= " AND d.academic_year = ?";
    $params[] = $academic_year;
    $types .= 's';
}

$sql .= " ORDER BY d.company_name, d.drive_no, r.designation_name, s.student_name";

// Execute query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Create new Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Applications Export');

// Set headers
$headers = [
    'Application ID',
    'UPID',
    'Register No',
    'Student Name',
    'Email',
    'Phone',
    'Course',
    'Company Name',
    'Drive No',
    'Role',
    'Offer Type',
    'CTC',
    'Stipend',
    'Status',
    'Comments',
    'Batch',
    'Status Changed',
    'Applied At'
];

$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '1', $header);
    $sheet->getStyle($col . '1')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '650000']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $sheet->getColumnDimension($col)->setAutoSize(true);
    $col++;
}

// Add data
$row = 2;
while ($data = $result->fetch_assoc()) {
    $sheet->setCellValue('A' . $row, $data['application_id']);
    $sheet->setCellValue('B' . $row, $data['upid']);
    $sheet->setCellValue('C' . $row, $data['reg_no']);
    $sheet->setCellValue('D' . $row, $data['student_name']);
    $sheet->setCellValue('E' . $row, $data['email']);
    $sheet->setCellValue('F' . $row, $data['phone_no']);
    $sheet->setCellValue('G' . $row, $data['course']);
    $sheet->setCellValue('H' . $row, $data['company_name']);
    $sheet->setCellValue('I' . $row, $data['drive_no']);
    $sheet->setCellValue('J' . $row, $data['role']);
    $sheet->setCellValue('K' . $row, $data['offer_type']);
    $sheet->setCellValue('L' . $row, $data['ctc']);
    $sheet->setCellValue('M' . $row, $data['stipend']);
    $sheet->setCellValue('N' . $row, ucfirst(str_replace('_', ' ', $data['status'])));
    $sheet->setCellValue('O' . $row, $data['comments']);
    $sheet->setCellValue('P' . $row, ucfirst($data['placement_batch']));
    $sheet->setCellValue('Q' . $row, $data['status_changed']);
    $sheet->setCellValue('R' . $row, $data['applied_at']);

    $row++;
}

// Apply borders to all cells
$sheet->getStyle('A1:R' . ($row - 1))->applyFromArray([
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
]);

// Set filename with timestamp
$filename = 'Applications_Export_' . date('Y-m-d_His') . '.xlsx';

// Send file to browser
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
