import sys
import os

sys.path.append(os.path.join(os.path.dirname(__file__), 'model'))
sys.stdout.reconfigure(encoding='utf-8')

from parser import parse_input
from db import (
    save_transaction, fetch_all_transactions, delete_transaction,
    get_summary, get_category_breakdown, fetch_transaction_by_id,
)
from classifier import get_classifier


# ── ANSI Colors ──────────────────────────────────────────────────────────────
class C:
    RESET   = "\033[0m"
    BOLD    = "\033[1m"
    DIM     = "\033[2m"
    RED     = "\033[31m"
    GREEN   = "\033[32m"
    YELLOW  = "\033[33m"
    BLUE    = "\033[34m"
    MAGENTA = "\033[35m"
    CYAN    = "\033[36m"
    WHITE   = "\033[37m"
    BG_BLUE = "\033[44m"


def print_banner():
    print(f"""
{C.CYAN}╔══════════════════════════════════════════════╗
║        {C.BOLD}🤖 AI FINANCE TRACKER v2.0{C.RESET}{C.CYAN}          ║
║                                              ║
║  {C.WHITE}Catat transaksi dalam bahasa Indonesia{C.CYAN}     ║
║  {C.WHITE}AI otomatis deteksi kategori{C.CYAN}               ║
║                                              ║
║  {C.YELLOW}Contoh:{C.RESET}{C.CYAN}                                     ║
║  {C.WHITE}  beli pentol 20k{C.CYAN}                         ║
║  {C.WHITE}  gajian 5jt{C.CYAN}                               ║
║  {C.WHITE}  bayar listrik 150rb{C.CYAN}                       ║
║                                              ║
║  {C.YELLOW}Ketik {C.BOLD}help{C.RESET}{C.YELLOW} untuk lihat semua menu{C.CYAN}         ║
╚══════════════════════════════════════════════╝{C.RESET}
""")


def print_transaction(t, tx_id=None):
    sign  = "+" if t["type"] == "income" else "-"
    color = C.GREEN if t["type"] == "income" else C.RED
    icon  = {
        "makan": "🍽️", "minum": "☕", "transport": "🚗",
        "hiburan": "🎬", "income": "💰", "tagihan": "🧾",
        "belanja": "🛍️", "lainnya": "📦",
    }.get(t["category"], "📦")

    # Confidence bar
    clf = get_classifier()
    _, conf = clf.predict_with_confidence(t["description"])
    bar_len = int(conf * 10)
    conf_bar = f"{C.GREEN}{'█' * bar_len}{C.DIM}{'░' * (10 - bar_len)}{C.RESET}"

    id_str = f" {C.DIM}(ID: {tx_id}){C.RESET}" if tx_id else ""

    print(f"""
  {C.BOLD}┌─────────────────────────────────────┐{C.RESET}
  {C.BOLD}│{C.RESET} 📝 {C.WHITE}{t['description'][:33]:<33}{C.RESET}{C.BOLD}│{C.RESET}
  {C.BOLD}│{C.RESET} 💰 {color}{sign}Rp {t['amount']:>12,.0f}{C.RESET}               {C.BOLD}│{C.RESET}
  {C.BOLD}│{C.RESET} {icon} {C.CYAN}{t['category']:<10}{C.RESET}  {conf_bar} {C.DIM}{conf:.0%}{C.RESET} {C.BOLD}│{C.RESET}
  {C.BOLD}└─────────────────────────────────────┘{C.RESET}{id_str}""")


def cmd_list():
    rows = fetch_all_transactions(10)
    if not rows:
        print(f"\n  {C.DIM}(belum ada transaksi){C.RESET}\n")
        return

    s = get_summary()

    print(f"\n  {C.BOLD}{'═' * 45}{C.RESET}")
    print(f"  {C.GREEN}💚 Income   : Rp {s['total_income']:>12,.0f}{C.RESET}")
    print(f"  {C.RED}❤️  Expense  : Rp {s['total_expense']:>12,.0f}{C.RESET}")
    print(f"  {C.BOLD}{'═' * 45}{C.RESET}")
    print(f"  {C.YELLOW}💰 Balance  : Rp {s['balance']:>12,.0f}{C.RESET}")
    print(f"  {C.DIM}📋 Total    : {s['total_tx']} transaksi{C.RESET}")
    print(f"  {C.BOLD}{'═' * 45}{C.RESET}\n")

    for r in rows:
        sign  = "+" if r["type"] == "income" else "-"
        color = C.GREEN if r["type"] == "income" else C.RED
        icon  = {
            "makan": "🍽️", "minum": "☕", "transport": "🚗",
            "hiburan": "🎬", "income": "💰", "tagihan": "🧾",
            "belanja": "🛍️", "lainnya": "📦",
        }.get(r["category"], "📦")
        print(
            f"  {C.DIM}[{r['id']:>3}]{C.RESET} "
            f"{icon} {r['description'][:25]:<25} "
            f"{color}{sign}Rp {r['amount']:>10,.0f}{C.RESET}  "
            f"{C.CYAN}{r['category']:<10}{C.RESET} "
            f"{C.DIM}{r['created_at']}{C.RESET}"
        )
    print()


def cmd_kategori():
    cats = get_category_breakdown()
    if not cats:
        print(f"\n  {C.DIM}(belum ada data kategori){C.RESET}\n")
        return

    total = sum(c['total'] for c in cats)
    icons = {
        "makan": "🍽️", "minum": "☕", "transport": "🚗",
        "hiburan": "🎬", "income": "💰", "tagihan": "🧾",
        "belanja": "🛍️", "lainnya": "📦",
    }

    print(f"\n  {C.BOLD}📂 Pengeluaran per Kategori{C.RESET}")
    print(f"  {C.BOLD}{'─' * 50}{C.RESET}")

    for c in cats:
        pct = (c['total'] / total * 100) if total > 0 else 0
        icon = icons.get(c['category'], "📦")
        bar_len = int(pct / 5)
        bar = f"{C.GREEN}{'█' * bar_len}{C.DIM}{'░' * (20 - bar_len)}{C.RESET}"
        print(
            f"  {icon} {C.BOLD}{c['category']:<10}{C.RESET} "
            f"Rp {c['total']:>10,.0f}  {bar} {C.YELLOW}{pct:.0f}%{C.RESET} "
            f"{C.DIM}({c['cnt']}x){C.RESET}"
        )

    print(f"\n  {C.BOLD}💰 Total: Rp {total:,.0f}{C.RESET}\n")


def cmd_delete(tx_id):
    tx = fetch_transaction_by_id(tx_id)
    if not tx:
        print(f"\n  {C.RED}❌ Transaksi ID {tx_id} tidak ditemukan.{C.RESET}\n")
        return

    print(f"\n  {C.YELLOW}⚠️  Hapus transaksi ini?{C.RESET}")
    print(f"  📝 {tx['description']}")
    sign = "+" if tx['type'] == 'income' else '-'
    print(f"  💰 {sign}Rp {tx['amount']:,.0f} ({tx['category']})")

    confirm = input(f"\n  {C.BOLD}Yakin? (y/n): {C.RESET}").strip().lower()
    if confirm != 'y':
        print(f"  {C.DIM}Dibatalkan.{C.RESET}\n")
        return

    if delete_transaction(tx_id):
        s = get_summary()
        print(f"\n  {C.GREEN}✅ Transaksi berhasil dihapus!{C.RESET}")
        print(f"  💰 Saldo skrg: Rp {s['balance']:,.0f}\n")
    else:
        print(f"\n  {C.RED}❌ Gagal menghapus transaksi.{C.RESET}\n")


def cmd_help():
    print(f"""
  {C.BOLD}{C.CYAN}📖 MENU FINANCEAI{C.RESET}
  {C.BOLD}{'─' * 40}{C.RESET}

  {C.YELLOW}💰 Catat Transaksi:{C.RESET}
    beli nasi goreng 15k
    gajian 5jt
    bayar listrik 150rb

  {C.YELLOW}📊 Lihat Data:{C.RESET}
    {C.BOLD}list{C.RESET}       — riwayat transaksi + summary
    {C.BOLD}kategori{C.RESET}   — pengeluaran per kategori
    {C.BOLD}help{C.RESET}      — menu ini

  {C.YELLOW}🗑️  Hapus:{C.RESET}
    {C.BOLD}hapus [id]{C.RESET} — hapus transaksi by ID

  {C.YELLOW}🧠 Koreksi AI:{C.RESET}
    {C.BOLD}salah [kategori]{C.RESET} — koreksi kategori terakhir
    {C.BOLD}dataset{C.RESET}       — lihat data koreksi

  {C.DIM}📂 Kategori: makan, minum, transport, hiburan,
              tagihan, belanja, income, lainnya{C.RESET}
""")


def main():
    print_banner()
    last_transaction = None

    while True:
        try:
            user_input = input(f"{C.BOLD}{C.BLUE}>> {C.RESET}").strip()
        except (KeyboardInterrupt, EOFError):
            print(f"\n  {C.CYAN}👋 Sampai jumpa!{C.RESET}\n")
            break

        if not user_input:
            continue

        cmd = user_input.lower()

        # ── Exit ─────────────────────────────────────────────────────────
        if cmd in ("exit", "quit", "q", "keluar"):
            print(f"\n  {C.CYAN}👋 Sampai jumpa!{C.RESET}\n")
            break

        # ── Help ─────────────────────────────────────────────────────────
        if cmd in ("help", "bantuan", "?", "menu"):
            cmd_help()
            continue

        # ── List / Summary ───────────────────────────────────────────────
        if cmd in ("list", "riwayat", "history", "ls"):
            cmd_list()
            continue

        # ── Kategori ─────────────────────────────────────────────────────
        if cmd in ("kategori", "category", "stat", "breakdown"):
            cmd_kategori()
            continue

        # ── Hapus ────────────────────────────────────────────────────────
        if cmd.startswith(("hapus ", "delete ", "remove ")):
            parts = cmd.split()
            if len(parts) >= 2 and parts[1].isdigit():
                cmd_delete(int(parts[1]))
            else:
                print(f"\n  {C.YELLOW}⚠️  Format: hapus [id]{C.RESET}")
                print(f"  {C.DIM}Contoh: hapus 5{C.RESET}\n")
            continue

        # ── Saldo ────────────────────────────────────────────────────────
        if cmd in ("saldo", "balance", "summary", "rekap"):
            s = get_summary()
            print(f"""
  {C.BOLD}{'═' * 40}{C.RESET}
  {C.GREEN}💚 Income   : Rp {s['total_income']:>12,.0f}{C.RESET}
  {C.RED}❤️  Expense  : Rp {s['total_expense']:>12,.0f}{C.RESET}
  {C.BOLD}{'═' * 40}{C.RESET}
  {C.YELLOW}💰 Balance  : Rp {s['balance']:>12,.0f}{C.RESET}
  {C.DIM}📋 Total    : {s['total_tx']} transaksi{C.RESET}
  {C.BOLD}{'═' * 40}{C.RESET}
""")
            continue

        # ── Parse & Save Transaction ─────────────────────────────────────
        result = parse_input(user_input)

        if result["amount"] == 0:
            print(f"\n  {C.YELLOW}⚠️  Nominal tidak ditemukan.{C.RESET}")
            print(f"  {C.DIM}Contoh: beli kopi 15k, gajian 3jt{C.RESET}\n")
            continue

        new_id = save_transaction(
            result["description"],
            result["amount"],
            result["type"],
            result["category"],
        )

        if new_id is None:
            print(f"\n  {C.RED}❌ Gagal menyimpan. Cek database.{C.RESET}\n")
            continue

        last_transaction = result
        print_transaction(result, new_id)


if __name__ == "__main__":
    main()
