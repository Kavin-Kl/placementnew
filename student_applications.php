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

// Fetch all applications
$apps_query = "
    SELECT a.*, d.company_name, d.drive_no, dr.designation_name, dr.ctc, dr.stipend, dr.offer_type
    FROM applications a
    JOIN drives d ON a.drive_id = d.drive_id
    JOIN drive_roles dr ON a.role_id = dr.role_id
    WHERE a.student_id = ?
    ORDER BY a.applied_at DESC
";
$apps_stmt = $conn->prepare($apps_query);
$apps_stmt->bind_param("i", $student_id);
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
                    $count = 1;
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
                      <tr>
                        <td><?= $count++ ?></td>
                        <td>
                          <strong><?= htmlspecialchars($app['company_name']) ?></strong>
                          <br>
                          <small class="text-muted">Drive #<?= htmlspecialchars($app['drive_no']) ?></small>
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
                          <?php if (!empty($app['resume_file']) && file_exists($app['resume_file'])): ?>
                            <a href="<?= htmlspecialchars($app['resume_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
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
