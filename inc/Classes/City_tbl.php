<?php
/*================================================
**  City Database Table
**==============================================*/
class City_tbl {
	private $cityTbl = 'CITY';
	public  $ci_id;
    public  $ci_name;
    public  $ci_name_en;
    public  $gov_id;
	public  $zone_id;
	private $conn;
	public function __construct($db){
        $this->conn = $db;
    }
	public function listCity(){
		$sqlQuery = 'SELECT A.CI_ID, 
		                    A.CI_NAME, 
		                    A.CI_NAME_EN,
		                    B.GOV_NAME ,
		                    B.GOV_NAME_EN,
		                    A.GOV_ID, 
		                    A.ZONE_ID, 
		                    COUNT(C.ADDRESS_ID) AS COUNTADDRESS 
		                 FROM ' . $this->cityTbl . ' A 
		                    LEFT JOIN GOV B 
		                        ON A.GOV_ID = B.GOV_ID
		                    LEFT JOIN ADDRESSES C 
		                        ON A.CI_ID = C.CITY_ID ';
		if(!empty($_GET["search"]["value"])){
			$sqlQuery .= ' WHERE (A.CI_ID LIKE   "%'.$_GET["search"]["value"].'%" ';
			$sqlQuery .= ' OR A.CI_NAME LIKE     "%'.$_GET["search"]["value"].'%" ';
			$sqlQuery .= ' OR A.CI_NAME_EN LIKE  "%'.$_GET["search"]["value"].'%" ';
			$sqlQuery .= ' OR B.GOV_NAME LIKE      "%'.$_GET["search"]["value"].'%" ';
			$sqlQuery .= ' OR B.GOV_NAME_EN LIKE   "%'.$_GET["search"]["value"].'%") ';
		}
		$sqlQuery .= ' GROUP BY 
		               A.CI_ID, 
		               A.CI_NAME, 
		               A.CI_NAME_EN,
		               B.GOV_NAME ,
		               B.GOV_NAME_EN,
		               A.GOV_ID, 
		               A.ZONE_ID ';
		if(!empty($_GET["order"])){
			$columnsArr=['A.CI_ID','A.CI_NAME','A.CI_NAME_EN','B.GOV_NAME' , 'B.GOV_NAME_EN'];
			$sqlQuery .= ' ORDER BY '.$columnsArr[$_GET['order']['0']['column']].' '.$_GET['order']['0']['dir'].' ';
		} else {
			$sqlQuery .= ' ORDER BY GOV_ID DESC ';
		}
		if(!empty($_GET["length"]) && $_GET["length"] != -1){
			$sqlQuery .= ' LIMIT ' . $_GET['start'] . ', ' . $_GET['length'] . ' ' ;
		}
		$stmt = $this->conn->prepare($sqlQuery);
		$stmt->execute();
		$result = $stmt;
		$stmtTotal = $this->conn->prepare("SELECT * FROM ".$this->cityTbl);
		$stmtTotal->execute();
		$allResult = $stmtTotal;
		$allRecords = $allResult->rowCount();
	    $displayRecords = $result->rowCount();
		$records = array();
		while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
			$rows = array();
			if($record['COUNTADDRESS'] <=0){
			    $rows[] = '<input id="'.$record['CI_ID'].'" type="checkbox">';
			} else {
			    $rows[] = '#';
			}
			$rows[] = $record['CI_NAME'];
			$rows[] = $record['CI_NAME_EN'];
			$rows[] = $record['GOV_NAME'];
			$rows[] = "<button id='btnNew' type='button' data-id='".$record["CI_ID"]."' data-namear='".$record["CI_NAME"]."' data-nameen='".$record["CI_NAME_EN"]."'  data-gov='".$record["GOV_ID"]."' class='btn btn-primary btn-xs btnEdit'><i class='fas fa-align-center fa-edit'></i></button>";
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
    public function addCity(){
		if(!empty($this->ci_name) && 
		   !empty($this->ci_name_en) && 
		   !empty($this->gov_id)) {
		   
			$sqlQuery = "INSERT INTO ".$this->cityTbl."(`CI_NAME`,`CI_NAME_EN`, `GOV_ID`,`ZONE_ID`)
			VALUES (?,?,?,NULL)";
			$this->ci_name    = htmlspecialchars(strip_tags($this->ci_name));
			$this->ci_name_en = htmlspecialchars(strip_tags($this->ci_name_en));
			$this->gov_id     = htmlspecialchars(strip_tags($this->gov_id));
        try{
                $stmt = $this->conn->prepare($sqlQuery);
			    $stmt->bindValue(1,$this->ci_name, PDO::PARAM_STR);
			    $stmt->bindValue(2,$this->ci_name_en, PDO::PARAM_STR);
		        $stmt->bindValue(3,$this->gov_id, PDO::PARAM_INT);
			    if($stmt->execute() && $stmt->rowCount()){
                    echo json_encode(array('s','تم حفظ البيان بنجاح'));
                }else{
                    echo json_encode(array('e','لم يتم الحفظ ... حدث خطاء غير معروف'));
                }
			} catch (PDOException $e) {
                echo json_encode(array('e','حدث خطاء  - رسالة الخطاء: '.$e->getMessage(),$e->getCode()));
            }
		    
		}
	}
	public function updateCity(){
	    
		if(!empty($this->ci_id) && !empty($this->ci_name) && !empty($this->ci_name_en)) {
		
			$sqlQuery = "UPDATE ". $this->cityTbl . " SET CI_NAME = ? , CI_NAME_EN = ? , GOV_ID = ? WHERE CI_ID = ? ";
			
			$this->ci_id       = htmlspecialchars(strip_tags($this->ci_id));
			$this->ci_name     = htmlspecialchars(strip_tags($this->ci_name));
			$this->ci_name_en  = htmlspecialchars(strip_tags($this->ci_name_en));
			$this->gov_id      = htmlspecialchars(strip_tags($this->gov_id));
			
            try{
                $stmt = $this->conn->prepare($sqlQuery);
			    $stmt->bindValue(1,$this->ci_name,    PDO::PARAM_STR);
			    $stmt->bindValue(2,$this->ci_name_en, PDO::PARAM_STR);
                $stmt->bindValue(3,$this->gov_id,     PDO::PARAM_INT);
                $stmt->bindValue(4,$this->ci_id,      PDO::PARAM_INT);
			    if($stmt->execute() && $stmt->rowCount()){
                    echo json_encode(array('s','تم تعديل البيانات بنجاح'));
                }else{
                    echo json_encode(array('e','لم يتم الحفظ ... حدث خطاء غير معروف'));
                }
            } catch (PDOException $e) {
                echo json_encode(array('e','حدث خطاء  - رسالة الخطاء: '.$e->getMessage(),$e->getCode())); 
            }
			
		} else { 
		    echo json_encode("tbl_error_parm");
		}
	}
	public function deleteCity(){
		if($this->ci_id) {			
			$sqlQuery = "DELETE FROM ".$this->cityTbl." WHERE FIND_IN_SET (CI_ID , ? ) ";
			$this->ci_id = htmlspecialchars(strip_tags($this->ci_id));
            try{
                $stmt = $this->conn->prepare($sqlQuery);
			    $stmt->bindValue(1, $this->ci_id, PDO::PARAM_INT);
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
	public function getdropCity(){
			$sqlQuery = "SELECT * FROM " . $this->cityTbl . " ";
			if (!empty($this->gov_id)) {
			    $sqlQuery .=" where GOV_ID = ? ";
			}
			$stmt = $this->conn->prepare($sqlQuery);
        	if (!empty($this->gov_id)) {
        	    $this->gov_id = htmlspecialchars(strip_tags($this->gov_id));
			    $stmt->bindValue(1, $this->gov_id , PDO::PARAM_INT);
        	}
			$stmt->execute();
			$result = $stmt;
			$records = array();
			while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
			    $rows = array();
			    $rows[] = $record['CI_ID']; 
			    $rows[] = $record['CI_ID']; 
			    $rows[] = $record['CI_NAME'];
			    //$rows[] = $record['GOV_NAME_EN'];
			    $records[] = $rows;
		    }
			echo json_encode($records);
	}
	public function getAddressDataUse_old(){
		header('Content-Type: application/json');
		$query = "SELECT gov.GOV_ID, 
				  GOV_NAME, 
				  CI_ID, 
				  CI_NAME 
				 FROM city 
				  JOIN gov ON city.GOV_ID = gov.GOV_ID";
		$result = $this->conn->query($query);
		$data = [];
		while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
		    $data[] = [
		        "GOV_ID" => $row["GOV_ID"],
		        "GOV_NAME" => $row["GOV_NAME"],
		        "CI_ID" => $row["CI_ID"],
		        "CI_NAME" => $row["CI_NAME"]
		    ];
		}
		echo json_encode($data);
	}

	public function getAddressDataUse() {
	    header('Content-Type: application/json');
	
	    // استعلام جلب الدول
	    $queryCountries = "SELECT CO_ID, CO_NAME, CO_NAME_EN FROM countries";
	    $resultCountries = $this->conn->query($queryCountries);
	    $countries = [];
	    while ($row = $resultCountries->fetch(PDO::FETCH_ASSOC)) {
	        $countries[] = [
	            "CO_ID" => (int) $row["CO_ID"],
	            "CO_NAME" => $row["CO_NAME"],
	            "CO_NAME_EN" => $row["CO_NAME_EN"]
	        ];
	    }
	
	    // استعلام جلب المحافظات
	    $queryGov = "SELECT GOV_ID, COUNTRY_ID, GOV_NAME, REGIONAL_AREAS_ID, GOV_NAME_EN FROM gov";
	    $resultGov = $this->conn->query($queryGov);
	    $governorates = [];
	    while ($row = $resultGov->fetch(PDO::FETCH_ASSOC)) {
	        $governorates[] = [
	            "GOV_ID" => (int) $row["GOV_ID"],
	            "COUNTRY_ID" => (int) $row["COUNTRY_ID"],
	            "GOV_NAME" => $row["GOV_NAME"],
	            "REGIONAL_AREAS_ID" => (int) $row["REGIONAL_AREAS_ID"],
	            "GOV_NAME_EN" => $row["GOV_NAME_EN"]
	        ];
	    }
	
	    // استعلام جلب المدن
	    $queryCity = "SELECT CI_ID, GOV_ID, ZONE_ID, CI_NAME, CI_NAME_EN FROM city";
	    $resultCity = $this->conn->query($queryCity);
	    $cities = [];
	    while ($row = $resultCity->fetch(PDO::FETCH_ASSOC)) {
	        $cities[] = [
	            "CI_ID" => (int) $row["CI_ID"],
	            "GOV_ID" => (int) $row["GOV_ID"],
	            "ZONE_ID" => (int) $row["ZONE_ID"],
	            "CI_NAME" => $row["CI_NAME"],
	            "CI_NAME_EN" => $row["CI_NAME_EN"]
	        ];
	    }
	
	    // إرجاع البيانات على شكل JSON يحتوي على الدول + المحافظات + المدن
	    echo json_encode([
	        "countries" => $countries,
	        "governorates" => $governorates,
	        "cities" => $cities
	    ]);
	}
}
?>