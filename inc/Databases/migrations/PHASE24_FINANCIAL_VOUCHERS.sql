-- Phase 24: GL-native receipt/payment voucher source documents.
-- Run after the approved chart seed and the branch/cash-box migrations.

CREATE TABLE IF NOT EXISTS financial_vouchers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_no VARCHAR(40) NULL,
    voucher_type ENUM('RECEIPT','PAYMENT') NOT NULL,
    voucher_subtype ENUM('CUSTOMER_RECEIPT','SUPPLIER_REFUND','GENERAL_RECEIPT','GENERAL_PAYMENT') NOT NULL,
    branch_id INT NOT NULL,
    cash_box_id BIGINT UNSIGNED NOT NULL,
    payment_method_id INT NOT NULL,
    voucher_date DATETIME NOT NULL,
    partner_type VARCHAR(20) NULL,
    partner_id BIGINT UNSIGNED NULL,
    total_amount DECIMAL(18,2) NOT NULL,
    description VARCHAR(500) NULL,
    status ENUM('PROCESSING','POSTED','REVERSED','FAILED') NOT NULL DEFAULT 'PROCESSING',
    journal_entry_id BIGINT UNSIGNED NULL,
    accounting_event_id BIGINT UNSIGNED NULL,
    reversal_journal_entry_id BIGINT UNSIGNED NULL,
    idempotency_key VARCHAR(128) NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    posted_at DATETIME NULL,
    reversed_by BIGINT UNSIGNED NULL,
    reversed_at DATETIME NULL,
    reversal_reason VARCHAR(500) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_financial_voucher_no (voucher_no),
    UNIQUE KEY uq_financial_voucher_idempotency (idempotency_key),
    UNIQUE KEY uq_financial_voucher_journal (journal_entry_id),
    UNIQUE KEY uq_financial_voucher_event (accounting_event_id),
    KEY idx_financial_voucher_branch_date (branch_id, voucher_date, id),
    KEY idx_financial_voucher_partner (partner_type, partner_id, voucher_date),
    KEY idx_financial_voucher_status (status, voucher_type, voucher_date),
    CONSTRAINT fk_financial_voucher_branch
        FOREIGN KEY (branch_id) REFERENCES branches (BR_ID)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_financial_voucher_cash_box
        FOREIGN KEY (cash_box_id) REFERENCES cash_boxes (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_financial_voucher_payment_method
        FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_financial_voucher_journal
        FOREIGN KEY (journal_entry_id) REFERENCES journal_entries (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_financial_voucher_event
        FOREIGN KEY (accounting_event_id) REFERENCES accounting_events (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_financial_voucher_reversal_journal
        FOREIGN KEY (reversal_journal_entry_id) REFERENCES journal_entries (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS financial_voucher_lines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_id BIGINT UNSIGNED NOT NULL,
    line_no SMALLINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    partner_type VARCHAR(20) NULL,
    partner_id BIGINT UNSIGNED NULL,
    cost_center_id BIGINT UNSIGNED NULL,
    amount DECIMAL(18,2) NOT NULL,
    line_description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_financial_voucher_line_no (voucher_id, line_no),
    KEY idx_financial_voucher_line_account_partner (account_id, partner_type, partner_id),
    CONSTRAINT fk_financial_voucher_line_header
        FOREIGN KEY (voucher_id) REFERENCES financial_vouchers (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_financial_voucher_line_account
        FOREIGN KEY (account_id) REFERENCES accounts (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
