-- PHASE25: one-time bridge for already-posted customer refund journals.
-- Runtime refund calculations no longer read invoices or financial_transactions.
-- Preserve historical refunds already posted to GL so the new refund screen cannot pay them twice.
-- This migration does not create or post journal entries and is safe to rerun.

INSERT INTO customer_refund_payments
    (refund_no, order_id, customer_id, branch_id, payment_method_id, refund_date,
     amount, journal_id, status, created_by, request_key, request_hash, posted_at)
SELECT
    CONCAT('LEGACY-CR-', h.id) AS refund_no,
    i.REFERENCE_ID AS order_id,
    ft.entity_id AS customer_id,
    ft.branch_id,
    ft.payment_method_id,
    h.entry_date AS refund_date,
    posted_refund.amount,
    h.id AS journal_id,
    'POSTED',
    COALESCE(ft.transaction_user_id, 0) AS created_by,
    CONCAT('LEGACY-RETURN-JOURNAL:', h.id) AS request_key,
    SHA2(CONCAT_WS('|', h.id, ft.id, i.REFERENCE_ID, ft.entity_id,
                   ft.branch_id, ft.payment_method_id, posted_refund.amount), 256) AS request_hash,
    h.entry_date AS posted_at
FROM journal_entries h
INNER JOIN financial_transactions ft
    ON h.reference_type = 'SALES_RETURN_PAYMENT'
   AND h.reference_id = ft.id
   AND ft.status = 'COMPLETED'
   AND ft.entity_type = 'CLIENT'
   AND ft.entity_id > 0
   AND ft.branch_id > 0
   AND ft.payment_method_id > 0
INNER JOIN invoices i
    ON i.INVO_ID = ft.invoice_id
   AND i.REFERENCE_TYPE = 'SALES'
INNER JOIN orders o
    ON o.ORDER_ID = i.REFERENCE_ID
INNER JOIN (
    SELECT d.journal_entry_id, d.partner_id, SUM(d.debit) AS amount
    FROM journal_entry_details d
    INNER JOIN account_settings aset
        ON aset.account_id = d.account_id
       AND aset.setting_key IN ('CUSTOMERS','CUSTOMER_CREDITS')
    WHERE d.partner_type = 'CLIENT' AND d.debit > 0
    GROUP BY d.journal_entry_id, d.partner_id
) posted_refund
    ON posted_refund.journal_entry_id = h.id
   AND posted_refund.partner_id = ft.entity_id
WHERE h.status = 'POSTED'
  AND posted_refund.amount > 0
  AND NOT EXISTS (
      SELECT 1
      FROM customer_refund_payments existing
      WHERE existing.journal_id = h.id
         OR existing.request_key = CONCAT('LEGACY-RETURN-JOURNAL:', h.id)
  )
ON DUPLICATE KEY UPDATE
    request_key = VALUES(request_key);
