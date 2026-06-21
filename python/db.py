import mysql.connector
from mysql.connector import pooling
import sys

sys.stdout.reconfigure(encoding='utf-8')

DB_CONFIG = {
    "host":     "localhost",
    "user":     "root",
    "password": "",
    "database": "ai_finance_tracker",
    "port":     3307,
    "charset":  "utf8mb4",
    "autocommit": True,
}

# ── Connection Pool ─────────────────────────────────────────────────────────
_pool = None

def get_pool():
    """Get or create the connection pool."""
    global _pool
    if _pool is None:
        try:
            _pool = pooling.MySQLConnectionPool(
                pool_name="finance_pool",
                pool_size=3,
                pool_reset_session=True,
                **DB_CONFIG
            )
        except mysql.connector.Error as e:
            print(f"❌ Connection pool error: {e}")
            # Fallback to direct connection
            return None
    return _pool


def get_connection():
    """Get a connection from the pool."""
    pool = get_pool()
    if pool:
        try:
            return pool.get_connection()
        except mysql.connector.Error:
            pass
    # Fallback: direct connection
    return mysql.connector.connect(**DB_CONFIG)


def save_transaction(description, amount, type_, category):
    """
    Insert one transaction into the DB.
    Returns the new row's ID.
    """
    conn = None
    try:
        conn = get_connection()
        cursor = conn.cursor()
        sql = """
            INSERT INTO transactions (description, amount, type, category)
            VALUES (%s, %s, %s, %s)
        """
        cursor.execute(sql, (description, amount, type_, category))
        conn.commit()
        new_id = cursor.lastrowid
        cursor.close()
        return new_id
    except mysql.connector.Error as e:
        print(f"❌ DB Error (save): {e}")
        return None
    finally:
        if conn:
            conn.close()


def update_transaction(tx_id, description, amount, type_, category):
    """
    Update an existing transaction.
    Returns True if successful.
    """
    conn = None
    try:
        conn = get_connection()
        cursor = conn.cursor()
        sql = """
            UPDATE transactions
            SET description=%s, amount=%s, type=%s, category=%s
            WHERE id=%s
        """
        cursor.execute(sql, (description, amount, type_, category, tx_id))
        conn.commit()
        affected = cursor.rowcount
        cursor.close()
        return affected > 0
    except mysql.connector.Error as e:
        print(f"❌ DB Error (update): {e}")
        return False
    finally:
        if conn:
            conn.close()


def delete_transaction(tx_id):
    """
    Delete a transaction by ID.
    Returns True if successful.
    """
    conn = None
    try:
        conn = get_connection()
        cursor = conn.cursor()
        cursor.execute("DELETE FROM transactions WHERE id=%s", (tx_id,))
        conn.commit()
        affected = cursor.rowcount
        cursor.close()
        return affected > 0
    except mysql.connector.Error as e:
        print(f"❌ DB Error (delete): {e}")
        return False
    finally:
        if conn:
            conn.close()


def fetch_all_transactions(limit=100):
    """
    Fetch all transactions ordered by newest first.
    """
    conn = None
    try:
        conn = get_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("""
            SELECT id, description, amount, type, category, created_at
            FROM transactions
            ORDER BY created_at DESC
            LIMIT %s
        """, (limit,))
        rows = cursor.fetchall()
        cursor.close()
        return rows
    except mysql.connector.Error as e:
        print(f"❌ DB Error (fetch): {e}")
        return []
    finally:
        if conn:
            conn.close()


def fetch_transaction_by_id(tx_id):
    """Fetch a single transaction by ID."""
    conn = None
    try:
        conn = get_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT * FROM transactions WHERE id=%s", (tx_id,))
        row = cursor.fetchone()
        cursor.close()
        return row
    except mysql.connector.Error as e:
        print(f"❌ DB Error (fetch_by_id): {e}")
        return None
    finally:
        if conn:
            conn.close()


def get_summary():
    """
    Get financial summary: total income, expense, balance, count.
    """
    conn = None
    try:
        conn = get_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("""
            SELECT
                SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) AS total_income,
                SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS total_expense,
                COUNT(*) AS total_tx
            FROM transactions
        """)
        row = cursor.fetchone()
        cursor.close()

        income  = float(row['total_income'] or 0)
        expense = float(row['total_expense'] or 0)
        return {
            "total_income":  income,
            "total_expense": expense,
            "balance":       income - expense,
            "total_tx":      int(row['total_tx'] or 0),
        }
    except mysql.connector.Error as e:
        print(f"❌ DB Error (summary): {e}")
        return {"total_income": 0, "total_expense": 0, "balance": 0, "total_tx": 0}
    finally:
        if conn:
            conn.close()


def get_category_breakdown():
    """
    Get expense breakdown by category.
    """
    conn = None
    try:
        conn = get_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("""
            SELECT category, COUNT(*) as cnt, SUM(amount) as total
            FROM transactions
            WHERE type = 'expense'
            GROUP BY category
            ORDER BY total DESC
        """)
        rows = cursor.fetchall()
        cursor.close()
        return rows
    except mysql.connector.Error as e:
        print(f"❌ DB Error (breakdown): {e}")
        return []
    finally:
        if conn:
            conn.close()


# ── Quick connection test ─────────────────────────────────────────────────
if __name__ == "__main__":
    try:
        conn = get_connection()
        print("✅ Database connected successfully!")
        print(f"   Host: {DB_CONFIG['host']}:{DB_CONFIG['port']}")
        print(f"   Database: {DB_CONFIG['database']}")

        # Test summary
        s = get_summary()
        print(f"\n📊 Summary:")
        print(f"   Income  : Rp {s['total_income']:,.0f}")
        print(f"   Expense : Rp {s['total_expense']:,.0f}")
        print(f"   Balance : Rp {s['balance']:,.0f}")
        print(f"   Total   : {s['total_tx']} transaksi")

        conn.close()
    except Exception as e:
        print(f"❌ Connection failed: {e}")
