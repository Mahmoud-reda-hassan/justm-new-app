-- PHASE17: حسابات مستقلة لعمولات المسوقين وقادة الفرق.
-- 2152 مستحقات (التزام/رصيد دائن)، و5230 مصروف عمولات (مصروف/رصيد مدين).
-- ينشئ الحسابين فقط عند عدم وجود كود الحساب، ثم يربطهما بإعدادات دفتر الأستاذ.
-- لا يتم دمج هذه الحسابات مع حسابات عمولات المندوبين.

INSERT INTO accounts
    (account_code, account_name, account_type, normal_balance, account_level,
     is_active, allow_posting, system_account, is_system_created, description)
SELECT
    '2152', 'مستحقات عمولات المسوقين وقادة الفرق', 'LIABILITY', 'CREDIT', 1,
    1, 1, 1, 1, 'مستحقات مستقلة لعمولات المسوقين وقادة الفرق'
WHERE NOT EXISTS (
    SELECT 1 FROM accounts WHERE account_code = '2152'
);

INSERT INTO accounts
    (account_code, account_name, account_type, normal_balance, account_level,
     is_active, allow_posting, system_account, is_system_created, description)
SELECT
    '5230', 'مصروف عمولات المسوقين وقادة الفرق', 'EXPENSE', 'DEBIT', 1,
    1, 1, 1, 1, 'مصروف مستقل لعمولات المسوقين وقادة الفرق'
WHERE NOT EXISTS (
    SELECT 1 FROM accounts WHERE account_code = '5230'
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
       'حساب مصروف عمولات المسوقين وقادة الفرق - 5230'
FROM accounts WHERE account_code = '5230'
ON DUPLICATE KEY UPDATE account_id = VALUES(account_id),
                        description = VALUES(description);

-- فحص نهائي: يجب أن يكون الحسابان نشطين وقابلين للترحيل وبطبيعة الرصيد الصحيحة.
SELECT account_code, account_name, account_type, normal_balance,
       is_active, allow_posting
FROM accounts
WHERE account_code IN ('2152', '5230')
ORDER BY account_code;
