<?php
/**
 * Shared utilities for academic year filtering and offer-type tab routing.
 * Include after session_start() and after config.php.
 */

if (!function_exists('ay_current')) {
    function ay_current(): ?string {
        return $_SESSION['selected_academic_year'] ?? null;
    }
}

if (!function_exists('ay_final_year_int')) {
    /** Returns the integer year of the final part of the academic year (e.g. "2026-2027" -> 2027). */
    function ay_final_year_int(?string $ay = null): ?int {
        $ay = $ay ?? ay_current();
        if (!$ay) return null;
        $parts = explode('-', $ay);
        return isset($parts[1]) ? (int) $parts[1] : null;
    }
}

if (!function_exists('is_final_year_student')) {
    /** True if the student's year_of_passing matches the second year of the academic year. */
    function is_final_year_student(?int $year_of_passing, ?string $ay = null): bool {
        $final = ay_final_year_int($ay);
        if ($final === null || $year_of_passing === null) return false;
        return (int) $year_of_passing === $final;
    }
}

if (!function_exists('offer_type_is_fulltime')) {
    /**
     * Returns true if the placement should be considered "full time" (i.e. lands in placed_students tab
     * and locks the student from further applications).
     *
     * Rules:
     *   FTE                       -> full time
     *   Apprenticeship (Full time)  -> full time
     *   Internship + PPO          -> full time only when student is final year
     *   Apprentice / Internship   -> internship
     */
    function offer_type_is_fulltime(string $offer_type, bool $is_final_year): bool {
        $ot = trim($offer_type);
        if ($ot === 'FTE' || $ot === 'Apprenticeship (Full time)') return true;
        if ($ot === 'Internship + PPO (Final Year)') return true;
        if ($ot === 'Internship + PPO (Pre-Final Year)') return false;
        if ($ot === 'Internship + PPO' || $ot === 'Internship+PPO') return $is_final_year;
        return false;
    }
}

if (!function_exists('offer_type_locks_eligibility')) {
    function offer_type_locks_eligibility(string $offer_type, bool $is_final_year): bool {
        return offer_type_is_fulltime($offer_type, $is_final_year);
    }
}

if (!function_exists('apply_placement_lock_if_fulltime')) {
    /**
     * After inserting into placed_students, call this with the same offer_type, student_id,
     * and the student's year_of_passing to enforce the "once placed full-time, not eligible" rule.
     */
    function apply_placement_lock_if_fulltime(mysqli $conn, int $student_id, string $offer_type, ?int $year_of_passing, ?string $ay = null): void {
        $is_final = is_final_year_student($year_of_passing, $ay);
        if (!offer_type_is_fulltime($offer_type, $is_final)) return;
        $stmt = $conn->prepare(
            "UPDATE students
                SET placed_status = 'placed',
                    allow_reapply = 'no'
              WHERE student_id = ?"
        );
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
    }
}

if (!function_exists('student_is_locked_out')) {
    function student_is_locked_out(array $student): bool {
        // A student is locked out only when they're placed AND admin has set
        // allow_reapply = 'no'. Any non-'no' value (legacy 'yes', or the new
        // 'any' / 'fulltime' / 'internship' settings) means they can reapply
        // — the per-drive offer-type filter is enforced in form_generator.php.
        $reapply = strtolower(trim($student['allow_reapply'] ?? 'no'));
        return ($student['placed_status'] ?? '') === 'placed'
            && ($reapply === '' || $reapply === 'no');
    }
}

/**
 * SQL fragment helpers — return ['sql' => ' AND ...', 'value' => '2026-2027'] or null sql when no AY set.
 * Use in concatenation, but bind ?value via prepared statement.
 */
if (!function_exists('ay_sql_clause')) {
    function ay_sql_clause(string $alias_dot_column = 's.academic_year'): array {
        $ay = ay_current();
        if (!$ay) return ['sql' => '', 'value' => null];
        return ['sql' => " AND $alias_dot_column = ?", 'value' => $ay];
    }
}
