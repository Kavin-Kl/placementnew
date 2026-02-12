<?php
$ug_courses_grouped = [
  "SCHOOL OF HUMANITIES(UG)" => [
    "Undergraduate Programs" => [
      "BA-Communicative English_Psychology",
      "BA-History_Political Science",
      "BA-History_Travel Tourism",
      "BA-Political Science_Economics",
      "BA-Political Science_Sociology",
      "BA-Psychology_Economics",
      "BA-Psychology_English Literature",
      "BA-Psychology_Journalism",
      "BA-Psychology_Sociology",
      "BA-Travel & Tourism_Journalism",
      "BVoc-Hospitality and Tourism",
      "BA-Communication Studies",
      "BA-Economics",
      "BA-Journalism & Mass Communication",
      "BA-Psychology"
    ]
  ],

  "SCHOOL OF MANAGEMENT(UG)" => [
    "Undergraduate Programs" => [
      "BBA-Branding & Advertising",
      "BBA-Business Analytics",
      "BBA-Regular"
    ]
  ],

  "SCHOOL OF COMMERCE(UG)" => [
    "Undergraduate Programs" => [
      "BCom-Business Process Services",
      "BCom-Corporate Finance",
      "BCom-General",
      "BCom-Industry Integrated",
      "BCom-International Accounting and Finance",
      "BCom-Professional",
      "BCom-Strategic Finance",
      "BCom-Tourism and Travel Management"
    ]
  ],

  "SCHOOL OF NATURAL AND APPLIED SCIENCES(UG)" => [
    "Undergraduate Programs" => [
      "BSc-Biochemistry",
      "BSc-Botany_Microbiology",
      "BSc-Botany_Zoology",
      "BSc-Chemistry_Biotechnology",
      "BSc-Chemistry_Microbiology",
      "BSc-COMPOSITE HOME SCIENCE",
      "BSc-Computer Science_Mathematics",
      "BSc-Economics_Statistics",
      "BSc-Environmental Science & Sustainability_Life Sciences",
      "BSc-Mathematics_Physics",
      "BSc-Microbiology_Zoology",
      "BSc-Nutrition & Dietetics_Human Development",
      "BSc-Zoology_Biotechnology",
      "BSc-Biotechnology",
      "BSc-Data Science",
      "BSc-Fashion and Apparel Design",
      "BSc-Food Science & Nutrition",
      "BSc-Interior Design & Management",
      "Bachelor of Computer Applications"
    ]
  ]
];

$pg_courses_grouped = [
  "SCHOOL OF HUMANITIES(PG)" => [
    "Postgraduate Programs" => [
      "MA-Economics",
      "MA-English",
      "MA-Public Policy"
    ]
  ],

  "SCHOOL OF MANAGEMENT(PG)" => [
    "Postgraduate Programs" => [
      "PG Diploma in Business Applications",
      "PG Diploma in Business Intelligence and Analytics",
      "Master of Business Administration",
      "PG Diploma in Management Analytics"
    ]
  ],

  "SCHOOL OF COMMERCE(PG)" => [
    "Postgraduate Programs" => [
      "MCom-Financial Analysis",
      "MCom-General",
      "MCom-International Business",
      "One Year Masters Degree In Commerce"
    ]
  ],

  "SCHOOL OF NATURAL AND APPLIED SCIENCES(PG)" => [
    "Postgraduate Programs" => [
      "MSc-Biochemistry",
      "MSc-Biotechnology",
      "MSc-Botany",
      "MSc-Chemistry",
      "MSc-Computer Science (Data Science Specialization)",
      "MSc-Electronics",
      "MSc-Food Science & Nutrition",
      "MSc-Life Science",
      "MSc-Mathematics",
      "MSc-Psychology",
      "MSc-Human Development",
      "Master of Computer Applications"
    ]
  ]
];


$UG_COURSES = [];
foreach ($ug_courses_grouped as $school => $levels) {
    foreach ($levels as $programs) {
        $UG_COURSES = array_merge($UG_COURSES, $programs);
    }
}

$PG_COURSES = [];
foreach ($pg_courses_grouped as $school => $levels) {
    foreach ($levels as $programs) {
        $PG_COURSES = array_merge($PG_COURSES, $programs);
    }
}

$UG_GROUPED_COURSES = [];
foreach ($ug_courses_grouped as $school => $levels) {
    foreach ($levels as $programName => $courses) {
        $UG_GROUPED_COURSES[$school . ' - ' . $programName] = $courses;
    }
}

$PG_GROUPED_COURSES = [];
foreach ($pg_courses_grouped as $school => $levels) {
    foreach ($levels as $programName => $courses) {
        $PG_GROUPED_COURSES[$school . ' - ' . $programName] = $courses;
    }
}
?>