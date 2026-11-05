<?php
include_once(ABSPATH.'/inc/controller/DataAccessObject/PurchaseOrderDAO.php');
class purchases_BE {
    private static $DAO;
    private $conn;
    public function __construct($pdo) {
        $this->DAO = new PurchaseOrderDAO($pdo);
        $this->conn = $pdo;
    }
    // دالة لإضافة مرتجع الشراء ومنتجاته المرتبطة
    public function returnPurchaseOrder($supplierId, $reference, $totalPrice, $totalPayments, $status, $note, $products, $returnOrderID = false) {
	    try {
	        // بدء المعاملة إذا لم تبدأ
	        if (!$returnOrderID && !$this->DAO->pdo->inTransaction()) {
	            $this->DAO->pdo->beginTransaction();
	            $this->DAO->pdo->exec("SAVEPOINT $startpoint");
	        }
	
	        // إدخال أمر الشراء
	        $orderId = $this->DAO->insertReturnOrder($supplierId, $reference, $totalPrice, $totalPayments, $status, $note, $products);
	
	        // التحقق من نجاح العملية
	        if (!$orderId) {
	            throw new Exception("Error: Failed to insert purchase order.");
	        }

	        // تثبيت المعاملة
	        if (!$returnOrderID && $this->DAO->pdo->inTransaction()) {
			    $this->DAO->pdo->commit();
			}
	
	        // إرجاع النتيجة بناءً على الخيار
	        return !$returnOrderID
	            ? array("s", "نجح", "تم حفظ مرتجع أمر الشراء بنجاح!", $orderId)
	            : $orderId;
	
		} catch (PDOException $e) {
		    if ($this->DAO->pdo->inTransaction()) {
		        $this->DAO->pdo->rollBack();
		    }
		    return array('error', 'خطأ', 'حدث خطأ أثناء تنفيذ عملية الحفظ: ' . $e->getMessage(), []);
		} catch (Exception $e) {
		    if ($this->DAO->pdo->inTransaction()) {
		        $this->DAO->pdo->rollBack();
		    }
		    // إرجاع أو رمي الاستثناء بناءً على الخيار
	        if ($returnOrderID) {
	            throw $e;
	        } else {
	            return array('e', "فشلت المعاملة", $e->getMessage());
	        }
		}
	}
	// دالة لإضافة امر الشراء ومنتجاته المرتبطة
	public function addPurchaseOrder($supplierId, $reference, $totalPrice, $totalPayments, $status, $note, $products, $returnOrderID = false) {
	    try {
	        // بدء المعاملة إذا لم تبدأ
	        if (!$returnOrderID && !$this->DAO->pdo->inTransaction()) {
	            $this->DAO->pdo->beginTransaction();
	            $this->DAO->pdo->exec("SAVEPOINT $startpoint");
	        }
	
	        // إدخال أمر الشراء
	        $orderId = $this->DAO->insertOrder($supplierId, $reference, $totalPrice, $totalPayments, $status, $note, $products);
	
	        // التحقق من نجاح العملية
	        if (!$orderId) {
	            throw new Exception("Error: Failed to insert purchase order.");
	        }

	        // تثبيت المعاملة
	        if (!$returnOrderID && $this->DAO->pdo->inTransaction()) {
			    $this->DAO->pdo->commit();
			}
	
	        // إرجاع النتيجة بناءً على الخيار
	        return !$returnOrderID
	            ? array("s", "نجح", "تم حفظ أمر الشراء بنجاح!", $orderId)
	            : $orderId;
	
		} catch (PDOException $e) {
		    if ($this->DAO->pdo->inTransaction()) {
		        $this->DAO->pdo->rollBack();
		    }
		    return array('error', 'خطأ', 'حدث خطأ أثناء تنفيذ عملية الحفظ: ' . $e->getMessage(), []);
		} catch (Exception $e) {
		    if ($this->DAO->pdo->inTransaction()) {
		        $this->DAO->pdo->rollBack();
		    }
		    // إرجاع أو رمي الاستثناء بناءً على الخيار
	        if ($returnOrderID) {
	            throw $e;
	        } else {
	            return array('e', "فشلت المعاملة", $e->getMessage());
	        }
		}
	}
	// دالة لتحديث بيانات ومنتجات امر البيع
	public function updatePurchaseOrder($orderId, $comment, $products, $self = true) {
	    try {
	        // بدء معاملة إذا لم تكن موجودة
	        if (!$this->DAO->pdo->inTransaction()) {
	            $this->DAO->pdo->beginTransaction();
	        }
	
	        // تحديث المنتجات
	        $result = $this->DAO->updateRecProducts($products);
	       
	        if ($result['result']) {
	            // تثبيت المعاملة
	            if (!$this->DAO->pdo->inTransaction()) {
	            	$this->DAO->pdo->commit();
	            	return array("s", "نجح", "تم حفظ أمر الشراء بنجاح!", $orderId);
	            }
	            return $result;
	        } else {
	            // في حالة فشل العملية
	            throw new Exception("Error: Failed to update purchase order.");
	        }
	    } catch (PDOException $e) {
	        // التراجع عن المعاملة إذا كانت نشطة
	        if ($this->DAO->pdo->inTransaction()) {
	            $this->DAO->pdo->rollBack();
	        }
	
	        if (!$self) {
	            throw $e;
	        }
	        return array('error', 'خطأ', 'حدث خطأ أثناء تنفيذ عملية الحفظ: ' . $e->getMessage(), []);
	    } catch (Exception $e) {
	        // التراجع عن المعاملة إذا كانت نشطة
	        if ($this->DAO->pdo->inTransaction()) {
	            $this->DAO->pdo->rollBack();
	        }
	
	        if (!$self) {
	            throw $e;
	        }
	        return array('error', 'خطأ', 'حدث خطأ: ' . $e->getMessage(), []);
	    }
	}
	//عرض قائمة بأوامر الشراء
	public function listPurchasesDT ($request,$where=[]) {
		return $this->DAO->getPurchaseData($request,$where);
	}
}
?>