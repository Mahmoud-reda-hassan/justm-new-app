-- Phase 1g: marketer / team-leader commission accounts.
-- Approved by the project owner on 2026-11-14:
--   5710 marketer commissions expense (child of 5000), 2220 marketer commission payable (child of 2200).
-- Idempotent: safe to run more than once. Run on a TEST copy first.

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
