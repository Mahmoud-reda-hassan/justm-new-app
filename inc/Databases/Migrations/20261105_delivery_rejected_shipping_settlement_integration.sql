-- PHASE29B: ربط تحصيل رسوم الشحن المرفوضة بمسار تسوية عهدة المندوب الحالي.
-- يشترط تنفيذ 20261105_delivery_rejected_shipping_collection.sql أولًا.
-- payment_method_id = NULL يعني أن العميل دفع للمندوب مباشرة، وليس عبر طريقة دفع خزينة الشركة.
ALTER TABLE `delivery_customer_collections`
    MODIFY COLUMN `payment_method_id` BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS `collection_source`
        ENUM('ORDER_COLLECTION','REJECTED_SHIPPING') NOT NULL DEFAULT 'ORDER_COLLECTION';

ALTER TABLE `delivery_rejected_shipping_collections`
    ADD COLUMN IF NOT EXISTS `delivery_collection_id` BIGINT UNSIGNED NULL,
    ADD UNIQUE INDEX IF NOT EXISTS `uq_rejected_shipping_delivery_collection` (`delivery_collection_id`);
