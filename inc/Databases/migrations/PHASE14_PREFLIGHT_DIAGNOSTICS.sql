-- فحوصات قراءة فقط قبل تفعيل تحصيلات المندوبين.
-- نفّذ بعد PHASE12_PARTNER_LEDGER_AND_ALLOCATIONS.sql وPHASE14_DELIVERY_COLLECTION_AND_SETTLEMENT.sql.

-- 1) تحصيلات مالية مرحّلة بلا تخصيص على سطر مديونية: يجب مراجعتها قبل الاعتماد على رصيد الأمر.
SELECT
    ft.id AS transaction_id,
    ft.invoice_id,
    ft.entity_id AS customer_id,
    ft.amount,
    ft.accounting_entry_id,
    ft.transaction_date
FROM financial_transactions ft
WHERE ft.entity_type = 'CLIENT'
  AND ft.status = 'COMPLETED'
  AND COALESCE(ft.accounting_posted, 0) = 1
  AND ft.accounting_entry_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM journal_entry_details jd
      INNER JOIN accounting_line_allocations a
          ON a.credit_line_id = jd.id AND a.status = 'ACTIVE'
      WHERE jd.journal_entry_id = ft.accounting_entry_id
        AND jd.credit > 0
  )
ORDER BY ft.transaction_date, ft.id;

-- 2) أوامر عليها SALE_ENTRY_ID لكن لا يوجد سطر مدين على حساب العملاء.
SELECT o.ORDER_ID, o.SUB_ORDER_ID, o.SALE_ENTRY_ID, o.ORDER_STATUS
FROM orders o
WHERE o.SALE_ENTRY_ID IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM journal_entry_details jd
      INNER JOIN account_settings ast ON ast.setting_key = 'CUSTOMERS' AND ast.account_id = jd.account_id
      WHERE jd.journal_entry_id = o.SALE_ENTRY_ID
        AND jd.partner_type = 'CLIENT'
        AND jd.debit > 0
  )
ORDER BY o.ORDER_ID;

-- 3) تأكد أن حسابات إعدادات المندوب نشطة وقابلة للترحيل.
SELECT s.id, s.commission_settlement_mode,
       custody.is_active AS custody_active, custody.allow_posting AS custody_postable,
       expense.is_active AS expense_active, expense.allow_posting AS expense_postable,
       payable.is_active AS payable_active, payable.allow_posting AS payable_postable
FROM delivery_accounting_settings s
LEFT JOIN accounts custody ON custody.id = s.courier_custody_account_id
LEFT JOIN accounts expense ON expense.id = s.courier_commission_expense_account_id
LEFT JOIN accounts payable ON payable.id = s.courier_commission_payable_account_id;

-- 4) قيود تحصيل/تسوية لا تساوي مدينها دائنها.
SELECT id, entry_no, reference_type, reference_id, total_debit, total_credit
FROM journal_entries
WHERE reference_type IN (
    'DELIVERY_CUSTOMER_COLLECTION',
    'DELIVERY_COMMISSION_ACCRUAL',
    'DELIVERY_SETTLEMENT',
    'DELIVERY_COMMISSION_PAYMENT'
)
AND ABS(total_debit - total_credit) > 0.009
ORDER BY id DESC;
