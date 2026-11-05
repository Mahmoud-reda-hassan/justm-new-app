<?php
class Users_tbl {
    //---------users---------------
    private $TableUser = "USERS";
    public $USER_ID;
    public $USER_GRUOP_ID;
    public $BRANCH_ID;
    public $FORENAME;
    public $LASTNAME;
    public $GENDER;
    public $SUSPENDED;
    public $USER_LOGIN;
    public $USER_PASSWORD;
    

    public $userlogin;
    public $user_password;

    public $RE_PASSCODE;
    public $RE_PASS_EXPIRE;
    public $TEL_ID;
    public $ADDRESS_ID;
    public $ADMIN_TYPE;
    public $MERCHANT_TYPE;
    public $AFFILIATE_TYPE;
    public $INTERMEDIARY_TYPE;
    public $DELIVERY_TYPE;
    public $USER_IMG_LINK;
    //---------tel-----------------
    public $DEFAULTTELL;
    public $WHATSAPP;
    public $TELEGRAM;
    public $TEL_TYPE;
    public $TEL_NUMBER;
    //---------address-------------
    public $CITY_ID;
    public $DEFAULTADD;
    public $ADDRESS_TITLE;
    public $ADDRESS;
    //---------affiliate-----------
    public $AFFILIATEED_WITH;
    public $LIVEL;
    public $COMMISSION_SALES;
    public $TEAM_COMMISSION;
    public $BIRTHDAY;
    //---------delivery-----------
    public $ZONE_ID;
    public $PERCENT;
    //----------------------------
    public $where="";
    private $conn;
    public function __construct($db) {
        $this->conn = $db;
    } // end function conn
    private function bool_To_int($bol) {
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
    }
    private function check_tel_email($t) {
        $q = '';
        if ($t == 1) {
            $q = ' AND USER_ID <> ? ';
        }
        $q1 = 'SELECT USER_ID FROM USERS WHERE USER_LOGIN = ? ' . $q;
        $q2 = 'SELECT TEL_ID  FROM TELEPHONES WHERE TEL_NUMBER = ? AND USER_ID IS NOT NULL ' . $q;

        return array($q1, $q2);
    }
    private function datafilter() {
        if ($this->USER_GRUOP_ID != '') {
            $this->USER_GRUOP_ID = htmlspecialchars(strip_tags($this->USER_GRUOP_ID));
        } else {
            $this->USER_GRUOP_ID = null;
        }
        $this->BRANCH_ID = htmlspecialchars(strip_tags($this->BRANCH_ID));
        $this->FORENAME = htmlspecialchars(strip_tags($this->FORENAME));
        $this->LASTNAME = htmlspecialchars(strip_tags($this->LASTNAME));
        $this->GENDER = htmlspecialchars(strip_tags($this->GENDER));
        $this->SUSPENDED = htmlspecialchars(strip_tags($this->SUSPENDED));
        $this->USER_LOGIN = $this->USER_LOGIN;
        $this->USER_PASSWORD = $this->USER_PASSWORD;
        $this->RE_PASSCODE = htmlspecialchars(strip_tags($this->RE_PASSCODE));
        $this->RE_PASS_EXPIRE = htmlspecialchars(strip_tags($this->RE_PASS_EXPIRE));
        $this->ADMIN_TYPE = htmlspecialchars(strip_tags($this->ADMIN_TYPE));
        $this->MERCHANT_TYPE = htmlspecialchars(strip_tags($this->MERCHANT_TYPE));
        $this->AFFILIATE_TYPE = htmlspecialchars(strip_tags($this->AFFILIATE_TYPE));
        $this->INTERMEDIARY_TYPE = htmlspecialchars(strip_tags($this->INTERMEDIARY_TYPE));
        $this->DELIVERY_TYPE = htmlspecialchars(strip_tags($this->DELIVERY_TYPE));
        $this->USER_IMG_LINK = $this->USER_IMG_LINK;
        $this->BIRTHDAY = htmlspecialchars(strip_tags($this->BIRTHDAY));
        //---------tel-----------------
        $this->DEFAULTTELL = htmlspecialchars(strip_tags($this->DEFAULTTELL));
        $this->WHATSAPP = htmlspecialchars(strip_tags(settype($this->WHATSAPP, 'boolean')));
        $this->TELEGRAM = htmlspecialchars(strip_tags(settype($this->TELEGRAM, 'boolean')));
        $this->TEL_TYPE = htmlspecialchars(strip_tags($this->TEL_TYPE));
        $this->TEL_NUMBER = htmlspecialchars(strip_tags($this->TEL_NUMBER));
        //---------address-------------
        $this->CITY_ID = htmlspecialchars(strip_tags($this->CITY_ID));
        $this->DEFAULTADD = htmlspecialchars(strip_tags($this->DEFAULTADD));
        $this->ADDRESS_TITLE = htmlspecialchars(strip_tags($this->ADDRESS_TITLE));
        $this->ADDRESS = htmlspecialchars(strip_tags($this->ADDRESS));

        $this->AFFILIATEED_WITH = htmlspecialchars(strip_tags($this->AFFILIATEED_WITH));
        $this->LIVEL = htmlspecialchars(strip_tags($this->LIVEL));
        $this->COMMISSION_SALES = htmlspecialchars(strip_tags($this->COMMISSION_SALES));
        $this->TEAM_COMMISSION = htmlspecialchars(strip_tags($this->TEAM_COMMISSION));

        $this->ZONE_ID = htmlspecialchars(strip_tags($this->ZONE_ID));
        $this->PERCENT = htmlspecialchars(strip_tags($this->PERCENT));
    }
    private function insert() {
        $sqlUser 		= 'INSERT INTO `USERS`(`USER_GRUOP_ID`, `BRANCH_ID`, `FORENAME`, `LASTNAME`, `GENDER`, `SUSPENDED`, `USER_LOGIN`, `USER_PASSWORD`, `ADMIN_TYPE`, `MERCHANT_TYPE`, `AFFILIATE_TYPE`, `INTERMEDIARY_TYPE`, `DELIVERY_TYPE`, `USER_IMG_LINK`,`BIRTHDAY`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,"1984-09-30")';
        $sqlAffiliate 	= 'INSERT INTO `USERS_AFFILIATE`(`USER_ID`, `AFFILIATEED_WITH`, `COMMISSION_SALES`, `TEAM_COMMISSION`) VALUES (?,?,?,?)';
        $sqlDelivery 	= 'INSERT INTO `USERS_DELIVERY`(`USER_ID`, `ZONE_ID`, `PERCENT`) VALUES (?,?,?)';
        $sqlTelephone 	= 'INSERT INTO `TELEPHONES`(`USER_ID`, `TYPE`,`DEFAULTTELL`, `WHATSAPP`, `TELEGRAM`, `TEL_LTYPE`, `TEL_NUMBER`) VALUES (?,1,1,?,?,?,?)';
        $sqlAddress 	= 'INSERT INTO `ADDRESSES`(`USER_ID`, `DEFAULTADD`, `CITY_ID`, `ADDRESS_TITLE`, `ADDRESS`) VALUES (?,1,?,?,?)';
        $sqlCmail 		= 'SELECT USER_ID FROM USERS WHERE USER_LOGIN = ? ';
        $sqlCtel 		= 'SELECT TEL_ID  FROM TELEPHONES WHERE TEL_NUMBER = ? AND USER_ID IS NOT NULL ';
        $this->datafilter();
        return array($sqlUser, $sqlAffiliate, $sqlDelivery, $sqlTelephone, $sqlAddress, $sqlCmail, $sqlCtel);
    }
    private function update() {
        $sqlUser = 'UPDATE `USERS` SET `USER_GRUOP_ID`=?,
		`BRANCH_ID`=?,
		`FORENAME`=?,
		`LASTNAME`=?,
		`GENDER`=?,
		`SUSPENDED`=?,
		`ADMIN_TYPE`=?,
		`MERCHANT_TYPE`=?,
		`AFFILIATE_TYPE`=?,
		`INTERMEDIARY_TYPE`=?,
		`DELIVERY_TYPE`=?,
		`USER_IMG_LINK`=?
		WHERE `USER_ID`= ? ';
        $sqlAffiliate = 'UPDATE `USERS_AFFILIATE` SET `AFFILIATEED_WITH`= ?, `COMMISSION_SALES`= ?, `TEAM_COMMISSION`= ? WHERE USER_ID = ? ';
        $sqlDelivery = 'UPDATE `USERS_DELIVERY` SET `ZONE_ID`=?, `PERCENT`=? WHERE `USER_ID`= ? ';
        $sqlTelephone = 'UPDATE TELEPHONES SET DEFAULTTELL= ?, WHATSAPP= ?, TELEGRAM = ?, TEL_LTYPE = ?, TEL_NUMBER= ? WHERE TEL_ID= ? ';
        $sqlAddress = 'UPDATE `ADDRESSES` SET `DEFAULTADD`= 1, `CITY_ID`= ?, `ADDRESS_TITLE`= ?, ADDRESS= ? WHERE `ADDRESS_ID`= ? ';
        $Check = $this->check_tel_email(1);
        $sqlCmail = $Check[0];
        $sqlCtel = $Check[1];
        $this->datafilter();
        return array($sqlUser, $sqlAffiliate, $sqlDelivery, $sqlTelephone, $sqlAddress, $sqlCmail, $sqlCtel);
    }
    private function dele() {
        $sqlUser = 'DELETE FROM `USERS` WHERE USER_ID <> 1 AND  USER_ID = ? ';
        $this->datafilter();
        return array($sqlUser, $sqlAffiliate);
    }
    private function select() {
        $sqlSelect = "SELECT A.USER_ID,
		A.FORENAME AS FNAME,
		A.LASTNAME AS LNAME,
		K.FULL_NAME,
		A.GENDER,
		A.SUSPENDED,
		A.USER_LOGIN,
		A.USER_PASSWORD,
		A.RE_PASSCODE,
		A.RE_PASS_EXPIRE,
		TIMESTAMPDIFF(MINUTE,
		A.RE_PASS_EXPIRE,
		UTC_TIMESTAMP()) as  EXPIRE_CODE,
		A.ADMIN_TYPE,
		A.MERCHANT_TYPE,
		A.AFFILIATE_TYPE,
		A.INTERMEDIARY_TYPE,
		A.DELIVERY_TYPE,
		A.USER_IMG_LINK,
		A.USER_GRUOP_ID,
		B.USER_GRUOP_NAME,
		B.USER_SCRIPT,
		D.COM_ID AS COMPANY_ID,
		D.COM_NAME AS COMPANY_NAME,
		D.LOGOBRAND AS COMPANY_LOGO,
		C.BR_ID AS BRANCH_ID,
		s.STO_ID AS STORE_ID,
		C.BR_NAME AS BRANCH_NAME,
		H.CO_ID AS COUNTRY_ID,
		H.CO_NAME AS COUNTRY_NAME,
		RA.ID AS REGIONAL_ID,
		RA.REGIONAL_NAME,
		G.GOV_ID,
		G.GOV_NAME,
		Z.ZONE_ID,
		Z.ZONE_NAME,
		Z.SHIPPING_RATE,
		F.CI_ID AS CITY_ID,
		F.CI_NAME AS CITY_NAME,
		E.ADDRESS_ID,
		E.ADDRESS_TITLE,
		E.ADDRESS,
		ZA.AFFILIATEED_WITH,
		ZA.LIVEL,
		ZA.COMMISSION_SALES,
		ZA.TEAM_COMMISSION,
		ZB.ZONE_ID AS SHIPPING_ZONE_ID,
		ZB.PERCENT,
		J.TEL_ID,
		J.WHATSAPP AS TEL_WHATSAPP,
		J.TELEGRAM AS TEL_TELEGRAM,
		J.TEL_NUMBER
		FROM  USERS AS A
		LEFT JOIN USER_GRUOP AS B ON A.USER_GRUOP_ID = B.USER_GRUOP_ID
		LEFT JOIN USERS_AFFILIATE ZA ON ZA.USER_ID = A.USER_ID
		LEFT JOIN USERS_DELIVERY ZB ON ZB.USER_ID = A.USER_ID
		LEFT JOIN BRANCHES AS C ON A.BRANCH_ID=C.BR_ID
		LEFT JOIN stores   AS S ON S.BRANCH_ID = C.BR_ID AND s.STTY_ID = 2
		LEFT JOIN COMPANIES AS D ON C.COMPANIE_ID = D.COM_ID
		LEFT JOIN ADDRESSES AS E ON A.USER_ID = E.USER_ID AND E.DEFAULTADD = 1
		LEFT JOIN CITY AS F ON E.CITY_ID = F.CI_ID
		LEFT JOIN ZONES AS Z ON F.ZONE_ID = Z.ZONE_ID
		LEFT JOIN GOV AS G ON F.GOV_ID = G.GOV_ID
		LEFT JOIN REGIONAL_AREAS AS RA ON G.REGIONAL_AREAS_ID = RA.ID
		LEFT JOIN COUNTRIES AS H ON G.COUNTRY_ID = H.CO_ID
		LEFT JOIN TELEPHONES AS J ON A.USER_ID = J.USER_ID AND J.DEFAULTTELL = 1
		LEFT JOIN (SELECT USER_ID,CONCAT(FORENAME,' ',LASTNAME) AS FULL_NAME FROM USERS) K ON A.USER_ID=K.USER_ID";
        $sqlGroup = ' GROUP BY A.USER_ID,A.FORENAME,A.LASTNAME, K.FULL_NAME, A.GENDER,A.SUSPENDED,A.USER_LOGIN,A.USER_PASSWORD,A.RE_PASSCODE,A.RE_PASS_EXPIRE,Timestampdiff(MINUTE,A.RE_PASS_EXPIRE,Utc_timestamp()),A.ADMIN_TYPE,A.MERCHANT_TYPE,A.AFFILIATE_TYPE,A.INTERMEDIARY_TYPE,A.DELIVERY_TYPE,A.USER_IMG_LINK,A.USER_GRUOP_ID,B.USER_GRUOP_NAME,B.USER_SCRIPT,D.COM_ID,D.COM_NAME,D.LOGOBRAND,C.BR_ID,C.BR_NAME,H.CO_ID,H.CO_NAME,RA.ID,RA.REGIONAL_NAME,G.GOV_ID,G.GOV_NAME,Z.ZONE_ID,Z.ZONE_NAME,Z.SHIPPING_RATE,F.CI_ID,F.CI_NAME,E.ADDRESS_ID,E.ADDRESS_TITLE,E.ADDRESS,J.TEL_ID,J.WHATSAPP,J.TELEGRAM,J.TEL_NUMBER ';
        return array($sqlSelect, $sqlGroup);
    }
    public function Userlogin() {
        $sql = $this->select();
        $sqlQuery = $sql[0] . " WHERE A.USER_LOGIN = :login AND A.USER_PASSWORD = :pass  AND  (A.ADMIN_TYPE = 1 OR A.MERCHANT_TYPE = 1 OR A.AFFILIATE_TYPE = 1 OR A.INTERMEDIARY_TYPE = 1 OR A.DELIVERY_TYPE = 1)";
        $sqlQuery .= $sql[1];
        $stmt = $this->conn->prepare($sqlQuery);
        $this->userlogin = htmlspecialchars(strip_tags($_POST['email']));
        $this->user_password = htmlspecialchars($_POST['pass']);
        $stmt->bindValue(':login', $this->userlogin, PDO::PARAM_STR);
        $stmt->bindValue(':pass', md5($this->user_password), PDO::PARAM_STR);
        $stmt->execute();
        return $stmt;
    } // end function Userlogin
    public function UserList() {
        $sql = $this->select();
        $query = "";
		$uid = $_SESSION['USER_ID'];
        if (!empty($_GET["search"]) && $_GET["search"]!='undefined') {
            $query .= $this->qwhere();
            $query .= ' (J.TEL_NUMBER LIKE "%'.$_GET["search"].'%" ';
            $query .= ' OR A.USER_LOGIN = "'.$_GET["search"].'" ';
            $query .= ' OR K.FULL_NAME LIKE "%'.$_GET["search"].'%" ';
            $query .= ' OR E.ADDRESS LIKE "%'.$_GET["search"].'%"';
            $query .= ' OR F.CI_NAME LIKE "%'.$_GET["search"].'%"';
            $query .= ' OR G.GOV_NAME LIKE "%'.$_GET["search"].'%"';
            $query .= ' OR H.CO_NAME LIKE "%'.$_GET["search"].'%"';
            $query .= ' OR C.BR_NAME LIKE "%'.$_GET["search"].'%" ';

            $query .= ' OR Z.ZONE_NAME LIKE "%'.$_GET["search"].'%") ';
        }
        if (!empty($_GET['utype'])) {
			
            if ($_GET['utype'] == 1) {
                $query .= $this->qwhere();
                $query .= " A.ADMIN_TYPE = 1 ";
            } else if ($_GET['utype'] == 2) {
                $query .= $this->qwhere();
                $query .= " A.AFFILIATE_TYPE = 1 ";
            } else if ($_GET['utype'] == 3) {
                $query .= $this->qwhere();
                $query .= " A.DELIVERY_TYPE = 1 ";
            } else if ($_GET['utype'] == 4) {
                $query .= $this->qwhere();
                $query .= " A.INTERMEDIARY_TYPE = 1 ";
            } else if ($_GET['utype'] == 5) {
                $query .= $this->qwhere();
                $query .= " A.MERCHANT_TYPE = 1 ";
            } else if ($_GET['utype'] == 6) {
                $query .= $this->qwhere();
                $query .= " A.ADMIN_TYPE = 0 AND A.AFFILIATE_TYPE = 0  AND A.DELIVERY_TYPE = 0 AND A.INTERMEDIARY_TYPE = 0 AND A.MERCHANT_TYPE = 0 ";
            } else if ($_GET['utype'] == -1) {
                $query .= $this->qwhere();
                $query .= " A.SUSPENDED = 0 ";
            }
        }
        if (!empty($this->USER_ID)) {
            $query .= $this->qwhere();
            $query .= " A.USER_ID = ? ";
        }
        $limit = "ORDER BY A.USER_ID DESC ";
        if (!empty($_GET['start']) && !empty($_GET['length'])) {
            $limit .= " LIMIT " . $_GET['start'] . ", ".$_GET['length']." ";
        }
        $stmtTotal = $this->conn->prepare("SELECT USER_ID FROM " . $this->TableUser); //. " WHERE ADMIN_TYPE = 1 ");
        $stmtTotal->execute();
        $allRecords = $stmtTotal->rowCount();
        $stmt = $this->conn->prepare($sql[0] . $query . $sql[1] . $limit);
        if (!empty($this->USER_ID)) {
            $stmt->bindValue(1, $this->USER_ID, PDO::PARAM_INT);
        }
        $stmt->execute();
        $result = $stmt;
        $displayRecords = $result->rowCount();
        $records = array();
        $recudata = array(
            "admin"       =>array(),
            "affilate"    =>array(),
            "intermdidry" =>array(),
            "merchant"    =>array(),
            "delivery"    =>array()
        );
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            if($record['USER_ID'] == 1 && $uid>1){
            	continue;
            }
            $rows = array();
            $rows['ID']                = $record['USER_ID'];
            $rows['NAME']              = $record['FNAME'] . " " . $record['LNAME'];
            $rows['FORENAME']          = $record['FNAME'];
            $rows['LASTNAME']          = $record['LNAME'];
            $rows['STATUSE']           = $record['SUSPENDED'];
            $rows['GENDER']            = $record['GENDER'];
            $rows['USER_LOGIN']        = $record['USER_LOGIN'];
            $rows['USER_PASSWORD']     = '';
			$rows['USER_GRUOP_ID']     = $record['USER_GRUOP_ID'];
			$rows['COMPANY_ID']        = $record['COMPANY_ID'];
			$rows['BRANCH_ID']         = $record['BRANCH_ID'];
            
			
            $rows['ADDRESS_ID']        = $record['ADDRESS_ID'];
			$rows['COUNTRY_ID']        = $record['COUNTRY_ID'];
			$rows['GOV_ID']            = $record['GOV_ID'];
            $rows['CITY_ID']           = $record['CITY_ID'];
            $rows['ADDRESS_TITLE']     = $record['ADDRESS_TITLE'];
			$rows['ZONE_ID']           = $record['ZONE_ID'];
			if ($record['ADDRESS']) {
                $rows['ADDRISS']       = $record['ADDRESS'] . ' - ' . $record['CITY_NAME'] . ' - ' . $record['GOV_NAME'] . ' - ' . $record['COUNTRY_NAME'];
            } else {
                $rows['ADDRISS']       = "غير متوفر";
            }
			$rows['ADDRESS_TEXT']      = $record['ADDRESS'];

            $rows['TEL_ID'] = $record['TEL_ID'];
			if ($record['TEL_NUMBER']) {
                $rows['PHONE']         = $record['TEL_NUMBER'];
                $rows['WHATSAPP']      = $record['TEL_WHATSAPP'];
                $rows['TELEGRAM']      = $record['TEL_TELEGRAM'];
            } else {
                $rows['PHONE']         = "غير متوفر";
            }
            $rows['PERCENT']           = $record['PERCENT'];
            $rows['SHIPPING_ZONE_ID']  = $record['SHIPPING_ZONE_ID'];
			$rows['AFFILIATEED_WITH']  = $record['AFFILIATEED_WITH'];
			$rows['COMMISSION_SALES']  = $record['COMMISSION_SALES'];
			$rows['TEAM_COMMISSION']   = $record['TEAM_COMMISSION'];
            $rows['ADMIN_TYPE']        = $record['ADMIN_TYPE'];
            $rows['AFFILIATE_TYPE']    = $record['AFFILIATE_TYPE'];
            $rows['INTERMEDIARY_TYPE'] = $record['INTERMEDIARY_TYPE'];
            $rows['MERCHANT_TYPE']     = $record['MERCHANT_TYPE'];
            $rows['DELIVERY_TYPE']     = $record['DELIVERY_TYPE'];
            $rows['ZONE']              = $record['ZONE_NAME'];
            $rows['JOP']               = $record['USER_GRUOP_NAME'];
            $rows['BRANCH']            = $record['BRANCH_NAME'];
            $rows['UIMG']              = $record['USER_IMG_LINK'];
            
            $records[] = $rows;
            
            $udata = array();
            $udata['ID']               = $record['USER_ID'];
            $udata['NAME']             = $record['FNAME'] . " " . $record['LNAME'];
            $udata['STATUSE']          = $record['SUSPENDED'];
            if($record['ADMIN_TYPE']){
                $recudata["admin"][$record['USER_ID']] = $udata;
            }
            if($record['AFFILIATE_TYPE']){
                $udata['AFFILIATEED_WITH'] = $record['AFFILIATEED_WITH'];
			    $udata['COMMISSION_SALES'] = $record['COMMISSION_SALES'];
			    $udata['TEAM_COMMISSION']  = $record['TEAM_COMMISSION'];
                $recudata["affilate"][$record['USER_ID']] = $udata;
            }
            if($record['INTERMEDIARY_TYPE']){
                $recudata["intermdidry"][$record['USER_ID']] = $udata;
            }
            if($record['MERCHANT_TYPE']){
                $recudata["merchant"][$record['USER_ID']] = $udata;
            }
            if($record['DELIVERY_TYPE']){
                $udata['PERCENT']          = $record['PERCENT'];
                $udata['SHIPPING_ZONE_ID'] = $record['SHIPPING_ZONE_ID'];
                $udata['COMMISSION_SALES'] = $record['COMMISSION_SALES'];
                $recudata["delivery"][$record['USER_ID']] = $udata;
            }
        }
        if(isset($_GET["draw"])){
            $cv=intval($_GET["draw"]);
        }else{
            $cv="";
        }
        $output = array(
            "draw" => $cv,
            "iTotalRecords" => $allRecords,
            "iTotalDisplayRecords" => $displayRecords,
            "data" => $records,
            "udata"=> $recudata
        );
        return $output;
    }
   /* public function insertUser() {
        $sql = $this->insert();
        $sqluser = $sql[0];
        $sqlaffiliate = $sql[1];
        $sqldelivery = $sql[2];
        $sqlTelephone = $sql[3];
        $sqlAddress = $sql[4];
        $sqlmail = $sql[5];
        $sqltel = $sql[6];
        try {
			
            //التأكد من ادخل البيانات المطلوبة
            if (!empty($this->BRANCH_ID) &&
                !empty($this->FORENAME) &&
                !empty($this->LASTNAME) &&
                !empty($this->GENDER) &&
                !empty($this->SUSPENDED) &&
                !empty($this->USER_LOGIN) &&
                !empty($this->USER_PASSWORD) &&
                !empty($this->TEL_NUMBER) &&
                isset($this->CITY_ID) && $this->CITY_ID > 0 &&
                !empty($this->ADDRESS) &&
                (
                    (!empty($this->ADMIN_TYPE) && $this->ADMIN_TYPE == 'true' && $this->USER_GRUOP_ID != '') ||
                    (!empty($this->ADMIN_TYPE) && $this->ADMIN_TYPE != 'true')
                ) &&
                (
                    (!empty($this->DELIVERY_TYPE) && $this->DELIVERY_TYPE == 'true' && !empty($this->ZONE_ID) && !empty($this->PERCENT)) ||
                    (!empty($this->DELIVERY_TYPE) && $this->DELIVERY_TYPE != 'true')
                ) &&
                (
                    (!empty($this->AFFILIATE_TYPE) && $this->AFFILIATE_TYPE == 'true' && !empty($this->COMMISSION_SALES) && !empty($this->TEAM_COMMISSION)) ||
                    (!empty($this->AFFILIATE_TYPE) && $this->AFFILIATE_TYPE != 'true')
                )) {

                //التأكد من اختيار صلاحية واحده على الاقل
                if ($this->ADMIN_TYPE == 'true' ||
                    $this->MERCHANT_TYPE == 'true' ||
                    $this->AFFILIATE_TYPE == 'true' ||
                    $this->INTERMEDIARY_TYPE == 'true' ||
                    $this->DELIVERY_TYPE == 'true') {
                    //فتح الاتصال بقواعد البيانات
                    $this->conn->beginTransaction();
                    $stmail = $this->conn->prepare($sqlmail);
                    $stmail->bindValue(1, $this->USER_LOGIN, PDO::PARAM_STR);
                    $stmail->execute();
                    $CH_mail = $stmail->rowCount();
                    $sttel = $this->conn->prepare($sqltel);
                    $sttel->bindValue(1, $this->TEL_NUMBER, PDO::PARAM_STR);
                    $sttel->execute();
                    $CH_tel = $sttel->rowCount();
                    if (!$CH_mail && !$CH_tel) {
                        //اضافة بيانات المستخدم فى جدول المستخدمين
                        $stmt = $this->conn->prepare($sqluser);
                        $stmt->bindValue(1 , $this->USER_GRUOP_ID, PDO::PARAM_STR);
                        $stmt->bindValue(2 , $this->BRANCH_ID, PDO::PARAM_INT);
                        $stmt->bindValue(3 , $this->FORENAME, PDO::PARAM_STR);
                        $stmt->bindValue(4 , $this->LASTNAME, PDO::PARAM_STR);
                        $stmt->bindValue(5 , $this->GENDER, PDO::PARAM_STR);
                        $stmt->bindValue(6 , $this->bool_To_int($this->SUSPENDED), PDO::PARAM_INT);
                        $stmt->bindValue(7 , $this->USER_LOGIN, PDO::PARAM_STR);
                        $stmt->bindValue(8 , md5($this->USER_PASSWORD), PDO::PARAM_STR);
                        $stmt->bindValue(9 , $this->bool_To_int($this->ADMIN_TYPE), PDO::PARAM_INT);
                        $stmt->bindValue(10, $this->bool_To_int($this->MERCHANT_TYPE), PDO::PARAM_INT);
                        $stmt->bindValue(11, $this->bool_To_int($this->AFFILIATE_TYPE), PDO::PARAM_INT);
                        $stmt->bindValue(12, $this->bool_To_int($this->INTERMEDIARY_TYPE), PDO::PARAM_INT);
                        $stmt->bindValue(13, $this->bool_To_int($this->DELIVERY_TYPE), PDO::PARAM_INT);
                        $stmt->bindValue(14, $this->USER_IMG_LINK, PDO::PARAM_STR);
                        
                        if (!$stmt->execute() || $stmt->rowCount()<=0) {
                        	echo json_encode(array('e', 'حدث خطاء غير معروف', 'E1'));
                            $this->conn->rollback();
                        }
                        
                        //اضافة رقم الهاتف فى جدول الهواتف
                        $userid = $this->conn->lastInsertId();
                        $stmtTelephone = $this->conn->prepare($sqlTelephone);
                        $stmtTelephone->bindValue(1, $userid, PDO::PARAM_INT);
                        $stmtTelephone->bindValue(2, $this->WHATSAPP, PDO::PARAM_INT);
                        $stmtTelephone->bindValue(3, $this->TELEGRAM, PDO::PARAM_INT);
                        $stmtTelephone->bindValue(4, $this->TEL_TYPE, PDO::PARAM_STR);
                        $stmtTelephone->bindValue(5, $this->TEL_NUMBER, PDO::PARAM_STR);
                        if (!$stmtTelephone->execute() || $stmtTelephone->rowCount()<= 0) {
                        	echo json_encode(array('e', 'حدث خطاء غير معروف', 'E2'));
                        	$this->conn->rollback();
                        }
                        
                        //اضافة العنوان فى جدول العناوين
                        $stmtAddress = $this->conn->prepare($sqlAddress);
                        $stmtAddress->bindValue(1, $userid, PDO::PARAM_INT);
                        $stmtAddress->bindValue(2, $this->CITY_ID, PDO::PARAM_INT);
                        $stmtAddress->bindValue(3, $this->ADDRESS_TITLE, PDO::PARAM_STR);
                        $stmtAddress->bindValue(4, $this->ADDRESS, PDO::PARAM_INT);
                        if (!$stmtAddress->execute() || $stmtAddress->rowCount()<=0) {
                        	echo json_encode(array('e', 'حدث خطاء غير معروف', 'E3'));
                            $this->conn->rollback();
                        }
                        
                        $err = 0;
                        //اضافة صلاحية المنسق
                        if (!empty($this->AFFILIATE_TYPE) && $this->AFFILIATE_TYPE == 'true' && !empty($this->COMMISSION_SALES) && !empty($this->TEAM_COMMISSION)) {
                        	$stmtAffiliate = $this->conn->prepare($sqlaffiliate);
                            $stmtAffiliate->bindValue(1, $userid, PDO::PARAM_INT);
                            if(!empty($this->AFFILIATEED_WITH)){
                                $stmtAffiliate->bindValue(2, $this->AFFILIATEED_WITH, PDO::PARAM_INT);
                            }else{
                            	$stmtAffiliate->bindValue(2, null);
                            }                                   
                            $stmtAffiliate->bindValue(3, $this->COMMISSION_SALES, PDO::PARAM_STR);
                            $stmtAffiliate->bindValue(4, $this->TEAM_COMMISSION, PDO::PARAM_STR);
                            if (!$stmtAffiliate->execute()) {
                                $err++;
                            }
                        }
                                    
                        //اضافة صلاحية موظف الشحن
                        if (!empty($this->DELIVERY_TYPE) && $this->DELIVERY_TYPE == 'true' && !empty($this->ZONE_ID) && !empty($this->PERCENT)) {
                            $stmtDelivery = $this->conn->prepare($sqldelivery);
                            $stmtDelivery->bindValue(1, $userid, PDO::PARAM_INT);
                            $stmtDelivery->bindValue(2, $this->ZONE_ID, PDO::PARAM_INT);
                            $stmtDelivery->bindValue(3, $this->PERCENT, PDO::PARAM_STR);
                            if (!$stmtDelivery->execute()) {
                                $err++;
                            }
                        }
                        
                        //التأكد من حفظ جميع البيانات بنجاح
                        if ($err <= 0) {
                            echo json_encode(array('s', 'تم حفظ المستخدم بنجاح'));
                            $this->conn->commit(); //تأكيد الحفظ بقواعد البيانات بنجاح
                        } else {
                            echo json_encode(array('e', 'حدث خطاء غير معروف'));
                            $this->conn->rollback(); //الغاء كل عمليات الحفظ
                        }
                            
                        
                            
                        
                    } else {
                        $msge='';
                        $msgt='';
                        if($CH_mail >0){
                            $msge = '<li>'.'البريد الالكترونى '.$this->USER_LOGIN.' مسجل بالفعل بقواعد البيانات'.'</li>';
                        }
                        if($CH_tel  >0){
                            $msgt = '<li>'.'رقم الهاتف '.$this->TEL_NUMBER.' مسجل بالفعل بقواعد البيانات'.'</li>';
                        }
                        echo json_encode(array('e', '<ul>'.$msgt.$msge.'</ul>'));
                    }//check USER_LOGIN and TEL_NUMBER
                } else {
                    echo json_encode(array('e', 'خطاء لم تقم بإختيار صلاحية تسجيل الدخول للمستخدم .. من فضلك قم بإختيار صلاحية واحده على الاقل', 'E0'));
                }//end check permission
            } else {
                echo json_encode(array('e', 'جميع الحقول المحددة باللون الاحمر مطلوب', 'E0'));
            }// end check input data
        } catch (PDOException $e) {
            $this->conn->rollback();
            echo json_encode(array('e', '<br>error message'.$e->getMessage(), $e->getCode()));
        }

    }*/
    
	
	public function insertUser() {

	    $sql = $this->insert();
	
	    $sqluser       = 'INSERT INTO `USERS`(`USER_GRUOP_ID`, `BRANCH_ID`, `FORENAME`, `LASTNAME`, `GENDER`, `SUSPENDED`, `USER_LOGIN`, `USER_PASSWORD`, `ADMIN_TYPE`, `MERCHANT_TYPE`, `AFFILIATE_TYPE`, `INTERMEDIARY_TYPE`, `DELIVERY_TYPE`, `USER_IMG_LINK`,`BIRTHDAY`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,"1984-09-30")';
	    $sqlaffiliate  = 'INSERT INTO `USERS_AFFILIATE`(`USER_ID`, `AFFILIATEED_WITH`, `COMMISSION_SALES`, `TEAM_COMMISSION`) VALUES (?,?,?,?)';
	    $sqldelivery   = 'INSERT INTO `USERS_DELIVERY`(`USER_ID`, `ZONE_ID`, `PERCENT`) VALUES (?,?,?)';
	    $sqlTelephone  = 'INSERT INTO `TELEPHONES`(`USER_ID`, `TYPE`,`DEFAULTTELL`, `WHATSAPP`, `TELEGRAM`, `TEL_LTYPE`, `TEL_NUMBER`) VALUES (?,1,1,?,?,?,?)';
	    $sqlAddress    = 'INSERT INTO `ADDRESSES`(`USER_ID`, `DEFAULTADD`, `CITY_ID`, `ADDRESS_TITLE`, `ADDRESS`) VALUES (?,1,?,?,?)';
	    $sqlmail       = 'SELECT USER_ID FROM USERS WHERE USER_LOGIN = ? ';
	    $sqltel        = 'SELECT TEL_ID  FROM TELEPHONES WHERE TEL_NUMBER = ? AND USER_ID IS NOT NULL ';

	
	    try {
	
	        // التأكد من إدخال البيانات المطلوبة
	        if (
	            !empty($this->BRANCH_ID) &&
	            !empty($this->FORENAME) &&
	            !empty($this->LASTNAME) &&
	            !empty($this->GENDER) &&
	            isset($this->SUSPENDED) &&
	            !empty($this->USER_LOGIN) &&
	            !empty($this->USER_PASSWORD) &&
	            !empty($this->TEL_NUMBER) &&
	            isset($this->CITY_ID) && $this->CITY_ID > 0 &&
	            !empty($this->ADDRESS) &&
	
	            (
	                (!empty($this->ADMIN_TYPE) && $this->ADMIN_TYPE == 'true' && $this->USER_GRUOP_ID != '') ||
	                (!empty($this->ADMIN_TYPE) && $this->ADMIN_TYPE != 'true')
	            ) &&
	
	            (
	                (!empty($this->DELIVERY_TYPE) && $this->DELIVERY_TYPE == 'true' && !empty($this->ZONE_ID) && !empty($this->PERCENT)) ||
	                (!empty($this->DELIVERY_TYPE) && $this->DELIVERY_TYPE != 'true')
	            ) &&
	
	            (
	                (!empty($this->AFFILIATE_TYPE) && $this->AFFILIATE_TYPE == 'true' && !empty($this->COMMISSION_SALES) && !empty($this->TEAM_COMMISSION)) ||
	                (!empty($this->AFFILIATE_TYPE) && $this->AFFILIATE_TYPE != 'true')
	            )
	
	        ) {
	
	            // التأكد من اختيار صلاحية واحدة على الأقل
	            if (
	                $this->ADMIN_TYPE == 'true' ||
	                $this->MERCHANT_TYPE == 'true' ||
	                $this->AFFILIATE_TYPE == 'true' ||
	                $this->INTERMEDIARY_TYPE == 'true' ||
	                $this->DELIVERY_TYPE == 'true'
	            ) {
	
	                // بدء Transaction
	                $this->conn->beginTransaction();
	
	                // التحقق من اليوزر
	                $stmail = $this->conn->prepare($sqlmail);
	                $stmail->bindValue(1, $this->USER_LOGIN, PDO::PARAM_STR);
	                $stmail->execute();
	
	                $CH_mail = $stmail->fetchColumn();
	
	                // التحقق من رقم الهاتف
	                $sttel = $this->conn->prepare($sqltel);
	                $sttel->bindValue(1, $this->TEL_NUMBER, PDO::PARAM_STR);
	                $sttel->execute();
	
	                $CH_tel = $sttel->fetchColumn();
	
	                // التأكد من أن البيانات غير مكررة
	                if (!$CH_mail && !$CH_tel) {
	
	                    // إضافة المستخدم
	                    $stmt = $this->conn->prepare($sqluser);
	
	                    $stmt->bindValue(1 , $this->USER_GRUOP_ID, PDO::PARAM_STR);
	                    $stmt->bindValue(2 , $this->BRANCH_ID, PDO::PARAM_INT);
	                    $stmt->bindValue(3 , $this->FORENAME, PDO::PARAM_STR);
	                    $stmt->bindValue(4 , $this->LASTNAME, PDO::PARAM_STR);
	                    $stmt->bindValue(5 , $this->GENDER, PDO::PARAM_STR);
	                    $stmt->bindValue(6 , $this->bool_To_int($this->SUSPENDED), PDO::PARAM_INT);
	                    $stmt->bindValue(7 , $this->USER_LOGIN, PDO::PARAM_STR);
	                    $stmt->bindValue(8 , md5($this->USER_PASSWORD), PDO::PARAM_STR);
	                    $stmt->bindValue(9 , $this->bool_To_int($this->ADMIN_TYPE), PDO::PARAM_INT);
	                    $stmt->bindValue(10, $this->bool_To_int($this->MERCHANT_TYPE), PDO::PARAM_INT);
	                    $stmt->bindValue(11, $this->bool_To_int($this->AFFILIATE_TYPE), PDO::PARAM_INT);
	                    $stmt->bindValue(12, $this->bool_To_int($this->INTERMEDIARY_TYPE), PDO::PARAM_INT);
	                    $stmt->bindValue(13, $this->bool_To_int($this->DELIVERY_TYPE), PDO::PARAM_INT);
	                    $stmt->bindValue(14, $this->USER_IMG_LINK, PDO::PARAM_STR);
	
	                    if (!$stmt->execute()) {
	
	                        echo json_encode(array('e', 'حدث خطاء غير معروف', 'E1'));
	
	                        $this->conn->rollback();
	                        return;
	                    }
	
	                    // الحصول على ID المستخدم
	                    $userid = $this->conn->lastInsertId();
	
	                    // إضافة الهاتف
	                    $stmtTelephone = $this->conn->prepare($sqlTelephone);
	
	                    $stmtTelephone->bindValue(1, $userid, PDO::PARAM_INT);
	                    $stmtTelephone->bindValue(2, $this->WHATSAPP, PDO::PARAM_INT);
	                    $stmtTelephone->bindValue(3, $this->TELEGRAM, PDO::PARAM_INT);
	                    $stmtTelephone->bindValue(4, $this->TEL_TYPE, PDO::PARAM_STR);
	                    $stmtTelephone->bindValue(5, $this->TEL_NUMBER, PDO::PARAM_STR);
	
	                    if (!$stmtTelephone->execute()) {
	
	                        echo json_encode(array('e', 'حدث خطاء غير معروف', 'E2'));
	
	                        $this->conn->rollback();
	                        return;
	                    }
	
	                    // إضافة العنوان
	                    $stmtAddress = $this->conn->prepare($sqlAddress);
	
	                    $stmtAddress->bindValue(1, $userid, PDO::PARAM_INT);
	                    $stmtAddress->bindValue(2, $this->CITY_ID, PDO::PARAM_INT);
	                    $stmtAddress->bindValue(3, $this->ADDRESS_TITLE, PDO::PARAM_STR);
	                    $stmtAddress->bindValue(4, $this->ADDRESS, PDO::PARAM_STR);
	
	                    if (!$stmtAddress->execute()) {
	
	                        echo json_encode(array('e', 'حدث خطاء غير معروف', 'E3'));
	
	                        $this->conn->rollback();
	                        return;
	                    }
	
	                    $err = 0;
	
	                    // إضافة بيانات المنسق
	                    if (
	                        !empty($this->AFFILIATE_TYPE) &&
	                        $this->AFFILIATE_TYPE == 'true' &&
	                        !empty($this->COMMISSION_SALES) &&
	                        !empty($this->TEAM_COMMISSION)
	                    ) {
	
	                        $stmtAffiliate = $this->conn->prepare($sqlaffiliate);
	
	                        $stmtAffiliate->bindValue(1, $userid, PDO::PARAM_INT);
	
	                        if (!empty($this->AFFILIATEED_WITH)) {
	
	                            $stmtAffiliate->bindValue(2, $this->AFFILIATEED_WITH, PDO::PARAM_INT);
	
	                        } else {
	
	                            $stmtAffiliate->bindValue(2, null, PDO::PARAM_NULL);
	                        }
	
	                        $stmtAffiliate->bindValue(3, $this->COMMISSION_SALES, PDO::PARAM_STR);
	                        $stmtAffiliate->bindValue(4, $this->TEAM_COMMISSION, PDO::PARAM_STR);
	
	                        if (!$stmtAffiliate->execute()) {
	                            $err++;
	                        }
	                    }
	
	                    // إضافة بيانات موظف الشحن
	                    if (
	                        !empty($this->DELIVERY_TYPE) &&
	                        $this->DELIVERY_TYPE == 'true' &&
	                        !empty($this->ZONE_ID) &&
	                        !empty($this->PERCENT)
	                    ) {
	
	                        $stmtDelivery = $this->conn->prepare($sqldelivery);
	
	                        $stmtDelivery->bindValue(1, $userid, PDO::PARAM_INT);
	                        $stmtDelivery->bindValue(2, $this->ZONE_ID, PDO::PARAM_INT);
	                        $stmtDelivery->bindValue(3, $this->PERCENT, PDO::PARAM_STR);
	
	                        if (!$stmtDelivery->execute()) {
	                            $err++;
	                        }
	                    }
	
	                    // التأكد من نجاح جميع العمليات
	                    if ($err <= 0) {
	
	                        $this->conn->commit();
	
	                        echo json_encode(array('s', 'تم حفظ المستخدم بنجاح'));
	
	                    } else {
	
	                        $this->conn->rollback();
	
	                        echo json_encode(array('e', 'حدث خطاء غير معروف'));
	
	                        return;
	                    }
	
	                } else {
	
	                    $msge = '';
	                    $msgt = '';
	
	                    if ($CH_mail) {
	                        $msge = '<li>البريد الالكترونى '.$this->USER_LOGIN.' مسجل بالفعل بقواعد البيانات</li>';
	                    }
	
	                    if ($CH_tel) {
	                        $msgt = '<li>رقم الهاتف '.$this->TEL_NUMBER.' مسجل بالفعل بقواعد البيانات</li>';
	                    }
	
	                    echo json_encode(array('e', '<ul>'.$msgt.$msge.'</ul>'));
	                }
	
	            } else {
	
	                echo json_encode(array(
	                    'e',
	                    'خطاء لم تقم بإختيار صلاحية تسجيل الدخول للمستخدم .. من فضلك قم بإختيار صلاحية واحده على الاقل',
	                    'E0'
	                ));
	            }
	
	        } else {
	
	            echo json_encode(array(
	                'e',
	                'جميع الحقول المحددة باللون الاحمر مطلوب',
	                'E0'
	            ));
	        }
	
	    } catch (PDOException $e) {
	
	        if ($this->conn->inTransaction()) {
	            $this->conn->rollback();
	        }
	
	        echo json_encode(array(
	            'e',
	            '<br>error message '.$e->getMessage(),
	            $e->getCode()
	        ));
	    }
	}

    /*public function updateUser() {
        $sql = $this->update();
		$sqlinsert = $this->insert();
        $sqluser = $sql[0];
        $sqlAffiliate = $sql[1];
        $sqlDelivery = $sql[2];
        $sqltel = $sql[3];
        $sqladd = $sql[4];
		$sqladdinsert = $sqlinsert[4];
        $sqlcmail = $sql[5];
        $sqlctel = $sql[6];
        try {
            //التأكد من ادخل البيانات المطلوبة
            /*if (!empty($this->USER_ID) &&
                !empty($this->BRANCH_ID) &&
                !empty($this->FORENAME) &&
                !empty($this->LASTNAME) &&
                !empty($this->GENDER) &&
                !empty($this->SUSPENDED) &&
                !empty($this->USER_LOGIN) &&
                //!empty($this->USER_PASSWORD) &&
                !empty($this->TEL_NUMBER) &&
                !empty($this->CITY_ID) &&
                !empty($this->ADDRESS) &&
                (
                    (!empty($this->ADMIN_TYPE) && $this->ADMIN_TYPE == 'true' && $this->USER_GRUOP_ID != '') ||
                    (!empty($this->ADMIN_TYPE) && $this->ADMIN_TYPE != 'true')
                ) &&
                (
                    (!empty($this->DELIVERY_TYPE) && $this->DELIVERY_TYPE == 'true' && !empty($this->ZONE_ID) && !empty($this->PERCENT)) ||
                    (!empty($this->DELIVERY_TYPE) && $this->DELIVERY_TYPE != 'true')
                ) &&
                (
                    (!empty($this->AFFILIATE_TYPE) && $this->AFFILIATE_TYPE == 'true' && !empty($this->COMMISSION_SALES) && !empty($this->TEAM_COMMISSION)) ||
                    (!empty($this->AFFILIATE_TYPE) && $this->AFFILIATE_TYPE != 'true')
                )) 
                
                
                if (
				    isset($this->USER_ID) &&
				    isset($this->BRANCH_ID) &&
				    $this->FORENAME !== '' &&
				    $this->LASTNAME !== '' &&
				    isset($this->GENDER) &&
				    isset($this->SUSPENDED) &&
				    $this->USER_LOGIN !== '' &&
				    isset($this->TEL_NUMBER) &&
				    isset($this->CITY_ID) &&
				    $this->ADDRESS !== '' &&
				
				    (
				        $this->ADMIN_TYPE !== 'true' ||
				        ($this->ADMIN_TYPE === 'true' && $this->USER_GRUOP_ID !== '')
				    ) &&
				
				    (
				        $this->DELIVERY_TYPE !== 'true' ||
				        ($this->DELIVERY_TYPE === 'true' && isset($this->ZONE_ID) && isset($this->PERCENT))
				    ) &&
				
				    (
				        $this->AFFILIATE_TYPE !== 'true' ||
				        ($this->AFFILIATE_TYPE === 'true' && isset($this->COMMISSION_SALES) && isset($this->TEAM_COMMISSION))
				    )
				){
                //التأكد من اختيار صلاحية واحده على الاقل
                if ($this->ADMIN_TYPE == 'true' ||
                    $this->MERCHANT_TYPE == 'true' ||
                    $this->AFFILIATE_TYPE == 'true' ||
                    $this->INTERMEDIARY_TYPE == 'true' ||
                    $this->DELIVERY_TYPE == 'true') {
                    //فتح الاتصال بقواعد البيانات
                    $this->conn->beginTransaction();
                    //اضافة بيانات المستخدم فى جدول المستخدمين
                    $stmail = $this->conn->prepare($sqlcmail);
                    $stmail->bindValue(1, $this->USER_LOGIN, PDO::PARAM_STR);
                    $stmail->bindValue(2, $this->USER_ID, PDO::PARAM_INT);
                    $stmail->execute();
                    $CH_mail = $stmail->rowCount();
                    $sttel = $this->conn->prepare($sqlctel);
                    $sttel->bindValue(1, $this->TEL_NUMBER, PDO::PARAM_STR);
                    $sttel->bindValue(2, $this->USER_ID, PDO::PARAM_INT);
                    $sttel->execute();
                    $CH_tel = $sttel->rowCount();
                    if ($CH_tel <= 0) {
                        $stmt = $this->conn->prepare($sqluser);
                        $stmt->bindValue(1, $this->USER_GRUOP_ID, PDO::PARAM_INT);
                        $stmt->bindValue(2, $this->BRANCH_ID, PDO::PARAM_INT);
                        $stmt->bindValue(3, $this->FORENAME, PDO::PARAM_STR);
                        $stmt->bindValue(4, $this->LASTNAME, PDO::PARAM_STR);
                        $stmt->bindValue(5, $this->GENDER, PDO::PARAM_STR);
                        $stmt->bindValue(6, $this->bool_To_int($this->SUSPENDED), PDO::PARAM_INT);
                        //$stmt->bindValue(7, strval($this->USER_LOGIN), PDO::PARAM_STR);
                        //$stmt->bindValue(8, strval(md5($this->USER_PASSWORD)), PDO::PARAM_STR);
                        $stmt->bindValue(7, $this->bool_To_int($this->ADMIN_TYPE), PDO::PARAM_INT);
                        $stmt->bindValue(8, $this->bool_To_int($this->MERCHANT_TYPE), PDO::PARAM_INT);
                        $stmt->bindValue(9, $this->bool_To_int($this->AFFILIATE_TYPE), PDO::PARAM_INT);
                        $stmt->bindValue(10, $this->bool_To_int($this->INTERMEDIARY_TYPE), PDO::PARAM_INT);
                        $stmt->bindValue(11, $this->bool_To_int($this->DELIVERY_TYPE), PDO::PARAM_INT);
                        $stmt->bindValue(12, $this->USER_IMG_LINK, PDO::PARAM_STR);
                        $stmt->bindValue(13, $this->USER_ID, PDO::PARAM_INT);
                        $result = $stmt->execute();
                        if ($result) {
                            //اضافة رقم الهاتف فى جدول الهواتف
                            $stmt_tel = $this->conn->prepare($sqltel);
                            $stmt_tel->bindValue(1, $this->DEFAULTTELL, PDO::PARAM_INT);
                            $stmt_tel->bindValue(2, $this->WHATSAPP, PDO::PARAM_INT);
                            $stmt_tel->bindValue(3, $this->TELEGRAM, PDO::PARAM_INT);
                            $stmt_tel->bindValue(4, $this->TEL_TYPE, PDO::PARAM_STR);
                            $stmt_tel->bindValue(5, $this->TEL_NUMBER, PDO::PARAM_STR);
                            $stmt_tel->bindValue(6, $this->TEL_ID, PDO::PARAM_INT);
                            $result = $stmt_tel->execute();
                            if ($result) {
								
                                //اضافة العنوان فى جدول العناوين
                                if(!empty($this->ADDRESS_ID)){
									$stmtAdd = $this->conn->prepare($sqladd);
									$stmtAdd->bindValue(1, $this->CITY_ID, PDO::PARAM_INT);
									$stmtAdd->bindValue(2, $this->ADDRESS_TITLE, PDO::PARAM_STR);
									$stmtAdd->bindValue(3, $this->ADDRESS, PDO::PARAM_STR);
									$stmtAdd->bindValue(4, $this->ADDRESS_ID, PDO::PARAM_INT);
								} else {
									$stmtAdd = $this->conn->prepare($sqladdinsert);
									$stmtAdd->bindValue(1, $this->USER_ID, PDO::PARAM_INT);
									$stmtAdd->bindValue(2, $this->CITY_ID, PDO::PARAM_INT);
									$stmtAdd->bindValue(3, $this->ADDRESS_TITLE, PDO::PARAM_STR);
									$stmtAdd->bindValue(4, $this->ADDRESS, PDO::PARAM_STR);
								}
                                if ($stmtAdd->execute()) {
                                    $err = 0;
									$query = $this->insert();
                                    //اضافة صلاحية المنسق
						            if (isset($this->AFFILIATE_TYPE) && $this->AFFILIATE_TYPE == 'true' && isset($is->COMMISSION_SALES) && isset($this->TEAM_COMMISSION)) {
                                        $sqlselectAf = "SELECT * FROM `USERS_AFFILIATE` WHERE `USER_ID` = ? ";
									    $stmtAf = $this->conn->prepare($sqlselectAf);
									    $stmtAf->bindValue(1, $this->USER_ID, PDO::PARAM_INT);
									    $stmtAf->execute();
									    $chAf = $stmtAf->rowCount(); 
                                        if($chAf === 1){
                                            $stmtAffiliate = $this->conn->prepare($sqlAffiliate);
                                            if(!empty($this->AFFILIATEED_WITH)){
                                                $stmtAffiliate->bindValue(1, $this->AFFILIATEED_WITH, PDO::PARAM_INT);
                                            } else {
                                                $stmtAffiliate->bindValue(1, null);
                                            }
                                            $stmtAffiliate->bindValue(2, $this->COMMISSION_SALES, PDO::PARAM_STR);
                                            $stmtAffiliate->bindValue(3, $this->TEAM_COMMISSION, PDO::PARAM_STR);
                                            $stmtAffiliate->bindValue(4, $this->USER_ID, PDO::PARAM_INT);
                                            $result_AFFI = $stmtAffiliate->execute();
                                            if (!$result_AFFI) {
                                                $err++;
                                            }
                                        } else {
										    $sqlinsertAf = $query[1];
										    $stmtAffiliate = $this->conn->prepare($sqlinsertAf);  
                                            $stmtAffiliate->bindValue(1, $this->USER_ID, PDO::PARAM_INT);
                                            if(!empty($this->AFFILIATEED_WITH)){
                                                $stmtAffiliate->bindValue(2, $this->AFFILIATEED_WITH, PDO::PARAM_INT);
                                            }else{
                                                $stmtAffiliate->bindValue(2, null);
                                            }
                                            $stmtAffiliate->bindValue(3, $this->COMMISSION_SALES, PDO::PARAM_STR);
                                            $stmtAffiliate->bindValue(4, $this->TEAM_COMMISSION, PDO::PARAM_STR);
                                            if (!$stmtAffiliate->execute()) {
                                                $err++;
                                            }
									    }
                                    }
                                    //اضافة صلاحية موظف الشحن
                                    if ( !empty($this->DELIVERY_TYPE) && $this->DELIVERY_TYPE == 'true' && !empty($this->ZONE_ID) && !empty($this->PERCENT)) {
                                        $sqlselectDe = "SELECT * FROM `USERS_DELIVERY` WHERE `USER_ID` = ? ";
									    $stmtDe = $this->conn->prepare($sqlselectDe);
									    $stmtDe->bindValue(1, $this->USER_ID, PDO::PARAM_INT);
									    $stmtDe->execute();
									    $chDe = $stmtDe->rowCount();
									    if($chDe===1){
                                            $stmtDelivery = $this->conn->prepare($sqlDelivery);
                                            $stmtDelivery->bindValue(1, $this->ZONE_ID, PDO::PARAM_INT);
                                            $stmtDelivery->bindValue(2, $this->PERCENT, PDO::PARAM_STR);
                                            $stmtDelivery->bindValue(3, $this->USER_ID, PDO::PARAM_INT);
                                            if (!$stmtDelivery->execute()) {
                                                $err++;
                                            }
                                        } else {
										    $sqlinsertDe = $query[2];
										    $stmtDelivery = $this->conn->prepare($sqlinsertDe);
                                            $stmtDelivery->bindValue(1, $this->USER_ID, PDO::PARAM_INT);
                                            $stmtDelivery->bindValue(2, $this->ZONE_ID, PDO::PARAM_INT);
                                            $stmtDelivery->bindValue(3, $this->PERCENT, PDO::PARAM_STR);
                                            if (!$stmtDelivery->execute()) {
                                                $err++;
                                            }
									    }
                                    }
                                    //التأكد من حفظ جميع البيانات بنجاح
                                    if ($err == 0) {
                                        echo json_encode(array('s', 'تم حفظ المستخدم بنجاح'));
                                        $this->conn->commit(); //تأكيد الحفظ بقواعد البيانات بنجاح
                                    } else {
                                        echo json_encode(array('e', 'حدث خطاء غير معروف'));
                                        $this->conn->rollback(); //الغاء كل عمليات الحفظ
                                    }
                                } else {
                                    echo json_encode(array('e', 'حدث خطاء غير معروف', 'E3'));
                                    $this->conn->rollback();
                                }//end address
                            } else {
                                echo json_encode(array('e', 'حدث خطاء غير معروف', 'E2'));
                                $this->conn->rollback();
                            }//end TEL_NUMBER
                        } else {
                            echo json_encode(array('e', 'حدث خطاء غير معروف', 'E1'));
                            $this->conn->rollback();
                        }//end users
                    } else {
                        echo json_encode(array('e', 'رقم الهاتف '.$this->TEL_NUMBER.' مسجل مسبقا بقواعد البيانات', 'E0'));
                    }//end check USER_LOGIN and TEL_NUMBER
                } else {
                    echo json_encode(array('e', 'خطاء لم تقم بإختيار صلاحية تسجيل الدخول للمستخدم .. من فضلك قم بإختيار صلاحية واحده على الاقل', 'E0'));
                }//end check permission
            } else {
                echo json_encode(array('e', 'جميع الحقول المحددة باللون الاحمر مطلوب', 'E0'));
            }// end check input data
        } catch (PDOException $e) {
            $this->conn->rollback();
            echo json_encode(array('e', '<br>error message'.$e->getMessage(), $e->getCode()));
        }
    }*/
    public function updateUser() {

	    $sql = $this->update();
	    $sqlinsert = $this->insert();
	
	    $sqluser       = $sql[0];
	    $sqlAffiliate  = $sql[1];
	    $sqlDelivery   = $sql[2];
	    $sqltel        = $sql[3];
	    $sqladd        = $sql[4];
	    $sqladdinsert  = $sqlinsert[4];
	    $sqlcmail      = $sql[5];
	    $sqlctel       = $sql[6];
	
	    try {
	
	        if (
	
	            isset($this->USER_ID) &&
	            isset($this->BRANCH_ID) &&
	            $this->FORENAME !== '' &&
	            $this->LASTNAME !== '' &&
	            isset($this->GENDER) &&
	            isset($this->SUSPENDED) &&
	            $this->USER_LOGIN !== '' &&
	            isset($this->TEL_NUMBER) &&
	            isset($this->CITY_ID) &&
	            $this->ADDRESS !== '' &&
	
	            (
	                $this->ADMIN_TYPE !== 'true' ||
	                ($this->ADMIN_TYPE === 'true' && $this->USER_GRUOP_ID !== '')
	            ) &&
	
	            (
	                $this->DELIVERY_TYPE !== 'true' ||
	                ($this->DELIVERY_TYPE === 'true' && isset($this->ZONE_ID) && isset($this->PERCENT))
	            ) &&
	
	            (
	                $this->AFFILIATE_TYPE !== 'true' ||
	                ($this->AFFILIATE_TYPE === 'true' && isset($this->COMMISSION_SALES) && isset($this->TEAM_COMMISSION))
	            )
	
	        ) {
	
	            // التأكد من وجود صلاحية واحدة على الأقل
	            if (
	
	                $this->ADMIN_TYPE == 'true' ||
	                $this->MERCHANT_TYPE == 'true' ||
	                $this->AFFILIATE_TYPE == 'true' ||
	                $this->INTERMEDIARY_TYPE == 'true' ||
	                $this->DELIVERY_TYPE == 'true'
	
	            ) {
	
	                $this->conn->beginTransaction();
	
	                // فحص اليوزر
	                $stmail = $this->conn->prepare($sqlcmail);
	
	                $stmail->bindValue(1, $this->USER_LOGIN, PDO::PARAM_STR);
	                $stmail->bindValue(2, $this->USER_ID, PDO::PARAM_INT);
	
	                $stmail->execute();
	
	                $CH_mail = $stmail->fetchColumn();
	
	                // فحص الهاتف
	                $sttel = $this->conn->prepare($sqlctel);
	
	                $sttel->bindValue(1, $this->TEL_NUMBER, PDO::PARAM_STR);
	                $sttel->bindValue(2, $this->USER_ID, PDO::PARAM_INT);
	
	                $sttel->execute();
	
	                $CH_tel = $sttel->fetchColumn();
	
	                // التأكد من عدم تكرار الهاتف واليوزر
	                if (!$CH_mail && !$CH_tel) {
	
	                    // تحديث المستخدم
	                    $stmt = $this->conn->prepare($sqluser);
	
	                    $stmt->bindValue(1, $this->USER_GRUOP_ID, PDO::PARAM_INT);
	                    $stmt->bindValue(2, $this->BRANCH_ID, PDO::PARAM_INT);
	                    $stmt->bindValue(3, $this->FORENAME, PDO::PARAM_STR);
	                    $stmt->bindValue(4, $this->LASTNAME, PDO::PARAM_STR);
	                    $stmt->bindValue(5, $this->GENDER, PDO::PARAM_STR);
	                    $stmt->bindValue(6, $this->bool_To_int($this->SUSPENDED), PDO::PARAM_INT);
	
	                    $stmt->bindValue(7, $this->bool_To_int($this->ADMIN_TYPE), PDO::PARAM_INT);
	                    $stmt->bindValue(8, $this->bool_To_int($this->MERCHANT_TYPE), PDO::PARAM_INT);
	                    $stmt->bindValue(9, $this->bool_To_int($this->AFFILIATE_TYPE), PDO::PARAM_INT);
	                    $stmt->bindValue(10, $this->bool_To_int($this->INTERMEDIARY_TYPE), PDO::PARAM_INT);
	                    $stmt->bindValue(11, $this->bool_To_int($this->DELIVERY_TYPE), PDO::PARAM_INT);
	
	                    $stmt->bindValue(12, $this->USER_IMG_LINK, PDO::PARAM_STR);
	
	                    $stmt->bindValue(13, $this->USER_ID, PDO::PARAM_INT);
	
	                    if (!$stmt->execute()) {
	
	                        echo json_encode(array('e', 'حدث خطاء غير معروف', 'E1'));
	
	                        $this->conn->rollback();
	                        return;
	                    }
	
	                    // تحديث الهاتف
	                    $stmt_tel = $this->conn->prepare($sqltel);
	
	                    $stmt_tel->bindValue(1, $this->DEFAULTTELL, PDO::PARAM_INT);
	                    $stmt_tel->bindValue(2, $this->WHATSAPP, PDO::PARAM_INT);
	                    $stmt_tel->bindValue(3, $this->TELEGRAM, PDO::PARAM_INT);
	                    $stmt_tel->bindValue(4, $this->TEL_TYPE, PDO::PARAM_STR);
	                    $stmt_tel->bindValue(5, $this->TEL_NUMBER, PDO::PARAM_STR);
	                    $stmt_tel->bindValue(6, $this->TEL_ID, PDO::PARAM_INT);
	
	                    if (!$stmt_tel->execute()) {
	
	                        echo json_encode(array('e', 'حدث خطاء غير معروف', 'E2'));
	
	                        $this->conn->rollback();
	                        return;
	                    }
	
	                    // تحديث أو إضافة العنوان
	                    if (!empty($this->ADDRESS_ID)) {
	
	                        $stmtAdd = $this->conn->prepare($sqladd);
	
	                        $stmtAdd->bindValue(1, $this->CITY_ID, PDO::PARAM_INT);
	                        $stmtAdd->bindValue(2, $this->ADDRESS_TITLE, PDO::PARAM_STR);
	                        $stmtAdd->bindValue(3, $this->ADDRESS, PDO::PARAM_STR);
	                        $stmtAdd->bindValue(4, $this->ADDRESS_ID, PDO::PARAM_INT);
	
	                    } else {
	
	                        $stmtAdd = $this->conn->prepare($sqladdinsert);
	
	                        $stmtAdd->bindValue(1, $this->USER_ID, PDO::PARAM_INT);
	                        $stmtAdd->bindValue(2, $this->CITY_ID, PDO::PARAM_INT);
	                        $stmtAdd->bindValue(3, $this->ADDRESS_TITLE, PDO::PARAM_STR);
	                        $stmtAdd->bindValue(4, $this->ADDRESS, PDO::PARAM_STR);
	                    }
	
	                    if (!$stmtAdd->execute()) {
	
	                        echo json_encode(array('e', 'حدث خطاء غير معروف', 'E3'));
	
	                        $this->conn->rollback();
	                        return;
	                    }
	
	                    $err = 0;
	
	                    $query = $this->insert();
	
	                    // affiliate
	                    if (
	
	                        isset($this->AFFILIATE_TYPE) &&
	                        $this->AFFILIATE_TYPE == 'true' &&
	                        isset($this->COMMISSION_SALES) &&
	                        isset($this->TEAM_COMMISSION)
	
	                    ) {
	
	                        $sqlselectAf = "SELECT USER_ID FROM USERS_AFFILIATE WHERE USER_ID = ?";
	
	                        $stmtAf = $this->conn->prepare($sqlselectAf);
	
	                        $stmtAf->bindValue(1, $this->USER_ID, PDO::PARAM_INT);
	
	                        $stmtAf->execute();
	
	                        $chAf = $stmtAf->fetchColumn();
	
	                        if ($chAf) {
	
	                            $stmtAffiliate = $this->conn->prepare($sqlAffiliate);
	
	                            if (!empty($this->AFFILIATEED_WITH)) {
	
	                                $stmtAffiliate->bindValue(1, $this->AFFILIATEED_WITH, PDO::PARAM_INT);
	
	                            } else {
	
	                                $stmtAffiliate->bindValue(1, null, PDO::PARAM_NULL);
	                            }
	
	                            $stmtAffiliate->bindValue(2, $this->COMMISSION_SALES, PDO::PARAM_STR);
	                            $stmtAffiliate->bindValue(3, $this->TEAM_COMMISSION, PDO::PARAM_STR);
	                            $stmtAffiliate->bindValue(4, $this->USER_ID, PDO::PARAM_INT);
	
	                            if (!$stmtAffiliate->execute()) {
	                                $err++;
	                            }
	
	                        } else {
	
	                            $sqlinsertAf = $query[1];
	
	                            $stmtAffiliate = $this->conn->prepare($sqlinsertAf);
	
	                            $stmtAffiliate->bindValue(1, $this->USER_ID, PDO::PARAM_INT);
	
	                            if (!empty($this->AFFILIATEED_WITH)) {
	
	                                $stmtAffiliate->bindValue(2, $this->AFFILIATEED_WITH, PDO::PARAM_INT);
	
	                            } else {
	
	                                $stmtAffiliate->bindValue(2, null, PDO::PARAM_NULL);
	                            }
	
	                            $stmtAffiliate->bindValue(3, $this->COMMISSION_SALES, PDO::PARAM_STR);
	                            $stmtAffiliate->bindValue(4, $this->TEAM_COMMISSION, PDO::PARAM_STR);
	
	                            if (!$stmtAffiliate->execute()) {
	                                $err++;
	                            }
	                        }
	                    }
	
	                    // delivery
	                    if (
	
	                        !empty($this->DELIVERY_TYPE) &&
	                        $this->DELIVERY_TYPE == 'true' &&
	                        !empty($this->ZONE_ID) &&
	                        !empty($this->PERCENT)
	
	                    ) {
	
	                        $sqlselectDe = "SELECT USER_ID FROM USERS_DELIVERY WHERE USER_ID = ?";
	
	                        $stmtDe = $this->conn->prepare($sqlselectDe);
	
	                        $stmtDe->bindValue(1, $this->USER_ID, PDO::PARAM_INT);
	
	                        $stmtDe->execute();
	
	                        $chDe = $stmtDe->fetchColumn();
	
	                        if ($chDe) {
	
	                            $stmtDelivery = $this->conn->prepare($sqlDelivery);
	
	                            $stmtDelivery->bindValue(1, $this->ZONE_ID, PDO::PARAM_INT);
	                            $stmtDelivery->bindValue(2, $this->PERCENT, PDO::PARAM_STR);
	                            $stmtDelivery->bindValue(3, $this->USER_ID, PDO::PARAM_INT);
	
	                            if (!$stmtDelivery->execute()) {
	                                $err++;
	                            }
	
	                        } else {
	
	                            $sqlinsertDe = $query[2];
	
	                            $stmtDelivery = $this->conn->prepare($sqlinsertDe);
	
	                            $stmtDelivery->bindValue(1, $this->USER_ID, PDO::PARAM_INT);
	                            $stmtDelivery->bindValue(2, $this->ZONE_ID, PDO::PARAM_INT);
	                            $stmtDelivery->bindValue(3, $this->PERCENT, PDO::PARAM_STR);
	
	                            if (!$stmtDelivery->execute()) {
	                                $err++;
	                            }
	                        }
	                    }
	
	                    // إنهاء العملية
	                    if ($err == 0) {
	
	                        $this->conn->commit();
	
	                        echo json_encode(array('s', 'تم حفظ المستخدم بنجاح'));
	
	                    } else {
	
	                        $this->conn->rollback();
	
	                        echo json_encode(array('e', 'حدث خطاء غير معروف'));
	                    }
	
	                } else {
	
	                    $msg = '';
	
	                    if ($CH_mail) {
	                        $msg .= '<li>اسم المستخدم '.$this->USER_LOGIN.' مسجل مسبقًا</li>';
	                    }
	
	                    if ($CH_tel) {
	                        $msg .= '<li>رقم الهاتف '.$this->TEL_NUMBER.' مسجل مسبقًا</li>';
	                    }
	
	                    echo json_encode(array('e', '<ul>'.$msg.'</ul>', 'E0'));
	                }
	
	            } else {
	
	                echo json_encode(array(
	                    'e',
	                    'خطاء لم تقم بإختيار صلاحية تسجيل الدخول للمستخدم .. من فضلك قم بإختيار صلاحية واحده على الاقل',
	                    'E0'
	                ));
	            }
	
	        } else {
	
	            echo json_encode(array(
	                'e',
	                'جميع الحقول المحددة باللون الاحمر مطلوب',
	                'E0'
	            ));
	        }
	
	    } catch (PDOException $e) {
	
	        if ($this->conn->inTransaction()) {
	            $this->conn->rollback();
	        }
	
	        echo json_encode(array(
	            'e',
	            '<br>error message '.$e->getMessage(),
	            $e->getCode()
	        ));
	    }
	}
    public function deleteUser() {
        $sql = $this->dele();
        $sqluser = $sql[0];
        $sqlaffiliate = $sql[1];
    }
	public function getScript(){
	    //password_verify
	    //password_hash
		$sqlQuery='SELECT 
                     A.ADMIN_TYPE,
                     A.AFFILIATE_TYPE,
                     A.INTERMEDIARY_TYPE,
                     A.DELIVERY_TYPE,
                     A.MERCHANT_TYPE,
                     A.SUSPENDED,
                     B.USER_SCRIPT
                    FROM  USERS A
                     LEFT JOIN user_gruop B
                     ON A.USER_GRUOP_ID = B.USER_GRUOP_ID
                     WHERE A.USER_ID = ? ';
		try {
			$stmt = $this->conn->prepare($sqlQuery);
			$stmt->bindValue(1, $this->USER_ID, PDO::PARAM_STR);
			$stmt->execute();
			$result = $stmt;
			$records = array();
			while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
				$records['ADMIN_TYPE']        = $record['ADMIN_TYPE'];
                $records['AFFILIATE_TYPE']    = $record['AFFILIATE_TYPE'];
                $records['INTERMEDIARY_TYPE'] = $record['INTERMEDIARY_TYPE'];
                $records['DELIVERY_TYPE']     = $record['DELIVERY_TYPE'];
                $records['MERCHANT_TYPE']     = $record['MERCHANT_TYPE'];
                $records['SUSPENDED']         = $record['SUSPENDED'];
                $records['USER_SCRIPT']       = $record['USER_SCRIPT'];
			}
			return $records;
		} catch (PDOException $e) {
            echo json_encode(array('e', '<br>error message'.$e->getMessage(), $e->getCode()));
		}
	}
	public function updatePassword($userId, $password){
		$sqlUser 		= 'UPDATE `USERS` SET `USER_PASSWORD` = ? WHERE USER_ID = ?';
		$stmt = $this->conn->prepare($sqlUser);
		$stmt->bindValue(1 , md5($password), PDO::PARAM_STR);
		$stmt->bindValue(2 , $userId, 		 PDO::PARAM_INT);
		$stmt->execute();
		if($stmt->execute()){
			return ['s','ناجح','تم تحديث كلمة المرور بنجاح'];
		} else {
			return ['e','خطاء','لم ننجح فى تحديث كلمة المرور'];
		}
	}
	public function getDeliveryList() {
		$serch = isset($_GET['query']) ? $_GET['query'] : "";
		$query = "SELECT * FROM (SELECT ua.user_id,CONCAT(FORENAME,' ',LASTNAME) as uname FROM users_delivery ua LEFT JOIN users u on ua.USER_ID = u.USER_ID) TBL WHERE uname LIKE '%".$serch."%'";
	
		$stmt = $this->conn->prepare($query);
		//$stmt->bindValue(1, $serch, PDO::PARAM_STR);
		$stmt->execute();
		$returnData = [];
		//$returnData .="<select id='itemName' class='form-control p-1 sw2'>";
	

		while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$returnData[] = ['id' =>$record['user_id'],'name' => $record['uname']];
		}
		return $returnData;
	}
	public function getMarketerList() {
		$serch = isset($_GET['query']) ? $_GET['query'] : "";
		$query = "SELECT * FROM (SELECT ua.user_id,CONCAT(FORENAME,' ',LASTNAME) as uname FROM users_affiliate ua LEFT JOIN users u on ua.USER_ID = u.USER_ID) TBL WHERE uname LIKE '%".$serch."%'";
	
		$stmt = $this->conn->prepare($query);
		//$stmt->bindValue(1, $serch, PDO::PARAM_STR);
		$stmt->execute();
		$returnData = [];
		//$returnData .="<select id='itemName' class='form-control p-1 sw2'>";
	

		while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$returnData[] = ['id' =>$record['user_id'],'name' => $record['uname']];
		}
		return $returnData;
	}
} // end class