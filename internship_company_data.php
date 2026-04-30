<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}
include("config.php");
include("course_groups_dynamic.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_company_data_row'])) {
    $id          = intval($_POST['row_id'] ?? 0);
    $min_stipend = $_POST['min_stipend'] ?? '';
    $max_stipend = $_POST['max_stipend'] ?? '';

    $stmt = $conn->prepare("UPDATE drive_data SET min_stipend = ?, max_stipend = ? WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
        exit;
    }
    $stmt->bind_param("ssi", $min_stipend, $max_stipend, $id);
    $ok = $stmt->execute();

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['status' => $ok ? 'success' : 'error']);
        exit;
    }
}

include("header.php");

$page_title = "Internship Company Data";

$academic_year = $_SESSION['selected_academic_year'] ?? null;
$ay_clause = $academic_year ? "AND drv.academic_year = '" . $conn->real_escape_string($academic_year) . "'" : "";

$sql = "
SELECT
  d.id,
  d.company_name,
  d.drive_no AS drive_number,
  d.role,
  d.offer_type,
  d.final_status,
  d.min_stipend,
  d.max_stipend,
  d.eligible_courses,
  drv.company_sector,
  dr.sector AS role_sector
FROM drive_data d
LEFT JOIN drives drv ON d.company_name = drv.company_name AND d.drive_no = drv.drive_no
LEFT JOIN drive_roles dr ON drv.drive_id = dr.drive_id AND TRIM(d.role) = TRIM(dr.designation_name)
WHERE d.offer_type IN ('Internship','Apprenticeship (Part Time)','Internship + PPO (Pre-Final Year)')
  $ay_clause
ORDER BY d.company_name ASC, d.id ASC
";

$result = $conn->query($sql);

$courseSchoolMap = [];
$cs = $conn->query("SELECT course_name, school FROM courses WHERE school IS NOT NULL");
if ($cs) {
    while ($row = $cs->fetch_assoc()) {
        $courseSchoolMap[strtolower(trim($row['course_name']))] = $row['school'];
    }
}

function formatCoursesPretty(array $courses): string {
    $clean = array_filter(array_map('trim', $courses), fn($v) => $v !== '' && strtolower($v) !== 'on');
    return implode(', ', $clean);
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
    min-width: 1500px;       /* force the table to be wider than viewport so scrollbar appears */
    white-space: nowrap;
    margin-bottom: 0;
}
.company-data-table th {
    background:#650000; color:#fff; vertical-align: middle; text-align:center;
    position: sticky; top: 0; z-index: 2;
}
.company-data-table td { vertical-align: middle; }
.company-data-table input.ctc-input {
    width: 110px; padding: 4px 6px; border:1px solid #ccc; border-radius:4px;
}
.icd-status-pill {
    display:inline-block; padding:3px 10px; border-radius:12px; font-size:12px;
    background:#f0f0f0;
}
.icd-save-btn {
    background:#fff; border:1px solid #198754; color:#198754; font-weight:600;
    border-radius:4px; padding:4px 10px; cursor:pointer;
}
.icd-save-btn:hover { background:#198754; color:#fff; }
.icd-saved { color:#198754; font-size:12px; margin-left:6px; }
</style>

<div class="home-section">
  <div class="container1" style="padding:20px;">
    <h2 class="headings"><?= htmlspecialchars($page_title) ?></h2>
    <p>Read-only company catalog. Final Status mirrors the Progress Tracker. Min Stipend is editable here.</p>
    <p style="font-size:12px;color:#666;">Academic Year: <strong><?= htmlspecialchars($academic_year ?? 'All') ?></strong></p>

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
            <th>Min Stipend</th>
            <th>Max Stipend</th>
            <th>Courses</th>
            <th>School</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php
        $i = 1;
        if ($result && $result->num_rows > 0):
            while ($row = $result->fetch_assoc()):
                $courses = json_decode($row['eligible_courses'] ?? '[]', true);
                if (!is_array($courses)) $courses = [];
                $coursesDisplay = formatCoursesPretty($courses);
                $schoolDisplay  = deriveSchools($courses, $courseSchoolMap);
        ?>
          <tr id="icd-row-<?= (int)$row['id'] ?>">
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($row['company_name'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['company_sector'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['drive_number'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['role'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['role_sector'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['offer_type'] ?? '') ?></td>
            <td><span class="icd-status-pill"><?= htmlspecialchars($row['final_status'] ?: '—') ?></span></td>
            <td><input type="text" class="ctc-input" name="min_stipend" value="<?= htmlspecialchars($row['min_stipend'] ?? '') ?>"></td>
            <td><input type="text" class="ctc-input" name="max_stipend" value="<?= htmlspecialchars($row['max_stipend'] ?? '') ?>"></td>
            <td><?= htmlspecialchars($coursesDisplay) ?></td>
            <td><?= htmlspecialchars($schoolDisplay) ?></td>
            <td>
              <button type="button" class="icd-save-btn" onclick="icdSave(<?= (int)$row['id'] ?>)">Save</button>
              <span id="icd-msg-<?= (int)$row['id'] ?>" class="icd-saved"></span>
            </td>
          </tr>
        <?php
            endwhile;
        else:
        ?>
          <tr><td colspan="13" class="text-center text-muted">No drives found for the selected academic year.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function icdSave(rowId) {
    const row = document.getElementById('icd-row-' + rowId);
    const data = new FormData();
    data.append('save_company_data_row', '1');
    data.append('row_id', rowId);
    data.append('min_stipend', row.querySelector('[name="min_stipend"]').value);
    data.append('max_stipend', row.querySelector('[name="max_stipend"]').value);

    const msg = document.getElementById('icd-msg-' + rowId);
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
