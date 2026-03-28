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
        self.vocab = set()
        self.classes = set()

    def tokenize(self, text):
        return text.lower().split()

    def train(self, data):
        for text, label in data:
            tokens = self.tokenize(text)
            self.class_counts[label] += 1
            self.classes.add(label)
            for token in tokens:
                self.class_word_counts[label][token] += 1
                self.vocab.add(token)

    def predict(self, text):
        tokens = self.tokenize(text)
        vocab_size = len(self.vocab)
        total_docs = sum(self.class_counts.values())
        best_class = None
        best_score = float('-inf')
        for cls in self.classes:
            log_prior = math.log(self.class_counts[cls] / total_docs)
            total_words_in_class = sum(self.class_word_counts[cls].values())
            log_likelihood = 0
            for token in tokens:
                word_count = self.class_word_counts[cls].get(token, 0)
                prob = (word_count + 1) / (total_words_in_class + vocab_size)
                log_likelihood += math.log(prob)
            score = log_prior + log_likelihood
            if score > best_score:
                best_score = score
                best_class = cls
        return best_class

    def confidence_scores(self, text):
        tokens = self.tokenize(text)
        vocab_size = len(self.vocab)
        total_docs = sum(self.class_counts.values())
        scores = {}
        for cls in self.classes:
            log_prior = math.log(self.class_counts[cls] / total_docs)
            total_words_in_class = sum(self.class_word_counts[cls].values())
            log_likelihood = 0
            for token in tokens:
                word_count = self.class_word_counts[cls].get(token, 0)
                prob = (word_count + 1) / (total_words_in_class + vocab_size)
                log_likelihood += math.log(prob)
            scores[cls] = log_prior + log_likelihood
        return scores


def load_custom_data():
    if not os.path.exists(CUSTOM_DATA_PATH):
        return []
    with open(CUSTOM_DATA_PATH, 'r', encoding='utf-8') as f:
        raw = json.load(f)
        return [(item["text"], item["category"]) for item in raw]


_classifier = None

def get_classifier():
    global _classifier
    if _classifier is None:
        _classifier = NaiveBayesClassifier()
        all_data = list(TRAINING_DATA)
        all_data.extend(load_custom_data())
        _classifier.train(all_data)
    return _classifier


if __name__ == "__main__":
    clf = get_classifier()
    test_cases = [
        "beli pentol", "naik gojek", "beli kopi susu",
        "nonton netflix", "terima gaji", "beli es teh manis",
    ]
    print(f"{'Input':<30} {'Predicted Category'}")
    print("-" * 50)
    for text in test_cases:
        print(f"{text:<30} {clf.predict(text)}")