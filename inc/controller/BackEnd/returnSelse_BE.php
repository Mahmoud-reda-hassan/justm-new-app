<?php

include_once(__dir__.'/../../../config.php');
include_once(ABSPATH.'/inc/configDateTimeFN.php');
include_once(ABSPATH.'/inc/controller/BackEnd/inventory_BE.php');
include_once(ABSPATH.'/inc/controller/BackEnd/finance_BE.php');
include_once(ABSPATH.'/inc/Classes/LogManager.php');
include_once(ABSPATH.'/inc/Classes/InventoryManager.php');

class shipment_BE {
	private $orders_log_Tbl = 'ORDERS_LOG';
	private $store_id;
	//==========tbl ORDERS_LOG============
	public $branch_id;
	public $user_action;
	public $user_group;
	//==========tbl ORDERS===============
	public $order_id;
	public $delivery_id;
	public $delivery_name;
	public $shiping_date;
	public $last_update;
	//==========public shipment tbl===========
	public $shipment_id;
	public $user_id;
	public $shipmentobj;
	//==========public variable===========
	public $orderobj;
	public $urlControl;
	public $ShipApproved;
	//==========public EXCHANGE===========
	public $ShipExchange;
	
	private $InventoryManager;
	private $logManager;
	private $dtz;
	private $conn;

	public function __construct($db) {
		$this->conn = $db;
		$this->dtz = new configDateTime();
		$this->logManager = new LogManager(null, 'shipping');
		$this->InventoryManager = new InventoryManager($db);
	}

	//==========وظائف جزئية يعتمد عليها الوظائف العامة========
	private function viewReturnProducts(int $oid):?array{
		$return = ['no' => [],'ex' => []];
		$stmt = $this->conn->prepare(
			"SELECT O.ORDER_ID, O.ORDER_STATUS AS ORDER_STATUSE, OP.PRO_CODE, PD.PRO_NAME, CASE WHEN OPT_JSON IS NOT NULL THEN PD.OPT_JSON ELSE '' END AS OPTION, OP.QTY, OP.DELIVERED_QTY, OP.RETURNED_QTY, OP.PRO_STATUSE
			FROM ORDERS AS O LEFT JOIN ORDER_PRODUCT AS OP ON OP.ORDER_ID = O.ORDER_ID LEFT JOIN (SELECT  PC.PRO_CODE, PR.PRO_NAME, Concat(Group_concat(Concat(E.OPTION_NAME, ' : ', D.VALUE))) AS OPT_JSON FROM   PRODUCT_CODES PC LEFT JOIN PRODUCTS PR ON PC.PRODUCT_ID = PR.PRO_ID LEFT JOIN PRODUCT_CODE_JOIN_OPTION C ON PC.PRO_CODE = C.PRODUCT_CODE LEFT JOIN PRODUCT_OPTION D ON D.ID = C.OPTION_VALUE_ID LEFT JOIN PRODUCT_OPTION_GROUP E ON E.OPTION_ID = D.OPTION_ID GROUP BY PC.PRO_CODE,PR.PRO_NAME ) AS PD ON OP.PRO_CODE = PD.PRO_CODE
			WHERE O.ORDER_ID = :oid AND O.ORDER_SUCCESS = 1 AND DELIVERED_QTY > 0 ";
		);
		$stmt->execute([':oid' => $oid]);
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$return['ex'][] = [
				'oid' => $row['ORDER_ID'] ?? '',
				'did' => 0,
				'code' => $row['PRO_CODE'] ?? '',
				'name' => $row['PRO_NAME'] ?? '',
				'option' => isset($row['OPTION']) ? explode(',', $row['OPTION']) : [],
				'qty' => (float) ($row['QTY'] ?? 0),
				'delivered_qty' => (float) (($row['PRO_STATUSE'] ?? '') === 'WAITING' ? ($row['QTY'] ?? 0) : ($row['DELIVERED_QTY'] ?? 0)), 'returned_qty' => (float) $row['RETURNED_QTY'] ?? 0, 'status' => $row['PRO_STATUSE'] ?? 'UNKNOWN' 
			];
		}
		return $return;
	}
	
	private function handleViewMode(int $oid): array {
		try {
			
			// جلب المنتجات العادية للطلب
			$products = $this->viewReturnProducts($this->order_id);

			// إرجاع نتيجة نجاح مع بيانات المنتجات
			return [
				'type' => 'success',
				'products' => $products['no'],
				'returned' => $products['ex']
			];

		} catch (Exception $e) {
			// معالجة أي استثناء قد يحدث وإرجاع رسالة خطأ
			return [
				'type' => 'error', 
				'title' => 'خطأ',
				'message' => 'حدث خطأ أثناء معالجة الطلب: ' . $e->getMessage()
			];
		}
	}
}

?>