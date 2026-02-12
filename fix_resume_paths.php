<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    die("Unauthorized access");
}

include("config.php");
include("header.php");

$fixed_count = 0;
$missing_count = 0;
$already_correct = 0;
$log = [];

// Get all applications with resume files
$result = $conn->query("SELECT application_id, resume_file, student_name FROM applications WHERE resume_file IS NOT NULL AND resume_file != ''");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $app_id = $row['application_id'];
        $old_path = $row['resume_file'];
        $student_name = $row['student_name'];

        // Skip if already in correct format
        if (strpos($old_path, 'uploads/resumes/') === 0 && file_exists($old_path)) {
            $already_correct++;
            continue;
        }

        // Extract filename from path
        $filename = basename($old_path);

        // Try to find the file in different locations
        $possible_paths = [
            $old_path,
            'uploads/' . $filename,
            'uploads/resumes/' . $filename
        ];

        $found_path = null;
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                $found_path = $path;
                break;
            }
        }

        if ($found_path) {
            // Create resumes directory if it doesn't exist
            if (!is_dir('uploads/resumes/')) {
                mkdir('uploads/resumes/', 0777, true);
            }

            $new_path = 'uploads/resumes/' . $filename;

            // Move file if not already in correct location
            if ($found_path !== $new_path) {
                if (copy($found_path, $new_path)) {
                    // Update database
                    $update_stmt = $conn->prepare("UPDATE applications SET resume_file = ? WHERE application_id = ?");
                    $update_stmt->bind_param("si", $new_path, $app_id);

                    if ($update_stmt->execute()) {
                        $fixed_count++;
                        $log[] = "✓ Fixed: $student_name - moved from $found_path to $new_path";
                    }
                    $update_stmt->close();
                } else {
                    $log[] = "✗ Failed to copy: $found_path to $new_path";
                }
            } else {
                // Just update database path
                $update_stmt = $conn->prepare("UPDATE applications SET resume_file = ? WHERE application_id = ?");
                $update_stmt->bind_param("si", $new_path, $app_id);

                if ($update_stmt->execute()) {
                    $fixed_count++;
                    $log[] = "✓ Updated path: $student_name - $new_path";
                }
                $update_stmt->close();
            }
        } else {
            $missing_count++;
            $log[] = "✗ Missing file: $student_name - $old_path not found";
        }
    }
}

?>

<div class="heading-container">
    <h3 class="headings">Resume Path Fix Utility</h3>
    <p>Fix resume file paths and move files to the correct directory</p>
</div>

<div class="container-fluid">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title">Summary</h5>
            <div class="row">
                <div class="col-md-3">
                    <div class="alert alert-success">
                        <strong>Fixed:</strong> <?= $fixed_count ?> files
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="alert alert-warning">
                        <strong>Missing:</strong> <?= $missing_count ?> files
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="alert alert-info">
                        <strong>Already Correct:</strong> <?= $already_correct ?> files
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="alert alert-primary">
                        <strong>Total:</strong> <?= ($fixed_count + $missing_count + $already_correct) ?> files
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h5 class="card-title">Detailed Log</h5>
            <div style="max-height: 500px; overflow-y: auto; font-family: monospace; font-size: 12px;">
                <?php foreach ($log as $entry): ?>
                    <div style="padding: 5px; border-bottom: 1px solid #eee;">
                        <?= htmlspecialchars($entry) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if ($missing_count > 0): ?>
    <div class="alert alert-warning mt-4">
        <strong>Note:</strong> <?= $missing_count ?> resume files could not be found. These files may need to be re-uploaded by students, or they may exist in a different location on the server. Check the uploads directory manually.
    </div>
    <?php endif; ?>

    <div class="mt-4">
        <a href="dashboard" class="btn btn-primary">Back to Dashboard</a>
        <button onclick="location.reload()" class="btn btn-secondary">Run Again</button>
    </div>
</div>

<style>
.container-fluid {
    padding: 20px;
}

.alert {
    margin-bottom: 0;
    text-align: center;
}

.card-title {
    color: #650000;
    font-weight: 600;
    margin-bottom: 20px;
}
</style>

<?php include("footer.php"); ?>
