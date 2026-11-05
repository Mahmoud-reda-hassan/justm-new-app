<?php
//include_once(__dir__.'/../../../config.php');
include_once(ABSPATH.'/inc/configDateTimeFN.php');

/**
 * ButtonEngine - تجميع منطق الأزرار لتقليل التكرار وتحسين الأداء
 */
class ButtonEngine {
    private static $permissionsCache = [];
    private static $sessionData = [];
    private static $requestData = [];
    
    public static function init($request, $userPermissions) {
        self::$permissionsCache = $userPermissions;
        self::$sessionData['USER_ID'] = $_SESSION['USER_ID'] ?? 0;
        self::$sessionData['BRANCH_ID'] = $_SESSION['BRANCH_ID'] ?? 0;
        self::$requestData = $request;
    }
    
    private static function getServiceNotification($orderData) {
        if ($orderData['OS_ID'] > 0 && $orderData['OS_STATUSE'] == 0 && $orderData['MSG_COUNT'] > 0) {
            return $orderData['MSG_COUNT'];
        }
        if ($orderData['OS_ID'] > 0 && $orderData['OS_STATUSE'] == 0 && $orderData['MSG_COUNT'] == 0) {
            return '<i class="fa-regular fa-envelope"></i>';
        }
        return "";
    }
    
    private static function makeButton($orderData, $type, $use, $extra = []) {
        $configs = [
            'view' => [
                'oclass' => $use ? "text-primary border" : "text-secondary border disble",
                'elid' => 'btnOview',
                'icon' => 'fa-solid fa-eye',
                'name' => 'عرض'
            ],
            'edit' => [
                'oclass' => $use ? "text-info border" : "text-secondary border disble",
                'elid' => 'btnOedit',
                'icon' => 'fa-solid fa-edit',
                'name' => 'تعديل'
            ],
            'delete' => [
                'oclass' => $use ? "text-danger border" : "text-secondary border disble",
                'elid' => 'btnOdel',
                'icon' => 'fa-solid fa-ban',
                'name' => 'إلغاء'
            ],
            'service' => [
                'oclass' => $use ? "text-warning border" : "text-secondary border disble",
                'elid' => 'btnOservice',
                'icon' => 'fa-solid fa-headset',
                'name' => 'الدعم',
                'msgnotification' => self::getServiceNotification($orderData)
            ],
            'return' => [
                'oclass' => $use ? "text-info border" : "text-secondary border disble",
                'elid' => 'btnReturn',
                'icon' => 'fa-solid fa-exchange',
                'name' => 'إسترداد'
            ],
            'returnProduct' => [
                'oclass' => $use ? "text-info border" : "text-secondary border disble",
                'elid' => 'returnProduct',
                'icon' => 'fa-solid fa-exchange',
                'name' => 'استرجاع'
            ],
            'transformation' => [
                'oclass' => $use ? (($orderData["ORDER_STATUS"] == 1 && $orderData["PRINT"]) ? "bg-primary" : "text-warning border") : "text-secondary border disble",
                'elid' => 'btnOservice',
                'icon' => 'fa-solid fa-exchange',
                'name' => 'تحويل'
            ],
            'print' => [
                'oclass' => $use ? "text-success border btnSelectOLiest" : "text-secondary border disble",
                'elid' => 'btnSelectOLiest',
                'icon' => $orderData['PRINT'] == true ? "fa-solid fa-file-circle-check" : "fa-solid fa-print",
                'name' => 'طباعة'
            ],
            'shipment' => [
                'oclass' => $use ? "text-primary border btnSelectOShipp" : "text-secondary border disble",
                'elid' => 'btnSelectOShipp',
                'icon' => 'fa-solid fa-truck-fast',
                'name' => 'شحن'
            ],
            'handshake' => [
                'oclass' => $use ? "text-success border btnDelevered" : "text-secondary border disble",
                'elid' => 'btnDelevered',
                'icon' => 'fa-solid fa-handshake',
                'name' => 'تسليم'
            ],
            'receivingReturns' => [
                'oclass' => $use ? "text-danger border btnReceivingReturns" : "text-secondary border disble",
                'elid' => 'btnReceivingReturns',
                'icon' => 'fa-solid fa-indent',
                'name' => 'استلام'
            ],
            'newOrderExchange' => [
                'oclass' => $use ? "text-danger border btnNewOrderExchange" : "text-secondary border disble",
                'elid' => 'btnNewOrderExchange',
                'icon' => 'fa-solid fa-copy',
                'name' => 'استبدال'
            ],
            'exchangeOrder' => [
                'oclass' => $use ? "text-success border btnExchangeOrder" : "text-secondary border disble",
                'elid' => 'btnExchangeOrder',
                'icon' => 'fa-solid fa-plus',
                'name' => ''
            ],
            'reactivation' => [
                'oclass' => $use ? "text-success border btnReactivation" : "text-secondary border disble",
                'elid' => 'btnReactivation',
                'icon' => 'fa-solid fa-recycle',
                'name' => 'اعادة الشحن'
            ],
            'refund' => [
                'oclass' => $use ? "text-danger border btnRefund" : "text-secondary border disble",
                'elid' => 'btnRefund',
                'icon' => 'fa-solid fa-person-walking-arrow-loop-left',
                'name' => 'ارجاع'
            ],
            'shipConfirmation' => [
                'oclass' => $use ? "text-danger border btnShipConfirmation" : "text-secondary border disble",
                'elid' => 'btnShipConfirmation',
                'icon' => 'fa-solid fa-phone-alt',
                'name' => 'اتصال'
            ]
        ];
        
        $config = $configs[$type] ?? $configs['view'];
        return [
            'oid' => $use ? $orderData["ORDER_ID"] : "",
            'oclass' => $config['oclass'],
            'available' => $use ? "" : "disabled",
            'elid' => $config['elid'],
            'icon' => $config['icon'],
            'name' => $config['name'],
            'msgnotification' => $config['msgnotification'] ?? ""
        ];
    }
    
    // ==================== منطق الأزرار المجمع حسب الحالة ====================
    
    public static function getBtnTR($orderData) {
        $ptype = self::$requestData["ptype"] ?? 1;
        
        switch($ptype){
            case 1:
                return self::makeButton($orderData, 'view', true);
            case 2:
                return self::makeButton($orderData, 'view', (self::$sessionData['USER_ID'] == $orderData['ORDER_BY']));
            case 3:
                $canView = ((($orderData["EXISTS_ID"] !== 6 || $orderData["EXISTS_ID"] !== 12) && self::$sessionData['USER_ID'] == $orderData['DELIVERY_ID']) || $orderData["EXISTS_ID"] == 6 || $orderData["EXISTS_ID"] == 12);
                return self::makeButton($orderData, 'view', $canView);
        }
        return self::makeButton($orderData, 'view', false);
    }
    
    public static function getBtnTL($orderData) {
        $ptype = self::$requestData["ptype"] ?? 1;
        $control = self::$requestData['CONTROL'] ?? '';
        $ex = self::$requestData["ex"] ?? 0;
        
        switch($ptype){
            case 1:
                if($control === "Orderlist" && $ex != 8){
                    $canEdit = ($orderData["ORDER_SUCCESS"] == 0 && $orderData["ORDER_STATUS"] < 2 && $orderData["PRINT"] == 0 && $ex !== 0);
                    return self::makeButton($orderData, 'edit', $canEdit);
                }
                if($control === "Orderlist" && $ex == 8){
                    return self::makeButton($orderData, 'print', true);
                }
                if($control === "OrdersCustody"){
                    $canReceive = (
                        ($orderData["CUST_STAT"] === "FREEZE" && $orderData["RETURNS"] == 0) ||
                        ($orderData["CUST_STAT"] === "DELIVERED" && $orderData["ORDER_SUCCESS"] == 0 && $orderData["ORDER_STATUS"] > 3 && $orderData["RETURNS"] == 0) || 
                        ($orderData["CUST_STAT"] === "DELIVERED" && $orderData["ORDER_SUCCESS"] == 0 && $orderData["ORDER_STATUS"] == 3 && $orderData["RETURNS"] == 0)
                    );
                    return self::makeButton($orderData, 'receivingReturns', $canReceive);
                }
                if($control === "SupportOrders" || $control === "SupportAfterSales"){
                    $canEdit = ($orderData["ORDER_SUCCESS"] == 0 && $orderData["ORDER_STATUS"] < 2 && $orderData["PRINT"] == 0);
                    return self::makeButton($orderData, 'edit', $canEdit);
                }
                if($control === "DirectSale"){
                    $canHandshake = ($orderData["ORDER_SUCCESS"] == 0 && $orderData["ORDER_STATUS"] == 0);
                    return self::makeButton($orderData, 'handshake', $canHandshake);
                }
                if($control === "SalesReturn"){
                    $canReturn = ($orderData["ORDER_SUCCESS"] == 1 && ($orderData["ORDER_STATUS"] == 4 || $orderData["ORDER_STATUS"] == 3));
                    return self::makeButton($orderData, 'returnProduct', $canReturn);
                }
                break;
            case 2:
                $canEdit = (self::$sessionData['USER_ID'] == $orderData['ORDER_BY'] && $orderData["ORDER_SUCCESS"] == 0 && $orderData["ORDER_STATUS"] < 2 && $orderData["PRINT"] == 0);
                return self::makeButton($orderData, 'edit', $canEdit);
            case 3:
                if($control === "ShippingConfirmation"){
                    $canEdit = ($orderData["ORDER_SUCCESS"] == 0 && $orderData["ORDER_STATUS"] < 2);
                    return self::makeButton($orderData, 'edit', $canEdit);
                }
                if($control === "SupportOrders" || $control === "SupportAfterSales"){
                    $canEdit = ($orderData["ORDER_SUCCESS"] == 0 && $orderData["ORDER_STATUS"] < 2);
                    return self::makeButton($orderData, 'edit', $canEdit);
                }
                break;
        }
        return self::makeButton($orderData, 'edit', false);
    }
    
    public static function getBtnBR($orderData) {
        $ptype = self::$requestData["ptype"] ?? 1;
        $control = self::$requestData['CONTROL'] ?? '';
        $ex = self::$requestData["ex"] ?? 0;
        
        switch($ptype){
            case 1:
                // Transformation
                if($control === "Orderlist" && $orderData["ORDER_STATUS"] < 3 && !$orderData["CUST_STAT"]){
                    if($orderData["ORDER_STATUS"] <=2 && isset(self::$permissionsCache["transformation"]) && self::$permissionsCache["transformation"]){
                        return self::makeButton($orderData, 'transformation', true);
                    }
                    return self::makeButton($orderData, 'transformation', false);
                }
                // Return
                if($orderData["ORDER_SUCCESS"] == 0 && $orderData["ORDER_STATUS"] >= 2 && $orderData["CUST_STAT"] && $orderData["RETURNS"] == 0 && $control === "Orderlist" && !$orderData["IS_EXCHANGE"]){
                    if($control === "Orderlist" && $orderData["ORDER_STATUS"] >= 2 && isset($ex) && $ex != 0){
                        return self::makeButton($orderData, 'return', true);
                    }
                    return self::makeButton($orderData, 'return', false);
                }
                // OrdersCustody Return
                if($control === "OrdersCustody"){
                    if($orderData["ORDER_STATUS"] == 2 && $orderData["ORDER_SUCCESS"] == 0 && $orderData["CUST_STAT"] === 'WAITING' && $orderData["RETURNS"] == 0 && !$orderData["IS_EXCHANGE"]){
                        return self::makeButton($orderData, 'return', true);
                    }
                    return self::makeButton($orderData, 'return', false);
                }
                // Support Transformation
                if($control === "SupportOrders" || $control === "SupportAfterSales"){
                    if($orderData["ORDER_STATUS"] < 2 ){
                        return self::makeButton($orderData, 'transformation', true);
                    }
                    return self::makeButton($orderData, 'transformation', false);
                }
                break;
            case 2:
                if(($orderData["ORDER_STATUS"] <= 1 && !$orderData["PRINT"]) || $orderData["ORDER_STATUS"] == 5){
                    return self::makeButton($orderData, 'service', true);
                }
                if($orderData["ORDER_SUCCESS"] == 1 && ($orderData["ORDER_STATUS"] == 3 || $orderData["ORDER_STATUS"] == 4) && !$orderData["IS_EXCHANGE"]){
                    return self::makeButton($orderData, 'refund', true);
                }
                return self::makeButton($orderData, 'service', false);
            case 3:
                if($control === "ShippingConfirmation" && $orderData['ORDER_STATUS'] < 2){
                    if($orderData["ORDER_SUCCESS"] == -1){
                        return self::makeButton($orderData, 'delete', true);
                    }
                    return self::makeButton($orderData, 'delete', false);
                }
                break;
        }
        return self::makeButton($orderData, 'service', false);
    }
    
    public static function getBtnBL($orderData) {
        $ptype = self::$requestData["ptype"] ?? 1;
        $control = self::$requestData['CONTROL'] ?? '';
        $ex = self::$requestData["ex"] ?? 0;
        
        switch($ptype){
            case 1:
                // Delete for ORDER_STATUS = 0
                if($control === "Orderlist" && $orderData["ORDER_STATUS"] == 0 && $ex > 0){
                    if($orderData["EXISTS_ID"] == 12){
                        return self::makeButton($orderData, 'delete', false);
                    }
                    if(isset(self::$permissionsCache["Delete_order"]) && self::$permissionsCache["Delete_order"]){
                        return self::makeButton($orderData, 'delete', true);
                    }
                    return self::makeButton($orderData, 'delete', false);
                }
                // Print for ORDER_STATUS = 1
                if($control === "Orderlist" && $orderData["ORDER_STATUS"] == 1 && $ex > 0){
                    if($orderData["ORDER_SUCCESS"] == -1){
                        if(isset(self::$permissionsCache["Delete_order"]) && self::$permissionsCache["Delete_order"]){
                            return self::makeButton($orderData, 'delete', true);
                        }
                        return self::makeButton($orderData, 'delete', false);
                    }
                    if(isset(self::$permissionsCache["Invoce_Print"]) && self::$permissionsCache["Invoce_Print"]){
                        return self::makeButton($orderData, 'print', true);
                    }
                    return self::makeButton($orderData, 'print', false);
                }
                // Reactivation for Support
                if($control === "SupportOrders" || $control === "SupportAfterSales"){
                    if($orderData["ORDER_SUCCESS"] == 0 && $orderData["ORDER_STATUS"] == 5 && $orderData["CUST_STAT"] == ""){
                        return self::makeButton($orderData, 'reactivation', true);
                    }
                    return self::makeButton($orderData, 'reactivation', false);
                }
                // Shipment for ORDER_STATUS = 2
                if($control === "Orderlist" && $orderData["ORDER_STATUS"] == 2 && $ex > 0 && $ex != 9){
                    if(isset(self::$permissionsCache["Shipment"]) && self::$permissionsCache["Shipment"]){
                        return self::makeButton($orderData, 'shipment', true);
                    }
                    return self::makeButton($orderData, 'shipment', false);
                }
                // Handshake for OrdersCustody
                if($control === "OrdersCustody" || ($control === "Orderlist" && $ex == 9)){
                    if($orderData["ORDER_SUCCESS"] < 1 && $orderData["RETURNS"] == 0 && isset(self::$permissionsCache["EditDelevered"]) && self::$permissionsCache["EditDelevered"]){
                        return self::makeButton($orderData, 'handshake', true);
                    }
                    return self::makeButton($orderData, 'handshake', false);
                }
                break;
            case 2:
                if($orderData["ORDER_STATUS"] < 2 && $orderData["ORDER_SUCCESS"] < 1 && !$orderData["CUST_STAT"] && !$orderData['PRINT']){
                    $btn = self::makeButton($orderData, 'delete', true);
                } else {
                    $btn = self::makeButton($orderData, 'delete', false);
                }
                
                if(self::$sessionData['USER_ID'] == $orderData['ORDER_BY'] && ($orderData["ORDER_SUCCESS"] == 0 || $orderData["ORDER_SUCCESS"] == 1) && !$orderData['IS_EXCHANGE'] && ($orderData["ORDER_STATUS"] == 2 || $orderData["ORDER_STATUS"] == 3 || $orderData["ORDER_STATUS"] == 4)){
                    return self::makeButton($orderData, 'newOrderExchange', true);
                }
                
                if($orderData["ORDER_SUCCESS"] == 1){
                    return self::makeButton($orderData, 'newOrderExchange', false);
                }
                return $btn;
            case 3:
                if($orderData['EXISTS_ID'] !== 6 && $orderData['EXISTS_ID'] !== 12){
                    if($orderData["ORDER_SUCCESS"] == 0 && $orderData["RETURNS"] == 0 && ($orderData['CUST_STAT'] == 'WAITING' || $orderData['CUST_STAT'] == 'DELIVERED')){
                        return self::makeButton($orderData, 'handshake', true);
                    }
                    return self::makeButton($orderData, 'handshake', false);
                }
                if($control === "ShippingConfirmation" && $orderData['ORDER_STATUS'] < 1){
                    return self::makeButton($orderData, 'shipConfirmation', true);
                }
                return self::makeButton($orderData, 'shipConfirmation', false);
        }
        return self::makeButton($orderData, 'handshake', false);
    }
    
    public static function getAllButtons($orderData) {
        return [
            self::getBtnTR($orderData),
            self::getBtnTL($orderData),
            self::getBtnBR($orderData),
            self::getBtnBL($orderData)
        ];
    }
}

/**
 * ordersList_BE - النسخة المحسنة
 */
class ordersList_BE {
    public  $rq_parm;
    private $ordersTbl      = 'ORDERS';
    private $oExistisTbl    = 'ORDER_EXISTS';
    private $oStatuseTbl    = 'ORDER_STATUSE';
    private $oTypeTbl       = 'ORDER_TYPE';
    private $usersTbl       = 'USERS';
    private $addressTbl     = 'ADDRESSES';
    private $clientTbl      = 'CLIENT';
    private $telephonTbl    = 'TELEPHONES';
    private $cityTbl        = 'CITY';
    private $govTbl         = 'GOV';
    private $zonesTbl       = 'ZONES';
    private $oProTbl        = 'ORDER_PRODUCT';
    private $proCodeTbl     = 'PRODUCT_CODES';
    private $prodTbl        = 'PRODUCTS';
    private $invoTbl        = 'invoices';
    private $oservTbl       = 'order_service';
    private $osertexTbl     = 'order_service_text';
    private $cusOrderTbl    = 'custody_orders';
    
    private $dtz;
    private $conn;
    
    // Cache for permissions
    private $cachedPermissions = null;
    private $cachedCustodyTotal = null;
    
    public function __construct($db) {
        $this->conn = $db;
        $this->dtz = new configDateTime();
    }
    
    // Cache helper methods
    private function getUserPermissions() {
        if ($this->cachedPermissions === null) {
            $us = unserialize($_SESSION['USER_SCRIPT']);
            if ($this->rq_parm["CONTROL"] == "Orderlist") {
                $this->cachedPermissions = $us['Admin']['orderlist_ex' . $this->rq_parm["ex"]] ?? false;
            } elseif ($this->rq_parm["CONTROL"] == "OrdersCustody") {
                $this->cachedPermissions = $us['Admin'][$this->rq_parm["CONTROL"]] ?? false;
            } else {
                $this->cachedPermissions = false;
            }
        }
        return $this->cachedPermissions;
    }
    
    private function getCustodyTotal() {
        if ($this->cachedCustodyTotal === null) {
            $this->cachedCustodyTotal = $this->custodyTotal();
        }
        return $this->cachedCustodyTotal;
    }
    
    private function listOrders_queries($fi, $us, $columns = '', $useLimit = true) {
        // شروط البحث
        $sqlWHEREpv = self::listOrders_Where($this->rq_parm, $fi, false, false, false);
        $sqlWHERE   = self::listOrders_Where($this->rq_parm, $fi);
        $sqlwex     = self::listOrders_Where($this->rq_parm, $fi, false);
    
        // الاستعلام الأساسي
        $sqlSELECT = "
            SELECT * FROM (
                SELECT
                    A.ORDER_ID,
                    A.ORDER_BY,
                    A.ORDER_TYPE_ID,
                    A.SUB_ORDER_ID,
                    A.EXISTS_ID,
                    X.DELIVERY_ID,
                    X.CUST_STAT,
                    X.RETURNS,
                    X.REFUND_STATUS,
                    X.ORDER_STATUS,
                    A.DATE_CREATED,
                    A.DELIVERY_DATE,
                    A.SHIPPING_DATE,
                    A.DELIVERED_DATE,
                    EXS.EXCHANGE AS IS_EXCHANGE,
                    A.TELEPHONE,
                    GREATEST(
                        COALESCE(A.LAST_UPDATE, '1900-01-02'),
                        COALESCE(EXO.LAST_UPDATE, '1900-01-02'),
                        COALESCE(OSE.OSTX_DATE,1900-01-02)
                    ) AS LAST_UPDATE,
                    A.SHIPPING_VAL,
                    A.STO_ID,
                    A.ORDER_SUCCESS,
                    CASE WHEN A.ORDER_SUCCESS = 0 THEN 'مفتوح' ELSE 'مغلق' END AS SUCCESS_STAT,
                    A.PRINT,
                    C.COLOR_CLASS,
                    A.EXCHANGE,
                    A.CITY_ID,
                    EXO.SUB_ORDER_ID AS EXCHANGE_SUPID,
                    CASE WHEN X.CUST_STAT = 'FREEZE' THEN 'استرجاع المنتجات' ELSE B.EXISTS_NAME END AS EXISTS_NAME,
                    CASE WHEN X.CUST_STAT = 'FREEZE' THEN 'ايقاف التسليم' ELSE C.STA_NAME END AS STA_NAME,
                    D.ORDER_TYPE_NAME,
                    CONCAT(E.FORENAME,' ',E.LASTNAME) AS ORDERBY,
                    CONCAT(F.FORENAME,' ',F.LASTNAME) AS DELIVERYBY,
                    A.TEL_ID,
                    A.ADDRESS,
                    G.DEFAULTADD,
                    G.ADDRESS_TITLE,
                    H.CLIENT_EMAIL,
                    H.CLIENT_NAME,
                    H.CLIENT_ID,
                    L.SHIPPING_TIME_MAX,
                    L.ZONE_NAME,
                    L.ZONE_ID,
                    L.SHIPPING_TYPE,
                    L.SHIPPING_RATE,
                    L.SHIPPING_LOWEST,
                    K.CI_NAME,
                    A.ADDRESS_ID,
                    k.CI_ID,
                    k.GOV_ID,
                    R.COUNTRY_ID,
                    R.GOV_NAME,
                    
                    OSE.OS_ID,
                    OSE.OS_STATUSE,
                    OSE.COUNT_END_OS AS OS_END_COUNT,
                    OSE.CMSG AS MSG_COUNT,
                    CASE WHEN OSE.OS_STATUSE = 0 THEN 1 ELSE 0 END AS OSMsgCS,
                    
                    P.INVO_ID,
                    COUNT(M.ORDER_ID) AS PRODUCTS_COUNT,
                    SUM(M.QTY) AS PRODUCTS_QTY,
                    SUM((M.PRICE - M.DISCOUNT) * M.QTY) AS PRICE,
                    CASE
				        WHEN NOT EXISTS (
				            SELECT 1
				            FROM order_product op
				            LEFT JOIN stores_product sp ON sp.PRO_CODE = op.PRO_CODE AND sp.STO_ID = A.STO_ID
				            WHERE op.ORDER_ID = A.ORDER_ID
				              AND IFNULL(sp.STO_QTY,0)  < op.QTY
				        )
				        THEN 1
				        ELSE 0
				    END AS STORE_IS_AVAILABLE,
                    JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'PRODUCT_CODE', POV.PRO_CODE,
                            'PRODUCT_NAME', POV.PRO_NAME,
                            'PRODUCT_ATTRIBUTES', IFNULL(POV.OPT_JSON,''),
                            'ORDER_QTY', FORMAT(M.QTY,2),
                            'RETURNED_QTY', FORMAT(IF(A.ORDER_STATUS < 3,0,M.QTY - M.DELIVERED_QTY),2),
                            'DELIVERED_QTY', FORMAT(M.DELIVERED_QTY,2),
                            'PRICE', FORMAT(M.PRICE,2),
                            'DISCOUNT', FORMAT(M.DISCOUNT,2),
                            'REQUIRED_PAYMENT', FORMAT(M.QTY*(M.PRICE-M.DISCOUNT),2)
                        )
                    ) AS PRODUCTS_DATA
                
                FROM (
                    SELECT 
                        ORDER_ID,
                        USER_ID AS DELIVERY_ID,
                        ORDER_STATUSE AS ORDER_STATUS,
                        0 AS REFUND_STATUS,
                        SHIPPING_ADDRESS AS ADDRESS,
                        CUST_STAT,
                        RETURNS
                    FROM custody_orders
                    WHERE CUST_STAT='FREEZE'
                    ".$sqlWHEREpv[0]."
                    UNION ALL
                    SELECT 
                        A.ORDER_ID,
                        COALESCE(B.USER_ID, A.DELIVERY_ID) AS DELIVERY_ID,
                        A.ORDER_STATUS,
                        CRO.ORDER_STATUSE AS REFUND_STATUS,
                        A.ADDRESS,
                        COALESCE(B.CUST_STAT,'') AS CUST_STAT,
                        CASE WHEN CRO.RETURNS IS NOT NULL AND B.ORDER_STATUSE = 3 THEN CRO.RETURNS
                             WHEN CRO.RETURNS IS NOT NULL AND B.ORDER_STATUSE > 3 THEN B.RETURNS
                             WHEN CRO.RETURNS IS NULL THEN COALESCE(B.RETURNS,0)
                             ELSE 0
                        END AS RETURNS
                    FROM ORDERS A
                    LEFT JOIN custody_orders B ON A.ORDER_ID = B.ORDER_ID AND CUST_STAT<>'FREEZE'
                    LEFT JOIN custody_return_orders CRO ON A.EXCHANGE = CRO.ORDER_ID 
                    ".$sqlWHEREpv[1]."
                ) X
                LEFT JOIN ORDERS           A ON X.ORDER_ID = A.ORDER_ID
                LEFT JOIN ORDER_EXISTS     B ON A.EXISTS_ID = B.EXISTS_ID
                LEFT JOIN ORDER_STATUSE    C ON A.ORDER_STATUS = C.STA_ID
                LEFT JOIN ORDER_TYPE       D ON A.ORDER_TYPE_ID = D.ORDER_TYPE_ID
                LEFT JOIN USERS            E ON A.ORDER_BY = E.USER_ID
                LEFT JOIN USERS            F ON A.DELIVERY_ID = F.USER_ID
                LEFT JOIN ADDRESSES        G ON A.ADDRESS_ID = G.ADDRESS_ID
                LEFT JOIN CLIENT           H ON G.CLIENT_ID = H.CLIENT_ID
                LEFT JOIN CITY             K ON G.CITY_ID = K.CI_ID
                LEFT JOIN GOV              R ON K.GOV_ID = R.GOV_ID
                LEFT JOIN ZONES            L ON K.ZONE_ID = L.ZONE_ID
                LEFT JOIN ORDER_PRODUCT    M ON A.ORDER_ID = M.ORDER_ID
                LEFT JOIN invoices         P ON A.ORDER_ID = P.REFERENCE_ID AND REFERENCE_TYPE='SALES'
                LEFT JOIN ORDERS           EXO ON A.EXCHANGE = EXO.ORDER_ID
                LEFT JOIN PRODUCT_OPTIONS_VIEW POV ON M.PRO_CODE = POV.PRO_CODE
                LEFT JOIN (
                    SELECT  
                        O.ORDER_ID,
                        COALESCE(MAX(OS.OS_ID),0) AS OS_ID,
                        COALESCE(MAX(OSTX_READ_DATE),'2000-01-01 00:00:00') AS OSTX_READ_DATE,
                        COALESCE(MAX(OST.OSTX_DATE),'2000-01-01 00:00:00') AS OSTX_DATE,
                        COALESCE(MAX(OSA.OS_STATUSE),MAX(OSB.OS_STATUSE),1) AS OS_STATUSE,
                        COUNT(DISTINCT OSB.OS_ID) AS COUNT_END_OS,
                        COUNT(DISTINCT CASE WHEN OST.OSTX_READ = 0 AND OST.USER_ID <> ".(isset($_SESSION['USER_ID'])? $_SESSION['USER_ID']: 0) ." THEN OST.OSTX_ID END) AS CMSG
                    FROM orders O
                    LEFT JOIN order_service OS  ON O.ORDER_ID = OS.ORDER_ID
                    LEFT JOIN order_service OSA ON O.ORDER_ID = OSA.ORDER_ID AND OSA.OS_STATUSE = 0
                    LEFT JOIN order_service OSB ON O.ORDER_ID = OSB.ORDER_ID AND OSB.OS_STATUSE = 1
                    LEFT JOIN order_service_text OST ON OSA.OS_ID = OST.OS_ID
                    GROUP BY O.ORDER_ID
                ) OSE ON X.ORDER_ID = OSE.ORDER_ID
                LEFT JOIN (SELECT EXCHANGE FROM ORDERS WHERE EXCHANGE IS NOT NULL) EXS ON EXS.EXCHANGE=X.ORDER_ID
                GROUP BY X.ORDER_ID, X.DELIVERY_ID, X.ORDER_STATUS, X.ADDRESS, X.CUST_STAT, X.RETURNS
            ) ORDERS
        ";
    
        $last_update = isset($this->rq_parm['last_update']) ? $this->rq_parm['last_update'] : '0000-01-01';
    
        // إضافة LIMIT و OFFSET اختيارياً
        $sqlLimitOffset = '';
        if ($useLimit && isset($this->rq_parm['start']) && isset($this->rq_parm['length'])) {
            $sqlLimitOffset = " LIMIT ".intval($this->rq_parm['start']).", ".intval($this->rq_parm['length']);
        }
    
        $Query_result = $sqlSELECT . $sqlWHERE . ' ORDER BY ORDER_ID ASC' . $sqlLimitOffset;
        $Query_odata  = $sqlSELECT . $sqlwex;
        $Query_total  = "SELECT  count(ORDER_ID) AS ordersCount FROM (
                SELECT A.ORDER_ID,
                    A.SUB_ORDER_ID,
                    A.ORDER_TYPE_ID,
                    CO.USER_ID AS DELIVERY_ID,
                    A.ORDER_BY,
                    A.TELEPHONE,
                    A.ADDRESS,
                    A.STO_ID,
                    H.CLIENT_NAME,
                    H.CLIENT_EMAIL,
                    A.ORDER_SUCCESS,
                    A.EXISTS_ID,
                    A.ORDER_STATUS,
                    Z.SHIPPING_TYPE,
                    Z.ZONE_ID,
                    EXS.EXCHANGE AS IS_EXCHANGE,
                    COALESCE(co.RETURNS,1) AS RETURNS,
                    COALESCE(CO.CUST_STAT,'') AS CUST_STAT,
                    COALESCE(B.OS_STATUSE,1) AS OS_STATUSE,
                    COUNT(DISTINCT CASE WHEN c.OSTX_READ = 0 THEN OSTX_ID END) AS MSG_COUNT,
                    GREATEST(
                        COALESCE(A.LAST_UPDATE,'0000-01-01'),
                        COALESCE(C.OSTX_READ_DATE,'0000-01-01'),
                        COALESCE(C.OSTX_DATE,'0000-01-01'),
                        COALESCE(EXO.LAST_UPDATE,'0000-01-01')
                    ) AS LAST_UPDATE
                FROM orders A
                LEFT JOIN orders EXO ON A.EXCHANGE=EXO.ORDER_ID
                LEFT JOIN order_service B ON A.ORDER_ID=B.ORDER_ID AND OS_STATUSE = 0
                LEFT JOIN order_service_text C ON B.OS_ID=C.OS_ID
                LEFT JOIN custody_orders CO ON CO.ORDER_ID = A.ORDER_ID
                LEFT JOIN USERS E ON A.ORDER_BY = E.USER_ID
                LEFT JOIN USERS F ON A.DELIVERY_ID = F.USER_ID
                LEFT JOIN ADDRESSES G ON A.ADDRESS_ID = G.ADDRESS_ID
                LEFT JOIN CLIENT H ON G.CLIENT_ID = H.CLIENT_ID
                LEFT JOIN city CI ON G.CITY_ID = CI.CI_ID
                LEFT JOIN ZONES Z ON CI.ZONE_ID = Z.ZONE_ID
                LEFT JOIN (SELECT EXCHANGE FROM ORDERS WHERE EXCHANGE IS NOT NULL) EXS ON EXS.EXCHANGE=A.ORDER_ID
                GROUP BY A.ORDER_ID
            ) AS OTBL" . $sqlWHERE;
        $Query_lastup = "
            SELECT DISTINCT ORDER_ID FROM (
                SELECT A.ORDER_ID,
                    GREATEST(
                        COALESCE(A.LAST_UPDATE,'0000-01-01'),
                        COALESCE(C.OSTX_READ_DATE,'0000-01-01'),
                        COALESCE(C.OSTX_DATE,'0000-01-01'),
                        COALESCE(EXO.LAST_UPDATE,'0000-01-01')
                    ) AS LAST_UPDATE
                FROM orders A
                LEFT JOIN orders EXO ON A.EXCHANGE=EXO.ORDER_ID
                LEFT JOIN order_service B ON A.ORDER_ID=B.ORDER_ID
                LEFT JOIN order_service_text C ON B.OS_ID=C.OS_ID
            ) AS OTBL
            WHERE LAST_UPDATE >= '".$last_update."'
        ";
    
        return [
            "result" => $Query_result,
            "total"  => $Query_total,
            "odata"  => $Query_odata,
            "lupdate" => $Query_lastup,
            "QueryData" => $sqlSELECT
        ];
    }
    
    private function custodyTotal() {
        $userDeliveryID = '';
        $wh = '';
        
        if(isset($this->rq_parm['CONTROL']) && $this->rq_parm['CONTROL'] === 'OrdersCustody'){
            if(isset($this->rq_parm['did']) && $this->rq_parm['did']>0){
                $userDeliveryID = $this->rq_parm['did'];
                $wh = ' WHERE CU.USER_ID = :delid ';
            }
        } else if(isset($this->rq_parm['CONTROL']) && $this->rq_parm['CONTROL'] === 'ListOrdersDelivery'){
            $userDeliveryID = $_SESSION['USER_ID'];
            $wh = ' WHERE CU.USER_ID = :delid AND CU.RETURNS =0';
        }
        
        $sqlQuery = ' SELECT 
                          Count(CU.ORDER_ID) AS CUT,
                          Sum(CASE WHEN CU.ORDER_STATUSE = 2 AND CU.CUST_STAT <> "FREEZE" THEN 1 ELSE 0 END)  AS CU2,
                          Sum(CASE WHEN (CU.ORDER_STATUSE IN (4,5) AND CU.RETURNS = 0) OR (CU.CUST_STAT = "FREEZE" AND CU.RETURNS = 0) THEN 1 ELSE 0 END)   AS CU4_5,
                          Sum(CP.RETURNPRO) AS REFQTY
                        FROM   custody_orders AS CU
                          LEFT JOIN orders AS O ON O.ORDER_ID = CU.ORDER_ID
                          LEFT JOIN (
                            SELECT  CP.ORDER_ID, Sum(CP.QTY - CP.DELIVERED_QTY) AS RETURNPRO
                            FROM   custody_product AS CP
                             LEFT JOIN custody_orders AS CO ON CO.ORDER_ID = CP.ORDER_ID
                            WHERE PRO_STATUSE IN( "partial","rejected" ) AND CO.RETURNS = 0
                            GROUP BY ORDER_ID
                          ) AS CP ON CP.ORDER_ID = CU.ORDER_ID ';
        
        $stCustTotal = $this->conn->prepare($sqlQuery . $wh);
        if($userDeliveryID !== ''){
            $userDeliveryID = htmlspecialchars(strip_tags(trim($userDeliveryID)));
            $stCustTotal->bindValue(':delid', $userDeliveryID, PDO::PARAM_INT);
        }
        $stCustTotal->execute();
        $returnData = array();
        while ($record = $stCustTotal->fetch(PDO::FETCH_ASSOC)) {
            $returnData['cuTotal']  = $record['CUT'] !== NULL ? $record['CUT'] : 0;
            $returnData['cuAshipp'] = $record['CU2'] !== NULL ? $record['CU2'] : 0;
            $returnData['cuBshipp'] = $record['CU4_5'] !== NULL ? $record['CU4_5'] : 0;
            $returnData['cuRefQty'] = $record['REFQTY'] !== NULL ? $record['REFQTY'] : 0;
        }
        return $returnData;
    }
    
    private function listOrders_limit(&$request){
        $limit = '';
        if (isset($request['start']) && isset($request['length'])) {
            $start  = (int) $request['start'];
            $length = (int) $request['length'];
            if ($length > -1) {
                $limit = " LIMIT {$start}, {$length}";
            }
        }
        return $limit;
    }
    
    private function listOrders_orderby(&$request) {
        $order = '';
        if(isset($request['order'])){
            $order = ' ORDER BY ORDER_ID ' . strtoupper($request['order'][0]['dir']);
        }
        return $order;
    }
    
    private function listOrders_Where(&$request, $fi, $bool=TRUE, $sherch = TRUE, $str = true) {
        $wq1 = array();
        $wq2 = array();
        $globalSearch = array();
        $globalwhere  = array();
        $globalhaving = array();
        $filterwhere  = array();
        $where = '';
        
        if(isset($request["last_update"])){
            $globalwhere[] = "LAST_UPDATE > '".$request["last_update"]."'";
            $wq1[] = "LAST_UPDATE > '".$request["last_update"]."'";
            $wq2[] = "(A.LAST_UPDATE > '".$request['last_update']."' OR B.LAST_UPDATE > '".$request["last_update"]."')";
        }
        
        //====================== Global Where ==========================
        switch ($request["ptype"]) {
            case 1: //منطقة الاداريين
                $globalwhere[] = 'STO_ID = (SELECT STO_ID FROM stores WHERE STTY_ID = 2 AND BRANCH_ID ='. $_SESSION['BRANCH_ID'].')';
                if($request["CONTROL"] == "Orderlist"){
                    if(isset($request["ex"]) && $request["ex"] > 0){
                        $globalwhere[] = 'ORDER_SUCCESS <=0';
                        $globalwhere[] = 'EXISTS_ID = '. $request["ex"] ;
                        $globalhaving[] = '(CASE WHEN CUST_STAT = "FREEZE" THEN 1 ELSE 0 END) = 0';
                        $wq2[] = 'A.ORDER_SUCCESS <=0 AND A.EXISTS_ID = '. $request["ex"] ;
                        if(isset($request["sto_available"]) && $request["sto_available"]){
                        	$globalwhere[] = "STORE_IS_AVAILABLE >0";
                        }
                    }
                } else if($request['CONTROL'] == "OrdersCustody"){
                    if(isset($request['did']) && $request['did']>0){
                        $globalwhere[] = 'DELIVERY_ID = '.$request['did'];
                        $WQ1 = 'USER_ID = '.$request['did'];
                        $WQ2 = 'A.DELIVERY_ID = '.$request['did'];
                    }
                    if(isset($request['cofi'])){
                        if($request['cofi'] === "OLUnderSettlement"){
                            $globalwhere[] = '(CUST_STAT IN("FREEZE","DELIVERED") AND ORDER_STATUS <> 3 AND RETURNS = 0)';
                        } else if($request['cofi'] === "OLDelivered"){
                            $globalwhere[] = '(CUST_STAT ="DELIVERED" AND ORDER_STATUS = 3 AND ORDER_SUCCESS = 0)';
                        } else if($request['cofi'] === "OLUnderDelivery"){
                            $globalwhere[] = 'CUST_STAT ="WAITING" ';
                        } else {
                            $globalwhere[] = 'ORDER_SUCCESS = 0 AND CUST_STAT IN("FREEZE","DELIVERED","WAITING")';
                        }
                    } else if(empty($request['cofi'])){
                        $globalwhere[] = 'ORDER_SUCCESS = 0 AND CUST_STAT IN("FREEZE","DELIVERED","WAITING")';
                    }
                } else if($request['CONTROL'] == "DirectSale"){
                    $globalwhere[] = "EXISTS_ID = 11";
                } else if($request['CONTROL'] == "SupportAfterSales"){
                    $globalwhere[] = "((ORDER_STATUS = 3 AND ORDER_SUCCESS = 1) OR (ORDER_STATUS = 4 AND ORDER_SUCCESS = 1) OR (ORDER_STATUS = 5 AND RETURNS = 1))";
                } else if($request['CONTROL'] == "SupportOrders"){
                    $globalwhere[] = "(OS_STATUSE = 0)";
                } else if($request['CONTROL'] == "TrackShipmentConfirmation"){
                    $globalwhere[] = "(EXISTS_ID = 12)";
                } else if($request['CONTROL'] == "SalesReturn"){
                    $globalwhere[] = "ORDER_STATUS IN(3,4)";
                }
                break;
            case 3: //منطقة مندوبين الشحن
                switch ($request["CONTROL"]) {
                    case "ListOrdersDelivery":
                        $globalwhere[] = 'DELIVERY_ID = ' . $_SESSION['USER_ID'] . ' AND CUST_STAT = "WAITING"';
                        break;
                    case "ListOrdersDelivered":
                        $globalwhere[] = 'DELIVERY_ID = ' . $_SESSION['USER_ID'] . ' AND CUST_STAT IN("FREEZE","DELIVERED")';
                        break;
                    case "ShippingConfirmation":
                        if(isset($_POST['cofi']) && $_POST['cofi'] === "waitingResponse"){
                            $globalwhere[] = '(ORDER_STATUS = 0 AND EXISTS_ID = 6 AND ORDER_ID IN(SELECT ORDER_ID FROM crm_order_logs WHERE employee_type = "delivery" AND ACTION_TYPE IN("SMS","WHATSAPP") ) AND  ZONE_ID IN(SELECT ZONE_ID from users_delivery WHERE USER_ID = '.$_SESSION['USER_ID'].'))';
                        } else if(isset($_POST['cofi']) && $_POST['cofi'] === "finished"){
                            $globalwhere[] = '(ORDER_STATUS = 0 AND EXISTS_ID = 12 AND  ZONE_ID IN(SELECT ZONE_ID from users_delivery WHERE USER_ID = '.$_SESSION['USER_ID'].'))';
                        } else {
                            $globalwhere[] = '(ORDER_STATUS = 0 AND EXISTS_ID = 6 AND  ZONE_ID IN(SELECT ZONE_ID from users_delivery WHERE USER_ID = '.$_SESSION['USER_ID'].'))';
                        }
                        break;
                }
                break;
            case 2:
                switch ($request["CONTROL"]) {
                    case "ListOrdersMarketer":
                        $globalwhere[] = 'ORDER_BY = ' . $_SESSION['USER_ID'] .' AND ORDER_TYPE_ID = 2 AND ORDER_SUCCESS >= 0 ';
                        $globalwhere[] = '(CASE WHEN CUST_STAT = "FREEZE" THEN 1 ELSE 0 END) = 0';
                        $wq2[] = 'A.ORDER_BY = ' . $_SESSION['USER_ID'] .' AND A.ORDER_TYPE_ID = 2 AND A.ORDER_SUCCESS >= 0 ';
                        break;
                    case "ClientOrderlist":
                        $globalwhere[] = 'CUST_STAT <> "FREEZE"';
                        break;
                }
                break;
            default: //منطقة منشئى اوامر البيع
                $globalwhere[]  = 'ORDER_BY = ' . $_SESSION['USER_ID'] .' AND ORDER_TYPE_ID = '.$request["ptype"].' AND ORDER_SUCCESS >= 0 ';
                $globalhaving[] = '(CASE WHEN CUST_STAT = "FREEZE" THEN 1 ELSE 0 END) = 0';
                $wq[]  = 'A.ORDER_BY = ' . $_SESSION['USER_ID'] .' AND A.ORDER_TYPE_ID = '.$request["ptype"].' AND A.ORDER_SUCCESS >= 0 ';
        }
        
        //====================== Filter Where ==========================
        if (!empty($request["ptype"])) {
            if (!empty($fi["orderType"]['id'])) {
                $filterwhere[] = 'ORDER_TYPE_ID = ' . $fi["orderType"]['id'];
            }
            if (!empty($fi["shippingType"]['id'])) {
                $filterwhere[] = 'SHIPPING_TYPE = ' . $fi["shippingType"]['id'];
            }
            if (!empty($fi["zones"]['id'])) {
                $filterwhere[] = 'ZONE_ID = ' . $fi["zones"]['id'];
            }
            if (!empty($fi["products"]['id'])) {
                $filterwhere[] = 'ORDER_ID IN (SELECT ORDER_ID FROM ORDER_PRODUCT AS OP LEFT JOIN PRODUCT_CODES AS PC ON OP.PRO_CODE = PC.PRO_CODE WHERE PC.PRODUCT_ID IN  ('. implode(',', $fi["products"]['id']) . ') GROUP BY ORDER_ID) ';
            }
            
            if(!empty($request['columns'][0]['search']['value'])){
                $globalSearch[] = 'SUB_ORDER_ID LIKE "%'.$request['columns'][0]['search']['value'].'%" ';
            }
        
            if(!empty($request['columns'][1]['search']['value'])){
              	$valarr = $request['columns'][1]['search']['value'];
              	
              	if(is_string($valarr) && $valarr){
              		$globalSearch[] = '(STA_NAME LIKE "%'.$valarr.'%" 
                                  or EXISTS_NAME LIKE "%'.$valarr.'%" )';
              	}    
              	if(is_array($valarr) && $valarr[0]){
              		$colindex = $valarr[1];
              		$valStr = $valarr[0];
	              	if($colindex == 1){
	              		$globalSearch[] = '(STA_ID = '.$valStr.')';
	              	}else{
	              		$globalSearch[] = '(EXISTS_ID = '.$valStr.')';
	              	}
              	}
            }
            
            if(!empty($request['columns'][2]['search']['value'])){
                $globalSearch[] = '(CLIENT_NAME LIKE "%'.$request['columns'][2]['search']['value'].'%" 
                                  OR TELEPHONE LIKE "%'.$request['columns'][2]['search']['value'].'%")';
            }
           
            if (!empty($request["dateFilter"]['column']) && 
                !empty($request["dateFilter"]['start']) && 
                !empty($request["dateFilter"]['end'])) {
                $column = trim($request["dateFilter"]['column']);
                $start  = trim($request["dateFilter"]['start']).' 00:00:00.000';
                $end    = trim($request["dateFilter"]['end']).' 23:59:59.999';  
                $filterwhere[] = $column . ' BETWEEN "' . $start . '" AND "' . $end . '" ';
            }
        }
        
        if (!empty($request["ptype"])) {
            if (!empty($fi["zones"]['id'])) {
                $filterwhere[] = 'ZONE_ID = ' . $fi["zones"]['id'];
            }
        }
        
        //====================== Global Search ==========================
        if (isset($request['search']) && $bool && $sherch && $request['search']['value'] != '') {
            $str = $request['search']['value'];
            $globalSearch[] = 'SUB_ORDER_ID LIKE "%'.$str.'%" ';
            $globalSearch[] = 'ORDERBY LIKE "%'.$str.'%" ';
            $globalSearch[] = 'DELIVERYBY LIKE "%'.$str.'%" ';
            $globalSearch[] = 'TELEPHONE LIKE "%'.$str.'%" ';
            $globalSearch[] = 'ADDRESS LIKE "%'.$str.'%" ';
            $globalSearch[] = 'CLIENT_NAME LIKE "%'.$str.'%" ';
            $globalSearch[] = 'CLIENT_EMAIL LIKE "%'.$str.'%" ';
        }
        
        $where = '';
        $having = '';
        
        if (count($globalwhere)) {
            $where = ' ' . implode(' AND ', $globalwhere);
        }
        if (count($filterwhere)) {
            $where = $where === '' ?
                ' ' . implode(' AND ', $filterwhere) :
                $where . ' AND ' . implode(' AND ', $filterwhere);
        }
        
        if(count($globalhaving)){
            $having = '(' . implode(' AND ', $globalhaving) . ')';
        }
        
        if (count($globalSearch)) {
            $having = $having === '' ?
                '(' . implode(' OR ', $globalSearch) . ')' :
                $having . ' AND (' . implode(' OR ', $globalSearch) . ')';
        }
        
        if ($where !== '') {
            $where = ' WHERE ' . $where;
        }
        if($having !== ''){
            $having = ' AND ' . $having;
        }
        if(count($wq2)){
            $wq2 = ' WHERE (' . implode(' AND ', $wq2) . ')';
        } else {
            $wq2 = '';
        }
        if(count($wq1)){
            $wq1 = ' AND (' . implode(' AND ', $wq1) . ')';
        } else {
            $wq1 = '';
        }
        
        if($str){
            return $where . $having;
        } else {
            return array($wq1, $wq2);
        }
    }
    
    /**
     * listOrders_btns - استخدام ButtonEngine المحسن
     */
    private function listOrders_btns(&$request, &$orderData, &$User_permissions){
        // تهيئة ButtonEngine
        ButtonEngine::init($request, $User_permissions);
        
        // الحصول على الأزرار الأربعة دفعة واحدة
        $buttons = ButtonEngine::getAllButtons($orderData);
        
        // استخراج البيانات الإضافية
        $msgnotification = "";
        if($orderData['MSG_COUNT'] > 0){
            $msgnotification = $orderData['MSG_COUNT'];
        } else if($orderData['OS_ID'] > 0 && $orderData['OS_STATUSE'] == 0 && $orderData['MSG_COUNT'] == 0){
            $msgnotification = '<i class="fa-regular fa-envelope"></i>';
        }
        
        $adminsupport = '';
        if($this->rq_parm["ptype"] == 1){ 
            $adminsupport = ' <td class="bg-white " colspan="2" rowspan="2">
                                    <a id="AdminSupport" data-id="'.$orderData["ORDER_ID"].'" class="btn btn-app-tools btn-light badge-ya text-5 bg-gradient mt-2" style="margin-left:-5px;margin-right:-5px;width:50px;">
                                        <span class="badge bg-danger">'.$msgnotification.'</span>
                                        <i class="fas fa-headset"></i>الدعم
                                    </a>
                                </td>';
        }
        
        $orderby = $orderData["ORDER_TYPE_NAME"].' : '.$orderData['ORDERBY'];
        if(isset($orderData["DELIVERY_ID"])){
            $orderby = 'المندوب : '.$orderData['DELIVERYBY'];
        }
        
        $shipdatetext = "";
        
        return array($buttons, $orderby, $shipdatetext);
    }
    
    public function listOrders_dt_server_side(){
	    try{
	        $sqlLIMIT = "";
	        $sqlSEARCH = "";
	        $us = unserialize($_SESSION['USER_SCRIPT']);
	        $fi = !empty($this->rq_parm['url']) ? json_decode($_COOKIE['filt_'. str_replace("*","_",$this->rq_parm['url'])], true) : '';
	        
	        $sqlWHEREpv = self::listOrders_Where($this->rq_parm, $fi, false, false, false);
	        $sqlWHERE   = self::listOrders_Where($this->rq_parm, $fi, true, true, true);
	        $sqlwex     = self::listOrders_Where($this->rq_parm, $fi, false, true, true);
	        $sqlORDERBY = isset($this->rq_parm['columns']) ? self::listOrders_orderby($this->rq_parm, $this->rq_parm['columns']) : '';
	        $sqlLIMIT   = isset($this->rq_parm['columns']) ? self::listOrders_limit($this->rq_parm, $this->rq_parm['columns']) : '';
	        $exlup = isset($_POST) ? "COALESCE(EXO.LAST_UPDATE, '1900-01-02')," : "";        
	        $existsid = (isset($_POST['ex']) && $_POST['ex']) ? $_POST['ex'] :0;
	        $sqlSELECT = "
	                SELECT
	                    A.ORDER_ID,
	                    A.ORDER_BY,
	                    A.ORDER_TYPE_ID,
	                    A.SUB_ORDER_ID,
	                    A.EXISTS_ID,
	                    X.DELIVERY_ID,
	                    X.CUST_STAT,
	                    X.RETURNS,
	                    X.REFUND_STATUS,
	                    X.ORDER_STATUS,
	                    A.DATE_CREATED,
	                    A.DELIVERY_DATE,
	                    A.SHIPPING_DATE,
	                    A.DELIVERED_DATE,
	                    EXS.EXCHANGE AS IS_EXCHANGE,
	                    A.TELEPHONE,
	                    GREATEST(
	                        COALESCE(A.LAST_UPDATE, '1900-01-01 00:00:00'),
	                        COALESCE(EXO.LAST_UPDATE, '1900-01-01 00:00:00'),
	                        COALESCE(OSE.OSTX_DATE, '1900-01-01 00:00:00')
	                    ) AS LAST_UPDATE,
	                    A.SHIPPING_VAL,
	                    A.STO_ID,
	                    A.ORDER_SUCCESS,
	                    CASE WHEN A.ORDER_SUCCESS = 0 THEN 'مفتوح' ELSE 'مغلق' END AS SUCCESS_STAT,
	                    A.PRINT,
	                    C.COLOR_CLASS,
	                    C.STA_ID,
	                    A.EXCHANGE,
	                    A.CITY_ID,
	                    EXO.SUB_ORDER_ID AS EXCHANGE_SUPID,
	                    CASE WHEN X.CUST_STAT = 'FREEZE' THEN 'استرجاع المنتجات' ELSE B.EXISTS_NAME END AS EXISTS_NAME,
	                    CASE WHEN X.CUST_STAT = 'FREEZE' THEN 'ايقاف التسليم' ELSE C.STA_NAME END AS STA_NAME,
	                    D.ORDER_TYPE_NAME,
	                    CONCAT(E.FORENAME,' ',E.LASTNAME) AS ORDERBY,
	                    CONCAT(F.FORENAME,' ',F.LASTNAME) AS DELIVERYBY,
	                    A.TEL_ID,
	                    A.ADDRESS,
	                    G.DEFAULTADD,
	                    G.ADDRESS_TITLE,
	                    H.CLIENT_EMAIL,
	                    H.CLIENT_NAME,
	                    H.CLIENT_ID,
	                    L.SHIPPING_TIME_MAX,
	                    L.ZONE_NAME,
	                    L.ZONE_ID,
	                    L.SHIPPING_TYPE,
	                    L.SHIPPING_RATE,
	                    L.SHIPPING_LOWEST,
	                    K.CI_NAME,
	                    A.ADDRESS_ID,
	                    k.CI_ID,
	                    k.GOV_ID,
	                    R.COUNTRY_ID,
	                    R.GOV_NAME,
	                    OSE.OS_ID,
	                    OSE.OS_STATUSE,
	                    OSE.COUNT_END_OS AS OS_END_COUNT,
	                    OSE.CMSG AS MSG_COUNT,
	                    CASE WHEN OSE.OS_STATUSE = 0 THEN 1 ELSE 0 END AS OSMsgCS,
	                    P.INVO_ID,
	                    COUNT(M.ORDER_ID) AS PRODUCTS_COUNT,
	                    SUM(M.QTY) AS PRODUCTS_QTY,
	                    SUM((M.PRICE - M.DISCOUNT) * M.QTY) AS PRICE,
	                    CASE
					        WHEN ".$existsid." <> 0 AND NOT EXISTS (
					            SELECT 1
					            FROM order_product op
					            LEFT JOIN stores_product sp ON sp.PRO_CODE = op.PRO_CODE AND sp.STO_ID = A.STO_ID
					            LEFT JOIN (
					                SELECT o2.STO_ID, op2.PRO_CODE, SUM(op2.QTY) AS RESERVED_QTY
					                FROM orders o2
					                INNER JOIN order_exists oe ON oe.EXISTS_ID = o2.EXISTS_ID
					                INNER JOIN order_product op2 ON op2.ORDER_ID = o2.ORDER_ID
					                WHERE oe.ADMIN_STORE = 1
					                GROUP BY o2.STO_ID, op2.PRO_CODE
					            ) r ON r.STO_ID = A.STO_ID AND r.PRO_CODE = op.PRO_CODE
					            WHERE op.ORDER_ID = A.ORDER_ID
					              AND (IFNULL(sp.STO_QTY,0) - IFNULL(r.RESERVED_QTY,0)) < op.QTY
					        )
					        THEN 1
					        ELSE 0
					    END AS STORE_IS_AVAILABLE,
	                    JSON_ARRAYAGG(
	                        JSON_OBJECT(
	                            'PRODUCT_CODE', POV.PRO_CODE,
	                            'PRODUCT_NAME', POV.PRO_NAME,
	                            'PRODUCT_ATTRIBUTES', IFNULL(POV.OPT_JSON,''),
	                            'ORDER_QTY', FORMAT(M.QTY,2),
	                            'RETURNED_QTY', FORMAT(IF(A.ORDER_STATUS < 3,0,M.QTY - M.DELIVERED_QTY),2),
	                            'DELIVERED_QTY', FORMAT(M.DELIVERED_QTY,2),
	                            'PRICE', FORMAT(M.PRICE,2),
	                            'DISCOUNT', FORMAT(M.DISCOUNT,2),
	                            'REQUIRED_PAYMENT', FORMAT(M.QTY*(M.PRICE-M.DISCOUNT),2)
	                        )
	                    ) AS PRODUCTS_DATA
	                FROM (
	                    SELECT 
	                        ORDER_ID,
	                        USER_ID AS DELIVERY_ID,
	                        ORDER_STATUSE AS ORDER_STATUS,
	                        0 AS REFUND_STATUS,
	                        SHIPPING_ADDRESS AS ADDRESS,
	                        CUST_STAT,
	                        RETURNS
	                    FROM custody_orders
	                    WHERE CUST_STAT='FREEZE'
	                    ".$sqlWHEREpv[0]."
	                    UNION ALL
	                    SELECT 
	                        A.ORDER_ID,
	                        COALESCE(B.USER_ID, A.DELIVERY_ID) AS DELIVERY_ID,
	                        A.ORDER_STATUS,
	                        CRO.ORDER_STATUSE AS REFUND_STATUS,
	                        A.ADDRESS,
	                        COALESCE(B.CUST_STAT,'') AS CUST_STAT,
	                        CASE WHEN CRO.RETURNS IS NOT NULL AND B.ORDER_STATUSE = 3 THEN CRO.RETURNS
	                             WHEN CRO.RETURNS IS NOT NULL AND B.ORDER_STATUSE > 3 THEN B.RETURNS
	                             WHEN CRO.RETURNS IS NULL THEN COALESCE(B.RETURNS,0)
	                             ELSE 0
	                        END AS RETURNS
	                    FROM ORDERS A
	                    LEFT JOIN custody_orders B ON A.ORDER_ID = B.ORDER_ID AND CUST_STAT<>'FREEZE'
	                    LEFT JOIN custody_return_orders CRO ON A.EXCHANGE = CRO.ORDER_ID 
	                    ".$sqlWHEREpv[1]."
	                ) X
	                LEFT JOIN ORDERS            A ON X.ORDER_ID = A.ORDER_ID
	                LEFT JOIN ORDER_EXISTS      B ON A.EXISTS_ID = B.EXISTS_ID
	                LEFT JOIN ORDER_STATUSE     C ON A.ORDER_STATUS = C.STA_ID
	                LEFT JOIN ORDER_TYPE        D ON A.ORDER_TYPE_ID = D.ORDER_TYPE_ID
	                LEFT JOIN USERS             E ON A.ORDER_BY = E.USER_ID
	                LEFT JOIN USERS             F ON A.DELIVERY_ID = F.USER_ID
	                LEFT JOIN ADDRESSES         G ON A.ADDRESS_ID = G.ADDRESS_ID
	                LEFT JOIN CLIENT            H ON G.CLIENT_ID = H.CLIENT_ID
	                LEFT JOIN CITY              K ON G.CITY_ID = K.CI_ID
	                LEFT JOIN GOV               R ON K.GOV_ID = R.GOV_ID
	                LEFT JOIN ZONES             L ON K.ZONE_ID = L.ZONE_ID
	                LEFT JOIN ORDER_PRODUCT     M ON A.ORDER_ID = M.ORDER_ID
	                LEFT JOIN invoices          P ON A.ORDER_ID = P.REFERENCE_ID AND REFERENCE_TYPE='SALES'
	                LEFT JOIN ORDERS            EXO ON A.EXCHANGE = EXO.ORDER_ID
	                LEFT JOIN order_service     OSB ON A.ORDER_ID=OSB.ORDER_ID AND OSB.OS_STATUSE=1
	                LEFT JOIN order_service     OSC ON A.ORDER_ID=OSC.ORDER_ID AND OSC.OS_STATUSE=0
	                LEFT JOIN order_service     OSD ON A.ORDER_ID=OSD.ORDER_ID
	                LEFT JOIN PRODUCT_OPTIONS_VIEW POV ON M.PRO_CODE = POV.PRO_CODE
	                LEFT JOIN (
	                    SELECT  
	                        O.ORDER_ID,
	                        COALESCE(MAX(OS.OS_ID),0) AS OS_ID,
	                        COALESCE(MAX(OST.OSTX_READ_DATE),'2000-01-01 00:00:00') AS OSTX_READ_DATE,
	                        COALESCE(MAX(OST.OSTX_DATE),'2000-01-01 00:00:00') AS OSTX_DATE,
	                        COALESCE(MAX(OSA.OS_STATUSE),MAX(OSB.OS_STATUSE),1) AS OS_STATUSE,
	                        COUNT(DISTINCT OSB.OS_ID) AS COUNT_END_OS,
	                        COUNT(DISTINCT CASE WHEN OST.OSTX_READ = 0 AND OST.USER_ID <> ".(isset($_SESSION['USER_ID'])? $_SESSION['USER_ID']: 0) ." THEN OST.OSTX_ID END) AS CMSG
	                    FROM orders O
	                    LEFT JOIN order_service OS  ON O.ORDER_ID = OS.ORDER_ID
	                    LEFT JOIN order_service OSA ON O.ORDER_ID = OSA.ORDER_ID AND OSA.OS_STATUSE = 0
	                    LEFT JOIN order_service OSB ON O.ORDER_ID = OSB.ORDER_ID AND OSB.OS_STATUSE = 1
	                    LEFT JOIN order_service_text OST ON OSA.OS_ID = OST.OS_ID
	                    GROUP BY O.ORDER_ID
	                ) OSE ON X.ORDER_ID = OSE.ORDER_ID 
	                LEFT JOIN (SELECT EXCHANGE FROM ORDERS WHERE EXCHANGE IS NOT NULL) EXS ON EXS.EXCHANGE=X.ORDER_ID
	                GROUP BY X.ORDER_ID, X.DELIVERY_ID, X.ORDER_STATUS, X.ADDRESS, X.CUST_STAT, X.RETURNS";
	        
	        $Query_result = "SELECT * FROM (".$sqlSELECT.") ORDERS " . $sqlWHERE . $sqlORDERBY . $sqlLIMIT;
	        $Query_odata  = "SELECT * FROM (".$sqlSELECT.") ORDERS " . $sqlwex;
	        $Query_total  = "SELECT count(ORDER_ID) AS ordersCount FROM (".$sqlSELECT.") AS ORDERS " . $sqlWHERE;
	        
	        $stmtTotal = $this->conn->prepare($Query_total);
	
	        $stmtTotal->execute();
	        
	        $stmt = $this->conn->prepare($Query_result);
	        $stmt->execute();
	        
	        $total  = $stmtTotal->fetchAll(PDO::FETCH_ASSOC);
	        $result = $stmt;
	                
	        $allResult = $stmtTotal;
	        $allRecords = $total[0]['ordersCount'];
	        $displayRecords = $result->rowCount();
	        
	        $records = array();
	        
	        $usex = isset($this->rq_parm["ex"], $us['Admin']['orderlist_ex'.$this->rq_parm["ex"]]) ? $us['Admin']['orderlist_ex'.$this->rq_parm["ex"]] : false;
	        $custodyTotal = $this->getCustodyTotal();
	        
	        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
	            $records[] = [
	                'oid' => $record['ORDER_ID'],
	                'shipUid' => $record['DELIVERY_ID'],
	                'shipUName' => $record['DELIVERYBY'],
	                'subOid' => $record['SUB_ORDER_ID'],
	                'OTName' => $record['ORDER_TYPE_NAME'], 
	                'OStat' => $record['ORDER_STATUS'],
	                'OSuccess' => $record['ORDER_SUCCESS'],
	                'OExists' => $record['EXISTS_ID'],
	                'OPrint' => $record['PRINT'],
	                'custstat' => $record['CUST_STAT'],
	                'returns' => $record['RETURNS'],
	                'olup' => $record['LAST_UPDATE'],
	                'OSid' => $record['OS_ID'],
	                'OSStatu' => $record['OS_STATUSE'],
	                'OSMsgc' => $record['MSG_COUNT'],
	                'OSMsgCS' => $record['OSMsgCS'],
	                'OSMsgEnd' => $record['OS_END_COUNT'],
	                'stoid' => $record['STO_ID'],
	                'OInvo' => $record['INVO_ID'],
	                'ZName' => $record['ZONE_NAME'],
	                'SHIP_VAL' => $record['SHIPPING_VAL'],
	                
	                'ADDRESS' => $record['ADDRESS'],
	                'DEFAULTADD' => $record['DEFAULTADD'],
	                'ADDRESS_TITLE' => $record['ADDRESS_TITLE'],
	                'EXCHANGE' => $record['EXCHANGE'],
	                'uid' => $record['CLIENT_ID'],
	                'PRO_QTY' => $record['PRODUCTS_QTY'],
	                'PRODUCTS_COUNT' => $record['PRODUCTS_COUNT'],
	                'EXISTS_NAME' => $record['EXISTS_NAME'],
	                'ORDER_TYPE_ID' => $record['ORDER_TYPE_ID'],
	                'COLOR_CLASS' => $record["COLOR_CLASS"],
	                'STA_NAME' => $record['STA_NAME'],
	                'SUCCESS_STAT' => $record['SUCCESS_STAT'],
	                'CLIENT_NAME' => $record['CLIENT_NAME'],
	                'CLIENT_EMAIL' => $record['CLIENT_EMAIL'],
	                'EXCHANGE_SUPID' => $record['EXCHANGE_SUPID'],
	                'DATE_CREATED' => date("Y/m/d H:i", strtotime($record["DATE_CREATED"])),
	                'DELIVERY_DATE' => date("Y/m/d", strtotime($record["DELIVERY_DATE"])),
	                'SHIPPING_DATE' => $record['ORDER_STATUS'] > 1 ? (date("Y/m/d", strtotime($record["SHIPPING_DATE"])) != '1970-01-01' ? date("Y/m/d", strtotime($record["SHIPPING_DATE"])) : 'غير متاح') : 'غير متاح',
	                'DELIVERED_DATE' => $record['ORDER_STATUS'] > 1 ? (date("Y/m/d", strtotime($record["DELIVERED_DATE"])) != '1970-01-01' ? date("Y/m/d", strtotime($record["SHIPPING_DATE"])) : 'غير متاح') : 'غير متاح',
	                'TELEPHONE' => $record['TELEPHONE'],
	                'TEL_ID' => $record['TEL_ID'],
	                'CI_ID' => $record['CITY_ID'],
	                'GOV_ID' => $record['GOV_ID'],
	                'COUNTRY_ID' => $record['COUNTRY_ID'],
	                'ADDRESS_ID' => $record['ADDRESS_ID'],
	                'SHIPPING_LOWEST' => $record['SHIPPING_LOWEST'],
	                'SHIPPING_RATE' => $record['SHIPPING_RATE'],
	                'SHIPPING_TIME_MAX' => $record['SHIPPING_TIME_MAX'],
	                'SHIPPING_TYPE' => $record['SHIPPING_TYPE'],
	                'PRICE' => $record['PRICE'],
	                'Zid' => $record['ZONE_ID'],
	                'GOV_NAME' => $record["GOV_NAME"],
	                'CI_NAME' => $record["CI_NAME"],
	                'ORDERBY'       => $record['ORDERBY'],
	                'ORDER_BY_ID'   => $record['ORDER_BY'],
	                'BUTTONS' => self::listOrders_btns($this->rq_parm, $record, $usex),
	                'STORE_IS_AVAILABLE' => $record['STORE_IS_AVAILABLE'],
	                'PRODUCTS_DATA'    => $record['PRODUCTS_DATA']
	            ];
	        } 
	
	        if(!isset($this->rq_parm["draw"])){
	            $this->rq_parm["draw"] = 0 ;
	        }
	        
	        $defaultData = array(
	            "draw"                  => $this->rq_parm["draw"] ? $this->rq_parm["draw"] : 0,
	            "iTotalRecords"         => 0,
	            "iTotalDisplayRecords"  => 0,
	            "data"                  => [],
	            "cuTotal"               => $custodyTotal,
	            "odata"                 => []
	        );
	        
	        if($displayRecords){
	            $defaultData["draw"]                  = $this->rq_parm["draw"];
	            $defaultData["iTotalRecords"]         = $displayRecords;
	            $defaultData["iTotalDisplayRecords"]  = $allRecords;
	            $defaultData["data"]                  = $records;
	            $defaultData["cuTotal"]               = $custodyTotal;
	        }
	
	        return $defaultData;
	    } catch(Throwable $e){
		
		    error_log(
		        "LIST_ORDERS_ERROR => ".
		        $e->getMessage().
		        " FILE=".$e->getFile().
		        " LINE=".$e->getLine()
		    );
		
		    error_log(print_r($_POST,true));
		    error_log(print_r($_COOKIE,true));
			error_log(print_r($Query_total,true));
		    throw $e;
		}
    
    }
    
    public function listOrders($dtjson = false) { 
        $oidarr   = [];
        $records  = [];
        $us       = unserialize($_SESSION['USER_SCRIPT']);
        $fi       = isset($this->rq_parm['url']) && !empty($this->rq_parm['url']) ? json_decode($_COOKIE['filt_'. str_replace("*","_",$this->rq_parm['url'])], true) : null;
        $sqlQuery = self::listOrders_queries($fi, $us);
        $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    
        // الصلاحيات - استخدام cache
        $usex = $this->getUserPermissions();
    
        $limit = isset($this->rq_parm["limit"]) ? (int)$this->rq_parm["limit"] : 1000;
        $offset = isset($this->rq_parm["offset"]) ? (int)$this->rq_parm["offset"] : 0;
        $checkLupdate = isset($this->rq_parm["limit"]);
    
        // ===== LUPDATE =====
        if(!$checkLupdate && !$dtjson){
            $stmtLupdate = $this->conn->prepare($sqlQuery['lupdate']);
            $stmtLupdate->execute();
            $resultLupdate = $stmtLupdate->fetchAll(PDO::FETCH_ASSOC);
            $resultLupdate = array_column($resultLupdate,'ORDER_ID');
        }
    
        // ===== RESULT =====
        $sqlQuery['result'] .= " LIMIT :offset, :limit ";
        $stmt = $this->conn->prepare($sqlQuery['result']);
        $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
        $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $displayRecords = count($result);
        $allRecords = $displayRecords;
        $custodyTotal = $this->getCustodyTotal();
    
        foreach ($result as $record) {
            if (!$checkLupdate && !$dtjson && is_array($resultLupdate ?? [])) {
                if (($key = array_search($record['ORDER_ID'], $resultLupdate)) !== false) {
                    unset($resultLupdate[$key]);
                }
                if(isset($resultLupdate)) {
                    $resultLupdate = array_values($resultLupdate);
                }
            }

            $records[] = [
                'oid' => $record['ORDER_ID'],
                'shipUid' => $record['DELIVERY_ID'],
                'shipUName' => $record['DELIVERYBY'],
                'subOid' => $record['SUB_ORDER_ID'],
                'OTName' => $record['ORDER_TYPE_NAME'], 
                'OStat' => $record['ORDER_STATUS'],
                'OSuccess' => $record['ORDER_SUCCESS'],
                'OExists' => $record['EXISTS_ID'],
                'OPrint' => $record['PRINT'],
                'custstat' => $record['CUST_STAT'],
                'returns' => $record['RETURNS'],
                'olup' => $record['LAST_UPDATE'],
                'OSid' => $record['OS_ID'],
                'OSStatu' => $record['OS_STATUSE'],
                'OSMsgc' => $record['MSG_COUNT'],
                'OSMsgCS' => $record['OSMsgCS'],
                'OSMsgEnd' => $record['OS_END_COUNT'],
                'stoid' => $record['STO_ID'],
                'OInvo' => $record['INVO_ID'],
                'ZName' => $record['ZONE_NAME'],
                'SHIP_VAL' => $record['SHIPPING_VAL'],
                
                'ADDRESS' => $record['ADDRESS'],
                'DEFAULTADD' => $record['DEFAULTADD'],
                'ADDRESS_TITLE' => $record['ADDRESS_TITLE'],
                'EXCHANGE' => $record['EXCHANGE'],
                'uid' => $record['CLIENT_ID'],
                'PRO_QTY' => $record['PRODUCTS_QTY'],
                'PRODUCTS_COUNT' => $record['PRODUCTS_COUNT'],
                'EXISTS_NAME' => $record['EXISTS_NAME'],
                'ORDER_TYPE_ID' => $record['ORDER_TYPE_ID'],
                'COLOR_CLASS' => $record["COLOR_CLASS"],
                'STA_NAME' => $record['STA_NAME'],
                'SUCCESS_STAT' => $record['SUCCESS_STAT'],
                'CLIENT_NAME' => $record['CLIENT_NAME'],
                'CLIENT_EMAIL' => $record['CLIENT_EMAIL'],
                'EXCHANGE_SUPID' => $record['EXCHANGE_SUPID'],
                'DATE_CREATED' => date("Y/m/d H:i", strtotime($record["DATE_CREATED"])),
                'DELIVERY_DATE' => date("Y/m/d", strtotime($record["DELIVERY_DATE"])),
                'SHIPPING_DATE' => $record['ORDER_STATUS'] > 1 ? (date("Y/m/d", strtotime($record["SHIPPING_DATE"])) != '1970-01-01' ? date("Y/m/d", strtotime($record["SHIPPING_DATE"])) : 'غير متاح') : 'غير متاح',
                'DELIVERED_DATE' => $record['ORDER_STATUS'] > 1 ? (date("Y/m/d", strtotime($record["DELIVERED_DATE"])) != '1970-01-01' ? date("Y/m/d", strtotime($record["SHIPPING_DATE"])) : 'غير متاح') : 'غير متاح',
                'TELEPHONE' => $record['TELEPHONE'],
                'TEL_ID' => $record['TEL_ID'],
                'CI_ID' => $record['CI_ID'],
                'GOV_ID'          => $record['GOV_ID'], 
                'COUNTRY_ID'      => $record['COUNTRY_ID'], 
                'ADDRESS_ID' => $record['ADDRESS_ID'],
                'SHIPPING_LOWEST' => $record['SHIPPING_LOWEST'],
                'SHIPPING_RATE' => $record['SHIPPING_RATE'],
                'SHIPPING_TIME_MAX' => $record['SHIPPING_TIME_MAX'],
                'SHIPPING_TYPE' => $record['SHIPPING_TYPE'],
                'PRICE' => $record['PRICE'],
                'Zid' => $record['ZONE_ID'],
                'GOV_NAME' => $record["GOV_NAME"],
                'CI_NAME' => $record["CI_NAME"],
                'ORDERBY'       => $record['ORDERBY'],
                'ORDER_BY_ID'   => $record['ORDER_BY'],
                'BUTTONS' => self::listOrders_btns($this->rq_parm, $record, $usex),
                'PRODUCTS_DATA'    => $record['PRODUCTS_DATA']
            ];
        }
    
        $nextOffset = ($offset < $allRecords) ? $offset + $limit : null;
        
        return !$dtjson ? [
            "totalRecords" => $allRecords,
            "currentBatchSize" => $displayRecords,
            "data" => $records,
            "cuTotal" => $custodyTotal,
            "dataDelete" => !$checkLupdate ? ($resultLupdate ?? []) : [],
            "hasMore" => null,
        ] : [
            "draw" => isset($_POST['draw']) ? intval($_POST['draw']) : (isset($_GET['draw']) ? intval($_GET['draw']) : 0),
            "iTotalRecords" => $allRecords,
            "iTotalDisplayRecords" => $displayRecords,
            "data" => $records,
            "cuTotal" => $custodyTotal,
        ];
    }
    
    public function listOrders_NewOrder() { 
        $records  = [];
    
        $clid = isset($_GET['clientId']) && !empty($_GET['clientId']) ? $_GET['clientId'] : -1;
    
        $stmtTotal = $this->conn->prepare("
            SELECT COUNT(A.ORDER_ID) AS TOTAL
            FROM ORDERS A
            LEFT JOIN TELEPHONES T ON A.TEL_ID = T.TEL_ID
            WHERE T.CLIENT_ID = ?
        ");
        $stmtTotal->execute([$clid]);
        $total = (int)$stmtTotal->fetchColumn();
    
        $start  = isset($_GET['start']) ? intval($_GET['start']) : 0;
        $length = isset($_GET['length']) ? intval($_GET['length']) : 0;
        $limit  = isset($_GET['start']) && isset($_GET['length']) ? " LIMIT $start, $length " : "";
    
        $stmtData = $this->conn->prepare("
            SELECT 
                A.ORDER_ID,
                A.ORDER_STATUS , 
                A.SUB_ORDER_ID, 
                A.ADDRESS, 
                A.TELEPHONE, 
                A.DATE_CREATED, 
                A.DELIVERY_DATE, 
                A.SHIPPING_DATE, 
                A.DELIVERED_DATE, 
                A.SHIPPING_VAL, 
                k.CI_ID,
                R.GOV_ID,
                R.COUNTRY_ID,
                
                C.STA_NAME, 
                C.COLOR_CLASS,
                
                D.ORDER_TYPE_NAME,
                
                CONCAT(E.FORENAME,' ',E.LASTNAME) AS ORDERBY,
                H.CLIENT_NAME, H.CLIENT_EMAIL,
                L.SHIPPING_TIME_MAX,
                SUM((M.PRICE - M.DISCOUNT) * M.QTY) AS PRICE,
                K.CI_NAME, R.GOV_NAME,
                JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'PRODUCT_CODE', POV.PRO_CODE,
                        'PRODUCT_NAME', POV.PRO_NAME,
                        'PRODUCT_ATTRIBUTES', IFNULL(POV.OPT_JSON,''),
                        'ORDER_QTY', FORMAT(M.QTY,2),
                        'RETURNED_QTY', FORMAT(IF(A.ORDER_STATUS < 3,0,M.QTY - M.DELIVERED_QTY),2),
                        'DELIVERED_QTY', FORMAT(M.DELIVERED_QTY,2),
                        'PRICE', FORMAT(M.PRICE,2),
                        'DISCOUNT', FORMAT(M.DISCOUNT,2),
                        'REQUIRED_PAYMENT', FORMAT(M.QTY*(M.PRICE-M.DISCOUNT),2)
                    )
                ) AS PRODUCTS_DATA
            FROM ORDERS A
            LEFT JOIN ORDER_STATUSE C ON A.ORDER_STATUS = C.STA_ID
            LEFT JOIN ORDER_TYPE D ON A.ORDER_TYPE_ID = D.ORDER_TYPE_ID
            LEFT JOIN USERS E ON A.ORDER_BY = E.USER_ID
            LEFT JOIN ADDRESSES G ON A.ADDRESS_ID = G.ADDRESS_ID
            LEFT JOIN CLIENT H ON G.CLIENT_ID = H.CLIENT_ID
            LEFT JOIN CITY K ON G.CITY_ID = K.CI_ID
            LEFT JOIN GOV R ON K.GOV_ID = R.GOV_ID
            LEFT JOIN ZONES L ON K.ZONE_ID = L.ZONE_ID
            LEFT JOIN ORDER_PRODUCT M ON A.ORDER_ID = M.ORDER_ID
            LEFT JOIN PRODUCT_OPTIONS_VIEW POV ON M.PRO_CODE=POV.PRO_CODE
            LEFT JOIN TELEPHONES T ON A.TEL_ID = T.TEL_ID
            WHERE T.CLIENT_ID = ?
            GROUP BY A.ORDER_ID
            ORDER BY A.ORDER_STATUS, A.DATE_CREATED DESC
            $limit
        ");
        $stmtData->execute([$clid]);
        $resultData = $stmtData->fetchAll(PDO::FETCH_ASSOC);
    
        foreach ($resultData as $record) {
            $records[] = [
                'oid'              => $record['ORDER_ID'],
                'subOid'           => $record['SUB_ORDER_ID'],
                'ADDRESS'          => $record['ADDRESS'],
                'TELEPHONE'        => $record['TELEPHONE'],
                'DATE_CREATED'     => date("Y/m/d H:i", strtotime($record["DATE_CREATED"])),
                'DELIVERY_DATE'    => date("Y/m/d", strtotime($record["DELIVERY_DATE"])),
                'SHIPPING_DATE'    => $record['ORDER_STATUS'] > 1 ? ( date("Y/m/d", strtotime($record["SHIPPING_DATE"])) !='1970-01-01' ?  date("Y/m/d", strtotime($record["SHIPPING_DATE"])) : 'غير متاح') : 'غير متاح',
                'DELIVERED_DATE'   => $record['ORDER_STATUS'] > 1 ? (date("Y/m/d", strtotime($record["DELIVERED_DATE"])) !='1970-01-01' ?  date("Y/m/d", strtotime($record["DELIVERED_DATE"])) : 'غير متاح') : 'غير متاح',
                'SHIP_VAL'         => $record['SHIPPING_VAL'],
                'STA_NAME'         => $record['STA_NAME'],
                'ORDER_STATUS'     => $record['ORDER_STATUS'],
                'ORDER_TYPE_NAME'  => $record['ORDER_TYPE_NAME'], 
                'ORDERBY'          => $record['ORDERBY'], 
                'CLIENT_NAME'      => $record['CLIENT_NAME'], 
                'CLIENT_EMAIL'     => $record['CLIENT_EMAIL'], 
                'SHIPPING_TIME_MAX'=> $record['SHIPPING_TIME_MAX'], 
                'PRICE'            => $record['PRICE'], 
                'CI_ID'            => $record['CI_ID'], 
                'GOV_ID'           => $record['GOV_ID'], 
                'COUNTRY_ID'       => $record['COUNTRY_ID'], 
                'CI_NAME'          => $record['CI_NAME'], 
                'GOV_NAME'         => $record['GOV_NAME'],
                'COLOR_CLASS'      => $record['COLOR_CLASS'],
                'PRODUCTS_DATA'    => $record['PRODUCTS_DATA']
            ];
        }
    
        return [
            "draw" => intval($_POST['draw'] ?? $_GET['draw'] ?? 0),
            "recordsTotal" => $total,
            "recordsFiltered" => $total,
            "data" => $records
        ];
    }
}
?>