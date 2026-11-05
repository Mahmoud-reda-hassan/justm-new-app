-- المرحلة 21: حجز مفتاح التحصيل على مستوى الحمولة الكاملة.
-- آمن لإعادة التشغيل على MariaDB 10.5 باستخدام CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS delivery_collection_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_key VARCHAR(64) NOT NULL,
  request_hash CHAR(64) NOT NULL,
  delivery_user_id BIGINT UNSIGNED NOT NULL,
  branch_id BIGINT UNSIGNED NOT NULL,
  payment_method_id BIGINT UNSIGNED NOT NULL,
  total_amount DECIMAL(15,2) NOT NULL,
  order_count INT UNSIGNED NOT NULL,
  note VARCHAR(500) NULL,
  status ENUM('PROCESSING','POSTED') NOT NULL DEFAULT 'PROCESSING',
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_delivery_collection_request_key (request_key),
  KEY idx_delivery_collection_request_delivery (delivery_user_id, created_at),
  CONSTRAINT chk_delivery_collection_request_total CHECK (total_amount > 0),
  CONSTRAINT chk_delivery_collection_request_orders CHECK (order_count > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delivery_collection_request_collections (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id BIGINT UNSIGNED NOT NULL,
  collection_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  customer_amount DECIMAL(15,2) NOT NULL,
  commission_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_delivery_request_order (request_id, order_id),
  UNIQUE KEY uq_delivery_request_collection (collection_id),
  KEY idx_delivery_request_collection_request (request_id),
  CONSTRAINT fk_delivery_request_collection_request FOREIGN KEY (request_id) REFERENCES delivery_collection_requests (id),
  CONSTRAINT fk_delivery_request_collection_document FOREIGN KEY (collection_id) REFERENCES delivery_customer_collections (id),
  CONSTRAINT chk_delivery_request_collection_amount CHECK (customer_amount > 0),
  CONSTRAINT chk_delivery_request_collection_commission CHECK (commission_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
