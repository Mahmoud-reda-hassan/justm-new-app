DROP PROCEDURE IF EXISTS shipping_movement;
DELIMITER $$
CREATE DEFINER=`justadmin2`@`%` PROCEDURE `shipping_movement`(IN `p_session_id` VARCHAR(64), IN `p_executed_by` INT, OUT `p_error_code` INT, OUT `p_status` VARCHAR(10), OUT `p_message` TEXT)
proc_exit: BEGIN

    DECLARE v_total_orders INT DEFAULT 0;
    DECLARE v_inserted_rows INT DEFAULT 0;
    DECLARE v_null_count INT DEFAULT 0;

    -- --------------------------------------------------------------
    -- 1. التحقق من وجود بيانات
    -- --------------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM confirm_staging WHERE SESSION_ID = p_session_id
    ) THEN
        SET p_status = 'error';
        SET p_message = 'لا توجد بيانات لتأكيد التسليم';
        SET p_error_code = 45111;
        LEAVE proc_exit;
    END IF;

    -- عدد الأوامر
    SELECT COUNT(DISTINCT ORDER_ID)
    INTO v_total_orders
    FROM confirm_staging
    WHERE SESSION_ID = p_session_id;

    IF v_total_orders = 0 THEN
        SET p_status = 'error';
        SET p_message = 'لا توجد أوامر في الجلسة المحددة';
        SET p_error_code = 45112;
        LEAVE proc_exit;
    END IF;

    -- --------------------------------------------------------------
    -- 2. التحقق من وجود movement_type = NULL
    -- --------------------------------------------------------------
    SELECT COUNT(*)
    INTO v_null_count
    FROM (
        -- MAIN MOVEMENTS
        SELECT DISTINCT
			p_session_id AS UNIQUE_ID,
            CASE
                WHEN cs.TYPE = 'SHIPPING' AND o.EXISTS_ID = 10 THEN 0
                WHEN cs.TYPE = 'FREEZE'   AND o.EXISTS_ID = 10 THEN 1
                WHEN cs.TYPE = 'DEFREEZE' AND o.EXISTS_ID = 10 THEN 2
				WHEN cs.TYPE = 'SELSE'    AND o.EXISTS_ID = 11 AND o.ORDER_STATUS IN (3,4,5) THEN o.ORDER_STATUS
				WHEN cs.TYPE = 'REFUND'   AND o.EXISTS_ID = 10 AND o.ORDER_STATUS = 6 THEN 8
				WHEN cs.TYPE = 'REFUND'   AND o.EXISTS_ID = 10 AND o.ORDER_STATUS = 7 THEN 9
                WHEN cs.TYPE = 'REFUND'   AND o.EXISTS_ID = 10 AND cro.ORDER_STATUSE IN (3,4,5) THEN 10
                ELSE NULL
            END AS movement_type,
            CASE
                WHEN o.EXISTS_ID = 11 THEN p_executed_by
                ELSE cs.USER_ID
            END AS delivery_id,
            p_executed_by AS user_id,
            o.ORDER_ID AS shipment_orders
        FROM confirm_staging cs
        JOIN orders o ON o.ORDER_ID = cs.ORDER_ID
        LEFT JOIN custody_return_orders cro ON cro.ORDER_ID = o.ORDER_ID AND cro.USER_ID = cs.USER_ID
        WHERE cs.SESSION_ID = p_session_id
        HAVING movement_type IS NOT NULL
        UNION ALL
        -- RETURN FROM CUSTODY ORDERS
        SELECT DISTINCT p_session_id AS UNIQUE_ID, 6 AS movement_type, co.USER_ID AS delivery_id, p_executed_by AS user_id, o.ORDER_ID  AS shipment_orders
        FROM confirm_staging cs
        JOIN orders o ON o.ORDER_ID = cs.ORDER_ID
        JOIN custody_orders co ON co.ORDER_ID = o.ORDER_ID AND co.USER_ID = cs.USER_ID 
        WHERE cs.SESSION_ID = p_session_id AND cs.TYPE = 'SELSE' AND o.EXISTS_ID = 10 AND co.ORDER_STATUSE IN (4,5) AND co.RETURNS = 1
        UNION ALL
        -- RETURN FROM REFUND ORDERS
        SELECT DISTINCT p_session_id AS UNIQUE_ID, 6 AS movement_type, cro.USER_ID AS delivery_id, p_executed_by AS user_id, o.ORDER_ID  AS shipment_orders
        FROM confirm_staging cs
        JOIN orders o ON o.ORDER_ID = cs.ORDER_ID
        JOIN custody_return_orders cro ON cro.ORDER_ID = o.ORDER_ID AND cro.USER_ID = cs.USER_ID
        WHERE cs.SESSION_ID = p_session_id AND cs.TYPE = 'REFUND' AND o.EXISTS_ID = 10 AND cro.CUST_STAT = 'RETURNED' AND cro.ORDER_STATUSE IN (6,7) AND cro.RETURNS = 1
    ) x
    WHERE x.movement_type IS NULL;

    IF v_null_count > 0 THEN
        SET p_status = 'error';
        SET p_message = CONCAT('يوجد ', v_null_count, ' سجلات غير صالحة للحركة');
        SET p_error_code = 45113;
        LEAVE proc_exit;
    END IF;

    -- --------------------------------------------------------------
    -- 3. INSERT SHIPPING MOVEMENT
    -- --------------------------------------------------------------
    INSERT INTO shipping_movement (
	    UNIQUE_ID,
        movement_type,
        delivery_id,
        user_id,
        shipment_orders
    )

    SELECT *
    FROM (
        -- MAIN MOVEMENTS
        SELECT DISTINCT
			p_session_id AS UNIQUE_ID,
            CASE
                WHEN cs.TYPE = 'SHIPPING' AND o.EXISTS_ID = 10 THEN 0
                WHEN cs.TYPE = 'FREEZE'   AND o.EXISTS_ID = 10 THEN 1
                WHEN cs.TYPE = 'DEFREEZE' AND o.EXISTS_ID = 10 THEN 2
				WHEN cs.TYPE = 'SELSE'    AND o.EXISTS_ID IN(10,11) AND o.ORDER_STATUS IN (3,4,5) THEN o.ORDER_STATUS
				WHEN cs.TYPE = 'REFUND'   AND o.EXISTS_ID = 10 AND o.ORDER_STATUS = 6 THEN 8
				WHEN cs.TYPE = 'REFUND'   AND o.EXISTS_ID = 10 AND o.ORDER_STATUS = 7 THEN 9
                WHEN cs.TYPE = 'REFUND'   AND o.EXISTS_ID = 10 AND cro.ORDER_STATUSE IN (3,4,5) THEN 10
                ELSE NULL
            END AS movement_type,
            CASE
                WHEN o.EXISTS_ID = 11 THEN p_executed_by
                ELSE cs.USER_ID
            END AS delivery_id,
            p_executed_by AS user_id,
            o.ORDER_ID AS shipment_orders
        FROM confirm_staging cs
        JOIN orders o ON o.ORDER_ID = cs.ORDER_ID
        LEFT JOIN custody_return_orders cro ON cro.ORDER_ID = o.ORDER_ID AND cro.USER_ID = cs.USER_ID
        WHERE cs.SESSION_ID = p_session_id
        HAVING movement_type IS NOT NULL
        UNION ALL
        -- RETURN FROM CUSTODY ORDERS
        SELECT DISTINCT p_session_id AS UNIQUE_ID, 6 AS movement_type, co.USER_ID AS delivery_id, p_executed_by AS user_id, o.ORDER_ID  AS shipment_orders
        FROM confirm_staging cs
        JOIN orders o ON o.ORDER_ID = cs.ORDER_ID
        JOIN custody_orders co ON co.ORDER_ID = o.ORDER_ID AND co.USER_ID = cs.USER_ID 
        WHERE cs.SESSION_ID = p_session_id AND cs.TYPE = 'SELSE' AND o.EXISTS_ID = 10 AND co.ORDER_STATUSE IN (4,5) AND co.RETURNS = 1
        UNION ALL
        -- RETURN FROM REFUND ORDERS
        SELECT DISTINCT p_session_id AS UNIQUE_ID, 6 AS movement_type, cro.USER_ID AS delivery_id, p_executed_by AS user_id, o.ORDER_ID  AS shipment_orders
        FROM confirm_staging cs
        JOIN orders o ON o.ORDER_ID = cs.ORDER_ID
        JOIN custody_return_orders cro ON cro.ORDER_ID = o.ORDER_ID AND cro.USER_ID = cs.USER_ID
        WHERE cs.SESSION_ID = p_session_id AND cs.TYPE = 'REFUND' AND o.EXISTS_ID = 10 AND cro.CUST_STAT = 'RETURNED' AND cro.ORDER_STATUSE IN (6,7) AND cro.RETURNS = 1
    ) final_data;

    SET v_inserted_rows = ROW_COUNT();

    -- --------------------------------------------------------------
    -- 4. validation
    -- --------------------------------------------------------------
    IF v_inserted_rows = 0 THEN
        SET p_status = 'error';
        SET p_message = 'لم يتم إدخال أي حركات شحن';
        SET p_error_code = 45114;
        LEAVE proc_exit;
    END IF;

    SET p_status = 'success';
    SET p_message = CONCAT('تم إدخال ', v_inserted_rows, ' حركة شحن بنجاح');
    SET p_error_code = 0;

END$$
DELIMITER ;