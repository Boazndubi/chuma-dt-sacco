CREATE TABLE IF NOT EXISTS admins (
    id            SERIAL PRIMARY KEY,
    username      VARCHAR(60)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(150) NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE payment_links
    ADD COLUMN IF NOT EXISTS sent_via VARCHAR(10) CHECK (sent_via IN ('sms','whatsapp','both')),
    ADD COLUMN IF NOT EXISTS sent_at  TIMESTAMP,
    ADD COLUMN IF NOT EXISTS sent_by  INT REFERENCES admins(id);