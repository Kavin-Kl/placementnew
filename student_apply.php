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

$student_id = $_SESSION['student_id'];
$role_id = $_GET['role_id'] ?? null;
$drive_id = $_GET['drive_id'] ?? null;

if (!$role_id || !$drive_id) {
    $_SESSION['error'] = "Invalid application request.";
    header("Location: student_drives.php");
    exit;
}

// Fetch student details
$student_stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();

// Fetch drive details
$drive_stmt = $conn->prepare("SELECT * FROM drives WHERE drive_id = ?");
$drive_stmt->bind_param("i", $drive_id);
$drive_stmt->execute();
$drive = $drive_stmt->get_result()->fetch_assoc();

// Fetch role details
$role_stmt = $conn->prepare("SELECT * FROM drive_roles WHERE role_id = ? AND drive_id = ?");
$role_stmt->bind_param("ii", $role_id, $drive_id);
$role_stmt->execute();
$role = $role_stmt->get_result()->fetch_assoc();

if (!$drive || !$role) {
    $_SESSION['error'] = "Drive or role not found.";
    header("Location: student_drives.php");
    exit;
}

// Check if already applied
$check_stmt = $conn->prepare("SELECT application_id FROM applications WHERE student_id = ? AND role_id = ?");
$check_stmt->bind_param("ii", $student_id, $role_id);
$check_stmt->execute();
if ($check_stmt->get_result()->num_rows > 0) {
    $_SESSION['error'] = "You have already applied for this role.";
    header("Location: student_drives.php");
    exit;
}

// Check eligibility
$eligible_courses = explode(',', $role['eligible_courses']);
$is_eligible = in_array($student['course'], $eligible_courses) ||
               in_array($student['program'], $eligible_courses) ||
               in_array('All', $eligible_courses);

if (!$is_eligible) {
    $_SESSION['error'] = "You are not eligible for this role.";
    header("Location: student_drives.php");
    exit;
}

// Check percentage requirement
if ($role['min_percentage'] && (!$student['percentage'] || $student['percentage'] < $role['min_percentage'])) {
    $_SESSION['error'] = "You do not meet the minimum percentage requirement.";
    header("Location: student_drives.php");
    exit;
}

// Check if drive is still open
$now = date('Y-m-d H:i:s');
if ($drive['close_date'] < $now) {
    $_SESSION['error'] = "This drive has already closed.";
    header("Location: student_drives.php");
    exit;
}

$error = "";
$success = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    $resume_file = null;

    // Handle resume upload
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/resumes/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['pdf', 'doc', 'docx'];

        if (!in_array(strtolower($file_extension), $allowed_extensions)) {
            $error = "Only PDF, DOC, and DOCX files are allowed.";
        } else {
            // Delete old resume files for this student-drive-role combination
            $old_files = glob($upload_dir . 'resume_' . $student_id . '_' . $drive_id . '_' . $role_id . '_*');
            foreach ($old_files as $old_file) {
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }

            $new_filename = 'resume_' . $student_id . '_' . $drive_id . '_' . $role_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['resume']['tmp_name'], $upload_path)) {
                $resume_file = $upload_path;
            } else {
                $error = "Failed to upload resume.";
            }
        }
    }

    if (empty($error)) {
        // Prepare student data as JSON
        $student_data = json_encode([
            'upid' => $student['upid'],
            'reg_no' => $student['reg_no'],
            'student_name' => $student['student_name'],
            'email' => $student['email'],
            'phone_no' => $student['phone_no'],
            'course' => $student['course'],
            'program' => $student['program'],
            'program_type' => $student['program_type'],
            'class' => $student['class'],
            'percentage' => $student['percentage']
        ]);

        // Insert application
        $insert_stmt = $conn->prepare("
            INSERT INTO applications
            (student_id, drive_id, role_id, resume_file, status, student_data, percentage,
             course, upid, reg_no, student_name, placement_batch, applied_at)
            VALUES (?, ?, ?, ?, 'applied', ?, ?, ?, ?, ?, ?, 'original', NOW())
        ");

        $insert_stmt->bind_param(
            "iiissdssss",
            $student_id,
            $drive_id,
            $role_id,
            $resume_file,
            $student_data,
            $student['percentage'],
            $student['course'],
            $student['upid'],
            $student['reg_no'],
            $student['student_name']
        );

        if ($insert_stmt->execute()) {
            // Create notification
            $notif_title = "Application Submitted";
            $notif_message = "Your application for " . $role['designation_name'] . " at " . $drive['company_name'] . " has been submitted successfully.";
            $notif_type = "application";

            $notif_stmt = $conn->prepare("INSERT INTO student_notifications (student_id, title, message, type) VALUES (?, ?, ?, ?)");
            $notif_stmt->bind_param("isss", $student_id, $notif_title, $notif_message, $notif_type);
            $notif_stmt->execute();

            $_SESSION['success'] = "Application submitted successfully!";
            header("Location: student_applications.php");
            exit;
        } else {
            $error = "Failed to submit application. Please try again.";
        }
    }
}

include("student_header.php");
?>

<div class="home-section">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-bottom">
            <h4 class="mb-0">Apply for Position</h4>
          </div>
          <div class="card-body">
            <?php if (!empty($error)): ?>
              <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
              <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <!-- Drive and Role Details -->
            <div class="mb-4 p-3 bg-light rounded">
              <h5><?= htmlspecialchars($drive['company_name']) ?></h5>
              <p class="mb-2"><strong>Role:</strong> <?= htmlspecialchars($role['designation_name']) ?></p>
              <p class="mb-2"><strong>Offer Type:</strong>
                <span class="badge <?= $role['offer_type'] == 'Internship' ? 'bg-info' : 'bg-success' ?>">
                  <?= htmlspecialchars($role['offer_type']) ?>
                </span>
              </p>
              <p class="mb-2"><strong>CTC/Stipend:</strong> ₹<?= htmlspecialchars($role['offer_type'] == 'Internship' ? $role['stipend'] : $role['ctc']) ?></p>
              <p class="mb-0"><strong>Sector:</strong> <?= htmlspecialchars($role['sector'] ?? 'N/A') ?></p>
            </div>

            <!-- Application Form -->
            <form method="POST" enctype="multipart/form-data">
              <h5 class="mb-3">Your Details</h5>

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Full Name</label>
                  <input type="text" class="form-control" value="<?= htmlspecialchars($student['student_name']) ?>" readonly>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Placement ID</label>
                  <input type="text" class="form-control" value="<?= htmlspecialchars($student['upid']) ?>" readonly>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Register Number</label>
                  <input type="text" class="form-control" value="<?= htmlspecialchars($student['reg_no']) ?>" readonly>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input type="text" class="form-control" value="<?= htmlspecialchars($student['email']) ?>" readonly>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Course</label>
                  <input type="text" class="form-control" value="<?= htmlspecialchars($student['course']) ?>" readonly>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Percentage</label>
                  <input type="text" class="form-control" value="<?= htmlspecialchars($student['percentage'] ?? 'Not provided') ?>" readonly>
                </div>
              </div>

              <hr class="my-4">

              <h5 class="mb-3">Upload Resume</h5>
              <div class="mb-3">
                <label for="resume" class="form-label">Resume (PDF, DOC, DOCX) *</label>
                <input type="file" class="form-control" id="resume" name="resume" accept=".pdf,.doc,.docx" required>
                <small class="text-muted">Maximum file size: 5MB</small>
              </div>

              <div class="alert alert-info">
                <i class="bx bx-info-circle"></i>
                <strong>Note:</strong> Please ensure all your profile information is correct before submitting.
                You can update your profile from the <a href="student_profile.php">Profile</a> page.
              </div>

              <div class="d-flex gap-2">
                <button type="submit" name="submit_application" class="btn btn-primary">
                  <i class="bx bx-send"></i> Submit Application
                </button>
                <a href="student_drives.php" class="btn btn-outline-secondary">Cancel</a>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

</body>
</html>
