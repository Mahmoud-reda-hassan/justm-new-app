-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 01, 2026 at 10:28 AM
-- Server version: 10.5.29-MariaDB
-- PHP Version: 8.4.27

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cptest`
--
CREATE DATABASE IF NOT EXISTS `cptest` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `cptest`;

DELIMITER $$
--
-- Procedures
--
CREATE PROCEDURE `confirm_orders_with_stock` (IN `p_session_id` TEXT, IN `p_user_action_id` INT, IN `p_statuse_id` INT, IN `p_store_id` INT, IN `p_last_update` DATETIME)   BEGIN

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

END$$

CREATE PROCEDURE `inventory_movement_purchases_orders` (IN `p_order_id` INT, IN `p_partner_id` INT, IN `p_user_action_id` INT, IN `p_branch_id` INT, IN `p_store_id` INT, IN `p_movement_type` ENUM('RECEIVING','REFUNDPURCHISES'), OUT `p_return` INT)  SQL SECURITY INVOKER BEGIN

    --  تعريف المتغيرات الداخلية

    DECLARE v_lock_acquired INT DEFAULT 0;       -- حالة الحصول على Lock

    DECLARE v_last_id BIGINT DEFAULT NULL;                -- رقم حركة المخزون بعد الإدخال

    DECLARE v_message VARCHAR(255);                       -- لتخزين الرسائل أو الأخطاء

    DECLARE v_result INT DEFAULT 0;                       -- ناتج استدعاء update_stores

    DECLARE v_row_count INT DEFAULT 0;                    -- عدد الصفوف التي تم إدخالها

    DECLARE v_movement_exists INT DEFAULT 0;               -- تحقق من وجود الحركة

    DECLARE v_type ENUM('IN', 'OUT') DEFAULT NULL;        -- نوع الحركة (إضافة أو صرف)

    DECLARE v_movstr TEXT DEFAULT NULL;                   -- نص وصفي للحركة

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

        IM_MOVE_TYPE, IM_USER, IM_BRANCH, STO_ID, REFERENCE, IM_DETAILS, 

        IM_APPROVAL_DISBURSED, IM_APPROVAL_RECEIVED

    ) 

    VALUES (

        p_movement_type, 

        p_user_action_id, 

        p_branch_id, 

        p_store_id, 

        p_order_id,

        JSON_OBJECT('partner', p_partner_id, 'NOTE', v_movstr),

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

        IM_ID, IPM_TYPE_MOV, IPM_CODE, IPM_QTY, CREATE_AT

    ) 

    SELECT 

        v_last_id AS IM_ID, 

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

CREATE PROCEDURE `inventory_movement_sales_order` (IN `p_session_id` VARCHAR(64), IN `p_movement_type` ENUM('SALES','SHIPPING','REFUND','RETURN_SHIPP','RETURN_SALES','RETURN_SHIPP_SALSE'), IN `p_user_action_id` INT, IN `p_store_id` INT, OUT `p_return` INT)   proc_exit: BEGIN
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

CREATE PROCEDURE `reduceClientCredit` (IN `p_order_id` INT, OUT `p_result` INT)  MODIFIES SQL DATA SQL SECURITY INVOKER BEGIN

    --  تعريف المتغيرات الداخلية

    DECLARE v_client_id INT;

    DECLARE v_order_status INT;

    DECLARE v_exists_id INT;

    DECLARE v_order_success INT;

    DECLARE v_total_order_price DECIMAL(12,2);

    DECLARE v_total_required_price DECIMAL(12,2);

    DECLARE v_total_paid_price DECIMAL(12,2);

    DECLARE v_total_old_return_price DECIMAL(12,2);

    DECLARE v_total_return_price DECIMAL(12,2);

    DECLARE v_message TEXT DEFAULT '';

    -- معالجة أي خطأ غير متوقع داخل الإجراء

    DECLARE EXIT HANDLER FOR SQLEXCEPTION

    BEGIN

        RESIGNAL; -- يُعيد إرسال الخطأ الأصلي إلى الخارج

    END;

    

    -- --------------------------------------------------------------

    -- 1 جلب قيمة المرتجع والرصيد الحالي (إن وجد)

    -- --------------------------------------------------------------

    SELECT

        A.CLIENT_ID,

        O.ORDER_STATUS,

        O.ORDER_SUCCESS,

        O.EXISTS_ID,

        E.BALANCE AS TOTAL_REQUIRED,

        C.BALANCE AS PAID,

        D.BALANCE AS REMAINING,

        SUM((OP.PRICE - OP.DISCOUNT) * OP.QTY) AS TOTAL_ORDER_PRICE,

        SUM((OP.PRICE - OP.DISCOUNT) * (CASE WHEN OP.PRO_STATUSE IN ('REFUND','partial_refund') THEN OP.RETURNED_QTY ELSE 0 END)) AS TOTAL_RETURN_ORDER

    INTO

        v_client_id,

        v_order_status,

        v_order_success,

        v_exists_id,

        v_total_required_price, -- قبل التحصيل

        v_total_paid_price, -- تم التحصيل

        v_total_old_return_price, -- المرتجع المسجل

        v_total_order_price, -- قيمة امر البيع

        v_total_return_price

    FROM orders O

        JOIN order_product OP ON O.ORDER_ID = OP.ORDER_ID

        JOIN addresses A ON O.ADDRESS_ID = A.ADDRESS_ID

        LEFT JOIN (

            SELECT * 

            FROM (

                SELECT *,

                    ROW_NUMBER() OVER (PARTITION BY REFERENCE_ID ORDER BY ID) AS rn

                FROM partners_balance

                WHERE TRANSACTION_CATEGORY_ID = 8

                AND PARTNER_TYPE = 'CLIENT'

                AND TRANSACTION_TYPE = 'DEBIT' 

            ) AS ranked

            WHERE rn = 1

            ) E ON E.REFERENCE_ID = O.ORDER_ID

        LEFT JOIN (

            SELECT * 

            FROM (

                SELECT *,

                    ROW_NUMBER() OVER (PARTITION BY REFERENCE_ID ORDER BY ID) AS rn

                FROM partners_balance

                WHERE TRANSACTION_CATEGORY_ID = 9

                AND PARTNER_TYPE = 'CLIENT'

                AND TRANSACTION_TYPE = 'CREDIT' 

            ) AS ranked

            WHERE rn = 1 

        ) C ON C.REFERENCE_ID = O.ORDER_ID

        LEFT JOIN (

            SELECT * 

            FROM (

                SELECT *,

                    ROW_NUMBER() OVER (PARTITION BY REFERENCE_ID ORDER BY ID) AS rn

                FROM partners_balance

                WHERE TRANSACTION_CATEGORY_ID = 14

                AND PARTNER_TYPE = 'CLIENT'

                AND TRANSACTION_TYPE = 'CREDIT' 

            ) AS ranked

            WHERE rn = 1

        ) D ON D.REFERENCE_ID = O.ORDER_ID

    WHERE 

        O.ORDER_ID = p_order_id 

    GROUP BY 

        A.CLIENT_ID, O.ORDER_ID, O.ORDER_STATUS, O.EXISTS_ID;

    -- --------------------------------------------------------------

    -- 2 تحقق من وجود بيانات مرتجع

    -- --------------------------------------------------------------

    IF v_order_success != 1 THEN

    	SET v_message = CONCAT('❌ لا يمكن تنفيذ الاجراء لهاذا الامر', p_order_id);

        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;

    END IF;

    

    IF v_total_paid_price <= v_total_return_price THEN

        SET v_message = CONCAT('❌ قيمة المرتجع اكبر من المدفوع', p_order_id);

        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;

    END IF;

    IF v_total_return_price IS NULL OR v_total_return_price = 0 THEN

        SET v_message = CONCAT('❌ قيمة المرتجع لابد ان تكون اكبر من صفر', p_order_id);

        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;

    END IF;

    -- --------------------------------------------------------------

    -- 3 تنفيذ العمليات حسب الحالة

    -- --------------------------------------------------------------

    -- الحالة 1: لا يوجد رصيد سابق، وهناك مرتجع ➜ إدراج سجل جديد

    IF v_total_old_return_price IS NULL  THEN

        

        INSERT INTO partners_balance (

            PARTNER_TYPE, PARTNER_ID, REFERENCE_ID, TRANSACTION_TYPE, BALANCE, NOTE, TRANSACTION_CATEGORY_ID, USE_OF_CREDIT

        ) VALUES (

            'CLIENT', v_client_id, p_order_id, 'CREDIT', v_total_return_price, 'رصيد دائن نتيجة إرجاع منتجات', 14, 1

        );

        SET p_result = ROW_COUNT();

    -- الحالة 2: يوجد رصيد سابق، وهناك مرتجع ➜ تحديث السجل

    ELSEIF v_total_old_return_price IS NOT NULL AND v_total_return_price > 0 THEN

        

        UPDATE partners_balance

        SET BALANCE = v_total_return_price

        WHERE

            REFERENCE_ID = p_order_id

            AND PARTNER_ID = v_client_id

            AND PARTNER_TYPE = 'CLIENT'

            AND TRANSACTION_TYPE = 'CREDIT'

            AND TRANSACTION_CATEGORY_ID = 14;

        SET p_result = ROW_COUNT();

    -- الحالة 3: لا يوجد مرتجع ➜ حذف السجل

    ELSEIF v_total_old_return_price IS NOT NULL  AND v_total_return_price = 0  THEN

        

        DELETE FROM partners_balance

        WHERE

            REFERENCE_ID = p_order_id

            AND PARTNER_ID = v_client_id

            AND PARTNER_TYPE = 'CLIENT'

            AND TRANSACTION_TYPE = 'CREDIT'

            AND TRANSACTION_CATEGORY_ID = 14;

        SET p_result = ROW_COUNT();

        

    ELSE 

        SET v_message = CONCAT(

            'نأسف لا يمكن تحديث رصيد مرتجع بعد البيع',

            ' | v_total_old_return_price: ', COALESCE(v_total_old_return_price, 'NULL'),

            ' | v_total_return_price: ', COALESCE(v_total_return_price, 'NULL'),

            ' | oid: ', COALESCE(p_order_id, 'NULL')

        );

        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;	    

    

    END IF;

    IF p_result IS NULL OR p_result = 0 THEN

        SET v_message = CONCAT('نأسف لا يمكن تحديث رصيد مرتجع بعد البيع', p_order_id);

        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;

    END IF;

END$$

CREATE PROCEDURE `reduceClientCreditBatch` (IN `p_session_id` VARCHAR(64), OUT `p_error_code` INT, OUT `p_status` VARCHAR(10), OUT `p_message` TEXT)   proc_exit: BEGIN

    

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

CREATE PROCEDURE `restClientDebt` (IN `p_order_id` INT, OUT `p_result` INT)  SQL SECURITY INVOKER BEGIN

    --  تعريف المتغيرات الداخلية

    DECLARE v_client_id INT;

    DECLARE v_order_status INT;

    DECLARE v_exists_id INT;

    DECLARE v_total_ship_price DECIMAL(12,2);

    DECLARE v_total_order_price DECIMAL(12,2);

    DECLARE v_total_refund_order DECIMAL(12,2);

    DECLARE v_balance_exists INT DEFAULT 0;

   -- DECLARE v_amount DECIMAL(12,2);

    DECLARE v_sqlstate CHAR(5) DEFAULT '45000';

    DECLARE v_message TEXT;

    -- --------------------------------------------------------------

    -- 1 جلب بيانات الطلب (العميل، الحالة، السعر الكلي، المبالغ المرتجعة)

    -- --------------------------------------------------------------

    

    SELECT 

        A.CLIENT_ID, 

        O.ORDER_STATUS, 

        O.EXISTS_ID, 

        E.BALANCE AS TOTAL_SHIP_PRICE,

        SUM((OP.PRICE - OP.DISCOUNT) * OP.QTY) AS TOTAL_ORDER_PRICE,

        SUM((OP.PRICE - OP.DISCOUNT) * (CASE WHEN OP.PRO_STATUSE IN ('rejected','partial') THEN (OP.QTY - OP.DELIVERED_QTY) ELSE 0 END)) AS TOTAL_REFUND_ORDER

    INTO 

        v_client_id, 

        v_order_status, 

        v_exists_id, 

        v_total_ship_price, 

        v_total_order_price, 

        v_total_refund_order

    FROM orders O

        JOIN order_product OP ON O.ORDER_ID = OP.ORDER_ID

        JOIN addresses A ON O.ADDRESS_ID = A.ADDRESS_ID

        LEFT JOIN (

            SELECT * 

            FROM (

                SELECT *,

                    ROW_NUMBER() OVER (PARTITION BY REFERENCE_ID ORDER BY ID) AS rn

                FROM partners_balance

                WHERE TRANSACTION_CATEGORY_ID = 8

                AND PARTNER_TYPE = 'CLIENT'

                AND TRANSACTION_TYPE = 'DEBIT' 

            ) AS ranked

            WHERE rn = 1

        ) E ON E.REFERENCE_ID = O.ORDER_ID

    WHERE O.ORDER_ID = p_order_id AND TRANSACTION_TYPE = 'DEBIT' AND TRANSACTION_CATEGORY_ID = 8

    GROUP BY A.CLIENT_ID, O.ORDER_ID, O.ORDER_STATUS, O.EXISTS_ID;

        

    -- تحقق من وجود بيانات للطلب

    IF v_client_id IS NULL THEN

        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = ' لا يوجد بيانات للطلب رقم ';

    END IF;

    -- تحقق من القيم المدخله

    IF v_total_order_price < v_total_refund_order THEN

        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = ' فشل فى تحديث مديونية العميل قيمة المرفضات اكبر من قيمة امر البيع';

    END IF;

    -- تحقق من وجود بيانات للطلب

    IF v_client_id IS NULL THEN

        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = ' لا يوجد بيانات للطلب رقم ';

    END IF;

    

    -- --------------------------------------------------------------

    -- 2 محاولة تحديث سجل المديونية إن وجد مسبقًا

    -- --------------------------------------------------------------

    

    IF v_order_status IN (3,4) AND v_total_ship_price IS NOT NULL THEN

        UPDATE partners_balance AS P

        SET P.BALANCE = CASE WHEN v_order_status IN (3,4) THEN (v_total_order_price - v_total_refund_order) ELSE 0 END, 

            P.LAST_UPDATE = NOW(6)

        WHERE 

            P.PARTNER_TYPE = 'CLIENT' 

            AND P.REFERENCE_ID = p_order_id 

            AND P.PARTNER_ID = v_client_id 

            AND P.TRANSACTION_TYPE = 'DEBIT' 

            AND P.TRANSACTION_CATEGORY_ID = 8;

        -- حفظ عدد الصفوف المتأثرة

        SET v_balance_exists = ROW_COUNT();

    -- --------------------------------------------------------------

    -- 3 لو لم يتم التحديث، نضيف سجل جديد

    -- --------------------------------------------------------------

    

    ELSEIF v_order_status IN (3,4) AND v_total_ship_price IS NULL THEN

        

        INSERT INTO partners_balance (PARTNER_TYPE, PARTNER_ID, REFERENCE_ID, TRANSACTION_TYPE, BALANCE, NOTE, TRANSACTION_CATEGORY_ID, USE_OF_CREDIT)

        VALUES ('CLIENT', v_client_id, p_order_id, 'DEBIT',  (v_total_order_price - v_total_refund_order) , 'مديونية عميل ناتجة عن أمر بيع', 8, 0);

        -- حفظ عدد الصفوف المتأثرة

        SET v_balance_exists = ROW_COUNT();

    

    ELSEIF v_order_status = 5 AND v_total_ship_price IS NOT NULL then

        

        DELETE FROM partners_balance

        WHERE

            REFERENCE_ID = p_order_id

            AND PARTNER_ID = v_client_id

            AND PARTNER_TYPE = 'CLIENT'

            AND TRANSACTION_TYPE = 'DEBIT'

            AND TRANSACTION_CATEGORY_ID = 8;

        SET p_result = ROW_COUNT();

        

    END IF;

    

    -- --------------------------------------------------------------

    -- التحقق من النتيجه

    -- --------------------------------------------------------------

    IF v_balance_exists = 0 AND v_total_ship_price != v_total_refund_order THEN 

        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT =  'فشل تحديث مديونية العميل';

    END IF;

    

    -- --------------------------------------------------------------

    -- 4 إرجاع النتيجة النهائية

    -- --------------------------------------------------------------

    IF v_balance_exists = 0 AND v_total_ship_price = v_total_refund_order THEN 

        SET p_result = -1;

    ELSE

        SET p_result = v_balance_exists;

    END IF;

END$$

CREATE PROCEDURE `restClientDebtBatch` (IN `p_session_id` VARCHAR(64), OUT `p_error_code` INT, OUT `p_status` VARCHAR(10), OUT `p_message` TEXT)   proc_exit:
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

CREATE PROCEDURE `setDeliveryConfirmationOptimized` (IN `p_session_id` VARCHAR(64), IN `p_executed_by` INT, IN `p_executed_group` VARCHAR(250), IN `p_branch_id` INT, IN `p_update_inventory` VARCHAR(10))   proc_exit: BEGIN
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
    -- --------------------------------
    -- 3+ - تسجيل اسباب الرفض
    -- --------------------------------
    DELETE R FROM reasons_for_product_rejection R
    INNER JOIN confirm_staging S ON S.ORDER_ID = R.ORDER_ID AND S.PRO_CODE = R.PRO_CODE AND S.TYPE = 'SELSE'
    INNER JOIN order_product OP ON OP.ORDER_ID = S.ORDER_ID AND OP.PRO_CODE = S.PRO_CODE
    WHERE S.APP_QTY >= OP.QTY;

    INSERT INTO reasons_for_product_rejection (ORDER_ID, PRO_CODE, REASON_ID, COMMENT)
    SELECT S.ORDER_ID, S.PRO_CODE, S.REASON_ID, COALESCE(S.COMMENT, '')
    FROM order_product OP
    INNER JOIN confirm_staging S ON OP.ORDER_ID = S.ORDER_ID AND OP.PRO_CODE = S.PRO_CODE AND S.TYPE = 'SELSE'
    WHERE S.REASON_ID IS NOT NULL AND S.APP_QTY >= 0 AND S.APP_QTY < OP.QTY
    ON DUPLICATE KEY UPDATE REASON_ID = VALUES(REASON_ID), COMMENT = VALUES(COMMENT);
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
    -- 9. تحديث مخزون المنتجات 
    -- --------------------------------------------------------------
    IF p_update_inventory = 'true' THEN
        IF v_total_orders_selse > 0 THEN
		    CALL inventory_movement_sales_order(p_session_id, 'RETURN_SHIPP', p_executed_by, v_store_id, v_inv_result_a);
			IF v_inv_result_a <= 0  THEN
				SELECT 'error' AS Status, 'فشل فى تحديث المخزون' AS message, 45450 AS error_code;
				LEAVE proc_exit;
			END IF;
			
			UPDATE custody_orders SET RETURNS = 1, LAST_UPDATE = NOW(6) WHERE ORDER_ID IN(SELECT DISTINCT ORDER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id AND TYPE = 'SELSE');
			IF ROW_COUNT() <= 0  THEN
				SELECT 'error' AS Status, 'فشل فى تحديث استلام مرفوضات عهدة المندوب' AS message, 45450 AS error_code;
				LEAVE proc_exit;
			END IF;
			
        END IF;
		
        IF v_total_orders_refund > 0 THEN
		    CALL inventory_movement_sales_order(p_session_id, 'RETURN_SALES', p_executed_by, v_store_id, v_inv_result_a);
			IF v_inv_result_a <= 0  THEN
				SELECT 'error' AS Status, 'فشل فى تحديث المخزون' AS message, 45450 AS error_code;
				LEAVE proc_exit;
			END IF;
			
            UPDATE custody_return_orders SET RETURNS = 1, LAST_UPDATE = NOW(6) WHERE ORDER_ID IN(SELECT DISTINCT ORDER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id AND TYPE = 'REFUND');
        	IF ROW_COUNT() <= 0  THEN
                SELECT 'error' AS Status, 'فشل فى تحديث استلام مرتجعات عهدة المندوب' AS message, 45450 AS error_code;
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
    DELETE FROM custody_orders WHERE RETURNS = 1 AND (ORDER_STATUSE = 5 OR CUST_STAT = 'FREEZE') AND ORDER_ID IN(SELECT DISTINCT ORDER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id AND TYPE = 'SELSE');
	DELETE FROM custody_return_orders  WHERE RETURNS = 1 AND ORDER_ID IN(SELECT DISTINCT ORDER_ID FROM confirm_staging WHERE SESSION_ID = p_session_id AND TYPE = 'REFUND');

    -- --------------------------------------------------------------
    -- 10. النتيجة النهائية
    -- --------------------------------------------------------------
    SELECT 'success' AS status, 
           CONCAT('تم تأكيد تسليم الشحنة ', v_total_orders, ' أمر بنجاح (', 
                  v_total_orders_selse, ' مبيعات, ', 
                  v_total_orders_refund, ' مرتجعات)') AS message;
           
END$$

CREATE PROCEDURE `setRetunedConfirmationOptimized` (IN `p_session_id` VARCHAR(64), IN `p_executed_by` INT, IN `p_executed_group` VARCHAR(250), IN `p_branch_id` INT, IN `p_update_inventory` VARCHAR(10))   proc_exit: BEGIN
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
    /*IF v_total_orders_selse > 0 THEN
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
    
   
		
CALL inventory_movement_sales_order(p_session_id, 'RETURN_SALES', p_executed_by, v_store_id,v_inv_result_a);
			IF v_inv_result_a <= 0  THEN
				SELECT 'error' AS Status, CONCAT ('فشل فى تحديث المخزون',' - ',v_inv_result_a) AS message, 45450 AS error_code;
				LEAVE proc_exit;
			END IF;
        
        
        
  
    
	-- --------------------------------------------------------------
    -- 7. اضافة حركة الشحن دفعه واحده
    -- --------------------------------------------------------------
    -- CALL shipping_movement(p_session_id, p_executed_by, @error_code, @status, @message);
    
    -- IF @status = 'error' THEN
    --    SELECT @status AS status, @message AS message, @error_code AS error_code;
    --    LEAVE proc_exit;
    -- END IF;
	
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

CREATE PROCEDURE `set_inventory_movement_orders` (IN `p_order_id` VARCHAR(70), IN `p_partner_id` INT, IN `p_user_action_id` INT, IN `p_branch_id` INT, IN `p_store_id` INT, IN `p_movement_type` ENUM('RECEIVING','REFUNDPURCHISES','SALES','SHIPPING','REFUND','RETURN_SHIPP','RETURN_SALES','RETURN_SHIPP_SALSE'))   proc_exit: BEGIN

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

CREATE PROCEDURE `set_orders_log` (IN `p_session_id` VARCHAR(64), IN `p_executed_by` INT, IN `p_executed_group` VARCHAR(250), OUT `p_error_code` INT, OUT `p_status` VARCHAR(10), OUT `p_message` TEXT)   proc_exit: BEGIN
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
   WHEN cs.TYPE = 'SELSE' AND co.CUST_STAT = 'WAITING' AND co.ORDER_STATUSE = 2 THEN CONCAT(v_logtext, '* تم تسليم أمر البيع إلى المندوب ', CONCAT(us.FORENAME, ' ', us.LASTNAME))
   WHEN cs.TYPE = 'SELSE' AND co.CUST_STAT = 'FREEZE' THEN CONCAT(v_logtext, '* تم تجميد أمر البيع على المندوب: ', CONCAT(us.FORENAME, ' ', us.LASTNAME))
   WHEN cs.TYPE = 'SELSE' AND co.CUST_STAT = 'DELIVERED' AND co.ORDER_STATUSE IN (3,4,5) THEN CONCAT(v_logtext, '* تم الشحن - حالة أمر البيع حالياً: ', COALESCE(ost.STA_NAME, 'غير معروف') ,IF(co.ORDER_STATUSE IN (4,5) AND CO.RETURNS = 1 , '* تم تسوية امر البيع مخزنيا: ' ,''))
   WHEN cs.TYPE = 'SELSE' AND co.CUST_STAT IS NULL AND o.EXISTS_ID = 11 AND o.ORDER_STATUS IN (3,4) THEN CONCAT(v_logtext, '* تم التسليم - حالة أمر البيع حالياً: ', COALESCE(ost.STA_NAME, 'غير معروف'))
   WHEN cs.TYPE = 'SELSE' AND co.CUST_STAT IS NULL AND o.EXISTS_ID = 11 AND o.ORDER_STATUS = 5 THEN CONCAT(v_logtext, '* رفض العميل الاستلام - حالة أمر البيع حالياً: ', COALESCE(ost.STA_NAME, 'غير معروف'))
   WHEN cs.TYPE = 'REFUND' AND cro.ORDER_STATUSE IN (3,4,5) THEN CONCAT(v_logtext, '* رفض العميل تسليم المرتجعات للمندوب - حالة أمر البيع حالياً: ', COALESCE(osta.STA_NAME, 'غير معروف'))
   WHEN cs.TYPE = 'REFUND' AND cro.ORDER_STATUSE IN(6,7) THEN CONCAT(v_logtext, '* تم استلام المرتجعات - حالة أمر البيع حالياً: ', COALESCE(osta.STA_NAME, 'غير معروف'),IF(cro.ORDER_STATUSE IN(6,7) AND cro.RETURNS = 1,'* تم تسوية مرتجعات امر البيع مخزنيا',''))
   WHEN cs.TYPE = 'REFUND' AND o.ORDER_STATUS IN(6,7) THEN CONCAT(v_logtext, '* تم استلام المرتجعات - حالة أمر البيع حالياً: ', COALESCE(osta.STA_NAME, 'غير معروف'),IF(cro.ORDER_STATUSE IN(6,7) AND cro.RETURNS = 1,'* تم تسوية مرتجعات امر البيع مخزنيا',''))
   ELSE NULL
  END AS LOG_TEXT,
  NOW(6) AS DATE_ACTION -- أضفت التاريخ الحالي
 FROM  (SELECT SESSION_ID, ORDER_ID, USER_ID, TYPE FROM confirm_staging GROUP BY SESSION_ID,ORDER_ID,USER_ID) cs
 INNER JOIN orders o ON o.ORDER_ID = cs.ORDER_ID
 LEFT JOIN custody_orders co ON o.ORDER_ID = co.ORDER_ID AND co.USER_ID = cs.USER_ID 
 LEFT JOIN custody_return_orders cro ON o.ORDER_ID = cro.ORDER_ID AND cro.USER_ID = cs.USER_ID
 LEFT JOIN users us ON cs.USER_ID = us.USER_ID
 LEFT JOIN order_statuse ost ON o.ORDER_STATUS = ost.STA_ID
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

CREATE PROCEDURE `shipping_movement` (IN `p_session_id` VARCHAR(64), IN `p_executed_by` INT, OUT `p_error_code` INT, OUT `p_status` VARCHAR(10), OUT `p_message` TEXT)   proc_exit: BEGIN

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
				WHEN cs.TYPE = 'REFUND'   AND o.EXISTS_ID IN(10,11) AND o.ORDER_STATUS = 7 THEN 9
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

CREATE PROCEDURE `update_stores` (OUT `p_return` INT)   BEGIN
    -- 🔹 تعريف المتغيرات
    DECLARE v_movement_exists INT DEFAULT 0;     -- عدد المنتجات في الحركات غير المعالجة
    DECLARE v_affected_rows INT DEFAULT 0;       -- الصفوف المتأثرة في التحديث
    DECLARE v_message VARCHAR(255);              -- رسائل الخطأ
        
    -- --------------------------------------------------------------
    -- الخطوة 2: التحقق من قفل المخزن فى حالة فتح محضر جرد
    -- --------------------------------------------------------------
    IF EXISTS (
       SELECT 1
       FROM inventory_product_movement IPM
       JOIN inventory_movement IM ON IM.IM_ID = IPM.IM_ID
       JOIN stores S ON S.STO_ID = IM.STO_ID
       WHERE IPM.IS_PROCESSED = 0 AND S.IS_COUNTING = 1 AND S.COUNTING_EXPIRES > NOW() AND IM.IM_MOVE_TYPE    <> 'SETTLEMENT'
   ) THEN
       SET v_message = 'لا يمكن تنفيذ الحركة، المخزن تحت الجرد.';
       SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
    END IF;
 
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

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE IF NOT EXISTS `accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `account_code` varchar(50) NOT NULL,
  `account_name` varchar(150) NOT NULL,
  `account_type` enum('ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE') NOT NULL,
  `normal_balance` enum('DEBIT','CREDIT') NOT NULL,
  `account_level` tinyint(4) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `allow_posting` tinyint(1) NOT NULL DEFAULT 1,
  `system_account` tinyint(1) NOT NULL DEFAULT 0,
  `is_system_created` tinyint(1) NOT NULL DEFAULT 0,
  `description` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_account_code` (`account_code`),
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`id`, `parent_id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `account_level`, `is_active`, `allow_posting`, `system_account`, `is_system_created`, `description`, `note`, `created_at`, `created_by`, `updated_at`, `updated_by`) VALUES
(1, NULL, '1000', 'الأصول', 'ASSET', 'DEBIT', 1, 1, 0, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-12 09:22:41', NULL),
(2, 1, '1100', 'النقدية', 'ASSET', 'DEBIT', 2, 1, 0, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-12 09:22:41', NULL),
(3, 1, '1200', 'العملاء', 'ASSET', 'DEBIT', 2, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-12 09:22:41', NULL),
(4, 1, '1300', 'المخزون', 'ASSET', 'DEBIT', 2, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-12 09:22:41', NULL),
(5, 1, '1400', 'البنوك', 'ASSET', 'DEBIT', 2, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-12 09:22:41', NULL),
(6, 1, '1500', 'العهد والسلف', 'ASSET', 'DEBIT', 2, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-12 09:22:41', NULL),
(7, 2, '1110', 'الصندوق الرئيسي', 'ASSET', 'DEBIT', 3, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-12 09:22:41', NULL),
(8, 2, '1120', 'خزينة الفرع', 'ASSET', 'DEBIT', 3, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-12 09:22:41', NULL),
(9, NULL, '2000', 'الالتزامات', 'LIABILITY', 'CREDIT', 1, 1, 0, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-13 09:53:52', NULL),
(10, 9, '2100', 'الموردون', 'LIABILITY', 'CREDIT', 2, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-13 09:53:52', NULL),
(11, 9, '2200', 'مصروفات مستحقة', 'LIABILITY', 'CREDIT', 2, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-13 09:53:52', NULL),
(12, 9, '2300', 'ضرائب مستحقة', 'LIABILITY', 'CREDIT', 2, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-13 09:53:52', NULL),
(13, NULL, '3000', 'حقوق الملكية', 'EQUITY', 'CREDIT', 1, 1, 0, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-13 09:53:52', NULL),
(14, 13, '3100', 'رأس المال', 'EQUITY', 'CREDIT', 2, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-13 09:53:52', NULL),
(15, 13, '3200', 'الأرباح المحتجزة', 'EQUITY', 'CREDIT', 2, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-13 09:53:52', NULL),
(16, NULL, '4000', 'الإيرادات', 'REVENUE', 'CREDIT', 1, 1, 0, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-13 09:53:52', NULL),
(17, 16, '4100', 'إيرادات المبيعات', 'REVENUE', 'CREDIT', 2, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-13 09:53:52', NULL),
(18, 16, '4200', 'إيرادات الشحن', 'REVENUE', 'CREDIT', 2, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-13 09:53:52', NULL),
(19, 16, '4300', 'إيرادات أخرى', 'REVENUE', 'CREDIT', 2, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-13 09:53:52', NULL),
(20, NULL, '5000', 'المصروفات', 'EXPENSE', 'DEBIT', 1, 1, 0, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-12 09:22:41', NULL),
(21, 20, '5100', 'تكلفة البضاعة المباعة', 'EXPENSE', 'DEBIT', 2, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-12 22:42:49', NULL),
(22, 20, '5200', 'المرتبات', 'EXPENSE', 'DEBIT', 2, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-12 22:42:49', NULL),
(23, 20, '5300', 'مصروفات التشغيل', 'EXPENSE', 'DEBIT', 2, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-12 22:42:49', NULL),
(24, 20, '5400', 'الكهرباء', 'EXPENSE', 'DEBIT', 2, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-12 22:42:49', NULL),
(25, 20, '5500', 'المياه', 'EXPENSE', 'DEBIT', 2, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-12 22:42:49', NULL),
(26, 20, '5600', 'الإنترنت', 'EXPENSE', 'DEBIT', 2, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-12 22:42:49', NULL),
(27, 20, '5700', 'عمولات المندوبين', 'EXPENSE', 'DEBIT', 2, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-12 22:42:49', NULL),
(28, 20, '5800', 'خسائر هالك المخزون', 'EXPENSE', 'DEBIT', 2, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-12 22:42:49', NULL),
(29, 20, '5900', 'مصروفات أخرى', 'EXPENSE', 'DEBIT', 2, 1, 1, 0, 0, NULL, NULL, '2026-07-12 09:22:41', NULL, '2026-07-12 22:42:49', NULL),
(30, 19, '4310', 'مكاسب فروقات الجرد', 'REVENUE', 'CREDIT', 2, 1, 1, 0, 0, 'مكاسب ناتجة عن زيادة المخزون عند الجرد', NULL, '2026-07-23 21:36:45', NULL, '2026-07-23 21:36:45', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `account_settings`
--

CREATE TABLE IF NOT EXISTS `account_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `account_id` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_account_id` (`account_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `account_settings`
--

INSERT INTO `account_settings` (`id`, `setting_key`, `account_id`, `description`, `created_at`, `updated_at`) VALUES
(1, 'CUSTOMERS', 3, 'حساب العملاء', '2026-07-13 10:02:32', '2026-07-13 10:02:32'),
(2, 'SUPPLIERS', 10, 'حساب الموردين', '2026-07-13 10:02:32', '2026-07-13 10:02:32'),
(3, 'INVENTORY', 4, 'حساب المخزون', '2026-07-13 10:02:32', '2026-07-13 10:02:32'),
(4, 'MAIN_CASH', 7, 'الصندوق الرئيسي', '2026-07-13 10:02:32', '2026-07-13 10:02:32'),
(5, 'SALES', 17, 'إيرادات المبيعات', '2026-07-13 10:02:32', '2026-07-13 10:02:32'),
(6, 'SHIPPING_REVENUE', 18, 'إيرادات الشحن', '2026-07-13 10:02:32', '2026-07-13 10:02:32'),
(7, 'COGS', 21, 'تكلفة البضاعة المباعة', '2026-07-13 10:02:32', '2026-07-13 10:02:32'),
(8, 'SALARY_EXPENSE', 22, 'مصروف المرتبات', '2026-07-13 10:02:32', '2026-07-13 10:02:32'),
(9, 'SCRAP_LOSS', 28, 'خسائر الهالك', '2026-07-13 10:02:32', '2026-07-13 10:02:32'),
(10, 'BANK', 5, 'حساب البنوك', '2026-07-19 03:22:02', '2026-07-19 03:22:02'),
(11, 'PURCHASES', 4, 'مشتريات المخزون', '2026-07-19 03:22:02', '2026-07-19 03:22:02'),
(12, 'SALES_RETURNS', 17, 'مردودات المبيعات', '2026-07-19 03:22:02', '2026-07-19 03:22:02'),
(13, 'PURCHASE_RETURNS', 10, 'مردودات المشتريات', '2026-07-19 03:22:02', '2026-07-19 03:22:02'),
(14, 'OTHER_REVENUE', 19, 'إيرادات أخرى', '2026-07-19 03:22:02', '2026-07-19 03:22:02'),
(15, 'OTHER_EXPENSE', 29, 'مصروفات أخرى', '2026-07-19 03:22:02', '2026-07-19 03:22:02'),
(16, 'DELIVERY_EXPENSE', 23, 'مصروفات الشحن', '2026-07-19 03:22:02', '2026-07-19 03:22:02'),
(17, 'DELIVERY_REVENUE', 18, 'إيرادات الشحن', '2026-07-19 03:22:02', '2026-07-19 03:22:02'),
(18, 'DELIVERY_COMMISSION', 27, 'عمولات المندوبين', '2026-07-19 03:22:02', '2026-07-19 03:22:02'),
(20, 'STOCK_COUNT_GAIN', 30, 'حساب مكاسب فروقات الجرد', '2026-07-23 21:38:40', '2026-07-23 21:38:40');

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE IF NOT EXISTS `addresses` (
  `ADDRESS_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `CITY_ID` int(11) NOT NULL,
  `USER_ID` bigint(20) DEFAULT NULL,
  `CLIENT_ID` bigint(20) DEFAULT NULL,
  `SUPPLIER_ID` bigint(20) DEFAULT NULL,
  `ADDRESS_TITLE` varchar(30) NOT NULL,
  `DEFAULTADD` tinyint(1) NOT NULL DEFAULT 0,
  `ADDRESS` text NOT NULL,
  PRIMARY KEY (`ADDRESS_ID`),
  UNIQUE KEY `USER_ID` (`USER_ID`,`DEFAULTADD`),
  UNIQUE KEY `CLIENT_ID_2` (`CLIENT_ID`,`DEFAULTADD`),
  UNIQUE KEY `SUPPLIER_ID` (`SUPPLIER_ID`,`DEFAULTADD`),
  KEY `ADDRESSES_CITY_ID_CITY_CI_ID` (`CITY_ID`),
  KEY `ADDRESSES_USER_ID_USERS_USER_ID` (`USER_ID`),
  KEY `CLIENT_ID` (`CLIENT_ID`),
  KEY `ADDRESS_ID` (`ADDRESS_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `banks`
--

CREATE TABLE IF NOT EXISTS `banks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `bank_name` varchar(150) DEFAULT NULL,
  `account_number` varchar(100) DEFAULT NULL,
  `iban` varchar(100) DEFAULT NULL,
  `swift_code` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_bank_account` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `barcode_counter`
--

CREATE TABLE IF NOT EXISTS `barcode_counter` (
  `last_code` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `barcode_counter`
--

INSERT INTO `barcode_counter` (`last_code`) VALUES
(290000000109);

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE IF NOT EXISTS `branches` (
  `BR_ID` int(11) NOT NULL AUTO_INCREMENT,
  `COMPANIE_ID` int(11) NOT NULL,
  `CITY_ID` int(11) DEFAULT NULL,
  `BR_NAME` varchar(50) NOT NULL,
  `BR_MAIN` tinyint(1) NOT NULL,
  `BR_ADDRESS` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`BR_ID`),
  UNIQUE KEY `BR_NAME` (`BR_NAME`),
  KEY `BRANCHES_COMPANIE_ID_COMPANIES_COM_ID` (`COMPANIE_ID`),
  KEY `BRANCHES_CITY_ID_CITY_CI_ID` (`CITY_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `cash_boxes`
--

CREATE TABLE IF NOT EXISTS `cash_boxes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `branch_id` (`branch_id`),
  KEY `fk_cash_account` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `city`
--

CREATE TABLE IF NOT EXISTS `city` (
  `CI_ID` int(11) NOT NULL AUTO_INCREMENT,
  `GOV_ID` int(11) DEFAULT NULL,
  `ZONE_ID` int(11) DEFAULT NULL,
  `CI_NAME` varchar(50) NOT NULL,
  `CI_NAME_EN` varchar(50) NOT NULL,
  PRIMARY KEY (`CI_ID`),
  UNIQUE KEY `CI_NAME` (`CI_NAME`),
  UNIQUE KEY `CI_NAME_EN` (`CI_NAME_EN`),
  KEY `CITY_GOV_ID_GOV_GOV_ID` (`GOV_ID`),
  KEY `CITY_ZONE_ID_ZONES_ZONE_ID` (`ZONE_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=922 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `city`
--

INSERT INTO `city` (`CI_ID`, `GOV_ID`, `ZONE_ID`, `CI_NAME`, `CI_NAME_EN`) VALUES
(2, 1, 13, 'الزيتون', 'Zeitoun'),
(3, 1, 15, 'الزاوية الحمراء', 'Zawya al-Hamra'),
(5, 1, 15, 'الشرابية', 'Sharbia'),
(6, 1, 15, 'الساحل', 'El Sahel'),
(7, 1, 15, 'شبرا', 'Shubra'),
(8, 1, 15, 'روض الفرج', 'Rod El Farag'),
(9, 1, 13, 'الاميرية', 'El Ameeria'),
(10, 1, 13, 'السلام', 'Salam'),
(12, 1, 13, 'المرج', 'El Marg'),
(13, 1, 13, 'المطريه', 'El Matariya'),
(14, 1, 13, 'عين شمس', 'Ain Shams'),
(15, 1, 13, 'النزهة', 'Al Nozha'),
(16, 1, 13, 'مصر الجديدة', 'Heliopolis'),
(17, 1, 12, 'مدينة نصر', 'Nasr City'),
(19, 1, 15, 'الوايلي', 'El Waily'),
(20, 1, 12, 'منشاه ناصر', 'Mansheyet Nasser'),
(21, 1, 15, 'وسط البلد', 'Downtown'),
(22, 1, 15, 'باب الشعرية', 'Bab al-Sharia'),
(23, 1, 15, 'الازبكية', 'Azbakeya'),
(24, 1, 15, 'بولاق ابو العلا', 'Bulaq abu elela'),
(25, 1, 15, 'الموسكي', 'El Mosky'),
(26, 1, 15, 'عابدين', 'Abdeen'),
(28, 1, 16, 'المقطم', 'Mokattam'),
(29, 1, 15, 'الخليفة', 'El Khalifa'),
(30, 1, 15, 'السيدة زينب', 'Sayeda Zeinab'),
(31, 1, 15, 'مصر القديمة', 'Old Cairo'),
(32, 1, 14, 'دار السلام', 'Dar El Salam'),
(33, 1, 14, 'البساتين', 'El Bassateen'),
(34, 1, 14, 'المعادي', 'Maadi'),
(35, 1, 14, 'طره', 'Torah'),
(36, 1, 14, 'المعصرة', 'El Maasara'),
(37, 1, 14, '15 مايو', 'May 15'),
(38, 1, 14, 'حلوان', 'Helwan'),
(39, 1, 14, 'التبين', 'Tebeen'),
(41, 2, 20, 'المنتزه', 'Montaza 1'),
(45, 2, 20, 'الجمرك', 'El Gomrok'),
(47, 2, 20, 'العجمي', 'El Agamy'),
(48, 2, 20, 'العامريه', 'Amreya 1'),
(50, 2, 20, 'برج العرب', 'Borg El Arab'),
(51, 2, 20, 'سموحة', 'Smouha'),
(52, 2, 20, 'المندرة', 'Mandara'),
(53, 2, 20, 'ابو قير', 'Abu Qir'),
(54, 2, 20, 'كوم الدكة', 'Kom El Dekka'),
(55, 2, 20, 'المنشية', 'El Mansheya'),
(56, 2, 20, 'العطارين', 'Attarine'),
(57, 2, 20, 'محرم بك', 'Moharram Bek'),
(58, 2, 20, 'سيدي جابر', 'Sidi Gaber'),
(59, 2, 20, 'الورديان', 'El Wardian'),
(60, 2, 20, 'القباري', 'El Kabari'),
(61, 2, 20, 'البيطاش', 'El Beitash'),
(63, 3, 18, 'الدقي', 'Dokki'),
(64, 3, 18, 'العجوزة', 'Agouza'),
(65, 3, 43, 'الهرم', 'Haram'),
(66, 3, 17, 'السادس من اكتوبر', '6th of October'),
(67, 3, 17, 'الشيخ زايد', 'Sheikh Zayed'),
(68, 3, 18, 'امبابة', 'Imbaba'),
(69, 3, 18, 'بولاق الدكرور', 'Bolaq El Dakrour'),
(70, 3, 43, 'العمرانية', 'Omrania'),
(71, 3, 43, 'كفر طهرمس', 'Kafr Tohormos'),
(72, 3, 41, 'المنصورية', 'Al Mansuriyah'),
(73, 3, 18, 'البدرشين', 'Badrasheen'),
(74, 3, 41, 'العياط', 'Al Ayyat'),
(76, 3, 18, 'ابو النمرس', 'Abu Nomros'),
(77, 3, 41, 'الصف', 'Saf'),
(78, 3, 41, 'اطفيح', 'Atfih'),
(79, 3, 41, 'كرداسة', 'Kerdasa'),
(80, 3, 18, 'اوسيم', 'Ossim'),
(81, 4, 30, 'بنها', 'Banha'),
(82, 4, 30, 'قليوب', 'Qalyub'),
(83, 4, 30, 'شبرا الخيمة', 'Shubra El-Kheima'),
(84, 4, 30, 'الخانكه', 'El Khanka'),
(85, 4, 30, 'طوخ', 'Tukh'),
(86, 4, 30, 'القناطر الخيرية', 'Qanater El Khairiya'),
(87, 4, 30, 'كفر شكر', 'Kafr Shukr'),
(88, 1, 16, 'العبور', 'Obour'),
(89, 1, 15, 'الخصوص', 'Khosous'),
(90, 1, 15, 'مسطرد', 'Mostorod'),
(91, 5, 27, 'الزقازيق', 'Zagazig'),
(92, 5, 27, 'بلبيس', 'Belbeis'),
(93, 5, 27, 'منيا القمح', 'Minya Al-Qamh'),
(94, 5, 27, 'ابو حماد', 'Abu Hammad'),
(95, 5, 27, 'ههيا', 'Hehia'),
(96, 5, 27, 'فاقوس', 'Fakous'),
(97, 5, 27, 'ابو كبير', 'Abu Kabir'),
(98, 5, 27, 'الحسينية', 'Husseiniya'),
(99, 5, 27, 'الصالحية الجديدة', 'El Salheya El Gadida'),
(100, 5, 27, 'الابراهيمية', 'El Ibrahimia'),
(101, 5, 27, 'ديرب نجم', 'Diarb Negm'),
(102, 5, 27, 'كفر صقر', 'Kafr Saqr'),
(103, 5, 27, 'اولاد صقر', 'Awlad Saqr'),
(104, 5, 27, 'العاشر من رمضان', '10th of Ramadan'),
(105, 6, 25, 'المنصورة', 'Mansoura'),
(106, 6, 25, 'طلخا', 'Talkha'),
(107, 6, 25, 'ميت غمر', 'Mit Ghamr'),
(108, 6, 25, 'دكرنس', 'Dekernes'),
(109, 6, 25, 'السنبلاوين', 'Senbellawein'),
(110, 6, 25, 'الجماليه -الدقهليه', 'El Gamalia'),
(111, 6, 25, 'شربين', 'Sherbin'),
(112, 6, 25, 'المنزلة', 'El Manzala'),
(113, 6, 25, 'نبروه', 'Nabaruh'),
(114, 6, 25, 'بلقاس', 'Belqas'),
(115, 6, 25, 'تمي الامديد', 'Tami El-Amdeed'),
(116, 6, 25, 'اجا', 'Aga'),
(118, 7, 44, 'دمنهور', 'Damanhour'),
(119, 7, 44, 'كفر الدوار', 'Kafr El Dawwar'),
(120, 7, 44, 'رشيد', 'Rasheed'),
(121, 7, 44, 'ادكو', 'Edku'),
(122, 7, 44, 'ابو حمص', 'Abu Hummus'),
(123, 7, 44, 'الدلنجات', 'Delengat'),
(124, 7, 44, 'المحمودية', 'Mahmoudiyah'),
(125, 7, 44, 'ايتاي البارود', 'Itay El Barud'),
(126, 7, 44, 'حوش عيسى', 'Hosh Issa'),
(127, 7, 44, 'شبراخيت', 'Shubrakhit'),
(128, 7, 44, 'كوم حمادة', 'Kom Hamada'),
(129, 7, 44, 'مدينه بدر', 'Madenet Badr'),
(130, 7, 44, 'وادي النطرون', 'Wadi El Natrun'),
(131, 7, 44, 'النوباريه الجديده', 'New Nubariya'),
(132, 8, 40, 'كفر الشيخ', 'Kafr El Sheikh'),
(133, 8, 40, 'دسوق', 'Desouk'),
(134, 8, 40, 'فوه', 'Fowa'),
(135, 8, 40, 'مطوبس', 'Motobas'),
(136, 8, 40, 'بلطيم', 'Baltim'),
(137, 8, 40, 'البرلس', 'Burullus'),
(138, 8, 40, 'الحامول', 'El Hamoul'),
(139, 8, 40, 'بيلا', 'Bella'),
(140, 8, 40, 'سيدي سالم', 'Sidi Salem'),
(141, 8, 40, 'قلين', 'Qellin'),
(142, 8, 40, 'الرياض', 'El Reyad'),
(143, 9, 28, 'طنطا', 'Tanta'),
(144, 9, 28, 'المحلة الكبرى', 'El Mahalla El Kubra'),
(145, 9, 28, 'كفر الزيات', 'Kafr El Zayat'),
(146, 9, 28, 'زفتى', 'Zefta'),
(147, 9, 28, 'السنطة', 'El Santa'),
(148, 9, 28, 'قطور', 'Qutour'),
(149, 9, 28, 'بسيون', 'Basyoun'),
(150, 9, 28, 'سمنود', 'Samannoud'),
(151, 10, 31, 'شبين الكوم', 'Shibin El Kom'),
(152, 10, 31, 'السادات', 'Sadat'),
(153, 10, 31, 'منوف', 'Menouf'),
(154, 10, 31, 'سرس الليان', 'Sars El-Layan'),
(155, 10, 31, 'اشمون', 'Ashmoun'),
(156, 10, 31, 'الباجور', 'El Bagour'),
(157, 10, 31, 'قويسنا', 'Quesna'),
(158, 10, 31, 'بركة السبع', 'Berket El-Sabaa'),
(159, 10, 31, 'تلا', 'Tala'),
(160, 10, 31, 'الشهداء', 'El Shohada'),
(162, 11, 29, 'اطسا', 'Itsa'),
(163, 11, 29, 'سنورس', 'Sinnuris'),
(164, 11, 29, 'طامية', 'Tamiya'),
(165, 11, 29, 'يوسف الصديق', 'Yusuf El Sediq'),
(166, 12, 34, 'بني سويف', 'Beni Suef'),
(167, 12, 34, 'الواسطى', 'Al Wasta'),
(168, 12, 34, 'ناصر', 'Nasser'),
(169, 12, 34, 'اهناسيا', 'Ehnasia'),
(170, 12, 34, 'ببا', 'Biba'),
(171, 12, 34, 'سمسطا', 'Samasta'),
(172, 12, 34, 'الفشن', 'Al Fashn'),
(173, 13, 32, 'المنيا', 'Minya'),
(174, 13, 32, 'المنيا الجديدة', 'New Minya'),
(175, 13, 32, 'ابوقرقاص', 'Abu Qurqas'),
(176, 13, 32, 'ملوي', 'Mallawi'),
(177, 13, 32, 'دير مواس', 'Deir Mawas'),
(178, 13, 32, 'مطاي', 'Mattay'),
(179, 13, 32, 'سمالوط', 'Samalut'),
(180, 13, 32, 'بني مزار', 'Beni Mazar'),
(181, 13, 32, 'مغاغة', 'Maghagha'),
(182, 13, 32, 'العدوة', 'El Adwa'),
(184, 14, 21, 'اسيوط الجديدة', 'New Asyut'),
(185, 14, 21, 'ديروط', 'Dairut'),
(186, 14, 21, 'القوصية', 'El Qusiya'),
(187, 14, 21, 'منفلوط', 'Manfalut'),
(188, 14, 21, 'ابنوب', 'Abnoub'),
(189, 14, 21, 'ابو تيج', 'Abu Tig'),
(190, 14, 21, 'الغنايم', 'El Ghanaim'),
(191, 14, 21, 'صدفا', 'Sodfa'),
(192, 14, 21, 'البداري', 'El Badari'),
(193, 15, 38, 'سوهاج', 'Sohag'),
(194, 15, 38, 'سوهاج الجديدة', 'New Sohag'),
(195, 15, 38, 'اخميم', 'Akhmim'),
(196, 15, 38, 'جرجا', 'Girga'),
(197, 15, 38, 'البلينا', 'El Balina'),
(198, 15, 38, 'المنشاة', 'El Monshah'),
(199, 15, 38, 'طهطا', 'Tahta'),
(200, 15, 38, 'طما', 'Tama'),
(203, 16, 39, 'قنا', 'Qena'),
(204, 16, 39, 'قنا الجديدة', 'New Qena'),
(205, 16, 39, 'ابوتشت', 'Abu Tesht'),
(206, 16, 39, 'نجع حمادي', 'Nag Hammadi'),
(207, 16, 39, 'دشنا', 'Deshna'),
(208, 16, 39, 'فرشوط', 'Farshout'),
(209, 16, 39, 'قفط', 'Qift'),
(210, 16, 39, 'قوص', 'Qus'),
(211, 16, 39, 'نقادة', 'Naqada'),
(213, 17, 23, 'اسنا', 'Esna'),
(214, 17, 23, 'ارمنت', 'Armant'),
(215, 17, 23, 'الزينية', 'El Zenia'),
(216, 17, 23, 'القرنة', 'El Qarna'),
(217, 17, 23, 'البياضية', 'El Bayadeya'),
(218, 17, 23, 'الطود', 'El Tod'),
(220, 18, 19, 'اسوان الجديدة', 'New Aswan'),
(221, 18, 19, 'ادفو', 'Edfu'),
(222, 18, 19, 'كوم امبو', 'Kom Ombo'),
(223, 18, 19, 'دراو', 'Drau'),
(224, 18, 19, 'نصر النوبه', 'Nasr El Nuba'),
(225, 18, 19, 'ابو سمبل', 'Abu Simbel'),
(226, 19, 24, 'الغردقة', 'Hurghada'),
(227, 19, 24, 'راس غارب', 'Ras Gharib'),
(228, 19, 24, 'سفاجا', 'Safaga'),
(229, 19, 24, 'القصير', 'El Quseir'),
(230, 19, 24, 'مرسى علم', 'Marsa Alam'),
(231, 19, 24, 'شلاتين', 'Shalateen'),
(232, 19, 24, 'حلايب', 'Halayeb'),
(233, 20, 33, 'الخارجة', 'El Kharga'),
(234, 20, 33, 'الداخلة', 'El Dakhla'),
(235, 20, 33, 'الفرافرة', 'El Farafra'),
(236, 20, 33, 'بلاط', 'Balat'),
(237, 20, 33, 'باريس', 'Paris'),
(238, 21, 42, 'مرسى مطروح', 'Marsa Matruh'),
(239, 21, 42, 'الحمام', 'El Hammam'),
(240, 21, 42, 'العلمين', 'El Alamein'),
(241, 21, 42, 'الضبعة', 'El Dabaa'),
(242, 21, 42, 'النجيلة', 'El Negila'),
(243, 21, 42, 'سيدي براني', 'Sidi Barrani'),
(244, 21, 42, 'السلوم', 'El Salloum'),
(245, 21, 42, 'سيوة', 'Siwa'),
(246, 22, 36, 'العريش', 'Arish'),
(247, 22, 36, 'الشيخ زويد', 'Sheikh Zuweid'),
(248, 22, 36, 'رفح', 'Rafah'),
(249, 22, 36, 'بئر العبد', 'Bir al-Abed'),
(250, 22, 36, 'الحسنة', 'El Hassana'),
(251, 22, 36, 'نخل', 'Nakhl'),
(252, 23, 36, 'الطور', 'El Tor'),
(253, 23, 36, 'شرم الشيخ', 'Sharm El Sheikh'),
(254, 23, 36, 'دهب', 'Dahab'),
(255, 23, 36, 'نويبع', 'Nuweiba'),
(256, 23, 36, 'طابا', 'Taba'),
(257, 23, 36, 'سانت كاترين', 'Saint Catherine'),
(258, 23, 36, 'ابو رديس', 'Abu Redis'),
(259, 23, 36, 'ابو زنيمة', 'Abu Zneima'),
(260, 23, 36, 'راس سدر', 'Ras Sedr'),
(261, 24, 35, 'بورسعيد', 'Port Said'),
(262, 24, 35, 'بورفؤاد', 'Port Fouad'),
(264, 25, 22, 'فايد', 'Fayed'),
(265, 25, 22, 'التل الكبير', 'El Tal El Kabir'),
(266, 25, 22, 'القنطرة شرق', 'Qantara Sharq'),
(267, 25, 22, 'القنطرة غرب', 'Qantara Gharb'),
(268, 25, 22, 'ابو صوير', 'Abu Suweir'),
(269, 25, 22, 'القصاصين', 'El Qassasin'),
(271, 26, 26, 'عتاقة', 'Ataqa'),
(272, 26, 26, 'الجناين', 'El Ganayen'),
(273, 26, 26, 'حى فيصل', 'HaiFaisal'),
(274, 27, 37, 'دمياط', 'Damietta'),
(275, 27, 37, 'دمياط الجديدة', 'New Damietta'),
(276, 27, 37, 'راس البر', 'Ras El Bar'),
(277, 27, 37, 'فارسكور', 'Faraskour'),
(278, 27, 37, 'كفر سعد', 'Kafr Saad'),
(279, 27, 37, 'الزرقا', 'El Zarqa'),
(280, 27, 37, 'السرو', 'El Sarw'),
(281, 27, 37, 'عزبة البرج', 'Ezbet El Borg'),
(282, 27, 37, 'ميت ابو غالب', 'Meet Abu Ghaleb'),
(283, 3, 17, 'حدائق الاهرام', 'Hadayk Elahram'),
(284, 2, 20, 'الازاريطه', 'alazareta'),
(286, 25, 22, 'المنايف', 'El Manaif'),
(287, 25, 22, 'سرابيوم', 'Serapeum'),
(288, 25, 22, 'نفيشه', 'Nefesha'),
(382, 2, 20, 'البرج الكعد', 'Al Borg Al Kaad'),
(383, 2, 20, 'الدخيله', 'El Dekheila'),
(384, 2, 20, 'الرمل', 'El Raml'),
(385, 2, 20, 'الساحل الشمالى', 'North Coast'),
(386, 2, 20, 'السيوف', 'El Seyouf'),
(387, 2, 20, 'العصافره', 'El Asafra'),
(388, 2, 20, 'العويدات الراس السوده', 'El Awedat Ras El Soda'),
(389, 2, 20, 'الوزراه', 'El Wozara'),
(390, 2, 20, 'ابيس', 'Abis'),
(391, 2, 20, 'برج العرب الجديده', 'Borg El Arab El Gedida'),
(392, 2, 20, 'بنجر السكر', 'Banger El Sokar'),
(393, 2, 20, 'جليم', 'Gleem'),
(394, 2, 20, 'خورشيد', 'Khorshid'),
(395, 2, 20, 'رشدى', 'Roushdy'),
(396, 2, 20, 'زيزينيا', 'Zizinia'),
(397, 2, 20, 'سان ستيفانو', 'San Stefano'),
(398, 2, 20, 'ستانلى', 'Stanley'),
(399, 2, 20, 'سيتى سنتر', 'City Center'),
(400, 2, 20, 'سيدى بشر', 'Sidi Bishr'),
(401, 2, 20, 'سيدى كرير', 'Sidi Kreir'),
(402, 2, 20, 'طوسون', 'Tosson'),
(403, 2, 20, 'كرموس', 'Karmouz'),
(404, 2, 20, 'كفر عبده', 'Kafr Abdo'),
(405, 2, 20, 'لوران', 'Louran'),
(406, 2, 20, 'منشيه', 'Mansheya'),
(407, 2, 20, 'محطه مصر', 'Mahatet Masr'),
(408, 2, 20, 'المعموره', 'El Maamoura'),
(409, 2, 20, 'ميامى', 'Miami'),
(410, 2, 20, 'مينا البصل', 'Mina El Basal'),
(411, 17, 23, 'البغدادى', 'Al Baghdadi'),
(412, 18, 19, 'السيل', 'El Seil'),
(413, 17, 23, 'القرنه الجديده', 'New Qurna'),
(414, 17, 23, 'الكرنك', 'El Karnak'),
(415, 17, 23, 'مدينه الاقصر الجديده', 'New Luxor City'),
(416, 17, 23, 'مدينه الاقصر', 'Luxor City'),
(420, 19, 24, 'الجونه', 'El Gouna'),
(421, 19, 24, 'الزعفرانه', 'El Zafarana'),
(422, 7, 44, 'الرحمانيه', 'El Rahmaniya'),
(423, 7, 44, 'ادفينا', 'Edko'),
(424, 7, 44, 'ابو المطامير', 'Abu El Matamir'),
(555, 3, 41, 'ابو رواش', 'Abu Rawash'),
(556, 3, 18, 'ارض اللواء', 'Ard El Lewa'),
(557, 3, 18, 'البراجيل', 'El Baragil'),
(558, 3, 43, 'التعاون', 'El Taawon'),
(559, 3, 18, 'الزمالك', 'Zamalek'),
(560, 3, 43, 'اللبينى هرم', 'El Lebeny Haram'),
(561, 3, 41, 'المناشى', 'El Manashi'),
(562, 3, 18, 'المنيب', 'El Moneeb'),
(563, 3, 18, 'المهندسين', 'Mohandessin'),
(564, 3, 18, 'الوراق', 'El Warraq'),
(565, 3, 18, 'بشتيل', 'Bashteel'),
(566, 3, 41, 'بهرمس', 'Bahramas'),
(567, 3, 43, 'ترسه', 'Tersa'),
(568, 3, 18, 'ساقيه مكى', 'Saqiyet Mekki'),
(569, 3, 41, 'سقاره', 'Saqqara'),
(570, 3, 41, 'سقيل', 'Saqil'),
(571, 3, 43, 'سهل حمزه', 'Sahl Hamza'),
(572, 3, 41, 'شبرامنت', 'Shubramant'),
(573, 3, 18, 'صفط اللبن', 'Saft El Laban'),
(574, 3, 41, 'الكوم الاحمر', 'El Koum El Ahmar'),
(575, 3, 18, 'الكيت كات', 'Kit Kat'),
(576, 3, 18, 'الحوامديه', 'El Hawamdeya'),
(577, 3, 41, 'الواحات البحريه', 'El Wahat El Bahariya'),
(578, 3, 41, 'كفر غطاطى', 'Kafr Ghataty'),
(579, 3, 43, 'منشاه البكارى', 'Mansheyet El Bakary'),
(580, 3, 18, 'منيل شيحا', 'Manyal Shiha'),
(581, 3, 18, 'ميدان الجيزه', 'Giza Square'),
(582, 3, 41, 'ناهيه', 'Nahya'),
(583, 3, 41, 'نزله السمان', 'Nazlet El Saman'),
(584, 3, 41, 'ناهيا البلد', 'Nahya El Balad'),
(585, 6, 25, 'الكردى', 'El Kurdi'),
(586, 6, 25, 'المطريه - الدقهليه', 'El Matareya'),
(587, 6, 25, 'بنى عبيد', 'Bani Ubaid'),
(588, 6, 25, 'محله دمنه', 'Mahalat Damana'),
(589, 6, 25, 'منيه النصر', 'Minyat El Nasr'),
(590, 6, 25, 'ميت سلسيل', 'Meet Salsil'),
(591, 26, 26, 'الادبيه', 'El Adabiya'),
(592, 26, 26, 'العين السخنه', 'Ain Sokhna'),
(593, 26, 26, 'الطاقه', 'El Taqa'),
(594, 26, 26, 'الاربعين', 'El Arbaeen'),
(595, 26, 26, 'حى السويس', 'Suez District'),
(596, 5, 27, 'الصالحيه', 'El Salhiya'),
(597, 5, 27, 'القرين', 'El Qurein'),
(598, 5, 27, 'القنايات', 'El Qanayat'),
(599, 5, 27, 'المنى', 'El Mune'),
(600, 5, 27, 'انشاص', 'Inshas'),
(601, 5, 27, 'صان الحجر', 'San El Hagar'),
(602, 5, 27, 'مشتول السوق', 'Mashtoul El Souq'),
(603, 11, 29, 'ابشواى', 'Epshway'),
(604, 11, 29, 'الشواشنه', 'El Shawashna'),
(605, 11, 29, 'العجميين', 'El Agmyeen'),
(606, 11, 29, 'العزب', 'El Ouzab'),
(607, 11, 29, 'الفهميه', 'El Fahmeya'),
(608, 11, 29, 'الفيوم الجديده', 'New Fayoum'),
(609, 11, 29, 'مدينه الفيوم', 'Fayoum City'),
(610, 11, 29, 'سرسنا', 'Sarsena'),
(611, 11, 29, 'سنهور', 'Sanhour'),
(612, 11, 29, 'كفور النيل', 'Kafour El Nile'),
(613, 11, 29, 'منشاه عبد الله', 'Manshaet Abdallah'),
(614, 11, 29, 'منشيه الجمل', 'Mansheyet El Gammal'),
(615, 11, 29, 'وادى الريان', 'Wadi El Rayan'),
(729, 1, 15, 'التحرير', 'El Tahrir'),
(730, 1, 16, 'الرحاب1', 'Al Rehab 1'),
(731, 1, 16, 'الرحاب2', 'Al Rehab 2'),
(732, 1, 13, 'السواح', 'El Sawah'),
(733, 1, 15, 'السيده عائشه', 'Sayeda Aisha'),
(734, 1, 15, 'السيده نفيسه', 'Sayeda Nafisa'),
(735, 1, 16, 'الشروق', 'El Shorouk'),
(736, 1, 41, 'العاصمه الاداريه', 'New Administrative Capital'),
(737, 1, 15, 'الفسطاط', 'El Fustat'),
(738, 1, 16, 'القاهره الجديده - التجمع الاول', 'New Cairo - First Settlement'),
(739, 1, 16, 'القاهره الجديده - التجمع الثالث', 'New Cairo - Third Settlement'),
(740, 1, 16, 'القاهره الجديده - التجمع الخامس', 'New Cairo - Fifth Settlement'),
(741, 1, 16, 'القاهره الجديده - التسعين الشمالى', 'New Cairo - North 90 Street'),
(742, 1, 16, 'القاهره الجديده - التسعين الجنوبى', 'New Cairo - South 90 Street'),
(743, 1, 16, 'القاهره الجديده - المستثمرين جنوبيه', 'New Cairo - South Investors'),
(744, 1, 16, 'القاهره الجديده - المستثمرين شماليه', 'New Cairo - North Investors'),
(745, 1, 16, 'القاهره الجديده - جنوب اكاديميه الشرطه', 'New Cairo - South of Police Academy'),
(746, 1, 16, 'القاهره الجديده - ميراج سيتى', 'New Cairo - Mirage City'),
(747, 1, 16, 'القاهره الجديده - اربيلا', 'New Cairo - Arabella'),
(748, 1, 16, 'القاهره الجديده - الدبلوماسيين', 'New Cairo - Diplomats District'),
(749, 1, 14, 'القاهره السد العالى', 'Cairo High Dam'),
(750, 1, 16, 'القطاميه', 'El Katameya'),
(751, 1, 13, 'المرج الجديده', 'El Marg El Gedida'),
(752, 1, 16, 'المعادى دائرى', 'Maadi Ring Road'),
(753, 1, 15, 'بهتيم', 'Bahtim'),
(754, 1, 15, 'جاردن سيتى', 'Garden City'),
(755, 1, 13, 'جسر السويس', 'Gisr El Suez'),
(756, 1, 13, 'حدائق الزيتون', 'Hadayek El Zaitoun'),
(757, 1, 13, 'حدائق القبه', 'Hadayek El Qobba'),
(758, 1, 14, 'حدائق المعادى', 'Hadayek El Maadi'),
(759, 1, 14, 'حدائق حلوان', 'Hadayek Helwan'),
(760, 1, 13, 'حلميه الزيتون', 'Helmeyet El Zaitoun'),
(761, 1, 14, 'زهراء المعادى', 'Zahraa El Maadi'),
(762, 1, 13, 'سرايا القبه', 'Saray El Qobba'),
(763, 1, 12, 'العباسيه', 'Abbasiya'),
(764, 1, 13, 'عزبه النخل', 'Ezbet El Nakhl'),
(765, 1, 13, 'عزبه الهجانه', 'Ezbet El Hagana'),
(766, 1, 15, 'الكابلات', 'El Kablât'),
(767, 1, 14, 'كوتسيكا', 'Kotsika'),
(768, 1, 16, 'المستقبل', 'El Mostakbal City'),
(769, 1, 15, 'منيل الروضه', 'Manyal El Roda'),
(770, 1, 15, 'الحسين', 'El Hussein'),
(771, 1, 15, 'الدراسه', 'El Darasa'),
(772, 1, 15, 'باب اللوق', 'Bab El Louq'),
(774, 1, 15, 'الاسعاف', 'El Esaaf'),
(775, 1, 15, 'الاوبرا', 'Opera'),
(776, 1, 15, 'الجماليه', 'El Gamaleya'),
(777, 1, 15, 'السبتيه', 'El Sabtiya'),
(778, 1, 15, 'الضاهر', 'El Daher'),
(779, 1, 15, 'العتبه', 'El Ataba'),
(780, 1, 15, 'الفجاله', 'El Faggala'),
(781, 1, 15, 'رمسيس', 'Ramses'),
(782, 1, 15, 'غمره', 'Ghamra'),
(783, 1, 15, 'ماسبيرو', 'Maspero'),
(784, 4, 30, 'ابوزعبل', 'Abu Zaabal'),
(785, 4, 30, 'القلج', 'El Qalag'),
(786, 4, 30, 'باسوس', 'Bassous'),
(787, 4, 30, 'بلقس', 'Bilqas'),
(788, 4, 30, 'شبين القناطر', 'Shibin El Qanater'),
(789, 3, 41, 'منشاه القناطر', 'Manshaat El Qanater'),
(790, 4, 30, 'قها', 'Qaha'),
(791, 4, 30, 'ميت نعمه', 'Meet Naama'),
(801, 18, 19, 'ابوسمبل السياحيه', 'Abu Simbel Tourist'),
(802, 18, 19, 'البصيليه', 'El Basiliya'),
(803, 18, 19, 'الرديسيه', 'El Radiseya'),
(804, 18, 19, 'السباعيه', 'El Sibaiya'),
(805, 18, 19, 'السد العالى', 'High Dam'),
(806, 18, 19, 'كلابشه', 'Kalabsha'),
(807, 18, 19, 'كوم امبو البياره السياحيه', 'Kom Ombo Bayara Tourist'),
(817, 14, 21, 'الاباديرى', 'Al Abadari'),
(818, 14, 21, 'الزاويه', 'Al Zawya'),
(819, 14, 21, 'العوامر', 'Al Awamer'),
(820, 14, 21, 'بنى حسين', 'Bani Hussein'),
(821, 14, 21, 'درونكه', 'Dronka'),
(822, 14, 21, 'ريفه', 'Reefa'),
(823, 14, 21, 'ساحل سليم', 'Sahel Selim'),
(824, 14, 21, 'منقباد', 'Monqabad'),
(825, 12, 34, 'الكريمات', 'Al Karimat'),
(826, 12, 34, 'بنى سويف الجديده', 'New Beni Suef'),
(827, 12, 34, 'حدائق القبو - الدويدار', 'Hadayek El Qubba - El Dweidar'),
(828, 12, 34, 'ناصر الشناويه', 'Naser El Shanaweya'),
(829, 24, 35, 'الحى الجنوبى', 'Al Hay Al Janoubi'),
(830, 24, 35, 'حى السلام', 'Hay Al Salam'),
(831, 24, 35, 'الزهور', 'Al Zahour'),
(832, 24, 35, 'الضواحى', 'Al Dawahi'),
(833, 24, 35, 'حى العرب', 'Hay Al Arab'),
(834, 24, 35, 'المناخ', 'Al Manakh'),
(839, 27, 37, 'الروضه', 'Al Rawda'),
(840, 27, 37, 'جمصه', 'Gamasa'),
(841, 27, 37, 'كفر البطيخ', 'Kafr El Batikh'),
(853, 15, 38, 'الجزيره', 'Al Gezira'),
(854, 15, 38, 'الغرذات', 'Al Ghardat'),
(855, 15, 38, 'الكوثر', 'Al Kawthar'),
(856, 15, 38, 'المراغه', 'Al Maragha'),
(857, 15, 38, 'برديس', 'Bardis'),
(858, 15, 38, 'ساقتله', 'Saqatlah'),
(859, 15, 38, 'شطوره', 'Shotoura'),
(860, 15, 38, 'شندويل', 'Shandweel'),
(861, 15, 38, 'غينه', 'Ghina'),
(862, 15, 38, 'نجع النجار', 'Nag El Nagar'),
(868, 16, 39, 'الوقف', 'Al Waqf'),
(869, 1, 16, 'بدر', 'Badr'),
(870, 9, 28, 'سملا', 'Samla'),
(871, 9, 28, 'بشبيش', 'Bashbesh'),
(872, 16, 39, 'نجع سعيد', 'Nagasaaid'),
(873, 11, 29, 'السلخانه', 'elsalakhana'),
(874, 26, 26, 'حوض الدرس', 'Hoodeldrs'),
(875, 25, 22, 'الدبابات', 'Eldbabat'),
(876, 18, 19, 'حى العقاد', 'Haielakad'),
(877, 2, 20, 'كوبرى الناموس', 'Coprialnamous'),
(878, 3, 41, 'حدائق اكتوبر', 'Hadayic october'),
(879, 16, 39, 'بهجوره', 'Bahgora'),
(880, 3, 43, 'فيصل', 'Faysal'),
(881, 1, 13, 'النهضه', 'Elnahda'),
(883, 25, 22, 'حى الشيخ زايد الكوكاكولا', 'Haialshikhzaidelkokakola'),
(884, 11, 29, 'منشاه لطف الله', 'Monshaatlotfallah'),
(885, 2, 20, 'المراغى', 'Almaraghy'),
(886, 1, 16, 'مدينتى', 'Madenti'),
(887, 1, 13, 'الف مسكن', 'Alfmaskan'),
(888, 26, 26, 'حى الكوثر', 'Hai Elkawyher'),
(889, 26, 26, 'منطقه السلام', 'Manteka elsalam'),
(890, 7, 44, 'نديبه', 'Nedepa'),
(891, 2, 20, 'فلمنج', 'Fleming'),
(892, 24, 35, 'حى الكويت', 'Hai Elkuiwt'),
(893, 1, 14, 'عرب المعادى', 'Arab Elmaadi'),
(894, 1, 12, 'الدويقه', 'Eldeweaa'),
(896, 1, 12, 'زهراء مدينه نصر', 'Zahraa madenet nasr'),
(897, 18, 19, 'المحموديه', 'Elmahmodya'),
(898, 2, 20, 'الابراهيميه', 'Alebrahimia'),
(899, 1, 15, 'باب الخلق', 'Bab elkhalk'),
(902, 25, 22, 'حي السلام', 'Hai elsalam'),
(903, 2, 20, 'العوايد', 'Elawayd'),
(904, 2, 20, 'بوكلى', 'Pokly'),
(905, 18, 19, 'منطقه حى السلام', 'Manteqt hai elsalam'),
(906, 2, 20, 'فيكتوريا', 'Victoria'),
(907, 25, 22, 'ارض الجمعيات', 'Ard elgameyat'),
(908, 25, 22, 'الاسماعيليه', 'Elesmaelia'),
(909, 14, 21, 'اسيوط', 'Asywt'),
(910, 18, 19, 'اسوان', 'Aswan'),
(911, 26, 26, 'التوفيقيه', 'altawfekia'),
(912, 11, 29, 'المسله', 'Almsla'),
(913, 6, 25, 'منيه سمنود', 'Minet samanod'),
(914, 2, 20, 'الفلكى', 'Alfalaki'),
(915, 2, 20, 'باكوس', 'Bakos'),
(916, 2, 20, 'سابا باشا', 'Saba basha'),
(917, 26, 26, 'الملاحه', 'Elmalaha'),
(918, 1, 15, 'الامام الشافعى', 'Alemamelshafie'),
(919, 2, 20, 'الشاطبى', 'Elshatbe'),
(920, 2, 20, 'الحضره', 'Elhadra'),
(921, 1, 13, 'حمامات القبه', 'Hamamat elkoba');

-- --------------------------------------------------------

--
-- Table structure for table `client`
--

CREATE TABLE IF NOT EXISTS `client` (
  `CLIENT_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `CLIENT_NAME` varchar(100) NOT NULL,
  `CLIENT_EMAIL` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`CLIENT_ID`),
  UNIQUE KEY `CLIENT_EMAIL` (`CLIENT_EMAIL`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE IF NOT EXISTS `companies` (
  `COM_ID` int(11) NOT NULL AUTO_INCREMENT,
  `COM_NAME` varchar(30) NOT NULL,
  `COM_TEL` varchar(255) NOT NULL,
  `COM_FAX` varchar(255) NOT NULL,
  `COM_MOBILE` varchar(255) NOT NULL,
  `COM_EMAIL` varchar(255) NOT NULL,
  `CITY_ID` int(20) DEFAULT NULL,
  `COM_ADDRESS` text NOT NULL,
  `LOGOBRAND` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`COM_ID`),
  UNIQUE KEY `COM_NAME` (`COM_NAME`),
  KEY `COMPANY_R_CITY_ID` (`CITY_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `cost_centers`
--

CREATE TABLE IF NOT EXISTS `cost_centers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `code` varchar(20) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `fk_cost_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `countries`
--

CREATE TABLE IF NOT EXISTS `countries` (
  `CO_ID` int(11) NOT NULL AUTO_INCREMENT,
  `CO_NAME` varchar(50) NOT NULL,
  `CO_NAME_EN` varchar(50) NOT NULL,
  PRIMARY KEY (`CO_ID`),
  UNIQUE KEY `CO_NAME` (`CO_NAME`),
  UNIQUE KEY `CO_NAME_EN` (`CO_NAME_EN`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `countries`
--

INSERT INTO `countries` (`CO_ID`, `CO_NAME`, `CO_NAME_EN`) VALUES
(1, 'مصر', 'Egypt');

-- --------------------------------------------------------

--
-- Table structure for table `crm_order_logs`
--

CREATE TABLE IF NOT EXISTS `crm_order_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ORDER_ID` bigint(20) NOT NULL,
  `EMPLOYEE_ID` int(11) NOT NULL,
  `employee_type` enum('admin','delivery','marketing','other') DEFAULT NULL,
  `ACTION_TYPE` enum('call','whatsapp','sms','note','status_change') DEFAULT NULL,
  `RESULT` enum('confirmed','editaddress','postponement','no_answer','phone_closed','call_later','cancelled','wrong_number','recall') DEFAULT NULL,
  `call_duration_sec` int(11) DEFAULT NULL,
  `NEXT_FOLLOW_UP` datetime DEFAULT NULL,
  `NOTES` text DEFAULT NULL,
  `CREATED_AT` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_crm_orders_orderid` (`ORDER_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

CREATE TABLE IF NOT EXISTS `currencies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(10) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `symbol` varchar(10) DEFAULT NULL,
  `exchange_rate` decimal(18,8) DEFAULT 1.00000000,
  `is_default` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `custody_orders`
--

CREATE TABLE IF NOT EXISTS `custody_orders` (
  `ORDER_ID` bigint(20) NOT NULL,
  `USER_ID` bigint(20) NOT NULL,
  `SHIPPING_ADDRESS` text NOT NULL,
  `RETURNS` tinyint(1) NOT NULL DEFAULT 0,
  `CUST_STAT` enum('WAITING','FREEZE','REFUND','DELIVERED','SETTLEMENT') NOT NULL DEFAULT 'WAITING',
  `ORDER_STATUSE` int(11) NOT NULL,
  `CREATE_AT` timestamp NOT NULL DEFAULT current_timestamp(),
  `LAST_UPDATE` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ORDER_ID`,`USER_ID`),
  KEY `custody_orders_ibfk_2` (`USER_ID`),
  KEY `idx_custody_orders_id_status` (`ORDER_ID`,`ORDER_STATUSE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `custody_product`
--

CREATE TABLE IF NOT EXISTS `custody_product` (
  `ORDER_ID` bigint(20) NOT NULL,
  `USER_ID` bigint(20) NOT NULL,
  `PRO_CODE` varchar(50) NOT NULL,
  `QTY` decimal(18,0) NOT NULL,
  `PRICE` decimal(18,2) NOT NULL,
  `DISCOUNT` decimal(18,2) NOT NULL,
  `DELIVERED_QTY` decimal(18,2) NOT NULL DEFAULT 0.00,
  `RETURNED_QTY` decimal(18,2) NOT NULL DEFAULT 0.00,
  `PRO_STATUSE` enum('waiting','partial','complete','rejected','freeze') NOT NULL DEFAULT 'waiting',
  `LAST_UPDATE` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ORDER_ID`,`USER_ID`,`PRO_CODE`),
  KEY `product_code_update_cascad` (`PRO_CODE`),
  KEY `idx_custody_product_orderid_status` (`ORDER_ID`,`PRO_STATUSE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='عهدة المندوبين من منتجات اوامر البيع';


-- --------------------------------------------------------

--
-- Table structure for table `custody_return_orders`
--

CREATE TABLE IF NOT EXISTS `custody_return_orders` (
  `ORDER_ID` bigint(20) NOT NULL,
  `USER_ID` bigint(20) NOT NULL,
  `SHIPPING_ADDRESS` text NOT NULL,
  `RETURNS` tinyint(1) NOT NULL DEFAULT 0,
  `CUST_STAT` enum('WAITING','RETURNED') NOT NULL DEFAULT 'WAITING',
  `ORDER_STATUSE` int(11) NOT NULL,
  `CREATE_AT` timestamp NOT NULL DEFAULT current_timestamp(),
  `LAST_UPDATE` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ORDER_ID`,`USER_ID`),
  KEY `custody_return_orders_ibfk_2` (`USER_ID`),
  KEY `idx_custody_return_orders_id_status_2` (`ORDER_ID`,`ORDER_STATUSE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='عهدة المندوبين اوامر البيع المرتجعه';

-- --------------------------------------------------------

--
-- Table structure for table `custody_return_product`
--

CREATE TABLE IF NOT EXISTS `custody_return_product` (
  `ORDER_ID` bigint(20) NOT NULL,
  `USER_ID` bigint(20) NOT NULL,
  `PRO_CODE` varchar(50) NOT NULL,
  `QTY` decimal(18,0) NOT NULL,
  `PRICE` decimal(18,2) NOT NULL,
  `DISCOUNT` decimal(18,2) NOT NULL,
  `DELIVERED_QTY` decimal(18,2) NOT NULL DEFAULT 0.00,
  `RETURNED_QTY` decimal(18,2) NOT NULL DEFAULT 0.00,
  `PRO_STATUSE` enum('partial','complete','partial_refund','refund') NOT NULL DEFAULT 'complete',
  `LAST_UPDATE` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ORDER_ID`,`USER_ID`,`PRO_CODE`),
  KEY `custody_return_product_code_update_cascad` (`PRO_CODE`),
  KEY `idx_custody_return_product_orderid_status2` (`ORDER_ID`,`PRO_STATUSE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='عهدة المندوبين من منتجات مرتجع اوامر البيع';

-- --------------------------------------------------------

--
-- Table structure for table `entities`
--

CREATE TABLE IF NOT EXISTS `entities` (
  `ENTITY_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `CREATE_AT` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ENTITY_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `financial_transactions`
--

CREATE TABLE IF NOT EXISTS `financial_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `transaction_user_id` bigint(20) DEFAULT NULL,
  `transaction_date` datetime NOT NULL DEFAULT current_timestamp(),
  `invoice_id` bigint(20) DEFAULT NULL,
  `entity_type` enum('CLIENT','SUPPLIER','EMPLOYEE','DELIVERY','MARKETER') DEFAULT NULL,
  `entity_id` bigint(20) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `status` enum('COMPLETED','CANCELLED','DEFERRED') DEFAULT 'COMPLETED',
  `note` text DEFAULT NULL,
  `accounting_posted` tinyint(1) NOT NULL DEFAULT 0,
  `accounting_entry_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_method_id` (`payment_method_id`),
  KEY `category_id` (`category_id`),
  KEY `transaction_USER_ID` (`transaction_user_id`),
  KEY `branch_id` (`branch_id`),
  KEY `fk_financial_transactions_journal` (`accounting_entry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `financial_years`
--

CREATE TABLE IF NOT EXISTS `financial_years` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year_name` varchar(50) NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `financial_years`
--

INSERT INTO `financial_years` (`id`, `year_name`, `date_from`, `date_to`, `is_closed`, `created_at`) VALUES
(4, '2026', '2026-01-01', '2026-12-31', 0, '2026-07-23 10:11:47');

-- --------------------------------------------------------

--
-- Table structure for table `gov`
--

CREATE TABLE IF NOT EXISTS `gov` (
  `GOV_ID` int(11) NOT NULL AUTO_INCREMENT,
  `COUNTRY_ID` int(11) NOT NULL,
  `GOV_NAME` varchar(50) NOT NULL,
  `REGIONAL_AREAS_ID` int(11) DEFAULT NULL,
  `GOV_NAME_EN` varchar(50) NOT NULL,
  PRIMARY KEY (`GOV_ID`),
  UNIQUE KEY `GOV_NAME` (`GOV_NAME`),
  UNIQUE KEY `GOV_NAME_EN` (`GOV_NAME_EN`),
  KEY `GOV_COUNTRY_ID_COUNTRIES_CO_ID` (`COUNTRY_ID`),
  KEY `GOV_REGIONAL_AREAS_ID_REGIONAL_AREAS_ID` (`REGIONAL_AREAS_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `gov`
--

INSERT INTO `gov` (`GOV_ID`, `COUNTRY_ID`, `GOV_NAME`, `REGIONAL_AREAS_ID`, `GOV_NAME_EN`) VALUES
(1, 1, 'القاهره', 1, 'Cairo'),
(2, 1, 'الاسكندريه', 2, 'Alexandria'),
(3, 1, 'الجيزه', 1, 'Giza'),
(4, 1, 'القليوبيه', 1, 'Qalyubia'),
(5, 1, 'الشرقيه', 4, 'Sharqia'),
(6, 1, 'الدقهليه', 3, 'Dakahlia'),
(7, 1, 'البحيره', 2, 'Beheira'),
(8, 1, 'كفر الشيخ', 3, 'Kafr El Sheikh'),
(9, 1, 'الغربيه', 3, 'Gharbia'),
(10, 1, 'المنوفيه', 3, 'Menofia'),
(11, 1, 'الفيوم', 5, 'Faiyum'),
(12, 1, 'بنى سويف', 5, 'Beni Suef'),
(13, 1, 'المنيا', 5, 'Minya'),
(14, 1, 'اسيوط', 6, 'Assiut'),
(15, 1, 'سوهاج', 7, 'Sohag'),
(16, 1, 'قنا', 7, 'Qena'),
(17, 1, 'الاقصر', 7, 'Luxor'),
(18, 1, 'اسوان', 7, 'Aswan'),
(19, 1, 'البحر الأحمر', 4, 'Red Sea'),
(20, 1, 'الوادي الجديد', 6, 'New Valley'),
(21, 1, 'مطروح', 2, 'Matrouh'),
(22, 1, 'شمال سيناء', 4, 'North Sinai'),
(23, 1, 'جنوب سيناء', 4, 'South Sinai'),
(24, 1, 'بورسعيد', 4, 'Port Said'),
(25, 1, 'الاسماعيليه', 4, 'Ismailia'),
(26, 1, 'السويس', 4, 'Suez'),
(27, 1, 'دمياط', 3, 'Damietta');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_counts`
--

CREATE TABLE IF NOT EXISTS `inventory_counts` (
  `COUNT_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `COUNT_CODE` varchar(30) NOT NULL,
  `BRANCH_ID` int(11) NOT NULL,
  `STORE_ID` int(11) NOT NULL,
  `COUNT_DATE` datetime NOT NULL,
  `STATUS` tinyint(4) NOT NULL DEFAULT 0 COMMENT '\n0=DRAFT\n1=COUNTING\n2=APPROVED\n3=CANCELLED',
  `NOTE` text DEFAULT NULL,
  `INVENTORY_MOVEMENT_ID` bigint(20) DEFAULT NULL,
  `JOURNAL_ENTRY_ID` bigint(20) DEFAULT NULL,
  `CREATED_BY` bigint(20) NOT NULL,
  `CREATED_AT` timestamp NOT NULL DEFAULT current_timestamp(),
  `UPDATED_BY` bigint(20) DEFAULT NULL,
  `UPDATED_AT` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `APPROVED_BY` bigint(20) DEFAULT NULL,
  `APPROVED_AT` datetime DEFAULT NULL,
  PRIMARY KEY (`COUNT_ID`),
  UNIQUE KEY `COUNT_CODE` (`COUNT_CODE`),
  KEY `BRANCH_ID` (`BRANCH_ID`),
  KEY `STORE_ID` (`STORE_ID`),
  KEY `STATUS` (`STATUS`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `inventory_count_details`
--

CREATE TABLE IF NOT EXISTS `inventory_count_details` (
  `ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `COUNT_ID` bigint(20) NOT NULL,
  `PRO_CODE` varchar(50) NOT NULL,
  `SYSTEM_QTY` decimal(18,4) NOT NULL,
  `ACTUAL_QTY` decimal(18,4) DEFAULT NULL,
  `DIFFERENCE_QTY` decimal(18,4) DEFAULT 0.0000,
  `UNIT_COST` decimal(18,4) DEFAULT 0.0000,
  `DIFFERENCE_COST` decimal(18,4) DEFAULT 0.0000,
  `NOTE` text DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `COUNT_ID` (`COUNT_ID`),
  KEY `PRO_CODE` (`PRO_CODE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_movement`
--

CREATE TABLE IF NOT EXISTS `inventory_movement` (
  `IM_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `CREATED_DATE` timestamp NOT NULL DEFAULT current_timestamp(),
  `IM_MOVE_TYPE` enum('RECEIVING','SHIPPING','SALES','TRANSFER','SETTLEMENT','REFUND','RETURN_SHIPP','RETURN_SALES','RETURN_SHIPP_SALSE','REFUNDPURCHISES') NOT NULL,
  `IM_USER` bigint(20) NOT NULL,
  `IM_BRANCH` int(11) NOT NULL,
  `STO_ID` int(11) NOT NULL,
  `STO_DEST` int(11) DEFAULT NULL,
  `REFERENCE` text DEFAULT NULL,
  `UNIQUE_ID` varchar(50) DEFAULT NULL,
  `IM_DETAILS` text DEFAULT '\'\'',
  `IM_APPROVAL_DISBURSED` tinyint(1) NOT NULL DEFAULT 0,
  `IM_APPROVAL_RECEIVED` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`IM_ID`),
  KEY `inventory_movement_ibfk_1` (`STO_ID`),
  KEY `IM_USER` (`IM_USER`),
  KEY `IM_PRANCH` (`IM_BRANCH`),
  KEY `idx_im_reference_unique` (`REFERENCE`(50),`UNIQUE_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `inventory_product_movement`
--

CREATE TABLE IF NOT EXISTS `inventory_product_movement` (
  `IPM_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `IM_ID` bigint(20) NOT NULL,
  `ORDER_ID` bigint(20) DEFAULT NULL,
  `IPM_CODE` varchar(50) NOT NULL,
  `IPM_TYPE_MOV` enum('IN','OUT') NOT NULL,
  `IPM_QTY` decimal(18,4) NOT NULL,
  `IPM_STO_QTY` decimal(18,4) NOT NULL COMMENT 'رصيد المخزون بعد',
  `CREATE_AT` timestamp(6) NOT NULL DEFAULT current_timestamp(6),
  `IS_PROCESSED` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`IPM_ID`),
  KEY `IM_ID` (`IM_ID`),
  KEY `IPM_CODE` (`IPM_CODE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE IF NOT EXISTS `invoices` (
  `INVO_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `CREATE_AT` datetime NOT NULL DEFAULT current_timestamp(),
  `REFERENCE_ID` bigint(20) NOT NULL,
  `REFERENCE_TYPE` enum('SALES','PURCHASES') NOT NULL,
  `TOTAL_PRICE` float(12,2) NOT NULL,
  `RETURN_PRICE` float(12,2) NOT NULL,
  `REFUND_PRICE` float(12,2) NOT NULL DEFAULT 0.00 COMMENT 'قيمة المرتجع بعد البيع',
  `AMOUNT_PAID` float(12,2) NOT NULL,
  `PAYMENT_SUCCESS` tinyint(1) NOT NULL,
  PRIMARY KEY (`INVO_ID`),
  KEY `reference index` (`REFERENCE_ID`,`REFERENCE_TYPE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `journal_entries`
--

CREATE TABLE IF NOT EXISTS `journal_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entry_no` varchar(30) DEFAULT NULL,
  `fiscal_year` smallint(6) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `entry_date` datetime NOT NULL DEFAULT current_timestamp(),
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `total_debit` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_credit` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('DRAFT','POSTED','CANCELLED') DEFAULT 'POSTED',
  `posted_at` datetime DEFAULT NULL,
  `posted_by` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_entry_no` (`entry_no`),
  KEY `idx_entry_date` (`entry_date`),
  KEY `idx_reference` (`reference_type`,`reference_id`),
  KEY `idx_branch` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `journal_entry_details`
--

CREATE TABLE IF NOT EXISTS `journal_entry_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `journal_entry_id` int(11) NOT NULL,
  `line_no` smallint(6) NOT NULL,
  `account_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `store_id` int(11) DEFAULT NULL,
  `cost_center_id` int(11) DEFAULT NULL,
  `partner_type` enum('CLIENT','SUPPLIER','DELIVERY','MARKETER','EMPLOYEE') DEFAULT NULL,
  `partner_id` bigint(20) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` bigint(20) DEFAULT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `currency_id` int(11) DEFAULT NULL,
  `exchange_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_journal` (`journal_entry_id`),
  KEY `idx_account` (`account_id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_store` (`store_id`),
  KEY `idx_partner` (`partner_type`,`partner_id`),
  KEY `idx_cost_center` (`cost_center_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE IF NOT EXISTS `orders` (
  `ORDER_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `SUB_ORDER_ID` varchar(19) NOT NULL,
  `ORDER_TYPE_ID` int(20) NOT NULL,
  `DELIVERY_ID` bigint(20) DEFAULT NULL,
  `ORDER_BY` bigint(20) NOT NULL,
  `COUPON_CODE` varchar(10) DEFAULT NULL,
  `CITY_ID` int(11) NOT NULL,
  `ADDRESS_ID` bigint(20) NOT NULL,
  `ADDRESS` varchar(300) NOT NULL,
  `COMMENT` varchar(200) DEFAULT NULL,
  `TEL_ID` bigint(20) NOT NULL,
  `TELEPHONE` varchar(20) NOT NULL,
  `DATE_CREATED` datetime NOT NULL DEFAULT current_timestamp(),
  `DELIVERY_DATE` datetime NOT NULL,
  `DELIVERY_TYPE` int(1) NOT NULL DEFAULT 0,
  `SHIPPING_DATE` datetime DEFAULT NULL,
  `DELIVERED_DATE` timestamp NULL DEFAULT NULL COMMENT 'تاريخ التسليم',
  `SHIPPING_VAL` decimal(18,2) NOT NULL,
  `SHIPP_PAYMENT` tinyint(1) NOT NULL DEFAULT 1,
  `ORDER_STATUS` int(2) NOT NULL,
  `SALE_ENTRY_ID` int(11) DEFAULT NULL,
  `RETURN_ENTRY_ID` int(11) DEFAULT NULL,
  `EXISTS_ID` int(4) NOT NULL DEFAULT 1,
  `PRINT` tinyint(1) NOT NULL DEFAULT 0,
  `ORDER_SUCCESS` tinyint(1) NOT NULL,
  `STO_ID` int(11) NOT NULL,
  `LAST_UPDATE` datetime(3) NOT NULL DEFAULT current_timestamp(3) ON UPDATE current_timestamp(3),
  `EXCHANGE` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`ORDER_ID`),
  UNIQUE KEY `SUB_ORDER_ID` (`SUB_ORDER_ID`),
  UNIQUE KEY `EXCHANGE` (`EXCHANGE`),
  KEY `ORDERS_CITY_ID_CITY_CI_ID` (`CITY_ID`),
  KEY `ORDERS_DELIVERY_ID_USERS_USER_ID` (`DELIVERY_ID`),
  KEY `ORDER_TYPE_ID` (`ORDER_TYPE_ID`),
  KEY `ADDRESS_ID` (`ADDRESS_ID`),
  KEY `TEL_ID` (`TEL_ID`),
  KEY `STO_ID` (`STO_ID`),
  KEY `ORDER_BY` (`ORDER_BY`),
  KEY `orders_date_crated_index` (`DATE_CREATED`),
  KEY `orders_shipping_date_index` (`SHIPPING_DATE`),
  KEY `order_delivery_date_index` (`DELIVERY_DATE`) USING BTREE,
  KEY `orders_exists_id_index` (`EXISTS_ID`) USING BTREE,
  KEY `idx_last_update` (`LAST_UPDATE`),
  KEY `idx_orders_status` (`ORDER_STATUS`,`ORDER_ID`),
  KEY `fk_orders_sale_entry` (`SALE_ENTRY_ID`),
  KEY `fk_orders_return_entry` (`RETURN_ENTRY_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='اخر تعديل على امر البيع';


-- --------------------------------------------------------

--
-- Table structure for table `orders_log`
--

CREATE TABLE IF NOT EXISTS `orders_log` (
  `LOG_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `ORDER_ID` bigint(20) NOT NULL,
  `USER_ACTION` bigint(20) NOT NULL,
  `DATE_ACTION` datetime NOT NULL DEFAULT current_timestamp(),
  `LOG_TEXT` longtext NOT NULL,
  PRIMARY KEY (`LOG_ID`),
  KEY `ORDER_ID` (`ORDER_ID`),
  KEY `USER_ACTION` (`USER_ACTION`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `order_exists`
--

CREATE TABLE IF NOT EXISTS `order_exists` (
  `EXISTS_ID` int(11) NOT NULL AUTO_INCREMENT,
  `EXISTS_NAME` varchar(50) NOT NULL,
  `EXISTS_NAME_EN` varchar(50) NOT NULL,
  `STA_ID` int(3) DEFAULT NULL COMMENT 'حالة امر البيع عند الوصول  هنا',
  `TRANSFER` tinyint(1) NOT NULL DEFAULT 0,
  `FINANCE` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'اضافة اوامر البيع الى كشف حساب العميل',
  `USER_STORE` int(1) NOT NULL DEFAULT 0 COMMENT 'المخزون الذي يظهر للمسوق او العميل\r\nاذا كانت القيمه true يخصم جميع المنتجات من اصل اجمالى المخزون',
  `ADMIN_STORE` int(1) NOT NULL DEFAULT 0 COMMENT 'المخزون الذى يظهر لخدمة العملاء\r\nاذا كانت القيمه true يخصم جميع المنتجات من اصل اجمالى المخزون',
  `COLOR_CLASS` varchar(30) NOT NULL,
  PRIMARY KEY (`EXISTS_ID`),
  UNIQUE KEY `EXISTS_NAME` (`EXISTS_NAME`),
  UNIQUE KEY `EXISTS_NAME_EN` (`EXISTS_NAME_EN`),
  KEY `STA_ID` (`STA_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `order_exists`
--

INSERT INTO `order_exists` (`EXISTS_ID`, `EXISTS_NAME`, `EXISTS_NAME_EN`, `STA_ID`, `TRANSFER`, `FINANCE`, `USER_STORE`, `ADMIN_STORE`, `COLOR_CLASS`) VALUES
(1, 'قيد المراجعه', 'Confirm sale orders', 0, 0, 1, 1, 0, 'badge-info'),
(2, 'مؤجل', 'pending sale orders', 0, 0, 1, 1, 1, 'badge-info'),
(3, 'غير متوفر', 'Advance booking orders', 0, 0, 1, 1, 0, 'badge-info'),
(4, 'العميل لايستجيب', 'The client is not responding', 0, 0, 1, 0, 0, 'badge-red20'),
(5, ' إلغاء امر البيع', 'Cancel order', 0, 0, 0, 0, 0, 'badge-danger'),
(6, 'تأكيد الشحن والتوصيل', 'Shipping Confirmation', 0, 1, 1, 1, 1, ''),
(7, 'طلبات قيد التجهيز', 'Processing', 1, 0, 1, 1, 1, 'badge-green10'),
(8, 'طلبات تم التجهيز', 'Prepared', 2, 0, 1, 1, 1, 'badge-green20'),
(9, 'طلبات جارى الشحن', 'Shipping', 2, 1, 1, 0, 0, 'badge-green60'),
(10, 'عهدة المندوبين', 'delivered ', NULL, 3, 1, 0, 0, 'badge-green100'),
(11, 'بيع مباشر', 'Direct sale', NULL, 0, 1, 1, 1, 'badge-praimary'),
(12, 'تتبع تأكيد الشحن من المندوبين', 'Track_shipping', 0, 0, 1, 1, 1, ''),
(13, 'الغاء لعدم الرد', 'customer not contacted', 0, 0, 0, 0, 0, '');

-- --------------------------------------------------------

--
-- Table structure for table `order_product`
--

CREATE TABLE IF NOT EXISTS `order_product` (
  `ORDER_ID` bigint(20) NOT NULL,
  `PRO_CODE` varchar(50) NOT NULL,
  `QTY` decimal(18,2) NOT NULL,
  `PRICE` decimal(18,2) NOT NULL,
  `COST_PRICE` decimal(18,4) NOT NULL,
  `DISCOUNT` decimal(18,2) NOT NULL DEFAULT 0.00,
  `DELIVERED_QTY` decimal(18,2) NOT NULL DEFAULT 0.00,
  `RETURNED_QTY` decimal(18,2) NOT NULL DEFAULT 0.00,
  `PRO_STATUSE` enum('waiting','complete','partial','rejected','partial_refund','refund') NOT NULL DEFAULT 'waiting',
  `LAST_UPDATE` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ORDER_ID`,`PRO_CODE`),
  UNIQUE KEY `ORDER_ID` (`ORDER_ID`,`PRO_CODE`),
  KEY `ORDER_PRODUCT_ibfk_2` (`PRO_CODE`),
  KEY `idx_order_product_orderid_status` (`ORDER_ID`,`PRO_STATUSE`)
) ;


-- --------------------------------------------------------

--
-- Table structure for table `order_service`
--

CREATE TABLE IF NOT EXISTS `order_service` (
  `OS_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `ORDER_ID` bigint(20) NOT NULL,
  `USER_ID` bigint(20) NOT NULL,
  `OST_ID` int(11) NOT NULL,
  `OS_DATE_OPEN` timestamp NOT NULL DEFAULT current_timestamp(),
  `OS_DATE_CLOSE` timestamp NULL DEFAULT NULL,
  `OS_STATUSE` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`OS_ID`),
  KEY `ORDERID_ID` (`ORDER_ID`),
  KEY `order_service_ibfk_2` (`USER_ID`),
  KEY `OS_TYPE` (`OST_ID`),
  KEY `idx_order_user` (`ORDER_ID`,`USER_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='جدول خدمة العملاء';


-- --------------------------------------------------------

--
-- Table structure for table `order_service_text`
--

CREATE TABLE IF NOT EXISTS `order_service_text` (
  `OSTX_ID` varchar(300) NOT NULL,
  `USER_ID` bigint(20) NOT NULL,
  `USER_TYPE` int(3) NOT NULL,
  `OS_ID` bigint(20) NOT NULL,
  `OSTX_DATE` timestamp NOT NULL DEFAULT current_timestamp(),
  `OSTX_MSG` varchar(1000) NOT NULL,
  `OSTX_READ` tinyint(1) NOT NULL DEFAULT 0,
  `OSTX_READ_DATE` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`OSTX_ID`),
  KEY `OS_ID` (`OS_ID`),
  KEY `USER_ID` (`USER_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `order_service_type`
--

CREATE TABLE IF NOT EXISTS `order_service_type` (
  `OST_ID` int(11) NOT NULL AUTO_INCREMENT,
  `OST_TYPE` varchar(50) NOT NULL,
  PRIMARY KEY (`OST_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `order_service_type`
--

INSERT INTO `order_service_type` (`OST_ID`, `OST_TYPE`) VALUES
(1, 'أخرى'),
(2, 'طلب استعجل الشحن');

-- --------------------------------------------------------

--
-- Table structure for table `order_statuse`
--

CREATE TABLE IF NOT EXISTS `order_statuse` (
  `STA_ID` int(11) NOT NULL AUTO_INCREMENT,
  `STA_NAME` varchar(50) NOT NULL,
  `STA_NAME_EN` varchar(50) NOT NULL,
  `COLOR_CLASS` varchar(25) NOT NULL,
  PRIMARY KEY (`STA_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `order_statuse`
--

INSERT INTO `order_statuse` (`STA_ID`, `STA_NAME`, `STA_NAME_EN`, `COLOR_CLASS`) VALUES
(0, 'قيد التأكيد', 'pending', 'dark'),
(1, 'جارى تجهيزالمنتج للشحن', 'Processing', 'primary '),
(2, 'جارى الشحن', 'Shipping', 'warning'),
(3, 'تم التسليم', 'Complete ', 'success '),
(4, 'تسليم جزئى', 'Partial ', 'info'),
(5, 'مرفوض', 'Rejected', 'danger'),
(6, 'مرتجع', 'Refund', 'danger'),
(7, 'مرتجع جزئى', 'Partial Refund', 'warning');

-- --------------------------------------------------------

--
-- Table structure for table `order_type`
--

CREATE TABLE IF NOT EXISTS `order_type` (
  `ORDER_TYPE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ORDER_TYPE_NAME` varchar(50) NOT NULL,
  PRIMARY KEY (`ORDER_TYPE_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `order_type`
--

INSERT INTO `order_type` (`ORDER_TYPE_ID`, `ORDER_TYPE_NAME`) VALUES
(1, 'إدارى'),
(2, 'مسوق'),
(3, 'وسيط بيع'),
(4, 'تاجر'),
(5, 'بيع مباشر');

-- --------------------------------------------------------

--
-- Table structure for table `partners_balance`
--

CREATE TABLE IF NOT EXISTS `partners_balance` (
  `ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `CREATE_AT` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `LAST_UPDATE` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `PARTNER_TYPE` enum('CLIENT','DELIVERY','MARKETER','SUPPLIER') NOT NULL,
  `PARTNER_ID` bigint(20) NOT NULL,
  `REFERENCE_ID` bigint(11) DEFAULT NULL,
  `TRANSACTION_TYPE` enum('CREDIT','DEBIT') NOT NULL,
  `BALANCE` float(12,2) NOT NULL,
  `NOTE` text NOT NULL,
  `TRANSACTION_CATEGORY_ID` int(3) NOT NULL DEFAULT 0 COMMENT 'نوع العمليه',
  `USE_OF_CREDIT` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `CREATE_AT` (`CREATE_AT`,`PARTNER_TYPE`,`PARTNER_ID`),
  KEY `idx_partner_balance` (`PARTNER_ID`,`PARTNER_TYPE`,`TRANSACTION_TYPE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE IF NOT EXISTS `products` (
  `PRO_ID` int(11) NOT NULL AUTO_INCREMENT,
  `CAT_ID` int(11) NOT NULL,
  `PRO_VISBLE` tinyint(1) NOT NULL,
  `PRO_NAME` varchar(255) NOT NULL,
  `PER_BOOKING` tinyint(1) NOT NULL,
  `PRO_DESCRIPTION` text NOT NULL,
  `LENGTH` double NOT NULL,
  `WIDTH` double NOT NULL,
  `HEIGHT` double NOT NULL,
  `WEIGHT` double NOT NULL,
  `PRO_IMG_LINK` text NOT NULL,
  PRIMARY KEY (`PRO_ID`),
  UNIQUE KEY `PRO_NAME` (`PRO_NAME`),
  KEY `PRODUCTS_CAT_ID_PRODUCT_CATEGORY_CAT_ID` (`CAT_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `product_category`
--

CREATE TABLE IF NOT EXISTS `product_category` (
  `CAT_ID` int(11) NOT NULL AUTO_INCREMENT,
  `CAT_NAME` varchar(255) NOT NULL,
  `CAT_PARENT` varchar(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`CAT_ID`),
  UNIQUE KEY `CAT_NAME` (`CAT_NAME`),
  KEY `PRODUCT_CATEGORY_CAT_PARENT_PRODUCT_CATEGORY_CAT_ID` (`CAT_PARENT`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `product_codes`
--

CREATE TABLE IF NOT EXISTS `product_codes` (
  `PRODUCT_CODE_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `PRODUCT_ID` int(11) NOT NULL,
  `PRO_CODE` varchar(50) NOT NULL,
  `CODE_PRICE` decimal(18,2) NOT NULL,
  `CODE_COST_PRICE` decimal(18,2) NOT NULL,
  `LOWEST_PRICE` decimal(18,2) NOT NULL,
  `CODE_MIN_STOCK` decimal(18,3) NOT NULL DEFAULT 20.000,
  `CODE_MAX_STOCK` decimal(18,3) NOT NULL DEFAULT 0.000,
  PRIMARY KEY (`PRODUCT_CODE_ID`),
  UNIQUE KEY `PRO_CODE` (`PRO_CODE`),
  KEY `PRODUCT_ID` (`PRODUCT_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `product_code_join_option`
--

CREATE TABLE IF NOT EXISTS `product_code_join_option` (
  `JOIN_ID` int(11) NOT NULL AUTO_INCREMENT,
  `PRODUCT_CODE` varchar(250) NOT NULL,
  `OPTION_VALUE_ID` int(11) NOT NULL,
  PRIMARY KEY (`JOIN_ID`),
  UNIQUE KEY `PRODUCT_CODE` (`PRODUCT_CODE`,`OPTION_VALUE_ID`),
  KEY `PRODUCT_CODE_JOIN_OPTION_ibfk_2` (`OPTION_VALUE_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `product_join_option_group`
--

CREATE TABLE IF NOT EXISTS `product_join_option_group` (
  `PRODUCT_ID` int(11) NOT NULL,
  `OPTION_GROUP_ID` int(11) NOT NULL,
  PRIMARY KEY (`PRODUCT_ID`,`OPTION_GROUP_ID`),
  KEY `OPTION_GROUP_ID` (`OPTION_GROUP_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_option`
--

CREATE TABLE IF NOT EXISTS `product_option` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `OPTION_ID` int(11) NOT NULL,
  `VALUE` varchar(250) NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `OPTION_ID_VALUE` (`OPTION_ID`,`VALUE`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Stand-in structure for view `product_options_view`
-- (See below for the actual view)
--
CREATE TABLE IF NOT EXISTS `product_options_view` (
`PRO_ID` int(11)
,`PRO_CODE` varchar(50)
,`PURCHASE_PRICE` decimal(18,2)
,`SELL_PRICE` decimal(18,2)
,`MIN_STOCK` decimal(18,3)
,`MAX_STOCK` decimal(18,3)
,`PRO_NAME` varchar(255)
,`OPT_IDS_JSON` mediumtext
,`OPT_JSON` mediumtext
);

-- --------------------------------------------------------

--
-- Table structure for table `product_option_group`
--

CREATE TABLE IF NOT EXISTS `product_option_group` (
  `OPTION_ID` int(11) NOT NULL AUTO_INCREMENT,
  `OPTION_NAME` varchar(250) NOT NULL,
  `OPTION_TYPE` int(11) NOT NULL,
  PRIMARY KEY (`OPTION_ID`),
  UNIQUE KEY `OPTION_NAME` (`OPTION_NAME`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `purchases_orders`
--

CREATE TABLE IF NOT EXISTS `purchases_orders` (
  `ORDER_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `CREATE_AT` timestamp NOT NULL DEFAULT current_timestamp(),
  `SUPPLIER_ID` bigint(20) DEFAULT NULL,
  `REFERANC` varchar(255) DEFAULT NULL,
  `TOTAL_PRICE` float(10,2) NOT NULL,
  `TOTAL_PAYMENTS` float(10,2) NOT NULL DEFAULT 0.00,
  `STATUSE` enum('DEFERRED','COMPLETED') NOT NULL DEFAULT 'DEFERRED',
  `NOTE` text DEFAULT NULL,
  PRIMARY KEY (`ORDER_ID`),
  KEY `SUPPLIER_ID` (`SUPPLIER_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Stand-in structure for view `purchases_orders_view`
-- (See below for the actual view)
--
CREATE TABLE IF NOT EXISTS `purchases_orders_view` (
`ORDER_ID` bigint(20)
,`CREATE_AT` timestamp
,`SUPPLIER_ID` bigint(20)
,`SUP_NAME` varchar(50)
,`NOTE` text
,`SUP_EMAIL` varchar(100)
,`TEL_NUMBER` varchar(15)
,`TOTAL_PRICE` float(10,2)
,`QTY_ORDER_TOTAL` decimal(32,2)
,`QTY_SUPPLIED_TOTAL` decimal(32,2)
,`QTY_ORDER_WAITING` decimal(33,2)
,`QTY_RETURN` decimal(54,2)
,`SUPPLIED_STATUSE` varchar(11)
,`PAYMENT_STATUSE` varchar(10)
,`RETURN_STATUS` int(1)
,`TOTAL_WAIT_PRICE` double(19,2)
,`TOTAL_ORDER_PRICE` double(19,2)
,`TOTAL_SUP_PRICE` double(19,2)
,`TOTAL_PAYMENTS` float(10,2)
,`PRODUCT_DETAILS` mediumtext
);

-- --------------------------------------------------------

--
-- Table structure for table `purchases_products`
--

CREATE TABLE IF NOT EXISTS `purchases_products` (
  `ORDER_ID` bigint(20) NOT NULL,
  `PRO_CODE` varchar(15) NOT NULL,
  `PURCHASE_UNIT_PRICE` float(10,2) NOT NULL,
  `QTY` decimal(10,2) NOT NULL,
  `QTY_SUPPLIED` decimal(10,2) NOT NULL,
  PRIMARY KEY (`ORDER_ID`,`PRO_CODE`),
  KEY `purchases_products_ibfk_2` (`PRO_CODE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `purchases_return_orders`
--

CREATE TABLE IF NOT EXISTS `purchases_return_orders` (
  `ORDER_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `CREATE_AT` timestamp NOT NULL DEFAULT current_timestamp(),
  `SUPPLIER_ID` bigint(20) DEFAULT NULL,
  `REFERANC` bigint(20) NOT NULL,
  `TOTAL_PRICE` float(10,2) NOT NULL,
  `TOTAL_PAYMENTS` float(10,2) NOT NULL DEFAULT 0.00,
  `STATUSE` enum('DEFERRED','COMPLETED') NOT NULL DEFAULT 'DEFERRED',
  `NOTE` text DEFAULT NULL,
  PRIMARY KEY (`ORDER_ID`),
  KEY `SUPPLIER_ID` (`SUPPLIER_ID`),
  KEY `purchases_return_orders_ibfk_1` (`REFERANC`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `purchases_return_products`
--

CREATE TABLE IF NOT EXISTS `purchases_return_products` (
  `ORDER_ID` bigint(20) NOT NULL,
  `PRO_CODE` varchar(15) NOT NULL,
  `PURCHASE_UNIT_PRICE` float(10,2) NOT NULL,
  `QTY_SUPPLIED` decimal(10,2) NOT NULL,
  `QTY_RETURN` decimal(10,2) NOT NULL,
  PRIMARY KEY (`ORDER_ID`,`PRO_CODE`),
  KEY `purchases_products_ibfk_2` (`PRO_CODE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `reasons_data`
--

CREATE TABLE IF NOT EXISTS `reasons_data` (
  `ID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `REASON` varchar(255) NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `UK_REASONS_DATA_REASON` (`REASON`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `reasons_data`
--

INSERT INTO `reasons_data` (`ID`, `REASON`) VALUES
(6, 'العميل غير راضى ( جودة المنتج )'),
(7, 'العنوان خطاء'),
(3, 'اللون خطاء'),
(2, 'المقاس خطاء'),
(1, 'المنتج غير صحيح'),
(5, 'عيوب خامات'),
(4, 'عيوب صناعه'),
(10, 'لاغى بسبب التأخير'),
(8, 'لاغى من المنسق'),
(9, 'لاغى من خدمة العملاء');

-- --------------------------------------------------------

--
-- Table structure for table `reasons_for_product_rejection`
--

CREATE TABLE IF NOT EXISTS `reasons_for_product_rejection` (
  `ID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ORDER_ID` bigint(20) UNSIGNED NOT NULL,
  `PRO_CODE` varchar(100) NOT NULL,
  `REASON_ID` int(11) NOT NULL,
  `COMMENT` text NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `UQ_REJECTION_ORDER_PRODUCT` (`ORDER_ID`,`PRO_CODE`),
  KEY `IDX_REASONS_REJECTION_ORDER` (`ORDER_ID`),
  KEY `IDX_REASONS_REJECTION_PRODUCT` (`PRO_CODE`),
  KEY `IDX_REASONS_REJECTION_REASON` (`REASON_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `regional_areas`
--

CREATE TABLE IF NOT EXISTS `regional_areas` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `REGIONAL_NAME` varchar(100) NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `REGIONAL_NAME` (`REGIONAL_NAME`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `regional_areas`
--

INSERT INTO `regional_areas` (`ID`, `REGIONAL_NAME`) VALUES
(2, 'إقليم الإسكندرية'),
(3, 'إقليم الدلتا'),
(1, 'إقليم القاهرة الكبرى'),
(4, 'إقليم القناة'),
(7, 'إقليم جنوب الصعيد'),
(5, 'إقليم شمال الصعيد'),
(6, 'إقليم وسط الصعيد');

-- --------------------------------------------------------

--
-- Table structure for table `sales_returns`
--

CREATE TABLE IF NOT EXISTS `sales_returns` (
  `RETURN_ID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ORDER_ID` bigint(20) NOT NULL,
  `RETURN_DATE` datetime NOT NULL DEFAULT current_timestamp(),
  `BRANCH_ID` int(11) NOT NULL,
  `STORE_ID` int(11) NOT NULL,
  `STATUS` tinyint(4) NOT NULL DEFAULT 0,
  `RETURN_AMOUNT` decimal(18,2) NOT NULL DEFAULT 0.00,
  `RETURN_COST_AMOUNT` decimal(18,2) NOT NULL DEFAULT 0.00,
  `RETURN_ENTRY_ID` bigint(20) DEFAULT NULL,
  `COST_ENTRY_ID` bigint(20) DEFAULT NULL,
  `CREATED_BY` bigint(20) NOT NULL,
  `NOTE` varchar(500) DEFAULT NULL,
  `CREATED_AT` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`RETURN_ID`),
  UNIQUE KEY `uq_sales_return_entry` (`RETURN_ENTRY_ID`),
  UNIQUE KEY `uq_sales_return_cost_entry` (`COST_ENTRY_ID`),
  KEY `idx_sales_returns_order` (`ORDER_ID`),
  KEY `idx_sales_returns_date` (`RETURN_DATE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shipping_movement`
--

CREATE TABLE IF NOT EXISTS `shipping_movement` (
  `shipmov_id` int(11) NOT NULL AUTO_INCREMENT,
  `UNIQUE_ID` varchar(50) DEFAULT NULL,
  `movement_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'From store = 0 |  to store = 1 | to customer = 2 | from customer = 3 | to colleague = 4',
  `delivery_id` bigint(20) DEFAULT NULL,
  `user_id` bigint(20) NOT NULL,
  `shipment_orders` bigint(20) NOT NULL,
  `MOVING_DATE` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`shipmov_id`),
  KEY `delivery_id` (`delivery_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_movement_type_date` (`movement_type`,`MOVING_DATE`),
  KEY `idx_moving_date` (`MOVING_DATE`),
  KEY `movement_type` (`movement_type`,`shipment_orders`),
  KEY `shipment_orders` (`shipment_orders`,`MOVING_DATE`),
  KEY `movement_type_2` (`movement_type`,`shipment_orders`,`MOVING_DATE`),
  KEY `idx_shipment_orders_moving` (`shipment_orders`,`MOVING_DATE`,`shipmov_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `shipping_type`
--

CREATE TABLE IF NOT EXISTS `shipping_type` (
  `SHIPPING_T_ID` int(11) NOT NULL AUTO_INCREMENT,
  `SHIPPING_T_NAME` varchar(50) NOT NULL,
  PRIMARY KEY (`SHIPPING_T_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `shipping_type`
--

INSERT INTO `shipping_type` (`SHIPPING_T_ID`, `SHIPPING_T_NAME`) VALUES
(1, 'شحن داخلى'),
(2, 'شحن خارجى');

-- --------------------------------------------------------

--
-- Table structure for table `stores`
--

CREATE TABLE IF NOT EXISTS `stores` (
  `STO_ID` int(11) NOT NULL AUTO_INCREMENT,
  `BRANCH_ID` int(11) NOT NULL,
  `STO_NAME` varchar(100) NOT NULL,
  `STTY_ID` int(11) NOT NULL,
  `IS_COUNTING` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = مفتوح ، 1 = تحت الجرد',
  `COUNTING_TOKEN` varchar(64) DEFAULT NULL,
  `COUNTING_EXPIRES` datetime DEFAULT NULL,
  PRIMARY KEY (`STO_ID`),
  UNIQUE KEY `BRANCH_ID` (`BRANCH_ID`,`STO_NAME`),
  KEY `STORES_BRANCH_ID_BRANCHES_BR_ID` (`BRANCH_ID`),
  KEY `STORES_STTY_ID_STORES_TYPE_STTY_ID` (`STTY_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `stores_product`
--

CREATE TABLE IF NOT EXISTS `stores_product` (
  `STO_ID` int(11) NOT NULL,
  `PRO_CODE` varchar(50) NOT NULL,
  `STO_QTY` decimal(18,0) NOT NULL,
  `LAST_UPDATE` timestamp(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  UNIQUE KEY `idx_sp_store_product` (`STO_ID`,`PRO_CODE`),
  KEY `idx_store_product` (`PRO_CODE`,`STO_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `stores_type`
--

CREATE TABLE IF NOT EXISTS `stores_type` (
  `STTY_ID` int(11) NOT NULL AUTO_INCREMENT,
  `STTY_NAME` varchar(100) NOT NULL,
  `STTY_DESCRIPTION` text DEFAULT NULL,
  PRIMARY KEY (`STTY_ID`),
  UNIQUE KEY `STTP_NAME` (`STTY_NAME`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `stores_type`
--

INSERT INTO `stores_type` (`STTY_ID`, `STTY_NAME`, `STTY_DESCRIPTION`) VALUES
(1, 'عادى', 'المخزن من نوع (عادى) يستخدم فقظ للتحويلات الداخلية بين المخازن الاخرى'),
(2, 'بيع مباشر', 'المخزن من نوع (بيع مباشر) يستخدم فى اذون الصرف الخاصة بأومر البيع كا مثال تسليم العهده للمنديب او العملاء');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE IF NOT EXISTS `suppliers` (
  `SUP_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `SUP_NAME` varchar(50) NOT NULL,
  `SUP_EMAIL` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`SUP_ID`),
  KEY `idx_supplier_id` (`SUP_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `supply_orders`
--

CREATE TABLE IF NOT EXISTS `supply_orders` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `order_by` bigint(20) NOT NULL,
  `status` enum('Pending','Completed','Cancelled') DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_by` (`order_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supply_order_details`
--

CREATE TABLE IF NOT EXISTS `supply_order_details` (
  `supply_order_id` bigint(20) NOT NULL,
  `pro_code` varchar(50) NOT NULL,
  `quantity` float(11,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`supply_order_id`,`pro_code`),
  KEY `pro_code` (`pro_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `telephones`
--

CREATE TABLE IF NOT EXISTS `telephones` (
  `TEL_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `USER_ID` bigint(20) DEFAULT NULL,
  `CLIENT_ID` bigint(20) DEFAULT NULL,
  `SUPPLIER_ID` bigint(20) DEFAULT NULL,
  `TYPE` int(2) NOT NULL DEFAULT 0,
  `DEFAULTTELL` tinyint(1) NOT NULL DEFAULT 0,
  `WHATSAPP` tinyint(1) NOT NULL,
  `TELEGRAM` tinyint(1) NOT NULL,
  `TEL_LTYPE` varchar(10) NOT NULL,
  `TEL_NUMBER` varchar(15) NOT NULL,
  PRIMARY KEY (`TEL_ID`),
  UNIQUE KEY `USER_ID_2` (`USER_ID`,`CLIENT_ID`,`TEL_NUMBER`),
  UNIQUE KEY `USER_ID_3` (`USER_ID`,`CLIENT_ID`,`SUPPLIER_ID`,`TEL_NUMBER`),
  UNIQUE KEY `USER_ID_4` (`USER_ID`,`CLIENT_ID`,`SUPPLIER_ID`,`TEL_NUMBER`),
  KEY `USER_ID` (`USER_ID`) USING BTREE,
  KEY `CLIENT_ID` (`CLIENT_ID`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `traffic_blocks`
--

CREATE TABLE IF NOT EXISTS `traffic_blocks` (
  `ID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `IP_ADDRESS` varchar(45) NOT NULL,
  `BLOCKED_FROM` datetime NOT NULL,
  `BLOCKED_UNTIL` datetime NOT NULL,
  `SCORE` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `REASON` varchar(255) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `IDX_IP_UNTIL` (`IP_ADDRESS`,`BLOCKED_UNTIL`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `traffic_logs`
--

CREATE TABLE IF NOT EXISTS `traffic_logs` (
  `ID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `CREATED_AT` datetime(6) NOT NULL,
  `IP_ADDRESS` varchar(45) NOT NULL,
  `USER_ID` bigint(20) DEFAULT NULL,
  `SESSION_ID` varchar(128) DEFAULT NULL,
  `REQUEST_METHOD` varchar(10) NOT NULL,
  `REQUEST_URI` varchar(2048) NOT NULL,
  `USER_AGENT` varchar(512) DEFAULT NULL,
  `HTTP_STATUS` smallint(6) DEFAULT NULL,
  `RESPONSE_TIME_MS` int(10) UNSIGNED DEFAULT NULL,
  `BOT_SCORE` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `ACTION` enum('ALLOW','MONITOR','THROTTLE','BLOCK') NOT NULL DEFAULT 'ALLOW',
  `REASON` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `IDX_CREATED` (`CREATED_AT`),
  KEY `IDX_IP_CREATED` (`IP_ADDRESS`,`CREATED_AT`),
  KEY `IDX_USER_CREATED` (`USER_ID`,`CREATED_AT`),
  KEY `IDX_ACTION_CREATED` (`ACTION`,`CREATED_AT`),
  KEY `IDX_SCORE_CREATED` (`BOT_SCORE`,`CREATED_AT`),
  KEY `IDX_URI_CREATED` (`REQUEST_URI`(191),`CREATED_AT`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `transaction_categories`
--

CREATE TABLE IF NOT EXISTS `transaction_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_type` enum('REVENUE','EXPENSES','CASH_IN','CASH_OUT','TRANSFER_IN','TRANSFER_OUT') DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `parent_category_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_category_id` (`parent_category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `transaction_categories`
--

INSERT INTO `transaction_categories` (`id`, `transaction_type`, `name`, `description`, `parent_category_id`) VALUES
(1, NULL, 'مبيعات', 'فئة المعاملات الخاصة بالمبيعات من العملاء، تشمل جميع العمليات المتعلقة بالبيع من فواتير أو تحصيلات نقدية.', NULL),
(2, NULL, 'مشتريات', 'فئة المعاملات الخاصة بالمشتريات من الموردين، تشمل جميع المدفوعات والمشتريات من الموردين.', NULL),
(3, 'CASH_OUT', 'مدفوعات', 'فئة المعاملات الخاصة بالمدفوعات المالية المختلفة مثل دفع الفواتير، المدفوعات المتعلقة بالموظفين أو الموردين.', NULL),
(4, NULL, 'خصومات', 'فئة المعاملات الخاصة بالخصومات الممنوحة للعملاء أو الموردين، مثل الخصم على الفواتير أو العروض الخاصة.', NULL),
(5, NULL, 'استردادات', 'فئة المعاملات الخاصة بالاستردادات أو الإرجاع، مثل استرداد الأموال أو إرجاع السلع من العملاء.', NULL),
(6, 'EXPENSES', 'مرتبات', 'فئة المعاملات الخاصة بالرواتب للموظفين، تشمل المدفوعات المتعلقة بالرواتب الشهرية والتعويضات.', NULL),
(7, 'REVENUE', 'إيرادات أخرى', 'فئة المعاملات الخاصة بالإيرادات الأخرى التي لا تندرج تحت فئات المبيعات أو المدفوعات، مثل إيرادات استثمارية أو إضافية.', NULL),
(8, 'REVENUE', 'فواتير مبيعات', 'فئة فرعية تختص بالفواتير التي يتم إصدارها للعملاء مقابل المبيعات، تشمل التفاصيل الخاصة بكل فاتورة بيع.', 1),
(9, 'CASH_IN', 'تحصيلات مبيعات', 'فئة فرعية تختص بالمدفوعات المستلمة من العملاء مقابل الفواتير المباعة، تشمل المدفوعات النقدية أو عبر الحوالات البنكية. من خلال الندوب', 1),
(10, 'EXPENSES', 'فواتير مشتريات', 'فئة فرعية تختص بالفواتير التي يتم استلامها من الموردين مقابل المنتجات أو الخدمات المشتراة.', 2),
(11, 'CASH_OUT', 'مدفوعات مشتريات', 'فئة فرعية تختص بالمدفوعات التي يتم دفعها للموردين مقابل الفواتير المستلمة، تشمل المدفوعات النقدية أو عبر الحوالات البنكية.', 2),
(12, 'CASH_OUT', 'مدفوعات مرتبات', 'فئة فرعية تختص بالمدفوعات المتعلقة برواتب الموظفين، بما في ذلك المدفوعات الشهرية أو المكافآت.', 3),
(13, 'CASH_OUT', 'مدفوعات فواتير خدمات', 'فئة فرعية تختص بالمدفوعات الخاصة بفواتير الخدمات المختلفة مثل الكهرباء، المياه، الإنترنت، إلخ.', 3),
(14, 'CASH_OUT', 'استردادات مبيعات', 'فئة فرعية تختص بمعاملات استرداد الأموال من العملاء عند إرجاع المنتجات أو عدم رضاهم عن الخدمة المقدمة.', 5),
(15, 'CASH_IN', 'استردادات مشتريات', 'فئة فرعية تختص بمعاملات استرداد المدفوعات للموردين في حال إرجاع البضائع المشتراة أو طلب استبدالها.', 5),
(16, 'REVENUE', 'خصم عمولة', 'فئة خاصه بخصم عمولة نظير مرتجع (منسق او مندوب)', 4),
(17, 'CASH_OUT', 'صرف عمولة ', 'صرف عمولة', 4),
(18, 'CASH_OUT', 'مدفوعات مرتجع مبيعات', 'فئه مخصصه لسداد مرتجعات المبيعات للعملاء', 3),
(20, 'EXPENSES', 'بضاعة تالفة', 'خسائر المخزون ', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `USER_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `USER_GRUOP_ID` int(11) DEFAULT NULL,
  `BRANCH_ID` int(11) NOT NULL,
  `FORENAME` varchar(50) NOT NULL,
  `LASTNAME` varchar(50) NOT NULL,
  `GENDER` char(10) NOT NULL,
  `SUSPENDED` tinyint(1) NOT NULL DEFAULT 1,
  `USER_LOGIN` varchar(50) NOT NULL,
  `USER_PASSWORD` varchar(100) NOT NULL,
  `RE_PASSCODE` int(5) DEFAULT NULL,
  `RE_PASS_EXPIRE` datetime NOT NULL DEFAULT current_timestamp(),
  `ADMIN_TYPE` tinyint(1) NOT NULL DEFAULT 0,
  `MERCHANT_TYPE` tinyint(1) NOT NULL DEFAULT 0,
  `AFFILIATE_TYPE` tinyint(1) NOT NULL DEFAULT 0,
  `INTERMEDIARY_TYPE` tinyint(1) NOT NULL DEFAULT 0,
  `DELIVERY_TYPE` tinyint(1) NOT NULL DEFAULT 0,
  `USER_IMG_LINK` varchar(100) DEFAULT NULL,
  `USER_DATE_CREATED` datetime NOT NULL DEFAULT current_timestamp(),
  `BIRTHDAY` date NOT NULL,
  PRIMARY KEY (`USER_ID`),
  UNIQUE KEY `USER_LOGIN` (`USER_LOGIN`),
  KEY `USERS_USER_GRUOP_ID_USER_GRUOP_USER_GRUOP_ID` (`USER_GRUOP_ID`),
  KEY `USERS_BRANCH_ID_BRANCHES_BR_ID` (`BRANCH_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `users_affiliate`
--

CREATE TABLE IF NOT EXISTS `users_affiliate` (
  `USER_ID` bigint(20) NOT NULL,
  `AFFILIATEED_WITH` bigint(20) DEFAULT NULL,
  `LIVEL` int(11) NOT NULL DEFAULT 0,
  `COMMISSION_SALES` float(18,2) NOT NULL DEFAULT 0.00,
  `TEAM_COMMISSION` float(18,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`USER_ID`),
  KEY `AFFILIATEED_WITH` (`AFFILIATEED_WITH`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `users_delivery`
--

CREATE TABLE IF NOT EXISTS `users_delivery` (
  `USER_ID` bigint(20) NOT NULL,
  `ZONE_ID` int(11) DEFAULT NULL,
  `SALARY` decimal(18,2) NOT NULL DEFAULT 0.00,
  `PERCENT` float(12,2) NOT NULL DEFAULT 0.00,
  `CUSTODY_STORE` longtext NOT NULL DEFAULT '',
  PRIMARY KEY (`USER_ID`),
  KEY `ZONE_ID` (`ZONE_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `user_gruop`
--

CREATE TABLE IF NOT EXISTS `user_gruop` (
  `USER_GRUOP_ID` int(11) NOT NULL AUTO_INCREMENT,
  `USER_GROUP_PARENT` int(11) DEFAULT NULL,
  `USER_GRUOP_NAME` varchar(20) NOT NULL,
  `USER_SCRIPT` text NOT NULL,
  PRIMARY KEY (`USER_GRUOP_ID`),
  UNIQUE KEY `USER_GRUOP_NAME` (`USER_GRUOP_NAME`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `user_gruop`
--

INSERT INTO `user_gruop` (`USER_GRUOP_ID`, `USER_GROUP_PARENT`, `USER_GRUOP_NAME`, `USER_SCRIPT`) VALUES
(1, 0, 'Administrator', 'a:1:{s:5:\"Admin\";a:52:{s:9:\"dashboard\";a:5:{s:5:\"Admin\";s:4:\"true\";s:16:\"CustomerService1\";s:4:\"true\";s:16:\"CustomerService2\";s:4:\"true\";s:7:\"Storage\";s:4:\"true\";s:8:\"Delivery\";s:4:\"true\";}s:9:\"Countries\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:3:\"Gov\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:4:\"City\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:14:\"Local-Shipping\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:22:\"International-Shipping\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:10:\"Enterprise\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:24:\"Administrative-Structure\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:8:\"Branches\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:9:\"Warehouse\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:5:\"Users\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:7:\"Clients\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:9:\"Suppliers\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:23:\"UserSpecificPermissions\";a:3:{s:14:\"viewAllCompany\";s:4:\"true\";s:15:\"viewAllBranches\";s:4:\"true\";s:13:\"viewAllStores\";s:4:\"true\";}s:7:\"Reports\";a:1:{s:4:\"View\";s:4:\"true\";}s:18:\"Product-Categories\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:13:\"ProductOption\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:8:\"Products\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:8:\"NewOrder\";a:1:{s:12:\"Create_Order\";s:4:\"true\";}s:13:\"orderlist_ex0\";a:2:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";}s:11:\"selseReport\";a:2:{s:15:\"View_order_list\";s:4:\"true\";s:4:\"View\";s:4:\"true\";}s:13:\"orderlist_ex1\";a:16:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";s:14:\"Track_shipping\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}s:13:\"orderlist_ex2\";a:16:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:5:\"false\";s:19:\"Confirm sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";s:14:\"Track_shipping\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}s:13:\"orderlist_ex3\";a:16:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";s:14:\"Track_shipping\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}s:13:\"orderlist_ex4\";a:16:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:22:\"customer not contacted\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:12:\"Cancel order\";s:4:\"true\";s:11:\"Direct sale\";s:5:\"false\";s:14:\"Track_shipping\";s:5:\"false\";}s:14:\"orderlist_ex13\";a:16:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:28:\"The client is not responding\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";s:14:\"Track_shipping\";s:5:\"false\";}s:13:\"orderlist_ex5\";a:16:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";s:14:\"Track_shipping\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}s:13:\"orderlist_ex6\";a:12:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:5:\"false\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:5:\"false\";s:28:\"The client is not responding\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";}s:14:\"orderlist_ex12\";a:17:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:8:\"Shipping\";s:5:\"false\";s:3:\"os2\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}s:17:\"SupportAfterSales\";a:3:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:16:\"NewOrderExchange\";s:4:\"true\";}s:13:\"SupportOrders\";a:12:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:5:\"false\";s:8:\"Prepared\";s:4:\"true\";}s:13:\"orderlist_ex7\";a:16:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:12:\"Invoce_Print\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:5:\"false\";s:12:\"Cancel order\";s:5:\"false\";s:8:\"Prepared\";s:4:\"true\";s:11:\"Direct sale\";s:5:\"false\";s:14:\"Track_shipping\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";s:7:\"Nostore\";s:5:\"false\";}s:13:\"orderlist_ex8\";a:17:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:8:\"Shipment\";s:4:\"true\";s:12:\"Shipmentdate\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:11:\"Direct sale\";s:5:\"false\";s:14:\"Track_shipping\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}s:13:\"orderlist_ex9\";a:11:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:18:\"DeliveredFromAdmin\";s:4:\"true\";s:14:\"RefundShipment\";s:4:\"true\";s:12:\"ChooseRefund\";s:4:\"true\";s:3:\"os2\";s:4:\"true\";s:3:\"os1\";s:4:\"true\";s:14:\"transformation\";s:5:\"false\";s:13:\"EditDelevered\";s:5:\"false\";s:10:\"Edit_order\";s:5:\"false\";s:12:\"Delete_order\";s:5:\"false\";}s:13:\"OrdersCustody\";a:6:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:13:\"EditDelevered\";s:4:\"true\";s:16:\"ShipmentApproved\";s:4:\"true\";s:14:\"RefundShipment\";s:4:\"true\";s:12:\"ChooseRefund\";s:4:\"true\";}s:12:\"StockReceipt\";a:2:{s:4:\"view\";s:4:\"true\";s:15:\"viewAllBranches\";s:5:\"false\";}s:10:\"StockIssue\";a:1:{s:4:\"view\";s:4:\"true\";}s:13:\"StockTransfer\";a:1:{s:4:\"view\";s:4:\"true\";}s:10:\"StockAudit\";a:3:{s:4:\"view\";s:4:\"true\";s:15:\"viewAllBranches\";s:5:\"false\";s:12:\"viewStoreQty\";s:5:\"false\";}s:19:\"CriticalItemsReport\";a:1:{s:4:\"view\";s:4:\"true\";}s:23:\"DelegatesShipmentReport\";a:1:{s:4:\"view\";s:4:\"true\";}s:24:\"DelegatesInventoryReport\";a:1:{s:4:\"view\";s:4:\"true\";}s:18:\"suppliersQtyReport\";a:1:{s:4:\"view\";s:4:\"true\";}s:15:\"PaymentsReceipt\";a:1:{s:4:\"view\";s:4:\"true\";}s:19:\"PaymentDisbursement\";a:1:{s:4:\"view\";s:4:\"true\";}s:19:\"CashReceiptVouchers\";a:1:{s:4:\"view\";s:4:\"true\";}s:24:\"AccountStatementSupplier\";a:1:{s:4:\"view\";s:4:\"true\";}s:22:\"AccountStatementClient\";a:1:{s:4:\"view\";s:4:\"true\";}s:24:\"AccountStatementMarketer\";a:1:{s:4:\"view\";s:4:\"true\";}s:24:\"AccountStatementDelivery\";a:1:{s:4:\"view\";s:4:\"true\";}s:14:\"orderlist_ex10\";a:13:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:5:\"false\";s:14:\"transformation\";s:5:\"false\";s:8:\"Shipping\";s:5:\"false\";s:3:\"os2\";s:5:\"false\";s:19:\"Confirm sale orders\";s:5:\"false\";s:19:\"pending sale orders\";s:5:\"false\";s:22:\"Advance booking orders\";s:5:\"false\";s:28:\"The client is not responding\";s:5:\"false\";s:12:\"Cancel order\";s:5:\"false\";s:10:\"Processing\";s:5:\"false\";}s:14:\"orderlist_ex11\";a:9:{s:19:\"Confirm sale orders\";s:5:\"false\";s:19:\"pending sale orders\";s:5:\"false\";s:22:\"Advance booking orders\";s:5:\"false\";s:28:\"The client is not responding\";s:5:\"false\";s:12:\"Cancel order\";s:5:\"false\";s:10:\"Processing\";s:5:\"false\";s:8:\"Prepared\";s:5:\"false\";s:14:\"Track_shipping\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}}}'),
(2, 0, 'Owner', 'a:1:{s:5:\"Admin\";a:52:{s:9:\"dashboard\";a:5:{s:5:\"Admin\";s:4:\"true\";s:16:\"CustomerService1\";s:4:\"true\";s:16:\"CustomerService2\";s:4:\"true\";s:7:\"Storage\";s:4:\"true\";s:8:\"Delivery\";s:4:\"true\";}s:9:\"Countries\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:3:\"Gov\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:4:\"City\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:14:\"Local-Shipping\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:22:\"International-Shipping\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:10:\"Enterprise\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:24:\"Administrative-Structure\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:8:\"Branches\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:9:\"Warehouse\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:5:\"Users\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:7:\"Clients\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:9:\"Suppliers\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:23:\"UserSpecificPermissions\";a:3:{s:14:\"viewAllCompany\";s:4:\"true\";s:15:\"viewAllBranches\";s:4:\"true\";s:13:\"viewAllStores\";s:4:\"true\";}s:7:\"Reports\";a:1:{s:4:\"View\";s:4:\"true\";}s:18:\"Product-Categories\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:13:\"ProductOption\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:8:\"Products\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:8:\"NewOrder\";a:1:{s:12:\"Create_Order\";s:4:\"true\";}s:13:\"orderlist_ex0\";a:2:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";}s:11:\"selseReport\";a:2:{s:15:\"View_order_list\";s:4:\"true\";s:4:\"View\";s:4:\"true\";}s:13:\"orderlist_ex1\";a:16:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:4:\"true\";s:14:\"Track_shipping\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}s:13:\"orderlist_ex2\";a:16:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:4:\"true\";s:14:\"Track_shipping\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}s:13:\"orderlist_ex3\";a:16:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:4:\"true\";s:14:\"Track_shipping\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}s:13:\"orderlist_ex4\";a:16:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:22:\"customer not contacted\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:12:\"Cancel order\";s:4:\"true\";s:11:\"Direct sale\";s:5:\"false\";s:14:\"Track_shipping\";s:5:\"false\";}s:14:\"orderlist_ex13\";a:16:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:11:\"Direct sale\";s:5:\"false\";s:14:\"Track_shipping\";s:5:\"false\";}s:13:\"orderlist_ex5\";a:16:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:4:\"true\";s:14:\"Track_shipping\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}s:13:\"orderlist_ex6\";a:12:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";}s:14:\"orderlist_ex12\";a:17:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:8:\"Shipping\";s:4:\"true\";s:3:\"os2\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:4:\"true\";s:11:\"Direct sale\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}s:17:\"SupportAfterSales\";a:2:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";}s:13:\"SupportOrders\";a:12:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:5:\"false\";s:8:\"Prepared\";s:4:\"true\";}s:13:\"orderlist_ex7\";a:15:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:12:\"Invoce_Print\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:8:\"Prepared\";s:4:\"true\";s:11:\"Direct sale\";s:4:\"true\";s:14:\"Track_shipping\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}s:13:\"orderlist_ex8\";a:17:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:8:\"Shipment\";s:4:\"true\";s:12:\"Shipmentdate\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:11:\"Direct sale\";s:4:\"true\";s:14:\"Track_shipping\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}s:13:\"orderlist_ex9\";a:5:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:18:\"DeliveredFromAdmin\";s:4:\"true\";s:14:\"RefundShipment\";s:4:\"true\";s:12:\"ChooseRefund\";s:4:\"true\";}s:13:\"OrdersCustody\";a:6:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:13:\"EditDelevered\";s:4:\"true\";s:16:\"ShipmentApproved\";s:4:\"true\";s:14:\"RefundShipment\";s:4:\"true\";s:12:\"ChooseRefund\";s:4:\"true\";}s:12:\"StockReceipt\";a:2:{s:4:\"view\";s:4:\"true\";s:15:\"viewAllBranches\";s:4:\"true\";}s:10:\"StockIssue\";a:1:{s:4:\"view\";s:4:\"true\";}s:13:\"StockTransfer\";a:1:{s:4:\"view\";s:4:\"true\";}s:10:\"StockAudit\";a:3:{s:4:\"view\";s:4:\"true\";s:15:\"viewAllBranches\";s:4:\"true\";s:12:\"viewStoreQty\";s:4:\"true\";}s:19:\"CriticalItemsReport\";a:1:{s:4:\"view\";s:4:\"true\";}s:23:\"DelegatesShipmentReport\";a:1:{s:4:\"view\";s:4:\"true\";}s:24:\"DelegatesInventoryReport\";a:1:{s:4:\"view\";s:4:\"true\";}s:18:\"suppliersQtyReport\";a:1:{s:4:\"view\";s:4:\"true\";}s:15:\"PaymentsReceipt\";a:1:{s:4:\"view\";s:4:\"true\";}s:19:\"PaymentDisbursement\";a:1:{s:4:\"view\";s:4:\"true\";}s:19:\"CashReceiptVouchers\";a:1:{s:4:\"view\";s:4:\"true\";}s:24:\"AccountStatementSupplier\";a:1:{s:4:\"view\";s:4:\"true\";}s:22:\"AccountStatementClient\";a:1:{s:4:\"view\";s:4:\"true\";}s:24:\"AccountStatementMarketer\";a:1:{s:4:\"view\";s:4:\"true\";}s:24:\"AccountStatementDelivery\";a:1:{s:4:\"view\";s:4:\"true\";}s:14:\"orderlist_ex10\";a:13:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:8:\"Shipping\";s:4:\"true\";s:3:\"os2\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";}s:14:\"orderlist_ex11\";a:9:{s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:4:\"true\";s:14:\"Track_shipping\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}}}'),
(3, 0, 'خدمة العملاء', 'a:1:{s:5:\"Admin\";a:52:{s:9:\"dashboard\";a:5:{s:5:\"Admin\";s:5:\"false\";s:16:\"CustomerService1\";s:5:\"false\";s:16:\"CustomerService2\";s:5:\"false\";s:7:\"Storage\";s:5:\"false\";s:8:\"Delivery\";s:5:\"false\";}s:9:\"Countries\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:3:\"Gov\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:4:\"City\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:14:\"Local-Shipping\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:22:\"International-Shipping\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:10:\"Enterprise\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:24:\"Administrative-Structure\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:8:\"Branches\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:9:\"Warehouse\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:5:\"Users\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:7:\"Clients\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:9:\"Suppliers\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:23:\"UserSpecificPermissions\";a:3:{s:14:\"viewAllCompany\";s:5:\"false\";s:15:\"viewAllBranches\";s:5:\"false\";s:13:\"viewAllStores\";s:5:\"false\";}s:7:\"Reports\";a:1:{s:4:\"View\";s:5:\"false\";}s:18:\"Product-Categories\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:13:\"ProductOption\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:8:\"Products\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:8:\"NewOrder\";a:1:{s:12:\"Create_Order\";s:5:\"false\";}s:13:\"orderlist_ex0\";a:2:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";}s:11:\"selseReport\";a:2:{s:15:\"View_order_list\";s:5:\"false\";s:4:\"View\";s:5:\"false\";}s:13:\"orderlist_ex1\";a:16:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:5:\"false\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";s:14:\"Track_shipping\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}s:13:\"orderlist_ex2\";a:16:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:5:\"false\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";s:14:\"Track_shipping\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}s:13:\"orderlist_ex3\";a:16:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:5:\"false\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";s:14:\"Track_shipping\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}s:13:\"orderlist_ex4\";a:16:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:22:\"customer not contacted\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:5:\"false\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:12:\"Cancel order\";s:4:\"true\";s:11:\"Direct sale\";s:5:\"false\";s:14:\"Track_shipping\";s:5:\"false\";}s:14:\"orderlist_ex13\";a:16:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:5:\"false\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:28:\"The client is not responding\";s:4:\"true\";s:11:\"Direct sale\";s:5:\"false\";s:14:\"Track_shipping\";s:5:\"false\";}s:13:\"orderlist_ex5\";a:16:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:5:\"false\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";s:14:\"Track_shipping\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}s:13:\"orderlist_ex6\";a:12:{s:15:\"View_order_list\";s:5:\"false\";s:10:\"View_order\";s:5:\"false\";s:10:\"Edit_order\";s:5:\"false\";s:12:\"Delete_order\";s:5:\"false\";s:14:\"transformation\";s:5:\"false\";s:7:\"Nostore\";s:5:\"false\";s:19:\"Confirm sale orders\";s:5:\"false\";s:19:\"pending sale orders\";s:5:\"false\";s:22:\"Advance booking orders\";s:5:\"false\";s:28:\"The client is not responding\";s:5:\"false\";s:10:\"Processing\";s:5:\"false\";s:8:\"Prepared\";s:5:\"false\";}s:14:\"orderlist_ex12\";a:17:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:8:\"Shipping\";s:4:\"true\";s:3:\"os2\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:5:\"false\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:4:\"true\";s:11:\"Direct sale\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}s:17:\"SupportAfterSales\";a:2:{s:15:\"View_order_list\";s:5:\"false\";s:10:\"View_order\";s:5:\"false\";}s:13:\"SupportOrders\";a:12:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:21:\"Shipping Confirmation\";s:5:\"false\";s:8:\"Prepared\";s:5:\"false\";}s:13:\"orderlist_ex7\";a:15:{s:15:\"View_order_list\";s:5:\"false\";s:10:\"View_order\";s:5:\"false\";s:10:\"Edit_order\";s:5:\"false\";s:12:\"Delete_order\";s:5:\"false\";s:12:\"Invoce_Print\";s:5:\"false\";s:14:\"transformation\";s:5:\"false\";s:19:\"Confirm sale orders\";s:5:\"false\";s:19:\"pending sale orders\";s:5:\"false\";s:22:\"Advance booking orders\";s:5:\"false\";s:28:\"The client is not responding\";s:5:\"false\";s:12:\"Cancel order\";s:5:\"false\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";s:14:\"Track_shipping\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}s:13:\"orderlist_ex8\";a:17:{s:15:\"View_order_list\";s:5:\"false\";s:10:\"View_order\";s:5:\"false\";s:10:\"Edit_order\";s:5:\"false\";s:12:\"Delete_order\";s:5:\"false\";s:8:\"Shipment\";s:5:\"false\";s:12:\"Shipmentdate\";s:5:\"false\";s:14:\"transformation\";s:5:\"false\";s:7:\"Nostore\";s:5:\"false\";s:22:\"Advance booking orders\";s:5:\"false\";s:12:\"Cancel order\";s:5:\"false\";s:10:\"Processing\";s:5:\"false\";s:19:\"Confirm sale orders\";s:5:\"false\";s:19:\"pending sale orders\";s:5:\"false\";s:28:\"The client is not responding\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";s:14:\"Track_shipping\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}s:13:\"orderlist_ex9\";a:5:{s:15:\"View_order_list\";s:5:\"false\";s:10:\"View_order\";s:5:\"false\";s:18:\"DeliveredFromAdmin\";s:5:\"false\";s:14:\"RefundShipment\";s:5:\"false\";s:12:\"ChooseRefund\";s:5:\"false\";}s:13:\"OrdersCustody\";a:6:{s:15:\"View_order_list\";s:5:\"false\";s:10:\"View_order\";s:5:\"false\";s:13:\"EditDelevered\";s:5:\"false\";s:16:\"ShipmentApproved\";s:5:\"false\";s:14:\"RefundShipment\";s:5:\"false\";s:12:\"ChooseRefund\";s:5:\"false\";}s:12:\"StockReceipt\";a:2:{s:4:\"view\";s:5:\"false\";s:15:\"viewAllBranches\";s:5:\"false\";}s:10:\"StockIssue\";a:1:{s:4:\"view\";s:5:\"false\";}s:13:\"StockTransfer\";a:1:{s:4:\"view\";s:5:\"false\";}s:10:\"StockAudit\";a:3:{s:4:\"view\";s:4:\"true\";s:15:\"viewAllBranches\";s:4:\"true\";s:12:\"viewStoreQty\";s:4:\"true\";}s:19:\"CriticalItemsReport\";a:1:{s:4:\"view\";s:5:\"false\";}s:23:\"DelegatesShipmentReport\";a:1:{s:4:\"view\";s:5:\"false\";}s:24:\"DelegatesInventoryReport\";a:1:{s:4:\"view\";s:5:\"false\";}s:18:\"suppliersQtyReport\";a:1:{s:4:\"view\";s:5:\"false\";}s:15:\"PaymentsReceipt\";a:1:{s:4:\"view\";s:5:\"false\";}s:19:\"PaymentDisbursement\";a:1:{s:4:\"view\";s:5:\"false\";}s:19:\"CashReceiptVouchers\";a:1:{s:4:\"view\";s:5:\"false\";}s:24:\"AccountStatementSupplier\";a:1:{s:4:\"view\";s:5:\"false\";}s:22:\"AccountStatementClient\";a:1:{s:4:\"view\";s:5:\"false\";}s:24:\"AccountStatementMarketer\";a:1:{s:4:\"view\";s:5:\"false\";}s:24:\"AccountStatementDelivery\";a:1:{s:4:\"view\";s:5:\"false\";}s:14:\"orderlist_ex10\";a:13:{s:15:\"View_order_list\";s:5:\"false\";s:10:\"View_order\";s:5:\"false\";s:10:\"Edit_order\";s:5:\"false\";s:12:\"Delete_order\";s:5:\"false\";s:14:\"transformation\";s:5:\"false\";s:8:\"Shipping\";s:5:\"false\";s:3:\"os2\";s:5:\"false\";s:19:\"Confirm sale orders\";s:5:\"false\";s:19:\"pending sale orders\";s:5:\"false\";s:22:\"Advance booking orders\";s:5:\"false\";s:28:\"The client is not responding\";s:5:\"false\";s:12:\"Cancel order\";s:5:\"false\";s:10:\"Processing\";s:5:\"false\";}s:14:\"orderlist_ex11\";a:9:{s:19:\"Confirm sale orders\";s:5:\"false\";s:19:\"pending sale orders\";s:5:\"false\";s:22:\"Advance booking orders\";s:5:\"false\";s:28:\"The client is not responding\";s:5:\"false\";s:12:\"Cancel order\";s:5:\"false\";s:10:\"Processing\";s:5:\"false\";s:8:\"Prepared\";s:5:\"false\";s:14:\"Track_shipping\";s:5:\"false\";s:22:\"customer not contacted\";s:5:\"false\";}}}'),
(4, 0, 'امين مخزن', 'a:1:{s:5:\"Admin\";a:47:{s:9:\"dashboard\";a:5:{s:5:\"Admin\";s:5:\"false\";s:16:\"CustomerService1\";s:5:\"false\";s:16:\"CustomerService2\";s:5:\"false\";s:7:\"Storage\";s:4:\"true\";s:8:\"Delivery\";s:5:\"false\";}s:9:\"Countries\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:3:\"Gov\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:4:\"City\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:14:\"Local-Shipping\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:22:\"International-Shipping\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:10:\"Enterprise\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:24:\"Administrative-Structure\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:8:\"Branches\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:9:\"Warehouse\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:5:\"Users\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:7:\"Clients\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:9:\"Suppliers\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:23:\"UserSpecificPermissions\";a:3:{s:14:\"viewAllCompany\";s:5:\"false\";s:15:\"viewAllBranches\";s:5:\"false\";s:13:\"viewAllStores\";s:5:\"false\";}s:7:\"Reports\";a:1:{s:4:\"View\";s:5:\"false\";}s:18:\"Product-Categories\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:13:\"ProductOption\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:8:\"Products\";a:4:{s:4:\"View\";s:5:\"false\";s:6:\"Addnew\";s:5:\"false\";s:6:\"Update\";s:5:\"false\";s:6:\"Delete\";s:5:\"false\";}s:8:\"NewOrder\";a:1:{s:12:\"Create_Order\";s:5:\"false\";}s:13:\"orderlist_ex0\";a:2:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";}s:11:\"selseReport\";a:1:{s:4:\"View\";s:5:\"false\";}s:13:\"orderlist_ex1\";a:13:{s:15:\"View_order_list\";s:5:\"false\";s:10:\"View_order\";s:5:\"false\";s:10:\"Edit_order\";s:5:\"false\";s:12:\"Delete_order\";s:5:\"false\";s:14:\"transformation\";s:5:\"false\";s:7:\"Nostore\";s:5:\"false\";s:19:\"pending sale orders\";s:5:\"false\";s:22:\"Advance booking orders\";s:5:\"false\";s:28:\"The client is not responding\";s:5:\"false\";s:12:\"Cancel order\";s:5:\"false\";s:10:\"Processing\";s:5:\"false\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";}s:13:\"orderlist_ex2\";a:13:{s:15:\"View_order_list\";s:5:\"false\";s:10:\"View_order\";s:5:\"false\";s:10:\"Edit_order\";s:5:\"false\";s:12:\"Delete_order\";s:5:\"false\";s:14:\"transformation\";s:5:\"false\";s:7:\"Nostore\";s:5:\"false\";s:19:\"Confirm sale orders\";s:5:\"false\";s:22:\"Advance booking orders\";s:5:\"false\";s:28:\"The client is not responding\";s:5:\"false\";s:12:\"Cancel order\";s:5:\"false\";s:10:\"Processing\";s:5:\"false\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";}s:13:\"orderlist_ex3\";a:13:{s:15:\"View_order_list\";s:5:\"false\";s:10:\"View_order\";s:5:\"false\";s:10:\"Edit_order\";s:5:\"false\";s:12:\"Delete_order\";s:5:\"false\";s:14:\"transformation\";s:5:\"false\";s:7:\"Nostore\";s:5:\"false\";s:19:\"Confirm sale orders\";s:5:\"false\";s:19:\"pending sale orders\";s:5:\"false\";s:28:\"The client is not responding\";s:5:\"false\";s:12:\"Cancel order\";s:5:\"false\";s:10:\"Processing\";s:5:\"false\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";}s:13:\"orderlist_ex4\";a:13:{s:15:\"View_order_list\";s:5:\"false\";s:10:\"View_order\";s:5:\"false\";s:10:\"Edit_order\";s:5:\"false\";s:12:\"Delete_order\";s:5:\"false\";s:14:\"transformation\";s:5:\"false\";s:7:\"Nostore\";s:5:\"false\";s:19:\"Confirm sale orders\";s:5:\"false\";s:19:\"pending sale orders\";s:5:\"false\";s:22:\"Advance booking orders\";s:5:\"false\";s:12:\"Cancel order\";s:5:\"false\";s:10:\"Processing\";s:5:\"false\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";}s:13:\"orderlist_ex5\";a:13:{s:15:\"View_order_list\";s:5:\"false\";s:10:\"View_order\";s:5:\"false\";s:10:\"Edit_order\";s:5:\"false\";s:12:\"Delete_order\";s:5:\"false\";s:14:\"transformation\";s:5:\"false\";s:7:\"Nostore\";s:5:\"false\";s:19:\"Confirm sale orders\";s:5:\"false\";s:19:\"pending sale orders\";s:5:\"false\";s:22:\"Advance booking orders\";s:5:\"false\";s:28:\"The client is not responding\";s:5:\"false\";s:10:\"Processing\";s:5:\"false\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";}s:17:\"SupportAfterSales\";a:2:{s:15:\"View_order_list\";s:5:\"false\";s:10:\"View_order\";s:5:\"false\";}s:13:\"SupportOrders\";a:11:{s:15:\"View_order_list\";s:5:\"false\";s:10:\"View_order\";s:5:\"false\";s:10:\"Edit_order\";s:5:\"false\";s:14:\"transformation\";s:5:\"false\";s:7:\"Nostore\";s:5:\"false\";s:19:\"pending sale orders\";s:5:\"false\";s:22:\"Advance booking orders\";s:5:\"false\";s:28:\"The client is not responding\";s:5:\"false\";s:12:\"Cancel order\";s:5:\"false\";s:10:\"Processing\";s:5:\"false\";s:8:\"Prepared\";s:5:\"false\";}s:13:\"orderlist_ex7\";a:13:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:12:\"Invoce_Print\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:19:\"Confirm sale orders\";s:5:\"false\";s:19:\"pending sale orders\";s:5:\"false\";s:22:\"Advance booking orders\";s:5:\"false\";s:28:\"The client is not responding\";s:5:\"false\";s:12:\"Cancel order\";s:5:\"false\";s:8:\"Prepared\";s:4:\"true\";s:11:\"Direct sale\";s:5:\"false\";}s:13:\"orderlist_ex8\";a:15:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:8:\"Shipment\";s:4:\"true\";s:12:\"Shipmentdate\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:22:\"Advance booking orders\";s:5:\"false\";s:12:\"Cancel order\";s:5:\"false\";s:10:\"Processing\";s:4:\"true\";s:19:\"Confirm sale orders\";s:5:\"false\";s:19:\"pending sale orders\";s:5:\"false\";s:28:\"The client is not responding\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";}s:13:\"orderlist_ex9\";a:5:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:18:\"DeliveredFromAdmin\";s:4:\"true\";s:14:\"RefundShipment\";s:4:\"true\";s:12:\"ChooseRefund\";s:4:\"true\";}s:13:\"OrdersCustody\";a:6:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:13:\"EditDelevered\";s:4:\"true\";s:16:\"ShipmentApproved\";s:4:\"true\";s:14:\"RefundShipment\";s:4:\"true\";s:12:\"ChooseRefund\";s:4:\"true\";}s:12:\"StockReceipt\";a:2:{s:4:\"view\";s:4:\"true\";s:15:\"viewAllBranches\";s:5:\"false\";}s:10:\"StockIssue\";a:1:{s:4:\"view\";s:4:\"true\";}s:16:\"InternalTransfer\";a:1:{s:4:\"view\";s:5:\"false\";}s:10:\"StockAudit\";a:3:{s:4:\"view\";s:4:\"true\";s:15:\"viewAllBranches\";s:4:\"true\";s:12:\"viewStoreQty\";s:4:\"true\";}s:19:\"CriticalItemsReport\";a:1:{s:4:\"view\";s:4:\"true\";}s:23:\"DelegatesShipmentReport\";a:1:{s:4:\"view\";s:5:\"false\";}s:24:\"DelegatesInventoryReport\";a:1:{s:4:\"view\";s:5:\"false\";}s:18:\"suppliersQtyReport\";a:1:{s:4:\"view\";s:5:\"false\";}s:15:\"PaymentsReceipt\";a:1:{s:4:\"view\";s:4:\"true\";}s:24:\"AccountStatementSupplier\";a:1:{s:4:\"view\";s:5:\"false\";}s:22:\"AccountStatementClient\";a:1:{s:4:\"view\";s:5:\"false\";}s:24:\"AccountStatementMarketer\";a:1:{s:4:\"view\";s:5:\"false\";}s:24:\"AccountStatementDelivery\";a:1:{s:4:\"view\";s:5:\"false\";}s:14:\"orderlist_ex10\";a:13:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:8:\"Shipping\";s:4:\"true\";s:3:\"os2\";s:5:\"false\";s:19:\"Confirm sale orders\";s:5:\"false\";s:19:\"pending sale orders\";s:5:\"false\";s:22:\"Advance booking orders\";s:5:\"false\";s:28:\"The client is not responding\";s:5:\"false\";s:12:\"Cancel order\";s:5:\"false\";s:10:\"Processing\";s:4:\"true\";}s:14:\"orderlist_ex11\";a:7:{s:19:\"Confirm sale orders\";s:5:\"false\";s:19:\"pending sale orders\";s:5:\"false\";s:22:\"Advance booking orders\";s:5:\"false\";s:28:\"The client is not responding\";s:5:\"false\";s:12:\"Cancel order\";s:5:\"false\";s:10:\"Processing\";s:5:\"false\";s:8:\"Prepared\";s:5:\"false\";}}}');

-- --------------------------------------------------------

--
-- Table structure for table `zones`
--

CREATE TABLE IF NOT EXISTS `zones` (
  `ZONE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ZONE_NAME` varchar(50) NOT NULL,
  `ZONE_NAME_EN` varchar(50) NOT NULL,
  `SHIPPING_RATE` decimal(18,2) NOT NULL DEFAULT 0.00,
  `SHIPPING_LOWEST` decimal(18,2) NOT NULL DEFAULT 0.00,
  `SHIPPING_TIME_MAX` int(11) NOT NULL DEFAULT 2,
  `SHIPPING_TYPE` int(11) NOT NULL,
  `STORE_ID` int(11) NOT NULL,
  PRIMARY KEY (`ZONE_ID`),
  UNIQUE KEY `ZONE_NAME` (`ZONE_NAME`),
  UNIQUE KEY `ZONE_NAME_EN` (`ZONE_NAME_EN`),
  UNIQUE KEY `ZONE_NAME_UNIQUE` (`ZONE_NAME`,`STORE_ID`),
  UNIQUE KEY `ZONE_NAME_EN_UNIQUE` (`ZONE_NAME_EN`,`STORE_ID`),
  KEY `STORE_ID` (`STORE_ID`),
  KEY `STORE_ID_key` (`STORE_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `zones`
--

INSERT INTO `zones` (`ZONE_ID`, `ZONE_NAME`, `ZONE_NAME_EN`, `SHIPPING_RATE`, `SHIPPING_LOWEST`, `SHIPPING_TIME_MAX`, `SHIPPING_TYPE`, `STORE_ID`) VALUES
(12, 'مدينه نصر', 'Madenet Nasr', 65.00, 1.00, 1, 1, 1),
(13, 'مصر الجديده', 'Misr Elgedida', 65.00, 1.00, 1, 1, 1),
(14, 'المعادى', 'Elmaadi', 65.00, 1.00, 1, 1, 1),
(15, 'وسط البلد', 'West Elbalad', 65.00, 1.00, 1, 1, 1),
(16, 'التجمع', 'Eltagamoa', 75.00, 1.00, 1, 1, 1),
(17, 'اكتوبر', 'October', 75.00, 1.00, 1, 1, 1),
(18, 'الجيزه1', 'Elgiza', 65.00, 1.00, 1, 1, 1),
(19, 'اسوان', 'Aswan', 85.00, 0.00, 1, 2, 1),
(20, 'اسكندريه', 'Alexandria', 80.00, 0.00, 1, 2, 1),
(21, 'اسيوط', 'Asyout', 85.00, 0.00, 1, 2, 1),
(22, 'الاسماعيليه', 'Elesmailia', 80.00, 0.00, 1, 2, 1),
(23, 'الاقصر', 'Luoxur', 90.00, 0.00, 1, 2, 1),
(24, 'البجر الاحمر', 'Elbahr Elahmar', 100.00, 0.00, 1, 2, 1),
(25, 'الدقهليه', 'Eldaahlya', 80.00, 0.00, 1, 2, 1),
(26, 'السويس', 'Elswyes', 80.00, 0.00, 1, 2, 1),
(27, 'الشرقيه', 'Elsharqia', 80.00, 0.00, 1, 2, 1),
(28, 'الغربيه', 'Elgharbya', 80.00, 0.00, 1, 2, 1),
(29, 'الفيوم', 'Elfayoum', 85.00, 0.00, 1, 2, 1),
(30, 'القليوبيه', 'Elqalyoubia', 80.00, 0.00, 1, 2, 1),
(31, 'المنوفيه', 'Elmnofia', 80.00, 0.00, 1, 2, 1),
(32, 'المنيا', 'Elmnian', 80.00, 0.00, 1, 2, 1),
(33, 'الوادى الجديد', 'Elwadielgded', 100.00, 0.00, 1, 2, 1),
(34, 'بنى سويف', 'BaniSwef', 85.00, 0.00, 1, 2, 1),
(35, 'بورسعيد', 'portsaid', 80.00, 0.00, 1, 2, 1),
(36, 'سيناء', 'Saynai', 100.00, 0.00, 1, 2, 1),
(37, 'دمياط', 'Domyat', 80.00, 0.00, 1, 2, 1),
(38, 'سوهاج', 'Sohag', 90.00, 0.00, 1, 2, 1),
(39, 'قنا', 'Qina', 90.00, 0.00, 1, 2, 1),
(40, 'كفر الشيخ', 'Kafrelshegh', 80.00, 0.00, 1, 2, 1),
(41, 'الباقى', 'elbake', 75.00, 0.00, 1, 2, 1),
(42, 'مطروح', 'Matroh', 100.00, 1.00, 1, 2, 1),
(43, 'الجيزه2', 'Algiza', 65.00, 0.00, 1, 1, 1),
(44, 'البحيره', 'Elbehera', 80.00, 0.00, 1, 2, 1);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accounts`
--
ALTER TABLE `accounts`
  ADD CONSTRAINT `fk_accounts_parent` FOREIGN KEY (`parent_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `account_settings`
--
ALTER TABLE `account_settings`
  ADD CONSTRAINT `fk_setting_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `ADDRESSES_ibfk_1` FOREIGN KEY (`CLIENT_ID`) REFERENCES `client` (`CLIENT_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `ADDRESSES_ibfk_2` FOREIGN KEY (`USER_ID`) REFERENCES `users` (`USER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `ADDRESSES_ibfk_3` FOREIGN KEY (`CITY_ID`) REFERENCES `city` (`CI_ID`) ON UPDATE CASCADE;

--
-- Constraints for table `banks`
--
ALTER TABLE `banks`
  ADD CONSTRAINT `fk_bank_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`);

--
-- Constraints for table `branches`
--
ALTER TABLE `branches`
  ADD CONSTRAINT `BRANCHES_CITY_ID_CITY_CI_ID` FOREIGN KEY (`CITY_ID`) REFERENCES `city` (`CI_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `BRANCHES_COMPANIE_ID_COMPANIES_COM_ID` FOREIGN KEY (`COMPANIE_ID`) REFERENCES `companies` (`COM_ID`) ON UPDATE CASCADE;

--
-- Constraints for table `cash_boxes`
--
ALTER TABLE `cash_boxes`
  ADD CONSTRAINT `cash_boxes_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`BR_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cash_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`);

--
-- Constraints for table `city`
--
ALTER TABLE `city`
  ADD CONSTRAINT `CITY_GOV_ID_GOV_GOV_ID` FOREIGN KEY (`GOV_ID`) REFERENCES `gov` (`GOV_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `CITY_ZONE_ID_ZONES_ZONE_ID` FOREIGN KEY (`ZONE_ID`) REFERENCES `zones` (`ZONE_ID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `companies`
--
ALTER TABLE `companies`
  ADD CONSTRAINT `COMPANY_R_CITY_ID` FOREIGN KEY (`CITY_ID`) REFERENCES `city` (`CI_ID`) ON UPDATE CASCADE;

--
-- Constraints for table `cost_centers`
--
ALTER TABLE `cost_centers`
  ADD CONSTRAINT `fk_cost_parent` FOREIGN KEY (`parent_id`) REFERENCES `cost_centers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `crm_order_logs`
--
ALTER TABLE `crm_order_logs`
  ADD CONSTRAINT `fk_crm_orders_orderid` FOREIGN KEY (`ORDER_ID`) REFERENCES `orders` (`ORDER_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `custody_orders`
--
ALTER TABLE `custody_orders`
  ADD CONSTRAINT `custody_orders_ibfk_1` FOREIGN KEY (`ORDER_ID`) REFERENCES `orders` (`ORDER_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `custody_orders_ibfk_2` FOREIGN KEY (`USER_ID`) REFERENCES `users` (`USER_ID`) ON UPDATE CASCADE;

--
-- Constraints for table `custody_product`
--
ALTER TABLE `custody_product`
  ADD CONSTRAINT `custody_order_primarykey` FOREIGN KEY (`ORDER_ID`,`USER_ID`) REFERENCES `custody_orders` (`ORDER_ID`, `USER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `product_code_update_cascad` FOREIGN KEY (`PRO_CODE`) REFERENCES `product_codes` (`PRO_CODE`) ON UPDATE CASCADE;

--
-- Constraints for table `custody_return_orders`
--
ALTER TABLE `custody_return_orders`
  ADD CONSTRAINT `custody_return_orders_ibfk_2` FOREIGN KEY (`ORDER_ID`) REFERENCES `orders` (`ORDER_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `custody_return_orders_ibfk_3` FOREIGN KEY (`USER_ID`) REFERENCES `users` (`USER_ID`) ON UPDATE CASCADE;

--
-- Constraints for table `custody_return_product`
--
ALTER TABLE `custody_return_product`
  ADD CONSTRAINT `custody_return_order_primarykey2` FOREIGN KEY (`ORDER_ID`,`USER_ID`) REFERENCES `custody_return_orders` (`ORDER_ID`, `USER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `custody_return_product_code_update_cascad2` FOREIGN KEY (`PRO_CODE`) REFERENCES `product_codes` (`PRO_CODE`) ON UPDATE CASCADE;

--
-- Constraints for table `financial_transactions`
--
ALTER TABLE `financial_transactions`
  ADD CONSTRAINT `financial_transactions_ibfk_1` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`),
  ADD CONSTRAINT `financial_transactions_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `transaction_categories` (`id`),
  ADD CONSTRAINT `financial_transactions_ibfk_3` FOREIGN KEY (`transaction_user_id`) REFERENCES `users` (`USER_ID`),
  ADD CONSTRAINT `financial_transactions_ibfk_4` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`BR_ID`),
  ADD CONSTRAINT `fk_financial_transactions_journal` FOREIGN KEY (`accounting_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `gov`
--
ALTER TABLE `gov`
  ADD CONSTRAINT `GOV_COUNTRY_ID_COUNTRIES_CO_ID` FOREIGN KEY (`COUNTRY_ID`) REFERENCES `countries` (`CO_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `GOV_REGIONAL_AREAS_ID_REGIONAL_AREAS_ID` FOREIGN KEY (`REGIONAL_AREAS_ID`) REFERENCES `regional_areas` (`ID`) ON UPDATE CASCADE;

--
-- Constraints for table `inventory_count_details`
--
ALTER TABLE `inventory_count_details`
  ADD CONSTRAINT `fk_inventory_count` FOREIGN KEY (`COUNT_ID`) REFERENCES `inventory_counts` (`COUNT_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inventory_count_product` FOREIGN KEY (`PRO_CODE`) REFERENCES `product_codes` (`PRO_CODE`) ON UPDATE CASCADE;

--
-- Constraints for table `inventory_movement`
--
ALTER TABLE `inventory_movement`
  ADD CONSTRAINT `inventory_movement_ibfk_1` FOREIGN KEY (`STO_ID`) REFERENCES `stores` (`STO_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `inventory_movement_ibfk_2` FOREIGN KEY (`IM_USER`) REFERENCES `users` (`USER_ID`) ON DELETE NO ACTION ON UPDATE CASCADE,
  ADD CONSTRAINT `inventory_movement_ibfk_3` FOREIGN KEY (`IM_BRANCH`) REFERENCES `branches` (`BR_ID`);

--
-- Constraints for table `inventory_product_movement`
--
ALTER TABLE `inventory_product_movement`
  ADD CONSTRAINT `inventory_product_movement_ibfk_1` FOREIGN KEY (`IM_ID`) REFERENCES `inventory_movement` (`IM_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `inventory_product_movement_ibfk_2` FOREIGN KEY (`IPM_CODE`) REFERENCES `product_codes` (`PRO_CODE`) ON UPDATE CASCADE;

--
-- Constraints for table `journal_entry_details`
--
ALTER TABLE `journal_entry_details`
  ADD CONSTRAINT `fk_journal_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `fk_journal_entry` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `ORDERS_CITY_ID_CITY_CI_ID` FOREIGN KEY (`CITY_ID`) REFERENCES `city` (`CI_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `ORDERS_DELIVERY_ID_USERS_USER_ID` FOREIGN KEY (`DELIVERY_ID`) REFERENCES `users` (`USER_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `ORDERS_ibfk_1` FOREIGN KEY (`ORDER_TYPE_ID`) REFERENCES `order_type` (`ORDER_TYPE_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `ORDERS_ibfk_2` FOREIGN KEY (`ADDRESS_ID`) REFERENCES `addresses` (`ADDRESS_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `ORDERS_ibfk_3` FOREIGN KEY (`TEL_ID`) REFERENCES `telephones` (`TEL_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `ORDERS_ibfk_4` FOREIGN KEY (`ORDER_STATUS`) REFERENCES `order_statuse` (`STA_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `ORDERS_ibfk_5` FOREIGN KEY (`EXISTS_ID`) REFERENCES `order_exists` (`EXISTS_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_exchange` FOREIGN KEY (`EXCHANGE`) REFERENCES `orders` (`ORDER_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_return_entry` FOREIGN KEY (`RETURN_ENTRY_ID`) REFERENCES `journal_entries` (`id`),
  ADD CONSTRAINT `fk_orders_sale_entry` FOREIGN KEY (`SALE_ENTRY_ID`) REFERENCES `journal_entries` (`id`);

--
-- Constraints for table `orders_log`
--
ALTER TABLE `orders_log`
  ADD CONSTRAINT `ORDERS_LOG_ibfk_1` FOREIGN KEY (`ORDER_ID`) REFERENCES `orders` (`ORDER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `ORDERS_LOG_ibfk_2` FOREIGN KEY (`USER_ACTION`) REFERENCES `users` (`USER_ID`) ON UPDATE CASCADE;

--
-- Constraints for table `order_exists`
--
ALTER TABLE `order_exists`
  ADD CONSTRAINT `order_exists_ibfk_1` FOREIGN KEY (`STA_ID`) REFERENCES `order_statuse` (`STA_ID`);

--
-- Constraints for table `order_product`
--
ALTER TABLE `order_product`
  ADD CONSTRAINT `ORDER_PRODUCT_ibfk_1` FOREIGN KEY (`ORDER_ID`) REFERENCES `orders` (`ORDER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `ORDER_PRODUCT_ibfk_2` FOREIGN KEY (`PRO_CODE`) REFERENCES `product_codes` (`PRO_CODE`) ON UPDATE CASCADE;

--
-- Constraints for table `order_service`
--
ALTER TABLE `order_service`
  ADD CONSTRAINT `order_service_ibfk_1` FOREIGN KEY (`ORDER_ID`) REFERENCES `orders` (`ORDER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `order_service_ibfk_2` FOREIGN KEY (`USER_ID`) REFERENCES `users` (`USER_ID`) ON DELETE NO ACTION ON UPDATE CASCADE,
  ADD CONSTRAINT `order_service_ibfk_3` FOREIGN KEY (`OST_ID`) REFERENCES `order_service_type` (`OST_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `order_service_text`
--
ALTER TABLE `order_service_text`
  ADD CONSTRAINT `order_service_text_ibfk_1` FOREIGN KEY (`OS_ID`) REFERENCES `order_service` (`OS_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `order_service_text_ibfk_2` FOREIGN KEY (`USER_ID`) REFERENCES `users` (`USER_ID`) ON DELETE NO ACTION ON UPDATE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `PRODUCTS_CAT_ID_PRODUCT_CATEGORY_CAT_ID` FOREIGN KEY (`CAT_ID`) REFERENCES `product_category` (`CAT_ID`) ON UPDATE CASCADE;

--
-- Constraints for table `product_codes`
--
ALTER TABLE `product_codes`
  ADD CONSTRAINT `PRODUCT_CODES_ibfk_1` FOREIGN KEY (`PRODUCT_ID`) REFERENCES `products` (`PRO_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_code_join_option`
--
ALTER TABLE `product_code_join_option`
  ADD CONSTRAINT `PRODUCT_CODE_JOIN_OPTION_ibfk_2` FOREIGN KEY (`OPTION_VALUE_ID`) REFERENCES `product_option` (`ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `PRODUCT_CODE_JOIN_OPTION_ibfk_3` FOREIGN KEY (`PRODUCT_CODE`) REFERENCES `product_codes` (`PRO_CODE`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_join_option_group`
--
ALTER TABLE `product_join_option_group`
  ADD CONSTRAINT `PRODUCT_JOIN_OPTION_GROUP_ibfk_1` FOREIGN KEY (`OPTION_GROUP_ID`) REFERENCES `product_option_group` (`OPTION_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `PRODUCT_JOIN_OPTION_GROUP_ibfk_2` FOREIGN KEY (`PRODUCT_ID`) REFERENCES `products` (`PRO_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_option`
--
ALTER TABLE `product_option`
  ADD CONSTRAINT `PRODUCT_OPTION_ibfk_1` FOREIGN KEY (`OPTION_ID`) REFERENCES `product_option_group` (`OPTION_ID`) ON UPDATE CASCADE;

--
-- Constraints for table `purchases_orders`
--
ALTER TABLE `purchases_orders`
  ADD CONSTRAINT `purchases_orders_ibfk_1` FOREIGN KEY (`SUPPLIER_ID`) REFERENCES `suppliers` (`SUP_ID`);

--
-- Constraints for table `purchases_products`
--
ALTER TABLE `purchases_products`
  ADD CONSTRAINT `purchases_products_ibfk_1` FOREIGN KEY (`ORDER_ID`) REFERENCES `purchases_orders` (`ORDER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `purchases_products_ibfk_2` FOREIGN KEY (`PRO_CODE`) REFERENCES `product_codes` (`PRO_CODE`) ON UPDATE CASCADE;

--
-- Constraints for table `purchases_return_orders`
--
ALTER TABLE `purchases_return_orders`
  ADD CONSTRAINT `purchases_return_orders_ibfk_1` FOREIGN KEY (`REFERANC`) REFERENCES `purchases_orders` (`ORDER_ID`) ON UPDATE CASCADE;

--
-- Constraints for table `shipping_movement`
--
ALTER TABLE `shipping_movement`
  ADD CONSTRAINT `shipping_movement_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `users` (`USER_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `shipping_movement_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`USER_ID`) ON UPDATE CASCADE;

--
-- Constraints for table `stores`
--
ALTER TABLE `stores`
  ADD CONSTRAINT `STORES_BRANCH_ID_BRANCHES_BR_ID` FOREIGN KEY (`BRANCH_ID`) REFERENCES `branches` (`BR_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `STORES_STTY_ID_STORES_TYPE_STTY_ID` FOREIGN KEY (`STTY_ID`) REFERENCES `stores_type` (`STTY_ID`) ON UPDATE CASCADE;

--
-- Constraints for table `stores_product`
--
ALTER TABLE `stores_product`
  ADD CONSTRAINT `STORES_PRODUCT_ibfk_1` FOREIGN KEY (`PRO_CODE`) REFERENCES `product_codes` (`PRO_CODE`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `STORES_PRODUCT_ibfk_2` FOREIGN KEY (`STO_ID`) REFERENCES `stores` (`STO_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `supply_orders`
--
ALTER TABLE `supply_orders`
  ADD CONSTRAINT `supply_orders_ibfk_2` FOREIGN KEY (`order_by`) REFERENCES `users` (`USER_ID`) ON UPDATE CASCADE;

--
-- Constraints for table `supply_order_details`
--
ALTER TABLE `supply_order_details`
  ADD CONSTRAINT `supply_order_details_ibfk_1` FOREIGN KEY (`supply_order_id`) REFERENCES `supply_orders` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `supply_order_details_ibfk_2` FOREIGN KEY (`pro_code`) REFERENCES `product_codes` (`PRO_CODE`) ON UPDATE CASCADE;

--
-- Constraints for table `telephones`
--
ALTER TABLE `telephones`
  ADD CONSTRAINT `TELEPHONES_USER_ID_USERS_USER_ID` FOREIGN KEY (`USER_ID`) REFERENCES `users` (`USER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `TELEPHONES_ibfk_1` FOREIGN KEY (`CLIENT_ID`) REFERENCES `client` (`CLIENT_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `transaction_categories`
--
ALTER TABLE `transaction_categories`
  ADD CONSTRAINT `transaction_categories_ibfk_1` FOREIGN KEY (`parent_category_id`) REFERENCES `transaction_categories` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `USERS_BRANCH_ID_BRANCHES_BR_ID` FOREIGN KEY (`BRANCH_ID`) REFERENCES `branches` (`BR_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `USERS_USER_GRUOP_ID_USER_GRUOP_USER_GRUOP_ID` FOREIGN KEY (`USER_GRUOP_ID`) REFERENCES `user_gruop` (`USER_GRUOP_ID`) ON UPDATE CASCADE;

--
-- Constraints for table `users_affiliate`
--
ALTER TABLE `users_affiliate`
  ADD CONSTRAINT `USERS_AFFILIATE_ibfk_1` FOREIGN KEY (`USER_ID`) REFERENCES `users` (`USER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `USERS_AFFILIATE_ibfk_2` FOREIGN KEY (`AFFILIATEED_WITH`) REFERENCES `users` (`USER_ID`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `users_delivery`
--
ALTER TABLE `users_delivery`
  ADD CONSTRAINT `USERS_DELIVERY_ibfk_1` FOREIGN KEY (`USER_ID`) REFERENCES `users` (`USER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `USERS_DELIVERY_ibfk_2` FOREIGN KEY (`ZONE_ID`) REFERENCES `zones` (`ZONE_ID`) ON DELETE SET NULL ON UPDATE SET NULL;
--
-- Phase 1 reference rows (courier custody and commission payable accounts)
--
-- Phase 1: accounts required by the courier (delivery agent) cycle.
-- Approved by the project owner on 2026-11-13:
--   1510 courier custody (child of 1500), 2210 courier commission payable (child of 2200).
-- Idempotent: safe to run more than once. Run on a TEST copy first.

INSERT INTO `accounts`
  (`parent_id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `account_level`, `is_active`, `allow_posting`, `system_account`, `is_system_created`)
SELECT p.`id`, '1510', 'عهدة المندوبين', 'ASSET', 'DEBIT', 3, 1, 1, 0, 0
FROM `accounts` p
WHERE p.`account_code` = '1500'
  AND NOT EXISTS (SELECT 1 FROM `accounts` x WHERE x.`account_code` = '1510');

INSERT INTO `accounts`
  (`parent_id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `account_level`, `is_active`, `allow_posting`, `system_account`, `is_system_created`)
SELECT p.`id`, '2210', 'عمولات مندوبين مستحقة', 'LIABILITY', 'CREDIT', 3, 1, 1, 0, 0
FROM `accounts` p
WHERE p.`account_code` = '2200'
  AND NOT EXISTS (SELECT 1 FROM `accounts` x WHERE x.`account_code` = '2210');

INSERT INTO `account_settings` (`setting_key`, `account_id`, `description`)
SELECT 'DELIVERY_CUSTODY', a.`id`, 'عهدة المندوبين (محصل لدى المندوب قبل التوريد)'
FROM `accounts` a
WHERE a.`account_code` = '1510'
  AND NOT EXISTS (SELECT 1 FROM `account_settings` s WHERE s.`setting_key` = 'DELIVERY_CUSTODY');

INSERT INTO `account_settings` (`setting_key`, `account_id`, `description`)
SELECT 'DELIVERY_COMMISSION_PAYABLE', a.`id`, 'عمولات المندوبين المستحقة (التزام)'
FROM `accounts` a
WHERE a.`account_code` = '2210'
  AND NOT EXISTS (SELECT 1 FROM `account_settings` s WHERE s.`setting_key` = 'DELIVERY_COMMISSION_PAYABLE');

--
-- Views (definitions supplied by the project owner; DEFINER removed for portability)
--
DROP TABLE IF EXISTS `product_options_view`;
DROP VIEW  IF EXISTS `product_options_view`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `product_options_view` AS select `pc`.`PRODUCT_ID` AS `PRO_ID`,`pc`.`PRO_CODE` AS `PRO_CODE`,`pc`.`CODE_COST_PRICE` AS `PURCHASE_PRICE`,`pc`.`CODE_PRICE` AS `SELL_PRICE`,`pc`.`CODE_MIN_STOCK` AS `MIN_STOCK`,`pc`.`CODE_MAX_STOCK` AS `MAX_STOCK`,`pr`.`PRO_NAME` AS `PRO_NAME`,json_arrayagg(json_array(ifnull(`e`.`OPTION_ID`,0),ifnull(`c`.`OPTION_VALUE_ID`,0))) AS `OPT_IDS_JSON`,group_concat(concat(`e`.`OPTION_NAME`,' : ',`d`.`VALUE`) separator ' | ') AS `OPT_JSON` from ((((`product_codes` `pc` left join `products` `pr` on(`pc`.`PRODUCT_ID` = `pr`.`PRO_ID`)) left join `product_code_join_option` `c` on(`pc`.`PRO_CODE` = `c`.`PRODUCT_CODE`)) left join `product_option` `d` on(`d`.`ID` = `c`.`OPTION_VALUE_ID`)) left join `product_option_group` `e` on(`e`.`OPTION_ID` = `d`.`OPTION_ID`)) group by `pc`.`PRO_CODE`,`pr`.`PRO_NAME`;

DROP TABLE IF EXISTS `purchases_orders_view`;
DROP VIEW  IF EXISTS `purchases_orders_view`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `purchases_orders_view` AS select `po`.`ORDER_ID` AS `ORDER_ID`,`po`.`CREATE_AT` AS `CREATE_AT`,`po`.`SUPPLIER_ID` AS `SUPPLIER_ID`,`su`.`SUP_NAME` AS `SUP_NAME`,`po`.`NOTE` AS `NOTE`,`su`.`SUP_EMAIL` AS `SUP_EMAIL`,`tel`.`TEL_NUMBER` AS `TEL_NUMBER`,`po`.`TOTAL_PRICE` AS `TOTAL_PRICE`,coalesce(sum(`pp`.`QTY`),0) AS `QTY_ORDER_TOTAL`,coalesce(sum(`pp`.`QTY_SUPPLIED`),0) AS `QTY_SUPPLIED_TOTAL`,coalesce(sum(`pp`.`QTY`),0) - coalesce(sum(`pp`.`QTY_SUPPLIED`),0) AS `QTY_ORDER_WAITING`,coalesce(sum(`prp`.`SUM_QTY_RETURN`),0) AS `QTY_RETURN`,case when coalesce(sum(`pp`.`QTY_SUPPLIED`),0) = 0 then 'قيد التوريد' when coalesce(sum(`pp`.`QTY_SUPPLIED`),0) > 0 and coalesce(sum(`pp`.`QTY_SUPPLIED`),0) < coalesce(sum(`pp`.`QTY`),0) then 'جزئي' when coalesce(sum(`pp`.`QTY_SUPPLIED`),0) = coalesce(sum(`pp`.`QTY`),0) then 'مكتمل' end AS `SUPPLIED_STATUSE`,case when `po`.`STATUSE` = 'COMPLETED' then 'مدفوع' else 'مؤجل الدفع' end AS `PAYMENT_STATUSE`,case when coalesce(sum(`prp`.`SUM_QTY_RETURN`),0) >= coalesce(sum(`pp`.`QTY_SUPPLIED`),0) then 0 else 1 end AS `RETURN_STATUS`,coalesce(sum((`pp`.`QTY` - `pp`.`QTY_SUPPLIED`) * `pp`.`PURCHASE_UNIT_PRICE`),0) AS `TOTAL_WAIT_PRICE`,coalesce(sum(`pp`.`QTY` * `pp`.`PURCHASE_UNIT_PRICE`),0) AS `TOTAL_ORDER_PRICE`,coalesce(sum(`pp`.`QTY_SUPPLIED` * `pp`.`PURCHASE_UNIT_PRICE`),0) AS `TOTAL_SUP_PRICE`,`po`.`TOTAL_PAYMENTS` AS `TOTAL_PAYMENTS`,json_arrayagg(json_object('PRODUCT_NAME',`pov`.`PRO_NAME`,'PRODUCT_ATTRIBUTES',`pov`.`OPT_JSON`,'PRODUCT_CODE',`pp`.`PRO_CODE`,'QTY',`pp`.`QTY`,'UNIT_PRICE',`pp`.`PURCHASE_UNIT_PRICE`,'QTY_SUPPLIED',`pp`.`QTY_SUPPLIED`,'QTY_RETURN',coalesce(`prp`.`SUM_QTY_RETURN`,0))) AS `PRODUCT_DETAILS` from (((((`purchases_orders` `po` left join `purchases_products` `pp` on(`po`.`ORDER_ID` = `pp`.`ORDER_ID`)) left join (select `pro`.`REFERANC` AS `ORDER_ID`,`prp`.`PRO_CODE` AS `PRO_CODE`,sum(`prp`.`QTY_RETURN`) AS `SUM_QTY_RETURN` from (`purchases_return_orders` `pro` left join `purchases_return_products` `prp` on(`pro`.`ORDER_ID` = `prp`.`ORDER_ID`)) group by `pro`.`REFERANC`,`prp`.`PRO_CODE`) `prp` on(`pp`.`ORDER_ID` = `prp`.`ORDER_ID` and `pp`.`PRO_CODE` = `prp`.`PRO_CODE`)) left join `product_options_view` `pov` on(`pp`.`PRO_CODE` = `pov`.`PRO_CODE`)) left join `suppliers` `su` on(`po`.`SUPPLIER_ID` = `su`.`SUP_ID`)) left join `telephones` `tel` on(`su`.`SUP_ID` = `tel`.`SUPPLIER_ID` and `tel`.`DEFAULTTELL` = 1)) group by `po`.`ORDER_ID`;

SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
