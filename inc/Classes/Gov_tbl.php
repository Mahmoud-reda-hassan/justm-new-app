<?php
/*================================================
**  Gov Table From Database
**==============================================*/
class Gov_tbl {
	private $govTbl = 'GOV';
	public $gov_id;
    public $gov_name;
    public $gov_name_en;
    public $country_id;
    public $regionnal_areas_id;
	private $conn;
	public function __construct($db){
        $this->conn = $db;
    }
    
	public function listGov(){
		$sqlQuery = 'SELECT A.GOV_ID, 
		                    A.GOV_NAME, 
		                    A.GOV_NAME_EN,
		                    B.CO_NAME ,
		                    A.COUNTRY_ID, 
		                    A.REGIONAL_AREAS_ID, 
		                    COUNT(C.CI_ID) AS COUNTCITY 
		                 FROM ' . $this->govTbl . ' A 
		                    LEFT JOIN COUNTRIES B 
		                        ON A.COUNTRY_ID = B.CO_ID
		                    LEFT JOIN CITY C 
		                        ON A.GOV_ID = C.GOV_ID ';
		if(!empty($_GET["search"]["value"])){
			$sqlQuery .= ' WHERE (A.GOV_ID LIKE   "%'.$_GET["search"]["value"].'%" ';
			$sqlQuery .= ' OR A.GOV_NAME LIKE     "%'.$_GET["search"]["value"].'%" ';
			$sqlQuery .= ' OR A.GOV_NAME_EN LIKE  "%'.$_GET["search"]["value"].'%" ';
			$sqlQuery .= ' OR B.CO_NAME LIKE      "%'.$_GET["search"]["value"].'%" ';
			$sqlQuery .= ' OR B.CO_NAME_EN LIKE   "%'.$_GET["search"]["value"].'%") ';
		}
		    $sqlQuery .= ' GROUP BY A.GOV_ID, 
		                            A.GOV_NAME,
		                            A.GOV_NAME_EN,
		                            B.CO_NAME ,
		                            A.COUNTRY_ID, 
		                            A.REGIONAL_AREAS_ID ';
		if(!empty($_GET["order"])){
			$columnsArray = ['A.GOV_ID', 'A.GOV_NAME', 'A.GOV_NAME_EN','B.CO_NAME'];
			$sqlQuery .= ' ORDER BY '.$columnsArray[$_GET['order']['0']['column']].' '.$_GET['order']['0']['dir'].' ';
		} else {
			$sqlQuery .= ' ORDER BY GOV_ID DESC ';
		}
		
		if(!empty($_POST["length"]) && $_GET["length"] != -1){
			$sqlQuery .= ' LIMIT ' . $_GET['start'] . ', ' . $_GET['length'] . ' ' ;
		}
		$stmt = $this->conn->prepare($sqlQuery);
		$stmt->execute();
		$result = $stmt;
		
		$stmtTotal = $this->conn->prepare("SELECT * FROM ".$this->govTbl);
		$stmtTotal->execute();
		$allResult = $stmtTotal;
		$allRecords = $allResult->rowCount();
		
	    $displayRecords = $result->rowCount();
		$records = array();
		while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
			$rows = array();
			if($record['COUNTCITY'] <=0){
			    $rows[] = '<input id="'.$record['GOV_ID'].'" type="checkbox">';
			} else {
			    $rows[] = '#';
			}
			$rows[] = $record['GOV_NAME'];
			$rows[] = $record['GOV_NAME_EN'];
			$rows[] = $record['CO_NAME'];
			$rows[] = "<button id='btnNew' type='button' data-id='".$record["GOV_ID"]."' data-namear='".$record["GOV_NAME"]."' data-nameen='".$record["GOV_NAME_EN"]."'  data-country='".$record["COUNTRY_ID"]."' class='btn btn-primary btn-xs'><i class='fas fa-align-center fa-edit'></i></button>";

			$records[] = $rows;
		}
		
		$output = array(
			"draw"	                =>  intval($_GET["draw"]),
			"iTotalRecords"	        =>  $displayRecords,
			"iTotalDisplayRecords"  =>  $allRecords,
			"data"                  =>  $records
			/*"Columnse"               =>  array(
			    array('targets' => 0 ,'title' =>'<input type="checkbox">'),
			    array('targets' => 1 ,'title' =>'الإسم'),
			    array('targets' => 2 ,'title' =>'Name'),
			    array('targets' => 3 ,'title' =>'تابع لـ'),
			    array('targets' => 4 ,'title' =>'تحرير')
			    ) */
		);
		echo json_encode($output);
	}
	public function getdropGov(){
			$sqlQuery = "SELECT * FROM " . $this->govTbl . " ";
			if(!empty($this->country_id)) {
		        $sqlQuery .= " WHERE FIND_IN_SET (COUNTRY_ID , ? ) ";
		    }
			$stmt = $this->conn->prepare($sqlQuery);
		    if(!empty($this->country_id)) {
			    $this->country_id = htmlspecialchars(strip_tags($this->country_id));
			    $stmt->bindValue(1, $this->country_id , PDO::PARAM_STR);
		    }
			$stmt->execute();
			$result = $stmt;
			$records = array();
			while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
			    $rows = array();
			    $rows[] = $record['GOV_ID']; 
			    $rows[] = $record['GOV_ID']; 
			    $rows[] = $record['GOV_NAME'];
			    //$rows[] = $record['GOV_NAME_EN'];
			    $records[] = $rows;
		    }
			echo json_encode($records);
	}
	public function getGov(){
			$sqlQuery = "SELECT * FROM ".$this->govTbl. " ";
		if(!empty($this->gov_id)) {
		    $sqlQuery .= " WHERE FIND_IN_SET (GOV_ID , ? ) ";
		}
			$stmt = $this->conn->prepare($sqlQuery);
		if(!empty($this->gov_id)) {
			$this->gov_id = htmlspecialchars(strip_tags($this->gov_id));
			$stmt->bindValue(1, $this->gov_id , PDO::PARAM_STR);
		}
			$stmt->execute();
			$result = $stmt;
			$records = array();
			while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
			    $rows = array();
			    $rows['GOV_ID']            = $record['GOV_ID'];
			    $rows['GOV_NAME']          = $record['GOV_NAME'];
			    $rows['GOV_NAME_EN']       = $record['GOV_NAME_EN'];
			    $rows['COUNTRY_ID']        = $record['COUNTRY_ID'];
			    $rows['REGIONAL_AREAS_ID'] = $record['REGIONAL_AREAS_ID'];
			    $records[] = $rows;
		    }
		echo json_encode($records);
	}
    public function getinfoGov(){
		if(!empty($this->gov_id)) {
			$sqlQuery = "SELECT * FROM ".$this->govTbl. " WHERE FIND_IN_SET (GOV_ID , ? ) ";
			$stmt = $this->conn->prepare($sqlQuery);
			$this->gov_id = htmlspecialchars(strip_tags($this->gov_id));
			$stmt->bindValue(1, $this->gov_id , PDO::PARAM_STR);
			$stmt->execute();
			$result = $stmt;
			$records = array();
			while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
			    $rows = array();
			   // $rows[] = $record['GOV_ID'];
			    $rows[] = $record['GOV_NAME'];
			    $rows[] = $record['GOV_NAME_EN'];
			  //  $rows[] = $record['COUNTRY_ID'];
			   // $rows[] = $record['REGIONAL_AREAS_ID'];
			    $records[] = $rows;
		    }
			echo json_encode($records);
		}
    }
	public function updateGov(){
		if(!empty($this->gov_id) && !empty($this->gov_name) && !empty($this->gov_name_en)) {
			$stmt = $this->conn->prepare("
			UPDATE ". $this->govTbl . " SET GOV_NAME = ? , GOV_NAME_EN = ? , COUNTRY_ID = ? 
			WHERE GOV_ID = ? ");
			
			$this->gov_id       = htmlspecialchars(strip_tags($this->gov_id));
			$this->gov_name     = htmlspecialchars(strip_tags($this->gov_name));
			$this->gov_name_en  = htmlspecialchars(strip_tags($this->gov_name_en));
			$this->country_id   = htmlspecialchars(strip_tags($this->country_id));
            try{
			    $stmt->bindValue(1,$this->gov_name, PDO::PARAM_STR);
			    $stmt->bindValue(2,$this->gov_name_en, PDO::PARAM_STR);
			    $stmt->bindValue(3,$this->country_id, PDO::PARAM_INT);
                $stmt->bindValue(4,$this->gov_id, PDO::PARAM_INT);
			    if($stmt->execute() && $stmt->rowCount()){
                    echo json_encode(array('s','تم تعديل البيانات بنجاح'));
                }else{
                    echo json_encode(array('e','لم يتم الحفظ ... حدث خطاء غير معروف'));
                }
            } catch (PDOException $e) {
                echo json_encode(array('e','حدث خطاء  - رسالة الخطاء: '.$e->getMessage(),$e->getCode())); 
            }
		}	    
	}
	public function addGov(){
		if(!empty($this->gov_name) && !empty($this->gov_name_en) && !empty($this->country_id)) {
			$stmt = $this->conn->prepare("
			INSERT INTO ".$this->govTbl."(`REGIONAL_AREAS_ID`,`GOV_NAME`, `GOV_NAME_EN`, COUNTRY_ID)
			VALUES (NULL,?,?,?)");

			$this->gov_name = htmlspecialchars(strip_tags($this->gov_name));
			$this->gov_name_en = htmlspecialchars(strip_tags($this->gov_name_en));
			$this->country_id = htmlspecialchars(strip_tags($this->country_id));

			try{
			    $stmt->bindValue(1,$this->gov_name, PDO::PARAM_STR);
			    $stmt->bindValue(2,$this->gov_name_en, PDO::PARAM_STR);
		        $stmt->bindValue(3,$this->country_id, PDO::PARAM_STR);
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
	public function deleteGov(){
		if($this->gov_id) {			
			$stmt = $this->conn->prepare("
				DELETE FROM ".$this->govTbl." 
				WHERE FIND_IN_SET (GOV_ID , ? ) ");

			$this->gov_id = htmlspecialchars(strip_tags($this->gov_id));
            try{
			    $stmt->bindValue(1, $this->gov_id, PDO::PARAM_STR);
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