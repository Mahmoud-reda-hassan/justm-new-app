<?php
include_once(ABSPATH.'/inc/configDateTimeFN.php');
class DataProcessor {

	private $dtz;
	// دالة مساعدة لفك تشفير JSON إلى مصفوفة متداخلة
	private function decodeJsonArray($arr) {
		
	    $result = [];
	    foreach ($arr as $key => $val) {
	        // إذا كانت القيمة مصفوفة أو كائن، نقوم بتكرار فك التشفير
	        $result[$key] = (is_string($val) && $this->isJson($val) && is_array(json_decode($val,true))) ? $this->decodeJsonArray(json_decode($val,true)) : $val ;
	    }
	    return $result;
	}
	// الباني (Constructor) لتلقي اتصال قاعدة البيانات عند إنشاء الكائن
	public function __construct() {
		$this->dtz       = new configDateTime();
	}
	// التحقق إذا كان النص JSON صالحًا.
	public function isJson($string) {
	    /*
		 * @param string $string النص المراد التحقق منه.
		 * @return bool هل هو JSON صالح.
		 */
	    if (!is_string($string)) return false;
	
	    $decoded = json_decode($string);
	    return json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded));
	}
    // تنظيف المدخلات مع الحفاظ على بيانات JSON.
	public function sanitizeInput($input) {
	    /*
		 * @param mixed $input المدخل (نص أو مصفوفة).
		 * @return mixed المدخل النظيف.
		 */
	    // التحقق إذا كانت المدخلات مصفوفة
	    if (is_array($input)) {
	        $sanitized = [];
	        foreach ($input as $key => $value) {
	            $sanitized[$key] = $this->sanitizeInput($value); // استدعاء نفس الدالة بشكل متكرر
	        }
	        return $sanitized;
	    }
	
	    // التحقق إذا كانت البيانات JSON
	    if ($this->isJson($input)) {
	        return $input; // إرجاعها كما هي إذا كانت JSON
	    }
		$sinval =  htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
	    // تنظيف النصوص الأخرى
	    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
	}
	public function fetchDataTypeDetection_old($stmt, $fields) {
		$records = [];
	    while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
	        $row = [];
	        foreach ($fields as $field => $fieldName) {
	            $value = $record[$fieldName['MAME']];

	            // التحقق من نوع البيانات والتعامل معها
	            if (($fieldName['TYPE'] === 'TIMESTAMP' || $fieldName['TYPE'] === 'TIME' || $fieldName['TYPE'] === 'DATE') && strtotime($value)) {
				    $dt = [];
				
				    // معالجة القيمة للتأكد من تطابقها مع التنسيقات
				    $value = trim($value); // إزالة المسافات الإضافية
				
				    // التحقق إذا كانت القيمة تحتوي على تاريخ ووقت
				    if (DateTime::createFromFormat('Y-m-d H:i:s.u', $value) || DateTime::createFromFormat('Y-m-d H:i:s', $value)) {
				        $dt[] = date_format($this->dtz->setDateTime($record["CREATE_AT"]), "Y/m/d");
				    	$dt[] = date_format($this->dtz->setDateTime($record["CREATE_AT"]),  "H:i A");
				    }
				    // التحقق إذا كانت القيمة تاريخًا فقط
				    elseif ($dateTime = DateTime::createFromFormat('Y-m-d', $value)) {
				        $dt[] = date_format($this->dtz->setDateTime($record["CREATE_AT"]), "Y/m/d");
				    }
				    // التحقق إذا كانت القيمة وقتًا فقط (مع أو بدون كسور)
				    elseif (DateTime::createFromFormat('H:i:s.u', $value) || DateTime::createFromFormat('H:i:s', $value)) {
				        $dt[] = date_format($this->dtz->setDateTime($record["CREATE_AT"]),  "H:i A");
				    }
				    // دمج النتائج وإسنادها إلى الحقل
				    $row[$fieldName] = !empty($dt) ? implode(' | ', $dt) : null;
				
	            } elseif ($fieldName['TYPE'] === 'JSON' && is_array(json_decode($value,true))) {
	                // فك تشفير JSON إذا كانت البيانات مصفوفة
	                 $reArr = [];
	                 $arr = json_decode($value, true);
	                 foreach($arr as $key => $val){
	                 	$reArr[] = json_decode($val,true);
	                 }
	                 $row[$fieldName] = $reArr;
	            } elseif ($fieldName['TYPE'] === 'TINYINT' && is_bool($value)) {
	                // إذا كانت القيمة منطقية
	                $row[$fieldName] = $value ? true : false;
	            } elseif ($fieldName['TYPE'] === 'STRING' &&  is_string($value)) {
	            	if (is_numeric($value)) {
						if($value == 0){ 
							$value = "0.00"; 
						}else{
			                // إذا كانت القيمة نصية
			                $row[$fieldName] = (string) $value;
						}
	            	}else{
	            		$row[$fieldName] = (string) $value;
	            	}
	            } else {
	                // القيم الأخرى
	                $row[$fieldName] = $value;
	            }
	        }
	        $records[] = $row;
	    }
	    return $records;
	}
	public function fetchDataTypeDetection($stmt, $fieldsName, $fieldsType) {
	    $records = [];
	
	    while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
	        $row = [];
	
	        foreach ($fieldsName as $field => $fieldName) {
	        	
	            $value = $record[$fieldName];
				
	            // التحقق من نوع البيانات والتعامل معها بناءً على نوع الحقل
	            switch ($fieldsType[$fieldName]) {
	                case 'TIMESTAMP':
	                case 'TIME':
	                case 'DATE':
	                    // معالجة التواريخ والوقت
	                    $dt = [];
	                    $value = trim($value); // إزالة المسافات الإضافية
	                    
	                    // التحقق من التنسيقات المختلفة
	                    if ($dateTime = DateTime::createFromFormat('Y-m-d H:i:s.u', $value) ?: DateTime::createFromFormat('Y-m-d H:i:s', $value)) {
	                        $dt[] = date_format($this->dtz->setDateTime($value), "Y/m/d");
	                        $dt[] = date_format($this->dtz->setDateTime($value), "H:i A");
	                    } elseif ($dateTime = DateTime::createFromFormat('Y-m-d', $value)) {
	                        $dt[] = date_format($this->dtz->setDateTime($value), "Y/m/d");
	                    } elseif ($dateTime = DateTime::createFromFormat('H:i:s.u', $value) ?: DateTime::createFromFormat('H:i:s', $value)) {
	                        $dt[] = date_format($this->dtz->setDateTime($value), "H:i A");
	                    }
	
	                    // دمج النتائج وإسنادها إلى الحقل
	                    $row[$fieldName] = $dt ? implode(' | ', $dt) : '';
	                    break;
	
	                case 'JSON':
	                    // معالجة JSON
	                    
	                    if ($this->isJson($value)) {
	                    	$decodedValue = json_decode($value, true);
	                    	//print_r($decodedValue);
	                        $row[$fieldName] = $this->decodeJsonArray($decodedValue);
	                        
	                    } else {
	                        $row[$fieldName] = null; // في حال كان JSON غير صحيح
	                    }
	                    break;
	
	                case 'TINYINT':
	                    // معالجة TINYINT (منطقية)
	                    $row[$fieldName] = (bool) $value;
	                    break;
	 
	                case 'STRING':
	                    // معالجة النصوص
	                    if (is_numeric($value)) {
	                        $row[$fieldName] = number_format((float)$value, 2); // تنسيق القيم الرقمية
	                    } else {
	                        $row[$fieldName] = (string) $value;
	                    }
	                    break;
					case 'TELEPHONE':
	                    // معالجة النصوص
	                    $row[$fieldName] = (string) $value;
	                    break;
	
	                default:
	                    // القيم الأخرى (حافظ عليها كما هي)
	                    $row[$fieldName] = $value;
	                    break;
	            }
	        }
	
	        $records[] = $row;
	    }
	
	    return $records;
	}

}