-- منع تكرار نفس طلب تأكيد الشحنة عند إعادة إرسال HTTP.
-- لا يمنع محاولة توصيل جديدة؛ الواجهة تنشئ request_key جديدًا بعد نجاح المحاولة.
CREATE TABLE IF NOT EXISTS `delivery_confirmation_requests` (
    `request_key` VARCHAR(64) NOT NULL,
    `request_hash` CHAR(64) NOT NULL,
    `session_id` VARCHAR(64) NOT NULL,
    `actor_id` BIGINT UNSIGNED NOT NULL,
    `branch_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM('PROCESSING','COMPLETED') NOT NULL DEFAULT 'PROCESSING',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME NULL,
    PRIMARY KEY (`request_key`),
    KEY `idx_delivery_confirmation_status_created` (`status`, `created_at`),
    KEY `idx_delivery_confirmation_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
