<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Set timezone (adjust to your timezone)
date_default_timezone_set('Asia/Kolkata');

include("config.php");
include("header.php");
include("course_groups_dynamic.php");

// Store filters in session when form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter_submit'])) {
    $_SESSION['dashboard_filters'] = [
        'company_name' => $_POST['company_name'] ?? '',
        'status' => $_POST['status'] ?? '',
        'from_date' => $_POST['from_date'] ?? '',
        'to_date' => $_POST['to_date'] ?? ''
    ];
}

// Clear filters if requested
if (isset($_GET['clear_filters'])) {
    unset($_SESSION['dashboard_filters']);
}

// Build WHERE clause based on session filters
$whereClause = "1=1";
$filterParams = [];
$types = '';

// Add academic year filter (from header.php year selector)
if (isset($_SESSION['selected_academic_year'])) {
    $whereClause .= " AND academic_year = ?";
    $filterParams[] = $_SESSION['selected_academic_year'];
    $types .= 's';
}

if (isset($_SESSION['dashboard_filters'])) {
    $filters = $_SESSION['dashboard_filters'];
    
    // Company name filter
    if (!empty($filters['company_name'])) {
        $whereClause .= " AND company_name LIKE ?";
        $filterParams[] = '%' . $filters['company_name'] . '%';
        $types .= 's';
    }
    
    // Status filter (computed)
    if (!empty($filters['status'])) {
        $now = date('Y-m-d H:i:s');
        $status = $filters['status'];
        
        if ($status === 'Finished') {
            $whereClause .= " AND close_date < ?";
            $filterParams[] = $now;
            $types .= 's';
        } 
        elseif ($status === 'Current') {
            $whereClause .= " AND open_date <= ? AND close_date >= ?";
            $filterParams[] = $now;
            $filterParams[] = $now;
            $types .= 'ss';
        } 
        elseif ($status === 'Upcoming') {
            $whereClause .= " AND open_date > ?";
            $filterParams[] = $now;
            $types .= 's';
        }
    }
    
    // Date range filters
    if (!empty($filters['from_date'])) {
        $whereClause .= " AND open_date >= ?";
        $filterParams[] = $filters['from_date'];
        $types .= 's';
    }
    
    if (!empty($filters['to_date'])) {
        $whereClause .= " AND close_date <= ?";
        $filterParams[] = $filters['to_date'];
        $types .= 's';
    }
}

// Use the filtered query
$drives_query = "SELECT * FROM drives WHERE $whereClause ORDER BY open_date DESC";

if (!empty($filterParams)) {
    $stmt = $conn->prepare($drives_query);
    $stmt->bind_param($types, ...$filterParams);
    $stmt->execute();
    $drives = $stmt->get_result();
} else {
    $drives = $conn->query($drives_query);
}

// Dynamically flatten UG courses
$allUG = [];
foreach ($ug_courses_grouped as $group) {
    foreach ($group as $programs) {
        $allUG = array_merge($allUG, $programs);
    }
}

// Dynamically flatten PG courses
$allPG = [];
foreach ($pg_courses_grouped as $group) {
    foreach ($group as $programs) {
        $allPG = array_merge($allPG, $programs);
    }
}

function simplifyCourses($coursesRaw) {
    global $allUG, $allPG;

    if (empty($coursesRaw)) {
        return "Not Provided";
    }

    $selected = json_decode($coursesRaw, true);
    if (!is_array($selected)) $selected = [];

    // Clean invalid values like "on"
    $selected = array_filter($selected, function ($v) {
        return $v !== "on" && trim($v) !== "";
    });

    if (empty($selected)) {
        return "Not Provided";
    }

    // Convert all to lowercase for accurate comparison
    $selected_lower = array_map('strtolower', $selected);
    $allUG_lower = array_map('strtolower', $allUG);
    $allPG_lower = array_map('strtolower', $allPG);

    $display = [];

    $ugMatch = array_intersect($selected_lower, $allUG_lower);
    $pgMatch = array_intersect($selected_lower, $allPG_lower);

    // If all UG matched
    if (count($ugMatch) === count($allUG)) {
        $display[] = "All UG Courses";
    } else {
        // Show actual UG course names (preserve case from original $allUG)
        foreach ($allUG as $ug) {
            if (in_array(strtolower($ug), $selected_lower)) {
                $display[] = $ug;
            }
        }
    }

    // If all PG matched
    if (count($pgMatch) === count($allPG)) {
        $display[] = "All PG Courses";
    } else {
        foreach ($allPG as $pg) {
            if (in_array(strtolower($pg), $selected_lower)) {
                $display[] = $pg;
            }
        }
    }

    // Add any remaining unknowns (not in UG or PG)
    foreach ($selected as $item) {
        $lower = strtolower($item);
        if (!in_array($lower, $allUG_lower) && !in_array($lower, $allPG_lower)) {
            $display[] = $item;
        }
    }

    return empty($display) ? "Not Provided" : implode(', ', $display);
}

// Initialize empty arrays for each status
$cards = ["Upcoming" => [], "Current" => [], "Finished" => []];

// Get current datetime for comparison
$now = new DateTime();
// 🔥 Auto-delete drives that have no roles
$conn->query("
    DELETE d 
    FROM drives d
    LEFT JOIN drive_roles dr ON d.drive_id = dr.drive_id
    WHERE dr.role_id IS NULL
");

// Fetch all drives and classify them
$drives_result = $conn->query("SELECT * FROM drives ORDER BY open_date DESC");
while ($drive = $drives_result->fetch_assoc()) {
    try {
        $open = new DateTime($drive['open_date']);
        $close = new DateTime($drive['close_date']);
        
        if ($close < $now) {
            $status = "Finished";
        } elseif ($open <= $now && $close >= $now) {
            $status = "Current";
        } else {
            $status = "Upcoming";
        }
        
        $cards[$status][] = $drive;
    } catch (Exception $e) {
        error_log("Error parsing dates for drive {$drive['drive_id']}: ".$e->getMessage());
        continue;
    }
}
// Sync placed students once per page load
include_once __DIR__ . '/sync_placed_students.php';
$sync_result = sync_placed_students($conn);
if (!empty($sync_result['errors'])) {
    error_log("Sync errors: " . implode(", ", $sync_result['errors']));
}

// Extract graduation year from academic year (e.g., "2025-2026" → 2026)
$graduation_year = null;
if (isset($_SESSION['selected_academic_year'])) {
    $parts = explode('-', $_SESSION['selected_academic_year']);
    $graduation_year = isset($parts[1]) ? intval($parts[1]) : null;
}

// Top Dashboard Boxes - Show ALL students regardless of year (consistent with statistics page)
$total_students = $conn->query("SELECT COUNT(*) AS count FROM students")->fetch_assoc()['count'];
$total_companies = $conn->query("SELECT COUNT(DISTINCT company_name) AS count FROM drives")->fetch_assoc()['count'];
$placed_students = $conn->query("SELECT COUNT(DISTINCT place_id) AS count FROM placed_students")->fetch_assoc()['count'];

function getCompanyProgress($status) {
    $status = strtolower($status);
    switch ($status) {
        case 'yet to start': return 10;
        case 'on hold': return 30;
        case 'ongoing': return 60;
        case 'process complete': return 100;
        case 'no applicants': return 0;
        case 'called off': return 0;
        default: return 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body class="bg-gray-100">
<h2 class="headings">Dashboard</h2>
<p>List of all the current, upcoming, finished placement drives.</p>
<div class="p-4">

  <!-- Top Three Boxes -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <!-- Total Students -->
    <div class="bg-[#650000] text-white rounded-md p-4 flex justify-between items-center">
      <div>
        <div class="text-sm">Total Students</div>
        <div class="text-2xl font-bold"><?= $total_students ?></div>
        <div class="text-green-300 text-xs mt-1">Up from yesterday</div>
      </div>
      <div class="text-4xl opacity-80 text-white">
        <i class="fas fa-user-graduate"></i>
      </div>
    </div>

    <!-- Total Companies -->
    <div class="bg-[#650000] text-white rounded-md p-4 flex justify-between items-center">
      <div>
        <div class="text-sm">Total Companies</div>
        <div class="text-2xl font-bold"><?= $total_companies ?></div>
        <div class="text-green-300 text-xs mt-1">Up from yesterday</div>
      </div>
      <div class="text-4xl opacity-80 text-white">
        <i class="bi bi-buildings"></i>
      </div>
    </div>

    <!-- Placed Students -->
    <div class="bg-[#650000] text-white rounded-md p-4 flex justify-between items-center">
      <div>
        <div class="text-sm">Placed Students</div>
        <div class="text-2xl font-bold"><?= $placed_students ?></div>
        <div class="text-red-300 text-xs mt-1">Updated daily</div>
      </div>
      <div class="text-4xl opacity-80 text-white">
        <i class="bi bi-briefcase"></i></i>
      </div>
    </div>
  </div>

  <!-- Filter and Export Section -->
 <div style="display: flex; justify-content: flex-end; gap: 20px; margin-bottom: 15px; align-items: center; ">
    <div style="position: relative;">
      <input type="text" id="globalSearch" class="searchInput" placeholder="Search drives...">
      <button onclick="globalSearch()" class="
        search-filter-btn-container 
      ">       
      </button>
    </div>
        <div class="export-import-container">

    <button type="button" onclick="openDashboardFilter()" class="filter-button"
    >
      <i class="fas fa-filter"></i> Filters
    </button>
    <button type="button" id="resetBtn" class="reset-button" onclick="window.location.href = window.location.pathname;">
                <i class="fas fa-undo"></i> Reset
            </button>
    <button type="button" onclick="openExportPopup()" class="exportBtn">
  <i class="fas fa-file-export"></i> Export
</button></div>
  </div>

  <!-- Tabs -->
  <div class="flex gap-3 mb-6">
    <button id="tabBtn_Current" onclick="showTab('Current')" class="tab-button active">Current</button>
    <button id="tabBtn_Upcoming" onclick="showTab('Upcoming')" class="tab-button">Upcoming</button>
    <button id="tabBtn_Finished" onclick="showTab('Finished')" class="tab-button">Finished</button>
  </div>

  <?php foreach (['Current', 'Upcoming', 'Finished'] as $tab): ?>
    <div id="tab_<?= $tab ?>" class="tab-content <?= $tab !== 'Current' ? 'hidden' : '' ?>">
      <?php if (empty($cards[$tab])): ?>
        <p class="text-gray-500 mb-4">No <?= strtolower($tab) ?> drives available.</p>
      <?php else: ?>
        <div class="space-y-4">
           <?php foreach ($cards[$tab] as $d): ?>
            <?php
              $roles_data = [];
              $roles_result = $conn->query("SELECT * FROM drive_roles WHERE drive_id = {$d['drive_id']}");
              $total_ctc = [];
              
              while ($role = $roles_result->fetch_assoc()) {
                  $roles_data[] = $role;
                  $total_ctc = $role['ctc'];
              }

              $companyName = $d['company_name'];
              $drive_id = $d['drive_id'];
              
              // Get applicants count from applications table
              $applicants_count = 0;
              $app_count_result = $conn->query("SELECT COUNT(DISTINCT CONCAT(upid, '-', reg_no)) AS count FROM applications WHERE drive_id = $drive_id
");
              if ($app_count_result) {
                  $applicants_count = $app_count_result->fetch_assoc()['count'];
              }

              // Calculate overall progress based on all roles
              $progress = 0;
              $final_status = 'N/A';
              $hired_count = 0;
              $role_count = count($roles_data);
              
              if ($role_count > 0) {
                  $total_progress = 0;
                  $status_counts = [
                      'yet to start' => 0,
                      'on hold' => 0,
                      'ongoing' => 0,
                      'process complete' => 0,
                      'no applicants' => 0,
                      'called off' => 0
                  ];

                  $hired_count = 0;
                  $total_progress = 0;

                 foreach ($roles_data as $role) {
    if (!isset($role['role_id'])) continue;

    // Get final status
    $stmt = $conn->prepare("
        SELECT final_status 
        FROM drive_data 
        WHERE drive_id = ? 
        AND role_id = ?
    ");
    $stmt->bind_param("ii", $drive_id, $role['role_id']);
    $stmt->execute();
    $role_status = $stmt->get_result()->fetch_assoc();

    // Get hired count separately (filtered by drive_id and role_id for accuracy)
    $hired_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT student_id) AS hired_count
        FROM placed_students
        WHERE drive_id = ?
        AND role_id = ?
    ");
    $hired_stmt->bind_param("ii", $drive_id, $role['role_id']);
    $hired_stmt->execute();
    $hired_result = $hired_stmt->get_result()->fetch_assoc();
    $current_hired_count = (int)($hired_result['hired_count'] ?? 0);

    if ($role_status) {
        $status = strtolower(trim($role_status['final_status']));
        if (!empty($status)) {
            if (!isset($status_counts[$status])) $status_counts[$status] = 0;
            $status_counts[$status]++;
            $total_progress += getCompanyProgress($status);
        }
    }
    
    // Add to hired count regardless of final status
    $hired_count += $current_hired_count;
}
                  $progress = round($total_progress / $role_count);

                  // ✅ Special rule: one role is 'process complete' and rest are 'called off'
                  if (
                      $status_counts['process complete'] >= 1 &&
                      ($status_counts['process complete'] + $status_counts['called off']) === $role_count
                  ) {
                      $progress = 100;
                      $final_status = 'Process Complete';
                  }
                  // ✅ Normal rules
                  elseif ($status_counts['process complete'] > 0) {
                      $final_status = 'Process Complete';
                  } elseif ($status_counts['called off'] == $role_count) {
                      $final_status = 'Called Off';
                  } elseif ($status_counts['ongoing'] > 0) {
                      $final_status = 'Ongoing';
                  } elseif ($status_counts['on hold'] > 0) {
                      $final_status = 'On Hold';
                  } elseif ($status_counts['yet to start'] == $role_count) {
                      $final_status = 'Yet To Start';
                  } else {
                      $final_status = 'Mixed Status';
                  }
              }
              
              // Decode extra_details JSON
              $extra_details = json_decode($d['extra_details'], true);
              if (!is_array($extra_details)) {
                  $extra_details = [];
              }
            ?>

            <div class="drive-card bg-white border border-gray-200 rounded-md shadow p-4 flex justify-between items-start" data-open-date="<?= (new DateTime($d['open_date']))->format('Y-m-d') ?>" data-close-date="<?= (new DateTime($d['close_date']))->format('Y-m-d') ?>">
              <div class="w-3/4">
                <?php
                  $openDate = new DateTime($d['open_date']);
                  $monthYear = $openDate->format("M Y");
                  $driveNum = $d['company_drive_number'] ?? 1;
// Build display name (company + drive no + monthYear)
                  $displayName = htmlspecialchars($d['company_name']) . " ( " . htmlspecialchars($d['drive_no']) . " ) " . $monthYear;

// Determine graduation year display (fall back to 'Not Provided' if missing)
// Show all years if comma-separated
$graduating_year_raw = trim((string)($d['graduating_year'] ?? ''));
if ($graduating_year_raw !== '') {
    $gradDisplay = '[' . htmlspecialchars($graduating_year_raw) . ' grads]';
} else {
    $gradDisplay = ''; // leave empty if not available
}

// Keep work location but DO NOT show it in the header; we'll print it after the application deadline
$workLocation = !empty($d['work_location']) ? htmlspecialchars($d['work_location']) : '';
?>
<h3 class="company-name text-lg font-bold text-[#5e8f84] mb-1" style="display:flex;align-items:center;gap:0.5rem;">
  <span><?= $displayName ?></span>
  <?php if ($gradDisplay): ?>
    <span style="font-weight:600; color:#650000;"style="display:flex;align-items:center;gap:0.5rem;"> [<?= $gradDisplay ?>]</span>
  <?php endif; ?>
</h3>

<p class="text-sm mb-1"><strong>Created by:</strong> <?= htmlspecialchars($d['created_by'] ?? 'Not Provided') ?></p>
<?php
$openDate = new DateTime($d['open_date']);
$closeDate = new DateTime($d['close_date']);
?>
<p class="text-sm mb-1"><strong>Application Deadline:</strong> <?= $openDate->format('d-m-Y h:i A') ?> to <?= $closeDate->format('d-m-Y h:i A') ?></p>

<?php if ($workLocation): ?>
  <p class="text-sm mb-1"><strong>Work Location:</strong> <?= $workLocation ?></p>
<?php endif; ?>
<div class="max-h-80 w-11/12 overflow-y-auto border border-gray-200 rounded-md p-2 bg-gray-50 pr-2 scroll-thin scroll-thumb-gray-300 scroll-track-transparent">

                <div class="roles"style="max-width:100%;overflow-x:auto;">
                  <?php foreach ($roles_data as $index => $role): ?>
                    <div class="role-block" style="word-break:break-word">
                      <p class="text-sm font-bold text-[#650000]">
                        Role <?= $index + 1 ?>: <?= htmlspecialchars($role['designation_name']) ?>
                      </p>

                      <?php if (!empty($role['ctc']) || !empty($role['stipend'])): ?>
                        <p class="text-sm">
                          <strong>
                            <?= !empty($role['ctc']) ? 'CTC: ₹' . htmlspecialchars($role['ctc']) : '' ?>
                            <?= (!empty($role['ctc']) && !empty($role['stipend'])) ? ' | ' : '' ?>
                            <?= !empty($role['stipend']) ? 'Stipend: ₹' . htmlspecialchars($role['stipend']) : '' ?>
                          </strong>
                        </p>
                      <?php else: ?>
                        <p class="text-sm"><strong>CTC/Stipend:</strong> Not Provided</p>
                      <?php endif; ?>

                      <p class="text-sm">
                        <strong>Min Percentage:</strong> <?= !empty($role['min_percentage']) ? htmlspecialchars($role['min_percentage']) . '%' : 'Not Provided' ?>
                      </p>

                      <p class="text-sm">
                        <strong>Offer Type:</strong> <?= !empty($role['offer_type']) ? htmlspecialchars($role['offer_type']) : 'Not Provided' ?>
                      </p>

                      <p class="text-sm">
                        <strong>Job Sector:</strong> <?= !empty($role['sector']) ? htmlspecialchars($role['sector']) : 'Not Provided' ?>
                      </p>
                      <p class="text-sm">
                        <strong>Work Timings:</strong> <?= !empty($role['work_timings']) ? htmlspecialchars($role['work_timings']) : 'Not Provided' ?>
                      </p>

                      <p class="text-sm">
                        <strong>Courses:</strong> <?= htmlspecialchars(simplifyCourses($role['eligible_courses'])) ?>
                      </p>
                    </div>
                  <?php endforeach; ?>
                </div>
                  </div>
          <?php if (!empty($d['jd_file']) || !empty($d['jd_link'])): ?>
    <button onclick="openJdPopup('jdPopup<?= $d['drive_id'] ?>')" class="text-sm text-blue-700 underline">View JD</button>
    <div id="jdPopup<?= $d['drive_id'] ?>" class="fixed inset-0 flex items-center justify-center hidden" style="z-index: 1100;">
       <div class="bg-white p-4 rounded-md w-full max-w-md shadow-lg break-words">
            <h2 class="text-lg font-semibold mb-2">Job Description</h2>
            <ul class="list-disc ml-5 space-y-1">
                <?php if (!empty($d['jd_link'])): ?>
                    <li>
                        <a href="<?= htmlspecialchars($d['jd_link']) ?>" target="_blank" class="text-blue-700 underline">
                            External JD Link
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php if (!empty($d['jd_file'])): ?>
                    <?php
                    $jd_files = json_decode($d['jd_file'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($jd_files)) {
                        foreach ($jd_files as $file) {
                            if (!empty($file)) {
                                echo '<li><a href="' . htmlspecialchars($file) . '" target="_blank" class="text-blue-700 underline break-words block">' . 
                                     htmlspecialchars(basename($file)) . '</a></li>';
                            }
                        }
                    }
                    ?>
                <?php endif; ?>
            </ul>
            <div class="mt-3 text-right">
                <button onclick="closeJdPopup('jdPopup<?= $d['drive_id'] ?>')" class="px-3 py-1 text-xs rounded-md bg-gray-600 text-white hover:bg-gray-700">Close</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<br>

<p class="text-sm">
  <strong>Form Link:</strong> 
  <a href="form_generator?form=<?= $d['form_link'] ?>" target="_blank" class="text-blue-700 underline">
    <?= $d['form_link'] ?>
  </a>
</p>

<?php if (!empty($d['company_url'])): 
    // normalize url (ensure scheme)
    $url = $d['company_url'];
    if (!preg_match('#^https?://#i', $url)) $url = 'https://' . ltrim($url, '/');
?>
  <p class="text-sm">
    <strong>Company URL:</strong>
    <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="text-blue-700 underline" style="margin-left:6px;">
      <?= htmlspecialchars($d['company_url']) ?>
    </a>
  </p>
<?php endif; ?>

                <div class="flex flex-wrap gap-2 mt-2">
<?php
$currentDate = date('Y-m-d');
$driveStatus = ($d['created_at'] >= $currentDate) ? 'current' : 'finished';
?>

<?php if ($applicants_count > 0): ?>
  <a href="enrolled_students?from_dashboard=1&company=<?= urlencode($companyName) ?>&drive_no=<?= $d['drive_no'] ?>&status=<?= $driveStatus ?>" 
     class="px-3 py-1 text-xs rounded-md border-2 border-[#650000] text-[#650000] bg-white hover:bg-[#fdf2f2]">
     View Applications
  </a>
<?php else: ?>
  <button type="button" 
          onclick="alert('No applications found for this drive')" 
          class="px-3 py-1 text-xs rounded-md border-2 border-gray-400 text-gray-400 bg-white cursor-not-allowed">
    View Applications
  </button>
<?php endif; ?>

                  <a href="edit_drive?drive_id=<?= $d['drive_id'] ?>" class="px-3 py-1 text-xs rounded-md border-2 border-purple-600 text-purple-600 bg-white hover:bg-purple-50">Edit</a>
                  <form action="delete_drive" method="POST" onsubmit="return confirm('Are you sure?');" style="display:inline-block;">
                    <input type="hidden" name="drive_id" value="<?= (int)$d['drive_id'] ?>">
                    <button type="submit" class="px-3 py-1 text-xs rounded-md border-2 border-red-600 text-red-600 bg-white hover:bg-red-50">Delete</button>
                  </form>
<button onclick="openCopyBox('<?= $d['drive_id'] ?>')" class="px-3 py-1 text-xs rounded-md border-2 border-green-600 text-green-600 bg-white hover:bg-green-50">Copy/Share</button>                </div>
              </div>

               <div class="w-1/4">
                <div class="text-sm font-bold mb-1">Progress</div>
                <div class="w-full bg-gray-200 rounded-full h-3 mb-1">
                  <div class="h-3 rounded-full" style="width: <?= $progress ?>%; background-color: #650000;"></div>
                </div>
                <div class="text-xs mb-2"><?= $progress ?>% completed</div>
                <p class="text-sm"><strong>Applicants:</strong> <?= $applicants_count ?></p>
                <p class="text-sm"><strong>Hired:</strong> <?= $hired_count ?></p>
              </div>

              <!-- Copy Box Popup -->
              <div id="copybox<?= $d['drive_id'] ?>" class="hidden fixed inset-0 flex items-center justify-center" style="z-index: 1100;">
                <div class="bg-white p-4 rounded-md w-full max-w-md">
                  <h2 class="text-lg font-semibold mb-2">Drive Details</h2>
                 
<textarea id="copyText<?= $d['drive_id'] ?>" rows="15" class="w-full text-xs border border-gray-300 rounded p-2 mb-2">
Dear All,
Greetings from the Placement Cell !
--------------------
<?php
// Build the short hiring sentence for the copy/share box
$companyName = trim($d['company_name'] ?? '');
$gradYear = trim((string) ($d['graduating_year'] ?? ''));
if ($companyName === '') {
    $companyLine = "Company is hiring students";
} else {
    if ($gradYear !== '') {
        // Show all years if comma-separated
        $companyLine = htmlspecialchars($companyName) . " is hiring " . htmlspecialchars($gradYear) . " grads";
    } else {
        $companyLine = htmlspecialchars($companyName) . " is hiring students";
    }
}
?>
*<?= $companyLine ?>*

Company URL: <?= !empty($d['company_url']) ? htmlspecialchars($d['company_url']) : 'Not Provided' ?>

Work Location: *<?= !empty($d['work_location']) ? htmlspecialchars($d['work_location']) : 'Not Provided' ?>*

APPLICATION FORM TIMELINE:
--------------------
*Form Open Date: <?= $openDate->format('d-m-Y h:i A') ?>*
*Form Close Date:<?= $closeDate->format('d-m-Y h:i A') ?>*

JOB ROLES:
--------------------
<?php foreach ($roles_data as $index => $role): ?>
ROLE <?= $index + 1 ?>:
<?php 
$role_fields = [];
        if (!empty($role['designation_name'])) $role_fields[] = "- *Designation: " . htmlspecialchars($role['designation_name']). "*";
        if (!empty($role['ctc'])) $role_fields[] = "- *CTC:₹ " . htmlspecialchars($role['ctc']). "*";
        if (!empty($role['stipend'])) $role_fields[] = "- *Stipend:₹ " . htmlspecialchars($role['stipend']). "*";
        if (!empty($role['work_timings'])) $role_fields[] = "- *Work Timings: " . htmlspecialchars($role['work_timings']). "*";
        if (!empty($role['eligible_courses'])) $role_fields[] = "- *Eligible Courses: " . htmlspecialchars(simplifyCourses($role['eligible_courses'])). "*";
        if (!empty($role['min_percentage'])) $role_fields[] = "- *Minimum Percentage: " . htmlspecialchars($role['min_percentage']) . "%". "*";
        if (!empty($role['offer_type'])) $role_fields[] = "- *Offer Type: " . htmlspecialchars($role['offer_type']). "*";
        if (!empty($role['sector'])) $role_fields[] = "- *Job Sector: " . htmlspecialchars($role['sector']). "*";

        echo implode("\n", $role_fields);
?>


<?php endforeach; ?>
--------------------
<?php
// --- Start replacement block ---

// Define the desired order of fields (removed 'whatsapp' from this order)
$fieldOrder = [
    'ctcDetails',
    'workMode',
    'officeAddress',
    'interviewDetails',
    'eligibilityNote',
    'deadlineNote',
    'vacancies',
    'location',
    'duration',
    'stipend',
    'postInternship',
    'timings',
    'additionalInfo' // whatsapp intentionally not included here
];

$defaultLabels = [
    'ctcDetails' => 'CTC Details',
    'workMode' => 'Mode of Work',
    'officeAddress' => 'Office Address',
    'interviewDetails' => 'Interview Details',
    'eligibilityNote' => 'Eligibility Notes / Restrictions',
    'deadlineNote' => 'Application Deadline',
    'vacancies' => 'No. of Vacancies',
    'location' => 'Location',
    'duration' => 'Duration',
    'stipend' => 'Process Details',
    'postInternship' => 'Post Internship Opportunity',
    'timings' => 'Timings',
    'additionalInfo' => 'Any Additional Info'
];

// We'll build a cleaned copy of extra_details excluding 'whatsapp'
$clean_extra = [];
foreach ($extra_details as $k => $v) {
    if ($k === 'whatsapp') continue; // skip whatsapp here
    if (!empty($v)) $clean_extra[$k] = $v;
}

// First display fields in the specified order (from $clean_extra)
foreach ($fieldOrder as $key) {
    if (isset($clean_extra[$key]) && !empty($clean_extra[$key])) {
        $label = $defaultLabels[$key] ?? ucfirst(str_replace('_', ' ', $key));
        echo "- " . $label . ": *" . htmlspecialchars($clean_extra[$key]) . "*\n";
    }
}

// Then display any remaining extra_details (excluding whatsapp)
foreach ($clean_extra as $key => $value) {
    if (!empty($value) && !in_array($key, $fieldOrder)) {
        $label = ucfirst(str_replace('_', ' ', $key));
        echo "- " . $label . ": *" . htmlspecialchars($value) . "*\n";
    }
}

// --- End replacement block ---
?>

APPLICATION DETAILS:
--------------------
<?php 
// Define base URL dynamically (works in root or subfolder)
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
          . "://{$_SERVER['HTTP_HOST']}" . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . "/";

if (!empty($d['form_link'])): ?>
*Form Link:* <?= $base_url ?>form_generator?form=<?= urlencode($d['form_link']) ?>


<?php endif; ?>
<?php
// Now print WhatsApp Group Link immediately after the form link (if present)
if (!empty($extra_details['whatsapp'])) {
    // Some teams store raw URL or a text — preserve as-is but escape
    $wa = trim($extra_details['whatsapp']);
    echo "*WhatsApp Group Link:* " . htmlspecialchars($wa) . "\n\n";
}
?>
<?php 
// JD Links/Files section
$jd_items = [];
if (!empty($d['jd_link'])) {
    $jd_items[] = $d['jd_link'];
}
if (!empty($d['jd_file'])) {
    $jd_files = json_decode($d['jd_file'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($jd_files)) {
        foreach ($jd_files as $file) {
            if (!empty($file)) {
                $jd_items[] = $base_url . ltrim($file, '/');
            }
        }
    } elseif (!empty($d['jd_file'])) {
        $jd_items[] = $base_url . ltrim($d['jd_file'], '/');
    }
}
if (!empty($jd_items)): ?>
*JD Links/Files:*
<?php foreach ($jd_items as $item): ?>-<?= htmlspecialchars($item) ?>


<?php endforeach; ?>
<?php endif; ?>


                  </textarea>

                  <div class="flex justify-end gap-2">
                    <button onclick="copyToClipboard('copyText<?= $d['drive_id'] ?>')"  style="background-color: #650000;" class="px-3 py-1 text-xs rounded-md text-white">Copy</button>
                    <button onclick="closeCopyBox('<?= $d['drive_id'] ?>')" class="px-3 py-1 text-xs rounded-md bg-gray-600 text-white">Close</button>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<!-- Overlay -->
<div id="overlay"></div>

<!-- Dashboard Filter Modal -->
<div id="dashboardFilterModal" class="filter-modal">
  <div class="modal-content">
    <span class="close" onclick="closeDashboardFilter()">&times;</span>
    <h3>Filter Drives</h3>
    <div class="filter-grid">
      <label>
        Company Name
        <input type="text" id="filter_company" placeholder="e.g. Google">
      </label>
      <label>
        Role
        <input type="text" id="filter_role" placeholder="e.g. Software Engineer">
      </label>
      <label>
        Open Date
        <input type="text" id="filter_open_date" placeholder="dd-mm-yyyy">
      </label>
      <label>
        Close Date
        <input type="text" id="filter_close_date" placeholder="dd-mm-yyyy">
      </label>
      <label>
        Created By
        <input type="text" id="filter_created_by" placeholder="e.g., admin">
      </label>
      <label>
        Offer Type
        <select name="offer_type" id="filter_offer_type">
          <option value="">--Select offer type--</option>
          <option value="FTE">FTE</option>
          <option value="Apprentice">Apprentice</option>
          <option value="Internship + PPO">Internship + PPO</option>
        </select>
      </label>
      <label>
        Job Sector
        <select name="sector" id="filter_sector">
          <option value="">-- Select Job Sector --</option>
          <option value="Sales, Marketing, BD">Sales, Marketing, BD</option>
          <option value="HR">HR</option>
          <option value="BFSI">BFSI</option>
          <option value="Consulting">Consulting</option>
          <option value="Analytics">Analytics</option>
          <option value="Ops & Management">Ops & Management</option>
          <option value="IT">IT</option>
          <option value="Healthcare & Wellness">Healthcare & Wellness</option>
          <option value="Ed & Teaching">Ed & Teaching</option>
          <option value="Hospitality & Tourism">Hospitality & Tourism</option>
          <option value="Media & Content">Media & Content</option>
          <option value="Customer/Client Service">Customer/Client Service</option>
          <option value="Fashion & Design">Fashion & Design</option>
          <option value="Int Design Mgmt">Int Design Mgmt</option>
          <option value="Research">Research</option>
          <option value="Resource Planning & Logistics">Resource Planning & Logistics</option>
        </select>
      </label>
    </div>
    <div class="filter-actions">
      <button type="submit" onclick="applyDashboardFilter()">Apply Filter</button>
      <button type="button" class="clear-button" onclick="clearFilterFields()">Clear Filter</button>
    </div>
  </div>
</div>
<script>
// Tab functionality
function openExportPopup() {
    // Overlay background
    const overlay = document.createElement('div');
    overlay.id = "exportOverlay";
    overlay.style.cssText = `
        position:fixed;
        top:0; left:0;
        width:100%; height:100%;
        background:rgba(0,0,0,0.6);
        z-index:2000;
        display:flex;
        justify-content:center;
        align-items:center;
    `;

    // Collect current dashboard filters (from modal inputs and active tab)
    const company = document.getElementById('filter_company')?.value || '';
    const role = document.getElementById('filter_role')?.value || '';
    const fromDateInput = document.getElementById('filter_open_date')?.value || '';
    const toDateInput = document.getElementById('filter_close_date')?.value || '';
    const createdBy = document.getElementById('filter_created_by')?.value || '';
    const offerType = document.getElementById('filter_offer_type')?.value || '';
    const sector = document.getElementById('filter_sector')?.value || '';
    let status = '';
    if (document.getElementById('tabBtn_Current')?.classList.contains('active')) status = 'Current';
    else if (document.getElementById('tabBtn_Upcoming')?.classList.contains('active')) status = 'Upcoming';
    else if (document.getElementById('tabBtn_Finished')?.classList.contains('active')) status = 'Finished';

    const query = document.getElementById('globalSearch')?.value || '';
    const toIso = (dmy) => {
        if (!dmy) return '';
        const parts = dmy.split('-');
        if (parts.length !== 3) return '';
        const [dd, mm, yyyy] = parts;
        if (!dd || !mm || !yyyy) return '';
        return `${yyyy}-${mm.padStart(2,'0')}-${dd.padStart(2,'0')}`;
    };

    const params = new URLSearchParams({
        popup: '1',
        company_name: company,
        role: role,
        from_date: toIso(fromDateInput),
        to_date: toIso(toDateInput),
        status: status,
        offer_type: offerType,
        sector: sector,
        q: query,
        created_by: createdBy
    });

    // Centered iframe modal
    const iframe = document.createElement('iframe');
    iframe.src = "export_dashboard?" + params.toString();
    iframe.style.cssText = `
        width:800px;
        height:600px;
        border:none;
        background:white;
        border-radius:8px;
        box-shadow:0 4px 20px rgba(0,0,0,0.3);
    `;

    overlay.appendChild(iframe);
    document.body.appendChild(overlay);
}

function closeExportPopup() {
    const overlay = document.getElementById("exportOverlay");
    if (overlay) overlay.remove();
}


function showTab(tab) {
  document.querySelectorAll('.tab-content').forEach(content => {
    content.classList.add('hidden');
  });
  document.querySelectorAll('.tab-button').forEach(button => {
    button.classList.remove('active');
  });

  document.getElementById(`tab_${tab}`).classList.remove('hidden');
  document.getElementById(`tabBtn_${tab}`).classList.add('active');
}

function openCopyBox(id) {
    const copyBox = document.getElementById('copybox' + id);
    if (copyBox) {
        copyBox.classList.remove('hidden');
        document.getElementById('overlay').style.display = 'block';
    }
}

function closeCopyBox(id) {
    const copyBox = document.getElementById('copybox' + id);
    if (copyBox) {
        copyBox.classList.add('hidden');
        document.getElementById('overlay').style.display = 'none';
    }
}

function copyToClipboard(id) {
    const textarea = document.getElementById(id);
    if (!textarea) return;

    try {
        navigator.clipboard.writeText(textarea.value)
            .then(() => {
                alert('Copied to clipboard!');
            })
            .catch(err => {
                console.error('Failed to copy:', err);
                textarea.select();
                document.execCommand('copy');
                alert('Copied to clipboard!');
            });
    } catch (err) {
        console.error('Clipboard API not available:', err);
        textarea.select();
        document.execCommand('copy');
        alert('Copied to clipboard!');
    }
}

function openJdPopup(id) {
  document.getElementById(id).classList.remove('hidden');
  document.getElementById('overlay').style.display = 'block';
}

function closeJdPopup(id) {
  document.getElementById(id).classList.add('hidden');
  document.getElementById('overlay').style.display = 'none';
}

function globalSearch() {
  const searchTerm = document.getElementById('globalSearch').value.toLowerCase();
  
  if (!searchTerm) {
    document.querySelectorAll('.drive-card').forEach(card => {
      card.style.display = '';
    });
    return;
  }
  
  document.querySelectorAll('.drive-card').forEach(card => {
    const cardText = card.innerText.toLowerCase();
    card.style.display = cardText.includes(searchTerm) ? '' : 'none';
  });
}

document.addEventListener('DOMContentLoaded', function() {
  showTab('Current');
  
  document.getElementById('overlay').addEventListener('click', function() {
    document.querySelectorAll('[id^="copybox"], [id^="jdPopup"], #dashboardFilterModal').forEach(el => {
      el.classList.add('hidden');
    });
    this.style.display = 'none';
  });

  document.getElementById('globalSearch').addEventListener('input', globalSearch);
});

function openDashboardFilter() {
  document.getElementById('dashboardFilterModal').style.display = 'flex';
  document.getElementById('overlay').style.display = 'block';
}

function closeDashboardFilter() {
  document.getElementById('dashboardFilterModal').style.display = 'none';
  document.getElementById('overlay').style.display = 'none';
}

function applyDashboardFilter() {
  const company = document.getElementById('filter_company').value.toLowerCase();
  const role = document.getElementById('filter_role').value.toLowerCase();
  const openDateInput = document.getElementById('filter_open_date').value;
  const closeDateInput = document.getElementById('filter_close_date').value;
  const offerType = document.getElementById('filter_offer_type').value;
  const sector = document.getElementById('filter_sector').value.toLowerCase();
  const createdBy = document.getElementById('filter_created_by').value.toLowerCase();

  const dmyToIso = (dmy) => {
    if (!dmy) return '';
    const parts = dmy.split('-');
    if (parts.length !== 3) return '';
    const [dd, mm, yyyy] = parts;
    if (!dd || !mm || !yyyy) return '';
    return `${yyyy}-${mm.padStart(2,'0')}-${dd.padStart(2,'0')}`;
  };

  const openDate = dmyToIso(openDateInput);
  const closeDate = dmyToIso(closeDateInput);

  document.querySelectorAll('.drive-card').forEach(card => {
    const companyText = card.querySelector('.company-name')?.innerText.toLowerCase() || '';
    const rolesText = card.querySelector('.roles')?.innerText.toLowerCase() || '';
    const cardOpenDate = card.dataset.openDate || '';
    const cardCloseDate = card.dataset.closeDate || '';

    // Created By is the first font-semibold paragraph in the left block
    const createdByText = (card.querySelector('p.text-sm')?.innerText || '').toLowerCase();
    const createdByValue = createdByText.replace('created by:', '').trim();

    let hasMatchingOfferType = !offerType;
    let hasMatchingSector = !sector;

    card.querySelectorAll('.role-block').forEach(roleBlock => {
      const offerTypeLine = Array.from(roleBlock.querySelectorAll('p.text-sm')).find(p => 
        p.innerText.includes('Offer Type:')
      );
      
      if (offerTypeLine && offerType) {
        const offerTypeText = offerTypeLine.innerText.split(':')[1].trim();
        if (offerTypeText === offerType) {
          hasMatchingOfferType = true;
        }
      }
      
      const sectorLine = Array.from(roleBlock.querySelectorAll('p.text-sm')).find(p => 
        p.innerText.toLowerCase().includes('sector:')
      );
      
      if (sectorLine && sector) {
        const sectorText = sectorLine.innerText.split(':')[1].trim().toLowerCase();
        if (sectorText.includes(sector)) {
          hasMatchingSector = true;
        }
      }
    });

    const matchesCompany = !company || companyText.includes(company);
    const matchesRole = !role || rolesText.includes(role);
    const matchesOpenDate = !openDate || (cardOpenDate === openDate);
    const matchesCloseDate = !closeDate || (cardCloseDate === closeDate);
    const matchesCreatedBy = !createdBy || createdByValue.includes(createdBy);

    if (matchesCompany && matchesRole && matchesOpenDate && matchesCloseDate && matchesCreatedBy && 
        hasMatchingOfferType && hasMatchingSector) {
      card.style.display = '';
    } else {
      card.style.display = 'none';
    }
  });

  closeDashboardFilter();
}

function resetDashboardFilter() {
  document.getElementById('filter_company').value = '';
  document.getElementById('filter_role').value = '';
  document.getElementById('filter_open_date').value = '';
  document.getElementById('filter_close_date').value = '';
  document.getElementById('filter_offer_type').value = '';
  document.getElementById('filter_sector').value = '';

  document.querySelectorAll('.drive-card').forEach(card => {
    card.style.display = '';
  });
}


function clearFilterFields() {
  document.getElementById('filter_company').value = '';
  document.getElementById('filter_role').value = '';
  document.getElementById('filter_open_date').value = '';
  document.getElementById('filter_close_date').value = '';
  document.getElementById('filter_created_by').value = '';
  document.getElementById('filter_offer_type').value = '';
  document.getElementById('filter_sector').value = '';
}


</script>

<?php include("footer.php"); ?>

<script>
// Initialize datepickers for dashboard filters with d-m-Y format
document.addEventListener('DOMContentLoaded', function() {
  if (window.flatpickr) {
    flatpickr('#filter_open_date', { dateFormat: 'd-m-Y', allowInput: true });
    flatpickr('#filter_close_date', { dateFormat: 'd-m-Y', allowInput: true });
  }
});
</script>