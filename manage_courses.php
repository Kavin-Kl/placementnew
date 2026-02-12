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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_course'])) {
        // Add new course
        $course_name = trim($_POST['course_name']);
        $program_type = $_POST['program_type'];
        $school = trim($_POST['school']);
        $program_level = trim($_POST['program_level']);
        $display_order = intval($_POST['display_order']);

        $stmt = $conn->prepare("INSERT INTO courses (course_name, program_type, school, program_level, display_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $course_name, $program_type, $school, $program_level, $display_order);

        if ($stmt->execute()) {
            $success_message = "Course added successfully!";
        } else {
            $error_message = "Error adding course: " . $conn->error;
        }
    }

    elseif (isset($_POST['edit_course'])) {
        // Edit existing course
        $course_id = intval($_POST['course_id']);
        $course_name = trim($_POST['course_name']);
        $program_type = $_POST['program_type'];
        $school = trim($_POST['school']);
        $program_level = trim($_POST['program_level']);
        $display_order = intval($_POST['display_order']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $conn->prepare("UPDATE courses SET course_name = ?, program_type = ?, school = ?, program_level = ?, display_order = ?, is_active = ? WHERE course_id = ?");
        $stmt->bind_param("ssssiii", $course_name, $program_type, $school, $program_level, $display_order, $is_active, $course_id);

        if ($stmt->execute()) {
            $success_message = "Course updated successfully!";
        } else {
            $error_message = "Error updating course: " . $conn->error;
        }
    }

    elseif (isset($_POST['delete_course'])) {
        // Delete course
        $course_id = intval($_POST['course_id']);

        // Check if course is being used by any students
        $check = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE course = (SELECT course_name FROM courses WHERE course_id = ?)");
        $check->bind_param("i", $course_id);
        $check->execute();
        $result = $check->get_result()->fetch_assoc();

        if ($result['count'] > 0) {
            $error_message = "Cannot delete this course. It is currently assigned to " . $result['count'] . " student(s).";
        } else {
            $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ?");
            $stmt->bind_param("i", $course_id);

            if ($stmt->execute()) {
                $success_message = "Course deleted successfully!";
            } else {
                $error_message = "Error deleting course: " . $conn->error;
            }
        }
    }

    elseif (isset($_POST['toggle_active'])) {
        // Toggle active status
        $course_id = intval($_POST['course_id']);
        $stmt = $conn->prepare("UPDATE courses SET is_active = NOT is_active WHERE course_id = ?");
        $stmt->bind_param("i", $course_id);

        if ($stmt->execute()) {
            $success_message = "Course status updated!";
        }
    }
}

// Fetch all courses
$courses_query = "SELECT * FROM courses ORDER BY program_type, school, display_order, course_name";
$courses_result = $conn->query($courses_query);

// Get unique schools
$schools_query = "SELECT DISTINCT school FROM courses ORDER BY school";
$schools_result = $conn->query($schools_query);

require 'header.php';
?>

<style>
.course-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.course-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.course-table th,
.course-table td {
    padding: 15px 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

.course-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #650000;
}

.course-table tr:hover {
    background: #f8f9fa;
}

.badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.badge-success {
    background: #d4edda;
    color: #155724;
}

.badge-danger {
    background: #f8d7da;
    color: #721c24;
}

.badge-ug {
    background: #cfe2ff;
    color: #084298;
}

.badge-pg {
    background: #f8d7da;
    color: #842029;
}

.btn-group {
    display: inline-flex;
    gap: 8px;
    align-items: center;
    justify-content: flex-start;
    flex-wrap: nowrap;
}

.btn-group form {
    margin: 0;
    padding: 0;
    display: inline-block;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 20px;
    border: 1px solid #888;
    border-radius: 8px;
    width: 80%;
    max-width: 600px;
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: #000;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #650000;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.search-box {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    width: 300px;
    margin-bottom: 20px;
}

.filter-buttons {
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 8px 16px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 4px;
    cursor: pointer;
}

.filter-btn.active {
    background: #650000;
    color: white;
    border-color: #650000;
}

.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.btn-sm {
    white-space: nowrap;
    font-size: 13px;
    padding: 6px 12px;
    min-width: 100px;
}

.course-table tbody tr {
    transition: background-color 0.2s ease;
}

.course-table tbody tr:hover {
    background-color: #f5f5f5;
}

.course-table td:last-child {
    white-space: nowrap;
    min-width: 380px;
}

.btn-primary {
    background-color: #007bff;
    border-color: #007bff;
}

.btn-warning {
    background-color: #ffc107;
    border-color: #ffc107;
    color: #000;
}

.btn-danger {
    background-color: #dc3545;
    border-color: #dc3545;
}
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="course-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3><i class='bx bx-book'></i> Manage Courses</h3>
                    <button class="btn btn-primary" onclick="openAddModal()">
                        <i class='bx bx-plus'></i> Add New Course
                    </button>
                </div>

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

                <!-- Search and Filter -->
                <input type="text" id="searchBox" class="search-box" placeholder="Search courses..." onkeyup="filterCourses()">

                <div class="filter-buttons">
                    <button class="filter-btn active" onclick="filterByType('all')">All</button>
                    <button class="filter-btn" onclick="filterByType('UG')">UG Only</button>
                    <button class="filter-btn" onclick="filterByType('PG')">PG Only</button>
                    <button class="filter-btn" onclick="filterByStatus('active')">Active Only</button>
                    <button class="filter-btn" onclick="filterByStatus('inactive')">Inactive Only</button>
                </div>

                <!-- Statistics -->
                <?php
                $total_courses = $courses_result->num_rows;
                $ug_count = $conn->query("SELECT COUNT(*) as count FROM courses WHERE program_type = 'UG' AND is_active = 1")->fetch_assoc()['count'];
                $pg_count = $conn->query("SELECT COUNT(*) as count FROM courses WHERE program_type = 'PG' AND is_active = 1")->fetch_assoc()['count'];
                $courses_result->data_seek(0); // Reset pointer
                ?>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5>Total Courses</h5>
                                <h3><?php echo $total_courses; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5>UG Courses</h5>
                                <h3><?php echo $ug_count; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5>PG Courses</h5>
                                <h3><?php echo $pg_count; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Courses Table -->
                <div class="table-responsive">
                <table class="course-table" id="coursesTable">
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th style="width: 300px;">Course Name</th>
                            <th style="width: 80px;">Type</th>
                            <th>School</th>
                            <th style="width: 100px;">Status</th>
                            <th style="width: 80px;">Order</th>
                            <th style="width: 400px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($course = $courses_result->fetch_assoc()): ?>
                            <tr data-type="<?php echo $course['program_type']; ?>" data-status="<?php echo $course['is_active'] ? 'active' : 'inactive'; ?>">
                                <td><?php echo $course['course_id']; ?></td>
                                <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                <td><span class="badge badge-<?php echo strtolower($course['program_type']); ?>"><?php echo $course['program_type']; ?></span></td>
                                <td><?php echo htmlspecialchars($course['school']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $course['is_active'] ? 'success' : 'danger'; ?>">
                                        <?php echo $course['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo $course['display_order']; ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-primary" onclick='editCourse(<?php echo json_encode($course); ?>)' title="Edit">
                                            <i class='bx bx-edit'></i> Edit
                                        </button>
                                        <form method="POST" onsubmit="return confirm('Toggle active status?')">
                                            <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                            <button type="submit" name="toggle_active" class="btn btn-sm btn-warning" title="Toggle Status">
                                                <i class='bx bx-power-off'></i> <?php echo $course['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        <form method="POST" onsubmit="return confirm('Delete this course permanently?')">
                                            <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                            <button type="submit" name="delete_course" class="btn btn-sm btn-danger" title="Delete">
                                                <i class='bx bx-trash'></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Course Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeAddModal()">&times;</span>
        <h4>Add New Course</h4>
        <form method="POST">
            <div class="form-group">
                <label>Course Name *</label>
                <input type="text" name="course_name" required placeholder="e.g., BA-Psychology">
            </div>
            <div class="form-group">
                <label>Program Type *</label>
                <select name="program_type" required>
                    <option value="UG">UG (Undergraduate)</option>
                    <option value="PG">PG (Postgraduate)</option>
                </select>
            </div>
            <div class="form-group">
                <label>School *</label>
                <input type="text" name="school" required placeholder="e.g., SCHOOL OF HUMANITIES(UG)" list="schools">
                <datalist id="schools">
                    <?php
                    $schools_result->data_seek(0);
                    while ($school = $schools_result->fetch_assoc()):
                    ?>
                        <option value="<?php echo htmlspecialchars($school['school']); ?>">
                    <?php endwhile; ?>
                </datalist>
            </div>
            <div class="form-group">
                <label>Program Level *</label>
                <input type="text" name="program_level" required value="Undergraduate Programs" placeholder="e.g., Undergraduate Programs">
            </div>
            <div class="form-group">
                <label>Display Order</label>
                <input type="number" name="display_order" value="0" min="0">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="add_course" class="btn btn-primary">Add Course</button>
                <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Course Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditModal()">&times;</span>
        <h4>Edit Course</h4>
        <form method="POST" id="editForm">
            <input type="hidden" name="course_id" id="edit_course_id">
            <div class="form-group">
                <label>Course Name *</label>
                <input type="text" name="course_name" id="edit_course_name" required>
            </div>
            <div class="form-group">
                <label>Program Type *</label>
                <select name="program_type" id="edit_program_type" required>
                    <option value="UG">UG (Undergraduate)</option>
                    <option value="PG">PG (Postgraduate)</option>
                </select>
            </div>
            <div class="form-group">
                <label>School *</label>
                <input type="text" name="school" id="edit_school" required list="schools">
            </div>
            <div class="form-group">
                <label>Program Level *</label>
                <input type="text" name="program_level" id="edit_program_level" required>
            </div>
            <div class="form-group">
                <label>Display Order</label>
                <input type="number" name="display_order" id="edit_display_order" min="0">
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" id="edit_is_active">
                    Active
                </label>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="edit_course" class="btn btn-primary">Update Course</button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addModal').style.display = 'block';
}

function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
}

function openEditModal() {
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function editCourse(course) {
    document.getElementById('edit_course_id').value = course.course_id;
    document.getElementById('edit_course_name').value = course.course_name;
    document.getElementById('edit_program_type').value = course.program_type;
    document.getElementById('edit_school').value = course.school;
    document.getElementById('edit_program_level').value = course.program_level;
    document.getElementById('edit_display_order').value = course.display_order;
    document.getElementById('edit_is_active').checked = course.is_active == 1;
    openEditModal();
}

function filterCourses() {
    const searchTerm = document.getElementById('searchBox').value.toLowerCase();
    const table = document.getElementById('coursesTable');
    const rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        const text = row.textContent.toLowerCase();

        if (text.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
}

let currentTypeFilter = 'all';
let currentStatusFilter = 'all';

function filterByType(type) {
    currentTypeFilter = type;
    currentStatusFilter = 'all'; // Reset status filter
    applyFilters();

    // Update button states
    document.querySelectorAll('.filter-buttons .filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    // Set active button
    const buttons = document.querySelectorAll('.filter-buttons .filter-btn');
    if (type === 'all') {
        buttons[0].classList.add('active');
    } else if (type === 'UG') {
        buttons[1].classList.add('active');
    } else if (type === 'PG') {
        buttons[2].classList.add('active');
    }
}

function filterByStatus(status) {
    currentStatusFilter = status;
    currentTypeFilter = 'all'; // Reset type filter
    applyFilters();

    // Update button states
    document.querySelectorAll('.filter-buttons .filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    // Set active button
    const buttons = document.querySelectorAll('.filter-buttons .filter-btn');
    if (status === 'active') {
        buttons[3].classList.add('active');
    } else if (status === 'inactive') {
        buttons[4].classList.add('active');
    }
}

function applyFilters() {
    const table = document.getElementById('coursesTable');
    const rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        const rowType = row.getAttribute('data-type');
        const rowStatus = row.getAttribute('data-status');

        let showRow = true;

        if (currentTypeFilter !== 'all' && rowType !== currentTypeFilter) {
            showRow = false;
        }

        if (currentStatusFilter !== 'all' && rowStatus !== currentStatusFilter) {
            showRow = false;
        }

        row.style.display = showRow ? '' : 'none';
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php require 'footer.php'; ?>
