<?php
session_start();
include_once(__dir__.'/../../config.php');
include_once(ABSPATH.'/inc/Databases/dbconn.php');
include_once(ABSPATH.'/inc/controller/BackEnd/finance_BE.php');
include_once(ABSPATH.'/inc/Classes/Accounting/DeliverySettlementManager.php');

$DBconn        	= new dbconn();
$db            	= $DBconn->getConnection(DB_HOST,DB_PORT,DB_USER,DB_PASSWORD,DB_NAME,DB_CHARSET);
$CBE 	= new finance_BE($db);
$deliveryAccounting = new DeliverySettlementManager($db);
$http_RQM = $_SERVER['REQUEST_METHOD'];
/*===================================================
** $Database Shipment_tbl table 
**====================================================*/
if($http_RQM != 'POST'){
    echo json_encode(array('error', 'Error', 'The request type is wrong', []));
    exit();
}

if(!isset($_POST['CONTROL'])){
	echo json_encode(array('error', 'Error', 'The parameters sent in your request are wrong.', []));
	exit();
}

header('Content-Type: application/json; charset=utf-8');
$requireFinanceAdmin = static function (): void {
    if ((int)($_SESSION['ADMIN_TYPE'] ?? 0) !== 1) {
        throw new RuntimeException('غير مصرح لك بتنفيذ عمليات محاسبة المندوبين.');
    }
};
$readArray = static function ($value, string $fieldName): array {
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    if (!is_array($decoded)) throw new InvalidArgumentException('تنسيق الحقل ' . $fieldName . ' غير صحيح.');
    return $decoded;
};
$branchId = (int)($_SESSION['BRANCH_ID'] ?? 0);

// معالجة الطلبات
try {
    switch ($_POST['CONTROL']) {
        case 'GetDeliverySettlementPaymentMethods':
            $requireFinanceAdmin();
            if ($branchId <= 0) throw new RuntimeException('الفرع الحالي غير محدد في الجلسة.');
            // نعرض فقط طرق الدفع المرتبطة بحساب أستاذ نشط للفرع الحالي.
            $columnsStmt = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_methods'");
            $methodColumns = array_map('strtolower', $columnsStmt->fetchAll(PDO::FETCH_COLUMN));
            $labelColumn = null;
            foreach (['name_ar', 'method_name_ar', 'payment_method_name', 'method_name', 'name', 'title', 'label'] as $candidate) {
                if (in_array($candidate, $methodColumns, true)) { $labelColumn = $candidate; break; }
            }
            $labelExpr = $labelColumn ? ('pm.`' . $labelColumn . '`') : "CONCAT('طريقة دفع #', pm.id)";
            $methodsStmt = $db->prepare("SELECT pm.id, COALESCE(NULLIF(CAST($labelExpr AS CHAR), ''), CONCAT('طريقة دفع #', pm.id)) AS name
                FROM payment_methods pm
                INNER JOIN payment_method_accounts pma ON pma.payment_method_id = pm.id
                    AND pma.branch_id = ? AND pma.is_active = 1
                WHERE pm.is_active = 1 ORDER BY pm.id");
            $methodsStmt->execute([$branchId]);
            echo json_encode(['success' => true, 'methods' => $methodsStmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
            break;

        case 'GetDeliveryCommissionMode':
            $requireFinanceAdmin();
            echo json_encode(['success' => true, 'mode' => $deliveryAccounting->getCommissionSettlementMode()], JSON_UNESCAPED_UNICODE);
            break;

        case 'UpdateDeliveryCommissionMode':
            $requireFinanceAdmin();
            $deliveryAccounting->updateCommissionSettlementMode((string)($_POST['mode'] ?? ''), (int)($_SESSION['USER_ID'] ?? 0));
            echo json_encode(['success' => true, 'mode' => $deliveryAccounting->getCommissionSettlementMode()], JSON_UNESCAPED_UNICODE);
            break;

        case 'GetDeliveryOpenOrders':
            $requireFinanceAdmin();
            echo json_encode(['success' => true, 'orders' => $deliveryAccounting->listOpenOrders((int)($_POST['delivery_id'] ?? 0))], JSON_UNESCAPED_UNICODE);
            break;

        case 'PreviewDeliverySettlement':
            $requireFinanceAdmin();
            $deliveryId = (int)($_POST['delivery_id'] ?? 0);
            $orderIds = array_map('intval', $readArray($_POST['order_ids'] ?? [], 'order_ids'));
            $requestedAmount = (float)($_POST['amount'] ?? 0);
            $preview = $requestedAmount > 0
                ? $deliveryAccounting->previewAllocation($deliveryId, $requestedAmount, $orderIds)
                : $deliveryAccounting->previewSelectedOrders($deliveryId, $orderIds);
            echo json_encode(['success' => true, 'preview' => $preview], JSON_UNESCAPED_UNICODE);
            break;

        case 'RegisterDeliveryCustomerCollection':
            $requireFinanceAdmin();
            if ($branchId <= 0) throw new RuntimeException('الفرع الحالي غير محدد في الجلسة.');
            $orderAmounts = $readArray($_POST['order_amounts'] ?? [], 'order_amounts');
            if ($orderAmounts && array_keys($orderAmounts) === range(0, count($orderAmounts) - 1)) {
                $normalized = [];
                foreach ($orderAmounts as $item) {
                    if (!is_array($item) || empty($item['order_id']) || !isset($item['amount'])) {
                        throw new InvalidArgumentException('كل تخصيص يجب أن يحتوي order_id و amount.');
                    }
                    $normalized[(int)$item['order_id']] = (float)$item['amount'];
                }
                $orderAmounts = $normalized;
            }
            $requestKey = trim((string)($_POST['request_key'] ?? ''));
            $result = $deliveryAccounting->registerCustomerCollection(
                (int)($_POST['delivery_id'] ?? 0), $branchId,
                (int)($_POST['payment_method_id'] ?? 0), $orderAmounts,
                $requestKey, (string)($_POST['note'] ?? '')
            );
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'SettleDeliveryCollections':
            $requireFinanceAdmin();
            if ($branchId <= 0) throw new RuntimeException('الفرع الحالي غير محدد في الجلسة.');
            $deliveryId = (int)($_POST['delivery_id'] ?? 0);
            $paymentMethodId = (int)($_POST['payment_method_id'] ?? 0);
            $orderIds = array_map('intval', $readArray($_POST['order_ids'] ?? [], 'order_ids'));
            $amount = (float)($_POST['amount'] ?? 0);
            $mode = isset($_POST['commission_mode']) && $_POST['commission_mode'] !== '' ? (string)$_POST['commission_mode'] : null;
            if ($orderIds && $amount <= 0) {
                $result = $deliveryAccounting->settleSelectedOrders($deliveryId, $branchId, $paymentMethodId,
                    $orderIds, $mode, (string)($_POST['note'] ?? ''));
            } else {
                $result = $deliveryAccounting->settle($deliveryId, $branchId, $paymentMethodId,
                    $amount, $orderIds, $mode, (string)($_POST['note'] ?? ''));
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'PayDeliveryCommission':
            $requireFinanceAdmin();
            if ($branchId <= 0) throw new RuntimeException('الفرع الحالي غير محدد في الجلسة.');
            $result = $deliveryAccounting->payCommission(
                (int)($_POST['delivery_id'] ?? 0), $branchId,
                (int)($_POST['payment_method_id'] ?? 0), (float)($_POST['amount'] ?? 0),
                (string)($_POST['note'] ?? '')
            );
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

		case 'CollectingFinancialPledge':
			$CBE->transaction_user_id = $_SESSION['USER_ID'];
			$CBE->user_group 		  = $_SESSION['USER_GRUOP_NAME'];
			echo json_encode($CBE->registerCollection($_POST['delivery_id'], $_POST['orderObj'], $_POST['totalAmount']));
		    break;
		case 'PaymentOfCustomerDues':
		    $CBE->transaction_user_id = $_SESSION['USER_ID'];
			$CBE->user_group 		  = $_SESSION['USER_GRUOP_NAME'];
		    echo json_encode($CBE->PaymentOfCustomerDues($_POST['oid']));
			break;
		case 'SaveSupplierPayment':
			$supplier_id= $_POST['supplier_id'];
			$total_amount = $_POST['total_amount'];
			$orders = isset($_POST['orders']) ? $_POST['orders']: [];
			echo json_encode($CBE->PaymentOfSupplierDues($supplier_id, $total_amount, $orders));
			break;
		case 'PaymentOfMarketerDues':
			$marketerId= $_POST['partner_id'];
			$total_amount = $_POST['amount'];
			$total_balance = isset($_POST['balance']) ? $_POST['balance']: 0;
			echo json_encode($CBE->PaymentOfMarketerDues($marketerId, $total_balance, $total_amount));
			break;
		case 'PaymentOfDeliveryDues':
			$deliveryid= $_POST['partner_id'];
			$total_amount = $_POST['amount'];
			$total_balance = isset($_POST['balance']) ? $_POST['balance']: 0;
			echo json_encode($CBE->PaymentOfDeliveryDues($deliveryid, $total_balance, $total_amount));
			break;

		default:
		    echo json_encode(array('error', 'Error', 'Your request is not available', []));
			break;
		}
}catch (Exception $e) {
	echo json_encode(array('error', 'خطاء', $e->getMessage()));
}finally{
	$db = null;
}
exit();

?>