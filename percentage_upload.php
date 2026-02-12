<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: index");
    exit;
}

include("config.php");
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$results = [];
$messageHtml = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["csv_file"])) {
    $file = $_FILES["csv_file"];
    $tmpPath = $file["tmp_name"];
    $fileExt = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

    $allowedTypes = ['csv', 'xls', 'xlsx'];
    if (!in_array($fileExt, $allowedTypes)) {
        $messageHtml = "<div class='alert alert-danger text-center' style='padding:5px;'>Invalid file type. Please upload a CSV, XLS, or XLSX file.</div>";
    } else {
        $dataRows = [];
        $header = [];

        try {
            // Create safe temp copy (avoids filename issues)
            $safeTmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'upload_' . uniqid() . '.' . $fileExt;
            move_uploaded_file($tmpPath, $safeTmp);

            // Read file depending on type
            if ($fileExt === 'csv') {
                if (($handle = fopen($safeTmp, "r")) !== false) {
                    $header = fgetcsv($handle);
                    while (($row = fgetcsv($handle, 1000, ",")) !== false) {
                        $dataRows[] = $row;
                    }
                    fclose($handle);
                }
            } else {
                $spreadsheet = IOFactory::load($safeTmp);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();
                $header = array_shift($rows);
                $dataRows = $rows;
            }

            // Remove the safe temp file after reading
            @unlink($safeTmp);

            // Map headers
            $headerPatterns = [
                'upid'       => ['placement id', 'upid', 'Placement ID', 'UPID', 'upid:', 'placement id:'],
                'reg_no'     => ['Student Register Number', 'Register Number', 'reg no', 'register no', 'register number', 'regno', 'regno:', 'reg no:', 'register no:'],
                'percentage' => ['percentage', 'percent', 'score', 'cgpa']
            ];

            $headerMap = [];
            foreach ($header as $index => $colName) {
                $normalized = preg_replace('/[^a-z0-9]/', '', strtolower(trim($colName)));
                foreach ($headerPatterns as $field => $patterns) {
                    foreach ($patterns as $pattern) {
                        if ($normalized === preg_replace('/[^a-z0-9]/', '', strtolower($pattern))) {
                            $headerMap[$field] = $index;
                            break 2;
                        }
                    }
                }
            }

            // Friendly display names
            $columnDisplayNames = [
                'upid'       => 'Placement ID',
                'reg_no'     => 'Registration Number',
                'percentage' => 'Percentage'
            ];

            //  Use display names for missing columns
            $missingColumns = array_diff(array_keys($headerPatterns), array_keys($headerMap));
            if (!empty($missingColumns)) {
                $missingDisplayNames = array_map(
                    fn($col) => $columnDisplayNames[$col] ?? $col,
                    $missingColumns
                );
                $messageHtml = "<div class='alert alert-danger text-center' style='padding:5px;'>Missing column(s): " . implode(', ', $missingDisplayNames) . "</div>";
            } else {
                foreach ($dataRows as $row) {
                    $upid = trim($row[$headerMap['upid']]);
                    $reg_no = trim($row[$headerMap['reg_no']]);
                    $percentage = trim($row[$headerMap['percentage']]);

                    if (empty($upid) || $percentage === '') {
                        $results[] = [
                            "upid" => $upid ?: "-",
                            "reg_no" => $reg_no ?: "-",
                            "percentage" => $percentage ?: "-",
                            "status" => "Skipped (missing data)"
                        ];
                        continue;
                    }

                    $stmt = $conn->prepare("UPDATE students SET percentage = ? WHERE upid = ?");
                    $stmt->bind_param("ds", $percentage, $upid);

                    if ($stmt->execute()) {
                        $status = ($stmt->affected_rows > 0) ? "Updated" : "Skipped (not found)";
                    } else {
                        $status = "Error: " . $stmt->error;
                    }

                    $results[] = [
                        "upid" => $upid,
                        "reg_no" => $reg_no,
                        "percentage" => $percentage,
                        "status" => $status
                    ];

                    $stmt->close();
                }

                if (empty($results)) {
                    $messageHtml = "<div class='alert alert-warning text-center' style='padding:5px;'>No records found in the uploaded file.</div>";
                } else {
                    $messageHtml = "<div class='alert alert-success text-center' style='padding:5px;'>File processed successfully!</div>";
                }
            }

        } catch (Exception $e) {
            $messageHtml = "<div class='alert alert-danger text-center' style='padding:5px;'>Error during import: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="bootstrap.min.css">
<title>Percentage Upload Status</title>

<style>
/* ================= Custom Table: pertable ================= */
.pertable {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}
.pertable th {
  position: sticky !important;
  top: 0;
  z-index: 2 !important;
  background-color: #650000 !important;
  color: white !important;
  font-size: 13px;
  padding: 12px 10px !important;
  text-align: center;
  vertical-align: middle;
  white-space: nowrap;
  border-bottom: 1px solid #444 !important;
}
.pertable td {
  font-size: 12px;
  padding: 5px 10px !important;
  white-space: nowrap;
  border-bottom: 1px solid #444 !important;
  text-align: center;
  vertical-align: middle;
}
.pertable tr:nth-child(even) td {
  background-color: #f2f2f2 !important;
}
.pertable input.form-control-sm,
.pertable select.form-select-sm {
  padding: 2px 6px !important;
  font-size: 11px !important;
  height: auto !important;
  background-color: white !important;
  color: black !important;
  border: 1px solid #fff !important;
}
.pertable td:last-child,
.pertable th:last-child {
    position: sticky !important;
    z-index: 3 !important;
}
.status-updated { color: green; font-weight: bold; }
.status-skipped { color: orange; font-weight: bold; }
.status-error { color: red; font-weight: bold; }
</style>
</head>

<body>
  <?php include 'header.php'; ?>

  <div class="heading-container">
    <h3 class="headings">Percentage Upload</h3>
    <p style="margin-bottom: 10px;">
      The uploaded Excel file should include:
      <strong>Student Register No</strong>, 
      <strong>Student Placement ID</strong>, 
      and the <strong>Student’s Percentage</strong>.
    </p>
  </div>

  <div class="export-import-container">
    <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search... (name, reg no, etc.)">

    <form method="POST" enctype="multipart/form-data" class="import-form" onsubmit="return validateFilename()">
      <label for="csv_file" class="import-button">
        <i class="fa fa-download" aria-hidden="true" style="margin-right: 5px;"></i> Import File
      </label>
      <input type="file" id="csv_file" name="csv_file" accept=".csv,.xls,.xlsx" required style="display: none;" onchange="validateAndSubmit()">
    </form>
  </div>

  <?php if (!empty($messageHtml)): ?>
    <div id="import-message-container">
      <?= $messageHtml ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($results)): ?>
    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
      <table class="pertable">
        <thead>
          <tr>
            <th>UPID</th>
            <th>Reg No</th>
            <th>Percentage</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="tableBody">
          <?php foreach ($results as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['upid']) ?></td>
              <td><?= htmlspecialchars($r['reg_no']) ?></td>
              <td><?= htmlspecialchars($r['percentage']) ?></td>
              <td class="<?= strpos(strtolower($r['status']), 'updated')!==false ? 'status-updated' : (strpos(strtolower($r['status']),'skipped')!==false ? 'status-skipped' : 'status-error') ?>">
                <?= htmlspecialchars($r['status']) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p style="text-align:center;">No data processed.</p>
  <?php endif; ?>

  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const msgBox = document.getElementById("import-message-container");
      if (msgBox) {
        setTimeout(() => {
          msgBox.style.transition = "opacity 0.5s ease";
          msgBox.style.opacity = "0";
          setTimeout(() => msgBox.remove(), 500);
        }, 5000);
      }
    });

    function validateFilename() {
      const fileInput = document.getElementById('csv_file');
      if (!fileInput.value) {
        alert('Please select a file.');
        return false;
      }
      return true;
    }

    function validateAndSubmit() {
      if (validateFilename()) {
        document.querySelector('.import-form').submit();
      }
    }

    //  Live search/filter within the table by keyword input
    function filterTable() {
      const keyword = document.getElementById("searchInput").value.toLowerCase();
      const rows = document.querySelectorAll("#tableBody tr");

      rows.forEach(row => {
        const rowText = Array.from(row.querySelectorAll("td"))
          .map(cell => cell.textContent.toLowerCase())
          .join(" ");
        row.style.display = rowText.includes(keyword) ? "" : "none";
      });
    }
  </script>
</body>
</html>