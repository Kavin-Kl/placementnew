<?php
session_start();
require 'config.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Check if admin is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$success_message = '';
$error_message = '';

// Handle Export
if (isset($_POST['export_data'])) {
    $selected_tables = $_POST['tables'] ?? [];

    if (empty($selected_tables)) {
        $error_message = "Please select at least one table to export.";
    } else {
        // Create export directory if it doesn't exist
        $export_dir = 'exports/';
        if (!is_dir($export_dir)) {
            mkdir($export_dir, 0777, true);
        }

        $export_file = $export_dir . 'data_export_' . date('Y-m-d_H-i-s') . '.sql';
        $output = '';

        foreach ($selected_tables as $table) {
            // Validate table name to prevent SQL injection
            $table = $conn->real_escape_string($table);

            // Get table structure
            $create_table = $conn->query("SHOW CREATE TABLE `$table`");
            if ($create_table) {
                $row = $create_table->fetch_row();
                $output .= "\n\n-- Table: $table\n";
                $output .= "DROP TABLE IF EXISTS `$table`;\n";
                $output .= $row[1] . ";\n\n";

                // Get table data
                $result = $conn->query("SELECT * FROM `$table`");
                if ($result && $result->num_rows > 0) {
                    while ($data = $result->fetch_assoc()) {
                        $output .= "INSERT INTO `$table` VALUES(";
                        $values = [];
                        foreach ($data as $value) {
                            if ($value === null) {
                                $values[] = 'NULL';
                            } else {
                                $values[] = "'" . $conn->real_escape_string($value) . "'";
                            }
                        }
                        $output .= implode(',', $values) . ");\n";
                    }
                }
            }
        }

        // Save to file
        file_put_contents($export_file, $output);

        // Download file
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="placement_data_export_' . date('Y-m-d') . '.sql"');
        header('Content-Length: ' . filesize($export_file));
        readfile($export_file);

        // Clean up
        unlink($export_file);
        exit;
    }
}

// Handle Import
if (isset($_POST['import_data']) && isset($_FILES['import_file'])) {
    require 'vendor/autoload.php';

    $file = $_FILES['import_file'];
    $target_table = $_POST['target_table'] ?? '';

    if (empty($target_table)) {
        $error_message = "Please select a table to import into.";
    } elseif ($file['error'] === UPLOAD_ERR_OK) {
        try {
            $filename = $file['tmp_name'];
            $spreadsheet = IOFactory::load($filename);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (empty($rows)) {
                $error_message = "Excel file is empty.";
            } else {
                // First row is assumed to be headers
                $headers = array_shift($rows);
                $headers = array_map('trim', $headers);

                // Get columns from target table
                $table_columns_query = $conn->query("SHOW COLUMNS FROM `$target_table`");
                $table_columns = [];
                while ($col = $table_columns_query->fetch_assoc()) {
                    $table_columns[] = $col['Field'];
                }

                // Validate headers match table columns (case-insensitive)
                $headers_lower = array_map('strtolower', $headers);
                $table_columns_lower = array_map('strtolower', $table_columns);

                $valid_headers = [];
                foreach ($headers as $idx => $header) {
                    if (in_array(strtolower($header), $table_columns_lower)) {
                        $valid_headers[$idx] = $header;
                    }
                }

                if (empty($valid_headers)) {
                    $error_message = "No matching columns found between Excel file and table.";
                } else {
                    // Disable foreign key checks temporarily
                    $conn->query("SET FOREIGN_KEY_CHECKS = 0");

                    $success_count = 0;
                    $error_count = 0;

                    foreach ($rows as $row) {
                        $values = [];
                        $columns = [];

                        foreach ($valid_headers as $idx => $header) {
                            $columns[] = "`" . $conn->real_escape_string($header) . "`";
                            $value = $row[$idx] ?? '';

                            if ($value === '' || $value === null) {
                                $values[] = "NULL";
                            } else {
                                $values[] = "'" . $conn->real_escape_string($value) . "'";
                            }
                        }

                        if (!empty($columns) && !empty($values)) {
                            $sql = "INSERT INTO `$target_table` (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ")";

                            if ($conn->query($sql)) {
                                $success_count++;
                            } else {
                                $error_count++;
                                error_log("SQL Error: " . $conn->error . " | SQL: " . $sql);
                            }
                        }
                    }

                    // Re-enable foreign key checks
                    $conn->query("SET FOREIGN_KEY_CHECKS = 1");

                    if ($error_count > 0) {
                        $success_message = "Import completed with some errors. Successful: $success_count rows, Failed: $error_count rows";
                    } else {
                        $success_message = "Import completed successfully! Imported $success_count rows into $target_table.";
                    }
                }
            }
        } catch (Exception $e) {
            $error_message = "Error reading Excel file: " . $e->getMessage();
        }
    } else {
        $error_message = "Error uploading file.";
    }
}

// Get all tables in database
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

require 'header.php';
?>

<style>
.migration-card {
    background: white;
    border-radius: 8px;
    padding: 30px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.table-checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 15px;
    margin: 20px 0;
    max-height: 400px;
    overflow-y: auto;
    padding: 10px;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    background: #fafafa;
}

.table-checkbox {
    padding: 12px 15px;
    background: white;
    border-radius: 6px;
    border: 2px solid #dee2e6;
    transition: all 0.2s ease;
}

.table-checkbox:hover {
    background: #f0f8ff;
    border-color: #0d6efd;
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(13, 110, 253, 0.15);
}

.table-checkbox label {
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
    font-weight: 500;
    color: #333;
}

.table-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.export-section, .import-section {
    border: 2px solid #e3e6ea;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 30px;
    background: #f8f9fa;
    transition: box-shadow 0.3s ease;
}

.export-section:hover, .import-section:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.section-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    font-size: 1.4rem;
    font-weight: 700;
    color: #650000;
    border-bottom: 3px solid #650000;
    padding-bottom: 10px;
}

.section-header i {
    font-size: 1.6rem;
}

.btn-group-custom {
    display: flex;
    gap: 12px;
    margin-top: 25px;
}

.btn-group-custom .btn {
    padding: 10px 20px;
    font-weight: 600;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.btn-group-custom .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.alert-info {
    background: #cff4fc;
    border: 1px solid #0dcaf0;
    color: #055160;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="migration-card">
                <h3><i class='bx bx-transfer'></i> Data Migration - Import/Export</h3>
                <p class="text-muted">Import data from old website or export current data for backup/migration</p>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Export Section -->
                <div class="export-section">
                    <div class="section-header">
                        <i class='bx bx-export'></i> Export Data
                    </div>
                    <div class="alert-info">
                        <strong>Note:</strong> Export selected tables to SQL file. This file can be imported to another installation.
                    </div>
                    <form method="POST">
                        <label class="mb-2"><strong>Select tables to export:</strong></label>
                        <div class="mb-3">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll()">Select All</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAll()">Deselect All</button>
                        </div>
                        <div class="table-checkbox-grid">
                            <?php foreach ($tables as $table): ?>
                                <div class="table-checkbox">
                                    <label>
                                        <input type="checkbox" name="tables[]" value="<?= htmlspecialchars($table) ?>" class="table-check">
                                        <?= htmlspecialchars($table) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="btn-group-custom">
                            <button type="submit" name="export_data" class="btn btn-primary">
                                <i class='bx bx-export'></i> Export Selected Tables
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Import Section -->
                <div class="import-section">
                    <div class="section-header">
                        <i class='bx bx-import'></i> Import Data
                    </div>
                    <div class="alert-info">
                        <strong>Warning:</strong> Importing will replace existing data in the tables. Make sure to backup your current data before importing.
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group mb-3">
                            <label><strong>Select table to import into:</strong></label>
                            <select name="target_table" required class="form-control mt-2">
                                <option value="">-- Select Table --</option>
                                <?php foreach ($tables as $table): ?>
                                    <option value="<?= htmlspecialchars($table) ?>"><?= htmlspecialchars($table) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Choose which table to import data into</small>
                        </div>
                        <div class="form-group">
                            <label><strong>Select XLSX file to import:</strong></label>
                            <input type="file" name="import_file" accept=".xlsx,.xls,.csv" required class="form-control mt-2">
                            <small class="text-muted">Upload the Excel export file from your old website</small>
                        </div>
                        <div class="btn-group-custom">
                            <button type="submit" name="import_data" class="btn btn-success">
                                <i class='bx bx-import'></i> Import Data
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function selectAll() {
    document.querySelectorAll('.table-check').forEach(cb => cb.checked = true);
}

function deselectAll() {
    document.querySelectorAll('.table-check').forEach(cb => cb.checked = false);
}
</script>

<?php require 'footer.php'; ?>
