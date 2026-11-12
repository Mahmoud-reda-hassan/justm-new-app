<?php
class TableInserter {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
	
	/**
	 * التحقق إذا كان النص JSON صالحًا.
	 *
	 * @param string $string النص المراد التحقق منه.
	 * @return bool هل هو JSON صالح.
	 */
	function isJson($string) {
	    json_decode($string);
	    return (json_last_error() === JSON_ERROR_NONE);
	}
	
     /**
 	 * تنظيف المدخلات مع الحفاظ على بيانات JSON.
	 *
	 * @param mixed $input المدخل (نص أو مصفوفة).
	 * @return mixed المدخل النظيف.
	 */
	function sanitizeInput($input) {
	    // التحقق إذا كانت المدخلات مصفوفة
	    if (is_array($input)) {
	        $sanitized = [];
	        foreach ($input as $key => $value) {
	            $sanitized[$key] = $this->sanitizeInput($value); // استدعاء نفس الدالة بشكل متكرر
	        }
	        return $sanitized;
	    }
	
	    // التحقق إذا كانت البيانات JSON
	    if ($this->isJson($input)) {
	        return $input; // إرجاعها كما هي إذا كانت JSON
	    }
	
	    // تنظيف النصوص الأخرى
	    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
	}
	
    /**
     * إدخال بيانات في الجدول باستخدام bindValue().
     *
     * @param string $tableName اسم الجدول.
     * @param array $data الحقول وقيمها.
     * @return array نتيجة العملية.
     */
    public function insert($tableName, $data) {
        try {
            // التحقق من صحة المدخلات
            if (empty($tableName) || empty($data)) {
                throw new Exception("Invalid parameters: Table name and data are required.");
                
            }
            
            // تنظيف البيانات
            $data = $this->sanitizeInput($data);

            // تجهيز أسماء الحقول والقيم
            $fields = implode(", ", array_keys($data));
            $placeholders = implode(", ", array_map(fn($key) => ":$key", array_keys($data)));
			
            // بناء استعلام SQL
            $query = "INSERT INTO $tableName ($fields) VALUES ($placeholders)";
            $stmt = $this->pdo->prepare($query);
            // استخدام bindValue لربط القيم
            foreach ($data as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }

            // تنفيذ الاستعلام
            $result = $stmt->execute();

            return [
                "result"		=> $stmt->rowCount(),
                "message"		=> $result ? "Insert into $tableName successful" : "Insert into $tableName failed",
                "insertedId" 	=> $result ? $this->pdo->lastInsertId() : null
            ];

        } catch (Exception $e) {
            throw $e;
        }
    }
}
?>