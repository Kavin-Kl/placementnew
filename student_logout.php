<?php
session_start();
$_SESSION = array();
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}
if (isset($_COOKIE['student_remember_me'])) {
    setcookie('student_remember_me', '', time() - 3600, '/');
}
session_destroy();
header('Location: student_login.php');
exit;
?>
