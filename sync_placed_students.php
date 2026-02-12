<?php
// Usage: include_once 'sync_placed_students.php'; sync_placed_students($conn);

include 'config.php';

function sync_placed_students(mysqli $conn, array $opts = [])
{
    $results = ['inserted' => 0, 'updated' => 0, 'deleted' => 0, 'errors' => []];

    // Small guard to avoid concurrent runs
    $lockName = $opts['lock_name'] ?? 'sync_placed_students_lock';
    $timeout = intval($opts['lock_timeout'] ?? 5);
    $res = $conn->query("SELECT GET_LOCK('{$lockName}', {$timeout}) AS got_lock");
    if (!$res) {
        $results['errors'][] = "Lock query failed: " . $conn->error;
        return $results;
    }

    $got = $res->fetch_assoc()['got_lock'] ?? 0;
    if ($got != 1) {
        $results['errors'][] = "Could not acquire sync lock";
        return $results;
    }

    try {
        $conn->begin_transaction();

        // === Select latest placed application per student+batch ===
        $missingQuery = "
            SELECT 
                s.student_id, 
                s.upid, 
                s.program_type, 
                s.program, 
                s.course, 
                s.reg_no,
                s.student_name, 
                s.email, 
                s.phone_no,
                a.drive_id, 
                a.role_id, 
                a.percentage,
                dd.offer_type,
                d.drive_no,
                d.company_name, 
                dr.designation_name AS role, 
                dr.ctc, 
                dr.stipend,
                s.allow_reapply, 
                COALESCE(a.placement_batch, 'original') AS placement_batch
            FROM students s
            JOIN (
                SELECT
                    a1.student_id,
                    a1.drive_id,
                    a1.role_id,
                    a1.percentage,
                    COALESCE(a1.placement_batch, 'original') AS placement_batch
                FROM applications a1
                INNER JOIN (
                    SELECT 
                        student_id, 
                        COALESCE(placement_batch, 'original') AS placement_batch,
                        MAX(status_changed) AS latest_change   
                    FROM applications
                    WHERE status = 'placed'
                    GROUP BY student_id, COALESCE(placement_batch, 'original')
                ) latest
                    ON a1.student_id = latest.student_id
                    AND COALESCE(a1.placement_batch, 'original') = latest.placement_batch
                    AND a1.status_changed = latest.latest_change   
                    AND a1.status = 'placed'
            ) a ON s.student_id = a.student_id
            JOIN drives d ON a.drive_id = d.drive_id
            JOIN drive_roles dr ON a.role_id = dr.role_id
            JOIN drive_data dd ON a.drive_id = dd.drive_id AND a.role_id = dd.role_id
            WHERE s.placed_status = 'placed';
        ";

        $missingResult = $conn->query($missingQuery);
        if ($missingResult === false) {
            throw new Exception("missingQuery failed: " . $conn->error);
        }

        while ($r = $missingResult->fetch_assoc()) {
            // Determine placement batch
            $batch = $r['placement_batch'];
            if (!$batch) {
                $batch = ($r['allow_reapply'] === 'yes') ? 'reapplied' : 'original';
            }

            // Check if student filled on/off campus form
            $formCheck = $conn->prepare("SELECT 1 FROM on_off_campus_students WHERE reg_no = ?");
            if (!$formCheck) throw new Exception("prepare failed (formCheck): " . $conn->error);
            $formCheck->bind_param("s", $r['reg_no']);
            $formCheck->execute();
            $formRes = $formCheck->get_result();
            $filled_on_off_form = ($formRes && $formRes->num_rows > 0) ? 'filled' : 'not filled';
            $formCheck->close();

            // Check if student already exists in placed_students for the batch
            $check = $conn->prepare("SELECT place_id FROM placed_students WHERE student_id = ? AND placement_batch = ?");
            if (!$check) throw new Exception("prepare failed (check): " . $conn->error);
            $check->bind_param("is", $r['student_id'], $batch);
            $check->execute();
            $checkResult = $check->get_result();

            if ($checkResult && $checkResult->num_rows > 0) {
                // Update existing placement record
                $placeId = $checkResult->fetch_assoc()['place_id'];

                $update = $conn->prepare("
                    UPDATE placed_students 
                    SET 
                        drive_id = ?, 
                        drive_no = ?, 
                        role_id = ?, 
                        company_name = ?, 
                        role = ?, 
                        ctc = ?, 
                        stipend = ?, 
                        offer_type = ?, 
                        filled_on_off_form = ?, 
                        percentage = ?
                    WHERE place_id = ?
                ");
                if (!$update) throw new Exception("prepare failed (update): " . $conn->error);

                // Assign percentage to a variable (needed for bind_param by reference)
                $percentage = (float)$r['percentage'];

                $update->bind_param(
                    "isissssssdi",
                    $r['drive_id'],
                    $r['drive_no'],
                    $r['role_id'],
                    $r['company_name'],
                    $r['role'],
                    $r['ctc'],
                    $r['stipend'],
                    $r['offer_type'],
                    $filled_on_off_form,
                    $percentage,
                    $placeId
                );

                $update->execute();
                $results['updated'] += ($update->affected_rows > 0) ? 1 : 0;
                $update->close();
            } else {
                // Insert new placement record
                $stmt = $conn->prepare("
                    INSERT INTO placed_students 
                    (student_id, drive_id, role_id, upid, program_type, program, course, reg_no, student_name, email, phone_no, drive_no, company_name, role, ctc, stipend, offer_type, placement_batch, filled_on_off_form, percentage)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                if (!$stmt) throw new Exception("prepare failed (insert): " . $conn->error);

                $percentage = (float)$r['percentage'];

                $stmt->bind_param(
                    "iiissssssssssssssssd",
                    $r['student_id'],
                    $r['drive_id'],
                    $r['role_id'],
                    $r['upid'],
                    $r['program_type'],
                    $r['program'],
                    $r['course'],
                    $r['reg_no'],
                    $r['student_name'],
                    $r['email'],
                    $r['phone_no'],
                    $r['drive_no'],
                    $r['company_name'],
                    $r['role'],
                    $r['ctc'],
                    $r['stipend'],
                    $r['offer_type'],
                    $batch,
                    $filled_on_off_form,
                    $percentage
                );

                $stmt->execute();
                $results['inserted'] += ($stmt->affected_rows > 0) ? 1 : 0;
                $stmt->close();
            }

            $check->close();
        }

        // Remove placed_students rows that no longer match (orphan / not placed)
        $delSql = "
            DELETE ps
            FROM placed_students ps
            LEFT JOIN applications a
                ON ps.upid = a.upid
                AND ps.reg_no = a.reg_no
                AND ps.drive_id = a.drive_id
                AND ps.role_id = a.role_id
                AND COALESCE(ps.placement_batch, 'original') = COALESCE(a.placement_batch, 'original')
            WHERE a.upid IS NULL
            OR a.status != 'placed';
        ";
        $delRes = $conn->query($delSql);
        if ($delRes === false) {
            throw new Exception("Delete query failed: " . $conn->error);
        }
        $results['deleted'] = $conn->affected_rows;

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $results['errors'][] = $e->getMessage();
    }

    // Release lock
    $conn->query("SELECT RELEASE_LOCK('{$lockName}')");

    return $results;
}
