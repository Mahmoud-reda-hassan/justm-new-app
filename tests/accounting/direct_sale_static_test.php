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
    has($collection, 'preg_match(\'/^[A-Za-z0-9._:-]{8,64}$/D\', $requestKey)', 'مفتاح طلب التحصيل غير محمي أو غير إلزامي.');
    has($collection, "'event_type' => 'COURIER_COLLECTION'", 'حدث تحصيل المندوب غير مسجل في سجل الأحداث.');
    has($collection, '$eventManager->post($eventData', 'تحصيل المندوب لا يمر عبر FinancialEventManager.');
    has($collection, 'WHERE idempotency_key = ? LIMIT 1 FOR UPDATE', 'إعادة إرسال التحصيل لا تفحص الحدث قبل فحص الرصيد المفتوح.');
    has($collection, 'SHIPPING_REVENUE_RECOGNIZED', 'تحصيل الطلب المشحون لا يتحقق من حدث الشحن.');
    has($collection, 'foreach ($receivableLines as $receivableLine)', 'تخصيص التحصيل لا يغطي سطور المنتجات والشحن.');
    has($collection, "'event_type' => 'COMMISSION_ACCRUED'", 'حدث استحقاق العمولة غير محمي بمنع التكرار.');
    has($financeController, 'request_key', 'Endpoint التحصيل لا يستقبل مفتاح منع التكرار.');

    $accountSettings = projectFile($root, 'inc/Classes/Accounting/AccountSettings.php');
    $commissionAccountsMigration = projectFile($root, 'inc/Databases/migrations/PHASE17_MARKETER_COMMISSION_ACCOUNTS.sql');
    has($accountSettings, "MARKETER_COMMISSION_PAYABLE", 'إعداد حساب مستحقات عمولات المسوقين وقادة الفرق غير موجود.');
    has($accountSettings, "MARKETER_COMMISSION_EXPENSE", 'إعداد حساب مصروف عمولات المسوقين وقادة الفرق غير موجود.');
    has($collection, 'affiliate.COMMISSION_SALES', 'حساب عمولة المسوق لا يقرأ النسبة المعتمدة للمسوق.');
    has($collection, 'leader.TEAM_COMMISSION', 'حساب عمولة قائد الفريق لا يقرأ نسبة عمولة الفريق.');
    has($collection, "custody.CUST_STAT", 'احتساب عمولات التسويق لا يتحقق من حالة تجميد العهدة.');
    has($collection, "'MARKETER_COMMISSION_ACCRUAL'", 'استحقاق عمولات المسوق والقائد لا يرحل إلى الأستاذ العام.');
    has($collection, "'MARKETER_COMMISSION:COLLECTION:'", 'استحقاق عمولات التسويق لا يستخدم مفتاح منع التكرار.');
    has($collection, 'marketerCommissionExpense()', 'مصروف عمولات التسويق لا يستخدم الحساب المستقل.');
    has($collection, 'marketerCommissionPayable()', 'مستحقات عمولات التسويق لا تستخدم الحساب المستقل.');
    has($commissionAccountsMigration, "'2152'", 'Migration لا ينشئ حساب مستحقات عمولات التسويق 2152.');
    has($commissionAccountsMigration, "'5230'", 'Migration لا ينشئ حساب مصروف عمولات التسويق 5230.');
    has($rootInstaller, "'MARKETER_COMMISSION_PAYABLE'", 'Installer الجذر لا يربط حساب مستحقات عمولات التسويق.');
    has($rootInstaller, "'MARKETER_COMMISSION_EXPENSE'", 'Installer الجذر لا يربط حساب مصروف عمولات التسويق.');
    has($installer, "'MARKETER_COMMISSION_PAYABLE'", 'Installer inc لا يربط حساب مستحقات عمولات التسويق.');
    has($installer, "'MARKETER_COMMISSION_EXPENSE'", 'Installer inc لا يربط حساب مصروف عمولات التسويق.');

    $marketerPayout = methodBody($deliveryManager, 'public function payMarketerCommission(');
    has($marketerPayout, 'marketer_commission_payments', 'صرف عمولة المسوق لا ينشئ مستند صرف GL.');
    has($marketerPayout, 'marketerCommissionPayable()', 'صرف عمولة المسوق لا يخفض حساب المستحقات المستقل.');
    has($marketerPayout, 'transactionAccount($paymentMethodId, $branchId)', 'صرف عمولة المسوق لا يستخدم حساب طريقة الدفع المرتبط بالفرع.');
    has($marketerPayout, "'partner_type' => 'MARKETER'", 'قيد صرف عمولة المسوق لا يربط الشريك.');
    has($marketerPayout, "'reference_type' => 'MARKETER_COMMISSION_PAYMENT'", 'قيد صرف عمولة المسوق بلا مرجع واضح.');
    lacks($marketerPayout, 'partners_balance', 'صرف عمولة المسوق ما زال يعتمد على دفتر الأرصدة القديم.');
    lacks($marketerPayout, 'financial_transactions', 'صرف عمولة المسوق ما زال يسجل معاملة مالية قديمة.');
    $payoutController = projectFile($root, 'inc/controller/finance.control.php');
    has($payoutController, "case 'PayMarketerCommission'", 'Endpoint صرف عمولة المسوق غير موجود.');
    has($payoutController, 'payMarketerCommission(', 'Endpoint الصرف لا يستدعي خدمة GL.');
    $payoutBackend = projectFile($root, 'inc/controller/BackEnd/PaymentDisbursement_BE.php');
    has($payoutBackend, "MARKETER_COMMISSION_PAYABLE", 'رصيد المسوق في شاشة الصرف لا يُقرأ من حساب GL.');
    $payoutView = projectFile($root, 'inc/Views/PaymentDisbursement.php');
    has($payoutView, 'marketer-payment-method', 'شاشة صرف المسوق لا تطلب طريقة الدفع.');
    $payoutJs = projectFile($root, 'dist/js/PaymentDisbursement.js');
    has($payoutJs, "'PayMarketerCommission'", 'شاشة الصرف لا تستخدم endpoint الأستاذ العام.');
    has($payoutJs, 'request_key', 'شاشة الصرف لا ترسل مفتاح منع التكرار.');

    $courierPayout = methodBody($deliveryManager, 'public function payCommission(');
    has($courierPayout, 'delivery_commission_payments', 'صرف عمولة المندوب لا ينشئ مستند صرف GL.');
    has($courierPayout, 'transactionAccount($paymentMethodId, $branchId)', 'صرف عمولة المندوب لا يستخدم حساب طريقة الدفع.');
    has($courierPayout, "'partner_type' => 'DELIVERY'", 'قيد صرف عمولة المندوب لا يربط الشريك.');
    has($payoutBackend, 'courier_commission_payable_account_id', 'رصيد المندوب في شاشة الصرف لا يُقرأ من حساب GL.');
    has($payoutView, 'delivery-payment-method', 'شاشة صرف المندوب لا تطلب طريقة الدفع.');
    has($payoutJs, "'PayDeliveryCommission'", 'شاشة صرف المندوب لا تستخدم endpoint الأستاذ العام.');
    has($payoutController, "case 'PaymentOfMarketerDues':", 'لم يتم إغلاق endpoint الصرف القديم للمسوق.');
    has($payoutController, "case 'PaymentOfDeliveryDues':", 'لم يتم إغلاق endpoint الصرف القديم للمندوب.');
    lacks($payoutController, '$CBE->PaymentOfMarketerDues(', 'endpoint الصرف القديم للمسوق ما زال نشطًا.');
    lacks($payoutController, '$CBE->PaymentOfDeliveryDues(', 'endpoint الصرف القديم للمندوب ما زال نشطًا.');

    has($installer, 'CREATE TABLE IF NOT EXISTS marketer_commission_payments', 'جدول صرف عمولات المسوق غير موجود في Installer inc.');
    has($rootInstaller, 'CREATE TABLE IF NOT EXISTS marketer_commission_payments', 'جدول صرف عمولات المسوق غير موجود في Installer الجذر.');

    $refundManager = projectFile($root, 'inc/Classes/Accounting/CustomerRefundManager.php');
    $refundMethod = methodBody($refundManager, 'public function refund(');
    has($refundMethod, 'RETURN_ENTRY_ID', 'رد المبلغ لا يشترط ترحيل مردود البيع.');
    has($refundMethod, 'customer_refund_payments', 'رد المبلغ لا يسجل مستند GL مستقلًا.');
    has($refundMethod, 'transactionAccount($paymentMethodId', 'رد المبلغ لا يستخدم حساب طريقة الدفع الفعلية.');
    lacks($refundMethod, 'partners_balance', 'رد المبلغ ما زال يعتمد على دفتر الأرصدة القديم.');
    lacks($refundMethod, 'INSERT INTO financial_transactions', 'رد المبلغ الجديد ما زال ينشئ حركة مالية قديمة.');
    lacks($refundMethod, 'UPDATE financial_transactions', 'رد المبلغ الجديد ما زال يعدل الحركات المالية القديمة.');
    $refundController = projectFile($root, 'inc/controller/finance.control.php');
    has($refundController, "case 'RefundCustomerReturn'", 'Endpoint رد المرتجع GL غير موجود.');
    has($refundController, 'customerRefunds->refund(', 'Endpoint رد المرتجع لا يستدعي خدمة GL.');
    has($refundController, "case 'PaymentOfCustomerDues':", 'لم يتم إغلاق endpoint رد المرتجع القديم.');
    lacks($refundController, '$CBE->PaymentOfCustomerDues(', 'endpoint رد المرتجع القديم ما زال نشطًا.');
    $refundBackend = projectFile($root, 'inc/controller/BackEnd/PaymentDisbursement_BE.php');
    has($refundBackend, 'customer_refund_payments', 'قائمة أرصدة المرتجعات لا تقرأ مستندات GL.');
    lacks(methodBody($refundBackend, 'public function GetRefundBalance('), 'partners_balance', 'رصيد المرتجع ما زال يعتمد على partners_balance.');
    $refundView = projectFile($root, 'inc/Views/PaymentDisbursement.php');
    has($refundView, 'refund-payment-method', 'واجهة رد المرتجع لا تطلب طريقة الدفع.');
    $refundJs = projectFile($root, 'dist/js/PaymentDisbursement.js');
    has($refundJs, "'RefundCustomerReturn'", 'واجهة رد المرتجع لا تستخدم endpoint GL.');
    has($refundJs, 'refund-payment-method', 'واجهة رد المرتجع لا ترسل طريقة الدفع.');
    has($installer, 'CREATE TABLE IF NOT EXISTS customer_refund_payments', 'جدول رد المرتجعات غير موجود في Installer inc.');
    has($rootInstaller, 'CREATE TABLE IF NOT EXISTS customer_refund_payments', 'جدول رد المرتجعات غير موجود في Installer الجذر.');

    $accountingEvents = projectFile($root, 'inc/Classes/Accounting/AccountingEvents.php');
    $salesReturn = methodBody($accountingEvents, 'private function salesReturnInternal(');
    has($salesReturn, 'MARKETER_COMMISSION_REVERSAL', 'مرتجع البيع لا يعكس عمولة المسوق أو قائد الفريق في GL.');
    has($salesReturn, 'assertSalesReturnStockReceived($orderId)', 'عكس عمولة المسوق يحدث قبل استلام المرتجع مخزنيًا.');
    has($salesReturn, 'marketerCommissionPayable()', 'عكس عمولة المسوق لا يستخدم حساب المستحقات المستقل.');
    has($salesReturn, 'marketerCommissionExpense()', 'عكس عمولة المسوق لا يعكس المصروف المستقل.');
    has($salesReturn, "d.reference_type = 'MARKETER_COMMISSION_ACCRUAL'", 'عكس عمولة المسوق لا يحد العكس بما سبق استحقاقه.');
    $reportsBackend = projectFile($root, 'inc/controller/BackEnd/reports_BE.php');
    $accountStatement = methodBody($reportsBackend, 'public function AccountStatement(');
    has($accountStatement, "if (strtoupper((string)\$partnerType) === 'MARKETER')", 'كشف حساب المسوق لا يقرأ الأستاذ العام.');
    has($accountStatement, 'MARKETER_COMMISSION_PAYABLE', 'كشف حساب المسوق لا يحدد حساب المستحقات.');
    has($accountStatement, "strtoupper((string)\$partnerType) === 'DELIVERY'", 'كشف حساب المندوب لا يقرأ الأستاذ العام.');
    has($accountStatement, 'courier_custody_account_id', 'كشف حساب المندوب لا يضم حساب العهدة.');
    has($accountStatement, 'courier_commission_payable_account_id', 'كشف حساب المندوب لا يضم مستحقات العمولة.');
    has($accountStatement, "strtoupper((string)\$partnerType) === 'CLIENT'", 'كشف حساب العميل لا يقرأ الأستاذ العام.');
    has($accountStatement, "aset.setting_key = 'CUSTOMERS'", 'كشف حساب العميل لا يستخدم حساب العملاء المعتمد.');
    lacks(substr($accountStatement, strpos($accountStatement, "if (strtoupper((string)\$partnerType) === 'CLIENT')"), strpos($accountStatement, "if (strtoupper((string)\$partnerType) === 'MARKETER')") - strpos($accountStatement, "if (strtoupper((string)\$partnerType) === 'CLIENT')")), 'partners_balance', 'فرع كشف حساب العميل ما زال يعتمد على دفتر الأرصدة القديم.');
    $marketerDailyReport = methodBody($reportsBackend, 'public function marketerDailyReport(');
    $marketerDailyDetails = methodBody($reportsBackend, 'public function marketerDailyDetails(');
    has($marketerDailyReport, 'journal_entry_details', 'تقرير المسوق اليومي لا يقرأ قيود GL.');
    has($marketerDailyDetails, 'journal_entry_details', 'تفاصيل تقرير المسوق لا تقرأ قيود GL.');
    lacks($marketerDailyReport, 'partners_balance', 'تقرير المسوق اليومي ما زال يعتمد على partners_balance.');
    lacks($marketerDailyDetails, 'partners_balance', 'تفاصيل تقرير المسوق ما زالت تعتمد على partners_balance.');

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
    // نسختا Installer قد تحتويان اختلافات تاريخية خاصة بقيود الجداول؛
    // نتحقق من وجود بنية الأحداث المحاسبية في النسختين بدل اشتراط تطابق الملفين بالكامل.
    has($rootInstaller, 'CREATE TABLE IF NOT EXISTS `accounting_events`', 'جدول accounting_events غير موجود في Installer الجذر.');
    has($rootInstaller, 'UNIQUE KEY `uq_accounting_event_idempotency`', 'قيد منع تكرار الحدث غير موجود في Installer الجذر.');

    $attemptCommission = projectFile($root, 'inc/Classes/Accounting/DeliveryAttemptCommissionManager.php');
    $shippingCollection = projectFile($root, 'inc/Classes/Accounting/DeliveryRejectedShippingCollectionManager.php');
    $shippingReview = projectFile($root, 'inc/Classes/Accounting/DeliveryRejectedShippingReviewManager.php');
    $shipmentBackend = projectFile($root, 'inc/controller/BackEnd/shipment_BE.php');
    $reviewMigration = projectFile($root, 'inc/Databases/Migrations/20261107_delivery_rejected_shipping_review.sql');

    has($rootInstaller, 'CREATE TABLE IF NOT EXISTS `delivery_attempt_commissions`', 'جدول عمولات محاولات التوصيل غير موجود في Installer الجذر.');
    has($rootInstaller, 'CREATE TABLE IF NOT EXISTS `delivery_rejected_shipping_collections`', 'جدول تحصيل شحن الرفض غير موجود في Installer الجذر.');
    has($rootInstaller, 'CREATE TABLE IF NOT EXISTS `delivery_rejected_shipping_reviews`', 'جدول مراجعة تعويض الشحن غير موجود في Installer الجذر.');
    has($rootInstaller, 'CREATE TABLE IF NOT EXISTS `delivery_confirmation_requests`', 'جدول منع تكرار تأكيد الشحن غير موجود في Installer الجذر.');
    has($rootInstaller, 'CREATE TABLE IF NOT EXISTS `delivery_collection_requests`', 'جدول منع تكرار التحصيل غير موجود في Installer الجذر.');
    has($rootInstaller, 'CREATE TABLE IF NOT EXISTS `delivery_collection_request_collections`', 'جدول ربط مستندات التحصيل بطلب التحصيل غير موجود في Installer الجذر.');
    has($rootInstaller, "`payment_method_id` BIGINT UNSIGNED NULL", 'طريقة الدفع يجب أن تسمح بالقيمة NULL لتحصيل الشحن المرفوض.');
    has($rootInstaller, "`collection_source` ENUM('ORDER_COLLECTION','REJECTED_SHIPPING')", 'مصدر التحصيل غير موجود في Installer الجذر.');
    $zoneSeed = strpos($rootInstaller, 'INSERT INTO `zones`');
    $citySeed = strpos($rootInstaller, 'INSERT INTO `city`');
    $companySeed = strpos($rootInstaller, 'INSERT INTO `companies`');
    $storeSeed = strpos($rootInstaller, 'INSERT INTO `stores`');
    $foreignKeysOff = strrpos(substr($rootInstaller, 0, $zoneSeed), 'SET FOREIGN_KEY_CHECKS=0;');
    $foreignKeysOn = strpos($rootInstaller, 'SET FOREIGN_KEY_CHECKS=1;', $storeSeed);
    if ($zoneSeed === false || $citySeed === false || $companySeed === false || $storeSeed === false
        || !($zoneSeed < $citySeed && $citySeed < $companySeed && $companySeed < $storeSeed)
        || $foreignKeysOff === false || $foreignKeysOn === false) {
        throw new RuntimeException('ترتيب بيانات المناطق والمدن والشركة والفرع والمخزن لا يعالج العلاقات الدائرية بصورة صحيحة.');
    }
    has($installer, 'CREATE TABLE IF NOT EXISTS `delivery_attempt_commissions`', 'جدول عمولات المحاولات غير موجود في Installer inc.');
    has($installer, 'CREATE TABLE IF NOT EXISTS `delivery_rejected_shipping_collections`', 'جدول تحصيل شحن الرفض غير موجود في Installer inc.');
    has($installer, 'CREATE TABLE IF NOT EXISTS `delivery_rejected_shipping_reviews`', 'جدول مراجعة تعويض الشحن غير موجود في Installer inc.');
    has($reviewMigration, 'CREATE TABLE IF NOT EXISTS delivery_rejected_shipping_reviews', 'Migration قائمة مراجعة التعويض غير موجودة.');
    has($attemptCommission, 'DELIVERY_ATTEMPT_COMMISSION:', 'منع تكرار عمولة المحاولة غير مربوط بمعرف حركة الشحن.');
    has($shippingCollection, 'DELIVERY_REJECTED_SHIPPING:', 'منع تكرار تحصيل الشحن غير مربوط بالطلب.');
    has($shippingReview, 'DELIVERY_REJECTED_SHIPPING_COMPENSATION:', 'تعويض الشحن لا يستخدم مفتاح منع تكرار.');
    has($shippingReview, "status='PENDING'", 'قرار التعويض لا يقيد السجل المعلق.');
    $collectionCall = strpos($shipmentBackend, 'collectPaidRejectedShipping(');
    $commissionCall = strpos($shipmentBackend, 'accrueRejectedAttempts(');
    $reviewCall = strpos($shipmentBackend, 'recordUnpaidAttempts(');
    if ($collectionCall === false || $commissionCall === false || $reviewCall === false
        || !($collectionCall < $commissionCall && $commissionCall < $reviewCall)) {
        throw new RuntimeException('ترتيب تحصيل الشحن ثم العمولة ثم قائمة المراجعة غير صحيح.');
    }

    $orderClass = projectFile($root, 'inc/Classes/Order_tbl.php');
    $shipmentSql = projectFile($root, 'inc/Databases/setDeliveryConfirmationOptimized.sql');
    $orderCreation = methodBody($orderClass, 'addOrders');
    $courierConfirmation = methodBody($shipmentBackend, 'private function confirmShipmentDelivery()');
    lacks($orderCreation, 'addClientDebt(', 'إنشاء الطلب ما زال ينشئ مديونية قديمة قبل التسليم.');
    lacks($orderCreation, 'partners_balance', 'إنشاء الطلب ما زال يكتب في دفتر الأرصدة القديم.');
    has($courierConfirmation, '$accountingEvents->directSale(', 'تأكيد تسليم المندوب لا يرحّل البيع إلى الأستاذ العام.');
    has($courierConfirmation, "cs.TYPE = 'SELSE'", 'ترحيل البيع لا يقتصر على أوامر التسليم الفعلية.');
    lacks($shipmentSql, 'CALL restClientDebtBatch', 'إجراء تأكيد التسليم ما زال يستدعي تحديث مديونية العميل القديمة.');
    lacks($shipmentSql, 'CALL reduceClientCreditBatch', 'تأكيد المرتجع ما زال ينشئ رصيدًا دائنًا قديمًا قبل الاستلام المخزني.');
    has($shipmentBackend, '$accounting->salesReturn($returnOrderId)', 'استلام المرتجع مخزنيًا لا يرحّل عكس البيع إلى GL.');
    has($shipmentBackend, "processInventory('RETURN_SALES'", 'مسار المرتجع لا ينفذ حركة المخزون قبل قيد عكس البيع.');
    $directCustomerDelivery = methodBody($shipmentBackend, 'public function CustomerDeliveryOfProducts(');
    $directCustomerSaleConfirmation = methodBody($shipmentBackend, 'public function confirmOrderSales(');
    lacks($directCustomerDelivery, 'INSERT INTO partners_balance', 'التسليم المباشر للعميل ما زال يضيف قيودًا إلى دفتر الأرصدة القديم.');
    lacks($directCustomerDelivery, 'UPDATE partners_balance', 'التسليم المباشر للعميل ما زال يعدل دفتر الأرصدة القديم.');
    lacks($directCustomerSaleConfirmation, 'INSERT INTO partners_balance', 'تأكيد التسليم المباشر للعميل ما زال يضيف قيودًا إلى دفتر الأرصدة القديم.');
    lacks($directCustomerSaleConfirmation, 'UPDATE partners_balance', 'تأكيد التسليم المباشر للعميل ما زال يعدل دفتر الأرصدة القديم.');
    has($directCustomerSaleConfirmation, '$accounting->directSale($orderId)', 'تأكيد التسليم المباشر للعميل لا يرحّل البيع إلى الأستاذ العام.');

    echo "PASS: direct sale posts product value only and excludes shipping.\n";
    echo "PASS: journal reversal locks, swaps lines, prevents duplicates and marks original reversed.\n";
    echo "PASS: financial event idempotency and transaction safeguards exist.\n";
    echo "PASS: both installer copies define accounting_events and its idempotency key.\n";
    echo "PASS: rejected-shipping tables exist in both installers and the migration.\n";
    echo "PASS: rejected-shipping collection, per-attempt commission and review are idempotent and ordered.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
    exit(1);
}
