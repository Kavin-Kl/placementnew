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
include("student_header.php");

$student_id = $_SESSION['student_id'];

// Pagination — keep the page light when a student has many past applications.
require_once __DIR__ . '/pagination_helper.php';
$count_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM applications WHERE student_id = ?");
$count_stmt->bind_param("i", $student_id);
$count_stmt->execute();
$total_apps = (int)$count_stmt->get_result()->fetch_assoc()['cnt'];
$apps_pagination = paginate_setup($total_apps, 25, 'page');
$apps_offset = (int)$apps_pagination['offset'];
$apps_limit = (int)$apps_pagination['per_page'];

$apps_query = "
    SELECT a.*, d.company_name, d.drive_no, d.extra_details AS drive_extra_details,
           dr.designation_name, dr.ctc, dr.stipend, dr.offer_type
    FROM applications a
    JOIN drives d ON a.drive_id = d.drive_id
    JOIN drive_roles dr ON a.role_id = dr.role_id
    WHERE a.student_id = ?
    ORDER BY a.applied_at DESC
    LIMIT ? OFFSET ?
";
$apps_stmt = $conn->prepare($apps_query);
$apps_stmt->bind_param("iii", $student_id, $apps_limit, $apps_offset);
$apps_stmt->execute();
$applications = $apps_stmt->get_result();
?>

<div class="home-section">
  <div class="container-fluid">
    <div class="row mb-4">
      <div class="col-12">
        <h2>My Applications</h2>
        <p class="text-muted">Track all your job and internship applications</p>
      </div>
    </div>

    <?php if ($applications->num_rows > 0): ?>
      <div class="row">
        <div class="col-12">
          <div class="card border-0 shadow-sm">
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-hover">
                  <thead class="table-light">
                    <tr>
                      <th>#</th>
                      <th>Company</th>
                      <th>Role</th>
                      <th>Offer Type</th>
                      <th>CTC/Stipend</th>
                      <th>Applied On</th>
                      <th>Status</th>
                      <th>Resume</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $count = $apps_offset + 1;
                    while ($app = $applications->fetch_assoc()):
                      // Status badge color
                      $status_class = match($app['status']) {
                        'placed' => 'bg-success',
                        'rejected' => 'bg-danger',
                        'blocked' => 'bg-dark',
                        'applied', 'pending' => 'bg-warning text-dark',
                        default => 'bg-secondary'
                      };
                    ?>
                      <?php
                        $whatsapp_link = '';
                        if (!empty($app['drive_extra_details'])) {
                            $ed = json_decode($app['drive_extra_details'], true);
                            if (is_array($ed) && !empty($ed['whatsapp']) && trim($ed['whatsapp']) !== '') {
                                $whatsapp_link = trim($ed['whatsapp']);
                            }
                        }
                      ?>
                      <tr>
                        <td><?= $count++ ?></td>
                        <td>
                          <strong><?= htmlspecialchars($app['company_name']) ?></strong>
                          <br>
                          <small class="text-muted">Drive #<?= htmlspecialchars($app['drive_no']) ?></small>
                          <?php if ($whatsapp_link): ?>
                            <br>
                            <a href="<?= htmlspecialchars($whatsapp_link) ?>" target="_blank" rel="noopener"
                               class="small text-success" style="text-decoration:none;">
                              <i class="bx bxl-whatsapp"></i> Join WhatsApp Group
                            </a>
                          <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($app['designation_name']) ?></td>
                        <td>
                          <span class="badge <?= $app['offer_type'] == 'Internship' ? 'bg-info' : 'bg-success' ?>">
                            <?= htmlspecialchars($app['offer_type']) ?>
                          </span>
                        </td>
                        <td>
                          ₹<?= htmlspecialchars($app['offer_type'] == 'Internship' ? $app['stipend'] : $app['ctc']) ?>
                        </td>
                        <td>
                          <?= date('M d, Y', strtotime($app['applied_at'])) ?>
                          <br>
                          <small class="text-muted"><?= date('h:i A', strtotime($app['applied_at'])) ?></small>
                        </td>
                        <td>
                          <span class="badge <?= $status_class ?>">
                            <?= ucfirst($app['status']) ?>
                          </span>
                          <?php if (!empty($app['comments'])): ?>
                            <br>
                            <small class="text-muted" title="<?= htmlspecialchars($app['comments']) ?>">
                              <i class="bx bx-info-circle"></i> View comment
                            </small>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php
                          // Resume path is sometimes on the applications.resume_file
                          // column and sometimes inside the student_data JSON under
                          // a "Resume" key (the form_generator stores it there). Try
                          // both before falling back to N/A.
                          $resumePath = !empty($app['resume_file']) ? $app['resume_file'] : null;
                          if (!$resumePath && !empty($app['student_data'])) {
                              $sd = json_decode($app['student_data'], true);
                              if (is_array($sd)) {
                                  foreach ($sd as $k => $v) {
                                      if (is_string($v) && stripos($k, 'resume') !== false && trim($v) !== '') {
                                          $resumePath = $v;
                                          break;
                                      }
                                  }
                              }
                          }
                          ?>
                          <?php if ($resumePath && file_exists($resumePath)): ?>
                            <a href="<?= htmlspecialchars($resumePath) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                              <i class="bx bx-download"></i> View
                            </a>
                          <?php else: ?>
                            <span class="text-muted">N/A</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  </tbody>
                </table>
              </div>
              <?= render_pagination($apps_pagination, 'page') ?>
            </div>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="row">
        <div class="col-12">
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
              <i class="bx bx-file" style="font-size: 64px; color: #ccc;"></i>
              <h4 class="mt-3">No Applications Yet</h4>
              <p class="text-muted">You haven't applied to any drives yet. Start exploring opportunities!</p>
              <a href="student_drives.php" class="btn btn-primary">
                <i class="bx bx-briefcase"></i> Browse Opportunities
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<style>
.table th {
  font-weight: 600;
  font-size: 14px;
}

.table td {
  vertical-align: middle;
}

.badge {
  font-size: 12px;
  padding: 5px 10px;
}
</style>

</body>
</html>
