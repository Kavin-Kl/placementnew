<?php
session_start();

// Clear all session variables
$_SESSION = [];
session_unset();
session_destroy();

// Delete the remember_me cookie
if (isset($_COOKIE['remember_me'])) {
    setcookie("remember_me", "", time() - 3600, "/"); // Delete cookie
}

header("Location: index.php");
exit;
