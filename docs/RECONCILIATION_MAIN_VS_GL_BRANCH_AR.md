# مطابقة `main` (النسخة المرفوعة 12-11-2026) مع فرع المحاسبة `main-integrated`

هذه الوثائق مستعادة من `main-integrated`. **الكود في `main` هو الأساس** (أحدث نسخة من المالك)، لكنه أقدم في طبقة المحاسبة.

## ناقص في `main` ومتوفر في `main-integrated`
- `FinancialEventManager` و`FinancialVoucherManager` و`DeliverySettlementManager` و`DeliveryAttemptCommissionManager` و`DeliveryRejectedShipping*Manager` و`CustomerRefundManager` و`SupplierAccountingManager` و`PartnerLedger` و`ShippingConfigurationManager`.
- اختبارات `tests/accounting` و`tests/run_static_acceptance.py` وworkflow الـ CI، وملفات `JUSTM_DATABASE_INSTALL.sql` وmigrations (PHASE15/PHASE22).
- ملفات فارغة في `main` (`Bank.php`, `CashBox.php`, `Reports.php`, `PartnerBalance.php`, ...).

## تباعد كبير (لا يُدمج آلياً)
`shipment_BE.php` (+1777/-501)، `finance_BE.php` (+1095/-214)، `Order_tbl.php`، `reports_BE.php`، `AccountingEvents.php`، `JournalManager.php`، `AccountSettings.php`، `SidebarMenu.php`، `dbconn.php`.

## الخطوة التالية المقترحة
1. تشغيل `main` على قاعدة اختبار وتحديد ما يعمل فعلاً.
2. نقل مديري المحاسبة واحداً واحداً مع اختباره، ثم إعادة الاختبارات والـ CI.
3. لا يُغيَّر أي قرار محاسبي أو مخزني معتمد (راجع `AI_WORK_RULES_AR.md`).
