import sys
import os

sys.path.append(os.path.join(os.path.dirname(__file__), 'model'))

from parser import parse_input
from db import save_transaction, fetch_all_transactions


def print_banner():
    print("""
╔══════════════════════════════════════╗
║       AI FINANCE TRACKER v1.0       ║
║   Ketik transaksi dalam bahasa       ║
║   Indonesia. Contoh:                 ║
║   > beli pentol 20k                  ║
║   > gajian 3jt                       ║
║   Ketik 'list' untuk lihat semua     ║
║   Ketik 'exit' untuk keluar          ║
╚══════════════════════════════════════╝
""")


def print_transaction(t):
    sign   = "+" if t["type"] == "income" else "-"
    color  = "" 
    print(f"""
  ┌─────────────────────────────────┐
  │ 📝 {t['description'][:33]:<33}│
  │ 💰 {sign}Rp {t['amount']:>10,.0f}              │
  │ 🏷  {t['type']:<8}  📂 {t['category']:<12}  │
  └─────────────────────────────────┘""")


def cmd_list():
    rows = fetch_all_transactions()
    if not rows:
        print("  (belum ada transaksi)")
        return

    total_income  = sum(r["amount"] for r in rows if r["type"] == "income")
    total_expense = sum(r["amount"] for r in rows if r["type"] == "expense")
    balance       = total_income - total_expense

    print(f"\n  {'='*40}")
    print(f"  Total Income : Rp {total_income:>12,.0f}")
    print(f"  Total Expense: Rp {total_expense:>12,.0f}")
    print(f"  Balance      : Rp {balance:>12,.0f}")
    print(f"  {'='*40}\n")

    for r in rows[:10]:  
        print(f"  [{r['created_at']}] {r['description'][:25]:<25} "
              f"{'+'if r['type']=='income' else '-'}Rp {r['amount']:>10,.0f}  [{r['category']}]")


def main():
    print_banner()

    while True:
        try:
            user_input = input(">> ").strip()
        except (KeyboardInterrupt, EOFError):
            print("\n👋 Sampai jumpa!")
            break

        if not user_input:
            continue

        if user_input.lower() == "exit":
            print("👋 Sampai jumpa!")
            break

        if user_input.lower() == "list":
            cmd_list()
            continue

        result = parse_input(user_input)

        if result["amount"] == 0:
            print("  ⚠️  Nominal tidak ditemukan. Contoh: 'beli kopi 15k'")
            continue

        new_id = save_transaction(
            result["description"],
            result["amount"],
            result["type"],
            result["category"],
        )

        print(f"\n  ✅ Tersimpan! (ID: {new_id})")
        print_transaction(result)


if __name__ == "__main__":
    main()