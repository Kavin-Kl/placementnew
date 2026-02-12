<?php
session_start();
include("config.php");
if (!isset($_SESSION['admin_id'])) {
    header("Location: index");
    exit;
}
include("header.php");
include("course_groups_dynamic.php"); 

function romanize($num) {
    $n = intval($num);
    $res = '';
    $map = [
        'M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400,
        'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40,
        'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1
    ];
    foreach ($map as $roman => $int) {
        while($n >= $int) {
            $res .= $roman;
            $n -= $int;
        }
    }
    return $res;
}

$success = "";
$created_by = trim($_POST["created_by"] ?? '');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $conn->begin_transaction(); // START TRANSACTION

    try {
        $company = $_POST["company_name"];
        $open = $_POST['open_date'] ? date('Y-m-d H:i:s', strtotime($_POST['open_date'])) : null;
        $close = $_POST['close_date'] ? date('Y-m-d H:i:s', strtotime($_POST['close_date'])) : null;

        if ($open && $close && strtotime($close) < strtotime($open)) {
            throw new Exception("Close date & time cannot be earlier than open date & time.");
        }

        $ctc = $_POST["ctc_main"] ?? '';
        $form_link = uniqid("form_");
        $sharedFormFields = $_POST["form_fields"][0] ?? "[]";

        // Upload JD Files
        $jd_files = [];
        if (!empty($_FILES["jd_files"]["name"][0])) {
            foreach ($_FILES["jd_files"]["tmp_name"] as $key => $tmp_name) {
                $filename = uniqid("JD_") . "_" . basename($_FILES["jd_files"]["name"][$key]);
                $target = "uploads/" . $filename;
                if (move_uploaded_file($tmp_name, $target)) {
                    $jd_files[] = $target;
                }
            }
        }
        $jd_file_json = json_encode($jd_files);
        $extra_details = $_POST["extra_details"] ?? '';

        // Get existing drives for numbering
        $stmt = $conn->prepare("SELECT drive_id, open_date FROM drives WHERE company_name = ? ORDER BY open_date ASC");
        $stmt->bind_param("s", $company);
        $stmt->execute();
        $result = $stmt->get_result();

        $existingDrives = [];
        while ($row = $result->fetch_assoc()) {
            $existingDrives[] = [
                'drive_id' => $row['drive_id'],
                'open_date' => $row['open_date']
            ];
        }
        $stmt->close();

        $existingDrives[] = ['drive_id' => 0, 'open_date' => $open]; // new drive placeholder

        usort($existingDrives, function($a, $b) {
            return strtotime($a['open_date']) <=> strtotime($b['open_date']);
        });

        $driveIndex = 1;
        foreach ($existingDrives as $d) {
            if ($d['drive_id'] === 0) break;
            $driveIndex++;
        }


        $drive_no = "Drive " . $driveIndex;

// Get new fields from form
$company_url = trim($_POST['company_url'] ?? '');
$graduating_year = trim($_POST['graduating_year'] ?? '');
$work_location = trim($_POST['work_location'] ?? '');
$academic_year = trim($_POST['academic_year'] ?? '2025-2026');

// Get eligibility checkboxes (1 if checked, 0 if not)
$show_to_internship = isset($_POST['show_to_internship']) ? 1 : 0;
$show_to_vantage = isset($_POST['show_to_vantage']) ? 1 : 0;
$show_to_placement = isset($_POST['show_to_placement']) ? 1 : 0;

// Insert into drives (added eligibility fields and academic_year)
$stmt = $conn->prepare("INSERT INTO drives (
    company_name, drive_no, open_date, close_date, jd_file, form_link, extra_details, created_by, form_fields, company_url, graduating_year, work_location, show_to_internship, show_to_vantage, show_to_placement, academic_year
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");


if (!$stmt) throw new Exception("SQL Error (drives insert): " . $conn->error);
$stmt->bind_param(
    "ssssssssssssiiis",
    $company,
    $drive_no,
    $open,
    $close,
    $jd_file_json,
    $form_link,
    $extra_details,
    $created_by,
    $sharedFormFields,
    $company_url,
    $graduating_year,
    $work_location,
    $show_to_internship,
    $show_to_vantage,
    $show_to_placement,
    $academic_year
);




        if (!$stmt->execute()) throw new Exception("SQL Error (drives insert execute): " . $stmt->error);
        $drive_id = $conn->insert_id;

        // Update jd_link if present
        $jdLink = trim($_POST['jd_link'] ?? '');
        if (!empty($jdLink)) {
            $stmt = $conn->prepare("UPDATE drives SET jd_link = ? WHERE drive_id = ?");
            $stmt->bind_param("si", $jdLink, $drive_id);
            if (!$stmt->execute()) throw new Exception("SQL Error (jd_link update): " . $stmt->error);
        }

        // Renumber all drives
        $allDrivesStmt = $conn->prepare("SELECT drive_id, open_date FROM drives WHERE company_name = ? ORDER BY open_date ASC");
        $allDrivesStmt->bind_param("s", $company);
        $allDrivesStmt->execute();
        $allDrivesResult = $allDrivesStmt->get_result();

        $index = 1;
        while ($row = $allDrivesResult->fetch_assoc()) {
            $new_drive_no = "Drive " . $index++;

            $updateStmt = $conn->prepare("UPDATE drives SET drive_no = ? WHERE drive_id = ?");
            $updateStmt->bind_param("si", $new_drive_no, $row['drive_id']);
            if (!$updateStmt->execute()) throw new Exception("SQL Error (drive renumber): " . $updateStmt->error);
            $updateStmt->close();

            $conn->query("UPDATE drive_data SET drive_no = '$new_drive_no' WHERE drive_id = {$row['drive_id']}");
        }
        $allDrivesStmt->close();

        // Insert roles & drive_data
        if (!empty($_POST["role_name"])) {
            foreach ($_POST["role_name"] as $i => $role) {
                $coursesArray = array_map('trim', explode(',', $_POST["eligible_courses"][$i] ?? ''));
                $courses = json_encode($coursesArray);
                $min_percent = trim($_POST["min_percentage"][$i] ?? "");
                $min_percent = $min_percent === "" ? NULL : floatval($min_percent);

                $role_ctc = $_POST["ctc"][$i];
                $role_stipend = $_POST["stipend"][$i] ?? '';
                $role_work_timings = $_POST["work_timings"][$i] ?? '';

                $form_fields = $sharedFormFields;
                $offer_type = $_POST["offer_type"][$i] ?? "FTE";
                $sector = $_POST["sector"][$i] ?? "IT";

                // drive_roles insert
              
                    $stmt2 = $conn->prepare("INSERT INTO drive_roles 
    (drive_id, designation_name, eligible_courses, min_percentage, ctc, stipend, work_timings, offer_type, sector) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");


                if (!$stmt2) throw new Exception("SQL Error (roles insert): " . $conn->error);
               
                $stmt2->bind_param("issdsssss", $drive_id, $role, $courses, $min_percent, $role_ctc, $role_stipend, $role_work_timings, $offer_type, $sector);

                if (!$stmt2->execute()) throw new Exception("SQL Error (roles insert execute): " . $stmt2->error);
                $role_id = $conn->insert_id;

                // drive_data insert
                $company_status = "Yet to Start";
                $eligible_courses_json = $courses;
                $no_of_applied = 0;
                $no_of_hired = 0;

                $stmt3 = $conn->prepare("INSERT INTO drive_data 
                    (drive_id, company_status, offer_type, sector, company_name, drive_no, role, eligible_courses, no_of_applied, no_of_hired, role_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt3) throw new Exception("SQL Error (drive_data insert): " . $conn->error);
                $stmt3->bind_param("issssssssii", $drive_id, $company_status, $offer_type, $sector, $company, 
                    $drive_no, $role, $eligible_courses_json, $no_of_applied, $no_of_hired, $role_id);
                if (!$stmt3->execute()) throw new Exception("SQL Error (drive_data insert execute): " . $stmt3->error);
            }
        }

        // ✅ Commit transaction if all succeeded
        $conn->commit();
        $success = "<strong>" . htmlspecialchars($company) . "</strong> drive has been created successfully!";

    } catch (Exception $e) {
        $conn->rollback();
        die("Drive creation failed: " . $e->getMessage());
    }
}
?>


<style>
  .flatpickr-close {
    position: absolute;
    top: 5px;
    right: 35px; /* shifted left so it doesn't overlap ">" arrow */
    font-size: 18px;
    cursor: pointer;
    color: #999;
    z-index: 10;
}
.flatpickr-close:hover {
    color: #333;
}


 </style>

<h2 class="headings">Add Drive</h2>
<p>Fill out the details below to create a new placement drive.</p>

<?php if ($success): ?>
  <div id="successMessage" style="
    background-color: #d4edda;
    color: #155724;
    border: 2px solid #c3e6cb;
    padding: 15px;
    margin: 20px 0;
    border-radius: 6px;
    font-size: 18px;
   
    text-align: center;
  ">
     <?= $success ?>
  </div>
<?php endif; ?>

<div class="drive-form-wrapper" style="width: 650px; margin: 0 auto;">


  
<form method="POST" enctype="multipart/form-data" onsubmit="return validateDriveForm()"style="
  background: #fff;        /* white background */
  padding: 20px;           /* inner spacing */
  border: 1px solid #ccc;  /* border */
  border-radius: 8px;      /* rounded corners */
  box-sizing: border-box;  /* safe sizing */
  width: 100%;             /* full width inside parent */
  max-width: 800px;        /* limit max width */
  margin: 0 auto;          /* center if parent allows */
">
  <label>Created By: <span style="color: red;">*</span></label>
<input type="text" name="created_by" required><br><br>

  <label>Company Name: <span style="color: red;">*</span></label><br>
  <input type="text" name="company_name" required><br><br>

<label>Form Open Date & Time: <span style="color: red;">*</span></label><br>
<input type="text" id="open_date" name="open_date" required><br><br>


<label>Form Close Date & Time: <span style="color: red;">*</span></label><br>
<input type="text" id="close_date" name="close_date" required><br><br>



<label>Upload JD Files:</label><br>
<input type="file" name="jd_files[]" multiple><br><br>
<label>OR Paste JD Link:</label><br>
<input type="text" name="jd_link" placeholder="https://drive.google.com/..." style="width: 100%;"><br><br>

<label>Company URL:</label><br>
<input type="text" name="company_url" placeholder="https://www.company.com" style="width: 100%;"><br><br>

<label>Graduating Year:</label><br>
<input type="text" name="graduating_year" placeholder="e.g. 2025" style="width: 100%;"><br><br>

<label>Work Location:</label><br>
<input type="text" name="work_location" placeholder="e.g. Bangalore, Remote" style="width: 100%;"><br><br>

<label>Academic Year:</label><br>
<input type="text" name="academic_year" placeholder="e.g. 2025-2026" value="2025-2026" style="width: 100%;"><br><br>

<label style="font-weight: bold; margin-top: 10px; display: block;">Show This Drive To:</label>
<div style="margin-left: 20px; margin-top: 10px; margin-bottom: 20px;">
  <label style="display: block; margin-bottom: 8px;">
    <input type="checkbox" name="show_to_internship" value="1" checked style="margin-right: 8px;">
    Internship Registered Students
  </label>
  <label style="display: block; margin-bottom: 8px;">
    <input type="checkbox" name="show_to_vantage" value="1" checked style="margin-right: 8px;">
    Vantage Registered Students
  </label>
  <label style="display: block; margin-bottom: 8px;">
    <input type="checkbox" name="show_to_placement" value="1" checked style="margin-right: 8px;">
    Placement Registered Students (Full-Time/FTE)
  </label>
</div>

<input type="hidden" name="extra_details" id="extra_details">
  <button type="button" onclick="openExtraModal()"class="button-standard" style="margin-bottom:30px";>+ Extra Drive Details</button>

 
  <h5>Roles</h5>
  <div id="roles-container"></div>

  
  <button type="button" onclick="addRole()"class="button-standard">+ Add Role</button><br><br>
 <!--------------preeetham
  <input type="text" name="extra_details" id="extra_details">---->
  <!-- Extra Drive Details Popup -->
<div id="extraDetailsModal" class="popup">
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
<button type="button" onclick="resetExtraDetails()" class="button-standard" >Reset</button>

  <div style="display: flex; gap: 8px;">
    <button type="button" onclick="saveExtraDetails()" class="button-standard">Save</button>
    <button type="button" onclick="closeExtraModal()" class="button-standard" >Cancel</button>
  </div>
</div>



  <h4>Extra Drive Details</h4>

<label>Mode of Work:</label>
<input type="text" id="workMode"><br>

<label>Office Address:</label>
<input type="text" id="officeAddress"><br>

<label>Interview Details:</label>
<input type="text" id="interviewDetails"><br>

<label>Eligibility Notes / Restrictions:</label>
<input type="text" id="eligibilityNote"><br>

  <label>No. of Vacancies:</label>
  <input type="text" id="vacancies"><br>

  <label>Duration:</label>
  <input type="text" id="duration"><br>

  <label>Process Details:</label>
  <input type="text" id="stipend"><br>

  <label>Post Internship Opportunity:</label>
  <input type="text" id="postInternship"><br>

  <label>Timings:</label>
  <input type="text" id="timings"><br>

  <label>WhatsApp Group Link:</label>
  <input type="text" id="whatsapp"><br>

  <label>Additional Info:</label>
<input type="text" id="additionalInfo"><br>
  <hr>
<h5>Add Custom Extra Field</h5>
<div id="custom-fields-container"></div>

<div style="display: flex; gap: 10px; margin-top: 10px;">
  <textarea id="customKey" placeholder="Field Title (e.g. Dress Code)" 
    style="width: 350px; height: 50px; resize: vertical;"></textarea>
  <textarea id="customValue" placeholder="Value (e.g. Formal)" 
    style="width: 350px; height: 50px; resize: vertical;"></textarea>
  <span onclick="addCustomExtraField()" style="
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    color: black;
    padding: 0 5px;
    position: relative;
    top: 7px;
  ">
    +
  </span>




</div>

</div>
<input type="submit" value="Create Drive" onclick="return validateAllEligibleCourses()">

</form>



<!-- Form Preview Modal (global) -->
<div id="formPreviewModal" class="popup" style="display:none;">
  <h4>Form Preview</h4>
  <button type="button" class="button-standard" style="margin-left:550px;" onclick="closeFormPreview()">Close</button>
  <div id="formPreviewContent"></div>

</div>



<div id="overlay" style="display:none;"></div>
<!-- Form Fields Popup -->
<div id="formFieldModal" class="popup">
 <div style="display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 10px;">
  <button type="button" onclick="saveFields()" class="button-standard">Save</button>
  <button type="button" onclick="resetFormFields()" class="button-standard">Reset</button>
  <button type="button" onclick="closeModal()" class="button-standard">Cancel</button>
</div>

  <h6>Select Form Fields</h6>
  <input type="text" class="search-box" id="fieldFilter" placeholder="Search fields..." style="margin-bottom: 10px;">
 <div class="tab-buttons">
  <button type="button" class="tab-btn active" onclick="switchTab('personal')">Personal Details</button>
  <button type="button" class="tab-btn" onclick="switchTab('education')">Educational Details</button>
  <button type="button" class="tab-btn" onclick="switchTab('work')">Work Experience</button>
  <button type="button" class="tab-btn" onclick="switchTab('others')">Other Details</button>
</div>

  <div id="modal-fields" class="modal-content-area"></div>
</div>
<!-- Eligible Courses Popup -->
<div id="courseModal" class="popup" style="width:650px";>
  <div style="display: flex; gap: 20px; margin-bottom: 10px;">
    <label><input type="checkbox" id="selectAllUG" onchange="toggleGroup('ug')"> Select All UG</label>
    <label><input type="checkbox" id="selectAllPG" onchange="toggleGroup('pg')"> Select All PG</label>
  </div>

  <div style="display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 10px;">
    <button type="button" onclick="saveCourses()" class="button-standard">Save</button>
    <button type="button" onclick="resetCourses()" class="button-standard" >Reset</button>
    <button type="button" onclick="closeCourseModal()" class="button-standard">Cancel</button>
  </div>

  <h6>Select Eligible Courses</h6>
  <input type="text" class="search-box" id="courseSearch" placeholder="Search courses...">

  <div class="tab-buttons">
    <button type="button" class="tab-btn active" onclick="showCourseTab('ug')">UG Courses</button>
    <button type="button" class="tab-btn" onclick="showCourseTab('pg')">PG Courses</button>
  </div>

  <!-- UG Courses -->
  <div id="ugCourses" class="course-tab">
    <?php
    foreach ($ug_courses_grouped as $school => $groups) {
      $groupId = preg_replace('/[^a-zA-Z0-9]/', '', $school);
      $groupClass = "subgroup_$groupId";

      echo "<div class='heading-row' style='display: flex; align-items: center; justify-content: space-between; margin-top: 16px;'>
        <label style='font-weight:600; white-space: nowrap;'>$school</label>
        <input type='checkbox' class='group-toggle' data-group='$groupClass' onchange='toggleGroupCourses(this, \"$groupClass\")'>
      </div>";
      foreach ($groups as $courses) {
        foreach ($courses as $course) {
          echo "<div class='course-row $groupClass'>
                  <input type='checkbox' class='ug-course $groupClass' value='$course'>
                  <span>$course</span>
                </div>";
        }
      }
    }
    ?>
  </div>

  <!-- PG Courses -->
  <div id="pgCourses" class="course-tab" style="display:none;">
    <?php
    foreach ($pg_courses_grouped as $school => $groups) {
      $groupId = preg_replace('/[^a-zA-Z0-9]/', '', $school);
      $groupClass = "subgroup_$groupId";

    echo "<div class='heading-row' style='display: flex; align-items: center; justify-content: space-between; margin-top: 16px;'>
        <label style='font-weight:600;'>$school</label>
        <input type='checkbox' class='group-toggle' data-group='$groupClass' onchange='toggleGroupCourses(this, \"$groupClass\")'>
      </div>";


      foreach ($groups as $courses) {
        foreach ($courses as $course) {
          echo "<div class='course-row $groupClass'>
                  <input type='checkbox' class='pg-course $groupClass' value='$course'>
                  <span>$course</span>
                </div>";
        }
      }
    }
    ?>
  </div>
</div>

  </div>




<!-- Extra Drive Details Popup -->
<!---preetham<div id="extraDetailsModal" class="popup">
  <div style="display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 10px;">
    <button type="button" onclick="saveExtraDetails()" class="popup-action-btn">Save</button>
    <button type="button" onclick="closeExtraModal()" class="popup-action-btn cancel-btn">Cancel</button>
  </div>

  <h4>Extra Drive Details</h4>

  <label>No. of Vacancies:</label>
  <input type="text" id="vacancies"><br>

  <label>Location:</label>
  <input type="text" id="location"><br>

  <label>Duration:</label>
  <input type="text" id="duration"><br>

  <label>Stipend:</label>
  <input type="text" id="stipend"><br>

  <label>Post Internship Opportunity:</label>
  <input type="text" id="postInternship"><br>

  <label>Timings:</label>
  <input type="text" id="timings"><br>

  <label>WhatsApp Group Link:</label>
  <input type="text" id="whatsapp"><br>
</div>
<input type="hidden" name="extra_details" id="extra_details">
<button type="button" onclick="openExtraModal()">+ Extra Drive Details</button>-->



<script>
  // PRESET FIELD LOGIC - place at top of add_drive.js
const fieldPresets = {
  "Gender": { type: "radio", options: ["Male", "Female", "Other"] },
  "Marital Status": { type: "dropdown", options: ["Single", "Married", "Divorced", "Widowed"] },
  "Category (General/OBC/SC/ST)": { type: "dropdown", options: ["General", "OBC", "SC", "ST"] },
  "Religion": { type: "dropdown", options: ["Hindu", "Christian", "Muslim", "Sikh", "Jain", "Other"] },
  "Blood Group": { type: "dropdown", options: ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"] },

  "Phone No": { type: "tel" },
  "Alternate Phone No": { type: "tel" },
"Email ID (please recheck the email before submitting)": {
   type: "text", // ✅ use text, not email
  pattern: "^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$",
  
  placeholder: "you@example.com",
  title: "Enter a valid email address (e.g. abc@example.com)"
},
"Alternate Email ID": {
  type: "text", // ✅ use text, not email
  pattern: "^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$",
  placeholder: "optional@example.com",
  title: "Enter a valid email address"
},
   
  "DOB": { type: "date" },
 



   // Year of Passing fields
  "10th Year Of Passing": { type: "month" },
  "12th Year Of Passing": { type: "month" },
  "Diploma Year Of Passing": { type: "month" },
  "UG Year of Passing": { type: "month" },
  "PG Year of Passing": { type: "month" },

  "Emergency Contact Number": {
    type: "tel",
    pattern: "[6-9][0-9]{9}",
    placeholder: "10-digit emergency contact number",
    title: "Enter a valid emergency number"
  },"Pincode": {
    type: "text",
    pattern: "[1-9][0-9]{5}",
    maxlength: 6,
    placeholder: "6-digit PIN",
    title: "Enter a valid 6-digit Indian pincode"
  },

  "Have you completed any internship?": { type: "radio", options: ["Yes", "No"] },
  "Certificate of Internship": { type: "file" },
  "Have Prior Full-time Experience?": { type: "radio", options: ["Yes", "No"] },
  "Are you ok with shifts?": { type: "radio", options: ["Yes", "No"] },
  "Are you ok with relocation?": { type: "radio", options: ["Yes", "No"] },
  "Are you available in person for interview?": { type: "radio", options: ["Yes", "No"] },
  "Willing to join Immediately ?": { type: "radio", options: ["Yes", "No"] },

  "Preferred Industry": {
    type: "dropdown",
    options: [
      "Information Technology", "Healthcare", "Finance", "Education",
      "Manufacturing", "Retail", "Telecommunications", "Media & Entertainment",
      "Travel & Hospitality", "Real Estate", "Automotive", "Consulting", "Government"
    ]
  },
  
  "Upload Photo": { type: "file" },
  "Upload Portfolio": { type: "file" },
  "Upload Cover Letter": { type: "file" },
  "Certifications Upload": { type: "file" },
  "Upload Academic Certificates": { type: "file" },
  "Upload ID Proof": { type: "file" },
  "Upload Signature": { type: "file" },
 "Additional Documents (You can upload multiple files Ex: Project, Portfolio)":{type: "file" },
  "Languages Known (Read/Write/Speak)": {
    type: "checkbox",
    options: ["English", "Hindi", "Kannada", "Tamil", "Telugu", "Malayalam", "Others"]
  },

  "Active Backlogs (UG)?": { type: "radio", options: ["Yes", "No"] },
  "Active Backlogs (PG)?": { type: "radio", options: ["Yes", "No"] },

  "I hereby undertake that I will appear for the complete recruitment process of the above mentioned organization. In case I fail to appear for the same, my candidature for next companies stands cancelled. I also confirm that I have not been placed so far.": { type: "checkbox",options: ["I Agree"] },
 
  "I hereby declare that the above information is correct.": { type: "checkbox",options: ["I Agree"] },
  "Declaration of Authenticity": { type: "checkbox",options: ["I Agree"] },
  "Agree to Terms and Conditions": { type: "checkbox",options: ["I Agree"] }


  
};


let roleIndex = 0;
let selectedFieldsPerRole = {};
let selectedCoursesPerRole = {};
let currentRoleId = 0;
let currentTab = 'personal';
let customExtraFields = []; // Stores added extra fields as array of { key, value }

var fields = fields || {
  personal: [
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
  education: [
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
  work: [
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
  others: [
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
};


function selectAllCourses() {
  document.querySelectorAll('#courseModal .course-row input[type="checkbox"]').forEach(cb => cb.checked = true);
}
function addRole() {
  let container = document.getElementById("roles-container");
let copyOptions = "";
  for (let i = 0; i < roleIndex; i++) {
    copyOptions += `<option value="${i}">Role ${i + 1}</option>`;
  }

  const roleId = roleIndex; // Lock this index to avoid bugs when deleting

  const roleHTML = `
    <div class="role-block" id="role-block-${roleId}" style="border: 1px solid #ccc; margin-bottom: 10px; border-radius: 5px; width: 600px;">

   <div class="role-header" style="
  display: flex;
  padding: 4px 8px;
font-size: 14px;
  justify-content: space-between;
  align-items: center;
  background: #e0e0e0;
 
  border-radius: 5px 5px 0 0;
  cursor: pointer;
" onclick="toggleRoleContent(${roleId})">
  <strong>Role ${roleId + 1}</strong>

  
<div style="display: flex; align-items: center; gap: 10px;">
  <button type="button" onclick="removeRole(${roleId}); event.stopPropagation();" title="Delete Role"
    style="background: transparent; border: none; cursor: pointer;">
    <i class="bi bi-trash-fill" style="font-size: 16px; color: #444; position: relative; top: 2px; margin-left: 6px;"></i>
  </button>
  <span id="toggle-icon-${roleId}" onclick="toggleRoleContent(${roleId}); event.stopPropagation();" style="font-size: 18px; position: relative; top: 5px; margin-right: 6px;">+</span>
</div>

</div>


      <div class="role-content" id="role-content-${roleId}" style="display: block; padding: 10px;">


        <label>Designation: <span style="color: red;">*</span></label>
        <input type="text" name="role_name[]" required>


        <label>CTC:</label>
        <input type="text" name="ctc[]" >

<label>Stipend:</label>
<input type="text" name="stipend[]" >

<label>Work Timings:</label>
<input type="text" name="work_timings[]" placeholder="e.g. 9 AM - 6 PM" >

        <label>Offer Type:</label>
        <select name="offer_type[]">
     <option value="">-- Select Offer Type --</option>
          <option value="FTE">FTE</option>
          <option value="Internship + PPO">Internship + PPO</option>
          <option value="Apprentice">Apprentice</option>
          <option value="Internship">Internship</option>
        </select>

      <label>Job Sector:</label>
<select name="sector[]" >
  <option value="">-- Select Job Sector --</option>
  <option value="BFSI">BFSI</option>
  <option value="Sales, Marketing, BD">Sales, Marketing, BD</option>
  <option value="HR">HR</option>
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


      <label>Eligibility: <span style="color:red;">*</span></label>

        <button type="button" class="btn" onclick="openCourseModal(${roleId})">Select Eligible Courses</button>
        <input type="hidden" name="eligible_courses[]" id="courses_${roleId}" required>
       
        <div id="display_courses_${roleId}" style="color:green; margin:5px 0;"></div>

        <label>Minimum Percentage:</label>
        <input type="text" name="min_percentage[]" >






        <input type="hidden" name="form_fields[${roleId}]" id="form_fields_${roleId}">
       

      </div>
    </div>
  `;

  container.insertAdjacentHTML('beforeend', roleHTML);

  // Init role-specific arrays
  selectedFieldsPerRole[roleId] = [];
  selectedCoursesPerRole[roleId] = [];

  roleIndex++; // Increment global role index only after setup
  renumberRoles();
}


function saveCurrentTabState() {
  const rows = document.querySelectorAll("#modal-fields .modal-field-row");
  rows.forEach(row => {
    const cb = row.querySelector("input[type='checkbox']");
    if (!cb) return;

    // ✅ SKIP heading checkboxes:
    if (cb.classList.contains('heading-checkbox')) return;

    const fieldName = cb.value;
    const id = fieldName.replace(/[^a-zA-Z]/g, '');
    const mandatory = document.getElementById(`mandatory_${id}`)?.checked || false;
    const customVal = document.getElementById(`custom_${id}`)?.value || "";

    let existingIndex = selectedFieldsPerRole[currentRoleId]?.findIndex(f => f.name === fieldName);

    if (cb.checked) {
      const fieldObj = { name: fieldName, mandatory: mandatory, options: customVal };
      if (existingIndex >= 0) {
        selectedFieldsPerRole[currentRoleId][existingIndex] = fieldObj;
      } else {
        selectedFieldsPerRole[currentRoleId].push(fieldObj);
      }
    } else {
      if (existingIndex >= 0) {
        selectedFieldsPerRole[currentRoleId].splice(existingIndex, 1);
      }
    }
  });
}


let mandatoryStatePerRole = {}; 
function switchTab(tab) {
  saveCurrentTabState();
  
  currentTab = tab;
let html = "";
  // Highlight active tab button
  document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
  if (tab === 'personal') document.querySelectorAll('.tab-btn')[0].classList.add('active');
  else if (tab === 'education') document.querySelectorAll('.tab-btn')[1].classList.add('active');
  else if (tab === 'work') document.querySelectorAll('.tab-btn')[2].classList.add('active');
  else if (tab === 'others') document.querySelectorAll('.tab-btn')[3].classList.add('active');

  
  // ✅ ADD THIS BLOCK
if (tab === 'personal') {
  // Are all personal fields checked?
  const personalFields = fields['personal'].filter(f => !f.startsWith('---'));
  const allChecked = personalFields.every(f =>
    selectedFieldsPerRole[currentRoleId]?.some(sf => sf.name === f)
  );
  
  html += `
    <div style="display: inline-flex; align-items: center; gap: 5px; margin: 10px 0; white-space: nowrap;">
      <input type="checkbox" id="selectAllPersonal" ${allChecked ? 'checked' : ''}>
      <label for="selectAllPersonal" style="font-weight: bold; font-size: 15px; margin: 0;">Select All Personal Details</label>
    </div>
  `;
}




  fields[tab].forEach((field, idx) => {
    if (field.trim().startsWith("---")) {
      const headingText = field.replace(/---/g, "").trim();
      const headingId = headingText.replace(/[^a-zA-Z]/g, '');

      // Find child fields under this heading
      const childFields = [];
      for (let i = idx + 1; i < fields[tab].length; i++) {
        if (fields[tab][i].trim().startsWith("---")) break;
        childFields.push(fields[tab][i]);
      }

      // Check if ALL children are selected
      const selectedCount = childFields.filter(cf =>
        selectedFieldsPerRole[currentRoleId]?.some(f => f.name === cf)
      ).length;

      const headingChecked = (selectedCount === childFields.length && childFields.length > 0) ? 'checked' : '';

      html += `
        <div class="modal-field-row heading-row" style="display: flex; align-items: center; gap: 10px; font-weight: bold; font-size: 15px; margin: 15px 0 5px;">
          <div style="display: flex; align-items: center; gap: 8px;">
            <input type="checkbox" class="heading-checkbox" data-heading="${headingText}" id="head_${headingId}" ${headingChecked}>
            <label for="head_${headingId}" class="field-name" style="margin: 0; white-space: nowrap;">${headingText}</label>
          </div>
        </div>
      `;
      return; // Skip normal row render for headings
    }

    // Normal field
  let id = field.replace(/[^a-zA-Z]/g, '');

    let isChecked = selectedFieldsPerRole[currentRoleId]?.some(f => f.name === field) ? "checked" : "";
    let existingField = selectedFieldsPerRole[currentRoleId]?.find(f => f.name === field);
    let customVal = existingField?.options || "";

    if (!customVal && fieldPresets[field] && fieldPresets[field].options) {
      customVal = fieldPresets[field].options.join(", ");
    }

    // Initialize if not present
if (!mandatoryStatePerRole[currentRoleId]) {
  mandatoryStatePerRole[currentRoleId] = {};
}

// Check saved mandatory state; default to checked if undefined
const storedMandatory = mandatoryStatePerRole[currentRoleId][field];
const mandatoryChecked = storedMandatory === false ? "" : "checked";

const savedVal = customDropdownValues[field] || customVal || "";
html += `
  <div class="modal-field-row">
    <input type="checkbox" value="${field}" ${isChecked}>
  <label class="field-name" style="margin-right: 10px; font-weight: normal;">${field}</label>

    <input type="text"
           class="custom-dropdown"
           data-field-name="${field}"
           placeholder="Custom dropdown values"
           id="custom_${id}"
           value="${savedVal}"
           style="width: 200px; padding: 4px 6px; font-size: 13px; border: 1px solid #ccc; border-radius: 4px;">

    <label class="mandatory-label" style="margin-left: 10px; color: #d00; font-size: 13px;">
      <input type="checkbox" id="mandatory_${id}" ${mandatoryChecked} onchange="handleMandatoryChange('${field}', this.checked)"> Mandatory

    </label>
  </div>
`;


  });

  // Add New Field input and button


html += `
  <div style="margin-top: 20px; border-top: 1px solid #ccc; padding-top: 10px; display: flex; align-items: center; gap: 8px;">
    <textarea id="newFieldName" placeholder="Enter new field name"
              style="width: 250px; height: 50px; padding: 5px 8px; font-size: 14px; resize: vertical;"></textarea>
    <button type="button" onclick="addNewField('${tab}')" class="button-standard">Add Field</button>
  </div>
`;

  document.getElementById("modal-fields").innerHTML = html;


if (tab === 'personal') {
  document.getElementById('selectAllPersonal').addEventListener('change', function () {
    document.querySelectorAll('#modal-fields .modal-field-row').forEach(row => {
      const cb = row.querySelector('input[type="checkbox"]');
      if (cb && !cb.classList.contains('heading-checkbox')) {
        cb.checked = this.checked;

        const fieldName = cb.value;
        let existingIndex = selectedFieldsPerRole[currentRoleId]?.findIndex(f => f.name === fieldName);
        if (this.checked) {
          if (existingIndex === -1) {
            selectedFieldsPerRole[currentRoleId].push({ name: fieldName });
          }
        } else {
          if (existingIndex >= 0) {
            selectedFieldsPerRole[currentRoleId].splice(existingIndex, 1);
          }
        }
      }
    });
  });
}


  // Bulk select logic for headings
  document.querySelectorAll('.heading-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function () {
      const heading = this.dataset.heading;
      const fieldsInHeading = getFieldsUnderHeading(heading, tab);

      document.querySelectorAll('#modal-fields .modal-field-row').forEach(row => {
        const label = row.querySelector('.field-name');
        if (label && fieldsInHeading.includes(label.textContent)) {
          const cb = row.querySelector("input[type='checkbox']");
          cb.checked = this.checked;

          // Update in selectedFieldsPerRole too
          if (this.checked) {
            if (!selectedFieldsPerRole[currentRoleId]) {
              selectedFieldsPerRole[currentRoleId] = [];
            }
            if (!selectedFieldsPerRole[currentRoleId].some(f => f.name === label.textContent)) {
              selectedFieldsPerRole[currentRoleId].push({ name: label.textContent });
            }
          } else {
            selectedFieldsPerRole[currentRoleId] = selectedFieldsPerRole[currentRoleId].filter(f => f.name !== label.textContent);
          }
        }
      });
    });
  });
  filterFields();
  
}

function handleMandatoryChange(fieldName, isChecked) {
  if (!mandatoryStatePerRole[currentRoleId]) {
    mandatoryStatePerRole[currentRoleId] = {};
  }
  mandatoryStatePerRole[currentRoleId][fieldName] = isChecked;
}

function getFieldsUnderHeading(heading, tab) {
  const all = fields[tab];
  let collecting = false;
  const collected = [];

  for (let i = 0; i < all.length; i++) {
    const item = all[i];
    if (item.trim().startsWith("---")) {
      collecting = item.includes(heading);
      continue;
    }
    if (collecting && !item.trim().startsWith("---")) {
      collected.push(item);
    } else if (collecting && item.trim().startsWith("---")) {
      break;
    }
  }
  return collected;
}


function saveFields() {
  saveCurrentTabState();

  const formFieldInput = document.getElementById(`form_fields_${currentRoleId}`);
  if (formFieldInput) {
    formFieldInput.value = JSON.stringify(selectedFieldsPerRole[currentRoleId]);
  }

  // Only try to update display if it exists
  const displayDiv = document.getElementById(`display_fields_${currentRoleId}`);
  if (displayDiv) {
    displayDiv.innerText = selectedFieldsPerRole[currentRoleId].map(f => f.name).join(", ");
  }

  closeModal();
}


function closeModal() {
  document.getElementById("overlay").style.display = "none";
  document.getElementById("formFieldModal").style.display = "none";
}

function openFieldSelector(roleId) {
  currentRoleId = roleId;

  // Attach search listener only once
  const searchInput = document.getElementById('fieldFilter');
  if (searchInput && !searchInput.dataset.listenerAttached) {
    searchInput.addEventListener('input', filterFields);
    searchInput.dataset.listenerAttached = "true"; // Mark it as attached
  }

  switchTab('personal');
  document.getElementById("overlay").style.display = "block";
  document.getElementById("formFieldModal").style.display = "block";
}

function filterFields() {
  const query = document.getElementById('fieldFilter').value.toLowerCase();
  document.querySelectorAll('#modal-fields .modal-field-row').forEach(row => {
    row.style.display = row.innerText.toLowerCase().includes(query) ? '' : 'none';
  });
}

function openCourseModal(roleId) {
  currentRoleId = roleId;
  document.getElementById("overlay").style.display = "block";
  document.getElementById("courseModal").style.display = "block";
  showCourseTab('ug');

  // Clear all checkboxes first
  document.querySelectorAll('#courseModal input[type="checkbox"]').forEach(cb => cb.checked = false);

  // ✅ Pre-fill checkboxes with saved courses for this role
  const selectedCourses = selectedCoursesPerRole[roleId] || [];
  selectedCourses.forEach(course => {
    const cb = document.querySelector(`#courseModal input[type="checkbox"][value="${course}"]`);
    if (cb) cb.checked = true;
  });

  // ✅ Reset Select All UG/PG checkboxes
  const allUG = document.querySelectorAll('.ug-course').length;
  const allPG = document.querySelectorAll('.pg-course').length;
  const checkedUG = document.querySelectorAll('.ug-course:checked').length;
  const checkedPG = document.querySelectorAll('.pg-course:checked').length;

  document.getElementById('selectAllUG').checked = selectedCourses.filter(c => {
    return document.querySelector(`.ug-course[value="${c}"]`);
  }).length === allUG;

  document.getElementById('selectAllPG').checked = selectedCourses.filter(c => {
    return document.querySelector(`.pg-course[value="${c}"]`);
  }).length === allPG;

  document.getElementById('courseSearch').value = ''; // Clear search
  filterCourses(); // Reset course list
//added preeth
    document.getElementById('courseSearch').addEventListener('input', filterCourses);
}

function showCourseTab(tab) {
  document.getElementById('ugCourses').style.display = (tab === 'ug') ? 'block' : 'none';
  document.getElementById('pgCourses').style.display = (tab === 'pg') ? 'block' : 'none';
  document.querySelectorAll('#courseModal .tab-btn').forEach(btn => btn.classList.remove('active'));
  document.querySelectorAll('#courseModal .tab-btn')[tab === 'ug' ? 0 : 1].classList.add('active');
}

function filterCourses() {
  const query = document.getElementById('courseSearch').value.toLowerCase();
  document.querySelectorAll('#courseModal .course-row').forEach(row => {
    row.style.display = row.innerText.toLowerCase().includes(query) ? '' : 'none';
  });
}
function saveCourses() {
  const checkboxes = document.querySelectorAll("#courseModal .ug-course:checked, #courseModal .pg-course:checked");
  let courses = Array.from(checkboxes).map(cb => cb.value);

  const allUG = document.querySelectorAll('.ug-course').length;
  const allPG = document.querySelectorAll('.pg-course').length;
  const checkedUG = document.querySelectorAll('.ug-course:checked').length;
  const checkedPG = document.querySelectorAll('.pg-course:checked').length;

  let prefix = [];
  let remaining = [];
  let coursesToSave = [...courses]; // Copy for saving to database

  // Add markers to database if all selected
  if (checkedUG === allUG && checkedUG > 0) {
    prefix.push("All UG Courses");
    coursesToSave.push("All UG"); // Add marker for database
  }
  if (checkedPG === allPG && checkedPG > 0) {
    prefix.push("All PG Courses");
    coursesToSave.push("All PG"); // Add marker for database
  }

  courses.forEach(course => {
    const isUG = Array.from(document.querySelectorAll('.ug-course')).find(cb => cb.value === course);
    const isPG = Array.from(document.querySelectorAll('.pg-course')).find(cb => cb.value === course);

    if (
      (isUG && checkedUG === allUG) ||
      (isPG && checkedPG === allPG)
    ) {
      // skip — covered by prefix in display
    } else {
      remaining.push(course);
    }
  });

  selectedCoursesPerRole[currentRoleId] = coursesToSave; // Save with markers

  const displayText = [...prefix, ...remaining].join(", ");

  document.getElementById(`courses_${currentRoleId}`).value = coursesToSave.join(",");
  document.getElementById(`display_courses_${currentRoleId}`).innerText = displayText;

  closeCourseModal();
}

function closeCourseModal() {
  document.getElementById("overlay").style.display = "none";
  document.getElementById("courseModal").style.display = "none";
  currentRoleId = null; // ✅ optional safety
}

function copyRole(roleId, fromId) {
  if (fromId === "") return;
  selectedFieldsPerRole[roleId] = JSON.parse(JSON.stringify(selectedFieldsPerRole[fromId]));
  //selectedCoursesPerRole[roleId] = [...selectedCoursesPerRole[fromId]];
  document.getElementById(`form_fields_${roleId}`).value = JSON.stringify(selectedFieldsPerRole[roleId]);
  //document.getElementById(`courses_${roleId}`).value = selectedCoursesPerRole[roleId].join(",");
  document.getElementById(`display_fields_${roleId}`).innerText = selectedFieldsPerRole[roleId].map(f => f.name).join(", ");
  //document.getElementById(`display_courses_${roleId}`).innerText = selectedCoursesPerRole[roleId].join(", ");
}

//window.onload = () => addRole();

function openExtraModal() {
  document.getElementById("overlay").style.display = "block";
  document.getElementById("extraDetailsModal").style.display = "block";
}

let extraDetailsChanged = false;

// Track input changes
document.addEventListener("input", function (e) {
  if (document.getElementById("extraDetailsModal").contains(e.target)) {
    extraDetailsChanged = true;
  }
});
function closeExtraModal() {
  // If any field was changed and not saved
  if (extraDetailsChanged) {
    const confirmSave = confirm("You have unsaved changes in Extra Drive Details. Do you want to save them before closing?");
    if (confirmSave) {
      // ✅ Call saveExtraDetails and return to avoid double-closing loop
      saveExtraDetails(true);
      return;
    }
  }

  // ✅ Close the modal normally
  document.getElementById("overlay").style.display = "none";
  document.getElementById("extraDetailsModal").style.display = "none";

  // Reset change tracker after closing
  extraDetailsChanged = false;
}


function saveExtraDetails(fromClose = false) {

  const details = {
    vacancies: document.getElementById('vacancies').value,
    duration: document.getElementById('duration').value,
    stipend: document.getElementById('stipend').value,
    postInternship: document.getElementById('postInternship').value,
    timings: document.getElementById('timings').value,
    whatsapp: document.getElementById('whatsapp').value,
    workMode: document.getElementById('workMode')?.value || '',
    officeAddress: document.getElementById('officeAddress')?.value || '',
    interviewDetails: document.getElementById('interviewDetails')?.value || '',
    eligibilityNote: document.getElementById('eligibilityNote')?.value || '',
    additionalInfo: document.getElementById('additionalInfo')?.value || ''
  };

  // Add custom dynamic fields
  document.querySelectorAll('.custom-extra-field').forEach(el => {
    const key = el.dataset.key;
    const value = el.dataset.value;
    if (key && value) {
      details[key] = value;
    }
  });

  document.getElementById('extra_details').value = JSON.stringify(details);
  
  extraDetailsChanged = false; // ✅ Reset after save
  if (!fromClose) {
    closeExtraModal();
  }
}


document.querySelector('form').addEventListener('submit', function(e) {
    saveExtraDetails();
});

// ✅ Group (subheading) checkbox toggles all related course checkboxes
document.addEventListener("change", function (e) {
  if (e.target.classList.contains("group-toggle")) {
    const groupClass = e.target.dataset.group;
    const isChecked = e.target.checked;
    document.querySelectorAll(`.${groupClass} input[type='checkbox']`).forEach(cb => {
      cb.checked = isChecked;
    });
  }
});

// Toggle courses when group checkbox is clicked
function toggleGroupCourses(groupCheckbox, groupClass) {
  let courseCheckboxes = document.querySelectorAll(`.${groupClass} input[type='checkbox']`);
  
  // If the group checkbox is checked, select all courses under that group
  if (groupCheckbox.checked) {
    courseCheckboxes.forEach((checkbox) => {
      checkbox.checked = true; // Select course
    });
  } else {
    courseCheckboxes.forEach((checkbox) => {
      checkbox.checked = false; // Unselect course
    });
  }
}

// Reset all course checkboxes (both UG and PG)
function resetCourses() {
  // Uncheck all checkboxes for UG courses
  document.querySelectorAll('#ugCourses input[type="checkbox"]').forEach(cb => cb.checked = false);

  // Uncheck all checkboxes for PG courses
  document.querySelectorAll('#pgCourses input[type="checkbox"]').forEach(cb => cb.checked = false);

  // Also reset the "Select All" checkboxes
  document.getElementById('selectAllUG').checked = false;
  document.getElementById('selectAllPG').checked = false;

  // ✅ Clear the search box
  document.getElementById('courseSearch').value = '';

  // ✅ ✅ Show all hidden course rows again
  document.querySelectorAll('.course-row').forEach(row => {
    row.style.display = 'flex'; // or 'block'
  });
}

function toggleGroup(type) {
  const isChecked = document.getElementById(type === 'ug' ? 'selectAllUG' : 'selectAllPG').checked;
  document.querySelectorAll(`#${type}Courses input[type='checkbox']`).forEach(cb => cb.checked = isChecked);
}
function toggleGroupCourses(groupCheckbox, groupClass) {
  const checkboxes = document.querySelectorAll(`.${groupClass} input[type="checkbox"]`);
  checkboxes.forEach(checkbox => {
    checkbox.checked = groupCheckbox.checked;
  });
}
function toggleRoleContent(index) {
  const content = document.getElementById(`role-content-${index}`);
  const icon = document.getElementById(`toggle-icon-${index}`);
  if (content.style.display === "none") {
    content.style.display = "block";
    icon.textContent = "−";
  } else {
    content.style.display = "none";
    icon.textContent = "+";
  }
}
function resetExtraDetails() {
  // Find the modal container
  const modal = document.getElementById('extraDetailsModal');

  // Reset only standard input[type="text"] inside it
  modal.querySelectorAll('input[type="text"]').forEach(input => {
    // Skip custom fields if needed
    if (input.id !== 'customKey' && input.id !== 'customValue') {
      input.value = '';
    }
  });

  // ✅ Do NOT clear the custom fields container anymore
  // document.getElementById('custom-fields-container').innerHTML = '';
}

function renumberRoles() {
  const roleBlocks = document.querySelectorAll('.role-block');

  // 🛠️ Preserve old selections
  const newSelectedCoursesPerRole = [];
  const newSelectedFieldsPerRole = [];

  roleBlocks.forEach((block, index) => {
    // 🧠 Get old role ID from DOM ID before we update it
    const oldIdMatch = block.id.match(/role-block-(\d+)/);
    const oldId = oldIdMatch ? parseInt(oldIdMatch[1]) : index;

    const newId = index;
    block.id = `role-block-${newId}`;

    // Update header text
    const header = block.querySelector('.role-header strong');
    if (header) header.innerText = `Role ${newId + 1}`;

    // Update toggle icon
    const icon = block.querySelector(`[id^="toggle-icon-"]`);
    if (icon) icon.id = `toggle-icon-${newId}`;

    // Update toggle handler
    const headerDiv = block.querySelector('.role-header');
    if (headerDiv) {
      headerDiv.setAttribute("onclick", `toggleRoleContent(${newId})`);
    }

    // Update delete button
    const delBtn = block.querySelector("button[title='Delete Role']");
    if (delBtn) {
      delBtn.setAttribute("onclick", `removeRole(${newId})`);
    }

    // Update content block ID
    const contentDiv = block.querySelector('.role-content');
    if (contentDiv) {
      contentDiv.id = `role-content-${newId}`;

      // Update IDs inside content
      const courseBtn = contentDiv.querySelector('button[onclick^="openCourseModal"]');
      if (courseBtn) {
        courseBtn.setAttribute("onclick", `openCourseModal(${newId})`);
      }

      const courseInput = contentDiv.querySelector(`[id^="courses_"]`);
      if (courseInput) {
        courseInput.id = `courses_${newId}`;
        courseInput.name = `eligible_courses[]`;
      }

      const courseDisplay = contentDiv.querySelector(`[id^="display_courses_"]`);
      if (courseDisplay) courseDisplay.id = `display_courses_${newId}`;

      const fieldBtn = contentDiv.querySelector('button[onclick^="openFieldSelector"]');
      if (fieldBtn) {
        fieldBtn.setAttribute("onclick", `openFieldSelector(${newId})`);
      }

      const formFieldInput = contentDiv.querySelector(`[id^="form_fields_"]`);
      if (formFieldInput) {
        formFieldInput.id = `form_fields_${newId}`;
        formFieldInput.name = `form_fields[${newId}]`;

        
      }

      const formFieldDisplay = contentDiv.querySelector(`[id^="display_fields_"]`);
      if (formFieldDisplay) formFieldDisplay.id = `display_fields_${newId}`;

      // Remove old form fields section if exists
      const oldFormFieldSection = contentDiv.querySelector('.form-field-section');
      if (oldFormFieldSection) oldFormFieldSection.remove();

      // Re-inject form field buttons only for Role 0
      if (newId === 0) {
        const formFieldWrapper = document.createElement('div');
        formFieldWrapper.className = 'form-field-section';
        formFieldWrapper.innerHTML = `
          <label>Student Form Fields (same for all roles): <span style="color: red">*</span></label>
          <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 10px;">
            <button type="button" class="btn" onclick="openFieldSelector(${newId})">Select Student Form Fields</button>
            <button type="button"  style="padding: 6px 14px; font-size: 13px; color: #650000; background-color: white; border: 2px solid #650000; border-radius: 4px; cursor: pointer ;"
  onmouseover="this.style.backgroundColor='#650000'; this.style.color='white';"
  onmouseout="this.style.backgroundColor='white'; this.style.color='#650000';" onclick="viewFormPreview(${newId})">View Student Form</button>
          </div>
        `;
        contentDiv.appendChild(formFieldWrapper);
      }
    }

    // ✅ Preserve previous selections
    newSelectedCoursesPerRole[newId] = selectedCoursesPerRole[oldId] || [];
    newSelectedFieldsPerRole[newId] = selectedFieldsPerRole[oldId] || [];
  });

  // ✅ Replace old arrays with updated ones
  selectedCoursesPerRole = newSelectedCoursesPerRole;
  selectedFieldsPerRole = newSelectedFieldsPerRole;

  roleIndex = roleBlocks.length;

  // Sync hidden form_fields inputs with selectedFieldsPerRole so view form updates immediately
selectedFieldsPerRole.forEach((fields, idx) => {
  const formFieldInput = document.getElementById(`form_fields_${idx}`);
  if (formFieldInput) {
    formFieldInput.value = JSON.stringify(fields || []);
  }
});

}


function removeRole(roleId) {
  const roleBlock = document.getElementById(`role-block-${roleId}`);
  if (roleBlock) {
    roleBlock.remove();
    renumberRoles();
    saveAllFormData();
  }
}



function addCustomExtraField() {
  const key = document.getElementById('customKey').value.trim();
  const value = document.getElementById('customValue').value.trim();

  if (key && value) {
    const container = document.getElementById('custom-fields-container');

    const fieldDiv = document.createElement('div');
    fieldDiv.className = 'custom-extra-field';
    fieldDiv.dataset.key = key;
    fieldDiv.dataset.value = value;

    // Styling for field
    fieldDiv.style.display = 'flex';
    fieldDiv.style.alignItems = 'flex-start'; // top align for multi-line
    fieldDiv.style.justifyContent = 'space-between';
    fieldDiv.style.background = '#f8f8f8';
    fieldDiv.style.padding = '6px 10px';
    fieldDiv.style.marginBottom = '5px';
    fieldDiv.style.border = '1px solid #ccc';
    fieldDiv.style.borderRadius = '4px';
    fieldDiv.style.whiteSpace = 'pre-wrap'; // preserves line breaks

    const fieldText = document.createElement('span');
    fieldText.style.flex = '1';
    fieldText.style.wordBreak = 'break-word';
    fieldText.style.marginRight = '10px';

fieldText.innerHTML = `<strong>${key}</strong>: ${value.replace(/\n/g, '<br>')}`;

    const deleteBtn = document.createElement('button');
    deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
    deleteBtn.title = 'Remove this field';
    deleteBtn.style.background = 'transparent';
    deleteBtn.style.border = 'none';
    deleteBtn.style.cursor = 'pointer';
    deleteBtn.style.fontSize = '14px';
    deleteBtn.style.color = '#e74c3c';
    deleteBtn.onclick = () => container.removeChild(fieldDiv);

    fieldDiv.appendChild(fieldText);
    fieldDiv.appendChild(deleteBtn);

    container.appendChild(fieldDiv);

    document.getElementById('customKey').value = '';
    document.getElementById('customValue').value = '';
  } else {
    alert("Please enter both a field name and value.");
  }
}



let customDropdownValues = {};

function addNewField(tab) {
  // Save current custom dropdown input values before re-rendering
  document.querySelectorAll('.custom-dropdown').forEach(input => {
    const fieldName = input.dataset.fieldName;
    if (fieldName) {
      customDropdownValues[fieldName] = input.value;
    }
  });

  const input = document.getElementById('newFieldName');
  const newFieldName = input.value.trim();

  if (!newFieldName) {
    alert("Please enter a field name.");
    return;
  }

  if (fields[tab].includes(newFieldName)) {
    alert("Field already exists.");
    return;
  }

  fields[tab].push(newFieldName);

  // Clear the new field input box
  input.value = '';

  switchTab(tab); 
   // Re-render the UI with the new field added
}

</script>

<script>
function validateDriveForm() {
  const roleBlocks = document.querySelectorAll('.role-block');

  // ✅ Check if at least one role is added
  if (roleBlocks.length === 0) {
    alert('Please add at least one Role.');
    return false;
  }

  const roleNames = new Set(); // Track role names to detect duplicates

  // ✅ Check each role name
  for (const block of roleBlocks) {
    const roleNameInput = block.querySelector('input[name="role_name[]"]');
    if (!roleNameInput || roleNameInput.value.trim() === '') {
      alert('Please fill in the Designation for each Role.');
      roleNameInput?.focus();
      return false;
    }

    const roleName = roleNameInput.value.trim().toLowerCase();
    if (roleNames.has(roleName)) {
      alert(`Duplicate Designation detected: "${roleNameInput.value}". Each role must have a unique name.`);
      roleNameInput.focus();
      return false;
    }
    roleNames.add(roleName);
  }

  // ✅ Check Flatpickr dates
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

if (closeDateObj && openDateObj && closeDateObj <= openDateObj) {
    alert("Form Close Date must be after Open Date.");
    document.getElementById('close_date').focus();
    return false;
}

  // ✅ Check if Student Form Fields (Role 1) is filled
const formFieldsInput = document.getElementById('form_fields_0');
if (formFieldsInput) {
  let fields = [];
  try {
    fields = JSON.parse(formFieldsInput.value);
  } catch (e) {
    fields = [];
  }
  if (!fields || fields.length === 0) {
    alert("Please select student form fields (mandatory).");
    formFieldsInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return false;
  }
}


  return true;
}
</script>










<script>
function viewFormPreview(roleId) {
  const formFields = document.getElementById(`form_fields_${roleId}`).value;
  let selectedFields = [];

  try {
    selectedFields = JSON.parse(formFields);
  } catch (e) {
    console.error('Invalid JSON in form_fields_', e);
    selectedFields = [];
  }
// --- Sort selected fields according to master fields order ---
const FIELD_ORDER = [
  ...fields.personal,
  ...fields.education,
  ...fields.work,
  ...fields.others
];

// Move Resume to the very end if it exists
selectedFields.sort((a, b) => {
  const nameA = typeof a === 'string' ? a : a.name;
  const nameB = typeof b === 'string' ? b : b.name;

  if (nameA === 'Upload Resume (PDF)') return 1;  // always last
  if (nameB === 'Upload Resume (PDF)') return -1;

  const indexA = FIELD_ORDER.indexOf(nameA);
  const indexB = FIELD_ORDER.indexOf(nameB);

  return (indexA === -1 ? 999 : indexA) - (indexB === -1 ? 999 : indexB);
});

  let html = '';
  selectedFields.forEach(field => {
    const fieldName = typeof field === 'string' ? field : field.name;
    const isRequired = field.mandatory ? '<span style="color:red">*</span>' : '';

    const preset = fieldPresets[fieldName] || {};
    let isPreset = !!fieldPresets[fieldName];
    let options = [];

    // Get options: prefer custom if exists
    if (field.options && field.options.trim() !== '') {
      options = field.options.split(',').map(o => o.trim()).filter(o => o);
    } else if (preset.options) {
      options = preset.options;
    }

    html += `<label>${fieldName} ${isRequired}</label><br>`;

    if (isPreset) {
      // ✅ Always use preset type for preset fields
      if (preset.type === 'file') {
        html += `<input type="file" ${field.mandatory ? 'required' : ''}><br><br>`;
      }
      else if (preset.type === 'date') {
        html += `<input type="date" ${field.mandatory ? 'required' : ''}><br><br>`;
      }
      else if (preset.type === 'month') {
  html += `<input type="month" ${field.mandatory ? 'required' : ''}><br><br>`;
}

      else if (preset.type === 'dropdown' && options.length > 0) {
        html += `<select ${field.mandatory ? 'required' : ''}>`;
        options.forEach(opt => {
          html += `<option value="${opt}">${opt}</option>`;
        });
        html += `</select><br><br>`;
      }
      else if (preset.type === 'radio' && options.length > 0) {
        options.forEach(opt => {
          html += `<label><input type="radio" name="${fieldName}" ${field.mandatory ? 'required' : ''}> ${opt}</label> `;
        });
        html += `<br><br>`;
      }
      else if (preset.type === 'checkbox' && options.length > 0) {
        options.forEach(opt => {
          html += `<label><input type="checkbox" name="${fieldName}[]" ${field.mandatory ? 'required' : ''}> ${opt}</label> `;
        });
        html += `<br><br>`;
      }
      else {
        html += `<input type="text" ${field.mandatory ? 'required' : ''}><br><br>`;
      }

    } else {
      // ✅ Use special logic for **custom field**
      if (options.length === 0) {
        html += `<input type="text" ${field.mandatory ? 'required' : ''}><br><br>`;
      } else if (options.length === 1) {
        html += `<label><input type="checkbox" name="${fieldName}" ${field.mandatory ? 'required' : ''}> ${options[0]}</label><br><br>`;
      } else if (options.length === 2) {
        html += `<select ${field.mandatory ? 'required' : ''}>`;
        options.forEach(opt => {
          html += `<option value="${opt}">${opt}</option>`;
        });
        html += `</select><br><br>`;
      } else {
        options.forEach(opt => {
          html += `<label><input type="checkbox" name="${fieldName}[]" ${field.mandatory ? 'required' : ''}> ${opt}</label> `;
        });
        html += `<br><br>`;
      }
    }
  });
  // --- Append static Resume field at the end ---
  html += `<label>Upload Resume (PDF/Doc) <span style="color:red">*</span>:</label><br>
           <input type="file" name="resume"  required><br>
           <small style="color:#666;">Filename should be like Rahul_CollegeName.pdf (Max 100MB)</small><br><br>`;

  document.getElementById('formPreviewContent').innerHTML = html;
  document.getElementById('formPreviewModal').style.display = 'block';
}
</script>

<script>
function closeFormPreview() {
  document.getElementById('formPreviewModal').style.display = 'none';
}
document.addEventListener('keydown', function(event) {
  const isInRoleBlock = event.target.closest('.role-block');
  if (isInRoleBlock && event.key === 'Enter') {
    event.preventDefault();
  }
});

function toggleRoleContent(roleId) {
  const content = document.getElementById(`role-content-${roleId}`);
  const icon = document.getElementById(`toggle-icon-${roleId}`);
  if (content.style.display === 'none' || content.style.display === '') {
    content.style.display = 'block';
    icon.textContent = '-';
  } else {
    content.style.display = 'none';
    icon.textContent = '+';
  }
}


</script>

<script>
  window.addEventListener('DOMContentLoaded', () => {
    const msg = document.getElementById('successMessage');
    if (msg) {
      setTimeout(() => {
        msg.style.display = 'none';
      }, 8000); // Hide after 3 seconds
    }
  });


function validateAllEligibleCourses() { 
  const allInputs = document.querySelectorAll('input[name="eligible_courses[]"]');
  for (let i = 0; i < allInputs.length; i++) {
    if (!allInputs[i].value.trim()) {
      alert(`Please select eligible courses for role ${i + 1}.`);
      allInputs[i].focus();
      return false; // Stop submission
    }
  }
  return true; // OK
}


</script>
<script>
function resetFormFields() {
  // Reset search query in the search box
  const searchBox = document.getElementById('fieldFilter');
  if (searchBox) searchBox.value = '';

  // Get all rows in the current modal tab
  const container = document.getElementById('modal-fields');
  const checkboxes = container.querySelectorAll('input[type="checkbox"]');
  checkboxes.forEach(cb => cb.checked = false);

  // Clear text inputs in current tab
  const textInputs = container.querySelectorAll('input[type="text"], textarea');
  textInputs.forEach(input => input.value = '');

  // Clear selected fields for current role only in this tab
  if (selectedFieldsPerRole[currentRoleId]) {
    selectedFieldsPerRole[currentRoleId] = [];
  }

  // Re-render the current tab to refresh rows
  filterFields();
}
</script>


<script>
let openDateObj = null;
let closeDateObj = null;

openDatePicker = flatpickr("#open_date", {
    enableTime: true,
    dateFormat: "d-m-Y H:i",
    onChange: function(selectedDates) {
        if (selectedDates.length > 0) {
            openDateObj = selectedDates[0];
            closeDatePicker.set('minDate', openDateObj);
        }
    },
    onReady: function(selectedDates, dateStr, instance) {
        if (selectedDates.length > 0) openDateObj = selectedDates[0];

       let closeBtn = document.createElement("span");
            closeBtn.innerHTML = "&times;";
            closeBtn.className = "flatpickr-close";
            closeBtn.onclick = () => instance.close();
            instance.calendarContainer.appendChild(closeBtn);
        
        
    }
    
});

closeDatePicker = flatpickr("#close_date", {
    enableTime: true,
    dateFormat: "d-m-Y H:i",
    onChange: function(selectedDates) {
        if (selectedDates.length > 0) closeDateObj = selectedDates[0];
    },
    onReady: function(selectedDates, dateStr, instance) {
        if (selectedDates.length > 0) closeDateObj = selectedDates[0];

 let closeBtn = document.createElement("span");
            closeBtn.innerHTML = "&times;";
            closeBtn.className = "flatpickr-close";
            closeBtn.onclick = () => instance.close();
            instance.calendarContainer.appendChild(closeBtn);
    }
});

</script>


<script>
document.querySelector("form").addEventListener("submit", function (e) {
  const minPercentInputs = document.querySelectorAll('input[name="min_percentage[]"]');
  let hasError = false;
  const maxPercentage = 100; // adjust if your DB allows more, like 255

  minPercentInputs.forEach((input, index) => {
    const value = parseFloat(input.value.trim());

    if (input.value.trim() !== "" && (isNaN(value) || value < 0 || value > maxPercentage)) {
      alert(`Role ${index + 1}: Please enter a valid percentage between 0 and ${maxPercentage}`);
      hasError = true;
    }
  });

  if (hasError) {
    e.preventDefault(); // prevent form submission
  }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Make Enter behave like Tab, but allow Enter in <textarea>
  document.getElementById('extraDetailsModal').addEventListener('keydown', function (e) {
    const active = document.activeElement;

    // Allow Enter and Shift+Enter in textareas for multiline input
    if (active.tagName.toLowerCase() === 'textarea') {
      return; // let Enter behave normally in textareas
    }

    if (e.key === 'Enter') {
      const focusable = Array.from(this.querySelectorAll('input, textarea, select'))
        .filter(el => !el.disabled && el.offsetParent !== null);

      const index = focusable.indexOf(active);
      if (index > -1 && index < focusable.length - 1) {
        e.preventDefault();
        focusable[index + 1].focus();
      }
    }
  });
});
</script>






















<script>
const storageKey = 'addDriveFormData';

function saveSimpleInputs() {
  const form = document.querySelector('form');
  const data = {};

  // Save normal inputs, selects, textarea
  form.querySelectorAll('input, select, textarea').forEach(input => {
    if (input.type === 'file') return; // skip files
    if (input.name && !input.closest('#roles-container') && !input.closest('#extraDetailsModal')) {
      data[input.name] = (input.type === 'checkbox' || input.type === 'radio') ? input.checked : input.value;
    }
  });

  // Save custom extra fields
  const customFieldsContainer = document.getElementById('custom-fields-container');

  if (customFieldsContainer) {
    // Assuming each field is a div with data-key and data-value attributes or some structure
    const customFields = [];
    customFieldsContainer.querySelectorAll('.custom-extra-field').forEach(div => {
      // Extract key and value — adjust selectors based on your markup
      const key = div.getAttribute('data-key') || "";
      const value = div.getAttribute('data-value') || "";
      if (key) {
        customFields.push({ key, value });
      }
    });
    data['customExtraFields'] = customFields; // store array under a special key
  }

  return data;
}

function loadSimpleInputs(data) {
  const form = document.querySelector('form');
  Object.entries(data || {}).forEach(([name, value]) => {
    // Skip customExtraFields special key here — handle separately
    if (name === 'customExtraFields') return;

    const input = form.querySelector(`[name="${name}"]`);
    if (!input) return;
    if (input.type === 'checkbox' || input.type === 'radio') {
      input.checked = value;
    } else {
      input.value = value;
    }
  });

  // Now load custom extra fields
  if (data.customExtraFields && Array.isArray(data.customExtraFields)) {
    const container = document.getElementById('custom-fields-container');

    if (container) {
  container.innerHTML = ''; // clear old fields
  data.customExtraFields.forEach(({ key, value }) => {
    const fieldDiv = document.createElement('div');
    fieldDiv.className = 'custom-extra-field';
    fieldDiv.setAttribute('data-key', key);
    fieldDiv.setAttribute('data-value', value);

    // Styling for field div (same as in addCustomExtraField)
    fieldDiv.style.display = 'flex';
    fieldDiv.style.alignItems = 'flex-start';
    fieldDiv.style.justifyContent = 'space-between';
    fieldDiv.style.background = '#f8f8f8';
    fieldDiv.style.padding = '6px 10px';
    fieldDiv.style.marginBottom = '5px';
    fieldDiv.style.border = '1px solid #ccc';
    fieldDiv.style.borderRadius = '4px';
    fieldDiv.style.whiteSpace = 'pre-wrap';

    // Create span for text
    const fieldText = document.createElement('span');
    fieldText.style.flex = '1';
    fieldText.style.wordBreak = 'break-word';
    fieldText.style.marginRight = '10px';
    fieldText.innerHTML = `<strong>${key}</strong>: ${value.replace(/\n/g, '<br>')}`;

    // Create delete button
    const deleteBtn = document.createElement('button');
    deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
    deleteBtn.title = 'Remove this field';
    deleteBtn.style.background = 'transparent';
    deleteBtn.style.border = 'none';
    deleteBtn.style.cursor = 'pointer';
    deleteBtn.style.fontSize = '14px';
    deleteBtn.style.color = '#e74c3c';
    deleteBtn.onclick = () => container.removeChild(fieldDiv);

    // Append text and button
    fieldDiv.appendChild(fieldText);
    fieldDiv.appendChild(deleteBtn);

    // Append to container
    container.appendChild(fieldDiv);
  });
}

  }
}


function getExtraDetailsData() {
  const ids = ['workMode', 'officeAddress', 'interviewDetails', 'eligibilityNote', 'vacancies', 'duration', 'stipend', 'postInternship', 'timings', 'whatsapp', 'additionalInfo'];
  const data = {};
  ids.forEach(id => {
    const el = document.getElementById(id);
    if (el) data[id] = el.value || "";
  });
  return data;
}

function loadExtraDetailsData(data) {
  const ids = ['workMode', 'officeAddress', 'interviewDetails', 'eligibilityNote', 'vacancies', 'duration', 'stipend', 'postInternship', 'timings', 'whatsapp', 'additionalInfo'];
  ids.forEach(id => {
    const el = document.getElementById(id);
    if (el && data[id] !== undefined) {
      el.value = data[id];
    }
  });
}

function getRolesData() {
  const roles = [];
  const container = document.getElementById('roles-container');
  container.querySelectorAll('.role-block').forEach(roleDiv => {
    const role = {
      role_name: roleDiv.querySelector('input[name="role_name[]"]')?.value || "",
      ctc: roleDiv.querySelector('input[name="ctc[]"]')?.value || "",
      stipend: roleDiv.querySelector('input[name="stipend[]"]')?.value || "",
      offer_type: roleDiv.querySelector('select[name="offer_type[]"]')?.value || "",
      sector: roleDiv.querySelector('select[name="sector[]"]')?.value || "",
      eligible_courses: roleDiv.querySelector('input[name="eligible_courses[]"]')?.value || "",
      min_percentage: roleDiv.querySelector('input[name="min_percentage[]"]')?.value || "",
      form_fields: roleDiv.querySelector('input[name^="form_fields"]')?.value || ""
    };
    roles.push(role);
  });
  return roles;
}

function loadRolesData(roles) {
  const container = document.getElementById('roles-container');
  container.innerHTML = '';
  roleIndex = 0;

  roles.forEach((role, i) => {
    addRole();
    const lastRole = container.querySelector('.role-block:last-child');
    lastRole.querySelector('input[name="role_name[]"]').value = role.role_name;
    lastRole.querySelector('input[name="ctc[]"]').value = role.ctc;
    lastRole.querySelector('input[name="stipend[]"]').value = role.stipend;
    lastRole.querySelector('select[name="offer_type[]"]').value = role.offer_type;
    lastRole.querySelector('select[name="sector[]"]').value = role.sector;
    lastRole.querySelector('input[name="eligible_courses[]"]').value = role.eligible_courses;
    lastRole.querySelector('input[name="min_percentage[]"]').value = role.min_percentage;
    lastRole.querySelector('input[name^="form_fields"]').value = role.form_fields;

    // Update display boxes if they exist
    const displayCourses = document.getElementById(`display_courses_${i}`);
    if (displayCourses && selectedCoursesPerRole?.[i]) {
      displayCourses.innerText = selectedCoursesPerRole[i].join(', ');
    }

    const displayFields = document.getElementById(`display_fields_${i}`);
    if (displayFields && selectedFieldsPerRole?.[i]) {
      displayFields.innerText = selectedFieldsPerRole[i].map(f => f.name).join(', ');
    }
  });
}

function saveAllFormData() {
  const data = {
    simpleInputs: saveSimpleInputs(),
    roles: getRolesData(),
    extraDetails: getExtraDetailsData(),
    selectedCoursesPerRole: selectedCoursesPerRole || {},
    selectedFieldsPerRole: selectedFieldsPerRole || {},
    mandatoryStatePerRole: mandatoryStatePerRole || {},
    fields: fields || {} // 🔧 Save updated fields (with custom fields)
  };
  localStorage.setItem(storageKey, JSON.stringify(data));
}


function loadAllFormData() {
  const saved = localStorage.getItem(storageKey);
  if (!saved) return;
  const data = JSON.parse(saved);

  loadSimpleInputs(data.simpleInputs || {});
  loadRolesData(data.roles || []);
  loadExtraDetailsData(data.extraDetails || {});

  if (data.selectedCoursesPerRole) selectedCoursesPerRole = data.selectedCoursesPerRole;
  if (data.selectedFieldsPerRole) selectedFieldsPerRole = data.selectedFieldsPerRole;
  if (data.mandatoryStatePerRole) mandatoryStatePerRole = data.mandatoryStatePerRole;
  if (data.fields) fields = data.fields; // 🔧 Restore updated fields
}

function clearAllFormData() {
  localStorage.removeItem(storageKey);
}

document.addEventListener('DOMContentLoaded', () => {
  loadAllFormData();

  const form = document.querySelector('form');

  // Save on any input or change (text, select, checkbox, radio)
  form.addEventListener('input', saveAllFormData);
  form.addEventListener('change', saveAllFormData);

  // Also listen for clicks on buttons (reset, delete, add etc)
  form.addEventListener('click', (e) => {
    const target = e.target;
    if (target.tagName === 'BUTTON' || (target.tagName === 'INPUT' && (target.type === 'button' || target.type === 'reset' || target.type === 'submit'))) {
      if (target.type === 'reset') {
        // Clear localStorage immediately on reset
        clearAllFormData();
        // Delay saving after reset (optional)
        setTimeout(() => {
          saveAllFormData();
        }, 100);
      } else {
        // For other buttons (delete/add), save immediately after click
        saveAllFormData();
      }
    }
  });

  // Clear saved data on form submit (normal behavior)
  form.addEventListener('submit', clearAllFormData);
});


document.getElementById('driveForm').addEventListener('submit', function() {
    // Save Role 1 selected fields before form submission
    const role1Input = document.getElementById('form_fields_0');
    if (role1Input) {
        role1Input.value = JSON.stringify(selectedFieldsPerRole[0] || []);
    }
});

</script>

<?php include("footer.php"); ?>
















