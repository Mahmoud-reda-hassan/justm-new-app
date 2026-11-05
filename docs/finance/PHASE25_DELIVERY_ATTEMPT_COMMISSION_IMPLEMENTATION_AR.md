# المرحلة 25: استحقاق عمولة محاولة رفض التوصيل

## الهدف

تسجيل عمولة مستقلة لكل محاولة توصيل انتهت برفض العميل، دون ربط استحقاق العمولة بدفع العميل رسوم الشحن، ودون تكرار العمولة عند إعادة إرسال نفس تأكيد HTTP.

## مصدر المحاولة والمندوب

وفق تعريف الإجراء المخزن `shipping_movement` في نسخة `cptest.sql` المؤرخة 2026-11-05:

- الرفض الفعلي يسجل في `shipping_movement.movement_type = 5`.
- `shipmov_id` هو المفتاح الأساسي للحركة، وهو معرف المحاولة.
- `UNIQUE_ID` يساوي `p_session_id` الخاص بتأكيد الشحنة.
- `shipment_orders` يربط الحركة بأمر البيع.
- `delivery_id` هو معرف المندوب الذي اعتمده الإجراء: `confirm_staging.USER_ID` عندما `EXISTS_ID <> 11)، و`p_executed_by` عندما `EXISTS_ID = 11`.
- تتم مطابقة `delivery_id` مع `users_delivery.USER_ID` لاستخراج `PERCENT`. إذا لم يوجد ملف مندوب مطابق، تتوقف المعاملة بدل إسقاط العمولة بصمت.
- يتم استخراج الفرع من `orders.STO_ID -> stores.BRANCH_ID` والتحقق من مطابقته لفرع تأكيد الشحنة.

## التنفيذ

1. أضيف `DeliveryAttemptCommissionManager`.
2. بعد نجاح `setDeliveryConfirmationOptimized` وقبل اعتماد `delivery_confirmation_requests`، يستخرج مدير العمولة حركات الرفض المنشأة في الجلسة نفسها.
3. يستثني `ORDER_TYPE_ID = 5` (البيع المباشر).
4. يحفظ snapshots لقيمة `orders.SHIPPING_VAL` ونسبة `users_delivery.PERCENT` ومبلغ العمولة المحسوب لكل `shipmov_id`.
5. مفتاح الحدث المالي هو `DELIVERY_ATTEMPT_COMMISSION:{shipmov_id}`.
6. القيد: مدين مصروف عمولة المندوب، دائن عمولة مستحقة للمندوب، باستخدام حسابي العمولة المحددين في `delivery_accounting_settings`.
7. إذا كانت العمولة صفرًا، يحفظ snapshot بحالة `NO_COMMISSION` دون إنشاء قيد صفري.
8. يتم الترحيل داخل معاملة تأكيد الشحنة نفسها؛ فشل الحسابات أو القيد يلغي تأكيد الشحنة كله، لمنع حفظ رفض دون استحقاقه المالي.
9. منع تكرار HTTP في المرحلة 24 يبقى كما هو: نفس `request_key` يعيد نتيجة التأكيد المكتملة ولا ينشئ حركة جديدة. أما محاولة حقيقية جديدة فتنتج `shipmov_id` جديدًا وعمولة جديدة.

## ملفات المرحلة

- `inc/Classes/Accounting/DeliveryAttemptCommissionManager.php`
- `inc/Databases/Migrations/20261105_delivery_attempt_commission.sql`
- `inc/controller/BackEnd/shipment_BE.php`
- `inc/Databases/JUSTM_DATABASE_INSTALL.sql`

## ما لم تنفذه هذه المرحلة

- لم تنفذ بعد واجهة/إعداد السماح بتعديل رسوم الشحن وقت التأكيد.
- لم تنفذ بعد مسار تحصيل رسوم الشحن الفعلية عند دفع العميل كعهدة للمندوب؛ ولا يتم إنشاء تحصيل وهمي عند عدم الدفع.
- لم تُشغّل اختبارات MariaDB تكاملية أو PHP lint على المشروع كاملًا.
- لم يتم تنفيذ migration على أي قاعدة بيانات ولم يتم النشر.

## متطلبات ما قبل التشغيل

يجب أن تكون قيمتا `courier_commission_expense_account_id` و`courier_commission_payable_account_id` في `delivery_accounting_settings` مربوطتين بحسابين نشطين يسمحان بالترحيل. كما يجب أن يكون `users_delivery.PERCENT` مضبوطًا للمندوب الفعلي.
