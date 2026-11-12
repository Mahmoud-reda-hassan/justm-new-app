<?php

/**
 * ============================================================
 * Auth Class
 * ============================================================
 */
class Auth {
	
	
    public static function login($db) {
		include_once ABSPATH.'/inc/Classes/Users_tbl.php';
        if ($_SERVER["REQUEST_METHOD"] !== "POST") return false;

        if (!isset($_POST['CONTROL'], $_POST['email'], $_POST['pass'], $_COOKIE['fpId'])) return false;

        if ($_POST['CONTROL'] !== 'login') return false;

        $Users_tbl = new Users_tbl($db);
        $result = $Users_tbl->Userlogin();

        if ($result->rowCount() <= 0) {
            self::ResponseData(0);
            exit();
        }

        $user = $result->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            self::ResponseData(0);
            exit();
        }

        // 🔐 حماية من Session Fixation
        session_regenerate_id(true);

        // ✅ إنشاء السيشن
        injectionSession($user, $_COOKIE['fpId']);

        $remember = $_POST['remember'] ?? false;

        setcookie(
            'rem',
            $remember,
            time() + (86400 * 365),
            "/",
            "",
            true,
            true
        );

        Security::saveUserCookie($user, $_COOKIE['fpId'], $remember);

        self::ResponseData(count($_SESSION));
        exit();
    }
	
    public static function check() {

        if (!isset($_COOKIE['fpId'], $_COOKIE['USER_AUTH'])) return false;
		
        $fp = $_COOKIE['fpId'];
        $remember = $_COOKIE['rem'] ?? false;
        $data = Security::getUserFromCookie($fp);

        if (!$data) return false;

        // 🔐 إعادة إنشاء السيشن لو مش موجود
		if (!check_session()) {
		    session_regenerate_id(true); // 🔥 مهم جدًا
		    injectionSession($data, $fp);
		}
		if (isset($_SESSION["FINGERPRINT"]) && $_SESSION["FINGERPRINT"] !== $fp) {
		    self::logout();
		    return false;
		}
        // ⏳ انتهاء السيشن (30 دقيقة)
        if (isset($_SESSION["LOGIN_TIME"]) && time() - $_SESSION["LOGIN_TIME"] > 36000) {
            self::logout();
            return false;
        }

        // 🔄 regenerate كل 5 دقايق
        if (!isset($_SESSION['LAST_REGEN'])) {
            $_SESSION['LAST_REGEN'] = time();
        }

        if (time() - $_SESSION['LAST_REGEN'] > 300) {
            session_regenerate_id(true);
            $_SESSION['LAST_REGEN'] = time();
        }
		
        // 🔁 renew cookie
        Security::saveUserCookie($_SESSION, $fp, $remember);

        return true;
    }

    public static function logout() {
        session_unset();
        session_destroy();

        setcookie('USER_AUTH', '', time() - 3600, '/');
        setcookie('rem', '', time() - 3600, '/');
    } 
    
    private static function ResponseData($data) {
	    $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
	    $encoding = false; 
	    
	    http_response_code(200);
	    header('Content-Type: application/json');
	    
	    if (strpos($acceptEncoding, 'br') !== false && function_exists('brotli_compress')) {
	        header('Content-Encoding: br');
	        echo brotli_compress(json_encode($data, JSON_UNESCAPED_UNICODE), 9, BROTLI_GENERIC);
	    } else if ($encoding) {
	        header('Content-Encoding: gzip');
	        echo gzencode(json_encode($data, JSON_UNESCAPED_UNICODE), 9);
	    } else {
	        echo json_encode($data, JSON_UNESCAPED_UNICODE);
	    }
	}
}

/**
 * ============================================================
 * Security Class
 * ============================================================
 */
class Security {

    private static $method = 'AES-256-CBC';
    private static $key;
    private static $iv_length;

    public static function init() {
        self::$key = hash('sha256', AUTH_KEY, true);
        self::$iv_length = openssl_cipher_iv_length(self::$method);
    }

    public static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64url_decode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function encrypt($data, $fingerprint) {

        // 🔐 ربط بالبصمة
        $data['FINGERPRINT'] = hash('sha256', $fingerprint);

        $jsonData = json_encode($data);
        $compressedData = gzdeflate($jsonData, 9);

        $iv = random_bytes(self::$iv_length);

        $encrypted = openssl_encrypt(
            $compressedData,
            self::$method,
            self::$key,
            OPENSSL_RAW_DATA,
            $iv
        );

        // 🔐 حماية من التلاعب
        $hmac = hash_hmac('sha256', $encrypted, self::$key, true);

        return self::base64url_encode($iv . $hmac . $encrypted);
    }

    public static function decrypt($encryptedData, $fingerprint) {

        $decoded = self::base64url_decode($encryptedData);

        $iv = substr($decoded, 0, self::$iv_length);
        $hmac = substr($decoded, self::$iv_length, 32);
        $encrypted = substr($decoded, self::$iv_length + 32);

        $calculated = hash_hmac('sha256', $encrypted, self::$key, true);

        if (!hash_equals($hmac, $calculated)) {
            return false;
        }

        $decrypted = openssl_decrypt(
            $encrypted,
            self::$method,
            self::$key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if (!$decrypted) return false;

        $jsonData = gzinflate($decrypted);
        $data = json_decode($jsonData, true);

        if (!$data) return false;

        // 🔐 تحقق fingerprint
        if (!isset($data['FINGERPRINT']) || $data['FINGERPRINT'] !== hash('sha256', $fingerprint)) {
            return false;
        }

        return $data;
    }

    public static function saveUserCookie($userData, $fingerprint, $remember = false) {

        $encryptedData = self::encrypt($userData, $fingerprint);

        $expire = $remember ? 604800 : 3600;

        setcookie(
            'USER_AUTH',
            $encryptedData,
            time() + $expire,
            "/",
            "",
            true,
            true
        );
    }

    public static function getUserFromCookie($fingerprint) {
        if (isset($_COOKIE['USER_AUTH'])) {
            return self::decrypt($_COOKIE['USER_AUTH'], $fingerprint);
        }
        return null;
    }
}

/**
 * ============================================================
 * Session Injection
 * ============================================================
 */
function injectionSession($data, $fingerprint){
	$_SESSION["FINGERPRINT"] = $fingerprint;  // 🔥 إضافة بصمة المتصفح لحماية الجلسة
    $_SESSION["LOGIN_TIME"] = time();
    $_SESSION["USER_ID"] = $data["USER_ID"];
    $_SESSION["FNAME"] = $data["FNAME"];
    $_SESSION["LNAME"] = $data["LNAME"];
    $_SESSION["GENDER"] = $data["GENDER"];
    $_SESSION["SUSPENDED"] = $data["SUSPENDED"];
    $_SESSION["USER_GRUOP_NAME"] = $data["USER_GRUOP_NAME"];
    $_SESSION["USER_IMG_LINK"] = empty($data["USER_IMG_LINK"]) ? 
                                 ($data["GENDER"] == "male" || $data["GENDER"] == "ذكر" ? "img/usericon-male.png" : 
                                 ($data["GENDER"] == "female" || $data["GENDER"] == "أنثى" ? "img/usericon-female.png" : "img/usericon.png")) 
                                 : $data["USER_IMG_LINK"];
    $_SESSION["COMPANY_LOGO"] = empty($data["COMPANY_LOGO"]) ? "img/logo.png" : $data["COMPANY_LOGO"];
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
}
function check_session(){
	if(isset($_SESSION["FINGERPRINT"],
	    $_SESSION["LOGIN_TIME"],
	    $_SESSION["USER_ID"],
	    $_SESSION["FNAME"],
	    $_SESSION["LNAME"],
	    $_SESSION["GENDER"],
	    $_SESSION["SUSPENDED"],
	    $_SESSION["USER_GRUOP_NAME"],
	    $_SESSION["USER_IMG_LINK"],
	    $_SESSION["COMPANY_LOGO"],
	    $_SESSION["COMPANY_NAME"],
	    $_SESSION["ADMIN_TYPE"],
	    $_SESSION["MERCHANT_TYPE"],
	    $_SESSION["AFFILIATE_TYPE"],
	    $_SESSION["INTERMEDIARY_TYPE"],
	    $_SESSION["DELIVERY_TYPE"],
	    $_SESSION["USER_SCRIPT"],
	    $_SESSION["COMPANY_ID"],
	    $_SESSION["BRANCH_ID"],
	    $_SESSION["BRANCH_NAME"],
	    $_SESSION["CITY_ID"],
	    $_SESSION["GOV_ID"],
	    $_SESSION["COUNTRY_ID"])
    ){
    	return true;
    } 
    return false;
}
Security::init();
?>