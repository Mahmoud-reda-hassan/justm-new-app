<?php

ob_start();  // يبدأ التخزين المؤقت للمخرجات
// إرسال PDF للمتصفح

//header('Content-Disposition: attachment; filename="f.pdf"');
// Cell($width, $height, $text, $border, $ln, $align, $fill, $link)`: هذه هي توقيع الدالة الأكثر استخدامًا. تحدد العرض والارتفاع للخلية، والنص الذي يتم عرضه في الخلية، وما إذا كانت تحتوي على حدود، وما إذا كانت تنتقل إلى السطر التالي بعد الانتهاء من الخلية.
// Cell(float $w, float $h = 0, string $txt = '', mixed $border = 0, int $ln = 0, string $align = '', bool $fill = false, string $link = '')
// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=0, $ln=1, $x='', $y='', $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0)
// write1DBarcode($code, $type, $x, $y, $w, $h, $xres, $style, $align)
// write2DBarcode('QR Code Data', 'QRCODE,H', $x, $y, $w, $h);
// http://test.just-m.net/invoices/salesOrderInvoic.php

session_start(); 


require_once($_SERVER["DOCUMENT_ROOT"].'/config.php');
require_once($_SERVER["DOCUMENT_ROOT"].'/inc/Databases/dbconn.php');
require_once($_SERVER["DOCUMENT_ROOT"].'/inc/tcpdf/tcpdf.php');
$Database = new dbconn();

$db       = $Database->getConnection(DB_HOST,DB_PORT,DB_USER,DB_PASSWORD,DB_NAME,DB_CHARSET);
$data     = json_decode(file_get_contents('php://input'),true);

$invtype  = $data['pdf'];
$orders   = array_keys($data['odata']);
$odata    = $data['odata'];

if(isset($_SESSION['USER_ID']) && !empty($_SESSION['USER_ID']) && isset($invtype)){
    if($invtype === "INVOICES"){
        $pdf = new TCPDF('L', PDF_UNIT, 'A5', true, 'UTF-8', false);
        
    // ---------------------------------------------------------
        require_once $_SERVER["DOCUMENT_ROOT"].'/inc/Classes/Invoices_tbl.php';
        $inv           = new Invoices_tbl($db);
        $inv->dataArr  = array(
            "oidPrint" => implode(",",$orders),
            "osubid"   => $odata
        );
        
        $data = $inv->in_Invoices();
		
        if(is_array($data[3]) && $data[0] == 's'){
            $dataRQ = $data[3];
            // set document information
            $pdf->setCreator(PDF_CREATOR);
            $pdf->setAuthor($_SESSION['FNAME'] .' '. $_SESSION['LNAME']);
            $pdf->setTitle('PDF_HEADER_TITLE');
            $pdf->setSubject('TCPDF Tutorial');
            //$pdf->setKeywords('just-m, PDF, example, test, guide');
            // set default header data
             $imgpath ='logoPDF.jpg';
            
            $pdf->setHeaderData($imgpath, 35, '', '', array(0,64,255), array(0,64,128));
            //$pdf->setFooterData(array(0,64,0), array(0,64,128));
            // set header and footer fonts
            $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
            $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
            // set default monospaced font
            $pdf->setDefaultMonospacedFont(PDF_FONT_MONOSPACED);
            // set margins
            $pdf->setMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
            $pdf->setHeaderMargin(PDF_MARGIN_HEADER);
            $pdf->setFooterMargin(PDF_MARGIN_FOOTER);
            
            // set auto page breaks
            $pdf->setAutoPageBreak(true, 9);
            
            // set image scale factor
            $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
            
            // ضبط ترميز اللغة العربية
            $pdf->setLanguageArray(array('a_meta_charset' => 'UTF-8'));
            // ضبط ترميز الخطوط للغة العربية
            $pdf->SetFont('aealarabiya', 'B', 12, '', true);
            function addPageSetting($xyz){
                //  set page direction 
                $xyz->setRTL(true);
                // add a page
                $xyz->AddPage();
                // set cell padding
                $xyz->setCellPaddings(1, 0, 1, 0);
                // set cell margins
                $xyz->setCellMargins(0, 0, 0, 0);
                // set color for background
                $xyz->setFillColor(220, 255, 220);
            }
            if(isset($dataRQ['UNPRINT']) && count($dataRQ['UNPRINT'])>0){
                addPageSetting($pdf);
                $title = 'قائمة أوامر البيع التى لاتستوفي شروط الطباعة والتجهيز';
                $lblorderid       = "أمر بيع";
                $lblservece    = "طلبات خدمة العملاء";
                $lblupdate     = "تغيرات تمت فى الخلفية";
                $lblPrint      = "جاهز للطباعة";
                //$lblsqty        = "المخزون";
                // إنشاء رأس الجدول
                $pdf->SetFont('aealarabiya', 'B', 12, '', true);
                $pdf->setCellMargins(0, 0, 0, 0);
                $pdf->MultiCell(180, 10, $title      , 0, 'C', 0, 1, '', '15', true, 0, false, true, 10, 'M');
                $pdf->MultiCell(60, 10, $lblorderid   , 1, 'C', 1, 0, '', '', true, 0, false, true, 10, 'M');
                $pdf->MultiCell(60, 10, $lblservece   , 1, 'C', 1, 0, '', '', true, 0, false, true, 10, 'M');
                $pdf->MultiCell(60, 10, $lblupdate    , 1, 'C', 1, 1, '', '', true, 0, false, true, 10, 'M');
                //$pdf->MultiCell(45, 10, $lblPrint    , 1, 'C', 1, 1, '', '', true, 0, false, true, 10, 'M');
                
                //$pdf->MultiCell(15, 10, $lbldiscount, 1, 'C', 1, 0, '', '', true, 0, false, true, 10, 'M');
                
                foreach($dataRQ['UNPRINT'] as $rej) {
                    //echo "<pre>";
                    //print_r($rej);
                    //echo "<pre>";
                    $lupCh  = 'مرفوض';
                    $servCh = 'مرفوض';
                    $printCh = 'مرفوض';
                    if($odata[$rej['order_id']]['olup'] == $rej['last_update']){
                        $lupCh = 'مقبول';
                    }
                    if($rej['MSGCS'] == 0){
                        $servCh ='مقبول';
                    }
                    
                    $pdf->MultiCell(60 , 7, $rej['sub_id'] , 1, 'C', 0, 0, '', '', true, 0, false, true, 7, 'M');
                    $pdf->MultiCell(60 , 7, $servCh, 1, 'C', 0, 0, '', '', true, 0, false, true, 7, 'M');
                    $pdf->MultiCell(60 , 7, $lupCh, 1, 'C', 0, 1, '', '', true, 0, false, true, 7, 'M');
                    //$pdf->MultiCell(45 , 7, $lupCh, 1, 'C', 0, 1, '', '', true, 0, false, true, 7, 'M');
                    
                    
                }
            }
            if(count($dataRQ['PRO'])>0){
                addPageSetting($pdf);
                $title = 'الكميات المطلوبة من كل صنف لتجهيز الاوامر المرفقة بنفس ملف الطباعة';
                $lblcode       = "كود الصنف";
                $lblproduct    = "المنتج";
                $lblqty      = "الكمية";
                //$lblsqty        = "المخزون";
                // إنشاء رأس الجدول
                $pdf->SetFont('aealarabiya', 'B', 12, '', true);
                $pdf->setCellMargins(0, 0, 0, 0);
                $pdf->MultiCell(180, 10, $title      , 0, 'C', 0, 1, '', '15', true, 0, false, true, 10, 'M');
     
                $pdf->MultiCell(50, 10, $lblcode      , 1, 'C', 1, 0, '', '', true, 0, false, true, 10, 'M');
                $pdf->MultiCell(100, 10, $lblproduct   , 1, 'C', 1, 0, '', '', true, 0, false, true, 10, 'M');
                $pdf->MultiCell(30, 10, $lblqty       , 1, 'C', 1, 1, '', '', true, 0, false, true, 10, 'M');
                //$pdf->MultiCell(15, 10, $lbldiscount, 1, 'C', 1, 0, '', '', true, 0, false, true, 10, 'M');
                foreach($dataRQ['PRO'] as $pro) {
                    //print_r($pro);
                    $pdf->MultiCell(50 , 7, $pro['PRO_CODE'] , 1, 'C', 0, 0, '', '', true, 0, false, true, 7, 'M');
                    $pdf->MultiCell(100, 7, $pro['PRO_NAME']   , 1, 'R', 0, 0, '', '', true, 0, false, true, 7, 'M');
                    $pdf->MultiCell(30 , 7, number_format($pro['qty'],2), 1, 'C', 0, 1, '', '', true, 0, false, true, 7, 'M');
                    //$pdf->MultiCell(20, 7, number_format($qty,2)     , 1, 'C', 0, 0, '', '', true, 0, false, true, 7, 'M');
                    
                }
            }
            if(count($dataRQ['PRINT'])>0){
            	$barcodeStyle = [
				    'position' => '',
				    'align' => 'L',
				    'stretch' => false,
				    'fitwidth' => true,
				    'border' => false,
				    'padding' => 2,
				    'fgcolor' => [0,0,0],
				    'bgcolor' => false,
				    'text' => true,
				    'font' => 'helvetica',
				    'fontsize' => 10
				];

				$qrStyle = [
				    'border' => 0,
				    'padding' => 2,
				    'fgcolor' => [0, 0, 0],
				    'bgcolor' => false
				];
                foreach($dataRQ['PRINT'] as $orderVal){
                    addPageSetting($pdf);
                    $od= $orderVal['orderData'];
                    $op= $orderVal['orderPro'];
                    //order data
                    $lbl_oBy       = "المسوق";
                    $lbl_oByPh     = "هاتف المسوق";
                    $lbl_Cli       = "العميل";
                    $lbl_CliPh     = "هاتف العميل";
                    $lbl_CliAdd    = "عنوان العميل";
                    $lblproduct    = "المنتج";
                    $lblprice      = "سعر الوحده";
                    $lblqty        = "الكميه";
                    $lbldiscount   = "الخصم";
                    $lbltotal      = "الاجمالى";
                    $lblinval      = "قيمة الفاتورة";
                    $lblship       = "قيمة الشحن";
                    $lblTotal      = "إجمالى";
                    $lblZName      = "خط السير";
                    $inv           = $od['sub_id'];;
                    $id            = $od['order_id'];
                    $oid           = $od['sub_id'];
                    $oBy           = $od['uorderby'];
                    $oByPh         = " ";
                    $Cli           = $od['uname'];
                    $CliPh         = $od['telstr'];
                    $exchange      = $od['EXCHANGE'];
                    $CliAdd        = $text = str_replace(["\r", "\n"], '', $od['addstr']);
                    //$CliAdd = preg_replace('/\s+/', '', $od['addstr']);
                    $invoVal       = 0;
                    $shipVal       = number_format($od['shipval'],2);
                    $totalval      = 0;
                    
                    $customerserv  = "فى حال واجهتاتك اى مشاكل يمكنك الاتصال بنا على الارقام التاليه :";
                    $customPhone   = "01018797001\n01030910888\n01060052620";
                    $customEndText = "مواعيد العمل من ٩ صباحا حتى ٧ مساءً";
                    $ZName 			= isset($od['ZName']) ? $od['ZName']:'غير معروف';
                            
                    //$pdf->MultiCell(40 , 7, $ZName , 0, 'C', 0, 1, '155', '10', true, 1, false, true, 7, 'T');
                    //$pdf->write1DBarcode($oid, "C128", 55, 20, 40, 7, null, array(), '') ;
					//  $pdf->write1DBarcode(
					//    $oid,
					//    'C128',
					//    85,   // X (مكانه الحالي تقريبًا)
					//    18,    // Y
					//    75,    // العرض (مهم جدًا)
					//    25,    // الارتفاع
					//    0.4,   // سماكة الخط
					//    $barcodeStyle,
					//   'N'
					//);	
                    $pdf->SetFont('msungstdlight', 'B', 9, '', true);
                    $pdf->MultiCell(45 , 7, 'OR:'.$oid , 0, 'L', 0, 1, '150', '13', true, 1, false, true, 7, 'T');
					if($exchange){
						$pdf->MultiCell(45 , 7, 'EX:'.$exchange , 0, 'L', 0, 1, '115', '13', true, 1, false, true, 7, 'T');
                    	$pdf->SetFont('aealarabiya', 'B', 12, '', true);
					    $pdf->MultiCell(70 , 7, 'خط : '.$ZName , 0, 'R', 0, 1, '75', '12', true, 1, false, true, 7, 'T');
					    $pdf->MultiCell(70 , 7, '{(استبـــدال)}' , 0, 'R', 0, 1, '105', '12', true, 1, false, true, 7, 'T');
					    
					}else{
                    	$pdf->SetFont('aealarabiya', 'B', 12, '', true);
						$pdf->MultiCell(70 , 7, 'خط : '.$ZName , 0, 'R', 0, 1, '140', '12', true, 1, false, true, 7, 'T');
                    }
                                                            
                    //$pdf->write2DBarcode($oid, 'QRCODE,H', 85, 18, 20, 20);
                    $pdf->write2DBarcode(
					    $oid,
					    'QRCODE,M', // مستوى تصحيح مناسب
					    40,         // X
					    18,         // Y
					    80,         // العرض
					    80,         // الارتفاع
					    $qrStyle,
					    'N'
					);
                    $pdf->MultiCell(13 , 7, $lbl_oBy   , 0, 'R', 0, 0, '', '20', true, 0, false, true, 7, 'T');
                    $pdf->MultiCell(4  , 7, ":"        , 0, 'R', 0, 0, '', '', true, 0, false, true, 7, 'T');
                    $pdf->MultiCell(35 , 7, $oBy       , 0, 'R', 0, 0, '', '', true, 0, false, true, 7, 'T');
                    $pdf->MultiCell(21 , 7, $lbl_oByPh , 0, 'R', 0, 0, '', '', true, 0, false, true, 7, 'T');
                    $pdf->MultiCell(4  , 7, ":"        , 0, 'R', 0, 0, '', '', true, 0, false, true, 7, 'T');
                    $pdf->MultiCell(40 , 7, $oByPh     , 0, 'R', 0, 1, '', '', true, 0, false, true, 7, 'T');
                    $pdf->MultiCell(13 , 7, $lbl_Cli   , 0, 'R', 0, 0, '', '', true, 0, false, true, 7, 'T');
                    $pdf->MultiCell(4  , 7, ":"        , 0, 'R', 0, 0, '', '', true, 0, false, true, 7, 'T');
                    $pdf->MultiCell(35 , 7, $Cli       , 0, 'R', 0, 0, '', '', true, 0, false, true, 7, 'T');
                    $pdf->MultiCell(21 , 7, $lbl_CliPh , 0, 'R', 0, 0, '', '', true, 0, false, true, 7, 'T');
                    $pdf->MultiCell(4  , 7, ":"        , 0, 'R', 0, 0, '', '', true, 0, false, true, 7, 'T');
                    $pdf->MultiCell(40 , 7, $CliPh     , 0, 'R', 0, 1, '', '', true, 0, false, true, 7, 'T');
                    $pdf->MultiCell(21 , 7, $lbl_CliAdd, 0, 'R', 0, 0, '', '', true, 0, false, true, 7, 'T');
                    $pdf->MultiCell(4  , 7, ":"        , 0, 'R', 0, 0, '', '', true, 0, false, true, 7, 'T');
                    $pdf->MultiCell(130, 7, $CliAdd    , 0, 'R', 0, 1, '', '', true, 0, false, true, 0, 'T');
                    $pdf->Ln(2);
                    
                    
                    // إنشاء رأس الجدول
                    $pdf->SetFont('aealarabiya', 'B', 12, '', true);
                    $pdf->setCellMargins(0, 0, 0, 0);
                    $pdf->MultiCell(80, 10, $lblproduct , 1, 'C', 1, 0, '', '', true, 0, false, true, 10, 'M');
                    $pdf->MultiCell(25, 10, $lblprice   , 1, 'C', 1, 0, '', '', true, 0, false, true, 10, 'M');
                    $pdf->MultiCell(25, 10, $lbldiscount, 1, 'C', 1, 0, '', '', true, 0, false, true, 10, 'M');
                    $pdf->MultiCell(20, 10, $lblqty     , 1, 'C', 1, 0, '', '', true, 0, false, true, 10, 'M');
                    $pdf->MultiCell(30, 10, $lblTotal   , 1, 'C', 1, 1, '', '', true, 0, false, true, 10, 'M');
                    
                    // اضافة المنتجات بالجدول
                    $pdf->SetFont('aealarabiya', 'B', 10, '', true);
                    foreach($op as $oProVal){
                        if(isset($oProVal['OPTION_TEXT']) && count($oProVal['OPTION_TEXT']) > 0){
                            $Proname = $oProVal['PRO_NAME']. " | " . implode( " | ",$oProVal['OPTION_TEXT']);
                        } else {
                            $Proname = $oProVal['PRO_NAME'];
                        }
                        $product       = $Proname;
                        $price         = $oProVal['CODE_PRICE'];
                        $qty           = $oProVal['qty'];
                        $discount      = $oProVal['dis'];
                        $total         = (($price-$discount) * $qty);
                        $invoVal       = ($invoVal+$total);
                        $totalval      = ($invoVal + $shipVal);
                        $pdf->MultiCell(80, 7, $product , 1, 'R', 0, 0, '', '', true, 0, false, true, 7, 'M');
                        $pdf->MultiCell(25, 7, number_format($price,2)   , 1, 'C', 0, 0, '', '', true, 0, false, true, 7, 'M');
                        $pdf->MultiCell(25, 7, number_format($discount,2), 1, 'C', 0, 0, '', '', true, 0, false, true, 7, 'M');
                        $pdf->MultiCell(20, 7, number_format($qty,2)     , 1, 'C', 0, 0, '', '', true, 0, false, true, 7, 'M');
                        $pdf->MultiCell(30, 7, number_format($total,2)   , 1, 'C', 0, 1, '', '', true, 0, false, true, 7, 'M');
                    }
                           
                    // إضافة إجمالي الفاتورة
                    $pdf->SetFont('aealarabiya', 'B', 11, '', true);
                    $pdf->setCellPaddings(1, 2, 1, 0);
                    $pdf->MultiCell(95, 10, $customerserv."\n".$customPhone."\n".$customEndText  , '', 'C', 0, 0, '', '', true, 0, false, true, 50, 'T');
                    
                    $pdf->setCellPaddings(0, 1, 12, 1);
                    $pdf->MultiCell(40, 10, $lblinval    , 'B', 'R', 0, 0, '110', '', true, 0, false, true, 10, 'M');
                    $pdf->setCellPaddings(0, 1, 0, 1);
                    $pdf->MultiCell(5 , 10, ':'          , 'B', 'C', 0, 0, '', '', true, 0, false, true, 10, 'M');
                    $pdf->setCellPaddings(12, 1, 0, 1);
                    $pdf->MultiCell(40, 10, number_format($invoVal,2)     , 'B', 'L', 0, 1, '', '', true, 0, false, true, 10, 'M');
                    
                    $pdf->setCellPaddings(0, 1, 12, 1);
                    $pdf->MultiCell(40, 10, $lblship     , 0, 'R', 0, 0, '110', '', true, 0, false, true, 10, 'M');
                    $pdf->setCellPaddings(0, 1, 0, 1);
                    $pdf->MultiCell(5 , 10, ':'          , 0, 'C', 0, 0, '', '', true, 0, false, true, 10, 'M');
                    $pdf->setCellPaddings(12, 1, 0, 1);
                    $pdf->MultiCell(40, 10, number_format($shipVal,2)     , 0, 'L', 0, 1, '', '', true, 0, false, true, 10, 'M');
                    
                    $pdf->setCellPaddings(0, 1, 12, 1);
                    $pdf->MultiCell(40, 10, $lblTotal    , 'T', 'R', 0, 0, '110', '', true, 0, false, true, 10, 'M');
                    $pdf->setCellPaddings(0, 1, 0, 1);
                    $pdf->MultiCell(5 , 10, ':'          , 'T', 'C', 0, 0, '', '', true, 0, false, true, 10, 'M');
                    $pdf->setCellPaddings(12, 1, 0, 1);
                    $pdf->MultiCell(40, 10, number_format($totalval,2)    , 'T', 'L', 0, 1, '', '', true, 0, false, true, 10, 'M');
                }
            }
            $pdf->close(true);
            /*ob_end_clean();
            header('Content-Type: application/pdf');
			header('Content-Disposition: attachment; filename="invoice.pdf"');
			header('Cache-Control: private, max-age=0, must-revalidate');
			header('Pragma: public');
            $pdf->Output('inv.pdf' , 'I');*/
            //ob_end_clean();
			while (ob_get_level()) {
			    ob_end_clean();
			}
			header('Content-Type: application/pdf');
			header('Content-Disposition: attachment; filename="invoice.pdf"');
			header('Cache-Control: private, max-age=0, must-revalidate');
			header('Pragma: public');
			
			$pdf->Output('invoice.', 'D');


            exit;
        }else{
            header('Content-Type: text/html; charset=UTF-8');
            ob_end_clean();



            echo json_encode($data[2]);
        }
        
    }
    // ---------------------------------------------------------
        
   

}
//============================================================+
// END OF FILE
//============================================================+




?>


