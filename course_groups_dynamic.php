<?php
/**
 * Dynamic Course Groups Loader
 * This file loads courses from database if available, otherwise uses hardcoded fallback
 * Version: 2.0 - Database-driven
 */

// Initialize arrays
$ug_courses_grouped = [];
$pg_courses_grouped = [];
$UG_COURSES = [];
$PG_COURSES = [];
$UG_GROUPED_COURSES = [];
$PG_GROUPED_COURSES = [];

// Try to load courses from database
$load_from_database = false;

if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    // Check if courses table exists
    $table_check = @$conn->query("SHOW TABLES LIKE 'courses'");

    if ($table_check && $table_check->num_rows > 0) {
        $load_from_database = true;

        // Load UG courses from database
        $ug_query = "SELECT * FROM courses WHERE program_type = 'UG' AND is_active = 1 ORDER BY school, display_order, course_name";
        $ug_result = @$conn->query($ug_query);

        if ($ug_result) {
            while ($course = $ug_result->fetch_assoc()) {
                $school = $course['school'];
                $program_level = $course['program_level'];
                $course_name = $course['course_name'];

                if (!isset($ug_courses_grouped[$school])) {
                    $ug_courses_grouped[$school] = [];
                }

                if (!isset($ug_courses_grouped[$school][$program_level])) {
                    $ug_courses_grouped[$school][$program_level] = [];
                }

                $ug_courses_grouped[$school][$program_level][] = $course_name;
            }
        }

        // Load PG courses from database
        $pg_query = "SELECT * FROM courses WHERE program_type = 'PG' AND is_active = 1 ORDER BY school, display_order, course_name";
        $pg_result = @$conn->query($pg_query);

        if ($pg_result) {
            while ($course = $pg_result->fetch_assoc()) {
                $school = $course['school'];
                $program_level = $course['program_level'];
                $course_name = $course['course_name'];

                if (!isset($pg_courses_grouped[$school])) {
                    $pg_courses_grouped[$school] = [];
                }

                if (!isset($pg_courses_grouped[$school][$program_level])) {
                    $pg_courses_grouped[$school][$program_level] = [];
                }

                $pg_courses_grouped[$school][$program_level][] = $course_name;
            }
        }
    }
}

// Fallback to hardcoded values if database loading failed or table doesn't exist
if (empty($ug_courses_grouped) || empty($pg_courses_grouped)) {
    $load_from_database = false;

    // Include the hardcoded backup
    if (file_exists(__DIR__ . '/course_groups_backup.php')) {
        include __DIR__ . '/course_groups_backup.php';
    } else {
        // Last resort hardcoded values (abbreviated version for emergency)
        $ug_courses_grouped = [
          "SCHOOL OF HUMANITIES(UG)" => [
            "Undergraduate Programs" => [
              "BA-Communicative English_Psychology",
              "BA-History_Political Science",
              "BA-Psychology",
              "BA-Economics",
              "BA-Journalism & Mass Communication"
            ]
          ],
          "SCHOOL OF MANAGEMENT(UG)" => [
            "Undergraduate Programs" => [
              "BBA-Regular",
              "BBA-Business Analytics"
            ]
          ],
          "SCHOOL OF COMMERCE(UG)" => [
            "Undergraduate Programs" => [
              "BCom-General",
              "BCom-Professional"
            ]
          ],
          "SCHOOL OF NATURAL AND APPLIED SCIENCES(UG)" => [
            "Undergraduate Programs" => [
              "BSc-Computer Science_Mathematics",
              "Bachelor of Computer Applications",
              "BSc-Data Science"
            ]
          ]
        ];

        $pg_courses_grouped = [
          "SCHOOL OF HUMANITIES(PG)" => [
            "Postgraduate Programs" => [
              "MA-Economics",
              "MA-English"
            ]
          ],
          "SCHOOL OF MANAGEMENT(PG)" => [
            "Postgraduate Programs" => [
              "Master of Business Administration"
            ]
          ],
          "SCHOOL OF COMMERCE(PG)" => [
            "Postgraduate Programs" => [
              "MCom-General"
            ]
          ],
          "SCHOOL OF NATURAL AND APPLIED SCIENCES(PG)" => [
            "Postgraduate Programs" => [
              "MSc-Computer Science (Data Science Specialization)",
              "Master of Computer Applications"
            ]
          ]
        ];
    }
}

// Generate flat arrays from grouped arrays
foreach ($ug_courses_grouped as $school => $levels) {
    foreach ($levels as $programs) {
        $UG_COURSES = array_merge($UG_COURSES, $programs);
    }
}

foreach ($pg_courses_grouped as $school => $levels) {
    foreach ($levels as $programs) {
        $PG_COURSES = array_merge($PG_COURSES, $programs);
    }
}

// Generate labeled grouped arrays
foreach ($ug_courses_grouped as $school => $levels) {
    foreach ($levels as $programName => $courses) {
        $UG_GROUPED_COURSES[$school . ' - ' . $programName] = $courses;
    }
}

foreach ($pg_courses_grouped as $school => $levels) {
    foreach ($levels as $programName => $courses) {
        $PG_GROUPED_COURSES[$school . ' - ' . $programName] = $courses;
    }
}

// Debug comment (remove in production)
// Source: $load_from_database ? "Database" : "Hardcoded Fallback"
?>
