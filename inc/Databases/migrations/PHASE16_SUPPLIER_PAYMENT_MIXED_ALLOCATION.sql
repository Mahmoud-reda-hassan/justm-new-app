-- PHASE16: توثيق توزيع دفعات الموردين بين فواتير ومقدم غير مخصص.
-- لا يعتمد على مفاتيح خارجية حتى لا يتعطل التثبيت على قواعد البيانات القديمة.
CREATE TABLE IF NOT EXISTS `supplier_payment_allocations` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `financial_transaction_id` INT(11) NOT NULL,
  `supplier_id` BIGINT(20) NOT NULL,
  `purchase_order_id` BIGINT(20) DEFAULT NULL,
  `allocation_type` ENUM('INVOICE','ADVANCE') NOT NULL,
  `amount` DECIMAL(18,2) NOT NULL,
  `created_by` BIGINT(20) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_supplier_payment_invoice` (`financial_transaction_id`,`purchase_order_id`,`allocation_type`),
  KEY `idx_supplier_payment_transaction` (`financial_transaction_id`),
  KEY `idx_supplier_payment_supplier` (`supplier_id`,`created_at`),
  KEY `idx_supplier_payment_order` (`purchase_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
