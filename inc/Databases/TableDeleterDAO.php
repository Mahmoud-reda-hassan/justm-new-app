<?php
class TableDeleter {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * حذف بيانات من الجداول.
     *
     * @param string $tableName اسم الجدول.
     * @param array $where شروط الحذف.
     * @param array $operators المشغلين (Operators) لشروط WHERE (اختياري).
     * @return array نتيجة العملية.
     */
    public function delete($tableName, $where, $operators = []) {
        try {
            // التحقق من صحة المدخلات
            if (empty($tableName) || empty($where)) {
                throw new Exception("Invalid parameters: Table name and where clause are required.");
            }

            // تجهيز شروط WHERE
            $whereClause = [];
            $params = [];
            foreach ($where as $key => $value) {
                $operator = isset($operators[$key]) ? $operators[$key] : '=';
                $whereClause[] = "$key $operator :$key";
                $params[$key] = $value;
            }
            $whereClauseString = implode(" AND ", $whereClause);

            // بناء استعلام SQL
            $query = "DELETE FROM $tableName WHERE $whereClauseString";

            // تحضير وتنفيذ الاستعلام
            $stmt = $this->pdo->prepare($query);
            $result = $stmt->execute($params);

            return [
                "result" => $result,
                "message" => $result ? "Delete ".$tableName." successful" : "Delete ".$tableName." failed",
                "affectedRows" => $stmt->rowCount()
            ];
        } catch (Exception $e) {
            return [
                "result" => false,
                "message" => $e->getMessage()
            ];
        }
    }
}
?>