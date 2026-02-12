<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("config.php");
include_once __DIR__ . '/logger.php';

$users_module_admins = ['Asgar Ahmed', 'Annie Shruthi'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
   
    <script>
  (function () {
    const isSidebarOpen = localStorage.getItem("sidebarOpen") === "true";
    if (isSidebarOpen) {
      document.documentElement.classList.add("sidebar-open");
    }
  })();
</script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php
    // Get current page name without ".php"
    $fileName = basename($_SERVER['PHP_SELF'], ".php");

    // Map specific file names to cleaner titles if needed
    $customTitles = [
        "dashboard" => "Dashboard",
        "add_drive" => "Add Drive",
        "enrolled_students" => "Applications List",
        "registered_students" => "Placement Registered Students",
        "placed_students" => "On Campus Placed Students",
        "on_off_campus" => "Overall Placed Students",
        "course_specific_drive_data" => "Company Progress Tracker",
        "backup_module" => "Backup",
        "index" => "Dashboard"
    ];

    // If custom title exists, use it; else format the file name nicely
    $pageTitle = isset($customTitles[$fileName]) 
        ? $customTitles[$fileName] 
        : ucwords(str_replace("_", " ", $fileName));

    // Final full title
    $fullTitle = $pageTitle . " - Mount Carmel College Placement Cell";
?>
<title><?= htmlspecialchars($fullTitle) ?></title>

    <!-- Bootstrap 5.3.0 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <!-- Flatpickr: date picker library -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <!-- Boxicons Icon Library (for icons like bx bx-menu) -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    <!-- Your custom stylesheet for site-specific styles -->
    <link href="style.css" rel="stylesheet">

    <!-- Chart.js Data Labels Plugin: adds labels to charts -->
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

    <!-- html2canvas: capture DOM elements as images -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <!-- jsPDF (UMD build): generate PDF files from JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <!-- Bootstrap 5.3.0 JS bundle (includes Popper.js for tooltips, modals, etc.) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Google Maps JavaScript API (requires your API key) with Places library -->
    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=places"></script>

    <!-- Bootstrap Icons: official icon set for Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Chart.js: charting library for creating graphs and visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Bootstrap JS (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Font Awesome: icon library for scalable vector icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

    <!-- Or if you use Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- jQuery (required by Select2) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- XLSX.js Library (for reading/writing Excel files) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<style>

body {
  background-color: white;
  padding: 0;
  margin: 0;
  overflow-y: auto;
  overflow-x: hidden;
  position: relative;
  min-height: 100vh;
}

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    height: 100%;
    width: 75px;
    background: white;
    padding: 6px;
    z-index: 1;
    transition: all 0.5s ease;
    display: flex;
    flex-direction: column;
    z-index: 100;
}

.sidebar.open {
    width: 300px;
}

.sidebar .logo-details {
    position: sticky;
    top: 0;
    z-index: 5;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 5px;
    height: 100px; /* or min-height:100px if you prefer */
    margin-top: 10px;
    margin-bottom: 5px;
    flex-shrink: 0;
}

/* Left side: Logo gets 80% */
.sidebar .logo-details .logo_name {
    width: 85%;
    display: flex;
    align-items: center;
}

.sidebar .logo-details .logo_name img {
    max-height: 100%;
    max-width: 100%;
    height: auto;
    opacity: 0;
    transition: opacity 0.5s ease;
    align-items: right;
}

/* Show when sidebar is open */
.sidebar-open .sidebar .logo-details .logo_name img {
    opacity: 1;
}

.sidebar .logo-details #btn {
  position: absolute;
  top: 50%;
  right: 0px;
  transform: translateY(-50%);
  font-size: 22px;
  text-align: left;
  cursor: pointer;
  transition: left 0.2s ease, transform 0.2s ease;
}

.sidebar.open .logo-details #btn {
    text-align: center;
}

.sidebar i {
    color: #650000;
    height: 60px;
    min-width: 50px;
    font-size: 28px;
    text-align: center;
    line-height: 60px;
}

.sidebar .nav-list {
    height: 100%;
    flex-direction: column;
    margin-bottom: 5px
}

.sidebar li {
    position: relative;
    margin: 8px 0;
    list-style: none;
}

.sidebar input {
    font-size: 12px;
    color: #650000;
    font-weight: 400;
    outline: none;
    height: 50px;
    width: 100%;
    border: none;
    border-radius: 12px;
    transition: all 0.5s ease;
    background: white;
}

.sidebar.open input {
    padding: 0 20px 0 50px;
    width: 100%;
}

.sidebar li i {
    height: 50px;
    line-height: 50px;
    font-size: 20px;
    border-radius: 12px;
}

.sidebar li a {
    display: flex;
    height: 100%;
    width: 100%;
    border-radius: 12px;
    align-items: center;
    text-decoration: none;
    background: white;
}


.sidebar li a .links_name {
    color: #650000;
    font-size: 14px;
    font-weight: 400;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: 0.4s;
}

.sidebar.open li a .links_name {
    opacity: 1;
    pointer-events: auto;
}

/* Hover effect for the whole link */
.sidebar li a:hover {
    border: 2px solid #650000;  /* yellow border */
    border-radius: 12px;

}

/* Change icon and text color to yellow on hover */
.sidebar li a:hover .links_name,
.sidebar li a:hover i {
    color: #650000; /* yellow text and icon */
}

.sidebar li .tooltip {
    position: fixed; /* take it out of scroll container clipping */
    left: 85px; /* adjust to match collapsed sidebar width */
    background: rgba(255, 255, 255, 1); /* white background */
    color: #000; /* black text */
    padding: 6px 12px;
    border: 1px solid #650000; /* theme border */
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    pointer-events: none;
    opacity: 0;
    transform: translateY(-50%) translateX(10px); /* start slightly offset */
    transition: opacity 0.3s ease, transform 0.3s ease; /* smooth animation */
    z-index: 999999;
}

.sidebar li:hover .tooltip {
    opacity: 1;
    pointer-events: auto;
    transform: translateY(-50%) translateX(0); /* slide into place */
}

.sidebar li .tooltip.show {
    opacity: 1;
}

.sidebar.open li .tooltip {
    display: none; /* hide tooltips when sidebar is expanded */
}

.sidebar li.profile {
    position: fixed;
    left: 0;
    bottom: 5px;   /* push up a bit */
    height: 50px;   /* enough space for icon + text */
    width: 50px;
    align-items: center;
    background: #650000;
    border-radius: 12px;
    transition: all 0.5s ease;
    z-index: 2;
    flex-shrink: 0;
    margin-left: 5px;
}

.sidebar.open li.profile {
    width: 270px;
    height: 50px;

}

.sidebar li .profile-details {
    display: flex;
    align-items: center;
    flex-wrap: nowrap;
}

.sidebar li.profile i {
    color: white;
}

.sidebar li img {
    height: 45px;
    width: 45px;
    object-fit: contain;
    border-radius: 6px;
    margin-right: 10px;
}

.sidebar li.profile .name,
.sidebar li.profile .job {
    font-size: 15px;
    font-weight: 400;
    color: #fff;
    white-space: nowrap;
}

.sidebar .profile #log_out {
    position: absolute;
    top: 50%;
    right: 0;
    transform: translateY(-50%);
    background: #650000;
    width: 100%;
    height: 50px;
    line-height: 50px;
    transition: all 0.5s ease;
    color: white;
}

.sidebar.open .profile #log_out {
    background: none;
}

.sidebar .profile #log_out:hover {
    background: #862626ff;
    color: #fdc800;
    cursor: pointer;
    transform: translateY(-50%) scale(1.05); /* subtle zoom effect */
}

.main {
    position: relative;
    left: 78px; /* Default collapsed sidebar width */
    width: calc(100% - 78px);
    transition: all 0.5s ease;
    padding: 20px;
}

.sidebar.open ~ .main {
    left: 300px; /* Expanded sidebar width */
    width: calc(100% - 300px);
}

ul, ol {
    padding-left: 0 !important;
    margin-left: 0;
    margin-bottom: 0 !important;
}

.sidebar li a.active {
  background: #650000;
}

.sidebar li a.active .links_name,
.sidebar li a.active i {
  color: white;
}

.sidebar-open .sidebar {
  width: 300px;
}

.sidebar-open .sidebar .logo-details,
.sidebar-open .sidebar .logo-details .logo_name {
  opacity: 1;
}

.sidebar-open .sidebar li a .links_name {
  opacity: 1;
  pointer-events: auto;
}

.sidebar-open .sidebar li .tooltip {
  display: none;
}

.sidebar-open .main {
  left: 300px;
  width: calc(100% - 300px);
}

.sidebar-open .sidebar li .profile-details {
    opacity: 1;
}

.sidebar-open .sidebar li.profile {
    width: 277px;
}

.sidebar-open .sidebar li.profile .name,
.sidebar-open .sidebar li.profile .job {
    opacity: 1;
}

.sidebar-open .sidebar .profile #log_out {
    width: 50px;
    background: none;
}

/* Scroll container */
.sidebar-scroll-container {
    display: flex; /* side-by-side */
    height: 100%;
}

/* The nav section (full clickable width) */
.nav-wrapper {
    flex: 1; /* takes all available space except scrollbar */
    overflow-y: auto;
    overflow-x: hidden;
    height: calc(100vh - 190px);
    scrollbar-width: thin;
}


/* The scrollbar column */
.scrollbar-track {
    width: 8px; /* same as scrollbar width */
    background: transparent;
    position: relative;
    margin-left: 4px; /* space between nav and scrollbar */
}

/* Optional custom scrollbar style inside scrollbar column */
.scrollbar-track::before {
    content: '';
    position: absolute;
    top: 0;
    width: 8px;
    border-radius: 4px;
    background: #aaa;
    height: 30%; /* example thumb height */
}

/* Notification Bell Styling */
.notification-bell-wrapper {
    position: sticky;
    top: 110px;
    z-index: 10;
    padding: 8px 5px;
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 10px;
}

.notification-bell-link {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 50px;
    background: white;
    border-radius: 12px;
    transition: all 0.3s ease;
    text-decoration: none;
}

.notification-bell-link:hover {
    border: 2px solid #650000;
    transform: scale(1.05);
}

.notification-bell-link i {
    color: #650000;
    font-size: 24px;
}

.notification-bell-link:hover i {
    animation: bell-ring 0.5s ease-in-out;
}

@keyframes bell-ring {
    0%, 100% { transform: rotate(0deg); }
    10%, 30%, 50%, 70%, 90% { transform: rotate(-10deg); }
    20%, 40%, 60%, 80% { transform: rotate(10deg); }
}

.notification-badge {
    position: absolute;
    top: 5px;
    right: 5px;
    background: #dc3545;
    color: white;
    font-size: 10px;
    font-weight: bold;
    padding: 2px 5px;
    border-radius: 10px;
    min-width: 18px;
    text-align: center;
    line-height: 1.2;
}

/* Scrollbar styling */
.nav-wrapper::-webkit-scrollbar {
    width: 6px;
}
.nav-wrapper::-webkit-scrollbar-thumb {
    background-color: rgba(0,0,0,0.4);
    border-radius: 3px;
}
.nav-wrapper::-webkit-scrollbar-track {
    background: transparent;
}

/* Show scrollbar only on hover (YouTube style) */
.nav-wrapper::-webkit-scrollbar {
    opacity: 0;
    transition: opacity 0.2s;
}
.nav-wrapper:hover::-webkit-scrollbar {
    opacity: 1;
}


</style>
</head>

<body>
    <div class="sidebar">
        <div class="logo-details">
            <div class="logo_name">
                <img src="images/MCC_login_logo.png" alt="Logo">

            </div>
            <i class="bx bx-menu sidebarToggleBtn" id="btn" onclick="changeMainWidth()"></i>
        </div>

        <!-- Notification Bell Icon -->
        <?php
        // Auto-check for deadline notifications (runs on every page load)
        include_once __DIR__ . '/check_deadlines_on_load.php';

        // Fetch unread notification count
        $notification_count_query = "SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0";
        $notification_count_result = $conn->query($notification_count_query);
        $unread_notification_count = 0;
        if ($notification_count_result) {
            $unread_notification_count = $notification_count_result->fetch_assoc()['count'];
        }
        ?>
        <div class="notification-bell-wrapper">
            <a href="admin_notifications.php" class="notification-bell-link">
                <i class="bx bx-bell"></i>
                <?php if ($unread_notification_count > 0): ?>
                    <span class="notification-badge"><?php echo $unread_notification_count > 99 ? '99+' : $unread_notification_count; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <div class="sidebar-scroll-container">
        <!-- Fixed-width alignment wrapper -->
        <div class="nav-wrapper">
            <ul class="nav-list">
                <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>

            <li>
                <a href="dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="bi bi-grid"></i>
                    <span class="links_name">Dashboard</span>
                </a>
                <span class="tooltip">Dashboard</span>
            </li>

            <li>
                <a href="add_drive.php" class="<?= $currentPage === 'add_drive.php' ? 'active' : '' ?>">
                    <i class="bi bi-plus-square"></i>
                    <span class="links_name">Add Drive</span>
                </a>
                <span class="tooltip">Add Drive</span>
            </li>

            <li>
                <a href="enrolled_students.php" class="<?= $currentPage === 'enrolled_students.php' ? 'active' : '' ?>">
                    <i class="bi bi-card-list"></i>
                    <span class="links_name">Applications List</span>
                </a>
                <span class="tooltip">Applications List</span>
            </li>

            <li>
                <a href="registered_students.php" class="<?= $currentPage === 'registered_students.php' ? 'active' : '' ?>">
                    <i class="bi bi-person-vcard"></i>
                    <span class="links_name">Placement Registered Students</span>
                </a>
                <span class="tooltip">Placement Registered</span>
            </li>

            <li>
                <a href="placed_students.php" class="<?= $currentPage === 'placed_students.php' ? 'active' : '' ?>">
                    <i class="bi bi-briefcase"></i>
                    <span class="links_name">Placed Students</span>
                </a>
                <span class="tooltip">Placed Students</span>
            </li>

            <li>
                <a href="on_off_campus.php" class="<?= $currentPage === 'on_off_campus.php' ? 'active' : '' ?>">
                    <i class="bi bi-buildings"></i>
                    <span class="links_name">Offer Letter Collection</span>
                </a>
                <span class="tooltip">Offer Letter Collection</span>
            </li>

            <li>
                <a href="course_specific_drive_data.php" class="<?= $currentPage === 'course_specific_drive_data.php' ? 'active' : '' ?>">
                    <i class="bi bi-journal-bookmark"></i>
                    <span class="links_name">Company Progress Tracker</span>
                </a>
                <span class="tooltip">Progress Tracker</span>
            </li>

<li>
    <a href="old_files_storage.php" class="<?= basename($_SERVER['PHP_SELF']) === 'old_files_storage.php' ? 'active' : '' ?>">
        <i class="bi bi-archive"></i>
        <span class="links_name">Previous Years Data</span>
    </a>
    <span class="tooltip">Previous Years Data</span>
</li>


            <li>
                <a href="backup_module.php" class="<?= basename($_SERVER['PHP_SELF']) === 'backup_module.php' ? 'active' : '' ?>">
                    <i class="bi bi-database"></i>
                    <span class="links_name">Backup</span>
                </a>
                <span class="tooltip">Backup</span>
            </li>


             <li>
                <a href="generate_course_report.php" class="<?= $currentPage === "generate_course_report.php" ? 'active' : '' ?>">
                    <i class="bi bi-pie-chart"></i>
                    <span class="links_name">Generate Report</span>
                </a>
                <span class="tooltip">Report</span>
            </li> 


            <?php if (isset($_SESSION['username']) && in_array($_SESSION['username'], $users_module_admins)): ?>
<li>
    <a href="users.php" class="<?= $currentPage === 'users.php' ? 'active' : '' ?>">
        <i class="bi bi-people"></i>
        <span class="links_name">Users</span>
    </a>
    <span class="tooltip">Users</span>
</li>
<?php endif; ?>
            </ul>
        </div>

        
    </div>

            
        <!-- Welcome + Logout Section -->
        <?php if (isset($_SESSION['username'])): ?>
<li class="profile">
    <div class="profile-details" onclick="window.location='logout.php';" style="cursor:pointer; position: relative;">
        <i class="bi bi-person-badge"></i>
        <div class="name_job">
            <div class="name"><?= htmlspecialchars($_SESSION['username']) ?></div>
        </div>
        <!-- Logout icon -->
        <i class="bi bi-box-arrow-right" id="log_out"></i>
    </div>
</li>

<?php endif; ?>



    </div>

<script>

function changeMainWidth() {
    const sidebarToggleBtn = document.querySelector('.sidebarToggleBtn#btn');
    const mainContainer = document.querySelector('.main');
    
    const explandedSidebarClassName = "bx-menu-alt-right"
    
    if(sidebarToggleBtn.classList.contains(explandedSidebarClassName)) {
        mainContainer.style.width = "calc(100% - 75px)"
    } else {
        mainContainer.style.width = "calc(100% - 300px)"
    }
}

document.addEventListener("DOMContentLoaded", function () {
    const html = document.documentElement;
    const sidebar = document.querySelector(".sidebar");
    const closeBtn = document.querySelector("#btn");

    const isSidebarOpen = localStorage.getItem("sidebarOpen") === "true";
    if (isSidebarOpen) {
        html.classList.add("sidebar-open");
    }

    menuBtnChange();

    if (closeBtn) {
        closeBtn.addEventListener("click", () => {
            html.classList.toggle("sidebar-open");
            localStorage.setItem("sidebarOpen", html.classList.contains("sidebar-open"));
            menuBtnChange();
        });
    }

    function menuBtnChange() {
        if (html.classList.contains("sidebar-open")) {
            closeBtn.classList.replace("bx-menu", "bx-menu-alt-right");
        } else {
            closeBtn.classList.replace("bx-menu-alt-right", "bx-menu");
        }
    }
});

document.querySelectorAll('.nav-list li').forEach(li => {
    const tooltip = li.querySelector('.tooltip');

    li.addEventListener('mouseenter', () => {
        const rect = li.getBoundingClientRect();
        tooltip.style.top = (rect.top + rect.height / 2) + 'px';
        tooltip.classList.add('show');
    });

    li.addEventListener('mouseleave', () => {
        tooltip.classList.remove('show');
    });
});






// Preserve sidebar scroll position across page reloads
document.addEventListener("DOMContentLoaded", function () {
    const navWrapper = document.querySelector(".nav-wrapper");

    // Restore previous scroll position
    const savedScrollTop = localStorage.getItem("sidebarScrollTop");
    if (savedScrollTop) {
        navWrapper.scrollTop = parseInt(savedScrollTop, 10);
    }

    // Save current scroll position before leaving page
    navWrapper.addEventListener("scroll", function () {
        localStorage.setItem("sidebarScrollTop", navWrapper.scrollTop);
    });

    // Also store scroll position just before navigating away
    document.querySelectorAll(".nav-list a").forEach(link => {
        link.addEventListener("click", () => {
            localStorage.setItem("sidebarScrollTop", navWrapper.scrollTop);
        });
    });
});
</script>



<div class="main">
