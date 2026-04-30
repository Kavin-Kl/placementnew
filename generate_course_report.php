<?php 
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}
error_reporting(E_ALL);
ini_set("display_errors", 1);

include("config.php");

include("course_groups_dynamic.php");

// === Academic Year filter (from header.php year selector) ===
$ay_value = $_SESSION['selected_academic_year'] ?? null;
$ay_year_int = null;
if ($ay_value && preg_match('/(\d{4})\s*-\s*(\d{4})/', $ay_value, $m)) {
    $ay_year_int = (int)$m[2];
}
$ay_value_esc = $ay_value ? $conn->real_escape_string($ay_value) : '';
// SQL fragments — empty string when no AY selected.
$ay_filter_students        = $ay_year_int ? " AND year_of_passing = $ay_year_int " : '';
$ay_filter_students_s      = $ay_year_int ? " AND s.year_of_passing = $ay_year_int " : '';
$ay_filter_drives_d        = $ay_value ? " AND d.academic_year = '$ay_value_esc' " : '';
$course = $_GET['course'] ?? '';
$offer_type_filter = $_GET['offer_type'] ?? 'FULLTIME'; // Default to Full-Time; only Internship/Full-Time supported
if (!in_array($offer_type_filter, ['FULLTIME', 'INTERNSHIP'], true)) {
    $offer_type_filter = 'FULLTIME';
}
$is_internship_report = ($offer_type_filter === 'INTERNSHIP');
$compensation_column = $is_internship_report ? 'stipend' : 'ctc';
$compensation_label  = $is_internship_report ? 'Stipend' : 'CTC';

// Normalize underscores to commas for matching with DB values
$normalized_course = str_replace('_', ',', $course);

// === SELECTED COURSES ===
$selectedCourses = [];

// Check for special "ALL" options first
if ($course === 'ALL') {
    $selectedCourses = array_merge($UG_COURSES, $PG_COURSES);
} elseif ($course === 'ALL_UG') {
    $selectedCourses = $UG_COURSES;
} elseif ($course === 'ALL_PG') {
    $selectedCourses = $PG_COURSES;
}
// Check if it's an individual course from UG_COURSES or PG_COURSES
elseif (in_array($course, $UG_COURSES) || in_array($course, $PG_COURSES)) {
    $selectedCourses = [$course];
}
// Check if it's a school/department group (only if not an individual course)
elseif (isset($ug_courses_grouped[$course])) {
    foreach ($ug_courses_grouped[$course] as $level => $courses) {
        $selectedCourses = array_merge($selectedCourses, $courses);
    }
} elseif (isset($pg_courses_grouped[$course])) {
    foreach ($pg_courses_grouped[$course] as $level => $courses) {
        $selectedCourses = array_merge($selectedCourses, $courses);
    }
}
// If none of the above, treat it as a single course
elseif (!empty($course)) {
    $selectedCourses = [$course];
}

// Fallback if still empty
if (empty($selectedCourses)) {
    $selectedCourses = ['DummyPlaceholderCourse'];
}


// === INITIALIZE VARIABLES ===
$students_registered = 0;
$students_placed = 0;
$students_not_placed = 0;
$students_defaulted = 0;
$placement_percentage = 0;

$ug_registered = 0;
$ug_placed = 0;
$ug_not_placed = 0;

$pg_registered = 0;
$pg_placed = 0;
$pg_not_placed = 0;

$average_ctc = 0;
$median_ctc = 0;
$highest_ctc = 0;

$companies_visited = 0;
$companies_hired = 0;

$companies_status_breakdown = [
  'Ongoing' => 0,
  'On Hold' => 0,
  'Called Off' => 0,
  'Yet to Start' => 0,
  'Process Complete' => 0,
  'No Applicants' => 0
];

$companies = [];
// ✅ Count UG Off-Campus Placed - EXCLUDING VANTAGE
$ug_offcampus_placed = 0;
if (!empty($UG_COURSES)) {
    // Define cleaned UG course list first
    $cleaned_ug = array_map(function($c){
        return strtolower(
            str_replace([' ', '.', '-', '_', ',', '(', ')', '–'], '', str_ireplace('&', 'and', trim($c)))
        );
    }, $UG_COURSES);

    $placeholders = implode(',', array_fill(0, count($cleaned_ug), '?'));
    $types = str_repeat('s', count($cleaned_ug));


    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM students
        WHERE LOWER(TRIM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(course, '&', 'and'),'–',''),'-',''),'(',''),')',''),' ',''),'.',''),'_',''),',','')))
        IN ($placeholders)
        AND LOWER(Offcampus_selection) = 'placed'
        AND (vantage_participant != 'yes' OR vantage_participant IS NULL)
        $ay_filter_students
    ");
    if ($stmt) {
        $stmt->bind_param($types, ...$cleaned_ug);
        $stmt->execute();
        $ug_offcampus_placed = (int)$stmt->get_result()->fetch_assoc()['total'];
    } else {
        error_log("Prepare failed for UG off-campus query: " . $conn->error);
        $ug_offcampus_placed = 0;
    }
}

// ✅ Count PG Off-Campus Placed - EXCLUDING VANTAGE
$pg_offcampus_placed = 0;
if (!empty($PG_COURSES)) {
    // Define cleaned PG course list first
    $cleaned_pg = array_map(function($c){
        return strtolower(
            str_replace([' ', '.', '-', '_', ',', '(', ')', '–'], '', str_ireplace('&', 'and', trim($c)))
        );
    }, $PG_COURSES);

    $placeholders = implode(',', array_fill(0, count($cleaned_pg), '?'));
    $types = str_repeat('s', count($cleaned_pg));


    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM students
        WHERE LOWER(TRIM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(course, '&', 'and'),'–',''),'-',''),'(',''),')',''),' ',''),'.',''),'_',''),',','')))
        IN ($placeholders)
        AND LOWER(Offcampus_selection) = 'placed'
        AND (vantage_participant != 'yes' OR vantage_participant IS NULL)
        $ay_filter_students
    ");
    if ($stmt) {
        $stmt->bind_param($types, ...$cleaned_pg);
        $stmt->execute();
        $pg_offcampus_placed = (int)$stmt->get_result()->fetch_assoc()['total'];
    } else {
        error_log("Prepare failed for PG off-campus query: " . $conn->error);
        $pg_offcampus_placed = 0;
    }
}

// === DATABASE QUERIES ===
$placeholders = implode(',', array_fill(0, count($selectedCourses), '?'));
$types = str_repeat('s', count($selectedCourses));
$cleaned_courses = array_map(function($c) {
  return strtolower(
    str_replace([' ', '.', '-', '_', ',', '(', ')', '–'], '', str_ireplace('&', 'and', trim($c)))
  );
}, $selectedCourses);

// Students registered - if ALL selected, count ALL students (EXCLUDING VANTAGE)
if ($course === 'ALL') {
    $stmt = $conn->query("SELECT COUNT(*) as total FROM students WHERE (vantage_participant != 'yes' OR vantage_participant IS NULL) $ay_filter_students");
    $students_registered = $stmt->fetch_assoc()['total'];
} else {
    $sql = "
      SELECT COUNT(*) as total
      FROM students
      WHERE LOWER(
        TRIM(
          REPLACE(
            REPLACE(
              REPLACE(
                REPLACE(
                  REPLACE(
                    REPLACE(
                      REPLACE(
                        REPLACE(
                          REPLACE(course, '&', 'and'),
                          '–', ''
                        ),
                        '-', ''
                      ),
                      '(', ''
                    ),
                    ')', ''
                  ),
                  ' ', ''
                ),
                '.', ''
              ),
              '_', ''
            ),
            ',', ''
          )
        )
      ) IN ($placeholders)
      AND (vantage_participant != 'yes' OR vantage_participant IS NULL)
      $ay_filter_students
    ";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$cleaned_courses);
        $stmt->execute();
        $students_registered = $stmt->get_result()->fetch_assoc()['total'];
    } else {
        error_log("Prepare failed for students_registered query: " . $conn->error);
        $students_registered = 0;
    }
}

// Students placed
// Students placed (using same normalization as students_registered) - EXCLUDING VANTAGE
// Build offer type filter
$offer_type_condition = "";
if ($offer_type_filter === 'FULLTIME') {
    $offer_type_condition = " AND (dr.offer_type IN ('FTE', 'Internship + PPO', 'Internship+PPO', 'Internship + PPO (Final Year)', 'Apprenticeship (Part Time)') OR dr.offer_type IS NULL)";
} elseif ($offer_type_filter === 'INTERNSHIP') {
    $offer_type_condition = " AND dr.offer_type = 'Internship'";
}

$stmt = $conn->prepare("
  SELECT COUNT(DISTINCT a.upid) as total
  FROM applications a
  INNER JOIN students s ON a.student_id = s.student_id
  LEFT JOIN drive_roles dr ON a.role_id = dr.role_id
  WHERE LOWER(
    TRIM(
      REPLACE(
        REPLACE(
          REPLACE(
            REPLACE(
              REPLACE(
                REPLACE(
                  REPLACE(
                    REPLACE(
                      REPLACE(a.course, '&', 'and'),
                      '–', ''
                    ),
                    '-', ''
                  ),
                  '(', ''
                ),
                ')', ''
              ),
              ' ', ''
            ),
            '.', ''
          ),
          '_', ''
        ),
        ',', ''
      )
    )
  ) IN ($placeholders)
  AND LOWER(a.status) = 'placed'
  AND (s.vantage_participant != 'yes' OR s.vantage_participant IS NULL)
  $offer_type_condition
  $ay_filter_students_s
");
if ($stmt) {
    $stmt->bind_param($types, ...$cleaned_courses);
    $stmt->execute();
    $students_placed = (int)$stmt->get_result()->fetch_assoc()['total'];
} else {
    error_log("Prepare failed for students_placed query: " . $conn->error);
    $students_placed = 0;
}


// Students defaulted - EXCLUDING VANTAGE
$stmt = $conn->prepare("
  SELECT COUNT(DISTINCT a.upid) as total
  FROM applications a
  INNER JOIN students s ON a.student_id = s.student_id
  WHERE LOWER(
    TRIM(
      REPLACE(
        REPLACE(
          REPLACE(
            REPLACE(
              REPLACE(
                REPLACE(
                  REPLACE(
                    REPLACE(
                      REPLACE(a.course, '&', 'and'),
                      '–', ''
                    ),
                    '-', ''
                  ),
                  '(', ''
                ),
                ')', ''
              ),
              ' ', ''
            ),
            '.', ''
          ),
          '_', ''
        ),
        ',', ''
      )
    )
  ) IN ($placeholders)
  AND LOWER(a.status) = 'blocked'
  AND (s.vantage_participant != 'yes' OR s.vantage_participant IS NULL)
  $ay_filter_students_s
");
if ($stmt) {
    $stmt->bind_param($types, ...$cleaned_courses);
    $stmt->execute();
    $students_defaulted = (int)$stmt->get_result()->fetch_assoc()['total'];
} else {
    error_log("Prepare failed for students_defaulted query: " . $conn->error);
    $students_defaulted = 0;
}


$students_not_placed = $students_registered - $students_placed - $students_defaulted;
$placement_percentage = $students_registered > 0 ? round(($students_placed / $students_registered) * 100) : 0;
// ✅ Count of students placed off-campus - EXCLUDING VANTAGE
$stmt = $conn->prepare("
  SELECT COUNT(*) AS total
  FROM students
  WHERE LOWER(TRIM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(course, '&', 'and'),'–',''),'-',''),'(',''),')',''),' ',''),'.',''),'_',''),',','')))
  IN ($placeholders)
  AND LOWER(Offcampus_selection) = 'placed'
  AND (vantage_participant != 'yes' OR vantage_participant IS NULL)
  $ay_filter_students
");
if ($stmt) {
    $stmt->bind_param($types, ...$cleaned_courses);
    $stmt->execute();
    $offcampus_placed = (int)$stmt->get_result()->fetch_assoc()['total'];
} else {
    error_log("Prepare failed for offcampus_placed query: " . $conn->error);
    $offcampus_placed = 0;
}

// CTC
// ✅ Fetch CTC values from placed_students.ctc instead of drive_roles.ctc
$ctc_values = [];

// Build offer type filter for CTC query
$ctc_offer_type_condition = "";
if ($offer_type_filter === 'FULLTIME') {
    $ctc_offer_type_condition = " AND (offer_type IN ('FTE', 'Internship + PPO', 'Internship+PPO', 'Internship + PPO (Final Year)', 'Apprenticeship (Part Time)') OR offer_type IS NULL)";
} elseif ($offer_type_filter === 'INTERNSHIP') {
    $ctc_offer_type_condition = " AND offer_type = 'Internship'";
}

$query = "
    SELECT $compensation_column AS comp_value
    FROM placed_students
    WHERE LOWER(
      TRIM(
        REPLACE(
          REPLACE(
            REPLACE(
              REPLACE(
                REPLACE(
                  REPLACE(
                    REPLACE(
                      REPLACE(
                        REPLACE(course, '&', 'and'),
                        '–', ''
                      ),
                      '-', ''
                    ),
                    '(', ''
                  ),
                  ')', ''
                ),
                ' ', ''
              ),
              '.', ''
            ),
            '_', ''
          ),
          ',', ''
        )
      )
    ) IN ($placeholders)
      AND $compensation_column IS NOT NULL
      AND $compensation_column != ''
      $ctc_offer_type_condition
      " . ($ay_year_int ? " AND student_id IN (SELECT student_id FROM students WHERE year_of_passing = $ay_year_int)" : "") . "
";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param($types, ...$cleaned_courses);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        // Remove any non-numeric text like 'LPA', 'lpa', etc.
        $clean_ctc = preg_replace('/[^0-9.]/', '', $row['comp_value']);
        if ($clean_ctc !== '') {
            $ctc_values[] = (float)$clean_ctc;
        }
    }
} else {
    error_log("Prepare failed for CTC values query: " . $conn->error);
    $ctc_values = [];
}
sort($ctc_values);

$average_ctc = $ctc_values ? round(array_sum($ctc_values) / count($ctc_values), 2) : 0;
$median_ctc = $ctc_values ? round($ctc_values[floor(count($ctc_values) / 2)], 2) : 0;
$highest_ctc = $ctc_values ? max($ctc_values) : 0;

sort($ctc_values);
$average_ctc = $ctc_values ? round(array_sum($ctc_values) / count($ctc_values), 2) : 0;
$median_ctc = $ctc_values ? round($ctc_values[floor(count($ctc_values) / 2)], 2) : 0;
$highest_ctc = $ctc_values ? max($ctc_values) : 0;

// Companies recruited
// Build offer type filter for companies query
$companies_offer_type_condition = "";
if ($offer_type_filter === 'FULLTIME') {
    $companies_offer_type_condition = " AND (dr.offer_type IN ('FTE', 'Internship + PPO', 'Internship+PPO', 'Internship + PPO (Final Year)', 'Apprenticeship (Part Time)') OR dr.offer_type IS NULL)";
} elseif ($offer_type_filter === 'INTERNSHIP') {
    $companies_offer_type_condition = " AND dr.offer_type = 'Internship'";
}

$stmt = $conn->prepare("SELECT DISTINCT d.company_name
                        FROM applications a
                        JOIN drives d ON a.drive_id = d.drive_id
                        LEFT JOIN drive_roles dr ON a.role_id = dr.role_id
                        WHERE LOWER(
                          TRIM(
                            REPLACE(
                              REPLACE(
                                REPLACE(
                                  REPLACE(
                                    REPLACE(
                                      REPLACE(
                                        REPLACE(
                                          REPLACE(
                                            REPLACE(a.course, '&', 'and'),
                                            '–', ''
                                          ),
                                          '-', ''
                                        ),
                                        '(', ''
                                      ),
                                      ')', ''
                                    ),
                                    ' ', ''
                                  ),
                                  '.', ''
                                ),
                                '_', ''
                              ),
                              ',', ''
                            )
                          )
                        ) IN ($placeholders)
                        AND LOWER(a.status) = 'placed'
                        $companies_offer_type_condition
                        $ay_filter_drives_d");
if ($stmt) {
    $stmt->bind_param($types, ...$cleaned_courses);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $companies[] = strtoupper($row['company_name']);
    }
} else {
    error_log("Prepare failed for companies recruited query: " . $conn->error);
    $companies = [];
}

// Companies visited & hired
$orLikes = $params = [];
foreach ($selectedCourses as $sc) {
    // Skip DummyPlaceholderCourse
    if ($sc !== 'DummyPlaceholderCourse') {
        $orLikes[] = "dd.eligible_courses LIKE ?";
        $params[] = '%"'.$sc.'"%';
    }
}
$where = !empty($orLikes) ? '(' . implode(' OR ', $orLikes) . ')' : '1=1';

// If filtering for a specific course (not ALL/ALL_UG/ALL_PG), exclude drives with broad course selections
$excludeBroad = '';
if ($course !== 'ALL' && $course !== 'ALL_UG' && $course !== 'ALL_PG' && $course !== '') {
    $excludeBroad = " AND dd.eligible_courses NOT LIKE '%\"ALL\"%'
                      AND dd.eligible_courses NOT LIKE '%\"All UG\"%'
                      AND dd.eligible_courses NOT LIKE '%\"All PG\"%'
                      AND dd.eligible_courses NOT LIKE '%\"all ug courses\"%'
                      AND dd.eligible_courses NOT LIKE '%\"all pg courses\"%'
                      AND dd.eligible_courses NOT LIKE '%\"All UG Courses\"%'
                      AND dd.eligible_courses NOT LIKE '%\"All PG Courses\"%'
                      AND JSON_LENGTH(dd.eligible_courses) <= 40";
}

$sql = "
    SELECT COUNT(DISTINCT d.company_name) AS total
    FROM drives d
    JOIN drive_data dd ON d.company_name = dd.company_name AND d.drive_no = dd.drive_no
    WHERE $where $excludeBroad $ay_filter_drives_d
";
$stmt = $conn->prepare($sql);
if ($stmt && !empty($params)) {
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $companies_visited = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
} else if ($stmt) {
    // No params, just execute
    $stmt->execute();
    $companies_visited = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
} else {
    error_log("Prepare failed for companies_visited query: " . $conn->error);
    $companies_visited = 0;
}

// Pull the list of distinct companies visited (for the same course filter) so the report
// can show the names alongside the recruited list.
$companies_visited_list = [];
$listSql = "
    SELECT DISTINCT d.company_name
    FROM drives d
    JOIN drive_data dd ON d.company_name = dd.company_name AND d.drive_no = dd.drive_no
    WHERE $where $excludeBroad $ay_filter_drives_d
    ORDER BY d.company_name ASC
";
$stmtList = $conn->prepare($listSql);
if ($stmtList) {
    if (!empty($params)) {
        $stmtList->bind_param(str_repeat('s', count($params)), ...$params);
    }
    $stmtList->execute();
    $resList = $stmtList->get_result();
    while ($r = $resList->fetch_assoc()) {
        $companies_visited_list[] = strtoupper($r['company_name']);
    }
}



// Build offer type filter for companies hired query
$hired_offer_type_condition = "";
if ($offer_type_filter === 'FULLTIME') {
    $hired_offer_type_condition = " AND (dr.offer_type IN ('FTE', 'Internship + PPO', 'Internship+PPO', 'Internship + PPO (Final Year)', 'Apprenticeship (Part Time)') OR dr.offer_type IS NULL)";
} elseif ($offer_type_filter === 'INTERNSHIP') {
    $hired_offer_type_condition = " AND dr.offer_type = 'Internship'";
}

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT d.company_name) AS hired
    FROM applications a
    JOIN drives d ON a.drive_id = d.drive_id
    LEFT JOIN drive_roles dr ON a.role_id = dr.role_id
    WHERE LOWER(
      TRIM(
        REPLACE(
          REPLACE(
            REPLACE(
              REPLACE(
                REPLACE(
                  REPLACE(
                    REPLACE(
                      REPLACE(
                        REPLACE(a.course, '&', 'and'),
                        '–', ''
                      ),
                      '-', ''
                    ),
                    '(', ''
                  ),
                  ')', ''
                ),
                ' ', ''
              ),
              '.', ''
            ),
            '_', ''
          ),
          ',', ''
        )
      )
    ) IN ($placeholders)
      AND LOWER(a.status) = 'placed'
      $hired_offer_type_condition
      $ay_filter_drives_d
");
if ($stmt) {
    $stmt->bind_param($types, ...$cleaned_courses);
    $stmt->execute();
    $companies_hired = $stmt->get_result()->fetch_assoc()['hired'] ?? 0;
} else {
    error_log("Prepare failed for companies_hired query: " . $conn->error);
    $companies_hired = 0;
}


// UG/PG split
$ug_registered = $ug_placed = $pg_registered = $pg_placed = 0;
if (!empty($UG_COURSES)) {
  $cleaned_ug = array_map(function($c){
  return strtolower(
    str_replace([' ', '.', '-', '_', ',', '(', ')', '–'], '', str_ireplace('&', 'and', trim($c)))
  );
}, $UG_COURSES);

    $placeholders = implode(',', array_fill(0, count($cleaned_ug), '?'));
    $types = str_repeat('s', count($cleaned_ug));
   $stmt = $conn->prepare("
  SELECT COUNT(*) as total
  FROM students
  WHERE LOWER(
    TRIM(
      REPLACE(
        REPLACE(
          REPLACE(
            REPLACE(
              REPLACE(
                REPLACE(
                  REPLACE(
                    REPLACE(
                      REPLACE(course, '&', 'and'),
                      '–', ''
                    ),
                    '-', ''
                  ),
                  '(', ''
                ),
                ')', ''
              ),
              ' ', ''
            ),
            '.', ''
          ),
          '_', ''
        ),
        ',', ''
      )
    )
  ) IN ($placeholders)
  AND (vantage_participant != 'yes' OR vantage_participant IS NULL)
  $ay_filter_students
");

    if ($stmt) {
        $stmt->bind_param($types, ...$cleaned_ug);
        $stmt->execute();
        $ug_registered = $stmt->get_result()->fetch_assoc()['total'];
    } else {
        error_log("Prepare failed for UG registered query: " . $conn->error);
        $ug_registered = 0;
    }
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT a.upid) as total
FROM applications a
INNER JOIN students s ON a.student_id = s.student_id
WHERE LOWER(
  TRIM(
    REPLACE(
      REPLACE(
        REPLACE(
          REPLACE(
            REPLACE(
              REPLACE(
                REPLACE(
                  REPLACE(
                    REPLACE(a.course, '&', 'and'),
                    '–', ''
                  ),
                  '-', ''
                ),
                '(', ''
              ),
              ')', ''
            ),
            ' ', ''
          ),
          '.', ''
        ),
        '_', ''
      ),
      ',', ''
    )
  )
) IN ($placeholders)
AND LOWER(a.status) = 'placed'
AND (s.vantage_participant != 'yes' OR s.vantage_participant IS NULL)
$ay_filter_students_s
");
    if ($stmt) {
        $stmt->bind_param($types, ...$cleaned_ug);
        $stmt->execute();
        $ug_placed = $stmt->get_result()->fetch_assoc()['total'];
    } else {
        error_log("Prepare failed for UG placed query: " . $conn->error);
        $ug_placed = 0;
    }
}
$ug_not_placed = $ug_registered - $ug_placed;

if (!empty($PG_COURSES)) {
   $cleaned_pg = array_map(function($c){
  return strtolower(
    str_replace([' ', '.', '-', '_', ',', '(', ')', '–'], '', str_ireplace('&', 'and', trim($c)))
  );
}, $PG_COURSES);

    $placeholders = implode(',', array_fill(0, count($cleaned_pg), '?'));
    $types = str_repeat('s', count($cleaned_pg));
   $stmt = $conn->prepare("
  SELECT COUNT(*) as total
  FROM students
  WHERE LOWER(
    TRIM(
      REPLACE(
        REPLACE(
          REPLACE(
            REPLACE(
              REPLACE(
                REPLACE(
                  REPLACE(
                    REPLACE(
                      REPLACE(course, '&', 'and'),
                      '–', ''
                    ),
                    '-', ''
                  ),
                  '(', ''
                ),
                ')', ''
              ),
              ' ', ''
            ),
            '.', ''
          ),
          '_', ''
        ),
        ',', ''
      )
    )
  ) IN ($placeholders)
  AND (vantage_participant != 'yes' OR vantage_participant IS NULL)
  $ay_filter_students
");

    if ($stmt) {
        $stmt->bind_param($types, ...$cleaned_pg);
        $stmt->execute();
        $pg_registered = $stmt->get_result()->fetch_assoc()['total'];
    } else {
        error_log("Prepare failed for PG registered query: " . $conn->error);
        $pg_registered = 0;
    }
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT a.upid) as total
FROM applications a
INNER JOIN students s ON a.student_id = s.student_id
WHERE LOWER(
  TRIM(
    REPLACE(
      REPLACE(
        REPLACE(
          REPLACE(
            REPLACE(
              REPLACE(
                REPLACE(
                  REPLACE(
                    REPLACE(a.course, '&', 'and'),
                    '–', ''
                  ),
                  '-', ''
                ),
                '(', ''
              ),
              ')', ''
            ),
            ' ', ''
          ),
          '.', ''
        ),
        '_', ''
      ),
      ',', ''
    )
  )
) IN ($placeholders)
AND LOWER(a.status) = 'placed'
AND (s.vantage_participant != 'yes' OR s.vantage_participant IS NULL)
$ay_filter_students_s
");
    if ($stmt) {
        $stmt->bind_param($types, ...$cleaned_pg);
        $stmt->execute();
        $pg_placed = $stmt->get_result()->fetch_assoc()['total'];
    } else {
        error_log("Prepare failed for PG placed query: " . $conn->error);
        $pg_placed = 0;
    }
}
$pg_not_placed = $pg_registered - $pg_placed;






// === EXPORT TO EXCEL ===
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Build report type title
    $report_type = '';
    if ($offer_type_filter === 'FULLTIME') {
        $report_type = ' - FULL-TIME';
    } elseif ($offer_type_filter === 'INTERNSHIP') {
        $report_type = ' - INTERNSHIP';
    }

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=placement_report_" . str_replace(' ', '_', $course) . "_" . $offer_type_filter . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "<table border='1'>";
    echo "<tr><th colspan='2' style='background:#650000;color:#fff;'>PLACEMENT REPORT" . $report_type . " - " . htmlspecialchars($course) . "</th></tr>";

    echo "<tr><td>Students Registered</td><td>$students_registered</td></tr>";
    echo "<tr><td>Students Placed On-Campus</td><td>$students_placed</td></tr>";
      echo "<tr><td>Students Placed Off-Campus</td><td>$offcampus_placed</td></tr>";
    //echo "<tr><td>Students Not Placed</td><td>$students_not_placed</td></tr>";
    echo "<tr><td>Students Defaulted</td><td>$students_defaulted</td></tr>";
    echo "<tr><td>Placement Percentage</td><td>$placement_percentage%</td></tr>";
    //echo "<tr><td>Students Placed Off-Campus</td><td>$offcampus_placed</td></tr>";


   // Show UG/PG split only for overall reports
// Show UG/PG split only for ALL report
if ($course === 'ALL') {
    echo "<tr><th colspan='2'>UG / PG Breakdown</th></tr>";
    echo "<tr><td>UG Registered</td><td>$ug_registered</td></tr>";
    echo "<tr><td>UG Placed</td><td>$ug_placed</td></tr>";
    echo "<tr><td>UG Not Placed</td><td>$ug_not_placed</td></tr>";
    echo "<tr><td>PG Registered</td><td>$pg_registered</td></tr>";
    echo "<tr><td>PG Placed</td><td>$pg_placed</td></tr>";
    echo "<tr><td>PG Not Placed</td><td>$pg_not_placed</td></tr>";
}


    echo "<tr><th colspan='2'>Companies Recruited</th></tr>";
    if (!empty($companies)) {
        foreach ($companies as $c) {
            echo "<tr><td colspan='2'>" . htmlspecialchars($c) . "</td></tr>";
        }
    } else {
        echo "<tr><td colspan='2'>None</td></tr>";
    }

    echo "<tr><th colspan='2'>Package Details</th></tr>";
    echo "<tr><td>Average $compensation_label</td><td>$average_ctc</td></tr>";
    echo "<tr><td>Median $compensation_label</td><td>$median_ctc</td></tr>";
    echo "<tr><td>Highest $compensation_label</td><td>$highest_ctc</td></tr>";

    echo "<tr><th colspan='2'>Companies Overview</th></tr>";
    echo "<tr><td>Total Companies Visited</td><td>$companies_visited</td></tr>";
    echo "<tr><td>Companies Hired</td><td>$companies_hired</td></tr>";

    echo "</table>";
    exit;
}


include ("header.php");
?>




<!DOCTYPE html>
<html>
<head>
    <title>Placement Report - <?= htmlspecialchars($course) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
   <style>
    .table ul {
  margin: 0;
  padding-left: 15px;
  line-height: 0.1; /* tighter line spacing */
  
}

.table ul li {
  margin: 1px 0; /* less space between items */
  font-size: 12px;
   /* smaller font */
}

    body {
        font-size: 13px !important; /* smaller overall font */
        background-color: #f8f9fa;
    }

    h3, h5 {
        font-size: 18px;
        margin-bottom: 10px;
    }

    .table {
        font-size: 12px; /* smaller table text */
        width: 100%; /* slightly smaller table width */
        margin: 0 auto 15px auto; /* less spacing */
    }

    .table thead th {
        background-color: #650000;
        color: #fff;
        font-size: 12px;
        padding: 6px;
    }

    .table td, .table th {
        padding: 6px 8px; /* compact cells */
    }

    .table-striped tbody tr:nth-of-type(odd) {
        background-color: #f9f9f9;
    }

    #report-content {
        max-width: 800px; /* reduce container width */
        margin: 0 auto;
        padding: 15px;
    }

    .dropdown, .text-end {
        font-size: 13px;
    }

    ul {
        margin: 0;
        padding-left: 15px;
    }

    .import-button {
        font-size: 12px;
        padding: 5px 10px;
        border-radius: 5px;
    }
    /* ===== Multi-level Dropdown Fix ===== */
.dropdown-submenu {
  position: relative;
}

.dropdown-submenu > .dropdown-menu {
  top: 0;
  left: 100%;
  margin-top: -1px;
  position: absolute;
  display: none; /* initially hidden */
}

.dropdown-submenu .dropdown-menu.show {
  display: block; /* show when .show class is toggled */
}
.company-list {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  padding: 5px;
}

.company-item {
  background: #ffffff;
  border: 1px solid #650000;
  border-radius: 6px;
  padding: 4px 10px;
  font-size: 12px;
  font-weight: 500;
  color: #650000;
  white-space: nowrap;
  transition: 0.2s;
}




</style>

</head>

<body class="bg-light">
<h3 class="text-center mb-4">PLACEMENT REPORT</h3>

<!-- Offer Type Filter Buttons -->
<div class="text-center mb-3">
    <div class="btn-group" role="group" aria-label="Offer Type Filter">
        <a href="?course=<?= urlencode($course) ?>&offer_type=INTERNSHIP"
           class="btn btn-sm <?= $offer_type_filter === 'INTERNSHIP' ? 'btn-primary' : 'btn-outline-primary' ?>">
            Internship Report (Stipend)
        </a>
        <a href="?course=<?= urlencode($course) ?>&offer_type=FULLTIME"
           class="btn btn-sm <?= $offer_type_filter === 'FULLTIME' ? 'btn-primary' : 'btn-outline-primary' ?>">
            Full-Time Report (CTC)
        </a>
    </div>
</div>

<?php if ($course): ?>
<div class="text-end mb-3">
 <button onclick="exportPDF()" class="import-button"style ="margin-top:20px; margin-right:20px;">  <i class="bi bi-folder-symlink-fill me-1"></i> Download Report as PDF</button>
<button onclick="window.location.href='<?= $_SERVER['PHP_SELF'] ?>?course=<?= urlencode($course) ?>&offer_type=<?= urlencode($offer_type_filter) ?>&export=excel'"
          class="import-button"
          style="margin-top:20px; margin-right:20px;">
    <i class="bi bi-file-earmark-excel-fill me-1"></i> Download Report as Excel
  </button>
</div>
<?php endif; ?>



<div class="dropdown mb-4 text-center" style="margin-right:500px;">
  <button class="btn btn-primary dropdown-toggle" type="button" id="courseDropdown" data-bs-toggle="dropdown" aria-expanded="false">
    <?= $course ? htmlspecialchars($course) : "Select Course" ?>
  </button>
  <ul class="dropdown-menu" aria-labelledby="courseDropdown">
    <li><a class="dropdown-item" href="?course=ALL">All UG & PG</a></li>
    <li><a class="dropdown-item" href="?course=ALL_UG">All UG</a></li>
    <li><a class="dropdown-item" href="?course=ALL_PG">All PG</a></li>
    <li><hr class="dropdown-divider"></li>
    <?php foreach ($ug_courses_grouped as $dept => $levels): ?>
      <li class="dropdown-submenu">
  <a class="dropdown-item dropdown-toggle" href="?course=<?= urlencode($dept) ?>"><?= htmlspecialchars($dept) ?></a>

        <ul class="dropdown-menu">
          <?php foreach ($levels as $program => $courses): ?>
            <?php foreach ($courses as $c): ?>
              <li><a class="dropdown-item" href="?course=<?= urlencode($c) ?>"><?= htmlspecialchars($c) ?></a></li>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </ul>
      </li>
    <?php endforeach; ?>
    <?php foreach ($pg_courses_grouped as $dept => $levels): ?>
      <li class="dropdown-submenu">
        <a class="dropdown-item dropdown-toggle" href="?course=<?= urlencode($dept) ?>"><?= htmlspecialchars($dept) ?> </a>
        <ul class="dropdown-menu">
          <?php foreach ($levels as $program => $courses): ?>
            <?php foreach ($courses as $c): ?>
              <li><a class="dropdown-item" href="?course=<?= urlencode($c) ?>"><?= htmlspecialchars($c) ?></a></li>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </ul>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<div id="report-content" class="container bg-white p-4 rounded shadow-sm mb-4">

    <h5 class="text-center mb-4"><?= htmlspecialchars($course) ?> -
    <?php
        if ($offer_type_filter === 'FULLTIME') {
            echo 'FULL-TIME PLACEMENT STATISTICS';
        } elseif ($offer_type_filter === 'INTERNSHIP') {
            echo 'INTERNSHIP PLACEMENT STATISTICS';
        } else {
            echo 'OVERALL STATISTICS';
        }
    ?>
    </h5>

    <table class="table table-bordered table-striped">
        <thead><tr><th>Metric</th><th>Value</th></tr></thead>
        <tbody>
            <tr><td>Students Registered</td><td><?= $students_registered ?></td></tr>
            <tr><td>Students Placed On-Campus</td><td><?= $students_placed ?></td></tr>
            <tr><td>Students Placed Off-Campus</td><td><?= $offcampus_placed ?></td></tr>
           
            <tr><td>Students Defaulted</td><td><?= $students_defaulted ?></td></tr>
            <tr><td>Placement Percentage</td><td><?= $placement_percentage ?>%</td></tr>
           
        </tbody>
    </table>

<?php if ($course === 'ALL'): ?>
    <table class="table table-bordered table-striped">
        <thead><tr><th>Category</th><th>Registered</th><th>Placed On-Campus</th><th>Placed Off-Campus</th></tr></thead>
        <tbody>
      <tr><td>UG</td><td><?= $ug_registered ?></td><td><?= $ug_placed ?></td><td><?= $ug_offcampus_placed ?></td></tr>
<tr><td>PG</td><td><?= $pg_registered ?></td><td><?= $pg_placed ?></td><td><?= $pg_offcampus_placed ?></td></tr>

        </tbody>
    </table>
<?php endif; ?>



   <table class="table table-bordered table-striped">
    <thead><tr><th>Companies Recruited</th></tr></thead>
    <tbody>
        <tr>
            <td >
                <?php if (!empty($companies)): ?>
                    <div class="company-list">
                        <?php foreach ($companies as $c): ?>
                            <span class="company-item"><?= htmlspecialchars($c) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    None
                <?php endif; ?>
            </td>
        </tr>
    </tbody>
</table>

   <table class="table table-bordered table-striped">
    <thead><tr><th>Companies Visited</th></tr></thead>
    <tbody>
        <tr>
            <td>
                <?php if (!empty($companies_visited_list)): ?>
                    <div class="company-list">
                        <?php foreach ($companies_visited_list as $c): ?>
                            <span class="company-item"><?= htmlspecialchars($c) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    None
                <?php endif; ?>
            </td>
        </tr>
    </tbody>
</table>


    <table class="table table-bordered table-striped">
        <thead><tr><th>Package Type</th><th>Value</th></tr></thead>
        <tbody>
            <tr><td>Average <?= $compensation_label ?></td><td><?= $average_ctc ?></td></tr>
            <tr><td>Median <?= $compensation_label ?></td><td><?= $median_ctc ?></td></tr>
            <tr><td>Highest <?= $compensation_label ?></td><td><?= $highest_ctc ?></td></tr>
        </tbody>
    </table>

    <table class="table table-bordered table-striped">
        <thead><tr><th>Metric</th><th>Value</th></tr></thead>
        <tbody>
            <tr><td>Total Companies Visited</td><td><?= $companies_visited ?></td></tr>
            <tr><td>Companies Hired</td><td><?= $companies_hired ?></td></tr>
        </tbody>
    </table>

    <!-- <?php
// Fetch company status breakdown (reuse query logic)
// $status_query = $conn->query("
//     SELECT 
//         status, 
//         COUNT(*) AS count 
//     FROM drives 
//     GROUP BY status
// ");
// if ($status_query && $status_query->num_rows > 0) {
//     while ($row = $status_query->fetch_assoc()) {
//         $companies_status_breakdown[$row['status']] = $row['count'];
//     }
// }
?> -->

<!-- ✅ Companies Status Breakdown Table -->


</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.dropdown-submenu > a').forEach(function(el) {
  el.addEventListener('click', function(e) {
    const submenu = el.nextElementSibling;

    if (submenu && submenu.classList.contains('dropdown-menu')) {
      e.preventDefault();
      e.stopPropagation();

      const alreadyOpen = submenu.classList.contains('show');

      // Close all other open submenus
      document.querySelectorAll('.dropdown-submenu .dropdown-menu').forEach(function(other) {
        if (other !== submenu) other.classList.remove('show');
      });

      // Toggle this submenu
      submenu.classList.toggle('show');

      // 🔹 If submenu was already open → now go to the school report
      if (alreadyOpen) {
        const link = el.getAttribute('href');
        if (link && link.startsWith('?course=')) {
          window.location.href = link;
        }
      }
    } else {
      // For subcourse links — normal navigation
      window.location.href = el.getAttribute('href');
    }
  });
});

// 🔹 Close submenus when clicking anywhere else
window.addEventListener('click', function() {
  document.querySelectorAll('.dropdown-submenu .dropdown-menu').forEach(function(submenu) {
    submenu.classList.remove('show');
  });
});



</script>
<script>
  //export button
async function exportPDF() {
  const { jsPDF } = window.jspdf;
  const element = document.getElementById('report-content');
  if (!element) {
    alert("No report-content found!");
    return;
  }

  const canvas = await html2canvas(element, { scale: 2 });
  const imgData = canvas.toDataURL('image/png');

  const pdf = new jsPDF('p', 'mm', 'a4');
  const pageWidth = pdf.internal.pageSize.getWidth();
  const pdfWidth = pageWidth - 20;
  const imgProps = pdf.getImageProperties(imgData);
  const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;

  pdf.addImage(imgData, 'PNG', 10, 10, pdfWidth, pdfHeight);
  pdf.save('placement_report.pdf');
}
</script>
</body>
</html>
