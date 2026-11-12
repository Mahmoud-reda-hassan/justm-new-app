<?php
/*================================================
**  Product Category Table From Database
**==============================================*/
class Product_category_tbl {
	private $procat_tbl = 'PRODUCT_CATEGORY';
	private $products_tbl= 'PRODUCTS';
	public $cat_id;
    public $cat_name;
    public $cat_parent;
    public $country_id;
    public $regionnal_areas_id;
	private $conn;
	public function __construct($db){
        $this->conn = $db;
    } //end function

    public function listProductCategories(){
        $sqlQuery = 'select * from ' .$this->procat_tbl . ' ';
		if (!empty($this->cat_id)) {
			$sqlQuery .= ' WHERE CAT_ID = ? ';
			$this->cat_id        = htmlspecialchars(strip_tags(trim($this->cat_id 	 ," ")));
		}
		$sqlQuery .= ' ORDER BY CAT_ID ';
		$stmt = $this->conn->prepare($sqlQuery);
		if (!empty($this->cat_id)) {
			$stmt->bindValue(1,$this->cat_id,PDO::PARAM_STR); 
		}
		$stmt->execute();
		$data = array();
		while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) { 
			$rows = array();
			$rows['CAT_ID'] = $record['CAT_ID'];
			$rows['id'] = $record['CAT_ID'];
			$rows['text'] = $record['CAT_NAME'];
			$rows['icon'] =  "fa fa-folder";
			$rows['class'] = "checked" ;
			$rows['parent_id'] = $record['CAT_PARENT'];
			//$rows['href'] = '?addProduct=1&CatId='.$record['CAT_ID'];
			array_push($data, $rows);
		}
		if (empty($this->cat_id)) {
			//$data =	$records;
			$itemsByReference = array();
			// Build array of item references:
			foreach($data as $key => &$item) {
			   $itemsByReference[$item['CAT_ID']] = &$item;
			   // Children array:
			   $itemsByReference[$item['CAT_ID']]['nodes'] = array();
			}

			// Set items as children of the relevant parent item.
			foreach($data as $key => &$item)  {
			//echo "<pre>";print_r($itemsByReference[$item['parent_id']]);die;
			   if($item['parent_id'] && isset($itemsByReference[$item['parent_id']])) {
				  $itemsByReference [$item['parent_id']]['nodes'][] = &$item;
				}
			}
			// Remove items that were added to parents elsewhere:
			foreach($data as $key => &$item) {
				if(empty($item['nodes'])) {
					unset($item['nodes']);
				 }
				if($item['parent_id'] && isset($itemsByReference[$item['parent_id']])) {
					unset($data[$key]);
				 }
			}
		}
	    // Encode:
	    echo json_encode($data);

    } //end function
    public function addProductCategories(){
        $sqlQuery = 'INSERT INTO ' . $this->procat_tbl . ' (`CAT_NAME`, `CAT_PARENT`) VALUES ( ?, ?);';
        $this->cat_name      = htmlspecialchars(strip_tags(trim($this->cat_name	 ," ")));
	    $this->cat_parent    = htmlspecialchars(strip_tags(trim($this->cat_parent," ")));
		try{
		    $this->conn->beginTransaction();
		    $stmt = $this->conn->prepare($sqlQuery);
		    $stmt->bindValue(1,$this->cat_name, 	PDO::PARAM_STR);
		    $stmt->bindValue(2,$this->cat_parent, 	PDO::PARAM_STR);
		    if($stmt->execute()){
		        echo json_encode($stmt->rowCount());
				$this->conn->commit();
			}else{
				$this->conn->rollback();
			}
		} catch (PDOException $e) {
			echo json_encode('error'.$e->getCode()); //.$e->getMessage();
			$this->conn->rollback();
		}
    } //end function
    public function updateProductCategories(){
        $sqlQuery = ' UPDATE ' . $this->procat_tbl . ' SET CAT_NAME = ? , CAT_PARENT = ? WHERE CAT_ID = ? ; ';
        $this->cat_name      = htmlspecialchars(strip_tags(trim($this->cat_name	 ," ")));
	    echo $this->cat_parent;
	    if($this->cat_parent == null || $this->cat_parent <=0 || empty($this->cat_parent) ){
			if($this->cat_parent !== $this->cat_id ){
			    $this->cat_parent    = 0;
			} else {
			    $this->cat_parent    = 1;
			}
		} else {
			$this->cat_parent    = htmlspecialchars(strip_tags(trim($this->cat_parent," ")));
		}
		echo $this->cat_parent;
		$this->cat_parent    = htmlspecialchars(strip_tags(trim($this->cat_parent," ")));
		
		try{
		    $this->conn->beginTransaction();
		    $stmt = $this->conn->prepare($sqlQuery);
		    $stmt->bindValue(1,$this->cat_name, 	PDO::PARAM_STR);
		    $stmt->bindValue(2,$this->cat_parent, 	PDO::PARAM_STR);
		    $stmt->bindValue(3,$this->cat_id,     	PDO::PARAM_STR); 
		    
		    if($stmt->execute()){
		        echo json_encode($stmt->rowCount());
				$this->conn->commit();
			}else{
				$this->conn->rollback();
			}
		} catch (PDOException $e) {
			echo json_encode('error'.$e->getCode()); //.$e->getMessage();
			$this->conn->rollback();
		}
    } //end function
    public function deleteProductCategories(){
        $sqlQuery0 = 'UPDATE '.$this->procat_tbl .' SET CAT_PARENT=1 WHERE FIND_IN_SET (CAT_PARENT , ? ) AND CAT_ID<>1 ; ';
        $sqlQuery = ' DELETE FROM ' . $this->procat_tbl . ' WHERE FIND_IN_SET (CAT_ID , ? ) AND CAT_ID <>1; ';
   		$this->cat_id        = htmlspecialchars(strip_tags(trim($this->cat_id 	 ," ")));
		try{
		    $this->conn->beginTransaction();
		    
		    $stmt0 = $this->conn->prepare($sqlQuery0);
		    $stmt0->bindValue(1,$this->cat_id,PDO::PARAM_STR);
		    if($stmt0->execute()){
		        $stmt = $this->conn->prepare($sqlQuery);
		        $stmt->bindValue(1,$this->cat_id,PDO::PARAM_STR);
		        if($stmt->execute()){
		            echo json_encode($stmt->rowCount());
				    $this->conn->commit();
		        }
			}else{
				$this->conn->rollback();
			}
		} catch (PDOException $e) {
			echo json_encode('error'.$e->getCode()); //.$e->getMessage();
			$this->conn->rollback();
		}
    } //end function
    public function getdropProductCategories(){
        $sqlQuery = "SELECT * FROM " . $this->procat_tbl ."  ";
		if(!empty($this->cat_id)) {
		    $sqlQuery .= " AND FIND_IN_SET (CAT_ID , ? ) ";
		}
		$sqlQuery .= " LIMIT 0,590000 ";
	    $stmt = $this->conn->prepare($sqlQuery);
		if(!empty($this->cat_id)) {
			$this->cat_id = htmlspecialchars(strip_tags($this->cat_id));
			$stmt->bindValue(1, $this->cat_id , PDO::PARAM_STR);
		}
		$stmt->execute();
		$result = $stmt;
		$records = array();
		while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
			$rows = array();
			$rows[] = $record['CAT_ID']; 
			$rows[] = $record['CAT_ID']; 
			$rows[] = $record['CAT_NAME'];
			$records[] = $rows;
		}
		echo json_encode($records);
    } //end function
    public function getdropProCat(){
		 $sqlQuery = "SELECT A.CAT_ID,A.PRO_NAME FROM " . $this->products_tbl ." A LEFT JOIN " . $this->procat_tbl ." B ON A.CAT_ID = A.CAT_ID ";
		if(!empty($this->cat_id)) {
		    $sqlQuery .= " WHERE FIND_IN_SET (A.CAT_ID , ? ) GROUP BY A.CAT_ID,A.PRO_NAME ";
		}
	    $stmt = $this->conn->prepare($sqlQuery);
		if(!empty($this->cat_id)) {
			$this->cat_id = htmlspecialchars(strip_tags($this->cat_id));
			$stmt->bindValue(1, $this->cat_id , PDO::PARAM_STR);
		}
		$stmt->execute();
		$result = $stmt;
		$records = array();
		while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
			$rows = array();
			$rows[]  = $record['CAT_ID'];
			$rows[]  = $record['PRO_NAME'];
			$records[] = $rows;
		}
		echo json_encode($records);
    }//end function
} //end class
?>