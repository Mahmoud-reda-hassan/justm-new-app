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


## 8. اعتماديات SQL وDAO مثبتة — 2026-11-05

| الملف/الدالة | الاعتماد المثبت | التصنيف والبديل المطلوب |
|---|---|---|
| PurchaseOrderDAO.php / insertOrder | يحفظ TOTAL_PAYMENTS ثم المنتجات ثم يستدعي finance->addSupplierDebt؛ فشل رصيد الشريك يفشل إنشاء الأمر | اعتماد قديم حرج؛ يجب إنشاء مستند المورد/قيد الاستحقاق ضمن معاملة واحدة دون partners_balance |
| PurchaseOrderDAO.php / insertReturnOrder | يحفظ TOTAL_PAYMENTS والمنتجات ثم يستدعي finance->addSupplierCredit؛ فشل رصيد الشريك يفشل المرتجع | اعتماد قديم حرج؛ يستبدل بقيد مرتجع المورد وتسوية الذمة |
| procedure_partners_balance.sql / restClientDebtBatch | يقرأ confirm_staging وorders وorder_product ويبني checkorders ثم يكتب partners_balance | اعتماد قديم حرج ضمن تأكيد التسليم؛ يجب نقل احتساب الذمة إلى خدمة أحداث GL |
| procedure_partners_balance.sql / reduceClientCreditBatch | يبني بيانات المرتجع ثم يحدث partners_balance ويضيف رصيدًا دائنًا بفئة 14 | اعتماد قديم حرج ضمن تأكيد المرتجع؛ يستبدل بحدث مرتجع بعد الاستلام المخزني وتخصيص/رصيد عميل في GL |
| Procedure_in_mysql.sql | يحتوي تعريفات restClientDebtBatch وreduceClientCreditBatch ويذكر partners_balance صراحة | نسخة SQL أخرى؛ يلزم إثبات مصدر النشر والاستدعاء قبل إيقافها |

**ملاحظة:** وجود الإجراءين في ملفين لا يثبت أن كليهما منشور. يجب فحص الاستدعاءات الفعلية من PHP وبيئة قاعدة البيانات قبل خطة الإزالة. لا تنفذ drop أو alter في هذه المرحلة.

### 8.1 أثر معماري مهم
تأكيد التسليم/المرتجع حاليًا قد يحدّث الأرصدة من SQL مباشرة؛ لذلك لا يكفي استبدال finance_BE أو التقارير. يجب أن يصبح الإجراء التشغيلي مسؤولًا عن تجهيز/تأكيد حركة المخزون فقط، وأن يطلق حدثًا ماليًا ذريًا واحدًا بمعرف فريد، ثم يسجل القيد والتخصيصات في GL. إنشاء أمر الشراء ومرتجعه يجب ألا يتوقف على رصيد الشريك القديم.


## 9. ربط واجهات JavaScript بالعمليات المالية

| ملف الواجهة | الطلب المثبت | Backend | ملاحظة الاستبدال |
|---|---|---|---|
| dist/js/DeliverySettlement.js | PreviewDeliverySettlement وSettleDeliveryCollections | finance.control.php | اتصال مباشر بخدمة التسوية الجديدة الجزئية |
| dist/js/PaymentDisbursement.js | GetRefundBalance | PaymentDisbursement.control.php | يعتمد على Backend الصرف/رصيد المرتجع القديم |
| dist/js/PaymentDisbursement.js | PaymentOfCustomerDues وSaveSupplierPayment وPaymentOfMarketerDues وPaymentOfDeliveryDues | finance.control.php | أربع عمليات صرف/تحصيل ما زالت تستدعي finance_BE |
| dist/js/ReceivePayments.js | CONTROL=ReceivePayments | accounts.control.php | تحميل قائمة التحصيل؛ يلزم استكمال تتبع أحداث الأزرار |
| dist/js/AddPurchaseOrder.js | listPurchasesDT | purchases.control.php | قائمة المشتريات؛ يلزم تتبع عملية الحفظ نفسها |

الفحص يثبت تكوين عناوين الطلبات وأسماء بعض actions فقط؛ لا يثبت استجابة HTTP أو نجاح الحفظ في بيئة التشغيل.


## 10. الخزائن والبنوك والسندات والتقارير — مصفوفة الاعتماديات

| المجال | المصدر الحالي المثبت | حالة التنفيذ | البديل المطلوب |
|---|---|---|---|
| دليل الحسابات | AccountManager.php | تنفيذ جزئي | استكمال دورة الحسابات والصلاحيات ومنع تعديل الحسابات المستخدمة |
| ربط طرق الدفع | AccountSettings.php / payment_method_accounts | يوجد lookup | إلزام كل قبض/صرف باختيار حساب الدفع الفعلي والتحقق من الفرع |
| الصندوق | cash_boxes + AccountSettings::cash | جدول/lookup فقط؛ CashBox.php فارغ | CashBoxService وحركة صندوق مستندة إلى قيود الأستاذ |
| البنك | إعداد BANK وpayment_method_accounts | Bank.php فارغ؛ لا مطابقة بنكية مثبتة | BankService وتحويلات ومطابقة كشف البنك |
| سندات القبض | CashReceiptVouchers.php وعمليات متفرقة في finance_BE | لا يوجد backend عام مثبت؛ JS يشير إلى endpoints غير ظاهرة في شجرة المستودع | مستند قبض موحد ببنود وتخصيصات ووسيلة دفع وصندوق/بنك |
| سندات الصرف | PaymentDisbursement.php وعمليات PaymentOf* في finance_BE | عمليات متفرقة ومتصلة بالجداول القديمة | سند صرف موحد مع مستفيد وبنود ومركز تكلفة واعتماد |
| كشف حساب طرف | reports_BE::AccountStatement | يقرأ partners_balance مباشرة | PartnerLedger + تخصيصات GL |
| تقرير حركة البيع | reports_BE::SalesMovementReport | يربط invoices | ربط بمستند البيع/القيد الجديد |
| تقرير المسوق | marketerDailyReport/marketerDailyDetails | يقرأ partners_balance | استخراج الاستحقاق والصرف من قيود الأستاذ وتخصيصاتها |
| ملخص العهدة/النقدية | accounts_BE::custodyTotal | financial_transactions + transaction_categories | أرصدة عهدة المندوب والصندوق/البنك من GL منفصلة |
| رصيد/مرتجع العميل | PaymentDisbursement_BE | partners_balance | PartnerLedger وأرصدة الدائنين وتخصيصاتهم |
| رصيد المورد | PaymentDisbursement_BE + purchases_orders.TOTAL_PAYMENTS | مصادر مختلطة | ذمم المورد من GL، وتفاصيل المستند من أوامر الشراء والتخصيصات الجديدة |

### 10.1 ملفات ذات تنفيذ صفري
Bank.php وCashBox.php وReports.php وPartnerBalance.php في Classes/Accounting فارغة (0 بايت) في المستودع وقت الفحص. لا تعتبر أسماء الكلاسات دليلًا على وجود الوظائف.

### 10.2 نقاط واجهة غير مثبتة داخل المستودع
dist/js/CashReceiptVouchers.js يستدعي get_mandob_data.php وget_mandob_balance.php وget_masouq_data.php وget_masouq_balance.php وget_morad_data.php وget_morad_balance.php وget_client_data.php. هذه الملفات لم تظهر في شجرة GitHub الحالية؛ يلزم التحقق من كونها ملفات نشر خارج المستودع قبل حذف/استبدال الواجهة.

### 10.3 التقارير التي يجب قطع اعتمادها القديم
- AccountStatement: partners_balance.
- SalesMovementReport: invoices.
- marketerDailyReport وmarketerDailyDetails: partners_balance.
- custodyTotal: financial_transactions وtransaction_categories.
- GetClientData/GetClientRetrnOrder/GetRefundBalance/GetPartnersBalance: partners_balance.

لا يتم حذف التقارير؛ يعاد بناؤها على قيود الأستاذ والتخصيصات مع الحفاظ على نفس الفلاتر ومخرجات الواجهة المطلوبة.


## 11. مخاطر مخطط Installer التي يجب إغلاقها قبل التنفيذ

| الجدول/الحقل | ما ثبت في DDL | الخطر | الإجراء المطلوب |
|---|---|---|---|
| journal_entries.reference_type/reference_id | فهرس غير فريد | يمكن نظريًا تكرار قيد نفس المصدر | اعتماد unique event/source key بعد فحص القيود التاريخية |
| accounting_line_allocations | لا يوجد unique لزوج debit_line_id/credit_line_id | احتمال تكرار تخصيص الزوج نفسه | منع التكرار والتحقق من المتبقي تحت قفل داخل الخدمة، مع قيد مناسب بعد معالجة التاريخ |
| cash_boxes | أكثر من صندوق لكل فرع ممكن | AccountSettings::cash يختار أول صندوق فقط | اختيار صندوق صريح في مستند القبض/الصرف وربطه بالفرع والحساب |
| banks | لا PK/FK/unique ظاهر، account_id INT | ضعف التكامل وتعارض النوع مع accounts.id BIGINT UNSIGNED | مطابقة Backup ثم إصلاح المفتاح والنوع والعلاقة |
| cost_centers | id وparent_id من نوع INT | journal_entry_details.cost_center_id من نوع BIGINT UNSIGNED | توحيد النوع وإضافة العلاقات المناسبة بعد فحص البيانات |
| financial_transactions | accounting_posted وaccounting_entry_id | المحرك الحالي يربط ترحيل GL بسجل قديم | بناء مرجع حدث جديد ثم نقل كل المستهلكين قبل إيقاف الجدول |
| supplier_payment_allocations | financial_transaction_id يشير منطقيًا للحركة القديمة | لا يمكن حذف الحركة القديمة مع بقاء التخصيص | نقل رأس الدفعة والتخصيصات إلى مستندات الدفع الجديدة |
| delivery_* | رؤوس collections/settlements/commission وروابطها | لا يوجد مفتاح idempotency عام لكل الأحداث | حماية تفرد الحدث على مستوى كل مصدر ومرحلة/جزء |

**ملاحظة:** هذه ملاحظات على DDL في Installer، وليست نتيجة `SHOW CREATE TABLE` من قاعدة التشغيل. لا يتم تعديل Installer قبل مطابقة Backup وبيانات المفاتيح الحالية.

## إضافة اعتماديات المرحلة — FinancialEventManager

| المكوّن | الاعتماد الجديد | الحالة |
|---|---|---|
| FinancialEventManager | PDO + accounting_events + journal_entries | أضيفت الخدمة والجدول إلى Installer |
| JournalManager | نفس اتصال PDO المستخدم في معاملة الحدث | مطلوب داخل callback |
| AccountingEvents | لم يُحوّل بعد إلى الخدمة | ما زال يعتمد جزئيًا على financial_transactions |
| نقاط البيع والتحصيل | تحتاج مفتاح idempotency ثابتًا لكل حدث تشغيلي | لم تُربط بعد |

تنبيه: إضافة الخدمة لا تعني أن النظام القديم قد توقف. يظل القطع النهائي محظورًا حتى تحويل كل المستهلكين، ترحيل التاريخ المطلوب، مطابقة الأرصدة، وتشغيل اختبارات القبول.

## تحويل البيع المباشر

| المسار | الاعتماد قبل التحويل | الاعتماد بعد التحويل | الحالة |
|---|---|---|---|
| `AccountingEvents::directSale()` | `JournalManager` مباشرة وربط `orders.SALE_ENTRY_ID` | `FinancialEventManager` ثم `JournalManager` داخل callback | محول |
| مبلغ البيع المباشر | المنتجات + `SHIPPING_VAL` | المنتجات فقط | تم تصحيح الشحن |
| تكلفة ومخزون البيع | `COST_AMOUNT` | `COST_AMOUNT` كما هو | محفوظ |
| إعادة طلب الترحيل | فحص `SALE_ENTRY_ID` فقط | مفتاح حدث ثابت يعيد القيد السابق | محسن |

لم يتم تحويل البيع عبر الشحن أو تحصيلات العميل بعد.

## 8. تحديث التنفيذ — فرع GL-only cutover، 2026-11-07

**النطاق المنفذ على فرع العمل فقط:** لا يوجد دمج إلى `main` ولا تطبيق على قاعدة اختبار/إنتاج.

### 8.1 المسارات التي أصبحت GL-only

| العملية | التغيير الحالي | الجداول/المصدر الجديد |
|---|---|---|
| عمولات المسوق وقائد الفريق | استحقاق مستقل، عكس عند المرتجع المخزني، وصرف مع طريقة الدفع الفعلية | `2152` مستحقات، `5230` مصروف، `marketer_commission_payments`، `journal_entries` |
| صرف عمولة المندوب | شاشة الصرف تستخدم طريقة الدفع وحساب المستحقات من GL؛ تعطيل endpoint القديم | `delivery_commission_payments`، `delivery_accounting_settings`، `journal_entry_details` |
| رد مبلغ مرتجع العميل | خدمة مستقلة، لا تسمح بالرد قبل وجود `RETURN_ENTRY_ID` وحالة مرتجع مخزني، وتمنع تجاوز الرصيد المتاح | `customer_refund_payments` وقيود GL |
| رصيد المرتجع المعروض | قائمة العملاء/الأوامر/الرصيد أصبحت تقرأ رصيد مردود البيع من GL وتخصم الردود الجديدة والردود التاريخية المترحلة إلى GL | `journal_entry_details` + سجل ردود GL |
| كشف حساب العميل | أصبح يقرأ حساب `CUSTOMERS` من GL مع ربط البيع والشحن والمرتجع والتحصيلات بالأوامر | `journal_entries`, `journal_entry_details`, `account_settings` |
| كشف حساب المندوب | يجمع حساب العهدة وحساب مستحقات العمولة وفق صافي رصيد الشريك | `delivery_accounting_settings` + قيود GL |
| كشف حساب المسوق | يقرأ حساب المستحقات المستقل من GL | `MARKETER_COMMISSION_PAYABLE` |
| التقارير اليومية للمسوق | استبدال `partners_balance` بقيود استحقاق العمولة وعكسها | `MARKETER_COMMISSION_ACCRUAL/REVERSAL` |

### 8.2 حسابات عمولات التسويق

- `2152`: مستحقات عمولات المسوقين وقادة الفرق — التزام/دائن.
- `5230`: مصروف عمولات المسوقين وقادة الفرق — مصروف/مدين.
- الربط يتم بمفاتيح `MARKETER_COMMISSION_PAYABLE` و`MARKETER_COMMISSION_EXPENSE` داخل `account_settings`، دون تثبيت أرقام IDs.
- الحسابان ينشآن في Migration وملفي Installer عند عدم وجود الكودين.

### 8.3 ردود المرتجعات التاريخية

خدمة الرد الجديدة لا تكتب في `invoices` أو `financial_transactions` أو `partners_balance`. لكنها تقرأ قيود ردود المرتجعات القديمة المترحلة إلى GL عبر `SALES_RETURN_PAYMENT` فقط عند احتساب الرصيد المتبقي، حتى لا يعاد صرف مبلغ سبق رده.

### 8.3.1 تجهيز حسابات الموردين للمرحلة التالية

تمت إضافة مفاتيح ربط إلى `AccountSettings` وMigration `PHASE19_SUPPLIER_ACCOUNT_SETTINGS.sql` وفق دليل الحسابات الحالي:
- `SUPPLIERS` ← 2111 حسابات الموردين.
- `INVENTORY_IN_TRANSIT` ← 1162 مخزون بالطريق.
- `SUPPLIER_ADVANCES` ← 1171 دفعات مقدمة للموردين.

تم تحويل الأحداث الجديدة على فرع العمل: أمر الشراء يثبت المخزون بالطريق مقابل المورد، والاستلام الفعلي يعيد التصنيف من المخزون بالطريق إلى المخزون، ومرتجع المورد لا يخفض مستحقه إلا بعد حركة الصرف المخزني، ودفعات المورد تسجل في GL مع توزيع الفواتير والمقدم غير المخصص. بقيت أرصدة الموردين التاريخية في partners_balance دون تعديل أو ترحيل افتتاحي. شاشة اختيار المورد وصرف المستحقات تعرض total_balance تشغيليًا باعتباره صافي (الرصيد التاريخي + حركات GL)، مع إرجاع legacy_balance وgl_balance منفصلين للتدقيق؛ أما كشف الحساب فيعرض رصيدًا افتتاحيًا وحاليًا مستقلًا لكل مسار حتى لا تختلط الحركات أو يُفهم الجمع على أنه ترحيل افتتاحي.

### 8.4 اعتماديات قديمة ما زالت مفتوحة

| المسار | الوضع الحالي | المطلوب قبل القطع النهائي |
|---|---|---|
| `CollectingFinancialPledge` / `finance_BE::registerCollection()` | تم تعطيل endpoint القديم؛ شاشة الأوامر تسجل الآن `RegisterDeliveryCustomerCollection` وتضيف التحصيل إلى عهدة المندوب، ولوحة التسوية تستقبل العهدة لاحقًا | يلزم اختبار دورة كاملة على بيانات اختبار والتأكد من تحديث حالة الطلب/العهدة التشغيلية دون الاعتماد على `ORDER_SUCCESS` القديم |
| `PaymentOfSupplierDues()` و`SaveSupplierPayment` | تم تحويل الدفعات الجديدة إلى GL مع الاحتفاظ بـ`purchases_payments` و`TOTAL_PAYMENTS` كبيانات تشغيلية، دون كتابة `financial_transactions` أو `partners_balance` | اختبار دورة كاملة ومراجعة كشف الحساب على قاعدة اختبار |
| كشف حساب المورد | يعرض رصيد `partners_balance` التاريخي وحركات حساب الموردين/المقدمات في GL كلًا على حدة | مطابقة الرصيدين بشكل مستقل ثم اعتماد ميزان افتتاحي قبل القطع النهائي |
| `directSalePayment()` و`salesReturnPayment()` التاريخيان | ما زالا يقرآن مستندات `invoices/financial_transactions` لإعادة بناء قيد تاريخي | نقل مصدر البيانات إلى أحداث/مستندات GL-native ثم إيقاف إنشاء المستندات القديمة |
| `partners_balance` في إجراءات SQL وقوائم الطلبات | توجد قراءات/كتابات أخرى خارج الشاشات التي تم تحويلها | استكمال الحصر على كل Procedures وDAO وواجهات، ثم إزالة كل كتابة قديمة بعد ضمان البديل |

### 8.5 الاختبارات

- اجتازت نسخة `4fb90be` تشغيل CI رقم `42384352499` بالكامل: PHP 8.0/8.4، فحص JavaScript للواجهات المحولة، استيراد Installer الجذر وInstaller `inc`، الفحوص الساكنة واختبارات Financial Event على MariaDB 10.5.29 و10.11.
- نجاح CI يثبت الفحص النحوي واستيراد المخطط والاختبارات الموجودة؛ لا يثبت تجربة واجهة فعلية أو مطابقة أرصدة بيانات حقيقية.
- لا توجد اختبارات متصفح فعلية أو تجربة بيانات إنتاجية حتى الآن.

### 8.6 ضوابط القطع النهائي

- لا تُحذف جداول `partners_balance` أو `invoices` أو `financial_transactions` قبل إكمال الموردين والتحصيل والتسويات التاريخية.
- لا يُسمح بالكتابة المزدوجة لنفس الحدث.
- لا تتغير دورة المخزون أو عهدة البضاعة ضمن هذا التحويل.
- لا يتم دمج الفرع أو تطبيق Migration على testar دون مراجعة واعتماد المستخدم.
- تحديث 2026-11-07: شاشة تحصيل المندوب أصبحت تحسب المديونية المفتوحة من قيود العميل وتخصيصات `accounting_line_allocations`، وملخص النقدية من حسابات الصناديق وطرق الدفع في GL؛ تم إيقاف `CollectingFinancialPledge` القديم.
