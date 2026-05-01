<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}
include("config.php");
include("course_groups_dynamic.php");

// Save handler — only writes the editable fields owned by this page (min_ctc, max_ctc).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_company_data_row'])) {
    $id      = intval($_POST['row_id'] ?? 0);
    $min_ctc = $_POST['min_ctc'] ?? '';
    $max_ctc = $_POST['max_ctc'] ?? '';

    $stmt = $conn->prepare("UPDATE drive_data SET min_ctc = ?, max_ctc = ? WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
        exit;
    }
    $stmt->bind_param("ssi", $min_ctc, $max_ctc, $id);
    $ok = $stmt->execute();

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['status' => $ok ? 'success' : 'error']);
        exit;
    }
}

$academic_year = $_SESSION['selected_academic_year'] ?? null;

// Match the full-time tracker's offer_type set so this page reflects the same companies.
$ay_clause = $academic_year ? "AND drv.academic_year = '" . $conn->real_escape_string($academic_year) . "'" : "";

$sql = "
SELECT
  d.id,
  d.company_name,
  d.drive_no AS drive_number,
  d.role,
  d.offer_type,
  d.final_status,
  d.min_ctc,
  d.max_ctc,
  d.eligible_courses,
  drv.company_sector,
  dr.sector AS role_sector
FROM drive_data d
INNER JOIN drives drv ON d.drive_id = drv.drive_id
LEFT JOIN drive_roles dr ON drv.drive_id = dr.drive_id AND TRIM(d.role) = TRIM(dr.designation_name)
WHERE d.offer_type IN ('FTE', 'Apprenticeship (Full time)', 'Internship + PPO', 'Internship+PPO', 'Internship + PPO (Final Year)')
  $ay_clause
ORDER BY d.company_name ASC, d.id ASC
";

$result = $conn->query($sql);

// Build a fast lookup of course_name -> school.
$courseSchoolMap = [];
$cs = $conn->query("SELECT course_name, school FROM courses WHERE school IS NOT NULL");
if ($cs) {
    while ($row = $cs->fetch_assoc()) {
        $courseSchoolMap[strtolower(trim($row['course_name']))] = $row['school'];
    }
}

function formatCoursesPretty(array $courses): string {
    global $UG_COURSES, $PG_COURSES;

    $clean = array_values(array_filter(array_map('trim', $courses), fn($v) => $v !== '' && strtolower($v) !== 'on'));
    if (empty($clean)) return '';

    $lower = array_map('strtolower', $clean);
    $ugLower = is_array($UG_COURSES ?? null) ? array_map('strtolower', $UG_COURSES) : [];
    $pgLower = is_array($PG_COURSES ?? null) ? array_map('strtolower', $PG_COURSES) : [];

    $hasAllUG = (bool) array_intersect($lower, ['all ug', 'all ug courses']);
    $hasAllPG = (bool) array_intersect($lower, ['all pg', 'all pg courses']);

    // Auto-detect: if every UG course is individually present, treat as "All UG".
    if (!$hasAllUG && !empty($ugLower) && count(array_intersect($ugLower, $lower)) === count($ugLower)) {
        $hasAllUG = true;
    }
    if (!$hasAllPG && !empty($pgLower) && count(array_intersect($pgLower, $lower)) === count($pgLower)) {
        $hasAllPG = true;
    }

    $display = [];
    if ($hasAllUG) $display[] = 'All UG Courses';
    if ($hasAllPG) $display[] = 'All PG Courses';

    foreach ($clean as $i => $c) {
        $lc = $lower[$i];
        if (in_array($lc, ['all ug', 'all pg', 'all ug courses', 'all pg courses'])) continue;
        if ($hasAllUG && in_array($lc, $ugLower)) continue;
        if ($hasAllPG && in_array($lc, $pgLower)) continue;
        $display[] = $c;
    }

    return implode(', ', $display);
}

function deriveSchools(array $courses, array $map): string {
    $found = [];
    foreach ($courses as $c) {
        $key = strtolower(trim($c));
        if (isset($map[$key])) {
            $found[$map[$key]] = true;
        }
    }
    return implode(', ', array_keys($found));
}

// Materialize rows so we can filter, export, and render from the same set.
$rows = [];
if ($result) {
    while ($r = $result->fetch_assoc()) {
        $courses = json_decode($r['eligible_courses'] ?? '[]', true);
        if (!is_array($courses)) $courses = [];
        $r['_courses_arr'] = $courses;
        $r['_courses_str'] = formatCoursesPretty($courses);
        $r['_school_str']  = deriveSchools($courses, $courseSchoolMap);
        $rows[] = $r;
    }
}

// Distinct option lists for filter dropdowns.
$companySectors = array_values(array_unique(array_filter(array_map(fn($r)=>trim((string)($r['company_sector']??'')), $rows))));
$roleSectors    = array_values(array_unique(array_filter(array_map(fn($r)=>trim((string)($r['role_sector']??'')),    $rows))));
$offerTypes     = array_values(array_unique(array_filter(array_map(fn($r)=>trim((string)($r['offer_type']??'')),     $rows))));
$finalStatuses  = array_values(array_unique(array_filter(array_map(fn($r)=>trim((string)($r['final_status']??'')),   $rows))));
$driveNos       = array_values(array_unique(array_filter(array_map(fn($r)=>trim((string)($r['drive_number']??'')),   $rows))));
sort($companySectors); sort($roleSectors); sort($offerTypes); sort($finalStatuses); sort($driveNos);

// Apply filters (GET).
$filter = $_GET;
$filtered = array_values(array_filter($rows, function($r) use ($filter) {
    if (!empty($filter['company_name']) && stripos((string)$r['company_name'], $filter['company_name']) === false) return false;
    if (!empty($filter['role'])         && stripos((string)$r['role'],         $filter['role'])         === false) return false;
    foreach (['company_sector','role_sector','offer_type','final_status','drive_number'] as $f) {
        if (!empty($filter[$f]) && (string)($r[$f] ?? '') !== (string)$filter[$f]) return false;
    }
    if (!empty($filter['min_ctc_min']) && is_numeric($filter['min_ctc_min']) && (float)($r['min_ctc'] ?? 0) < (float)$filter['min_ctc_min']) return false;
    if (!empty($filter['max_ctc_max']) && is_numeric($filter['max_ctc_max']) && (float)($r['max_ctc'] ?? 0) > (float)$filter['max_ctc_max']) return false;
    if (!empty($filter['course'])) {
        $needle = strtolower(trim($filter['course']));
        $hay = strtolower($r['_courses_str']);
        if ($needle !== '' && strpos($hay, $needle) === false) return false;
    }
    return true;
}));

// XLSX export of currently-filtered rows.
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    require __DIR__ . '/vendor/autoload.php';
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $headerRow = ['Sl No','Company Name','Sector of Company','Drive Number','Role','Sector of Role','Offer Type','Final Status','Min CTC','Max CTC','Courses','School'];
    $sheet->fromArray($headerRow, null, 'A1');
    $rowNum = 2; $sl = 1;
    foreach ($filtered as $r) {
        $sheet->fromArray([
            $sl++,
            $r['company_name'] ?? '',
            $r['company_sector'] ?? '',
            $r['drive_number'] ?? '',
            $r['role'] ?? '',
            $r['role_sector'] ?? '',
            $r['offer_type'] ?? '',
            $r['final_status'] ?? '',
            $r['min_ctc'] ?? '',
            $r['max_ctc'] ?? '',
            $r['_courses_str'] ?? '',
            $r['_school_str'] ?? '',
        ], null, 'A' . $rowNum++);
    }
    foreach (range('A','L') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
    $sheet->freezePane('A2');
    $filename = 'FullTime_Company_Data_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
    exit;
}

include("header.php");

$page_title = "Full Time Company Data";
?>

<style>
/* Horizontal scroll container — overrides any parent overflow:hidden. */
.cd-scroll-wrap {
    overflow-x: auto !important;
    overflow-y: auto;
    max-height: 70vh;
    width: 100%;
    -webkit-overflow-scrolling: touch;
}
.company-data-table {
    font-size: 13px;
    min-width: 1900px;       /* force the table to be wider than viewport so scrollbar appears */
    margin-bottom: 0;
}
.company-data-table th,
.company-data-table td {
    white-space: nowrap;     /* short fields stay on one line */
    vertical-align: middle;
    padding: 8px 10px;
}
.company-data-table th {
    background:#650000; color:#fff; text-align:center;
    position: sticky; top: 0; z-index: 2;
}
/* Long-content columns: allow wrapping with bounded width. */
.company-data-table th.col-courses,
.company-data-table td.col-courses {
    white-space: normal;
    min-width: 320px;
    max-width: 480px;
    word-break: break-word;
}
.company-data-table th.col-school,
.company-data-table td.col-school {
    white-space: normal;
    min-width: 220px;
    max-width: 280px;
    word-break: break-word;
}
.company-data-table input.ctc-input {
    width: 110px; padding: 4px 6px; border:1px solid #ccc; border-radius:4px;
}
.fcd-status-pill {
    display:inline-block; padding:3px 10px; border-radius:12px; font-size:12px;
    background:#f0f0f0;
}
.fcd-save-btn {
    background:#fff; border:1px solid #198754; color:#198754; font-weight:600;
    border-radius:4px; padding:4px 10px; cursor:pointer;
}
.fcd-save-btn:hover { background:#198754; color:#fff; }
.fcd-saved { color:#198754; font-size:12px; margin-left:6px; }

/* Top toolbar: filter + export + reset */
.fcd-toolbar {
    display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;
    margin: 12px 0;
}
.fcd-toolbar .fcd-search {
    height: 36px; padding: 6px 10px; border: 1px solid #ccc; border-radius: 6px; min-width: 220px;
}
.fcd-btn {
    height: 36px; padding: 6px 14px; border-radius: 6px; border: 1px solid #650000;
    background: #fff; color: #650000; font-weight: 500; cursor: pointer; display: inline-flex;
    align-items: center; gap: 6px;
}
.fcd-btn:hover { background: #650000; color: #fff; }
.fcd-btn.primary { background: #650000; color: #fff; }
.fcd-btn.primary:hover { background: #4a0000; }
.fcd-btn .bi { font-size: 14px; }
.fcd-summary { color: #666; font-size: 12px; margin-left: auto; }

/* Filter modal layout */
#fcdFilterModal .filter-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 12px;
}
#fcdFilterModal .filter-grid label {
    display: flex; flex-direction: column; font-size: 12px; color: #444; gap: 4px;
}
#fcdFilterModal .filter-grid input,
#fcdFilterModal .filter-grid select {
    height: 34px; padding: 4px 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;
}
#fcdFilterModal .filter-actions {
    display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px;
}

/* Make the page area fill the viewport so the bottom isn't a big white slab. */
.home-section .container1 {
    min-height: calc(100vh - 120px);
    display: flex;
    flex-direction: column;
}
.home-section .cd-scroll-wrap { flex: 1 1 auto; min-height: 320px; }
</style>

<div class="home-section">
  <div class="container1" style="padding:20px;">
    <h2 class="headings"><?= htmlspecialchars($page_title) ?></h2>
    <p>Read-only company catalog. Final Status mirrors the Progress Tracker. Min / Max CTC are editable here.</p>
    <p style="font-size:12px;color:#666;">Academic Year: <strong><?= htmlspecialchars($academic_year ?? 'All') ?></strong></p>

    <?php
      // Build the export URL preserving every active filter.
      $exportParams = $_GET;
      $exportParams['action'] = 'export';
      $exportUrl = '?' . http_build_query($exportParams);
    ?>
    <div class="fcd-toolbar">
        <input type="text" id="fcdLiveSearch" class="fcd-search" placeholder="Quick search visible rows...">
        <button type="button" class="fcd-btn" onclick="fcdOpenFilter()"><i class="fas fa-filter"></i> Filter</button>
        <button type="button" class="fcd-btn" onclick="window.location.href = window.location.pathname;"><i class="fas fa-undo"></i> Reset</button>
        <a class="fcd-btn primary" href="<?= htmlspecialchars($exportUrl) ?>"><i class="fas fa-file-export"></i> Export</a>
        <span class="fcd-summary">Showing <?= count($filtered) ?> of <?= count($rows) ?> drives</span>
    </div>

    <div class="cd-scroll-wrap">
      <table class="table table-bordered table-striped company-data-table">
        <thead>
          <tr>
            <th>Sl No</th>
            <th>Company Name</th>
            <th>Sector of Company</th>
            <th>Drive Number</th>
            <th>Role</th>
            <th>Sector of Role</th>
            <th>Offer Type</th>
            <th>Final Status</th>
            <th>Min CTC</th>
            <th>Max CTC</th>
            <th class="col-courses">Courses</th>
            <th class="col-school">School</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php
        $i = 1;
        if (!empty($filtered)):
            foreach ($filtered as $row):
                $coursesDisplay = $row['_courses_str'] ?? '';
                $schoolDisplay  = $row['_school_str']  ?? '';
        ?>
          <tr id="fcd-row-<?= (int)$row['id'] ?>">
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($row['company_name'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['company_sector'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['drive_number'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['role'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['role_sector'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['offer_type'] ?? '') ?></td>
            <td><span class="fcd-status-pill"><?= htmlspecialchars($row['final_status'] ?: '—') ?></span></td>
            <td><input type="text" class="ctc-input" name="min_ctc" value="<?= htmlspecialchars($row['min_ctc'] ?? '') ?>"></td>
            <td><input type="text" class="ctc-input" name="max_ctc" value="<?= htmlspecialchars($row['max_ctc'] ?? '') ?>"></td>
            <td class="col-courses"><?= htmlspecialchars($coursesDisplay) ?></td>
            <td class="col-school"><?= htmlspecialchars($schoolDisplay) ?></td>
            <td>
              <button type="button" class="fcd-save-btn" onclick="fcdSave(<?= (int)$row['id'] ?>)">Save</button>
              <span id="fcd-msg-<?= (int)$row['id'] ?>" class="fcd-saved"></span>
            </td>
          </tr>
        <?php
            endforeach;
        else:
        ?>
          <tr><td colspan="13" class="text-center text-muted">No drives match the current filters.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Filter Modal -->
<div id="fcdFilterModal" class="modal fade" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Filter Full Time Company Data</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="GET" class="modal-body">
        <div class="filter-grid">
          <label>Company Name<input type="text" name="company_name" value="<?= htmlspecialchars($filter['company_name'] ?? '') ?>"></label>
          <label>Role<input type="text" name="role" value="<?= htmlspecialchars($filter['role'] ?? '') ?>"></label>
          <label>Sector of Company
            <select name="company_sector">
              <option value="">All</option>
              <?php foreach ($companySectors as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>" <?= ($filter['company_sector'] ?? '') === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Sector of Role
            <select name="role_sector">
              <option value="">All</option>
              <?php foreach ($roleSectors as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>" <?= ($filter['role_sector'] ?? '') === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Drive Number
            <select name="drive_number">
              <option value="">All</option>
              <?php foreach ($driveNos as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>" <?= ($filter['drive_number'] ?? '') === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Offer Type
            <select name="offer_type">
              <option value="">All</option>
              <?php foreach ($offerTypes as $o): ?>
                <option value="<?= htmlspecialchars($o) ?>" <?= ($filter['offer_type'] ?? '') === $o ? 'selected' : '' ?>><?= htmlspecialchars($o) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Final Status
            <select name="final_status">
              <option value="">All</option>
              <?php foreach ($finalStatuses as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>" <?= ($filter['final_status'] ?? '') === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Min CTC ≥<input type="number" step="any" name="min_ctc_min" value="<?= htmlspecialchars($filter['min_ctc_min'] ?? '') ?>"></label>
          <label>Max CTC ≤<input type="number" step="any" name="max_ctc_max" value="<?= htmlspecialchars($filter['max_ctc_max'] ?? '') ?>"></label>
          <label>Course contains<input type="text" name="course" value="<?= htmlspecialchars($filter['course'] ?? '') ?>" placeholder="e.g. BCom"></label>
        </div>
        <div class="filter-actions">
          <button type="button" class="fcd-btn" onclick="document.querySelectorAll('#fcdFilterModal input, #fcdFilterModal select').forEach(el => el.tagName === 'SELECT' ? el.selectedIndex = 0 : el.value = '')">Clear</button>
          <button type="submit" class="fcd-btn primary">Apply Filters</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function fcdOpenFilter() {
    if (window.bootstrap && bootstrap.Modal) {
        new bootstrap.Modal(document.getElementById('fcdFilterModal')).show();
    } else {
        document.getElementById('fcdFilterModal').classList.add('show');
        document.getElementById('fcdFilterModal').style.display = 'block';
    }
}

// Live filter on visible rows.
document.addEventListener('DOMContentLoaded', function() {
    const ls = document.getElementById('fcdLiveSearch');
    if (!ls) return;
    ls.addEventListener('input', function() {
        const v = this.value.toLowerCase();
        document.querySelectorAll('.company-data-table tbody tr').forEach(tr => {
            let txt = tr.textContent.toLowerCase();
            tr.querySelectorAll('input').forEach(el => txt += ' ' + (el.value || '').toLowerCase());
            tr.style.display = txt.includes(v) ? '' : 'none';
        });
    });
});

function fcdSave(rowId) {
    const row = document.getElementById('fcd-row-' + rowId);
    const data = new FormData();
    data.append('save_company_data_row', '1');
    data.append('row_id', rowId);
    data.append('min_ctc', row.querySelector('[name="min_ctc"]').value);
    data.append('max_ctc', row.querySelector('[name="max_ctc"]').value);

    const msg = document.getElementById('fcd-msg-' + rowId);
    msg.textContent = 'Saving…';
    msg.style.color = '#666';

    fetch(window.location.href, {
        method: 'POST',
        body: data,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(d => {
        if (d.status === 'success') {
            msg.textContent = '✓ Saved';
            msg.style.color = '#198754';
            setTimeout(() => msg.textContent = '', 1800);
        } else {
            msg.textContent = 'Error';
            msg.style.color = '#dc3545';
        }
    })
    .catch(() => {
        msg.textContent = 'Error';
        msg.style.color = '#dc3545';
    });
}
</script>

<?php include("footer.php"); ?>
