<?php

class AccountManager{

    private $db;

    public function __construct($db){
        $this->db = $db;
    }

    /**
     * إنشاء حساب جديد
     */
    public function create(array $data): int{

        $parentId      = $data['parent_id'] ?? null;
        $accountCode   = trim($data['account_code']);
        $accountName   = trim($data['account_name']);
        $accountType   = strtoupper($data['account_type']);
        $allowPosting  = !empty($data['allow_posting']) ? 1 : 0;
        $description   = $data['description'] ?? '';

        if ($accountCode == '')
            throw new Exception("كود الحساب مطلوب.");

        if ($accountName == '')
            throw new Exception("اسم الحساب مطلوب.");

        if (!in_array($accountType, ['ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE']))
            throw new Exception("نوع الحساب غير صحيح.");

        // التأكد من عدم تكرار الكود
        if ($this->existsCode($accountCode))
            throw new Exception("كود الحساب مستخدم بالفعل.");

        $level = 1;
        $normalBalance = $this->defaultNormalBalance($accountType);

        if ($parentId != null) {

            $parent = $this->find($parentId);

            if (!$parent)
                throw new Exception("الحساب الأب غير موجود.");

            if ((int)$parent['allow_posting'] == 1)
                throw new Exception("لا يمكن إضافة أبناء لحساب يسمح بالترحيل.");

            if ($parent['account_type'] != $accountType)
                throw new Exception("نوع الحساب يجب أن يكون مطابقاً للحساب الأب.");

            $level = $parent['account_level'] + 1;

        }

        $sql = "

            INSERT INTO accounts
            (

                parent_id,
                account_code,
                account_name,
                account_type,
                normal_balance,
                account_level,
                allow_posting,
                system_account,
                description

            )

            VALUES

            (?,?,?,?,?,?,?,?,?)

        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([

            $parentId,
            $accountCode,
            $accountName,
            $accountType,
            $normalBalance,
            $level,
            $allowPosting,
            0,
            $description

        ]);

        return (int)$this->db->lastInsertId();

    }

    /**
     * البحث بواسطة ID
     */
    public function find(int $id): ?array {

        $stmt = $this->db->prepare("

            SELECT *

            FROM accounts

            WHERE id=?

            LIMIT 1

        ");

        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;

    }

    /**
     * البحث بواسطة الكود
     */
    public function findByCode(string $code): ?array{

        $stmt = $this->db->prepare("

            SELECT *

            FROM accounts

            WHERE account_code=?

            LIMIT 1

        ");

        $stmt->execute([$code]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;

    }

    /**
     * التأكد من وجود الكود
     */
    private function existsCode(string $code): bool {

        $stmt = $this->db->prepare("

            SELECT id

            FROM accounts

            WHERE account_code=?

            LIMIT 1

        ");

        $stmt->execute([$code]);

        return $stmt->fetch() ? true : false;

    }

    /**
     * الطبيعة الافتراضية للحساب
     */
    private function defaultNormalBalance(string $type): string {

        switch ($type) {

            case 'ASSET':
            case 'EXPENSE':
                return 'DEBIT';

            case 'LIABILITY':
            case 'EQUITY':
            case 'REVENUE':
                return 'CREDIT';

            default:
                throw new Exception("نوع الحساب غير صحيح.");

        }

    }

}