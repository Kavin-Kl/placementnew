import pandas as pd
import pymysql

# Connect to database
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
df = pd.read_excel(excel_file, sheet_name='Applications')

print(f"Processing {len(df)} applications from Excel...")

updated_count = 0
not_found_count = 0

for index, row in df.iterrows():
    try:
        upid = str(row['Placement_id']).strip() if pd.notna(row.get('Placement_id')) else None
        company_name = str(row['company_name']).strip() if pd.notna(row.get('company_name')) else None
        designation_name = str(row['designation_name']).strip() if pd.notna(row.get('designation_name')) else None

        if not upid or not company_name or not designation_name:
            continue

        # Find matching drive_id and role_id
        query = """
            SELECT d.drive_id, dr.role_id
            FROM drives d
            JOIN drive_roles dr ON d.drive_id = dr.drive_id
            WHERE REPLACE(TRIM(d.company_name), ' ', '') = REPLACE(TRIM(%s), ' ', '')
            AND REPLACE(TRIM(dr.designation_name), ' ', '') = REPLACE(TRIM(%s), ' ', '')
            LIMIT 1
        """

        cursor.execute(query, (company_name, designation_name))
        result = cursor.fetchone()

        if result:
            drive_id, role_id = result

            # Update applications table
            update_query = """
                UPDATE applications
                SET role_id = %s
                WHERE upid = %s AND drive_id = %s AND role_id IS NULL
            """
            cursor.execute(update_query, (role_id, upid, drive_id))

            if cursor.rowcount > 0:
                updated_count += cursor.rowcount
        else:
            not_found_count += 1
            if not_found_count <= 10:  # Print first 10 not found
                print(f"Not found: {company_name} - {designation_name}")

        # Commit every 100 rows
        if (index + 1) % 100 == 0:
            conn.commit()
            print(f"Processed {index + 1}/{len(df)} rows...")

    except Exception as e:
        print(f"Error at row {index}: {e}")
        continue

# Final commit
conn.commit()

print(f"\nSummary:")
print(f"Updated: {updated_count}")
print(f"Not found: {not_found_count}")

# Check final count
cursor.execute("SELECT COUNT(*) FROM applications WHERE role_id IS NOT NULL")
total_with_role = cursor.fetchone()[0]
print(f"Total applications with role_id: {total_with_role}")

cursor.close()
conn.close()
