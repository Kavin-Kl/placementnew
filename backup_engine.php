<?php
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;

Cell::setValueBinder(new StringValueBinder());  // <-- keep this line

use PhpOffice\PhpSpreadsheet\Spreadsheet;
$spreadsheet = new Spreadsheet();

function exportSheet($spreadsheet, $title, $query, $conn, $handleStudentData = false) {
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle(substr($title, 0, 31)); // limit to 31 characters

    $result = mysqli_query($conn, $query);
    if (!$result) {
        $sheet->setCellValue('A1', '❌ Query Failed: ' . mysqli_error($conn));
        return;
    }
    $colCount = 0;
    $row = 1;
    if ($firstRow = mysqli_fetch_assoc($result)) {
        $headers = array_keys($firstRow);
        $studentDataKeys = [];

        // ✅ Handle student_data
        if ($handleStudentData && isset($firstRow['student_data'])) {
            $jsonData = json_decode($firstRow['student_data'], true);
            if (is_array($jsonData)) {
                $studentDataKeys = array_keys($jsonData);

                // ❌ Exclude these fields
                $excludeColumns = ['UPID', 'Register No', 'Course', 'Percentage', 'Priority'];
                $studentDataKeys = array_filter($studentDataKeys, function($key) use ($excludeColumns) {
                    return !in_array($key, $excludeColumns);
                });

                $headers = array_filter($headers, fn($h) => $h !== 'student_data');
                $headers = array_merge($headers, $studentDataKeys);
            }
        }

        array_unshift($headers, 'Sl No');
        $colCount = count($headers);
        

        $sheet->fromArray($headers, null, 'A' . $row++);

        // Style
        $headerRange = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount) . '1';
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID);
        $sheet->getStyle($headerRange)->getFill()->getStartColor()->setARGB('FFFFC000');
        $sheet->getStyle($headerRange)->getAlignment()->setWrapText(true);

        // Column Widths
        for ($col = 0; $col < $colCount; $col++) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
            $sheet->getColumnDimension($columnLetter)->setWidth($col == 0 ? 12 : 30);
        }

        // First row values
        if ($handleStudentData && isset($firstRow['student_data'])) {
            $jsonData = json_decode($firstRow['student_data'], true);
            if (is_array($jsonData)) {
                unset($firstRow['student_data']);
                foreach ($studentDataKeys as $key) {
                    $firstRow[$key] = $jsonData[$key] ?? '';
                }
            }
        }

        $firstRowValues = array_values($firstRow);
        array_unshift($firstRowValues, 1); // Sl No = 1
        $sheet->fromArray($firstRowValues, null, 'A' . $row++);
        $sheet->getRowDimension(2)->setRowHeight(20);
    }

    // Loop remaining data
    $serial = 2;
    while ($data = mysqli_fetch_assoc($result)) {
        if ($handleStudentData && isset($data['student_data'])) {
            $jsonData = json_decode($data['student_data'], true);
            if (is_array($jsonData)) {
                unset($data['student_data']);
                foreach ($studentDataKeys as $key) {
                    $data[$key] = $jsonData[$key] ?? '';
                }
            }
        }
        foreach ($data as $col => $val) {
        if (is_array($val)) {
            $data[$col] = implode(', ', $val);
        }
    }

        $dataValues = array_values($data);
        array_unshift($dataValues, $serial++);
        $sheet->fromArray($dataValues, null, 'A' . $row++);
        $sheet->getRowDimension($row - 1)->setRowHeight(20);
    }

    // Wrap and align
    if ($colCount > 0) {
    $dataRange = 'A2:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount) . ($row - 1);
    $sheet->getStyle($dataRange)->getAlignment()->setWrapText(true);

    $fullRange = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount) . ($row - 1);
    $sheet->getStyle($fullRange)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
}

}
