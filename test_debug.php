<?php
/**
 * PLACEMENT CELL - DIAGNOSTIC TOOL
 * This file helps diagnose setup issues
 * Open: http://localhost/placementcell/test_debug.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>";
echo "<html><head>";
echo "<title>Placement Cell - Diagnostic Test</title>";
echo "<style>
body {
    font-family: Arial, sans-serif;
    max-width: 900px;
    margin: 40px auto;
    padding: 20px;
    background: #f5f5f5;
}
.test-section {
    background: white;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
h1 {
    color: #581729;
    border-bottom: 3px solid #581729;
    padding-bottom: 10px;
}
h2 {
    color: #581729;
    margin-top: 0;
}
.success {
    color: green;
    font-weight: bold;
}
.error {
    color: red;
    font-weight: bold;
}
.warning {
    color: orange;
    font-weight: bold;
}
.info {
    background: #e3f2fd;
    padding: 10px;
    border-left: 4px solid #2196F3;
    margin: 10px 0;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin: 10px 0;
}
table td {
    padding: 8px;
    border: 1px solid #ddd;
}
table td:first-child {
    font-weight: bold;
    width: 40%;
    background: #f9f9f9;
}
pre {
    background: #f4f4f4;
    padding: 10px;
    border-radius: 4px;
    overflow-x: auto;
}
</style>";
echo "</head><body>";

echo "<h1>🔍 Placement Cell System Diagnostic</h1>";

// ============================================
// TEST 1: PHP Environment
// ============================================
echo "<div class='test-section'>";
echo "<h2>1. PHP Environment Check</h2>";
echo "<table>";
echo "<tr><td>PHP Version:</td><td>" . phpversion() . "</td></tr>";
echo "<tr><td>Server Software:</td><td>" . $_SERVER['SERVER_SOFTWARE'] . "</td></tr>";
echo "<tr><td>Document Root:</td><td>" . $_SERVER['DOCUMENT_ROOT'] . "</td></tr>";
echo "<tr><td>Current Directory:</td><td>" . __DIR__ . "</td></tr>";
echo "<tr><td>Current File:</td><td>" . __FILE__ . "</td></tr>";
echo "</table>";

if (version_compare(phpversion(), '8.0', '>=')) {
    echo "<p class='success'>✓ PHP version is compatible (8.0+)</p>";
} else {
    echo "<p class='warning'>⚠ PHP version is " . phpversion() . " (recommended 8.0+)</p>";
}
echo "</div>";

// ============================================
// TEST 2: Critical Files Check
// ============================================
echo "<div class='test-section'>";
echo "<h2>2. Critical Files Check</h2>";

$critical_files = [
    'config.php' => 'Database configuration',
    'course_groups.php' => 'Course definitions (CRITICAL for registration)',
    'header.php' => 'Admin navigation',
    'footer.php' => 'Footer template',
    'student_header.php' => 'Student navigation',
    'check_deadlines_on_load.php' => 'Deadline checker (new feature)',
    'admin_notifications.php' => 'Notification center (new feature)',
    'edit_form_fields.php' => 'Form field editor (new feature)',
    'student_register.php' => 'Student registration page',
    'generate_course_report.php' => 'Report generator'
];

echo "<table>";
echo "<tr><td><strong>File</strong></td><td><strong>Status</strong></td><td><strong>Size</strong></td></tr>";

$all_files_ok = true;
foreach ($critical_files as $file => $description) {
    $exists = file_exists($file);
    $size = $exists ? filesize($file) : 0;

    echo "<tr>";
    echo "<td>$file<br><small style='color:#666'>$description</small></td>";

    if ($exists) {
        if ($size > 0) {
            echo "<td class='success'>✓ EXISTS</td>";
            echo "<td>" . number_format($size) . " bytes</td>";
        } else {
            echo "<td class='error'>✗ EMPTY FILE (0 bytes)</td>";
            echo "<td>0 bytes</td>";
            $all_files_ok = false;
        }
    } else {
        echo "<td class='error'>✗ MISSING</td>";
        echo "<td>-</td>";
        $all_files_ok = false;
    }
    echo "</tr>";
}
echo "</table>";

if ($all_files_ok) {
    echo "<p class='success'>✓ All critical files present and valid</p>";
} else {
    echo "<p class='error'>✗ Some files are missing or empty - see table above</p>";
    echo "<div class='info'><strong>Action Required:</strong> Get missing files from the sender</div>";
}
echo "</div>";

// ============================================
// TEST 3: course_groups.php Detailed Test
// ============================================
echo "<div class='test-section'>";
echo "<h2>3. Course Groups File Test (CRITICAL)</h2>";

if (file_exists('course_groups.php')) {
    echo "<p class='success'>✓ course_groups.php file exists</p>";
    echo "<p>File size: " . filesize('course_groups.php') . " bytes</p>";

    // Check file encoding
    $file_content = file_get_contents('course_groups.php');
    if (substr($file_content, 0, 3) == "\xEF\xBB\xBF") {
        echo "<p class='warning'>⚠ File has UTF-8 BOM - may cause issues</p>";
        echo "<div class='info'>Fix: Open in Notepad++, Encoding → Convert to UTF-8 without BOM</div>";
    } else {
        echo "<p class='success'>✓ File encoding is OK (no BOM)</p>";
    }

    // Check if file starts correctly
    if (strpos(ltrim($file_content), '<?php') === 0) {
        echo "<p class='success'>✓ File starts correctly with &lt;?php</p>";
    } else {
        echo "<p class='error'>✗ File does not start with &lt;?php</p>";
    }

    // Try to include it
    echo "<p>Attempting to include course_groups.php...</p>";
    try {
        include "course_groups.php";
        echo "<p class='success'>✓ File included successfully</p>";

        // Check variables
        echo "<h3>Variable Check:</h3>";
        echo "<table>";

        $vars_to_check = [
            'ug_courses_grouped' => 'UG courses grouped by school',
            'pg_courses_grouped' => 'PG courses grouped by school',
            'UG_COURSES' => 'Flat array of all UG courses',
            'PG_COURSES' => 'Flat array of all PG courses',
            'UG_GROUPED_COURSES' => 'UG courses with school labels',
            'PG_GROUPED_COURSES' => 'PG courses with school labels'
        ];

        $all_vars_ok = true;
        foreach ($vars_to_check as $var => $desc) {
            echo "<tr>";
            echo "<td>\$$var<br><small style='color:#666'>$desc</small></td>";

            if (isset($$var)) {
                $count = is_array($$var) ? count($$var) : 0;
                if ($count > 0) {
                    echo "<td class='success'>✓ Exists ($count items)</td>";
                } else {
                    echo "<td class='error'>✗ Empty array</td>";
                    $all_vars_ok = false;
                }
            } else {
                echo "<td class='error'>✗ Not defined</td>";
                $all_vars_ok = false;
            }
            echo "</tr>";
        }
        echo "</table>";

        if ($all_vars_ok) {
            echo "<p class='success'>✓ All course variables are properly defined</p>";

            // Show sample data
            echo "<h3>Sample Course Data:</h3>";
            echo "<p><strong>First 5 UG Courses:</strong></p>";
            echo "<pre>";
            print_r(array_slice($UG_COURSES, 0, 5));
            echo "</pre>";

            echo "<p><strong>First 5 PG Courses:</strong></p>";
            echo "<pre>";
            print_r(array_slice($PG_COURSES, 0, 5));
            echo "</pre>";

        } else {
            echo "<p class='error'>✗ Course variables are not properly defined</p>";
            echo "<div class='info'>The course_groups.php file may be corrupted or incomplete</div>";
        }

    } catch (Exception $e) {
        echo "<p class='error'>✗ Error including file: " . $e->getMessage() . "</p>";
        $all_vars_ok = false;
    }

} else {
    echo "<p class='error'>✗ course_groups.php file NOT FOUND</p>";
    echo "<div class='info'><strong>This is why student_register.php and generate_course_report.php are failing!</strong><br>";
    echo "Action: Get course_groups.php from the sender and place it in this folder.</div>";
}
echo "</div>";

// ============================================
// TEST 4: Database Connection
// ============================================
echo "<div class='test-section'>";
echo "<h2>4. Database Connection Test</h2>";

if (file_exists('config.php')) {
    include 'config.php';

    if (isset($conn) && $conn instanceof mysqli) {
        if ($conn->connect_error) {
            echo "<p class='error'>✗ Database connection failed: " . $conn->connect_error . "</p>";
        } else {
            echo "<p class='success'>✓ Database connected successfully</p>";
            echo "<table>";
            echo "<tr><td>Database Name:</td><td>" . $db . "</td></tr>";
            echo "<tr><td>Host:</td><td>" . $host . "</td></tr>";
            echo "<tr><td>User:</td><td>" . $user . "</td></tr>";
            echo "</table>";

            // Check critical tables
            echo "<h3>Database Tables Check:</h3>";
            $required_tables = [
                'students',
                'drives',
                'drive_roles',
                'applications',
                'placed_students',
                'admin_users',
                'admin_notifications',
                'deadline_notifications_sent'
            ];

            echo "<table>";
            echo "<tr><td><strong>Table Name</strong></td><td><strong>Status</strong></td><td><strong>Row Count</strong></td></tr>";

            $all_tables_ok = true;
            foreach ($required_tables as $table) {
                $check = $conn->query("SHOW TABLES LIKE '$table'");
                echo "<tr>";
                echo "<td>$table</td>";

                if ($check && $check->num_rows > 0) {
                    $count_result = $conn->query("SELECT COUNT(*) as cnt FROM `$table`");
                    $count = $count_result ? $count_result->fetch_assoc()['cnt'] : 0;
                    echo "<td class='success'>✓ Exists</td>";
                    echo "<td>$count rows</td>";
                } else {
                    echo "<td class='error'>✗ Missing</td>";
                    echo "<td>-</td>";
                    $all_tables_ok = false;
                }
                echo "</tr>";
            }
            echo "</table>";

            if ($all_tables_ok) {
                echo "<p class='success'>✓ All required tables exist</p>";
            } else {
                echo "<p class='error'>✗ Some tables are missing</p>";
                echo "<div class='info'><strong>Action Required:</strong><br>";
                echo "1. Import the main database (admin_placement_db.sql)<br>";
                echo "2. Run sql/create_admin_notifications.sql<br>";
                echo "3. Run sql/add_missing_columns.sql</div>";
            }
        }
    } else {
        echo "<p class='error'>✗ Database connection object not created</p>";
    }
} else {
    echo "<p class='error'>✗ config.php not found</p>";
}
echo "</div>";

// ============================================
// TEST 5: SQL Scripts Check
// ============================================
echo "<div class='test-section'>";
echo "<h2>5. SQL Scripts Check</h2>";

$sql_files = [
    'sql/create_admin_notifications.sql' => 'Creates notification system tables',
    'sql/add_missing_columns.sql' => 'Adds off-campus tracking columns'
];

echo "<table>";
echo "<tr><td><strong>SQL Script</strong></td><td><strong>Status</strong></td></tr>";

foreach ($sql_files as $file => $desc) {
    echo "<tr>";
    echo "<td>$file<br><small style='color:#666'>$desc</small></td>";

    if (file_exists($file)) {
        echo "<td class='success'>✓ EXISTS</td>";
    } else {
        echo "<td class='error'>✗ MISSING</td>";
    }
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// ============================================
// TEST 6: Page Access Test
// ============================================
echo "<div class='test-section'>";
echo "<h2>6. Quick Page Access Test</h2>";

echo "<p>Try accessing these pages:</p>";
echo "<ul>";
echo "<li><a href='index.php' target='_blank'>Admin Login</a></li>";
echo "<li><a href='student_login.php' target='_blank'>Student Login</a></li>";
echo "<li><a href='student_register.php' target='_blank'>Student Registration</a> (Tests course_groups.php)</li>";
echo "<li><a href='admin_notifications.php' target='_blank'>Admin Notifications</a> (New feature)</li>";
echo "</ul>";
echo "</div>";

// ============================================
// SUMMARY
// ============================================
echo "<div class='test-section' style='background:#581729; color:white;'>";
echo "<h2 style='color:white;'>📋 Diagnostic Summary</h2>";

$issues = [];

if (!$all_files_ok) {
    $issues[] = "Some critical files are missing or empty";
}

if (!file_exists('course_groups.php')) {
    $issues[] = "course_groups.php is MISSING - this is causing registration and report errors";
}

if (isset($conn) && $conn->connect_error) {
    $issues[] = "Database connection failed";
}

if (!empty($issues)) {
    echo "<p style='color:#ffeb3b;'><strong>⚠ Issues Found:</strong></p>";
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li>$issue</li>";
    }
    echo "</ul>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Fix the issues listed above</li>";
    echo "<li>Refresh this page to re-test</li>";
    echo "<li>Once all green, delete this test_debug.php file</li>";
    echo "</ol>";
} else {
    echo "<p style='color:#4caf50; font-size:18px;'><strong>✓ All tests passed! System is ready to use.</strong></p>";
    echo "<p>You can now:</p>";
    echo "<ul>";
    echo "<li>Login as admin</li>";
    echo "<li>Students can register</li>";
    echo "<li>Generate reports</li>";
    echo "<li>Use all features</li>";
    echo "</ul>";
    echo "<p style='color:#ffeb3b;'><strong>Security Note:</strong> Delete this test_debug.php file after testing!</p>";
}

echo "</div>";

echo "<div style='text-align:center; padding:20px; color:#666; font-size:12px;'>";
echo "Placement Cell System v2.0 | Diagnostic Tool";
echo "</div>";

echo "</body></html>";
?>
