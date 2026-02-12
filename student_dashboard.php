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

// Fetch student details
$student_stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();

// Get total applications count
$apps_count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM applications WHERE student_id = ?");
$apps_count_stmt->bind_param("i", $student_id);
$apps_count_stmt->execute();
$total_applications = $apps_count_stmt->get_result()->fetch_assoc()['total'];

// Get placed status
$placed_count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM applications WHERE student_id = ? AND status = 'placed'");
$placed_count_stmt->bind_param("i", $student_id);
$placed_count_stmt->execute();
$placed_applications = $placed_count_stmt->get_result()->fetch_assoc()['total'];

// Pending applications removed from dashboard as per requirement

// Get active drives count
$now = date('Y-m-d H:i:s');
$active_drives_query = "SELECT COUNT(*) as total FROM drives WHERE open_date <= '$now' AND close_date >= '$now'";
$active_drives_result = $conn->query($active_drives_query);
$active_drives = $active_drives_result->fetch_assoc()['total'];

// Get recent applications
$recent_apps_stmt = $conn->prepare("
    SELECT a.*, d.company_name, dr.designation_name, dr.ctc
    FROM applications a
    JOIN drives d ON a.drive_id = d.drive_id
    JOIN drive_roles dr ON a.role_id = dr.role_id
    WHERE a.student_id = ?
    ORDER BY a.applied_at DESC
    LIMIT 5
");
$recent_apps_stmt->bind_param("i", $student_id);
$recent_apps_stmt->execute();
$recent_apps = $recent_apps_stmt->get_result();

// Get upcoming drives
$upcoming_drives_query = "
    SELECT d.*, COUNT(dr.role_id) as role_count
    FROM drives d
    LEFT JOIN drive_roles dr ON d.drive_id = dr.drive_id
    WHERE d.open_date <= '$now' AND d.close_date >= '$now'
    GROUP BY d.drive_id
    ORDER BY d.open_date ASC
    LIMIT 5
";
$upcoming_drives = $conn->query($upcoming_drives_query);
?>

<div class="home-section">
  <div class="container-fluid">
    <div class="row mb-4">
      <div class="col-12">
        <h2>Welcome, <?= htmlspecialchars($student['student_name']) ?>!</h2>
        <p class="text-muted">Here's your placement dashboard overview</p>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
      <div class="col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="text-muted mb-1">Total Applications</h6>
                <h2 class="mb-0"><?= $total_applications ?></h2>
              </div>
              <div class="stat-icon bg-primary bg-opacity-10 p-3 rounded">
                <i class="bx bx-file text-primary" style="font-size: 32px;"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="text-muted mb-1">Placements</h6>
                <h2 class="mb-0"><?= $placed_applications ?></h2>
              </div>
              <div class="stat-icon bg-success bg-opacity-10 p-3 rounded">
                <i class="bx bx-check-circle text-success" style="font-size: 32px;"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="text-muted mb-1">Active Drives</h6>
                <h2 class="mb-0"><?= $active_drives ?></h2>
              </div>
              <div class="stat-icon bg-info bg-opacity-10 p-3 rounded">
                <i class="bx bx-briefcase text-info" style="font-size: 32px;"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Applications & Upcoming Drives -->
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white border-bottom">
            <h5 class="mb-0">Recent Applications</h5>
          </div>
          <div class="card-body">
            <?php if ($recent_apps->num_rows > 0): ?>
              <div class="list-group list-group-flush">
                <?php while ($app = $recent_apps->fetch_assoc()): ?>
                  <div class="list-group-item px-0">
                    <div class="d-flex justify-content-between align-items-start">
                      <div>
                        <h6 class="mb-1"><?= htmlspecialchars($app['company_name']) ?></h6>
                        <p class="mb-1 text-muted small"><?= htmlspecialchars($app['designation_name']) ?></p>
                        <p class="mb-0 text-muted small">CTC: ₹<?= htmlspecialchars($app['ctc']) ?></p>
                      </div>
                      <div class="text-end">
                        <span class="badge <?=
                          $app['status'] == 'placed' ? 'bg-success' :
                          ($app['status'] == 'rejected' ? 'bg-danger' :
                          ($app['status'] == 'applied' || $app['status'] == 'pending' ? 'bg-warning' : 'bg-secondary'))
                        ?>">
                          <?= ucfirst($app['status']) ?>
                        </span>
                        <p class="mb-0 text-muted small mt-1"><?= date('M d, Y', strtotime($app['applied_at'])) ?></p>
                      </div>
                    </div>
                  </div>
                <?php endwhile; ?>
              </div>
              <div class="text-center mt-3">
                <a href="student_applications.php" class="btn btn-sm btn-outline-primary">View All Applications</a>
              </div>
            <?php else: ?>
              <div class="text-center text-muted py-5">
                <i class="bx bx-folder-open" style="font-size: 48px;"></i>
                <p class="mt-2">No applications yet</p>
                <a href="student_drives.php" class="btn btn-primary btn-sm">Browse Opportunities</a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white border-bottom">
            <h5 class="mb-0">Active Opportunities</h5>
          </div>
          <div class="card-body">
            <?php if ($upcoming_drives->num_rows > 0): ?>
              <div class="list-group list-group-flush">
                <?php while ($drive = $upcoming_drives->fetch_assoc()): ?>
                  <div class="list-group-item px-0">
                    <div class="d-flex justify-content-between align-items-start">
                      <div>
                        <h6 class="mb-1"><?= htmlspecialchars($drive['company_name']) ?></h6>
                        <p class="mb-1 text-muted small"><?= $drive['role_count'] ?> role(s) available</p>
                        <p class="mb-0 text-muted small">
                          <i class="bx bx-time-five"></i> Closes: <?= date('M d, Y h:i A', strtotime($drive['close_date'])) ?>
                        </p>
                      </div>
                      <div>
                        <a href="student_drives.php?drive_id=<?= $drive['drive_id'] ?>" class="btn btn-sm btn-primary">View</a>
                      </div>
                    </div>
                  </div>
                <?php endwhile; ?>
              </div>
              <div class="text-center mt-3">
                <a href="student_drives.php" class="btn btn-sm btn-outline-primary">View All Opportunities</a>
              </div>
            <?php else: ?>
              <div class="text-center text-muted py-5">
                <i class="bx bx-briefcase" style="font-size: 48px;"></i>
                <p class="mt-2">No active drives at the moment</p>
                <p class="small">Check back later for new opportunities</p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Profile Completion -->
    <div class="row g-3 mt-3">
      <div class="col-12">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h5 class="card-title">Profile Status</h5>
            <?php
            $profile_completeness = 0;
            $checks = [
              'email' => 'Email',
              'phone_no' => 'Phone Number',
              'course' => 'Course',
              'percentage' => 'Percentage'
            ];
            $total_checks = count($checks);
            $completed = 0;

            foreach ($checks as $field => $label) {
              if (!empty($student[$field])) {
                $completed++;
              }
            }

            $profile_completeness = ($completed / $total_checks) * 100;
            ?>
            <div class="progress" style="height: 25px;">
              <div class="progress-bar <?= $profile_completeness == 100 ? 'bg-success' : 'bg-warning' ?>"
                   role="progressbar"
                   style="width: <?= $profile_completeness ?>%;"
                   aria-valuenow="<?= $profile_completeness ?>"
                   aria-valuemin="0"
                   aria-valuemax="100">
                <?= round($profile_completeness) ?>% Complete
              </div>
            </div>
            <?php if ($profile_completeness < 100): ?>
              <p class="mt-2 mb-0 text-muted small">
                <i class="bx bx-info-circle"></i> Complete your profile to apply for more opportunities
              </p>
              <a href="student_profile.php" class="btn btn-sm btn-primary mt-2">Complete Profile</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<style>
.card {
  transition: transform 0.2s ease;
}

.card:hover {
  transform: translateY(-2px);
}

.stat-icon {
  border-radius: 12px;
}

.list-group-item {
  border-left: none;
  border-right: none;
}

.list-group-item:first-child {
  border-top: none;
}

.list-group-item:last-child {
  border-bottom: none;
}
</style>

</body>
</html>
