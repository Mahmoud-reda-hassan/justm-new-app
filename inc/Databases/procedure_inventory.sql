DROP PROCEDURE IF EXISTS confirm_orders_with_stock;

DELIMITER $$

CREATE PROCEDURE confirm_orders_with_stock (
    IN p_session_id TEXT,
    IN p_user_action_id INT,
    IN p_statuse_id INT,
    IN p_store_id INT,
    IN p_last_update DATETIME
)
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_order_id INT;

    DECLARE cur_orders CURSOR FOR
        SELECT ORDER_ID
        FROM tmp_orders
        ORDER BY seq;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    -- =====================================================
    -- 1️⃣ جدول مؤقت لتجميع حالة كل Order
    -- ===================================================== 
    DROP TEMPORARY TABLE IF EXISTS tmp_orders;

    CREATE TEMPORARY TABLE tmp_orders (
        seq INT AUTO_INCREMENT PRIMARY KEY, -- ترتيب التنفيذ
        ORDER_ID INT,
        osubid  VARCHAR(150),
        statuse TINYINT,
        print   TINYINT,
        freeze  TINYINT,
        oserv   TINYINT,
        lastup  TINYINT,
        store   TINYINT DEFAULT 0,
        can_proceed TINYINT DEFAULT 0
    ) ENGINE=MEMORY;

    -- =====================================================
    -- 2️⃣ إدخال الأوامر + حساب الشروط المنطقية
    -- ===================================================== 
    INSERT INTO tmp_orders (ORDER_ID, osubid, statuse, print, freeze, oserv, lastup)
    SELECT 
        CS.ORDER_ID,
        O.SUB_ORDER_ID AS osubid,
        CASE WHEN O.ORDER_STATUS = p_statuse_id THEN 0 ELSE 1 END AS statuse,
        CASE WHEN O.PRINT = 0 THEN 0 ELSE 1 END AS print,
        CASE WHEN co.CUST_STAT != 'FREEZE' OR co.CUST_STAT IS NULL THEN 1 ELSE 0 END AS freeze,
        CASE WHEN Q.OS_STATUSE IS NOT NULL AND Q.OS_STATUSE = 0 THEN 0 ELSE 1 END AS oserv,
        CASE WHEN o.LAST_UPDATE <= p_last_update THEN 1 ELSE 0 END AS lastup
        
    FROM (SELECT ORDER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id GROUP BY ORDER_ID) CS
    LEFT JOIN orders AS O ON O.ORDER_ID = CS.ORDER_ID
    LEFT JOIN custody_orders CO ON O.ORDER_ID = CO.ORDER_ID AND USER_ID = p_user_action_id
    LEFT JOIN (SELECT ORDER_ID, OS_STATUSE FROM order_service WHERE OS_STATUSE = 0 GROUP BY ORDER_ID) AS Q ON O.ORDER_ID = Q.ORDER_ID
    GROUP BY O.ORDER_ID, O.SUB_ORDER_ID, O.PRINT, O.ORDER_STATUS, O.LAST_UPDATE, CO.CUST_STAT, Q.OS_STATUSE
    ORDER BY O.DATE_CREATED, O.ORDER_ID;

    -- =====================================================
    -- 4️⃣ جدول مؤقت لمنتجات الأوامر الصالحة فقط
    -- ===================================================== 
    DROP TEMPORARY TABLE IF EXISTS tmp_products;
    
    CREATE TEMPORARY TABLE tmp_products (
        ORDER_ID INT  NOT NULL ,
        PRO_CODE VARCHAR(150) NOT NULL,
        QTY DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0.00
    ) ENGINE=MEMORY;

    INSERT INTO tmp_products ( ORDER_ID, PRO_CODE, QTY)
    SELECT
        CS.ORDER_ID,
        op.PRO_CODE,
        SUM(op.QTY) AS QTY
    FROM (SELECT ORDER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id GROUP BY ORDER_ID) CS
    LEFT JOIN order_product op ON op.ORDER_ID = CS.ORDER_ID
    GROUP BY CS.ORDER_ID, op.PRO_CODE
    ORDER BY CS.ORDER_ID, op.PRO_CODE;

    -- ==============================================
    -- 5️⃣ إنشاء مخزون مؤقت للتعديل أثناء التنفيذ
    -- ==============================================
    DROP TEMPORARY TABLE IF EXISTS tmp_store;

    CREATE TEMPORARY TABLE tmp_store (
        PRO_CODE VARCHAR(150) NOT NULL,
        QTY DECIMAL(10,2) NOT NULL
    ) ENGINE=MEMORY;

    INSERT INTO tmp_store (PRO_CODE, QTY)
    SELECT PRO_CODE, STO_QTY
    FROM stores_product
    WHERE STO_ID = p_store_id;

    -- ==============================================
    -- 6️⃣ Loop على الأوامر حسب seq
    -- ==============================================
    
    
    OPEN cur_orders;

    order_loop: LOOP
        FETCH cur_orders INTO v_order_id;
        IF done = 1 THEN
            LEAVE order_loop;
        END IF;

        -- هل كل منتجات الأمر متاحة؟
        IF NOT EXISTS (
            SELECT 1
            FROM tmp_products p
            JOIN tmp_store s ON s.PRO_CODE = p.PRO_CODE
            WHERE p.ORDER_ID = v_order_id
              AND s.QTY < p.QTY
        ) THEN
            -- ✔️ كل المنتجات متاحة → خصم المخزون
            UPDATE tmp_store s
            JOIN tmp_products p ON p.PRO_CODE = s.PRO_CODE
            SET s.QTY = s.QTY - p.QTY
            WHERE p.ORDER_ID = v_order_id;

            -- تعليم الأمر كمقبول من ناحية المخزون
            UPDATE tmp_orders
            SET store = 1
            WHERE ORDER_ID = v_order_id;

        ELSE
            -- ❌ أمر مرفوض → لا يخصم أي مخزون
            UPDATE tmp_orders
            SET store = 0
            WHERE ORDER_ID = v_order_id;
        END IF;

    END LOOP;

    CLOSE cur_orders;

    -- =====================================================
    --  7️⃣ القرار النهائي
    -- ===================================================== 
    UPDATE tmp_orders
    SET can_proceed = (
        statuse = 1 AND
        print   = 1 AND
        freeze  = 1 AND
        oserv   = 1 AND
        store   = 1 AND
        lastup  = 1
    );

    -- =====================================================
    --  8️⃣ Result Set النهائي
    -- ===================================================== 
    SELECT
        ORDER_ID,
        osubid,
        statuse,
        print,
        freeze,
        oserv,
        lastup,
        store,
        can_proceed
    FROM tmp_orders
    ORDER BY seq;


END $$

DELIMITER ;





DELIMITER $$

CREATE PROCEDURE sp_reg_employee_custody (
    IN p_order_ids TEXT,      -- قائمة الاوامر مفصولة بفواصل
    IN p_user_id INT,         -- ID المستخدم الحالي
    IN p_store_id INT,
    IN p_delivery_id INT
)
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_order_id INT;
    
    -- ======================= جداول مؤقتة ===========================
    CREATE TEMPORARY TABLE IF NOT EXISTS tmp_order_products (
        ORDER_ID INT,
        PRO_CODE VARCHAR(50),
        QTY INT,
        can_fulfill TINYINT DEFAULT 0,
        PRIMARY KEY (ORDER_ID, PRO_CODE)
    ) ENGINE=MEMORY;

    CREATE TEMPORARY TABLE IF NOT EXISTS tmp_stock (
        PRO_CODE VARCHAR(50) PRIMARY KEY,
        current_stock INT
    ) ENGINE=MEMORY;

    CREATE TEMPORARY TABLE IF NOT EXISTS tmp_orders_result (
        ORDER_ID INT PRIMARY KEY,
        status ENUM('ACCEPTED','REJECTED') DEFAULT 'REJECTED'
    ) ENGINE=MEMORY;

    CREATE TEMPORARY TABLE IF NOT EXISTS tmp_confirm_staging (
        SESSION_ID VARCHAR(50),
        ORDER_ID INT,
        PRO_CODE VARCHAR(50),
        USER_ID INT,
        APP_QTY INT,
        STATUSE ENUM('pending','confirmed'),
        TYPE VARCHAR(50)
    ) ENGINE=MEMORY;

    -- ======================= 5️⃣ إضافة المنتجات إلى confirm_staging ===========================
    SET @session_id := CONCAT('cust_', UUID());

    SET @sql_insert_staging = CONCAT('
        INSERT INTO tmp_confirm_staging (SESSION_ID, ORDER_ID, PRO_CODE, USER_ID, APP_QTY, STATUSE, TYPE)
        SELECT "', @session_id, '", ORDER_ID, PRO_CODE, ', p_delivery_id, ', QTY, PRO_STATUSE, "SHIPPING"
        FROM order_product
        WHERE ORDER_ID IN (', p_order_ids, ')
    ');
    PREPARE stmt_staging FROM @sql_insert_staging;
    EXECUTE stmt_staging;
    DEALLOCATE PREPARE stmt_staging;

    -- ======================= 3️⃣ إدخال المنتجات وحساب المخزون التراكمي ===========================
    SET @sql_products = CONCAT('
        INSERT INTO tmp_order_products (ORDER_ID, PRO_CODE, QTY)
        SELECT ORDER_ID, PRO_CODE, SUM(QTY)
        FROM order_product
        WHERE ORDER_ID IN (', p_order_ids, ')
        GROUP BY ORDER_ID, PRO_CODE
        ORDER BY ORDER_ID
    ');
    PREPARE stmt_products FROM @sql_products;
    EXECUTE stmt_products;
    DEALLOCATE PREPARE stmt_products;

    INSERT INTO tmp_stock(PRO_CODE, current_stock)
    SELECT PRO_CODE, COALESCE(STO_QTY,0)
    FROM stores_product
    WHERE STO_ID = p_store_id
    GROUP BY PRO_CODE;

    -- ======================= 4️⃣ تحديث can_fulfill لكل منتج حسب المخزون ===========================
    SET @running_stock := 0;

    UPDATE tmp_order_products op
    JOIN tmp_stock s ON s.PRO_CODE = op.PRO_CODE
    SET op.can_fulfill = IF(op.QTY <= s.current_stock, 1, 0);

    -- ======================= 4️⃣ تحديد الأوامر المقبولة والمرفوضة ===========================
    INSERT INTO tmp_orders_result(ORDER_ID, status)
    SELECT ORDER_ID,
           CASE WHEN MIN(can_fulfill) = 1 THEN 'ACCEPTED' ELSE 'REJECTED' END
    FROM tmp_order_products
    GROUP BY ORDER_ID;

    -- ======================= 7️⃣ تحديث orders المقبولة ===========================
    UPDATE orders o
    JOIN tmp_orders_result r ON o.ORDER_ID = r.ORDER_ID
    SET o.EXISTS_ID = 9,
        o.SHIPPING_DATE = NOW(3),
        o.DELIVERY_ID = p_delivery_id
    WHERE r.status = 'ACCEPTED';

    -- ======================= 8️⃣ إضافة العهدة (custody) ===========================
    -- نفترض أن هناك procedure insert_custody موجود
    -- استدعاء لكل ORDER_ID مقبول
    -- DECLARE cur_orders CURSOR FOR
    --     SELECT ORDER_ID FROM tmp_orders_result WHERE status = 'ACCEPTED';
    -- DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    -- OPEN cur_orders;
    -- read_loop: LOOP
    --     FETCH cur_orders INTO v_order_id;
    --     IF done THEN
    --         LEAVE read_loop;
    --     END IF;
        -- CALL insert_custody(v_order_id, p_delivery_id);
    -- END LOOP;
    -- CLOSE cur_orders;

    -- ======================= 9️⃣ إضافة المقبول إلى shipping_movement ===========================
    -- نفترض procedure set_inventory_movement_orders موجود
    -- CALL set_inventory_movement_orders(@session_id, NULL, p_user_id, NULL, p_store_id, 'SHIPPING');

    -- ======================= 🔟 إضافة orders_log ===========================
    -- يمكن أن نضيف سجل لكل ORDER_ID مقبول
    -- INSERT INTO orders_log(ORDER_ID, USER_ID, LOG_TEXT) VALUES (...);

    -- ======================= 6️⃣ صرف المخزون فعليًا ===========================
    -- (تم التعليق على هذا الجزء كما طلبت)
    -- UPDATE stores_product SET STO_QTY = STO_QTY - ? WHERE PRO_CODE = ? AND STO_ID = p_store_id;

    -- ======================= 1️⃣2️⃣ إرجاع النتيجة ===========================
    SELECT r.ORDER_ID, r.status, 
           GROUP_CONCAT(CONCAT(op.PRO_CODE,'=',op.QTY,'-',op.can_fulfill) ORDER BY op.PRO_CODE SEPARATOR '; ') AS product_details
    FROM tmp_orders_result r
    JOIN tmp_order_products op ON op.ORDER_ID = r.ORDER_ID
    GROUP BY r.ORDER_ID, r.status
    ORDER BY r.ORDER_ID;

    -- تنظيف الجداول المؤقتة
    DROP TEMPORARY TABLE IF EXISTS tmp_order_products;
    DROP TEMPORARY TABLE IF EXISTS tmp_stock;
    DROP TEMPORARY TABLE IF EXISTS tmp_orders_result;
    DROP TEMPORARY TABLE IF EXISTS tmp_confirm_staging;

END$$

DELIMITER ;

/*===============================================================
 * # الإجراء: update_stores
 * ------------------------------------------
 * # الغرض:
 *   تحديث أرصدة المخازن بناءً على حركات المخزون غير المعالجة
 *   مع ضمان التكامل والسلامة في البيئات متعددة المستخدمين
 *   من خلال تطبيق Lock مركزي على عمليات تحديث المخزون
 * ------------------------------------------
 * # المعاملات:
 *   OUT p_return → عدد الصفوف التي تم تحديثها في المخازن
 * ------------------------------------------
 * # المنطق:
 *   1 - تطبيق Lock مركزي على المخزون لمنع التعديلات المتزامنة
 *   2 - التحقق من وجود حركات مخزون غير معالجة
 *   3 - حساب صافي الحركات (IN - OUT) وتحديث أرصدة المخازن
 *   4 - تحديث علامة المعالجة للحركات المنفذة
 *   5 - التحقق من تطابق عدد الصفوف المتأثرة
 *   6 - تحرير Lock المخزون وإرجاع النتيجة
 * ------------------------------------------
 * # آلية الـ Lock:
 *   ## Lock المركزي:
 *      - استخدام GET_LOCK('inventory_global_lock', 3)
 *      - timeout لمدة 3 ثواني لمنع الانتظار الطويل
 *      - يضمن عدم تنفيذ أكثر من عملية تحديث مخزون في نفس الوقت
 *   
 *   ## معالجة الأخطاء:
 *      - في حالة أي خطأ، يتم تحرير الـ Lock تلقائياً
 *      - إعادة إرسال الخطأ الأصلي للطبقات العليا
 * ------------------------------------------
 * # الجداول المستخدمة:
 *   ## 1 inventory_product_movement
 *      - جدول حركات المنتجات
 *      - IM_ID         → رقم الحركة
 *      - IPM_TYPE_MOV  → نوع الحركة (IN/OUT)
 *      - IPM_CODE      → كود المنتج
 *      - IPM_QTY       → الكمية
 *      - IS_PROCESSED  → حالة المعالجة
 *
 *   ## 2 inventory_movement
 *      - جدول الحركات الرئيسية
 *      - IM_ID         → رقم الحركة
 *      - STO_ID        → رقم المخزن
 *
 *   ## 3 stores_product
 *      - جدول أرصدة المخازن
 *      - STO_ID        → رقم المخزن
 *      - PRO_CODE      → كود المنتج
 *      - STO_QTY       → الكمية المتاحة
 *      - LAST_UPDATE   → آخر تحديث
 * ------------------------------------------
 * # آلية الحساب:
 *   ## صافي الكمية = 
 *      - IN  → +IPM_QTY
 *      - OUT → -IPM_QTY
 *   ## التحديث:
 *      STO_QTY = STO_QTY + Σ(صافي الحركات)
 * ------------------------------------------
 * # التحقق من التكامل:
 *   ## قبل التحديث:
 *      - التحقق من وجود حركات غير معالجة
 *      - COUNT(DISTINCT PRO_CODE) → عدد المنتجات المختلفة
 *   
 *   ## بعد التحديث:
 *      - مقارنة ROW_COUNT() بعدد الحركات الأصلية
 *      - التأكد من تطابق عدد الصفوف المتأثرة
 * ------------------------------------------
 * # حالات الخطأ:
 *   - SQLSTATE '45000' → فشل في الحصول على Lock المخزون
 *   - SQLSTATE '45000' → لا توجد حركات مخزون للمعالجة
 *   - SQLSTATE '45000' → عدم تطابق عدد الصفوف المتأثرة
 *   - SQLSTATE '45000' → أي خطأ في تنفيذ الاستعلامات
 * ------------------------------------------
 * # المخرجات:
 *   - p_return → عدد صفوف المخازن التي تم تحديثها
 * ------------------------------------------
 * # المبرمج: [محمود رضا]
 * # تاريخ التعديل: [15/11/2025]
 * ------------------------------------------
 * # ملاحظات:
 *   - الإجراء يعالج جميع الحركات غير المعالجة في نفس الوقت
 *   - Lock المخزون يمنع التعديلات المتزامنة من أي مصدر
 *   - مناسب للبيئات عالية التزامن والاستخدام
 *   - timeout الـ Lock محدود ب 3 ثواني لضمان استجابة النظام
 *   - يتم تحرير الـ Lock في جميع الحالات (نجاح/فشل/خطأ)
 * ===============================================================*/

DROP PROCEDURE IF EXISTS update_stores;

DELIMITER $$

CREATE PROCEDURE update_stores(OUT p_return INT)
BEGIN
    -- 🔹 تعريف المتغيرات
    DECLARE v_movement_exists INT DEFAULT 0;     -- عدد المنتجات في الحركات غير المعالجة
    DECLARE v_affected_rows INT DEFAULT 0;       -- الصفوف المتأثرة في التحديث
    DECLARE v_message VARCHAR(255);              -- رسائل الخطأ

    -- --------------------------------------------------------------
    -- الخطوة 2: التحقق من وجود حركات غير معالجة
    -- --------------------------------------------------------------
    SELECT COUNT(IPM_CODE) INTO v_movement_exists 
    FROM inventory_product_movement
    WHERE IS_PROCESSED = FALSE;

    IF v_movement_exists = 0 THEN
        SET v_message = ' لا توجد حركات مخزون جديدة للمعالجة';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;

    -- --------------------------------------------------------------
    -- الخطوة 3: تحديث أرصدة المخازن
    -- --------------------------------------------------------------
    UPDATE stores_product STP 
    INNER JOIN (
        SELECT 
            IM.STO_ID,
            IPM.IPM_CODE,
            SUM(CASE
                WHEN IPM.IPM_TYPE_MOV = 'IN' THEN IPM.IPM_QTY
                WHEN IPM.IPM_TYPE_MOV = 'OUT' THEN -IPM.IPM_QTY
                ELSE 0
            END) AS NET_QTY
        FROM inventory_product_movement IPM
        INNER JOIN inventory_movement IM ON IPM.IM_ID = IM.IM_ID
        WHERE IPM.IS_PROCESSED = FALSE 
        GROUP BY IM.STO_ID, IPM.IPM_CODE
    ) AS MOV ON STP.STO_ID = MOV.STO_ID AND STP.PRO_CODE = MOV.IPM_CODE
    SET 
        STP.STO_QTY = STP.STO_QTY + MOV.NET_QTY,  -- تحديث الرصيد
        STP.LAST_UPDATE = NOW(6);                 -- تحديث وقت التعديل

    -- --------------------------------------------------------------
    -- الخطوة 4: تحديث علامة المعالجة
    -- --------------------------------------------------------------
    UPDATE inventory_product_movement 
    SET IS_PROCESSED = TRUE 
    WHERE IS_PROCESSED = FALSE;  -- وضع علامة المعالجة

    -- --------------------------------------------------------------
    -- الخطوة 5: التحقق من التكامل
    -- --------------------------------------------------------------
    SET v_affected_rows = ROW_COUNT();  -- عدد الصفوف المحدثة

    IF v_affected_rows = 0 OR v_affected_rows != v_movement_exists THEN
        SET v_message = CONCAT(
            ' حدث خطأ أثناء تحديث المخزون ',
            ' | الصفوف المتأثرة: ', v_affected_rows, 
            ' | صفوف الحركة: ', v_movement_exists
        );
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;

    -- --------------------------------------------------------------
    -- 🔹 الخطوة 7: إرجاع النتيجة
    -- --------------------------------------------------------------
    SET p_return = v_affected_rows;  -- إرجاع عدد الصفوف المحدثة

END$$

DELIMITER ;


/*===============================================================
 * # الإجراء: inventory_movement_purchases_orders
 * ------------------------------------------
 * # الغرض:
 *   إدارة حركات المخزون الخاصة بأوامر الشراء والمرتجعات للموردين
 *   من خلال تسجيل حركات المخزون وتحديث أرصدة المخازن تلقائياً
 * ------------------------------------------
 * # المعاملات:
 *   IN  p_order_id        → رقم أمر الشراء
 *   IN  p_partner_id      → رقم المورد
 *   IN  p_user_action_id  → رقم المستخدم المنفذ للعملية
 *   IN  p_branch_id       → رقم الفرع
 *   IN  p_store_id        → رقم المخزن المستهدف
 *   IN  p_movement_type   → نوع حركة المخزون
 *   OUT p_return          → رمز النتيجة (1 = نجاح, 0 = فشل)
 * ------------------------------------------
 * # أنواع الحركات المدعومة:
 *   RECEIVING       → استلام منتجات أمر الشراء من المورد
 *   REFUNDPURCHISES → صرف مرتجعات أمر الشراء إلى المورد
 * ------------------------------------------
 * # المنطق:
 *   1 - تحديد اتجاه الحركة (IN/OUT) والنص الوصفي
 *   2 - التحقق من صحة نوع الحركة المدخل
 *   3 - تسجيل سجل رئيسي لحركة المخزون
 *   4 - تسجيل المنتجات المرتبطة بالحركة من أمر الشراء
 *   5 - تحديث أرصدة المخازن عبر الإجراء update_stores
 *   6 - التحقق من نجاح كل خطوة وإرجاع النتيجة
 * ------------------------------------------
 * # الجداول المستخدمة:
 *   ## 1 inventory_movement
 *      - جدول الحركات الرئيسية للمخزون
 *      - IM_MOVE_TYPE    → نوع الحركة (RECEIVING, REFUNDPURCHISES)
 *      - IM_USER         → المستخدم المنفذ
 *      - IM_BRANCH       → الفرع التابع
 *      - STO_ID          → المخزن المستهدف
 *      - REFERENCE       → رقم مرجعي (يستخدم p_order_id)
 *      - IM_DETAILS      → تفاصيل الحركة بتنسيق JSON
 *      - IM_APPROVAL_DISBURSED → موافقة الصرف (1 = معتمدة)
 *      - IM_APPROVAL_RECEIVED  → موافقة الاستلام (1 = معتمدة)
 *
 *   ## 2 inventory_product_movement
 *      - جدول تفاصيل منتجات الحركة
 *      - IM_ID          → رقم الحركة الرئيسية
 *      - IPM_TYPE_MOV   → نوع الحركة (IN/OUT)
 *      - IPM_CODE       → كود المنتج
 *      - IPM_QTY        → الكمية المنقولة في الحركة
 *      - CREATE_AT      → تاريخ الإنشاء
 *
 *   ## 3 purchases_products
 *      - جدول منتجات أوامر الشراء
 *      - ORDER_ID       → رقم أمر الشراء
 *      - PRO_CODE       → كود المنتج
 *      - QTY_SUPPLIED   → الكمية المستلمة من المورد
 *
 *   ## 4 purchases_return_products
 *      - جدول منتجات مرتجعات الشراء
 *      - ORDER_ID       → رقم أمر الشراء
 *      - PRO_CODE       → كود المنتج
 *      - QTY_RETURN     → الكمية المرتجعة للمورد
 * ------------------------------------------
 * # شروط تحديد الكمية المنقولة:
 *   ## لحركة الاستلام (RECEIVING):
 *      - الكمية = QTY_SUPPLIED من purchases_products
 *
 *   ## لحركة المرتجعات (REFUNDPURCHISES):
 *      - الكمية = QTY_RETURN من purchases_return_products
 *      - استخدام COALESCE للتعامل مع القيم NULL
 * ------------------------------------------
 * # قواعد تحديد اتجاه الحركة:
 *   ## حركات الدخول (IN):
 *      - RECEIVING → استلام منتجات من المورد
 *
 *   ## حركات الخروج (OUT):
 *      - REFUNDPURCHISES → إرجاع منتجات إلى المورد
 * ------------------------------------------
 * # هيكل بيانات IM_DETAILS:
 *   {
 *     "partner": [رقم المورد],
 *     "NOTE": "[النص الوصفي للحركة]"
 *   }
 * ------------------------------------------
 * # آلية الربط بين الجداول:
 *   - الربط بين purchases_products و purchases_return_products
 *     ON ORDER_ID AND PRO_CODE
 *   - استخدام LEFT JOIN للتعامل مع حالات عدم وجود مرتجعات
 * ------------------------------------------
 * # حالات الخطأ:
 *   - SQLSTATE '45000' → نوع حركة غير معروف
 *   - SQLSTATE '45000' → فشل حفظ سجل الحركة الرئيسي
 *   - SQLSTATE '45000' → فشل حفظ سجلات المنتجات
 *   - SQLSTATE '45000' → فشل تحديث المخزون
 * ------------------------------------------
 * # التبعيات:
 *   - الإجراء update_stores → لتحديث أرصدة المخازن
 * ------------------------------------------
 * # المخرجات:
 *   - p_return → 1 في حالة النجاح, 0 في حالة الفشل
 * ------------------------------------------
 * # المبرمج: [محمود رضا]
 * # تاريخ الإنشاء: [23/11/2025]
 * ------------------------------------------
 * # ملاحظات:
 *   - الإجراء يستخدم معالج أخطاء عام (SQLEXCEPTION)
 *   - يتم استخدام p_order_id كرقم مرجعي للحركة
 *   - يتم تخزين تفاصيل إضافية في حقل IM_DETAILS بتنسيق JSON
 *   - جميع الحركات معتمدة تلقائياً (موافقة مسبقة)
 *   - يتم التعامل مع القيم NULL في المرتجعات باستخدام COALESCE
 *   - الإجراء يدعم معالجة أمر شراء واحد في كل استدعاء
 * ===============================================================*/

DROP PROCEDURE IF EXISTS inventory_movement_purchases_orders;

DELIMITER $$

CREATE PROCEDURE inventory_movement_purchases_orders(
    IN p_session_id VARCHAR(50),
   -- IN p_order_id INT,
    IN p_partner_id INT,
    IN p_user_action_id INT,
    IN p_branch_id INT, 
    IN p_store_id INT,
    IN p_movement_type ENUM('RECEIVING', 'REFUNDPURCHISES'),
    OUT p_return INT
)
BEGIN
    --  تعريف المتغيرات الداخلية
    DECLARE v_lock_acquired INT DEFAULT 0;          -- حالة الحصول على Lock
    DECLARE v_last_id BIGINT DEFAULT NULL;          -- رقم حركة المخزون بعد الإدخال
    DECLARE v_message VARCHAR(255);                 -- لتخزين الرسائل أو الأخطاء
    DECLARE v_result INT DEFAULT 0;                 -- ناتج استدعاء update_stores
    DECLARE v_row_count INT DEFAULT 0;              -- عدد الصفوف التي تم إدخالها
    DECLARE v_movement_exists INT DEFAULT 0;        -- تحقق من وجود الحركة
    DECLARE v_type ENUM('IN', 'OUT') DEFAULT NULL;  -- نوع الحركة (إضافة أو صرف)
    DECLARE v_movstr TEXT DEFAULT NULL;             -- نص وصفي للحركة
    --  معالج الأخطاء العامة
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SELECT RELEASE_LOCK('inventory_global_lock');  -- 🔓 تحرير Lock المخزون
        RESIGNAL;
    END;
    
    -- --------------------------------------------------------------
    --  الخطوة 1: تطبيق Lock مركزي على المخزون
    -- --------------------------------------------------------------
    SELECT GET_LOCK('inventory_global_lock', 3) INTO v_lock_acquired;
    IF v_lock_acquired != 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '🔧 النظام يقوم بتحديث المخزون حالياً الرجاء المحاولة مرة أخرى بعد 5-10  ثواني للاستفسار، اتصل بالدعم الفني';
    END IF;
    
    
    -- --------------------------------------------------------------
    --  تحديد نوع الحركة (IN / OUT) ونص الوصف الخاص بها
    -- --------------------------------------------------------------
    SET v_type = CASE 
                    WHEN p_movement_type = 'RECEIVING'        THEN 'IN' 
                    WHEN p_movement_type = 'REFUNDPURCHISES'  THEN 'OUT' 
                    ELSE NULL 
                 END;

    SET v_movstr = CASE 
                      WHEN p_movement_type = 'RECEIVING'        THEN 'استلام منتجات أمر الشراء من المورد'
                      WHEN p_movement_type = 'REFUNDPURCHISES'  THEN 'صرف مرتجعات أمر الشراء إلى المورد'
                      ELSE NULL 
                   END;

    IF v_type IS NULL OR v_movstr IS NULL THEN 
        SET v_message = CONCAT('❌ غير قادر على التعرف على نوع حركة المخزون: ', p_order_id);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;

    -- --------------------------------------------------------------
    --  تسجيل رأس الحركة في جدول inventory_movement
    -- --------------------------------------------------------------
    INSERT INTO inventory_movement (
        IM_MOVE_TYPE, IM_USER, IM_BRANCH, STO_ID, REFERENCE, 
        IM_DETAILS, IM_APPROVAL_DISBURSED, IM_APPROVAL_RECEIVED
    )
    VALUES (
        p_movement_type, 
        p_user_action_id, 
        v_branch_id, 
        p_store_id, 
        CONCAT(p_user_action_id, DATE_FORMAT(NOW(), '%y%m%d%H%i%s'), LPAD(FLOOR(RAND() * 1000), 3, '0')), 
        p_order_id, 
        1, 
        1
    );

    SET v_last_id = LAST_INSERT_ID();
    IF v_last_id IS NULL OR v_last_id = 0 THEN
        SET v_message = CONCAT('❌ لم يتم حفظ سجلات حركة المخزون لأمر الشراء رقم: ', p_order_id);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;

    -- --------------------------------------------------------------
    --  تسجيل تفاصيل المنتجات الخاصة بالحركة
    -- --------------------------------------------------------------
    INSERT INTO inventory_product_movement (
        IM_ID, ORDER_ID, IPM_TYPE_MOV, IPM_CODE, IPM_QTY, CREATE_AT
    ) 
    SELECT 
        v_last_id AS IM_ID, 
        CASE 
            WHEN p_movement_type = 'RECEIVING'        THEN PUP.ORDER_ID
            WHEN p_movement_type = 'REFUNDPURCHISES'  THEN PRP.ORDER_ID
            ELSE 0
        END AS ORDER_ID,
        v_type AS IPM_TYPE_MOV, 
        PUP.PRO_CODE AS IPM_CODE,
        CASE 
            WHEN p_movement_type = 'RECEIVING'        THEN PUP.QTY_SUPPLIED
            WHEN p_movement_type = 'REFUNDPURCHISES'  THEN COALESCE(PRP.QTY_RETURN, 0)
            ELSE 0
        END AS IPM_QTY, 
        NOW(6) AS CREATE_AT 
    FROM purchases_products PUP
    LEFT JOIN purchases_return_products PRP 
           ON PUP.ORDER_ID = PRP.ORDER_ID 
          AND PUP.PRO_CODE = PRP.PRO_CODE
    WHERE PUP.ORDER_ID = p_order_id;

    SET v_row_count = ROW_COUNT();
    IF v_row_count = 0 THEN
        SET v_message = CONCAT('❌ لم يتم حفظ تفاصيل منتجات حركة المخزون لأمر الشراء رقم: ', p_order_id);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;

    -- --------------------------------------------------------------
    --  تحديث كميات المخزون بعد الحركة
    -- --------------------------------------------------------------
    CALL update_stores(v_result);

    IF v_result = 0 THEN
        SET v_message = CONCAT('❌ فشل في تحديث كميات المخزون للحركة رقم: ', v_last_id);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;
    
    -- --------------------------------------------------------------
    --  الغاء الاقفال
    -- --------------------------------------------------------------
    SELECT RELEASE_LOCK('inventory_global_lock');  -- 🔓 تحرير Lock المخزون
    
    -- --------------------------------------------------------------
    --  إرجاع النتيجة النهائية
    -- --------------------------------------------------------------
    SET p_return = v_result;

END$$

DELIMITER ;


/*===============================================================
 * # الإجراء: inventory_movement_sales_order
 * ------------------------------------------
 * # الغرض:
 *   إدارة حركات المخزون الخاصة بأوامر البيع والتسليم والمرتجعات
 *   من خلال تسجيل حركات المخزون وتحديث أرصدة المخازن تلقائياً
 * ------------------------------------------
 * # المعاملات:
 *   IN  p_session_id       → معرف الجلسة (SESSION_ID) 
 *   IN  p_movement_type    → نوع حركة المخزون
 *   IN  p_user_action_id   → رقم المستخدم المنفذ للعملية
 *   IN  p_store_id         → رقم المخزن المستهدف
 *   OUT p_return           → رمز النتيجة (1 = نجاح, 0 = فشل)
 * ------------------------------------------
 * # أنواع الحركات المدعومة:
 *   SALES        → صرف منتجات أمر البيع المباشر
 *   SHIPPING     → صرف منتجات أمر البيع للمندوب  
 *   REFUND       → استرداد منتجات أمر البيع من المندوب
 *   RETURN_SHIPP → استلام المنتجات المرفوضة من العميل
 *   RETURN_SALES → استلام مرتجعات بعد البيع من العميل
 * ------------------------------------------
 * # المنطق:
 *   1 - جلب بيانات الفرع التابع للمستخدم وأرقام الأوامر
 *   2 - تحديد اتجاه الحركة (IN/OUT) والنص الوصفي
 *   3 - التحقق من صحة نوع الحركة المدخل
 *   4 - تسجيل سجل رئيسي لحركة المخزون
 *   5 - تسجيل المنتجات المرتبطة بالحركة
 *   6 - تحديث أرصدة المخازن عبر الإجراء update_stores
 *   7 - التحقق من نجاح كل خطوة وإرجاع النتيجة
 * ------------------------------------------
 * # الجداول المستخدمة:
 *   ## 1 confirm_staging
 *      - جدول مؤقت لتخزين نتائج جلسة التأكيد قبل التنفيذ الفعلي
 *      - يحتوي على معلومات الأوامر والمنتجات المراد معالجتها
 *
 *   ## 2 users
 *      - جدول المستخدمين لاستخراج بيانات الفرع التابع للمستخدم
 *
 *   ## 3 inventory_movement
 *      - جدول الحركات الرئيسية للمخزون
 *      - IM_MOVE_TYPE    → نوع الحركة
 *      - IM_USER         → المستخدم المنفذ
 *      - IM_BRANCH       → الفرع التابع
 *      - STO_ID          → المخزن المستهدف
 *      - REFERENCE       → رقم مرجعي فريد
 *      - IM_DETAILS      → تفاصيل الحركة (أرقام الأوامر)
 *      - IM_APPROVAL_DISBURSED → موافقة الصرف (1 = معتمدة)
 *      - IM_APPROVAL_RECEIVED  → موافقة الاستلام (1 = معتمدة)
 *
 *   ## 4 inventory_product_movement
 *      - جدول تفاصيل منتجات الحركة
 *      - IM_ID          → رقم الحركة الرئيسية
 *      - IPM_TYPE_MOV   → نوع الحركة (IN/OUT)
 *      - IPM_CODE       → كود المنتج
 *      - IPM_STO_QTY    → الكمية المتاحة في المخزن قبل الحركة
 *      - IPM_QTY        → الكمية المنقولة في الحركة
 *      - CREATE_AT      → تاريخ الإنشاء
 *
 *   ## 5 order_product
 *      - جدول منتجات الأوامر
 *      - PRO_CODE       → كود المنتج
 *      - QTY            → الكمية المطلوبة
 *      - DELIVERED_QTY  → الكمية المسلمة
 *      - RETURNED_QTY   → الكمية المرتجعة
 *
 *   ## 6 stores_product
 *      - جدول أرصدة المنتجات في المخازن
 *      - STO_ID         → رقم المخزن
 *      - PRO_CODE       → كود المنتج
 *      - STO_QTY        → الكمية المتاحة
 * ------------------------------------------
 * # شروط تحديد الكمية المنقولة:
 *   ## لحركات الصرف (OUT):
 *      - SHIPPING, SALES, REFUND → الكمية الكاملة (QTY)
 *
 *   ## لحركات الاستلام (IN):
 *      - RETURN_SHIPP → الكمية المتبقية (QTY - DELIVERED_QTY)
 *      - RETURN_SALES → الكمية المرتجعة (RETURNED_QTY)
 * ------------------------------------------
 * # قواعد تحديد اتجاه الحركة:
 *   ## حركات الدخول (IN):
 *      - REFUND, RETURN_SHIPP, RETURN_SALES
 *
 *   ## حركات الخروج (OUT):
 *      - SALES, SHIPPING
 * ------------------------------------------
 * # آلية إنشاء الرقم المرجعي:
 *   [USER_ID][YYMMDDHHMMSS][3 أرقام عشوائية]
 *   مثال: 150231120000123
 * ------------------------------------------
 * # حالات الخطأ:
 *   - SQLSTATE '45000' → نوع حركة غير معروف
 *   - SQLSTATE '45000' → فشل حفظ سجل الحركة الرئيسي
 *   - SQLSTATE '45000' → فشل حفظ سجلات المنتجات
 *   - SQLSTATE '45000' → فشل تحديث المخزون
 * ------------------------------------------
 * # التبعيات:
 *   - الإجراء update_stores → لتحديث أرصدة المخازن
 * ------------------------------------------
 * # المخرجات:
 *   - p_return → 1 في حالة النجاح, 0 في حالة الفشل
 * ------------------------------------------
 * # المبرمج: [محمود رضا]
 * # تاريخ الإنشاء: [23/11/2025]
 * ------------------------------------------
 * # ملاحظات:
 *   - يجب أن تكون بيانات confirm_staging جاهزة قبل الاستدعاء
 *   - الإجراء يستخدم معالج أخطاء عام (SQLEXCEPTION)
 *   - يتم التحقق من صحة البيانات في كل مرحلة
 *   - الإجراء يدعم معالجة أوامر متعددة في جلسة واحدة
 *   - يتم إنشاء رقم مرجعي فريد لكل حركة
 *   - جميع الحركات معتمدة تلقائياً (موافقة مسبقة)
 * ===============================================================*/
 
DROP PROCEDURE IF EXISTS inventory_movement_sales_order;

DELIMITER $$

CREATE PROCEDURE inventory_movement_sales_order(
       IN p_session_id VARCHAR(64),
       IN p_movement_type ENUM('SALES','SHIPPING','REFUND','RETURN_SHIPP','RETURN_SALES','RETURN_SHIPP_SALSE'),
       IN p_user_action_id INT,
       IN p_store_id INT,
       OUT p_return INT
)
BEGIN
    --  تعريف المتغيرات
    DECLARE v_lock_acquired INT DEFAULT 0;       -- حالة الحصول على Lock
    DECLARE v_release_acquired INT DEFAULT 0;  
    DECLARE v_orders_id TEXT DEFAULT NULL;
    DECLARE v_branch_id INT DEFAULT 0;
    
    DECLARE v_last_id BIGINT DEFAULT NULL; 
    DECLARE v_message VARCHAR(255);                      
    DECLARE v_result INT DEFAULT 0;                      
    DECLARE v_row_count INT DEFAULT 0;                   
    DECLARE v_type ENUM('IN','OUT') DEFAULT NULL;        
    DECLARE v_movstr TEXT DEFAULT NULL;                 
    --  معالج الأخطاء العامة
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SELECT RELEASE_LOCK('inventory_global_lock');  -- 🔓 تحرير Lock المخزون
        RESIGNAL;
    END;
    
    -- --------------------------------------------------------------
    --  الخطوة 1: تطبيق Lock مركزي على المخزون
    -- --------------------------------------------------------------
    SELECT GET_LOCK('inventory_global_lock', 3) INTO v_lock_acquired;
    IF v_lock_acquired != 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '🔧 النظام يقوم بتحديث المخزون حالياً الرجاء المحاولة مرة أخرى بعد 5-10  ثواني للاستفسار، اتصل بالدعم الفني';
    END IF;
    
    --  التصحيح 1: جلب ID الفرع التابع للمستخدم
    SELECT BRANCH_ID INTO v_branch_id FROM users WHERE USER_ID = p_user_action_id;
    
    --  التصحيح 2: ارقام اوامر البيع 
    SELECT GROUP_CONCAT(DISTINCT ORDER_ID SEPARATOR ', ') INTO v_orders_id 
    FROM confirm_staging 
    WHERE SESSION_ID = p_session_id;

    -- --------------------------------------------------------------
    --  تحديد نوع الحركة (IN / OUT) والنص الوصفي
    -- --------------------------------------------------------------
    SET v_type = CASE 
                    WHEN p_movement_type IN ('REFUND','RETURN_SHIPP','RETURN_SALES','RETURN_SHIPP_SALSE') THEN 'IN'
                    WHEN p_movement_type IN ('SALES','SHIPPING') THEN 'OUT'
                    ELSE NULL
                 END;

    SET v_movstr = CASE
                      WHEN p_movement_type = 'SALES'         THEN 'صرف منتجات أمر البيع المباشر'
                      WHEN p_movement_type = 'SHIPPING'      THEN 'صرف منتجات أمر البيع للمندوب'
                      WHEN p_movement_type = 'REFUND'        THEN 'استرداد منتجات أمر البيع من المندوب'
                      WHEN p_movement_type = 'RETURN_SHIPP'  THEN 'استلام المنتجات المرفوضة من العميل'
                      WHEN p_movement_type = 'RETURN_SALES'  THEN 'استلام مرتجعات بعد البيع من العميل'
                      WHEN p_movement_type = 'RETURN_SHIPP_SALSE'  THEN 'استلام مرفوضات التسليم ومرتجعات بعد البيع من العميل'
                      ELSE NULL
                   END;

    IF v_type IS NULL OR v_movstr IS NULL THEN
        SET v_message = CONCAT('❌ غير قادر على التعرف على نوع حركة المخزون: ', p_movement_type);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;

    -- --------------------------------------------------------------
    --  تسجيل حركة المخزون
    -- --------------------------------------------------------------
    INSERT INTO inventory_movement (
        IM_MOVE_TYPE, IM_USER, IM_BRANCH, STO_ID, REFERENCE, 
        IM_DETAILS, IM_APPROVAL_DISBURSED, IM_APPROVAL_RECEIVED
    )
    VALUES (
        p_movement_type, 
        p_user_action_id, 
        v_branch_id, 
        p_store_id, 
        CONCAT(p_user_action_id, DATE_FORMAT(NOW(), '%y%m%d%H%i%s'), LPAD(FLOOR(RAND() * 1000), 3, '0')), 
        v_orders_id, 
        1, 
        1
    );

    SET v_last_id = LAST_INSERT_ID();
    IF v_last_id IS NULL OR v_last_id = 0 THEN
        SET v_message = CONCAT('❌ لم يتم حفظ سجل حركة المخزون: ', p_movement_type,v_orders_id);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;

    -- --------------------------------------------------------------
    --  تسجيل منتجات الحركة
    -- --------------------------------------------------------------
    INSERT INTO inventory_product_movement (
        IM_ID, ORDER_ID, IPM_TYPE_MOV, IPM_CODE, IPM_STO_QTY, IPM_QTY, CREATE_AT
    )
    SELECT
     v_last_id AS IM_ID,
     op.ORDER_ID,
     v_type AS IPM_TYPE_MOV,
     op.PRO_CODE AS IPM_CODE,
     COALESCE(sp.STO_QTY, 0) AS IPM_STO_QTY,
     CASE
        WHEN o.ORDER_STATUS = 2 AND p_movement_type IN ('SHIPPING', 'SALES', 'REFUND') THEN op.QTY
        WHEN o.ORDER_STATUS IN (6,7) AND op.PRO_STATUSE IN ('partial_refund', 'refund') AND p_movement_type IN ('RETURN_SALES', 'RETURN_SHIPP_SALSE') THEN COALESCE(op.RETURNED_QTY, 0)
        WHEN o.ORDER_STATUS IN (4,5) AND op.PRO_STATUSE IN ('partial', 'rejected') AND p_movement_type IN ('RETURN_SHIPP', 'RETURN_SHIPP_SALSE') THEN GREATEST(COALESCE(op.QTY, 0) - COALESCE(op.DELIVERED_QTY, 0), 0)
        ELSE 0
     END AS IPM_QTY,
     NOW(6) AS CREATE_AT
    FROM order_product op
    INNER JOIN orders o ON o.ORDER_ID = op.ORDER_ID
    LEFT JOIN stores_product sp ON op.PRO_CODE = sp.PRO_CODE AND sp.STO_ID = p_store_id
    WHERE op.ORDER_ID IN (SELECT DISTINCT ORDER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id)
     AND CASE
        WHEN o.ORDER_STATUS = 2 AND p_movement_type IN ('SHIPPING', 'SALES', 'REFUND') THEN op.QTY
        WHEN o.ORDER_STATUS IN (6,7) AND op.PRO_STATUSE IN ('partial_refund', 'refund') AND p_movement_type IN ('RETURN_SALES', 'RETURN_SHIPP_SALSE') THEN COALESCE(op.RETURNED_QTY, 0)
        WHEN o.ORDER_STATUS IN (4,5) AND op.PRO_STATUSE IN ('partial', 'rejected') AND p_movement_type IN ('RETURN_SHIPP', 'RETURN_SHIPP_SALSE') THEN GREATEST(COALESCE(op.QTY, 0) - COALESCE(op.DELIVERED_QTY, 0), 0)
        ELSE 0
     END > 0;
    
    
         
    SET v_row_count = ROW_COUNT();
    IF v_row_count = 0 THEN
        SET v_message = CONCAT('❌ لم يتم حفظ سجلات منتجات حركة المخزون: ', p_movement_type);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;

    -- --------------------------------------------------------------
    --  تحديث المخزون عبر الإجراء update_stores
    -- --------------------------------------------------------------
    CALL update_stores(v_result);

    IF v_result = 0 THEN
        SET v_message = CONCAT('❌ فشل تحديث المخزون للحركة رقم: ', v_last_id);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;

-- --------------------------------------------------------------
--  اضافة  Logorders
-- --------------------------------------------------------------

INSERT INTO orders_log (`ORDER_ID`, `USER_ACTION`, `LOG_TEXT`, `DATE_ACTION`) SELECT ORDER_ID,p_user_action_id AS USER_ACTION,'تم تسوية امر البيع مخزنيا' AS LOG_TEXT,NOW(6) AS DATE_ACTION FROM orders WHERE  ORDER_ID IN (SELECT DISTINCT ORDER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id);

    
    -- --------------------------------------------------------------
    --  تحرير الـ Lock
    -- --------------------------------------------------------------
    SELECT RELEASE_LOCK('inventory_global_lock') INTO v_release_acquired ;  -- 🔓 تحرير Lock المخزون
    IF v_release_acquired = 0 THEN
        SIGNAL SQLSTATE '45503' SET MESSAGE_TEXT = '🔧 النظام يقوم بتحديث المخزون حالياً الرجاء المحاولة مرة أخرى بعد 5-10  ثواني للاستفسار، اتصل بالدعم الفني';
    END IF;
    -- --------------------------------------------------------------
    --  إرجاع النتيجة النهائية
    -- --------------------------------------------------------------
    SET p_return = v_result;

END$$

DELIMITER ;


/*===============================================================
 * # الإجراء:set_inventory_movement_orders
 * ------------------------------------------
 * # الغرض:
 *   داله وسيطه لاستخدام دالة -> inventory_movement_purchases_orders
 *   لتحويل OUTPUT الى RESULTS لاستخدمها من php مباشرتا
 * ------------------------------------------
 * # المعاملات:
 *   IN  p_order_id        → رقم أمر الشراء
 *   IN  p_partner_id      → رقم المورد
 *   IN  p_user_action_id  → رقم المستخدم المنفذ للعملية
 *   IN  p_branch_id       → رقم الفرع
 *   IN  p_store_id        → رقم المخزن المستهدف
 *   IN  p_movement_type   → نوع حركة المخزون
 *   OUT p_return          → رمز النتيجة (1 = نجاح, 0 = فشل)
 * ------------------------------------------
 * # أنواع الحركات المدعومة:
 *   RECEIVING       → استلام منتجات أمر الشراء من المورد
 *   REFUNDPURCHISES → صرف مرتجعات أمر الشراء إلى المورد
 * ------------------------------------------
 * # المخرجات:
 *   - SQLSTATE :
 *     - column 1  →  statuse  (success or error)
 *     - column 2  →  statuse  (message)
 *     - column 3  →  error_code
 * ------------------------------------------
 * # المبرمج: [محمود رضا]
 * # تاريخ الإنشاء: [23/11/2025]
 * ===============================================================*/
 
DROP PROCEDURE IF EXISTS set_inventory_movement_orders;

DELIMITER $$

CREATE PROCEDURE set_inventory_movement_orders(
    IN p_order_id VARCHAR(70),
    IN p_partner_id INT,
    IN p_user_action_id INT,
    IN p_branch_id INT, 
    IN p_store_id INT,
    IN p_movement_type ENUM('RECEIVING', 'REFUNDPURCHISES','SALES','SHIPPING','REFUND','RETURN_SHIPP','RETURN_SALES','RETURN_SHIPP_SALSE')
    
)
proc_exit: BEGIN
    DECLARE v_result INT DEFAULT 0;    
    DECLARE v_type_text VARCHAR(50);
    
    SET v_type_text = CASE WHEN p_movement_type = 'RECEIVING'          THEN 'استلام المنتجات من المورد' 
                           WHEN p_movement_type = 'REFUNDPURCHISES'    THEN 'صرف مرتجعات المنتجات للمورد'
                           WHEN p_movement_type = 'SALES'              THEN 'صرف منتجات أمر البيع المباشر'
                           WHEN p_movement_type = 'SHIPPING'           THEN 'صرف منتجات أمر البيع للمندوب'
                           WHEN p_movement_type = 'REFUND'             THEN 'استرداد منتجات أمر البيع من المندوب'
                           WHEN p_movement_type = 'RETURN_SHIPP'       THEN 'استلام المنتجات المرفوضة من العميل'
                           WHEN p_movement_type = 'RETURN_SALES'       THEN 'استلام مرتجعات بعد البيع من العميل'
                           WHEN p_movement_type = 'RETURN_SHIPP_SALSE' THEN 'استلام مرفوضات التسليم ومرتجعات بعد البيع من العميل'
                           ELSE 'تنفيذ الاجراء نوع الطلب مجهول' 
                           END;
    
    -- --------------------------------------------------------------
    -- 1. استدعاء الداله الرئيسية
    -- --------------------------------------------------------------
    IF p_movement_type IN ('RECEIVING', 'REFUNDPURCHISES') THEN
        CALL inventory_movement_purchases_orders(p_order_id, p_partner_id, p_user_action_id, p_branch_id, p_store_id, p_movement_type, v_result);
    END IF;
    
    IF p_movement_type IN('SALES', 'SHIPPING','REFUND','RETURN_SHIPP','RETURN_SALES','RETURN_SHIPP_SALSE') THEN
        CALL inventory_movement_sales_order(p_order_id , p_movement_type, p_user_action_id, p_store_id, v_result);
    END IF;
    
    -- --------------------------------------------------------------
    -- 2. النتيجة النهائية
    -- --------------------------------------------------------------
    
    IF v_result <= 0  THEN
        SELECT 'error' AS Status, CONCAT('فشل فى ',v_type_text) AS message, 45450 AS error_code;
        LEAVE proc_exit;
    END IF;
    
    SELECT 'success' AS status, CONCAT('تم تأكيد ', v_type_text) AS message;
    
END$$

DELIMITER ;




/*

DELIMITER $$

CREATE PROCEDURE Inventory_movement_transfer(
    IN p_order_id INT,
    IN p_partner INT,
    IN p_delivery_id INT,
    IN p_movement_type VARCHAR(30),
    IN p_branch_id INT,
    IN p_fromsto INT,
    IN p_user_action INT,
    OUT p_return INT
)
BEGIN
    DECLARE v_type ENUM('IN', 'OUT') DEFAULT 'IN';
	DECLARE v_fromsto INT DEFAULT NULL;
	DECLARE v_movstr TEXT DEFAULT NULL;
    DECLARE v_last_id BIGINT DEFAULT NULL;
    DECLARE v_message VARCHAR(255);
    DECLARE v_row_count int DEFAULT 0;
	DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        IF p_use_transaction THEN
            ROLLBACK;
        END IF;
        RESIGNAL;
    END;
    
    
	SET v_type = CASE WHEN p_movement_type IN ('SETTLEMENT', 'RECEIVTRANSFER') THEN 'IN' 
					  WHEN p_movement_type = 'SENTTRANSFER') THEN	'OUT' 
					  ELSE NULL 
				 END;
	
	SET v_movstr = CASE -- WHEN p_movement_type = 'RECEIVING'			THEN 'استلام المنتجات اوامر الشراء من المورد'
						-- WHEN p_movement_type = 'REFUNDPURCHISES'		THEN 'صرف مرتجعات اوامر الشراء للمورد'
						-- WHEN p_movement_type = 'SALES'				THEN 'صرف منتجات امر البيع المباشر'
						-- WHEN p_movement_type = 'SHIPPING'			THEN 'صرف منتجات امر البيع للمندوب'
						-- WHEN p_movement_type = 'REFUND'				THEN 'استرداد منتجات امر البيع من المندوب'
						-- WHEN p_movement_type = 'RETURN_SHIPP'		THEN 'إستلام المنتجات المرفوضه بأمر البيع من المندوب'
						-- WHEN p_movement_type = 'RETURN_SALES'		THEN 'استلام مرتجعات بعد البيع '
						WHEN p_movement_type = 'SETTLEMENT' 		THEN 'تسويه مخزنية'
						WHEN p_movement_type = 'RECEIVTRANSFER' 	THEN 'تحويل داخلى (اضافة)'
						WHEN p_movement_type = 'SENTTRANSFER'		THEN 'تحويل داخلى (صرف)' 
											
						ELSE NULL 
					END;
					  
		
    -- تحديد مخزن المصدر
    IF p_fromsto IS NOT NULL THEN
        SET v_fromsto = p_fromsto;
    ELSE
        SELECT STO_ID INTO v_fromsto
        FROM STORES
        WHERE STTY_ID = 2 AND BRANCH_ID = p_branch_id
        LIMIT 1;
    END IF;
    
    IF v_fromsto IS NULL THEN
        SET v_message = CONCAT('❌ لا يوجد مخزن مصدر معرف للفرع رقم ', p_branch_id);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;

    -- سجل حركة المخزون
    INSERT INTO inventory_movement (IM_MOVE_TYPE, IM_USER, IM_BRANCH, STO_ID, STO_DEST, REFERENCE, IM_DETAILS, IM_APPROVAL_DISBURSED, IM_APPROVAL_RECEIVED) VALUES (p_movement_type, p_user_action, p_branch_id, v_fromsto, NULL, p_order_id,JSON_OBJECT('partner', p_partner, 'order_id', p_order_id),1, 1);
    
    SET v_last_id = LAST_INSERT_ID();
	
	IF v_last_id IS NULL OR v_last_id = 0 THEN
		SET v_message = CONCAT('❌ تم الغاء الاجراء لم يتم حفظ سجلات حركة المخزون ', p_branch_id);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;
    
    -- سجل منتجات الحركة
    INSERT INTO inventory_product_movement (IM_ID, IPM_TYPE_MOV, IPM_CODE, IPM_STO_QTY, IPM_QTY, CREATE_AT) SELECT v_last_id AS IM_ID, CASE WHEN p_movement_type IN ('return_shipp','return_sales') THEN 'IN' ELSE NULL END AS IPM_TYPE_MOV, op.PRO_CODE AS IPM_CODE, COALESCE(sp.STO_QTY, 0) AS IPM_STO_QTY, CASE  WHEN p_movement_type = 'return_shipp' THEN (op.QTY - IFNULL(op.DELIVERED_QTY,0)) WHEN p_movement_type = 'return_sales' THEN IFNULL(op.RETURNED_QTY,0) ELSE 0 END AS IPM_QTY, NOW(6) AS CREATE_AT FROM order_product op LEFT JOIN stores_product sp ON op.PRO_CODE = sp.PRO_CODE AND sp.STO_ID = v_fromsto WHERE op.ORDER_ID = p_order_id;
	SET v_row_count = ROW_COUNT();
	IF ROW_COUNT() = 0 THEN
		SET v_message = CONCAT('❌ تم الغاء الاجراء لم يتم حفظ سجلات منتجات حركة المخزون ', p_branch_id);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;

    -- تحديث مخزون المخزن
    UPDATE stores_product STP 
    JOIN ( 
    	SELECT 
    	IM.STO_ID, 
    	IPM.IPM_CODE, 
    	SUM(CASE 
    		WHEN IPM.IPM_TYPE_MOV = 'IN' THEN IPM.IPM_QTY 
    		WHEN IPM.IPM_TYPE_MOV = 'OUT' THEN -IPM.IPM_QTY 
    		ELSE 0 
    	END) AS TQTY 
    	FROM inventory_product_movement IPM 
    	JOIN inventory_movement IM ON IPM.IM_ID = IM.IM_ID 
    	WHERE IM.IM_ID = v_last_id 
    	GROUP BY IM.STO_ID, IPM.IPM_CODE) AS VSP 
    ON STP.STO_ID = VSP.STO_ID 
    AND STP.PRO_CODE = VSP.IPM_CODE 
    SET STP.STO_QTY = STP.STO_QTY + VSP.TQTY, STP.LAST_UPDATE = NOW(6);
    	
    SET v_row_count = ROW_COUNT();
	IF ROW_COUNT() = 0 THEN
		SET v_message = CONCAT('❌ تم الغاء الاجراء لم يتم تحديث الكميات للمخزون ', p_branch_id);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;
	
    -- إرجاع النتيجة
    set p_return = v_row_count;
    
END$$

DELIMITER ;


DELIMITER $$

CREATE PROCEDURE Inventory_movement_transfer(
    IN p_order_id INT,
    IN p_partner INT,
    IN p_delivery_id INT,
    -- IN p_movement_type VARCHAR(30),
    IN p_branch_id INT,
    IN p_fromsto INT,
    IN p_user_action INT,
    OUT p_return INT
)
BEGIN
    DECLARE v_type ENUM('IN', 'OUT') DEFAULT 'IN';
	DECLARE v_fromsto INT DEFAULT NULL;
	DECLARE v_movstr TEXT DEFAULT NULL;
    DECLARE v_last_id BIGINT DEFAULT NULL;
    DECLARE v_message VARCHAR(255);
    DECLARE v_row_count int DEFAULT 0;
	DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        IF p_use_transaction THEN
            ROLLBACK;
        END IF;
        RESIGNAL;
    END;
    
    
	SET v_type = 'IN';
	
	SET v_movstr =  'تسويه مخزنية';

    -- تحديد مخزن المصدر
    IF p_fromsto IS NOT NULL THEN
        SET v_fromsto = p_fromsto;
    ELSE
        SELECT STO_ID INTO v_fromsto
        FROM STORES
        WHERE STTY_ID = 2 AND BRANCH_ID = p_branch_id
        LIMIT 1;
    END IF;
    
    IF v_fromsto IS NULL THEN
        SET v_message = CONCAT('❌ لا يوجد مخزن مصدر معرف للفرع رقم ', p_branch_id);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;

    -- سجل حركة المخزون
    INSERT INTO inventory_movement (IM_MOVE_TYPE, IM_USER, IM_BRANCH, STO_ID, STO_DEST, REFERENCE, IM_DETAILS, IM_APPROVAL_DISBURSED, IM_APPROVAL_RECEIVED) VALUES (p_movement_type, p_user_action, p_branch_id, v_fromsto, NULL, p_order_id,JSON_OBJECT('partner', p_partner, 'order_id', p_order_id),1, 1);
    
    SET v_last_id = LAST_INSERT_ID();
	
	IF v_last_id IS NULL OR v_last_id = 0 THEN
		SET v_message = CONCAT('❌ تم الغاء الاجراء لم يتم حفظ سجلات حركة المخزون ', p_branch_id);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;
    
    -- سجل منتجات الحركة
    INSERT INTO inventory_product_movement (IM_ID, IPM_TYPE_MOV, IPM_CODE, IPM_STO_QTY, IPM_QTY, CREATE_AT) SELECT v_last_id AS IM_ID, CASE WHEN p_movement_type IN ('return_shipp','return_sales') THEN 'IN' ELSE NULL END AS IPM_TYPE_MOV, op.PRO_CODE AS IPM_CODE, COALESCE(sp.STO_QTY, 0) AS IPM_STO_QTY, CASE  WHEN p_movement_type = 'return_shipp' THEN (op.QTY - IFNULL(op.DELIVERED_QTY,0)) WHEN p_movement_type = 'return_sales' THEN IFNULL(op.RETURNED_QTY,0) ELSE 0 END AS IPM_QTY, NOW(6) AS CREATE_AT FROM order_product op LEFT JOIN stores_product sp ON op.PRO_CODE = sp.PRO_CODE AND sp.STO_ID = v_fromsto WHERE op.ORDER_ID = p_order_id;
	SET v_row_count = ROW_COUNT();
	IF ROW_COUNT() = 0 THEN
		SET v_message = CONCAT('❌ تم الغاء الاجراء لم يتم حفظ سجلات منتجات حركة المخزون ', p_branch_id);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;

    -- تحديث مخزون المخزن
    UPDATE stores_product STP 
    JOIN ( 
    	SELECT 
    	IM.STO_ID, 
    	IPM.IPM_CODE, 
    	SUM(CASE 
    		WHEN IPM.IPM_TYPE_MOV = 'IN' THEN IPM.IPM_QTY 
    		WHEN IPM.IPM_TYPE_MOV = 'OUT' THEN -IPM.IPM_QTY 
    		ELSE 0 
    	END) AS TQTY 
    	FROM inventory_product_movement IPM 
    	JOIN inventory_movement IM ON IPM.IM_ID = IM.IM_ID 
    	WHERE IM.IM_ID = v_last_id 
    	GROUP BY IM.STO_ID, IPM.IPM_CODE) AS VSP 
    ON STP.STO_ID = VSP.STO_ID 
    AND STP.PRO_CODE = VSP.IPM_CODE 
    SET STP.STO_QTY = STP.STO_QTY + VSP.TQTY, STP.LAST_UPDATE = NOW(6);
    	
    SET v_row_count = ROW_COUNT();
	IF ROW_COUNT() = 0 THEN
		SET v_message = CONCAT('❌ تم الغاء الاجراء لم يتم تحديث الكميات للمخزون ', p_branch_id);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;
	
    -- إرجاع النتيجة
    set p_return = v_row_count;
    
END$$

DELIMITER ;

*/