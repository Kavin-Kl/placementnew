# 📚 Placement Cell Management System - Complete Guide

## 🎯 Project Overview

A **comprehensive placement cell management system** for Mount Carmel College with both **Admin Portal** and **Student Portal**. This system manages placement drives, student applications, and provides complete tracking of the placement process.

### Version Information
- **Version:** 2.0
- **Release Date:** January 2026
- **Platform:** PHP + MySQL
- **Framework:** Bootstrap 5

---

## ✨ Key Features

### 🔐 Dual Portal System

#### **Admin Portal**
- Complete placement drive management
- Student application tracking
- Placement statistics and reporting
- Company progress tracker
- Automated backup system
- User role management
- Export functionality (Excel/CSV)
- Form generator with custom fields

#### **Student Portal** ⭐ NEW
- Student self-registration
- Browse active opportunities
- Online application submission
- Real-time application tracking
- Profile management
- Notification system
- Password recovery
- Resume management

---

## 📁 Project Structure

```
placementcell/
│
├── 📂 Database/
│   ├── admin_placement_db (17-09-2025).sql    # Main database
│   └── student_auth_migration.sql              # Student portal migration ⭐
│
├── 📂 Admin Portal Files
│   ├── index.php                               # Admin login
│   ├── dashboard.php                           # Admin dashboard
│   ├── add_drive.php                           # Create new drives
│   ├── edit_drive.php                          # Edit existing drives
│   ├── enrolled_students.php                   # View applications
│   ├── registered_students.php                 # View registered students
│   ├── placed_students.php                     # On-campus placements
│   ├── on_off_campus.php                       # Overall placements
│   ├── course_specific_drive_data.php          # Company tracker
│   ├── form_generator.php                      # Custom form builder
│   ├── backup_module.php                       # Backup management
│   ├── users.php                               # Admin user management
│   ├── header.php                              # Admin navigation
│   └── ... (other admin files)
│
├── 📂 Student Portal Files ⭐ NEW
│   ├── student_login.php                       # Student login
│   ├── student_register.php                    # Student registration
│   ├── student_dashboard.php                   # Student dashboard
│   ├── student_drives.php                      # Browse opportunities
│   ├── student_apply.php                       # Apply for positions
│   ├── student_applications.php                # Track applications
│   ├── student_profile.php                     # Profile management
│   ├── student_notifications.php               # Notifications
│   ├── student_header.php                      # Student navigation
│   ├── student_send_reset.php                  # Password reset (send)
│   └── student_reset_password.php              # Password reset (form)
│
├── 📂 Shared Files
│   ├── config.php                              # Database configuration
│   ├── course_groups.php                       # Course definitions
│   └── style.css                               # Admin styles
│
├── 📂 Assets
│   └── images/
│       ├── login_background.png
│       └── MCC_login_logo.png
│
├── 📂 Uploads (Auto-created)
│   ├── resumes/                                # Student resumes
│   ├── offers/                                 # Offer letters
│   └── photos/                                 # Student photos
│
├── 📂 Documentation
│   ├── README.txt                              # Basic readme
│   ├── README_COMPLETE.md                      # This file
│   ├── QUICKSTART.md                           # Quick setup guide
│   ├── STUDENT_PORTAL_SETUP.md                 # Detailed setup
│   └── DEPLOYMENT_CHECKLIST.md                 # Deployment guide
│
└── home.php                                    # Landing page ⭐
```

---

## 🚀 Installation Guide

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- phpMyAdmin (recommended)

### Step-by-Step Installation

#### 1️⃣ Database Setup
```sql
-- Create database
CREATE DATABASE admin_placement_db;

-- Import main database
# Import: Database/admin_placement_db (17-09-2025).sql

-- Import student portal migration ⚠️ IMPORTANT
# Import: Database/student_auth_migration.sql
```

#### 2️⃣ Configuration
Edit `config.php`:
```php
$host = "localhost";
$user = "your_mysql_username";
$pass = "your_mysql_password";
$db   = "admin_placement_db";

// Update email config for password resets
$email_config = [
    'smtp_username' => 'your_email@gmail.com',
    'smtp_password' => 'your_app_password',
    // ... other settings
];
```

#### 3️⃣ File Permissions
```bash
chmod 755 placementcell/
chmod 777 placementcell/uploads/
chmod 777 placementcell/backups/
chmod 777 placementcell/exports/
chmod 777 placementcell/logs/
```

#### 4️⃣ Access the System
- **Home Page:** `http://yourdomain.com/placementcell/home.php`
- **Admin Portal:** `http://yourdomain.com/placementcell/index.php`
- **Student Portal:** `http://yourdomain.com/placementcell/student_login.php`

---

## 📊 Database Schema

### Core Tables

| Table | Description | Type |
|-------|-------------|------|
| `admin_users` | Admin credentials | Existing |
| `students` | Student information + auth | **Modified** ⭐ |
| `drives` | Placement drives | Existing |
| `drive_roles` | Roles within drives | Existing |
| `applications` | Student applications | Existing |
| `placed_students` | Placement records | Existing |
| `student_notifications` | Student notifications | **NEW** ⭐ |
| `student_password_resets` | Password reset tokens | **NEW** ⭐ |

### Student Table Modifications ⭐
New fields added:
- `password_hash` - Hashed password
- `is_active` - Account status
- `last_login` - Last login timestamp
- `email_verified` - Email verification status

---

## 👥 User Roles & Access

### Admin Users
**Access:** Full system control
**Features:**
- Manage drives and roles
- View all applications
- Update application statuses
- Generate reports
- Manage users
- System backups

### Students
**Access:** Self-service portal
**Features:**
- Browse opportunities
- Submit applications
- Track status
- Manage profile
- Receive notifications

---

## 🔄 Workflow

### Placement Drive Workflow

```
1. Admin creates drive
   ↓
2. Admin adds roles to drive
   ↓
3. Drive opens (based on dates)
   ↓
4. Students see drive in portal
   ↓
5. Students apply with resume
   ↓
6. Admin reviews applications
   ↓
7. Admin updates status (Placed/Rejected)
   ↓
8. Student receives notification
   ↓
9. Placement tracking updated
```

---

## 🎨 UI/UX Highlights

### Admin Portal
- **Color Scheme:** Maroon (#581729)
- **Layout:** Sidebar navigation
- **Framework:** Bootstrap 5
- **Icons:** Boxicons, Font Awesome
- **Features:** Charts, tables, modals

### Student Portal ⭐
- **Color Scheme:** Purple gradient
- **Layout:** Modern sidebar navigation
- **Framework:** Bootstrap 5
- **Icons:** Boxicons
- **Features:** Cards, badges, notifications

---

## 🔐 Security Features

1. **Password Security**
   - PHP `password_hash()` with bcrypt
   - Minimum 6 characters
   - Secure password reset with tokens

2. **SQL Injection Prevention**
   - Prepared statements
   - Parameter binding

3. **Session Management**
   - Secure session handling
   - Auto-logout on inactivity
   - Remember me with secure cookies

4. **File Upload Security**
   - File type validation
   - Size restrictions
   - Secure file naming

5. **Access Control**
   - Role-based access
   - Page-level authentication
   - Admin/Student separation

---

## 📱 Mobile Responsiveness

- ✅ Fully responsive design
- ✅ Works on phones, tablets, desktops
- ✅ Collapsible sidebar on mobile
- ✅ Touch-friendly interfaces
- ✅ Optimized tables for small screens

---

## 🔔 Notification System ⭐

### Automatic Notifications
Students receive notifications when:
- Application is submitted
- Application status changes
- New drives are posted (can be enabled)

### Notification Types
- 🟦 Drive notifications (new opportunities)
- 🟨 Application notifications (status updates)
- 🟩 Placement notifications (placed status)
- ⬜ General notifications

---

## 📈 Reports & Analytics

### Admin Analytics
- Total drives created
- Applications per drive
- Placement statistics
- Course-wise placement data
- Company-wise hiring data

### Student Analytics
- Total applications
- Pending applications
- Placement status
- Profile completion

---

## 🛠️ Maintenance & Support

### Regular Maintenance
- **Daily:** Check error logs
- **Weekly:** Database backup
- **Monthly:** Clear old notifications
- **Quarterly:** Archive old data

### Backup Strategy
- Automatic backups (configurable)
- Manual backup option
- Download backup files
- Restore capability

---

## 🐛 Troubleshooting

### Common Issues

**Issue:** Database connection failed
```
Solution: Check config.php credentials
```

**Issue:** Student can't see drives
```
Solution: Verify drive dates (open_date <= NOW() <= close_date)
```

**Issue:** Resume upload fails
```
Solution:
- Check uploads/resumes/ folder exists
- Verify permissions (777)
- Check file size limits in php.ini
```

**Issue:** Notifications not appearing
```
Solution: Verify student_notifications table exists
```

---

## 📞 Support & Documentation

### Documentation Files
1. **QUICKSTART.md** - 5-minute setup guide
2. **STUDENT_PORTAL_SETUP.md** - Detailed setup instructions
3. **DEPLOYMENT_CHECKLIST.md** - Production deployment guide
4. **README_COMPLETE.md** - This comprehensive guide

### Getting Help
1. Check documentation first
2. Review troubleshooting section
3. Check error logs
4. Verify database structure

---

## 🚀 Future Enhancements (Roadmap)

### Potential Features
- [ ] Email notifications via SMTP
- [ ] SMS notifications
- [ ] Interview scheduling
- [ ] Video interview integration
- [ ] Document verification
- [ ] Multi-language support
- [ ] Advanced analytics dashboard
- [ ] Mobile app (React Native/Flutter)
- [ ] API for third-party integration
- [ ] Two-factor authentication
- [ ] Social login (Google, LinkedIn)

---

## 📜 Changelog

### Version 2.0 (January 2026) ⭐
**Major Update: Student Portal Added**
- ✅ Student authentication system
- ✅ Student self-registration
- ✅ Student dashboard
- ✅ Browse and apply for drives
- ✅ Application tracking
- ✅ Profile management
- ✅ Notification system
- ✅ Password reset for students
- ✅ Resume upload system
- ✅ Landing page (home.php)
- ✅ Comprehensive documentation

### Version 1.0 (September 2025)
- Admin portal with full functionality
- Drive management
- Application tracking
- Placement records
- Backup system
- Export features

---

## 🎓 Credits

**Developed for:** Mount Carmel College Placement Cell

**Technologies Used:**
- PHP 8.x
- MySQL 8.0
- Bootstrap 5.3
- jQuery 3.6
- Chart.js
- Boxicons
- Font Awesome
- Flatpickr
- XLSX.js

---

## 📄 License

This project is proprietary to Mount Carmel College. All rights reserved.

---

## 📝 Notes

### Important Reminders
1. **Always backup** before making changes
2. **Test in staging** before production
3. **Keep credentials secure**
4. **Update regularly**
5. **Monitor logs**

### Best Practices
- Change default passwords immediately
- Use HTTPS in production
- Regular database maintenance
- Archive old data periodically
- Train users properly

---

## 🎯 Quick Links

| Resource | URL |
|----------|-----|
| Admin Login | `/index.php` |
| Student Login | `/student_login.php` |
| Student Register | `/student_register.php` |
| Home Page | `/home.php` |
| Documentation | `/STUDENT_PORTAL_SETUP.md` |

---

**System Status:** ✅ Production Ready

**Last Updated:** January 4, 2026

**Version:** 2.0 with Student Portal

---

For questions or support, refer to the documentation or contact the placement cell IT team.

**Happy Recruiting! 🎓💼**
