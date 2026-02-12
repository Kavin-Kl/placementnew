<?php
ob_start(); // Start output buffering to allow header redirects
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: index");
    exit;
}

include("config.php");

// === HANDLE CSV IMPORT FIRST (before any output) ===
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["csv_file"])) {
    require 'vendor/autoload.php';

    // Increase limits for large file processing - MUST be before file reading
    set_time_limit(600); // 10 minutes
    ini_set('memory_limit', '2048M'); // 2GB
    ini_set('max_execution_time', '600');

    $file = $_FILES["csv_file"];
    $fileName = $file["name"];
    $tmpPath = $file["tmp_name"];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Check for file upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['import_message'] = "File upload failed with error code: " . $file['error'];
        $_SESSION['import_status'] = "error";
        header("Location: vantage_registered_students.php");
        exit;
    }

    if (preg_match('/(\d{4})-(\d{4})/', $fileName, $matches)) {
        $batchYear = $matches[0];
        $yearOfPassing = (int) $matches[2];
    } else {
        $_SESSION['import_message'] = "Filename must include batch year in format YYYY-YYYY (e.g., students_2023-2026).";
        $_SESSION['import_status'] = "error";
        header("Location: vantage_registered_students.php");
        exit;
    }

    $allowedTypes = ['csv', 'xls', 'xlsx'];
    if (!in_array($fileExt, $allowedTypes)) {
        $_SESSION['import_message'] = "Invalid file type. Only .CSV, .XLS and .XLSX are allowed.";
        $_SESSION['import_status'] = "error";
        header("Location: vantage_registered_students.php");
        exit;
    }

    $dataRows = [];
    $header = [];

    try {
        if ($fileExt === 'csv') {
            if (($handle = fopen($tmpPath, "r")) !== false) {
                $header = fgetcsv($handle);
                while (($row = fgetcsv($handle, 1000, ",")) !== false) {
                    $dataRows[] = $row;
                }
                fclose($handle);
            }
        } else {
            // Load Excel file with error handling - optimized for speed
            try {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmpPath);
                $reader->setReadDataOnly(true); // Skip formatting/styling - much faster!
                $reader->setReadEmptyCells(false); // Skip empty cells

                $spreadsheet = $reader->load($tmpPath);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray(null, false, false, false); // Raw values only
                $header = array_shift($rows);
                $dataRows = $rows;
            } catch (Exception $e) {
                $_SESSION['import_message'] = "Failed to read Excel file: " . $e->getMessage();
                $_SESSION['import_status'] = "error";
                header("Location: vantage_registered_students.php");
                exit;
            }
        }

        // Map headers to internal field names
        $headerPatterns = [
            'upid'          => ['placement id', 'upid', 'placement key id', 'Placement Id', 'Placement ID'],
            'program_type'  => ['program type', 'Program Type'],
            'program'       => ['program', 'Program'],
            'course'        => ['course', 'Course'],
            'reg_no'        => ['Student Register Number', 'Register Number', 'Register number', 'Register No', 'reg no', 'register no', 'regno', 'regno:', 'reg no:', 'register no:'],
            'student_name'  => ['Student Name', 'name', 'student name', 'Student name', 'student'],
            'email'         => ['Student Mail ID', 'Student Email ID', 'Student Email', 'Student email', 'email', 'mail', 'mail id', 'email address', 'email id'],
            'phone_no'      => ['Student Phone No', 'Student Mobile No', 'student phone no', 'student mobile no', 'phone', 'mobile', 'mobile no', 'mobile number', 'phone number', 'phone no.', 'mobile no.'],
            'percentage'    => ['percentage', 'Percentage', 'percent', 'score', 'grade', 'cgpa'],
        ];

        $expectedColumns = array_keys($headerPatterns);
        $headerMap = [];

        foreach ($header as $index => $colName) {
            $normalized = strtolower(trim($colName));
            $normalized = preg_replace('/[^a-z0-9]/', '', $normalized);

            foreach ($headerPatterns as $field => $patterns) {
                foreach ($patterns as $pattern) {
                    $normPattern = preg_replace('/[^a-z0-9]/', '', strtolower($pattern));
                    if ($normalized === $normPattern) {
                        $headerMap[$field] = $index;
                        break 2;
                    }
                }
            }
        }

        // Friendly display names for each expected column
        $columnDisplayNames = [
            'upid'          => 'Placement ID',
            'program_type'  => 'Program Type',
            'program'       => 'Program',
            'course'        => 'Course',
            'reg_no'        => 'Registration Number',
            'student_name'  => 'Student Name',
            'email'         => 'Email',
            'phone_no'      => 'Phone Number',
            'percentage'    => 'Percentage'
        ];

        // Check for missing required columns
        $missingColumns = [];

        foreach ($expectedColumns as $col) {
            if (!isset($headerMap[$col])) {
                $missingColumns[] = $col;
            }
        }

        if (!empty($missingColumns)) {
            // Convert to readable names
            $readableNames = array_map(function($col) use ($columnDisplayNames) {
                return $columnDisplayNames[$col] ?? $col;
            }, $missingColumns);

            $_SESSION['import_message'] = "Missing required column(s): " . implode(', ', $readableNames);
            $_SESSION['import_status'] = "error";
            header("Location: vantage_registered_students.php");
            exit;
        }


        $inserted = 0;
        $skipped = 0;

        // OPTIMIZATION: Get all existing UPIDs - Process in BATCHES to avoid MySQL limit
        $allUpids = array_map(function($row) use ($headerMap) {
            return trim($row[$headerMap['upid']] ?? '');
        }, $dataRows);
        $allUpids = array_filter($allUpids); // Remove empty values

        $existingUpids = [];
        if (!empty($allUpids)) {
            // Process in batches of 500 to avoid MySQL placeholder limit
            $batchSize = 500;
            $batches = array_chunk($allUpids, $batchSize);

            foreach ($batches as $batch) {
                $placeholders = implode(',', array_fill(0, count($batch), '?'));
                $checkStmt = $conn->prepare("SELECT upid FROM students WHERE upid IN ($placeholders)");
                if ($checkStmt) {
                    $types = str_repeat('s', count($batch));
                    $checkStmt->bind_param($types, ...$batch);
                    $checkStmt->execute();
                    $result = $checkStmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $existingUpids[$row['upid']] = true;
                    }
                    $checkStmt->close();
                }
            }
        }

        // Prepare the insert statement ONCE
        $stmt = $conn->prepare("INSERT INTO students
            (upid, program_type, program, course, reg_no, student_name, email, phone_no, batch, year_of_passing, percentage, vantage_participant)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'yes')");

        foreach ($dataRows as $data) {
            $upid          = trim($data[$headerMap['upid']] ?? '');
            $program_type  = trim($data[$headerMap['program_type']] ?? '');
            $program       = trim($data[$headerMap['program']] ?? '');
            $course        = trim($data[$headerMap['course']] ?? '');
            $reg_no        = trim($data[$headerMap['reg_no']] ?? '');
            $student_name  = trim($data[$headerMap['student_name']] ?? '');
            $email         = trim($data[$headerMap['email']] ?? '');
            $phone_no      = trim($data[$headerMap['phone_no']] ?? '');
            $percentage    = isset($headerMap['percentage']) && !empty($data[$headerMap['percentage']]) ? (float)$data[$headerMap['percentage']] : null;

            if (empty($upid) || empty($reg_no) || empty($student_name) || empty($email)) {
                $skipped++;
                continue;
            }

            // Check if UPID already exists (using in-memory array - MUCH faster!)
            if (isset($existingUpids[$upid])) {
                $skipped++;
                continue;
            }

            // Insert the student
            if ($stmt) {
                $stmt->bind_param("sssssssssis",
                    $upid, $program_type, $program, $course,
                    $reg_no, $student_name, $email, $phone_no,
                    $batchYear, $yearOfPassing, $percentage
                );

                if ($stmt->execute()) {
                    $inserted++;
                    $existingUpids[$upid] = true; // Mark as inserted to avoid duplicates in same file
                } else {
                    error_log("Insert failed for UPID $upid: " . $stmt->error);
                    $skipped++;
                }
            }
        }

        if ($stmt) {
            $stmt->close();
        }

        $_SESSION['import_message'] = "Import completed. Inserted: $inserted rows.";
        $_SESSION['import_status'] = "success";

    } catch (Exception $e) {
        $_SESSION['import_message'] = "Error during import: " . $e->getMessage();
        $_SESSION['import_status'] = "error";
    }

    ob_end_clean(); // Clear any output buffer
    header("Location: vantage_registered_students.php");
    exit;
}

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$batches = $conn->query("SELECT DISTINCT batch FROM students ORDER BY batch DESC");
$placedStatuses = $conn->query("SELECT DISTINCT placed_status FROM students ORDER BY placed_status ASC");
$applications = $conn->query("
    SELECT DISTINCT cnt.application_count 
FROM (
    SELECT s.student_id,
        (
            SELECT COUNT(DISTINCT CONCAT(d.drive_no, '_', a.drive_id))
            FROM applications a
            INNER JOIN drives d ON a.drive_id = d.drive_id
            WHERE a.student_id = s.student_id
        ) AS application_count
    FROM students s
) AS cnt
ORDER BY cnt.application_count ASC
");

// ---------- 1. Build Filters ----------

function build_filters(&$types, &$params) {
    $where = [];
    $types = "";
    $params = [];

    // Add academic year filter (from header.php year selector)
    // DISABLED: Show all vantage students regardless of year
    /*
    if (isset($_SESSION['selected_academic_year'])) {
        $parts = explode('-', $_SESSION['selected_academic_year']);
        $graduation_year = isset($parts[1]) ? intval($parts[1]) : null;
        if ($graduation_year) {
            $where[] = "s.year_of_passing = ?";
            $params[] = $graduation_year;
            $types .= "i";
        }
    }
    */

    $fields = [
        "upid", "program_type", "program", "course", "reg_no", "batch", "year_of_passing", "placed_status",
        "company_name", "allow_reapply", "comment", "editable_comment", "Offcampus_selection"
    ];

    $exactMatchFields = [
        "upid", "batch", "placed_status", "program_type",
        "reg_no", "year_of_passing", "allow_reapply", "Offcampus_selection"
    ];

    $tablePrefix = [
        'upid'           => 's',
        'program_type'   => 's',
        'program'        => 's',
        'course'         => 's',
        'reg_no'         => 's',
        'batch'          => 's',
        'year_of_passing'=> 's',
        'placed_status'  => 's',
        'company_name'   => 's',
        'allow_reapply'  => 's',
        'comment'        => 's',
        'Offcampus_selection'  => 's',
        'editable_comment'     => 's',
        'percentage'     => 's'
    ];

    // Standard fields
    foreach ($fields as $field) {
        if ($field === 'course' && !empty($_POST['course']) && is_array($_POST['course'])) {
            $placeholders = [];
            foreach ($_POST['course'] as $course) {
                $params[] = $course;
                $placeholders[] = '?';
                $types .= "s";
            }
            $where[] = "s.course IN (" . implode(",", $placeholders) . ")";
            continue;
        }

        if (!empty($_POST[$field])) {
            $col = $tablePrefix[$field] . '.' . $field;
            if (in_array($field, $exactMatchFields)) {
                $where[] = "$col = ?";
                $params[] = $_POST[$field];
            } else {
                $where[] = "LOWER($col) LIKE ?";
                $params[] = "%" . strtolower($_POST[$field]) . "%";
            }
            $types .= "s";
        }
    }

    // Percentage filter
    if (!empty($_POST['min_percentage'])) {
        $where[] = "s.percentage >= ?";
        $params[] = floatval($_POST['min_percentage']);
        $types .= "d";
    }
    if (!empty($_POST['max_percentage'])) {
        $where[] = "s.percentage <= ?";
        $params[] = floatval($_POST['max_percentage']);
        $types .= "d";
    }

    // Global live search
    if (!empty($_POST['search_query'])) {
        $search = "%" . strtolower($_POST['search_query']) . "%";
        $searchableColumns = [
          "s.upid", "s.program_type", "s.program", "s.course",
          "s.reg_no", "s.batch", "s.year_of_passing", "s.percentage",
          "s.placed_status", "s.company_name", "s.allow_reapply",
          "s.comment", "s.email", "s.student_name", "s.phone_no",
          "s.Offcampus_selection", "s.editable_comment"
        ];

        $searchConditions = array_map(fn($col) => "LOWER($col) LIKE ?", $searchableColumns);
        $where[] = "(" . implode(" OR ", $searchConditions) . ")";

        foreach ($searchableColumns as $_) {
            $params[] = $search;
            $types .= "s";
        }
    }

    // ---------- Applications filter ----------
    if (isset($_POST['applications_count']) && $_POST['applications_count'] !== '') {
        $applications_count = $_POST['applications_count'];

        if ($applications_count === 'above_0') {
            $where[] = "(SELECT COUNT(DISTINCT CONCAT(d.drive_no,'_',a.drive_id))
                        FROM applications a
                        INNER JOIN drives d ON a.drive_id = d.drive_id
                        WHERE a.student_id = s.student_id) > 0";
        } elseif (is_numeric($applications_count)) {
            $where[] = "(SELECT COUNT(DISTINCT CONCAT(d.drive_no,'_',a.drive_id))
                        FROM applications a
                        INNER JOIN drives d ON a.drive_id = d.drive_id
                        WHERE a.student_id = s.student_id) = ?";
            $params[] = (int)$applications_count; // cast to integer
            $types .= "i";
        }
    }

    // ---------- Offer Type filter (special handling for computed field) ----------
    if (!empty($_POST['offer_type'])) {
        $where[] = "dd.offer_type = ?";
        $params[] = $_POST['offer_type'];
        $types .= "s";
    }

    return $where;
}

// ---------- 2. Fetch Students ----------
function fetch_students($conn, $where, $types, $params, $limit = 50, $offset = 0) {
    $sql = "
      WITH ranked_applications AS (
          SELECT 
              a.*,
              ROW_NUMBER() OVER (
                  PARTITION BY a.student_id
                  ORDER BY 
                      CASE a.status
                          WHEN 'placed' THEN 1
                          WHEN 'blocked' THEN 2
                          WHEN 'original' THEN 3
                          ELSE 4
                      END,
                      a.status_changed DESC
              ) AS rn
          FROM applications a
          WHERE a.placement_batch IN ('original', 'reapplied')
      )
      SELECT
          s.*,

          -- Count of distinct applications
          (
              SELECT COUNT(DISTINCT CONCAT(d.drive_no, '_', a.drive_id))
              FROM applications a
              INNER JOIN drives d ON a.drive_id = d.drive_id
              WHERE a.student_id = s.student_id
          ) AS application_count,

          -- Final status
          CASE
              WHEN ra.status = 'blocked' THEN 'blocked'
              WHEN ra.status = 'placed'  THEN 'placed'
              ELSE 'not_placed'
          END AS final_status,

          -- Comments only for placed/blocked
          CASE 
              WHEN ra.status IN ('placed','blocked') THEN ra.comments
              ELSE NULL
          END AS comment,

          -- Company info only for placed/blocked
          CASE 
              WHEN ra.status IN ('placed','blocked') THEN d.company_name
              ELSE NULL
          END AS company_name,

          -- Role info only for placed/blocked
          CASE 
              WHEN ra.status IN ('placed','blocked') THEN dr.designation_name
              ELSE NULL
          END AS role_name,

          CASE 
              WHEN ra.status IN ('placed','blocked') THEN dr.ctc
              ELSE NULL
          END AS ctc,

          CASE 
              WHEN ra.status IN ('placed','blocked') THEN dr.stipend
              ELSE NULL
          END AS stipend,

          -- Offer Type only for placed/blocked
          CASE
              WHEN ra.status IN ('placed','blocked') THEN dd.offer_type
              ELSE NULL
          END AS offer_type

      FROM students s
      LEFT JOIN ranked_applications ra 
            ON s.student_id = ra.student_id AND ra.rn = 1
      LEFT JOIN drives d 
            ON ra.drive_id = d.drive_id
      LEFT JOIN drive_roles dr 
            ON ra.role_id = dr.role_id
      LEFT JOIN drive_data dd
            ON ra.drive_id = dd.drive_id AND ra.role_id = dd.role_id

      -- Dynamic WHERE clause if filters exist
      WHERE 1=1
        AND s.vantage_participant = 'yes'
    ";

    if (!empty($where)) {
        $sql .= " AND " . implode(" AND ", $where);
    }

    $sql .= "
        ORDER BY 
    s.batch DESC, 
    CASE s.program_type 
        WHEN 'UG' THEN 1 
        WHEN 'PG' THEN 2 
        ELSE 3 
    END,
    s.upid ASC
        LIMIT ? OFFSET ?  
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Error preparing statement: " . $conn->error);
    }

    if (!empty($params)) {
        $types .= "ii";
        $params[] = $limit;
        $params[] = $offset;
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt->bind_param("ii", $limit, $offset);
    }

    $stmt->execute();
    return $stmt->get_result();
}

// ---------- 3. Render Students Table Rows ----------
function render_students_table($conn, $result, $offset = 0) {
    $sl_no = $offset + 1; 

    while ($row = $result->fetch_assoc()) {
        $finalStatus = $row['final_status'];

        // Update student table
        $update = $conn->prepare("
          UPDATE students SET
              placed_status = ?,
              comment = ?,
              company_name = ?,
              role = ?,
              ctc = ?,
              offer_type = ?
          WHERE upid = ?
        ");

        if ($update) {
            $update->bind_param(
              "sssssss",
              $finalStatus,
              $row['comment'],
              $row['company_name'],
              $row['role_name'],
              $row['ctc'],
              $row['offer_type'],
              $row['upid']
            );
            $update->execute();
            $update->close();
        } else {
            // Log error for debugging
            error_log("Failed to prepare UPDATE statement: " . $conn->error);
        }

        // Render row
        echo '<tr data-student-id="' . htmlspecialchars($row['student_id']) . '">';
        echo '<td><input type="checkbox" class="student-checkbox" value="' . htmlspecialchars($row['student_id']) . '"></td>';
        echo '<td>' . $sl_no++ . '</td>';

        // UPID
        echo '<td class="sticky-col sticky-01">
                <span class="field-view">' . htmlspecialchars($row['upid']) . '</span>
                <input type="text" class="field-edit form-control d-none" name="upid" value="' . htmlspecialchars($row['upid']) . '">
              </td>';

        // Registration Number
        echo '<td class="sticky-col sticky-02">
                <span class="field-view">' . htmlspecialchars($row['reg_no']) . '</span>
                <input type="text" class="field-edit form-control d-none" name="reg_no" value="' . htmlspecialchars($row['reg_no']) . '">
              </td>';

        // Student Name
        echo '<td>
                <span class="field-view">' . htmlspecialchars($row['student_name']) . '</span>
                <input type="text" class="field-edit form-control d-none" name="student_name" value="' . htmlspecialchars($row['student_name']) . '">
              </td>';

        // Program Type
        echo '<td>
                <span class="field-view">' . htmlspecialchars($row['program_type']) . '</span>
                <input type="text" class="field-edit form-control d-none" name="program_type" value="' . htmlspecialchars($row['program_type']) . '">
              </td>';

        // Program
        echo '<td>
                <span class="field-view">' . htmlspecialchars($row['program']) . '</span>
                <input type="text" class="field-edit form-control d-none" name="program" value="' . htmlspecialchars($row['program']) . '">
              </td>';

        // Course
        echo '<td>
                <span class="field-view">' . htmlspecialchars($row['course']) . '</span>
                <input type="text" class="field-edit form-control d-none" name="course" value="' . htmlspecialchars($row['course']) . '">
              </td>';

        // Email
        echo '<td>
                <span class="field-view">' . htmlspecialchars($row['email']) . '</span>
                <input type="email" class="field-edit form-control d-none" name="email" value="' . htmlspecialchars($row['email']) . '">
              </td>';

        // Phone Number
        echo '<td>
                <span class="field-view">' . htmlspecialchars($row['phone_no']) . '</span>
                <input type="tel" pattern="[0-9]{10}" class="field-edit form-control d-none" name="phone_no" value="' . htmlspecialchars($row['phone_no']) . '">
              </td>';

        // Batch
        echo '<td>
                <span class="field-view">' . htmlspecialchars($row['batch']) . '</span>
                <input type="text" class="field-edit form-control d-none" name="batch" value="' . htmlspecialchars($row['batch']) . '" placeholder="Eg:2023-2026">
              </td>';

        // Year of passing
        echo '<td>
                <span class="field-view year-of-passing">' . htmlspecialchars($row['year_of_passing']) . '</span>
              </td>';

        // percentage
        echo '<td>
                <span class="field-view">' . htmlspecialchars($row['percentage']) . '</span>
                <input type="text" class="field-edit form-control d-none" name="percentage" value="' . htmlspecialchars($row['percentage']) . '" placeholder="Eg:89.5">
              </td>';

        
        // Final Status
        echo '<td>' . htmlspecialchars($finalStatus) . '</td>';

        // Application Count
        echo '<td>' . htmlspecialchars($row['application_count']) . '</td>';

        // Allow Reapply Dropdown
        echo '<td>
                <select id="allow-reapply-' . htmlspecialchars($row['upid']) . '" class="allow-reapply-select">
                    <option value="yes"' . ($row['allow_reapply'] === 'yes' ? ' selected' : '') . '>Yes</option>
                    <option value="no"' . ($row['allow_reapply'] === 'no' ? ' selected' : '') . '>No</option>
                </select>
              </td>';
        
        // Offer Type
        echo '<td>' . htmlspecialchars($row['offer_type']) . '</td>';
        //echo '<td>
                //<span class="field-view">' . htmlspecialchars($row['offer_type']) . '</span>
                //<input type="text" class="field-edit form-control d-none" name="offer_type" value="' . htmlspecialchars($row['offer_type']) . '">
              //</td>';

        // Company + Comment
        echo '<td>' . htmlspecialchars($row['company_name']) . '</td>';
        echo '<td>' . htmlspecialchars($row['comment']) . '</td>';

        // Offcampus Selection Dropdown
        echo '<td>
          <label>
            <select name="Offcampus_selection" class="offcampus-select">
              <option value="unknown"' . ((empty($row['Offcampus_selection'] ?? '') || ($row['Offcampus_selection'] ?? '') === 'unknown') ? ' selected' : '') . '>Select Option</option>
              <option value="not_placed"' . (($row['Offcampus_selection'] ?? '') === 'not_placed' ? ' selected' : '') . '>Not Placed</option>
              <option value="placed"' . (($row['Offcampus_selection'] ?? '') === 'placed' ? ' selected' : '') . '>Placed</option>
            </select>
          </label>
        </td>';

        // offcampus Editable Comment (renamed column)
        $editableComment = $row['editable_comment'] ?? '';
        echo '<td>
                <span class="field-view">' . htmlspecialchars($editableComment) . '</span>
                <input type="text" class="field-edit form-control d-none" name="editable_comment" value="' . htmlspecialchars($editableComment) . '" placeholder="Add comment...">
              </td>';
  
        // Action buttons
        echo '<td>
                <button type="button" title="Save Student Record" class="save-btn btn btn-sm" data-student-id="' . htmlspecialchars($row['student_id']) . '" style="margin-right:3px; background-color:white; border:1px solid #198754; font-weight: 700; color:#198754;">Save</button>
                <button type="button" title="Edit Student Record" class="edit-btn btn btn-sm" data-student-id="' . htmlspecialchars($row['student_id']) . '" style="margin-right:3px; background-color:white; border:1px solid #650000; color:#650000;"><i class="fas fa-edit"></i></button>
                <button type="button" title="Delete Student Record" class="delete-student-btn btn btn-sm" data-student-id="' . htmlspecialchars($row['student_id']) . '" style="background-color:white; border:1px solid #dc3545; color:#dc3545;"><i class="fas fa-trash"></i></button>
              </td>';

        echo '</tr>';
    }
}

// ---------- 4. Handle AJAX Request for the lazy and search ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_filter'])) {
    $types = '';
    $params = [];
    $where = build_filters($types, $params);

    $limit  = isset($_POST['limit']) ? (int)$_POST['limit'] : 50;
    $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;

    $result = fetch_students($conn, $where, $types, $params, $limit, $offset);
    render_students_table($conn, $result, $offset);
    exit;
}

// Display message if available
$messageHtml = '';
if (!empty($_SESSION['import_message'])) {
    $type = $_SESSION['import_status'] ?? 'success';

    switch ($type) {
        case 'error':
            $class = 'msg-error';
            break;
        case 'warning':
            $class = 'msg-warning';
            break;
        case 'info':
            $class = 'msg-info';
            break;
        default: // success
            $class = 'msg-success';
    }

    $messageHtml = "<div class='$class'>" . htmlspecialchars($_SESSION['import_message']) . "</div>";

    unset($_SESSION['import_message']);
    unset($_SESSION['import_status']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_all'])) {
    // Disable any output
    error_reporting(0);
    ob_end_clean(); // clear any previous output buffer

    require 'vendor/autoload.php';

    // Build filters
    $types = '';
    $params = [];
    $where = build_filters($types, $params);

    // Fetch all matching students (no limit)
    $result = fetch_students($conn, $where, $types, $params, 100000, 0);

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Map table headers to database columns
    $allColumns = [
        'Placement ID' => 'upid',
        'Register Number' => 'reg_no',
        'Student Name' => 'student_name',
        'Program Type' => 'program_type',
        'Program' => 'program',
        'Course' => 'course',
        'Student Mail ID' => 'email',
        'Student Mobile No' => 'phone_no',
        'Batch' => 'batch',
        'Year of Passing' => 'year_of_passing',
        'Percentage' => 'percentage',
        'Off Campus Placement' => 'Offcampus_selection',
        'Off Campus Placement Comments' => 'editable_comment',
        'On Campus Placement Status' => 'final_status',
        'No. of Applications' => 'application_count',
        'Allow Reapply' => 'allow_reapply',
        'Job Offer Type' => 'offer_type',
        'Company' => 'company_name',
        'Comments' => 'comment'
    ];

    // Get selected columns from POST
    $selectedCols = isset($_POST['columns']) ? $_POST['columns'] : array_keys($allColumns);
    array_unshift($selectedCols, 'Sl No'); // always include Sl No

    // Set header row
    $sheet->fromArray($selectedCols, NULL, 'A1');

    // Fill data
    $rowIndex = 2;
    $slNo = 1;
    while ($row = $result->fetch_assoc()) {
        $rowData = [];
        foreach ($selectedCols as $col) {
            if ($col === 'Sl No') {
                $rowData[] = $slNo++;
            } else {
                $dbCol = $allColumns[$col];
                $rowData[] = $row[$dbCol] ?? '';
            }
        }
        $sheet->fromArray($rowData, NULL, "A$rowIndex");
        $rowIndex++;
    }

    // Send proper headers before output
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="vantage_registered_students.xlsx"');
    header('Cache-Control: max-age=0');
    header('Expires: 0');
    header('Pragma: public');

    // Write to output
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;
}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_student') {
    $student_id = trim($_POST['student_id']); // use student_id now

    // Fields allowed to be updated
    $fieldsToUpdate = [
        'upid','program_type', 'program', 'course', 'percentage', 'reg_no', 'student_name',
        'email', 'phone_no', 'allow_reapply', 'batch', 'editable_comment', 'Offcampus_selection'
    ];

    $setParts = [];
    $params   = [];
    $types    = "";

    foreach ($fieldsToUpdate as $field) {
        if (isset($_POST[$field])) {
            $value = trim($_POST[$field]);

            // reject empty values (except allow_reapply which can be "yes"/"no")
            if ($value === "" && !in_array($field, ['allow_reapply', 'Offcampus_selection', 'editable_comment'])) {
                echo ucfirst(str_replace("_", " ", $field)) . " cannot be empty.";
                exit;
            }

            // === Special validation for batch ===
            if ($field === 'batch') {
                if (!preg_match('/^\d{4}-\d{4}$/', $value)) {
                    echo "Batch must be in format YYYY-YYYY (e.g., 2023-2026)."; 
                    exit;
                }

                // Extract last year for year_of_passing
                $years = explode("-", $value);
                $year_of_passing = $years[1];

                // Add both batch and year_of_passing to query
                $setParts[] = "batch = ?";
                $params[]   = $value;
                $types     .= "s";

                $setParts[] = "year_of_passing = ?";
                $params[]   = $year_of_passing;
                $types     .= "s";

                continue; // skip normal flow, already handled
            }

            $setParts[] = "$field = ?";
            $params[]   = $value;
            $types     .= "s";
        }
    }

    if (!empty($setParts)) {
        $sql = "UPDATE students SET " . implode(", ", $setParts) . " WHERE student_id = ?"; 
        $params[] = $student_id;
        $types   .= "s";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            echo "Error preparing statement: " . $conn->error;
            exit;
        }

        $stmt->bind_param($types, ...$params);

        try {
            if ($stmt->execute()) {
                echo "Student updated successfully.";
            } else {
                echo "Database update failed. Please try again.";
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) { // Duplicate entry
                echo "Duplicate entries are not allowed in the Register No and Placement ID.";
            } else {
                echo "Database error: " . $e->getMessage();
            }
        }

        $stmt->close();
    } else {
        echo "No fields to update.";
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="stylesheet" href="style.css">
  <style>
    .msg-error {
        background-color: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }
    .msg-warning {
        background-color: #fef9c3;
        color: #854d0e;
        border: 1px solid #fde68a;
    }
    .msg-info {
        background-color: #dbeafe;
        color: #1e40af;
        border: 1px solid #93c5fd;
    }
    .msg-success {
        background-color: #dcfce7;
        color: #166534;
        border: 1px solid #86efac;
    }
    .msg-error, .msg-warning, .msg-info, .msg-success {
        margin-bottom: 1rem;
        border-radius: 0.25rem;
        text-align: center;
    }


#toast.msg-info {
  --bs-toast-bg: #dbeafe;
  --bs-toast-color: #1e40af;
  --bs-toast-border-color: #93c5fd;
}

#toast.msg-success {
  --bs-toast-bg: #dcfce7;
  --bs-toast-color: #166534;
  --bs-toast-border-color: #86efac;
}

#toast.msg-warning {
  --bs-toast-bg: #fef9c3;
  --bs-toast-color: #854d0e;
  --bs-toast-border-color: #fde68a;
}

#toast.msg-error {
  --bs-toast-bg: #fee2e2;
  --bs-toast-color: #991b1b;
  --bs-toast-border-color: #fca5a5;
}

</style>

</head>
<body>

  <?php include 'header.php'; ?>
    <div class="heading-container">
      <h3 class="headings">Vantage Registered Students</h3>
      <p>View the complete list of registered students with placement information.</p>
    </div>
    <div class="top-bar">
      <div class="left-controls">
        <div class="search-filter-btn-container" style="position: relative;">
          <input type="text" id="searchInput" placeholder="Search... (name, reg no, etc.)">
          <button type="button" id="filter-button" class="filter-button">
            <i class="fas fa-filter"></i> Filters
          </button>
          <a href="vantage_registered_students" class="reset-button">
            <i class="fas fa-rotate-left"></i> Reset
          </a>

          <!-- Filter Modal -->
          <div id="filterModal" class="filter-modal">
            <div class="modal-content">
              <span class="close">&times;</span>
              <h5>Filters</h5>
              <form id="filterForm" onsubmit="applyFilters(); return false;">
                <div class="filter-grid">
                  <label>Placement ID: <input type="text" name="upid"></label>
                  <label>Register No: <input type="text" name="reg_no"></label>
                  <label>Program: <input type="text" name="program"></label>
                  <label>Program Type:
                    <select name="program_type">
                      <option value="">All</option>
                      <option value="UG">UG</option>
                      <option value="PG">PG</option>
                    </select>
                  </label>
                  <label>Course:
                    <select name="course[]" id="course-multiselect" multiple="multiple" style="width: 100%;">
                      <?php
                      $courses = $conn->query("SELECT DISTINCT course FROM students WHERE course IS NOT NULL AND course != '' ORDER BY course ASC");
                      while ($row = $courses->fetch_assoc()):
                      ?>
                        <option value="<?= htmlspecialchars($row['course']) ?>">
                          <?= htmlspecialchars($row['course']) ?>
                        </option>
                      <?php endwhile; ?>
                    </select>
                  </label>
                  <label>Year of Passing:
                    <input type="number" name="year_of_passing" placeholder="e.g., 2025" min="2000" max="2099">
                  </label>
                  <label>
                    Percentage:
                    <div>
                      <input type="number" name="min_percentage" placeholder="Min %" step="0.01"
                            style="width: 100%; max-width: 100px; padding: 6px; border: 1px solid #444; border-radius: 3px; text-align: center;">
                      <span style="font-weight: bold;">-</span>
                      <input type="number" name="max_percentage" placeholder="Max %" step="0.01"
                            style="width: 100%; max-width: 100px; padding: 6px; border: 1px solid #444; border-radius: 3px; text-align: center;">
                    </div>
                  </label>
                  <label>Batch:
                    <select name="batch">
                      <option value="">All Batches</option>
                      <?php $batches->data_seek(0); while ($b = $batches->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($b['batch']) ?>"><?= htmlspecialchars($b['batch']) ?></option>
                      <?php endwhile; ?>
                    </select>
                  </label>
                  <label>Applications:
                      <select name="applications_count">
                          <option value="">All Applications</option>
                          <option value="above_0" <?= (isset($_POST['applications_count']) && $_POST['applications_count'] === 'above_0') ? 'selected' : '' ?>>Above 0</option>
                          <?php
                          $applications->data_seek(0); // Reset pointer
                          while ($a = $applications->fetch_assoc()):
                              $count = (string)$a['application_count']; // cast to string
                          ?>
                          <option value="<?= htmlspecialchars($count) ?>"
                              <?= (isset($_POST['applications_count']) && $_POST['applications_count'] === $count) ? 'selected' : '' ?>>
                              <?= htmlspecialchars($count) ?>
                          </option>
                          <?php endwhile; ?>
                      </select>
                  </label>
                  <label>Allow Reapply:
                    <select name="allow_reapply">
                      <option value="">All</option>
                      <option value="yes">Yes</option>
                      <option value="no">No</option>
                    </select>
                  </label>
                  <label>On Campus Placed Status:
                    <select name="placed_status">
                      <option value="">All Statuses</option>
                      <?php $placedStatuses->data_seek(0); while ($p = $placedStatuses->fetch_assoc()): ?>
                        <option value="<?= $p['placed_status'] ?>"><?= $p['placed_status'] ?></option>
                      <?php endwhile; ?>
                    </select>
                  </label>
                  <label>On Campus Comments: <input type="text" name="comment"></label>
                  <label>Offer Type:
                    <select name="offer_type">
                      <option value="">All options</option>
                      <option value="FTE" <?= (isset($_GET['offer_type']) && $_GET['offer_type'] === 'FTE') ? 'selected' : '' ?>>FTE</option>
                      <option value="Internship" <?= (isset($_GET['offer_type']) && $_GET['offer_type'] === 'Internship') ? 'selected' : '' ?>>Internship</option>
                      <option value="Apprentice" <?= (isset($_GET['offer_type']) && $_GET['offer_type'] === 'Apprentice') ? 'selected' : '' ?>>Apprentice</option>
                      <option value="Internship + PPO" <?= (isset($_GET['offer_type']) && $_GET['offer_type'] === 'Internship + PPO') ? 'selected' : '' ?>>Internship + PPO</option>
                    </select>
                  </label>
                  <label>Company:
                    <select name="company_name" id="company_name" size="5" style="width: 100%;">
                      <option></option> <!-- placeholder -->
                      <?php
                      $companies = $conn->query("SELECT DISTINCT company_name FROM students WHERE company_name IS NOT NULL AND company_name != '' ORDER BY company_name ASC") or die("SQL Error: " . $conn->error);
                      while ($c = $companies->fetch_assoc()):
                          $val = htmlspecialchars($c['company_name']);
                          $selected = (isset($_GET['company_name']) && $_GET['company_name'] === $val) ? 'selected' : '';
                          echo "<option value=\"$val\" $selected>$val</option>";
                      endwhile;
                      ?>
                    </select>
                  </label>
                  <label>Off Campus Placed Status:
                    <select name="Offcampus_selection">
                      <option value="">All</option>
                      <option value="not_placed">Not placed</option>
                      <option value="placed">Placed</option>
                    </select>
                  </label>
                  <label>Off Campus Comments: <input type="text" name="editable_comment"></label>
                </div>
                <div class="filter-actions">
                  <button type="submit">Apply Filter</button>
                  <button type="button" class="clear-button" onclick="clearFilters()">Clear Filter</button>
                </div>
              </form>
            </div>
          </div>
          <!-- /Filter Modal -->

        </div>
      </div>

      <div class="right-controls">
        <div class="export-import-container">
          <button type="button" id="openImportPopup" class="import-button">
            <i class="fa fa-download" aria-hidden="true" style="margin-right: 5px;"></i> Import File
          </button>
          <!-- Export Button -->
          <button type="button" id="exportBtn">
            <i class="fas fa-file-export"></i> Export File
          </button>
        </div>
      </div>
    </div>
    <!-- /top-bar -->

    <div id="ipt_importPopup" class="ipt_modal">
  <div class="ipt_modal-content">
    <span class="ipt_close-btn">&times;</span>
    <h5>Select Import Option</h5>

    <form method="POST" enctype="multipart/form-data" class="import-form" onsubmit="return validateFilename()">
      <label for="csv_file" class="ipt_import-option">
        <i class="fa fa-download"></i> Import Excel File
      </label>
      <input type="file" id="csv_file" name="csv_file" accept=".csv,.xls,.xlsx" required style="display:none;" onchange="validateAndSubmit()">
    </form>

    <a href="percentage_upload.php" class="ipt_import-option">
      <i class="fa fa-file-upload"></i> Update Percentage
    </a>
  </div>
</div>


    <!-- Export Modal -->
    <div class="export-modal" id="exportModal">
      <div class="export-box export-content">
        <span class="close" onclick="closeExportModal()">&times;</span>
        <h5>Select Columns to Export</h5>
        
        <form id="exportForm">
          <div id="exportGrid" class="export-grid">
            <label><input type="checkbox"> Placement ID</label>
            <label><input type="checkbox"> Program Type</label>
            <label><input type="checkbox"> Program</label>
            <label><input type="checkbox"> Course</label>
            <label><input type="checkbox"> Register No</label>
            <label><input type="checkbox"> Student Name</label>
            <label><input type="checkbox"> Student Mail ID</label>
            <label><input type="checkbox"> Mobile No</label>
            <label><input type="checkbox"> Batch</label>
            <label><input type="checkbox"> Year of Passing</label>
            <label><input type="checkbox"> Percentage</label>
            <label><input type="checkbox"> Off Campus Placement Status</label>
            <label><input type="checkbox"> Off Campus Placement Comments</label>
            <label><input type="checkbox"> On Campus Placement Status</label>
            <label><input type="checkbox"> Application Count</label>
            <label><input type="checkbox"> Allow Reapply</label>
            <label><input type="checkbox"> Job Offer Type</label>
            <label><input type="checkbox"> Company</label>
            <label><input type="checkbox"> Comments</label>
          </div>

          <div class="export-actions">
            <button type="submit" class="download-button" onclick="exportTable()">Export selected fields</button>
          </div>
        </form>
      </div>
    </div>

    <!-- /Export Modal -->

  <div id="toast" class="toast"></div>

  <?php if (!empty($messageHtml)): ?>
    <div id="import-message-container">
      <?= $messageHtml ?>
    </div>
  <?php endif; ?>

<div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
  <!-- Bulk Action Controls -->
  <div style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
    <button type="button" id="bulkDeleteBtn" class="btn btn-danger btn-sm" style="display: none;">
      <i class="bi bi-trash"></i> Delete Selected (<span id="selectedCount">0</span>)
    </button>
    <button type="button" id="exportSelectedBtn" class="btn btn-success btn-sm" style="display: none;">
      <i class="bi bi-download"></i> Export Selected
    </button>
  </div>

  <table class="table table-bordered table-striped custom-table sticky-table" id="studentsTable">
    <thead>
      <tr>
        <th><input type="checkbox" id="selectAllCheckbox" title="Select All"></th>
        <th>Sl No</th><th class="sticky-col sticky-01">Placement ID</th><th class="sticky-col sticky-02">Register Number</th><th>Student Name</th><th>Program Type</th><th>Program</th><th>Course</th>
        <th>Student Mail ID</th><th>Student Mobile No</th><th>Batch</th><th>Year of passing</th><th>Percentage</th>
        <th>On-Campus Placement Status</th><th>No. of Applications</th><th>Allow Reapply</th><th>Job Offer Type</th><th>Company</th><th>Comments</th><th>Off-Campus Placement Status</th><th>Off-Campus Placement Comments</th><th></th>

      </tr>
    </thead>
    <tbody id="tableBody">
      <!-- Loaded by AJAX -->
    </tbody>
    <div id="loading" style="text-align:center; display:none;">Loading...</div>
  </table>
</div>


<script>
document.addEventListener("DOMContentLoaded", () => {
  const openPopup = document.getElementById("openImportPopup");
  const popup = document.getElementById("ipt_importPopup");
  const closePopup = document.querySelector(".ipt_close-btn");

  // Open modal
  openPopup.addEventListener("click", (e) => {
    e.stopPropagation();
    popup.style.display = "flex"; // use flex for centering
  });

  // Close modal when clicking outside
  window.addEventListener("click", (e) => {
    if (e.target === popup) {
      popup.style.display = "none";
    }
  });

  // Close modal on X click
  closePopup.addEventListener("click", () => {
    popup.style.display = "none";
  });
});


// Toast notification function
function showToast(message, type = "info", duration = 3000) {
  const toast = document.getElementById("toast");
  toast.className = `toast msg-${type}`;
  toast.innerHTML = message.replace(/\n/g, "<br>");
  toast.classList.add("show");
  setTimeout(() => {
    toast.classList.remove("show");
  }, duration);
}

document.addEventListener("DOMContentLoaded", () => {
  const filterBtn = document.getElementById("filter-button");
  const filterModal = document.getElementById("filterModal");
  const closeBtn = document.querySelector(".modal-content .close");

  // Toggle modal visibility
  filterBtn.addEventListener("click", function (e) {
    e.stopPropagation();
    filterModal.style.display = "flex";
  });

  // Close modal when clicking outside
  window.addEventListener("click", function (e) {
    if (e.target === filterModal) {
      filterModal.style.display = "none";
    }
  });

  // Close modal on X click
  closeBtn.addEventListener("click", () => {
    filterModal.style.display = "none";
  });

  // Auto-load data on page load
  applyFilters();
});

// Apply filters and reload table data
function applyFilters(searchQuery = "") {
  const form = document.getElementById("filterForm");
  const formData = new FormData(form);
  formData.append("ajax_filter", "1");

  // Include search term if present
  if (searchQuery.trim() !== "") {
    formData.append("search_query", searchQuery.trim());
  }

  fetch("vantage_registered_students", {
    method: "POST",
    body: formData
  })
    .then((res) => res.text())
    .then((data) => {
      document.getElementById("tableBody").innerHTML = data;
      document.getElementById("filterModal").style.display = "none";
    })
    .catch((err) => console.error("Fetch error:", err));
}


// Close modal only (no reset)
function cancelFilters() {
  const modal = document.getElementById("filterModal");
  modal.style.display = "none";
}

// Clear filters and reload all data
function clearFilters() {
  const form = document.getElementById("filterForm");
  form.reset();

  // Clear Select2 dropdowns individually
  if (window.$) {
    if ($("#course-multiselect").length) {
      $("#course-multiselect").val(null).trigger("change");
    }
    if ($("#company_name").length) {
      $("#company_name").val(null).trigger("change");
    }
  }

  // Reset course dropdown to show all courses again
  if (typeof populateCourses === 'function' && window.allCourses) {
    populateCourses(window.allCourses);
  }
}

// Live search listener
let searchTimeout = null;
document.getElementById("searchInput").addEventListener("input", function () {
  clearTimeout(searchTimeout);
  const query = this.value.trim();

  searchTimeout = setTimeout(() => {
    applyFilters(query); // Now actually sends search term
  }, 200);
});


// exportTable function to export selected columns
document.addEventListener("DOMContentLoaded", () => {
  const exportBtn = document.getElementById("exportBtn");
  const exportModal = document.getElementById("exportModal");
  const closeBtn = exportModal.querySelector(".export-content .close");
  const grid = document.getElementById("exportGrid"); // grid container
  const table = document.getElementById("studentsTable");

  // Open export modal
  exportBtn.addEventListener("click", (e) => {
    e.stopPropagation();
    grid.innerHTML = ""; // Clear only the checkbox grid

    // Create "Select All" checkbox
    const selectAllLabel = document.createElement("label");
    const selectAllCheckbox = document.createElement("input");
    selectAllCheckbox.type = "checkbox";
    selectAllCheckbox.id = "selectAllCols";
    selectAllCheckbox.checked = false; // default none selected
    selectAllLabel.appendChild(selectAllCheckbox);
    const boldText = document.createElement("strong");
    boldText.textContent = " Select All Fields";
    selectAllLabel.appendChild(boldText);
    grid.appendChild(selectAllLabel);

    // Generate checkboxes from table headers
    if (table) {
      const headers = table.querySelectorAll("thead th");

      headers.forEach((th, index) => {
        // Skip the first column (Sl No)
        if (index === 0) return;

        // Skip the last column (Action, empty <th>)
        if (index === headers.length - 1) return;

        const label = document.createElement("label");
        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.className = "col-checkbox";
        checkbox.value = index;
        checkbox.checked = false; // default none selected
        label.appendChild(checkbox);
        label.appendChild(document.createTextNode(" " + th.innerText.trim()));
        grid.appendChild(label);
      });
    }

    // Select/Deselect all columns when "Select All" is toggled
    selectAllCheckbox.addEventListener("change", () => {
      const colCheckboxes = grid.querySelectorAll(".col-checkbox");
      colCheckboxes.forEach(cb => cb.checked = selectAllCheckbox.checked);
    });

    // Update "Select All" if any checkbox is manually changed
    grid.querySelectorAll(".col-checkbox").forEach(cb => {
      cb.addEventListener("change", () => {
        selectAllCheckbox.checked = Array.from(grid.querySelectorAll(".col-checkbox")).every(cb => cb.checked);
      });
    });

    exportModal.style.display = "flex"; // Show modal
  });

  // Close modal when clicking outside
  window.addEventListener("click", (e) => {
    if (e.target === exportModal) {
      exportModal.style.display = "none";
    }
  });

  // Close modal on X click
  closeBtn.addEventListener("click", () => {
    exportModal.style.display = "none";
  });
});

// Handle export form submission
document.getElementById("exportForm").addEventListener("submit", function(e) {
    e.preventDefault();

    // Collect selected columns
    const selectedCols = Array.from(document.querySelectorAll("#exportForm .col-checkbox:checked"))
        .map(input => input.nextSibling.textContent.trim());

    if (selectedCols.length === 0) {
        alert("Please select at least one column to export.");
        return;
    }

    // Collect filters from filter form
    const filterForm = document.getElementById("filterForm");
    const formData = new FormData(filterForm);
    formData.append("export_all", "1");

    // Append selected columns
    selectedCols.forEach(col => formData.append("columns[]", col));

    // Send POST to trigger PHP export
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "vantage_registered_students", true);
    xhr.responseType = "blob";
    xhr.onload = function() {
        if (this.status === 200) {
            const blob = this.response;
            const link = document.createElement('a');
            link.href = window.URL.createObjectURL(blob);
            link.download = "vantage_registered_students.xlsx";
            link.click();
            closeExportModal();
        }
    };
    xhr.send(formData);
});

function closeExportModal() {
  document.getElementById("exportModal").style.display = "none";
}

// import excel sheets
function validateAndSubmit() {
    const input = document.getElementById("csv_file");
    const file = input.files[0];
    if (!file) return;

    const filename = file.name;
    const pattern = /\d{4}-\d{4}/; // Matches "2022-2024"

    if (!pattern.test(filename)) {
        alert('Error: Filename must include batch year in format YYYY-YYYY (e.g., vantage_students_2023-2026.xlsx)');
        input.value = ''; // Reset file input
        return false;
    }

    // Show loading indicator
    const modal = document.getElementById("ipt_importPopup");
    const modalContent = modal.querySelector(".ipt_modal-content");
    modalContent.innerHTML = '<div style="text-align:center; padding:40px;"><i class="fas fa-spinner fa-spin" style="font-size:48px; color:#007bff;"></i><p style="margin-top:20px; font-size:16px;">Importing file... Please wait.</p></div>';

    // Submit the form
    input.form.submit();

    // Force reload after 5 seconds if redirect doesn't work
    setTimeout(function() {
        window.location.href = 'vantage_registered_students.php';
    }, 5000);
  }

  function validateFilename() {
    // Prevent manual form submission if needed
    return true;
  }

  document.addEventListener("DOMContentLoaded", function () {
  const fileInput = document.getElementById("csv_file");
  if (fileInput) {
    fileInput.setAttribute("accept", ".csv, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.google-apps.spreadsheet");
  }
});

// message auto-hide
document.addEventListener("DOMContentLoaded", function () {
  const msgBox = document.getElementById("import-message-container");
  if (msgBox) {
    setTimeout(() => {
      msgBox.style.transition = "opacity 0.5s ease";
      msgBox.style.opacity = "0";
      setTimeout(() => msgBox.remove(), 500); // Remove it from DOM
    }, 5000); // 4 seconds
  }
});

// table sticky
function setStickyOffsets() {
  const header = document.querySelector(".sticky-table thead");
  if (!header) return;

  const stickyClasses = ['sticky-01', 'sticky-02', 'sticky-03'];
  let offset = 0;

  stickyClasses.forEach(stickyClass => {
    const th = header.querySelector(`th.${stickyClass}`);
    if (!th) return;

    // Apply left offset to the header cell
    th.style.left = `${offset}px`;

    // Get the column index for this sticky header
    const columnIndex = Array.from(th.parentNode.children).indexOf(th);

    // Apply left offset to each cell in this sticky column
    document.querySelectorAll(`.sticky-table tbody tr`).forEach(row => {
      const td = row.children[columnIndex];
      if (td && td.classList.contains(stickyClass)) {
        td.style.left = `${offset}px`;
      }
    });

    // Increase offset by the actual rendered width
    offset += th.getBoundingClientRect().width;
  });
}

// Run after table is rendered
window.addEventListener("load", setStickyOffsets);
window.addEventListener("resize", setStickyOffsets);



// Initialize Select2 for multi-select dropdowns
$(document).ready(function() {
  $('#course-multiselect').select2({
    placeholder: "Select course(s)",
    allowClear: true
  });
});

$(document).ready(function() {
    $('#company_name').select2({
        placeholder: "Select a company",
        width: '100%'
    });
});

// delete student functionality
$(document).on('click', '.delete-student-btn', function () {
  var studentId = $(this).data('student-id');
  var button = $(this); // Store reference to the clicked button

  if (confirm("Are you sure you want to delete this student and all their related data?")) {
    $.ajax({
      url: 'delete_student',
      method: 'POST',
      data: { student_id: studentId },
      success: function (response) {
        // Animate fade out of the row
        button.closest('tr').fadeOut(200, function () {
          $(this).remove(); // Remove row after animation
        });
      },
      error: function () {
        alert("Error deleting student.");
      }
    });
  }
});

// Edit and Save functionality
document.addEventListener("DOMContentLoaded", () => {
  // --- Detect dropdown change (Offcampus Selection) ---
  document.addEventListener("change", function (e) {
    const offcampusSelect = e.target.closest(".offcampus-select");
    if (offcampusSelect) {
      const row = offcampusSelect.closest("tr");

      // Enter edit mode (show editable inputs)
      row.querySelectorAll(".field-view").forEach(el => {
        if (!el.classList.contains("year-of-passing")) {
          el.classList.add("d-none");
        }
      });
      row.querySelectorAll(".field-edit").forEach(el => el.classList.remove("d-none"));

      // Focus the editable_comment input
      const commentInput = row.querySelector('input[name="editable_comment"]');
      const commentSpan = row.querySelector('.field-view');

      if (commentInput) {
        if (commentSpan) commentSpan.classList.add("d-none");
        commentInput.classList.remove("d-none");
        commentInput.focus();
      }
      showToast("Update the Off Campus Placement Comments column.", "info", 3000);
    }
  });

  // --- Handle edit and save button clicks ---
  document.addEventListener("click", function (e) {
    // --- Enter edit mode manually (Edit button) ---
    const editBtn = e.target.closest(".edit-btn");
    if (editBtn) {
      const row = editBtn.closest("tr");

      row.querySelectorAll(".field-view").forEach(el => {
        if (!el.classList.contains("year-of-passing")) {
          el.classList.add("d-none");
        }
      });
      row.querySelectorAll(".field-edit").forEach(el => el.classList.remove("d-none"));
      return;
    }

    // --- Save all editable fields (Save button) ---
    const saveBtn = e.target.closest(".save-btn");
    if (saveBtn) {
      const row = saveBtn.closest("tr");
      const studentId = saveBtn.dataset.studentId;

      const data = new URLSearchParams();
      data.append("action", "update_student");
      data.append("student_id", studentId);

      let valid = true;
      let emptyFields = [];

      // Collect all visible editable inputs
      row.querySelectorAll(".field-edit:not(.d-none)").forEach(input => {
        const val = input.value.trim();

        // --- HTML5 validation ---
        if (!input.checkValidity()) {
          input.classList.add("is-invalid");
          valid = false;
          input.reportValidity();
          return;
        }

        // --- Batch validation ---
        if (input.name === "batch") {
          const regex = /^\d{4}-\d{4}$/;
          if (!regex.test(val)) {
            input.classList.add("is-invalid");
            valid = false;
            showToast("Batch must be in format YYYY-YYYY (e.g., 2023-2024)", "warning", 3000);
            input.focus();
            return;
          }
        }

        // --- Empty field check (editable_comment can be empty) ---
        if (!val && !["allow_reapply", "editable_comment"].includes(input.name)) {
          input.classList.add("is-invalid");
          valid = false;
          const label = input.name.replace(/_/g, " ").replace(/\b\w/g, c => c.toUpperCase());
          emptyFields.push(label);
        } else {
          input.classList.remove("is-invalid");
          data.append(input.name, val);
        }
      });

      // include allow_reapply dropdown
      const reapplySelect = row.querySelector(".allow-reapply-select");
      if (reapplySelect) {
        data.append("allow_reapply", reapplySelect.value);
      }

      // include Offcampus_selection dropdown
      const offcampusSelect = row.querySelector(".offcampus-select");
      if (offcampusSelect) {
        data.append("Offcampus_selection", offcampusSelect.value);
      }

      // include editable_comment
      const editableComment = row.querySelector('input[name="editable_comment"]');
      if (editableComment) {
        data.append("editable_comment", editableComment.value.trim());
      }

      // --- Empty field notify ---
      if (emptyFields.length > 0) {
        showToast("Please fill the following fields before saving:<br>" + emptyFields.join(", "), "error", 6000);        
        return;
      }

      if (!valid) return;

      // --- Send to backend ---
      fetch(location.href, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: data.toString()
      })
        .then(res => res.text())
        .then(resp => {
          resp = resp.trim();

          if (resp.includes("Student updated successfully")) {
            showToast("Student record updated successfully.", "success", 3000);

            // update UI
            row.querySelectorAll(".field-edit").forEach(input => {
              const span = input.closest("td").querySelector(".field-view");
              if (span && input.value.trim()) {
                span.textContent = input.value.trim();

                // special: update year_of_passing
                if (input.name === "batch") {
                  const years = input.value.trim().split("-");
                  if (years.length === 2) {
                    const yopSpan = row.querySelector(".year-of-passing");
                    if (yopSpan) yopSpan.textContent = years[1];
                  }
                }
              }
              input.classList.add("d-none");
            });

            row.querySelectorAll(".field-view").forEach(el => el.classList.remove("d-none"));
          } else {
            showToast("Update failed: " + resp, "error", 4000);
          }
        })
        .catch(err => showToast("Error: " + err, "error", 4000));
    }
  });
});

// and the lazy scroll with Search filter (live search in table)
let offset = 0;
const limit = 50;
let loading = false;
let noMoreData = false;

function loadMoreStudents(reset = false) {
  if (loading || noMoreData) return;
  loading = true;
  document.getElementById("loading").style.display = "block";

  const form = document.getElementById("filterForm");
  const formData = new FormData(form);
  formData.append("ajax_filter", "1");
  formData.append("limit", limit);
  formData.append("offset", offset);

  const searchQuery = document.getElementById("searchInput")?.value.trim() || "";
  if (searchQuery !== "") {
    formData.append("search_query", searchQuery);
  }

  fetch("vantage_registered_students", {
    method: "POST",
    body: formData
  })
    .then(res => res.text())
    .then(data => {
      if (reset) {
        document.getElementById("tableBody").innerHTML = "";
        offset = 0;
        noMoreData = false;
      }

      if (data.trim() === "") {
        noMoreData = true; // no more records
      } else {
        document.getElementById("tableBody").insertAdjacentHTML("beforeend", data);
        offset += limit;
      }

      loading = false;
      document.getElementById("loading").style.display = "none";
    })
    .catch(err => {
      console.error("Error loading students:", err);
      loading = false;
      document.getElementById("loading").style.display = "none";
    });
}

// Auto-load first batch
document.addEventListener("DOMContentLoaded", () => {
  loadMoreStudents(true);

  // Infinite scroll
  document.querySelector(".table-responsive").addEventListener("scroll", function () {
    if (this.scrollTop + this.clientHeight >= this.scrollHeight - 50) {
      loadMoreStudents();
    }
  });

  // Bulk delete functionality
  const selectAllCheckbox = document.getElementById("selectAllCheckbox");
  const bulkDeleteBtn = document.getElementById("bulkDeleteBtn");
  const selectedCountSpan = document.getElementById("selectedCount");

  // Handle select all checkbox
  selectAllCheckbox.addEventListener("change", function() {
    const checkboxes = document.querySelectorAll(".student-checkbox");
    checkboxes.forEach(checkbox => {
      checkbox.checked = this.checked;
    });
    updateBulkActions();
  });

  // Handle individual checkbox changes
  document.addEventListener("change", function(e) {
    if (e.target.classList.contains("student-checkbox")) {
      updateBulkActions();

      // Update select all checkbox state
      const allCheckboxes = document.querySelectorAll(".student-checkbox");
      const checkedCheckboxes = document.querySelectorAll(".student-checkbox:checked");
      selectAllCheckbox.checked = allCheckboxes.length > 0 && allCheckboxes.length === checkedCheckboxes.length;
    }
  });

  // Update bulk action buttons visibility
  function updateBulkActions() {
    const checkedCheckboxes = document.querySelectorAll(".student-checkbox:checked");
    const count = checkedCheckboxes.length;

    selectedCountSpan.textContent = count;

    if (count > 0) {
      bulkDeleteBtn.style.display = "inline-block";
    } else {
      bulkDeleteBtn.style.display = "none";
    }
  }

  // Handle bulk delete button click
  bulkDeleteBtn.addEventListener("click", function() {
    const checkedCheckboxes = document.querySelectorAll(".student-checkbox:checked");
    const studentIds = Array.from(checkedCheckboxes).map(cb => cb.value);

    if (studentIds.length === 0) {
      showToast("No students selected", "error");
      return;
    }

    if (!confirm(`Are you sure you want to delete ${studentIds.length} student(s)? This action cannot be undone and will also delete their applications and placement records.`)) {
      return;
    }

    // Send delete request
    const formData = new FormData();
    studentIds.forEach(id => formData.append("student_ids[]", id));

    fetch("bulk_delete_students.php", {
      method: "POST",
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showToast(data.message, "success");

        // Remove deleted rows from table
        studentIds.forEach(id => {
          const row = document.querySelector(`tr[data-student-id="${id}"]`);
          if (row) row.remove();
        });

        // Reset checkboxes and counters
        selectAllCheckbox.checked = false;
        updateBulkActions();
      } else {
        showToast(data.message, "error");
      }
    })
    .catch(error => {
      showToast("Error deleting students: " + error, "error");
    });
  });
});

</script>

<?php include("footer.php"); ?>