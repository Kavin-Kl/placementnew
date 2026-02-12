<?php
session_start();
include("config.php");

$error = "";
$success = "";
$validToken = false;
$email = "";

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    $stmt = $conn->prepare("SELECT email FROM student_password_resets WHERE token = ? AND created_at > (NOW() - INTERVAL 1 HOUR)");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $row = $res->fetch_assoc();
        $email = $row['email'];
        $validToken = true;
    } else {
        $error = "Invalid or expired reset link.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $token = $_POST['token'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    if (empty($newPassword) || empty($confirmPassword)) {
        $error = "Both password fields are required.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        $stmt = $conn->prepare("SELECT email FROM student_password_resets WHERE token = ? AND created_at > (NOW() - INTERVAL 1 HOUR)");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 1) {
            $row = $res->fetch_assoc();
            $email = $row['email'];

            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $updateStmt = $conn->prepare("UPDATE students SET password_hash = ? WHERE email = ?");
            $updateStmt->bind_param("ss", $passwordHash, $email);

            if ($updateStmt->execute()) {
                $deleteStmt = $conn->prepare("DELETE FROM student_password_resets WHERE token = ?");
                $deleteStmt->bind_param("s", $token);
                $deleteStmt->execute();

                $_SESSION['success'] = "Password reset successful! Please login with your new password.";
                header("Location: student_login.php");
                exit;
            } else {
                $error = "Failed to update password. Please try again.";
            }
        } else {
            $error = "Invalid or expired reset link.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password - Student Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Libertinus+Serif:ital,wght@0,400;0,600;0,700;1,400;1,600;1,700&display=swap" rel="stylesheet">
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 0;
      font-family: "Libertinus Serif", serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .container {
      background: white;
      border-radius: 12px;
      padding: 40px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
      max-width: 450px;
      width: 100%;
      margin: 20px;
    }

    h2 {
      text-align: center;
      color: #581729;
      margin-bottom: 10px;
    }

    p {
      text-align: center;
      color: #666;
      margin-bottom: 25px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    label {
      display: block;
      margin-bottom: 5px;
      font-weight: 600;
      color: #581729;
    }

    input[type="password"] {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 14px;
    }

    input:focus {
      outline: none;
      border-color: #581729;
      box-shadow: 0 0 5px rgba(88, 23, 41, 0.2);
    }

    button {
      width: 100%;
      padding: 12px;
      border: none;
      background-color: #581729;
      color: white;
      font-weight: bold;
      border-radius: 6px;
      cursor: pointer;
      font-size: 16px;
    }

    button:hover {
      background-color: #3d0f1c;
    }

    .error-msg {
      background-color: #fdd;
      padding: 10px;
      margin-bottom: 15px;
      color: #900;
      border-radius: 5px;
      text-align: center;
    }

    .success-msg {
      background-color: #dfd;
      padding: 10px;
      margin-bottom: 15px;
      color: #090;
      border-radius: 5px;
      text-align: center;
    }

    .back-link {
      text-align: center;
      margin-top: 15px;
    }

    .back-link a {
      color: #581729;
      text-decoration: none;
    }

    .back-link a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Reset Password</h2>

    <?php if (!empty($error)): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="success-msg"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($validToken): ?>
      <p>Enter your new password for <strong><?= htmlspecialchars($email) ?></strong></p>

      <form method="POST">
        <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token']) ?>">

        <div class="form-group">
          <label for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password" placeholder="At least 6 characters" required>
        </div>

        <div class="form-group">
          <label for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required>
        </div>

        <button type="submit" name="reset_password">Reset Password</button>
      </form>
    <?php else: ?>
      <p>This reset link is invalid or has expired.</p>
    <?php endif; ?>

    <div class="back-link">
      <a href="student_login.php">Back to Login</a>
    </div>
  </div>
</body>
</html>
