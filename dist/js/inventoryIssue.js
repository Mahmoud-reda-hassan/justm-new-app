const urlinv	= `../../inc/controller/inventory.control.php`,
urlship = "../../inc/controller/shipment.control.php",
inventoriesData =  sendRequestGET(`${urlApi}?CONTROL=getdropWarehouse`);
var row,
formCategory = false,
formProduct = false,
orderIssueType = '',
orderIssueStoId ='',
orderLastUpdate= '1900-01-01',
table = $('#table').DataTable(),
title = '';
// جلب بيانات المخازن
async function getInventoryData(orderType) {
    const controller = new AbortController(); // لإنشاء كائن التحكم في الإلغاء
    const signal = controller.signal;
    orderType = orderType == 2 ? orderType : 1;
    // تحديد المهلة الزمنية (5 ثوانٍ)
    const timeout = setTimeout(() => controller.abort(), 25000);

    try {
        let response = await fetch(`${urlApi}?CONTROL=getdropWarehouse&type=${orderType}`, { signal });
        clearTimeout(timeout); // إيقاف المهلة إذا وصل الرد في الوقت المحدد
        if (!response.ok) throw new Error("خطأ في جلب البيانات");
        let stores = await response.json();
        displayStores(stores, 'SelectInventory');
    } catch (error) {
        if (error.name === "AbortError") {
            //console.warn("تم إلغاء الطلب بسبب التأخير");
            Swal.fire({
	            icon: 'error',
	            title: 'خطأ في الاتصال',
	            text: 'انتهى الوقت استغرق الخادم وقت طويل فى الرد . يرجى المحاولة لاحقًا.',
	        });
	    } else {
            console.error("خطأ:", error);
        }
    }
}
// دالة عرض البيانات في المخازن فى الشاشة
function displayStores(stores,elementID) {
    const container = document.getElementById(elementID);
	$('#'+elementID+' .inv-select').remove();
	$('.btn-app-pade .btnBack').show();
	showSection(elementID)
	//$('#SelectOrderType').hide();
	//انشاء عرض المخازن
	Object.keys(stores).forEach((key, index) => {
		const [id, nun, name] = stores[key];
		// توليد اسم الكلاس ديناميكيًا
		const classNumber = (id % 10) || 10;
		const className = `warehouse-${classNumber}`;
		// إنشاء عنصر المستودع
		const warehouseDiv = document.createElement("div");
		warehouseDiv.id = id;
		warehouseDiv.className = "col-md-3 col-sm-6 mb-4 p-1 text-center inv-select";
		warehouseDiv.innerHTML = `<div class="warehouse-card ${className}">
									<i class="fas fa-warehouse"></i>
									${name}
								  </div>`;
		if (index % 4 === 0) {
			row = document.createElement("div");
			row.className = "row";
			container.appendChild(row);
		}
		// إضافة عنصر المستودع إلى الصف الحالي
		row.appendChild(warehouseDiv);
	});
	// اختيار المخزن
	$('.inv-select').click(function() {
		orderIssueStoId = $(this).attr('id');
		$('#SelectInventory').hide();
		$('#tableList').show();
		if(orderIssueType == 3){
			ScrapInvintory();
		}else if(orderIssueType == 2){
			// return orders list
			DirectSale();
		}else if(orderIssueType == 1){
			// return Purchase List and add Return Purchase Order
			ReturnPurchase();
		}
	})
}
// اخفاء كل الاقسام واظهار القسم المطلوب
function showSection(id) {
	$('.form-section').hide();
	$('#' + id).fadeIn();
}
// التراجع عن اختيار النوع
function btnBack () {
	if ($('#SelectInventory').is(':visible')) {
		$('.btn-app-pade .btnBack').hide();
		$('.form-section').hide();
		
		$('#SelectOrderType').show();
		$('#SelectInventory .inv-select').remove();
	}else if($('#tableList').is(':visible')){
		$('.form-section').hide();
		$('#SelectInventory').show();
	}else if($('#tblScrapPro').is(':visible')){
		$('#SelectInventory').show();
		$('#tblScrapPro').hide();
	}
}
// عرض منتجات الارجاع فى الجدول
async function addReturnPurchase(d) {
		
	 	let SUPPLIED = [];
	 	let i = 0;
		for(let k in d['PRODUCT_DETAILS']){
			const pd = d['PRODUCT_DETAILS'][k];
	
			if((pd.QTY_SUPPLIED - pd.QTY_RETURN) > 0){
				pd.RETURN_QTY = 0;
				SUPPLIED.push(pd);
				i++;
			};
		};
		if(Object.keys(SUPPLIED).length<=0){
			Toast_fire(['w','هذا الامر تم صرف مرتجعات الامر بالكامل مسبقاً']);
			return false;
		}
		let textClass = 'danger';
		let textClass1 = 'danger';
		if (d['SUPPLIED_STATUSE'] === 'مكتمل') {
			textClass = 'success';
		} else if (d['SUPPLIED_STATUSE'] === 'جزئى') {
			textClass = 'warning';
		}
		if (d['PAYMENT_STATUSE'] === 'مدفوع') {
			textClass1 = 'success';
		}
		$('.invoice-info').html('');
		$('.invoice-info').html(`
		<div id="orderData" class="col-sm-6 invoice-col">
					<address>
						<a id="obyuname" data-suppid ="${d['SUPPLIER_ID']}"><strong>المورد : </strong>${d['SUP_NAME']}</a>
						<br>
						<a id="ouphone"><strong>الهاتف : </strong>${d['TEL_NUMBER']}</a>
						<br>
						<a id="ouemail"><strong>البريد الالكتروني : </strong>${d['SUP_EMAIL']}</a>
						<br>
						<a id="odate"><strong>تاريخ الانشاء : </strong>${d['CREATE_AT']}</a>
						<br>
						<a id="ostatuse" class="text-${textClass}"><strong>الاستلام : </strong>${d['SUPPLIED_STATUSE']}</a>
						<br>
						<a id="ostatuse" class="text-${textClass1}"><strong>السـداد : </strong>${d['PAYMENT_STATUSE']}</a>
					</address>
				</div>
				<div class="col-sm-6 invoice-col">
					<address>
						<a id="ouaddress" class="mt-0 pt-0"><strong>ملاحظات : </strong>
							<button id="AddComment" class="btn btn-app position-relative AddComment" style="right:67%; margin-top:0; min-width: 30px; height: 30px !important;">
								<i class="fa fa-plus"></i>
							</button>
							<br>
							<div class="row border border-light col-sm-12 p-0 m-0 h-100">
								<div class="text-nowrap border badge-gray5 m-0 p-0 border-light bg-ligh overflow-auto w-100" style="height:95px;">
									<ul class="actionOrderloop text-left m-1 p-1">
										${d['NOTE']} 
									</ul>
								</div>
							</div>
						</a>
					</address>
				</div>`);
		// btnBack
		$('.PurchaseList ,.btnBack').hide();
	 	$('.ReceiveOrderPurchase ,.btnCancel, .btnSave').show();
	 	showSection('ReceiveOrderPurchase');
	 	tableROPU.clear();              		 // حذف جميع البيانات الحالية
	    tableROPU.rows.add(SUPPLIED);			 // إضافة البيانات الجديدة
	    tableROPU.draw();
	 }
// اذن صرف هالك
function ScrapInvintory(){
	
	$('#btnSave').show()
	$('.btn-app-pade #btnSave').attr('onclick',`saveScrap()`);
	
	
	if ($.fn.DataTable.isDataTable('#table')) {
		$('#table').DataTable().destroy();
		$('#tableList .table-container #table').remove();
		$('#tableList .table-container').append(`<table id="table" class="table table-hover  table-striped text-nowrap w-100 col-sm-120 p-0 m-0"></table>`);
		$('#tableList').hide();
		$('#tblScrapPro').show();
	}
	$('#table').DataTable({
	    	dom: '<"wrapper"t>',
	    	processing: true,
		    serverSide: false,
		    columns: [
		        { data: "0" },
		        { data: "1" },
		        { data: "2" },
		        { data: "3" }
		    ],
		    buttons: [],
	});
	
}

// عرض اوامر البيع المباشر
function DirectSale(){
	if ($.fn.DataTable.isDataTable('#table')) {
		$('#table').DataTable().destroy();
		$('#tableList .table-container #table').remove();
		$('#tableList .table-container').append(`<table id="table" class="table table-hover  table-striped text-nowrap w-100 col-sm-120 p-0 m-0"></table>`);
	}
	table = $('#table').DataTable({
	        scrollY : false,
	        scrollX : true,
	        ajax: {
	        	url:`../../inc/controller/viewOrders.control.php`,
	        	type:'POST',
	        	data:{'CONTROL':'DirectSale',
						'ptype':ptype,
						'st':ost,
						'url':'' 
	        	}
	        },
	        processing: true,
	        serverSide: true,
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
		                		/*return [
									'oid'       => $use == true ? $orderData["ORDER_ID"] : "",
									'oclass'    => $use == true ? "text-primary border" : "text-secondary border",
									'available' => $use == true ? "" : "disabled",
									'elid'      => 'btnOview',
									'icon'      => 'fa-solid fa-eye',
									'msgnotification' => ""
								];*/
							let adminsupport = "",
								msgnotification = "",
								clasRowSpan = "",
								return_BTNs = "";
							if((row['OSid'] > 0 && row['OSStatu'] == 0) || row['OSMsgc'] > 0){
								if(row['OSMsgc']>0){
									msgnotification = row['OSMsgc'];
								}else {
									msgnotification = '<i class="fa-regular fa-envelope"></i>';
								}
							}
					        
							if(ptype == 1){ 
					            adminsupport =`<td class="bg-white " colspan="2" rowspan="2" style="display:none">
							                            <a id="AdminSupport" data-id="${row['oid']}" class="btn btn-app-tools btn-light badge-ya text-5 bg-gradient mt-2" style="margin-left:-5px;margin-right:-5px;width:50px;">
							                                <span class="badge bg-danger">${msgnotification}</span>
							                                <i class="fas fa-headset"></i>الدعم
							                            </a>
							                    	</td>`;
					    	}
							if( row['BUTTONS'][0][2] == "" && row['BUTTONS'][0][3] == "" && (row['BUTTONS'][0][1] != "" || row['BUTTONS'][0][0] == "" ) ||
					        	row['BUTTONS'][0][0] == "" && row['BUTTONS'][0][1] == "" && (row['BUTTONS'][0][3] != "" || row['BUTTONS'][0][2] == "" ) 
					        ){
					        	clasRowSpan = 'rowspan="2"';
					        }
					        return_BTNs = `
					        	<table class="table OLCBtn table-borderless" >
					        		<tbody class="badge card-body mt-2 ml-2 mt-r tableAllBtn" style="font-size:1.5rem;">
					                    <tr class="p-1">
						                    ${adminsupport}
						                    <td class="p-1 badge-light btnTR" ${clasRowSpan}>
							                    <button id="${row['BUTTONS'][0][0]['elid']}" data-id="${row['BUTTONS'][0][0]['oid']}" class="btn btn-app-tools btn-xs ${row['BUTTONS'][0][0]['elid']} ovbtn w-100 h-100 ${row['BUTTONS'][0][0]['oclass']}">
											            <i class="${row['BUTTONS'][0][0]['icon']}"></i>
											            <a style="font-size:14px; font-weight: bold;">${row['BUTTONS'][0][0]['name']}</a>
											    </button>
							                </td>`;
					        if(row['BUTTONS'][0][1] != ""){
						        return_BTNs += `<td class="p-1 badge-light btnTL " ${clasRowSpan}>
						        					<button id="${row['BUTTONS'][0][1]['elid']}" data-id="${row['BUTTONS'][0][1]['oid']}" class="btn btn-app-tools btn-xs ${row['BUTTONS'][0][1]['elid']} ovbtn w-100 h-100 ${row['BUTTONS'][0][1]['oclass']}">
											            <i class="${row['BUTTONS'][0][1]['icon']}"></i>
											            <a style="font-size:14px; font-weight: bold;">${row['BUTTONS'][0][1]['name']}</a>
											        </button>
						        				</td>`;
					        }
							return_BTNs += `</tr><tr class="p-1">`;
					        if(row['BUTTONS'][0][2] != ""){
						    	return_BTNs += `<td class="p-1 badge-light btnBR " ${clasRowSpan}>
						    						<button id="${row['BUTTONS'][0][2]['elid']}" data-id="${row['BUTTONS'][0][2]['oid']}" class="btn btn-app-tools btn-xs ${row['BUTTONS'][0][2]['elid']} ovbtn w-100 h-100 ${row['BUTTONS'][0][2]['oclass']}">
											         	<span class="badge bg-danger spantimer">${adminsupport != "" ? "" : msgnotification}</span>
											            <i class="${row['BUTTONS'][0][2]['icon']}"></i>
											            <a style="font-size:14px; font-weight: bold;">${row['BUTTONS'][0][2]['name']}</a>
											         </button>
						    					</td>`;
					        }
					        if(row['BUTTONS'][0][3] != ""){
								 return_BTNs += `<td class="p-1 badge-light btnBL" ${clasRowSpan}>
								 					<button id="${row['BUTTONS'][0][3]['elid']}" data-id="${row['BUTTONS'][0][3]['oid']}" class="btn btn-app-tools btn-xs ${row['BUTTONS'][0][3]['elid']} ovbtn w-100 h-100 ${row['BUTTONS'][0][3]['oclass']}">
											            <span class="badge bg-danger spantimer"></span>
											            <i class="${row['BUTTONS'][0][3]['icon']}"></i>
											            <a style="font-size:14px; font-weight: bold;">${row['BUTTONS'][0][3]['name']}</a>
											         </button>
								 				</td>`;
							}
						         return_BTNs += `</tr>
						                     </tbody>
						                     </table>`;
		                     
		                	 return return_BTNs;
		                }
		            },
		            {data:'SHIPPING_DATE', visible:false, searchable: true}, //5
					{data:'DELIVERED_DATE', visible:false, searchable: true},//6
					{data:'DATE_CREATED', visible:false, searchable: true},//7
					{data:'DELIVERY_DATE', visible:false, searchable: true},//8
					{data:'Zid', visible:false, searchable: true},
					{data:'SHIPPING_TYPE', visible:false, searchable: true},
					{data:'ORDER_TYPE_ID', visible:false, searchable: true},
					//{data:'PRODUCT_IDS', visible:false, searchable: true},
					
		        ],
		       // fixedHeader: true, // تثبيت الهيدر أثناء التمرير
			    columnDefs: [
			        { targets: '_all', className: 'text-center' }
			    ],
			    initComplete: function () {
			        this.api().columns.adjust(); // ضبط الأعمدة بعد تحميل البيانات
			    },
	    
		        buttons: [
			        {
			            text: 'تحديث البيانات',
			            action: function ( e, dt, node, config ) {
			                $('.table-overlay1').fadeIn(200); 
			                table.ajax.reload();
			            }
			        }
			    ],
	        paging: {
	            pageLength: 50,
	        },
	                    
	}).on('draw', function() {
		table.columns.adjust();
	});
	
}
// عرض اومر الشراء
function ReturnPurchase(){
		
		if ($.fn.DataTable.isDataTable('#table')) {
			$('#table').DataTable().destroy();
			$('#tableList .table-container #table').remove();
			$('#tableList .table-container').append(`<table id="table" class="table table-hover  table-striped text-nowrap w-100 col-sm-120 p-0 m-0"></table>`);
		}
		table = $('#table').DataTable({
		    ajax: {
		        url : `../../inc/controller/purchases.control.php`,
		        data: {'CONTROL':'listPurchasesDT', 'tab':'OLReturnPurchase'},
		        type: 'POST'
		    },
		    autoWidth: true, // تعيين العرض التلقائي إلى false
		    scrollX: true, // تمكين التمرير الأفقي
		    scrollY: '600px', // تحديد ارتفاع الجدول
		    scrollCollapse: true, // تمكين تقليص الجدول عند الحاجة
		    processing: true,
		    serverSide: true,
		    columns: [
		        
		        {
		            data: 'ORDER_ID',
		            orderable: true,
		            title: 'الامر',
		            className: 'text-center text-nowrap'
		        },
		        {
		            data: 'SUP_NAME',
		            orderable: true,
		            title: 'المورد',
		            className: 'text-center text-nowrap'
		        },
		        {
		            orderable: false,
		            render: (data, type, row, meta) => {
		               
		                let payStColor = 'danger', suppStColor = payStColor;
		                if (row['PAYMENT_STATUSE'] === 'مسدد') {
		                    payStColor = 'success';
		                }
		                if (row['SUPPLIED_STATUSE'] === 'مكتمل') {
		                    suppStColor = 'success';
		                } else if (row['SUPPLIED_STATUSE'] === 'جزئى') {
		                    suppStColor = 'warning';
		                }
		                return `
		                <span class="badge badge-${payStColor} w-100">
		                    حالة السداد: ${row['PAYMENT_STATUSE']}
		                </span>
		                <br>
		                <span class="badge badge-${suppStColor} w-100">
		                    حالة الاستلام: ${row['SUPPLIED_STATUSE']}
		                </span>`;
		            },
		            title: 'الحاله',
		            className: 'text-center text-nowrap'
		        },
		        {
		            data: 'QTY_ORDER_TOTAL',
		            orderable: true,
		            title: 'الكميه المطلوبة',
		            className: 'text-center text-nowrap'
		        },
		        {
		           // data: 'QTY_ORDER_WAITING',
		            orderable: true,
		            render: (data, type, row, meta) => {
		            	let tclass ="text-success", value = row['QTY_ORDER_WAITING'];
						if (value > 0 ){
						  tclass = "text-danger"; // استبدال NaN بالصفر
						}
		            	return `<span class="${tclass}">${value}</span>`;
		            },
		            title: 'كميه قيد الاستلام',
		            className: 'text-center text-nowrap'
		        },
		        {
		            orderable: false,
		            render: (data, type, row, meta) => {
		            	
		            	let disabled='';
		            	if (!row['SUPPLIED_STATUSE'] || row['SUPPLIED_STATUSE'] === 'مكتمل'){
		            		disabled = 'disabled'
		            	}
		                return `
		                <button id="ReturnPurchaseOrder" type="button" data-id="${row['ORDER_ID']}" data-suppid="${row['SUPPLIER_ID']}" class="btn btn-app-row btn-xs bg-info h-80 f-5 m-1">
		                    <i class="fas fa-hand-holding-medical"></i>
		                </button>`;
		            },
		            title: 'صرف',
		            className: 'text-center text-nowrap'
		        }
		    ],
		    buttons: [
			        {
			            text: 'تحديث البيانات',
			            action: function ( e, dt, node, config ) {
			                dt.ajax.reload();
			            }
			        }
			    ],
			order  : [[1,'desc']]
		}).on('draw', function() {
			table.columns.adjust();
			// زر انشاء امر مرتجع
			table.on('click', '#ReturnPurchaseOrder', function (e) {
				$('.btn-app-pade #btnSave').attr('onclick',`savePurchaseReturnOrderStock(`+$(this).attr('data-id')+`,`+$(this).attr('data-suppid')+`)`);
				let tr  = e.target.closest('tr');
				let row = table.row(tr);
				$('#ReceiveOrderPurchase .tableROPU').attr('data-id'	,$(this).attr('data-id'));
				$('#ReceiveOrderPurchase .tableROPU').attr('data-suppid',$(this).attr('data-suppid'));
				addReturnPurchase(row.data());
			});
			
		});
	
}
// حفظ امر ارجاع المشتريات
async function savePurchaseReturnOrderStock(oid,suppid){
	 	if(!oid || oid ==''){
	    	Swal.fire({
	            icon: 'error',
	            title: 'خطاء',
	            text: 'لايمكن العثور على رقم امر الشراء',
	        });
	        return false;
	    }
	 	var tableuppu = $('#tableROPU').DataTable();
	 	var tablePUR  = $('#dputbl').DataTable();
	    const tableData = [];
	    // جمع البيانات من الجدول
	    tableuppu.rows().every(function() {
	        var data = this.data();
	        var RETURN_QTY= parseInt(data["RETURN_QTY"]);
	        if (isNaN(RETURN_QTY) || RETURN_QTY <= 0) {
	            Toast_fire([
	                'e',
	                'تأكد من إدخال كميات صحيحة لكل منتج'
	            ]);
	            return ; // إنهاء التكرار
	        }
	        tableData.push({
	        	//'ORDER_ID'		 : oid,
	            'PRO_CODE'  		 : data["PRODUCT_CODE"],
	            'PURCHASE_UNIT_PRICE': data["UNIT_PRICE"],
	            'QTY_SUPPLIED'		 : data["QTY_SUPPLIED"],
	            'QTY_RETURN'		 : RETURN_QTY,
	            'QTY'				 : RETURN_QTY,
	            
	        });
	        
	    });
		
	    // التحقق مما إذا كانت البيانات فارغة
	    if (tableData.length === 0) {
	        Swal.fire({
	            icon: 'warning',
	            title: 'لا يوجد مرتجعات',
	            text: 'يرجى إضافة الكمية المرتجعه للمنتجات قبل الحفظ لايمكن قبول مرتجع بقيمة صفرية',
	        });
	        return false;
	    }
	    
	    // إرسال البيانات إلى الخادم
	    try {
	    	let obj = {
	    		 'CONTROL'	  : 'setReturnOrderPurchises',
	    		 'ORDER_ID'	  : oid,
	    		 'SUPPLIER_ID': suppid,
	    		 'STO_ID'	  : orderIssueStoId,
		         'PRODUCTS'	  : tableData
	    	};
	    	console.log(obj)
		    let response = await sendRequest(urlinv, obj);
		    // التعامل مع الرد
		    
		    if (response[0] === 'success') {
		    	tableuppu.clear();   
		        tablePUR.ajax.reload();
		        $('.btn-app-pade #btnSave,#btnCancel').hide();
		        showSection('SelectOrderType');
		        //viewPurchaseList();
		    }
		    responseMsg(response);
	    } catch (error) {
	        console.error('خطأ أثناء الإرسال:', error);
	        Swal.fire({
	            icon: 'error',
	            title: 'خطأ في الاتصال',
	            text: 'حدث خطأ أثناء الاتصال بالخادم. يرجى المحاولة لاحقًا.',
	        });
	    }
	 };
// الغاء امر مرتجع المشتريات
function cancell(){
	$('.btn-app-pade #btnSave,#btnCancel').hide();
	showSection('SelectOrderType');
};




// استلام مرتجعات امر البيع
async function ShipmentApproved	(order) {
 	objdata = {}
 	objdata[order.oid] = order
	//let deliveryid = objdata[order.oid]['shipUid']
 	
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
						    "CONTROL": "DirectSaleDelivery",
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
	        		ShipmentDelivery(order);
	        	}else if(result.isDenied && order.custstat =='FREEZE'){
	        		responseMsg(['warning','تنبيه',
	        		'لايمكن تصحيح تسليمات المندوب - امر البيع مجمد بعهدة المندوب']);
	        	}
	        })
		} else { 
			Toast_fire(['i','حالة امر البيع لا تسمح بتنفيذ هذا الاجراء']);
			
			//ShipmentDelivery(order);
		}
 	}else{
 		Toast_fire(['w','ليس لديك صلاحية لاستلام مرتجعات امر البيع']);
 		
 	}
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

function parseShipmentTable() {
    let oid = null;
    document.querySelectorAll(".table-swel tbody tr").forEach(tr => {
        oid = tr.dataset.oid;
    });
    
    return oid;
};

//تجهيز بيانات المنتجات لعرضها على العميل
function renderProductsTable(data_products) {
	  index = 0
	  let products = data_products.products;
	  let returned = data_products.returned;
	  let html = `
	    <div class="bg-danger1">
			<table class="table table-swel table-hover table-responsive text-nowrap table-striped w-100 col-sm-12 p-0 m-0 " style="font-size: 1rem;">
					<thead>
						<tr class="">
							<th class="col-4">المنتج</th>
							<th class="col-2 badge-gry10">المطلوب</th>
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
		</tr>`;
		index++;
	  });
	
	  html += `</tbody></table></div>`;
	  
	  $(".swal2-html-container").html(html);
}

// جلب منتجات امر البيع
function loadOrderDetails(orderId) {
    return $.ajax({
        url: urlship,
        type: 'GET',
        async:true,
        dataType: 'json',
        data: {
            CONTROL  : "DirectSaleDelivery",
            order_id : orderId,
			olup     : orderLastUpdate
        }
    });
};

//تسليم امر البيع
function ShipmentDelivery(orderId) {
	console.log(orderId)
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

                if (response.type == "success") {
					
                    $(".swal2-title").html("تأكيد تسليم امر البيع المباشر");
                    //$(".swal2-html-container").html(response[1]).show();
					renderProductsTable(response);
                    $(".swal2-cancel").show();
                    Swal.hideLoading();
                } else {
                    Swal.fire(response.type, response.title, response.message);
					Swal.hideLoading();
                }
            }).fail(() => { 
                Swal.fire('نأسف', 'حدث خطأ غير معروف أثناء التنفيذ', 'error');
            });
        },
        preConfirm: () => {
            const orderid = parseShipmentTable();
            
            return orderid;
        }
    }).then(result => {
        if (result.isConfirmed) {
           
            Swal.fire({
		        title: 'تأكيد',
		        icon: 'question',
		        html: `سوف يتم صرف منتجات امر البيع بالكامل`,
				showCancelButton: true,
		        confirmButtonText: 'تأكيد',
		        cancelButtonText: 'إلغاء',
		        didOpen : ()=> {
		        	
		        	$(".swal2-container label").hide();
		        	
		        	const checkbox = true;
				    const confirmBtn = Swal.getConfirmButton();
		        },
		        preConfirm: () => {
		        	let data = orderId[Object.keys(orderId)[0]];
		        	data.items = result.value.shipp;
		            
		            
		            return {
		                CONTROL: "confirmDirectOrderSales",
		                oid: result.value,
						olup: orderLastUpdate
		            };
		        }
		    }).then(confirmRes => {
                if (confirmRes.isConfirmed) {
                    let finalData = confirmRes.value;
                    console.log(finalData)
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












//===================add purchase order====================
// التعامل مع خيارات المنتج والمواصفات
const handleProductOptions = async function (product) {
	    const iGroup = product.product['OPTION_GROUP'];
	    const iCodes = product.product['CODES'];
	    if (Object.keys(iGroup).length) {
	        return renderOptionSelectors(iGroup, iCodes, product);
	    } else {
	        //setupDefaultProduct(iCodes, product);
	        let icodeArr = typeof iCodes[0] === "string" ? JSON.parse(iCodes[0]) : iCodes[0];
	        return prepareProductData(icodeArr, product);
	    }
	},
	// عرض قائمة الخيارات عند وجود مواصفات
	renderOptionSelectors = async function (iGroup, iCodes, product) {
	    const loadingHtml = `
	            <div id="swl-productes" data-id="${product.id}" data-code="${product.text}">
	                <div class="spinner-grow" style="margin-top:25px;" role="status">
	                    <span class="sr-only">Loading...</span>
	                </div>
	                <div style="font-size:8px;">
	                    <span>جارى عرض البيانات...</span>
	                </div>
	            </div>`;
	   const result = await Swal.fire({
		    	title: 'إختار المنتج',
		    	html: loadingHtml,
		    	showCancelButton: true,
		    	confirmButtonText: 'تأكيد',
		        cancelButtonText: 'إلغاء',
		        didOpen: () => {
		            	setTimeout(function (){
		            		let selectors = '';
						    for (const groupKey in iGroup) {
						        let group = typeof iGroup[groupKey] === 'string' ? JSON.parse(iGroup[groupKey]) : iGroup[groupKey];						       // group = JSON.parse(group);
						        selectors += `
						            <div class="form-group">
						                <label>${group.oName}</label>
						                <select class="form-control op select2">
						                    <option value="" selected>اختار ${group.oName}</option>
						                    ${Object.values(group.values).map(option => `<option value="${option.ID}">${option.VAL}</option>`).join('')}
						                </select>
						            </div>`;
						    }
						    $("#swl-productes").html(selectors);
						    // التعامل مع تغيير الخيارات
						   /* $(document).on('change', '#swl-productes .op', function () {
						        handleOptionChange(iCodes, product);
						    });*/
		            	},500);
		            },
		        preConfirm: ()=>{
		        	return handleOptionChange(iCodes, product);
		        }
	    });
	    
	    if (result.isConfirmed && Object.values(result.value).length > 0) {
	        return result.value; // Return the selected options array
	    } else {
	        return []; // Return an empty array if the user cancels or doesn't select anything
	    }
	},
	// التعامل مع تغيير الخيارات
	handleOptionChange = async function (iCodes, product) {
	    const selectedOptions = $('#swl-productes .op').map((_, el) => parseInt($(el).val())).get();
	    const productCode = findMatchingCode(iCodes, selectedOptions);
	    if (productCode) {
	       return  prepareProductData(productCode, product);
	    } else {
	       return [];
	    }
	    
	},
	// البحث عن الكود المطابق للخيارات المحددة
	findMatchingCode = function (iCodes, selectedOptions) {
	    for (const codeKey in iCodes) {
	    	iCodeSet = typeof iCodes[codeKey] === "string" ? JSON.parse(iCodes[codeKey]) : iCodes[codeKey];
	    	
	        const codeOptions = typeof iCodeSet['VID'] === 'string' ? JSON.parse(iCodeSet['VID']) : iCodeSet['VID'];
	        const matching = arraysMatch(codeOptions, selectedOptions);
	        if (matching) {
	            return typeof iCodes[codeKey] === 'string' ?JSON.parse(iCodes[codeKey]) : iCodes[codeKey];
	        }
	    }
	    return null;
	},
	// التحقق من تطابق المصفوفات
	arraysMatch = function (arr1, arr2) {
	    if (arr1.length !== arr2.length) return false;
	    const sortedArr1 = [...arr1].sort();
	    const sortedArr2 = [...arr2].sort();
	    return sortedArr1.every((val, index) => {
	        // تطابق مع تجاهل المسافات وحساسية الحالة، وتحويل النوع
	        return String(val).trim().toLowerCase() === String(sortedArr2[index]).trim().toLowerCase();
	    });
	},
	// إعداد بيانات المنتج
	prepareProductData = async function (code, product) {
	    const arr = {
			'itemCode' :code.PRO_CODE,
			'itemName' :product.text,
			'option'   :code.OPTIONS? code.OPTIONS:'',
			'costPrice':code.PURCHASE_PRICE,
			'sellPrice':code.SELL_PRICE,
			'maxstock' :code.MAX_STOCK,
			'minstock' :code.MIN_STOCK
		};
		return arr;
	},
	// اضافة صنف الى الجدول
	addNewItem = function () {
	    // الحصول على القيم من الحقول
	    var x = newItemArray;
	    var quantity = parseInt($('#itemForm #quantity').val(), 10);
		var unitprice = parseInt($('#itemForm #unitprice').val(), 10);
	    //var totalprice = (unitprice * quantity)
	    // التحقق من أن الحقول غير فارغة
	    if (x.itemCode && x.itemName && x.costPrice && quantity) {
	        var addtable = $('#dtbl').DataTable();
	        var rowIndex = null;
	        
	        // البحث عن المنتج في الجدول
	        addtable.rows().every(function(rowIdx, tableLoop, rowLoop) {
	            var data = this.data();
	            
	            if (data["0"] == x.itemCode) { // التحقق من تطابق itemCode
	                rowIndex = rowIdx;
	                return false; // التوقف عن البحث عند إيجاد المنتج
	            }
	        });
	
	        if (rowIndex !== null) {
	            // إذا كان المنتج موجودًا، تحديث الكمية وحذف السطر القديم
	            var existingData = addtable.row(rowIndex).data();
	            var newQuantity = parseInt(existingData["3"], 10) + parseInt(quantity,10);
	            addtable.row(rowIndex).remove().draw(false); // إزالة السطر القديم
	
	            // إضافة السطر مرة أخرى كأحدث سطر مع الكمية الجديدة
	            addtable.row.add({
	                "0": x.itemCode,
	                "1": `<div class='text-nowrap text-left'><div class=''>${x.itemName}</div><a class="badge text-info">${x.option}</a></div>`,
	                "2": newQuantity,
	                "3": `<button onclick="removeItem(this)" class="btn btn-app-row btn-xs bg-danger w-100 h-100 f-5"><i class="fas fa-trash"></i></button>`
	            }).draw(false);
	        } else {
	            // إذا لم يكن المنتج موجودًا، يتم إضافته كمنتج جديد
	            addtable.row.add({
	                "0": x.itemCode,
	                "1": `<div class='text-nowrap text-left'><div class=''>${x.itemName}</div><a class="badge text-info">${x.option}</a></div>`,
	                "2": quantity,
	                "3":`<button onclick="removeItem(this)" class="btn btn-app-row btn-xs bg-danger w-100 h-100 f-5"><i class="fas fa-trash"></i></button>`
	            }).draw(false);
	        }
	
	        // إعادة تعيين الحقول
	        $('#quantity').val('');
	        $('#itemName').val(null).trigger('change');
	        $('#itemName').select2('open');
	    } else {
	        alert('يرجى تعبئة جميع الحقول');
	    }
	};
	// حذف جميع الاصناف من الجدول
	cleardata = function () {
	    var addtable = $('#dtbl').DataTable();
	    addtable.clear().draw(); // إزالة كل السجلات وتحديث الجدول
	    $('.textbox,.reference,.quantity,.unitprice').val('').change();
	    $('itemName')// الكود يتم تنفيذه بعد الإجراء المطلوب
		$('#itemName,#suppliers').val(null).trigger('change');
	},
	// حذف الصنف من الجدول
	removeItem = function (button) {
	    var addtable = $('#dtbl').DataTable();
	    addtable.row($(button).parents('tr')).remove().draw();
	},
	// التأكد من الحقول
	validateRequiredFields = function () {
	    let isValid = true;
	    $('.required').each(function () {
	        const field = $(this);
	        // التحقق من وجود الحقل وقيمته
	        if (field.val()) {
	            field.removeClass('is-invalid');
	
	            // إزالة رسالة الخطأ إن وجدت
	            const existingError = field.next('.invalid-feedback');
	            if (existingError.length > 0) {
	                existingError.remove();
	            }
	        } else {
	            isValid = false;
	            field.addClass('is-invalid');
	
	            // إضافة رسالة الخطأ فقط إذا لم تكن موجودة
	            if (field.next('.invalid-feedback').length === 0) {
	                field.after(
	                    $('<div>')
	                        .addClass('invalid-feedback')
	                        .css({
	                            color: 'red', // لون الخط أحمر
	                            fontSize: '12px', // خط صغير
	                        })
	                        .text('هذا الحقل مطلوب.')
	                );
	            }
	        }
	    });
	
	    return isValid; // إرجاع حالة التحقق
	},
	cleardata = function () {
	    var addtable = $('#dtbl').DataTable();
	    addtable.clear().draw(); // إزالة كل السجلات وتحديث الجدول
	    $('.textbox,.reference,.quantity,.unitprice').val('').change();
	    $('itemName')// الكود يتم تنفيذه بعد الإجراء المطلوب
		$('#itemName,#suppliers').val(null).trigger('change');
	},
	
	
	
	
	
	saveScrap = async function () {
	 	var addtable = $('#dtbl').DataTable();
		let obj = {};
	    const tableData = [];
	    // جمع البيانات من الجدول
	    addtable.rows().every(function() {
	        var data = this.data();
	        var quantity = parseInt(data[2]);
			//var costPrice = data[2];
			var qtySupp = 0;
	        // التحقق من أن الكمية صالحة
	        if (isNaN(quantity) || quantity <= 0) {
	            Toast_fire([
	                'e',
	                'تأكد من إدخال كميات صحيحة لكل منتج'
	            ]);
	            return ; // إنهاء التكرار
	        }
		
			if(Go==='AddStock'){
		   		qtySupp = quantity;
		    }
		    tableData.push({
		            'PRO_CODE'  		 : data["0"],
		            'QTY'				 : quantity
		    });
	    });
	
	    // التحقق مما إذا كانت البيانات فارغة
	    if (tableData.length === 0) {
	        Swal.fire({
	            icon: 'warning',
	            title: 'لا يوجد منتجات مسجلة',
	            text: 'يرجى إضافة منتجات قبل الحفظ',
	        });
	        return false;
	    }

	    // إرسال البيانات إلى الخادم
	    try {
	    	var vrf = validateRequiredFields();
	    	obj = {
	    		 'CONTROL'	  : 'addScrapPro',
	    		 'from_stoid' : orderIssueStoId,
		         'COMMENT'    : $('#textbox').val(),
		         'PRODUCTS'	  : tableData
	    	}
	  
	    	if(vrf){
		        let response = await sendRequest(urlinv, obj);//['success','ok','done'] 
		
		        // التعامل مع الرد
		        responseMsg(response);
	
		        if (response[0] === 'success') {
		            cleardata();
		        }
	    	}else{
	    		Toast_fire([
			         'e',
					 'جميع الحقول المحدده بـ اللون الاحمر مطلوبة'
		    	]);
	    	}
	    } catch (error) {
	        console.error('خطأ أثناء الإرسال:', error);
	        Swal.fire({
	            icon: 'error',
	            title: 'خطأ في الاتصال',
	            text: 'حدث خطأ أثناء الاتصال بالخادم. يرجى المحاولة لاحقًا.',
	        });
	    }
	 };












$(document).ready(function () {
	$('.btn-app-pade').append('<button id="btnSave" class="btn btn-app-tools btnh text-dark btnSave" style="display:none" onclick="" ><i class="fas fa-save"></i><span class="btnname">حفظ</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>');
	$('.btn-app-pade').append('<button id="btnBack"         class="btn btn-app-tools btnh text-dark btnBack" style="display:none" onclick="btnBack();" ><i class="fas fa-arrow-left"></i><span class="btnname">الرجوع</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>');
	$('.btn-app-pade').append('<button id="newPurchaseOrder"         class="btn btn-app-tools btnh text-dark newPurchaseOrder" style="display:none" onclick="" ><i class="fas fa-arrow-left"></i><span class="btnname">الرجوع</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>');
	$('.btn-app-pade').append('<button id="btnCancel"       class="btn btn-app-tools btnh text-dark btnCancel" style="display:none" onclick="cancell()" ><i class="fas fa-times"></i><span class="btnname">الغاء</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>');
	//$('.btn-app-pade').append(`<button id="btnReturnPurchaseList" class="btn btn-app-tools btnh text-dark btnReturnPurchaseList" onclick="viewPurchaseList();$('.btn-app-pade #btnSave,#btnCancel').hide();" style="display:none;" ><i class="fas fa-th-list"></i><span class="btnname">الاوامر</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>`);
	$('.btn-app-pade').append(`<button id="btnReturnOrderList" class="btn btn-app-tools btnh text-dark btnReturnOrderList" onclick="viewPurchaseList();" style="display:none;"><i class="fas fa-th-list"></i><span class="btnname">الاوامر</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>`);
	$('.DOL').on('showElement', function () {
		$('#btnback').show();
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
		    if(ptype == 1){
				displayOrderViewData([$(this).attr('data-id')],btnapp);
			} else {
				Toast_fire(['w','ليس لديك صلاحية عرض بيانات امر البيع']);
			}
	    },1);
	});
	$(document).on('click', '.btnDelevered', function () {
		// tr الخاص بالجدول الداخلي
		let innerTr = $(this).closest('tr');
		// tr الخاص بالداتا تيبلز الأساسي
		let mainTr = innerTr.closest('table').closest('tr');
		let data = table.row(mainTr).data();
		let obj = {
			[data['oid']] : {
				'oid':data['oid'],
				'EXCHANGE' : data['EXCHANGE'],
				'EXCHANGE_SUPID' : data['custstat'],
				'custstat' : data['EXCHANGE'],
				'olup' : data['olup'],
				'shipUid' : -1
			}
		};
		orderLastUpdate = data['olup'];
		ShipmentDelivery(obj);
	});

	showSection('SelectOrderType');
	// اختيار النوع
	$('.type-select').click(function() {
		orderIssueType = $(this).attr('id');
		getInventoryData(orderIssueType);
		title = 'إذن ' + $(this).text();
		$('#tableTitle').html(title);
	})
	
	//عرض منتجات مرتجع امر الشراء
	tableROPU = $('#tableROPU').DataTable({
 		dom: '<"wrapper"t>',
    	searching: true,
    	ordering: false,
    	processing: true,
    	serverSide: false,
        data: [],
        columns: [
            { data: 'PRODUCT_CODE', title: 'كود الصنف' },
			{
				orderable: false,
				className: 'text-left col-12 text-nowrap',
				render: (data, type, row, meta) => {
					return `
					<div class="tdrest tdproName">${row['PRODUCT_NAME']}</div>
					<a class="tdrest text-nowrap text-info tdproOption">${row['PRODUCT_ATTRIBUTES'] ?? ''}</a>`
				},
				title: 'الصنف'
			},
            {
				orderable: false,
				className: 'text-center col-12 text-nowrap',
				render: (data, type, row, meta) => {
					return row['QTY_SUPPLIED'] - row['QTY_RETURN'];
				},
				title: 'المستلم'
			},
            { 
            	data:'RETURN_QTY',
            	className: 'text-center col-12 text-nowrap', 
            	title: 'المرتجع'
            },
            {
				orderable: false,
				className: 'text-center col-12 text-nowrap',
				render: (data, type, row, meta) => {
					return `
					<button id="btnEdit" type="button" data-oid="${row['ORDER_ID']}" class="btn btn-app-row btn-xs bg-primary btnEdit h-80 f-5 m-1">
						<i class="fas fa-edit"></i>
					</button>`
				},
				title: 'تحرير'
			},
        ]
    }).on('draw',function(){
    	tableROPU.columns.adjust();
    	tableROPU.on('click', '.btnEdit', function () {
			// الحصول على بيانات الصف
			var rowData = tableROPU.row($(this).closest('tr')).data();
				
			// تحديد الكمية قيد الاستلام
			var waitingQty = parseInt(rowData['QTY_SUPPLIED'] - rowData['QTY_RETURN']).toFixed(2);
			
			// عرض مربع الحوار بـ SweetAlert
			    Swal.fire({
			        title: 'تعديل الكمية المرتجعه',
			        input: 'number',
			        inputLabel: `الكمية قيد الارجاع: ${waitingQty}`,
			        inputAttributes: {
			            min: 1,
			            step: 1
			        },
			        inputPlaceholder: 'أدخل الكمية المرتجعه',
			        showCancelButton: true,
			        confirmButtonText: 'موافق',
			        cancelButtonText: 'إلغاء',
			        preConfirm: (inputValue) => {
			            if (inputValue === '' || inputValue === null) {
			                Swal.showValidationMessage('الرجاء إدخال الكمية المرتجعه');
			                return false;
			            }
			            if (parseInt(inputValue) > parseInt(waitingQty)) {
			                Swal.showValidationMessage(`الكمية لا يمكن أن تتجاوز ${waitingQty}`);
			                return false;
			            }
			            return inputValue;
			        }
			    }).then((result) => {
			        if (result.isConfirmed) {
			            var newQty = result.value;
			            // تحديث البيانات في DataTable
			            rowData['RETURN_QTY'] = parseInt(newQty);
			            //rowData['WAITING_QTY'] -= newQty;
			            // إعادة رسم الجدول لعرض التعديلات
			            tableROPU.row($(this).closest('tr')).data(rowData).draw();
			            // تحديث البيانات في الخادم (مثال)
			            //updateOrderQuantity(rowData['ORDER_ID'], newQty);
			        }
			    });
		});
    })
    
	$('#dtbl').DataTable({
	    	dom: '<"wrapper"t>',
	    	processing: true,
		    serverSide: false,
		    columns: [
		        { data: "0" },
		        { data: "1" },
		        { data: "2" },
		        { data: "3" }
		    ],
		    buttons: [],
	});
	
	    // حقل اختيار المنتج select2
	$('#itemName').select2({
	    placeholder: 'ابحث عن المنتج',
	    allowClear: true,
	    language: 'ar',
	    selectionCssClass: 'form-control',
	    width: '100%',
	    dir: 'rtl',
	    ajax: {
	        url: `${urlApi}?CONTROL=Productlist`, 
	        dataType: 'json',
	        delay: 500, // تأخير الطلب لتقليل التحميل
            data: function(params) {
                return {
                    query: params.term // مصطلح البحث
                };
            },
	        processResults: function(data) {
	            return {
	                results: data.map(function(product) {
	                    return {
	                        id: product.id,
	                        text: product.name, // استخدام المفتاح الصحيح للنص
	                        product: {
	                            OPTION_GROUP: product.OPTION_GROUP,
	                            CODES: product.CODES
	                        }
	                    };
	                })
	            };
	        }
	    },
	    templateResult: function(data) {
	        if (!data.id) {
	            return data.text;
	        }
	
	        // عرض الاسم في القائمة
	        return data.text;
	    }
	});
	
	// عند اختيار المنتج
	$('#itemName').on('select2:select',async function(e) {
	    $('#itemForm .unitprice,.quantity').val('');
	    const selected = e.params.data;
	    const result = await handleProductOptions(selected);
	    if(Object.keys(result).length){
	    	newItemArray = result;
	    	setTimeout(function(){
				focusNext('quantity');
	    		//$('#itemForm .unitprice').val(result.costPrice).trigger('change');
			},400)
	    }
	    return 
	});

});

