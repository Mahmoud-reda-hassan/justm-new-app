<?php

/*================================================
**  Client Database Table
**==============================================*/
class Client_tbl {
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

    public function __construct($db) {
        $this->conn = $db;
    }
    public function getClientList () {
		$serch = isset($_GET['query']) ? $_GET['query'] : "";
		$query = "SELECT c.CLIENT_ID,c.CLIENT_NAME FROM client c LEFT JOIN telephones t ON t.CLIENT_ID is not null AND c.CLIENT_ID = t.CLIENT_ID WHERE t.TEL_NUMBER LIKE '".$serch."%' OR c.CLIENT_NAME LIKE '".$serch."%'";
	
		$stmt = $this->conn->prepare($query);
		//$stmt->bindValue(1, $serch, PDO::PARAM_STR);
		$stmt->execute();
		$returnData = [];
		//$returnData .="<select id='itemName' class='form-control p-1 sw2'>";
	

		while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$returnData[] = ['id' =>$record['CLIENT_ID'],'name' => $record['CLIENT_NAME']];
		}
		return $returnData;
	}
    public function searchClient(){
        $this->client_id  = htmlspecialchars(strip_tags($this->client_id));
        $this->tel_id     = htmlspecialchars(strip_tags($this->tel_id));
        $this->address_id = htmlspecialchars(strip_tags($this->address_id));
        if($this->client_id <= 0){
            $query_id = "(SELECT CLIENT_ID FROM  TELEPHONES WHERE CLIENT_ID is not null AND TEL_NUMBER = ?)";
        } else {
            $query_id = "?" ;
        }
        $sqlQuery0  = ' SELECT *
                        FROM TELEPHONES A
                        WHERE  A.CLIENT_ID IS NOT NULL AND FIND_IN_SET (A.CLIENT_ID ,'.$query_id.')' ;
        $sqlQuery1  = ' SELECT A.ADDRESS_ID ,
						    A.CLIENT_ID ,
							A.ADDRESS_TITLE,
							A.DEFAULTADD,
							A.CITY_ID,
							B.CI_NAME,
							B.ZONE_ID,
							C.GOV_ID ,
							C.GOV_NAME,
							C.COUNTRY_ID,
							D.CO_NAME,
							A.ADDRESS,
							E.SHIPPING_LOWEST,
							E.SHIPPING_RATE,
							E.SHIPPING_TIME_MAX,
							E.STORE_ID
						FROM ADDRESSES A 
							LEFT  JOIN CITY B 
								ON A.CITY_ID=B.CI_ID 
							LEFT JOIN GOV C 
								ON B.GOV_ID = C.GOV_ID 
						    LEFT JOIN COUNTRIES D 
						        ON C.COUNTRY_ID=D.CO_ID
						    LEFT JOIN ZONES E 
						        ON B.ZONE_ID=E.ZONE_ID
						WHERE CLIENT_ID IS NOT NULL AND FIND_IN_SET (CLIENT_ID , ? ) ' ;
						
        $sqlQuery2  = ' SELECT * 
						FROM CLIENT 
						WHERE FIND_IN_SET (CLIENT_ID , ? ) ';
       
        $client_id = array();
        $radio=0;
        $num = '';
        $kay;
        $tel=array();
        $add=array();
        $cli=array();
        $this->tel_namber      = htmlspecialchars(strip_tags(trim($this->tel_namber," ")));
        try {
		// start qury - run query TELEPHONES
            $stmt0 = $this->conn->prepare($sqlQuery0);
            if($this->client_id > 0){
                
                $stmt0->bindValue(1,$this->client_id, PDO::PARAM_STR);
            } else {
                
                $stmt0->bindValue(1,$this->tel_namber, PDO::PARAM_STR);
            }
            if($stmt0->execute() && $stmt0->rowCount()>0) {
                $result = $stmt0;
                $radio=50;
                while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
                    if(!array_key_exists($record["CLIENT_ID"],$tel)){
                        $tel[$record["CLIENT_ID"]] = array();
                        $radio++;
                        $client_id[]=$record["CLIENT_ID"];
                    }
                    $num = '<div class="form-check col-auto">';
                    if( $this->tel_id == $record["TEL_ID"] || $this->tel_namber == $record["TEL_NUMBER"] ){
                        $num .= '<input id="'.$record["TEL_ID"].'" class="form-check-input telradio" type="radio" name="radio'. $radio .'" checked>';
                    } else {
                        $num .= '<input id="'.$record["TEL_ID"].'" class="form-check-input telradio" type="radio" name="radio'. $radio .'" >';
                    }
                    if($record["DEFAULTTELL"] == 1){
                        $num .= '<label class="row form-check-label text-success" style="margin-right:3px;">'.$record["TEL_NUMBER"].'</label>';
                    } else {
                        $num .= '<label class="row form-check-label text-primary" style="margin-right:3px;">'.$record["TEL_NUMBER"].'</label>';
                    }
                    $num .= '</div>';
                    array_push($tel[$record["CLIENT_ID"]],array($num,$record["WHATSAPP"],$record["TELEGRAM"],$record["TEL_ID"],$record["TEL_NUMBER"],$record["DEFAULTTELL"]));
                }
				$this->client_id = implode(",",$client_id);
				
//run query ADDRESSES
                $stmt1 = $this->conn->prepare($sqlQuery1);
                $stmt1->bindValue(1,trim($this->client_id," "), PDO::PARAM_STR);
                if($stmt1->execute() && $stmt1->rowCount()>0) {
                    $result = $stmt1;
                    $addSTR ='';
                    while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
                        if(!array_key_exists($record["CLIENT_ID"],$add)){
                            $add[$record["CLIENT_ID"]] = array();
                            $radio++;
                        }
                        $addSTR = '<div class="form-check col-auto">';
                        //echo "------------------". $this->address_id . "-".$record["ADDRESS_ID"].'-'.$record["DEFAULTADD"]  ;
                        if((int)$this->address_id === 0 && $record["DEFAULTADD"] ==1 && $record["ZONE_ID"] != null ){
                            $addSTR .= '<input id="'.$record["ADDRESS_ID"].'" data-shilow="'.$record["SHIPPING_LOWEST"].'" data-shirat="'.$record["SHIPPING_RATE"].'" data-shitim="'.$record["SHIPPING_TIME_MAX"].'" date-stoid="'.$record["STORE_ID"].'" data-cityid="'.$record["CITY_ID"].'" class="form-check-input addradio" type="radio" name="radio'.$radio.'" checked>';
                            $addSTR .= '<label class="form-check-label text-success" style="margin-right:3px;">'.$record["ADDRESS"].' - '.$record["CI_NAME"].' - '.$record["GOV_NAME"].' - '.$record["CO_NAME"].'</label>';
                        } else if((int)$this->address_id === $record["ADDRESS_ID"]*1 && $record["ZONE_ID"] != null){
                            $addSTR .= '<input id="'.$record["ADDRESS_ID"].'" data-shilow="'.$record["SHIPPING_LOWEST"].'" data-shirat="'.$record["SHIPPING_RATE"].'" data-shitim="'.$record["SHIPPING_TIME_MAX"].'" date-stoid="'.$record["STORE_ID"].'" data-cityid="'.$record["CITY_ID"].'" class="form-check-input addradio" type="radio" name="radio'.$radio.'" checked>';
                            $addSTR .= '<label class="form-check-label text-success" style="margin-right:3px;">'.$record["ADDRESS"].' - '.$record["CI_NAME"].' - '.$record["GOV_NAME"].' - '.$record["CO_NAME"].'</label>';
                        } else if($record["ZONE_ID"]!=null) {
                            $addSTR .= '<input id="'.$record["ADDRESS_ID"].'" data-shilow="'.$record["SHIPPING_LOWEST"].'" data-shirat="'.$record["SHIPPING_RATE"].'" data-shitim="'.$record["SHIPPING_TIME_MAX"].'" date-stoid="'.$record["STORE_ID"].'" data-cityid="'.$record["CITY_ID"].'" class="form-check-input addradio" type="radio" name="radio'.$radio.'">';
                            $addSTR .= '<label class="form-check-label text-primary" style="margin-right:3px;">'.$record["ADDRESS"].' - '.$record["CI_NAME"].' - '.$record["GOV_NAME"].' - '.$record["CO_NAME"].'</label>';
                        } else {
                        	//$addSTR .= '<input id="'.$record["ADDRESS_ID"].'" data-shilow="0" data-shirat="1000" data-shitim="15" date-stoid="'.$record["STORE_ID"].'" data-cityid="'.$record["CITY_ID"].'" class="form-check-input addradio" type="radio" name="radio'.$radio.'">';
                            $addSTR .= '<label class="form-check-label text-primary" style="margin-right:3px;">'.$record["ADDRESS"].' - '.$record["CI_NAME"].' - '.$record["GOV_NAME"].' - '.$record["CO_NAME"].'</label>';
                        }
                        $addSTR .= '</div>';
                        if($record["ZONE_ID"]==null) {
                            $addSTR .= '<div class="text-danger text-left">الشحن غير متاح حاليا</div>';
                        }
                        array_push($add[$record["CLIENT_ID"]],array($addSTR,$record["ADDRESS_ID"],$record["COUNTRY_ID"],$record["GOV_ID"],$record["CITY_ID"],$record["ADDRESS_TITLE"],$record["ADDRESS"],$record["DEFAULTADD"],$record["SHIPPING_LOWEST"],$record["SHIPPING_RATE"],$record["SHIPPING_TIME_MAX"]));
                    }
                }//END ADDRESS
//run query CLIENTS
                $stmt2 = $this->conn->prepare($sqlQuery2);
                $stmt2->bindValue(1,trim($this->client_id," "), PDO::PARAM_STR);
                if($stmt2->execute() && $stmt2->rowCount()>0) {
                    $result = $stmt2;
                    while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
                        $cli[$record["CLIENT_ID"]] = array($record["CLIENT_ID"],$record["CLIENT_NAME"],$record["CLIENT_EMAIL"]);
                    }
                }//END CLIENTS 
            }//END TEL_NUMBER
//end query
//================================================================
//بيانات العميل (الاسم والبريد الالكترونى)
            $html="";
			for($i = 0; $i < count($client_id); $i++){
                $html .= '<div id="clientview" class="card  card-widget widget-user-2">
                            <div class="widget-user-header mt-3">
							    <div class="widget-user-image">
							        <img class="img-circle elevation-2" src="../../img/usericon.png" alt="User Avatar">
							    </div>
							    <!-- /.widget-user-image -->
							    <h5 class="widget-user-username text-primary" id="client-name-'.$cli[$client_id[$i]][0].'">
							        '. $cli[$client_id[$i]][1] .'
							       <button id="btnEditName" class="btn btn-sm btn-outline-primary btnEditName edit-btn mr-3" data-id="'.$cli[$client_id[$i]][0].'">  
							           <i class="fas fa-edit"></i>
							       </button> 
							    </h5>
							    <h6 class="widget-user-desc">'.$cli[$client_id[$i]][2].'</h6>
							</div>
                            <div class="card-body p-0">
                              <div class="" style="padding: 10px;margin-top: 0px;">
                                <div class="row">
                                    <div class="col-7">
                                        <label> هاتف العميل </label>
                                    </div>
                                    <div class="col-5 text-right">
                                        <button id="#newtel" data-cli_id="'.$cli[$client_id[$i]][0].'" class="btn btn-app  newtel" style="font-size: 9px; min-width: 30px; height: 30px !important;">
                                            <i class="fa fa-plus "></i>
                                        </button>
                                    </div>
                                </div>
                                <table class="table">
                                    <thead></thead>
                                    <tbody>';
// ارقام الهاتف للعميل
                for($x = 0; $x < count($tel[$client_id[$i]]); $x++){
                   
                    $html .='<tr>
                                <td>'.$tel[$client_id[$i]][$x][0].'
                                </td>
					            <td class="text-center py-0 align-middle">
                                    <div class="btn-group btn-group-sm">';
                                      if($tel[$client_id[$i]][$x][1]==1){  
                                          $html .= '<a href="#" class="btn " style="color:#128c7e"><i class="fa-brands fa-whatsapp" style="font-size:20px"></i></a>';
                                      } else {
                                          //$html .= '<a href="#" class="btn" style="color:#128c7e" disabled ><i class="fa-brands fa-whatsapp" style="font-size:20px"></i></a>';
                                      }
                                      if($tel[$client_id[$i]][$x][2] == 1){
                                          $html .= '<a href="#" class="btn" style="color:#0088cc"><i class="fa-brands fa-telegram" style="font-size:20px"></i></a>';
                                      } else {
                                          //$html .= '<a href="#" class="btn" style="color:#0088cc" disabled ><i class="fa-brands fa-telegram" style="font-size:20px"></i></a>';
                                      }
                            $html .= '</div>
                                </td>
                                <td class="text-right py-0 align-middle">
                                    <div class="btn-group btn-group-sm">
                                       <button onclick="edittel('.$tel[$client_id[$i]][$x][3].',`'.$tel[$client_id[$i]][$x][4] .'`,'.$tel[$client_id[$i]][$x][1].','.$tel[$client_id[$i]][$x][2].','.$tel[$client_id[$i]][$x][5].')" class="btn btn-info editTel"><i class="fas fa-edit"></i></button>';
                                       if($tel[$client_id[$i]][$x][5]== 1){
                                            $html .= '<button class="btn btn-danger delTel" disabled><i class="fas fa-trash"></i></button>';
                                        } else {
                                            $html .= '<button onclick="delclitel('.$client_id[$i].','.$tel[$client_id[$i]][$x][3].',`'.$tel[$client_id[$i]][$x][4] .'`)" class="btn btn-danger delTel"><i class="fas fa-trash"></i></button>';
                                        }
                            $html .= '</div>
                                </td>
                            </tr>';
                }
//عنوان العميل
                $html .='</tbody>';
                $html .='</table>';
                $html .= '</div>';
                $html .= '<div class="" style="padding: 10px;margin-top: 0px;">';
                $html .= '<div class="row">
                            <div class="col-7">
                                <label> عنوان العميل </label>
                            </div>
                            <div class="col-5 text-right">
                                <button id="NewAdd" data-cli_id="'.$cli[$client_id[$i]][0].'" class="btn btn-app newAdd" style="font-size: 9px; min-width: 30px; height: 35px !important;">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                         </div>';
                $html .='<table class="table">';
                $html .='<thead>';
                $html .='</thead>';
                $html .='<tbody>';
                
                for($y = 0; $y < count($add[$client_id[$i]]); $y++){
                    $html .='<tr>';
                    $html .='<td>';
                    $html .= $add[$client_id[$i]][$y][0];
                    $html .='</td>';
                    $html .='<td class="text-right py-0 align-middle">';
                    $html .='<div class="btn-group btn-group-sm">';
                    
                    $html .='<button onclick="editAdd('.$add[$client_id[$i]][$y][1].','.$add[$client_id[$i]][$y][2].','.$add[$client_id[$i]][$y][3].','.$add[$client_id[$i]][$y][4].',`'.$add[$client_id[$i]][$y][5].'`,`'.$add[$client_id[$i]][$y][6].'`,'.$add[$client_id[$i]][$y][7].')" class="btn btn-info editTel"><i class="fas fa-edit"></i></button>';
                    if($add[$client_id[$i]][$y][7] == 1){
                        $html .='<button class="btn btn-danger delTel" disabled><i class="fas fa-trash"></i></button>';
                    } else {
                        $html .='<button onclick="delcliAdd('.$client_id[$i].','.$add[$client_id[$i]][$y][1].',$(this))" class="btn btn-danger delTel" ><i class="fas fa-trash"></i></button>';
                    }
                    $html .='</div>';
                    $html .='</td>';
                    $html .='</tr>';
                }
                $html .='</tbody>';
                $html .='</table>';
                $html .='</div><div class="text-center mt-0 mb-3">';
				
				if(!isset($cli[$client_id[$i]][0])){
					$cli[$client_id[$i]][0]="";
				}
				if(!isset($cli[$client_id[$i]][1])){
					$cli[$client_id[$i]][1]="";
				}
				if(!isset($cli[$client_id[$i]][3])){
					$cli[$client_id[$i]][3]="";
				}
				$html .='<button id="btnAddNewOrder" data-cli_id="'.$cli[$client_id[$i]][0].'" data-cli_name="'.$cli[$client_id[$i]][1].'" data-cli_mail="'.$cli[$client_id[$i]][3].'" class="btn  btn-success"> استخدام البيانات المحدده وانشاء امر بيع</button></div></div></div></div>';
            }
            //echo json_encode($tel[$client_id[0]][0]);
            echo json_encode($html);
        } catch (PDOException $e) {
            //$this->conn->rollback();
            $arr = array("errorConn",$e->getCode() .' - '.$e->getMessage());
            echo json_encode($arr);
        } 
    }

    public function listClient(){
    	$where = '';
    	$order = '';
    	$limit = '';
        $sqlQuery  = "SELECT * FROM (
        				SELECT 
         				 CL.CLIENT_ID,
						 CL.CLIENT_NAME,
						 CL.CLIENT_EMAIL,
						 TE.TEL_ID,
						 TE.WHATSAPP,
						 TE.TELEGRAM,
						 TE.TEL_LTYPE,
						 TE.TEL_NUMBER,
						 AD.ADDRESS_ID,
						 AD.ADDRESS_TITLE,
						 Concat(ADDRESS,' - ',CI.CI_NAME,' - ',GO.GOV_NAME,' - ',CO.CO_NAME) AS ADDRESS,
						 O.ORDERS
						FROM  client AS CL
						 LEFT JOIN telephones AS TE ON CL.CLIENT_ID = TE.CLIENT_ID AND DEFAULTTELL = 1
						 LEFT JOIN addresses AS AD ON CL.CLIENT_ID = AD.CLIENT_ID AND AD.DEFAULTADD = 1
						 LEFT JOIN city AS CI ON CI.CI_ID = AD.CITY_ID
						 LEFT JOIN gov AS GO ON CI.GOV_ID = GO.GOV_ID
						 LEFT JOIN countries CO ON CO.CO_ID = GO.GOV_ID 
						 LEFT JOIN (
						  SELECT 
						   CL.CLIENT_ID , 
						   COUNT(O.ORDER_ID) AS ORDERS
						  FROM  orders AS O
						   LEFT JOIN addresses AS AD ON AD.ADDRESS_ID = O.ADDRESS_ID
						   LEFT JOIN client AS CL ON CL.CLIENT_ID = AD.CLIENT_ID
						  GROUP BY CL.CLIENT_ID
						  ) AS O ON CL.CLIENT_ID = O.CLIENT_ID
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
					$globalSearch[] = 'CLIENT_NAME		LIKE "%'.$ShVal.'%" ';
            		break;
           		case ($ShVal && ($key === '2' || $key === 'B' || $key === 'EMAIL' || $key === 'البريد')):
            		$globalSearch[] = 'CLIENT_EMAIL 	LIKE "%'.$ShVal.'%" ';
            		break;
           		case ($ShVal && ($key === '3' || $key === 'C' || $key === 'ADDRESS' || $key === 'عنوان' ||  $key === 'العنوان')):
           			$globalSearch[] = 'ADDRESS  		LIKE "%'.$ShVal.'%" ';
            		break;
            	case ($ShVal && ($key === '4' || $key === 'D' || $key === 'TEL' || $key === 'TELEPHONE' || $key === 'PHONE' || $key === 'MOBILE' || $key === 'هاتف' ||  $key === 'موبيل' ||  $key === 'تليفون' ||    $key === 'الهاتف')):
            		$globalSearch[] = 'TEL_NUMBER    	LIKE "%'.$ShVal.'%" ';
           			break;
           		default :
           			$globalSearch[] = 'CLIENT_NAME		LIKE "%'.$str.'%" ';
					$globalSearch[] = 'TEL_NUMBER    	LIKE "%'.$str.'%" ';
					$globalSearch[] = 'CLIENT_EMAIL 	LIKE "%'.$str.'%" ';
					$globalSearch[] = 'ADDRESS  		LIKE "%'.$str.'%" ';
           			break;
            }
								
			
			$where = $where === '' ? ' WHERE ('.implode(' OR ', $globalSearch).')' : $where .' AND ('.implode(' OR ', $globalSearch).')';
			
		}
		if(isset($_POST['order'])){
			$columnsArr = ['CLIENT_ID', 'CLIENT_NAME', 'CLIENT_EMAIL', 'ADDRESS', 'TEL_NUMBER', 'ORDERS'];
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
		    	if($record['ORDERS'] <=0){
				    $colF = '<input id="'.$record['CLIENT_ID'].'" type="checkbox">';
				} else {
				    $colF = '#';
				}
			
				$colL = '<button id="#editclientfromOrder" data-cliid="'.$record['CLIENT_ID'].'" data-telid="'.$record['TEL_ID'].'" date-addid="'.$record['ADDRESS_ID'].'" data-telstr="'.$record['TEL_LTYPE'].'" class="btn btn-app editcliOrder" style="font-size: 9px; min-width: 30px; height: 30px !important;">
                             <i class="fa fa-edit "></i>
                        </button>';
		    	$Item = array(
		    		$colF,
		    		$record['CLIENT_NAME'],
		    		$record['CLIENT_EMAIL'],
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
        $sqlQuery  = ' SELECT A.CLIENT_EMAIL,C.TEL_NUMBER FROM CLIENT A LEFT JOIN ADDRESSES B ON A.CLIENT_ID = B.CLIENT_ID LEFT JOIN TELEPHONES C ON A.CLIENT_ID = C.CLIENT_ID WHERE A.CLIENT_ID IS NOT NULL AND C.TEL_NUMBER = ? OR A.CLIENT_EMAIL = ?';     
        $sqlQuery0 = ' INSERT INTO `CLIENT`(`CLIENT_NAME`, `CLIENT_EMAIL`) VALUES (?,?) ';
	    $sqlQuery1 = ' INSERT INTO `ADDRESSES`(`CLIENT_ID`,`CITY_ID`, `ADDRESS_TITLE`, `DEFAULTADD`, `ADDRESS`) VALUES (?,?,?,?,?) ';
        $sqlQuery2 = ' INSERT INTO `TELEPHONES`(`CLIENT_ID`, `DEFAULTTELL`, `WHATSAPP`, `TELEGRAM`, `TEL_LTYPE`, `TEL_NUMBER`) VALUES (?,?,?,?,?,?) ';
        
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
    public function addClientTel(){
        $sqlQuery  = ' SELECT TEL_NUMBER FROM TELEPHONES WHERE CLIENT_ID IS NOT NULL AND TEL_NUMBER = ? ';    
        $sqlQuery1 = ' UPDATE TELEPHONES SET DEFAULTTELL=0 WHERE FIND_IN_SET (CLIENT_ID,?) ';
        $sqlQuery2 = ' INSERT INTO `TELEPHONES`(`CLIENT_ID`, `DEFAULTTELL`, `WHATSAPP`, `TELEGRAM`, `TEL_LTYPE`, `TEL_NUMBER`) VALUES (?,?,?,?,?,?) ';
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
    public function updateClientTel(){
        $sqlQuery  = ' SELECT TEL_NUMBER FROM TELEPHONES WHERE CLIENT_ID IS NOT NULL AND TEL_NUMBER = ? AND TEL_ID <> ? ';    
        $sqlQuery1 = ' UPDATE TELEPHONES SET DEFAULTTELL=0 WHERE CLIENT_ID is not null AND FIND_IN_SET (CLIENT_ID,(SELECT CLIENT_ID FROM TELEPHONES  WHERE CLIENT_ID IS NOT NULL AND TEL_NUMBER = ? )) ';
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
    public function deleteClientTel(){
        $sqlwher   =' SELECT COUNT(TEL_ID) AS COUN_TEL FROM TELEPHONES WHERE FIND_IN_SET(CLIENT_ID,?) ' ;
        $sqlQuery  =' DELETE FROM TELEPHONES WHERE TEL_ID = ? AND CLIENT_ID = ? AND DEFAULTTELL <> 1 AND ('.$sqlwher.') = 0 ' ;
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
                $arr = array("w","خطاء - لم يتم حذف الرقم - مستخدم فى اوامر بيع اخرى يمكنك تعديل رقم الهاتف دون ان يؤثر على اوامر البيع السابقه ");
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
    public function updateClientName(){
    	$query = "UPDATE client SET CLIENT_NAME = ? WHERE CLIENT_ID = ? ";
        $this->city_id         = htmlspecialchars(strip_tags($this->client_id));
        $this->client_name     = htmlspecialchars(strip_tags($this->client_name));
        try{
     
        	$this->conn->beginTransaction();
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(1,$this->client_name,  PDO::PARAM_STR);
            $stmt->bindValue(2  ,$this->client_id,    PDO::PARAM_INT);
            $stmt->execute();
            if($stmt->execute() ) {
            	$this->conn->commit();
                return array("s","تم تحديث اسم العميل بنجاح");
            } else {
            	$this->conn->rollback();
            	return array("e","حدث خطاء - غير قادر على تنفيذ الاجراء");
            }
        } catch (PDOException $e) {
            $this->conn->rollback();
            return array("e",$e->getCode() .' - '.$e->getMessage());
        }
    }
    public function deleteClients(){
        $query = 'DELETE FROM `client` WHERE CLIENT_ID = ?';
        $this->client_id = htmlspecialchars(strip_tags($this->client_id));
        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(1 ,$this->client_id,   PDO::PARAM_STR);
            $stmt->execute();
            if($stmt->rowCount()>0) {
                $this->conn->commit();
                return array("s","تم حذف بيانات العميل بنجاح");
            } else {
            	$this->conn->rollback();
                return array("w","خطاء غير متوقع - لم يتم الخذف لاسباب غير معروفه");
                
            }
        } catch (PDOException $e) {
            $this->conn->rollback();
            return array("e",$e->getCode() .' - '.$e->getMessage());
        }
        
    }
}