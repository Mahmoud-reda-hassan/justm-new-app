<?php
declare(strict_types=1);

/**
 * إدارة الخزائن وربط طرق الدفع بحسابات الخزائن داخل الفروع.
 * لا تنشئ هذه الخدمة خزائن أو mappings افتراضية.
 */
final class CashBox
{
    private PDO|TrimmedPDO $db;

    public function __construct(PDO|TrimmedPDO $db)
    {
        $this->db = $db;
    }

    public function listBranches(): array
    {
        $stmt = $this->db->query(
            'SELECT BR_ID AS id, BR_NAME AS name FROM branches ORDER BY BR_NAME, BR_ID'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listPostingAccounts(): array
    {
        $stmt = $this->db->query(
            "SELECT id, account_code, account_name
             FROM accounts
             WHERE is_active = 1 AND allow_posting = 1 AND account_type = 'ASSET'
             ORDER BY account_code"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listPaymentMethods(int $branchId): array
    {
        if ($branchId <= 0) {
            throw new InvalidArgumentException('الفرع مطلوب لعرض طرق الدفع.');
        }

        $stmt = $this->db->prepare(
            "SELECT pm.id, pm.name,
                    pma.account_id,
                    cb.id AS cash_box_id,
                    COALESCE(NULLIF(cb.box_name, ''), NULLIF(cb.name, '')) AS cash_box_name
             FROM payment_methods pm
             LEFT JOIN payment_method_accounts pma
               ON pma.payment_method_id = pm.id
              AND pma.branch_id = ?
              AND pma.is_active = 1
             LEFT JOIN cash_boxes cb
               ON cb.account_id = pma.account_id
              AND cb.branch_id = pma.branch_id
              AND cb.is_active = 1
             WHERE pm.is_active = 1 AND pm.id = 1
             ORDER BY pm.id"
        );
        $stmt->execute([$branchId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listForBranch(int $branchId): array
    {
        if ($branchId <= 0) {
            throw new InvalidArgumentException('الفرع مطلوب لعرض الخزائن.');
        }

        $stmt = $this->db->prepare(
            "SELECT cb.id, cb.branch_id, cb.account_id,
                    COALESCE(NULLIF(cb.box_name, ''), NULLIF(cb.name, ''), CONCAT('خزينة #', cb.id)) AS name,
                    cb.is_active, a.account_code, a.account_name,
                    COALESCE(SUM(CASE WHEN je.status = 'POSTED' THEN jd.debit - jd.credit ELSE 0 END), 0) AS balance
             FROM cash_boxes cb
             INNER JOIN accounts a ON a.id = cb.account_id
             LEFT JOIN journal_entry_details jd ON jd.cash_box_id = cb.id
             LEFT JOIN journal_entries je ON je.id = jd.journal_entry_id
             WHERE cb.branch_id = ?
             GROUP BY cb.id, cb.branch_id, cb.account_id, cb.box_name, cb.name,
                      cb.is_active, a.account_code, a.account_name
             ORDER BY cb.is_active DESC, cb.id"
        );
        $stmt->execute([$branchId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listForPaymentMethod(int $branchId, int $paymentMethodId): array
    {
        if ($branchId <= 0 || $paymentMethodId <= 0) {
            throw new InvalidArgumentException('الفرع وطريقة الدفع مطلوبان لعرض الخزائن.');
        }

        $stmt = $this->db->prepare(
            "SELECT cb.id, cb.account_id,
                    COALESCE(NULLIF(cb.box_name, ''), NULLIF(cb.name, ''), CONCAT('خزينة #', cb.id)) AS name
             FROM cash_boxes cb
             INNER JOIN payment_method_accounts pma
                ON pma.branch_id = cb.branch_id
               AND pma.account_id = cb.account_id
               AND pma.payment_method_id = ?
               AND pma.is_active = 1
             INNER JOIN payment_methods pm ON pm.id = pma.payment_method_id AND pm.is_active = 1
             INNER JOIN accounts a ON a.id = cb.account_id
             WHERE cb.branch_id = ? AND cb.is_active = 1
               AND a.is_active = 1 AND a.allow_posting = 1
             ORDER BY cb.id"
        );
        $stmt->execute([$paymentMethodId, $branchId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function save(int $branchId, int $boxId, string $boxName, int $accountId, bool $isActive = true): int
    {
        $boxName = trim($boxName);
        if ($branchId <= 0 || $accountId <= 0 || $boxName === '' || mb_strlen($boxName) > 100) {
            throw new InvalidArgumentException('الفرع واسم الخزينة وحسابها مطلوبة، والاسم لا يتجاوز 100 حرف.');
        }

        return $this->withinTransaction(function () use ($branchId, $boxId, $boxName, $accountId, $isActive): int {
            $this->assertBranch($branchId);
            $this->assertPostingAssetAccount($accountId);

            $stmt = $this->db->prepare(
                'SELECT id FROM cash_boxes
                 WHERE branch_id = ? AND account_id = ? AND (? = 0 OR id <> ?)
                 LIMIT 1'
            );
            $stmt->execute([$branchId, $accountId, $boxId, $boxId]);
            if ($stmt->fetchColumn()) {
                throw new RuntimeException('حساب الأستاذ مرتبط بالفعل بخزينة أخرى في هذا الفرع. خصص حسابًا مستقلًا لكل خزينة.');
            }
            $stmt->closeCursor();

            $stmt = $this->db->prepare(
                'SELECT id FROM cash_boxes
                 WHERE branch_id = ? AND box_name = ? AND (? = 0 OR id <> ?)
                 LIMIT 1'
            );
            $stmt->execute([$branchId, $boxName, $boxId, $boxId]);
            if ($stmt->fetchColumn()) {
                throw new RuntimeException('يوجد صندوق آخر بنفس الاسم في هذا الفرع.');
            }
            $stmt->closeCursor();

            if ($boxId > 0) {
                $stmt = $this->db->prepare('SELECT id, account_id FROM cash_boxes WHERE id = ? AND branch_id = ? FOR UPDATE');
                $stmt->execute([$boxId, $branchId]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                $stmt->closeCursor();
                if (!$existing) {
                    throw new RuntimeException('الخزينة المطلوب تعديلها غير موجودة في هذا الفرع.');
                }

                $oldAccountId = (int)$existing['account_id'];
                if ($oldAccountId !== $accountId) {
                    $usage = $this->db->prepare('SELECT COUNT(*) FROM journal_entry_details WHERE cash_box_id = ?');
                    $usage->execute([$boxId]);
                    $hasHistory = (int)$usage->fetchColumn() > 0;
                    $usage->closeCursor();
                    if ($hasHistory) {
                        throw new RuntimeException('لا يمكن تغيير حساب خزينة لها حركات سابقة. أنشئ خزينة جديدة بالحساب الجديد.');
                    }

                    $moveMappings = $this->db->prepare(
                        'UPDATE payment_method_accounts SET account_id = ?
                         WHERE branch_id = ? AND account_id = ?'
                    );
                    $moveMappings->execute([$accountId, $branchId, $oldAccountId]);
                }

                if (!$isActive) {
                    $this->assertZeroBalance($boxId);
                }

                $update = $this->db->prepare(
                    'UPDATE cash_boxes SET account_id = ?, box_name = ?, name = ?, is_active = ? WHERE id = ? AND branch_id = ?'
                );
                $update->execute([$accountId, $boxName, $boxName, $isActive ? 1 : 0, $boxId, $branchId]);

                if (!$isActive) {
                    $this->deactivatePaymentMappings($branchId, $accountId);
                }
                return $boxId;
            }

            if (!$isActive) {
                throw new InvalidArgumentException('أنشئ الخزينة نشطة أولًا، ثم يمكنك إيقافها عند الحاجة.');
            }

            $insert = $this->db->prepare(
                'INSERT INTO cash_boxes (branch_id, account_id, box_name, name, is_active)
                 VALUES (?, ?, ?, ?, 1)'
            );
            $insert->execute([$branchId, $accountId, $boxName, $boxName]);
            return (int)$this->db->lastInsertId();
        });
    }

    public function setPaymentMethodMapping(int $branchId, int $paymentMethodId, int $cashBoxId): void
    {
        if ($branchId <= 0 || $paymentMethodId <= 0) {
            throw new InvalidArgumentException('الفرع وطريقة الدفع مطلوبان.');
        }
        if ($paymentMethodId !== 1) {
            throw new InvalidArgumentException('ربط الخزائن مخصص لطريقة الدفع النقدي فقط؛ التحويلات والبطاقات والمحافظ لها حسابات تسوية منفصلة.');
        }

        $this->withinTransaction(function () use ($branchId, $paymentMethodId, $cashBoxId): void {
            $this->assertBranch($branchId);

            $method = $this->db->prepare('SELECT id FROM payment_methods WHERE id = ? AND is_active = 1');
            $method->execute([$paymentMethodId]);
            if (!$method->fetchColumn()) {
                throw new RuntimeException('طريقة الدفع غير موجودة أو غير نشطة.');
            }
            $method->closeCursor();

            if ($cashBoxId <= 0) {
                $disable = $this->db->prepare(
                    'UPDATE payment_method_accounts SET is_active = 0
                     WHERE payment_method_id = ? AND branch_id = ?'
                );
                $disable->execute([$paymentMethodId, $branchId]);
                return;
            }

            $box = $this->db->prepare(
                'SELECT account_id FROM cash_boxes
                 WHERE id = ? AND branch_id = ? AND is_active = 1 FOR UPDATE'
            );
            $box->execute([$cashBoxId, $branchId]);
            $accountId = (int)$box->fetchColumn();
            $box->closeCursor();
            if ($accountId <= 0) {
                throw new RuntimeException('الخزينة غير موجودة أو غير نشطة في الفرع المحدد.');
            }
            $this->assertPostingAssetAccount($accountId);

            $stmt = $this->db->prepare(
                'INSERT INTO payment_method_accounts (payment_method_id, branch_id, account_id, is_active)
                 VALUES (?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE account_id = VALUES(account_id), is_active = 1'
            );
            $stmt->execute([$paymentMethodId, $branchId, $accountId]);
        });
    }

    private function withinTransaction(callable $callback)
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $result = $callback();
            if ($ownsTransaction) {
                $this->db->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function assertBranch(int $branchId): void
    {
        $stmt = $this->db->prepare('SELECT BR_ID FROM branches WHERE BR_ID = ?');
        $stmt->execute([$branchId]);
        $exists = (bool)$stmt->fetchColumn();
        $stmt->closeCursor();
        if (!$exists) {
            throw new RuntimeException('الفرع المحدد غير موجود.');
        }
    }

    private function assertPostingAssetAccount(int $accountId): void
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM accounts
             WHERE id = ? AND account_type = 'ASSET' AND is_active = 1 AND allow_posting = 1"
        );
        $stmt->execute([$accountId]);
        $exists = (bool)$stmt->fetchColumn();
        $stmt->closeCursor();
        if (!$exists) {
            throw new RuntimeException('حساب الخزينة يجب أن يكون حساب أصول نشطًا وقابلًا للترحيل.');
        }
    }

    private function assertZeroBalance(int $boxId): void
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(jd.debit - jd.credit), 0)
             FROM journal_entry_details jd
             INNER JOIN journal_entries je ON je.id = jd.journal_entry_id
             WHERE jd.cash_box_id = ? AND je.status = 'POSTED'"
        );
        $stmt->execute([$boxId]);
        $balance = (float)$stmt->fetchColumn();
        $stmt->closeCursor();
        if (abs($balance) > 0.0001) {
            throw new RuntimeException('لا يمكن إيقاف خزينة لها رصيد غير مسوّى.');
        }
    }

    private function deactivatePaymentMappings(int $branchId, int $accountId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE payment_method_accounts SET is_active = 0 WHERE branch_id = ? AND account_id = ?'
        );
        $stmt->execute([$branchId, $accountId]);
    }
}
