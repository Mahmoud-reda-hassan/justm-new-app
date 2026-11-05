<?php
class User_group_tbl {
    private $Tablename = "USER_GRUOP";
    public $user_group_id;
    public $user_group_parent;
    public $user_group_name;
    public $user_script;
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    } // end function conn
    public function listUserGroup() {
    	$uid = $_SESSION['USER_ID'];
        $sqlQuery = "SELECT A.*,COUNT(B.USER_ID) AS COUNT_USER,B.USER_GRUOP_ID AS UGID FROM ". $this->Tablename ." AS A LEFT JOIN USERS AS B ON A.USER_GRUOP_ID = B.USER_GRUOP_ID ";
        if (!empty($_GET["search"]["value"])) {
            $sqlQuery .= ' where (A.USER_GRUOP_ID LIKE "%'.$_GET["search"]["value"].'%" ';
            $sqlQuery .= ' OR A.USER_GRUOP_NAME LIKE "%'.$_GET["search"]["value"].'%") ';
        }
        // Group by sqlQuery
        $sqlQuery .= ' GROUP BY A.USER_GRUOP_ID, A.USER_GROUP_PARENT,A.USER_GRUOP_NAME,A.USER_SCRIPT,B.USER_GRUOP_ID ';
        // Order by sqlQuery
        if (!empty($_GET["order"])) {
        	$columnsArray=['A.USER_GRUOP_NAME','A.USER_GRUOP_NAME'];
            $sqlQuery .= ' ORDER BY '.$columnsArray[$_GET['order']['0']['column']].' '.$_GET['order']['0']['dir'].' ';
        } else {
            $sqlQuery .= ' ORDER BY USER_GRUOP_ID DESC ';
        }
        // LIMIT data sqlQuery
        if (!empty($_GET["length"]) && $_GET["length"] != -1) {
            $sqlQuery .= ' LIMIT ' . $_GET['start'] . ', ' . $_GET['length'] . ' ';
        }
        $stmt = $this->conn->prepare($sqlQuery);
        $stmt->execute();
        $result = $stmt;
        $stmtTotal = $this->conn->prepare("SELECT * FROM ".$this->Tablename);
        $stmtTotal->execute();
        $allResult = $stmtTotal;
        $allRecords = $allResult->rowCount();
        $displayRecords = $result->rowCount();
        $records = array();
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            if($record['USER_GROUP_PARENT'] == 1 && $uid >1){
            	continue;
            }
            $rows = array();
            if ($record['COUNT_USER'] <= 0) {
                $rows[] = '<input id="'.$record['USER_GRUOP_ID'].'" data-name="'.$record["USER_GRUOP_NAME"].'" type="checkbox">';
            } else {
                $rows[] = '#';
            }
            $rows[] = $record['USER_GRUOP_NAME'];
            if ($record['USER_GRUOP_ID'] === 1 && $record['UGID'] !== 1) {
                $rows[] = "<button type='button' class='btn btn-primary btn-xs btnEdit' disabled><i class='fas fa-edit'></i></button>";
            } else {
                $rows[] = "<button id='btnNew' type='button' data-id='".$record["USER_GRUOP_ID"]."' data-name='".$record["USER_GRUOP_NAME"]."' class='btn btn-primary btn-xs btnEdit'><i class='fas fa-edit'></i></button>";
            }
            $records[] = $rows;
        }
        $output = array(
            "draw" => intval($_GET["draw"]),
            "iTotalRecords" => $displayRecords,
            "iTotalDisplayRecords" => $allRecords,
            "data" => $records
        );
        echo json_encode($output);
    }
    public function AddUserGroup() {
        $sqlQuery = 'INSERT INTO `USER_GRUOP`(`USER_GROUP_PARENT`, `USER_GRUOP_NAME`, `USER_SCRIPT`) VALUES (?,?,?)';
        $this->user_group_parent = 0;
        $this->user_group_name = htmlspecialchars(strip_tags($this->user_group_name));
        $this->user_script = serialize($this->user_script);
        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare($sqlQuery);
            $stmt->bindValue(1, $this->user_group_parent, PDO::PARAM_INT);
            $stmt->bindValue(2, trim($this->user_group_name, " "), PDO::PARAM_STR);
            $stmt->bindValue(3, $this->user_script);
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                $this->conn->commit();
                echo json_encode(['s', 'تم الحفظ بنجاح']);
            } else {
                $this->conn->rollback();
                echo json_encode(['e', 'حدث خطاء غير معروف اثناء الحفظ']);
            }
        } catch (PDOException $e) {
            $this->conn->rollback();
            $arr = array("e", $e->getMessage(), $e->getCode());
            echo json_encode($arr);
        }
    }
    public function updateUserGroup() {
        $sqlQuery = 'UPDATE `USER_GRUOP` SET `USER_GRUOP_NAME`= ? ,`USER_SCRIPT`= ?  WHERE `USER_GRUOP_ID`= ? ';
        $this->user_group_id = htmlspecialchars(strip_tags($this->user_group_id));
        $this->user_group_name = htmlspecialchars(strip_tags(trim($this->user_group_name)));
        $this->user_script = serialize($this->user_script);
        
        try {
        	
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare($sqlQuery);
            $stmt->bindValue(1, $this->user_group_name, PDO::PARAM_STR);
            $stmt->bindValue(2, $this->user_script);
            $stmt->bindValue(3, $this->user_group_id, PDO::PARAM_INT);
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                $this->conn->commit();
                echo json_encode(['s', 'تم الحفظ بنجاح']);
            } else {
                $this->conn->rollback();
                echo json_encode(['w', 'لم يحدث اى تغيرات لحفظها وتبقى بيناتك فى امان']);
            }
        } catch (PDOException $e) {
            $this->conn->rollback();
            $arr = array("errorConn", $e->getCode() .' - '.$e->getMessage());
            echo json_encode($arr);
        }
    }
    public function deleteUserGroup() {
        $sqlQuery = 'DELETE FROM `USER_GRUOP` WHERE  FIND_IN_SET (`USER_GRUOP_ID`, ? ) ';
        $this->user_group_id = htmlspecialchars(strip_tags($this->user_group_id));
        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare($sqlQuery);
            $stmt->bindValue(1, $this->user_group_id, PDO::PARAM_INT);
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    $this->conn->commit();
                    echo json_encode(['s', 'تم حذف البيانات بنجاح']);
                } else {
                    $this->conn->rollback();
                    echo json_encode(['e', 'لم يتم حذف البينات المطلوبة - يحتمل انه تم حذف البانات من قبل مستخدم اخر او السجل غير موجد بالفعل بقواعد البيانات']);
                }
            } else {
                $this->conn->rollback();
                echo json_encode(['e', 'لم يتم الحذف - حدث خطاء غير معروف']);
            }
        } catch (PDOException $e) {
            $this->conn->rollback();
            $arr = array("e", $e->getMessage(), $e->getCode());
            echo json_encode($arr);
        }
    }
    public function user_permission_array() {

        $sqlQuery = "SELECT * FROM ". $this->Tablename ." WHERE USER_GRUOP_ID = ? ";
        $sqlQueryEx = "SELECT * FROM order_exists";
        $stmt = $this->conn->prepare($sqlQuery);
        $stmt->bindValue(1, $this->user_group_id, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt;
        $records = array();
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {

            $script = unserialize($record['USER_SCRIPT']);

        }
        $stmtEx = $this->conn->prepare($sqlQueryEx);
        $stmtEx->execute();
        $resultEx = $stmtEx;
        $ExArr = array();

        while ($recordEx = $resultEx->fetch(PDO::FETCH_ASSOC)) {
            $rows = array();
            $rows[] = $recordEx['EXISTS_ID'];
            $rows[] = $recordEx['EXISTS_NAME_EN'];
            $rows[] = $recordEx['EXISTS_NAME'];
            $rows[] = $recordEx['TRANSFER'];
            $ExArr[] = $rows;
        }
        $ExArr[] = array("-2", "os2", "طلبات الدعم", -2);
        $ExArr[] = array("-1", "os1", "متابعة تسليمات المنديب", -1);
        $per1 = ["Admin" =>
            [
                "dashboard" => [
                    "Admin" => false,
                    "CustomerService1" => false,
                    "CustomerService2" => false,
                    "Storage" => false,
                    "Delivery" => false,
                ],
//اعدادات جغرافية
                "Countries" => [
                    "View" => false,
                    "Addnew" => false,
                    "Update" => false,
                    "Delete" => false,
                ],

                "Gov" => [
                    "View" => false,
                    "Addnew" => false,
                    "Update" => false,
                    "Delete" => false,
                ],

                "City" => [
                    "View" => false,
                    "Addnew" => false,
                    "Update" => false,
                    "Delete" => false,
                ],

                "Local-Shipping" => [
                    "View" => false,
                    "Addnew" => false,
                    "Update" => false,
                    "Delete" => false,
                ],

                "International-Shipping" => [
                    "View" => false,
                    "Addnew" => false,
                    "Update" => false,
                    "Delete" => false,
                ],
//اعدادات عامه
                "Enterprise" => [
                    "View" => false,
                    "Addnew" => false,
                    "Update" => false,
                    "Delete" => false,
                ],

                "Administrative-Structure" => [
                    "View" => false,
                    "Addnew" => false,
                    "Update" => false,
                    "Delete" => false,
                ],

                "Branches" => [
                    "View" => false,
                    "Addnew" => false,
                    "Update" => false,
                    "Delete" => false,
                ],

                "Warehouse" => [
                    "View" => false,
                    "Addnew" => false,
                    "Update" => false,
                    "Delete" => false,
                ],

                "Users" => [
                    "View" => false,
                    "Addnew" => false,
                    "Update" => false,
                    "Delete" => false,
                ],
// العملاء
				"Clients" => [
                    "View" => false,
                    "Addnew" => false,
                    "Update" => false,
                    "Delete" => false,
                ],
//الموردين
                "Suppliers" => [
                    "View" => false,
                    "Addnew" => false,
                    "Update" => false,
                    "Delete" => false,
                ],
                'UserSpecificPermissions' => [
                	"viewAllCompany" => false,
                	"viewAllBranches" => false,
                	"viewAllStores" => false,
                ],

                "Reports" => [
                    "View" => false,
                ],
//المنتجات
                "Product-Categories" => [
                    "View" => false,
                    "Addnew" => false,
                    "Update" => false,
                    "Delete" => false,
                ],

                "ProductOption" => [
                    "View" => false,
                    "Addnew" => false,
                    "Update" => false,
                    "Delete" => false,
                ],

                "Products" => [
                    "View"   => false,
                    "Addnew" => false,
                    "Update" => false,
                    "Delete" => false,
                ],
                
//المبيعات
                "NewOrder" => [
                	"Create_Order" => false,
               	],
                "orderlist_ex0" => [
                    "View_order_list" => false,
                    "View_order" => false,
                ],
                "selseReport"=>[
                	"View_order_list"   => false,
                ],
//تاكيد
                "orderlist_ex1" => [
                    "View_order_list" => false,
                    "View_order" => false,
                    "Edit_order" => false,
                    "Delete_order" => false,
                    "transformation" => false,
                    "Nostore" => false,
                    "pending sale orders" => false,
                    "Advance booking orders" => false,
                    "The client is not responding" => false,
                    "Cancel order" => false,
                    "Shipping Confirmation" => false,
                    "Processing" => false,
                    "Prepared" => false,
                ],
//مؤجلات
                "orderlist_ex2" => [
                    "View_order_list" => false,
                    "View_order" => false,
                    "Edit_order" => false,
                    "Delete_order" => false,
                    "transformation" => false,
                    "Nostore" => false,
                    "Confirm sale orders" => false,
                    "Advance booking orders" => false,
                    "The client is not responding" => false,
                    "Cancel order" => false,
                    "Shipping Confirmation" => false,
                    "Processing" => false,
                    "Prepared" => false,
                ],
//غير متوفر
                "orderlist_ex3" => [
                    "View_order_list" => false,
                    "View_order" => false,
                    "Edit_order" => false,
                    "Delete_order" => false,
                    "transformation" => false,
                    "Nostore" => false,
                    "Confirm sale orders" => false,
                    "pending sale orders" => false,
                    "The client is not responding" => false,
                    "Cancel order" => false,
                    "Shipping Confirmation" => false,
                    "Processing" => false,
                    "Prepared" => false,
                ],
//لا يستجيب
                "orderlist_ex4" => [
                    "View_order_list" => false,
                    "View_order" => false,
                    "Edit_order" => false,
                    "Delete_order" => false,
                    "transformation" => false,
                    "Nostore" => false,
                    "Confirm sale orders" => false,
                    "pending sale orders" => false,
                    "Advance booking orders" => false,
                    "Cancel order" => false,
                    "Shipping Confirmation" => false,
                    "Processing" => false,
                    "Prepared" => false,
                ],
//ملغى
                "orderlist_ex5" => [
                    "View_order_list" => false,
                    "View_order" => false,
                    "Edit_order" => false,
                    "Delete_order" => false,
                    "transformation" => false,
                    "Nostore" => false,
                    "Confirm sale orders" => false,
                    "pending sale orders" => false,
                    "Advance booking orders" => false,
                    "The client is not responding" => false,
                    "Shipping Confirmation" => false,
                    "Processing" => false,
                    "Prepared" => false,
                ],
//تأكيد الشحن والتوصيل
                "orderlist_ex6" => [
                    "View_order_list" => false,
                    "View_order" => false,
                    "Edit_order" => false,
                    "Delete_order" => false,
                    "transformation" => false,
                    "Nostore" => false,
                    "Confirm sale orders" => false,
                    "pending sale orders" => false,
                    "Advance booking orders" => false,
                    "The client is not responding" => false,
                    "Processing" => false,
                    "Prepared" => false,
                ],
/*
                "orderlist_os2" => [
                    "View_order_list" => false,
                    "View_order" => false,
                    "Edit_order" => false,
                    "Delete_order" => false,
                    "transformation" => false,
                    "Shipping" => false,
                    "os1" => false,
                    "Confirm sale orders" => false,
                    "pending sale orders" => false,
                    "Advance booking orders" => false,
                    "The client is not responding" => false,
                    "Cancel order" => false,
                    "Processing" => false,
                ],
*/
				
                "orderlist_ex12" => [
                    "View_order_list" => false,
                    "View_order" => false,
                    "Edit_order" => false,
                    "Delete_order" => false,
                    "transformation" => false,
                    "Shipping" => false,
                    "os2" => false,
                    "Confirm sale orders" => false,
                    "pending sale orders" => false,
                    "Advance booking orders" => false,
                    "The client is not responding" => false,
                    "Cancel order" => false,
                    "Shipping Confirmation" => false,
                    "Processing" => false,
                ],
//خدمة ما بعد البيغ
				"SupportAfterSales" 		=> [
					"View_order_list" 	=> false,
					"View_order" 		=> false,
					//"transformation" => false,
					//"EditDelevered" 	=> false,
					//"ShipmentApproved"	=> false,
					//"RefundShipment" 	=> false,
                    //"ChooseRefund" 		=> false,
                ],
//طلبات المنسقين
                "SupportOrders" 		=> [
					"View_order_list" 	=> false,
					"View_order" 		=> false,
					"Edit_order" => false,
					"transformation" => false,
					"Nostore" => false,
                    "pending sale orders" => false,
                    "Advance booking orders" => false,
                    "The client is not responding" => false,
                    "Cancel order" => false,
                    "Processing" => false,
                    "Shipping Confirmation" => false,
                    "Prepared" => false,
					//"ShipmentApproved"	=> false,
					//"RefundShipment" 	=> false,
                    //"ChooseRefund" 		=> false,
                ],
//المخزن
                "orderlist_ex7" => [
                    "View_order_list" => false,
                    "View_order" => false,
                    "Edit_order" => false,
                    "Delete_order" => false,
                    "Invoce_Print" => false,
                    "transformation" => false,
				//	"Nostore" => false,
                    "Confirm sale orders" => false,
                    "pending sale orders" => false,
                    "Advance booking orders" => false,
                    "The client is not responding" => false,
                    "Cancel order" => false,
                    "Prepared" => false,
                ],

                "orderlist_ex8" => [
                    "View_order_list" => false,
                    "View_order" => false,
                    "Edit_order" => false,
                    "Delete_order" => false,
                    "Shipment" => false,
                    "Shipmentdate" => false,
                    "transformation" => false,
                    "Nostore" => false,
				//	"Confirm sale orders" => false,
				//	"pending sale orders" => false,
					"Advance booking orders" => false,
				//	"The client is not responding" => false,
                    "Cancel order" => false,
                    "Processing" => false,
                ],

                "orderlist_ex9" => [
                    "View_order_list" => false,
                    "View_order" => false,
					"DeliveredFromAdmin" => false,
                    "RefundShipment" => false,
                    "ChooseRefund" => false,
                    //"transformation" => false,
                    //"os2" => false,
                    //"os1" => false,
                ],
                
                "OrdersCustody" 		=> [
					"View_order_list" 	=> false,
					"View_order" 		=> false,
					"EditDelevered" 	=> false,
					"ShipmentApproved"	=> false,
					"RefundShipment" 	=> false,
                    "ChooseRefund" 		=> false,
                ],
                'StockReceipt' =>[
                	"view" => false,
                	"viewAllBranches" => false,
                ],
                'StockIssue' =>[
                	"view" => false,
                	
                ],
                'StockTransfer' =>[
                	"view" => false,
                ],
                'StockAudit' =>[
                	"view" => false,
                	"viewAllBranches" => false,
                	"viewStoreQty" => false,
                ],
                
                'CriticalItemsReport' =>[
                	"view" => false,
                ],
                'DelegatesShipmentReport' =>[
                	"view" => false,
                ],
                'DelegatesInventoryReport' =>[
                	"view" => false,
                ],
                'suppliersQtyReport' =>[
                	"view" => false,
                ],
                
// الحسابات
                'PaymentsReceipt' =>[
                	"view" => false,
                ],
                
                'PaymentDisbursement' => [
                	"view" => false,
                ],
                'CashReceiptVouchers' => [
                	"view" => false,
                ],
                
                
                
				'AccountStatementSupplier' =>[
                	"view" => false,
                ],
                'AccountStatementClient' =>[
                	"view" => false,
                ],
                'AccountStatementMarketer' =>[
                	"view" => false,
                ],
                'AccountStatementDelivery' =>[
                	"view" => false,
                ],
 
                
                "orderlist_ex10" => [
                    "View_order_list" => false,
                    "View_order" => false,
                    "Edit_order" => false,
                    "Delete_order" => false,
                    "transformation" => false,
                    "Shipping" => false,
                    "os2" => false,
                    "Confirm sale orders" => false,
                    "pending sale orders" => false,
                    "Advance booking orders" => false,
                    "The client is not responding" => false,
                    "Cancel order" => false,
                    "Processing" => false,
                ],
                
            ]
        ];

        $langPer = array(
            "dashboard" => "لوحة التحكم",
            "Admin" => "المدير العام",
            "CustomerService1" => "خدمة المتابعه والتأكيد",
            "CustomerService2" => "خدمة ما بعد البيع",
            "Storage" => "المخزن",
            "Delivery" => "خدمة التوصيل",
            'Countries' => 'الدول',
            'Gov' => 'المحافظة او المقاطعه',
            "City" => "المدن",
            "Local-Shipping" => "إعداد الشحن المحلى",
            "International-Shipping" => "إعداد الشحن الدولى",
            "Enterprise" => "المؤسسه",
            "Administrative-Structure" => "الهيكل الادارى",
            "Branches" => "بيانات الفروع",
            "Warehouse" => "بيانات المخازن",
            "Users" => "المستخدمين",
            "Clients" => "بيانات العملاء",
            "Suppliers" => "بيانات الموردين",
            "Reports" => "تقرير عامه",
            "Product-Categories" => "الاقسام",
            "ProductOption" => "خيارات المنتجات",
            "Products" => "عرض المنتجات",
            "orderlist_ex0" => "قائمة الطلبات",
			"OrdersCustody" => "تسوية عهدة المندوبين",
            "View" => "عرض",
            "Addnew" => "اضافة",
            "Update" => "تعديل",
            "Delete" => "حذف",
            "View_order_list" => "عرض قائمة الطلبات",
            "View_order" => "عرض بيانات الطلب",
            "Edit_order" => "تعديل بيانات الطلب",
            "Delete_order" => "حذف الطلب",
            
            "transformation" => "تحويل الطلب",
            "Confirm sale orders" => false,
            "pending sale orders" => false,
            "Advance booking orders" => false,
            "The client is not responding" => false,
            "Cancel order" => false,
            "Processing" => false,
            "Prepared" => false,
            
            "Invoce_Print" => "طباعة فاتواتير الشحن",
            
            "Shipment" => "تسليم الشحنه للمندوب",
            "Shipmentdate" => "اظهار وأتاحت التعديل على تاريخ التسليم",
            "DeliveredFromAdmin" => "تسليم الشحنه بدلا من المندوب",
            "EditDelevered" => "تعديل تسليمات المندوب",
            "RefundShipment" =>"استرداد الشحنة من المندوب",
            "ChooseRefund" => "استرداد امر البيع فقط دون استرداد العهده",
            "Nostore" => "التحويل فى حالة عدم توافر المنتجات",
			"ShipmentApproved" => "تأكيد استلام مرتجعات امر البيع",
			"TrackShipmentConfirmation" => 'تتبع تأكيد الشحن',
			"SupportAfterSales" => 'خدمة ما بعد البيع',
			"SupportOrders" => 'طلبات المنسقين والعملاء',
			
			"NewOrder" =>'انشاء امر بيع',
            "Create_Order" => 'انشاء امر بيع جديد',
            'StockReceipt' => 'اذن استلام مخزنى',
            'StockIssue' => 'اذن صرف مخزنى',
            'StockTransfer' =>  'اذن تحويل مخزنى',
            'StockAudit' => 'جرد ومطابقة مخزون',
            'CriticalItemsReport' => 'تقرير الاصناف الحرجه',
            'DelegatesShipmentReport' => 'تقرير حركة عهدة المندوبين',
            'DelegatesInventoryReport' =>'تقرير حركة الاستلامات المخزنيه',
            'suppliersQtyReport' => 'تقرير استلامات الموردين',
            'PaymentsReceipt' => 'استلام عهدة المندوبين',
            'PaymentDisbursement' => 'اذن صرف نقدية',
            'CashReceiptVouchers' => 'اذن استلام نقدية',
            
            'AccountStatementMarketer' =>'كشف حساب المسوقين',
            'AccountStatementDelivery' =>'كشف حساب المندوبين',
            'AccountStatementClient' =>'كشف حساب العملاء',
            'AccountStatementSupplier'=>'كشف حساب الموردين',
            
            'UserSpecificPermissions' => 'صلاحيات مخصصه للمستخدم',
            
            'view' => 'عرض',
            "viewAllCompany" =>  'يمكن للمستخدم التعامل مع كل الشركات',
           	"viewAllBranches" => 'يمكن للمستخدم التعامل مع كل الفروع',
            "viewAllStores" => 'يمكن للمستخدم التعامل مع كل المخازن',
            "viewStoreQty" => 'عرض الكميات الفعليه فى المخزن عند الجرد',
        );


        foreach ($ExArr as $val1) {
            $exi = '';

            if ($val1[0] >= 0) {
                $exi = 'orderlist_ex' . $val1[0];
            } else {
                $exi = 'orderlist_os' . abs($val1[0]);
            }


          /*  $per1['Admin'][$exi]["View_order_list"] = false;
            $per1['Admin'][$exi]["View_order"] = false;
            $per1['Admin'][$exi]["Edit_order"] = false;
            $per1['Admin'][$exi]["Delete_order"] = false;
            if ($val1[0] === 7) {
                $per1['Admin'][$exi]['Invoce_Print'] = false;
            }

            if ($val1[0] === 8) {
                $per1['Admin'][$exi]['Shipment'] = false;
                $per1['Admin'][$exi]['Shipmentdate'] = false;
            }
            if ($val1[0] === 9) {
                //$per1['Admin'][$exi]['Shipment']       = false;
                //$per1['Admin'][$exi]['Shipmentdate']   = false;
            }
            $langPer[$exi] = 'قائمة ' . $val1[2];
            $langPer[$val1[1]] = ' التحويل لـ ' . $val1[2];
            if ($val1[3] == 1) {
                $langPer['RefundShipment'] = 'استرداد الشحنة من المندوب';
                $langPer['ChooseRefund'] = 'اختيار استرداد امر البيع فقط دون رد العهده';
                $per1['Admin'][$exi]['RefundShipment'] = false;
                $per1['Admin'][$exi]['ChooseRefund'] = false;

            }
            if ($val1[3] == 0) {
                $per1['Admin'][$exi]["transformation"] = false;
                $per1['Admin'][$exi]['Nostore'] = false;
            } */
            $langPer[$exi] = 'قائمة ' . $val1[2];
            $langPer[$val1[1]] = ' التحويل لـ ' . $val1[2];
            foreach ($ExArr as $val2) {
                if ($val1[0] != $val2[0] &&
                    $val2[3] == 0 && $val1[3] == 0
                ) {
                    $per1['Admin'][$exi][$val2[1]] = false;
                }
            }


        }  

        $perfinal = array();
        if (isset($script) && is_array($script) && count($script['Admin']) > 0 && $script['Admin']) {
            $perfinal['Admin'] = array_replace_recursive($per1['Admin'], $script['Admin']);
        } else {
            $perfinal['Admin'] = $per1['Admin'];
        }

        $per = $perfinal;
        $htmlperm = '<nav class="mt-2 text-left"><ul id="ulPerm" class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="true">';
        foreach ($per['Admin'] as $key1 => $value1) {
            if (array_key_exists($key1, $per1['Admin'])) {
                $htmlperm .= '<li class="nav-item menu-ope"  style="margin-right:-20px;margin-left:-15px;">';
                $htmlperm .= '<a class="nav-link">';
                $htmlperm .= '<i class="fa fa-ellipsis-v nav-icon"></i>';
                if (array_key_exists($key1, $langPer)) {
                    $htmlperm .= '<span>'.$langPer[$key1].'</span>';
                } else {
                    $htmlperm .= '<span>'.$key1.'</span>';
                }
                $htmlperm .= '<i class="fas fa-angle-left right"></i>';
                $htmlperm .= '</a>';
                $htmlperm .= '<ul class="nav nav-treeview" name="Redir" value="1">';
                $xo = 0;
                foreach ($per['Admin'][$key1] as $key2 => $value2) {
                    if ($key2 == "transformation" && array_key_exists($key2, $per1['Admin'][$key1])) {
                        $htmlperm .= '<li class="nav-item ml-4">';
                        $htmlperm .= '<a class="nav-link"> ';
                        $htmlperm .= '<i class="fa-solid fa-rectangle-list" style="margin-left:15px;"></i> '.$langPer[$key2];
                        $htmlperm .= '<i class="fas fa-angle-left right"></i>';
                        $htmlperm .= '</a>';
                        $htmlperm .= '<ul class="nav nav-treeview" name="Redir" value="1">';
                        if ($value2 == "true") {
                            $htmlperm .= '<li class="nav-item " style="margin-right:40px;"><input class="q" type="checkbox" data-k1="'.$key1.'" data-k2="'.$key2.'" checked="'.$value2.'" style="margin-left:15px;">'.$langPer[$key2].'</li>';
                        } else {
                            $htmlperm .= '<li class="nav-item " style="margin-right:40px;"><input class="q" type="checkbox" data-k1="'.$key1.'" data-k2="'.$key2.'" style="margin-left:15px;">'.$langPer[$key2].'</li>';
                        }
                        $xo++;
                    } else {
                        if ($value2 == "true") {
                            $htmlperm .= '<li class="nav-item " style="margin-right:40px;"><input class="q" type="checkbox" data-k1="'.$key1.'" data-k2="'.$key2.'" checked="'.$value2.'" style="margin-left:15px;">'.$langPer[$key2].'</li>';
                        } else {
                            $htmlperm .= '<li class="nav-item " style="margin-right:40px;"><input class="q" type="checkbox" data-k1="'.$key1.'" data-k2="'.$key2.'" style="margin-left:15px;">'.$langPer[$key2].'</li>';
                        }
                    }
                }
                if ($xo > 0) {
                    $htmlperm .= '</ul></li>';
                    $xo = 0;
                }
                $htmlperm .= '</ul></li>';
            }
        }
        $htmlperm .= '</ul></nav>';
        echo json_encode($htmlperm);
    }//end function
    public function Select_user_group() {
        $sqlQuery = "SELECT * FROM ". $this->Tablename .' ORDER BY USER_GRUOP_ID ';
        $stmt = $this->conn->prepare($sqlQuery);
        $stmt->execute();
        $result = $stmt;
        $records = array();
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            $rows = array();
            $rows[] = $record["USER_GRUOP_ID"];
            $rows[] = $record['USER_GRUOP_NAME'];
            $records[] = $rows;
        }
        echo json_encode($records);
    }
}//end class