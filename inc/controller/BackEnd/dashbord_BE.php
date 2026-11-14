<?php
class dashbord_BE {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    private function isEnabled($value) {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    private function scalar($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? 0 : (0 + $value);
    }

    private function rows($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function orderWhere($alias, $branchId, $canViewAllBranches, $dateFilter = true) {
        $where = $dateFilter
            ? "$alias.DATE_CREATED >= :date_from AND $alias.DATE_CREATED < DATE_ADD(:date_to, INTERVAL 1 DAY)"
            : '1=1';
        $params = [];
        if ($dateFilter) {
            $params[':date_from'] = $this->dateFrom;
            $params[':date_to'] = $this->dateTo;
        }
        if (!$canViewAllBranches) {
            if ($branchId > 0) {
                $where .= " AND EXISTS (SELECT 1 FROM stores dashboard_scope_store WHERE dashboard_scope_store.STO_ID = $alias.STO_ID AND dashboard_scope_store.BRANCH_ID = :dashboard_branch_id)";
                $params[':dashboard_branch_id'] = $branchId;
            } else {
                $where .= ' AND 1=0';
            }
        }
        return [$where, $params];
    }

    private $dateFrom;
    private $dateTo;

    public function getDashboardData($roles, $adminPermissions, $userId, $branchId, $dateFrom, $dateTo) {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $metrics = [];
        $canViewAllBranches = $this->isEnabled($adminPermissions['UserSpecificPermissions']['viewAllBranches'] ?? false)
            || $this->isEnabled($adminPermissions['StockAudit']['viewAllBranches'] ?? false)
            || $this->isEnabled($adminPermissions['StockReceipt']['viewAllBranches'] ?? false);
        $hasOrdersDashboard = in_array('Admin', $roles, true)
            || in_array('CustomerService1', $roles, true)
            || in_array('CustomerService2', $roles, true)
            || in_array('Delivery', $roles, true);

        if ($hasOrdersDashboard) {
            [$where, $params] = $this->orderWhere('o', $branchId, $canViewAllBranches);
            [$unpaidWhere, $unpaidParams] = $this->orderWhere('o', $branchId, $canViewAllBranches, false);
            $metrics['unpaid_orders'] = $this->scalar("SELECT COUNT(*) FROM orders o WHERE $unpaidWhere AND o.ORDER_SUCCESS = 0", $unpaidParams);

            $trend = $this->rows(
                "SELECT DATE(o.DATE_CREATED) AS order_day, COUNT(*) AS total
                 FROM orders o WHERE $where
                 GROUP BY DATE(o.DATE_CREATED) ORDER BY order_day",
                $params
            );
            $trendByDay = [];
            foreach ($trend as $row) {
                $trendByDay[$row['order_day']] = (int)$row['total'];
            }
            $ordersTrend = [];
            $periodStart = new DateTimeImmutable($dateFrom);
            $periodEnd = new DateTimeImmutable($dateTo);
            $days = (int)$periodStart->diff($periodEnd)->format('%a');
            if ($days > 30) {
                $periodStart = $periodEnd->modify('-30 days');
                $days = 30;
            }
            for ($offset = $days; $offset >= 0; $offset--) {
                $day = $periodEnd->modify('-' . $offset . ' days')->format('Y-m-d');
                $ordersTrend[] = [
                    'label' => $periodEnd->modify('-' . $offset . ' days')->format('d/m'),
                    'total' => $trendByDay[$day] ?? 0
                ];
            }

            $statusRows = $this->rows(
                "SELECT os.STA_NAME AS label, COUNT(*) AS total
                 FROM orders o
                 LEFT JOIN order_statuse os ON os.STA_ID = o.ORDER_STATUS
                 WHERE $where GROUP BY o.ORDER_STATUS, os.STA_NAME ORDER BY o.ORDER_STATUS",
                $params
            );

            $latestOrders = $this->rows(
                "SELECT o.SUB_ORDER_ID, DATE_FORMAT(o.DATE_CREATED, '%Y-%m-%d %H:%i') AS created_at,
                        COALESCE(oe.EXISTS_NAME, '-') AS exists_name,
                        COALESCE(os.STA_NAME, '-') AS status_name,
                        CASE WHEN o.ORDER_SUCCESS = 1 THEN 'مسدد' WHEN o.ORDER_SUCCESS = 0 THEN 'غير مسدد' ELSE 'قيد المعالجة' END AS payment_status,
                        COALESCE(CONCAT(u.FORENAME, ' ', u.LASTNAME), '-') AS delivery_name
                 FROM orders o
                 LEFT JOIN order_exists oe ON oe.EXISTS_ID = o.EXISTS_ID
                 LEFT JOIN order_statuse os ON os.STA_ID = o.ORDER_STATUS
                 LEFT JOIN users u ON u.USER_ID = o.DELIVERY_ID
                 WHERE $where
                 ORDER BY o.DATE_CREATED DESC LIMIT 30",
                $params
            );
        } else {
            $ordersTrend = [];
            $statusRows = [];
            $latestOrders = [];
        }

        $existsCounts = [];
        if (in_array('Admin', $roles, true)) {
            [$allWhere, $allParams] = $this->orderWhere('o', $branchId, $canViewAllBranches, false);
            [$periodWhere, $periodParams] = $this->orderWhere('o', $branchId, $canViewAllBranches, true);
            $metrics['orders_month'] = $this->scalar(
                "SELECT COUNT(*) FROM orders o WHERE $periodWhere",
                $periodParams
            );
            $metrics['orders_system_total'] = $this->scalar(
                "SELECT COUNT(*) FROM orders o WHERE $allWhere",
                $allParams
            );
            $existsJoinScope = '';
            if (!$canViewAllBranches) {
                $existsJoinScope = $branchId > 0
                    ? ' AND EXISTS (SELECT 1 FROM stores dashboard_scope_store WHERE dashboard_scope_store.STO_ID = o.STO_ID AND dashboard_scope_store.BRANCH_ID = ' . (int)$branchId . ')'
                    : ' AND 1=0';
            }
            $existsCounts = $this->rows(
                "SELECT oe.EXISTS_ID AS exists_id, oe.EXISTS_NAME AS exists_name, COUNT(o.ORDER_ID) AS total
                 FROM order_exists oe
                 LEFT JOIN orders o
                   ON o.EXISTS_ID = oe.EXISTS_ID
                  AND o.DATE_CREATED >= :date_from
                  AND o.DATE_CREATED < DATE_ADD(:date_to, INTERVAL 1 DAY)
                  $existsJoinScope
                 WHERE oe.EXISTS_ID <> 10
                 GROUP BY oe.EXISTS_ID, oe.EXISTS_NAME
                 ORDER BY CASE oe.EXISTS_ID
                    WHEN 1 THEN 1
                    WHEN 2 THEN 2
                    WHEN 3 THEN 3
                    WHEN 4 THEN 4
                    WHEN 13 THEN 5
                    WHEN 5 THEN 6
                    ELSE 100 + oe.EXISTS_ID
                 END",
                $allParams + [':date_from' => $dateFrom, ':date_to' => $dateTo]
            );

            // قيد الشحن فقط يظل مرتبطًا بعهدة المندوب، أما نتائج التسليم والرفض والمرتجعات
            // فتُحسب من حالة الأمر حتى بعد إجراء التسوية المخزنية أو المالية.
            $deliveryCustodyStatuses = [
                ['delivery_custody_shipping', 'قيد الشحن', 'o.EXISTS_ID = 10 AND o.ORDER_STATUS <= 2 AND o.DELIVERY_ID IS NOT NULL'],
                ['delivery_rejected', 'مرفوض', 'o.ORDER_STATUS = 5 AND o.DELIVERY_ID IS NOT NULL'],
                ['delivery_partial', 'تسليم جزئي', 'o.ORDER_STATUS = 4 AND o.DELIVERY_ID IS NOT NULL'],
                ['delivery_delivered', 'تسليم كلي', 'o.ORDER_STATUS = 3 AND o.DELIVERY_ID IS NOT NULL'],
                ['delivery_partial_return', 'مرتجع جزئي', 'o.ORDER_STATUS = 7 AND o.DELIVERY_ID IS NOT NULL'],
                ['delivery_full_return', 'مرتجع كلي', 'o.ORDER_STATUS = 6 AND o.DELIVERY_ID IS NOT NULL']
            ];
            foreach ($deliveryCustodyStatuses as $statusMetric) {
                $existsCounts[] = [
                    'exists_id' => null,
                    'metric_key' => $statusMetric[0],
                    'exists_name' => $statusMetric[1],
                    'total' => $this->scalar(
                        "SELECT COUNT(*) FROM orders o
                         WHERE $periodWhere
                           AND " . $statusMetric[2],
                        $periodParams
                    )
                ];
            }
            $metrics['orders_today'] = $this->scalar(
                "SELECT COUNT(*) FROM orders o WHERE $allWhere AND o.DATE_CREATED >= CURDATE() AND o.DATE_CREATED < DATE_ADD(CURDATE(), INTERVAL 1 DAY)",
                $allParams
            );
            $metrics['stores_count'] = $this->scalar(
                !$canViewAllBranches && $branchId > 0
                    ? 'SELECT COUNT(*) FROM stores WHERE BRANCH_ID = :branch_id'
                    : ($canViewAllBranches ? 'SELECT COUNT(*) FROM stores' : 'SELECT 0'),
                !$canViewAllBranches && $branchId > 0 ? [':branch_id' => $branchId] : []
            );
            if ($this->isEnabled($adminPermissions['Suppliers']['View'] ?? false)) {
                $metrics['suppliers_count'] = $this->scalar('SELECT COUNT(*) FROM suppliers');
            }
        }

        if (in_array('CustomerService1', $roles, true)) {
            [$where, $params] = $this->orderWhere('o', $branchId, $canViewAllBranches);
            foreach ([
                'cs1_review' => 1,
                'cs1_pending' => 2,
                'cs1_preorder' => 3,
                'cs1_no_response' => 4,
                'cs1_cancel_no_response' => 13,
                'cs1_cancel_order' => 5
            ] as $metric => $existsId) {
                $metrics[$metric] = $this->scalar(
                    "SELECT COUNT(*) FROM orders o WHERE $where AND o.EXISTS_ID = :exists_id",
                    $params + [':exists_id' => $existsId]
                );
            }
        }

        if (in_array('CustomerService2', $roles, true)) {
            [$where, $params] = $this->orderWhere('o', $branchId, $canViewAllBranches);
            $metrics['cs2_processing'] = $this->scalar("SELECT COUNT(*) FROM orders o WHERE $where AND o.EXISTS_ID = 7", $params);
            $metrics['cs2_prepared'] = $this->scalar("SELECT COUNT(*) FROM orders o WHERE $where AND o.EXISTS_ID = 8", $params);
            $metrics['cs2_shipping'] = $this->scalar("SELECT COUNT(*) FROM orders o WHERE $where AND o.EXISTS_ID = 9", $params);
            $metrics['cs2_returns'] = $this->scalar("SELECT COUNT(*) FROM orders o WHERE $where AND o.ORDER_STATUS IN (5,6,7)", $params);
        }

        if (in_array('Storage', $roles, true)) {
            $storeWhere = '';
            $storeParams = [];
            if (!$canViewAllBranches) {
                if ($branchId > 0) {
                    $storeWhere = ' WHERE s.BRANCH_ID = :branch_id';
                    $storeParams[':branch_id'] = $branchId;
                } else {
                    $storeWhere = ' WHERE 1=0';
                }
            }
            $metrics['storage_stores_count'] = $this->scalar('SELECT COUNT(*) FROM stores s' . $storeWhere, $storeParams);
            $metrics['stock_units'] = $this->scalar(
                'SELECT COALESCE(SUM(sp.STO_QTY), 0) FROM stores_product sp INNER JOIN stores s ON s.STO_ID = sp.STO_ID' . $storeWhere,
                $storeParams
            );
            $lowStockWhere = 'pc.CODE_MIN_STOCK > sp.STO_QTY';
            $lowStockParams = [];
            if (!$canViewAllBranches) {
                if ($branchId > 0) {
                    $lowStockWhere .= ' AND s.BRANCH_ID = :branch_id';
                    $lowStockParams[':branch_id'] = $branchId;
                } else {
                    $lowStockWhere .= ' AND 1=0';
                }
            }
            $metrics['low_stock_count'] = $this->scalar(
                "SELECT COUNT(*) FROM (
                    SELECT sp.STO_ID, pc.PRO_CODE
                    FROM stores_product sp
                    INNER JOIN stores s ON s.STO_ID = sp.STO_ID
                    INNER JOIN product_codes pc ON pc.PRO_CODE = sp.PRO_CODE
                    WHERE $lowStockWhere
                    GROUP BY sp.STO_ID, pc.PRO_CODE
                ) low_stock_items",
                $lowStockParams
            );
            $lowStockRows = $this->rows(
                "SELECT s.STO_NAME AS store_name, p.PRO_NAME AS product_name,
                        pc.PRO_CODE AS product_code, pc.CODE_MIN_STOCK AS minimum_qty,
                        sp.STO_QTY AS current_qty
                 FROM stores_product sp
                 INNER JOIN stores s ON s.STO_ID = sp.STO_ID
                 INNER JOIN product_codes pc ON pc.PRO_CODE = sp.PRO_CODE
                 INNER JOIN products p ON p.PRO_ID = pc.PRODUCT_ID
                 WHERE $lowStockWhere
                 ORDER BY (pc.CODE_MIN_STOCK - sp.STO_QTY) DESC LIMIT 50",
                $lowStockParams
            );
        } else {
            $lowStockRows = [];
        }

        if (in_array('Delivery', $roles, true)) {
            [$where, $params] = $this->orderWhere('o', $branchId, $canViewAllBranches);
            $metrics['delivery_assigned_orders'] = $this->scalar("SELECT COUNT(*) FROM orders o WHERE $where AND o.DELIVERY_ID IS NOT NULL", $params);
            $metrics['delivery_delivered'] = $this->scalar("SELECT COUNT(*) FROM orders o WHERE $where AND o.DELIVERY_ID IS NOT NULL AND o.ORDER_STATUS IN (3,4)", $params);
            $metrics['delivery_rejected'] = $this->scalar("SELECT COUNT(*) FROM orders o WHERE $where AND o.DELIVERY_ID IS NOT NULL AND o.ORDER_STATUS = 5", $params);
            $metrics['delivery_returns'] = $this->scalar("SELECT COUNT(*) FROM orders o WHERE $where AND o.DELIVERY_ID IS NOT NULL AND o.ORDER_STATUS IN (6,7)", $params);
            $deliveryRows = $this->rows(
                "SELECT CONCAT(u.FORENAME, ' ', u.LASTNAME) AS delivery_name,
                        COUNT(*) AS assigned_count,
                        SUM(CASE WHEN o.ORDER_STATUS IN (3,4) THEN 1 ELSE 0 END) AS delivered_count,
                        SUM(CASE WHEN o.ORDER_STATUS = 5 THEN 1 ELSE 0 END) AS rejected_count,
                        SUM(CASE WHEN o.ORDER_STATUS IN (6,7) THEN 1 ELSE 0 END) AS returned_count
                 FROM orders o
                 INNER JOIN users u ON u.USER_ID = o.DELIVERY_ID
                 WHERE $where AND o.DELIVERY_ID IS NOT NULL
                 GROUP BY o.DELIVERY_ID, u.FORENAME, u.LASTNAME
                 ORDER BY assigned_count DESC LIMIT 50",
                $params
            );
        } else {
            $deliveryRows = [];
        }


        if (in_array('Admin', $roles, true)) {
            [$productWhere, $productParams] = $this->orderWhere('o', $branchId, $canViewAllBranches);
            $topProducts = $this->rows(
                "SELECT p.PRO_NAME AS product_name,
                        SUM(GREATEST(op.QTY - op.RETURNED_QTY, 0)) AS sold_qty,
                        SUM(GREATEST(op.QTY - op.RETURNED_QTY, 0) * GREATEST(op.PRICE - op.DISCOUNT, 0)) AS sales_value
                 FROM orders o
                 INNER JOIN order_product op ON op.ORDER_ID = o.ORDER_ID
                 INNER JOIN product_codes pc ON pc.PRO_CODE = op.PRO_CODE
                 INNER JOIN products p ON p.PRO_ID = pc.PRODUCT_ID
                 WHERE $productWhere
                   AND op.PRO_STATUSE NOT IN ('rejected', 'refund')
                 GROUP BY p.PRO_ID, p.PRO_NAME
                 HAVING SUM(GREATEST(op.QTY - op.RETURNED_QTY, 0)) > 0
                 ORDER BY sold_qty DESC, sales_value DESC
                 LIMIT 20",
                $productParams
            );
        } else {
            $topProducts = [];
        }

        return [
            'metrics' => $metrics,
            'exists_counts' => $existsCounts,
            'orders_trend' => $ordersTrend,
            'status_distribution' => $statusRows,
            'latest_orders' => $latestOrders,
            'low_stock' => $lowStockRows,
            'delivery_performance' => $deliveryRows,
            'top_products' => $topProducts
        ];
    }
}
