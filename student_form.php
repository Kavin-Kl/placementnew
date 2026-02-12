<?php
// ✅ Redirected success message page
if (isset($_GET['submitted']) && $_GET['submitted'] == '1') {
  echo <<<HTML
  <!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>Form Submitted</title>
  </head>
  <body>
    
    <div class="msg-box" style="text-align: center;">
      <h2 style="color:green;">Form Submitted Successfully!</h2>
      <p>Thank you for submitting your placement information.</p>
    </div>
  </body>
  </html>
  HTML;
  exit;
}
?>
<?php
ob_start();
session_start();
include("config.php");
$available_fields = [
    "company_name" => "Company Name",
    "full_name" => "Full Name",
    "reg_no" => "Register No",
    "upid" => "Placement ID",
    "email" => "Email",
    "role" => " Job Role",
    "course_name" => "Course Name",
    "phone_no" => "Phone No",
    "offer_letter_received" => "Offer Letter Received",
    "uploaded_offer_letter" => "Upload Offer Letter",
    "intent_letter_received" => "Intent Letter Received",
    "uploaded_intent_letter" => "Upload intent Letter",
    "onboarding_date" => "Joining Date",
    "passing_year" => "Year of Passing",
    "campus_type" => "On/Off Campus",
    "register_type" => "Register Type", // Added new field
    "photo" => "Upload Photo",
    "comments" => "Comments"
];

$success = false;
if (!isset($_GET['short']) && isset($_SERVER['REQUEST_URI'])) {
    $uri = $_SERVER['REQUEST_URI'];
    if (preg_match('/student_form(?:\.php)?\/(form_[a-zA-Z0-9]+|Overall_Placed_Students)/', $uri, $matches)) {
    $_GET['short'] = $matches[1];
}

    }


// ✅ Process shortcode if present
if (isset($_GET['short'])) {
    $shortcode = $_GET['short'];
    $stmt = $conn->prepare("SELECT fields, custom_field_meta FROM form_links WHERE shortcode = ?");
    $stmt->bind_param("s", $shortcode);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();

        $fields_from_db = trim($row['fields']);
        $fieldList = explode(',', $fields_from_db);
        $fieldList = array_map(function($field) {
            return preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower(trim($field)));
        }, $fieldList);

        $customFieldsRaw = $row['custom_field_meta'] ?? '';
        $customFieldMeta = json_decode($customFieldsRaw, true);
        $customFieldMap = [];

        if (is_array($customFieldMeta)) {
            foreach ($customFieldMeta as $item) {
                if (!empty($item['label'])) {
                    $cleanCol = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower(trim($item['label'])));
                    $customFieldMap[$cleanCol] = $item['label'];
                }
            }
        }

        // You now have $fieldList and $customFieldMap ready for form rendering

    } else {
        die("<h3>❌ Invalid or expired form link</h3>");
    }
} else {
    die("<h3>❌ No form link provided. Please use a valid form URL.</h3>");
}

 // FORM SUBMIT HANDLING
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_submit'])) {
    echo "<script>console.log('POST STARTED');</script>";
    $campus_type = $_POST['campus_type'] ?? '';
    $register_type = $_POST['register_type'] ?? ''; // Get register_type
    $reg_no = trim($_POST['reg_no'] ?? '');
    $upid = trim($_POST['upid'] ?? '');
    $full_name = $_POST['full_name'] ?? '';
    $company_name = $_POST['company_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone_no = $_POST['phone_no'] ?? '';
    $course_name = $_POST['course_name'] ?? '';
    $role = $_POST['role'] ?? '';
    $comments = $_POST['comments'] ?? '';
    $passing_year = $_POST['passing_year'] ?? '';
    $field_errors = []; // Initialize field_errors array
    $success = false;
    $error = ''; // Global error message

    // ✅ Override UPID if not registered
    if ($campus_type === 'on' && $register_type === 'not_registered') {
        $upid = null;
        $_POST['upid'] = null; // Ignore whatever the user entered
    }

    error_log("🟡 Campus Type Received: " . $campus_type);

    if ($campus_type === 'on') {
        // Conditional validation for reg_no and upid based on register_type
        if ($register_type === 'registered') {
            $stmt = $conn->prepare("SELECT * FROM students WHERE reg_no = ? AND upid = ?");
            $stmt->bind_param("ss", $reg_no, $upid);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result || $result->num_rows === 0) {
               if (!isset($field_errors['reg_no'])) {
                   $field_errors['reg_no'] = "Invalid Register No.";
               }
               if (!isset($field_errors['upid'])) {
                   $field_errors['upid'] = "Invalid UPI ID.";
               }
           }
        }
        if (empty($field_errors)) { // Proceed only if initial validation (if any) passed
            $checkStmt = $conn->prepare("SELECT * FROM on_off_campus_students WHERE reg_no = ?");
            $checkStmt->bind_param("s", $reg_no);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult && $checkResult->num_rows > 0) {
                $error = "You have already submitted the form."; // Global error
            } else {
                $offer_letter_paths = [];
                $intent_letter_path = '';
                $photo_path = '';

                // ✅ Offer Letter Upload Handling
if (isset($_POST['offer_letter_received']) && $_POST['offer_letter_received'] === 'yes') {
    if (!isset($_FILES["uploaded_offer_letter"]) || empty($_FILES["uploaded_offer_letter"]["name"][0])) {
        $field_errors['uploaded_offer_letter'] = "Offer Letter(s) are required if marked as received.";
    } else {
        $offer_letter_paths = [];
        $upload_dir = "uploads/";
        $max_file_size = 10 * 1024 * 1024; // 10MB
        $uploaded_files = $_FILES["uploaded_offer_letter"];
        

        // ✅ Sanitize user inputs for file name
        $safe_full_name = preg_replace("/[^a-zA-Z0-9]/", "_", $_POST['full_name'] ?? '');
        $safe_reg_no = preg_replace("/[^a-zA-Z0-9]/", "_", $_POST['reg_no'] ?? '');
        $safe_company_name = preg_replace("/[^a-zA-Z0-9]/", "_", $_POST['company_name'] ?? '');

        foreach ($uploaded_files['name'] as $index => $original_name) {
            $tmp_name = $uploaded_files['tmp_name'][$index];
            $error = $uploaded_files['error'][$index];
            $size = $uploaded_files['size'][$index];
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

            // ✅ Validation
            if ($error !== 0) {
                $field_errors['uploaded_offer_letter'] = "One or more Offer Letters failed to upload.";
                break;
            }

            if ($ext !== 'pdf') {
                $field_errors['uploaded_offer_letter'] = "Only PDF files are allowed.";
                break;
            }

            if ($size > $max_file_size) {
                $field_errors['uploaded_offer_letter'] = "Each file must be under 10MB.";
                break;
            }

            // ✅ Format: Name_RegisterNumber_CompanyName_1.pdf
            $file_number = $index + 1;
            $filename = "{$safe_full_name}_{$safe_reg_no}_{$safe_company_name}_offer_Letter_{$file_number}.pdf";
            $target = $upload_dir . $filename;

            if (!move_uploaded_file($tmp_name, $target)) {
                $field_errors['uploaded_offer_letter'] = "Failed to save one or more Offer Letters.";
                break;
            }

            $offer_letter_paths[] = $target;
        }
    }
}

                // ✅ Intent Letter Upload Handling
        if (isset($_POST['intent_letter_received']) && $_POST['intent_letter_received'] === 'yes') {
            if (!isset($_FILES["uploaded_intent_letter"]) || $_FILES["uploaded_intent_letter"]["error"] !== 0) {
                $field_errors['uploaded_intent_letter'] = "Intent Letter is required if marked as received.";
            } else {
                $safe_full_name = preg_replace("/[^a-zA-Z0-9]/", "_", $_POST['full_name'] ?? '');
$safe_reg_no = preg_replace("/[^a-zA-Z0-9]/", "_", $_POST['reg_no'] ?? '');
$safe_company_name = preg_replace("/[^a-zA-Z0-9]/", "_", $_POST['company_name'] ?? '');
$filename = "{$safe_full_name}_{$safe_reg_no}_{$safe_company_name}_intent_Letter.pdf";
                $target = "uploads/" . $filename;
                $ext = strtolower(pathinfo($_FILES["uploaded_intent_letter"]["name"], PATHINFO_EXTENSION));

                if ($ext !== 'pdf') {
                    $field_errors['uploaded_intent_letter'] = "Only PDF is allowed for Intent Letter.";
                } elseif ($_FILES["uploaded_intent_letter"]["size"] > 10 * 1024 * 1024) {
                    $field_errors['uploaded_intent_letter'] = "Intent Letter must be less than 10MB.";
                } elseif (!move_uploaded_file($_FILES["uploaded_intent_letter"]["tmp_name"], $target)) {
                    $field_errors['uploaded_intent_letter'] = "Intent Letter upload failed.";
                } else {
                    $intent_letter_path = $target;
                }
            }
        }

        // ✅ Photo Upload (Optional)
        if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] === 0) {
            $ext = strtolower(pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION));
            $safe_full_name = preg_replace("/[^a-zA-Z0-9]/", "_", $_POST['full_name'] ?? '');
$safe_reg_no = preg_replace("/[^a-zA-Z0-9]/", "_", $_POST['reg_no'] ?? '');
$safe_company_name = preg_replace("/[^a-zA-Z0-9]/", "_", $_POST['company_name'] ?? '');
$filename = "{$safe_full_name}_{$safe_reg_no}_{$safe_company_name}_photo.{$ext}";
            $target = "uploads/" . $filename;

            if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $field_errors['photo'] = "Invalid image format for photo.";
            } elseif ($_FILES["photo"]["size"] > 1 * 1024 * 1024) {
                $field_errors['photo'] = "Photo must be less than 1MB.";
            } elseif (!move_uploaded_file($_FILES["photo"]["tmp_name"], $target)) {
                $field_errors['photo'] = "Photo upload failed.";
            } else {
                $photo_path = $target;
            }
        } elseif (isset($_FILES["photo"]) && $_FILES["photo"]["error"] !== UPLOAD_ERR_NO_FILE) {
            // Handle other file upload errors for photo if it was attempted
            $field_errors['photo'] = "Photo upload error: " . $_FILES["photo"]["error"];
        }
                // ✅ Optional Onboarding Date
                $onboarding_date = trim($_POST['onboarding_date'] ?? '');
                if ($onboarding_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $onboarding_date)) {
                    $field_errors['onboarding_date'] = "Invalid date format. Use YYYY-MM-DD.";
                    $onboarding_date = null;
                }
                $_POST['onboarding_date'] = $onboarding_date !== '' ? $onboarding_date : null;

                // ✅ Add final file paths to $_POST
                $_POST['offer_letter_file'] = implode(',', $offer_letter_paths);
                $_POST['intent_letter_file'] = $intent_letter_path;
                $_POST['photo_path'] = $photo_path;
                $_POST['campus_type'] = $campus_type;
                $_POST['register_type'] = $register_type; // Add register_type to $_POST

                $skipFields = ['form_submit', 'declaration'];
                $fields = array_keys($_POST);
                $finalFields = array_diff($fields, $skipFields);

                if (empty($field_errors)) { // Only proceed with DB insert if no field errors
                    $columns = implode(',', array_map(function($f) { return "`$f`"; }, $finalFields)); // Quote column names
                    $values = implode(',', array_map(function ($f) use ($conn) {
                        $val = $_POST[$f];
                        if (is_null($val) || $val === '') return "NULL";
                        return "'" . $conn->real_escape_string($val) . "'";
                    }, $finalFields));

                    $insert = "INSERT INTO on_off_campus_students ($columns) VALUES ($values)";
                    if ($conn->query($insert)) {
                        header("Location: on_off_campus.php?submitted=1");
                        exit;
                    } else {
                        $error = "Database insert failed: " . $conn->error; // Global error
                    }
                }
            }
        }
    } elseif ($campus_type === 'off') {
        error_log("✅ Off Campus Block Triggered");

        $offer_letter_paths = [];
        $intent_letter_path = '';
        $photo_path = '';

// ✅ Offer Letter Upload Handling
if (isset($_POST['offer_letter_received']) && $_POST['offer_letter_received'] === 'yes') {
    if (!isset($_FILES["uploaded_offer_letter"]) || empty($_FILES["uploaded_offer_letter"]["name"][0])) {
        $field_errors['uploaded_offer_letter'] = "Offer Letter(s) are required if marked as received.";
    } else {
        $offer_letter_paths = [];
        $upload_dir = "uploads/";
        $max_file_size = 10 * 1024 * 1024; // 10MB
        $uploaded_files = $_FILES["uploaded_offer_letter"];
        

        // ✅ Sanitize user inputs for file name
        $safe_full_name = preg_replace("/[^a-zA-Z0-9]/", "_", $_POST['full_name'] ?? '');
        $safe_reg_no = preg_replace("/[^a-zA-Z0-9]/", "_", $_POST['reg_no'] ?? '');
        $safe_company_name = preg_replace("/[^a-zA-Z0-9]/", "_", $_POST['company_name'] ?? '');

        foreach ($uploaded_files['name'] as $index => $original_name) {
            $tmp_name = $uploaded_files['tmp_name'][$index];
            $error = $uploaded_files['error'][$index];
            $size = $uploaded_files['size'][$index];
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

            // ✅ Validation
            if ($error !== 0) {
                $field_errors['uploaded_offer_letter'] = "One or more Offer Letters failed to upload.";
                break;
            }

            if ($ext !== 'pdf') {
                $field_errors['uploaded_offer_letter'] = "Only PDF files are allowed.";
                break;
            }

            if ($size > $max_file_size) {
                $field_errors['uploaded_offer_letter'] = "Each file must be under 10MB.";
                break;
            }

            // ✅ Format: Name_RegisterNumber_CompanyName_1.pdf
            $file_number = $index + 1;
            $filename = "{$safe_full_name}_{$safe_reg_no}_{$safe_company_name}_offer_Letter_{$file_number}.pdf";
            $target = $upload_dir . $filename;

            if (!move_uploaded_file($tmp_name, $target)) {
                $field_errors['uploaded_offer_letter'] = "Failed to save one or more Offer Letters.";
                break;
            }

            $offer_letter_paths[] = $target;
        }
    }
}

        // ✅ Intent Letter Upload Handling
        if (isset($_POST['intent_letter_received']) && $_POST['intent_letter_received'] === 'yes') {
            if (!isset($_FILES["uploaded_intent_letter"]) || $_FILES["uploaded_intent_letter"]["error"] !== 0) {
                $field_errors['uploaded_intent_letter'] = "Intent Letter is required if marked as received.";
            } else {
                $safe_full_name = preg_replace("/[^a-zA-Z0-9]/", "_", $_POST['full_name'] ?? '');
$safe_reg_no = preg_replace("/[^a-zA-Z0-9]/", "_", $_POST['reg_no'] ?? '');
$safe_company_name = preg_replace("/[^a-zA-Z0-9]/", "_", $_POST['company_name'] ?? '');
$filename = "{$safe_full_name}_{$safe_reg_no}_{$safe_company_name}_intent_Letter.pdf";
                $target = "uploads/" . $filename;
                $ext = strtolower(pathinfo($_FILES["uploaded_intent_letter"]["name"], PATHINFO_EXTENSION));

                if ($ext !== 'pdf') {
                    $field_errors['uploaded_intent_letter'] = "Only PDF is allowed for Intent Letter.";
                } elseif ($_FILES["uploaded_intent_letter"]["size"] > 10 * 1024 * 1024) {
                    $field_errors['uploaded_intent_letter'] = "Intent Letter must be less than 10MB.";
                } elseif (!move_uploaded_file($_FILES["uploaded_intent_letter"]["tmp_name"], $target)) {
                    $field_errors['uploaded_intent_letter'] = "Intent Letter upload failed.";
                } else {
                    $intent_letter_path = $target;
                }
            }
        }

        // ✅ Photo Upload (Optional)
        if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] === 0) {
            $ext = strtolower(pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION));
            $safe_full_name = preg_replace("/[^a-zA-Z0-9]/", "_", $_POST['full_name'] ?? '');
$safe_reg_no = preg_replace("/[^a-zA-Z0-9]/", "_", $_POST['reg_no'] ?? '');
$safe_company_name = preg_replace("/[^a-zA-Z0-9]/", "_", $_POST['company_name'] ?? '');
$filename = "{$safe_full_name}_{$safe_reg_no}_{$safe_company_name}_photo.{$ext}";
            $target = "uploads/" . $filename;

            if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $field_errors['photo'] = "Invalid image format for photo.";
            } elseif ($_FILES["photo"]["size"] > 1 * 1024 * 1024) {
                $field_errors['photo'] = "Photo must be less than 1MB.";
            } elseif (!move_uploaded_file($_FILES["photo"]["tmp_name"], $target)) {
                $field_errors['photo'] = "Photo upload failed.";
            } else {
                $photo_path = $target;
            }
        } elseif (isset($_FILES["photo"]) && $_FILES["photo"]["error"] !== UPLOAD_ERR_NO_FILE) {
            // Handle other file upload errors for photo if it was attempted
            $field_errors['photo'] = "Photo upload error: " . $_FILES["photo"]["error"];
        }

        // ✅ Optional Onboarding Date
        $onboarding_date = trim($_POST['onboarding_date'] ?? '');
        if ($onboarding_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $onboarding_date)) {
            $field_errors['onboarding_date'] = "Invalid date format. Use YYYY-MM-DD.";
            $onboarding_date = null;
        }
        $_POST['onboarding_date'] = $onboarding_date !== '' ? $onboarding_date : null;

        // ✅ Check if already submitted
        $checkStmt = $conn->prepare("SELECT * FROM on_off_campus_students WHERE reg_no = ?");
        $checkStmt->bind_param("s", $reg_no);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult && $checkResult->num_rows > 0) {
            $error = "You have already submitted the form."; // Global error
        } else {
            $_POST['offer_letter_file'] = implode(',', $offer_letter_paths);
            $_POST['intent_letter_file'] = $intent_letter_path;
            $_POST['photo_path'] = $photo_path;
            $_POST['campus_type'] = $campus_type;
            $_POST['upid'] = null; // UPI ID is not applicable for off-campus
            $_POST['register_type'] = null; // Register Type is not applicable for off-campus

            $skipFields = ['form_submit', 'declaration'];
            $fields = array_keys($_POST);
            $finalFields = array_diff($fields, $skipFields);

            if (empty($field_errors)) { // Only proceed with DB insert if no field errors
                $columns = implode(',', array_map(function($f) { return "`$f`"; }, $finalFields)); // Quote column names
                $values = implode(',', array_map(function ($f) use ($conn) {
                    $val = $_POST[$f];
                    if (is_null($val) || $val === '') return "NULL";
                    return "'" . $conn->real_escape_string($val) . "'";
                }, $finalFields));

                $insert = "INSERT INTO on_off_campus_students ($columns) VALUES ($values)";
                if ($conn->query($insert)) {
                    header("Location: on_off_campus.php?submitted=1");
                    exit;
                } else {
                    $error = "Database insert failed: " . $conn->error; // Global error
                }
            }
        }

        if (!empty($error)) {
            echo "<script>console.error('❌ FORM ERROR: " . $error . "');</script>";
        }
    }
} // ✅ END of POST handling block

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>On/Off Campus Form</title>
    <style>
        .custom-style-wrapper *,
        .custom-style-wrapper *::before,
        .custom-style-wrapper *::after {
            box-sizing: border-box;
        }
        .custom-style-wrapper {
            font-family: 'Roboto', sans-serif;
            display: flex; /* Use flexbox for centering */
            flex-direction: column; /* Stack children vertically */
            align-items: center; /* Center children horizontally */
            width: 100%; /* Ensure it takes the full width */
            padding: 20px; /* Adjust padding as needed */
            overflow-x: hidden; /* Prevent horizontal overflow */
            justify-content: center;
            min-height: 100vh;
            box-sizing: border-box;
        }

        .custom-style-wrapper img,
        .custom-style-wrapper iframe {
            max-width: 100%;
            height: auto;
        }
        .custom-style-wrapper .form-box {
    background: white;
    border-radius: 8px;
    padding: 30px;
    width: 100%; /* Use full width of container */
    max-width: 800px; /* Increased from 500px to 800px */
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
    border-top: 6px solid #4285f4;
    margin: 0 auto;
}
        .custom-style-wrapper input[type="submit"],
        .custom-style-wrapper button[type="submit"] {
            width: 100%;
            margin-top: 15px;
            background: rgb(101, 22, 218);
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 13px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .custom-style-wrapper button[type="submit"]:hover {
            background: #3367d6;
        }
        .custom-style-wrapper h2 {
            text-align: left;
            font-size: 20px;
            font-weight: 500;
            margin-bottom: 20px;
            color: #202124;
        }
        .custom-style-wrapper .form-group {
            margin-bottom: 15px;
            width: 100%; /* Ensure form groups take full width */
        }
        .custom-style-wrapper label {
            font-size: 14px;
            font-weight: 500;
            color: #202124;
            display: block;
            margin-bottom: 5px;
        }
        .custom-style-wrapper input[type="text"],
        .custom-style-wrapper input[type="number"],
        .custom-style-wrapper input[type="file"],
        .custom-style-wrapper input[type="email"],
        .custom-style-wrapper input[type="tel"],
        .custom-style-wrapper input[type="date"],
        .custom-style-wrapper select,
        .custom-style-wrapper textarea {
            width: 100%;
            border: none;
            border-bottom: 1px solid #dadce0;
            padding: 6px 0;
            background: transparent;
            outline: none;
            font-size: 13px;
            color: #202124;
        }
        .custom-style-wrapper input:focus,
        .custom-style-wrapper select:focus,
        .custom-style-wrapper textarea:focus {
            border-color: #4285f4;
        }
        /* Consolidated error-tooltip styles */
        .error-tooltip {
            position: absolute;
            top: calc(100% + 4px); /* Position below the input */
            left: 0;
            right: auto; /* Allow it to expand to the right */
            background: #fee2e2;
            color: #b91c1c;
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 6px;
            white-space: normal; /* Allow text to wrap */
            word-break: break-word; /* Break long words */
            z-index: 10;
            margin-top: 4px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            display: none; /* Hidden by default */
            max-width: 100%; /* Ensure it doesn't overflow its parent */
        }
        .error-tooltip.visible {
            display: block; /* Shown when 'visible' class is added */
        }
        .form-group {
            position: relative; /* Needed to position tooltip inside */
            overflow: visible; /* Allow tooltip to show outside its bounds */
        }
        /* Remove .error and .error.visible if they are not used for tooltips */
        /* .error {
            color: red;
            font-size: 13px;
            opacity: 0;
            transition: opacity 0.3s ease;
            display: block;
            height: 16px;
            margin-top: 4px;
        }
        .error.visible {
            opacity: 1;
        } */
        .error-msg { /* This is for PHP-generated errors at the top of the form */
            color: red;
            font-weight: bold;
            margin-top: 10px;
        }
        /*
        #sidebar, .sidebar, .main-sidebar {
            display: none !important;
            visibility: hidden !important;
            width: 0 !important;
        }*/
        @media (max-width: 600px) {
            .custom-style-wrapper {
                padding: 10px;
                height: auto;
            }
            .custom-style-wrapper .form-box {
                padding: 20px;
                width: 95%;
                box-shadow: none;
                border-radius: 6px;
                margin: 0 auto;
            }
            .error-tooltip {
                max-width: 100%; /* Ensure it fits on small screens */
                left: 0;
                right: auto;
            }
        }
        body {
    margin: 0 !important;
    padding: 0 !important;
    width: 100vw !important;
    overflow-x: hidden !important;
}
/* Force content area to take full width when sidebar is hidden */
.content-wrapper, .main-content, #content {
    width: 100% !important;
    margin-left: 0 !important;
}
/* For WordPress admin bar */
.admin-bar .custom-style-wrapper {
    min-height: calc(100vh - 32px); /* Adjust for admin bar height */
}
.error-tooltip:empty {
    display: none !important;
}

    </style>
</head>
<body>
    <div class="custom-style-wrapper">
        <form method="POST" enctype="multipart/form-data" id="studentForm">
            <!-- Your form fields go here -->
            <?php
            // Initialize $field_errors and $error if they are not set, to prevent undefined variable warnings
            if (!isset($field_errors)) {
                $field_errors = [];
            }
            if (!isset($error)) {
                $error = '';
            }

            // Function to get sticky value
            function getStickyValue($fieldName) {
                return isset($_POST[$fieldName]) ? htmlspecialchars($_POST[$fieldName]) : '';
            }

            // Function to get sticky selected option
            function getStickySelected($fieldName, $optionValue) {
                return (isset($_POST[$fieldName]) && $_POST[$fieldName] == $optionValue) ? 'selected' : '';
            }

            // Function to get sticky checked radio button
            function getStickyChecked($fieldName, $optionValue) {
                return (isset($_POST[$fieldName]) && $_POST[$fieldName] == $optionValue) ? 'checked' : '';
            }

            ?>
            <?php if (isset($fieldList) && is_array($fieldList)): ?>
                <div class="form-box">
                    <h2>Placement Details Form</h2>
                    <?php if (!empty($error)): ?><div class="error-msg"><?php echo $error; ?></div><?php endif; ?>
                    
            <?php endif; ?>
            <!-- 1. Campus Type -->
            <div class="form-group" style="position: relative;">
                <label>Was your placement on-campus or off-campus?<span style="color:red;">*</span></label>
                <select name="campus_type" required>
                    <option value="">Select</option>
                    <option value="on" <?php echo getStickySelected('campus_type', 'on'); ?>>On Campus</option>
                    <option value="off" <?php echo getStickySelected('campus_type', 'off'); ?>>Off Campus</option>
                </select>
            </div>
            <!-- Register Type (Only for On Campus) -->
            <div class="form-group" id="register_type_group" style="position: relative; display: none;">
                <label>Register Type(Are you registered for placement?) <span style="color:red;">*</span></label>
                <select name="register_type" id="register_type">
                    <option value="">Select</option>
                    <option value="registered" <?php echo getStickySelected('register_type', 'registered'); ?>>Registered</option>
                    <option value="not_registered" <?php echo getStickySelected('register_type', 'not_registered'); ?>>Not Registered</option>
                </select>
                <span id="register_type_error" class="error-tooltip"></span>
            </div>

            <?php if (in_array('reg_no', $fieldList)): ?>
                <div class="form-group" style="position: relative;">
                    <label>Register Number <span style="color:red;">*</span></label>
                    <input type="text" name="reg_no" id="reg_no" required value="<?php echo getStickyValue('reg_no'); ?>" />
                    <span id="reg_no_error" class="error-tooltip">
                        <?php echo isset($field_errors['reg_no']) ? htmlspecialchars($field_errors['reg_no']) : ''; ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if (in_array('upid', $fieldList)): ?>
                <div class="form-group" style="position: relative;">
                    <label>Placement ID (Required for On Campus)</label>
                    <input type="text" name="upid" id="upid" value="<?php echo getStickyValue('upid'); ?>" />
                    <span id="upid_error" class="error-tooltip">
                        <?php echo isset($field_errors['upid']) ? htmlspecialchars($field_errors['upid']) : ''; ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if (in_array('full_name', $fieldList)): ?>
                <div class="form-group" style="position: relative;">
                    <label>Full Name<span style="color:red;">*</span></label>
                    <input type="text" name="full_name" id="full_name" value="<?php echo getStickyValue('full_name'); ?>"/>
                    <span id="full_name_error" class="error-tooltip"></span>
                </div>
            <?php endif; ?>

            <?php if (in_array('phone_no', $fieldList)): ?>
                <div class="form-group" style="position: relative;">
                    <label>Phone No<span style="color:red;">*</span></label>
                    <input type="text" name="phone_no" id="phone_no" value="<?php echo getStickyValue('phone_no'); ?>" />
                    <span id="phone_no_error" class="error-tooltip"></span>
                </div>
            <?php endif; ?>

            <?php if (in_array('email', $fieldList)): ?>
                <div class="form-group" style="position: relative;">
                    <label>Email<span style="color:red;">*</span></label>
                    <input type="email" name="email" id="email" value="<?php echo getStickyValue('email'); ?>" />
                    <span id="email_error" class="error-tooltip"></span>
                </div>
            <?php endif; ?>

            <?php if (in_array('course_name', $fieldList)): ?>
                <div class="form-group" style="position: relative;">
                    <label>Current Course Name (UG/PG)<span style="color:red;">*</span></label>
                    <input type="text" name="course_name" id="course_name" placeholder="e.g., B.Sc Computer Science or M.A. English" value="<?php echo getStickyValue('course_name'); ?>" />
                    <span id="course_name_error" class="error-tooltip"></span>
                </div>
            <?php endif; ?>

            <?php if (in_array('passing_year', $fieldList)): ?>
                <div class="form-group" style="position: relative;">
                    <label>Year of Passing (UG/PG)<span style="color:red;">*</span></label>
                    <input type="text" name="passing_year" id="passing_year" placeholder="e.g., 2024" value="<?php echo getStickyValue('passing_year'); ?>" />
                    <span id="passing_year_error" class="error-tooltip"></span>
                </div>
            <?php endif; ?>

            <?php if (in_array('company_name', $fieldList)): ?>
                <div class="form-group" style="position: relative;">
                    <label>Company Name<span style="color:red;">*</span></label>
                    <input type="text" name="company_name" id="company_name" value="<?php echo getStickyValue('company_name'); ?>"/>
                    <span id="company_name_error" class="error-tooltip"></span>
                </div>
            <?php endif; ?>

            <?php if (in_array('role', $fieldList)): ?>
                <div class="form-group" style="position: relative;">
                    <label>Job Role<span style="color:red;">*</span></label>
                    <input type="text" name="role" id="role" value="<?php echo getStickyValue('role'); ?>"/>
                    <span id="role_error" class="error-tooltip"></span>
                </div>
            <?php endif; ?>

            <?php if (in_array('offer_letter_received', $fieldList)): ?>
                <div class="form-group" style="position: relative;">
                    <label>Offer Letter Received<span style="color:red;">*</span></label>
                    <input type="radio" name="offer_letter_received" value="yes" <?php echo getStickyChecked('offer_letter_received', 'yes'); ?> required /> Yes
                    <input type="radio" name="offer_letter_received" value="no" <?php echo getStickyChecked('offer_letter_received', 'no'); ?> /> No
                    <span id="offer_letter_received_error" class="error-tooltip"></span>
                </div>
            <?php endif; ?>
            <?php if (in_array('uploaded_offer_letter', $fieldList)): ?>
                <div class="form-group" style="position: relative;">
                    <label>Upload Offer Letter</label>
                    <input type="file" name="uploaded_offer_letter[]" id="uploaded_offer_letter" accept=".pdf" multiple/>
                    <span id="uploaded_offer_letter_error" class="error-tooltip">
                        <?php echo $field_errors['uploaded_offer_letter'] ?? ''; ?>
                    </span>
                </div>
            <?php endif; ?>
            <?php if (in_array('intent_letter_received', $fieldList)): ?>
                <div class="form-group" style="position: relative;">
                    <label>Intent Letter Received<span style="color:red;">*</span></label>
                    <input type="radio" name="intent_letter_received" value="yes" <?php echo getStickyChecked('intent_letter_received', 'yes'); ?> required /> Yes
                    <input type="radio" name="intent_letter_received" value="no" <?php echo getStickyChecked('intent_letter_received', 'no'); ?> /> No
                    <span id="intent_letter_received_error" class="error-tooltip"></span>
                </div>
            <?php endif; ?>
            <?php if (in_array('uploaded_intent_letter', $fieldList)): ?>
                <div class="form-group" style="position: relative;">
                    <label>Upload Intent Letter</label>
                    <input type="file" name="uploaded_intent_letter" id="uploaded_intent_letter" accept=".pdf" />
                    <span id="uploaded_intent_letter_error" class="error-tooltip">
                        <?php echo $field_errors['uploaded_intent_letter'] ?? ''; ?>
                    </span>
                </div>
            <?php endif; ?>
            <?php if (in_array('onboarding_date', $fieldList)): ?>
                <div class="form-group" style="position: relative;">
                    <label>Joining Date</label>
                    <input type="date" name="onboarding_date" id="onboarding_date" value="<?php echo getStickyValue('onboarding_date'); ?>" />
                    <span id="onboarding_date_error" class="error-tooltip">
                        <?php echo $field_errors['onboarding_date'] ?? ''; ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if (in_array('photo', $fieldList)): ?>
                <div class="form-group" style="position: relative;">
                    <label>Upload Photo<span style="color:red;">*</span></label>
                    <input type="file" name="photo" id="photo" accept="image/*" />
                    <span id="photo_error" class="error-tooltip"><?php echo $field_errors['photo'] ?? ''; ?></span>
                </div>
            <?php endif; ?>
            <?php foreach ($fieldList as $field): ?>
                <?php if (!array_key_exists($field, $available_fields)): ?>
                    <?php $customLabel = $customFieldMap[$field] ?? $field; ?>
                    <div class="form-group">
                        <label><?= htmlspecialchars($customLabel) ?><span style="color:red;">*</span></label>
                        <input type="text" name="<?= htmlspecialchars($field) ?>" id="<?= htmlspecialchars($field) ?>" value="<?php echo getStickyValue($field); ?>" />
                        <span id="<?= htmlspecialchars($field) ?>_error" class="error-tooltip"></span>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (in_array('comments', $fieldList)): ?>
                <div class="form-group" style="position: relative;">
                    <label>Comments</label>
                    <textarea name="comments" placeholder="Let us know if you need help or have special requirements..."><?php echo getStickyValue('comments'); ?></textarea>
                </div>
            <?php endif; ?>

            <!-- ✅ Declaration -->
            <div class="form-group">
                <label>
                    <input type="checkbox" name="declaration" id="declaration" required <?php echo getStickyChecked('declaration', 'on'); ?> />
                    I hereby declare that the above information is correct. <span style="color:red;">*</span>
                </label>
                <span id="declaration_error" class="error-tooltip"></span>
            </div>
            <!-- Submit Button -->
            <button type="submit" class="submit-btn" name="form_submit" value="1">Submit</button>
            <?php if (!empty($field_errors)): ?>
                </div> <!-- Close form-box if it was opened -->
            <?php endif; ?>
        </form>
    </div>
<script>
document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("studentForm");
  if (!form) return;

  const campusTypeSelect = document.querySelector("select[name='campus_type']");
  const upiField = document.getElementById("upid");
  const registerTypeGroup = document.getElementById("register_type_group");
  const registerTypeSelect = document.getElementById("register_type");

  if (campusTypeSelect && upiField) {
    campusTypeSelect.addEventListener("change", () => {
      if (campusTypeSelect.value === "on") {
        registerTypeGroup.style.display = "block";
        registerTypeSelect.setAttribute("required", "required");
      } else {
        registerTypeGroup.style.display = "none";
        registerTypeSelect.value = "";
        registerTypeSelect.removeAttribute("required");
        upiField.removeAttribute("required");
        upiField.value = "";
        clearAllErrors(); // Clear errors when switching to Off Campus
      }
      if (upiField) {
          validateUpiField();
      }
    });

    registerTypeSelect.addEventListener("change", () => {
        validateUpiField();
    });

    // Initial check on page load
    if (campusTypeSelect.value === "on") {
      registerTypeGroup.style.display = "block";
      registerTypeSelect.setAttribute("required", "required");
    } else {
      registerTypeGroup.style.display = "none";
      registerTypeSelect.removeAttribute("required");
    }
    validateUpiField();
  }

  // Show error function
  function showError(id, message) {
    const el = document.getElementById(id + "_error"); // Targets the span with id="FIELD_NAME_error"
    if (el) {
      el.textContent = message;
      el.classList.add("visible"); // Adds the 'visible' class to the error-tooltip
    }
  }

  // Clear all error messages
  function clearAllErrors() {
    document.querySelectorAll(".error-tooltip.visible").forEach(err => {
      err.textContent = "";
      err.classList.remove("visible");
    });
  }

  function fieldExists(id) {
    return document.getElementById(id) !== null;
  }

  function isVisible(id) {
    const el = document.getElementById(id);
    return el && el.offsetParent !== null;
  }

  function validateUpiField() {
    if (fieldExists("upid") && isVisible("upid")) {
      const upi = document.getElementById("upid");
      const upiVal = upi.value.trim();
      const campusType = campusTypeSelect ? campusTypeSelect.value.trim() : "";
      const registerType = registerTypeSelect ? registerTypeSelect.value : "";

      showError("upid", ""); // Clear previous UPI error

      if (campusType === "on") {
        if (registerType === "registered") {
          if (upiVal === "") {
            showError("upid", "Placement ID is required for Registered On Campus students.");
            return false;
          } else if (!/^[a-zA-Z0-9@._-]{3,64}$/.test(upiVal)) {
            showError("upid", "Invalid Placement ID format.");
            return false;
          }
        } else if (registerType === "not_registered") {
          if (upiVal !== "" && !/^[a-zA-Z0-9@._-]{3,64}$/.test(upiVal)) {
            showError("upid", "Invalid Placement ID format.");
            return false;
          }
        }
      } else {
        upi.value = "";
        showError("upid", "");
      }
    }
    return true;
  }
   <?php if (!empty($field_errors)): ?>
       <?php foreach (array_unique($field_errors) as $field => $message): ?>
           showError("<?php echo $field; ?>", "<?php echo htmlspecialchars($message); ?>");
       <?php endforeach; ?>
   <?php endif; ?>
   

  form.addEventListener("submit", function (e) {
    clearAllErrors(); // Clear all errors at the start of submission validation
    let hasErrors = false;

    const campusType = campusTypeSelect ? campusTypeSelect.value.trim() : "";

    if (campusType === "") {
        showError("campus_type", "Please select Campus Type.");
        hasErrors = true;
    }

    if (campusType === "on") {
      const registerType = registerTypeSelect.value;

      if (!registerType) {
        showError("register_type", "Please select a Register Type.");
        hasErrors = true;
      }

      if (registerType === "registered") {
        if (fieldExists("reg_no") && isVisible("reg_no")) {
          const regNo = document.getElementById("reg_no");
          const regVal = regNo.value.trim();
          if (regVal === "") {
            showError("reg_no", "Register Number is required.");
            hasErrors = true;
          } else if (!/^[a-zA-Z0-9]{2,15}$/.test(regVal)) {
            showError("reg_no", "Register number should be 2–15 alphanumeric characters.");
            hasErrors = true;
          }
        }
      } else if (registerType === "not_registered") {
        if (fieldExists("reg_no") && isVisible("reg_no")) {
          const regNo = document.getElementById("reg_no");
          const regVal = regNo.value.trim();
          if (regVal === "") {
            showError("reg_no", "Register Number is required for On Campus Not Registered.");
            hasErrors = true;
          } else if (!/^[a-zA-Z0-9]{2,15}$/.test(regVal)) {
            showError("reg_no", "Register number should be 2–15 alphanumeric characters.");
            hasErrors = true;
          }
        }
      }
    }

    if (!validateUpiField()) {
        hasErrors = true;
    }

    const requiredFields = [
      { id: "full_name", label: "Full Name" },
      { id: "course_name", label: "Course Name" },
      { id: "company_name", label: "Company Name" },
      { id: "role", label: "Job Role" },
      { id: "passing_year", label: "Year of Passing" }
    ];

    requiredFields.forEach(field => {
      const el = document.getElementById(field.id);
      if (el && isVisible(field.id) && el.value.trim() === "") {
        showError(field.id, `${field.label} is required.`);
        hasErrors = true;
      }
    });

    // --- CORRECTED EMAIL VALIDATION ---
    if (fieldExists("email") && isVisible("email")) {
        const email = document.getElementById("email");
        const emailVal = email.value.trim();
        if (emailVal === "") {
            showError("email", "Email is required.");
            hasErrors = true;
        } else if (!/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(emailVal)) {
            showError("email", "Please enter a valid email address (e.g., user@example.com).");
            hasErrors = true;
        }
    }
    // --- END CORRECTED EMAIL VALIDATION ---

    if (fieldExists("phone_no") && isVisible("phone_no")) {
      const phone = document.getElementById("phone_no");
      if (!/^\d{10}$/.test(phone.value.trim())) {
        showError("phone_no", "Please enter a valid 10-digit phone number.");
        hasErrors = true;
      }
    }

    if (fieldExists("reg_no") && isVisible("reg_no") && campusType === "off") {
        const regNo = document.getElementById("reg_no");
        const regVal = regNo.value.trim();
        if (regVal === "") {
            showError("reg_no", "Register Number is required.");
            hasErrors = true;
        } else if (!/^[a-zA-Z0-9]{2,15}$/.test(regVal)) {
            showError("reg_no", "Register number should be 2–15 alphanumeric characters.");
            hasErrors = true;
        }
    }

    if (fieldExists("uploaded_offer_letter") && isVisible("uploaded_offer_letter")) {
    const offerReceivedYes = form.querySelector("input[name='offer_letter_received'][value='yes']");
    const offerUpload = document.getElementById("uploaded_offer_letter");

    if (offerReceivedYes?.checked) {
        const files = offerUpload.files;

        if (files.length === 0) {
            showError("uploaded_offer_letter", "Upload Offer Letter PDF(only pdf, max 10MB).");
            hasErrors = true;
        } else {
            // Optional: validate all selected files are PDFs
            for (let i = 0; i < files.length; i++) {
                if (files[i].type !== "application/pdf") {
                    showError("uploaded_offer_letter", "Only PDF files are allowed.");
                    hasErrors = true;
                    break;
                }
            }
        }
    }
}

    if (fieldExists("uploaded_intent_letter") && isVisible("uploaded_intent_letter")) {
      const intentReceivedYes = form.querySelector("input[name='intent_letter_received'][value='yes']");
      const intentUpload = document.getElementById("uploaded_intent_letter");
      if (intentReceivedYes?.checked && intentUpload.files.length === 0) {
        showError("uploaded_intent_letter", "Upload Intent Letter PDF(only pdf, max 10MB).");
        hasErrors = true;
      }
    }

    if (fieldExists("photo") && isVisible("photo")) {
      const photo = document.getElementById("photo");
      if (photo.files.length === 0) {
        showError("photo", "Please upload your photo (JPG/PNG, max 1MB).");
        hasErrors = true;
      } else {
        const ext = photo.files[0].name.split(".").pop().toLowerCase();
        if (!["jpg", "jpeg", "png"].includes(ext)) {
          showError("photo", "Only JPG, JPEG or PNG allowed.");
          hasErrors = true;
        } else if (photo.files[0].size > 1 * 1024 * 1024) {
          showError("photo", "Photo must be under 1MB.");
          hasErrors = true;
        }
      }
    }

    <?php foreach ($fieldList as $field): ?>
      <?php if (!array_key_exists($field, $available_fields)): ?>
        if (fieldExists("<?= htmlspecialchars($field) ?>") && isVisible("<?= htmlspecialchars($field) ?>")) {
          const customField = document.getElementById("<?= htmlspecialchars($field) ?>");
          if (customField.value.trim() === "") {
            showError("<?= htmlspecialchars($field) ?>", "<?= htmlspecialchars($customFieldMap[$field] ?? $field) ?> is required.");
            hasErrors = true;
          }
        }
      <?php endif; ?>
    <?php endforeach; ?>

    const declarationCheckbox = document.querySelector("input[name='declaration']");
    if (declarationCheckbox && !declarationCheckbox.checked) {
        showError("declaration", "You must agree to the declaration."); // Added specific error for declaration
        hasErrors = true;
    }

    if (hasErrors) {
      e.preventDefault();
      const firstVisibleError = document.querySelector(".error-tooltip.visible");
      if (firstVisibleError) {
        firstVisibleError.scrollIntoView({ behavior: "smooth", block: "center" });
      }
    }
  });

  function attachRealTimeValidation(id, validator = null) {
    const input = document.getElementById(id);
    const errorEl = document.getElementById(id + "_error");

    if (input && errorEl) {
      input.addEventListener("input", () => {
        const value = input.value.trim();
        if (!validator || validator(value)) {
          errorEl.textContent = "";
          errorEl.classList.remove("visible");
        }
      });
      // Also attach to 'blur' for immediate feedback when leaving the field
      input.addEventListener("blur", () => {
        const value = input.value.trim();
        if (validator && !validator(value) && value !== "") { // Only show error on blur if not empty
            // Use specific messages for blur, matching submit logic
            if (id === "email") {
                showError("email", "Please enter a valid email address (e.g., user@example.com).");
            } else if (id === "phone_no") {
                showError("phone_no", "Please enter a valid 10-digit phone number.");
            } else if (id === "reg_no") {
                showError("reg_no", "Register number should be 2–15 alphanumeric characters.");
            } else if (id === "upid") { // Added UPI blur validation
                validateUpiField(); // Re-run UPI specific logic
            } 
        } else {
            errorEl.textContent = "";
            errorEl.classList.remove("visible");
        }
      });
    }
  }

  function attachFileValidation(id) {
    const input = document.getElementById(id);
    const errorEl = document.getElementById(id + "_error");
    if (input && errorEl) {
      input.addEventListener("change", () => {
        if (input.files.length > 0) {
          errorEl.textContent = "";
          errorEl.classList.remove("visible");
        }
      });
    }
  }

  function attachRadioClear(name, errorId) {
    const radios = document.querySelectorAll(`input[name='${name}']`);
    const errorEl = document.getElementById(errorId);
    radios.forEach(r => {
      r.addEventListener("change", () => {
        if (errorEl) {
          errorEl.textContent = "";
          errorEl.classList.remove("visible");
        }
      });
    });
  }

  // Attach validations to individual fields
  attachRealTimeValidation("reg_no", val => /^[a-zA-Z0-9]{2,15}$/.test(val));
  attachRealTimeValidation("full_name", val => val.length > 0);
  attachRealTimeValidation("course_name", val => val.length > 0);
  attachRealTimeValidation("company_name", val => val.length > 0);
  attachRealTimeValidation("role", val => val.length > 0);
  attachRealTimeValidation("passing_year", val => val.length > 0);
  attachRealTimeValidation("email", val => /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(val));
  attachRealTimeValidation("phone_no", val => /^\d{10}$/.test(val));
  attachRealTimeValidation("upid"); // UPI validation is handled by validateUpiField, but attach blur for clearing
  attachFileValidation("photo");
  attachFileValidation("uploaded_offer_letter");
  attachFileValidation("uploaded_intent_letter");
  attachRealTimeValidation("register_type", val => val !== "");
  attachRadioClear("offer_letter_received", "uploaded_offer_letter_error");
  attachRadioClear("intent_letter_received", "uploaded_intent_letter_error");
  attachRealTimeValidation("declaration", val => document.getElementById("declaration").checked); // For declaration checkbox

  // Attach real-time validation for custom fields
  <?php foreach ($fieldList as $field): ?>
    <?php if (!array_key_exists($field, $available_fields)): ?>
      attachRealTimeValidation("<?= htmlspecialchars($field) ?>", val => val.length > 0);
    <?php endif; ?>
  <?php endforeach; ?>

});
</script>
<script>
// This script block is separate and handles the fade-out for specific errors.
// It should be fine as is, but ensure it's placed after the main script.
document.addEventListener("DOMContentLoaded", function () {
    const errorMap = {
        reg_no: document.getElementById("reg_no_error"),
        upid: document.getElementById("upid_error")
    };

    Object.keys(errorMap).forEach(function (fieldId) {
        const inputEl = document.getElementById(fieldId);
        const errorEl = errorMap[fieldId];

        if (inputEl && errorEl) {
            inputEl.addEventListener("input", function () {
                if (fieldId === "upid") {
                    // Fade out for placement ID
                    errorEl.style.transition = "opacity 0.5s";
                    errorEl.style.opacity = "0";

                    setTimeout(() => {
                        errorEl.style.display = "none";
                    }, 500);
                } else {
                    // Instant hide for others
                    errorEl.style.display = "none";
                }
            });
        }
    });
});

</script>

</body>
</html>
