# 🧪 Testing Guide for Placement Cell Updates

## 📌 Pre-Testing Setup

### Step 1: Start XAMPP Services

1. Open **XAMPP Control Panel** (should be running from earlier)
   - If not, run: `C:\xampp\xampp-control.exe`

2. Start these services:
   - ✅ **Apache** - Click "Start" button
   - ✅ **MySQL** - Click "Start" button

3. Verify they're running (buttons should show "Stop" and highlight in green)

### Step 2: Run Database Migration

**IMPORTANT:** This adds the `company_sector` column to your database.

1. Open your browser
2. Go to: `http://localhost/placementcell/migration_add_company_sector.php`
3. You should see: "✓ Successfully added company_sector column to drives table"
4. If you see "Column already exists" - that's fine, proceed!

---

## 🎯 Testing Each Feature

### **TEST 1: Block Student from Registered Tabs**

**Location:** Registered Students Tab

1. Navigate to: `http://localhost/placementcell/dashboard.php`
2. Login with your admin credentials
3. Click **"Placement Registered Students"** in left menu
4. Scroll through the student list
5. Find any student and click the **🚫 Block button** (yellow color)
6. Confirm the popup
7. ✅ **Expected:**
   - Success toast message appears
   - Student status changes to "blocked" in the table
   - Student is blocked from all future applications

**Also test in:** Internship Registered Students tab (same button should appear)

---

### **TEST 2: Company Sector Field**

**Location:** Add Drive Page

1. Click **"Add Drive"** in left menu
2. Fill in the form:
   - Created By: Your Name
   - **Company Name:** Test Company XYZ
   - **Company Sector:** Select any option (IT, BFSI, Consulting, etc.)
   - Form Open Date: Select today
   - Form Close Date: Select tomorrow
   - Academic Year: 2025-2026
   - Add at least one role with details

3. Click **Submit**

4. ✅ **Expected:** Drive created successfully

5. **Verify:** Go to Edit Drive and check if Company Sector is pre-selected

**Edit Test:**
1. Click any drive's "Edit" button
2. Check if **Company Sector** dropdown appears below Company Name
3. Change the sector
4. Save
5. ✅ **Expected:** Sector updates successfully

---

### **TEST 3: Data Shared Button in Notifications**

**Location:** Admin Notifications

1. Click **"Notifications"** (🔔 bell icon) in left menu
2. Look at any unread notification
3. ✅ **Expected:** Button says **"Data Shared"** instead of "Mark as Read"
4. Click the button
5. ✅ **Expected:** Notification marked as shared
6. Check top button: Should say **"Mark All Data Shared"**

---

### **TEST 4: Internship Letter Collection Tab**

**Location:** Navigation Menu

1. Look at left sidebar menu
2. Find **"Internship Letter Collection"** menu item (📄 icon)
3. Click it
4. ✅ **Expected:**
   - Page opens successfully
   - Similar to Offer Letter Collection but for internships
   - Form generation interface appears

---

### **TEST 5: Academic Year Switching**

**Location:** Dashboard

1. Go to Dashboard: `http://localhost/placementcell/dashboard.php`
2. Look at top-right corner for **Academic Year dropdown**
3. Note current numbers:
   - Total Registered Students: _____
   - Total Companies: _____
   - Placed Students: _____
   - Internship Placed: _____

4. **Change academic year** to a different year (e.g., 2024-2025)
5. ✅ **Expected:**
   - ALL numbers should update/change
   - If no data exists for that year, numbers should show 0
   - Drives list should filter by that year

6. **Test Reset:**
   - Change back to 2025-2026
   - Numbers should return to original values

---

### **TEST 6: Internship Dashboard (No Final Year)**

**Location:** Dashboard

1. Go to Dashboard
2. Find the **"Internship Placed"** card (red/maroon color)
3. ✅ **Expected:**
   - Shows only: **"Vantage: X"**
   - Should NOT show: "Final Year: X"
   - Only 2 lines: Total number and Vantage count

---

### **TEST 7: Company Bulk Import**

**Location:** Import Companies Page

#### Part A: Prepare Test File

1. Create a CSV file named `test_companies.csv` with this content:

```csv
Company Name,Company Sector,Role,Salary,Deadline
TechCorp India,IT,Software Engineer,12 LPA,2026-04-30
DataSystems LLC,Analytics,Data Analyst,8 LPA,2026-05-15
FinanceHub,BFSI,Financial Analyst,10 LPA,2026-05-20
ConsultPro,Consulting,Business Consultant,15 LPA,2026-06-01
```

2. Save this file to your Desktop

#### Part B: Test Import

1. Click **"Import Companies"** in left menu (☁️ upload icon)
2. Click **"Choose File"** button
3. Select your `test_companies.csv` file
4. Click **"Import Companies"** button
5. ✅ **Expected:**
   - Success message: "Import completed. Inserted: 4 companies"
   - Page shows green success banner

6. **Verify:**
   - Go to Dashboard
   - Check if new companies appear in drives list
   - Click "Edit" on any imported company
   - Verify Company Sector is filled

---

### **TEST 8: Dashboard Pagination (Finished Section)**

**Location:** Dashboard - Finished Tab

#### Part A: Check Finished Drives

1. Go to Dashboard
2. Click **"Finished"** tab (3rd tab)
3. ✅ **Expected:**
   - If you have more than 10 finished drives:
     - Only 10 drives are shown
     - Pagination controls appear at bottom
     - Shows: "Page 1 of X (Total: Y drives)"

4. **Test Navigation:**
   - Click **"Next »"** button
   - ✅ **Expected:** Shows drives 11-20
   - Click **"« Previous"** button
   - ✅ **Expected:** Returns to drives 1-10

#### Part B: Test Pagination Parameters

1. Look at URL when on page 2
2. Should show: `?finished_page=2`
3. Manually change to `?finished_page=3` in URL
4. ✅ **Expected:** Shows page 3 content

---

### **TEST 9: Applications Tab Pagination**

**Location:** Enrolled Students (Applications List)

1. Click **"Applications List"** in left menu
2. Select any company/drive
3. ✅ **Expected:**
   - Shows registered students for that drive
   - Already has pagination (LIMIT/OFFSET implemented)
   - Lazy loading works as you scroll

**Note:** This tab already had pagination infrastructure, so it's working!

---

## 🐛 Troubleshooting Common Issues

### Issue 1: "Can't connect to MySQL"
**Solution:**
- Stop MySQL in XAMPP
- Wait 5 seconds
- Start MySQL again
- Port should be 3308

### Issue 2: "Table doesn't exist" or "Unknown column 'company_sector'"
**Solution:**
- Run migration: `http://localhost/placementcell/migration_add_company_sector.php`
- If error persists, check MySQL service is running

### Issue 3: Block button doesn't work
**Solution:**
- Check browser console (F12) for JavaScript errors
- Verify `block_student.php` file exists in root folder
- Check if you're logged in as admin

### Issue 4: Import Companies shows "Invalid file type"
**Solution:**
- Ensure file extension is .csv, .xls, or .xlsx
- Check file is not corrupted
- Try creating a new CSV with UTF-8 encoding

### Issue 5: Pagination doesn't show
**Solution:**
- Need at least 11 finished drives to see pagination
- Check Finished tab is selected
- Look for pagination controls below drive cards

### Issue 6: Academic year switch doesn't work
**Solution:**
- Clear browser cache
- Check if `$_SESSION['selected_academic_year']` is set
- Verify students have `year_of_passing` values

---

## ✅ Success Criteria Checklist

After testing, verify all these work:

- [ ] Block button appears in registered tabs
- [ ] Blocking a student shows success message
- [ ] Company sector dropdown appears in Add Drive
- [ ] Company sector saves and displays in Edit Drive
- [ ] Notifications button says "Data Shared"
- [ ] Internship Letter Collection menu item exists and opens
- [ ] Academic year switch updates all dashboard numbers
- [ ] Internship card shows only Total + Vantage (no Final Year)
- [ ] Company import accepts CSV/Excel files
- [ ] Import shows success message with count
- [ ] Finished section shows pagination with 10 items per page
- [ ] Pagination Next/Previous buttons work
- [ ] Applications tab loads successfully

---

## 📊 Quick Test URLs

Copy these into your browser (replace localhost if different):

1. **Dashboard:** `http://localhost/placementcell/dashboard.php`
2. **Migration:** `http://localhost/placementcell/migration_add_company_sector.php`
3. **Add Drive:** `http://localhost/placementcell/add_drive.php`
4. **Registered Students:** `http://localhost/placementcell/registered_students.php`
5. **Notifications:** `http://localhost/placementcell/admin_notifications.php`
6. **Import Companies:** `http://localhost/placementcell/import_companies.php`
7. **Internship Letters:** `http://localhost/placementcell/internship_offer_letters.php`

---

## 💡 Tips

1. **Use Browser DevTools (F12):**
   - Console tab: Check for JavaScript errors
   - Network tab: See AJAX requests

2. **Check PHP Errors:**
   - Look at: `C:\xampp\apache\logs\error.log`

3. **Database Verification:**
   - Open phpMyAdmin: `http://localhost/phpmyadmin`
   - Check `admin_placement_db` database
   - Verify `drives` table has `company_sector` column

4. **Test with Multiple Students:**
   - Import student CSV if you have limited data
   - Use different academic years

---

## 🎉 All Tests Passed?

If everything works, you're done!

**Next Steps:**
- Train your team on new features
- Update user documentation
- Monitor for any edge cases

**Need Help?**
- Check error logs: `C:\xampp\apache\logs\error.log`
- Check import logs: `C:\xampp\htdocs\placementcell\logs\`
- Review code comments in modified files

---

*Generated: <?= date('Y-m-d H:i:s') ?>*
*Version: 1.0*
