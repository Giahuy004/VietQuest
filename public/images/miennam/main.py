import pandas as pd
import mysql.connector

# Đường dẫn file CSV
excel_path = "miennam.csv"

# Đọc dữ liệu từ file CSV
df = pd.read_csv(excel_path)

# Thay thế NaN bằng None để tương thích với MySQL
df = df.where(pd.notnull(df), None)

# Tạo kết nối tới database MySQL
conn = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",
    database="vietquestdb",
)
cursor = conn.cursor()

# Lặp qua từng dòng trong DataFrame và tạo câu lệnh INSERT
for index, row in df.iterrows():
    try:
        sql = """
        INSERT INTO Question (image_name, correct_lat, correct_lng, image_url, description, created_id)
        VALUES (%s, %s, %s, %s, %s, NULL)
        """
        values = (
            row["image_name"],
            float(row["correct_lat"]) if row["correct_lat"] is not None else None,
            float(row["correct_lng"]) if row["correct_lng"] is not None else None,
            row["image_url"],
            row["description"]
        )
        cursor.execute(sql, values)
    except Exception as e:
        print(f"❌ Error inserting row {index + 1}: {e}")

# Commit và đóng kết nối
conn.commit()
cursor.close()
conn.close()

print("✅ Done inserting all records.")
