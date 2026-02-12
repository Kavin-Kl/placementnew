# New Features Implementation Guide

This document explains the two new features that have been added to the Placement Cell system.

---

## Feature 1: Dynamic Form Field Editor

### Overview
Admins can now customize application form fields for each drive. You can add, remove, or edit fields based on the specific requirements of each company/job posting.

### How to Use

1. **Navigate to Edit Drive Page**
   - Go to any drive's edit page (`edit_drive.php?id=X`)
   - You'll see a new button "Customize Application Form" in the top-right corner of the Drive Information card

2. **Open Form Field Editor**
   - Click the "Customize Application Form" button
   - This opens the form field customization interface

3. **Select Fields to Include**
   - Fields are organized into 4 categories:
     - **Personal**: Name, contact info, demographics
     - **Education**: Academic qualifications (10th, 12th, UG, PG)
     - **Work**: Internships, projects, experience
     - **Others**: Skills, certifications, documents

   - Each category is collapsible - click the header to expand/collapse
   - Check/uncheck fields as needed for the specific job
   - Use "Select All" / "Deselect All" buttons for quick selection

4. **Add Custom Fields**
   - Scroll to the "Custom Fields" section at the bottom
   - Click "Add Custom Field" button
   - For each custom field, specify:
     - **Field Name**: The label students will see
     - **Category**: Which section it belongs to
     - **Type**: Text, Textarea, Number, Date, or File upload
   - Click the trash icon to remove a custom field

5. **Save Configuration**
   - Click "Save Form Configuration" to save your changes
   - Changes apply immediately to the student application form
   - Click "Preview Form" to see how students will see the form

### Important Notes
- Form customization is per-drive, not global
- Students applying after you save changes will see the updated form
- Existing applications are not affected
- You can edit form fields multiple times before the drive closes

---

## Feature 2: Deadline Notification System

### Overview
Admins now receive automatic notifications when drive deadlines expire, reminding them to share applicant lists with companies.

### Notifications Sent

1. **24-Hour Reminder (Before Deadline)**
   - Sent when deadline is within 24 hours
   - Shows time remaining and current application count
   - Helps you prepare for deadline closure

2. **Deadline Expired Notification (After Deadline)**
   - Sent 1 hour after the application deadline passes
   - Includes:
     - Company name and drive number
     - Total number of applications received
     - Direct link to view applicant list
     - Action items reminder (review, prepare list, share with HR)

### How to View Notifications

1. **Notification Bell Icon**
   - Located in the sidebar (below the logo)
   - Shows red badge with count of unread notifications
   - Bell icon rings on hover

2. **Notification Center**
   - Click the bell icon to open the notification center
   - View all notifications with timestamps
   - Notifications are color-coded by type:
     - **Red** (Deadline): Deadline-related
     - **Yellow** (Reminder): Upcoming deadlines
     - **Blue** (Application): Application updates
     - **Gray** (System): System messages

3. **Notification Actions**
   - **View Details**: Click to go to related page (e.g., applicant list)
   - **Mark as Read**: Marks single notification as read
   - **Mark All as Read**: Clears all unread notifications
   - **Delete**: Removes notification permanently

### How Automatic Notifications Work

The notification system works automatically without any background tasks or scheduled jobs!

**How it works:**
1. Every time you load any admin page (dashboard, drives, applications, etc.), the system automatically checks for expired deadlines
2. If a drive's deadline expired more than 1 hour ago, a notification is created
3. The notification appears immediately in the bell icon

**No Setup Required:**
- No Windows Task Scheduler needed
- No cron jobs or background processes
- Simply refresh any admin page to check for new notifications

**When notifications are created:**
- When deadline expired 1+ hour ago (and you haven't been notified yet)
- When deadline is within 24 hours (advance warning)

### Testing the Notification System

To test if notifications are working:

1. Create a test drive with a close date in the past (2 hours ago)
2. Refresh any admin page (or go to dashboard)
3. Check the notification bell - you should see a new notification immediately
4. Click the bell to view the notification details

---

## Database Setup

Before using these features, you need to run the SQL setup script:

### Method 1: Using phpMyAdmin
1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Select database: `admin_placement_db`
3. Click "SQL" tab
4. Open file: `placementcell/sql/create_admin_notifications.sql`
5. Copy and paste the contents
6. Click "Go" to execute

### Method 2: Using MySQL Command Line
```bash
mysql -u root -p admin_placement_db < placementcell/sql/create_admin_notifications.sql
```

This creates:
- `admin_notifications` table: Stores all admin notifications
- `deadline_notifications_sent` table: Prevents duplicate notifications
- Adds `form_fields` column to `drives` table: Stores custom form configuration

---

## Troubleshooting

### Form Fields Not Showing Up
- **Issue**: Custom fields not appearing in student form
- **Solution**: Clear browser cache and reload form page
- **Check**: Verify form_fields column in drives table has JSON data

### Notifications Not Appearing
- **Issue**: No notifications after deadline expires
- **Solution**:
  1. Verify drive deadline is at least 1 hour in the past
  2. Refresh any admin page to trigger the check
  3. Check if `check_deadlines_on_load.php` file exists
  4. Verify database tables exist (admin_notifications, deadline_notifications_sent)
  5. Check phpMyAdmin to see if notification rows were created in admin_notifications table

---

## Technical Details

### Files Created/Modified

**New Files:**
- `admin_notifications.php` - Notification center page
- `edit_form_fields.php` - Form field editor interface
- `check_deadlines_on_load.php` - Automatic deadline checking (runs on page load)
- `sql/create_admin_notifications.sql` - Database setup

**Modified Files:**
- `header.php` - Added notification bell icon and auto-check for deadlines
- `form_generator.php` - Added custom field loading
- `edit_drive.php` - Added "Customize Application Form" button

### Database Schema

**admin_notifications**
```sql
- notification_id (PK)
- admin_id (optional FK to admin_users)
- drive_id (optional FK to drives)
- title (VARCHAR 255)
- message (TEXT)
- type (ENUM: deadline, application, system, reminder)
- is_read (BOOLEAN)
- created_at (TIMESTAMP)
- action_url (VARCHAR 500)
```

**deadline_notifications_sent**
```sql
- id (PK)
- drive_id (FK to drives)
- notification_type (VARCHAR 50)
- sent_at (TIMESTAMP)
- UNIQUE(drive_id, notification_type)
```

**drives** (modified)
```sql
- form_fields (JSON) - New column
  Structure:
  {
    "enabled_fields": {
      "personal": ["Full Name", "Phone No", ...],
      "education": [...],
      "work": [...],
      "others": [...]
    },
    "custom_fields": [
      {
        "name": "Portfolio URL",
        "category": "work",
        "type": "text"
      }
    ]
  }
```

---

## Support

For issues or questions:
1. Check PHP error log (xampp/apache/logs/error.log)
2. Verify database tables and data in phpMyAdmin
3. Test features with sample data first
4. Check browser console for JavaScript errors (F12)
5. Ensure all files exist in the placementcell directory

---

## Future Enhancements (Optional)

Possible improvements:
- Email notifications for deadlines
- SMS notifications
- Configurable notification timing (instead of fixed 1 hour)
- Form templates (save/load common field configurations)
- Bulk edit form fields across multiple drives
- Export notifications as reports
