<?php
class Companies_tbl {
	private $companyTbl = 'COMPANIES';
	public  $com_id;
    public  $com_name;
    public  $com_tel;
    public  $com_fax;
	public  $com_mobile;
	public  $com_email;
	public  $city_id;
	public  $com_address;
	public  $com_logobrand;
	private $conn;
	
	public function __construct($db){
        $this->conn = $db;
    }
    
	public function getCompany(){
	    $sqlQuery = "SELECT A.COM_ID, 
	                       A.COM_NAME, 
	                       A.COM_TEL, 
	                       A.COM_FAX, 
	                       A.COM_MOBILE, 
	                       A.COM_EMAIL, 
	                       D.CO_ID,
	                       D.CO_NAME,
	                       C.GOV_ID,
	                       C.GOV_NAME,
	                       A.CITY_ID, 
	                       B.CI_NAME, 
	                       A.COM_ADDRESS, 
	                       A.LOGOBRAND 
	                FROM ". $this->companyTbl ." A 
	                    LEFT JOIN CITY B 
	                        ON A.CITY_ID = B.CI_ID
	                    LEFT JOIN GOV C 
	                        ON B.GOV_ID=C.GOV_ID
	                    LEFT JOIN COUNTRIES D 
	                        ON C.COUNTRY_ID = D.CO_ID
	                WHERE COM_ID=1";
        $stmt = $this->conn->prepare($sqlQuery);
		$stmt->execute();
		$result = $stmt;
		$records = array();
		while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
		    $rows = array();
			$rows['COM_NAME']     = $record['COM_NAME']; 
			$rows['COM_TEL']      = $record['COM_TEL']; 
			$rows['COM_FAX']      = $record['COM_FAX'];
			$rows['COM_MOBILE']   = $record['COM_MOBILE']; 
			$rows['COM_EMAIL']    = $record['COM_EMAIL']; 
			$rows['CO_ID']        = $record['CO_ID'];
			$rows['GOV_ID']       = $record['GOV_ID'];
			$rows['CITY_ID']      = $record['CITY_ID'];
			$rows['COM_ADDRESS']  = $record['COM_ADDRESS'];
			$rows['LOGOBRAND']    = $record['LOGOBRAND'];
			$records[] = $rows;
		}
	    echo json_encode($records);
	}
	public function getdropCompany(){
		$sqlQuery = "SELECT * FROM " . $this->companyTbl . " ";
		if(!empty($this->com_id)) {
		    $sqlQuery .= " WHERE FIND_IN_SET (COM_ID , ? ) ";
		}
		$stmt = $this->conn->prepare($sqlQuery);
		if(!empty($this->com_id)) {
			$this->com_id = htmlspecialchars(strip_tags($this->com_id));
			$stmt->bindValue(1, $this->com_id , PDO::PARAM_STR);
		}
		$stmt->execute();
		$result = $stmt;
		$records = array();
		while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
			$rows = array();
			$rows[] = $record['COM_ID']; 
			$rows[] = $record['COM_ID']; 
			$rows[] = $record['COM_NAME'];
			//$rows[] = $record['GOV_NAME_EN'];
			$records[] = $rows;
		}
		echo json_encode($records);
	}
	public function updateCompany(){
			$sqlQuery = "UPDATE ". $this->companyTbl . " 
							SET  COM_NAME	 = ? , 
								 COM_TEL 	 = ? , 
								 COM_FAX 	 = ? , 
								 COM_MOBILE  = ? , 
								 COM_EMAIL 	 = ? ,
								 CITY_ID 	 = ? ,
								 COM_ADDRESS = ? 
								  
						WHERE COM_ID = 1 ";
			
			$this->com_name			= htmlspecialchars(strip_tags($this->com_name));
			$this->com_tel			= htmlspecialchars(strip_tags($this->com_tel));
			$this->com_fax			= htmlspecialchars(strip_tags($this->com_fax));
			$this->com_mobile		= htmlspecialchars(strip_tags($this->com_mobile));
			$this->com_email		= htmlspecialchars(strip_tags($this->com_email));
			$this->city_id			= htmlspecialchars(strip_tags($this->city_id));
			$this->com_address		= htmlspecialchars(strip_tags($this->com_address));
			//$this->com_logobrand	= htmlspecialchars(strip_tags($this->com_logobrand));
			
            try{
                $this->conn->beginTransaction();
                $stmt = $this->conn->prepare($sqlQuery);
			    $stmt->bindValue(1,$this->com_name,    	PDO::PARAM_STR);
			    $stmt->bindValue(2,$this->com_tel, 		PDO::PARAM_STR);
                $stmt->bindValue(3,$this->com_fax,     	PDO::PARAM_STR);
                $stmt->bindValue(4,$this->com_mobile,   PDO::PARAM_STR);
				$stmt->bindValue(5,$this->com_email,    PDO::PARAM_STR);
				$stmt->bindValue(6,$this->city_id,      PDO::PARAM_INT);
				$stmt->bindValue(7,$this->com_address,  PDO::PARAM_STR);
				//$stmt->bindValue(8,$this->com_logobrand,PDO::PARAM_STR);
			    if($stmt->execute()){
		            echo json_encode(array('s','تم الحفظ بنجاح'));
		            $this->conn->commit();
			    }else{
			        echo json_encode(array('e','لم يتم الحفظ ... حدث خطاء غير معروف'));
			        $this->conn->rollback();
			    }
		    } catch (PDOException $e) {
                echo json_encode(array('e','حدث خطاء  - رسالة الخطاء: '.$e->getMessage(),$e->getCode()));
                $this->conn->rollback();
	        }
	}
	
	
}