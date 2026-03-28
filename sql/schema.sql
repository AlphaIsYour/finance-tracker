CREATE DATABASE IF NOT EXISTS ai_finance_tracker;

USE ai_finance_tracker;

CREATE TABLE IF NOT EXISTS transactions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    description VARCHAR(255)  NOT NULL,
    amount      DECIMAL(15,2) NOT NULL,
    type        ENUM('income','expense') NOT NULL,
    category    VARCHAR(50)   NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sample seed data (optional, for testing dashboard)
INSERT INTO transactions (description, amount, type, category) VALUES
('beli nasi goreng', 15000, 'expense', 'makan'),
('naik grab', 12000, 'expense', 'transport'),
('gajian bulan ini', 5000000, 'income', 'income'),
('beli kopi', 25000, 'expense', 'minum'),
('nonton bioskop', 50000, 'expense', 'hiburan');