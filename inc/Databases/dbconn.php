<?php
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

class DBLockManager { 
	private $conn;

    /** الأقفال المسماة التي تم الحصول عليها */
    private array $namedLocks = [];

    /** الجداول التي تم قفلها بـ LOCK TABLES */
    private bool $tablesLocked = false;

    public function __construct($db){
        $this->conn = $db;
    }


    /**
     * قفل Rows متعددة من نفس الجدول باستدعاء واحد.
     *
     * مثال:
     *
     * $lock->lockRows(
     *     'orders',
     *     'ORDER_ID',
     *     [100, 101, 105],
     *     'UPDATE',
     *     true
     * );
     */
    public function lockRows( string $table, string $column, array $ids, string $mode = 'UPDATE', bool $nowait = true): array {
    	
        if (empty($ids)) {
            throw new InvalidArgumentException('لا توجد Rows للقفل');
        }

        $this->validateIdentifier($table);
        $this->validateIdentifier($column);

        $mode = strtoupper($mode);

        if (!in_array($mode, ['UPDATE', 'SHARE'], true)) {
            throw new InvalidArgumentException(
                'نوع قفل غير صحيح: UPDATE أو SHARE فقط'
            );
        }

        // تحويل الأرقام + إزالة التكرار
        $ids = array_values(
            array_unique(
                array_map('intval', $ids)
            )
        );

        // مهم جدًا لتقليل احتمالية Deadlock
        sort($ids, SORT_NUMERIC);

        $placeholders = implode(
            ',',
            array_fill(0, count($ids), '?')
        );

        $lockSql = $mode === 'UPDATE'
            ? 'FOR UPDATE'
            : 'FOR SHARE';

        if ($nowait) {
            $lockSql .= ' NOWAIT';
        }

        $sql = "
            SELECT `$column`
            FROM `$table`
            WHERE `$column` IN ($placeholders)
            ORDER BY `$column`
            $lockSql
        ";

        try {

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($ids);

            $lockedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // التأكد أن كل الـ IDs المطلوبة موجودة
            if (count($lockedIds) !== count($ids)) {
                $missing = array_values(
                    array_diff($ids, array_map('intval', $lockedIds))
                );

                throw new RuntimeException(
                    'بعض الصفوف غير موجودة: ' . implode(',', $missing)
                );
            }

            return array_map('intval', $lockedIds);

        } catch (PDOException $e) {

            throw new RuntimeException(
                'فشل قفل الصفوف: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }


    /**
     * قفل Rows من أكثر من جدول باستدعاء واحد.
     *
     * مثال:
     *
     * $lock->lockMultipleRows([
     *
     *     [
     *         'table'  => 'orders',
     *         'column' => 'ORDER_ID',
     *         'ids'    => [100, 101, 102]
     *     ],
     *
     *     [
     *         'table'  => 'order_product',
     *         'column' => 'ORDER_ID',
     *         'ids'    => [100, 101, 102]
     *     ]
     *
     * ]);
     */
    public function lockMultipleRows(array $locks, string $mode = 'UPDATE', bool $nowait = true): void {

        foreach ($locks as $lock) {
            if (!isset($lock['table']) || !isset($lock['column']) || !isset($lock['ids'])) {
                throw new InvalidArgumentException('بيانات قفل الصفوف غير مكتملة');
            }
            
            $this->lockRows($lock['table'], $lock['column'], $lock['ids'], $mode, $nowait);
        }
    }


    /**
     * قفل جدول أو أكثر.
     *
     * مثال:
     *
     * $lock->lockTables([
     *     'orders'       => 'WRITE',
     *     'order_product'=> 'WRITE'
     * ]);
     */
    public function lockTables(array $tables): void {
        if (empty($tables)) {
            throw new InvalidArgumentException('لا توجد جداول للقفل');
        }

        $parts = [];

        foreach ($tables as $table => $mode) {
            $this->validateIdentifier($table);

            $mode = strtoupper($mode);

            if (!in_array($mode, ['READ', 'WRITE'], true)) {
                throw new InvalidArgumentException("نوع قفل الجدول غير صحيح: $table");
            }

            $parts[] = "`$table` $mode";
        }

        $sql = 'LOCK TABLES ' . implode(', ', $parts);

        try {

            $this->conn->exec($sql);

            $this->tablesLocked = true;

        } catch (PDOException $e) {

            throw new RuntimeException(
                'فشل قفل الجداول: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }


    /**
     * الحصول على Named Lock باستخدام GET_LOCK().
     *
     * مثال:
     *
     * $lock->getLock('global_inventory_lock', 2);
     */
    public function getLock(
        string $name,
        int $timeout = 0
    ): void {

        if ($name === '') {
            throw new InvalidArgumentException(
                'اسم القفل فارغ'
            );
        }

        if ($timeout < 0) {
            throw new InvalidArgumentException(
                'مدة الانتظار غير صحيحة'
            );
        }

        try {

            $stmt = $this->conn->prepare(
                'SELECT GET_LOCK(?, ?)'
            );

            $stmt->execute([
                $name,
                $timeout
            ]);

            $result = $stmt->fetchColumn();

            if ((int)$result !== 1) {

                throw new RuntimeException(
                    "تعذر الحصول على GET_LOCK: $name"
                );
            }

            $this->namedLocks[$name] = true;

        } catch (PDOException $e) {

            throw new RuntimeException(
                "فشل GET_LOCK: $name - " . $e->getMessage(),
                0,
                $e
            );
        }
    }


    /**
     * الحصول على أكثر من GET_LOCK باستدعاء واحد.
     *
     * مهم:
     * يتم ترتيب الأسماء لمنع Deadlock بين العمليات.
     */
    public function getLocks(array $names, int $timeout = 0): void {
        if (empty($names)) return;

        $names = array_values(array_unique($names));

        sort($names, SORT_STRING);

        foreach ($names as $name) {
            $this->getLock($name, $timeout);
        }
    }


    /**
     * تحرير GET_LOCK واحد.
     */
    public function releaseLock(string $name): void {
        if (!isset($this->namedLocks[$name])) {
            return;
        }

        try {

            $stmt = $this->conn->prepare(
                'SELECT RELEASE_LOCK(?)'
            );

            $stmt->execute([$name]);

            unset($this->namedLocks[$name]);

        } catch (PDOException $e) {

            throw new RuntimeException(
                "فشل تحرير GET_LOCK: $name",
                0,
                $e
            );
        }
    }


    /**
     * تحرير جميع GET_LOCK التي حصل عليها هذا الكلاس.
     */
    public function releaseAllLocks(): void
    {
        foreach (array_keys($this->namedLocks) as $name) {
            $this->releaseLock($name);
        }
    }


    /**
     * تحرير LOCK TABLES.
     */
    public function unlockTables(): void
    {
        if (!$this->tablesLocked) {
            return;
        }

        try {

            $this->conn->exec('UNLOCK TABLES');

            $this->tablesLocked = false;

        } catch (PDOException $e) {

            throw new RuntimeException(
                'فشل تحرير أقفال الجداول',
                0,
                $e
            );
        }
    }


    /**
     * تحرير كل الأقفال التي يديرها الكلاس.
     */
    public function releaseAll(): void
    {
        // Named Locks
        $this->releaseAllLocks();

        // Table Locks
        $this->unlockTables();

        // Row Locks لا تحتاج RELEASE
        // لأنها تتحرر تلقائيًا مع COMMIT / ROLLBACK
    }


    /**
     * التحقق من اسم Table / Column.
     */
    private function validateIdentifier(string $identifier): void
    {
        if (
            !preg_match(
                '/^[A-Za-z_][A-Za-z0-9_]*$/',
                $identifier
            )
        ) {
            throw new InvalidArgumentException(
                "اسم SQL غير صالح: $identifier"
            );
        }
    }
}
?>