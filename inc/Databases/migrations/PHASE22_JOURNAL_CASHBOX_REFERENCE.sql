-- ================================================================
-- PHASE 22: Preserve the physical cash-box identity on GL journal lines
-- Target: MariaDB 10.5+
-- Safe additive migration. Existing journal lines remain NULL.
-- Requires PHASE21 branch ownership constraints (or equivalent FK).
-- ================================================================

-- Preflight: the target column must not already exist.
SELECT COUNT(*) AS existing_cash_box_column
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'journal_entry_details'
  AND COLUMN_NAME = 'cash_box_id';

-- Preflight: cash_boxes and journal_entry_details must exist.
SELECT COUNT(*) AS required_tables
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('cash_boxes', 'journal_entry_details');

-- Add nullable reference; historical entries are not assigned guessed boxes.
ALTER TABLE journal_entry_details
    ADD COLUMN cash_box_id BIGINT UNSIGNED NULL AFTER branch_id,
    ADD KEY idx_journal_detail_cash_box (cash_box_id),
    ADD CONSTRAINT fk_journal_detail_cash_box
        FOREIGN KEY (cash_box_id) REFERENCES cash_boxes (id)
        ON UPDATE CASCADE ON DELETE RESTRICT;

-- Postflight: the new column and FK must exist.
SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'journal_entry_details'
  AND COLUMN_NAME = 'cash_box_id';

SELECT CONSTRAINT_NAME
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE()
  AND TABLE_NAME = 'journal_entry_details'
  AND CONSTRAINT_NAME = 'fk_journal_detail_cash_box';
