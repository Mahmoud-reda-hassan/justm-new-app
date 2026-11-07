<?php
declare(strict_types=1);

require_once __DIR__ . '/JournalManager.php';

/**
 * بوابة موحدة لترحيل الأحداث المالية مرة واحدة فقط.
 *
 * يجب أن ينفذ callback إنشاء القيد باستخدام نفس اتصال PDO؛
 * بذلك يدخل سجل الحدث والقيد في معاملة قاعدة بيانات واحدة.
 */
final class FinancialEventManager
{
    private PDO|TrimmedPDO $db;

    public function __construct(PDO|TrimmedPDO $db)
    {
        $this->db = $db;
    }

    /**
     * @param array $event
     *   idempotency_key, event_type, source_type, source_id,
     *   branch_id, event_date, created_by, payload
     * @param callable $postJournal ينشئ القيد ويرجع journal_entry_id.
     * @return array{event_id:int,journal_entry_id:int,already_posted:bool}
     */
    public function post(array $event, callable $postJournal): array
    {
        $data = $this->validateEvent($event);
        $payloadHash = hash('sha256', $this->canonicalJson($data['payload']));

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $inserted = true;
            $insertException = null;
            try {
                $insert = $this->db->prepare(
                    "INSERT INTO accounting_events
                        (idempotency_key, event_type, source_type, source_id,
                         branch_id, event_date, payload_hash, status, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'PROCESSING', ?)"
                );
                $insert->execute([
                    $data['idempotency_key'],
                    $data['event_type'],
                    $data['source_type'],
                    $data['source_id'],
                    $data['branch_id'],
                    $data['event_date'],
                    $payloadHash,
                    $data['created_by'],
                ]);
                $eventId = (int)$this->db->lastInsertId();
            } catch (PDOException $e) {
                if ((string)$e->getCode() !== '23000') {
                    throw $e;
                }
                $inserted = false;
                $insertException = $e;
                $eventId = 0;
            }

            if (!$inserted) {
                $existingStmt = $this->db->prepare(
                    "SELECT id, event_type, source_type, source_id, branch_id,
                            payload_hash, status, journal_entry_id
                     FROM accounting_events
                     WHERE idempotency_key = ?
                     LIMIT 1
                     FOR UPDATE"
                );
                $existingStmt->execute([$data['idempotency_key']]);
                $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

                if (!$existing) {
                    // قد يكون SQLSTATE 23000 ناتجًا عن قيد آخر، وليس تكرار مفتاح الحدث.
                    // إذا لم نجد المفتاح نفسه، أعد خطأ قاعدة البيانات الأصلي دون إخفائه.
                    if ($insertException instanceof PDOException) {
                        throw $insertException;
                    }
                    throw new RuntimeException('تعذر التحقق من سبب تعارض سجل الحدث المالي.');
                }
                if (
                    $existing['event_type'] !== $data['event_type'] ||
                    $existing['source_type'] !== $data['source_type'] ||
                    (int)$existing['source_id'] !== $data['source_id'] ||
                    (int)$existing['branch_id'] !== $data['branch_id'] ||
                    !hash_equals((string)$existing['payload_hash'], $payloadHash)
                ) {
                    throw new RuntimeException('مفتاح منع التكرار مستخدم لحدث مالي ببيانات مختلفة.');
                }
                if ($existing['status'] !== 'POSTED' || empty($existing['journal_entry_id'])) {
                    throw new RuntimeException('يوجد حدث مالي غير مكتمل؛ يلزم فحصه قبل إعادة الترحيل.');
                }

                if ($ownsTransaction && $this->db->inTransaction()) {
                    $this->db->commit();
                }
                return [
                    'event_id' => (int)$existing['id'],
                    'journal_entry_id' => (int)$existing['journal_entry_id'],
                    'already_posted' => true,
                ];
            }

            $journalId = (int)$postJournal();
            if ($journalId <= 0) {
                throw new RuntimeException('لم تُرجع عملية إنشاء القيد رقم قيد صالحًا.');
            }

            $journalStmt = $this->db->prepare(
                "SELECT id, status FROM journal_entries WHERE id = ? LIMIT 1 FOR UPDATE"
            );
            $journalStmt->execute([$journalId]);
            $journal = $journalStmt->fetch(PDO::FETCH_ASSOC);
            if (!$journal || $journal['status'] !== 'POSTED') {
                throw new RuntimeException('القيد الناتج غير موجود أو لم يتم ترحيله.');
            }

            $update = $this->db->prepare(
                "UPDATE accounting_events
                 SET status = 'POSTED', journal_entry_id = ?, posted_at = NOW()
                 WHERE id = ? AND status = 'PROCESSING'"
            );
            $update->execute([$journalId, $eventId]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('تعذر اعتماد سجل الحدث المالي بعد إنشاء القيد.');
            }

            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }

            return [
                'event_id' => $eventId,
                'journal_entry_id' => $journalId,
                'already_posted' => false,
            ];
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function validateEvent(array $event): array
    {
        $required = ['idempotency_key', 'event_type', 'source_type', 'source_id',
            'branch_id', 'event_date', 'created_by', 'payload'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $event)) {
                throw new InvalidArgumentException('بيانات الحدث المالي ناقصة: ' . $key);
            }
        }

        $data = [
            'idempotency_key' => trim((string)$event['idempotency_key']),
            'event_type' => strtoupper(trim((string)$event['event_type'])),
            'source_type' => strtoupper(trim((string)$event['source_type'])),
            'source_id' => (int)$event['source_id'],
            'branch_id' => (int)$event['branch_id'],
            'event_date' => trim((string)$event['event_date']),
            'created_by' => (int)$event['created_by'],
            'payload' => $event['payload'],
        ];

        if ($data['idempotency_key'] === '' || strlen($data['idempotency_key']) > 128) {
            throw new InvalidArgumentException('مفتاح منع التكرار مطلوب وبحد أقصى 128 حرفًا.');
        }
        foreach (['event_type', 'source_type'] as $key) {
            if ($data[$key] === '' || strlen($data[$key]) > 60) {
                throw new InvalidArgumentException('نوع الحدث أو المصدر غير صالح.');
            }
        }
        if ($data['source_id'] <= 0 || $data['branch_id'] <= 0 || $data['created_by'] <= 0) {
            throw new InvalidArgumentException('معرف المصدر والفرع والمستخدم يجب أن تكون أرقامًا موجبة.');
        }
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $data['event_date']);
        if (!$date || $date->format('Y-m-d H:i:s') !== $data['event_date']) {
            throw new InvalidArgumentException('تاريخ الحدث يجب أن يكون بصيغة YYYY-MM-DD HH:MM:SS.');
        }
        if (!is_array($data['payload'])) {
            throw new InvalidArgumentException('تفاصيل الحدث المالي يجب أن تكون مصفوفة.');
        }

        return $data;
    }

    private function canonicalJson(array $value): string
    {
        $normalize = function ($item) use (&$normalize) {
            if (!is_array($item)) {
                return $item;
            }
            if (array_keys($item) !== range(0, count($item) - 1)) {
                ksort($item);
            }
            foreach ($item as $key => $child) {
                $item[$key] = $normalize($child);
            }
            return $item;
        };

        $json = json_encode($normalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false) {
            throw new InvalidArgumentException('تعذر تحويل بيانات الحدث إلى JSON: ' . json_last_error_msg());
        }
        return $json;
    }
}
