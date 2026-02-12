<?php
session_start();
include("config.php");
if (!isset($_SESSION['admin_id'])) {
    header("Location: index");
    exit;
}
date_default_timezone_set('Asia/Kolkata'); // set to your timezone


$backupDir = __DIR__ . '/previous_year_data/';

// Ensure folder exists
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}

// Upload & Rename Logic
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // File upload
   if (isset($_FILES['upload_file'])) {
    $totalFiles = count($_FILES['upload_file']['name']);
    $uploadSuccess = 0;
    $uploadFail = 0;

    for ($i = 0; $i < $totalFiles; $i++) {
        $fileName = basename($_FILES['upload_file']['name'][$i]);
        $tmpName = $_FILES['upload_file']['tmp_name'][$i];
        $error = $_FILES['upload_file']['error'][$i];

        if ($error === UPLOAD_ERR_OK) {
            $targetPath = $backupDir . $fileName;
            if (move_uploaded_file($tmpName, $targetPath)) {
                $uploadSuccess++;
            } else {
                $uploadFail++;
            }
        } else {
            $uploadFail++;
        }
    }

    if ($uploadSuccess > 0 && $uploadFail === 0) {
        $message = "<div class='alert success'>$uploadSuccess file(s) uploaded successfully!</div>";
    } elseif ($uploadSuccess > 0 && $uploadFail > 0) {
        $message = "<div class='alert error'>$uploadSuccess uploaded, $uploadFail failed.</div>";
    } else {
        $message = "<div class='alert error'>All file uploads failed.</div>";
    }
}


    // File rename
    if (isset($_POST['rename_file'], $_POST['new_name'])) {
        $oldName = basename($_POST['rename_file']);
        $oldPath = $backupDir . $oldName;

        if (file_exists($oldPath)) {
            $ext = pathinfo($oldName, PATHINFO_EXTENSION);
            $newBaseName = pathinfo($_POST['new_name'], PATHINFO_FILENAME);
            $newName = $newBaseName . '.' . $ext;
            $newPath = $backupDir . $newName;

            if (!file_exists($newPath)) {
                rename($oldPath, $newPath);
                $message = "<div class='alert success'> File renamed successfully!</div>";
            } else {
                $message = "<div class='alert error'> A file with this name already exists.</div>";
            }
        } else {
            $message = "<div class='alert error'> Original file not found.</div>";
        }
    }
}

// Delete file
if (isset($_GET['delete'])) {
    $fileName = basename($_GET['delete']);
    $filePath = $backupDir . $fileName;

    if (file_exists($filePath)) {
        unlink($filePath);
        $_SESSION['message'] = "<div class='alert success'> File deleted successfully!</div>";
    } else {
        $_SESSION['message'] = "<div class='alert error'> File not found.</div>";
    }

    header("Location: old_files_storage.php");
    exit;
}

// Display messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Download file
if (isset($_GET['file'])) {
    $fileName = basename($_GET['file']);
    $filePath = $backupDir . $fileName;
    if (file_exists($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        echo "<script>alert('File not found.');window.location='old_files_storage.php';</script>";
        exit;
    }
}

function formatSize($bytes) {
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}
$files = array_diff(scandir($backupDir), ['.', '..']);
?>

<?php include 'header.php'; ?>
<div>
        <div class="heading-container">
            <h3 class="headings"></i> Previous Years Data</h3>
            <p>Upload, view, download, rename, or delete archived project or backup files.</p>
        </div>
        <!-- Search -->
        <div class="left-controls">
            <input type="text" id="searchInput"  placeholder="Search...">

            <a href="old_files_storage" class="reset-button">
            <i class="fa fa-undo" aria-hidden="true"></i> <span>Reset</span>
            </a>
        </div>

        <?= $message ?>

        <div class="upload-section">
            <form method="POST" enctype="multipart/form-data" class="upload-form">
                <label class="upload-label">Select File:</label>
                <input type="file" name="upload_file[]" multiple required>

                <button type="submit" class="upload-btn"><i class="bi bi-cloud-upload"></i> Upload</button>
            </form>
            <p class="upload-hint">Supports ZIP, PDF, Excel, Word, and other file types.</p>
        </div>

        <hr>

        <?php if (count($files) === 0): ?>
            <p class="no-files">No files available in storage.</p>
        <?php else: ?>
            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
            <table class="table table-bordered table-striped custom-table">
                <thead>
                    <tr>
                        <th style="background-color: #650000; color: white;">Sl no.</th>
                        <th style="background-color: #650000; color: white;">File Name</th>
                        <th style="background-color: #650000; color: white;">Size (KB)</th>
                        <th style="background-color: #650000; color: white;">Last Modified</th>
                        <th style="background-color: #650000; color: white;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $i = 1;
                    foreach ($files as $file): 
                        $filePath = $backupDir . $file;
                        $size = round(filesize($filePath) / 1024, 2);
                        //$size = formatSize(filesize($filePath));
                        $date = date("d M Y, h:i A", filemtime($filePath));
                        
                    ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <!-- Click to view -->
                        <td>
                            <a href="previous_year_data/<?= urlencode($file) ?>" target="_blank" 
                            title="Click to view file"
                            style="text-decoration:none; color:#007bff; font-weight:500;">
                                <?= htmlspecialchars($file) ?>
                            </a>
                        </td>
                        <td><?= $size ?></td>
                        <td><?= $date ?></td>
                        <td>
                            <div class="file-actions">
                                <a href="?file=<?= urlencode($file) ?>" class="download-btn"><i class="bi bi-download"></i> Download</a>
                                <a href="?delete=<?= urlencode($file) ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this file?');"><i class="bi bi-trash"></i> Delete</a>
                                <button type="button" class="rename-btn" onclick="openRenameModal('<?= htmlspecialchars($file) ?>')"><i class="bi bi-pencil-square"></i> Rename</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                    </div>
            </table>
        <?php endif; ?>

</div>

<!-- Rename Modal -->
<div id="renameModal" class="modal" style="display:none; position:fixed; z-index:1000; padding-top:100px; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5);">
    <div class="rename-modal-content" style="background-color:#fff; margin:auto; padding:15px; border-radius:8px; width:280px; text-align:center; box-shadow:0 4px 10px rgba(0,0,0,0.3);">
        <span class="close" onclick="closeRenameModal()" style="color:#aaa; float:right; font-size:24px; font-weight:bold; cursor:pointer;">&times;</span>
        <h3 style="margin-top:5px; font-size:18px;">Rename File</h3>
        <form method="POST">
            <input type="hidden" id="rename_file_input" name="rename_file">
            <input type="text" name="new_name" placeholder="Enter new file name" required 
                   style="width:85%; padding:6px; font-size:14px; border:1px solid #ccc; border-radius:5px; margin-top:10px;">
            <button type="submit" class="rename-btn" 
                    style="margin-top:12px; background-color:#ffc107; color:white; border:none; padding:6px 12px; border-radius:5px; cursor:pointer; font-size:13px;">
                Rename
            </button>
        </form>
    </div>
</div>



<style>
    .rename-modal-content {
    width:450px !important;
}

.upload-section {  margin-top: 30px;  background-color: white;  border: 2px dashed #650000;  border-radius: 10px;  padding: 15px;  transition: 0.3s; }
.upload-section:hover { background-color: #f9f9f9; }
.upload-form { display: flex; align-items: center;  justify-content: flex-start;  gap: 12px;}
.upload-form input[type="file"] {  border: 1px solid #ccc;  padding: 6px;  border-radius: 6px;  cursor: pointer; }
.upload-label { font-weight: 600; white-space: nowrap; }
.upload-btn { background-color: #28a745; color: white; border: none; padding: 8px 8px; border-radius: 6px; cursor: pointer; transition: 0.3s; }
.upload-btn:hover { background-color: #650000; }
.upload-hint { font-size: 13px; color: #666; margin: 8px; text-align: center;}
.download-btn { background-color: #28a745; color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none; font-size: 13px; margin-right: 5px; }
.download-btn:hover { background-color: #218838; }
.delete-btn { background-color: #dc3545; color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none; font-size: 13px; margin-right: 5px; }
.delete-btn:hover { background-color: #a71d2a; }
.rename-btn { background-color: #ffc107; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; font-size: 13px; }
.rename-btn:hover { background-color: #e0a800; }

.no-files { color: #777; text-align: center; font-size: 16px; }
.alert { text-align: center; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-weight: 500; }
.alert.success { background-color: #e6f4ea; color: #276749; }
.alert.error { background-color: #fdecea; color: #b02a37; }

/* Modal CSS */
.modal { display: none; position: fixed; z-index: 1000; padding-top: 100px; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
.modal-content { background-color: #fff; margin: auto; padding: 20px; border-radius: 8px; width: 400px; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
.close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
.close:hover { color: black; }

</style>

<script>
function openRenameModal(fileName) {
    document.getElementById('rename_file_input').value = fileName;
    document.getElementById('renameModal').style.display = 'block';
}
function closeRenameModal() {
    document.getElementById('renameModal').style.display = 'none';
}
window.onclick = function(event) {
    let modal = document.getElementById('renameModal');
    if (event.target == modal) {
        modal.style.display = "none";
    }
}

// Auto-hide alerts after 5 seconds
setTimeout(() => {
    const alertBox = document.querySelector('.alert');
    if(alertBox) {
        alertBox.style.transition = "opacity 0.5s";
        alertBox.style.opacity = '0';
        setTimeout(() => alertBox.remove(), 500); // Remove completely after fade
    }
}, 5000);

if (window.history.replaceState) {
  window.history.replaceState(null, null, window.location.href);
}


document.getElementById('searchInput').addEventListener('keyup', function() {
    const query = this.value.toLowerCase();
    const rows = document.querySelectorAll('table tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
});

</script>


<?php include("footer.php"); ?>
