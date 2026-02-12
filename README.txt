# Placement Cell – Deployment Guide

## Steps to Deploy

1. **Upload Project**
   - Copy the project folder to the server’s `htdocs` or `public_html` directory.
   - The folder name can be changed (system works independently of folder name).

2. **Database Setup**
   - Create a new MySQL database.
   - Import the SQL file provided in `/database/admin_placement_db.sql`.

3. **Configure Database Credentials**
   - Open `config.php`.
   - Update the following values with server credentials:
     ```php
     $host = "localhost";   // Database host
     $user = "DB_USERNAME"; // Server username
     $pass = "DB_PASSWORD"; // Server password
     $db   = "DB_NAME";     // Database name
     ```

4. **Access the Portal**
   - Navigate to:
     ```
     http://yourdomain.com/<folder_name>/
     ```
   - Or, if placed in root:
     ```
     http://yourdomain.com/
     ```

## Notes
- Password reset links are dynamic and work with any domain/folder.
- Ensure `/uploads/` folder has write permission for resume uploads.
