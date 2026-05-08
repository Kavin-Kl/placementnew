<?php
// One-time setup: creates (or refreshes) the "Admin Preview" student account.
// Open this page once in the browser, then log in to the student portal with
// the credentials shown below. Delete or restrict access to this script after
// running it if desired.

require __DIR__ . '/config.php';
require __DIR__ . '/admin_preview_helper.php';

$reg_no   = ADMIN_PREVIEW_REG_NO;
$upid     = ADMIN_PREVIEW_UPID;
$name     = 'Admin Preview';
$email    = 'admin_preview@placement.local';
$password = 'Preview@123';
$hash     = password_hash($password, PASSWORD_DEFAULT);

$check = $conn->prepare("SELECT student_id FROM students WHERE reg_no = ?");
$check->bind_param('s', $reg_no);
$check->execute();
$existing = $check->get_result()->fetch_assoc();
$check->close();

if ($existing) {
    $stmt = $conn->prepare("
        UPDATE students
           SET upid = ?, student_name = ?, email = ?, password_hash = ?,
               is_active = 1, email_verified = 1
         WHERE reg_no = ?
    ");
    $stmt->bind_param('sssss', $upid, $name, $email, $hash, $reg_no);
    $action = 'updated';
} else {
    $stmt = $conn->prepare("
        INSERT INTO students
            (upid, reg_no, student_name, email, password_hash,
             is_active, email_verified, placed_status, created_at)
        VALUES (?, ?, ?, ?, ?, 1, 1, 'not_placed', NOW())
    ");
    $stmt->bind_param('sssss', $upid, $reg_no, $name, $email, $hash);
    $action = 'created';
}

$ok = $stmt->execute();
$err = $ok ? '' : $stmt->error;
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head><title>Admin Preview Student Setup</title>
<style>
body { font-family: 'Segoe UI', sans-serif; background: #f8f9fa; padding: 40px; }
.card { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,.08); padding: 30px; }
h1 { color: #581729; margin-top: 0; }
.cred { background: #fdf6e3; border: 1px solid #e8d8a0; padding: 14px 18px;
        border-radius: 8px; font-family: monospace; font-size: 15px; margin: 18px 0; }
.ok  { color: #197a31; font-weight: 600; }
.err { color: #b00020; font-weight: 600; }
a.btn { display: inline-block; background: #581729; color: #fff; padding: 10px 18px;
        border-radius: 6px; text-decoration: none; margin-top: 10px; }
a.btn:hover { background: #3d0f1c; }
</style></head>
<body>
<div class="card">
  <h1>Admin Preview Student</h1>
  <?php if ($ok): ?>
    <p class="ok">✓ Account <?= htmlspecialchars($action) ?> successfully.</p>
    <p>Use these credentials to log in on the student side and see every drive
       (no filters by academic year, graduating year, or placement category):</p>
    <div class="cred">
      Register Number: <strong><?= htmlspecialchars($reg_no) ?></strong><br>
      Password:        <strong><?= htmlspecialchars($password) ?></strong>
    </div>
    <a class="btn" href="student_login.php">Go to Student Login</a>
  <?php else: ?>
    <p class="err">✗ Failed to set up the preview account.</p>
    <pre><?= htmlspecialchars($err) ?></pre>
  <?php endif; ?>
</div>
</body>
</html>
