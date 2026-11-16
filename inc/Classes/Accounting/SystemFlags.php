<?php
/**
 * أعلام النظام (system_flags). BALANCE_SOURCE = legacy (افتراضي) | gl:
 * يحدد مصدر الأرصدة في الشاشات التي حُوّلت للقراءة من الأستاذ. الافتراضي legacy = لا تغيير في أي شاشة قديمة.
 */
class SystemFlags {
    public static function get($db, string $key, string $default = ''): string {
        try {
            $st = $db->prepare("SELECT flag_value FROM system_flags WHERE flag_key = ?");
            $st->execute([$key]);
            $v = $st->fetchColumn();
            return $v === false ? $default : (string)$v;
        } catch (Throwable $e) { return $default; } // الجدول غير موجود قبل التحديث
    }

    public static function set($db, string $key, string $value, int $userId): void {
        $allowed = ['BALANCE_SOURCE' => ['legacy', 'gl']];
        if (!isset($allowed[$key]) || !in_array($value, $allowed[$key], true)) { throw new Exception('إعداد غير مسموح.'); }
        $st = $db->prepare("INSERT INTO system_flags (flag_key, flag_value, updated_by) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE flag_value = VALUES(flag_value), updated_by = VALUES(updated_by)");
        $st->execute([$key, $value, $userId ?: null]);
    }
}

/** رصيد طرف صافي (دائن − مدين) من المصدر المختار. للنظام القديم نفس معادلة الشاشات القديمة. */
class PartnerBalanceSource {
    public static function net($db, string $type, int $partnerId): float {
        // الوسيط مقرَّه الأستاذ فقط (لا يوجد له رصيد قديم)
        if ($type === 'BROKER' || SystemFlags::get($db, 'BALANCE_SOURCE', 'legacy') === 'gl') {
            $st = $db->prepare("SELECT COALESCE(SUM(d.credit - d.debit),0) FROM journal_entry_details d
                JOIN journal_entries e ON e.id = d.journal_entry_id AND e.status = 'POSTED'
                WHERE d.partner_type = ? AND d.partner_id = ?");
            $st->execute([$type, $partnerId]);
            return round((float)$st->fetchColumn(), 2);
        }
        $st = $db->prepare("SELECT COALESCE(SUM(CASE WHEN TRANSACTION_TYPE='CREDIT' THEN BALANCE ELSE 0 END)
                - SUM(CASE WHEN TRANSACTION_TYPE='DEBIT' THEN BALANCE ELSE 0 END),0)
            FROM partners_balance WHERE PARTNER_TYPE = ? AND PARTNER_ID = ?");
        $st->execute([$type, $partnerId]);
        return round((float)$st->fetchColumn(), 2);
    }
}
