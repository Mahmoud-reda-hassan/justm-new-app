# 17 — مصفوفة اعتماديات الدورة المالية الحالية

**التاريخ:** 2026-11-05  
**الحالة:** حصر أولي مثبت من المصدر — يحتاج استكمال تتبع الاستدعاءات والواجهات.  
**المرجع المعماري المستهدف:** `docs/13_NEW_ACCOUNTING_ARCHITECTURE_AND_MIGRATION_PLAN_AR.md`  
**سجل ما قبل/بعد التدقيق:** `docs/16_ACCOUNTING_AUDIT_BEFORE_AND_AFTER_AR.md`

## 1. مصفوفة الجداول والكيانات

| الجدول/الكيان | كتابة مثبتة في المصدر | قراءة مثبتة في المصدر | الملفات/المكونات المثبتة | التصنيف الحالي | ملاحظات |
|---|---|---|---|---|---|
| `partners_balance` | نعم | نعم | `finance_BE.php`, `PaymentDisbursement_BE.php`, `reports_BE.php`, `accounts_BE.php` | قديم/مستمر الاستخدام | رصيد الأطراف والعمولات والمقدمات والصرف والمرتجعات ما زال يعتمد عليه في مسارات متعددة. |
| `invoices` | نعم | نعم | `finance_BE.php`, `AccountingEvents.php`, `Order_tbl.php`, `ordersList_BE.php`, `Invoices_tbl.php` | قديم/حلقة ربط انتقالية | يربط المستندات التشغيلية بحركات التحصيل القديمة. |
| `financial_transactions` | نعم | نعم | `finance_BE.php`, `AccountingEvents.php`, `accounts_BE.php` | قديم/حلقة ربط انتقالية | يخزن القبض/الصرف، ويستخدم كمدخل لمحرك الأستاذ العام في بعض الأحداث. |
| `transaction_categories` | غير مثبت في الملفات المفحوصة ككاتب | نعم | `AccountingEvents.php`, `accounts_BE.php` | قديم | تصنيف الحركات وتحديد CASH_IN/CASH_OUT وفئة صرف المرتجع. |
| `payment_methods` | غير محسوم | نعم | `AccountSettings.php` | مشترك/انتقالي | ربط طريقة الدفع بحساب الأستاذ يتم عبر `payment_method_accounts`. |
| `payment_method_accounts` | غير محسوم | نعم | `AccountSettings.php` | مستهدف/انتقالي | يحدد حساب الترحيل لكل طريقة دفع وفرع. |
| `accounts` | نعم | نعم | `AccountManager.php`, `AccountSettings.php`, `JournalManager.php` (من خلال تفاصيل القيد) | مستهدف | دليل الحسابات وإعداد الحسابات. |
| `account_settings` | غير محسوم | نعم | `AccountSettings.php` | مستهدف | يربط مفاتيح الأحداث بحسابات الأستاذ. |
| `journal_entries` | نعم | نعم | `JournalManager.php`, `PartnerLedger.php`, `DeliverySettlementManager.php` | مستهدف | رأس القيد ومصدر تقارير الأستاذ. |
| `journal_entry_details` | نعم | نعم | `JournalManager.php`, `PartnerLedger.php`, `AccountingEvents.php`, `DeliverySettlementManager.php` | مستهدف | تفاصيل المدين/الدائن والأبعاد والطرف. |
| `accounting_line_allocations` | نعم | نعم | `AccountingEvents.php`, `PartnerLedger.php`, `DeliverySettlementManager.php` | مستهدف | تخصيص التحصيلات على المديونيات. |
| `cash_boxes` | غير محسوم | نعم | `AccountSettings.php` | مستهدف | اختيار صندوق الفرع الافتراضي عند غياب طريقة الدفع؛ يلزم تتبع شاشة إدارة الصناديق. |
| `banks` | غير محسوم | غير محسوم | لم يثبت استعماله في الملفات الأساسية المفحوصة | مستهدف/غير محسوم | وجود الجدول لا يثبت اكتمال دورة البنوك. |
| `delivery_accounting_settings` | نعم | نعم | `DeliverySettlementManager.php` | مستهدف | إعداد طريقة تسوية عهدة المندوب. |
| `purchases_payments` | نعم | لم يثبت في العينة المحددة | `finance_BE.php` | قديم/انتقالي | تفاصيل دفعات المورد لكل أمر شراء. |
| `supplier_payment_allocations` | نعم | غير محسوم | `finance_BE.php` | انتقالي | ربط دفعة المورد بالفواتير أو المقدم. |
| `purchases_orders.TOTAL_PAYMENTS` | نعم | نعم | `finance_BE.php`, `PaymentDisbursement_BE.php` | قديم/تشغيلي مالي | حقل إجمالي مدفوع متكرر مع جداول الدفعات والأرصدة. |
| `orders.SALE_ENTRY_ID` | نعم | نعم | `AccountingEvents.php`, `DeliverySettlementManager.php`, `Order_tbl.php` | مستهدف/انتقالي | ربط قيد البيع بالطلب. |
| `orders.RETURN_ENTRY_ID` | نعم | نعم | `AccountingEvents.php` | مستهدف/انتقالي | ربط قيد المرتجع بالطلب. |
| `inventory_movement` و`inventory_product_movement` | نعم | نعم | `InventoryManager.php`, `inventory_BE.php`, `AccountingEvents.php` | تشغيلي | دليل استلام المرتجع المخزني قبل ترحيل المرتجع المالي. |

## 2. مصفوفة الأحداث والمسارات المكتشفة

| الحدث | المسار القديم الظاهر | المسار المحاسبي الجديد الظاهر | الخطر/الفجوة |
|---|---|---|---|
| تسليم/إثبات بيع | إنشاء `invoices` وتحديث `partners_balance` | `AccountingEvents::directSale()` ينشئ قيد البيع ويربطه بالطلب | يلزم تحديد ترتيب التنفيذ والذرية، وهل كل أنواع الطلبات تمر بنفس المسار. |
| تحصيل عميل مباشر | `financial_transactions` + تحديث `partners_balance` | `directSalePayment()` ينشئ قيد قبض وتخصيصًا | نفس الحدث له سجل قديم وقيد GL؛ يجب إثبات عدم تكرار الأثر عند إعادة الطلب. |
| رسوم الشحن | حركة تحصيل مستقلة ضمن الفاتورة | التخصيص/القيد من خلال `directSalePayment()` | يجب مطابقة مجموع التحصيل مع مديونية الطلب؛ لا نفترض أن وجود حركتين يعني خطأ قبل مراجعة تفاصيل المبالغ. |
| عمولة مندوب/مسوق | `partners_balance` | `DeliverySettlementManager` يتعامل مع عهدة المندوب والتخصيصات | مصدر الاستحقاق والصرف ما زال مختلطًا. |
| تسوية عهدة مندوب | مسارات قديمة عبر `finance_BE.php` | `DeliverySettlementManager.php` + `delivery_accounting_settings` | يلزم ربط كل واجهة باستدعائها الفعلي وتحديد المسار المستخدم. |
| مرتجع بضاعة | `partners_balance` وفاتورة/حركة مالية قديمة | `AccountingEvents::salesReturn()` بعد تأكيد الاستلام المخزني | يلزم تتبع الجزئي والمتكرر وتوقيت صرف مبلغ العميل. |
| صرف مبلغ المرتجع | `financial_transactions` + `partners_balance` | مسار ترحيل صرف مرتبط بحركة مالية | يلزم حصر رصيد العميل والمقدمات ومصدر النقد. |
| دفعة مورد | `financial_transactions` + `purchases_payments` + `TOTAL_PAYMENTS` + `partners_balance` + `supplier_payment_allocations` | لم يثبت بعد وجود مسار GL مكتمل في العينة | خمس طبقات مرتبطة لنفس الدفع تستلزم مطابقة دقيقة. |
| تقارير الأرصدة | `partners_balance` | `PartnerLedger.php` من القيود والتخصيصات | تقارير مختلفة قد تعرض مصدرين مختلفين للرصيد. |
| نقدية الفرع | `financial_transactions` وتصنيفاتها | إعداد ربط طريقة الدفع/الصندوق بحساب GL | شاشة الخزينة والتقارير البنكية لم يكتمل حصرها. |

## 3. الملفات الأساسية التي تم فحصها

| الملف | SHA وقت الفحص | نطاق الاستخدام المكتشف |
|---|---|---|
| `inc/controller/BackEnd/finance_BE.php` | `6049cdb929ca28483e45eb63c34276a4287e46e5` | الفواتير، الحركات المالية، أرصدة الأطراف، عمولات، دفعات الموردين، مرتجعات وصرف مستحقات. |
| `inc/controller/BackEnd/accounts_BE.php` | `a11bfca1a015d996fcbcdef1623597110846bb82` | ملخصات نقدية من `financial_transactions` وحسابات عهدة الشحن. |
| `inc/controller/BackEnd/reports_BE.php` | `8d4464266e806d1ea6ed4223918b6fae6cb314fe` | تقارير تعتمد على `partners_balance` وفواتير البيع. |
| `inc/controller/BackEnd/PaymentDisbursement_BE.php` | `4fe074f97085c8e5973851f9a26f371e66936288` | اختيار مستحقات العملاء/الموردين من أرصدة قديمة وإجماليات أوامر الشراء. |
| `inc/Classes/Accounting/AccountingEvents.php` | `50e6e7cd0284f5746a714c587e1b5dd7c5cb7a16` | ترحيل البيع والتحصيل والمرتجع، مع قراءة الحركات المالية القديمة. |
| `inc/Classes/Accounting/JournalManager.php` | `4d6b5f1aad91b62fbc29503dc65eab163a0b2b91` | حفظ رأس القيد وتفاصيله والتحقق من توازن القيد. |
| `inc/Classes/Accounting/PartnerLedger.php` | `31b9f2f23cca6d85ae19ff44180ca1b6b38f0dc3` | كشف الطرف من الأستاذ والتخصيصات دون `partners_balance`. |
| `inc/Classes/Accounting/DeliverySettlementManager.php` | `060588f78f762327cd5559a04c161f79a826111c` | إعدادات وتسوية عهدة المندوب من قيود الأستاذ. |
| `inc/Classes/Accounting/AccountSettings.php` | `00487844682aec2c6d7a9dab9dc70f029a70d21e` | إعداد حسابات الأحداث والصناديق وربط طرق الدفع. |
| `inc/Classes/Accounting/AccountManager.php` | `ae59e1a214e478f56b6f4ef0e563c22e8f5e6000` | إدارة دليل الحسابات. |
| `inc/Classes/Invoices_tbl.php` | `3858699cb778e2eab885a87265c08be54c276e4e` | إدخال فواتير قديمة مرتبطة بالطلبات. |
| `inc/Classes/Order_tbl.php` | `e59473a2038c2a78edede7c047299d0e9154d866` | قراءة الفواتير والحركات لتحديد حالة الطلب المالية. |
| `inc/Classes/InventoryManager.php` | `6f7ba9b2149c688a7915ff328aedad6c5ecee216` | إنشاء حركات المخزون التشغيلية. |
| `inc/controller/BackEnd/inventory_BE.php` | `0847790e785d846001d8af4bef064e571e14f1b9` | إدخال/قراءة/تحديث تفاصيل حركات المخزون. |

## 4. استنتاجات مؤكدة حتى الآن

1. لا تزال `partners_balance` و`financial_transactions` و`invoices` مستخدمة في كود الإنتاج الموجود بالمستودع؛ ليست جداول مهجورة.
2. الأستاذ العام مطبق جزئيًا في البيع والتحصيل والمرتجعات وتسوية المندوب، لكنه لا يمثل بعد المصدر الوحيد لكل المسارات والتقارير.
3. دورة الموردين ما زالت تعتمد على أكثر من تمثيل تشغيلي/مالي للدفعة، ولم يثبت في العينة مسار ترحيل GL متكامل لها.
4. تقارير الأرصدة القديمة لا تزال تقرأ `partners_balance`، بينما كلاس كشف الأستاذ يقرأ قيود GL.
5. وجود جدول أو كلاس لا يثبت وجود واجهة تشغيل مكتملة أو أن المسار مستخدم فعليًا.

## 5. ما لم يكتمل بعد

- تتبع نقاط استدعاء `finance_BE` و`AccountingEvents` و`DeliverySettlementManager` من AJAX والواجهات والصلاحيات.
- فحص كل ملفات Backend وDAO والمهام المجدولة وSQL Procedures/Views بحثًا عن الكتابة والقراءة المالية.
- مراجعة تفاصيل دوال التحصيل المقدم، إلغاء الطلب، الرصيد الدائن، صرف المرتجع، الصرف للمندوب والمسوق.
- مطابقة مخطط Installer مع كل الاستعلامات والأعمدة المطلوبة، دون افتراض أن المخطط يطابق قاعدة التشغيل.
- رسم تدفق مستقل للمبيعات والتحصيل والمقدمات والمرتجعات والموردين والخزائن والبنوك.

## 6. ضوابط المرحلة التالية

- لا تعديل PHP/SQL قبل اكتمال خريطة الاستدعاءات واعتماد قواعد الأحداث.
- لا حذف/تعطيل للجداول القديمة قبل ترحيل البيانات والمستهلكين والمطابقة.
- لا تغيير لسلوك مالي أو مخزني بناءً على قراءة جزئية.
- كل إضافة أو تغيير لاحق يسجل في وثيقة ما قبل/بعد التدقيق مع SHA للملفات ونتائج الاختبارات.

## توجيه التنفيذ الصحيح

هذه المصفوفة حصر مؤقت لتحديد ما يجب استبداله، وليست قائمة مكونات يجب الإبقاء عليها. كل صف مصنف قديمًا أو مختلطًا يمثل اعتمادًا يجب نقله إلى الخدمة/الحدث/التقرير الجديد، ثم إزالة استعماله التشغيلي. الجداول القديمة لا تستمر كمصدر بديل بعد القطع النهائي.

**معيار إغلاق كل اعتماد قديم:** تحديد البديل الجديد، ترحيل البيانات المطلوبة، تحويل كل المستهلكين، اختبار الوظيفة والنتائج والمطابقة، ثم إزالة القراءة/الكتابة القديمة وتوثيق ذلك في سجل ما قبل/بعد التغيير.

**ممنوع:** dual-write إنتاجي، أو تحديث أرصدة قديمة وجديدة لنفس الحدث، أو إبقاء تقارير تعتمد على الأرصدة القديمة بعد اعتماد الأستاذ العام.


## 7. إضافات الحصر من الواجهات ونقاط التحكم — 2026-11-05

المرجع: HEAD 64ed1a678c73900199d57688321c869afa6d9188. النتائج فحص ساكن فقط.

### 7.1 نقاط الدخول والواجهات
| النقطة | الاعتماد المثبت | اتجاه الاستبدال |
|---|---|---|
| finance.control.php | يجمع DeliverySettlementManager مع PaymentOfCustomerDues وSaveSupplierPayment وPaymentOfMarketerDues وPaymentOfDeliveryDues | فصل عمليات القبض والصرف والتخصيص والتسوية إلى خدمات جديدة |
| ReceivePayments.php | لوحة تسوية عهدة المندوب وطريقة الدفع وإجماليات الأوامر والعمولة | إبقاء الواجهة كعميل لخدمة التسوية الجديدة |
| PaymentDisbursement.control.php | GetRefundBalance عبر PaymentDisbursement_BE | استبدال رصيد المرتجع باستعلام الأستاذ والتخصيصات |
| accounts.control.php | ReceivePayments | تحويله لخدمة قبض وتخصيص واحدة |
| ordersList_BE.php وordersList_BE2.php | ربط قوائم الطلبات بجدول invoices | إزالة الربط بعد توفير مستند مالي جديد |
| shipment_BE.php | استلام وتسليم عهدة البضاعة وShipmentRefundFN وShipmentRefundFromClient وحركات refund المخزنية | فصل إثبات المرتجع المخزني عن قيد رد النقد |
| purchases_BE.php | إنشاء أمر شراء ومرتجع عبر DAO وتمرير totalPayments | فحص DAO لتحديد طبيعة المبلغ قبل تصميم خدمة المورد |

### 7.2 عمليات مالية مثبتة
| العملية | نقطة الدخول | المكون الحالي | الحالة |
|---|---|---|---|
| معاينة تسوية المندوب | finance.control.php / PreviewDeliverySettlement | DeliverySettlementManager | جديد جزئيًا ويحتاج اختبار |
| تسجيل تسوية العهدة | finance.control.php / SettleDeliveryCollections | DeliverySettlementManager | جديد جزئيًا |
| صرف عمولة المندوب | finance.control.php / PayDeliveryCommission | DeliverySettlementManager | جديد جزئيًا |
| تحصيل مديونية عميل | finance.control.php / PaymentOfCustomerDues | finance_BE | قديم ويستبدل |
| دفع مورد | finance.control.php / SaveSupplierPayment | finance_BE | قديم ويستبدل |
| صرف مستحق مسوق | finance.control.php / PaymentOfMarketerDues | finance_BE | قديم ويستبدل |
| صرف مستحق مندوب | finance.control.php / PaymentOfDeliveryDues | finance_BE | قديم/مختلط ويستبدل بخدمة موحدة |
| عرض رصيد مرتجع | PaymentDisbursement.control.php / GetRefundBalance | PaymentDisbursement_BE | قديم/انتقالي |
| استلام دفعات العملاء | accounts.control.php / ReceivePayments | accounts_BE | يحتاج تتبع SQL والدالة |

### 7.3 ملفات SQL الواجب فحصها
| الملف | التصنيف الأولي |
|---|---|
| Procedure_in_mysql.sql | غير محسوم حتى فحص المحتوى |
| procedure_partners_balance.sql | اعتماد قديم مرشح للإزالة بعد نقل الوظيفة |
| procedure_inventory.sql | تشغيلي؛ يجب فصل الأثر المالي عن المخزني |
| restClientDebtBatch.sql | غير محسوم |
| setDeliveryConfirmationOptimized.sql | تشغيلي مع احتمال استدعاء مالي |
| setRetunedConfirmationOptimized.sql | مخزني/تشغيلي مع احتمال استدعاء مالي |
| shipping_movement.sql | تشغيلي ولا يثبت وحده قيدًا ماليًا |
| migrations PHASE11 إلى PHASE16 | DDL وترحيل بيانات يجب فحصهما كلٌ على حدة |

### 7.4 استنتاجات وحدود
1. finance.control.php يمزج خدمات تسوية الأستاذ الجديدة مع عمليات مالية قديمة؛ القطع يجب أن يكون على مستوى العملية لا إنشاء الجداول فقط.
2. قوائم الطلبات لا تزال تقرأ invoices، لذلك يجب تحويلها ضمن خطة القطع.
3. حركة استلام المرتجع في shipment_BE.php إثبات مخزني/عهدة، وليست إيصال رد نقدي.
4. totalPayments الممرر إلى DAO لا يمكن تصنيفه دون فحص DAO.
5. البحث النصي السلبي لا يثبت غياب اعتماد غير مباشر.

### 7.5 المطلوب قبل عقود التنفيذ
تتبع AJAX actions، وفحص PurchaseOrderDAO.php وSupplyOrderDAO.php، ومراجعة SQL procedures، وحصر شاشات الخزائن والبنوك والمصروفات والإيرادات والتحويلات والتقارير وكشف الحساب. بعدها تثبيت نموذج الحدث المالي وidempotency والقيود والتخصيصات وعقود الخدمات.

لم يتم تعديل PHP أو SQL، ولم يتم تنفيذ SQL أو نشر تغييرات على الاستضافة في هذه الجولة.
