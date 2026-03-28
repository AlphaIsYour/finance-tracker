import mysql.connector

DB_CONFIG = {
    "host":     "localhost",
    "user":     "root",
    "password": "",           
    "database": "ai_finance_tracker",
    "port":     3307,
}

def get_connection():
    return mysql.connector.connect(**DB_CONFIG)


def save_transaction(description, amount, type_, category):
    """
    Insert one transaction into the DB.
    Returns the new row's ID.
    """
    conn   = get_connection()
    cursor = conn.cursor()

    sql = """
        INSERT INTO transactions (description, amount, type, category)
        VALUES (%s, %s, %s, %s)
    """
    cursor.execute(sql, (description, amount, type_, category))
    conn.commit()

    new_id = cursor.lastrowid
    cursor.close()
    conn.close()
    return new_id


def fetch_all_transactions():
    """
    Fetch all transactions ordered by newest first.
    """
    conn   = get_connection()
    cursor = conn.cursor(dictionary=True)

    cursor.execute("""
        SELECT id, description, amount, type, category, created_at
        FROM transactions
        ORDER BY created_at DESC
    """)
    rows = cursor.fetchall()

    cursor.close()
    conn.close()
    return rows


# ── Quick connection test ─────────────────────────────────────────────────
if __name__ == "__main__":
    try:
        conn = get_connection()
        print("✅ Database connected successfully!")
        conn.close()
    except Exception as e:
        print(f"❌ Connection failed: {e}")