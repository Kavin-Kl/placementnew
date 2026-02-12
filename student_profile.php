<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit;
}

include("config.php");
include("course_groups_dynamic.php");

$student_id = $_SESSION['student_id'];
$error = "";
$success = "";

// Fetch student details
$student_stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $phone_no = trim($_POST['phone_no']);
    $email = trim($_POST['email']);
    $percentage = floatval($_POST['percentage']);
    $year_of_passing = intval($_POST['year_of_passing']);

    // Update profile
    $update_stmt = $conn->prepare("
        UPDATE students
        SET phone_no = ?, email = ?, percentage = ?, year_of_passing = ?
        WHERE student_id = ?
    ");
    $update_stmt->bind_param("ssdii", $phone_no, $email, $percentage, $year_of_passing, $student_id);

    if ($update_stmt->execute()) {
        $success = "Profile updated successfully!";
        // Refresh student data
        $student_stmt->execute();
        $student = $student_stmt->get_result()->fetch_assoc();
        $_SESSION['student_name'] = $student['student_name'];
        $_SESSION['student_email'] = $student['email'];
    } else {
        $error = "Failed to update profile.";
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif (!password_verify($current_password, $student['password_hash'])) {
        $error = "Current password is incorrect.";
    } else {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $pass_stmt = $conn->prepare("UPDATE students SET password_hash = ? WHERE student_id = ?");
        $pass_stmt->bind_param("si", $new_hash, $student_id);

        if ($pass_stmt->execute()) {
            $success = "Password changed successfully!";
        } else {
            $error = "Failed to change password.";
        }
    }
}

include("student_header.php");

// Flatten courses
$allUG = [];
foreach ($ug_courses_grouped as $group) {
    foreach ($group as $programs) {
        $allUG = array_merge($allUG, $programs);
    }
}
$allPG = [];
foreach ($pg_courses_grouped as $group) {
    foreach ($group as $programs) {
        $allPG = array_merge($allPG, $programs);
    }
}
?>

<div class="home-section">
  <div class="container">
    <div class="row mb-4">
      <div class="col-12">
        <h2>My Profile</h2>
        <p class="text-muted">Manage your personal information and settings</p>
      </div>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="row g-4">
      <!-- Profile Information -->
      <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-bottom">
            <h5 class="mb-0">Profile Information</h5>
          </div>
          <div class="card-body">
            <form method="POST">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Placement ID (UPID)</label>
                  <input type="text" class="form-control" value="<?= htmlspecialchars($student['upid']) ?>" readonly>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Register Number</label>
                  <input type="text" class="form-control" value="<?= htmlspecialchars($student['reg_no']) ?>" readonly>
                </div>
                <div class="col-12">
                  <label class="form-label">Full Name</label>
                  <input type="text" class="form-control" value="<?= htmlspecialchars($student['student_name']) ?>" readonly>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email *</label>
                  <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($student['email']) ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Phone Number *</label>
                  <input type="tel" class="form-control" name="phone_no" value="<?= htmlspecialchars($student['phone_no']) ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Program Type</label>
                  <input type="text" class="form-control" value="<?= htmlspecialchars($student['program_type']) ?>" readonly>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Program</label>
                  <input type="text" class="form-control" value="<?= htmlspecialchars($student['program']) ?>" readonly>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Course</label>
                  <input type="text" class="form-control" value="<?= htmlspecialchars($student['course']) ?>" readonly>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Class/Year</label>
                  <input type="text" class="form-control" value="<?= htmlspecialchars($student['class']) ?>" readonly>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Percentage *</label>
                  <input type="number" step="0.01" class="form-control" name="percentage"
                         value="<?= htmlspecialchars($student['percentage'] ?? '') ?>"
                         placeholder="e.g., 85.5" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Year of Passing *</label>
                  <input type="number" class="form-control" name="year_of_passing"
                         value="<?= htmlspecialchars($student['year_of_passing']) ?>"
                         min="2020" max="2030" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Placement Status</label>
                  <input type="text" class="form-control"
                         value="<?= ucfirst(str_replace('_', ' ', $student['placed_status'])) ?>" readonly>
                </div>
              </div>
              <div class="mt-4">
                <button type="submit" name="update_profile" class="btn btn-primary">
                  <i class="bx bx-save"></i> Update Profile
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Change Password -->
        <div class="card border-0 shadow-sm mt-4">
          <div class="card-header bg-white border-bottom">
            <h5 class="mb-0">Change Password</h5>
          </div>
          <div class="card-body">
            <form method="POST">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label">Current Password</label>
                  <input type="password" class="form-control" name="current_password" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">New Password</label>
                  <input type="password" class="form-control" name="new_password" required>
                  <small class="text-muted">Minimum 6 characters</small>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Confirm New Password</label>
                  <input type="password" class="form-control" name="confirm_password" required>
                </div>
              </div>
              <div class="mt-4">
                <button type="submit" name="change_password" class="btn btn-warning">
                  <i class="bx bx-key"></i> Change Password
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Profile Summary -->
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-bottom">
            <h5 class="mb-0">Profile Summary</h5>
          </div>
          <div class="card-body">
            <div class="text-center mb-3">
              <div class="avatar-circle bg-primary text-white mx-auto" style="width: 100px; height: 100px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 36px; font-weight: bold;">
                <?= strtoupper(substr($student['student_name'], 0, 2)) ?>
              </div>
              <h5 class="mt-3 mb-1"><?= htmlspecialchars($student['student_name']) ?></h5>
              <p class="text-muted small mb-0"><?= htmlspecialchars($student['upid']) ?></p>
            </div>

            <hr>

            <div class="info-item mb-3">
              <small class="text-muted d-block">Email</small>
              <strong><?= htmlspecialchars($student['email']) ?></strong>
            </div>

            <div class="info-item mb-3">
              <small class="text-muted d-block">Phone</small>
              <strong><?= htmlspecialchars($student['phone_no']) ?></strong>
            </div>

            <div class="info-item mb-3">
              <small class="text-muted d-block">Course</small>
              <strong><?= htmlspecialchars($student['course']) ?></strong>
            </div>

            <div class="info-item mb-3">
              <small class="text-muted d-block">Percentage</small>
              <strong><?= $student['percentage'] ? htmlspecialchars($student['percentage']) . '%' : 'Not provided' ?></strong>
            </div>

            <div class="info-item mb-3">
              <small class="text-muted d-block">Year of Passing</small>
              <strong><?= htmlspecialchars($student['year_of_passing']) ?></strong>
            </div>

            <div class="info-item mb-3">
              <small class="text-muted d-block">Account Status</small>
              <span class="badge <?= $student['is_active'] ? 'bg-success' : 'bg-danger' ?>">
                <?= $student['is_active'] ? 'Active' : 'Inactive' ?>
              </span>
            </div>

            <div class="info-item">
              <small class="text-muted d-block">Last Login</small>
              <strong><?= $student['last_login'] ? date('M d, Y h:i A', strtotime($student['last_login'])) : 'Never' ?></strong>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.info-item {
  padding: 8px 0;
}

.avatar-circle {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
</style>

</body>
</html>
