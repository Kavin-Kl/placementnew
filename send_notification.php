<?php
session_start();
require 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $recipient_type = $_POST['recipient_type'];
    $selected_courses = $_POST['courses'] ?? [];
    $selected_program_types = $_POST['program_types'] ?? [];

    if (empty($title) || empty($message)) {
        $error_message = "Title and message are required.";
    } else {
        // Build query based on recipient type
        $student_query = "SELECT student_id, student_name, email FROM students WHERE 1=1";
        $params = [];
        $types = '';

        if ($recipient_type === 'specific_course' && !empty($selected_courses)) {
            $placeholders = implode(',', array_fill(0, count($selected_courses), '?'));
            $student_query .= " AND course IN ($placeholders)";
            $params = array_merge($params, $selected_courses);
            $types .= str_repeat('s', count($selected_courses));
        } elseif ($recipient_type === 'program_type' && !empty($selected_program_types)) {
            $placeholders = implode(',', array_fill(0, count($selected_program_types), '?'));
            $student_query .= " AND program_type IN ($placeholders)";
            $params = array_merge($params, $selected_program_types);
            $types .= str_repeat('s', count($selected_program_types));
        }
        // If 'all', no additional filter needed

        $stmt = $conn->prepare($student_query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $students = $stmt->get_result();

        $sent_count = 0;
        $insert_stmt = $conn->prepare("
            INSERT INTO student_notifications (student_id, title, message, type, created_at)
            VALUES (?, ?, ?, 'general', NOW())
        ");

        while ($student = $students->fetch_assoc()) {
            $insert_stmt->bind_param("iss", $student['student_id'], $title, $message);
            if ($insert_stmt->execute()) {
                $sent_count++;
            }
        }

        if ($sent_count > 0) {
            $success_message = "Notification sent successfully to $sent_count student(s)!";
        } else {
            $error_message = "No students matched the selected criteria.";
        }
    }
}

// Get course list
include('course_groups_dynamic.php');

require 'header.php';
?>

<style>
.notification-card {
    background: white;
    border-radius: 8px;
    padding: 30px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #650000;
}

.form-group input[type="text"],
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group textarea {
    min-height: 120px;
    resize: vertical;
}

.course-selection {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 10px;
    margin-top: 10px;
    max-height: 300px;
    overflow-y: auto;
    padding: 15px;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    background: #fafafa;
}

.course-checkbox {
    padding: 8px 12px;
    background: white;
    border-radius: 4px;
    border: 1px solid #ddd;
}

.course-checkbox label {
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    font-weight: normal;
}

.recipient-options {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.recipient-option {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
    border: 2px solid #ddd;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.recipient-option:has(input:checked) {
    border-color: #650000;
    background: #fff5f5;
}

.recipient-option input[type="radio"] {
    cursor: pointer;
}

.btn-send {
    background: #650000;
    color: white;
    padding: 12px 30px;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-send:hover {
    background: #800000;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.preview-box {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 20px;
    margin-top: 20px;
}

.preview-box h4 {
    color: #650000;
    margin-bottom: 15px;
}
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="notification-card">
                <h3><i class='bx bx-send'></i> Send Notification to Students</h3>
                <p class="text-muted">Create and send custom notifications to all students or specific groups</p>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class='bx bx-check-circle'></i> <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class='bx bx-error'></i> <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="notificationForm">
                    <div class="form-group">
                        <label>Notification Title *</label>
                        <input type="text" name="title" required placeholder="e.g., Seminar on Career Development" maxlength="200">
                    </div>

                    <div class="form-group">
                        <label>Message *</label>
                        <textarea name="message" required placeholder="Enter the notification message..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Send To *</label>
                        <div class="recipient-options">
                            <div class="recipient-option">
                                <input type="radio" name="recipient_type" value="all" id="all_students" checked onchange="toggleCourseSelection()">
                                <label for="all_students">All Students</label>
                            </div>
                            <div class="recipient-option">
                                <input type="radio" name="recipient_type" value="program_type" id="program_type" onchange="toggleCourseSelection()">
                                <label for="program_type">By Program Type (UG/PG)</label>
                            </div>
                            <div class="recipient-option">
                                <input type="radio" name="recipient_type" value="specific_course" id="specific_course" onchange="toggleCourseSelection()">
                                <label for="specific_course">Specific Courses</label>
                            </div>
                        </div>
                    </div>

                    <div id="program_type_selection" style="display: none;">
                        <div class="form-group">
                            <label>Select Program Type</label>
                            <div class="course-selection" style="max-height: 100px;">
                                <div class="course-checkbox">
                                    <label>
                                        <input type="checkbox" name="program_types[]" value="UG"> Undergraduate (UG)
                                    </label>
                                </div>
                                <div class="course-checkbox">
                                    <label>
                                        <input type="checkbox" name="program_types[]" value="PG"> Postgraduate (PG)
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="course_selection" style="display: none;">
                        <div class="form-group">
                            <label>Select Courses</label>
                            <button type="button" class="btn btn-sm btn-outline-primary mb-2" onclick="selectAllCourses(true)">Select All</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary mb-2" onclick="selectAllCourses(false)">Deselect All</button>
                            <div class="course-selection">
                                <?php foreach (array_merge($UG_COURSES, $PG_COURSES) as $course): ?>
                                    <div class="course-checkbox">
                                        <label>
                                            <input type="checkbox" name="courses[]" value="<?= htmlspecialchars($course) ?>">
                                            <?= htmlspecialchars($course) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="send_notification" class="btn-send">
                        <i class='bx bx-send'></i> Send Notification
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleCourseSelection() {
    const selectedType = document.querySelector('input[name="recipient_type"]:checked').value;
    document.getElementById('course_selection').style.display = selectedType === 'specific_course' ? 'block' : 'none';
    document.getElementById('program_type_selection').style.display = selectedType === 'program_type' ? 'block' : 'none';
}

function selectAllCourses(checked) {
    document.querySelectorAll('#course_selection input[type="checkbox"]').forEach(cb => {
        cb.checked = checked;
    });
}
</script>

<?php require 'footer.php'; ?>
