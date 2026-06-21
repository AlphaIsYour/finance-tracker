import sys
sys.stdout.reconfigure(encoding='utf-8')
import os
import json
import re

sys.path.append(os.path.join(os.path.dirname(__file__), 'model'))

from parser import parse_input
from db import (
    save_transaction, fetch_all_transactions, delete_transaction,
    get_summary, get_category_breakdown, fetch_transaction_by_id,
)
from classifier import reload_classifier

# ── Icon map for categories ──────────────────────────────────────────────────
ICON_MAP = {
    "makan": "🍽️", "minum": "☕", "transport": "🚗",
    "hiburan": "🎬", "income": "💰", "tagihan": "🧾",
    "belanja": "🛍️", "lainnya": "📦",
}


def get_summary_text():
    """Generate formatted summary text."""
    s = get_summary()
    return (
        f"📊 *Rekap Keuangan*\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        f"💚 Income   : Rp {s['total_income']:,.0f}\n"
        f"❤️ Expense  : Rp {s['total_expense']:,.0f}\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        f"💰 Balance  : Rp {s['balance']:,.0f}\n"
        f"📋 Total    : {s['total_tx']} transaksi"
    )


def get_history_text(limit=5):
    """Generate formatted history text."""
    rows = fetch_all_transactions(limit)
    if not rows:
        return "📭 Belum ada transaksi."

    lines = [f"📋 *{len(rows)} Transaksi Terakhir:*", ""]
    for r in rows:
        sign = "+" if r["type"] == "income" else "-"
        icon = ICON_MAP.get(r["category"], "📦")
        lines.append(
            f"{icon} {sign}Rp {r['amount']:,.0f} · {r['category']} · {r['description'][:25]}"
        )
    return "\n".join(lines)


def get_kategori_text():
    """Generate category breakdown text."""
    cats = get_category_breakdown()
    if not cats:
        return "📭 Belum ada data kategori."

    total = sum(c['total'] for c in cats)
    lines = ["📂 *Pengeluaran per Kategori:*", ""]

    for c in cats:
        pct = (c['total'] / total * 100) if total > 0 else 0
        icon = ICON_MAP.get(c['category'], "📦")
        bar_len = int(pct / 5)  # max 20 chars
        bar = "█" * bar_len + "░" * (20 - bar_len)
        lines.append(
            f"{icon} {c['category']:<10} Rp {c['total']:>10,.0f}  {bar} {pct:.0f}%"
        )

    lines.append("")
    lines.append(f"💰 Total pengeluaran: Rp {total:,.0f}")
    return "\n".join(lines)


def handle_delete(text):
    """Handle delete command. Returns response text."""
    # Extract ID from text like "hapus 5" or "delete 5"
    match = re.search(r'(\d+)', text)
    if not match:
        return (
            "⚠️ Format: *hapus [id]*\n"
            "Contoh: *hapus 5*\n\n"
            "💡 Ketik *riwayat* untuk lihat ID transaksi."
        )

    tx_id = int(match.group(1))

    # Check if transaction exists
    tx = fetch_transaction_by_id(tx_id)
    if not tx:
        return f"❌ Transaksi dengan ID {tx_id} tidak ditemukan."

    # Delete it
    success = delete_transaction(tx_id)
    if success:
        sign = "+" if tx['type'] == 'income' else '-'
        icon = ICON_MAP.get(tx['category'], "📦")
        s = get_summary()
        return (
            f"🗑️ *Transaksi Dihapus!*\n"
            f"📝 {tx['description']}\n"
            f"{icon} {sign}Rp {tx['amount']:,.0f} ({tx['category']})\n"
            f"💰 Saldo skrg: Rp {s['balance']:,.0f}"
        )
    else:
        return "❌ Gagal menghapus transaksi."


def handle_command(text):
    """
    Main command handler. Routes to appropriate function.
    Returns response text.
    """
    text_clean = text.strip()
    text_lower = text_clean.lower()

    # ── Saldo / summary ──────────────────────────────────────────────────
    if text_lower in ["saldo", "balance", "rekap", "summary", "ringkasan"]:
        return get_summary_text()

    # ── Riwayat terakhir ─────────────────────────────────────────────────
    if text_lower in ["riwayat", "history", "terakhir", "log"]:
        return get_history_text(5)

    # ── Kategori breakdown ───────────────────────────────────────────────
    if text_lower in ["kategori", "category", "breakdown", "stat"]:
        return get_kategori_text()

    # ── Hapus transaksi ──────────────────────────────────────────────────
    if text_lower.startswith(("hapus", "delete", "remove")):
        result = handle_delete(text_clean)
        return result

    # ── Bantuan ──────────────────────────────────────────────────────────
    if text_lower in ["help", "bantuan", "?", "menu"]:
        return (
            "📖 *Menu FinanceAI:*\n\n"
            "💰 *Catat Transaksi:*\n"
            "  • beli nasi goreng 15k\n"
            "  • gajian 5jt\n"
            "  • bayar listrik 150rb\n\n"
            "📊 *Lihat Data:*\n"
            "  • *saldo* — rekap keuangan\n"
            "  • *riwayat* — 5 transaksi terakhir\n"
            "  • *kategori* — pengeluaran per kategori\n\n"
            "✏️ *Edit/Hapus:*\n"
            "  • *hapus [id]* — hapus transaksi\n\n"
            "🧠 *Koreksi AI:*\n"
            "  • *salah [kategori]* — koreksi kategori terakhir\n"
            "  • *dataset* — lihat data koreksi\n\n"
            "📂 Kategori: makan, minum, transport, hiburan, tagihan, belanja, lainnya"
        )

    # ── Transaksi biasa ──────────────────────────────────────────────────
    result = parse_input(text_clean)

    if result["amount"] == 0:
        return (
            f"⚠️ Nominal tidak ditemukan.\n"
            f"Contoh: *beli pentol 20k* atau *gajian 3jt*\n\n"
            f"💡 Ketik *help* untuk lihat semua menu."
        )

    new_id = save_transaction(
        result["description"],
        result["amount"],
        result["type"],
        result["category"],
    )

    if new_id is None:
        return "❌ Gagal menyimpan transaksi. Cek database."

    icon = ICON_MAP.get(result["category"], "📦")
    sign = "+" if result["type"] == "income" else "-"

    # Saldo terkini setelah transaksi
    s = get_summary()

    # Confidence info
    from classifier import get_classifier
    clf = get_classifier()
    _, conf = clf.predict_with_confidence(result["description"])
    conf_bar = "█" * int(conf * 10) + "░" * (10 - int(conf * 10))

    return (
        f"✅ *Transaksi Tercatat!* (ID: {new_id})\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        f"📝 {result['description']}\n"
        f"{icon} Kategori : {result['category']} {conf_bar} {conf:.0%}\n"
        f"💸 Nominal  : {sign}Rp {result['amount']:,.0f}\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        f"💰 Saldo skrg: Rp {s['balance']:,.0f}\n\n"
        f"💡 Kategori salah? Ketik *salah makan* (atau kategori yg benar)"
    )


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python process.py '<pesan>'")
        sys.exit(1)

    message = sys.argv[1]
    response = handle_command(message)
    print(response)
