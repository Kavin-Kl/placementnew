<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    die("<h1>Error: Admin login required</h1><p>Please <a href='index.php'>login</a> first.</p>");
}

include("config.php");
require 'vendor/autoload.php';

set_time_limit(1800); // 30 minutes
ini_set('memory_limit', '4096M');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Migrate Old Website Data</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: #2d2d2d;
            padding: 30px;
            border-radius: 8px;
        }
        h1 {
            color: #89d185;
            border-bottom: 2px solid #650000;
            padding-bottom: 10px;
        }
        h2 {
            color: #61afef;
            margin-top: 30px;
        }
        .success { color: #89d185; font-weight: bold; }
        .error { color: #f48771; font-weight: bold; }
        .warning { color: #e5c07b; font-weight: bold; }
        .info { color: #61afef; }
        .log-line {
            padding: 5px 0;
            border-left: 3px solid transparent;
            padding-left: 10px;
            margin: 2px 0;
        }
        .log-line.success { border-left-color: #89d185; }
        .log-line.error { border-left-color: #f48771; }
        .log-line.warning { border-left-color: #e5c07b; }
        .log-line.info { border-left-color: #61afef; }
        .stats-box {
            background: #650000;
            color: white;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .stats-box h3 {
            margin: 0 0 15px 0;
            color: white;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background: #1e1e1e;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border: 1px solid #444;
            font-size: 12px;
        }
        th {
            background: #650000;
            color: white;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #650000;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 5px;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #520000;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📦 Migrate Old Website Data - Drives & Applications</h1>

<?php

$file = 'C:/Users/Kavin/Downloads/placement_backup_February_2026 (1).xlsx';

if (!file_exists($file)) {
    echo "<div class='log-line error'>ERROR: File not found at $file</div>";
    echo "<p>Please ensure the backup file exists at this location.</p>";
    exit;
}

try {
    echo "<div class='log-line info'>📁 File found: $file</div>";
    echo "<div class='log-line info'>📊 File size: " . round(filesize($file)/1024, 2) . " KB</div>";
    echo "<br>";

    // Load Excel file
    echo "<div class='log-line info'>📂 Loading Excel file...</div>";
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($file);

    echo "<div class='log-line success'>✓ Excel file loaded successfully</div>";
    echo "<div class='log-line info'>Available sheets: " . implode(', ', $spreadsheet->getSheetNames()) . "</div>";
    echo "<br>";

    // Statistics
    $stats = [
        'drives_inserted' => 0,
        'drives_skipped' => 0,
        'drives_updated' => 0,
        'roles_inserted' => 0,
        'roles_skipped' => 0,
        'applications_inserted' => 0,
        'applications_skipped' => 0,
        'students_not_found' => 0
    ];

    $errors = [];

    // ============================================================
    // STEP 1: Import Drives
    // ============================================================
    echo "<h2>🚗 STEP 1: Importing Drives</h2>";

    $drivesSheet = $spreadsheet->getSheetByName('Drives');
    if (!$drivesSheet) {
        throw new Exception("Sheet 'Drives' not found in Excel file");
    }

    $drivesData = $drivesSheet->toArray(null, false, false, false);
    $drivesHeader = array_shift($drivesData);

    // Map headers
    $driveHeaderMap = [];
    foreach ($drivesHeader as $index => $colName) {
        $normalized = strtolower(trim($colName));
        $driveHeaderMap[$normalized] = $index;
    }

    echo "<div class='log-line info'>Found " . count($drivesData) . " drives in Excel</div>";
    echo "<div class='log-line info'>Headers: " . implode(', ', $drivesHeader) . "</div>";
    echo "<br>";

    foreach ($drivesData as $rowNum => $data) {
        if (empty(array_filter($data))) continue; // Skip empty rows

        $company_name = trim($data[$driveHeaderMap['company_name'] ?? 0] ?? '');
        $drive_no = trim($data[$driveHeaderMap['drive_no'] ?? 1] ?? '');
        $courses = trim($data[$driveHeaderMap['courses'] ?? 2] ?? '');
        $academic_year = trim($data[$driveHeaderMap['academic_year'] ?? 3] ?? '');
        $open_date = trim($data[$driveHeaderMap['open_date'] ?? 4] ?? '');
        $created_by = trim($data[$driveHeaderMap['created_by'] ?? 5] ?? 'Admin');
        $created_on = trim($data[$driveHeaderMap['created_on'] ?? 6] ?? date('Y-m-d H:i:s'));

        if (empty($company_name) || empty($drive_no)) {
            $stats['drives_skipped']++;
            continue;
        }

        // Check if drive already exists
        $checkStmt = $conn->prepare("SELECT drive_id FROM drives WHERE company_name = ? AND drive_no = ?");
        $checkStmt->bind_param("ss", $company_name, $drive_no);
        $checkStmt->execute();
        $existingDrive = $checkStmt->get_result();

        if ($existingDrive->num_rows > 0) {
            $stats['drives_skipped']++;
            $checkStmt->close();
            continue;
        }
        $checkStmt->close();

        // Convert date format if needed
        if (!empty($open_date)) {
            try {
                if (is_numeric($open_date)) {
                    // Excel serial date
                    $unix_date = ($open_date - 25569) * 86400;
                    $open_date = date('Y-m-d', $unix_date);
                } elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $open_date)) {
                    // dd-mm-yyyy format
                    $dt = DateTime::createFromFormat('d-m-Y', $open_date);
                    if ($dt) $open_date = $dt->format('Y-m-d');
                }
            } catch (Exception $e) {
                $open_date = null;
            }
        }

        // Insert drive
        $insertDriveStmt = $conn->prepare("
            INSERT INTO drives (company_name, drive_no, courses, academic_year, open_date, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        if (!$insertDriveStmt) {
            $stats['drives_skipped']++;
            $errors[] = "Failed to prepare drive insert: $company_name - $drive_no - " . $conn->error;
            continue;
        }

        $insertDriveStmt->bind_param("ssssss", $company_name, $drive_no, $courses, $academic_year, $open_date, $created_by);

        if ($insertDriveStmt->execute()) {
            $stats['drives_inserted']++;
            echo "<div class='log-line success'>✓ Inserted drive: $company_name - $drive_no</div>";
        } else {
            $stats['drives_skipped']++;
            $errors[] = "Failed to insert drive: $company_name - $drive_no - " . $insertDriveStmt->error;
        }
        $insertDriveStmt->close();

        if ($stats['drives_inserted'] % 20 == 0) {
            echo "<div class='log-line info'>Progress: {$stats['drives_inserted']} drives imported...</div>";
            flush();
            ob_flush();
        }
    }

    echo "<br>";
    echo "<div class='log-line success'>✓ Drives import completed</div>";
    echo "<div class='log-line info'>Inserted: {$stats['drives_inserted']}, Skipped: {$stats['drives_skipped']}</div>";

    // ============================================================
    // STEP 2: Import Drive Roles
    // ============================================================
    echo "<br><h2>👔 STEP 2: Importing Drive Roles</h2>";

    $rolesSheet = $spreadsheet->getSheetByName('Drive_Roles');
    if ($rolesSheet) {
        $rolesData = $rolesSheet->toArray(null, false, false, false);
        $rolesHeader = array_shift($rolesData);

        // Map headers
        $roleHeaderMap = [];
        foreach ($rolesHeader as $index => $colName) {
            $normalized = strtolower(trim($colName));
            $roleHeaderMap[$normalized] = $index;
        }

        echo "<div class='log-line info'>Found " . count($rolesData) . " roles in Excel</div>";
        echo "<br>";

        foreach ($rolesData as $data) {
            if (empty(array_filter($data))) continue;

            $company_name = trim($data[$roleHeaderMap['company_name'] ?? 0] ?? '');
            $drive_no = trim($data[$roleHeaderMap['drive_no'] ?? 1] ?? '');
            $designation_name = trim($data[$roleHeaderMap['designation_name'] ?? 2] ?? '');
            $ctc = trim($data[$roleHeaderMap['ctc'] ?? 3] ?? '');
            $stipend = trim($data[$roleHeaderMap['stipend'] ?? 4] ?? '');
            $offer_type = trim($data[$roleHeaderMap['offer_type'] ?? 5] ?? 'FTE');

            if (empty($company_name) || empty($drive_no) || empty($designation_name)) {
                $stats['roles_skipped']++;
                continue;
            }

            // Get drive_id
            $driveStmt = $conn->prepare("SELECT drive_id FROM drives WHERE company_name = ? AND drive_no = ?");
            $driveStmt->bind_param("ss", $company_name, $drive_no);
            $driveStmt->execute();
            $driveResult = $driveStmt->get_result();

            if ($driveResult->num_rows == 0) {
                $stats['roles_skipped']++;
                $errors[] = "Drive not found for role: $company_name - $drive_no - $designation_name";
                $driveStmt->close();
                continue;
            }

            $drive_id = $driveResult->fetch_assoc()['drive_id'];
            $driveStmt->close();

            // Check if role already exists
            $checkRoleStmt = $conn->prepare("SELECT role_id FROM drive_roles WHERE drive_id = ? AND designation_name = ?");
            $checkRoleStmt->bind_param("is", $drive_id, $designation_name);
            $checkRoleStmt->execute();
            $existingRole = $checkRoleStmt->get_result();

            if ($existingRole->num_rows > 0) {
                $stats['roles_skipped']++;
                $checkRoleStmt->close();
                continue;
            }
            $checkRoleStmt->close();

            // Insert role
            $insertRoleStmt = $conn->prepare("
                INSERT INTO drive_roles (drive_id, designation_name, ctc, stipend, offer_type)
                VALUES (?, ?, ?, ?, ?)
            ");

            if (!$insertRoleStmt) {
                $stats['roles_skipped']++;
                $errors[] = "Failed to prepare role insert: $company_name - $designation_name - " . $conn->error;
                continue;
            }

            $insertRoleStmt->bind_param("issss", $drive_id, $designation_name, $ctc, $stipend, $offer_type);

            if ($insertRoleStmt->execute()) {
                $stats['roles_inserted']++;
                echo "<div class='log-line success'>✓ Inserted role: $company_name - $designation_name</div>";
            } else {
                $stats['roles_skipped']++;
                $errors[] = "Failed to insert role: $company_name - $designation_name - " . $insertRoleStmt->error;
            }
            $insertRoleStmt->close();

            if ($stats['roles_inserted'] % 20 == 0) {
                echo "<div class='log-line info'>Progress: {$stats['roles_inserted']} roles imported...</div>";
                flush();
                ob_flush();
            }
        }

        echo "<br>";
        echo "<div class='log-line success'>✓ Roles import completed</div>";
        echo "<div class='log-line info'>Inserted: {$stats['roles_inserted']}, Skipped: {$stats['roles_skipped']}</div>";
    } else {
        echo "<div class='log-line warning'>⚠ No 'Drive_Roles' sheet found, skipping...</div>";
    }

    // ============================================================
    // STEP 3: Import Applications
    // ============================================================
    echo "<br><h2>📝 STEP 3: Importing Applications</h2>";

    $appsSheet = $spreadsheet->getSheetByName('Applications');
    if ($appsSheet) {
        $appsData = $appsSheet->toArray(null, false, false, false);
        $appsHeader = array_shift($appsData);

        // Map headers
        $appHeaderMap = [];
        foreach ($appsHeader as $index => $colName) {
            $normalized = strtolower(trim($colName));
            $appHeaderMap[$normalized] = $index;
        }

        echo "<div class='log-line info'>Found " . count($appsData) . " applications in Excel</div>";
        echo "<div class='log-line info'>Headers: " . implode(', ', $appsHeader) . "</div>";
        echo "<br>";

        foreach ($appsData as $data) {
            if (empty(array_filter($data))) continue;

            $upid = trim($data[$appHeaderMap['upid'] ?? 0] ?? '');
            $reg_no = trim($data[$appHeaderMap['reg_no'] ?? 1] ?? '');
            $company_name = trim($data[$appHeaderMap['company_name'] ?? 2] ?? '');
            $drive_no = trim($data[$appHeaderMap['drive_no'] ?? 3] ?? '');
            $role = trim($data[$appHeaderMap['role'] ?? 4] ?? '');
            $status = trim($data[$appHeaderMap['status'] ?? 5] ?? 'pending');

            if (empty($upid) && empty($reg_no)) {
                $stats['applications_skipped']++;
                continue;
            }

            // Find student_id
            $studentStmt = $conn->prepare("SELECT student_id FROM students WHERE upid = ? OR reg_no = ?");
            $studentStmt->bind_param("ss", $upid, $reg_no);
            $studentStmt->execute();
            $studentResult = $studentStmt->get_result();

            if ($studentResult->num_rows == 0) {
                $stats['students_not_found']++;
                $errors[] = "Student not found: UPID=$upid, RegNo=$reg_no";
                $studentStmt->close();
                continue;
            }

            $student_id = $studentResult->fetch_assoc()['student_id'];
            $studentStmt->close();

            // Find drive_id
            $driveStmt = $conn->prepare("SELECT drive_id FROM drives WHERE company_name = ? AND drive_no = ?");
            $driveStmt->bind_param("ss", $company_name, $drive_no);
            $driveStmt->execute();
            $driveResult = $driveStmt->get_result();

            if ($driveResult->num_rows == 0) {
                $stats['applications_skipped']++;
                $errors[] = "Drive not found for application: $company_name - $drive_no";
                $driveStmt->close();
                continue;
            }

            $drive_id = $driveResult->fetch_assoc()['drive_id'];
            $driveStmt->close();

            // Find role_id
            $roleStmt = $conn->prepare("SELECT role_id FROM drive_roles WHERE drive_id = ? AND designation_name = ?");
            $roleStmt->bind_param("is", $drive_id, $role);
            $roleStmt->execute();
            $roleResult = $roleStmt->get_result();

            $role_id = null;
            if ($roleResult->num_rows > 0) {
                $role_id = $roleResult->fetch_assoc()['role_id'];
            }
            $roleStmt->close();

            // Check if application already exists
            $checkAppStmt = $conn->prepare("SELECT application_id FROM applications WHERE student_id = ? AND drive_id = ?");
            $checkAppStmt->bind_param("ii", $student_id, $drive_id);
            $checkAppStmt->execute();
            $existingApp = $checkAppStmt->get_result();

            if ($existingApp->num_rows > 0) {
                $stats['applications_skipped']++;
                $checkAppStmt->close();
                continue;
            }
            $checkAppStmt->close();

            // Insert application
            $insertAppStmt = $conn->prepare("
                INSERT INTO applications (student_id, drive_id, role_id, upid, reg_no, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            if (!$insertAppStmt) {
                $stats['applications_skipped']++;
                $errors[] = "Failed to prepare application insert: UPID=$upid - " . $conn->error;
                continue;
            }

            $insertAppStmt->bind_param("iiisss", $student_id, $drive_id, $role_id, $upid, $reg_no, $status);

            if ($insertAppStmt->execute()) {
                $stats['applications_inserted']++;
                if ($stats['applications_inserted'] % 50 == 0) {
                    echo "<div class='log-line info'>Progress: {$stats['applications_inserted']} applications imported...</div>";
                    flush();
                    ob_flush();
                }
            } else {
                $stats['applications_skipped']++;
                $errors[] = "Failed to insert application: UPID=$upid - " . $insertAppStmt->error;
            }
            $insertAppStmt->close();
        }

        echo "<br>";
        echo "<div class='log-line success'>✓ Applications import completed</div>";
        echo "<div class='log-line info'>Inserted: {$stats['applications_inserted']}, Skipped: {$stats['applications_skipped']}</div>";
    } else {
        echo "<div class='log-line warning'>⚠ No 'Applications' sheet found, skipping...</div>";
    }

    // ============================================================
    // SUMMARY
    // ============================================================
    echo "<br>";
    echo "<div class='stats-box'>";
    echo "<h3>📊 MIGRATION SUMMARY</h3>";
    echo "<table style='background: white; color: black;'>";
    echo "<tr><th>Category</th><th>Inserted</th><th>Skipped</th></tr>";
    echo "<tr><td>Drives</td><td class='success'>{$stats['drives_inserted']}</td><td class='warning'>{$stats['drives_skipped']}</td></tr>";
    echo "<tr><td>Drive Roles</td><td class='success'>{$stats['roles_inserted']}</td><td class='warning'>{$stats['roles_skipped']}</td></tr>";
    echo "<tr><td>Applications</td><td class='success'>{$stats['applications_inserted']}</td><td class='warning'>{$stats['applications_skipped']}</td></tr>";
    echo "<tr><td>Students Not Found</td><td colspan='2' class='error'>{$stats['students_not_found']}</td></tr>";
    echo "</table>";
    echo "</div>";

    if (!empty($errors)) {
        echo "<h2 class='error'>⚠️ Errors Encountered (First 50)</h2>";
        echo "<table>";
        echo "<tr><th>#</th><th>Error Message</th></tr>";
        foreach (array_slice($errors, 0, 50) as $index => $error) {
            echo "<tr><td>" . ($index + 1) . "</td><td>" . htmlspecialchars($error) . "</td></tr>";
        }
        echo "</table>";
        if (count($errors) > 50) {
            echo "<p class='warning'>... and " . (count($errors) - 50) . " more errors</p>";
        }
    }

    echo "<div style='margin: 30px 0;'>";
    echo "<a href='dashboard.php' class='btn'>📈 Go to Dashboard</a>";
    echo "<a href='course_specific_drive_data.php' class='btn'>📊 View Company Tracker</a>";
    echo "<a href='manage_rounds.php' class='btn'>📝 View Applications</a>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='log-line error'>❌ ERROR: " . $e->getMessage() . "</div>";
    echo "<div class='log-line error'>Stack trace:</div>";
    echo "<pre style='color: #f48771;'>" . $e->getTraceAsString() . "</pre>";
}

$conn->close();
?>

    </div>
</body>
</html>
