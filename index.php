<?php 
session_start();
include "config.php";

$error = $_SESSION['error'] ?? "";
$success = $_SESSION['success'] ?? "";
$old_username = $_SESSION['old_username'] ?? "";
$old_password = $_SESSION['old_password'] ?? "";
unset($_SESSION['error'], $_SESSION['success'], $_SESSION['old_username'], $_SESSION['old_password']);

$remember_days = 7;

// Auto-create admin_users table (for dev only)
$conn->query("CREATE TABLE IF NOT EXISTS admin_users (
  admin_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    setcookie("remember_me", "", time() - 3600);
    header("Location: index.php");
    exit;
}

// Cookie auto-login
if (isset($_COOKIE['remember_me']) && !isset($_SESSION['admin_id'])) {
    $username = base64_decode($_COOKIE['remember_me']);
    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE BINARY username = ? OR BINARY email = ?");
    if ($stmt === false) {
        die("Database error: " . $conn->error);
    }
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
        $row = $res->fetch_assoc();
        $_SESSION['admin_id'] = $row['admin_id'];
        $_SESSION['username'] = $row['username'];
        header("Location: dashboard.php");
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember_me']);

    // Set defaults
    $_SESSION['old_username'] = $username;
    $_SESSION['old_password'] = $password;

    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Both fields are required.";
        header("Location: index.php");
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE BINARY username = ? OR BINARY email = ?");
    if ($stmt === false) {
        die("Database error: " . $conn->error);
    }
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows === 1) {
        $row = $res->fetch_assoc();
        if (password_verify($password, $row['password_hash'])) {
            $_SESSION['admin_id'] = $row['admin_id'];
            $_SESSION['username'] = $row['username'];

            if ($remember) {
                setcookie("remember_me", base64_encode($row['username']), time() + (86400 * $remember_days), "/");
            }

            include_once 'auto_backup.php';
            header("Location: dashboard.php");
            exit;
        } else {
            $_SESSION['error'] = "Incorrect password.";
            $_SESSION['old_password'] = ""; // ❌ Password wrong, so clear it
            header("Location: index.php");
            exit;
        }
    } else {
        $_SESSION['error'] = "No account found with this username or email.";
        $_SESSION['old_username'] = ""; // ❌ Username wrong, so clear it
        // Password remains
        header("Location: index.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Login</title>
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

input[type="text"],
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

.links:hover {
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

  </style>
</head>
<body>
  <!-- Background layers -->
  <div class="background-image"></div>
  <div class="overlay"></div>

  <!-- Centered Login Form -->
  <div class="center-wrapper">
    <div class="login-container">
      <form class="login-box" method="POST">
     <img src="images/MCC_login_logo.png" alt="Placement Logo" class="login-logo">
        <h3>Admin Log in</h3>

        <?php if (!empty($error)): ?>
          <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
          <div class="success-msg"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <label for="username">Username</label>
        <input type="text" id="username" name="username" placeholder="Enter username" required value="<?= htmlspecialchars($old_username ?? '') ?>">

        <label for="password" class="password-label">Password</label>
        <div class="password-wrapper">
          <input type="password" id="password" name="password" placeholder="Enter password" required value="<?= htmlspecialchars($old_password ?? '') ?>">
          <span class="toggle-password" id="togglePassword">
            <span class="toggle-text">Show</span>
          </span>
        </div>

        <label class="remember-me">
          <input type="checkbox" name="remember_me"> Keep me logged in
        </label><br>

        <button type="submit" name="login">Sign in</button>

        <div class="links">
        <a href="#" onclick="forgotPassword(); return false;" class="link">Forgot password?</a>

        </div>
        <div id="resetMessage" style="color: green; margin-top: 15px;"></div>

        <div style="text-align: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
          <small style="color: #666;">Looking for student portal?</small><br>
          <a href="student_login.php" style="color: #581729; text-decoration: none; font-weight: 600;">Student Login →</a>
        </div>
      </form>
    </div>
  </div>

  <script>
function forgotPassword() {
  const email = prompt("Enter your registered email:");

  // If user clicks Cancel (email is null), just return quietly
  if (email === null) {
    return;
  }

  // If input is empty or invalid format, show error
  if (email.trim() === "" || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    alert("Invalid email address.");
    return;
  }

  // If valid email, send request
  fetch('send_reset', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'email=' + encodeURIComponent(email)
  })
  .then(res => res.text())
.then(data => {
  if (data.startsWith("http")) {
    prompt("Copy your reset link:", data);
  } else {
    alert(data); // Show error if any
  }
})
  .catch(() => alert("Failed to process request."));
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
      }, 3000); // 3 seconds
    }
  };
  </script>
</body>
</html>
