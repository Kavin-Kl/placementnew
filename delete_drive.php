<?php
session_start();
include("config.php");

// Accept both POST (preferred) and GET for compatibility
$id = $_POST['drive_id'] ?? ($_GET['drive_id'] ?? 0);
$id = (int)$id;

if ($id) {
    // Step 1: Get company name for the drive being deleted
    $stmt = $conn->prepare("SELECT company_name FROM drives WHERE drive_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($company);
    if (!$stmt->fetch()) {
        $stmt->close();
        header("Location: dashboard");
        exit;
    }
    $stmt->close();

    // Step 2: Delete from all related tables
    // Use prepared statements for safety and to avoid SQL mode issues
$tables = [
    'placed_students' => 'drive_id',
    'applications' => 'drive_id',
    'drive_data' => 'drive_id',
    'drive_roles' => 'drive_id',
    'drives' => 'drive_id'
];
    
        foreach ($tables as $table => $col) {
        $del = $conn->prepare("DELETE FROM {$table} WHERE {$col} = ?");
        if ($del) {
            $del->bind_param("i", $id);
            $del->execute();
            $del->close();
        }
    }

    // ✅ New Step: reset placed_status for students not in placed_students
    $conn->query("
        UPDATE students 
        SET placed_status = 'not placed' 
        WHERE student_id NOT IN (SELECT student_id FROM placed_students)
    ");

    // Step 3: Renumber remaining drives for this company


    // Step 3: Renumber remaining drives for this company
    $stmt = $conn->prepare("SELECT drive_id FROM drives WHERE company_name = ? ORDER BY open_date ASC");
    $stmt->bind_param("s", $company);
    $stmt->execute();
    $result = $stmt->get_result();

    $count = 1;
    while ($row = $result->fetch_assoc()) {
        $newDriveNo = "Drive " . $count++;
        $driveId = $row['drive_id'];

        $upd1 = $conn->prepare("UPDATE drives SET drive_no = ? WHERE drive_id = ?");
        $upd1->bind_param("si", $newDriveNo, $driveId);
        $upd1->execute();
        $upd1->close();

        //$upd2 = $conn->prepare("UPDATE company_followup SET drive_no = ? WHERE drive_id = ?");
       // if ($upd2) { $upd2->bind_param("si", $newDriveNo, $driveId); $upd2->execute(); $upd2->close(); }

        $upd3 = $conn->prepare("UPDATE drive_data SET drive_no = ? WHERE drive_id = ?");
        if ($upd3) { $upd3->bind_param("si", $newDriveNo, $driveId); $upd3->execute(); $upd3->close(); }
    }
    $stmt->close();
}

header("Location: dashboard");
exit;
