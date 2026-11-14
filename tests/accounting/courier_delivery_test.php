<?php
/**
 * Integration test (needs a MariaDB with inc/Databases/createDatabase.sql imported as `cptest`).
 * usage: php tests/accounting/courier_delivery_test.php [host] [user] [pass]
 */
$host = $argv[1] ?? '127.0.0.1';
$user = $argv[2] ?? 'root';
$pass = $argv[3] ?? 'test';
$db = new PDO("mysql:host=$host;dbname=cptest;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec('SET FOREIGN_KEY_CHECKS=0');

require_once __DIR__ . '/../../inc/Classes/Accounting/AccountingEvents.php';

$fail = 0;
function check(string $name, bool $ok, string $extra = ''): void {
    global $fail;
    echo ($ok ? 'PASS' : 'FAIL') . "  $name" . ($extra && !$ok ? "  [$extra]" : '') . "\n";
    if (!$ok) { $fail++; }
}
function throwsMsg(callable $f, string $needle): bool {
    try { $f(); } catch (Throwable $e) { return strpos($e->getMessage(), $needle) !== false; }
    return false;
}

// ---- seed ----
$db->exec("INSERT INTO financial_years (year_name,date_from,date_to) VALUES ('T','2000-01-01','2099-12-31')");
$db->exec("INSERT INTO branches (COMPANIE_ID,BR_NAME,BR_MAIN) VALUES (1,'T',1)");
$branch = (int)$db->lastInsertId();
$db->exec("INSERT INTO stores (BRANCH_ID,STO_NAME,STTY_ID) VALUES ($branch,'S',2)");
$store = (int)$db->lastInsertId();
$db->exec("INSERT INTO client (CLIENT_NAME) VALUES ('Test Client')");
$client = (int)$db->lastInsertId();
$db->exec("INSERT INTO telephones (CLIENT_ID,WHATSAPP,TELEGRAM,TEL_LTYPE,TEL_NUMBER) VALUES ($client,0,0,'mobile','01000000001')");
$tel = (int)$db->lastInsertId();
$db->exec("INSERT INTO users (BRANCH_ID,FORENAME,LASTNAME,GENDER,USER_LOGIN,USER_PASSWORD,BIRTHDAY) VALUES ($branch,'t','t','M','t@example.com','x','2000-01-01')");
$userId = (int)$db->lastInsertId();
$_SESSION = ['USER_ID' => $userId, 'BRANCH_ID' => $branch];

function makeOrder(PDO $db, int $store, int $tel, int $status, float $ship, int $shippPayment, array $lines): int {
    static $n = 0; $n++;
    $db->exec("INSERT INTO orders (SUB_ORDER_ID,ORDER_TYPE_ID,ORDER_BY,CITY_ID,ADDRESS_ID,ADDRESS,TEL_ID,TELEPHONE,DELIVERY_DATE,SHIPPING_VAL,SHIPP_PAYMENT,ORDER_STATUS,ORDER_SUCCESS,STO_ID)
               VALUES ('T-$n',2,1,1,1,'a',$tel,'01000000001',NOW(),$ship,$shippPayment,$status,0,$store)");
    $id = (int)$db->lastInsertId();
    foreach ($lines as $i => [$qty, $price, $disc, $cost, $deliv]) {
        $db->exec("INSERT INTO order_product (ORDER_ID,PRO_CODE,QTY,PRICE,DISCOUNT,COST_PRICE,DELIVERED_QTY,RETURNED_QTY)
                   VALUES ($id,'P$i',$qty,$price,$disc,$cost,$deliv,0)");
    }
    return $id;
}
function entry(PDO $db, int $jid): array {
    $h = $db->query("SELECT total_debit,total_credit,status,reference_type FROM journal_entries WHERE id=$jid")->fetch();
    $l = $db->query("SELECT a.account_code,d.debit,d.credit,d.partner_type FROM journal_entry_details d JOIN accounts a ON a.id=d.account_id WHERE d.journal_entry_id=$jid ORDER BY d.line_no")->fetchAll();
    return [$h, $l];
}
function lineSum(array $lines, string $code, string $col): float {
    $s = 0.0; foreach ($lines as $x) { if ($x['account_code'] === $code) { $s += (float)$x[$col]; } } return $s;
}

$ev = new AccountingEvents($db);

// 1) fully delivered: 2 x (100-10) = 180 sales, cost 2x60 = 120, shipping 30
$o1 = makeOrder($db, $store, $tel, 3, 30, 1, [[2, 100, 10, 60, 2]]);
$j1 = $ev->courierDelivery($o1);
[$h, $l] = entry($db, $j1);
check('delivered: entry posted and balanced', $j1 > 0 && (float)$h['total_debit'] === (float)$h['total_credit'] && (float)$h['total_debit'] === 330.0, json_encode($h));
check('delivered: customers debit 210', lineSum($l, '1200', 'debit') === 210.0);
check('delivered: sales credit 180', lineSum($l, '4100', 'credit') === 180.0);
check('delivered: shipping revenue credit 30', lineSum($l, '4200', 'credit') === 30.0);
check('delivered: COGS debit 120 / inventory credit 120', lineSum($l, '5100', 'debit') === 120.0 && lineSum($l, '1300', 'credit') === 120.0);
check('delivered: order linked to entry', (int)$db->query("SELECT SALE_ENTRY_ID FROM orders WHERE ORDER_ID=$o1")->fetchColumn() === $j1);

// 2) duplicate call must not post twice
check('duplicate delivery is refused', throwsMsg(fn() => $ev->courierDelivery($o1), 'already posted'));
check('only one entry for the order', (int)$db->query("SELECT COUNT(*) FROM journal_entries WHERE reference_type='COURIER_DELIVERY' AND reference_id=$o1")->fetchColumn() === 1);

// 3) partial delivery: 1 of 2 delivered
$o2 = makeOrder($db, $store, $tel, 4, 30, 1, [[2, 100, 10, 60, 1]]);
[$h, $l] = entry($db, $ev->courierDelivery($o2));
check('partial: sales 90, cost 60, balanced', lineSum($l, '4100', 'credit') === 90.0 && lineSum($l, '5100', 'debit') === 60.0 && (float)$h['total_debit'] === (float)$h['total_credit']);

// 4) rejected after attempt: shipping paid -> revenue only; refused -> nothing
$o3 = makeOrder($db, $store, $tel, 5, 30, 1, [[2, 100, 10, 60, 0]]);
[$h, $l] = entry($db, $ev->courierDelivery($o3));
check('rejected+paid shipping: customers 30 / shipping revenue 30, no sales/COGS', lineSum($l, '1200', 'debit') === 30.0 && lineSum($l, '4200', 'credit') === 30.0 && lineSum($l, '4100', 'credit') === 0.0 && lineSum($l, '5100', 'debit') === 0.0);
$o4 = makeOrder($db, $store, $tel, 5, 30, 0, [[2, 100, 10, 60, 0]]);
check('rejected+shipping refused: nothing posted', $ev->courierDelivery($o4) === 0 && $db->query("SELECT SALE_ENTRY_ID FROM orders WHERE ORDER_ID=$o4")->fetchColumn() === null);

// 5) missing cost
$o5 = makeOrder($db, $store, $tel, 3, 0, 1, [[1, 100, 0, 0, 1]]);
check('missing cost is blocked by default', throwsMsg(fn() => $ev->courierDelivery($o5), 'cost price'));
check('blocked order left unposted', $db->query("SELECT SALE_ENTRY_ID FROM orders WHERE ORDER_ID=$o5")->fetchColumn() === null);
[$h, $l] = entry($db, $ev->courierDelivery($o5, false));
check('missing cost allowed by option: sales only, balanced', lineSum($l, '4100', 'credit') === 100.0 && lineSum($l, '5100', 'debit') === 0.0 && (float)$h['total_debit'] === (float)$h['total_credit']);

// 6) not yet delivered
$o6 = makeOrder($db, $store, $tel, 2, 30, 1, [[1, 100, 0, 50, 0]]);
check('order still in shipping is refused', throwsMsg(fn() => $ev->courierDelivery($o6), 'not been delivered'));

// 6b) reopening: reverse the delivery entry, then post again after the storekeeper edits
$orig = (int)$db->query("SELECT SALE_ENTRY_ID FROM orders WHERE ORDER_ID=$o2")->fetchColumn();
check('reverse requires a reason', throwsMsg(fn() => $ev->reverseCourierDelivery($o2, '  '), 'Reason'));
$rev = $ev->reverseCourierDelivery($o2, 'courier mistake');
check('reverse returns a new entry and clears the order link', $rev > 0 && $rev !== $orig && $db->query("SELECT SALE_ENTRY_ID FROM orders WHERE ORDER_ID=$o2")->fetchColumn() === null);
$net = $db->query("SELECT d.account_id, ROUND(SUM(d.debit-d.credit),2) AS n FROM journal_entry_details d WHERE d.journal_entry_id IN ($orig,$rev) GROUP BY d.account_id")->fetchAll();
check('original + reversal net to zero on every account', count($net) > 0 && count(array_filter($net, fn($r) => (float)$r['n'] !== 0.0)) === 0, json_encode($net));
check('original entry is kept (not deleted)', (int)$db->query("SELECT COUNT(*) FROM journal_entries WHERE id=$orig")->fetchColumn() === 1);
check('reverse twice is refused', throwsMsg(fn() => $ev->reverseCourierDelivery($o2, 'again'), 'no posted delivery'));
$db->exec("UPDATE order_product SET DELIVERED_QTY=2 WHERE ORDER_ID=$o2");   // storekeeper corrects: all delivered
$j2b = $ev->courierDelivery($o2);
[$h, $l] = entry($db, $j2b);
check('re-post after correction uses the corrected quantity (sales 180)', lineSum($l, '4100', 'credit') === 180.0 && (float)$h['total_debit'] === (float)$h['total_credit']);
$db->exec("UPDATE orders SET ORDER_SUCCESS=1 WHERE ORDER_ID=$o1");
check('collected order cannot be reversed', throwsMsg(fn() => $ev->reverseCourierDelivery($o1, 'x'), 'collected'));

// 6c) inventory reversal on reopen (RETURN_SHIPP -> REOPEN_RETURN_SHIPP)
require_once __DIR__ . '/../../inc/Classes/InventoryManager.php';
$im = new InventoryManager($db);
$o7 = makeOrder($db, $store, $tel, 4, 0, 1, [[2, 100, 0, 60, 1]]);
$db->exec("UPDATE order_product SET PRO_STATUSE='partial' WHERE ORDER_ID=$o7");
$db->exec("INSERT INTO stores_product (STO_ID,PRO_CODE,STO_QTY) VALUES ($store,'P0',10)");
$stock = fn() => (float)$db->query("SELECT STO_QTY FROM stores_product WHERE STO_ID=$store AND PRO_CODE='P0'")->fetchColumn();
$db->beginTransaction();
$im->processInventory('RETURN_SHIPP', [$o7], $userId, $branch, 'T-RS-1', $store);
$db->commit();
check('RETURN_SHIPP returned 1 rejected unit to stock (10 -> 11)', $stock() === 11.0, (string)$stock());
$db->beginTransaction();
$n = $im->reverseReturnShipp($o7, $userId, $branch, 'T-REV-1');
$db->commit();
check('reversal takes the unit back out (11 -> 10)', $n > 0 && $stock() === 10.0, (string)$stock());
$db->beginTransaction();
$n2 = $im->reverseReturnShipp($o7, $userId, $branch, 'T-REV-2');
$db->commit();
check('second reversal does nothing', $n2 === 0 && $stock() === 10.0);
check('original movement is kept alongside the reversal', (int)$db->query("SELECT COUNT(*) FROM inventory_movement WHERE REFERENCE='$o7' AND IM_MOVE_TYPE IN ('RETURN_SHIPP','REOPEN_RETURN_SHIPP')")->fetchColumn() === 2);
$db->exec("UPDATE stores_product SET STO_QTY=0 WHERE STO_ID=$store AND PRO_CODE='P0'");
$db->beginTransaction();
$im->processInventory('RETURN_SHIPP', [$o7], $userId, $branch, 'T-RS-2', $store);
$db->exec("UPDATE stores_product SET STO_QTY=0 WHERE STO_ID=$store AND PRO_CODE='P0'");  // stock already sold elsewhere
$blocked = throwsMsg(fn() => $im->reverseReturnShipp($o7, $userId, $branch, 'T-REV-3'), 'أقل من الكمية');
$db->rollBack();
check('reversal is refused when current stock is lower than the quantity to take back', $blocked);

// 6d) full reopen (CourierReopen): ledger + stock + custody + status
require_once __DIR__ . '/../../inc/Classes/CourierReopen.php';
$reopen = new CourierReopen($db);
function inCustody(PDO $db, int $oid, int $status, int $deliveryUser, bool $custodyRow): void {
    $db->exec("UPDATE orders SET EXISTS_ID=10, DELIVERY_ID=$deliveryUser, ORDER_STATUS=$status, ADDRESS='addr' WHERE ORDER_ID=$oid");
    if ($custodyRow) {
        $db->exec("INSERT INTO custody_orders (ORDER_ID,USER_ID,SHIPPING_ADDRESS,RETURNS,CUST_STAT,ORDER_STATUSE) VALUES ($oid,$deliveryUser,'addr',1,'DELIVERED',$status)");
        $db->exec("INSERT INTO custody_product (ORDER_ID,USER_ID,PRO_CODE,QTY,PRICE,DISCOUNT,DELIVERED_QTY,RETURNED_QTY,PRO_STATUSE) SELECT ORDER_ID,$deliveryUser,PRO_CODE,QTY,PRICE,DISCOUNT,DELIVERED_QTY,RETURNED_QTY,'partial' FROM order_product WHERE ORDER_ID=$oid");
    }
}
$db->exec("UPDATE stores_product SET STO_QTY=20 WHERE STO_ID=$store AND PRO_CODE='P0'");
$o8 = makeOrder($db, $store, $tel, 4, 30, 1, [[2, 100, 0, 60, 1]]);       // partial: 1 of 2 delivered
$db->exec("UPDATE order_product SET PRO_STATUSE='partial' WHERE ORDER_ID=$o8");
inCustody($db, $o8, 4, $userId, true);
$db->beginTransaction();
$im->processInventory('RETURN_SHIPP', [$o8], $userId, $branch, 'T-RS-8', $store);
$ev->courierDelivery($o8);
$db->commit();
$stockBefore = $stock();
check('reopen refused for an unauthorised group', throwsMsg(fn() => $reopen->reopen($o8, $userId, $branch, 'امين مخزن', 'x'), 'صلاحية'));
check('reopen requires a reason', throwsMsg(fn() => $reopen->reopen($o8, $userId, $branch, 'Owner', ' '), 'سبب'));
$res = $reopen->reopen($o8, $userId, $branch, 'Owner', 'خطأ في الكمية');
$ord = $db->query("SELECT ORDER_STATUS,EXISTS_ID,SALE_ENTRY_ID,DELIVERED_DATE FROM orders WHERE ORDER_ID=$o8")->fetch();
check('reopen: order back at the courier (status 2) with no ledger link', (int)$ord['ORDER_STATUS'] === 2 && (int)$ord['EXISTS_ID'] === 10 && $ord['SALE_ENTRY_ID'] === null && $ord['DELIVERED_DATE'] === null);
check('reopen: ledger reversed', $res['reverse_entry_id'] > 0);
check('reopen: rejected unit taken back out of stock', $stock() === $stockBefore - 1.0, (string)$stock());
$cu = $db->query("SELECT CUST_STAT,RETURNS,ORDER_STATUSE FROM custody_orders WHERE ORDER_ID=$o8")->fetch();
check('reopen: custody row is WAITING again', $cu && $cu['CUST_STAT'] === 'WAITING' && (int)$cu['RETURNS'] === 0 && (int)$cu['ORDER_STATUSE'] === 2);
check('reopen: quantities reset for the storekeeper', (int)$db->query("SELECT SUM(DELIVERED_QTY)+SUM(RETURNED_QTY) FROM order_product WHERE ORDER_ID=$o8")->fetchColumn() === 0 && (int)$db->query("SELECT COUNT(*) FROM custody_product WHERE ORDER_ID=$o8 AND PRO_STATUSE='waiting'")->fetchColumn() === 1);
check('reopen: logged with reason', (int)$db->query("SELECT COUNT(*) FROM orders_log WHERE ORDER_ID=$o8 AND LOG_TEXT LIKE '%خطأ في الكمية%'")->fetchColumn() === 1);
check('reopen: not allowed again until re-confirmed', throwsMsg(fn() => $reopen->reopen($o8, $userId, $branch, 'Owner', 'again'), 'ليس في عهدة'));

// rejected order whose custody row was removed at approval is rebuilt
$o9 = makeOrder($db, $store, $tel, 5, 30, 1, [[1, 100, 0, 60, 0]]);
inCustody($db, $o9, 5, $userId, false);
$ev->courierDelivery($o9);
$reopen->reopen($o9, $userId, $branch, 'المدير المالي', 'رفض بالخطأ');
check('reopen of a rejected order rebuilds the custody row', (int)$db->query("SELECT COUNT(*) FROM custody_orders WHERE ORDER_ID=$o9 AND CUST_STAT='WAITING'")->fetchColumn() === 1 && (int)$db->query("SELECT ORDER_STATUS FROM orders WHERE ORDER_ID=$o9")->fetchColumn() === 2);

// collected orders cannot be reopened
$o10 = makeOrder($db, $store, $tel, 3, 0, 1, [[1, 100, 0, 60, 1]]);
inCustody($db, $o10, 3, $userId, true);
$ev->courierDelivery($o10);
$db->exec("UPDATE orders SET ORDER_SUCCESS=1 WHERE ORDER_ID=$o10");
check('collected order cannot be reopened', throwsMsg(fn() => $reopen->reopen($o10, $userId, $branch, 'Owner', 'x'), 'تحصيل'));

// 7) DELIVERY partner lines (was broken: wrong partner column names)
$s = new AccountSettings($db);
$jm = new JournalManager($db);
$jm->reset()->create(['branch_id' => $branch, 'entry_date' => date('Y-m-d H:i:s'), 'reference_type' => 'TEST', 'reference_id' => 1, 'description' => 'partner test', 'created_by' => $userId]);
$jm->addLine(['account_id' => $s->deliveryCommission(), 'debit' => 10]);
$jm->addLine(['account_id' => $s->deliveryCommissionPayable(), 'partner_type' => 'DELIVERY', 'partner_id' => $userId, 'credit' => 10]);
check('journal accepts a DELIVERY partner line', $jm->post() > 0);

// 8) collection from courier: cash in, customer credited, commission accrued
$db->exec("INSERT INTO cash_boxes (branch_id, account_id, name) SELECT $branch, id, 'T' FROM accounts WHERE account_code='1110'");
$o11 = makeOrder($db, $store, $tel, 3, 30, 1, [[2, 100, 10, 60, 2]]);   // delivered, 210 receivable
$ev->courierDelivery($o11);
$jc = $ev->courierCollection($o11, $userId, 210.0, 3.0);
[$h, $l] = entry($db, $jc);
check('collection: balanced entry', $jc > 0 && (float)$h['total_debit'] === (float)$h['total_credit'] && (float)$h['total_debit'] === 213.0, json_encode($h));
check('collection: cash debit 210, customers credit 210', lineSum($l, '1110', 'debit') === 210.0 && lineSum($l, '1200', 'credit') === 210.0);
check('collection: commission 3 debit 5700 / credit 2210', lineSum($l, '5700', 'debit') === 3.0 && lineSum($l, '2210', 'credit') === 3.0);
check('collection: customer nets to zero after delivery + collection', (float)$db->query("SELECT ROUND(SUM(d.debit-d.credit),2) FROM journal_entry_details d JOIN accounts a ON a.id=d.account_id JOIN journal_entries e ON e.id=d.journal_entry_id WHERE a.account_code='1200' AND e.reference_id=$o11")->fetchColumn() === 0.0);
check('duplicate collection is refused', throwsMsg(fn() => $ev->courierCollection($o11, $userId, 210.0, 3.0), 'already posted'));
$o12 = makeOrder($db, $store, $tel, 3, 0, 1, [[1, 100, 0, 50, 1]]);   // delivery not yet posted
$jc2 = $ev->courierCollection($o12, $userId, 100.0, 0.0);
check('collection posts the delivery first when missing', $jc2 > 0 && $db->query("SELECT SALE_ENTRY_ID FROM orders WHERE ORDER_ID=$o12")->fetchColumn() !== null);
$o13 = makeOrder($db, $store, $tel, 5, 0, 0, [[1, 100, 0, 50, 0]]);
check('nothing to collect posts nothing', $ev->courierCollection($o13, $userId, 0.0, 0.0) === 0);

// 9) marketer + leader commission accrual, then payouts
$o14 = makeOrder($db, $store, $tel, 3, 0, 1, [[1, 100, 0, 50, 1]]);
$ev->courierDelivery($o14);
$jc3 = $ev->courierCollection($o14, $userId, 100.0, 0.0, true, [[$userId, 5.0], [$userId, 2.0]]);
[$h, $l] = entry($db, $jc3);
check('marketer commission: 7 debit 5710 / credit 2220, balanced', lineSum($l, '5710', 'debit') === 7.0 && lineSum($l, '2220', 'credit') === 7.0 && (float)$h['total_debit'] === (float)$h['total_credit'], json_encode($l));
$pj = $ev->commissionPayout('DELIVERY', $userId, 3.0, 9001);
[$h, $l] = entry($db, $pj);
check('delivery payout: debit 2210 / credit cash 3', lineSum($l, '2210', 'debit') === 3.0 && lineSum($l, '1110', 'credit') === 3.0 && $h['reference_type'] === 'DELIVERY_PAYOUT');
[$h, $l] = entry($db, $ev->commissionPayout('MARKETER', $userId, 7.0, 9002));
check('marketer payout: debit 2220 / credit cash 7', lineSum($l, '2220', 'debit') === 7.0 && lineSum($l, '1110', 'credit') === 7.0);
check('payout rejects zero and unknown type', throwsMsg(fn() => $ev->commissionPayout('DELIVERY', $userId, 0.0, 1), 'Invalid') && throwsMsg(fn() => $ev->commissionPayout('X', $userId, 1.0, 1), 'Unsupported'));

// 10) sales return after delivery/collection (delta posting, idempotent)
$o15 = makeOrder($db, $store, $tel, 3, 0, 1, [[2, 100, 10, 60, 2]]);
$ev->courierDelivery($o15);
$db->exec("UPDATE order_product SET RETURNED_QTY=1 WHERE ORDER_ID=$o15");
$db->exec("UPDATE orders SET ORDER_STATUS=7 WHERE ORDER_ID=$o15");
$rj = $ev->courierSalesReturn($o15);
[$h, $l] = entry($db, $rj);
check('return #1: sales returns 90 / customers 90 / inventory 60 / COGS 60', lineSum($l, '4100', 'debit') === 90.0 && lineSum($l, '1200', 'credit') === 90.0 && lineSum($l, '1300', 'debit') === 60.0 && lineSum($l, '5100', 'credit') === 60.0 && (float)$h['total_debit'] === (float)$h['total_credit']);
check('return: same state posts nothing again', $ev->courierSalesReturn($o15) === 0);
$db->exec("UPDATE order_product SET RETURNED_QTY=2 WHERE ORDER_ID=$o15");
$db->exec("UPDATE orders SET ORDER_STATUS=6 WHERE ORDER_ID=$o15");
[$h, $l] = entry($db, $ev->courierSalesReturn($o15));
check('return #2 posts only the delta (90 / 60)', lineSum($l, '4100', 'debit') === 90.0 && lineSum($l, '1300', 'debit') === 60.0);
$o16 = makeOrder($db, $store, $tel, 7, 0, 1, [[1, 100, 0, 50, 1]]);
$db->exec("UPDATE order_product SET RETURNED_QTY=1 WHERE ORDER_ID=$o16");
check('return of an order whose sale was never posted is skipped', $ev->courierSalesReturn($o16) === 0);

// 11) purchases and suppliers
$db->exec("INSERT INTO suppliers (SUP_NAME) VALUES ('Sup')");
$sup = (int)$db->lastInsertId();
$db->exec("INSERT INTO purchases_orders (SUPPLIER_ID,REFERANC,TOTAL_PRICE,STATUSE) VALUES ($sup,'R',500,'DEFERRED')");
$po = (int)$db->lastInsertId();
$db->exec("INSERT INTO purchases_products (ORDER_ID,PRO_CODE,PURCHASE_UNIT_PRICE,QTY,QTY_SUPPLIED) VALUES ($po,'P0',25,20,0)");
[$h, $l] = entry($db, $ev->supplierReceipt($po, $sup, $store, 500.0));
check('purchase receipt: inventory 500 / suppliers 500', lineSum($l, '1300', 'debit') === 500.0 && lineSum($l, '2100', 'credit') === 500.0 && $l[1]['partner_type'] === 'SUPPLIER');
[$h, $l] = entry($db, $ev->purchasePartialReceipt($po, [['ORDER_ID' => $po, 'PRO_CODE' => 'P0', 'QTY_SUPPLIED' => 4]], $store));
check('partial receipt: 4 x 25 = 100', lineSum($l, '1300', 'debit') === 100.0 && lineSum($l, '2100', 'credit') === 100.0);
[$h, $l] = entry($db, $ev->supplierReturn(7001, $sup, $store, 50.0));
check('purchase return: suppliers 50 debit / inventory 50 credit', lineSum($l, '2100', 'debit') === 50.0 && lineSum($l, '1300', 'credit') === 50.0);
[$h, $l] = entry($db, $ev->supplierPayment($sup, 200.0, 7002));
check('supplier payment: suppliers debit / cash credit 200', lineSum($l, '2100', 'debit') === 200.0 && lineSum($l, '1110', 'credit') === 200.0);
[$h, $l] = entry($db, $ev->supplierRefund($sup, 30.0, 7003));
check('supplier refund: cash debit / suppliers credit 30', lineSum($l, '1110', 'debit') === 30.0 && lineSum($l, '2100', 'credit') === 30.0);
check('supplier entry rejects zero amount', throwsMsg(fn() => $ev->supplierPayment($sup, 0.0, 1), 'Invalid'));

// 12) rejected order whose customer paid the shipping: shipping revenue, then cash collected
$o21 = makeOrder($db, $store, $tel, 5, 30, 1, [[1, 100, 0, 50, 0]]);
$ev->courierDelivery($o21);
$jr = $ev->courierCollection($o21, $userId, 30.0, 1.5);
[$h, $l] = entry($db, $jr);
check('rejected+paid shipping: cash 30 / customers 30, commission 1.5', lineSum($l, '1110', 'debit') === 30.0 && lineSum($l, '1200', 'credit') === 30.0 && lineSum($l, '5700', 'debit') === 1.5 && lineSum($l, '2210', 'credit') === 1.5);
check('rejected+paid shipping: customer nets to zero', (float)$db->query("SELECT ROUND(SUM(d.debit-d.credit),2) FROM journal_entry_details d JOIN accounts a ON a.id=d.account_id JOIN journal_entries e ON e.id=d.journal_entry_id WHERE a.account_code='1200' AND e.reference_id=$o21")->fetchColumn() === 0.0);
check('rejected+paid shipping: no sales/COGS posted', (float)$db->query("SELECT COUNT(*) FROM journal_entry_details d JOIN accounts a ON a.id=d.account_id JOIN journal_entries e ON e.id=d.journal_entry_id WHERE a.account_code IN ('4100','5100') AND e.reference_id=$o21")->fetchColumn() === 0.0);

// 13) backfill of an order closed before the GL existed
$o30 = makeOrder($db, $store, $tel, 3, 30, 1, [[2, 400, 0, 350, 2]]);
$db->exec("UPDATE orders SET ORDER_SUCCESS=1, DELIVERY_ID=$userId WHERE ORDER_ID=$o30");
$db->exec("INSERT INTO partners_balance (PARTNER_TYPE,PARTNER_ID,REFERENCE_ID,TRANSACTION_TYPE,BALANCE,NOTE) VALUES ('DELIVERY',$userId,$o30,'CREDIT',30,'x')");
$bf = $ev->backfillClosedCourierOrder($o30);
check('backfill: amount 830 and commission 30', $bf['amount'] === 830.0 && $bf['commission'] === 30.0, json_encode($bf));
[$h, $l] = entry($db, $bf['collection_entry_id']);
check('backfill: cash 830 / customers 830 / 5700 30 / 2210 30', lineSum($l, '1110', 'debit') === 830.0 && lineSum($l, '1200', 'credit') === 830.0 && lineSum($l, '5700', 'debit') === 30.0 && lineSum($l, '2210', 'credit') === 30.0);
check('backfill: second run is refused', throwsMsg(fn() => $ev->backfillClosedCourierOrder($o30), 'already posted'));
$o31 = makeOrder($db, $store, $tel, 3, 30, 1, [[1, 100, 0, 50, 1]]);
$db->exec("UPDATE orders SET DELIVERY_ID=$userId WHERE ORDER_ID=$o31");
check('backfill: refuses an order not closed financially', throwsMsg(fn() => $ev->backfillClosedCourierOrder($o31), 'غير مُقفل'));

echo $fail ? "\n$fail FAILED\n" : "\nALL PASSED\n";
exit($fail ? 1 : 0);
