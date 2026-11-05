-- Just-M ecommerce chart of accounts | MariaDB 10.5+ | Idempotent seed
-- Review backup first. This script inserts account definitions and system mappings only.
-- It does not post opening balances or historical transactions.
-- VAT accounts intentionally omitted per approved setting (VAT = none).
-- Inventory valuation method intentionally NOT configured pending legacy-system review.
START TRANSACTION;
CREATE TEMPORARY TABLE IF NOT EXISTS tmp_justm_coa (account_code VARCHAR(30) PRIMARY KEY, account_name VARCHAR(150), account_type VARCHAR(30), normal_balance VARCHAR(10), parent_code VARCHAR(30) NULL, allow_posting TINYINT(1), description VARCHAR(500));
TRUNCATE TABLE tmp_justm_coa;
INSERT INTO tmp_justm_coa VALUES ('1000','الأصول','ASSET','DEBIT',NULL,0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1100','الأصول المتداولة','ASSET','DEBIT','1000',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1110','النقدية بالصندوق','ASSET','DEBIT','1100',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1120','الحسابات البنكية','ASSET','DEBIT','1100',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1130','وسائل التحصيل الإلكتروني','ASSET','DEBIT','1100',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1140','أرصدة شركات الشحن والمناديب','ASSET','DEBIT','1100',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1150','العملاء','ASSET','DEBIT','1100',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1160','المخزون','ASSET','DEBIT','1100',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1170','السلف والمقدمات','ASSET','DEBIT','1100',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1111','الخزينة الرئيسية','ASSET','DEBIT','1110',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1112','خزينة الفرع','ASSET','DEBIT','1110',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1121','حسابات بنكية','ASSET','DEBIT','1120',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1122','حسابات بنكية إضافية','ASSET','DEBIT','1120',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1131','مستحقات بوابات الدفع','ASSET','DEBIT','1130',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1132','مستحقات المحافظ الإلكترونية','ASSET','DEBIT','1130',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1133','مستحقات الدفع بالبطاقات','ASSET','DEBIT','1130',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1141','عهدة مناديب التوصيل','ASSET','DEBIT','1140',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1142','مستحقات لدى شركات الشحن','ASSET','DEBIT','1140',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1143','مبالغ محصلة ولم تورد','ASSET','DEBIT','1140',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1151','حسابات العملاء','ASSET','DEBIT','1150',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1152','أرصدة العملاء الدائنة - حساب رقابي','ASSET','DEBIT','1150',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1161','مخزون البضاعة','ASSET','DEBIT','1160',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1162','مخزون بالطريق','ASSET','DEBIT','1160',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1163','مرتجعات تحت الفحص','ASSET','DEBIT','1160',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1171','دفعات مقدمة للموردين','ASSET','DEBIT','1170',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1172','مصروفات مدفوعة مقدماً','ASSET','DEBIT','1170',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1173','تأمينات لدى الغير','ASSET','DEBIT','1170',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1200','الأصول غير المتداولة','ASSET','DEBIT','1000',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1210','الأثاث والتجهيزات','ASSET','DEBIT','1200',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1220','أجهزة الكمبيوتر والمعدات','ASSET','DEBIT','1200',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1230','وسائل النقل','ASSET','DEBIT','1200',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('1240','مجمع الإهلاك','ASSET','CREDIT','1200',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('2000','الالتزامات','LIABILITY','CREDIT',NULL,0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('2100','الالتزامات المتداولة','LIABILITY','CREDIT','2000',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('2110','الموردون','LIABILITY','CREDIT','2100',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('2120','مستحقات شركات الشحن','LIABILITY','CREDIT','2100',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('2130','مستحقات العملاء','LIABILITY','CREDIT','2100',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('2140','مصروفات مستحقة','LIABILITY','CREDIT','2100',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('2150','التزامات أخرى','LIABILITY','CREDIT','2100',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('2111','حسابات الموردين','LIABILITY','CREDIT','2110',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('2121','مستحقات شركات التوصيل','LIABILITY','CREDIT','2120',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('2122','عمولات المناديب المستحقة','LIABILITY','CREDIT','2120',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('2131','دفعات مقدمة من العملاء','LIABILITY','CREDIT','2130',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('2132','مبالغ مستحقة ردها للعملاء','LIABILITY','CREDIT','2130',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('2133','أرصدة دائنة للعملاء','LIABILITY','CREDIT','2130',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('2141','مصروفات شحن مستحقة','LIABILITY','CREDIT','2140',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('2142','عمولات بوابات دفع مستحقة','LIABILITY','CREDIT','2140',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('2143','مصروفات تشغيل مستحقة','LIABILITY','CREDIT','2140',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('2151','التزامات متنوعة','LIABILITY','CREDIT','2150',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('2200','الالتزامات طويلة الأجل','LIABILITY','CREDIT','2000',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('2210','قروض طويلة الأجل','LIABILITY','CREDIT','2200',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('3000','حقوق الملكية','EQUITY','CREDIT',NULL,0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('3100','رأس المال','EQUITY','CREDIT','3000',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('3200','جاري الشركاء','EQUITY','CREDIT','3000',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('3300','الأرباح المحتجزة','EQUITY','CREDIT','3000',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('3400','أرباح أو خسائر العام','EQUITY','CREDIT','3000',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('3500','المسحوبات الشخصية','EQUITY','CREDIT','3000',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('3210','جاري الشريك الأول','EQUITY','CREDIT','3200',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('3220','جاري الشريك الثاني','EQUITY','CREDIT','3200',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('4000','الإيرادات','REVENUE','CREDIT',NULL,0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('4100','إيرادات النشاط','REVENUE','CREDIT','4000',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('4110','مبيعات المنتجات','REVENUE','CREDIT','4100',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('4120','إيرادات الشحن المحصلة من العملاء','REVENUE','CREDIT','4100',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('4130','إيرادات خدمات أخرى','REVENUE','CREDIT','4100',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('4200','مردودات وخصومات المبيعات','REVENUE','DEBIT','4000',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('4210','مردودات المبيعات','REVENUE','DEBIT','4200',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('4220','خصومات المبيعات','REVENUE','DEBIT','4200',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('4300','إيرادات أخرى','REVENUE','CREDIT','4000',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('4310','إيرادات متنوعة','REVENUE','CREDIT','4300',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('4320','أرباح فروق العملة','REVENUE','CREDIT','4300',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5000','المصروفات والتكاليف','EXPENSE','DEBIT',NULL,0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5100','تكلفة المبيعات','EXPENSE','DEBIT','5000',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5110','تكلفة البضاعة المباعة','EXPENSE','DEBIT','5100',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5120','تكلفة معالجة البضاعة المرتجعة','EXPENSE','DEBIT','5100',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5200','مصروفات الشحن والتوصيل','EXPENSE','DEBIT','5000',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5300','مصروفات بوابات الدفع والتحصيل','EXPENSE','DEBIT','5000',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5400','مصروفات التسويق والمبيعات','EXPENSE','DEBIT','5000',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5500','المصروفات الإدارية والعمومية','EXPENSE','DEBIT','5000',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5600','مصروفات مالية','EXPENSE','DEBIT','5000',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5800','خسائر ومصروفات أخرى','EXPENSE','DEBIT','5000',0,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5210','تكلفة شحن الطلبات','EXPENSE','DEBIT','5200',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5220','عمولات المناديب','EXPENSE','DEBIT','5200',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5230','رسوم إعادة الشحن والمرتجعات','EXPENSE','DEBIT','5200',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5310','عمولات بوابات الدفع','EXPENSE','DEBIT','5300',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5320','رسوم التحصيل الإلكتروني','EXPENSE','DEBIT','5300',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5330','رسوم التحويلات البنكية','EXPENSE','DEBIT','5300',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5410','الإعلانات الممولة','EXPENSE','DEBIT','5400',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5420','التسويق بالعمولة','EXPENSE','DEBIT','5400',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5430','العروض الترويجية','EXPENSE','DEBIT','5400',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5510','الرواتب والأجور','EXPENSE','DEBIT','5500',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5520','الإيجارات','EXPENSE','DEBIT','5500',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5530','الكهرباء والمياه والاتصالات','EXPENSE','DEBIT','5500',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5540','مستلزمات مكتبية','EXPENSE','DEBIT','5500',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5550','صيانة وتشغيل','EXPENSE','DEBIT','5500',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5610','مصروفات بنكية','EXPENSE','DEBIT','5600',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5620','فوائد مدينة إن وجدت','EXPENSE','DEBIT','5600',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5810','ديون معدومة','EXPENSE','DEBIT','5800',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5820','فروق جرد وعجز مخزون','EXPENSE','DEBIT','5800',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5830','خسائر فروق العملة','EXPENSE','DEBIT','5800',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');
INSERT INTO tmp_justm_coa VALUES ('5700','الإهلاكات','EXPENSE','DEBIT','5000',1,'حساب ضمن شجرة التجارة الإلكترونية المعتمدة');

-- Root accounts first; parent references are resolved by account_code.
INSERT INTO accounts (parent_id, account_code, account_name, account_type, normal_balance, account_level, is_active, allow_posting, system_account, is_system_created, description)
SELECT CASE WHEN t.parent_code IS NULL THEN NULL ELSE (SELECT p.id FROM accounts p WHERE p.account_code=t.parent_code LIMIT 1) END, t.account_code, t.account_name, t.account_type, t.normal_balance,
       CASE WHEN t.parent_code IS NULL THEN 1 ELSE 2 END, 1, t.allow_posting, 1, 1, t.description
FROM tmp_justm_coa t ORDER BY (t.parent_code IS NOT NULL), t.account_code
ON DUPLICATE KEY UPDATE account_name=VALUES(account_name), account_type=VALUES(account_type), normal_balance=VALUES(normal_balance), allow_posting=VALUES(allow_posting), description=VALUES(description), is_active=1;

-- Repair parent links and levels after all account codes exist.
UPDATE accounts a JOIN tmp_justm_coa t ON t.account_code=a.account_code
LEFT JOIN accounts p ON p.account_code=t.parent_code
SET a.parent_id=p.id, a.account_level=CASE WHEN t.parent_code IS NULL THEN 1 ELSE 2 END;

-- Recalculate actual hierarchy depth (some accounts are grandchildren/great-grandchildren).
UPDATE accounts a JOIN accounts p ON p.id=a.parent_id
SET a.account_level=p.account_level+1
WHERE a.account_code IN (SELECT account_code FROM tmp_justm_coa);
UPDATE accounts a JOIN accounts p ON p.id=a.parent_id
SET a.account_level=p.account_level+1
WHERE a.account_code IN (SELECT account_code FROM tmp_justm_coa);
UPDATE accounts a JOIN accounts p ON p.id=a.parent_id
SET a.account_level=p.account_level+1
WHERE a.account_code IN (SELECT account_code FROM tmp_justm_coa);
UPDATE accounts a JOIN accounts p ON p.id=a.parent_id
SET a.account_level=p.account_level+1
WHERE a.account_code IN (SELECT account_code FROM tmp_justm_coa);

-- Parent accounts must never receive direct journal postings.
UPDATE accounts a JOIN tmp_justm_coa t ON t.account_code=a.account_code SET a.allow_posting=t.allow_posting;

-- System account mappings consumed by AccountSettings.php.
INSERT INTO account_settings (setting_key, account_id, description)
SELECT m.setting_key, a.id, m.description
FROM (SELECT 'CUSTOMERS' setting_key,'1151' account_code,'حساب رقابي للعملاء' description
      UNION ALL SELECT 'SALES','4110','إيراد مبيعات المنتجات'
      UNION ALL SELECT 'COGS','5110','تكلفة البضاعة المباعة'
      UNION ALL SELECT 'INVENTORY','1161','مخزون البضاعة'
      UNION ALL SELECT 'SALES_RETURNS','4210','مردودات المبيعات'
      UNION ALL SELECT 'SHIPPING_REVENUE','4120','إيراد الشحن على مستوى الطلب') m
JOIN accounts a ON a.account_code=m.account_code
ON DUPLICATE KEY UPDATE account_id=VALUES(account_id), description=VALUES(description);

-- Approved delivery commission policy: deduct commission from courier custody.
INSERT INTO delivery_accounting_settings (id, commission_settlement_mode, courier_custody_account_id, courier_commission_expense_account_id, courier_commission_payable_account_id)
SELECT 1,'DEDUCT_FROM_CUSTODY', custody.id, expense.id, payable.id
FROM accounts custody, accounts expense, accounts payable
WHERE custody.account_code='1141' AND expense.account_code='5220' AND payable.account_code='2122'
ON DUPLICATE KEY UPDATE commission_settlement_mode=VALUES(commission_settlement_mode), courier_custody_account_id=VALUES(courier_custody_account_id), courier_commission_expense_account_id=VALUES(courier_commission_expense_account_id), courier_commission_payable_account_id=VALUES(courier_commission_payable_account_id);

-- Deliberately not populated: cash_boxes and payment_method_accounts require real branch/payment-method IDs.
-- No VAT accounts, inventory costing policy, opening balances, or historical journal entries are created.
DROP TEMPORARY TABLE tmp_justm_coa;
COMMIT;

-- Post-install verification
SELECT account_code, account_name, parent_id, account_type, normal_balance, allow_posting FROM accounts WHERE account_code BETWEEN '1000' AND '5999' ORDER BY account_code;
SELECT setting_key, account_code, account_name FROM account_settings s JOIN accounts a ON a.id=s.account_id ORDER BY setting_key;
SELECT commission_settlement_mode, courier_custody_account_id, courier_commission_expense_account_id, courier_commission_payable_account_id FROM delivery_accounting_settings WHERE id=1;