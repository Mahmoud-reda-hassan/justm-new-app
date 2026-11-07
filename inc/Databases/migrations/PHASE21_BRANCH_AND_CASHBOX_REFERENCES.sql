-- ================================================================
-- PHASE 21: Branch and cash-box relational foundation
-- Target: MariaDB 10.5+
-- Scope: one company now; schema remains compatible with multi-company
--       operation later. This migration does not create financial data.
-- Apply ONLY to databases created with the older installer (global BR_NAME
-- index and missing branch foreign keys). Do NOT run on a fresh database
-- created with the updated JUSTM_DATABASE_INSTALL.sql.
--
-- IMPORTANT:
-- 1) Take a full database backup first.
-- 2) Run the preflight queries below and require every count to be zero.
-- 3) This is a one-time migration; do not run it twice.
-- 4) No cash boxes, account mappings, or opening balances are inserted.
-- ================================================================

-- Preflight A: branches that share a name inside the same company.
-- Expected: no rows.
SELECT COMPANIE_ID, BR_NAME, COUNT(*) AS duplicate_count
FROM branches
GROUP BY COMPANIE_ID, BR_NAME
HAVING COUNT(*) > 1;

-- Preflight B: cash boxes referring to a missing branch.
-- Expected: 0.
SELECT COUNT(*) AS orphan_cash_boxes
FROM cash_boxes cb
LEFT JOIN branches b ON b.BR_ID = cb.branch_id
WHERE b.BR_ID IS NULL;

-- Preflight C: payment-method mappings referring to a missing branch.
-- Expected: 0.
SELECT COUNT(*) AS orphan_payment_method_accounts
FROM payment_method_accounts pma
LEFT JOIN branches b ON b.BR_ID = pma.branch_id
WHERE b.BR_ID IS NULL;

-- Preflight D: confirm the old global branch-name index exists before dropping it.
SHOW INDEX FROM branches WHERE Key_name = 'BR_NAME';

-- Replace global branch-name uniqueness with company-scoped uniqueness.
ALTER TABLE branches
    DROP INDEX BR_NAME,
    ADD UNIQUE KEY uq_branches_company_name (COMPANIE_ID, BR_NAME);

-- Enforce valid branch ownership for operational cash boxes and payment mappings.
ALTER TABLE cash_boxes
    ADD CONSTRAINT fk_cash_boxes_branch
        FOREIGN KEY (branch_id) REFERENCES branches (BR_ID)
        ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE payment_method_accounts
    ADD KEY idx_pma_branch_fk (branch_id),
    ADD CONSTRAINT fk_pma_branch
        FOREIGN KEY (branch_id) REFERENCES branches (BR_ID)
        ON UPDATE CASCADE ON DELETE RESTRICT;

-- Postflight: both queries must return zero.
SELECT COUNT(*) AS orphan_cash_boxes
FROM cash_boxes cb
LEFT JOIN branches b ON b.BR_ID = cb.branch_id
WHERE b.BR_ID IS NULL;

SELECT COUNT(*) AS orphan_payment_method_accounts
FROM payment_method_accounts pma
LEFT JOIN branches b ON b.BR_ID = pma.branch_id
WHERE b.BR_ID IS NULL;
