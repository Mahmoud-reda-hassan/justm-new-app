<?php

/*class dbconn{
    public function getConnection($host,$port,$user,$password,$database,$db_charset){
	    try{
		      $conn = @new PDO(
		      	"mysql:host=" . $host . ";port=" . $port . ";dbname=" . $database , $user, $password , array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . $db_charset));
              // set the PDO error mode to exception
              $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
              $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES,true);
		    } catch(PDOException $e) {
                echo "An error occurred while connecting to the database. ".
					"The error reported by the server was: ".$e->getMessage();
                exit(0);
            }
        return $conn;
    }
    
             
          
}*/

class dbconn {
    public function getConnection($host, $port, $user, $password, $database, $db_charset) {
        try {
            $conn = new PDO(
                "mysql:host=$host;port=$port;dbname=$database", 
                $user, 
                $password, 
                [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $db_charset"]
            );
            
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            
            return new TrimmedPDO($conn);

        } catch (PDOException $e) {
            // 👇 رسالة للمستخدم
            die("⚠️ غير قادر على الوصول إلى قاعدة البيانات حالياً، حاول مرة أخرى لاحقاً. ".$e);

            // 👇 (اختياري) تسجيل الخطأ للمبرمج
            // error_log($e->getMessage());
        }
    }
}
class dbconn_old {
    public function getConnection($host, $port, $user, $password, $database, $db_charset) {
        $conn = new PDO(
            "mysql:host=$host;port=$port;dbname=$database", 
            $user, 
            $password, 
            [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $db_charset"]
        );
        
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        
        // 🧹 إنشاء كلاس wrapper بسيط
        return new TrimmedPDO($conn);
    }
}

/*class TrimmedPDO {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function prepare($sql, $options = []) {
        $cleanSql = trim(preg_replace('/\s+/', ' ', $sql));
        return $this->pdo->prepare($cleanSql, $options);
    }
    
    public function query($sql, $params = null) {
        $cleanSql = trim(preg_replace('/\s+/', ' ', $sql));
        if ($params === null) {
            return $this->pdo->query($cleanSql);
        } else {
            return $this->pdo->query($cleanSql, $params);
        }
    }
    
    public function exec($sql) {
        $cleanSql = trim(preg_replace('/\s+/', ' ', $sql));
        return $this->pdo->exec($cleanSql);
    }
    
    // 🎯 تمرير باقي الدوال كما هي
    public function __call($method, $args) {
        return call_user_func_array([$this->pdo, $method], $args);
    }
    
    public function __get($property) {
        return $this->pdo->$property;
    }
    
    public function __set($property, $value) {
        $this->pdo->$property = $value;
    }
}*/


class TrimmedPDO {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // 🔒 حارس أمان فقط
    public function rollBack() {
        if ($this->pdo->inTransaction()) {
            return $this->pdo->rollBack();
        }
        // تجاهل بصمت
        return true;
    }

    public function commit() {
        if ($this->pdo->inTransaction()) {
            return $this->pdo->commit();
        }
        return true;
    }

    public function beginTransaction() {
        if (!$this->pdo->inTransaction()) {
            return $this->pdo->beginTransaction();
        }
        return true;
    }

    public function prepare($sql, $options = []) {
        $cleanSql = trim(preg_replace('/\s+/', ' ', $sql));
        return $this->pdo->prepare($cleanSql, $options);
    }

    public function __call($method, $args) {
        return call_user_func_array([$this->pdo, $method], $args);
    }
}


/*class TrimmedPDO {
    private static $instance = null;
    private $pdo;

    private function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ✅ إنشاء اتصال واحد فقط
    public static function connect($dsn, $user, $pass, $options = []) {
        if (self::$instance === null) {

            $options = $options + [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT => false // مهم
            ];

            $pdo = new PDO($dsn, $user, $pass, $options);

            self::$instance = new self($pdo);
        }

        return self::$instance;
    }

    // 🔒 إغلاق الاتصال (اختياري)
    public function close() {
        $this->pdo = null;
        self::$instance = null;
    }

    // 🔒 الحماية من أخطاء الترانزاكشن
    public function rollBack() {
        if ($this->pdo && $this->pdo->inTransaction()) {
            return $this->pdo->rollBack();
        }
        return true;
    }

    public function commit() {
        if ($this->pdo && $this->pdo->inTransaction()) {
            return $this->pdo->commit();
        }
        return true;
    }

    public function beginTransaction() {
        if ($this->pdo && !$this->pdo->inTransaction()) {
            return $this->pdo->beginTransaction();
        }
        return true;
    }

    // 🔧 تنظيف الاستعلام
    public function prepare($sql, $options = []) {
        $cleanSql = trim(preg_replace('/\s+/', ' ', $sql));
        return $this->pdo->prepare($cleanSql, $options);
    }

    // تمرير باقي الدوال
    public function __call($method, $args) {
        return call_user_func_array([$this->pdo, $method], $args);
    }
}*/
?>