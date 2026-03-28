import sys
sys.stdout.reconfigure(encoding='utf-8')
import sys
import os
import json

sys.path.append(os.path.join(os.path.dirname(__file__), 'model'))

from parser import parse_input
from db import save_transaction, fetch_all_transactions

def get_summary():
    rows = fetch_all_transactions()
    total_income  = sum(r["amount"] for r in rows if r["type"] == "income")
    total_expense = sum(r["amount"] for r in rows if r["type"] == "expense")
    balance       = total_income - total_expense
    return {
        "total_income":  total_income,
        "total_expense": total_expense,
        "balance":       balance,
        "total_tx":      len(rows)
    }

def handle_command(text):
    text_clean = text.strip()

    # ── Saldo / summary ──────────────────────────────────────────────────
    if text_clean.lower() in ["saldo", "balance", "rekap", "summary"]:
        s = get_summary()
        return (
            f"📊 *Rekap Keuangan*\n"
            f"💚 Income  : Rp {s['total_income']:,.0f}\n"
            f"❤️ Expense : Rp {s['total_expense']:,.0f}\n"
            f"💰 Balance : Rp {s['balance']:,.0f}\n"
            f"📋 Total   : {s['total_tx']} transaksi"
        )

    # ── Riwayat terakhir ─────────────────────────────────────────────────
    if text_clean.lower() in ["riwayat", "history", "terakhir"]:
        rows = fetch_all_transactions()[:5]
        if not rows:
            return "📭 Belum ada transaksi."
        lines = ["📋 *5 Transaksi Terakhir:*"]
        for r in rows:
            sign = "+" if r["type"] == "income" else "-"
            lines.append(
                f"{sign}Rp {r['amount']:,.0f} · {r['category']} · {r['description'][:20]}"
            )
        return "\n".join(lines)

    # ── Transaksi biasa ──────────────────────────────────────────────────
    result = parse_input(text_clean)

    if result["amount"] == 0:
        return (
            f"⚠️ Nominal tidak ditemukan.\n"
            f"Contoh: *~beli pentol 20k* atau *~gajian 3jt*"
        )

    save_transaction(
        result["description"],
        result["amount"],
        result["type"],
        result["category"],
    )

    icon_map = {
        "makan": "🍽️", "minum": "☕", "transport": "🚗",
        "hiburan": "🎬", "income": "💰", "tagihan": "🧾",
        "belanja": "🛍️", "lainnya": "📦"
    }
    icon = icon_map.get(result["category"], "📦")
    sign = "+" if result["type"] == "income" else "-"

    # Saldo terkini setelah transaksi
    s = get_summary()

    return (
        f"✅ *Transaksi Tercatat!*\n"
        f"📝 {result['description']}\n"
        f"{icon} Kategori : {result['category']}\n"
        f"💸 Nominal  : {sign}Rp {result['amount']:,.0f}\n"
        f"💰 Saldo skrg: Rp {s['balance']:,.0f}"
    )

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python process.py '<pesan>'")
        sys.exit(1)

    message = sys.argv[1]
    response = handle_command(message)
    print(response)