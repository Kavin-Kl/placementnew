<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
require __DIR__ . '/config.php';
require __DIR__ . '/placed_import_helper.php';

$app_id = isset($argv[1]) ? (int)$argv[1] : 0;
if (!$app_id) { fwrite(STDERR, "Usage: php _backfill_one_placement.php <application_id>\n"); exit(1); }

// Trace each stage so we can see exactly where the helper bails out.
$sql = "
    SELECT a.application_id, a.student_id, a.drive_id, a.role_id, a.percentage,
           a.placement_batch, a.upid AS app_upid, a.status,
           s.upid, s.program_type, s.program, s.course, s.reg_no,
           s.student_name, s.email, s.phone_no, s.allow_reapply, s.year_of_passing,
           d.company_name, d.drive_no, d.academic_year,
           dr.designation_name AS role, dr.ctc, dr.stipend, dr.offer_type
      FROM applications a
      JOIN students s     ON a.student_id = s.student_id
      JOIN drives d       ON a.drive_id  = d.drive_id
      JOIN drive_roles dr ON a.role_id   = dr.role_id
     WHERE a.application_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $app_id);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
echo "Loaded row:\n"; var_export($r); echo "\n";

$ok = pi_upsert_placed_from_application($conn, $app_id);
echo "\nResult: " . ($ok ? "INSERTED" : "NO INSERT") . "\n";
