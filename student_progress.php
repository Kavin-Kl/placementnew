<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit;
}

include("config.php");
include("student_header.php");

$student_id = $_SESSION['student_id'];

// Fetch all applications with drive and role details
$query = "
    SELECT
        a.application_id,
        a.drive_id,
        a.role_id,
        a.status,
        a.applied_at,
        a.comments,
        d.company_name,
        d.drive_no,
        d.close_date,
        dr.designation_name,
        dr.offer_type,
        dr.ctc,
        dr.stipend
    FROM applications a
    INNER JOIN drives d ON a.drive_id = d.drive_id
    LEFT JOIN drive_roles dr ON a.role_id = dr.role_id
    WHERE a.student_id = ?
    ORDER BY a.applied_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$applications = $stmt->get_result();

// Count statistics
$total_applications = 0;
$placed_count = 0;
$pending_count = 0;
$rejected_count = 0;

$apps_array = [];
while ($row = $applications->fetch_assoc()) {
    // Fetch rounds for this application
    $rounds_stmt = $conn->prepare("
        SELECT * FROM application_rounds
        WHERE application_id = ?
        ORDER BY created_at ASC
    ");
    $rounds_stmt->bind_param("i", $row['application_id']);
    $rounds_stmt->execute();
    $row['rounds'] = $rounds_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $apps_array[] = $row;
    $total_applications++;
    if ($row['status'] == 'placed') $placed_count++;
    elseif ($row['status'] == 'rejected') $rejected_count++;
    elseif ($row['status'] == 'applied' || $row['status'] == 'pending') $pending_count++;
}
?>

<div class="home-section">
  <div class="container-fluid">
    <div class="row mb-4">
      <div class="col-12">
        <h2><i class='bx bx-line-chart'></i> My Progress Tracker</h2>
        <p class="text-muted">Track your placement application journey</p>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
      <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card">
          <div class="stat-icon" style="background: #e3f2fd;">
            <i class='bx bx-file' style="color: #2196F3;"></i>
          </div>
          <div class="stat-details">
            <h3><?= $total_applications ?></h3>
            <p>Total Applications</p>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card">
          <div class="stat-icon" style="background: #e8f5e9;">
            <i class='bx bx-check-circle' style="color: #4CAF50;"></i>
          </div>
          <div class="stat-details">
            <h3><?= $placed_count ?></h3>
            <p>Placed</p>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card">
          <div class="stat-icon" style="background: #fff8e1;">
            <i class='bx bx-time-five' style="color: #FFC107;"></i>
          </div>
          <div class="stat-details">
            <h3><?= $pending_count ?></h3>
            <p>In Progress</p>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card">
          <div class="stat-icon" style="background: #ffebee;">
            <i class='bx bx-x-circle' style="color: #f44336;"></i>
          </div>
          <div class="stat-details">
            <h3><?= $rejected_count ?></h3>
            <p>Not Selected</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Timeline -->
    <div class="row">
      <div class="col-12">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white">
            <h5 class="mb-0"><i class='bx bx-history'></i> Application Timeline</h5>
          </div>
          <div class="card-body">
            <?php if (count($apps_array) > 0): ?>
              <div class="timeline">
                <?php foreach ($apps_array as $app):
                  $status_config = [
                    'placed' => ['color' => 'success', 'icon' => 'bx-check-circle', 'text' => 'Placed'],
                    'applied' => ['color' => 'primary', 'icon' => 'bx-paper-plane', 'text' => 'Applied'],
                    'pending' => ['color' => 'warning', 'icon' => 'bx-time-five', 'text' => 'Pending'],
                    'rejected' => ['color' => 'danger', 'icon' => 'bx-x-circle', 'text' => 'Not Selected'],
                    'blocked' => ['color' => 'dark', 'icon' => 'bx-block', 'text' => 'Blocked'],
                    'not_placed' => ['color' => 'secondary', 'icon' => 'bx-info-circle', 'text' => 'Not Placed']
                  ];
                  $config = $status_config[$app['status']] ?? ['color' => 'secondary', 'icon' => 'bx-info-circle', 'text' => 'Unknown'];
                ?>
                  <div class="timeline-item">
                    <div class="timeline-marker bg-<?= $config['color'] ?>">
                      <i class='bx <?= $config['icon'] ?>'></i>
                    </div>
                    <div class="timeline-content">
                      <div class="timeline-header">
                        <h5 class="mb-1"><?= htmlspecialchars($app['company_name']) ?></h5>
                        <small class="text-muted">
                          <i class='bx bx-calendar'></i>
                          <?= date('M d, Y h:i A', strtotime($app['applied_at'])) ?>
                        </small>
                      </div>
                      <div class="timeline-body">
                        <?php if ($app['designation_name']): ?>
                          <p class="mb-2">
                            <i class='bx bx-briefcase'></i>
                            <strong>Role:</strong> <?= htmlspecialchars($app['designation_name']) ?>
                            <span class="badge bg-<?= $app['offer_type'] == 'Internship' ? 'info' : 'success' ?> ms-2">
                              <?= htmlspecialchars($app['offer_type']) ?>
                            </span>
                          </p>
                        <?php endif; ?>

                        <?php if ($app['offer_type'] == 'Internship' && $app['stipend']): ?>
                          <p class="mb-2">
                            <i class='bx bx-money'></i>
                            <strong>Stipend:</strong> ₹<?= htmlspecialchars($app['stipend']) ?>
                          </p>
                        <?php elseif ($app['ctc']): ?>
                          <p class="mb-2">
                            <i class='bx bx-money'></i>
                            <strong>CTC:</strong> ₹<?= htmlspecialchars($app['ctc']) ?>
                          </p>
                        <?php endif; ?>

                        <?php if (!empty($app['rounds'])): ?>
                          <div class="rounds-section mt-3 mb-3">
                            <h6 style="color: #650000; margin-bottom: 10px;">
                              <i class='bx bx-list-ol'></i> Round-wise Progress
                            </h6>
                            <div class="rounds-list">
                              <?php foreach ($app['rounds'] as $round):
                                $round_config = [
                                  'shortlisted' => ['color' => 'success', 'icon' => 'bx-check-circle', 'text' => 'Shortlisted'],
                                  'rejected' => ['color' => 'danger', 'icon' => 'bx-x-circle', 'text' => 'Not Selected'],
                                  'pending' => ['color' => 'warning', 'icon' => 'bx-time-five', 'text' => 'Pending'],
                                  'not_conducted' => ['color' => 'secondary', 'icon' => 'bx-info-circle', 'text' => 'Not Conducted']
                                ];
                                $round_style = $round_config[$round['result']] ?? ['color' => 'secondary', 'icon' => 'bx-info-circle', 'text' => 'Unknown'];
                              ?>
                                <div class="round-badge" style="
                                  background: <?= $round['result'] == 'shortlisted' ? '#e8f5e9' : ($round['result'] == 'rejected' ? '#ffebee' : ($round['result'] == 'pending' ? '#fff8e1' : '#f5f5f5')) ?>;
                                  border-left: 3px solid;
                                  border-left-color: <?= $round['result'] == 'shortlisted' ? '#4CAF50' : ($round['result'] == 'rejected' ? '#f44336' : ($round['result'] == 'pending' ? '#FFC107' : '#9E9E9E')) ?>;
                                  padding: 10px 12px;
                                  border-radius: 6px;
                                  margin-bottom: 8px;
                                ">
                                  <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                      <strong style="color: #333;"><?= htmlspecialchars($round['round_name']) ?></strong>
                                      <span class="badge bg-secondary" style="font-size: 11px; margin-left: 8px;"><?= htmlspecialchars($round['round_type']) ?></span>
                                      <?php if ($round['scheduled_date']): ?>
                                        <br><small style="color: #666;">
                                          <i class='bx bx-calendar'></i> <?= date('M d, Y', strtotime($round['scheduled_date'])) ?>
                                        </small>
                                      <?php endif; ?>
                                    </div>
                                    <span class="badge bg-<?= $round_style['color'] ?>" style="padding: 6px 10px;">
                                      <i class='bx <?= $round_style['icon'] ?>'></i> <?= $round_style['text'] ?>
                                    </span>
                                  </div>
                                  <?php if (!empty($round['comments'])): ?>
                                    <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(0,0,0,0.1);">
                                      <small style="color: #555;"><strong>Note:</strong> <?= htmlspecialchars($round['comments']) ?></small>
                                    </div>
                                  <?php endif; ?>
                                </div>
                              <?php endforeach; ?>
                            </div>
                          </div>
                        <?php endif; ?>

                        <div class="d-flex align-items-center justify-content-between">
                          <span class="badge bg-<?= $config['color'] ?> px-3 py-2">
                            <i class='bx <?= $config['icon'] ?>'></i> <?= $config['text'] ?>
                          </span>
                          <?php if (!empty($app['comments'])): ?>
                            <button class="btn btn-sm btn-outline-secondary"
                                    onclick="showComments('<?= htmlspecialchars(addslashes($app['comments'])) ?>')">
                              <i class='bx bx-comment-detail'></i> View Comments
                            </button>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="text-center py-5">
                <i class='bx bx-inbox' style="font-size: 80px; color: #ccc;"></i>
                <h4 class="mt-3 text-muted">No Applications Yet</h4>
                <p class="text-muted">Start applying to placement drives to track your progress here.</p>
                <a href="student_drives.php" class="btn btn-primary mt-3">
                  <i class='bx bx-briefcase'></i> Browse Opportunities
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.stat-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  display: flex;
  align-items: center;
  gap: 15px;
  transition: all 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.stat-icon {
  width: 60px;
  height: 60px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-icon i {
  font-size: 32px;
}

.stat-details h3 {
  margin: 0;
  font-size: 32px;
  font-weight: 700;
  color: #333;
}

.stat-details p {
  margin: 0;
  color: #666;
  font-size: 14px;
}

.timeline {
  position: relative;
  padding-left: 0;
}

.timeline-item {
  position: relative;
  padding-left: 60px;
  padding-bottom: 40px;
}

.timeline-item:not(:last-child):before {
  content: '';
  position: absolute;
  left: 20px;
  top: 45px;
  bottom: -40px;
  width: 2px;
  background: #e0e0e0;
}

.timeline-marker {
  position: absolute;
  left: 0;
  top: 0;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
  z-index: 1;
}

.timeline-content {
  background: #f8f9fa;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
  transition: all 0.3s ease;
}

.timeline-content:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  transform: translateX(5px);
}

.timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  border-bottom: 1px solid #dee2e6;
  padding-bottom: 10px;
}

.timeline-header h5 {
  margin: 0;
  color: #650000;
  font-weight: 600;
}

.timeline-body p {
  margin-bottom: 8px;
  color: #555;
}

.timeline-body i {
  color: #650000;
  margin-right: 5px;
}

@media (max-width: 768px) {
  .stat-card {
    flex-direction: column;
    text-align: center;
  }

  .timeline-item {
    padding-left: 40px;
  }

  .timeline-marker {
    width: 30px;
    height: 30px;
    font-size: 16px;
  }

  .timeline-item:not(:last-child):before {
    left: 15px;
  }

  .timeline-header {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>

<script>
function showComments(comments) {
  alert(comments);
}
</script>

</body>
</html>
