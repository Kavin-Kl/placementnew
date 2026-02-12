<?php
// ----------------------------
// Session & admin auth
// ----------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// ----------------------------
// DB connection
include("config.php");
include("course_groups_dynamic.php");
// ----------------------------
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// ----------------------------
// Upload folder
// ----------------------------
const UPLOAD_DIR = 'uploads/';
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// ----------------------------
// Flash helpers
// ----------------------------
function flash($key, $message = null)
{
    if ($message === null) {
        $value = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $value;
    }
    $_SESSION['flash'][$key] = $message;
}
function redirect($url)
{
    header("Location: $url");
    exit;
}

// ----------------------------
// Validate drive_id
// ----------------------------
$drive_id = isset($_GET['drive_id']) ? (int) $_GET['drive_id'] : 0;
if (!$drive_id) {
    redirect('dashboard.php');
}

$success = '';
$error   = '';

// ----------------------------
// Fetch drive + roles (initial page load)
// ----------------------------
$stmt = $pdo->prepare('SELECT * FROM drives WHERE drive_id = ?');
$stmt->execute([$drive_id]);
$drive = $stmt->fetch();

// Format the dates for display - use POST values if available (after form submission)
if ($drive) {
    // Check if we're coming from a POST submission
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $drive['open_date_display'] = $_POST['open_date'] ?? '';
        $drive['close_date_display'] = $_POST['close_date'] ?? '';
    } else {
        // Format dates from database (display as d-m-Y H:i)
        $drive['open_date_display'] = $drive['open_date'] ? date('d-m-Y H:i', strtotime($drive['open_date'])) : '';
        $drive['close_date_display'] = $drive['close_date'] ? date('d-m-Y H:i', strtotime($drive['close_date'])) : '';
    }
}

if (!$drive) {
    flash('error', 'Drive not found');
    redirect('drives.php');
}

$stmt = $pdo->prepare('SELECT * FROM drive_roles WHERE drive_id = ? ORDER BY role_id');
$stmt->execute([$drive_id]);
$roles = $stmt->fetchAll();

// Add a simple counter so the view can use sequential indexes
$counter = 0;
foreach ($roles as &$role) {
    $role['counter'] = $counter++;
}
unset($role);                     // ⚠️ break the reference!

// Decode JSON blobs once so the view can use them directly
$drive['jd_file']      = json_decode($drive['jd_file']      ?? '[]',  true);
$drive['extra_details'] = json_decode($drive['extra_details'] ?? '{}', true);

foreach ($roles as &$role) {
    $role['eligible_courses'] = json_decode($role['eligible_courses'] ?? '[]', true);
    // $role['form_fields'] is not needed anymore
}
unset($role);

// decode drive-level form fields
$drive_form_fields = json_decode($drive['form_fields'] ?? '[]', true) ?: [];
// -----------------------------------------------------------------------------
// Handle POST (update drive + roles)
// -----------------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Process dates - convert from d-m-Y H:i to Y-m-d H:i:s for DB
        $open = null;
        $close = null;
        if (!empty($_POST['open_date'])) {
            $dt = DateTime::createFromFormat('d-m-Y H:i', $_POST['open_date']);
            if ($dt) { $open = $dt->format('Y-m-d H:i:s'); }
        }
        if (!empty($_POST['close_date'])) {
            $dt2 = DateTime::createFromFormat('d-m-Y H:i', $_POST['close_date']);
            if ($dt2) { $close = $dt2->format('Y-m-d H:i:s'); }
        }
        
        // Validate: Close date cannot be before open date
        if ($open && $close && strtotime($close) < strtotime($open)) {
            die("Error: Close date & time cannot be earlier than open date & time.");
        }
    
        // -------------------------------
        // Capture drive-level form fields
        // -------------------------------
      $form_fields_json = !empty($_POST['drive_form_fields']) ? json_encode($_POST['drive_form_fields']) : '[]';

        // -------------------------------------------------
        // Basic drive columns
        // -------------------------------------------------
        $company    = $_POST['company_name'];
        $extra_json = $_POST['extra_details'] ?? '{}';
        $jd_link    = $_POST['jd_link'] ?? '';
        $company_url     = $_POST['company_url'] ?? '';
        $graduating_year = $_POST['graduating_year'] ?? '';
        $work_location   = $_POST['work_location'] ?? '';
        $academic_year   = $_POST['academic_year'] ?? '2025-2026';

        // Get eligibility checkboxes (1 if checked, 0 if not)
        $show_to_internship = isset($_POST['show_to_internship']) ? 1 : 0;
        $show_to_vantage = isset($_POST['show_to_vantage']) ? 1 : 0;
        $show_to_placement = isset($_POST['show_to_placement']) ? 1 : 0;

         // Added JD Link field

        // -------------------------------------------------
        // Handle JD files (append new uploads, keep old ones)
        // -------------------------------------------------
        $jd_files = [];

        // Keep existing files unless marked for deletion
        if (!empty($_POST['existing_jd_files'])) {
            foreach ($_POST['existing_jd_files'] as $existingFile) {
                if (file_exists($existingFile)) {
                    $jd_files[] = $existingFile;
                }
            }
        }

        // Handle file deletions
        if (!empty($_POST['deleted_jd_files'])) {
            foreach ($_POST['deleted_jd_files'] as $fileToDelete) {
                if (file_exists($fileToDelete)) {
                    unlink($fileToDelete);
                }
            }
        }

        // Handle new file uploads
        if (!empty($_FILES['jd_files'])) {
            foreach ($_FILES["jd_files"]["tmp_name"] as $key => $tmp_name) {
                $filename = uniqid("JD_") . "_" . basename($_FILES["jd_files"]["name"][$key]);
                $target = "uploads/" . $filename;
                if (move_uploaded_file($tmp_name, $target)) {
                    $jd_files[] = $target;
                }
            }
        }

        $jd_json = json_encode($jd_files);

        // Update drives row
        //$pdo->prepare('UPDATE drives SET company_name = ?, open_date = ?, close_date = ?, jd_file = ?, extra_details = ?, jd_link = ? WHERE drive_id = ?')
          //  ->execute([$company, $open, $close, $jd_json, $extra_json, $jd_link, $drive_id]);
    $pdo->prepare('UPDATE drives
    SET company_name = ?, open_date = ?, close_date = ?, jd_file = ?, extra_details = ?, jd_link = ?, company_url = ?, graduating_year = ?, work_location = ?, form_fields = ?, show_to_internship = ?, show_to_vantage = ?, show_to_placement = ?, academic_year = ?
    WHERE drive_id = ?')
    ->execute([
        $company,
        $open,
        $close,
        $jd_json,
        $extra_json,
        $jd_link,
        $company_url,
        $graduating_year,
        $work_location,
        $form_fields_json,
        $show_to_internship,
        $show_to_vantage,
        $show_to_placement,
        $academic_year,
        $drive_id
    ]);

        // -------------------------------------------------
        // Build list of *existing* role_ids for later deletion
        // Re-fetch updated drive record


        // -------------------------------------------------
        
        $stmt = $pdo->prepare('SELECT role_id FROM drive_roles WHERE drive_id = ?');
        $stmt->execute([$drive_id]);
        $existing_role_ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));

        // -------------------------------------------------
        // Loop over roles passed by the form
        // -------------------------------------------------
        if (!empty($_POST['role_name'])) {
            foreach ($_POST['role_name'] as $idx => $role_name) {
                if ($role_name === '') {
                    continue;   // skip blank rows
                }

                $role_id = (int) ($_POST['role_id'][$idx] ?? 0);
                $rawCourses = $_POST["eligible_courses"][$idx] ?? '[]';

                if ($rawCourses === 'All UG Courses') {
                    // Get all UG courses
                    $coursesArray = [];
                    foreach ($ug_courses_grouped as $groups) {
                        foreach ($groups as $groupCourses) {
                            $coursesArray = array_merge($coursesArray, $groupCourses);
                        }
                    }
                } elseif ($rawCourses === 'All PG Courses') {
                    // Get all PG courses
                    $coursesArray = [];
                    foreach ($pg_courses_grouped as $groups) {
                        foreach ($groups as $groupCourses) {
                            $coursesArray = array_merge($coursesArray, $groupCourses);
                        }
                    }
                } elseif ($rawCourses === 'All UG and PG Courses') {
                    // Get all courses
                    $coursesArray = [];
                    foreach ($ug_courses_grouped as $groups) {
                        foreach ($groups as $groupCourses) {
                            $coursesArray = array_merge($coursesArray, $groupCourses);
                        }
                    }
                    foreach ($pg_courses_grouped as $groups) {
                        foreach ($groups as $groupCourses) {
                            $coursesArray = array_merge($coursesArray, $groupCourses);
                        }
                    }
                } else {
                    // Handle individual course selection as before
                    $coursesArray = is_array($rawCourses) ? $rawCourses : json_decode($rawCourses, true);
                    if (!is_array($coursesArray)) {
                        $coursesArray = array_map('trim', explode(',', $rawCourses));
                    }
                }

                $courses = json_encode($coursesArray);

                $min_percent = !empty($_POST['min_percentage'][$idx]) ? $_POST['min_percentage'][$idx] : null;
                $role_ctc     = $_POST['ctc'][$idx]           ?? '';
                $role_stipend = $_POST['stipend'][$idx]        ?? '';
                $work_timings = $_POST['work_timings'][$idx] ?? '';
 // Added stipend field
                $offer_type   = $_POST['offer_types'][$idx]   ?? 'FTE';
                $sector       = $_POST['sectors'][$idx]       ?? 'IT';

                // Update drive_data table for existing roles
                if ($role_id > 0) {
                    $pdo->prepare('UPDATE drive_data 
                        SET company_name = ?, 
                            role = ?, 
                            offer_type = ?, 
                            sector = ?, 
                            eligible_courses = ?
                        WHERE drive_id = ? AND role_id = ?
                    ')->execute([$company, $role_name, $offer_type, $sector, $courses, $drive_id, $role_id]);
                }

                // ------------ form fields ------------
                $form_fields = [];
                if (isset($_POST['form_fields'][$idx])) {
                    foreach ($_POST['form_fields'][$idx] as $field) {
                        if ($field['name'] !== '') {
                            // Determine field type based on field name and options
                            $field_type = $field['type'] ?? 'text';
                            
                            // If type is not set, try to determine from field name and options
                            if (!isset($field['type']) || $field['type'] === 'text') {
                                $field_name = strtolower($field['name']);
                                $options = $field['options'] ?? '';
                                $optsArray = is_array($options)
                                    ? array_values(array_filter(array_map('trim', $options), fn($v) => $v !== ''))
                                    : array_values(array_filter(array_map('trim', explode(',', $options)), fn($v) => $v !== ''));
                                if (count($optsArray) === 1) {
                                    $field_type = 'checkbox';
                                } elseif (!empty($options) && strpos($options, ',') !== false) {
                                    $field_type = 'select';
                                } elseif (strpos($field_name, 'email') !== false) {
                                    $field_type = 'email';
                                } elseif (strpos($field_name, 'phone') !== false || strpos($field_name, 'mobile') !== false) {
                                    $field_type = 'tel';
                                } elseif (strpos($field_name, 'percentage') !== false || strpos($field_name, 'cgpa') !== false) {
                                    $field_type = 'number';
                                } elseif (strpos($field_name, 'declaration') !== false || strpos($field_name, 'agree') !== false) {
                                    $field_type = 'checkbox';
                                }
                            }
                            
                            $form_fields[] = [
                                'name'      => $field['name'],
                                'type'      => $field_type,
                                'options'   => $field['options']  ?? '',
                                'placeholder' => $field['placeholder'] ?? '',
                                'mandatory' => empty($field['mandatory']) ? 0 : 1,
                            ];
                        }
                    }
                }
                $form_json = json_encode($form_fields);

                // ------------ update vs insert ------------
                if ($role_id > 0) {
                    // update
                    $pdo->prepare('UPDATE drive_roles SET designation_name = ?, eligible_courses = ?, min_percentage = ?, ctc = ?, stipend = ?, work_timings = ?, offer_type = ?, sector = ?, form_fields = ? WHERE role_id = ?')
    ->execute([$role_name, $courses, $min_percent, $role_ctc, $role_stipend, $work_timings, $offer_type, $sector, $form_json, $role_id]);

                    // keep it from being deleted later
                    if (($k = array_search($role_id, $existing_role_ids, true)) !== false) {
                        unset($existing_role_ids[$k]);
                    }
                } else {
                    // insert
                    $pdo->prepare('INSERT INTO drive_roles (drive_id, designation_name, eligible_courses, min_percentage, ctc, stipend, work_timings, offer_type, sector, form_fields) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
    ->execute([$drive_id, $role_name, $courses, $min_percent, $role_ctc, $role_stipend, $work_timings, $offer_type, $sector, $form_json]);

                    // Get the newly inserted role_id
                    $new_role_id = $pdo->lastInsertId();
                    
                    // Now insert into drive_data with the correct role_id
                    $pdo->prepare('INSERT INTO drive_data 
                        (drive_id, company_name, drive_no, role, offer_type, sector, eligible_courses, company_status, role_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ')->execute([$drive_id, $company, $drive['drive_no'], $role_name, $offer_type, $sector, $courses, 'Yet to Start', $new_role_id]);
                }
            }
        }

        // -------------------------------------------------
// Delete roles that the user removed in the form
// -------------------------------------------------
// -------------------------------------------------
// Delete roles that the user removed in the form
// -------------------------------------------------
// -------------------------------------------------
// Delete roles that the user removed in the form
// -------------------------------------------------
if (!empty($existing_role_ids)) {
    $in = implode(',', array_fill(0, count($existing_role_ids), '?'));
    
    try {
        // Start a transaction to ensure all deletions happen or none
        $pdo->beginTransaction();
        
        // Delete from all tables that reference role_id
        // Add any other tables that might have foreign keys to drive_roles
        // DISABLED: Import from Excel already has complete data
        // include_once __DIR__ . '/sync_placed_students.php';
        // sync_placed_students($conn);
        $tables_to_clear = [
            'placed_students',
            'applications', 
            'drive_data'
            // Add any other tables that reference role_id here
        ];
        
        foreach ($tables_to_clear as $table) {
            $sql = "DELETE FROM $table WHERE role_id IN ($in)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($existing_role_ids));
        }
        
        // Finally delete from drive_roles table
        $sql = "DELETE FROM drive_roles WHERE role_id IN ($in)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($existing_role_ids));
        
        // Commit the transaction
        $pdo->commit();
        
    } catch (PDOException $e) {
        // Rollback the transaction on error
        $pdo->rollBack();
        $error = 'Error deleting roles: ' . $e->getMessage();
        error_log($error);
        throw new Exception($error);
    }
}
// -------------------------------------------------
// Recalculate drive numbers per company after date change
// -------------------------------------------------
$stmt = $pdo->query("
    SELECT drive_id, company_name, open_date, close_date 
    FROM drives 
    ORDER BY company_name ASC, open_date ASC, close_date ASC
");

$allDrives = $stmt->fetchAll();
$currentCompany = null;
$driveNumber = 0;

foreach ($allDrives as $d) {
    if ($d['company_name'] !== $currentCompany) {
        // new company group, reset numbering
        $currentCompany = $d['company_name'];
        $driveNumber = 1;
    } else {
        $driveNumber++;
    }

    $driveLabel = "Drive " . $driveNumber;

    // Update in drives table
    $pdo->prepare("UPDATE drives SET drive_no = ? WHERE drive_id = ?")
        ->execute([$driveLabel, $d['drive_id']]);

    // Update in drive_data table (sync roles)
    $pdo->prepare("UPDATE drive_data SET drive_no = ? WHERE drive_id = ?")
        ->execute([$driveLabel, $d['drive_id']]);
}


        $success = 'Drive updated successfully!';

        // -----------------------------------------------------------------
        // Re‑fetch the fresh data so the page can re‑render immediately
        // -----------------------------------------------------------------
       // Re-fetch updated drive record
$stmt = $pdo->prepare('SELECT * FROM drives WHERE drive_id = ?');
$stmt->execute([$drive_id]);
$drive = $stmt->fetch();

// Update display-friendly dates
$drive['open_date_display']  = $drive['open_date'] ? date('d-m-Y H:i', strtotime($drive['open_date'])) : '';
$drive['close_date_display'] = $drive['close_date'] ? date('d-m-Y H:i', strtotime($drive['close_date'])) : '';

        $stmt = $pdo->prepare('SELECT * FROM drive_roles WHERE drive_id = ? ORDER BY role_id');
        $stmt->execute([$drive_id]);
        $roles = $stmt->fetchAll();

        // fresh decode
        $drive['jd_file']       = json_decode($drive['jd_file']       ?? '[]',  true);
        $drive['extra_details'] = json_decode($drive['extra_details'] ?? '{}', true);
    foreach ($roles as &$role) {
    $role['eligible_courses'] = json_decode($role['eligible_courses'] ?? '[]', true);
    // $role['form_fields'] is not needed anymore
}
unset($role);

// decode drive-level form fields
$drive_form_fields = json_decode($drive['form_fields'] ?? '[]', true) ?: [];
    } catch (PDOException $e) {
        $error = 'Error updating drive: ' . $e->getMessage();
    }
}

// Course data for the form

// -----------------------------------------------------------------------------
// Static arrays for the form (unchanged)
// -----------------------------------------------------------------------------
$offer_types = ['FTE', 'Internship + PPO', 'Apprentice','Internship'];
$sectors = [
    'BFSI',
    'Sales , Marketing, BD ',
    'HR',
    'Consulting',
    'Analytics',
    'Ops & Management',
    'IT',
    'Healthcare & Wellness',
    'Ed & Teaching',
    'Hospitality & Tourism',
    'Media & Content',
    'Customer/Client Service',
    'Fashion & Design',
    'Int Design Mgmt',
    'Research',
    'Resource Planning & Logistics'
];
$field_types = ['text', 'number', 'email', 'textarea', 'select', 'file', 'checkbox', 'radio'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Drive - Drive Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .reset-button {
            border: 1px solid #650000 !important;  
            color: #650000 !important;             
            border-radius: 6px;
            padding: 0.5rem 1rem !important;  /* padding controls button size */
            font-weight: 700;
            height: 35px !important;     /* keeps icon + text aligned */
            align-items: center;        /* vertical centering */
            justify-content: center;    /* horizontal centering */
            gap: 6px;                   /* spacing between icon & text */
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
            background: white;
   }

        .reset-button:hover {
            background: #f2f2f2 !important;
            color: #650000 !important;
        }

        .required-asterisk {
            color: red;
        }
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8fafc;
        }

        /* Navigation */
        .navbar {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-brand {
            font-size: 1.5rem;
            font-weight: bold;
            color: #1e293b;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-links {
            display: flex;
            gap: 1rem;
            list-style: none;
        }

        .nav-link {
            color: #64748b;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            transition: all 0.2s;
        }

        .nav-link:hover,
        .nav-link.active {
            color: #1e293b;
            background-color: #f1f5f9;
        }
        

        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }

        .card-content {
            padding: 1.5rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0.375rem;
            border: 1px solid transparent;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .btn-primary:hover {
            background-color: #2563eb;
            border-color: #2563eb;
        }

        .btn-secondary {
            background-color: #800400;
            
            color:rgb(227, 238, 255);
            border-color: #800400;
            ;
        }

        
        .btn-danger {
            background-color: #ef4444;
            color: white;
            border-color: #ef4444;
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }

        /* Forms */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
            color: #374151;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            transition: border-color 0.2s;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 0.25rem;
        }

        .badge-primary {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .badge-secondary {
            background-color: #f1f5f9;
            color: #475569;
        }

        .badge-success {
            background-color: #dcfce7;
            color: #166534;
        }

        .badge-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }

        /* Modals */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal {
            background: white;
            border-radius: 0.5rem;
            max-width: 90vw;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .modal-content {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }
         /* Form Fields Popup - Updated to match image */
         .form-fields-popup {
            background: white;
            border-radius: 0.5rem;
            padding: 0;
            width: 500px;
            max-width: 90vw;
            max-height: 80vh;
            overflow-y: auto;
        }

        .form-fields-popup-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .form-fields-popup-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }

        .form-fields-popup-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #64748b;
        }

        .form-fields-search-container {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .form-fields-search {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }

        .form-fields-categories {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .form-fields-category {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .form-fields-list {
            max-height: 300px;
            overflow-y: auto;
            padding: 0 1.5rem;
            gap:
            
        }
      
.form-field-item button {
  margin-left: 1rem;
  padding: 0.50rem;
}

        .form-field-item {
            display: flex;
            align-items: flex-start;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .form-field-item:last-child {
            border-bottom: none;
        }

        .form-field-checkbox {
            margin-right: 0.75rem;
            margin-top: 0.25rem;
        }

        .form-field-details {
            flex: 1;
        }
        

        .form-field-name {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .form-field-options {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 0.25rem;
        }

        .form-field-mandatory {
            font-size: 0.75rem;
            color: #ef4444;
            font-weight: 500;
        }

        .form-fields-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        /* Tables */
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .table th {
            font-weight: 600;
            background-color: #f8fafc;
        }

        /* Role Editor */
        .role-block {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            background: white;
        }

        .role-header {
            padding: 1rem;
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .role-header:hover {
            background-color: #f1f5f9;
        }

        .role-content {
            padding: 1rem;
            display: none;
        }

        .role-content.expanded {
            display: block;
        }

        /* Course Selection */
        .course-group {
            margin-bottom: 1rem;
        }

        .course-group-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
        }

        .course-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.5rem;
        }

        .course-tabs {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 1rem;
        }

        .course-tab {
            padding: 0.5rem 1rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }

        .course-tab.active {
            border-bottom-color: #3b82f6;
            color: #3b82f6;
        }

        /* Form Fields Editor */
        /* Dropdown animation for Application Form Fields */
        .form-field-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .form-field-item-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }


/* Dropdown behavior */
.dropdown-section {
    transition: max-height 0.4s ease, opacity 0.3s ease;
    overflow: hidden;
}

/* Collapsed (hidden) */
.dropdown-section:not(.show) {
    max-height: 0;
    opacity: 0;
    padding-top: 0;
    padding-bottom: 0;
}

/* Expanded (visible) */
.dropdown-section.show {
    max-height: 2000px; /* Large enough for full content */
    opacity: 1;
}

/* Form fields container styling */
.form-fields-container {
     margin-top: 1rem;
    border-top: 1px solid #e2e8f0;
    padding-top: 1rem;

    width: 55%;
}

/* Individual field items */
.form-field-item {
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 0.6rem;
    background: #fff;
}

/* Chevron icon rotation */
#formFieldToggleIcon {
    transition: transform 0.3s ease;
}

#formFieldToggleIcon.rotate {
    transform: rotate(180deg);
}

       
        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                flex-direction: column;
                gap: 0.5rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modal {
                max-width: 95vw;
            }
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Loading */
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .spinner {
            width: 2rem;
            height: 2rem;
            border: 2px solid #e2e8f0;
            border-top: 2px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Grid layouts */
        .grid {
            display: grid;
            gap: 1rem;
        }

        .grid-cols-1 {
            grid-template-columns: repeat(1, 1fr);
        }
        .grid-cols-2 {
            grid-template-columns: repeat(2, 1fr);
        }
        .grid-cols-3 {
            grid-template-columns: repeat(3, 1fr);
        }

        @media (min-width: 768px) {
            .md\:grid-cols-2 {
                grid-template-columns: repeat(2, 1fr);
            }
            .md\:grid-cols-3 {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        /* Utilities */
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .flex {
            display: flex;
        }
        .flex-col {
            flex-direction: column;
        }
        .items-center {
            align-items: center;
        }
        .justify-between {
            justify-content: space-between;
        }
        .mt-4 {
            margin-top: 1rem;
        }
        .mb-4 {
            margin-bottom: 1rem;
        }
        .p-4 {
            padding: 1rem;
        }
        .hidden {
            display: none;
        }

        .course-content {
            max-height: 400px;
            overflow-y: auto;
        }

        .field-content {
            max-height: 400px;
            overflow-y: auto;
        }gap-2 {
            gap: 0.5rem;
        }
        .gap-4 {
            gap: 1rem;
        }
        .mt-2 {
            margin-top: 0.5rem;
        }
        .flatpickr-close {
        position: absolute;
        top: 5px;
        right: 35px;
        font-size: 18px;
        cursor: pointer;
        color: #999;
        z-index: 10;
    }
    .flatpickr-close:hover {
        color: #333;
    }
    /* Scrollable container for Application Form Fields */
.form-fields-container {
  max-height: 420px;
  overflow-y: auto;
  padding: .75rem .5rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
}

/* Keep the Add Field button visible at the bottom of the card */
.drive-fields-actions {
  position: sticky;
  bottom: 0;
  display: flex;
  justify-content: flex-end;
  gap: .5rem;
  padding: .75rem;
  background: #fff;
  border-top: 1px solid #e2e8f0;
}

/* Compact inline editor layout for a single field row */
.field-row {
  display: grid;
  grid-template-columns: 2fr 1.2fr 2fr auto auto;
  gap: .5rem;
  align-items: center;
}

.field-row > .form-input,
.field-row > .form-select {
  min-width: 0;
}

    </style>
</head>
<body>
   
    <div class="container">
        <!-- Header -->
        <div class="flex items-center gap-4 mb-4">
            <div>
            
                <h1 style="font-size: 2rem; font-weight: bold;">Edit Drive</h1>

                <p style="color: #64748b;">
    Update <?= htmlspecialchars($drive['company_name'] ?? '') ?>
    (<?= htmlspecialchars($drive['drive_no'] ?? '') ?>) drive details and roles
</p>
            </div><br>
            <div class="flex items-center gap-4">
        <a href="dashboard" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title">Drive Information</h3>
                <a href="edit_form_fields.php?drive_id=<?= $drive_id ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-edit"></i> Customize Application Form
                </a>
            </div>
            <div class="card-content">
                <form method="POST" enctype="multipart/form-data" id="driveForm" action="">
                    <input type="hidden" name="drive_id" value="<?= $drive_id ?>">

                    <!-- Basic Drive Info -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Company Name <span class="required-asterisk">*</span></label>
                           <input type="text" name="company_name" class="form-input" value="<?= htmlspecialchars($drive['company_name'] ?? '') ?>" required>  </div>
                        <div class="form-group">
    <label class="form-label">Form Open Date & Time <span class="required-asterisk">*</span></label>
    <input type="text" id="open_date" name="open_date" class="form-input" 
           value="<?= htmlspecialchars($drive['open_date_display'] ?? '') ?>" required>
</div>
<div class="form-group">
    <label class="form-label">Form Close Date & Time <span class="required-asterisk">*</span></label>
    <input type="text" id="close_date" name="close_date" class="form-input" 
           value="<?= htmlspecialchars($drive['close_date_display'] ?? '') ?>" required>
</div>
                        <div class="form-group">
    <label class="form-label">JD Files</label>
    <input type="file" name="jd_files[]" class="form-input" multiple>
    <?php if (!empty($drive['jd_file'])): ?>
        <div style="margin-top: 0.5rem;">
            <div style="font-size: 0.875rem; color: #64748b; margin-bottom: 0.25rem;">Current files:</div>
            <div id="currentJdFiles" style="max-width: 100%;">
                <?php foreach ($drive['jd_file'] as $index => $file): ?>
                    <div class="flex items-center justify-between gap-2 mb-1 p-2 bg-gray-50 rounded" id="jdFile-<?= $index ?>" style="word-break: break-all;">
                        <span class="text-sm flex-1 truncate" title="<?= htmlspecialchars(basename($file)) ?>">
                            <?= htmlspecialchars(basename($file)) ?>
                        </span>
                        <button type="button" class="text-red-500 hover:text-red-700 flex-shrink-0" onclick="removeJdFile('<?= $index ?>', '<?= urlencode($file) ?>')">
                            <i class="fas fa-times"></i>
                        </button>
                        <input type="hidden" name="existing_jd_files[]" value="<?= $file ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
                        <div class="form-group">
    <label class="form-label">JD Link</label>
    <input type="url" name="jd_link" class="form-input" value="<?= htmlspecialchars($drive['jd_link'] ?? '') ?>" placeholder="https://example.com/jd.pdf">
</div>

<!-- NEW drive-level fields: company_url, graduating_year, work_location -->
    <div class="form-group">
        <label class="form-label">Company URL</label>
        <input type="url" name="company_url" class="form-input" value="<?= htmlspecialchars($drive['company_url'] ?? '') ?>" placeholder="https://company.example.com">
    </div>

    <div class="form-group">
        <label class="form-label">Graduating Year</label>
        <input type="text" name="graduating_year" class="form-input" value="<?= htmlspecialchars($drive['graduating_year'] ?? '') ?>" placeholder="e.g. 2026">
    </div>

    <div class="form-group">
        <label class="form-label">Work Location</label>
        <input type="text" name="work_location" class="form-input" value="<?= htmlspecialchars($drive['work_location'] ?? '') ?>" placeholder="City / Remote">
    </div>

    <div class="form-group">
        <label class="form-label">Academic Year</label>
        <input type="text" name="academic_year" class="form-input" value="<?= htmlspecialchars($drive['academic_year'] ?? '2025-2026') ?>" placeholder="e.g. 2025-2026">
    </div>

    <div class="form-group">
        <label class="form-label" style="font-weight: bold;">Show This Drive To:</label>
        <div style="margin-left: 20px; margin-top: 10px;">
            <label style="display: block; margin-bottom: 8px;">
                <input type="checkbox" name="show_to_internship" value="1" <?= ($drive['show_to_internship'] ?? 1) ? 'checked' : '' ?> style="margin-right: 8px;">
                Internship Registered Students
            </label>
            <label style="display: block; margin-bottom: 8px;">
                <input type="checkbox" name="show_to_vantage" value="1" <?= ($drive['show_to_vantage'] ?? 1) ? 'checked' : '' ?> style="margin-right: 8px;">
                Vantage Registered Students
            </label>
            <label style="display: block; margin-bottom: 8px;">
                <input type="checkbox" name="show_to_placement" value="1" <?= ($drive['show_to_placement'] ?? 1) ? 'checked' : '' ?> style="margin-right: 8px;">
                Placement Registered Students (Full-Time/FTE)
            </label>
        </div>
    </div>


                    </div>

                    <!-- Extra Details -->
                    <div class="form-group">
                        <button type="button" class="btn btn-secondary" onclick="openExtraModal()">
                            <i class="fas fa-plus"></i> Extra Drive Details
                        </button>
                        <div id="extraDetailsDisplay" class="mt-2" style="color: #16a34a; font-size: 0.875rem;"></div>
                        <input type="hidden" name="extra_details" id="extraDetailsInput" value="<?= htmlspecialchars(json_encode($drive['extra_details'])) ?>">
                    </div>
                    <!-- Put this where the Add Field button is (inside the Application Form Fields card header/content) -->

<!-- Toggle Button -->
<button type="button" class="btn btn-secondary mt-3" onclick="toggleAppFormFields()">
  <i class="fas fa-chevron-down" id="formFieldToggleIcon"></i> Application Form Fields
</button>

<!-- Application Form Fields Section (initially hidden) -->
<div id="applicationFormFieldsSection" class="dropdown-section">
    <div class="card mt-4">
        <div class="card-header">
            <h4>Application Form Fields</h4>
        </div>
        <div class="card-content">
            <div id="driveFormFieldsContainer" class="form-fields-container">
                <?php if (!empty($drive_form_fields)): ?>
                    <?php foreach ($drive_form_fields as $idx => $field): ?>
                        <div class="form-field-item" style="display: flex; justify-content: space-between; align-items: center;margin-bottom: 0.5rem; width: 100%;">
                            <strong><?= htmlspecialchars($field['name']) ?></strong>
                            <div style="display: inline-block; margin-left: 10px;">
                                <button type="button" class="btn btn-danger btn-sm" onclick="removeDriveFormFieldItem(this)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <input type="hidden" name="drive_form_fields[<?= $idx ?>][name]" value="<?= htmlspecialchars($field['name']) ?>">
                            <input type="hidden" name="drive_form_fields[<?= $idx ?>][type]" value="<?= htmlspecialchars($field['type'] ?? 'text') ?>">
                            <input type="hidden" name="drive_form_fields[<?= $idx ?>][options]" value="<?= htmlspecialchars($field['options'] ?? '') ?>">
                            <input type="hidden" name="drive_form_fields[<?= $idx ?>][mandatory]" value="<?= !empty($field['mandatory']) ? '1' : '0' ?>">
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="color: #64748b;">No application form fields defined for this drive.</div>
                <?php endif; ?>
            </div>
            <!-- Add Field Button -->
            <button type="button" class="btn btn-secondary mt-2" onclick="openDriveFormFieldModal()">
                <i class="fas fa-plus"></i> Add Field
            </button>
        </div>
    </div>
</div>


                    <!-- Roles Section -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <div class="flex justify-between items-center">
                                <h4 style="margin: 0;">Job Roles</h4>
                                <button type="button" class="btn btn-primary btn-sm" onclick="addRole()">
                                    <i class="fas fa-plus"></i> Add Role
                </button>
                            </div>
                        </div>
                        <div class="card-content">
                            <div id="rolesContainer">
                                <?php if (empty($roles)): ?>
                                    <div class="text-center" style="padding: 2rem; color: #64748b;">
                                        No roles added yet. Click "Add Role" to get started.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($roles as $index => $role): ?>
                                        <div class="role-block" id="role-<?= $index ?>">
                                            <div class="role-header" onclick="toggleRole(<?= $index ?>)">
                                                <div>
                                                    <strong><?= htmlspecialchars($role['designation_name']) ?></strong>
                                                    <span class="badge badge-primary ml-2"><?= htmlspecialchars($role['offer_type'] ?? 'FTE') ?></span>
                                                    <span class="badge badge-secondary ml-1"><?= htmlspecialchars($role['sector'] ?? 'IT') ?></span>
                                                </div>
                                                <div>
                                                    <button type="button" class="btn btn-danger btn-sm" onclick="removeRole(<?= $index ?>, event)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="role-content expanded">
                                                <div class="form-grid">
                                                    <input type="hidden" name="role_id[]" value="<?= $role['role_id'] ?>">
                                                    <div class="form-group">
                                                        <label class="form-label">Role Name <span class="required-asterisk">*</span></label>
                                                        <input type="text" name="role_name[]" class="form-input" value="<?= htmlspecialchars($role['designation_name']) ?>" >
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="form-label">Minimum Percentage </label>
                                                        <input type="text" name="min_percentage[]" class="form-input" value="<?= htmlspecialchars($role['min_percentage'] ?? '') ?>" >
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="form-label">CTC (in LPA)</label>
                                                        <input type="text" name="ctc[]" class="form-input" value="<?= htmlspecialchars($role['ctc'] ?? '') ?>">
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="form-label">Stipend (per month)</label>
                                                        <input type="text" name="stipend[]" class="form-input" value="<?= htmlspecialchars($role['stipend'] ?? '') ?>">
                                                    </div>
                                                    <!-- NEW: Work Timings -->
                                                    <div class="form-group">
                                                        <label class="form-label">Work Timings</label>
                                                        <input type="text" name="work_timings[]" class="form-input" value="<?= htmlspecialchars($role['work_timings'] ?? '') ?>" placeholder="e.g. 9:30 AM - 6:00 PM / Remote / Flexible">
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="form-label">Offer Type </label>
                                                        <select name="offer_types[]" class="form-select" >
                                                         <option value="">-- Select --</option>
                                                            <?php foreach ($offer_types as $type): ?>
                                                                <option value="<?= $type ?>" <?= ($role['offer_type'] ?? 'FTE') == $type ? 'selected' : '' ?>><?= $type ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="form-label">Job Sector </label>
                                                        <select name="sectors[]" class="form-select">
                                                            <option value="">-- Select --</option>
                                                            <?php foreach ($sectors as $sector): ?>
                                                                <option value="<?= $sector ?>" <?= ($role['sector'] ?? 'IT') == $sector ? 'selected' : '' ?>><?= $sector ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="form-label">Eligible Courses <span class="required-asterisk">*</span></label>
                                                        <div class="flex gap-2">
                                                            <input type="text" name="eligible_courses[]" class="form-input"  id="eligible-courses-<?= $index ?>" 
                                                                   value="<?= implode(', ', $role['eligible_courses']) ?>" required>
                                                            <button type="button" class="btn btn-secondary" onclick="openCourseModal(<?= $index ?>)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Form Fields Section -->
                                                    <!-- Form Fields Section -->


                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="flex justify-end mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Drive
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Course Selection Modal -->
        <div id="courseModal" class="modal-overlay" style="display: none;">
            <div class="modal" style="max-width: 800px;">
                <div class="modal-header">
                    <h3 class="modal-title">Select Eligible Courses</h3>
                    <button type="button" onclick="closeCourseModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
                </div>
                <div class="modal-content">
                    <!-- Search and bulk actions -->
                    <div class="form-group">
                        <input type="text" id="courseSearch" class="form-input" placeholder="Search courses..." onkeyup="filterCourses()">
                    </div>
                    <div class="flex justify-between items-center mb-4 mx-4 flex-wrap gap-2">
  <!-- Left side buttons -->
  <div class="flex gap-4 mb-2">
    <button type="button" class="btn btn-secondary btn-sm px-3 py-1" onclick="selectAllCourses('ug')">Select All UG</button>
    <button type="button" class="btn btn-secondary btn-sm px-3 py-1" onclick="selectAllCourses('pg')">Select All PG</button>
  </div>

  <!-- Right side buttons -->
  <div class="flex gap-4 mb-2">
    <button type="button" class="reset-button" onclick="resetCourseSelection()">Reset</button>
    <button type="button" class="btn btn-primary" onclick="saveCourseSelection()">Save Selection</button>
  </div>
</div>

                    <!-- Course tabs -->
                    <div class="course-tabs">
                        <div class="course-tab active" onclick="showCourseTab('ug')">UG Courses</div>
                        <div class="course-tab" onclick="showCourseTab('pg')">PG Courses</div>
                    </div>

                    <!-- UG Courses -->
                    <div id="ugCourses" class="course-content">
                        <?php foreach ($ug_courses_grouped as $school => $groups): ?>
                            <div class="course-group">
                                <div class="course-group-header">
    <span><?= htmlspecialchars($school) ?></span>
    <input type="checkbox" data-school="<?= htmlspecialchars($school) ?>" data-type="ug" onchange="toggleSchoolCourses(this)">
</div>
                                <div class="course-items">
                                    <?php foreach ($groups as $group_name => $courses): ?>
                                        <?php foreach ($courses as $course): ?>
                                            <div class="course-item" data-school="<?= htmlspecialchars($school) ?>" data-type="ug">
                                                <input type="checkbox" value="<?= htmlspecialchars($course) ?>" onchange="updateCourseSelection()">
                                                <span><?= htmlspecialchars($course) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- PG Courses -->
                    <div id="pgCourses" class="course-content" style="display: none;">
                        <?php foreach ($pg_courses_grouped as $school => $groups): ?>
                            <div class="course-group">
                                <div class="course-group-header">
    <span><?= htmlspecialchars($school) ?></span>
    <input type="checkbox" data-school="<?= htmlspecialchars($school) ?>" data-type="pg" onchange="toggleSchoolCourses(this)">
</div>
                                <div class="course-items">
                                    <?php foreach ($groups as $group_name => $courses): ?>
                                        <?php foreach ($courses as $course): ?>
                                            <div class="course-item" data-school="<?= htmlspecialchars($school) ?>" data-type="pg">
                                                <input type="checkbox" value="<?= htmlspecialchars($course) ?>" onchange="updateCourseSelection()">
                                                <span><?= htmlspecialchars($course) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div id="selectedCoursesCount" class="mt-4" style="color: #64748b; font-size: 0.875rem;">
                        Selected: 0 courses
                    </div>
                </div>
            </div>
        </div>

        <div id="formFieldModal" class="modal-overlay" style="display: none;">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
         <h3 class="modal-title">Add Form Field</h3>
            <button type="button" onclick="closeFormFieldModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <div class="modal-content">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Field Name <span class="required-asterisk">*</span></label>
                    <input type="text" id="fieldName" class="form-input" required>
                </div>
               
      <div class="form-group">
    <label class="form-label">Options (comma separated)</label>
    <input type="text" id="fieldValue" class="form-input" placeholder="Option 1, Option 2, Option 3">
</div>


                <div class="form-group">
                    <label>
                        <input type="checkbox" id="fieldMandatory"> Mandatory Field
                    </label>
                    <small class="text-gray-500">Applies only to this field</small>
                </div>
            </div>
        </div>
        <div class="modal-footer">

           <button type="button" class="btn btn-primary" onclick="addDriveFormField()">Add Field</button>

        </div>
    </div>
</div>
        <div id="formFieldsModal" class="modal-overlay" style="display: none;">
    <div class="modal" style="width: 600px; max-width: 90%;">
        <div class="modal-header">
            <h3 class="modal-title">Select Form Fields</h3>
            <button type="button" onclick="closeFormFieldsModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <div class="modal-content">
            <div style="margin-bottom: 1rem;">
                <input type="text" id="searchFields" class="form-input" placeholder="Search fields..." style="width: 100%;" onkeyup="filterFormFields()">
            </div>
            
            <div style="margin-bottom: 1rem; font-weight: 600;">Personal Details</div>
                </div>
    </div>
</div>
            
           

        <!-- Extra Details Modal -->
         
        <div id="extraDetailsModal" class="modal-overlay" style="display: none;">
           <div class="modal" style="max-width: 600px; display: flex; flex-direction: column; max-height: 90vh;">
                <div class="modal-header">
                    <h3 class="modal-title">Extra Drive Details</h3>
                    <button type="button" onclick="closeExtraModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="resetExtraDetails()">Reset</button>
                    <button type="button" class="btn btn-primary" onclick="saveExtraDetails()">Save Details</button>
                </div>
    <div class="modal-content" style="overflow-y: auto; padding: 1rem; flex: 1;">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">CTC Details</label>
                            <input type="text" id="ctcDetails" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Mode of Work:</label>
                            <input type="text" id="workMode" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Office Address:</label>
                            <input type="text" id="officeAddress" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Interview Details:</label>
                            <input type="text" id="interviewDetails" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Eligibility Notes / Restrictions:</label>
                            <input type="text" id="eligibilityNote" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Application Deadline</label>
                            <input type="text" id="deadlineNote" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">No. of Vacancies</label>
                            <input type="text" id="vacancies" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Location</label>
                            <input type="text" id="location" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Duration</label>
                            <input type="text" id="duration" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Process Details</label>
                            <input type="text" id="stipend" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Post Internship Opportunity</label>
                            <input type="text" id="postInternship" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Timings</label>
                            <input type="text" id="timings" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">WhatsApp Group Link</label>
                            <input type="text" id="whatsapp" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Additional Info</label>
                            <textarea id="additionalInfo" class="form-textarea" rows="3"></textarea>
                        </div>
                    </div>

                    <!-- Custom fields -->
                    <div style="border-top: 1px solid #e2e8f0; padding-top: 1rem; margin-top: 1rem;">
                        <h4 style="margin-bottom: 1rem;">Custom Fields</h4>
                        <div id="customFieldsContainer"></div>
                        <div class="flex gap-2">
                            <input type="text" id="customFieldKey" class="form-input" placeholder="Field name">
                            <input type="text" id="customFieldValue" class="form-input" placeholder="Value">
                            <button type="button" class="btn btn-secondary" onclick="addCustomField()">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>

        <script>
        function validateDriveForm() {
    // Existing validation code...
    
    // Check Flatpickr dates
    const openDate = document.getElementById('open_date').value.trim();
    const closeDate = document.getElementById('close_date').value.trim();

    if (!openDate) {
        alert("Please enter the Form Open Date & Time.");
        document.getElementById('open_date').focus();
        return false;
    }

    if (!closeDate) {
        alert("Please enter the Form Close Date & Time.");
        document.getElementById('close_date').focus();
        return false;
    }

    if (new Date(closeDate) <= new Date(openDate)) {
        alert("Form Close Date must be after Open Date.");
        document.getElementById('close_date').focus();
        return false;
    }

    return true;
}
            // Global variables
            let selectedCourses = [];
            let currentRoleIndex = -1;
            let currentFormFieldRoleIndex = -1;
            let tempExtraDetails = {};
            let roleCount = <?= count($roles) ?>;
            let formFieldCounters = {};
            
// Extra details field mapping configuration
const extraDetailsFields = {
    // Form ID : Database field name
    'ctcDetails': 'ctc_details',
    'workMode': 'work_mode',
    'officeAddress': 'office_address',
    'interviewDetails': 'interview_details',
    'eligibilityNote': 'eligibility_note',
    'deadlineNote': 'deadline_note',
    'vacancies': 'vacancies',
    'location': 'location',
    'duration': 'duration',
    'stipend': 'stipend',
    'postInternship': 'post_internship',
    'timings': 'timings',
    'whatsapp': 'whatsapp_link',
    'additionalInfo': 'additional_info'
};

// Standard field names (for reference)
const standardFieldNames = Object.values(extraDetailsFields);

            // Initialize with existing extra details
            document.addEventListener('DOMContentLoaded', function() {
                try {
                    tempExtraDetails = JSON.parse(document.getElementById('extraDetailsInput').value || '{}');
                    updateExtraDetailsDisplay();
                    
                    // Initialize form field counters for each role
                    document.querySelectorAll('.role-block').forEach(roleBlock => {
                        const roleId = roleBlock.id.split('-')[1];
                        const fieldsCount = roleBlock.querySelectorAll('.form-field-item').length;
                        formFieldCounters[roleId] = fieldsCount;
                    });
                } catch (e) {
                    console.error("Error parsing extra details:", e);
                }
            });

            // Role management functions
            function addRole() {
                const container = document.getElementById('rolesContainer');
                if (container.innerHTML.includes('No roles added yet')) {
                    container.innerHTML = '';
                }
                
                const roleId = roleCount++;
                formFieldCounters[roleId] = 0;
                
                const roleBlock = document.createElement('div');
                roleBlock.className = 'role-block';
                roleBlock.id = `role-${roleId}`;
                
                roleBlock.innerHTML = `
                    <div class="role-header" onclick="toggleRole(${roleId})">
                        <div>
                            <strong>New Role</strong>
                            <span class="badge badge-primary ml-2"></span>
                            <span class="badge badge-secondary ml-1"></span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-danger btn-sm" onclick="removeRole(${roleId}, event)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="role-content expanded">
                        <div class="form-grid">
                            <input type="hidden" name="role_id[]" value="0">
                            <div class="form-group">
                                <label class="form-label">Role Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="role_name[]" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Minimum Percentage </label>
                                <input type="text" name="min_percentage[]" class="form-input" >
                            </div>
                            <div class="form-group">
                                <label class="form-label">CTC (in LPA) </label>
                                <input type="text" name="ctc[]" class="form-input" >
                            </div>
                            <div class="form-group">
                                <label class="form-label">Stipend (per month) </label>
                                <input type="text" name="stipend[]" class="form-input" >
                            </div>
                            <div class="form-group">
                                <label class="form-label">Work Timings</label>
                                <input type="text" name="work_timings[]" class="form-input" value="" placeholder="e.g. 9:30 AM - 6:00 PM / Remote / Flexible">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Offer Type </label>
                                <select name="offer_types[]" class="form-select" >
                                 <option value="">-- Select --</option>
                                    <?php foreach ($offer_types as $type): ?>
                                        <option value="<?= $type ?>"><?= $type ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Sector </label>
                                <select name="sectors[]" class="form-select" >
                                    <option value="">-- Select --</option>
                                    <?php foreach ($sectors as $sector): ?>
                                        <option value="<?= $sector ?>"><?= $sector ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Eligible Courses <span class="required-asterisk">*</span></label>
                                <div class="flex gap-2">
                                    <input type="text" name="eligible_courses[]" class="form-input" id="eligible-courses-${roleId}" value="" required>
                                    <button type="button" class="btn btn-secondary" onclick="openCourseModal(${roleId})">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Form Fields Section -->
                            <div class="form-fields-container">
                                
                            </div>
                        </div>
                    </div>
                `;
                
                container.appendChild(roleBlock);
            }
            function toggleAppFormFields() {
    const section = document.getElementById('applicationFormFieldsSection');
    const icon = document.getElementById('formFieldToggleIcon');
    
    // Toggle visibility class
    section.classList.toggle('show');
    
    // Rotate icon
    icon.classList.toggle('rotate');
}


            function removeRole(roleId, event) {
                if (event) event.stopPropagation();
                const roleElement = document.getElementById(`role-${roleId}`);
                if (roleElement) {
                    roleElement.remove();
                    delete formFieldCounters[roleId];
                }
                const container = document.getElementById('rolesContainer');
                if (container.children.length === 0) {
                    container.innerHTML = '<div class="text-center" style="padding: 2rem; color: #64748b;">No roles added yet. Click "Add Role" to get started.</div>';
                }
            }

            function toggleRole(roleId) {
                const content = document.querySelector(`#role-${roleId} .role-content`);
                if (content) {
                    content.classList.toggle('expanded');
                }
            }

            // Course selection functions
            function openCourseModal(roleIndex) {{
                currentRoleIndex = roleIndex;
                const coursesInput = document.getElementById(`eligible-courses-${roleIndex}`);
                if (coursesInput && coursesInput.value) {
                    selectedCourses = coursesInput.value.split(',').map(c => c.trim());
                } else {
                    selectedCourses = [];
                }
                
                document.getElementById('courseModal').style.display = 'flex';
                document.getElementById('courseSearch').value = '';
                
                // Check the selected courses
                document.querySelectorAll('#courseModal input[type="checkbox"]').forEach(checkbox => {
                    checkbox.checked = selectedCourses.includes(checkbox.value);
                });
                
                  updateSchoolCheckboxes();
    }
    
    updateCourseSelection();
}
            function updateSchoolCheckboxes() {
    // For each school checkbox, check if all its courses are selected
    document.querySelectorAll('.course-group-header input[type="checkbox"]').forEach(schoolCheckbox => {
        const school = schoolCheckbox.getAttribute('data-school');
        const type = schoolCheckbox.getAttribute('data-type');
        
        const container = document.getElementById(`${type}Courses`);
        const courseCheckboxes = container.querySelectorAll(`.course-item[data-school="${school}"] input[type="checkbox"]`);
        const allChecked = Array.from(courseCheckboxes).every(cb => cb.checked);
        
        schoolCheckbox.checked = allChecked;
    });
}

            function closeCourseModal() {
                document.getElementById('courseModal').style.display = 'none';
            }

            function showCourseTab(tab) {
                document.querySelectorAll('.course-tab').forEach(t => t.classList.remove('active'));
                document.querySelector(`.course-tab[onclick="showCourseTab('${tab}')"]`).classList.add('active');
                document.getElementById('ugCourses').style.display = tab === 'ug' ? 'block' : 'none';
                document.getElementById('pgCourses').style.display = tab === 'pg' ? 'block' : 'none';
            }

            function toggleSchoolCourses(checkbox) {
    const school = checkbox.getAttribute('data-school');
    const type = checkbox.getAttribute('data-type');
    const container = document.getElementById(`${type}Courses`);
    
    container.querySelectorAll(`.course-item[data-school="${school}"] input[type="checkbox"]`).forEach(item => {
        item.checked = checkbox.checked;
    });
    
    updateCourseSelection();
}

            function updateCourseSelection() {
    selectedCourses = [];
    document.querySelectorAll('#courseModal input[type="checkbox"]:checked').forEach(checkbox => {
        if (checkbox.value && !checkbox.hasAttribute('data-school')) { // Skip school checkboxes
            selectedCourses.push(checkbox.value);
        }
    });
    
    document.getElementById('selectedCoursesCount').textContent = `Selected: ${selectedCourses.length} courses`;
    
    // Update school checkboxes based on current selection
    updateSchoolCheckboxes();
}

            function selectAllCourses(type) {
                const container = document.getElementById(`${type}Courses`);
                const checkboxes = container.querySelectorAll('input[type="checkbox"]');
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                
                checkboxes.forEach(checkbox => {
                    checkbox.checked = !allChecked;
                });
                updateCourseSelection();
            }

            function resetCourseSelection() {
    // Uncheck all course checkboxes
    document.querySelectorAll('#courseModal input[type="checkbox"]').forEach(checkbox => {
        checkbox.checked = false;
    });
    // Clear the search bar
    document.getElementById('courseSearch').value = '';
    // Show all course items
    document.querySelectorAll('.course-item').forEach(item => {
        item.style.display = 'flex';
    });
    updateCourseSelection();
}

            function saveCourseSelection() {
                if (currentRoleIndex >= 0) {
                    const coursesInput = document.getElementById(`eligible-courses-${currentRoleIndex}`);
                    if (coursesInput) {
                        coursesInput.value = selectedCourses.join(', ');
                    }
                }
                closeCourseModal();
            }

            function filterCourses() {
                const searchTerm = document.getElementById('courseSearch').value.toLowerCase();
                document.querySelectorAll('.course-item').forEach(item => {
                    const text = item.textContent.toLowerCase();
                    item.style.display = text.includes(searchTerm) ? 'flex' : 'none';
                });
            }

            // Form Field functions
           function openFormFieldModal(roleIndex) {
    currentFormFieldRoleIndex = roleIndex;
    document.getElementById('formFieldModal').style.display = 'flex';
    document.getElementById('fieldName').value = '';
    document.getElementById('fieldValue').value = '';
    document.getElementById('fieldMandatory').checked = false;
}


            function closeFormFieldModal() {
                document.getElementById('formFieldModal').style.display = 'none';
            }

           

    // Add form field with proper validation
 function addFormField() {
    const name = document.getElementById('fieldName').value.trim();
    const type = document.getElementById('fieldType').value;
    const options = document.getElementById('fieldOptions').value.trim();
    const mandatory = document.getElementById('fieldMandatory').checked ? 1 : 0;
    
    // Validate inputs
    if (!name) {
        alert('Field name is required');
        return;
    }
    
    if (['select', 'radio', 'checkbox'].includes(type) && !options) {
        alert('Options are required for this field type');
        return;
    }
    
    // Create field element
    const fieldId = Date.now(); // Unique ID for the field
    const fieldsContainer = document.getElementById(`form-fields-${currentFormFieldRoleIndex}`);
    
    const fieldDiv = document.createElement('div');
    fieldDiv.className = 'form-field-item';
    fieldDiv.style.marginBottom = '12px';

    // Generate preview based on field type
    let previewElement = '';
    switch(type) {
        case 'text':
            previewElement = `<input type="text" class="form-input" placeholder="${name}" disabled>`;
            break;
        case 'number':
            previewElement = `<input type="number" class="form-input" placeholder="${name}" disabled>`;
            break;
        case 'email':
            previewElement = `<input type="email" class="form-input" placeholder="${name}" disabled>`;
            break;
        case 'textarea':
            previewElement = `<textarea class="form-textarea" placeholder="${name}" disabled></textarea>`;
            break;
        case 'select':
            const selectOptions = options.split(',').map(opt => opt.trim());
            previewElement = `<select class="form-select" disabled>
                <option value="">-- Select --</option>
                ${selectOptions.map(opt => `<option>${opt}</option>`).join('')}
            </select>`;
            break;
        case 'radio':
            const radioOptions = options.split(',').map(opt => opt.trim());
            previewElement = radioOptions.map(opt => `
                <div class="flex items-center gap-2">
                    <input type="radio" name="preview_radio_${fieldId}" disabled>
                    <span>${opt}</span>
                </div>
            `).join('');
            break;
        case 'checkbox':
            const checkboxOptions = options.split(',').map(opt => opt.trim());
            previewElement = checkboxOptions.map(opt => `
                <div class="flex items-center gap-2">
                    <input type="checkbox" disabled>
                    <span>${opt}</span>
                </div>
            `).join('');
            break;
        case 'file':
            previewElement = `<input type="file" disabled>`;
            break;
        default:
            previewElement = `<input type="text" class="form-input" placeholder="${name}" disabled>`;
    }

    fieldDiv.innerHTML = `
        <div class="form-field-item-header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <span style="flex: 1; font-weight: 500; font-size: 14px;">${name} <small>(${type})</small></span>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeFormField(this)">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="preview-container mt-2">
            ${previewElement}
        </div>
        <input type="hidden" name="form_fields[${currentFormFieldRoleIndex}][${fieldId}][name]" value="${name}">
        <input type="hidden" name="form_fields[${currentFormFieldRoleIndex}][${fieldId}][type]" value="${type}">
        <input type="hidden" name="form_fields[${currentFormFieldRoleIndex}][${fieldId}][options]" value="${options}">
        <input type="hidden" name="form_fields[${currentFormFieldRoleIndex}][${fieldId}][placeholder]" value="">
        <input type="hidden" name="form_fields[${currentFormFieldRoleIndex}][${fieldId}][mandatory]" value="${mandatory ? '1' : '0'}">
    `;
    
    fieldsContainer.appendChild(fieldDiv);
    closeFormFieldModal();
}

    // Remove form field
    function removeFormField(button) {
        const fieldItem = button.closest('.form-field-item');
        if (fieldItem) {
            fieldItem.remove();
        }
    }

            // Show/hide options field based on field type
            document.getElementById('fieldType').addEventListener('change', function() {
                const type = this.value;
                const optionsContainer = document.getElementById('optionsContainer');
                optionsContainer.style.display = (type === 'select' || type === 'radio' || type === 'checkbox') ? 'block' : 'none';
            });

            // Extra details functions
      function openExtraModal() {
    document.getElementById('extraDetailsModal').style.display = 'flex';
    
    // Parse the current extra details
    const extraDetails = JSON.parse(document.getElementById('extraDetailsInput').value || '{}');
    
    // Map each field from extraDetails to the modal inputs
    document.getElementById('ctcDetails').value = extraDetails.ctcDetails || '';
    document.getElementById('workMode').value = extraDetails.workMode || '';
    document.getElementById('officeAddress').value = extraDetails.officeAddress || '';
    document.getElementById('interviewDetails').value = extraDetails.interviewDetails || '';
    document.getElementById('eligibilityNote').value = extraDetails.eligibilityNote || '';
    document.getElementById('deadlineNote').value = extraDetails.deadlineNote || '';
    document.getElementById('vacancies').value = extraDetails.vacancies || '';
    document.getElementById('location').value = extraDetails.location || '';
    document.getElementById('duration').value = extraDetails.duration || '';
    document.getElementById('stipend').value = extraDetails.stipend || '';
    document.getElementById('postInternship').value = extraDetails.postInternship || '';
    document.getElementById('timings').value = extraDetails.timings || '';
    document.getElementById('whatsapp').value = extraDetails.whatsapp || '';
    document.getElementById('additionalInfo').value = extraDetails.additionalInfo || '';

     // Handle custom fields (any fields not in our standard mapping)
    const customFieldsContainer = document.getElementById('customFieldsContainer');
    customFieldsContainer.innerHTML = '';
    
    // Standard field names we've already handled
    const standardFields = [
        'ctcDetails', 'workMode', 'officeAddress', 'interviewDetails', 
        'eligibilityNote', 'deadlineNote', 'vacancies', 'location', 
        'duration', 'stipend', 'postInternship', 'timings', 
        'whatsapp', 'additionalInfo'
    ];
    
    // Find and add any custom fields
    Object.keys(extraDetails).forEach(key => {
        if (!standardFields.includes(key) && extraDetails[key]) {
            addCustomFieldToContainer(key, extraDetails[key]);
        }
    });
}

            function closeExtraModal() {
                document.getElementById('extraDetailsModal').style.display = 'none';
            }

            function addCustomField() {
                const keyInput = document.getElementById('customFieldKey');
                const valueInput = document.getElementById('customFieldValue');
                const key = keyInput.value.trim();
                const value = valueInput.value.trim();
                
                if (key && value) {
                    addCustomFieldToContainer(key, value);
                    keyInput.value = '';
                    valueInput.value = '';
                }
            }

            function addCustomFieldToContainer(key, value) {
                const container = document.getElementById('customFieldsContainer');
                const fieldDiv = document.createElement('div');
                fieldDiv.className = 'flex gap-2 mb-2';
                fieldDiv.innerHTML = `
                    <input type="text" value="${key}" class="form-input" style="flex: 1;" readonly>
                    <input type="text" value="${value}" class="form-input" style="flex: 1;">
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
                container.appendChild(fieldDiv);
            }

            function resetExtraDetails() {
                document.getElementById('extraDetailsModal').querySelectorAll('input, select, textarea').forEach(element => {
                    if (element.id !== 'customFieldKey' && element.id !== 'customFieldValue') {
                        element.value = '';
                    }
                });
                document.getElementById('customFieldsContainer').innerHTML = '';
            }
function saveExtraDetails() {
    const extraDetails = {};
    
    // Standard fields
    extraDetails.ctcDetails = document.getElementById('ctcDetails').value;
    extraDetails.workMode = document.getElementById('workMode').value;
    extraDetails.officeAddress = document.getElementById('officeAddress').value;
    extraDetails.interviewDetails = document.getElementById('interviewDetails').value;
    extraDetails.eligibilityNote = document.getElementById('eligibilityNote').value;
    extraDetails.deadlineNote = document.getElementById('deadlineNote').value;
    extraDetails.vacancies = document.getElementById('vacancies').value;
    extraDetails.location = document.getElementById('location').value;
    extraDetails.duration = document.getElementById('duration').value;
    extraDetails.stipend = document.getElementById('stipend').value;
    extraDetails.postInternship = document.getElementById('postInternship').value;
    extraDetails.timings = document.getElementById('timings').value;
    extraDetails.whatsapp = document.getElementById('whatsapp').value;
    extraDetails.additionalInfo = document.getElementById('additionalInfo').value;
    
    // Custom fields
    document.querySelectorAll('#customFieldsContainer > div').forEach(fieldDiv => {
        const key = fieldDiv.querySelector('input:first-child').value;
        const value = fieldDiv.querySelector('input:nth-child(2)').value;
        if (key && value) {
            extraDetails[key] = value;
        }
    });
    
    // Save to hidden input
    document.getElementById('extraDetailsInput').value = JSON.stringify(extraDetails);
    updateExtraDetailsDisplay();
    closeExtraModal();
}

            function updateExtraDetailsDisplay() {
                const display = document.getElementById('extraDetailsDisplay');
                const count = Object.keys(tempExtraDetails).length;
                
            }
            let currentFormFieldsRoleIndex = -1;
    
    function openFormFieldsModal(roleIndex) {
        currentFormFieldsRoleIndex = roleIndex;
        document.getElementById('formFieldsModal').style.display = 'flex';
        
        // Clear search
        document.getElementById('searchFields').value = '';
        filterFormFields();
        
        // Check already selected fields
        const fieldsContainer = document.getElementById(`form-fields-${roleIndex}`);
        const selectedFields = [];
        
        fieldsContainer.querySelectorAll('input[name^="form_fields"]').forEach(input => {
            if (input.name.includes('[name]')) {
                selectedFields.push(input.value);
            }
        });
        
        document.querySelectorAll('#formFieldsList input[type="checkbox"]').forEach(checkbox => {
            checkbox.checked = selectedFields.includes(checkbox.value);
        });
    }
    
    function closeFormFieldsModal() {
        document.getElementById('formFieldsModal').style.display = 'none';
    }
    
    function filterFormFields() {
        const searchTerm = document.getElementById('searchFields').value.toLowerCase();
        document.querySelectorAll('#formFieldsList .form-field-item').forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(searchTerm) ? 'flex' : 'none';
        });
    }
    
    function saveSelectedFormFields() {
        if (currentFormFieldsRoleIndex >= 0) {
            const fieldsContainer = document.getElementById(`form-fields-${currentFormFieldsRoleIndex}`);
            
            // Get current field count
            let fieldCount = fieldsContainer.querySelectorAll('.form-field-item').length;
            
            // Add selected fields
            document.querySelectorAll('#formFieldsList input[type="checkbox"]:checked').forEach(checkbox => {
                const fieldName = checkbox.value;
                const fieldId = fieldCount++;
                
                // Determine field type based on field name
                let fieldType = 'text';
                const fieldNameLower = fieldName.toLowerCase();
                
                if (fieldNameLower.includes('email')) {
                    fieldType = 'email';
                } else if (fieldNameLower.includes('phone') || fieldNameLower.includes('mobile')) {
                    fieldType = 'tel';
                } else if (fieldNameLower.includes('percentage') || fieldNameLower.includes('cgpa')) {
                    fieldType = 'number';
                } else if (fieldNameLower.includes('declaration') || fieldNameLower.includes('agree')) {
                    fieldType = 'checkbox';
                }
                
                const fieldDiv = document.createElement('div');
                fieldDiv.className = 'form-field-item';
                fieldDiv.innerHTML = `
                    <div class="form-field-item-header">
                        <span>${fieldName}</span>
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeFormField(this)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="form-field-item-content">
                        <input type="hidden" name="form_fields[${currentFormFieldsRoleIndex}][${fieldId}][name]" value="${fieldName}">
                        <input type="hidden" name="form_fields[${currentFormFieldsRoleIndex}][${fieldId}][type]" value="${fieldType}">
                        <input type="hidden" name="form_fields[${currentFormFieldsRoleIndex}][${fieldId}][options]" value="">
                        <input type="hidden" name="form_fields[${currentFormFieldsRoleIndex}][${fieldId}][placeholder]" value="">
                        <input type="hidden" name="form_fields[${currentFormFieldsRoleIndex}][${fieldId}][mandatory]" value="0">
                    </div>
                `;
                
                fieldsContainer.appendChild(fieldDiv);
            });
        }
        closeFormFieldsModal();
    }
    
    function removeFormField(button) {
        const fieldItem = button.closest('.form-field-item');
        if (fieldItem) {
            fieldItem.remove();
            // After removing, we should re-index the fields to maintain proper array structure
            reindexFormFields(currentFormFieldsRoleIndex);
        }
    }
    
    function reindexFormFields(roleIndex) {
        const fieldsContainer = document.getElementById(`form-fields-${roleIndex}`);
        let fieldIndex = 0;
        
        fieldsContainer.querySelectorAll('.form-field-item').forEach(item => {
            // Update all input names with new index
            const inputs = item.querySelectorAll('input');
            inputs.forEach(input => {
                const name = input.name.replace(/\[\d+\]/, `[${fieldIndex}]`);
                input.name = name;
            });
            fieldIndex++;
        });
    }
    function removeJdFile(index, filePath) {
        // Remove the file from the display
        document.getElementById(`jdFile-${index}`).remove();
        
        // Add the file to a hidden field to track deletions
        const deletedFilesInput = document.createElement('input');
        deletedFilesInput.type = 'hidden';
        deletedFilesInput.name = 'deleted_jd_files[]';
        deletedFilesInput.value = decodeURIComponent(filePath);
        document.getElementById('currentJdFiles').appendChild(deletedFilesInput);
        
        // If no files left, hide the container
        if (document.querySelectorAll('#currentJdFiles div[id^="jdFile-"]').length === 0) {
            document.querySelector('#currentJdFiles').parentElement.style.display = 'none';
        }
    }
    function validateDriveForm() {
    const openDate = document.getElementById('open_date').value.trim();
    const closeDate = document.getElementById('close_date').value.trim();

    if (!openDate) {
        alert("Please enter the Form Open Date & Time.");
        document.getElementById('open_date').focus();
        return false;
    }

    if (!closeDate) {
        alert("Please enter the Form Close Date & Time.");
        document.getElementById('close_date').focus();
        return false;
    }

    if (new Date(closeDate) <= new Date(openDate)) {
        alert("Form Close Date must be after Open Date.");
        document.getElementById('close_date').focus();
        return false;
    }

    return true;
}

// Attach the validation to the form submission
document.getElementById('driveForm').addEventListener('submit', function(e) {
    if (!validateDriveForm()) {
        e.preventDefault();
    }
});
        </script>
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize flatpickr with existing values
    const openDatePicker = flatpickr("#open_date", {
        enableTime: true,
        dateFormat: "d-m-Y H:i",
        defaultDate: document.getElementById('open_date').value || null,
        onChange: function(selectedDates) {
            if (selectedDates.length > 0) {
                closeDatePicker.set('minDate', selectedDates[0]);
            }
        },
        onReady: function(selectedDates, dateStr, instance) {
            let closeBtn = document.createElement("span");
            closeBtn.innerHTML = "&times;";
            closeBtn.className = "flatpickr-close";
            closeBtn.onclick = () => instance.close();
            instance.calendarContainer.appendChild(closeBtn);
            
            // Set the initial value if it exists
            if (document.getElementById('open_date').value) {
                instance.setDate(document.getElementById('open_date').value);
            }
        }
    });

    const closeDatePicker = flatpickr("#close_date", {
        enableTime: true,
        dateFormat: "d-m-Y H:i",
        defaultDate: document.getElementById('close_date').value || null,
        onReady: function(selectedDates, dateStr, instance) {
            let closeBtn = document.createElement("span");
            closeBtn.innerHTML = "&times;";
            closeBtn.className = "flatpickr-close";
            closeBtn.onclick = () => instance.close();
            instance.calendarContainer.appendChild(closeBtn);
            
            // Set the initial value if it exists
            if (document.getElementById('close_date').value) {
                instance.setDate(document.getElementById('close_date').value);
            }
        }
    });

    // Set min date for close date based on open date
    document.getElementById('open_date').addEventListener('change', function() {
        const openDate = this.value;
        if (openDate) {
            closeDatePicker.set('minDate', openDate);
        }
    });
});
















// Open modal
function openDriveFormFieldModal() {
    document.getElementById('formFieldModal').style.display = 'flex';
}

// Close modal
function closeDriveFormFieldModal() {
    document.getElementById('formFieldModal').style.display = 'none';
    document.getElementById('fieldName').value = '';
    document.getElementById('fieldValue').value = '';
    document.getElementById('fieldMandatory').checked = false;
}



// Add field
// Add field
function addDriveFormField() {
    const name = document.getElementById('fieldName').value.trim();
    const value = document.getElementById('fieldValue').value.trim();
    const mandatory = document.getElementById('fieldMandatory').checked ? 1 : 0;

    if (!name) { 
        alert('Field Name is required'); 
        return; 
    }

    const container = document.getElementById('driveFormFieldsContainer');
    const index = container.children.length;

    let fieldHTML = '';

    if (!value) {
        // If Field Value is blank → render as textbox
        fieldHTML = `<input type="text" name="drive_form_fields[${index}][value]" placeholder="Enter ${name}">`;
    } else {
        // Split value by commas
        const optionsArray = value.split(',').map(opt => opt.trim()).filter(opt => opt);

        if (optionsArray.length === 1) {
            // Single value → Checkbox
            fieldHTML = `<label><input type="checkbox" name="drive_form_fields[${index}][value][]" value="${optionsArray[0]}"> ${optionsArray[0]}</label>`;
        } else if (optionsArray.length === 2) {
            // 2 options → Dropdown
            fieldHTML = `<select name="drive_form_fields[${index}][value]"><option value="">--Select--</option>`;
            optionsArray.forEach(opt => {
                fieldHTML += `<option value="${opt}">${opt}</option>`;
            });
            fieldHTML += `</select>`;
        } else {
            // 3 or more → Multi-checkbox
            optionsArray.forEach(opt => {
                fieldHTML += `<label><input type="checkbox" name="drive_form_fields[${index}][value][]" value="${opt}"> ${opt}</label><br>`;
            });
        }
    }

    const div = document.createElement('div');
    div.className = 'form-field-item';
    div.style.marginBottom = '0.5rem';
    div.innerHTML = `
        <strong>${name}</strong><br>
        <div style="margin-left:10px;">${fieldHTML}</div>
        <div style="display:inline-block; margin-top:5px;">
            <button type="button" class="btn btn-danger btn-sm" onclick="removeDriveFormFieldItem(this)">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <input type="hidden" name="drive_form_fields[${index}][name]" value="${name}">
        <input type="hidden" name="drive_form_fields[${index}][mandatory]" value="${mandatory}">
        <input type="hidden" name="drive_form_fields[${index}][options]" value="${value}">
    `;

    container.appendChild(div);
    closeDriveFormFieldModal();
}

// Remove field
function removeDriveFormFieldItem(button) {
    if (confirm('Are you sure you want to delete this field?')) {
        button.closest('.form-field-item').remove();
    }
}


</script>

    </div>
</body>
</html>