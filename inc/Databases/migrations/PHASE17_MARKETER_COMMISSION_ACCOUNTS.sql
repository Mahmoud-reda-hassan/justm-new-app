-- PHASE17: حسابات مستقلة لعمولات المسوقين وقادة الفرق.
-- 2152 مستحقات (التزام/رصيد دائن)، و5420 تسويق بالعمولة (مصروف/رصيد مدين).
-- ينشئ الحسابين فقط عند عدم وجود كود الحساب، ثم يربطهما بإعدادات دفتر الأستاذ.
-- لا يتم دمج هذه الحسابات مع حسابات عمولات المندوبين.

INSERT INTO accounts
    (parent_id, account_code, account_name, account_type, normal_balance, account_level,
     is_active, allow_posting, system_account, is_system_created, description)
SELECT
    (SELECT id FROM accounts WHERE account_code = '2150' LIMIT 1),
    '2152', 'مستحقات عمولات المسوقين وقادة الفرق', 'LIABILITY', 'CREDIT', 4,
    1, 1, 1, 1, 'مستحقات مستقلة لعمولات المسوقين وقادة الفرق'
WHERE EXISTS (SELECT 1 FROM accounts WHERE account_code = '2150')
  AND NOT EXISTS (
    SELECT 1 FROM accounts WHERE account_code = '2152'
);

INSERT INTO accounts
    (parent_id, account_code, account_name, account_type, normal_balance, account_level,
     is_active, allow_posting, system_account, is_system_created, description)
SELECT
    (SELECT id FROM accounts WHERE account_code = '5400' LIMIT 1),
    '5420', 'التسويق بالعمولة', 'EXPENSE', 'DEBIT', 3,
    1, 1, 1, 1, 'مصروف عمولات المسوقين وقادة الفرق'
WHERE EXISTS (SELECT 1 FROM accounts WHERE account_code = '5400')
  AND NOT EXISTS (
    SELECT 1 FROM accounts WHERE account_code = '5420'
);

-- ربط مفاتيح الحسابات بمعرفاتها الفعلية؛ لا توجد أرقام IDs ثابتة.
INSERT INTO account_settings (setting_key, account_id, description)
SELECT 'MARKETER_COMMISSION_PAYABLE', id,
       'حساب مستحقات عمولات المسوقين وقادة الفرق - 2152'
FROM accounts WHERE account_code = '2152'
ON DUPLICATE KEY UPDATE account_id = VALUES(account_id),
                        description = VALUES(description);

INSERT INTO account_settings (setting_key, account_id, description)
SELECT 'MARKETER_COMMISSION_EXPENSE', id,
       'حساب مصروف عمولات المسوقين وقادة الفرق - 5420'
FROM accounts WHERE account_code = '5420'
ON DUPLICATE KEY UPDATE account_id = VALUES(account_id),
                        description = VALUES(description);

-- فحص نهائي: يجب أن يكون الحسابان نشطين وقابلين للترحيل وبطبيعة الرصيد الصحيحة.
SELECT account_code, account_name, account_type, normal_balance,
       is_active, allow_posting
FROM accounts
WHERE account_code IN ('2152', '5420')
ORDER BY account_code;


-- مستندات صرف مستحقات المسوقين وقادة الفرق؛ القيود المالية في دفتر الأستاذ فقط.
CREATE TABLE IF NOT EXISTS marketer_commission_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_no VARCHAR(40) NULL,
    marketer_user_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    payment_method_id BIGINT UNSIGNED NOT NULL,
    payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    amount DECIMAL(15,2) NOT NULL,
    journal_id BIGINT UNSIGNED NULL,
    status ENUM('DRAFT','POSTED','REVERSED') NOT NULL DEFAULT 'DRAFT',
    note VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    request_key VARCHAR(64) NULL,
    request_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    posted_at DATETIME NULL,
    reversed_at DATETIME NULL,
    reversed_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_marketer_commission_payment_request_key (request_key),
    UNIQUE KEY uq_marketer_commission_payment_no (payment_no),
    KEY idx_marketer_commission_payment_partner (marketer_user_id,status,payment_date),
    KEY idx_marketer_commission_payment_journal (journal_id),
    CONSTRAINT chk_marketer_commission_payment_positive CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
