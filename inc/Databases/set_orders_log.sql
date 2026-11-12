DROP PROCEDURE IF EXISTS set_orders_log;
DELIMITER $$
CREATE DEFINER=`justadmin2`@`%` PROCEDURE `set_orders_log`(IN `p_session_id` VARCHAR(64), IN `p_executed_by` INT, IN `p_executed_group` VARCHAR(250), OUT `p_error_code` INT, OUT `p_status` VARCHAR(10), OUT `p_message` TEXT)
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
DELIMITER ;