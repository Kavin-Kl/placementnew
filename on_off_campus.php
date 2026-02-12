<?php
ob_start();
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}
include("config.php");
include("header.php");
// Dynamically detect the base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
$base_url = $protocol . $_SERVER['HTTP_HOST'] 
          . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

$available_fields = [
    "company_name" => "Company Name",
    "full_name" => "Full Name",
    "reg_no" => "Register No",
    "upid" => "Placement ID",
    "email" => "Email",
    "role" => "Designation",
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

// Generate student_form URL
$student_form_url = $base_url . "student_form";
?>

<?php

$customFieldMap = [];
$lastShortQuery = $conn->query("SELECT shortcode FROM form_links ORDER BY id DESC LIMIT 1");

if ($lastShortQuery->num_rows > 0) {
    $shortcode = $lastShortQuery->fetch_assoc()['shortcode'];
    $generated_link = $student_form_url . "/" . $shortcode;
} else {
    $generated_link = "Select fields to generate form link...";
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_fields'])) {
    $selected = $_POST['selected_fields'];
    if (!empty($selected)) {
        $custom_fields_raw = $_POST['custom_fields'] ?? '';
        $custom_fields_array = json_decode($custom_fields_raw, true);
        $custom_keys = [];

        if (is_array($custom_fields_array)) {
            foreach ($custom_fields_array as $item) {
                if (!empty($item['label'])) {
                    // Use label directly to generate column name
                    $cleanCol = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower(trim($item['label'])));
                    $customFieldMap[$cleanCol] = $item['label'];
                    $custom_keys[] = $cleanCol;

                    // Create DB column if not exists
                    $checkColumnQuery = "SHOW COLUMNS FROM on_off_campus_students LIKE '$cleanCol'";
                    $columnExists = $conn->query($checkColumnQuery)->num_rows > 0;

                    if (!$columnExists) {
                        $alterQuery = "ALTER TABLE on_off_campus_students ADD `$cleanCol` TEXT";
                        $conn->query($alterQuery);
                    }
                }
            }
        }

        // Merge with selected standard fields
        $combined = array_merge($selected, $custom_keys);
        $fields_query = implode(",", $combined);

        // Save form link with just label array
        $custom_fields_json = json_encode($custom_fields_array);

        $shortcode = "Overall_Placed_Students";
        $stmt = $conn->prepare("REPLACE INTO form_links (shortcode, fields, custom_field_meta) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $shortcode, $fields_query, $custom_fields_json);
        $stmt->execute();

        $generated_link = $student_form_url . "/Overall_Placed_Students";
    } else {
        $error = "Please select at least one field.";
    }
}
?>

<head><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>


  <h2 class="headings">Offer Letter Collection</h2>
  <p style="font-size: 16px; color: #4B5563; margin-top: 4px; font-family: 'Inter', sans-serif;">
  Student placement details for both on-campus and off-campus.
</p>

  <button onclick="openFieldPopup()" class="imports-button">Select Fields to Generate Form</button>
<?php if (!empty($generated_link)): ?>
  <div style="margin-top: 10px; padding: 4px 2px; background-color: #f9fafb; border: 1px solid #d1d5db; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); font-family: 'Inter', sans-serif; max-width: 100%;">
  <strong style="font-size: 16px; margin-top: 4px; margin-left: 6px;color: #111827;">Generated Link:</strong>
  <a href="<?= $generated_link ?>" target="_blank" style="display: inline-block; font-size: 14px; color: #2563eb; text-decoration: underline; word-break: break-all; margin-bottom: 12px;margin-top: 1px;margin-right: 12px;margin-left: 6px;">
  <?= $generated_link ?>
</a>
    <button onclick="copyToClipboard('<?= $generated_link ?>')" class="bulksave-button" style="flex: 1;">
      Copy Link
    </button>
    <span id="copyMessage" style="color: #198754; flex: 1; display: none;" >✔ Copied!</span>
  </div>
<?php endif; ?>
<style>
        /* Base modal container */
        .field-selection-modal-custom {
            display: none; /* Hidden by default, controlled by JS */
            background-color: rgba(0,0,0,0.4); /* Overlay background */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1050; /* Ensure it's on top */
            overflow-x: hidden;
            overflow-y: auto;
            outline: 0;
        }

        /* REMOVED .show rule from here, as it's now controlled by JS */
        /* .field-selection-modal-custom.show {
            display: block;
        } */

        /* Modal dialog positioning and sizing */
        .field-selection-modal-dialog {
            position: relative;
            width: auto;
            margin: 1.75rem auto;
            pointer-events: none;
            max-width: 800px; /* Equivalent to modal-lg */
        }

        @media (min-width: 576px) {
            .field-selection-modal-dialog {
                max-width: 500px; /* Default for Bootstrap modal-dialog */
            }
        }

        @media (min-width: 992px) {
            .field-selection-modal-dialog {
                max-width: 800px; /* Equivalent to modal-lg */
            }
        }

        .field-selection-modal-dialog.modal-dialog-centered {
            display: flex;
            align-items: center;
            min-height: calc(100% - 3.5rem);
        }

        /* Modal content box */
        .field-selection-modal-content {
            position: relative;
            display: flex;
            flex-direction: column;
            width: 100%;
            pointer-events: auto;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid rgba(0,0,0,.2);
            border-radius: .3rem; /* Standard Bootstrap border-radius */
            outline: 0;
            padding: 1rem !important; /* Your existing padding */
            box-shadow: 0 .5rem 1rem rgba(0,0,0,.15); /* Standard Bootstrap shadow */
        }

        /* Modal header */
        .field-selection-modal-header {
            display: flex;
            flex-shrink: 0;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1rem; /* Standard Bootstrap padding */
            border-bottom: 1px solid #dee2e6; /* Standard Bootstrap border */
            border-top-left-radius: calc(.3rem - 1px);
            border-top-right-radius: calc(.3rem - 1px);
        }

        /* Modal title */
        .field-selection-modal-title {
            margin-bottom: 0;
            line-height: 1.5;
            /* Inline styles from HTML are preserved here */
        }

        /* Close button */
        .field-selection-btn-close {
            padding: .5rem .5rem;
            margin: -.5rem -.5rem -.5rem auto;
            background-color: transparent;
            border: 0;
            opacity: .5;
            cursor: pointer;
        }

        /* Modal body */
        .field-selection-modal-body {
            position: relative;
            flex: 1 1 auto;
            padding: 1rem; /* Standard Bootstrap padding */
            /* max-height and overflow-y are already inline */
        }

        /* Modal footer */
        .field-selection-modal-footer {
            display: flex;
            flex-wrap: wrap;
            flex-shrink: 0;
            align-items: center;
            justify-content: flex-end;
            padding: .75rem;
            border-top: 1px solid #dee2e6; /* Standard Bootstrap border */
            border-bottom-right-radius: calc(.3rem - 1px);
            border-bottom-left-radius: calc(.3rem - 1px);
        }

        /* Preserve existing button styles (applybutton, cancel-btn) */
        /* These are assumed to be defined globally or in your main CSS.
           If they are causing issues, you might need to scope them too, e.g.: */
        /*
        .field-selection-modal-footer .applybutton {
            border: 1px solid rgba(8, 78, 12, 1);
            background: white;
            color: rgba(8, 78, 12, 1);
            font-weight: 400;
            height: 38px;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .field-selection-modal-footer .applybutton:hover {
            background: rgba(8, 78, 12, 1);
            color: white;
        }
        .field-selection-modal-footer .cancel-btn {
            border: 2px solid #d12626ff;
            border-radius: 6px;
            background: white;
            color: #d12626ff;
            cursor: pointer;
            height: 36px;
            width: 100px;
        }
        .field-selection-modal-footer .cancel-btn:hover {
            background: #d12626ff;
            color: white;
        }
        */
        .modal {
    display: none; /* Hidden by default */
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100% auto;
    height: 100% auto;
    overflow: auto;
    background-color: rgb(0,0,0);
    background-color: rgba(0,0,0,0.4);
}
.modal-content {
    background-color: #fefefe;
    margin: 15% auto;
    padding: 20px;
    border: 1px solid #888;
    width: auto;
    max-width: 500px;
}
.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
}
.close:hover,
.close:focus {
    color: black;
    text-decoration: none;
    cursor: pointer;
}
/* new style code for mobile */
@media screen and (max-width: 600px) {
    /* Make top bar controls stack */
    .search-filter-btn-container {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
        width: 100%;
    }

    /* Make search input full width */
    .search-filter-btn-container input[type="text"] {
        width: 100% !important;
        margin: 0 !important;
        height: 40px;
    }

    /* Make all buttons full width */
    .search-filter-btn-container button,
    .exportsBtn,
    .reset-button,
    .filter-button,
    .export-files-btn {
        width: 100% !important;
        margin: 0 !important;
        padding: 10px !important;
        font-size: 14px;
    }

    /* Stack export buttons container vertically */
    .search-filter-btn-container > div {
        display: flex;
        flex-direction: column;
        gap: 8px;
        width: 100%;
    }

    /* Adjust Excel field popup for mobile */
    #excelFieldPopup {
        top: 10% !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        width: 90% !important;
        max-width: 350px !important;
        margin: 0 auto !important;
        padding: 15px !important;
    }

    /* Ensure popup button fits mobile */
    #excelFieldPopup .export-files-btn {
        width: 100% !important;
        margin: 10px 0 0 0 !important;
    }
}
/* Ensure select xsl pop */
#excelCheckboxContainer {
  display: flex;
  gap: 30px;
  padding: 5px 0;
}

#excelCheckboxContainer label {
  font-size: 14px;
  white-space: normal; /* ✅ Allow wrapping */
}

#excelCheckboxContainer input[type="checkbox"] {
  width: 16px;
  height: 16px;
  flex-shrink: 0; /* ✅ Prevents shrinking when label wraps */
}
/* table start*/
/* === Table === */
.custom-table {
  font-size: 12px; /* Base row font size */
}
.table-wrapper {
  max-height: 400px;
  overflow-y: auto;
  overflow-x: auto;
  border: 1px solid #ccc;
}

.custom-table th {
  position: sticky;
  top: 0;
  z-index: 2;
  background-color:  #650000;
  color: white;
  font-size: 13px; /* Heading font size */
  padding-top: 12px;
  padding-bottom: 12px;
  padding-left: 10px !important;
  padding-right: 10px !important;
  vertical-align: middle;
  white-space: nowrap;
}

.custom-table td {
  font-size: 12px; /* Row font size */
  padding-left: 10px !important;
  padding-right: 10px !important;
  vertical-align: middle;
  white-space: nowrap;
}
/* === Inputs inside table === */
.custom-table input.form-control-sm,
.custom-table select.form-select-sm {
  padding: 2px 6px;
  font-size: 11px;
  height: auto;
}

.custom-table td input,
.custom-table td select {
  width: 100%;
}
.top-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: nowrap;            /* prevent wrapping */
  gap: 1rem;
  margin-bottom: 1rem;
  margin-top: 1.5rem;
  position: relative;
}

.left-controls {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  flex-wrap: wrap;
}

.right-controls {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  flex-wrap: wrap;
}
.imports-button {
  border: 1px solid #650000;  /* dark green */
  background: white;
  color: #650000; 
  height:auto;
  border-radius: 6px;
  height: 35px;
  width:auto;
  flex-wrap: nowrap;
  padding: 0.5rem;  
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease-in-out;
  display: inline-flex;      /* make it a flex container */
  align-items: center;       /* vertically center */
  justify-content: center;   /* horizontally center */
  gap: 6px;
}
  
.imports-button:hover {
  background-color: #f2f2f2;  /* dark green bg */
  color: #650000; /* make sure text stays white */
}
.searchinput {
  padding: 10px 12px;
  height: 35px;
  width: 200px;
  border-radius: 6px;
  border: 1px solid #650000;   /* light thin border */
  background-color: white;
  font-size: 14px;
  outline: none;
  transition: border 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.searchinput:focus {
  border-color: #650000;  /* darker border when focused */
  box-shadow: 0 0 5px #650000;
}
.cancel-btn {
  border: 2px solid #d12626ff;  /* darker + thicker border */
  border-radius: 6px;         /* slight curve */
  background: white;
  color: #d12626ff;
 cursor: pointer;
 height:36px;
 width: 100px;
}

.cancel-btn:hover {
  background: #f2f2f2; /* dark pink */
  color: #d12626ff;
}
.exportsbtn  {
  border: 1px solid #198754;  /* dark pink */
  background: white;
  color: #198754;
  height: 35px;
  border-radius: 6px;  
  width:auto;
  flex-wrap: nowrap;
  padding: 0.5rem;  
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease-in-out;
  display: inline-flex;      /* make it a flex container */
  align-items: center;       /* vertically center */
  justify-content: center;   /* horizontally center */
  gap: 6px;
}

.exportsbtn:hover {
  background: #f2f2f2; /* dark pink */
  color: #198754;
}
.filter-button {
  border: 1px solid black;
  background: white;
  color: black;
  height: 35px;
  width: auto;
  border-radius: 6px;
  padding: 0.5rem !important;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease-in-out;
  display: inline-flex;      /* make it a flex container */
  align-items: center;       /* vertically center */
  justify-content: center;   /* horizontally center */
  gap: 6px;                  /* space between icon & text */
}

.filter-button:hover {
  background: #f2f2f2 !important; /* light gray on hover */
  color: black;
}
.reset-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #162C64;
  background: white;
  color: #162C64;
  height:38px;
  width: auto;
  font-weight: 500;
  padding: 8px 16px;
  border-radius: 6px;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s ease;
  width:70px;

}

.reset-button:hover {
  background: #f2f2f2 ;
  color: #1E3A8A;
}
.resets-button {
  border: 1px solid #650000 !important;  
  color: #650000 !important;             
  border-radius: 6px;
  padding: 0.5rem 1rem !important;  /* padding controls button size */
  font-weight: 700;
  height: 35px !important;
  display: inline-flex;       /* keeps icon + text aligned */
  align-items: center;        /* vertical centering */
  justify-content: center;    /* horizontal centering */
  gap: 6px;                   /* spacing between icon & text */
  cursor: pointer;
  transition: all 0.2s ease-in-out;
  text-decoration: none;
  background: white;
  width: auto;
}

.resets-button:hover {
  background: #f2f2f2 !important;
  color: #650000 !important;
}

.applybutton {
  border: 1px solid rgba(8, 78, 12, 1); /* dark green */
  background: white;
  color: rgba(8, 78, 12, 1); /* dark green */
  font-weight: 400;
  height:38px;
  padding: 10px 16px;         /* control height via padding */
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;        /* vertical centering */
  justify-content: center;    /* horizontal centering */
}
.applybutton:hover {
  background: #f2f2f2; /* dark green */ /* fill on hover */
  color: rgba(8, 78, 12, 1);
}
    </style>
<!-- 💡 Bootstrap Field Selection Modal -->
</head>
<body>

<!-- 💡 Bootstrap Field Selection Modal -->
<!-- REMOVED 'show' class from here -->
<div id="fieldPopup" class="field-selection-modal-custom" tabindex="-1">
  <div class="field-selection-modal-dialog modal-lg modal-dialog-centered">
    <div class="field-selection-modal-content p-4">
      <div class="field-selection-modal-header">
        <h5 style="font-size: 16px; font-weight: bold; color:#0F172A;" class="field-selection-modal-title"> Select Fields to Include in the Form</h5>
        <button type="button" onclick="closeFieldPopup()" style=" margin-left:30px;color: black; border: none; padding: 2px 8px; cursor: pointer; font-size: 12px; border-radius: 4px;">✕</button>
      </div>
      <form method="POST" id="fieldForm">
        <div class="field-selection-modal-body" style="max-height: 400px; overflow-y: auto;">
          <!-- 🔘 Select All Option -->
          <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" id="select_all" onclick="toggleAll(this)">
            <label class="form-check-label fw-bold" for="select_all">Select All Fields</label>
          </div>
          <hr>

          <!-- 📋 Available Fields -->
          <div class="row">
            <?php foreach ($available_fields as $key => $label): ?>
              <div class="col-md-4">
                <div class="form-check mb-2">
                  <input type="checkbox" class="form-check-input" id="<?= $key ?>" name="selected_fields[]" value="<?= $key ?>">
                  <label class="form-check-label" for="<?= $key ?>"><?= $label ?></label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- ➕ Custom Fields Section -->
          <hr>
          <div id="customFieldContainer"></div>
          <button type="button" class="imports-button" onclick="addCustomField()">+ Add Custom Field</button>
          <input type="hidden" name="custom_fields" id="custom_fields_list">
        </div>
        <div class="field-selection-modal-footer">
          <button type="submit" class="applybutton">Generate Link</button>
          <button type="button" class="cancel-btn" onclick="closeFieldPopup()" style="margin-left:6px">Cancel</button>
        </div>

        <div id="error-message" class="text-danger mt-2" style="display: none;"></div>
      </form>
    </div>
  </div>
</div>

<!-- 🔲 Overlay -->
<div id="popupOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
background: rgba(0,0,0,0.4); z-index: 999;"></div>


<!-- ✅ JavaScript Logic -->
 <script>
    function toggleAll(source) {
      const checkboxes = document.querySelectorAll('input[name="selected_fields[]"]');
      checkboxes.forEach(cb => cb.checked = source.checked);
    }

    function openFieldPopup() {
      document.getElementById('fieldPopup').style.display = 'block';
      document.getElementById('popupOverlay').style.display = 'block';
    }

    function closeFieldPopup() {
      document.getElementById('fieldPopup').style.display = 'none';
      document.getElementById('popupOverlay').style.display = 'none';
    }

    function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        let msg = document.getElementById("copyMessage");
        msg.style.display = "inline"; // Show message

        // Hide message after 10 seconds
        setTimeout(function() {
            msg.style.display = "none";
        }, 2000);
    });
}
  </script>

<!---it start the form here-->
<?php
/*
// ... (existing code before this block) ...

if ($studentMode) {

    // Include the new student form file here
    include("student_form.php");
   
}
 */
// ... (rest of the on_off_campus_17.php file, including the POST handling block and the admin interface) ...
?>
<hr>
<!--?php if (!$studentMode): ?-->
<h4 style="margin-top:18px;">Submitted List</h4>
<!-- 🔘 JavaScript -->
<script>
  function toggleFilterModal() {
    const modal = document.getElementById('filterModal');
    const overlay = document.getElementById('filterOverlay');
    const isVisible = modal.style.display === 'block';

    modal.style.display = isVisible ? 'none' : 'block';
    overlay.style.display = isVisible ? 'none' : 'block';
    document.body.style.overflow = isVisible ? '' : 'hidden';
  }
  window.addEventListener('DOMContentLoaded', function () {
  const yesRadio = document.querySelector('input[name="offer_letter_received"][value="yes"]');
  if (yesRadio && yesRadio.checked) {
    document.getElementById('offer_upload').style.display = 'block';
  }
});


</script>
<div class="top-bar" style="display: flex; align-items: center; margin-bottom:4px; margin-top:4px;">
    <div class="left-controls" style="display: flex; align-items: center;">
<!-- 🔍 Live Search Input Only -->
<div class="search-filter-btn-container" style="position: relative;">
  <input id="liveSearch" type="text" class="searchinput" placeholder="Search (name, reg no, etc.)" >
  <!-- 🔽 Filter Button -->
  <button type="button" onclick="toggleFilterModal()" class="filter-button">
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#344054" class="bi bi-funnel-fill" viewBox="0 0 16 16">
    <path d="M1.5 1.5a.5.5 0 0 1 .5-.5h12a.5.5 0 0 1 .4.8L10 7.333V13.5a.5.5 0 0 1-.757.429l-2-1.2a.5.5 0 0 1-.243-.429V7.333L1.1 1.8a.5.5 0 0 1 .4-.8z"/>
  </svg>
  Filters
</button>
  <!-- 🔄 Reset (clears input and resets rows) -->
<button type="button" id="resetBtn" class="reset-button" onclick="window.location.href = window.location.pathname;" style="margin-right:3px;">
  <i class="fas fa-undo"></i> <span style="margin-left: 0px;">Reset</span>
</button>
<!-- ✅ Export Buttons -->
<div style="display: flex;">
  <!-- Export Selected Fields Button -->
<button type="button" onclick="showExcelFieldPopup()" class="exportsbtn">
  <i class="fas fa-file-export" style="font-size:14px; margin-right:3px;"></i> Export Selected Fields
  </button>
  </div>
  <button class="exportsbtn" onclick="openModal()"><i class="fas fa-file-archive" style= "margin-right:3px"></i>Open Export Options</button>
</div>
</div>
<!-- ✅ Export Field Selection Popup -->
<div id="excelFieldPopup" class="field-popup" style="display: none; position: fixed; top: 20%; left: 50%; transform: translate(-50%, -20%); background: white; padding: 20px; border: 1px solid #ccc; border-radius: 10px; z-index: 1000;">
  <div style="display: flex; justify-content: space-between; align-items: center;">
    <h4 style="margin: 0;">Select Fields to Export Excel</h4>
  <button type="button" onclick="hideExcelFieldPopup()" style=" margin-left:30px;color: black; border: none; padding: 2px 8px; cursor: pointer; font-size: 12px; border-radius: 4px;">✕</button>
</div>
  <form onsubmit="exportSelectedFields(event)">
    <div id="excelCheckboxContainer"></div>
    <br>
    <button type="submit" name="export_selected" class="export-files-btn" style="width:300px;display:block; margin:0 auto;">
            <i class="fas fa-file-archive"  style="font-size:14px; margin-right:4px;"></i> Export Selected Fields
        </button>
  </form>
</div>
</div>

<!-- Modal for Export Options new -->
<div id="exportFilesModal" class="export-files-modal" style="display:none;">
    <div class="export-files-modal-content">
        <span class="export-files-close" onclick="closeModal()">&times;</span>
        <h5>Export Options</h5>
        
        <button class="resets-button" style="margin-bottom:10px;" onclick="downloadOfferLetterZip()">
            <i class="fas fa-file-archive" style="margin-right:3px; margin-top:2px"></i> Offer Letter ZIP
        </button>

        <button class="resets-button" style="margin-bottom:10px;" onclick="downloadIntentLetterZip()">
            <i class="fas fa-file-archive" style="margin-right:3px; margin-top:2px"></i> Intent Letter ZIP
        </button>

        <button class="resets-button" onclick="downloadPhotoZip()">
            <i class="fas fa-file-archive" style="margin-right:3px; margin-top:2px"></i> Photo ZIP
        </button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const searchInput = document.getElementById('liveSearch');
  if (!searchInput) return;

  searchInput.addEventListener('input', function () {
    const val = this.value.toLowerCase();
    document.querySelectorAll('#tableBody tr').forEach(row => {
      row.style.display = row.innerText.toLowerCase().includes(val) ? '' : 'none';
    });
  });
});
function openModal() {
  
    document.getElementById("exportFilesModal").style.display = "flex";
}

function closeModal() {
    document.getElementById("exportFilesModal").style.display = "none";
}
function exportData() {
    // Implement your export logic here
    alert("Exporting data...");
}

function downloadOfferLetterZip() {
    // Implement your download logic here
    window.location.href = 'download_all_offers_zip.php'; // Adjust the URL as needed
}

function downloadIntentLetterZip() {
    // Implement your download logic here
    window.location.href = 'download_all_indent_zip.php'; // Adjust the URL as needed
}

function downloadPhotoZip() {
    // Implement your download logic here
    window.location.href = 'download_all_photos_zip.php'; // Adjust the URL as needed
}
</script>
<!-- 📁 Export Button -->



<!-- 🔽 Scrollable Table Container -->
<!-- 🌐 Filter Modal -->
<!-- 🌐 Filter Modal -->
<div id="filterModal" class="filter-modal">
  <div class="modal-content">
    <!-- Cancel X button -->
    <span class="close" onclick="toggleFilterModal()">&times;</span>

    <h3 style="font-size: 16px; font-weight: bold; color:#0F172A;">Apply Filters</h3>

    <form method="GET" id="filterForm">
      <div class="filter-grid">
        <label>Company Name:
          <input type="text" name="company_name" placeholder="Company Name" value="<?= htmlspecialchars($_GET['company_name'] ?? '') ?>" />
        </label>

        <label>Full Name:
          <input type="text" name="full_name" placeholder="Full Name" value="<?= htmlspecialchars($_GET['full_name'] ?? '') ?>" />
        </label>

        <label>Register No:
          <input type="text" name="reg_no" placeholder="Register No" value="<?= htmlspecialchars($_GET['reg_no'] ?? '') ?>" />
        </label>

        <label>Placement ID:
          <input type="text" name="upid" placeholder="Placement ID" value="<?= htmlspecialchars($_GET['upid'] ?? '') ?>" />
        </label>

        <label>Course:
  <select name="course[]" id="course-multiselect" multiple="multiple" style="width: 100%;">
    <?php
    $courses = $conn->query("SELECT DISTINCT course_name FROM on_off_campus_students WHERE course_name IS NOT NULL AND course_name != '' ORDER BY course_name ASC");
    while ($row = $courses->fetch_assoc()):
    ?>
      <option value="<?= htmlspecialchars($row['course_name']) ?>">
        <?= htmlspecialchars($row['course_name']) ?>
      </option>
    <?php endwhile; ?>
  </select>
</label>

        <label>Offer Letter:
          <select name="filter_offer">
            <option value="">Offer Letter Received</option>
            <option value="yes" <?= (($_GET['filter_offer'] ?? '') === 'yes') ? 'selected' : '' ?>>Yes</option>
            <option value="no" <?= (($_GET['filter_offer'] ?? '') === 'no') ? 'selected' : '' ?>>No</option>
          </select>
        </label>

        <label>Campus Type:
          <select name="filter_campus">
            <option value="">Campus Type</option>
            <option value="on" <?= (($_GET['filter_campus'] ?? '') === 'on') ? 'selected' : '' ?>>On Campus</option>
            <option value="off" <?= (($_GET['filter_campus'] ?? '') === 'off') ? 'selected' : '' ?>>Off Campus</option>
          </select>
        </label>

        <label>Year of Passing:
          <input type="text" name="passing_year" placeholder="Year of Passing" value="<?= htmlspecialchars($_GET['passing_year'] ?? '') ?>" />
        </label>
      </div>

      <div class="filter-actions">
        <button type="submit">Apply Filters</button>
   <button type="button" onclick="clearAllFilters()" class="clear-button">Clear Filters</button>


    
      </div>
    </form>
  </div>
</div>
<div class="table-wrapper">
  <table class="table table-bordered table-striped custom-table">
<?php
$whereClauses = [];
if (!empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $whereClauses[] = "(full_name LIKE '%$search%' OR reg_no LIKE '%$search%' OR email LIKE '%$search%')";
}

$filterFields = ['company_name', 'full_name', 'reg_no', 'upid', 'passing_year'];
foreach ($filterFields as $field) {
    if (!empty($_GET[$field])) {
        $val = $conn->real_escape_string(trim($_GET[$field]));
        $whereClauses[] = "$field LIKE '%$val%'";
    }
}
if (!empty($_GET['course']) && is_array($_GET['course'])) {
    $courses = array_map(function($c) use ($conn) {
        return "'" . mysqli_real_escape_string($conn, $c) . "'";
    }, $_GET['course']);
    $whereClauses[] = "course_name IN (" . implode(",", $courses) . ")";
}

if (!empty($_GET['filter_offer'])) {
    $offer = $conn->real_escape_string($_GET['filter_offer']);
    $whereClauses[] = "offer_letter_received = '$offer'";
}

if (!empty($_GET['filter_campus'])) {
    $campus = $conn->real_escape_string($_GET['filter_campus']);
    $whereClauses[] = "campus_type = '$campus'";
}


$where = '';
if (!empty($whereClauses)) {
    $where = 'WHERE ' . implode(' AND ', $whereClauses);
}


// Always get column names first
$colResult = $conn->query("SHOW COLUMNS FROM on_off_campus_students");
$columns = [];
while ($col = $colResult->fetch_assoc()) {
    if ($col['Field'] === 'external_id' || $col['Field'] === 'organization_name') continue;
    $columns[] = $col['Field'];
}

$customLabels = [
  'upid' => 'Placement ID',
  'reg_no' => 'Register Number',
  'photo_path' => 'Photo',
  'offer_letter_file' => 'Offer Letter',
  'intent_letter_file' => 'Intent Letter',
  'campus_type' => 'Campus Type',
  'register_type' => 'Register Type', // Added custom label for display
  'onboarding_date' => 'Joining Date',
  'passing_year' => 'Year of Passing',
  'role' => 'Designation'
  // Add more custom labels as needed
];
echo "<thead><tr>";
echo "<th>SL.No.</th>";
foreach ($columns as $col) {
    // ✅ Skip only 'external_id', show everything else (even 'submitted_at')
    if ($col === 'external_id') {
        continue;
    }

    // ✅ Determine label: check customLabels → customFieldMap → fallback
    $label = $customLabels[$col] 
        ?? ($customFieldMap[$col] ?? ucwords(str_replace("_", " ", $col)));

    echo "<th>$label</th>";
}
echo "<th class='no-export'></th>";
echo "</tr></thead>";
// Fetch data
$qry = "SELECT * FROM on_off_campus_students $where ORDER BY external_id DESC";
$r = $conn->query($qry);

// ✅ Show table body
echo "<tbody id='tableBody'>";
if ($r && $r->num_rows > 0) {
  $serial = 1; // 👈 Initialize serial number
    while ($row = $r->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$serial}</td>"; 
        foreach ($columns as $col) {
            echo "<td>";
            switch ($col) {
                case 'offer_letter_file':
                case 'photo_path':
                case 'intent_letter_file':
                    if (!empty($row[$col])) {
                        $files = explode(',', $row[$col]);
foreach ($files as $i => $file) {
    $file = trim($file);
    if ($file) {
        $filename = basename($file);  // This will get, e.g. "John_123_Company_1.pdf"
        $url = htmlspecialchars($file, ENT_QUOTES);
        echo "<a href='{$url}' target='_blank' onclick=\"window.open(this.href, 'popup', 'width=800,height=600'); return false;\">{$filename}</a><br>";
    }
}
                    } else {
                        echo "-";
                    }
                    break;

                case 'campus_type':
                    echo $row[$col] === 'on' ? 'On Campus' : ($row[$col] === 'off' ? 'Off Campus' : '-');
                    break;
                
                case 'register_type': // Display for register_type
                    echo $row[$col] === 'registered' ? 'Registered' : ($row[$col] === 'not_registered' ? 'Not Registered' : '-');
                    break;

                case 'onboarding_date':
                    echo (!empty($row[$col]) && $row[$col] !== '0000-00-00')
                        ? date("d-m-Y", strtotime($row[$col]))
                        : "-";
                    break;
                case 'submitted_at':
    echo (!empty($row[$col]) && $row[$col] !== '0000-00-00 00:00:00')
        ? date('d-m-Y h:i A', strtotime($row[$col]))
        : "-";
    break;

                default:
                    echo isset($row[$col]) && trim($row[$col]) !== ''
                        ? htmlspecialchars($row[$col], ENT_QUOTES)
                        : "-";
                    break;
            }
            echo "</td>";
        }
        // Add Action column with Delete button
echo "<td class='no-export'>";
echo "<button class='btn btn-outline-danger btn-sm ms-2' data-id='{$row['external_id']}' onclick='deleteRow(this)'>
        <i class='fa fa-trash'></i>
      </button>";
      
echo "</td>";
        echo "</tr>";
        $serial++;
    }
} else {
    // ✅ Show row under existing headers
    echo "<tr><td colspan='" . count($columns) . "' class='text-center' style='padding: 16px; color: #555;'>No data found</td></tr>";
}
echo "</tbody>";
?>
</table>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
function exportTable() {
  const table = document.querySelector(".custom-table");
  if (!table) {
    alert("❌ Table not found!");
    return;
  }

  // Convert HTML table to a worksheet
  const workbook = XLSX.utils.book_new();
  const worksheet = XLSX.utils.table_to_sheet(table);

  // Append worksheet to workbook
  XLSX.utils.book_append_sheet(workbook, worksheet, "Placement Data");

  // Export to .xlsx file
  XLSX.writeFile(workbook, "OnOffCampusData.xlsx");
}
function showExcelFieldPopup() {
  const table = document.querySelector(".custom-table");
  if (!table) return alert("❌ Table not found!");

  const headers = Array.from(table.querySelectorAll("thead th")); // ✅ Correct placement
  const container = document.getElementById("excelCheckboxContainer");
  container.innerHTML = "";

  // Create "Select All" checkbox
  const selectAllLabel = document.createElement("label");
  selectAllLabel.style.display = "flex";
  selectAllLabel.style.alignItems = "center";
  selectAllLabel.style.gap = "8px";
  selectAllLabel.style.cursor = "pointer";

  const selectAllCheckbox = document.createElement("input");
  selectAllCheckbox.type = "checkbox";
  selectAllCheckbox.id = "selectAllCheckbox";

  const strongText = document.createElement("strong");
  strongText.textContent = "Select All Fields";

  selectAllLabel.appendChild(selectAllCheckbox);
  selectAllLabel.appendChild(strongText);
  container.appendChild(selectAllLabel);

  container.style.display = "flex";
  container.style.flexDirection = "column";
  container.style.gap = "8px";
  container.style.marginTop = "10px";

  // Build checkboxes (skip S.No. at index 0)
headers.forEach((th, index) => {
  if (index === 0 || th.classList.contains("no-export")) return; // ✅ Skip S.No. and no-export columns
 // ✅ Skip S.No.

    const label = document.createElement("label");
    label.style.display = "flex";
    label.style.alignItems = "center";
    label.style.gap = "6px";
    label.style.cursor = "pointer";

    const checkbox = document.createElement("input");
    checkbox.type = "checkbox";
    checkbox.name = "fields";
    checkbox.value = index;
    checkbox.checked = false;

    const span = document.createElement("span");
    span.textContent = th.textContent.trim();

    label.appendChild(checkbox);
    label.appendChild(span);
    container.appendChild(label);
  });

  selectAllCheckbox.addEventListener("change", (event) => {
    const checkboxes = document.querySelectorAll("#excelCheckboxContainer input[name='fields']");
    checkboxes.forEach(cb => cb.checked = event.target.checked);
  });

  document.getElementById("excelFieldPopup").style.display = "block";
}

function hideExcelFieldPopup() {
  document.getElementById("excelFieldPopup").style.display = "none";
}
function exportSelectedFields(event) {
  event.preventDefault(); // Prevent form from redirecting

  const table = document.querySelector(".custom-table");
  if (!table) return alert("❌ Table not found!");

  const headers = Array.from(table.querySelectorAll("thead th")); // Fixed position

  const selectedIndexes = Array.from(document.querySelectorAll("#excelCheckboxContainer input[name='fields']:checked"))
    .map(cb => parseInt(cb.value));

  if (selectedIndexes.length === 0) {
    alert("❌ Please select at least one field to export.");
    return;
  }

  const rows = Array.from(table.querySelectorAll("tbody tr"))
    .filter(row => row.offsetParent !== null); // Only visible rows

  const data = [];
  let serial = 1;

  for (let row of rows) {
    const cells = Array.from(row.children);
    const rowData = selectedIndexes.map(i => (cells[i]?.innerText || "").trim());
    data.push([serial++, ...rowData]); // ✅ Add S.No.
  }

  const headerRow = ["SL.No."];
  selectedIndexes.forEach(i => {
    headerRow.push(headers[i]?.innerText.trim() || `Column ${i}`);
  });
  data.unshift(headerRow); // ✅ Add header row

  // ✅ Create worksheet and workbook
  const worksheet = XLSX.utils.aoa_to_sheet(data);

  // ✅ Auto-adjust column widths (after worksheet is created)
  worksheet['!cols'] = data[0].map((_, colIndex) => {
    const maxLen = data.reduce((max, row) => {
      const cellValue = row[colIndex] ?? "";
      return Math.max(max, cellValue.toString().length);
    }, 10); // Minimum width fallback
    return { wch: maxLen + 2 }; // Add padding
  });

  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, worksheet, "Sheet1");

  // ✅ Export as XLSX
  XLSX.writeFile(workbook, "Over_all_Placed_Students.xlsx");

  hideExcelFieldPopup();
}

$(document).ready(function() {
    $('#course-multiselect').select2({
      placeholder: "Select course(s)",
      allowClear: true
    });
  });
  function deleteRow(button) {
    if (!confirm("Are you sure you want to delete this row?")) return;

    const externalId = button.getAttribute('data-id');

    fetch('delete_row', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'external_id=' + encodeURIComponent(externalId)
    })
    .then(response => response.text())
    .then(result => {
        if (result.trim() === 'success') {
            // Remove the row from the table
            const row = button.closest('tr');
            row.parentNode.removeChild(row);
        } else {
            alert('Failed to delete row: ' + result);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Something went wrong.');
    });
}
</script>

<!--?php endif; ?-->

          </body>

<script>
  
function resetFilterFieldsOnly() {
  const form = document.getElementById('filterForm');
  if (!form) return;

  // Filter-only field names (inside the modal)
  const filterFields = [
    'company_name',
    'full_name',
    'reg_no',
    'upid',
    'course_name',
    'filter_offer',
    'filter_campus',
    'passing_year',
  ];

  filterFields.forEach(name => {
    const field = form.elements[name];
    if (field) field.value = '';
  });

  // Submit the form with only reset filters (won't touch global search)
  form.submit();
}
function resetFilterForm() {
    const form = document.querySelector('#filterModal form');
    form.reset();
}

/*// ✅ Dynamic Custom Field Logic
function addCustomField() {
  const container = document.getElementById('customFieldContainer');
  const index = container.children.length;
  const id = `custom_field_${index}`;
  const div = document.createElement("div");
  div.className = "mb-2";
  div.innerHTML = `
    <input type="text" name="custom_field_names[]" class="form-control mb-1" placeholder="Custom Field Label" required>
  `;
  container.appendChild(div);
}*/
function addCustomField() {
  const container = document.getElementById('customFieldContainer');
  const index = container.children.length;

  // Create wrapper div
  const div = document.createElement("div");
  div.className = "d-flex align-items-center mb-2";

  // Create input field
  const input = document.createElement("input");
  input.type = "text";
  input.name = "custom_field_names[]";
  input.className = "form-control";
  input.placeholder = "Custom Field Label";
  input.required = true;

  // Create delete button with Font Awesome icon
  const deleteBtn = document.createElement("button");
  deleteBtn.type = "button";
  deleteBtn.className = "btn btn-outline-danger btn-sm ms-2";
  deleteBtn.innerHTML = "<i class='fa fa-trash'></i>";
  deleteBtn.title = "Delete this custom field";
  deleteBtn.onclick = function () {
    container.removeChild(div);
  };

  // Append input and delete button to wrapper
  div.appendChild(input);
  div.appendChild(deleteBtn);

  // Add the wrapper to the container
  container.appendChild(div);
}


// ✅ Append custom fields to query string
    // ✅ Append custom fields to query string and handle form submission
    document.addEventListener('DOMContentLoaded', function() {
        const fieldForm = document.getElementById('fieldForm');
        const errorMessageDiv = document.getElementById('error-message'); // Get the error message div

        if (fieldForm) {
            fieldForm.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent default form submission initially

                // Check if at least one checkbox is selected
                const selectedCheckboxes = fieldForm.querySelectorAll('input[name="selected_fields[]"]:checked');
                if (selectedCheckboxes.length === 0) {
                    errorMessageDiv.textContent = "Please select at least one field."; // Set error message
                    errorMessageDiv.style.display = 'block'; // Show error message
                    return; // Stop the function here, preventing form submission
                } else {
                    errorMessageDiv.style.display = 'none'; // Hide error message if fields are selected
                }

                // Collect custom fields (existing logic)
                const inputList = document.querySelectorAll('input[name="custom_field_names[]"]');
                const customFields = Array.from(inputList)
                    .map(i => i.value.trim())
                    .filter(v => v !== '')
                    .map(label => ({ label: label })); // ✅ Only store label

                document.getElementById('custom_fields_list').value = JSON.stringify(customFields);

                // Now, programmatically submit the form after validation and setting the hidden field
                // This will trigger the PHP POST handling block
                fieldForm.submit();
            });
        }
    });

function clearAllFilters() {
  // Remove query params WITHOUT reloading
  history.replaceState({}, '', window.location.pathname);

  // Clear form fields
  const form = document.getElementById("filterForm");
  if (form) {
    // This wipes all form field values
    form.querySelectorAll("input, select").forEach(el => {
      if (el.tagName === "SELECT") {
        el.selectedIndex = 0;
      } else {
        el.value = "";
      }
    });
  }
}

// Modal toggle as usual
function toggleFilterModal() {
  const modal = document.getElementById("filterModal");
  modal.style.display = modal.style.display === "flex" ? "none" : "flex";
}

window.addEventListener("click", function(e) {
  const modal = document.getElementById("filterModal");
  if (e.target === modal) {
    modal.style.display = "none";
  }
});
</script>
