/*===============================================================
 * # الإجراء: setRetunedConfirmationOptimized
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
DROP PROCEDURE IF EXISTS setRetunedConfirmationOptimized;
DELIMITER $$
CREATE DEFINER=`justadmin2`@`%` PROCEDURE `setRetunedConfirmationOptimized`(
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
        o.LAST_UPDATE = NOW(3);
        
    
    SET v_rows = ROW_COUNT();
    
    IF v_total_orders != v_rows THEN
        SET v_message = CONCAT('لم يتم تحديث أي امر بيع. المتوقع: ', v_total_orders, ', المحدث: ', v_rows);
        SELECT 'error' AS status, v_message AS message, 45103 AS error_code;
        LEAVE proc_exit;
    END IF;
    
	-- --------------------------------------------------------------
    -- 6. تحديث أرصدة العملاء دفعة واحدة
    -- --------------------------------------------------------------
   /* IF v_total_orders_selse > 0 THEN
        CALL restClientDebtBatch(p_session_id, @error_code, @status, @message);
        
        IF @status = 'error' THEN
            SELECT @status AS status, @message AS message, @error_code AS error_code;
            LEAVE proc_exit;
        END IF;
    END IF;*/
    
    IF v_total_orders_refund > 0 THEN
        CALL reduceClientCreditBatch(p_session_id, @error_code, @status, @message);
        IF @status = 'error' THEN
            SELECT @status AS status, @message AS message, @error_code AS error_code;
            LEAVE proc_exit;
        END IF;
    END IF;
	

    -- --------------------------------------------------------------
    -- 9. تحديث مخزون المنتجات 
    -- --------------------------------------------------------------
    IF p_update_inventory = 'true' THEN
        /*IF v_total_orders_selse > 0 THEN
		    CALL inventory_movement_sales_order(p_session_id, 'RETURN_SHIPP', p_executed_by, v_store_id, v_inv_result_a);
			IF v_inv_result_a <= 0  THEN
				SELECT 'error' AS Status, 'فشل فى تحديث المخزون' AS message, 45450 AS error_code;
				LEAVE proc_exit;
			END IF;
        END IF;*/
		
        IF v_total_orders_refund > 0 THEN
		    CALL inventory_movement_sales_order(p_session_id, 'RETURN_SALES', p_executed_by, v_store_id, v_inv_result_a);
			IF v_inv_result_a <= 0  THEN
				SELECT 'error' AS Status, 'فشل فى تحديث المخزون' AS message, 45450 AS error_code;
				LEAVE proc_exit;
			END IF;
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
    -- 10. النتيجة النهائية
    -- --------------------------------------------------------------
    SELECT 'success' AS status, 
           CONCAT('تم تأكيد تسليم الشحنة ', v_total_orders, ' أمر بنجاح (', 
                  v_total_orders_selse, ' مبيعات, ', 
                  v_total_orders_refund, ' مرتجعات)') AS message;
           
END$$
DELIMITER ;