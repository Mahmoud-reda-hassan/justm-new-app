-- PHASE19: ربط حسابات دورة الموردين وفق دليل الحسابات المعتمد.
-- لا ينشئ حسابات بديلة؛ يجب استيراد دليل الحسابات أولاً.
INSERT INTO account_settings (setting_key, account_id, description)
SELECT 'SUPPLIERS', id, 'حسابات الموردين الدائنة - 2111'
FROM accounts WHERE account_code = '2111'
ON DUPLICATE KEY UPDATE account_id = VALUES(account_id), description = VALUES(description);

INSERT INTO account_settings (setting_key, account_id, description)
SELECT 'INVENTORY_IN_TRANSIT', id, 'مخزون بالطريق للمشتريات - 1162'
FROM accounts WHERE account_code = '1162'
ON DUPLICATE KEY UPDATE account_id = VALUES(account_id), description = VALUES(description);

INSERT INTO account_settings (setting_key, account_id, description)
SELECT 'SUPPLIER_ADVANCES', id, 'دفعات مقدمة للموردين - 1171'
FROM accounts WHERE account_code = '1171'
ON DUPLICATE KEY UPDATE account_id = VALUES(account_id), description = VALUES(description);

SELECT aset.setting_key, a.account_code, a.account_name
FROM account_settings aset
INNER JOIN accounts a ON a.id = aset.account_id
WHERE aset.setting_key IN ('SUPPLIERS','INVENTORY_IN_TRANSIT','SUPPLIER_ADVANCES')
ORDER BY aset.setting_key;
