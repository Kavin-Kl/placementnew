<?php
// Configure session for ngrok compatibility
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', '0'); // Allow HTTP for local testing
    session_start();
}

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit;
}

include("config.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <script>
  (function () {
    const isSidebarOpen = localStorage.getItem("studentSidebarOpen") === "true";
    if (isSidebarOpen) {
      document.documentElement.classList.add("sidebar-open");
    }
  })();
  </script>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php
    $fileName = basename($_SERVER['PHP_SELF'], ".php");

    $customTitles = [
        "student_dashboard" => "Dashboard",
        "student_profile" => "My Profile",
        "student_drives" => "Available Opportunities",
        "student_applications" => "My Applications",
        "student_progress" => "Progress Tracker",
        "student_notifications" => "Notifications"
    ];

    $pageTitle = isset($customTitles[$fileName])
        ? $customTitles[$fileName]
        : ucwords(str_replace("_", " ", $fileName));

    $fullTitle = $pageTitle . " - Student Portal - Mount Carmel College";
  ?>
  <title><?= htmlspecialchars($fullTitle) ?></title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <style>
body {
  background-color: #f8f9fa;
  padding: 0;
  margin: 0;
  overflow-y: auto;
  overflow-x: hidden;
  position: relative;
  min-height: 100vh;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    height: 100%;
    width: 75px;
    background: linear-gradient(180deg, #581729 0%, #7a1f38 100%);
    padding: 6px;
    z-index: 100;
    transition: all 0.5s ease;
    display: flex;
    flex-direction: column;
    box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
}

.sidebar.open {
    width: 280px;
}

.sidebar .logo-details {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px 10px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    margin-bottom: 20px;
}

.sidebar .logo-details i {
    font-size: 28px;
    color: white;
    cursor: pointer;
}

.sidebar .logo-details .logo_name {
    color: white;
    font-size: 18px;
    font-weight: 600;
    opacity: 0;
    transition: opacity 0.3s ease;
    white-space: nowrap;
}

.sidebar.open .logo_name {
    opacity: 1;
}

.sidebar .nav-list {
    list-style: none;
    padding: 0;
    margin: 0;
    flex: 1;
    overflow-y: auto;
}

.sidebar .nav-list li {
    position: relative;
    margin: 8px 0;
}

.sidebar .nav-list li a {
    display: flex;
    align-items: center;
    padding: 12px 10px;
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.sidebar .nav-list li a:hover {
    background: rgba(255, 255, 255, 0.15);
    color: white;
}

.sidebar .nav-list li a.active {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    font-weight: 600;
}

.sidebar .nav-list li a i {
    min-width: 40px;
    font-size: 20px;
    text-align: center;
}

.sidebar .nav-list li a .links_name {
    opacity: 0;
    transition: opacity 0.3s ease;
    white-space: nowrap;
}

.sidebar.open .nav-list li a .links_name {
    opacity: 1;
}

.sidebar .profile {
    position: relative;
    padding: 12px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    margin-top: auto;
}

.sidebar .profile-details {
    display: flex;
    align-items: center;
}

.sidebar .profile-details i {
    font-size: 28px;
    color: white;
    min-width: 40px;
}

.sidebar .profile-details .name_job {
    opacity: 0;
    transition: opacity 0.3s ease;
    color: white;
}

.sidebar.open .profile-details .name_job {
    opacity: 1;
}

.sidebar .profile-details .name {
    font-size: 14px;
    font-weight: 600;
}

.sidebar .profile-details .job {
    font-size: 12px;
    opacity: 0.8;
}

.sidebar .profile i.bx-log-out {
    position: absolute;
    right: 10px;
    font-size: 22px;
    cursor: pointer;
    opacity: 1;
    transition: opacity 0.3s ease;
}

.home-section {
    position: relative;
    margin-left: 75px;
    transition: all 0.5s ease;
    padding: 20px;
    min-height: 100vh;
}

html.sidebar-open .home-section {
    margin-left: 280px;
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: 0;
    background: #ff4444;
    color: white;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 10px;
    font-weight: bold;
}

@media (max-width: 768px) {
    .sidebar {
        width: 0;
        padding: 0;
    }

    .sidebar.open {
        width: 280px;
        padding: 6px;
    }

    .home-section {
        margin-left: 0;
        padding: 15px;
    }

    html.sidebar-open .home-section {
        margin-left: 0;
    }

    .sidebar .logo-details {
        padding: 10px;
    }

    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 99;
    }

    .sidebar.open ~ .sidebar-overlay {
        display: block;
    }

    /* Mobile typography */
    h1, h2 {
        font-size: 24px !important;
    }

    h3 {
        font-size: 20px !important;
    }

    h4 {
        font-size: 18px !important;
    }

    h5 {
        font-size: 16px !important;
    }

    p, span, div {
        font-size: 14px !important;
    }

    /* Mobile cards */
    .card {
        margin-bottom: 15px;
    }

    .card-body {
        padding: 15px !important;
    }

    /* Mobile buttons */
    .btn {
        padding: 10px 15px !important;
        font-size: 14px !important;
        width: 100%;
        margin-bottom: 10px;
    }

    .btn-sm {
        padding: 8px 12px !important;
        font-size: 13px !important;
    }

    /* Mobile tables */
    .table-responsive {
        font-size: 12px !important;
    }

    .table {
        font-size: 12px !important;
    }

    .table th, .table td {
        padding: 8px 4px !important;
        white-space: nowrap;
    }

    /* Mobile forms */
    input, select, textarea {
        font-size: 16px !important; /* Prevents zoom on iOS */
        padding: 12px !important;
    }

    /* Mobile stat cards */
    .stat-card {
        flex-direction: column;
        text-align: center;
        padding: 15px !important;
    }

    .stat-icon {
        margin-bottom: 10px;
    }

    /* Mobile timeline */
    .timeline-item {
        padding-left: 30px !important;
    }

    .timeline-marker {
        width: 30px !important;
        height: 30px !important;
        font-size: 16px !important;
    }

    .timeline-header {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 8px;
    }

    .timeline-header h5 {
        font-size: 16px !important;
    }

    /* Mobile spacing */
    .row {
        margin: 0 !important;
    }

    .col, [class*="col-"] {
        padding-left: 8px !important;
        padding-right: 8px !important;
    }

    /* Mobile drive cards */
    .card-header h4 {
        font-size: 18px !important;
    }

    .badge {
        font-size: 11px !important;
        padding: 4px 8px !important;
    }

    /* Mobile alert boxes */
    .alert {
        padding: 10px !important;
        font-size: 13px !important;
    }

    /* Touch-friendly clickable areas */
    a, button, .btn {
        min-height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    /* Mobile navigation improvements */
    .nav-list li a {
        padding: 15px 10px !important;
    }

    /* Hide desktop-only elements */
    .d-none-mobile {
        display: none !important;
    }

    /* Stack elements vertically on mobile */
    .d-flex {
        flex-direction: column !important;
        gap: 10px;
    }

    .justify-content-between {
        justify-content: flex-start !important;
    }

    /* Mobile-optimized containers */
    .container-fluid {
        padding-left: 10px !important;
        padding-right: 10px !important;
    }

    /* Progress tracker mobile adjustments */
    .timeline-item:not(:last-child):before {
        left: 15px !important;
    }

    .timeline-body p {
        font-size: 13px !important;
    }

    /* Mobile dashboard stats */
    .stat-number {
        font-size: 32px !important;
    }

    .stat-label {
        font-size: 12px !important;
    }

    /* Improve mobile scrolling */
    .table-responsive {
        -webkit-overflow-scrolling: touch;
    }

    /* Mobile dropdown improvements */
    select {
        background-size: 12px;
        padding-right: 30px !important;
    }

    /* Hide long text on mobile */
    .text-truncate-mobile {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Mobile padding adjustments */
    .mb-4 {
        margin-bottom: 15px !important;
    }

    .mt-4 {
        margin-top: 15px !important;
    }

    .py-5 {
        padding-top: 20px !important;
        padding-bottom: 20px !important;
    }

    /* Mobile notification improvements */
    .notification-badge {
        font-size: 9px !important;
        padding: 2px 5px !important;
    }

    /* Improve mobile search/filter */
    .form-group {
        margin-bottom: 15px !important;
    }

    /* Mobile modal improvements */
    .modal-dialog {
        margin: 10px !important;
    }

    .modal-content {
        border-radius: 8px !important;
    }

    /* Better mobile breakpoints for grid */
    .row.g-3 {
        gap: 10px !important;
    }

    /* Mobile empty state */
    .empty-state i {
        font-size: 60px !important;
    }

    /* Horizontal scroll for wide content */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
}

/* Extra small devices (phones < 576px) */
@media (max-width: 576px) {
    h1, h2 {
        font-size: 20px !important;
    }

    .home-section {
        padding: 10px;
    }

    .card-body {
        padding: 12px !important;
    }

    .btn {
        padding: 8px 12px !important;
        font-size: 13px !important;
    }

    /* Stack all columns on extra small screens */
    [class*="col-"] {
        flex: 0 0 100%;
        max-width: 100%;
    }

    /* Reduce spacing further */
    .mb-4, .my-4 {
        margin-bottom: 10px !important;
    }

    .mt-4 {
        margin-top: 10px !important;
    }

    /* Timeline adjustments for very small screens */
    .timeline-content {
        padding: 12px !important;
    }

    .timeline-marker {
        width: 25px !important;
        height: 25px !important;
        font-size: 14px !important;
    }

    .timeline-item {
        padding-left: 25px !important;
    }

    .timeline-item:not(:last-child):before {
        left: 12px !important;
    }

    /* Very small buttons */
    .btn-sm {
        padding: 6px 10px !important;
        font-size: 12px !important;
    }

    /* Stat cards on very small screens */
    .stat-number {
        font-size: 28px !important;
    }
}
  </style>
</head>
<body>

<div class="sidebar" id="studentSidebar">
  <div class="logo-details">
    <i class='bx bx-menu' id="btn"></i>
    <span class="logo_name">Student Portal</span>
  </div>
  <ul class="nav-list">
    <li>
      <a href="student_dashboard.php" class="<?= ($fileName == 'student_dashboard') ? 'active' : '' ?>">
        <i class='bx bx-grid-alt'></i>
        <span class="links_name">Dashboard</span>
      </a>
    </li>
    <li>
      <a href="student_drives.php" class="<?= ($fileName == 'student_drives') ? 'active' : '' ?>">
        <i class='bx bx-briefcase'></i>
        <span class="links_name">Available Opportunities</span>
      </a>
    </li>
    <li>
      <a href="student_applications.php" class="<?= ($fileName == 'student_applications') ? 'active' : '' ?>">
        <i class='bx bx-list-ul'></i>
        <span class="links_name">My Applications</span>
      </a>
    </li>
    <li>
      <a href="student_progress.php" class="<?= ($fileName == 'student_progress') ? 'active' : '' ?>">
        <i class='bx bx-line-chart'></i>
        <span class="links_name">Progress Tracker</span>
      </a>
    </li>
    <li>
      <a href="student_profile.php" class="<?= ($fileName == 'student_profile') ? 'active' : '' ?>">
        <i class='bx bx-user'></i>
        <span class="links_name">My Profile</span>
      </a>
    </li>
    <li>
      <a href="student_notifications.php" class="<?= ($fileName == 'student_notifications') ? 'active' : '' ?>">
        <i class='bx bx-bell'></i>
        <span class="links_name">Notifications</span>
        <?php
        // Get unread notifications count
        $student_id = $_SESSION['student_id'];
        $notif_stmt = $conn->prepare("SELECT COUNT(*) as unread FROM student_notifications WHERE student_id = ? AND is_read = 0");
        $notif_stmt->bind_param("i", $student_id);
        $notif_stmt->execute();
        $notif_result = $notif_stmt->get_result();
        $unread = $notif_result->fetch_assoc()['unread'];
        if ($unread > 0):
        ?>
          <span class="notification-badge"><?= $unread ?></span>
        <?php endif; ?>
      </a>
    </li>
  </ul>
  <div class="profile">
    <div class="profile-details">
      <i class='bx bx-user-circle'></i>
      <div class="name_job">
        <div class="name"><?= htmlspecialchars($_SESSION['student_name']) ?></div>
        <div class="job">Student</div>
      </div>
    </div>
    <i class='bx bx-log-out' id="log_out" onclick="logout()"></i>
  </div>
</div>

<div class="sidebar-overlay" onclick="closeSidebar()"></div>

<script>
let sidebar = document.querySelector(".sidebar");
let sidebarBtn = document.querySelector("#btn");

function updateSidebarState() {
  if (sidebar.classList.contains("open")) {
    document.documentElement.classList.add("sidebar-open");
    localStorage.setItem("studentSidebarOpen", "true");
  } else {
    document.documentElement.classList.remove("sidebar-open");
    localStorage.setItem("studentSidebarOpen", "false");
  }
}

if (localStorage.getItem("studentSidebarOpen") === "true") {
  sidebar.classList.add("open");
}

sidebarBtn.addEventListener("click", () => {
  sidebar.classList.toggle("open");
  updateSidebarState();
});

function closeSidebar() {
  sidebar.classList.remove("open");
  updateSidebarState();
}

function logout() {
  if (confirm("Are you sure you want to logout?")) {
    window.location.href = "student_logout.php";
  }
}
</script>
