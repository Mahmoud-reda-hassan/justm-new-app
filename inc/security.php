<?php
/**
 * ============================================================
 * كلاس الحماية (Security)
 * ============================================================
 *
 * هذا الكلاس مسؤول عن:
 *
 * - تشفير بيانات المستخدم قبل تخزينها داخل Cookie.
 * - ضغط البيانات لتقليل حجمها داخل الكوكي.
 * - تحويل البيانات المشفرة إلى صيغة Base64 آمنة للاستخدام في الـ URL والكوكي.
 * - فك التشفير واسترجاع البيانات الأصلية عند الحاجة.
 *
 * آلية العمل:
 *
 * عند الحفظ:
 * 1) تحويل البيانات إلى JSON.
 * 2) ضغط البيانات باستخدام Deflate.
 * 3) تشفير البيانات باستخدام AES بنمط CBC.
 * 4) إضافة IV (متجه التهيئة) إلى البيانات المشفرة.
 * 5) تحويل الناتج إلى Base64 بصيغة آمنة.
 * 6) تخزين القيمة داخل كوكي باسم USER_AUTH.
 *
 * عند الاسترجاع:
 * - فك ترميز Base64.
 * - استخراج IV.
 * - فك التشفير.
 * - فك الضغط.
 * - تحويل JSON مرة أخرى إلى مصفوفة.
 *
 * ملاحظات:
 * - مفتاح التشفير يتم توليده من الثابت AUTH_KEY.
 * - مدة صلاحية الكوكي ساعة واحدة.
 *
 * ============================================================
 */
//require_once __DIR__ . '/../config.php'; // تحميل الإعدادات العامة

class Security2 {

    private static $method = 'AES-256-CBC'; // خوارزمية التشفير
    private static $key; // المفتاح الأساسي
    private static $iv_length; // طول IV (الـ Initialization Vector)
	
	public static function base64url_encode($data) {
	    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}
	
	private static function base64url_decode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function init() {
        self::$key = hash('sha256', AUTH_KEY); // إنشاء مفتاح قوي من AUTH_KEY
        self::$iv_length = openssl_cipher_iv_length(self::$method);
    }

    public static function encrypt($data, $fingerprint) {
	    
	    //$data['FINGERPRINT'] = $fingerprint;
	    $jsonData = json_encode($data); // تحويل البيانات إلى JSON
	    $compressedData = gzdeflate($jsonData, 9); // ضغط البيانات باستخدام Deflate
	    $iv = random_bytes(self::$iv_length); // توليد IV عشوائي
	    $encrypted = openssl_encrypt($compressedData, 'AES-128-CBC', self::$key, 0, $iv);
		
	    return self::base64url_encode($iv . $encrypted); // تخزين IV مع البيانات المشفرة
	}

    public static function decrypt($encryptedData, $fingerprint) {
	    $decoded = self::base64url_decode($encryptedData);
	    $iv = substr($decoded, 0, self::$iv_length); // استخراج IV
	    $encrypted = substr($decoded, self::$iv_length); // استخراج البيانات المشفرة
	
	    $decrypted = openssl_decrypt($encrypted, 'AES-128-CBC', self::$key, 0, $iv);
	    $jsonData = gzinflate($decrypted); // فك الضغط باستخدام gzinflate
	    return json_decode($jsonData, true); // تحويل JSON إلى مصفوفة
	}

    public static function saveUserCookie($userData, $fingerprint, $remember = false) {
        $encryptedData = self::encrypt($userData, $fingerprint);
		
		// تعيين الكوكي
		$expire = (isset($_COOKIE['rem']) && $_COOKIE['rem']) ? 604800 : 3600;
		
		setcookie(
		    'USER_AUTH',
		    $encryptedData,
		    time() + $expire,
		    "/",
		    "",
		    true,  // Secure (HTTPS بس)
		    true   // HttpOnly (مايتقراش من JS)
		);
		
		
		
    }

    public static function getUserFromCookie($fingerprint) {
        if (isset($_COOKIE['USER_AUTH'])) {
            return self::decrypt($_COOKIE['USER_AUTH'], $fingerprint);
        }
        return null;
    }
}

// تهيئة الإعدادات
Security::init();
?>