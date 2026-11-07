-- PHASE18: Customer refund documents, posted only to the General Ledger.

-- PHASE18: مستندات رد مبالغ مرتجعات العملاء من الأستاذ العام فقط.
CREATE TABLE IF NOT EXISTS customer_refund_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    refund_no VARCHAR(40) NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    payment_method_id BIGINT UNSIGNED NOT NULL,
    refund_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    amount DECIMAL(15,2) NOT NULL,
    journal_id BIGINT UNSIGNED NULL,
    status ENUM('DRAFT','POSTED','REVERSED') NOT NULL DEFAULT 'DRAFT',
    created_by BIGINT UNSIGNED NOT NULL,
    request_key VARCHAR(64) NULL,
    request_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    posted_at DATETIME NULL,
    reversed_at DATETIME NULL,
    reversed_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customer_refund_request_key (request_key),
    UNIQUE KEY uq_customer_refund_no (refund_no),
    KEY idx_customer_refund_order (order_id,status,refund_date),
    KEY idx_customer_refund_customer (customer_id,status,refund_date),
    KEY idx_customer_refund_journal (journal_id),
    CONSTRAINT chk_customer_refund_positive CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
