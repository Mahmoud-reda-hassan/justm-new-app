<?php
/*================================================
**  Countries Table From Database
**==============================================*/
class Stores_tbl {
	private $storesTbl 	= 'STORES';
	private $sttyTbl 	= 'STORES_TYPE';
	public  $sto_id;
    public  $branch_id;
    public  $sto_name;
    public  $stty_id;
    public  $filtertype;
	
	private $conn;
	private $UserScript;
	public function __construct($db){
        $this->conn = $db;
        $this->UserScript = unserialize($_SESSION['USER_SCRIPT'])['Admin'];
    }
	public function listWarehouse(){
		$sqlQuery = 'SELECT A.STO_ID,
							D.COM_ID,
							D.COM_NAME,
							A.BRANCH_ID,
							B.BR_NAME,
							A.STO_NAME,
							A.STTY_ID,
							C.STTY_NAME,
							C.STTY_DESCRIPTION,
							E.CHECK_QTY,
							E.CHECK_ORDER
					FROM ' . $this->storesTbl . ' AS A 
						LEFT JOIN  BRANCHES AS B
							ON A.BRANCH_ID = B.BR_ID
						LEFT JOIN STORES_TYPE AS C 
							ON A.STTY_ID = C.STTY_ID
						LEFT JOIN COMPANIES AS D
							ON B.COMPANIE_ID = D.COM_ID
						LEFT JOIN (SELECT A.STO_ID,
                                        SUM(CASE WHEN A.STO_QTY <> 0 THEN 1 ELSE 0 END)  AS CHECK_QTY,
										SUM(CASE WHEN C.ORDER_ID IS NOT NULL THEN 1 ELSE 0 END)  AS CHECK_ORDER
                                    FROM  STORES_PRODUCT A
                                    LEFT JOIN ORDER_PRODUCT B
                                        ON A.PRO_CODE = B.PRO_CODE
									LEFT JOIN ORDERS C 
										ON A.STO_ID=C.STO_ID
                                    GROUP BY A.STO_ID) E
                            ON A.STO_ID = E.STO_ID';
		if(!empty($_GET["search"]["value"])){
			$sqlQuery .= ' WHERE (D.COM_NAME     LIKE   "%'.$_GET["search"]["value"].'%" ';
			$sqlQuery .= ' OR B.BR_NAME       LIKE     "%'.$_GET["search"]["value"].'%" ';
			$sqlQuery .= ' OR A.STO_NAME    LIKE  "%'.$_GET["search"]["value"].'%" ';
			$sqlQuery .= ' OR C.STTY_NAME LIKE      "%'.$_GET["search"]["value"].'%") ';
		}
		$columnsArray =['A.STO_ID', 'A.STO_NAME','D.COM_NAME','C.STTY_NAME'];
		if(!empty($_GET["order"])){
			$sqlQuery .= ' ORDER BY '.$columnsArray[$_GET['order']['0']['column']].' '.strtoupper($_GET['order']['0']['dir']).' ';
		} else {
			$sqlQuery .= ' ORDER BY A.STO_ID DESC ';
		}
		
		if(!empty($_GET["length"]) && $_GET["length"] != -1){
			$sqlQuery .= ' LIMIT ' . $_GET['start'] . ' , ' . $_GET['length'] . ' ' ;
		}
		
		$stmt = $this->conn->prepare($sqlQuery);
		$stmt->execute();
		$result = $stmt;
		
		$stmtTotal = $this->conn->prepare("SELECT * FROM ".$this->storesTbl);
		$stmtTotal->execute();
		$allResult = $stmtTotal;
		$allRecords = $allResult->rowCount();
	    $displayRecords = $result->rowCount();
		$records = array();
		while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
			$rows = array();
			if(intval($record['CHECK_ORDER']) == 0 && intval($record['CHECK_QTY']) == 0){
			    $rows[] = '<input id="'.$record['STO_ID'].'" type="checkbox">';
			} else {
			    $rows[] = '#';
			}
			$rows[] = $record['STO_NAME'];
			$rows[] = $record['BR_NAME'] . ' - ' . $record['COM_NAME'];
			$rows[] = $record['STTY_NAME'];
			$rows[] = "<button 
						id='btnNew' 
						type='button' 
						data-id   ='". $record["STO_ID"]."' 
						data-STO_NAME ='". $record["STO_NAME"]."'  
						data-STTY_ID  ='". $record["STTY_ID"] ."' 
						data-COM_ID   ='". $record["COM_ID"] ."' 
						data-BRANCH_ID='". $record["BRANCH_ID"] ."' 
						class='btn btn-primary btn-xs btnEdit'><i class='fas fa-align-center fa-edit'></i></button>";

			$records[] = $rows;
		}
		if(isset($_GET["draw"])){
			$draw = intval($_GET["draw"]);
		}else{
			$draw = 0;
		}
		$output = array(
			"draw"	                =>	$draw,
			"iTotalRecords"	        =>	$displayRecords,
			"iTotalDisplayRecords"	=>  $allRecords,
			"data"	                => 	$records
		);
		echo json_encode($output);
	}
	public function addWarehouse(){
    	if( !empty($this->sto_name) 	&& 
			!empty($this->branch_id) 	&& 
			!empty($this->stty_id)) {
	            $sqlQuery1 = " SELECT * FROM ".$this->storesTbl." WHERE BRANCH_ID = ? AND STTY_ID = 2  ; ";
				$sqlQuery2 = "INSERT INTO " . $this->storesTbl . " (`STO_NAME`, `BRANCH_ID`, `STTY_ID`) VALUES (? , ? , ? )";
				$sqlQuery3 = "INSERT INTO `STORES_PRODUCT`(`STO_ID`, `PRO_CODE`, `STO_QTY`) SELECT A.STO_ID, A.PRO_CODE, A.STO_QTY FROM  (SELECT B.STO_ID,A.PRO_CODE, 0 AS STO_QTY FROM  PRODUCT_CODES A JOIN STORES B) A LEFT JOIN STORES_PRODUCT B ON A.STO_ID = B.STO_ID  AND A.PRO_CODE = B.PRO_CODE WHERE B.PRO_CODE IS NULL";
				$this->sto_name  = htmlspecialchars(strip_tags(trim($this->sto_name	," ")));
				$this->branch_id = htmlspecialchars(strip_tags(trim($this->branch_id," ")));
				$this->stty_id   = htmlspecialchars(strip_tags(trim($this->stty_id	," ")));				
				//$this->br_address  	= htmlspecialchars(strip_tags(trim($this->br_address	," ")));
				try{
					$this->conn->beginTransaction();
					$stmt1 = $this->conn->prepare($sqlQuery1);
					$stmt1->bindValue(1,$this->branch_id, 	PDO::PARAM_STR);
					if($stmt1->execute()){
					    if($stmt1->rowCount() < 1){
					        $this->stty_id   = 2;
				        } else {
				            $this->stty_id   = 1;
				        }
						$stmt2 = $this->conn->prepare($sqlQuery2);
						$stmt2->bindValue(1,$this->sto_name, 	PDO::PARAM_STR);
						$stmt2->bindValue(2,$this->branch_id, 	PDO::PARAM_STR);
						$stmt2->bindValue(3,$this->stty_id, 	PDO::PARAM_STR);
						if($stmt2->execute()) {
							$stmt3 = $this->conn->prepare($sqlQuery3);
							if ($stmt3->execute()) {
								if($stmt3->rowCount()> 0){
									$this->conn->commit();
									return json_encode(array('s','تم حفظ البيانات بنجاح'));
								} else {
									return json_encode(array('e','حدث خطاء لم يتم تأكيد حفظ البيانات من الخادم'));
									$this->conn->rollback();
								}
							} else {
								return json_encode(array('e','حدث خطاء لم يتم تأكيد حفظ البيانات من الخادم'));
								$this->conn->rollback();
							}
						}else{
							return json_encode(array('e','حدث خطاء لم يتم تأكيد حفظ البيانات من الخادم'));
							$this->conn->rollback();
						}
					} else {
						return json_encode(array('e','حدث خطاء لم يتم تأكيد حفظ البيانات من الخادم'));
						$this->conn->rollback();
					}
				} catch (PDOException $e) {
					return json_encode(array('e','حدث خطاء  - رسالة الخطاء: '.$e->getMessage(),$e->getCode()));
					$this->conn->rollback();
				}
		}	
	}
	public function updateWarehouse(){
		if( !empty($this->sto_id)     &&
		    !empty($this->sto_name) 	&& 
			!empty($this->branch_id) 	&& 
			!empty($this->stty_id)) {
			    $sqlQuery0 ="SELECT BRANCH_ID,
                            sum(case when STTY_ID =1 and STO_ID <> ? THEN 1 ELSE 0 END) as ST_NORMAL,
                            sum(case when STTY_ID =2 and STO_ID <> ? THEN 1 ELSE 0 END) as ST_SELSE
                            FROM  stores 
                            WHERE BRANCH_ID = ?
                            GROUP BY BRANCH_ID";
	            $sqlQuery1 ="SELECT  A.STO_ID,A.STTY_ID,
                              Sum(CASE
                              WHEN B.STO_ID IS NOT NULL
                              THEN 1
                              ELSE 0
                                END) AS ORDERS
                            FROM   stores A
                              LEFT JOIN orders B
                              ON A.STO_ID = B.STO_ID
                            WHERE  A.STO_ID = ?
                            GROUP BY A.STO_ID,A.STTY_ID ";
                $sqlQuery2 = "UPDATE " . $this->storesTbl . " SET STO_NAME = ?, BRANCH_ID = ?, STTY_ID = ?  WHERE STO_ID = ? ;";
				$this->sto_name  = htmlspecialchars(strip_tags(trim($this->sto_name	," ")));
				$this->branch_id = htmlspecialchars(strip_tags(trim($this->branch_id," ")));
				$this->stty_id   = htmlspecialchars(strip_tags(trim($this->stty_id	," ")));				
				$this->sto_id    = htmlspecialchars(strip_tags(trim($this->sto_id	," ")));
				try{
					$stmt0 = $this->conn->prepare($sqlQuery0);
					$stmt0->bindValue(1,$this->sto_id, 	 PDO::PARAM_STR);
                    $stmt0->bindValue(2,$this->sto_id, 	 PDO::PARAM_STR);
					$stmt0->bindValue(3,$this->branch_id,PDO::PARAM_STR);
					$stmt0->execute();
					$rows = array();
					while ($record = $stmt0->fetch(PDO::FETCH_ASSOC)) { 
			            $rows['ST_NORMAL'] = $record['ST_NORMAL'];
			            $rows['ST_SELSE']  = $record['ST_SELSE'];
			        }
			        //echo $this->stty_id ."-".$rows['ST_SELSE'];
					if(($this->stty_id == 2 && $rows['ST_SELSE'] == 0) || ($this->stty_id == 1 && $rows['ST_SELSE'] >=0) ){	
					    $stmt1 = $this->conn->prepare($sqlQuery1);
    					$stmt1->bindValue(1,$this->sto_id, 	 PDO::PARAM_STR);
    					$stmt1->execute();
    					$rows = array();
    					while ($record = $stmt1->fetch(PDO::FETCH_ASSOC)) { 
    			            $rows['ORDERS'] = $record['ORDERS'];
    			            $rows['STTY_ID']  = $record['STTY_ID'];
    			        }
                        if(isset($this->stty_id) && $this->stty_id !=2 && isset($rows['STTY_ID']) && $rows['STTY_ID'] == 2 && $rows['ORDERS'] >0){
    					    echo json_encode(array('e','لايمكن تغير نوع المخزن .. هناك اوامر بيع مرتبطه بالمخزن الحالى',10002));
                        }else{
                            $this->conn->beginTransaction();
                            $stmt2 = $this->conn->prepare($sqlQuery2);
    						$stmt2->bindValue(1,$this->sto_name, 	PDO::PARAM_STR);
    						$stmt2->bindValue(2,$this->branch_id, 	PDO::PARAM_STR);
    						$stmt2->bindValue(3,$this->stty_id, 	PDO::PARAM_STR);
    						$stmt2->bindValue(4,$this->sto_id, 	    PDO::PARAM_STR);
    						if($stmt2->execute()) {
    						    echo json_encode(array('s','تم  تحديث البيانات بنجاح'));
    							$this->conn->commit();
    						}else{
    							echo json_encode(array('e','حدث خطاء لم يتم تأكيد حفظ البيانات من الخادم'));
    							$this->conn->rollback();
    						}
						}
				    } else {
						echo json_encode(array('w','لا يمكن تعين اكتر من مخزن بيع مباشر واحد لنفس الفرع',10001));
					}
				} catch (PDOException $e) {
					echo json_encode(array('e','حدث خطاء  - رسالة الخطاء: '.$e->getMessage(),$e->getCode()));
				    $this->conn->rollback();
				}
		}	
	}
	public function deleteWarehouse(){
		if(!empty($this->sto_id)) {
			$sqlQuery = "DELETE A FROM "  . $this->storesTbl . " A
							LEFT JOIN (SELECT A.STO_ID,
                                        Sum(CASE WHEN A.STO_QTY <> 0 THEN 1 ELSE 0 END)  AS CHECK_QTY,
                                        Count(B.ORDER_ID) AS CHECK_ORDER
                                    FROM  STORES_PRODUCT A
                                    LEFT JOIN ORDER_PRODUCT B
                                        ON A.PRO_CODE = B.PRO_CODE
                                    GROUP BY A.STO_ID) B
								ON A.STO_ID = B.STO_ID 
							WHERE B.CHECK_QTY = 0 AND FIND_IN_SET (A.STO_ID , ? ) ;";
			$this->sto_id    = htmlspecialchars(strip_tags(trim($this->sto_id	," ")));
			try{
				$this->conn->beginTransaction();
				$stmt = $this->conn->prepare($sqlQuery);
				$stmt->bindValue(1,$this->sto_id, 	    PDO::PARAM_STR);
				if($stmt->execute()){
					echo json_encode(array('s','تم حذف البيانات بنجاح'));
					$this->conn->commit();
				} else {
					echo json_encode(array('e','حدث خطاء لم يتم تأكيد حفظ البيانات من الخادم'));
					$this->conn->rollback();
				}
			} catch (PDOException $e) {
					echo json_encode(array('e','حدث خطاء  - رسالة الخطاء: '.$e->getMessage(),$e->getCode()));
			}
			
		}
	}
	public function getdropWarehouse($select2Data = false){
		$where = [];
		$sqlQuery = "SELECT A.STO_ID,
		                    A.STTY_ID,
		                    B.BR_NAME,
		                    A.STO_NAME,
		                    C.STTY_NAME
		            FROM " . $this->storesTbl . " AS A 
						LEFT JOIN  BRANCHES AS B
							ON A.BRANCH_ID = B.BR_ID
						LEFT JOIN STORES_TYPE AS C 
							ON A.STTY_ID = C.STTY_ID ";
			if(isset($this->UserScript['UserSpecificPermissions']) && !$this->UserScript['UserSpecificPermissions']['viewAllStores']){
				$where[]= " B.BR_ID = ".$_SESSION['BRANCH_ID'];
			}
			if(isset($this->filtertype) && !empty($this->filtertype) && $this->filtertype > 1){
		    	$this->stty_id = 2;
		    }
			if(!empty($this->stty_id)) {
		        $where[]= " FIND_IN_SET (A.STTY_ID , ? ) ";
		    }
		    if(count($where)){
		    	$sqlQuery .= 'WHERE ' .implode(' AND ',$where);
		    }
			$stmt = $this->conn->prepare($sqlQuery);
		    
		    if(isset($this->stty_id) && !empty($this->stty_id)) {
			    $this->stty_id = htmlspecialchars(strip_tags($this->stty_id));
			    $stmt->bindValue(1, $this->stty_id , PDO::PARAM_STR);
				
		    }
		   
			$stmt->execute();
			$result = $stmt;
			$records = array();
			if(!$select2Data){
				while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
				    $rows = array();
				    $rows[] = $record['STO_ID']; 
				    $rows[] = $record['STO_ID']; 
				    $rows[] = $record['STO_NAME'] .' - الفرع '.$record['BR_NAME'] .'('.$record['STTY_NAME'].')';
				    $records[] = $rows;
			    }
			} else {
				while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
				    $rows = array();
				    $rows['ID'] = $record['STO_ID']; 
				    $rows['NAME'] = $record['STO_NAME'] .' - الفرع '.$record['BR_NAME'] .'('.$record['STTY_NAME'].')';
				    $records[] = $rows;
			    }
			}
			
		    
			echo json_encode($records);
	}
	public function getdropStoresType(){
		$sqlQuery = "SELECT * FROM " . $this->sttyTbl;
			if(!empty($this->stty_id)) {
		        $sqlQuery .= " WHERE FIND_IN_SET (STTY_ID , ? ) ";
		    }
			$stmt = $this->conn->prepare($sqlQuery);
		    if(!empty($this->stty_id)) {
			    $this->stty_id = htmlspecialchars(strip_tags($this->stty_id));
			    $stmt->bindValue(1, $this->stty_id , PDO::PARAM_STR);
				
		    }
			$stmt->execute();
			$result = $stmt;
			$records = array();
			while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
			    $rows = array();
			    $rows[] = $record['STTY_ID']; 
			    $rows[] = $record['STTY_ID']; 
			    $rows[] = $record['STTY_NAME'];
			    $records[] = $rows;
		    }
			echo json_encode($records);
	}
}
?>