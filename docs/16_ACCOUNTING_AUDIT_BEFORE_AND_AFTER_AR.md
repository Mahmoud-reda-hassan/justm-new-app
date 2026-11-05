# سجل تدقيق دورة الحسابات — قبل التنفيذ

**التاريخ:** 2026-11-05  
**الحالة:** خط أساس موثق — لم يبدأ تعديل الكود أو قاعدة البيانات.  
**المستودع:** `Mahmoud-reda-hassan/justm-new-app`  
**الفرع:** `main`  
**Commit خط الأساس:** `31c970e3e34f32ab95be1f84d7d91c4f3f3d87ea`

## 1. الهدف
حصر دورة الحسابات الحالية بالكامل تمهيدًا لاستبدالها بدورة مالية موحدة يكون الأستاذ العام مصدر الحقيقة الوحيد بعد اكتمال الانتقال. هذا التدقيق ليس تنفيذًا للتصميم الجديد، ولا يجيز حذف الجداول القديمة أو تعديل سلوكها.

## 2. المرجع المعماري
الوثيقة الحاكمة: `docs/13_NEW_ACCOUNTING_ARCHITECTURE_AND_MIGRATION_PLAN_AR.md`.

تُراجع معها الوثائق التاريخية ذات الصلة:
- `docs/09_LEGACY_ACCOUNTING_AND_FINANCE_AR.md`
- `docs/10_PARTNER_STATEMENTS_AND_CASH_OPERATIONS_AR.md`
- `docs/11_GENERAL_LEDGER_CASH_BANK_AND_FINANCIAL_MAP_AR.md`
- `docs/12_DATABASE_SCHEMA_AND_RELATIONSHIP_AUDIT_AR.md`

## 3. حدود هذه المرحلة
- قراءة الكود وSQL والواجهات والتقارير والمهام ذات الصلة.
- تحديد كل موضع قراءة أو كتابة، والحدث التشغيلي الذي يسبقه أو يتبعه.
- تصنيف كل مكون: قديم، مستهدف، أو انتقالي/غير محسوم.
- رسم الاعتماديات ومخاطر التكرار أو اختلاف الأرصدة.
- عدم تعديل PHP أو SQL أو بيانات قاعدة البيانات في مرحلة الحصر.
- عدم حذف أو تعطيل أي جدول أو مسار قديم.

## 4. نطاق الحصر الأولي
يشمل — دون افتراض أن القائمة نهائية — الجداول:
`partners_balance`, `invoices`, `financial_transactions`, `transaction_categories`, `payment_methods`, `purchases_payments`, `accounts`, `account_settings`, `journal_entries`, `journal_entry_details`, `accounting_line_allocations`, `cash_boxes`, `banks`, `delivery_accounting_settings`.

ويشمل كذلك المستندات التشغيلية المرتبطة بالمبيعات والمشتريات والشحن والعهدة والمرتجعات والمخزون.

## 5. المخرجات المطلوبة
1. مصفوفة اعتماديات: الجدول/الكيان، نوع الاستخدام (قراءة/كتابة)، الملف والكلاس والدالة، الحدث، حالة الاعتماد، وملاحظات المخاطر.
2. خريطة تدفق لكل حدث مالي من واجهة المستخدم إلى قاعدة البيانات ثم التقارير.
3. قائمة بالتعارضات والكتابات المزدوجة والحقول التي تعمل كأرصدة مشتقة أو مصادر حقيقة متنافسة.
4. قائمة قرارات تحتاج اعتمادًا قبل تصميم البديل.
5. سجل بعد التنفيذ يوضح ما تغير وما لم يتغير والاختبارات الفعلية ونتائجها.

## 6. قواعد الإثبات
- لا يُسجل أي جدول على أنه مستخدم أو مهجور إلا بدليل من المصدر.
- نفرق بين وجود الجدول في Installer وبين وجوده/تطابقه في قاعدة فعلية.
- نفرق بين استعلام PHP وبين تنفيذ المسار فعليًا.
- لا نعتبر فحص الصياغة أو البحث النصي اختبار تكامل.
- أي عنصر غير قابل للإثبات يُوسم **غير محسوم** بدل التخمين.

## 7. حالة خط الأساس
تم تثبيت رأس الفرع `main` عند Commit المذكور أعلاه قبل بدء الحصر. لا توجد في هذا السجل تغييرات كود أو DDL أو نشر على الاستضافة. سيتم تحديث هذا السجل بعد اكتمال كل مرحلة، مع الاحتفاظ بسجل ما قبل التغيير وما بعده.


## 8. نتائج الفحص الأولي للمصدر — بعد تثبيت خط الأساس

> هذه النتائج تثبت وجود الاستعلامات/المسارات في المصدر فقط، ولا تثبت أن كل مسار يُستدعى فعليًا في بيئة التشغيل.

### 8.1 تعايش مسارين ماليين

- `finance_BE.php` ينشئ فواتير في `invoices`، وحركات قبض/صرف في `financial_transactions`، وأرصدة أطراف في `partners_balance`.
- في مسار تأكيد التسليم/التحصيل المباشر، يستدعي `AccountingEvents::directSalePayment()` لترحيل الحركة إلى الأستاذ العام، ثم يضيف أيضًا رصيدًا دائنًا في `partners_balance`.
- تحصيل رسوم الشحن يظهر في المصدر كحركة تحصيل منفصلة مرتبطة بالفاتورة نفسها، مع استدعاء ترحيل مستقل لكل حركة. يلزم تتبع القيمة المحصلة ومقدار مديونية العميل لمنع التكرار أو تجاوز التخصيص.
- `accounts_BE.php` يحسب CASH_IN/CASH_OUT من `financial_transactions` و`transaction_categories`، بينما `reports_BE.php` و`PaymentDisbursement_BE.php` يقرآن `partners_balance`.
- `PartnerLedger.php` يقرأ أرصدة الأطراف من `journal_entry_details` والقيود المرحلة، ويستخدم `accounting_line_allocations` لحساب المفتوح.

### 8.2 المندوب والتسوية

- `DeliverySettlementManager.php` يستخدم `delivery_accounting_settings` وقيود الأستاذ والتخصيصات، ويصرح في تعليق الكلاس بأنه لا يقرأ أو يحدث `partners_balance`.
- `finance_BE.php` ما زال يسجل عمولات المندوب والمسوق في `partners_balance` عبر `Add_partners_balance()`، كما يسجل صرف مستحقات المندوب والمسوق بالطريقة القديمة.
- إذن مسار استحقاق العمولة/الصرف لا يزال مختلطًا بين النظام القديم ومدير التسوية الجديد، ويلزم تحديد نقطة بدء كل مسار ومستهلكيه.

### 8.3 الموردون والمشتريات

- `finance_BE.php` يسجل دفعة المورد في `financial_transactions`، وتفاصيل التوزيع في `purchases_payments`، ويحدّث `purchases_orders.TOTAL_PAYMENTS`، ويضيف رصيدًا في `partners_balance`، ويسجل `supplier_payment_allocations`.
- هذه أربعة تمثيلات مرتبطة لنفس دفعة المورد، ويجب مطابقتها قبل اعتماد مصدر الحقيقة الجديد.
- `PaymentDisbursement_BE.php` يعرض التزامات المورد باستخدام `purchases_orders.TOTAL_PAYMENTS` ومرتجعات الشراء المؤجلة، وليس من دفتر الأستاذ العام.

### 8.4 القيود والمرتجعات

- `JournalManager.php` ينشئ رؤوس القيود في `journal_entries` وتفاصيلها في `journal_entry_details` ويتحقق من توازن المدين والدائن في مسار الترحيل.
- `AccountingEvents.php` يربط قيد البيع والمرتجع بأعمدة `orders.SALE_ENTRY_ID` و`orders.RETURN_ENTRY_ID`.
- مسار مرتجع المبيعات يتحقق من استلام الكميات في `inventory_product_movement` قبل إنشاء قيد المرتجع، وفق الكود المفحوص.
- تحصيلات المبيعات وصرف المرتجعات لا تزال تُقرأ من `financial_transactions` مع ربط `invoices` و`transaction_categories` قبل ترحيلها إلى الأستاذ.

### 8.5 حدود الفحص الحالي

- تم فحص الملفات الأساسية المحددة في مصفوفة الاعتماديات، وليس كل ملفات المشروع البالغ عددها أكثر من خمسة آلاف ملف.
- لم يتم بعد تتبع جميع نقاط الاستدعاء من AJAX والواجهات والـ controllers إلى الدوال أعلاه.
- لم يتم تشغيل SQL أو PHP، ولم يتم اختبار مسارات التشغيل أو التزامن أو فشل المعاملات.
- لم يتم تعديل أي PHP أو SQL أو بيانات قاعدة بيانات.
- تظل الجداول القديمة قيد الاستخدام الظاهر في المصدر، ولا يجوز حذفها أو تعطيلها في هذه المرحلة.


## تصحيح اتجاه المشروع — 2026-11-05

**قرار صاحب المشروع:** إلغاء الدورة المالية القديمة وتنفيذ الدورة الجديدة بالكامل. الحصر الحالي ليس هدفًا للحفاظ على النظام القديم ولا لتشغيله كخيار احتياطي؛ هو إجراء اكتشافي مؤقت لفهم الوظائف والبيانات والاعتماديات حتى لا تضيع وظيفة مطلوبة أو بيانات تاريخية أثناء الاستبدال.

- النظام القديم مصدر لفهم السلوك والبيانات فقط، وليس مسارًا مستهدفًا.
- المطلوب تحويل الوظائف إلى النظام الجديد، ثم إزالة استدعاءات القراءة والكتابة التشغيلية القديمة.
- لا يُسمح بتشغيل محاسبتين إنتاجيتين بالتوازي أو تسجيل الأثر المالي مرتين.
- المقارنة قبل القطع تكون في بيئة اختبار/نسخة معزولة أو تشغيل ظل لا يكرر القيود.
- بعد نجاح الترحيل والمطابقة والاختبارات، تُوقف الجداول والمسارات القديمة وتُؤرشف أو تُحذف حسب سياسة الاحتفاظ.
- لا يعني هذا حذف التاريخ قبل ترحيله أو قبل التأكد من إمكانية الرجوع إلى المستندات المطلوبة قانونيًا وتشغيليًا.



## 9. استكمال الحصر — الواجهات ونقاط التحكم (2026-11-05)

مرجع الفحص: HEAD 64ed1a678c73900199d57688321c869afa6d9188. هذه مراجعة ساكنة للمصدر وليست اختبار تشغيل.

### 9.1 نقاط الدخول المثبتة
- inc/controller/finance.control.php يجمع بين DeliverySettlementManager وعمليات PaymentOfCustomerDues وSaveSupplierPayment وPaymentOfMarketerDues وPaymentOfDeliveryDues، إضافة إلى معاينة وتسوية عهدة المندوب وصرف عمولته. هذا يثبت اختلاط خدمات GL الجزئية مع دوال finance_BE القديمة في نقطة دخول واحدة.
- inc/Views/ReceivePayments.php يحتوي لوحة تسوية عهدة المندوب، اختيار طريقة الدفع، إجماليات الأوامر والعمولة، وزر تسجيل استلام العهدة، وتبويب قيد التسوية.
- inc/controller/PaymentDisbursement.control.php يحمّل PaymentDisbursement_BE.php ويعرض GetRefundBalance.
- inc/controller/accounts.control.php يمرر ReceivePayments إلى Backend الحسابات.
- inc/controller/BackEnd/ordersList_BE.php وordersList_BE2.php يربطان قوائم الطلبات بجدول invoices؛ يجب نقل مؤشر المستند المالي إلى المصدر الجديد.
- inc/controller/BackEnd/shipment_BE.php يحتوي ShipmentRefundFN وShipmentRefundFromClient وحركات مخزون refund. هذا مسار مخزني/عهدة ولا يثبت وحده ردًا نقديًا.

### 9.2 المشتريات والموردون
- inc/controller/BackEnd/purchases_BE.php يفوض إنشاء أمر الشراء ومرتجع الشراء إلى DAO عبر insertOrder وinsertReturnOrder ويمرر totalPayments. يلزم فحص DAO لتحديد هل هو مقدم أو دفعة فعلية أو إجمالي محفوظ.
- لم يثبت حتى الآن مسار GL كامل لفاتورة المورد ودفعاتها وتخصيصاتها ومرتجع الشراء ورد المبلغ.
- عدم ظهور أسماء الجداول في فحص supplyOrders_BE.php وreturnSelse_BE.php لا يثبت عدم وجود أثر غير مباشر عبر DAO أو استدعاءات أخرى.

### 9.3 SQL والواجهات
ملفات SQL المطلوب استكمالها: Procedure_in_mysql.sql وprocedure_partners_balance.sql وprocedure_inventory.sql وrestClientDebtBatch.sql وsetDeliveryConfirmationOptimized.sql وsetRetunedConfirmationOptimized.sql وshipping_movement.sql ومigrations من PHASE11 إلى PHASE16. وجودها بالمستودع لا يثبت نشرها على قاعدة التشغيل، ولم يتم تنفيذ أي منها.
ملفات الواجهة ذات الصلة: CashReceiptVouchers.js وReceivePayments.js وDeliverySettlement.js وPaymentDisbursement.js وAddPurchaseOrder.js وreports.js. لم يكتمل تتبع كل AJAX action فيها.

### 9.4 سجل الجولة
التغييرات: تحديث وثيقتي التدقيق والمصفوفة فقط؛ لا تعديل PHP أو SQL أو بيانات. الاختبارات: قراءة شجرة GitHub وفحص محتوى الملفات واستخراج مواضع العمليات. لا PHP lint ولا SQL import ولا runtime/API test. النتيجة: اكتشاف نقطة تحكم تمزج المسارين، واستمرار ربط قوائم الطلبات بجدول invoices. المتبقي: تتبع AJAX وDAO وSQL والتقارير قبل تصميم عقود الأحداث.


## 10. إثبات الاعتماد المباشر على إجراءات SQL وDAO — 2026-11-05

- PurchaseOrderDAO::insertOrder يحفظ purchases_orders.TOTAL_PAYMENTS، ثم يحفظ المنتجات، ثم يستدعي finance->addSupplierDebt؛ وإذا فشل يعتبر أمر الشراء غير مؤكد برسالة partner balanceis not confirmed.
- PurchaseOrderDAO::insertReturnOrder يحفظ purchases_return_orders.TOTAL_PAYMENTS والمنتجات، ثم يستدعي finance->addSupplierCredit؛ والفشل مرتبط برسالة partner balanceis not confirmed. إذن رصيد المورد القديم شرط نجاح صريح لإنشاء أمر الشراء/مرتجعه، وليس مجرد تقرير.
- inc/Databases/procedure_partners_balance.sql يحتوي restClientDebtBatch وreduceClientCreditBatch. الأول يبني checkorders من confirm_staging وorders وorder_product ثم يكتب/يحدث partners_balance. الثاني يبني refund_orders من بيانات المرتجعات ثم يحدث partners_balance ويضيف رصيدًا دائنًا لفئة 14.
- inc/Databases/Procedure_in_mysql.sql يحتوي نسخًا من الإجراءات ذاتها، ويوثق partners_balance ضمن الجداول المستخدمة. يجب تحديد أي نسخة تُستدعى/تُثبت فعليًا قبل إيقافها؛ لا يمكن افتراض التطابق التشغيلي من وجود الملفين.
- بذلك يمتد الاعتماد القديم إلى DAO وإجراءات SQL التي تؤكد التسليم والمرتجعات، وليس إلى finance_BE والتقارير فقط.

### 10.1 قرار هندسي مطلوب للتنفيذ
يجب نقل حساب أثر تأكيد التسليم والمرتجع إلى خدمة/حدث مالي ذري قابل لإعادة المحاولة بأمان، مع مفتاح منع تكرار لكل حدث ومصدر مستند واضح. تبقى إجراءات المخزون مسؤولة عن إثبات الكميات فقط؛ ولا تعدل أرصدة مالية. ويجب ألا يعتمد نجاح إنشاء أمر الشراء أو مرتجعه على partners_balance بعد القطع.

### 10.2 حدود الجولة
فُحصت مقاطع DAO والإجراءات المذكورة ساكنًا. لم يتم تشغيل الإجراء أو استدعاؤه من PHP، ولم يتم إثبات أي نسخة منشورة على قاعدة التشغيل. لا PHP lint ولا SQL import ولا runtime test.


## 11. ربط JavaScript بنقاط التحكم — 2026-11-05

- dist/js/DeliverySettlement.js يرسل PreviewDeliverySettlement وSettleDeliveryCollections إلى finance.control.php؛ نجاح الواجهة يعتمد على response.success. هذا يثبت اتصال واجهة التسوية بالمدير الجديد.
- dist/js/PaymentDisbursement.js يرسل GetRefundBalance إلى PaymentDisbursement.control.php، ويرسل PaymentOfCustomerDues وSaveSupplierPayment وPaymentOfMarketerDues وPaymentOfDeliveryDues إلى finance.control.php. بذلك تأكد أن شاشة الصرف ما زالت تستدعي أربع عمليات Backend قديمة مباشرة.
- dist/js/ReceivePayments.js يقرأ قائمة الطلبات من accounts.control.php?CONTROL=ReceivePayments، ويحتفظ أيضًا بعنوان finance.control.php لعمليات مالية أخرى؛ لم يتم تتبع كل event handler حتى النهاية.
- dist/js/AddPurchaseOrder.js يستخدم purchases.control.php لقائمة أوامر الشراء؛ يلزم فحص action حفظ أمر الشراء داخل الملف وربطه بـ purchases_BE وPurchaseOrderDAO.
- هذه الجولة تتبعت عناوين الطلبات وبعض أسماء actions فقط؛ لم تنفذ AJAX requests ولم تختبر استجابات الخادم.


## 12. حصر الخزائن والبنوك والسندات والتقارير المالية — 2026-11-05

### 12.1 فجوات تنفيذ نواة الأستاذ
تم فحص أحجام/محتوى ملفات Classes/Accounting التالية في المستودع:
- Bank.php وCashBox.php وReports.php وPartnerBalance.php: ملفات فارغة (0 بايت)، وليست خدمات تنفيذية.
- AccountManager.php وAccountSettings.php وJournalManager.php وAccountingEvents.php وPartnerLedger.php وDeliverySettlementManager.php تحتوي تنفيذًا فعليًا بدرجات مختلفة.
- لذلك لا يوجد حاليًا تنفيذ خدمة مستقل لإدارة الخزائن والبنوك أو تقارير الأستاذ، رغم وجود جداول وإعدادات مرتبطة بها.

### 12.2 الخزائن والبنوك ووسائل الدفع
- AccountSettings.php يقرأ حساب الصندوق من cash_boxes حسب الفرع، ويقرأ ربط طريقة الدفع من payment_method_accounts مع payment_methods، كما يقرأ حساب البنك من إعداد BANK.
- finance_BE::Add_transaction() ما زالت تسجل في financial_transactions، وتربط الحركة بفرع ومستخدم وفاتورة وطرف ومبلغ وطريقة دفع وفئة وحالة.
- AccountingEvents يعتمد في ترحيل التحصيل ورد المرتجع على financial_transactions وinvoices وtransaction_categories، ولا يختار حساب الأستاذ من payment_method_accounts في جميع المسارات؛ وثائق PHASE4 تسجل أن بعض التحصيلات تُرحّل إلى حساب صندوق الفرع بغض النظر عن وسيلة الدفع.
- لم يظهر تنفيذ فعلي في Bank.php أو CashBox.php لإدارة أرصدة/حركات الصندوق والبنك أو التحويل الداخلي أو المطابقة البنكية.

### 12.3 سندات القبض والصرف
- CashReceiptVouchers.php يعرض خيارات قبض من مندوب/مورد/عميل واستلام مفتوح، لكنه يضمّن ReceivePayments.php داخل قسم المندوب.
- dist/js/CashReceiptVouchers.js يطلب نقاطًا مستقلة مثل get_mandob_data.php وget_mandob_balance.php وget_masouq_data.php وget_masouq_balance.php وget_morad_data.php وget_morad_balance.php وget_client_data.php. لم تظهر هذه الملفات ضمن شجرة المستودع الحالية؛ لذلك هذه النداءات غير مثبتة الوجهة داخل المستودع وتحتاج تحديد هل هي ملفات خارجية/قديمة أو روابط مكسورة.
- لا يوجد في controllers التي فُحصت مسار عام واضح لسند قبض/صرف متعدد البنود مع حساب مقابل ومركز تكلفة ومرفقات واعتماد وترحيل. العمليات الحالية منفصلة حسب نوع الطرف.
- finance_BE يحتوي PaymentOfDeliveryDues وPaymentOfMarketerDues وPaymentOfSupplierDues وPaymentOfCustomerDues وregisterCollection؛ أغلبها ما زال يكتب في financial_transactions و/أو partners_balance. دفعة المورد تكتب أيضًا purchases_payments وsupplier_payment_allocations وتحدّث purchases_orders.TOTAL_PAYMENTS.

### 12.4 التقارير وكشوف الحساب
- reports.control.php يوجه AccountStatement إلى reports_BE::AccountStatement؛ الدالة تبني الرصيد السابق والحركة والرصيد الجاري مباشرة من partners_balance. يجب استبدال التقرير باستعلام PartnerLedger/دفتر الأستاذ، لا مجرد تغيير مصدر الرقم.
- reports_BE::SalesMovementReport يربط orders بجدول invoices، وmarketerDailyReport/marketerDailyDetails يقرآن partners_balance. بذلك تقارير البيع والمسوق ما زالت مرتبطة بالمصادر القديمة.
- accounts_BE::custodyTotal يجمع إجمالي CASH_IN وCASH_OUT وصافي النقدية من financial_transactions وtransaction_categories حسب الفرع، ويعرضها ضمن شاشة التحصيل/العهدة؛ لا يوجد ربط مثبت برصيد حسابات الأستاذ الفعلي أو فصل الصندوق عن البنك/طرق الدفع.
- PaymentDisbursement_BE::GetClientData وGetClientRetrnOrder وGetRefundBalance وGetPartnersBalance تقرأ partners_balance مباشرة. كما أن GetSupplierOrderList يعتمد على TOTAL_PAYMENTS ومرتجعات الشراء؛ يجب تحويل مصدر الرصيد والتخصيص إلى الأستاذ الجديد مع إبقاء تفاصيل أمر الشراء.

### 12.5 تصنيف الاعتماديات الجديدة
1. حرج — كتابة قديمة: finance_BE::Add_transaction وAdd_partners_balance وجميع عمليات الدفع والتحصيل التابعة لها.
2. حرج — قراءة قديمة: reports_BE::AccountStatement وتقارير المسوق وPaymentDisbursement_BE وأرصدة شاشة accounts_BE.
3. حرج — ربط مستند: invoices داخل تقارير البيع وAccountingEvents وقوائم الطلبات.
4. غير مكتمل — خزائن/بنوك: جداول ومفاتيح ربط موجودة، لكن ملفات Bank/CashBox فارغة ولا توجد خدمة تحويل/مطابقة مثبتة.
5. غير مثبت — سندات عامة: واجهة القبض الحالية تشير إلى endpoints غير موجودة في شجرة المستودع، ولا يظهر backend عام للسندات متعددة البنود.

### 12.6 الاختبارات والحدود
هذه الجولة فحص ساكن لشجرة GitHub ومحتوى الملفات وتعريفات الدوال. لم يتم استدعاء endpoints، ولم يتم تنفيذ AJAX، ولم يتم فحص قاعدة تشغيل أو أرصدة فعلية. لم ينفذ PHP lint أو SQL import في هذه الجولة. يجب عدم اعتبار غياب ملفات endpoints من GitHub دليلًا قاطعًا على عدم وجودها على الخادم قبل مقارنة نسخة النشر.
