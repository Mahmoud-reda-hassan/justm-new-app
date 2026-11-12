<?php

include_once(ABSPATH.'/inc/controller/DataAccessObject/SupplyOrderDAO.php');
class purchases_BE {
    private static $DAO;
    public function __construct($pdo) {
        $this->DAO = new SupplyOrderDAO($pdo);
    }

    // دالة لإضافة طلب التوريد ومنتجاته المرتبطة
    public function addSupplyOrder($orderBy, $status, $notes, $products, $returnOrderID = false) {
		
        // تنفيذ الإدخال داخل معاملة (Transaction)
        try {
        	if (!$this->DAO->pdo->inTransaction()) {
			    $this->DAO->pdo->beginTransaction();
			}
            $orderId = insertOrder($orderBy, $status, $notes, $products);
            if($orderId){
				if (!$this->DAO->pdo->inTransaction()) {
	            	$this->DAO->pdo->commit(); // تثبيت المعاملة
				}
	            if(!$returnOrderID){
	            	return array("s","نجح","تم حفظ امر الشراء بنجاح!",$orderId); // إرجاع رسالة بنجاح
	            }else{
	            	return $orderId;
	            }
            }else{
            	throw new Exception('حدث خطاء لم يتم حفظ طلب التوريد لاسباب غير معروفه');
            }
        } catch (Exception $e) {
            $this->DAO->pdo->rollBack(); // التراجع عن المعاملة في حالة خطأ
            if(!$returnOrderID){
            	return array('e',"فشلت المعاملة", $e->getMessage());
            }else{
            	throw new Exception($e->getMessage());
            	return false;
            }
        }
    }
	
	// دالة لتحديث بيانات ومنتجات امر البيع
	public function updatePurchaseOrder($orderId,$comment,$products) {
		
        // تنفيذ الإدخال داخل معاملة (Transaction)
        try {
        	if (!$this->DAO->pdo->inTransaction()) {
			    $this->DAO->pdo->beginTransaction();
			}
            $result = $this->DAO->updateRecProducts($products);
            if($result[0] =='s'){
				if(!$this->DAO->pdo->inTransaction()){
	            	$this->DAO->pdo->commit(); // تثبيت المعاملة
				}
	            return array("s","نجح","تم حفظ امر الشراء بنجاح!",$orderId); // إرجاع رسالة بنجاح
	            
            }else{
            	return array("e","خطاء","حدث خطاء اثناء الحفظ",$orderId); // إرجاع رسالة بنجاح
            	
            }
        } catch (Exception $e) {
            $this->DAO->pdo->rollBack(); // التراجع عن المعاملة في حالة خطأ
           	return array('e',"فشلت المعاملة", $e->getMessage());
        }
    }
	 
	//عرض قائمة بأوامر الشراء
	public function listPurchasesDT($request,$where=[]) {
		return $this->DAO->getPurchaseData($request,$where);
	}
	
}


?>