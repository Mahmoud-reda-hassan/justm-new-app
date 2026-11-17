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

// 14) opening balances (legacy -> GL alignment)
require_once __DIR__ . '/../../inc/Classes/Accounting/OpeningBalanceManager.php';
$ob = new OpeningBalanceManager($db);
$db->exec("INSERT INTO partners_balance (PARTNER_TYPE,PARTNER_ID,REFERENCE_ID,TRANSACTION_TYPE,BALANCE,NOTE) VALUES ('MARKETER',$userId,NULL,'CREDIT',120,'legacy'),('SUPPLIER',$sup,NULL,'CREDIT',900,'legacy')");
$rows = $ob->preview('MARKETER');
check('opening: marketer difference is 120 before posting', count($rows) >= 1 && abs(array_sum(array_column(array_filter($rows, fn($r) => $r['id'] === $userId), 'diff')) - 120.0) < 0.01, json_encode($rows));
$ejid = $ob->post('MARKETER', $userId, $branch, $userId);
[$h, $l] = entry($db, $ejid);
check('opening: marketer entry Cr 2220 120 / Dr 3300 120', lineSum($l, '2220', 'credit') === 120.0 && lineSum($l, '3300', 'debit') === 120.0 && $h['reference_type'] === 'OPENING_BALANCE');
check('opening: second post does nothing', $ob->post('MARKETER', $userId, $branch, $userId) === 0);
$supRows = array_values(array_filter($ob->preview('SUPPLIER'), fn($r) => $r['id'] === $sup));
check('opening: supplier difference reflects GL activity already posted', count($supRows) === 1 && abs($supRows[0]['diff'] - ($supRows[0]['legacy'] - $supRows[0]['gl'])) < 0.01);
$ejs = $ob->post('SUPPLIER', $sup, $branch, $userId);
check('opening: supplier aligned to legacy 900 after posting', $ejs > 0 && count(array_filter($ob->preview('SUPPLIER'), fn($r) => $r['id'] === $sup)) === 0);
check('opening: invalid kind refused', throwsMsg(fn() => $ob->post('NOPE', 1, $branch, $userId), 'Invalid'));

// 15) legacy returned order closed before the GL: backfill posts delivery, collection and return
$o40 = makeOrder($db, $store, $tel, 7, 30, 1, [[2, 400, 0, 350, 2]]);
$db->exec("UPDATE orders SET ORDER_SUCCESS=1, DELIVERY_ID=$userId WHERE ORDER_ID=$o40");
$db->exec("UPDATE order_product SET RETURNED_QTY=1 WHERE ORDER_ID=$o40");
$bf2 = $ev->backfillClosedCourierOrder($o40);
check('backfill returned: delivery 830 collected and return entry posted', $bf2['amount'] === 830.0 && $bf2['return_entry_id'] > 0, json_encode($bf2));
[$h, $l] = entry($db, $bf2['return_entry_id']);
check('backfill returned: return 400 / cost 350', lineSum($l, '4100', 'debit') === 400.0 && lineSum($l, '1300', 'debit') === 350.0);
check('backfill returned: customer net is -400 (credit) until refunded', (float)$db->query("SELECT ROUND(SUM(d.debit-d.credit),2) FROM journal_entry_details d JOIN accounts a ON a.id=d.account_id JOIN journal_entries e ON e.id=d.journal_entry_id WHERE a.account_code='1200' AND e.reference_id=$o40")->fetchColumn() === -400.0);

// 16) one-shot legacy migration: summary then runAll
require_once __DIR__ . '/../../inc/Classes/Accounting/LegacyMigration.php';
$lm = new LegacyMigration($db);
$o50 = makeOrder($db, $store, $tel, 3, 30, 1, [[1, 100, 0, 50, 1]]);
$db->exec("UPDATE orders SET ORDER_SUCCESS=1, DELIVERY_ID=$userId WHERE ORDER_ID=$o50");
$sm = $lm->summary();
check('legacy summary: counts the closed un-posted order', $sm['closed_orders'] >= 1 && isset($sm['opening_total']), json_encode($sm));
$rep = $lm->runAll($branch, $userId);
check('legacy runAll: order backfilled, no failures', $rep['orders']['done'] >= 1 && $rep['orders']['failed'] === 0, json_encode($rep));
check('legacy runAll: order now has a sale entry', (int)$db->query("SELECT COUNT(*) FROM orders WHERE ORDER_ID=$o50 AND SALE_ENTRY_ID IS NOT NULL")->fetchColumn() === 1);
$sm2 = $lm->summary();
check('legacy runAll: nothing left to settle afterwards', $sm2['closed_orders'] === 0 && $sm2['opening_total'] === 0, json_encode($sm2));

// 17) settings page: automatic database updater
require_once __DIR__ . '/../../inc/Classes/DatabaseUpdater.php';
$du = new DatabaseUpdater($db);
check('updater: file is pending before first run', $du->status()[0]['state'] === 'pending');
$ur = $du->run('ACCOUNTING_COMPLETE_MIGRATION.sql', $userId);
check('updater: executes and every readiness check passes', $ur['total'] >= 10 && count(array_filter($ur['checks'], fn($c) => !$c['ok'])) === 0, json_encode($ur));
check('updater: recorded as applied', $du->status()[0]['state'] === 'applied');
$ur2 = $du->run('ACCOUNTING_COMPLETE_MIGRATION.sql', $userId);
check('updater: second run is safe (idempotent)', $ur2['total'] === $ur['total']);
check('updater: unknown file refused', throwsMsg(fn() => $du->run('../../config.php', $userId), 'ملف غير معروف'));
check('updater: splitter ignores comments and quoted semicolons', DatabaseUpdater::split("-- c;\nSELECT 'a;b'; SELECT 2;") === ["SELECT 'a;b'", "SELECT 2"]);

// 18) the app passes a TrimmedPDO wrapper (not a raw PDO): every screen class must accept it
if (!class_exists('TrimmedPDO')) {
    class TrimmedPDO {
        private $pdo;
        public function __construct($pdo) { $this->pdo = $pdo; }
        public function rollBack() { return $this->pdo->inTransaction() ? $this->pdo->rollBack() : true; }
        public function commit() { return $this->pdo->inTransaction() ? $this->pdo->commit() : true; }
        public function beginTransaction() { return $this->pdo->inTransaction() ? true : $this->pdo->beginTransaction(); }
        public function prepare($sql, $options = []) { return $this->pdo->prepare(trim(preg_replace('/\s+/', ' ', $sql)), $options); }
        public function __call($m, $a) { return call_user_func_array([$this->pdo, $m], $a); }
    }
}
require_once __DIR__ . '/../../inc/Classes/CourierReopen.php';
$w = new TrimmedPDO($db);
$wrapOk = true; $wrapErr = '';
try {
    (new CourierReopen($w))->listReopenable();
    (new LegacyMigration($w))->summary();
    (new OpeningBalanceManager($w))->preview();
    (new DatabaseUpdater($w))->status();
    (new AccountingEvents($w));
} catch (Throwable $e) { $wrapOk = false; $wrapErr = $e->getMessage(); }
check('wrapper: screen classes work with TrimmedPDO', $wrapOk, $wrapErr);
$lmw = new LegacyMigration($w);
$detOk = true; $detErr = '';
try {
    $a1 = (new CourierReopen($w))->listReopenable(); $a2 = $lmw->closedOrdersDetailed(); $a3 = $lmw->unpostedReturnsDetailed();
    foreach ([$a1, $a2, $a3] as $set) { if ($set && !array_key_exists('CLIENT_NAME', $set[0])) { $detOk = false; $detErr = 'missing CLIENT_NAME'; } }
} catch (Throwable $e) { $detOk = false; $detErr = $e->getMessage(); }
check('order lists: detailed queries run and carry order-list fields', $detOk, $detErr);
check('order lists: backfill detail count equals summary count', count($lmw->closedOrdersDetailed()) === count($lmw->closedOrders()));

// 19) CourierReopen permission from the group permissions screen (Admin > CourierReopen)
$_SESSION = [];
check('perm: random group without permission cannot reopen/view', !CourierReopen::canReopen('موظف') && !CourierReopen::canView('موظف'));
$_SESSION['USER_SCRIPT'] = serialize(['Admin' => ['CourierReopen' => ['view' => 'true', 'reopen' => 'false']]]);
check('perm: view-only permission opens the screen but cannot reopen', CourierReopen::canView('موظف') && !CourierReopen::canReopen('موظف'));
$_SESSION['USER_SCRIPT'] = serialize(['Admin' => ['CourierReopen' => ['view' => 'false', 'reopen' => 'true']]]);
check('perm: reopen permission can view and reopen', CourierReopen::canView('موظف') && CourierReopen::canReopen('موظف'));
check('perm: approved groups keep access without permission', CourierReopen::canReopen('Administrator') && CourierReopen::canView('المدير المالي'));
$_SESSION = [];

// 20) ledger tools: reports, flags, period lock, cash opening, collection correction
require_once __DIR__ . '/../../inc/Classes/Accounting/Reports.php';
require_once __DIR__ . '/../../inc/Classes/Accounting/SystemFlags.php';
require_once __DIR__ . '/../../inc/Classes/Accounting/PeriodGuard.php';
require_once __DIR__ . '/../../inc/Classes/Accounting/CashOpening.php';
require_once __DIR__ . '/../../inc/Classes/Accounting/CollectionCorrection.php';
$gr = new GlReports($db);
$tbRows = $gr->trialBalance(null, null);
check('ledger: trial balance is balanced', count($tbRows) > 0 && abs(array_sum(array_column($tbRows, 'closing_debit')) - array_sum(array_column($tbRows, 'closing_credit'))) < 0.01);
$cashAcc = (int)$db->query("SELECT id FROM accounts WHERE account_code = '1110'")->fetchColumn();
$st1 = $gr->accountStatement($cashAcc, null, null);
check('ledger: account statement running balance ends at closing', !$st1['lines'] || abs(end($st1['lines'])['running'] - $st1['closing']) < 0.005);
$pRow = $db->query("SELECT d.partner_type, d.partner_id FROM journal_entry_details d WHERE d.partner_type = 'CLIENT' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($pRow) {
    $ps = $gr->partnerStatement('CLIENT', (int)$pRow['partner_id'], null, null);
    check('ledger: partner statement closing equals GL total and has legacy compare', abs($ps['closing'] - $ps['gl_total']) < 0.005 && array_key_exists('legacy_total', $ps));
    // رصيد قديم لأمر لم يُقفل بعد (ORDER_SUCCESS=0) لا يدخل في المقارنة، مثل تسوية الأرصدة
    $openOid = (int)$db->query("SELECT ORDER_ID FROM orders WHERE ORDER_SUCCESS = 0 LIMIT 1")->fetchColumn();
    if ($openOid) {
        $beforeLegacy = $gr->partnerStatement('CLIENT', (int)$pRow['partner_id'], null, null)['legacy_total'];
        $db->exec("INSERT INTO partners_balance (PARTNER_TYPE,PARTNER_ID,REFERENCE_ID,TRANSACTION_TYPE,BALANCE,NOTE) VALUES ('CLIENT'," . (int)$pRow['partner_id'] . ",$openOid,'CREDIT',777,'open order')");
        $afterLegacy = $gr->partnerStatement('CLIENT', (int)$pRow['partner_id'], null, null)['legacy_total'];
        check('ledger: legacy compare ignores balances of orders not yet closed', abs($afterLegacy - $beforeLegacy) < 0.005);
    }
}
check('ledger: journal book + entry lines', count($gr->journalBook(null, null)) > 0 && count($gr->entryLines((int)$gr->journalBook(null, null)[0]['id'])) >= 2);
$iv = $gr->inventoryValuation();
check('ledger: inventory valuation compare returns numbers', isset($iv['gl_inventory'], $iv['current_cost_value'], $iv['diff']));
check('ledger: partner search validates type', throwsMsg(fn() => $gr->searchPartners('X', 'a'), 'نوع الطرف'));

// flags + balance source
check('flag: default balance source is legacy', SystemFlags::get($db, 'BALANCE_SOURCE', 'legacy') === 'legacy');
check('flag: invalid value refused', throwsMsg(fn() => SystemFlags::set($db, 'BALANCE_SOURCE', 'zzz', $userId), 'إعداد غير مسموح'));
if ($pRow) {
    SystemFlags::set($db, 'BALANCE_SOURCE', 'gl', $userId);
    $glNet = PartnerBalanceSource::net($db, 'CLIENT', (int)$pRow['partner_id']);
    $expect = (float)$db->query("SELECT COALESCE(SUM(d.credit - d.debit),0) FROM journal_entry_details d JOIN journal_entries e ON e.id = d.journal_entry_id AND e.status='POSTED' WHERE d.partner_type='CLIENT' AND d.partner_id=" . (int)$pRow['partner_id'])->fetchColumn();
    check('flag: GL source returns ledger net (credit-debit)', abs($glNet - round($expect, 2)) < 0.005);
    // كشف الحساب (شاشة التقارير) من الأستاذ: نفس الصفوف والرصيد الحالي = صافي الأستاذ
    require_once __DIR__ . '/../../inc/controller/BackEnd/reports_BE.php';
    $_POST = ['draw' => 1, 'start' => 0, 'length' => -1, 'search' => ['value' => ''], 'partner_type' => 'CLIENT',
              'partner_id' => (int)$pRow['partner_id'], 'daterange' => '2000-01-01 - 2099-12-31'];
    $stm = (new reports_BE($db))->AccountStatement();
    $last = end($stm['data']);
    check('statement(gl): first row is opening, last row is current balance', $stm['data'][0]['note'] === 'رصيد سابق' && $last['note'] === 'رصيد حالي');
    check('statement(gl): current balance equals ledger net', abs((float)$last['running_balance'] - round($expect, 2)) < 0.005);
    SystemFlags::set($db, 'BALANCE_SOURCE', 'legacy', $userId);
    check('flag: back to legacy', SystemFlags::get($db, 'BALANCE_SOURCE') === 'legacy');
}

// period lock
$db->exec("INSERT INTO accounting_periods (date_from, date_to, note, is_closed, closed_by) VALUES ('2025-01-01', '2025-01-31', 'test', 1, $userId)");
$pid = (int)$db->lastInsertId();
check('period: closed period refuses entry date', throwsMsg(fn() => PeriodGuard::assertOpen($db, '2025-01-15 10:00:00'), 'مقفولة'));
check('period: date outside closed period is allowed', !throwsMsg(fn() => PeriodGuard::assertOpen($db, '2025-02-15'), 'مقفولة'));
$jm = new JournalManager($db);
$jm->create(['branch_id' => $branch, 'entry_date' => '2025-01-15 00:00:00', 'reference_type' => 'TEST_PERIOD', 'reference_id' => 1, 'description' => 'x', 'created_by' => $userId]);
$jm->addLine(['account_id' => $cashAcc, 'debit' => 1]);
$jm->addLine(['account_id' => (int)$db->query("SELECT id FROM accounts WHERE account_code = '4100'")->fetchColumn(), 'credit' => 1]);
check('period: JournalManager refuses posting in a closed period', throwsMsg(fn() => $jm->post(), 'مقفولة'));
$jm->reset();
$db->exec("UPDATE accounting_periods SET is_closed = 0 WHERE id = $pid");
check('period: reopened period accepts entries again', !throwsMsg(fn() => PeriodGuard::assertOpen($db, '2025-01-15'), 'مقفولة'));

// cash opening
$co = new CashOpening($db);
$accs = $co->listAccounts();
check('cash opening: lists cash boxes', count($accs) > 0);
$a0 = (int)$accs[0]['account_id'];
$e1 = $co->set($a0, 500, '2026-01-01', $branch, $userId);
check('cash opening: first set posts an entry', $e1 > 0);
check('cash opening: same amount posts nothing', $co->set($a0, 500, '2026-01-01', $branch, $userId) === 0);
$e3 = $co->set($a0, 700, '2026-01-01', $branch, $userId);
$op = array_values(array_filter($co->listAccounts(), fn($r) => (int)$r['account_id'] === $a0))[0]['opening'];
check('cash opening: raising amount posts the difference only', $e3 > 0 && abs($op - 700) < 0.005);
check('cash opening: negative refused', throwsMsg(fn() => $co->set($a0, -1, '2026-01-01', $branch, $userId), 'سالبًا'));

// collection correction
$cc = new CollectionCorrection($db);
$col = $db->query("SELECT e.id, e.reference_id FROM journal_entries e WHERE e.reference_type = 'COURIER_COLLECTION' AND e.status = 'POSTED' ORDER BY e.id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($col) {
    $found = $cc->find((string)$col['reference_id']);
    check('correction: finds the collection entry with lines', count($found) >= 1 && count($found[0]['lines']) >= 2 && (int)$found[0]['reversed'] === 0);
    check('correction: reason is mandatory', throwsMsg(fn() => $cc->reverse((int)$col['id'], 'x', $userId), 'سبب'));
    $before = (float)$db->query("SELECT COALESCE(SUM(debit - credit),0) FROM journal_entry_details d JOIN journal_entries e ON e.id=d.journal_entry_id AND e.status='POSTED' WHERE e.id IN (" . (int)$col['id'] . ")")->fetchColumn();
    $rid = $cc->reverse((int)$col['id'], 'تصحيح اختبار', $userId);
    $net = (float)$db->query("SELECT COALESCE(SUM(d.debit - d.credit),0) FROM journal_entry_details d WHERE d.journal_entry_id IN (" . (int)$col['id'] . ", $rid)")->fetchColumn();
    check('correction: reversal nets the collection entry to zero', $rid > 0 && abs($net) < 0.005);
    check('correction: cannot reverse twice', throwsMsg(fn() => $cc->reverse((int)$col['id'], 'تصحيح ثاني', $userId), 'معكوس'));
    check('correction: non-collection entry refused', throwsMsg(fn() => $cc->reverse(1, 'سبب كافي هنا', $userId), 'ليس قيد تحصيل'));
}

// open cash (صرف/استلام مفتوح)
if (!class_exists('OpenCash')) { include_once(__DIR__ . '/../../inc/Classes/Accounting/OpenCash.php'); }
$oc = new OpenCash($db);
$newExp = $oc->addAccount('PAYOUT', 'بند اختبار ' . uniqid());
check('open cash: new expense account gets a code in the 59xx range', (int)$newExp['account_code'] >= 5901 && (int)$newExp['account_code'] <= 5999);
check('open cash: duplicate name refused', throwsMsg(fn() => $oc->addAccount('PAYOUT', $newExp['account_name']), 'بهذا الاسم'));
check('open cash: new item is listed as a reason', in_array((string)$newExp['id'], array_map('strval', array_column($oc->listAccounts('PAYOUT'), 'id')), true));
check('open cash: COGS is not offered as a reason', !in_array('5100', array_column($oc->listAccounts('PAYOUT'), 'account_code'), true));
$cashBefore = (float)$db->query("SELECT COALESCE(SUM(d.debit - d.credit),0) FROM journal_entry_details d JOIN journal_entries e ON e.id=d.journal_entry_id AND e.status='POSTED' WHERE d.account_id = $cashAcc")->fetchColumn();
$pid1 = $oc->post('PAYOUT', (int)$newExp['id'], 25.5, 'اختبار', $branch, $userId);
$cashAfter = (float)$db->query("SELECT COALESCE(SUM(d.debit - d.credit),0) FROM journal_entry_details d JOIN journal_entries e ON e.id=d.journal_entry_id AND e.status='POSTED' WHERE d.account_id = $cashAcc")->fetchColumn();
check('open cash: payout posts and reduces cash', $pid1 > 0 && abs(($cashBefore - $cashAfter) - 25.5) < 0.005);
check('open cash: zero amount refused', throwsMsg(fn() => $oc->post('PAYOUT', (int)$newExp['id'], 0, '', $branch, $userId), 'مبلغ'));
check('open cash: non-reason account refused', throwsMsg(fn() => $oc->post('PAYOUT', $cashAcc, 5, '', $branch, $userId), 'سببًا'));
$newRev = $oc->addAccount('RECEIPT', 'إيراد اختبار ' . uniqid());
$rid1 = $oc->post('RECEIPT', (int)$newRev['id'], 10, '', $branch, $userId);
check('open cash: receipt posts', $rid1 > 0 && (int)$newRev['account_code'] >= 4301);

// ---- commission method: marketer (per piece) vs broker (price difference over minimum) ----
$colDefault = $db->query("SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users_affiliate' AND COLUMN_NAME='COMMISSION_TYPE'")->fetchColumn();
check('commission type: column exists, default PER_PIECE', $colDefault !== false && trim((string)$colDefault, "'") === 'PER_PIECE');
$db->exec("INSERT INTO users_affiliate (USER_ID,COMMISSION_SALES,TEAM_COMMISSION,COMMISSION_TYPE) VALUES ($userId,3,0,'PRICE_DIFF')");
$db->exec("INSERT INTO product_codes (PRODUCT_ID,PRO_CODE,CODE_PRICE,CODE_COST_PRICE,LOWEST_PRICE) VALUES (1,'BRK1',150,50,100),(1,'BRK2',90,50,100)");
$expr = "CASE WHEN UA.COMMISSION_TYPE = 'PRICE_DIFF'
    THEN Round(SUM(CP.DELIVERED_QTY * GREATEST(0, (CP.PRICE - CP.DISCOUNT) - IFNULL(PCM.LOWEST_PRICE, CP.PRICE))),2)
    ELSE Round(SUM(CP.DELIVERED_QTY)*UA.COMMISSION_SALES,2) END";
$db->exec("INSERT INTO custody_product (ORDER_ID,USER_ID,PRO_CODE,QTY,PRICE,DISCOUNT,DELIVERED_QTY) VALUES (990001,$userId,'BRK1',3,130,10,2),(990001,$userId,'BRK2',1,95,0,1)");
$q = $db->prepare("SELECT $expr FROM custody_product CP LEFT JOIN product_codes PCM ON PCM.PRO_CODE=CP.PRO_CODE LEFT JOIN users_affiliate UA ON UA.USER_ID=CP.USER_ID WHERE CP.ORDER_ID=990001 GROUP BY UA.USER_ID");
$q->execute();
// BRK1: (130-10-100)=20 x 2 = 40 ; BRK2: price below minimum -> 0
check('broker commission = (price - discount - minimum) x received qty, never negative', abs((float)$q->fetchColumn() - 40.0) < 0.005);
$db->exec("UPDATE users_affiliate SET COMMISSION_TYPE='PER_PIECE' WHERE USER_ID=$userId");
$q->execute();
check('marketer commission unchanged: per piece x received qty', abs((float)$q->fetchColumn() - 9.0) < 0.005);

// ---- shipping companies (master data; the company is backed by an internal suspended courier user) ----
require_once __DIR__ . '/../../inc/Classes/ShippingCompanies.php';
$scBranch = (int)$db->query("SELECT BR_ID FROM branches ORDER BY BR_ID LIMIT 1")->fetchColumn();
$scs = new ShippingCompanies($db);
$scId = $scs->save(['name' => 'شركة اختبار', 'receiving_policy' => 'PARTIAL', 'settlement_method' => 'GROSS_THEN_FEE', 'fee_mode' => 'ZONE_RATE'], $userId, $scBranch);
$scRow = $scs->get($scId);
check('shipping company: saved with policy/method/fee mode', $scRow['receiving_policy'] === 'PARTIAL' && $scRow['settlement_method'] === 'GROSS_THEN_FEE' && $scRow['fee_mode'] === 'ZONE_RATE');
$scu = $db->query("SELECT SUSPENDED, DELIVERY_TYPE, LENGTH(USER_PASSWORD) AS pl FROM users WHERE USER_ID=" . (int)$scRow['user_id'])->fetch(PDO::FETCH_ASSOC);
check('shipping company: active courier user so it appears in courier pickers', $scu && (int)$scu['SUSPENDED'] === 1 && (int)$scu['DELIVERY_TYPE'] === 1);
check('shipping company: stored password can never equal an md5 login (no login)', $scu && (int)$scu['pl'] === 64);
check('shipping company: users_delivery row exists', (int)$db->query("SELECT COUNT(*) FROM users_delivery WHERE USER_ID=" . (int)$scRow['user_id'])->fetchColumn() === 1);
check('shipping company: duplicate name refused', throwsMsg(fn() => $scs->save(['name' => 'شركة اختبار'], $userId, $scBranch), 'مستخدم'));
check('shipping company: FIXED needs a fee', throwsMsg(fn() => $scs->save(['name' => 'ثابتة', 'fee_mode' => 'FIXED', 'fixed_fee' => 0], $userId, $scBranch), 'الرسم الثابت'));
$scs->save(['id' => $scId, 'name' => 'شركة اختبار 2', 'receiving_policy' => 'SINGLE', 'settlement_method' => 'NET_OF_FEE', 'fee_mode' => 'FIXED', 'fixed_fee' => 25], $userId, $scBranch);
$scRow2 = $scs->get($scId);
check('shipping company: edit keeps the same internal user and renames it', (int)$scRow2['user_id'] === (int)$scRow['user_id']
    && $db->query("SELECT FORENAME FROM users WHERE USER_ID=" . (int)$scRow['user_id'])->fetchColumn() === 'شركة اختبار 2');
$zoneId = (int)$db->query("SELECT ZONE_ID FROM zones ORDER BY ZONE_ID LIMIT 1")->fetchColumn();
if ($zoneId > 0) {
    $scs->saveRates($scId, [$zoneId => '40']);
    $zr = $scs->zonesWithRates($scId);
    $hit = array_values(array_filter($zr, fn($r) => (int)$r['zone_id'] === $zoneId));
    check('shipping company: zone rate saved', $hit && abs((float)$hit[0]['fee'] - 40.0) < 0.005);
    $scs->saveRates($scId, [$zoneId => '']);
    check('shipping company: empty rate removes the zone price', (int)$db->query("SELECT COUNT(*) FROM shipping_company_rates WHERE company_id=$scId")->fetchColumn() === 0);
    check('shipping company: negative rate refused', throwsMsg(fn() => $scs->saveRates($scId, [$zoneId => '-5']), 'رسم غير صحيح'));
}
$scs->setActive($scId, false);
check('shipping company: deactivate (never deleted)', (int)$scs->get($scId)['is_active'] === 0);
check('shipping company: deactivating also hides the courier user from pickers', (int)$db->query("SELECT SUSPENDED FROM users WHERE USER_ID=" . (int)$scRow['user_id'])->fetchColumn() === 0);
$scs->setActive($scId, true);
check('shipping company: reactivating restores the courier user', (int)$db->query("SELECT SUSPENDED FROM users WHERE USER_ID=" . (int)$scRow['user_id'])->fetchColumn() === 1);
// 7b) link a company to an EXISTING courier
$db->exec("INSERT INTO users (BRANCH_ID,FORENAME,LASTNAME,GENDER,SUSPENDED,USER_LOGIN,USER_PASSWORD,DELIVERY_TYPE,BIRTHDAY) VALUES ($branch,'مندوب','قائم','male',1,'real.courier@example.com','x',1,'1990-01-01')");
$realCourier = (int)$db->lastInsertId();
$db->exec("INSERT INTO users_delivery (USER_ID,ZONE_ID,PERCENT) VALUES ($realCourier,NULL,5)");
check('existing courier listed as available', in_array($realCourier, array_map('intval', array_column($scs->availableCouriers(), 'id')), true));
$linkId = $scs->save(['name' => 'شركة مرتبطة', 'courier_mode' => 'EXISTING', 'courier_user_id' => $realCourier, 'fee_mode' => 'ORDER_SHIPPING'], $userId, $branch);
check('company linked to existing courier keeps that user', (int)$scs->get($linkId)['user_id'] === $realCourier && (int)$scs->get($linkId)['courier_internal'] === 0);
check('linked courier no longer available', !in_array($realCourier, array_map('intval', array_column($scs->availableCouriers(), 'id')), true));
check('same courier cannot be linked twice', throwsMsg(fn() => $scs->save(['name' => 'شركة ثانية', 'courier_mode' => 'EXISTING', 'courier_user_id' => $realCourier, 'fee_mode' => 'ORDER_SHIPPING'], $userId, $branch), 'مرتبط بشركة'));
check('non-courier user cannot be linked', throwsMsg(fn() => $scs->save(['name' => 'شركة ثالثة', 'courier_mode' => 'EXISTING', 'courier_user_id' => $userId, 'fee_mode' => 'ORDER_SHIPPING'], $userId, $branch), 'غير موجود'));
$scs->save(['id' => $linkId, 'name' => 'شركة مرتبطة معدلة', 'fee_mode' => 'ORDER_SHIPPING'], $userId, $branch);
check('renaming the company does not rename a real courier', $db->query("SELECT FORENAME FROM users WHERE USER_ID=$realCourier")->fetchColumn() === 'مندوب');
$scs->setActive($linkId, false);
check('deactivating the company never suspends a real courier', (int)$db->query("SELECT SUSPENDED FROM users WHERE USER_ID=$realCourier")->fetchColumn() === 1);
$otherCourier = (function () use ($db, $branch) {
    $db->exec("INSERT INTO users (BRANCH_ID,FORENAME,LASTNAME,GENDER,SUSPENDED,USER_LOGIN,USER_PASSWORD,DELIVERY_TYPE,BIRTHDAY) VALUES ($branch,'مندوب','آخر','male',1,'other.courier@example.com','x',1,'1990-01-01')");
    $id = (int)$db->lastInsertId(); $db->exec("INSERT INTO users_delivery (USER_ID,ZONE_ID,PERCENT) VALUES ($id,NULL,0)"); return $id;
})();
$scs->save(['id' => $linkId, 'name' => 'شركة مرتبطة معدلة', 'fee_mode' => 'ORDER_SHIPPING', 'courier_mode' => 'EXISTING', 'courier_user_id' => $otherCourier], $userId, $branch);
check('courier link can be changed while the current one has no activity', (int)$scs->get($linkId)['user_id'] === $otherCourier);
$o777 = makeOrder($db, $store, $tel, 2, 0, 1, [[1, 10, 0, 5, 0]]);
$db->exec("INSERT INTO custody_orders (ORDER_ID,USER_ID,SHIPPING_ADDRESS,RETURNS,CUST_STAT,ORDER_STATUSE) VALUES ($o777,$otherCourier,'a',1,'WAITING',2)");
check('courier link change refused once custody exists', throwsMsg(fn() => $scs->save(['id' => $linkId, 'name' => 'شركة مرتبطة معدلة', 'fee_mode' => 'ORDER_SHIPPING', 'courier_mode' => 'NEW'], $userId, $branch), 'عهدة'));
$db->exec("DELETE FROM custody_orders WHERE ORDER_ID=$o777");

// 8) shipping company Excel preview (read-only classification)
require_once __DIR__ . '/../../inc/Classes/ShippingCompanyImport.php';
$imp = new ShippingCompanyImport($db);
$scCourier = (int)$scRow['user_id'];
function scCustody(PDO $db, int $oid, int $courier, string $stat = 'WAITING', string $line = 'waiting'): void {
    $db->exec("UPDATE orders SET EXISTS_ID=10, DELIVERY_ID=$courier, ADDRESS='addr' WHERE ORDER_ID=$oid");
    $db->exec("INSERT INTO custody_orders (ORDER_ID,USER_ID,SHIPPING_ADDRESS,RETURNS,CUST_STAT,ORDER_STATUSE) VALUES ($oid,$courier,'addr',1,'$stat',2)");
    $db->exec("INSERT INTO custody_product (ORDER_ID,USER_ID,PRO_CODE,QTY,PRICE,DISCOUNT,DELIVERED_QTY,RETURNED_QTY,PRO_STATUSE) SELECT ORDER_ID,$courier,PRO_CODE,QTY,PRICE,DISCOUNT,0,0,'$line' FROM order_product WHERE ORDER_ID=$oid");
}
$q1 = makeOrder($db, $store, $tel, 2, 30, 1, [[2, 100, 10, 60, 0]]);   // value 180, shipping 30
$q2 = makeOrder($db, $store, $tel, 2, 30, 1, [[2, 100, 10, 60, 0], [1, 50, 0, 30, 0]]); // 180 + 50
$q3 = makeOrder($db, $store, $tel, 2, 30, 1, [[1, 100, 0, 60, 0]]);
$q4 = makeOrder($db, $store, $tel, 2, 30, 1, [[1, 100, 0, 60, 0]]);
foreach ([$q1, $q2, $q3] as $qq) { scCustody($db, $qq, $scCourier); }
scCustody($db, $q4, $scCourier, 'DELIVERED', 'complete');
$sub = fn($id) => $db->query("SELECT SUB_ORDER_ID FROM orders WHERE ORDER_ID=$id")->fetchColumn();
$res = $imp->preview($scId, [
    ['row' => 2, 'order' => $sub($q1), 'amount' => '180', 'status' => 'استلم'],
    ['row' => 3, 'order' => $sub($q2), 'amount' => '230', 'status' => ''],
    ['row' => 4, 'order' => $sub($q3), 'amount' => '30', 'status' => 'مرتجع ودفع الشحن'],
    ['row' => 5, 'order' => $sub($q4), 'amount' => '100', 'status' => ''],
    ['row' => 6, 'order' => 'NOPE-1', 'amount' => '10', 'status' => ''],
    ['row' => 7, 'order' => $sub($q1), 'amount' => '180', 'status' => ''],
], false, [], 1.0);
$byRow = []; foreach ($res['rows'] as $r) { $byRow[$r['row']] = $r; }
check('import: duplicate order in file flagged', $byRow[2]['flag'] === 'ERROR' && $byRow[7]['flag'] === 'ERROR');
check('import: amount below value matches a partial distribution (230 = 180 + 50)', $byRow[3]['derived'] === 'FULL' || $byRow[3]['derived'] === 'PARTIAL');
check('import: amount equal to shipping on a return = RETURN_SHIPPING_PAID', $byRow[4]['derived'] === 'RETURN_SHIPPING_PAID' && $byRow[4]['proposed'] === 'RETURN_SHIPPING_PAID' && $byRow[4]['flag'] === 'OK');
check('import: already processed order is an error', $byRow[5]['flag'] === 'ERROR');
check('import: unknown order is an error', $byRow[6]['flag'] === 'ERROR');
$res2 = $imp->preview($scId, [['row' => 2, 'order' => $sub($q1), 'amount' => '210', 'status' => 'استلم']], true, [], 1.0);
check('import: includes-shipping flag makes 210 a full delivery', $res2['rows'][0]['derived'] === 'FULL' && $res2['rows'][0]['flag'] === 'OK');
$res3 = $imp->preview($scId, [['row' => 2, 'order' => $sub($q2), 'amount' => '50', 'status' => '']], false, [], 1.0);
check('import: partial amount suggests the matching quantity split', $res3['rows'][0]['derived'] === 'PARTIAL' && count($res3['rows'][0]['candidates']) === 1);
$res4 = $imp->preview($scId, [['row' => 2, 'order' => $sub($q1), 'amount' => '0', 'status' => 'اجل']], false, [], 1.0);
check('import: postponed with no collection means no change', $res4['rows'][0]['proposed'] === 'IGNORE');
$scs->saveImportSettings($scId, ['sheet' => 'حساب', 'header_row' => 1, 'order_col' => ['name' => 'الكود', 'index' => 0], 'amount_col' => ['name' => 'المدفوع', 'index' => 5], 'includes_shipping' => 1, 'tolerance' => 1, 'status_map' => ['تم التسليم' => 'FULL', 'x' => 'BAD']]);
$is = json_decode($scs->get($scId)['import_settings'], true);
check('import settings: saved per company by header names', $is['order_col']['name'] === 'الكود' && $is['includes_shipping'] === 1 && $is['sheet'] === 'حساب');
check('import settings: invalid status action dropped', isset($is['status_map']['تم التسليم']) && !isset($is['status_map']['x']));
$scs->saveImportSettings($scId, ['sheet'=>'حساب','order_col'=>['name'=>'a','index'=>0],'amount_col'=>['name'=>'b','index'=>1]]);
check('import settings: amount column required', throwsMsg(fn() => $scs->saveImportSettings($scId, ['order_col'=>['name'=>'a','index'=>0]]), 'المبلغ المحصل'));
check('import settings: order column required', throwsMsg(fn() => $scs->saveImportSettings($scId, ['sheet' => 'a', 'amount_col' => ['name'=>'b','index'=>1]]), 'عمود رقم الأمر'));
$res5 = $imp->preview($scId, [['row' => 2, 'order' => $sub($q3), 'amount' => '25', 'status' => 'مرتجع ودفع الشحن']], false, [], 1.0);
check('import: return with collected below the shipping = shipping paid with a shipping-difference note', $res5['rows'][0]['derived'] === 'RETURN_SHIPPING_PAID' && $res5['rows'][0]['proposed'] === 'RETURN_SHIPPING_PAID' && count($res5['rows'][0]['notes']) >= 1);
$res6 = $imp->preview($scId, [['row' => 2, 'order' => $sub($q1), 'amount' => '220', 'status' => 'تم التسليم', 'fee' => '40']], true, [], 1.0);
check('import: company shipping differing from system shipping is accepted and reported', $res6['rows'][0]['derived'] === 'FULL' && $res6['rows'][0]['flag'] === 'OK' && abs($res6['rows'][0]['shipping_diff'] - 10.0) < 0.005);
check('import: preview never writes (custody still waiting)', (int)$db->query("SELECT COUNT(*) FROM custody_orders WHERE USER_ID=$scCourier AND CUST_STAT='WAITING'")->fetchColumn() === 3);
check('import: order numbers from Excel E-notation normalized', ShippingCompanyImport::normalizeOrder('6.9261113125989E+13') === '69261113125989');
check('import: status text defaults', ShippingCompanyImport::defaultAction('فشل التسليم') === 'RETURN' && ShippingCompanyImport::defaultAction('تم التسليم') === 'FULL');

echo $fail ? "\n$fail FAILED\n" : "\nALL PASSED\n";
exit($fail ? 1 : 0);
