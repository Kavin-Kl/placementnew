<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Check if this is a popup request
$isPopup = isset($_GET['popup']) && $_GET['popup'] == '1';
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

include("config.php");
include("course_groups_dynamic.php");

// Initialize course arrays if not already set
if (!isset($ug_courses_grouped)) $ug_courses_grouped = [];
if (!isset($pg_courses_grouped)) $pg_courses_grouped = [];

// Dynamically flatten UG courses with null checks
$allUG = [];
foreach ($ug_courses_grouped as $group) {
    if (is_array($group)) {
        foreach ($group as $programs) {
            if (is_array($programs)) {
                $allUG = array_merge($allUG, $programs);
            }
        }
    }
}

// Dynamically flatten PG courses with null checks
$allPG = [];
foreach ($pg_courses_grouped as $group) {
    if (is_array($group)) {
        foreach ($group as $programs) {
            if (is_array($programs)) {
                $allPG = array_merge($allPG, $programs);
            }
        }
    }
}

// Function to get company progress percentage
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

// Function to simplify courses display with enhanced error handling
function simplifyCourses($coursesRaw) {
    global $allUG, $allPG;

    // Ensure course arrays are at least empty arrays if null
    $allUG = is_array($allUG) ? $allUG : [];
    $allPG = is_array($allPG) ? $allPG : [];

    if (empty($coursesRaw)) {
        return "Not Provided";
    }

    $selected = json_decode($coursesRaw, true);
    if (!is_array($selected)) {
        $selected = [];
    }

    // Clean invalid values like "on"
    $selected = array_filter($selected, function ($v) {
        return $v !== "on" && trim($v) !== "";
    });

    if (empty($selected)) {
        return "Not Provided";
    }

    // Convert all to lowercase for accurate comparison
    $selected_lower = array_map('strtolower', $selected);
    $allUG_lower = is_array($allUG) ? array_map('strtolower', $allUG) : [];
    $allPG_lower = is_array($allPG) ? array_map('strtolower', $allPG) : [];

    $display = [];

    $ugMatch = array_intersect($selected_lower, $allUG_lower);
    $pgMatch = array_intersect($selected_lower, $allPG_lower);

    // If all UG matched
    if (count($ugMatch) === count($allUG_lower) && count($allUG_lower) > 0) {
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
    if (count($pgMatch) === count($allPG_lower) && count($allPG_lower) > 0) {
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

// Function to get drive status - FIXED to use proper datetime comparison
function getDriveStatus($open, $close) {
    $now = new DateTime();
    $openDate = new DateTime($open);
    $closeDate = new DateTime($close);
    
    if ($closeDate < $now) return "Finished";
    if ($openDate <= $now && $closeDate >= $now) return "Current";
    return "Upcoming";
}

// Function to format date to d-m-y-H-i A format
function formatDateForExport($dateString) {
    $date = new DateTime($dateString);
    return $date->format('d-m-y-H-i A');
}

// Process export request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_fields'])) {
    $selectedFields = $_POST['export_fields'];
    
    $validFields = [
        'slno', 'company_name', 'drive_no', 'status', 'form_open_date', 'form_close_date',
        'progress', 'drive_status', 'applicants', 'hired', 'designation',
        'ctc', 'stipend', 'min_percentage', 'offer_type', 'job_sector', 'eligible_courses',
        'form_link', 'jd_link', 'jd_files', 'extra_details', 'created_by', 'share_data'
    ];
    
    $exportFields = array_intersect($selectedFields, $validFields);
    $exportFields = array_intersect($selectedFields, $validFields);
    array_unshift($exportFields, 'slno'); // Always add slno as the first column
    
    if (empty($exportFields)) {
        die("No valid fields selected for export");
    }

    // Create new Spreadsheet with compatibility settings
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set document properties for better compatibility
    $spreadsheet->getProperties()
        ->setCreator("Placement Portal")
        ->setLastModifiedBy("Placement Portal")
        ->setTitle("Placement Drives Export")
        ->setSubject("Placement Drives Data")
        ->setDescription("Export of placement drives data for Excel and Google Sheets");
    
    // Set headers with styling
    $col = 1;
    foreach ($exportFields as $field) {
        $header = ucwords(str_replace('_', ' ', $field));
        if ($field === 'share_data') {
            $header = 'WhatsApp shared message';
        }
        if ($field === 'designation') $header = 'Role';
        if ($field === 'form_open_date') $header = 'Form Open Date';
        if ($field === 'form_close_date') $header = 'Form Close Date';
        if ($field === 'jd_files') $header = 'JD Files';
        $sheet->setCellValueExplicitByColumnAndRow($col++, 1, $header, DataType::TYPE_STRING);
    }
    
    // Apply header style: bold only (no background fill color)
    $headerStyle = [
        'font' => [
            'bold' => true,
            'size' => 11
        ]
    ];
    $sheet->getStyle('A1:'.chr(64+count($exportFields)).'1')->applyFromArray($headerStyle);
    
    // ================================================================
    // Apply filters from POST data (passed from dashboard)
    // ================================================================
    $whereClause = "1=1";
    $filterParams = [];
    $types = '';
    
    // Get filter data directly from POST
    $filters = [
        'company_name' => $_POST['company_name'] ?? '',
        'status' => $_POST['status'] ?? '',
        'from_date' => $_POST['from_date'] ?? '',
        'to_date' => $_POST['to_date'] ?? ''
    ];
    
    // Apply filters
    if (!empty($filters['company_name'])) {
        $whereClause .= " AND company_name LIKE ?";
        $filterParams[] = '%' . $filters['company_name'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['status'])) {
        $now = new DateTime();
        $today = $now->format('Y-m-d H:i:s');
        $status = $filters['status'];
        
        if ($status === 'Finished') {
            $whereClause .= " AND close_date < ?";
            $filterParams[] = $today;
            $types .= 's';
        } 
        elseif ($status === 'Current') {
            $whereClause .= " AND open_date <= ? AND close_date >= ?";
            $filterParams[] = $today;
            $filterParams[] = $today;
            $types .= 'ss';
        } 
        elseif ($status === 'Upcoming') {
            $whereClause .= " AND open_date > ?";
            $filterParams[] = $today;
            $types .= 's';
        }
    }
    
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
    
    // Get data from database with filters applied
    $drives_query = "SELECT * FROM drives WHERE $whereClause ORDER BY open_date DESC";
    
    if (!empty($filterParams)) {
        $stmt = $conn->prepare($drives_query);
        $stmt->bind_param($types, ...$filterParams);
        $stmt->execute();
        $drives = $stmt->get_result();
    } else {
        $drives = $conn->query($drives_query);
    }
    // ================================================================
    
    $row = 2;
    $slno = 1;
    
    if ($drives->num_rows > 0) {
        while ($drive = $drives->fetch_assoc()) {
            $drive_id = $drive['drive_id'];
            $companyName = $drive['company_name'];
            $drive_no = $drive['drive_no'];
            $openDate = $drive['open_date'];
            $closeDate = $drive['close_date'];
            $formLink = $drive['form_link'];
            $jdLink = $drive['jd_link']; // Use jd_link field instead of jd_file
            $jdFiles = $drive['jd_file']; // Get JD files for separate export
            $createdBy = $drive['created_by'] ?? 'Not Provided';
            
            // Get roles data
            $roles_data = [];
            $roles_result = $conn->query("SELECT * FROM drive_roles WHERE drive_id = {$drive['drive_id']}");
            while ($role = $roles_result->fetch_assoc()) {
                $roles_data[] = $role;
            }
            $data = [];

            // Calculate progress and status
            $progress = 0;
            $final_status = 'N/A';
            $hired_count = 0;
            
            if (count($roles_data) > 0) {
                $total_progress = 0;
                $status_counts = [
                    'yet to start' => 0,
                    'on hold' => 0,
                    'ongoing' => 0,
                    'process complete' => 0,
                    'no applicants' => 0,
                    'called off' => 0
                ];

                foreach ($roles_data as $role) {
                    if (!isset($role['role_id'])) continue;
                    
                    $stmt = $conn->prepare("SELECT final_status, hired_count FROM drive_data WHERE drive_id = ? AND role_id = ?");
                    $stmt->bind_param("ii", $drive_id, $role['role_id']);
                    $stmt->execute();
                    $role_status = $stmt->get_result()->fetch_assoc();
                    
                    if ($role_status) {
                        $status = strtolower(trim($role_status['final_status']));
                        if (!empty($status)) {
                            $status_counts[$status]++;
                            $hired_count += (int)$role_status['hired_count'];
                            $total_progress += getCompanyProgress($status);
                        }
                    }
                }
                
                $progress = round($total_progress / count($roles_data));
                
                if ($status_counts['process complete'] >= 1 && ($status_counts['process complete'] + $status_counts['called off']) === count($roles_data)) {
                    $final_status = 'Process Complete';
                } elseif ($status_counts['process complete'] > 0) {
                    $final_status = 'Process Complete';
                } elseif ($status_counts['called off'] == count($roles_data)) {
                    $final_status = 'Called Off';
                } elseif ($status_counts['ongoing'] > 0) {
                    $final_status = 'Ongoing';
                } elseif ($status_counts['on hold'] > 0) {
                    $final_status = 'On Hold';
                } elseif ($status_counts['yet to start'] == count($roles_data)) {
                    $final_status = 'Yet To Start';
                } else {
                    $final_status = 'Mixed Status';
                }
            }
            
            // Prepare share data (whatsapp message format)
            // --------------------------
// Build share_data (WhatsApp style) — made identical to dashboard's Copy/Share output
// --------------------------
$share_data = "";

// Short bolded hiring sentence (matches dashboard)
// Build companyLine like dashboard: "Social Panga is hiring 2026 grads"
$companyNameClean = trim($companyName ?: '');
$gradYearRaw      = trim((string)($drive['graduating_year'] ?? ''));
if ($companyNameClean === '') {
    $companyLine = "Company is hiring students";
} else {
    if ($gradYearRaw !== '') {
        $firstYear = explode(',', $gradYearRaw)[0];
        $companyLine = $companyNameClean . " is hiring " . $firstYear . " grads";
    } else {
        $companyLine = $companyNameClean . " is hiring students";
    }
}
// Bold for markdown/WhatsApp
// Intro message before the company headline
$share_data .= "Dear All,\n";
$share_data .= "Greetings from the Placement Cell !\n";
$share_data .= "--------------------\n";
$share_data .= "*{$companyLine}*\n";

// Add drive-level extras (company URL, graduating year, work location) — same order as edit page
if (!empty($drive['company_url'])) {
    $share_data .= "Company URL: " . $drive['company_url'] . "\n";
}
if (!empty($drive['graduating_year'])) {
    $share_data .= "Graduating Year: " . $drive['graduating_year'] . "\n";
}
if (!empty($drive['work_location'])) {
    $share_data .= "Work Location: " . $drive['work_location'] . "\n";
}
if (!empty($drive['company_url']) || !empty($drive['graduating_year']) || !empty($drive['work_location'])) {
    $share_data .= "\n";
}

// Drive timeline
$share_data .= "DRIVE TIMELINE:\n";
$share_data .= "--------------------\n";
$share_data .= "Form Open Date: " . formatDateForExport($openDate) . "\n";
$share_data .= "Form Close Date: " . formatDateForExport($closeDate) . "\n\n";

// Roles (include Work Timings)
$share_data .= "JOB ROLES:\n";
$share_data .= "--------------------\n";
foreach ($roles_data as $index => $role) {
    $share_data .= "ROLE " . ($index + 1) . ":\n";
    if (!empty($role['designation_name'])) {
        $share_data .= "- Designation Name: " . $role['designation_name'] . "\n";
    }
    if (!empty($role['ctc'])) {
        $share_data .= "- CTC: ₹" . $role['ctc'] . "\n";
    }
    if (!empty($role['stipend'])) {
        $share_data .= "- Stipend: ₹" . $role['stipend'] . "\n";
    }
    if (!empty($role['work_timings'])) {
        $share_data .= "- Work Timings: " . $role['work_timings'] . "\n";
    }
    if (!empty($role['min_percentage'])) {
        $share_data .= "- Min %: " . $role['min_percentage'] . "%\n";
    }
    $share_data .= "- Courses: " . simplifyCourses($role['eligible_courses']) . "\n";
    $share_data .= "\n";
}

// Application details (form link)
$share_data .= "APPLICATION DETAILS:\n";
$share_data .= "--------------------\n";
if (!empty($formLink)) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $subfolder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $baseUrl = "$protocol://$host$subfolder";
    $share_data .= "Form Link: {$baseUrl}/form_generator?form=" . $formLink . "\n\n";
}

// JD Link & Files
if (!empty($jdLink)) {
    $share_data .= "JD Link: $jdLink\n\n";
}
if (!empty($jdFiles)) {
    $files = json_decode($jdFiles, true);
    if (is_array($files) && !empty($files)) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $subfolder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $baseUrl = "$protocol://$host$subfolder";
        $share_data .= "JD Files:\n";
        foreach ($files as $file) {
            if (!empty($file)) {
                $share_data .= $baseUrl . '/' . ltrim($file, '/') . "\n";
            }
        }
        $share_data .= "\n";
    }
}

// Extra details (already present in your code) — keep their existing label mapping
$extra_details = json_decode($drive['extra_details'], true) ?: [];
if (!empty($extra_details)) {
    $share_data .= "EXTRA DETAILS:\n";
    $share_data .= "--------------------\n";
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
        'whatsapp' => 'WhatsApp Group Link',
        'additionalInfo' => 'Additional Info'
    ];
    foreach ($extra_details as $key => $value) {
        if (!empty($value)) {
            $label = $defaultLabels[$key] ?? ucfirst(str_replace('_', ' ', $key));
            $share_data .= "- " . $label . ": " . $value . "\n";
        }
    }
    $share_data .= "\n";
}


// Define base URL dynamically (works on localhost, root, or subfolder)
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
          . "://{$_SERVER['HTTP_HOST']}" . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . "/";

            // Add application details with full URL
            $share_data .= "APPLICATION DETAILS:\n";
            $share_data .= "--------------------\n";
            if (!empty($formLink)) {
                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                
                $share_data .= "Form Link: {$baseUrl}/form_generator?form=" . $formLink . "\n\n";

                
            }

            // Handle JD links
            if (!empty($jdLink)) {
                $share_data .= "JD Link: $jdLink\n\n";
            }

            // Handle JD files
if (!empty($jdFiles)) {
    $files = json_decode($jdFiles, true);
    if (is_array($files) && !empty($files)) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $subfolder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // includes /placement_cell_it

        $baseUrl = "$protocol://$host$subfolder";

        $share_data .= "JD Files:\n";
        foreach ($files as $file) {
            if (!empty($file)) {
                $share_data .= $baseUrl . '/' . ltrim($file, '/') . "\n";
            }
        }
        $share_data .= "\n";
    }
}


            // Prepare extra details
            
            // Prepare extra details
            $extra_details = json_decode($drive['extra_details'], true) ?: [];
            $extra_details_str = '';
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
  'whatsapp' => 'WhatsApp Group Link',
  'additionalInfo' => 'Additional Info'
];

foreach ($extra_details as $key => $value) {
    if (!empty($value)) {
        $label = $defaultLabels[$key] ?? ucfirst(str_replace('_', ' ', $key)); // fallback to formatted key
        $extra_details_str .= $label . ": " . $value . "\n";
    }
}
    $data = [];
// DISABLED: Import from Excel already has complete data
// include_once __DIR__ . '/sync_placed_students.php';
// sync_placed_students($conn);

            
            // If multiple roles, create multiple rows
            if (count($roles_data) > 0) {
                foreach ($roles_data as $role) {
                    $col = 1;
                    foreach ($exportFields as $field) {
                        $value = '';
                        switch ($field) {
                            case 'slno': $value = $slno; break;
                            case 'company_name': $value = $companyName; break;
                            case 'drive_no': $value = $drive_no; break;
                            case 'status': $value = getDriveStatus($openDate, $closeDate); break;
                            case 'form_open_date': $value = formatDateForExport($openDate); break;
                            case 'form_close_date': $value = formatDateForExport($closeDate); break;
                            case 'progress': $value = $progress; break;
                            case 'drive_status':
                            // Fetch final_status for this specific role
                                  $stmt = $conn->prepare("SELECT final_status FROM drive_data WHERE drive_id = ? AND role_id = ?");
                                  $stmt->bind_param("ii", $drive_id, $role['role_id']);
                                  $stmt->execute();
                                  $result = $stmt->get_result();
                                  $roleStatusData = $result->fetch_assoc();
                                  $value = (!empty($roleStatusData['final_status'])) 
                                  ? $roleStatusData['final_status'] 
                                  : 'N/A';
                                break;
                            case 'applicants': 
                                // Get applicants count specific to this role
                                $role_app_count_result = $conn->query("SELECT COUNT(DISTINCT upid) AS count FROM applications WHERE drive_id = $drive_id AND role_id = {$role['role_id']}");
                                if ($role_app_count_result) {
                                    $value = $role_app_count_result->fetch_assoc()['count'];
                                } else {
                                    $value = 0;
                                }
                                break;
                            case 'hired':
    // ✅ Fetch hired count directly from placed_students
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT upid) AS count 
                            FROM placed_students 
                            WHERE drive_id = ? AND role_id = ?");
    $stmt->bind_param("ii", $drive_id, $role['role_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $rowData = $result->fetch_assoc();
    $value = $rowData['count'] ?? 0;
    break;


                            case 'designation': $value = $role['designation_name']; break;
                            case 'ctc': $value = $role['ctc'] ?: ''; break;
                            case 'stipend': $value = $role['stipend'] ?: ''; break;
                            case 'min_percentage': 
                                $value = !empty($role['min_percentage']) ? $role['min_percentage'] . '%' : ''; 
                                break;
                            case 'offer_type': 
                                $value = !empty($role['offer_type']) ? $role['offer_type'] : ''; 
                                break;
                            case 'job_sector': 
                                $value = !empty($role['sector']) ? $role['sector'] : ''; 
                                break;
                            case 'eligible_courses': 
                                $value = simplifyCourses($role['eligible_courses']); 
                                break;
                            case 'form_link': 
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $subfolder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // e.g. /placement_cell_it

    $baseUrl = "$protocol://$host$subfolder";
    $value = $baseUrl . "/form_generator?form=" . $formLink;
    break;

                            case 'jd_link': $value = $jdLink ?: ''; break;
                            case 'jd_files': 
    if (!empty($jdFiles)) {
        $files = json_decode($jdFiles, true);
        if (is_array($files) && !empty($files)) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $subfolder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // e.g., /placement_cell_it

            $baseUrl = "$protocol://$host$subfolder";

            $fileUrls = [];
            foreach ($files as $file) {
                if (!empty($file)) {
                    $fileUrls[] = $baseUrl . '/' . ltrim($file, '/');
                }
            }
            $value = implode("\n", $fileUrls);
        }
    }
    break;

                            case 'extra_details': $value = trim($extra_details_str); break;
                            case 'created_by': $value = $createdBy; break;
                            case 'share_data': $value = $share_data; break;
                        }
                        // Use explicit type setting for better compatibility
                        $cell = $sheet->getCellByColumnAndRow($col, $row);
                        $cell->setValueExplicit($value, DataType::TYPE_STRING);
                        
                        // Set text wrapping for multi-line fields
                        if (in_array($field, ['extra_details', 'share_data', 'jd_files', 'jd_link'])) {
                            $sheet->getStyleByColumnAndRow($col, $row)
                                ->getAlignment()
                                ->setWrapText(true)
                                ->setVertical(Alignment::VERTICAL_TOP);
                        }
                        $col++;
                    }
                    $row++;
                    $slno++;
                }
            } else {
                // Handle case with no roles (ALWAYS include drive-only row for newly added drives)
                $col = 1;
                foreach ($exportFields as $field) {
                    $value = '';
                    if (in_array($field, ['slno', 'company_name', 'drive_no', 'status', 'form_open_date', 'form_close_date', 
                                         'applicants', 'hired', 'progress', 'drive_status', 'designation', 'ctc', 'stipend', 'min_percentage', 'offer_type', 'job_sector', 'eligible_courses',
                                         'form_link', 'jd_link', 'jd_files', 'created_by', 'extra_details', 'share_data'])) {
                        switch ($field) {
                            case 'slno': $value = $slno; break;
                            case 'company_name': $value = $companyName; break;
                            case 'drive_no': $value = $drive_no; break;
                            case 'status': $value = getDriveStatus($openDate, $closeDate); break;
                            case 'form_open_date': $value = formatDateForExport($openDate); break;
                            case 'form_close_date': $value = formatDateForExport($closeDate); break;
                           case 'form_link': 
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $subfolder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // e.g. /placement_cell_it

    $baseUrl = "$protocol://$host$subfolder";
    $value = $baseUrl . "/form_generator?form=" . $formLink;
    break;

                            case 'jd_link': $value = $jdLink ?: ''; break;
                        case 'jd_files': 
    if (!empty($jdFiles)) {
        $files = json_decode($jdFiles, true);
        if (is_array($files) && !empty($files)) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $subfolder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // includes /placement_cell_it

            $baseUrl = "$protocol://$host$subfolder";

            $fileUrls = [];
            foreach ($files as $file) {
                if (!empty($file)) {
                    $fileUrls[] = $baseUrl . '/' . ltrim($file, '/');
                }
            }
            $value = implode("\n", $fileUrls);
        }
    }
    break;

                            case 'created_by': $value = $createdBy; break;
                            case 'extra_details': $value = trim($extra_details_str); break;
                            case 'share_data': $value = $share_data; break;
                        }
                    }
                    $cell = $sheet->getCellByColumnAndRow($col, $row);
                    $cell->setValueExplicit($value, DataType::TYPE_STRING);
                    $col++;
                }
                $row++;
                $slno++;
            }
        }
    }
    
    // Set optimal column widths and row heights
    // After writing all data, set row heights
    for ($i = 1; $i <= $row; $i++) {
        $sheet->getRowDimension($i)->setRowHeight(40); // 40 points height for all rows
    }

    // For rows with multi-line content, set larger height
    foreach ($sheet->getRowDimensions() as $rowDimension) {
        $rowIndex = $rowDimension->getRowIndex();
        $cell = $sheet->getCell('A' . $rowIndex); // Check first cell in row
        
        if ($cell && strpos($cell->getValue(), "\n") !== false) {
            $lineCount = substr_count($cell->getValue(), "\n") + 1;
            $sheet->getRowDimension($rowIndex)->setRowHeight(min(40 * $lineCount, 150));
        }
    }

    // Set column widths to fit headers
    $headerWidths = [
        'slno' => 8,
        'company_name' => 25,
        'drive_no' => 12,
        'status' => 12,
        'form_open_date' => 18,
        'form_close_date' => 18,
        'progress' => 12,
        'drive_status' => 20,
        'applicants' => 12,
        'hired' => 10,
        'designation' => 25,
        'ctc' => 15,
        'stipend' => 15,
        'min_percentage' => 15,
        'offer_type' => 15,
        'job_sector' => 20,
        'eligible_courses' => 35,
        'form_link' => 40,
        'jd_link' => 40,
        'jd_files' => 40,
        'extra_details' => 30,
        'created_by' => 20,
        'share_data' => 50
    ];

    $col = 1;
    foreach ($exportFields as $field) {
        $width = $headerWidths[$field] ?? 20; // Default to 20 if not specified
        $sheet->getColumnDimensionByColumn($col)->setWidth($width);
        $col++;
    }
    
    // Freeze header row for easy scrolling
    $sheet->freezePane('A2');
    
    // ---- Robust XLSX streaming (replace your current header + $writer->save('php://output')) ----

// Turn off error display so no warnings are injected into the file
ini_set('display_errors', '0');
error_reporting(0);

// Clean (end) all active output buffers to avoid corrupting binary output
while (ob_get_level()) {
    ob_end_clean();
}

// Disable zlib output compression if enabled (can corrupt binary)
if (ini_get('zlib.output_compression')) {
    ini_set('zlib.output_compression', '0');
}

// Create a temporary file to write the spreadsheet (safer than direct php://output)
$tmpfname = tempnam(sys_get_temp_dir(), 'drives_') . '.xlsx';

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->setIncludeCharts(false);
$writer->setPreCalculateFormulas(false);

try {
    $writer->save($tmpfname);
} catch (Exception $e) {
    // Log error and exit cleanly
    error_log("Spreadsheet write failed: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo "Export failed. Check server logs.";
    exit;
}

// Now stream the temp file to the client with correct headers
$filesize = filesize($tmpfname);
header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="placement_drives_' . date('Y-m-d') . '.xlsx"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . $filesize);

// Read and output the file without extra buffering
$fp = fopen($tmpfname, 'rb');
if ($fp) {
    fpassthru($fp);
    fclose($fp);
}

// Remove the temporary file
@unlink($tmpfname);

// Ensure PHP stops here
exit;

}

// HTML for the export popup - include filter parameters as hidden fields
$filterParams = [
    'company_name' => $_POST['company_name'] ?? '',
    'status' => $_POST['status'] ?? '',
    'from_date' => $_POST['from_date'] ?? '',
    'to_date' => $_POST['to_date'] ?? ''
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Fields to Export</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: white;
        }
        .export-popup {
            background-color: white;
            border-radius: 8px;
            width: 100%;
            max-height: 100%;
            overflow-y: auto;
        }
        .popup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }
        .popup-header h2 {
            margin: 0;
            font-size: 1.2rem;
            color: #333;
        }
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        .popup-content {
            padding: 20px;
        }
        .field-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        .field-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        .field-option:hover {
            background-color: #f8f9fa;
        }
        .field-option input {
            margin: 0;
            width: 16px;
            height: 16px;
            cursor: pointer;
            align-self: center;
        }
        .field-option label {
            display: flex;
            align-items: center;
            cursor: pointer;
            user-select: none;
            font-size: 0.95rem;
            color: #495057;
            line-height: 1.2;
        }
        .action-buttons {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .select-all-btn {
            padding: 8px 12px;
            margin-right: 10px;
            background-color: #650000;
            color: #fff;
            border: 1px solid #650000;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .select-all-btn:hover {
            background-color: #f2f2f2;
            color: #650000;
        }
        .export-btn {
            padding: 8px 16px;
            border-radius: 4px;
            background-color: #f2f2f2;
            color: #650000;
            border: 1px solid #650000;
            cursor: pointer;
            margin-right:0%;
            transition: all 0.2s;
            width: 100%;
        }
        .export-btn:hover {
            background-color: #650000;
            color: #f2f2f2;
        }
        /* Scrollbar styling */
        .export-popup::-webkit-scrollbar {
            width: 8px;
        }
        .export-popup::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        .export-popup::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 4px;
        }
        .export-popup::-webkit-scrollbar-thumb:hover {
            background: #aaa;
        }
        /* Unified checkbox color */
        input[type="checkbox"] {
            accent-color: #650000;
        }
    </style>
</head>
<body>
    <div class="export-popup">
        <div class="popup-header">
            <h2>Select Fields to Export</h2>
<button class="close-btn" onclick="window.parent.closeExportPopup()">&times;</button>        </div>
        <div class="popup-content">
            <form method="post" id="exportForm">
                <!-- Pass filter parameters as hidden fields -->
                <input type="hidden" name="company_name" value="<?= htmlspecialchars($filterParams['company_name']) ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filterParams['status']) ?>">
                <input type="hidden" name="from_date" value="<?= htmlspecialchars($filterParams['from_date']) ?>">
                <input type="hidden" name="to_date" value="<?= htmlspecialchars($filterParams['to_date']) ?>">
                
                <div class="field-grid">
                    <div class="field-option select-all">
                        <input type="checkbox" id="select_all" onchange="toggleAllFields(this)">
                        <label for="select_all"><strong>Select All Fields</strong></label>
                   </div>
                    <div class="field-option">
                        <input type="checkbox" id="company_name" name="export_fields[]" value="company_name">
                        <label for="company_name">Company Name</label>
                    </div>
                    <div class="field-option">
                        <input type="checkbox" id="drive_no" name="export_fields[]" value="drive_no">
                        <label for="drive_no">Drive Number</label>
                    </div>
                    <div class="field-option">
                        <input type="checkbox" id="form_open_date" name="export_fields[]" value="form_open_date">
                        <label for="form_open_date">Form Open Date</label>
                    </div>
                    <div class="field-option">
                        <input type="checkbox" id="form_close_date" name="export_fields[]" value="form_close_date">
                        <label for="form_close_date">Form Close Date</label>
                    </div>
                    <!--<div class="field-option">
                        <input type="checkbox" id="status" name="export_fields[]" value="status">
                        <label for="status">Status</label>
                    </div>-->
                    <div class="field-option">
                        <input type="checkbox" id="progress" name="export_fields[]" value="progress">
                        <label for="progress">Progress (%)</label>
                    </div>
                    <div class="field-option">
                        <input type="checkbox" id="drive_status" name="export_fields[]" value="drive_status">
                        <label for="drive_status">Drive Status</label>
                    </div>
                    <div class="field-option">
                        <input type="checkbox" id="applicants" name="export_fields[]" value="applicants">
                        <label for="applicants">Applicants</label>
                    </div>
                    <div class="field-option">
                        <input type="checkbox" id="hired" name="export_fields[]" value="hired">
                        <label for="hired">Hired</label>
                    </div>
                    <div class="field-option">
                        <input type="checkbox" id="designation" name="export_fields[]" value="designation">
                        <label for="designation">Role</label>
                    </div>
                    <div class="field-option">
                        <input type="checkbox" id="ctc" name="export_fields[]" value="ctc">
                        <label for="ctc">CTC</label>
                    </div>
                    <div class="field-option">
                        <input type="checkbox" id="stipend" name="export_fields[]" value="stipend">
                        <label for="stipend">Stipend</label>
                    </div>
                    <div class="field-option">
                        <input type="checkbox" id="min_percentage" name="export_fields[]" value="min_percentage">
                        <label for="min_percentage">Min Percentage</label>
                    </div>
                    <div class="field-option">
                        <input type="checkbox" id="offer_type" name="export_fields[]" value="offer_type">
                        <label for="offer_type">Offer Type</label>
                    </div>
                    <div class="field-option">
                        <input type="checkbox" id="job_sector" name="export_fields[]" value="job_sector">
                        <label for="job_sector">Job Sector</label>
                    </div>
                    <div class="field-option">
                        <input type="checkbox" id="eligible_courses" name="export_fields[]" value="eligible_courses">
                        <label for="eligible_courses">Eligible Courses</label>
                    </div>
                    <div class="field-option">
                        <input type="checkbox" id="form_link" name="export_fields[]" value="form_link">
                        <label for="form_link">Form Link</label>
                    </div>
                    <div class="field-option">
                        <input type="checkbox" id="jd_link" name="export_fields[]" value="jd_link">
                        <label for="jd_link">JD Link</label>
                    </div>
                    <div class="field-option">
                        <input type="checkbox" id="jd_files" name="export_fields[]" value="jd_files">
                        <label for="jd_files">JD Files</label>
                    </div>
                    <div class="field-option">
                        <input type="checkbox" id="created_by" name="export_fields[]" value="created_by">
                        <label for="created_by">Created By</label>
                    </div>
                    <div class="field-option">
                        <input type="checkbox" id="extra_details" name="export_fields[]" value="extra_details">
                        <label for="extra_details">Extra Details</label>
                    </div>
                    <div class="field-option">
                        <input type="checkbox" id="share_data" name="export_fields[]" value="share_data">
                        <label for="share_data">WhatsApp shared message</label>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button type="submit" class="export-btn">Export selected fields</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const selectAllBtn = document.getElementById('selectAllBtn');
        const checkboxes = document.querySelectorAll('input[type="checkbox"]');
        function toggleAllFields(checkbox) {
    const checkboxes = document.querySelectorAll('#exportForm input[type="checkbox"]:not(#select_all)');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
        // Trigger change event for any dependent logic
        cb.dispatchEvent(new Event('change'));
    });
}
        
        function toggleSelectAll() {
            if (!selectAllBtn) return;
            const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
            const newState = !allChecked;
            checkboxes.forEach(checkbox => { checkbox.checked = newState; });
            updateSelectAllButton();
        }
        
        function updateSelectAllButton() {
            if (!selectAllBtn) return;
            const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
            selectAllBtn.textContent = allChecked ? 'Deselect All' : 'Select All';
        }
        
        function init() {
            updateSelectAllButton();
            
            if (selectAllBtn) {
                selectAllBtn.addEventListener('click', toggleSelectAll);
                checkboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', updateSelectAllButton);
                });
            }

            // Handle form submission with AJAX
            document.getElementById('exportForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const form = e.target;
                // Prevent submission if no fields selected
                const anyChecked = !!form.querySelector('input[name="export_fields[]"]:checked');
                if (!anyChecked) {
                    alert('Please select at least one field to export.');
                    return;
                }

                // Show loading indicator
                const exportBtn = document.querySelector('.export-btn');
                const originalText = exportBtn.textContent;
                exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
                exportBtn.disabled = true;

                // Get the form data
                const formData = new FormData(form);
                
                // Submit via fetch API
                fetch('export_dashboard', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.ok) {
                        return response.blob();
                    }
                    throw new Error('Export failed');
                })
                .then(blob => {
                    // Create download link
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'placement_drives_export.xlsx';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    // Close the popup
                    if (window.parent.closeExportPopup) {
                        window.parent.closeExportPopup();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Export failed: ' + error.message);
                })
                .finally(() => {
                    exportBtn.textContent = originalText;
                    exportBtn.disabled = false;
                });
            });
        }
        
        document.addEventListener('DOMContentLoaded', init);
    </script>
</body>
</html>