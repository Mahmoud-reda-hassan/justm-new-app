<?php
require_once __DIR__ . '/Mailer.php';
//require_once ABSPATH . "/config.php";
//require_once ABSPATH . "/inc/security.php";

//session_set_cookie_params(0); // الجلسة تنتهي عند إغلاق المتصفح
session_start();

class user_permission {
	public  $PageType ;
    public  $Pagelink ="" ;
    public  $title ;
    public  $css        =array();
    public  $js_header  =array();
    public  $js_footer  =array();
    public  $privetjs   = "";
    public  $js_page    = array();//"hnl.mobileConsole.js");
    private $furldir;
    //public $usSc  = unserialize($_SESSION['USER_SCRIPT']);

/*public function checkSession() {
if(!isset($_COOKIE['fpId'],$_COOKIE['USER_AUTH'])){
	$data = Security::getUserFromCookie($_COOKIE['fpId']);
	$remember= isset($_COOKIE['rem']) ? $_COOKIE['rem'] : false;
//	if(!isset($data["FINGERPRINT"],$_COOKIE['fpId']) || $data["FINGERPRINT"] !== $_COOKIE['fpId']) return false;
	
   	if(!isset($_SESSION["USER_ID"])) {
   	
		if (isset($_COOKIE['fpId']) && $_COOKIE['fpId']) {
	        $_SESSION["USER_ID"] = $data["USER_ID"];
		    $_SESSION["FNAME"] = $data["FNAME"];
		    $_SESSION["LNAME"] = $data["LNAME"];
		    $_SESSION["GENDER"] = $data["GENDER"];
		    $_SESSION["SUSPENDED"] = $data["SUSPENDED"];
		    $_SESSION["USER_GRUOP_NAME"] = $data["USER_GRUOP_NAME"];
		    $_SESSION["USER_IMG_LINK"] = $data["USER_IMG_LINK"];
		    $_SESSION["COMPANY_LOGO"] = $data["COMPANY_LOGO"];
		    $_SESSION["COMPANY_NAME"] = $data["COMPANY_NAME"];
		    $_SESSION["ADMIN_TYPE"] = $data["ADMIN_TYPE"];
		    $_SESSION["MERCHANT_TYPE"] = $data["MERCHANT_TYPE"];
		    $_SESSION["AFFILIATE_TYPE"] = $data["AFFILIATE_TYPE"];
		    $_SESSION["INTERMEDIARY_TYPE"] = $data["INTERMEDIARY_TYPE"];
		    $_SESSION["DELIVERY_TYPE"] = $data["DELIVERY_TYPE"];
		    $_SESSION["USER_SCRIPT"] = $data["USER_SCRIPT"];
		    $_SESSION["COMPANY_ID"] = $data["COMPANY_ID"];
		    $_SESSION["BRANCH_ID"] = $data["BRANCH_ID"];
		    $_SESSION["BRANCH_NAME"] = $data["BRANCH_NAME"];
		    $_SESSION["CITY_ID"] = $data["CITY_ID"];
		    $_SESSION["GOV_ID"] = $data["GOV_ID"];
		    $_SESSION["COUNTRY_ID"] = $data["COUNTRY_ID"];
		    $_SESSION["LOGIN_TIME"] = $data["LOGIN_TIME"];
		    $_SESSION["FINGERPRINT"] = $data["FINGERPRINT"];
		    //Security::saveUserCookie($_SESSION, $_COOKIE['fpId']);
		}
   	}
    //--------------------Check login----------------------
        
        if (
            isset(  
                $_SESSION["USER_ID"],
                $_SESSION["FNAME"],
                $_SESSION["LNAME"],
                $_SESSION["GENDER"],
                $_SESSION["SUSPENDED"],
                $_SESSION["USER_IMG_LINK"],
                $_SESSION["COMPANY_LOGO"],
                $_SESSION["COMPANY_NAME"],
                $_SESSION["BRANCH_NAME"],
                $_SESSION["ADMIN_TYPE"],
                $_SESSION["MERCHANT_TYPE"],
                $_SESSION["AFFILIATE_TYPE"],
                $_SESSION["INTERMEDIARY_TYPE"],
                $_SESSION["DELIVERY_TYPE"],
                $_SESSION["BRANCH_ID"],
                $_SESSION["FINGERPRINT"],
                $data["FINGERPRINT"],
                $_COOKIE['fpId'],
                
            ) && $_SESSION["FINGERPRINT"] === $_COOKIE['fpId'] && $_SESSION["FINGERPRINT"] === $data["FINGERPRINT"]
        ){ 
        	
        	Security::saveUserCookie($_SESSION, $_COOKIE['fpId'], $remember);
        	if(!isset($_COOKIE['fpId'], $_SESSION["FINGERPRINT"]) ||  $_COOKIE['fpId'] != $_SESSION["FINGERPRINT"] ){
				return false;
			}
        	return "true";
        } else {
            return "false";
        }
} else {
	return "false";
}
    //session_write_close();
}*/
public function PageNotFound(){
	// صفحة غير موجودة: لا تستخدم تحويلًا داخليًا حتى لا تتكرر الطلبات.
	http_response_code(404);
	$this->Pagelink = "404";
}

public function getcheck($tyv = array('1','2','3')){
	
    $us=array();
    if(isset($_SESSION) && isset($_SESSION['USER_SCRIPT'])){
        $us = unserialize($_SESSION['USER_SCRIPT']);
    }
    if(isset($us['Admin'][$tyv[0].$_GET[$tyv[1]]][$tyv[2]])){
        return $us['Admin'][$tyv[0].$_GET[$tyv[1]]][$tyv[2]];
    }else {
        return false;
    }
}
public function orderlist_ex($parm){
    if(isset($_GET['ex'])){
       return  $this->getcheck(array('orderlist_ex','ex',$parm));
    }else if(isset($_GET['os'])){
       return  $this->getcheck(array('orderlist_os','os',$parm));
    }
}
public function furl(){
    if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'){  
        $urllink = "https://".$_SERVER['HTTP_HOST'];
    }else{  
        $urllink = "http://".$_SERVER['HTTP_HOST']; 
    }// Append the host(domain name, ip) to the URL.   
    return $urllink."/";
}



public function app_request($loginType = 0,$pageType = 0){
  /*=================================================
     Request post check
  ================================================*/
  if ( $_SERVER["REQUEST_METHOD"] == "POST" ){
        $class_textcolor = "text-primary";
        if (isset($_POST['doo'])){
			//----------------------------------NewAccount post-------------------------------------
            if ($_POST["doo"]=="NewAccount"){
				
            } //end check New Account
			//------------------------------------verifyemail post---------------------------------------
            if($_POST["doo"]=="verifyemail"){
            // start Verify Email
				           $iscode = 0;
			               $result = checkEmailAndCode($_POST["email"]);
			                if($result->rowCount() > 0){
				              //The mail is already registered.
					                while($row=$result->fetch(PDO::FETCH_ASSOC)){
                                      $userid = $row["User_id"] ;
							                    $repass   = $row["REPASSCODE"] ;
					                         $expire = $row["EXPIRE"] ;		
					                     }
					                 if ($repass=="" || $expire >= 60){
					                 // cod is null or code expired
				                        while ($iscode == 0){
							                        $code = rand(10000,99999);
							                        $resultcode = CheckDuplicateCode($code);
							                        if($resultcode->rowCount() == 0){      
								                        $iscode = $code;                  
							                           }
				                            }
							                $resultsetcode = setRepassCode($iscode,$userid);
						                   if (!sendEmail($_POST['email'], $iscode)) {
										$class_textcolor = 'text-danger';
										$strerror = 'تعذر إرسال رمز التحقق حاليًا. يرجى مراجعة إعدادات البريد أو المحاولة لاحقًا.';
									}
					                    }
						              $Holderandvalue = 'value="' . $_POST['email'] . '" ';
						              $readonly= "readonly" ;
						              $_POST["doo"]="approvalcode";
						              $page_type = $_POST["doo"];
				              } else {
				              //The mail is not registered.
					                $class_textcolor = "text-danger";
					                $class_invaled = "is-invalid";
					                $strerror = "الحساب غير مسجل لديناً" ;
					                $Holderandvalue= 'value= "' . $_POST['email'] . '"';
					                $page_type=$_POST["doo"];
				              }	
			     } // end Verify Email
			//-----------------------------------approvalcode post------------------------------------
            if ( $_POST["doo"]=="approvalcode" && isset($_POST["email"]) && isset($_POST["code"])){
				//Approval Code
                $result = checkEmailAndCode($_POST["email"]);
                //get data from USERS.
			    if($result->rowCount() > 0){
				    while($row=$result->fetch(PDO::FETCH_ASSOC)){
				        $userid = $row["User_id"] ;
				        $repass   = $row["REPASSCODE"] ;
				        $expire = $row["EXPIRE"] ;		
				    }
					if ( $expire <= 60){
						if ($_POST['code'] == $repass) {
						    $Holderandvalue = 'value="' . $_POST['email'] . '" ';
						    $readonly= "readonly" ;
						    $strerror="من فضلك ادخل كلمة المرور الجديدة";
						    $page_type = "reset_password";
						} else {
						    $class_textcolor = "text-danger";
						    $class_invaled = "is-invalid";
						    $strerror="الكود الذى ادخلته غير صحيح من فضلك اعد المحاولة";
						    $Holderandvalue=  'value ="' . $_POST['email'] . '"';
						    $readonly= "readonly" ;
						    $page_type= $_POST["doo"];
						}
					} else {
					    // expire code
					    $class_textcolor = "text-danger";
					    $class_invaled = "is-invalid";
					    $strerror = "هذا الكود منتهى الصلاحيه و لم يعد صالح للإستخدام" ;
					    $Holderandvalue=  'value ="' . $_POST['email'] . '"';
					    $readonly= "readonly" ;
					    $page_type=$_POST["doo"];
					}
				} else {
				    $class_textcolor = "text-danger";
				    $class_invaled = "is-invalid";
				    $readonly= "readonly" ;
					$strerror = "هذا الحساب غير مسجل لديناً نعتذر على وصولك الى هنا" ;
				    $Holderandvalue=  'value ="' . $_POST['email'] . '"';
					$readonly= "readonly" ;
			        $page_type=$_POST["doo"];
                }
			} //end Approval Code
			//-----------------------------------Reset Password post----------------------------------
            if ($_POST["doo"]=="reset_password") {
             //reset_password
			            if ($_POST["pass"] == $_POST["repass"]) {
				              if (strlen($_POST["pass"]) >= 6 ) {
					                $result = updateloginpass($_POST["pass"],$_POST["email"]);
					                if($result->rowCount() > 0){
						                    $strerror="تم تعغير كلمة المرور بنجاح يمكنك الان تسجيل الدخول";
						                    $Holderandvalue=  'value ="' . $_POST['email'] . '"';
						                    $passvalue=  'value ="' . $_POST['email'] . '"';
						                    $page_type="login";
					                    }	
			                 	} else {
					                $class_textcolor = "text-danger";
                            $class_pass = "is-invalid";
					                $class_repass = "is-invalid";
					                $strerror = "من فضل ادخل كلمة مرور لا تقل عن ٦ احرف او ارقام" ;
					                $readonly = "readonly" ;
					                $Holderandvalue=  'value ="' . $_POST['email'] . '"';
					                $page_type=$_POST["doo"];	
				               }
						
			            } else {
				            $class_textcolor = "text-danger";
				            $class_repass = "is-invalid";
				            $strerror = "كلمة المرور غير مطابقه" ;
				            $readonly = "readonly" ;
				            $Holderandvalue=  'value ="' . $_POST['email'] . '"';
				            $page_type=$_POST["doo"];			
			            }
            }//end reset_password
        }  //end check doo
        
    }  //end check request method post*/
 /*=====================================================
   Request Get check
  ====================================================*/
  if ($_SERVER["REQUEST_METHOD"] == "GET" ) {
  	
        if(isset($_SESSION)){
            
    		//========== $_GET["logout"]=============
    		if (isset($_GET["logout"])){
    			session_unset ();
                session_destroy();
                session_write_close();
                $this->checkSession(true);
                exit;
             } //end logout
    		//=========== $_GET["Go"] ==============
    		if($loginType == 1){
    			
                if ($pageType == 1 && isset($_SESSION['USER_SCRIPT']) && $_SESSION["ADMIN_TYPE"] == 1 ){
	    			
	    			$usc = unserialize($_SESSION['USER_SCRIPT']);
	    			
	    			if (isset($_GET["Go"]) && $_GET["Go"] === "DatabaseMigrations" && (int)($_SESSION["ADMIN_TYPE"] ?? 0) === 1){
    $this->Pagelink = "DatabaseMigrations";
    $this->title = "إدارة تحديثات قاعدة البيانات";
    array_push($this->js_footer,"DatabaseMigrations.js");
} else if(isset($_GET["Go"]) && isset($usc['Admin'])){
	    		    	if ($usc['Admin']['Countries']['View'] == "true" && $_GET["Go"] === "Countries"){
	        				    $this->Pagelink = "Countries";
	            				$this->title= "إعدادات الدول";		
	            				array_push($this->js_footer,"Countries.js");
	            		} else if ($usc['Admin']['Gov']['View'] == "true" && $_GET["Go"] === "Gov"){
	            				$this->Pagelink = "Countries";
	            				$this->title= "إعدادات المحافظات";
	            				array_push($this->js_footer,"Countries.js");
	            		} else if ($usc['Admin']['City']['View'] == "true" && $_GET["Go"] === "City"){
	            				$this->Pagelink = "Countries";
	            				$this->title= "اعدادات المدن";
	            				array_push($this->js_footer,"Countries.js");
	            		} else if ($usc['Admin']['Local-Shipping']['View'] == "true" && $_GET["Go"] === "LocalShipping"){
	            				$this->Pagelink = "Countries";
	            				$this->title= "إعدادات الشحن المحلى";
	            				array_push($this->js_footer,"Countries.js");
	            		} else if ($usc['Admin']['International-Shipping']['View'] == "true" && $_GET["Go"] === "International-Shipping"){
	            				$this->Pagelink = "International-Shipping";
	            				$this->title= "إعدادات الشحن الدولى";
	            			//.........................................................
	            		} else if ($usc['Admin']['Enterprise']['View'] == "true" && $_GET["Go"] === "Enterprise"){
	            				$this->Pagelink = "Enterprise";
	            				$this->tit= "بيانات المؤسسة";
	            		} else if ($usc['Admin']['Administrative-Structure']['View'] == "true" && $_GET["Go"] === "Administrative-Structure"){
	            				$this->Pagelink = "Administrative-Structure";
	            				$this->title= "الهيكل الإدارى";
	            				array_push($this->js_footer,"Administrative-Structure.js");
	            		} else if ($usc['Admin']['Branches']['View'] == "true" && $_GET["Go"] === "Branches"){
	            				$this->Pagelink = "Branches";
	            				$this->title= "الفروع";
	            		} else if ($usc['Admin']['Warehouse']['View'] == "true" && $_GET["Go"] === "Warehouse"){
	            				$this->Pagelink = "Warehouse";
	            				$this->title= "المخازن";
	            		} else if ($usc['Admin']['Users']['View'] == "true" && $_GET["Go"] === "Users"){
	            				$this->Pagelink = "Users";
	            				$this->title= "المستخدمين";
	            				array_push($this->js_footer,"users.js");
	            		} else if ($usc['Admin']['Clients']['View'] == "true" && $_GET["Go"] === "Clients"){
	            				$this->Pagelink = "Clients";
	            				$this->title= "العملاء";
	            				array_push($this->js_footer,"Clients.js");
	            		} else if ($usc['Admin']['Suppliers']['View'] == "true" && $_GET["Go"] === "Suppliers"){
	            				$this->Pagelink = "Suppliers";
	            				$this->title= "الموردين";
	            				array_push($this->js_footer,"Suppliers.js");
	            		} else if ($usc['Admin']['Suppliers']['View'] == "true" && isset($usc['Admin']['PublicReports']['View']) && $usc['Admin']['PublicReports']['View'] == "true" && $_GET["Go"] === "PublicReports"){
	            				$this->Pagelink = "Reports";
	            				$this->title= "تقرير عامه";
	            			//.........................................................
	            		} else if ($usc['Admin']['Product-Categories']['View'] == "true" && $_GET["Go"] === "Product-Categories"){
	            				$this->Pagelink = "Categoris";
	            				$this->title="اقسام المنتجات";
	            				array_push($this->js_footer,"Categoris.js");
	            		} else if ($usc['Admin']['ProductOption']['View'] == "true" && $_GET["Go"] === "ProductOption"){
	            				$this->Pagelink = "ProductOption";
	            				$this->title="صفات المنتجات";
	            				array_push($this->js_footer,"productOption.js");
	            		} else if ($usc['Admin']['Products']['View'] == "true" && $_GET["Go"] === "Products"){
	            				$this->Pagelink = "AddProduct";
	            				$this->title="المنتجات";
	            				array_push($this->css,"summernote-bs4.css");
	            				array_push($this->js_footer,"products.js","summernote-bs4.min.js");
	            			//.........................................................
	            		
	            		} else if ($this->PageType == 1 && 
	            				isset($usc['Admin']['NewOrder']['Create_Order']) && 
	            				$usc['Admin']['NewOrder']['Create_Order'] == "true" && $_GET["Go"] === "NewOrder"){
	            				$this->Pagelink = "Neworder";
	            				$this->title= "أمر جديد";
	            			   	array_push($this->js_footer,"selectProduct.js","sreach_client.js");
	            			//.........................................................
	            			
	            		} else if (
						    isset($_GET["ex"], $_GET["Go"]) &&
						    $_GET["Go"] === "Orderlist" &&
						    $this->orderlist_ex('View_order_list') === 'true'
						) {
							
						    $titles = [
						        0  => "طلبات العملاء",
						        1  => "تأكيد طلبات العملاء",
						        2  => "طلبات مؤجلة",
						        3  => "طلبات غير متوفرة",
						        4  => "طلبات لم يتم التأكيد مع العميل",
						        5  => "أوامر البيع الملغاه",
						        6  => "تأكيد الشحن والتوصيل",
						        7  => "قيد التجهيز",
						        8  => "جاهز للشحن",
						        9  => "طلبات جارى الشحن",
						        10 => "طلبات تم شحنها",
						        11 => "طلبات بيع مباشر",
						        12 => "تتبع تأكيد الشحن",
						    ];
						
						    $ex = (int) $_GET["ex"];
						
						    $this->title = $titles[$ex] ?? "طلبات العملاء";
						
						    $this->Pagelink = "viewOrders";
						
						    $this->js_footer = array_merge($this->js_footer, [
						        "filterOrderList.js",
						        "viewOrders.js",
						        "viewOrderData.js",
						        "DatatablesFromObject.js"
						    ]);
						} else if (isset($usc['Admin']['SupportAfterSales']['View_order_list']) == 'true' && $_GET["Go"] === "SupportAfterSales" ){
	            				$this->title= "اوامر تم شحنها";
	            				$this->Pagelink = "viewOrders";
	            				array_push($this->js_footer,"filterOrderList.js","viewOrders.js","viewOrderData.js","DatatablesFromObject.js");
	            		} else if (isset($usc['Admin']['SupportOrders']['View_order_list']) == 'true' && $_GET["Go"] === "SupportOrders" ){
	            				$this->title= "طلبات المنسقين والعملاء";
	            				$this->Pagelink = "viewOrders";
	            				array_push($this->js_footer,"filterOrderList.js","viewOrders.js","viewOrderData.js","DatatablesFromObject.js");
	            		} else if (isset($usc['Admin']['OrdersCustody']['View_order_list']) && 
	            				$usc['Admin']['OrdersCustody']['View_order_list'] == "true" && $_GET["Go"] === "SalesMovementReport"){
	            				$this->Pagelink = "WarehouseShortageReport";
	            				array_push($this->js_footer,"reports.js");
	            		} else if (isset($usc['Admin']['OrdersCustody']['View_order_list']) && 
	            				$usc['Admin']['OrdersCustody']['View_order_list'] == "true" && $_GET["Go"] === "OrdersCustody"){
	            				$this->title 	= "عهدة المندوبين";
	            				$this->Pagelink = "viewOrders";
	            				array_push($this->js_footer,'filterOrderList.js',"viewOrders.js","viewOrderData.js","DatatablesFromObject.js");
	            		
						} else if (isset($usc['Admin']['StockReceipt']['view']) && 
	            				$usc['Admin']['StockReceipt']['view'] == "true" && $_GET["Go"] === "AddStock"){
	            				$this->title 	= "إذن استلام مخزنى";
	            				$this->Pagelink = "AddStockPermission";
	            				array_push($this->js_footer,"viewOrderData.js","AddStockPermission.js");
	            		} else if (isset($usc['Admin']['StockIssue']['view']) && 
	            				$usc['Admin']['StockIssue']['view'] == "true" && $_GET["Go"] === "StockIssue"){
	            				$this->title 	= "إذن صرف مخزنى";
	            				$this->Pagelink = "inventoryIssue";
	            				array_push($this->js_footer,"viewOrderData.js","inventoryIssue.js");
	            		
	            						
	            		} else if (isset($usc['Admin']['StockTransfer']['view']) && 
	            				$usc['Admin']['StockTransfer']['view'] == "true" && $_GET["Go"] === "StockTransfer"){
	            				$this->title 	= "إذن تحويل مخزنى";
	            				$this->Pagelink = "StockTransfer";
	            				array_push($this->js_footer,"StockTransfer.js");
	            		} else if (isset($usc['Admin']['StockAudit']['view']) && 
	            				$usc['Admin']['StockAudit']['view'] == "true" && $_GET["Go"] === "StockAudit"){
	            				$this->title 	= "جرد ومطابقة المخزون";
	            				$this->Pagelink = "InventoryReconciliation";
	            				array_push($this->js_footer,"InventoryReconciliation.js");
	            						
	            		} else if (isset($usc['Admin']['CriticalItemsReport']['view']) && 
	            				$usc['Admin']['CriticalItemsReport']['view'] == "true" && $_GET["Go"] === "stockShortageReport"){
	            				$this->title 	= "تقرير الاصناف الحرجه";
	            				$this->Pagelink = "WarehouseShortageReport";
	            				array_push($this->js_footer,"reports.js");
	            			
	            		} else if (isset($usc['Admin']['DelegatesShipmentReport']['view']) && 
	            				$usc['Admin']['DelegatesShipmentReport']['view'] == "true" && $_GET["Go"] === "DelegatesShipmentReport"){
	            				$this->title 	= "تقرير حركة عهدة المندوبين";
	            				$this->Pagelink = "DelegatesReport";
	            				//array_push($this->js_footer,"reports.js");
	            		} else if (isset($usc['Admin']['DelegatesInventoryReport']['view']) && 
	            				$usc['Admin']['DelegatesInventoryReport']['view'] == "true" && $_GET["Go"] === "DelegatesInventoryReport"){
	            				$this->title 	= "يومية حركة المخزون";
	            				$this->Pagelink = "InventoryMovement-report";
	            				//array_push($this->js_footer,"reports.js");
	            		} else if (isset($usc['Admin']['suppliersQtyReport']['view']) && 
	            				$usc['Admin']['suppliersQtyReport']['view'] == "true" && $_GET["Go"] === "SuppliersQtyReport"){
	            				$this->title 	= "تقرير استلامات الموردين";
	            				$this->Pagelink = "PurchasesReports";
	            				//array_push($this->js_footer,"reports.js");
	            				
	            			//.........................................................
	            		
	            		} else if (isset($_GET["Go"]) && $_GET["Go"] === "AddPurchaseOrder"){
	            				$this->title 	= "طلب توريد";
	            				$this->Pagelink = "AddPurchaseOrder";
	            				array_push($this->js_footer,"AddPurchaseOrder.js");
	            		//.........................................................
	     
	            		} else if (isset($usc['Admin']['PaymentsReceipt']['view']) && $usc['Admin']['PaymentsReceipt']['view'] && $_GET["Go"] === "ReceivePayments" ){
	            				$this->title 	= "استلام المدفوعات";
	            				$this->Pagelink = "ReceivePayments";
	            				array_push($this->js_footer,"ReceivePayments.js");
	            		
	            		} else if (isset($usc['Admin']['PaymentDisbursement']['view']) && $usc['Admin']['PaymentDisbursement']['view'] && $_GET["Go"] === "PaymentDisbursement" ){
	            				$this->title 	= "اذن صرف نقدية";
	            				$this->Pagelink = "PaymentDisbursement";
	            				array_push($this->js_footer,"PaymentDisbursement.js");
	            				
	            		} else if (isset($usc['Admin']['CashReceiptVouchers']['view']) && $usc['Admin']['CashReceiptVouchers']['view'] && $_GET["Go"] === "CashReceiptVouchers" ){
	            				$this->title 	= "اذن استلام نقدية";
	            				$this->Pagelink = "CashReceiptVouchers";
	            				array_push($this->js_footer,"ReceivePayments.js");
	            				array_push($this->js_footer,"CashReceiptVouchers.js");
	            				
	                    //.........................................................
	            		} else if (isset($usc['Admin']['AccountStatementSupplier']['view']) && 
	            				$usc['Admin']['AccountStatementSupplier']['view'] == "true" && $_GET["Go"] === "AccountStatement" && $_GET['ptr'] == 'SUPPLIER'){
	            				$this->title 	= "كشف حساب الموردين";
	            				$this->Pagelink = "AccountStatementReport";
	            				//array_push($this->js_footer,"reports.js");
	            				
	            		} else if (isset($usc['Admin']['AccountStatementClient']['view']) && 
	            				$usc['Admin']['AccountStatementClient']['view'] == "true" && $_GET["Go"] === "AccountStatement" && $_GET['ptr'] == 'CLIENT'){
	            				$this->title 	= "كشف حساب العملاء";
	            				$this->Pagelink = "AccountStatementReport";
	            				//array_push($this->js_footer,"reports.js");
	            				
	            		} else if (isset($usc['Admin']['AccountStatementMarketer']['view']) && 
	            				$usc['Admin']['AccountStatementMarketer']['view'] == "true" && $_GET["Go"] === "AccountStatement" && $_GET['ptr'] == 'MARKETER'){
	            				$this->title 	= "كشف حساب المسوقين";
	            				$this->Pagelink = "AccountStatementReport";
	            				//array_push($this->js_footer,"reports.js");
	            				
	            		} else if (isset($usc['Admin']['AccountStatementDelivery']['view']) && 
	            				$usc['Admin']['AccountStatementDelivery']['view'] == "true" && $_GET["Go"] === "AccountStatement" && $_GET['ptr'] == 'DELIVERY'){
	            				$this->title 	= "كشف حساب المندوبين";
	            				$this->Pagelink = "AccountStatementReport";
	            				//array_push($this->js_footer,"reports.js");
						//.........................................................
						
	            		} else if ($_GET["Go"] === "ShippingSettings" && (int)($_SESSION["ADMIN_TYPE"] ?? 0) === 1){
	            				$this->Pagelink = "ShippingSettings";
	            				$this->title = "إعدادات الشحن والتوصيل";
	            				array_push($this->js_footer,"ShippingSettings.js");
	            		} else if ($_GET["Go"] === "EditOrder"  && $_GET["ty"]==1){
	            				$this->Pagelink = "Neworder";
	            				$this->title= "تحرير امر البيع";
	            			    array_push($this->js_footer,"selectProduct.js","sreach_client.js");
	                    } else {
	                    		$this->Pagelink = "404";
	                    		$this->title = "الصفحة غير موجودة";
	                    		http_response_code(404);
	            		    }
	    			} else {
	    				$this->Pagelink = "cp_admin";
	            		$this->title="لوحة التحكم";
	            		array_push($this->js_footer,
	            					"jquery.mapael.min.js",
	            					"raphael.min.js",
	            					"world_countries.min.js",
	            					"Chart.min.js",
	            					"dashboard2.js");
	    			}
	    		} else if($pageType == 2 && isset($_SESSION["AFFILIATE_TYPE"]) && $_SESSION["AFFILIATE_TYPE"] == 1){ 
	            		    if(isset($_GET["Go"]) ){
	            		    	switch ($_GET["Go"]){
	            		    		case 'NewOrder' :
	            		    			$this->Pagelink = "Neworder";
	            						$this->title= "أمر جديد";
	            			    		array_push($this->js_footer,"selectProduct.js","sreach_client.js");
	            		    			break;
	            		    		case 'EditOrder':
	            		    			$this->Pagelink = "Neworder";
	            						$this->title= "تحرير امر البيع";
	            						array_push($this->js_footer,"selectProduct.js","sreach_client.js");
	            						break;
	            					case 'ListOrdersMarketer': 
			    						$this->Pagelink = "viewOrders";
	            						$this->title= "مبيعاتى";
	            						array_push($this->js_footer,"filterOrderList.js","viewOrders.js","viewOrderData.js","DatatablesFromObject.js");
	            						break;
	            					case 'MyEarnings': 
			    						$this->Pagelink = "MarketerBalance";
	            						$this->title= "تفاصيل الرصيد";
	            						break;
	            					case 'mDailyReport': 
			    						$this->Pagelink = "marketerDailyReport";
	            						$this->title= "تفاصيل اوامر البيع";
	            						break;
			    					case 'Profile': 
			    						$this->Pagelink = "Profile";
	            						$this->title= "الملف الشخصى";
	            						break;
	            					default :
	            						$this->Pagelink = "cp_admin";
			            				$this->title="لوحة التحكم";
			            				array_push($this->js_header,"jquery.mapael.js");
			            				array_push($this->js_footer,
			            					"raphael.min.js",
			            					"world_countries.min.js",
			            					"Chart.min.js",
			            					"dashboard2.js");
	            		    	}
	            		    }else {
	            		    	$this->Pagelink = "cp_admin";
	            				$this->title="لوحة التحكم";
	            				array_push($this->js_header,"jquery.mapael.js");
	            				array_push($this->js_footer,
	            					"raphael.min.js",
	            					"world_countries.min.js",
	            					"Chart.min.js",
	            					"dashboard2.js");
	            		    }
	            		    
	            		    
	            		    
	            		  /*  if(isset($_GET["Go"]) && $_GET["Go"] ==="Profile"){
	            				$this->Pagelink = "Profile";
	            				$this->title= "الملف الشخصى";
	            			} else if (isset($_GET["Go"]) && $_GET["Go"] ==="NewOrder"){
	            				$this->Pagelink = "Neworder";
	            				$this->title= "أمر جديد";
	            			    array_push($this->js_footer,"sreach_client.js");
	            			} else if (isset($_GET["Go"]) && $_GET["Go"] ==="Orderlist" && isset($_GET["ex"]) && $_GET["ex"]==0){
	            				$this->Pagelink = "viewOrders";
	            				$this->title= "مبيعاتى";
	            			    array_push($this->js_footer,"viewOrders.js");
	            			} else if (isset($_GET["Go"]) && $_GET["Go"] ==="EditOrder"){
	            				$this->Pagelink = "Neworder";
	            				$this->title= "تحرير امر البيع";
	            			    array_push($this->js_footer,"sreach_client.js");
	            			} else if (!$_GET){
	            				
	            				$this->Pagelink = "cp_admin";
	            				$this->title="لوحة التحكم";
	            				array_push($this->js_header,"jquery.mapael.js");
	            				array_push($this->js_footer,
	            					
	            					"raphael.min.js",
	            					"world_countries.min.js",
	            					"Chart.min.js",
	            					"dashboard2.js");
	                    	}*/
    		    } else if($pageType == 3 && isset($_SESSION["DELIVERY_TYPE"]) && $_SESSION["DELIVERY_TYPE"] == 1){
        			if(isset($_GET["Go"])){
        				switch ($_GET["Go"]) {
            				case "Profile":
            					$this->Pagelink = "Profile";
        						$this->title= "الملف الشخصى";
	        					break;
	        				case "ShippingConfirmation":
            					$this->Pagelink = "viewOrders";
            					$this->title    = "تأكيد الشحن والتوصيل";
            					array_push($this->js_footer,"filterOrderList.js","viewOrders.js","viewOrderData.js","DatatablesFromObject.js");
	        					break;
	        				case "ListOrdersDelivery":
            					$this->Pagelink = "viewOrders";
            					$this->title    = "تسليم الطلبات للعميل";
            					array_push($this->js_footer,"filterOrderList.js","viewOrders.js","viewOrderData.js","DatatablesFromObject.js");
	        					break;
	        				case "ListOrdersDelivered":
            					$this->Pagelink = "viewOrders";
            					$this->title= "سجل حركات التسليم";
            			    	array_push($this->js_footer,"filterOrderList.js","viewOrders.js","viewOrderData.js","DatatablesFromObject.js");
	        					break;
	        				case 'EditOrder':
	            		    	$this->Pagelink = "Neworder";
	            				$this->title= "تحرير امر البيع";
	            				array_push($this->js_footer,"selectProduct.js","sreach_client.js");
	            				break;
            			} 
        			} else if(!$_GET) {
        				$this->Pagelink = "cp_admin";
	            		$this->title="لوحة التحكم";
	            		array_push($this->js_header,"raphael.min.js","jquery.mapael.js");
	            		array_push($this->js_footer,
	            			"jquery.mapael.js",
	            			"raphael.min.js",
	            			"world_countries.min.js",
	            			"Chart.min.js",
	            			"dashboard2.js"
	            		);
        			}
        				
        				
        				
        				
        				
        				
        				
        				
        		/*		if (isset($_GET["Go"]) && $_GET["Go"] ==="Profile"){
        					$this->Pagelink = "Profile";
        					$this->title= "الملف الشخصى";
        					
        				} else if (isset($_GET["Go"]) && $_GET["Go"] === "Orderlist" && isset($_GET["ex"],$_GET["st"]) && $_GET["st"] == 2 && $_GET["ex"]==9){
            				$this->Pagelink = "viewOrders";
            				$this->title    = "تسليم الطلبات للعميل";
            			    array_push($this->js_footer,"viewOrders.js");
            			    
        				} else if (isset($_GET["Go"]) && $_GET["Go"] === "Orderlist" && isset($_GET["ex"],$_GET["st"]) && $_GET["st"] == 0 && $_GET["ex"]==9){
            				$this->Pagelink = "viewOrders";
            				$this->title= "سجل حركات التسليم";
            			    array_push($this->js_footer,"viewOrders.js");
            			    
						} else if (isset($_GET["Go"],$_GET['st']) && $_GET['st']=-1 && $_GET["Go"] ==="trt"){
            				$this->Pagelink = "viewOrders1";
            				$this->title= " ؟؟؟؟؟؟؟؟؟؟؟ ";
            			    array_push($this->js_footer,"viewOrders.js");
            			    
            			    
        				} else if (!$_GET){
            				$this->Pagelink = "cp_admin";
            				$this->title="لوحة التحكم";
            				array_push($this->js_header,"raphael.min.js","jquery.mapael.js");
            				array_push($this->js_footer,
            					"jquery.mapael.js",
            					"raphael.min.js",
            					"world_countries.min.js",
            					"Chart.min.js",
            					"dashboard2.js");
                    	}*/
        		    	
    		    } else if($pageType == 4){
    		        if(isset($_SESSION["INTERMEDIARY_TYPE"]) && $_SESSION["INTERMEDIARY_TYPE"] == 1){
        				if (isset($_GET["Go"]) && $_GET["Go"] ==="Profile"){
        					$this->Pagelink = "Profile";
        					$this->title= "الملف الشخصى";
        				} else if (!isset($_GET["Go"])){
            				$this->Pagelink = "cp_admin";
            				$this->title="لوحة التحكم";
            				array_push($this->js_footer,
            					"jquery.mapael.min.js",
            					"raphael.min.js",
            					"world_countries.min.js",
            					"Chart.min.js",
            					"dashboard2.js");
                    	}
    		        }else{
    		        	$this->Pagelink = "404";
            			$this->title="عنوان غير متاح";
    		        }
    			} else if($pageType == 5 && isset($_SESSION["MERCHANT_TYPE"] ) && $_SESSION["MERCHANT_TYPE"] == 1){
    				
        				if (isset($_GET["Go"]) && $_GET["Go"] ==="Profile"){
        					$this->Pagelink = "Profile";
        					$this->title    = "الملف الشخصى";
        				} else if (!isset($_GET["Go"])){
            				$this->Pagelink = "cp_admin";
            				$this->title="لوحة التحكم";
            				array_push($this->js_footer,
            					"jquery.mapael.min.js",
            					"raphael.min.js",
            					"world_countries.min.js",
            					"Chart.min.js",
            					"dashboard2.js");
                    	}
    				
    			} else if (isset($_SESSION['USER_ID'])) {
				    // المستخدم مسجل الدخول لكنه لا يطابق نوع الحساب المطلوب لهذا المسار.
				    // لا تعِد توجيهه إلى الصفحة الرئيسية؛ اعرض رفض الوصول بدل Redirect Loop.
				    $this->Pagelink = "403";
				    $this->title = "غير مصرح بالوصول";
				    http_response_code(403);
				}
    		} //end if loginType	
        }
    } //end if request GET
} // end function
/*=======================================================
      user permission function
 ======================================================*/
public function header_CSS(){
    $dirRoot=$this->furl();
    echo '
    <link rel="stylesheet" href="/libraries/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/libraries/dataTables/dataTables.min.css">
    <link rel="stylesheet" href="/libraries/overlayScrollbars/css/OverlayScrollbars.min.css">
    <link rel="stylesheet" href="/libraries/select2/css/select2.min.css">
    <link rel="stylesheet" href="/libraries/sweetalert2/sweetalert2.min.css">
	<link rel="stylesheet" href="/libraries/bootstrap-treeview/dist/css/bstreeview.min.css">
	<link rel="stylesheet" href="/libraries/daterangepicker-master/daterangepicker.css">
	<link rel="stylesheet" href="/libraries/DropdownTree-master/dropdowntree.css">
	<link rel="stylesheet" href="/dist/css/adminlte.min.css">
	<link rel="stylesheet" href="/libraries/bootstrap/4.2.1/css/rtl/bootstrap-4.2.1-rtl.css">
	<link rel="stylesheet" href="/dist/css/custom.css?v='. filemtime('dist/css/custom.css').'">
	';
}

private function getData ($getpar){
    	$apiUrl =$this->furl().'Api.php';
	    $ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $apiUrl.$getpar);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		curl_close($ch);
		return json_encode($response);
}
public function header_JS(){
    $dirRoot=$this->furl();
    if(!$this->PageType){
    	$this->PageType = 0;
    }
    
   echo '
    <!-- JavaScript Variables -->
    <script>
        const pt = ' . $this->PageType . ';
    </script>
    ';
}
public function array_CSS(){
    $rd = $_SERVER["DOCUMENT_ROOT"];
    $dirRoot=$this->furl();
    $dr ="";
    foreach($this->css as $val){
       // $dr .= '<link rel="stylesheet" href="'.realpath($val).'?v='.filemtime(realpath($val)).'">';
        $dr .= '<link rel="stylesheet" href="/dist/css/'.$val.'?ver='.filemtime('dist/css/'.$val).'">';
    }
    echo $dr;
}
public function array_headr_js(){
    //$rd = $_SERVER["DOCUMENT_ROOT"];
    //$dirRoot=$this->furl();
    $dr ="";
    foreach ($this->js_header as $val){
        $dr .= '<script src="/dist/js/'.$val.'?v='.filemtime('dist/js/'.$val).'"></script>';
    }
    echo $dr;
}
public function array_body_JS(){
    //$rd = $_SERVER["DOCUMENT_ROOT"];
//    $dirRoot=$perm->furl();  
    $debug = false;
    $dr ='
    <!------------ body_JS() ------------>

		<script src="../../dist/js/model/connection-monitor.js?ver='.filemtime($_SERVER["DOCUMENT_ROOT"]."/dist/js/model/connection-monitor.js").'"></script>
		
		<!-- Popper.js (مطلوب للقوائم المنسدلة في Bootstrap -->
		<script src="../../libraries/popper/umd/popper.min.js"></script> 
		
		<!-- select2 -->
		<script src="../../libraries/select2/js/select2.min.js?ver=' . filemtime($_SERVER["DOCUMENT_ROOT"]."/libraries/select2/js/select2.min.js") . '"></script>
		<script src="../../libraries/select2/js/i18n/ar.js?v=' . filemtime($_SERVER["DOCUMENT_ROOT"]."/libraries/select2/js/i18n/ar.js") . '"></script>
		
		<!-- Bootstrap -->
		<script src="../../libraries/bootstrap/4.2.1/js/bootstrap.bundle.min.js"></script>
		
		<!-- Date Range Picker -->
		<script src="../../libraries/daterangepicker-master/moment.min.js"></script>
		<script src="../../libraries/daterangepicker-master/daterangepicker.js"></script>
		
		
		<!-- Overlay Scrollbars -->
		<script src="../../libraries/overlayScrollbars/js/jquery.overlayScrollbars.min.js?ver=' . filemtime($_SERVER["DOCUMENT_ROOT"]."/dist/js/custom.js") . '"></script>
		
			
		<!-- Dropdown Tree -->
		<script src="../../libraries/DropdownTree-master/dropdowntree.js?ver=' . filemtime($_SERVER["DOCUMENT_ROOT"]."/libraries/DropdownTree-master/dropdowntree.js") . '"></script>
		
		<!-- Bootstrap Treeview -->
		<script src="../../libraries/bootstrap-treeview/dist/js/bstreeview.min.js?ver=' . filemtime($_SERVER["DOCUMENT_ROOT"]."/libraries/bootstrap-treeview/dist/js/bstreeview.min.js") . '"></script>
		
		<!-- HTML5 QR Code -->
		<script src="../../libraries/html5-qrcode/html5-qrcode.min.js?ver=' . filemtime($_SERVER["DOCUMENT_ROOT"]."/libraries/html5-qrcode/html5-qrcode.min.js") . '"></script>
		
		<!-- SweetAlert2 -->
		<script src="../../libraries/sweetalert2/sweetalert2.min.js?ver='.filemtime($_SERVER["DOCUMENT_ROOT"]."/libraries/sweetalert2/sweetalert2.min.js").'"></script>
		
		<!-- AdminLTE -->
		<script src="../../libraries/dist/js/adminlte.min.js?ver='.filemtime($_SERVER["DOCUMENT_ROOT"]."/libraries/dist/js/adminlte.min.js").'"></script>
		<script src="../../libraries/dist/js/demo.js?v='.filemtime($_SERVER["DOCUMENT_ROOT"]."/libraries/dist/js/demo.js").'"></script>
		
		<!-- DataTables -->
		<script src="../../libraries/datatables/datatables.min.js"></script>
		
		<!-- Fuse.js -->
		<script src="../../libraries/fuse/fuse.min.js"></script>
		
		<!-- Custom Scripts -->
		<script src="../../dist/js/custom.js?ver='.filemtime($_SERVER["DOCUMENT_ROOT"]."/dist/js/custom.js").'"></script>  

	';
    
    foreach ($this->js_footer as $val){
        $dr .= '
        	<script  src="/dist/js/'.$val.'?ver='.filemtime('dist/js/'.$val).'"></script>';
    }
    
  	$dr.="
	  	<script>
			if (ptype == 2 && Go !== 'NewOrder') {
				$('#btnCartAddNewOrder').show();
				$(document).on('click', '#btnCartAddNewOrder', function () {
					window.location.href = '?ty=2&Go=NewOrder';
				});
			} else {
				$('#btnCartAddNewOrder').hide();
			}
			if(!(Go === 'Orderlist' ||
				   Go === 'ListOrdersDelivery' ||
				   Go === 'OrdersCustody' ||
				   Go === 'ListOrdersMarketer')
			){
				$('#hid').toggle();
				$('#ovarlay').hide();
				$('#contentbody').removeClass('overlay-wrapper');
				$('#contentbody').addClass('wrapper');
			}
			console.log(pt)
		</script>
		";
		
    
	
    echo $dr;
}




}//end class

?>

