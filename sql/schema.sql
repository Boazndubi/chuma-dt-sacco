CREATE TABLE IF NOT EXISTS members (
    id            SERIAL PRIMARY KEY,
    member_no     VARCHAR(20)  NOT NULL UNIQUE,
    full_name     VARCHAR(150) NOT NULL,
    phone         VARCHAR(15)  NOT NULL,
    email         VARCHAR(150),
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS loans (
    id              SERIAL PRIMARY KEY,
    member_id       INT NOT NULL REFERENCES members(id),
    loan_no         VARCHAR(20)  NOT NULL UNIQUE,
    product_code    VARCHAR(3),
    principal       NUMERIC(12,2) NOT NULL,
    balance         NUMERIC(12,2) NOT NULL,
    due_date        DATE NOT NULL,
    status          VARCHAR(10) NOT NULL DEFAULT 'active'
                        CHECK (status IN ('active','overdue','cleared')),
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS payment_links (
    id              SERIAL PRIMARY KEY,
    token           CHAR(64) NOT NULL UNIQUE,
    loan_id         INT NOT NULL REFERENCES loans(id),
    expires_at      TIMESTAMP,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS payments (
    id                    SERIAL PRIMARY KEY,
    loan_id               INT NOT NULL REFERENCES loans(id),
    payment_link_id       INT REFERENCES payment_links(id),
    phone                 VARCHAR(15) NOT NULL,
    amount                NUMERIC(12,2) NOT NULL,
    checkout_request_id   VARCHAR(60) UNIQUE,
    merchant_request_id   VARCHAR(60),
    mpesa_receipt         VARCHAR(30),
    status                VARCHAR(10) NOT NULL DEFAULT 'pending'
                              CHECK (status IN ('pending','success','failed','cancelled')),
    result_desc           VARCHAR(255),
    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_payments_status ON payments(status);
CREATE INDEX IF NOT EXISTS idx_loans_member ON loans(member_id);

CREATE OR REPLACE FUNCTION set_updated_at() RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_payments_updated_at ON payments;
CREATE TRIGGER trg_payments_updated_at
    BEFORE UPDATE ON payments
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();