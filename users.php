<?php

session_start();
include("config.php");
if (!isset($_SESSION['admin_id'])) {
    header("Location: index");
    exit;
}
 include 'header.php'; 
ini_set('display_errors', 1);
error_reporting(E_ALL);

$message = '';
$error = '';
$edit_id = null;
$edit_user = null;

// Handle form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $email === '' || $password === '') {
            $error = 'All fields are required.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admin_users (username, email, password_hash) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('sss', $username, $email, $password_hash);
                $stmt->execute();
                $message = "User added.";
                $stmt->close();
            } else {
                $error = "Insert error: " . $conn->error;
            }
        }
    }

    if ($_POST['action'] === 'delete') {
        $admin_id = intval($_POST['admin_id']);
        $stmt = $conn->prepare("DELETE FROM admin_users WHERE admin_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $admin_id);
            $stmt->execute();
            $message = "User deleted.";
            $stmt->close();
        } else {
            $error = "Delete error: " . $conn->error;
        }
    }

    if ($_POST['action'] === 'edit') {
        $edit_id = intval($_POST['admin_id']);
        $stmt = $conn->prepare("SELECT admin_id, username, email FROM admin_users WHERE admin_id = ?");
        $stmt->bind_param('i', $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_user = $result->fetch_assoc();
        $stmt->close();
    }

    if ($_POST['action'] === 'update') {
        $admin_id = intval($_POST['admin_id']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);

        if ($username === '' || $email === '') {
            $error = "Username and email are required.";
        } else {
            $stmt = $conn->prepare("UPDATE admin_users SET username = ?, email = ? WHERE admin_id = ?");
            if ($stmt) {
                $stmt->bind_param('ssi', $username, $email, $admin_id);
                $stmt->execute();
                $message = "User updated.";
                $stmt->close();
            } else {
                $error = "Update error: " . $conn->error;
            }
        }
    }
}

$result = $conn->query("SELECT admin_id, username, email, created_at FROM admin_users ORDER BY admin_id DESC");
$users = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">

    <style>
* { box-sizing: border-box; }

body {
    font-family: 'Inter', sans-serif;
    margin: 0;
    padding: 30px;
    background-color: #f4f6f8;
    color: #333;
    font-size: 14px;
}

h1 {
    margin-bottom: 25px;
    color: #222;
}

.container {
    max-width: 900px;
    margin: 0 auto;
}

form {
    background-color: #fff;
    padding: 2px;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    margin-bottom: 25px;
}

form label {
    display: block;
    font-weight: 600;
    margin-top: 8px;
    font-size: 13px;
}

form input[type="text"],
form input[type="email"],
form input[type="password"] {
    width: 100%;
    padding: 8px;
    margin-top: 4px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 13px;
}

form button {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 8px 14px;
    margin-top: 15px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
    font-size: 13px;
}

form button:hover {
    background-color: #0056b3;
}

.message {
    background: #d4edda;
    color: #155724;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 15px;
    font-size: 13px;
}

.error {
    background: #f8d7da;
    color: #721c24;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 15px;
    font-size: 13px;
}

table {
    width: 100%;
    background-color: #fff;
    border-collapse: collapse;
    font-size: 13px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    table-layout: auto; /* allow column widths to adjust */
}

th, td {
    text-align: left;
    border-bottom: 1px solid #eaeaea;
    padding: 6px 8px;
    word-wrap: break-word;
}

th {
    background-color: #650000; /* brown color */
    color: #fff;
    font-weight: 600;
    font-size: 15px;
}

.action-buttons {
    white-space: nowrap; /* keep buttons on same line */
}

.action-buttons form {
    display: inline-block;
    margin: 0 2px; /* small gap between buttons */
}

.action-buttons button {
    padding: 5px 8px;
    font-size: 12px;
    display: inline-block;
}

.edit-btn {
    background-color: #17a2b8;
    color: white;
    border: none;
    border-radius: 3px;
}

.edit-btn:hover {
    background-color: #117a8b;
}

.delete-btn {
    background-color: #dc3545;
    color: white;
    border: none;
    border-radius: 3px;
}

.delete-btn:hover {
    background-color: #a71d2a;
}

.update-btn {
    background-color: #28a745;
    color: white;
    border: none;
    border-radius: 3px;
}

.update-btn:hover {
    background-color: #1e7e34;
}
</style>

</head>
<body>
<div class="container">
    <h3>Manage Users</h3>

    <?php if ($message): ?><div class="message"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Add or Edit Form -->
    <?php if (!$edit_user): ?>
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <label>Username:</label>
        <input type="text" name="username" required>
        <label>Email:</label>
        <input type="email" name="email" required>
        <label>Password:</label>
        <input type="password" name="password" required>
        <button type="submit">Add User</button>
    </form>
    <?php else: ?>
    <form method="POST">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="admin_id" value="<?= htmlspecialchars($edit_user['admin_id']) ?>">
        <label>Username:</label>
        <input type="text" name="username" value="<?= htmlspecialchars($edit_user['username']) ?>" required>
        <label>Email:</label>
        <input type="email" name="email" value="<?= htmlspecialchars($edit_user['email']) ?>" required>
        <button type="submit" class="update-btn">Update User</button>
    </form>
    <?php endif; ?>

    <h3>Users List</h3>
    <table>
        <thead>
        <tr>
           <th>SL No.</th><th>Username</th><th>Email</th><th>Created At</th><th>Actions</th>

        </tr>
        </thead>
        <tbody>
        <?php if (empty($users)): ?>
            <tr><td colspan="5" style="text-align:center;">No users found.</td></tr>
        <?php else: ?>
            <?php $sl = 1; foreach ($users as $user): ?>

                <tr>
                   <td><?= $sl++ ?></td>

                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= htmlspecialchars($user['created_at']) ?></td>
                    <td class="action-buttons">
                        <form method="POST">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="admin_id" value="<?= htmlspecialchars($user['admin_id']) ?>">
                            <button type="submit" class="edit-btn">Edit</button>
                        </form>
                        <form method="POST" onsubmit="return confirm('Delete user <?= htmlspecialchars($user['username']) ?>?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="admin_id" value="<?= htmlspecialchars($user['admin_id']) ?>">
                            <button type="submit" class="delete-btn">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<script>
window.addEventListener('DOMContentLoaded', () => {
    // Fade out messages after 5 seconds
    const messages = document.querySelectorAll('.message, .error');
    messages.forEach(msg => {
        setTimeout(() => {
            msg.style.transition = 'opacity 0.5s ease';
            msg.style.opacity = '0';
            setTimeout(() => msg.remove(), 500);
        }, 3000);
    });

    // Prevent form resubmission on refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
});
</script>



</body>


</html>
