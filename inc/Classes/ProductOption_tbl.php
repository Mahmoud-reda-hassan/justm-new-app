<?php
/*================================================
**  ProductOption Table From Database
**==============================================*/
class ProductOption_tbl {
	private $product_option_groupTbl = 'PRODUCT_OPTION_GROUP';
	private $product_optionTbl = 'PRODUCT_OPTION';
	private $product_code_join_optionTbl = 'PRODUCT_CODE_JOIN_OPTION';
	private $product_join_option_groupTbl = 'PRODUCT_JOIN_OPTION_GROUP';
	public $option_id;
	public $product_id;
    public $option_name;
    public $option_type;
	public $option_value_id;
    public $option_value;
	private $conn;
	public function __construct($db){
        $this->conn = $db;
    }
	public function listProductOption(){
		$sqlQuery = 'SELECT A.OPTION_ID, 
							A.OPTION_NAME, 
							B.VALUE ,
							B.ID AS OPTION_VALUE_ID,
							A.OPTION_TYPE ,
							COUNT(C.OPTION_VALUE_ID) AS COUNT_CODE_PRODUCT
					FROM PRODUCT_OPTION_GROUP  AS A 
						LEFT JOIN PRODUCT_OPTION AS B
							ON A.OPTION_ID = B.OPTION_ID 
						LEFT JOIN PRODUCT_CODE_JOIN_OPTION AS C
							ON B.ID = C.OPTION_VALUE_ID
						  ';
		    if(!empty($_GET["search"]["value"])){
			    $sqlQuery .= ' WHERE (A.OPTION_ID LIKE "%'.$_GET["search"]["value"].'%" ';
			    $sqlQuery .= ' OR A.OPTION_NAME LIKE "%'.$_GET["search"]["value"].'%" ';
			    $sqlQuery .= ' OR B.VALUE LIKE "%'.$_GET["search"]["value"].'%") ';
		    }
			// Group by sqlQuery
		    $sqlQuery .= ' GROUP BY A.OPTION_ID ,A.OPTION_NAME ,B.VALUE,B.ID ,A.OPTION_TYPE ';
	        // Order by sqlQuery
	        if(!empty($_GET["order"])){
	        	$columnsArray = ['A.OPTION_ID', 'A.OPTION_NAME', 'B.VALUE'];
			    $sqlQuery .= ' ORDER BY '.$columnsArray[$_GET['order']['0']['column']].' '.$_GET['order']['0']['dir'].' ';
		    } else {
			    $sqlQuery .= ' ORDER BY A.OPTION_ID DESC ';
		    }
	        // LIMIT data sqlQuery
		    if(!empty($_GET["length"]) && $_GET["length"] != -1){
			    $sqlQuery .= ' LIMIT ' . $_GET['start'] . ', ' . $_GET['length'] . ' ' ;
		    }
		    $stmt = $this->conn->prepare($sqlQuery);
		    $stmt->execute();
		    $result = $stmt;
		
		    $stmtTotal = $this->conn->prepare('SELECT B.OPTION_ID FROM '. $this->product_option_groupTbl .' AS A LEFT JOIN '. $this->product_optionTbl .' AS B ON A.OPTION_ID = B.OPTION_ID ' );
		    $stmtTotal->execute();
		    $allResult = $stmtTotal;
		    $allRecords = $allResult->rowCount();
		
	        $displayRecords = $result->rowCount();
		    $records = array();
		    while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
			    $rows = array();
			if($record['COUNT_CODE_PRODUCT'] <=0){
			        if(!empty($record['OPTION_ID'])){
						$rows[] = '<input id="'.$record['OPTION_ID'].'" option_value_id="'.$record['OPTION_VALUE_ID'].'" type="checkbox">';
					} else {
						$rows[] = '<input id="'.$record['OPTION_ID'].'" option_value_id="0" type="checkbox">';
					}
			} else {
			        $rows[] = '#';
			}
			    $rows[] = $record['OPTION_NAME'];
			    $rows[] = $record['VALUE'];
			    $rows[] = "<button id='btnNew' type='button' 
							data-id='".$record["OPTION_ID"]."' 
							data-OPTION_VALUE_id='".$record["OPTION_VALUE_ID"]."' 
							data-option_name='".$record["OPTION_NAME"]."' 
							data-option_value='".$record["VALUE"]."'  class='btn btn-primary btn-xs btnEdit'><i class='fas fa-align-center fa-edit'></i></button>";
			    $records[] = $rows;
		    }
		
		    $output = array(
			    "draw"	                =>	intval($_GET["draw"]),
			    "iTotalRecords"	        =>	$displayRecords,
			    "iTotalDisplayRecords"	=>  $allRecords,
			    "data"	                => 	$records
		    );
		    echo json_encode($output);
	}
    public function addProductOptionGroup(){
        if( !empty($this->option_name) 	&& 
			!empty($this->option_type)) {

			$sqlQuery = ' INSERT INTO '.$this->product_option_groupTbl.'(OPTION_NAME , OPTION_TYPE) VALUES (?,?) ';
			$this->option_name  = htmlspecialchars(strip_tags(trim($this->option_name," ")));
            $this->option_type  = htmlspecialchars(strip_tags(trim($this->option_type," ")));
            try{
				$this->conn->beginTransaction();
				$stmt = $this->conn->prepare($sqlQuery);
				$stmt->bindValue(1,$this->option_name, 	PDO::PARAM_STR);
			    $stmt->bindValue(2,$this->option_type, 	PDO::PARAM_INT);
				if($stmt->execute()){
					echo json_encode($stmt->rowCount());
					$this->conn->commit();
				} else {
					$this->conn->rollback();
				}
            } catch (PDOException $e) {
				echo json_encode('error'.$e->getCode()); //.$e->getMessage();
				$this->conn->rollback();
			}
		}
    }
	public function addProductOptionValue(){
        if( !empty($this->option_id) && 
			!empty($this->option_value)) {

			$sqlQuery = ' INSERT INTO '.$this->product_optionTbl.'(OPTION_ID , VALUE) VALUES (?,?) ';
            $this->option_id     = htmlspecialchars(strip_tags(trim($this->option_id	," ")));
            $this->option_value  = htmlspecialchars(strip_tags(trim($this->option_value	," ")));
            try{
                $this->conn->beginTransaction();
				$stmt = $this->conn->prepare($sqlQuery);
                $stmt->bindValue(1,$this->option_id, 	PDO::PARAM_INT);
			    $stmt->bindValue(2,$this->option_value,	PDO::PARAM_STR);
		        if($stmt->execute()){
					echo json_encode($stmt->rowCount());
					$this->conn->commit();
				} else {
					$this->conn->rollback();
				}
            } catch (PDOException $e) {
				echo json_encode('error'.$e->getCode()); //.$e->getMessage();
				$this->conn->rollback();
			}
		}
    }
	public function updateProductOptionGroup(){
        if( !empty($this->option_id) 	&& 
			!empty($this->option_name) 	&& 
			!empty($this->option_type)) {
			
			$sqlQuery  = ' UPDATE '.$this->product_option_groupTbl.' SET OPTION_NAME = ? , OPTION_TYPE = ?  WHERE OPTION_ID = ?  ';
			$sqlQuery1 = ' SELECT A.PRODUCT_CODE
							FROM PRODUCT_CODE_JOIN_OPTION A 
								LEFT JOIN PRODUCT_OPTION B 
									ON A.OPTION_VALUE_ID = B.ID 
								LEFT JOIN PRODUCT_OPTION_GROUP C 
									ON B.OPTION_ID = C.OPTION_ID
							WHERE C.OPTION_ID = ? 
							GROUP BY A.PRODUCT_CODE ';
			$this->option_id  = htmlspecialchars(strip_tags(trim($this->option_id," ")));
			$this->option_name  = htmlspecialchars(strip_tags(trim($this->option_name," ")));
            $this->option_type  = htmlspecialchars(strip_tags(trim($this->option_type," ")));
			try{
				$this->conn->beginTransaction();
				$stmt1 = $this->conn->prepare($sqlQuery1);
				$stmt1->bindValue(1,$this->option_id, 	PDO::PARAM_INT);
				if($stmt1->execute()){
					if($stmt1->rowCount() < 1){
						$stmt = $this->conn->prepare($sqlQuery);
						$stmt->bindValue(1,$this->option_name, 	PDO::PARAM_STR);
						$stmt->bindValue(2,$this->option_type, 	PDO::PARAM_INT);
						$stmt->bindValue(3,$this->option_id, 	PDO::PARAM_INT);
						if($stmt->execute()){
							echo json_encode($stmt->rowCount());
							$this->conn->commit();
						} else {
							$this->conn->rollback();
						}
					} else {
						echo json_encode("لايمكن تعديل او حذف هذا البيان هناك عدد ( ". $stmt1->rowCount() ." ) من المنتجات مرتبطة بهاذا البيان ");
						$this->conn->rollback();
					}
				} else {
				    echo json_encode("لم ننجح فى تنفيذ هذا الاجراء .. حدث خطاء غير معروف");
				    $this->conn->rollback();
				}
            } catch (PDOException $e) {
				echo json_encode('error'.$e->getCode().$e->getMessage());
				$this->conn->rollback();
			}
		}
    }
	public function updateProductOptionValue(){
        if( !empty($this->option_value_id) &&
			!empty($this->option_value)) {
			$sqlQuery  = ' UPDATE '.$this->product_optionTbl.' SET VALUE = ? WHERE ID = ? ';
            $sqlQuery1 = ' SELECT A.PRODUCT_CODE 
							FROM PRODUCT_CODE_JOIN_OPTION A 
								LEFT JOIN PRODUCT_OPTION B 
									ON A.OPTION_VALUE_ID = B.ID 
							WHERE B.ID = ? 
							GROUP BY A.PRODUCT_CODE ';
            $this->option_value_id     = htmlspecialchars(strip_tags(trim($this->option_value_id	," ")));
            $this->option_value        = htmlspecialchars(strip_tags(trim($this->option_value	    ," ")));
            try{
                $this->conn->beginTransaction();
                $stmt1 = $this->conn->prepare($sqlQuery1);
				$stmt1->bindValue(1,$this->option_value_id, 	PDO::PARAM_INT);
				if($stmt1->execute()){
					if($stmt1->rowCount() < 1){
				        $stmt = $this->conn->prepare($sqlQuery);
                        $stmt->bindValue(1,$this->option_value, 	PDO::PARAM_STR);
			            $stmt->bindValue(2,$this->option_value_id,	PDO::PARAM_INT);
		                if($stmt->execute()){
					        echo json_encode($stmt->rowCount());
					        $this->conn->commit();
				        } else {
					        $this->conn->rollback();
				        }
			    	} else {
						echo json_encode("لايمكن تعديل او حذف هذا البيان هناك عدد ( ". $stmt1->rowCount() ." ) من المنتجات مرتبطة بهاذا البيان ");
						$this->conn->rollback();
					}
				} else {
				    echo json_encode("لم ننجح فى تنفيذ هذا الاجراء .. حدث خطاء غير معروف");
				    $this->conn->rollback();
				}
            } catch (PDOException $e) {
				echo json_encode('error'.$e->getCode().$e->getMessage());
				$this->conn->rollback();
			}
		}
    }
	public function deleteProductOptionGroup(){
        if( !empty($this->option_id)) {
			$sqlQuery  = ' 	DELETE FROM '.$this->product_option_groupTbl.' WHERE  OPTION_TYPE <> 0 AND FIND_IN_SET (OPTION_ID , ? ) ';
            $sqlQuery1 = '  SELECT B.OPTION_ID
							FROM PRODUCT_OPTION B 
								LEFT JOIN PRODUCT_OPTION_GROUP C 
									ON B.OPTION_ID = C.OPTION_ID
							WHERE FIND_IN_SET (C.OPTION_ID , ? )
							GROUP BY B.OPTION_ID ';
            $this->option_id     = htmlspecialchars(strip_tags(trim($this->option_id	," ")));
            try{
                $this->conn->beginTransaction();
                $stmt1 = $this->conn->prepare($sqlQuery1);
				$stmt1->bindValue(1,$this->option_id, 	PDO::PARAM_STR);
				if($stmt1->execute()){
					if($stmt1->rowCount() < 1){
				        $stmt = $this->conn->prepare($sqlQuery);
                        $stmt->bindValue(1,$this->option_id, 	PDO::PARAM_STR);
		                if($stmt->execute()){
					        echo json_encode($stmt->rowCount());
					        $this->conn->commit();
				        } else {
					        $this->conn->rollback();
				        }
			    	} else {
						echo json_encode("هذة الصفة لها قيم مسجلة ولايمكن حذفها .. قم بحذف القيم اولاً ثم احذف الصفة مره اخرى");
						$this->conn->rollback();
					}
				} else {
				    echo json_encode("لم ننجح فى تنفيذ هذا الاجراء .. حدث خطاء غير معروف");
				    $this->conn->rollback();
				}
            } catch (PDOException $e) {
				echo json_encode('error'.$e->getCode() . ' - ' .$e->getMessage());
				$this->conn->rollback();
			}
		}
    }
	public function deleteProductOptionValue(){
        if( !empty($this->option_value_id)) {
			$sqlQuery  = ' 	DELETE FROM '.$this->product_optionTbl.' WHERE FIND_IN_SET (ID , ? ) ';
            $sqlQuery1 = '  SELECT A.PRODUCT_CODE
							FROM PRODUCT_CODE_JOIN_OPTION A 
								LEFT JOIN PRODUCT_OPTION B 
									ON A.OPTION_VALUE_ID = B.ID 
							WHERE FIND_IN_SET (B.ID , ? )
							GROUP BY A.PRODUCT_CODE ';
            $this->option_value_id     = htmlspecialchars(strip_tags(trim($this->option_value_id	," ")));
            try{
                $this->conn->beginTransaction();
                $stmt1 = $this->conn->prepare($sqlQuery1);
				$stmt1->bindValue(1,$this->option_value_id, 	PDO::PARAM_STR);
				if($stmt1->execute()){
					if($stmt1->rowCount() < 1){
				        $stmt = $this->conn->prepare($sqlQuery);
                        $stmt->bindValue(1,$this->option_value_id, 	PDO::PARAM_STR);
		                if($stmt->execute()){
					        echo json_encode($stmt->rowCount());
					        $this->conn->commit();
				        } else {
					        $this->conn->rollback();
				        }
			    	} else {
						echo json_encode("لايمكن حذف هذا البيان هناك عدد ( ". $stmt1->rowCount() ." ) من المنتجات مرتبطة بهاذا البيان ");
						$this->conn->rollback();
					}
				} else {
				    echo json_encode("لم ننجح فى تنفيذ هذا الاجراء .. حدث خطاء غير معروف");
				    $this->conn->rollback();
				}
            } catch (PDOException $e) {
				echo json_encode('error'.$e->getCode() . ' - ' .$e->getMessage());
				$this->conn->rollback();
			}
		}
    }
	public function getdropProductOptionGroup(){
			$sqlQuery = "SELECT * FROM ".$this->product_option_groupTbl. " WHERE OPTION_TYPE <> '0' ";
			$this->option_id = htmlspecialchars(strip_tags($this->option_id));
			if($this->option_id){
			    $sqlQuery .= " and FIND_IN_SET (OPTION_ID , ? ) ";
			}
			$stmt = $this->conn->prepare($sqlQuery);
			if($this->option_id){
			    $stmt->bindValue(1, $this->option_id , PDO::PARAM_STR);
			}
			$stmt->execute();
			$result = $stmt;
			$records = array();
			while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
			    $rows = array();
			    $rows[] = $record['OPTION_ID']; 
			    $rows[] = $record['OPTION_TYPE']; 
			    $rows[] = $record['OPTION_NAME'];
			    $records[] = $rows;
		    }
			echo json_encode($records);
	}
	public function getdropProductOptionValue(){
			$where1 = "";
			$where2 = "";
			if(!empty($this->product_id) && (empty($this->option_id))){
			    $where1 = " WHERE A.PRODUCT_ID = ? ";
			    $where2 = " where A.OPTION_ID = B.OPTION_ID ";
			} else if(empty($this->product_id) && (!empty($this->option_id))){
			    $where1 = "";
			    $where2 .= " WHERE FIND_IN_SET (A.OPTION_ID , ? ) ";
			} else {
			    echo json_encode('db:err_param');
			    exit();
			}
			$sqlQuery ="SELECT DISTINCT A.* ,C.OPTION_NAME
			                FROM PRODUCT_OPTION A 
                            LEFT JOIN (SELECT DISTINCT A.PRODUCT_ID,D.OPTION_ID ,OPTION_VALUE_ID AS ID
                                        FROM PRODUCT_CODES A 
                                        LEFT JOIN PRODUCT_CODE_JOIN_OPTION B 
                                            ON A.PRO_CODE = B.PRODUCT_CODE
                                        LEFT JOIN PRODUCT_OPTION C 
                                            ON B.OPTION_VALUE_ID = C.ID
                                        LEFT JOIN PRODUCT_OPTION_GROUP D
                                            ON C.OPTION_ID = D.OPTION_ID 
                                        ". $where1 .") B 
                                ON A.OPTION_ID = B.OPTION_ID 
                                LEFT JOIN PRODUCT_OPTION_GROUP C
                                ON A.OPTION_ID = C.OPTION_ID
                                ".$where2."
                            ORDER BY A.ID";
			$this->option_id  = htmlspecialchars(strip_tags($this->option_id));
            $this->product_id = htmlspecialchars(strip_tags($this->product_id));
			//$sqlQuery = "SELECT * FROM ".$this->product_optionTbl. " ";
			$stmt = $this->conn->prepare($sqlQuery);
			if(!empty($this->product_id) && (empty($this->option_id))){
			    $stmt->bindValue(1, $this->product_id , PDO::PARAM_STR);
			} else if(empty($this->product_id) && (!empty($this->option_id))){
			    $stmt->bindValue(1, $this->option_id , PDO::PARAM_STR);
			}
			$stmt->execute();
			$result  = $stmt;
			$records = array();
			$option  = array();
			while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
			    $rows = array();
			    $opt  = array();
			    $rows[] = $record['ID']; 
			    $rows[] = $record['OPTION_ID']; 
			    $rows[] = $record['VALUE'];
			    $opt[]  = $record['OPTION_ID'];
			    $opt[]  = $record['OPTION_NAME'];
			    
			    $records[] = $rows;
			    $option[]  = $opt;
			    
		    }
		    $xarray = array();
		    if(!empty($this->product_id) && (empty($this->option_id))){
		        $xarray[]= $option;
		        $xarray[]= $records;
		        echo json_encode($xarray);
		    } else {
			    echo json_encode($records);
		    }
	}
}
?>