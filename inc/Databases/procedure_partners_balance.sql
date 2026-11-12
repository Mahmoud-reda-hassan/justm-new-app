DELIMITER $$
CREATE PROCEDURE `restClientDebtBatch`(
    IN p_session_id VARCHAR(64),
    OUT p_error_code INT,
    OUT p_status VARCHAR(10),
    OUT p_message TEXT
)
proc_exit:
BEGIN
    DECLARE
        v_rowsInsert INT DEFAULT 0 ; DECLARE v_rowsUpdate INT DEFAULT 0 ; DECLARE v_rowsDelete INT DEFAULT 0 ; DECLARE v_total_record INT DEFAULT 0 ; DECLARE v_total_orders_error INT DEFAULT 0 ; DECLARE v_total_rejact_value_error INT DEFAULT 0 ; DECLARE v_json_result JSON DEFAULT NULL ; DECLARE v_json_rejact_value_result JSON DEFAULT NULL ;
        -- --------------------------------------------------------------
        -- 1. التحقق من وجود سجلات لتأكيد SELSE و REFUND
        -- --------------------------------------------------------------
        IF NOT EXISTS(
        SELECT
            1
        FROM
            confirm_staging
        WHERE
            SESSION_ID = p_session_id
    ) THEN
SET
    p_error_code = 45200 ;
SET
    p_status = 'error' ;
SET
    p_message = 'لا توجد بيانات لتأكيد التسليم' ; LEAVE proc_exit ;
END IF ;
-- --------------------------------------------------------------
-- جدول مؤقت يجمع بيانات كل أمر للجلسة المحددة
-- --------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS
    checkorders ;
CREATE TEMPORARY TABLE checkorders(
    SESSION_ID VARCHAR(64) NOT NULL,
    ORDER_ID INT NOT NULL,
    CLIENT_ID INT NOT NULL,
    SUB_ORDER_ID VARCHAR(64) NOT NULL,
    ORDER_STATUS INT NOT NULL,
    SUM_ORDER DECIMAL(18, 3) NOT NULL DEFAULT 0,
    SUM_REJECTED DECIMAL(18, 3) NOT NULL DEFAULT 0,
    SUM_DELIVERED DECIMAL(18, 3) NOT NULL DEFAULT 0,
    SUM_RETURNED DECIMAL(18, 3) NOT NULL DEFAULT 0,
    SHIPPING_VAL DECIMAL(18, 3) NOT NULL DEFAULT 0,
    TYPE ENUM('SELSE', 'REFUND') NOT NULL,
    CREATED_AT DATETIME(3) DEFAULT NOW(3),
    PRIMARY KEY(SESSION_ID, ORDER_ID)
) ENGINE = InnoDB ;
-- --------------------------------------------------------------
-- تعبئة الجدول المؤقت ببيانات الطلبات الخاصة بالجلسة
-- --------------------------------------------------------------
INSERT INTO checkorders(
    SESSION_ID,
    ORDER_ID,
    CLIENT_ID,
    SUB_ORDER_ID,
    ORDER_STATUS,
    SUM_ORDER,
    SUM_REJECTED,
    SUM_DELIVERED,
    SUM_RETURNED,
    SHIPPING_VAL,
    TYPE
)
SELECT
    cs.SESSION_ID,
    cs.ORDER_ID,
    a.CLIENT_ID,
    o.SUB_ORDER_ID,
    o.ORDER_STATUS,
    COALESCE(
        SUM(
            (op.PRICE - op.DISCOUNT) * op.QTY
        ),
        0
    ) AS SUM_ORDER,
    COALESCE(
        SUM(
            CASE WHEN o.ORDER_STATUS IN(4, 5) THEN(op.PRICE - op.DISCOUNT) *(
                op.QTY - COALESCE(op.DELIVERED_QTY, 0)
            ) ELSE 0
        END
    ),
    0
) AS SUM_REJECTED,
COALESCE(
    SUM(
        CASE WHEN o.ORDER_STATUS IN(3, 4) THEN(op.PRICE - op.DISCOUNT) * COALESCE(op.DELIVERED_QTY, 0) ELSE 0
    END
),
0
) AS SUM_DELIVERED,
COALESCE(
    SUM(
        CASE WHEN o.ORDER_STATUS > 5 THEN(op.PRICE - op.DISCOUNT) * COALESCE(op.RETURNED_QTY, 0) ELSE 0
    END
),
0
) AS SUM_RETURNED,
COALESCE(o.SHIPPING_VAL, 0) AS SHIPPING_VAL,
cs.TYPE
FROM
    (
    SELECT
        SESSION_ID,
        ORDER_ID,
        TYPE
    FROM
        confirm_staging
    WHERE TYPE
        = 'SELSE' AND SESSION_ID = p_session_id
    GROUP BY
        SESSION_ID,
        ORDER_ID,
        TYPE
) cs
LEFT JOIN ORDERS O ON
    cs.ORDER_ID = o.ORDER_ID
LEFT JOIN order_product op ON
    o.ORDER_ID = op.ORDER_ID
LEFT JOIN addresses a ON
    o.ADDRESS_ID = a.ADDRESS_ID
GROUP BY
    cs.SESSION_ID,
    cs.ORDER_ID,
    a.CLIENT_ID,
    o.SUB_ORDER_ID,
    o.ORDER_STATUS,
    o.SHIPPING_VAL,
    cs.TYPE ;
    -- --------------------------------------------------------------
    -- عدد السجلات المجمعة (الأوامر من نوع SELSE ضمن الجلسة)
    -- --------------------------------------------------------------
SELECT
    COUNT(*)
INTO v_total_record
FROM
    checkorders
WHERE
    SESSION_ID = p_session_id AND TYPE = 'SELSE' AND(
        ORDER_STATUS != 5 OR(
            ORDER_STATUS = 5 AND SHIPPING_VAL > 0
        )
    ) ;
    -- --------------------------------------------------------------
    -- التحقق من وجود SUB_ORDER_ID لكل أمر (خطأ إن كان مفقود)
    -- --------------------------------------------------------------
SELECT
    COUNT(ORDER_ID),
    JSON_OBJECT(
        'status',
        'error',
        'title',
        'خطاء',
        'message',
        CONCAT(
            'يوجد عدد (',
            COUNT(ORDER_ID),
            ') من أوامر البيع التي لا تحتوي على بيانات فرعية.'
        ),
        'orders',
        JSON_ARRAYAGG(SUB_ORDER_ID)
    )
INTO v_total_orders_error, v_json_result
FROM
    checkorders
WHERE
    SESSION_ID = p_session_id AND TYPE = 'SELSE' AND(
        SUB_ORDER_ID IS NULL OR SUB_ORDER_ID = ''
    ) ; IF v_total_orders_error > 0 THEN
SET
    p_status = 'error' ;
SET
    p_message = CAST(v_json_result AS CHAR) ;
SET
    p_error_code = 45201 ; LEAVE proc_exit ;
END IF ;
-- --------------------------------------------------------------
-- التحقق من أن قيمة المرفوضات لا تتجاوز قيمة الطلب
-- --------------------------------------------------------------
SELECT
    COUNT(ORDER_ID),
    JSON_OBJECT(
        'status',
        'error',
        'title',
        'خطاء',
        'message',
        CONCAT(
            'يوجد عدد (',
            COUNT(ORDER_ID),
            ') من أوامر البيع قيمة المرفوض اكبر من قيمة الطلب.'
        ),
        'orders',
        JSON_ARRAYAGG(SUB_ORDER_ID)
    )
INTO v_total_rejact_value_error, v_json_rejact_value_result
FROM
    checkorders
WHERE
    SESSION_ID = p_session_id AND TYPE = 'SELSE' AND SUM_ORDER < SUM_REJECTED ; IF v_total_rejact_value_error > 0 THEN
SET
    p_status = 'error' ;
SET
    p_message = CAST(
        v_json_rejact_value_result AS CHAR
    ) ;
SET
    p_error_code = 45202 ; LEAVE proc_exit ;
END IF ;
-- --------------------------------------------------------------
-- تحديث أرصدة العملاء الموجودة - الإصدار المعدل
-- --------------------------------------------------------------
UPDATE
    partners_balance pb
JOIN(
    SELECT ORDER_ID,
        CLIENT_ID,
        SUM_ORDER,
        ORDER_STATUS,
        SUM_REJECTED,
        SUM_RETURNED,
        SUM_DELIVERED,
        SHIPPING_VAL
    FROM
        checkorders
    WHERE
        SESSION_ID = p_session_id AND TYPE = 'SELSE' AND(
            ORDER_STATUS IN(2, 3, 4) OR(
                ORDER_STATUS = 5 AND SHIPPING_VAL > 0
            )
        )
) AS src
ON
    pb.PARTNER_ID = src.CLIENT_ID AND pb.REFERENCE_ID = src.ORDER_ID
SET
    pb.BALANCE = CASE WHEN src.ORDER_STATUS = 4 THEN src.SUM_DELIVERED + src.SHIPPING_VAL WHEN src.ORDER_STATUS = 5 AND src.SHIPPING_VAL > 0 THEN src.SHIPPING_VAL ELSE src.SUM_ORDER + src.SHIPPING_VAL
END,
pb.LAST_UPDATE = NOW(6)
WHERE
    pb.REFERENCE_ID = src.ORDER_ID AND pb.PARTNER_TYPE = 'CLIENT' AND pb.TRANSACTION_TYPE = 'DEBIT' AND pb.TRANSACTION_CATEGORY_ID = 8 AND CASE WHEN src.ORDER_STATUS = 4 THEN src.SUM_DELIVERED + src.SHIPPING_VAL WHEN src.ORDER_STATUS = 5 AND src.SHIPPING_VAL > 0 THEN src.SHIPPING_VAL ELSE src.SUM_ORDER + src.SHIPPING_VAL
END > 0 ;
SET
    v_rowsUpdate = ROW_COUNT() ;
    -- --------------------------------------------------------------
    -- إدراج أرصدة جديدة إن لم توجد
    -- --------------------------------------------------------------
INSERT INTO partners_balance(
    PARTNER_TYPE,
    PARTNER_ID,
    REFERENCE_ID,
    TRANSACTION_TYPE,
    BALANCE,
    NOTE,
    TRANSACTION_CATEGORY_ID,
    USE_OF_CREDIT,
    LAST_UPDATE
)
SELECT
    'CLIENT',
    CLIENT_ID,
    ORDER_ID,
    'DEBIT',
    CASE WHEN ORDER_STATUS = 4 THEN SUM_DELIVERED WHEN ORDER_STATUS = 5 AND SHIPPING_VAL > 0 THEN SHIPPING_VAL ELSE SUM_ORDER + SHIPPING_VAL
    END,
    'مديونية عميل ناتجة عن أمر بيع',
    8,
    0,
    NOW(6)
FROM
    checkorders
WHERE
    SESSION_ID = p_session_id AND TYPE = 'SELSE' AND(
        ORDER_STATUS IN(2, 3, 4) OR(
            ORDER_STATUS = 5 AND SHIPPING_VAL > 0
        )
    ) AND NOT EXISTS(
    SELECT
        1
    FROM
        partners_balance pb
    WHERE
        pb.PARTNER_TYPE = 'CLIENT' AND pb.TRANSACTION_TYPE = 'DEBIT' AND pb.TRANSACTION_CATEGORY_ID = 8 AND pb.PARTNER_ID = checkorders.CLIENT_ID AND pb.REFERENCE_ID = checkorders.ORDER_ID
) ;
SET
    v_rowsInsert = ROW_COUNT() ;
    -- --------------------------------------------------------------
    -- حذف الأرصدة إذا كانت حالة الطلب ملغاة (ORDER_STATUS = 5)
    -- مع التحقق من عدم وجود رصيد موجب
    -- --------------------------------------------------------------
DELETE
    pb
FROM
    partners_balance pb
JOIN(
    SELECT
        ORDER_ID,
        CLIENT_ID
    FROM
        checkorders
    WHERE
        SESSION_ID = p_session_id AND TYPE = 'SELSE' AND ORDER_STATUS = 5 AND SHIPPING_VAL <= 0
) AS tmp
ON
    pb.PARTNER_TYPE = 'CLIENT' AND pb.TRANSACTION_TYPE = 'DEBIT' AND pb.TRANSACTION_CATEGORY_ID = 8 AND pb.PARTNER_ID = tmp.CLIENT_ID AND pb.REFERENCE_ID = tmp.ORDER_ID
WHERE
    pb.BALANCE <= 0 ;
SET
    v_rowsDelete = ROW_COUNT() ;
    -- --------------------------------------------------------------
    -- مقارنة النتيجة الكلية مع العمليات التي تمت
    -- --------------------------------------------------------------
    IF v_total_record =(
        v_rowsInsert + v_rowsUpdate + v_rowsDelete
    ) THEN
SET
    p_status = 'success' ;
SET
    p_message = 'تم تحديث مديونية العميل بنجاح' ;
SET
    p_error_code = 0 ; ELSE
SET
    p_status = 'error' ;
SET
    p_message = CONCAT(
        'فشل تحديث مديونية العميل - عدم تطابق في عدد السجلات: متوقع ',
        v_total_record,
        ', تم إدخال ',
        (
            v_rowsInsert + v_rowsUpdate + v_rowsDelete
        )
    ) ;
SET
    p_error_code = 45203 ; LEAVE proc_exit ;
    END IF ; END$$
DELIMITER ;






DELIMITER $$
CREATE PROCEDURE `reduceClientCreditBatch`(IN `p_session_id` VARCHAR(64), OUT `p_error_code` INT, OUT `p_status` VARCHAR(10), OUT `p_message` TEXT)
proc_exit: BEGIN

    

    DECLARE v_rowsInsert INT DEFAULT 0;

    DECLARE v_rowsUpdate INT DEFAULT 0;

    DECLARE v_rowsDelete INT DEFAULT 0;

    DECLARE v_error_msg VARCHAR(250);

    DECLARE v_total_record INT DEFAULT 0;

    DECLARE v_total_orders_error INT DEFAULT 0;

    DECLARE v_total_reject_value_error INT DEFAULT 0;

    DECLARE v_json_result JSON DEFAULT NULL;

    DECLARE v_json_reject_value_result JSON DEFAULT NULL;

    

    -- --------------------------------------------------------------

    -- 1. التحقق من وجود سجلات REFUND فقط

    -- --------------------------------------------------------------

    IF NOT EXISTS (

        SELECT 1 FROM confirm_staging 

        WHERE SESSION_ID = p_session_id AND TYPE = 'REFUND'

    ) THEN

        SET p_status = 'error';

        SET p_message = 'لا توجد بيانات مرتجعات لتنفيذ هذا الإجراء';

        SET p_error_code = 45301;

        LEAVE proc_exit;

    END IF;

    

    -- --------------------------------------------------------------

    -- جدول مؤقت لبيانات المرتجعات فقط

    -- --------------------------------------------------------------

    DROP TEMPORARY TABLE IF EXISTS refund_orders;

    CREATE TEMPORARY TABLE refund_orders (

        SESSION_ID      VARCHAR(64) NOT NULL,

        ORDER_ID        INT NOT NULL,

        CLIENT_ID       INT NOT NULL,

        SUB_ORDER_ID    VARCHAR(64) NOT NULL,

        ORDER_STATUS    INT NOT NULL,

        TOTAL_RETURN    DECIMAL(18,3) NOT NULL DEFAULT 0,

        SUM_ORDER       DECIMAL(18,3) NOT NULL DEFAULT 0,

        TYPE            ENUM('REFUND') NOT NULL,

        CREATED_AT      DATETIME(3) DEFAULT NOW(3),

        PRIMARY KEY (SESSION_ID, ORDER_ID)

    ) ENGINE=InnoDB;

    

    -- --------------------------------------------------------------

    -- تعبئة الجدول ببيانات المرتجعات فقط

    -- --------------------------------------------------------------

    INSERT INTO refund_orders (

        SESSION_ID, ORDER_ID, CLIENT_ID, SUB_ORDER_ID, ORDER_STATUS,

        TOTAL_RETURN, SUM_ORDER, TYPE

    )

    SELECT

        s.SESSION_ID,

        s.ORDER_ID,

        A.CLIENT_ID,

        o.SUB_ORDER_ID,

        o.ORDER_STATUS,

        COALESCE(SUM((op.PRICE - op.DISCOUNT) * op.RETURNED_QTY), 0) AS TOTAL_RETURN,

        COALESCE(SUM((op.PRICE - op.DISCOUNT) * op.DELIVERED_QTY), 0) AS SUM_ORDER,

        s.TYPE

    FROM confirm_staging s

    LEFT JOIN orders o ON s.ORDER_ID = o.ORDER_ID

    LEFT JOIN order_product op ON s.ORDER_ID = op.ORDER_ID AND s.PRO_CODE = op.PRO_CODE

    LEFT JOIN addresses A ON o.ADDRESS_ID = A.ADDRESS_ID

    WHERE s.SESSION_ID = p_session_id 

      AND s.TYPE = 'REFUND'

    GROUP BY s.SESSION_ID, s.ORDER_ID, A.CLIENT_ID, o.SUB_ORDER_ID, o.ORDER_STATUS, s.TYPE;

    

    SET v_total_record = ROW_COUNT();

    

    -- --------------------------------------------------------------

    -- التحقق من وجود اوامر للمرتجعات

    -- --------------------------------------------------------------

    IF v_total_record <= 0 THEN

        SET p_status = 'error';

        SET p_message = 'فشل تسجيل أرصدة مرتجع العميل لا يوجد بيانات لتنفيذ هذا الاجراء';

        SET p_error_code = 45302;

        LEAVE proc_exit;

    END IF;

    

    -- --------------------------------------------------------------

    -- التحقق من وجود SUB_ORDER_ID للمرتجعات

    -- --------------------------------------------------------------

    SELECT COUNT(ORDER_ID)

    INTO v_total_orders_error

    FROM refund_orders

    WHERE SUB_ORDER_ID IS NULL OR SUB_ORDER_ID = '';

    IF v_total_orders_error > 0 THEN

        SET p_status = 'error';

        SET p_message = CONCAT('فشل تسجيل أرصدة مرتجع العميل','يوجد عدد (',  v_total_orders_error, ') من أوامر المرتجعات التي لا تحتوي على بيانات كاملة.');

        SET p_error_code = 45302;

        LEAVE proc_exit;

    END IF;

    -- --------------------------------------------------------------

    -- التحقق من أن قيمة المرتجع لا تتجاوز قيمة الطلب

    -- --------------------------------------------------------------

    SELECT COUNT(ORDER_ID) INTO v_total_reject_value_error FROM refund_orders WHERE TOTAL_RETURN > SUM_ORDER;

    IF v_total_reject_value_error > 0 THEN

        SET p_status = 'error';

        SET p_message = CONCAT('فشل تسجيل أرصدة مرتجع العميل','يوجد عدد (', v_total_reject_value_error, ') من أوامر المرتجعات قيمة المرتجع أكبر من قيمة الطلب.');

        SET p_error_code = 45303;

        LEAVE proc_exit;

    END IF;

    

    -- --------------------------------------------------------------

    -- تحديث أرصدة المرتجعات الحالية

    -- --------------------------------------------------------------

    UPDATE partners_balance pb

    JOIN refund_orders ro ON pb.PARTNER_ID = ro.CLIENT_ID AND pb.REFERENCE_ID = ro.ORDER_ID

    SET pb.BALANCE = ro.TOTAL_RETURN,

        pb.LAST_UPDATE = NOW(6)

    WHERE pb.PARTNER_TYPE = 'CLIENT' 

      AND pb.TRANSACTION_TYPE = 'CREDIT' 

      AND pb.TRANSACTION_CATEGORY_ID = 14

      AND ro.TOTAL_RETURN > 0;

    SET v_rowsUpdate = ROW_COUNT();

     

    -- --------------------------------------------------------------

    -- إدراج السجلات الجديدة

    -- --------------------------------------------------------------

    INSERT INTO partners_balance (

        PARTNER_TYPE, PARTNER_ID, REFERENCE_ID, TRANSACTION_TYPE, 

        BALANCE, NOTE, TRANSACTION_CATEGORY_ID, USE_OF_CREDIT

    )

    SELECT 

        'CLIENT', CLIENT_ID, ORDER_ID, 'CREDIT', 

        TOTAL_RETURN, 'رصيد دائن نتيجة إرجاع منتجات', 14, 1

    FROM refund_orders ro

    WHERE NOT EXISTS (

        SELECT 1 

        FROM partners_balance pb

        WHERE pb.PARTNER_TYPE = 'CLIENT' 

          AND pb.TRANSACTION_TYPE = 'CREDIT' 

          AND pb.TRANSACTION_CATEGORY_ID = 14

          AND pb.PARTNER_ID = ro.CLIENT_ID 

          AND pb.REFERENCE_ID = ro.ORDER_ID

    );

    

    SET v_rowsInsert = ROW_COUNT();

    DELETE pb

    FROM partners_balance pb

    JOIN refund_orders ro ON pb.PARTNER_ID = ro.CLIENT_ID 

        AND pb.REFERENCE_ID = ro.ORDER_ID

    WHERE pb.PARTNER_TYPE = 'CLIENT' 

        AND pb.TRANSACTION_TYPE = 'CREDIT' 

        AND pb.TRANSACTION_CATEGORY_ID = 14

        AND ro.TOTAL_RETURN = 0;

    

    SET v_rowsDelete = ROW_COUNT();

    -- --------------------------------------------------------------

    -- التحقق من تطابق عدد السجلات

    -- --------------------------------------------------------------

    IF v_total_record != (v_rowsInsert + v_rowsUpdate + v_rowsDelete) THEN

        SET p_status = 'error';

        SET p_message = CONCAT('فشل تحديث رصيد مرتجع العميل - عدم تطابق في عدد السجلات: متوقع ', v_total_record, ', تم معالجة ', (v_rowsInsert + v_rowsUpdate + v_rowsDelete));

        SET p_error_code = 45304;

        LEAVE proc_exit;

    END IF;

    SET p_status = 'success';

    SET p_message = CONCAT('تم تحديث أرصدة مرتجعات العملاء بنجاح: ', (v_rowsInsert + v_rowsUpdate + v_rowsDelete), ' سجل');

    SET p_error_code = 0;

END$$
DELIMITER ;