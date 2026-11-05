-- المرحلة 15: مواءمة مخطط التشغيل مع كود دفتر الأستاذ العام الحالي.
-- MariaDB 10.5+ / InnoDB. نفّذ على نسخة اختبار بعد أخذ نسخة احتياطية.
-- إضافات غير هدامة؛ لا تحذف أو تعيد كتابة بيانات قديمة.
-- COST_PRICE يظل NULL للسجلات التاريخية التي لا نعرف تكلفتها وقت البيع.

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS SALE_ENTRY_ID BIGINT(20) UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS RETURN_ENTRY_ID BIGINT(20) UNSIGNED NULL;

ALTER TABLE orders
    ADD INDEX IF NOT EXISTS idx_orders_sale_entry (SALE_ENTRY_ID),
    ADD INDEX IF NOT EXISTS idx_orders_return_entry (RETURN_ENTRY_ID);

ALTER TABLE order_product
    ADD COLUMN IF NOT EXISTS COST_PRICE DECIMAL(18,2) NULL
    COMMENT 'تكلفة الوحدة وقت إنشاء أمر البيع';

ALTER TABLE financial_transactions
    ADD COLUMN IF NOT EXISTS accounting_posted TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS accounting_entry_id BIGINT(20) UNSIGNED NULL;

ALTER TABLE financial_transactions
    ADD INDEX IF NOT EXISTS idx_financial_accounting_entry (accounting_entry_id);



-- InventoryManager يعتمد على UNIQUE_ID لربط تفاصيل الحركة برأسها ومنع خلط الدفعات.
ALTER TABLE inventory_movement
    ADD COLUMN IF NOT EXISTS UNIQUE_ID VARCHAR(100) NULL COMMENT 'معرف تجميعي موحد لحركة المخزون',
    ADD INDEX IF NOT EXISTS idx_inventory_unique_id (UNIQUE_ID);

-- عمود مرجع الطلب مطلوب في كود InventoryManager لتتبع تفاصيل حركات البيع والشراء
-- وربط استلام مرتجع المبيعات بالطلب والصنف دون الاعتماد على نص REFERENCE وحده.
ALTER TABLE inventory_product_movement
    ADD COLUMN IF NOT EXISTS ORDER_ID BIGINT(20) NULL COMMENT 'مرجع الطلب التشغيلي المرتبط بالحركة؛ يشمل البيع والشراء',
    ADD INDEX IF NOT EXISTS idx_ipm_order_code (ORDER_ID, IPM_CODE);

-- فحوصات قراءة فقط بعد التنفيذ:
SHOW COLUMNS FROM orders LIKE 'SALE_ENTRY_ID';
SHOW COLUMNS FROM orders LIKE 'RETURN_ENTRY_ID';
SHOW COLUMNS FROM order_product LIKE 'COST_PRICE';
SHOW COLUMNS FROM financial_transactions LIKE 'accounting_posted';
SHOW COLUMNS FROM financial_transactions LIKE 'accounting_entry_id';

SELECT COUNT(*) AS order_product_rows_without_cost
FROM order_product
WHERE COST_PRICE IS NULL;

-- لا تنفذ UPDATE تلقائيًا على COST_PRICE للسجلات التاريخية؛ يلزم اعتماد طريقة تقييم التكلفة.
