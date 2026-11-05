# المرحلة 26: إعداد سياسة تعديل رسوم الشحن

## ما تم
- إضافة ShippingConfigurationManager لقراءة وحفظ ALLOW_SHIPPING_AMOUNT_EDIT_DURING_CONFIRMATION.
- القيمة الافتراضية 0 (منع التعديل) في التثبيت الجديد وقاعدة البيانات القائمة.
- إضافة API للقراءة والحفظ في finance.control.php، مع اشتراط ADMIN_TYPE = 1.
- إضافة صفحة ShippingSettings ورابط لمسؤولي النظام فقط.

## الملفات
- inc/Classes/Accounting/ShippingConfigurationManager.php
- inc/Databases/Migrations/20261105_shipping_amount_edit_setting.sql
- inc/Databases/JUSTM_DATABASE_INSTALL.sql
- inc/controller/finance.control.php
- inc/Classes/user_permission.php
- inc/template/SidebarMenu.php
- inc/Views/ShippingSettings.php
- dist/js/ShippingSettings.js

## ما لم يكتمل
لم تتم إضافة حقل تعديل رسوم الشحن إلى نافذة تأكيد التوصيل، ولم يتم تمرير قيمة التعديل إلى الخادم.
أثناء المراجعة ظهر اختلاف بين استجابة shipment_BE::handleViewMode (مفاتيح type/products/returned/SHIPPING_VAL) وطريقة قراءة dist/js/model/shipmentDelivery.js للاستجابة (response[0] وresponse[1] كـ HTML). لذلك لم أضف تحديثًا مباشرًا لـ orders.SHIPPING_VAL قبل إصلاح مسار العرض والتأكيد، حتى لا يتم تغيير القيمة دون واجهة صحيحة أو تحقق مناسب.

## تحصيل الشحن عند الرفض
لم ينفذ بعد. التصميم المحاسبي المتاح:
- مدين: حساب عهدة المندوب من delivery_accounting_settings.courier_custody_account_id.
- دائن: حساب إيراد الشحن من AccountSettings::shippingRevenue().
- يجب أن يتم التحصيل فقط عند تأكيد دفع العميل، وأن يُمنع تحصيل رسوم الشحن أكثر من مرة للطلب الواحد.
- لا ينشأ أي تحصيل إذا لم يدفع العميل.
- عمولة الرفض تظل مستقلة وتتكرر لكل محاولة فعلية.

## الاختبارات
تم رفع الملفات والتحقق من وجودها على GitHub. لم تُشغّل اختبارات PHP lint أو MariaDB، ولم يتم تنفيذ Migration أو النشر.
