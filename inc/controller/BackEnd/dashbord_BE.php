<?php
class dashbord_BE {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    private function scalar($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $value = $stmt->fetchColumn();
            return $value === false || $value === null ? null : (0 + $value);
        } catch (Throwable $e) {
            error_log('Dashboard metric query error: ' . $e->getMessage());
            return null;
        }
    }

    private function isAllowed($permissions, $key, $action = 'View') {
        $value = $permissions[$key][$action] ?? false;
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    public function getDashboardData($roles, $adminPermissions, $userId, $branchId) {
        $metrics = [];
        $ordersTrend = [];

        if (in_array('Admin', $roles, true)) {
            $metrics['orders_today'] = $this->scalar(
                'SELECT COUNT(*) FROM orders WHERE DATE_CREATED >= CURDATE() AND DATE_CREATED < DATE_ADD(CURDATE(), INTERVAL 1 DAY)'
            );
            $metrics['unpaid_orders'] = $this->scalar(
                'SELECT COUNT(*) FROM orders WHERE ORDER_SUCCESS = 0'
            );
            $metrics['stores_count'] = $this->scalar('SELECT COUNT(*) FROM stores');

            if ($this->isAllowed($adminPermissions, 'Suppliers')) {
                $metrics['suppliers_count'] = $this->scalar('SELECT COUNT(*) FROM suppliers');
            }

            try {
                $stmt = $this->conn->prepare(
                    'SELECT DATE(DATE_CREATED) AS order_day, COUNT(*) AS total
                     FROM orders
                     WHERE DATE_CREATED >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                       AND DATE_CREATED < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                     GROUP BY DATE(DATE_CREATED)
                     ORDER BY order_day'
                );
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $byDay = [];
                foreach ($rows as $row) {
                    $byDay[$row['order_day']] = (int)$row['total'];
                }
                for ($offset = 6; $offset >= 0; $offset--) {
                    $day = date('Y-m-d', strtotime('-' . $offset . ' days'));
                    $ordersTrend[] = [
                        'label' => date('d/m', strtotime($day)),
                        'total' => $byDay[$day] ?? 0
                    ];
                }
            } catch (Throwable $e) {
                error_log('Dashboard orders trend error: ' . $e->getMessage());
            }
        }

        if (in_array('CustomerService1', $roles, true)) {
            $metrics['cs1_total_orders'] = $this->scalar('SELECT COUNT(*) FROM orders');
            $metrics['cs1_today_orders'] = $this->scalar(
                'SELECT COUNT(*) FROM orders WHERE DATE_CREATED >= CURDATE() AND DATE_CREATED < DATE_ADD(CURDATE(), INTERVAL 1 DAY)'
            );
        }

        if (in_array('CustomerService2', $roles, true)) {
            $metrics['cs2_total_orders'] = $this->scalar('SELECT COUNT(*) FROM orders');
            $metrics['cs2_today_orders'] = $this->scalar(
                'SELECT COUNT(*) FROM orders WHERE DATE_CREATED >= CURDATE() AND DATE_CREATED < DATE_ADD(CURDATE(), INTERVAL 1 DAY)'
            );
        }

        if (in_array('Storage', $roles, true)) {
            $storeScope = '';
            $params = [];
            $canViewAllBranches = $this->isAllowed($adminPermissions, 'UserSpecificPermissions', 'viewAllBranches')
                || $this->isAllowed($adminPermissions, 'StockAudit', 'viewAllBranches');

            if (!$canViewAllBranches && $branchId > 0) {
                $storeScope = ' WHERE BRANCH_ID = :branch_id';
                $params[':branch_id'] = $branchId;
            }

            $metrics['storage_stores_count'] = $this->scalar(
                'SELECT COUNT(*) FROM stores' . $storeScope,
                $params
            );

            $stockSql = 'SELECT COALESCE(SUM(SP.STO_QTY), 0)
                         FROM stores_product SP
                         INNER JOIN stores S ON S.STO_ID = SP.STO_ID';
            $stockParams = [];
            if (!$canViewAllBranches && $branchId > 0) {
                $stockSql .= ' WHERE S.BRANCH_ID = :branch_id';
                $stockParams[':branch_id'] = $branchId;
            }
            $metrics['stock_units'] = $this->scalar($stockSql, $stockParams);

            $lowStockSql = 'SELECT COUNT(*) FROM (
                                SELECT SP.STO_ID, PC.PRO_CODE
                                FROM PRODUCTS PR
                                INNER JOIN PRODUCT_CODES PC ON PC.PRODUCT_ID = PR.PRO_ID
                                INNER JOIN stores_product SP ON SP.PRO_CODE = PC.PRO_CODE
                                INNER JOIN stores S ON S.STO_ID = SP.STO_ID
                                WHERE PC.CODE_MIN_STOCK > SP.STO_QTY';
            $lowStockParams = [];
            if (!$canViewAllBranches && $branchId > 0) {
                $lowStockSql .= ' AND S.BRANCH_ID = :branch_id';
                $lowStockParams[':branch_id'] = $branchId;
            }
            $lowStockSql .= ' GROUP BY SP.STO_ID, PC.PRO_CODE
                            ) AS low_stock_items';
            $metrics['low_stock_count'] = $this->scalar($lowStockSql, $lowStockParams);
        }

        if (in_array('Delivery', $roles, true)) {
            $metrics['delivery_assigned_orders'] = $this->scalar(
                'SELECT COUNT(*) FROM orders WHERE DELIVERY_ID IS NOT NULL'
            );
            $metrics['delivery_delivered_today'] = $this->scalar(
                'SELECT COUNT(*) FROM orders
                 WHERE DELIVERY_ID IS NOT NULL
                   AND DELIVERED_DATE >= CURDATE()
                   AND DELIVERED_DATE < DATE_ADD(CURDATE(), INTERVAL 1 DAY)'
            );
        }

        return [
            'metrics' => $metrics,
            'orders_trend' => $ordersTrend
        ];
    }
}
