<?php
//session_start();
//$UserPerm = new user_permission();
/*================================================
**  Countries Table From Database
**==============================================*/
class Countries_tbl {
	private $countriesTbl = 'COUNTRIES';
	private $govTbl = 'GOV';
	private $cityTbl = 'CITY';
	public $co_id;
    public $co_name;
    public $co_name_en;
	private $conn;
	public function __construct($db){
        $this->conn = $db;
    }
/*============================================
    function getCountries 
    --------------------------------
    dataTables parameters:
        1 - $_POST["search"]["value"]
        2 - $_POST["order"] 
        3 - $_POST["length"]
        4 - $_POST['start'] 
===========================================*/ 
	public function listCountries(){
	    //if($this->UserScript['Admin']['Countries']['Me'] === true){
		    $sqlQuery = 'SELECT A.CO_ID, A.CO_NAME, A.CO_NAME_EN, COUNT(B.GOV_ID) AS COUNTGOV  FROM ' . $this->countriesTbl . ' A LEFT JOIN GOV B  ON A.CO_ID = B.COUNTRY_ID  ';
		    if(!empty($_GET["search"]["value"])){
			    $sqlQuery .= ' where (CO_ID LIKE "%'.$_GET["search"]["value"].'%" ';
			    $sqlQuery .= ' OR CO_NAME LIKE "%'.$_GET["search"]["value"].'%" ';
			    $sqlQuery .= ' OR CO_NAME_EN LIKE "%'.$_GET["search"]["value"].'%") ';
		    }
	        // Group by sqlQuery
		    $sqlQuery .= ' GROUP BY A.CO_ID, A.CO_NAME, A.CO_NAME_EN ';
	        // Order by sqlQuery
	        if(!empty($_GET["order"])){
	        	$columnsArray = ['A.CO_ID', 'A.CO_NAME', 'A.CO_NAME_EN'];
			    $sqlQuery .= ' ORDER BY '.$columnsArray[$_GET['order']['0']['column']].' '.$_GET['order']['0']['dir'].' ';
		    } else {
			    $sqlQuery .= ' ORDER BY CO_ID DESC ';
		    }
	        // LIMIT data sqlQuery
		    if(!empty($_GET["length"]) && $_GET["length"] != -1){
			    $sqlQuery .= ' LIMIT ' . $_GET['start'] . ', ' . $_GET['length'] . ' ' ;
		    }
		    $stmt = $this->conn->prepare($sqlQuery);
		    $stmt->execute();
		    $result = $stmt;
		
		    $stmtTotal = $this->conn->prepare("SELECT * FROM ".$this->countriesTbl);
		    $stmtTotal->execute();
		    $allResult = $stmtTotal;
		    $allRecords = $allResult->rowCount();
		
	        $displayRecords = $result->rowCount();
		    $records = array();
		    while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
			    $rows = array();
			    if($record['COUNTGOV'] <=0){
			        $rows[] = '<input id="'.$record['CO_ID'].'" type="checkbox">';
			    } else {
			        $rows[] = '#';
			    }
			    $rows[] = $record['CO_NAME'];
			    $rows[] = $record['CO_NAME_EN'];
			    $rows[] = "<button id='btnNew' type='button' data-id='".$record["CO_ID"]."' data-namear='".$record["CO_NAME"]."' data-nameen='".$record["CO_NAME_EN"]."'  class='btn btn-primary btn-xs btnEdit'><i class='fas fa-align-center fa-edit'></i></button>";

			    $records[] = $rows;
		    }
		    if(isset($_GET["draw"])){
		        $dra = $_GET["draw"];
		    }else{
		        $dra = 0;
		    }
		    $output = array(
			    "draw"	                =>	$dra,
			    "iTotalRecords"	        =>	$displayRecords,
			    "iTotalDisplayRecords"	=>  $allRecords,
			    "data"	                => 	$records
		    );
		    echo json_encode($output);
	    //}
	}
/*==============================================
 getoneCountries :
        $this->co_id 
     or $this->co_name  
     or $this->co_name_en
==============================================*/
	public function getoneCountries(){
			$sqlQuery  = "SELECT * FROM ".$this->countriesTbl. " ";
			if(!empty($this->co_id)) {
			    $this->co_id   = htmlspecialchars(strip_tags($this->co_id));
			    $sqlQuery     .= " WHERE FIND_IN_SET (CO_ID , ? ) ";
			    $stmt          = $this->conn->prepare($sqlQuery);
			    $this->co_id   = htmlspecialchars(strip_tags($this->co_id));
			    $stmt->bindValue(1,$this->co_id, PDO::PARAM_STR);
			} else if (!empty($this->co_name)) {
			    $this->co_name  = htmlspecialchars(strip_tags($this->co_name));
			    $sqlQuery      .= " WHERE CO_NAME LIKE ? ";
			    $stmt           = $this->conn->prepare($sqlQuery);
			    $stmt->bindValue(1,$this->co_name, PDO::PARAM_STR);
			} else if(!empty($this->co_name_en)) {
			    $this->co_name_en = htmlspecialchars(strip_tags($this->co_name_en));
			    $sqlQuery        .= " WHERE CO_NAME_EN LIKE ? ";
			    $stmt             = $this->conn->prepare($sqlQuery);
			    $stmt->bindValue(2,$this->co_name_en, PDO::PARAM_STR);
			}
			$stmt->execute();
			$result = $stmt;
			$records = array();
			while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
			    $rows = array();
			    $rows['CO_ID']      = $record['CO_ID'];
			    $rows['CO_NAME']    = $record['CO_NAME'];
			    $rows['CO_NAME_EN'] = $record['CO_NAME_EN'];
			    $records[] = $rows;

		}
	}
	public function getdropCountries(){
			$sqlQuery = "SELECT * FROM ".$this->countriesTbl. " ";
			$stmt = $this->conn->prepare($sqlQuery);
		//	$this->co_id = htmlspecialchars(strip_tags($this->co_id));
		//	$stmt->bindValue(1, $this->co_id , PDO::PARAM_STR);
			$stmt->execute();
			$result = $stmt;
			$records = array();
			while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
			    $rows = array();
			    $rows[] = $record['CO_ID']; 
			    $rows[] = $record['CO_ID']; 
			    $rows[] = $record['CO_NAME'];
			    //$rows[] = $record['CO_NAME_EN'];
			    $records[] = $rows;
		    }
		       
			echo json_encode($records);
	}
	public function getSelectOptionCountries(){
			$sqlQuery0 = "SELECT * FROM ".$this->countriesTbl. " ";
			$sqlQuery1 = "SELECT * FROM ".$this->govTbl. " ";
			$sqlQuery2 = "SELECT A.*,
			 CASE WHEN B.SHIPPING_LOWEST IS NULL THEN 20 ELSE B.SHIPPING_LOWEST END AS SHIPPING_LOWEST,
			 CASE WHEN B.SHIPPING_RATE IS NULL THEN 500 ELSE B.SHIPPING_RATE END AS SHIPPING_RATE,
			 CASE WHEN B.SHIPPING_TIME_MAX IS NULL THEN 15 ELSE B.SHIPPING_TIME_MAX END AS SHIPPING_TIME_MAX,
			 CASE WHEN B.STORE_ID IS NULL THEN (SELECT B.STO_ID from BRANCHES A left join STORES as B on A.BR_ID=B.BRANCH_ID where A.BR_MAIN = 1 AND STTY_ID =2) ELSE B.STORE_ID END AS STORE_ID
             FROM CITY A
             LEFT JOIN ZONES B ON A.ZONE_ID = B.ZONE_ID  ";
			$countryData = array();
			$stmt0 = $this->conn->prepare($sqlQuery0);
			$stmt0->execute();
			$result = $stmt0;
			$records0 = array();
			while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 	  
				$records0[] = array(
				    '<option value="'.$record['CO_ID'].'">'.$record['CO_NAME'].'</option>',
				    '<option value="'.$record['CO_ID'].'">'.$record['CO_NAME_EN'].'</option>');

		    }
			$countryData[]=$records0;
			$stmt1 = $this->conn->prepare($sqlQuery1);
			$stmt1->execute();
			$result = $stmt1;
			$records1 = array();
			while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
				$records1[] = array(
				    '<option class="opgov" data-id ="'.$record['COUNTRY_ID'].'" value="'.$record['GOV_ID'].'">'.$record['GOV_NAME'].'</option>',
				    '<option class="opgov" data-id ="'.$record['COUNTRY_ID'].'" value="'.$record['GOV_ID'].'">'.$record['GOV_NAME_EN'].'</option>');  
				
		    }
			$countryData[] = $records1;
			$stmt2 = $this->conn->prepare($sqlQuery2);
			$stmt2->execute();
			$result = $stmt2;
			$records2 = array();
			$records2[] = array(
			        '<option class="" value="" selected>اختار المدينه</option>',
                    '<option class="" value="" selected>Choose the city</option>');

			while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
				$records2[] = array(
				    '<option class="opcity" data-id ="'.$record['GOV_ID'].'" data-zid="'.$record['ZONE_ID'].'" data-shipMin="'.$record['SHIPPING_LOWEST'].'" data-shipMax="'.$record['SHIPPING_RATE'].'" data-shipTime="'.$record['SHIPPING_TIME_MAX'].'" date-stoid="'.$record['STORE_ID'].'" value="'.$record['CI_ID'].'">'.$record['CI_NAME'].'</option>',
				    '<option class="opcity" data-id ="'.$record['GOV_ID'].'" data-zid="'.$record['ZONE_ID'].'" data-shipMin="'.$record['SHIPPING_LOWEST'].'" data-shipMax="'.$record['SHIPPING_RATE'].'" data-shipTime="'.$record['SHIPPING_TIME_MAX'].'" date-stoid="'.$record['STORE_ID'].'" value="'.$record['CI_ID'].'">'.$record['CI_NAME_EN'].'</option>'); 
		    }
			$countryData[] = $records2;

			echo json_encode($countryData);
	} 
	public function updateCountries(){
		if($this->co_id) {
			$stmt = $this->conn->prepare("
			UPDATE ".$this->countriesTbl." 
			SET CO_NAME= ?, CO_NAME_EN = ?
			WHERE CO_ID = ?");
			
			$this->co_id       = htmlspecialchars(strip_tags($this->co_id));
			$this->co_name     = htmlspecialchars(strip_tags($this->co_name));
			$this->co_name_en  = htmlspecialchars(strip_tags($this->co_name_en));
			try{
			    $stmt->bindValue(1,$this->co_name, PDO::PARAM_STR);
			    $stmt->bindValue(2,$this->co_name_en, PDO::PARAM_STR);
			    $stmt->bindValue(3,$this->co_id, PDO::PARAM_INT);
		        if($stmt->execute()){
		            if($stmt->rowCount()){
		                echo json_encode(array('s','تم حفظ البيانات بنجاح'));
		            }else{
		                echo json_encode(array('w','لم تقم بأى تعديلات لحفظها'));
		            }
		        }else{
                    echo json_encode(array('e','لم يتم الحفظ ... حدث خطاء غير معروف'));
                }
			} catch (PDOException $e) {
                echo json_encode(array('e','حدث خطاء  - رسالة الخطاء: '.$e->getMessage(),$e->getCode()));
            }
		}	    
	}
	public function addCountries(){
		if($this->co_name ) {
		    $this->co_name    = htmlspecialchars(strip_tags(trim($this->co_name," ")));
			$this->co_name_en = htmlspecialchars(strip_tags(trim($this->co_name_en," ")));
            try{
                $stmt = $this->conn->prepare("INSERT INTO ".$this->countriesTbl." (`CO_NAME`, `CO_NAME_EN`) VALUES (?,?)");
                $stmt->bindValue(1,$this->co_name, PDO::PARAM_STR);
		        $stmt->bindValue(2,$this->co_name_en, PDO::PARAM_STR);
                if($stmt->execute()){
                    if($stmt->rowCount()){
		                echo json_encode(array('s','تم حفظ البيانات بنجاح'));
                    }
                }else{
                    echo json_encode(array('e','لم يتم الحفظ ... حدث خطاء غير معروف'));
                }
            } catch (PDOException $e) {
                echo json_encode(array('e','حدث خطاء  - رسالة الخطاء: '.$e->getMessage(),$e->getCode()));
            }
		    
		}
	}
	public function deleteCountries(){
		if($this->co_id) {
			$stmt = $this->conn->prepare("
				DELETE FROM ".$this->countriesTbl." 
				WHERE FIND_IN_SET (CO_ID , ? ) ");

			$this->co_id = htmlspecialchars(strip_tags($this->co_id));
            try{
			    $stmt->bindValue(1, $this->co_id, PDO::PARAM_STR);
                if($stmt->execute() && $stmt->rowCount()){
                    echo json_encode(array('s','تم حذف البيانات بنجاح'));
                }else{
                    echo json_encode(array('e','لم يتم الحفظ ... حدث خطاء غير معروف'));
                }
	    	} catch (PDOException $e) {
                echo json_encode(array('e','حدث خطاء  - رسالة الخطاء: '.$e->getMessage(),$e->getCode()));
            }
		}
	}
}
?>