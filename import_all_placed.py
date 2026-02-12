import pandas as pd
import pymysql

# Database connection
conn = pymysql.connect(
    host="127.0.0.1",
    port=3308,
    user="root",
    password="",
    database="admin_placement_db"
)
cursor = conn.cursor()

# Read Excel file
excel_file = r"C:\Users\Kavin\Downloads\placement_backup_all (1).xlsx"
print(f"Reading: {excel_file}")

df = pd.read_excel(excel_file, sheet_name='On_Campus_Placed_Students')
print(f"Found {len(df)} placed students")
print(f"Columns: {list(df.columns)[:10]}")

# Clear existing data
cursor.execute("TRUNCATE TABLE placed_students")
print("Cleared existing data")

inserted = 0
skipped = 0

for index, row in df.iterrows():
    try:
        # Get UPID - try different column names
        upid = None
        for col in ['upid', 'Placement_id', 'UPID', 'placement_id']:
            if col in df.columns and pd.notna(row.get(col)):
                upid = str(row[col]).strip()
                break

        # Get student name
        student_name = None
        for col in ['student_name', 'full_name', 'name', 'Student Name']:
            if col in df.columns and pd.notna(row.get(col)):
                student_name = str(row[col]).strip()
                break

        if not upid or not student_name:
            print(f"Row {index}: Missing UPID or name")
            skipped += 1
            continue

        # Get other fields
        reg_no = str(row.get('reg_no', '')).strip() if pd.notna(row.get('reg_no')) else None
        email = str(row.get('email', '')).strip() if pd.notna(row.get('email')) else None
        phone_no = str(row.get('phone_no', '')).strip() if pd.notna(row.get('phone_no')) else None
        company_name = str(row.get('company_name', '')).strip() if pd.notna(row.get('company_name')) else None

        # Try different role column names
        role = None
        for col in ['role', 'designation', 'designation_name']:
            if col in df.columns and pd.notna(row.get(col)):
                role = str(row[col]).strip()
                break

        course = str(row.get('course', '')).strip() if pd.notna(row.get('course')) else None

        # CTC and stipend
        ctc = str(row.get('ctc', '')).strip() if pd.notna(row.get('ctc')) else None
        stipend = str(row.get('stipend', '')).strip() if pd.notna(row.get('stipend')) else None

        # Offer type
        offer_type = str(row.get('offer_type', 'FTE')).strip() if pd.notna(row.get('offer_type')) else 'FTE'

        # Optional fields
        offer_letter_received = str(row.get('offer_letter_received', 'unknown')).strip().lower() if pd.notna(row.get('offer_letter_received')) else 'unknown'
        if offer_letter_received not in ['yes', 'no', 'unknown']:
            offer_letter_received = 'unknown'

        comments = str(row.get('comments', '')).strip() if pd.notna(row.get('comments')) else None

        query = """
        INSERT INTO placed_students
        (upid, student_name, reg_no, email, phone_no, company_name, role, course, ctc, stipend,
         offer_letter_received, comment, placement_batch, offer_type)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        """

        cursor.execute(query, (
            upid, student_name, reg_no, email, phone_no, company_name, role, course, ctc, stipend,
            offer_letter_received, comments, 'original', offer_type
        ))

        inserted += 1

        if inserted % 50 == 0:
            print(f"Processed {inserted}...")

    except Exception as e:
        print(f"Error at row {index}: {e}")
        skipped += 1

conn.commit()
print(f"\nCompleted!")
print(f"Inserted: {inserted}")
print(f"Skipped: {skipped}")

# Now link student_id
print("\nLinking student_id...")
cursor.execute("""
UPDATE placed_students ps
JOIN students s ON ps.upid = s.upid
SET ps.student_id = s.student_id
WHERE ps.student_id IS NULL
""")
linked = cursor.rowcount
print(f"Linked {linked} records to students")

conn.commit()

# Verify
cursor.execute("SELECT COUNT(*) FROM placed_students")
count = cursor.fetchone()[0]
cursor.execute("SELECT COUNT(*) FROM placed_students WHERE student_id IS NOT NULL")
with_student = cursor.fetchone()[0]
print(f"\nTotal in database: {count}")
print(f"With student_id: {with_student}")

cursor.close()
conn.close()
