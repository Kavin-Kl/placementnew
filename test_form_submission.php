<?php
session_start();
$_SESSION['admin_id'] = 1;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["csv_file"])) {
    echo "<h2>SUCCESS! Form was submitted!</h2>";
    echo "<pre>";
    echo "File details:\n";
    print_r($_FILES["csv_file"]);
    echo "\n\nPOST data:\n";
    print_r($_POST);
    echo "</pre>";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Form Submission Test</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .ipt_modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); align-items: center; justify-content: center; }
        .ipt_modal-content { background-color: white; padding: 20px; border-radius: 5px; width: 400px; }
        .ipt_import-option { display: block; padding: 15px; margin: 10px 0; background: #f0f0f0; cursor: pointer; border-radius: 5px; text-align: center; }
        .ipt_import-option:hover { background: #e0e0e0; }
    </style>
</head>
<body>
    <h1>Form Submission Test</h1>
    <button type="button" id="openImportPopup">Open Import Modal</button>

    <div id="ipt_importPopup" class="ipt_modal">
        <div class="ipt_modal-content">
            <span class="ipt_close-btn" style="float:right; cursor:pointer;">&times;</span>
            <h5>Select Import Option</h5>

            <form method="POST" enctype="multipart/form-data" class="import-form" onsubmit="return validateFilename()">
                <label for="csv_file" class="ipt_import-option">
                    <i class="fa fa-download"></i> Import Excel File
                </label>
                <input type="file" id="csv_file" name="csv_file" accept=".csv,.xls,.xlsx" required style="display:none;" onchange="validateAndSubmit()">
            </form>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const openPopup = document.getElementById("openImportPopup");
            const popup = document.getElementById("ipt_importPopup");
            const closePopup = document.querySelector(".ipt_close-btn");

            openPopup.addEventListener("click", (e) => {
                e.stopPropagation();
                popup.style.display = "flex";
            });

            window.addEventListener("click", (e) => {
                if (e.target === popup) {
                    popup.style.display = "none";
                }
            });

            closePopup.addEventListener("click", () => {
                popup.style.display = "none";
            });
        });

        function validateAndSubmit() {
            console.log("validateAndSubmit called");
            const input = document.getElementById("csv_file");
            const file = input.files[0];

            console.log("File selected:", file);

            if (!file) {
                console.log("No file selected");
                return;
            }

            const filename = file.name;
            const pattern = /\d{4}-\d{4}/;

            console.log("Filename:", filename);
            console.log("Pattern test:", pattern.test(filename));

            if (!pattern.test(filename)) {
                alert('Error: Filename must include batch year in format YYYY-YYYY (e.g., vantage_students_2023-2026.xlsx)');
                input.value = '';
                return false;
            }

            // Show loading indicator
            const modal = document.getElementById("ipt_importPopup");
            const modalContent = modal.querySelector(".ipt_modal-content");
            modalContent.innerHTML = '<div style="text-align:center; padding:40px;"><i class="fas fa-spinner fa-spin" style="font-size:48px; color:#007bff;"></i><p style="margin-top:20px; font-size:16px;">Importing file... Please wait.</p></div>';

            console.log("About to submit form");
            console.log("Form:", input.form);

            // Submit the form
            input.form.submit();

            console.log("Form submitted");
        }

        function validateFilename() {
            return true;
        }
    </script>
</body>
</html>
