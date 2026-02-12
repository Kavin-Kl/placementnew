<?php
session_start(); 
include 'config.php';

$token = $_GET['token'] ?? '';
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['reset_password'])) {
    $token = $_POST['token'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation checks with immediate redirect on error
    if (empty($new_password) || empty($confirm_password)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: reset_password?token=" . urlencode($token));
        exit();
    }
    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: reset_password?token=" . urlencode($token));
        exit();
    }
  $requirements = [];

if (strlen($new_password) < 8) {
    $requirements[] = "8 or more characters";
}
if (!preg_match('/[A-Z]/', $new_password)) {
    $requirements[] = "an uppercase letter";
}
if (!preg_match('/[a-z]/', $new_password)) {
    $requirements[] = "a lowercase letter";
}
if (!preg_match('/[0-9]/', $new_password)) {
    $requirements[] = "a number";
}
if (!preg_match('/[\W]/', $new_password)) {
    $requirements[] = "a special character";
}

if (!empty($requirements)) {
    $last = array_pop($requirements);
    $message = implode(', ', $requirements);
    if (!empty($message)) {
        $message .= " and $last";
    } else {
        $message = $last;
    }

    $_SESSION['error'] = "Your password must include $message.";
    header("Location: reset_password?token=" . urlencode($token));
    exit();
}


    // Token and password reset logic
    $stmt = $conn->prepare("SELECT email, created_at FROM password_resets WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows === 1) {
        $row = $res->fetch_assoc();
        $email = $row['email'];
        $created_at = strtotime($row['created_at']);
        $now = time();

        // Expire after 1 hour (3600 seconds)
        if ($now - $created_at > 3600) {
            $_SESSION['error'] = "This password reset link has expired. Please request a new one.";
            header("Location: reset_password?token=" . urlencode($token));
            exit();
        } else {
            $hashed = password_hash($new_password, PASSWORD_BCRYPT);
            $update = $conn->prepare("UPDATE admin_users SET password_hash = ? WHERE email = ?");
            $update->bind_param("ss", $hashed, $email);
            $update->execute();

            $del = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
            $del->bind_param("s", $token);
            $del->execute();

            $_SESSION['success'] = "Password updated. <a href='index.php'>Click here to login</a>";
            header("Location: reset_password?token=" . urlencode($token));
            exit();
        }
    } else {
        $_SESSION['error'] = "This password reset link is no longer valid. It may have been used already or expired. Please request a new password reset.";
        header("Location: reset_password?token=" . urlencode($token));
        exit();
    }
}
?>

<script>
  function toggleVisibility(inputId, toggleElement) {
    const input = document.getElementById(inputId);
    const isPassword = input.type === "password";
    input.type = isPassword ? "text" : "password";
    toggleElement.textContent = isPassword ? "Hide" : "Show";
  }
window.addEventListener('DOMContentLoaded', () => {
    const errorBox = document.querySelector('.error-msg');
    const successBox = document.querySelector('.success-msg');

    // ❗ Error disappears after 10 seconds (can change as needed)
    if (errorBox) {
      setTimeout(() => {
        errorBox.style.display = 'none';
      }, 20000); // 10 seconds
    }

    // ✅ Success disappears after 50 seconds
    if (successBox) {
      setTimeout(() => {
        successBox.style.display = 'none';
      }, 50000); // 50 seconds
    }
  });
</script>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password</title>
  <style>
    *, *::before, *::after {
      box-sizing: inherit;
    }

    html, body {
      margin: 0;
      padding: 0;
      width: 100%;
      height: 100%;
      font-family: "Times New Roman", Times, serif;
      overflow: hidden;
      box-sizing: border-box;
    }

    .background-image {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
     background: url('images/login_background.png') no-repeat center center/cover;

      filter: blur(4px);
      z-index: -1;
    }

    .center-wrapper {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .reset-box {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 12px;
      width: 400px;
      padding: 30px 20px;
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
      z-index: 1;
    }

    .reset-box h2 {
      text-align: center;
      color: #581729;
      margin-bottom: 20px;
    }

    .form-group {
      margin-bottom: 15px;
    }

    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-weight: bold;
      color: #581729;
    }

    .form-group input {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 6px;
      box-sizing: border-box;
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
    }

    button:hover {
      background-color: #3d0f1c;
    }

    .error-msg {
      background-color: #fdd;
      padding: 10px;
      margin-bottom: 10px;
      color: #900;
      border-radius: 5px;
      text-align: center;
    }

    .success-msg {
      background-color: #dfd;
      padding: 10px;
      margin-bottom: 10px;
      color: #090;
      border-radius: 5px;
      text-align: center;
    }
    .password-wrapper {
  position: relative;
}

.password-wrapper input {
  width: 100%;
  padding-right: 60px;
}

.toggle-password {
  position: absolute;
  top: 50%;
  right: 10px;
  transform: translateY(-50%);
  cursor: pointer;
  color: #581729;
  font-size: 14px;
  user-select: none;
}

  </style>
</head>
<body>
  <div class="background-image"></div>
  <div class="center-wrapper">
    <div class="reset-box">
      <h2>Reset Passsword</h2>

      <?php if (!empty($error)): ?>
        <div class="error-msg"><?= $error ?></div>
      <?php endif; ?>
      
      <?php if (!empty($success)): ?>
        <div class="success-msg"><?= $success ?></div>
      <?php endif; ?>

      <?php if (empty($success)): ?>
        <form method="POST">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

          <div class="form-group">
            <label>New Password</label>
            <div class="password-wrapper">
  <input type="password" id="new_password" name="new_password" required>
  <span class="toggle-password" onclick="toggleVisibility('new_password', this)">Show</span>
</div>
          </div>

          <div class="form-group">
            <label>Confirm Password</label>
            <div class="password-wrapper">
  <input type="password" id="confirm_password" name="confirm_password" required>
  <span class="toggle-password" onclick="toggleVisibility('confirm_password', this)">Show</span>
</div>

          </div>

          <button type="submit" name="reset_password">Reset Password</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
