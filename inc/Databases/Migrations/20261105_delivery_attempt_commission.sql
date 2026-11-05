-- المرحلة 25: عمولة مستقلة لكل محاولة رفض توصيل فعلية.
-- shipmov_id هو مفتاح المحاولة؛ لا نستخدم order_id لمنع تكرار عمولة محاولات لاحقة.
CREATE TABLE IF NOT EXISTS `delivery_attempt_commissions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `shipmov_id` INT(11) NOT NULL,
    `order_id` BIGINT(20) NOT NULL,
    `delivery_user_id` BIGINT(20) NOT NULL,
    `branch_id` INT(11) NOT NULL,
    `shipping_amount_snapshot` DECIMAL(15,2) NOT NULL,
    `commission_percent_snapshot` DECIMAL(9,4) NOT NULL,
    `commission_amount` DECIMAL(15,2) NOT NULL,
    `accounting_event_id` BIGINT UNSIGNED DEFAULT NULL,
    `journal_entry_id` BIGINT UNSIGNED DEFAULT NULL,
    `status` ENUM('POSTED','NO_COMMISSION') NOT NULL,
    `created_by` BIGINT(20) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_delivery_attempt_commission_shipmov` (`shipmov_id`),
    UNIQUE KEY `uq_delivery_attempt_commission_event` (`accounting_event_id`),
    UNIQUE KEY `uq_delivery_attempt_commission_journal` (`journal_entry_id`),
    KEY `idx_delivery_attempt_commission_delivery` (`delivery_user_id`,`status`,`created_at`),
    KEY `idx_delivery_attempt_commission_order` (`order_id`),
    CONSTRAINT `chk_delivery_attempt_shipping_nonnegative` CHECK (`shipping_amount_snapshot` >= 0),
    CONSTRAINT `chk_delivery_attempt_percent_range` CHECK (`commission_percent_snapshot` >= 0 AND `commission_percent_snapshot` <= 100),
    CONSTRAINT `chk_delivery_attempt_commission_nonnegative` CHECK (`commission_amount` >= 0),
    CONSTRAINT `chk_delivery_attempt_commission_journal` CHECK (
        (`status` = 'POSTED' AND `commission_amount` > 0 AND `accounting_event_id` IS NOT NULL AND `journal_entry_id` IS NOT NULL)
        OR
        (`status` = 'NO_COMMISSION' AND `commission_amount` = 0 AND `accounting_event_id` IS NULL AND `journal_entry_id` IS NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
