<?php
class Order_Service_tbl {
    //---------order_service_type-------------
    private $TableOSTYPE = "order_service_type";
    public $OST_ID;
    public $OST_TYPE;
    //---------ORDER SERVICE---------------
    private $TableOS = "order_service";
    public $OS_ID;
    public $ORDER_ID;
    public $USER_ID;
    public $USER_TYPE;
    public $OS_DATE_OPEN;
    public $OS_DATE_CLOSE;
    public $OS_STATUSE;
    public $last_update;
    //---------ORDER SERVICE TEXT-----------------
    private $TableOSTEXT = "order_service_text";
    public $OSTX_ID;
    public $OSTX_MSG;
    public $OSTX_READ;
    //---------ORDER LOG-----------------
    private $TableOLOG = "ORDERS_LOG";
    public $user_action;
    public $log_text;
    public $logObj;
    public $logStr;
    //----------------------------
    //public $where="";
    private $conn;
    public function __construct($db) {
        $this->conn = $db;
    } // end function conn
    
    
    
    /*private function bool_To_int($bol) {
        if ($bol == 'true') {
            return 1;
        } else {
            return 0;
        }
    }
    private function qwhere() {
        if ($this->where == "") {
            $this->where = "WHERE";
            return " WHERE ";
        } else {
            return " AND ";
        }
    }*/
    
    private function remove_specialchars() {
        $this->OS_ID         = htmlspecialchars(strip_tags($this->OS_ID));
        $this->ORDER_ID      = htmlspecialchars(strip_tags($this->ORDER_ID));
        $this->USER_ID       = htmlspecialchars(strip_tags($this->USER_ID));
        $this->USER_TYPE     = htmlspecialchars(strip_tags($this->USER_TYPE));
        $this->OS_DATE_OPEN  = htmlspecialchars(strip_tags($this->OS_DATE_OPEN));
        $this->OS_DATE_CLOSE = htmlspecialchars(strip_tags($this->OS_DATE_CLOSE));
        $this->OS_STATUSE    = htmlspecialchars(strip_tags(settype($this->OS_STATUSE, 'boolean')));
        $this->OSTX_ID       = htmlspecialchars(strip_tags($this->OSTX_ID));
        $this->OSTX_MSG      = htmlspecialchars(strip_tags($this->OSTX_MSG));
        $this->OSTX_READ     = htmlspecialchars(strip_tags(settype($this->OSTX_READ, 'boolean')));
        $this->OST_ID        = htmlspecialchars(strip_tags($this->OST_ID));
        $this->OST_TYPE      = htmlspecialchars(strip_tags($this->OST_TYPE));
    }

    private function Queries($Qtype) {
        $Qarr=array();
        if($Qtype =="i"){
            $Qarr[] = 'INSERT INTO `'.$this->TableOS.'` (`ORDER_ID`,`USER_ID`,`OST_ID`) VALUES (?,?,?) ';
            $Qarr[] = 'INSERT INTO `'.$this->TableOSTEXT.'` (`OSTX_ID` ,`OS_ID` ,`USER_ID` ,`USER_TYPE` ,`OSTX_MSG`) VALUES (?,?,?,?,?)';
            $Qarr[] = 'INSERT INTO `'.$this->TableOSTYPE.'` (`OST_TYPE`) VALUES (?)';
            $Qarr[] = 'SELECT `OSTX_ID` FROM `'.$this->TableOSTEXT.'` WHERE `OSTX_ID` = ? ';
            $Qarr[] = 'INSERT INTO `'.$this->TableOLOG.'`(`ORDER_ID`,`USER_ACTION`,`LOG_TEXT`) VALUES (?,?,?) ';
        }else if($Qtype =="u"){
            $Qarr[] = 'UPDATE `USERS_AFFILIATE` SET `AFFILIATEED_WITH`= ?, `COMMISSION_SALES`= ?, `TEAM_COMMISSION`= ? WHERE USER_ID = ? ';
            $Qarr[] = 'UPDATE `'.$this->TableOSTEXT.'` SET `OSTX_READ`= 1 ,`OSTX_READ_DATE` = current_timestamp() WHERE `OSTX_READ`= 0 AND USER_TYPE <> ? AND FIND_IN_SET (`OSTX_ID`, ? ) ';
            $Qarr[] = 'UPDATE `order_service` SET `OS_DATE_CLOSE`= CURRENT_TIMESTAMP() ,`OS_STATUSE`=1 WHERE OS_ID = ? ';
            $Qarr[] = 'UPDATE `orders` SET `LAST_UPDATE`  = now(3) WHERE FIND_IN_SET (`ORDER_ID`,?) ';
            
        }else if($Qtype =="d"){
            $Qarr[] = 'DELETE';
        }else if($Qtype =="s"){
            
            $Qarr[] = 'SELECT A.OS_ID,A.OS_STATUSE, E.OST_ID, F.OSTX_ID, A.ORDER_ID, B.SUB_ORDER_ID, B.ORDER_TYPE_ID, B.DELIVERY_ID,
                         F.USER_ID AS OSTX_USER_ID,
                         F.USER_TYPE AS OSTX_USER_TYPE, 
						 CASE WHEN F.USER_ID = A.USER_ID THEN 1 ELSE 0 END  AS MSG_ME,
						 Concat(C.FORENAME," ",C.LASTNAME)AS OS_UNAME, Concat(G.FORENAME," ",G.LASTNAME)AS OSTX_UNAME,
                         CASE WHEN F.USER_ID = B.ORDER_BY THEN "منشئ" ELSE "ادارى" END  AS MSG_BY,
                         D.USER_GRUOP_NAME, E.OST_TYPE, F.OSTX_MSG, F.OSTX_DATE, F.OSTX_READ
                        FROM  order_service A
                         LEFT JOIN orders B ON A.ORDER_ID = B.ORDER_ID
                         LEFT JOIN users C ON A.USER_ID = C.USER_ID
                         LEFT JOIN user_gruop D ON C.USER_GRUOP_ID = D.USER_GRUOP_ID
                         LEFT JOIN order_service_type E ON A.OST_ID = E.OST_ID
                         LEFT JOIN order_service_text F ON A.OS_ID = F.OS_ID
                         LEFT JOIN users G ON F.USER_ID = G.USER_ID ';
            $Qarr[] = 'SELECT A.*,
                         Concat(B.FORENAME," ",B.LASTNAME) AS UNAME,
                         C.OST_TYPE
                        FROM  order_service A
                         LEFT JOIN users B
                         ON A.USER_ID = B.USER_ID
                         LEFT JOIN order_service_type C
                         ON A.OST_ID = C.OST_ID 
                        WHERE A.ORDER_ID = ?';
            $Qarr[] = 'SELECT TELEPHONES SET DEFAULTTELL= ?, WHATSAPP= ?, TELEGRAM = ?, TEL_LTYPE = ?, TEL_NUMBER= ? WHERE TEL_ID= ? ';
            $Qarr[] = 'SELECT * FROM orders WHERE ORDER_ID = ? ';
        }
        $this->remove_specialchars();
        return $Qarr;
    }
    public function OSinsert($insert){
        $sqlQuery  = $this->Queries("i");
        $sqlQuery1 = $this->Queries("u");
        $sqlQuery2 = $this->Queries("s");
        try {
            if (!$this->conn->inTransaction()) {
			    $this->conn->beginTransaction();
			}
            if($insert == 'os'){
                $stmtCH = $this->conn->prepare($sqlQuery2[3]);
                $stmtCH->bindValue(1, $this->ORDER_ID   , PDO::PARAM_INT);
                $stmtCH->execute();
                $record = $stmtCH->fetch(PDO::FETCH_ASSOC);
                if( $stmtCH->rowCount() <= 0 || $record['LAST_UPDATE'] != $this->last_update) {
					return array('e', 'لايمكن انشاء طلب جديد .. لقد قام احد المستخدمين بتحديث بيانات امر البع من فضلك قم بمراجعة امر البيع ثم حاول مره اخرى');
				}

 				if(($record['ORDER_STATUS'] <= 1 && $record['PRINT'] == 1)  || ($record['ORDER_STATUS'] > 1 && $record['ORDER_STATUS'] < 5) || $record['ORDER_STATUS'] > 5){
				 	return array('e','لايمكن انشاء طلب جديد .. حالة امر البيع لاتسمح.');
				}

				//=====================Save Order Service --------------
				$stmt = $this->conn->prepare($sqlQuery[0]);
				$stmt->bindValue(1, $this->ORDER_ID, PDO::PARAM_INT);
				$stmt->bindValue(2, $this->USER_ID, PDO::PARAM_INT);
				$stmt->bindValue(3, $this->OST_ID, PDO::PARAM_INT);
				if(!$stmt->execute() || $stmt->rowCount() <= 0) {
					$this->conn->rollback();
					return array('e', 'حدث خطاء - غير قادر على فتح تذكره جديده بسبب خطاء غير معروف');
				}
				
				//=======================Save msg=====================================
				$this->OS_ID = $this->conn->lastInsertId(); // orderserveice last Inserted
				$i = 0;
				do {
		 						$i++;
		 						$code = $this->USER_ID . '-' . $this->OS_ID . '-' . rand(1, 9999999);
								$stCh[$i] = $this->conn->prepare($sqlQuery[3]);
								$stCh[$i]->bindValue(1, $code, PDO::PARAM_STR);
								$stCh[$i]->execute();
							} while ($stCh[$i]->rowCount() > 0);

				$stmt1 = $this->conn->prepare($sqlQuery[1]);
				$stmt1->bindValue(1, $code			 , PDO::PARAM_STR);
				$stmt1->bindValue(2, $this->OS_ID	 , PDO::PARAM_INT);
				$stmt1->bindValue(3, $this->USER_ID	 , PDO::PARAM_INT);
				$stmt1->bindValue(4, $this->USER_TYPE, PDO::PARAM_INT);
				$stmt1->bindValue(5, $this->OSTX_MSG , PDO::PARAM_STR);
				if (!$stmt1->execute() || $stmt1->rowCount() <= 0){
					$this->conn->rollback();
					return array('e', 'حدث خطاء - الاسباب غير معروفة');
				}

				//==========================Save Order log===========================
				$this->logStr  = ' قام المستخدم بفتح تذكرة جديده لخدمة العملاء   ';
				$this->logStr .= '* تذكرة رقم : '.$this->OS_ID.' الرساله المرسله : '.$this->OSTX_MSG;
				$stlog = $this->conn->prepare($sqlQuery[4]);
				$stlog->bindValue(1, $this->ORDER_ID, PDO::PARAM_INT);
				$stlog->bindValue(2, $this->USER_ID, PDO::PARAM_INT);
				$stlog->bindValue(3, $this->logStr, PDO::PARAM_STR);
				if (!$stlog->execute() || $stlog->rowCount() <= 0) {
					$this->conn->rollback();
					return array('e', 'حدث خطاء - غير قادر على تحديث سجلات امر البيع');
				}

				//====================UPDATE orders set LAST_UPDATE==================
				$stuo = $this->conn->prepare($sqlQuery1[3]);
				$stuo->bindValue(1, $this->ORDER_ID, PDO::PARAM_STR);
				if (!$stuo->execute() || $stuo->rowCount() <= 0) {
					$this->conn->rollback();
					return array('e', 'حدث خطاء - الاسباب غير معروفة');
				}
				
				$this->conn->commit();
				return array('s', 'تم استلام طلبك - رقم : '.$this->OS_ID);

            } else if($insert == 'msg'){
				// انشاء مفتاح فريد لحفظ الرسالة
				$i = 0;
				do {
					$i++;
					$code = $this->USER_ID . $this->OS_ID . rand(1, 9999) .'-'. date('YmdHis') ;
					$stCh[$i] = $this->conn->prepare('SELECT OSTX_ID FROM order_service_text WHERE OSTX_ID = ?');
					$stCh[$i]->bindValue(1, $code, PDO::PARAM_STR);
					$stCh[$i]->execute();
				} while ($stCh[$i]->rowCount() > 0);
				
				//حفظ الرساله بقواعد البيانات
				$stmtMSG = $this->conn->prepare('INSERT INTO `'.$this->TableOSTEXT.'` (`OSTX_ID` ,`OS_ID` ,`USER_ID` ,`USER_TYPE` ,`OSTX_MSG`) VALUES (?,?,?,?,?)');
				$stmtMSG->bindValue(1, $code, PDO::PARAM_INT);
				$stmtMSG->bindValue(2, $this->OS_ID, PDO::PARAM_INT);
				$stmtMSG->bindValue(3, $this->USER_ID, PDO::PARAM_INT);
				$stmtMSG->bindValue(4, $this->USER_TYPE, PDO::PARAM_INT);
				$stmtMSG->bindValue(5, $this->OSTX_MSG, PDO::PARAM_STR);
				if (!$stmtMSG->execute() || $stmtMSG->rowCount()<=0) {
					if ($this->conn->inTransaction()) $this->conn->rollback();
					return array('e', 'حدث خطاء ولم يتم ارسال رسالتك');
				}
				//تسجيل اخر تحديث لامر البيع	
				$stmtMSG1 = $this->conn->prepare('UPDATE orders SET LAST_UPDATE = now(3) WHERE ORDER_ID = (SELECT o.ORDER_ID FROM orders o LEFT JOIN order_service os ON o.ORDER_ID = os.ORDER_ID WHERE OS_ID = ? LIMIT 1)');
				$stmtMSG1->bindValue(1, $this->OS_ID, PDO::PARAM_INT);
				if (!$stmtMSG1->execute()){
					if ($this->conn->inTransaction()) $this->conn->rollback();
					return array('e', 'حدث خطاء ولم يتم ارسال رسالتك');
				}
				
				//======================Insert Order Log =========================
				$groupName = (isset($_SESSION["USER_GRUOP_NAME"]) && $_SESSION["USER_GRUOP_NAME"]!='') ? $_SESSION["USER_GRUOP_NAME"] : ' المستخدم ';
				$this->logStr  = 'تم تحديث تذكرة خدمة العملاء من  '.$groupName.'';
	            $this->logStr .= '*محتوى الرساله : '.$this->OSTX_MSG;
				
				$stmtlog = $this->conn->prepare('INSERT INTO '.$this->TableOLOG.' (ORDER_ID,USER_ACTION,LOG_TEXT) SELECT o.ORDER_ID AS ORDER_ID, '.$_SESSION['USER_ID'].' AS USER_ACTION,"'.$this->logStr.'" AS LOG_TEXT  FROM orders o LEFT JOIN order_service os ON o.ORDER_ID = os.ORDER_ID WHERE OS_ID = ? LIMIT 1');
				$stmtlog->bindValue(1, $this->OS_ID, PDO::PARAM_INT);
				if (!$stmtlog->execute() || $stmtlog->rowCount() <= 0) {
					if ($this->conn->inTransaction()) $this->conn->rollback();
					return array('e', 'حدث خطاء ولم يتم ارسال رسالتك');
				}

				//تأكيد العمليه
				if ($this->conn->inTransaction()) $this->conn->commit();
				return array('s', 'تم ارسال رسالتك');

			} else if($insert == 'type'){
				
			}
		} catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollback();
            return array('e', $e->getCode().' - '.$e);
        }
    }
	public function OSupdate($update){
		$sqlQuery = $this->Queries("u");
		$transaction = false;
		try {
			if (!$this->conn->inTransaction()){
				$this->conn->beginTransaction();
				$transaction= true;
			}
			
			if($update == 'readmsg' ){
				$stmtRedmsg = $this->conn->prepare($sqlQuery[1]);
				$stmtRedmsg->bindValue(1, $this->USER_TYPE, PDO::PARAM_INT);
				$stmtRedmsg->bindValue(2, $this->OSTX_ID  , PDO::PARAM_STR);
				if($stmtRedmsg->execute() ){//|| $stmtRedmsg->rowCount() <= 0){
					if ($transaction) $this->conn->rollback();
					return array('e', 'حدث خطاء - غير قادر على تغير حالة الرسائل');
				}
				return $stmtRedmsg->rowCount();
				if ($transaction) $this->conn->commit();
				
				if($stmtRedmsg->rowCount()> 0){
					return array('s', ' رسائل جديده');
				}else{
					return array('s', 'لايوجد رسائل جديده');
				}
			} else if($update == 'osClose'){
				
				if(!$this->OS_ID || $this->OS_ID == ""){
					if ($this->conn->inTransaction()) $this->conn->rollback();
					return array('e', 'حدث خطاء - رقم التذكره مفقود');
				}
				
			    $stmtOsClose = $this->conn->prepare($sqlQuery[2]);
				$stmtOsClose->bindValue(1, $this->OS_ID, PDO::PARAM_INT);
				if(!$stmtOsClose->execute() || $stmtOsClose->rowCount() <= 0){
					if ($this->conn->inTransaction()) $this->conn->rollback();
					return array('e', 'حدث خطاء - غير قادر على اغلاق التذكرة');
				}
				
				if(!isset($this->OS_ID,$this->USER_ID)){
					if ($this->conn->inTransaction()) $this->conn->rollback();
					return array('e', 'حدث خطاء - هناك بيانات مفقوده');
				}
				
				$this->OSTX_MSG = 'تم اغلاق التذكرة بعد اتخاذ الاجراء اللازم' ;
				$reternfun = $this->OSinsert('msg');
				
				if($reternfun[0]!='s'){
					if ($this->conn->inTransaction()) $this->conn->rollback();
					return array('e', 'حدث خطاء - غير قادر على حفظ رسالتك');
				}
				
				//تسجيل اخر تحديث لامر البيع	
				$stuo = $this->conn->prepare('UPDATE orders SET LAST_UPDATE = now(3) WHERE ORDER_ID = (SELECT o.ORDER_ID FROM orders o LEFT JOIN order_service os ON o.ORDER_ID = os.ORDER_ID WHERE OS_ID = ? LIMIT 1)');
				$stuo->bindValue(1, $this->OS_ID, PDO::PARAM_INT);
				if (!$stuo->execute() || $stuo->rowCount() <= 0) {
					if ($this->conn->inTransaction()) $this->conn->rollback();
					return array('e', 'حدث خطاء - غير قادر على تحديث بيانات امر البيع');
				}
				
				if ($this->conn->inTransaction()) $this->conn->commit();
                return array('s', 'تم اغلاق التذكرة رقم : '.$this->OS_ID);
			}
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollback();
            return array('e', $e->getCode().' - '.$e->getMessage());
        }
		
	}
    /*public function OSview($select){
        $sqlQuery = $this->Queries("s");
       
        try {
            if($select == 'osch'){
                $sqlQueryAll  = $sqlQuery[0] . ' WHERE A.OS_ID = ? ORDER BY F.OSTX_DATE ';
                $sqlQuerylast = $sqlQuery[0] . ' WHERE A.OS_ID = ? AND F.USER_ID <> ? AND F.USER_TYPE <> ? AND F.OSTX_READ=0  ORDER BY F.OSTX_DATE ';
                
                if(isset($_GET['uid'],$_GET['ptype']) ){
                    $sqlQueryrun = $sqlQuerylast; //user type
                } else {
                    $sqlQueryrun = $sqlQueryAll; //admin type
                }
				$stmt = $this->conn->prepare($sqlQueryrun);
				$stmt->bindValue(1, $this->OS_ID, PDO::PARAM_INT);
				if(isset($_GET['uid'],$_GET['ptype'])){
				    $this->USER_ID   = htmlspecialchars(strip_tags($_GET['uid']));
				    $this->USER_TYPE = htmlspecialchars(strip_tags($_GET['ptype']));
				    $stmt->bindValue(2, $this->USER_ID  , PDO::PARAM_INT);
				    $stmt->bindValue(3, $this->USER_TYPE, PDO::PARAM_INT);
				}
				$stmt->execute();
				$result = $stmt;
				$records = array();
				$ostx_idRmsg = array();
				$orderid_cotact = null;
                while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
				    $rows = array();
				    $rows['OS_ID']=$record['OS_ID'];
				    $rows['OS_STATUSE']=$record['OS_STATUSE'];
				    $rows['OST_ID']=$record['OST_ID'];
				    $rows['OSTX_ID']=$record['OSTX_ID'];
				    $rows['ORDER_ID']=$record['ORDER_ID'];
				    //$rows['USER_ID']=$record['USER_ID'];
				    $rows['SUB_ORDER_ID']=$record['SUB_ORDER_ID'];
				    $rows['ORDER_TYPE_ID']=$record['ORDER_TYPE_ID'];
				    $rows['DELIVERY_ID']=$record['DELIVERY_ID'];
				    $rows['OSTX_USER_ID']=$record['OSTX_USER_ID'];
				    $rows['OSTX_USER_TYPE']=$record['OSTX_USER_TYPE'];
				    $rows['MSG_ME']=$record['MSG_ME'];
				    $rows['OS_UNAME']=$record['OS_UNAME'];
				    $rows['OSTX_UNAME']=$record['OSTX_UNAME'];
				    $rows['USER_GRUOP_NAME']=$record['USER_GRUOP_NAME'];
				    $rows['OST_TYPE']=$record['OST_TYPE'];
				    $rows['OSTX_MSG']=$record['OSTX_MSG'];
				    $rows['OSTX_DATE']=$record['OSTX_DATE'];
				    $rows['OSTX_READ']=$record['OSTX_READ'];
				    $records[$record['OSTX_DATE']] = $rows;
					if($record['OSTX_READ'] != 1 && $rows['OSTX_USER_TYPE'] != $this->USER_TYPE){
						$ostx_idRmsg[] = $record['OSTX_ID'];
					}
					
					$orderid_cotact = $record['ORDER_ID'];
                }
				$this->OSTX_ID = join(",",$ostx_idRmsg); 
				$Rmsg=$this->OSupdate('readmsg');
                if($Rmsg[0] !=="s"){
                	return $Rmsg;
                }
                $stmt2 = $this->conn->prepare($sqlQuery[1]);
				$stmt2->bindValue(1, $orderid_cotact, PDO::PARAM_INT);
				$stmt2->execute();
				$result = $stmt2;
				$records2 = array();
				
                while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
				    $rows = array();
				    $rows['OS_ID']=$record['OS_ID'];
				    $rows['ORDER_ID']=$record['ORDER_ID'];
				    $rows['OS_STATUSE']=$record['OS_STATUSE'];
				    $rows['OST_TYPE']=$record['OST_TYPE'];
				    $rows['OS_DATE_OPEN']=$record['OS_DATE_OPEN'];
				    $rows['OS_DATE_CLOSE']=$record['OS_DATE_CLOSE'];
				    $rows['UNAME']=$record['UNAME'];
				    $records2[$record['OS_DATE_OPEN']] = $rows;
                }
                $returnarr = array(
                    "msg" => $records,
                    "contact" => $records2
                );
                return $returnarr;
            } else if($select == ''){
               
            } else {
            	 
            }
        } catch (PDOException $e) {
		    if ($this->conn->inTransaction()) {
		        $this->conn->rollBack();
		    }
		    return array('e', $e->getCode().' - '.$e->getMessage());
		}
    }*/
    public function OSview($select){
    $sqlQuery = $this->Queries("s");
   
    try {
        if($select == 'osch'){
            $sqlQueryAll  = $sqlQuery[0] . ' WHERE A.OS_ID = ? ORDER BY F.OSTX_DATE ';
            $sqlQuerylast = $sqlQuery[0] . ' WHERE A.OS_ID = ? AND F.USER_ID <> ? AND F.USER_TYPE <> ? AND F.OSTX_READ=0  ORDER BY F.OSTX_DATE ';

            if(isset($_GET['uid'],$_GET['ptype'])){
                $sqlQueryrun = $sqlQuerylast; // user type
            } else {
                $sqlQueryrun = $sqlQueryAll; // admin type
            }

            $this->conn->beginTransaction();

            // استعلام الرسائل
            $stmt = $this->conn->prepare($sqlQueryrun);
            $stmt->bindValue(1, $this->OS_ID, PDO::PARAM_INT);
            if(isset($_GET['uid'],$_GET['ptype'])){
                $this->USER_ID   = htmlspecialchars(strip_tags($_GET['uid']));
                $this->USER_TYPE = htmlspecialchars(strip_tags($_GET['ptype']));
                $stmt->bindValue(2, $this->USER_ID  , PDO::PARAM_INT);
                $stmt->bindValue(3, $this->USER_TYPE, PDO::PARAM_INT);
            }
            $stmt->execute();
            $records = [];
            $ostx_idRmsg = [];
            $orderid_cotact = null; // قيمة افتراضية لمنع تحذيرات

            while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rows = [
                    'OS_ID' => $record['OS_ID'],
                    'OS_STATUSE' => $record['OS_STATUSE'],
                    'OST_ID' => $record['OST_ID'],
                    'OSTX_ID' => $record['OSTX_ID'],
                    'ORDER_ID' => $record['ORDER_ID'],
                    'SUB_ORDER_ID' => $record['SUB_ORDER_ID'],
                    'ORDER_TYPE_ID' => $record['ORDER_TYPE_ID'],
                    'DELIVERY_ID' => $record['DELIVERY_ID'],
                    'OSTX_USER_ID' => $record['OSTX_USER_ID'],
                    'OSTX_USER_TYPE' => $record['OSTX_USER_TYPE'],
                    'MSG_ME' => $record['MSG_ME'],
                    'OS_UNAME' => $record['OS_UNAME'],
                    'OSTX_UNAME' => $record['OSTX_UNAME'],
                    'USER_GRUOP_NAME' => $record['USER_GRUOP_NAME'],
                    'OST_TYPE' => $record['OST_TYPE'],
                    'OSTX_MSG' => $record['OSTX_MSG'],
                    'OSTX_DATE' => $record['OSTX_DATE'],
                    'OSTX_READ' => $record['OSTX_READ'],
                ];
                $records[$record['OSTX_DATE']] = $rows;

                if($record['OSTX_READ'] != 1 && $rows['OSTX_USER_TYPE'] != $this->USER_TYPE){
                    $ostx_idRmsg[] = $record['OSTX_ID'];
                }

                $orderid_cotact = $record['ORDER_ID'];
            }

            // تحديث الرسائل المقروءة
            if(!empty($ostx_idRmsg)){
                $this->OSTX_ID = join(",", $ostx_idRmsg);
                $this->OSupdate('readmsg');
            }

            // استعلام البيانات المرتبطة بالطلب
            $records2 = [];
            if($orderid_cotact !== null){
                $stmt2 = $this->conn->prepare($sqlQuery[1]);
                $stmt2->bindValue(1, $orderid_cotact, PDO::PARAM_INT);
                $stmt2->execute();
                while ($record = $stmt2->fetch(PDO::FETCH_ASSOC)) {
                    $rows = [
                        'OS_ID' => $record['OS_ID'],
                        'ORDER_ID' => $record['ORDER_ID'],
                        'OS_STATUSE' => $record['OS_STATUSE'],
                        'OST_TYPE' => $record['OST_TYPE'],
                        'OS_DATE_OPEN' => $record['OS_DATE_OPEN'],
                        'OS_DATE_CLOSE' => $record['OS_DATE_CLOSE'],
                        'UNAME' => $record['UNAME'],
                    ];
                    $records2[$record['OS_DATE_OPEN']] = $rows;
                }
            }

            $this->conn->commit();

            return [
                "msg" => $records,
                "contact" => $records2
            ];

        } else if($select == ''){
            return [];
        } else {
            return [];
        }

    } catch (PDOException $e) {
        if($this->conn->inTransaction()){
            $this->conn->rollBack();
        }
        return ['e', $e->getCode().' - '.$e->getMessage()];
    }
}

} // end class