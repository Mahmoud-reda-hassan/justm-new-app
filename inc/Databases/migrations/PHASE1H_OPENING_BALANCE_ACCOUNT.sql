-- Phase 1h: opening balance equity account (3300). Idempotent. Run on a TEST copy first.

-- Opening balance equity account (counterpart of legacy-balance alignment entries).
INSERT INTO `accounts`
  (`parent_id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `account_level`, `is_active`, `allow_posting`, `system_account`, `is_system_created`)
SELECT p.`id`, '3300', 'أرصدة افتتاحية', 'EQUITY', 'CREDIT', 2, 1, 1, 0, 0
FROM `accounts` p
WHERE p.`account_code` = '3000'
  AND NOT EXISTS (SELECT 1 FROM `accounts` x WHERE x.`account_code` = '3300');

INSERT INTO `account_settings` (`setting_key`, `account_id`, `description`)
SELECT 'OPENING_BALANCE_EQUITY', a.`id`, 'أرصدة افتتاحية (مقابل تسويات النظام القديم)'
FROM `accounts` a
WHERE a.`account_code` = '3300'
  AND NOT EXISTS (SELECT 1 FROM `account_settings` s WHERE s.`setting_key` = 'OPENING_BALANCE_EQUITY');
