<?php
require 'config.php'; // Update with your actual DB connection file

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['external_id'])) {
    $external_id = intval($_POST['external_id']);
    $stmt = $conn->prepare("DELETE FROM on_off_campus_students WHERE external_id = ?");
    $stmt->bind_param("i", $external_id);

    if ($stmt->execute()) {
        echo 'success';
    } else {
        echo 'error';
    }
    $stmt->close();
} else {
    echo 'invalid';
}
?>
