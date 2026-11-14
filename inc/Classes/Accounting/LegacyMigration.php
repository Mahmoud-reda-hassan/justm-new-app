<?php
include_once(__dir__.'/AccountingEvents.php');
include_once(__dir__.'/OpeningBalanceManager.php');

/**
 * ترحيل النظام القديم إلى الأستاذ دفعة واحدة، بالترتيب:
 *  1) أوامر المندوب المُقفلة قبل الأستاذ (تسليم + تحصيل + عمولات + مرتجع إن وُجد)
 *  2) مرتجعات أوامر مُرحَّلة ولم يُرحَّل مرتجعها
 *  3) تسويات الأرصدة (عملاء، مندوبون، مسوقون، موردون، مخزون) مقابل حساب الأرصدة الافتتاحية
 * كل عنصر في معاملة مستقلة؛ فشل عنصر لا يوقف الباقي ويُسجَّل في التقرير. لا تعديل لأي بيان قديم.
 */
class LegacyMigration {
    protected $db;

    public function __construct($db) { $this->db = $db; }

    public function closedOrders(): array {
        return $this->db->query("SELECT o.ORDER_ID, o.SUB_ORDER_ID, o.ORDER_STATUS, o.SHIPPING_VAL, o.LAST_UPDATE,
                CONCAT(u.FORENAME, ' ', u.LASTNAME) AS DELIVERY_NAME
            FROM orders o LEFT JOIN users u ON u.USER_ID = o.DELIVERY_ID
            WHERE o.ORDER_SUCCESS = 1 AND o.SALE_ENTRY_ID IS NULL AND o.ORDER_STATUS IN (3,4,5,6,7)
              AND o.DELIVERY_ID > 0 AND o.ORDER_TYPE_ID <> 5
              AND NOT EXISTS (SELECT 1 FROM journal_entries e WHERE e.reference_type = 'COURIER_COLLECTION' AND e.reference_id = o.ORDER_ID AND e.status = 'POSTED')
            ORDER BY o.LAST_UPDATE DESC LIMIT 5000")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function unpostedReturns(): array {
        return $this->db->query("SELECT o.ORDER_ID, o.SUB_ORDER_ID, o.ORDER_STATUS,
                ROUND(SUM(op.RETURNED_QTY * (op.PRICE - op.DISCOUNT)),2) AS RETURN_VALUE,
                COALESCE((SELECT SUM(d.debit) FROM journal_entries e JOIN journal_entry_details d ON d.journal_entry_id = e.id
                    WHERE e.reference_type = 'COURIER_SALES_RETURN' AND e.reference_id = o.ORDER_ID AND e.status = 'POSTED'
                      AND d.account_id = (SELECT account_id FROM account_settings WHERE setting_key = 'SALES_RETURNS' LIMIT 1)),0) AS POSTED_VALUE
            FROM orders o JOIN order_product op ON op.ORDER_ID = o.ORDER_ID
            WHERE o.ORDER_STATUS IN (6,7) AND o.SALE_ENTRY_ID IS NOT NULL
            GROUP BY o.ORDER_ID, o.SUB_ORDER_ID, o.ORDER_STATUS
            HAVING RETURN_VALUE > POSTED_VALUE + 0.005
            ORDER BY o.ORDER_ID DESC LIMIT 5000")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** ملخص ما لم يُرحَّل: أعداد وإجماليات الفروق. */
    public function summary(): array {
        $orders  = $this->closedOrders();
        $returns = $this->unpostedReturns();
        $opening = (new OpeningBalanceManager($this->db))->preview();
        $byKind = [];
        foreach ($opening as $r) {
            if (!isset($byKind[$r['kind']])) { $byKind[$r['kind']] = ['count' => 0, 'diff' => 0.0, 'abs' => 0.0]; }
            $byKind[$r['kind']]['count']++;
            $byKind[$r['kind']]['diff'] = round($byKind[$r['kind']]['diff'] + $r['diff'], 2);
            $byKind[$r['kind']]['abs']  = round($byKind[$r['kind']]['abs'] + abs($r['diff']), 2);
        }
        return [
            'closed_orders' => count($orders),
            'returns'       => count($returns),
            'returns_value' => round(array_sum(array_map(fn($r) => $r['RETURN_VALUE'] - $r['POSTED_VALUE'], $returns)), 2),
            'opening_total' => count($opening),
            'opening'       => $byKind,
        ];
    }

    /** ينفّذ الترحيل كله. @return array تقرير بالأعداد والإخفاقات */
    public function runAll(int $branchId, int $userId, bool $blockOnMissingCost = true): array {
        @set_time_limit(0);
        $report = ['orders' => ['done' => 0, 'failed' => 0], 'returns' => ['done' => 0, 'failed' => 0],
                   'opening' => ['done' => 0, 'failed' => 0], 'errors' => []];
        $addError = function (string $what, string $msg) use (&$report) {
            if (count($report['errors']) < 30) { $report['errors'][] = $what.': '.$msg; }
        };

        foreach ($this->closedOrders() as $o) {
            try {
                $this->db->beginTransaction();
                (new AccountingEvents($this->db))->backfillClosedCourierOrder((int)$o['ORDER_ID'], $blockOnMissingCost);
                $this->db->commit(); $report['orders']['done']++;
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) { $this->db->rollBack(); }
                $report['orders']['failed']++; $addError('أمر '.($o['SUB_ORDER_ID'] ?: $o['ORDER_ID']), $e->getMessage());
            }
        }
        foreach ($this->unpostedReturns() as $r) {
            try {
                $this->db->beginTransaction();
                (new AccountingEvents($this->db))->courierSalesReturn((int)$r['ORDER_ID']);
                $this->db->commit(); $report['returns']['done']++;
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) { $this->db->rollBack(); }
                $report['returns']['failed']++; $addError('مرتجع '.($r['SUB_ORDER_ID'] ?: $r['ORDER_ID']), $e->getMessage());
            }
        }
        // الفروق تُحسب بعد ترحيل الأوامر والمرتجعات
        foreach ((new OpeningBalanceManager($this->db))->preview() as $row) {
            try {
                $this->db->beginTransaction();
                (new OpeningBalanceManager($this->db))->post($row['kind'], (int)$row['id'], $branchId, $userId);
                $this->db->commit(); $report['opening']['done']++;
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) { $this->db->rollBack(); }
                $report['opening']['failed']++; $addError($row['kind'].' '.$row['name'], $e->getMessage());
            }
        }
        return $report;
    }
}
