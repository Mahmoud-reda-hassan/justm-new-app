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

// 7) DELIVERY partner lines (was broken: wrong partner column names)
$s = new AccountSettings($db);
$jm = new JournalManager($db);
$jm->reset()->create(['branch_id' => $branch, 'entry_date' => date('Y-m-d H:i:s'), 'reference_type' => 'TEST', 'reference_id' => 1, 'description' => 'partner test', 'created_by' => $userId]);
$jm->addLine(['account_id' => $s->deliveryCommission(), 'debit' => 10]);
$jm->addLine(['account_id' => $s->deliveryCommissionPayable(), 'partner_type' => 'DELIVERY', 'partner_id' => $userId, 'credit' => 10]);
check('journal accepts a DELIVERY partner line', $jm->post() > 0);

echo $fail ? "\n$fail FAILED\n" : "\nALL PASSED\n";
exit($fail ? 1 : 0);
