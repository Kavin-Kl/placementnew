<?php
session_start();
require 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

require 'header.php';

$student_data = null;
$applications = [];
$error_message = '';

// Handle student search
if (isset($_GET['student_id']) && !empty($_GET['student_id'])) {
    $student_id = trim($_GET['student_id']);

    // Fetch student details
    $student_stmt = $conn->prepare("
        SELECT * FROM students WHERE student_id = ? OR upid = ? OR email = ?
    ");
    $student_stmt->bind_param("sss", $student_id, $student_id, $student_id);
    $student_stmt->execute();
    $result = $student_stmt->get_result();

    if ($result->num_rows > 0) {
        $student_data = $result->fetch_assoc();

        // Fetch applications - Using DISTINCT to prevent any potential duplicates
        $app_stmt = $conn->prepare("
            SELECT DISTINCT
                a.application_id,
                a.drive_id,
                a.role_id,
                a.status,
                a.applied_at,
                a.comments,
                a.status_changed,
                d.company_name,
                d.drive_no,
                dr.designation_name,
                dr.offer_type,
                dr.ctc,
                dr.stipend
            FROM applications a
            INNER JOIN drives d ON a.drive_id = d.drive_id
            LEFT JOIN drive_roles dr ON a.role_id = dr.role_id
            WHERE a.student_id = ?
            GROUP BY a.application_id
            ORDER BY a.applied_at DESC
        ");
        $app_stmt->bind_param("i", $student_data['student_id']);
        $app_stmt->execute();
        $result = $app_stmt->get_result();

        // Manually fetch to ensure uniqueness by application_id
        $applications = [];
        $seen_ids = [];
        while ($row = $result->fetch_assoc()) {
            // Only add if we haven't seen this application_id before
            if (!in_array($row['application_id'], $seen_ids)) {
                $applications[] = $row;
                $seen_ids[] = $row['application_id'];
            }
        }

        // Fetch rounds for each application
        $apps_with_rounds = [];
        foreach ($applications as $app) {
            $rounds_stmt = $conn->prepare("
                SELECT * FROM application_rounds
                WHERE application_id = ?
                ORDER BY created_at ASC
            ");
            $rounds_stmt->bind_param("i", $app['application_id']);
            $rounds_stmt->execute();
            $app['rounds'] = $rounds_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $apps_with_rounds[] = $app;
        }
        $applications = $apps_with_rounds;
    } else {
        $error_message = "No student found with ID/UPID/Email: " . htmlspecialchars($student_id);
    }
}

// Calculate statistics
$total_apps = count($applications);
$placed_count = 0;
$pending_count = 0;
$rejected_count = 0;

foreach ($applications as $app) {
    if ($app['status'] == 'placed') $placed_count++;
    elseif ($app['status'] == 'rejected') $rejected_count++;
    elseif ($app['status'] == 'applied' || $app['status'] == 'pending') $pending_count++;
}
?>

<style>
.search-card {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.search-input-group {
    display: flex;
    gap: 10px;
    max-width: 800px;
}

.search-input-group input {
    flex: 1;
    padding: 12px 20px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 16px;
    transition: all 0.3s ease;
}

.search-input-group input:focus {
    border-color: #650000;
    outline: none;
    box-shadow: 0 0 0 3px rgba(101, 0, 0, 0.1);
}

.search-btn {
    padding: 12px 30px;
    background: #650000;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.search-btn:hover {
    background: #800000;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(101, 0, 0, 0.3);
}

.student-info-card {
    background: linear-gradient(135deg, #650000 0%, #800000 100%);
    color: white;
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.student-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-item i {
    font-size: 24px;
    opacity: 0.8;
}

.info-item .label {
    font-size: 12px;
    opacity: 0.8;
    margin-bottom: 5px;
}

.info-item .value {
    font-size: 16px;
    font-weight: 600;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.stat-card .stat-number {
    font-size: 48px;
    font-weight: 700;
    margin-bottom: 10px;
}

.stat-card .stat-label {
    color: #666;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 1px;
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
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-left: 4px solid #e0e0e0;
    transition: all 0.3s ease;
}

.timeline-content:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    transform: translateX(5px);
}

.timeline-content.status-placed {
    border-left-color: #4CAF50;
}

.timeline-content.status-rejected {
    border-left-color: #f44336;
}

.timeline-content.status-applied {
    border-left-color: #2196F3;
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.timeline-header h5 {
    margin: 0;
    color: #650000;
    font-weight: 600;
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.empty-state i {
    font-size: 100px;
    color: #e0e0e0;
    margin-bottom: 20px;
}

.error-message {
    background: #ffebee;
    color: #c62828;
    padding: 15px 20px;
    border-radius: 8px;
    border-left: 4px solid #c62828;
    margin-bottom: 20px;
}
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h3><i class='bx bx-search-alt'></i> Student Progress Lookup</h3>
            <p class="text-muted">Search by Student ID, UPID, or Email to view their placement progress</p>
        </div>
    </div>

    <!-- Search Form -->
    <div class="search-card">
        <form method="GET" action="admin_student_progress.php">
            <div class="search-input-group">
                <input
                    type="text"
                    name="student_id"
                    placeholder="Enter Student ID, UPID, or Email..."
                    value="<?= htmlspecialchars($_GET['student_id'] ?? '') ?>"
                    required
                >
                <button type="submit" class="search-btn">
                    <i class='bx bx-search'></i> Search
                </button>
            </div>
        </form>
    </div>

    <?php if ($error_message): ?>
        <div class="error-message">
            <i class='bx bx-error-circle'></i> <?= $error_message ?>
        </div>
    <?php endif; ?>

    <?php if ($student_data): ?>
        <!-- Student Info Card -->
        <div class="student-info-card">
            <h4><i class='bx bx-user-circle'></i> Student Information</h4>
            <div class="student-info-grid">
                <div class="info-item">
                    <div>
                        <div class="label">Name</div>
                        <div class="value"><?= htmlspecialchars($student_data['student_name']) ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div>
                        <div class="label">Student ID</div>
                        <div class="value"><?= htmlspecialchars($student_data['student_id']) ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div>
                        <div class="label">UPID</div>
                        <div class="value"><?= htmlspecialchars($student_data['upid'] ?? 'N/A') ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div>
                        <div class="label">Email</div>
                        <div class="value"><?= htmlspecialchars($student_data['email']) ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div>
                        <div class="label">Course</div>
                        <div class="value"><?= htmlspecialchars($student_data['course']) ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div>
                        <div class="label">Program Type</div>
                        <div class="value"><?= htmlspecialchars($student_data['program_type']) ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div>
                        <div class="label">Percentage</div>
                        <div class="value"><?= htmlspecialchars($student_data['percentage'] ?? 'N/A') ?>%</div>
                    </div>
                </div>
                <div class="info-item">
                    <div>
                        <div class="label">Phone</div>
                        <div class="value"><?= htmlspecialchars($student_data['phone'] ?? 'N/A') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-number" style="color: #2196F3;"><?= $total_apps ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-number" style="color: #4CAF50;"><?= $placed_count ?></div>
                    <div class="stat-label">Placed</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-number" style="color: #FFC107;"><?= $pending_count ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-number" style="color: #f44336;"><?= $rejected_count ?></div>
                    <div class="stat-label">Not Selected</div>
                </div>
            </div>
        </div>

        <!-- Application Timeline -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class='bx bx-history'></i> Application Timeline</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($applications) > 0): ?>
                            <div class="timeline">
                                <?php foreach ($applications as $app):
                                    $status_config = [
                                        'placed' => ['color' => 'success', 'icon' => 'bx-check-circle', 'text' => 'Placed', 'bg' => '#4CAF50'],
                                        'applied' => ['color' => 'primary', 'icon' => 'bx-paper-plane', 'text' => 'Applied', 'bg' => '#2196F3'],
                                        'pending' => ['color' => 'warning', 'icon' => 'bx-time-five', 'text' => 'Pending', 'bg' => '#FFC107'],
                                        'rejected' => ['color' => 'danger', 'icon' => 'bx-x-circle', 'text' => 'Not Selected', 'bg' => '#f44336'],
                                        'blocked' => ['color' => 'dark', 'icon' => 'bx-block', 'text' => 'Blocked', 'bg' => '#424242'],
                                        'not_placed' => ['color' => 'secondary', 'icon' => 'bx-info-circle', 'text' => 'Not Placed', 'bg' => '#9E9E9E']
                                    ];
                                    $config = $status_config[$app['status']] ?? ['color' => 'secondary', 'icon' => 'bx-info-circle', 'text' => 'Unknown', 'bg' => '#9E9E9E'];
                                ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker" style="background: <?= $config['bg'] ?>;">
                                            <i class='bx <?= $config['icon'] ?>'></i>
                                        </div>
                                        <div class="timeline-content status-<?= $app['status'] ?>">
                                            <div class="timeline-header">
                                                <h5><?= htmlspecialchars($app['company_name']) ?></h5>
                                                <small class="text-muted">
                                                    <i class='bx bx-calendar'></i>
                                                    Applied: <?= date('M d, Y h:i A', strtotime($app['applied_at'])) ?>
                                                </small>
                                            </div>
                                            <div class="timeline-body">
                                                <?php if ($app['designation_name']): ?>
                                                    <p class="mb-2">
                                                        <i class='bx bx-briefcase' style="color: #650000;"></i>
                                                        <strong>Role:</strong> <?= htmlspecialchars($app['designation_name']) ?>
                                                        <span class="badge bg-<?= $app['offer_type'] == 'Internship' ? 'info' : 'success' ?> ms-2">
                                                            <?= htmlspecialchars($app['offer_type']) ?>
                                                        </span>
                                                    </p>
                                                <?php endif; ?>

                                                <?php if ($app['offer_type'] == 'Internship' && $app['stipend']): ?>
                                                    <p class="mb-2">
                                                        <i class='bx bx-money' style="color: #650000;"></i>
                                                        <strong>Stipend:</strong> ₹<?= htmlspecialchars($app['stipend']) ?>
                                                    </p>
                                                <?php elseif ($app['ctc']): ?>
                                                    <p class="mb-2">
                                                        <i class='bx bx-money' style="color: #650000;"></i>
                                                        <strong>CTC:</strong> ₹<?= htmlspecialchars($app['ctc']) ?>
                                                    </p>
                                                <?php endif; ?>

                                                <?php if (!empty($app['rounds'])): ?>
                                                    <div class="rounds-section mt-3 mb-3" style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                                                        <h6 style="color: #650000; margin-bottom: 12px;">
                                                            <i class='bx bx-list-ol'></i> Round-wise Progress (<?= count($app['rounds']) ?> rounds)
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
                                                                <div style="
                                                                    background: <?= $round['result'] == 'shortlisted' ? '#e8f5e9' : ($round['result'] == 'rejected' ? '#ffebee' : ($round['result'] == 'pending' ? '#fff8e1' : 'white')) ?>;
                                                                    border-left: 4px solid;
                                                                    border-left-color: <?= $round['result'] == 'shortlisted' ? '#4CAF50' : ($round['result'] == 'rejected' ? '#f44336' : ($round['result'] == 'pending' ? '#FFC107' : '#9E9E9E')) ?>;
                                                                    padding: 12px 15px;
                                                                    border-radius: 6px;
                                                                    margin-bottom: 10px;
                                                                ">
                                                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                                                        <div>
                                                                            <strong style="color: #333; font-size: 15px;"><?= htmlspecialchars($round['round_name']) ?></strong>
                                                                            <span class="badge bg-secondary" style="font-size: 11px; margin-left: 8px;"><?= htmlspecialchars($round['round_type']) ?></span>
                                                                        </div>
                                                                        <span class="badge bg-<?= $round_style['color'] ?>" style="padding: 6px 12px; font-size: 12px;">
                                                                            <i class='bx <?= $round_style['icon'] ?>'></i> <?= $round_style['text'] ?>
                                                                        </span>
                                                                    </div>
                                                                    <div style="display: flex; gap: 20px; font-size: 13px; color: #666;">
                                                                        <?php if ($round['scheduled_date']): ?>
                                                                            <span>
                                                                                <i class='bx bx-calendar'></i> <?= date('M d, Y h:i A', strtotime($round['scheduled_date'])) ?>
                                                                            </span>
                                                                        <?php endif; ?>
                                                                        <?php if ($round['marked_at']): ?>
                                                                            <span>
                                                                                <i class='bx bx-user'></i> Updated by <?= htmlspecialchars($round['marked_by']) ?> on <?= date('M d, Y', strtotime($round['marked_at'])) ?>
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <?php if (!empty($round['comments'])): ?>
                                                                        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(0,0,0,0.1);">
                                                                            <small style="color: #555;">
                                                                                <strong><i class='bx bx-comment-detail'></i> Comments:</strong> <?= htmlspecialchars($round['comments']) ?>
                                                                            </small>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="d-flex align-items-center justify-content-between mt-3">
                                                    <span class="badge bg-<?= $config['color'] ?> px-3 py-2">
                                                        <i class='bx <?= $config['icon'] ?>'></i> <?= $config['text'] ?>
                                                    </span>
                                                    <?php if ($app['status_changed']): ?>
                                                        <small class="text-muted">
                                                            <i class='bx bx-time'></i>
                                                            Status updated: <?= date('M d, Y', strtotime($app['status_changed'])) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if (!empty($app['comments'])): ?>
                                                    <div class="alert alert-info mt-3 mb-0">
                                                        <strong><i class='bx bx-comment-detail'></i> Comments:</strong><br>
                                                        <?= nl2br(htmlspecialchars($app['comments'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class='bx bx-inbox'></i>
                                <h4>No Applications Found</h4>
                                <p class="text-muted">This student hasn't applied to any placement drives yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require 'footer.php'; ?>
