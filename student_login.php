<?php
// Configure session for ngrok compatibility
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0'); // Allow HTTP for local testing
session_start();
include "config.php";

$error = $_SESSION['error'] ?? "";
$success = $_SESSION['success'] ?? "";
$old_email = $_SESSION['old_email'] ?? "";
unset($_SESSION['error'], $_SESSION['success'], $_SESSION['old_email']);

$remember_days = 7;

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    setcookie("student_remember_me", "", time() - 3600);
    header("Location: student_login.php");
    exit;
}

// Cookie auto-login
if (isset($_COOKIE['student_remember_me']) && !isset($_SESSION['student_id'])) {
    $email = base64_decode($_COOKIE['student_remember_me']);
    $stmt = $conn->prepare("SELECT * FROM students WHERE email = ? AND password_hash IS NOT NULL");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
        $row = $res->fetch_assoc();
        $_SESSION['student_id'] = $row['student_id'];
        $_SESSION['student_name'] = $row['student_name'];
        $_SESSION['student_email'] = $row['email'];

        // Update last login
        $update_stmt = $conn->prepare("UPDATE students SET last_login = NOW() WHERE student_id = ?");
        $update_stmt->bind_param("i", $row['student_id']);
        $update_stmt->execute();

        header("Location: student_dashboard.php");
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember_me']);

    $_SESSION['old_email'] = $email;

    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Both fields are required.";
        header("Location: student_login.php");
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM students WHERE email = ? AND password_hash IS NOT NULL");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows === 1) {
        $row = $res->fetch_assoc();

        if (!$row['is_active']) {
            $_SESSION['error'] = "Your account has been deactivated. Please contact the placement cell.";
            header("Location: student_login.php");
            exit;
        }

        if (password_verify($password, $row['password_hash'])) {
            $_SESSION['student_id'] = $row['student_id'];
            $_SESSION['student_name'] = $row['student_name'];
            $_SESSION['student_email'] = $row['email'];

            // Update last login
            $update_stmt = $conn->prepare("UPDATE students SET last_login = NOW() WHERE student_id = ?");
            $update_stmt->bind_param("i", $row['student_id']);
            $update_stmt->execute();

            if ($remember) {
                setcookie("student_remember_me", base64_encode($row['email']), time() + (86400 * $remember_days), "/");
            }

            header("Location: student_dashboard.php");
            exit;
        } else {
            $_SESSION['error'] = "Incorrect password.";
            header("Location: student_login.php");
            exit;
        }
    } else {
        $_SESSION['error'] = "No account found with this email. Please register first.";
        $_SESSION['old_email'] = "";
        header("Location: student_login.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student Login - Placement Cell</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Libertinus+Serif:ital,wght@0,400;0,600;0,700;1,400;1,600;1,700&display=swap" rel="stylesheet">
  <style>
  *, *::before, *::after {
    box-sizing: inherit;
  }

  html, body {
  margin: 0;
  padding: 0;
  width: 100%;
  height: 100%;
  font-family: "Libertinus Serif", serif;
  font-weight: 400;
  font-style: normal;
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
}

.center-wrapper {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100vh;
}

.login-container {
  background: rgba(255, 255, 255, 0.95);
  border-radius: 12px;
  width: 400px;
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
  z-index: 1;
}

.login-box {
  width: 100%;
  padding: 20px;
  box-sizing: border-box;
}

.login-logo {
  max-width: 300px;
  display: block;
  margin: 0 auto 20px auto;
}

.login-box h3 {
  margin-bottom: 10px;
  text-align: center;
  color: #581729;
  font-size: 21px;
}

.login-box label {
  display: block;
  margin: 10px 0 5px;
  font-weight: 600;
  color: #581729;
}

input[type="email"],
input[type="password"] {
  width: 100%;
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 6px;
  transition: all 0.2s ease;
  box-sizing: border-box;
}

.password-label {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-weight: bold;
  margin: 10px 0 5px;
}

.password-wrapper {
  position: relative;
}

.password-wrapper input {
  width: 100%;
  padding-right: 60px;
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 6px;
  box-sizing: border-box;
}

.toggle-password {
  position: absolute;
  top: 50%;
  right: 12px;
  transform: translateY(-50%);
  cursor: pointer;
  font-size: 14px;
  color: #581729;
  font-weight: normal;
}

.toggle-password:hover {
  text-decoration: underline;
}

.remember-me {
  margin: 15px 0;
  font-size: 14px;
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

.links {
margin-top: 15px;
font-size: 14px;
display: flex;
flex-direction: column;
gap: 5px;
text-align: center;
color: #581729;
}

.links a {
  color: #581729;
  text-decoration: none;
}

.links a:hover {
  color: blue;
}

.error-msg {
  background-color: #fdd;
  padding: 10px;
  margin-bottom: 10px;
  color: #900;
  border-radius: 5px;
}

.success-msg {
  background-color: #dfd;
  padding: 10px;
  margin-bottom: 10px;
  color: #090;
  border-radius: 5px;
}

input[type="password"]::-ms-reveal,
input[type="password"]::-ms-clear,
input[type="password"]::-webkit-credentials-auto-fill-button {
  display: none;
}

.admin-link {
  text-align: center;
  margin-top: 10px;
  font-size: 13px;
}

.admin-link a {
  color: #666;
  text-decoration: none;
}

.admin-link a:hover {
  color: #581729;
  text-decoration: underline;
}
  </style>
</head>
<body>
  <div class="background-image"></div>
  <div class="overlay"></div>

  <div class="center-wrapper">
    <div class="login-container">
      <form class="login-box" method="POST">
     <img src="images/MCC_login_logo.png" alt="Placement Logo" class="login-logo">
        <h3>Student Login</h3>

        <?php if (!empty($error)): ?>
          <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
          <div class="success-msg"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="Enter your college email" required value="<?= htmlspecialchars($old_email ?? '') ?>">

        <label for="password" class="password-label">Password</label>
        <div class="password-wrapper">
          <input type="password" id="password" name="password" placeholder="Enter password" required>
          <span class="toggle-password" id="togglePassword">
            <span class="toggle-text">Show</span>
          </span>
        </div>

        <label class="remember-me">
          <input type="checkbox" name="remember_me"> Keep me logged in
        </label><br>

        <button type="submit" name="login">Sign in</button>

        <div class="links">
          <a href="#" onclick="forgotPassword(); return false;">Forgot password?</a>
          <a href="student_register.php">Don't have an account? Register here</a>
        </div>

        <div class="admin-link">
          <a href="index.php">Admin Login</a>
        </div>
      </form>
    </div>
  </div>

  <script>
function forgotPassword() {
  const email = prompt("Enter your registered email address:");

  if (email === null || email === "") {
    return;
  }

  if (email.trim() === "" || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    alert("❌ Please enter a valid email address.\nExample: student@example.com");
    return;
  }

  // Show processing message
  alert("⏳ Processing your request...");

  fetch('student_send_reset.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'email=' + encodeURIComponent(email.trim())
  })
  .then(res => {
    if (!res.ok) {
      throw new Error('Network error');
    }
    return res.json();
  })
  .then(data => {
    if (data.success) {
      // Show success message and ask user to confirm
      const userChoice = confirm(
        "✅ Password reset link generated successfully!\n\n" +
        "Click OK to:\n" +
        "• Copy the link to clipboard\n" +
        "• Open the reset page automatically\n\n" +
        "Or click Cancel to see the link."
      );

      if (userChoice) {
        // Copy to clipboard
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(data.reset_link).then(() => {
            alert("✅ Reset link copied to clipboard!\n\nOpening reset page...");
            // Open in same or new tab
            window.location.href = data.reset_link;
          }).catch(() => {
            alert("⚠️ Couldn't auto-copy the link.\n\nOpening reset page...");
            window.location.href = data.reset_link;
          });
        } else {
          // For older browsers
          window.location.href = data.reset_link;
        }
      } else {
        // User clicked Cancel - show the link
        prompt("📋 Copy this reset link and paste it in your browser:", data.reset_link);
        alert("💡 After copying the link, paste it in your browser's address bar to reset your password.");
      }
    } else {
      alert("❌ " + (data.message || "No account found with that email.\n\nPlease check your email and try again."));
    }
  })
  .catch((error) => {
    console.error('Error:', error);
    alert("❌ Failed to process your request.\n\nPlease check:\n• Your internet connection\n• The email is correct\n\nThen try again.");
  });
}

    const toggle = document.getElementById("togglePassword");
    const passwordInput = document.getElementById("password");
    const toggleText = toggle.querySelector(".toggle-text");

    toggle.addEventListener("click", () => {
      const isPassword = passwordInput.type === "password";
      passwordInput.type = isPassword ? "text" : "password";
      toggleText.textContent = isPassword ? "Hide" : "Show";
    });

    window.onload = function () {
    const errorMsg = document.querySelector('.error-msg');
    const successMsg = document.querySelector('.success-msg');
    if (errorMsg || successMsg) {
      setTimeout(() => {
        if (errorMsg) errorMsg.style.display = 'none';
        if (successMsg) successMsg.style.display = 'none';
      }, 3000);
    }
  };
  </script>
</body>
</html>
