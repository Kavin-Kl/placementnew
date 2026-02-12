import pandas as pd
import pymysql
from datetime import datetime

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
print(f"Reading Excel file: {excel_file}")

# Get sheet names
xl = pd.ExcelFile(excel_file)
print(f"Available sheets: {xl.sheet_names}")

# Read placed_students sheet
df = pd.read_excel(excel_file, sheet_name='Overall_Placed_Students')
print(f"\nFound {len(df)} placed students records")
print(f"Columns: {df.columns.tolist()}")

# Clear existing data
cursor.execute("DELETE FROM placed_students")
print(f"\nCleared existing placed_students table")

# Insert data
inserted = 0
skipped = 0

for index, row in df.iterrows():
    try:
        # Prepare values
        upid = str(row.get('upid', '')) if pd.notna(row.get('upid')) else None
        student_name = str(row.get('student_name', '')) if pd.notna(row.get('student_name')) else None
        company_name = str(row.get('company_name', '')) if pd.notna(row.get('company_name')) else None

        if not upid or not student_name:
            skipped += 1
            continue

        # Get all columns
        values = {}
        for col in df.columns:
            val = row.get(col)
            if pd.notna(val):
                if col in ['placed_date', 'joining_date']:
                    # Handle dates
                    if isinstance(val, str):
                        try:
                            values[col] = datetime.strptime(val, '%Y-%m-%d %H:%M:%S')
                        except:
                            try:
                                values[col] = datetime.strptime(val, '%Y-%m-%d')
                            except:
                                values[col] = None
                    else:
                        values[col] = val
                elif col in ['ctc', 'stipend']:
                    # Handle numeric fields
                    try:
                        values[col] = float(val)
                    except:
                        values[col] = None
                elif col in ['drive_id', 'role_id']:
                    # Handle integer fields
                    try:
                        values[col] = int(val)
                    except:
                        values[col] = None
                else:
                    values[col] = str(val)
            else:
                values[col] = None

        # Build INSERT query dynamically
        columns = ', '.join(values.keys())
        placeholders = ', '.join(['%s'] * len(values))
        query = f"INSERT INTO placed_students ({columns}) VALUES ({placeholders})"

        cursor.execute(query, list(values.values()))
        inserted += 1

        if inserted % 50 == 0:
            print(f"Processed {inserted} records...")

    except Exception as e:
        print(f"Error inserting row {index}: {e}")
        skipped += 1
        continue

conn.commit()
print(f"\n✓ Import completed!")
print(f"  Inserted: {inserted}")
print(f"  Skipped: {skipped}")

# Verify
cursor.execute("SELECT COUNT(*) FROM placed_students")
count = cursor.fetchone()[0]
print(f"\nTotal placed_students in database: {count}")

cursor.close()
conn.close()
