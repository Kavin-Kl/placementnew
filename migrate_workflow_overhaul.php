<?php
/**
 * One-shot migration for the workflow overhaul.
 * Idempotent — safe to run multiple times.
 *
 * Adds:
 *   - students.academic_year
 *   - placed_students.academic_year
 *   - drive_data.follow_status_team / follow_status_officer / min_ctc / max_ctc / min_stipend
 * Backfills:
 *   - students.academic_year from year_of_passing (or default 2026-2027)
 *   - drives.academic_year default 2026-2027 where blank
 *   - placed_students.academic_year from joined students.academic_year
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
include __DIR__ . '/config.php';

function column_exists(mysqli $conn, string $table, string $column): bool {
    $db = $conn->query("SELECT DATABASE()")->fetch_row()[0];
    $stmt = $conn->prepare(
        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->bind_param('sss', $db, $table, $column);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_row();
}

function add_column_if_missing(mysqli $conn, string $table, string $column, string $ddl): string {
    if (column_exists($conn, $table, $column)) {
        return "  - $table.$column already exists, skipping";
    }
    if (!$conn->query("ALTER TABLE `$table` ADD COLUMN $ddl")) {
        return "  ! FAILED $table.$column: " . $conn->error;
    }
    return "  + Added $table.$column";
}

$lines = [];
$lines[] = '== Workflow overhaul migration ==';

// 1. students.academic_year
$lines[] = add_column_if_missing(
    $conn, 'students', 'academic_year',
    '`academic_year` VARCHAR(20) NULL AFTER `year_of_passing`'
);

// 2. placed_students.academic_year
$lines[] = add_column_if_missing(
    $conn, 'placed_students', 'academic_year',
    '`academic_year` VARCHAR(20) NULL'
);

// 3. drive_data new columns
$lines[] = add_column_if_missing(
    $conn, 'drive_data', 'follow_status_team',
    '`follow_status_team` TEXT NULL'
);
$lines[] = add_column_if_missing(
    $conn, 'drive_data', 'follow_status_officer',
    '`follow_status_officer` TEXT NULL'
);
$lines[] = add_column_if_missing(
    $conn, 'drive_data', 'min_ctc',
    '`min_ctc` VARCHAR(50) NULL'
);
$lines[] = add_column_if_missing(
    $conn, 'drive_data', 'max_ctc',
    '`max_ctc` VARCHAR(50) NULL'
);
$lines[] = add_column_if_missing(
    $conn, 'drive_data', 'min_stipend',
    '`min_stipend` VARCHAR(50) NULL'
);
$lines[] = add_column_if_missing(
    $conn, 'drive_data', 'max_stipend',
    '`max_stipend` VARCHAR(50) NULL'
);

// 4. Backfills
$lines[] = '-- Backfills --';

$conn->query("UPDATE students
                SET academic_year = CONCAT(year_of_passing - 1, '-', year_of_passing)
              WHERE (academic_year IS NULL OR academic_year = '')
                AND year_of_passing IS NOT NULL");
$lines[] = '  students.academic_year backfilled from year_of_passing for ' . $conn->affected_rows . ' rows';

$conn->query("UPDATE students SET academic_year = '2026-2027'
              WHERE academic_year IS NULL OR academic_year = ''");
$lines[] = '  students.academic_year defaulted for ' . $conn->affected_rows . ' rows';

$conn->query("UPDATE drives SET academic_year = '2026-2027'
              WHERE academic_year IS NULL OR academic_year = ''");
$lines[] = '  drives.academic_year defaulted for ' . $conn->affected_rows . ' rows';

$conn->query("UPDATE placed_students ps
                JOIN students s ON ps.student_id = s.student_id
                 SET ps.academic_year = s.academic_year
               WHERE ps.academic_year IS NULL OR ps.academic_year = ''");
$lines[] = '  placed_students.academic_year synced from students for ' . $conn->affected_rows . ' rows';

$lines[] = '== Done ==';

if (php_sapi_name() === 'cli') {
    echo implode("\n", $lines), "\n";
} else {
    echo '<pre>', htmlspecialchars(implode("\n", $lines)), '</pre>';
    echo '<p style="font-family:sans-serif;color:#080">Migration complete. You can delete <code>migrate_workflow_overhaul.php</code> after confirming the schema.</p>';
}
