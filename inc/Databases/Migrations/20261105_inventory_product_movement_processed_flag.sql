-- إصلاح قواعد البيانات التي أُنشئت من نسخة Installer أقدم ولا تحتوي على IS_PROCESSED.
-- متوافق مع MariaDB 10.5، ويمكن إعادة تشغيله بأمان.

SET @has_is_processed = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'inventory_product_movement'
      AND COLUMN_NAME = 'IS_PROCESSED'
);

SET @ddl = IF(
    @has_is_processed = 0,
    'ALTER TABLE inventory_product_movement ADD COLUMN IS_PROCESSED TINYINT(1) NOT NULL DEFAULT 0 AFTER IPM_STO_QTY',
    'SELECT 1'
);

PREPARE add_inventory_processed_column FROM @ddl;
EXECUTE add_inventory_processed_column;
DEALLOCATE PREPARE add_inventory_processed_column;
