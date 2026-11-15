<?php
/**
 * إقفال الفترات: يمنع إنشاء قيد بتاريخ داخل فترة مقفولة (accounting_periods) أو سنة مالية مقفولة (financial_years.is_closed).
 * بدون أي فترة مقفولة لا يتغير شيء.
 */
class PeriodGuard {
    public static function assertOpen($db, string $entryDate): void {
        $d = substr($entryDate, 0, 10);
        try {
            $st = $db->prepare("SELECT COUNT(*) FROM accounting_periods WHERE is_closed = 1 AND ? BETWEEN date_from AND date_to");
            $st->execute([$d]);
            if ((int)$st->fetchColumn() > 0) { throw new Exception("الفترة المحاسبية لتاريخ $d مقفولة، لا يمكن تسجيل قيد بها."); }
        } catch (PDOException $e) { /* الجدول غير موجود قبل التحديث */ }
        try {
            $st = $db->prepare("SELECT COUNT(*) FROM financial_years WHERE is_closed = 1 AND ? BETWEEN date_from AND date_to");
            $st->execute([$d]);
            if ((int)$st->fetchColumn() > 0) { throw new Exception("السنة المالية لتاريخ $d مقفولة، لا يمكن تسجيل قيد بها."); }
        } catch (PDOException $e) { }
    }
}
