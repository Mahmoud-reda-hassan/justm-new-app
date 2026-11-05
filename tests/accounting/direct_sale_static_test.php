<?php
declare(strict_types=1);

/**
 * فحوص ثابتة لقواعد البيع المباشر وسجل الأحداث.
 * هذه الفحوص لا تستبدل اختبارات قاعدة البيانات أو التشغيل الفعلي.
 */

$root = dirname(__DIR__, 2);

function readProjectFile(string $root, string $relative): string
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path)) {
        fwrite(STDERR, "FAIL: الملف غير موجود: {$relative}\n");
        exit(1);
    }
    return (string)file_get_contents($path);
}

function extractMethod(string $source, string $signature): string
{
    $start = strpos($source, $signature);
    if ($start === false) {
        throw new RuntimeException("لم يتم العثور على الدالة: {$signature}");
    }

    $open = strpos($source, '{', $start);
    if ($open === false) {
        throw new RuntimeException("بداية جسم الدالة غير موجودة: {$signature}");
    }

    $depth = 0;
    $length = strlen($source);
    for ($i = $open; $i < $length; $i++) {
        if ($source[$i] === '{') {
            $depth++;
        } elseif ($source[$i] === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($source, $start, $i - $start + 1);
            }
        }
    }

    throw new RuntimeException("أقواس الدالة غير متوازنة: {$signature}");
}

function assertContainsText(string $haystack, string $needle, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException($message);
    }
}

function assertNotContainsText(string $haystack, string $needle, string $message): void
{
    if (strpos($haystack, $needle) !== false) {
        throw new RuntimeException($message);
    }
}

try {
    $accounting = readProjectFile($root, 'inc/Classes/Accounting/AccountingEvents.php');
    $eventManager = readProjectFile($root, 'inc/Classes/Accounting/FinancialEventManager.php');
    $installer = readProjectFile($root, 'inc/Databases/JUSTM_DATABASE_INSTALL.sql');

    $directSale = extractMethod($accounting, 'public function directSale(int $orderId): int');
    $validation = extractMethod($accounting, 'protected function validateDirectSale(array $order): void');

    assertContainsText($directSale, 'new FinancialEventManager($this->db)', 'البيع المباشر لا يستخدم FinancialEventManager.');
    assertContainsText($directSale, "'event_type' => 'SALE_RECOGNIZED'", 'نوع حدث البيع غير صحيح.');
    assertContainsText($directSale, "'source_type' => 'ORDER'", 'مصدر حدث البيع غير صحيح.');
    assertContainsText($directSale, 'DIRECT_SALE:ORDER:', 'مفتاح منع التكرار الثابت غير موجود.');
    assertContainsText($directSale, "'debit' => $productsAmount", 'مديونية العميل ليست بقيمة المنتجات فقط.');
    assertContainsText($directSale, "'credit' => $productsAmount", 'إيراد المبيعات ليس بقيمة المنتجات فقط.');
    assertContainsText($directSale, 'FOR UPDATE', 'لا يوجد قفل لرأس الطلب وبنوده قبل الترحيل.');
    assertNotContainsText($directSale, 'SHIPPING_VAL', 'تم إدخال قيمة الشحن في مسار البيع المباشر.');
    assertNotContainsText($directSale, 'shippingRevenue', 'تم إدخال حساب إيراد الشحن في البيع المباشر.');
    assertNotContainsText($validation, 'SHIPPING_VAL', 'التحقق من البيع المباشر ما زال يعتمد على رسوم الشحن.');
    assertNotContainsText($validation, 'shippingRevenue', 'التحقق من البيع المباشر يعتمد على حساب الشحن.');

    assertContainsText($eventManager, 'idempotency_key', 'خدمة الأحداث لا تحتوي مفتاح منع التكرار.');
    assertContainsText($eventManager, "status'] !== 'POSTED'", 'خدمة الأحداث لا تتحقق من حالة القيد.');
    assertContainsText($eventManager, 'beginTransaction()', 'خدمة الأحداث لا تبدأ معاملة عند الحاجة.');
    assertContainsText($eventManager, 'rollBack()', 'خدمة الأحداث لا تلغي المعاملة عند الفشل.');

    assertContainsText($installer, 'CREATE TABLE IF NOT EXISTS `accounting_events`', 'جدول accounting_events غير موجود في Installer.');
    assertContainsText($installer, 'UNIQUE KEY `uq_accounting_event_idempotency`', 'قيد التفرد لمفتاح الحدث غير موجود.');

    echo "PASS: direct sale is product-only and uses idempotent financial events.\n";
    echo "PASS: direct sale contains no shipping amount or shipping revenue reference.\n";
    echo "PASS: event manager transaction/idempotency safeguards are present.\n";
    echo "PASS: installer defines accounting_events with a unique idempotency key.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
    exit(1);
}
