-- Vam Kamali starter schema (MySQL 8 / MariaDB)
-- Import this file into the database created in cPanel.

CREATE TABLE IF NOT EXISTS ads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  bank VARCHAR(120) NOT NULL,
  loan_amount BIGINT UNSIGNED NOT NULL,
  price BIGINT UNSIGNED NOT NULL,
  term_months SMALLINT UNSIGNED NOT NULL,
  interest_rate VARCHAR(40) NOT NULL DEFAULT '',
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ads_active_created (is_active, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Deliberately no real customer records or payment-card fields.
