<?php
include_once(ABSPATH.'/inc/Databases/DataProcessor.php');
class TableUpdater {
    private $pdo;
	private $DPro;
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->DPro = new DataProcessor();
    }
	/**
	 * بناء شروط WHERE مع دعم AND و OR.
	 */
	private function buildWhereClause($conditions, &$params, $operators, $prefix = "") {
	    $clauses = [];
	    foreach ($conditions as $key => $value) {
	        if (is_array($value) && isset($value['_logic'])) {
	            // إذا كان هناك منطق (AND/OR) داخل الشرط
	            $logic = strtoupper($value['_logic']);
	            unset($value['_logic']);
	            $nestedClause = $this->buildWhereClause($value, $params, $operators, $prefix . $key . "_");
	            $clauses[] = "($nestedClause)";
	        } else {
	            // شرط عادي
	            $operator = isset($operators[$key]) ? $operators[$key] : '=';
	            $paramKey = ":{$prefix}{$key}";
	            $clauses[] = "$key $operator $paramKey";
	            $params[$paramKey] = $value;
	        }
	    }
	    return implode(" " . ($conditions['_logic'] ?? "AND") . " ", $clauses);
	}
	
    /**
     * تحديث بيانات الجدول.
     *
     * @param string $tableName اسم الجدول.
     * @param array $data الحقول وقيمها للتحديث.
     * @param array $where شروط التحديث.
     * 
	 * example:-
		  data = [
				    [
				        "TOTAL_PRICE"    => 600,
				        "TOTAL_PAYMENTS" => 300
				    ],
				    [
				        "orderid = :where_0 and statuse = :where_1",  // جملة WHERE النصية
				        [50, "deferred"] // القيم الخاصة بالـ WHERE
				    ]
				]
     */
	public function update($tableName, $data, $where) {
	    try {
	        // التحقق من صحة المدخلات
	        if (empty($tableName) || empty($data) || empty($where) || !isset($where[0]) || !isset($where[1])) {
	            throw new Exception("Invalid parameters: Table name, data, and where clause are required.");
	        }
	
	        // تنظيف المدخلات
	        $data = $this->DPro->sanitizeInput($data);
			
	        // تجهيز الحقول والتعبيرات للـ SET
	        $setClause = [];
	        foreach ($data as $key => $value) {
	        	
	            $setClause[] = "$key = $value";
	        }
	        $setClauseString = implode(", ", $setClause);
	        
	        // جملة WHERE النصية
	        $whereClauseString = $where[0]; // جملة WHERE النصية
	        $whereValues = $this->DPro->sanitizeInput($where[1]); // القيم الخاصة بـ WHERE
	        // بناء استعلام SQL
	        $query = "UPDATE $tableName SET $setClauseString WHERE $whereClauseString";
	
	        // تحضير الاستعلام
	        $stmt = $this->pdo->prepare($query);
	
	        // استخدام bindValue لجميع البيانات
	        foreach ($data as $key => $value) {
	            $stmt->bindValue(":$key", $value, PDO::PARAM_STR);
	        }
	        foreach ($whereValues as $key => $value) {
	            $stmt->bindValue($key, $value, PDO::PARAM_STR);
	        }
	        
	        // تنفيذ الاستعلام
	        $result = $stmt->execute();
	        return [
	            "result" => $result,
	            "message" => $result ? "Update $tableName successful" : "Update $tableName failed",
	            "affectedRows" => $stmt->rowCount(),
	        ];

	    } catch (Exception $e) {
	        throw $e;
	    }
	}
}
?>