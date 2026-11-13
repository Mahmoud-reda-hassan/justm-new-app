# 12 — تدقيق قاعدة البيانات والعلاقات الفعلية في Just-M القديم

> الغرض: توثيق ما يمكن إثباته من ملفات المصدر وملف `inc/Databases/createDatabase.sql` داخل الأرشيف. هذه ليست شهادة مطابقة لقاعدة الإنتاج؛ لم يتوفر اتصال حي بقاعدة الإنتاج أو dump كامل منها أثناء الفحص.

## 1. حدود الفحص

- المصدر: نسخة المشروع المستخرجة من `CP_03082026.zip`.
- ملف الإنشاء الأساسي: `inc/Databases/createDatabase.sql`.
- ملفات SQL الإضافية داخل `inc/Databases/`، وملفات DAO وBackend وClasses التي تنفذ الاستعلامات.
- تم التفريق بين: جدول موجود في SQL، جدول يستخدمه PHP فقط، View، وإجراء مخزن.
- عدم وجود تعريف جدول في ملف الإنشاء لا يثبت عدم وجوده في قاعدة الإنتاج؛ لكنه يعني أن ملف التثبيت المرفق غير مكتفٍ بذاته.

## 2. مجموعات الجداول التشغيلية

### 2.1 الهيكل الإداري

| الجدول | الوظيفة المستنتجة من المصدر |
|---|---|
| `companies` | الشركة |
| `branches` | الفروع |
| `stores_type` | تصنيفات المخازن |
| `stores` | مخازن الفروع |
| `users`, `user_gruop` | المستخدمون والمجموعات |
| `users_affiliate`, `users_delivery` | بيانات أدوار المسوقين/المندوبين |

### 2.2 الأطراف

| الجدول | الوظيفة |
|---|---|
| `client` | العملاء |
| `suppliers` | الموردون |
| `telephones`, `addresses` | بيانات الاتصال المرتبطة بالأطراف |
| `entities` | تعريف/ربط أنواع كيانات مستخدمة في النظام؛ يلزم مراجعة كل استعمال قبل اعتمادها كمرجع موحد |

### 2.3 المنتجات والمخزون

| الجدول | الوظيفة |
|---|---|
| `product_category`, `products` | تصنيف المنتجات وتعريفها |
| `product_option_group`, `product_option` | مجموعات الخيارات |
| `product_codes` | أكواد/متغيرات المنتج وأسعارها وتكلفتها |
| `product_join_option_group`, `product_code_join_option` | ربط الخيارات بالمنتجات والأكواد |
| `stores_product` | أرصدة الأصناف بالمخازن |
| `barcode_counter` | عداد/توليد الباركود |
| `inventory_movement`, `inventory_product_movement` | رأس حركة المخزون وتفاصيلها |

### 2.4 المبيعات والشحن والمرتجعات

| الجدول | الوظيفة |
|---|---|
| `order_type`, `order_statuse`, `order_exists` | قوائم أنواع/حالات/مسارات الطلب |
| `orders`, `order_product` | رأس أمر البيع وبنوده |
| `order_service_type`, `order_service`, `order_service_text` | خدمات الطلب والنصوص التابعة |
| `orders_log` | سجل تغييرات الطلب |
| `shipping_movement` | حركات الشحن والتحصيل والتسوية |
| `custody_orders`, `custody_product` | عهدة المندوب/المسلم إليه |
| `custody_return_orders`, `custody_return_product` | تفاصيل المرتجع من العهدة |
| `sales_return_orders`, `sales_return_products` | مرتجعات المبيعات |

### 2.5 المشتريات

| الجدول | الوظيفة |
|---|---|
| `purchases_orders`, `purchases_products` | أمر شراء وبنوده والكميات المستلمة |
| `purchases_return_orders`, `purchases_return_products` | مرتجع المورد |
| `supply_orders`, `supply_order_details` | مسار توريد منفصل؛ لا يدمج مع أمر الشراء قبل إثبات استعماله التشغيلي |
| `purchases_payments` | جدول دفعات شراء مستقل موجود في SQL، ويجب فحص استعماله الفعلي قبل اعتباره مصدر الحقيقة |

### 2.6 المالية القديمة

| الجدول | الوظيفة والحدود |
|---|---|
| `invoices` | مستند مالي مرجعي لأنواع SALES وPURCHASES، مع إجمالي ومدفوع ومرتجع |
| `payment_methods` | طرق الدفع |
| `transaction_categories` | تصنيف حركة مالية إلى REVENUE/EXPENSES وتصنيف أب/ابن |
| `financial_transactions` | سجل تحصيل/صرف، مرتبط بالفرع والمستخدم والطرف والفاتورة والتصنيف وطريقة الدفع |
| `partners_balance` | دفتر أرصدة فرعي للأطراف CLIENT/DELIVERY/MARKETER/SUPPLIER؛ لا يحتوي على حساب أستاذ عام ولا مرجع قيد محاسبي في التعريف المرفق |
| `system_settings` | إعدادات عامة بنظام مفتاح/قيمة |

## 3. العلاقات التي يجب الحفاظ عليها أو إعادة بنائها

العلاقات التالية مستنتجة من المفاتيح والاستعلامات. لا تُعامل جميعها كـ FK فعلي إلا إذا كان تعريف المفتاح موجودًا صراحة في SQL:

1. `companies → branches → stores`.
2. `branches → users` أو ارتباط المستخدم بالفرع عبر جلسة/جدول صلاحيات؛ راجع المخطط الكامل قبل فرض FK.
3. `products → product_codes → stores_product`.
4. `orders → order_product → product_codes`.
5. `orders → shipping_movement`، مع مسار العهدة `custody_orders → custody_product`.
6. `purchases_orders → purchases_products`.
7. `purchases_orders → purchases_return_orders` عبر `REFERANC`، ثم `purchases_return_products`.
8. `financial_transactions → invoices` عبر `invoice_id`، و`transaction_categories` عبر `category_id`، و`payment_methods` عبر `payment_method_id`.
9. `partners_balance` يستخدم `PARTNER_TYPE + PARTNER_ID` كمرجع polymorphic؛ لا يمكن وضع FK تقليدي واحد إلى أربعة جداول أطراف.
10. `invoices.REFERENCE_ID + REFERENCE_TYPE` مرجع polymorphic إلى SALES/PURCHASES؛ لا توجد FK واحدة تضمن سلامته.

## 4. فجوة قاعدة بيانات المحاسبة العامة

الكود داخل `inc/Classes/Accounting/` يستعلم عن الجداول التالية:

- `accounts`
- `account_settings`
- `journal_entries`
- `journal_entry_details`
- `financial_years`
- `cash_boxes`

لكن البحث في ملفات SQL المرفقة لم يعثر على تعريفات `CREATE TABLE` لهذه الجداول. كذلك توجد ملفات كلاس فارغة (صفر بايت) في النسخة المفحوصة:

- `Bank.php`
- `CashBox.php`
- `CostCenter.php`
- `FinancialYear.php`
- `Helpers.php`
- `PartnerBalance.php`
- `Reports.php`

**النتيجة:** لا يمكن اعتبار وحدة الأستاذ العام قابلة للتثبيت من الأرشيف وحده. يلزم الحصول على schema فعلي من قاعدة البيانات المستخدمة، أو migrations/SQL أخرى غير موجودة في الأرشيف.

## 5. Views والإجراءات المخزنة

- `purchases_orders_view` في SQL المرفق يعيد `NULL` لأعمدة تفصيلية مثل كميات الطلب/المستلم/المتبقي وحالة التوريد وتفاصيل المنتجات، رغم أن واجهات الشراء تتوقع هذه البيانات. يجب فحص تعريف الـ View الحي قبل الاستنتاج أن الواجهة تعمل بهذا التعريف.
- `product_options_view` يعيد `NULL` لبعض حقول JSON الخاصة بالخيارات.
- توجد إجراءات/ملفات SQL إضافية للمخزون والأرصدة والشحن، منها `procedure_partners_balance.sql`, `procedure_inventory.sql`, `shipping_movement.sql`, `inventory_movement_sales_order.sql`, `setDeliveryConfirmationOptimized.sql`, `setRetunedConfirmationOptimized.sql`, `restClientDebtBatch.sql`, `Procedure_in_mysql.sql`, `set_orders_log.sql`.
- يجب حفظ نسخة من `SHOW CREATE PROCEDURE` و`SHOW CREATE VIEW` من قاعدة الإنتاج؛ ملفات SQL المنفصلة لا تثبت أنها مطابقة لما هو منشور حاليًا.

## 6. مشكلات تصميم البيانات القديمة

1. استخدام `FLOAT` في مبالغ `invoices` و`partners_balance` قد ينتج فروق تقريب؛ النظام الجديد يستخدم `DECIMAL(18,2)` أو دقة العملة المحددة.
2. تعدد مصادر رصيد الطرف بين `partners_balance` و`financial_transactions` وحقول مدفوعة على المستندات يؤدي إلى اختلاف الرصيد عند عدم الترحيل المتزامن.
3. المراجع polymorphic (`PARTNER_TYPE/PARTNER_ID`, `REFERENCE_TYPE/REFERENCE_ID`) لا تضمن التكامل المرجعي وحدها.
4. `partners_balance.REFERENCE_ID` لا يوضح نوع المرجع؛ يلزم الاعتماد على التصنيف والمصدر وسياق الكود لفهمه.
5. عدم وجود تعريفات GL في SQL المرفق يجعل تهيئة النظام على قاعدة جديدة غير قابلة لإعادة الإنتاج بصورة مؤكدة.
6. Views تحتوي أعمدة NULL ثابتة لا تصلح كمصدر بيانات نهائي لواجهة تعتمد عليها.
7. وجود `purchases_payments` بجانب `financial_transactions` و`purchases_orders.TOTAL_PAYMENTS` يخلق احتمال تعدد مصادر الحقيقة لسداد المورد.

## 7. عناصر لا يجوز حذفها قبل الترحيل

لا تحذف أو توقف أيًا من الآتي قبل اكتمال تحليل الاستخدام والربط والتسوية:

- `partners_balance`
- `financial_transactions`
- `transaction_categories`
- `payment_methods`
- `invoices`
- `purchases_payments`
- `shipping_movement`
- `custody_orders` وجداول تفاصيلها
- جداول المرتجعات وحركات المخزون

يجب إعداد تقرير اعتماد/استخدام لكل جدول، وتحديد المستهلكين في PHP وSQL والتقارير والمهام المجدولة قبل الإيقاف.

## 8. المطلوب للحصول على تطابق 100%

من قاعدة الإنتاج/الاختبار:

```sql
SHOW FULL TABLES;
SHOW CREATE TABLE accounts;
SHOW CREATE TABLE account_settings;
SHOW CREATE TABLE journal_entries;
SHOW CREATE TABLE journal_entry_details;
SHOW CREATE TABLE financial_years;
SHOW CREATE TABLE cash_boxes;
SHOW CREATE TABLE purchases_orders_view;
SHOW CREATE VIEW purchases_orders_view;
```

ثم تصدير schema كامل دون بيانات، مع الإجراءات والدوال والـ Views والـ Triggers، وتسجيل إصدار MariaDB وSQL_MODE وcollation.

## 9. حكم التدقيق

- **مؤكد:** تعريفات الجداول التشغيلية والمالية القديمة المذكورة أعلاه موجودة في `createDatabase.sql`.
- **مؤكد:** كود GL يستخدم جداول غير معرفة في SQL المرفق.
- **مؤكد:** ملفات بعض وحدات الخزائن والبنوك ومراكز التكلفة والتقارير فارغة.
- **غير محسوم:** هل توجد الجداول الناقصة في قاعدة الإنتاج، وهل تختلف الـ Views والإجراءات الحية عن الأرشيف.
- **غير منفذ:** لا يوجد اتصال حي أو اختبار استيراد على نسخة إنتاجية؛ لا تدّعِ نجاح ترحيل أو تطابق فعلي.
