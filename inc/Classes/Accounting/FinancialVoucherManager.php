<?php
declare(strict_types=1);

require_once __DIR__ . '/JournalManager.php';
require_once __DIR__ . '/FinancialEventManager.php';
require_once __DIR__ . '/PartnerLedger.php';
require_once __DIR__ . '/AccountSettings.php';
require_once __DIR__ . '/CashBox.php';

/**
 * مستندات قبض وصرف عامة، كمستندات مصدر مرتبطة بالأستاذ العام.
 * لا تحتفظ بأرصدة مستقلة؛ كل الأرصدة تُقرأ من journal_entries وjournal_entry_details.
 */
final class FinancialVoucherManager
{
    private PDO|TrimmedPDO $db;
    private FinancialEventManager $events;
    private PartnerLedger $partnerLedger;
    private AccountSettings $accountSettings;
    private CashBox $cashBoxes;

    public function __construct(PDO|TrimmedPDO $db)
    {
        $this->db = $db;
        $this->events = new FinancialEventManager($db);
        $this->partnerLedger = new PartnerLedger($db);
        $this->accountSettings = new AccountSettings($db);
        $this->cashBoxes = new CashBox($db);
    }

    /**
     * subtype:
     * CUSTOMER_RECEIPT, SUPPLIER_REFUND, GENERAL_RECEIPT, GENERAL_PAYMENT
     */
    public function post(array $input): array
    {
        $data = $this->normalizeInput($input);
        $payloadHash = hash('sha256', $this->canonicalJson($data['payload']));

        return $this->withinTransaction(function () use ($data, $payloadHash): array {
            $existing = $this->findByIdempotencyKey($data['idempotency_key']);
            if ($existing) {
                if (!hash_equals((string)$existing['payload_hash'], $payloadHash)) {
                    throw new RuntimeException('مفتاح منع التكرار مستخدم لمستند ببيانات مختلفة.');
                }
                if (!in_array($existing['status'], ['POSTED', 'REVERSED'], true)
                    || empty($existing['journal_entry_id'])) {
                    throw new RuntimeException('يوجد مستند قبض/صرف غير مكتمل؛ يلزم فحصه قبل إعادة المحاولة.');
                }
                return [
                    'voucher_id' => (int)$existing['id'],
                    'voucher_no' => (string)$existing['voucher_no'],
                    'journal_entry_id' => (int)$existing['journal_entry_id'],
                    'status' => (string)$existing['status'],
                    'already_posted' => true,
                ];
            }

            $cashBoxAccountId = $this->getCashBoxAccountId($data['branch_id'], $data['cash_box_id']);
            $this->validateCounterpartLines($data);

            if ($data['voucher_subtype'] === 'SUPPLIER_REFUND') {
                $available = $this->getOpenDebitAmount(
                    $data['lines'][0]['account_id'],
                    'SUPPLIER',
                    $data['partner_id']
                );
                if ($data['total_amount'] > $available) {
                    throw new RuntimeException(
                        'مبلغ رد المورد أكبر من الرصيد المدين المستحق لدى المورد. المتاح: '
                        . number_format($available, 2)
                    );
                }
            }

            $insert = $this->db->prepare(
                "INSERT INTO financial_vouchers
                    (voucher_no, voucher_type, voucher_subtype, branch_id, cash_box_id,
                     payment_method_id, voucher_date, partner_type, partner_id,
                     total_amount, description, status, idempotency_key, payload_hash, created_by)
                 VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PROCESSING', ?, ?, ?)"
            );
            $insert->execute([
                $data['voucher_type'],
                $data['voucher_subtype'],
                $data['branch_id'],
                $data['cash_box_id'],
                $data['payment_method_id'],
                $data['voucher_date'],
                $data['partner_type'],
                $data['partner_id'],
                $data['total_amount'],
                $data['description'],
                $data['idempotency_key'],
                $payloadHash,
                $data['created_by'],
            ]);
            $voucherId = (int)$this->db->lastInsertId();
            $prefix = $data['voucher_type'] === 'RECEIPT' ? 'CR' : 'CP';
            $voucherNo = sprintf('%s-%s-%06d', $prefix, substr($data['voucher_date'], 0, 4), $voucherId);

            $updateNo = $this->db->prepare('UPDATE financial_vouchers SET voucher_no = ? WHERE id = ?');
            $updateNo->execute([$voucherNo, $voucherId]);

            $lineInsert = $this->db->prepare(
                'INSERT INTO financial_voucher_lines
                    (voucher_id, line_no, account_id, partner_type, partner_id,
                     cost_center_id, amount, line_description)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($data['lines'] as $index => $line) {
                $lineInsert->execute([
                    $voucherId,
                    $index + 1,
                    $line['account_id'],
                    $line['partner_type'],
                    $line['partner_id'],
                    $line['cost_center_id'],
                    $line['amount'],
                    $line['line_description'],
                ]);
            }

            $event = $this->events->post([
                'idempotency_key' => 'FV-POST:' . $data['idempotency_key'],
                'event_type' => 'FINANCIAL_VOUCHER_POSTED',
                'source_type' => 'FINANCIAL_VOUCHER',
                'source_id' => $voucherId,
                'branch_id' => $data['branch_id'],
                'event_date' => $data['voucher_date'],
                'created_by' => $data['created_by'],
                'payload' => [
                    'voucher_id' => $voucherId,
                    'voucher_no' => $voucherNo,
                    'voucher_type' => $data['voucher_type'],
                    'voucher_subtype' => $data['voucher_subtype'],
                    'branch_id' => $data['branch_id'],
                    'cash_box_id' => $data['cash_box_id'],
                    'payment_method_id' => $data['payment_method_id'],
                    'voucher_date' => $data['voucher_date'],
                    'partner_type' => $data['partner_type'],
                    'partner_id' => $data['partner_id'],
                    'total_amount' => $data['total_amount'],
                    'lines' => $data['lines'],
                ],
            ], function () use ($data, $cashBoxAccountId, $voucherId, $voucherNo): int {
                $journal = (new JournalManager($this->db))->create([
                    'branch_id' => $data['branch_id'],
                    'entry_date' => $data['voucher_date'],
                    'reference_type' => 'FINANCIAL_VOUCHER',
                    'reference_id' => $voucherId,
                    'description' => $voucherNo . ' — ' . $data['description'],
                    'created_by' => $data['created_by'],
                    'status' => 'POSTED',
                ]);

                $journal->addLine([
                    'account_id' => $cashBoxAccountId,
                    'branch_id' => $data['branch_id'],
                    'cash_box_id' => $data['cash_box_id'],
                    'debit' => $data['voucher_type'] === 'RECEIPT' ? $data['total_amount'] : 0,
                    'credit' => $data['voucher_type'] === 'PAYMENT' ? $data['total_amount'] : 0,
                    'note' => $voucherNo . ' — حركة الخزينة',
                ]);

                foreach ($data['lines'] as $line) {
                    $journal->addLine([
                        'account_id' => $line['account_id'],
                        'branch_id' => $data['branch_id'],
                        'cost_center_id' => $line['cost_center_id'],
                        'partner_type' => $line['partner_type'],
                        'partner_id' => $line['partner_id'],
                        'debit' => $data['voucher_type'] === 'PAYMENT' ? $line['amount'] : 0,
                        'credit' => $data['voucher_type'] === 'RECEIPT' ? $line['amount'] : 0,
                        'note' => $line['line_description'] !== '' ? $line['line_description'] : $data['description'],
                    ]);
                }

                $journalId = $journal->post();

                if (in_array($data['voucher_subtype'], ['CUSTOMER_RECEIPT', 'SUPPLIER_REFUND'], true)) {
                    $creditLine = $this->db->prepare(
                        'SELECT id FROM journal_entry_details WHERE journal_entry_id = ? AND line_no = 2 LIMIT 1'
                    );
                    $creditLine->execute([$journalId]);
                    $creditLineId = (int)$creditLine->fetchColumn();
                    $creditLine->closeCursor();
                    if ($creditLineId <= 0) {
                        throw new RuntimeException('تعذر تحديد سطر الطرف في قيد سند القبض للتخصيص.');
                    }

                    $this->allocateReceiptToOldestDebits(
                        $journalId,
                        $creditLineId,
                        $data['lines'][0]['account_id'],
                        (string)$data['partner_type'],
                        (int)$data['partner_id'],
                        (float)$data['total_amount'],
                        (int)$data['created_by'],
                        $data['voucher_subtype'] === 'CUSTOMER_RECEIPT',
                        $voucherNo
                    );
                }

                return $journalId;
            });

            $posted = $this->db->prepare(
                "UPDATE financial_vouchers
                 SET status = 'POSTED', journal_entry_id = ?, accounting_event_id = ?, posted_at = NOW()
                 WHERE id = ? AND status = 'PROCESSING'"
            );
            $posted->execute([$event['journal_entry_id'], $event['event_id'], $voucherId]);
            if ($posted->rowCount() !== 1) {
                throw new RuntimeException('تعذر اعتماد مستند القبض/الصرف بعد ترحيل القيد.');
            }

            return [
                'voucher_id' => $voucherId,
                'voucher_no' => $voucherNo,
                'journal_entry_id' => (int)$event['journal_entry_id'],
                'event_id' => (int)$event['event_id'],
                'status' => 'POSTED',
                'already_posted' => !empty($event['already_posted']),
            ];
        });
    }

    public function reverse(int $voucherId, string $reason, int $reversedBy): array
    {
        if ($voucherId <= 0 || trim($reason) === '' || $reversedBy <= 0) {
            throw new InvalidArgumentException('المستند وسبب العكس والمستخدم مطلوبة.');
        }

        return $this->withinTransaction(function () use ($voucherId, $reason, $reversedBy): array {
            $stmt = $this->db->prepare('SELECT * FROM financial_vouchers WHERE id = ? LIMIT 1 FOR UPDATE');
            $stmt->execute([$voucherId]);
            $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            if (!$voucher) {
                throw new RuntimeException('مستند القبض/الصرف غير موجود.');
            }
            if ($voucher['status'] === 'REVERSED') {
                return [
                    'voucher_id' => $voucherId,
                    'reversal_journal_entry_id' => (int)$voucher['reversal_journal_entry_id'],
                    'status' => 'REVERSED',
                    'already_reversed' => true,
                ];
            }
            if ($voucher['status'] !== 'POSTED' || empty($voucher['journal_entry_id'])) {
                throw new RuntimeException('لا يمكن عكس مستند غير مرحل.');
            }

            $lineStmt = $this->db->prepare(
                'SELECT id FROM journal_entry_details
                 WHERE journal_entry_id = ? AND partner_type IS NOT NULL AND partner_id IS NOT NULL'
            );
            $lineStmt->execute([(int)$voucher['journal_entry_id']]);
            $partnerLineIds = array_map('intval', $lineStmt->fetchAll(PDO::FETCH_COLUMN));
            $lineStmt->closeCursor();

            foreach ($partnerLineIds as $lineId) {
                $allocations = $this->db->prepare(
                    "SELECT id FROM accounting_line_allocations
                     WHERE credit_line_id = ? AND status = 'ACTIVE'"
                );
                $allocations->execute([$lineId]);
                $allocationIds = array_map('intval', $allocations->fetchAll(PDO::FETCH_COLUMN));
                $allocations->closeCursor();
                foreach ($allocationIds as $allocationId) {
                    $this->partnerLedger->reverseAllocation(
                        $allocationId,
                        $reversedBy,
                        'عكس سند قبض/صرف ' . $voucher['voucher_no'] . ': ' . trim($reason)
                    );
                }
            }

            $event = $this->events->post([
                'idempotency_key' => 'FV-REV:' . $voucherId,
                'event_type' => 'FINANCIAL_VOUCHER_REVERSED',
                'source_type' => 'FINANCIAL_VOUCHER_REVERSAL',
                'source_id' => $voucherId,
                'branch_id' => (int)$voucher['branch_id'],
                'event_date' => date('Y-m-d H:i:s'),
                'created_by' => $reversedBy,
                'payload' => [
                    'voucher_id' => $voucherId,
                    'voucher_no' => (string)$voucher['voucher_no'],
                    'original_journal_entry_id' => (int)$voucher['journal_entry_id'],
                    'reason' => trim($reason),
                ],
            ], function () use ($voucher, $reason): int {
                return (new JournalManager($this->db))->reverse(
                    (int)$voucher['journal_entry_id'],
                    trim($reason)
                );
            });

            $update = $this->db->prepare(
                "UPDATE financial_vouchers
                 SET status = 'REVERSED', reversal_journal_entry_id = ?, reversal_event_id = ?,
                     reversed_by = ?, reversed_at = NOW(), reversal_reason = ?
                 WHERE id = ? AND status = 'POSTED'"
            );
            $update->execute([
                $event['journal_entry_id'],
                $event['event_id'],
                $reversedBy,
                trim($reason),
                $voucherId,
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('تعذر اعتماد عكس مستند القبض/الصرف.');
            }

            return [
                'voucher_id' => $voucherId,
                'reversal_journal_entry_id' => (int)$event['journal_entry_id'],
                'reversal_event_id' => (int)$event['event_id'],
                'status' => 'REVERSED',
                'already_reversed' => !empty($event['already_posted']),
            ];
        });
    }

    public function listGeneralAccounts(string $voucherType): array
    {
        $voucherType = strtoupper(trim($voucherType));
        $accountType = $voucherType === 'RECEIPT' ? 'REVENUE' : ($voucherType === 'PAYMENT' ? 'EXPENSE' : '');
        if ($accountType === '') {
            throw new InvalidArgumentException('نوع سند القبض/الصرف غير صحيح.');
        }
        $stmt = $this->db->prepare(
            'SELECT id, account_code, account_name
             FROM accounts
             WHERE account_type = ? AND is_active = 1 AND allow_posting = 1
             ORDER BY account_code'
        );
        $stmt->execute([$accountType]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listCashBoxes(int $branchId): array
    {
        return $this->cashBoxes->listForPaymentMethod($branchId, 1);
    }

    public function getPartnerBalance(string $partnerType, int $partnerId): float
    {
        $partnerType = strtoupper(trim($partnerType));
        $accountId = $partnerType === 'CLIENT'
            ? $this->accountSettings->customers()
            : ($partnerType === 'SUPPLIER' ? $this->accountSettings->suppliers() : 0);
        if ($accountId <= 0 || $partnerId <= 0) {
            throw new InvalidArgumentException('نوع الطرف أو رقمه غير صحيح.');
        }
        return $this->partnerLedger->getBalance($accountId, $partnerType, $partnerId);
    }

    public function listVouchers(int $branchId, int $limit = 100): array
    {
        if ($branchId <= 0) {
            throw new InvalidArgumentException('الفرع مطلوب لعرض المستندات.');
        }
        $limit = max(1, min(500, $limit));
        $stmt = $this->db->prepare(
            'SELECT v.id, v.voucher_no, v.voucher_type, v.voucher_subtype,
                    v.voucher_date, v.total_amount, v.status, v.description,
                    v.partner_type, v.partner_id, v.journal_entry_id,
                    COALESCE(NULLIF(cb.box_name, \'\'), NULLIF(cb.name, \'\')) AS cash_box_name
             FROM financial_vouchers v
             INNER JOIN cash_boxes cb ON cb.id = v.cash_box_id
             WHERE v.branch_id = ?
             ORDER BY v.voucher_date DESC, v.id DESC
             LIMIT ' . $limit
        );
        $stmt->execute([$branchId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function normalizeInput(array $input): array
    {
        $subtype = strtoupper(trim((string)($input['voucher_subtype'] ?? '')));
        $subtypes = [
            'CUSTOMER_RECEIPT' => ['RECEIPT', 'CLIENT'],
            'SUPPLIER_REFUND' => ['RECEIPT', 'SUPPLIER'],
            'GENERAL_RECEIPT' => ['RECEIPT', null],
            'GENERAL_PAYMENT' => ['PAYMENT', null],
        ];
        if (!isset($subtypes[$subtype])) {
            throw new InvalidArgumentException('نوع مستند القبض/الصرف غير مدعوم.');
        }

        $voucherType = $subtypes[$subtype][0];
        $partnerType = $subtypes[$subtype][1];
        $branchId = (int)($input['branch_id'] ?? 0);
        $cashBoxId = (int)($input['cash_box_id'] ?? 0);
        $paymentMethodId = (int)($input['payment_method_id'] ?? 1);
        $createdBy = (int)($input['created_by'] ?? 0);
        $voucherDate = $this->normalizeDate((string)($input['voucher_date'] ?? date('Y-m-d H:i:s')));
        $description = trim((string)($input['description'] ?? ''));
        $idempotencyKey = trim((string)($input['idempotency_key'] ?? ''));

        if ($branchId <= 0 || $cashBoxId <= 0 || $createdBy <= 0) {
            throw new InvalidArgumentException('الفرع والخزينة والمستخدم مطلوبة.');
        }
        if ($paymentMethodId !== 1) {
            throw new InvalidArgumentException('القبض/الصرف النقدي يستخدم طريقة الدفع النقدي فقط حتى اكتمال الحسابات البنكية.');
        }
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 110) {
            throw new InvalidArgumentException('مفتاح منع التكرار مطلوب وبحد أقصى 110 أحرف.');
        }
        if ($description === '' || mb_strlen($description) > 500) {
            throw new InvalidArgumentException('وصف المستند مطلوب وبحد أقصى 500 حرف.');
        }

        $partnerId = null;
        $lines = [];
        if ($subtype === 'CUSTOMER_RECEIPT' || $subtype === 'SUPPLIER_REFUND') {
            $partnerId = (int)($input['partner_id'] ?? 0);
            $amount = $this->normalizeAmount($input['amount'] ?? 0);
            $accountId = $subtype === 'CUSTOMER_RECEIPT'
                ? $this->accountSettings->customers()
                : $this->accountSettings->suppliers();
            $lines[] = [
                'account_id' => $accountId,
                'partner_type' => $partnerType,
                'partner_id' => $partnerId,
                'cost_center_id' => null,
                'amount' => $amount,
                'line_description' => $description,
            ];
        } else {
            $inputLines = $input['lines'] ?? [];
            if (!is_array($inputLines) || count($inputLines) < 1 || count($inputLines) > 20) {
                throw new InvalidArgumentException('يجب إضافة بند واحد على الأقل وبحد أقصى 20 بندًا.');
            }
            foreach ($inputLines as $line) {
                if (!is_array($line)) {
                    throw new InvalidArgumentException('أحد بنود المستند غير صحيح.');
                }
                $lines[] = [
                    'account_id' => (int)($line['account_id'] ?? 0),
                    'partner_type' => null,
                    'partner_id' => null,
                    'cost_center_id' => !empty($line['cost_center_id']) ? (int)$line['cost_center_id'] : null,
                    'amount' => $this->normalizeAmount($line['amount'] ?? 0),
                    'line_description' => trim((string)($line['line_description'] ?? $description)),
                ];
            }
        }

        $totalAmount = round(array_sum(array_column($lines, 'amount')), 2);
        if ($totalAmount <= 0) {
            throw new InvalidArgumentException('إجمالي المستند يجب أن يكون أكبر من صفر.');
        }

        return [
            'voucher_type' => $voucherType,
            'voucher_subtype' => $subtype,
            'branch_id' => $branchId,
            'cash_box_id' => $cashBoxId,
            'payment_method_id' => $paymentMethodId,
            'voucher_date' => $voucherDate,
            'partner_type' => $partnerType,
            'partner_id' => $partnerId,
            'total_amount' => $totalAmount,
            'description' => $description,
            'idempotency_key' => $idempotencyKey,
            'created_by' => $createdBy,
            'lines' => $lines,
            'payload' => [
                'voucher_type' => $voucherType,
                'voucher_subtype' => $subtype,
                'branch_id' => $branchId,
                'cash_box_id' => $cashBoxId,
                'payment_method_id' => $paymentMethodId,
                'voucher_date' => $voucherDate,
                'partner_type' => $partnerType,
                'partner_id' => $partnerId,
                'total_amount' => $totalAmount,
                'description' => $description,
                'lines' => $lines,
            ],
        ];
    }

    private function validateCounterpartLines(array $data): void
    {
        $expectedType = $data['voucher_subtype'] === 'GENERAL_RECEIPT'
            ? 'REVENUE'
            : ($data['voucher_subtype'] === 'GENERAL_PAYMENT' ? 'EXPENSE' : null);

        $account = $this->db->prepare(
            'SELECT account_type, is_active, allow_posting FROM accounts WHERE id = ? LIMIT 1'
        );
        foreach ($data['lines'] as $line) {
            if ($line['account_id'] <= 0 || $line['amount'] <= 0) {
                throw new InvalidArgumentException('حساب ومبلغ كل بند مطلوبان.');
            }
            $account->execute([$line['account_id']]);
            $row = $account->fetch(PDO::FETCH_ASSOC);
            if (!$row || (int)$row['is_active'] !== 1 || (int)$row['allow_posting'] !== 1) {
                throw new RuntimeException('أحد حسابات بنود المستند غير موجود أو غير نشط أو غير قابل للترحيل.');
            }
            if ($expectedType !== null && $row['account_type'] !== $expectedType) {
                throw new RuntimeException(
                    $data['voucher_subtype'] === 'GENERAL_RECEIPT'
                        ? 'سند القبض العام يقبل حسابات الإيرادات فقط.'
                        : 'سند الصرف العام يقبل حسابات المصروفات فقط.'
                );
            }
        }
        $account->closeCursor();

        if (in_array($data['voucher_subtype'], ['CUSTOMER_RECEIPT', 'SUPPLIER_REFUND'], true)) {
            if (count($data['lines']) !== 1 || empty($data['partner_id'])) {
                throw new InvalidArgumentException('سند الطرف يجب أن يحتوي بندًا واحدًا وطرفًا محددًا.');
            }
        }
    }

    private function getCashBoxAccountId(int $branchId, int $cashBoxId): int
    {
        foreach ($this->cashBoxes->listForPaymentMethod($branchId, 1) as $box) {
            if ((int)$box['id'] === $cashBoxId) {
                return (int)$box['account_id'];
            }
        }
        throw new RuntimeException('الخزينة المحددة غير نشطة أو غير مربوطة بطريقة الدفع النقدي لهذا الفرع.');
    }

    private function getOpenDebitAmount(int $accountId, string $partnerType, int $partnerId): float
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(open_amount), 0)
             FROM (
                SELECT d.id, GREATEST(d.debit - COALESCE(SUM(a.amount), 0), 0) AS open_amount
                FROM journal_entry_details d
                INNER JOIN journal_entries h ON h.id = d.journal_entry_id AND h.status = 'POSTED'
                LEFT JOIN accounting_line_allocations a ON a.debit_line_id = d.id AND a.status = 'ACTIVE'
                WHERE d.account_id = ? AND d.partner_type = ? AND d.partner_id = ?
                  AND d.debit > 0 AND d.credit = 0
                GROUP BY d.id, d.debit
             ) open_lines"
        );
        $stmt->execute([$accountId, $partnerType, $partnerId]);
        return round((float)$stmt->fetchColumn(), 2);
    }

    private function allocateReceiptToOldestDebits(
        int $journalId,
        int $creditLineId,
        int $accountId,
        string $partnerType,
        int $partnerId,
        float $amount,
        int $createdBy,
        bool $allowCustomerCredit,
        string $voucherNo
    ): void {
        $stmt = $this->db->prepare(
            "SELECT d.id, d.debit, COALESCE(SUM(a.amount), 0) AS allocated_amount
             FROM journal_entry_details d
             INNER JOIN journal_entries h ON h.id = d.journal_entry_id AND h.status = 'POSTED'
             LEFT JOIN accounting_line_allocations a ON a.debit_line_id = d.id AND a.status = 'ACTIVE'
             WHERE d.account_id = ? AND d.partner_type = ? AND d.partner_id = ?
               AND d.debit > 0 AND d.credit = 0
             GROUP BY d.id, d.debit, h.entry_date, h.id, d.line_no
             HAVING d.debit > COALESCE(SUM(a.amount), 0)
             ORDER BY h.entry_date, h.id, d.line_no, d.id
             FOR UPDATE"
        );
        $stmt->execute([$accountId, $partnerType, $partnerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $remaining = round($amount, 2);

        foreach ($rows as $row) {
            if ($remaining <= 0) break;
            $available = round((float)$row['debit'] - (float)$row['allocated_amount'], 2);
            if ($available <= 0) continue;
            $allocationAmount = min($available, $remaining);
            $this->partnerLedger->allocate(
                (int)$row['id'],
                $creditLineId,
                $allocationAmount,
                $createdBy,
                'تخصيص تلقائي حسب الأقدم — ' . $voucherNo
            );
            $remaining = round($remaining - $allocationAmount, 2);
        }

        if (!$allowCustomerCredit && $remaining > 0.009) {
            throw new RuntimeException('تعذر تخصيص كامل رد المورد إلى مردودات/أرصدة مدينة مفتوحة. لم يتم ترحيل السند.');
        }
    }

    private function findByIdempotencyKey(string $key): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, voucher_no, payload_hash, status, journal_entry_id
             FROM financial_vouchers WHERE idempotency_key = ? LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $stmt->closeCursor();
        return $row;
    }

    private function normalizeDate(string $date): string
    {
        $date = trim($date);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date .= ' 00:00:00';
        }
        $parsed = DateTime::createFromFormat('Y-m-d H:i:s', $date);
        if (!$parsed || $parsed->format('Y-m-d H:i:s') !== $date) {
            throw new InvalidArgumentException('تاريخ المستند يجب أن يكون بصيغة YYYY-MM-DD أو YYYY-MM-DD HH:MM:SS.');
        }
        return $date;
    }

    private function normalizeAmount($value): float
    {
        if (!is_numeric($value) || !is_finite((float)$value)) {
            throw new InvalidArgumentException('المبلغ يجب أن يكون رقمًا صالحًا.');
        }
        $amount = round((float)$value, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('المبلغ يجب أن يكون أكبر من صفر.');
        }
        return $amount;
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

    private function canonicalJson(array $value): string
    {
        $normalize = function ($item) use (&$normalize) {
            if (!is_array($item)) return $item;
            if (array_keys($item) !== range(0, count($item) - 1)) ksort($item);
            foreach ($item as $key => $child) $item[$key] = $normalize($child);
            return $item;
        };
        $json = json_encode($normalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false) throw new InvalidArgumentException('تعذر تجهيز بصمة بيانات المستند.');
        return $json;
    }
}
