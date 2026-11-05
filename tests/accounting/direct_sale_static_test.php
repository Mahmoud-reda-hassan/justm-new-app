<?php
declare(strict_types=1);

/** فحوص ثابتة لقواعد البيع المباشر وعكس القيود وخدمة الأحداث المالية. */
$root = dirname(__DIR__, 2);

function projectFile(string $root, string $relative): string
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path)) {
        throw new RuntimeException("الملف غير موجود: {$relative}");
    }
    return (string)file_get_contents($path);
}
function methodBody(string $source, string $signature): string
{
    $start = strpos($source, $signature);
    if ($start === false) {
        throw new RuntimeException("الدالة غير موجودة: {$signature}");
    }
    $open = strpos($source, '{', $start);
    $depth = 0;
    for ($i = $open, $n = strlen($source); $i < $n; $i++) {
        if ($source[$i] === '{') $depth++;
        elseif ($source[$i] === '}' && --$depth === 0) return substr($source, $start, $i - $start + 1);
    }
    throw new RuntimeException("أقواس غير متوازنة: {$signature}");
}
function has(string $source, string $needle, string $message): void
{
    if (strpos($source, $needle) === false) throw new RuntimeException($message);
}
function lacks(string $source, string $needle, string $message): void
{
    if (strpos($source, $needle) !== false) throw new RuntimeException($message);
}

try {
    $accounting = projectFile($root, 'inc/Classes/Accounting/AccountingEvents.php');
    $events = projectFile($root, 'inc/Classes/Accounting/FinancialEventManager.php');
    $journal = projectFile($root, 'inc/Classes/Accounting/JournalManager.php');
    $installer = projectFile($root, 'inc/Databases/JUSTM_DATABASE_INSTALL.sql');
    $rootInstaller = projectFile($root, 'JUSTM_DATABASE_INSTALL.sql');
    $deliveryManager = projectFile($root, 'inc/Classes/Accounting/DeliverySettlementManager.php');
    $financeController = projectFile($root, 'inc/controller/finance.control.php');

    $sale = methodBody($accounting, 'public function directSale(int $orderId): int');
    $validation = methodBody($accounting, 'protected function validateDirectSale(array $order): void');
    $shipping = methodBody($accounting, 'private function postOrderShippingCharge(int $orderId): int');
    has($sale, 'new FinancialEventManager($this->db)', 'البيع المباشر لا يستخدم بوابة الأحداث.');
    has($sale, "'event_type' => 'SALE_RECOGNIZED'", 'نوع حدث البيع غير صحيح.');
    has($sale, "'source_type' => 'ORDER'", 'مصدر حدث البيع غير صحيح.');
    has($sale, 'DIRECT_SALE:ORDER:', 'مفتاح منع تكرار البيع غير موجود.');
    has($sale, '\'debit\' => $productsAmount', 'مديونية العميل لا تساوي قيمة المنتجات.');
    has($sale, '\'credit\' => $productsAmount', 'إيراد البيع لا يساوي قيمة المنتجات.');
    has($sale, 'FOR UPDATE', 'لا يوجد قفل للطلب وبنوده.');
    lacks($sale, 'SHIPPING_VAL', 'الشحن دخل في البيع المباشر.');
    lacks($sale, 'shippingRevenue', 'إيراد الشحن دخل في البيع المباشر.');
    lacks($validation, 'SHIPPING_VAL', 'التحقق من البيع المباشر يعتمد على الشحن.');
    lacks($validation, 'shippingRevenue', 'التحقق من البيع المباشر يستدعي إيراد الشحن.');

    has($sale, 'postOrderShippingCharge($orderId)', 'مسار البيع لا يستدعي حدث الشحن المنفصل.');
    has($shipping, 'if ($orderTypeId === 5 || $shippingAmount <= 0)', 'يجب استثناء البيع المباشر والطلبات بلا رسوم من حدث الشحن.');
    has($shipping, "'event_type' => 'SHIPPING_REVENUE_RECOGNIZED'", 'نوع حدث إيراد الشحن غير صحيح.');
    has($shipping, 'ORDER_SHIPPING:ORDER:', 'مفتاح منع تكرار إيراد الشحن غير موجود.');
    has($shipping, '\'debit\' => $shippingAmount', 'مديونية رسوم الشحن غير مثبتة.');
    has($shipping, '\'credit\' => $shippingAmount', 'إيراد الشحن غير مثبت.');
    has($shipping, 'shippingRevenue()', 'إيراد الشحن لا يستخدم حساب الإعدادات.');
    has($shipping, 'FOR UPDATE', 'حدث الشحن لا يقفل الطلب قبل إعادة التحقق.');



    $collection = methodBody($deliveryManager, 'public function registerCustomerCollection(');
    has($deliveryManager, "require_once __DIR__ . '/FinancialEventManager.php'", 'خدمة تحصيل المندوب لا تحمل FinancialEventManager.');
    has($collection, "preg_match('/^[A-Za-z0-9._:-]{8,64}$/D', $requestKey)", 'مفتاح طلب التحصيل غير محمي أو غير إلزامي.');
    has($collection, "'event_type' => 'COURIER_COLLECTION'", 'حدث تحصيل المندوب غير مسجل في سجل الأحداث.');
    has($collection, '$eventManager->post($eventData', 'تحصيل المندوب لا يمر عبر FinancialEventManager.');
    has($collection, 'WHERE idempotency_key = ? LIMIT 1 FOR UPDATE', 'إعادة إرسال التحصيل لا تفحص الحدث قبل فحص الرصيد المفتوح.');
    has($collection, 'SHIPPING_REVENUE_RECOGNIZED', 'تحصيل الطلب المشحون لا يتحقق من حدث الشحن.');
    has($collection, 'foreach ($receivableLines as $receivableLine)', 'تخصيص التحصيل لا يغطي سطور المنتجات والشحن.');
    has($collection, "'event_type' => 'COMMISSION_ACCRUED'", 'حدث استحقاق العمولة غير محمي بمنع التكرار.');
    has($financeController, "$_POST['request_key']", 'Endpoint التحصيل لا يستقبل مفتاح منع التكرار.');

    $reverse = methodBody($journal, 'public function reverse(');
    has($reverse, 'FOR UPDATE', 'عكس القيد لا يقفل القيد الأصلي.');
    has($reverse, "status'] !== 'POSTED'", 'عكس القيد لا يشترط أن يكون القيد الأصلي مرحلًا.');
    has($reverse, "reference_type = 'JOURNAL_REVERSE'", 'منع العكس المكرر غير موجود.');
    has($reverse, "'debit'          => (float)\$line['credit']", 'العكس لا يقلب المدين والدائن.');
    has($reverse, "'credit'         => (float)\$line['debit']", 'العكس لا يقلب الدائن والمدين.');
    has($reverse, "status = 'REVERSED'", 'حالة القيد الأصلي لا تتغير بعد العكس.');
    has($reverse, 'rollBack()', 'عكس القيد لا يتراجع عند الفشل.');

    has($events, 'INSERT INTO accounting_events', 'خدمة الأحداث لا تسجل الأحداث في جدولها.');
    has($events, 'hash_equals', 'مقارنة بصمة الحدث غير موجودة.');
    has($events, 'FOR UPDATE', 'قفل الحدث/القيد غير موجود.');
    has($events, "status'] !== 'POSTED'", 'التحقق من حالة القيد الناتج غير موجود.');
    has($events, 'rollBack()', 'التراجع عند فشل الحدث غير موجود.');

    has($installer, 'CREATE TABLE IF NOT EXISTS `accounting_events`', 'جدول accounting_events غير موجود.');
    has($installer, 'UNIQUE KEY `uq_accounting_event_idempotency`', 'قيد منع تكرار الحدث غير موجود.');
    if (hash('sha256', $installer) !== hash('sha256', $rootInstaller)) {
        throw new RuntimeException('نسخة Installer في الجذر تختلف عن نسخة inc/Databases.');
    }

    echo "PASS: direct sale posts product value only and excludes shipping.\n";
    echo "PASS: journal reversal locks, swaps lines, prevents duplicates and marks original reversed.\n";
    echo "PASS: financial event idempotency and transaction safeguards exist.\n";
    echo "PASS: both installer copies are identical and define accounting_events.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
    exit(1);
}
