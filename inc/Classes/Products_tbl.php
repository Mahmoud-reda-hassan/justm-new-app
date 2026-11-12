<?php
/*================================================
**  Products Database Table
**==============================================*/
class Products_tbl {

    private $ProductsTbl = 'PRODUCTS';
    public $pro_id;
    public $cat_id;
    public $pro_visble;
    public $pro_name;
    public $per_booking;
    public $pro_description;
    public $length;
    public $width;
    public $height;
    public $weight;
    public $pro_img_link;
    //-----------------------------------
    public $pro_item;
    public $pro_new_item;
    public $pro_option;
    //----------PRODUCT_CODES------------
    private $ProductCodesTbl = "PRODUCT_CODES";
    public $product_code_id;
    public $product_id;
    public $pro_code;
    public $code_lowest_price;
    public $code_price;
    public $code_cost_price;
    public $code_min_stock;
    public $code_max_stock;
    //-----PRODUCT_CODE_JOIN_OPTION-----
    private $CodeJoinOptionTbl = "PRODUCT_CODE_JOIN_OPTION";
    public $product_code;
    public $optionid;
    
    public $sto_id;
    public $prolength;
    //-------connection_database--------
    private $conn;
    //---------start_functions----------
    private function check_pro_codes($proArray){
    	$stmtSHPC = $this->conn->prepare("SELECT PRO_CODE FROM product_codes");
    	$stmtSHPC->execute();
    	$codes = $stmtSHPC->fetchAll(PDO::FETCH_ASSOC);
    	
    }
    public function __construct($db) {
        $this->conn = $db;
    }
    // دالة حساب رقم التحقق (Check Digit)
    private function calculateCheckDigit($barcode) {
        if (strlen($barcode) !== 12) {
            throw new Exception("خطأ: الطول قبل حساب Check Digit يجب أن يكون 12، لكن الكود هو: $barcode");
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $num = (int) $barcode[$i];
            $sum += ($i % 2 === 0) ? $num : $num * 3;
        }
        return (10 - ($sum % 10)) % 10;
    }
    // إنشاء مجموعة من الأكواد المتسلسلة
    public function generateMultipleCodes($count, $prefix = "29") {
        if ($count <= 0) {
            throw new Exception("عدد الأكواد يجب أن يكون أكبر من 0");
        }

        // بدء المعاملة (Transaction)
        $this->conn->beginTransaction();

        // جلب آخر كود مخزن
        $stmt = $this->conn->prepare("SELECT last_code FROM barcode_counter FOR UPDATE");
        $stmt->execute();
        $lastCode = $stmt->fetchColumn();

        if (!$lastCode) {
            // إذا لم يكن هناك كود مسجل، ابدأ من الحد الأدنى المسموح به
            $lastCode = $prefix . "0000000000"; // 10 أرقام بعد 29
            $stmt1 = $this->conn->prepare("INSERT barcode_counter (last_code) values (?)" );
        	$stmt1->execute([$lastCode]);
        }
		
        // استخراج الجزء الرقمي بعد البادئة
        $numericPart = (int)substr($lastCode, strlen($prefix));

        // حساب نطاق الأكواد الجديدة
        $startNumber = $numericPart + 1;
        $endNumber = $startNumber + $count - 1;

        // تحديث آخر كود في قاعدة البيانات
        $newLastCode =(int)$prefix . str_pad($endNumber, 10, "0", STR_PAD_LEFT); // 10 أرقام بعد 29
       
        $stmt2 = $this->conn->prepare("UPDATE barcode_counter SET last_code = ? " );
        $stmt2->execute([$newLastCode]);

        // تأكيد العملية
        $this->conn->commit();

        // إنشاء الأكواد مع حساب رقم التحقق
        $codes = [];
        for ($i = $startNumber; $i <= $endNumber; $i++) {
            $barcodeWithoutCheck = $prefix . str_pad($i, 10, "0", STR_PAD_LEFT); // 10 أرقام بعد 29
            if (strlen($barcodeWithoutCheck) !== 12) {
                throw new Exception("خطأ: الكود الناتج ليس بطول 12 رقمًا قبل إضافة Check Digit! الكود: $barcodeWithoutCheck");
            }
            $checkDigit = $this->calculateCheckDigit($barcodeWithoutCheck);
            $finalBarcode = $barcodeWithoutCheck . $checkDigit;

            // التحقق من الطول النهائي
            if (strlen($finalBarcode) !== 13) {
                throw new Exception("خطأ: الكود النهائي ليس بطول 13 رقمًا! الكود: $finalBarcode");
            }

            $codes[] = $finalBarcode;
        }

        return $codes;
    }
    public function addProduct() {
        $z = 0;
        if (empty($this->length)) {
            $this->length = 0;
        }
        if (empty($this->width)) {
            $this->width = 0;
        }
        if (empty($this->height)) {
            $this->height = 0;
        }
        if (empty($this->weight)) {
            $this->weight = 0;
        }
        if (empty($this->pro_description)) {
            $this->pro_description = '';
        }
        $sqlQuery0 = "INSERT INTO `PRODUCTS`(`CAT_ID`, `PRO_VISBLE`, `PRO_NAME`, `PER_BOOKING`, `PRO_DESCRIPTION`, `LENGTH`, `WIDTH`, `HEIGHT`, `WEIGHT`, `PRO_IMG_LINK`) VALUES (?,?,?,?,?,?,?,?,?,?); ";
        $sqlQuery1 = "INSERT INTO `PRODUCT_CODES`(`PRODUCT_ID`, `PRO_CODE`, LOWEST_PRICE, `CODE_PRICE`, `CODE_COST_PRICE`, `CODE_MIN_STOCK`, `CODE_MAX_STOCK`) VALUES (?,?,?,?,?,?,?)";
        $sqlQuery2 = "INSERT INTO `PRODUCT_CODE_JOIN_OPTION`(`PRODUCT_CODE`, `OPTION_VALUE_ID`) VALUES (?,?) ";
        $sqlQuery3 = "INSERT INTO `STORES_PRODUCT`(`STO_ID`, `PRO_CODE`, `STO_QTY`) SELECT A.STO_ID, A.PRO_CODE, A.STO_QTY FROM  (SELECT B.STO_ID,A.PRO_CODE, 0 AS STO_QTY FROM  PRODUCT_CODES A JOIN STORES B) A LEFT JOIN STORES_PRODUCT B ON A.STO_ID = B.STO_ID  AND A.PRO_CODE = B.PRO_CODE WHERE B.PRO_CODE IS NULL";
        try {
            $this->conn->beginTransaction();
            $this->cat_id = htmlspecialchars(strip_tags($this->cat_id));
            $this->pro_visble = htmlspecialchars(strip_tags(trim($this->pro_visble)));
            $this->pro_name = htmlspecialchars(strip_tags(trim($this->pro_name)));
            $this->per_booking = htmlspecialchars(strip_tags(trim($this->per_booking)));
            $this->length = htmlspecialchars(strip_tags(trim($this->length)));
            $this->width = htmlspecialchars(strip_tags(trim($this->width)));
            $this->height = htmlspecialchars(strip_tags(trim($this->height)));
            $this->weight = htmlspecialchars(strip_tags(trim($this->weight)));
            $this->pro_img_link = htmlspecialchars(strip_tags(trim($this->pro_img_link)));

            $stmt0 = $this->conn->prepare($sqlQuery0);
            $stmt0->bindValue(1, $this->cat_id, PDO::PARAM_INT);
            $stmt0->bindValue(2, $this->pro_visble, PDO::PARAM_STR);
            $stmt0->bindValue(3, $this->pro_name, PDO::PARAM_STR);
            $stmt0->bindValue(4, $this->per_booking, PDO::PARAM_STR);
            $stmt0->bindValue(5, $this->pro_description, PDO::PARAM_STR);
            $stmt0->bindValue(6, $this->length, PDO::PARAM_STR);
            $stmt0->bindValue(7, $this->width, PDO::PARAM_STR);
            $stmt0->bindValue(8, $this->height, PDO::PARAM_STR);
            $stmt0->bindValue(9, $this->weight, PDO::PARAM_STR);
            $stmt0->bindValue(10,$this->pro_img_link, PDO::PARAM_STR);
            if ($stmt0->execute()) {
                $done = 0;
                $err = 0;
                $this->product_id = $this->conn->lastInsertId();
                $x = json_decode($this->pro_item);

                for ($i = 0; $i < count($x); $i++) {
					$barcode = (string) $x[$i][0]; // مثال على باركود
					$firstDigit = substr(trim($barcode), 0, 1); // استخراج أول رقم وتحويله إلى عدد صحيح
                    $this->product_id = htmlspecialchars(strip_tags(trim($this->product_id, " ")));
                    $this->pro_code   = htmlspecialchars(strip_tags(trim($x[$i][0], " ")));
                    $this->code_cost_price = htmlspecialchars(strip_tags(trim($x[$i][1], " ")));
					$this->code_lowest_price = htmlspecialchars(strip_tags(trim($x[$i][2], " ")));
					$this->code_price = htmlspecialchars(strip_tags(trim($x[$i][3], " ")));
                    $this->code_min_stock = htmlspecialchars(strip_tags(trim($x[$i][4], " ")));
                    $this->code_max_stock = htmlspecialchars(strip_tags(trim($x[$i][5], " ")));
                    					
                    if($firstDigit>0){
	                    if (strlen($this->pro_code) >= 12 && strlen($this->pro_code) <= 13) {
	                        if ($this->code_cost_price <= $this->code_price) {
	                            if ($this->code_min_stock <= $this->code_max_stock) {
	                                $st[$i] = $this->conn->prepare($sqlQuery1);
	                                $st[$i]->bindValue(1, $this->product_id, PDO::PARAM_INT);
	                                $st[$i]->bindValue(2, $this->pro_code, PDO::PARAM_INT);
	                                $st[$i]->bindValue(3, $this->code_lowest_price, PDO::PARAM_INT);
	 								$st[$i]->bindValue(4, $this->code_price, PDO::PARAM_INT);
	                                $st[$i]->bindValue(5, $this->code_cost_price, PDO::PARAM_INT);
	                                $st[$i]->bindValue(6, $this->code_min_stock, PDO::PARAM_INT);
	                                $st[$i]->bindValue(7, $this->code_max_stock, PDO::PARAM_INT);
	                                $st[$i]->execute();
	                                if ($st[$i]->rowCount() > 0) {
	                                    $done++;
	                                } else {
	                                    $err++;
	                                }
	                            } else {
	                                echo json_encode("الكود".$this->pro_code."الحد الاقصى للمخزون اقل من الحد الادنى ");
	                                $this->conn->rollback();
	                                exit;
	                            }
	                        } else {
	                            echo json_encode("الكود".$this->pro_code."سعر البيع اقل من سعر الشراء");
	                            $this->conn->rollback();
	                            exit;
	                        }
	                    } else {
	                        echo json_encode(" الكود ".$this->pro_code ." الذى ادخلته اقصر من 12 رقم او اطول من 13 رقم ");
	                        $this->conn->rollback();
	                        exit;
	                    }
                    }else{
                    	echo json_encode(" الكود ".$this->pro_code ." الذى ادخلته يبداء ب صفر وهذا غير مسموح - للتكويد المحلى يمكنك استخدام الكود بهذا التنسيق 99xxxxxxxxxx وذالك حتى لايحدث تعارض بين الاكواد العالميه والاكواد المحليه
                    	");
	                    $this->conn->rollback();
	                    exit;
                    }
                }
                if ($done > 0 && $err == 0) {
                    if (count(json_decode($this->pro_option)) > 0) {
                        $done = 0;
                        $err = 0;
                        $x = json_decode($this->pro_option);

                        for ($i = 0; $i < count($x); $i++) {
                            for ($y = 0; $y < count($x[$i][1]); $y++) {
                                $this->product_code = htmlspecialchars(strip_tags(trim($x[$i][0], " ")));
                                $this->optionid = htmlspecialchars(strip_tags(trim($x[$i][1][$y], " ")));

                                $stmt[$y] = $this->conn->prepare($sqlQuery2);
                                $stmt[$y]->bindValue(1, $this->product_code, PDO::PARAM_STR);
                                $stmt[$y]->bindValue(2, $this->optionid, PDO::PARAM_STR);
                                if ($stmt[$y]->execute()) {
                                    $done++;
                                } else {
                                    $err++;
                                }
                            }
                        }
                        if ($done > 0 && $err == 0) {
                            $stmt33 = $this->conn->prepare($sqlQuery3);
                            if ($stmt33->execute()) {
                                $this->conn->commit();
                                echo json_encode($done);
                            } else {
                                echo json_encode("ERROR_INSERT_STORES_PRODUCT");
                                $this->conn->rollback();
                            }
                        } else {
                            echo json_encode("ERROR_INSERT_OPTION");
                            $this->conn->rollback();
                        }
                    } else {
                        $stmt33 = $this->conn->prepare($sqlQuery3);
                        if ($stmt33->execute()) {
                            $this->conn->commit();
                            echo json_encode($done);
                        } else {
                            echo json_encode("ERROR_INSERT_STORES_PRODUCT");
                            $this->conn->rollback();
                        }
                    }
                } else {
                    echo json_encode("ERROR_INSERT_CODES");
                    $this->conn->rollback();
                }
            } else {
                echo json_encode("ERROR_INSERT_PRODUCT");
                $this->conn->rollback();
            }
        } catch (PDOException $e) {
            $this->conn->rollback();
            echo json_encode('error : '. $e->getCode() .' - '.$e->getMessage());
            //print_r($this->conn->errorInfo());
        }
    }
    /*public function updateProduct() {
        $z = 0;
        if (empty($this->length)) {
            $this->length = 0;
        }
        if (empty($this->width)) {
            $this->width = 0;
        }
        if (empty($this->height)) {
            $this->height = 0;
        }
        if (empty($this->weight)) {
            $this->weight = 0;
        }
        if (empty($this->pro_description)) {
            $this->pro_description = '';
        }
        $sqlQuery0 = "UPDATE `PRODUCTS` 
        	SET `CAT_ID` = ?, 
        	`PRO_VISBLE` = ?, 
        	`PRO_NAME` = ?, 
        	`PER_BOOKING` = ?, 
        	`PRO_DESCRIPTION` = ?, 
        	`LENGTH` = ?, 
        	`WIDTH` = ?, 
        	`HEIGHT` = ?, 
        	`WEIGHT` = ?, 
        	`PRO_IMG_LINK` = ? 
        	WHERE PRO_ID = ? ; ";
        $sqlQuery1 = "UPDATE `PRODUCT_CODES` 
        	SET `PRO_CODE` = ?, 
        	`CODE_COST_PRICE` = ?, 
        	`LOWEST_PRICE` = ?, 
        	`CODE_PRICE` = ?, 
        	`CODE_MIN_STOCK` = ?, 
        	`CODE_MAX_STOCK` = ? 
        	WHERE PRODUCT_CODE_ID = ? ;";
        $sqlQuery2 = "INSERT INTO `PRODUCT_CODES`(`PRODUCT_ID`, `PRO_CODE`, `LOWEST_PRICE`, `CODE_COST_PRICE`, `CODE_PRICE`, `CODE_MIN_STOCK`, `CODE_MAX_STOCK`) VALUES (?,?,?,?,?,?,?)";
        $sqlQuery3 = "INSERT INTO `PRODUCT_CODE_JOIN_OPTION`(`PRODUCT_CODE`, `OPTION_VALUE_ID`) VALUES (?,?) ";
        $sqlQuery4 = "INSERT INTO `STORES_PRODUCT`(`STO_ID`, `PRO_CODE`, `STO_QTY`) SELECT A.STO_ID, A.PRO_CODE, A.STO_QTY FROM  (SELECT B.STO_ID,A.PRO_CODE, 0 AS STO_QTY FROM  PRODUCT_CODES A JOIN STORES B) A LEFT JOIN STORES_PRODUCT B ON A.STO_ID = B.STO_ID  AND A.PRO_CODE = B.PRO_CODE WHERE B.PRO_CODE IS NULL";
        
        $this->pro_id = htmlspecialchars(strip_tags(trim($this->pro_id)));
        $this->pro_name = htmlspecialchars(strip_tags(trim($this->pro_name)));
        $this->cat_id = htmlspecialchars(strip_tags(trim($this->cat_id)));
        $this->pro_visble = htmlspecialchars(strip_tags(trim($this->pro_visble)));
        $this->per_booking = htmlspecialchars(strip_tags(trim($this->per_booking)));
        $this->pro_img_link = htmlspecialchars(strip_tags(trim($this->pro_img_link)));
        $this->length = htmlspecialchars(strip_tags(trim($this->length)));
        $this->width = htmlspecialchars(strip_tags(trim($this->width)));
        $this->height = htmlspecialchars(strip_tags(trim($this->height)));
        $this->weight = htmlspecialchars(strip_tags(trim($this->weight)));
        try {
            $this->conn->beginTransaction();
            // update products
            $stmt0 = $this->conn->prepare($sqlQuery0);
            $stmt0->bindValue(1, $this->cat_id, PDO::PARAM_INT);
            $stmt0->bindValue(2, $this->pro_visble, PDO::PARAM_STR);
            $stmt0->bindValue(3, $this->pro_name, PDO::PARAM_STR);
            $stmt0->bindValue(4, $this->per_booking, PDO::PARAM_STR);
            $stmt0->bindValue(5, $this->pro_description, PDO::PARAM_STR);
            $stmt0->bindValue(6, $this->length, PDO::PARAM_STR);
            $stmt0->bindValue(7, $this->width, PDO::PARAM_STR);
            $stmt0->bindValue(8, $this->height, PDO::PARAM_STR);
            $stmt0->bindValue(9, $this->weight, PDO::PARAM_STR);
            $stmt0->bindValue(10,$this->pro_img_link, PDO::PARAM_STR);
            $stmt0->bindValue(11,$this->pro_id, PDO::PARAM_INT);
            if ($stmt0->execute()) {
                $done = 0;
                $err = 0;
                $x = json_decode($this->pro_item);
                for ($i = 0; $i < count($x); $i++) {
                	
                    $this->pro_code = htmlspecialchars(strip_tags(trim($x[$i][0], " ")));
                    $this->code_cost_price = htmlspecialchars(strip_tags(trim($x[$i][1], " ")));
                    $this->code_lowest_price = htmlspecialchars(strip_tags(trim($x[$i][2], " ")));
                    $this->code_price = htmlspecialchars(strip_tags(trim($x[$i][3], " ")));
                    $this->code_min_stock = htmlspecialchars(strip_tags(trim($x[$i][4], " ")));
                    $this->code_max_stock = htmlspecialchars(strip_tags(trim($x[$i][5], " ")));
                    $this->product_code_id = htmlspecialchars(strip_tags(trim($x[$i][6], " ")));
                    // update product_codes
                    $st[$i] = $this->conn->prepare($sqlQuery1);
                    $st[$i]->bindValue(1, $this->pro_code, PDO::PARAM_INT);
                    $st[$i]->bindValue(2, $this->code_cost_price, PDO::PARAM_STR);
                    $st[$i]->bindValue(3, $this->code_lowest_price, PDO::PARAM_STR);
                    $st[$i]->bindValue(4, $this->code_price, PDO::PARAM_STR);
                    $st[$i]->bindValue(5, $this->code_min_stock, PDO::PARAM_STR);
                    $st[$i]->bindValue(6, $this->code_max_stock, PDO::PARAM_STR);
                    $st[$i]->bindValue(7, $this->product_code_id, PDO::PARAM_INT);
                    if ($st[$i]->execute()) {
                        $done++;
                    } else {
                        $err++;
                    }
                }
                if ($done > 0 && $err == 0) {
                    // add product_codes
                    $x = json_decode($this->pro_new_item);
                    if (count($x) > 0) {
                        $done = 0;
                        $err = 0;
                        for ($i = 0; $i < count($x); $i++) {
                            $this->pro_code = htmlspecialchars(strip_tags(trim($x[$i][0], " ")));
                            $this->code_cost_price = htmlspecialchars(strip_tags(trim($x[$i][1], " ")));
                            $this->code_lowest_price = htmlspecialchars(strip_tags(trim($x[$i][2], " ")));
   							$this->code_price = htmlspecialchars(strip_tags(trim($x[$i][3], " ")));
                            $this->code_min_stock = htmlspecialchars(strip_tags(trim($x[$i][4], " ")));
                            $this->code_max_stock = htmlspecialchars(strip_tags(trim($x[$i][5], " ")));
                            //$this->product_code_id = htmlspecialchars(strip_tags(trim($x[$i][5]," ")));
                            $st[$i] = $this->conn->prepare($sqlQuery2);
                            $st[$i]->bindValue(1, $this->pro_id, PDO::PARAM_INT);
                            $st[$i]->bindValue(2, $this->pro_code, PDO::PARAM_INT);
                            $st[$i]->bindValue(3, $this->code_lowest_price, PDO::PARAM_STR);
                            $st[$i]->bindValue(4, $this->code_cost_price, PDO::PARAM_STR);
                            $st[$i]->bindValue(5, $this->code_price, PDO::PARAM_STR);
                            $st[$i]->bindValue(6, $this->code_min_stock, PDO::PARAM_STR);
                            $st[$i]->bindValue(7, $this->code_max_stock, PDO::PARAM_STR);
                            //$st[$i]->bindValue(6, $this->product_code_id, PDO::PARAM_INT);
                            if ($st[$i]->execute()) {
                                $done++;
                            } else {
                                $err++;
                            }
                        }
                        if ($done > 0 && $err == 0) {
                            //add new option
                            $done = 0;
                            $err = 0;
                            $x = json_decode($this->pro_option);
                            for ($i = 0; $i < count($x); $i++) {
                            	$this->product_code = htmlspecialchars(strip_tags(trim($x[$i][0], " ")));
                               	$checkStmt = $this->conn->prepare("SELECT COUNT(*) FROM `PRODUCT_CODE_JOIN_OPTION` WHERE `PRODUCT_CODE` = ? ");
						        $checkStmt->bindValue(1, $this->product_code , PDO::PARAM_STR);
						        $checkStmt->execute();
						        // إذا كان السجل غير موجود، نقوم بإضافته
								if ($checkStmt->fetchColumn() == 0) {
									for ($y = 0; $y < count($x[$i][1]); $y++) {
										
										$this->optionid = htmlspecialchars(strip_tags(trim($x[$i][1][$y], " ")));
										$stmt[$y] = $this->conn->prepare($sqlQuery3);
										$stmt[$y]->bindValue(1, $this->product_code, 	PDO::PARAM_STR);
										$stmt[$y]->bindValue(2, $this->optionid, 		PDO::PARAM_INT);
										if ($stmt[$y]->execute()) {
											$done++;
										} else {
											$err++;
										}
									}
								}
							}
                            
                            
                            // متغيرين لعدّ عدد العمليات الناجحة والفاشلة
					/*		$done = 0;
							$err = 0;
						
						    // تحويل JSON من قاعدة البيانات إلى مصفوفة PHP للاستخدام
						    $options = json_decode($this->pro_option, true);
						
						    // تكرار على كل عنصر في المصفوفة، حيث يمثل كل عنصر منتجًا وخياراته
						    foreach ($options as $option) {
						        // استخراج كود المنتج مع تنظيفه من المدخلات الضارة والمسافات غير الضرورية
						        $product_code = htmlspecialchars(strip_tags(trim($option[0])));

						        // تكرار على كل خيار مرتبط بالمنتج
						        foreach ($option[1] as $opt) {
						            // تنظيف ID الخيار بنفس الطريقة
						            $optionid = htmlspecialchars(strip_tags(trim($opt)));
						
						            // التحقق مما إذا كان السجل موجودًا بالفعل
						            $checkQuery = "SELECT COUNT(*) FROM `PRODUCT_CODE_JOIN_OPTION` WHERE `PRODUCT_CODE` = ? ";
						            $checkStmt = $this->conn->prepare($checkQuery);
						            $checkStmt->bindValue(1, $product_code, PDO::PARAM_STR);
						            
						            $checkStmt->execute();
						
						            // إذا كان السجل غير موجود، نقوم بإضافته
						            if ($checkStmt->fetchColumn() == 0) {
						                // تحضير استعلام الإدخال لإضافة المنتج والخيار
						                $stmt = $this->conn->prepare($sqlQuery3);
						                $stmt->bindValue(1, $product_code, PDO::PARAM_STR);
						                $stmt->bindValue(2, $optionid, PDO::PARAM_INT);
						
						                // تنفيذ الاستعلام والتحقق من نجاحه
						                if ($stmt->execute()) {
						                    $done++;
						                } else {
						                    $err++;
						                }
						            }
						        }
						    } */


                           /* if ($done > 0 && $err == 0) {
                            	$stmtSTP = $this->conn->prepare($sqlQuery4);
						        if($stmtSTP->execute()){
	                                $this->conn->commit();
	                                echo json_encode($done);
						        }else{
						        	echo json_encode("ERROR_INSERT_STORES_PRODUCT");
                                	$this->conn->rollback();
						        }
                            } else {
                                echo json_encode("ERROR_INSERT_OPTION");
                                $this->conn->rollback();
                            }
                        } else {
                            echo json_encode("ERROR_INSERT_CODES");
                            $this->conn->rollback();
                        }
                    } else {
                        $this->conn->commit();
                        echo json_encode($done);
                    }
                } else {
                    echo json_encode("ERROR_UPDATE_CODES");
                    $this->conn->rollback();
                }
            } else {
                echo json_encode("ERROR_UPDATE_PRODUCT");
                $this->conn->rollback();
            }
        } catch (PDOException $e) {
            $this->conn->rollback();
            echo json_encode('error'.$e->getCode() .' - '.$e->getMessage());
        }
    }*/
	
	public function updateProduct() {

		try {

			$this->conn->beginTransaction();

			// ==========================
			// تنظيف البيانات الأساسية
			// ==========================

			$this->pro_id          = (int) trim($this->pro_id);
			$this->cat_id          = (int) trim($this->cat_id);

			$this->pro_name        = htmlspecialchars(strip_tags(trim($this->pro_name)));
			$this->pro_visble      = htmlspecialchars(strip_tags(trim($this->pro_visble)));
			$this->per_booking     = htmlspecialchars(strip_tags(trim($this->per_booking)));
			$this->pro_description = htmlspecialchars(strip_tags(trim($this->pro_description ?? '')));
			$this->pro_img_link    = htmlspecialchars(strip_tags(trim($this->pro_img_link)));

			$this->length          = !empty($this->length) ? $this->length : 0;
			$this->width           = !empty($this->width) ? $this->width : 0;
			$this->height          = !empty($this->height) ? $this->height : 0;
			$this->weight          = !empty($this->weight) ? $this->weight : 0;

			// ==========================
			// تحديث المنتج الأساسي
			// ==========================

			$sqlProduct = "
				UPDATE PRODUCTS SET
					CAT_ID = ?,
					PRO_VISBLE = ?,
					PRO_NAME = ?,
					PER_BOOKING = ?,
					PRO_DESCRIPTION = ?,
					LENGTH = ?,
					WIDTH = ?,
					HEIGHT = ?,
					WEIGHT = ?,
					PRO_IMG_LINK = ?
				WHERE PRO_ID = ?
			";

			$stmtProduct = $this->conn->prepare($sqlProduct);

			$stmtProduct->execute([
				$this->cat_id,
				$this->pro_visble,
				$this->pro_name,
				$this->per_booking,
				$this->pro_description,
				$this->length,
				$this->width,
				$this->height,
				$this->weight,
				$this->pro_img_link,
				$this->pro_id
			]);

			// ==========================
			// تحديث الأكواد القديمة
			// ==========================

			$items = json_decode($this->pro_item, true);

			if (!is_array($items)) {
				throw new Exception("INVALID_PRODUCT_ITEMS");
			}

			$sqlUpdateCode = "
				UPDATE PRODUCT_CODES SET
					PRO_CODE = ?,
					CODE_COST_PRICE = ?,
					LOWEST_PRICE = ?,
					CODE_PRICE = ?,
					CODE_MIN_STOCK = ?,
					CODE_MAX_STOCK = ?
				WHERE PRODUCT_CODE_ID = ?
			";

			$stmtUpdateCode = $this->conn->prepare($sqlUpdateCode);

			foreach ($items as $row) {

				$pro_code         = trim($row[0]);
				$cost_price       = trim($row[1]);
				$lowest_price     = trim($row[2]);
				$price            = trim($row[3]);
				$min_stock        = trim($row[4]);
				$max_stock        = trim($row[5]);
				$product_code_id  = (int) trim($row[6]);

				if ($pro_code == '') {
					throw new Exception("EMPTY_PRODUCT_CODE");
				}

				// منع التكرار
				$checkDuplicate = $this->conn->prepare("
					SELECT COUNT(*)
					FROM PRODUCT_CODES
					WHERE PRO_CODE = ?
					AND PRODUCT_CODE_ID != ?
				");

				$checkDuplicate->execute([
					$pro_code,
					$product_code_id
				]);

				if ($checkDuplicate->fetchColumn() > 0) {
					throw new Exception("DUPLICATE_PRODUCT_CODE : ".$pro_code);
				}

				$stmtUpdateCode->execute([
					$pro_code,
					$cost_price,
					$lowest_price,
					$price,
					$min_stock,
					$max_stock,
					$product_code_id
				]);
			}

			// ==========================
			// إضافة أكواد جديدة
			// ==========================

			$newItems = json_decode($this->pro_new_item, true);

			if (is_array($newItems) && count($newItems) > 0) {

				$sqlInsertCode = "
					INSERT INTO PRODUCT_CODES
					(
						PRODUCT_ID,
						PRO_CODE,
						LOWEST_PRICE,
						CODE_COST_PRICE,
						CODE_PRICE,
						CODE_MIN_STOCK,
						CODE_MAX_STOCK
					)
					VALUES (?,?,?,?,?,?,?)
				";

				$stmtInsertCode = $this->conn->prepare($sqlInsertCode);

				foreach ($newItems as $row) {
					$pro_code     = trim($row[0]);
					$cost_price   = trim($row[1]);
					$lowest_price = trim($row[2]);
					$price        = trim($row[3]);
					$min_stock    = trim($row[4]);
					$max_stock    = trim($row[5]);

					if ($pro_code == '') {
						throw new Exception("لايوجد مواصفات جديده لحفظها");
					}

					// منع التكرار
					$checkCode = $this->conn->prepare("
						SELECT COUNT(*)
						FROM PRODUCT_CODES
						WHERE PRO_CODE = ?
					");

					$checkCode->execute([$pro_code]);
					
					if ($checkCode->fetchColumn() > 0) {
						throw new Exception("كود المنتج مكرر : ".$pro_code);
					}

					if(!$stmtInsertCode->execute([$this->pro_id, $pro_code, $lowest_price, $cost_price, $price, $min_stock, $max_stock])){
						throw new Exception("غير قادر على أضافة المواصفة الجديده");
					};
					
				}
			}

			// ==========================
			// إضافة الخيارات
			// ==========================

			$options = json_decode($this->pro_option, true);

			if (is_array($options) && count($options) > 0) {

				$sqlInsertOption = "
					INSERT INTO PRODUCT_CODE_JOIN_OPTION
					(
						PRODUCT_CODE,
						OPTION_VALUE_ID
					)
					VALUES (?,?)
				";

				$stmtInsertOption = $this->conn->prepare($sqlInsertOption);

				foreach ($options as $optionRow) {

					$product_code = trim($optionRow[0]);

					// التأكد أن الكود موجود
					$checkProductCode = $this->conn->prepare("
						SELECT COUNT(*)
						FROM PRODUCT_CODES
						WHERE PRO_CODE = ?
					");

					$checkProductCode->execute([$product_code]);

					if ($checkProductCode->fetchColumn() == 0) {
						throw new Exception("PRODUCT_CODE_NOT_FOUND : ".$product_code);
					}

					// التأكد أن المصفوفة موجودة
					if (!isset($optionRow[1]) || !is_array($optionRow[1])) {
						continue;
					}

					foreach ($optionRow[1] as $opt) {

						$option_id = (int) trim($opt);

						// منع التكرار
						$checkOption = $this->conn->prepare("
							SELECT COUNT(*)
							FROM PRODUCT_CODE_JOIN_OPTION
							WHERE PRODUCT_CODE = ?
							AND OPTION_VALUE_ID = ?
						");

						$checkOption->execute([
							$product_code,
							$option_id
						]);

						if ($checkOption->fetchColumn() == 0) {

							$stmtInsertOption->execute([
								$product_code,
								$option_id
							]);
						}
					}
				}
			}

			// ==========================
			// إنشاء مخزون افتراضي
			// ==========================

			$sqlStores = "
				INSERT INTO STORES_PRODUCT
				(
					STO_ID,
					PRO_CODE,
					STO_QTY
				)

				SELECT
					A.STO_ID,
					A.PRO_CODE,
					0

				FROM
				(
					SELECT
						B.STO_ID,
						A.PRO_CODE
					FROM PRODUCT_CODES A
					JOIN STORES B
				) A

				LEFT JOIN STORES_PRODUCT B
					ON A.STO_ID = B.STO_ID
					AND A.PRO_CODE = B.PRO_CODE

				WHERE B.PRO_CODE IS NULL
			";

			$stmtStores = $this->conn->prepare($sqlStores);

			$stmtStores->execute();

			// ==========================
			// نجاح العملية
			// ==========================

			$this->conn->commit();

			echo json_encode([
				'success',
				'تم الحفظ بنجاح'
			]);

		} catch (Exception $e) {

			$this->conn->rollback();

			echo json_encode([
				 'error',
				 $e->getMessage()
			]);

		} catch (PDOException $e) {

			$this->conn->rollback();

			echo json_encode([
				 'error',
				 $e->getMessage()
			]);
		}
	}
    public function deleteProducts() {
        $sqlQuery = 'DELETE A FROM PRODUCTS A
                                LEFT JOIN (SELECT
                                                A.PRO_ID,
                                                SUM(CASE WHEN C.STO_QTY <> 0 THEN 1 ELSE 0 END) AS CHECK_QTY,
                                                COUNT(E.ORDER_ID) AS CHECK_ORDER
                                            FROM  PRODUCTS A
                                            LEFT JOIN PRODUCT_CODES B
                                                ON A.PRO_ID = B.PRODUCT_ID
                                            LEFT JOIN STORES_PRODUCT C
                                                ON B.PRO_CODE = C.PRO_CODE
                                            LEFT JOIN ORDER_PRODUCT E
                                                ON C.PRO_CODE = E.PRO_CODE
                                            GROUP BY A.PRO_ID) B
                                    ON A.PRO_ID=B.PRO_ID
                                WHERE CHECK_ORDER = 0 AND FIND_IN_SET (A.PRO_ID , ? ) ';
        $this->pro_id = htmlspecialchars(strip_tags(trim($this->pro_id, ' ')));
        try {
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare($sqlQuery);
            $stmt->bindValue(1, $this->pro_id, PDO::PARAM_STR);
            if ($stmt->execute()) {
                $this->conn->commit();
                echo json_encode($stmt->rowCount());
            } else {
                $this->conn->rollback();
                echo json_encode("ERROR_DELETE_PRODUCT");
            }

        } catch (PDOException $e) {
            $this->conn->rollback();
            echo json_encode('error'.$e->getCode().' - '.$e->getMessage());
        }
    }
    public function Productlist () {
    
    	$serch = isset($_GET['query']) ? $_GET['query'] : "";
		$query = "SELECT  pr.PRO_ID,
			  pr.PRO_NAME,
			  CASE
			   WHEN e.OPTION_ID IS NULL
			   THEN '[]'
			   ELSE Json_arrayagg(DISTINCT Json_object('oId', E.OPTION_ID, 'oName',E.OPTION_NAME, 'values', (SELECT Json_arrayagg(DISTINCT Json_object('ID',PO2.ID, 'VAL',PO2.VALUE))
			           FROM  product_option PO2
			            JOIN product_code_join_option CJ2
			            ON CJ2.OPTION_VALUE_ID = PO2.ID
			           WHERE CJ2.PRODUCT_CODE IN (SELECT PC2.PRO_CODE
			                FROM  product_codes PC2
			                WHERE PC2.PRODUCT_ID = PR.PRO_ID)
			            AND PO2.OPTION_ID = d.OPTION_ID)))
			  END  AS optionsId,
			  
			  Json_arrayagg(
			  	Json_object(
			  		'PRO_CODE', pc.PRO_CODE, 
			  		'PURCHASE_PRICE', pc.PURCHASE_PRICE, 
			  		'LOWEST_PRICE', pc.LOWEST_PRICE, 
			  		'SELL_PRICE', pc.SELL_PRICE, 
			  		'MIN_STOCK', pc.MIN_STOCK, 
			  		'MAX_STOCK', pc.MAX_STOCK, 
			  		'OPTIONS', pc.OPT_JSON,
			  		'VID',PC.VID
			  	)
			  ) AS JSON_DATA
			  
			FROM   products pr
			  LEFT JOIN (SELECT  pc.PRODUCT_ID       AS PRO_ID,
			      pc.PRO_CODE        AS PRO_CODE,
			      pc.CODE_COST_PRICE       AS PURCHASE_PRICE,
			      PC.LOWEST_PRICE 		   AS LOWEST_PRICE,
			      pc.CODE_PRICE       	   AS SELL_PRICE,
			      pc.CODE_MIN_STOCK        AS MIN_STOCK,
			      pc.CODE_MAX_STOCK        AS MAX_STOCK,
			      Json_arrayagg(D.ID) AS VID,
			      Group_concat(Concat(e.OPTION_NAME, ' : ', d.VALUE) SEPARATOR ' | ') AS OPT_JSON
			    FROM   product_codes pc
			      LEFT JOIN product_code_join_option c
			      ON pc.PRO_CODE = c.PRODUCT_CODE
			      LEFT JOIN product_option d
			      ON d.ID = c.OPTION_VALUE_ID
			      LEFT JOIN product_option_group e
			      ON e.OPTION_ID = d.OPTION_ID
			    GROUP BY pc.PRO_CODE) AS pc
			  ON pr.PRO_ID = pc.PRO_ID
			  LEFT JOIN product_code_join_option c
			  ON pc.PRO_CODE = c.PRODUCT_CODE
			  LEFT JOIN product_option d
			  ON d.ID = c.OPTION_VALUE_ID
			  LEFT JOIN product_option_group e
			  ON e.OPTION_ID = d.OPTION_ID
			WHERE pr.PRO_NAME LIKE '%".$serch."%'
			GROUP BY pr.PRO_ID,
			  pr.PRO_NAME;";	
		$stmt = $this->conn->prepare($query);
		$stmt->execute();
		$returnData = [];
		//$returnData .="<select id='itemName' class='form-control p-1 sw2'>";
		//$returnData .= "<option></option>";

		while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$returnData[] = [
				"id" =>$record['PRO_ID'], 
				"name" =>$record['PRO_NAME'], 
				'CODES' => json_decode($record['JSON_DATA']),
				'OPTION_GROUP'=>json_decode($record['optionsId'])
			];
		}
		// $returnData.='</select>';
		return $returnData;
	}
	public function ProAttributes(&$productid) {
		$query1 = 'SELECT
	        PC.PRODUCT_ID,
	        PO.OPTION_ID,
	        PG.OPTION_NAME,
	        PO.ID AS vid,
	        PO.VALUE AS val
	    FROM product_codes AS PC
	        LEFT JOIN product_code_join_option PCJ ON PC.PRO_CODE = PCJ.PRODUCT_CODE
	        LEFT JOIN product_option PO ON PCJ.OPTION_VALUE_ID = PO.ID
	        LEFT JOIN product_option_group PG ON PO.OPTION_ID = PG.OPTION_ID
	    WHERE PC.PRODUCT_ID = :pid
	    GROUP BY
	        PC.PRODUCT_ID, PG.OPTION_NAME, PO.ID, PO.VALUE';

		$query2 = 'SELECT
			 PC.PRO_CODE,
			 PC.PRODUCT_ID,
			 PC.CODE_COST_PRICE AS COUST_PRICE,
			 PC.LOWEST_PRICE AS LOWEST_PRICE,
	         PC.CODE_PRICE AS SELL_PRICE,
			 json_arrayagg( PCO.OPTION_VALUE_ID) OPTION_VALUE
			FROM  product_codes PC
			 LEFT JOIN product_code_join_option PCO
			 ON PC.PRO_CODE = PCO.PRODUCT_CODE
			WHERE PC.PRODUCT_ID = :pid
			GROUP BY
			 PC.PRODUCT_ID,
			 PC.PRO_CODE';
		$stmt1 = $this->conn->prepare($query1);
		$stmt1->bindValue(':pid', $productid, PDO::PARAM_INT);
		$stmt1->execute();

		$stmt2 = $this->conn->prepare($query2);
		$stmt2->bindValue(':pid', $productid, PDO::PARAM_INT);
		$stmt2->execute();
		$results = $stmt2->fetchAll(PDO::FETCH_ASSOC);

		$returnData = array();
		$currentOptionId = null; // لتتبع OPTION_ID الحالي
		$countElment = 0;
		while ($record = $stmt1->fetch(PDO::FETCH_ASSOC)) {
			$countElment++;
			$productId = $record['PRODUCT_ID'];
			$optionId = $record['OPTION_ID'];
			$optionName = $record['OPTION_NAME'];
			$valueId = $record['vid'];
			$value = $record['val'];

			// Initialize select HTML if not already set for the product
			if (!isset($returnData[$productId])) {
				$returnData[$productId] = '';
			}

			// Close previous <select> if OPTION_ID has changed
			if ($currentOptionId !== null && $currentOptionId != $optionId) {
				$returnData[$productId] .= '</select>';
			}

			// Create a new select dropdown for each unique OPTION_ID
			if ($currentOptionId != $optionId) {
				$returnData[$productId] .= "<label for='swelSelect$countElment'>$optionName</label><select id='swelSelect$countElment' class='form-control proop' name='options[{$productId}][{$optionId}]' id='select-{$optionId}'>";
				$returnData[$productId] .= "<option>اختر $optionName</option>";
				$currentOptionId = $optionId;
			}

			// Add each option to the select
			$returnData[$productId] .= "<option value='{$valueId}'>{$value}</option>";
		}

		// Close the last opened <select> tag if necessary
		if ($currentOptionId !== null) {
			$returnData[$productId] .= '</select>';
		}

		return array('product_option' => $returnData,
			'product_code' => $results
		);
	}
    public function listProducts() {
        $sqlQuery = " SELECT 	A.PRO_ID,
								A.CAT_ID,
								C.CAT_NAME,
								A.PRO_NAME,
								A.PRO_VISBLE,
								A.PER_BOOKING,
								A.PRO_DESCRIPTION,
								A.PRO_IMG_LINK,
								MAX(B.CODE_PRICE) AS  MAXPRICE,
								MIN(B.LOWEST_PRICE) AS  MINPRICE,
								D.STO_QTY AS STOREQTY,
                                SUM(CASE WHEN D.STO_QTY <> 0 THEN 1 ELSE 0 END) AS CHECK_QTY,
								COUNT(E.ORDER_ID) AS CHECK_ORDER
						FROM " . $this->ProductsTbl . " A
    						LEFT JOIN " . $this->ProductCodesTbl . " B ON B.PRODUCT_ID = A.PRO_ID
    						LEFT JOIN PRODUCT_CATEGORY C ON C.CAT_ID = A.CAT_ID
    						LEFT JOIN (SELECT 
                                 B.PRODUCT_ID,
                                 Sum(A.STO_QTY) as STO_QTY
                                FROM  `stores_product` A
                                 LEFT JOIN stores S ON A.STO_ID = S.STO_ID
                                 LEFT JOIN PRODUCT_CODES B ON A.PRO_CODE = B.PRO_CODE
                                 WHERE S.BRANCH_ID = ".$_SESSION['BRANCH_ID']." AND S.STTY_ID = 2
                                GROUP BY B.PRODUCT_ID) D ON A.PRO_ID = D.PRODUCT_ID
						    LEFT JOIN ORDER_PRODUCT E ON B.PRO_CODE = E.PRO_CODE 
                        GROUP BY A.PRO_ID,
								A.CAT_ID,
								C.CAT_NAME,
								A.PRO_NAME,
							    A.PRO_VISBLE,
								A.PER_BOOKING,
								A.PRO_DESCRIPTION,
                                A.PRO_ID,
								A.CAT_ID,
								C.CAT_NAME,
								A.PRO_NAME,
								A.PRO_VISBLE,
								A.PER_BOOKING,
								A.PRO_DESCRIPTION,
								A.PRO_IMG_LINK,
                                D.STO_QTY ";

        if (!empty($_POST["search"]["value"])) {
            $sqlQuery .= ' having (C.CAT_NAME LIKE     "%'.$_POST["search"]["value"].'%" ';
            $sqlQuery .= ' OR A.PRO_NAME LIKE          "%'.$_POST["search"]["value"].'%" ';
            $sqlQuery .= ' OR MIN(B.CODE_PRICE) LIKE   "'.$_POST["search"]["value"].'%" ';
            $sqlQuery .= ' OR MAX(B.CODE_PRICE) LIKE   "'.$_POST["search"]["value"].'%") ';

        }
        
        $columnsArray = ['A.PRO_NAME','C.CAT_NAME','D.STO_QTY'];
        if (!empty($_POST["order"])) {
            $sqlQuery .= ' ORDER BY '.$columnsArray[$_POST['order']['0']['column']].' '.$_POST['order']['0']['dir'].' ';
        } else {
            $sqlQuery .= ' ORDER BY A.PRO_ID DESC ';
        }

        if (!empty($_POST["length"]) && $_POST["length"] != -1) {
            $sqlQuery .= ' LIMIT ' . $_POST['start'] . ', ' . $_POST['length'] . ' ';
        }
        $stmt = $this->conn->prepare($sqlQuery);
        $stmt->execute();
        $result = $stmt;

        $stmtTotal = $this->conn->prepare("SELECT PRO_ID FROM ".$this->ProductsTbl);
        $stmtTotal->execute();
        $allResult = $stmtTotal;
        $allRecords = $allResult->rowCount();

        $displayRecords = $result->rowCount();
        $records = array();
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            $rows = array();
            if (intval($record['CHECK_QTY']) === 0 && intval($record['CHECK_ORDER']) === 0) {
                $rows[] = '<input id="'.$record['PRO_ID'].'" type="checkbox">';
            } else {
                $rows[] = '#';
            }
            $rows[] = '<img src="'.ltrim(preg_replace("/[^\/]+/","..",dirname($_SERVER['PHP_SELF']).'/'),'/').'img/logo.png" alt="Logo" class="brand-image img-circle elevation-3" style="opacity: .8">';
            if ($record['PRO_VISBLE'] == 0) {
                $rows[] = '<a class="text-secondary">' . $record['PRO_NAME'] . '</a>';
                $rows[] = '<a class="text-secondary">' . $record['CAT_NAME'] . '</a>';
            } else {
                $rows[] = '<a class="text-primary">' . $record['PRO_NAME'] . '</a>';
                $rows[] = '<a class="text-primary">' . $record['CAT_NAME'] . '</a>';
            }
            if ($record['STOREQTY'] == 0) {
                $rows[] = '<a class="text-danger">' . $record['STOREQTY'] . '</a>';
            } else {
                if ($record['PRO_VISBLE'] == 0) {
                    $rows[] = '<a class="text-secondary">' . $record['STOREQTY'] . '</a>';
                } else {
                    $rows[] = '<a class="text-primary">' . $record['STOREQTY'] . '</a>';
                }
            }
            if ($record['PRO_VISBLE'] == 1) {
                $rows[] = '<a class="text-primary">تمكين</a>';
            } else {
                $rows[] = '<a class="text-danger">معطل</a>';
            }
            if ($record['PER_BOOKING'] == 1) {
                $rows[] = '<a class="text-primary">تمكين</a>';
            } else {
                $rows[] = '<a class="text-danger">معطل</a>';
            }
            if ($record['MINPRICE'] == $record['MAXPRICE']) {
                $rows[] = '<a class="text-primary">' . $record['MINPRICE'] . '</a>';
            } else {
                $rows[] = '<a class="text-secondary">' . $record['MINPRICE'] .' - '.$record['MAXPRICE'] . '</a>';
            }
            $rows[] = "<button id='btnNew' type='button' data-id='".$record["PRO_ID"]."' class='btn btn-primary btn-xs Edit'><i class='fas fa-align-center fa-edit'></i></button>";

            $records[] = $rows;
        }

        $output = array(
            "draw" => intval($_POST["draw"]),
            "iTotalRecords" => $displayRecords,
            "iTotalDisplayRecords" => $allRecords,
            "data" => $records
        );
        echo json_encode($output);
    } //end list
    public function getProductCodes() {
        $sqlQuery = 'SELECT PRO_CODE FROM PRODUCT_CODES WHERE PRO_CODE LIKE ? AND PRODUCT_CODE_ID <> ? ';
        $this->pro_code = htmlspecialchars(strip_tags(trim($this->pro_code)));
        $this->product_code_id = htmlspecialchars(strip_tags(trim($this->product_code_id)));
        if (empty($this->product_code_id)) {
            $this->product_code_id = 0;
        }
        $sqlQuery .= '  ';
        $this->pro_code = htmlspecialchars(strip_tags(trim($this->pro_code)));
        $this->product_code_id = htmlspecialchars(strip_tags(trim($this->product_code_id)));
        $stmt = $this->conn->prepare($sqlQuery);
        $stmt->bindValue(1, $this->pro_code, PDO::PARAM_STR);
        $stmt->bindValue(2, $this->product_code_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt;
        $records = array();
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            $records[] = $record['PRO_CODE'];
        }
        echo json_encode($records);
    } //end getProductCodes
    public function viewProductName() {
        $this->pro_name = htmlspecialchars(strip_tags($this->pro_name));
        $sqlQuery = 'SELECT PRO_NAME FROM PRODUCTS WHERE PRO_NAME LIKE ? AND PRO_ID <> ? ';
        $this->pro_id = htmlspecialchars(strip_tags(trim($this->pro_id)));
        if (empty($this->pro_id)) {
            $this->pro_id = 0;
        }
        $this->pro_name = htmlspecialchars(strip_tags(trim($this->pro_name)));
        $stmt = $this->conn->prepare($sqlQuery);
        $stmt->bindValue(1, $this->pro_name, PDO::PARAM_STR);
        $stmt->bindValue(2, $this->pro_id, PDO::PARAM_INT);
        if ($stmt->execute()) {
            echo json_encode($stmt->rowCount());
        }
    }
    public function viewProduct() {
        // VIEW TO  UPDATE
        $sqlQuery = "SELECT  
                         A.PRODUCT_ID,
                         B.PRO_NAME,
                         F.CAT_NAME,
                         B.PRO_VISBLE,
                         B.PER_BOOKING,
                         B.HEIGHT,
                         B.LENGTH,
                         B.WIDTH,
                         B.WEIGHT,
                         B.PRO_DESCRIPTION,
                         B.PRO_IMG_LINK,
                         A.PRODUCT_CODE_ID,
                         A.PRO_CODE,
                         A.CODE_COST_PRICE,
                         A.LOWEST_PRICE,
                         A.CODE_PRICE,
                         A.CODE_MIN_STOCK,
                         A.CODE_MAX_STOCK,
                         Group_concat(Concat(E.OPTION_ID) SEPARATOR ' , ')   AS OPTION_ID,
                         Group_concat(Concat(E.OPTION_NAME, ' : ', D.VALUE) SEPARATOR ', ') AS OPTION_TEXT,
                         sum(G.STO_QTY) AS STO_QTY
                     FROM   PRODUCT_CODES A
                         LEFT JOIN PRODUCTS B ON A.PRODUCT_ID = B.PRO_ID
                         LEFT JOIN PRODUCT_CODE_JOIN_OPTION C ON A.PRO_CODE = C.PRODUCT_CODE
                         LEFT JOIN PRODUCT_OPTION D ON D.ID = C.OPTION_VALUE_ID
                         LEFT JOIN PRODUCT_OPTION_GROUP E ON E.OPTION_ID = D.OPTION_ID
                         LEFT JOIN product_category F ON B.CAT_ID = F.CAT_ID
                         LEFT JOIN stores_product G ON A.PRO_CODE = G.PRO_CODE AND G.STO_ID=2
                     WHERE Find_in_set (A.PRODUCT_ID, ) 
                     GROUP BY A.PRO_CODE,
                         A.CODE_COST_PRICE,
                         A.CODE_MAX_STOCK,
                         A.CODE_MIN_STOCK,
                         A.CODE_PRICE,
                         F.CAT_NAME,
                         B.PRO_NAME,
                         B.PRO_VISBLE,
                         B.PER_BOOKING,
                         B.HEIGHT,
                         B.LENGTH,
                         B.WIDTH,
                         B.WEIGHT,
                         B.PRO_DESCRIPTION,
                         B.PRO_IMG_LINK
                     ORDER BY A.PRO_CODE ";
        $this->pro_id = htmlspecialchars(strip_tags($this->pro_id));
        $this->sto_id = htmlspecialchars(strip_tags($this->sto_id));
        /*$DataReturn = array();
        try {
            $stmt = $this->conn->prepare($sqlQuery);
            $stmt->bindValue(1, $this->pro_id, PDO::PARAM_INT);
            if ($stmt->execute()) {
                while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if(!isset($DataReturn[$record['PRO_ID']])){
                        $DataReturn[$record['PRO_ID']] = array(
                            'PRO_ID'          => $record['PRO_ID'],
                            'PRO_NAME'        => $record['PRO_NAME'],
                            'CAT_ID'          => $record['CAT_ID'],
                            'PRO_VISBLE'      => $record['PRO_VISBLE'],
                            'PER_BOOKING'     => $record['PER_BOOKING'],
                            'PRO_DESCRIPTION' => $record['PRO_DESCRIPTION'],
                            'LENGTH'          => $record['LENGTH'],
                            'WIDTH'           => $record['WIDTH'],
                            'HEIGHT'          => $record['HEIGHT'],
                            'WEIGHT'          => $record['WEIGHT'],
                            'PRO_IMG_LINK'    => $record['PRO_IMG_LINK'],
                            'CODES'           => array()
                        );
                    }
                    $DataReturn[$record['PRO_ID']]['CODES'][$row['PRODUCT_CODE_ID']]  = array(
                        'PRODUCT_CODE_ID' => $record['PRODUCT_CODE_ID'],
                        'PRODUCT_ID'      => $record['PRODUCT_ID'],
                        'PRO_CODE'        => $record['PRO_CODE'],
                        'CODE_COST_PRICE' => $record['CODE_COST_PRICE'],
                        'CODE_PRICE'      => $record['CODE_PRICE'],
                        'CODE_MIN_STOCK'  => $record['CODE_MIN_STOCK'],
                        'CODE_MAX_STOCK'  => $record['CODE_MAX_STOCK'],
                        'STOCK_AVAILABLE' => $record['STO_QTY'],
                        'OPTION'          => $record['OPTION'],
                        'OPTION_VALUE_ID' => $record['OPTION_VALUE_ID']
                    );
                    
                }
            }
        } catch (PDOException $e) {
            echo json_encode('error'.$e->getCode() . ' - ' . $e->getMessage());
        }*/
        
        $columans = '';
        $ids = '';
        $stqty1="";
        $stqty2="";
        $qjoin="";
        $qwhere="";
        if(!empty($this->sto_id)){
            $stqty1 = " ,STO_QTY ";
            $stqty2 = " ,E.STO_ID ,E.STO_QTY ";
            $qjoin  = " LEFT JOIN STORES_PRODUCT E ON A.PRO_CODE = E.PRO_CODE ";
            $qwhere = " AND STO_ID = ? ";
        } else {
            $stqty1 = " ,0 AS STO_QTY ";
        }
        $sqlQuery0  = "SELECT * FROM PRODUCTS WHERE PRO_ID = ? ";
        $sqlQuery1  = "SELECT DISTINCT D.OPTION_NAME
                            FROM  PRODUCT_CODES A
                            LEFT JOIN PRODUCT_CODE_JOIN_OPTION B ON A.PRO_CODE = B.PRODUCT_CODE
                            LEFT JOIN PRODUCT_OPTION C ON B.OPTION_VALUE_ID = ID
                            LEFT JOIN PRODUCT_OPTION_GROUP D ON C.OPTION_ID = D.OPTION_ID 
                            WHERE D.OPTION_NAME IS NOT NULL AND A.PRODUCT_ID = ? ";

        $sqlQuery3 = "SELECT D.OPTION_NAME, C.OPTION_ID
                       FROM PRODUCT_CODES A
                       LEFT JOIN PRODUCT_CODE_JOIN_OPTION B ON A.PRO_CODE = B.PRODUCT_CODE
                       LEFT JOIN PRODUCT_OPTION C ON B.OPTION_VALUE_ID = C.ID
                       LEFT JOIN PRODUCT_OPTION_GROUP D  ON C.OPTION_ID = D.OPTION_ID
                       WHERE C.OPTION_ID IS NOT NULL AND FIND_IN_SET (A.PRODUCT_ID , ?)  GROUP BY C.OPTION_ID";
        $DataReturn = array();
        
        try {
            $stmt0 = $this->conn->prepare($sqlQuery0);
            $stmt0->bindValue(1, $this->pro_id, PDO::PARAM_INT);
            if ($stmt0->execute()) {
                while ($record = $stmt0->fetch(PDO::FETCH_ASSOC)) {
                    $row = array();
                    $row['PRO_ID'] = $record['PRO_ID'];
                    $row['PRO_NAME'] = $record['PRO_NAME'];
                    $row['CAT_ID'] = $record['CAT_ID'];
                    $row['PRO_VISBLE'] = $record['PRO_VISBLE'];
                    $row['PER_BOOKING'] = $record['PER_BOOKING'];
                    $row['PRO_DESCRIPTION'] = $record['PRO_DESCRIPTION'];
                    $row['LENGTH'] = $record['LENGTH'];
                    $row['WIDTH'] = $record['WIDTH'];
                    $row['HEIGHT'] = $record['HEIGHT'];
                    $row['WEIGHT'] = $record['WEIGHT'];
                    $row['PRO_IMG_LINK'] = $record['PRO_IMG_LINK'];
                    $DataReturn['PRODUCT'][$record['PRO_ID']] = $row;
                }
            } else {
                //error
            }
            $stmt1 = $this->conn->prepare($sqlQuery1);
            $stmt1->bindValue(1, $this->pro_id, PDO::PARAM_INT);
            if ($stmt1->execute()) {
                while ($record = $stmt1->fetch(PDO::FETCH_ASSOC)) {
                    if ($columans === '') {
                        $columans .= " CONCAT(Max(CASE WHEN  `OPTION_NAME` = '".$record['OPTION_NAME']."' THEN CONCAT('".$record['OPTION_NAME']."',' : ',VALUE) END)  ";
                    } else {
                        $columans .= ",' | ',Max(CASE WHEN `OPTION_NAME` = '".$record['OPTION_NAME']."' THEN CONCAT('".$record['OPTION_NAME']."',' : ',VALUE) END) ";
                    }
                    if ($ids === '') {
                        $ids .= " CONCAT(Max(CASE WHEN `OPTION_NAME` = '".$record['OPTION_NAME']."' THEN CONCAT(OPTION_ID,'-',ID,'-',VALUE) END)  ";
                    } else {
                        $ids .= ",',',Max(CASE WHEN `OPTION_NAME` = '".$record['OPTION_NAME']."' THEN CONCAT(OPTION_ID,'-',ID,'-',VALUE) END) ";
                    }
                }
                if ($columans !== '') {
                    $columans .= ") AS `OPTION` ,";
                }
                if ($ids !== '') {
                    $ids .= ") AS `OPTION_VALUE_ID` ,";
                }
				
            }
            $sqlQuery2 = "SELECT PRODUCT_CODE_ID,
                              PRODUCT_ID,
                              PRO_CODE,
                              ". $columans . $ids ."
                              CODE_COST_PRICE,
                              LOWEST_PRICE,
                              CODE_PRICE,
                              CODE_MIN_STOCK,
                              CODE_MAX_STOCK
                              ".$stqty1."
                       FROM  (SELECT A.*,
                                     D.OPTION_ID,
                                     D.OPTION_NAME,
                                     C.ID,
                                     C.VALUE
                                     ".$stqty2."
                              FROM  PRODUCT_CODES A
                              LEFT JOIN PRODUCT_CODE_JOIN_OPTION B ON A.PRO_CODE = B.PRODUCT_CODE
                              LEFT JOIN PRODUCT_OPTION C ON B.OPTION_VALUE_ID = C.ID
                              LEFT JOIN PRODUCT_OPTION_GROUP D ON C.OPTION_ID = D.OPTION_ID 
                              ".$qjoin." ) AS W 
							  
							  WHERE PRODUCT_ID = ? ".$qwhere."
							  GROUP BY PRODUCT_CODE_ID, PRODUCT_ID, PRO_CODE, CODE_COST_PRICE, CODE_PRICE, CODE_MIN_STOCK, CODE_MAX_STOCK";
            if ($columans !== '') {
                $sqlQuery2 .= " ORDER BY OPTION";
            }
            $sqlQuery2 .= ";";
            
            //echo $sqlQuery2;
            $stmt2 = $this->conn->prepare($sqlQuery2);
			$stmt2->bindValue(1, $this->pro_id, PDO::PARAM_INT);
            if(!empty($this->sto_id)){
                $stmt2->bindValue(2, $this->sto_id, PDO::PARAM_INT);
            }
            
            if ($stmt2->execute()) {
				$op=array();
                while ($record = $stmt2->fetch(PDO::FETCH_ASSOC)) {
                    $row = array();
                    $row['PRODUCT_CODE_ID'] = $record['PRODUCT_CODE_ID'];
                    $row['PRODUCT_ID']      = $record['PRODUCT_ID'];
                    $row['PRO_CODE']        = $record['PRO_CODE'];
                    if(isset($record['OPTION'])){
                        $row['OPTION']          = $record['OPTION'];
                    } else {
                        $row['OPTION']          = null;
                    }
                    $row['CODE_COST_PRICE'] = $record['CODE_COST_PRICE'];
                    $row['LOWEST_PRICE']    = $record['LOWEST_PRICE'];
 					$row['CODE_PRICE']      = $record['CODE_PRICE'];
                    $row['CODE_MIN_STOCK']  = $record['CODE_MIN_STOCK'];
                    $row['CODE_MAX_STOCK']  = $record['CODE_MAX_STOCK'];
                    $row['OPTION_VALUE_ID'] = "";
                    $row['STOCK_AVAILABLE'] = $record['STO_QTY'];
                 
    				if(isset($record['OPTION_VALUE_ID'])){
    					$opv = explode(",",$record['OPTION_VALUE_ID']);
    				}
					if(!array_key_exists($record['PRODUCT_ID'],$op)){
						$op[$record['PRODUCT_ID']]=array();
					}
                    if(isset($opv) && count($opv)>0){
						for($i=0 ; $i<count($opv); $i++){
							$var = explode("-",$opv[$i]);
							if($row['OPTION_VALUE_ID']==="" && $var[1]!==""){
								$row['OPTION_VALUE_ID'] .= $var[1];
							} else {
								$row['OPTION_VALUE_ID'] .= ",".$var[1];
							}
							if(!array_key_exists($var[0],$op[$record['PRODUCT_ID']])){
								$op[$record['PRODUCT_ID']][$var[0]]=array();
							}
							if(!array_key_exists($var[1],$op[$record['PRODUCT_ID']][$var[0]])){
								$op[$record['PRODUCT_ID']][$var[0]][$var[1]]=array($var[1],$var[2]);
							}
						}
                    }
						//$row['OPTION_VALUE_ID'] = str_replace(" ",",",$row['OPTION_VALUE_ID']);
						$DataReturn['CODES'][$record['PRODUCT_ID']][] = $row; 
						
                }
                if(isset($opv)){
                $DataReturn['OPTION_VALUE'] = $op;
                }
            }
            //echo json_encode( $var);
            $stmt3 = $this->conn->prepare($sqlQuery3);
            $stmt3->bindValue(1, $this->pro_id, PDO::PARAM_STR);
            if ($stmt3->execute()) {
                while ($record = $stmt3->fetch(PDO::FETCH_ASSOC)) {
                    $row = array();
                    $row['OPTION_ID'] = $record['OPTION_ID'];
                    $row['OPTION_NAME'] = $record['OPTION_NAME'];
                    if(!empty($record['OPTION_ID']) && !empty($record['OPTION_NAME'])){
                        $DataReturn['OPTION'][] = $row;
                    }
                }
               echo json_encode($DataReturn);
            }

        } catch (PDOException $e) {
            echo json_encode('error'.$e->getCode() . ' - ' . $e->getMessage());
        }
    }
    public function ProductsData() {
        $sqlQuery  = 'SELECT PRO_ID AS XPRO_ID, PER_BOOKING   FROM PRODUCTS WHERE PRO_VISBLE <> 0 ORDER BY PRO_ID  ';
		
		if (!empty($_POST["start"]) && $_POST["start"] != -1) {
            $sqlQuery .= ' LIMIT ' . $_POST['start'] . ', 1000000 ';
        }
        
		$sqlQuery0 = 'SELECT A.PRO_ID,
								A.CAT_ID,
								C.CAT_NAME,
								C.CAT_PARENT,
								A.PRO_NAME,
								A.PRO_VISBLE,
								A.PER_BOOKING,
								A.PRO_DESCRIPTION,
								A.PRO_IMG_LINK,
								MAX(B.CODE_PRICE) AS  MAXPRICE,
								MIN(B.LOWEST_PRICE) AS  MINPRICE,
								SUM(D.STO_QTY) AS STOREQTY,
                                SUM(CASE WHEN D.STO_QTY <> 0 THEN 1 ELSE 0 END) AS CHECK_QTY,
								COUNT(E.ORDER_ID) AS CHECK_ORDER
						FROM PRODUCTS A
						LEFT JOIN PRODUCT_CODES B ON B.PRODUCT_ID = A.PRO_ID
						LEFT JOIN PRODUCT_CATEGORY C ON C.CAT_ID = A.CAT_ID
						LEFT JOIN STORES_PRODUCT D ON B.PRO_CODE = D.PRO_CODE
						LEFT JOIN ORDER_PRODUCT E ON B.PRO_CODE = E.PRO_CODE
						LEFT JOIN ('.$sqlQuery.') F ON A.PRO_ID = F.XPRO_ID 
						WHERE A.PRO_VISBLE <> 0 AND F.XPRO_ID IS NOT NULL
                        GROUP BY A.PRO_ID,
								A.CAT_ID,
								C.CAT_NAME,
								C.CAT_PARENT,
								A.PRO_NAME,
							    A.PRO_VISBLE,
								A.PER_BOOKING,
								A.PRO_DESCRIPTION,
                                A.PRO_ID,
								A.CAT_ID,
								C.CAT_NAME,
								A.PRO_NAME,
								A.PRO_VISBLE,
								A.PER_BOOKING,
								A.PRO_DESCRIPTION,
								A.PRO_IMG_LINK  ';
		
        $sqlQuery1 = 'SELECT A.*,B.PER_BOOKING  FROM PRODUCT_CODES A LEFT JOIN ('.$sqlQuery.') AS B ON A.PRODUCT_ID=B.XPRO_ID  WHERE XPRO_ID IS NOT NULL ';
        $sqlQuery2 = "SELECT  F.PRODUCT_ID,
                              F.PRO_CODE,
                              B.ID  AS OPTION_VAL_ID,
                              B.VALUE,
                              C.OPTION_ID AS GROUP_ID,
                              C.OPTION_NAME AS GROUP_NAME,
                              D.PER_BOOKING,
                              CONCAT(C.OPTION_NAME,' : ',B.VALUE) as OPTION_TEXT
                        FROM  PRODUCT_CODES F 
                              LEFT JOIN PRODUCT_CODE_JOIN_OPTION A ON F.PRO_CODE=A.PRODUCT_CODE
                              LEFT JOIN PRODUCT_OPTION B ON B.ID = A.OPTION_VALUE_ID
                              LEFT JOIN PRODUCT_OPTION_GROUP C ON C.OPTION_ID = B.OPTION_ID
						      LEFT JOIN (".$sqlQuery.") AS D ON F.PRODUCT_ID=D.XPRO_ID
						WHERE  D.XPRO_ID IS NOT NULL 
                        ORDER BY PRODUCT_CODE,OPTION_VALUE_ID ";
		$orderidx ="";
		if(isset($_GET['orderid']) && $_GET['orderid'] != 'undefined' && $_GET['orderid'] !=''){
		    $orderidx=" AND A.ORDER_ID <> ".$_GET['orderid'];
		}
		$sqlQuery3 = " SELECT A.STO_ID, 
							 C.PRODUCT_ID, 
							 A.PRO_CODE, 
							 A.STO_QTY,
                             ( A.STO_QTY - CASE WHEN B.USER_QTY IS NOT NULL THEN B.USER_QTY ELSE 0 END ) AS USER_STORE,
                             ( A.STO_QTY - CASE WHEN B.ADMIN_QTY IS NOT NULL THEN B.ADMIN_QTY ELSE 0 END ) AS ADMIN_STORE
                        FROM  STORES_PRODUCT A
                            LEFT JOIN (SELECT B.STO_ID, D.PRODUCT_ID, A.PRO_CODE, A.QTY,
                                              Sum(CASE WHEN C.USER_STORE = 1 ".$orderidx." THEN A.QTY ELSE 0 END) AS USER_QTY,
                                              Sum(CASE WHEN C.ADMIN_STORE = 1 ".$orderidx." THEN A.QTY ELSE 0 END) AS ADMIN_QTY
                                       FROM   ORDER_PRODUCT A
                                       LEFT JOIN ORDERS B ON A.ORDER_ID = B.ORDER_ID
                                       LEFT JOIN ORDER_EXISTS C ON B.EXISTS_ID = C.EXISTS_ID
                                       LEFT JOIN PRODUCT_CODES D ON A.PRO_CODE = D.PRO_CODE
                                       GROUP BY B.STO_ID, D.PRODUCT_ID, A.PRO_CODE) B 
                                ON A.PRO_CODE = B.PRO_CODE 
                                   AND A.STO_ID = B.STO_ID
                            LEFT JOIN PRODUCT_CODES C
                                ON A.PRO_CODE = C.PRO_CODE
                            LEFT JOIN STORES D ON A.STO_ID=D.STO_ID
                            LEFT JOIN (".$sqlQuery.") E ON C.PRODUCT_ID=E.XPRO_ID
                        WHERE D.STTY_ID=2 AND E.XPRO_ID IS NOT NULL
                        ORDER BY E.XPRO_ID,A.PRO_CODE , A.STO_ID ";		
        $DataReturn1 = array();
        try {
            $stmt = $this->conn->prepare('SELECT COUNT(PRO_ID) AS COUNTPRO FROM PRODUCTS WHERE PRO_VISBLE <> 0');
            $stmt->execute();
            while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $DataReturn1['COUNTPRO']=$record['COUNTPRO'];
            }
            $stmt0 = $this->conn->prepare($sqlQuery0);
            $stmt0->execute();
            while ($record = $stmt0->fetch(PDO::FETCH_ASSOC)) {
                $row = array();
                $row['PRO_ID']          = $record['PRO_ID'];
                $row['PRO_NAME']        = $record['PRO_NAME'];
                $row['CAT_ID']          = $record['CAT_ID'];
                $row['CAT_NAME']        = $record['CAT_NAME'];
                $row['PRO_VISBLE']      = $record['PRO_VISBLE'];
                $row['PER_BOOKING']     = $record['PER_BOOKING'];
                $row['PRO_DESCRIPTION'] = $record['PRO_DESCRIPTION'];
                $row['MINPRICE']        = $record['MINPRICE'];
                $row['MAXPRICE']        = $record['MAXPRICE'];
                //$row['LENGTH']          = $record['LENGTH'];
                //$row['WIDTH']           = $record['WIDTH'];
                //$row['HEIGHT']          = $record['HEIGHT'];
                //$row['WEIGHT']          = $record['WEIGHT'];
                $row['PRO_IMG_LINK']    = $record['PRO_IMG_LINK'];
                $row['OPTION_GROUP']    = array();
                
                $DataReturn1['PRODUCT'][$record['PRO_ID']] = $row;
            }
            $stmt1 = $this->conn->prepare($sqlQuery1);
            $stmt1->execute();
            while ($record = $stmt1->fetch(PDO::FETCH_ASSOC)) {
                $row = array();
                $row['PRODUCT_ID']      = $record['PRODUCT_ID'];
                $row['PRODUCT_CODE_ID'] = $record['PRODUCT_CODE_ID'];
                $row['PRO_CODE']        = $record['PRO_CODE'];
                $row['CODE_COST_PRICE'] = $record['CODE_COST_PRICE'];
                $row['LOWEST_PRICE'] 	= $record['LOWEST_PRICE'];
                $row['CODE_PRICE']      = $record['CODE_PRICE'];
                $row['CODE_MIN_STOCK']  = $record['CODE_MIN_STOCK'];
                $row['CODE_MAX_STOCK']  = $record['CODE_MAX_STOCK'];
				$row['OPTION_TEXT']     = array();
				$row['OPTION_VAL_ID']   = array();
			    $row['BOOKING']  = $record['PER_BOOKING'];
			    
			    $DataReturn1['PRODUCT'][$record['PRODUCT_ID']]['CODES'][$record['PRO_CODE']] = $row; 
			}
			$stmt2 = $this->conn->prepare($sqlQuery2);
            $stmt2->execute();
            while ($record = $stmt2->fetch(PDO::FETCH_ASSOC)) {
			    if($record['OPTION_VAL_ID'] != null ){
			        $DataReturn1['PRODUCT'][$record['PRODUCT_ID']]['CODES'][$record['PRO_CODE']]['OPTION_VAL_ID'][] = $record['OPTION_VAL_ID'];
				}
			    if($record['OPTION_TEXT'] != null){
                    $DataReturn1['PRODUCT'][$record['PRODUCT_ID']]['CODES'][$record['PRO_CODE']]['OPTION_TEXT'][(int) $record['GROUP_ID']]   = $record['OPTION_TEXT'];
			    }
			    if($record['GROUP_ID'] != null){
					if(!array_key_exists($record['GROUP_ID'],$DataReturn1['PRODUCT'][$record['PRODUCT_ID']]['OPTION_GROUP'])){
						$DataReturn1['PRODUCT'][$record['PRODUCT_ID']]['OPTION_GROUP'][$record['GROUP_ID']]  = array('GROUP_ID' => $record['GROUP_ID'],'GROUP_NAME'=>$record['GROUP_NAME'] , 'OPTION'=>array());
					}
					if(!array_key_exists($record['OPTION_VAL_ID'],$DataReturn1['PRODUCT'][$record['PRODUCT_ID']]['OPTION_GROUP'][$record['GROUP_ID']]['OPTION'])){
                        $DataReturn1['PRODUCT'][$record['PRODUCT_ID']]['OPTION_GROUP'][$record['GROUP_ID']]['OPTION'][$record['OPTION_VAL_ID']]=array('VAL_ID'=>$record['OPTION_VAL_ID'],'VAL_NAME'=>$record['VALUE']);
					}
				}
            }
            $stmt3 = $this->conn->prepare($sqlQuery3);
            $stmt3->execute();
            while ($record = $stmt3->fetch(PDO::FETCH_ASSOC)) {
                if(isset($_GET["sttype"]) && $_GET["sttype"]==0){
					// خاص بالمخزون بعد التغليف
                } else if(isset($_GET["sttype"]) && $_GET["sttype"]==1){
                    $stoval = $record['ADMIN_STORE'];
				} else if(isset($_GET["sttype"]) &&  ($_GET["sttype"]== 2 || $_GET["sttype"]== 4  || $_GET["sttype"]== 5  || $_GET["sttype"]== 6)){
                    $stoval = $record['USER_STORE'];
                } else {
					$stoval = $record['STO_QTY'];
				}
			    $DataReturn1['PRODUCT'][$record['PRODUCT_ID']]['CODES'][$record['PRO_CODE']]['STORSQTY'][$record['STO_ID']]=$stoval;
            }
            http_response_code(200);
			echo json_encode($DataReturn1);
        } catch (PDOException $e) {
            echo json_encode('error'.$e->getCode() . ' - ' . $e->getMessage());
        }
    }
    public function chDelOptionRow() {
        $sqlQuery = "SELECT sum(PRO_CODE) AS PRO_CODE
                     FROM  (
                         SELECT count(A.PRO_CODE) AS PRO_CODE
                             FROM  order_product A
                             WHERE A.PRO_CODE = :code1
                             UNION
                             SELECT SUM(B.STO_QTY) AS PRO_CODE
                             FROM  stores_product B
                             WHERE B.PRO_CODE = :code2
                     ) AS A";
        try {
            $this->pro_code = htmlspecialchars(strip_tags($this->pro_code));
            $stmt = $this->conn->prepare($sqlQuery);
            $stmt->bindValue(':code1', $this->pro_code, PDO::PARAM_STR);
            $stmt->bindValue(':code2', $this->pro_code, PDO::PARAM_STR);
            
            $stmt->execute();
            $recode = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($recode['PRO_CODE']==0){
                return array('s','نجح','يمكنك حذف هذا الكود <hr> الكود لايوجد عليه حركات مخزنية او حركات بيع');
            }else{
                return array('w','نأسف','لايمكن حذف الكود <hr> الكود مسجل له حركات مخزنية او حركات بيع');
            }
        } catch (PDOException $e) {
            return array('e','حدث خطاء غير متوقع',$e->getCode() . ' - ' . $e->getMessage());
        }
    }
    public function DelOptionRow() {
        $sqlQuery = "DELETE FROM product_codes WHERE PRODUCT_CODE_ID =:pcid AND PRO_CODE = :pc";
        try {
            $this->pro_code = htmlspecialchars(strip_tags($this->pro_code));
            $stmt = $this->conn->prepare($sqlQuery);
            $stmt->bindValue(':pcid', $this->product_code_id, PDO::PARAM_INT);
            $stmt->bindValue(':pc', $this->pro_code, PDO::PARAM_STR);
            
            $stmt->execute();
            
            if($stmt->rowCount()>0){
                return array('s','نجح','تم حذف الكود بنجاح');
            }else{
                return array('w','نأسف','لايمكن حذف الكود .. قد يكون الكود مسجل له حركات مخزنية او حركات بيع');
            }
            
        } catch (PDOException $e) {
            return array('e','حدث خطاء غير متوقع',$e->getCode() . ' - ' . $e->getMessage());
        }
    }
} //end class
?>