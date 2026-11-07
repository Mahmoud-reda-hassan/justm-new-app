#!/usr/bin/env python3
"""Static acceptance checks for Just-M. Does not replace MariaDB integration/E2E tests."""
from pathlib import Path
import re
import subprocess
import sys
from concurrent.futures import ThreadPoolExecutor

ROOT = Path(__file__).resolve().parents[1]
PASS = []
FAIL = []

def check(name, condition, detail=''):
    (PASS if condition else FAIL).append((name, detail))

def read(rel):
    return (ROOT / rel).read_text(encoding='utf-8', errors='replace')

# 1) Required runtime columns must exist in the clean installer and upgrade migration.
installer = read('inc/Databases/JUSTM_DATABASE_INSTALL.sql')
create_db = read('inc/Databases/createDatabase.sql')
migration = read('inc/Databases/migrations/PHASE15_ACCOUNTING_SCHEMA_ALIGNMENT.sql')

def table_block(sql, table):
    m = re.search(r'CREATE TABLE IF NOT EXISTS\s+`' + re.escape(table) + r'`\s*\((.*?)\) ENGINE=', sql, re.S | re.I)
    return m.group(1) if m else ''

im = table_block(installer, 'inventory_movement')
ipm = table_block(installer, 'inventory_product_movement')
check('DDL inventory_movement.UNIQUE_ID', '`UNIQUE_ID`' in im and 'idx_inventory_unique_id' in im)
check('DDL inventory_product_movement.ORDER_ID', '`ORDER_ID`' in ipm and 'idx_ipm_order_code' in ipm)
check('DDL createDatabase inventory columns', '`UNIQUE_ID`' in table_block(create_db, 'inventory_movement') and '`ORDER_ID`' in table_block(create_db, 'inventory_product_movement'))
check('createDatabase includes new ledger tables', all(('CREATE TABLE IF NOT EXISTS `' + t + '`') in create_db for t in ['accounts','journal_entries','accounting_line_allocations','delivery_customer_collections','purchases_payments','supplier_payment_allocations']))
relations = read('inc/Databases/createRelations.sql')
check('separate relation script generated', len(re.findall(r'(?m)^ALTER TABLE ', relations)) == 42)
for fragment in [
    'ADD COLUMN IF NOT EXISTS SALE_ENTRY_ID',
    'ADD COLUMN IF NOT EXISTS RETURN_ENTRY_ID',
    'ADD COLUMN IF NOT EXISTS COST_PRICE',
    'ADD COLUMN IF NOT EXISTS accounting_posted',
    'ADD COLUMN IF NOT EXISTS accounting_entry_id',
    'ADD COLUMN IF NOT EXISTS UNIQUE_ID',
    'ADD COLUMN IF NOT EXISTS ORDER_ID',
]:
    check('Migration contains ' + fragment, fragment in migration)

# 1b) Branch and cash-box ownership integrity.
branches = table_block(installer, 'branches')
cash_boxes = table_block(installer, 'cash_boxes')
pma = table_block(installer, 'payment_method_accounts')
check('Branch names are unique within company', 'uq_branches_company_name' in branches and 'UNIQUE KEY `BR_NAME`' not in branches)
check('Cash boxes reference an existing branch', 'fk_cash_boxes_branch' in cash_boxes)
check('Payment-method mappings reference an existing branch', 'fk_pma_branch' in pma)
check('Root and nested installers are synchronized', read('JUSTM_DATABASE_INSTALL.sql') == installer)
cash_settings = read('inc/Classes/Accounting/AccountSettings.php')
cash_method = re.search(r'public function cash\(int \$branchId\): int(.*?)public function transactionAccount', cash_settings, re.S)
check('Cash selection rejects multiple active boxes', bool(cash_method) and 'count($rows) > 1' in cash_method.group(1) and 'LIMIT 1' not in cash_method.group(1))
phase21 = read('inc/Databases/migrations/PHASE21_BRANCH_AND_CASHBOX_REFERENCES.sql')
check('Legacy branch/cash-box migration includes orphan preflight', 'orphan_cash_boxes' in phase21 and 'orphan_payment_method_accounts' in phase21)
check('Phase 21 migration adds branch foreign keys', 'fk_cash_boxes_branch' in phase21 and 'fk_pma_branch' in phase21)
check('Journal lines store cash-box identity', '`cash_box_id` BIGINT UNSIGNED DEFAULT NULL' in table_block(installer, 'journal_entry_details'))
check('Journal cash-box foreign key exists', 'fk_journal_detail_cash_box' in table_block(installer, 'journal_entry_details'))
journal_manager = read('inc/Classes/Accounting/JournalManager.php')
check('JournalManager validates cash-box branch and account', 'cash_box_id' in journal_manager and 'الخزينة المحددة غير نشطة' in journal_manager)
check('JournalManager persists cash-box reference', 'cash_box_id,' in journal_manager and "$line['cash_box_id']" in journal_manager)
journal_lines = journal_manager.split('protected function saveLines', 1)[1].split('protected function generateEntryNumber', 1)[0]
check('JournalManager cash-box insert has 14 matching placeholders', '(?,?,?,?,?,?,?,?,?,?,?,?,?,?)' in journal_lines)
# 1c) Courier settlement must identify the actual receiving cash box.
cash_box_service = read('inc/Classes/Accounting/CashBox.php')
settlement_manager = read('inc/Classes/Accounting/DeliverySettlementManager.php')
finance_controller = read('inc/controller/finance.control.php')
settlement_js = read('dist/js/DeliverySettlement.js')
settlement_view = read('inc/Views/ReceivePayments.php')
check('CashBox lists only active boxes mapped to branch payment account',
      'listForPaymentMethod' in cash_box_service and 'pma.account_id = cb.account_id' in cash_box_service
      and 'cb.branch_id = ? AND cb.is_active = 1' in cash_box_service)
check('Courier settlement validates selected box against payment mapping',
      'cash_box_id' in settlement_manager and 'pma.payment_method_id = ?' in settlement_manager
      and 'الخزينة المحددة غير نشطة' in settlement_manager)
check('Courier settlement stores cash-box ID on received cash journal line',
      "'cash_box_id' => $cashBoxId" in settlement_manager)
check('Finance endpoint returns branch cash boxes and requires selected box',
      'GetDeliverySettlementCashBoxes' in finance_controller and "'cash_box_id' => $cashBoxId" in finance_controller
      and 'INNER JOIN cash_boxes cb' in finance_controller and 'SELECT DISTINCT pm.id' in finance_controller)
check('Courier settlement UI requires and sends selected cash box',
      'id="dsCashBox"' in settlement_view and 'cash_box_id: cashBoxId' in settlement_js
      and 'GetDeliverySettlementCashBoxes' in settlement_js)

phase22 = read('inc/Databases/migrations/PHASE22_JOURNAL_CASHBOX_REFERENCE.sql')
check('Phase 22 migration adds nullable cash-box reference', 'ADD COLUMN cash_box_id BIGINT UNSIGNED NULL' in phase22 and 'fk_journal_detail_cash_box' in phase22)
migration_manager = read('inc/Classes/DatabaseMigrationManager.php')
check('Phase 22 is registered in migration manager', 'PHASE22_JOURNAL_CASHBOX_REFERENCE.sql' in migration_manager)

# 2) Accounting safeguards and approved shipping behavior.
accounting = read('inc/Classes/Accounting/AccountingEvents.php')
check('Per-line delivered cost validation', 'INVALID_COST_LINES_COUNT' in accounting and "COST_PRICE IS NULL OR op.COST_PRICE <= 0" in accounting)
check('Sales journal idempotency marker', 'SALE_ENTRY_ID' in accounting and 'WHERE ORDER_ID = ? AND SALE_ENTRY_ID IS NULL' in accounting)
check('Return stock receipt gate', 'assertSalesReturnStockReceived' in accounting and "IM_MOVE_TYPE = 'RETURN_SALES'" in accounting)
check('Shipping revenue mapped separately', 'shippingRevenue()' in accounting and "'SHIPPING_REVENUE'" in read('inc/Classes/Accounting/AccountSettings.php'))
check('Sales journal uses order branch', "'branch_id'      => (int)$order['BRANCH_ID']" in accounting)
mailer = read('inc/Classes/Mailer.php')
check('SMTP mailer uses server configuration', 'JUSTM_SMTP_HOST' in mailer and 'JUSTM_SMTP_PASSWORD' in mailer)
check('SMTP credentials are not hard-coded in legacy mail files', 'pogy25502984' not in read('inc/public_functionsold_.php') and 'pogy25502984' not in read('inc/controller/BackEnd/sendMail_BE.php'))

# 3) Supplier payments: one payment header, mixed invoice/advance allocation, and safeguards.
supplier_finance = read('inc/controller/BackEnd/finance_BE.php')
supplier_js = read('dist/js/PaymentDisbursement.js')
supplier_view = read('inc/Views/PaymentDisbursement.php')
supplier_migration = read('inc/Databases/migrations/PHASE16_SUPPLIER_PAYMENT_MIXED_ALLOCATION.sql')
check('Supplier allocation migration exists', 'supplier_payment_allocations' in supplier_migration and "'INVOICE','ADVANCE'" in supplier_migration)
check('Supplier payment records one financial transaction', "Add_transaction(null, 'SUPPLIER'" in supplier_finance and 'supplier_payment_allocations' in supplier_finance)
check('Supplier invoice ownership and outstanding validation', "SUPPLIER_ID'] !== $supplier_id" in supplier_finance and '$value - $remaining > 0.009' in supplier_finance)
check('Supplier invoice payment updates purchases_payments', 'INSERT INTO purchases_payments' in supplier_finance and 'TOTAL_PAYMENTS = ?' in supplier_finance)
check('Supplier advance recorded separately', "'ADVANCE'" in supplier_finance and "'مقدم للمورد" in supplier_finance)
check('Supplier payment uses credit side of partner ledger', "VALUES ('SUPPLIER', ?, ?, 'CREDIT'" in supplier_finance)
check('Supplier UI exposes invoice allocation table', '<table id="supplierOrdersTable"' in supplier_view and '<!-- جدول أوامر الشراء' not in supplier_view)
check('Supplier auto-distribution preserves excess as advance', 'function autoDistributeSupplierPayment(amount)' in supplier_js and "$('#supplier-advance-total')" in supplier_js)

# 3) Legacy financial tables must remain during staged migration.
for sql_rel in ['JUSTM_DATABASE_INSTALL.sql', 'inc/Databases/JUSTM_DATABASE_INSTALL.sql']:
    sql = read(sql_rel).upper()
    check(sql_rel + ' retains legacy tables', all('DROP TABLE IF EXISTS `' + t + '`' not in sql for t in ['partners_balance','invoices','financial_transactions']))

# 4) Syntax checks: all PHP outside vendor; all JS except source fragments intentionally concatenated by jQuery build.
php_files = [p for p in ROOT.rglob('*.php') if p.is_file() and 'vendor' not in p.parts]
def lint_php(p):
    r = subprocess.run(['php', '-l', str(p)], stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, text=True)
    return (str(p.relative_to(ROOT)), r.stderr.strip()) if r.returncode else None
with ThreadPoolExecutor(max_workers=12) as pool:
    php_bad = [x for x in pool.map(lint_php, php_files) if x]
check('PHP syntax (' + str(len(php_files)) + ' files)', not php_bad, '; '.join(x[0] for x in php_bad[:8]))

js_files = [p for p in ROOT.rglob('*.js') if p.is_file() and 'vendor' not in p.parts and p.name not in {'intro.js','outro.js'}]
def lint_js(p):
    r = subprocess.run(['node', '--check', str(p)], stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, text=True)
    return (str(p.relative_to(ROOT)), r.stderr.strip()) if r.returncode else None
with ThreadPoolExecutor(max_workers=12) as pool:
    js_bad = [x for x in pool.map(lint_js, js_files) if x]
check('JavaScript syntax (' + str(len(js_files)) + ' files)', not js_bad, '; '.join(x[0] for x in js_bad[:8]))

print('Just-M static acceptance checks')
print('PASS:', len(PASS), 'FAIL:', len(FAIL))
for name, detail in PASS:
    print('[PASS]', name)
for name, detail in FAIL:
    print('[FAIL]', name, detail)
sys.exit(1 if FAIL else 0)
