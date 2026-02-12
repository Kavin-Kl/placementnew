<?php
session_start();
include("config.php");

include('course_groups_dynamic.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_POST['upid']  = strtoupper(trim($_POST['upid'] ?? ''));
    $_POST['regno'] = strtoupper(trim($_POST['regno'] ?? ''));
}

// ----------------------------
// Create PDO connection
// ----------------------------
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// ----------------------------
// Handle AJAX request for student name
// ----------------------------
if (isset($_GET['action']) && $_GET['action'] === 'get_student_name') {
    $upid = $_GET['upid'] ?? '';
    $regno = $_GET['regno'] ?? '';
    $student_name = '';

    if ($upid && $regno) {
        try {
            $stmt = $pdo->prepare("SELECT student_name FROM students WHERE upid = ? AND reg_no = ?");
            $stmt->execute([$upid, $regno]);
            $student = $stmt->fetch();
            if ($student) $student_name = $student['student_name'];
        } catch (PDOException $e) {
            $student_name = '';
        }
    }

    echo json_encode(['student_name' => $student_name]);
    exit; // stop the rest of the page
}

// ----------------------------
// Fetch student name if form submitted normally
// ----------------------------
$student_name = '';
if (!empty($_POST['upid']) && !empty($_POST['regno'])) {
    try {
        $stmt = $pdo->prepare("SELECT student_name FROM students WHERE upid = ? AND reg_no = ?");
        $stmt->execute([$_POST['upid'], $_POST['regno']]);
        $student = $stmt->fetch();
        if ($student) {
            $student_name = $student['student_name'];
        }
    } catch (PDOException $e) {
        $student_name = '';
    }
}

$fields = [
    'personal' => [
        "Full Name", "First Name", "Middle Name", "Last Name",
        "Phone No", "Alternate Phone No", 
        "Email ID (please recheck the email before submitting)", "Alternate Email ID",
        "Gender", "DOB",
        "Hometown", "State", "District",
        "City (Currently Residing In)", "Pincode",
        "Current Address", "Permanent Address",
        "PAN No", "Aadhar No", "Passport No",
        "Nationality", "Category (General/OBC/SC/ST)", "Religion", "Blood Group",
        "Marital Status", "Father’s Name", "Mother’s Name",
        "Emergency Contact Name", "Emergency Contact Number"
    ],
    'education' => [
        "--- 10th Details ---",
        "10th grade %", "10th Board Name", "10th Year Of Passing", "10th School Name", "10th School Location",
        "--- 12th Details ---",
        "12th grade/PUC %", "12th Board Name", "12th Year Of Passing", "12th/PUC School Name", "12th/PUC Location", "12th Stream",
        "--- Diploma Details (If any) ---",
        "Diploma %", "Diploma Year Of Passing", "Diploma Specialization", "Diploma College Name", "Diploma University Name",
        "--- UG Details ---",
        "UG Degree", "UG Course Name", "UG %/CGPA", "UG Year of Passing", 
        "UG Stream/Specialization", "UG College Name", "UG College Location", 
        "UG University Name", "Active Backlogs (UG)?", "No. of Backlogs (UG)",
        "--- PG Details (If any) ---",
        "PG Degree", "PG Course Name", "PG %/CGPA", "PG Year of Passing", 
        "PG Stream/Specialization", "PG College Name", "PG College Location", 
        "PG University Name", "Active Backlogs (PG)?", "No. of Backlogs (PG)"
    ],
    'work' => [
        "--- Internship Details ---",
        "Have you completed any internship?", "No. of Months of Internship", "Name of Organization", 
        "Internship Role", "Internship Project Details", "Internship Location",
        "Certificate of Internship",
        "--- Full-time Experience ---",
        "Have Prior Full-time Experience?", "Company Name", "Designation", "Duration (Months)",
        "Job Role Description", "Last Drawn Salary (If any)", "Reason for Leaving (If any)",
             "--- Projects ---",
    "Project Title", "Project Description", "Technologies Used", 
    "GitHub/Project URL", "Upload Portfolio", "Upload Cover Letter",
    "LinkedIn Profile", "Dribbble/Behance Link",
  
     "--- Preferences ---",
    "Are you available in person for interview?", "Are you ok with relocation?",
    "Are you ok with shifts?","Willing to join Immediately ?", "Preferred Work Locations?",
    "Preferred Industry", "Preferred Job Role"

    ],
    'others' => [
        "--- Skillset & Certifications ---",
        "Key Skills", "Name of Certifications Completed", "Certifications Upload",
        "Technical Skills", "Programming Languages Known",
        "Languages Known (Read/Write/Speak)",
        "--- Documents Uploads ---",
        "Upload Photo", "Upload Academic Certificates",
        "Upload ID Proof", "Upload Signature",  "Additional Documents (You can upload multiple files Ex: Project, Portfolio)",
        "--- Declarations ---",
        "I hereby undertake that I will appear for the complete recruitment process of the above mentioned organization. In case I fail to appear for the same, my candidature for next companies stands cancelled. I also confirm that I have not been placed so far.",
        "I hereby declare that the above information is correct.",
        "Declaration of Authenticity",
        "Agree to Terms and Conditions"
    ]
];

$ALL_COURSES = array_merge($UG_COURSES, $PG_COURSES);

$normalized_official_courses = [];

foreach ($ALL_COURSES as $course) {
    $normalized = normalizeCourseName($course);
    $normalized_official_courses[$normalized] = $course;
}

// Get form_link from URL
$form_link = isset($_GET['form']) ? trim($_GET['form']) : '';

if ($form_link === '') {
 die("<div style='
  text-align: center; 
  font-family: Arial, Helvetica, sans-serif; 
  font-size: 20px; 
  color: #cc0000;
  margin-top: 50px;
'>Invalid form link.</div>");
}

// Query using form_link!
$stmt = $conn->prepare("SELECT * FROM drives WHERE form_link = ?");
if (!$stmt) {
    die("<div style='
      text-align: center;
      font-family: Arial, Helvetica, sans-serif;
      font-size: 20px;
      color: #cc0000;
      margin-top: 50px;
    '>Database error: " . htmlspecialchars($conn->error) . "</div>");
}
$stmt->bind_param("s", $form_link);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
 die("<div style='
  text-align: center;
  font-family: Arial, Helvetica, sans-serif;
  font-size: 20px;
  color: #cc0000;
  margin-top: 50px;
'>Invalid form link.</div>");
}

$drive = $res->fetch_assoc();

// Load custom form fields if configured for this drive
if (!empty($drive['form_fields'])) {
    $custom_config = json_decode($drive['form_fields'], true);
    if (!empty($custom_config['enabled_fields'])) {
        // Use custom enabled fields
        $fields = $custom_config['enabled_fields'];

        // Add custom fields to appropriate categories
        if (!empty($custom_config['custom_fields']) && is_array($custom_config['custom_fields'])) {
            foreach ($custom_config['custom_fields'] as $custom_field) {
                // Skip if custom_field is not an array
                if (!is_array($custom_field)) {
                    continue;
                }
                $category = $custom_field['category'] ?? 'others';
                $field_name = $custom_field['name'] ?? $custom_field;

                if (!isset($fields[$category])) {
                    $fields[$category] = [];
                }
                if (!is_array($fields[$category])) {
                    $fields[$category] = [];
                }
                $fields[$category][] = $field_name;
            }
        }
    }
}

date_default_timezone_set('Asia/Kolkata'); // ✅ Add this line to set India time
$now = date('Y-m-d H:i:s'); // ✅ Get full date and time (e.g., 2025-08-17 02:47:00)

if ($now < $drive['open_date']) {
    die("
    <div style='
        background-color: #fff8e1;
        border: 1px solid #fbbc04;
        color: #202124;
        font-weight: 600;
        text-align: center;
        font-size: 1.5rem;
        line-height: 1.6;
        padding: 30px 15px;
        border-radius: 8px;
        max-width: 90vw;
        margin: 40px auto 20px auto;
        font-family: Arial, sans-serif;
        box-sizing: border-box;
    '>
        This form is not open yet.<br><br>
        <span style='font-weight: normal; font-size: 1.4rem; color: #5f6368;'>
            The application window will open soon. Please check back later.
        </span>
    </div>
    ");
}

if ($now > $drive['close_date']) {
    die("
    <div style='
        background-color: #fce8e6;
        border: 1px solid #d93025;
        color: #202124;
        font-weight: 600;
        text-align: center;
        font-size: 1.5rem;
        line-height: 1.6;
        padding: 30px 15px;
        border-radius: 8px;
        max-width: 90vw;
        margin: 40px auto 20px auto;
        font-family: Arial, sans-serif;
        box-sizing: border-box;
    '>
        Sorry, this form is no longer accepting submissions.<br><br>
        <span style='font-weight: normal; font-size: 1.4rem; color: #5f6368;'>
            The application deadline has passed. Please contact the placement office for further information.
        </span>
    </div>
    ");
}

// ✅ If valid, show the form below...
?>

<?php
$courseOptions = $ALL_COURSES;

$extraDetails = json_decode($drive['extra_details'] ?? '', true);

// Fetch Roles
$rolesQuery = $conn->prepare("SELECT * FROM drive_roles WHERE drive_id = ?");
if (!$rolesQuery) {
    die("Database error fetching roles: " . htmlspecialchars($conn->error));
}
$rolesQuery->bind_param("i", $drive['drive_id']);
$rolesQuery->execute();
$rolesResult = $rolesQuery->get_result();
$roles = [];
while ($r = $rolesResult->fetch_assoc()) $roles[] = $r;


$errorUpid = $errorRegno = $errorCourse = $errorPercentage = $errorField = $successMsg = "";
$fieldTypePresets = [
  "Gender" => ["type" => "radio", "options" => ["Male", "Female", "Other"]],
  "Marital Status" => ["type" => "dropdown", "options" => ["Single", "Married", "Divorced", "Widowed"]],
  "Category (General/OBC/SC/ST)" => ["type" => "dropdown", "options" => ["General", "OBC", "SC", "ST"]],
  "Religion" => ["type" => "dropdown", "options" => ["Hindu", "Christian", "Muslim", "Sikh", "Jain", "Other"]],
  "Blood Group" => ["type" => "dropdown", "options" => ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"]],

  "Phone No" => ["type" => "tel", "pattern" => "[0-9]{10}", "placeholder" => "10-digit number"],
  "Alternate Phone No" => ["type" => "tel", "pattern" => "[0-9]{10}", "placeholder" => "10-digit number"],
  "Emergency Contact Number" => ["type" => "tel", "pattern" => "[0-9]{10}", "placeholder" => "10-digit number"],
 "Email ID (please recheck the email before submitting)" => [
  "type" => "email",
  "pattern" => "^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$",
  "placeholder" => "you@example.com",
  "title" => "Enter a valid email address (e.g. abc@example.com)"
],
"Alternate Email ID" => [
  "type" => "email",
  "pattern" => "^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$",
  "placeholder" => "optional@example.com",
  "title" => "Enter a valid email address"
],
"DOB" => ["type" => "date"],
"10th Year Of Passing"     => ["type" => "month"],
"12th Year Of Passing"     => ["type" => "month"],
"Diploma Year Of Passing"  => ["type" => "month"],
"UG Year of Passing"       => ["type" => "month"],
"PG Year of Passing"       => ["type" => "month"],

 
"Pincode" => [
    "type" => "text",
    "pattern" => "[1-9][0-9]{5}",
    "placeholder" => "6-digit PIN",
    "maxlength" => "6",
    "title" => "Enter a valid 6-digit Indian pincode"
  ],

  "Have you completed any internship?" => ["type" => "radio", "options" => ["Yes", "No"]],
  "Certificate of Internship" => ["type" => "file", "multiple" => true],
  "Have Prior Full-time Experience?" => ["type" => "radio", "options" => ["Yes", "No"]],
  "Are you ok with shifts?" => ["type" => "radio", "options" => ["Yes", "No"]],
  "Are you ok with relocation?" => ["type" => "radio", "options" => ["Yes", "No"]],
  "Are you available in person for interview?" => ["type" => "radio", "options" => ["Yes", "No"]],
  "Willing to join Immediately ?" => ["type" => "radio", "options" => ["Yes", "No"]],

 "Preferred Industry" => [
    "type" => "dropdown",
    "options" => [
        "Information Technology",
        "Artificial Intelligence & Machine Learning",
        "Software Development",
        "Cybersecurity",
        "Healthcare & Life Sciences",
        "Pharmaceuticals & Biotechnology",
        "Finance & Banking",
        "Investment Banking",
        "Fintech",
        "Insurance",
        "Accounting & Auditing",
        "Education & EdTech",
        "Research & Development",
        "Manufacturing",
        "Automotive",
        "Aerospace & Defense",
        "Energy & Power",
        "Renewable Energy & Sustainability",
        "Oil & Gas",
        "Construction & Infrastructure",
        "Real Estate",
        "Retail & E-commerce",
        "Consumer Goods",
        "FMCG",
        "Logistics & Supply Chain",
        "Transport & Shipping",
        "Travel & Hospitality",
        "Tourism",
        "Telecommunications",
        "Media & Entertainment",
        "Advertising & Branding",
        "Public Relations & Communications",
        "Market Research",
        "Legal Services",
        "Government & Public Sector",
        "NGO & Social Work",
        "Agriculture & AgroTech",
        "Food & Beverage",
        "Sports & Fitness",
        "Event Management",
        "Fashion & Apparel",
        "Interior Design",
        "Architecture & Urban Planning",
        "Consulting",
        "Human Resources & Recruitment",
        "Business Analytics",
        "Data Science & Big Data",
        "Blockchain & Web3",
        "Cloud Computing",
        "Gaming & Animation",
        "Space Technology",
        "Robotics",
        "Environmental Services",
        "Waste Management",
        "Marine & Oceanography",
        "Defense & Homeland Security",
        "Printing & Publishing"
    ]
],

//"Upload Resume" => ["type" => "file", "multiple" => true],

  "Upload Photo" => ["type" => "file", "multiple" => true],
  "Upload Portfolio" => ["type" => "file", "multiple" => true],
  "Upload Cover Letter" => ["type" => "file", "multiple" => true],
  "Certifications Upload" => ["type" => "file", "multiple" => true],
  "Upload Academic Certificates" => ["type" => "file", "multiple" => true],
  "Upload ID Proof" => ["type" => "file", "multiple" => true],
  "Upload Signature" => ["type" => "file", "multiple" => true],
  
 "Additional Documents (You can upload multiple files Ex: Project, Portfolio)" => ["type" => "file", "multiple" => true],
  "Languages Known (Read/Write/Speak)" => [
    "type" => "checkbox",
    "options" => ["English", "Hindi", "Kannada", "Tamil", "Telugu", "Malayalam", "Others"]
  ],

  "Active Backlogs (UG)?" => ["type" => "radio", "options" => ["Yes", "No"]],
  "Active Backlogs (PG)?" => ["type" => "radio", "options" => ["Yes", "No"]],

  "I hereby undertake that I will appear for the complete recruitment process of the above mentioned organization. In case I fail to appear for the same, my candidature for next companies stands cancelled. I also confirm that I have not been placed so far." => ["type" => "checkbox"],

  "I hereby declare that the above information is correct." => ["type" => "checkbox"],
  "Declaration of Authenticity" => ["type" => "checkbox"],
  "Agree to Terms and Conditions" => ["type" => "checkbox"]
];

$student = null;
$eligibleRoles = [];
$alreadyApplied = false;

//preee
// Normalize course names for comparison
// Normalize course names for comparison — letters only, case-insensitive

function normalizeCourseName($name) {
    $name = strtolower($name);
    $name = str_replace('&', 'and', $name);

    // Fix specific abbreviations: B.COM, B.SC, M.SC etc.
    $name = str_ireplace(['b.com', 'bcom', 'b. com'], 'bcom', $name);
    $name = str_ireplace(['b.sc', 'bsc', 'b. sc'], 'bsc', $name);
    $name = str_ireplace(['m.com', 'mcom', 'm. com'], 'mcom', $name);
    $name = str_ireplace(['m.sc', 'msc', 'm. sc'], 'msc', $name);
    $name = str_ireplace(['b.a', 'ba', 'b. a'], 'ba', $name);
    $name = str_ireplace(['m.a', 'ma', 'm. a'], 'ma', $name);
    $name = str_ireplace(['bba'], 'bba', $name);
    $name = str_ireplace(['mba'], 'mba', $name);

    // Remove all non-alphanumeric characters and normalize spaces
    $name = preg_replace('/[^a-z0-9]+/', ' ', $name);
    $name = preg_replace('/\s+/', ' ', $name);

    return trim($name);
}

//pree end
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    $upid = trim($_POST["upid"] ?? '');

    $regno = $_POST["regno"] ?? '';
    $course = trim($_POST["course"] ?? '');
    $percentageInput = trim($_POST["percentage"] ?? '');

    // Validate percentage
  if ($percentageInput === '' || !is_numeric($percentageInput)) {
    $errorPercentage = "Please enter a valid percentage.";
} else {
    $percentage = floatval($percentageInput);
    if ($percentage < 0 || $percentage > 100) {
        $errorPercentage = "Percentage must be between 0 and 100.";
    }
}

    $selectedRoles = $_POST["selected_roles"] ?? [];
    $priorities = $_POST["selected_roles_priority"] ?? [];

    // Validate UPID
    $checkUpid = $conn->prepare("SELECT * FROM students WHERE upid = ?");
    if (!$checkUpid) {
        $errorUpid = "Database error: " . htmlspecialchars($conn->error);
    } else {
        $checkUpid->bind_param("s", $upid);
        $checkUpid->execute();
        $resultUpid = $checkUpid->get_result();

    if ($resultUpid->num_rows === 0) {
    $errorUpid = "Invalid Placement ID.";
} else {
    $student = $resultUpid->fetch_assoc();

    // Manually compare to handle case and space issues
    if (strcasecmp(trim($student['upid']), $upid) !== 0) {
        $errorUpid = "Invalid Placement ID.";
    } else {
        $studentId = $student['student_id'];


        
        // Validate Regno
if (strcasecmp(trim($student['reg_no']), trim($regno)) !== 0) {
    $errorRegno = "Register No does not match with Placement ID.";
}

elseif (strtolower($student['placed_status']) === "blocked") {
    $errorRegno = "You are blocked and not eligible.";
} elseif (strtolower($student['placed_status']) === "placed" && strtolower($student['allow_reapply']) !== "yes") {
    // Check if the placement is an internship - if so, allow applying for full-time roles
    $placement_check = $conn->prepare("
        SELECT dr.offer_type
        FROM placed_students ps
        JOIN drive_roles dr ON ps.role_id = dr.role_id
        WHERE ps.student_id = ?
        LIMIT 1
    ");
    $placement_check->bind_param("i", $student['student_id']);
    $placement_check->execute();
    $placement_result = $placement_check->get_result();

    if ($placement_result->num_rows > 0) {
        $placement_data = $placement_result->fetch_assoc();
        $current_offer_type = $placement_data['offer_type'];

        // Only block if placed in Full-time, Internship+PPO, or Apprentice
        // Allow if placed in Internship only
        if ($current_offer_type !== 'Internship') {
            $errorRegno = "You are already placed (" . htmlspecialchars($current_offer_type) . ") and not eligible to apply for other roles.";
        }
        // If Internship, allow them to continue (no error)
    } else {
        // No placement record found, block as precaution
        $errorRegno = "You are already placed and not eligible.";
    }
}
// 🚫 Check if already placed off-campus
elseif (isset($student['Offcampus_selection']) && strtolower(trim($student['Offcampus_selection'])) === 'placed') {
    $errorRegno = "You are already placed off campus and cannot apply.";
}

// ----- COURSE VALIDATION using course_groups.php -----
// ----- COURSE VALIDATION using ALL_COURSES from course_groups.php -----
// ----- COURSE VALIDATION using course_groups.php -----
if (empty($errorUpid) && empty($errorRegno)) {
   $normalizedSelected = normalizeCourseName($course);
$normalizedActual   = normalizeCourseName($student['course'] ?? '');

if ($normalizedSelected !== $normalizedActual) {
    $errorCourse = "Your course entered is wrong. Actual course: " . htmlspecialchars($student['course'] ?? '');
    $eligibleRoles = [];
    goto skipEligibilityCheck;
}

}






        // Check if already applied for this drive
        $checkApplication = $conn->prepare("SELECT 1 FROM applications WHERE student_id = ? AND drive_id = ?");
        if (!$checkApplication) {
            $errorField = "Database error: " . htmlspecialchars($conn->error);
        } else {
            $checkApplication->bind_param("ii", $studentId, $drive['drive_id']);
            $checkApplication->execute();
            $checkApplication->store_result();

            if ($checkApplication->num_rows > 0) {
                $errorField = "You have already submitted the application for this Company.";
            }
        }
        if (!empty($errorField)) {
    $eligibleRoles = [];
}

    }
    }
    // If UPID, Regno, Percentage Valid
    // If UPID, Regno, Percentage Valid
    
    //pree chnaged
    skipEligibilityCheck:
    if (empty($errorUpid) && empty($errorRegno) && empty($errorPercentage) && empty($errorCourse)) {
    foreach ($roles as $r) {
            $eligibleCourses = json_decode($r['eligible_courses'], true);
            if (!is_array($eligibleCourses)) $eligibleCourses = [];

            foreach ($eligibleCourses as $ec) {
                if (strcasecmp(trim($course), trim($ec)) === 0 && $percentage >= floatval($r['min_percentage'])) {
                    $eligibleRoles[] = $r;
                    break;
                }
            }
        }
//pree chnaged end
       if (empty($eligibleRoles)) {
    $allCourses = [];
    foreach ($roles as $r) {
        $eligibleCourses = json_decode($r['eligible_courses'], true);
        if (is_array($eligibleCourses)) {
            $allCourses = array_merge($allCourses, array_map('trim', $eligibleCourses));
        }
    }
//pree chnaged 
 if (!in_array(trim($course), $allCourses)) {
        $errorCourse = "Your course is not eligible for the role.";
    } else {
        $errorPercentage = "Your percentage does not meet the minimum required for any eligible role.";
    }
}
//pree chnaged end

        $priorityValues = array_values($priorities);
        $nonEmptyPriorities = array_filter($priorityValues, fn($p) => $p !== '');

        if (count($nonEmptyPriorities) !== count(array_unique($nonEmptyPriorities))) {
            $errorField = "Each selected role must have a unique priority.";
        }

        // On Final Submit
        if (isset($_POST["final_submit"]) && empty($errorCourse) && empty($errorPercentage) && empty($errorField)) {

            $fieldData = [];
            $resumePath = "";

            // Prepare basic Full Name fallback first
            $fullName = $student['student_name'];

            // Resume Upload
    if (empty($errorField) && isset($_FILES["resume"]) && $_FILES["resume"]["error"] === 0) {
    $fileType = mime_content_type($_FILES["resume"]["tmp_name"]);
  $allowedTypes = [
    'application/pdf',
    'application/msword', // .doc
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' // .docx
];

if (!in_array($fileType, $allowedTypes)) {
    $errorField = "Only PDF, DOC, and DOCX files are allowed for Resume.";
} elseif ($_FILES["resume"]["size"] > 100 * 1024 * 1024) {
    $errorField = "Resume file size should be less than 100MB.";
} else {
    $studentNameSanitized = preg_replace('/[^a-zA-Z0-9]/', '_', $fullName);
    $ext = pathinfo($_FILES["resume"]["name"], PATHINFO_EXTENSION);  // get original extension
    $namePrefix = $studentNameSanitized . "resume";
    $resumeName = $namePrefix . uniqid() . "." . $ext;
    $targetPath = "uploads/resumes/" . $resumeName;

    // Create directory if it doesn't exist
    if (!is_dir("uploads/resumes/")) {
        mkdir("uploads/resumes/", 0755, true);
    }

    // Delete old resume files for this student
    $old_files = glob("uploads/resumes/" . $namePrefix . "*");
    foreach ($old_files as $old_file) {
        if (file_exists($old_file)) {
            unlink($old_file);
        }
    }

    if (move_uploaded_file($_FILES["resume"]["tmp_name"], $targetPath)) {
        $resumePath = $targetPath;
        $fieldData['Resume'] = $resumePath;
    } else {
        $errorField = "Failed to upload Resume file. Please try again.";
    }
}

}

            $additionalDocPaths = [];
            if (empty($errorField) && isset($_FILES["additional_docs"])) {
                foreach ($_FILES["additional_docs"]["tmp_name"] as $index => $tmpPath) {
                    if ($_FILES["additional_docs"]["error"][$index] === 0 && is_uploaded_file($tmpPath)) {
                        $originalName = basename($_FILES["additional_docs"]["name"][$index]);
                        $uniqueName = uniqid("doc_") . "_" . $originalName;
                        $targetPath = "uploads/" . $uniqueName;
                        move_uploaded_file($tmpPath, $targetPath);
                       $additionalDocPaths[] = $targetPath;

                    }
                }
            }

           //pret $formFieldsJson = $eligibleRoles[0]['form_fields'] ?? '[]';
      //  $formFields = json_decode($formFieldsJson, true) ?: [];

        // ✅ Get common form fields from the drives table instead of roles
$formFieldsJson = $drive['form_fields'] ?? '[]';
$formFields = json_decode($formFieldsJson, true) ?: [];

            $fieldDataCore = [];

            foreach ($formFields as $field) {
                $fname = preg_replace('/[^a-zA-Z0-9]/', '', $field['name']);
                $inputName = "field_$fname";
                $preset = $fieldTypePresets[$field['name']] ?? null;

                if ($preset && $preset['type'] === 'file') {
                    $fieldFiles = $_FILES[$inputName] ?? null;
                    $uploadedPaths = [];

                    if ($fieldFiles && isset($fieldFiles['tmp_name'])) {
                        $count = is_array($fieldFiles['tmp_name']) ? count($fieldFiles['tmp_name']) : 1;

                        for ($i = 0; $i < $count; $i++) {
                            $tmp = is_array($fieldFiles['tmp_name']) ? $fieldFiles['tmp_name'][$i] : $fieldFiles['tmp_name'];
                            $name = is_array($fieldFiles['name']) ? $fieldFiles['name'][$i] : $fieldFiles['name'];

                            if ($tmp && is_uploaded_file($tmp)) {
                                $fullName = $fieldDataCore['Full Name'] ?? $student['student_name'];
                                $studentNameSanitized = preg_replace('/[^a-zA-Z0-9]/', '_', $fullName);
                                $fieldNameSanitized = preg_replace('/[^a-zA-Z0-9]/', '_', $field['name']);

                                $fullName = $fieldDataCore['Full Name'] ?? $student['student_name'];
$studentNameSanitized = preg_replace('/[^a-zA-Z0-9]/', '_', $fullName);
$fieldNameSanitized = preg_replace('/[^a-zA-Z0-9]/', '_', $field['name']);
$originalName = pathinfo($name, PATHINFO_FILENAME);
$originalExt = pathinfo($name, PATHINFO_EXTENSION);
$originalNameSanitized = preg_replace('/[^a-zA-Z0-9]/', '_', $originalName);

$newFileName = "{$studentNameSanitized}_{$fieldNameSanitized}_{$originalNameSanitized}." . strtolower($originalExt);
$targetPath = "uploads/" . $newFileName;

if (move_uploaded_file($tmp, $targetPath)) {
    $uploadedPaths[] = $targetPath;
}

                            }
                        }

                        if (!empty($uploadedPaths)) {
                            $fieldDataCore[$field['name']] = count($uploadedPaths) === 1 ? $uploadedPaths[0] : $uploadedPaths;
                        }
                    }
                } else {
                    $value = isset($_POST[$inputName]) ? (is_array($_POST[$inputName]) ? implode(", ", $_POST[$inputName]) : $_POST[$inputName]) : '';
                    if ($field['mandatory'] && empty($value)) {
                        $errorField = "Field '{$field['name']}' is mandatory.";
                        break;
                    }
                    $fieldDataCore[$field['name']] = $value;
                }
            }

            if ($resumePath) $fieldDataCore['Resume'] = $resumePath;
            if (!empty($additionalDocPaths)) $fieldDataCore['Additional Documents'] = $additionalDocPaths;

            $fullName = $fieldDataCore['Full Name'] ?? $student['student_name'];
            $studentNameSanitized = preg_replace('/[^a-zA-Z0-9]/', '_', $fullName);

            if (!empty($_POST['selected_roles'])) {
                foreach ($_POST['selected_roles'] as $roleId => $value) {
                    $priority = $_POST['selected_roles_priority'][$roleId] ?? 1;

                    $checkQuery = $conn->prepare("SELECT 1 FROM applications WHERE student_id = ? AND drive_id = ? AND role_id = ?");
                    if (!$checkQuery) {
                        error_log("Database error in form_generator checkQuery: " . $conn->error);
                        continue;
                    }
                    $checkQuery->bind_param("iii", $studentId, $drive['drive_id'], $roleId);
                    $checkQuery->execute();
                    $checkQuery->store_result();

                    if ($checkQuery->num_rows > 0) continue;

                    $fieldData = $fieldDataCore;
                    $fieldData['UPID'] = $upid;
                    $fieldData['Register No'] = $regno;
                    $fieldData['Course'] = $course;
                    $fieldData['Percentage'] = $percentage;
                    $fieldData['Priority'] = $priority;

                    $fieldJson = json_encode($fieldData);

                  $insert = $conn->prepare("INSERT INTO applications
    (student_id, drive_id, role_id, percentage, course, priority, student_data, upid, reg_no)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

                    if (!$insert) {
                        error_log("Database error in form_generator INSERT: " . $conn->error);
                        continue;
                    }

                    $insert->bind_param("iiissssss",
                        $studentId, $drive['drive_id'], $roleId,
                        $percentage, $course, $priority, $fieldJson, $upid, $regno);

                    $insert->execute();
                }
            }

$companyName = htmlspecialchars($drive['company_name'] ?? 'the company');
$fullName = htmlspecialchars($student['student_name'] ?? 'Applicant');
$successMsg = "
<div style='
    background-color: #e6f4ea;
    border: 1px solid #34a853;
    color: #202124;
    font-weight: 600;
    text-align: center;
    font-size: 1.5rem;          /* bigger and scalable */
    line-height: 1.6;
    padding: 30px 15px;         /* more padding for small screens */
    border-radius: 8px;
    max-width: 90vw;            /* max 90% of viewport width */
    margin: 40px auto 20px auto;
    font-family: Arial, sans-serif;
    box-sizing: border-box;     /* make padding included in width */
'>
    Dear <span style='color: #1a73e8; text-transform: capitalize;'>$fullName</span>,<br><br>
    Your application has been successfully submitted for <span style='color: #1a73e8; text-transform: uppercase;'>$companyName</span>.<br><br>
    <span style='font-weight: normal; font-size: 1.4rem; color: #5f6368;'>Thank you for applying. We look forward to reviewing your profile.</span>
</div>
";

echo $successMsg;
exit;


            }
            
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=places"></script>

    <title>Application Form - <?= htmlspecialchars($drive['company_name']) ?></title>
    <style>
    *,
*::before,
*::after {
  box-sizing: border-box;
}
body {
  font-family: 'Roboto', sans-serif;
background: #f1f3f4;
  margin: 0;
 padding: 20px 10px;
  display: flex;
  justify-content: center;
  overflow-x: hidden;
}
img, iframe {
  max-width: 100%;
  height: auto;
}
.priority-input {
  max-width: 100%;
}

.form-container {
  background: white;
  border-radius: 8px;
  padding: 30px;
 width: 90%;
  max-width: 500px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
  border-top: 6px solid #4285f4;
}
input[type="submit"] {
  width: 100%; /* so button is large & easy to tap */
   margin-top: 15px;
}
h2 {
  text-align: left;
  font-size: 20px; /* smaller */
  font-weight: 500;
  margin-bottom: 20px;
  color: #202124;
}

.announcement {
  background: white;
  border-radius: 4px;
  padding: 15px;
  margin-bottom: 20px;
  border: 1px solid #dadce0;
  color: #202124;
  font-size: 14px;
  line-height: 1.4;
}

.announcement h4 {
  margin: 0 0 5px 0;
  font-weight: 500;
}
.announcement p {
  word-break: break-word;
}

.field-group {
  background: white;
  border: 1px solid #dadce0;
  border-radius: 4px;
  padding: 15px;
  margin-bottom: 15px;
}

label {
  font-size: 13px;
  font-weight: 500;
  color: #202124;
  display: block;
  margin-bottom: 5px;
}
h4 {
  font-weight: normal;
}
input[type="text"],
input[type="number"],
input[type="file"],
input[type="email"],
input[type="tel"],
input[type="date"],
select,
textarea {
  width: 100%;
  border: none;
  border-bottom: 1px solid #dadce0;
  padding: 6px 0;
  background: transparent;
  outline: none;
 font-size: 14px;
  color: #202124;
}

input[type="text"]:focus,
input[type="number"]:focus,
input[type="file"]:focus,
input[type="email"]:focus,
input[type="tel"]:focus,
input[type="date"]:focus,
select:focus,
textarea:focus {
  border-color: #4285f4;
}

input[type="submit"] {  background:rgb(101, 22, 218);
  color: white;
  border: none;
  padding: 10px 20px;
  font-size: 13px;
  border-radius: 4px;
  cursor: pointer;
  transition: background 0.3s;
}

input[type="submit"]:hover {
  background: #3367d6;
}

.error {
  color: red;
  margin-bottom: 10px;
  font-weight: 500;
  font-size: 13px;
}

.priority-input {
  width: auto;
  padding: 4px 8px;
  font-size: 12px;
  border: 1px solid #dadce0;
  border-radius: 4px;
}
.announcement p {
  word-break: break-word;  /* force long words/strings to break */
  white-space: pre-wrap;   /* preserve line breaks if any */
}
.role-label {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 14px;
  line-height: 1.4;
  word-break: break-word;
  white-space: normal;
  flex-wrap: wrap;
}
.role-label input[type="checkbox"] {
  margin-top: 3px;
}
.role-label span {
  max-width: 90%;
}
/* Ensures long URLs don't overflow their container */
.form-container a, 
.form-container li a {
    word-break: break-word;      /* better than break-all for URLs */
    overflow-wrap: anywhere;     /* allows breaking anywhere if too long */
    display: inline-block;       /* keeps link inside li */
    max-width: 100%;             /* never exceed container width */
    white-space: normal;         /* allow wrapping to multiple lines */
}

</style>
</head>
<body>

<div class="form-container">
     <h2>Application Form - <?= htmlspecialchars($drive['company_name']) ?></h2>
    <div class="announcement">
       <h4><strong>Dear All,</strong></h4>
        <h4><strong>Greetings from the Placement Cell !</strong></h4>

      
       
 <h4><strong>Application form for company- </strong><?= htmlspecialchars($drive['company_name']) ?></h4>
     <?php if (empty($errorField) && !empty($eligibleRoles)): ?>

   <hr><br>
        <h4><strong>Role Details:</strong></h4>
      
        <?php foreach ($eligibleRoles as $index => $role): ?>
            <p><strong>ROLE <?= $index + 1 ?>:</strong></p>
            <ul>
                <li><strong>Designation Name:</strong> <?= htmlspecialchars($role['designation_name']) ?></li>
                <?php if (!empty($role['ctc'])): ?>
                    <li><strong>CTC:</strong> <?= htmlspecialchars($role['ctc']) ?></li>
                <?php endif; ?>
                <?php if (!empty($role['stipend'])): ?>
                    <li><strong>Stipend:</strong> <?= htmlspecialchars($role['stipend']) ?></li>
                <?php endif; ?>

                
              <?php 
if (!empty($role['eligible_courses'])): ?>
    <li><strong>Eligible Courses:</strong> 
        <?php 
            $courses = json_decode($role['eligible_courses'], true);

            if (is_array($courses)) {
                $courses = array_map('trim', $courses); // clean spacing

                $ugMatch = empty(array_diff($UG_COURSES, $courses)); // all UG present
                $pgMatch = empty(array_diff($PG_COURSES, $courses)); // all PG present
                $extra   = array_diff($courses, array_merge($UG_COURSES, $PG_COURSES)); // extra custom courses

                if ($ugMatch && $pgMatch && empty($extra)) {
                    echo "All UG Courses, All PG Courses";
                } elseif ($ugMatch && empty($extra)) {
                    echo "All UG Courses";
                } elseif ($pgMatch && empty($extra)) {
                    echo "All PG Courses";
                } else {
                    echo implode(", ", $courses);
                }
            } else {
                echo htmlspecialchars($role['eligible_courses']);
            }
        ?>
        
    </li>
<?php endif; ?>

                <?php if (!empty($role['min_percentage'])): ?>
                    <li><strong>Minimum Percentage:</strong> <?= htmlspecialchars($role['min_percentage']) ?>%</li>
                <?php endif; ?>
                <?php if (!empty($role['offer_type'])): ?>
                    <li><strong>Offer Type:</strong> <?= htmlspecialchars($role['offer_type']) ?></li>
                <?php endif; ?>
                <?php if (!empty($role['sector'])): ?>
                    <li><strong>Job Sector:</strong> <?= htmlspecialchars($role['sector']) ?></li>
                <?php endif; ?>
                <?php if (!empty($role['work_timings'])): ?>
    <li><strong>Work Timings:</strong> <?= htmlspecialchars($role['work_timings']) ?></li>
<?php endif; ?>

            </ul>
         
            
        <?php endforeach; ?>
        
    
<?php endif; ?>

<hr>


  <?php if ($extraDetails): ?>
  

  <?php
  
$defaultKeys = [
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
  'whatsapp',
  'additionalInfo'
];

$defaultLabels = [
  'ctcDetails' => 'CTC Details',
  'workMode' => 'Mode of Work ',
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

?>

<?php if (!empty($eligibleRoles) && empty($errorField) && $extraDetails): ?>
<?php foreach ($defaultKeys as $key): ?>
    <?php if (!empty($extraDetails[$key])): ?>
        <p><strong><?= $defaultLabels[$key] ?>:</strong> <?= nl2br(htmlspecialchars($extraDetails[$key])) ?></p>
    <?php endif; ?>
<?php endforeach; ?>

        <?php
        // Step 2: Any extra fields (shown last)
        foreach ($extraDetails as $key => $val):
            if (!in_array($key, $defaultKeys) && !empty($val)):
                ?>
                <p><strong><?= ucfirst($key) ?>:</strong> <?= nl2br(htmlspecialchars($val)) ?></p>
                <?php
            endif;
        endforeach;
        ?>
         <?php if (!empty($drive['company_url'])): ?>
    <p><strong>Company URL:</strong> <a href="<?= htmlspecialchars($drive['company_url']) ?>" target="_blank"><?= htmlspecialchars($drive['company_url']) ?></a></p>
<?php endif; ?>

<?php if (!empty($drive['graduating_year'])): ?>
    <p><strong>Graduating Year:</strong> <?= htmlspecialchars($drive['graduating_year']) ?></p>
<?php endif; ?>

<?php if (!empty($drive['work_location'])): ?>
    <p><strong>Work Location:</strong> <?= htmlspecialchars($drive['work_location']) ?></p>
<?php endif; ?>

<?php
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
          . "://{$_SERVER['HTTP_HOST']}" . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . "/";
?>

        <?php
        
// Display JD Files if any
if (!empty($drive['jd_file'])):
    $jdFiles = json_decode($drive['jd_file'], true);
    if (is_array($jdFiles) && count($jdFiles) > 0):
?>

    <p><strong>JD Files:</strong></p>
    <ul>
        <?php foreach ($jdFiles as $file): ?>
  <li>
    <a href="<?= htmlspecialchars($base_url . ltrim($file, '/')) ?>" target="_blank">
        <?= htmlspecialchars($base_url . ltrim($file, '/')) ?>
    </a>
</li>

<?php endforeach; ?>

    </ul>
<?php
    endif;
endif;

// Display JD Link if provided
if (!empty($drive['jd_link'])):
?>
    <p><strong>JD Link:</strong> <a href="<?= htmlspecialchars($drive['jd_link']) ?>" target="_blank"><?= htmlspecialchars($drive['jd_link']) ?></a></p>
<?php endif; ?>

    </div>
    
<?php endif; ?>
 
     <?php endif; ?>
   
 <!-- ✅ close the extraDetails if block -->
  

    <?php if ($successMsg): ?>
        <div style="color:green; font-weight:bold;"><?= $successMsg ?></div>
    <?php endif; ?>

    <?php if ($errorUpid) echo "<div class='error'>$errorUpid</div>"; ?>
    <?php if ($errorRegno) echo "<div class='error'>$errorRegno</div>"; ?>
    <?php if ($errorCourse) echo "<div class='error'>$errorCourse</div>"; ?>
    <?php if ($errorPercentage) echo "<div class='error'>$errorPercentage</div>"; ?>
    <?php if ($errorField) echo "<div class='error'>$errorField</div>"; ?>

 

    <form method="POST" enctype="multipart/form-data">
        <div class="field-group">
            <label>Placement ID:</label>
            <input type="text" name="upid" value="<?= $_POST['upid'] ?? '' ?>" required>
        </div>

        <div class="field-group">
            <label>Register No:</label>
            <input type="text" name="regno" value="<?= $_POST['regno'] ?? '' ?>" required>
        </div>
<div class="field-group">
    <label>Student Name:</label>
    <input type="text" id="student_name" name="student_name" value="<?= htmlspecialchars($student_name ?? '') ?>" readonly>
</div>

     <div class="field-group">
  <label>Your Current Course:</label>
  <select name="course" required>
      <option value="">-- Select Course --</option>
      <?php foreach ($courseOptions as $course): ?>
          <option value="<?= htmlspecialchars($course) ?>" <?= ($_POST['course'] ?? '') === $course ? 'selected' : '' ?>>
              <?= htmlspecialchars($course) ?>
          </option>
      <?php endforeach; ?>
  </select>
</div>





        <div class="field-group">
            <label>Your current course percentage (aggregate) (%):</label>
            <input type="number" step="0.01" name="percentage" value="<?= $_POST['percentage'] ?? '' ?>" required>
        </div>

        
       <?php if (!empty($eligibleRoles) && empty($errorField)): ?>
        
            <h4>Select Roles (mark priority for each selected role):</h4>
            <?php foreach ($eligibleRoles as $role): ?>
                <div class="field-group" style="border:1px solid #ddd; padding:10px; margin-bottom:10px;">
                   <?php
$ctc = trim($role['ctc']);
$stipend = trim($role['stipend']);

$ctcDisplay = ($ctc !== '') ? "<strong>CTC:</strong> ". htmlspecialchars($ctc) : '';
$stipendDisplay = ($stipend !== '') ? "<strong>Stipend:</strong> " . htmlspecialchars($stipend) : '';

$roleInfo = '';
if ($ctcDisplay && $stipendDisplay) {
    $roleInfo = "$ctcDisplay, $stipendDisplay";
} elseif ($ctcDisplay) {
    $roleInfo = $ctcDisplay;
} elseif ($stipendDisplay) {
    $roleInfo = $stipendDisplay;
} else {
    $roleInfo = "Not Provided";
}
?>
<label class="role-label">
    <input type="checkbox" name="selected_roles[<?= $role['role_id'] ?>]" value="<?= $role['role_id'] ?>" <?= isset($_POST['selected_roles'][$role['role_id']]) ? 'checked' : '' ?>>
    <strong>Role:</strong> <?= htmlspecialchars($role['designation_name']) ?>

</label>
<?php if (count($eligibleRoles) > 1): ?>
                    
                    <br>
<label for="priority_<?= $role['role_id'] ?>">Priority:</label>
<select 
    id="priority_<?= $role['role_id'] ?>" 
    name="selected_roles_priority[<?= $role['role_id'] ?>]" 
    class="priority-input"
    <?= isset($_POST['selected_roles'][$role['role_id']]) ? 'required' : '' ?>
>
    <option value="">--Select--</option>
    <?php for ($p = 1; $p <= count($eligibleRoles); $p++): ?>
        <option value="<?= $p ?>" <?= ($_POST['selected_roles_priority'][$role['role_id']] ?? '') == $p ? 'selected' : '' ?>>
            <?= $p ?>
        </option>
                    <?php endfor; ?>
                </select>
            <?php endif; ?>

        </div>
    <?php endforeach; ?>

<?php
// Display common form fields
// Display common form fields

//preet $commonFields = json_decode($eligibleRoles[0]['form_fields'], true);
// ✅ Get common form fields from the selected drive
$formFieldsJson = $drive['form_fields'] ?? '[]';  // <<< ensures it exists
$formFields = json_decode($formFieldsJson, true) ?: [];

// Use $formFields in rendering
$commonFields = $formFields;


if (!empty($commonFields)):
     //prreeeethn start--// ===== SORT FIELDS ACCORDING TO MASTER ORDER =====
// ===== SORT FIELDS ACCORDING TO MASTER ORDER AND SECTIONS =====
// ===== SORT FIELDS ACCORDING TO MASTER ORDER AND SECTIONS =====
$sectionOrder = ['personal','education','work','others'];

// Assign section to each field if not already

foreach ($commonFields as &$f) {
    if (!isset($f['section'])) {
        $assigned = false;
        foreach ($sectionOrder as $sec) {
            // Check if 'name' key exists and if $fields[$sec] exists
            if (isset($f['name']) && isset($fields[$sec]) && in_array($f['name'], $fields[$sec])) {
                $f['section'] = $sec;
                $assigned = true;
                break;
            }
        }
        if (!$assigned) $f['section'] = 'personal';
    }
}
unset($f);

// Sort fields by section first, then by order inside section
usort($commonFields, function($a, $b) use ($fields, $sectionOrder) {
    // Keep Resume last
    if (isset($a['name']) && $a['name'] === 'Upload Resume (PDF)') return 1;
    if (isset($b['name']) && $b['name'] === 'Upload Resume (PDF)') return -1;

    $sectionA = $a['section'] ?? 'others';
    $sectionB = $b['section'] ?? 'others';

    $secIndexA = array_search($sectionA, $sectionOrder);
    $secIndexB = array_search($sectionB, $sectionOrder);

    if ($secIndexA !== $secIndexB) return $secIndexA - $secIndexB;

    // Check if 'name' key exists and section exists in $fields array
    $orderA = (isset($a['name']) && isset($fields[$sectionA])) ? array_search($a['name'], $fields[$sectionA]) : false;
    $orderB = (isset($b['name']) && isset($fields[$sectionB])) ? array_search($b['name'], $fields[$sectionB]) : false;

    $orderA = ($orderA === false) ? 999 : $orderA;
    $orderB = ($orderB === false) ? 999 : $orderB;

    return $orderA - $orderB;
});

   //prreeeethn enddd--


   
    echo "<h4>Form Fields:</h4>";
    foreach ($commonFields as $field):
        // Skip fields without 'name' key
        if (!isset($field['name'])) {
            continue;
        }

        $fname = preg_replace('/[^a-zA-Z0-9]/', '', $field['name']);
        $inputName = "field_$fname";
        $required = (isset($field['mandatory']) && $field['mandatory']) ? 'required' : '';
        $preset = $fieldTypePresets[$field['name']] ?? null;
?>
<div class="field-group">
    <label><?= $field['name'] ?> <?= (isset($field['mandatory']) && $field['mandatory']) ? '<span style="color:red;">*</span>' : '' ?></label>
    <?php
    if ($preset) {
switch ($preset['type']) {
    case "radio":
        if (isset($preset['options'])) {
            foreach ($preset['options'] as $opt) {
                echo "<label><input type='radio' name='$inputName' value='".htmlspecialchars($opt)."' $required> ".htmlspecialchars($opt)."</label><br>";
            }
        }
        break;

    case "checkbox":
        if (isset($preset['options'])) {
            foreach ($preset['options'] as $opt) {
                echo "<label><input type='checkbox' name='{$inputName}[]' value='".htmlspecialchars($opt)."'> ".htmlspecialchars($opt)."</label><br>";
            }
        } else {
            // Single checkbox (like declaration)
         echo "<label><input type='checkbox' name='{$inputName}' value='Yes' $required> I Agree</label>";

        }
        break;

    case "dropdown":
        echo "<select name='$inputName' $required>";
        echo "<option value=''>--Select--</option>";
        if (isset($preset['options'])) {
            foreach ($preset['options'] as $opt) {
                echo "<option value='".htmlspecialchars($opt)."'>".htmlspecialchars($opt)."</option>";
            }
        }
        echo "</select>";
        break;

   case "file":
    $multipleAttr = isset($preset['multiple']) ? "multiple" : "";
    $arraySuffix = isset($preset['multiple']) ? "[]" : "";
    echo "<input type='file' name='{$inputName}{$arraySuffix}' accept='.pdf,.doc,.docx,.jpg,.jpeg,.png,.zip' $multipleAttr $required>";
    break;
    case "date":
        case "month":
    case "email":
    case "tel":
        $placeholder = $preset['placeholder'] ?? '';
        $pattern = $preset['pattern'] ?? '';
        echo "<input type='{$preset['type']}' name='$inputName' $required" .
             ($placeholder ? " placeholder='$placeholder'" : '') .
             ($pattern ? " pattern='$pattern'" : '') .
             ">";
        break;

    default:
        echo "<input type='text' name='$inputName' $required>";
}

  } elseif (!empty($field['options'])) {
    $optionsArray = array_map('trim', explode(',', $field['options']));
    $optionsArray = array_filter($optionsArray); // remove empty

    if (count($optionsArray) === 1) {
        // Single value (like "Yes") → Checkbox
        echo "<label><input type='checkbox' name='{$inputName}[]' value='".htmlspecialchars($optionsArray[0])."' $required> ".htmlspecialchars($optionsArray[0])."</label>";
    } elseif (count($optionsArray) === 2) {
        // 2 options → Dropdown
        echo "<select name='$inputName' $required>";
        echo "<option value=''>--Select--</option>";
        foreach ($optionsArray as $opt) {
            echo "<option value='".htmlspecialchars($opt)."'>".htmlspecialchars($opt)."</option>";
        }
        echo "</select>";
    } else {
        // 3 or more → Multi-checkbox
        foreach ($optionsArray as $opt) {
            echo "<label><input type='checkbox' name='{$inputName}[]' value='".htmlspecialchars($opt)."'> ".htmlspecialchars($opt)."</label><br>";
        }
    }
}
else {
        echo "<input type='text' name='$inputName' $required>";
    }
    ?>
</div>
<?php
    endforeach;
endif;
?>

<!--
            <div class="field-group">
    <label>Additional Documents (Ex: Project, Portfolio):</label>
    <input type="file" name="additional_docs[]" multiple accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.txt,.jpg,.png,.jpeg">
    <small style="color: #666;">You can upload multiple files (PDF, DOCX, ZIP, PPT, etc.)</small>
</div>
-->

           <div class="field-group">
    <label>Upload Resume (Pdf/Doc):<span style="color:red;">*</span></label>
    <input type="file" name="resume" accept=".pdf, .doc, .docx"  >
    <small style="color: #666;">Filename should be like Rahul_CollegeName.pdf (Max 100MB)</small>
</div>

            <input type="submit" name="final_submit" value="Submit Application">
        <?php else: ?>
            <input type="submit" value="Check Eligibility & Load Roles">
        <?php endif; ?>
      
    </form>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('input[type="checkbox"][name^="selected_roles["]').forEach(function(checkbox) {
        checkbox.addEventListener("change", function() {
            const roleId = this.value;
            const dropdown = document.getElementById("priority_" + roleId);
            if (this.checked) {
                dropdown.setAttribute("required", "required");
            } else {
                dropdown.removeAttribute("required");
            }
        });
    });
});
</script>

<script>
document.querySelector("form").addEventListener("submit", function (e) {
    const form = this;
    const submitter = document.activeElement;

    // -------------------------------
    // (1) Role Selection & Priority Validation
    // -------------------------------
    if (submitter && submitter.name === "final_submit") {
        const selected = Array.from(document.querySelectorAll('input[type="checkbox"][name^="selected_roles["]:checked'));
        const selectedIds = selected.map(cb => cb.value);

        if (selectedIds.length === 0) {
            e.preventDefault();
            alert("Please select at least one role to apply.");
            return;
        }

        const selectedPriorities = selectedIds.map(id => {
            const dropdown = document.getElementById("priority_" + id);
            return dropdown?.value ?? '';
        });

        const filled = selectedPriorities.filter(p => p !== '');
        const hasDuplicate = filled.length !== new Set(filled).size;

        if (hasDuplicate) {
            e.preventDefault();
            alert("Each selected role must have a unique priority!");
            return;
        }

        // Check if any priority is selected without checkbox
        const allCheckboxes = document.querySelectorAll('input[type="checkbox"][name^="selected_roles["]');
        let invalidRoleSelected = false;

        allCheckboxes.forEach(cb => {
            const roleId = cb.value;
            const dropdown = document.getElementById("priority_" + roleId);
            const isChecked = cb.checked;
            const priority = dropdown?.value ?? '';

            if (!isChecked && priority !== '') {
                invalidRoleSelected = true;
            }
        });

        if (invalidRoleSelected) {
            e.preventDefault();
            alert("Please select the role checkbox if you selected its priority.");
            return;
        }
    }

    // -------------------------------
    // (2) Required Multi-Checkbox Groups Validation
    // -------------------------------
    let isValid = true;

    // Remove any previous error messages
    document.querySelectorAll('.field-group .error').forEach(el => el.remove());

    // Validate all required multi-checkbox groups
    document.querySelectorAll('.field-group').forEach(group => {
        const label = group.querySelector('label');
        const isRequired = label?.innerHTML.includes('<span style="color:red;">*</span>');
        const checkboxes = group.querySelectorAll('input[type="checkbox"]');

        if (isRequired && checkboxes.length > 1) {
            const anyChecked = Array.from(checkboxes).some(cb => cb.checked);

            if (!anyChecked) {
                isValid = false;

                const error = document.createElement('div');
                error.className = 'error';
                error.style.color = 'red';
                error.style.fontSize = '0.9em';
                error.textContent = 'This field is required.';
                group.appendChild(error);
            }
        }
    });

    if (!isValid) {
        e.preventDefault();
        const firstError = document.querySelector('.field-group .error');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
    }

    // -------------------------------
    // (3) File Upload Validation (Resume + Others)
// -------------------------------
// (3) File Upload Validation (Resume + Others)
// -------------------------------
const phpUploadErrors = [
    'The file exceeds the upload_max_filesize limit.',
    'The file exceeds the MAX_FILE_SIZE limit in the form.',
    'The file was only partially uploaded.',
    'No file was uploaded.',
    'Missing a temporary folder on the server.',
    'Failed to write file to disk.',
    'A PHP extension stopped the upload.'
];
function validateFileInput(input, isRequired = false, allowedTypes = [], maxSizeMB = null) {
    if (!input) return null;

    if (input.files.length === 0) {
        if (isRequired) return `Please upload the required file: ${input.name}`;
        return null;
    }

    for (const file of input.files) {
        if (!file) continue;

        if (file.size === 0) {
            return `File "${file.name}" seems incomplete or failed to upload properly.`;
        }

        if (allowedTypes.length > 0 && !allowedTypes.includes(file.type)) {
            return `File "${file.name}" must be one of types: ${allowedTypes.join(", ")}`;
        }

        // ✅ Only check size if maxSizeMB is provided
        if (maxSizeMB && file.size > maxSizeMB * 1024 * 1024) {
            return `File "${file.name}" exceeds max allowed size of ${maxSizeMB}MB.`;
        }
    }

    return null;
}

// Resume (required)
const resumeInput = form.querySelector('input[name="resume"]');
const resumeError = validateFileInput(resumeInput, true, [
    "application/pdf",
    "application/msword",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
], 100);

if (resumeError) {
    alert(resumeError);
    e.preventDefault();
    return;
}

// Other optional file inputs
const otherFileInputs = [
    'field_UploadPhoto',
    'field_UploadPortfolio',
    'field_UploadCoverLetter',
    'field_CertificationsUpload',
    'field_UploadAcademicCertificates',
    'field_UploadIDProof',
    'field_UploadSignature',
    'field_AdditionalDocumentsYoucanuploadmultiplefilesExProjectPortfolio',
    'field_CertificateOfInternship'  // <-- Added this line
];

for (const name of otherFileInputs) {
    const inputSingle = form.querySelector(`input[name="${name}"]`);
    const inputMultiple = form.querySelector(`input[name="${name}[]"]`);
    const input = inputSingle || inputMultiple;
    if (!input) continue;

   const error = validateFileInput(input, false, [], null);

    if (error) {
        alert(error);
        e.preventDefault();
        return;
    }
}

// ✅ If everything passed, form submits naturally

    // ✅ If everything passed, form submits naturally
});
</script>






<script>
function fetchStudentName() {
    const upid = document.querySelector('input[name="upid"]').value.trim().toUpperCase();
    const regno = document.querySelector('input[name="regno"]').value.trim().toUpperCase();


    if (upid && regno) {
        fetch(`form_generator.php?action=get_student_name&upid=${encodeURIComponent(upid)}&regno=${encodeURIComponent(regno)}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('student_name').value = data.student_name || '';
            });
    } else {
        document.getElementById('student_name').value = '';
    }
}

document.querySelector('input[name="upid"]').addEventListener('input', fetchStudentName);
document.querySelector('input[name="regno"]').addEventListener('input', fetchStudentName);
</script>

</body>
</html>

