-- ============================================================
-- ACCOUNTING COMPLETE MIGRATION (single file)
-- Brings an existing Just-M database up to the new GL-based accounting.
-- Idempotent: safe to run more than once.
-- *** RUN ON A TEST COPY OF THE DATABASE FIRST, NEVER DIRECTLY ON PRODUCTION. ***
-- Take a full backup before running. Nothing here deletes or rewrites existing data.
-- Contents: (1) courier custody + commission payable accounts, (2) reopen inventory movement type,
--           (3) marketer commission accounts + opening balance account 3300, (4) read-only GL reporting views,
--           (5) accounting_periods + system_flags tables (empty/default = no behaviour change).
-- Part 2 changes an ENUM: it assumes the schema in inc/Databases/createDatabase.sql;
-- if your IM_MOVE_TYPE has extra custom values, add them to the list first.
-- ============================================================

-- ---------- Part 1 ----------
INSERT INTO `accounts`
  (`parent_id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `account_level`, `is_active`, `allow_posting`, `system_account`, `is_system_created`)
SELECT p.`id`, '1510', 'عهدة المندوبين', 'ASSET', 'DEBIT', 3, 1, 1, 0, 0
FROM `accounts` p
WHERE p.`account_code` = '1500'
  AND NOT EXISTS (SELECT 1 FROM `accounts` x WHERE x.`account_code` = '1510');

INSERT INTO `accounts`
  (`parent_id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `account_level`, `is_active`, `allow_posting`, `system_account`, `is_system_created`)
SELECT p.`id`, '2210', 'عمولات مندوبين مستحقة', 'LIABILITY', 'CREDIT', 3, 1, 1, 0, 0
FROM `accounts` p
WHERE p.`account_code` = '2200'
  AND NOT EXISTS (SELECT 1 FROM `accounts` x WHERE x.`account_code` = '2210');

INSERT INTO `account_settings` (`setting_key`, `account_id`, `description`)
SELECT 'DELIVERY_CUSTODY', a.`id`, 'عهدة المندوبين (محصل لدى المندوب قبل التوريد)'
FROM `accounts` a
WHERE a.`account_code` = '1510'
  AND NOT EXISTS (SELECT 1 FROM `account_settings` s WHERE s.`setting_key` = 'DELIVERY_CUSTODY');

INSERT INTO `account_settings` (`setting_key`, `account_id`, `description`)
SELECT 'DELIVERY_COMMISSION_PAYABLE', a.`id`, 'عمولات المندوبين المستحقة (التزام)'
FROM `accounts` a
WHERE a.`account_code` = '2210'
  AND NOT EXISTS (SELECT 1 FROM `account_settings` s WHERE s.`setting_key` = 'DELIVERY_COMMISSION_PAYABLE');

-- ---------- Part 2 ----------
ALTER TABLE `inventory_movement`
  MODIFY `IM_MOVE_TYPE` enum('RECEIVING','SHIPPING','SALES','TRANSFER','SETTLEMENT','REFUND','RETURN_SHIPP','RETURN_SALES','RETURN_SHIPP_SALSE','REFUNDPURCHISES','REOPEN_RETURN_SHIPP') NOT NULL;

-- ---------- Part 3 ----------
INSERT INTO `accounts`
  (`parent_id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `account_level`, `is_active`, `allow_posting`, `system_account`, `is_system_created`)
SELECT p.`id`, '5710', 'عمولات المسوقين', 'EXPENSE', 'DEBIT', 2, 1, 1, 0, 0
FROM `accounts` p
WHERE p.`account_code` = '5000'
  AND NOT EXISTS (SELECT 1 FROM `accounts` x WHERE x.`account_code` = '5710');

INSERT INTO `accounts`
  (`parent_id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `account_level`, `is_active`, `allow_posting`, `system_account`, `is_system_created`)
SELECT p.`id`, '2220', 'عمولات مسوقين مستحقة', 'LIABILITY', 'CREDIT', 3, 1, 1, 0, 0
FROM `accounts` p
WHERE p.`account_code` = '2200'
  AND NOT EXISTS (SELECT 1 FROM `accounts` x WHERE x.`account_code` = '2220');

INSERT INTO `account_settings` (`setting_key`, `account_id`, `description`)
SELECT 'MARKETER_COMMISSION', a.`id`, 'عمولات المسوقين (مصروف)'
FROM `accounts` a
WHERE a.`account_code` = '5710'
  AND NOT EXISTS (SELECT 1 FROM `account_settings` s WHERE s.`setting_key` = 'MARKETER_COMMISSION');

INSERT INTO `account_settings` (`setting_key`, `account_id`, `description`)
SELECT 'MARKETER_COMMISSION_PAYABLE', a.`id`, 'عمولات المسوقين المستحقة (التزام)'
FROM `accounts` a
WHERE a.`account_code` = '2220'
  AND NOT EXISTS (SELECT 1 FROM `account_settings` s WHERE s.`setting_key` = 'MARKETER_COMMISSION_PAYABLE');

-- ---------- Part 3b: opening balance account ----------
-- Opening balance equity account (counterpart of legacy-balance alignment entries).
INSERT INTO `accounts`
  (`parent_id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `account_level`, `is_active`, `allow_posting`, `system_account`, `is_system_created`)
SELECT (SELECT p.`id` FROM `accounts` p WHERE p.`account_code` = '3000' LIMIT 1), '3300', 'أرصدة افتتاحية', 'EQUITY', 'CREDIT', 2, 1, 1, 0, 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `accounts` x WHERE x.`account_code` = '3300');

INSERT INTO `account_settings` (`setting_key`, `account_id`, `description`)
SELECT 'OPENING_BALANCE_EQUITY', a.`id`, 'أرصدة افتتاحية (مقابل تسويات النظام القديم)'
FROM `accounts` a
WHERE a.`account_code` = '3300'
  AND NOT EXISTS (SELECT 1 FROM `account_settings` s WHERE s.`setting_key` = 'OPENING_BALANCE_EQUITY');

-- ============================================================
-- Part 4: read-only GL reporting views (no screens; used to reconcile the GL with the legacy numbers)
-- ============================================================
CREATE OR REPLACE VIEW `gl_trial_balance` AS
SELECT a.`id` AS account_id, a.`account_code`, a.`account_name`, a.`account_type`,
       COALESCE(SUM(d.`debit`),0)  AS total_debit,
       COALESCE(SUM(d.`credit`),0) AS total_credit,
       COALESCE(SUM(d.`debit` - d.`credit`),0) AS net_debit_minus_credit
FROM `accounts` a
LEFT JOIN `journal_entry_details` d ON d.`account_id` = a.`id`
LEFT JOIN `journal_entries` e ON e.`id` = d.`journal_entry_id` AND e.`status` = 'POSTED'
WHERE d.`id` IS NULL OR e.`id` IS NOT NULL
GROUP BY a.`id`, a.`account_code`, a.`account_name`, a.`account_type`;

CREATE OR REPLACE VIEW `gl_partner_balances` AS
SELECT d.`partner_type`, d.`partner_id`, a.`account_code`,
       SUM(d.`debit`) AS total_debit, SUM(d.`credit`) AS total_credit,
       SUM(d.`debit` - d.`credit`) AS net_debit_minus_credit
FROM `journal_entry_details` d
JOIN `journal_entries` e ON e.`id` = d.`journal_entry_id` AND e.`status` = 'POSTED'
JOIN `accounts` a ON a.`id` = d.`account_id`
WHERE d.`partner_type` IS NOT NULL
GROUP BY d.`partner_type`, d.`partner_id`, a.`account_code`;

-- ------------------------------------------------------------
-- Part 5: GL ledger tools (periods lock + system flags). No data is changed.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `accounting_periods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT 1,
  `closed_by` int(11) DEFAULT NULL,
  `closed_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_period_dates` (`date_from`, `date_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `system_flags` (
  `flag_key` varchar(60) NOT NULL,
  `flag_value` varchar(255) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`flag_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `system_flags` (`flag_key`, `flag_value`) VALUES ('BALANCE_SOURCE', 'legacy');

-- ------------------------------------------------------------
-- Part 6: Broker (وسيط) accounts. Chart-of-accounts only; no posting logic yet.
-- Rule: revenue is recognised at the product minimum price; (selling price - minimum)
-- is owed to the broker as commission -> credited to 2230 until paid.
-- ------------------------------------------------------------
ALTER TABLE `journal_entry_details`
  MODIFY `partner_type` enum('CLIENT','SUPPLIER','DELIVERY','MARKETER','EMPLOYEE','BROKER') DEFAULT NULL;

INSERT INTO `accounts`
  (`parent_id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `account_level`, `is_active`, `allow_posting`, `system_account`, `is_system_created`)
SELECT p.`id`, '2230', 'عمولات وسطاء مستحقة', 'LIABILITY', 'CREDIT', 3, 1, 1, 0, 0
FROM `accounts` p
WHERE p.`account_code` = '2200'
  AND NOT EXISTS (SELECT 1 FROM `accounts` x WHERE x.`account_code` = '2230');

INSERT INTO `account_settings` (`setting_key`, `account_id`, `description`)
SELECT 'BROKER_COMMISSION_PAYABLE', a.`id`, 'عمولات الوسطاء المستحقة (التزام): الفرق بين سعر البيع والحد الأدنى'
FROM `accounts` a
WHERE a.`account_code` = '2230'
  AND NOT EXISTS (SELECT 1 FROM `account_settings` s WHERE s.`setting_key` = 'BROKER_COMMISSION_PAYABLE');

-- ------------------------------------------------------------
-- Part 7: Broker = marketer with a different commission formula.
-- PER_PIECE : fixed amount per received piece (COMMISSION_SALES)  [marketer]
-- PRICE_DIFF: (selling price - product minimum price) x received qty [broker]
-- Both flow through the same marketer pipeline (2220 / MARKETER partner).
-- ------------------------------------------------------------
ALTER TABLE `users_affiliate`
  ADD COLUMN IF NOT EXISTS `COMMISSION_TYPE` enum('PER_PIECE','PRICE_DIFF') NOT NULL DEFAULT 'PER_PIECE' AFTER `COMMISSION_SALES`;

-- ------------------------------------------------------------
-- Part 8: Shipping companies (external). A company is NOT a system user who logs in.
-- Each company is backed by an internal suspended DELIVERY user so the existing courier
-- custody / delivery-confirmation / GL partner (DELIVERY) machinery is reused as-is.
-- receiving_policy : SINGLE (one warehouse receipt) | PARTIAL (several receipts)
-- settlement_method: NET_OF_FEE (deduct fee from collection, remit net) | GROSS_THEN_FEE (remit full value, pay fee separately)
-- fee_mode         : ORDER_SHIPPING (order SHIPPING_VAL) | FIXED (fixed_fee per shipment) | ZONE_RATE (shipping_company_rates by zone)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shipping_companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `receiving_policy` enum('SINGLE','PARTIAL') NOT NULL DEFAULT 'SINGLE',
  `settlement_method` enum('NET_OF_FEE','GROSS_THEN_FEE') NOT NULL DEFAULT 'NET_OF_FEE',
  `fee_mode` enum('ORDER_SHIPPING','FIXED','ZONE_RATE') NOT NULL DEFAULT 'ORDER_SHIPPING',
  `fixed_fee` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `import_settings` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shipping_company_name` (`name`),
  UNIQUE KEY `uq_shipping_company_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- saved Excel statement settings (sheet, header row, columns by header name, status map) so staff only upload the file
ALTER TABLE `shipping_companies` ADD COLUMN IF NOT EXISTS `import_settings` text DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `shipping_company_rates` (
  `company_id` int(11) NOT NULL,
  `zone_id` int(11) NOT NULL,
  `fee` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`company_id`, `zone_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- how a shortage (returned piece the company never delivered back) is valued: COST or SALE_PRICE
ALTER TABLE `shipping_companies` ADD COLUMN IF NOT EXISTS `shortage_basis` enum('COST','SALE_PRICE') NOT NULL DEFAULT 'COST';
-- return_fee_mode: رسم الأمر المرفوض/المرتجع غير المدفوع (NONE بدون | SAME_AS_SHIPPING نفس رسم الشحن | FIXED رسم ثابت | STATEMENT_ONLY من كشف الشركة فقط)
ALTER TABLE `shipping_companies` ADD COLUMN IF NOT EXISTS `return_fee_mode` enum('NONE','SAME_AS_SHIPPING','FIXED','STATEMENT_ONLY') NOT NULL DEFAULT 'NONE';
ALTER TABLE `shipping_companies` ADD COLUMN IF NOT EXISTS `return_fixed_fee` decimal(15,2) NOT NULL DEFAULT 0.00;

-- Saved shipping-company statement (Excel) rows: one latest row per (company, order), linked to the custody list.
CREATE TABLE IF NOT EXISTS `shipping_company_imports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_hash` char(64) DEFAULT NULL,
  `rows_total` int(11) NOT NULL DEFAULT 0,
  `summary` text DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sci_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `shipping_company_statement_rows` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `import_id` int(11) NOT NULL,
  `order_id` bigint(20) DEFAULT NULL,
  `sub_order_id` varchar(32) NOT NULL,
  `row_no` int(11) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `fee` decimal(15,2) DEFAULT NULL,
  `file_status` varchar(100) DEFAULT NULL,
  `status_action` varchar(30) DEFAULT NULL,
  `derived` varchar(30) DEFAULT NULL,
  `proposed` varchar(30) DEFAULT NULL,
  `flag` enum('OK','REVIEW','ERROR') NOT NULL DEFAULT 'OK',
  `order_value` decimal(15,2) DEFAULT NULL,
  `shipping` decimal(15,2) DEFAULT NULL,
  `company_shipping` decimal(15,2) DEFAULT NULL,
  `shipping_diff` decimal(15,2) DEFAULT NULL,
  `expected` decimal(15,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `candidates` text DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scsr_company_order` (`company_id`, `sub_order_id`),
  KEY `idx_scsr_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Previous values of a statement row when a newer statement changes it (audit trail).
CREATE TABLE IF NOT EXISTS `shipping_company_statement_history` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `import_id` int(11) NOT NULL,
  `order_id` bigint(20) DEFAULT NULL,
  `sub_order_id` varchar(32) NOT NULL,
  `row_no` int(11) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `fee` decimal(15,2) DEFAULT NULL,
  `file_status` varchar(100) DEFAULT NULL,
  `flag` varchar(10) DEFAULT NULL,
  `derived` varchar(30) DEFAULT NULL,
  `proposed` varchar(30) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_scsh_company_order` (`company_id`, `sub_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ---------- Part 9: shipping-company fees (settlement at financial receipt) ----------
INSERT INTO `accounts`
  (`parent_id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `account_level`, `is_active`, `allow_posting`, `system_account`, `is_system_created`)
SELECT p.`id`, '5720', 'رسوم شركات الشحن', 'EXPENSE', 'DEBIT', 2, 1, 1, 0, 0
FROM `accounts` p
WHERE p.`account_code` = '5000'
  AND NOT EXISTS (SELECT 1 FROM `accounts` x WHERE x.`account_code` = '5720');

INSERT INTO `accounts`
  (`parent_id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `account_level`, `is_active`, `allow_posting`, `system_account`, `is_system_created`)
SELECT p.`id`, '2240', 'مستحق لشركات الشحن', 'LIABILITY', 'CREDIT', 3, 1, 1, 0, 0
FROM `accounts` p
WHERE p.`account_code` = '2200'
  AND NOT EXISTS (SELECT 1 FROM `accounts` x WHERE x.`account_code` = '2240');

INSERT INTO `account_settings` (`setting_key`, `account_id`, `description`)
SELECT 'SHIPPING_COMPANY_FEE', a.`id`, 'رسوم شركات الشحن (مصروف)'
FROM `accounts` a
WHERE a.`account_code` = '5720'
  AND NOT EXISTS (SELECT 1 FROM `account_settings` s WHERE s.`setting_key` = 'SHIPPING_COMPANY_FEE');

INSERT INTO `account_settings` (`setting_key`, `account_id`, `description`)
SELECT 'SHIPPING_COMPANY_PAYABLE', a.`id`, 'مستحق لشركات الشحن (التزام)'
FROM `accounts` a
WHERE a.`account_code` = '2240'
  AND NOT EXISTS (SELECT 1 FROM `account_settings` s WHERE s.`setting_key` = 'SHIPPING_COMPANY_PAYABLE');

CREATE TABLE IF NOT EXISTS `shipping_company_fees` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `order_id` bigint(20) NOT NULL,
  `fee` decimal(15,2) NOT NULL DEFAULT 0.00,
  `source` enum('STATEMENT','ORDER_SHIPPING','FIXED','ZONE_RATE','RETURN_FIXED','NONE') NOT NULL DEFAULT 'NONE',
  `method` enum('NET_OF_FEE','GROSS_THEN_FEE') NOT NULL,
  `gross_collected` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payable_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `journal_id` bigint(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scf_company_order` (`company_id`, `order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ---------- Part 10: shipping-company shortages (returned goods that never arrived) ----------
INSERT INTO `accounts`
  (`parent_id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `account_level`, `is_active`, `allow_posting`, `system_account`, `is_system_created`)
SELECT p.`id`, '1520', 'مستحق من شركات الشحن (عجز)', 'ASSET', 'DEBIT', 3, 1, 1, 0, 0
FROM `accounts` p
WHERE p.`account_code` = '1500'
  AND NOT EXISTS (SELECT 1 FROM `accounts` x WHERE x.`account_code` = '1520');

INSERT INTO `accounts`
  (`parent_id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `account_level`, `is_active`, `allow_posting`, `system_account`, `is_system_created`)
SELECT p.`id`, '4320', 'فروق تقييم عجز شركات الشحن', 'REVENUE', 'CREDIT', 2, 1, 1, 0, 0
FROM `accounts` p
WHERE p.`account_code` = '4300'
  AND NOT EXISTS (SELECT 1 FROM `accounts` x WHERE x.`account_code` = '4320');

INSERT INTO `account_settings` (`setting_key`, `account_id`, `description`)
SELECT 'SHIPPING_COMPANY_RECEIVABLE', a.`id`, 'مستحق من شركات الشحن (عجز)'
FROM `accounts` a
WHERE a.`account_code` = '1520'
  AND NOT EXISTS (SELECT 1 FROM `account_settings` s WHERE s.`setting_key` = 'SHIPPING_COMPANY_RECEIVABLE');

INSERT INTO `account_settings` (`setting_key`, `account_id`, `description`)
SELECT 'SHIPPING_SHORTAGE_DIFF', a.`id`, 'فروق تقييم عجز شركات الشحن'
FROM `accounts` a
WHERE a.`account_code` = '4320'
  AND NOT EXISTS (SELECT 1 FROM `account_settings` s WHERE s.`setting_key` = 'SHIPPING_SHORTAGE_DIFF');

CREATE TABLE IF NOT EXISTS `shipping_company_shortages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `order_id` bigint(20) NOT NULL,
  `pro_code` varchar(50) NOT NULL,
  `qty` int(11) NOT NULL,
  `unit_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `unit_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `basis` enum('COST','SALE_PRICE') NOT NULL,
  `value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('OPEN','SETTLED','WRITTEN_OFF') NOT NULL DEFAULT 'OPEN',
  `journal_id` bigint(20) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `posted_by` bigint(20) DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_scs_company` (`company_id`, `status`),
  KEY `idx_scs_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `shipping_company_shortages` ADD COLUMN IF NOT EXISTS `posted_by` bigint(20) DEFAULT NULL;
ALTER TABLE `shipping_company_shortages` ADD COLUMN IF NOT EXISTS `posted_at` datetime DEFAULT NULL;

-- ---------- Part 10b: تعديل يدوي لرسم الشركة / عمولة المندوب قبل السداد (بسبب مسجَّل) ----------
CREATE TABLE IF NOT EXISTS `shipping_settlement_overrides` (
  `order_id` bigint(20) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `original_amount` decimal(15,2) DEFAULT NULL,
  `kind` enum('COMPANY_FEE','COURIER_COMMISSION') NOT NULL,
  `reason` varchar(255) NOT NULL,
  `created_by` bigint(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ---------- Part 11: المرفوض يبقى في عهدة المندوب/الشركة بعد الاستلام المخزني ليظهر في الاستلام المالي ويُسوّى هناك ----------
-- (الإجراء كما هو، والتعديل الوحيد: لا يحذف الأمر المرفوض (حالة 5) من العهدة في آخره، ويحذف الإيقاف FREEZE فقط)
-- @PROC-BEGIN
DELIMITER $$
CREATE OR REPLACE PROCEDURE `setDeliveryConfirmationOptimized` (IN `p_session_id` VARCHAR(64), IN `p_executed_by` INT, IN `p_executed_group` VARCHAR(250), IN `p_branch_id` INT, IN `p_update_inventory` VARCHAR(10))   proc_exit: BEGIN
    DECLARE v_status VARCHAR(10) DEFAULT 'ERROR';
    DECLARE v_total_orders INT DEFAULT 0;
    DECLARE v_total_products INT DEFAULT 0;
    DECLARE v_total_orders_selse INT DEFAULT 0;
    DECLARE v_total_products_selse INT DEFAULT 0;
    DECLARE v_total_orders_refund INT DEFAULT 0;
    DECLARE v_total_products_refund INT DEFAULT 0;
    DECLARE v_message TEXT DEFAULT NULL;
    DECLARE v_rows INT DEFAULT 0;
    DECLARE v_order_logs INT DEFAULT 0;
    DECLARE v_logtext VARCHAR(250) DEFAULT CONCAT(' تم إجراء تحديث على بيانات أمر البيع من قِبل : ', p_executed_group);
    DECLARE v_store_id INT DEFAULT NULL;
    DECLARE v_inv_result_a INT DEFAULT 0;
    DECLARE v_inv_result_b INT DEFAULT 0;

    -- --------------------------------------------------------------
    -- التحقق من وجود سجلات لتأكيد SELSE و REFUND
    -- --------------------------------------------------------------
    IF NOT EXISTS (SELECT 1 FROM confirm_staging WHERE SESSION_ID = p_session_id) THEN
        SET v_message = 'لا توجد بيانات لتأكيد التسليم';
        SELECT 'error' AS status, v_message AS message, 45100 AS error_code;
        LEAVE proc_exit;
    END IF;
    
    -- --------------------------------------------------------------
    -- جلب عدد الاوامر والمنتجات
    -- --------------------------------------------------------------
    SELECT 
        COUNT(DISTINCT ORDER_ID) AS total_orders,
        COUNT(DISTINCT CASE WHEN TYPE = 'SELSE' THEN ORDER_ID END) AS selse_orders,
        COUNT(DISTINCT CASE WHEN TYPE = 'REFUND' THEN ORDER_ID END) AS refund_orders,
        COUNT(*) AS total_products,
        COUNT(CASE WHEN TYPE = 'SELSE' THEN 1 END) AS selse_products,
        COUNT(CASE WHEN TYPE = 'REFUND' THEN 1 END) AS refund_products
    INTO
        v_total_orders,
        v_total_orders_selse,
        v_total_orders_refund,
        v_total_products,
        v_total_products_selse,
        v_total_products_refund
    FROM confirm_staging
    WHERE SESSION_ID = p_session_id;
    
    SELECT STO_ID INTO v_store_id FROM STORES WHERE STTY_ID=2 AND BRANCH_ID = p_branch_id;
    
    -- --------------------------------------------------------------
    -- عد الاوامر الفريده لـ معالجة الأخطاء
    -- --------------------------------------------------------------
    IF v_total_orders = 0 THEN
        SET v_message = 'لا توجد بيانات لتأكيد التسليم';
        SELECT 'error' AS status, v_message AS message, 45101 AS error_code;
        LEAVE proc_exit;
    END IF;

    -- --------------------------------------------------------------
    -- 2. تحديث order_product بناءً على confirm_staging
    -- --------------------------------------------------------------
    UPDATE order_product op
    JOIN confirm_staging s 
        ON op.ORDER_ID = s.ORDER_ID AND op.PRO_CODE = s.PRO_CODE
    SET 
        op.DELIVERED_QTY = CASE WHEN s.TYPE = 'SELSE' THEN s.APP_QTY ELSE op.DELIVERED_QTY END,
        op.RETURNED_QTY  = CASE WHEN s.TYPE = 'REFUND' THEN s.APP_QTY ELSE op.RETURNED_QTY END,
        op.PRO_STATUSE   = CASE 
                            WHEN s.TYPE = 'REFUND' AND s.APP_QTY > 0 AND op.DELIVERED_QTY > s.APP_QTY THEN 'partial_refund'
                            WHEN s.TYPE = 'REFUND' AND s.APP_QTY > 0 AND op.DELIVERED_QTY = s.APP_QTY THEN 'refund'
                            WHEN s.TYPE = 'SELSE'  AND s.APP_QTY > 0 AND s.APP_QTY < op.QTY THEN 'partial'
                            WHEN s.TYPE = 'SELSE'  AND s.APP_QTY = 0 THEN 'rejected'
                            WHEN s.TYPE = 'SELSE'  AND s.APP_QTY = op.QTY THEN 'complete'
                            ELSE op.PRO_STATUSE
                          END,
        op.LAST_UPDATE = NOW(3)
    WHERE s.SESSION_ID = p_session_id;

    SET v_rows = ROW_COUNT();

    IF v_total_products != v_rows THEN
        SET v_message = CONCAT('لم يتم تحديث أي منتجات. المتوقع: ', v_total_products, ', المحدث: ', v_rows);
        SELECT 'error' AS status, v_message AS message, 45102 AS error_code;
        LEAVE proc_exit;
    END IF;

    -- --------------------------------------------------------------
    -- 3. تحديث orders بناءً على مجاميع order_product
    -- --------------------------------------------------------------
    UPDATE orders o
    JOIN (
        SELECT ORDER_ID,
               CASE 
                 WHEN SUM(RETURNED_QTY) > 0 AND SUM(DELIVERED_QTY) > SUM(RETURNED_QTY) THEN 7
                 WHEN SUM(RETURNED_QTY) > 0 AND SUM(DELIVERED_QTY) = SUM(RETURNED_QTY) THEN 6
                 WHEN SUM(RETURNED_QTY) = 0 AND SUM(DELIVERED_QTY) = 0 THEN 5
                 WHEN SUM(RETURNED_QTY) = 0 AND SUM(DELIVERED_QTY) < SUM(QTY) AND SUM(DELIVERED_QTY) > 0 THEN 4
                 WHEN SUM(RETURNED_QTY) = 0 AND SUM(DELIVERED_QTY) = SUM(QTY) THEN 3
                 ELSE NULL
               END AS NEW_ORDER_STATUS
        FROM order_product
        WHERE ORDER_ID IN (SELECT DISTINCT ORDER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id)
        GROUP BY ORDER_ID
    ) AS agg ON o.ORDER_ID = agg.ORDER_ID
    SET o.ORDER_STATUS = agg.NEW_ORDER_STATUS,
        o.LAST_UPDATE = NOW(3),
        o.DELIVERED_DATE = CASE WHEN agg.NEW_ORDER_STATUS IN (3,4,6,7) THEN NOW(3) ELSE o.DELIVERED_DATE END;
    
    SET v_rows = ROW_COUNT();
    
    IF v_total_orders != v_rows THEN
        SET v_message = CONCAT('لم يتم تحديث أي امر بيع. المتوقع: ', v_total_orders, ', المحدث: ', v_rows);
        SELECT 'error' AS status, v_message AS message, 45103 AS error_code;
        LEAVE proc_exit;
    END IF;
    -- --------------------------------
    -- 3+ - تسجيل اسباب الرفض
    -- --------------------------------
    DELETE R FROM reasons_for_product_rejection R
    INNER JOIN confirm_staging S ON S.ORDER_ID = R.ORDER_ID AND S.PRO_CODE = R.PRO_CODE AND S.TYPE = 'SELSE'
    INNER JOIN order_product OP ON OP.ORDER_ID = S.ORDER_ID AND OP.PRO_CODE = S.PRO_CODE
    WHERE S.APP_QTY >= OP.QTY;

    INSERT INTO reasons_for_product_rejection (ORDER_ID, PRO_CODE, REASON_ID, COMMENT)
    SELECT S.ORDER_ID, S.PRO_CODE, S.REASON_ID, COALESCE(S.COMMENT, '')
    FROM order_product OP
    INNER JOIN confirm_staging S ON OP.ORDER_ID = S.ORDER_ID AND OP.PRO_CODE = S.PRO_CODE AND S.TYPE = 'SELSE'
    WHERE S.REASON_ID IS NOT NULL AND S.APP_QTY >= 0 AND S.APP_QTY < OP.QTY
    ON DUPLICATE KEY UPDATE REASON_ID = VALUES(REASON_ID), COMMENT = VALUES(COMMENT);
    -- --------------------------------------------------------------
    -- 4. تحديث custody_product و custody_order للـ SELSE
    -- --------------------------------------------------------------
    IF v_total_orders_selse > 0 THEN
        UPDATE custody_product cp
        JOIN order_product op 
            ON cp.ORDER_ID = op.ORDER_ID AND cp.PRO_CODE = op.PRO_CODE
        JOIN confirm_staging s 
            ON s.ORDER_ID = op.ORDER_ID AND s.PRO_CODE = op.PRO_CODE AND s.TYPE = 'SELSE'
        SET cp.DELIVERED_QTY = op.DELIVERED_QTY,
            cp.PRO_STATUSE   = op.PRO_STATUSE,
            cp.LAST_UPDATE   = NOW(3)
        WHERE s.SESSION_ID = p_session_id;
        
        SET v_rows = ROW_COUNT();
        
        IF v_total_products_selse != v_rows THEN
            SET v_message = CONCAT('❌ لم يتم تحديث منتجات عهدة المندوب. المتوقع: ', v_total_products_selse, ', المحدث: ', v_rows);
            SELECT 'error' AS status, v_message AS message, 45104 AS error_code;
            LEAVE proc_exit;
        END IF;
        
        UPDATE custody_orders co
        JOIN orders o ON co.ORDER_ID = o.ORDER_ID
        JOIN (SELECT DISTINCT ORDER_ID, USER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id AND TYPE = 'SELSE') s
            ON co.ORDER_ID = s.ORDER_ID AND co.USER_ID = s.USER_ID
        SET co.CUST_STAT = 'DELIVERED',
            co.ORDER_STATUSE = o.ORDER_STATUS,
            co.LAST_UPDATE = NOW(3);
        
        SET v_rows = ROW_COUNT();
        
        IF v_total_orders_selse != v_rows THEN
            SET v_message = CONCAT('❌ لم يتم تحديث أي امر بيع من عهدة المندوب. المتوقع: ', v_total_orders_selse, ', المحدث: ', v_rows);
            SELECT 'error' AS status, v_message AS message, 45105 AS error_code;
            LEAVE proc_exit;
        END IF;
    END IF;
    
    -- --------------------------------------------------------------
    -- 5. تحديث custody_return_product و custody_return_order للـ REFUND
    -- --------------------------------------------------------------
    IF v_total_orders_refund > 0 THEN
        UPDATE custody_return_product crp
        JOIN order_product op 
            ON crp.ORDER_ID = op.ORDER_ID AND crp.PRO_CODE = op.PRO_CODE
        JOIN confirm_staging s 
            ON s.ORDER_ID = op.ORDER_ID AND s.PRO_CODE = op.PRO_CODE AND s.TYPE = 'REFUND'
        SET crp.RETURNED_QTY = op.RETURNED_QTY,
            crp.PRO_STATUSE = op.PRO_STATUSE,
            crp.LAST_UPDATE = NOW(3)
        WHERE s.SESSION_ID = p_session_id;
    
        SET v_rows = ROW_COUNT();

        IF v_total_products_refund != v_rows THEN
            SET v_message = CONCAT('❌ لم يتم تحديث منتجات مرتجع عهدة المندوب. المتوقع: ', v_total_products_refund, ', المحدث: ', v_rows);
            SELECT 'error' AS status, v_message AS message, 45106 AS error_code;
            LEAVE proc_exit;
        END IF;

        UPDATE custody_return_orders cro
        JOIN orders o ON cro.ORDER_ID = o.ORDER_ID
        JOIN (SELECT DISTINCT ORDER_ID, USER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id AND TYPE = 'REFUND') s
            ON cro.ORDER_ID = s.ORDER_ID AND cro.USER_ID = s.USER_ID
        SET cro.CUST_STAT = 'RETURNED',
            cro.ORDER_STATUSE = o.ORDER_STATUS,
            cro.LAST_UPDATE = NOW(3);
    
        SET v_rows = ROW_COUNT();

        IF v_total_orders_refund != v_rows THEN
            SET v_message = CONCAT('❌ لم يتم تحديث أي امر مرتجع من عهدة المندوب. المتوقع: ', v_total_orders_refund, ', المحدث: ', v_rows);
            SELECT 'error' AS status, v_message AS message, 45107 AS error_code;
            LEAVE proc_exit;
        END IF;
        
    END IF;
    
    -- --------------------------------------------------------------
    -- 6. تحديث أرصدة العملاء دفعة واحدة
    -- --------------------------------------------------------------
    IF v_total_orders_selse > 0 THEN
        CALL restClientDebtBatch(p_session_id, @error_code, @status, @message);
        
        IF @status = 'error' THEN
            SELECT @status AS status, @message AS message, @error_code AS error_code;
            LEAVE proc_exit;
        END IF;
    END IF;
    
    IF v_total_orders_refund > 0 THEN
        CALL reduceClientCreditBatch(p_session_id, @error_code, @status, @message);
        IF @status = 'error' THEN
            SELECT @status AS status, @message AS message, @error_code AS error_code;
            LEAVE proc_exit;
        END IF;
    END IF; 
    -- --------------------------------------------------------------
    -- 9. تحديث مخزون المنتجات 
    -- --------------------------------------------------------------
    IF p_update_inventory = 'true' THEN
        IF v_total_orders_selse > 0 THEN
		    CALL inventory_movement_sales_order(p_session_id, 'RETURN_SHIPP', p_executed_by, v_store_id, v_inv_result_a);
			IF v_inv_result_a <= 0  THEN
				SELECT 'error' AS Status, 'فشل فى تحديث المخزون' AS message, 45450 AS error_code;
				LEAVE proc_exit;
			END IF;
			
			UPDATE custody_orders SET RETURNS = 1, LAST_UPDATE = NOW(6) WHERE ORDER_ID IN(SELECT DISTINCT ORDER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id AND TYPE = 'SELSE');
			IF ROW_COUNT() <= 0  THEN
				SELECT 'error' AS Status, 'فشل فى تحديث استلام مرفوضات عهدة المندوب' AS message, 45450 AS error_code;
				LEAVE proc_exit;
			END IF;
			
        END IF;
		
        IF v_total_orders_refund > 0 THEN
		    CALL inventory_movement_sales_order(p_session_id, 'RETURN_SALES', p_executed_by, v_store_id, v_inv_result_a);
			IF v_inv_result_a <= 0  THEN
				SELECT 'error' AS Status, 'فشل فى تحديث المخزون' AS message, 45450 AS error_code;
				LEAVE proc_exit;
			END IF;
			
            UPDATE custody_return_orders SET RETURNS = 1, LAST_UPDATE = NOW(6) WHERE ORDER_ID IN(SELECT DISTINCT ORDER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id AND TYPE = 'REFUND');
        	IF ROW_COUNT() <= 0  THEN
                SELECT 'error' AS Status, 'فشل فى تحديث استلام مرتجعات عهدة المندوب' AS message, 45450 AS error_code;
                LEAVE proc_exit;
            END IF;
			
        END IF;
        
        
    END IF;
    
	-- --------------------------------------------------------------
    -- 7. اضافة حركة الشحن دفعه واحده
    -- --------------------------------------------------------------
    CALL shipping_movement(p_session_id, p_executed_by, @error_code, @status, @message);
    
    IF @status = 'error' THEN
        SELECT @status AS status, @message AS message, @error_code AS error_code;
        LEAVE proc_exit;
    END IF;
	
    -- --------------------------------------------------------------
    -- 8. اضافة حركة log دفعه واحده
    -- --------------------------------------------------------------
    CALL set_orders_log(p_session_id, p_executed_by, p_executed_group, @error_code, @status, @message);
    IF @status = 'error' THEN
        SELECT @status AS status, @message AS message, @error_code AS error_code;
        LEAVE proc_exit;
    END IF;
    DELETE FROM custody_orders WHERE RETURNS = 1 AND CUST_STAT = 'FREEZE' AND ORDER_ID IN(SELECT DISTINCT ORDER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id AND TYPE = 'SELSE');
	DELETE FROM custody_return_orders  WHERE RETURNS = 1 AND ORDER_ID IN(SELECT DISTINCT ORDER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id AND TYPE = 'REFUND');

    -- --------------------------------------------------------------
    -- 10. النتيجة النهائية
    -- --------------------------------------------------------------
    SELECT 'success' AS status, 
           CONCAT('تم تأكيد تسليم الشحنة ', v_total_orders, ' أمر بنجاح (', 
                  v_total_orders_selse, ' مبيعات, ', 
                  v_total_orders_refund, ' مرتجعات)') AS message;
           
END$$
DELIMITER ;
-- @PROC-END
