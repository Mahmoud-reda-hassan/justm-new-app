<?php
/**
 * حقول وجداول عرض الأمر بنفس بيانات شاشة قائمة الأوامر (العميل، الهاتف، العنوان، المنطقة، المندوب، الإجماليات...)
 * قراءة فقط. تُستخدم في قوائم شاشة "رد أوامر المندوب للمخزن".
 * يفترض أن جدول الأوامر مُسمّى o في الاستعلام.
 */
class OrderListFields {
    const COLUMNS = "o.ORDER_ID, o.SUB_ORDER_ID, o.ORDER_STATUS, o.ORDER_TYPE_ID, ot.ORDER_TYPE_NAME,
        c.CLIENT_NAME, o.TELEPHONE, o.ADDRESS, ci.CI_NAME AS CITY_NAME, z.ZONE_NAME,
        o.DELIVERY_ID, CONCAT(u.FORENAME, ' ', u.LASTNAME) AS DELIVERY_NAME,
        o.SHIPPING_VAL, o.SHIPP_PAYMENT, o.DATE_CREATED, o.SHIPPING_DATE, o.DELIVERED_DATE, o.LAST_UPDATE,
        o.COMMENT, o.SALE_ENTRY_ID,
        (SELECT SUM(op.QTY) FROM order_product op WHERE op.ORDER_ID = o.ORDER_ID) AS PRO_QTY,
        (SELECT ROUND(SUM(op.QTY * (op.PRICE - op.DISCOUNT)), 2) FROM order_product op WHERE op.ORDER_ID = o.ORDER_ID) AS ORDER_TOTAL";

    const JOINS = "LEFT JOIN users u ON u.USER_ID = o.DELIVERY_ID
        LEFT JOIN order_type ot ON ot.ORDER_TYPE_ID = o.ORDER_TYPE_ID
        LEFT JOIN telephones t ON t.TEL_ID = o.TEL_ID
        LEFT JOIN client c ON c.CLIENT_ID = t.CLIENT_ID
        LEFT JOIN city ci ON ci.CI_ID = o.CITY_ID
        LEFT JOIN zones z ON z.ZONE_ID = ci.ZONE_ID";
}
