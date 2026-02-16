<?php
session_start();
require 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$success_message = '';
$error_message = '';

// Handle adding a new round
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_round'])) {
    $application_id = $_POST['application_id'];
    $round_name = trim($_POST['round_name']);
    $round_type = $_POST['round_type'];
    $scheduled_date = !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : null;

    $stmt = $conn->prepare("INSERT INTO application_rounds (application_id, round_name, round_type, scheduled_date) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $application_id, $round_name, $round_type, $scheduled_date);

    if ($stmt->execute()) {
        $success_message = "Round added successfully!";
    } else {
        $error_message = "Failed to add round.";
    }
}

// Handle updating round result
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_result'])) {
    $round_id = $_POST['round_id'];
    $result = $_POST['result'];
    $comments = trim($_POST['comments']);
    $marked_by = $_SESSION['username'];

    $stmt = $conn->prepare("UPDATE application_rounds SET result = ?, comments = ?, marked_by = ?, marked_at = NOW() WHERE round_id = ?");
    $stmt->bind_param("sssi", $result, $comments, $marked_by, $round_id);

    if ($stmt->execute()) {
        $success_message = "Result updated successfully!";
    } else {
        $error_message = "Failed to update result.";
    }
}

// Handle deleting a round
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_round'])) {
    $round_id = $_POST['round_id'];

    $stmt = $conn->prepare("DELETE FROM application_rounds WHERE round_id = ?");
    $stmt->bind_param("i", $round_id);

    if ($stmt->execute()) {
        $success_message = "Round deleted successfully!";
    } else {
        $error_message = "Failed to delete round.";
    }
}

// Handle bulk add round
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_add_round'])) {
    $application_ids = json_decode($_POST['application_ids'], true);
    $round_name = trim($_POST['round_name']);
    $round_type = $_POST['round_type'];
    $scheduled_date = !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : null;

    $success_count = 0;
    $fail_count = 0;

    foreach ($application_ids as $app_id) {
        $stmt = $conn->prepare("INSERT INTO application_rounds (application_id, round_name, round_type, scheduled_date) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $app_id, $round_name, $round_type, $scheduled_date);

        if ($stmt->execute()) {
            $success_count++;
        } else {
            $fail_count++;
        }
    }

    if ($success_count > 0) {
        $success_message = "Round added to $success_count applicant(s) successfully!";
    }
    if ($fail_count > 0) {
        $error_message = "Failed to add round to $fail_count applicant(s).";
    }
}

// Handle bulk update result
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update_result'])) {
    $application_ids = json_decode($_POST['application_ids'], true);
    $round_name = trim($_POST['round_name']);
    $result = $_POST['result'];
    $comments = trim($_POST['comments']);
    $marked_by = $_SESSION['username'];

    $success_count = 0;
    $fail_count = 0;

    foreach ($application_ids as $app_id) {
        // Find the most recent round with this name for this application
        $find_stmt = $conn->prepare("
            SELECT round_id FROM application_rounds
            WHERE application_id = ? AND round_name = ?
            ORDER BY created_at DESC LIMIT 1
        ");
        $find_stmt->bind_param("is", $app_id, $round_name);
        $find_stmt->execute();
        $round_result = $find_stmt->get_result();

        if ($round_row = $round_result->fetch_assoc()) {
            $round_id = $round_row['round_id'];
            $update_stmt = $conn->prepare("UPDATE application_rounds SET result = ?, comments = ?, marked_by = ?, marked_at = NOW() WHERE round_id = ?");
            $update_stmt->bind_param("sssi", $result, $comments, $marked_by, $round_id);

            if ($update_stmt->execute()) {
                $success_count++;
            } else {
                $fail_count++;
            }
        } else {
            $fail_count++;
        }
    }

    if ($success_count > 0) {
        $success_message = "Results updated for $success_count applicant(s) successfully!";
    }
    if ($fail_count > 0) {
        $error_message = "Failed to update results for $fail_count applicant(s).";
    }
}

// Get all drives with applications
$drives_query = "
    SELECT DISTINCT d.drive_id, d.company_name, d.drive_no, COUNT(a.application_id) as app_count
    FROM drives d
    INNER JOIN applications a ON d.drive_id = a.drive_id
    GROUP BY d.drive_id
    ORDER BY d.drive_no DESC
";
$drives = $conn->query($drives_query);

// Get selected drive applications
$selected_drive = $_GET['drive_id'] ?? null;
$applications = [];

if ($selected_drive) {
    $apps_query = "
        SELECT
            a.application_id,
            a.student_id,
            a.status,
            s.student_name,
            s.upid,
            s.email,
            s.course,
            dr.designation_name,
            dr.offer_type,
            ps.company_name AS placed_company,
            ps.role AS placed_role,
            ps.offer_type AS placed_offer_type,
            DATE_FORMAT(ps.placed_date, '%d-%b-%Y') AS placed_date,
            ps.place_id
        FROM applications a
        INNER JOIN students s ON a.student_id = s.student_id
        LEFT JOIN drive_roles dr ON a.role_id = dr.role_id
        LEFT JOIN placed_students ps ON a.student_id = ps.student_id
        WHERE a.drive_id = ?
        ORDER BY ps.place_id IS NULL DESC, s.student_name ASC
    ";

    $stmt = $conn->prepare($apps_query);
    $stmt->bind_param("i", $selected_drive);
    $stmt->execute();
    $applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get rounds for each application
    foreach ($applications as &$app) {
        $rounds_stmt = $conn->prepare("
            SELECT * FROM application_rounds
            WHERE application_id = ?
            ORDER BY created_at ASC
        ");
        $rounds_stmt->bind_param("i", $app['application_id']);
        $rounds_stmt->execute();
        $app['rounds'] = $rounds_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

require 'header.php';
?>

<style>
.drive-selector {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.drive-card {
    background: white;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    border-left: 4px solid #650000;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    cursor: pointer;
    transition: all 0.3s ease;
}

.drive-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transform: translateX(5px);
}

.drive-card.active {
    background: #f5f5f5;
    border-left-color: #4CAF50;
}

.application-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.application-card.placed-student {
    background: #f5f5f5;
    opacity: 0.7;
    border-left: 4px solid #4CAF50;
}

.application-card.placed-student .student-header h5,
.application-card.placed-student .student-header small {
    color: #666;
}

.placement-info {
    background: #e8f5e9;
    border-left: 3px solid #4CAF50;
    padding: 10px 15px;
    margin: 10px 0;
    border-radius: 5px;
    font-size: 14px;
}

.placement-info i {
    color: #4CAF50;
    margin-right: 5px;
}

.student-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 15px;
    border-bottom: 2px solid #e0e0e0;
    margin-bottom: 15px;
}

.rounds-list {
    margin-top: 15px;
}

.round-item {
    background: #f8f9fa;
    border-left: 4px solid #2196F3;
    padding: 15px;
    margin-bottom: 10px;
    border-radius: 6px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.round-item.shortlisted {
    border-left-color: #4CAF50;
    background: #f1f8f4;
}

.round-item.rejected {
    border-left-color: #f44336;
    background: #fff5f5;
}

.round-item.pending {
    border-left-color: #FFC107;
    background: #fffbf0;
}

.round-details {
    flex: 1;
}

.round-actions {
    display: flex;
    gap: 10px;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    overflow-y: auto;
}

.modal-content {
    background: white;
    margin: 50px auto;
    padding: 30px;
    border-radius: 12px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e0e0e0;
}

.close {
    font-size: 28px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
}

.close:hover {
    color: #000;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #650000;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary {
    background: #650000;
    color: white;
}

.btn-primary:hover {
    background: #800000;
}

.btn-success {
    background: #4CAF50;
    color: white;
}

.btn-success:hover {
    background: #45a049;
}

.btn-danger {
    background: #f44336;
    color: white;
}

.btn-danger:hover {
    background: #da190b;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}

.badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.badge-success {
    background: #4CAF50;
    color: white;
}

.badge-danger {
    background: #f44336;
    color: white;
}

.badge-warning {
    background: #FFC107;
    color: #333;
}

.badge-secondary {
    background: #6c757d;
    color: white;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state i {
    font-size: 80px;
    margin-bottom: 20px;
}
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h3><i class='bx bx-list-check'></i> Manage Round-wise Results</h3>
            <p class="text-muted">Track student progress through different interview rounds</p>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class='bx bx-check-circle'></i> <?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class='bx bx-error'></i> <?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="drive-selector">
                <h5 class="mb-3"><i class='bx bx-briefcase'></i> Select Drive</h5>

                <!-- Search Box -->
                <div class="mb-3">
                    <input type="text"
                           id="driveSearch"
                           class="form-control"
                           placeholder="Search company name..."
                           style="border-radius: 8px;">
                </div>

                <div id="driveList">
                    <?php if ($drives->num_rows > 0): ?>
                        <?php while ($drive = $drives->fetch_assoc()): ?>
                            <div class="drive-card <?= $selected_drive == $drive['drive_id'] ? 'active' : '' ?>"
                                 data-company="<?= htmlspecialchars(strtolower($drive['company_name'])) ?>"
                                 onclick="window.location.href='manage_rounds.php?drive_id=<?= $drive['drive_id'] ?>'">
                                <h6 class="mb-1"><?= htmlspecialchars($drive['company_name']) ?></h6>
                                <small class="text-muted">
                                    Drive #<?= $drive['drive_no'] ?> • <?= $drive['app_count'] ?> applications
                                </small>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class='bx bx-inbox'></i>
                            <p>No applications yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <?php if ($selected_drive && count($applications) > 0): ?>
                <!-- Search Box for Students -->
                <div class="student-search-bar" style="background: white; border-radius: 12px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <div class="d-flex align-items-center gap-2">
                        <i class='bx bx-search' style="font-size: 20px; color: #650000;"></i>
                        <input type="text"
                               id="studentSearchInput"
                               class="form-control"
                               placeholder="Search students by name, UPID, email, or course..."
                               onkeyup="filterStudents()"
                               style="border: 1px solid #ddd; border-radius: 8px; padding: 10px 15px;">
                        <button type="button" class="btn btn-outline-secondary" onclick="clearStudentSearch()" id="clearSearchBtn" style="display: none;">
                            <i class='bx bx-x'></i> Clear
                        </button>
                    </div>
                    <div id="searchResultsCount" style="margin-top: 10px; color: #666; font-size: 14px; display: none;">
                        <i class='bx bx-info-circle'></i> <span id="resultsText"></span>
                    </div>
                </div>

                <!-- Bulk Actions Bar -->
                <div class="bulk-actions-bar" style="background: white; border-radius: 12px; padding: 15px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="selectAllApplicants()">
                                <i class='bx bx-check-square'></i> Select All
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="deselectAllApplicants()">
                                <i class='bx bx-x'></i> Deselect All
                            </button>
                            <span id="selected-count" class="ms-3 text-muted">0 selected</span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-success btn-sm" onclick="openBulkAddRoundModal()" id="bulk-add-btn" disabled>
                                <i class='bx bx-plus-circle'></i> Add Round to Selected
                            </button>
                            <button type="button" class="btn btn-warning btn-sm" onclick="openBulkUpdateResultModal()" id="bulk-update-btn" disabled>
                                <i class='bx bx-edit'></i> Update Result for Selected
                            </button>
                        </div>
                    </div>
                </div>

                <?php foreach ($applications as $app): ?>
                    <?php $is_placed = !empty($app['place_id']); ?>
                    <div class="application-card <?= $is_placed ? 'placed-student' : '' ?>">
                        <div class="student-header">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <input type="checkbox" class="applicant-checkbox" value="<?= $app['application_id'] ?>"
                                       style="width: 20px; height: 20px; cursor: pointer;" onchange="updateSelectedCount()"
                                       <?= $is_placed ? 'disabled title="Student already placed"' : '' ?>>
                                <div>
                                    <h5 class="mb-1">
                                        <?= htmlspecialchars($app['student_name']) ?>
                                        <?php if ($is_placed): ?>
                                            <span class="badge bg-success ms-2" style="font-size: 11px;">
                                                <i class='bx bx-check-circle'></i> Already Placed
                                            </span>
                                        <?php endif; ?>
                                    </h5>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($app['upid']) ?> •
                                        <?= htmlspecialchars($app['course']) ?>
                                        <?php if ($app['designation_name']): ?>
                                            • <?= htmlspecialchars($app['designation_name']) ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                            <div>
                                <span class="badge badge-<?=
                                    $app['status'] == 'placed' ? 'success' :
                                    ($app['status'] == 'rejected' ? 'danger' : 'warning')
                                ?>">
                                    <?= ucfirst($app['status']) ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($is_placed): ?>
                            <div class="placement-info">
                                <i class='bx bx-info-circle'></i>
                                <strong>Placement Details:</strong>
                                Student placed at <strong><?= htmlspecialchars($app['placed_company']) ?></strong>
                                as <strong><?= htmlspecialchars($app['placed_role']) ?></strong>
                                (<?= htmlspecialchars($app['placed_offer_type']) ?>)
                                on <?= $app['placed_date'] ?>
                            </div>
                        <?php endif; ?>

                        <div class="rounds-list">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6><i class='bx bx-list-ol'></i> Rounds</h6>
                                <button class="btn btn-primary btn-sm" onclick="openAddRoundModal(<?= $app['application_id'] ?>, '<?= htmlspecialchars(addslashes($app['student_name'])) ?>')">
                                    <i class='bx bx-plus'></i> Add Round
                                </button>
                            </div>

                            <?php if (count($app['rounds']) > 0): ?>
                                <?php foreach ($app['rounds'] as $round): ?>
                                    <div class="round-item <?= $round['result'] ?>">
                                        <div class="round-details">
                                            <strong><?= htmlspecialchars($round['round_name']) ?></strong>
                                            <span class="badge badge-secondary ms-2"><?= htmlspecialchars($round['round_type']) ?></span>
                                            <br>
                                            <small class="text-muted">
                                                <?php if ($round['scheduled_date']): ?>
                                                    <i class='bx bx-calendar'></i> <?= date('M d, Y h:i A', strtotime($round['scheduled_date'])) ?>
                                                <?php endif; ?>
                                                <?php if ($round['marked_at']): ?>
                                                    • Updated by <?= htmlspecialchars($round['marked_by']) ?> on <?= date('M d, Y', strtotime($round['marked_at'])) ?>
                                                <?php endif; ?>
                                            </small>
                                            <?php if ($round['comments']): ?>
                                                <br><small><strong>Comments:</strong> <?= htmlspecialchars($round['comments']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="round-actions">
                                            <button class="btn btn-success btn-sm"
                                                    onclick="openUpdateResultModal(<?= $round['round_id'] ?>, '<?= htmlspecialchars(addslashes($round['round_name'])) ?>', '<?= $round['result'] ?>', '<?= htmlspecialchars(addslashes($round['comments'])) ?>')">
                                                <i class='bx bx-edit'></i> Result
                                            </button>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this round?')">
                                                <input type="hidden" name="round_id" value="<?= $round['round_id'] ?>">
                                                <button type="submit" name="delete_round" class="btn btn-danger btn-sm">
                                                    <i class='bx bx-trash'></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted text-center py-3">No rounds added yet</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php elseif ($selected_drive): ?>
                <div class="empty-state">
                    <i class='bx bx-user-x'></i>
                    <h5>No Applications</h5>
                    <p>No students have applied to this drive yet.</p>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class='bx bx-pointer'></i>
                    <h5>Select a Drive</h5>
                    <p>Choose a drive from the left to manage rounds</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Round Modal -->
<div id="addRoundModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5><i class='bx bx-plus-circle'></i> Add New Round</h5>
            <span class="close" onclick="closeModal('addRoundModal')">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="application_id" id="add_application_id">

            <div class="form-group">
                <label>Student</label>
                <input type="text" id="add_student_name" readonly style="background: #f0f0f0;">
            </div>

            <div class="form-group">
                <label for="round_name">Round Name *</label>
                <input type="text" name="round_name" id="round_name" placeholder="e.g., Group Discussion, Technical Round 1" required>
            </div>

            <div class="form-group">
                <label for="round_type">Round Type *</label>
                <select name="round_type" id="round_type" required>
                    <option value="GD">Group Discussion</option>
                    <option value="Aptitude">Aptitude Test</option>
                    <option value="Technical">Technical Interview</option>
                    <option value="HR">HR Interview</option>
                    <option value="Case Study">Case Study</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="scheduled_date">Scheduled Date & Time (Optional)</label>
                <input type="datetime-local" name="scheduled_date" id="scheduled_date">
            </div>

            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addRoundModal')">Cancel</button>
                <button type="submit" name="add_round" class="btn btn-primary">
                    <i class='bx bx-plus'></i> Add Round
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Update Result Modal -->
<div id="updateResultModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5><i class='bx bx-edit'></i> Update Round Result</h5>
            <span class="close" onclick="closeModal('updateResultModal')">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="round_id" id="update_round_id">

            <div class="form-group">
                <label>Round Name</label>
                <input type="text" id="update_round_name" readonly style="background: #f0f0f0;">
            </div>

            <div class="form-group">
                <label for="result">Result *</label>
                <select name="result" id="update_result" required>
                    <option value="pending">Pending</option>
                    <option value="shortlisted">Shortlisted</option>
                    <option value="rejected">Rejected</option>
                    <option value="not_conducted">Not Conducted</option>
                </select>
            </div>

            <div class="form-group">
                <label for="comments">Comments (Optional)</label>
                <textarea name="comments" id="update_comments" rows="3" placeholder="Add any additional notes..."></textarea>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary" onclick="closeModal('updateResultModal')">Cancel</button>
                <button type="submit" name="update_result" class="btn btn-success">
                    <i class='bx bx-save'></i> Update Result
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Add Round Modal -->
<div id="bulkAddRoundModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5><i class='bx bx-plus-circle'></i> Add Round to Selected Applicants</h5>
            <span class="close" onclick="closeModal('bulkAddRoundModal')">&times;</span>
        </div>
        <form method="POST" id="bulkAddRoundForm">
            <input type="hidden" name="application_ids" id="bulk_add_application_ids">

            <div class="alert alert-info">
                <i class='bx bx-info-circle'></i>
                <span id="bulk-add-count-text">This round will be added to selected applicants.</span>
            </div>

            <div class="form-group">
                <label for="bulk_round_name">Round Name *</label>
                <input type="text" name="round_name" id="bulk_round_name" placeholder="e.g., Group Discussion, Technical Round 1" required>
            </div>

            <div class="form-group">
                <label for="bulk_round_type">Round Type *</label>
                <select name="round_type" id="bulk_round_type" required>
                    <option value="">-- Select Type --</option>
                    <option value="GD">Group Discussion</option>
                    <option value="Technical">Technical</option>
                    <option value="HR">HR</option>
                    <option value="Aptitude">Aptitude</option>
                    <option value="Case Study">Case Study</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="bulk_scheduled_date">Scheduled Date & Time (Optional)</label>
                <input type="datetime-local" name="scheduled_date" id="bulk_scheduled_date">
            </div>

            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary" onclick="closeModal('bulkAddRoundModal')">Cancel</button>
                <button type="submit" name="bulk_add_round" class="btn btn-success">
                    <i class='bx bx-plus'></i> Add Round to Selected
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Update Result Modal -->
<div id="bulkUpdateResultModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5><i class='bx bx-edit'></i> Update Results for Selected Applicants</h5>
            <span class="close" onclick="closeModal('bulkUpdateResultModal')">&times;</span>
        </div>
        <form method="POST" id="bulkUpdateResultForm">
            <input type="hidden" name="application_ids" id="bulk_update_application_ids">

            <div class="alert alert-info">
                <i class='bx bx-info-circle'></i>
                <span id="bulk-update-count-text">Results will be updated for selected applicants.</span>
            </div>

            <div class="form-group">
                <label for="bulk_update_round_name">Round Name *</label>
                <input type="text" name="round_name" id="bulk_update_round_name" placeholder="e.g., Group Discussion" required>
                <small class="text-muted">Enter the exact round name to update</small>
            </div>

            <div class="form-group">
                <label for="bulk_update_result">Result *</label>
                <select name="result" id="bulk_update_result" required>
                    <option value="pending">Pending</option>
                    <option value="shortlisted">Shortlisted</option>
                    <option value="rejected">Rejected</option>
                    <option value="not_conducted">Not Conducted</option>
                </select>
            </div>

            <div class="form-group">
                <label for="bulk_update_comments">Comments (Optional)</label>
                <textarea name="comments" id="bulk_update_comments" rows="3" placeholder="Add any additional notes..."></textarea>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary" onclick="closeModal('bulkUpdateResultModal')">Cancel</button>
                <button type="submit" name="bulk_update_result" class="btn btn-warning">
                    <i class='bx bx-save'></i> Update Results for Selected
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddRoundModal(applicationId, studentName) {
    document.getElementById('add_application_id').value = applicationId;
    document.getElementById('add_student_name').value = studentName;
    document.getElementById('addRoundModal').style.display = 'block';
}

function openUpdateResultModal(roundId, roundName, currentResult, currentComments) {
    document.getElementById('update_round_id').value = roundId;
    document.getElementById('update_round_name').value = roundName;
    document.getElementById('update_result').value = currentResult;
    document.getElementById('update_comments').value = currentComments || '';
    document.getElementById('updateResultModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Student Search Filter Functions
function filterStudents() {
    const searchInput = document.getElementById('studentSearchInput');
    const searchValue = searchInput.value.toLowerCase().trim();
    const clearBtn = document.getElementById('clearSearchBtn');
    const resultsCount = document.getElementById('searchResultsCount');
    const resultsText = document.getElementById('resultsText');

    // Show/hide clear button
    clearBtn.style.display = searchValue ? 'block' : 'none';

    const applicationCards = document.querySelectorAll('.application-card');
    let visibleCount = 0;
    let totalCount = applicationCards.length;

    applicationCards.forEach(card => {
        // Get student information from the card
        const studentName = card.querySelector('.student-header h5').textContent.toLowerCase();
        const studentInfo = card.querySelector('.student-header small').textContent.toLowerCase();

        // Check if search value matches any field
        const matches = searchValue === '' ||
                       studentName.includes(searchValue) ||
                       studentInfo.includes(searchValue);

        // Show/hide the card
        if (matches) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Update results count
    if (searchValue) {
        resultsCount.style.display = 'block';
        resultsText.textContent = `Showing ${visibleCount} of ${totalCount} students`;
    } else {
        resultsCount.style.display = 'none';
    }

    // Update selected count after filtering
    updateSelectedCount();
}

function clearStudentSearch() {
    document.getElementById('studentSearchInput').value = '';
    filterStudents();
}

// Bulk Operations Functions
function selectAllApplicants() {
    const checkboxes = document.querySelectorAll('.applicant-checkbox');
    checkboxes.forEach(cb => cb.checked = true);
    updateSelectedCount();
}

function deselectAllApplicants() {
    const checkboxes = document.querySelectorAll('.applicant-checkbox');
    checkboxes.forEach(cb => cb.checked = false);
    updateSelectedCount();
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.applicant-checkbox:checked');
    const count = checkboxes.length;
    document.getElementById('selected-count').textContent = count + ' selected';

    // Enable/disable bulk action buttons
    const bulkAddBtn = document.getElementById('bulk-add-btn');
    const bulkUpdateBtn = document.getElementById('bulk-update-btn');

    if (count > 0) {
        bulkAddBtn.disabled = false;
        bulkUpdateBtn.disabled = false;
    } else {
        bulkAddBtn.disabled = true;
        bulkUpdateBtn.disabled = true;
    }
}

function getSelectedApplicationIds() {
    const checkboxes = document.querySelectorAll('.applicant-checkbox:checked');
    const ids = [];
    checkboxes.forEach(cb => ids.push(cb.value));
    return ids;
}

function openBulkAddRoundModal() {
    const selectedIds = getSelectedApplicationIds();

    if (selectedIds.length === 0) {
        alert('Please select at least one applicant.');
        return;
    }

    document.getElementById('bulk_add_application_ids').value = JSON.stringify(selectedIds);
    document.getElementById('bulk-add-count-text').textContent =
        'This round will be added to ' + selectedIds.length + ' selected applicant(s).';
    document.getElementById('bulkAddRoundModal').style.display = 'block';
}

function openBulkUpdateResultModal() {
    const selectedIds = getSelectedApplicationIds();

    if (selectedIds.length === 0) {
        alert('Please select at least one applicant.');
        return;
    }

    document.getElementById('bulk_update_application_ids').value = JSON.stringify(selectedIds);
    document.getElementById('bulk-update-count-text').textContent =
        'Results will be updated for ' + selectedIds.length + ' selected applicant(s).';
    document.getElementById('bulkUpdateResultModal').style.display = 'block';
}

// Auto-dismiss alerts
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (alert) {
            alert.style.display = 'none';
        }
    });
}, 3000);

// Drive search functionality
document.getElementById('driveSearch')?.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const driveCards = document.querySelectorAll('.drive-card');

    driveCards.forEach(card => {
        const companyName = card.getAttribute('data-company');
        if (companyName.includes(searchTerm)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
});
</script>

<?php require 'footer.php'; ?>
