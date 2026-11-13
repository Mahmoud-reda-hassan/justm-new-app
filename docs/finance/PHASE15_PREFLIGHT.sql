-- فحوصات قبل تفعيل المحاسبة الجديدة (قراءة فقط).
SELECT 'orders.SALE_ENTRY_ID' AS required_column, COUNT(*) AS found
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'SALE_ENTRY_ID'
UNION ALL
SELECT 'orders.RETURN_ENTRY_ID', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'RETURN_ENTRY_ID'
UNION ALL
SELECT 'order_product.COST_PRICE', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_product' AND COLUMN_NAME = 'COST_PRICE'
UNION ALL
SELECT 'financial_transactions.accounting_posted', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'financial_transactions' AND COLUMN_NAME = 'accounting_posted'
UNION ALL
SELECT 'financial_transactions.accounting_entry_id', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'financial_transactions' AND COLUMN_NAME = 'accounting_entry_id';

SELECT setting_key, account_id, description FROM account_settings ORDER BY setting_key;
SELECT id, branch_id, account_id, box_name, is_active FROM cash_boxes ORDER BY branch_id, id;
SELECT payment_method_id, branch_id, account_id, is_active FROM payment_method_accounts ORDER BY branch_id, payment_method_id;
SELECT COUNT(*) AS products_without_cost FROM order_product WHERE COST_PRICE IS NULL;

SELECT setting_key, account_id, description FROM account_settings WHERE setting_key = 'SHIPPING_REVENUE';
