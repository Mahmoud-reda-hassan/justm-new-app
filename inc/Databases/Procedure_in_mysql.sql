--restClientDebtBatch
(
/**==============================================================
 * # الإجراء: restClientDebtBatch
 * ------------------------------------------
 * # الغرض:
 *   تحديث أو إنشاء مديونية العميل بناءً على أوامر البيع الموجودة
 *   في جلسة التأكيد (confirm_staging).
 * ------------------------------------------
 * # المعاملات:
 *   IN  p_session_id          → رقم جلسة التأكيد (Confirm Session)
 *   OUT p_return INT          → output parameter رمز الخطاء
 *   OUT p_status VARCHAR(10)  → output parameter للstatus
 *   OUT p_message TEXT        → output parameter للرسالة
 * ------------------------------------------
 * # الجداول المستخدمة:
 *   - confirm_staging
 *   - orders
 *   - order_product
 *   - addresses
 *   - partners_balance
 * ------------------------------------------
 * # المنطق العام:
 *   1. بناء جدول مؤقت (checkorders) يجمع معلومات كل طلب للـ session المحدد.
 *   2. التحقق من وجود SUB_ORDER_ID لكل أمر (خطأ إذا وجد مفقود).
 *   3. التحقق من أن قيمة المرفوضات لا تتجاوز قيمة الطلب (خطأ إذا وجدت).
 *   4. إدراج أرصدة جديدة في partners_balance إن لم تكن موجودة.
 *   5. تحديث الأرصدة الموجودة وفقًا لحالة الطلب.
 *   6. حذف رصيد العميل إذا كانت حالة الطلب ملغاة (ORDER_STATUS = 5).
 *   7. ضبط p_result وإرجاع خطأ عام إذا لم تتوافق النتائج المتوقعة.
 * ------------------------------------------
 * # رسائل الأخطاء الممكنة:
 *   - JSON يوضح الأوامر الناقصة أو التي فيها قيم مرفوضات أكبر من الطلب.
 *   - رسالة عامة: 'فشل تحديث مديونية العميل'
 * ------------------------------------------
 * # المبرمج: محمود رضا
 * # تاريخ: 13 / 11 / 2025
===============================================================*/

DROP PROCEDURE IF EXISTS restClientDebtBatch;

DELIMITER $$

CREATE PROCEDURE restClientDebtBatch(
    IN p_session_id VARCHAR(64),
    OUT p_error_code INT,
    OUT p_status VARCHAR(10),
    OUT p_message TEXT

)
proc_exit: BEGIN
    DECLARE v_rowsInsert INT DEFAULT 0;
    DECLARE v_rowsUpdate INT DEFAULT 0;
    DECLARE v_rowsDelete INT DEFAULT 0;
    DECLARE v_total_record INT DEFAULT 0;
    DECLARE v_total_orders_error INT DEFAULT 0;
    DECLARE v_total_rejact_value_error INT DEFAULT 0;
    DECLARE v_json_result JSON DEFAULT NULL;
    DECLARE v_json_rejact_value_result JSON DEFAULT NULL;
    
    -- --------------------------------------------------------------
    -- 1. التحقق من وجود سجلات لتأكيد SELSE و REFUND
    -- --------------------------------------------------------------
    IF NOT EXISTS (SELECT 1 FROM confirm_staging WHERE SESSION_ID = p_session_id) THEN
        SET p_error_code = 45200;
        SET p_status = 'error';
        SET p_message = 'لا توجد بيانات لتأكيد التسليم';
        LEAVE proc_exit;
    END IF;
    
    -- --------------------------------------------------------------
    -- جدول مؤقت يجمع بيانات كل أمر للجلسة المحددة
    -- --------------------------------------------------------------
	DROP TEMPORARY TABLE IF EXISTS checkorders;
	CREATE TEMPORARY TABLE checkorders (
	    SESSION_ID      VARCHAR(64) NOT NULL,
	    ORDER_ID        INT NOT NULL,
	    CLIENT_ID       INT NOT NULL,
	    SUB_ORDER_ID    VARCHAR(64) NOT NULL,
	    ORDER_STATUS    INT NOT NULL,
	    SUM_ORDER       DECIMAL(18,3) NOT NULL DEFAULT 0,
	    SUM_REJECTED    DECIMAL(18,3) NOT NULL DEFAULT 0,
	    SUM_DELIVERED   DECIMAL(18,3) NOT NULL DEFAULT 0,
	    SUM_RETURNED    DECIMAL(18,3) NOT NULL DEFAULT 0,
	    SHIPPING_VAL    DECIMAL(18,3) NOT NULL DEFAULT 0,
	    TYPE            ENUM('SELSE','REFUND') NOT NULL,
	    CREATED_AT      DATETIME(3) DEFAULT NOW(3),
	    PRIMARY KEY (SESSION_ID, ORDER_ID)
	) ENGINE=InnoDB;
    
    -- --------------------------------------------------------------
    -- تعبئة الجدول المؤقت ببيانات الطلبات الخاصة بالجلسة
    -- --------------------------------------------------------------
    INSERT INTO checkorders (SESSION_ID, ORDER_ID, CLIENT_ID, SUB_ORDER_ID, ORDER_STATUS, SUM_ORDER, SUM_REJECTED, SUM_DELIVERED, SUM_RETURNED, SHIPPING_VAL, TYPE)
    SELECT cs.SESSION_ID, 
           cs.ORDER_ID, 
           a.CLIENT_ID, 
           o.SUB_ORDER_ID, 
           o.ORDER_STATUS, 
           
           COALESCE(
                SUM((op.PRICE - op.DISCOUNT) * op.QTY), 0
            ) AS SUM_ORDER, 
           
           COALESCE(
                SUM(
                    CASE WHEN o.ORDER_STATUS IN (4, 5) THEN (op.PRICE - op.DISCOUNT) * (op.QTY - COALESCE(op.DELIVERED_QTY, 0)) 
                        ELSE 0 
                    END
                ), 0
            ) AS SUM_REJECTED, 
           
           COALESCE(
                SUM(
                    CASE WHEN o.ORDER_STATUS IN (3, 4) THEN (op.PRICE - op.DISCOUNT) * COALESCE(op.DELIVERED_QTY, 0) 
                         ELSE 0 
                    END
                    ), 0
                ) AS SUM_DELIVERED, 
           
           COALESCE(
                SUM(
                    CASE WHEN o.ORDER_STATUS > 5 THEN (op.PRICE - op.DISCOUNT) * COALESCE(op.RETURNED_QTY, 0) 
                         ELSE 0 
                    END
                ), 0
            ) AS SUM_RETURNED, 
            
           COALESCE(
                o.SHIPPING_VAL, 0
            ) AS SHIPPING_VAL, 
            
            cs.TYPE
    FROM (SELECT SESSION_ID, ORDER_ID, TYPE FROM confirm_staging WHERE TYPE = 'SELSE' AND SESSION_ID = p_session_id GROUP BY SESSION_ID, ORDER_ID, TYPE) cs LEFT JOIN ORDERS O ON cs.ORDER_ID = o.ORDER_ID LEFT JOIN order_product op ON o.ORDER_ID = op.ORDER_ID LEFT JOIN addresses a ON o.ADDRESS_ID = a.ADDRESS_ID
    GROUP BY cs.SESSION_ID, cs.ORDER_ID, a.CLIENT_ID, o.SUB_ORDER_ID, o.ORDER_STATUS, o.SHIPPING_VAL, cs.TYPE;
     
    -- --------------------------------------------------------------
    -- عدد السجلات المجمعة (الأوامر من نوع SELSE ضمن الجلسة)
    -- --------------------------------------------------------------
    SELECT COUNT(*) INTO v_total_record FROM checkorders WHERE SESSION_ID = p_session_id AND TYPE = 'SELSE' AND (ORDER_STATUS != 5 OR (ORDER_STATUS = 5 AND SHIPPING_VAL > 0));	
    
    -- --------------------------------------------------------------
    -- التحقق من وجود SUB_ORDER_ID لكل أمر (خطأ إن كان مفقود)
    -- --------------------------------------------------------------
    SELECT COUNT(ORDER_ID), JSON_OBJECT('status','error', 'title','خطاء', 'message', CONCAT('يوجد عدد (', COUNT(ORDER_ID), ') من أوامر البيع التي لا تحتوي على بيانات فرعية.'), 'orders', JSON_ARRAYAGG(SUB_ORDER_ID)) INTO v_total_orders_error, v_json_result FROM checkorders WHERE SESSION_ID = p_session_id AND TYPE = 'SELSE' AND (SUB_ORDER_ID IS NULL OR SUB_ORDER_ID = '');
    IF v_total_orders_error > 0 THEN
        SET p_status = 'error';
        SET p_message = CAST(v_json_result AS CHAR);
        SET p_error_code = 45201;
        LEAVE proc_exit;
    END IF;

    -- --------------------------------------------------------------
    -- التحقق من أن قيمة المرفوضات لا تتجاوز قيمة الطلب
    -- --------------------------------------------------------------
    SELECT COUNT(ORDER_ID), JSON_OBJECT( 'status','error', 'title','خطاء', 'message', CONCAT('يوجد عدد (', COUNT(ORDER_ID), ') من أوامر البيع قيمة المرفوض اكبر من قيمة الطلب.'), 'orders', JSON_ARRAYAGG(SUB_ORDER_ID)) INTO v_total_rejact_value_error, v_json_rejact_value_result FROM checkorders WHERE SESSION_ID = p_session_id AND TYPE = 'SELSE' AND SUM_ORDER < SUM_REJECTED;
    IF v_total_rejact_value_error > 0 THEN 
        SET p_status = 'error';
        SET p_message = CAST(v_json_rejact_value_result AS CHAR);
        SET p_error_code = 45202;
        LEAVE proc_exit;
    END IF;
    
    -- --------------------------------------------------------------
    -- تحديث أرصدة العملاء الموجودة - الإصدار المعدل
    -- --------------------------------------------------------------
    UPDATE partners_balance pb JOIN (
    SELECT ORDER_ID, CLIENT_ID, SUM_ORDER, ORDER_STATUS, SUM_REJECTED, SUM_RETURNED , SUM_DELIVERED, SHIPPING_VAL
    FROM checkorders 
    WHERE SESSION_ID = p_session_id AND TYPE = 'SELSE' AND (ORDER_STATUS IN (2,3,4) OR (ORDER_STATUS = 5 AND SHIPPING_VAL > 0))) AS src ON pb.PARTNER_ID = src.CLIENT_ID AND pb.REFERENCE_ID = src.ORDER_ID
    SET pb.BALANCE = CASE WHEN src.ORDER_STATUS = 4 THEN src.SUM_DELIVERED + src.SHIPPING_VAL 
                          WHEN src.ORDER_STATUS = 5 AND src.SHIPPING_VAL > 0 THEN src.SHIPPING_VAL 
                          ELSE src.SUM_ORDER + src.SHIPPING_VAL END, 
        pb.LAST_UPDATE = NOW(6)
    WHERE pb.REFERENCE_ID = src.ORDER_ID AND pb.PARTNER_TYPE = 'CLIENT' AND pb.TRANSACTION_TYPE = 'DEBIT' AND pb.TRANSACTION_CATEGORY_ID = 8 AND CASE WHEN src.ORDER_STATUS = 4 THEN src.SUM_DELIVERED + src.SHIPPING_VAL WHEN src.ORDER_STATUS = 5 AND src.SHIPPING_VAL > 0 THEN src.SHIPPING_VAL ELSE src.SUM_ORDER + src.SHIPPING_VAL END > 0;
        
    SET v_rowsUpdate = ROW_COUNT();
    
    -- --------------------------------------------------------------
    -- إدراج أرصدة جديدة إن لم توجد
    -- --------------------------------------------------------------
    INSERT INTO partners_balance (
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
    SELECT 'CLIENT', 
    		CLIENT_ID, 
    		ORDER_ID, 
    		CASE WHEN ORDER_STATUS IN(0,1,2,3,4,5) THEN 'DEBIT',  
                 WHEN ORDER_STATUS IN(6,7) THEN 'CREDIT' 
            END,
    		CASE WHEN ORDER_STATUS IN(0,1,2) THEN SUM_ORDER
    		     WHEN ORDER_STATUS IN(3,4) THEN SUM_DELIVERED 
                 WHEN ORDER_STATUS IN(6,7) THEN SUM_RETURNED 
                 ELSE 0
            END, 
    		'مديونية عميل ناتجة عن أمر بيع',  
    		8, 
			0, 
    	    NOW(6) 
    FROM checkorders 
    WHERE SESSION_ID = p_session_id 
     
      AND (
            ORDER_STATUS IN (2,3,4) 
            OR (
                 ORDER_STATUS = 5 
                 AND SHIPPING_VAL > 0
               )
           ) 
      AND NOT EXISTS (
            SELECT 1 FROM partners_balance pb 
            WHERE pb.PARTNER_TYPE = 'CLIENT' 
              AND pb.TRANSACTION_TYPE = 'DEBIT' 
              AND pb.TRANSACTION_CATEGORY_ID = 8 
              AND pb.PARTNER_ID = checkorders.CLIENT_ID 
              AND pb.REFERENCE_ID = checkorders.ORDER_ID
      );    
    
    SET v_rowsInsert =  ROW_COUNT();

    -- --------------------------------------------------------------
    -- حذف الأرصدة إذا كانت حالة الطلب مرفوض (ORDER_STATUS = 5)
    -- مع التحقق من عدم وجود رصيد موجب
    -- --------------------------------------------------------------
    DELETE pb
    FROM partners_balance pb JOIN (SELECT ORDER_ID, CLIENT_ID FROM checkorders WHERE SESSION_ID = p_session_id AND TYPE = 'SELSE' AND ORDER_STATUS = 5 AND SHIPPING_VAL <= 0) AS tmp ON pb.PARTNER_TYPE = 'CLIENT' AND pb.TRANSACTION_TYPE = 'DEBIT' AND pb.TRANSACTION_CATEGORY_ID = 8 AND pb.PARTNER_ID = tmp.CLIENT_ID AND pb.REFERENCE_ID = tmp.ORDER_ID
    WHERE pb.BALANCE <= 0;    
    
    SET v_rowsDelete = ROW_COUNT();
    
    -- --------------------------------------------------------------
    -- مقارنة النتيجة الكلية مع العمليات التي تمت
    -- --------------------------------------------------------------
    IF v_total_record = (v_rowsInsert + v_rowsUpdate + v_rowsDelete) THEN
        SET p_status = 'success';
        SET p_message = 'تم تحديث مديونية العميل بنجاح';
        SET p_error_code = 0;
    ELSE
        SET p_status = 'error';
        SET p_message = CONCAT('فشل تحديث مديونية العميل - عدم تطابق في عدد السجلات: متوقع ', v_total_record , ', تم إدخال ', (v_rowsInsert + v_rowsUpdate + v_rowsDelete));
        SET p_error_code = 45203;
        LEAVE proc_exit;
    END IF;

END$$

DELIMITER ;

)
--reduceClientCreditBatch
(/**==============================================================
 * # الإجراء: reduceClientCreditBatch
 * ------------------------------------------
 * # الغرض:
 *   تحديث أو إنشاء أو حذف رصيد العميل (دائن) بناءً على المرتجعات بعد البيع.
 * ------------------------------------------
 * # المعاملات:
 *    IN  p_order_id  → رقم أمر البيع
 *   OUT p_return INT,         → output parameter رمز الخطاء
 *   OUT p_status VARCHAR(10), → output parameter للstatus
 *   OUT p_message TEXT        → output parameter للرسالة
 * ------------------------------------------
 * # الجداول المستخدمة:
 *     - orders
 *     - order_product
 *     - partners_balance
 * ------------------------------------------
 * # المنطق:
 *     1. جلب بيانات الطلب والرصيد الحالي:
 *         - رقم العميل، حالة الطلب، نجاح الطلب، رصيد العميل قبل التحصيل،
 *           الرصيد المدفوع، الرصيد القديم للمرتجعات، قيمة المرتجع الحالي.
 *    2. التحقق من وجود بيانات المرتجع:
 *         - إذا لم ينجح الطلب أو قيمة المرتجع أكبر من المدفوع أو صفر → إطلاق خطأ.
 *    3. معالجة سيناريوهات المرتجعات:
 *         - لا يوجد رصيد سابق + يوجد مرتجع → إدراج سجل جديد.
 *         - يوجد رصيد سابق + يوجد مرتجع → تحديث السجل.
 *         - يوجد رصيد سابق + لا يوجد مرتجع → حذف السجل.
 *    4. التحقق النهائي من النتيجة:
 *         - إذا لم يتم تعديل أي سجل → إطلاق خطأ.
 *    5. ضبط p_result بعد التنفيذ.
 * ------------------------------------------
 * # رسائل الأخطاء:
 *     - "❌ لا يمكن تنفيذ الاجراء لهاذا الامر..."
 *     - "❌ قيمة المرتجع اكبر من المدفوع..."
 *     - "❌ قيمة المرتجع لابد ان تكون اكبر من صفر"
 *     - "نأسف لا يمكن تحديث رصيد مرتجع بعد البيع ..."
 * ------------------------------------------
 * # المبرمج: محمود رضا
 * ------------------------------------------
 * # تاريخ: 09 / 11 / 2025
===============================================================*/

DROP PROCEDURE IF EXISTS reduceClientCreditBatch;

DELIMITER $$

CREATE PROCEDURE reduceClientCreditBatch(
    IN p_session_id VARCHAR(64),
    OUT p_error_code INT,
    OUT p_status VARCHAR(10),
    OUT p_message TEXT
)
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
        SESSION_ID, 
        ORDER_ID, 
        CLIENT_ID, 
        SUB_ORDER_ID, 
        ORDER_STATUS,
        TOTAL_RETURN, 
        SUM_ORDER, 
        TYPE
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
        PARTNER_TYPE, 
        PARTNER_ID, 
        REFERENCE_ID, 
        TRANSACTION_TYPE, 
        BALANCE, 
        NOTE, 
        TRANSACTION_CATEGORY_ID, 
        USE_OF_CREDIT
    )
    SELECT 
        'CLIENT', 
        CLIENT_ID, 
        ORDER_ID, 
        'CREDIT', 
        TOTAL_RETURN, 
        'رصيد دائن نتيجة إرجاع منتجات', 
        14,
        1
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
	
	-- --------------------------------------------------------------
    -- حذف السجلات عند الغاء المرتجع
    -- --------------------------------------------------------------
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
)

--shipping_movement
(
/*===============================================================
 * # الإجراء: shipping_movement
 * ------------------------------------------
 * # الغرض:
 *   تأكيد عمليات التسليم من خلال إدخال سجلات في جدول حركات الشحن
 *   والتحقق من صحة البيانات قبل التنفيذ
 * ------------------------------------------
 * # المعاملات:
 *   IN  p_session_id          → معرف الجلسة (SESSION_ID)
 *   IN  p_executed_by         → رقم المستخدم المنفذ للعملية
 *   OUT p_return INT,         → output parameter رمز الخطاء
 *   OUT p_status VARCHAR(10), → output parameter للstatus
 *   OUT p_message TEXT        → output parameter للرسالة
 * ------------------------------------------
 * # المنطق:
 *   1 - حساب عدد الأوامر الفريدة في الجلسة
 *   2 - التحقق من عدم وجود قيم NULL في movement_type قبل التنفيذ
 *   3 - إطلاق استثناء إذا وجدت أوامر ستنتج قيم NULL
 *   4 - إدخال سجلات حركات الشحن في shipping_movement
 *   5 - التحقق من تطابق عدد الأوامر المحسوبة مع المسجلة
 *   6 - إرجاع عدد السجلات المدخلة
 * ------------------------------------------
 * # الجداول المستخدمة:
 *   ## 1 confirm_staging
 *      - جدول مؤقت لتخزين نتائج جلسة التأكيد قبل التنفيذ الفعلي
 *      - يحتوي على معلومات المنتجات المسلّمة أو المرجعة
 *
 *      CREATE TEMPORARY TABLE IF NOT EXISTS confirm_staging (
 *          SESSION_ID   VARCHAR(64) NOT NULL,   -- رقم الجلسة (مفتاح رئيسي)
 *          ORDER_ID     INT NOT NULL,           -- رقم الطلب
 *          PRO_CODE     VARCHAR(100) NOT NULL,  -- كود المنتج
 *          USER_ID      INT NOT NULL,           -- رقم المستخدم (مندوب التسليم)
 *          APP_QTY      DECIMAL(18,3) NOT NULL, -- الكمية المؤكدة
 *          STATUSE      VARCHAR(50) NOT NULL,   -- حالة المنتج بعد التأكيد
 *          TYPE         ENUM('SELSE','REFUND') NOT NULL, -- نوع العملية
 *          CREATED_AT   DATETIME(3) DEFAULT NOW(3),
 *          PRIMARY KEY (SESSION_ID, ORDER_ID, PRO_CODE, USER_ID)
 *      );
 *
 *   ## 2 orders
 *      - جدول الطلبات الرئيسية وحالتها الإجمالية
 *
 *   ## 3 custody_orders
 *      - جدول عهدة أوامر البيع للمندوبين
 *
 *   ## 4 custody_return_orders
 *      - جدول عهدة أوامر المرتجعات من العملاء
 *
 *   ## 5 shipping_movement
 *      - جدول حركات الشحن والتسليم
 * ------------------------------------------
 * # شروط تحديد movement_type:
 *   ## لأوامر البيع (SELSE):
 *      - 0 → عندما تكون CUST_STAT = 'WAITING' و ORDER_STATUSE = 2
 *      - 1 → عندما تكون CUST_STAT = 'FREEZE'
 *      - ORDER_STATUS → عندما تكون CUST_STAT = 'DELIVERED' و ORDER_STATUSE IN (3,4,5)
 *
 *   ## لأوامر المرتجع (REFUND):
 *      - 10 → عندما تكون ORDER_STATUSE IN (3,4,5)
 *      - 8  → عندما تكون ORDER_STATUSE = 6
 *      - 9  → عندما تكون ORDER_STATUSE = 7
 * ------------------------------------------
 * # حالات الخطأ:
 *   - SQLSTATE '45000' → إذا وجدت أوامر ستنتج NULL في movement_type
 *   - SQLSTATE '45000' → إذا حدث عدم تطابق في عدد السجلات المدخلة
 * ------------------------------------------
 * # المخرجات:
 *   - p_return → عدد السجلات المدخلة بنجاح في shipping_movement
 * ------------------------------------------
 * # المبرمج: [محمود رضا]
 * # تاريخ التعديل: [15/11/2025]
 * ------------------------------------------
 * # ملاحظات:
 *   - يجب أن تكون بيانات confirm_staging جاهزة قبل استدعاء الإجراء
 *   - يتم التحقق من صحة البيانات قبل التنفيذ الفعلي
 *   - الإجراء يضمن تكامل البيانات من خلال فحص القيم NULL
 *   - يمكن تحسين الأداء بإضافة فهارس على الجداول المشاركة
===============================================================*/
    
DROP PROCEDURE IF EXISTS shipping_movement;

DELIMITER $$

CREATE PROCEDURE shipping_movement(
    IN p_session_id VARCHAR(64),
    IN p_executed_by INT,
    OUT p_error_code INT,
    OUT p_status VARCHAR(10),
    OUT p_message TEXT
)
proc_exit: BEGIN
    DECLARE v_total_orders INT DEFAULT 0;
    DECLARE v_total_product INT DEFAULT 0;
    DECLARE v_null_count INT DEFAULT 0;
    DECLARE v_inserted_rows INT DEFAULT 0;

    -- --------------------------------------------------------------
    -- 1. التحقق من وجود سجلات لتأكيد SELSE و REFUND
    -- --------------------------------------------------------------
    IF NOT EXISTS (SELECT 1 FROM confirm_staging WHERE SESSION_ID = p_session_id) THEN
        SET p_status = 'error';
        SET p_message = 'لا توجد بيانات لتأكيد التسليم';
        SET p_error_code = 45111;
        LEAVE proc_exit;
    END IF;

    -- عد الأوامر الفريدة مع معالجة الأخطاء
    SELECT COUNT(DISTINCT ORDER_ID) INTO v_total_orders
    FROM confirm_staging
    WHERE SESSION_ID = p_session_id;
    
    IF v_total_orders = 0 THEN
        SET p_status = 'error';
        SET p_message = 'لا توجد أوامر في الجلسة المحددة';
        SET p_error_code = 45112;
        LEAVE proc_exit;
    END IF;

    -- التحقق من وجود قيم NULL في movement_type مع استعلام محسن
    SELECT COUNT(*) INTO v_null_count 
    FROM confirm_staging cs
    LEFT JOIN orders o ON o.ORDER_ID = cs.ORDER_ID
    LEFT JOIN order_product op ON o.ORDER_ID = op.ORDER_ID
    LEFT JOIN custody_orders co ON o.ORDER_ID = co.ORDER_ID AND co.USER_ID = cs.USER_ID
    LEFT JOIN custody_return_orders cro ON o.ORDER_ID = cro.ORDER_ID AND cro.USER_ID = cs.USER_ID
    WHERE cs.SESSION_ID = p_session_id
      AND  cs.TYPE NOT IN('SHIPPING','FREEZE','DEFREEZE','SELSE','REFUND')
      OR  (cs.TYPE = 'SHIPPING' AND co.CUST_STAT IS NOT NULL AND co.CUST_STAT =  'FREEZE')       
      OR  (cs.TYPE = 'FREEZE'   AND co.CUST_STAT IS NOT NULL AND co.CUST_STAT <> 'WAITING')       
      OR  (cs.TYPE = 'DEFREEZE' AND co.CUST_STAT IS NOT NULL AND co.CUST_STAT <> 'FREEZE')       
      OR  (cs.TYPE = 'SELSE'    AND co.CUST_STAT IS NULL)              -- لو مرفوض من التسليم وغير مسجل بعهدة المندوب
      OR  (cs.TYPE = 'REFUND'   AND cro.ORDER_STATUSE IS NULL)         -- لو مرتجع بعد البيع وغير مسجل بعهدة المندوب
      OR  (cs.TYPE = 'FREEZE'   AND co.CUST_STAT IS NULL))             -- لو استرداد من مندوب وغير مسجل بعهدة المندوب
        -- (cs.TYPE = 'SHIPPING' AND co.ORDER_STATUS IS NOT NULL AND co.CUST_STAT = 'FREEZE') OR     -- لو هيتصرف للمندوب وهو بالفعل مسجل كعهده لمندوب اخر او نفس المندوب
        -- الحالات التي تنتج NULL بناء على المنطق الحالي
        (cs.TYPE = 'SHIPPING' AND co.CUST_STAT = 'WAITING' AND co.ORDER_STATUSE != 2) OR
        (cs.TYPE = 'SELSE' AND co.CUST_STAT = 'DELIVERED' AND co.ORDER_STATUSE NOT IN (3,4,5)) OR
        (cs.TYPE = 'FREEZE' AND (co.CUST_STAT != 'WAITING' || op.ORDER_STATUS != 2)) OR              -- لو استرداد من مندوب وغير مسجل بعهدة المندوب
        (cs.TYPE = 'REFUND' AND cro.ORDER_STATUSE NOT IN (3,4,5,6,7))
    );

    -- إرسال استثناء إذا وجدت قيم NULL
    IF v_null_count > 0 THEN
       	SET p_status = 'error';
        SET p_message = CONCAT('يوجد ', v_null_count, ' طلب ستنتج قيم غير صالحه في سجلات حركة الشحن');
        SET p_error_code = 45113;
        LEAVE proc_exit;
    END IF;
    
    -- 0 = start Shipping                       = SHIPPING
    -- 1 = freeze                               = FREEZE
    -- 2 = Returned From Freeze                 = DEFREEZE
    -- 3 = Delivered                            =
    -- 4 = Partial                              =
    -- 5 = Rejected                             =
    -- 6 = Returned From Rejected or Partial    =
    -- 7 = تم السداد
    -- 8 = REFUND From Sell
    -- 9 = REFUND partial from sell
    
    -- 10= رفض العميل تسليم المرتجعات
    -- 11= تسويه ماليه للمرتجع
    -- إدخال البيانات
    INSERT INTO shipping_movement(movement_type, delivery_id, user_id, shipment_orders)
    SELECT DISTINCT
        CASE 
            WHEN cs.TYPE = 'SHIPPING' THEN 0
            WHEN cs.TYPE = 'FREEZE' THEN 1
            WHEN cs.TYPE = 'DEFREEZE' THEN 2
            WHEN cs.TYPE = 'SELSE' AND co.CUST_STAT = 'DELIVERED' AND co.ORDER_STATUSE IN (3,4,5) THEN o.ORDER_STATUS
            WHEN cs.TYPE = 'REFUND' AND cro.ORDER_STATUSE IN (3,4,5) THEN 10
            WHEN cs.TYPE = 'REFUND' AND cro.ORDER_STATUSE = 6 THEN 8
            WHEN cs.TYPE = 'REFUND' AND cro.ORDER_STATUSE = 7 THEN 9
            ELSE NULL
        END AS movement_type,
        cs.USER_ID AS delivery_id,
        p_executed_by AS user_id,
        o.ORDER_ID AS shipment_orders
    FROM (
        SELECT DISTINCT SESSION_ID, ORDER_ID, USER_ID, TYPE 
        FROM confirm_staging 
        WHERE SESSION_ID = p_session_id  -- ✅ التصحيح هنا
    ) cs
    LEFT JOIN orders o ON o.ORDER_ID = cs.ORDER_ID
    LEFT JOIN custody_orders co ON o.ORDER_ID = co.ORDER_ID AND co.USER_ID = cs.USER_ID
    LEFT JOIN custody_return_orders cro ON o.ORDER_ID = cro.ORDER_ID AND cro.USER_ID = cs.USER_ID
    WHERE o.ORDER_ID IS NOT NULL;
    
    SET v_inserted_rows = ROW_COUNT();
    
    -- التحقق من تطابق عدد السجلات
    IF v_total_orders != v_inserted_rows THEN
        SET p_status = 'error';
        SET p_message = CONCAT('خطاء فى اضافة حركة الشحن - عدم تطابق في عدد السجلات: متوقع ', v_total_orders, ', تم إدخال ', v_inserted_rows);
        SET p_error_code = 45114;
        LEAVE proc_exit;
    END IF;

    SET p_status = 'success';
    SET p_message = 'تم اضافة حركة الشحن بنجاح';
    SET p_error_code = 0;


END$$

DELIMITER ;
)



DROP PROCEDURE IF EXISTS st_shipping_movement;

DELIMITER $$

CREATE PROCEDURE st_shipping_movement(
    IN p_session_id VARCHAR(64),
    IN p_executed_by INT,
    
)
BEGIN
	DECLARE v_error_code VARCHAR(100);
    DECLARE v_status VARCHAR(50);
    DECLARE v_message TEXT ;
    
	CALL PROCEDURE shipping_movement(p_session_id, p_executed_by, v_error_code, v_status, v_message);
    
    SELECT v_status AS statuse, v_message AS message, v_error_code AS error_code;

END$$

DELIMITER ;


DROP PROCEDURE IF EXISTS set_orders_log;

DELIMITER $$

CREATE PROCEDURE set_orders_log( -- غيرت اسم الإجراء ليتطابق مع DROP
    IN p_session_id VARCHAR(64),
    IN p_executed_by INT,
    IN p_executed_group VARCHAR(250),
    OUT p_error_code INT,
    OUT p_status VARCHAR(10),
    OUT p_message TEXT

)
proc_exit: BEGIN
    DECLARE v_rows INT DEFAULT 0;
    DECLARE v_total_orders INT DEFAULT 0;
    DECLARE v_logtext VARCHAR(250) DEFAULT CONCAT(' تم إجراء تحديث على بيانات أمر البيع من قِبل : ', p_executed_group);
    
    -- التحقق من وجود سجلات في confirm_staging
    IF NOT EXISTS (SELECT 1 FROM confirm_staging WHERE SESSION_ID = p_session_id) THEN
        SET p_status = 'error';
        SET p_message = 'لا توجد بيانات لتأكيد التسليم';
        SET p_error_code = 45111;
        LEAVE proc_exit;
    END IF;
    
    -- عد الأوامر الفريدة
    SELECT COUNT(DISTINCT ORDER_ID) INTO v_total_orders
    FROM confirm_staging
    WHERE SESSION_ID = p_session_id;
    
    IF v_total_orders = 0 THEN
        SET p_status = 'error';
        SET p_message = 'لا توجد أوامر في الجلسة المحددة';
        SET p_error_code = 45112;
        LEAVE proc_exit;
    END IF;
    
    -- إدخال سجلات السجل
    INSERT INTO orders_log (`ORDER_ID`, `USER_ACTION`, `LOG_TEXT`, `DATE_ACTION`) -- أضفت DATE_ACTION
    SELECT 
        o.ORDER_ID,
        p_executed_by AS USER_ACTION,
        CASE 
            WHEN cs.TYPE = 'SELSE' AND co.CUST_STAT = 'WAITING' AND co.ORDER_STATUSE = 2 THEN 
                CONCAT(v_logtext, '* تم تسليم أمر البيع إلى المندوب ', CONCAT(us.FORENAME, ' ', us.LASTNAME))
            WHEN cs.TYPE = 'SELSE' AND co.CUST_STAT = 'FREEZE' THEN 
                CONCAT(v_logtext, '* تم تجميد أمر البيع على المندوب: ', CONCAT(us.FORENAME, ' ', us.LASTNAME))
            WHEN cs.TYPE = 'SELSE' AND co.CUST_STAT = 'DELIVERED' AND co.ORDER_STATUSE IN (3,4,5) THEN 
                CONCAT(v_logtext, '* تم الشحن - حالة أمر البيع حالياً: ', COALESCE(ost.STA_NAME, 'غير معروف'))
            WHEN cs.TYPE = 'REFUND' AND cro.ORDER_STATUSE IN (3,4,5) THEN 
                CONCAT(v_logtext, '* رفض العميل تسليم المرتجعات - حالة أمر البيع حالياً: ', COALESCE(osta.STA_NAME, 'غير معروف'))
            WHEN cs.TYPE = 'REFUND' AND cro.ORDER_STATUSE = 6 THEN 
                CONCAT(v_logtext, '* تم استلام المرتجعات - حالة أمر البيع حالياً: ', COALESCE(osta.STA_NAME, 'غير معروف'))
            WHEN cs.TYPE = 'REFUND' AND cro.ORDER_STATUSE = 7 THEN 
                CONCAT(v_logtext, '* تم استلام المرتجعات - حالة أمر البيع حالياً: ', COALESCE(osta.STA_NAME, 'غير معروف'))
            ELSE NULL
        END AS LOG_TEXT,
        NOW(6) AS DATE_ACTION -- أضفت التاريخ الحالي
    FROM  (SELECT SESSION_ID, ORDER_ID, USER_ID, TYPE FROM confirm_staging GROUP BY SESSION_ID,ORDER_ID,USER_ID) cs
    INNER JOIN orders o ON o.ORDER_ID = cs.ORDER_ID
    LEFT JOIN custody_orders co ON o.ORDER_ID = co.ORDER_ID AND co.USER_ID = cs.USER_ID
    LEFT JOIN custody_return_orders cro ON o.ORDER_ID = cro.ORDER_ID AND cro.USER_ID = cs.USER_ID
    LEFT JOIN users us ON cs.USER_ID = us.USER_ID
    LEFT JOIN order_statuse ost ON co.ORDER_STATUSE = ost.STA_ID
    LEFT JOIN order_statuse osta ON cro.ORDER_STATUSE = osta.STA_ID
    WHERE cs.SESSION_ID = p_session_id;
    
    SET v_rows = ROW_COUNT();
    
    -- التحقق من تطابق عدد السجلات
    IF v_total_orders != v_rows THEN
        SET p_status = 'error';
        SET p_message = CONCAT('عدم تطابق في عدد السجلات: متوقع ', v_total_orders, ', تم إدخال ', v_rows, ' - غير قادر على اضافة سجلات اوامر البيع');
        SET p_error_code = 45113;
        LEAVE proc_exit;
    END IF;
    
    SET p_status = 'success';
    SET p_message = 'تم اضافة سجلات امر البيع بنجاح';
    SET p_error_code = 0;


END$$

DELIMITER ;




/*===============================================================
 * # الإجراء: setDeliveryConfirmationOptimized
 * ------------------------------------------
 * # الغرض:
 *   تأكيد تسليم أوامر البيع (SELSE) وأوامر المرتجعات (REFUND)
 *   دفعة واحدة من جدول confirm_staging، مع تحديث الجداول المرتبطة
 *   بالعهدة والأرصدة.
 * ------------------------------------------
 * # المعاملات:
 *   IN  p_session_id         → رقم جلسة التأكيد (Confirm Session)
 *   IN  p_executed_by        → رقم المستخدم المنفذ
 *   IN  p_selse_last_update  → تاريخ آخر تحديث لأمر البيع للتحقق من التزامن
 * ------------------------------------------
 * # المنطق:
 *   1 - التحقق من وجود سجلات في confirm_staging للجلسة.
 *   2 - تحديث order_product استنادًا إلى SESSION_ID:
 *         - SELSE → تحديث DELIVERED_QTY و PRO_STATUSE.
 *         - REFUND → تحديث RETURNED_QTY و PRO_STATUSE.
 *   3 - تحديث orders بناءً على مجاميع order_product للأوامر المتأثرة.
 *   4 - تحديث custody_product و custody_order للسجلات من نوع SELSE.
 *   5 - تحديث custody_return_product و custody_return_order للسجلات من نوع REFUND.
 *   6 - تحديث أرصدة العملاء دفعة واحدة عبر:
 *         - restClientDebtBatch → مديونية العملاء.
 *         - reduceClientCreditBatch → الرصيد الدائن للمرتجعات.
 *   7 - اضافة  حركات الشحن دفعه واحده:
 *         - shipping_movement → اضافة حركات الشحن لكل اوامر البيع 
 *   8 - النتيجة النهائية.
 * ------------------------------------------
 * # الجداول المستخدمة:
 *   - confirm_staging
 *   - order_product
 *   - orders
 *   - custody_product
 *   - custody_order
 *   - custody_return_product
 *   - custody_return_order
 * ------------------------------------------
 * # المبرمج: محمود رضا
 * # التاريخ: 2025-11-15
 * ------------------------------------------
 * # ملاحظات:
 *   - النسخة هذه محسنة للتعامل مع أي عدد أوامر دفعة واحدة.
 *   - مناسبة لمعالجة آلاف الأوامر في جلسة واحدة.
 *   - إدارة الأخطاء تتم عبر SIGNAL SQLSTATE مع رسالة واضحة.
===============================================================*/
DROP PROCEDURE IF EXISTS setDeliveryConfirmationOptimized;

DELIMITER $$

CREATE PROCEDURE setDeliveryConfirmationOptimized(
    IN p_session_id VARCHAR(64),
    IN p_executed_by INT,
    IN p_executed_group VARCHAR(250),
    IN p_branch_id INT,
    IN p_update_inventory VARCHAR(10)
)
proc_exit: BEGIN
    DECLARE v_status VARCHAR(10) DEFAULT 'ERROR';
    DECLARE v_total_orders INT DEFAULT 0;
    DECLARE v_total_products INT DEFAULT 0;
    DECLARE v_total_orders_selse INT DEFAULT 0;
    DECLARE v_total_products_selse INT DEFAULT 0;
    DECLARE v_total_orders_refund INT DEFAULT 0;
    DECLARE v_total_products_refund INT DEFAULT 0;
    DECLARE v_message TEXT DEFAULT NULL;
    DECLARE v_rows INT DEFAULT 0;
    DECLARE v_order_logs INT DEFAULT 0;
    DECLARE v_logtext VARCHAR(250) DEFAULT CONCAT(' تم إجراء تحديث على بيانات أمر البيع من قِبل : ', p_executed_group);
    DECLARE v_store_id INT DEFAULT NULL;
    DECLARE v_inv_result_a INT DEFAULT 0;
    DECLARE v_inv_result_b INT DEFAULT 0;

    -- --------------------------------------------------------------
    -- التحقق من وجود سجلات لتأكيد SELSE و REFUND
    -- --------------------------------------------------------------
    IF NOT EXISTS (SELECT 1 FROM confirm_staging WHERE SESSION_ID = p_session_id) THEN
        SET v_message = 'لا توجد بيانات لتأكيد التسليم';
        SELECT 'error' AS status, v_message AS message, 45100 AS error_code;
        LEAVE proc_exit;
    END IF;
    
    -- --------------------------------------------------------------
    -- جلب عدد الاوامر والمنتجات
    -- --------------------------------------------------------------
    SELECT 
        COUNT(DISTINCT ORDER_ID) AS total_orders,
        COUNT(DISTINCT CASE WHEN TYPE = 'SELSE' THEN ORDER_ID END) AS selse_orders,
        COUNT(DISTINCT CASE WHEN TYPE = 'REFUND' THEN ORDER_ID END) AS refund_orders,
        COUNT(*) AS total_products,
        COUNT(CASE WHEN TYPE = 'SELSE' THEN 1 END) AS selse_products,
        COUNT(CASE WHEN TYPE = 'REFUND' THEN 1 END) AS refund_products
    INTO
        v_total_orders,
        v_total_orders_selse,
        v_total_orders_refund,
        v_total_products,
        v_total_products_selse,
        v_total_products_refund
    FROM confirm_staging
    WHERE SESSION_ID = p_session_id;
    
    SELECT STO_ID INTO v_store_id FROM STORES WHERE STTY_ID=2 AND BRANCH_ID = p_branch_id;
    
    -- --------------------------------------------------------------
    -- عد الاوامر الفريده لـ معالجة الأخطاء
    -- --------------------------------------------------------------
    IF v_total_orders = 0 THEN
        SET v_message = 'لا توجد بيانات لتأكيد التسليم';
        SELECT 'error' AS status, v_message AS message, 45101 AS error_code;
        LEAVE proc_exit;
    END IF;

    -- --------------------------------------------------------------
    -- 2. تحديث order_product بناءً على confirm_staging
    -- --------------------------------------------------------------
    UPDATE order_product op
    JOIN confirm_staging s 
        ON op.ORDER_ID = s.ORDER_ID AND op.PRO_CODE = s.PRO_CODE
    SET 
        op.DELIVERED_QTY = CASE WHEN s.TYPE = 'SELSE' THEN s.APP_QTY ELSE op.DELIVERED_QTY END,
        op.RETURNED_QTY  = CASE WHEN s.TYPE = 'REFUND' THEN s.APP_QTY ELSE op.RETURNED_QTY END,
        op.PRO_STATUSE   = CASE 
                            WHEN s.TYPE = 'REFUND' AND s.APP_QTY > 0 AND op.DELIVERED_QTY > s.APP_QTY THEN 'partial_refund'
                            WHEN s.TYPE = 'REFUND' AND s.APP_QTY > 0 AND op.DELIVERED_QTY = s.APP_QTY THEN 'refund'
                            WHEN s.TYPE = 'SELSE'  AND s.APP_QTY > 0 AND s.APP_QTY < op.QTY THEN 'partial'
                            WHEN s.TYPE = 'SELSE'  AND s.APP_QTY = 0 THEN 'rejected'
                            WHEN s.TYPE = 'SELSE'  AND s.APP_QTY = op.QTY THEN 'complete'
                            ELSE op.PRO_STATUSE
                          END,
        op.LAST_UPDATE = NOW(3)
    WHERE s.SESSION_ID = p_session_id;

    SET v_rows = ROW_COUNT();

    IF v_total_products != v_rows THEN
        SET v_message = CONCAT('لم يتم تحديث أي منتجات. المتوقع: ', v_total_products, ', المحدث: ', v_rows);
        SELECT 'error' AS status, v_message AS message, 45102 AS error_code;
        LEAVE proc_exit;
    END IF;

    -- --------------------------------------------------------------
    -- 3. تحديث orders بناءً على مجاميع order_product
    -- --------------------------------------------------------------
    UPDATE orders o
    JOIN (
        SELECT ORDER_ID,
               CASE 
                 WHEN SUM(RETURNED_QTY) > 0 AND SUM(DELIVERED_QTY) > SUM(RETURNED_QTY) THEN 7
                 WHEN SUM(RETURNED_QTY) > 0 AND SUM(DELIVERED_QTY) = SUM(RETURNED_QTY) THEN 6
                 WHEN SUM(RETURNED_QTY) = 0 AND SUM(DELIVERED_QTY) = 0 THEN 5
                 WHEN SUM(RETURNED_QTY) = 0 AND SUM(DELIVERED_QTY) < SUM(QTY) AND SUM(DELIVERED_QTY) > 0 THEN 4
                 WHEN SUM(RETURNED_QTY) = 0 AND SUM(DELIVERED_QTY) = SUM(QTY) THEN 3
                 ELSE NULL
               END AS NEW_ORDER_STATUS
        FROM order_product
        WHERE ORDER_ID IN (SELECT DISTINCT ORDER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id)
        GROUP BY ORDER_ID
    ) AS agg ON o.ORDER_ID = agg.ORDER_ID
    SET o.ORDER_STATUS = agg.NEW_ORDER_STATUS,
        o.LAST_UPDATE = NOW(3),
        o.DELIVERED_DATE = CASE WHEN agg.NEW_ORDER_STATUS IN (3,4,6,7) THEN NOW(3) ELSE o.DELIVERED_DATE END;
    
    SET v_rows = ROW_COUNT();
    
    IF v_total_orders != v_rows THEN
        SET v_message = CONCAT('لم يتم تحديث أي امر بيع. المتوقع: ', v_total_orders, ', المحدث: ', v_rows);
        SELECT 'error' AS status, v_message AS message, 45103 AS error_code;
        LEAVE proc_exit;
    END IF;
    
    -- --------------------------------------------------------------
    -- 4. تحديث custody_product و custody_order للـ SELSE
    -- --------------------------------------------------------------
    IF v_total_orders_selse > 0 THEN
        UPDATE custody_product cp
        JOIN order_product op 
            ON cp.ORDER_ID = op.ORDER_ID AND cp.PRO_CODE = op.PRO_CODE
        JOIN confirm_staging s 
            ON s.ORDER_ID = op.ORDER_ID AND s.PRO_CODE = op.PRO_CODE AND s.TYPE = 'SELSE'
        SET cp.DELIVERED_QTY = op.DELIVERED_QTY,
            cp.PRO_STATUSE   = op.PRO_STATUSE,
            cp.LAST_UPDATE   = NOW(3)
        WHERE s.SESSION_ID = p_session_id;
        
        SET v_rows = ROW_COUNT();
        
        IF v_total_products_selse != v_rows THEN
            SET v_message = CONCAT('❌ لم يتم تحديث منتجات عهدة المندوب. المتوقع: ', v_total_products_selse, ', المحدث: ', v_rows);
            SELECT 'error' AS status, v_message AS message, 45104 AS error_code;
            LEAVE proc_exit;
        END IF;
        
        UPDATE custody_orders co
        JOIN orders o ON co.ORDER_ID = o.ORDER_ID
        JOIN (SELECT DISTINCT ORDER_ID, USER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id AND TYPE = 'SELSE') s
            ON co.ORDER_ID = s.ORDER_ID AND co.USER_ID = s.USER_ID
        SET co.CUST_STAT = 'DELIVERED',
            co.ORDER_STATUSE = o.ORDER_STATUS,
            co.LAST_UPDATE = NOW(3);
        
        SET v_rows = ROW_COUNT();
        
        IF v_total_orders_selse != v_rows THEN
            SET v_message = CONCAT('❌ لم يتم تحديث أي امر بيع من عهدة المندوب. المتوقع: ', v_total_orders_selse, ', المحدث: ', v_rows);
            SELECT 'error' AS status, v_message AS message, 45105 AS error_code;
            LEAVE proc_exit;
        END IF;
    END IF;
    
    -- --------------------------------------------------------------
    -- 5. تحديث custody_return_product و custody_return_order للـ REFUND
    -- --------------------------------------------------------------
    IF v_total_orders_refund > 0 THEN
        UPDATE custody_return_product crp
        JOIN order_product op 
            ON crp.ORDER_ID = op.ORDER_ID AND crp.PRO_CODE = op.PRO_CODE
        JOIN confirm_staging s 
            ON s.ORDER_ID = op.ORDER_ID AND s.PRO_CODE = op.PRO_CODE AND s.TYPE = 'REFUND'
        SET crp.RETURNED_QTY = op.RETURNED_QTY,
            crp.PRO_STATUSE = op.PRO_STATUSE,
            crp.LAST_UPDATE = NOW(3)
        WHERE s.SESSION_ID = p_session_id;
    
        SET v_rows = ROW_COUNT();

        IF v_total_products_refund != v_rows THEN
            SET v_message = CONCAT('❌ لم يتم تحديث منتجات مرتجع عهدة المندوب. المتوقع: ', v_total_products_refund, ', المحدث: ', v_rows);
            SELECT 'error' AS status, v_message AS message, 45106 AS error_code;
            LEAVE proc_exit;
        END IF;

        UPDATE custody_return_orders cro
        JOIN orders o ON cro.ORDER_ID = o.ORDER_ID
        JOIN (SELECT DISTINCT ORDER_ID, USER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id AND TYPE = 'REFUND') s
            ON cro.ORDER_ID = s.ORDER_ID AND cro.USER_ID = s.USER_ID
        SET cro.CUST_STAT = 'RETURNED',
            cro.ORDER_STATUSE = o.ORDER_STATUS,
            cro.LAST_UPDATE = NOW(3);
    
        SET v_rows = ROW_COUNT();

        IF v_total_orders_refund != v_rows THEN
            SET v_message = CONCAT('❌ لم يتم تحديث أي امر مرتجع من عهدة المندوب. المتوقع: ', v_total_orders_refund, ', المحدث: ', v_rows);
            SELECT 'error' AS status, v_message AS message, 45107 AS error_code;
            LEAVE proc_exit;
        END IF;
        
    END IF;
    
    -- --------------------------------------------------------------
    -- 6. تحديث أرصدة العملاء دفعة واحدة
    -- --------------------------------------------------------------
    IF v_total_orders_selse > 0 THEN
        CALL restClientDebtBatch(p_session_id, @error_code, @status, @message);
        
        IF @status = 'error' THEN
            SELECT @status AS status, @message AS message, @error_code AS error_code;
            LEAVE proc_exit;
        END IF;
    END IF;
    
    IF v_total_orders_refund > 0 THEN
        CALL reduceClientCreditBatch(p_session_id, @error_code, @status, @message);
        IF @status = 'error' THEN
            SELECT @status AS status, @message AS message, @error_code AS error_code;
            LEAVE proc_exit;
        END IF;
    END IF;
    
    -- --------------------------------------------------------------
    -- 7. اضافة حركة الشحن دفعه واحده
    -- --------------------------------------------------------------
    CALL shipping_movement(p_session_id, p_executed_by, @error_code, @status, @message);
    
    IF @status = 'error' THEN
        SELECT @status AS status, @message AS message, @error_code AS error_code;
        LEAVE proc_exit;
    END IF;
    
    -- --------------------------------------------------------------
    -- 8. اضافة حركة log دفعه واحده
    -- --------------------------------------------------------------
    CALL set_orders_log(p_session_id, p_executed_by, p_executed_group, @error_code, @status, @message);
    IF @status = 'error' THEN
        SELECT @status AS status, @message AS message, @error_code AS error_code;
        LEAVE proc_exit;
    END IF;
    
    -- --------------------------------------------------------------
    -- 9. تحديث مخزون المنتجات 
    -- --------------------------------------------------------------
    IF p_update_inventory = 'true' THEN
        CALL inventory_movement_sales_order(p_session_id, 'RETURN_SHIPP_SALSE', p_executed_by, v_store_id, v_inv_result_a);
        
        IF v_inv_result_a <= 0  THEN
            SELECT 'error' AS Status, 'فشل فى تحديث المخزون' AS message, 45450 AS error_code;
            LEAVE proc_exit;
        END IF;
        
        UPDATE custody_orders SET RETURNS = 1 WHERE ORDER_ID IN(SELECT DISTINCT ORDER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id AND TYPE = 'SELSE');
        
        IF ROW_COUNT() <= 0  THEN
            SELECT 'error' AS Status, 'فشل فى تحديث استلام مرفوضات عهدة المندوب' AS message, 45450 AS error_code;
            LEAVE proc_exit;
        END IF;
        
        IF v_total_orders_refund > 0 THEN
            UPDATE custody_return_orders SET RETURNS = 1 WHERE ORDER_ID IN(SELECT DISTINCT ORDER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id AND TYPE = 'REFUND');
        	IF ROW_COUNT() <= 0  THEN
                SELECT 'error' AS Status, 'فشل فى تحديث استلام مرتجعات عهدة المندوب' AS message, 45450 AS error_code;
                LEAVE proc_exit;
            END IF;
        END IF;
        
        
    END IF;

    -- --------------------------------------------------------------
    -- 10. النتيجة النهائية
    -- --------------------------------------------------------------
    SELECT 'success' AS status, 
           CONCAT('تم تأكيد تسليم الشحنة ', v_total_orders, ' أمر بنجاح (', 
                  v_total_orders_selse, ' مبيعات, ', 
                  v_total_orders_refund, ' مرتجعات)') AS message;
           
END$$

DELIMITER ;













DROP PROCEDURE IF EXISTS setDeliveryConfirmationOptimized;

DELIMITER $$

CREATE PROCEDURE setDeliveryConfirmationOptimized(
    IN p_session_id VARCHAR(64),
    IN p_executed_by INT,
    IN p_executed_group VARCHAR(250),
    IN p_branch_id INT ,
    IN p_update_inventory BOOLEAN  FALSE 
)
proc_exit: BEGIN
    DECLARE v_status VARCHAR(10) DEFAULT 'ERROR';
    DECLARE v_total_orders INT DEFAULT 0;
    DECLARE v_total_products INT DEFAULT 0;
    DECLARE v_total_orders_selse INT DEFAULT 0;
    DECLARE v_total_products_selse INT DEFAULT 0;
    DECLARE v_total_orders_refund INT DEFAULT 0;
    DECLARE v_total_products_refund INT DEFAULT 0;
    DECLARE v_message TEXT DEFAULT NULL;
    DECLARE v_rows INT DEFAULT 0;
    DECLARE v_order_logs INT DEFAULT 0; -- أضفت هذا المتغير المفقود
    DECLARE v_logtext VARCHAR(250) DEFAULT CONCAT(' تم إجراء تحديث على بيانات أمر البيع من قِبل : ', p_executed_group);
    DECLARE v_store_id INT DEFAULT NULL;
    -- --------------------------------------------------------------
    -- التحقق من وجود سجلات لتأكيد SELSE و REFUND
    -- --------------------------------------------------------------
    IF NOT EXISTS (SELECT 1 FROM confirm_staging WHERE SESSION_ID = p_session_id) THEN
        SET v_message = 'لا توجد بيانات لتأكيد التسليم';
        SELECT 'error' AS status, v_message AS message, 45100 AS error_code;
        LEAVE proc_exit;
    END IF;
    
    -- --------------------------------------------------------------
    -- جلب عدد الاوامر والمنتجات
    -- --------------------------------------------------------------
    SELECT 
        COUNT(DISTINCT ORDER_ID) AS total_orders,
        COUNT(DISTINCT CASE WHEN TYPE = 'SELSE' THEN ORDER_ID END) AS selse_orders,
        COUNT(DISTINCT CASE WHEN TYPE = 'REFUND' THEN ORDER_ID END) AS refund_orders,
        COUNT(*) AS total_products,
        COUNT(CASE WHEN TYPE = 'SELSE' THEN 1 END) AS selse_products,
        COUNT(CASE WHEN TYPE = 'REFUND' THEN 1 END) AS refund_products
    INTO
        v_total_orders,
        v_total_orders_selse,
        v_total_orders_refund,
        v_total_products,
        v_total_products_selse,
        v_total_products_refund
    FROM confirm_staging
    WHERE SESSION_ID = p_session_id;
    
    SELECT STO_ID INTO v_store_id FROM STORES WHERE STTY_ID=2 AND BRANCH_ID = p_branch_id ;
    -- --------------------------------------------------------------
    -- عد الاوامر الفريده لـ معالجة الأخطاء
    -- --------------------------------------------------------------
    IF v_total_orders = 0 THEN
        SET v_message = 'لا توجد بيانات لتأكيد التسليم';
        SELECT 'error' AS status, v_message AS message, 45101 AS error_code;
        LEAVE proc_exit;
    END IF;

    -- --------------------------------------------------------------
    -- 2. تحديث order_product بناءً على confirm_staging
    -- --------------------------------------------------------------
    UPDATE order_product op
    JOIN confirm_staging s 
        ON op.ORDER_ID = s.ORDER_ID AND op.PRO_CODE = s.PRO_CODE
    SET 
        op.DELIVERED_QTY = CASE WHEN s.TYPE = 'SELSE' THEN s.APP_QTY ELSE op.DELIVERED_QTY END,
        op.RETURNED_QTY  = CASE WHEN s.TYPE = 'REFUND' THEN s.APP_QTY ELSE op.RETURNED_QTY END,
        op.PRO_STATUSE   = CASE 
                            WHEN s.TYPE = 'REFUND' AND s.APP_Qty > 0 AND op.DELIVERED_QTY > s.APP_QTY THEN 'partial_refund'
                            WHEN s.TYPE = 'REFUND' AND s.APP_QTY > 0 AND op.DELIVERED_QTY = s.APP_QTY THEN 'refund'
                            WHEN s.TYPE = 'SELSE'  AND s.APP_QTY > 0 AND s.APP_QTY < op.QTY THEN 'partial'
                            WHEN s.TYPE = 'SELSE'  AND s.APP_QTY = 0 THEN 'rejected'
                            WHEN s.TYPE = 'SELSE'  AND s.APP_QTY = op.QTY THEN 'complete'
                            ELSE op.PRO_STATUSE
                          END,
        op.LAST_UPDATE = NOW(3)
    WHERE s.SESSION_ID = p_session_id;

    SET v_rows = ROW_COUNT();

    IF v_total_products != v_rows THEN
        SET v_message = CONCAT('لم يتم تحديث أي منتجات. المتوقع: ', v_total_products, ', المحدث: ', v_rows);
        SELECT 'error' AS status, v_message AS message, 45102 AS error_code;
        LEAVE proc_exit;
    END IF;

    -- --------------------------------------------------------------
    -- 3. تحديث orders بناءً على مجاميع order_product
    -- --------------------------------------------------------------
    UPDATE orders o
    JOIN (
        SELECT ORDER_ID,
               CASE 
                 WHEN SUM(RETURNED_QTY) > 0 AND SUM(DELIVERED_QTY) > SUM(RETURNED_QTY) THEN 7
                 WHEN SUM(RETURNED_QTY) > 0 AND SUM(DELIVERED_QTY) = SUM(RETURNED_QTY) THEN 6
                 WHEN SUM(RETURNED_QTY) = 0 AND SUM(DELIVERED_QTY) = 0 THEN 5
                 WHEN SUM(RETURNED_QTY) = 0 AND SUM(DELIVERED_QTY) < SUM(QTY) AND SUM(DELIVERED_QTY) > 0 THEN 4
                 WHEN SUM(RETURNED_QTY) = 0 AND SUM(DELIVERED_QTY) = SUM(QTY) THEN 3
                 ELSE NULL
               END AS NEW_ORDER_STATUS
        FROM order_product
        WHERE ORDER_ID IN (SELECT DISTINCT ORDER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id)
        GROUP BY ORDER_ID
    ) AS agg ON o.ORDER_ID = agg.ORDER_ID
    SET o.ORDER_STATUS = agg.NEW_ORDER_STATUS,
        o.LAST_UPDATE = NOW(3),
        o.DELIVERED_DATE = CASE WHEN agg.NEW_ORDER_STATUS IN (3,4,6,7) THEN NOW(3) ELSE o.DELIVERED_DATE END;
    
    SET v_rows = ROW_COUNT();
    
    IF v_total_orders != v_rows THEN
        SET v_message = CONCAT('لم يتم تحديث أي امر بيع. المتوقع: ', v_total_orders, ', المحدث: ', v_rows);
        SELECT 'error' AS status, v_message AS message, 45103 AS error_code;
        LEAVE proc_exit;
    END IF;
    
    -- --------------------------------------------------------------
    -- 4. تحديث custody_product و custody_order للـ SELSE
    -- --------------------------------------------------------------
    IF v_total_orders_selse > 0 THEN
        UPDATE custody_product cp
        JOIN order_product op 
            ON cp.ORDER_ID = op.ORDER_ID AND cp.PRO_CODE = op.PRO_CODE
        JOIN confirm_staging s 
            ON s.ORDER_ID = op.ORDER_ID AND s.PRO_CODE = op.PRO_CODE AND s.TYPE = 'SELSE'
        SET cp.DELIVERED_QTY = op.DELIVERED_QTY,
            cp.PRO_STATUSE   = op.PRO_STATUSE,
            cp.LAST_UPDATE   = NOW(3)
        WHERE s.SESSION_ID = p_session_id;
        
        SET v_rows = ROW_COUNT();
        
        IF v_total_products_selse != v_rows THEN
            SET v_message = CONCAT('❌ لم يتم تحديث منتجات عهدة المندوب. المتوقع: ', v_total_products_selse, ', المحدث: ', v_rows);
            SELECT 'error' AS status, v_message AS message, 45104 AS error_code;
            LEAVE proc_exit;
        END IF;
        
        UPDATE custody_orders co
        JOIN orders o ON co.ORDER_ID = o.ORDER_ID
        JOIN (SELECT DISTINCT ORDER_ID, USER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id AND TYPE = 'SELSE') s
            ON co.ORDER_ID = s.ORDER_ID AND co.USER_ID = s.USER_ID
        SET co.CUST_STAT = 'DELIVERED',
            co.ORDER_STATUSE = o.ORDER_STATUS,
            co.LAST_UPDATE = NOW(3);
        
        SET v_rows = ROW_COUNT();
        
        IF v_total_orders_selse != v_rows THEN
            SET v_message = CONCAT('❌ لم يتم تحديث أي امر بيع من عهدة المندوب. المتوقع: ', v_total_orders_selse, ', المحدث: ', v_rows);
            SELECT 'error' AS status, v_message AS message, 45105 AS error_code;
            LEAVE proc_exit;
        END IF;
    END IF;
    
    -- --------------------------------------------------------------
    -- 5. تحديث custody_return_product و custody_return_order للـ REFUND
    -- --------------------------------------------------------------
    IF v_total_orders_refund > 0 THEN
        UPDATE custody_return_product crp
        JOIN order_product op 
            ON crp.ORDER_ID = op.ORDER_ID AND crp.PRO_CODE = op.PRO_CODE
        JOIN confirm_staging s 
            ON s.ORDER_ID = op.ORDER_ID AND s.PRO_CODE = op.PRO_CODE AND s.TYPE = 'REFUND'
        SET crp.RETURNED_QTY = op.RETURNED_QTY, -- غيرت DELIVERED_QTY إلى RETURNED_QTY
            crp.PRO_STATUSE = op.PRO_STATUSE,
            crp.LAST_UPDATE = NOW(3)
        WHERE s.SESSION_ID = p_session_id;
    
        SET v_rows = ROW_COUNT();

        IF v_total_products_refund != v_rows THEN
            SET v_message = CONCAT('❌ لم يتم تحديث منتجات مرتجع عهدة المندوب. المتوقع: ', v_total_products_refund, ', المحدث: ', v_rows);
            SELECT 'error' AS status, v_message AS message, 45106 AS error_code;
            LEAVE proc_exit;
        END IF;

        UPDATE custody_return_orders cro
        JOIN orders o ON cro.ORDER_ID = o.ORDER_ID
        JOIN (SELECT DISTINCT ORDER_ID, USER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id AND TYPE = 'REFUND') s
            ON cro.ORDER_ID = s.ORDER_ID AND cro.USER_ID = s.USER_ID
        SET cro.CUST_STAT = 'RETURNED',
            cro.ORDER_STATUSE = o.ORDER_STATUS,
            cro.LAST_UPDATE = NOW(3);
    
        SET v_rows = ROW_COUNT();

        IF v_total_orders_refund != v_rows THEN
            SET v_message = CONCAT('❌ لم يتم تحديث أي امر مرتجع من عهدة المندوب. المتوقع: ', v_total_orders_refund, ', المحدث: ', v_rows);
            SELECT 'error' AS status, v_message AS message, 45107 AS error_code;
            LEAVE proc_exit;
        END IF;
        
    END IF;
    
    -- --------------------------------------------------------------
    -- 6. تحديث أرصدة العملاء دفعة واحدة
    -- --------------------------------------------------------------
    IF v_total_orders_selse > 0 THEN
        CALL restClientDebtBatch(p_session_id, @error_code, @status, @message);
        
        IF @status = 'error' THEN
            SELECT @status AS status, @message AS message, @error_code AS error_code;
            LEAVE proc_exit;
        END IF;
    END IF;
    
    IF v_total_orders_refund > 0 THEN
        CALL reduceClientCreditBatch(p_session_id, @error_code, @status, @message);
        IF @status = 'error' THEN
            SELECT @status AS status, @message AS message, @error_code AS error_code;
            LEAVE proc_exit;
        END IF;

    END IF;
    
    -- --------------------------------------------------------------
    -- 7. اضافة حركة الشحن دفعه واحده
    -- --------------------------------------------------------------
    CALL shipping_movement(p_session_id, p_executed_by, @error_code, @status, @message);
    
    IF @status = 'error' THEN
        SELECT @status AS status, @message AS message, @error_code AS error_code;
        LEAVE proc_exit;
    END IF;
    
    -- --------------------------------------------------------------
    -- 8. اضافة حركة log دفعه واحده
    -- --------------------------------------------------------------
    CALL set_orders_log(p_session_id, p_executed_by, p_executed_group, @error_code, @status, @message);
    IF @status = 'error' THEN
        SELECT @status AS status, @message AS message, @error_code AS error_code;
        LEAVE proc_exit;
    END IF;
    
    -- --------------------------------------------------------------
    -- 9. تحديث مخزون المنتجات 
    -- --------------------------------------------------------------
    IF p_update_inventory = TRUE THEN
        CALL inventory_movement_sales_order(p_session_id, 'RETURN_SHIPP', p_executed_by, v_store_id INT, @inv_result_a);
        IF v_total_orders_refund > 0 THEN 
            CALL inventory_movement_sales_order(p_session_id, 'RETURN_SALES', p_executed_by, v_store_id INT, @inv_result_b);
        END IF;
    	IF @inv_result_a <= 0 OR @inv_result_b <= 0 THEN
            SELECT 'error' AS Status, 'فشل فى تحديث المخزون' AS message, 45450 AS error_code;
            LEAVE proc_exit;
        END IF;
    END IF;
    
    -- --------------------------------------------------------------
    -- 9. تحديث مخزون المنتجات 
    -- --------------------------------------------------------------
    IF p_update_inventory = true THEN
        CALL inventory_movement_sales_order(p_session_id, 'RETURN_SHIPP', p_executed_by, v_store_id INT, @inv_result_a);
        IF v_total_orders_refund > 0 THEN 
            CALL inventory_movement_sales_order(p_session_id, 'RETURN_SALES', p_executed_by, v_store_id INT, @inv_result_b);
        END IF;
        IF @inv_result_a <= 0 OR @inv_result_b <= 0 THEN
            SELECT 'error' AS Status, 'فشل فى تحديث المخزون' AS message, 45450 AS error_code;
            LEAVE proc_exit;
        END IF;
        UPDATE custody_orders SET RETURNS = 1 WHERE ORDER_ID IN(SELECT DISTINCT ORDER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id AND TYPE = 'SELSE');
        IF ROW_COUNT() <= 0  THEN
            SELECT 'error' AS Status, 'فشل فى تحديث استلام مرفوضات عهدة المندوب' AS message, 45450 AS error_code;
            LEAVE proc_exit;
        END IF;
        IF v_total_orders_refund > 0 THEN
            UPDATE custody_return_orders SET RETURNS = 1 WHERE ORDER_ID IN(SELECT DISTINCT ORDER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id AND TYPE = 'REFUND');
            IF ROW_COUNT() <= 0  THEN
                SELECT 'error' AS Status, 'فشل فى تحديث استلام مرتجعات عهدة المندوب' AS message, 45450 AS error_code;
                LEAVE proc_exit;
            END IF;
        END IF;
    END IF;
    -- --------------------------------------------------------------
    -- 10. النتيجة النهائية
    -- --------------------------------------------------------------
    SELECT 'success' AS status, 
           CONCAT('تم تأكيد تسليم الشحنه ', v_total_orders, ' أمر بنجاح (', 
                  v_total_orders_selse, ' مبيعات, ', 
                  v_total_orders_refund, ' مرتجعات)') AS message;
           
END$$

DELIMITER ;


 
/* CREATE TEMPORARY TABLE IF NOT EXISTS confirm_staging (
    SESSION_ID   	VARCHAR(64) NOT NULL,
    ORDER_ID     	INT NOT NULL,
    PRO_CODE     	VARCHAR(100) NOT NULL,
    USER_ID      	INT NOT NULL,        -- delivery id (المندوب)
    APP_QTY      	DECIMAL(18,3) NOT NULL,
    STATUSE   		VARCHAR(50) NOT NULL, -- PRO_STATUSE value المحسوب
    TYPE     		ENUM('SELSE','REFUND') NOT NULL,
    CREATED_AT   	DATETIME(3) DEFAULT NOW(3),
    PRIMARY KEY (SESSION_ID, ORDER_ID, PRO_CODE, USER_ID)
); */

/*
* @Params INT p_order_id	  => رقم امر البيع 
* @Params INT p_partner 	  => id العميل او المورد
* @Params INT p_delivery_id   => id موظف الشحن
* @Params INT P_fromsto 	  => id المخزن اللى هيتم عليه العمليه
* @Params INT p_branch_id	  => id الفرع التابع له موظف المخزن لاستخراج id مخزن الفرع
* @Params INT p_user_action   => id موظف المخزن
* @Params STR p_movement_type => 
*	'refund'		= استرداد منتجات امر البيع من المندوب
*	'return_shipp'	= استلام المنتجات المرفوضه بأمر البيع من المندوب
*	'return_sales'	= استلام مرتجعات بعد البيع من المندوب
*	'shipping'		= صرف منتجات امر البيع للمندوب


DELIMITER $$

CREATE PROCEDURE up_custody_returns (
    IN p_order_id INT,
    IN p_partner INT,
    IN p_delivery_id INT,
    IN p_branch_id INT,
    IN P_fromsto INT,
    IN p_user_action INT,
    IN p_movement_type VARCHAR(30)
)
BEGIN
    -- متغيرات محلية
    DECLARE v_type VARCHAR(10) DEFAULT NULL;
    -- DECLARE v_type_dist VARCHAR(10) DEFAULT NULL;
    DECLARE v_mov_str VARCHAR(255) DEFAULT NULL;
    DECLARE v_fromsto INT DEFAULT NULL;
    DECLARE v_details JSON DEFAULT NULL;
    DECLARE v_last_id BIGINT DEFAULT NULL;
    -- DECLARE v_exchange_order INT DEFAULT NULL;

    DECLARE v_update_rows INT DEFAULT 0;
    
    DECLARE v_message VARCHAR(255);

    -- لإدارة الأخطاء والـ ROLLBACK
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        -- نعيد رسالة عامة بخطأ قابل للقراءة
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'خطأ أثناء تنفيذ الإجراء. تم التراجع عن التغييرات.';
    END;

    -- الحصول على مخزن المصدر (مثال: نوع مخزن STTY_ID = 2)
    IF p_tosto IS NOT NULL THEN
    	set v_fromsto = p_fromsto
    ELSEIF 
	    SELECT STO_ID INTO v_fromsto
	    FROM STORES
	    WHERE STTY_ID = 2 AND BRANCH_ID = p_branch_id
	    LIMIT 1;
	END IF;
	
    IF v_fromsto IS NULL THEN
        SET v_message = CONCAT('❌ لا يوجد مخزن مصدر معرف للفرع رقم ', p_branch_id);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;

    -- تحقق من نوع الحركة وحدد المتغيرات المناسبة
    IF p_movement_type IS NULL OR p_movement_type = '' THEN
        SET v_message = CONCAT('❌ لم يتم تحديد نوع الحركة للحالة الطلب رقم ', p_order_id);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;

	IF p_movement_type = 'refund' THEN
        SET v_type = 'IN';
        SET v_mov_str = 'استرداد منتجات امر البيع من المندوب';
    ELSEIF p_movement_type = 'return_shipp' THEN
        SET v_type = 'IN';
        SET v_mov_str = 'استلام المنتجات المرفوضه بأمر البيع من المندوب';
    ELSEIF p_movement_type = 'return_sales' THEN
        SET v_type = 'IN';
        SET v_mov_str = 'استلام مرتجعات بعد البيع من المندوب';
    ELSEIF p_movement_type = 'shipping' THEN
        SET v_type = 'OUT';
        SET v_mov_str = 'صرف منتجات امر البيع للمندوب';
    ELSE
        SET v_message = CONCAT('❌ نوع الحركة غير معروف: ', p_movement_type);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;

    -- تفاصيل الحركة كـ JSON
    SET v_details = JSON_OBJECT('note', v_mov_str, 'partner', p_partner, 'order_id', p_order_id, 'exchange', v_exchange_order);

    -- سجل حركة المخزون
    INSERT INTO inventory_movement
        (
            IM_MOVE_TYPE, 
	        IM_USER, 
	        IM_BRANCH, 
	        STO_ID, 
	        STO_DEST, 
	        REFERENCE, 
	        IM_DETAILS, 
	        IM_APPROVAL_DISBURSED, 
	        IM_APPROVAL_RECEIVED
        ) VALUES (
	        p_movement_type, 
	        p_user_action, 
	        p_branch_id, 
	        v_fromsto, 
	        NULL, 
	        p_order_id, 
	        JSON_OBJECT('note', v_mov_str, 'partner', p_partner, 'order_id', p_order_id), 
	        1, 
	        1
		);
		
	SET v_last_id = LAST_INSERT_ID();
    IF v_last_id = 0 OR v_last_id IS NULL THEN
        SET v_message = '❌ غير قادر على تسجيل حركة المخزون';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;
    
    -- سجل منتجات الحركة: INSERT ... SELECT
    INSERT INTO inventory_product_movement
        (
        IM_ID, 
        IPM_TYPE_MOV, 
        IPM_CODE, 
        IPM_STO_QTY, 
        IPM_QTY, 
        CREATE_AT
        )
    SELECT
	    v_last_id AS IM_ID,
	    CASE 
	        WHEN p_movement_type IN ('refund','return_shipp','return_sales') THEN 'IN' 
	        WHEN p_movement_type = 'shipping' THEN 'OUT' 
	        ELSE NULL 
	    END AS IPM_TYPE_MOV,
	    op.PRO_CODE AS IPM_CODE,
	    COALESCE(sp.STO_QTY, 0) AS IPM_STO_QTY,
	    CASE 
	        WHEN p_movement_type IN ('shipping','refund') THEN op.QTY  
	        WHEN p_movement_type = 'return_shipp' THEN (op.QTY - IFNULL(op.DELIVERED_QTY,0))  
	        WHEN p_movement_type = 'return_sales' THEN IFNULL(op.RETURNED_QTY,0)
	    END AS IPM_QTY,
	    NOW(6) AS CREATE_AT
	FROM order_product op
	LEFT JOIN stores_product sp ON op.PRO_CODE = sp.PRO_CODE AND sp.STO_ID = v_fromsto
	WHERE op.ORDER_ID = p_order_id;

    SET v_update_rows = ROW_COUNT();
    IF v_update_rows = 0 THEN
        SET v_message = '❌ غير قادر على تسجيل منتجات حركة المخزون';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;
	SET v_update_rows = 0;
	
    -- تحديث مخزون المخزن من مجموع حركات المنتجات الجديدة
    UPDATE stores_product STP
    JOIN (
        SELECT IM.STO_ID,
               IPM.IPM_CODE,
               SUM(
                   CASE
                       WHEN IPM.IPM_TYPE_MOV = 'IN' THEN IPM.IPM_QTY
                       WHEN IPM.IPM_TYPE_MOV = 'OUT' THEN -IPM.IPM_QTY
                       ELSE 0
                   END
               ) AS TQTY,
               MAX(IPM.CREATE_AT) as LAST_IPM
        FROM inventory_product_movement IPM
        JOIN inventory_movement IM ON IPM.IM_ID = IM.IM_ID
        GROUP BY IM.STO_ID, IPM.IPM_CODE
    ) AS VSP ON STP.STO_ID = VSP.STO_ID AND STP.PRO_CODE = VSP.IPM_CODE
    SET STP.STO_QTY = STP.STO_QTY + VSP.TQTY,
        STP.LAST_UPDATE = NOW(6)
    WHERE VSP.TQTY IS NOT NULL;

    SET v_stores_product = ROW_COUNT();

    IF v_stores_product = 0 THEN
        SET v_message = '❌ غير قادر على تحديث جداول المخزون (stores_product)';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;

    -- إنهاء بنجاح: يمكنك إرجاع ID أو رسالة عبر SELECT
    SELECT v_last_id AS inventory_movement_id, v_inventory_product_movement AS products_count;

END$$

DELIMITER ;*/

/*=============insert shipping_movement================
        0 = start Shipping
        1 = freeze
        2 = Returned From Freeze
        3 = Delivered
        4 = Partial
        5 = Rejected ,
        6 = Returned From Rejected or Partial
        7 = تم السداد
        8 = Retutn From Sell
        9 = Return partial from sell
        10= رفض العميل تسليم المرتجعات
=============insert shipping_movement================*/

/*===============================================================
 * # الإجراء: setDeliveryConfirmation
 * ------------------------------------------
 * # الغرض:
 *   تأكيد تسليم أوامر البيع (SELSE) وأوامر المرتجعات (REFUND)
 *   بناءً على جلسة تأكيد معينة، مع تحديث الجداول المرتبطة
 *   بالأوامر والعهدة والأرصدة الخاصة بالعملاء.
 * ------------------------------------------
 * # المعاملات:
 *   IN  p_session_id         → رقم جلسة التأكيد (Confirm Session)
 *   IN  p_executed_by        → رقم المستخدم المنفذ
 *   IN  p_selse_last_update  → تاريخ آخر تحديث لأمر البيع للتحقق من التزامن
 * ------------------------------------------
 * # المنطق:
 *   1 - التحقق من صلاحية الجلسة وأن الطلب غير مجمّد (FREEZE).
 *   2 - تحديث تفاصيل المنتجات (order_product) من confirm_staging.
 *   3 - تحديث حالة الطلب (orders) بناءً على المجاميع المحسوبة.
 *   4 - تحديث عهدة المندوب (custody_order / custody_product).
 *   5 - تحديث مديونية العميل عبر restClientDebt.
 *   6 - في حال وجود مرتجع REFUND:
 *         - تحديث custody_return_order و custody_return_product.
 *         - تقليل مديونية العميل عبر reduceClientCredit.
 *   7 - التحقق من النتائج النهائية لضمان نجاح العملية.
 * ------------------------------------------
 * # الجداول المستخدمة:
 *   ## 1 confirm_staging
 *      - جدول مؤقت لتخزين نتائج جلسة التأكيد قبل التنفيذ الفعلي.
 *      - يحتوي على معلومات المنتجات المسلّمة أو المرجعة.
 *
 *      CREATE TEMPORARY TABLE IF NOT EXISTS confirm_staging (
 *          SESSION_ID   VARCHAR(64) NOT NULL,   -- رقم الجلسة (مفتاح رئيسي)
 *          ORDER_ID     INT NOT NULL,           -- رقم الطلب
 *          PRO_CODE     VARCHAR(100) NOT NULL,  -- كود المنتج
 *          USER_ID      INT NOT NULL,           -- رقم المستخدم (مندوب التسليم)
 *          APP_QTY      DECIMAL(18,3) NOT NULL, -- الكمية المؤكدة
 *          STATUSE      VARCHAR(50) NOT NULL,   -- حالة المنتج بعد التأكيد
 *          TYPE         ENUM('SELSE','REFUND') NOT NULL, -- نوع العملية
 *          CREATED_AT   DATETIME(3) DEFAULT NOW(3),
 *          PRIMARY KEY (SESSION_ID, ORDER_ID, PRO_CODE, USER_ID)
 *      );
 *
 *   ## 2 orders
 *      - الطلبات الرئيسية وحالتها الإجمالية.
 *
 *   ## 3 order_product
 *      - تفاصيل المنتجات داخل الطلب.
 *
 *   ## 4 custody_order / custody_product
 *      - حركة المنتجات في العهدة أثناء التسليم.
 *
 *   ## 5 custody_return_order / custody_return_product
 *      - حركة المرتجعات من العملاء.
 *
 *   ## 6 partners_balance
 *      - أرصدة وديون العملاء.
 * ------------------------------------------
 * # الإجراءات الفرعية المستدعاة:
 *   - restClientDebt(p_selse_order_id, v_client_balance)
 *   - reduceClientCredit(p_refund_order_id, v_client_return_balance)
 * ------------------------------------------
 * # المخرجات:
 *   - SELECT نهائي يعرض رسالة النجاح وعدد الصفوف المحدثة.
 * ------------------------------------------
 * # المبرمج: محمود رضا
 * # التاريخ: 2025-11-11
 * ------------------------------------------
 * # ملاحظات:
 *   - يجب أن تكون بيانات confirm_staging جاهزة قبل التنفيذ.
 *   - يُوصى باستخدام معاملات (TRANSACTION) لحماية تكامل البيانات.
 *   - الجدول المؤقت يتم إنشاؤه تلقائيًا إن لم يكن موجودًا.
===============================================================*/

/*DROP PROCEDURE IF EXISTS setDeliveryConfirmation;

DELIMITER $$

CREATE PROCEDURE setDeliveryConfirmation(
    IN p_session_id VARCHAR(64),
    IN p_executed_by INT,
    IN p_selse_last_update VARCHAR(64)
)
BEGIN
    -- --------------------------------------------------------------
    -- التأكد من وجود جدول confirm_staging (للاستخدام الآمن)
    -- --------------------------------------------------------------
    CREATE TEMPORARY TABLE IF NOT EXISTS confirm_staging (
        SESSION_ID   VARCHAR(64) NOT NULL,
        ORDER_ID     INT NOT NULL,
        PRO_CODE     VARCHAR(100) NOT NULL,
        USER_ID      INT NOT NULL,
        APP_QTY      DECIMAL(18,3) NOT NULL,
        STATUSE      VARCHAR(50) NOT NULL,
        TYPE         ENUM('SELSE','REFUND') NOT NULL,
        CREATED_AT   DATETIME(3) DEFAULT NOW(3),
        PRIMARY KEY (SESSION_ID, ORDER_ID, PRO_CODE, USER_ID)
    );

    -- المتغيرات الداخلية
    DECLARE v_selse_order_id INT DEFAULT 0;
    DECLARE v_refund_order_id INT DEFAULT 0;
    DECLARE v_dilevery_id INT DEFAULT 0;
    
    DECLARE v_order_product INT DEFAULT 0;
    DECLARE v_orders_updated INT DEFAULT 0;
    
    DECLARE v_custody_product INT DEFAULT 0;
    DECLARE v_custody_return_product INT DEFAULT 0;
    DECLARE v_custody_orders INT DEFAULT 0;
    DECLARE v_custody_return_orders INT DEFAULT 0;
    DECLARE v_order_logs INT DEFAULT 0;
    DECLARE v_shipping_movement INT DEFAULT 0;
    
    DECLARE v_client_balance INT DEFAULT 0;
    DECLARE v_client_return_balance INT DEFAULT 0;
    
    DECLARE v_freeze INT DEFAULT 0;
    DECLARE v_lastup_date DATETIME(3);
    DECLARE hasRefundOrder BOOLEAN DEFAULT FALSE;
    DECLARE v_status VARCHAR(10) DEFAULT 'ERROR';
    DECLARE v_message TEXT DEFAULT NULL;
    
    -- --------------------------------------------------------------
    -- معالج أخطاء عام
    -- --------------------------------------------------------------
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        -- إذا حدث خطأ نعيد رسالة واضحة
        SET v_status = 'ERROR';
        SET v_message = 'حدث خطأ أثناء تنفيذ الإجراء.';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END;

    -- --------------------------------------------------------------
    -- جلب بيانات للتحقق من FREEZE و LAST_UPDATE
    -- --------------------------------------------------------------
    SELECT DISTINCT ORDER_ID INTO v_selse_order_id FROM confirm_staging WHERE TYPE = 'SELSE' AND SESSION_ID = p_session_id;
    SELECT DISTINCT USER_ID INTO v_dilevery_id FROM confirm_staging WHERE TYPE = 'SELSE' AND SESSION_ID = p_session_id;
    
    SELECT 
        o.EXCHANGE,
        o.LAST_UPDATE,
        co.CUST_STAT,
    INTO 
        v_refund_order_id,
        v_lastup_date ,
        v_freeze,
    FROM custody_order co
    JOIN orders o on co.ORDER_ID = o.ORDER_ID
    WHERE co.ORDER_ID = v_selse_order_id AND co.USER_ID = v_dilevery_id;

	-- التحقق من وجود refund order
    
    IF v_refund_order_id IS NOT NULL AND p_refund_order_id > 0 THEN
        SET hasRefundOrder = TRUE;
    END IF;
    
    -- التحقق من LAST_UPDATE
    
    IF v_lastup_date = p_selse_last_update THEN
        SET v_status = 'ERROR';
        SET v_message = 'لم يتم تحديث أي بيانات — تم تحديث بيانات امر البيع من قبل مستخدم اخر.';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;
    
    -- التحقق من FREEZE
    
    IF v_freeze = 'FREEZE' THEN
        SET v_status = 'ERROR';
        SET v_message = 'لم يتم تحديث أي بيانات — امر البيع تم تقيد التسليم لهذا المندوب لحين قيام المندوب بإرجاع المنتجات للمخزن.';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;

    -- --------------------------------------------------------------
    -- 1. تحديث order_product استنادًا إلى confirm_staging
    -- --------------------------------------------------------------
    
    UPDATE order_product op 
    JOIN confirm_staging s ON op.ORDER_ID = s.ORDER_ID AND op.PRO_CODE = s.PRO_CODE
    SET 
      op.DELIVERED_QTY = CASE WHEN s.TYPE = 'SELSE' THEN s.APP_QTY ELSE op.DELIVERED_QTY END,
      op.RETURNED_QTY  = CASE WHEN s.TYPE = 'REFUND' THEN s.APP_QTY ELSE op.RETURNED_QTY END,
      op.PRO_STATUSE   = CASE 
                          WHEN s.TYPE = 'REFUND' AND s.APP_QTY > 0 AND op.DELIVERED_QTY > s.APP_QTY THEN 'partial_refund'
                          WHEN s.TYPE = 'REFUND' AND s.APP_QTY > 0 AND op.DELIVERED_QTY = s.APP_QTY THEN 'refund'
                          WHEN s.TYPE = 'SELSE'  AND s.APP_QTY > 0 AND s.APP_QTY < op.QTY THEN 'partial'
                          WHEN s.TYPE = 'SELSE'  AND s.APP_QTY = 0 THEN 'rejected'
                          WHEN s.TYPE = 'SELSE'  AND s.APP_QTY = op.QTY THEN 'complete'
                          ELSE op.PRO_STATUSE
                        END,
      op.LAST_UPDATE = NOW(3)
    WHERE s.SESSION_ID = p_session_id;

    SET v_order_product = ROW_COUNT();

    -- --------------------------------------------------------------
    -- 2. تحديث orders بناءً على تجميع order_product للأوامر المتأثرة
    -- --------------------------------------------------------------
    UPDATE orders O
    JOIN (
        SELECT ORDER_ID,
               CASE 
                 WHEN SUM(RETURNED_QTY) > 0 AND SUM(DELIVERED_QTY) > SUM(RETURNED_QTY) THEN 7
                 WHEN SUM(RETURNED_QTY) > 0 AND SUM(DELIVERED_QTY) = SUM(RETURNED_QTY) THEN 6
                 WHEN SUM(RETURNED_QTY) = 0 AND SUM(DELIVERED_QTY) = 0 THEN 5
                 WHEN SUM(RETURNED_QTY) = 0 AND SUM(DELIVERED_QTY) < SUM(QTY) AND SUM(DELIVERED_QTY) > 0 THEN 4
                 WHEN SUM(RETURNED_QTY) = 0 AND SUM(DELIVERED_QTY) = SUM(QTY) THEN 3
                 ELSE NULL
               END AS ORDER_STATUS
        FROM order_product
        WHERE ORDER_ID IN (SELECT DISTINCT ORDER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id)
        GROUP BY ORDER_ID
    ) OPagg ON O.ORDER_ID = OPagg.ORDER_ID
    SET O.EXISTS_ID = 10,
        O.ORDER_STATUS = OPagg.ORDER_STATUS,
        O.LAST_UPDATE = NOW(3),
        O.DELIVERED_DATE = NOW(3)
    WHERE O.ORDER_ID IN (SELECT DISTINCT ORDER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id);

    SET v_orders_updated = ROW_COUNT();

    -- --------------------------------------------------------------
    -- 3. تحديث custody_product من السجلات من نوع SELSE
    -- --------------------------------------------------------------
    UPDATE custody_product cp
    JOIN order_product op 
        ON cp.ORDER_ID = op.ORDER_ID 
        AND cp.PRO_CODE = op.PRO_CODE
    JOIN confirm_staging s 
        ON s.ORDER_ID = op.ORDER_ID 
        AND s.PRO_CODE = op.PRO_CODE 
        AND s.SESSION_ID = p_session_id 
        AND s.TYPE = 'SELSE' 
        AND op.USER_ID = s.USER_ID
    SET cp.DELIVERED_QTY = op.DELIVERED_QTY,
        cp.PRO_STATUSE   = op.PRO_STATUSE,
        cp.LAST_UPDATE   = NOW(3);

    SET v_custody_product = ROW_COUNT();

    -- --------------------------------------------------------------
    -- 4. تحديث custody_order من السجلات من (orders) للـ SELSE
    -- --------------------------------------------------------------
    UPDATE custody_order co
    JOIN orders o ON co.ORDER_ID = o.ORDER_ID
    JOIN (
        SELECT DISTINCT ORDER_ID, USER_ID
        FROM confirm_staging
        WHERE SESSION_ID = p_session_id AND TYPE = 'SELSE'
    ) s ON s.ORDER_ID = co.ORDER_ID AND s.USER_ID = co.USER_ID
    SET co.CUST_STAT = 'DELIVERED',
        co.ORDER_STATUSE = o.ORDER_STATUS,
        co.LAST_UPDATE = NOW(3);

    SET v_custody_orders = ROW_COUNT();

    -- --------------------------------------------------------------
    -- تحديث أرصدة العميل لأمر البيع
    -- --------------------------------------------------------------
    
    CALL restClientDebtBatch(p_selse_order_id, v_client_balance);

    -- --------------------------------------------------------------
    -- 5. تحديث custody_return_product من السجلات من نوع REFUND (إن وجدت)
    -- --------------------------------------------------------------

    UPDATE custody_return_product crp
    JOIN order_product op
        ON crp.ORDER_ID = op.ORDER_ID
        AND crp.PRO_CODE = op.PRO_CODE
    JOIN confirm_staging s
        ON s.ORDER_ID = op.ORDER_ID
        AND s.PRO_CODE = op.PRO_CODE
        AND s.SESSION_ID = p_session_id
        AND s.TYPE = 'REFUND'
        AND crp.USER_ID = s.USER_ID
    SET crp.DELIVERED_QTY = op.DELIVERED_QTY,
        crp.PRO_STATUSE = op.PRO_STATUSE,
        crp.LAST_UPDATE = NOW(3);

    SET v_custody_return_product = ROW_COUNT();

    -- --------------------------------------------------------------
    -- 6. تحديث custody_return_order من السجلات من (orders) للـ REFUND
    -- --------------------------------------------------------------
  
    UPDATE custody_return_order cro
    JOIN orders o ON cro.ORDER_ID = o.ORDER_ID
    JOIN (
        SELECT DISTINCT ORDER_ID, USER_ID
        FROM confirm_staging
        WHERE SESSION_ID = p_session_id AND TYPE = 'REFUND'
    ) s ON s.ORDER_ID = cro.ORDER_ID AND s.USER_ID = cro.USER_ID
    SET cro.CUST_STAT = 'RETURNED',
        cro.ORDER_STATUSE = o.ORDER_STATUS,
        cro.LAST_UPDATE = NOW(3);

    SET v_custody_return_orders = ROW_COUNT();

    -- --------------------------------------------------------------
    -- تحديث عهدة المندوب مرتبط بالـ refund order (إن وجد)
    -- --------------------------------------------------------------
    
    IF hasRefundOrder = TRUE THEN
        CALL reduceClientCreditBatch(p_refund_order_id, v_client_return_balance);
    END IF;
   
	CALL shipping_movement(p_session_id, p_executed_by , v_shipping_movement);
    
    -- --------------------------------------------------------------
    -- التحقق النهائي: إذا لم يحدث أي تغيير في الجوانب الأساسية -> خطأ
    -- --------------------------------------------------------------
    
    IF v_orders_updated = 0
       OR v_order_product = 0
       OR v_custody_orders = 0
       OR v_client_balance = 0
       OR v_custody_product = 0
       OR v_shipping_movement = 0
       OR (hasRefundOrder IS NOT NULL AND v_custody_return_orders = 0)
       OR (hasRefundOrder IS NOT NULL AND v_custody_return_product = 0)
       OR (hasRefundOrder IS NULL AND v_custody_return_orders > 0)
       OR (hasRefundOrder IS NULL AND v_custody_return_product > 0)
       OR (hasRefundOrder IS NOT NULL AND v_client_return_balance = 0)
       
    THEN
        SET v_status = 'ERROR';
        SET v_message = 'لم يتم تحديث أي بيانات — تحقق من رقم الأمر أو المستخدم.';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;

    -- --------------------------------------------------------------
    -- النتيجة النهائية (SELECT لعرض النتيجة للمستدعي)
    -- --------------------------------------------------------------
    
    SELECT 'success' AS status,
           'تم التنفيذ بنجاح' AS message,
           v_custody_product     AS custody_product_rows,
           v_custody_return_product AS custody_return_product_rows,
           v_order_product       AS order_product_rows,
           v_orders_updated      AS orders_rows;

END$$

DELIMITER ;*/