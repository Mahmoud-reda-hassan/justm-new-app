-- PHASE 14: تحصيلات العملاء بواسطة المندوب وتسوية عهدة المندوب والعمولة
-- MariaDB 10.5+ / InnoDB. نفّذ على نسخة اختبار وبعد مراجعة المخطط.
-- مستقل عن partners_balance؛ مصدر الحقيقة هو القيود وسجلات التسوية الجديدة.

CREATE TABLE IF NOT EXISTS delivery_accounting_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    commission_settlement_mode ENUM('DEDUCT_FROM_CUSTODY','SEPARATE','USER_CHOICE') NOT NULL DEFAULT 'USER_CHOICE',
    courier_custody_account_id BIGINT UNSIGNED NULL,
    courier_commission_expense_account_id BIGINT UNSIGNED NULL,
    courier_commission_payable_account_id BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_delivery_accounting_settings_singleton CHECK (id = 1),
    CONSTRAINT fk_delivery_custody_account FOREIGN KEY (courier_custody_account_id) REFERENCES accounts(id),
    CONSTRAINT fk_delivery_commission_expense_account FOREIGN KEY (courier_commission_expense_account_id) REFERENCES accounts(id),
    CONSTRAINT fk_delivery_commission_payable_account FOREIGN KEY (courier_commission_payable_account_id) REFERENCES accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO delivery_accounting_settings (id, commission_settlement_mode)
VALUES (1, 'USER_CHOICE');

CREATE TABLE IF NOT EXISTS delivery_customer_collections (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    receipt_no VARCHAR(40) NULL,
    delivery_user_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    payment_method_id BIGINT UNSIGNED NOT NULL,
    collection_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    customer_paid_amount DECIMAL(15,2) NOT NULL,
    commission_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    commission_mode ENUM('DEDUCT_FROM_CUSTODY','SEPARATE') NULL,
    collection_journal_id BIGINT UNSIGNED NULL,
    commission_journal_id BIGINT UNSIGNED NULL,
    status ENUM('DRAFT','POSTED','REVERSED') NOT NULL DEFAULT 'DRAFT',
    note VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    posted_at DATETIME NULL,
    reversed_at DATETIME NULL,
    reversed_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_delivery_collection_receipt_no (receipt_no),
    KEY idx_delivery_collection_courier_status (delivery_user_id, status, collection_date),
    KEY idx_delivery_collection_customer (customer_id, collection_date),
    KEY idx_delivery_collection_journal (collection_journal_id),
    KEY idx_delivery_collection_commission_journal (commission_journal_id),
    CONSTRAINT chk_delivery_customer_paid_positive CHECK (customer_paid_amount > 0),
    CONSTRAINT chk_delivery_commission_nonnegative CHECK (commission_amount >= 0),
    CONSTRAINT chk_delivery_collection_commission_not_overpaid CHECK (commission_amount <= customer_paid_amount)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delivery_collection_order_allocations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    collection_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    customer_amount DECIMAL(15,2) NOT NULL,
    commission_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_delivery_collection_order (collection_id, order_id),
    KEY idx_delivery_alloc_order (order_id),
    CONSTRAINT fk_delivery_alloc_collection FOREIGN KEY (collection_id)
        REFERENCES delivery_customer_collections(id),
    CONSTRAINT chk_delivery_alloc_customer_amount CHECK (customer_amount > 0),
    CONSTRAINT chk_delivery_alloc_commission_amount CHECK (commission_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delivery_company_settlements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    settlement_no VARCHAR(40) NULL,
    delivery_user_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    payment_method_id BIGINT UNSIGNED NOT NULL,
    settlement_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    commission_mode ENUM('DEDUCT_FROM_CUSTODY','SEPARATE') NOT NULL,
    collected_gross_amount DECIMAL(15,2) NOT NULL,
    commission_offset_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    cash_received_amount DECIMAL(15,2) NOT NULL,
    journal_id BIGINT UNSIGNED NULL,
    status ENUM('DRAFT','POSTED','REVERSED') NOT NULL DEFAULT 'DRAFT',
    note VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    posted_at DATETIME NULL,
    reversed_at DATETIME NULL,
    reversed_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_delivery_settlement_no (settlement_no),
    KEY idx_delivery_settlement_courier (delivery_user_id, status, settlement_date),
    KEY idx_delivery_settlement_journal (journal_id),
    CONSTRAINT chk_delivery_settlement_gross CHECK (collected_gross_amount > 0),
    CONSTRAINT chk_delivery_settlement_commission CHECK (commission_offset_amount >= 0),
    CONSTRAINT chk_delivery_settlement_cash CHECK (cash_received_amount >= 0),
    CONSTRAINT chk_delivery_settlement_math CHECK (
        ROUND(collected_gross_amount - commission_offset_amount, 2) = ROUND(cash_received_amount, 2)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delivery_commission_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_no VARCHAR(40) NULL,
    delivery_user_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    payment_method_id BIGINT UNSIGNED NOT NULL,
    payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    amount DECIMAL(15,2) NOT NULL,
    journal_id BIGINT UNSIGNED NULL,
    status ENUM('DRAFT','POSTED','REVERSED') NOT NULL DEFAULT 'DRAFT',
    note VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    posted_at DATETIME NULL,
    reversed_at DATETIME NULL,
    reversed_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_delivery_commission_payment_no (payment_no),
    KEY idx_delivery_commission_payment_courier (delivery_user_id, status, payment_date),
    KEY idx_delivery_commission_payment_journal (journal_id),
    CONSTRAINT chk_delivery_commission_payment_positive CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delivery_settlement_collection_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    settlement_id BIGINT UNSIGNED NOT NULL,
    collection_id BIGINT UNSIGNED NOT NULL,
    allocation_id BIGINT UNSIGNED NOT NULL,
    applied_gross_amount DECIMAL(15,2) NOT NULL,
    applied_commission_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_delivery_settlement_allocation (settlement_id, allocation_id),
    KEY idx_delivery_link_collection (collection_id),
    KEY idx_delivery_link_allocation (allocation_id),
    CONSTRAINT fk_delivery_link_settlement FOREIGN KEY (settlement_id)
        REFERENCES delivery_company_settlements(id),
    CONSTRAINT fk_delivery_link_collection FOREIGN KEY (collection_id)
        REFERENCES delivery_customer_collections(id),
    CONSTRAINT fk_delivery_link_allocation FOREIGN KEY (allocation_id)
        REFERENCES delivery_collection_order_allocations(id),
    CONSTRAINT chk_delivery_link_gross CHECK (applied_gross_amount > 0),
    CONSTRAINT chk_delivery_link_commission CHECK (applied_commission_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ملاحظات تنفيذية:
-- 1) لا تُضف حسابات محاسبية تلقائيًا. عيّن حسابات العهدة/مصروف العمولة/مستحق العمولة المعتمدة في الإعدادات.
-- 2) collection_journal_id و commission_journal_id و journal_id تربط بسجلات journal_entries.id من طبقة التطبيق.
-- 3) يجب أن تنفذ طبقة التطبيق كل التحقق/التخصيص/القيود داخل transaction واحدة مع SELECT ... FOR UPDATE.
-- 4) لا يسمح بتخصيص أكثر من المتبقي من مديونية الأمر أو تحصيل/تسوية سجل REVERSED.
-- 5) لا يتم اعتبار استلام الشركة من المندوب تحصيلًا جديدًا من العميل؛ هو تخفيض لعهدة المندوب فقط.
-- 6) طريقة DEDUCT_FROM_CUSTODY تخصم العمولة من العهدة عند تسوية الشركة؛ لا تنشئ قيد صرف نقدي للعمولة.
-- 7) طريقة SEPARATE تورّد كامل التحصيل، وتبقى العمولة مستحقة حتى صرفها بقيد مستقل فعلي.
