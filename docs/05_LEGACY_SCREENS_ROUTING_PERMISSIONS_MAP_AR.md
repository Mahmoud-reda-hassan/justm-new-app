# 05 — خريطة الشاشات والتوجيه والصلاحيات في النظام القديم

**الإصدار:** 0.1  
**تاريخ الفحص:** 2026-11-04  
**المصادر الأساسية:** `inc/template/SidebarMenu.php`، `inc/Classes/user_permission.php`، `inc/routes/*.php`، `Admin.php`، `Affiliate.php`، `inc/template/body.php`، ومجلد `inc/Views`.

> هذا المستند يصف ما ظهر في نسخة المصدر المفحوصة. أسماء الروابط والعناوين قد تختلف في بعض المواضع، وبعض الصفحات لا يظهر لها تعريف متطابق في كل ملفات التوجيه. لا تعتبر هذه الخريطة بديلًا عن تتبع الـ API والـ Controller لكل عملية داخل الشاشة.

## 1. معمارية فتح الشاشة

المسار الفعلي الظاهر في ملفات الدخول:

1. `Admin.php` يجهز الاتصال بقاعدة البيانات والمصادقة، ثم ينشئ `user_permission`.
2. يحدد `pageType = 1` و`loginType = 1` ثم يستدعي `app_request()`.
3. `user_permission::app_request()` يقرأ `Go` ومعاملات مثل `ex` و`ty` و`ptr`، ويحدد `Pagelink` واسم الصفحة والـ JavaScript/CSS.
4. `inc/template/header.php` يحمّل القالب، ثم `inc/template/body.php` يضمّن `inc/Views/{Pagelink}.php`.
5. `SidebarMenu.php` يعرض الروابط بحسب `USER_SCRIPT` ونوع المستخدم.

ملفات `inc/routes/admin_router.php` و`affiliate_router.php` و`delivery_router.php` و`inc/router.php` تحتوي على بنية Router بديلة تعتمد على مصفوفات تعريف المسارات. لكن في نقاط الدخول التي تمت مراجعتها (`Admin.php` و`Affiliate.php`) الاستدعاء الفعلي هو `app_request()` من `user_permission.php`، كما أن `inc/router.php` مستدعى بالتعليق وليس بالتنفيذ في هذه الملفات. لذلك يجب عدم اعتبار مصفوفات routes هي وحدها مصدر التوجيه الفعلي.

## 2. أنواع المستخدمين ولوحاتهم

| النوع | `pageType` | متغير الجلسة | ملف الدخول الذي تمت مراجعته |
|---|---:|---|---|
| الإدارة | 1 | `ADMIN_TYPE` | `Admin.php` |
| المسوق بالعمولة | 2 | `AFFILIATE_TYPE` | `Affiliate.php` |
| مندوب الشحن | 3 | `DELIVERY_TYPE` | منطق `user_permission.php`؛ ملف الدخول يحتاج تتبعًا مستقلًا |
| وسيط البيع | 4 | `INTERMEDIARY_TYPE` | منطق `user_permission.php` |
| التاجر | 5 | `MERCHANT_TYPE` | منطق `user_permission.php` |

`home.php` يوجه المستخدم تلقائيًا إلى مسار الدور إذا كان لديه دور واحد، أو يعرض `AdminZone.php` إذا كانت لديه عدة أدوار. أسماء المسارات الظاهرة: `/Admin` و`/Merchant` و`/Affiliate` و`/Intermediary` و`/Shipping`.

## 3. خريطة شاشات الإدارة

### أ. المنتجات

| الوظيفة/الرابط | الواجهة الفعلية | ملفات JS الظاهرة في التوجيه |
|---|---|---|
| أقسام المنتجات `Product-Categories` | `Categoris.php` | `Categoris.js` |
| صفات المنتجات `ProductOption` | `ProductOption.php` | `productOption.js` |
| المنتجات `Products` | `AddProduct.php` | `products.js`، `summernote-bs4.min.js` |

### ب. المبيعات والطلبات

| الوظيفة/الرابط | الواجهة الفعلية | ملاحظات |
|---|---|---|
| أمر جديد `NewOrder` | `Neworder.php` | `selectProduct.js` و`sreach_client.js` |
| تحرير أمر `EditOrder` | `Neworder.php` | نفس واجهة إنشاء الأمر |
| قوائم الطلبات `Orderlist` مع `ex` | `viewOrders.php` | تستخدم ملفات `filterOrderList.js` و`viewOrders.js` و`viewOrderData.js` و`DatatablesFromObject.js` |
| خدمة ما بعد البيع `SupportAfterSales` | `viewOrders.php` | واجهة مشتركة مع قوائم الطلبات |
| طلبات المنسقين والعملاء `SupportOrders` | `viewOrders.php` | واجهة مشتركة مع قوائم الطلبات |
| عهدة المندوبين `OrdersCustody` | `viewOrders.php` | واجهة مشتركة مع قوائم الطلبات |
| تقرير حركة/مبيعات `SalesMovementReport` | `WarehouseShortageReport.php` حسب `user_permission.php` | اسم الرابط لا يطابق اسم الواجهة؛ يحتاج تحققًا من المقصود الوظيفي |

قيم `ex` التي تظهر في جدول العناوين داخل `user_permission.php`:

| `ex` | العنوان الظاهر |
|---:|---|
| 0 | طلبات العملاء |
| 1 | تأكيد طلبات العملاء |
| 2 | طلبات مؤجلة |
| 3 | طلبات غير متوفرة |
| 4 | طلبات لم يتم التأكيد مع العميل |
| 5 | أوامر البيع الملغاة |
| 6 | تأكيد الشحن والتوصيل |
| 7 | قيد التجهيز |
| 8 | جاهز للشحن |
| 9 | طلبات جاري الشحن |
| 10 | طلبات تم شحنها |
| 11 | طلبات بيع مباشر |
| 12 | تتبع تأكيد الشحن |

هذه عناوين قوائم وليست تعريفًا كاملًا لقواعد الحالات أو انتقالاتها. يجب ربط كل قيمة باستعلام API وحقول الحالة الفعلية قبل كتابة دورة العمل النهائية.

### ج. المخازن والتوريد

| الوظيفة/الرابط | الواجهة الفعلية | JS |
|---|---|---|
| إذن استلام مخزني `AddStock` | `AddStockPermission.php` | `AddStockPermission.js`، ويضاف `viewOrderData.js` في المسار القديم |
| إذن صرف مخزني `StockIssue` | `inventoryIssue.php` | `inventoryIssue.js` |
| إذن تحويل مخزني `StockTransfer` | `StockTransfer.php` | `StockTransfer.js` |
| جرد ومطابقة `StockAudit` | `InventoryReconciliation.php` | `InventoryReconciliation.js` |
| طلب توريد `AddPurchaseOrder` | `AddPurchaseOrder.php` | `AddPurchaseOrder.js` |
| الأصناف الحرجة `stockShortageReport` | `WarehouseShortageReport.php` | `reports.js` |
| حركة عهدة المندوبين `DelegatesShipmentReport` | `DelegatesReport.php` | لا يوجد JS إضافي في تعريف المسار |
| تقرير استلامات/حركة المرتجعات `DelegatesInventoryReport` | `InventoryMovement-report.php` في `user_permission.php` | لا يوجد JS إضافي ظاهر |
| تقرير استلامات الموردين `SuppliersQtyReport` | `PurchasesReports.php` | لا يوجد JS إضافي ظاهر |

### د. المالية القديمة

| الوظيفة/الرابط | الواجهة الفعلية | ملاحظة |
|---|---|---|
| استلام المدفوعات `ReceivePayments` | `ReceivePayments.php` | يحمل `ReceivePayments.js`، وتضيف مصفوفة routes ملف `DeliverySettlement.js` |
| إذن صرف نقدية `PaymentDisbursement` | `PaymentDisbursement.php` | `PaymentDisbursement.js` |
| إذن استلام نقدية `CashReceiptVouchers` | `CashReceiptVouchers.php` | `ReceivePayments.js` و`CashReceiptVouchers.js` |
| كشف حساب مورد | `AccountStatementReport.php` | `ptr=SUPPLIER` |
| كشف حساب عميل | `AccountStatementReport.php` | `ptr=CLIENT` |
| كشف حساب مسوق | `AccountStatementReport.php` | `ptr=MARKETER` |
| كشف حساب مندوب | `AccountStatementReport.php` | `ptr=DELIVERY` |

هذه أسماء ووظائف الواجهات القديمة فقط، وليست اعتمادًا لاستمرار تصميمها المالي في النسخة الجديدة؛ توجه المشروع المعتمد هو استبدال النظام المالي القديم بدفتر الأستاذ العام كمصدر وحيد للحقيقة.

### هـ. الإعدادات والبيانات الأساسية

| الوظيفة/الرابط | الواجهة الفعلية |
|---|---|
| الدول `Countries` | `Countries.php` |
| المحافظات `Gov` | `Countries.php` |
| المدن `City` | `Countries.php` |
| الشحن المحلي `LocalShipping` | `Countries.php` |
| الشحن الدولي `International-Shipping` | `International-Shipping.php` |
| بيانات المؤسسة `Enterprise` | `Enterprise.php` |
| الهيكل الإداري `Administrative-Structure` | `Administrative-Structure.php` |
| الفروع `Branches` | `Branches.php` |
| المخازن `Warehouse` | `Warehouse.php` |
| المستخدمون `Users` | `Users.php` |
| العملاء `Clients` | `Clients.php` |
| الموردون `Suppliers` | `Suppliers.php` |
| التقرير العام `PublicReports` | `Reports.php` وفق التوجيه القديم |

### و. صفحات أخرى

- `Profile` ← `Profile.php`
- `AdminZone` ← `AdminZone.php`
- لوحة الإدارة ← `cp_admin.php`
- لوحة المسوق ← `cp_affiliate.php`
- لوحة الشحن ← `cp_shipping.php`
- صفحات تقارير/مساعدة إضافية: `Reports.php`، `InventoryMovement-report.php`، `SalesMovementReport.php`، `shipping_report.php`، `marketerDailyReport.php`، `MarketerBalance.php`.

وجود ملف View لا يثبت أنه متاح في قائمة كل الأدوار أو أن مساره الحالي فعال.

## 4. صفحات المسوق والمندوب

### المسوق

المسارات التي ظهرت في القائمة ومنطق الدور:

| الرابط | الواجهة |
|---|---|
| `NewOrder` | `Neworder.php` |
| `EditOrder` | `Neworder.php` |
| `ListOrdersMarketer` | `viewOrders.php` |
| `MyEarnings` | `MarketerBalance.php` في `user_permission.php` |
| `mDailyReport` | `marketerDailyReport.php` |
| `Profile` | `Profile.php` |

### مندوب الشحن

| الرابط | الواجهة |
|---|---|
| `ShippingConfirmation` | `viewOrders.php` |
| `ListOrdersDelivery` | `viewOrders.php` |
| `ListOrdersDelivered` | `viewOrders.php` |
| `EditOrder` | `Neworder.php` |
| `Profile` | `Profile.php` |

مصفوفة `delivery_router.php` تضيف `DelegatesShipmentReport` إلى `DelegatesReport.php`، لكن منطق `app_request()` الخاص بالدور 3 الذي تمت مراجعته لا يظهر نفس الحالة ضمن switch؛ يجب التأكد من مسار الدخول المستخدم فعليًا قبل الحكم على إتاحتها.

## 5. اختلافات وتعارضات تستحق التتبع

هذه نقاط مكتشفة من مقارنة الملفات، وليست اقتراحات تعديل فوري:

1. **وجود Router بديل:** `inc/router.php` ومصفوفات `inc/routes/*.php` موجودة، لكن ملفات الدخول التي تمت مراجعتها تستدعي `user_permission::app_request()`، ولا يظهر أنها تستخدم Router البديل.
2. **اختلاف تغطية قوائم الطلبات:** `admin_router.php` يعرّف `Orderlist0` و`Orderlist1` و`Orderlist2` فقط، بينما `user_permission.php` يتعامل مع `ex` من 0 إلى 12.
3. **تحرير الطلب:** فرع `EditOrder` في `user_permission.php` يعتمد على `Go=EditOrder` و`ty=1`، ولا يظهر في نفس الشرط تحقق صريح من صلاحية `Create_Order` مثل شرط `NewOrder`. يلزم اختبار صلاحيات مباشر قبل أي تغيير.
4. **طلب التوريد:** `AddPurchaseOrder` في `admin_router.php` معرف بصلاحية `true` تعليقًا على صلاحية قديمة، وشرط `user_permission.php` الظاهر لا يتحقق من مفتاح صلاحية قبل فتح الصفحة.
5. **التقرير العام:** شرط `PublicReports` داخل `user_permission.php` يتحقق أيضًا من صلاحية الموردين `Suppliers.View`، وهو ربط غير معتاد يحتاج تأكيد المقصود.
6. **تقرير حركة المبيعات:** `SalesMovementReport` يوجه إلى `WarehouseShortageReport.php`، رغم وجود ملف مستقل باسم `SalesMovementReport.php`.
7. **تقرير المرتجعات/المندوبين:** `DelegatesInventoryReport` يوجه في `user_permission.php` إلى `InventoryMovement-report.php`، بينما تعريف `admin_router.php` يربطه بـ`PurchasesReports.php`.
8. **ملفات JavaScript:** بعض قوائم routes لا تطابق تمامًا ملفات JS التي يضيفها `user_permission.php`؛ يجب توثيق الاعتماد الفعلي من Network/DOM أو مراجعة `array_body_JS()` قبل النقل.
9. **فحص الصلاحية ليس موحدًا:** بعض الشروط تفحص القيمة `"true"`، وبعضها يكتفي بوجود المفتاح، وبعضها يمرر قيمة صلاحية إلى مصفوفة routes. يجب مراجعة مولد `USER_SCRIPT` ومصدر الصلاحيات قبل إعادة بناء RBAC.

## 6. ما نحتاجه في المرحلة التالية

لكل شاشة من هذه الخريطة، يجب استكمال الأعمدة التالية من المصدر:

- الهدف التشغيلي للشاشة.
- عناصر الإدخال والبحث والفلاتر والأزرار.
- API أو طلب AJAX الذي ينفذ كل زر.
- Controller/Backend class المستدعى.
- الجداول التي تتم قراءتها أو تعديلها.
- حالات النجاح والفشل والتحقق.
- الآثار الجانبية على المخزون والشحن والحسابات.
- الصلاحيات المطلوبة لكل عملية، لا مجرد صلاحية فتح الصفحة.

**الأولوية التالية المقترحة:** تفكيك دورة الطلب من `Neworder.php` وملفات `selectProduct.js` و`sreach_client.js` وواجهات الـ API إلى `Order_tbl.php`، ثم متابعة التأكيد والتجهيز والتسليم والمرتجعات. لا نبدأ تعديلًا برمجيًا في هذه المرحلة؛ هذه مرحلة توثيق وفهم.
