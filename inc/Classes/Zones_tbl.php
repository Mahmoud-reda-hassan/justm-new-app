<?php
class Zones_tbl {
	private $zonesTbl = 'ZONES';
	public  $zone_id;
    public  $zone_name;
    public  $zone_name_en;
    public  $shipping_rate;
	public  $shipping_lowest;
	public  $shipping_time_max;
	public  $ci_zone_id;
	public  $user_type;
	public  $store_id;
	public  $shipping_type;
	private $conn;
	public function __construct($db){
        $this->conn = $db;
    }
	public function listLocalShipping(){
		$sqlQuery = 'SELECT A.ZONE_ID,
                            A.ZONE_NAME,
                            A.ZONE_NAME_EN,
                            Count(B.ZONE_ID) AS COUNTCITES,
                            A.SHIPPING_RATE,
                            A.SHIPPING_LOWEST,
                            A.SHIPPING_TIME_MAX ,
                            A.STORE_ID,
							A.SHIPPING_TYPE
                        FROM   '. $this->zonesTbl .' A
                            LEFT JOIN CITY B
                            ON A.ZONE_ID = B.ZONE_ID
                             ';
		if(!empty($_GET["search"]["value"])){
			$sqlQuery .= ' WHERE (A.ZONE_ID     LIKE   "%'.$_GET["search"]["value"].'%" ';
			$sqlQuery .= ' OR A.ZONE_NAME       LIKE     "%'.$_GET["search"]["value"].'%" ';
			$sqlQuery .= ' OR A.ZONE_NAME_EN    LIKE  "%'.$_GET["search"]["value"].'%" ';
			$sqlQuery .= ' OR A.SHIPPING_LOWEST LIKE      "%'.$_GET["search"]["value"].'%" ';
			$sqlQuery .= ' OR A.SHIPPING_RATE   LIKE   "%'.$_GET["search"]["value"].'%") ';
		}
		    $sqlQuery .= ' GROUP BY A.ZONE_ID,
                              A.ZONE_NAME,
                              A.ZONE_NAME_EN,
                              A.SHIPPING_RATE,
                              A.SHIPPING_LOWEST,
                              A.SHIPPING_TIME_MAX,
							  A.STORE_ID,
							  A.SHIPPING_TYPE ';
		if(!empty($_GET["order"])){
			$columnsArray=['A.ZONE_ID', 'A.ZONE_NAME', 'A.ZONE_NAME_EN', 'A.SHIPPING_RATE', 'A.SHIPPING_TIME_MAX','A.STORE_ID', 'A.SHIPPING_TYPE'];
			$sqlQuery .= ' ORDER BY '.$columnsArray[$_GET['order']['0']['column']].' '.$_GET['order']['0']['dir'].' ';
		} else {
			$sqlQuery .= ' ORDER BY A.ZONE_ID DESC ';
		}
		if(!empty($_GET["length"]) && $_GET["length"] != -1){
			$sqlQuery .= ' LIMIT ' . $_GET['start'] . ', ' . $_GET['length'] . ' ' ;
		}
		$stmt = $this->conn->prepare($sqlQuery);
		$stmt->execute();
		$result = $stmt;
		$stmtTotal = $this->conn->prepare("SELECT * FROM ".$this->zonesTbl);
		$stmtTotal->execute();
		$allResult = $stmtTotal;
		$allRecords = $allResult->rowCount();
	    $displayRecords = $result->rowCount();
		$records = array();
		while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
			$rows = array();
			//if($record['COUNTCITES'] <=0){
			    $rows[] = '<input id="'.$record['ZONE_ID'].'" type="checkbox">';
			//} else {
			 //   $rows[] = '#';
			//}
			$rows[] = $record['ZONE_NAME'];
			$rows[] = $record['ZONE_NAME_EN'];
			$rows[] = $record['SHIPPING_RATE'].' - '.$record['SHIPPING_LOWEST'];
			$rows[] = $record['SHIPPING_TIME_MAX'];
			$rows[] = $record['COUNTCITES'];
			$rows[] = "<button id='btnNew' type='button' 
			            data-id='".$record["ZONE_ID"]."' 
			            data-namear='".$record["ZONE_NAME"]."' 
			            data-nameen='".$record["ZONE_NAME_EN"]."' 
			            data-rate='".$record["SHIPPING_RATE"]."' 
			            data-lowest='".$record["SHIPPING_LOWEST"]."' 
			            data-time='".$record["SHIPPING_TIME_MAX"]."' 
			            data-stoid='".$record["STORE_ID"]."' 
						data-shiptype='".$record["SHIPPING_TYPE"]."' 
			            class='btn btn-primary btn-xs btnEdit'><i class='fas fa-align-center fa-edit'></i></button>";

			$records[] = $rows;
		}
		$output = array("draw"	    =>	intval($_GET["draw"]),
			"iTotalRecords"	        =>	$displayRecords,
			"iTotalDisplayRecords"	=>  $allRecords,
			"data"	                => 	$records);
		echo json_encode($output);
	}
    public function getdropShipping($alldata){
	    $sqlQuery = "SELECT CI_ID,
	                    A.ZONE_ID,
                        CONCAT(CO_NAME,'-',GOV_NAME,'-' ,  CI_NAME) AS NAME,
                        B.GOV_ID
                    FROM CITY A 
                        LEFT JOIN GOV B ON A.GOV_ID = B.GOV_ID
                        LEFT JOIN COUNTRIES C ON B.COUNTRY_ID = C.CO_ID 
                        LEFT JOIN ZONES D ON A.ZONE_ID=D.ZONE_ID 
                    WHERE A.ZONE_ID is null ";
            if(isset($alldata) && $alldata != 'new'){
            	$sqlQuery .= " UNION
                    SELECT CI_ID,
	                    A.ZONE_ID,
                        CONCAT(CO_NAME,'-',GOV_NAME,'-' ,  CI_NAME) AS NAME,
                        B.GOV_ID
                    FROM CITY A 
                        LEFT JOIN GOV B ON A.GOV_ID = B.GOV_ID
                        LEFT JOIN COUNTRIES C ON B.COUNTRY_ID = C.CO_ID 
                        LEFT JOIN ZONES D ON A.ZONE_ID=D.ZONE_ID 
                    WHERE A.ZONE_ID = ".$alldata;
            }
            $sqlQuery = "SELECT * FROM (".$sqlQuery.") AS TBL ORDER BY GOV_ID ";
			$stmt = $this->conn->prepare($sqlQuery);
			$stmt->execute();
			$result = $stmt;
			$records = array();
			while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
			    $rows = array();
			    $rows[] = $record['CI_ID']; 
			    $rows[] = $record['ZONE_ID']; 
			    $rows[] = $record['NAME'];
			    $records[] = $rows;
		    }
			echo json_encode($records);
	}
	public function getdropLocalShipping(){
	    $sqlQuery = "SELECT ZONE_ID,
	                    ZONE_ID,
                        ZONE_NAME
                    FROM ZONES ";
			$stmt = $this->conn->prepare($sqlQuery);
			$stmt->execute();
			$result = $stmt;
			$records = array();
			while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
			    $rows = array();
			    $rows[] = $record['ZONE_ID']; 
			    $rows[] = $record['ZONE_ID']; 
			    $rows[] = $record['ZONE_NAME'];
			    $records[] = $rows;
		    }
			echo json_encode($records);
	}
	public function addLocalShipping(){
		if(!empty($this->zone_name) &&
			!empty($this->zone_name_en)  &&
            !empty($this->shipping_rate) &&
            isset($this->shipping_lowest) && $this->shipping_lowest >=0 &&
			!empty($this->shipping_time_max) &&
            !empty($this->shipping_type) &&
            !empty($this->store_id) &&
            !empty($this->ci_zone_id)) {
			if($this->shipping_lowest <= $this->shipping_rate){
				$this->zone_name         = htmlspecialchars(strip_tags(trim($this->zone_name," ")));
				$this->zone_name_en      = htmlspecialchars(strip_tags(trim($this->zone_name_en," ")));
				$this->shipping_rate     = htmlspecialchars(strip_tags(trim($this->shipping_rate," ")));
				$this->shipping_lowest   = htmlspecialchars(strip_tags(trim($this->shipping_lowest," ")));
				$this->shipping_time_max = htmlspecialchars(strip_tags(trim($this->shipping_time_max," ")));
				$this->ci_zone_id        = htmlspecialchars(strip_tags(trim($this->ci_zone_id," ")));
				$this->shipping_type     = htmlspecialchars(strip_tags(trim($this->shipping_type," ")));
				$this->store_id          = htmlspecialchars(strip_tags(trim($this->store_id," ")));
				try{
					$this->conn->beginTransaction();
					$stmt = $this->conn->prepare("INSERT INTO ".$this->zonesTbl." (`ZONE_NAME`, `ZONE_NAME_EN`,`SHIPPING_RATE`,`SHIPPING_LOWEST`,`SHIPPING_TIME_MAX`,`SHIPPING_TYPE`,`STORE_ID`) VALUES (?,?,?,?,?,?,?)");
					$stmt->bindValue(1,$this->zone_name, PDO::PARAM_STR);
					$stmt->bindValue(2,$this->zone_name_en, PDO::PARAM_STR);
					$stmt->bindValue(3,$this->shipping_rate, PDO::PARAM_STR);
					$stmt->bindValue(4,$this->shipping_lowest, PDO::PARAM_STR);
					$stmt->bindValue(5,$this->shipping_time_max, PDO::PARAM_STR);
					$stmt->bindValue(6,$this->shipping_type, PDO::PARAM_INT);
					$stmt->bindValue(7,$this->store_id, PDO::PARAM_INT);
					if($stmt->execute() == true){
						$lastid = $this->conn->LastInsertId();
						$stmt2 = $this->conn->prepare("UPDATE CITY SET ZONE_ID = ".$lastid." WHERE FIND_IN_SET(CI_ID, ? )");
						$stmt2->bindValue(1,$this->ci_zone_id, PDO::PARAM_STR);
						if($stmt2->execute() == true){
							echo json_encode(array('s','تم الحفظ بنجاح'));
							$this->conn->commit();
						}else{
							echo json_encode(array('e','لم يتم الحفظ ... حدث خطاء غير معروف'));
							$this->conn->rollback();
						};
					} else {
						echo json_encode(array('e','لم يتم الحفظ ... حدث خطاء غير معروف'));
					};
				} catch (PDOException $e) {
					$this->conn->rollback();
					echo json_encode(array('e','حدث خطاء  - رسالة الخطاء: '.$e->getMessage(),$e->getCode()));
				}
			} else {
				echo json_encode(array('e','لم يتم الحفظ ... الحد الادنى لقيمة الشحن يجب ان يكون اقل من او يساوى اعلى قيمة للشحن'));
			}
		} else {
			echo json_encode(array('e','لم يتم الحفظ ... حدث خطاء غير معروف'));
		}
	}
    public function updateZones(){
		if(!empty($this->zone_id) && 
		   !empty($this->zone_name) && 
		   !empty($this->zone_name_en) && 
		   !empty($this->store_id) &&
		   isset($this->shipping_lowest) && $this->shipping_lowest >=0 &&
		   !empty($this->shipping_rate) &&
		   !empty($this->shipping_type) &&
		   !empty($this->shipping_time_max)) {
			if($this->shipping_lowest <= $this->shipping_rate){
				$sqlQuery  = "UPDATE ". $this->zonesTbl . " SET ZONE_NAME = ? , ZONE_NAME_EN = ? , SHIPPING_LOWEST = ? , SHIPPING_RATE = ? , SHIPPING_TIME_MAX = ?, SHIPPING_TYPE = ?, STORE_ID = ? WHERE ZONE_ID = ? ; ";
				$sqlQuery1 = "UPDATE CITY SET ZONE_ID = Null WHERE FIND_IN_SET( ZONE_ID, ? ) ";
				$sqlQuery2 = "UPDATE CITY SET ZONE_ID = ? WHERE FIND_IN_SET(CI_ID, ? )";
				$this->zone_id            = htmlspecialchars(strip_tags($this->zone_id));
				$this->zone_name          = htmlspecialchars(strip_tags($this->zone_name));
				$this->zone_name_en       = htmlspecialchars(strip_tags($this->zone_name_en));
				$this->shipping_lowest    = htmlspecialchars(strip_tags($this->shipping_lowest));
				$this->shipping_rate      = htmlspecialchars(strip_tags($this->shipping_rate));
				$this->shipping_time_max  = htmlspecialchars(strip_tags($this->shipping_time_max));
				$this->shipping_type      = htmlspecialchars(strip_tags($this->shipping_type));
				$this->store_id           = htmlspecialchars(strip_tags($this->store_id));
				$this->ci_zone_id         = htmlspecialchars(strip_tags(trim($this->ci_zone_id," ")));
				try{
					$this->conn->beginTransaction();
					$stmt  = $this->conn->prepare($sqlQuery);
					$stmt->bindValue(1,$this->zone_name, PDO::PARAM_STR);
					$stmt->bindValue(2,$this->zone_name_en, PDO::PARAM_STR);
					$stmt->bindValue(3,$this->shipping_lowest, PDO::PARAM_STR);
					$stmt->bindValue(4,$this->shipping_rate, PDO::PARAM_STR);
					$stmt->bindValue(5,$this->shipping_time_max, PDO::PARAM_STR);
					$stmt->bindValue(6,$this->shipping_type, PDO::PARAM_INT);
					$stmt->bindValue(7,$this->store_id, PDO::PARAM_INT);
					$stmt->bindValue(8,$this->zone_id, PDO::PARAM_INT);
					if($stmt->execute() == true ){
						$stmt1 = $this->conn->prepare($sqlQuery1);
						$stmt1->bindValue(1,$this->zone_id, PDO::PARAM_STR);
						if($stmt1->execute () == true){
							$stmt2 = $this->conn->prepare($sqlQuery2);
							$stmt2->bindValue(1,$this->zone_id, PDO::PARAM_INT);
							$stmt2->bindValue(2,$this->ci_zone_id, PDO::PARAM_STR);
							if($stmt2->execute()){
								echo json_encode(array('s','تم الحفظ بنجاح'));
								$this->conn->commit();
							} else {
								echo json_encode(array('e','لم يتم الحفظ ... حدث خطاء غير معروف'));
								$this->conn->rollback();
							}
						} else {
							echo json_encode(array('e','لم يتم الحفظ ... حدث خطاء غير معروف'));
							$this->conn->rollback();
						}
					} else {
						echo json_encode(array('e','لم يتم الحفظ ... حدث خطاء غير معروف'));
						$this->conn->rollback();
					}
				} catch (PDOException $e) {
					$this->conn->rollback();
					echo json_encode(array('e','حدث خطاء  - رسالة الخطاء: '.$e->getMessage(),$e->getCode()));
				}
			} else {
				echo json_encode(array('e','لم يتم الحفظ ... الحد الادنى لقيمة الشحن يجب ان يكون اقل من او يساوى اعلى قيمة للشحن'));
			}
		} else {
			echo json_encode(array('e','لم يتم الحفظ ... حدث خطاء غير معروف'));
		}
	}	
	public function deleteZones(){
		if($this->zone_id) {
			$sqlQuery="DELETE FROM ".$this->zonesTbl." WHERE FIND_IN_SET (zone_ID , ? ) ";
            $stmt = $this->conn->prepare($sqlQuery);
			$this->zone_id = htmlspecialchars(strip_tags($this->zone_id));
            try{
			    $stmt->bindValue(1, $this->zone_id, PDO::PARAM_STR);
			    if($stmt->execute() && $stmt->rowCount()){
                    echo json_encode(array('s','تم حذف البيانات بنجاح'));
                }else{
                    echo json_encode(array('e','لم يتم  الحذف ... حدث خطاء غير معروف'));
                }
            } catch (PDOException $e) {
                echo json_encode(array('e','حدث خطاء  - رسالة الخطاء: '.$e->getMessage(),$e->getCode()));
            }
			
		}
	}
}
?>