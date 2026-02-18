<!DOCTYPE html>
<html>
<head>
    <title>Upload Test Receiver</title>
</head>
<body>
    <h1>Upload Test</h1>

    <?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        echo "<h2>POST Request Received</h2>";
        echo "<pre>";
        echo "POST data:\n";
        print_r($_POST);
        echo "\n\nFILES data:\n";
        print_r($_FILES);
        echo "\n\nServer variables:\n";
        echo "REQUEST_METHOD: " . $_SERVER["REQUEST_METHOD"] . "\n";
        echo "CONTENT_TYPE: " . ($_SERVER["CONTENT_TYPE"] ?? 'not set') . "\n";
        echo "CONTENT_LENGTH: " . ($_SERVER["CONTENT_LENGTH"] ?? 'not set') . "\n";
        echo "</pre>";

        if (isset($_FILES["csv_file"])) {
            echo "<h3>File Upload Details</h3>";
            echo "<pre>";
            $file = $_FILES["csv_file"];
            echo "Name: " . $file["name"] . "\n";
            echo "Type: " . $file["type"] . "\n";
            echo "Size: " . $file["size"] . " bytes\n";
            echo "Tmp name: " . $file["tmp_name"] . "\n";
            echo "Error: " . $file["error"] . "\n";

            if ($file["error"] === UPLOAD_ERR_OK) {
                echo "\nFile uploaded successfully!\n";
                echo "File exists at temp location: " . (file_exists($file["tmp_name"]) ? "YES" : "NO") . "\n";
            } else {
                echo "\nUpload error code: " . $file["error"] . "\n";
                echo "Error meaning: ";
                switch ($file["error"]) {
                    case UPLOAD_ERR_INI_SIZE:
                        echo "File exceeds upload_max_filesize\n";
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        echo "File exceeds MAX_FILE_SIZE\n";
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        echo "File was only partially uploaded\n";
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        echo "No file was uploaded\n";
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        echo "Missing temporary folder\n";
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        echo "Failed to write file to disk\n";
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        echo "A PHP extension stopped the file upload\n";
                        break;
                    default:
                        echo "Unknown error\n";
                }
            }
            echo "</pre>";
        } else {
            echo "<h3 style='color:red;'>No file found in request!</h3>";
        }
    } else {
        ?>
        <p>This page receives form submissions. Use this to test if the upload form is working.</p>

        <form method="POST" enctype="multipart/form-data">
            <label>Select a file:</label><br>
            <input type="file" name="csv_file" accept=".csv,.xls,.xlsx" required><br><br>
            <button type="submit">Upload</button>
        </form>
        <?php
    }
    ?>
</body>
</html>
