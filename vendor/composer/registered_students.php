<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

include("config.php");
require_once __DIR__ . '/PhpSpreadsheet/src/Bootstrap.php';
use PhpOffice\PhpSpreadsheet\IOFactory;


$batches = $conn->query("SELECT DISTINCT batch FROM students ORDER BY batch DESC");
$placedStatuses = $conn->query("SELECT DISTINCT placed_status FROM students ORDER BY placed_status ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_filter'])) {
    $where = [];
    $params = [];
    $types = "";

    $fields = [
        "upid", "program_type", "program", "course", "class",
        "applied_date", "batch", "placed_status", "company_name"
    ];

    $exactMatchFields = ["upid", "batch", "placed_status", "applied_date"];

    foreach ($fields as $field) {
        if (!empty($_POST[$field])) {
            if (in_array($field, $exactMatchFields)) {
                $where[] = "$field = ?";
                $params[] = $_POST[$field];
            } else {
                $where[] = "$field LIKE ?";
                $params[] = "%" . $_POST[$field] . "%";
            }
            $types .= "s";
        }
    }

    $sql = "SELECT 
    s.*,
    (
        SELECT a.status
        FROM applications a
        WHERE a.student_id = s.student_id AND a.status IN ('placed', 'blocked')
        ORDER BY FIELD(a.status, 'placed', 'blocked')
        LIMIT 1
    ) AS placed_status,
    (
        SELECT d.company_name
        FROM applications a
        LEFT JOIN drives d ON a.drive_id = d.drive_id
        WHERE a.student_id = s.student_id AND a.status IN ('placed', 'blocked')
        ORDER BY FIELD(a.status, 'placed', 'blocked')
        LIMIT 1
    ) AS company_name,
    (
        SELECT a.comments
        FROM applications a
        WHERE a.student_id = s.student_id AND a.status IN ('placed', 'blocked')
        ORDER BY FIELD(a.status, 'placed', 'blocked')
        LIMIT 1
    ) AS comment
FROM students s

";

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " GROUP BY s.student_id";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
    $statuses = explode(',', strtolower($row['all_statuses'] ?? ''));
    $statuses = array_map('trim', $statuses);

    $finalStatus = 'not_placed';
    if (in_array('placed', $statuses)) {
        $finalStatus = 'placed';
    } elseif (in_array('blocked', $statuses)) {
        $finalStatus = 'blocked';
    }

    $update = $conn->prepare("UPDATE students SET 
        placed_status = ?, 
        comment = ?, 
        company_name = ? 
        WHERE student_id = ?");

    $update->bind_param("sssi", 
        $finalStatus,
        $row['comment'],
        $row['company'],
        $row['student_id']
    );
    $update->execute();
}


    $result->data_seek(0);

    while ($s = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($s['upid']) . "</td>";
        echo "<td>" . htmlspecialchars($s['program_type']) . "</td>";
        echo "<td>" . htmlspecialchars($s['program']) . "</td>";
        echo "<td>" . htmlspecialchars($s['course']) . "</td>";
        echo "<td>" . htmlspecialchars($s['class']) . "</td>";
        echo "<td>" . htmlspecialchars($s['year_of_passing']) . "</td>";
        echo "<td>" . htmlspecialchars($s['reg_no']) . "</td>";
        echo "<td>" . htmlspecialchars($s['student_name']) . "</td>";
        echo "<td>" . htmlspecialchars($s['email']) . "</td>";
        echo "<td>" . htmlspecialchars($s['phone_no']) . "</td>";
        echo "<td>" . htmlspecialchars($s['applied_date']) . "</td>";
        echo "<td>" . htmlspecialchars($s['batch']) . "</td>";
        echo "<td>" . htmlspecialchars($s['placed_status']) . "</td>";
        echo "<td>" . htmlspecialchars($s['company_name']) . "</td>";
        echo "<td>" . htmlspecialchars($s['comment']) . "</td>";
        echo "</tr>";
        error_log("Statuses: " . implode(',', $statuses));
error_log("Final Placed Status: $finalStatus");

    }
    exit;
}

// Display message if available
$messageHtml = '';
if (!empty($_SESSION['import_message'])) {
    $type = $_SESSION['import_status'] ?? 'success';

    switch ($type) {
        case 'error':
            $class = 'bg-red-100 text-red-800 border-red-300';
            break;
        case 'warning':
            $class = 'bg-yellow-100 text-yellow-800 border-yellow-300';
            break;
        case 'info':
            $class = 'bg-blue-100 text-blue-800 border-blue-300';
            break;
        default: // success
            $class = 'bg-green-100 text-green-800 border-green-300';
    }

    $messageHtml = "<div class='border p-1 mb-4 rounded text-center $class'>" . htmlspecialchars($_SESSION['import_message']) . "</div>";
    unset($_SESSION['import_message']);
    unset($_SESSION['import_status']);
}

// === Handle CSV Import ===


if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["csv_file"])) {
    $file = $_FILES["csv_file"];
    $fileName = $file["name"];
    $tmpPath = $file["tmp_name"];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (preg_match('/(\d{4})-(\d{4})/', $fileName, $matches)) {
        $batchYear = $matches[0];
        $yearOfPassing = (int) $matches[2];
    } else {
        $_SESSION['import_message'] = "Filename must include batch year like 2022-2023";
        $_SESSION['import_status'] = "info";
        header("Location: registered_students.php");
        exit;
    }

    $allowedTypes = ['csv', 'xls', 'xlsx'];
    if (!in_array($fileExt, $allowedTypes)) {
        $_SESSION['import_message'] = "Invalid file type. Only .CSV, .XLS and .XLSX (Excel sheets and Goolge sheets) are allowed.";
        $_SESSION['import_status'] = "error";
        header("Location: registered_students.php");
        exit;
    }

    $dataRows = [];

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

        // Header alias map (user header → DB field)
        $headerAliasMap = [
            'placement id'    => 'upid',
            'program type'    => 'program_type',
            'program'         => 'program',
            'course'          => 'course',
            'class'           => 'class',
            'reg no'          => 'reg_no',
            'name'            => 'student_name',
            'email'           => 'email',
            'phone'           => 'phone_no'
        ];

        $expectedColumns = array_values($headerAliasMap);
        $headerMap = [];

        foreach ($header as $index => $colName) {
            $normalized = strtolower(trim($colName));
            if (isset($headerAliasMap[$normalized])) {
                $fieldName = $headerAliasMap[$normalized];
                $headerMap[$fieldName] = $index;
            }
        }

        foreach ($expectedColumns as $col) {
            if (!isset($headerMap[$col])) {
                $_SESSION['import_message'] = "Missing required column: $col";
                $_SESSION['import_status'] = "error";
                header("Location: registered_students.php");
                exit;
            }
        }

        $inserted = 0;
        $skipped = 0;

        foreach ($dataRows as $data) {
            $upid          = trim($data[$headerMap['upid']]);
            $program_type  = trim($data[$headerMap['program_type']]);
            $program       = trim($data[$headerMap['program']]);
            $course        = trim($data[$headerMap['course']]);
            $class         = trim($data[$headerMap['class']]);
            $reg_no        = trim($data[$headerMap['reg_no']]);
            $student_name  = trim($data[$headerMap['student_name']]);
            $email         = trim($data[$headerMap['email']]);
            $phone_no      = trim($data[$headerMap['phone_no']]);

            // Check if upid already exists
            $check = $conn->prepare("SELECT 1 FROM students WHERE upid = ?");
            $check->bind_param("s", $upid);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $skipped++;
                continue; // Skip duplicate
            }

            $stmt = $conn->prepare("INSERT INTO students 
                (upid, program_type, program, course, class, reg_no, student_name, email, phone_no, applied_date, batch, year_of_passing)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");

            $stmt->bind_param("ssssssssssi",
                $upid, $program_type, $program, $course, $class,
                $reg_no, $student_name, $email, $phone_no,
                $batchYear, $yearOfPassing
            );

            $stmt->execute();
            $inserted++;
        }

        $_SESSION['import_message'] = "Import completed. Inserted: $inserted. Skipped duplicates: $skipped.";
        $_SESSION['import_status'] = "success";
    } catch (Exception $e) {
        $_SESSION['import_message'] = "Error during import: " . $e->getMessage();
        $_SESSION['import_status'] = "error";
    }

    header("Location: registered_students.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Registered Students</title>
</head>
<body>

  <?php include 'header.php'; ?>


    <div class="heading-container">
      <h2 class="headings">Registered Students</h2>
      <p>View the complete list of registered students with placement information.</p>
    </div>

    <div class="top-bar">
      <div class="left-controls">
        <div class="search-filter-btn-container" style="position: relative;">
          <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search (name, reg no, etc.)">
          <button type="button" id="filter-button" class="filter-button">
            <i class="fas fa-filter"></i> Filters
          </button>
          <a href="registered_students.php" class="reset-button">
            <i class="fa fa-times" aria-hidden="true"></i> Reset
          </a>

          <div id="filterDropdown" class="filter-dropdown">
            <form id="filterForm" onsubmit="applyFilters(); return false;">
              <label>Placement ID: <input type="text" name="upid"></label>
              <label>Program: <input type="text" name="program"></label>
              <label>Course: <input type="text" name="course"></label>

              <label>Class: <input type="text" name="class"></label>
              <label>Applied Date: <input type="date" name="applied_date"></label>
              <label>Batch:
                <select name="batch">
                  <option value="">All Batches</option>
                  <?php $batches->data_seek(0); while ($b = $batches->fetch_assoc()): ?>
                    <option value="<?= $b['batch'] ?>"><?= $b['batch'] ?></option>
                  <?php endwhile; ?>
                </select>
              </label>

              <label>Placed Status:
                <select name="placed_status">
                  <option value="">All Statuses</option>
                  <?php $placedStatuses->data_seek(0); while ($p = $placedStatuses->fetch_assoc()): ?>
                    <option value="<?= $p['placed_status'] ?>"><?= $p['placed_status'] ?></option>
                  <?php endwhile; ?>
                </select>
              </label>
              <label>Company: <input type="text" name="company_name"></label>
              <div class="filter-actions">
                <button type="submit">Apply Filter</button>
                <button type="button" class="clear-button" onclick="clearFilters()">Clear Filter</button>
                <button type="button" class="cancel-button" onclick="cancelFilters()">Cancel</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="right-controls">
        <form method="POST" enctype="multipart/form-data" class="import-form" onsubmit="return validateFilename()">
          <label for="csv_file" class="import-button">
            <i class="fa fa-download" aria-hidden="true" style="margin-right: 5px;"></i>Import File
          </label>
          <input type="file" id="csv_file" name="csv_file" accept=".csv,.xls,.xlsx" required style="display: none;" onchange="validateAndSubmit()">
        </form>
        <button type="button" id="exportBtn" onclick="exportTable()">
          <i class="fas fa-file-export"></i> Export File
        </button>
      </div>
    </div>

    <?php if (!empty($messageHtml)): ?>
      <div id="import-message-container">
        <?= $messageHtml ?>
      </div>
    <?php endif; ?>

    <div class="table-container">
      <table id="studentsTable">
        <thead>
          <tr>
            <th>Placement ID</th><th>Program Type</th><th>Program</th><th>Course</th><th>Class</th><th>Year of passing</th><th>Register No</th>
            <th>Student Name</th><th>Mail ID</th><th>Mobile No</th><th>Applied Date</th><th>Batch</th>
            <th>Placed Status</th><th>Company</th><th>Comment</th>
          </tr>
        </thead>
        <tbody id="tableBody">
          <!-- Loaded by AJAX -->
        </tbody>
      </table>
    </div>

<script>
function filterTable() {
  const keyword = document.getElementById("searchInput").value.toLowerCase();
  const rows = document.querySelectorAll("#tableBody tr");
  rows.forEach(row => {
    const cells = row.querySelectorAll("td");
    const text = Array.from(cells).map(td => td.textContent.toLowerCase()).join(" ");
    row.style.display = text.includes(keyword) ? "" : "none";
  });
}

function exportTable() {
  const rows = document.querySelectorAll("#studentsTable tr");
  let csv = "";
  rows.forEach(row => {
    if (row.style.display === "none") return;
    const cols = row.querySelectorAll("th, td");
    let data = Array.from(cols).map(c => `"${c.textContent.trim().replace(/"/g, '""')}"`);
    csv += data.join(",") + "\n";
  });
  const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = "registered_students.csv";
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

function applyFilters() {
  const form = document.getElementById("filterForm");
  const formData = new FormData(form);
  formData.append("ajax_filter", "1");

  fetch("registered_students.php", {
    method: "POST",
    body: formData
  })
  .then(res => res.text())
  .then(data => {
    document.getElementById("tableBody").innerHTML = data;
    document.getElementById("filterDropdown").style.display = "none"; // Only hide on Apply
  });
}

// Cancel just closes dropdown, no reset
function cancelFilters() {
  document.getElementById("filterDropdown").style.display = "none";
}

// Clear resets filter values, keeps dropdown open, and refreshes table
function clearFilters() {
  document.getElementById("filterForm").reset();

  const formData = new FormData();
  formData.append("ajax_filter", "1");

  fetch("registered_students.php", {
    method: "POST",
    body: formData
  })
  .then(res => res.text())
  .then(data => {
    document.getElementById("tableBody").innerHTML = data;
    // Don’t close the dropdown
  });
}

document.addEventListener("DOMContentLoaded", () => {
  const filterBtn = document.getElementById("filter-button");
  const filterDropdown = document.getElementById("filterDropdown");

  // Toggle dropdown on button click
  filterBtn.addEventListener("click", function (e) {
    e.stopPropagation();
    filterDropdown.style.display =
      filterDropdown.style.display === "block" ? "none" : "block";
  });

  // Hide dropdown when clicking outside
  document.addEventListener("click", function (e) {
    if (!filterDropdown.contains(e.target) && e.target !== filterBtn) {
      filterDropdown.style.display = "none";
    }
  });

  // Auto-load data when page loads
  applyFilters();
});

function validateAndSubmit() {
    const input = document.getElementById("csv_file");
    const file = input.files[0];
    if (!file) return;

    const filename = file.name;
    const pattern = /\d{4}-\d{4}/; // Matches "2022-2024"

    if (!pattern.test(filename)) {
    // Use a hidden form to trigger server-side error handling
    const form = input.form;
    const messageField = document.createElement("input");
    messageField.type = "hidden";
    messageField.name = "invalid_filename";
    messageField.value = "1";
    form.appendChild(messageField);
    form.submit();
    return false;
  }

    input.form.submit();
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


document.addEventListener("DOMContentLoaded", function () {
  const msgBox = document.getElementById("import-message-container");
  if (msgBox) {
    setTimeout(() => {
      msgBox.style.transition = "opacity 0.5s ease";
      msgBox.style.opacity = "0";
      setTimeout(() => msgBox.remove(), 500); // Remove it from DOM
    }, 4000); // 4 seconds
  }
});


</script>
<?php include("footer.php"); ?>

