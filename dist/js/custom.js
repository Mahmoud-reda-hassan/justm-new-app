
/*==========================================
*	dataTable defaults
* ==========================================*/
document.addEventListener('DOMContentLoaded', function () {
      
});
// اظهار علامة التحميل
function showSpinner() {
	document.getElementById("overlay2").style.display = "flex";
}
			
// إخفاء علامة التحميل
function hideSpinner() {
	document.getElementById("overlay2").style.display = "none";
}

//const responseTimeout = 5000; // الزمن المسموح للاستجابة (3 ثوانٍ)
//let isTestingInternet = false; // لمنع تكرار اختبار الاتصال أثناء وجود اختبار نشط
        
/**
* دالة لاختبار الاتصال بخادم معين عبر Ping.
* @param {string} url - عنوان الخادم لاختباره.
* @returns {Promise<number>} - زمن التأخير (latency).
*/
/*async function pingServer(url) {
	try {
		const delta = await ping(url);
		return delta; // تعيد النتيجة
	} catch (error) {
		console.log(String(error)); // تعرض رسالة خطأ
		return null; // يمكن إعادة قيمة افتراضية عند حدوث خطأ
	}
}*/

/**
* دالة لعرض رسالة خطأ اسفل navbar.
* @param {string} title - عنوان الرسالة.
* @param {string} text - نص الرسالة.
*/
/*const displayError = function (type= 'w' ,title, text) {
	if(!$('#notificationMSG').is(':visible')){
		$('#notificationMSG').removeClass('bg-warning , bg-danger');
		if(type === 'e'){
			$('#notificationMSG').addClass('bg-danger');
			$('#notificationMSG .notification-icon').removeClass('fa-exclamation-triangle,fa-check')
			$('#notificationMSG .notification-icon').addClass('fa-tims')
		}else if(type === 'w'){
			$('#notificationMSG').addClass('bg-warning');
			$('#notificationMSG .notification-icon').removeClass('fa-tims,fa-check')
			$('#notificationMSG .notification-icon').addClass('fa-exclamation-triangle')
		}else if(type ==='s'){
			$('#notificationMSG').addClass('bg-success');
			$('#notificationMSG .notification-icon').removeClass('fa-tims ,fa-exclamation-triangle')
			$('#notificationMSG .notification-icon').addClass('fa-check')
		}
		$('#notificationMSG .notification-title').html(title);
		$('#notificationMSG .notification-message').html(text);
		$('#notificationMSG').fadeIn();
		setTimeout(function () {
			$('#notificationMSG').fadeOut();
		}, responseTimeout);
	}
} */
/**
* دالة لتكرار التحقق من الاتصال بالإنترنت.
* - تكرار التحقق من حالة الاتصال بالخادم والإنترنت.
*/
/*async function intrvalCheck(){
        	let intervalId = setInterval(async () => {
			    console.log(1);
			    let statusScore = 0;
			
			    // التحقق من حالة الاتصال بالإنترنت
			    if (navigator.onLine) {
			        displayError('s', 'نجح', 'تم استعادة الاتصال بالإنترنت');
			        statusScore++;
			    }
			
			    if (statusScore > 0) {
			        try {
			            // قياس تأخير الإنترنت
			            const latencyInternet = await pingServer('https://google.com');
			            if (latencyInternet > 800) {
			                statusScore++;
			            }
			
			            // قياس تأخير الخادم
			            const latencyServer = await pingServer('https://just-m.net');
			            if (latencyServer > 1500) {
			                statusScore++;
			            }
			        } catch (error) {
			            console.error("خطأ أثناء قياس التأخير:", error);
			            statusScore = 0; // إعادة تعيين الحالة عند الخطأ
			        }
			    }
			
			    // تحديد الحالة بناءً على النقاط
			    if (statusScore === 3) {
			    	isTestingInternet = false;
			    	displayError('w', 'هناك تأخير كبير في زمن استجابة الخادم', '');
			    	clearInterval(intervalId); // إيقاف التكرار عند استعادة الاتصال
			    } else if (statusScore === 2) {
			    	isTestingInternet = false;
			        displayError('w', '', 'هناك بطء في اتصال الإنترنت الخاص بك.');
			    	clearInterval(intervalId); // إيقاف التكرار عند استعادة الاتصال
			    } else if (statusScore === 0) {
			        displayError('e', 'تحقق من حالة اتصالك بالإنترنت', '');
			    } else {
			        displayError('s', '', 'تم استعادة الاتصال بالإنترنت');
			        clearInterval(intervalId); // إيقاف التكرار عند استعادة الاتصال
			        isTestingInternet = false;
			    }
			}, responseTimeout);
}*/

/**
* دالة لاختبار الاتصال بالإنترنت.
* - تتحقق من حالة الاتصال بالخادم والإنترنت.
*/
/*async function testInternetConnection() {
	
	if (isTestingInternet) {
		console.log('تم ايقاف اختبار الانترنت')
		return;
	}

	isTestingInternet = true;

	if (!navigator.onLine) {
		displayError('e','غير متصل',  'يرجى التحقق من اتصالك بالإنترنت.');
		isTestingInternet = false;
		intrvalCheck();
		return false;
    }

	try {

		const latencyInternet = await pingServer('https://google.com');
		if (latencyInternet > 1500 ) {
			displayError('w','انتبه', 'هناك بطئ فى اتصال الانترنت الخاص بك.');
			isTestingInternet = false;
			return false;
		}
		
		const latencyServer   = await pingServer('https://just-m.net');
		if(latencyServer>latencyInternet){
 			displayError('e','خطاء فى زمن الاستجابه','هناك تأخير كبير فى زمن رد الخادم. .');
			isTestingInternet = false;
			return false;
		}
		
		$('#notificationMSG').hide();
		console.log('الاتصال مستقر.');
		isTestingInternet = false;
		return true;

	} catch (err) {
		displayError('e','خطاء فى الاتصال','فشل في اختبار اتصال الإنترنت.');
		intrvalCheck();
		return false;
	}
}*/

/*
هذ الملف يحتوى على كل الاكواد الاضافية من المطور بلغة
javascript
*/ 
/*============================================
** variable's global scope
=============================================*/
const ptype		   = pt;
const characters_random ='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',
    queryString    = window.location.search,
	urlParams      = new URLSearchParams(queryString),
	Go             = urlParams.get('Go'),
	ordernum       = urlParams.get('on'),
	subOrderid     = urlParams.get('sid'),
	Exists         = urlParams.get('ex'),
	oService       = urlParams.get('os'),
	oCustody	   = urlParams.get('cu'),
	partnertype    = urlParams.get('ptr'),
	ost			   = '' | urlParams.get('st'),
	ordertype      = ptype,
	logout         = urlParams.get('logout'),
	urlControl     = '../../inc/Api.php',
	urlprint       = '../../inc/generatPdf.php',
	urlApi 	       = urlControl,/////////////
	date           = new Date(),
	//userlogin_data = sendRequestGET(`${urlApi}?CONTROL=valSess`),
	rqof           = sendRequestGET(`${urlApi}?CONTROL=filterOrder`, false),
    
    userlogin_data = rqof[2],
    orderfilter    = rqof[0],
    existsview     = rqof[1],
    
    // التحقق من نوع الجهاز ===========
	isMobile = function () {
    	return /Android|iPhone|iPad|iPod|Opera Mini|IEMobile|WPDesktop/i.test(navigator.userAgent);
	},
	filobj         = {'pType'    :ptype,
                		'orderType'     :{'id': '','val': ''},
                		'orderType'		:{'id': '','val': ''},
                		'shippingType'	:{'id': '','val': ''},
                		'exists'		:{'id': '','val': ''},
                		'statuse'		:{'id': '','val': ''},
                		'category'		:{'id': '','val': ''},
                		'products'		:{'id': '','val': ''},
                		'dateType'		:{'id': '','val': ''},
                		'dateRange'		:{'id': '','val': ''},
                		'zones'	        :{'id': '','val': ''}
                	 },
	option1        = {
                		weekday: 'long',
                		year: 'numeric',
                		month: 'long',
                		day: 'numeric'
	                },
	option2        = {
                		year: 'numeric',
                		month: 'numeric',
                		day: 'numeric'
            	    },
	Toast          = Swal.mixin({
                    	  toast: true,
                    	  position: 'top-start',
                    	  iconColor: 'white',
                    	  customClass: {
                    		popup: 'colored-toast '
                    	  },
                    	  didOpen: (toast) => {
                    		toast.addEventListener('mouseenter', Swal.stopTimer)
                    		toast.addEventListener('mouseleave', Swal.resumeTimer)
                    	  },
                    	  showConfirmButton: false,
                    	  timer: 3500,
                    	  timerProgressBar: true,
                    	  showCloseButton: false
	                }),
	wls            = queryString.replace(/&/g,`_`).replace(/=/g,'_').replace('?','_'),
/*=========================================================
viewOrder كل وظائف عرض اوامر البيع
=========================================================*/
	responseMsg				= (response,view = [], reloadTbl = false) => {
		Swal.fire({
        title: response[1],
        html : `<div>${response[2]}</div>
                <div id="orderrejected"></div>`,
        icon : response[0],
        confirmButtonText: 'موافق',
        didOpen : ()=>{
            function sticon(val){
                let rx = '<i class="fa-solid fa-check text-success"></i>'
                if(!val) {
                    rx = '<i class="fa-solid fa-xmark text-danger"></i>';
                }
                return rx;
            }
            if(Object.keys(response[3] || {}).length  && (response[0] === "error" || response[0] === "warning")){
	            let tablehtml ='',tablerow ='';
	            const res = response[3];
	            const viewArr = [];
	            const vicon = (view)=>{
	            	if(!(view in viewArr)){
	            		viewArr.push(view);
	            	}
	            };
	            
	            for (let key in res) {
	            	
	                tablerow += `<tr>`
	                tablerow +=`<td class="col-12 text-left">${res[key]['osubid']}</td>`
	            	if(typeof res[key] == 'array' || typeof res[key] == 'object'){
	                if("lastup" in res[key]){
	                	tablerow +=`<td class="col-12">${sticon(res[key]['lastup'])}</td>`;
	                	vicon('lastup');
	                }
	                if("oserv" in res[key]){
	                	tablerow +=`<td class="col-12">${sticon(res[key]['oserv'])}</td>`;
	                	vicon('oserv');
	                }
	                if('status' in res[key]){
	                	tablerow +=`<td class="col-12">${sticon(res[key]['status'])}</td>`;
	                	vicon('status');
	                }
	                if('print' in res[key]){
	                	tablerow +=`<td class="col-12">${sticon(res[key]['print'])}</td>`
	                	vicon('print');
	                }
	                if('statuse' in res[key]){
	                	tablerow +=`<td class="col-12">${sticon(res[key]['statuse'])}</td>`
	                	vicon('statuse');
	                }
	                if('store' in res[key]){
	                	tablerow +=`<td class="col-12">${sticon(res[key]['store'])}</td>`
	                	vicon('store');
	                }
	                
	                if('freeze' in res[key]){
	                	tablerow +=`<td class="col-12">${sticon(res[key]['freeze'])}</td>`
	                	vicon('freeze');
	                }
	                tablerow +=`</tr>`;
	            }
	            
	           
	            tablehtml = `<hr style="margin:5px 0px 5px 0px">
	            <div class="text-left ml-2" style="font-size:0.85rem;">`;
	                if( viewArr.includes('lastup')){
	                	tablehtml += `<div style="margin:-4px 0px 0px 0px"><i class="fa-solid fa-arrows-rotate text-primary"></i><span class="ml-2 text-info">  لايوجد اى تحديث بيانات لهذا الامر اثناء عملك</span></div>`;
	                }
	                if( viewArr.includes('oserv')){
	                	tablehtml += `<div style="margin:-4px 0px 0px 0px"><i class="fas fa-headset text-primary"></i><span class="ml-2 text-info"> مسموح التحويل اثناء وجود طلبات مفتوحة لخدمة العملاء</span></div>`;
	                }
	                if( viewArr.includes('print')){
	                	tablehtml += `<div style="margin:-4px 0px 0px 0px"><i class="fa-solid fa-print text-primary"></i><span class="ml-2 text-info"> طباعة ملصق الشحن قبل التحويل الى تم التجهيز</span></div>`;
	                }
	                if( viewArr.includes('statuse')){
	                	tablehtml += `<div style="margin:-4px 0px 0px 0px"><i class="fas fa-traffic-light text-primary ml-1"></i><span class="ml-2 text-info"> مسموح بلتحويل من الحالة الحالية الى الحالة المحدده</span></div>`;
	                }
	                if( viewArr.includes('store')){
	                	tablehtml += `<div style="margin:-4px 0px 0px 0px"><i class="fas fa-warehouse text-primary"></i><span class="ml-2 text-info"> المنتجات متوفرة بالمخزن</span></div>`;
	                }
	                if( viewArr.includes('freeze')){
	                	tablehtml += `<div style="margin:-4px 0px 0px 0px"><i class="fa-solid fa-lock-open text-primary"></i><span class="ml-2 text-info">مسموح لموظف الشحن تسليم امر البيع للعميل </span></div>`;
	                }
	            tablehtml += `</div>
	            <hr style="margin:0px 30px 0px 30px"><div class="text-center mt-2 text-primary"  style="font-size:0.85rem;"> علامة  <i class="fa-solid fa-check text-success"></i> تعنى نجح  وعلامة <i class="fa-solid fa-xmark text-danger"></i> تعنى فشل </div>
	            <table class="mt-1 table table-hove table-responsive w-100 text-nowrap" style="max-height:400px;">
	                <thead>
	                    <tr>`;
			                tablehtml += `<th class="text-left">رقم الامر</th> `;
		                    if( viewArr.includes('lastup')){
		                    	tablehtml += `<th><i class="fa-solid fa-arrows-rotate"></i></th>`;
		                    }
		                    if( viewArr.includes('oserv')){
		                    	tablehtml += `<th><i class="fas fa-headset"></i></th>`;
		                    }
		                    if( viewArr.includes('print')){
		                    	tablehtml += `<th><i class="fa-solid fa-print"></i></th>`;
		                    }
		                    if( viewArr.includes('statuse')){
		                    	tablehtml += `<th><i class="fas fa-traffic-light"></i></th>`;
		                    }
		                    if( viewArr.includes('store')){
		                    	tablehtml += `<th><i class="fas fa-warehouse"></i></th>`;
		                    }
		                    if( viewArr.includes('freeze')){
		                    	tablehtml += `<th><i class="fa-solid fa-lock-open"></i></th>`;
		                    }
		                    console.log(viewArr);
	                	tablehtml += `</tr>
	                </thead>
	                <tbody class="tbody" style="font-size:0.90rem;">
	                	${tablerow}
	                </tbody>
	            </table>`;
	            $("#orderrejected").html(tablehtml);
            }
            }
            if(response[0] !== "error" && reloadTbl){
            	updateOrders();
            }
        }
    })
},
   /* orderdataview           = function (orderID){
    	
    	
        let oidarr = orderID,
            oidstr = orderID;
        if(typeof orderID == 'string'){
            oidarr = orderID.split(",");
        }else if(typeof orderID == 'array'){
            oidstr = orderID.toString()
        }
        
       
        	
        	let orderDataRQ =  sendRequestGET(`${urlApi}?CONTROL=orderView&order_id=${orderID}`);
        	console.log(orderDataRQ);
        	const itemArr = {};
	        const olength = oidarr.length;
	        if(olength>1){
	            
	        }
	        for(key in orderDataRQ){
	            if(olength > 1){ 
	                delete orderDataRQ[key]['orderAction'];
	            }
	            for(proCode in orderDataRQ[key]['orderPro']) {
	                let store ="UQTY";
	                if(ptype==1 && orderDataRQ[key].orderData.exists_id < 7){
	                    store='AQTY';
	                } else if(ptype==1 && orderDataRQ[key].orderData.exists_id ==7){
		                store='PQTY';
		            } else if(ptype==1 && orderDataRQ[key].orderData.exists_id > 7){
		            	store='SQTY'
		            }
	                const productId       = orderDataRQ[key]['orderPro'][proCode]['pro_id'];
	                const productCode     = orderDataRQ[key]['orderPro'][proCode]['PRO_CODE'];
	                itemArr[productCode]  = {
	                    qty             : orderDataRQ[key]['orderPro'][proCode].qty,
	                    deliveredQty    : orderDataRQ[key]['orderPro'][proCode].deliveredQty,
	                    returnedQty     : orderDataRQ[key]['orderPro'][proCode].returnedQty,
	                    dis             : orderDataRQ[key]['orderPro'][proCode].dis,
	                    PROMDIS         : 20,
	                    STQTY           : orderDataRQ[key]['orderPro'][proCode][store],//productCodeData.STORSQTY[orderData.orderData.stoid],
	                    CAT_NAME        : orderDataRQ[key]['orderPro'][proCode].CAT_NAME,
	                    PER_BOOKING     : orderDataRQ[key]['orderPro'][proCode].PER_BOOKING,
	                    PRO_VISBLE      : orderDataRQ[key]['orderPro'][proCode].PRO_VISBLE,
	                    PRO_DESCRIPTION : orderDataRQ[key]['orderPro'][proCode].PRO_DESCRIPTION,
	                    PRO_NAME        : orderDataRQ[key]['orderPro'][proCode].PRO_NAME,
	                    PRO_IMG_LINK    : orderDataRQ[key]['orderPro'][proCode].PRO_IMG_LINK,
	                    HEIGHT          : orderDataRQ[key]['orderPro'][proCode].HEIGHT,
	                    LENGTH          : orderDataRQ[key]['orderPro'][proCode].LENGTH,
	                    WEIGHT          : orderDataRQ[key]['orderPro'][proCode].WEIGHT,
	                    PRODUCT_ID      : productId,
	                    PRODUCT_ID      : orderDataRQ[key]['orderPro'][proCode].pro_id,
	                    PRO_CODE        : orderDataRQ[key]['orderPro'][proCode].PRO_CODE,
	                    PRODUCT_CODE_ID : orderDataRQ[key]['orderPro'][proCode].PRODUCT_CODE_ID,
	                    OPTION_VAL_ID   : orderDataRQ[key]['orderPro'][proCode].OPTION_ID,
	                    OPTION_TEXT     : orderDataRQ[key]['orderPro'][proCode].OPTION_TEXT,
	                    CODE_PRICE      : orderDataRQ[key]['orderPro'][proCode].CODE_PRICE,
	                    CODE_MIN_STOCK  : orderDataRQ[key]['orderPro'][proCode].CODE_MIN_STOCK,
	                    CODE_MAX_STOCK  : orderDataRQ[key]['orderPro'][proCode].CODE_MAX_STOCK,
	                    CODE_COST_PRICE : orderDataRQ[key]['orderPro'][proCode].CODE_COST_PRICE,
	                };
	            };
	            orderDataRQ[key]['orderPro']=itemArr;
	        };
	        
	       hideSpinner();
	       console.log(orderDataRQ);
	       return orderDataRQ;
   
       
        
         
    },*/
    orderdataview = function (orderID) {

    let oidarr = orderID;

    if (typeof orderID === 'string') {
        oidarr = orderID.split(",");
    } else if (Array.isArray(orderID)) {
        oidarr = orderID;
    }

    const orderDataRQ = sendRequestGET(
        `${urlApi}?CONTROL=orderView&order_id=${orderID}`
    );

    const olength = oidarr.length;

    for (let key in orderDataRQ) {

        if (!orderDataRQ.hasOwnProperty(key)) continue;

        const orderObj   = orderDataRQ[key];
        const orderData  = orderObj.orderData;
        const orderPro   = orderObj.orderPro;
        const itemArr    = {};

        if (olength > 1) {
            delete orderObj.orderAction;
        }

        // تحديد نوع المخزون مرة واحدة فقط
        let store = "UQTY";

        if (ptype == 1) {
            if (orderData.exists_id < 7) store = "AQTY";
            else if (orderData.exists_id == 7) store = "PQTY";
            else if (orderData.exists_id > 7) store = "SQTY";
        }

        for (let proCode in orderPro) {

            if (!orderPro.hasOwnProperty(proCode)) continue;

            const product = orderPro[proCode];

            itemArr[proCode] = {
                qty: product.qty,
                deliveredQty: product.deliveredQty,
                returnedQty: product.returnedQty,
                dis: product.dis,
                PROMDIS: 20,
                STQTY: product[store],
                CAT_NAME: product.CAT_NAME,
                PER_BOOKING: product.PER_BOOKING,
                PRO_VISBLE: product.PRO_VISBLE,
                PRO_DESCRIPTION: product.PRO_DESCRIPTION,
                PRO_NAME: product.PRO_NAME,
                PRO_IMG_LINK: product.PRO_IMG_LINK,
                HEIGHT: product.HEIGHT,
                LENGTH: product.LENGTH,
                WEIGHT: product.WEIGHT,
                PRODUCT_ID: product.pro_id,
                PRO_CODE: product.PRO_CODE,
                PRODUCT_CODE_ID: product.PRODUCT_CODE_ID,
                OPTION_VAL_ID: product.OPTION_ID,
                OPTION_TEXT: product.OPTION_TEXT,
                CODE_PRICE: product.CODE_PRICE,
                CODE_MIN_STOCK: product.CODE_MIN_STOCK,
                CODE_MAX_STOCK: product.CODE_MAX_STOCK,
                CODE_COST_PRICE: product.CODE_COST_PRICE
            };
        }

        orderObj.orderPro = itemArr;
    }

    hideSpinner();

    return orderDataRQ;
},
    formatPrice             = (price)=>{
        return parseFloat(price).toFixed(2);
    },
    formatOptionText        = (optionText)=>{
        return optionText.join(' | ');
    },
    calculateTotalPrice     = (orderPro)=>{
      let sumTotal = 0;
      for (let i in orderPro) {
        const codePrice = orderPro[i].CODE_PRICE;
        const dis = orderPro[i].dis;
        const qty = orderPro[i].qty;
        const totalPrice = (codePrice - dis) * qty;
        sumTotal += totalPrice;
      }
      return formatPrice(sumTotal);
    },
    generateOrderActionList = (orderAction)=>{
      let tblhtml = '';
      for (let i = orderAction.length - 1; i >= 0; i--) {
        const log = orderAction[i].log.split('*');
        const user = orderAction[i].user;
        const date = orderAction[i].date;
        const time = orderAction[i].time;
        let li = '';
        tblhtml += `<li class="font-weight-bold text-left text-secondary"><div>${log[0]}</div>
                        <span class="text-warning mt-0 pt-0" style="font-size:11px">${user} | ${date} | ${time}</span>`;
        for (let x = 1; x < log.length; x++) {
          li += '<li class="font-weight-lighter font-italic text-info text-left" style="font-size:14px">' + log[x] + '</li>';
        }
        if (log.length > 1) {
          tblhtml += '<ul class="">' + li + '</ul>';
        }
        tblhtml += '</li><hr class="ml-0 pl-0">';
      }
      return tblhtml;
    },
    /*====================================================================================================
function order and product
======================================================================================================*/
	shippingDateStr = function(shippDate, shipWaiting, deliveredDate = null) {
    const option1 = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const shipDate = new Date(shippDate);
    const today = new Date();
    const formattedShipDate = shipDate.toLocaleDateString('ar-EG', option1);
    deliveredDate = deliveredDate =="غير متاح" ? null : deliveredDate;
    // لو الشحنة تم تسليمها
    if (deliveredDate) {
        const delivered = new Date(deliveredDate);
        const formattedDelivered = delivered.toLocaleDateString('ar-EG', option1);

        // لو التسليم بعد التاريخ المحدد
        if (delivered > shipDate) {
            return `<span class="text-success font-weight-bold">
                        تم تسليم الشحنة في ${formattedDelivered} والمفترض التسليم كان في ${formattedShipDate}
                    </span>`;
        } else {
            // التسليم قبل أو في نفس التاريخ → نعرض بس تاريخ التسليم
            return `<span class="text-success font-weight-bold">
                        تم تسليم الشحنة في ${formattedDelivered}
                    </span>`;
        }
    }

    // حساب الفرق بالأيام
    const diff = shipDate.getTime() - today.getTime();
    const TotalDays = Math.ceil(diff / (1000 * 3600 * 24));

    // لو متأخرة
    if (TotalDays < 0) {
        return `<span class="text-danger font-weight-bold">
                    الشحنة متأخرة منذ ${Math.abs(TotalDays)} يوم
                    (كان الحد الأقصى ${formattedShipDate})
                </span>`;
    }

    // لو اليوم هو يوم التسليم
    if (TotalDays === 0) {
        return `من المتوقع تسليم الشحنة اليوم (${formattedShipDate})`;
    }

    // لو باقي أيام للتسليم
    if (shipWaiting > 0) {
        return `ستصل الشحنة خلال ${TotalDays} يوم بحد أقصى ${formattedShipDate}`;
    } else {
        return `ستصل الشحنة يوم ${formattedShipDate}`;
    }
},
    closedTiketSuport       = async (osid, select=false)=>{
        if(!select){
            const steps = ['1']
            const Queue = Swal.mixin({
              progressSteps: steps,
              confirmButtonText:'« التالى',
              showClass: { backdrop: 'swal2-noanimation' },
              hideClass: { backdrop: 'swal2-noanimation' }
            })
            await Queue.fire({
              title: 'هل تريد انهاء هذا الطلب',
              showCancelButton: true,
              confirmButtonText:'اغلاق الطلب نهائيا',
              cancelButtonText: 'ترك الطلب مفتوح',
              //currentProgressStep: 0,
              
              // optional class to show fade-in backdrop animation which was disabled in Queue mixin
              showClass: { backdrop: 'swal2-noanimation' },
            }).then((result) => {
                if(result.isConfirmed){
                    const x = sendRequest(urlControl,{'CONTROL':'OrderServeiceClose','skip':0,'osid':osid}, false);
                    console.log(x)
                    updateOrders()
                    Toast_fire(x);
                    
                } else {
                    updateOrders()
                }
                if(select){
                	actionSupport()
                }
                
            })
        }else{
            const x = sendRequest(urlControl,{'CONTROL':'OrderServeiceClose','ptype':ptype,'skip':1,'osid':osid}, false);
            updateOrders();
            Toast_fire(x)
        }
    },
    tableScrollToROW		= ()=>{
        const table = document.querySelector("#tbl");
        const row = table.querySelector("tr:nth-child(2)");
        table.scrollToRow(row);
    },
    openTiketSupport        = async (orderid,orderlastupdate,OStat)=>{
        //const dt = table.ajax.json();

       // const data = dt.odata[orderid];
        const obj ={'CONTROL':'addOrderServeiceOS','orderid':orderid,'lu':orderlastupdate,'ptype':ptype,'ostid':0,'ostxmsg':''};
        let retunRQ = '';
        const inputOpions = orderfilter['oservice'];
        const steps = ['1', '2'];
        const Queue = Swal.mixin({
          progressSteps: steps,
          confirmButtonText:'« التالى',
          showClass: { backdrop: 'swal2-noanimation' },
          hideClass: { backdrop: 'swal2-noanimation' }
        })
        
        if(OStat<=1 || OStat ==5){
            await Queue.fire({
              title: 'اختار نوع الطلب',
              currentProgressStep: 0,
              input: 'radio',
              inputOptions: orderfilter['oservice'],
              inputValidator: (value) => {
                  if (!value) {
                    return 'من فضلك اختار نوع الطلب اولا ثم اضغط التالى!';
                  } else {
                    obj['ostid'] = value;
                  }
               },
              // optional : class to show fade-in backdrop animation which was disabled in Queue mixin
              showClass: { backdrop: 'swal2-noanimation' },
            }).then(async (value)=>{
                if(value.isConfirmed){
                   await Queue.fire({
                      title: 'ادخل رسالتك',
                      currentProgressStep: 1,
                      input: 'textarea',
                      inputPlaceholder: 'اكتب رسالتك هنا',
                      showCancelButton: true,
                      confirmButtonText:'حفظ وارسال',
                      cancelButtonText: 'الغاء',
                      inputValue :  ` نوع الطلب : ${orderfilter['oservice'][value.value]}`,
                      inputValidator: (value) => {
                          if (!value) {
                            return 'من فضلك ادخل ملاحظاتك !';
                          }
                       },
                      preConfirm:(value) => {
                          obj['ostxmsg'] = value;
                          retunRQ = sendRequest(urlControl,obj, false);
                          return retunRQ;
                      },
                      showClass: { backdrop: 'swal2-noanimation' }
                    }).then((result) => {
                        if(result.isConfirmed){
                        //	console.log(retunRQ)
                            if(retunRQ[0] == 's'){
	                            Swal.fire({
	                                icon: 'success',
	                                text: retunRQ[1],
	                            });
                            }else{
	                            Swal.fire({
	                                icon: 'error',
	                                text: retunRQ[1],
	                            });
                            }
                            updateOrders();
                        }
                        
                    })
                }
            })
        }else{
            Swal.fire(
                'عفواً',
                'لايمكن فتح طلب جديد .. حالة الامر لاتسمح بذلك',
                'warning'
            );
        }
    
    
},
    scrollToBottom          = (target, speed = 600)=>{
        target.click(() => {
            target.stop();
        });
        target.on("mousedown touchstart", function() {
            target.stop();
        });
          
        target.on("mouseup touchend", function() {
            target.stop();
        });
        target.animate({ scrollTop: target.prop('scrollHeight') }, speed);
    },
    sendMessage             = (osid,ostxmsg,ptype)=>{
        const obj = {
            'CONTROL':'OrderServeiceSENDMSG',
            'osid': osid,
            'ostxmsg': ostxmsg,
            'ptype': ptype
        },
        ms = $('#msgtemp1'),
        md = $('#swlTicket .msgdir'),
        mu1= $('#msgtemp1 .msguser1'),
        mt1= $('#msgtemp1 .msgtime1'),
        mtx1= $('#msgtemp1 .msgtext1'),
        mrd1= $('#msgtemp1 .msgraed');
        mu1.html('انا');
        mt1.html('الان');
        mtx1.html(ostxmsg);
        const returnRQ = sendRequest(urlApi,obj,false);
        if(returnRQ[0]=='s'){
            mrd1.addClass('text-success');
            mrd1.html('نجح الارسال');
        }else{
            mrd1.addClass('text-danger');
            mrd1.html('فشل الارسال');
        }
        
        $('#swlTicket .msgdir').append(ms.html());
        scrollToBottom(md);
        return returnRQ;
    },
    ticketView              = async (oid,osid,type,view=0,newMsg=false)=>{
        let intrval;
        Swal.fire({
            html: `<div id="swlTicket" style="margin-top: !important; padding-bottom:0px !important">
                        <div class="text-center">
                          <div class="spinner-border m-5" role="status">
                            <span class="sr-only">Loading...</span>
                          </div>
                          <div class="text-center">من فضلك انتظر جارى عرض البيانات ...</div>
                        </div>
                    </div>`,
            showCancelButton: false,
            showConfirmButton:false,
            showCloseButton: true,
            didOpen:()=>{
                $('.swal2-close').blur();
                $('.swal2-html-container').addClass('pb-0')
                $('.swal2-html-container').addClass('m-3')
                
                let scrollRun=0;
                function newMsg(res){
                    for (var a in res){
                        for (var b in res[a]){
                            let x = res[a][b];
                          	
                            if(x.OS_STATUSE ==1 && x.OS_ID == osid){
                                $('#swlTicket .msgForm').remove();
                            }
                            if(a == 'msg'){
                                $('#msgtemp1 .msgraed').html('');
                                if(x.MSG_ME ==1 && x.OSTX_USER_TYPE == ptype){
                                    $('#msgtemp1 .msguser1').html('');
                                    $('#msgtemp1 .msgtime1').html(x.OSTX_DATE);
                                    $('#msgtemp1 .msgtext1').html(x.OSTX_MSG);
                                    if(x.OSTX_READ){
                                        $('#msgtemp1 .msgraed').html(`<i class="fa-sharp fa-solid fa-circle-check"></i>`);
                                    }
                                    $('#swlTicket .msgdir').append($('#msgtemp1').html());
                                }else{
                                    $('#msgtemp2 .msguser2').html(x.OSTX_UNAME);
                                    $('#msgtemp2 .msgtime2').html(x.OSTX_DATE);
                                    $('#msgtemp2 .msgtext2').html(x.OSTX_MSG);
                                    $('#swlTicket .msgdir').append($('#msgtemp2').html());
                                }
                            } else if(a == 'contact'){
                                var oid=x.ORDER_ID;
                                $('#contacts-list-temp .selectcontact').prop('id',x.OS_ID)
                                $('#contacts-list-temp .contacts-list-name').html(x.UNAME+`<small id="conld" class="contacts-list-date float-right">`+x.OS_DATE_OPEN+`</small>`);
                                $('#contacts-list-temp .contacts-list-msg').html(x.OST_TYPE);
                                $('#swlTicket .contacts-list').append($('#contacts-list-temp').html());
                            }//end if
                        }//endloop
                    }//end loop
                }
                const promiseMsg = new Promise(async (resolve)=>{
                        const params = `${urlApi}?CONTROL=OrderServeiceCHAT&ptype=${ptype}&osid=${osid}`;
                        const RQ = await fetch(params).then(response => {return response.json()});
                        resolve(RQ);
                }).then((resolve)=>{
                    $('#swlTicket').html($('#dirchat').html());
                    $('#swlTicket .ossmsg').attr('data-id', osid);
                    $('#swlTicket .inmsgtext').attr('data-id', osid);
                    $('#swlTicket .countmsg').html(Object.keys(resolve.contact).length || 0);
                    newMsg(resolve);
                    if(scrollRun ==0){
                        scrollToBottom($('#swlTicket .msgdir'),2000);
                    }
                    if(type==1 && scrollRun==0){
                        $(".swal2-timer-progress-bar").after(`<div class="swal2-actions mb-3" style="display: flex;">
                        										<button  type="button" data-oid="${oid}" data-osid="${osid}" class="btn btn-xs btn-danger btnosClose" style="width:160px;margin:1px 1 2px 1;"><i class="fas fa-user-times"></i> اغلاق الطلب </button>
                        										<button  type="button" data-oid="${oid}" data-osid="${osid}" class="btn btn-xs btn-warning btnAction" style="width:160px;margin:1px 1 2px 1;"><i class="fas fa-exchange"></i> اغلاق وتحويل  </button>
                        									<div>`)
                    }
                });
                intrval = setInterval(()=>{ 
                    const PromiseMsg2 = new Promise(async (resolve)=>{
                        const params = `${urlApi}?CONTROL=OrderServeiceCHAT&ptype=${ptype}&uid=${userlogin_data.ui}&osid=${osid}`;
                        const RQ = await fetch(params).then(response => {return response.json()});
                        resolve(RQ);
                    }).then((resolve)=>{
                        let t = online($('#swlTicket .msgForm'))
                        if($('#swlTicket .msgForm').is(':visible')){
                            newMsg(resolve);
                        }else{
                            clearInterval(intrval);
                        }
                    });
                },20000);
            },
			willClose:()=>{ 
				clearInterval(intrval);
			}
		}).then((value)=>{
            updateOrders();
        })
    };
let addressOpti    =[]; //sendRequestGET(`${urlApi}?CONTROL=getSelectOptionCountries`,false);
  
/*function format(d) {
	console.log(d);
    let textClass = 'danger';
    if (d[14]) {
        textClass = 'success';
    }

    const pro = JSON.parse(d['PRODUCTS_DATA'] || '[]');
    let rowsPro = '';

    for (let key in pro) {

        let dt = pro[key];
        let bgc = dt['RETURNED_QTY'] > 0 ? 'table-danger' : '';

        rowsPro += `
        <tr class="${bgc}">
            <td class="text-left align-middle">
                <div class="font-weight-bold">${dt['PRODUCT_NAME']}</div>
                <small class="text-muted">${dt['PRODUCT_ATTRIBUTES'] || ''}</small>
            </td>
            <td class="align-middle">${dt['ORDER_QTY']}</td>
            <td class="align-middle text-danger">${dt['RETURNED_QTY']}</td>
            <td class="align-middle text-success">${dt['DELIVERED_QTY']}</td>
            <td class="align-middle">${dt['PRICE']}</td>
            <td class="align-middle">${dt['DISCOUNT']}</td>
            <td class="align-middle font-weight-bold">${dt['REQUIRED_PAYMENT']}</td>
        </tr>`;
    }

    const price = parseFloat(d['PRICE']) || 0;
    const shipVal = parseFloat(d['SHIP_VAL']) || 0;
    const total = price + shipVal;
	console.log(d['ORDER_STATUS'])
	d['DELIVERED_DATE'] = d['ORDER_STATUS'] <= 2 ? null : (d['ORDER_STATUS'] > 2 && d['DELIVERED_DATE'] == "1970/01/01" ? d['DELIVERY_DATE'] : d['DELIVERED_DATE']);
	console.log(d['DELIVERED_DATE'])
	
    return `
    <div class="card shadow-sm border-0 mb-3">

        <!-- Header -->
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-file-invoice text-primary"></i>
                طلب رقم #${d['subOid']}
            </h5>
            <span class="badge badge-${textClass}">
                ${d['STA_NAME']}
            </span>
        </div>

        <div class="card-body">

            <!-- Order & Client Info -->
            <div class="row mb-3">

                <div class="col-md-6">
                    <div class="border rounded p-3 h-100 bg-light">
                        <h6 class="text-primary mb-3">بيانات الطلب</h6>
                        <p class="mb-1"><strong>المسوق:</strong> ${d['ORDERBY']}</p>
                        <p class="mb-1"><strong>تاريخ الإنشاء:</strong> ${d['DATE_CREATED']}</p>
                        <p class="mb-0">
                            <strong>موعد الشحن:</strong> 
                            ${shippingDateStr(d['DELIVERY_DATE'], d['SHIPPING_TIME_MAX'],d['DELIVERED_DATE'])}
                        </p>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="border rounded p-3 h-100 bg-light">
                        <h6 class="text-primary mb-3">بيانات العميل</h6>
                        <p class="mb-1"><strong>الاسم:</strong> ${d['CLIENT_NAME']}</p>
                        <p class="mb-1"><strong>الهاتف:</strong> ${d['TELEPHONE']}</p>
                        <p class="mb-0"><strong>العنوان:</strong> ${d['ADDRESS']}</p>
                    </div>
                </div>

            </div>

            <!-- Products Table -->
            <div class="table-responsive mb-3">
                <table class="table table-bordered table-hover text-center">
                    <thead class="thead-light">
                        <tr>
                            <th>المنتج</th>
                            <th>الكمية</th>
                            <th>مرتجع</th>
                            <th>تسليم</th>
                            <th>السعر</th>
                            <th>الخصم</th>
                            <th>المطلوب</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsPro}
                    </tbody>
                </table>
            </div>

            <!-- Summary -->
            <div class="row justify-content-end">
                <div class="col-md-5">
                    <div class="border rounded p-3 bg-light">
                        <h6 class="text-primary mb-3">ملخص الحساب</h6>

                        <div class="d-flex justify-content-between">
                            <span>قيمة الفاتورة:</span>
                            <strong>${price.toFixed(2)}</strong>
                        </div>

                        <div class="d-flex justify-content-between">
                            <span>قيمة الشحن:</span>
                            <strong>${shipVal.toFixed(2)}</strong>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between">
                            <span>الإجمالي:</span>
                            <strong class="text-success">
                                ${total.toFixed(2)}
                            </strong>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    `;
}*/
function format(d) {
console.log(d);
    let textClass = 'danger';
    if (d[14]) {
        textClass = 'success';
    }

    const pro = JSON.parse(d['PRODUCTS_DATA'] || '[]');
    let rowsPro = '';

    for (let key in pro) {

        let dt = pro[key];
        let bgc = dt['RETURNED_QTY'] > 0 ? 'table-danger' : '';

        rowsPro += `
        <tr class="${bgc}">
            <td class="text-left align-middle">
                <div class="font-weight-bold">${dt['PRODUCT_NAME']}</div>
                <small class="text-muted">${dt['PRODUCT_ATTRIBUTES'] || ''}</small>
            </td>
            <td class="align-middle">${dt['ORDER_QTY']}</td>
            <td class="align-middle text-danger">${dt['RETURNED_QTY']}</td>
            <td class="align-middle text-success">${dt['DELIVERED_QTY']}</td>
            <td class="align-middle">${dt['PRICE']}</td>
            <td class="align-middle">${dt['DISCOUNT']}</td>
            <td class="align-middle font-weight-bold">${dt['REQUIRED_PAYMENT']}</td>
        </tr>`;
    }

    const price = parseFloat(d['PRICE']) || 0;
    const shipVal = parseFloat(d['SHIP_VAL']) || 0;
    const total = price + shipVal;
	console.log(d['ORDER_STATUS'])
	d['DELIVERED_DATE'] = d['ORDER_STATUS'] <= 2 ? null : (d['ORDER_STATUS'] > 2 && d['DELIVERED_DATE'] == "1970/01/01" ? d['DELIVERY_DATE'] : d['DELIVERED_DATE']);
	console.log(d['DELIVERED_DATE'])
	
    return `
    <div class="card shadow-sm border-0 mb-3">

        <!-- Header -->
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-file-invoice text-primary"></i>
                طلب رقم #${d['subOid']}
            </h5>
            <span class="badge badge-${textClass}">
                ${d['STA_NAME']}
            </span>
        </div>

        <div class="card-body">

            <!-- Order & Client Info -->
            <div class="row mb-3">

                <div class="col-md-6">
                    <div class="border rounded p-3 h-100 bg-light">
                        <h6 class="text-primary mb-3">بيانات الطلب</h6>
                        <p class="mb-1"><strong>المسوق:</strong> ${d['ORDERBY']}</p>
                        <p class="mb-1"><strong>تاريخ الإنشاء:</strong> ${d['DATE_CREATED']}</p>
                        <p class="mb-0 text-wrap">
                            <strong>موعد الشحن:</strong> 
                            ${shippingDateStr(d['DELIVERY_DATE'], d['SHIPPING_TIME_MAX'],d['DELIVERED_DATE'])}
                        </p>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="border rounded p-3 h-100 bg-light">
                        <h6 class="text-primary mb-3">بيانات العميل</h6>
                        <p class="mb-1"><strong>الاسم:</strong> ${d['CLIENT_NAME']}</p>
                        <p class="mb-1"><strong>الهاتف:</strong> ${d['TELEPHONE']}</p>
                        <p class="mb-0 text-wrap"><strong>العنوان:</strong> ${d['ADDRESS']}</p>
                    </div>
                </div>

            </div>

            <!-- Products Table -->
            <div class="table-responsive mb-3">
                <table class="table table-bordered table-hover text-center">
                    <thead class="thead-light">
                        <tr>
                            <th>المنتج</th>
                            <th>الكمية</th>
                            <th>مرتجع</th>
                            <th>تسليم</th>
                            <th>السعر</th>
                            <th>الخصم</th>
                            <th>المطلوب</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsPro}
                    </tbody>
                </table>
            </div>

            <!-- Summary -->
            <div class="row justify-content-end">
                <div class="col-md-5">
                    <div class="border rounded p-3 bg-light">
                        <h6 class="text-primary mb-3">ملخص الحساب</h6>

                        <div class="d-flex justify-content-between">
                            <span>قيمة الفاتورة:</span>
                            <strong>${price.toFixed(2)}</strong>
                        </div>

                        <div class="d-flex justify-content-between">
                            <span>قيمة الشحن:</span>
                            <strong>${shipVal.toFixed(2)}</strong>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between">
                            <span>الإجمالي:</span>
                            <strong class="text-success">
                                ${total.toFixed(2)}
                            </strong>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    `;
}

/*=========================================================
	وظيفة عرض الفلتر للمستخدم
=========================================================*/
/*function filterOrderlist() {
    if ($.cookie('filt_'+wls) != null) {
        let fiobj = JSON.parse($.cookie('filt_'+wls));
		/*if( fiobj && fiobj['pType']!= ptype){
			$.removeCookie('filt'); 
            $.cookie('filt'+, JSON.stringify(filobj), {expires: 1, path: '/'}); 
		} 
        let badge = "";
        if (typeof fiobj !== "undefined") {
            for (let x in fiobj) {
                if ((x !== 'dateRange' && x !== 'dateType' && x !== 'products') && fiobj[x]['val']) {
                    badge += '<span id="'+x+'" data-id="'+x+'" onclick="clearfilter($(this))" class="badge badge-danger ficlose" style="margin:2px 0px 0 2px">'+fiobj[x]['val']+' ×</span>';
                } else if (x === 'products') {
                    for (let y in fiobj[x]['id']) {
                        badge += '<span id="'+fiobj[x]['id'][y]+'" data-name="'+fiobj[x]['val'][y]+'" data-id="'+x+'" onclick="clearfilter($(this))" class="badge badge-danger ficlose" style="margin:2px 0px 0 2px">'+fiobj[x]['val'][y]+' ×</span>';
                    }
                } else if (x === 'dateRange' && fiobj[x]['id'].length > 0) {
                    if (fiobj[x]['id'][0] !== fiobj[x]['id'][1]) {
                        badge += '<span data-id="'+x+'" onclick="clearfilter($(this))" class="badge badge-danger ficlose" style="margin:2px 0px 0 2px">'+fiobj['dateType']['val']+' | من: '+fiobj[x]['id'][0]+' الى: '+fiobj[x]['id'][1]+' ×</span>';
                    } else {
                        badge += '<span data-id="'+x+'" onclick="clearfilter($(this))" class="badge badge-danger ficlose" style="margin:2px 0px 0 2px">'+fiobj['dateType']['val']+' | '+fiobj[x]['id'][1]+' ×</span>';
                    }

                }
            }
        }
        if (badge !== "") {
            $('.fiinfo').html(badge);
            $('.fiinfo').parent('div').fadeIn(1000);
        } else {
            $('.fiinfo').html('');
            $('.fiinfo').parent('div').fadeOut(1000);

        }
    } else {
        $('.fiinfo').html('');
        $('.fiinfo').parent('div').hide();
    }
}*/
///////////////////////////////////////////////////////////////
function Toast_fire(arr=[]){
	let i,t,typeMSG = arr[0],textMSG = arr[1];
	if(typeMSG === "s"){
		i="success"
	} else if(typeMSG === "i"){
		i = "info"
	} else if(typeMSG === "w"){
		i="warning"
	} else if(typeMSG === "q"){
		i="question"
	} else if(typeMSG === "e"){
		i = "error"
	} 
	Swal.fire({
	    icon: i,
		title: "",
		html:textMSG,
		toast: true,
		position: 'top-start',
		iconColor: 'white',
		customClass: {
			popup: 'colored-toast '
		},
		didOpen: (toast) => {
			toast.addEventListener('mouseenter', Swal.stopTimer)
			toast.addEventListener('mouseleave', Swal.resumeTimer)
		},
		showConfirmButton: false,
		timer: 4000,
		timerProgressBar: false,
		showCloseButton: true
	})
}
let Columns;
let update_element;
//let SwalTitle = ''
let ModalParams = {};
//check user online 


async function showCaptcha() {
    let i = 0;
    return await Swal.fire({
        html: '<div id="swalCaptcha" class="col-12"><div class="form-row align-items-center"><div class="col-6">  <input type="text" class="form-control inputCaptcha" name="inputCaptcha" id="inputCaptcha" placeholder="ادخل كود التأكيد"></div><div class=" col-6 captchaImg"><img src="../../inc/Api.php?CONTROL=getCAPTCHA&124588644445" alt="CAPTCHA" class="image-captcha"> <a id="refresh-captcha" class="align-middle refresh-captcha col-1" title="r"><i class="fa-sharp fa-solid fa-arrows-rotate"></i></a> </div> </div></div>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: 'الغاء',
        confirmButtonText: 'حذف',
        didOpen: () => {
            const refreshButton = $("#swalCaptcha .refresh-captcha"),
                captchaImage = $('#swalCaptcha .image-captcha');
            refreshButton.click((event) => {
                i++
                let imgurl = '../../inc/Api.php?CONTROL=getCAPTCHA&' + i;
                
                $('#swalCaptcha img').replaceWith('<img src="../../inc/Api.php?CONTROL=getCAPTCHA&' + i +'" alt="CAPTCHA" class="image-captcha">');
            });
        },
        preConfirm: () => {
            if ($('#swalCaptcha .inputCaptcha').val()) {
                let rqcp = {'CONTROL': 'checkCAPTCHA', 'inputCaptcha': $('#swalCaptcha .inputCaptcha').val()};
                let rqc5 = sendRequest(urlControl, rqcp, false);
                if (rqc5 == undefined || rqc5 === 'no') {
                    $("#swalCaptcha .refresh-captcha").click();
                    Swal.showValidationMessage('كود التحقق غير صحيح');
                }
            } else {
                Swal.showValidationMessage('من فضلك ادخل كود التحقق');
            }
        }
    }).then((result) => {
    	
        return result.value.inputCaptcha;
    });
}
function checkIntrnet() {
    if ((window.navigator.onLine ? 'on': 'off') === 'off') {
        Toast.fire(
            'خطاء',
            'غير متصل بخدمة الانترنت من فضلك تأكد من تشغيل خدمة الانترنت على جهازك',
            'warning'
        )
        return false;
    }
    return true
}
function online(el){
    if (!el.hidden) {
        return true;
    }
}
function proTreeview(element) {
    let xtreeData = sendRequest(urlControl, {
        CONTROL: 'getlistProduct-Categories'
    })

    element.bstreeview({
        data: xtreeData,
        expandIcon: 'fa fa-angle-right fa-fw',
        collapseIcon: 'fa fa-angle-down fa-fw',
        indent: 2,
        parentsMarginLeft: '1.25rem',
        openNodeLinkOnNewTab: false
    });
    $('.bstreeview,.collapse').collapse();
}
function showproduct(cat_id) {
    $('.proitem').each(function () {
        $(this).show();
    })
    $('.proitem').each(function () {
        if ($(this).attr('data-catid') != cat_id && cat_id != -1) {
            $(this).hide();
        }
    })

}
function datepick(el, mineDate = '1900-01-01', startdate = new Date(),enddate = new Date(),singleDate=true,showRange=false,autoapply = true) {
    el.click(()=>{
        el.attr("disabled" , true);
        el.attr("disabled" , false);
    });
    el.click(function (){blur()});
    el.daterangepicker({
        "singleDatePicker": singleDate,
        "showDropdowns": true,
        "minYear": 1900,
        "maxYear": 2055,
        "timePicker": false,
        "autoApply": autoapply,
        "ranges": {
        'اليوم': [moment(), moment()],
        'فى الامس': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
        'اخر ٧ ايام': [moment().subtract(6, 'days'), moment()],
        'اخر ٣٠ يومـًا': [moment().subtract(29, 'days'), moment()],
        'هذا الشهر ': [moment().startOf('month'), moment().endOf('month')],
        'الشهر الماضى': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
    },
        "locale": {
            "format": "YYYY-MM-DD",
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
        "linkedCalendars": true,
        "showCustomRangeLabel": showRange,
        "alwaysShowCalendars": false,
        "startDate": startdate,
        "endDate": enddate,
        "minDate": mineDate,
        "drops": "up"
    },function(start, end, label) {
        start.format('YYYY-MM-DD') + '  ' + end.format('YYYY-MM-DD');
    });
};
function addDays(date, days) {
    var ms = days * 86400000;
    var result = new Date(date).getTime() + ms;
    //result.setDate(result.getDate() + days);
    return result;
}
function getClientData(id, tel_id, add_id, number) {
    let datarq;
    datarq = sendRequestGET(urlApi+'?CONTROL=searchClient&cli_search='+number+'&cli_id='+id+'&tel_id='+tel_id+'&add_id='+add_id);
    return datarq;
}
function generateString(length) {
    let result = ' ';
    const charactersLength = characters_random.length;
    for ( let i = 0; i < length; i++ ) {
        result += characters_random.charAt(Math.floor(Math.random() * charactersLength));
    }

    return result;
}

/*==========================================
    Strong password validation
==========================================*/
function validatePassword(password) {
    let emsg = "";
	if (typeof password != "string") {
        password = "" + password;
    }
    if (password.length < 8) {
        emsg +=  '<li>يجب أن يكون طول كلمة المرور 8 أحرف على الاقل</li>';
    }
    if (password.search(/[0-9]/) === -1) {
        emsg +=  '<li>لابد ان تحتوى كلمة المرور على رقم واحد على الاقل</li>';
    }
    if (password.search(/[a-z]/) === -1) {
        emsg +=  '<li>لابد ان تحتوى كلمة المرور على قيمة حرف صغيرة واحدة على الأقل [a-z]</li>';
    }
    if (password.search(/[A-Z]/) === -1) {
        emsg +=  '<li>يجب ان تحتوى كلمة المرور على قيمة حرف كبير واحدة على الأقل [A-Z]</li>';
    }
    if (password.search(/[`~!@#\$%\^&\*\(\)\-_\{\}\=\+'"\>\.\,\?<\/\|\\\:;\]\[]/) === -1) {
        emsg +=  'حرف خاص واحد على الأقل [~! @ # $٪ ^ & * () _ + {} ":؟> <...]';
    }
    return emsg;
}
/*==========================================
    phone validation
==========================================*/
function checkPhoneNum(el) {
    let x = false,
    numarr,
    type = 0,
    data_return,
    regex,
    num = el.val();

    el.removeClass('is-invalid');
    el.removeClass('is-valid');
    $('#telspan').remove();
    data_return = 1;
    numarr = num.split('', 1);
    numarr = num.split('', 3);
    numstr = numarr.toString();
    num = numstr.replaceAll(',', '');
    if (num.length !== "") {
        if (parseInt(numarr) > 0) {
            el.addClass('is-invalid');
            el.after('<span id="telspan" class="text-danger">من فضلك ادخل كود المحافظة او كود المحمول .والذى يبداء بصفر</span>');

        } else if (parseInt(numarr) === 0 && num.length < 3) {
            el.addClass('is-invalid');
            el.after('<span id="telspan" class="text-danger">ادخل كود المحافظة او كود المحمول</span>');

        } else if (parseInt(numarr) === 0 && num.length >= 3 && ((parseInt(num) >= 10 && parseInt(num) <= 15) && parseInt(num) != 13)) {
            type = 1;
            regex = /^[0-0]{1}?[1-9]{1}?[0-9]{9}$/g;
            el.addClass('is-invalid');
            $('#telspan').remove();
            $(".tele-type1").prop('checked', true);
            el.after('<span id="telspan" class="text-danger">برجاء ادخال الهاتف المحمول المكون من 11 رقم </span>');

        } else if (parseInt(numarr) === 0 && num.length >= 3 && parseInt(num) >= 20 && parseInt(num) < 40) {
            type = 2;
            regex = /^[0-0]{1}?[1-9]{1}?[0-9]{8}$/g;
            el.addClass('is-invalid');
            $('#telspan').remove();
            el.after('<span id="telspan" class="text-danger">برجاء ادخال رقم الهاتف الأرضى المكون من 10 رقم </span>');
            $(".tele-type2").prop('checked', true);

        } else if (parseInt(numarr) === 0 && num.length >= 3 && parseInt(num) == 13 || parseInt(num) >= 40) {
            type = 3;
            regex = /^[0-0]{1}?[1-9]{1}?[0-9]{9}$/g;
            $('#telspan').remove();
            el.after('<span id="telspan" class="text-danger">برجاء ادخال رقم الهاتف الأرضى المكون من 11 رقم </span>');
            $(".tele-type2").prop('checked', true);
        }
        if (parseInt(numarr) === 0 && regex && regex.test(el.val())) {
            $('#telspan').remove();
            el.removeClass('is-invalid');
            el.addClass('is-valid');
            x = true;
        }
    }
    return x;
}
function elPhone(x) {
    let element = "";
    element = '<div id="phoneDiv" class="form-group">';
    element += '<div class="row d-flex justify-content-between flex-nowrap">';
    element += '<label class="col-sm-2 d-flex justify-content-start" for="COM_TEL">الهاتف</label>';
    element += '<div class="form-check col-auto">';
    element += '<input id="tel_type1" class="form-check-input treq tele-type1" value="mobile" type="radio"  name="telType" checked="">';
    element += '<label class="form-check-label text teletype2"><i class="fa fa-lg fa-mobile-alt" ></i></label>';
    element += '</div>';
    element += '<div class="form-check col-auto">';
    element += '<input id="tel_type2" class="form-check-input treq tele-type2" value="telephone" type="radio" name="telType">';
    element += '<label class="form-check-label text"><i class="fa fa-lg fa-phone-square-alt" ></i></label>';
    element += '</div>';
    element += '<div class="d-flex justify-content-end" style="text-size:10px">';
    element += '<div class="form-check col-auto">';
    element += '<input id="whatsapp" class="form-check-input treq whatsapp" name="whatsapp" type="checkbox" name="">';
    element += '<label class="form-check-label text-success"><i class="fab fa-lg fa-whatsapp"></i></label>';
    element += '</div>';
    element += '<div class="form-check col-auto">';
    element += '<input id="telegram" class="form-check-input treq telegram" type="checkbox" name="telegram">';
    element += '<label class="form-check-label text-primary"><i class="fab fa-lg fa-telegram"></i></label>';
    element += '</div></div></div>';
    element += '<input type="number" class="form-control phone tel cli_tel cli req" id="cli_tel" name="clitel" placeholder="ادخل رقم الهاتف" required=""><span id="telspan" class="text-danger">ادخل كود المحافظة او كود المحمول</span>';
    element += '</div>';
    element += '<div class="form-check">';
    element += '<label class="form-check-label" style="margin-left:30px">تعين الرقم كإفتراضى</label>';
    element += '<input id="defultTel" class="form-check-input" type="checkbox">';
    element += '</div>';
    $("#phoneDiv").remove();
    x.html(element);
    $('.phone').keyup(function() {
        checkPhoneNum($(this));
    });
    $('.phone').change(function() {
        checkPhoneNum($(this));
    });
    $('.phone').off('focus', function() {
        if (!$(this).hasClass("is-valid")) {
            $(this).addClass("is-invalid");
        } else {
            $(this).removeClass("is-valid")
        }
    })
}
/*==========================================
    Email validation
==========================================*/
function validateEmail(e) {
    var x = false, regex = /^([a-z0-9!#?$%&'*+\-/=?^_`{|}~]+(?:\.[a-zA-Z0-9!#$%&'*+\-/=?^_`{|}~]+)*)@((?:[a-z0-9]+(?:[a-z-0-9-]*)\.)+[a-z]{2,})$/gi;
    $('#mailspan').remove();
    e.removeClass('is-invalid');
    e.removeClass('is-valid');
    if (regex.test(e.val()) && e.val().length > 0) {
        e.addClass('is-valid');
        x = true;
    } else if (e.length > 0) {

        e.after('<span id="mailspan" class="text-danger">تنسيق البريد الصحيح : مثال ( yorname@domin.com ) </span>');
    }
    return x;
}
$('input .email').keyup(function() {
    validateEmail($(this));
});
$('input .email').change(function() {
    validateEmail($(this));
});
/*==========================================
    TreeView function
==========================================*/
function initTtree(treeData) {
    $('#tree').bstreeview({
        data: treeData,
        expandIcon: 'fa fa-angle-right fa-fw',
        collapseIcon: 'fa fa-angle-down fa-fw',
        indent: 1.25,
        parentsMarginLeft: '1.25rem',
        openNodeLinkOnNewTab: true
    });
    $('.bstreeview,.collapse').collapse();
}



async function fetchrq(url) {
	let returnData;
    try {
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                //'Authorization': 'Basic xxxxxxxxxxxxx',
                //'X-CSRF-TOKEN': 'xxxxxxxxxxxxxxxxxxxx',
                'Content-Type': 'application/json',
            },
            cache: 'default', // مشابه لـ $.ajaxSetup
        });

        // التحقق إذا كان الرد ناجحًا
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }

        // تحويل الرد إلى JSON وإرجاعه
        const data = await response.json();
        returnData = data;
    } catch (error) {
        console.error('Error:', error.message);
        //return false; // مشابه لما كان يحدث في rq.fail
    }
    if(returnData){
    	return returnData
    }
}

/*async function sendRequestGET777(url = urlControl, x = false) {
    try {
        // انتظر استجابة الدالة fetchrq
        const result = await fetchrq(url);
console.log(result)
        // إرجاع النتيجة بعد انتهاء الطلب
        return result;
    } catch (error) {
        console.error('Error in sendRequestGET:', error.message);
        return false; // مشابه للـ rq.fail
    }
}
*/


/*============================================
**sendRequest function ;
** معلمات الوظيفة
**  1-[url] => عنوان الملف المرسل اليه الطلب
**  2-[parms]  => المعلمات  فى الطلب
**  3-[method_req] => نوع الطلب  سواء get او post وان ترك فارغا يكون اتوماتكيا post
**  4-[dataType]  => نوع البيانات التى تستخدم فى الرد على الطلب
**  5-[async]       => true تعنى انه يجب انتظار الرد على الطلب لاستكمال التعليمات البرمجيه
=============================================*/


function sendRequestGET(url = urlControl,sync=false) {
    
    const reqtime = Date.now();
    try { 
        rq = $.ajax({
            url: url,
            cache: true,
            headers: {
                //'Authorization':'Basic xxxxxxxxxxxxx',
                //'X-CSRF-TOKEN':'xxxxxxxxxxxxxxxxxxxx',
                'Content-Type':'application/json'
            },
            async: false,
            type: 'GET',
            //data: params,
            timeout: 5000, 
            dataType: 'json',
		            
		    // إضافة حدث لتسجيل بداية الطلب
		    beforeSend: function () {
		        console.log('Request is being sent...');
		    }
        });
        rq.done( function (response, statuse) {
           data_return = response;
        });
        rq.fail(function (xhr, textStatus) {
        	console.error(textStatus)
          // Toast_fire(['e','حدث خطاء غير متوقع اثناء معالجة الطلب من النظام']);
        });
   
        if (data_return != undefined) {
           return data_return;
        };
    } catch (error) {
    	console.error(error.message);
        //Toast_fire(['e', error.message]);
    } finally {
    	const x = reqtime - Date.now();
    	console.log(`انتهى الاتصال - الزمن المنقضى ${x}ms`);
    }

    
}; 
/*async function sendRequestGET(url = urlControl) {
    const reqtime = Date.now();
    let data_return;

    try {
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                //'Authorization': 'Basic xxxxxxxxxxxxx',
                //'X-CSRF-TOKEN': 'xxxxxxxxxxxxxxxxxxxx',
                'Content-Type': 'application/json'
            },
            cache: 'default',
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        data_return = await response.json();
        return data_return; // النتيجة بعد نجاح الطلب

    } catch (error) {
        console.error(error.message);
        // Toast_fire(['e', error.message]); // التعليق حسب الكود لديك
    } finally {
        const x = Date.now() - reqtime;
        console.log(`انتهى الاتصال - الزمن المنقضى ${x}ms`);
    }
}*/

/*async function sendAsyncRequestGET(url = urlControl, sync = false) {
    try { 
        const response = await $.ajax({
            url: url,
            headers: {
                'Content-Type': 'application/json'
            },
            async: false,
            type: 'GET',
            data: params,
            timeout: 5000, 
            dataType: 'json'
        });
        return await response;
    } catch (error) {
    	console.error(error.message)
        Toast_fire(['e', error.message]);
    }
}*/
/*============================================
**sendRequest function ;
** معلمات الوظيفة
**  1-[url] => عنوان الملف المرسل اليه الطلب
**  2-[parms]  => المعلمات  فى الطلب
**  3-[method_req] => نوع الطلب  سواء get او post وان ترك فارغا يكون اتوماتكيا post
**  4-[dataType]  => نوع البيانات التى تستخدم فى الرد على الطلب
**  5-[async]       => true تعنى انه يجب انتظار الرد على الطلب لاستكمال التعليمات البرمجيه
=============================================*/
/*async function sendRequest(url = urlControl, data = {}) {
    const reqtime = Date.now();
    let data_return;

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                //'Authorization': 'Basic xxxxxxxxxxxxx',
                //'X-CSRF-TOKEN': 'xxxxxxxxxxxxxxxxxxxx',
                'Content-Type': 'application/json',
            },
            cache: 'default',
            body: JSON.stringify(data), // تحويل البيانات إلى JSON
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        data_return = await response.json();
        return data_return; // النتيجة بعد نجاح الطلب

    } catch (error) {
        console.error(error.message+'***');
        // Toast_fire(['e', error.message]); // تعليق حسب الكود لديك
    } finally {
        const x = Date.now() - reqtime;
        console.log(`انتهى الاتصال - الزمن المنقضى ${x}ms`);
    }
}*/
function  sendRequest(url = urlControl, params = {}, sync=false) {
	try { 
   		let data_return;
        
        rq = $.ajax({
            url: url,
            cache: true,
            headers: {
                //'Authorization':'Basic xxxxxxxxxxxxx',
                //'X-CSRF-TOKEN':'xxxxxxxxxxxxxxxxxxxx',
                //'Content-Type':'application/json'
            },
            async: sync,
            type: 'POST',
            data: params,
            timeout: 5000, 
            dataType: 'json'
            
        });
        rq.done(function (response, statuse) {
            data_return = response;
        });
        rq.fail(function (xhr, textStatus) {
            Toast_fire(['e','حدث خطاء غير متوقع اثناء معالجة الطلب من النظام']);
        });
        if (data_return != undefined) {
            return data_return;
        };
	} catch (error) {
    	console.error(error.message);
        //Toast_fire(['e', error.message]);
        //return false;
    }
};

/*=============================================
   function dropdownData
   1 [datatbl]  => parameters control from Database table
   2 [selected] => defult option
===============================================*/
function dropdownData({datatbl = '', selected = '', where = ''}) {
    let x = sendRequestGET(urlApi+'?CONTROL='+datatbl+'&ID='+where);
    let data = "";
    if (x.length != 0) {
        if (selected < 0) {
            data += "<option  value='' selected>غير محدد</option>";
        }
        for (var row = 0; row < x.length; row++) {
            if (selected > 0 && x[row][1] == selected) {
                data += "<option class='val"+x[row][0]+"' value='"+x[row][0]+"' selected>"+x[row][2]+"</option>";
            } else {
                data += "<option class='val"+x[row][0]+"' value='"+x[row][0]+"'>"+x[row][2]+"</option>";
            }
        }
    } else {
        data += "<option value='' selected>غير محدد</option>";
    }
    return data;
}
/*=============================================
  function htmlel
===============================================*/
function htmlel( {el = '', val = '', lbl = '', id = '', name = '', place = '', onChenge = '', type = 'text', multiple = '', req = 'required', params = Go, wh = ''}) {

    let x = '';
    if (el === 'input') {
        x = '<div class="form-group text-left">';
        x += '<label class="text-left">'+ lbl +'</label>';
        x += '<input id="' + id +'" type="'+ type +'" class="form-control '+ req +'" value="'+ val +'" placeholder="' + place + '" >';
        x += '</div>';
    } else if (el === 'select') {
        let y = dropdownData({
            datatbl: 'getdrop'+params, selected: val, where: wh
        });
        if (y !== undefined || y !== '') {
            x = '<div class="form-group text-left">';
            x += '<label>' + lbl + '</label>';
            x += '<select id="'+ id +'" class="form-control select2 ' + req +'" '+ multiple +' onChenge= >'+ y +'</select>';
            x += '</div>';
        } else {
            console.log('ERROR function htmlel : no data in select option');
        };
    } else if (el === 'textarea') {
        x = '<div class="form-group text-left">';
        x += '<label class="text-left">'+ lbl +'</label>';
        x += '<textarea id="' + id +'" type="'+ type +'" row=4 class="form-control '+ req +'" value="'+ val +'" placeholder="' + place + '" ></textarea>';
        x += '</div>';
    } else {
        console.log('ERROR function htmlel : element is not in function');
    };
    return x;
}
/*===============================================
  addressOption function
===============================================*/
function addressOption() {
    //let Country = dropdownData({datatbl:'getdropGov', selected:1});
    //let City    = dropdownData('getCityList',0);
    let address = '';
    address = '<div class="form-group text-left">';
    address += '<label>الدولة</label>';
    address += '<select id="addressCountry" class="form-control required" onChenge="" ></select>';
    address += '</div>';
    address += '<div class="form-group text-left">';
    address += '<label>المحافظة</label>';
    address += '<select id="addressGov" class="form-control addressGov required" onChenge=""></select>';
    address += '</div>';
    address += '<div class="form-group text-left">';
    address += '<label>المدينة</label>';
    address += '<select id="addressCity" name="addressCity" class="form-control addressCity areq required" onChenge="" ></select>';
    address += '</div>';
    return address;
}
$(document).ready(function () {
    //$('.disabledCheckboxes').removeAttr("disabled");
    /*$('#addressCountry').change( function () {
        let Gov     = dropdownData('getGovList',1);
        $('#addressGov').attr();
    });*/
});
/*===============================================
  addressclient function
===============================================*/

function addressclient(el, css = '', co = '', go = '', ci = '') {

    const html = `
        <div id="elCountryAdd" class="zindexselect2 ${css}">
            
            <div class="form-group text-left">
                <label>الدولة</label>
                <select id="addressCountry"
                        class="form-control select2 addressCountry cli req">
                </select>
            </div>

            <div class="form-group text-left">
                <label>المحافظة</label>
                <select id="addressGov"
                        class="form-control select2 addressGov cli req">
                </select>
            </div>

            <div class="form-group text-left">
                <label>المدينة</label>
                <select id="addressCity" name="addressCity"
                        class="form-control select2 addressCity cli req">
                </select>
            </div>

            <div class="form-group">

                <div class="row d-flex justify-content-between flex-nowrap">

                    <label class="col-sm-2 d-flex justify-content-start">
                        العنوان
                    </label>

                    <div class="d-flex justify-content-center">

                        <div class="form-check col-auto">
                            <input class="form-check-input areq"
                                   type="radio"
                                   value="home"
                                   name="addressType"
                                   checked>

                            <label class="form-check-label text-primary">
                                منزل
                            </label>
                        </div>

                        <div class="form-check col-auto">
                            <input class="form-check-input areq"
                                   type="radio"
                                   value="work"
                                   name="addressType">

                            <label class="form-check-label text-primary">
                                مكتب
                            </label>
                        </div>

                    </div>
                </div>

                <textarea class="form-control cli req" name="cliaddress"
                          id="cli_address"
                          placeholder="من فضلك ادخل تفاصيل العنوان"></textarea>

            </div>

            <div class="form-check">
                <label class="form-check-label mr-4">
                    تعيين العنوان كإفتراضي
                </label>

                <input id="defultAdd"
                       class="form-check-input"
                       type="checkbox">
            </div>

        </div>
    `;

    $('#elCountryAdd').remove();
    el.html(html);

    /*
    |--------------------------------------------------------------------------
    | تحميل البيانات مرة واحدة
    |--------------------------------------------------------------------------
    */

    if (addressOpti.length === 0) {

        addressOpti = sendRequestGET(
            `${urlApi}?CONTROL=getSelectOptionCountries`,
            false
        );

    }

    const data = JSON.parse(JSON.stringify(addressOpti));

    /*
    |--------------------------------------------------------------------------
    | تجهيز البيانات
    |--------------------------------------------------------------------------
    */

    const countries = data[0].map(item => $(item[0]));
    const govs      = data[1].map(item => $(item[0]));
    const cities    = data[2].map(item => $(item[0]));

    /*
    |--------------------------------------------------------------------------
    | العناصر
    |--------------------------------------------------------------------------
    */

    const $country = $('#addressCountry');
    const $gov     = $('#addressGov');
    const $city    = $('#addressCity');

    /*
    |--------------------------------------------------------------------------
    | بناء select
    |--------------------------------------------------------------------------
    */

    function buildSelect($select, items, parentId = null, selected = '') {

        let html = '<option value=""></option>';

        items.forEach(option => {

            if (
                parentId === null ||
                option.attr('data-id') == parentId
            ) {

                html += option.prop('outerHTML');

            }

        });

        $select.html(html);

        if (selected) {

            $select.val(selected);

        }

        $select.trigger('change.select2');

    }

    /*
    |--------------------------------------------------------------------------
    | تحميل أولي
    |--------------------------------------------------------------------------
    */

    buildSelect($country, countries, null, co);
    buildSelect($gov, govs, co, go);
    buildSelect($city, cities, go, ci);

    /*
    |--------------------------------------------------------------------------
    | Select2
    |--------------------------------------------------------------------------
    */

    $('.select2').select2({
        dir: 'rtl',
        width: '100%',
        dropdownParent: $('#elCountryAdd')
    });

    /*
    |--------------------------------------------------------------------------
    | تغيير الدولة
    |--------------------------------------------------------------------------
    */

    $country.on('change', function () {

        const countryId = $(this).val();

        buildSelect($gov, govs, countryId);

        buildSelect($city, cities);

    });

    /*
    |--------------------------------------------------------------------------
    | تغيير المحافظة
    |--------------------------------------------------------------------------
    */

    $gov.on('change', function () {

        const govId = $(this).val();

        buildSelect($city, cities, govId);

    });

    /*
    |--------------------------------------------------------------------------
    | تغيير المدينة
    |--------------------------------------------------------------------------
    */

    $city.on('change', function () {

        const $selectedCity = $(this).find(':selected');

        const govId = $selectedCity.data('id');

        if (!govId) return;

        $gov.val(govId).trigger('change.select2');

        const countryId = $gov.find(':selected').data('id');

        if (countryId) {

            $country.val(countryId).trigger('change.select2');

        }

    });

}
/*function addressclient(el, css, co, go, ci) {

    let element,
    addCountry,
    addGov, addCity,
    Data, ids = ['Country', 'Gov', 'City'],
    idsv = [co, go, ci];
    element = '<div id="elCountryAdd" class="zindexselect2 '+css+'">';
    element += '<div class="form-group text-left">';
    element += '<label>الدولة</label>';
    element += '<select id="addressCountry" class="form-control select2 custom-select addressCountry cli req" name="addressCountry" placeholder="اختار الدولة" required></select>';
    element += '</div>';
    element += '<div class="form-group text-left ">';
    element += '<label for="addressGov">المحافظة</label>';
    element += '<select id="addressGov" class="form-control select2 custom-select addressGov cli req" name="addressGov" placeholder="اختار المحافظة" required></select>';
    element += '</div>';
    element += '<div class="form-group select2 text-left ">';
    element += '<label>المدينة</label>';
    element += '<select id="addressCity" class="form-control select2 custom-select cli addressCity req" name="addressCity" placeholder="اختار المدينه" required></select>';
    element += '</div>';
    element += '<div class="form-group ">';
    element += '<div class="row d-flex justify-content-between flex-nowrap">';
    element += '<label class="col-sm-2 d-flex justify-content-start" for="COM_ADDRESS">العنوان</label>';
    element += '<div class="d-flex justify-content-center" style="text-size:10px">';
    element += '<div class="form-check col-auto">';
    element += '<input id="address_type1" class="form-check-input areq" type="radio" value="home" name="addressType" checked>';
    element += '<label class="form-check-label text-primary">منزل</label>';
    element += '</div>';
    element += '<div class="form-check col-auto">';
    element += '<input id="address_type2" class="form-check-input areq" type="radio" value="work" name="addressType">';
    element += '<label class="form-check-label text-primary">مكتب</label>';
    element += '</div></div></div>';
    element += '<textarea type="text" row="4" class="form-control cli req" id="cli_address" name="cliaddress" placeholder="منفضلك ادخل تفاصيل العنوان" required></textarea></div>';
    element += '</div>';
    element += '<div class="form-check">';
    element += '<label class="form-check-label" style="margin-left:30px">تعين العنوان كإفتراضى</label>';
    element += '<input id="defultAdd" class="form-check-input" type="checkbox">';
    element += '</div>';
    $("#elCountryAdd").remove();
    el.html(element);
    $('#addressCity').html("");
    $('#addressCountry').html("");
    $('#addressGov').html("");
    if(addressOpti.length == 0){
    	addressOpti    = sendRequestGET(`${urlApi}?CONTROL=getSelectOptionCountries`,false);
    }
    data = JSON.parse(JSON.stringify(addressOpti));
    for (let i = 0; i < data.length; i++) {
        for (let x = 0; x < data[i].length; x++) {
            $('#address'+ids[i]).append(data[i][x][0]);
        }
		$('#addressCountry').val(co).trigger('change.select2'); 
        if (idsv[i] != undefined) {
            $('.address'+ids[i]).val(idsv[i]);
        }
        if (i !== 0) {
            $('#address'+ids[i]).children('option').each(function() {
                if ($('.address'+ids[i-1]).val() == $(this).attr('data-id')) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            })
        }
    }
    
    /*$('#addressCountry').change(function () {
    	$('#addressGov').html("");
    	$('#addressCity').html("");
        $('#addressGov').val("").change();
        $('#addressCity').val("").change();
        for (let x = 0; x < data[1].length; x++) {
            $('#addressGov').append(data[1][x][0]);
        }
        $('#addressGov').children('option').each(function() {
            if ($('.addressCountry').val() !== $(this).attr('data-id')) {
                $(this).remove();
                
            }
        })
        $('#addressGov').val(null).trigger('change.select2'); 
    });*/
    
    /*$('#addressGov').change(function () {
        //$('#addressCity').html("");
        //$('#addressCity').val("").change();
        for (let x = 0; x < data[2].length; x++) {
            $('#addressCity').append(data[2][x][0]);
        }
        $('#addressCity').children('option').each(function() {
            if ($('#addressGov').val() != $(this).attr('data-id')) {
                $(this).remove()
            }
        }) 
        $('#addressCity').val(null).trigger('change.select2'); 
    });*/
    // عند اختيار مدينة، تحديد المحافظة والدولة تلقائيًا
    /*$('#addressCity').change(function () {
        let selectedCityId = $(this).val();
        let selectedGovId = $("#addressCity option:selected").attr("data-id");
        console.log(selectedGovId);
		$('#addressGov').val(selectedGovId).change();
        let selectedCountryId = $("#addressGov option:selected").data("id");
        $('#addressCountry').val(selectedCountryId).change();
        
        // البحث عن المحافظة المرتبطة بالمدينة
        /*for (let i = 0; i < data[2].length; i++) {
            if (data[2][i][1] == selectedCityId) { // 1 هنا تعني أن البيانات تحتوي على ID المحافظة
                selectedGovId = data[2][i][2]; // ID المحافظة
                break;
            }
        }*/
		
        // تحديث المحافظة
        /*if (selectedGovId) {
            $('#addressGov').val(selectedGovId).change();
        }*/

        // البحث عن الدولة المرتبطة بالمحافظة
        /*for (let i = 0; i < data[1].length; i++) {
            if (data[1][i][1] == selectedGovId) { // البحث عن الدولة بناءً على ID المحافظة
                selectedCountryId = data[1][i][2]; // ID الدولة
                break;
            }
        }*/

        // تحديث الدولة
        //if (selectedCountryId) {
            //$('#addressCountry').val(selectedCountryId).change();
        //}
    /*});

	//$('#addressCountry').val(co).change();
    //$('#addressGov').val(go).change();
    //$('#addressCity').val(ci).change();
    
    $('.select2').css('z-index', '1');
    $.fn.modal.Constructor.prototype.enforceFocus = function() {
        $('.addressCity').attr('class', 'border border-primary');
    };
};*/
/*function addressclient(el, css, co, go, ci) {
    let element, data, ids = ['Country', 'Gov', 'City'], idsv = [co, go, ci];

    element = `<div id="elCountryAdd" class="zindexselect2 ${css}">
        <div class="form-group text-left">
            <label>الدولة</label>
            <select id="addressCountry" class="form-control select2 custom-select addressCountry cli req" name="addressCountry" required></select>
        </div>
        <div class="form-group text-left">
            <label>المحافظة</label>
            <select id="addressGov" class="form-control select2 custom-select addressGov cli req" name="addressGov" required></select>
        </div>
        <div class="form-group text-left">
            <label>المدينة</label>
            <select id="addressCity" class="form-control select2 custom-select cli addressCity req" name="addressCity" required></select>
        </div>
    </div>`;

    $("#elCountryAdd").remove();
    el.html(element);

    // تحميل البيانات إذا لم تكن محملة مسبقًا
    if (addressOpti.length == 0) {
        addressOpti = sendRequestGET(`${urlApi}?CONTROL=getSelectOptionCountries`, false);
    }
    data = JSON.parse(JSON.stringify(addressOpti));

    // تعبئة القوائم بدون تصفية
    for (let i = 0; i < data.length; i++) {
        for (let x = 0; x < data[i].length; x++) {
            $('#address' + ids[i]).append(data[i][x][0]);
        }
    }

    // تحديد القيم الافتراضية
    $('#addressCountry').val(co).trigger('change.select2');
    $('#addressGov').val(go).trigger('change.select2');
    $('#addressCity').val(ci).trigger('change.select2');

    // عند اختيار مدينة، تحديد المحافظة والدولة تلقائيًا
    $('#addressCity').change(function () {
        let selectedCityId = $(this).val();
        let selectedGovId, selectedCountryId;

        // البحث عن المحافظة المرتبطة بالمدينة
        for (let i = 0; i < data[2].length; i++) {
            if (data[2][i][1] == selectedCityId) { // 1 هنا تعني أن البيانات تحتوي على ID المحافظة
                selectedGovId = data[2][i][2]; // ID المحافظة
                break;
            }
        }

        // تحديث المحافظة
        if (selectedGovId) {
            $('#addressGov').val(selectedGovId).trigger('change.select2');
        }

        // البحث عن الدولة المرتبطة بالمحافظة
        for (let i = 0; i < data[1].length; i++) {
            if (data[1][i][1] == selectedGovId) { // البحث عن الدولة بناءً على ID المحافظة
                selectedCountryId = data[1][i][2]; // ID الدولة
                break;
            }
        }

        // تحديث الدولة
        if (selectedCountryId) {
            $('#addressCountry').val(selectedCountryId).trigger('change.select2');
        }
    });

    $('.select2').css('z-index', '1');
}*/
$(document).ready(function () {
/*===============================================
  login Button
===============================================*/
    $(document).on('submit', '#loginform', function () {
        $('#loginbox').hide();
        $('.spinner-border').show();
        $('#login_massage').hide();
        $('.input-group-text , .form-control').removeClass('border-danger');
        let params = {
            CONTROL: 'login',
            email: $("[name='email']").val(),
            pass: $("[name='pass']").val(),
            type: $('#login_btn').attr('data-type'),
            remember : document.getElementById('login_remember_input').checked
        };
        let request = sendRequest(window.location.href, params);
        if (request != undefined && request > 0) {
           location.reload();
        } else {
            $('#loginbox').show();
            $('#login_massage').show();
            $('.input-group-text , .form-control').addClass('border-danger');
            $('.spinner-border').hide();
        }
        return false;
    });
/*===============================================
  logout Button
===============================================*/
    $('#logoutbtn').click(function() {
        var response = sendRequest(urlApi, {
            logout: 'true'
        })
        if (response == 0) {
            location.reload();
        }
    });
});
/*==========================================
    dataTable function
==========================================*/
function tblGET(datatbl) {
    table = $('#tbl').DataTable({
        scrollY : '70vh',
        scrollX : true,
        
        ajax: {
            url:datatbl,
            async:true,
            dataType: 'json',
            type: 'GET',
            
        },
        //deferLoading: 57,
	    processing: true,
        serverSide: true,
        columns: Columns,
        columnDefs: [
            { className: "text-nowarp text-center", targets: "_all" }
        ],
        buttons: [
		        {
		            text: 'تحديث البيانات',
		            action: function ( e, dt, node, config ) {
		                dt.ajax.reload();
		            }
		        }
		    ],
		order: [[0, 'desc']] // 👈 يعكس الترتيب
    });
};
function tbl(urlControl, datatbl) {
    table = $('#tbl').DataTable({
        scrollY : '70vh',
        scrollX : true,
        processing: true,
        serverSide: true,
        ajax: {
            url: urlControl,
            async: true,
            type: 'POST',
            data: datatbl,
            dataType: 'json'
        },
        columns: Columns,
        columnDefs: [
            { className: "text-nowarp text-center", targets: "_all" }
        ],
		buttons: [
				        {
				            text: 'تحديث البيانات',
				            action: function ( e, dt, node, config ) {
				                dt.ajax.reload();
				            }
				        }
				    ],
    });
};
/*==========================================
    addressdata function
==========================================*/
function addressdata( {
    country = -1, gov = -1, city = -1
}) {
    let addCountry, addGov, addCity;
    let data = sendRequestGET(urlApi+'?CONTROL=getEnterprise');
    $('.enpr').each(function() {
        $('#' + $(this).attr('id')).val(data[0][$(this).attr('id')]);
    });
    $('.select2').select2({
        //theme: 'classic',
        selectionCssClass: 'form-control',
        width: '100%',
        dir: 'rtl'
    });
    $('#addressGov').prop('disabled', 'disabled');
    $('#addressCity').prop('disabled', 'disabled');

    addCountry = dropdownData({
        datatbl: 'getCountryList', selected: country
    });
    $('#addressCountry').html('')
    $('#addressCountry').html(addCountry)

    if ($('#addressCountry').val() == true) {
        addGov = dropdownData({
            'datatbl': 'getGovList', 'selected': gov, 'where': $('#addressCountry').val()});
        $('#addressGov').html(addGov);
        $('#addressGov').prop('disabled', '');
    };

    if ($('#addressGov').val() == true) {
        addCity = dropdownData({
            'datatbl': 'getCityList', 'selected': city, 'where': $('#addressGov').val()});
        $('#addressCity').html(addCity);
        $('#addressCity').prop('disabled', '');
    };


    $('#addressCountry').change(function () {

        if ($('#addressCountry').val() > 0) {
            $('#addressGov').prop('disabled', '');
            addGov = dropdownData({
                'datatbl': 'getGovList', 'selected': 1, 'where': $('#addressCountry').val()});
            $('#addressGov').html(' ');
            $('#addressGov').html(addGov);
            addGov = '';
            if ($('#addressGov').val() > 0) {
                $('#addressGov').prop('disabled', '');
                $('#addressCity').prop('disabled', 'disabled');
            } else {
                $('#addressGov').prop('disabled', 'disabled');
            }
        };
        if ($('#addressGov').val() > 0) {
            addCity = dropdownData({
                'datatbl': 'getCityList', 'selected': -1, 'where': $(this).val()});
            $('#addressCity').html(' ');
            $('#addressCity').html(addCity);
            addCity = '';
            $('#addressCity').prop('disabled', '');
        } else {
            $('#addressCity').prop('disabled', 'disabled');
        };
    });

    $('#addressGov').change(function () {
        if ($(this).val() !== undefined || $(this).val() !== '') {
            addCity = dropdownData({
                'datatbl': 'getCityList', 'selected': -1, 'where': $(this).val()});
            $('#addressCity').html(' ');
            $('#addressCity').html(addCity);
            City = ''
            $('#addressCity').prop('disabled', '');
            if ($(this).val() !== '') {
                $('#addressCity').prop('disabled', '');
            } else {
                $('#addressCity').prop('disabled', 'disabled');
            }
        };
    });
    /*$('.select2').css('z-index',
        '1061');*/
    $.fn.modal.Constructor.prototype.enforceFocus = function() {
        $('#addressCity').attr('class',
            'border border-primary');
    };
};
//=========================================
$(document).ready(function () {

    //=============== checkbox ===========================================================
    $(document).on('click', '#select-all', function(event) {
        if (this.checked) {
            // Iterate each checkbox
            $('tbody td :checkbox').each(function() {
                this.checked = true;
                $('#btnDelete').prop("disabled", false);
            });
        } else {
            $('tr :checkbox').each(function() {
                this.checked = false;
                $('#btnDelete').prop("disabled", true);
            });
        }
    }); //end click
    $(document).on('click',
        'tbody :checkbox',
        function(event) {
            if ($('tbody td input:checkbox:checked').length > 0) {
                $('#btnDelete').prop("disabled", false);
            } else {
                $('#btnDelete').prop("disabled", true);
                $('#select-all').checked = false;
            }
        }); // end click
	$('.nav .active').removeClass('active');
    $('.nav .active').removeClass('menu-open');
	$('.nav-sidebar a[href="?'+ window.location.href.slice(window.location.href.indexOf('?') + 1)+'"]').addClass('active');
	$('.nav-sidebar .active').parent('li').parent('ul').parent('li').addClass('menu-open');
	$('.nav-sidebar .active').parent('li').parent('ul').parent('li').children('a').addClass('active');
	
}); //end document.ready