-- PHASE29: تحصيل رسوم الشحن مرة واحدة للطلب عند رفض العميل ودفعه الرسوم.
-- آمن لإعادة التشغيل ولا ينفذ أي ترحيل مالي تلقائي.
CREATE TABLE IF NOT EXISTS `delivery_rejected_shipping_collections` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `shipmov_id` INT(11) NOT NULL,
    `delivery_user_id` BIGINT UNSIGNED NOT NULL,
    `branch_id` INT(11) NOT NULL,
    `shipping_amount_snapshot` DECIMAL(15,2) NOT NULL,
    `accounting_event_id` BIGINT UNSIGNED NOT NULL,
    `journal_entry_id` BIGINT UNSIGNED NOT NULL,
    `delivery_collection_id` BIGINT UNSIGNED NULL,
    `status` ENUM('POSTED') NOT NULL DEFAULT 'POSTED',
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rejected_shipping_order` (`order_id`),
    UNIQUE KEY `uq_rejected_shipping_attempt` (`shipmov_id`),
    UNIQUE KEY `uq_rejected_shipping_event` (`accounting_event_id`),
    UNIQUE KEY `uq_rejected_shipping_journal` (`journal_entry_id`),
    UNIQUE KEY `uq_rejected_shipping_delivery_collection` (`delivery_collection_id`),
    KEY `idx_rejected_shipping_delivery` (`delivery_user_id`, `created_at`),
    KEY `idx_rejected_shipping_branch` (`branch_id`, `created_at`),
    CONSTRAINT `chk_rejected_shipping_amount_positive` CHECK (`shipping_amount_snapshot` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
