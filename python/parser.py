import re
import sys
import os

sys.path.append(os.path.join(os.path.dirname(__file__), 'model'))
from classifier import get_classifier

# ── Keywords to detect income ─────────────────────────────────────────────
INCOME_KEYWORDS = [
    "gaji", "gajian", "bonus", "terima", "dapat", "transfer masuk",
    "cashback", "freelance", "honor", "komisi", "bayaran", "income"
]

def parse_amount(text):
    """
    Extract amount from text.
    Supports: 20k, 20rb, 20ribu, 20.000, 20000
    """
    text_lower = text.lower()

    # Pattern: angka + k / rb / ribu
    match = re.search(r'(\d+(?:\.\d+)?)\s*(k|rb|ribu)', text_lower)
    if match:
        number = float(match.group(1).replace('.', ''))
        return number * 1000

    match = re.search(r'(\d{1,3}(?:\.\d{3})+)', text_lower)
    if match:
        return float(match.group(1).replace('.', ''))

    match = re.search(r'(\d+)', text_lower)
    if match:
        return float(match.group(1))

    return 0.0


def detect_type(text):
    """
    Detect income or expense based on keywords.
    """
    text_lower = text.lower()
    for keyword in INCOME_KEYWORDS:
        if keyword in text_lower:
            return "income"
    return "expense"


def classify_category(text, transaction_type):
    """
    Use Naive Bayes to predict category.
    Force 'income' category if type is income.
    """
    if transaction_type == "income":
        return "income"
    clf = get_classifier()
    return clf.predict(text)


def parse_input(text):
    """
    Main parser — returns dict with all extracted fields.
    """
    amount          = parse_amount(text)
    transaction_type = detect_type(text)
    category        = classify_category(text, transaction_type)

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
        "gajian bulan ini 5000000",
        "beli kopi 25.000",
        "nonton bioskop 50k",
        "beli aqua 5000",
        "dapat bonus 500k",
    ]

    print(f"{'Input':<35} {'Amount':>10}  {'Type':<10} {'Category'}")
    print("-" * 75)
    for t in tests:
        r = parse_input(t)
        print(f"{t:<35} {r['amount']:>10.0f}  {r['type']:<10} {r['category']}")