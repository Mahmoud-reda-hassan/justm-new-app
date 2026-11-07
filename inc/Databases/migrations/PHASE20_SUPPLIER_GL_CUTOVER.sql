-- PHASE20: supplier purchases and payments use the General Ledger for new events.
-- أرصدة الموردين التاريخية في partners_balance لا يتم نقلها أو تعديلها في هذه المرحلة.
CREATE TABLE IF NOT EXISTS supplier_gl_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_key VARCHAR(64) NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    payment_method_id INT NOT NULL,
    amount DECIMAL(18,2) NOT NULL,
    journal_entry_id BIGINT UNSIGNED NOT NULL,
    note TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_supplier_gl_payment_request (request_key),
    UNIQUE KEY uq_supplier_gl_payment_journal (journal_entry_id),
    KEY idx_supplier_gl_payment_supplier (supplier_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS supplier_gl_payment_allocations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    purchase_order_id BIGINT UNSIGNED DEFAULT NULL,
    allocation_type ENUM('INVOICE','ADVANCE') NOT NULL,
    amount DECIMAL(18,2) NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_supplier_gl_payment_allocation (payment_id, purchase_order_id, allocation_type),
    KEY idx_supplier_gl_allocation_supplier (supplier_id, created_at),
    KEY idx_supplier_gl_allocation_order (purchase_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
