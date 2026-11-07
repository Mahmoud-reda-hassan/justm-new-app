<?php

class JournalManager{
	public $db;
	public $header;
	public $lines;
	
   /*****************************************************
	 * إنشاء الكلاس
	 *****************************************************/
	public function __construct($db){
	    $this->db = $db;
	    $this->header = [];
	    $this->lines = [];
	}
	
	
	/*****************************************************
	 * إنشاء قيد جديد
	 *****************************************************/
	public function create(array $data): self{
	    $this->reset();
	
	    $this->header = [
	
	        'branch_id'      => (int)$data['branch_id'],
	        'entry_date'     => $data['entry_date'] ?? date('Y-m-d H:i:s'),
	        'reference_type' => $data['reference_type'] ?? null,
	        'reference_id'   => $data['reference_id'] ?? null,
	        'description'    => $data['description'] ?? '',
	        'created_by'     => (int)$data['created_by'],
	        'status'         => $data['status'] ?? 'POSTED'
	
	    ];
	    
	    return $this;
	}
	
	
	/*****************************************************
	 * إعادة تهيئة الكلاس
	 *****************************************************/
	public function reset(): self{
	    $this->header = [];
	    $this->lines  = [];
	
	    return $this;
	}
	
	/*****************************************************
	 * تحميل قيد موجود
	 *****************************************************/
	public function load(int $journalId): self{
	    $this->reset();
	
	    $stmt = $this->db->prepare("
	        SELECT *
	        FROM journal_entries
	        WHERE id = ?
	        LIMIT 1
	    ");
	
	    $stmt->execute([$journalId]);
	
	    $header = $stmt->fetch(PDO::FETCH_ASSOC);
	
	    if (!$header)
	        throw new Exception("Journal entry not found.");
	
	    $this->header = $header;
	
	    $stmt = $this->db->prepare("
	        SELECT *
	        FROM journal_entry_details
	        WHERE journal_entry_id = ?
	        ORDER BY id
	    ");
	
	    $stmt->execute([$journalId]);
	
	    $this->lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
	    return $this;
	}
	
	
	/*****************************************************
	 * تعديل تاريخ القيد
	 *****************************************************/
	public function setEntryDate(string $date): self{
	    $this->header['entry_date'] = $date;
	
	    return $this;
	}
	
	
	/*****************************************************
	 * تعديل الفرع
	 *****************************************************/
	public function setBranch(int $branchId): self{
	    $this->header['branch_id'] = $branchId;
	
	    return $this;
	}
	
	/*****************************************************
	 * تحديد المرجع
	 *****************************************************/
	public function setReference(
	    string $referenceType,
	    int $referenceId
	): self {
	    $this->header['reference_type'] = strtoupper($referenceType);
	    $this->header['reference_id']   = $referenceId;
	
	    return $this;
	}
	
	
	/*****************************************************
	 * تعديل وصف القيد
	 *****************************************************/
	public function setDescription(
	    string $description
	): self {
	    $this->header['description'] = trim($description);
	
	    return $this;
	}
	
	
	/*****************************************************
	 * إضافة سطر قيد
	 *****************************************************/
	/*public function addLine(
	    int $accountId,
	    float $debit = 0,
	    float $credit = 0,
	    ?int $branchId = null,
	    ?int $storeId = null,
	    ?int $costCenterId = null,
	    ?string $partnerType = null,
	    ?int $partnerId = null,
	    string $note = ''
	): self {
	    if ($debit < 0 || $credit < 0)
	        throw new Exception("Amount cannot be negative.");
	
	    if ($debit == 0 && $credit == 0)
	        throw new Exception("Debit or Credit is required.");
	
	    if ($debit > 0 && $credit > 0)
	        throw new Exception("Only Debit or Credit is allowed.");
	
	    $this->lines[] = [
	
	        'account_id'      => $accountId,
	        'branch_id'       => $branchId,
	        'store_id'        => $storeId,
	        'cost_center_id'  => $costCenterId,
	        'partner_type'    => $partnerType,
	        'partner_id'      => $partnerId,
	        'debit'           => round($debit, 2),
	        'credit'          => round($credit, 2),
	        'note'            => trim($note)
	
	    ];
	
	    return $this;
	}*/
	public function addLine(array $line): self{
	    $line = array_merge([
	        'account_id'     => null,
	        'branch_id'      => null,
	        'cash_box_id'    => null,
	        'store_id'       => null,
	        'cost_center_id' => null,
	        'partner_type'   => null,
	        'partner_id'     => null,
	        'reference_type' => null,
	        'reference_id'   => null,
	        'debit'          => 0,
	        'credit'         => 0,
	        'note'           => '',
	    ], $line);
	
	    if (empty($line['account_id'])) {
	        throw new Exception("Account is required.");
	    }
	
	    if ($line['debit'] < 0 || $line['credit'] < 0) {
	        throw new Exception("Amount cannot be negative.");
	    }
	
	    if ($line['debit'] == 0 && $line['credit'] == 0) {
	        throw new Exception("Debit or Credit is required.");
	    }
	
	    if ($line['debit'] > 0 && $line['credit'] > 0) {
	        throw new Exception("Only Debit or Credit is allowed.");
	    }
	
	    $line['debit']  = round((float)$line['debit'], 2);
	    $line['credit'] = round((float)$line['credit'], 2);
	    $line['note']   = trim((string)$line['note']);
	
	    $this->lines[] = $line;
	
	    return $this;
	}
	
	/*****************************************************
	 * تعديل سطر قيد
	 *****************************************************/
	public function updateLine(
	    int $lineIndex,
	    array $data
	): self {
	    if (!isset($this->lines[$lineIndex]))
	        throw new Exception("Journal line not found.");
	
	    $this->lines[$lineIndex] = array_merge(
	        $this->lines[$lineIndex],
	        $data
	    );
	
	    return $this;
	}
	
	
	/*****************************************************
	 * حذف سطر قيد
	 *****************************************************/
	public function removeLine(
	    int $lineIndex
	): self {
	    if (!isset($this->lines[$lineIndex]))
	        throw new Exception("Journal line not found.");
	
	    unset($this->lines[$lineIndex]);
	
	    $this->lines = array_values($this->lines);
	
	    return $this;
	}
	
	
	/*****************************************************
	 * حذف جميع السطور
	 *****************************************************/
	public function clearLines(): self {
	    $this->lines = [];
	
	    return $this;
	}
	
	/*****************************************************
	 * حفظ القيد
	 *****************************************************/
	public function post(): int {
		if (empty($this->lines)) {
		    throw new Exception("Journal entry has no lines.");
		}
		
	    $this->validate();

	    try {
	
	        $ownTransaction = false;

			if (!$this->db->inTransaction()) {
			    $this->db->beginTransaction();
			    $ownTransaction = true;
			}
	
	        // حفظ رأس القيد
	        $journalId = $this->saveHeader();
			
			$entryNo = $this->generateEntryNumber($journalId);
			$stmt = $this->db->prepare("
			    UPDATE journal_entries
			       SET entry_no = ?
			     WHERE id = ?
			");
			
			$stmt->execute([
			    $entryNo,
			    $journalId
			]);
	        // حفظ السطور
	        $this->saveLines($journalId);
	
	        if ($ownTransaction) {
			    $this->db->commit();
			}
	
	        $this->reset();
	        return $journalId;
	
	    } catch (Throwable $e) {
			if ($ownTransaction && $this->db->inTransaction()) {
			    $this->db->rollBack();
			}
	
	        throw $e;
	    }
	}
	
	/*****************************************************
	 * عكس قيد
	 *****************************************************/
	public function reverse(
	    int $journalId,
	    string $reason = '',
	    ?string $coordinatedSupplierReference = null): int {
	    if ($journalId <= 0) {
	        throw new InvalidArgumentException("Journal ID is required.");
	    }

	    // قفل القيد الأصلي يمنع طلبين متزامنين من إنشاء عكسين لنفس القيد.
	    $ownsTransaction = !$this->db->inTransaction();
	    if ($ownsTransaction) {
	        $this->db->beginTransaction();
	    }

	    try {
	        $lock = $this->db->prepare("
	            SELECT id, branch_id, created_by, status, reference_type
	            FROM journal_entries
	            WHERE id = ?
	            LIMIT 1
	            FOR UPDATE
	        ");
	        $lock->execute([$journalId]);
	        $original = $lock->fetch(PDO::FETCH_ASSOC);
	        if (!$original) {
	            throw new Exception("Journal entry not found.");
	        }
	        if ($original['status'] !== 'POSTED') {
	            throw new Exception("Only a posted journal entry can be reversed.");
	        }
	        if (in_array((string)$original['reference_type'], ['SUPPLIER_PAYMENT', 'SUPPLIER_ADVANCE_APPLICATION'], true)
                && $coordinatedSupplierReference !== (string)$original['reference_type']) {
	            throw new RuntimeException('لا يمكن عكس دفعات المورد أو تطبيق مقدماته بالعكس العام؛ يجب استخدام مسار عكس مخصص يحدّث الأستاذ العام وأرصدة أوامر الشراء وسجلات التخصيص معًا.');
	        }

	        $existing = $this->db->prepare("
	            SELECT id
	            FROM journal_entries
	            WHERE reference_type = 'JOURNAL_REVERSE'
	              AND reference_id = ?
	            LIMIT 1
	        ");
	        $existing->execute([$journalId]);
	        if ($existing->fetchColumn()) {
	            throw new Exception("Journal entry has already been reversed.");
	        }

	        $this->load($journalId);
	        $header = $this->header;
	        $lines = $this->lines;
	        $this->reset();

	        $this->create([
	            'branch_id'      => (int)$header['branch_id'],
	            'entry_date'     => date('Y-m-d H:i:s'),
	            'reference_type' => 'JOURNAL_REVERSE',
	            'reference_id'   => $journalId,
	            'description'    => 'Reverse Entry : ' . $reason,
	            'created_by'     => (int)$header['created_by']
	        ]);

	        foreach ($lines as $line) {
	            $this->addLine([
	                'account_id'     => (int)$line['account_id'],
	                'branch_id'      => $line['branch_id'] ?? null,
	                'cash_box_id'    => $line['cash_box_id'] ?? null,
	                'store_id'       => $line['store_id'] ?? null,
	                'cost_center_id' => $line['cost_center_id'] ?? null,
	                'partner_type'   => $line['partner_type'] ?? null,
	                'partner_id'     => $line['partner_id'] ?? null,
	                'reference_type' => $line['reference_type'] ?? null,
	                'reference_id'   => $line['reference_id'] ?? null,
	                'debit'          => (float)$line['credit'],
	                'credit'         => (float)$line['debit'],
	                'note'           => $line['note'] ?? '',
	            ]);
	        }

	        $reversalId = $this->post();

	        $markOriginal = $this->db->prepare(
	            "UPDATE journal_entries SET status = 'REVERSED'
	             WHERE id = ? AND status = 'POSTED'"
	        );
	        $markOriginal->execute([$journalId]);
	        if ($markOriginal->rowCount() !== 1) {
	            throw new Exception("Original journal entry status changed during reversal.");
	        }

	        if ($ownsTransaction && $this->db->inTransaction()) {
	            $this->db->commit();
	        }
	        return $reversalId;
	    } catch (Throwable $e) {
	        if ($ownsTransaction && $this->db->inTransaction()) {
	            $this->db->rollBack();
	        }
	        $this->reset();
	        throw $e;
	    }
	}

	/*****************************************************
	 * البحث عن قيد
	 *****************************************************/
	public function find(
	    int $journalId
	): array {
	    $this->load($journalId);
	
	    return [
	
	        'header' => $this->header,
	        'lines'  => $this->lines
	
	    ];
	}
	
	/*****************************************************
	 * إرجاع بيانات رأس القيد
	 *****************************************************/
	public function getHeader(): array {
	    return $this->header;
	}
	
	
	/*****************************************************
	 * إرجاع سطور القيد
	 *****************************************************/
	public function getLines(): array {
	    return $this->lines;
	}
	
	
	/*****************************************************
	 * التحقق من صحة القيد
	 *****************************************************/
	protected function validate(): void {
	    $this->validateHeader();
	
	    $this->validateLines();
	
	    $this->validateAccounts();
	
	    $this->validatePartners();
	
	    $this->validateBalance();
	}
	
	/*****************************************************
	 * التحقق من بيانات رأس القيد
	 *****************************************************/
	protected function validateHeader(): void {
	    if (empty($this->header))
	        throw new Exception("Journal header is missing.");
	
	    if (empty($this->header['branch_id']))
	        throw new Exception("Branch is required.");
	
	    if (empty($this->header['entry_date']))
	        throw new Exception("Entry date is required.");
	
	    if (empty($this->header['created_by']))
	        throw new Exception("Created by is required.");
	}
	
	
	/*****************************************************
	 * التحقق من سطور القيد
	 *****************************************************/
	protected function validateLines(): void {
	    if (count($this->lines) < 2)
	        throw new Exception("Journal entry must contain at least two lines.");
	
	    foreach ($this->lines as $index => $line) {
	
	        if (empty($line['account_id']))
	            throw new Exception("Account is required at line ".($index + 1));
	
	        if (
	            ($line['debit'] <= 0 && $line['credit'] <= 0) ||
	            ($line['debit'] > 0 && $line['credit'] > 0)
	        )
	            throw new Exception("Invalid debit/credit at line ".($index + 1));
	
	    
	        if (!empty($line['cash_box_id'])) {
	            $boxStmt = $this->db->prepare(
	                "SELECT cb.account_id
	                 FROM cash_boxes cb
	                 INNER JOIN accounts a ON a.id = cb.account_id
	                 WHERE cb.id = ? AND cb.branch_id = ? AND cb.is_active = 1
	                   AND a.is_active = 1 AND a.allow_posting = 1
	                 LIMIT 1"
	            );
	            $boxStmt->execute([(int)$line['cash_box_id'], (int)($line['branch_id'] ?: $this->header['branch_id'])]);
	            $boxAccountId = $boxStmt->fetchColumn();
	            if (!$boxAccountId || (int)$boxAccountId !== (int)$line['account_id']) {
	                throw new RuntimeException('الخزينة المحددة غير نشطة أو لا تتبع الفرع أو لا تطابق حساب سطر القيد.');
	            }
	        }
}
	}
	
	
	/*****************************************************
	 * التحقق من الحسابات
	 *****************************************************/
	protected function validateAccounts(): void {
	    $stmt = $this->db->prepare("
	        SELECT
	            is_active,
	            allow_posting
	        FROM accounts
	        WHERE id = ?
	        LIMIT 1
	    ");
	
	    foreach ($this->lines as $line) {
	
	        $stmt->execute([$line['account_id']]);
	
	        $account = $stmt->fetch(PDO::FETCH_ASSOC);
	
	        if (!$account)
	            throw new Exception("Account not found.");
	
	        if (!$account['is_active'])
	            throw new Exception("Account is inactive.");
	
	        if (!$account['allow_posting'])
	            throw new Exception("Posting is not allowed for this account.");
	    }
	}
	
	/*****************************************************
	 * التحقق من الشريك
	 *****************************************************/
	protected function validatePartners(): void {
	    $tables = [
	
	        'CLIENT'    => 'client',
	        'SUPPLIER'  => 'suppliers',
	        'DELIVERY'  => 'users',
	        'MARKETER'  => 'users',
	        'EMPLOYEE'  => 'users'
	
	    ];
	    
	    $column = [
	
	        'CLIENT'    => 'CLIENT_ID',
	        'SUPPLIER'  => 'SUP_ID',
	        'DELIVERY'  => 'USER_ID',
	        'MARKETER'  => 'USER_ID',
	        'EMPLOYEE'  => 'USER_ID'
	
	    ];
	
	    foreach ($this->lines as $line) {
	
	        if (empty($line['partner_type']) || empty($line['partner_id']))
	            continue;
	
	        if (!isset($tables[$line['partner_type']]))
	            throw new Exception("Invalid partner type.");
	
	        $stmt = $this->db->prepare("
	            SELECT 1
	            FROM {$tables[$line['partner_type']]}
	            WHERE {$column[$line['partner_type']]} = ?
	            LIMIT 1
	        ");
	
	        $stmt->execute([
	            $line['partner_id']
	        ]);
	
	        if (!$stmt->fetchColumn())
	            throw new Exception("Partner not found.");
	    }
	}
	
	
	/*****************************************************
	 * التحقق من توازن القيد
	 *****************************************************/
	protected function validateBalance(): void {
	    $debit  = 0;
	    $credit = 0;
	
	    foreach ($this->lines as $line) {
	
	        $debit  += (float)$line['debit'];
	        $credit += (float)$line['credit'];
	
	    }
	
	    if (round($debit, 2) != round($credit, 2))
	        throw new Exception("Journal entry is not balanced.");
	}
	
	
	/*****************************************************
	 * حفظ رأس القيد
	 *****************************************************/
	protected function saveHeader(): int {
	
	    $entryNo = null;
	
	    $fiscalYear = $this->getFiscalYear(
	        $this->header['entry_date']
	    );
	
	    // حساب الإجماليات
	    $totalDebit  = 0;
	    $totalCredit = 0;
	
	    foreach ($this->lines as $line) {
	        $totalDebit  += $line['debit'];
	        $totalCredit += $line['credit'];
	    }
	
	    $stmt = $this->db->prepare("
	
	        INSERT INTO journal_entries
	        (
	
	            entry_no,
	            fiscal_year,
	            branch_id,
	            entry_date,
	            reference_type,
	            reference_id,
	            description,
	            total_debit,
	            total_credit,
	            status,
	            posted_at,
	            posted_by,
	            created_by
	
	        )
	
	        VALUES
	
	        (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
	
	    ");
	
	    $stmt->execute([
	
	        $entryNo,
	        $fiscalYear,
	        $this->header['branch_id'],
	        $this->header['entry_date'],
	        $this->header['reference_type'],
	        $this->header['reference_id'],
	        $this->header['description'],
	        $this->getTotalDebit(),
	        $this->getTotalCredit(),
	        $this->header['status'],
	        $this->header['status'] === 'POSTED' ? date('Y-m-d H:i:s') : null,
	        $this->header['status'] === 'POSTED' ? $this->header['created_by'] : null,
	        $this->header['created_by']
	
	    ]);
	
	    return (int)$this->db->lastInsertId();
	}	
	/*****************************************************
	 * حفظ سطور القيد
	 *****************************************************/
	protected function saveLines(int $journalId): void{
	
	    $stmt = $this->db->prepare("
	
	        INSERT INTO journal_entry_details
	        (
	
	            journal_entry_id,
	            line_no,
	            account_id,
	            branch_id,
	            cash_box_id,
	            store_id,
	            cost_center_id,
	            partner_type,
	            partner_id,
	            reference_type,
	            reference_id,
	            debit,
	            credit,
	            note
	
	        )
	
	        VALUES
	
	        (?,?,?,?,?,?,?,?,?,?,?,?,?)
	
	    ");
	
	    $lineNo = 1;
	
	    foreach ($this->lines as $line){
	
	        $stmt->execute([
	
	            $journalId,
	            $lineNo,
	            $line['account_id'],
	            $line['branch_id'],
	            $line['cash_box_id'],
	            $line['store_id'],
	            $line['cost_center_id'],
	            $line['partner_type'],
	            $line['partner_id'],
	            $line['reference_type'],
	            $line['reference_id'],
	            $line['debit'],
	            $line['credit'],
	            $line['note']
	
	        ]);
	
	        $lineNo++;
	
	    }
	
	}
	
	/*****************************************************
	 * توليد رقم القيد
	 *****************************************************/
	protected function generateEntryNumber( int $journalId ): string{
	    return sprintf(
	        'JV-%04d-%06d',
	        $this->getFiscalYear($this->header['entry_date']),
	        $journalId
	    );
	}
		
	
	/*****************************************************
	 * الحصول على السنة المالية
	 * (إنشاؤها تلقائياً إذا لم تكن موجودة)
	 *****************************************************/
	protected function getFiscalYear(string $date): int{
	
	    $date = date('Y-m-d', strtotime($date));
	
	    // البحث عن السنة المالية
	    $stmt = $this->db->prepare("
	        SELECT
	            id,
	            is_closed
	        FROM financial_years
	        WHERE ?
	        BETWEEN date_from AND date_to
	        LIMIT 1
	    ");
	
	    $stmt->execute([$date]);
	
	    $year = $stmt->fetch(PDO::FETCH_ASSOC);
	
	    // إذا كانت موجودة
	    if ($year){
	
	        if ((int)$year['is_closed'] === 1){
	            throw new Exception("Financial year is closed.");
	        }
	
	        return (int)$year['id'];
	    }
	
	    // إنشاء سنة مالية جديدة
	    $yearName = date('Y', strtotime($date));
	
	    $stmt = $this->db->prepare("
	        INSERT INTO financial_years
	        (
	            year_name,
	            date_from,
	            date_to,
	            is_closed
	        )
	        VALUES
	        (
	            ?,
	            ?,
	            ?,
	            0
	        )
	    ");
	
	    $stmt->execute([
	        $yearName,
	        "{$yearName}-01-01",
	        "{$yearName}-12-31"
	    ]);
	
	    return (int)$this->db->lastInsertId();
	}
	
	/*****************************************************
	 * عدد السطور
	 *****************************************************/
	public function countLines(): int {
	    return count($this->lines);
	}
	
	
	/*****************************************************
	 * إجمالى المدين
	 *****************************************************/
	public function getTotalDebit(): float {
	    return array_sum(
	        array_column($this->lines,'debit')
	    );
	}
	
	
	/*****************************************************
	 * إجمالى الدائن
	 *****************************************************/
	public function getTotalCredit(): float {
	    return array_sum(
	        array_column($this->lines,'credit')
	    );
	}
	
	/*****************************************************
	 * هل القيد متزن
	 *****************************************************/
	public function isBalanced(): bool {
	    return round($this->getTotalDebit(),2)
	        ===
	        round($this->getTotalCredit(),2);
	}
	
	
	/*****************************************************
	 * هل يوجد سطور
	 *****************************************************/
	public function hasLines(): bool {
	    return !empty($this->lines);
	}
	
	
	/*****************************************************
	 * هل تم إنشاء رأس القيد
	 *****************************************************/
	public function hasHeader(): bool {
	    return !empty($this->header);
	}
	
}

?>