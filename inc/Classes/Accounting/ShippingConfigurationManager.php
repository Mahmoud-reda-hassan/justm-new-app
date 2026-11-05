<?php
final class ShippingConfigurationManager {
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }
    public function isShippingAmountEditAllowed(): bool {
        $stmt = $this->db->prepare('SELECT SETTING_VALUE FROM system_settings WHERE SETTING_KEY = ? LIMIT 1');
        $stmt->execute(['ALLOW_SHIPPING_AMOUNT_EDIT_DURING_CONFIRMATION']);
        return in_array(strtolower(trim((string)$stmt->fetchColumn())), ['1','true','yes','on'], true);
    }
    public function setShippingAmountEditAllowed(bool $allowed, int $updatedBy): void {
        if ($updatedBy <= 0) throw new InvalidArgumentException('Invalid actor.');
        $stmt = $this->db->prepare('INSERT INTO system_settings (SETTING_KEY, SETTING_VALUE, SETTING_DESCRIPTION) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE SETTING_VALUE = VALUES(SETTING_VALUE), SETTING_DESCRIPTION = VALUES(SETTING_DESCRIPTION), LAST_UPDATE = CURRENT_TIMESTAMP');
        $stmt->execute(['ALLOW_SHIPPING_AMOUNT_EDIT_DURING_CONFIRMATION', $allowed ? '1' : '0', 'السماح بتعديل قيمة الشحن أثناء تأكيد محاولة التوصيل']);
    }
}
