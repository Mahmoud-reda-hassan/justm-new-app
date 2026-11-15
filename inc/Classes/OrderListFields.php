<?php
/**
 * حقول وجداول عرض الأمر بنفس بيانات شاشة قائمة الأوامر (العميل، الهاتف، العنوان، المنطقة، المندوب، الإجماليات...)
 * قراءة فقط. تُستخدم في قوائم شاشة "رد أوامر المندوب للمخزن".
 * يفترض أن جدول الأوامر مُسمّى o في الاستعلام.
 */
class OrderListFields {
    const COLUMNS = "o.ORDER_ID, o.SUB_ORDER_ID, o.ORDER_STATUS, o.EXISTS_ID, o.ORDER_TYPE_ID, o.ORDER_SUCCESS,
        D.ORDER_TYPE_NAME, C.STA_NAME, B.EXISTS_NAME,
        CASE WHEN o.ORDER_SUCCESS = 0 THEN 'مفتوح' ELSE 'مغلق' END AS SUCCESS_STAT,
        H.CLIENT_NAME, o.TELEPHONE, o.ADDRESS, K.CI_NAME AS CITY_NAME, R.GOV_NAME, L.ZONE_NAME,
        o.DELIVERY_ID, CONCAT(F.FORENAME, ' ', F.LASTNAME) AS DELIVERY_NAME,
        CONCAT(E.FORENAME, ' ', E.LASTNAME) AS ORDERBY,
        o.SHIPPING_VAL, o.SHIPP_PAYMENT, o.DATE_CREATED, o.SHIPPING_DATE, o.DELIVERED_DATE, o.LAST_UPDATE,
        o.COMMENT, o.SALE_ENTRY_ID,
        (SELECT SUM(op.QTY) FROM order_product op WHERE op.ORDER_ID = o.ORDER_ID) AS PRO_QTY,
        (SELECT ROUND(SUM(op.QTY * (op.PRICE - op.DISCOUNT)), 2) FROM order_product op WHERE op.ORDER_ID = o.ORDER_ID) AS ORDER_TOTAL";

    // نفس ربط شاشة قائمة الأوامر (ordersList_BE): العميل والمدينة والمنطقة عن طريق العنوان
    const JOINS = "LEFT JOIN order_exists B ON o.EXISTS_ID = B.EXISTS_ID
        LEFT JOIN order_statuse C ON o.ORDER_STATUS = C.STA_ID
        LEFT JOIN order_type D ON o.ORDER_TYPE_ID = D.ORDER_TYPE_ID
        LEFT JOIN users E ON o.ORDER_BY = E.USER_ID
        LEFT JOIN users F ON o.DELIVERY_ID = F.USER_ID
        LEFT JOIN addresses G ON o.ADDRESS_ID = G.ADDRESS_ID
        LEFT JOIN client H ON G.CLIENT_ID = H.CLIENT_ID
        LEFT JOIN city K ON G.CITY_ID = K.CI_ID
        LEFT JOIN gov R ON K.GOV_ID = R.GOV_ID
        LEFT JOIN zones L ON K.ZONE_ID = L.ZONE_ID";
}
