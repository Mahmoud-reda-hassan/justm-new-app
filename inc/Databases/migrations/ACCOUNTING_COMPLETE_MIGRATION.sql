-- ============================================================
-- ACCOUNTING COMPLETE MIGRATION (single file)
-- Brings an existing Just-M database up to the new GL-based accounting.
-- Idempotent: safe to run more than once.
-- *** RUN ON A TEST COPY OF THE DATABASE FIRST, NEVER DIRECTLY ON PRODUCTION. ***
-- Take a full backup before running. Nothing here deletes or rewrites existing data.
-- Contents: (1) courier custody + commission payable accounts, (2) reopen inventory movement type,
--           (3) marketer commission accounts, (4) read-only GL reporting views.
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
