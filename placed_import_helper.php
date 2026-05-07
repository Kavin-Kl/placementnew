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

if (!function_exists('pi_upsert_placed_from_application')) {
    /**
     * When an application is marked status='placed' from the round-results /
     * enrolled-students UIs, mirror it into placed_students so the row appears
     * on the Placed Students tab and Hired counts on the trackers update.
     *
     * Insert-only: if a placed_students row already exists for this
     * (student_id, drive_id, role_id, batch) we leave it alone so any
     * Excel-imported CTC/stipend overrides are preserved.
     *
     * Returns true if a new row was inserted, false otherwise.
     */
    function pi_upsert_placed_from_application(mysqli $conn, int $application_id): bool
    {
        require_once __DIR__ . '/academic_year_helper.php';

        $sql = "
            SELECT a.application_id, a.student_id, a.drive_id, a.role_id, a.percentage,
                   a.placement_batch, a.upid AS app_upid,
                   s.upid, s.program_type, s.program, s.course, s.reg_no,
                   s.student_name, s.email, s.phone_no, s.allow_reapply,
                   s.year_of_passing,
                   d.company_name, d.drive_no, d.academic_year,
                   dr.designation_name AS role, dr.ctc, dr.stipend, dr.offer_type
              FROM applications a
              JOIN students s     ON a.student_id = s.student_id
              JOIN drives d       ON a.drive_id  = d.drive_id
              JOIN drive_roles dr ON a.role_id   = dr.role_id
             WHERE a.application_id = ?
               AND a.status = 'placed'
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param("i", $application_id);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$r) return false;

        // Any non-'no' allow_reapply value (yes / any / fulltime / internship) is treated
        // as a reapply; only 'no' (or empty) keeps the original batch label.
        $batch = $r['placement_batch'] ?: (
            in_array(strtolower($r['allow_reapply'] ?? ''), ['yes', 'any', 'fulltime', 'internship'], true)
                ? 'reapplied' : 'original'
        );

        $check = $conn->prepare(
            "SELECT 1 FROM placed_students
              WHERE student_id = ? AND drive_id = ? AND role_id = ?
                AND COALESCE(placement_batch, 'original') = ?"
        );
        $check->bind_param("iiis", $r['student_id'], $r['drive_id'], $r['role_id'], $batch);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();
        if ($exists) return false;

        $formCheck = $conn->prepare("SELECT 1 FROM on_off_campus_students WHERE reg_no = ?");
        $formCheck->bind_param("s", $r['reg_no']);
        $formCheck->execute();
        $filled_on_off_form = ($formCheck->get_result()->num_rows > 0) ? 'filled' : 'not filled';
        $formCheck->close();

        $percentage = isset($r['percentage']) ? (float)$r['percentage'] : null;
        $row_ay     = $r['academic_year'] ?? ($_SESSION['selected_academic_year'] ?? null);
        $upid       = $r['upid'] ?: $r['app_upid'];

        $ins = $conn->prepare(
            "INSERT INTO placed_students
                (student_id, drive_id, role_id, upid, program_type, program, course, reg_no,
                 student_name, email, phone_no, percentage, offer_type, drive_no, company_name,
                 role, ctc, stipend, placement_batch, filled_on_off_form, academic_year)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$ins) {
            $errMsg = '[pi_upsert_placed_from_application] prepare failed: ' . $conn->error;
            error_log($errMsg);
            if (PHP_SAPI === 'cli') fwrite(STDERR, $errMsg . "\n");
            return false;
        }
        $ins->bind_param(
            "iiissssssssdsssssssss",
            $r['student_id'], $r['drive_id'], $r['role_id'], $upid,
            $r['program_type'], $r['program'], $r['course'], $r['reg_no'],
            $r['student_name'], $r['email'], $r['phone_no'], $percentage,
            $r['offer_type'], $r['drive_no'], $r['company_name'], $r['role'],
            $r['ctc'], $r['stipend'], $batch, $filled_on_off_form, $row_ay
        );
        $ok = $ins->execute();
        if (!$ok) {
            $errMsg = '[pi_upsert_placed_from_application] insert failed: ' . $ins->error;
            error_log($errMsg);
            if (PHP_SAPI === 'cli') fwrite(STDERR, $errMsg . "\n");
            $ins->close();
            return false;
        }
        $ins->close();

        $touched = [($r['drive_id'] . ':' . $r['role_id']) => [(int)$r['drive_id'], (int)$r['role_id']]];
        pi_recompute_hired_counts($conn, $touched);
        if (!empty($r['course'])) {
            pi_add_course_to_role($conn, (int)$r['drive_id'], (int)$r['role_id'], (string)$r['course']);
        }

        $yop = $r['year_of_passing'] !== null ? (int)$r['year_of_passing'] : null;
        apply_placement_lock_if_fulltime($conn, (int)$r['student_id'], (string)$r['offer_type'], $yop, $row_ay);

        return true;
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
