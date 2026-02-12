<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    die("Unauthorized. <a href='index.php'>Login</a>");
}

include("config.php");

?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Debug</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #650000; color: white; }
        .good { color: green; font-weight: bold; }
        .bad { color: red; font-weight: bold; }
        h3 { color: #650000; margin-top: 30px; }
    </style>
</head>
<body>

<h2>🔍 Dashboard Debug Information</h2>

<h3>1. Placed Students Summary</h3>
<?php
$total = $conn->query("SELECT COUNT(*) as c FROM placed_students")->fetch_assoc()['c'];
echo "<p>Total records in placed_students: <span class='good'>$total</span></p>";
?>

<h3>2. Hired Counts by Drive</h3>
<table>
    <tr>
        <th>Drive ID</th>
        <th>Role ID</th>
        <th>Company</th>
        <th>Drive No</th>
        <th>Role Name</th>
        <th>Status</th>
        <th>Hired Count</th>
        <th>Student Names</th>
    </tr>
    <?php
    $query = "
        SELECT
            d.drive_id,
            dr.role_id,
            d.company_name,
            d.drive_no,
            dr.designation_name,
            CASE
                WHEN d.close_date < NOW() THEN 'Finished'
                WHEN d.open_date <= NOW() AND d.close_date >= NOW() THEN 'Current'
                ELSE 'Upcoming'
            END as drive_status,
            COUNT(DISTINCT ps.student_id) as hired_count,
            GROUP_CONCAT(DISTINCT ps.student_name ORDER BY ps.student_name SEPARATOR ', ') as students
        FROM drives d
        INNER JOIN drive_roles dr ON d.drive_id = dr.drive_id
        LEFT JOIN placed_students ps ON d.drive_id = ps.drive_id AND dr.role_id = ps.role_id
        GROUP BY d.drive_id, dr.role_id, d.company_name, d.drive_no, dr.designation_name, drive_status
        ORDER BY d.drive_no DESC, dr.role_id
        LIMIT 50
    ";

    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()):
        $color = $row['hired_count'] > 0 ? 'good' : 'bad';
    ?>
    <tr>
        <td><?= $row['drive_id'] ?></td>
        <td><?= $row['role_id'] ?></td>
        <td><?= htmlspecialchars($row['company_name']) ?></td>
        <td><?= $row['drive_no'] ?></td>
        <td><?= htmlspecialchars($row['designation_name']) ?></td>
        <td><?= $row['drive_status'] ?></td>
        <td class="<?= $color ?>"><?= $row['hired_count'] ?></td>
        <td><?= htmlspecialchars(substr($row['students'] ?? '', 0, 100)) ?></td>
    </tr>
    <?php endwhile; ?>
</table>

<h3>3. Drives Missing JD/Form</h3>
<table>
    <tr>
        <th>Drive ID</th>
        <th>Company</th>
        <th>Drive No</th>
        <th>JD Status</th>
        <th>Form Status</th>
        <th>Status</th>
    </tr>
    <?php
    $query = "
        SELECT
            drive_id,
            company_name,
            drive_no,
            CASE
                WHEN (jd_file IS NOT NULL AND jd_file != '') OR (jd_link IS NOT NULL AND jd_link != '') THEN 'Has JD'
                ELSE 'MISSING JD'
            END as jd_status,
            CASE
                WHEN form_link IS NOT NULL AND form_link != '' THEN 'Has Form'
                ELSE 'MISSING FORM'
            END as form_status,
            CASE
                WHEN close_date < NOW() THEN 'Finished'
                WHEN open_date <= NOW() AND close_date >= NOW() THEN 'Current'
                ELSE 'Upcoming'
            END as drive_status
        FROM drives
        WHERE (jd_file IS NULL OR jd_file = '')
          AND (jd_link IS NULL OR jd_link = '')
          OR (form_link IS NULL OR form_link = '')
        ORDER BY drive_no DESC
        LIMIT 20
    ";

    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()):
        $jd_color = strpos($row['jd_status'], 'MISSING') !== false ? 'bad' : 'good';
        $form_color = strpos($row['form_status'], 'MISSING') !== false ? 'bad' : 'good';
    ?>
    <tr>
        <td><?= $row['drive_id'] ?></td>
        <td><?= htmlspecialchars($row['company_name']) ?></td>
        <td><?= $row['drive_no'] ?></td>
        <td class="<?= $jd_color ?>"><?= $row['jd_status'] ?></td>
        <td class="<?= $form_color ?>"><?= $row['form_status'] ?></td>
        <td><?= $row['drive_status'] ?></td>
    </tr>
    <?php endwhile; ?>
</table>

<h3>4. Resume Files Status</h3>
<?php
$total_resumes = $conn->query("SELECT COUNT(*) as c FROM applications WHERE resume_file IS NOT NULL AND resume_file != ''")->fetch_assoc()['c'];
$in_resumes_dir = $conn->query("SELECT COUNT(*) as c FROM applications WHERE resume_file LIKE 'uploads/resumes/%'")->fetch_assoc()['c'];
$in_old_dir = $total_resumes - $in_resumes_dir;

echo "<p>Total resumes in database: <span class='good'>$total_resumes</span></p>";
echo "<p>In correct directory (uploads/resumes/): <span class='" . ($in_resumes_dir == $total_resumes ? 'good' : 'bad') . "'>$in_resumes_dir</span></p>";
echo "<p>In old directory (uploads/): <span class='" . ($in_old_dir == 0 ? 'good' : 'bad') . "'>$in_old_dir</span></p>";

if ($in_old_dir > 0) {
    echo "<p><a href='move_resume_files.php' style='background:#650000; color:white; padding:10px; text-decoration:none;'>→ Move Resume Files</a></p>";
}
?>

<h3>5. Quick Actions</h3>
<p>
    <a href="fix_hired_counts.php" style="background:#650000; color:white; padding:10px; text-decoration:none; margin:5px;">🔄 Fix Hired Counts</a>
    <a href="move_resume_files.php" style="background:#650000; color:white; padding:10px; text-decoration:none; margin:5px;">📁 Move Resumes</a>
    <a href="dashboard.php" style="background:#333; color:white; padding:10px; text-decoration:none; margin:5px;">← Dashboard</a>
</p>

</body>
</html>
