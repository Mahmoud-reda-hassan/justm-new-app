var ls;
var shipmentUser=[];

let orderslist = []; // تخزين جميع البيانات
let odata = [];
let lastUpdate = "1971-01-01 00:00:00"; // آخر تحديث للبيانات
let offset = 0; // متغير لتحديد بداية البيانات
let totalRecords = 0; // إجمالي عدد السجلات
let batchSize = 500; // عدد الصفوف لكل دفعة
let isLoading = false; // لمنع تشغيل أكثر من عملية تحميل في نفس الوقت
let totalRequests = 0;
const retryCount = 3;

    

if(Exists && !oService && Go =="Orderlist"){
	orderlistDir= `orderlist_ex${Exists}`;
}else if(!Exists && oService && Go =="Orderlist"){
	orderlistDir= `orderlist_os${oService}`;
}else if(!Exists && !oService && Go == "OrdersCustody"){
	orderlistDir= Go;
}else{
	orderlistDir= Go;
}

let sort = 'asc';
if(Exists == 0){
	sort = 'desc';
}
const vieworderlistPram={
	'CONTROL':Go,
	'ptype':ptype,
	'st':ost,
	'url':wls,
	'did':0
};
document.addEventListener("DOMContentLoaded", function() {
	vieworderlistPram['cofi'] =  $('#Olhbtn li a.active.show').attr('id') // القيمة الجديدة للـ cofi

});
let partbl= ``;
if (Exists && !oService && !oCustody){
    vieworderlistPram['ex'] = Exists;
} else if (oService && !oCustody && !Exists){
    vieworderlistPram['os']=oService;
} else if (oCustody && !Exists && !oService){
	vieworderlistPram['cu']=oCustody;
}
const urlvo = `../../inc/controller/viewOrders.control.php`,
	  urlship = `../../inc/controller/shipment.control.php`,
	  audio_success = new Audio("../../media/beep.mp3"),
	  audio_error = new Audio("../../media/error.mp3"),
	  scanner = new Html5Qrcode( "qrreader", { 
	    formatsToSupport: [
		    Html5QrcodeSupportedFormats.EAN_13,
		    Html5QrcodeSupportedFormats.EAN_8,
		    Html5QrcodeSupportedFormats.UPC_A,
		    Html5QrcodeSupportedFormats.UPC_E,
		    Html5QrcodeSupportedFormats.UPC_EAN_EXTENSION,
		    Html5QrcodeSupportedFormats.QR_CODE,
		],  
	  }),
	  orderidarr  = {
	    'Print':{},
	    'Exchange':{},
	    'Shipp' :{}
	    //'osubid':{}
	  },
	  btnhead = ()=>{
	    if(ptype==1 && Exists ==7){
	        $('#btnPrint').show();
	        $('#btnExchange').show();
	    }else if(ptype==1 && Exists==8){
	        $('#btnShipp').show();
	    }else if(ptype==1 && Go=="OrdersCustody"){
	    
	    	$('#btnQrcode').show();
	    }
	    $('#btnfilter').show();
	  };
/*========================================
	start function 
========================================*/
const stopScanner				= (FNCback) =>{
	scanner.stop();
	$('.btnStratBarcode').attr('onclick',`startScanner(${FNCback})`).show()
};
 qrCodeExchangeCallback			= async (decodedText, decodedResult) => {
 	
	                
	                
	                const od ={};
			        for (const key in odata) {
			            od[odata[key]['subOid']] = odata[key]['oid'];
			        }
	                
			        let lastResult ='',
			        countResults = 0,
			        index = od[decodedText];
			        orderidarr.Exchange ={};
	                //countResults++;
	                //updateOdata();
	                stopScanner(qrCodeExchangeCallback);
	                if(decodedText !== lastResult){
	                    lastResult = decodedText;
	                    if(odata[index]){
	                    	
	                        if(odata[index]['OPrint']){
	                        	
	                            if(!orderidarr.Exchange[index]){
	                                orderidarr.Exchange[index] = odata[index];
	                                Toast_fire(['s','نجح']);
	                                $('#btnExchange .navbar-badge').html(Object.keys(orderidarr.Exchange).length);
	                                audio_success.play();
	                            }else{
	                                Toast_fire(['w','تم ضم امر البيع هذا الى قايمة الاوامر المراد تحولها بالفعل']);
	                                audio_success.play();
	                            }
	                        }else{
	                            Swal.fire(
	                                'نأسف!',
	                                'لايمكن تحويل امر البيع إلا بعد طباعته',
	                                'error'
	                            );
	                            audio_error.play();
	                        }
	                    }else{
	                        Swal.fire(
	                            'نأسف!',
	                            'الكود غير متاح فى هذة القائمة',
	                            'error',
	                        );
	                        audio_error.play();
	                    }
	                }else{
	                     showOselectConfirmationDialog (
	                        "لقد قمت بقرائة هذا الكود مسبقا .. هل تريد اعده قرأته مرة اخرى", 
	                        'انتبه',
	                        'warning',
	                        '',
	                        ()=>{}, 
	                        ()=>{}, 
	                        ()=>{}
	                    );
	                    audio_error.play();
	                }
	            },
 qrCodeShippingCallback 		= async (decodedText, decodedResult) => {
	               
	                
	               
	                const od ={};
			        for (const key in odata) {
			            od[odata[key]['subOid']] = odata[key]['oid'];
			        }
	                
			        let lastResult ='',
			        countResults = 0,
			        index = od[decodedText];
			        orderidarr.Exchange ={};
	                //countResults++;
	                //updateOdata();
	                stopScanner(qrCodeShippingCallback);
	                if(decodedText !== lastResult){
	                    lastResult = decodedText;
	                    //console.log(odata);
	                    if(odata[index]){
	                    	
	                        if(odata[index]['OPrint']){
	                            if(!orderidarr.Shipp[index]){
	                                orderidarr.Shipp[index] = odata[index];
	                                let ht =`
	                                	<table class="col-12 tbl table table-hover table-striped m-0 text-table">
		                                	<thead class="thead-dark text-center">
										      <tr>
										        <th>
										        عدد القطع
										        <span id="qty" class="text.light border pr-2 pl-2 mr-2"></span>
										        </th>
										        <th>
										        عدد اوامر البيع 
										        <span class="text.light border pr-2 pl-2 mr-2">${Object.keys(orderidarr.Shipp).length}</span>
										        </th>
										      </tr>
										    </thead>
									    <tbody>`;
									let qty = 0
	                                for (let key in orderidarr.Shipp) {
	                                	qty += Number(orderidarr.Shipp[key]['PRO_QTY']);
	                                	ht += `   
	                                		<tr>
										          <td>${orderidarr.Shipp[key]['PRO_QTY']}</td>
										          <td>${orderidarr.Shipp[key]['subOid']}</td>

									        </tr>`;
									}
									ht += `</tbody>
										</table>`;
	                                $("#qrreader-results").html(ht);
	                                $("#qrreader-results #qty").html(qty);
	                                Toast_fire(['s','نجح']);
	                                $('#btnShipp .navbar-badge').html(Object.keys(orderidarr.Shipp).length);
	                                audio_success.play();
	                            }else{
	                                Toast_fire(['w','تم ضم امر البيع هذا الى قايمة الاوامر المراد تحولها']);
	                                audio_success.play();
	                                
	                            }
	                        }else{
	                            Swal.fire(
	                                'نأسف!',
	                                'لايمكن تحويل امر البيع إلا بعد طباعته',
	                                'error'
	                            );
	                            audio_error.play();
	                        }
	                    }else{
	                        Swal.fire(
	                            'نأسف!',
	                            'الكود غير متاح فى هذة القائمة',
	                            'error'
	                        );
	                        scanner.stop();
	                        audio_error.play();
	                    }
	                }
	            };
 qrCodeSettlementCallback		= async (decodedText, decodedResult) => {
	                
	                
	                const od ={};
			        for (const key in odata) {
			            od[odata[key]['subOid']] = odata[key]['oid'];
			        }
	                
			        let lastResult ='',
			        countResults = 0,
			        index = od[decodedText];
			      
			        orderidarr.Exchange ={};
	                stopScanner(qrCodeSettlementCallback);
	                if(decodedText !== lastResult){
	                    lastResult = decodedText;
	                    
	                    if(odata[index]){
	                        if(odata[index]['OStat'] > 2){
	                        	
	                                orderidarr.Exchange[index] = odata[index];
	                                audio_success.play();
									const ordata = odata[od[lastResult]],
									dtoid = {};
									dtoid[od[lastResult]] = odata;
									ShipmentApproved(odata);
	                            
	                        }else{
	                        	
	                        	audio_success.play();
	                        	const ordata =  odata[od[lastResult]],
								dtoid = {};
								dtoid[od[lastResult]] = odata;
	                        	ShipmentApproved(odata[index])
	                            
	                            
	                        }
	                    }else{
	                        Swal.fire(
	                            'نأسف!',
	                            'الكود غير متاح فى هذة القائمة',
	                            'error'
	                        );
	                        audio_error.play();
	                    }
	                }else{
	                     showOselectConfirmationDialog (
	                        "لقد قمت بقرائة هذا الكود مسبقا .. هل تريد اعده قرأته مرة اخرى", 
	                        'انتبه',
	                        'warning',
	                        '',
	                        ()=>{}, 
	                        ()=>{}
	                    );
	                    audio_error.play();
	                }
	            },
 startScanner					= (success) => {
	let configScan = { 
		fps: 11, 
		qrbox: { width: 250, height: 200 } 
	};
	const val = $('#SelectUserDelivery').val();
	if($('#SelectUserDelivery').is(':visible') && !val){
			$(".DOV").hide();
	        $(".DOL").show();
	        $(".DIRC").hide();
			responseMsg(['warning','نعتذر','لايمكن استخدام قارئ البار كود الا عند اختيار المندوب',[]]);
	}else{
		if(typeof success == "function"){
		    // تحقق من دعم المتصفح لواجهة getUserMedia
		    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
		        // طلب الوصول إلى الفيديو (الكاميرا)
		        navigator.mediaDevices.getUserMedia(
		        	{
				        video: {
				            facingMode: "environment", // اختيار الكاميرا الخلفية
				            advanced: [{ focusMode: "continuous" }] // ضبط الفوكس التلقائي
				        }
				    }
		        ).then(function(stream) {
		        	stream.getTracks().forEach(track => track.stop());
		        		$('.btnStratBarcode').hide()
						scanner.start(
							{ facingMode: "environment" }, 
						    configScan, 
						   	success
						).catch((err) => {
						    $(".DOV").hide();
						    $(".DOL").show();
						    $(".DIRC").hide();
						    scanner.stop();
						    Toast_fire(['e',"تم رفض الوصول إلى الكاميرا:"+ err.name,]);
						});
		        }).catch(function(err) {
		            // رفض الوصول إلى الكاميرا
		            $(".DOV .DIRC").hide();
		    		$(".DOL").show();
		    		$(".DIRC").hide();
		            Toast_fire(['e',"تم رفض الوصول إلى الكاميرا:"+ err.name,]);
		        });
		    } else {
		    	$(".DOV .DIRC").hide();
		    	$(".DOL").show();
		    	//$(".DIRC").hide();
		        Toast_fire(['e',"المتصفح لا يدعم الوصول إلى الكاميرا."]);
		    }
		
		}else{
		    $(".DOV").hide();
		    $(".DOL").show();
		    $(".DIRC").hide();
			Toast_fire(['e',"المتصفح لا يدعم الوصول إلى الكاميرا."]);
		}
	}
 },
 custodyTotal					= (dt) => {
	//console.log(dt)
	$('.cut').html(dt['cuTotal']['cuTotal']);
	$('.custodyTotal .cu2').html(dt['cuTotal']['cuAshipp']);
	$('.custodyTotal .cu3_4').html(dt['cuTotal']['cuBshipp']);
	$('.custodyTotal .cutqty').html(dt['cuTotal']['cuRefQty']);
},
 ShipmentDelivery				= (orderData) => {
	
	let qty_rejected = 0;
		Swal.fire({
			title:" ",
			html: 'جارى عرض البيانات ...',
			icon : '',
			showCancelButton: true,
			confirmButtonText:'تأكيد',
			cancelButtonText:'إلغاء',
			customClass: {
				actions: 'my-actions',
		    	confirmButton: 'order-1 ',
		    	cancelButton: 'order-2 right-gap',
			},
			didOpen: () =>{
				Swal.showLoading();
				$(".swal2-cancel").hide();
				rq = $.ajax({
		            url: urlship,
		            headers: {
		                //'Authorization':'Basic xxxxxxxxxxxxx',
		                //'X-CSRF-TOKEN':'xxxxxxxxxxxxxxxxxxxx',
		                //'Content-Type':'application/json'
		            },
		            async: true,
		            type: 'POST',
		            data: {
					    "CONTROL": "shipDelivery",
					    "order_id": orderData,
						},
		            dataType: 'json'
		        });
		        rq.done(function (response, statuse) {
		        	if(response[0]==="success"){
			        	$(".swal2-title").html("تأكيد تسليم امر البيع")
			            $('.swal2-html-container').html(response[1]);
						Swal.hideLoading();
						$('.swal2-html-container').show();
						$(".swal2-cancel").show();
		        	}else if(response[0]==="warning"){
		        		responseMsg(response);
		        	}
		        });
		        rq.fail(function (xhr, textStatus) {
		        	Swal.fire(
			            'نأسف',
			            'نعتذر حدث خطاء غير معروف اثناء تنفيذ هذا الاجراء ',
			            'error'
			        )
		        });
			},
			preConfirm: () => {
				Swal.showLoading();
				var obj = {}, objApprov = {}, objReject={}, err=0, qtyDef=0, qtyApp=0;
				// تحديد جميع عناصر tr داخل الجدول
				var trElements = document.querySelectorAll(".table-swel tbody tr");
				
				// حلقة للتكرار على كل عنصر tr
				trElements.forEach(function(trElement) {
					
					var code   = trElement.getAttribute('data-code');
					//الحصول على العنصر defaultQty 
					var defQty = trElement.querySelector("td .default-Qty");
					var dQty    = parseFloat(defQty.innerText);
				    // الحصول على العنصر approved-qty داخل كل tr
				    var appQty = trElement.querySelector("td .approved-Qty");
					var aQty    = parseFloat(appQty.innerText);
				//	var aQty    = dQty - aQty;
					var proName = trElement.querySelector("td .proname");
					proName = proName.innerText;
					qtyDef+=dQty;
					qtyApp+=aQty;
					
				    // التحقق مما إذا كان العنصر موجودًا
				    if (aQty<=dQty && aQty>=0 && dQty>=0 && code) {
				        // الحصول على القيمة المخزنة في العنصر
						objApprov[code]={'defqty':dQty,
							'appQty':aQty
						};
						 if(aQty>=0 && dQty > aQty) {
							objReject[code]={'defqty':dQty,
								'appQty':aQty,
								'proData':proName
							};
						}
				    } else {
				    	Swal.showValidationMessage('لايمكن تسليم كمية اكتر من المطلوب او اقل من الصفر')
						err++;
				    }
				});
				if(qtyApp > 0 && qtyDef === qtyApp){
					obj['reapp'] = "تسليم امر البيع بالكامل";
				}else if(qtyApp > 0 && qtyDef !== qtyApp){
					qty_rejected++;
					obj['reapp']="تسليم امر البيع بشكل جزئي";
				}else if(qtyApp == 0){
					qty_rejected++;
					obj['reapp']="رفض امر البيع بالكامل";
				}
				obj['odata'] = orderData
				obj['oapp']  = objApprov;
				obj['orej']  = objReject;
				obj['utype'] = ptype;
				return obj;
			}
		}).then((result) => {
			if(result.isConfirmed){
				Swal.fire({
					title: 'تأكيد',
					icon : 'question',
					html : 'سوف يتم '+result.value.reapp+'؟',
					showCancelButton: true,
					confirmButtonText:'حفظ',
					cancelButtonText:'إلغاء',
					preConfirm: () => {
						const objconf = result.value;
						
						const srqobj = {
						    "CONTROL": "shipDelivered",
						    "odata"  : objconf['odata'],
						    "oshipp" : {
						        "oapp" : objconf['oapp'],
						        "orej" : objconf['orej'],
						        "reapp": objconf['reapp'],
						        "ptype": objconf['utype']
						    },
						    "ShipApproved" : false,
						    'urlControl' : Go
						};
						return srqobj;
					}
				}).then((result) => {
					if(result.isConfirmed){
						if(ptype === 1 && Go === "OrdersCustody" && qty_rejected >0){
								Swal.fire({
									title: 'تأكيد',
									icon : 'question',
									html : 'هل تريد استلام المرتجعات ',
									showCancelButton: true,
									confirmButtonText:'نعم',
									cancelButtonText:'لا',
									preConfirm: () => {
										result.value['ShipApproved'] = true;
									}
								}).then((result2) => {
									res = sendRequest(urlship,result.value);
									responseMsg(res);
									table.clear().rows.add(data).draw().on('draw', function() {
										// هذه الكود سيتم تشغيله بعد اكتمال عرض البيانات
										custodyTotal(orderslist);
									});
								})
						}else{
							res = sendRequest(urlship,result.value);
							responseMsg(res);
							table.clear().rows.add(data).draw().on('draw', function() {
								// هذه الكود سيتم تشغيله بعد اكتمال عرض البيانات
								custodyTotal(orderslist);
							});
						}
					}
				});
			}
		});
	
},
 increaseQty					= (productId) => {
	$('.swal2-html-container .btn').blur();
  var maxqty = document.getElementById('max-qty-' + productId);
  var d = document.getElementById('difference-qty-'+productId);
  var e = document.getElementById('approved-qty-' + productId);
  var currentValue = parseFloat(e.innerHTML);
  //var carrentdvalue= parseFloat(d.innerHTML);
  var max = parseInt(maxqty.innerHTML);
  if (currentValue < max) {
    e.innerHTML = parseFloat(currentValue + 1);
    d.innerHTML = parseFloat(max - (currentValue + 1))
  }
},
 increaseQtyAll 				= (productId) => {
	$('.swal2-html-container .btn').blur();
  var maxqty = document.getElementById('max-qty-' + productId);
  var d = document.getElementById('difference-qty-'+productId);
  var e = document.getElementById('approved-qty-' + productId);
  var max = parseInt(maxqty.innerHTML);
  e.innerHTML = max;
  d.innerHTML =0;
},
 increaseQtyAllPublic			= () => {
	$('.swal2-html-container .btn').blur();
	var trElements = document.querySelectorAll(".table-swel tbody tr");
	// حلقة للتكرار على كل عنصر tr
	trElements.forEach(function(trElement) {
		var code   = trElement.getAttribute('data-code');
		var d = document.getElementById('difference-qty-'+code);
		var maxqty = document.getElementById('max-qty-' + code);
		var e = document.getElementById('approved-qty-' + code);
		var max = parseInt(maxqty.innerHTML);
		e.innerHTML = max;
		d.innerHTML = 0;
	})
},
 decreaseQty					= (productId) => {
	$('.swal2-html-container .btn').blur();
  var d = document.getElementById('difference-qty-'+productId);
  var e = document.getElementById('approved-qty-' + productId);
  var maxqty = document.getElementById('max-qty-' + productId);
  var max = parseInt(maxqty.innerHTML);
  var currentValue = parseFloat(e.innerHTML);
  if (currentValue > 0) {
    e.innerHTML = parseFloat(currentValue - 1);
    d.innerHTML = parseFloat(max - (currentValue - 1));
  }
},
 decreaseQtyAll 				= (productId) => {
	$('.swal2-html-container .btn').blur();
  var d = document.getElementById('difference-qty-'+productId);
  var e = document.getElementById('approved-qty-' + productId);
  var maxqty = document.getElementById('max-qty-' + productId);
  var max = parseInt(maxqty.innerHTML);
  e.innerHTML = 0;
  d.innerHTML = max;
},
 decreaseQtyAllPublic			= () => {
	$('.swal2-html-container .btn').bl
		var trElements = document.querySelectorAll(".table-swel tbody tr");
	// حلقة للتكرار على كل عنصر tr
	trElements.forEach(function(trElement) {
		var code   = trElement.getAttribute('data-code');
		var d = document.getElementById('difference-qty-'+code);
		var e = document.getElementById('approved-qty-' + code);
		var maxqty = document.getElementById('max-qty-' + code);
		var max = parseInt(maxqty.innerHTML);
		e.innerHTML = 0;
		d.innerHTML = max;
		
	})
},
 actionSupport					= (allOrdeDataObj,osid=0)=>{
    const os = {};
    for(let key in allOrdeDataObj){
         const x = allOrdeDataObj[key];
         if(x.OSMsgCS){
             os[x.OSid]=[x.OStat,x.OSMsgCS];
         }
    }
    const x = new Promise((resolve,error)=>{
      if(userlogin_data['uScript']['Admin'][orderlistDir]['transformation'] == "true"){
        let inputOptions = new Promise( function (resolve) {
            let obj = {};
            for(let i = 0; i < existsview[1].length; i++){
              if(Exists != existsview[1][i][0]){
                  if(userlogin_data['uScript']['Admin'][orderlistDir][existsview[1][i][3]] == "true"){
                        obj[existsview[1][i][0]] = existsview[1][i][2];
                  }
              }
            } 
            resolve(obj);
        });
        let ex_int = null;
        Swal.fire({
            title: 'إختار حالة للتحويل اليها !',
            input: 'radio',
            inputOptions: inputOptions,
            showCancelButton: true,
            cancelButtonText: 'إلغاء',
            confirmButtonText: 'تحويل',
            inputValidator: (value) => {
            
              if (!value && value !=0) {
                return 'من فضلك اختار الحالة المراد التحويل اليها!';
              }
            },
        }).then((result) => {
        	//console.log(allOrdeDataObj)
            if(result.isConfirmed){
                inputOptions.then((sTrue) => { 
                	const ordersobj ={};
                	Object.keys(allOrdeDataObj).forEach(key => {
                		ordersobj[key] = {} ;
                		ordersobj[key]['olup'] = allOrdeDataObj[key]['olup'];
						ordersobj[key]['OExists'] = allOrdeDataObj[key]['OExists'];
						
                		
                	});
                   /* 
                   olup
                   OExists
                   
                   */
                    const Parameters= {
                    				   'CONTROL' : 'Redirect_Orders',
                                       'ex_int' : result.value,
                                       'ex_str' : sTrue[result.value],
                                       'osid'   : osid,
                                       'ordersData' : ordersobj,
                    };
                    const response  = sendRequest(urlControl,Parameters, false);
                    Swal.fire({
                        title: response[1],
                        html : `<div>${response[2]}</div>
                                 <div id="orderrejected"></div>`,
                        icon : response[0],
                        confirmButtonText: 'اغلاق',
                        didOpen : ()=>{
                            function sticon(val){
                                let rx = '<i class="fa-solid fa-check text-success"></i>'
                                if(!val) {
                                    rx = '<i class="fa-solid fa-xmark text-danger"></i>';
                                }
                                return rx;
                            }
                            if(response[0] === "error" || response[0] === "warning"){
                                let tablehtml ='';
                                const res = response[3];
                                
                                tablehtml += `<hr style="margin:5px 0px 5px 0px"><div class="text-left ml-2" style="font-size:0.85rem;">
                                                    <div style="margin:-4px 0px 0px 0px"><i class="fa-solid fa-arrows-rotate text-primary"></i><span class="ml-2 text-info">  لايوجد اى تحديث بيانات لهذا الامر اثناء عملك</span></div>
                                                    <div style="margin:-4px 0px 0px 0px"><i class="fas fa-headset text-primary"></i><span class="ml-2 text-info"> مسموح التحويل اثناء وجود طلبات مفتوحة لخدمة العملاء</span></div>
                                                    <div style="margin:-4px 0px 0px 0px"><i class="fa-solid fa-print text-primary"></i><span class="ml-2 text-info"> طباعة ملصق الشحن قبل التحويل الى تم التجهيز</span></div>
                                                    <div style="margin:-4px 0px 0px 0px"><i class="fas fa-traffic-light text-primary ml-1"></i><span class="ml-2 text-info"> مسموح بلتحويل من الحالة الحالية الى الحالة المحدده</span></div>
                                                    <div style="margin:-4px 0px 0px 0px"><i class="fas fa-warehouse text-primary"></i><span class="ml-2 text-info"> المنتجات متوفرة بالمخزن</span></div>
                                             </div>`;
                                             
                                tablehtml += `<hr style="margin:0px 30px 0px 30px"><div class="text-center mt-2 text-primary"  style="font-size:0.85rem;"> علامة  <i class="fa-solid fa-check text-success"></i> تعنى تحقق الشرط  وعلامة <i class="fa-solid fa-xmark text-danger"></i> لم يتحقق الشرط </div>
                                
                                            <table class="mt-1 table table-hove table-responsive w-100 text-nowrap" style="max-height:400px;">
                                                <thead><tr>
                                                    <th class="text-left">رقم الامر</th> 
                                                    <th><i class="fa-solid fa-arrows-rotate"></i></th>
                                                    <th><i class="fas fa-headset"></i></th>
                                                    <th><i class="fa-solid fa-print"></i></th>
                                                    <th><i class="fas fa-traffic-light"></i></th>
                                                    <th><i class="fas fa-warehouse"></i></th>
                                                    </tr>
                                                </thead>
                                                <tbody style="font-size:0.90rem;">`;
                                for (let key in res) {
                                    tablehtml += `<tr>
                                                    <td class="col-12 text-left">${res[key]['osubid']}</td>
                                                    <td class="col-12">${sticon(res[key]['lastup'])}</td>
                                                    <td class="col-12">${sticon(res[key]['oserv'])}</td>
                                                    <td class="col-12">${sticon(res[key]['print'])}</td>
                                                    <td class="col-12">${sticon(res[key]['statuse'])}</td>
                                                    <td class="col-12">${sticon(res[key]['store'])}</td>
                                                  </tr>`;
                                }
                                tablehtml +=`   </tbody>
                                            </table>`;
                                
                                $("#orderrejected").html(tablehtml);
                            }
                            if(response[0] !== "error"){
                                updateOrders();
                            }
                        }
                    })
                })
            }
        });
  
      } else {
        Toast_fire(['w','ليس لديك صلاحية تحويل امر البيع']);
      }
        
    }).then((resolve)=>{
       return resolve
    })
    return x
},
 oSelect						= async (id,ftype) => {
    const dt  = odata
    const odt = odata[id];
    let response=[]
    if(ptype == 1){
        if(odt.OSid > 0 && odt.OSMsgCS >0) {
            let n = new Promise((resolve) => {
                resolve(ticketView(odt.oid,odt.OSid,ptype));
            }).then((resolve)=>{
                if(resolve){
                    return 'osOpen';
                }
            });
        }else{
            if(!orderidarr[ftype][odt.oid]){
            	if(ftype =='Print'){
            		var {subOid,OSMsgCS,ZName,olup} = odt;
            		orderidarr[`${ftype}`][odt.oid] = {"subOid":subOid,"OSMsgCS":OSMsgCS,"ZName":ZName,"olup":olup};
            	}else{
                	orderidarr[`${ftype}`][odt.oid] = odt
            	}
                $(`#btn${ftype} .navbar-badge`).html(Object.keys(orderidarr[ftype]).length);
            };
            return 'success';
        };
    };
},
 showOselectConfirmationDialog	= (htm, title, icon, denyBtnText, didOpenCallback, confirmCallback, denyCallback) => {
    let showDeny = true, showCancel = true , i ='';
    if(!denyBtnText){
        showDeny = false;
    }
    Swal.fire({
        title:title,
        html: htm,
        icon: icon,
        showDenyButton: showDeny,
        showCancelButton: true,
        confirmButtonText: 'موافق',
        denyButtonText: denyBtnText,
        cancelButtonText: 'إغلاق',
        customClass: {
          actions: 'my-actions',
          confirmButton: 'order-1',
          denyButton: 'order-2',
          cancelButton: 'order-3 left-gap',
        },
        didOpen: didOpenCallback,
    }).then((result) => {
        if (result.isConfirmed) {
          confirmCallback();
        } else if (result.isDenied) {
          denyCallback();
        }
    });
},
 deliveryConfirmation			= async (order) => {
 
    let obj = {'shipUser':'','shipDate':'','order_id':order}, today = new Date();
    today = today.toLocaleDateString("en-US", option2);
    obj['shipDate'] = today;
    if(userlogin_data['uScript']['Admin'][orderlistDir]['Shipment'] == "true"){
        function shipmentudata(){
            let returnobj ={};
            if(!shipmentUser.length){
                shipmentUser = sendRequestGET(`${urlApi}?CONTROL=UserListForType&utype=delivery`);
            }
            for(let item in shipmentUser){
                if(shipmentUser[item]['STATUSE']){
                    returnobj[shipmentUser[item]['ID']] = shipmentUser[item]['NAME'];
                }
            }
            
            return returnobj
        }
        const countProducts = sendRequest(urlship, {'CONTROL':'countProducts','order_id':order}, false);
        const shipuser = shipmentudata()
        Swal.fire({
            title: 'شحن عن طريق',
            html :  'اجمالى عدد الاوامر ('+countProducts[0] +') اجمالى عدد المنتجات ('+countProducts[1]+')',
            input: 'select',
            inputOptions:   shipuser,
            inputPlaceholder: 'اختار موظف الشحن',
            showCancelButton: true,
            cancelButtonText: 'الغاء',
            confirmButtonText:'تم',
            inputValidator: (value) => {
                if (value === '') {
                    return 'من فضلك قم بإختيار الموظف اولاً';
                }
            },
            didOpen : ()=>{
                $('.swal2-select').select2({
                    width: 'auto',
                    dir: 'rtl'
                });
                $('.select2').css('margin', '20px');
            },
        }).then((result) => {
            
            if (result.isConfirmed) {
                obj['shipUser'] = result.value;
                obj['shipUName'] = shipuser[result.value];
               //console.log(obj);
                async function done(obj){
                   obj['CONTROL'] = 'shipmentOut';
                   let rq = await sendRequest(urlship, obj, false);
                   if(rq[0] !== 'error'){
                        updateOrders();
                   }
                   Swal.fire({
                        title: rq[1],
                        icon : rq[0],
                        html:`<div class="form-group ">${rq[2]}</div><div class="tbl"></div>`,
                        confirmButtonText:'اغلاق',
                        didOpen: () =>{
                            if(Object.keys(rq[3]).length){
                                let trd = '';
                                function ic(val){
                                    let icon = '<i class="text-success fa fa-check fa-2x">';
                                    if(val == false){
                                        icon = '<i class="text-danger fa fa-times fa-2x">';
                                    }
                                    return icon;
                                }
                                for (const x in rq[3]){
                                    
                                    trd += `<tr>
                                        <td>${rq[3][x].osubid}</td>
                                        <td>${ic(rq[3][x].oserv)}</td>
                                        <td>${ic(rq[3][x].lastup)}</td>
                                        <td>${ic(rq[3][x].store)}</td>
                                        <td>${ic(rq[3][x].freeze)}</td>
                                    <tr>`;
                                }
                                $('.swal2-html-container .tbl').html(`
                                <div class="text-left ml-5" style="font-size: 0.80rem;">
                                    <i class="fa-solid fa-users"></i> طلبات العملاء <i class="text-success fa fa-check fa-1x"></i> لا يوجد <i class="text-danger fa fa-times fa-1x"></i> يوجد طلب </span><br>
                                    <i class="fa-solid fa-arrows-rotate"></i>   حالة الامر <i class="text-success fa fa-check fa-1x"></i> لايوجد تغير <i class="text-danger fa fa-times fa-1x"></i> يوجد تغير <br>
                                    <i class="fa-solid fa-warehouse"></i> المخزون <i class="text-success fa fa-check fa-1x"></i> متوفر <i class="text-danger fa fa-times fa-1x"></i> غير متوفر  <br>
                                    <i class="fa-solid fa-lock"></i> مقيد لهذا المندوب <i class="text-success fa fa-check fa-1x"></i> غير مقيد <i class="text-danger fa fa-times fa-1x"></i> مقيد  <br>
                                </div>
                                <table class="table" style="font-size: 0.84rem;">
                                    <tr>
                                        <th>امر بيع</th>
                                        <th><i class="fa-solid fa-users"></i></th>
                                        <th><i class="fa-solid fa-arrows-rotate"></i></th>
                                        <th><i class="fa-solid fa-warehouse"></i></th>
                                        <th><i class="fa-solid fa-lock"></i></th>
                                    </tr>
                                    ${trd}
                                </table>`);

                            }
                        }
                   });
                }
                if(userlogin_data['uScript']['Admin'][orderlistDir]['Shipmentdate'] == "true"){
                    Swal.fire({
                        title: 'تغير تاريخ الشحن', 
                        html:'<div class="form-group"><input type="daterange" id="shipmentdate" class="form-control shipmentdate" autoOpen="false"></div>',
                        confirmButtonText:'موافق',
                        didOpen: () =>{
                            const e =  $('#shipmentdate');
                            e.click(()=>{
                              e.attr("disabled" , true);
                              e.attr("disabled" , false);
                            });
                            e.daterangepicker({
                                singleDatePicker: true,
                                "locale": {
                                    "format": "YYYY/MM/DD",
                                    "separator": " - ",
                                    "applyLabel": "موافق",
                                    "cancelLabel": "الغاء",
                                    "fromLabel": "من",
                                    "toLabel": "الى",
                                    "customRangeLabel": "تخصيص",
                                    "weekLabel": "رقم الاسبوع",
                                    "daysOfWeek": [ "الاحد","الاثنين","الثلاثاء","الاربعاء","الخميس","الجمعه","السبت"],
                                    "monthNames": ["يناير", "فبراير", "مارس", "ابريل", "مايو", "يونية", "يوليو", "اغسطس", "سبتمبر", "اكتوبر", "نوفمبر", "ديسمبر"],
                                    "firstDay": 1
                                }, 
                            })
                        },
                        preConfirm: function () {
                            const e =  $('#shipmentdate');
                            obj['shipDate'] = e.val();
                            return obj;
                        }
                    }).then((result) => {
                        //console.log(obj)
                        if (result.isConfirmed) {
                            done(obj).then((rq)=>{
                                
                            })
                        }
                    })
                }else{
                    done(obj).then((rq)=>{
                        
                    })
                }
            }
        });
    } else {
        Toast_fire(['w','ليس لديك صلاحية تسليم امر البيع للمندوب']);
    }
},
 displayOrderViewData   		= (orderID,btnapp) => {
    // Define constants for clearer code
    
    const obj = orderdataview(orderID);
        const ORDER_TYPE	= obj[orderID].orderData.ordertype,
             U_ORDER_BY 	= obj[orderID].orderData.uorderby,
             ORDER_DATE 	= obj[orderID].orderData.orderDate,
             SHIP_DATE		= obj[orderID].orderData.shipDate,
             SHIP_WAITING	= obj[orderID].orderData.shipWaiting,
             STATUS_NAME	= obj[orderID].orderData.status_name,
             STATUS_ID		= obj[orderID].orderData.status_id,
             EXISTS_NAME	= obj[orderID].orderData.exists_name,
             SUB_ID 		= obj[orderID].orderData.sub_id,
             U_NAME 		= obj[orderID].orderData.uname,
             ADD_STR		= obj[orderID].orderData.addstr,
             TEL_STR		= obj[orderID].orderData.telstr,
             U_MAIL 		= obj[orderID].orderData.umail,
             CLI_COMMENT	= obj[orderID].orderData.cliComment,
             ORDER_PRO		= obj[orderID].orderPro,
             ORDER_ACTION	= obj[orderID].orderAction,
             supTotal		= calculateTotalPrice(ORDER_PRO),
             shipVal		= obj[orderID].orderData.shipval,
             total			= formatPrice(parseFloat(supTotal) + parseInt(shipVal));
    // Update order data
    $('#obyuname').html('<strong>' + ORDER_TYPE + ' : </strong>' + U_ORDER_BY);
    $('#odate').html('<strong>الانشاء : </strong>' + ORDER_DATE);
    $('#oshipdate').html('<strong>الشحن : </strong>' + shippingDateStr(SHIP_DATE, SHIP_WAITING));
    $('#ostatuse').html('<strong>حالة امر البيع : </strong>' + STATUS_NAME);
    $('#oexists').html('<strong>الموقف الادارى : </strong>' + EXISTS_NAME);
    $('#orderid').html(SUB_ID);
    $('#ouname').html('<strong> العميل : </strong>' + U_NAME);
    $('#ouaddress').html('<strong>العنوان : </strong>' + ADD_STR);
    $('#ouphone').html('<strong>الهاتف : </strong>' + TEL_STR);
    $('#ouemail').html('<strong>البريد الالكتروني : </strong>' + U_MAIL);
    if (CLI_COMMENT) {
        $('#oucomm').html('<strong> ملاحظات : </strong>' + CLI_COMMENT);
    } else {
        $('#oucomm').html('');
    }
    // Update product order view
    $("#ovdt .productOrderView").html('');
    for (let i in ORDER_PRO) {
    
        const proName = ORDER_PRO[i].PRO_NAME,
            optionText = formatOptionText(ORDER_PRO[i].OPTION_TEXT),
            qty = ORDER_PRO[i].qty,
            codePrice = ORDER_PRO[i].CODE_PRICE,
            dis = ORDER_PRO[i].dis,
        totalPrice = formatPrice((codePrice - dis) * qty);
            
            let stqty= ORDER_PRO[i].STQTY;
        $("#rowvop .tdrest").html('');
        $("#rowvop .tdproName").html(proName);
        $("#rowvop .tdproOption").html(optionText);
        $("#rowvop .tdproQty").html(qty);
        $("#rowvop .tdproPrice").html(codePrice);
        $("#rowvop .tdproDis").html(dis);
        $("#rowvop .tdproTotal").html(totalPrice);
        if(stqty<=0){
            stqty='غير متوفر';
            $("#rowvop .tdstore").addClass('bg-danger');
        } else {
            $("#rowvop .tdstore").removeClass('bg-danger');
        }
        $("#rowvop .tdstore").html(stqty);
        $("#ovdt .productOrderView").append($("#rowvop tbody").html());
    }
    // Calculate and update total prices
    $('#ovdt .suptotal').html(supTotal);
    $('#ovdt .shipval').html(shipVal);
    $('#ovdt .total').html(total);
    // Update order action loop
    const orderActionHtml = generateOrderActionList(ORDER_ACTION);
    $('#ovdt .actionOrderloop').html(orderActionHtml)
    // Show/hide relevant elements
    $('.DOV').html($('#ovdt').html());
    $('.DOV').show();
    $('.DOL').hide();
    $('.DCU').hide();
    $('#btnfilter').after('<div id="btnhead" class="btnhead btn-group">' + btnapp + '<button id="btnback" type="button" class="btn btn-app-row w-100 h-100 f-5 btn-primary btn-xs btnback"><i class="fas fa-arrow-left"></i></button></div>');
    //btnapp.appendTo("#btnhead");
    $('.btnhead .btn-xs').attr('style', '');
    $('.btnhead .btnOview').remove();
    const btnGroupHtml = $('.btnhead .btn-group-vertical').html();
    $('.btnhead .btn-group-vertical').remove();
    $('.btnhead .btnOedit').after(btnGroupHtml);
    //$('.btnhead .lastbtn').after('<button id="btnback" type="button" class="btn btn-primary btn-xs badge btnback"><i class="fas fa-arrow-left"></i></button>');
    $('.btnhead .btn-xs').attr('style', 'margin-right:3px');
    $('.btnh').hide();
    $('#fiinfo').hide();
    hideSpinner();
    
},
 refundEmployeeCustody = async (order) => {
    if (userlogin_data['uScript']['Admin'][orderlistDir]['RefundShipment'] == "true") {
        let swalContent = `
            <p>هل تريد استرداد أمر البيع من المندوب؟</p>
            <div style="display: flex; align-items: center; margin-top: 10px;">
                <input type="checkbox" id="confirmRefundCheckbox" style="margin-right: 5px;">
                <label for="confirmRefundCheckbox">أنا أوافق على استرداد أمر البيع</label>
            </div>
        `;

        let result = await Swal.fire({
            title: 'هل أنت متأكد؟',
            html: swalContent,
            icon: 'question',
            showCancelButton: true,
            cancelButtonText: 'إلغاء',
            confirmButtonText: 'نعم',
            didOpen: () => {
                let confirmButton = Swal.getConfirmButton();
                confirmButton.disabled = true;

                // تفعيل الزر عند تحديد Checkbox
                document.getElementById("confirmRefundCheckbox").addEventListener("change", function () {
                    confirmButton.disabled = !this.checked;
                });

                // تأخير تفعيل الزر 10 ثوانٍ
                setTimeout(() => {
                    confirmButton.disabled = false;
                }, 10000);
            }
        });

        if (result.isConfirmed) {
            if (userlogin_data['uScript']['Admin'][orderlistDir]['ChooseRefund'] == "true") {
                let result2 = await Swal.fire({
                    title: '',
                    html: 'هل تريد رد المنتجات إلى المخزن؟',
                    icon: 'question',
                    showCancelButton: true,
                    cancelButtonText: 'لا',
                    confirmButtonText: 'نعم'
                });

                let msg = result2.isConfirmed
                    ? 'أنت طلبت استرداد أمر البيع ورد المنتجات إلى المخزن.'
                    : 'أنت طلبت استرداد أمر البيع فقط، ستبقى المنتجات عهدة على المندوب.';

                let finalResult = await Swal.fire({
                    title: 'تأكيد',
                    html: `<div>${msg}</div>`,
                    icon: 'info',
                    showCancelButton: true,
                    cancelButtonColor: "#ff0000",
                    cancelButtonText: 'إلغاء',
                    confirmButtonText: 'تأكيد'
                });

                if (finalResult.isConfirmed) {
                    const res = sendRequest(urlship, { 'CONTROL': 'shipmentRef', 'order_id': order, 'type': !result2.isConfirmed }, false);
                    responseMsg(res);
                    updateOrders();
                }
            } else {
                let finalResult = await Swal.fire({
                    title: 'تأكيد',
                    html: `<div>أنت طلبت استرداد أمر البيع</div>`,
                    icon: 'info',
                    showCancelButton: true,
                    cancelButtonColor: "#ff0000",
                    cancelButtonText: 'إلغاء',
                    confirmButtonText: 'تأكيد'
                });

                if (finalResult.isConfirmed) {
                    const res = sendRequest(urlship, { 'CONTROL': 'shipmentRef', 'order_id': order, 'type': true }, false);
                    responseMsg(res);
                    updateOrders();
                }
            }
        }
    } else {
        Toast_fire(['w', 'ليس لديك صلاحية لاسترداد أمر البيع']);
    }
},
 ShipmentApproved				= async (order) => {
 	objdata = {}
 	objdata[order.oid] = order
 	if(userlogin_data['uScript']['Admin'][orderlistDir]['ShipmentApproved'] == "true"){
		
		if(order.OStat > 2){
	 		
	 		Swal.fire({
	            title: 'تأكيد!',
	            html: 'جارى عرض البيانات ...',
	            //html : htm, 
	            icon : 'question',
	            showCancelButton: true,
	            cancelButtonText: 'الغاء',
	            confirmButtonText:'موافق',
	            didOpen: () =>{
					Swal.showLoading();
					$(".swal2-cancel").hide();
					rq = $.ajax({
			            url: urlship,
			            headers: {
			                //'Authorization':'Basic xxxxxxxxxxxxx',
			                //'X-CSRF-TOKEN':'xxxxxxxxxxxxxxxxxxxx',
			                //'Content-Type':'application/json'
			            },
			            async: true,
			            type: 'POST',
			            data: {
						    "CONTROL": "shipDelivery",
						    "order_id": objdata,
							},
			            dataType: 'json'
			        });
			        rq.done(function (response, statuse) {
			        	if(response[0]==="success"){
				        	$(".swal2-title").html("هل تريد انشاء اذن استلام للمرتجعات التاليه؟")
				            $('.swal2-html-container').html(response[1]);
							$('.swal2-html-container .btn-success').hide();
				            $('.swal2-html-container .btn-danger').hide();
				            $(".swal2-html-container thead").addClass('bg-black');
							$(".swal2-html-container table").css('font-size','1.2rem');
							$(".swal2-html-container th:nth-child(2), .swal2-html-container td:nth-child(2)").hide();
				            $(".swal2-html-container th:nth-child(4), .swal2-html-container td:nth-child(4)").hide();
							$(".swal2-html-container th:nth-child(5), .swal2-html-container td:nth-child(5)").hide();
							$(".swal2-html-container tbody tr").each(function() {
					            var salary = $(this).find("td:nth-child(3)").text().trim(); // جلب قيمة العمود الثالث (الراتب)
					            if (salary == "0") {
					                $(this).hide(); // إخفاء الصف إذا كان الراتب 5000
					            }
							});
							Swal.hideLoading();
							$('.swal2-html-container').show();
							$(".swal2-cancel").show();
			        	}else if(response[0]==="warning"){
			        		responseMsg(response);
			        	}
			        });
			        rq.fail(function (xhr, textStatus) {
			        	Swal.fire(
				            'نأسف',
				            'نعتذر حدث خطاء غير معروف اثناء تنفيذ هذا الاجراء ',
				            'error'
				        )
			        });
				},
	        }).then((result) => {
	        	if(result.isConfirmed){
		        	const res2 = sendRequest(urlship, {
		        					'CONTROL':'approvedShip',
		        					'oid':order['order_id'],
		        					'odata':order,
		        					'delivery_id':order['shipUid']
		        				}, false);
		        	responseMsg(res2);
		        	updateOrders();
		        	
	        	}
	        	
	        	
	        })
		} else {
			ShipmentDelivery(objdata);
		}
 	}else{
 		Toast_fire(['w','ليس لديك صلاحية لاستلام مرتجعات امر البيع']);
 		
 	}
 },
 //==================وظايف عرض تذاكر خدمة العملاء=================
 
 /**
 * معالجة النقر للمستخدم غير الإداري.
 */
handleNonAdminUser = function (oid, OSid, OStat, OPrint, OSMsgCS) {
    if ((OSid == 0 && OStat < 1) || (!OSid && OStat == 1 && !OPrint) ||(OStat==5)) {
       alert(oid);
        openTiketSupport(oid);
    } else if (OSid != 0 && OSMsgCS <1) {
        showClosedTicketAlert(oid, OSid,OPrint,OStat);
    } else if (OSid > 0 || OSMsgCS > 0) {
        viewTicket(oid, OSid);
    }
}

/**
 * معالجة النقر للمستخدم الإداري.
 */
handleAdminUser = function (oid, OSid, OStat, OPrint, OSMsgCS, oidData) {
   
    if (OSid > 0 && OSMsgCS > 0) {
    	
        viewTicket(oid, OSid, true);
    } else {
        if (OStat != 1 || (OStat == 1 && OPrint == 0)) {
            actionSupport(oidData, OSid);
            refreshTable();
        } else if (OPrint == 1) {
            processOrderExchange(oid);
        } else {
            showWarning("انتبه", "!لايمكن تحويل اوامر البيع قبل طباعتها");
        }
    }
}

/**
 * عرض تنبيه عند وجود طلب مغلق.
 */
showClosedTicketAlert = function (oid, OSid,opr,oSt) {
    Swal.fire({
        text: "طلبك الاخير تم اغلاقه من قبل الاداره",
        icon: "info",
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: 'عرض اخر طلب ',
        confirmButtonText: 'انشاء طلب جديد',
        didOpen: () => {
            const btnApplay = Swal.getConfirmButton();
            if ((opr == 1 && oSt >= 1 && oSt != 5)) {
                btnApplay.disabled = true;
                Swal.showValidationMessage('انتبه حالة الامر لا تسمح بإنشاء طلب جديد');
            }
        }
    }).then((result) => {
        if (result.isConfirmed){
            openTiketSupport(oid);
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            ticketView(oid, OSid, ptype);
        }
    });
}

/**
 * عرض التذكرة.
 */
viewTicket = function (oid, OSid, isAdmin = false) {
    new Promise((resolve) => {
        resolve(ticketView(oid, OSid, ptype));
    }).then(() => {
        if (isAdmin) {
            refreshTable();
        }
    });
}

/**
 * تحديث الجدول.
 */
refreshTable = function () {
    updateOrders();
}

/**
 * تنفيذ عملية تحويل الطلب.
 */
processOrderExchange = function (oid) {
    const result = oSelect(oid, 'Exchange');
    //if (result === 'osOpen') {
        $(this).children('span').remove();
   /* } else {
        showWarning("انتبه", "!لايمكن تحويل اوامر البيع قبل طباعتها");
    }*/
}

/**
 * عرض رسالة تحذيرية.
 */
showWarning = function (title, message) {
    Swal.fire(title, message, 'warning');
}
// تحديث واجهة المستخدم
function updateDataTable(newData) {
    let table = $("#tbl").DataTable();
	newData.sort((a, b) => Number(a.oid) - Number(b.oid));
    let existingData = table.rows().data().toArray();
    let newDataMap = new Map(newData.map(row => [row.oid, row]));
    let page = table.page(); // حفظ رقم الصفحة الحالية
    existingData.forEach((row, index) => {
        if (!newDataMap.has(row.oid)) {
            table.row(index).remove();
        }
    });

    let filteredData = newData.filter(newRow => {
        let existingRow = existingData.find(row => row.oid === newRow.oid);
        return !existingRow || JSON.stringify(existingRow) !== JSON.stringify(newRow);
    });

    if (filteredData.length > 0) {
        table.rows.add(filteredData).draw(false);
        table.page(page).draw(false); // العودة لنفس الصفحة بعد التحديث
    }
    table.order([0, 'desc']).draw();
    //table.columns.adjust();
}
// تحديث شريط التقدم
function updateProgressBar(progress) {
    $('.progress-container').show();
    let progressBar = document.getElementById("progressBar");
    progressBar.style.width = progress + "%";
    progressBar.innerText = progress + "%";
    if (progress >= 100) {
        $('#tbl').show();
        progressBar.style.background = "linear-gradient(90deg, #2196F3, #64B5F6)";
        setTimeout(()=>{$('.progress-container').fadeOut(600);},900)
    }
}
// الحصول على أحدث `LAST_UPDATE`
function getLastUpdate(/*data*/) {
    //return data.reduce((max, row) => (row.olup > max ? row.olup : max), lastUpdate);
	    const now = new Date();
	const utcDateTime = `${now.getUTCFullYear()}-${String(now.getUTCMonth() + 1).padStart(2, '0')}-${String(now.getUTCDate()).padStart(2, '0')} ` +
	                    `${String(now.getUTCHours()).padStart(2, '0')}:${String(now.getUTCMinutes()).padStart(2, '0')}:${String(now.getUTCSeconds()).padStart(2, '0')}`;
	return utcDateTime;
}
// حذف امر صف من الصفوف 
function removeOrderFromMemory(orderId) {
    let index = orderslist.findIndex(order => order.oid === orderId);
    if (index !== -1) {
        orderslist.splice(index, 1);
    }
	odata = convertOrdersListToOData(orderslist);
}
function removeOrderFromTable(orderId) {
    let table = $('#tbl').DataTable();
    
    table.rows(function(idx, data, node) {
        return data.oid === orderId; // البحث عن الصف المطلوب
    }).remove().draw(false); // الحذف بدون إعادة ضبط الصفحة
}
// تحويل orderslist إلى odata
function convertOrdersListToOData(ordersList) {
    let odata = {};

    ordersList.forEach(order => {
        odata[order.oid] = {
            oid: order.oid,
            shipUid: order.shipUid,
            shipUName: order.shipUName,
            subOid: order.subOid,
            OTName: order.OTName,
            OStat: order.OStat,
            OSuccess: order.OSuccess,
            OExists: order.OExists,
            OPrint: order.OPrint,
            custstat: order.custstat,
            returns: order.returns,
            olup: order.olup,
            OSid: order.OSid,
            OSStatu: order.OSStatu,
            OSMsgc: order.OSMsgc,
            OSMsgCS: order.OSMsgCS,
            OSMsgEnd: order.OSMsgEnd,
            stoid: order.stoid,
            OInvo: order.OInvo,
            ZName: order.ZName,
            SHIP_VAL: order.SHIP_VAL,
            EXCHANGE: order.EXCHANGE,
            EX_SUPID: order.EX_SUPID,
            uid: order.uid,
            PRO_QTY: order.PRO_QTY
        };
    });

    return odata;
}
// دالة لمعالجة الردود وتخزين البيانات بالترتيب
function processResponse(data) {
    if (data.totalRecords !== undefined) totalRecords = data.totalRecords ;
    if (Array.isArray(data.data) && data.data.length > 0) {
        orderslist.push(...data.data);
        
		odata = convertOrdersListToOData(orderslist);
		custodyTotal(data);
    }
    let progress = totalRecords > 0 ? Math.round((orderslist.length / totalRecords) * 100) : 0;
    updateProgressBar(progress);
    if (!data.hasMore) {
        updateDataTable(orderslist);
    }
}
// دالة إعادة المحاولة عند فشل الطلب
async function retryFetch(url, retries = 5) {
    for (let i = 0; i < retries; i++) {
        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
            return await response.json();
        } catch (error) {
            console.warn(`⚠ فشل الطلب ${i + 1}/${retries} لـ: ${url}, المحاولة مجددًا...`);
            await new Promise(resolve => setTimeout(resolve, 500 * (i + 1))); // زيادة مهلة الانتظار بين المحاولات
        }
    }
    throw new Error(`❌ فشل تحميل البيانات بعد ${retries} محاولات: ${url}`);
}

// تحميل البيانات على دفعات
async function fetchOrders(setparm = null) {
	orderslist = [];

	var getparm = {};
	if (typeof setparm === 'object' && setparm !== null) {
	    getparm = setparm;
	} else {
	    getparm = vieworderlistPram;
	}
    isLoading = true;
    //let firstparm = geturl;
    //firstparm['count']=true;
    //console.log(firstparm);
    await setTimeout(function (){
    	$('.table-overlay').fadeIn(200);
    	
    },20);
     
    try {
        // إرسال الطلب الأولي للحصول على إجمالي عدد السجلات
        const firstURL = new URLSearchParams(getparm);
        firstURL.append("offset", 0);
	    firstURL.append("limit", batchSize);
	    firstURL.append("order", 'desc');
	    firstURL.append("last_update", '1970-01-01 00:00:00');
		offset += batchSize;
		
        //إرسال الطلب الأولي وانتظار الاستجابه
        firstResponse = await retryFetch(urlvo + '?' +firstURL.toString(), retryCount);
		
		// فى حالى عدم وجود اى سجلات 
        if (!firstResponse || !firstResponse.totalRecords) {
            setTimeout(function (){updateProgressBar(Math.round(99));},10)
            setTimeout(function (){updateProgressBar(Math.round(100));},50)
            $('.table-overlay').fadeOut(200);
            
            //return 
        }
        //تحديث العرض والذاكرة المؤقته التى تم جلبها من الطلب الاولى
        processResponse(firstResponse);
   } catch (error) {
        console.error("❌ فشل في جلب البيانات الأولية:", error);
        isLoading = false;
        $('.table-overlay').fadeOut(200);
        return;
    }
    try{
	//حفظ اجمالى عدد السجلات 
    totalRecords = firstResponse.totalRecords;
	// حساب عدد الطلبات المطلوبة
    totalRequests = Math.ceil(totalRecords / batchSize);
    //متغير لحفظ الطلبات التى سوف يتم ارسلها دفعه واحده
    let pendingRequests = [];
	//ترسال الطلبات دفعه واحده الى الخادم
    for (let i = 1; i < totalRequests; i++) {
    	await setTimeout(function(){
        let currentOffset = i * batchSize;
        let finalURL = new URLSearchParams(getparm);
        finalURL.append("offset", offset);
	    finalURL.append("limit", batchSize);
	    finalURL.append("last_update", '1970-01-01 00:00:00');
		offset += batchSize

        let requestPromise = retryFetch(urlvo + '?' +finalURL.toString(), retryCount)
            .then(data => {
                if (Array.isArray(data.data) && data.data.length > 0) {
                	processResponse(data);
                }
            })
            .catch(error => {
                console.error(`❌ فشل الطلب ${i}:`, error);
            });

        pendingRequests.push(requestPromise);
    	},i*300);
    }
    // ⏳ انتظار جميع الطلبات حتى تنتهي
    await Promise.allSettled(pendingRequests);
	//تريخ اخر تحديث للشاشة
	
    // تحويل البيانات الى كائن odata
	//odata = convertOrdersListToOData(orderslist);
    // ✅ تحديث الواجهة بعد الانتهاء من جميع الطلبات
    // processResponse(orderslist);
    //updateDataTable(orderslist, odata);
    // تفعيل ارسال طلبات التحديث
    //isLoading = false;
    } catch (error) {
        console.error("❌ فشل في جلب البيانات المتبقيه:", error);
        isLoading = false;
        $('.table-overlay').fadeOut(200);
        return;
    }finally {
    	lastUpdate = getLastUpdate();
    	table.columns.adjust();
    	if (orderslist.length > 0) {
    		odata = convertOrdersListToOData(orderslist);
    	}else{
			odata = {};
		}
        isLoading = false;
        $('.table-overlay').fadeOut(200);
    }
   
};

// اعادة تحميل البيانات على دفعات
/*async function reloadfetchOrders() {
    orderslist = []; // تخزين جميع البيانات
	odata = [];
	lastUpdate = "1971-01-01 00:00:00"; // آخر تحديث للبيانات
	offset = 0; // متغير لتحديد بداية البيانات
	totalRecords = 0; // إجمالي عدد السجلات
	batchSize = 50; // عدد الصفوف لكل دفعة
	totalRequests = 0;
	await updateDataTable(orderslist);
	await fetchOrders();
	updateDataTable(orderslist);
};*/

// تحديث البياناتالمتغيره فقط كل 30 ثانية عند الحاجة
async function updateOrders(loding = true) {
    if (isLoading) return;
    isLoading = true;

    try {
        const urlParams = new URLSearchParams(vieworderlistPram);
        urlParams.append("last_update", lastUpdate);
        const finalURL = urlvo + '?' + urlParams.toString();
		lastUpdate = getLastUpdate();
        if(loding){
	        await setTimeout(function (){
		    	$('.table-overlay').fadeIn(200);
		    },20);
		}
        const response = await fetch(finalURL);
        const data = await response.json();
		
        if (data.data.length > 0) {
            let table = $('#tbl').DataTable();

            data.data.forEach(newRow => {
                // إزالة الصف من الجدول والذاكرة إذا كان موجودًا
                removeOrderFromTable(newRow.oid);
                removeOrderFromMemory(newRow.oid);
                // البحث عن الموضع الصحيح حسب `ocreated`
                let insertIndex = orderslist.findIndex(row => new Date(row.oid) > new Date(newRow.oid));
                
                if (insertIndex !== -1) {
                    orderslist.splice(insertIndex, 0, newRow); // إدراجه قبل الطلب الأحدث منه
                } else {
                    orderslist.push(newRow); // إضافته في نهاية القائمة
                }
				
                // تحديث الجدول فقط للصفوف المتأثرة
                table.row.add(newRow).draw(false);
            });
            lastUpdate = getLastUpdate(data.data); // تحديث `lastUpdate` بعد التعديلات.
			//odata = convertOrdersListToOData(orderslist);
        }
        if(data.dataDelete.length > 0){
        	data.dataDelete.forEach(removRow => {
                // إزالة الصف من الجدول والذاكرة إذا كان موجودًا
                removeOrderFromTable(removRow);
                removeOrderFromMemory(removRow);
			});
        }
		

    } catch (error) {
        console.error("⚠ خطأ أثناء تحديث البيانات:", error);
    } finally {
    	table.columns.adjust();
    	if (orderslist.length > 0) {
    		odata = convertOrdersListToOData(orderslist);
    	}else{
			odata = {};
		}
        isLoading = false;
        $('.table-overlay').fadeOut(200);
    }
}
// **تشغيل تحميل البيانات عند فتح الصفحة**
document.addEventListener("DOMContentLoaded", () => fetchOrders(true));

// تشغيل التحديثات كل 30 ثانيه
setInterval(() => {
    if (!isLoading) {
       // updateOrders(false);
    }
}, 60000); 

/*=======================================
	start event listener
=======================================*/
document.addEventListener("DOMContentLoaded", function() {
	
	$('.select2').select2({
			dir:"rtl",
	        placeholder:'اختار من القائمة',
	        allowClear: true
	});
	/*=========================================================
	 عرض بيانات اوامر البيع بالجدول
	=========================================================*/
	
	table = $('#tbl').DataTable({
			scrollX: true, // تفعيل التمرير الأفقي لتجنب ضغط الجدول
		    autoWidth: true, // تعطيل التحكم التلقائي في عرض الأعمدة
			
	        //scrollY : '70vh',
	        //scrollX : true,
	        //autoWidth: false,
	        data: orderslist,
			orderCellsTop: true, // يضمن أن الترتيب يعمل فقط على الصف العلوي
    		ordering: true, // يسمح بالترتيب ولكن فقط على الهيدر الأول
	        processing: false,
	        serverSide: false,
	        columns: [
	            { 
	                data: 'oid', 
	                type: 'num',
	                //title: 'رقم الطلب',
	                className: 'text-center',
            		//width: '', 
	                render: (data, type, row) => {
	                    let badgcolor = " badge-light ";
	                    badgeColor = row['custstat'] === "FREEZE" ? "badge-red50" :
	                                    row['ORDER_TYPE_ID'] == 1 ? "badge-blue10" :
	                                    row['EXCHANGE'] ? "badge-warning" : "";
	
	                    let orderEx = row['EXCHANGE'] ? 
	                        `<span class="text-danger badge badge-warning" style="position: absolute;top: 16px;right: -5px;">
	                        استبدال للأمر: ${row['EXCHANGE_SUPID']}</span>` : "";
	
	                    return `
	                    <div class="badge ${badgcolor} text-left"  style="font-size: 1.2rem;width:100%;height:auto">
				            <div class="d-flex justify-content-between m-2" style ="position: relative;">
		                        <div class="text-primary"> ${row['subOid']}</div>
	                        	${orderEx}
	                        </div>
	                        <hr class="mb-1 mt-1">
				            <div class="d-flex justify-content-between m-2">
	                        	<div class="badge"><i class="fa-regular fa-user"></i> ${row['BUTTONS'][1]}</div>
	                        </div>
				            <hr class="mb-1 mt-1">
				            <div class="d-flex justify-content-between m-2">
	                        	<div class="badge"><i class="far fa-calendar-plus"></i> ${row['DATE_CREATED']}</div>
	                        </div>
	                    </div>`;
	                }
	            },
	            { 
	                data: 'STA_NAME', 
	               // title: 'حالة الطلب',
	                className: 'text-center',
	                render: (data, type, row) => {
	                	let badgcolor = " badge-light ";
	                	badgeColor = row['custstat'] === "FREEZE" ? "badge-red50" :
	                                    row['ORDER_TYPE_ID'] == 1 ? "badge-blue10" :
	                                    row['EXCHANGE'] ? "badge-warning" : "";
	                                    
	                    return `
	                    <div class="badge ${badgcolor} text-left"  style="font-size: 1.2rem;width:100%;height:auto">
				            <div class="d-flex justify-content-between m-2" style ="position: relative;">
	                        	<div class="badge badge-${row["COLOR_CLASS"]} w-100"><i class="fa-regular fa-clock"></i> ${data}</div>
	                        </div>
	                        <hr class="mb-1 mt-1">
	                        <div class="d-flex justify-content-between m-2" style ="position: relative;">
	                        	<div class="badge badge-light w-100"><i class="fa-solid fa-display"></i> ${row['EXISTS_NAME']}</div>
	                        </div>
	                        <hr class="mb-1 mt-1">
	                        <div class="d-flex justify-content-between m-2" style ="position: relative;">
	                        	<div class="badge badge-light w-100"><i class="fa-solid fa-gears"></i> ${row['SUCCESS_STAT']}</div>
	                    	</div>
	                    </div>`;
	                }
	            },
	            { 
	                data: 'CLIENT_NAME', 
	                //title: 'معلومات العميل',
	                className: 'text-left',
	                render: (data, type, row) => {
	                	let badgcolor = " badge-light ";
	                	badgeColor = row['custstat'] === "FREEZE" ? "badge-red50" :
	                                    row['ORDER_TYPE_ID'] == 1 ? "badge-blue10" :
	                                    row['EXCHANGE'] ? "badge-warning" : "";
	                                    
	                    return `
	                    <div class="badge ${badgcolor} text-left"  style="font-size: 1.2rem;width:100%;height:auto">
				            <div class="d-flex justify-content-between m-2" style ="position: relative;">
								<div class="badge"><i class="fas fa-user"></i> ${data}</div>
	                            <div class="badge"><i class="fas fa-mobile-alt"></i><a href="tel:${row['TELEPHONE']}"> ${row['TELEPHONE']}</a></div>
	                        </div>
	                        <hr class="mb-1 mt-1">
	                        <div class="d-flex justify-content-between m-2" style ="position: relative;">
	                        	<div class="badge"><i class="fas fa-shopping-cart"></i> المنتجات ${row['PRODUCTS_COUNT']} | الكمية ${row['PRO_QTY']} | التكلفة ${row['PRICE']}</div>
	                        </div>
	                        <hr class="mb-1 mt-1">
	                        <div class="d-flex justify-content-between m-2">
	                            <div class="badge"><i class="fas fa-truck-fast"></i> ${row['ZName']}</div>
	                            <div class="badge"><i class="fa fa-map-location-dot"></i><span class="text-primary"> ${row["GOV_NAME"]} - ${row["CI_NAME"]} </span></div>
	                        </div>
	                    </div>`;
	                }
	            },
	            { 
	                data: 'DELIVERY_DATE', 
	                //title: 'تواريخ الشحن والتسليم',
	                className: 'text-center',
	                render: (data, type, row) => {
	                	let badgcolor = " badge-light ";
	                	badgeColor = row['custstat'] === "FREEZE" ? "badge-red50" :
	                                    row['ORDER_TYPE_ID'] == 1 ? "badge-blue10" :
	                                    row['EXCHANGE'] ? "badge-warning" : "";
	                                    
	                    return `
	                    <div class="badge ${badgcolor} text-left"  style="font-size: 1.2rem;width:100%;height:auto">
				            <div class="d-flex justify-content-between m-2" style ="position: relative;">
	                        	<div class="badge badge-light w-100"><i class="fa-solid fa-truck-fast"></i> ${data}</div>
	                        </div>
	                        <hr class="mb-1 mt-1">
	                        <div class="d-flex justify-content-between m-2" style ="position: relative;">
	                        	<div class="badge badge-light w-100"><i class="fa-solid fa-right-from-bracket"></i> ${row['SHIPPING_DATE']}</div>
	                        </div>
	                        <hr class="mb-1 mt-1">
	                        <div class="d-flex justify-content-between m-2" style ="position: relative;">
	                        	<div class="badge badge-light w-100"><i class="fa-solid fa-handshake"></i> ${row['DELIVERED_DATE']}</div>
	                        </div>
	                    </div>`;
	                }
	            },
	            {
	                data : 'BUTTONS',
	                //title: 'تحرير',
	                orderable: false,
	                className: 'text-center text-nowrap',
	                render: (data, type, row) => {
	                	 return row['BUTTONS'][0];
	                }
	            },
	            {data:'SHIPPING_DATE', visible:false, searchable: true}, //5
				{data:'DELIVERED_DATE', visible:false, searchable: true},//6
				{data:'DATE_CREATED', visible:false, searchable: true},//7
				{data:'DELIVERY_DATE', visible:false, searchable: true},//8
				{data:'Zid', visible:false, searchable: true},
				{data:'SHIPPING_TYPE', visible:false, searchable: true},
				{data:'ORDER_TYPE_ID', visible:false, searchable: true},
				{data:'PRODUCT_IDS', visible:false, searchable: true},
				
	        ],
	       // fixedHeader: true, // تثبيت الهيدر أثناء التمرير
		    columnDefs: [
		        { targets: '_all', className: 'text-center' }
		    ],
		    initComplete: function () {
		        this.api().columns.adjust().draw(); // ضبط الأعمدة بعد تحميل البيانات
		        
		    },
    
	        buttons: [
		        {
		            text: 'تحديث البيانات',
		            action: function ( e, dt, node, config ) {
		                $('.table-overlay').fadeIn(200); 
		                updateOrders();
		            }
		        }
		    ],
	        
	        pageLength: 10,
	       
	                    
	}).on('draw', function() {
		
		//$('.table-overlay').fadeOut(200); 
		// هذه سيتم تشغيله بعد اكتمال عرض البيانات
		$('#hid').show();
		$('#ovarlay').hide();
		$('#contentbody').removeClass('overlay-wrapper');
		$('#contentbody').addClass('wrapper');
		if(Go === "OrdersCustody"){
			$('.OLCBtn .btnTL').hide();
			$('.OLCBtn .btnBL').hide();
			if(!$('#Olhbtn li a.active.show').length){
				$('#OLAll').addClass('active show');
			}
		}
		//custodyTotal(orderslist);
		if(odata.length){
			//odata = odata
		}
	});
	
	$('#tbl').parent().css('position', 'relative') // لضمان ظهور الـ overlay فوق الجدول
	$('#tbl tbody').before('<div class="table-overlay" style="display: none;"><i class="fa fa-spinner fa-spin spinner"></i></div>');
	
	
    // **البحث داخل كل عمود عند الكتابة في الحقل**
    /*$('#tbl thead tr:eq(1) th input.column-search').on('keyup change', function() {
        let columnIndex = $(this).parent().index(); // تحديد رقم العمود بناءً على مكان حقل البحث
        table.column(columnIndex).search(this.value).draw();
    });*/

	/*$('#tbl thead tr:eq(1) th .column-search').on('keyup change', function () {
        let searchValue = this.value.trim();
        let columnIndex = $(this).closest('th').index(); // تحديد العمود بشكل صحيح
        
        table.column(columnIndex)
            .search(searchValue ? `(?=.*${searchValue})` : '', true, false) // البحث بالتعبير النمطي
            .draw();
    });*/
	
	let searchTimeout;
	
	$('#tbl thead tr:eq(1) th .column-search, #tbl thead tr:eq(1) th .select-search').on('keyup change', function () {
	    clearTimeout(searchTimeout);
	    let inputField = $(this);
	    let columnIndex = inputField.closest('th').index();
		
	    let isSelect = inputField.is('select');
		
	    // إظهار التأثير على tbody
	    $('.table-overlay').fadeIn(200); 
	
	    searchTimeout = setTimeout(function () {
	        let searchValue = inputField.val().trim();
	        
	        table.column(columnIndex)
	            .search(searchValue ? `(?=.*${searchValue})` : '', true, false)
	            .draw();
	
	        // إخفاء التأثير بعد انتهاء البحث
	        $('.table-overlay').fadeOut(200);
	    }, isSelect ? 0 : 300); // تأخير البحث في input، وتنفيذ فوري في select
	});
	
    // عند اختيار نطاق تاريخ معين
	$('#daterange').on('apply.daterangepicker', function (ev, picker) {
	    $.fn.dataTable.ext.search = []; // إعادة تعيين أي فلترة سابقة
	
	    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
		    var selectedIcon = $('#selectedIcon').attr('class'); // جلب الأيقونة الحالية
		    var columnIndex = 7; // تأكد من اختيار العمود الصحيح حسب الحاجة
		    
		    if (selectedIcon.includes('fa-calendar-plus')) {
	            columnIndex = 7; // البحث في العمود الأول عند اختيار "تاريخ الإنشاء"
	        }else if (selectedIcon.includes('fa-truck-fast')) {
	            columnIndex = 8; // البحث في العمود الأول عند اختيار "تاريخ الشحن"
	        }else if (selectedIcon.includes('fa-right-from-bracket')) {
	            columnIndex = 5; // البحث في العمود الأول عند اختيار "تاريخ الخروج"
	        }else if (selectedIcon.includes('fa-handshake')) {
	            columnIndex = 6; // البحث في العمود الأول عند اختيار "تاريخ التسليم"
	        }
	        
		    var cellNode = table.cell(dataIndex, columnIndex).node();
		    var dateText = $(cellNode).text().trim(); // جلب النص مباشرة من الخلية
		
		    var startDate = picker.startDate.format('YYYY/MM/DD');
	        var endDate = picker.endDate.format('YYYY/MM/DD');
	
		    
		    // التحقق من صلاحية التاريخ وتصفية البيانات في النطاق المحدد
		    if (dateText !== "غير متاح" && dateText !== "1970/01/01" && moment(dateText, 'YYYY/MM/DD').isValid()) {
		        var rowDate = moment(dateText, 'YYYY/MM/DD');
		       // alert(rowDate)
		        return rowDate.isBetween(startDate, endDate, null, '[]'); // يشمل الحدين
		    }
		
		    return false; // استبعاد الصف إذا لم يكن التاريخ صالحًا
		});
	
		// إعادة رسم الجدول لتطبيق الفلترة
	    table.draw();
	});

	// عند مسح التاريخ، يتم إلغاء الفلترة
    $('.clear-daterange').on('click', function () {
	    $.fn.dataTable.ext.search = []; // إزالة جميع الفلاتر المخصصة
	    table.draw(); // إعادة رسم الجدول لإظهار جميع البيانات
	});

	$('#SelectUserDelivery').select2({
		dir:"rtl",
		placeholder:'اختار المندوب',
		allowClear: true
	});
	
	if ($("#SelectUserDelivery").length) {
		shipmentUser = sendRequestGET(`${urlApi}?CONTROL=UserListForType&utype=delivery`);
		let options = '<option value="">عرض الكل</option>';
		for (let key in shipmentUser) {
			options += `<option value="${shipmentUser[key]['ID']}">${shipmentUser[key]['NAME']}</option>`;
		}
		$("#SelectUserDelivery").html(options);
	}
	
	btnhead();
	filterOrderlist(); 
	$(document).on("click", "#btnOview", function() {
	    
	    showSpinner();
	    setTimeout(()=>{
		    let btnapp = "";
		    $(this).parents('.tableAllBtn').find('.ovbtn').each(function() {
		    	if($(this).is(':visible')){
		    		btnapp += $(this).parents('td').html();
		    	}
		    });
		    
		    // إنشاء عنصر div جديد
		    let btnheadel = '<div ></div>';
		    if(ptype != 1){
				displayOrderViewData([$(this).attr('data-id')],btnapp);
			}else if(userlogin_data['uScript']['Admin'][orderlistDir]['View_order'] == "true"){
				displayOrderViewData([$(this).attr('data-id')],btnapp);
			} else {
				Toast_fire(['w','ليس لديك صلاحية عرض بيانات امر البيع']);
			}
	    },1);
	});
	$(document).on("click", "#btnOedit", function () {
		const oedata = odata
		let oid = $(this).attr('data-id');
		let on = oedata[oid]['subOid'];
		obj = orderdataview(oid);
		obj = obj[oid];
		function Oedit(){
			localStorage.removeItem('newOrder');
			localStorage.removeItem('itemarr');
			localStorage.setItem('newOrder', JSON.stringify(obj['orderData']));
			localStorage.setItem('itemarr', JSON.stringify(obj['orderPro']));
			localStorage.setItem('orderlog', '['+oid+']'); 
			localStorage.setItem('editON', JSON.stringify([oid,on])); 
			window.location.assign("?Go=EditOrder&ty="+ptype+"&on="+oid+"&sid="+on);
		}
		if(ptype == 1){
	    	if(userlogin_data['uScript']['Admin'][orderlistDir]['Edit_order'] == "true"){
		        Oedit();
		    }else{
		        Toast_fire(['w','ليس لديك صلاحية التعديل على امر البيع']);
		    }
		}else{
		    if(obj['orderData']['order_status'] <=1 && obj['orderData']['print'] ==0){
	    		Oedit();
	    	} else {
	    	    Toast_fire(['w','حالة امر البيع لا تسمح بالتعديل قد يكون امر البيع قيد التجهيز او قيد التسليم او فى اى حالة من حالات التسليم الفعلى']);
	    	}
		}
	});
	table.on("click", "#btnNewOrderExchange", function () {
		var rowData = table.row($(this).closest('table').closest('td').closest('tr')).data();
		
		
		const orderDate = new Date();
		const oedata = odata
		let oid = $(this).attr('data-id');
		let on = oedata[oid]['subOid'];
	//	objAllOrders = orderdataview(oid);
	//	objAllDataOrder = objAllOrders[oid];
	//	objDataOrder = objAllDataOrder.orderData;
		//console.log(objDataOrder);
		const objOEx = {
			"uid":rowData.uid,
			"orderby":rowData.ORDER_BY_ID,
			"uname":rowData.CLIENT_NAME,
			"umail":rowData.CLIENT_EMAIL,
			"oty":rowData.ORDER_TYPE_ID,
			"telid":rowData.TEL_ID,
			"telstr":rowData.TELEPHONE,
			"addid":rowData.ADDRESS_ID,
			"addstr":rowData.ADDRESS,
			"shiplow":rowData.SHIPPING_LOWEST,
			"shipmax":rowData.SHIPPING_REAT,
			"stoid":rowData.stoid,
			"cityid":rowData.CI_ID,
			"cliComment":"",
			"shipval":rowData.SHIP_VAL,
			"shipDays":rowData.SHIPPING_TIME_MAX,
			"shipWaiting":rowData.SHIPPING_TYPE,
			"shipDate": new Date(addDays(orderDate.toLocaleDateString('en-US', "yy-mm-dd"),rowData.SHIPPING_TIME_MAX)),
			"orderExchange":rowData.oid
		};
		var log =`*تم انشاء نسخه من امر بيع رقم ${rowData['subOid']} للعميل ${rowData['CLIENT_NAME']} رقم الهاتف ${rowData['TELEPHONE']} العنوان ${rowData['ADDRESS']}`;
		console.log(log)
		function NewOrderExchange(){
			localStorage.removeItem('newOrder');
			localStorage.removeItem('itemarr');
			localStorage.setItem('newOrder', JSON.stringify(objOEx));
			localStorage.setItem('orderlog', JSON.stringify([null,log])); 
			localStorage.setItem('NewOExchange', JSON.stringify([rowData.oid,rowData.subOid])); 
			
			window.location.assign("?Go=NewOrder&ex="+rowData.subOid+"&ty="+ptype);
		}
		
		if(ptype == 1){
	    	if(userlogin_data['uScript']['Admin'][orderlistDir]['Edit_order'] == "false"){
		        NewOrderExchange();
		    }else{
		        Toast_fire(['w','ليس لديك صلاحية التعديل على امر البيع']);
		    }
		}else{
		    if(rowData.OStat >2){
	    		NewOrderExchange();
	    	} else {
	    	    Toast_fire(['w','حالة امر البيع لا تسمح بالتعديل قد يكون امر البيع قيد التجهيز او قيد التسليم او فى اى حالة من حالات التسليم الفعلى']);
	    	}
		}
	});
	/*$(document).on("click", "#btnOdel", async function () {
	    try {
	        const oid   = $(this).attr('data-id');
	        const orderdata = odata[oid];
	            last_update = orderdata['olup'],
	            confirmText = 'متأكد',
	            cancelText = 'اغلاق',
	            swalOptions = {
	                title: 'هل انت متأكد من حذف هذا الامر؟',
	                text: "يرجى العلم انه فى حال الموافقة سوف يتم حذف امر البيع بجميع بياناته ولا يمكن الرجوع اليها مره اخرى!",
	                icon: 'warning',
	                showCancelButton: true,
	                showDenyButton: true,
	                confirmButtonColor: '#3085d6',
	                cancelButtonColor: '#d33',
	                cancelButtonText : cancelText,
	                confirmButtonText: confirmText,
	                denyButtonText: 'الغاء الطلب ',
	            };
	        if (ptype != 1) {
	            let result = await Swal.fire(swalOptions);
	            if (result.isConfirmed) {
	                let inputCaptcha = await showCaptcha();
	                let rqr = await sendRequest(urlControl, {'CONTROL': 'delOrder', 'oid': oid, 'lup': last_update}, false);
	                updateOrders();
	                if($('.DOV').is(':visible')){
	                    $('.btnback').click();
	                }
	                Swal.fire(rqr[1], rqr[2], rqr[0]);
	            }
	        } else {
	            let result = await Swal.fire(swalOptions);
	            if (result.isConfirmed) {
	                let rqr = sendRequest(urlControl, {'CONTROL': 'delOrder', 'oid': oid, 'lup': last_update}, false);
	                
	                if($('.DOV').is(':visible')){
	                    $('.btnback').click();
	                }
	                Swal.fire(rqr[1], rqr[2], rqr[0]);
	                updateOrders();
	            }else if (result.isDenied) {
	                let rqr = sendRequest(urlControl, {'CONTROL': 'unDelOrder', 'oid': oid, 'lup': last_update}, false);
	                if($('.DOV').is(':visible')){
	                    $('.btnback').click();
	                }
	                Swal.fire(rqr[1], rqr[2], rqr[0]);
	                updateOrders();
	            }
	        }
	    } catch (error) {
	        console.log(error);
	    }
	});*/
	$(document).on("click", "#btnOdel", async function () {
	    try {
	        const oid = $(this).attr('data-id');
	        const orderdata = odata[oid];
	        let last_update = orderdata['olup'];
			//console.log(orderdata)
	        // إنشاء محتوى SweetAlert مع Checkbox
	        let swalContent = `
	            <p>يرجى العلم أنه في حال الموافقة سيتم حذف أمر البيع بجميع بياناته ولا يمكن الرجوع إليها مرة أخرى!</p>
	            <div style="display: flex; align-items: center; margin-top: 10px;">
	                <input type="checkbox" id="confirmDeleteCheckbox" style="margin-right: 5px;">
	                <label for="confirmDeleteCheckbox">أنا أوافق على حذف الطلب</label>
	            </div>
	        `;
	
	        let swalOptions = {
	            title: 'هل أنت متأكد من حذف هذا الأمر؟',
	            html: swalContent,
	            icon: 'warning',
	            showCancelButton: true,
	            showDenyButton: ptype == 1, // إظهار زر "إلغاء الطلب" فقط عندما يكون ptype == 1
	            confirmButtonColor: '#3085d6',
	            cancelButtonColor: '#d33',
	            cancelButtonText: 'إغلاق',
	            confirmButtonText: 'متأكد',
	            denyButtonText: 'إلغاء الطلب',
	            didOpen: () => {
	                let confirmButton = Swal.getConfirmButton();
	                confirmButton.disabled = true; // تعطيل زر التأكيد في البداية
	                
	                // التحقق من الـ Checkbox لتفعيل زر التأكيد
	                document.getElementById("confirmDeleteCheckbox").addEventListener("change", function () {
	                    confirmButton.disabled = !this.checked;
	                });
	
	                // تأخير تفعيل زر التأكيد لمدة 10 ثوانٍ
	                setTimeout(() => {
	                    confirmButton.disabled = false;
	                }, 10000);
	            }
	        };
	
	        let result = await Swal.fire(swalOptions);
	
	        if (result.isConfirmed) {
	            let rqr = await sendRequest(urlControl, { 'CONTROL': 'delOrder', 'oid': oid, 'lup': last_update }, false);
	            updateOrders();
	
	            if ($('.DOV').is(':visible')) {
	                $('.btnback').click();
	            }
	
	            Swal.fire(rqr[1], rqr[2], rqr[0]);
	        } else if (result.isDenied && ptype == 1) {
	            let rqr = await sendRequest(urlControl, { 'CONTROL': 'unDelOrder', 'oid': oid, 'lup': last_update }, false);
	            updateOrders();
	
	            if ($('.DOV').is(':visible')) {
	                $('.btnback').click();
	            }
	
	            Swal.fire(rqr[1], rqr[2], rqr[0]);
	        }
	
	    } catch (error) {
	        console.log(error);
	    }
	});	
	table.on("click", "#AdminSupport", async function (){
		    try {
		    	var rowData = table.row($(this).closest('table').closest('td').closest('tr')).data();
		    	
		        const oid = rowData.oid;// $(this).attr('data-id'); 
		        const ordata =  rowData;
		        let oidData = {};
		        oidData[oid] = ordata,
		         x = rowData;
		        const osSt = x.OSStatu,
		         oSt  = x.OStat,
		         opr  = x.OPrint,
		         osid = x.OSid, 
		         osex = x.OExists,
		         msgc = x.OSMsgc,
		         msgcs= x.OSMsgCS;
		        if(ptype == 1){
		        	//فى حالة الادارى
		        	
		            if( osid > 0 && msgcs >0 || msgc>0){
		                //فى حالة وجود طلب مفتوح او رسائل غير مقروئة
		                let n = new Promise((resolve) => {
		                    resolve(ticketView(oid,osid,ptype));
		                }).then((resolve)=>{
		                    if(resolve){
		                        updateOrders();
		                        $(this).children('span').remove();
		                    }
		                });
		            }else{
		                Swal.fire({
		                    text: "لايوجد اى مراسلات مفتوحه حاليا \n يمكنك فتح قناة مراسلة جديده من خلال الضغط على انشاء قناة مراسلات جديدة او عرض المراسلات القديمة من خلال الضغط عل عرض اخر طلب",
		                    icon: "info",
		                    showCancelButton: true,
		                    confirmButtonColor: '#3085d6',
		                    cancelButtonColor: '#d33',
		                    cancelButtonText: 'عرض اخر طلب ',
		                    confirmButtonText: 'انشاء طلب جديد',
		                    didOpen : ()=>{
		                        
		                        if(opr ==1 || oSt>1){
		                            const btnApplay = Swal.getConfirmButton();
		                            btnApplay.disabled = true;
		                            Swal.showValidationMessage('انتبه حالة الامر لا تسمح بإنشاء طلب جديد')
		                        }
		                        if(osid ==0){ 
		                            const btnCancel = Swal.getCancelButton();
		                            btnCancel.disabled = true;
		                            Swal.showValidationMessage('لايوجد مرسلات سابقة لفتحاها')
		                        }
		                    }
		                }).then((result) => {
		                    if(result.isConfirmed && !opr == 0 && oSt<=1){
		                       openTiketSupport(oid);
		                   // }else if(){
		                        
		                    } else if(result.dismiss === Swal.DismissReason.cancel){
		                       ticketView(oid,osid,ptype);
							    //table.clear().rows.add(data).draw().on('draw', function() {
								    // هذه الكود سيتم تشغيله بعد اكتمال عرض البيانات
								    //custodyTotal(orderslist);
								//});
		                    }
		                })
		            }
		        }
		    } catch (error) {
		        console.log(error);
		    }
		})
	/*$(document).on("click", "#btnOservice", function () {
	   
	    try {
	        const oid = $(this).attr('data-id'), 
	        odata = odata[oid];
	        let oidData = {};
	        oidData[oid] = odata,
	         x = odata;
	        const osSt = x.OSStatu,
	         oSt  = x.OStat,
	         opr  = x.OPrint,
	         osid = x.OSid, 
	         osex = x.OExists,
	         msgc = x.OSMsgc,
	         msgcs = x.OSMsgCS;
	        //فى حالة المنسق او اى نوع غير الادارى
	        if(ptype != 1){
	            if((osid ==0 && oSt<1) || (!osid && oSt==1 && !opr)){
	          //فى حال عدم وجود اى طلبات مسبق لامر البيع .. يتم فتح طلب جديد
	    	    	openTiketSupport(oid);
	            } else if(osid != 0 &&  !msgcs){
	          //فى حال وجود طلب مسبق وبحالة مغلق
	                Swal.fire({
	                    text: "طلبك الاخير تم اغلاقه من قبل الاداره",
	                    icon: "info",
	                    showCancelButton: true,
	                    confirmButtonColor: '#3085d6',
	                    cancelButtonColor: '#d33',
	                    cancelButtonText: 'عرض اخر طلب ',
	                    confirmButtonText: 'انشاء طلب جديد',
	                    didOpen : ()=>{
	                        
	                        if(opr || oSt>1){
	                            const btnApplay = Swal.getConfirmButton();
	                            btnApplay.disabled = true;
	                            Swal.showValidationMessage('انتبه حالة الامر لا تسمح بإنشاء طلب جديد')
	                        }
	                    }
	                }).then((result) => {
	                    if(result.isConfirmed && !opr && oSt<=1){
	                       openTiketSupport(oid);
	                   // }else if(){
	                        
	                    } else if(result.dismiss === Swal.DismissReason.cancel){
	                       ticketView(oid,osid,ptype);
	                       //table.clear().rows.add(data).draw().on('draw', function() {
							    // هذه الكود سيتم تشغيله بعد اكتمال عرض البيانات
							    //custodyTotal(orderslist);
							    
							//});
	                    }
	                })
	            } else if(osid && msgcs){
	          //فى حال وجود طلب مسبق وبحالة مفتوح
	                let n = new Promise((resolve) => {
	                    resolve(ticketView(oid,osid,ptype));
	                }).then((resolve)=>{
	                    if(resolve){
	                        $(this).children('span').remove();
	                    }
	                });
	            } 
	        } else if(ptype == 1){
	        //فى حالة الادارى
	            if( osid>0 && msgcs>0 ){
	                //فى حالة وجود طلب مفتوح او رسائل غير مقروئة
	                let n = new Promise((resolve) => {
	                    resolve(ticketView(oid,osid,ptype));
	                }).then((resolve)=>{
	                    if(resolve){
	                        updateOrders();
	                        $(this).children('span').remove();
	                    }
	                });
	                
	            }else{
	                
	                if(oSt!=1 || (oSt==1 && !opr)){
	                    actionSupport(oidData,osid);
	                    updateOrders();
	                    
	                }else{
	                    
	                    if(opr){
	                        let rq = oSelect(oid,'Exchange');
	                        $(this).blur();
	                        $(this).attr('disabled',true);
	                        if(rq === 'osOpen'){
	                            $(this).children('span').remove();
	                        }
	                    }else{
	                        swal.fire(
	                            'انتبة',
	                            ' !لايمكن تحويل اوامر البيع قبل طباعتها',
	                            'warning'
	                            )
	                    }
	                }
	            }
	        }
	    	
	    } catch (error) {
	        console.log(error);
	    }
	});
*/	

	table.on("click", "#btnOservice", async function () {
		
		alert(); 
	    try {
	    	var rowData = table.row($(this).closest('table').closest('td').closest('tr')).data();
	        //const oid = $(this).attr('data-id');
	        //const ordata =  odata[oid];
	        const oidData = { [rowData.oid]: rowData };
	        
	        
	        // استخراج البيانات من الكائن
	        const { oid, OSStatu, OStat, OPrint, OSid, OExists, OSMsgc, OSMsgCS } = rowData;
	
	        // التحقق من نوع المستخدم (إداري أو غير إداري)
	        if (ptype !== 1) {
	            await handleNonAdminUser(oid, OSid, OStat, OPrint, OSMsgCS);
	        } else {
	            await handleAdminUser(oid, OSid, OStat, OPrint, OSMsgCS, oidData);
	        }
	        
	    } catch (error) {
	        console.error("Error handling button click:", error);
	    } finally {
	    	updateOrders();
	    }
	});

	$(document).on("click", ".btnosClose", function () {
	    closedTiketSuport($(this).attr('data-osid'),true);
	});
	$(document).on("click", "#swlTicket .inmsgtext", function () {
	    const el = $(this);
	    el.keypress((e)=>{
	        if(e.which == 13){
	            try {
	                
	                let osid = el.attr('data-id'),
	                ostxmsg = $.trim(el.val());
	        
	                if (ostxmsg != '') {
	                    const rq = sendMessage(osid,ostxmsg,ptype);
	                    el.val('');
	                } else {
	                    el.val('');
	                }
	                el.focus()
	            } catch (error) {
	                console.log(error);
	            }
	            return false
	        }
	    })
	})
	$(document).on("click", "#swlTicket .ossmsg", function () {
	    try {
	        const e = $(this).parent('span').parent('div').children('input'),
	        osid = $(this).attr('data-id'),
	        ostxmsg = $.trim(e.val());
	
	        if (ostxmsg != '') {
	            const rq = sendMessage(osid,ostxmsg,ptype);
	            e.val('');
	        } else {
	            e.val('');
	        }
	        e.focus()
	    } catch (error) {
	        console.log(error);
	    }
	});
	$(document).on("click", "#btnSelectOLiest", async function () {
	    const oid =  $(this).attr('data-id');
	    const rq  = oSelect(oid,'Print');
	    if(rq){
	        $(this).blur();
	        $(this).attr('disabled',true);
	        if(rq === 'osOpen'){
	            $(this).children('span').remove();
	        }
	    };
	    
	});
	
	$(document).on("click", "#btnSelectOShipp", async function () {
	    const oid = $(this).attr('data-id')
	    const rq  = oSelect(oid,'Shipp');
	    if(rq){
	        $(this).blur();
	        $(this).attr('disabled',true);
	        if(rq === 'osOpen'){
	            $(this).children('span').remove();
	        }
	    };
	});
	
	$(document).on("click", "#btnPrint",async function () {
	    const html = $('#swlSlectOrdersTmp').html();
	    const obj = {
	        "pdf": "INVOICES",
	        "odata" : orderidarr.Print
	    }
	    
	  //function's Callback
	    const didOpenCallback = ()=>{
	        const el = $(".swal2-html-container .ordersSelectedList");
	        let i=1;
	        for (const key in orderidarr.Print) {
	            let tc="",
	                icon =`<i class="fa-solid fa-lg text-success fa-check"></i>`;
	           
	            if(orderidarr.Print[key]['OSMsgCS']>0){
	                tc= "text-danger";
	                icon =`<i class="fa-solid fa-lg text-danger fa-xmark" style="color: #ff0000;"></i>`;
	            }
	            el.append(
	                `<tr class="${tc}">
	                    <td>${i}</td>
	                    <td>${orderidarr.Print[key].subOid}</td>
	                    <td>${orderidarr.Print[key].ZName}</td>
	                    <td>${icon}</td>
	                </tr>`
	            );
	        }
	    }
	    const confirmCallback = (data) => {
	        // البيانات التي سترسلها في طلب POST
	        if(!Object.keys(orderidarr.Print).length){
	            const dt = odata
	            const dtoid = dt;
	            for(let x in dtoid){
	               // if(dtoid[x]['OPrint'] == 0){
	                	var {subOid,ZName,OSMsgCS,olup}=dtoid[x]
	                    orderidarr.Print[x] = {"subOid":subOid , "ZName":ZName ,"OSMsgCS":OSMsgCS,'olup':olup};
	               // }
	            }
	        }
	        
	        
	        
	        
	        
	        if (Object.keys(orderidarr.Print).length > 0) {
			    // إعداد البيانات التي سيتم إرسالها
			    const obj = {
			        "pdf": "INVOICES",
			        "odata": orderidarr.Print
			    };
			
			    // إرسال الطلب باستخدام fetch
			    fetch(urlprint, {
			        method: 'POST',
			        headers: { 'Content-Type': 'application/json' },
			        body: JSON.stringify(obj)
			    })
			    .then(response => {
			        if (!response.ok) {
			            throw new Error(`HTTP error! Status: ${response.status}`);
			        }
			        return response.blob();
			    })
			    .then(blob => {
			        const pdfUrl = URL.createObjectURL(blob);
			        window.open(pdfUrl, 'INV.pdf');
			
			        // تحرير الذاكرة بعد فتح الملف
			        setTimeout(() => URL.revokeObjectURL(pdfUrl), 5000);
			    })
			    .catch(error => {
			        console.error("Error fetching PDF:", error);
			        Swal.fire('خطأ', 'حدث خطأ أثناء طباعة الفاتورة، يرجى المحاولة مرة أخرى.', 'error');
			    })
			    .finally(() => {
			        updateOrders();
			        $('#btnPrint .navbar-badge').html('');
			        $('.lastbtn').attr('disabled', false);
			        orderidarr.Print = {};
			    });
			} else {
			    Swal.fire(
			        'نأسف!',
			        'كل الأوامر المعروضة تم طباعتها مسبقًا، من فضلك قم بتحديد الأوامر المراد طباعتها',
			        'warning'
			    );
			}
	        /*if(Object.keys(orderidarr.Print).length>0){
	            // البيانات التي سترسلها في طلب POST
	            //const x = orderdataview(orderidarr.Print);
	            const obj = {
	                "pdf": "INVOICES",
	                "odata" : orderidarr.Print
	            };
	            const RQ = fetch(urlprint, {
	                method: 'POST',
	                headers: {'Content-Type': 'application/json'}
	                body: JSON.stringify(obj)
	            });
	            RQ.then((res) => res.blob()).then((blob) => window.open(URL.createObjectURL(blob), 'INV-1234.pdf'));
	            RQ.then(table.clear().rows.add(data).draw());
	            $('#btnPrint .navbar-badge').html('');
	            $('.lastbtn').attr('disabled',false);
	            orderidarr.Print={};
	        }else{
	            Swal.fire(
	                'نأسف!',
	                'كل الاومر المعروضه تم طباعتها مسبقا من فضلك قم بتحديد الاوامر المراد طباعتها',
	                'warning'
	            );
	        }*/
	    };
	    //الغاء قايمة الاوامر المختارة
	    const denyCallback = ()=>{
	        $('#btnPrint .navbar-badge').html('');
	        $('.lastbtn').attr('disabled',false);
	        orderidarr.Print = {};
	    };
	    
	    if(Object.keys(orderidarr.Print).length > 0){
	        // طباعة الاوامر المحدده
	        showOselectConfirmationDialog(
	            `هل تريد طباعة الاوامر التاليه ? <hr>${html}`,
	            'تأكيد',
	            'question',
	            'الغاء قايمة الاوامر',
	            didOpenCallback,
	            confirmCallback,
	            denyCallback,
	        );
	    }else{
	        // طباعة كل الاوامر
	        showOselectConfirmationDialog(
	            "لم تقم بتحديد اى اوامر بيع لطباعتها <p> هل تريد طباعة كل الاوامر المعروضه ؟",
	            '',
	            'warning',
	            '',
	            ()=>{},
	            confirmCallback,
	            ()=>{},
	        );
	    }
	
	});
	$(document).on("click", "#btnExchange",()=>{
	    
	    let lastResult, countResults = 0;
	    const html = $('#swlSlectOrdersTmp').html();
	    let dt = odata
	    const dtoid = {};
	    const arrlen = orderidarr.Exchange;
	    const didOpenCallback  = () =>{
	        if(!Object.keys(orderidarr.Exchange).length){
	            if(!scanner.isScanning){
	                Swal.getDenyButton().textContent = "استخدم الباركود";
	            }else if(scanner.isScanning && ! Object.keys(orderidarr.Exchange).length){
	                Swal.getDenyButton().textContent = "التحويل اليدوى";
	            }else{
	                Swal.getDenyButton().textContent = "الغاء قايمة الاوامر";
	            }
	            Swal.showValidationMessage('ملحوظة فى حال الموافقة يتم تحويل الاوامر بنائاً على التصفية (الفلاتر) فى حال استخدامها ')
	        }else{
	            const el = $(".swal2-html-container .ordersSelectedList");
	            let i = 1;
	            for (const key in orderidarr.Exchange) {
	                let tc="",
	                    icon =`<i class="fa-solid fa-lg text-success fa-check"></i>`;
	                if(orderidarr.Exchange[key]['OSMsgCS']>0){
	                    tc= "text-danger";
	                    icon =`<i class="fa-solid fa-lg text-danger fa-xmark" style="color: #ff0000;"></i>`;
	                }
	                el.append(
	                    `<tr class="${tc}">
	                        <td>${i}</td>
	                        <td>${orderidarr.Exchange[key].subOid}</td>
	                        <td>${orderidarr.Exchange[key].ZName}</td>
	                        <td>${icon}</td>
	                    </tr>`
	                );
	                i++;
	            }
	        }
	    };
	    async function  updateOdata(){
	        await updateOrders();
	        dt = odata
	    }
	    const confirmCallbackA = () =>{
	    //الاوامر المحدده
	        function dataloob(dataarray){
	            for(const key in dataarray){
	                dtoid[key] = dataarray[key];
	            }
	        }
	        if(Object.keys(orderidarr.Exchange).length<=0){
	            dataloob(odata);
	        }else{
	            dataloob(orderidarr.Exchange);
	        }
	        if(Object.keys(dtoid).length>0){
	            actionSupport(dtoid);
	            $('#btnExchange .navbar-badge').html('');
	            $('.btnOservice').attr('disabled',false);
	            orderidarr.Exchange={};
	        }else{
	            Swal.fire(
	                'نأسف!',
	                'لايوجد اوامر بيع لتحويلها',
	                'warning'
	            );
	        }
	    };
	    const confirmCallbackB = () =>{
	    //كل الاوامر
	        for(const key in odata){
	            dtoid[key] = odata[key];
	        }
	        if(Object.keys(dtoid).length>0 ){
	            // البيانات التي سترسلها في طلب POST
	            actionSupport(dtoid);
	            $('#btnExchange .navbar-badge').html('');
	            $('.lastbtn').attr('disabled',false);
	            orderidarr.Exchange={};
	            if(scanner.isScanning){
	                html5QrCode.stop();
	                $(".DIRC").hide();
	            }
	            if(!$(".DOL").is('visible')){
	                $(".DOL").show();
	            }
	            updateOrders();
	        }else{
	            if(!scanner.isScanning){
	                Swal.fire(
	                    'نأسف!',
	                    'كل الاومر المعروضه لم يتم طباعتها او لا يوجد اوامر من الاساس من فضلك تأكد من وجود اوامر بيع ثم قم بطباعة الاوامر اولاً حتى تتمكن من تحويل امر البيع',
	                    'warning'
	                );
	            }else if(scanner.isScanning){
	                Swal.fire(
	                    'نأسف!',
	                    'لايوجد اوامر بيع لتحويلها',
	                    'warning'
	                );
	            }
	        }
	    };

	    const denyCallbackA    = () =>{
	        $('#btnExchange .navbar-badge').html('');
	        $('.btnOservice').attr('disabled',false);
	        orderidarr.Exchange={};
	        lastResult ='';
	        countResults = 0;
	        updateOrders();
	    };
	    const denyCallbackB    = () =>{
	        const od ={};
	        for (const key in odata) {
	            od[odata[key]['subOid']] = odata[key]['oid'];
	        }
	        $(".DOV").hide();
	        $(".DOL").hide();
	        $(".DIRC").show();
	        if(!scanner.isScanning){
	            startScanner(qrCodeExchangeCallback);
	        } else {
	        	scanner.stop();
	            $(".DOV").hide();
	            $(".DOL").show();
	            $(".DIRC").hide();
	        }
	    };
	    
	    if(Object.keys(orderidarr.Exchange).length > 0){
	    // الاوامر المحدده
	        showOselectConfirmationDialog(
	            html, 
	            'تأكيد',
	            'question',
	            'الغاء قايمة الاوامر',
	            didOpenCallback, 
	            confirmCallbackA, 
	            denyCallbackA
	        );
	    }else{
	    //كل الاوامر
	        showOselectConfirmationDialog (
	            "يمكنك استخدام ماسح الباركود لتحديد اوامر البيع التى تريد تحويلها  بالضغط على استخدام الباركود <p>او يمكنك ان تقوم بتحويل الاوامر المعروضه كلها بالضغط على موافق", 
	            'لم تقم بتحديد اى امر بيع',
	            'warning',
	            'استخدام الباركود',
	            didOpenCallback, 
	            confirmCallbackB, 
	            denyCallbackB
	        );
	    }
	});
	$(document).on("click", "#btnShipp", function () {
	    let lastResult, countResults = 0;
	    const html = $('#swlSlectOrdersTmp').html();
	    const dt = odata
	    console.log (dt)
	    const dtoid = {};
	    const arrlen = orderidarr.Shipp;
	    const didOpenCallback  = () =>{
	        if(!Object.keys(orderidarr.Shipp).length){
	            if(!scanner.isScanning){
	                Swal.getDenyButton().textContent = "استخدم الباركود";
	            }else if(scanner.isScanning && ! Object.keys(orderidarr.Shipp).length){
	                Swal.getDenyButton().textContent = "التحويل اليدوى";
	            }else{
	                Swal.getDenyButton().textContent = "الغاء قايمة الاوامر";
	            }
	            Swal.showValidationMessage('ملحوظة فى حال الموافقة يتم تحويل الاوامر بنائاً على التصفية (الفلاتر) فى حال استخدامها ')
	        }else{
	            const el = $(".swal2-html-container .ordersSelectedList");
	            let i = 1;
	            for (const key in orderidarr.Shipp) {
	                let tc="",
	                    icon =`<i class="fa-solid fa-lg text-success fa-check"></i>`;
	                if(orderidarr.Shipp[key]['OSMsgCS']){
	                    tc= "text-danger";
	                    icon =`<i class="fa-solid fa-lg text-danger fa-xmark" style="color: #ff0000;"></i>`;
	                }
	                el.append(
	                    `<tr class="${tc}">
	                        <td>${i}</td>
	                        <td>${orderidarr.Shipp[key].subOid}</td>
	                        <td>${orderidarr.Shipp[key].ZName}</td>
	                        <td>${icon}</td>
	                    </tr>`
	                );
	                i++;
	            }
	        }
	    };
	    const confirmCallbackA = () =>{
	    //الاوامر المحدده
	            let  eprint = 0
	            if(!Object.keys(orderidarr.Shipp).length){
	                for(const key in odata){
	                    if(odata[key]['OPrint']){
	                        dtoid[key] = odata[key];
	                    }else{
	                        eprint++;
	                    }
	                }
	            }else{
	                for(const key in orderidarr.Shipp){
	                    if(odata[key]['OPrint']){
	                        dtoid[key] = orderidarr.Shipp[key];
	                    } else {
	                        eprint++;
	                    }
	                }
	            }
	            if(Object.keys(dtoid).length>0){
	                deliveryConfirmation(dtoid);
	                $('#btnShipp .navbar-badge').html('');
	                $('.btnSelectOShipp').attr('disabled',false);
	                orderidarr.Shipp={};
	            }else{
	                if(!eprint){
	                    Swal.fire(
	                        'نأسف!',
	                        'لايوجد اوامر بيع لتحويلها',
	                        'warning'
	                    );
	                }else{
	                    Swal.fire(
	                        'نأسف!',
	                        'كل الاومر المعروضه لم يتم طباعتها او لا يوجد اوامر من الاساس من فضلك تأكد من وجود اوامر بيع ثم قم بطباعة الاوامر اولاً حتى تتمكن من تحويل امر البيع',
	                        'warning'
	                    );
	                }
	            }
	            
	    };
	    const confirmCallbackB = () =>{
	    //كل الاوامر
	        
	        for(const key in odata){
	            if(odata[key]['OPrint']){
	                dtoid[key] = odata[key];
	            }
	        }
	        if(Object.keys(dtoid).length>0 ){
	            // البيانات التي سترسلها في طلب POST
	            deliveryConfirmation(dtoid);
	            $('#btnShipp .navbar-badge').html('');
	            $('.lastbtn').attr('disabled',false);
	            orderidarr.Shipp={};
	            if(scanner.isScanning){
	                html5QrCode.stop();
	                $(".DIRC").hide();
	            }
	            if(!$(".DOL").is('visible')){
	                $(".DOL").show();
	            }
	            updateOrders();
	        }else{
	            if(!scanner.isScanning){
	                Swal.fire(
	                    'نأسف!',
	                    'كل الاومر المعروضه لم يتم طباعتها او لا يوجد اوامر من الاساس من فضلك تأكد من وجود اوامر بيع ثم قم بطباعة الاوامر اولاً حتى تتمكن من تحويل امر البيع',
	                    'warning'
	                );
	            }else if(scanner.isScanning){
	                Swal.fire(
	                    'نأسف!',
	                    'لايوجد اوامر بيع لتحويلها',
	                    'warning'
	                );
	            }
	        }
	    };
	    const denyCallbackA    = () =>{
	        $('#btnShipp .navbar-badge').html('');
	        $('.lastbtn').attr('disabled',false);
	        orderidarr.Shipp={};
	        lastResult ='';
	        countResults = 0;
	        updateOrders();
	    };
	    const denyCallbackB    = () =>{
	        const od ={};
	        for (const key in odata) {
	            od[odata[key]['subOid']] = odata[key]['oid'];
	        }
	        $(".DOV").hide();
	        $(".DOL").hide();
	        $(".DIRC").show();
	        if(!scanner.isScanning){
	            /*const qrCodeSuccessCallback = (decodedText, decodedResult) => {
	                countResults++;
	                let index = od[decodedText];
	                if(decodedText !== lastResult){
	                    lastResult = decodedText;
	                    if(odata[index]){
	                        if(odata[index]['OPrint']){
	                            if(!orderidarr.Shipp[index]){
	                                orderidarr.Shipp[index] = odata[index];
	                                Toast_fire(['s','نجح']);
	                                $('#btnShipp .navbar-badge').html(Object.keys(orderidarr.Shipp).length);
	                                audio_success.play();
	                            }else{
	                                Toast_fire(['w','تم ضم امر البيع هذا الى قايمة الاوامر المراد تحولها']);
	                                audio_success.play();
	                                
	                            }
	                        }else{
	                            Swal.fire(
	                                'نأسف!',
	                                'لايمكن تحويل امر البيع إلا بعد طباعته',
	                                'error'
	                            );
	                            audio_error.play();
	                        }
	                    }else{
	                        Swal.fire(
	                            'نأسف!',
	                            'الكود غير متاح فى هذة القائمة',
	                            'error'
	                        );
	                        scanner.stop();
	                        audio_error.play();
	                    }
	                }
	            };
	            const qrCodeErrorCallback = (err)=>{
	               // console.log(err);
	            };
	            const configScan = { fps: 5, qrbox: { width: 250, height: 250 } };
	            scanner.start({ facingMode: "environment" }, configScan, qrCodeSuccessCallback,qrCodeErrorCallback).catch((err) => {
	                $(".DOV").hide();
	                $(".DOL").show();
	                $(".DIRC").hide();
	                scanner.stop();
	                Swal.fire(
	                    'نأسف!',
	                    'لم نتمكن من فتح الكاميرا ... يمكن ان يكون المتصفح لايدعم هذه الاعداد او انك رفضت اعطاء صلاحية الوصول الى الكاميرا',
	                    'warning'
	                );
	            });
	        */
	        	startScanner(qrCodeShippingCallback);
	        } else {
	            scanner.stop();
	            $(".DOV").hide();
	            $(".DOL").show();
	            $(".DIRC").hide();
	        }
	    };
	    if(Object.keys(orderidarr.Shipp).length > 0){
	    // الاوامر المحدده
	        showOselectConfirmationDialog(
	            html, 
	            'تأكيد',
	            'question',
	            'الغاء قايمة الاوامر',
	            didOpenCallback, 
	            confirmCallbackA, 
	            denyCallbackA
	        );
	    }else{
	    //كل الاوامر
	        showOselectConfirmationDialog (
	            "يمكنك استخدام ماسح الباركود لتحديد اوامر البيع التى تريد تحويلها  بالضغط على استخدام الباركود <p>او يمكنك ان تقوم بتحويل الاوامر المعروضه كلها بالضغط على موافق", 
	            'لم تقم بتحديد اى امر بيع',
	            'warning',
	            'استخدام الباركود',
	            didOpenCallback, 
	            confirmCallbackB, 
	            denyCallbackB
	        );
	    }
	});
	$(document).on("click", ".btnAction", function () {
	    if(ptype == 1){
	        const dt   =odata
	        const data = odata[$(this).attr('data-oid')];
	        const oid  =data.oid,
	        osid = data.OSid,
	        obj = {};
	        obj[oid] = data;
	        
	        async function go(){
	        	await closedTiketSuport(data.OSid);
	        	actionSupport(obj,osid,true)
	        }
	        go();
	    }
	        
	});
	$(document).on("click", ".btnback", function () {
	    btnhead();
	    $(".DOV").hide();
	    $(".DOL").show();
	    if(Go ==="OrdersCustody"){
	    	$('.DCU').show();
	    }
	    $(".btnback").hide();
	    $(".btnhead").remove();
	    if($(".fiinfo").text()!=""){
	        $("#fiinfo").show();
	    }
	    if(Exists ==7){
	        $('#btnShipp').hide();
	        
	    }else if( Exists==8){
	        $('#btnSOL').hide();
	        $('#btnExchange').hide();
	    }
	});
	$(document).on("click", "#btnReturn", function () {
		try {
			
			const oid   = $(this).attr('data-id');
			const dt = odata
			const ordata =  odata[oid];
			dtoid = {};
			dtoid[oid] = odata;
			refundEmployeeCustody(dtoid);
		} catch (error) {
	        console.log(error);
	    }
	});
	$(document).on("click", "#btnDelevered", function () {
		oid = $(this).attr('data-id');
		const dt = odata
		const ordata =  odata[oid];
		dtoid = {};
		dtoid = odata;
		ShipmentDelivery(dtoid);
		
	});
	$(document).on("click", "#btnReceivingReturns" ,function () {
		let oid = $(this).attr('data-id');
		const ordata =  odata[oid];
		ShipmentApproved(odata);
	});
	$(document).on("click", "#btnQrcode" ,function () {
		$(".DOV").hide();
	    $(".DOL").hide();
	    $(".DIRC").show();
	    if(!scanner.isScanning){
	    	startScanner(qrCodeSettlementCallback);
	    } else {
	    	scanner.stop();
	        $(".DOV").hide();
	        $(".DOL").show();
	        $(".DIRC").hide();
	    }
	});
	
	$(document).on("click", ".OLF" ,function () {
		let oltype = $(this).attr('id');
		let val = $("#SelectUserDelivery").val();
		vieworderlistPram['cofi'] =  oltype;
		async function zx(){
			await table.clear();
			await fetchOrders();
			
			if(val){
			    $('.OLCBtn .btnTL').show();
				$('.OLCBtn .btnBL').show();
			}else{
			    $('.OLCBtn .btnTL').hide();
				$('.OLCBtn .btnBL').hide();
			}
		};
		zx();
	});
	$(document).on("change","#SelectUserDelivery",function(){
		const val = $(this).val();
		
		if(!val && $('.DIRC').is(':visible')){
			if(!$('.notscan').is(':visible') && scanner.isScanning){
				scanner.stop();
			}
			$(".DOV").hide();
	        $(".DOL").show();
	        $(".DIRC").hide();
			responseMsg(['warning','نعتذر','لايمكن استخدام قارئ البار كود الا عند اختيار المندوب',[]]);
		}
		vieworderlistPram['did'] = val;
		orderslist=[];
		
		async function zx(){
			await table.clear();
			await fetchOrders() ;
			if(val){
			    $('.OLCBtn .btnTL').show();
				$('.OLCBtn .btnBL').show();
			}else{
			    $('.OLCBtn .btnTL').hide();
				$('.OLCBtn .btnBL').hide();
			}
		}
		zx();
	});
	
	/*====================================================
	 check edit Order
	=====================================================*/
    let eorder = JSON.parse(localStorage.getItem('editON')),
        df_input='';
	const myInterval = setInterval(ru, 1000);
	function ru  (){
		if(eorder && eorder[0] !='' && ptype == 1 && Exists == 1){
		    $('.dataTables_filter input').val();
			$('.dataTables_filter input').val(eorder[1]).keyup();
			$('table tbody tr td button').each(function (){
				let xb = $(this);
				if(xb.attr('id') =='btnOview' && 
				   xb.attr('data-id') == eorder[0] && xb.is(':visible')){
				     xb.click();
				     sto();
				} else{
				    sto();
				}
				if(xb.attr('id') =='btnServiceRespons' &&
				   xb.attr('data-id') == eorder[0] && xb.is(':visible')){
				    xb.click();
				    sto();
				}else{
				    sto();
				}
			})
		}
	}
	function sto (){
	    $('.dataTables_filter input').val(df_input).keyup();
	    clearInterval(myInterval);
	    localStorage.removeItem('editON');
	}
});//end document ready