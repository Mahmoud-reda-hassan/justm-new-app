if ($("#SelectUserDelivery").length) {
	shipmentUser = sendRequestGET(`${urlApi}?CONTROL=UserListForType&utype=delivery`);
	let options = '<option value="">عرض الكل</option>';
	for (let key in shipmentUser) {
		options += `<option value="${shipmentUser[key]['ID']}">${shipmentUser[key]['NAME']}</option>`;
	}
	$("#SelectUserDelivery").html(options);
}
$('#SelectUserDelivery').select2({
	placeholder:'اختار المندوب',
	allowClear: true,
	language: 'ar',
    selectionCssClass: 'form-control',
	dir: 'rtl',
});
	
//$('#SelectUserDelivery').next('.select2-container').addClass('position-static');
//$('#SelectUserDelivery').css('z-index', 'auto');
const urlVORP	= `../../inc/controller/accounts.control.php?CONTROL=ReceivePayments&ptype=${ptype}&st=${ost}&url=${wls}`,
urlfinance		= `../../inc/controller/finance.control.php`,
orderPayArr		= {};
vieworder		= (row) => {
	if (!$('.DOL').is(':visible')) {
		$('.btn-app-pade .btnhead').remove();
		$('.DOVDATA').remove();
		$('.card.DOV').hide();
		$('.DCU').show();
		$('.DOL').show();
	} else {
		$('.btn-app-pade').append('<div id="btnhead" class="btnhead btn-group"><button id="btnback" type="button" class="btn btn-app-row w-100 h-100 f-5 btn-primary btn-xs btnback" onclick = "vieworder(0)"><i class="fas fa-arrow-left"></i></button></div>');
		$('.card.DOV').append(format(row.data()));
		$('.card.DOV').show();
		$('.DCU').hide();
		$('.DOL').hide(); 
	}

},
/*shippingDateStr = (shippDate, shipWaiting)=> {
	let shipDateStr = "";
	let date_1 = new Date(shippDate);
	let date_2 = new Date();
	let diff = date_1.getTime() - date_2.getTime();
	let TotalDays = Math.ceil(diff / (1000 * 3600 * 24));
	if (shipWaiting > 0) {
		if (TotalDays > 0) {
			shipDateStr = ' ستصل الشحنه خلال '+TotalDays +" ايام بحد اقصى " + date_1.toLocaleDateString('ar-EG', option1);
		} else {
			shipDateStr = 'تأخر منذ '+TotalDays*-1 +" يوم بتاريخ   " + date_1.toLocaleDateString('ar-EG', option2);
		}
	} else {

		shipDateStr = "ستصل الشحنة يوم "+ date_1.toLocaleDateString('ar-EG', option1);
	}
	return shipDateStr;
},*/
supTotal		= ()  => {
		let total = 0;
	
		for (const key in orderPayArr) {
		    if (orderPayArr.hasOwnProperty(key)) {
		        const row = orderPayArr[key];
		        const value = parseFloat(row[28]) || 0;
		        total += value;
		    }
		}
		
		return total;
},
custodyTotal	= ()  => {
	const dt = table.ajax.json();
	$('.cut').html(dt['cuTotal']['total']);
	$('.custodyTotal .cu2').html(dt['cuTotal']['underDeli']);
	$('.custodyTotal .cu3_4').html(dt['cuTotal']['invTotal']);
	$('.custodyTotal .cutqty').html(dt['cuTotal']['finTotal']);
	$('.custodyTotal .cash').html(dt['cuTotal']['cashBalance']);
	$('.custodyTotal .selse').html(dt['cuTotal']['CASH_IN']);
	$('.custodyTotal .ship').html(dt['cuTotal']['CASH_OUT']);
},

/*format = (d) => {

	let textClass = 'danger';
	if (d[14]) {
		textClass = 'success';
	}
	const pro = d[29];
	let rowsPro = '';
	for (let key in pro) {
		let dt = null;
		if (typeof pro[key] === "string") {
		  try {
		    dt = JSON.parse(pro[key]);
		  } catch (e) {
		    console.error("المحتوى مش بصيغة JSON:", e);
		    return 
		  }
		} else {
		  dt = pro[key];
		}
		let bgc = '';
		if (dt['RETURNED_QTY'] > 0) {
			bgc = 'bg-danger';
		}
		rowsPro += `<tr class="${bgc}">
			<td class="text-nowrap text-left">
			<div class=" tdrest tdproName">${dt['PRODUCT_NAME']}</div>
			<a class="tdrest text-warning tdproOption">${dt['PRODUCT_ATTRIBUTES']}</a>
			</td>
			<td class="tdrest col-sm-1 text-center">${dt['ORDER_QTY']}</td>
			<td class="tdrest col-sm-1 text-center">${dt['RETURNED_QTY']}</td>
			<td class="tdrest col-sm-1 text-center">${dt['DELIVERED_QTY']}</td>
			<td class="tdrest col-sm-1 text-center">${dt['PRICE']}</td>
			<td class="tdrest col-sm-1 text-center">${dt['DISCOUNT']}</td>
			<td class="tdrest col-sm-1 text-center">${dt['REQUIRED_PAYMENT']}</td>
			</tr>`
	}
	return (
		`
		<div class="card-body DOVDATA" style="display: block;">
		<div class="invoice">
		<!-- title row -->
		<div class="row m-1">
		<div class="col-sm-12 text-left mt-2">
		<h4>
		<i class="fas fa-globe"></i><span id="orderid">${d[1]}</span>
		<small id="orderdate" class="float-right"></small>
		</h4>
		</div>
		<!-- /.col -->
		</div>
		<!-- info row -->
		<div class="row invoice-info text-left m-1">
		<div class="col-sm-6 invoice-col">
		<address>
		<a id="obyuname"><strong>مسوق : </strong>${d[2]}</a>
		<br>
		<a id="odate"><strong>الانشاء : </strong>${d[7]}</a>
		<br>
		<a id="oshipdate"><strong>الشحن :
		</strong>${shippingDateStr(d['SHIPPING_TIME_MAX'], d['DELIVERY_TYPE'])}   ${d[4]}</a>
		<br>
		<a id="ostatuse" class="text-${textClass}"><strong>حالة امر البيع : </strong>${d[13]}</a>
		</address>
		</div>
		<div class="col-sm-6 invoice-col">
		<address>
		<a id="ouname"><strong> العميل : </strong> ${d[30]}</a>
		<br>
		<a id="ouphone"><strong>الهاتف : </strong>${d[32]}</a>
		<br>
		<a id="ouemail"><strong>البريد الالكتروني : </strong>${d[33]}</a>
		<br>
		<a id="ouaddress" class="text-wrap"><strong>العنوان : </strong>${d[31]}</a>
		<br>
		<a id="oucomm"></a>
		</address>
		</div>
		<!-- /.col -->
		</div>

		<!--Sales order product display template-->
		<div class="row ml-0 mr-0">
		<table class="table table-hover table-responsive table-bordered table-striped w-100 col-sm-12 p-0 m-0 ">
		<thead class="bg-secondary text-center">
		<tr>
		<th class="col">اسم المنتج</th>
		<th class="col">الكميه</th>
		<th class="col">مرتجع</th>
		<th class="col">تسليم</th>
		<th class="col">السعر</th>
		<th class="col">الخصم</th>
		<th class="col">المطلوب</th>
		</tr>
		</thead>
		<tbody class="bg-light productOrder-View"><tr class="item itemorder bg-info">
		${rowsPro}
		</tbody>
		</table>
		</div>
		<div class="row bg-light border ml-0 mr-0 mb-1">
		<div class="col-sm-6">
		</div>
		<!--subtotal-->
		<div class="col-sm-6">
		<p class="text-left pt-2"><b>ملخص الحساب</b></p>
		<div class="">
		<table class="table">
		<tbody>
		<tr>
		<th class="w-50">قيمة الفاتورة:</th>
		<td colspan="2" class="te suptotal">${d[26]}</td>
		</tr>
		<tr>
		<th>قيمة الشحن:</th>
		<td colspan="2" class="shipval">${d[27]}</td>
		</tr>
		<tr>
		<th>اجمالى:</th>
		<td colspan="2" class="total">${d[28]}</td>
		</tr>
		</tbody>
		</table>
		</div>
		</div>
		<hr>

		</div>
		<!--/Sales order product display template-->
		</div>
		</div>`
	);
};*/
/*function format(d) {

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

/*=========================================================
	 عرض بيانات اوامر البيع بالجدول
	=========================================================*/$(document).ready(function() {
	// زر السداد
	$('.btn-app-pade').append('<button id="btnPay" class="btn btn-app-tools btnh text-dark btnPay" data-id="new" ><i class="fas fa-shopping-cart"></i><span class="btnname">سداد</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>');
	table = $('#tbl').DataTable({
		//scrollY: '70vh',
		//scrollX: false,
		ajax: {
			url: urlVORP,
			type: 'GET',
			dataSrc: function(json) {
                // عند تحميل البيانات من السيرفر، نقوم بإرجاع البيانات لتكون قابلة للتصفية محليًا
                return json.data; 
            }
		},
		orderCellsTop: true, // يضمن أن الترتيب يعمل فقط على الصف العلوي
    	ordering: true, // يسمح بالترتيب ولكن فقط على الهيدر الأول
		processing: true,
		serverSide: true,
		columns: [
            {
                orderable: false,
                render: function(data, type, row, meta) {
                    return `<button id="btnOview" type="button" data-id="${row[0]}" class="btn btn-app-row btn-xs bg-primary dt-control h-80 f-5 m-1"><i class="fas fa-eye"></i></button>`;
                }
            },
            { data: 1 ,orderable: true},
            { data: 13},
            { data: 30},
            { data: 3 },
            { data: 34},
            { data: 28, orderable: false },
            {
                orderable: false,
                render: function(data, type, row, meta) {
                    return `<button id="btnPayment" type="button" data-id="${row[0]}" class="btn btn-app-row btn-xs bg-secondary btnPayment h-80 f-5 m-1"><i class="fas fa-hand-holding-usd"></i></button>
                    	<button id="btnCancelPayment" type="button" data-id="${row[0]}" class="btn btn-app-row btn-xs bg-danger btnCancelPayment h-80 f-5 m-1" style="display:none;"><i class="fas fa-xmark"></i></button>`;
                }
            }
        ],
        columnDefs: [{
            targets: '_all',
            className: 'mdc-data-table__cell text-nowrap text-center'
        }],
        headerCallback: function(thead, data, start, end, display) {
            $(thead).addClass('bg-secondary');
        },
		buttons: [
			{
				text: 'Reload',
				action: function (e, dt, node, config) {
					dt.ajax.reload();
				}
			},
			{
			    extend: 'excelHtml5',
			    text: 'Excel',
			    filename: 'تقرير العهده الماليه',
			    exportOptions: {
			        columns: [1, 2, 3, 4, 5, 6] // تصدير الأعمدة رقم 1, 2, 4, 6 فقط
			    },
			    customize: function (xlsx) {
			        var sheet = xlsx.xl.worksheets['sheet1.xml'];
			        var $sheet = $(sheet);
			
			        // احذف الصف الثاني (صف الفلاتر)
			        $sheet.find('row[r="3"]').remove();
			
			        // أعد ترقيم الصفوف المتبقية (مهم لتجنّب فراغ في Excel)
			        $sheet.find('row').each(function (i) {
			            var newNum = i + 1;
			            $(this).attr('r', newNum);
			            $(this).find('c').each(function () {
			                var col = $(this).attr('r').replace(/[0-9]/g, '');
			                $(this).attr('r', col + newNum);
			            });
			        });
			    }
			}
			


			
		],
		pageLength: -1,
        lengthMenu: [[10, 25, 50, 100, 500, -1], [10, 25, 50, 100, 500, "الكل"]],
		rowId: 'id'
	}).on('draw', function() {
		// هذه سيتم تشغيله بعد اكتمال عرض البيانات
		if(!$('#SelectUserDelivery').val()){
			$('.btnPayment').each(function() {
				$(this).removeClass('bg-info').addClass('bg-secondary');
				$(this).prop('disabled', true);
			});
		}
		if (!$('#Olhbtn li a.active.show').length) {
			$('#OLAll').addClass('active show');
		}
		
		custodyTotal();
		$('.custodyTotal .sup').html(supTotal());
	});
	
	// دالة لتجميع كل الفلاتر وتنفيذ draw
	function applyAllColumnFilters() {
	    table.column(1).search($('#FiOrderNumber').val());
	    table.column(2).search($('#FiStatus').val());
	    table.column(3).search($('#FiClient').val());
	    table.column(4).search($('#FiDelivary').val());
	    table.column(5).search($('#daterange').val());
	    table.column(6).search($('#FiPrice').val());
	    table.draw(); // Apply all filters at once
	}
	
	let typingTimer;  // المؤقت
	let doneTypingInterval = 1000;  // الوقت قبل تنفيذ البحث (بالملي ثانية)
	
	$('#tbl thead tr:eq(1) th .column-filter').on('input change', function() {
	    clearTimeout(typingTimer);
	    typingTimer = setTimeout(() => {
	        applyAllColumnFilters();
	    }, doneTypingInterval);
	});
	$('#daterange').on('apply.daterangepicker', function (ev, picker) {
		clearTimeout(typingTimer);
	    typingTimer = setTimeout(() => {
	        applyAllColumnFilters();
	    }, doneTypingInterval);
	})
	
	// أحداث الأزرار
	table.on('click', '.dt-control', function(e) {
		let tr = e.target.closest('tr');
		let row = table.row(tr);
		table.rows().every(function() {
			if (this.child.isShown() && this.index() !== row.index()) this.child.hide();
		});
		console.log(row.data());
		let rowdata = row.data();
		let datasend ={
			'ORDER_ID'     : rowdata[0], 
			'subOid' : rowdata[1],
			'ORDERBY'	   : rowdata[2],
			'DELIVERYBY'   : rowdata[3],
			'DELIVERY_DATE': rowdata[4],
			'ORDER_SUCCESS': rowdata[5],
			'LAST_UPDATE'  : rowdata[6],
			'DATE_CREATED' : rowdata[7],
			'ORDER_TYPE_NAME':rowdata[8],
			'DELIVERYID'   : rowdata[9],
			'CUST_STAT'    : rowdata[10],
			'STA_ID'       : rowdata[11],
			'STA_NAME'     : rowdata[12],
			'ORDER_STATUSE': rowdata[13],
			'INVENT_STAT'  : rowdata[14],
			'COLOR_CLASS'  : rowdata[15],
			'RETURNS'      : rowdata[16],
			'PRODUCTS_COUNT':rowdata[17],
			'TOTAL_QTY'    : rowdata[18],
			'REJECTED_QTY' : rowdata[19],
			'DELIVERED_QTY': rowdata[20],
			'TOTAL_PRICE'  : rowdata[21],
			'TOTAL_DISCOUNT':rowdata[22],
			'TOTAL_REJECTED_PRICE':rowdata[23],
			'TOTAL_DELIVERED_PRICE': rowdata[24],
			'TOTAL_DELIVERED_DISCOUNT': rowdata[25],
			'PRICE' : rowdata[26],
			'SHIP_VAL'     : rowdata[27],
			'REQUIRED_PAYMENT':rowdata[28],
			'PRODUCTS_DATA': rowdata[29],
            'CLIENT_NAME'  : rowdata[30],
			'ADDRESS'      : rowdata[31],
			'TELEPHONE'    : rowdata[32],
			'CLIENT_EMAIL' : rowdata[33],
			'SHIPPING_DATE': rowdata[34],
			'DELIVERED_DATE': rowdata[35],
		}
		row.child.isShown() ? row.child.hide() : row.child(format(datasend)).show();
	});
	table.on('click', '.btnOview', function(e) {
		let tr = e.target.closest('tr');
		let row = table.row(tr);
		vieworder(row);
	});
	table.on("click", "#btnPayment", function(e) {
		if ($("#SelectUserDelivery").val()) {
			$(this).hide();
			$(this).parent('td').find('.btnCancelPayment').show();
			let tr = e.target.closest('tr');
			let row = table.row(tr);
			let rowData = row.data();
		
			orderPayArr[rowData[0]] = row.data();
			console.log(orderPayArr)
			$('.custodyTotal .sup').html(supTotal());
			$('.onumBtnPay').html(Object.keys(orderPayArr).length);
			//$(this).removeClass('bg-info').addClass('bg-secondary').prop('disabled', true);
		} else {
			Toast_fire(['i', 'من فضلك اختر المندوب أولاً.']);
		}
	});
	
	table.on("click", "#btnCancelPayment", function(e) {
		
			$(this).hide();
			$(this).parent('td').find('.btnPayment').show();			let tr = e.target.closest('tr');
			let row = table.row(tr);
			let rowData = row.data();
			
			delete orderPayArr[rowData[0]];
			$('.custodyTotal .sup').html(supTotal());
			if(Object.keys(orderPayArr).length>0){
				$('.onumBtnPay').html(Object.keys(orderPayArr).length);
			}else{
				$('.onumBtnPay').html('');
			}
			//$(this).removeClass('bg-info').addClass('bg-secondary').prop('disabled', true);

	});
	
	$(document).on("click", "#btnSelectAllPayment", function(e) {
		$('.btnPayment').each(function() {
			console.log('all');
		    $(this).click();
		});
		$(this).hide();
		$("#btnCancelAllPayment").show();
	});
	
	$(document).on("click", "#btnCancelAllPayment", function(e) {
		$('.btnCancelPayment').each(function() {
		    $(this).click();
		});
		$(this).hide();
		$("#btnSelectAllPayment").show();
	});
	
	$(document).on("click", ".OLF", function () {
		let oltype = $(this).attr('id');
		const new_urlov = urlVORP+'&did='+$("#SelectUserDelivery").val()+"&cofi="+oltype
		table.ajax.url(new_urlov).load();
	});
	$(document).on("click", "#btnPay", function () {
		
		if(Object.keys(orderPayArr).length){
			
			let totalprice = 0.00,
				totaldelivered = 0,
				totalshipping = 0,
				orderIds = {} ;
			console.log(orderPayArr)
			Object.keys(orderPayArr).forEach((total) => {
				totalprice += Number(orderPayArr[total][28]);
				totaldelivered += Number(orderPayArr[total][26]);
				totalshipping += Number(orderPayArr[total][27]);
				orderIds[total] = {6 : orderPayArr[total][6]};
			});
			let msg =`<h4 class="text-info">المطلوب<span class="text-danger"> ${totalprice.toLocaleString('en-us')}</span> جنيه </h4>
			
			<h6 class="fw-light fs-6 mt-5 mb-0 pb-0">
				مقسمه الى:
			<br>
			 قيمة مبيعات المنتجات ${totaldelivered.toFixed(2).toLocaleString()}
			<br>
			 قيمـة خـدمـات الشحـن ${totalshipping.toFixed(2).toLocaleString()}
			</h>`;
			let rqArray = {
				'CONTROL':'CollectingFinancialPledge',
				'delivery_id':$('#SelectUserDelivery').val(),
				'orderObj': orderIds,//orderPayArr,
				'totalAmount':totalprice
			};
			
			Swal.fire({
				title:"تأكيد سداد ",
				html:  msg,
				icon : '',
				showDenyButton: true,
				showCancelButton: true,
				confirmButtonText:'تأكيد',
				denyButtonText: 'الغاء قائمة الاوامر',
				cancelButtonText:'إلغاء',
				customClass: {
					actions: 'my-actions',
			    	confirmButton: 'order-1',
			    	denyButton: 'order-2',
			    	cancelButton: 'order-3 right-gap',
				},

			}).then((result) => {
				
				console.log(rqArray)
				if(result.isConfirmed){
					
					let res = sendRequest(urlfinance,rqArray, false);
					Object.keys(orderPayArr).forEach(key => delete orderPayArr[key]);
					$('.onumBtnPay').html('');
					$('.btnPayment').attr('disabled',false).removeClass('bg-secondary').addClass('bg-info');
					responseMsg(res);
					table.ajax.reload();
				}else if(result.isDenied){
					Object.keys(orderPayArr).forEach(key => delete orderPayArr[key]);
					$('.onumBtnPay').html('');
					$('.btnPayment').attr('disabled',false).removeClass('bg-secondary').addClass('bg-info');
					$("#btnCancelAllPayment").click();

				}
			});
		} else {
			Swal.fire({
			  title: "عفوا!",
			  text:  "لايمكن تنفيذ الاجراء ... من فضلك قم بإختيار اوامر البيع اولا!",
			  icon: "warning"
			});
		}
		
	});
	$(document).on("change", "#SelectUserDelivery", function() {
		const val = $(this).val(), new_urlov = urlVORP+'&did='+val+'&cofi='+$('#Olhbtn li a.active.show').attr('id');
		Object.keys(orderPayArr).forEach(key => delete orderPayArr[key]);
		$('.onumBtnPay').html('');
		
		
		table.ajax.url(new_urlov).load().on('draw', function() {
			// هذه سيتم تشغيله بعد اكتمال عرض البيانات
			if (val) {
				$("#btnSelectAllPayment").prop('disabled', false);
			    $('.btnPayment').each(function() {
			        $(this).prop('disabled', false);
			        $(this).removeClass('bg-secondary').addClass('bg-info');
			    });
			} else {
				//$("#btnCancelAllPayment").click();
				//$("#btnSelectAllPayment").prop('disabled', true);
			    $('.btnPayment').each(function() {
			        
			        $(this).removeClass('bg-info').addClass('bg-secondary');
			        $(this).prop('disabled', true);
			    });
			}
			custodyTotal();
			
		});
	});
	
});