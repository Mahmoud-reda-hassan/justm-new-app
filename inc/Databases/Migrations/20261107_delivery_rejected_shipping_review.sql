CREATE TABLE IF NOT EXISTS delivery_rejected_shipping_reviews (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 shipmov_id INT(11) NOT NULL,
 order_id BIGINT UNSIGNED NOT NULL,
 delivery_user_id BIGINT UNSIGNED NOT NULL,
 branch_id INT(11) NOT NULL,
 shipping_amount_snapshot DECIMAL(15,2) NOT NULL,
 commission_percent_snapshot DECIMAL(9,4) NOT NULL,
 estimated_compensation DECIMAL(15,2) NOT NULL DEFAULT 0,
 approved_compensation DECIMAL(15,2) NOT NULL DEFAULT 0,
 status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
 accounting_event_id BIGINT UNSIGNED NULL,
 journal_entry_id BIGINT UNSIGNED NULL,
 decision_note VARCHAR(500) NULL,
 decided_by BIGINT UNSIGNED NULL,
 decided_at DATETIME NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 UNIQUE KEY uq_rejected_shipping_review_attempt (shipmov_id),
 UNIQUE KEY uq_rejected_shipping_review_event (accounting_event_id),
 UNIQUE KEY uq_rejected_shipping_review_journal (journal_entry_id),
 KEY idx_rejected_shipping_review_delivery (delivery_user_id,status,created_at),
 KEY idx_rejected_shipping_review_branch (branch_id,status,created_at),
 KEY idx_rejected_shipping_review_order (order_id),
 CONSTRAINT chk_rejected_shipping_review_amounts CHECK (
  shipping_amount_snapshot >= 0 AND commission_percent_snapshot >= 0
  AND commission_percent_snapshot <= 100 AND estimated_compensation >= 0
  AND approved_compensation >= 0
 ),
 CONSTRAINT chk_rejected_shipping_review_decision CHECK (
  (status='PENDING' AND approved_compensation=0 AND accounting_event_id IS NULL AND journal_entry_id IS NULL AND decided_by IS NULL)
  OR (status='REJECTED' AND approved_compensation=0 AND accounting_event_id IS NULL AND journal_entry_id IS NULL AND decided_by IS NOT NULL)
  OR (status='APPROVED' AND approved_compensation>0 AND accounting_event_id IS NOT NULL AND journal_entry_id IS NOT NULL AND decided_by IS NOT NULL)
 )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
