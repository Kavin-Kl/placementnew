<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include("config.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'], $_POST['upid'])) {
    $studentId = intval($_POST['student_id']);
    $upid = trim($_POST['upid']);

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update all applications of this student to 'blocked' status
        $stmt = $conn->prepare("UPDATE applications SET status = 'blocked', placement_batch = 'original', comments = 'Blocked by admin' WHERE upid = ?");
        $stmt->bind_param("s", $upid);
        $stmt->execute();
        $stmt->close();

        // Update student's placed_status to 'blocked'
        $stmt2 = $conn->prepare("UPDATE students SET placed_status = 'blocked', comment = 'Blocked by admin' WHERE upid = ?");
        $stmt2->bind_param("s", $upid);
        $stmt2->execute();
        $stmt2->close();

        // Commit transaction
        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Student blocked successfully']);
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error blocking student: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
