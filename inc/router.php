<?php
//require_once '/../../config.php';
//require_once(ABSPATH."/Languages/AR/SidebarMenu.lang.php");
//session_start();

class Router {
    private $routes = [];
    private $controller = null;
    private $pageLink = "404";
    private $title = "";
    public $js_footer = [];
    public $css = [];

    public function __construct($loginType = 0,$pageType = 0) {
        
        if (
        	$loginType == 1 && 
        	!isset($_SESSION["USER_ID"], 
        	$_SESSION["FNAME"], 
        	$_SESSION["LNAME"], 
        	$_SESSION["GENDER"], 
        	$_SESSION["SUSPENDED"], 
        	$_SESSION["USER_IMG_LINK"], 
        	$_SESSION["COMPANY_LOGO"], 
        	$_SESSION["COMPANY_NAME"], 
        	$_SESSION["BRANCH_NAME"], 
        	$_SESSION["BRANCH_ID"], 
        	$_SESSION["ADMIN_TYPE"], 
        	$_SESSION["MERCHANT_TYPE"], 
        	$_SESSION["AFFILIATE_TYPE"], 
        	$_SESSION["INTERMEDIARY_TYPE"],
        	$_SESSION["DELIVERY_TYPE"])) include ABSPATH. "/inc/template/login.php";
		
        // جلب الrouter المناسب لنوع الصفحه
        if($loginType === 0 && $pageType === 0) $this->routes = require ABSPATH . '/inc/routes/public_router.php';
        if($loginType === 1 && $pageType === 0) $this->routes = [
        	"dashboard" => [
        		"view" => "AdminZone", 
        		"title" => "منطقة المستخدمين المميزين",
        		"js" => []
        	]
        ];
        if($loginType === 1 && $pageType === 1) $this->routes = require ABSPATH . '/inc/routes/admin_router.php';
        if($loginType === 1 && $pageType === 2) $this->routes = require ABSPATH . '/inc/routes/affiliate_router.php';
        if($loginType === 1 && $pageType === 3) $this->routes = require ABSPATH . '/inc/routes/delivery_router.php';
        if($loginType === 1 && $pageType === 4) $this->routes = require ABSPATH . '/inc/routes/supplier_router.php';
        
        if(empty($this->routes)){
	        $this->pageLink = "404";
	    	$this->title = "صفحة غير موجودة";
        }
        
        
		
		
        
    	
    }

    public function loadPage() {
    	
        $go = $_GET['Go'] ?? 'Dashboard';
			
			
			
			
        if (isset($this->routes[$go]) && !empty($this->routes[$go]['permission']) && $this->routes[$go]['permission']) {
            $route = $this->routes[$go];

            $this->pageLink = $route['view'];
            $this->title = $route['title'];

			if (!empty($route['css'])) {
                $this->css = array_merge($this->css, $route['css']);
            }
            if (!empty($route['js'])) {
                $this->js_footer = array_merge($this->js_footer, $route['js']);
            }
            
			
            // يمكن استدعاء Controller حسب الحاجة
            $controllerFile = ABSPATH . '/../controllers/' . $userType . 'Controller.php';
            if (file_exists($controllerFile)) {
                require_once $controllerFile;
                $controllerClass = ucfirst($userType) . "Controller";
                if (class_exists($controllerClass)) {
                    $this->controller = new $controllerClass();
                    if (method_exists($this->controller, 'handle')) {
                        $this->controller->handle($go);
                    }
                }
            }

        } else {
            // صفحة 404 افتراضية
            $this->pageLink = "404";
            $this->title = "صفحة غير موجودة";
        }

        $this->render();
    }

    private function render() {
        // تحميل الـ View
        $viewFile = ABSPATH . '/inc/views/' . $this->pageLink . '.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            echo "<h1>404 - الصفحة غير موجودة</h1>";
        }
    }
}