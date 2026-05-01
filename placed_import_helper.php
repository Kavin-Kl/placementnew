<?php
/**
 * Shared helpers for the various placed-students import flows.
 *
 * Each placed-students import (final year, internship, vantage, backup) needs to:
 *   1. Find or create the drive (drives table) for the (company_name, drive_no, AY) tuple.
 *   2. Find or create the role (drive_roles table) for that drive + designation.
 *   3. Ensure a drive_data row exists for that (drive_id, role_id) — this is what
 *      the Progress Tracker and Company Data tabs read.
 *   4. After all rows are inserted, recompute drive_data.no_of_hired from
 *      placed_students so the "Hired" column on the trackers matches reality.
 */

if (!function_exists('pi_offer_type_to_flags')) {
    /**
     * Map an offer_type to drive show_to_* flags so the auto-created drive
     * lands in the right tracker tab(s). Mirrors the WHERE clauses in
     * fulltime_company_data.php / internship_company_data.php.
     */
    function pi_offer_type_to_flags(string $ot): array
    {
        $fte_set        = ['FTE', 'Apprenticeship (Full time)', 'Internship + PPO', 'Internship+PPO', 'Internship + PPO (Final Year)'];
        $internship_set = ['Internship', 'Internship + PPO (Pre-Final Year)', 'Apprenticeship (Part Time)'];
        $is_fte        = in_array($ot, $fte_set, true);
        $is_internship = in_array($ot, $internship_set, true);
        $is_vantage    = (stripos($ot, 'vantage') !== false);

        return [
            'show_to_placement'  => $is_fte ? 1 : 0,
            'show_to_internship' => $is_internship ? 1 : 0,
            'show_to_vantage'    => $is_vantage ? 1 : 0,
        ];
    }
}

if (!function_exists('pi_resolve_drive_role')) {
    /**
     * Find or create the drive + drive_role + drive_data rows for a placed
     * record. Returns [drive_id, role_id] on success, or null on failure.
     *
     * @param string[] $err Array (by ref) where any error message is appended.
     */
    function pi_resolve_drive_role(
        mysqli $conn,
        string $company_name,
        string $drive_no,
        string $role,
        string $offer_type,
        string $ctc,
        string $stipend,
        string $row_ay,
        int &$drives_created,
        int &$roles_created,
        array &$err = []
    ): ?array {
        // 1. Find or create drive
        $driveStmt = $conn->prepare(
            "SELECT drive_id FROM drives
              WHERE company_name = ? AND drive_no = ? AND academic_year = ?"
        );
        $driveStmt->bind_param("sss", $company_name, $drive_no, $row_ay);
        $driveStmt->execute();
        $driveResult = $driveStmt->get_result();

        if ($driveResult->num_rows === 0) {
            $driveStmt->close();
            $flags      = pi_offer_type_to_flags($offer_type);
            $open_date  = date('Y-m-d H:i:s');
            $close_date = date('Y-m-d H:i:s');
            $form_link  = uniqid('form_');
            $created_by = ($_SESSION['username'] ?? 'Placed Import');

            $createDrive = $conn->prepare("INSERT INTO drives
                (company_name, drive_no, open_date, close_date, form_link, created_by, academic_year, show_to_placement, show_to_internship, show_to_vantage)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $createDrive->bind_param(
                "sssssssiii",
                $company_name, $drive_no, $open_date, $close_date,
                $form_link, $created_by, $row_ay,
                $flags['show_to_placement'], $flags['show_to_internship'], $flags['show_to_vantage']
            );
            if (!$createDrive->execute()) {
                $err[] = "Failed to auto-create drive '$company_name - $drive_no': " . $createDrive->error;
                $createDrive->close();
                return null;
            }
            $drive_id = $conn->insert_id;
            $drives_created++;
            $createDrive->close();
        } else {
            $drive_id = (int)$driveResult->fetch_assoc()['drive_id'];
            $driveStmt->close();
        }

        // 2. Find or create role
        $roleStmt = $conn->prepare(
            "SELECT role_id FROM drive_roles WHERE drive_id = ? AND designation_name = ?"
        );
        $roleStmt->bind_param("is", $drive_id, $role);
        $roleStmt->execute();
        $roleResult = $roleStmt->get_result();

        if ($roleResult->num_rows === 0) {
            $roleStmt->close();
            $createRole = $conn->prepare("INSERT INTO drive_roles
                (drive_id, designation_name, ctc, stipend, offer_type)
                VALUES (?, ?, ?, ?, ?)");
            $createRole->bind_param("issss", $drive_id, $role, $ctc, $stipend, $offer_type);
            if (!$createRole->execute()) {
                $err[] = "Failed to auto-create role '$role' for '$company_name': " . $createRole->error;
                $createRole->close();
                return null;
            }
            $role_id = $conn->insert_id;
            $roles_created++;
            $createRole->close();
        } else {
            $role_id = (int)$roleResult->fetch_assoc()['role_id'];
            $roleStmt->close();
        }

        // 3. Ensure drive_data exists (the row trackers read from)
        $ddCheck = $conn->prepare("SELECT id FROM drive_data WHERE drive_id = ? AND role_id = ?");
        $ddCheck->bind_param("ii", $drive_id, $role_id);
        $ddCheck->execute();
        if ($ddCheck->get_result()->num_rows === 0) {
            $ddCheck->close();
            $createDD = $conn->prepare("INSERT INTO drive_data
                (drive_id, role_id, company_name, drive_no, role, offer_type, no_of_applied, no_of_hired)
                VALUES (?, ?, ?, ?, ?, ?, 0, 0)");
            $createDD->bind_param("iissss", $drive_id, $role_id, $company_name, $drive_no, $role, $offer_type);
            $createDD->execute();
            $createDD->close();
        } else {
            $ddCheck->close();
        }

        return [$drive_id, $role_id];
    }
}

if (!function_exists('pi_add_course_to_role')) {
    /**
     * Add a placed student's course to drive_roles.eligible_courses and
     * drive_data.eligible_courses for the given (drive_id, role_id).
     *
     * Stored as a JSON array (matches add_drive.php / edit_drive.php). De-dupes
     * case-insensitively so re-imports don't bloat the list.
     */
    function pi_add_course_to_role(mysqli $conn, int $drive_id, int $role_id, string $course): void
    {
        $course = trim($course);
        if ($course === '') return;

        // Update drive_roles
        $sel = $conn->prepare("SELECT eligible_courses FROM drive_roles WHERE role_id = ?");
        $sel->bind_param("i", $role_id);
        $sel->execute();
        $existing = $sel->get_result()->fetch_assoc()['eligible_courses'] ?? null;
        $sel->close();

        $courses = [];
        if (!empty($existing)) {
            $decoded = json_decode($existing, true);
            if (is_array($decoded)) {
                $courses = $decoded;
            } else {
                // Legacy CSV → split
                $courses = array_map('trim', explode(',', $existing));
            }
        }
        $lc = array_map('strtolower', $courses);
        if (!in_array(strtolower($course), $lc, true)) {
            $courses[] = $course;
        }
        $newJson = json_encode(array_values(array_filter($courses, fn($v) => trim((string)$v) !== '')));

        $upd = $conn->prepare("UPDATE drive_roles SET eligible_courses = ? WHERE role_id = ?");
        $upd->bind_param("si", $newJson, $role_id);
        $upd->execute();
        $upd->close();

        // Mirror into drive_data so trackers / company-data pages render the same list.
        $upd2 = $conn->prepare("UPDATE drive_data SET eligible_courses = ? WHERE drive_id = ? AND role_id = ?");
        $upd2->bind_param("sii", $newJson, $drive_id, $role_id);
        $upd2->execute();
        $upd2->close();
    }
}

if (!function_exists('pi_recompute_hired_counts')) {
    /**
     * Recompute drive_data.no_of_hired = COUNT(DISTINCT student_id) in
     * placed_students for each (drive_id, role_id) in $touched_pairs.
     *
     * $touched_pairs format: ['<drive_id>:<role_id>' => [drive_id, role_id], ...]
     */
    function pi_recompute_hired_counts(mysqli $conn, array $touched_pairs): int
    {
        if (empty($touched_pairs)) return 0;

        $stmt = $conn->prepare("
            UPDATE drive_data dd
            LEFT JOIN (
                SELECT drive_id, role_id, COUNT(DISTINCT student_id) AS cnt
                FROM placed_students
                WHERE drive_id = ? AND role_id = ?
                GROUP BY drive_id, role_id
            ) ps ON dd.drive_id = ps.drive_id AND dd.role_id = ps.role_id
            SET dd.no_of_hired = COALESCE(ps.cnt, 0)
            WHERE dd.drive_id = ? AND dd.role_id = ?
        ");
        $count = 0;
        foreach ($touched_pairs as [$dId, $rId]) {
            $stmt->bind_param("iiii", $dId, $rId, $dId, $rId);
            if ($stmt->execute()) $count++;
        }
        $stmt->close();
        return $count;
    }
}
