<?php

/*================================================
**  Client Database Table
**==============================================*/
class Suppliers_tbl {
    private $client_Tbl = "CLIENT";
    public $client_id;
    public $client_name;
    public $client_email;

    private $addresses_Tbl = "ADDRESSES";
    public $address_id;
    public $city_id;
    public $address_title;
    public $defaultadd;
    public $address;

    private $telephones_Tbl = "TELEPHONES";
    public $tel_id;
    public $defaulttel;
    public $whatsapp;
    public $telegeam;
    public $tel_type;
    public $tel_namber;
    
    private $conn;
    // تنظيف المدخلات
    private function sanitizeInput($input) {
        return htmlspecialchars(strip_tags(trim($input)));
    }
    // استعلام البحث في الهواتف
    private function fetchTelephones($clientId, $telNumber) {
        $query = 'SELECT * 
                  FROM TELEPHONES 
                  WHERE SUPPLIER_ID IS NOT NULL AND 
                        (TEL_NUMBER = ? OR SUPPLIER_ID = ?)';
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(1, $telNumber, PDO::PARAM_STR);
        $stmt->bindValue(2, $clientId, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // استعلام البحث في العناوين
    private function fetchAddresses($clientIds) {
        $query = 'SELECT A.ADDRESS_ID, A.SUPPLIER_ID, A.ADDRESS_TITLE, A.DEFAULTADD, 
                         A.CITY_ID, B.CI_NAME, B.ZONE_ID, C.GOV_ID, C.GOV_NAME, 
                         C.COUNTRY_ID, D.CO_NAME, A.ADDRESS, E.SHIPPING_LOWEST, 
                         E.SHIPPING_RATE, E.SHIPPING_TIME_MAX, E.STORE_ID
                  FROM ADDRESSES A
                  LEFT JOIN CITY B ON A.CITY_ID = B.CI_ID
                  LEFT JOIN GOV C ON B.GOV_ID = C.GOV_ID
                  LEFT JOIN COUNTRIES D ON C.COUNTRY_ID = D.CO_ID
                  LEFT JOIN ZONES E ON B.ZONE_ID = E.ZONE_ID
                  WHERE A.SUPPLIER_ID IN (' . implode(',', array_fill(0, count($clientIds), '?')) . ')';
        
        $stmt = $this->conn->prepare($query);
        foreach ($clientIds as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // استعلام البحث في الموردين
    private function fetchSuppliers($clientIds) {
        $query = 'SELECT * FROM SUPPLIERS WHERE SUP_ID IN (' . implode(',', array_fill(0, count($clientIds), '?')) . ')';
        
        $stmt = $this->conn->prepare($query);
        foreach ($clientIds as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // توليد HTML
    public function __construct($db) {
        $this->conn = $db;
    }
	private function generateHtml($telephones, $addresses, $suppliers){
	    $html = "";
	    $supplierData = [];
	
	    // تجميع أرقام الهواتف
	    foreach ($telephones as $tel) {
	        $supplierId = $tel['SUPPLIER_ID'];
	        $supplierData[$supplierId]['telephones'][] = $tel;
	    }
	
	    // تجميع العناوين
	    foreach ($addresses as $address) {
	        $supplierId = $address['SUPPLIER_ID'];
	        $supplierData[$supplierId]['addresses'][] = $address;
	    }
	
	    // إنشاء HTML
	    foreach ($suppliers as $supplier) {
	        $supplierId = $supplier['SUP_ID'];
	        $html .= '<div id="clientview" class="card card-widget widget-user-2">';
	        $html .= '<div class="widget-user-header">';
	        $html .= '<div class="widget-user-image"><img class="img-circle elevation-2" src="../../img/usericon.png" alt="User Avatar"></div>';
	        $html .= '<h3 class="widget-user-username text-primary">' . htmlspecialchars($supplier['SUP_NAME']) . '</h3>';
	        $html .= '<h5 class="widget-user-desc">' . htmlspecialchars($supplier['SUP_EMAIL']) . '</h5>';
	        $html .= '</div>';
	
	        // الهواتف
	        $html .= '<div class="card-body p-">';
	        $html .= '	<div class="row">';
            $html .= '		<div class="col-7">';
            $html .= '			<label> هاتف المورد </label>';
            $html .= '		</div>';
            $html .= '		<div class="col-5 text-right">';
            $html .= '			<button id="#newtel" data-cli_id="'.$supplier['SUP_ID'].'" class="btn btn-app  newtel" style="font-size: 9px; min-width: 30px; height: 30px !important;">';
            $html .= '				<i class="fa fa-plus "></i>';
            $html .= '			</button>';
            $html .= '		</div>';
            $html .= '	</div>';
	        $html .= '<table class="table"><tbody>';
	        foreach ($telephones as $tel) {
	           
	                $html .= '<tr><td>';
	                $html .= '<label>' . htmlspecialchars($tel['TEL_NUMBER']) . '</label>';
	                $html .= '</td> <td class="text-center py-0 align-middle">';
             		$html .= '<div class="btn-group btn-group-sm">';
                    if($tel['WHATSAPP']){  
                    	$html .= '<a href="#" class="btn " style="color:#128c7e"><i class="fa-brands fa-whatsapp" style="font-size:20px"></i></a>';
                    }
                    if($tel['TELEGRAM']){
                    	$html .= '<a href="#" class="btn" style="color:#0088cc"><i class="fa-brands fa-telegram" style="font-size:20px"></i></a>';
                    }
                    $html .= '</td><td class="text-right py-0 align-middle"> <div class="btn-group btn-group-sm">';
                    $html .= '<button onclick="edittell('.$tel['TEL_ID'].',`'.$tel["TEL_NUMBER"].'`,'. $tel['WHATSAPP'].','.$tel['TELEGRAM'].','.$tel['DEFAULTTELL'].','.$tel['SUPPLIER_ID'].')" class="btn btn-info editTel"><i class="fas fa-edit"></i></button>';
                    if($tel['DEFAULTTELL']){
                    	$html .= '<button class="btn btn-danger delTel" disabled><i class="fas fa-trash"></i></button>';
                    } else {
                    	$html .= '<button onclick="delclitel('.$tel['SUPPLIER_ID'].','. $tel['TEL_ID'].',`'. $tel['TEL_NUMBER'].'`)" class="btn btn-danger delTel"><i class="fas fa-trash"></i></button>';
                    }
                    $html .= '</div>';
                                
	                $html .= '</td></tr>';
	        }
	        $html .= '</tbody></table>';
	
	        // العناوين
	        $html .= '<div style="padding: 10px;">';
	        $html .= '<label> عنوان المورد </label>';
	        $html .= '<table class="table"><tbody>';
	        if (!empty($supplierData[$supplierId]['addresses'])) {
	            foreach ($supplierData[$supplierId]['addresses'] as $address) {
	                $html .= '<tr><td>';
	                $html .= '<label>' . htmlspecialchars($address['ADDRESS']) . ' - ' . htmlspecialchars($address['CI_NAME']) . '</label>';
	                $html .= '</td></tr>';
	            }
	        }
	        $html .= '</tbody></table></div>';
	
	        $html .= '</div></div>';
	    }
	
	    return $html;
	}
    public function searchSuppliers($clientId, $telNumber) {
        try {
            $clientId = $this->sanitizeInput($clientId);
            $telNumber = $this->sanitizeInput($telNumber);
            //$addressId = $this->sanitizeInput($addressId);

            // جلب البيانات
            $telephones = $this->fetchTelephones($clientId, $telNumber);

            $clientIds = array_unique(array_column($telephones, 'SUPPLIER_ID'));
            $addresses = $this->fetchAddresses($clientIds);
            $suppliers = $this->fetchSuppliers($clientIds);

            // توليد النتائج
            $html = $this->generateHtml($telephones, $addresses, $suppliers);

            echo json_encode($html);

        } catch (PDOException $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }
    public function supplierslist () {
		$serch = isset($_GET['query']) ? $_GET['query'] : "";
		$query = "SELECT * FROM suppliers WHERE SUP_NAME LIKE '%".$serch."'";
	
		$stmt = $this->conn->prepare($query);
		//$stmt->bindValue(1, $serch, PDO::PARAM_STR);
		$stmt->execute();
		$returnData = [];
		//$returnData .="<select id='itemName' class='form-control p-1 sw2'>";
	

		while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$returnData[] = ['id' =>$record['SUP_ID'],'name' => $record['SUP_NAME']];
		}
		return $returnData;
	}
	public function listSuppliers (){
    	$where = '';
    	$order = '';
    	$limit = '';
        $sqlQuery  = "SELECT * FROM (
        				SELECT 
         				 SU.SUP_ID,
						 SU.SUP_NAME,
						 SU.SUP_EMAIL,
						 TE.TEL_ID,
						 TE.WHATSAPP,
						 TE.TELEGRAM,
						 TE.TEL_LTYPE,
						 TE.TEL_NUMBER,
						 AD.ADDRESS_ID,
						 AD.ADDRESS_TITLE,
						 Concat(AD.ADDRESS,' - ',CI.CI_NAME,' - ',GO.GOV_NAME,' - ',CO.CO_NAME) AS ADDRESS,
						 INV.INVO_COUNT
						FROM  SUPPLIERS AS SU
						 LEFT JOIN telephones AS TE ON SU.SUP_ID = TE.SUPPLIER_ID AND DEFAULTTELL = 1
						 LEFT JOIN ADDRESSES AS AD ON SU.SUP_ID = AD.SUPPLIER_ID AND DEFAULTTELL = 1
						 LEFT JOIN city AS CI ON CI.CI_ID = AD.CITY_ID
						 LEFT JOIN gov AS GO ON CI.GOV_ID = GO.GOV_ID
						 LEFT JOIN countries CO ON CO.CO_ID = GO.GOV_ID 
						 LEFT JOIN (
						  	SELECT SUPPLIER_ID, COUNT(ORDER_ID) AS INVO_COUNT
							FROM purchases_orders
							GROUP BY SUPPLIER_ID
 						) AS INV ON SU.SUP_ID = INV.SUPPLIER_ID
						) AS TBL ";
		
		
		if(isset($_POST['search']) && $_POST['search']['value'] != '') {
            $globalSearch = array();
            $str = htmlspecialchars(strip_tags(trim($_POST['search']['value'])));
			if (strpos($str, ":") !== false) {
			    list($ShKey, $ShVal) = explode(":", $str, 2);
			} else {
			    $ShKey = $str;
			    $ShVal = false; // تعيين قيمة فارغة إذا لم توجد نقطتين
			}
			$key = strtoupper($ShKey);
			switch (true) {
            	case ($ShVal && ($key === '1' || $key === 'A' || $key === 'NAME' || $key === 'الاسم')):
					$globalSearch[] = 'SUP_NAME		LIKE "%'.$ShVal.'%" ';
            		break;
           		case ($ShVal && ($key === '2' || $key === 'B' || $key === 'EMAIL' || $key === 'البريد')):
            		$globalSearch[] = 'SUP_EMAIL 	LIKE "%'.$ShVal.'%" ';
            		break;
           		case ($ShVal && ($key === '3' || $key === 'C' || $key === 'ADDRESS' || $key === 'عنوان' ||  $key === 'العنوان')):
           			$globalSearch[] = 'ADDRESS  		LIKE "%'.$ShVal.'%" ';
            		break;
            	case ($ShVal && ($key === '4' || $key === 'D' || $key === 'TEL' || $key === 'TELEPHONE' || $key === 'PHONE' || $key === 'MOBILE' || $key === 'هاتف' ||  $key === 'موبيل' ||  $key === 'تليفون' ||    $key === 'الهاتف')):
            		$globalSearch[] = 'TEL_NUMBER    	LIKE "%'.$ShVal.'%" ';
           			break;
           		default :
           			$globalSearch[] = 'SUP_NAME		LIKE "%'.$str.'%" ';
					$globalSearch[] = 'TEL_NUMBER    	LIKE "%'.$str.'%" ';
					//$globalSearch[] = 'CLIENT_EMAIL 	LIKE "%'.$str.'%" ';
					$globalSearch[] = 'ADDRESS  		LIKE "%'.$str.'%" ';
           			break;
            }
								
			
			$where = $where === '' ? ' WHERE ('.implode(' OR ', $globalSearch).')' : $where .' AND ('.implode(' OR ', $globalSearch).')';
			
		}
		if(isset($_POST['order'])){
			$columnsArr = ['SUP_ID', 'SUP_NAME', 'ADDRESS', 'TEL_NUMBER', 'INVO_COUNT'];
            $order =  ' ORDER BY '.$columnsArr[$_POST['order']['0']['column']].' '.$_POST['order']['0']['dir'].' ';
        }
		// إنشاء جملة  limit  لاستعلام SQL لمعالجة البيانات من جانب الخادم
		if(isset($_POST['start']) && $_POST['length'] != -1 ) {
			$limit = " LIMIT ".intval($_POST['start']).", ".intval($_POST['length']." ");
		}

		$stmt = $this->conn->prepare($sqlQuery.$where.$order.$limit);
		$stmt->execute();
		$result = $stmt;
		
		$stmtTotal = $this->conn->prepare($sqlQuery);
		$stmtTotal->execute();
		$allResult = $stmtTotal;

		$records = array();
		
		while ($record = $result->fetch(PDO::FETCH_ASSOC)) { 
		    	if($record['INVO_COUNT'] <=0){
				    $colF = '<input id="'.$record['SUP_ID'].'" type="checkbox">';
				} else {
				    $colF = '#';
				}
			
				$colL = '<button id="#editSupfromOrder" data-supid="'.$record['SUP_ID'].'" data-telid="'.$record['TEL_ID'].'" date-addid="'.$record['ADDRESS_ID'].'" data-telstr="'.$record['TEL_LTYPE'].'" class="btn btn-app editSupOrder" style="font-size: 9px; min-width: 30px; height: 30px !important;">
                             <i class="fa fa-edit "></i>
                        </button>';
		    	$Item = array(
		    		$colF,
		    		$record['SUP_NAME'],
		    		$record['SUP_EMAIL'],
		    		$record['ADDRESS'],
		    		$record['TEL_NUMBER'],
		    		$colL
		    	);
		    	
		    	$records[] = $Item;
		}
		$output = array(
			    "draw"	                =>	$_POST['draw'],
			    "iTotalRecords"	        =>	$result->rowCount(),
			    "iTotalDisplayRecords"	=>  $allResult->rowCount(),
			    "data"	                => 	$records
		);

        return $output;
        $this->conn = null;
        exit();
    }
    public function addClient(){
        $sqlQuery  = ' SELECT A.SUP_EMAIL,
 C.TEL_NUMBER
FROM  SUPPLIERS A
 LEFT JOIN ADDRESSES B
 ON A.SUP_ID = B.SUPPLIER_ID AND B.SUPPLIER_ID IS NOT NULL 
 LEFT JOIN TELEPHONES C
 ON A.SUP_ID = C.SUPPLIER_ID AND C.SUPPLIER_ID IS NOT NULL 
WHERE  C.TEL_NUMBER = ? OR A.SUP_EMAIL = ?';     
        $sqlQuery0 = ' INSERT INTO `SUPPLIERS`(`SUP_NAME`, `SUP_EMAIL`) VALUES (?,?) ';
	    $sqlQuery1 = ' INSERT INTO `ADDRESSES`(`SUPPLIER_ID`,`CITY_ID`, `ADDRESS_TITLE`, `DEFAULTADD`, `ADDRESS`) VALUES (?,?,?,?,?) ';
        $sqlQuery2 = ' INSERT INTO `TELEPHONES`(`SUPPLIER_ID`, `DEFAULTTELL`, `WHATSAPP`, `TELEGRAM`, `TEL_LTYPE`, `TEL_NUMBER`) VALUES (?,?,?,?,?,?) ';
        
        $this->client_name     = htmlspecialchars(strip_tags($this->client_name));
        if($this->client_email !==""){
            $this->client_email = htmlspecialchars(strip_tags($this->client_email));
        }else{
            $this->client_email = null;
        }
        $this->city_id         = htmlspecialchars(strip_tags($this->city_id));
        $this->address_title   = htmlspecialchars(strip_tags($this->address_title));
        $this->defaultadd      = htmlspecialchars(strip_tags(1));
        $this->address         = htmlspecialchars(strip_tags($this->address));
        $this->defaulttel      = htmlspecialchars(strip_tags(1));
        $this->whatsapp        = htmlspecialchars(strip_tags($this->whatsapp));
        $this->telegeam        = htmlspecialchars(strip_tags($this->telegeam));
        $this->tel_type        = htmlspecialchars(strip_tags($this->tel_type));
        $this->tel_namber      = htmlspecialchars(strip_tags($this->tel_namber));
   
        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare($sqlQuery);
            $stmt->bindValue(1,trim($this->tel_namber," "), PDO::PARAM_STR);
            $stmt->bindValue(2,$this->client_email,  PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt;
            if ($stmt->rowCount()<=0) {
                $stmt0 = $this->conn->prepare($sqlQuery0);
                $stmt0->bindValue(1,$this->client_name,   PDO::PARAM_STR);
                $stmt0->bindValue(2,$this->client_email,  PDO::PARAM_STR);
                if ($stmt0->execute() && $stmt0->rowCount()>0) {
                    $this->client_id = "";
                    $this->client_id = $this->conn->lastInsertId();
                    $stmt1 = $this->conn->prepare($sqlQuery1);
                    $stmt1->bindValue(1,$this->client_id,     PDO::PARAM_INT);
                    $stmt1->bindValue(2,$this->city_id,       PDO::PARAM_INT);
                    $stmt1->bindValue(3,$this->address_title, PDO::PARAM_STR);
                    $stmt1->bindValue(4,$this->defaultadd,    PDO::PARAM_INT);
                    $stmt1->bindValue(5,trim($this->address," "), PDO::PARAM_STR);
                    if ($stmt1->execute() && $stmt1->rowCount()>0) {
                        $this->address_id = "";
                        $this->address_id = $this->conn->lastInsertId();
                        $stmt2 = $this->conn->prepare($sqlQuery2);
                        $stmt2->bindValue(1,$this->client_id,  PDO::PARAM_STR);
                        $stmt2->bindValue(2,$this->defaulttel, PDO::PARAM_INT);
                        $stmt2->bindValue(3,$this->whatsapp,   PDO::PARAM_INT);
                        $stmt2->bindValue(4,$this->telegeam,   PDO::PARAM_INT);
                        $stmt2->bindValue(5,$this->tel_type,   PDO::PARAM_STR);
                        $stmt2->bindValue(6,trim($this->tel_namber," "), PDO::PARAM_STR);
                        if ($stmt2->execute() && $stmt2->rowCount()>0) {
                            $this->tel_id = "";
                            $this->tel_id = $this->conn->lastInsertId();
                            $this->conn->commit();
                            $arr=array('done',$this->client_id,$this->address_id,$this->tel_id);
                            echo json_encode($arr);
                        } else {
                            $arr = array("error","حدث خطاء غير متوقع - لم يتم حفظ رقم الهاتف لأسباب غير معروفه");
                            echo json_encode($arr);
                            $this->conn->rollback();
                        }
                    } else {
                        $arr = array("error","خطاء غير متوقع - لم يتم حفظ العنون لاسباب غير معروفه");
                        echo json_encode($arr);
                        $this->conn->rollback();
                    }
                } else {
                    $arr = array("error","حدث خطاء غير متوقع - لم يتم حفظ بيانات العميل لإسباب غير معروفه");
                    echo json_encode($arr);
                    $this->conn->rollback();
                }
            } else {
                $this->conn->commit();
                $dbarr  = array();
                $tel    = array();
                $arr    = array();
                $arr[]  = "msg";
                while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
                    $dbarr[] = strtolower($record['CLIENT_EMAIL']);
                    $dbarr[] = strtolower($record['TEL_NUMBER']);
                }
                if(in_array(strtolower($this->tel_namber),$dbarr,true)){
                    $arr[] = $this->tel_namber;
                } else {
                    $arr[] =" ";
                }
                if(in_array(strtolower($this->client_email),$dbarr,true)){
                    $arr[] = $this->client_email;
                } else {
                    $arr[] =" ";
                }
                echo json_encode($arr);
            }
        } catch (PDOException $e) {
            $this->conn->rollback();
            $arr = array("errorConn",$e->getCode() .' - '.$e->getMessage());
            echo json_encode($arr);
        } 
    }
    public function addSupplierTel(){
        $sqlQuery  = ' SELECT TEL_NUMBER FROM TELEPHONES WHERE SUPPLIER_ID IS NOT NULL AND TEL_NUMBER = ? ';    
        $sqlQuery1 = ' UPDATE TELEPHONES SET DEFAULTTELL=0 WHERE FIND_IN_SET (SUPPLIER_ID,?) AND DEFAULTTELL = 1';
        $sqlQuery2 = ' INSERT INTO `TELEPHONES`(`SUPPLIER_ID`, `DEFAULTTELL`, `WHATSAPP`, `TELEGRAM`, `TEL_LTYPE`, `TEL_NUMBER`) VALUES (?,?,?,?,?,?) ';
        $this->client_id       = htmlspecialchars(strip_tags($this->client_id));
        $this->defaulttel      = htmlspecialchars(strip_tags($this->defaulttel));
        $this->whatsapp        = htmlspecialchars(strip_tags($this->whatsapp));
        $this->telegeam        = htmlspecialchars(strip_tags($this->telegeam));
        $this->tel_type        = htmlspecialchars(strip_tags($this->tel_type));
        $this->tel_namber      = htmlspecialchars(strip_tags($this->tel_namber));
        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare($sqlQuery);
            $stmt->bindValue(1,trim($this->tel_namber," "), PDO::PARAM_STR);
            $stmt->execute();
            //$result = $stmt;
            if ($stmt->rowCount()<=0) {
               
                if($this->defaulttel == 1){
                    $stmt1 = $this->conn->prepare($sqlQuery1);
                    $stmt1->bindValue(1,$this->client_id, PDO::PARAM_STR);
                    $stmt1->execute();
                    $return =$stmt1->rowCount();
                } else {
                    $return = 1;
                }
                if ($return > 0) {
                    $stmt2 = $this->conn->prepare($sqlQuery2);
                    $stmt2->bindValue(1,$this->client_id,  PDO::PARAM_STR);
                    $stmt2->bindValue(2,$this->defaulttel, PDO::PARAM_INT);
                    $stmt2->bindValue(3,$this->whatsapp,   PDO::PARAM_INT);
                    $stmt2->bindValue(4,$this->telegeam,   PDO::PARAM_INT);
                    $stmt2->bindValue(5,$this->tel_type,   PDO::PARAM_STR);
                    $stmt2->bindValue(6,trim($this->tel_namber," "), PDO::PARAM_STR);
                    if ($stmt2->execute() && $stmt2->rowCount()>0) {
                        $this->conn->commit();
                        $arr = array("s","تم حفظ رقم الهاتف بنجاح ");
                        echo json_encode($arr);
                    } else {
                        $arr = array("w","حدث خطاء غير متوقع - لم يتم حفظ رقم الهاتف  - Error code : I-3000");
                        echo json_encode($arr);
                        $this->conn->rollback();
                    }
                } else {
                    $this->conn->commit();
                    $arr = array("e","حدث خطاء غير متوقع - لم يتم حفظ رقم الهاتف  -Error code : I-3001 ");
                    echo json_encode($arr);
                    
                }
            } else {
                $this->conn->commit();
                echo json_encode(array("msg","خطاء - رقم الهاتف الذى تم ادخاله مسجل مسبقا بقواعد البيانات"));
            }
        } catch (PDOException $e) {
            $this->conn->rollback();
            $arr = array("e",$e->getCode() .' - '.$e->getMessage());
            echo json_encode($arr);
        } 
    }
    public function updateSupplierTel(){
        $sqlQuery  = ' SELECT TE.TEL_NUMBER FROM SUPPLIERS SU LEFT JOIN TELEPHONES TE ON SU.SUP_ID = TE.SUPPLIER_ID  WHERE SUPPLIER_ID IS NOT NULL AND TEL_NUMBER = ? AND TEL_ID <> ? ';    
        $sqlQuery1 = ' UPDATE TELEPHONES SET DEFAULTTELL=0 WHERE FIND_IN_SET (SUPPLIER_ID,(SELECT SUPPLIER_ID FROM TELEPHONES WHERE SUPPLIER_ID IS NOT NULL AND TEL_NUMBER = ? )) ';
        $sqlQuery2 = ' UPDATE TELEPHONES SET DEFAULTTELL=?,WHATSAPP=?, TELEGRAM =?,TEL_LTYPE=?,TEL_NUMBER=? WHERE FIND_IN_SET (TEL_ID,?) ';
        $this->tel_id          = htmlspecialchars(strip_tags($this->tel_id));
        $this->defaulttel      = htmlspecialchars(strip_tags($this->defaulttel));
        $this->whatsapp        = htmlspecialchars(strip_tags($this->whatsapp));
        $this->telegeam        = htmlspecialchars(strip_tags($this->telegeam));
        $this->tel_type        = htmlspecialchars(strip_tags($this->tel_type));
        $this->tel_namber      = htmlspecialchars(strip_tags($this->tel_namber));
        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare($sqlQuery);
            $stmt->bindValue(1,trim($this->tel_namber," "), PDO::PARAM_STR);
            $stmt->bindValue(2,$this->tel_id, PDO::PARAM_INT);
            $stmt->execute();
            //$result = $stmt;
            if ($stmt->rowCount()<=0) {
               
                if($this->defaulttel == 1){
                    $stmt1 = $this->conn->prepare($sqlQuery1);
                    $stmt1->bindValue(1,$this->tel_namber, PDO::PARAM_STR);
                    $stmt1->execute();
                    $return = 1;
                } else {
                    $return = 1;
                }
                if ($return > 0) {
                    $stmt2 = $this->conn->prepare($sqlQuery2);
                    $stmt2->bindValue(1,$this->defaulttel, PDO::PARAM_INT);
                    $stmt2->bindValue(2,$this->whatsapp,   PDO::PARAM_INT);
                    $stmt2->bindValue(3,$this->telegeam,   PDO::PARAM_INT);
                    $stmt2->bindValue(4,$this->tel_type,   PDO::PARAM_STR);
                    $stmt2->bindValue(5,trim($this->tel_namber," "), PDO::PARAM_STR);
                    $stmt2->bindValue(6,$this->tel_id,  PDO::PARAM_STR);
                    if ($stmt2->execute()){
                        $this->conn->commit();
                        echo json_encode(['s','تم تعديل رقم الهاتف بنجاح']);
                    } else {
                        $arr = array("w","حدث خطاء غير متوقع - لم يتم حفظ رقم الهاتف لأسباب غير معروفه");
                        echo json_encode($arr);
                        $this->conn->rollback();
                    }
                } else {
                    $this->conn->commit();
                    $arr = array("w","حدث خطاء غير متوقع - لم يتم حفظ رقم الهاتف لأسباب غير معروفه");
                }
            } else {
                $this->conn->commit();
                echo json_encode(["msg","خطاء - رقم الهاتف الذى تم ادخاله مسجل مسبقا بقواعد البيانات"]);
            }
        } catch (PDOException $e) {
            $this->conn->rollback();
            $arr = array("e",$e->getCode() .' - '.$e->getMessage());
            echo json_encode($arr);
        } 
    }
    public function deleteSupplierTel(){
        $sqlwher   =' SELECT COUNT(TEL_ID) AS COUN_TEL FROM TELEPHONES WHERE FIND_IN_SET(SUPPLIER_ID,?) ' ;
        $sqlQuery  =' DELETE FROM TELEPHONES WHERE TEL_ID = ? AND SUPPLIER_ID = ? AND DEFAULTTELL <> 1 AND ('.$sqlwher.') > 1 ' ;
        $this->client_id       = htmlspecialchars(strip_tags($this->client_id));
        $this->tel_id          = htmlspecialchars(strip_tags($this->tel_id));
        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare($sqlQuery);
            $stmt->bindValue(1,$this->tel_id,  PDO::PARAM_STR);
            $stmt->bindValue(2,$this->client_id,  PDO::PARAM_STR);
            $stmt->bindValue(3,$this->client_id,  PDO::PARAM_STR);
            $stmt->execute();
            if($stmt->rowCount()>0) {
                $this->conn->commit();
                $arr = array('s',"تم حذف رقم الهاتف بنجاح");
                echo json_encode($arr);
            } else {
                $arr = array("w","خطاء غير متوقع - لم يتم حذف العنون لاسباب غير معروفه");
                echo json_encode($arr);
                $this->conn->rollback();
            }
        } catch (PDOException $e) {
            $this->conn->rollback();
            $arr = array("e",$e->getCode() .' - '.$e->getMessage());
            echo json_encode($arr);
        }
    }
    public function addClientAdd(){
	    $sqlQuery = ' INSERT INTO `ADDRESSES`(`CLIENT_ID`,`CITY_ID`, `ADDRESS_TITLE`, `DEFAULTADD`, `ADDRESS`) VALUES (?,?,?,1,?) ';
        $sqlQuery1 = ' UPDATE ADDRESSES SET DEFAULTADD = 0 where FIND_IN_SET( CLIENT_ID , ? ) ';
        
        $this->client_id       = htmlspecialchars(strip_tags($this->client_id));
        $this->city_id         = htmlspecialchars(strip_tags($this->city_id));
        $this->address_title   = htmlspecialchars(strip_tags($this->address_title));
        $this->defaultadd      = 1;
        $this->address         = htmlspecialchars(strip_tags($this->address));
        try {
            
            $this->conn->beginTransaction();
            $stmt1 = $this->conn->prepare($sqlQuery1);
            $stmt1->bindValue(1,$this->client_id, PDO::PARAM_INT);
            if ($stmt1->execute() && $stmt1->rowCount()>0) {
                $stmt = $this->conn->prepare($sqlQuery);
                $stmt->bindValue(1,$this->client_id,     PDO::PARAM_INT);
                $stmt->bindValue(2,$this->city_id,       PDO::PARAM_INT);
                $stmt->bindValue(3,$this->address_title, PDO::PARAM_STR);
                //$stmt->bindValue(4,$this->defaultadd,    PDO::PARAM_INT);
                $stmt->bindValue(4,trim($this->address," "), PDO::PARAM_STR);
                if($stmt->execute() && $stmt->rowCount()>0) {
                    $this->conn->commit();
                    $arr = array("s","تم حفظ العنوان بنجاح");
                    echo json_encode($arr);
                } else {
                    $this->conn->rollback();
                    $arr = array("w","حدث خطاء غير معروف - Error code : I-3000");
                    echo json_encode($arr);
                }
            } else {
                $this->conn->rollback();
                $arr = array("w","حدث خطاء غير معروف - Error code : I-3001");
                echo json_encode($arr);
            }
        } catch (PDOException $e) {
            $this->conn->rollback();
            $arr = array("e",$e->getCode() .' - '.$e->getMessage());
            echo json_encode($arr);
        } 
    }
    public function updateClientAdd(){
        $sqlQuery  = ' UPDATE `ADDRESSES` SET `CITY_ID`= ? , `ADDRESS_TITLE` = ? , `DEFAULTADD` = ? , `ADDRESS` = ? WHERE ADDRESS_ID = ? ';
        $sqlQuery1 = ' UPDATE `ADDRESSES` SET `DEFAULTADD` = 0 where FIND_IN_SET( CLIENT_ID , (SELECT CLIENT_ID FROM ADDRESSES WHERE ADDRESS_ID = ? ) ) ';
        $this->address_id      = htmlspecialchars(strip_tags($this->address_id));
        $this->city_id         = htmlspecialchars(strip_tags($this->city_id));
        $this->address_title   = htmlspecialchars(strip_tags($this->address_title));
        $this->defaultadd      = htmlspecialchars(strip_tags($this->defaultadd));
        $this->address         = htmlspecialchars(strip_tags($this->address));
        
        try {
            $this->conn->beginTransaction();
            if($this->defaultadd*1===1){
                $stmt0 = $this->conn->prepare($sqlQuery1);
                $stmt0->bindValue(1,$this->address_id,     PDO::PARAM_INT);
                $stmt0->execute();
            }
            $stmt = $this->conn->prepare($sqlQuery);
            $stmt->bindValue(1,$this->city_id,       PDO::PARAM_INT);
            $stmt->bindValue(2,$this->address_title, PDO::PARAM_STR);
            $stmt->bindValue(3,$this->defaultadd,    PDO::PARAM_INT);
            $stmt->bindValue(4,trim($this->address," "), PDO::PARAM_STR);
            $stmt->bindValue(5,$this->address_id,     PDO::PARAM_INT);
            if ($stmt->execute()) {
                $this->conn->commit();
                $arr = array("s","تم تحديث بيانات العنوان بنجاح");
                echo json_encode($arr);
            } else {
                $this->conn->rollback();
                $arr = array("e","حدث خطاء غير متوقع - لم يتم حفظ رقم الهاتف لأسباب غير معروفه");
                echo json_encode($arr);
            }
        } catch (PDOException $e) {
            $this->conn->rollback();
            $arr = array("errorConn",$e->getCode() .' - '.$e->getMessage());
            echo json_encode($arr);
        } 
    }
    public function deleteClientAdd(){
        $sqlwher   =' SELECT COUNT(CLIENT_ID) AS COUNTCLI FROM ADDRESSES WHERE FIND_IN_SET(CLIENT_ID, ? )';
        $sqlQuery  =' DELETE FROM ADDRESSES WHERE ADDRESS_ID = ? AND CLIENT_ID = ? AND DEFAULTADD <> 1 AND ('.$sqlwher.')>1 ';
        $this->client_id       = htmlspecialchars(strip_tags($this->client_id));
        $this->address_id      = htmlspecialchars(strip_tags($this->address_id));
        $cliid = $this->client_id ;
        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare($sqlQuery);
            $stmt->bindValue(1,$this->address_id,  PDO::PARAM_STR);
            $stmt->bindValue(2,$this->client_id,   PDO::PARAM_STR);
            $stmt->bindValue(3,$cliid,   PDO::PARAM_STR);
            $stmt->execute();
            if($stmt->rowCount()>0) {
                $this->conn->commit();
                $arr = array("s","تم حذف العنوان بنجاح");
				echo json_encode($arr);
            } else {
                $arr = array("w","خطاء غير متوقع - لم يتم حذف العنون لاسباب غير معروفه");
                echo json_encode($arr);
                $this->conn->rollback();
            }
        } catch (PDOException $e) {
            $this->conn->rollback();
            $arr = array("e",$e->getCode() .' - '.$e->getMessage());
            echo json_encode($arr);
        }
    }
    public function updateClient(){
        
    }
    public function deleteClients(){
        $query = 'DELETE FROM suppliers 
					WHERE SUP_ID = ? 
					AND NOT EXISTS (
					    SELECT 1 FROM purchases_orders 
					    WHERE purchases_orders.SUPPLIER_ID = suppliers.SUP_ID
					);';
	$this->client_id = htmlspecialchars(strip_tags($this->client_id));
        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(1 ,$this->client_id,   PDO::PARAM_STR);
            $stmt->execute();
            if($stmt->rowCount()>0) {
                $this->conn->commit();
                return array("s","تم حذف بيانات المورد بنجاح");
            } else {
            	$this->conn->rollback();
                return array("w","خطاء غير متوقع - لم يتم الخذف ربما يوجد اوامر شراء للمورد");
                
            }
        } catch (PDOException $e) {
            $this->conn->rollback();
            return array("e",$e->getCode() .' - '.$e->getMessage());
        }
        
    }
}