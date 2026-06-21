import math
import json
import os
from collections import defaultdict
from training_data import TRAINING_DATA

CUSTOM_DATA_PATH = os.path.join(os.path.dirname(__file__), 'custom_data.json')


class NaiveBayesClassifier:
    def __init__(self):
        self.class_word_counts = defaultdict(lambda: defaultdict(int))
        self.class_counts = defaultdict(int)
        self.class_total_words = defaultdict(int)
        self.vocab = set()
        self.classes = set()
        self.total_docs = 0

    def tokenize(self, text):
        """Tokenize text: lowercase, split by whitespace."""
        return text.lower().split()

    def train(self, data):
        """Train the classifier on (text, label) pairs."""
        for text, label in data:
            tokens = self.tokenize(text)
            self.class_counts[label] += 1
            self.total_docs += 1
            self.classes.add(label)
            for token in tokens:
                self.class_word_counts[label][token] += 1
                self.class_total_words[label] += 1
                self.vocab.add(token)

    def predict(self, text):
        """Predict the most likely class for given text."""
        scores = self._log_scores(text)
        if not scores:
            return "lainnya"
        return max(scores, key=scores.get)

    def predict_with_confidence(self, text):
        """
        Predict class with confidence score (0.0 - 1.0).
        Returns (category, confidence).
        """
        scores = self._log_scores(text)
        if not scores:
            return ("lainnya", 0.0)

        best_class = max(scores, key=scores.get)

        # Convert log scores to probabilities using softmax
        max_score = max(scores.values())
        exp_scores = {c: math.exp(s - max_score) for c, s in scores.items()}
        total_exp = sum(exp_scores.values())
        probs = {c: v / total_exp for c, v in exp_scores.items()}

        confidence = probs[best_class]
        return (best_class, round(confidence, 3))

    def confidence_scores(self, text):
        """
        Return all class probabilities for given text.
        Returns dict of {class: probability}.
        """
        scores = self._log_scores(text)
        if not scores:
            return {}

        max_score = max(scores.values())
        exp_scores = {c: math.exp(s - max_score) for c, s in scores.items()}
        total_exp = sum(exp_scores.values())
        return {c: round(v / total_exp, 4) for c, v in exp_scores.items()}

    def _log_scores(self, text):
        """Calculate log-probability scores for each class."""
        tokens = self.tokenize(text)
        if not tokens or not self.classes:
            return {}

        vocab_size = len(self.vocab)
        scores = {}

        for cls in self.classes:
            # Log prior: P(class)
            log_prior = math.log(self.class_counts[cls] / self.total_docs)

            # Log likelihood: P(tokens|class) with Laplace smoothing
            total_words_in_class = self.class_total_words[cls]
            log_likelihood = 0
            for token in tokens:
                word_count = self.class_word_counts[cls].get(token, 0)
                # Laplace smoothing
                prob = (word_count + 1) / (total_words_in_class + vocab_size)
                log_likelihood += math.log(prob)

            scores[cls] = log_prior + log_likelihood

        return scores


def load_custom_data():
    """Load user corrections from JSON file."""
    if not os.path.exists(CUSTOM_DATA_PATH):
        return []
    try:
        with open(CUSTOM_DATA_PATH, 'r', encoding='utf-8') as f:
            raw = json.load(f)
            return [(item["text"], item["category"]) for item in raw]
    except (json.JSONDecodeError, KeyError):
        return []


_classifier = None


def get_classifier():
    """Get or create the singleton classifier instance."""
    global _classifier
    if _classifier is None:
        _classifier = NaiveBayesClassifier()
        all_data = list(TRAINING_DATA)
        all_data.extend(load_custom_data())
        _classifier.train(all_data)
    return _classifier


def reload_classifier():
    """Force reload the classifier (after learning new data)."""
    global _classifier
    _classifier = None
    return get_classifier()


if __name__ == "__main__":
    clf = get_classifier()
    test_cases = [
        "beli pentol",
        "naik gojek",
        "beli kopi susu",
        "nonton netflix",
        "terima gaji",
        "beli es teh manis",
        "bayar listrik",
        "top up game",
        "beli skincare",
        "isi bensin",
    ]
    print(f"{'Input':<30} {'Predicted':<12} {'Confidence':>10}")
    print("-" * 55)
    for text in test_cases:
        cat, conf = clf.predict_with_confidence(text)
        bar = "█" * int(conf * 10) + "░" * (10 - int(conf * 10))
        print(f"{text:<30} {cat:<12} {bar} {conf:.1%}")
