-- المرحلة 13: إنشاء حساب مستقل لمردودات المبيعات في دليل الحسابات الجديد.
-- لا يستخدم أي جدول قديم أو تصنيف حركة قديم.
-- الكود 4110 مقترح ضمن دليل الحسابات الحالي (4100 مبيعات، 4200 شحن، 4300 إيرادات أخرى).
-- راجع الدليل المالي قبل التنفيذ على الإنتاج.

START TRANSACTION;

INSERT INTO accounts
    (parent_id, account_code, account_name, account_type, normal_balance,
     account_level, is_active, allow_posting, system_account, is_system_created,
     description, created_at, updated_at)
SELECT
    16, '4110', 'مردودات المبيعات', 'REVENUE', 'DEBIT',
    2, 1, 1, 1, 1,
    'حساب مقابل للإيرادات لتسجيل مردودات المبيعات', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM accounts WHERE account_code = '4110'
);

-- لا نحدّث الإعداد إلا إذا كان الحساب 4110 موجودًا ونشطًا وقابلًا للترحيل.
UPDATE account_settings s
JOIN accounts a ON a.account_code = '4110'
               AND a.parent_id = 16
               AND a.account_name = 'مردودات المبيعات'
               AND a.account_type = 'REVENUE'
               AND a.normal_balance = 'DEBIT'
               AND a.is_active = 1
               AND a.allow_posting = 1
SET s.account_id = a.id,
    s.description = 'مردودات المبيعات',
    s.updated_at = NOW()
WHERE s.setting_key = 'SALES_RETURNS';

-- تحقق من النتيجة قبل اعتماد المعاملة.
SELECT s.setting_key, a.id AS account_id, a.account_code,
       a.account_name, a.account_type, a.normal_balance,
       a.is_active, a.allow_posting
FROM account_settings s
JOIN accounts a ON a.id = s.account_id
WHERE s.setting_key IN ('SALES', 'SALES_RETURNS');

-- راجع النتيجة ثم نفّذ COMMIT يدويًا، أو ROLLBACK إذا كانت غير صحيحة.
-- COMMIT;
-- ROLLBACK;
