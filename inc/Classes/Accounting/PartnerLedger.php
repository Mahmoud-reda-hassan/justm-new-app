<?php
declare(strict_types=1);

/**
 * دفتر أستاذ الأطراف مبني على القيود المرحلة فقط.
 * لا يعتمد على partners_balance أو transaction_categories.
 *
 * التخصيص يربط سطرًا مدينًا بسطر دائن لنفس الحساب والطرف، مثل ربط تحصيل
 * العميل بمديونيته أو ربط دفعة المورد بالمستحق. التخصيص لا ينشئ قيدًا ماليًا.
 */
final class PartnerLedger
{
    private PDO|TrimmedPDO $db;

    public function __construct(PDO|TrimmedPDO $db)
    {
        $this->db = $db;
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * الرصيد الصافي للطرف على حساب الأستاذ المحدد: المدين ناقص الدائن.
     * الموجب = مدين، والسالب = دائن.
     */
    public function getBalance(int $accountId, string $partnerType, int $partnerId): float
    {
        $this->validatePartner($accountId, $partnerType, $partnerId);
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(d.debit - d.credit), 0)
             FROM journal_entry_details d
             INNER JOIN journal_entries h ON h.id = d.journal_entry_id
             WHERE h.status IN ('POSTED', 'REVERSED')
               AND d.account_id = ? AND d.partner_type = ? AND d.partner_id = ?"
        );
        $stmt->execute([$accountId, strtoupper($partnerType), $partnerId]);
        return round((float)$stmt->fetchColumn(), 2);
    }

    /**
     * كشف حساب الطرف من القيود المرحلة والقيود الأصلية التي لها قيد عكسي؛ يظهر القيد الأصلي والعكسي معًا للوصول إلى الرصيد الصافي.
     * يعيد الحركات بالترتيب الزمني مع الرصيد التراكمي.
     */
    public function getStatement(
        int $accountId,
        string $partnerType,
        int $partnerId,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $this->validatePartner($accountId, $partnerType, $partnerId);
        $this->validateDateRange($fromDate, $toDate);

        $openingBalance = 0.0;
        if ($fromDate !== null) {
            $opening = $this->db->prepare(
                "SELECT COALESCE(SUM(d.debit - d.credit), 0)
                 FROM journal_entry_details d
                 INNER JOIN journal_entries h ON h.id = d.journal_entry_id
                 WHERE h.status IN ('POSTED', 'REVERSED')
                   AND d.account_id = ? AND d.partner_type = ? AND d.partner_id = ?
                   AND h.entry_date < ?"
            );
            $opening->execute([$accountId, strtoupper($partnerType), $partnerId, $fromDate . ' 00:00:00']);
            $openingBalance = round((float)$opening->fetchColumn(), 2);
        }

        $sql = "SELECT d.id AS line_id, h.id AS journal_entry_id, h.entry_no,
                       h.entry_date, h.reference_type, h.reference_id,
                       h.description AS entry_description, d.note AS line_note,
                       d.debit, d.credit
                FROM journal_entry_details d
                INNER JOIN journal_entries h ON h.id = d.journal_entry_id
                WHERE h.status IN ('POSTED', 'REVERSED')
                  AND d.account_id = ? AND d.partner_type = ? AND d.partner_id = ?";
        $params = [$accountId, strtoupper($partnerType), $partnerId];
        if ($fromDate !== null) {
            $sql .= ' AND h.entry_date >= ?';
            $params[] = $fromDate . ' 00:00:00';
        }
        if ($toDate !== null) {
            $sql .= ' AND h.entry_date < DATE_ADD(?, INTERVAL 1 DAY)';
            $params[] = $toDate;
        }
        $sql .= ' ORDER BY h.entry_date, h.id, d.line_no, d.id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $running = $openingBalance;
        foreach ($rows as &$row) {
            $running += (float)$row['debit'] - (float)$row['credit'];
            $row['running_balance'] = round($running, 2);
            $row['debit'] = round((float)$row['debit'], 2);
            $row['credit'] = round((float)$row['credit'], 2);
        }
        unset($row);
        return $rows;
    }

    /**
     * تخصيص مبلغ بين سطر مدين وسطر دائن لنفس الحساب والطرف.
     * يشترط أن يكون القيدان مرحلين وألا يتجاوز التخصيص المتبقي في أي سطر.
     */
    public function allocate(
        int $debitLineId,
        int $creditLineId,
        float $amount,
        int $createdBy,
        string $note = ''
    ): int {
        if ($debitLineId <= 0 || $creditLineId <= 0 || $debitLineId === $creditLineId) {
            throw new InvalidArgumentException('يجب تحديد سطرين مختلفين للتخصيص.');
        }
        if (!is_finite($amount) || $amount <= 0) {
            throw new InvalidArgumentException('مبلغ التخصيص يجب أن يكون أكبر من صفر.');
        }
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('مبلغ التخصيص بعد التقريب غير صالح.');
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            // ترتيب ثابت للأقفال لتقليل احتمال التعارض بين الطلبات المتزامنة.
            $ids = [$debitLineId, $creditLineId];
            sort($ids, SORT_NUMERIC);
            $locked = [];
            $lock = $this->db->prepare(
                "SELECT d.id, d.journal_entry_id, d.account_id, d.partner_type,
                        d.partner_id, d.debit, d.credit, h.status AS header_status
                 FROM journal_entry_details d
                 INNER JOIN journal_entries h ON h.id = d.journal_entry_id
                 WHERE d.id = ? FOR UPDATE"
            );
            foreach ($ids as $id) {
                $lock->execute([$id]);
                $row = $lock->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    throw new RuntimeException('سطر القيد المطلوب للتخصيص غير موجود: ' . $id);
                }
                $locked[$id] = $row;
            }

            $debit = $locked[$debitLineId];
            $credit = $locked[$creditLineId];
            if ($debit['header_status'] !== 'POSTED' || $credit['header_status'] !== 'POSTED') {
                throw new RuntimeException('لا يمكن تخصيص سطور إلا من قيود مرحلة.');
            }
            if ((float)$debit['debit'] <= 0 || (float)$debit['credit'] != 0.0) {
                throw new RuntimeException('السطر الأول يجب أن يكون سطرًا مدينًا فقط.');
            }
            if ((float)$credit['credit'] <= 0 || (float)$credit['debit'] != 0.0) {
                throw new RuntimeException('السطر الثاني يجب أن يكون سطرًا دائنًا فقط.');
            }
            if ((int)$debit['account_id'] !== (int)$credit['account_id']
                || $debit['partner_type'] !== $credit['partner_type']
                || (string)$debit['partner_id'] !== (string)$credit['partner_id']
                || $debit['partner_type'] === null || $debit['partner_id'] === null) {
                throw new RuntimeException('يجب أن يتبع السطران نفس حساب الأستاذ ونفس الطرف.');
            }

            $remaining = $this->db->prepare(
                "SELECT COALESCE(SUM(CASE WHEN debit_line_id = ? OR credit_line_id = ?
                                          THEN amount ELSE 0 END), 0)
                 FROM accounting_line_allocations
                 WHERE status = 'ACTIVE' AND (debit_line_id = ? OR credit_line_id = ?)"
            );
            $remaining->execute([$debitLineId, $debitLineId, $debitLineId, $debitLineId]);
            $debitAllocated = (float)$remaining->fetchColumn();

            $remaining->execute([$creditLineId, $creditLineId, $creditLineId, $creditLineId]);
            $creditAllocated = (float)$remaining->fetchColumn();

            $debitAvailable = round((float)$debit['debit'] - $debitAllocated, 2);
            $creditAvailable = round((float)$credit['credit'] - $creditAllocated, 2);
            if ($amount > $debitAvailable || $amount > $creditAvailable) {
                throw new RuntimeException(
                    'مبلغ التخصيص يتجاوز المتبقي. المتاح مدين: ' . number_format(max(0, $debitAvailable), 2)
                    . '، والمتاح دائن: ' . number_format(max(0, $creditAvailable), 2)
                );
            }

            $insert = $this->db->prepare(
                "INSERT INTO accounting_line_allocations
                    (debit_line_id, credit_line_id, amount, note, created_by)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $insert->execute([$debitLineId, $creditLineId, $amount, trim($note), $createdBy > 0 ? $createdBy : null]);
            $id = (int)$this->db->lastInsertId();

            if ($ownsTransaction) {
                $this->db->commit();
            }
            return $id;
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /** عكس تخصيص دون حذف أثره التاريخي. */
    public function reverseAllocation(int $allocationId, int $reversedBy, string $reason): void
    {
        if ($allocationId <= 0 || trim($reason) === '') {
            throw new InvalidArgumentException('رقم التخصيص وسبب العكس مطلوبان.');
        }
        $stmt = $this->db->prepare(
            "UPDATE accounting_line_allocations
             SET status = 'REVERSED', reversed_by = ?, reversed_at = NOW(), reversal_reason = ?
             WHERE id = ? AND status = 'ACTIVE'"
        );
        $stmt->execute([$reversedBy > 0 ? $reversedBy : null, trim($reason), $allocationId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('التخصيص غير موجود أو سبق عكسه.');
        }
    }

    private function validatePartner(int $accountId, string $partnerType, int $partnerId): void
    {
        $allowed = ['CLIENT', 'SUPPLIER', 'DELIVERY', 'MARKETER', 'EMPLOYEE'];
        if ($accountId <= 0 || $partnerId <= 0 || !in_array(strtoupper($partnerType), $allowed, true)) {
            throw new InvalidArgumentException('الحساب ونوع الطرف ومعرفه مطلوبة بقيم صحيحة.');
        }
    }

    private function validateDateRange(?string $fromDate, ?string $toDate): void
    {
        foreach ([$fromDate, $toDate] as $date) {
            if ($date !== null) {
                $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                    throw new InvalidArgumentException('صيغة التاريخ المطلوبة YYYY-MM-DD.');
                }
            }
        }
        if ($fromDate !== null && $toDate !== null && $fromDate > $toDate) {
            throw new InvalidArgumentException('تاريخ البداية يجب ألا يتجاوز تاريخ النهاية.');
        }
    }
}
