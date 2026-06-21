import sys
import os
import json

sys.stdout.reconfigure(encoding='utf-8')

CUSTOM_DATA_PATH = os.path.join(os.path.dirname(__file__), 'model', 'custom_data.json')

VALID_CATEGORIES = ["makan", "minum", "transport", "hiburan", "income", "tagihan", "belanja", "lainnya"]

def load_custom_data():
    if not os.path.exists(CUSTOM_DATA_PATH):
        return []
    with open(CUSTOM_DATA_PATH, 'r', encoding='utf-8') as f:
        return json.load(f)

def save_custom_data(data):
    with open(CUSTOM_DATA_PATH, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)

def learn(description, correct_category):
    if correct_category not in VALID_CATEGORIES:
        return f"⚠️ Kategori tidak valid.\nPilihan: {', '.join(VALID_CATEGORIES)}"

    data = load_custom_data()

    for item in data:
        if item["text"].lower() == description.lower():
            item["category"] = correct_category
            save_custom_data(data)

            # Reload classifier with updated data
            from classifier import reload_classifier
            reload_classifier()

            return (
                f"✅ *Koreksi Diperbarui!*\n"
                f"📝 \"{description}\"\n"
                f"📂 Kategori → *{correct_category}*\n"
                f"🧠 Aku sudah belajar dari kesalahanku!"
            )

    data.append({"text": description, "category": correct_category})
    save_custom_data(data)

    # Reload classifier with new data
    from classifier import reload_classifier
    reload_classifier()

    return (
        f"✅ *Aku Belajar Hal Baru!*\n"
        f"📝 \"{description}\"\n"
        f"📂 Kategori → *{correct_category}*\n"
        f"🧠 Dataset bertambah! ({len(data)} koreksi tersimpan)"
    )

def get_stats():
    data = load_custom_data()
    if not data:
        return "📭 Belum ada koreksi tersimpan."

    # Count per category
    cat_counts = {}
    for item in data:
        cat = item['category']
        cat_counts[cat] = cat_counts.get(cat, 0) + 1

    lines = [f"🧠 *Custom Dataset ({len(data)} data):*", ""]

    # Category breakdown
    for cat, cnt in sorted(cat_counts.items(), key=lambda x: -x[1]):
        bar = "█" * min(cnt, 10) + "░" * max(0, 10 - cnt)
        lines.append(f"  {cat:<10} {bar} {cnt}")

    lines.append("")
    lines.append("📝 *5 Koreksi Terakhir:*")
    for item in data[-5:]:
        lines.append(f"  • \"{item['text']}\" → {item['category']}")

    return "\n".join(lines)


def batch_learn(items):
    """
    Learn multiple corrections at once.
    items: list of {"text": "...", "category": "..."}
    Returns summary text.
    """
    data = load_custom_data()
    added = 0
    updated = 0

    for item in items:
        text = item.get("text", "").strip()
        cat  = item.get("category", "").strip()

        if not text or cat not in VALID_CATEGORIES:
            continue

        # Check if exists
        found = False
        for existing in data:
            if existing["text"].lower() == text.lower():
                existing["category"] = cat
                updated += 1
                found = True
                break

        if not found:
            data.append({"text": text, "category": cat})
            added += 1

    save_custom_data(data)

    from classifier import reload_classifier
    reload_classifier()

    return (
        f"✅ *Batch Learning Selesai!*\n"
        f"➕ Ditambahkan: {added}\n"
        f"✏️ Diperbarui: {updated}\n"
        f"🧠 Total dataset: {len(data)}"
    )

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python learn.py '<deskripsi>' '<kategori>'")
        sys.exit(1)

    if sys.argv[1] == "--stats":
        print(get_stats())
        sys.exit(0)

    if len(sys.argv) < 3:
        print("Usage: python learn.py '<deskripsi>' '<kategori>'")
        sys.exit(1)

    description     = sys.argv[1]
    correct_category = sys.argv[2]
    print(learn(description, correct_category))