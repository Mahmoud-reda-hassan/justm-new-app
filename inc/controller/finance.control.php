<?php
session_start();
include_once(__dir__.'/../../config.php');
include_once(ABSPATH.'/inc/Databases/dbconn.php');
include_once(ABSPATH.'/inc/controller/BackEnd/finance_BE.php');
include_once(ABSPATH.'/inc/Classes/Accounting/DeliverySettlementManager.php');
include_once(ABSPATH.'/inc/Classes/Accounting/ShippingConfigurationManager.php');
include_once(ABSPATH.'/inc/Classes/Accounting/DeliveryRejectedShippingReviewManager.php');

$DBconn        	= new dbconn();
$db            	= $DBconn->getConnection(DB_HOST,DB_PORT,DB_USER,DB_PASSWORD,DB_NAME,DB_CHARSET);
$deliveryShippingReviews = new DeliveryRejectedShippingReviewManager($db);
$CBE 	= new finance_BE($db);
$deliveryAccounting = new DeliverySettlementManager($db);
$shippingConfiguration = new ShippingConfigurationManager($db);
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
        case 'GetShippingAmountEditSetting':
            $requireFinanceAdmin();
            echo json_encode([
                'success' => true,
                'allow_shipping_amount_edit' => $shippingConfiguration->isShippingAmountEditAllowed(),
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'UpdateShippingAmountEditSetting':
            $requireFinanceAdmin();
            $rawValue = $_POST['allow_shipping_amount_edit'] ?? null;
            if (!in_array((string)$rawValue, ['0', '1'], true)) {
                throw new InvalidArgumentException('قيمة إعداد تعديل رسوم الشحن غير صحيحة.');
            }
            $shippingConfiguration->setShippingAmountEditAllowed(
                $rawValue === '1',
                (int)($_SESSION['USER_ID'] ?? 0)
            );
            echo json_encode([
                'success' => true,
                'allow_shipping_amount_edit' => $shippingConfiguration->isShippingAmountEditAllowed(),
            ], JSON_UNESCAPED_UNICODE);
            break;
        case 'PayMarketerCommission':
            $requireFinanceAdmin();
            $marketerId = (int)($_POST['partner_id'] ?? 0);
            $paymentMethodId = (int)($_POST['payment_method_id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0);
            $requestKey = trim((string)($_POST['request_key'] ?? ''));
            $note = trim((string)($_POST['note'] ?? ''));
            $actorId = (int)($_SESSION['USER_ID'] ?? 0);
            if ($branchId <= 0) throw new RuntimeException('الفرع الحالي غير محدد في الجلسة.');
            if ($actorId <= 0) throw new RuntimeException('تعذر تحديد المستخدم المنفذ للعملية.');
            if (!preg_match('/^[A-Za-z0-9._:-]{8,64}$/D', $requestKey)) {
                throw new InvalidArgumentException('مفتاح طلب الصرف مطلوب.');
            }
            $requestHash = hash('sha256', json_encode([
                'actor_id' => $actorId,
                'marketer_id' => $marketerId,
                'branch_id' => $branchId,
                'payment_method_id' => $paymentMethodId,
                'amount' => number_format(round($amount, 2), 2, '.', ''),
                'note' => $note,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $result = $deliveryAccounting->payMarketerCommission(
                $marketerId, $branchId, $paymentMethodId, $amount, $note, $requestKey, $requestHash
            );
            echo json_encode([
                'success',
                'ناجح',
                !empty($result['already_posted']) ? 'تم تسجيل نفس عملية الصرف سابقًا دون تكرار القيد.' : 'تم صرف مستحقات المسوق بنجاح.',
                'payment' => $result,
            ], JSON_UNESCAPED_UNICODE);
            break;

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

        case 'GetRejectedShippingReviews':
            $requireFinanceAdmin();
            echo json_encode(['success' => true, 'reviews' => $deliveryShippingReviews->listPending($branchId, (int)($_POST['delivery_id'] ?? 0) ?: null)], JSON_UNESCAPED_UNICODE);
            break;

        case 'DecideRejectedShippingReview':
            $requireFinanceAdmin();
            if ($branchId <= 0) throw new RuntimeException('الفرع الحالي غير محدد في الجلسة.');
            $approve = (string)($_POST['decision'] ?? '') === 'APPROVE';
            if (!in_array((string)($_POST['decision'] ?? ''), ['APPROVE', 'REJECT'], true)) {
                throw new InvalidArgumentException('قرار التسوية غير صحيح.');
            }
            $result = $deliveryShippingReviews->decide(
                (int)($_POST['review_id'] ?? 0), $branchId, (int)($_SESSION['USER_ID'] ?? 0),
                $approve, (float)($_POST['amount'] ?? 0), (string)($_POST['note'] ?? '')
            );
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
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
                    $orderId = (int)$item['order_id'];
                    if ($orderId <= 0 || array_key_exists($orderId, $normalized)) {
                        throw new InvalidArgumentException('لا يمكن تكرار أمر البيع داخل طلب التحصيل.');
                    }
                    $normalized[$orderId] = (float)$item['amount'];
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
            $note = trim((string)($_POST['note'] ?? ''));
            $requestKey = trim((string)($_POST['request_key'] ?? ''));
            $canonicalOrderIds = array_values(array_unique(array_map('intval', $orderIds)));
            sort($canonicalOrderIds, SORT_NUMERIC);
            $requestHash = hash('sha256', json_encode([
                'delivery_id' => $deliveryId,
                'branch_id' => $branchId,
                'payment_method_id' => $paymentMethodId,
                'order_ids' => $canonicalOrderIds,
                'amount' => round($amount, 2),
                'commission_mode' => $mode,
                'note' => $note,
            ], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
            if ($orderIds && $amount <= 0) {
                $result = $deliveryAccounting->settleSelectedOrders($deliveryId, $branchId, $paymentMethodId,
                    $orderIds, $mode, $note, $requestKey, $requestHash);
            } else {
                $result = $deliveryAccounting->settle($deliveryId, $branchId, $paymentMethodId,
                    $amount, $orderIds, $mode, $note, $requestKey, $requestHash);
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'PayDeliveryCommission':
            $requireFinanceAdmin();
            if ($branchId <= 0) throw new RuntimeException('الفرع الحالي غير محدد في الجلسة.');
            $deliveryId = (int)($_POST['delivery_id'] ?? 0);
            $paymentMethodId = (int)($_POST['payment_method_id'] ?? 0);
            $amount = round((float)($_POST['amount'] ?? 0), 2);
            $note = trim((string)($_POST['note'] ?? ''));
            $requestKey = trim((string)($_POST['request_key'] ?? ''));
            $requestHash = hash('sha256', json_encode([
                'delivery_id' => $deliveryId,
                'branch_id' => $branchId,
                'payment_method_id' => $paymentMethodId,
                'amount' => $amount,
                'note' => $note,
            ], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
            $result = $deliveryAccounting->payCommission(
                $deliveryId, $branchId, $paymentMethodId, $amount, $note, $requestKey, $requestHash
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