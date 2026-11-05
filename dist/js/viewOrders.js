if(Exists && !oService && Go =="Orderlist"){
	orderlistDir= `orderlist_ex${Exists}`;
}else if(!Exists && oService && Go =="Orderlist"){
	orderlistDir= `orderlist_os${oService}`;
}else if(!Exists && !oService && Go == "OrdersCustody"){
	orderlistDir= Go;
}else{
	orderlistDir= Go;
}

if(Exists == 0){
	sort = 'desc';
}

const urlvo = `../../inc/controller/viewOrders.control.php`;
const urlship = `../../inc/controller/shipment.control.php`;
const audio_success = new Audio("../../media/beep.mp3");
const audio_error = new Audio("../../media/error.mp3");
const scanner = new Html5Qrcode( "qrreader", { 
	    formatsToSupport: [
	    	Html5QrcodeSupportedFormats.CODE_128,
		    Html5QrcodeSupportedFormats.EAN_13,
		    Html5QrcodeSupportedFormats.EAN_8,
		    Html5QrcodeSupportedFormats.UPC_A,
		    Html5QrcodeSupportedFormats.UPC_E,
		    Html5QrcodeSupportedFormats.UPC_EAN_EXTENSION,
		    Html5QrcodeSupportedFormats.QR_CODE,
		],  
	  });
const orderidarr  = {
	    'Print':{},
	    'Exchange':{},
	    'Shipp' :{}
	    //'osubid':{}
	  };
const vieworderlistPram={
	'CONTROL':Go,
	'ptype':ptype,
	'st':ost,
	'url':wls,
	'did':0
};
let orderslist = []; // تخزين جميع البيانات
let odata = [];
let lastUpdate = "1971-01-01 00:00:00"; // آخر تحديث للبيانات
let visibilityHandler = null;
var ls;
var shipmentUser={};

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
};

/*========================================
	start function 
========================================*/

async function qrCodeExchangeCallback (decodedText, decodedResult) {

	                const od ={};
			        for (const key in odata) {
			            od[odata[key]['subOid']] = odata[key]['oid'];
			        }
	                
			        let lastResult ='',
			        countResults = 0,
			        index = od[decodedText];
			        //orderidarr.Exchange ={};
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
	            };
async function qrCodeShippingCallback (decodedText, decodedResult) {
	               
	                
	               
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
async function qrCodeSettlementCallback (decodedText, decodedResult) {
	                
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
	                    	
	                        if(odata[index]['OStat'] > 3){
	                        	alert(odata[index]['OStat'])
	                                orderidarr.Exchange[index] = odata[index];
	                                audio_success.play();
									const ordata = odata[od[lastResult]],
									dtoid = {};
									dtoid[od[lastResult]] = odata;
									ShipmentApproved(ordata);
	                            
	                        }else{
	                        	
	                        	audio_success.play();
	                        /*	const ordata =  odata[od[lastResult]],
								dtoid = {};
								dtoid[od[lastResult]] = odata;
	                        	ShipmentApproved(odata[index])*/

								const oid = od[lastResult];
								const ordata =  odata[oid];
								
								dtoid={};
								dtoid[oid] = ordata;
								
								if(ordata['custstat']=='FREEZE'){
									ShipmentApproved(ordata);
								}else{
									ShipmentDelivery(dtoid);
								}
	                            
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
	            };
async function oSelect (id,ftype) {
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
};
async function deliveryConfirmation (order) {
	console.log(order);
	const batchSize = 250;
	const batch = Object.keys(order).slice(0, batchSize);
	
	// keys اللي انت مختارها (جاية من order)
	const selectedKeys = batch;
	
	// نبني Object جديد من odata
	const result = Object.fromEntries(
	  selectedKeys
	    .filter(key => odata[key])   // تأكد إن الأوردر موجود
	    .map(key => [key, odata[key]])
	);
	
	// عدد الأوردرات الفعلية
	const orderCount = Object.keys(result).length;
	
	// إجمالي الكمية
	const totalQty = Object.values(result).reduce(
	  (sum, o) => sum + Number(o.PRO_QTY || 0),
	  0
	);
	
	function shipmentudata(){
            let returnobj ={};
            if(!Object.keys(shipmentUser).length){
                shipmentUser = sendRequestGET(`${urlApi}?CONTROL=UserListForType&utype=delivery`);
            }
            for(let item in shipmentUser){
                if(shipmentUser[item]['STATUSE']){
                    returnobj[shipmentUser[item]['ID']] = shipmentUser[item]['NAME'];
                }
            }
            
            return returnobj
	}
		
    
	let obj = {'shipUser':'','shipDate':'','order_id':batch,'lastUpdate':lastUpdate}, today = new Date();
    today = today.toLocaleDateString("en-US", option2);
    obj['shipDate'] = today;
    if(userlogin_data['uScript']['Admin'][orderlistDir]['Shipment'] == "true"){
        
        const shipuser = shipmentudata()
        Swal.fire({
            title: 'شحن عن طريق',
            html : 'اجمالى عدد الاوامر ('+orderCount+') اجمالى عدد المنتجات ('+totalQty+')',
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
					dir: 'rtl',
					dropdownParent: $('.swal2-popup')
				});
                $('.select2').css('margin', '20px');
            },
        }).then((result) => {
            
            if (result.isConfirmed) {
                obj['shipUser'] = result.value;
                obj['shipUName'] = shipuser[result.value];

                async function done(obj){
                   obj['CONTROL'] = 'shipmentOut';
                   let rq = await sendRequest(urlship, obj, false);
                   if(rq[0] !== 'error'){
                   		console.log(orderidarr.Shipp);
                   		// بعد النجاح احذف من object
						batch.forEach(k => delete orderidarr.Shipp[k]);
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
};
async function refundEmployeeCustody (order) {
    
    
    if (userlogin_data['uScript']['Admin'][orderlistDir]['RefundShipment'] == "true") {
        let swalContent = `
            <h4>هل تريد استرداد أمر البيع من المندوب؟</h4>
            <div style="display: flex; align-items: center; margin-top: 10px;">
                <input type="checkbox" id="confirmRefundCheckbox" style="margin-right: 5px;">
                <label for="confirmRefundCheckbox"> انا متأكد من تنفيذ هذا الاجراء </label>
            </div>
        `;
		let orderIds = Object.keys(order)
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
                    //confirmButton.disabled = false;
                }, 3000);
            }
        });
        
	    if (result.isConfirmed) {
	            if (userlogin_data['uScript']['Admin'][orderlistDir]['ChooseRefund'] == "true") {
	                let swalContentP = `
					            <h4>هل تريد ابقاء المنتجات عهده عى المندوب لحين رده الى المخزن</h4>
					            
					            <div style="display: flex; align-items: center; margin-top: 10px;">
					                <input type="checkbox" id="confirmRefundPCheckbox" style="margin-right: 5px;">
					                <label for="confirmRefundPCheckbox"> نعم اريد ابقاء المنتجات فى عهدة المندوب </label>
					            </div>
					        `;
	                let result2 = await Swal.fire({
	                    title: 'تأكيد',
	                    html: swalContentP,
	                    icon: 'question',
	                    showCancelButton: true,
	                    cancelButtonText: 'تخطى',
	                    confirmButtonText: 'تأكيد',
	                    didOpen: () => {
	                    	let confirmButton = Swal.getConfirmButton();
                			confirmButton.disabled = true;
	                    	// تفعيل الزر عند تحديد Checkbox
			                document.getElementById("confirmRefundPCheckbox").addEventListener("change", function () {
			                    confirmButton.disabled = !this.checked;
			                });	
	                    }
	                });
	
	                let msg = !result2.isConfirmed
	                    ? 'سوف يتم استرداد أمر البيع ورد المنتجات إلى المخزن.'
	                    : 'سوف يتم استرداد أمر البيع فقط، ستبقى المنتجات عهدة على المندوب.';
					
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
	                    const res = sendRequest(urlship, { 'CONTROL': 'shipmentRef', 'order_id': orderIds, 'lastUpdate':lastUpdate, 'type': result2.isConfirmed }, false);
	                    responseMsg(res);
	                    updateOrders();
	                    
	                }
	            } else {
	                let finalResult = await Swal.fire({
	                    title: 'تأكيد',
	                    html: `<div>سوف يتم استرداد امر البيع بالكامل.</div>`,
	                    icon: 'info',
	                    showCancelButton: true,
	                    cancelButtonColor: "#ff0000",
	                    cancelButtonText: 'إلغاء',
	                    confirmButtonText: 'تأكيد'
	                });
	
	                if (finalResult.isConfirmed) {
	                    const res = sendRequest(urlship, { 'CONTROL': 'shipmentRef', 'order_id': orderIds, 'lastUpdate':lastUpdate, 'type': false }, false);
	                    responseMsg(res);
	                    updateOrders();
	                    
	                    
	                }
	            }
	        }
        
    } else {
        Toast_fire(['w', 'ليس لديك صلاحية لاسترداد أمر البيع']);
    }
};
async function ShipmentApproved	(order) {
 	objdata = {}
 	objdata[order.oid] = order
	//let deliveryid = objdata[order.oid]['shipUid']
 	
console.log(order);
 	if(userlogin_data['uScript']['Admin'][orderlistDir]['ShipmentApproved'] == "true"){
		
		if(order.OStat > 2 || order.custstat =='FREEZE'){
	 		Swal.fire({
	            title: 'تأكيد!',
	            html: 'جارى عرض البيانات ...',
	            //html : htm, 
	            icon : 'question',
	            showCancelButton: true,
	            showDenyButton: true,
	            cancelButtonText: 'الغاء',
	            confirmButtonText:'موافق',
	            denyButtonText:'تصحيح',
	            didOpen: () =>{
					Swal.showLoading();
					$(".swal2-cancel").hide();
					$(".swal2-deny").hide();
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
						    //'deliveryid': deliveryid
							},
			            dataType: 'json'
			        });
			        
			        rq.done(function (response, statuse) {
			        	console.log(response);
			        	if(response.type==="success"){
			        		let trHtml = '';
				        	$(".swal2-title").html("هل تريد انشاء اذن استلام للمرتجعات التاليه؟")
				            //$('.swal2-html-container').html(response[1]);
				            renderProductsTable(response);
							$('.swal2-html-container .btn-success').hide();
				            $('.swal2-html-container .btn-danger').hide();
				            $(".swal2-html-container thead").addClass('bg-black');
							$(".swal2-html-container table").css('font-size','1.2rem');
							$(".swal2-html-container th:nth-child(2), .swal2-html-container td:nth-child(2)").hide();
				            $(".swal2-html-container th:nth-child(3), .swal2-html-container td:nth-child(3)").hide();
							$(".swal2-html-container th:nth-child(5), .swal2-html-container td:nth-child(5)").hide();
							
							$(".swal2-html-container tbody tr").each(function() {
					            var salary = $(this).find("td:nth-child(4)").text().trim(); // جلب قيمة العمود 
					            if (salary > 0) {
					                trHtml += `<tr>`+ $(this).html() +`</tr>`; // إخفاء الصف إذا كان المرتجع اكبر من صفر
					            }
							});  
							
							$('.swal2-html-container').hide();
							$('.swal2-html-container').html(`
									<table class="table bg-inf table-swel-ex table-hover table-responsive text-nowrap table-striped w-100 col-sm-12 p-0 m-0 " style="display:inline-table;font-size: 1rem;">
										<thead class="bg-black col-sm-12">
											<tr class="">
												<th class="col-sm-8">المنتج</th>
												<th class="col-sm-4">المرتجع</th>
											</tr>
										</thead>
										<tbody class="bg-light">
										${trHtml}
										</tbody>
									</table>`);
							Swal.hideLoading();
							
							$('.swal2-html-container').show();
							$(".swal2-cancel").show();
							if(order.custstat !='FREEZE'){
								$(".swal2-deny").show();
							}
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
		        					'delivery_id':order['shipUid'],
		        					'lastUpdate' : lastUpdate
		        				}, false);
		        	responseMsg(res2);
		        	updateOrders();
		        	
	        	}
	        	if(result.isDenied && order.custstat !='FREEZE'){
	        		ShipmentDelivery(objdata);
	        	}else if(result.isDenied && order.custstat =='FREEZE'){
	        		responseMsg(['warning','تنبيه',
	        		'لايمكن تصحيح تسليمات المندوب - امر البيع مجمد بعهدة المندوب']);
	        	}
	        })
		} else { 
			Toast_fire(['i','حالة امر البيع لا تسمح بتنفيذ هذا الاجراء']);
			
			//ShipmentDelivery(objdata);
		}
 	}else{
 		Toast_fire(['w','ليس لديك صلاحية لاستلام مرتجعات امر البيع']);
 		
 	}
 };
async function dataTableReload(){
	await table.clear().draw();
	if( 
		(ptype == 2 && ! window.APP_CONFIG.datatablesServerSide) || 
		(ptype == 3 && ! window.APP_CONFIG.datatablesServerSide) || 
		(ptype == 1 && Exists == 0  && !window.APP_CONFIG.datatablesServerSideEx0) || 
		(ptype == 1 && Exists == 1  && !window.APP_CONFIG.datatablesServerSideEx1) || 
		(ptype == 1 && Exists == 2  && !window.APP_CONFIG.datatablesServerSideEx2) || 
		(ptype == 1 && Exists == 3  && !window.APP_CONFIG.datatablesServerSideEx3) || 
		(ptype == 1 && Exists == 4  && !window.APP_CONFIG.datatablesServerSideEx4) || 
		(ptype == 1 && Exists == 5  && !window.APP_CONFIG.datatablesServerSideEx5) || 
		(ptype == 1 && Exists == 7  && !window.APP_CONFIG.datatablesServerSideEx7) || 
		(ptype == 1 && Exists == 8  && !window.APP_CONFIG.datatablesServerSideEx8) || 
		(ptype == 1 && Exists == 9  && !window.APP_CONFIG.datatablesServerSideEx9) || 
		(ptype == 1 && Exists == 10 && !window.APP_CONFIG.datatablesServerSideEx10)
	){
		await fetchOrders(false) ;
	}else{
		table.ajax.reload(null, false);
	}
	
}
		
function btnhead  () {
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
function stopScanner (FNCback) {
	scanner.stop();
	$('.btnStratBarcode').attr('onclick',`startScanner(${FNCback})`).show()
};
function startScanner (success) {
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
 };
function custodyTotal (dt) {
	
	$('.cut').html(dt['cuTotal']['cuTotal']);
	$('.custodyTotal .cu2').html(dt['cuTotal']['cuAshipp']);
	$('.custodyTotal .cu3_4').html(dt['cuTotal']['cuBshipp']);
	$('.custodyTotal .cutqty').html(dt['cuTotal']['cuRefQty']);
};
/*function Reactivation(orderData){
		if(ptype == 1 ){//&& userlogin_data['uScript']['Admin'][orderlistDir]['transformation'] == "true")
			
			let res = sendRequest('../../inc/Api.php',{'CONTROL':'resetOrder','orderData':orderData} ,true)
			responseMsg(res);
			
		}else{
			alert('انت لا تملك صلاحية استخدام هذه الخدمه')
		}
	}*/
function Reactivation(orderData) {
	if (ptype == 1) { // && userlogin_data['uScript']['Admin'][orderlistDir]['transformation'] == "true")
		
		Swal.fire({
			title: 'هل أنت متأكد؟',
			text: 'سيتم إعادة تفعيل الطلب',
			icon: 'warning',
			showCancelButton: true,
			confirmButtonText: 'نعم، تنفيذ',
			cancelButtonText: 'إلغاء',
			confirmButtonColor: '#3085d6',
			cancelButtonColor: '#d33'
		}).then((result) => {

			if (result.isConfirmed) {

				let res = sendRequest('../../inc/Api.php',{'CONTROL': 'resetOrder','orderData': orderData}, false);

				responseMsg(res);
			}

		});

	} else {
		alert('انت لا تملك صلاحية استخدام هذه الخدمه');
	}
}
function btnhead () {
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
function increaseQty (productId,index) {
	  $('.swal2-html-container .btn').blur();
	  var maxqty = document.getElementById('max-qty-'+ index + '-' + productId);
	  var d = document.getElementById('difference-qty-'+ index + '-'+productId);
	  var e = document.getElementById('approved-qty-'+ index + '-' + productId);
	  var currentValue = parseFloat(e.innerHTML);
	  //var carrentdvalue= parseFloat(d.innerHTML);
	  var max = parseInt(maxqty.dataset.maxqty);
	  if (currentValue < max) {
	    e.innerHTML = parseFloat(currentValue + 1);
	    d.innerHTML = parseFloat(max - (currentValue + 1))
	  }
};
function increaseQtyAll (productId,index) {
	$('.swal2-html-container .btn').blur();
  var maxqty = document.getElementById('max-qty-'+ index + '-' + productId);
  var d = document.getElementById('difference-qty-'+ index + '-'+productId);
  var e = document.getElementById('approved-qty-'+ index + '-' + productId);
  var max = parseInt(maxqty.dataset.maxqty);
  e.innerHTML = max;
  d.innerHTML =0;
};
function increaseQtyAllPublic () {
	$('.swal2-html-container .btn').blur();
	var trElements = document.querySelectorAll(".table-swel tbody tr");
	// حلقة للتكرار على كل عنصر tr
	let i = 0;
	trElements.forEach(function(trElement) {
		var code   = trElement.getAttribute('data-code');
		var d = document.getElementById('difference-qty-'+i+'-'+code);
		var maxqty = document.getElementById('max-qty-'+i+'-' + code);
		var e = document.getElementById('approved-qty-'+i+'-' + code);
		var max = parseInt(maxqty.dataset.maxqty);
		e.innerHTML = max;
		d.innerHTML = 0;
		i++;
	})
};
function decreaseQty (productId,index) {
	$('.swal2-html-container .btn').blur();
  var d = document.getElementById('difference-qty-'+ index + '-'+productId);
  var e = document.getElementById('approved-qty-'+ index + '-' + productId);
  var maxqty = document.getElementById('max-qty-'+ index + '-' + productId);
  var max = parseInt(maxqty.dataset.maxqty);
  var currentValue = parseFloat(e.innerHTML);
  if (currentValue > 0) {
    e.innerHTML = parseFloat(currentValue - 1);
    d.innerHTML = parseFloat(max - (currentValue - 1));
  }
};
function decreaseQtyAll (productId,index) {
	$('.swal2-html-container .btn').blur();
  var d = document.getElementById('difference-qty-'+ index + '-'+productId);
  var e = document.getElementById('approved-qty-'+ index + '-' + productId);
  var maxqty = document.getElementById('max-qty-'+ index + '-' + productId);
  var max = parseInt(maxqty.dataset.maxqty);
  e.innerHTML = 0;
  d.innerHTML = max;
};
function decreaseQtyAllPublic () {
	$('.swal2-html-container .btn').blur();
		var trElements = document.querySelectorAll(".table-swel tbody tr");
	// حلقة للتكرار على كل عنصر tr
	let i = 0;
	trElements.forEach(function(trElement) {
		var code   = trElement.getAttribute('data-code');
		var d = document.getElementById('difference-qty-'+i+'-'+code);
		var e = document.getElementById('approved-qty-'+i+'-' + code);
		var maxqty = document.getElementById('max-qty-'+i+'-' + code);
		var max = parseInt(maxqty.dataset.maxqty);
		e.innerHTML = 0;
		d.innerHTML = max;
		i++;
		
	})
};

function actionSupport (allOrdeDataObj,osid=0) {
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
            preConfirm:()=>{
            	Swal.showLoading(); 
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
                   console.log(ordersobj);
                    const Parameters= {
                    				   'CONTROL' : 'Redirect_Orders',
                                       'ex_int' : result.value,
                                       'ex_str' : sTrue[result.value],
                                       'osid'   : osid,
                                       'ordersData' : ordersobj,
                    };
                    let response = false;
                    Swal.fire({
					  title: 'جاري تنفيذ العملية...',
					  html: 'يرجى الانتظار',
					  allowOutsideClick: false,
					  didOpen: () => {
					    Swal.showLoading();
					    setTimeout(()=>{
					    	const res  =   sendRequest(urlControl,Parameters, false);
					        response = res;      
					    },800);
					    
					    const interval = setInterval(() => {
    						if(!response) return;
    						clearInterval(interval); // نوقف التكرار بعد أول مرة
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
					                    updateOrders(true);
					    },10);
					    
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
};
function showOselectConfirmationDialog (htm, title, icon, denyBtnText, didOpenCallback, confirmCallback, denyCallback) {
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
};
function optionBtnCustody () {
 		if (document.getElementById('myElement')) {
	    	let delval = $("#SelectUserDelivery").val();
	    	if(delval){
			    $('.OLCBtn .btnTL').show();
				$('.OLCBtn .btnBL').show();
			}else{
			    $('.OLCBtn .btnTL').hide();
				$('.OLCBtn .btnBL').hide();
			}
 		} 
 };

 /*===================================
  عرض تذاكر خدمة العملاء
 ===================================*/
async function handleAdminUser (oid, OSid, OStat, OPrint, OSStatu, oidData) {
    if (OSid > 0 && OSStatu == 0) {
        await viewTicket(oid, OSid, true);
    } else {
        if (OStat != 1 || (OStat == 1 && OPrint == 0)) {
           	await actionSupport(oidData, OSid);
        } else if (OPrint == 1) {
            await processOrderExchange(oid);
        } else {
            showWarning("انتبه", "!لايمكن تحويل اوامر البيع قبل طباعتها");
        }
    }
};
function handleNonAdminUser (oid, OSid, OStat, OPrint, OSStatu, olup) {
    if ((OSid == 0 && OStat < 1) || (!OSid && OStat == 1 && !OPrint) ||(OSid == 0 && OStat==5)) {
        openTiketSupport(oid,olup,OStat);
    } else if (OSid > 0 && OSStatu >0) {
        showClosedTicketAlert(oid, OSid,OPrint,OStat, olup);
    } else if (OSid > 0 || OSStatu > 0) {
        viewTicket(oid, OSid);
    }
};
function showClosedTicketAlert (oid, OSid,opr,oSt,olup) {
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
            openTiketSupport(oid, olup, oSt);
            //orderid,orderlastupdate,OStat
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            ticketView(oid, OSid, ptype);
        }
    });
};
function viewTicket (oid, OSid, isAdmin = false) {
    new Promise((resolve) => {
        resolve(ticketView(oid, OSid, ptype));
    }).then(() => {
        if (isAdmin) {
            updateOrders();
        }
    });
};

/*====================================
  تسليم امر البيع للعميل
 ====================================*/
function processOrderExchange (oid) {
    const result = oSelect(oid, 'Exchange');
    //if (result === 'osOpen') {
        $(this).children('span').remove();
   /* } else {
        showWarning("انتبه", "!لايمكن تحويل اوامر البيع قبل طباعتها");
    }*/
};
function showWarning (title, message) {
    Swal.fire(title, message, 'warning');
};
function renderProductsTable(data_products) {
	  index = 0
	  let products = data_products.products;
	  let returned = data_products.returned;
	  let html = `
	    <div class="bg-danger1">
			<div class="btn-group" style="margin:1px;">
				<button type="button" class="btn btn-success m-1" onclick="increaseQtyAllPublic()">
					تسليم الكل
					<i class="fa-solid fa-check"></i>
				</button>
				<button type="button" class="btn btn-danger m-1" onclick="decreaseQtyAllPublic()">
					رفض الكل
					<i class="fa-solid fa-xmark"></i>
				</button>
			</div>
				<table class="table table-swel table-hover table-responsive text-nowrap table-striped w-100 col-sm-12 p-0 m-0 " style="font-size: 1rem;">
					<thead>
						<tr class="">
							<th class="col-4">المنتج</th>
							<th class="col-2 badge-gry10">المطلوب</th>
							<th class="col-2 badge-green10">التسليم</th>
							<th class="col-2 badge-red10">مرفوض</th>
							<th class="col-4"><+-></th>
						</tr>
					</thead>
				<tbody>`;
	
	  products.forEach(prod => {
	  
	    const difference = prod.qty - prod.delivered_qty;
	    html += `
	      
	      <tr data-oid="${prod.oid}" data-code="${prod.code}" data-default="${prod.qty}" data-delivered="${prod.delivered_qty}" data-difference="${difference}" data-did="${prod.did}">
	      
	        <td class="text-nowrap productname text-left">
				<div class="proname">
					<div class=" tdrest tdproName">${prod.name}</div>
					<a class="tdrest text-info tdproOption p-0 m-0" style="font-size:0.79rem;margin-bottom:5px">${Array.isArray(prod.option) ? prod.option.join(" | ") : prod.option.replace(/,/g, " | ")}</a>
				</div>
			</td>
			<td class="badge-gry10 w-50"><h5 class="default-Qty" data-maxqty="${prod.qty}" id="max-qty-${index}-${prod.code}">${prod.qty}</h5></td>
			<td class="appQty badge-green10 w-50"><h5 class="approved-Qty" data-code="${prod.code}" id="approved-qty-${index}-${prod.code}">${prod.delivered_qty}</h5></td>
			<td class="difQty badge-red10 w-50"><h5 class="difference-Qty" data-code="${prod.code}" id="difference-qty-${index}-${prod.code}">${difference}</h5></td>
			<td class="p-0">
				<div class="btn-group1" style="margin:-1px;">
					<button type="button" class="btn" onclick="increaseQty(${prod.code},${index})">
						<i class="fa-solid fa-plus text-primary"></i>
					</button>
					<button type="button" class="btn" onclick="decreaseQty(${prod.code},${index})">
						<i class="fa-solid fa-minus text-primary"></i>
					</button>
				</div>
				<div class="btn-group1" style="margin:-1px;">
					<button type="button" class="btn" onclick="increaseQtyAll(${prod.code},${index})">
						<i class="fa-solid fa-check text-success"></i>
					</button>
					<button type="button" class="btn" onclick="decreaseQtyAll(${prod.code},${index})">
						<i class="fa-solid fa-xmark text-danger"></i>
					</button>
				</div>
			</td>
		</tr>`;
		index++;
	  });
	
	  html += `</tbody></table></div>`;
	  
	  if (returned.length){
	
		  html += `
			<div class="bg-danger1 refhide">
				<h4 class="mt-2 bg-danger refhide">استلام مرتجعات الامر الاصلى<h4>
				<table class="table bg-inf table-swel-ex table-hover table-responsive text-nowrap table-striped w-100 col-sm-12 p-0 m-0 " style="font-size: 1rem;">
					<thead class="bg-light refhide">
						<tr class="">
							<th class="col-4">المنتج</th>
							<th class="col-2 badge-gry10">المطلوب</th>
							<th class="col-2 badge-green10">التسليم</th>
							<th class="col-2 badge-red10">المرتجع</th>
							<th class="col-4"><+-></th>
						</tr>
					</thead>
					<tbody class="bg-light">`;
	
		  returned.forEach(prod => {
		    const difference = prod.delivered_qty - prod.returned_qty;
		   //	<--<td class="badge-gry10 w-50"><h5 class="Exdefault-Qty" data-maxqty=12"${prod.qty}" id="default-qty-${prod.code}" >${prod.qty}</h5></td>-->
		   
		    html += `
		    	<tr data-oid="${prod.oid}" data-code="${prod.code}" data-default="${prod.qty}" data-delivered="${prod.delivered_qty}" data-difference="${difference}" data-did="${prod.did}">
					<td class="text-nowrap productname text-left">
						<div class="proname">
					        <div class=" tdrest tdproName">${prod.name}</div>
							<a class="tdrest text-info tdproOption p-0 m-0" style="font-size:0.79rem;margin-bottom:5px">${Array.isArray(prod.option) ? prod.option.join(" | ") : prod.option.replace(/,/g, " | ")}</a>
						</div>
					</td>
					<td class="badge-gry10 w-50"><h5 class="default-Qty" data-maxqty="${prod.delivered_qty}" id="max-qty-${index}-${prod.code}">${prod.qty}</h5></td>
					<td class="ExdifQty bg-success w-50"><h5 class="difference-Qty" data-code="${prod.code}" id="difference-qty-${index}-${prod.code}">${difference}</h5></td>
					<td class="ExappQty bg-danger w-50"><h5 class="approved-Qty" data-code="${prod.code}" id="approved-qty-${index}-${prod.code}">${prod.returned_qty}</h5></td>
					<td class="p-0">
						<div class="btn-group1" style="margin:-1px;">
							<button type="button" class="btn" onclick="increaseQty(${prod.code},${index})">
								<i class="fa-solid fa-plus text-primary"></i>
							</button>
							<button type="button" class="btn" onclick="decreaseQty(${prod.code},${index})">
								<i class="fa-solid fa-minus text-primary"></i>
							</button>
						</div>
						<div class="btn-group1" style="margin:-1px;">
							<button type="button" class="btn" onclick="increaseQtyAll(${prod.code},${index})">
								<i class="fa-solid fa-check text-success"></i>
							</button>
							<button type="button" class="btn" onclick="decreaseQtyAll(${prod.code},${index})">
								<i class="fa-solid fa-xmark text-danger"></i>
							</button>
						</div>
					</td>
				</tr>`;
				index++;
		  });
		
		  html += `</tbody></table></div>`;
	  }
	  $(".swal2-html-container").html(html);
}
function loadOrderDetails(orderId) {
    return $.ajax({
        url: urlship,
        type: 'POST',
        async:true,
        dataType: 'json',
        data: {
            CONTROL: "shipDelivery",
            order_id: orderId,
        }
    });
};
function saveShipApproved(parmm) {
    return $.ajax({
		url: urlship,
		type: 'POST',
		async:true,
		dataType: 'json',
		data: parmm
	});
};
function parseShipmentTable() {
    let objApprov = objReject = {}, 
    shipp_products =[],
    qtyApp = qtyDef = err = 0;
	let objExApprov = objExReject = {}, 
    	ExqtyApp = ExqtyDef = Exerr = 0;
    	
    document.querySelectorAll(".table-swel tbody tr").forEach(tr => {
        let oid = tr.dataset.oid;
        let code = tr.dataset.code;
        let did = tr.dataset.did;
        let dQty = parseFloat(tr.querySelector(".default-Qty").innerText);
        let aQty = parseFloat(tr.querySelector(".approved-Qty").innerText);
        let proName = tr.querySelector(".proname").innerText;

        qtyDef += dQty;
        qtyApp += aQty;
		
        if (aQty <= dQty && aQty >= 0 && code) {
        	shipp_products.push({
				'ORDER_ID'    : oid,
				'PRO_CODE'    : code,
				'productData' : {'appQty' : aQty, 'defqty' : dQty , 'recqty' : null},
				'DELIVERY_ID' : did,
				'TYPE'        : 'SELSE'
			});
				
            objApprov[code] = {
            	'oid': oid, 
               	'code': code ,
            	'defqty': dQty, 
            	'appQty': aQty,
            	'proData': proName
            };
            
            if (dQty > aQty) {
                objReject[code] = { 
                	'oid': oid, 
                	'code': code ,
                	'defqty': dQty, 
                	'appQty': aQty, 
                	'proData': proName };
            }
        } else {
            err++;
        }
    });
    
    
    document.querySelectorAll(".table-swel-ex tbody tr").forEach(tr => {
    	let Exoid           = tr.dataset.oid;
        let Excode          = tr.dataset.code;
        let did 			= tr.dataset.did;
        let ExOrderQty      = tr.dataset.default;
        let ExDeliveredQty  = parseFloat(tr.dataset.delivered);
        let ExDifferenceQty = parseFloat(tr.querySelector(".difference-Qty").innerText);
        let ExReturnedQty   = parseFloat(tr.querySelector(".approved-Qty").innerText);
        let ExproName       = tr.querySelector(".proname").innerText;

        ExqtyDef += ExDeliveredQty;
        ExqtyApp += ExReturnedQty;

        if (ExReturnedQty <= ExDeliveredQty && ExReturnedQty >= 0 && Excode != 'null') {
            shipp_products.push({
				'ORDER_ID'	  : Exoid,
				'PRO_CODE'    : Excode,
				'productData' : {'appQty' : ExReturnedQty, 'defqty' : ExOrderQty, 'recqty' : ExDeliveredQty },
				'DELIVERY_ID' : did,
				'TYPE'		  : 'REFUND' 
			});
			
            objExApprov[Excode] = {
            	'Exoid'     	 : Exoid, 
            	'Excode'    	 : Excode, 
             	'ExOrderQty'     : ExOrderQty, 
            	'ExDeliveredQty' : ExDeliveredQty, 
            	'ExDifferenceQty': ExDifferenceQty, 
            	'ExReturnedQty'  : ExReturnedQty
            };
        } else {
            Exerr++;
        }
    });
    
    return { objApprov, objExApprov, objReject, objExReject, qtyApp, ExqtyApp, qtyDef, ExqtyDef, Exerr, err,shipp_products };
};
function createShipmentObject(orderId, parsed) {
    let { objApprov, objExApprov, objReject, objExReject, qtyApp, ExqtyApp, qtyDef, ExqtyDef, shipp_products} = parsed;
    // const { , , ,  } = parsed;
    
    let reapp = "رفض امر البيع بالكامل";
    let qty_rejected = 0;
    let qty_returned = 0;
    let oExchange = null;
    if (qtyApp > 0 && qtyApp === qtyDef) {
        reapp = "تسليم امر البيع بالكامل";
    } else if (qtyApp > 0) {
        reapp = "تسليم امر البيع بشكل جزئي";
        qty_rejected++;
    } else {
        qty_rejected++;
    }
    if(ExqtyDef>0){
	    reapp +='<p>';
	    reapp += " و ";
    }
    if (ExqtyApp > 0 && ExqtyApp === ExqtyDef) {
        reapp += "مرتجع كامل لمنتجات امر الاستبدال";
        qty_returned++;
    } else if (ExqtyApp > 0) {
        reapp += "مرتجع جزئى لمنتجات امر الاستبدال";
        qty_returned++;
    } else if(ExqtyDef > 0){
    	//reapp +='<p>';
	   // reapp += " و ";
    	reapp +='رفض العميل تسليم مرتجعات امر الاستبدال'
        
    }
    const allowedKeys = ['oid', 'shipUid', 'custstat', 'olup', 'EXCHANGE', 'EXCHANGE_SUPID'];

	if (Object.keys(orderId).length) {
	    for (const key in orderId) {
	        const original = orderId[key];
	        const filtered = {};
	        allowedKeys.forEach(k => {
	            if (original.hasOwnProperty(k)) {
	                filtered[k] = original[k];
	            }
	        });
			oExchange= orderId[key].EXCHANGE
	        orderId[key] = filtered;
	    }
	}

    let objrq = {
    		checkbox : ((qty_returned + qty_rejected) > 0 && ptype == 1) ? true:false,
	        odata: orderId,
	        oapp: objApprov,
	        orej: objReject,
	        shipp: shipp_products,
	        reapp,
	        utype: ptype,
	        qty_rejected,
	        qty_returned,
	        exchange: oExchange ? {
	        	exid: oExchange,
	        	oapp: parsed['objExApprov'],
	        } : null
    };
    return objrq;
};
function ShipmentDelivery(orderId) {
	checkIntrnet();
    Swal.fire({
        title: " ",
        html: 'جارى عرض البيانات ...',
        showCancelButton: true,
        confirmButtonText: 'تأكيد',
        cancelButtonText: 'إلغاء',
        customClass: {
            actions: 'my-actions',
            confirmButton: 'order-1',
            cancelButton: 'order-2 right-gap',
        },
        didOpen: () => {
            Swal.showLoading();
            $(".swal2-cancel").hide();
            loadOrderDetails(orderId).done(response => {
                if (response.type === "success") {
                    $(".swal2-title").html("تأكيد تسليم امر البيع");
                    //$(".swal2-html-container").html(response[1]).show();
                    renderProductsTable(response);
                    $(".swal2-cancel").show();
                    Swal.hideLoading();
                } else if (response.type === "warning") {
                    Swal.fire(data.title, data.message, 'warning');
                }
            }).fail(() => {
                Swal.fire('نأسف', 'حدث خطأ غير معروف أثناء التنفيذ', 'error');
            });
        },
        preConfirm: () => {
            const parsed = parseShipmentTable();
            
            if (parsed.err > 0) {
                Swal.showValidationMessage('لا يمكن تسليم كمية أكثر من المطلوب أو أقل من الصفر');
                return false;
            }
            return createShipmentObject(orderId, parsed);
        }
    }).then(result => {
        if (result.isConfirmed) {
           
            Swal.fire({
		        title: 'تأكيد',
		        icon: 'question',
		        html: `<div><p>سوف يتم ${result.value.reapp}؟</p></div>`,
		        input: 'checkbox',
				inputPlaceholder: 'انشاء اذن استلام مرتجعات',
		        inputValidator: () => {},
				showCancelButton: true,
		        confirmButtonText: 'حفظ',
		        cancelButtonText: 'إلغاء',
		        didOpen : ()=> {
		        	if(result.value.checkbox){
		        		$(".swal2-container label").show();
		        	}else{
		        		$(".swal2-container label").hide();
		        	}
		        	const checkbox = Swal.getInput();
				    const confirmBtn = Swal.getConfirmButton();
				
				    // اللون الافتراضي (رمادي مثلاً)
				    confirmBtn.style.backgroundColor = '#7066e0'; // رمادي
				    confirmBtn.style.borderColor = '#7066e0';
				
				    // عند تغيير حالة الـ checkbox
				    checkbox.addEventListener('change', () => {
				      if (checkbox.checked) {
				        confirmBtn.textContent = 'إنشاء';
				        confirmBtn.style.backgroundColor = '#28a745'; // أخضر
				        confirmBtn.style.borderColor = '#28a745';
				      } else {
				        confirmBtn.textContent = 'تأكيد';
				        confirmBtn.style.backgroundColor = '#7066e0'; // أزرق
				        confirmBtn.style.borderColor = '#7066e0';
				      }
				    });
		        },
		        preConfirm: () => {
		        	let data = orderId[Object.keys(orderId)[0]];
		        	data.items = result.value.shipp;
		            const isChecked = Swal.getInput().checked;
		            
		            return {
		                CONTROL: "shipDelivered",
		                odata: result.value.odata,
		                data : data,
		                oshipp: {
		                    ship: result.value.shipp,
		                    oapp: result.value.oapp,
		                    orej: result.value.orej,
		                    reapp: result.value.reapp,
		                    ptype: result.value.utype
		                },
		                exchange: result.value.exchange,
		                ShipApproved: isChecked, // ✅ القيمة تعتمد على الـ checkbox
		                urlControl: Go
		            };
		        }
		    }).then(confirmRes => {
                if (confirmRes.isConfirmed) {
                    let finalData = confirmRes.value;
                    
                        Swal.fire({
                            title: 'جارى الحفظ',
                            icon: '',
                            html: 'من فضلك انتظر ...',
                            allowOutsideClick: false,
                            didOpen: () => {
                				Swal.showLoading();
                				//const key = Object.keys(orderId)[0];
                				//oid = orderId[key]['oid'];
	                            saveShipApproved(finalData).done(response => { 
								    Swal.hideLoading();
	                            	responseMsg(response);
	                            	
							    	if (response[0] =='success' && ptype === 1 && Go === "OrdersCustody" ) {
								        
									        Swal.fire({
								                title: response[1],
								                icon: response[0],
								                html: response[2],
								                //showCancelButton: true,
								                showDenyButton : response[4],
								                confirmButtonText: 'إغلاق',
								                denyButtonText: 'إنشاء إذن أستلام مخزنى',
								                
									        }).then(resp => {
									        	if (resp.isDenied) {
									        		
									        	//	const ordata =  odata[oid];
													//ShipmentApproved(ordata);
									        	}
									        	updateOrders(true);
									        });
							    	} else {
						            	responseMsg(response);
						            	updateOrders(true);
						        	}
	                            }).fail(() => {
	                            	updateOrders(true);
									Swal.fire('نأسف', 'حدث خطأ غير معروف أثناء التنفيذ', 'error');
								});
								
                			},
                        });
                    
                }
            });
        }
    });
};

/*====================================
  اتصال المناديب
 ====================================*/
let phoneNumber 			 = null;
let callStartTime			 = null;
let timerInterval			 = null;
let duration				 = null;
let orderId 				 = null;
let isOpen					 = false;
let visibilityHandlerContact = null;
let clientdatafromlocal 	 = {};
let contactCustomerObj		 = {
	'order_id'          : null,
	'employee_type'     : ptype, // sales, delivery, marketing, other
	'action_type'       : null,  // call, whatsapp, sms, note, status_change
	'result'            : null,   // no_answer, phone_closed, call_later, wrong_number || confirmed, cancelled
	'call_duration_sec' : 0,     //مدة المكالمه
	'notes'             : "",
	'shipDate'          : null
}; 

// Elements ==================
const widget = document.getElementById("callWidget");
const callMain = document.getElementById("callMain");
const callDetails = document.getElementById("callDetails");
const endCallEl = document.getElementById("endCall");
const timerEl = document.getElementById("callTimer");
const clientNameEl = document.getElementById("clientName");
const clientPhoneEl = document.getElementById("clientPhone");
const clientAddressEl = document.getElementById("clientAddress");

// Toggle ==================
callMain.addEventListener("click", function () {
    isOpen = !isOpen;
    callDetails.style.display = isOpen ? "block" : "none";
});
// Timer  ==================
function startTimer() {
    timerInterval = setInterval(() => {
		let totalSeconds = Math.floor((Date.now() - callStartTime) / 1000);
		duration = totalSeconds;
		let hours = Math.floor(totalSeconds / 3600);
		let minutes = Math.floor((totalSeconds % 3600) / 60);
		let seconds = totalSeconds % 60;
		let formattedTime = 
			hours.toString().padStart(2, '0') + ':' +
			minutes.toString().padStart(2, '0') + ':' +
			seconds.toString().padStart(2, '0');
		timerEl.textContent =
			String(hours).padStart(2, '0') + ":" +
			String(minutes).padStart(2, '0') + ":" +
			String(seconds).padStart(2, '0');
	}, 1000);
}
// انهاء الاتصال ==================
function endCall() {
	clearInterval(timerInterval);
	widget.style.display = "none";
	callDetails.style.display = "none";
	callMain.classList.remove("active");
	callStartTime = null;
	Contact_result();
	timerEl.textContent = '00:00:00'
	
}

function removeEvent() {
	if (visibilityHandlerContact) {
		document.removeEventListener('visibilitychange', visibilityHandlerContact);
		visibilityHandlerContact = null;
	}
}

function activate(button, method) {
	document.querySelectorAll('.contact-option').forEach(btn => btn.style.opacity = "0.6");
	button.style.opacity = "1";
	contactCustomerObj['action_type'] = method;
};

function updateOrderStatus() {
	contactCustomerObj['call_duration_sec'] = duration;
	contactCustomerObj['CONTROL'] = 'AddCRM';
	localStorage.removeItem(' clientData');
	// هنا AJAX لتحديث الحالة في السيرفر
	const res = sendRequest('../../inc/controller/crmOrderLog.control.php', contactCustomerObj, false);
	responseMsg(res);
	dataTableReload();
	
};

function formatDateArabic(date) {
	if (!date) return '';
	return new Date(date).toLocaleDateString('ar-EG', {
		year: 'numeric',
		month: 'long',
		day: 'numeric'
	});
};

function buildOrderMessage(order, type, extraData = null) {
    const total = (Number(order.PRICE) + Number(order.SHIP_VAL)).toFixed(2);
    const basicInfo =
		`طلب رقم ${order.subOid}
		الإجمالي: ${total} جنيه
		العنوان: ${order.ADDRESS}`;
	switch (type) {
		case 'FIRST_CONTACT_WHATSAPP':
		return `مرحباً بحضرتكم 👋 نود تأكيد ${basicInfo}
						
						يرجى التكرم بالرد لتأكيد الاستلام أو تحديد موعد مناسب.
						نشكر تعاونكم 🌹`;
						
						        case 'FIRST_CONTACT_SMS':
						            return `برجاء تأكيد طلب رقم ${order.subOid}
						الإجمالي ${total} جنيه.
						للاستفسار يرجى التواصل معنا.`;
						
						        case 'NO_ANSWER_FIRST':
						            return `تمت محاولة التواصل بخصوص ${basicInfo}
						نرجو الرد لتأكيد التوصيل.`;
	
	        case 'NO_ANSWER_REPEAT':
	            return `تمت عدة محاولات للتواصل بخصوص ${basicInfo}
						في حال عدم الرد سيتم إلغاء الطلب خلال 24 ساعة.`;
						
			case 'CONFIRMED':
				return `تم تأكيد ${basicInfo}
						وسيتم التوصيل في الموعد المحدد.
						شكراً لثقتكم بنا.`;
	
	        case 'POSTPONED':
	            return `تم تأجيل ${basicInfo}
						الموعد الجديد: ${extraData}`;

        default:
            return '';
    }
};

function makeCall(phoneNumber) {
	if(1==1){
		clientdatafromlocal['STARTCALL']= Date.now();
		localStorage.removeItem(' clientData');
		localStorage.setItem(' clientData', JSON.stringify(clientdatafromlocal));
	}			
	if (!phoneNumber) return;
	const cleaned = phoneNumber.replace(/[^0-9+]/g, '');
	window.location.href = `tel:${cleaned}`;
};

function openWhatsApp(phoneNumber, message = '') {
	if (!phoneNumber) return;
	let cleaned = String(phoneNumber).replace(/[^0-9]/g, '');
	if (cleaned.startsWith('01') && cleaned.length === 11) {
		cleaned = '2' + cleaned; // مصر
	}

	const encoded = encodeURIComponent(message);
	const url = message
		? `https://wa.me/${cleaned}?text=${encoded}`
		: `https://wa.me/${cleaned}`;
		console.log(url);
	window.open(url, '_blank');
};

function sendSMS(phoneNumber, message = '') {

    if (!phoneNumber) return;

    const cleaned = phoneNumber.replace(/[^0-9+]/g, '');
    const encoded = encodeURIComponent(message);
    const isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);

    let url = `sms:${cleaned}`;

    if (message) {
        url += isIOS ? `&body=${encoded}` : `?body=${encoded}`;
    }

    window.location.href = url;
};

function startCountdown(btn, expireTime) {

    const textEl = btn.find('.spantimer');

    btn.prop('disabled', true);
	btn.addClass('bg-warning');
    const interval = setInterval(() => {
		
        const remainingIf = Math.floor((expireTime - Date.now()) / 1000);
		let totalSeconds = Math.floor((expireTime - Date.now()) / 1000);
		let hours = Math.floor(totalSeconds / 3600);
		let minutes = Math.floor((totalSeconds % 3600) / 60);
		let seconds = totalSeconds % 60;
			
		let remaining = 
			        hours.toString().padStart(2, '0') + ':' +
			        minutes.toString().padStart(2, '0') + ':' +
			        seconds.toString().padStart(2, '0');
        if (remainingIf <= 0) {
            clearInterval(interval);
			btn.removeClass('bg-warning').addClass('bg-success');
            btn.prop('disabled', false);
            textEl.html('');
            return;
        }

        textEl.text('انتظر ' + remaining);

    }, 1000);
};

function ShipConfirmRecall() {
	const expireTime = Date.now() + (600 * 1000);
    const expireSeconds = 600; // 10 دقائق
    // نعمل الكوكي لو مش موجود
    if (!getCookie(`ShipConfirmRecall_${orderId}`)) {
        document.cookie = `${`ShipConfirmRecall_${orderId}`}=${expireTime}; max-age=${expireSeconds}; path=/; Secure; SameSite=Lax`;
    }

    $('.btnShipConfirmation').each(function () {
    	
        const btn = $(this);
        const oId = btn.data('id');
        if (orderId != oId) return;
        const remaining = getCookie(`ShipConfirmRecall_${orderId}`);
        if (remaining > 0) {
			updateOrderStatus();
            //startCountdown(btn, remaining);
        }
    });
}

function addNote() {
    Swal.fire({
        title: 'اكتب ملاحظتك',
        input: 'textarea',
        inputLabel: 'الملاحظات',
        inputPlaceholder: 'اكتب ملاحظاتك هنا...',
        inputAttributes: {
            'aria-label': 'اكتب ملاحظاتك هنا'
        },
        showCancelButton: true,
        confirmButtonText: 'حفظ',
        cancelButtonText: 'إلغاء',
        inputValidator: (value) => {
            if (!value) {
                //return 'من فضلك اكتب ملاحظة!'
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            let note = result.value;
			contactCustomerObj['notes'] = note;
			updateOrderStatus();
        }
    });
}


// ================== Start Call ==================
function startCall() {
	   	
    widget.style.display = "block";
    callMain.classList.add("active");
    startTimer();
    endCallEl.onclick = function () {
		endCall();
    }; 
}

function Contact_result(phone,order) {
	//var timerInterval;
    // افتح SweetAlert2
    Swal.fire({
        title: '📞 تم الاتصال بالعميل؟',
        showConfirmButton: false,
        showDenyButton: true,
        denyButtonText: 'إلغاء',
        allowOutsideClick: false,
        allowEscapeKey: false,
        width: 520,
        html: `<h5>وقت المكالمة : <span id="timer">${timerEl.textContent}</span></h>
            <div class="call-grid">
                <button class="call-card bg-success spen" data-status="confirmed">
						<div class="icon"><i class="fas fa-check-circle"></i></div>
						<div class="text">تأكيد الشحن</div>
				</button>
                <button class="call-card bg-warning no-answer" data-status="no_answer">
                    <div class="icon"><i class="fas fa-phone-slash"></i></div>
                    <div class="text">لم يرد</div>
                </button>
                <button class="call-card bg-danger" data-status="cancelled">
						<div class="icon"><i class="fa-solid fa-xmark"></i></div>
						<div class="text">الغاء امر البيع</div>
				</button>
                <button class="call-card phone-off" data-status="wrong_number">
                    <div class="icon"><i class="fas fa-mobile-alt"></i></div>
                    <div class="text">رقم الهاتف خطاء</div>
                </button>
                <button class="call-card phone-off" data-status="phone_off">
                    <div class="icon"><i class="fas fa-mobile-alt"></i></div>
                    <div class="text">الهاتف مغلق</div>
                </button>

                <button class="call-card bg-primary recall spen" data-status="recall">
                    <div class="icon"><i class="fas fa-phone"></i></div>
                    <div class="text">الاتصال فى وقت لاحق (بعد 10 دقائق)</div>
                </button>
            </div>
        `,
        didOpen: () => {
            // التعامل مع الضغط على الأزرار
            timerEl.textContent;
            document.querySelectorAll('.call-card').forEach(btn => {
                btn.addEventListener('click', function(){
                	let statuse = this.dataset.status;
                	contactCustomerObj['result'] = this.dataset.status;
                    contactCustomerObj['call_duration_sec'] = duration;
                    
                    if(statuse === 'recall'){
                    	ShipConfirmRecall();
                    }
                    Swal.close(); // اغلاق SweetAlert2
                    addNote();
                    
                });
            });
        }
        
        
    }).then((result) => {
		if (result.isDenied) {
			localStorage.removeItem(' clientData');
			Swal.close(); // اغلاق SweetAlert2
	    }
	});
};
// وسيلة الاتصال
function contactCustomerFlow(order) {
	clientdatafromlocal = JSON.parse(localStorage.getItem(' clientData'));
	clientNameEl.textContent = clientdatafromlocal['CLIENT_NAME'];
    clientPhoneEl.textContent = clientdatafromlocal['TELEPHONE'];
	clientAddressEl.textContent = clientdatafromlocal['ADDRESS'];
	
	const el = document.getElementById('callWidget');
	
	if (widget) {
	    const style = window.getComputedStyle(widget);
	    if (style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0') {
	        Swal.fire({icon : 'error', title: 'لايمكن بداء مكالمه جديده', html: 'من فضلك قم بإنهاء المكالمه السابقه واتخذ الاجراء المناسب ثم قم بفتح مكالمه جديده' , showConfirmButton: false, showCancelButton: true, cancelButtonText:'اغلاق',})
			return;
	    }
	}

    if (!order || !order.TELEPHONE) {
        Swal.fire('تنبيه', 'رقم العميل غير متوفر', 'warning');
        return;
    }
	
    const phone = order.TELEPHONE;
	orderId = order.oid;
	contactCustomerObj['order_id'] = order.oid;
	contactCustomerObj['action_type'] = null;
	contactCustomerObj['result'] = null;
	contactCustomerObj['call_duration_sec'] = null;
	contactCustomerObj['notes'] = null;
	contactCustomerObj['shipDate'] = '';
    // المرحلة الأولى: اختيار وسيلة التواصل
    Swal.fire({
        title: 'التواصل مع العميل',
        html: `<div class="call-details1" id="">
					<div>${clientdatafromlocal['CLIENT_NAME']}</div>
					<div>${clientdatafromlocal['TELEPHONE']}</div>
					<div>${clientdatafromlocal['ADDRESS']}</div>
				</div>
            <div style="display:flex;flex-direction:column;gap:10px;margin-top:15px;">
				
                <button id="callBtn" class="call-card bg-primary call" data-status="call">
		                <div class="icon"><i class="fas fa-phone"></i></div>
		                <div class="text">اتصال</div>
		        </button>
                <button id="waBtn" class="call-card bg-success whatsapp" data-status="whatsapp">
                		<div class="icon"><i class="fa-brands fa-whatsapp"></i></div>
                		<div class="text">واتساب</div>
                </button>
                <button id="smsBtn" class="call-card bg-info sms" data-status="sms">
                		<div class="icon"><i class="fa-solid fa-message"></i></div>
                		<div class="text">SMS</div>
                </button>
            </div>
        `, 
        showConfirmButton: false,
        showDenyButton: true,
        showCancelButton: false,
        denyButtonText: 'إلغاء',
        allowOutsideClick: false,
        didOpen: () => {
        	document.removeEventListener('visibilitychange', visibilityHandlerContact);
			visibilityHandlerContact = function() {
				if (!document.hidden && callStartTime) {
					 duration = (Date.now() - callStartTime) / 1000;
					if(duration> 0){
											   	
						startCall(order);
						document.removeEventListener('visibilitychange', visibilityHandlerContact);
					} else {
						console.log("المستخدم لم يغادر الصفحة بشكل كافي");
					}
				}else{
					if(callStartTime === null){
						callStartTime = Date.now();
					}
				}
			};
            document.getElementById('callBtn').onclick = function () {
   			   activate(this, 'call');
               makeCall(phone);
   			   document.addEventListener('visibilitychange', visibilityHandlerContact);
               Swal.close(); // اغلاق SweetAlert
            }; 
            document.getElementById('waBtn').onclick = function () {
                activate(this, 'whatsapp');
                const msg = buildOrderMessage(order, 'FIRST_CONTACT_WHATSAPP');
                openWhatsApp(phone, msg);
                Swal.close(); // اغلاق SweetAlert
            };
            document.getElementById('smsBtn').onclick = function () {
                activate(this, 'sms');
                const msg = buildOrderMessage(order, 'FIRST_CONTACT_SMS');
                sendSMS(phone, msg);
                Swal.close(); // اغلاق SweetAlert
            };
            
        }
        
       
    }).then((result) => {
	    if (result.isDenied) {
	        // إزالة الحدث عند غلق SweetAler
            if(visibilityHandlerContact ){
                document.removeEventListener('visibilitychange', visibilityHandlerContact);
                visibilityHandlerContact = null;
                callStartTime = null;
                duration = null;
            }
	    }
	});
};

document.addEventListener("DOMContentLoaded", function() {
	const dataStr = localStorage.getItem('clientData');
	const cliData = dataStr ? JSON.parse(dataStr) : null;
	if(cliData){
		console.log(cliData)
		callStartTime = cliData['STARTCALL'];
		
		clientdatafromlocal = cliData;//JSON.parse(localStorage.getItem(' clientData'));
		clientNameEl.textContent = clientdatafromlocal['CLIENT_NAME'];
	    clientPhoneEl.textContent = clientdatafromlocal['TELEPHONE'];
		clientAddressEl.textContent = clientdatafromlocal['ADDRESS'];
		const phone = cliData.TELEPHONE;
		orderId = cliData.oid;
		contactCustomerObj['order_id'] = cliData.oid;
		contactCustomerObj['action_type'] = 'call';
		startCall(cliData['oid']);
		
	}
});

/*=======================================
	start event listener
=======================================*/
document.addEventListener("DOMContentLoaded", function() {
	
	$('.select2').select2({
			dir:"rtl",
	        placeholder:'اختار من القائمة',
	        allowClear: true
	});
	
	
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
	$(document).on("click", "#AdminSupport", async function (){
		const id = $(this).attr('data-id');
	    const oidData = {[odata[id]['oid']]:odata[id]}; //{ [rowData.oid]: rowData };

	    // استخراج البيانات من الكائن
		const { oid, OSStatu, OStat, OPrint, OSid, OExists, OSMsgc, OSMsgCS, olup} = oidData[id];//rowData;
	    handleAdminUser(oid, OSid, OStat, OPrint, OSStatu, oidData);
	})
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
			localStorage.setItem('pt', ptype); 
			const newOrder = JSON.parse(localStorage.getItem('newOrder'));

			if(!newOrder){
				alert('حدث خطاء اثناء حفظ بيانات امر البيع فى ذاكرة المتصفح')
			}
						
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
	        await updateOrders(false);
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
	            updateOrders(false);
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
	$(document).on("click", "#btnOdel", async function () {
	    try {
	        const oid = $(this).attr('data-id');
	        const orderdata = odata[oid];
	        let last_update = orderdata['olup'];
			//console.log(orderdata)
	        // إنشاء محتوى SweetAlert مع Checkbox
	        let swalContent = `
	            <p>سيتم تغيير حالة أمر البيع إلى «ملغى» مع الاحتفاظ بسجل الطلب وحركاته. إذا كان عليه مبلغ محصل، يجب تسوية المبلغ أولًا.</p>
	            <div style="display: flex; align-items: center; margin-top: 10px;">
	                <input type="checkbox" id="confirmDeleteCheckbox" style="margin-right: 5px;">
	                <label for="confirmDeleteCheckbox">أؤكد إلغاء أمر البيع مع الاحتفاظ بسجله</label>
	            </div>
	        `;
	
	        let swalOptions = {
	            title: 'هل تريد إلغاء أمر البيع؟',
	            html: swalContent,
	            icon: 'warning',
	            showCancelButton: true,
	            showDenyButton: false,
	            confirmButtonColor: '#3085d6',
	            cancelButtonColor: '#d33',
	            cancelButtonText: 'إغلاق',
	            confirmButtonText: 'تأكيد الإلغاء',
	            denyButtonText: 'إغلاق',
	            didOpen: () => {
	                let confirmButton = Swal.getConfirmButton();
	                confirmButton.disabled = true; // تعطيل زر التأكيد في البداية
	                
	                // التحقق من الـ Checkbox لتفعيل زر التأكيد
	                document.getElementById("confirmDeleteCheckbox").addEventListener("change", function () {
	                    confirmButton.disabled = !this.checked;
	                });
	
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
	        }
	    } catch (error) {
	        console.log(error);
	    }
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
	$(document).on("click", "#btnOservice", function () {
	    
	    try {
	        const id = $(this).attr('data-id');
	        const oidData = {[odata[id]['oid']]:odata[id]}; //{ [rowData.oid]: rowData };

	        // استخراج البيانات من الكائن
	        const { oid, OSStatu, OStat, OPrint, OSid, OExists, OSMsgc, OSMsgCS, olup} = oidData[id];//rowData;
	        
	        // التحقق من نوع المستخدم (إداري أو غير إداري)
	        if (ptype == 1) {
	        	handleAdminUser(oid, OSid, OStat, OPrint, OSStatu, oidData);
	        } else {
	        	handleNonAdminUser(oid, OSid, OStat, OPrint, OSStatu, olup);
	        }
	        
	    } catch (error) {
	        console.error("Error handling button click:", error);
	    } finally {
	    	//updateOrders();
	    }
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
	$(document).on("click", "#btnRefund", function() {
		alert("هذا الاجراء قيد التطوير")
	});
	$(document).on("click", "#btnOedit_old", function () {
		const oedata = odata
		let oid = $(this).attr('data-id');
		let on = oedata[oid]['subOid'];
		console.log(oedata)
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
	$(document).on("click", "#btnNewOrderExchange", function () {
		var rowData = table.row($(this).closest('table').closest('td').closest('tr')).data();
		
		
		const orderDate = new Date();
		const oedata = odata
		let oid = $(this).attr('data-id');
		let on = oedata[oid]['subOid'];
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
			"shipmax":rowData.SHIPPING_RATE,
			"stoid":rowData.stoid,
			"cityid":rowData.CI_ID,
			"cliComment":"",
			"shipval":rowData.SHIP_VAL,
			"shipDays":rowData.SHIPPING_TIME_MAX,
			"shipWaiting":rowData.SHIPPING_TYPE,
			"shipDate": new Date(addDays(orderDate.toLocaleDateString('en-US', "yy-mm-dd"),rowData.SHIPPING_TIME_MAX)),
			"orderExchange":rowData.oid
		};
		var log =`*تم انشاء امر استبدال رقم ${rowData['subOid']} للعميل ${rowData['CLIENT_NAME']} رقم الهاتف ${rowData['TELEPHONE']} العنوان ${rowData['ADDRESS']}`;
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
	/*$(document).on("click", "#btnNewOrderExchange", function () {
		var rowData = table.row($(this).closest('table').closest('td').closest('tr')).data();
		
		
		const orderDate = new Date();
		const oedata = odata
		let oid = $(this).attr('data-id');
		let on = oedata[oid]['subOid'];
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
			"shipmax":rowData.SHIPPING_RATE,
			"stoid":rowData.stoid,
			"cityid":rowData.CI_ID,
			"cliComment":"",
			"shipval":rowData.SHIP_VAL,
			"shipDays":rowData.SHIPPING_TIME_MAX,
			"shipWaiting":rowData.SHIPPING_TYPE,
			"shipDate": new Date(addDays(orderDate.toLocaleDateString('en-US', "yy-mm-dd"),rowData.SHIPPING_TIME_MAX)),
			"orderExchange":rowData.oid
		};
		var log =`*تم انشاء امر استبدال رقم ${rowData['subOid']} للعميل ${rowData['CLIENT_NAME']} رقم الهاتف ${rowData['TELEPHONE']} العنوان ${rowData['ADDRESS']}`;
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
	});*/
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
	$(document).on("click", "#btnShipp", function () {
	    let lastResult, countResults = 0;
	    const html = $('#swlSlectOrdersTmp').html();
	    const dt = odata
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
	                if(orderidarr.Shipp[key]['OSMsgCS'] >0){
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
	    // الاوامر المحدده
	    const confirmCallbackA = () =>{
	    //الاوامر المحدده
	            let  eprint = 0
	            if(!Object.keys(orderidarr.Shipp).length){
	                for(const key in odata){
	                    if(odata[key]['OPrint']){
	                    	let {olup} = odata[key];
	                        dtoid[key] = {'olup':olup};
	                    }else{
	                        eprint++;
	                    }
	                }
	            }else{
	            	
	            	
	                for(const key in orderidarr.Shipp){
	                	
	                    if(odata[key]['OPrint']){
	                        //dtoid[key] = orderidarr.Shipp[key];
	                        let {olup} = orderidarr.Shipp[key];
	                        dtoid[key] = {'olup':olup};
	                    } else {
	                        eprint++;
	                    }
	                }
	            }
	            if(Object.keys(dtoid).length>0){
	                deliveryConfirmation(dtoid);
	                $('#btnShipp .navbar-badge').html('');
	                $('.btnSelectOShipp').attr('disabled',false);
	                
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
	    //كل الاوامر
	    const confirmCallbackB = () =>{
	    //كل الاوامر
	        
	        for(const key in odata){
	            if(odata[key]['OPrint']){
	            	let {olup} = odata[key];
	                dtoid[key] = {'olup':olup};
	            }
	        }
	       
	        if(Object.keys(dtoid).length>0 ){
	            // البيانات التي سترسلها في طلب POST
	            deliveryConfirmation(dtoid);
	            $('#btnShipp .navbar-badge').html('');
	            $('.lastbtn').attr('disabled',false);
	            
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
	        $('.btnSelectOShipp').attr('disabled',false);
	        //updateOrders();
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
			let {olup} = odata[oid];
			const ordata =  {'olup':olup};
			dtoid = {};
			dtoid[oid] = ordata;
			refundEmployeeCustody(dtoid);
		} catch (error) {
	        console.log(error);
	    }
	});
	$(document).on("click", "#btnDelevered", function () {
		oid = $(this).attr('data-id');
		if(oid){
			const ordata =  odata[oid];
			
			dtoid={};
			dtoid[oid] = ordata;
			
			if(ordata['custstat']=='FREEZE'){
				ShipmentApproved(ordata);
			}else{
				ShipmentDelivery(dtoid);
			}
		}else{
			responseMsg(['warning','نعتذر','غير مسموح بتنفيذ هذا الاجراء فى الوقت الحالى',[]]);
			
		}
		
	});
	$(document).on("click", "#btnReceivingReturns" ,function () {
		let oid = $(this).attr('data-id');
		const ordata =  odata[oid];
		ShipmentApproved(ordata);
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
		dataTableReload();
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
		 
		dataTableReload();
	});
	$(document).off("click", "#btnShipConfirmation").on("click", "#btnShipConfirmation", function () {
		const id = $(this).attr('data-id');
	    const oidData = {[odata[id]['oid']]:odata[id]}; //{ [rowData.oid]: rowData };
		//let rowData = oidData[id];//table.row($(this).closest('table').closest('td').closest('tr')).data();
		// احصل على البيانات من الصف
		let rowData = table.row($(this).closest('table').closest('td').closest('tr')).data();
		
		// خزنها في localStorage بعد تحويلها إلى JSON
		localStorage.setItem(' clientData', JSON.stringify(rowData));

		contactCustomerFlow(rowData);
	});
	$(document).on("click", "#btnReactivation", function () {
		try {
			const oid   = $(this).attr('data-id');
			const dt	= odata
			let {olup}	= odata[oid];
			const ordata=  {'olup':olup};
			dtoid		= {};
			dtoid[oid]	= ordata;
			Reactivation(dtoid);
		} catch (error) {
	        console.log(error);
	    }
	});
	
	/*====================================================
	 check edit Order
	=====================================================*/
    let eorder  = JSON.parse(localStorage.getItem('editON'));
    let df_input= '';
	const myInterval = setInterval(ru, 1000);
	function ru  (){
		if(eorder && eorder[0] !='' && ( (ptype == 1 && Exists == 1) || (ptype == 3 && Exists == 6))  ){
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