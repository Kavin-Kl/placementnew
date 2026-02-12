<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index");
    exit;
}

include("config.php");

// === Helper to render student row ===
function render_student_row($row, $sl_no = '') {
    ob_start();
    ?>
    <tr data-student-id="<?= $row['student_id'] ?>" data-place-id="<?= $row['place_id'] ?>">
        <td class="sticky-col sticky-1">
            <input type="checkbox" class="student-checkbox" value="<?= $row['place_id'] ?>">
        </td>
        <td class="sl-no"><?= $sl_no ?></td>
        <td class="sticky-col sticky-2"><?= htmlspecialchars($row['upid']) ?></td>
        <td class="sticky-col sticky-3"><?= htmlspecialchars($row['reg_no']) ?></td>
        <td><?= htmlspecialchars($row['program_type']) ?></td>
        <td><?= htmlspecialchars($row['program']) ?></td>
        <td><?= htmlspecialchars($row['course']) ?></td>
        <td><?= htmlspecialchars($row['student_name']) ?></td>
        <td><?= htmlspecialchars($row['email']) ?></td>
        <td><?= htmlspecialchars($row['phone_no']) ?></td>
        <td><?= htmlspecialchars($row['percentage']) ?></td>
        <td><?= htmlspecialchars($row['offer_type']) ?></td>
        <td><?= htmlspecialchars($row['drive_no']) ?></td>
        <td><?= htmlspecialchars($row['company_name']) ?></td>
        <td><?= htmlspecialchars($row['role']) ?></td>
        <!-- <td style="display:none;"><?= htmlspecialchars($row['ctc']) ?></td> -->
        <!-- <td style="display:none;"><?= htmlspecialchars($row['stipend']) ?></td> -->
        <td><input type="text" class="edit_ctc" value="<?= htmlspecialchars($row['edit_ctc'] ?? '') ?>"></td>
        <td><input type="text" class="edit_stipend" value="<?= htmlspecialchars($row['edit_stipend'] ?? '') ?>"></td>

        <?php
        $fields = ['offer_letter_received', 'offer_letter_accepted', 'joining_status'];
        foreach ($fields as $field) {
            $val = $row[$field] ?? '';
            echo "<td><select class='{$field}'>";

            // Default option - only selected if no value saved in DB
           $selected = ($val === '' || $val === null || $val === 'unknown') ? 'selected' : '';
            echo "<option value='unknown' {$selected}>Select Option</option>";

            // Field-specific options
            $options = $field === 'joining_status'
                ? ['joined', 'not_joined']
                : ['yes', 'no'];

            foreach ($options as $option) {
                $selected = ($val === $option) ? 'selected' : '';
                echo "<option value='{$option}' {$selected}>" . ucfirst($option) . "</option>";
            }

            echo "</select></td>";
        }
        ?>
        <td><input type="text" class="joining_reason" value="<?= htmlspecialchars($row['joining_reason'] ?? '') ?>"></td>
        <td><?= htmlspecialchars($row['filled_on_off_form'] ?? 'not filled') ?></td>
        <td><?= htmlspecialchars($row['placement_batch'] ?? 'original') ?></td>

        <td>
            <button type="button" class="row-save-btn btn btn-sm btn-success" title="Save Changes" style="margin-right:3px; background-color:white; border:1px solid #198754; font-weight:700; color:#198754; border-radius:5px; padding:5px 12px; cursor:pointer;">Save</button>
            <div class="row-status-msg"></div>
        </td>
    </tr>
    <?php
    return ob_get_clean();
}



if (isset($_GET['ajax_row']) && isset($_GET['student_id'])) {
    include("config");
    $student_id = (int)$_GET['student_id'];
    $stmt = $conn->prepare("SELECT * FROM placed_students WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo render_student_row($row, ''); 
    }
    exit;
}


// === BULK UPDATE and single via JSON POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_SERVER['CONTENT_TYPE']) &&
    strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false
) {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents("php://input"), true);

    if (empty($input['place_ids']) || !is_array($input['place_ids'])) {
        echo json_encode(["success" => false, "message" => "No rows selected"]);
        exit;
    }

    $fields = [];
    $params = [];
    $types = '';

    // Allowed updatable fields
    $updatable = [
        'offer_letter_accepted',
        'offer_letter_received',
        'joining_status',
        'joining_reason',
        'edit_ctc',
        'edit_stipend'
    ];

    foreach ($updatable as $field) {
        if (array_key_exists($field, $input)) {
            $value = $input[$field];

            // Handle fields that can be NULL
            if (in_array($field, ['joining_reason', 'edit_ctc', 'edit_stipend'])) {
                if ($value === '' || $value === null) {
                    $fields[] = "$field = NULL";
                } else {
                    $fields[] = "$field = ?";
                    $params[] = $value;
                    $types .= 's';
                }
            } else {
                // Skip empty strings for dropdown-type fields
                if ($value !== '' && $value !== null) {
                    $fields[] = "$field = ?";
                    $params[] = $value;
                    $types .= 's';
                }
            }
        }
    }

    if (empty($fields)) {
        echo json_encode(["success" => false, "message" => "No fields selected for update"]);
        exit;
    }

    // Build the IN clause for place_ids
    $place_ids = $input['place_ids'];
    $placeholders = implode(',', array_fill(0, count($place_ids), '?'));

    $sql = "UPDATE placed_students 
            SET " . implode(', ', $fields) . " 
            WHERE place_id IN ($placeholders)";

    // Bind all params (fields + IDs)
    $params = array_merge($params, $place_ids);
    $types .= str_repeat('i', count($place_ids));

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["success" => false, "message" => "SQL prepare error: " . $conn->error]);
        exit;
    }

    // Bind dynamically
    $stmt->bind_param($types, ...$params);

    $success = $stmt->execute();

    echo json_encode([
        "success" => $success,
        "message" => $success
            ? "Updated " . $stmt->affected_rows . " record(s)"
            : "No rows updated"
    ]);
    exit;
}



// === Single Row Reload ===
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax_row'], $_GET['place_id'])) {
    $id = intval($_GET['place_id']);
    $stmt = $conn->prepare("SELECT * FROM placed_students WHERE place_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        header("Content-Type: text/html; charset=UTF-8");
        echo render_student_row($row);
    } else {
        http_response_code(404);
    }
    exit;
}

//auto update placed_students from sync_placed_students.php
// DISABLED: Import from Excel already has complete data
// include_once __DIR__ . '/sync_placed_students.php';
// sync_placed_students($conn);

//filter ajax
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_filter']) && $_POST['ajax_filter'] === "1") {
    $where = [];
    $params = [];
    $types = '';

    // Add academic year filter (from header.php year selector)
    if (isset($_SESSION['selected_academic_year'])) {
        $parts = explode('-', $_SESSION['selected_academic_year']);
        $graduation_year = isset($parts[1]) ? intval($parts[1]) : null;
        if ($graduation_year) {
            $where[] = "s.year_of_passing = ?";
            $params[] = $graduation_year;
            $types .= "i";
        }
    }

    $exactMatchFields = [
        'upid', 'program_type', 'reg_no', 'percentage', 'offer_type', 'drive_no', 'company_name', 'role', 'edit_ctc', 'edit_stipend',
        'filled_on_off_form', 'offer_letter_received', 'offer_letter_accepted',
        'joining_status', 'joining_reason', 'placement_batch'
    ];

    $allFields = [
        'upid', 'program_type', 'program', 'course', 'reg_no', 'percentage', 'drive_no', 'offer_type',
        'company_name', 'role', 'edit_ctc', 'edit_stipend', 'filled_on_off_form',
        'offer_letter_received', 'offer_letter_accepted',
        'joining_status', 'joining_reason', 'placement_batch'
    ];

    foreach ($allFields as $field) {
        if ($field === 'course') {
            // Handle multi-select course
            if (!empty($_POST['course']) && is_array($_POST['course'])) {
                $placeholders = [];
                foreach ($_POST['course'] as $course) {
                    $params[] = $course;
                    $placeholders[] = '?';
                    $types .= 's';
                }
                $where[] = "course IN (" . implode(',', $placeholders) . ")";
            }
        } elseif (isset($_POST[$field]) && $_POST[$field] !== '') {
    if (in_array($field, $exactMatchFields) && !in_array($field, ['edit_ctc', 'edit_stipend'])) {
        $where[] = "$field = ?";
        $params[] = $_POST[$field];
    } else {
        $where[] = "$field LIKE ?";
        $params[] = "%" . $_POST[$field] . "%";
    }
    $types .= 's';
}
    }

    // 🔹 Add Percentage Filter (new)
    if (!empty($_POST['min_percentage'])) {
        $where[] = "percentage >= ?";
        $params[] = (float) $_POST['min_percentage'];
        $types .= 'd'; // 'd' for double/float
    }

    if (!empty($_POST['max_percentage'])) {
        $where[] = "percentage <= ?";
        $params[] = (float) $_POST['max_percentage'];
        $types .= 'd';
    }

    $sql = "SELECT ps.* FROM placed_students ps
            INNER JOIN students s ON ps.student_id = s.student_id
            WHERE s.vantage_placed = 'yes'";
    if (!empty($where)) {
        $sql .= " AND " . implode(" AND ", $where);
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }

    $res = $stmt->get_result();
    if (!$res) {
        die("Get result failed: " . $stmt->error);
    }

    echo "<tbody id='tableBody'>";
    $sl_no = 1;
    while ($row = $res->fetch_assoc()) {
        echo render_student_row($row, $sl_no++);
    }
    echo "</tbody>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <?php include 'header.php'; ?>
</head>
<style>
  .percentage_column {
  display: flex;
  align-items: center;       /* vertically centers items */
  justify-content: space-between;
  gap: 8px;                  /* space between items */
  width: 100%;
}

</style>
<body>
  <!-- Heading Section -->
<div class="heading-container">
  <h2 class="headings">Vantage Placed Students</h2>
  <p style="margin-bottom: 10px;">View the complete list of vantage placed students with placement information.</p>
</div>

<!-- TOP Bar -->
<div class="top-bar">
  <!-- LEFT CONTROLS -->
  <div class="left-controls">
    <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search... (name, reg no, etc.)">

    <button type="button" id="filter-button" class="filter-button">
      <i class="fas fa-filter"></i> Filters
    </button>

    <a href="vantage_placed_students" class="reset-button">
      <i class="fa fa-undo" aria-hidden="true"></i> <span>Reset</span>
    </a>
  </div>

  <!-- RIGHT CONTROLS -->
  <div class="right-controls">
    <div class="export-import-container">
      <button type="button" id="openImportPopup" class="import-button">
        <i class="fa fa-download" aria-hidden="true" style="margin-right: 5px;"></i> Import File
      </button>
      <button type="button" id="exportBtn">
        <i class="fas fa-file-export"></i> Export File
      </button>
    </div>
  </div>
</div>

<!-- Import Popup Modal -->
<div id="ipt_importPopup" class="ipt_modal">
  <div class="ipt_modal-content">
    <span class="ipt_close-btn">&times;</span>
    <h5>Select Import Option</h5>

    <form method="POST" enctype="multipart/form-data" class="import-form">
      <label for="csv_file_placed" class="ipt_import-option">
        <i class="fa fa-download"></i> Import Excel File
      </label>
      <input type="file" id="csv_file_placed" name="csv_file_placed" accept=".csv,.xls,.xlsx" required style="display:none;" onchange="this.form.submit()">
    </form>
  </div>
</div>

<!-- FILTER MODAL -->
<div id="filterModal" class="filter-modal">
<div class="modal-content" style="min-height: 70vh; max-height: 90vh; overflow-y: auto;">

    <span class="close" onclick="closeFilterModal()">&times;</span>
    <h5>Filters</h5>
    <form id="filterForm" method="POST" onsubmit="event.preventDefault(); applyFilters();">
      <div class="filter-grid1">
        <label>Placement ID: <input type="text" name="upid"></label>
        <label>Program Type:
          <select name="program_type">
            <option value="">All options</option>
            <?php
              $programTypes = $conn->query("SELECT DISTINCT program_type FROM placed_students ORDER BY program_type ASC");
              while ($pt = $programTypes->fetch_assoc()):
                $val = htmlspecialchars($pt['program_type']);
                $selected = (isset($_GET['program_type']) && $_GET['program_type'] === $val) ? 'selected' : '';
                echo "<option value=\"$val\" $selected>$val</option>";
              endwhile;
            ?>
          </select>
        </label>
        <label>Program: <input type="text" name="program"></label>
        <label>Reg No: <input type="text" name="reg_no"></label>
        <label>Course:
          <select name="course[]" id="course-multiselect" multiple="multiple" style="width: 100%;">
            <?php
            $courses = $conn->query("SELECT DISTINCT course FROM placed_students WHERE course IS NOT NULL AND course != '' ORDER BY course ASC");
            while ($row = $courses->fetch_assoc()):
            ?>
              <option value="<?= htmlspecialchars($row['course']) ?>">
                <?= htmlspecialchars($row['course']) ?>
              </option>
            <?php endwhile; ?>
          </select>
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
        <label>Offer Type:
          <select name="offer_type">
            <option value="">All options</option>
            <option value="FTE" <?= (isset($_GET['offer_type']) && $_GET['offer_type'] === 'FTE') ? 'selected' : '' ?>>FTE</option>
            <option value="Internship" <?= (isset($_GET['offer_type']) && $_GET['offer_type'] === 'Internship') ? 'selected' : '' ?>>Internship</option>
            <option value="Apprentice" <?= (isset($_GET['offer_type']) && $_GET['offer_type'] === 'Apprentice') ? 'selected' : '' ?>>Apprentice</option>
            <option value="Internship + PPO" <?= (isset($_GET['offer_type']) && $_GET['offer_type'] === 'Internship + PPO') ? 'selected' : '' ?>>Internship + PPO</option>
          </select>
        </label>
        <label>Company Drive No:
          <select name="drive_no">
            <option value="">All options</option>
            <?php
            $driveNos = $conn->query("SELECT DISTINCT drive_no FROM placed_students ORDER BY drive_no ASC");
            while ($d = $driveNos->fetch_assoc()):
                $val = htmlspecialchars($d['drive_no']);
                $selected = (isset($_GET['drive_no']) && $_GET['drive_no'] === $val) ? 'selected' : '';
                echo "<option value=\"$val\" $selected>$val</option>";
            endwhile;
            ?>
          </select>
        </label>
        <label>Company:
          <select name="company_name" id="company_name" size="5" style="width:100%;">
            <option value="">All options</option>
            <?php
            $companies = $conn->query("SELECT DISTINCT company_name FROM placed_students ORDER BY company_name ASC") 
                        or die("SQL Error: " . $conn->error);
            while ($c = $companies->fetch_assoc()):
                $val = htmlspecialchars($c['company_name']);
                $selected = (isset($_GET['company_name']) && $_GET['company_name'] === $val) ? 'selected' : '';
                echo "<option value=\"$val\" $selected>$val</option>";
            endwhile;
            ?>
          </select>
        </label>
        <label>Role: <input type="text" name="role"></label>
        <label>CTC: <input type="text" name="edit_ctc"></label>
        <label>Stipend: <input type="text" name="edit_stipend"></label>
        <label>Offer letter collection Form:
          <select name="filled_on_off_form">
            <option value="">All options</option>
            <option value="filled">Filled</option>
            <option value="not filled">Not Filled</option>
          </select>
        </label>
        <label>Offer Received:
          <select name="offer_letter_received">
            <option value="">All options</option>
            <option value="yes">Yes</option>
            <option value="no">No</option>
          </select>
        </label>
        <label>Offer Accepted:
          <select name="offer_letter_accepted">
            <option value="">All options</option>
            <option value="yes">Yes</option>
            <option value="no">No</option>
          </select>
        </label>
        <label>Joining Status:
          <select name="joining_status">
            <option value="">All options</option>
            <option value="joined">Joined</option>
            <option value="not_joined">Not Joined</option>
          </select>
        </label>
        <label>Application Type:
          <select name="placement_batch">
            <option value="">All options</option>
            <option value="original">Original</option>
            <option value="reapplied">Reapplied</option>
          </select>
        </label>
        <label>Comments: <input type="text" name="joining_reason"></label>
      </div>

      <div class="filter-actions">
        <button type="submit">Apply Filters</button>
        <button type="button" class="clear-button" onclick="clearFilters()">Clear Filters</button>
      </div>
    </form>
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
            <label><input type="checkbox"> Mail ID</label>
            <label><input type="checkbox"> Mobile No</label>
            <label><input type="checkbox"> Job Offer Type</label>
            <label><input type="checkbox"> Company Drive No</label>
            <label><input type="checkbox"> Company</label>
            <label><input type="checkbox"> Designation</label>
            <label><input type="checkbox"> Company CTC</label>
            <label><input type="checkbox"> Company Stipend</label>
            <label><input type="checkbox"> Offer Letter Received</label>
            <label><input type="checkbox"> Offer Letter Accepted</label>
            <label><input type="checkbox"> Joining Status</label>
            <label><input type="checkbox"> Comments</label>
            <label><input type="checkbox"> Offer letter collection Form</label>
            <label><input type="checkbox"> Application Type</label>
          </div>

          <div class="export-actions">
            <button type="submit" class="download-button" onclick="exportTable()">Export selected fields</button>
          </div>
        </form>
      </div>
    </div>

    <!-- /Export Modal -->

<hr>
<p style="margin-bottom: 10px; font-size: 14px;">Select the below check boxes for the bulk update</p>

<div id="bulk-message" style="margin-top:10px;"></div>
  <div class="bulk-update-row">

  <select id="bulk_offer_received">
    <option value="">--- Offer Received ---</option>
    <option value="yes">Yes</option>
    <option value="no">No</option>
  </select>

  <select id="bulk_offer_accepted">
    <option value="">--- Offer Accepted ---</option>
    <option value="yes">Yes</option>
    <option value="no">No</option>
  </select>

  <select id="bulk_joining_status">
    <option value="">--- Joining Status ---</option>
    <option value="joined">Joined</option>
    <option value="not_joined">Not Joined</option>
  </select>

  <input type="text" id="bulk_joining_reason" placeholder="Comments">
  
  <button onclick="applyBulkUpdate()" class="bulksave-button">Save Selected Rows</button>
</div>

<div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
  <table class="table table-bordered table-striped custom-table sticky-table" id="placed_studentsTable">
    <thead>
      <tr>
        <th class="sticky-col sticky-1"><input type="checkbox" onclick="toggleAll(this)"></th>
        <th>Sl No</th>
        <th class="sticky-col sticky-2">Placement ID</th>
        <th class="sticky-col sticky-3">Register No</th>
        <th>Program Type</th>
        <th>Program</th>
        <th>Course</th>
        <th>Student Name</th>
        <th>Mail ID</th>
        <th>Mobile No</th>
        <th>Percentage</th>
        <th>Job Offer Type</th>
        <th>Company Drive No</th>
        <th>Company Name</th>
        <th>Designation</th>
        <!-- <th style="display:none;">CTC</th>-->
        <!-- <th style="display:none;">Stipend</th>-->
        <th>Company CTC</th>
        <th>Company Stipend</th>
        <th>Offer Letter Received</th>
        <th>Offer Letter Accepted</th>
        <th>Joining Status</th>
        <th>Comments</th>
        <th>Offer letter collection Form</th>
        <th>Application Type</th>
        <th></th>
      </tr>
    </thead>
    <tbody id="tableBody">
      <?php
      $defaultQuery = $conn->query("SELECT ps.* FROM placed_students ps INNER JOIN students s ON ps.student_id = s.student_id WHERE s.vantage_placed = 'yes' ORDER BY ps.place_id DESC");
      $sl_no = 1;
      while ($row = $defaultQuery->fetch_assoc()) {
          echo render_student_row($row, $sl_no++);
      }
      ?>
</tbody>


  </table>
</div>


<script>
document.addEventListener("DOMContentLoaded", () => {
  const filterBtn = document.getElementById("filter-button");
  const filterModal = document.getElementById("filterModal");
  const closeBtn = document.querySelector(".modal-content .close");
  const tableBody = document.getElementById("tableBody");

  // Show modal
  filterBtn?.addEventListener("click", (e) => {
    e.preventDefault();
    filterModal.style.display = "flex";
  });

  // Close modal on close button
  closeBtn?.addEventListener("click", () => {
    filterModal.style.display = "none";
  });

  // Close modal on outside click
  window.addEventListener("click", (e) => {
    if (e.target === filterModal) {
      filterModal.style.display = "none";
    }
  });

  // Import popup modal handling
  const openPopup = document.getElementById("openImportPopup");
  const popup = document.getElementById("ipt_importPopup");
  const closePopup = document.querySelector(".ipt_close-btn");

  // Open import modal
  openPopup?.addEventListener("click", (e) => {
    e.stopPropagation();
    popup.style.display = "flex";
  });

  // Close import modal when clicking outside
  window.addEventListener("click", (e) => {
    if (e.target === popup) {
      popup.style.display = "none";
    }
  });

  // Close import modal on X click
  closePopup?.addEventListener("click", () => {
    popup.style.display = "none";
  });

  // Trigger file input when clicking the label
  document.querySelector('label[for="csv_file_placed"]')?.addEventListener("click", () => {
    document.getElementById("csv_file_placed").click();
  });
});

//clear filters
function clearFilters() {
  // First clear all Select2s visually
  if (window.$) {
    if ($("#course-multiselect").length) {
      $("#course-multiselect").val(null).trigger("change");
    }
    if ($("#company_name").length) {
      $("#company_name").val(null).trigger("change");
    }
  }

  // Then reset the form (so hidden inputs etc. also clear)
  document.getElementById("filterForm").reset();

  // Restore course dropdown to all courses
  if (typeof populateCourses === "function" && window.allCourses) {
    populateCourses(window.allCourses);
  }

}


// Apply filters via AJAX GET request
function applyFilters() {
  const form = document.getElementById("filterForm");
  const formData = new FormData(form);
  formData.append("ajax_filter", "1");

  fetch("vantage_placed_students", {
    method: "POST",
    body: formData
  })
  .then(res => res.text())
  .then(html => {
    const tableBody = document.querySelector("#placed_studentsTable tbody");
    tableBody.innerHTML = html;
    document.getElementById("filterModal").style.display = "none";
    recalculateSerialNumbers();
    attachRowSaveEvent(); // Re-attach save events after filtering
  })
  .catch(err => {
    console.error("Filter error:", err);
    showGlobalMessage("Failed to apply filters: " + err.message, false);
  });
}

// Live search/filter within the table by keyword input
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

// exportTable function to export selected columns
document.addEventListener("DOMContentLoaded", () => {
  const exportBtn = document.getElementById("exportBtn");
  const exportModal = document.getElementById("exportModal");
  const closeBtn = exportModal.querySelector(".export-content .close");
  const grid = document.getElementById("exportGrid"); // Grid container for checkboxes
  const table = document.getElementById("placed_studentsTable");

  // Open export modal
  exportBtn.addEventListener("click", (e) => {
    e.stopPropagation();
    grid.innerHTML = ""; // Clear only the checkbox grid

    // Create "Select All" checkbox
    const selectAllLabel = document.createElement("label");
    const selectAllCheckbox = document.createElement("input");
    selectAllCheckbox.type = "checkbox";
    selectAllCheckbox.id = "selectAllCols";
    selectAllCheckbox.checked = false;
    selectAllLabel.appendChild(selectAllCheckbox);
    const boldText = document.createElement("strong");
    boldText.textContent = " Select All Fields";
    selectAllLabel.appendChild(boldText);
    grid.appendChild(selectAllLabel);

    // Generate checkboxes from table headers
    if (table) {
      const headers = table.querySelectorAll("thead th");

      headers.forEach((th, index) => {
        const headerText = th.innerText.trim();

        // Skip unwanted headers: empty, Sl No, Action
        if (!headerText || headerText === "Sl No" || headerText === "Action") return;

        const label = document.createElement("label");
        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.className = "col-checkbox";
        checkbox.value = index;
        checkbox.checked = false;
        label.appendChild(checkbox);
        label.appendChild(document.createTextNode(" " + headerText));
        grid.appendChild(label);
      });
    }

    // Toggle all checkboxes when "Select All" is clicked
    selectAllCheckbox.addEventListener("change", () => {
      const colCheckboxes = grid.querySelectorAll(".col-checkbox");
      colCheckboxes.forEach(cb => cb.checked = selectAllCheckbox.checked);
    });

    // Update "Select All" if any checkbox is changed
    grid.querySelectorAll(".col-checkbox").forEach(cb => {
      cb.addEventListener("change", () => {
        const colCheckboxes = grid.querySelectorAll(".col-checkbox");
        selectAllCheckbox.checked = Array.from(colCheckboxes).every(cb => cb.checked);
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


// Export table to Excel
function exportTable() {
  const table = document.getElementById("placed_studentsTable");
  const clonedTable = table.cloneNode(true);

  // Remove hidden rows
  Array.from(clonedTable.rows).forEach(row => {
    if (row.style.display === "none") row.remove();
  });

  // Get selected columns (checkboxes in export modal)
  const selectedCols = Array.from(document.querySelectorAll("#exportForm .col-checkbox:checked"))
    .map(input => parseInt(input.value));

  // If no column selected, stop
  if (selectedCols.length === 0) {
    alert("Please select at least one column to export.");
    return;
  }

  // Process each row
  Array.from(clonedTable.rows).forEach((row, rowIndex) => {
    const totalCols = row.cells.length;
    for (let i = totalCols - 1; i >= 0; i--) {
      // Always remove first column (checkbox) and last column (Action)
      if (i === 0 || i === totalCols - 1) {
        row.deleteCell(i);
        continue;
      }

      // Remove unchecked columns
      if (!selectedCols.includes(i)) {
        row.deleteCell(i);
      } else {
        const cell = row.cells[i];
        // Convert form fields to plain text
        const select = cell.querySelector("select");
        if (select) {
          cell.textContent = select.options[select.selectedIndex]?.text || "";
        }
        const textarea = cell.querySelector("textarea");
        if (textarea) {
          cell.textContent = textarea.value.trim();
        }
        const input = cell.querySelector("input");
        if (input) {
          cell.textContent = input.value.trim();
        }
      }
    }

    // Insert Sl No column at the beginning
    const slCell = row.insertCell(0);
    if (rowIndex === 0) {
      slCell.textContent = "Sl No"; // header row
    } else {
      slCell.textContent = rowIndex; // numbering starts from 1
    }
  });

  if (!clonedTable.rows.length || !clonedTable.rows[0].cells.length) {
    alert("No data available to export.");
    return;
  }

  // Export to Excel
  const workbook = XLSX.utils.book_new();
  const worksheet = XLSX.utils.table_to_sheet(clonedTable);
  XLSX.utils.book_append_sheet(workbook, worksheet, "Students");
  XLSX.writeFile(workbook, "placed_students.xlsx");

  // Close export modal
  closeExportModal();
}


function closeExportModal() {
  document.getElementById("exportModal").style.display = "none";
}


// check box toggle
function toggleAll(source) {
  document.querySelectorAll(".student-checkbox").forEach(cb => cb.checked = source.checked);
}

// for bulk update
function applyBulkUpdate() {
  const selectedIds = Array.from(document.querySelectorAll(".student-checkbox:checked"))
    .map(cb => cb.closest("tr")?.dataset.placeId)
    .filter(id => id); // filter out undefined

  if (selectedIds.length === 0) {
    showGlobalMessage("Please select at least one student.", false);
    return;
  }

  const offerAccepted = document.getElementById("bulk_offer_accepted")?.value.trim();
  const offerReceived = document.getElementById("bulk_offer_received")?.value.trim();
  const joiningStatus = document.getElementById("bulk_joining_status")?.value.trim();
  const joiningReason = document.getElementById("bulk_joining_reason")?.value.trim();

  const data = { place_ids: selectedIds };

  if (offerAccepted) data.offer_letter_accepted = offerAccepted;
  if (offerReceived) data.offer_letter_received = offerReceived;
  if (joiningStatus) data.joining_status = joiningStatus;
  if (joiningReason) data.joining_reason = joiningReason;


  fetch("vantage_placed_students", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  })
    .then(res => res.ok ? res.json() : Promise.reject(new Error("Failed to update students.")))
    .then(response => {
      showGlobalMessage(response.message, response.success);

      if (response.success) {
        document.querySelectorAll(".student-checkbox").forEach(cb => cb.checked = false);
        const selectAll = document.querySelector('thead input[type="checkbox"]');
        if (selectAll) selectAll.checked = false;

        // Clear input fields
        document.getElementById("bulk_offer_accepted").value = "";
        document.getElementById("bulk_offer_received").value = "";
        document.getElementById("bulk_joining_status").value = "";
        document.getElementById("bulk_joining_reason").value = "";

        const promises = selectedIds.map(id => {
          return fetch(`vantage_placed_students?ajax_row=1&place_id=${encodeURIComponent(id)}&t=${Date.now()}`)
            .then(r => r.ok ? r.text() : Promise.reject(new Error("Failed to fetch updated row.")))
            .then(html => ({ id, html }));
        });

        Promise.all(promises).then(rows => {
          rows.forEach(({ id, html }) => {
            const temp = document.createElement("tbody");
            temp.innerHTML = html;
            const newRow = temp.querySelector("tr");
            const oldRow = document.querySelector(`tr[data-place-id="${id}"]`);
            if (newRow && oldRow) {
              oldRow.replaceWith(newRow);
            }
          });

          // Re-number SL numbers
          const allRows = Array.from(document.querySelectorAll("tbody tr"));
          allRows.forEach((row, index) => {
            const slCell = row.querySelector(".sl-no");
            if (slCell) {
              slCell.textContent = index + 1;
            }
          });

          attachRowSaveEvent(); // Re-bind Save button events
        }).catch(err => {
          console.error("Bulk row update error:", err);
          showGlobalMessage("Error updating rows: " + err.message, false);
        });
      }
    })
    .catch(err => {
      console.error("AJAX Error:", err);
      showGlobalMessage("AJAX error occurred: " + err.message, false);
    });
}


// for single row update
function attachRowSaveEvent() {
  document.querySelectorAll(".row-save-btn").forEach(button => {
    button.addEventListener("click", () => {
      const row = button.closest("tr");
      const placeId = row.dataset.placeId;
      const edit_ctc = row.querySelector(".edit_ctc")?.value || "";
      const edit_stipend = row.querySelector(".edit_stipend")?.value || "";
      const data = {
        place_ids: [placeId],
        offer_letter_accepted: row.querySelector(".offer_letter_accepted").value,
        offer_letter_received: row.querySelector(".offer_letter_received").value,
        joining_status: row.querySelector(".joining_status").value,
        joining_reason: row.querySelector(".joining_reason").value,
        edit_ctc: edit_ctc,
        edit_stipend: edit_stipend
      };

      fetch("vantage_placed_students", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
      })
      .then(res => res.json())
      .then(response => {
        showGlobalMessage(response.message, response.success);

        if (response.success) {
          fetch(`vantage_placed_students?ajax_row=1&place_id=${encodeURIComponent(placeId)}&t=${Date.now()}`)
            .then(r => r.text())
            .then(html => {
              const temp = document.createElement("tbody");
              temp.innerHTML = html;
              const newRow = temp.querySelector("tr");
              if (newRow) {
                row.replaceWith(newRow);
                attachRowSaveEvent();       // Re-bind event after replacing row
                recalculateSerialNumbers(); // Recalculate serial numbers
              }
            });
        }
      })
      .catch(err => {
        showGlobalMessage("AJAX error: " + err.message, false);
      });
    });
  });
}


// Clean, ID-free Sl No recalculation
function recalculateSerialNumbers() {
  const rows = document.querySelectorAll("#placed_studentsTable tbody tr");
  rows.forEach((row, index) => {
    const slNoCell = row.querySelector("td:nth-child(2)");
    if (slNoCell) {
      slNoCell.textContent = index + 1;
    }
  });
}

// message/statement display
function showGlobalMessage(text, success = true) {
  const messageDiv = document.getElementById("bulk-message");
  messageDiv.innerText = text;
  messageDiv.style.display = "block";
  messageDiv.style.backgroundColor = success ? "#d1fae5" : "#fee2e2";
  messageDiv.style.color = success ? "#065f46" : "#991b1b";
  messageDiv.style.border = success ? "1px solid #6ee7b7" : "1px solid #fca5a5";
  messageDiv.style.padding = "3px 3px";
  messageDiv.style.borderRadius = "4px";
  messageDiv.style.margin = "10px 0";
  messageDiv.style.textAlign = "center";
  messageDiv.style.whiteSpace = "nowrap";
  messageDiv.style.overflow = "hidden";
  messageDiv.style.textOverflow = "ellipsis";

  // Optional: hide after 5 seconds
  setTimeout(() => {
    messageDiv.style.display = "none";
  }, 2000);
}
attachRowSaveEvent();

// table sticky
function setStickyOffsets() {
  const header = document.querySelector(".sticky-table thead");
  if (!header) return;

  const stickyClasses = ['sticky-1', 'sticky-2', 'sticky-3'];
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


// Initialize Select2 for multi-select
$(document).ready(function() {
    $('#course-multiselect').select2({
      placeholder: "Select course(s)",
      allowClear: true
    });
  });
$(document).ready(function() {
    $('#company_name').select2({
      placeholder: "Select a company",
    });
  });

</script>

<?php include("footer.php"); ?>
</body>
</html>