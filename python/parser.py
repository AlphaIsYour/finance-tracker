import re
import sys
import os

sys.path.append(os.path.join(os.path.dirname(__file__), 'model'))
from classifier import get_classifier

# ── Keywords to detect income ─────────────────────────────────────────────
INCOME_KEYWORDS = [
    "gaji", "gajian", "bonus", "terima", "dapat", "transfer masuk",
    "cashback", "freelance", "honor", "komisi", "bayaran", "income",
    "duit", "masuk", "cair", "dicairkan", "pemasukan", "revenue",
    "profit", "untung", "hasil jual", "laku", "terjual",
]

# ── Keywords to detect expense (strengthen detection) ─────────────────────
EXPENSE_KEYWORDS = [
    "beli", "bayar", "bayarin", "ongkir", "tip", "tips",
    "nabung", "setor", "transfer keluar", "keluar", "keluarin",
    "donasi", "sumbangan", "iuran", "patungan",
]

# ── Category hints from keywords (override classifier if confident) ───────
CATEGORY_HINTS = {
    "makan": [
        "nasi", "mie", "ayam", "ikan", "sate", "bakso", "rendang", "gorang",
        "goreng", "pangsit", "dimsum", "sushi", "pizza", "burger", "roti",
        "kue", "snack", "cemilan", "biskuit", "coklat", "permen", "pentol",
        "cilok", "siomay", "ketoprak", "gado", "lontong", "martabak",
        "bubur", "seblak", "batagor", "pempek", "kwetiau", "bihun",
        "nasi uduk", "nasi kuning", "pecel", "rawon", "opor", "fried chicken",
        "kentang", "warteg", "kantin", "rumah makan", "katering", "catering",
        "restoran", "resto", "kafe", "cafe", "foodcourt", "food court",
    ],
    "minum": [
        "kopi", "teh", "susu", "jus", "es", "air mineral", "aqua",
        "teh botol", "pocari", "isotonik", "sprite", "cola", "fanta",
        "yakult", "matcha", "americano", "latte", "cappuccino",
        "thai tea", "boba", "dawet", "doger", "kelapa", "energi",
        "extra joss", "kratingdaeng", "mizone",
    ],
    "transport": [
        "bensin", "bbm", "tol", "parkir", "grab", "gojek", "ojol",
        "taksi", "trans", "mrt", "lrt", "krl", "commuter", "pesawat",
        "kapal", "travel", "servis motor", "servis mobil", "ganti oli",
        "ban motor", "ban mobil", "cuci motor", "cuci mobil", "helm",
        "jas hujan", "sewa motor", "sewa mobil",
    ],
    "hiburan": [
        "nonton", "bioskop", "netflix", "spotify", "youtube", "disney",
        "prime", "vidio", "game", "steam", "mobile legends", "ml",
        "free fire", "ff", "pubg", "valorant", "genshin", "karaoke",
        "futsal", "badminton", "billiard", "renang", "gym", "fitness",
        "tiket", "pameran", "museum", "zoo", "kebun binatang",
        "puzzle", "board game", "novel", "majalah", "komik",
    ],
    "tagihan": [
        "listrik", "pdam", "air", "internet", "wifi", "indihome",
        "telpon", "pulsa", "paket data", "cicilan", "kos", "kost",
        "kontrakan", "sewa", "asuransi", "bpjs", "iuran", "pajak",
        "telepon", "speedy", "myrepublic", "biznet", "cbn",
    ],
    "belanja": [
        "baju", "celana", "sepatu", "sandal", "tas", "dompet",
        "jam tangan", "aksesoris", "kosmetik", "skincare", "parfum",
        "sabun", "shampo", "deterjen", "peralatan", "alat tulis",
        "charger", "kabel", "headset", "earphone", "elektronik",
        "hp", "laptop", "tablet", "shopee", "tokopedia", "lazada",
        "bukalapak", "blibli", "tiktok shop",
    ],
}


def parse_amount(text):
    """
    Extract amount from text.
    Supports: 20k, 20rb, 20ribu, 1.5jt, 1,5jt, 20.000, 20000, 1jt, 500rb
    """
    text_lower = text.lower().replace(",", ".")

    # Pattern: angka + jt / juta (juta)
    match = re.search(r'(\d+(?:\.\d+)?)\s*(jt|juta)', text_lower)
    if match:
        number = float(match.group(1))
        return number * 1_000_000

    # Pattern: angka + k / rb / ribu
    match = re.search(r'(\d+(?:\.\d+)?)\s*(k|rb|ribu)', text_lower)
    if match:
        number = float(match.group(1))
        return number * 1000

    # Pattern: angka dengan titik sebagai pemisah ribuan (20.000, 1.500.000)
    match = re.search(r'(\d{1,3}(?:\.\d{3})+)', text_lower)
    if match:
        return float(match.group(1).replace('.', ''))

    # Pattern: angka biasa
    match = re.search(r'(\d+)', text_lower)
    if match:
        val = float(match.group(1))
        # Heuristic: if number is small and has "rb" or "k" nearby context, multiply
        return val

    return 0.0


def detect_type(text):
    """
    Detect income or expense based on keywords.
    Returns 'income' or 'expense'.
    """
    text_lower = text.lower()

    # Check income keywords first (stronger signal)
    for keyword in INCOME_KEYWORDS:
        if keyword in text_lower:
            return "income"

    # Check expense keywords
    for keyword in EXPENSE_KEYWORDS:
        if keyword in text_lower:
            return "expense"

    # Default: expense (most transactions are expenses)
    return "expense"


def detect_category_from_keywords(text):
    """
    Try to detect category from keywords directly.
    Returns category string or None if not confident.
    """
    text_lower = text.lower()
    scores = {}

    for category, keywords in CATEGORY_HINTS.items():
        score = 0
        for kw in keywords:
            if kw in text_lower:
                # Longer keyword matches are more confident
                score += len(kw)
        if score > 0:
            scores[category] = score

    if not scores:
        return None

    # Return the category with highest score
    best = max(scores, key=scores.get)
    return best


def classify_category(text, transaction_type):
    """
    Classify category using:
    1. Force 'income' if type is income
    2. Keyword hints (fast, no ML needed)
    3. Naive Bayes classifier (fallback)
    """
    if transaction_type == "income":
        return "income"

    # Try keyword-based detection first
    keyword_cat = detect_category_from_keywords(text)
    if keyword_cat:
        return keyword_cat

    # Fallback to Naive Bayes
    clf = get_classifier()
    return clf.predict(text)


def parse_input(text):
    """
    Main parser — returns dict with all extracted fields.
    """
    amount           = parse_amount(text)
    transaction_type = detect_type(text)
    category         = classify_category(text, transaction_type)

    return {
        "description": text.strip(),
        "amount":      amount,
        "type":        transaction_type,
        "category":    category,
    }


if __name__ == "__main__":
    tests = [
        "beli pentol 20k",
        "naik grab 15rb",
        "gajian bulan ini 5jt",
        "beli kopi 25.000",
        "nonton bioskop 50k",
        "beli aqua 5000",
        "dapat bonus 500k",
        "bayar listrik 150rb",
        "terima gaji 7.500.000",
        "beli nasi goreng 15k",
        "top up game 50k",
        "bayar kos 1.2jt",
        "isi bensin 50rb",
        "beli skincare 150k",
        "jajan di kantin 12000",
    ]

    print(f"{'Input':<35} {'Amount':>12}  {'Type':<10} {'Category'}")
    print("-" * 75)
    for t in tests:
        r = parse_input(t)
        print(f"{t:<35} {r['amount']:>12,.0f}  {r['type']:<10} {r['category']}")
