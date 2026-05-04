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

// Get drive_id parameter
$drive_id = $_GET['drive_id'] ?? null;

if (!$drive_id) {
    die("Error: No drive ID specified");
}

// Get drive details
$drive_stmt = $conn->prepare("SELECT company_name, drive_no, academic_year FROM drives WHERE drive_id = ?");
$drive_stmt->bind_param("i", $drive_id);
$drive_stmt->execute();
$drive_result = $drive_stmt->get_result();
$drive = $drive_result->fetch_assoc();

if (!$drive) {
    die("Error: Drive not found");
}

// Refuse to export drives that belong to a different academic year than the
// admin currently has selected — prevents URL-tampering exports across years.
$selected_ay = $_SESSION['selected_academic_year'] ?? null;
if ($selected_ay && !empty($drive['academic_year']) && $drive['academic_year'] !== $selected_ay) {
    die("Error: This drive belongs to academic year " . htmlspecialchars($drive['academic_year']) . ", but you are currently viewing " . htmlspecialchars($selected_ay) . ". Switch academic year to export this drive.");
}

// Get applications with round results for this drive
$query = "
    SELECT
        a.application_id,
        s.upid,
        s.student_name,
        s.email,
        s.phone_no,
        s.course,
        dr.designation_name as role,
        dr.offer_type,
        a.status as application_status
    FROM applications a
    INNER JOIN students s ON a.student_id = s.student_id
    LEFT JOIN drive_roles dr ON a.role_id = dr.role_id
    WHERE a.drive_id = ?
    ORDER BY s.student_name ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $drive_id);
$stmt->execute();
$applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// For each application, get its rounds
foreach ($applications as &$app) {
    $rounds_stmt = $conn->prepare("
        SELECT
            round_name,
            round_type,
            scheduled_date,
            result,
            comments
        FROM application_rounds
        WHERE application_id = ?
        ORDER BY created_at ASC
    ");
    $rounds_stmt->bind_param("i", $app['application_id']);
    $rounds_stmt->execute();
    $app['rounds'] = $rounds_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Create new Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Round Results');

// Set title
$sheet->setCellValue('A1', 'ROUND RESULTS - ' . $drive['company_name'] . ' (Drive #' . $drive['drive_no'] . ')');
$sheet->mergeCells('A1:M1'); // M is the last column after dropping Marked By/Date
$sheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '650000']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);

// Set headers
$headers = [
    'UPID',
    'Student Name',
    'Email',
    'Phone',
    'Course',
    'Role',
    'Offer Type',
    'Application Status',
    'Round Name',
    'Round Type',
    'Scheduled Date',
    'Result',
    'Comments'
];

$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '3', $header);
    $sheet->getStyle($col . '3')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '650000']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    $sheet->getColumnDimension($col)->setAutoSize(true);
    $col++;
}

// Add data
$row = 4;
foreach ($applications as $app) {
    if (empty($app['rounds'])) {
        // Student with no rounds - show basic info only
        $sheet->setCellValue('A' . $row, $app['upid']);
        $sheet->setCellValue('B' . $row, $app['student_name']);
        $sheet->setCellValue('C' . $row, $app['email']);
        $sheet->setCellValue('D' . $row, $app['phone_no']);
        $sheet->setCellValue('E' . $row, $app['course']);
        $sheet->setCellValue('F' . $row, $app['role']);
        $sheet->setCellValue('G' . $row, $app['offer_type']);
        $sheet->setCellValue('H' . $row, ucfirst(str_replace('_', ' ', $app['application_status'])));
        $sheet->setCellValue('I' . $row, 'No rounds yet');
        $row++;
    } else {
        // Student with rounds - show one row per round
        foreach ($app['rounds'] as $round) {
            $sheet->setCellValue('A' . $row, $app['upid']);
            $sheet->setCellValue('B' . $row, $app['student_name']);
            $sheet->setCellValue('C' . $row, $app['email']);
            $sheet->setCellValue('D' . $row, $app['phone_no']);
            $sheet->setCellValue('E' . $row, $app['course']);
            $sheet->setCellValue('F' . $row, $app['role']);
            $sheet->setCellValue('G' . $row, $app['offer_type']);
            $sheet->setCellValue('H' . $row, ucfirst(str_replace('_', ' ', $app['application_status'])));
            $sheet->setCellValue('I' . $row, $round['round_name']);
            $sheet->setCellValue('J' . $row, $round['round_type']);
            $sheet->setCellValue('K' . $row, $round['scheduled_date'] ?? 'Not scheduled');
            $sheet->setCellValue('L' . $row, ucfirst($round['result'] ?? 'Pending'));
            $sheet->setCellValue('M' . $row, $round['comments'] ?? '');
            $row++;
        }
    }
}

// Apply borders to all cells
if ($row > 4) {
    $sheet->getStyle('A3:M' . ($row - 1))->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ]);
}

// Set filename with timestamp
$company_name_clean = preg_replace('/[^A-Za-z0-9_\-]/', '_', $drive['company_name']);
$filename = 'Round_Results_' . $company_name_clean . '_Drive' . $drive['drive_no'] . '_' . date('Y-m-d_His') . '.xlsx';

// Send file to browser
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
