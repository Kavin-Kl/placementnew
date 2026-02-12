# Student Portal Setup Guide

## Overview
This placement cell management system now includes both **Admin Portal** and **Student Portal** with complete functionality for managing placement drives, student applications, and notifications.

---

## 🚀 Quick Setup Instructions

### Step 1: Run Database Migration
Before using the student portal, you **MUST** run the database migration to add required tables and fields.

1. Open **phpMyAdmin** or your MySQL client
2. Select your database (default: `admin_placement_db`)
3. Go to the **SQL** tab
4. Open the file: `Database/student_auth_migration.sql`
5. Copy and paste the entire SQL content
6. Click **"Go"** to execute

This will:
- Add authentication fields to the `students` table (`password_hash`, `is_active`, `last_login`, `email_verified`)
- Create `student_notifications` table
- Create `student_password_resets` table
- Add necessary indexes

### Step 2: Update Configuration (if needed)
The student portal uses the same `config.php` as the admin portal. No additional configuration is needed.

### Step 3: Access the Portals

#### Admin Portal
- URL: `http://yourdomain.com/placementcell/index.php`
- Use existing admin credentials

#### Student Portal
- URL: `http://yourdomain.com/placementcell/student_login.php`
- Students need to register first using the registration page

---

## 📋 Features Overview

### Admin Portal Features (ALL PRESERVED)
✅ Admin login and authentication
✅ Dashboard with drive statistics
✅ Add/Edit/Delete placement drives
✅ Manage drive roles and eligibility
✅ View registered students
✅ View enrolled/applied students
✅ View placed students (on/off campus)
✅ Company progress tracker
✅ Form generator with custom fields
✅ Database backup system
✅ Export to Excel/CSV
✅ User management
✅ File uploads (resumes, offers, photos)

### NEW Student Portal Features
✅ Student registration and login
✅ Student dashboard with statistics
✅ View available drives and opportunities
✅ Apply for jobs/internships
✅ Track application status
✅ Profile management
✅ Notifications system
✅ Password reset functionality
✅ Resume upload during application
✅ Eligibility checking (course, percentage)

---

## 👥 Student Portal Pages

### 1. `student_login.php`
- Student login page
- Remember me functionality
- Forgot password link
- Link to registration page
- Link to admin portal

### 2. `student_register.php`
- New student registration
- Course selection with dynamic dropdowns
- Password validation (min 6 characters)
- Duplicate checking (UPID, Reg No, Email)

### 3. `student_dashboard.php`
- Overview of applications
- Active drives count
- Recent applications
- Profile completion status
- Quick access to all features

### 4. `student_drives.php`
- List of active placement drives
- View available roles
- Check eligibility for each role
- Apply button for eligible roles
- Drive details and deadlines

### 5. `student_apply.php`
- Application form for specific role
- Resume upload (PDF, DOC, DOCX)
- Student information pre-filled
- Eligibility validation
- Auto-notification on submission

### 6. `student_applications.php`
- View all submitted applications
- Track application status
- Download uploaded resumes
- Status badges (Applied, Placed, Rejected, etc.)

### 7. `student_profile.php`
- Update personal information
- Change password
- View profile summary
- Account status

### 8. `student_notifications.php`
- View all notifications
- Unread notification count
- Mark as read/delete
- Different notification types (Drive, Application, Placement)

---

## 🔐 Student Registration Process

### For New Students:
1. Visit `student_login.php`
2. Click "Register here"
3. Fill in the registration form:
   - Placement ID (UPID)
   - Register Number
   - Full Name
   - Email (college email recommended)
   - Phone Number
   - Program Type (UG/PG)
   - Program (BCA, B.COM, BBA, etc.)
   - Course (specific course)
   - Class/Year
   - Year of Passing
   - Password (min 6 characters)
4. Submit and login

### For Existing Students (Already in Database):
If students are already in the `students` table but don't have passwords:
- They need to register using the same UPID/Reg No
- The system will update their existing record with a password
- OR Admin can manually set passwords via phpMyAdmin

---

## 📊 Database Tables

### Modified Tables:
- **`students`** - Added: `password_hash`, `is_active`, `last_login`, `email_verified`

### New Tables:
- **`student_notifications`** - Stores student notifications
- **`student_password_resets`** - Handles password reset tokens

### Existing Tables (Unchanged):
- `admin_users`
- `applications`
- `drives`
- `drive_roles`
- `drive_data`
- `placed_students`
- `on_off_campus_students`
- `form_links`
- `password_resets`

---

## 🎨 UI/UX Features

### Student Portal Design:
- Modern, responsive design
- Purple gradient login/register pages
- Sidebar navigation (collapsible)
- Bootstrap 5 components
- Boxicons for icons
- Mobile-friendly
- Real-time notification badges
- Status color coding

### Admin Portal:
- All existing styling preserved
- Same maroon theme (#581729)
- All functionality intact

---

## 🔔 Notification System

Notifications are automatically created when:
- Student submits an application
- Application status changes (admin updates)
- New drive is posted (can be customized)

Admins can trigger notifications by:
- Updating application status in `enrolled_students.php`
- The system will auto-notify students

---

## 🛡️ Security Features

- Password hashing (PHP `password_hash()`)
- SQL injection prevention (prepared statements)
- Session management
- CSRF protection (can be enhanced)
- File upload validation
- Eligibility checking before application
- Duplicate application prevention

---

## 📁 File Structure

```
placementcell/
├── Database/
│   ├── admin_placement_db (17-09-2025).sql
│   └── student_auth_migration.sql (NEW)
├── images/
│   ├── login_background.png
│   └── MCC_login_logo.png
├── uploads/
│   └── resumes/ (auto-created)
│
├── Admin Portal Files:
│   ├── index.php (Admin login)
│   ├── dashboard.php
│   ├── add_drive.php
│   ├── edit_drive.php
│   ├── enrolled_students.php
│   ├── registered_students.php
│   ├── placed_students.php
│   ├── on_off_campus.php
│   ├── course_specific_drive_data.php
│   ├── form_generator.php
│   ├── backup_module.php
│   ├── users.php
│   ├── header.php
│   └── ... (all other admin files)
│
├── Student Portal Files (NEW):
│   ├── student_login.php
│   ├── student_register.php
│   ├── student_dashboard.php
│   ├── student_drives.php
│   ├── student_apply.php
│   ├── student_applications.php
│   ├── student_profile.php
│   ├── student_notifications.php
│   ├── student_header.php
│   ├── student_send_reset.php
│   └── student_reset_password.php
│
├── config.php (shared)
├── course_groups.php (shared)
├── style.css (admin)
└── README.txt
```

---

## ✅ Testing Checklist

### Admin Portal:
- [ ] Admin login works
- [ ] All existing pages load correctly
- [ ] Can create drives
- [ ] Can view students
- [ ] Can view applications
- [ ] Export functions work
- [ ] Backup system works

### Student Portal:
- [ ] Student registration works
- [ ] Student login works
- [ ] Dashboard displays correctly
- [ ] Can view active drives
- [ ] Can apply for eligible roles
- [ ] Application tracking works
- [ ] Profile update works
- [ ] Password change works
- [ ] Notifications display
- [ ] Resume upload works

---

## 🐛 Troubleshooting

### Issue: Migration SQL fails
**Solution:** Ensure you're using MySQL 5.7+ or MySQL 8.0+

### Issue: Student can't register
**Solution:** Check if UPID/Reg No/Email already exists in database

### Issue: Resume upload fails
**Solution:**
- Check `uploads/resumes/` folder exists
- Set folder permissions to 777 (or 755)
- Ensure `upload_max_filesize` in php.ini is adequate

### Issue: Notifications not showing
**Solution:** Ensure `student_notifications` table was created properly

### Issue: Student can't see drives
**Solution:** Check that drives have `open_date <= NOW()` and `close_date >= NOW()`

---

## 🔄 Migration Notes

### For Existing Systems:
If you already have students in the database:
1. Run the migration SQL
2. Students must register to set their passwords
3. OR manually add passwords via phpMyAdmin using:
   ```php
   password_hash('your_password', PASSWORD_DEFAULT)
   ```

### Backward Compatibility:
- All admin functionality remains unchanged
- Admin panel works exactly as before
- Student table structure is backwards compatible
- New fields have defaults (NULL or 0)

---

## 📞 Support

For issues or questions:
1. Check the troubleshooting section
2. Review database structure
3. Check browser console for JavaScript errors
4. Check PHP error logs

---

## 🎯 Future Enhancements (Optional)

- Email notifications (SMTP integration)
- Document upload in profile
- Advanced filtering in drives
- Interview scheduling
- Placement statistics for students
- Mobile app integration
- Two-factor authentication
- Email verification
- Social login (Google, Microsoft)

---

**Note:** This setup maintains 100% backward compatibility. All existing admin features are preserved and continue to work exactly as before. The student portal is an addition, not a replacement.
