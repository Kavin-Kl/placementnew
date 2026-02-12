# 🚀 Quick Start Guide - Placement Cell Management System

## ⚡ Getting Started in 5 Minutes

### Step 1: Import Database
1. Open **phpMyAdmin**
2. Create a new database: `admin_placement_db`
3. Import: `Database/admin_placement_db (17-09-2025).sql`
4. Import: `Database/student_auth_migration.sql` ⚠️ **IMPORTANT for student portal**

### Step 2: Configure Database Connection
Edit `config.php`:
```php
$host = "localhost";
$user = "root";        // Your MySQL username
$pass = "";            // Your MySQL password
$db   = "admin_placement_db";
```

### Step 3: Set File Permissions
```bash
chmod 777 uploads/
chmod 777 uploads/resumes/
chmod 777 backups/
```

### Step 4: Access the System

#### Admin Portal
- URL: `http://localhost/placementcell/index.php`
- Default credentials are in the database (`admin_users` table)
- Check existing admin usernames and reset passwords if needed

#### Student Portal
- URL: `http://localhost/placementcell/student_login.php`
- Students need to register first
- Click "Register here" to create a new account

---

## 🎯 What You Get

### Admin Features
✅ Manage placement drives
✅ Track student applications
✅ View placement statistics
✅ Generate reports
✅ Backup system
✅ User management

### Student Features
✅ Register and login
✅ View available drives
✅ Apply for jobs/internships
✅ Track application status
✅ Manage profile
✅ Receive notifications

---

## 📝 First-Time Setup Tasks

### For Admins:
1. Login to admin portal
2. Verify existing drives
3. Add new placement drives if needed
4. Check student data

### For Students:
1. Register at `student_login.php`
2. Complete profile information
3. Browse available drives
4. Apply for suitable positions

---

## 🔑 Default Test Credentials

### Admin (from database):
- Username: `adminuser`
- Email: `preethamkumari391@gmail.com`
- Password: Check database or reset using forgot password

### Student:
- No defaults - students must register
- Test registration with dummy data

---

## 📂 Important Folders

| Folder | Purpose | Permissions |
|--------|---------|-------------|
| `uploads/` | Student resumes | 777 |
| `uploads/resumes/` | Auto-created for resumes | 777 |
| `backups/` | Database backups | 777 |
| `exports/` | Excel exports | 777 |
| `logs/` | System logs | 777 |

---

## ⚠️ Common Issues

### Issue: "Database connection failed"
**Fix:** Check `config.php` credentials

### Issue: "Table doesn't exist"
**Fix:** Run both SQL files in correct order:
1. First: `admin_placement_db (17-09-2025).sql`
2. Then: `student_auth_migration.sql`

### Issue: "Resume upload failed"
**Fix:** Create folder and set permissions:
```bash
mkdir -p uploads/resumes
chmod 777 uploads/resumes
```

### Issue: "Student can't see drives"
**Fix:** Ensure drives have:
- `open_date` <= current date/time
- `close_date` >= current date/time

---

## 🎨 Customization

### Change College Name/Logo:
1. Replace `images/MCC_login_logo.png`
2. Update titles in header files

### Change Theme Colors:
- Admin theme: `#581729` (maroon)
- Student theme: Purple gradient
- Edit CSS in respective header files

### Add Custom Fields:
- Use admin form generator
- Create custom application forms

---

## 📱 URLs Reference

| Page | URL | Access |
|------|-----|--------|
| Admin Login | `/index.php` | Admin only |
| Admin Dashboard | `/dashboard.php` | Admin only |
| Student Login | `/student_login.php` | Students |
| Student Register | `/student_register.php` | Public |
| Student Dashboard | `/student_dashboard.php` | Students only |

---

## 🔐 Security Notes

1. **Change default passwords** after installation
2. **Use HTTPS** in production
3. **Set proper file permissions** (755 for files, 777 for upload folders)
4. **Update email config** in `config.php` for password resets
5. **Backup regularly** using the built-in backup module

---

## 📞 Next Steps

1. ✅ Complete database setup
2. ✅ Login as admin
3. ✅ Create a test drive
4. ✅ Register as a test student
5. ✅ Apply for the test drive
6. ✅ Check application in admin panel

---

**Need Help?** Check `STUDENT_PORTAL_SETUP.md` for detailed documentation.

**Report Issues:** Review troubleshooting section in setup guide.

---

**Version:** 2.0 with Student Portal
**Last Updated:** January 2026
