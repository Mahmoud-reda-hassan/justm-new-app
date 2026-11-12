<?php
/*================================================
**  orders Database Table
**==============================================*/
class Invoices_tbl {
    private $invocesTbl = 'INVOICES';
    public $order_id;
    public $sub_invo_id;
    public $creation_date;
    public $payment_success;
    public $dataArr;
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    private function Query($chose){
        switch ($chose) {
          case "insert_inv": // انشاء فاتورة مبيعات مؤجلة التسديد
            return  " INSERT INTO `invoices`(`SUB_INVO_ID`, `ORDER_ID`, `CREATION_DATE`, `PAYMENT_SUCCESS` ) VALUES ";
            break;
          case "update_order": // استعلام تحديث بينات امر البيع
            return "UPDATE `ORDERS` SET PRINT = 1, LAST_UPDATE  = now(3) WHERE PRINT = FALSE AND FIND_IN_SET (ORDER_ID, ? )  ";
            break;
          case "insert_olog": // استعلام اضافة حركة الطباعه الى امر البيع
            return "INSERT INTO ORDERS_LOG (ORDER_ID,USER_ACTION,LOG_TEXT) VALUES ";
            break;
        }
    }
    public function in_Invoices () {
        
        $approvedOID = []; // مصفوفة جمع ارقام اوامر البيع
        $approvedPro = []; // مصفوفة جمع اصناف اوامر البيع
        $rejectedOID = []; // مصفوفة جمع ارقام اوامر البيع المرفوضه
        $orderidarr  = []; // مصفوفة جمع كل ارقام اوامر البيع
        $insertologtxt = [];    // متغير نصى لحفظ بينات حركة المستخدم على امر البيع
        require_once($_SERVER["DOCUMENT_ROOT"].'/inc/Classes/Order_tbl.php'); // ادراج ملف Order_tbl
        $order_tbl           = new Order_tbl($this->conn); // جلب كائن Order_tbl
        $order_tbl->order_id = $this->dataArr['oidPrint']; // set Order_id
        
        $dataRQ = $order_tbl->orderView(false); // جلب بينات اوامر البيع المطلوب طباعتها
        foreach ($dataRQ as $value) { //فرز بيانات اوامر البيع المطلوب طباعتها
            $row = $value['orderData']; //جلب بيانات كل امر بيع على حده
            /* $che argument array('oid'    => 'ORDER_ID', 'subOid' => 'SUB_ORDER_ID', 'OTName' => 'ORDER_TYPE_NAME', 'OStat'  => 'ORDER_STATUS', 'OSuccess'=> 'ORDER_SUCCESS', 'OExists' => 'EXISTS_ID', 'OPrint'  => 'PRINT', 'olup'    => 'LAST_UPDATE', 'OSid'    => 'OS_ID', 'OSStatu' => 'OS_STATUSE', 'OSMsgc'  => 'MSG_COUNT', 'OSMsgCS' => 'MSGCS', 'OInvo'   => 'INVO_ID', 'ZName'   => 'ZONE_NAME''SHIP_VAL'=> 'SHIPPING_VAL',)*/
            $che = $this->dataArr['osubid'][$row['order_id']]; // تنسيق بيانات امر البيع على حسب subid
            
            if( $che['olup'] >= $row['last_update'] && !$row['MSGCS'] ){ //التأكد من عدم تغير بيانات امر البيع فى الخلفية وايضا التأكد من عدم وجود طلبات من المستخدمين
                $orderidarr[] = $row['order_id'];
                $this->sub_invo_id = 'INV'.$_SESSION["USER_ID"].'-'.date('ymdhis').$row['order_id'];
                $value['orderData']['sub_invo'] = $this->sub_invo_id ;
                $approvedOID[$row['order_id']] = array(
                        'orderData' =>$value['orderData'],
                        'orderPro'  =>$value['orderPro']
                );
                foreach ($value['orderPro'] as $value2){
                    if(!isset($approvedPro[$value2['PRO_CODE']])){
                        $approvedPro[$value2['PRO_CODE']] = array(
                            'CAT_NAME'   => $value2['CAT_NAME'],
                            'PRO_CODE'   => $value2['PRO_CODE'],
                            'PRO_NAME'   => $value2['PRO_NAME']. " | " . implode($value2['OPTION_TEXT']),
                            'qty'        => $value2['qty'],
                            'SQTY'       => (isset($value2['SQTY']) && $value2['SQTY'] >= 0) ? $value2['SQTY']: 0
                        );
                    } else {
                        $approvedPro[$value2['PRO_CODE']]['qty'] += $value2['qty'];
                    }
                }
                $logStr  = 'تم تحديث بيانات امر البيع من '.$_SESSION["USER_GRUOP_NAME"];
                $logStr .= "* طباعة ملصق الشحن ";         
                $insertologtxt[]= '( '.$row['order_id'] .','.$_SESSION['USER_ID'].',"'.$logStr.'" )';
            } else {
                $rejectedOID[$row['order_id']] = $row;
            }
        }
        //print_r($insertologtxt);
        // إزالة الفاصلة الزائدة في نهاية العبارة
        $sql2 = implode(", ", $insertologtxt);
        $returnarray = array('UNPRINT'=>$rejectedOID, 'PRO'=>$approvedPro,'PRINT'=>$approvedOID);
       
        $retutext = "";
        try {
            $this->conn->beginTransaction();
            $stmt1 = $this->conn->prepare($this->Query('update_order'));
            $stmt1->bindValue(1, implode(",",$orderidarr), PDO::PARAM_STR);
            
            if (!$stmt1->execute()) {
                $this->conn->rollback();
                return array('e','خطاء',"خطاء اثناء تحديث بيانات امر البيع : Error update orders");
            }
            
            $stmt2 = $this->conn->prepare($this->Query('insert_olog').$sql2);
            
            if (!$stmt2->execute()) {
            	$this->conn->rollback();
            	return array('e','خطاء',"خطاء اثناء تحديث سجل حركة المستخدمين على امر البيع: Error insert order log");
			}
            
            $this->conn->commit();
            $retustat = 's';
            $retutext = "تم انشاء ملف ال PDF والتحويل بنجاح";
                
        } catch (PDOException $e) {
            $this->conn->rollback();
            $retustat = 'e';
            $retutext .= " حدث خطاء غير متوقع  : <pre> ".$e->getCode()." - ".$e->getMessage();
        }
        $title='ناجح';
        if($retustat == 'e'){
            $title="حدث خطاء";
        }
        return array($retustat,$title,$retutext,$returnarray);
        //echo "<pre>";
    }
    
}
