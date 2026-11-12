<?php
class configDateTime {
	public function DateTimeNaw(){
		
		// ضبط المنطقة الزمنية لمصر
		date_default_timezone_set(TIMEZONE);
		// الحصول على الوقت الحالي
		$current_time 	= new DateTime();
		// التحقق مما إذا كان التاريخ يقع ضمن فترة التوقيت الصيفي
		$start_dst 		= new DateTime('last Friday of April ' . date('Y'));
		$end_dst 		= new DateTime('last Friday of October ' . date('Y'));
		$is_dst = date("I");
		// التحقق مما إذا كان التوقيت الصيفي مفعّلًا
		if (!$is_dst && $current_time >= $start_dst && $current_time < $end_dst) {
		    // إضافة ساعة للتوقيت الصيفي
		    $current_time->modify('+1 hour');
		}
		return $current_time->format('Y-m-d H:i:s.u');
	}
	public function setDateTime(&$date_string){
		
		$default_timezone = date_default_timezone_get();
		$old_timezone = new DateTimeZone($default_timezone);
		// إنشاء كائن DateTime باستخدام التاريخ والوقت والنص والمنطقة الزمنية الأصلية
		$date = new DateTime($date_string,$old_timezone);
		// تحويل إلى منطقة زمنية جديدة (مثال: نيويورك)
		if($default_timezone == "UTC"){
			$new_timezone = new DateTimeZone(TIMEZONE);
			$date->setTimeZone($new_timezone);
		}
		 
		$is_dst = date("I");
		// التحقق مما إذا كان التاريخ يقع ضمن فترة التوقيت الصيفي
		$start_dst 		= new DateTime('last Friday of April '   . $date->format('Y'));
		$end_dst 		= new DateTime('last Friday of October ' . $date->format('Y'));
		// التحقق مما إذا كان التوقيت الصيفي مفعّلًا
		if (!$is_dst && $date >= $start_dst && $date < $end_dst) {
		    // إضافة ساعة للتوقيت الصيفي
		    $date->modify('+1 hour');
		}
		return $date;
	}
	
	public function resetDateTimeUTC($date_string) {
	    try {
	        $user_timezone = new DateTimeZone(TIMEZONE);
			// تحويل تاريخ البداية إلى UTC
			$startDateTime = new DateTime($date_string, $user_timezone);
			$startDateTime->setTimezone(new DateTimeZone('UTC'));
			$startDateUTC = $startDateTime->format('Y-m-d H:i:s');
			return $startDateUTC;
	    } catch (Exception $e) {
	        throw new Exception("خطأ في معالجة التاريخ: " . $e->getMessage());
	    }
	}	
	
}
?>