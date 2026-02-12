# Placement Cell Management System - Complete Documentation

**Version:** 2.0
**Last Updated:** January 2026
**Institution:** Mount Carmel College

---

## Table of Contents

1. [System Overview](#system-overview)
2. [Getting Started](#getting-started)
3. [Admin Features](#admin-features)
4. [Student Features](#student-features)
5. [Database Structure](#database-structure)
6. [Technical Details](#technical-details)
7. [Troubleshooting](#troubleshooting)
8. [API Reference](#api-reference)

---

## System Overview

### What is This System?

The Placement Cell Management System is a comprehensive web application designed to manage the entire placement process for colleges. It handles:

- Student registration and profile management
- Drive creation and management
- Application tracking
- Round-wise interview results
- Notifications and communication
- Reports and analytics

### Key Capabilities

- **Dual Portal System**: Separate interfaces for admins and students
- **Mobile-Friendly**: Fully responsive design for all devices
- **Real-time Updates**: Students see live status changes
- **Secure Registration**: UPID-based verification system
- **Round Tracking**: Complete interview round management
- **Automated Notifications**: Custom notifications to student groups

---

## Getting Started

### System Requirements

- **Server**: Apache (XAMPP/WAMP)
- **Database**: MySQL 8.0+ (Port 3307)
- **PHP**: Version 8.0 or higher
- **Browser**: Modern browser (Chrome, Firefox, Safari, Edge)

### Installation

1. **Database Setup**:
   ```sql
   -- Database already exists: admin_placement_db
   -- Tables are automatically created
   ```

2. **Configuration** (`config.php`):
   ```php
   $servername = "localhost";
   $username = "root";
   $password = "";
   $dbname = "admin_placement_db";
   $port = 3307;
   ```

3. **Access URLs**:
   - Admin Portal: `http://localhost/placementcell/index.php`
   - Student Portal: `http://localhost/placementcell/student_login.php`

### Default Login Credentials

**Admin:**
- Username: [Set in admin_users table]
- Password: [Set in admin_users table]

**Student:**
- Students must register using UPID provided by admin

---

## Admin Features

### 1. Dashboard

**Location:** Admin Menu → Dashboard

**Features:**
- Overview of total students, drives, applications
- Placement statistics
- Recent activity
- Quick action buttons

**Widgets:**
- Total Registered Students
- Active Drives Count
- Pending Applications
- Placement Percentage

---

### 2. Drive Management

#### 2.1 Add Drive

**Location:** Admin Menu → Add Drive

**Steps:**
1. Click "Add Drive" in sidebar
2. Fill in basic information:
   - Company Name (required)
   - Drive Number (auto-generated)
   - Open Date & Time
   - Close Date & Time
   - Form Link (unique identifier)

3. Add Extra Details:
   - Vacancies
   - Duration
   - Stipend (for internships)
   - Work Mode (Remote/Hybrid/On-site)
   - Office Address
   - Other Details

4. Upload Job Description (optional):
   - Paste JD link URL

5. Add Roles:
   - Designation Name
   - Offer Type (Full-time/Internship)
   - CTC or Stipend
   - Minimum Percentage Required
   - Eligible Courses (select from list)

6. Customize Form Fields:
   - Select which fields to show in application form
   - Add custom fields if needed

7. Click "Create Drive"

**Important Notes:**
- Form Link must be unique
- At least one role must be added
- Students can only apply between open and close dates
- Drives automatically become inactive after close date

---

#### 2.2 Applications List

**Location:** Admin Menu → Applications List

**Purpose:** View all student applications organized by company

**Features:**

**Tabs:**
- Current Drives (ongoing)
- Finished Drives (completed)

**For Each Company:**
- Company name and drive number
- Total applications count
- Filter by status (All/Placed/Not Placed/Blocked)
- Export to Excel

**Student Details Shown:**
- UPID, Name, Email, Phone
- Course, Program, Percentage
- Registration Number
- Application Date
- Current Status

**Actions:**
- Change Status (Placed/Not Placed/Blocked/Rejected)
- Add Comments
- View Student Data (form responses)
- Export Selected Fields

**Status Meanings:**
- **Placed**: Student got the job/internship
- **Applied/Pending**: Application under review
- **Rejected**: Not selected
- **Blocked**: Student blocked from applying
- **Not Placed**: Didn't get the offer

---

#### 2.3 Manage Round Results

**Location:** Admin Menu → Manage Round Results

**Purpose:** Track students through multiple interview rounds

**How It Works:**

**Step 1: Select Drive**
- Left sidebar shows all drives with applications
- Click on any drive to manage its rounds

**Step 2: View Applications**
- See all students who applied
- Each student card shows:
  - Name, UPID, Course
  - Designation applied for
  - Current status

**Step 3: Add Rounds**
1. Click "Add Round" button on student card
2. Fill in round details:
   - **Round Name**: e.g., "Group Discussion", "Technical Round 1"
   - **Round Type**: GD/Aptitude/Technical/HR/Case Study/Other
   - **Scheduled Date**: When the round will happen (optional)
3. Click "Add Round"

**Step 4: Update Results**
1. Click "Result" button on any round
2. Select result:
   - **Pending**: Round not yet evaluated
   - **Shortlisted**: Student passed this round
   - **Rejected**: Student didn't clear this round
   - **Not Conducted**: Round was cancelled
3. Add comments (optional feedback)
4. Click "Update Result"

**Visual Indicators:**
- 🟢 Green border = Shortlisted
- 🔴 Red border = Rejected
- 🟡 Yellow border = Pending
- ⚪ Gray border = Not Conducted

**Use Cases:**

**Example 1: TCS Drive**
```
Student: John Doe
Round 1: Aptitude Test → Shortlisted ✅
Round 2: Technical Interview → Shortlisted ✅
Round 3: HR Interview → Pending ⏳
```

**Example 2: Infosys Drive**
```
Student: Jane Smith
Round 1: Group Discussion → Shortlisted ✅
Round 2: Coding Test → Rejected ❌
(No further rounds needed)
```

**Benefits:**
- Students see real-time progress
- Complete audit trail (who updated, when)
- Transparent communication
- Reduces "when will results come" queries

---

### 3. Student Management

#### 3.1 Registered Students

**Location:** Admin Menu → Placement Registered Students

**Purpose:** Import and manage all placement-registered students

**Features:**

**Import Students:**
1. Click "Download Template" for Excel format
2. Fill in student details:
   - UPID (Placement ID) - must be unique
   - Student Name
   - Email (college email)
   - Phone Number
   - Course
   - Program Type (UG/PG)
   - Program
   - Percentage
   - Year of Passing
   - Batch

3. Upload filled Excel file
4. System validates and imports data
5. Students can now register on portal using their UPID

**Manage Students:**
- View all registered students
- Search by name, UPID, email
- Edit student details
- Activate/Deactivate accounts
- Export student list

**Important:**
- Students MUST be imported by admin before they can register
- UPID is the key identifier - must be unique
- Email should be official college email

---

#### 3.2 Student Progress Lookup

**Location:** Admin Menu → Student Progress Lookup

**Purpose:** View complete placement journey of any student

**How to Use:**
1. Enter Student ID, UPID, or Email in search box
2. Click "Search"

**What You See:**

**Student Information Card:**
- Basic details (name, UPID, email, phone)
- Academic details (course, percentage)
- Program information

**Statistics:**
- Total Applications
- Placed Count
- In Progress Count
- Not Selected Count

**Application Timeline:**
- Chronological list of all applications
- For each application:
  - Company name and role
  - Application date
  - CTC/Stipend details
  - Current status
  - **Round-wise Progress** (NEW!)
    - All rounds with results
    - Who marked the result
    - When it was updated
    - Comments/feedback
  - Admin comments

**Use Cases:**
- Quick student history check
- Placement counseling sessions
- Performance review
- Responding to student queries

---

### 4. Notifications

#### 4.1 Send Notification

**Location:** Admin Menu → Send Notification

**Purpose:** Send custom notifications to students

**Steps:**
1. Enter notification title (e.g., "Career Seminar Tomorrow")
2. Write message content
3. Select recipients:
   - **All Students**: Send to everyone
   - **By Program Type**: Send to all UG or all PG students
   - **Specific Courses**: Select individual courses

4. For specific courses:
   - Click "Select All" or choose individually
   - Can select multiple courses

5. Click "Send Notification"

**Notification appears:**
- In student portal (Notifications page)
- With badge count on notification icon
- Students can mark as read

**Examples:**

**Use Case 1: Seminar Announcement**
```
Title: Career Development Seminar
Message: Join us tomorrow at 2 PM in Auditorium for a session on Resume Building.
Send To: All Students
```

**Use Case 2: Course-Specific Information**
```
Title: Special Drive for BCA Students
Message: Exclusive drive for BCA students by Tech Corp. Apply by Friday.
Send To: Bachelor of Computer Applications
```

**Use Case 3: Program-Wide Update**
```
Title: PG Placement Drive Schedule
Message: All PG students please check the updated placement calendar.
Send To: By Program Type → PG
```

---

#### 4.2 View Notifications

**Location:** Admin Menu → Bell Icon (Top Right)

**Features:**
- View all admin notifications
- Deadline reminders
- System notifications
- Mark as read
- Red badge shows unread count

---

### 5. Reports & Analytics

#### 5.1 Company Progress Tracker

**Location:** Admin Menu → Company Progress Tracker

**Purpose:** Monitor progress of all placement drives

**Features:**

**Filters:**
- Program Type (UG/PG/All)
- Course Selection
- Drive Status

**Data Shown:**
- Company name
- Eligible courses
- Total students registered
- Students placed
- SPOC Name
- SPOC Email
- SPOC Contact Number

**Actions:**
- Export to Excel
- View detailed breakdown
- Generate reports

---

#### 5.2 Generate Report

**Location:** Admin Menu → Generate Report

**Purpose:** Create course-wise placement reports

**Features:**
- Select course
- Choose date range
- Generate PDF report
- View statistics:
  - Total eligible students
  - Students placed
  - Placement percentage
  - Company-wise breakdown

---

### 6. Course Management

**Location:** Admin Menu → Manage Courses

**Purpose:** Add, edit, delete academic courses

**Actions:**

**Add New Course:**
1. Click "Add Course"
2. Enter course name
3. Select program type (UG/PG)
4. Click "Save"

**Edit Course:**
1. Click "Edit" button
2. Modify course name
3. Save changes

**Activate/Deactivate:**
- Deactivated courses don't show in dropdowns
- Historical data remains intact

**Delete Course:**
- Permanently removes course
- Use with caution

---

### 7. Data Management

#### 7.1 Data Migration

**Location:** Admin Menu → Data Migration

**Purpose:** Full database backup and restore

**Features:**

**Export Data:**
1. Select tables to export
2. Click "Export Selected"
3. Downloads SQL file with data
4. Use for backups or moving to new server

**Import Data:**
1. Upload SQL file
2. System validates structure
3. Imports data
4. Merges with existing data

**Use Cases:**
- Year-end data archiving
- Moving to new server
- Disaster recovery
- Cloning database

---

#### 7.2 Backup Module

**Location:** Admin Menu → Backup

**Purpose:** Regular database backups

**Features:**
- One-click backup
- Scheduled backups
- Download backup files
- Restore from backup

---

### 8. Placed Students Management

#### 8.1 On Campus Placed Students

**Location:** Admin Menu → Placed Students

**Purpose:** Track on-campus placements

**Features:**
- View all placed students
- Company-wise breakdown
- CTC details
- Export reports

---

#### 8.2 Offer Letter Collection

**Location:** Admin Menu → Offer Letter Collection

**Purpose:** Track both on and off-campus placements

**Features:**
- On-campus placements (through portal)
- Off-campus placements (external)
- Offer letter uploads
- Status tracking
- Comprehensive reports

---

## Student Features

### 1. Registration Process

**Location:** `http://localhost/placementcell/student_register.php`

**NEW: Two-Step Registration Process**

#### Step 1: UPID Verification
1. Student visits registration page
2. Enters their UPID (Placement ID)
3. Clicks "Verify UPID"

**System Checks:**
- Is UPID registered by admin?
- Is UPID already used?
- Is student data available?

**If Valid:**
- System auto-fills all data:
  - Name
  - Email
  - Phone
  - Course
  - Program
  - Program Type

#### Step 2: Complete Registration
1. Pre-filled fields are read-only (can't be changed)
2. Student fills remaining fields:
   - Register Number
   - Class/Year
   - Year of Passing
   - Password (6+ characters)
   - Confirm Password

3. Click "Register"

**Security Features:**
- Only admin-imported students can register
- UPID must exist in system
- Email verification
- Password encryption
- Duplicate prevention

**Error Scenarios:**

**UPID Not Found:**
```
Error: This UPID is not registered with the placement cell.
Action: Contact placement office to get registered first.
```

**UPID Already Used:**
```
Error: This UPID has already been registered. Please login instead.
Action: Go to login page.
```

---

### 2. Student Dashboard

**Location:** Student Portal → Dashboard

**Overview:**
- Welcome message with student name
- Statistics cards:
  - Total Applications
  - Pending Applications
  - Placements
  - Active Drives

**Recent Activity:**
- Last 5 applications with status
- Upcoming drives
- Recent notifications

---

### 3. Available Opportunities

**Location:** Student Portal → Available Opportunities

**Purpose:** Browse and apply to active placement drives

**What Students See:**

**For Each Drive:**
- Company Name
- Drive Number
- Open and Close Dates
- Time Remaining (countdown)
- Extra Details:
  - Vacancies
  - Duration
  - Stipend/CTC Range
  - Work Mode
  - Office Address
  - Other Details
- Job Description Link
- Available Roles

**Role Details:**
- Designation Name
- Offer Type (Full-time/Internship)
- CTC or Stipend
- Minimum Percentage Required
- Eligible Courses
- Action Button

**Apply Button States:**
- **"Apply Now"** (Green): Eligible and can apply
- **"Applied"** (Green Badge): Already applied
- **"Not Eligible"** (Gray): Course not eligible
- **"Low %"** (Gray): Doesn't meet percentage criteria
- **Status Badge**: If already applied, shows status (Placed/Pending/Rejected)

**Application Process:**
1. Click "Apply Now"
2. Fill application form with:
   - Auto-filled personal details
   - Academic information
   - Resume upload
   - Custom fields (if any)
3. Submit application
4. Confirmation message

**Eligibility Rules:**
- Course must match eligible courses
- Percentage must meet minimum requirement
- Must not have already applied
- Drive must be open (between open and close dates)

---

### 4. My Applications

**Location:** Student Portal → My Applications

**Purpose:** View all submitted applications

**Features:**
- List of all applications
- Sort by date
- Filter by status
- For each application:
  - Company name
  - Role applied for
  - Application date
  - Current status
  - CTC/Stipend
  - Comments from admin (if any)

**Status Badges:**
- 🟢 Green: Placed
- 🔵 Blue: Applied/Pending
- 🔴 Red: Rejected
- ⚫ Black: Blocked

---

### 5. Progress Tracker

**Location:** Student Portal → Progress Tracker

**Purpose:** Track complete placement journey with round-wise details

**What Students See:**

**Statistics Dashboard:**
- Total Applications
- Placed Count
- In Progress Count
- Not Selected Count

**Application Timeline:**
Visual timeline showing:
- Company name
- Role and offer type
- CTC/Stipend
- Application date
- Current status

**🌟 NEW: Round-wise Progress** (for each application)

**Round Information:**
- Round name (e.g., "Group Discussion")
- Round type badge (GD/Technical/HR/Aptitude)
- Scheduled date and time
- Result status with color coding:
  - 🟢 Green: "Shortlisted" - Passed this round!
  - 🔴 Red: "Not Selected" - Didn't clear this round
  - 🟡 Yellow: "Pending" - Result awaited
  - ⚪ Gray: "Not Conducted" - Round cancelled
- Admin comments/feedback

**Example View:**

```
═══════════════════════════════════════════
📊 TCS - Software Developer
Applied: Jan 15, 2026

Round-wise Progress:

1️⃣ Aptitude Test [Aptitude]
   📅 Jan 20, 2026
   ✅ Shortlisted
   💬 "Good performance in logical reasoning"

2️⃣ Technical Interview Round 1 [Technical]
   📅 Jan 22, 2026
   ✅ Shortlisted
   💬 "Strong coding skills demonstrated"

3️⃣ Technical Interview Round 2 [Technical]
   📅 Jan 24, 2026
   ⏳ Pending

4️⃣ HR Interview [HR]
   📅 Not Scheduled Yet
   ⏳ Pending

Status: In Progress 🔵
═══════════════════════════════════════════
```

**Benefits for Students:**
- Real-time status updates
- Know exactly where they stand
- Clear feedback from placement team
- No need to keep asking "when will results come?"
- Transparent process
- Plan for next rounds

---

### 6. My Profile

**Location:** Student Portal → My Profile

**Features:**
- View personal information
- Edit contact details
- Update academic information
- Change password
- View account activity

**Editable Fields:**
- Phone number
- Email (with verification)
- Address
- Percentage
- Resume

**Read-Only Fields:**
- UPID
- Name
- Course (imported by admin)
- Program Type

---

### 7. Notifications

**Location:** Student Portal → Notifications

**Purpose:** View all notifications from placement cell

**Types of Notifications:**

**1. Drive Notifications:**
- New drive opened
- Drive closing soon
- Drive extended

**2. Application Notifications:**
- Application received
- Status changed
- Interview scheduled

**3. Placement Notifications:**
- Selected for role
- Offer letter ready

**4. General Notifications:**
- Seminars and workshops
- Important announcements
- Deadline reminders
- **Custom notifications from admin** (NEW)

**Features:**
- Unread count badge
- Mark as read
- Delete notifications
- Sort by date
- Filter by type

---

## Database Structure

### Core Tables

#### 1. students
**Purpose:** Store all student information

| Column | Type | Description |
|--------|------|-------------|
| student_id | INT (PK) | Unique identifier |
| upid | VARCHAR(50) | Placement ID (unique) |
| student_name | VARCHAR(100) | Full name |
| email | VARCHAR(100) | College email |
| password_hash | VARCHAR(255) | Encrypted password |
| phone_no | VARCHAR(20) | Contact number |
| program_type | VARCHAR(50) | UG or PG |
| program | VARCHAR(50) | Program name |
| course | VARCHAR(100) | Course name |
| class | VARCHAR(50) | Year/Class |
| reg_no | VARCHAR(50) | Registration number |
| percentage | FLOAT | Academic percentage |
| year_of_passing | INT | Expected passing year |
| batch | VARCHAR(20) | Batch year |
| is_active | TINYINT | Active status |
| last_login | TIMESTAMP | Last login time |
| created_at | TIMESTAMP | Registration date |

---

#### 2. drives
**Purpose:** Store placement drive information

| Column | Type | Description |
|--------|------|-------------|
| drive_id | INT (PK) | Unique identifier |
| company_name | VARCHAR(255) | Company name |
| drive_no | VARCHAR(50) | Drive number |
| form_link | VARCHAR(100) | Unique form identifier |
| open_date | DATETIME | Drive opening date |
| close_date | DATETIME | Drive closing date |
| jd_link | TEXT | Job description URL |
| extra_details | TEXT | JSON extra information |
| created_at | TIMESTAMP | Creation date |

**extra_details JSON structure:**
```json
{
  "vacancies": "10",
  "duration": "6 months",
  "stipend": "15000",
  "workMode": "Hybrid",
  "officeAddress": "Bangalore, Karnataka",
  "otherDetails": "Additional information here"
}
```

---

#### 3. drive_roles
**Purpose:** Store roles for each drive

| Column | Type | Description |
|--------|------|-------------|
| role_id | INT (PK) | Unique identifier |
| drive_id | INT (FK) | Reference to drives |
| designation_name | VARCHAR(255) | Job title |
| offer_type | ENUM | Full-time/Internship |
| ctc | VARCHAR(50) | Annual CTC |
| stipend | VARCHAR(50) | Monthly stipend |
| min_percentage | FLOAT | Minimum % required |
| eligible_courses | TEXT | JSON array of courses |
| is_finished | TINYINT | Completed status |
| created_at | TIMESTAMP | Creation date |

---

#### 4. applications
**Purpose:** Store student applications

| Column | Type | Description |
|--------|------|-------------|
| application_id | INT (PK) | Unique identifier |
| student_id | INT (FK) | Reference to students |
| drive_id | INT (FK) | Reference to drives |
| role_id | INT (FK) | Reference to roles |
| student_data | LONGTEXT | JSON form responses |
| resume_file | VARCHAR(255) | Resume file path |
| status | ENUM | Application status |
| comments | TEXT | Admin comments |
| applied_at | TIMESTAMP | Application date |
| status_changed | TIMESTAMP | Last status update |

**Status values:**
- applied
- pending
- placed
- rejected
- blocked
- not_placed

---

#### 5. application_rounds ⭐ NEW
**Purpose:** Track interview rounds for each application

| Column | Type | Description |
|--------|------|-------------|
| round_id | INT (PK) | Unique identifier |
| application_id | INT (FK) | Reference to applications |
| round_name | VARCHAR(100) | Round name |
| round_type | ENUM | Type of round |
| scheduled_date | DATETIME | When round happens |
| result | ENUM | Round result |
| comments | TEXT | Feedback/notes |
| marked_by | VARCHAR(100) | Admin who updated |
| marked_at | TIMESTAMP | When result marked |
| created_at | TIMESTAMP | Round creation date |

**round_type values:**
- GD (Group Discussion)
- Technical
- HR
- Aptitude
- Case Study
- Other

**result values:**
- pending
- shortlisted
- rejected
- not_conducted

**Use Cases:**

**Scenario 1: Multi-round Interview**
```sql
-- Application ID: 123 (TCS Drive)
INSERT INTO application_rounds VALUES
(1, 123, 'Aptitude Test', 'Aptitude', '2026-01-20 10:00:00',
 'shortlisted', 'Good score', 'admin', NOW(), NOW()),

(2, 123, 'Group Discussion', 'GD', '2026-01-21 14:00:00',
 'shortlisted', 'Active participant', 'admin', NOW(), NOW()),

(3, 123, 'Technical Interview', 'Technical', '2026-01-23 11:00:00',
 'pending', NULL, NULL, NULL, NOW());
```

**Scenario 2: Early Rejection**
```sql
-- Application ID: 124 (Infosys Drive)
INSERT INTO application_rounds VALUES
(4, 124, 'Coding Test', 'Technical', '2026-01-20 09:00:00',
 'rejected', 'Did not meet minimum score', 'admin', NOW(), NOW());
-- No further rounds needed
```

---

#### 6. student_notifications
**Purpose:** Store notifications for students

| Column | Type | Description |
|--------|------|-------------|
| notification_id | INT (PK) | Unique identifier |
| student_id | INT (FK) | Recipient student |
| title | VARCHAR(255) | Notification title |
| message | TEXT | Notification content |
| type | ENUM | Notification type |
| is_read | TINYINT | Read status |
| created_at | TIMESTAMP | Creation date |

**type values:**
- drive
- application
- placement
- general

---

#### 7. courses
**Purpose:** Store available courses

| Column | Type | Description |
|--------|------|-------------|
| course_id | INT (PK) | Unique identifier |
| course_name | VARCHAR(255) | Course name |
| program_type | VARCHAR(50) | UG or PG |
| is_active | TINYINT | Active status |
| created_at | TIMESTAMP | Creation date |

---

#### 8. admin_users
**Purpose:** Store admin login credentials

| Column | Type | Description |
|--------|------|-------------|
| id | INT (PK) | Unique identifier |
| username | VARCHAR(50) | Admin username |
| password | VARCHAR(255) | Encrypted password |
| created_at | TIMESTAMP | Creation date |

---

#### 9. student_password_resets
**Purpose:** Handle password reset tokens

| Column | Type | Description |
|--------|------|-------------|
| id | INT (PK) | Unique identifier |
| email | VARCHAR(100) | Student email |
| token | VARCHAR(255) | Reset token |
| created_at | TIMESTAMP | Token creation time |

---

### Database Relationships

```
students (1) ──→ (Many) applications
drives (1) ──→ (Many) drive_roles
drives (1) ──→ (Many) applications
drive_roles (1) ──→ (Many) applications
applications (1) ──→ (Many) application_rounds ⭐ NEW
courses (1) ──→ (Many) students (via course field)
students (1) ──→ (Many) student_notifications
```

---

## Technical Details

### File Structure

```
placementcell/
│
├── config.php                      # Database configuration
├── index.php                       # Admin login
├── dashboard.php                   # Admin dashboard
│
├── Admin Pages
├── header.php                      # Admin navigation
├── footer.php                      # Admin footer
├── add_drive.php                   # Create drives
├── enrolled_students.php           # Applications list
├── manage_rounds.php               # ⭐ Round management (NEW)
├── registered_students.php         # Student database
├── placed_students.php             # Placed students
├── course_specific_drive_data.php  # Progress tracker
├── admin_student_progress.php      # ⭐ Student lookup (NEW)
├── send_notification.php           # ⭐ Send notifications (NEW)
├── admin_notifications.php         # View notifications
├── manage_courses.php              # Course management
├── data_migration.php              # Backup/restore
├── backup_module.php               # Database backup
├── generate_course_report.php      # Reports
│
├── Student Pages
├── student_login.php               # Student login
├── student_register.php            # ⭐ Registration (UPDATED)
├── student_header.php              # Student navigation
├── student_dashboard.php           # Student dashboard
├── student_drives.php              # Browse drives
├── student_applications.php        # My applications
├── student_progress.php            # ⭐ Progress tracker (UPDATED)
├── student_profile.php             # Edit profile
├── student_notifications.php       # View notifications
│
├── API Endpoints
├── check_student_upid.php          # ⭐ UPID verification (NEW)
├── student_send_reset.php          # Password reset
├── form_generator.php              # Dynamic forms
│
├── Utilities
├── course_groups_dynamic.php       # Course definitions
├── logger.php                      # Activity logging
├── check_deadlines_on_load.php     # Deadline monitoring
│
└── Assets
    ├── images/                     # Logos and images
    ├── style.css                   # Admin styles
    └── uploads/                    # Resume uploads
```

---

### Security Features

#### 1. Authentication
- **Password Hashing**: PHP password_hash() with bcrypt
- **Session Management**: Secure session handling
- **Remember Me**: Encrypted cookie with expiry
- **CSRF Protection**: Session-based validation

#### 2. Authorization
- **Role-Based Access**: Admin vs Student portals
- **UPID Verification**: Only admin-registered students can access
- **Resource Protection**: Checks on every page load

#### 3. Input Validation
- **SQL Injection Prevention**: Prepared statements
- **XSS Protection**: htmlspecialchars() on all output
- **File Upload Validation**: Type and size checks
- **Email Validation**: Format and domain checks

#### 4. Data Protection
- **Encrypted Passwords**: Never stored in plain text
- **Secure Cookies**: HttpOnly and Secure flags
- **Session Timeout**: Auto-logout after inactivity
- **Database Backup**: Regular backups

---

### Mobile Responsiveness

#### Breakpoints

**Desktop (> 768px):**
- Full sidebar navigation
- Multi-column layouts
- Large typography
- Desktop-optimized tables

**Tablet (768px - 576px):**
- Collapsible sidebar
- Responsive grid (2 columns)
- Medium typography
- Horizontal scroll tables

**Mobile (< 576px):**
- Hidden sidebar (toggle button)
- Single column layout
- Touch-friendly buttons (44px min)
- 16px inputs (prevents iOS zoom)
- Stacked forms
- Simplified tables

#### Mobile Optimizations

**Typography:**
```css
@media (max-width: 768px) {
  h1, h2 { font-size: 24px; }
  h3 { font-size: 20px; }
  h4 { font-size: 18px; }
  p, span, div { font-size: 14px; }
}
```

**Touch Targets:**
```css
@media (max-width: 768px) {
  a, button, .btn {
    min-height: 44px;  /* Apple guidelines */
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
}
```

**Form Inputs:**
```css
@media (max-width: 768px) {
  input, select, textarea {
    font-size: 16px !important;  /* Prevents zoom on iOS */
    padding: 12px;
  }
}
```

---

### Performance Optimization

#### 1. Database Queries
- **Prepared Statements**: Reusable queries
- **Joins Over Loops**: Minimize database calls
- **Indexes**: On foreign keys and search fields
- **Pagination**: Large result sets split

#### 2. Frontend
- **CSS Minification**: Compressed stylesheets
- **JavaScript Optimization**: Deferred loading
- **Image Optimization**: Compressed images
- **Lazy Loading**: Load on scroll

#### 3. Caching
- **Session Cache**: Store frequently accessed data
- **Browser Cache**: Static assets cached
- **Query Cache**: MySQL query caching enabled

---

## Troubleshooting

### Common Issues

#### Issue 1: Cannot Login as Admin
**Symptoms:**
- "Invalid username or password" error
- Login page keeps reloading

**Solutions:**
1. Check database connection:
   ```php
   // In config.php, verify:
   $port = 3307;  // Correct port
   ```

2. Verify admin credentials exist:
   ```sql
   SELECT * FROM admin_users;
   ```

3. Clear browser cache and cookies

4. Check session directory permissions

---

#### Issue 2: Student Registration Fails
**Symptoms:**
- "UPID not found" error
- Registration form doesn't submit

**Solutions:**
1. Verify student imported by admin:
   ```sql
   SELECT * FROM students WHERE upid = 'YOUR_UPID';
   ```

2. Check password_hash is NULL (not already registered):
   ```sql
   SELECT upid, password_hash FROM students
   WHERE upid = 'YOUR_UPID';
   ```

3. Ensure UPID is exact match (case-sensitive)

4. Check network connectivity to `check_student_upid.php`

---

#### Issue 3: Apply Button Not Working
**Symptoms:**
- "Not Eligible" even though course matches
- Apply button doesn't appear

**Solutions:**
1. Check course spelling in student profile:
   ```sql
   SELECT course FROM students WHERE student_id = YOUR_ID;
   ```

2. Verify course in eligible list:
   ```sql
   SELECT eligible_courses FROM drive_roles WHERE role_id = ROLE_ID;
   ```

3. Check percentage requirement:
   ```sql
   SELECT percentage FROM students WHERE student_id = YOUR_ID;
   SELECT min_percentage FROM drive_roles WHERE role_id = ROLE_ID;
   ```

4. Verify drive is open:
   ```sql
   SELECT open_date, close_date FROM drives WHERE drive_id = DRIVE_ID;
   ```

---

#### Issue 4: Rounds Not Showing
**Symptoms:**
- Students can't see round results
- Admin can't add rounds

**Solutions:**
1. Verify table exists:
   ```sql
   SHOW TABLES LIKE 'application_rounds';
   ```

2. Check application_id:
   ```sql
   SELECT * FROM applications WHERE student_id = YOUR_STUDENT_ID;
   ```

3. Query rounds directly:
   ```sql
   SELECT * FROM application_rounds
   WHERE application_id = YOUR_APPLICATION_ID;
   ```

4. Check foreign key constraints:
   ```sql
   SHOW CREATE TABLE application_rounds;
   ```

---

#### Issue 5: Notifications Not Sending
**Symptoms:**
- Success message but students don't receive
- Zero recipients shown

**Solutions:**
1. Check notification type in query:
   ```sql
   -- Should be 'general', not 'admin'
   SELECT type FROM student_notifications;
   ```

2. Verify students exist in selected course:
   ```sql
   SELECT COUNT(*) FROM students WHERE course = 'YOUR_COURSE';
   ```

3. Check student_notifications table:
   ```sql
   SELECT * FROM student_notifications
   ORDER BY created_at DESC LIMIT 10;
   ```

---

#### Issue 6: Mobile Layout Broken
**Symptoms:**
- Sidebar not hiding on mobile
- Buttons too small
- Text overflowing

**Solutions:**
1. Clear browser cache

2. Check viewport meta tag:
   ```html
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   ```

3. Verify CSS loaded:
   - Open browser inspector
   - Check Network tab for CSS files

4. Test in different browsers

---

### Database Maintenance

#### Weekly Tasks

**1. Backup Database**
```bash
mysqldump -u root -P 3307 admin_placement_db > backup_$(date +%Y%m%d).sql
```

**2. Check Table Health**
```sql
CHECK TABLE students;
CHECK TABLE applications;
CHECK TABLE application_rounds;
```

**3. Optimize Tables**
```sql
OPTIMIZE TABLE applications;
OPTIMIZE TABLE application_rounds;
```

---

#### Monthly Tasks

**1. Archive Old Data**
```sql
-- Move old drives to archive table
CREATE TABLE drives_archive AS
SELECT * FROM drives
WHERE close_date < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

**2. Clean Up Sessions**
```sql
-- Remove expired password reset tokens
DELETE FROM student_password_resets
WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
```

**3. Update Statistics**
```sql
-- Rebuild indexes
ANALYZE TABLE students;
ANALYZE TABLE applications;
```

---

### Log Files

#### Application Logs

**Location:** `C:\xampp\htdocs\placementcell\logs\`

**Log Files:**
- `activity.log` - User actions
- `errors.log` - PHP errors
- `queries.log` - Database queries (if enabled)

**View Recent Errors:**
```bash
tail -n 50 errors.log
```

#### Apache Logs

**Location:** `C:\xampp\apache\logs\`

**Log Files:**
- `error.log` - Apache errors
- `access.log` - HTTP requests

---

## API Reference

### Student APIs

#### 1. UPID Verification API

**Endpoint:** `check_student_upid.php`

**Method:** POST

**Parameters:**
```json
{
  "upid": "UP12345"
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "UPID verified! Your details have been pre-filled.",
  "data": {
    "upid": "UP12345",
    "student_name": "John Doe",
    "email": "john@college.edu",
    "phone_no": "9876543210",
    "program_type": "UG",
    "program": "Bachelor of Computer Applications",
    "course": "BCA-Data Science",
    "reg_no": "",
    "percentage": 85.5
  }
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "This UPID is not registered with the placement cell."
}
```

**Usage Example:**
```javascript
fetch('check_student_upid.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: 'upid=' + encodeURIComponent(upid)
})
.then(res => res.json())
.then(data => {
  if (data.success) {
    // Pre-fill form
    document.getElementById('student_name').value = data.data.student_name;
    document.getElementById('email').value = data.data.email;
    // ... etc
  } else {
    alert(data.message);
  }
});
```

---

#### 2. Password Reset API

**Endpoint:** `student_send_reset.php`

**Method:** POST

**Parameters:**
```json
{
  "email": "student@college.edu"
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Password reset link has been generated.",
  "reset_link": "http://localhost/placementcell/student_reset_password.php?token=abc123..."
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "No account found with that email."
}
```

---

### Admin APIs

#### 1. Round Management

**Add Round:**
```php
POST /manage_rounds.php

Parameters:
- application_id: int
- round_name: string
- round_type: enum (GD/Technical/HR/Aptitude/Case Study/Other)
- scheduled_date: datetime (optional)
```

**Update Result:**
```php
POST /manage_rounds.php

Parameters:
- round_id: int
- result: enum (pending/shortlisted/rejected/not_conducted)
- comments: text (optional)
```

**Delete Round:**
```php
POST /manage_rounds.php

Parameters:
- round_id: int
- delete_round: boolean
```

---

#### 2. Notification API

**Send Notification:**
```php
POST /send_notification.php

Parameters:
- title: string (required)
- message: text (required)
- recipient_type: enum (all/program_type/specific_course)
- program_types[]: array (if recipient_type = program_type)
- courses[]: array (if recipient_type = specific_course)
```

**Example:**
```php
// Send to all BCA students
$_POST = [
  'title' => 'Coding Competition',
  'message' => 'Register by Friday',
  'recipient_type' => 'specific_course',
  'courses' => ['BCA-Data Science', 'BCA-Cloud Computing']
];
```

---

## Best Practices

### For Admins

#### 1. Data Entry
- Import students at start of semester
- Assign unique UPIDs systematically
- Use consistent naming conventions
- Double-check course names (must match exactly)
- Verify email addresses are valid

#### 2. Drive Management
- Create drives well in advance
- Set realistic eligibility criteria
- Add detailed job descriptions
- Close drives on time
- Update round results promptly

#### 3. Communication
- Send notifications for important updates
- Keep students informed of round results
- Add meaningful comments on applications
- Respond to student queries quickly

#### 4. Round Management
- Add rounds as soon as scheduled
- Update results within 24 hours
- Provide constructive feedback in comments
- Mark cancelled rounds as "Not Conducted"
- Keep students informed

---

### For Students

#### 1. Registration
- Use official college email
- Enter correct UPID
- Keep password secure
- Complete profile information
- Update resume regularly

#### 2. Applications
- Read eligibility carefully
- Apply before deadline
- Fill all required fields
- Attach updated resume
- Double-check before submit

#### 3. Profile Management
- Keep contact info updated
- Maintain current percentage
- Upload professional resume
- Check for notifications daily

#### 4. Round Tracking
- Check progress tracker regularly
- Prepare for upcoming rounds
- Read admin comments carefully
- Ask for clarification if needed

---

## Frequently Asked Questions (FAQ)

### General

**Q1: What is UPID?**
A: UPID (University Placement ID) is a unique identifier assigned by the placement cell to each student. It's used to register on the student portal.

**Q2: Can I register without UPID?**
A: No. Students must first be registered by the admin (placement cell) with a UPID before they can create an account.

**Q3: I forgot my password. What should I do?**
A: Click "Forgot Password" on login page, enter your email, and you'll receive a reset link.

---

### For Students

**Q4: Why can't I see some drives?**
A: Drives are only visible if:
- They are currently open (between open and close dates)
- Your course is eligible
- You meet the minimum percentage requirement

**Q5: I applied but status shows "Not Eligible". Why?**
A: This can happen if:
- Admin changed eligibility criteria after you applied
- Your course name doesn't exactly match
- Your percentage was updated and no longer meets requirement
Contact placement office to verify.

**Q6: Where can I see my interview round results?**
A: Go to "Progress Tracker" in student portal. You'll see:
- All your applications
- Round-wise progress for each
- Results (Shortlisted/Rejected/Pending)
- Admin comments and feedback

**Q7: Will I get notified about round results?**
A: Yes, check:
- Progress Tracker (updated in real-time)
- Notifications page (if admin sends notification)
- Email (if configured)

**Q8: Can I apply to multiple roles in same company?**
A: No, you can only apply to one role per drive. Choose carefully.

**Q9: Can I withdraw my application?**
A: No, applications cannot be withdrawn. Apply only to drives you're seriously interested in.

---

### For Admins

**Q10: How do I import students in bulk?**
A:
1. Go to "Placement Registered Students"
2. Download Excel template
3. Fill student details (UPID, name, email, course, etc.)
4. Upload filled Excel file
5. System will validate and import

**Q11: What if student's UPID is wrong?**
A:
1. Go to "Placement Registered Students"
2. Find the student
3. Edit and correct UPID
4. Student can now register with correct UPID

**Q12: How do I add interview rounds?**
A:
1. Go to "Manage Round Results"
2. Select the drive
3. Find the student's application
4. Click "Add Round"
5. Fill round details and save

**Q13: Can I edit round results after marking?**
A: Yes, click "Result" button again and update. System maintains audit trail (who updated, when).

**Q14: How do I send notifications to specific courses?**
A:
1. Go to "Send Notification"
2. Enter title and message
3. Select "Specific Courses"
4. Choose courses from list
5. Click "Send Notification"

**Q15: Can I restore deleted data?**
A: Only if you have backup. Use "Data Migration" → "Export" regularly to create backups.

---

## Support & Contact

### Technical Issues

**Email:** placement@college.edu
**Phone:** +91 123-456-7890
**Office:** Placement Cell, Ground Floor

**Office Hours:**
- Monday to Friday: 9:00 AM - 5:00 PM
- Saturday: 9:00 AM - 1:00 PM
- Sunday: Closed

---

### Reporting Bugs

If you encounter a bug:
1. Note the exact error message
2. Screenshot the issue
3. Note steps to reproduce
4. Email to technical support with details

---

### Feature Requests

To suggest new features:
1. Email detailed description
2. Explain use case
3. Provide examples
4. Wait for evaluation

---

## Version History

### Version 2.0 (January 2026) - Current
**Major Updates:**
- ✅ Round-wise Results Tracking System
- ✅ Auto-sync Registration with UPID Verification
- ✅ Manual Notification System
- ✅ Student Progress Tracker
- ✅ Admin Student Progress Lookup
- ✅ Mobile-Friendly Student Portal
- ✅ Course Management Enhancements

**Bug Fixes:**
- Fixed notification type enum error
- Improved form generator type safety
- Enhanced password reset flow
- Fixed course filter logic
- Improved eligibility checking

---

### Version 1.0 (2023)
**Initial Release:**
- Admin and Student Portals
- Drive Management
- Application Tracking
- Basic Reporting
- User Management

---

## Appendix

### A. Keyboard Shortcuts

**Admin Portal:**
- `Ctrl + S` - Save/Submit forms
- `Esc` - Close modals
- `Ctrl + F` - Search on page

**Student Portal:**
- `Ctrl + S` - Save profile changes
- `Esc` - Close notifications

---

### B. Browser Compatibility

| Browser | Minimum Version | Tested |
|---------|----------------|--------|
| Chrome | 90+ | ✅ Yes |
| Firefox | 88+ | ✅ Yes |
| Safari | 14+ | ✅ Yes |
| Edge | 90+ | ✅ Yes |
| Opera | 76+ | ⚠️ Partial |
| IE | Not Supported | ❌ No |

---

### C. Glossary

**Application:** Student's submission for a specific role in a drive

**CTC:** Cost to Company (annual package)

**Drive:** Placement opportunity by a company

**Eligibility:** Criteria students must meet to apply

**Form Link:** Unique identifier for drive application forms

**Internship:** Short-term work opportunity with stipend

**Role:** Specific job position in a drive

**Round:** Interview/test stage in selection process

**SPOC:** Single Point of Contact (company representative)

**Stipend:** Monthly payment during internship

**UPID:** University Placement ID (unique student identifier)

---

### D. SQL Queries Reference

**Get all students in a course:**
```sql
SELECT * FROM students
WHERE course = 'BCA-Data Science'
AND is_active = 1;
```

**Get applications for a drive:**
```sql
SELECT a.*, s.student_name, s.email
FROM applications a
JOIN students s ON a.student_id = s.student_id
WHERE a.drive_id = 1;
```

**Get round-wise progress for student:**
```sql
SELECT
  d.company_name,
  dr.designation_name,
  ar.round_name,
  ar.round_type,
  ar.result,
  ar.scheduled_date
FROM application_rounds ar
JOIN applications a ON ar.application_id = a.application_id
JOIN drives d ON a.drive_id = d.drive_id
JOIN drive_roles dr ON a.role_id = dr.role_id
WHERE a.student_id = 1
ORDER BY ar.created_at;
```

**Get placement statistics:**
```sql
SELECT
  COUNT(DISTINCT CASE WHEN status = 'placed' THEN student_id END) as placed,
  COUNT(DISTINCT student_id) as total,
  ROUND(COUNT(DISTINCT CASE WHEN status = 'placed' THEN student_id END) * 100.0 /
        COUNT(DISTINCT student_id), 2) as percentage
FROM applications;
```

**Get students who cleared all rounds:**
```sql
SELECT DISTINCT s.student_name, d.company_name
FROM students s
JOIN applications a ON s.student_id = a.student_id
JOIN drives d ON a.drive_id = d.drive_id
WHERE a.application_id NOT IN (
  SELECT application_id
  FROM application_rounds
  WHERE result = 'rejected'
)
AND a.application_id IN (
  SELECT application_id
  FROM application_rounds
);
```

---

### E. Color Codes Reference

**Status Colors:**
- 🟢 #4CAF50 - Success/Placed/Shortlisted
- 🔵 #2196F3 - Applied/Pending
- 🔴 #f44336 - Rejected/Error
- 🟡 #FFC107 - Warning/Pending Decision
- ⚫ #424242 - Blocked/Inactive
- ⚪ #9E9E9E - Not Conducted/Neutral

**Brand Colors:**
- Primary: #650000 (Maroon)
- Secondary: #800000 (Dark Red)
- Accent: #fdc800 (Gold)

---

## Credits

**Developed For:** Mount Carmel College Placement Cell
**Version:** 2.0
**Last Updated:** January 2026
**Powered By:** PHP, MySQL, Bootstrap, jQuery

---

**End of Documentation**

For latest updates and support, contact the Placement Cell office.

---

*This documentation is subject to updates as new features are added and improvements are made to the system.*
