DROP PROCEDURE IF EXISTS inventory_movement_sales_order;
DELIMITER $$
CREATE DEFINER=`justadmin2`@`%` PROCEDURE `inventory_movement_sales_order`(
	IN `p_session_id` VARCHAR(64), 
	IN `p_movement_type` ENUM('SALES','SHIPPING','REFUND','RETURN_SHIPP','RETURN_SALES','RETURN_SHIPP_SALSE'), 
	IN `p_user_action_id` INT, 
	IN `p_store_id` INT, 
	OUT `p_return` INT)
proc_exit : BEGIN
    DECLARE v_lock_acquired INT DEFAULT 0;       
    DECLARE v_release_acquired INT DEFAULT 0;  
    DECLARE v_orders_id TEXT DEFAULT NULL;
    DECLARE v_branch_id INT DEFAULT 0;
    DECLARE v_last_id BIGINT DEFAULT NULL; 
    DECLARE v_message VARCHAR(255);                      
    DECLARE v_result INT DEFAULT 0;                      
    DECLARE v_row_count INT DEFAULT 0;                   
    DECLARE v_type ENUM('IN','OUT') DEFAULT NULL;        
    DECLARE v_movstr TEXT DEFAULT NULL;                 
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SELECT RELEASE_LOCK('inventory_global_lock');  
        RESIGNAL;
    END;

    SELECT GET_LOCK('inventory_global_lock', 3) INTO v_lock_acquired;
    IF v_lock_acquired != 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '🔧 النظام يقوم بتحديث المخزون حالياً الرجاء المحاولة مرة أخرى بعد 5-10  ثواني للاستفسار، اتصل بالدعم الفني';
    	LEAVE proc_exit;
    END IF;

    SELECT BRANCH_ID INTO v_branch_id FROM users WHERE USER_ID = p_user_action_id;

    SELECT GROUP_CONCAT(DISTINCT ORDER_ID SEPARATOR ', ') INTO v_orders_id 
    FROM confirm_staging 
    WHERE SESSION_ID = p_session_id;

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
                      WHEN p_movement_type = 'RETURN_SHIPP_SALSE'  THEN 'تم تسوية امر البيع مخزنيا'
                      ELSE NULL
                   END;
                   
    IF v_type IS NULL OR v_movstr IS NULL THEN
        SET v_message = CONCAT('❌ غير قادر على التعرف على نوع حركة المخزون: ', p_movement_type);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    	LEAVE proc_exit;
    END IF;

    INSERT INTO inventory_movement
	(
		IM_MOVE_TYPE,
		IM_USER,
		IM_BRANCH,
		STO_ID,
		REFERENCE,
		UNIQUE_ID,
		IM_DETAILS,
		IM_APPROVAL_DISBURSED,
		IM_APPROVAL_RECEIVED
	)
	SELECT
		p_movement_type,
		p_user_action_id,
		v_branch_id,
		p_store_id,
		s.ORDER_ID,
		p_session_id,
		v_movstr,
		1,
		1
	FROM (
		SELECT DISTINCT ORDER_ID
		FROM confirm_staging
		WHERE SESSION_ID = p_session_id
	) s;

    SET v_row_count = ROW_COUNT();

	IF v_row_count = 0 THEN
        SET v_message = CONCAT('❌ لم يتم حفظ سجل حركة المخزون: ', p_movement_type,v_orders_id);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    	LEAVE proc_exit;
    END IF;

	INSERT INTO inventory_product_movement (IM_ID, ORDER_ID, IPM_TYPE_MOV, IPM_CODE, IPM_STO_QTY, IPM_QTY, CREATE_AT)
	SELECT im.IM_ID, 
	        op.ORDER_ID, 
		    v_type, 
		    op.PRO_CODE,
		    CASE 
			    WHEN v_type = 'IN' THEN
				COALESCE(sp.STO_QTY,0) + COALESCE(
							SUM(
								CASE 
									WHEN im.IM_MOVE_TYPE IN ('SALES','SHIPPING','REFUND') THEN op.QTY
									WHEN im.IM_MOVE_TYPE='RETURN_SHIPP' THEN op.QTY - op.DELIVERED_QTY
									WHEN im.IM_MOVE_TYPE='RETURN_SALES' THEN op.RETURNED_QTY
									ELSE 0
								END
							) OVER(PARTITION BY op.PRO_CODE ORDER BY im.IM_ID ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING),0
					  )
				ELSE 
				COALESCE(sp.STO_QTY,0) - COALESCE(
							SUM(
								CASE 
									WHEN im.IM_MOVE_TYPE IN ('SALES','SHIPPING','REFUND') THEN op.QTY
									WHEN im.IM_MOVE_TYPE='RETURN_SHIPP' THEN op.QTY - op.DELIVERED_QTY
									WHEN im.IM_MOVE_TYPE='RETURN_SALES' THEN op.RETURNED_QTY
									ELSE 0
								END
							) OVER(PARTITION BY op.PRO_CODE ORDER BY im.IM_ID ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING),0
					  )
		    END,
	        CASE 
	            WHEN im.IM_MOVE_TYPE IN ('SALES','SHIPPING','REFUND') THEN op.QTY
	            WHEN im.IM_MOVE_TYPE='RETURN_SHIPP' THEN op.QTY - op.DELIVERED_QTY
	            WHEN im.IM_MOVE_TYPE='RETURN_SALES' THEN op.RETURNED_QTY
	            ELSE 0
	        END,
	        NOW(6)
	FROM order_product op
	INNER JOIN orders o ON o.ORDER_ID = op.ORDER_ID
	INNER JOIN inventory_movement im ON im.REFERENCE = o.ORDER_ID AND im.UNIQUE_ID = p_session_id AND im.IM_MOVE_TYPE = p_movement_type
	LEFT JOIN stores_product sp ON sp.PRO_CODE = op.PRO_CODE AND sp.STO_ID = p_store_id
	WHERE op.ORDER_ID IN (SELECT DISTINCT ORDER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id)
	    AND (im.IM_MOVE_TYPE IN ('SALES','SHIPPING','REFUND') OR (im.IM_MOVE_TYPE = 'RETURN_SHIPP' AND op.PRO_STATUSE IN ('rejected','partial')) OR (im.IM_MOVE_TYPE = 'RETURN_SALES' AND op.PRO_STATUSE IN ('partial_refund','refund')));

    SET v_row_count = ROW_COUNT();
    
    IF v_row_count = 0 THEN
        SET v_message = CONCAT('❌ لم يتم حفظ سجلات منتجات حركة المخزون: ', p_movement_type);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    	LEAVE proc_exit;
    END IF;

    CALL update_stores(v_result);

    IF v_result = 0 THEN

        SET v_message = CONCAT('❌ فشل تحديث المخزون للحركة رقم: ', v_last_id);

        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
		LEAVE proc_exit;
    END IF;
    
    SELECT RELEASE_LOCK('inventory_global_lock') INTO v_release_acquired ;  

    IF v_release_acquired = 0 THEN

        SIGNAL SQLSTATE '45503' SET MESSAGE_TEXT = '🔧 النظام يقوم بتحديث المخزون حالياً الرجاء المحاولة مرة أخرى بعد 5-10  ثواني للاستفسار، اتصل بالدعم الفني';
		LEAVE proc_exit;
    END IF;

    SET p_return = v_result;

END$$
DELIMITER ;