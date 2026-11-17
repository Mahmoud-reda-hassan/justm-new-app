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

