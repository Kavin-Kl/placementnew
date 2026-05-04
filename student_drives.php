<?php
// Configure session for ngrok compatibility
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0'); // Allow HTTP for local testing
session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit;
}

date_default_timezone_set('Asia/Kolkata');
include("config.php");
include("course_groups_dynamic.php");
include("student_header.php");

if (!function_exists('formatStudentCourseList')) {
    function formatStudentCourseList(array $courses): array {
        global $UG_COURSES, $PG_COURSES;

        $clean = array_values(array_filter(array_map('trim', $courses), fn($v) => $v !== '' && strtolower($v) !== 'on'));
        if (empty($clean)) return [];

        $lower = array_map('strtolower', $clean);
        $ugLower = is_array($UG_COURSES ?? null) ? array_map('strtolower', $UG_COURSES) : [];
        $pgLower = is_array($PG_COURSES ?? null) ? array_map('strtolower', $PG_COURSES) : [];

        $hasAllUG = (bool) array_intersect($lower, ['all ug', 'all ug courses']);
        $hasAllPG = (bool) array_intersect($lower, ['all pg', 'all pg courses']);

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

        return $display;
    }
}

$student_id = $_SESSION['student_id'];
require_once __DIR__ . '/academic_year_helper.php';

// Fetch student details for eligibility checking
$student_stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();

// Once placed full-time and not allowed to reapply, the student cannot apply to anything new.
$student_locked_out = student_is_locked_out($student ?? []);

// Determine which eligibility flags to check based on student's category
$placement_category = $student['placement_category'] ?? 'none';

// Get active drives filtered by eligibility
$now = date('Y-m-d H:i:s');
$drives_query = "
    SELECT d.*
    FROM drives d
    WHERE d.open_date <= '$now' AND d.close_date >= '$now'
";

// Add eligibility filter based on student's placement category
if ($placement_category === 'internship') {
    $drives_query .= " AND d.show_to_internship = 1";
} elseif ($placement_category === 'vantage') {
    $drives_query .= " AND d.show_to_vantage = 1";
} elseif ($placement_category === 'full-time') {
    $drives_query .= " AND d.show_to_placement = 1";
} else {
    // If no category or 'none', show nothing or all - you can adjust this logic
    $drives_query .= " AND (d.show_to_internship = 1 OR d.show_to_vantage = 1 OR d.show_to_placement = 1)";
}

// Filter by academic year — drive's AY must match the student's stored AY.
$student_ay = $student['academic_year'] ?? null;
if ($student_ay) {
    $student_ay_e = $conn->real_escape_string($student_ay);
    $drives_query .= " AND d.academic_year = '$student_ay_e'";
}

// Strict graduating_year match — drives without an explicit graduating_year tag are hidden
// from student dashboards. Forces admins to tag each drive's target cohort.
$student_year = $student['year_of_passing'] ?? null;
if ($student_year) {
    $student_year_e = $conn->real_escape_string((string)$student_year);
    $drives_query .= " AND d.graduating_year IS NOT NULL
                       AND d.graduating_year <> ''
                       AND FIND_IN_SET('$student_year_e', REPLACE(d.graduating_year, ' ', ''))";
}

$drives_query .= " ORDER BY d.close_date ASC";

// Pagination — limit drives per page so the page stays light as drives accumulate.
require_once __DIR__ . '/pagination_helper.php';
$total_drives = 0;
$count_q = preg_replace('/SELECT\s+d\.\*/', 'SELECT COUNT(*) AS cnt', $drives_query, 1);
// Strip trailing ORDER BY for the count query (faster).
$count_q = preg_replace('/\s+ORDER\s+BY\s+.+$/is', '', $count_q);
$count_res = $conn->query($count_q);
if ($count_res) { $total_drives = (int)$count_res->fetch_assoc()['cnt']; }
$drives_pagination = paginate_setup($total_drives, 5, 'page');
$drives_query .= " LIMIT " . (int)$drives_pagination['per_page'] . " OFFSET " . (int)$drives_pagination['offset'];

$drives_result = $conn->query($drives_query);

// If specific drive is requested
$selected_drive_id = $_GET['drive_id'] ?? null;
?>

<div class="home-section">
  <div class="container-fluid">
    <div class="row mb-4">
      <div class="col-12">
        <h2>Available Opportunities</h2>
        <p class="text-muted">Browse and apply for placement drives</p>
      </div>
    </div>

    <?php if ($student_locked_out): ?>
      <div class="alert alert-warning" role="alert">
        <strong>You are already placed.</strong>
      </div>
    <?php endif; ?>

    <?php if ($drives_result->num_rows > 0): ?>
      <div class="row g-3">
        <?php while ($drive = $drives_result->fetch_assoc()): ?>
          <?php
          // Get roles for this drive
          $roles_stmt = $conn->prepare("
              SELECT * FROM drive_roles
              WHERE drive_id = ? AND is_finished = 0
              ORDER BY created_at DESC
          ");
          $roles_stmt->bind_param("i", $drive['drive_id']);
          $roles_stmt->execute();
          $roles = $roles_stmt->get_result();

          // Check if student has already applied to any role in this drive
          $applied_stmt = $conn->prepare("
              SELECT COUNT(*) as applied FROM applications
              WHERE student_id = ? AND drive_id = ?
          ");
          $applied_stmt->bind_param("ii", $student_id, $drive['drive_id']);
          $applied_stmt->execute();
          $has_applied = $applied_stmt->get_result()->fetch_assoc()['applied'] > 0;

          // Calculate time remaining
          $close_timestamp = strtotime($drive['close_date']);
          $now_timestamp = time();
          $time_diff = $close_timestamp - $now_timestamp;
          $days_remaining = floor($time_diff / (60 * 60 * 24));
          $hours_remaining = floor(($time_diff % (60 * 60 * 24)) / (60 * 60));
          ?>

          <div class="col-12">
            <div class="card border-0 shadow-sm">
              <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <div>
                  <h4 class="mb-1"><?= htmlspecialchars($drive['company_name']) ?></h4>
                  <p class="mb-0 text-muted small">Drive #<?= htmlspecialchars($drive['drive_no']) ?></p>
                </div>
                <div class="text-end">
                  <?php if ($has_applied): ?>
                    <span class="badge bg-success">Applied</span>
                  <?php endif; ?>
                  <?php if ($days_remaining <= 2): ?>
                    <span class="badge bg-danger">Closing Soon</span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="card-body">
                <div class="row mb-3">
                  <div class="col-md-6">
                    <p class="mb-2">
                      <i class="bx bx-calendar text-primary"></i>
                      <strong>Opens:</strong> <?= date('M d, Y h:i A', strtotime($drive['open_date'])) ?>
                    </p>
                    <p class="mb-2">
                      <i class="bx bx-calendar-x text-danger"></i>
                      <strong>Closes:</strong> <?= date('M d, Y h:i A', strtotime($drive['close_date'])) ?>
                    </p>
                  </div>
                  <div class="col-md-6">
                    <p class="mb-2">
                      <i class="bx bx-time-five text-warning"></i>
                      <strong>Time Remaining:</strong>
                      <?php if ($days_remaining > 0): ?>
                        <?= $days_remaining ?> days, <?= $hours_remaining ?> hours
                      <?php else: ?>
                        <?= $hours_remaining ?> hours remaining
                      <?php endif; ?>
                    </p>
                  </div>
                </div>

                <?php
                if (!empty($drive['extra_details'])) {
                  $extra_details = json_decode($drive['extra_details'], true);
                  // Only show if we have at least one non-empty field
                  $has_details = false;
                  if ($extra_details && is_array($extra_details)) {
                    foreach ($extra_details as $key => $value) {
                      if (!empty($value) && trim($value) !== '') {
                        $has_details = true;
                        break;
                      }
                    }
                  }

                  if ($has_details):
                  ?>
                    <div class="alert alert-info mb-3">
                      <strong>Details:</strong>
                      <ul class="mb-0 mt-2">
                        <?php if (!empty($extra_details['vacancies']) && trim($extra_details['vacancies']) !== ''): ?>
                          <li><strong>Vacancies:</strong> <?= htmlspecialchars($extra_details['vacancies']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($extra_details['duration']) && trim($extra_details['duration']) !== ''): ?>
                          <li><strong>Duration:</strong> <?= htmlspecialchars($extra_details['duration']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($extra_details['stipend']) && trim($extra_details['stipend']) !== ''): ?>
                          <li><strong>Stipend:</strong> <?= htmlspecialchars($extra_details['stipend']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($extra_details['timings']) && trim($extra_details['timings']) !== ''): ?>
                          <li><strong>Timings:</strong> <?= htmlspecialchars($extra_details['timings']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($extra_details['workMode']) && trim($extra_details['workMode']) !== ''): ?>
                          <li><strong>Work Mode:</strong> <?= htmlspecialchars($extra_details['workMode']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($extra_details['officeAddress']) && trim($extra_details['officeAddress']) !== ''): ?>
                          <li><strong>Office Address:</strong> <?= htmlspecialchars($extra_details['officeAddress']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($extra_details['postInternship']) && trim($extra_details['postInternship']) !== ''): ?>
                          <li><strong>PPO Available:</strong> <?= htmlspecialchars($extra_details['postInternship']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($extra_details['otherDetails']) && trim($extra_details['otherDetails']) !== ''): ?>
                          <li><strong>Other Details:</strong> <?= nl2br(htmlspecialchars($extra_details['otherDetails'])) ?></li>
                        <?php endif; ?>
                      </ul>
                    </div>
                  <?php endif; ?>
                <?php } ?>

                <?php
                // Drives sometimes store jd_file as the literal string "[]" — non-empty
                // but with no actual files. Decode and confirm at least one path is set
                // before showing the button; otherwise students see a button that opens
                // an "Unable to display job description" page.
                $_jd_decoded = !empty($drive['jd_file']) ? json_decode($drive['jd_file'], true) : null;
                $has_jd_file = (is_array($_jd_decoded) && !empty(array_filter($_jd_decoded)))
                               || (is_string($drive['jd_file'] ?? null) && strpos($drive['jd_file'], 'data:') === 0);
                ?>
                <?php if (!empty($drive['jd_link'])): ?>
                  <a href="<?= htmlspecialchars($drive['jd_link']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mb-3">
                    <i class="bx bx-file"></i> View Job Description
                  </a>
                <?php elseif ($has_jd_file): ?>
                  <a href="view_jd.php?drive_id=<?= $drive['drive_id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary mb-3">
                    <i class="bx bx-file"></i> View Job Description
                  </a>
                <?php endif; ?>

                <?php
                $whatsapp_link = '';
                if (!empty($drive['extra_details'])) {
                    $ed = json_decode($drive['extra_details'], true);
                    if (is_array($ed) && !empty($ed['whatsapp']) && trim($ed['whatsapp']) !== '') {
                        $whatsapp_link = trim($ed['whatsapp']);
                    }
                }
                ?>
                <?php if ($whatsapp_link): ?>
                  <div class="alert alert-success mb-3">
                    <i class="bx bxl-whatsapp"></i>
                    <strong>WhatsApp group for this drive:</strong>
                    <a href="<?= htmlspecialchars($whatsapp_link) ?>" target="_blank" rel="noopener">
                      <?= htmlspecialchars($whatsapp_link) ?>
                    </a>
                  </div>
                <?php endif; ?>

                <!-- Roles List -->
                <?php if ($roles->num_rows > 0): ?>
                  <h5 class="mb-3">Available Roles (<?= $roles->num_rows ?>)</h5>
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead class="table-light">
                        <tr>
                          <th>Designation</th>
                          <th>CTC/Stipend</th>
                          <th>Offer Type</th>
                          <th>Min Percentage</th>
                          <th>Eligible Courses</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php while ($role = $roles->fetch_assoc()):
                          // Check if student already applied for this specific role
                          $role_applied_stmt = $conn->prepare("
                              SELECT application_id, status FROM applications
                              WHERE student_id = ? AND role_id = ?
                          ");
                          $role_applied_stmt->bind_param("ii", $student_id, $role['role_id']);
                          $role_applied_stmt->execute();
                          $role_app_result = $role_applied_stmt->get_result();
                          $role_applied = $role_app_result->num_rows > 0;
                          $app_status = $role_applied ? $role_app_result->fetch_assoc()['status'] : null;

                          // Check eligibility
                          $eligible_courses_raw = $role['eligible_courses'];
                          $eligible_courses = json_decode($eligible_courses_raw, true);
                          if (!is_array($eligible_courses)) {
                              // Fallback to comma-separated if not JSON
                              $eligible_courses = explode(',', $eligible_courses_raw);
                              $eligible_courses = array_map('trim', $eligible_courses);
                          }

                          // Normalize course names for comparison (remove dots, spaces, dashes, make lowercase)
                          $normalize_course = function($course) {
                              return strtolower(str_replace(['.', ' ', '-', '_'], '', trim($course)));
                          };

                          $eligible_courses_normalized = array_map($normalize_course, $eligible_courses);
                          $eligible_courses_lower = array_map('strtolower', $eligible_courses);
                          $student_course_normalized = $normalize_course($student['course']);
                          $student_course_lower = strtolower($student['course']);
                          $student_program_lower = strtolower($student['program']);
                          $student_program_type_lower = strtolower($student['program_type']);

                          $is_eligible = in_array($student_course_normalized, $eligible_courses_normalized) ||
                                        in_array($student_course_lower, $eligible_courses_lower) ||
                                        in_array($student_program_lower, $eligible_courses_lower) ||
                                        in_array('all', $eligible_courses_lower) ||
                                        (in_array('all ug', $eligible_courses_lower) && $student_program_type_lower == 'ug') ||
                                        (in_array('all pg', $eligible_courses_lower) && $student_program_type_lower == 'pg') ||
                                        (in_array('all ug courses', $eligible_courses_lower) && $student_program_type_lower == 'ug') ||
                                        (in_array('all pg courses', $eligible_courses_lower) && $student_program_type_lower == 'pg');

                          $meets_percentage = !$role['min_percentage'] ||
                                             ($student['percentage'] && $student['percentage'] >= $role['min_percentage']);

                          $can_apply = $is_eligible && $meets_percentage && !$role_applied && !$student_locked_out;
                        ?>
                          <tr>
                            <td><strong><?= htmlspecialchars($role['designation_name']) ?></strong></td>
                            <td>
                              <?php if ($role['offer_type'] == 'Internship'): ?>
                                <?php
                                  $stipend = trim($role['stipend'] ?? '');
                                  echo $stipend !== '' ? '₹' . htmlspecialchars($stipend) : 'N/A';
                                ?>
                              <?php else: ?>
                                <?php
                                  $ctc = trim($role['ctc'] ?? '');
                                  echo $ctc !== '' ? '₹' . htmlspecialchars($ctc) : 'N/A';
                                ?>
                              <?php endif; ?>
                            </td>
                            <td>
                              <span class="badge <?= $role['offer_type'] == 'Internship' ? 'bg-info' : 'bg-success' ?>">
                                <?= htmlspecialchars($role['offer_type']) ?>
                              </span>
                            </td>
                            <td><?= $role['min_percentage'] ? $role['min_percentage'] . '%' : 'N/A' ?></td>
                            <td>
                              <?php
                              $eligible_display_list = formatStudentCourseList($eligible_courses);
                              $courses_display = array_slice($eligible_display_list, 0, 3);
                              $hidden_courses  = array_slice($eligible_display_list, 3);
                              ?>
                              <small class="eligible-courses">
                                <span class="ec-shown"><?= htmlspecialchars(implode(', ', $courses_display)) ?></span>
                                <?php if (!empty($hidden_courses)): ?>
                                  <span class="ec-hidden" style="display:none;">, <?= htmlspecialchars(implode(', ', $hidden_courses)) ?></span>
                                  <a href="#" class="ec-toggle text-decoration-none ms-1"
                                     data-shown="0"
                                     data-more-label="... +<?= count($hidden_courses) ?> more"
                                     data-less-label="show less">
                                    ... +<?= count($hidden_courses) ?> more
                                  </a>
                                <?php endif; ?>
                              </small>
                            </td>
                            <td>
                              <?php if ($role_applied): ?>
                                <span class="badge <?=
                                  $app_status == 'placed' ? 'bg-success' :
                                  ($app_status == 'rejected' ? 'bg-danger' :
                                  ($app_status == 'blocked' ? 'bg-dark' : 'bg-warning'))
                                ?>">
                                  <?= ucfirst($app_status) ?>
                                </span>
                              <?php elseif (!$is_eligible): ?>
                                <span class="badge bg-secondary" title="Your course is not eligible">Not Eligible</span>
                              <?php elseif (!$meets_percentage): ?>
                                <span class="badge bg-secondary" title="Minimum percentage required: <?= $role['min_percentage'] ?>%">
                                  Low %
                                </span>
                              <?php else: ?>
                                <a href="form_generator.php?form=<?= htmlspecialchars($drive['form_link']) ?>"
                                   class="btn btn-sm btn-primary">
                                  Apply Now
                                </a>
                              <?php endif; ?>
                            </td>
                          </tr>
                        <?php endwhile; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <div class="alert alert-warning">
                    <i class="bx bx-info-circle"></i> No roles available for this drive yet.
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
      <?= render_pagination($drives_pagination, 'page') ?>
    <?php else: ?>
      <div class="row">
        <div class="col-12">
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
              <i class="bx bx-briefcase" style="font-size: 64px; color: #ccc;"></i>
              <h4 class="mt-3">No Active Drives</h4>
              <p class="text-muted">There are no active placement drives at the moment. Check back later!</p>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<style>
.card {
  transition: all 0.2s ease;
}

.card:hover {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1) !important;
}

.table th {
  font-weight: 600;
  font-size: 14px;
}

.table td {
  vertical-align: middle;
}
.eligible-courses .ec-toggle {
  cursor: pointer;
  color: #0d6efd;
}
.eligible-courses .ec-toggle:hover { text-decoration: underline; }
</style>

<script>
document.addEventListener('click', function(e) {
  const toggle = e.target.closest('.ec-toggle');
  if (!toggle) return;
  e.preventDefault();
  const wrapper = toggle.closest('.eligible-courses');
  const hidden = wrapper.querySelector('.ec-hidden');
  if (!hidden) return;
  const isShown = toggle.dataset.shown === '1';
  hidden.style.display = isShown ? 'none' : 'inline';
  toggle.textContent = isShown ? toggle.dataset.moreLabel : toggle.dataset.lessLabel;
  toggle.dataset.shown = isShown ? '0' : '1';
});
</script>

</body>
</html>
