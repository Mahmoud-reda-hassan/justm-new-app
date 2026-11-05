-- PHASE26: إعداد السماح بتعديل رسوم الشحن أثناء تأكيد التوصيل.
-- القيمة الافتراضية 0 حتى لا يتغير السلوك الحالي تلقائيًا.
INSERT INTO `system_settings` (`SETTING_KEY`, `SETTING_VALUE`, `SETTING_DESCRIPTION`)
VALUES (
    'ALLOW_SHIPPING_AMOUNT_EDIT_DURING_CONFIRMATION',
    '0',
    'السماح بتعديل قيمة الشحن أثناء تأكيد محاولة التوصيل'
)
ON DUPLICATE KEY UPDATE
    `SETTING_KEY` = VALUES(`SETTING_KEY`);
