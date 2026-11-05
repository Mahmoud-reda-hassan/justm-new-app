var getulog = localStorage.getItem('orderlog');
var ordercook = JSON.parse(localStorage.getItem('newOrder'));
var ulog =[] ;
var existsArr  = '';
const rqFil   = rqof;
const urlvo = `../../inc/controller/viewOrders.control.php`;
var currentSearch = "";
var tblClintOrders = null;
var clientId = -1
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
function showInventory(){
	$('#addorderform ,#editClientData ,#sreachform ,#addClientform').hide();
	$('#DOL').show();
	$('#backtoOrder').show();
}
function backtoOrder(){
	btnInvetPro
	$('#DOL ,#backtoOrder').hide();
	if ($("#ClintOrdersList").is(":visible")) {
		$('#ClintOrdersList').fadeOut(500);
		setTimeout(function(){
			$("#fchcil").fadeIn(500);
			$("#result").fadeIn(500);
			$(".alert-box").fadeIn(500);
		},500);
	}else{
		$('#btnInvetPro ,#addorderform').show();
	}
	
}
$(document).ready(function (){
	$('.btn-app-pade').append('<button id="backtoOrder" class="btn btn-app-tools text-primary backtoOrder" onclick="backtoOrder()" style="display:none"><i class="fas fa-arrow-left-long"></i><span>رجوع</span></button>');
	$('.btn-app-pade').append('<button id="btnInvetPro" class="btn btn-app-tools text-primary btnInvetPro" onclick="showInventory()" style="display:none"><i class="fas fa-warehouse"></i><span>المخزون</span></button>');
    if(!getulog){
        ulog = [ordernum];
        localStorage.setItem('orderlog',JSON.stringify(ulog));
    } else {
        if(ordernum == getulog[0]){
            ulog = JSON.parse(localStorage.getItem('orderlog') || '[]') || [];
            localStorage.setItem('orderlog',JSON.stringify(ulog));
        } else {
            ulog = [ordernum];
            localStorage.setItem('orderlog',JSON.stringify(ulog));
        }
    }
    

	tblClintOrders = $('#tblClintOrders').DataTable({
		    dom: 'litp', // يخفي صندوق البحث التلقائي
		    scrollY : false,
	        scrollX : true,
		    processing: true,
		    serverSide: true,
		    deferLoading: 0,
		    ordering: false,
		    ajax: {
		        url: urlvo,
		        type: 'GET',
		        data: function(d) {
		        	d.CONTROL = 'ClientOrderlist';
		            d.ptype = 2;
		            d.url = wls;
		            d.clientId = clientId;
		        }
		    },
	        columns: [
	        	{
	        		data: '', 
	                title: 'عرض',
	                className: 'text-center text-nowrap dt-control',
	                render: (data, type, row) => {
	                	 return `<button id="btnOview" type="button" data-id="954" class="btn btn-app-tools btn-xs text-primary border btnOview  w-100 h-100 f-3">
				    				<i class="fas fa-eye"></i>
				    			</button>`
	                }
	        	},
	        	{
	        		data: 'subOid', 
	                title: 'رقم الامر',
	                className: 'text-center text-nowrap ',
	                
	        	},
	        	{
	        		data: 'STA_NAME', 
	                title: 'الحاله',
	                className: 'text-center text-nowrap',
	                render: (data, type, row) => {
	                	return`<div class="d-flex justify-content-between m-2" style ="position: relative;">
	                        	<div class="badge badge-${row["COLOR_CLASS"]} w-100"><i class="fa-regular fa-clock"></i> ${data}</div>
	                        </div>`;
	                }
	        	},
	        	{ 
	                data: 'CLIENT_NAME', 
	                title: 'العميل',
	                className: 'text-center text-nowrap',
	        	},
	        	{ 
	                data: 'DATE_CREATED', 
	                title: 'تاريخ الانشاء',
	                className: 'text-center text-nowrap',
	                render: (data, type, row) => {
	                	return`<div class="f-5 d-flex justify-content-between m-2">
	                        	<div class="badge5"><i class="far fa-calendar-plus"></i> ${data}</div>
	                        </div>`;
	                }
	        	}
	        ],
	        columnDefs: [
	            {
	                targets: ['_all'],
	                className: 'mdc-data-table__cell text-nowarp text-center ',
	            },
	        ],
	        buttons: [],
	        /*paging: {
	            pageLength: 5,
	        },*/
	        
    		//lengthMenu: [[5,10, 25, 50, 100,], [5,10, 25, 50, 100]],
	        order: [[0, 'desc']]
	       
	                    
	}).on('draw',function(){
		tblClintOrders.columns.adjust();
	});
	 
    table = $('#tbl').DataTable({
	        scrollY : '70vh',
	        scrollX : true,
	        deferLoading: 0,
	        ajax: {
	        	url:'../../inc/Api.php',
	        	type:'POST',
	        	//data:{'CONTROL':'getInventory','stoid':1}
	        	data: function(d) {
		        	d.CONTROL = 'getInventory';
		        	d.stoid = 1,
		            d.ptype = 2;
		            d.ex = 2;
		            //d.st = 0;
		            //d.last_update = '2000-01-01'
		            //d.url = wls;
		            //d.search.value = 'ABCD' // نبعت البحث يدوي
		        }
	        },
	        processing: true,
	        serverSide: true,
	        columns: [
	            { title: "كود", data: "PRO_CODE" ,orderable: true }, // العمود الأول
            	{ title: "اسم المنتج", data: "PRO_NAME" ,orderable: true }, // العمود الثاني
            	{ title: "وصف المنتج", data: "OPT_JSON",className:"text-nowrap col-6" , orderable: true}, // العمود الرابع
            	{
				orderable: true,
				className: 'text-center col-12 text-nowrap',
				render: (data, type, row, meta) => {
					let store ="USER_QTY";
					if(Go =='EditOrder' || Go =='NewOrder'){
		                if(ptype==1 && ordercook['exists_id'] <7){
		                    store='ADMIN_QTY';
		                } else if(ptype==1 && ordercook['exists_id'] ==7){
		                    store='PROCES_QTY';
		                } else if(ptype==1 && ordercook['exists_id'] > 7){
		                	store='STORE_QTY'
		                }
					}
					return parseInt(row[store]);
				},
				title: 'الكميه المتوفرة'
			},
            ],
	        columnDefs: [
	            {
	                targets: ['_all'],
	                className: 'mdc-data-table__cell text-nowarp text-center',
	            },
	        ],
	        buttons: [
		        {
		            text: 'تحديث البيانات',
		            action: function ( e, dt, node, config ) {
		                dt.ajax.reload();
		            }
		        }
		    ],
	        paging: {
	            pageLength: 50,
	        },
	}).on('draw', function() {
		table.columns.adjust();
	});
	
	tblClintOrders.on('click', '.dt-control', function(e) {
		let tr = e.target.closest('tr');
		let row = tblClintOrders.row(tr);
		tblClintOrders.rows().every(function() {
			if (this.child.isShown() && this.index() !== row.index()) this.child.hide();
		});
		row.child.isShown() ? row.child.hide() : row.child(format(row.data())).show();

	});
	
})
let drop = "";
if(Go ==="NewOrder" ){
	
	if(ordertype === 1){
		drop  = '<button id="btnSave_order" data-exid="sav" data-vid="saveAndReceived"  type="button" class="btn btn-default m-1 pr-4 btn-primary">حفظ وتسليم</button>';
    	drop += '<button id="btnSave_order" data-exid="sav" data-vid="sav"  type="button" class="btn btn-default m-1 pr-4 btn-success">حفظ للشحن</button>';
	}else{
		drop  = '<button id="btnSave_order" data-exid="sav" data-vid="sav"  type="button" class="btn btn-default m-1 pr-4 btn-success">حفظ</button>';
	}
    drop += '<button id="btnCancel_order" type="button" class="btn btn-default m-1 pr-4 btn-danger">إلغاء</button>';
    drop += '<div id="btn_drop" class="order-droo  dropdown-menu"></div>';
    
}else if(Go ==="EditOrder"){
    drop  = '<button id="btnSave_order" data-exid="sav" data-vid="sav"  type="button" class="btn btn-default m-1 pr-4 bg-gradient-success">حفظ</button>';
    //drop  = '<button type="button" class="btn btn-default bg-gradient-success btnSave_order dropdown-toggle" data-toggle="dropdown" aria-expanded="true">تأكيد</button>';
	drop += '<button id="btnCancel_order"type="button" class="btn btn-default m-1 pr-4 bg-gradient-danger">إلغاء</button>';
	drop += '<div id="btn_drop" class="order-drop dropdown-menu" style="z-index: 99999; ">';
	if(ordertype == 2 || ordertype == 3 || ordertype == 4 || ordertype == 5){
		drop += '<a id="btnSave_order" data-exid="sav" data-vid="sav" class="dropdown-item"  onclick="return false;">حفظ</a>';
	//	drop += '<div class="dropdown-divider"></div>';
	//	drop += '<a id="btnSave_order" data-exid="ser" data-vid="ser" class="dropdown-item" onclick="return false;">خدمة العملاء</a>';
    }else if(ordertype == 1 ){
		for(i=0;i<rqFil[1][1].length;i++){
			if (Array.isArray(ordercook)) {
				if(ordercook.length && ordercook['exists_id'] != rqFil[1][1][i][0]){
					if(userlogin_data['uScript']['Admin']['orderlist_ex'+ordercook['exists_id']]['transformation'] == "true" && userlogin_data['uScript']['Admin']['orderlist_ex'+ordercook['exists_id']][rqFil[1][1][i][3]] == "true"){
				//		existsArr+='<a id="btnSave_order" data-exid="'+rqFil[1][1][i][0]+'" data-vid="'+rqFil[1][1][i][1]+'" class="dropdown-item btnSave_order" onclick="return false;">'+rqFil[1][1][i][2]+'</a>';
					}
				}
			}
		}
				
		drop += '<a id="btnSave_order" data-exid="sav" data-vid="sav" class="dropdown-item" href="/" onclick="return false;">حفظ</a>';
	//	drop += '<div class="dropdown-divider"></div>';
	//	drop += '<a id="btnSave_order" data-exid="ser" data-vid="ser" class="dropdown-item" onclick="return false;">حفظ وتحويل الى .. </a>';
	//	drop += existsArr;
		//forech (){}
	}
    drop += '</div>';
    //
    //$(this).attr("data-toggle","dropdown");
    //$('.order-drop').remove();
    
}
$('.order-foot').html(drop);
/*====================================================================================================
function client data
======================================================================================================*/
$(document).on("click", '.edit-btn', function() {
        let clientId = $(this).data("id");
        let clientNameElement = $("#client-name-" + clientId);
        let currentName = $.trim(clientNameElement.contents().first().text());

        Swal.fire({
            title: "تعديل اسم العميل",
            input: "text",
            inputValue: currentName,
            showCancelButton: true,
            confirmButtonText: "حفظ",
            cancelButtonText: "إلغاء",
            inputValidator: (value) => {
                if (!$.trim(value)) {
                    return "الاسم لا يمكن أن يكون فارغًا!";
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                let newName = $.trim(result.value);

                // إرسال الطلب عبر Ajax لتحديث الاسم في قاعدة البيانات
                $.post(urlApi, {CONTROL : 'updateClientName', cli_id: clientId, cli_name: newName }, function(data) {
                    if (data[0] == 's') {
                        clientNameElement.html(newName + 
                            ' <button class="btn btn-sm btn-outline-primary edit-btn" data-id="'+ clientId +'">' +
                            '<i class="fas fa-edit"></i></button>'
                        );
                        let cookie = JSON.parse(localStorage.getItem('newOrder'));
                        cookie['uname'] = newName;
                        localStorage.setItem('newOrder',JSON.stringify(cookie))
                        
                        Swal.fire("تم التحديث!", "تم تعديل اسم العميل بنجاح.", "success");
                        
                    } else {
                        Swal.fire("خطأ!", "حدث خطأ أثناء التحديث.", "error");
                    }
                }, "json");
            }
        });
});
function edittel(tel_id, cli_tel, whatsapp, telegram, defultTel) {
    Swal.fire({
        title: 'إضافة رقم هاتف جديد',
        //type: 'success',
        html: '<div id="swl-tel" style="font-size:15px"><div>',
        //'<div class="form-group"><label class="col-sm-2 d-flex justify-content-start" for="COM_TEL">الهاتف</label><input type="number" class="form-control phone1 cli req" id="cli_tel" name="clitel" placeholder="ادخل رقم الهاتف" required></div>',
        showCancelButton: true,
        confirmButtonText: 'حفظ',
        cancelButtonText: 'إلغاء',
        didOpen: () => {
        	
            elPhone($("#swl-tel"));
            $("#cli_tel").val(""+cli_tel.toString()+"").change();
            $("#whatsapp").prop('checked', whatsapp);
            $("#telegram").prop('checked', telegram);
            if (defultTel === 1) {
                $("#defultTel").prop('checked', true);
                $("#defultTel").prop('disabled', true);
            }
        },
        preConfirm: function () {
            let err = 0,
            el = $("#swl-tel"),
            ajaxpar = {};
            ajaxpar['CONTROL'] = 'updateClientTel';
            ajaxpar['tel_id'] = tel_id;
            ajaxpar['cli_tel'] = $('#cli_tel').val();
            if ($('.tele-type1').is(':checked')) {
                ajaxpar['tel_type'] = 'هاتف جوال';
            } else if ($('.tele-type2').is(':checked')) {
                ajaxpar['tel_type'] = 'هاتف أرضى'
            }
            if ($('.whatsapp').is(':checked')) {
                ajaxpar['whatsapp'] = '1';
            } else {
                ajaxpar['whatsapp'] = '0';
            }
            if ($('.telegram').is(':checked')) {
                ajaxpar['telegram'] = '1';
            } else {
                ajaxpar['telegram'] = '0';
            }
            if ($('#defultTel').is(':checked')) {
                ajaxpar['defultTel'] = '1';
            } else {
                ajaxpar['defultTel'] = '0';
            }
            if ($('#cli_tel').hasClass('is-invalid') === true) {
                Swal.showValidationMessage('جميع الحقول المحدده باللون الاحمر مطلوبة');
            } else if ($('#cli_tel').val() == '' || !$('#cli_tel').hasClass("is-valid")) {
                Swal.showValidationMessage('جميع الحقول المحدده باللون الاحمر مطلوبة');
                $('#cli_tel').addClass("is-invalid");
            } else {
                let datarq = sendRequest(urlControl, ajaxpar);
                if (datarq[0]==="msg") {
                    Swal.showValidationMessage(datarq[1]);
                } else {
                    let d = JSON.parse(localStorage.getItem('orderlog'));
                    if( d && typeof d == "object" && cli_tel != ajaxpar["cli_tel"]){
                        d.push('* تم تغير رقم الهاتف من: '+ cli_tel +' الى: '+ajaxpar["cli_tel"]+' - للعميل: '+$(".widget-user-username").text());
                        localStorage.setItem('orderlog',JSON.stringify(d))
                    }
                    return datarq;
                }
            }
        }
    }).then((result) => {
        if (typeof result.value === "object") {
            if($("#sreachform").is(":visible")){
                $("#btnSearchClient").click();
            } else if($("#editClientData").is(":visible")){
                $(".editcliOrder").click();
            }
            Toast_fire(result.value);
        }
    })

};
function delclitel(cli_id, tel_id, tel_num) {
    let el = $(this), datarq, ajaxpar = {};
    ajaxpar['CONTROL'] = 'deleteClientTel';
    ajaxpar["cli_id"] = cli_id;
    ajaxpar["tel_id"] = tel_id;

    Swal.fire({
        title: 'انت متأكد ؟',
        text: " سوف يتم حذف "+tel_num.toString()+" نهائيا ولا يمكن استرجعاه مره اخرى",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'حذف',
        cancelButtonText: 'إلغاء',
        didOpen: () => {
            
        },
        preConfirm: function () {
            datarq = sendRequest(urlControl, ajaxpar);
            let d = JSON.parse(localStorage.getItem('orderlog'));
            if(d && typeof d == "object"){
                d.push('* تم حذف الهاتف: '+ tel_num+' من حساب العميل: '+$(".widget-user-username").text());
                localStorage.setItem('orderlog',JSON.stringify(d))
            }
            return datarq;
        }
    }).then((result) => {
        if (typeof result.value === "object") {
            if($("#sreachform").is(":visible")){
                $("#btnSearchClient").click();
            } else if($("#editClientData").is(":visible")){
                $(".editcliOrder").click();
            }
            Toast_fire(result.value);
        }
    })
};
function editAdd(add_id, country_id, gov_id, city_id, address_type, address, defultAdd) {
    Swal.fire({
        title: 'تعديل عنوان',
        //icon: 'success',
        html: '<div id="swl-Add" style="font-size:15px"></div>',
        showCancelButton: true,
        confirmButtonText: 'حفظ',
        cancelButtonText: 'إلغاء',
        didOpen: () => {
            addressclient($('#swl-Add'), "", country_id, gov_id, city_id);
            $("#cli_address").val(address);
            if (address_type === 'المنزل') {
                $('#address_type1').prop('checked', true)
            } else {
                $('#address_type2').prop('checked', true)
            }
            if (defultAdd === 1) {
                $('#defultAdd').prop('checked', true);
                $('#defultAdd').prop('disabled', true);
            }
            $(".addressCity").select2({
			    placeholder: "اختر المدينة",
			    allowClear: true,
			    dir: 'rtl',
			    dropdownParent: $('.swal2-popup'),
			    matcher: function(params, data) {
			        
			        // لو مفيش بحث
			        if ($.trim(params.term) === '') {
			            return data;
			        }
			
			        // لو العنصر ملوش نص
			        if (typeof data.text === 'undefined') {
			            return null;
			        }
			
			        // البحث من أول الكلمة فقط
			        if (data.text.toLowerCase().startsWith(params.term.toLowerCase())) {
			            return data;
			        }
			
			        return null;
			    }
			});
			$(".addressGov").select2({
				placeholder: "اختر المحافظة", 
				allowClear: true ,
				dir : 'rtl',
				dropdownParent: $('.swal2-popup')
			});
			$(".addressCountry").select2({
				placeholder: "اختر الدولة", 
				allowClear: true ,
				dir : 'rtl',
				dropdownParent: $('.swal2-popup')
			});
			
			
        },
        preConfirm: function () {
            let ajaxpar = {};
            ajaxpar['CONTROL'] = 'updateClientAdd';
            ajaxpar['add_id'] = add_id;
            ajaxpar['addressCity'] = $("#addressCity").val();
            ajaxpar['cli_address'] = $("#cli_address").val();

            if ($('#address_type1').is(':checked')) {
                ajaxpar['address_type'] = 'المنزل';
            } else if ($('#address_type2').is(':checked')) {
                ajaxpar['address_type'] = 'العمل'
            }
            if ($('#defultAdd').is(':checked')) {
                ajaxpar['defultAdd'] = '1';
            } else {
                ajaxpar['defultAdd'] = '0';
            }
            $(".req").each(function() {
                // alert($(this).attr('id')+"-"+$(this).val());
                $(this).removeClass('is-invalid');
                if ($(this).val() === "" || $(this).val() === null) {
                    $(this).addClass('is-invalid');
                }
            })
            if ($('#cli_address').val() === "" || $('#addressCity').val() === null) {
                Swal.showValidationMessage('جميع الحقول المحدده باللون الاحمر مطلوبه');
            } else {
                let datarq = sendRequest(urlControl, ajaxpar);
                if (datarq[0]==="msg") {
                    Swal.showValidationMessage(datarq[1]);
                } else {
                    let d = JSON.parse(localStorage.getItem('orderlog'));
                    if(d && typeof d == "object" && address != ajaxpar['cli_address']){
                        d.push('* تم تغير العنوان: '+ address +' الى: '+ajaxpar["cli_address"]+' - للعميل: '+$(".widget-user-username").text());
                        localStorage.setItem('orderlog',JSON.stringify(d))
                    }
                    return datarq;
                }
            }
        }
    }).then((result) => {
        if (typeof result.value === "object") {
            if($("#sreachform").is(":visible")){
                $("#btnSearchClient").click();
            } else if($("#editClientData").is(":visible")){
                $(".editcliOrder").click();
            }
            Toast_fire(result.value);
        }
        $(".addradio").each(function () {
            if (add_id*1 === $(this).attr('id')*1) {
                $(this).prop('checked', true);
            }
        });
    })
}
function delcliAdd(cli_id, add_id,el) {
    let datarq, ajaxpar = {};
    let addresstext=el.parents('tr').children('td').eq(0).children('div').children('label').html();
    ajaxpar['CONTROL'] = 'deleteClientAdd';
    ajaxpar["cli_id"] = cli_id;
    ajaxpar["add_id"] = add_id;
    Swal.fire({
        title: 'انت متأكد ؟',
        text: " سوف يتم حذف هذا العنوان نهائيا ولا يمكن استرجعاه مره اخرى",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'حذف',
        cancelButtonText: 'إلغاء',
        preConfirm: function () {
            datarq = sendRequest(urlControl,ajaxpar);
            Toast_fire(datarq);
            if($("#sreachform").is(":visible")){
                $("#btnSearchClient").click();
            } else if($("#editClientData").is(":visible")){
                $(".editcliOrder").click();
            }
            let d = JSON.parse(localStorage.getItem('orderlog'));
            if(d && typeof d == "object"){
                d.push('* تم حذف العنوان : '+ addresstext +' - من حساب العميل : '+$(".widget-user-username").text());
                localStorage.setItem('orderlog',JSON.stringify(d))
            }
            return datarq;
        }
    }).then((result) => {
       
    })
};
/*====================================================================================================
function order and product
======================================================================================================
function shippingDateStr(shippDate, shipWaiting, deliveredDate = null) {
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
};*/
function addPorductcook(st=0){
    ulog = JSON.parse(localStorage.getItem('orderlog') || '[]') || [];
    let a,b,rows,rownum,htm="",objkey="",
    new_order = JSON.parse(localStorage.getItem('newOrder'));
    /*if(localStorage.getItem('itemarr') === null){
        localStorage.setItem('itemarr',JSON.stringify({}));
    }*/
	a= JSON.parse(localStorage.getItem('itemarr') || '{}') || {};
    /*if(localStorage.getItem("swlitem")){
        b= JSON.parse(localStorage.getItem("swlitem"));
	    if(Object.keys(a).length>0){
		    let proId   = $('#swl-productes').attr('data-id'),
		        proCode = $('#swl-productes').attr('data-code');
		    let m = 0;
			if(proId!=='undefined' && proCode!=='undefined'){
			//update product
				if(Object.values(a[proCode]['OPTION_TEXT']).toString() !== Object.values(b['OPTION_TEXT']).toString()){
					for(var i in a[proCode]['OPTION_TEXT']){
						if(a[proCode]['OPTION_TEXT'][i] != b['OPTION_TEXT'][i]){
							m++;
							ulog.push('* تم تغير ' +a[proCode]['OPTION_TEXT'][i] + ' إلى ' +b['OPTION_TEXT'][i] + ' لـ ' + a[proCode]['PRO_NAME']);
						}
					}
				}
				if(parseFloat(a[proCode]['qty']).toFixed(2) !== b['qty']){
					m++;
					ulog.push('* تم تغير الكمية من ' + parseFloat(a[proCode]['qty']).toFixed(2)+ ' إلى ' +b['qty'] + ' لـ ' + a[proCode]['PRO_NAME']);
				}
				if(a[proCode]['dis'] != b['dis']){
					m++;
					ulog.push('* تم تغير الخصم من ' +a[proCode]['dis'] + ' إلى ' +b['dis'] + ' لـ ' + a[proCode]['PRO_NAME']);
				}
		        delete a[proCode];
		    }
		    for(var i in a){
		        var qty1 = parseInt(b['qty']) , qty2= parseInt(a[i]['qty']);
			    if(b['PRO_CODE'] === a[i]['PRO_CODE'] ){
			        b.qty = qty1 + qty2;
					if(a[i]['qty'] !== b['qty']){
						m++;
						ulog.push('* تم تغير الكمية من ' +a[i]['qty'] + ' إلى ' +b['qty'] + ' لـ ' + a[i]['PRO_NAME']);
					}
					if(a[i]['dis'] !== b['dis']){
						m++;
						ulog.push('* تم تغير الخصم من ' +a[i]['dis'] + ' إلى ' +b['dis'] + ' لـ ' + a[i]['PRO_NAME']);
					}
				    delete a[i];
			    }
		    }
			if(m===0){
				ulog.push('* تم اضافة المنتج ' + b['PRO_NAME']+' '+b['OPTION_TEXT'].toString()+' - الكمية: ' +b['qty'] + ' - الخصم: ' +b['dis']);
			}
			m = 0;
		    a[b['PRO_CODE']]= b;
		    localStorage.setItem('itemarr',JSON.stringify(a));
		    localStorage.removeItem('swlitem');
	    } else if(Object.keys(b).length>0 && parseInt(b['qty'])>0){
		//new product
			ulog.push('* تم اضافة المنتج ' + b['PRO_NAME']+' '+b['OPTION_TEXT'].toString()+' - الكمية: ' +b['qty'] + ' - الخصم: ' +b['dis']);
		    a[b['PRO_CODE']]= b;
		    localStorage.setItem('itemarr',JSON.stringify(a));
		    localStorage.removeItem('swlitem');
	    }
    }*/
    localStorage.setItem('itemarr',JSON.stringify(a));
    localStorage.setItem('orderlog',JSON.stringify(ulog));
    localStorage.removeItem('swlitem');
    if (typeof Go !== 'undefined' && (Go === 'NewOrder' || Go === 'EditOrder') && typeof renderCartItems === 'function') {
        return renderCartItems(a, new_order || {});
    }
	$('.item').remove();
	let tp=0;
	for(var f in a){
	    let o = a[f]["OPTION_TEXT"],
	        p = ((a[f]["CODE_PRICE"] * a[f]["qty"]) - (a[f]["dis"]*a[f]["qty"]));
	        tp=tp+p;
	    if(o===null){
	        o='';
	    } else {
			let m = "";
			for(let h in o){
				if(m === ""){
					m += o[h];
				}else{
				m += " | " + o[h];
				}
			}
			o = m; 
		}
		htm+='<tr class="item itemorder bg-color"><td class="col-sm-2 text-center tblitemcode">'+a[f]["PRO_CODE"]+'</td>';
		htm+='<td class="text-nowrap text-left"><div>'+a[f]["PRO_NAME"]+'</div><a class="text-info">'+o+'</a></td>';
	    htm+='<td class="col-sm-1 text-center">'+parseInt(a[f]["qty"])+'</td>';
		htm+='<td class="col-sm-1 text-center">'+a[f]["CODE_PRICE"]+'</td>';
		htm+='<td class="col-sm-1 text-center">'+parseFloat(a[f]["dis"]).toFixed(2)+'</td>'; 
		htm+='<td class="col-sm-1 text-center">'+parseFloat(p).toFixed(2)+'</td>';
		htm+='<td class="col-sm-1 text-center"><button id="addPorduct" type="button" data-id="'+a[f]["PRODUCT_ID"]+'" data-code="'+a[f]["PRO_CODE"]+'" class="btn btn-primary btn-md"><i class="fa fa-edit fa-align-center"></i></button></td>';
		htm+='<td class="col-sm-1 text-center"><button id="btnProDele" type="button" data-code="'+a[f]["PRO_CODE"]+'" class="btn btn-danger btn-md delete"><i class="fas fa-align-center fa-close"></i></button></td></tr>';
	}
	$('#orderprice').text(parseFloat(tp).toFixed(2));
	$('#ordertotal').text(parseFloat(tp+parseInt(new_order["shipval"])).toFixed(2))
	return htm;
	
}
async function searchClientData(){
	$('#ClintOrdersList').hide();
    $('.alert-box').hide();
    $('#btnAddClient').show();
    $(this).blur();
    $("#result").html(`
        <div class="text-center my-3">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">جاري التحميل...</span>
            </div>
            <div class="mt-2">جاري البحث عن العميل...</div>
        </div>
    `).show();

    // تعريف العلامات والمتغيرات
	let clientDataReady = false;
	let tableReady = false;
	let dtCount = 0;
	let clientHTML = "";
	// جلب بيانات العميل
	let searchVal = $("#clisreach").val();
	    
    

    // تأخير لانتظار نتائج getClientData (إذا كانت متأخرة)
    setTimeout(function () {
    	
	
	    var x = getClientData(0, 0, 0, searchVal);
	    if (x !== undefined && x !== "") {
	        clientHTML = x;
	    }
	    clientDataReady = true;
	    clientId = $(x).find('#btnEditName').attr('data-id');
    	// تحميل جدول الطلبات
	    tblClintOrders.ajax.reload(function () {
	        let dt = tblClintOrders.ajax.json();
	        if (dt && dt.iTotalDisplayRecords > 0) {
	            dtCount = dt.iTotalDisplayRecords;
	        }
	        tableReady = true;
	        checkAndDisplay();
	    });
        clientDataReady = true;
        checkAndDisplay();
    }, 500);

    // دالة التحقق عند جاهزية الطرفين
    function checkAndDisplay() {
        if (clientDataReady && tableReady) {
            $("#result").html("");

            if (clientHTML !== "") {
                $("#result").html(clientHTML);

                var table = $('#tblClintOrders').DataTable();

				var info = table.page.info();
				var totalRecords = info.recordsTotal;
				
				if (totalRecords > 0) {
				    $(".alert-box .ordercount").html(totalRecords);
				    $(".alert-box").fadeIn();
				}
            } else {
                $("#result").html(`
                    <div class="text-center text-danger my-3">
                        لا يوجد بيانات مسجلة لهذا الرقم.
                    </div>
                `);
            }
        }
    }
}
/*===================================================================================================
event click button client
=====================================================================================================*/
$(document).on("click", "#btnSearchClient", function () {
   searchClientData()
   /* $('#ClintOrdersList').hide();
    $('.alert-box').hide();
    $('#btnAddClient').show();
    $(this).blur();
    $("#result").html(`
        <div class="text-center my-3">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">جاري التحميل...</span>
            </div>
            <div class="mt-2">جاري البحث عن العميل...</div>
        </div>
    `).show();

    // تعريف العلامات والمتغيرات
	let clientDataReady = false;
	let tableReady = false;
	let dtCount = 0;
	let clientHTML = "";
	// جلب بيانات العميل
	let searchVal = $("#clisreach").val();
	    
    

    // تأخير لانتظار نتائج getClientData (إذا كانت متأخرة)
    setTimeout(function () {
    	
	
	    var x = getClientData(0, 0, 0, searchVal);
	    if (x !== undefined && x !== "") {
	        clientHTML = x;
	    }
	    clientDataReady = true;
	    clientId = $(x).find('#btnEditName').attr('data-id');
    	// تحميل جدول الطلبات
	    tblClintOrders.ajax.reload(function () {
	        let dt = tblClintOrders.ajax.json();
	        if (dt && dt.iTotalDisplayRecords > 0) {
	            dtCount = dt.iTotalDisplayRecords;
	        }
	        tableReady = true;
	        checkAndDisplay();
	    });
        clientDataReady = true;
        checkAndDisplay();
    }, 500);

    // دالة التحقق عند جاهزية الطرفين
    function checkAndDisplay() {
        if (clientDataReady && tableReady) {
            $("#result").html("");

            if (clientHTML !== "") {
                $("#result").html(clientHTML);

                var table = $('#tblClintOrders').DataTable();

				var info = table.page.info();
				var totalRecords = info.recordsTotal;
				
				if (totalRecords > 0) {
				    $(".alert-box .ordercount").html(totalRecords);
				    $(".alert-box").fadeIn();
				}
            } else {
                $("#result").html(`
                    <div class="text-center text-danger my-3">
                        لا يوجد بيانات مسجلة لهذا الرقم.
                    </div>
                `);
            }
        }
    }*/
});
$(document).on("click",".alert-box", function(){
	$(this).fadeOut(500);
	
	$("#fchcil").fadeOut(500);
	$("#result").fadeOut(500);
	setTimeout(function(){
		$('#backtoOrder').show();
		$('#ClintOrdersList').fadeIn(500);
	},500);
})
$(document).on("click","#btnAddClient",function (){
	addressclient($("#coAd"),"",1,1);
	elPhone($("#cliTel"));
	$('#cli_tel').val("").change()
	let valnum = $('#clisreach').val();
	$("#cli_name").focus();
	$("#cli_name").change("");
	if(valnum.length>0){
	    $('#cli_tel').val(valnum).change();
	}
	
    //$(this).hide();
    $('#btnSearchClient').show();
	$("#btnSave").show();
	$('#btnCancel').show();
    $('#sreachform').hide(); 
    $('#addClientform').fadeIn();
	$('#btnCancel').attr('disabled',false);
	$("#btnSave").attr('disabled',false);
	$("#defultAdd").parent().hide();
	$("#defultTel").parent().hide();
	$('#btnAddClient').show();
	$(".req").keyup(function(){
	   if($(this).attr("id")=== "cli_name" && $(this).val().length <=2){
	       $(this).removeClass('is-valid');
	       $(this).addClass("is-invalid");
	   }else if($(this).attr("id")=== "cli_name" ){
	       $(this).removeClass('is-invalid');
           $(this).addClass('is-valid');
	   };
	});
	$(".addressCity").select2({
	    placeholder: "اختر المدينة",
	    allowClear: true,
	    dir: 'rtl',
	    //dropdownParent: $('.swal2-popup'),
	    matcher: function(params, data) {
	        
	        // لو مفيش بحث
	        if ($.trim(params.term) === '') {
	            return data;
	        }
	
	        // لو العنصر ملوش نص
	        if (typeof data.text === 'undefined') {
	            return null;
	        }
	
	        // البحث من أول الكلمة فقط
	        if (data.text.toLowerCase().startsWith(params.term.toLowerCase())) {
	            return data;
	        }
	
	        return null;
	    }
	});
	$(".addressGov").select2({
		placeholder: "اختر المحافظة", 
		allowClear: true ,
		dir : 'rtl',
		//dropdownParent: $('.swal2-popup')
	});
	$(".addressCountry").select2({
		placeholder: "اختر الدولة", 
		allowClear: true ,
		dir : 'rtl',
		//dropdownParent: $('.swal2-popup')
	});
	$("#cli_address").blur(function(){
		$("#cli_name").focus();
	})
	$("#cli_name").ready(function() {
		$("#cli_name").focus();
	});	
});
$(document).on("click",".newtel",function (){
        let cli_id= $(this).attr("data-cli_id");
        Swal.fire({
            title: 'إضافة رقم هاتف جديد',
            html:'<div id="swl-tel" style="font-size:15px"><div>',//'<div class="form-group"><label class="col-sm-2 d-flex justify-content-start" for="COM_TEL">الهاتف</label><input type="number" class="form-control phone1 cli req" id="cli_tel" name="clitel" placeholder="ادخل رقم الهاتف" required></div>',
            showCancelButton: true,
            confirmButtonText:  'حفظ',
            cancelButtonText: 'إلغاء',
            didOpen: () =>{
				elPhone($("#swl-tel"));
            },
            preConfirm: function () {
				let err = 0,el=$("#swl-tel"),ajaxpar={};
	            ajaxpar['CONTROL']= 'addClientTel';
	            ajaxpar['cli_id']=cli_id;
	            ajaxpar['cli_tel']=$('#cli_tel').val();
	            if($('.tele-type1').is(':checked')){
	                ajaxpar['tel_type'] = 'هاتف جوال';
	            } else if($('.tele-type2').is(':checked')){
	                ajaxpar['tel_type'] = 'هاتف أرضى'
	            }
	            if($('.whatsapp').is(':checked')){
	                ajaxpar['whatsapp'] = '1';
	            } else {
	                ajaxpar['whatsapp'] = '0';
	            }
	            if ($('.telegram').is(':checked')){
	                ajaxpar['telegram'] = '1';
	            } else {
	                ajaxpar['telegram'] = '0';
	            }
	            if ($('#defultTel').is(':checked')){
	                ajaxpar['defultTel'] = '1';
	            } else {
	                ajaxpar['defultTel'] = '0';
	            }
	            if($('#cli_tel').hasClass('is-invalid') === true ){
	                Swal.showValidationMessage('جميع الحقول المحدده باللون الاحمر مطلوبة');
	            }else if($('#cli_tel').val()==''){
                    Swal.showValidationMessage('جميع الحقول المحدده باللون الاحمر مطلوبة');
                    el.children('#cli_tel').addClass("is-invalid");
	            } else {
	                let datarq = sendRequest(urlControl,ajaxpar);
	                if(datarq[0] !== "msg"){
	                    let d = JSON.parse(localStorage.getItem('orderlog'));
                        if(d && typeof d == "object"){
                            d.push('* تم اضافة الهاتف : '+ajaxpar["cli_tel"]+' - للعميل: '+$(".widget-user-username").text());
                            localStorage.setItem('orderlog',JSON.stringify(d))
                        }
	                    return datarq
	                } else {
	                    Swal.showValidationMessage(datarq[1]);
	                }
                }
            }
        }).then((result) => {
            if(typeof result.value==="object"){
                if($("#sreachform").is(":visible")){
                    $("#btnSearchClient").click();
                } else if($("#editClientData").is(":visible")){
                    $(".editcliOrder").click();
                }
                Toast_fire(result.value);
            }
        })
    });
$(document).on("click",".newAdd",function (){
        let cli_id= $(this).attr("data-cli_id");
        Swal.fire({
                title: 'إضافة عنوان جديد',
                html:'<div id="swl-Add" style="font-size:15px"></div>',            
                showCancelButton: true,
                confirmButtonText:  'حفظ',
                cancelButtonText: 'إلغاء',
                didOpen: () =>{
                    addressclient($('#swl-Add'),"",1,1);
                    $(".addressCity").select2({
					    placeholder: "اختر المدينة",
					    allowClear: true,
					    dir: 'rtl',
					    
					    matcher: function(params, data) {
					        
					        // لو مفيش بحث
					        if ($.trim(params.term) === '') {
					            return data;
					        }
					
					        // لو العنصر ملوش نص
					        if (typeof data.text === 'undefined') {
					            return null;
					        }
					
					        // البحث من أول الكلمة فقط
					        if (data.text.toLowerCase().startsWith(params.term.toLowerCase())) {
					            return data;
					        }
					
					        return null;
					    }
					});
				    $(".addressGov").select2({
						placeholder: "اختر المحافظة", 
						allowClear: true ,
						dir : 'rtl'
					});
					$(".addressCountry").select2({
						placeholder: "اختر الدولة", 
						allowClear: true ,
						dir : 'rtl'
					});
                }, 
                preConfirm: function () {
                    let city = $("#addressCity").children(':selected').text();
                    let Gov = $("#addressGov").children(':selected').text();
                    let Country = $("#addressCountry").children(':selected').text();
                    let err = 0,el=$("#swl-Add") ,ajaxpar={};
	                ajaxpar['CONTROL']= 'addClientAdd';
	                ajaxpar['cli_id']=cli_id;
	                ajaxpar['addressCity']=$("#addressCity").val();
	                ajaxpar['cli_address']=$("#cli_address").val();
	                 
	                if($('#address_type1').is(':checked')){
	                    ajaxpar['address_type'] = 'المنزل';
	                } else if($('#address_type2').is(':checked')){
	                    ajaxpar['address_type'] = 'العمل'
	                }
	                if ($('#defultAdd').is(':checked')){
	                    ajaxpar['defultAdd'] = '1';
	                } else {
	                    ajaxpar['defultAdd'] = '0';
	                }
	                $(".req").each(function(){
	                   // alert($(this).attr('id')+"-"+$(this).val());
	                   $(this).removeClass('is-invalid');
	                   if( $(this).val()==="" ||  $(this).val()===null){
	                       $(this).addClass('is-invalid');
	                   }
	                })
	                if($("#swl-Add .is-invalid").length > 0){
                        Swal.showValidationMessage('جميع الحقول المحدده باللون الاحمر مطلوبه');
                    } else {
	                    let datarq = sendRequest(urlControl,ajaxpar);
                        if(datarq[0] !== "msg"){ 
                            let d = JSON.parse(localStorage.getItem('orderlog'));
                            if(typeof d == "object"){
                                d.push('* تم اضافة العنوان : ('+ ajaxpar['cli_address']+' - '+city+' - '+Gov+' - '+Country+') - لحساب العميل: '+$(".widget-user-username").text());
                                localStorage.setItem('orderlog',JSON.stringify(d))
                            }
                            return datarq;
	                    } else {
	                        Swal.showValidationMessage(datarq[1]);
	                    }
                    }
                }
            }).then((result) => {
                if(typeof result.value==="object"){
                    if($("#sreachform").is(":visible")){
                        $("#btnSearchClient").click();
                    } else if($("#editClientData").is(":visible")){
                        $(".editcliOrder").click();
                    }
                    Toast_fire(result.value);
                }
            })
    });
$(document).on("click","#btnCancel",function(){
	$('#btnSearchClient').show();
	$("#btnSave").hide();
	$('#btnCancel').hide();
	$('#sreachform').fadeIn();
	$('#addClientform').fadeOut();
	if (typeof jmSetOrderWizardStep === 'function' && $('#jmStepPanel3').length) {
		$('#addorderform').show();
		jmSetOrderWizardStep(3);
	} else {
		$('#addorderform').fadeOut();
	}
	$('#mailspan').remove();
	$('.cli').each(function (){
		$(this).val("");
		$(this).removeClass('is-invalid , is-valid');
    });
	$('#whatsapp').prop('checked',false);
	$('#telegram').prop('checked',false);
	$("#tel_type1").prop('checked',true);
	$("#address_type1").prop('checked',true);
	$('#btnSave').attr('disabled',true);
	$('#clisreach').focus();
});
$(document).on("click", "#btnSave", function () {
    $('.orderOv').hide();
    $('.btnSave_order').attr('disabled', false);
    let err = 0, ajaxpar = { CONTROL: 'addClient' };
   
    $('.cli').each(function () {
        ajaxpar[$(this).attr('id')] = $(this).val();
    });

    ajaxpar['tel_type'] 	= $('#tel_type1').is(':checked') ? 'هاتف جوال' : 'هاتف أرضى';
    ajaxpar['address_type'] = $('#address_type1').is(':checked') ? 'المنزل' : 'العمل';
    ajaxpar['whatsapp'] 	= $('#whatsapp').is(':checked') ? '1' : '0';
    ajaxpar['telegram'] 	= $('#telegram').is(':checked') ? '1' : '0';
    ajaxpar['defultTel']	= $('#defultTel').is(':checked') ? '1' : '0';
    ajaxpar['defultAdd']	= $('#defultAdd').is(':checked') ? 1 : 0;
	
    function validateField(selector, validator, val = true) {
	    let e = val ? $(selector).val() : $(selector);
	    if (validator(e)) {
	        $(selector).removeClass("is-invalid").addClass(" ");
	        $(selector).next('.select2-container').find('.select2-selection').removeClass("is-invalid").addClass(' ');
	        $('#mailspan').remove();
	    } else {
	        $(selector).removeClass(" ").addClass("is-invalid");
	        $(selector).next('.select2-container').find('.select2-selection').removeClass(' ').addClass("is-invalid");
	        err++;
	    }
	}
	
    function resetForm() {
    	$('btnCancel').click();
    	$('#whatsapp, #telegram').prop('checked', false);
	    $('#tel_type1, #address_type1').prop('checked', true);
	    $('.addressCity').next('.select2-container').find('.select2-selection').removeClass("is-invalid");
		$('#mailspan').remove();
		$('.cli').each(function (){
			$(this).val("");
			$(this).next('.select2-container').find('.select2-selection').removeClass("is-invalid");
			$(this).removeClass('is-invalid , is-valid');
	    });
	    let countryValue = document.querySelector(".addressCountry").options[0].value;
	    $('.addressCountry').val(countryValue).trigger('change');
	    let govValue = document.querySelector(".addressGov").options[0].value;
	    $('.addressGov').val(govValue).trigger('change');
	    $('.addressCity').val(null).trigger('change').removeClass('is-invalid');
		$('#btnSave').attr('disabled',false);
	}
	
	function handleErrors(datarq) {
	    let msg = "<ul class='text-left'>";
	    if (Array.isArray(datarq) && datarq[0] === "msg") {
	        if(datarq[1] && datarq[1].trim() !== ''){
	        	msg += `<li>رقم الهاتف ${datarq[1]} مسجل مسبقًا</li>`;
	        }
	        if(datarq[2] && datarq[2].trim() !== ''){
	        	msg += `<li>البريد الإلكتروني ${datarq[2]} مسجل مسبقًا</li>`;
	        }
	        	
	    } else {
	    	msg = "<li>جميع الحقول المطلوبة باللون الأحمر</li>";
	    }
	    msg += "</ul>";
	    Toast.fire({
	        position: 'top-end',
	        icon: 'error',
	        title: 'خطأ',
	        html: msg,
	        showConfirmButton: false,
	        timer: 3500
	    });
	}
	
	$('#cli_email').off("keyup").on("keyup", function() {
		validateField("#cli_email", value => {value = value.trim(); return value === '' || validateEmail($("#cli_email"));});
	});
	$('#cli_tel').off("keyup").on("keyup", function() {
		validateField("#cli_tel", checkPhoneNum, false);
	});
    $('.addressCountry').off("change.custom").on("change.custom", function() { 
        let value = $(this).val();
        if (!value) {
            $(this).val(null).trigger('change.select2'); 
        } else {
            $(this).trigger('change.select2'); 
        }
        validateField(".addressCountry", value => value !== null && value !== "null" && value.trim() !== "");
    });
    $('.addressGov').off("change.custom").on("change.custom", function() { 
        let value = $(this).val();
        if (!value) {
            $(this).val(null).trigger('change.select2'); 
        } else {
            $(this).trigger('change.select2'); 
        }
        validateField(".addressGov", value => value !== null && value !== "null" && value.trim() !== "");
    });
    $('.addressCity').off("change.custom").on("change.custom", function() { 
        let value = $(this).val();
        if (!value) {
            $(this).val(null).trigger('change.select2'); 
        } else {
            $(this).trigger('change.select2'); 
        }

        validateField(".addressCity", value => value !== null && value !== "null" && value.trim() !== "");
    });
	$('#cli_address').off("keyup").on("keyup", function() {
		validateField("#cli_address", value => value != "");
	});
	
	validateField("#cli_email", value => {value = value.trim(); return value === '' || validateEmail($("#cli_email"));});
	validateField("#cli_tel", checkPhoneNum, false);
	validateField("#cli_name", value => value.length > 2);
	validateField(".addressCountry", value => value !== null && value !== "null" && value.trim() !== "");
	validateField(".addressGov", value => value !== null && value !== "null" && value.trim() !== "");
	validateField(".addressCity", value => value !== null && value !== "null" && value.trim() !== "");
	validateField("#cli_address", value => value.trim() != "");

    if (err === 0) {
        let datarq = sendRequest(urlControl, ajaxpar);
        let elm = $("#addressCity").children(":selected");
        
        if (Array.isArray(datarq) && datarq[0] === "done") {
            Swal.fire({
                title: 'تم حفظ بيانات العميل بنجاح',
                icon: 'success',
                showCancelButton: true,
                confirmButtonText: 'إنشاء أمر بيع',
                cancelButtonText: 'العودة لتسجيل بيانات جديدة',
                preConfirm: function () {
                    return {
                        uid: datarq[1],
                        uname: $('#cli_name').val(),
                        orderby: userlogin_data['ui'],
                        oty: ordertype,
                        umail: $('#cli_mail').val(),
                        telid: datarq[3],
                        telstr: $('#cli_tel').val(),
                        addid: datarq[2],
                        addstr: `${$('#cli_address').val()} - ${elm.text()} - ${$('#addressGov').children(':selected').text()} - ${$('#addressCountry').children(':selected').text()}`,
                        shiplow: elm.attr("data-shipMin"),
                        shipmax: elm.attr("data-shipMax"),
                        shipval: elm.attr("data-shipMax"),
                        shipDays: elm.attr("data-shipTime"),
                        shipWaiting: 1,
                        cityid: elm.val(),
                        shipDate: new Date(addDays(new Date(), elm.attr("data-shipTime")))
                    };
                }
            }).then((result) => {
                if (result.value && result.isConfirmed) {
                   	$('#clisreach').val(result.value.telstr).change();
            		$('#btnSearchClient').click();
            		
                	$('#addClientform').hide();
                	
                	$('#btnCancel').click();
                	$('#btnSave, #btnCancel').hide();
                	/*localStorage.setItem('newOrder', JSON.stringify(result.value));
                    $('#addClientform').hide();
                    $('#addorderform').show();
                    $('#clientStatuse').html(
                        `<strong>اسم العميل: ${result.value.uname}</strong><br>
                        ${result.value.umail ? `البريد الإلكتروني: ${result.value.umail}<br>` : ''}
                        الهاتف: ${result.value.telstr}<br>
                        العنوان: ${result.value.addstr}`
                    );
                    $('#shippingdate').html(shippingDateStr(result.value.shipDate, result.value.shipWaiting));
                    $('#shipping_rate').html(result.value.shipmax);
                    $('#btnSave, #btnCancel').hide();*/
                    
                }
                resetForm();
            });
        } else {
            handleErrors(datarq);
        }
    } else{
    	Toast.fire({
	        position: 'top-end',
	        icon: 'error',
	        title: 'خطأ',
	        html: "جميع الحقول المحدده باللون الاحمر مطلوبه.",
	        showConfirmButton: false,
	        timer: 3500
	    });
    }
    
});
/*===================================================================================================
event click button order
=====================================================================================================*/
$(document).ready(function(){
	setTimeout(function (){
		if($('#addorderform').is(':visible')){
			$('.btn-app-pade .btnInvetPro').show();
		}else{
			$('.btn-app-pade .btnInvetPro').hide();
		}
	},350)
})
$(document).on("click",function(event){
	setTimeout(function (){
		if($('#addorderform').is(':visible')){
			$('.btn-app-pade .btnInvetPro').show();
		}else{
			$('.btn-app-pade .btnInvetPro').hide();
		}
	},50)
})
$(document).on("click","#btnAddNewOrder",async function(){
	    //$('.btn-app-pade .btnInvetPro').show();
	    $('.orderOv').hide();
	    $('.btnSave_order').attr('disabled',false);
	    let z ="",
			shipval,
			orderDate  = new Date(),
			shippDate,
			item={},
			log=[],
			cookArr={};
		if(Go === 'EditOrder'){
			cookArr = JSON.parse(localStorage.getItem('newOrder'));
			item = JSON.parse(localStorage.getItem('itemarr'));
			log = JSON.parse(localStorage.getItem('orderlog'));
		}else{
		    log = JSON.parse(localStorage.getItem('orderlog')) || [];
		    // اختيار العميل يأتي بعد تكوين السلة؛ احتفظ بأصناف الطلب كما هي.
		    item = JSON.parse(localStorage.getItem('itemarr') || '{}') || {};
			cookArr = { "uid"  :$(this).attr('data-cli_id'),
		              "orderby":userlogin_data['ui'],
		              "uname":$(this).attr('data-cli_name'),
		              "umail":$(this).attr('data-cli_mail'),
		              "oty":ordertype
		    }
		}
	    $('.telradio').each (function (){
            if($(this).is(':checked')){
                cookArr["telid"] =$(this).attr('id');
                cookArr["telstr"]=$(this). parent().children('label').text();
            }
        })
		
        $('.addradio').each (function (){
            if($(this).is(':checked')){
                cookArr["addid"]   =$(this).attr('id');
                cookArr["addstr"]  =$(this).parent().children('label').text();
				cookArr["shiplow"] =$(this).attr("data-shilow");
				cookArr["shipmax"] =$(this).attr("data-shirat");
				cookArr["cityid"]  =$(this).attr("data-cityid");
				cookArr["stoid"]   =$(this).attr("date-stoid");
				cookArr["cliComment"] ="";
				//cookArr["existis"]  =$(this).attr("data-cityid");
				
				if(localStorage.getItem('newOrder') && typeof JSON.parse(localStorage.getItem('newOrder')) =="object"){
					let b = JSON.parse(localStorage.getItem('newOrder'));
					if(typeof b['shipval'] && b['shipval'] >0 && b['shipmax'] === $(this).attr("data-shirat")){
					cookArr["shipval"] = b['shipval'];
					} else {
						cookArr["shipval"] =$(this).attr("data-shirat");
					}
					
					if(typeof b['cliComment'] != 'undefined' && b['cliComment'] !== ''){
						cookArr["cliComment"] = b['cliComment'];
					}
				} else {
					cookArr["shipval"] =$(this).attr("data-shirat");
				}
				cookArr["shipDays"]=$(this).attr("data-shitim");
				cookArr["shipWaiting"]= 1;
				shippDate = new Date(addDays(orderDate.toLocaleDateString('en-US', "yy-mm-dd"),cookArr["shipDays"]));
				
				cookArr["shipDate"]= shippDate;
            }
        })

        // رقم المخزن يؤخذ من عنوان العميل كما في التدفق الأصلي (date-stoid).

		if(cookArr["telid"] && cookArr["addid"] && cookArr["telstr"] && cookArr["addstr"] && cookArr["shiplow"] && cookArr["shipmax"] && cookArr["stoid"]){
            $('#dateNaw').html(orderDate.toLocaleDateString('ar-EG', option1));	
	        $("#editClientData").hide();
            z="<strong>اسم العميل : "+cookArr["uname"]+"</strong>";
			if(cookArr["umail"]!==""){
                z+="<br>البريد الالكتروني : "+cookArr["umail"];
            }
            z+="<br> الهاتف : "+cookArr["telstr"];
            z+="<br> العنوان : "+cookArr["addstr"];
		    localStorage.setItem('itemarr',JSON.stringify(item));
		    let d = JSON.parse(localStorage.getItem('orderlog'));
            if(d && typeof d == "object"){
                d.push('*  تم إختيار العميل : '+ cookArr["uname"] +' الهاتف : '+cookArr["telstr"]+' - العنوان : '+cookArr["addstr"]);
                localStorage.setItem('orderlog',JSON.stringify(d))
            }
            
            localStorage.setItem('newOrder',"");
            localStorage.setItem('newOrder',JSON.stringify(cookArr));
            $('#clientStatuse').html(z);
            $("#shippingdate").html(shippingDateStr(cookArr["shipDate"],cookArr["shipWaiting"]));
		    if(cookArr["cliComment"]!==""){
				$('#clientComment').html(cookArr["cliComment"]);
			}else{
				$("#clientComment").html('<span class="text-secondary">لايوجد ملاحظات حتى الان ....</span>');
			}
			$('#shipping_rate').html(cookArr["shipval"]);
		    if($("#sreachform").is(":visible")){
                $('#result').html('');
                $('#sreachform').hide();
            } else if($("#editClientData").is(":visible")){
                $('#editClientData').html(""); 
                $('#editClientData').hide(); 
            }
            $('#sreachform').hide();
	        $('#addorderform').show();
            $('#result').html('');
            $('#ClintOrdersList').hide();
            // يظل المستخدم في الخطوة الثالثة داخل معالج أمر البيع.
            if (typeof jmSetOrderWizardStep === 'function' && $('#jmStepPanel3').length) {
                jmSetOrderWizardStep(2);
                if (typeof jmShowSelectedClient === 'function') jmShowSelectedClient();
            }
        } else{
			Toast_fire(['w','من فضلك قم بإختيار الهاتف والعنوان'])
		}
	});
$(document).on("click",".editcliOrder",function(){
        const j = JSON.parse(localStorage.getItem('newOrder') || '{}');
        if (!j.uid || !j.telid || !j.addid) { Toast_fire(['w','تعذر تحديد بيانات العميل الحالية.']); return; }
        const html = getClientData(j.uid,j.telid,j.addid,j.telstr);
        $('#result').empty();
        $('#editClientData').empty().html(html);
        if (typeof jmSetOrderWizardStep === 'function' && $('#jmStepPanel1').length) {
            // نموذج التعديل موجود في المرحلة الأولى؛ لا تخفِ بيانات العميل ثم تترك المستخدم في المرحلة الثالثة.
            $('#jmClientStepSummary').prop('hidden', true).empty();
            $('#sreachform,#addClientform').hide();
            jmSetOrderWizardStep(1);
            $('#editClientData').stop(true,true).show();
            $('#jmWizardHint').text('عدّل بيانات العميل أو عنوان التوصيل ثم احفظ التغييرات.');
            $('#jmWizardNext').hide();
            $('#btnSave_order').hide();
        } else {
            $('#addorderform').fadeOut();
            $('#editClientData').fadeIn();
        }
});
$(document).on("click",".edit_shippingDate",function (){
	    let new_order = JSON.parse(localStorage.getItem('newOrder'));
	    let today = new Date();
	    let shipMinDate = new Date(addDays(today,new_order["shipDays"]));
		let shippDate = new Date(new_order["shipDate"]);
	    let shippDateformat = shippDate.toLocaleDateString("en-US", option2)
	    Swal.fire({
                    title: 'تغير تاريخ الشحن', 
                    html:'<div class="form-group"><input id="shipping_date" type="text" class="form-control shipping_date" value="" required></div><div style="height:220px"></div><div class="form-check"><label class="form-check-label" style="margin-left:30px">تسليم الشحنه فى الموعد</label><input id="shipp_Days" class="form-check-input shipp_Days" type="checkbox"></div>',
                    showCancelButton: true,
                    confirmButtonText:'تأكيد',
                    cancelButtonText: 'إلغاء',
                    didOpen: () =>{
                        datepick($('.shipping_date'),shipMinDate,new_order["shipDate"]);
                    },
                    preConfirm: function () {
						
                        let nd= new Date($('#shipping_date').val());
                        new_order["shipDate"] = nd;
                        if($('.shipp_Days').is(':checked')){
                            new_order["shipWaiting"] = 0;
                        }else{
                            new_order["shipWaiting"] = 1;
                        }
                        ulog.push('* تم تغير حالة الشحن من : '+$("#shippingdate").text() +' الى : '+shippingDateStr(new_order["shipDate"],new_order["shipWaiting"]))
                        $("#shippingdate").html(shippingDateStr(new_order["shipDate"],new_order["shipWaiting"]));
                        localStorage.setItem('orderlog',JSON.stringify(ulog));
						localStorage.setItem('newOrder',JSON.stringify(new_order));
                    }
                }).then((result) => {
                    if (result.value ==true) {
                        //console.log(new_order);
                    } else {
                       // $.removeCookie('newOrder')
                    }
                })
	    //new_order["shipmax"]=$("#swel_shippingDate").val()
	}); 
$(document).on("click",".edit_clientComment",function (){
	    let new_order = JSON.parse(localStorage.getItem('newOrder'));
	    async function swl(){
			const { value: text } = await Swal.fire({
				input: 'textarea',
				inputValue: new_order["cliComment"],
				inputLabel: 'ملاحظات',
				inputPlaceholder: 'أكتب ملاحظاتك هنا ...',
				inputAttributes: {
					'aria-label': 'أكتب ملاحظاتك هنا'
				 },
				showCancelButton: true,
				confirmButtonText:'تأكيد',
				cancelButtonText: 'إلغاء',
			})
			if (text) {
			    ulog.push('* تم اضافة ملاحظات جديد : '+text);
				new_order["cliComment"] = text;
				$("#clientComment").html(text);
				localStorage.setItem('orderlog',JSON.stringify(ulog));
				localStorage.setItem('newOrder',JSON.stringify(new_order));
			}
		}
		swl();
}); 
$(document).on("click",".del_clientComment",function (){
	let new_order = JSON.parse(localStorage.getItem('newOrder'));
	if(new_order["cliComment"]!==undefined && new_order["cliComment"]!==""){
		Swal.fire({
		  title: 'هل انت متأكد ؟',
		  text: "سيتم حذف الملاحظات ولايمكن استرجعها مراه اخرى!",
		  icon: 'warning',
		  showCancelButton: true,
		  confirmButtonColor: '#3085d6',
		  cancelButtonColor: '#d33',
		  confirmButtonText: 'نعم ، احذفها!',
		  cancelButtonText: 'لا تقم بحذفها',
		  preConfirm: function(){
		      if(new_order["cliComment"]){
		        ulog.push('*  تم حذف الملاحظات التالية : ' + new_order["cliComment"]);
		        localStorage.setItem('orderlog',JSON.stringify(ulog));
		      }
			  new_order["cliComment"] ="";
			  
		  }
		}).then((result) => {
		  if (result.isConfirmed && new_order["cliComment"]==="") {
			Toast_fire(["s","تم الحذف بنجاح"]); 
			$("#clientComment").html('<span class="text-secondary">لايوجد ملاحظات حتى الان ....</span>');
			localStorage.setItem('newOrder',JSON.stringify(new_order));
		  } else {
			 //Toast_fire(["e","حدث حظاء: غير قادر على حذف الملاحظات"]);
		  }
		})
	}else{
		Toast_fire(["q","لم تقم بإدخال ملاحظات حتى الان لنقوم بحذفها"]);
	}
})
$(document).on("click","#addPorduct",function (){
    let id   = $(this).attr('data-id');
    let code = $(this).attr('data-code');
	// استدعاء الوظيفة
	handleAddProduct(id,code);
});
$(document).on('click', '.jm-delete-cart-product', function(){
    const code = String($(this).data('code') || '');
    const items = JSON.parse(localStorage.getItem('itemarr') || '{}') || {};
    const logs = JSON.parse(localStorage.getItem('orderlog') || '[]') || [];
    const order = JSON.parse(localStorage.getItem('newOrder') || '{}') || {};
    if (!items[code]) return;
    logs.push('* تم حذف المنتج ' + (items[code].PRO_NAME || code) + ' - الكمية: ' + items[code].qty);
    delete items[code];
    localStorage.setItem('itemarr', JSON.stringify(items));
    localStorage.setItem('orderlog', JSON.stringify(logs));
    $('#itemorder').html(renderCartItems(items, order));
    if (typeof updateStorefrontCartCount === 'function') updateStorefrontCartCount();
    if (typeof jmRenderClientReview === 'function' && typeof jmHasSelectedClient === 'function' && jmHasSelectedClient()) jmRenderClientReview();
});
$(document).on("click","#btnProDele",function (){
    const code = $(this).attr('data-code');
    const items = JSON.parse(localStorage.getItem('itemarr') || '{}') || {};
    const logs = JSON.parse(localStorage.getItem('orderlog') || '[]') || [];
    const order = JSON.parse(localStorage.getItem('newOrder') || '{}') || {};
    if (!items[code]) return;
    logs.push('* تم حذف المنتج ' + items[code]['PRO_NAME'] + ' ' + Object.values(items[code]['OPTION_TEXT'] || {}).toString() + ' - الكمية: ' + items[code]['qty'] + ' - الخصم: ' + items[code]['dis']);
    delete items[code];
    localStorage.setItem('itemarr', JSON.stringify(items));
    localStorage.setItem('orderlog', JSON.stringify(logs));
    if (typeof renderCartItems === 'function') $('#itemorder').html(renderCartItems(items, order));
    if (typeof updateStorefrontCartCount === 'function') updateStorefrontCartCount();
    if (typeof jmRenderClientReview === 'function' && typeof jmHasSelectedClient === 'function' && jmHasSelectedClient()) jmRenderClientReview();
});
$(document).on("click","#edit_shipping",function (){
    const order = JSON.parse(localStorage.getItem('newOrder') || '{}') || {};
    const min = Number(order.shiplow);
    const max = Number(order.shipmax);
    const current = Number(order.shipval) || 0;
    if (!Number.isFinite(min) || !Number.isFinite(max) || max < min) {
        Toast_fire(["e","تعذر تحديد الحد الأدنى والأقصى لقيمة الشحن من عنوان العميل."]);
        return;
    }
    Swal.fire({
        title: 'تعديل قيمة الشحن',
        html: '<div class="form-group text-right"><label for="editshipval">قيمة الشحن</label><input id="editshipval" type="number" step="0.01" min="'+min+'" max="'+max+'" value="'+current+'" class="form-control" dir="ltr"><small class="form-text text-muted">القيمة المسموح بها من '+min+' إلى '+max+'</small></div>',
        showCancelButton: true,
        confirmButtonText: 'تأكيد',
        cancelButtonText: 'إلغاء',
        focusConfirm: false,
        preConfirm: function () {
            const value = Number($('#editshipval').val());
            if (!Number.isFinite(value) || value < min || value > max) {
                Swal.showValidationMessage('قيمة الشحن يجب أن تكون بين '+min+' و'+max);
                return false;
            }
            return value;
        }
    }).then((result) => {
        if (!result.isConfirmed) return;
        const newValue = Number(result.value);
        const oldValue = Number(order.shipval) || 0;
        const productsTotal = Number(String($('#orderprice').text()).replace(/[^0-9.\-]/g,'')) || 0;
        ulog.push('* تم تغيير قيمة الشحن من '+oldValue.toFixed(2)+' إلى '+newValue.toFixed(2));
        order.shipval = newValue;
        $('#shipping_rate').text(newValue.toFixed(2));
        $('#ordertotal').text((productsTotal + newValue).toFixed(2));
        localStorage.setItem('orderlog', JSON.stringify(ulog));
        localStorage.setItem('newOrder', JSON.stringify(order));
        if (typeof jmRenderClientReview === 'function') jmRenderClientReview();
        Toast_fire(["s","تم تحديث قيمة الشحن"]);
    });
});
$(document).on("click","#btnCancel_order",function (){
    localStorage.removeItem('newOrder');
    localStorage.removeItem('itemarr');
	localStorage.removeItem('swlitem');
	localStorage.removeItem('orderlog');
	localStorage.removeItem('editON');
	
	if(Go === 'NewOrder'){
		$('.btn-app-pade .btnInvetPro').hide();
		$(".alert-box").hide();
		$('#btnSave').hide();
	    $('#btnEdit').hide();
	    $('#btnCancel').hide();
	    $("#btnNew").hide();
	    $('#btnDelete').hide();
	    $('#addClientform').hide();
	    $('#addorderform').fadeOut();
	    $('#sreachform').fadeIn();
	    $(this).attr('disabled',false);
	    $('.item').remove();
	    $('#orderprice').text('0.00');
		$('#clientComment').html('<span class="text-secondary">لايوجد ملاحظات حتى الان ....</span>');
	    $('#ordertotal').html('--');
		$("body").scrollTop();
		$("#clisreach").val('');
		Toast_fire(["w","تم الغاء امر البيع ولا يمكن العوده له مره اخرى"]);
		
		javascript:history.back();
	} else {
		localStorage.setItem('viewOrderNum',ordernum);
		javascript:history.back();
	}
    
})
// إعادة شاشة إنشاء أمر البيع إلى حالة جديدة بعد الحفظ أو حذف المسودة.
function jmResetNewOrderScreen(message) {
    localStorage.removeItem('newOrder');
    localStorage.removeItem('itemarr');
    localStorage.removeItem('swlitem');
    localStorage.removeItem('editON');
    localStorage.setItem('orderlog', '[]');
    if (typeof jmInitializeNewOrderDraft === 'function') jmInitializeNewOrderDraft();
    $('#sreachform,#addorderform').show();
    $('#addClientform,#editClientData').hide();
    $('#clientStatuse').empty();
    $('#jmClientStepSummary,#jmClientReview').empty().hide();
    $('#jmStepPanel3 .invoice-info').hide();
    $('#itemorder').html('<div class="jm-cart-empty-inline"><i class="fas fa-basket-shopping"></i><strong>السلة فارغة</strong><span>أضف المنتجات بعد اختيار العميل.</span></div>');
    $('.item').remove();
    $('#orderprice').text('0.00');
    $('#shipping_rate').text('');
    $('#shippingdate').empty();
    $('#clientComment').html('<span class="text-secondary">لايوجد ملاحظات حتى الان ....</span>');
    $('#ordertotal').text('--');
    $('#clisreach').val('').focus();
    $('#btnSave_order').hide().prop('disabled', false);
    $('.orderOv').hide();
    if (typeof jmSetOrderWizardStep === 'function' && $('#jmStepPanel1').length) jmSetOrderWizardStep(1);
    if (typeof updateStorefrontCartCount === 'function') updateStorefrontCartCount();
    if (message && typeof Toast_fire === 'function') Toast_fire(['s', message]);
}

$(document).on("click","#btnSave_order",function (){
    $(this).attr('disabled',true); // ايقاف عمل زر الحفظ
    $('.orderOv').fadeIn(100); // اظها ايكونة جارة الحفظ
    
    //جلب بيانات امر اليع من التخزين المحلى
    let order = JSON.parse(localStorage.getItem('newOrder') ? localStorage.getItem('newOrder') : '{}'),// جلب بيانات امر البيع
		item  = JSON.parse(localStorage.getItem('itemarr') ? localStorage.getItem('itemarr') : '{}'), // جلب بيانات منتجات امر البيع
		olog  = JSON.parse(localStorage.getItem('orderlog') ? localStorage.getItem('orderlog') : '[]'),// جلب بيانات حركة التعديل على امر البيع
		pt = localStorage.getItem('pt') ? localStorage.getItem('pt') : null,
		ex = $(this).attr('data-exid'),
	    oid = order['order_id'];

        if (Go === 'NewOrder' && !(order.uid && order.telid && order.addid && order.stoid)) {
            if (typeof Toast_fire === 'function') Toast_fire(['w', 'اختر العميل ورقم الهاتف وعنوان الشحن قبل حفظ أمر البيع']);
            $(this).prop('disabled', false);
            $('.orderOv').hide();
            return;
        }
		$(this).addClass('save');
		if(ex !='sav' && ex !='ser'){
            order['exists_id']=$(this).attr('data-exid');
		//    olog.push("* تم تحويل امر البيع الى - "+$(this).text());
		    localStorage.removeItem('editON');
		}
		
		if(ordertype != 1 && order['print'] ==1 && Go === 'EditOrder' && olog.length > 2){
		    order['print']=0;
		    olog.push("* تم الغاء طباعة الملصق القديم نظرا للتعديلات السابقة");
		}
	//------- وظيفة حفظ امر البيع -------------------
	setTimeout(()=>{
    	const myPromise = new Promise((res,rej)=>{ 
    		let rqd; 
	       	const params={
	        	'CONTROL':'',
	        	'order':order,
	        	'item':item,
	        	'olog':olog,
	    		'sttye':ordertype,
	    		'ptype':pt
	       	};
	       	if(Go === 'NewOrder'){
				params.CONTROL = 'addOrder';
				if($(this).attr('data-vid') == 'saveAndReceived'){
					order['exist'] = 11;
					params.order = order;
				}
	   	   	} else if(Go === 'EditOrder'){
		    	params.CONTROL = 'updateOrder';
		    }
		    
		    if( Object.keys(item).length < 1) {
	    		rqd = ['e','لايمكن حفظ امر البيع بدون منتجات'];
			}else if (Go === 'EditOrder' && typeof olog =='object' && olog.length < 2){
		    	rqd = ['e','لايوجد اى تغييرات على امر البيع لحفظها'];
		    } else {
	    		rqd =  sendRequest(urlControl,params,false);
	        }
	        res(rqd);
		}).then((res)=>{
    	    if(res[0] == 's'){
    			localStorage.removeItem('newOrder');//حذف بيانات امر البيع من التخزين المحلى
    			localStorage.removeItem('itemarr'); //حذف بيانات المنتجات من التخزين المحلى
    			localStorage.removeItem('swlitem'); //حذف بيانات المنتجات المؤقت من التخزين المحلى
    			localStorage.removeItem('orderlog');//حذف بيانات العديل على امر البيع من التحزين المحلى
    			if(Go == 'NewOrder'){
    				const swalObj = {
    					title: 'تم الحفظ',
    					html: res[1],
    					icon: 'success',
    					showCancelButton: true,
    					confirmButtonText:  'انشاء امر جديد',
    					cancelButtonText: 'عرض قائمة مبيعاتى',
    				};
    				if(ordertype === 1){
    					swalObj.showCancelButton = false;
    				}
    				Swal.fire(swalObj).then((result) => {
    					if (result.isConfirmed) {
                                $('#btnSave').hide();
                            $('#btnEdit').hide();
                            $('#btnCancel').hide();
                            $('#btnNew').hide();
                            $('#btnDelete').hide();
                            jmResetNewOrderScreen('تم حفظ أمر البيع، يمكنك الآن إنشاء أمر جديد');
                            $('body').scrollTop(0);
                            Toast_fire(res);
    						//$('.orderOv').fadeOut(1000);
    					} else {
    						//$('.orderOv').fadeOut(1000);
    						if(ordertype == 2){
    							window.location.href = '?Go=ListOrdersMarketer';
    						}else if(ordertype == 3){
    							window.location.href = '?ty='+ordertype+'&ex=0&Go=Orderlist';
    						}else if(ordertype == 1){
    							window.location.href = '?ty='+ordertype+'&ex=0&Go=Orderlist';
    						}
    					}
    				});
    			} else {
        			async function savres(){
        			    if(ex == 'ser'){
        			        await openTiketSupport(oid);
        			    }
            			await Swal.fire({
            				title: 'تم حفظ التعديلات بنجاح!',
            				icon: 'success',
            				html: 'جارى الان الرجوع للصفحه السابقة',
            				timer: 3000,
            				timerProgressBar: true,
            				didOpen: () => {
            					Swal.showLoading()
            					const b = Swal.getHtmlContainer().querySelector('b')
            					timerInterval = setInterval(() => {
            					  b.textContent = Swal.getTimerLeft()
            					}, 3000)
            				},
            				willClose: () => {
            					clearInterval(timerInterval);
            				}
            			}).then((result) => {
            				localStorage.setItem('viewOrderNum',ordernum);
            				javascript:history.back();
            			})
        			}
        			savres();
    			}
    			
    		} else {
    		    /*$(".tblitemcode").each(function (){
    		        if(olog.length>1){
    					for(var i=0; i < rq[2].length; i++){
    						if($(this).text()==rq[2][i]){
    							$(this).parent('tr').addClass("text-danger");
    						}
    					}
    				}
    		    });*/
    		    Toast_fire(res);
    			$('.orderOv').fadeOut();
    			$('.save').attr('disabled',false);
    		}
    		$(this).attr('disabled',false);
		}).catch((err) => {
		    console.error("خطأ:", err);
		});
	},300)
})
/*===================================================================================================
check order in relode page
=====================================================================================================*/
const newOrder = JSON.parse(localStorage.getItem('newOrder'));
	
if( Go ==="NewOrder" && 
	newOrder !== null && 
	newOrder !== "" && 
	(userlogin_data['admin']==1 || 
	(userlogin_data['affiliate'] == 1 && ordertype==2 && newOrder['oty'] ==2 ) || 
	(userlogin_data['intermediary']==1 && ordertype==4 && newOrder['oty'] ==2 ) || 
	(userlogin_data['merchat']==1 && ordertype==4 && newOrder['oty'] ==2 ))){
	$('.orderOv').hide();
	let orderDate,shipDateStr,new_order,item;
	//Date of creation of the sale order
	orderDate = new Date();
	//convert cookie from string to array
	item = JSON.parse(localStorage.getItem('itemarr'));
	new_order = JSON.parse(localStorage.getItem('newOrder'));
	//Verify the type of data and make sure that the variables exist
	const draftItems = (item && typeof item === 'object') ? Object.keys(item) : [];
	const hasUnsavedDraft = !!(new_order && (
		(new_order.uid && new_order.uid !== '') ||
		(new_order.telid && new_order.telid !== '') ||
		(new_order.addid && new_order.addid !== '') ||
		draftItems.length > 0
	));
	if(new_order && typeof new_order === 'object' &&
		!new_order['order_id'] &&
		new_order["orderby"] === userlogin_data['ui'] &&
		hasUnsavedDraft){
			
		function confirm(ex = false){
			localStorage.removeItem('swlitem');
			if(ex){
				$("#orderHeadr").html('انشاء امر استبدال');
			}
			let xhtm = addProductToCart();
				new_order["shipDate"]= String(new Date(addDays(orderDate,new_order["shipDays"])));
			    $("#addPorduct").before(xhtm);
				$('#sreachform').hide();
				$('#addorderform').show();
				$('#dateNaw').html(String(orderDate.toLocaleDateString('ar-EG', option1)));	
				z ="<strong> اسم العميل : "+new_order["uname"]+"</strong>";
				if(new_order["umail"]!==""){
					z+="<br>البريد الإلكتروني"+new_order["umail"];
				}
				z+="<br>الهاتف : "+new_order["telstr"];
				z+="<br>العنوان : "+new_order["addstr"];
				$('#clientStatuse').html(z);
				$("#shippingdate").html(shippingDateStr(new_order["shipDate"],new_order["shipWaiting"]));
				if(new_order["cliComment"]!==""){
					$('#clientComment').html(new_order["cliComment"]);
				}else{
					$("#clientComment").html('<span class="text-secondary">لايوجد ملاحظات حتى الان ....</span>');
				}
				$('#shipping_rate').html(new_order["shipval"]);
		}	
		if(Exists && new_order["orderExchange"]){
			confirm(true);
		}else if(!new_order["orderExchange"]){
			Swal.fire({
				title: 'إنتبة',
				text: "هناك امر بيع لم يتم حفظة حتى الان - هل تريد استكمال امر البيع ؟",
				icon: 'info',
				showCancelButton: true,
				confirmButtonText:  'متابعة',
				cancelButtonText: 'حذف والبداء من جديد',
			}).then((result) => {
                if (result.isConfirmed || result.value) {
                    confirm();
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    jmResetNewOrderScreen('تم حذف المسودة، ابدأ إنشاء أمر بيع جديد');
                }
            })
		}else{
			localStorage.removeItem('newOrder');
			localStorage.removeItem('itemarr');
			localStorage.removeItem('swlitem');
			localStorage.setItem('orderlog','['+ordernum+']');
		}
	}
} else if( 	
			Go ==="EditOrder" && 
			(
				(userlogin_data['admin']     == 1) || 
				(userlogin_data['delivery']  == 1) ||
				(userlogin_data['affiliate'] == 1 && ordertype==2) || 
				(userlogin_data['intermediary']  == 1 && ordertype==4) || 
				(userlogin_data['merchat']==1 && ordertype==4)
			) &&
			ordernum 
		){
			
			let shipDateStr,new_order,item;
			new_order = JSON.parse(localStorage.getItem('newOrder'));
			
			
			if(	(new_order && new_order['order_id'] && ordernum == new_order['order_id'] && userlogin_data['ui'] == new_order['orderby'] && ordertype != 1 && ordertype == new_order['oty']) || 
				(new_order && ordertype == 1 && new_order['order_id'] && ordernum == new_order['order_id']) || 
				(ptype == 3)
			){
				
				//Date of creation of the sale order
				//convert cookie string to array
				item = JSON.parse(localStorage.getItem('itemarr'));
				
				orderDate = new Date(new_order['orderDate']);
				localStorage.removeItem('swlitem');
				let xhtm = addPorductcook();
				new_order["shipDate"] = String(new Date(addDays(new_order["shipDate"],new_order["shipDays"])));
				$(document).ready(function(){
					$("#orderHeadr").html('تعديل امر بيع رقم : '+new_order["sub_id"]);
					$("#itemorder").html(xhtm);
					$('#sreachform').hide();
					$('#addorderform').show();
					$('#dateNaw').html(String(orderDate.toLocaleDateString('ar-EG', option1)));	
					z ="<strong> اسم العميل : "+new_order["uname"]+"</strong>";
					if(new_order["umail"]!==""){
						z+="<br>البريد الإلكتروني"+new_order["umail"];
					}
					z+="<br>الهاتف : "+new_order["telstr"];
					z+="<br>العنوان : "+new_order["addstr"];
					$('#clientStatuse').html(z);
					$("#shippingdate").html(shippingDateStr(new_order["shipDate"],new_order["shipWaiting"]));
					if(new_order["cliComment"]!==""){
						$('#clientComment').html(new_order["cliComment"]);
					}else{
						$("#clientComment").html('<span class="text-secondary">لايوجد ملاحظات حتى الان ....</span>');
					}
					$('#shipping_rate').html(new_order["shipval"]);
			})
			} else {
				$('#sreachform').hide();
				let timerInterval;
				Swal.fire({
				  title: 'انت لا تملك صلاحية الوصول !',
				  html: 'الوصول لهذا العنوان جارى الان الرجوع الى لوحة التحكم',
				  timer: 3000,
				  timerProgressBar: true,
				  didOpen: () => {
					Swal.showLoading()
					const b = Swal.getHtmlContainer().querySelector('b')
					timerInterval = setInterval(() => {
					  b.textContent = Swal.getTimerLeft()
					}, 2000)
				  },
				  willClose: () => {
					clearInterval(timerInterval)
				  }
				}).then((result) => {
					localStorage.removeItem('newOrder');
					localStorage.removeItem('itemarr');
					localStorage.removeItem('swlitem');
					localStorage.setItem('orderlog','['+ordernum+']');
					window.location.assign("?");
				})
			}
				
							
					//alert('edit order');
}else{
	localStorage.removeItem('newOrder');
	localStorage.removeItem('itemarr');
	localStorage.removeItem('swlitem');
	localStorage.setItem('orderlog','['+ordernum+']');
}
