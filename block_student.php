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

        // Capture which (drive_id, role_id) pairs are about to lose placements so we
        // can recompute their "Hired" counts after the delete.
        $touched_pairs = [];
        $pairStmt = $conn->prepare("SELECT drive_id, role_id FROM placed_students WHERE upid = ? OR student_id = ?");
        $pairStmt->bind_param("si", $upid, $studentId);
        $pairStmt->execute();
        $pairRes = $pairStmt->get_result();
        while ($p = $pairRes->fetch_assoc()) {
            if (!empty($p['drive_id']) && !empty($p['role_id'])) {
                $touched_pairs[$p['drive_id'] . ':' . $p['role_id']] = [(int)$p['drive_id'], (int)$p['role_id']];
            }
        }
        $pairStmt->close();

        // Remove the student's rows from placed_students so they no longer appear
        // on the Placed Students / Internship / Vantage Placed pages — those views
        // read placed_students directly, not students.placed_status.
        $stmt3 = $conn->prepare("DELETE FROM placed_students WHERE upid = ? OR student_id = ?");
        $stmt3->bind_param("si", $upid, $studentId);
        $stmt3->execute();
        $removed_placements = $stmt3->affected_rows;
        $stmt3->close();

        // Recompute hired counts for the affected (drive_id, role_id) pairs so
        // Progress Tracker / Company Data don't show stale "Hired" numbers.
        if (!empty($touched_pairs)) {
            require_once __DIR__ . '/placed_import_helper.php';
            if (function_exists('pi_recompute_hired_counts')) {
                pi_recompute_hired_counts($conn, $touched_pairs);
            }
        }

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Student blocked successfully' . ($removed_placements > 0 ? " (removed $removed_placements placement record" . ($removed_placements > 1 ? 's' : '') . ")" : ""),
        ]);
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error blocking student: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
