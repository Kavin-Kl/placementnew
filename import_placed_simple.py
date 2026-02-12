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

df = pd.read_excel(excel_file, sheet_name='Overall_Placed_Students')
print(f"Found {len(df)} placed students")

# Clear existing data
cursor.execute("TRUNCATE TABLE placed_students")
print("Cleared existing data")

inserted = 0
skipped = 0

for index, row in df.iterrows():
    try:
        # Map Excel columns to database columns
        upid = str(row['Placement_id']).strip() if pd.notna(row['Placement_id']) else None
        student_name = str(row['full_name']).strip() if pd.notna(row['full_name']) else None
        reg_no = str(row['reg_no']).strip() if pd.notna(row['reg_no']) else None
        email = str(row['email']).strip() if pd.notna(row['email']) else None
        company_name = str(row['company_name']).strip() if pd.notna(row['company_name']) else None
        role = str(row['role']).strip() if pd.notna(row['role']) else None
        course = str(row['course_name']).strip() if pd.notna(row['course_name']) else None
        phone_no = str(row['phone_no']).strip() if pd.notna(row['phone_no']) else None

        # Optional fields
        offer_letter_received = str(row['offer_letter_received']).strip().lower() if pd.notna(row['offer_letter_received']) else 'unknown'
        if offer_letter_received not in ['yes', 'no', 'unknown']:
            offer_letter_received = 'unknown'

        comments = str(row['comments']).strip() if pd.notna(row['comments']) else None

        if not upid or not student_name:
            skipped += 1
            continue

        query = """
        INSERT INTO placed_students
        (upid, student_name, reg_no, email, phone_no, company_name, role, course,
         offer_letter_received, comment, placement_batch)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        """

        cursor.execute(query, (
            upid, student_name, reg_no, email, phone_no, company_name, role, course,
            offer_letter_received, comments, 'original'
        ))

        inserted += 1

        if inserted % 20 == 0:
            print(f"Processed {inserted}...")

    except Exception as e:
        print(f"Error at row {index}: {e}")
        skipped += 1

conn.commit()
print(f"\nCompleted!")
print(f"Inserted: {inserted}")
print(f"Skipped: {skipped}")

# Verify
cursor.execute("SELECT COUNT(*) FROM placed_students")
count = cursor.fetchone()[0]
print(f"Total in database: {count}")

cursor.close()
conn.close()
