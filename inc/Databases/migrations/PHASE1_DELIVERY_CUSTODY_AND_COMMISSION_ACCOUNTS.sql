-- Phase 1: accounts required by the courier (delivery agent) cycle.
-- Approved by the project owner on 2026-11-13:
--   1510 courier custody (child of 1500), 2210 courier commission payable (child of 2200).
-- Idempotent: safe to run more than once. Run on a TEST copy first.

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
