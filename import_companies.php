<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

include("config.php");
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// Handle CSV/Excel Import
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["company_file"])) {
    set_time_limit(600);
    ini_set('memory_limit', '2048M');

    $file = $_FILES["company_file"];
    $fileName = $file["name"];
    $tmpPath = $file["tmp_name"];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $allowedTypes = ['csv', 'xls', 'xlsx'];
    if (!in_array($fileExt, $allowedTypes)) {
        $_SESSION['import_message'] = "Invalid file type. Only CSV, XLS and XLSX are allowed.";
        $_SESSION['import_status'] = "error";
        header("Location: import_companies.php");
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
            $spreadsheet = IOFactory::load($tmpPath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            $header = array_shift($rows);
            $dataRows = $rows;
        }

        // Map headers to internal field names
        $headerPatterns = [
            'company_name'   => ['company name', 'company', 'name'],
            'company_sector' => ['company sector', 'sector', 'industry'],
            'role'           => ['role', 'designation', 'position', 'job title'],
            'salary'         => ['salary', 'ctc', 'package', 'compensation'],
            'deadline'       => ['deadline', 'close date', 'closing date', 'application deadline'],
            'jd_link'        => ['jd link', 'job description', 'jd url', 'description link']
        ];

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

        // Check if company_name is present (required)
        if (!isset($headerMap['company_name'])) {
            $_SESSION['import_message'] = "Missing required column: Company Name";
            $_SESSION['import_status'] = "error";
            header("Location: import_companies.php");
            exit;
        }

        $inserted = 0;
        $skipped = 0;
        $updated = 0;

        foreach ($dataRows as $data) {
            $company_name = trim($data[$headerMap['company_name']] ?? '');

            if (empty($company_name)) {
                $skipped++;
                continue;
            }

            $company_sector = isset($headerMap['company_sector']) ? trim($data[$headerMap['company_sector']] ?? '') : 'Others';
            $role = isset($headerMap['role']) ? trim($data[$headerMap['role']] ?? '') : '';
            $salary = isset($headerMap['salary']) ? trim($data[$headerMap['salary']] ?? '') : '';
            $deadline = isset($headerMap['deadline']) ? trim($data[$headerMap['deadline']] ?? '') : '';
            $jd_link = isset($headerMap['jd_link']) ? trim($data[$headerMap['jd_link']] ?? '') : '';

            // Check if company already exists in drives
            $check = $conn->prepare("SELECT drive_id FROM drives WHERE company_name = ? ORDER BY created_at DESC LIMIT 1");
            $check->bind_param("s", $company_name);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                // Company exists - optionally update or skip
                $updated++;
                $check->close();
                continue;
            }
            $check->close();

            // Insert placeholder drive entry (admin needs to complete it)
            $academic_year = $_SESSION['selected_academic_year'] ?? '2025-2026';
            $drive_no = "Drive 1"; // Will be updated when admin edits
            $open_date = date('Y-m-d H:i:s');
            $close_date = !empty($deadline) ? date('Y-m-d H:i:s', strtotime($deadline)) : date('Y-m-d H:i:s', strtotime('+30 days'));
            $created_by = $_SESSION['username'] ?? 'Bulk Import';
            $form_link = uniqid("form_");

            $stmt = $conn->prepare("INSERT INTO drives (
                company_name, company_sector, drive_no, open_date, close_date, created_by, form_link, jd_link, academic_year, show_to_placement, show_to_internship, show_to_vantage
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1)");

            if ($stmt) {
                $stmt->bind_param("sssssssss",
                    $company_name, $company_sector, $drive_no, $open_date, $close_date, $created_by, $form_link, $jd_link, $academic_year
                );

                if ($stmt->execute()) {
                    $inserted++;
                } else {
                    error_log("Insert failed for company $company_name: " . $stmt->error);
                    $skipped++;
                }
                $stmt->close();
            }
        }

        $message = "Import completed. Inserted: $inserted companies";
        if ($updated > 0) {
            $message .= ", Skipped (already exists): $updated";
        }
        if ($skipped > 0) {
            $message .= ", Failed: $skipped";
        }
        $_SESSION['import_message'] = $message;
        $_SESSION['import_status'] = ($inserted > 0) ? "success" : "warning";

    } catch (Exception $e) {
        $_SESSION['import_message'] = "Error during import: " . $e->getMessage();
        $_SESSION['import_status'] = "error";
    }

    header("Location: import_companies.php");
    exit;
}

include("header.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Companies</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .import-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .upload-area {
            border: 2px dashed #650000;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            margin: 20px 0;
        }
        .btn-upload {
            background: #650000;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-upload:hover {
            background: #7d0000;
        }
        .sample-format {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .message.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }
        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        .message.warning {
            background: #fef9c3;
            color: #854d0e;
            border: 1px solid #fde68a;
        }
    </style>
</head>
<body>
    <div class="import-container">
        <h2>📊 Bulk Import Companies</h2>
        <p>Import multiple companies at once using CSV or Excel files.</p>

        <?php if (isset($_SESSION['import_message'])): ?>
            <div class="message <?= $_SESSION['import_status'] ?? 'success' ?>">
                <?= htmlspecialchars($_SESSION['import_message']) ?>
            </div>
            <?php
            unset($_SESSION['import_message']);
            unset($_SESSION['import_status']);
            ?>
        <?php endif; ?>

        <div class="sample-format">
            <h4>📋 Required Format:</h4>
            <p><strong>Required Column:</strong> Company Name</p>
            <p><strong>Optional Columns:</strong> Company Sector, Role, Salary, Deadline, JD Link</p>
            <br>
            <p><strong>Example:</strong></p>
            <table border="1" cellpadding="5" style="width: 100%; border-collapse: collapse;">
                <tr>
                    <th>Company Name</th>
                    <th>Company Sector</th>
                    <th>Role</th>
                    <th>Salary</th>
                    <th>Deadline</th>
                </tr>
                <tr>
                    <td>TechCorp</td>
                    <td>IT</td>
                    <td>Software Engineer</td>
                    <td>12 LPA</td>
                    <td>2026-04-30</td>
                </tr>
                <tr>
                    <td>DataSystems Inc</td>
                    <td>Analytics</td>
                    <td>Data Analyst</td>
                    <td>8 LPA</td>
                    <td>2026-05-15</td>
                </tr>
            </table>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="upload-area">
                <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #650000;"></i>
                <h3>Upload CSV or Excel File</h3>
                <p>Supported formats: .csv, .xls, .xlsx</p>
                <input type="file" name="company_file" id="company_file" accept=".csv,.xls,.xlsx" required style="display:none;">
                <button type="button" class="btn-upload" onclick="document.getElementById('company_file').click()">
                    Choose File
                </button>
                <p id="file-name" style="margin-top: 15px; color: #666;"></p>
            </div>

            <button type="submit" class="btn-upload" style="width: 100%; margin-top: 20px;" id="submitBtn" disabled>
                <i class="fas fa-upload"></i> Import Companies
            </button>
        </form>

        <p style="margin-top: 20px; text-align: center;">
            <a href="dashboard.php" style="color: #650000;">← Back to Dashboard</a>
        </p>
    </div>

    <script>
        document.getElementById('company_file').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || '';
            document.getElementById('file-name').textContent = fileName ? `Selected: ${fileName}` : '';
            document.getElementById('submitBtn').disabled = !fileName;
        });
    </script>

    <?php include("footer.php"); ?>
</body>
</html>
