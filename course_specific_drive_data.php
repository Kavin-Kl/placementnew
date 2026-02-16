<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}
include("config.php");
include("course_groups_dynamic.php");
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_single_row'])) {
    $id = $_POST['row_id'];
    $spo = $_POST['spo_name'];
    $spoc_email = $_POST['spoc_email'] ?? '';
    $contact = $_POST['contact_no'];
    $follow = $_POST['follow_status'];
    $final = $_POST['final_status'];
    $person = $_POST['follow_up_person'] ?? '';  // Added null coalescing operator

    // Fetch the live hired count
   // Fetch the live hired count using drive_id and role_id for accuracy
$sql = "
  SELECT COUNT(DISTINCT ps.student_id) as count
  FROM placed_students ps
  INNER JOIN drive_data dd ON ps.drive_id = dd.drive_id AND ps.role_id = dd.role_id
  WHERE dd.id = ?
";

$stmtCount = $conn->prepare($sql);
$stmtCount->bind_param("i", $id);
$stmtCount->execute();
$result = $stmtCount->get_result();
$row = $result->fetch_assoc();
$hiredCount = $row['count'] ?? 0;



    
    // Now update hired_count too!
    $stmt = $conn->prepare("
      UPDATE drive_data
      SET spo_name = ?, spoc_email = ?, contact_no = ?, follow_status = ?, final_status = ?, follow_up_person = ?, hired_count = ?
      WHERE id = ?
    ");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    // Changed to "ssssssii" to account for spoc_email and follow_up_person
    if (!$stmt->bind_param("ssssssii", $spo, $spoc_email, $contact, $follow, $final, $person, $hiredCount, $id)) {
        die("Bind failed: " . $stmt->error);
    }

    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }
    
    // Return success response for AJAX
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['status' => 'success']);
        exit;
    }
}

function buildOptions($arr, $selected) {
    return implode('', array_map(fn($v) => "<option ".($v==$selected?'selected':'').">$v</option>", $arr));
}

function formatCourseDisplay($courses) {
    global $UG_COURSES, $PG_COURSES;

    $selected = is_array($courses) ? $courses : [];
    $selected = array_filter($selected, fn($v) => $v !== "on" && trim($v) !== "");

    $selected_lower = array_map('strtolower', $selected);
    $ug_lower = array_map('strtolower', $UG_COURSES);
    $pg_lower = array_map('strtolower', $PG_COURSES);

    $display = [];

    $ugMatch = array_intersect($selected_lower, $ug_lower);
    $pgMatch = array_intersect($selected_lower, $pg_lower);

    if (count($ugMatch) === count($UG_COURSES)) {
        $display[] = "All UG Courses";
    } else {
        foreach ($UG_COURSES as $ug) {
            if (in_array(strtolower($ug), $selected_lower)) {
                $display[] = $ug;
            }
        }
    }

    if (count($pgMatch) === count($PG_COURSES)) {
        $display[] = "All PG Courses";
    } else {
        foreach ($PG_COURSES as $pg) {
            if (in_array(strtolower($pg), $selected_lower)) {
                $display[] = $pg;
            }
        }
    }

    foreach ($selected as $item) {
        $lower = strtolower($item);
        if (!in_array($lower, $ug_lower) && !in_array($lower, $pg_lower)) {
            $display[] = $item;
        }
    }

    return implode(', ', $display);
}

// Dropdown master data
$statuses = [];
$offerTypes = [];
$sectors = [];

$offerTypeQuery = $conn->query("SELECT DISTINCT offer_type FROM drive_roles WHERE offer_type IS NOT NULL");
while ($row = $offerTypeQuery->fetch_assoc()) {
    $offerTypes[] = $row['offer_type'];
}

$sectorQuery = $conn->query("SELECT DISTINCT sector FROM drive_roles WHERE sector IS NOT NULL");
while ($row = $sectorQuery->fetch_assoc()) {
    $sectors[] = $row['sector'];
}

$ugCourses = $UG_GROUPED_COURSES;
$pgCourses = $PG_GROUPED_COURSES;
$allCourses = array_merge($UG_COURSES, $PG_COURSES);

// Sample data
$data = [];
// DISABLED: Import from Excel already has complete data
// include_once __DIR__ . '/sync_placed_students.php';
// sync_placed_students($conn);

$sql = "
SELECT
  d.*,
  drv.drive_no AS drive_number,
  drv.open_date AS opening_date,
  MIN(dr.close_date) AS closing_date,
  drv.created_by,
  (
    SELECT COUNT(DISTINCT student_id)
    FROM placed_students ps
    WHERE
      ps.company_name = d.company_name
      AND ps.role = d.role
  ) AS hired_count
FROM drive_data d
LEFT JOIN drives drv ON d.company_name = drv.company_name AND d.drive_no = drv.drive_no
LEFT JOIN drive_roles dr ON drv.drive_id = dr.drive_id AND TRIM(d.role) = TRIM(dr.designation_name)
GROUP BY d.id
ORDER BY d.company_name ASC, d.id ASC
";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
while ($row = $result->fetch_assoc()) {
    $row['eligible_courses'] = json_decode($row['eligible_courses'], true);
    if (!is_array($row['eligible_courses'])) {
        $row['eligible_courses'] = [];
    }

    // Calculate actual hired count from placed_students table
    // Match by company_name and role instead of IDs (since drive_id/role_id may be NULL in drive_data)
    $hiredCountSql = "
        SELECT COUNT(DISTINCT student_id) as count
        FROM placed_students
        WHERE company_name = ? AND role = ?
    ";
    $hiredStmt = $conn->prepare($hiredCountSql);
    $hiredStmt->bind_param("ss", $row['company_name'], $row['role']);
    $hiredStmt->execute();
    $hiredResult = $hiredStmt->get_result();
    $actualHired = $hiredResult->fetch_assoc()['count'] ?? 0;
    $hiredStmt->close();

    // Update the row with actual hired count
    $row['hired_count'] = $actualHired;

    // Update hired_count in database
    $updateStmt = $conn->prepare("UPDATE drive_data SET hired_count = ? WHERE id = ?");
    $updateStmt->bind_param("ii", $actualHired, $row['id']);
    $updateStmt->execute();
    $updateStmt->close();

    $data[] = $row;
}

}

// Filters
$filter = $_GET;

// Normalize date filters: accept d-m-Y from UI and convert to Y-m-d for comparisons
if (!empty($filter['opening_date']) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $filter['opening_date'])) {
    $dt = DateTime::createFromFormat('d-m-Y', $filter['opening_date']);
    if ($dt) { $filter['opening_date'] = $dt->format('Y-m-d'); }
}
if (!empty($filter['closing_date']) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $filter['closing_date'])) {
    $dt2 = DateTime::createFromFormat('d-m-Y', $filter['closing_date']);
    if ($dt2) { $filter['closing_date'] = $dt2->format('Y-m-d'); }
}

$filtered = array_filter($data, function($r) use($filter) {
    // Dedicated company_name filter (decoupled from live search)
    if (!empty($filter['company_name']) && stripos($r['company_name'], $filter['company_name']) === false) return false;
    
    foreach (['offer_type','sector','drive_number','created_by','spo_name','final_status','follow_up_person'] as $f) {
        if (!empty($filter[$f]) && $r[$f] !== $filter[$f]) return false;
    }
    // Date equality (compare date parts only)
    if (!empty($filter['opening_date'])) {
        $rowOpen = !empty($r['opening_date']) ? substr($r['opening_date'], 0, 10) : '';
        if ($rowOpen !== $filter['opening_date']) return false;
    }
    if (!empty($filter['closing_date'])) {
        $rowClose = !empty($r['closing_date']) ? substr($r['closing_date'], 0, 10) : '';
        if ($rowClose !== $filter['closing_date']) return false;
    }

    if (!empty($filter['course'])) {
        $selected = $filter['course'];
        if (!is_array($selected)) { $selected = [$selected]; }
        $selected = array_values(array_filter($selected, fn($v) => trim((string)$v) !== ''));
        $courses = is_array($r['eligible_courses']) ? $r['eligible_courses'] : [];

        // If specific course(s) selected (not broad selections), exclude drives with broad course options FIRST
        $broadTerms = ['ALL', 'All UG', 'All PG', 'all ug courses', 'all pg courses', 'All UG Courses', 'All PG Courses'];
        $hasSpecificCourse = !in_array('ALL', $selected) && !in_array('ALL_UG', $selected) && !in_array('ALL_PG', $selected);

        if ($hasSpecificCourse) {
            // Exclude if eligible_courses contains any broad terms OR has all UG/PG courses (case-insensitive)
            $courses_lower = array_map('strtolower', $courses);
            $broadTerms_lower = array_map('strtolower', $broadTerms);

            foreach ($broadTerms_lower as $broad) {
                if (in_array($broad, $courses_lower)) {
                    return false;
                }
            }

            // Also exclude if drive has ALL UG courses listed (meaning it was created with "Select All UG")
            // Check if drive has more than 40 courses (indicating it's a bulk selection)
            if (count($courses) > 40) {
                return false;
            }
        }

        // Then check if any selected course matches
        if (count(array_intersect($selected, $courses)) === 0) return false;
    }

    return true;
});

// Export CSV with selected fields
// Export XLSX with selected fields (only filtered records)
// Export XLSX with selected fields (only filtered records)
// Export XLSX with selected fields (only filtered records)
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    require 'vendor/autoload.php';
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Get selected fields from POST or use default
    $selectedFields = $_POST['export_fields'] ?? [
        'company_name', 'drive_number', 'role', 'offer_type', 'sector',
        'courses', 'opening_date', 'closing_date', 'created_by',
        'spo_name', 'spoc_email', 'contact_no', 'follow_status', 'final_status',
        'hired_count', 'follow_up_person'
    ];
    array_unshift($selectedFields, 'slno'); // Always add Sl No as the first column
    
    // Map field names to display names
    $fieldLabels = [
        'slno' => 'Sl No',
        'company_name' => 'Company Name',
        'drive_number' => 'Drive No',
        'role' => 'Role',
        'offer_type' => 'Offer Type',
        'sector' => 'Sector',
        'courses' => 'Courses',
        'opening_date' => 'Form Open Date',
        'closing_date' => 'Role Close Date',
        'created_by' => 'Created By',
        'spo_name' => 'SPOC Name',
        'spoc_email' => 'SPOC Email ID',
        'contact_no' => 'SPOC Contact Number',
        'follow_status' => 'Follow-Up Status',
        'final_status' => 'Final Status',
        'hired_count' => 'Hired',
        'follow_up_person' => 'Follow-Up Person'
    ];
    
    // Combine GET and POST filters (for live search)
    $allFilters = array_merge($_GET, $_POST);
    
    // Apply all filters to the data
    $filteredForExport = array_filter($data, function($r) use($allFilters) {
        // Company name filter
        if (!empty($allFilters['company_name']) && stripos($r['company_name'], $allFilters['company_name']) === false) {
            return false;
        }

        // Search filter
        if (!empty($allFilters['search'])) {
            $search = strtolower($allFilters['search']);
            if (
                stripos((string)$r['company_name'], $search) === false &&
                stripos((string)$r['drive_number'], $search) === false &&
                stripos((string)$r['role'], $search) === false &&
                stripos((string)$r['offer_type'], $search) === false &&
                stripos((string)$r['sector'], $search) === false &&
                stripos(formatCourseDisplay($r['eligible_courses']), $search) === false &&
                stripos((string)$r['created_by'], $search) === false &&
                stripos((string)$r['spo_name'], $search) === false &&
                stripos((string)$r['spoc_email'], $search) === false &&
                stripos((string)$r['contact_no'], $search) === false &&
                stripos((string)$r['follow_status'], $search) === false &&
                stripos((string)$r['final_status'], $search) === false &&
                stripos((string)$r['hired_count'], $search) === false &&
                stripos((string)$r['follow_up_person'], $search) === false
            ) {
                return false;
            }
        }
        
        // Standard filters (non-date)
        foreach (['offer_type','sector','drive_number','created_by','spo_name','final_status','follow_up_person'] as $f) {
            if (!empty($allFilters[$f]) && $r[$f] !== $allFilters[$f]) {
                return false;
            }
        }
        
        // Date filters (compare date parts only)
        if (!empty($allFilters['opening_date'])) {
            $fOpen = $allFilters['opening_date'];
            if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $fOpen)) {
                $dt = DateTime::createFromFormat('d-m-Y', $fOpen);
                if ($dt) { $fOpen = $dt->format('Y-m-d'); }
            }
            $rowOpen = !empty($r['opening_date']) ? substr($r['opening_date'],0,10) : '';
            if ($rowOpen !== $fOpen) return false;
        }
        
        if (!empty($allFilters['closing_date'])) {
            $fClose = $allFilters['closing_date'];
            if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $fClose)) {
                $dt2 = DateTime::createFromFormat('d-m-Y', $fClose);
                if ($dt2) { $fClose = $dt2->format('Y-m-d'); }
            }
            $rowClose = !empty($r['closing_date']) ? substr($r['closing_date'],0,10) : '';
            if ($rowClose !== $fClose) return false;
        }

        // Course filter (multi-select OR semantics)
        if (!empty($allFilters['course'])) {
            $selected = $allFilters['course'];
            if (!is_array($selected)) { $selected = [$selected]; }
            $selected = array_values(array_filter($selected, fn($v) => trim((string)$v) !== ''));
            $courses = is_array($r['eligible_courses']) ? $r['eligible_courses'] : [];

            // If specific course(s) selected (not broad selections), exclude drives with broad course options FIRST
            $broadTerms = ['ALL', 'All UG', 'All PG', 'all ug courses', 'all pg courses', 'All UG Courses', 'All PG Courses'];
            $hasSpecificCourse = !in_array('ALL', $selected) && !in_array('ALL_UG', $selected) && !in_array('ALL_PG', $selected);

            if ($hasSpecificCourse) {
                // Exclude if eligible_courses contains any broad terms (case-insensitive)
                $courses_lower = array_map('strtolower', $courses);
                $broadTerms_lower = array_map('strtolower', $broadTerms);

                foreach ($broadTerms_lower as $broad) {
                    if (in_array($broad, $courses_lower)) {
                        return false;
                    }
                }

                // Also exclude if drive has ALL UG courses listed (meaning it was created with "Select All UG")
                // Check if drive has more than 40 courses (indicating it's a bulk selection)
                if (count($courses) > 40) {
                    return false;
                }
            }

            // Then check if any selected course matches
            if (count(array_intersect($selected, $courses)) === 0) return false;
        }

        return true;
    });

    // Reset array keys to ensure proper sequential indexing
    $filteredForExport = array_values($filteredForExport);

    // Write headers
    $headers = [];
    foreach ($selectedFields as $field) {
        $headers[] = $fieldLabels[$field] ?? ucfirst($field);
    }
    $sheet->fromArray($headers, NULL, 'A1');

    // Add data
    $rowNum = 2;
    $slNo = 1; // Initialize sequential serial number counter
    
    foreach ($filteredForExport as $row) {
        $col = 1;
        foreach ($selectedFields as $field) {
            switch ($field) {
                case 'slno':
                    $value = $slNo; // Use sequential counter
                    break;
                case 'courses':
                    $value = formatCourseDisplay($row['eligible_courses']);
                    break;
                case 'opening_date':
                    $value = !empty($row['opening_date']) ? date('d-m-Y', strtotime($row['opening_date'])) : '';
                    break;
                case 'closing_date':
                    $value = !empty($row['closing_date']) ? date('d-m-Y', strtotime($row['closing_date'])) : '';
                    break;
                case 'hired_count':
                    $value = isset($row['hired_count']) ? $row['hired_count'] : ($row['no_of_hired'] ?? 0);
                    break;
                default:
                    $value = $row[$field] ?? '';
            }
            
            // Set cell value with explicit type
            $cell = $sheet->getCellByColumnAndRow($col, $rowNum);
            $cell->setValueExplicit($value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            
            // Set text wrapping for courses column
            if ($field === 'courses') {
                $sheet->getStyleByColumnAndRow($col, $rowNum)
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
            }
            
            $col++;
        }
        
        // Set row height to 30 points
        $sheet->getRowDimension($rowNum)->setRowHeight(30);
        $rowNum++;
        $slNo++; // Increment the serial number for next row
    }
    
    // Set column widths
    $columnWidths = [
        'slno' => 10,
        'company_name' => 25,
        'drive_number' => 12,
        'role' => 25,
        'offer_type' => 15,
        'sector' => 20,
        'courses' => 35,
        'opening_date' => 18,
        'closing_date' => 18,
        'created_by' => 20,
        'spo_name' => 20,
        'spoc_email' => 25,
        'contact_no' => 15,
        'follow_status' => 20,
        'final_status' => 20,
        'hired_count' => 10,
        'follow_up_person' => 20
    ];

    $col = 1;
    foreach ($selectedFields as $field) {
        if (isset($columnWidths[$field])) {
            $sheet->getColumnDimensionByColumn($col)->setWidth($columnWidths[$field]);
        } else {
            $sheet->getColumnDimensionByColumn($col)->setWidth(20); // Default width
        }
        $col++;
    }
    
    // Freeze header row for easy scrolling
    $sheet->freezePane('A2');
    
    // Set headers and output
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="CompanyProgress_export.xlsx"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    // Output file
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  
<style>
.heading-container7{
  margin-bottom: 10px;
  
}

  /* Fix container width issues */
.container1 {
    width:100%; /* Use viewport width */
   
    margin-left: 0;
    transition: 0.3s ease;
    overflow-x: auto;
    /* Add horizontal scrolling if needed */
}

/* When nav is open */
body.nav-open .container1 {
    margin-left: 250px;
    width: calc(100vw - 450px); /* Adjust width to account for navbar */
}


.company-table {
    width: 100%;
    min-width: 100%; /* Changed from fixed width to 100% */
    table-layout: auto;
}

/* Remove fixed min-width from table cells */
.company-table td {
    min-width: auto;
    max-width: none;
}

.right-controls7 {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  flex-wrap: wrap;
}  
  table.company-table th:nth-child(1) {
  position: sticky;
  left: 0;
  top: 0;
  z-index: 5; /* highest */
  background: #650000;
  border-right: 1px solid #ccc;
  box-shadow: 2px 0 5px -2px rgba(0,0,0,0.1);
}

table.company-table td:nth-child(1) {
  position: sticky;
  left: 0;
  background: #fff;
  z-index: 4;
  border-right: 1px solid #ccc;
  box-shadow: 2px 0 5px -2px rgba(0,0,0,0.05);
}

/* === Sticky UPID column === */
/* Checkbox assumed ~50px wide */
table.company-table th:nth-child(2) {
  position: sticky;
  left: 50px; /* shift by checkbox width */
  top: 0;
  z-index: 4;
  background: #650000;
  border-right: 1px solid #ccc;
  box-shadow: 2px 0 5px -2px rgba(0,0,0,0.1);
}

table.company-table td:nth-child(2) {
  position: sticky;
  left: 50px;
  background: #fff;
  z-index: 3;
  border-right: 1px solid #ccc;
  box-shadow: 2px 0 5px -2px rgba(0,0,0,0.05);
}

/* === Sticky Reg No column === */
/* Checkbox 50px + UPID 120px = 170px offset */
table.company-table th:nth-child(3) {
  position: sticky;
  left: 170px;
  top: 0;
  z-index: 4;
  background: #650000;
  border-right: 1px solid #ccc;
  box-shadow: 2px 0 5px -2px rgba(0,0,0,0.1);
}

table.company-table td:nth-child(3) {
  position: sticky;
  left: 170px;
  background: #fff;
  z-index: 3;
  border-right: 1px solid #ccc;
  box-shadow: 2px 0 5px -2px rgba(0,0,0,0.05);
}

table.company-table th:nth-child(4) {
  position: sticky;
  left: 170px;
  top: 0;
  z-index: 4;
  background: #650000;
  border-right: 1px solid #ccc;
  box-shadow: 2px 0 5px -2px rgba(0,0,0,0.1);
}

table.company-table td:nth-child(4) {
  position: sticky;
  left: 170px;
  background: #fff;
  z-index: 3;
  border-right: 1px solid #ccc;
  box-shadow: 2px 0 5px -2px rgba(0,0,0,0.05);
}


/* Lock sticky column widths */
table.company-table th:nth-child(1),
table.company-table td:nth-child(1) {
  min-width: 50px;
  max-width: 50px;
}

table.company-table th:nth-child(2),
table.company-table td:nth-child(2) {
  min-width: 120px;
  max-width: 220px;
}

table.company-table th:nth-child(3),
table.company-table td:nth-child(3) {
  min-width: 120px;
  max-width: 220px;
}
table.company-table th:nth-child(4),
table.company-table td:nth-child(4) {
  min-width: 120px;
  max-width: 220px;
}

.table-container {
  overflow-x: auto;
  max-width: 100%;
  position:fixed;
}

/* === Enrolled Table === */
table.company-table {
  width: 100%;
  min-width: 3000px;
  border-collapse: collapse;
  font-size: 12px;
  line-height: 1.2;
  table-layout: auto; /* allow columns to size naturally */
}

/* === Table Headers === */
table.company-table th {
  top: 0;
  z-index: 2;
  background-color: #650000;
  color: white;
  font-size: 13px; /* Heading font size */
  padding-top: 12px;
  padding-bottom: 12px;
  padding-left: 10px !important;
  padding-right: 10px !important;
  vertical-align: middle;
  
}


/* === Table Data Cells === */
table.company-table td {
  border: 1px solid #ccc;
  padding: 6px 8px;
  text-align: left;
  vertical-align: middle;
  overflow: hidden;
  text-overflow: ellipsis;
  min-width: 120px;
  max-width: 250px;
}

/* Ensure stable table layout */

  
  .courses-column {
    cursor: pointer;
    position: fixed;
    width: 200px; /* Fixed width for the column */
  }
  
  .courses-toggle {
    display: inline-flex;
    align-items: center;
    gap: 5px;
  }
  
  .courses-toggle i {
    transition: transform 0.3s ease;
  }
  
  .courses-collapsed .courses-cell {
    max-width: 100%;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    transition: all 0.3s ease;
  }
  
  .courses-collapsed .courses-toggle i {
    transform: rotate(-90deg);
  }
  
  /* Status colors */
  .final-status-dropdown option[value="No selects"] { color: #FFA500; }
  .final-status-dropdown option[value="Process complete"] { color: #28A745; }
  .final-status-dropdown option[value="No applicants"] { color: #DC3545; }
  .final-status-dropdown option[value="Yet to start"] { color: #FFC107; }
  .final-status-dropdown option[value="On hold/Called off"] { color: #6C757D; }
  .final-status-dropdown option[value="Ongoing"] { color: #007BFF; }
  
  .final-status-dropdown {
    color: inherit;
  }
  
  .courses-cell {
    transition: all 0.3s ease;
  }
  
  /* Export fields container styling */
  .export-fields-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
    padding: 10px;
    overflow-y: auto;
    flex-grow: 1;
    margin-bottom: 0px;
  }

  .export-field-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    border-radius: 4px;
    transition: background-color 0.2s;
  }

  .export-field-item:hover {
    background-color: #f8f9fa;
  }

  .export-field-item input[type="checkbox"] {
    margin: 0;
    width: 16px;
    height: 16px;
    cursor: pointer;
    align-self: center;
  }

  .export-field-item label {
    display: flex;
    align-items: center;
    cursor: pointer;
    user-select: none;
    font-size: 1rem;
    color: #495057;
    line-height: 1.2;
  }

  /* Unified checkbox color */
  input[type="checkbox"] {
    accent-color: #650000;
  }

  /* Scrollbar styling */
  .export-fields-container::-webkit-scrollbar {
    width: 8px;
  }

  .export-fields-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
  }

  .export-fields-container::-webkit-scrollbar-thumb {
    background: #ccc;
    border-radius: 4px;
  }

  .export-fields-container::-webkit-scrollbar-thumb:hover {
    background: #aaa;
  }
  
  /* Save button styles */
  .save-btn {
    background-color: #28a745;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.2s;
  }
  
  .save-btn:hover {
    background-color: #218838;
  }
  
  .save-btn:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
  }
  
  .export-confirm {
            padding: 8px 16px;
            border-radius: 4px;
            background-color: #f2f2f2;
            color: #650000;
            border: 1px solid #650000;
            cursor: pointer;
            margin-right:35%;
            transition: all 0.2s;
            width: 0%;
  }
  .export-confirm:hover {
    background-color: #650000;
    color: #f2f2f2;
  }
  
  .save-success {
    color: #28a745;
    font-size: 12px;
    margin-left: 5px;
  }

  /* Ensure Select2 inside filter modal renders above modal content */
  #filterModal .select2-container,
  #filterModal .select2-container .select2-dropdown {
    z-index: 2000 !important;
  }

  /* Properly align Filter modal close (X) button to match registered_students */
  #filterModal .modal-header { position: relative; }
  #filterModal .modal-header .close {
    position: absolute;
    top: 8px;
    right: 12px;
    font-size: 1.5rem;
    line-height: 1;
    cursor: pointer;
    color: #000;
    opacity: 0.5;
  }
  #filterModal .modal-header .close:hover { opacity: 0.75; }
/* Sticky utility */
table.company-table th.sticky-col,
table.company-table td.sticky-col {
  position: sticky;
  background: #fff;
  z-index: 3;
}
table.company-table th.sticky-col { background: #650000; z-index: 5; }

/* Widths for sticky columns */
.col-slno { min-width: 60px; max-width: 60px; }
.col-company { min-width: 220px; max-width: 320px; }
.col-role { min-width: 220px; max-width: 320px; }



/* Reduce width of last column in .company-table */
.company-table td:last-child,
.company-table th:last-child {
  min-width: 100px;  /* or 100px if you want it tighter */
  max-width: 100px;
  position: sticky;
  right: 0;
  
  z-index: 2;
  text-align: center;
}

/* Remove old nth-child sticky rules (replaced by JS offsets) */
</style>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

</head>
<body>
<?php include 'header.php'; ?>
<link rel="stylesheet" href="style.css">


<div class="heading-container7">
    <h3 class="headings">Company Progress Tracker</h3>
    <p>Manage company SPOC info, hiring counts, status and follow-ups for drives.</p>

    <div class="top-bar">
        <div class="left-controls">
            <input id="liveSearch" type="text" class="form-control searchInput" 
                   style="width: 200px;" 
                   placeholder="Search..." 
                   value="<?= htmlspecialchars($filter['search'] ?? '') ?>">


            <button type="button" class="filter-button" onclick="openFilterModal()">
                <i class="fas fa-filter"></i> Filter
            </button>
            <button type="button" id="resetBtn" class="reset-button" onclick="window.location.href = window.location.pathname;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>

        <div class="right-controls7">
            <button type="button" class="exportBtn" onclick="openExportModal()">
                <i class="fas fa-file-export"></i> Export
            </button>
        </div>
  </div>

</div>
<div class="container1">
    <div class="table-responsive">
        <table class="table table-bordered  custom-table company-table">
            <thead>
                <tr>
                    <th class="sticky-col col-slno">Sl No</th>
                    <th class="sticky-col col-company">Company Name</th>
                    <th>Drive No</th>
                    <th class="sticky-col col-role">Role</th>
                    <th>Created By</th>
                    <th>Offer Type</th>
                    <th>Sector</th>
                    <th class="courses-column">
                        <span class="courses-toggle" onclick="toggleCourses()">
                            Courses <i class="fas fa-chevron-down"></i>
                        </span>
                    </th>
                    <th>Form Open Date</th>
                    <th>Role Close Date</th>
                    <th class="w-spoc">SPOC Name</th>
                    <th class="w-spoc-email">SPOC Email ID</th>
                    <th class="w-contact">SPOC Contact Number</th>
                    <th class="w-followup-status">Follow-Up Status</th>
                    <th class="w-final-status">Final Status</th>
                    <th class="w-hired">Hired</th>
                    <th class="w-followup-person">Follow-Up Person</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <?php $i = 1; foreach ($filtered as $row): ?>
                <tr id="row-<?= $row['id'] ?>">
                    <td class="sticky-col col-slno"><?= $i++ ?></td>
                    <td class="sticky-col col-company"><?= htmlspecialchars($row['company_name']) ?></td>
                    <td><?= htmlspecialchars($row['drive_number']) ?></td>
                    <td class="sticky-col col-role"><?= htmlspecialchars($row['role']) ?></td>
                    <td><?= htmlspecialchars($row['created_by'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['offer_type']) ?></td>
                    <td><?= htmlspecialchars($row['sector']) ?></td>
                    <td class="courses-cell">
                        <div class="selected-courses">
                            <?= formatCourseDisplay(is_array($row['eligible_courses']) ? $row['eligible_courses'] : []) ?>
                        </div>
                    </td>
                    <?php
                      // Form Open Date and Role Close Date
                      $openFmt = !empty($row['opening_date']) ? date('d-m-y', strtotime($row['opening_date'])) : '-';
                      $closeFmt = !empty($row['closing_date']) ? date('d-m-y', strtotime($row['closing_date'])) : '-';
                    ?>
                    <td data-open-iso="<?= htmlspecialchars(!empty($row['opening_date']) ? date('Y-m-d', strtotime($row['opening_date'])) : '') ?>"><?= $openFmt ?></td>
                    <td data-close-iso="<?= htmlspecialchars(!empty($row['closing_date']) ? date('Y-m-d', strtotime($row['closing_date'])) : '') ?>"><?= $closeFmt ?></td>
                    <td class="w-spoc"><input type="text" name="spo_name" class="form-control form-control-sm" value="<?= htmlspecialchars($row['spo_name'] ?? '') ?>"></td>
                    <td class="w-spoc-email"><input type="email" name="spoc_email" class="form-control form-control-sm" value="<?= htmlspecialchars($row['spoc_email'] ?? '') ?>"></td>
                    <td class="w-contact"><input type="text" name="contact_no" class="form-control form-control-sm" value="<?= htmlspecialchars($row['contact_no'] ?? '') ?>"></td>
                    <td class="w-followup-status"><input type="text" name="follow_status" class="form-control form-control-sm" value="<?= htmlspecialchars($row['follow_status'] ?? '') ?>"></td>
                    <td class="w-final-status">
                        <select name="final_status" class="form-select form-select-sm final-status-dropdown">
                            <option value="">---Select---</option>
                            <option value="No selects" <?= ($row['final_status'] ?? '') == 'No selects' ? 'selected' : '' ?>>🟠 No selects</option>
                            <option value="Process complete" <?= ($row['final_status'] ?? '') == 'Process complete' ? 'selected' : '' ?>>✅ Process complete</option>
                            <option value="No applicants" <?= ($row['final_status'] ?? '') == 'No applicants' ? 'selected' : '' ?>>❌ No applicants</option>
                            <option value="Yet to start" <?= ($row['final_status'] ?? '') == 'Yet to start' ? 'selected' : '' ?>>🟡 Yet to start</option>
                            <option value="On hold/Called off" <?= ($row['final_status'] ?? '') == 'On hold/Called off' ? 'selected' : '' ?>>⚪ On hold/Called off</option>
                            <option value="Ongoing" <?= ($row['final_status'] ?? '') == 'Ongoing' ? 'selected' : '' ?>>🔵 Ongoing</option>
                        </select>
                    </td>
                    <td class="w-hired"><?= htmlspecialchars($row['hired_count'] ?? '0') ?></td>
                    <td class="w-followup-person">   
                        <input type="text" name="follow_up_person" class="form-control form-control-sm" 
                               value="<?= !empty($row['follow_up_person']) ? htmlspecialchars($row['follow_up_person']) : '' ?>">
                    </td>
                                         <td>
                         <button type="button" onclick="saveRow(<?= $row['id'] ?>)" class="save-btn">Save</button>
                         <span id="save-status-<?= $row['id'] ?>" class="save-success"></span>
                     </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Filter Modal -->
<div id="filterModal" class="modal fade" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Filters</h5>
        <span class="close" onclick="closeFilterModal()" aria-label="Close">&times;</span>
      </div>

      <form id="filterForm" method="GET" class="modal-body">
      <div class="filter-grid">
        <!-- Company Name -->
        <label>
          Company Name
          <input type="text" name="company_name" value="<?= htmlspecialchars($filter['company_name'] ?? '') ?>" placeholder="Company name">
        </label>

        <!-- Offer Type -->
        <label>
          Offer Type
          <select name="offer_type">
            <option value="">All</option>
            <?php foreach ($offerTypes as $o): ?>
              <?php if (trim($o) !== ''): ?>
                <option value="<?= $o ?>" <?= ($filter['offer_type'] ?? '') == $o ? 'selected' : '' ?>><?= $o ?></option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
        </label>

        <!-- Sector -->
        <label>
          Sector
          <select name="sector">
            <option value="">All</option>
            <?php foreach ($sectors as $s): ?>
              <?php if (trim($s) !== ''): ?>
                <option value="<?= $s ?>" <?= ($filter['sector'] ?? '') == $s ? 'selected' : '' ?>><?= $s ?></option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
        </label>

        <!-- Drive No -->
        <label>
          Drive No
          <input type="text" name="drive_number" value="<?= htmlspecialchars($filter['drive_number'] ?? '') ?>">
        </label>

        <!-- Form Open Date -->
        <label>
          Form Open Date
          <input type="text" id="filter_opening_date" name="opening_date" placeholder="dd-mm-yyyy" value="<?= htmlspecialchars(!empty($filter['opening_date']) ? date('d-m-Y', strtotime($filter['opening_date'])) : '') ?>">
        </label>

        <!-- Role Close Date -->
        <label>
          Role Close Date
          <input type="text" id="filter_closing_date" name="closing_date" placeholder="dd-mm-yyyy" value="<?= htmlspecialchars(!empty($filter['closing_date']) ? date('d-m-Y', strtotime($filter['closing_date'])) : '') ?>">
        </label>

        <!-- SPOC Name -->
        <label>
          SPOC Name
          <input type="text" name="spo_name" value="<?= htmlspecialchars($filter['spo_name'] ?? '') ?>">
        </label>

        <!-- Final Status -->
        <label>
          Final Status
          <select name="final_status">
            <option value="">All</option>
            <?php foreach (['No selects','Process complete','No applicants','Yet to start','On hold/Called off','Ongoing'] as $status): ?>
              <option value="<?= $status ?>" <?= ($filter['final_status'] ?? '') == $status ? 'selected' : '' ?>><?= $status ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <!-- Follow-Up Person -->
        <label>
          Follow-Up Person
          <input type="text" name="follow_up_person" value="<?= htmlspecialchars($filter['follow_up_person'] ?? '') ?>">
        </label>

        <!-- Created By -->
        <label>
          Created By
          <input type="text" name="created_by" value="<?= htmlspecialchars($filter['created_by'] ?? '') ?>">
        </label>

        <!-- Course -->
        <label>
          Course
          <select name="course[]" id="course-multiselect" multiple="multiple" style="width: 100%;">
            <?php
              $selectedCourses = isset($filter['course']) ? (array)$filter['course'] : [];
              $coursesOptions = array_values(array_filter(array_unique($allCourses), fn($c) => trim((string)$c) !== ''));
              foreach ($coursesOptions as $c):
                $isSelected = in_array($c, $selectedCourses) ? 'selected' : '';
            ?>
              <option value="<?= htmlspecialchars($c) ?>" <?= $isSelected ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

             <div class="filter-actions">
         <button type="submit">Apply Filters</button>
         <button type="button" class="clear-button" onclick="resetFilterForm()">Clear</button>
       </div>
     </form>
   </div>
 </div>
 </div>

<!-- Export Modal -->
<div id="exportModal" class="modal fade" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Select Fields to Export</h5>
        <span class="close" onclick="closeExportModal()">&times;</span>
      </div>
    <form id="exportForm" method="POST" action="?action=export">
      <!-- Hidden fields for all current filters -->
      <input type="hidden" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
      <input type="hidden" name="company_name" value="<?= htmlspecialchars($_GET['company_name'] ?? '') ?>">
      <input type="hidden" name="offer_type" value="<?= htmlspecialchars($_GET['offer_type'] ?? '') ?>">
      <input type="hidden" name="sector" value="<?= htmlspecialchars($_GET['sector'] ?? '') ?>">
      <input type="hidden" name="drive_number" value="<?= htmlspecialchars($_GET['drive_number'] ?? '') ?>">
      <input type="hidden" name="opening_date" value="<?= htmlspecialchars($_GET['opening_date'] ?? '') ?>">
      <input type="hidden" name="closing_date" value="<?= htmlspecialchars($_GET['closing_date'] ?? '') ?>">
      <input type="hidden" name="spo_name" value="<?= htmlspecialchars($_GET['spo_name'] ?? '') ?>">
      <input type="hidden" name="final_status" value="<?= htmlspecialchars($_GET['final_status'] ?? '') ?>">
      <input type="hidden" name="follow_up_person" value="<?= htmlspecialchars($_GET['follow_up_person'] ?? '') ?>">
      <input type="hidden" name="created_by" value="<?= htmlspecialchars($_GET['created_by'] ?? '') ?>">
      <?php
        if (!empty($_GET['course'])) {
          $exportCourses = (array)$_GET['course'];
          foreach ($exportCourses as $c) {
            if (trim((string)$c) === '') continue;
            echo '<input type="hidden" name="course[]" value="' . htmlspecialchars($c) . '">';
          }
        }
      ?>
      
      <div class="export-fields-container">
        <div class="export-field-item select-all">
          <input type="checkbox" id="select_all" onchange="toggleAllFields(this)">
          <label for="select_all"><strong>Select All Fields</strong></label>
        </div>
        
        <div class="export-field-item">
          <input type="checkbox" id="company_name" name="export_fields[]" value="company_name">
          <label for="company_name">Company Name</label>
        </div>
        
        <div class="export-field-item">
          <input type="checkbox" id="drive_number" name="export_fields[]" value="drive_number">
          <label for="drive_number">Drive Number</label>
        </div>
        
        <div class="export-field-item">
          <input type="checkbox" id="role" name="export_fields[]" value="role">
          <label for="role">Role</label>
        </div>
        
        <div class="export-field-item">
          <input type="checkbox" id="offer_type" name="export_fields[]" value="offer_type">
          <label for="offer_type">Offer Type</label>
        </div>
        
        <div class="export-field-item">
          <input type="checkbox" id="sector" name="export_fields[]" value="sector">
          <label for="sector">Sector</label>
        </div>
        
        <div class="export-field-item">
          <input type="checkbox" id="courses" name="export_fields[]" value="courses">
          <label for="courses">Courses</label>
        </div>
        
        <div class="export-field-item">
          <input type="checkbox" id="opening_date" name="export_fields[]" value="opening_date">
          <label for="opening_date">Form Open Date</label>
        </div>
        
        <div class="export-field-item">
          <input type="checkbox" id="closing_date" name="export_fields[]" value="closing_date">
          <label for="closing_date">Role Close Date</label>
        </div>
        
        <div class="export-field-item">
          <input type="checkbox" id="created_by" name="export_fields[]" value="created_by">
          <label for="created_by">Created By</label>
        </div>
        
        <div class="export-field-item">
          <input type="checkbox" id="spo_name" name="export_fields[]" value="spo_name">
          <label for="spo_name">SPOC Name</label>
        </div>

        <div class="export-field-item">
          <input type="checkbox" id="spoc_email" name="export_fields[]" value="spoc_email">
          <label for="spoc_email">SPOC Email ID</label>
        </div>

        <div class="export-field-item">
          <input type="checkbox" id="contact_no" name="export_fields[]" value="contact_no">
          <label for="contact_no">SPOC Contact Number</label>
        </div>
        
        <div class="export-field-item">
          <input type="checkbox" id="follow_status" name="export_fields[]" value="follow_status">
          <label for="follow_status">Follow-Up Status</label>
        </div>
        
        <div class="export-field-item">
          <input type="checkbox" id="final_status" name="export_fields[]" value="final_status">
          <label for="final_status">Final Status</label>
        </div>
        
        <div class="export-field-item">
          <input type="checkbox" id="hired_count" name="export_fields[]" value="hired_count">
          <label for="hired_count">Hired Count</label>
        </div>
        
        <div class="export-field-item">
          <input type="checkbox" id="follow_up_person" name="export_fields[]" value="follow_up_person">
          <label for="follow_up_person">Follow-Up Person</label>
        </div>
      </div>
      
             <div class="modal-footer">
         <button type="submit" class="export-confirm">
           <i class="fas fa-file-export"></i> Export Selected Fields
         </button>
       </div>
     </form>
   </div>
 </div>
 </div>

<script>
  // Add this to your existing JavaScript
// Add this function to handle navbar toggling


// Toggle courses column visibility
function toggleCourses() {
    const table = document.querySelector('.table');
    table.classList.toggle('courses-collapsed');
    
    const icon = document.querySelector('.courses-toggle i');
    if (table.classList.contains('courses-collapsed')) {
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-right');
    } else {
        icon.classList.remove('fa-chevron-right');
        icon.classList.add('fa-chevron-down');
    }
}

// Filter modal functions
function openFilterModal() {
    const modal = new bootstrap.Modal(document.getElementById('filterModal'));
    modal.show();
}

function closeFilterModal() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('filterModal'));
    if (modal) {
        modal.hide();
    }
}

function resetFilterForm() {
    const form = document.getElementById('filterForm');
    const inputs = form.querySelectorAll('input, select');
    
    inputs.forEach(input => {
        if (input.type === 'text' || input.type === 'date') {
            input.value = '';
        } else if (input.type === 'select-one') {
            input.selectedIndex = 0;
        }
    });
    
    // Also clear the live search input
    document.getElementById('liveSearch').value = '';
    
    // Trigger the input event to refresh the table
    document.getElementById('liveSearch').dispatchEvent(new Event('input'));

    // Clear Select2 multi-select for courses if present
    if (window.$ && $('#course-multiselect').length) {
        $('#course-multiselect').val(null).trigger('change');
    }
}

// Export modal functions
// Modify the openExportModal function
function openExportModal() {
    const modal = new bootstrap.Modal(document.getElementById('exportModal'));
    modal.show();
    
    // Get the live search value
    const searchValue = document.getElementById('liveSearch').value;
    
    // Do not override search from filters; keep export in sync with current URL
    const hiddenSearchInput = document.querySelector('#exportForm input[name="search"]');
    if (hiddenSearchInput) {
        hiddenSearchInput.value = searchValue;
    }
}

function closeExportModal() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('exportModal'));
    if (modal) {
        modal.hide();
    }
}

function toggleAllFields(checkbox) {
    const checkboxes = document.querySelectorAll('#exportForm input[type="checkbox"]:not(#select_all)');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
        // Trigger change event for any dependent logic
        cb.dispatchEvent(new Event('change'));
    });
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Bootstrap handles modal outside clicks and escape key automatically
    // No need for manual event listeners
    if (window.$ && $('#course-multiselect').length) {
        $('#course-multiselect').select2({
            placeholder: 'Select course(s)',
            allowClear: true,
            width: 'resolve',
            dropdownParent: $('#filterModal')
        });
    }
    const filterBtn = document.getElementById('filter-button');
    if (filterBtn) {
        filterBtn.addEventListener('click', openFilterModal);
    }

    // Keep export hidden search in sync at submit time
    const exportForm = document.getElementById('exportForm');
    if (exportForm) {
        exportForm.addEventListener('submit', function(e) {
            const hiddenSearchInput = exportForm.querySelector('input[name="search"]');
            const liveSearch = document.getElementById('liveSearch');
            if (hiddenSearchInput && liveSearch) {
                hiddenSearchInput.value = liveSearch.value;
            }
            const anyChecked = !!exportForm.querySelector('input[name="export_fields[]"]:checked');
            if (!anyChecked) {
                e.preventDefault();
                alert('Please select at least one field to export.');
                return false;
            }
        });
    }
});

// Live search/filter on the main table
document.getElementById('liveSearch').addEventListener('input', function() {
    const val = this.value.toLowerCase();
    document.querySelectorAll('#tableBody tr').forEach(row => {
        // Get all visible text in the row
        let rowText = row.textContent.toLowerCase();

        // Also include values from input/select fields
        row.querySelectorAll('input, select').forEach(el => {
            rowText += ' ' + (el.value || '').toLowerCase();
        });

        row.style.display = rowText.includes(val) ? '' : 'none';
    });
});

// AJAX save function
function saveRow(rowId) {
    const row = document.getElementById('row-' + rowId);
    const formData = new FormData();
    
    // Add all form data
    formData.append('row_id', rowId);
    formData.append('save_single_row', '1');
    formData.append('spo_name', row.querySelector('[name="spo_name"]').value);
    formData.append('contact_no', row.querySelector('[name="contact_no"]').value);
    formData.append('follow_status', row.querySelector('[name="follow_status"]').value);
    formData.append('final_status', row.querySelector('[name="final_status"]').value);
    formData.append('follow_up_person', row.querySelector('[name="follow_up_person"]').value);
    
    const saveBtn = row.querySelector('.save-btn');
    const saveStatus = document.getElementById('save-status-' + rowId);
    
    // Disable button during save
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';
    saveStatus.textContent = '';
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            saveStatus.textContent = '✓ Saved';
            saveStatus.style.color = '#28a745';
            
            // Hide success message after 2 seconds
            setTimeout(() => {
                saveStatus.textContent = '';
            }, 2000);
        } else {
            saveStatus.textContent = 'Error saving';
            saveStatus.style.color = '#dc3545';
        }
    })
    .catch(error => {
        saveStatus.textContent = 'Error saving';
        saveStatus.style.color = '#dc3545';
        console.error('Error:', error);
    })
    .finally(() => {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save';
    });
}
</script>
<script>
// Initialize flatpickr for d-m-Y inputs
document.addEventListener('DOMContentLoaded', function() {
  if (window.flatpickr) {
    flatpickr('#filter_opening_date', { dateFormat: 'd-m-Y', allowInput: true });
    flatpickr('#filter_closing_date', { dateFormat: 'd-m-Y', allowInput: true });
  }
});
</script>
</div>
</body>
</html>