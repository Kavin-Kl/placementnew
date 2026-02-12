<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $studentId = (int) $_POST['student_id'];

    // Delete from placed_students
    $conn->query("DELETE FROM placed_students WHERE student_id = $studentId");

    // Delete from applications
    $conn->query("DELETE FROM applications WHERE student_id = $studentId");

    // Delete from students
    $result = $conn->query("DELETE FROM students WHERE student_id = $studentId");

    if ($result) {
        echo "Student and related records deleted successfully.";
    } else {
        echo "Error deleting student: " . $conn->error;
    }
} else {
    echo "Invalid request.";
}
?>
