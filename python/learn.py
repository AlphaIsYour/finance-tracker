import sys
import os
import json

sys.stdout.reconfigure(encoding='utf-8')

CUSTOM_DATA_PATH = os.path.join(os.path.dirname(__file__), 'model', 'custom_data.json')

VALID_CATEGORIES = ["makan", "minum", "transport", "hiburan", "income", "lainnya"]

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
            return (
                f"✅ *Koreksi Diperbarui!*\n"
                f"📝 \"{description}\"\n"
                f"📂 Kategori → *{correct_category}*\n"
                f"🧠 Aku sudah belajar dari kesalahanku!"
            )

    data.append({"text": description, "category": correct_category})
    save_custom_data(data)

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
    lines = [f"🧠 *Custom Dataset ({len(data)} data):*"]
    for item in data[-5:]:  
        lines.append(f"  • \"{item['text']}\" → {item['category']}")
    return "\n".join(lines)

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Usage: python learn.py '<deskripsi>' '<kategori>'")
        sys.exit(1)
    description     = sys.argv[1]
    correct_category = sys.argv[2]
    print(learn(description, correct_category))