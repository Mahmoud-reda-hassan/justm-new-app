-- المرحلة 11: ربط طرق الدفع بحساب الأستاذ الفعلي لكل فرع.
-- لا تُدرج بيانات افتراضية؛ يجب أن يحدد المحاسب الحساب الصحيح لكل طريقة دفع/فرع.
-- نفّذ على قاعدة اختبار أولًا، ثم راجع الترحيل قبل الإنتاج.
CREATE TABLE IF NOT EXISTS payment_method_accounts (
    id INT NOT NULL AUTO_INCREMENT,
    payment_method_id INT NOT NULL,
    branch_id INT NOT NULL,
    account_id INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_method_branch (payment_method_id, branch_id),
    KEY idx_pma_account (account_id),
    CONSTRAINT fk_pma_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id),
    CONSTRAINT fk_pma_account FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- قبل إدخال أي ربط، تأكد أن حساب مردودات المبيعات مستقل عن حساب إيرادات المبيعات.
SELECT s1.setting_key, s1.account_id, a.account_name
FROM account_settings s1
JOIN accounts a ON a.id = s1.account_id
WHERE s1.setting_key IN ('SALES', 'SALES_RETURNS');

-- بعد اعتماد الحساب الصحيح من المحاسب، حدّث SALES_RETURNS إلى حساب مستقل.
-- لا يمكن اختيار رقم الحساب نيابةً عن مالك الدفاتر؛ لا تنفّذ UPDATE تخمينيًا.
