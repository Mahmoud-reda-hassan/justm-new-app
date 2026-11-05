-- المرحلة 19: منع تكرار تسوية عهدة المندوب وصرف العمولة عند إعادة إرسال الطلب.
-- ينفذ مرة واحدة على قواعد البيانات القائمة بعد أخذ نسخة احتياطية.
-- ملف التثبيت الجديد يحتوي الأعمدة والفهارس بالفعل.

ALTER TABLE `delivery_company_settlements`
    ADD COLUMN `request_key` VARCHAR(64) NULL AFTER `created_by`,
    ADD COLUMN `request_hash` CHAR(64) NULL AFTER `request_key`,
    ADD UNIQUE KEY `uq_delivery_settlement_request_key` (`request_key`);

ALTER TABLE `delivery_commission_payments`
    ADD COLUMN `request_key` VARCHAR(64) NULL AFTER `created_by`,
    ADD COLUMN `request_hash` CHAR(64) NULL AFTER `request_key`,
    ADD UNIQUE KEY `uq_delivery_commission_payment_request_key` (`request_key`);
