<?php
session_start();
include 'config.php';

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_ids'])) {
    $studentIds = $_POST['student_ids'];

    if (empty($studentIds) || !is_array($studentIds)) {
        echo json_encode(['success' => false, 'message' => 'No students selected.']);
        exit;
    }

    // Sanitize student IDs
    $studentIds = array_map('intval', $studentIds);
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));

    // Start transaction
    $conn->begin_transaction();

    try {
        // Delete from placed_students
        $stmt = $conn->prepare("DELETE FROM placed_students WHERE student_id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($studentIds)), ...$studentIds);
        $stmt->execute();
        $stmt->close();

        // Delete from applications
        $stmt = $conn->prepare("DELETE FROM applications WHERE student_id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($studentIds)), ...$studentIds);
        $stmt->execute();
        $stmt->close();

        // Delete from students
        $stmt = $conn->prepare("DELETE FROM students WHERE student_id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($studentIds)), ...$studentIds);
        $stmt->execute();
        $deletedCount = $stmt->affected_rows;
        $stmt->close();

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => "$deletedCount student(s) and their related records deleted successfully."
        ]);
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Error deleting students: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>
