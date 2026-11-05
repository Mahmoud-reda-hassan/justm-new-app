<?php
class Shipping_type_tbl {
	private $shipping_type_Tbl = 'SHIPPING_TYPE';
	public  $shipping_t_id;
    public  $shipping_t_name;
	private $conn;
	
	public function __construct($db){
        $this->conn = $db;
    }
    
	public function  getdropShippingType(){
		$sqlQuery = 'select * from ' .$this->shipping_type_Tbl;
		$stmt = $this->conn->prepare($sqlQuery);
		$stmt->execute();
		$result = $stmt;
		$records = array();
		while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
		     $rows=array();
		     $rows[] = $record["SHIPPING_T_ID"];
		     $rows[] = $record["SHIPPING_T_ID"];
		     $rows[] = $record["SHIPPING_T_NAME"];
		     $records[] = $rows;
		}
        echo json_encode($records);
	}
	
}

?>