# Excel Import Guide - Placement Cell System

## Quick Start

1. **Prepare your Excel file** with the correct format and filename
2. **Go to the import page** (Vantage/Internship Registered Students)
3. **Click "Import File"** and select your Excel file
4. **View results** on the page and check logs if needed

---

## File Requirements

### ✅ Filename Format
Your Excel file MUST include the batch year in format: `YYYY-YYYY`

**Examples:**
- ✓ `vantage_students_2025-2026.xlsx`
- ✓ `Vantage_Registered_Students_2024-2025.xlsx`
- ✓ `students_2023-2024.csv`
- ✗ `vantage_students.xlsx` (❌ Missing batch year)
- ✗ `students_2025.xlsx` (❌ Wrong format)

### ✅ File Types Supported
- `.xlsx` (Excel 2007+)
- `.xls` (Excel 97-2003)
- `.csv` (Comma-separated values)

### ✅ Required Columns

Your Excel file must have these columns (headers can vary):

| Column | Accepted Header Names | Required |
|--------|----------------------|----------|
| **Placement ID** | Placement ID, UPID, Placement Key ID | ✓ Yes |
| **Program Type** | Program Type | ✓ Yes |
| **Program** | Program | ✓ Yes |
| **Course** | Course | ✓ Yes |
| **Register Number** | Student Register Number, Register Number, Reg No, Regno | ✓ Yes |
| **Student Name** | Student Name, Name | ✓ Yes |
| **Email** | Student Mail ID, Student Email, Email, Mail ID | ✓ Yes |
| **Phone Number** | Student Phone No, Mobile No, Phone Number | ✓ Yes |
| **Percentage** | Percentage, CGPA, Score | Optional |

---

## Example Excel File Structure

```
| Placement ID      | Program Type | Program                       | Course                | Register Number | Student Name  | Student Mail ID        | Student Phone No | Percentage |
|-------------------|--------------|-------------------------------|-----------------------|-----------------|---------------|------------------------|------------------|------------|
| MCC26VAN_TEST001  | UG           | Bachelor of Business Admin    | BBA - Finance         | MB234567        | John Doe      | john.doe@example.com   | 9876543210       | 85.5       |
| MCC26VAN_TEST002  | UG           | Bachelor of Commerce          | B.Com - Accounting    | MB234568        | Jane Smith    | jane.smith@example.com | 9876543211       | 88.2       |
```

---

## Import Process Flow

```
1. File Upload
   ↓
2. Filename Validation (check for YYYY-YYYY format)
   ↓
3. File Type Validation (.xlsx, .xls, .csv only)
   ↓
4. Parse Excel/CSV File
   ↓
5. Map Headers to Database Columns
   ↓
6. Validate Required Columns
   ↓
7. Check for Duplicate UPIDs
   ↓
8. Insert Valid Rows into Database
   ↓
9. Show Results on Website
```

---

## Understanding Results

### ✅ Success Message
```
Import completed. Inserted: 15 rows
View full import logs →
```
**Meaning:** All 15 rows were successfully imported!

### ⚠️ Warning Message
```
Import completed. Inserted: 10 rows, Skipped: 5 rows (3 duplicates, 2 with missing fields)
Details: Row 3: Duplicate UPID 'MCC25VAN_TEST002' already exists; Row 7: Missing required fields - Email
View full import logs →
```
**Meaning:**
- 10 rows imported successfully
- 5 rows were skipped:
  - 3 rows: Students already exist in database (duplicates)
  - 2 rows: Missing required information (like email)

### ❌ Error Message
```
File upload failed with error code: 1
View full import logs →
```
**Meaning:** The file couldn't be uploaded. Check logs for details.

---

## Common Issues & Solutions

### Issue 1: "Filename must include batch year in format YYYY-YYYY"
**Solution:** Rename your file to include the batch year
```
❌ students.xlsx
✅ students_2025-2026.xlsx
```

### Issue 2: "Missing required column(s): Email, Phone Number"
**Solution:** Check your Excel headers. They must match one of the accepted names.
```
❌ Email Address, Contact Number
✅ Student Mail ID, Student Phone No
```

### Issue 3: "Inserted: 0 rows, Skipped: 50 rows (50 duplicates)"
**Solution:** All students already exist in the database. This is normal for re-imports.

### Issue 4: "Failed to read Excel file"
**Solution:**
- Make sure the file is a valid Excel file
- Try opening it in Excel to check for corruption
- Save it as a new file and try again

---

## Viewing Import Logs

### Method 1: Click the link in the message
After import, click "View full import logs →" in the success/error message

### Method 2: Direct URL
Go to: `http://localhost/placementcell/view_import_logs.php`

### Log Information Includes:
- ✓ File upload details (filename, size, type)
- ✓ Batch year extraction
- ✓ File parsing progress
- ✓ Header mapping results
- ✓ Row-by-row processing
- ✓ Database insertion results
- ✓ Detailed error messages

### Log File Location
**Path:** `C:\xampp\htdocs\placementcell\logs\import_log.txt`

You can open this file directly in any text editor.

---

## Testing the Import

### Step 1: Use the Sample File
A test file already exists: `vantage_students_2026-2027.xlsx`

This file contains 15 test students and is ready to import.

### Step 2: Import the File
1. Go to: **Vantage Registered Students**
2. Click **"Import File"**
3. Select: `vantage_students_2026-2027.xlsx`
4. Click **Upload**

### Step 3: Check Results
You should see:
```
Import completed. Inserted: 15 rows
View full import logs →
```

### Step 4: View Logs
Click "View full import logs →" to see detailed information about what happened.

---

## Troubleshooting Workflow

```
Import Failed?
    ↓
1. Check error message on website
    ↓
2. Click "View full import logs"
    ↓
3. Look for lines with "ERROR" in red
    ↓
4. Common fixes:
   • Filename issue → Rename file with batch year
   • Missing columns → Check Excel headers
   • File corrupt → Re-save in Excel
   • Duplicates → This is expected, not an error
    ↓
5. Fix the issue and try again
    ↓
6. Provide logs to developer if still failing
```

---

## Getting Help

### Information to Provide:
1. **Error message** from the website
2. **Import logs** (download from log viewer page)
3. **Excel file** headers (first row)
4. **Filename** you used

### How to Download Logs:
1. Go to `view_import_logs.php`
2. Click **"Download Logs"** button
3. Send the downloaded file

---

## Database Tables

### Students Table
Imported students are saved to the `students` table with these fields:
- `upid` - Unique Placement ID
- `program_type` - UG/PG
- `program` - Full program name
- `course` - Specific course
- `reg_no` - Registration number
- `student_name` - Full name
- `email` - Email address
- `phone_no` - Contact number
- `batch` - Batch year (e.g., "2025-2026")
- `year_of_passing` - Graduation year
- `percentage` - Academic percentage
- `vantage_participant` - Set to 'yes' for Vantage imports

### Duplicate Detection
The system checks `upid` field to prevent duplicate imports. If a student with the same UPID already exists, that row is skipped.

---

## System Requirements

### Server:
- PHP 7.4 or higher
- MySQL 5.7 or higher
- PhpSpreadsheet library (already installed)

### Browser:
- Any modern browser (Chrome, Firefox, Edge, Safari)

### File Limits:
- Maximum file size: 2048 MB (2 GB)
- Maximum rows: Unlimited (but keep under 10,000 for best performance)
- Timeout: 600 seconds (10 minutes)

---

## Best Practices

### ✓ DO:
- Use consistent naming for files (e.g., `vantage_students_YYYY-YYYY.xlsx`)
- Keep Excel files clean (no merged cells, no formulas)
- Test with a small file first (5-10 rows)
- Check logs after every import
- Download and save logs for record keeping

### ✗ DON'T:
- Import the same file twice (causes duplicates)
- Use special characters in filenames
- Merge cells in Excel
- Leave required fields empty
- Import without checking the results

---

## Advanced: Log Analysis

### Log Entry Format:
```
[2026-02-16 10:30:45] File received: vantage_students_2025-2026.xlsx (Size: 7.77 KB, Type: xlsx)
```

### Log Colors in Viewer:
- 🔴 **Red** - ERROR (critical issues)
- 🟢 **Green** - SUCCESS (completed operations)
- 🟡 **Yellow** - WARNING (skipped rows)
- 🔵 **Blue** - INFO (general information)

### Key Log Sections:
1. **File Upload** - Confirms file received
2. **Filename Validation** - Checks batch year format
3. **File Parsing** - Reads Excel/CSV content
4. **Header Mapping** - Maps columns to database fields
5. **Duplicate Check** - Finds existing students
6. **Row Processing** - Inserts each student
7. **Final Results** - Summary statistics

---

## Support

For technical support or bug reports, contact the system administrator or check the logs for detailed error information.

**Remember:** The logs are your friend! They contain all the information needed to diagnose any import issues.
