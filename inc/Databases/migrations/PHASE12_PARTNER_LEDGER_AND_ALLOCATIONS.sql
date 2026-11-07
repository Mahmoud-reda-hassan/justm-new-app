-- المرحلة 12: دفتر أستاذ الأطراف وتخصيص التسويات
-- يعتمد على accounts / journal_entries / journal_entry_details فقط.
-- لا يقرأ partners_balance ولا يرحّل منه بيانات.
-- نفّذ على نسخة اختبار أولًا بعد مراجعة أسماء الجداول والأعمدة.

CREATE TABLE IF NOT EXISTS accounting_line_allocations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    debit_line_id BIGINT UNSIGNED NOT NULL,
    credit_line_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    allocation_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('ACTIVE','REVERSED') NOT NULL DEFAULT 'ACTIVE',
    note VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reversed_by INT DEFAULT NULL,
    reversed_at DATETIME DEFAULT NULL,
    reversal_reason VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_alloc_debit_active (debit_line_id, status),
    KEY idx_alloc_credit_active (credit_line_id, status),
    KEY idx_alloc_date (allocation_date),
    CONSTRAINT fk_alloc_debit_line FOREIGN KEY (debit_line_id)
        REFERENCES journal_entry_details(id),
    CONSTRAINT fk_alloc_credit_line FOREIGN KEY (credit_line_id)
        REFERENCES journal_entry_details(id),
    CONSTRAINT chk_alloc_amount_positive CHECK (amount > 0),
    CONSTRAINT chk_alloc_distinct_lines CHECK (debit_line_id <> credit_line_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- تحقق بعد الإنشاء: هذا الجدول لا ينقل أرصدة قديمة ولا يغيّر أي قيود قائمة.
SHOW CREATE TABLE accounting_line_allocations;
