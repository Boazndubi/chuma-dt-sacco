-- Run this AFTER sql/schema.sql, against the same database.
-- Adds: admin login table, and columns to track how/when each
-- payment link was sent out.

CREATE TABLE IF NOT EXISTS admins (
    id            SERIAL PRIMARY KEY,
    username      VARCHAR(60)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,     -- password_hash(), never plain text
    full_name     VARCHAR(150) NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE payment_links
    ADD COLUMN IF NOT EXISTS sent_via VARCHAR(10) CHECK (sent_via IN ('sms','whatsapp','both')),
    ADD COLUMN IF NOT EXISTS sent_at  TIMESTAMP,
    ADD COLUMN IF NOT EXISTS sent_by  INT REFERENCES admins(id);

-- Create your first admin login. Generate the hash once with:
--   php -r "echo password_hash('your-password-here', PASSWORD_DEFAULT), PHP_EOL;"
-- then paste the result below in place of the placeholder.
-- INSERT INTO admins (username, password_hash, full_name)
-- VALUES ('admin', '$2y$10$REPLACE_WITH_REAL_HASH', 'Your Name');
