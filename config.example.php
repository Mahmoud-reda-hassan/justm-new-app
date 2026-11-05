<?php
/**
 * إعدادات  الأساسية
 *
 * عملية إنشاء الملف config.php يستخدم هذا الملف أثناء التنصيب. لا يجب عليك
 * استخدام الموقع، يمكنك نسخ هذا الملف إلى "config.php" وبعدها ملئ القيم المطلوبة.
 *
 * هذا الملف يحتوي على هذه الإعدادات:
 *
 * * إعدادات قاعدة البيانات
 * * مفاتيح الأمان
 * * بادئة جداول قاعدة البيانات
 * * المسار المطلق لمجلد التطبيق
 *
 * @package WordPress
 */
 
//ini_set('display_errors', 1);
//error_reporting(E_ALL);

// ** إعدادات قاعدة البيانات - يمكنك الحصول على هذه المعلومات من مستضيفك ** //

/** اسم قاعدة البيانات  */
define( 'DB_NAME', 'your_database_name' );

/** اسم مستخدم قاعدة البيانات */
define( 'DB_USER', 'your_database_user' );

/** كلمة مرور قاعدة البيانات */
define( 'DB_PASSWORD', 'CHANGE_THIS_DATABASE_PASSWORD' );

/** عنوان خادم قاعدة البيانات */
define( 'DB_HOST', 'localhost' );
/** منفذ خادم قاعدة البيانات */
define( 'DB_PORT', '3306' );
/** ترميز قاعدة البيانات */
define( 'DB_CHARSET', 'utf8mb4' );

/** نوع تجميع قاعدة البيانات. لا تغير هذا إن كنت غير متأكد */
define( 'DB_COLLATE', 'utf8_unicode_ci' );

define('DATATABLES_SERVER_SIDE_EX0', true); // تتبع طلبات العملاء
define('DATATABLES_SERVER_SIDE_EX1', true); // قيد المراجعه
define('DATATABLES_SERVER_SIDE_EX2', true); // مؤجل
define('DATATABLES_SERVER_SIDE_EX3', true); // غير متوفر
define('DATATABLES_SERVER_SIDE_EX4', true); // العميل لا يستجيب
define('DATATABLES_SERVER_SIDE_EX5', true); // الغاء أوامر البيع
define('DATATABLES_SERVER_SIDE_EX7', false); // طلبات قيد التجهيز
define('DATATABLES_SERVER_SIDE_EX8', false); // طلبات تم التجهيز
define('DATATABLES_SERVER_SIDE_EX9', true); // طلبات جارى الشحن
define('DATATABLES_SERVER_SIDE_EX10', false); // عهدة المندوبين
define('DATATABLES_SERVER_SIDE', true);  // السوقين وموظفين الشحن
define('DATATABLES_ROW_COUNT_IN_PAGE', 10);

/**#@+
 * مفاتيح الأمان.
 *
 * تغيير هذه العبارات إلى عبارات فريدة مختلفة!
 * استخدم الرابط التالي لتوليد المفاتيح {@link https://api.wordpress.org/secret-key/1.1/salt/}
 * يمكنك تغيير هذه في أي وقت لإلغاء جميع ملفات تعريف الارتباط الموجودة. سيؤدي هذا إلى إجبار جميع المستخدمين على تسجيل الدخول مرة أخرى.
 *
 * @since 2.6.0
 */
define( 'versionApp' , 		'2.1.0' );
define( 'AUTH_KEY',         'GENERATE_A_UNIQUE_RANDOM_SECRET' );
define( 'SECURE_AUTH_KEY',  'GENERATE_A_UNIQUE_RANDOM_SECRET' );
define( 'LOGGED_IN_KEY',    'GENERATE_A_UNIQUE_RANDOM_SECRET' );
define( 'NONCE_KEY',        'GENERATE_A_UNIQUE_RANDOM_SECRET' );
define( 'AUTH_SALT',        'GENERATE_A_UNIQUE_RANDOM_SECRET' );
define( 'SECURE_AUTH_SALT', 'GENERATE_A_UNIQUE_RANDOM_SECRET' );
define( 'LOGGED_IN_SALT',   'GENERATE_A_UNIQUE_RANDOM_SECRET' );
define( 'NONCE_SALT',       'GENERATE_A_UNIQUE_RANDOM_SECRET' );

/**#@-*/

/**
 * بادئة الجداول في قاعدة البيانات.
 *
 * تستطيع تركيب أكثر من موقع على نفس قاعدة البيانات إذا أعطيت لكل موقع بادئة جداول مختلفة
 * يرجى استخدام حروف، أرقام وخطوط سفلية فقط!
 */
$table_prefix = '';

/**
 * للمطورين: نظام تشخيص الأخطاء
 *
 * قم بتغييرالقيمة، إن أردت تمكين عرض الملاحظات والأخطاء أثناء التطوير.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* هذا هو المطلوب، توقف عن التعديل! نتمنى لك التوفيق. */

/** المسار المطلق لمجلد التطبيق. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . '/');
}

//require_once ABSPATH . "/inc/security.php";

define('TIMEZONE', 'Africa/Cairo');

define('DEBUG', true);
/** إعداد متغيرات الووردبريس وتضمين الملفات. */
//require_once ABSPATH . 'wp-settings.php';

// فحص إن كانت الجلسة مفقودة ولكن الكوكي موجود
//if(!isset($_SESSION)){
//	session_save_path(__DIR__ . '/inc/sessions');
//	session_start();
//}




