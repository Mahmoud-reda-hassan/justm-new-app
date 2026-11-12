const urlvo = `../../inc/controller/viewOrders.control.php`;
var newItemArray		={};
var tableSalesReturn =null;
var tableROPU = $('#tableROPU').DataTable({
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
				className: 'text-left text-nowrap',
				render: (data, type, row, meta) => {
					return `
					<div class="tdrest tdproName">${row['PRODUCT_NAME']}</div>
					<a class="tdrest text-nowrap text-info tdproOption">${row['PRODUCT_ATTRIBUTES'] ?? ''}</a>`
				},
				title: 'الصنف'
			},
            {
				orderable: false,
				className: 'text-center text-nowrap',
				render: (data, type, row, meta) => {
					return parseInt(row['QTY'] - row['QTY_SUPPLIED']);
				},
				title: 'قيد الاستلام'
			},
            { 
            	data:'RECEIVE_QTY',
            	className: 'text-center text-nowrap', 
            	title: 'المستلم'
            },
            {
				orderable: false,
				className: 'text-center text-nowrap',
				render: (data, type, row, meta) => {
					return `
					<button id="btnEdit" type="button" data-oid="${row['ORDER_ID']}" class="btn btn-app-row btn-xs bg-primary btnEdit h-80 f-5 m-1">
						<i class="fas fa-edit"></i>
					</button>`
				},
				title: 'تحرير'
			},
        ]
    });

const urlcontrol	= `../../inc/controller/inventory.control.php`,
	 urlpurchase	= `../../inc/controller/purchases.control.php`,
	 urlinv	= `../../inc/controller/inventory.control.php`,
	 urlship = "../../inc/controller/shipment.control.php",
	 //urlApi    		= `../../inc/Api.php`,
	 datatableObj	= {'CONTROL':'listPurchasesDT'},

	 // اخفاء كل الاقسام واظهار القسم المطلوب
	 showSection = function(id) {
		$('.form-section').hide();
		$('#' + id).fadeIn();
	 },
	 // التراجع عن اختيار النوع
	 btnBack =function () {
	 	
				if ($('#SelectInventory').is(':visible') || $('#PurchaseList').is(':visible') || $('#ordersReturn').is(':visible')) {
					$('.btn-app-pade .btnBack').hide();
					$('.form-section, .btnPlus').hide();
					$('#SelectInventory .inv-select').remove();
					showSection('SelectOrderType');
				}else if($('#tableList').is(':visible')){
					$('.form-section').hide();
					showSection('SelectInventory');
				}
 	 }
	 addnewstock = function(){
	 		$('.btnBack').hide();
			$('.btnSaveAddStock, .btnCancel').show();
			$('#PurchaseList , .btnPlus').hide();
			$('#addnewstock').show();
	 }
	 viewPurchaseList = function () {
		if (!$('#PurchaseList').is(':visible')) {
			$('#PurchaseList, .btnBack, .btnPlus').show();
			$('.ReceiveOrderPurchase, .btnCancel, .btnPurchaseList, #addnewstock, .btnSaveAddStock').hide();
			var tablePUR = $('#dputbl').DataTable();
			tablePUR.ajax.reload();     
		}else{
			$('.btnBack , .btnPlus').hide();
			$('.btnSaveAddStock, .btnCancel').show();
			$('#PurchaseList').hide();
			$('#addnewstock').show();
			//$('.btn-app-pade .btnCancel').attr('onclick',viewPurchaseList) ;
			
		}
	
	 },
	 sendTableDataToServer  = async function () {
	    if ($('#addnewstock').is(':visible')) {
	    	savAddNewStock();
	    }else if ($('#ReceiveOrderPurchase').is(':visible')) {
	    	const oid	 = $('#ReceiveOrderPurchase .tableROPU').attr('data-id');
	    	const suppid = $('#ReceiveOrderPurchase .tableROPU').attr('data-suppid');
	    	updatePurchaseorderStock(oid,suppid);
	    }
	 
	},
//===================Received purchase order====================
	// عرض اوامر الشراء فى الجدول
	viewOrderPurchase = function (d) {
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
		const pro = d['PRODUCT_DETAILS'];
		let rowsPro = '';
		for (let key in pro) {
			let dt = pro[key],
			bgc = '';
			if (dt['RETURNED_QTY'] > 0) {
				bgc = 'bg-danger';
			}
			rowsPro += `<tr class="w-100 ${bgc}">
							<td class="text-nowrap col-4 pr-4 text-left">
								<div class="tdrest tdproName">${dt['PRODUCT_NAME']}</div>
								<a class="tdrest text-info tdproOption">${dt['PRODUCT_ATTRIBUTES']}</a>
							</td>
							<td class="tdrest col-1 text-center">${dt['QTY']}</td>
							<td class="tdrest col-1 text-center">${dt['UNIT_PRICE']}</td>
							<td class="tdrest col-1 text-center">${dt['QTY'] * dt['UNIT_PRICE']}</td>
							<td class="tdrest col-1 text-center">${dt['QTY_SUPPLIED']}</td>
							<td class="tdrest col-1 text-center">${dt['QTY'] - dt['QTY_SUPPLIED']}</td>
						</tr>`
		}
		return (`<div class="card-body DOVDATA" style="display: block;">
					<div class="invoice">
								<!-- title row -->
								<div class="row m-1">
									<div class="col-sm-12 text-left mt-2">
										<h4>
											<i class="fas fa-globe"></i>
											<span id="orderid"> امر توريد رقم - ${d['ORDER_ID']}</span>
											<small id="orderdate" class="float-right"></small>
										</h4>
									</div>
									<!-- /.col -->
								</div>
								<!-- info row -->
								<div class="row invoice-info text-left m-1">
									<div class="col-sm-6 invoice-col">
										<address>
											<a id="obyuname"><strong>المورد : </strong>${d['SUP_NAME']}</a>
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
											<a id="ouaddress"><strong>ملاحظات : </strong>${d['NOTE']}</a>
											<br>
											<a id="oucomm"></a>
										</address>
									</div>
									<!-- /.col -->
								</div>
								<!--Sales order product display template-->
								<div class="row ml-0 mr-0">
									<table class="table table-hover table-responsive table-bordered table-striped w-100 col-sm-12 p-0 m-0 ">
										<thead class="bg-secondary w-100 text-center">
											<tr>
											<th class="text-nowrap text-center">اسم المنتج</th>
											<th class="text-nowrap text-center">الكميه</th>
											<th class="text-nowrap text-center">سعر الشراء</th>
											<th class="text-nowrap text-center">اجمالى السعر</th>
											<th class="text-nowrap text-center">الكميه المستلمة</th>
											<th class="text-nowrap text-center">الكميه المنتظرة</th>
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
												<td colspan="2" class="te suptotal">${d['TOTAL_ORDER_PRICE']}</td>
											</tr>
											<tr>
												<th>المدفوع:</th>
												<td colspan="2" class="shipval">${d['TOTAL_PAYMENTS'] > 0 ? d['TOTAL_PAYMENTS'] : '0.00'}</td>
											</tr>
											<tr>
												<th>المتبقى:</th>
												<td colspan="2" class="total">${parseInt(parseInt(d['TOTAL_ORDER_PRICE']) - (parseInt(d['TOTAL_PAYMENTS']) > 0 ? parseInt(d['TOTAL_PAYMENTS']):'0.00')).toFixed(2)}</td>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
							<hr>
						</div>
						<!--/Sales order product display template-->
					</div>
				</div>`);
	},
	// عرض منتجات قيد الاستلام فى الجدول
	ReceiveOrder = async function  (d) {
	 	let waitingQty = [];
	 	let i = 0;
		for(let k in d['PRODUCT_DETAILS']){
			const pd = d['PRODUCT_DETAILS'][k];
			if(pd.QTY_SUPPLIED < pd.QTY){
				pd.RECEIVE_QTY = 0;
				waitingQty.push(pd);
				i++
			};
		};
		if(Object.keys(waitingQty).length<=0){
			Toast_fire(['w','هذا الامر تم استلامه بالكامل مسبقاً']);
			//return false;
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
		$('.PurchaseList ,.btnBack , .btnPlus').hide();
	 	$('.ReceiveOrderPurchase ,.btnCancel, .btnSaveAddStock').show();
	 	
	 	//btnBack 
		//	btnCancel
		
	 	tableROPU.clear();              		 // حذف جميع البيانات الحالية
	    tableROPU.rows.add(waitingQty);			 // إضافة البيانات الجديدة
	    tableROPU.draw();               		 // إعادة عرض الجدول
	
	 },
	// حفظ المنتجات المستلمه من امر البيع
	updatePurchaseorderStock = async function (oid,suppid){
	 	if(!oid || oid ==''){
	    	Swal.fire({
	            icon: 'error',
	            title: 'خطاء',
	            text: 'لايمكن العثور على رقم امر البيع',
	        });
	        return false;
	    }
	 	var tableuppu = $('#tableROPU').DataTable();
	 	var tablePUR = $('#dputbl').DataTable();
	    const tableData = [];
	    // جمع البيانات من الجدول
	    tableuppu.rows().every(function() {
	        var data = this.data();
	        var receivedQty= parseInt(data["RECEIVE_QTY"]);
		//	var costPrice = data["2"];
	        // التحقق من أن الكمية صالحة
	        if (isNaN(receivedQty) || receivedQty <= 0) {
	            Toast_fire([
	                'e',
	                'تأكد من إدخال كميات صحيحة لكل منتج'
	            ]);
	            
	            return ; // إنهاء التكرار
	       
	        }
	
	        tableData.push({
	        	'ORDER_ID'			 : oid,
	            'PRO_CODE'  		 : data["PRODUCT_CODE"],
	            'QTY'				 : receivedQty,
	            'QTY_SUPPLIED'		 : receivedQty
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
	    	let obj = {
	    		 'CONTROL'	  : 'SuppliedStock',
	    		 'ORDER_ID'	  : oid,
	    		 'SUPPLIER_ID': suppid,
		         'PRODUCTS'	  : tableData
	    	};
		    let response = await sendRequest(urlcontrol, obj);
		    // التعامل مع الرد
		    
		    if (response[0] === 'success') {
		    	tableuppu.clear();   
		        tablePUR.ajax.reload();
		        viewPurchaseList();
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
	 },
	//===================add purchase order====================
	// التعامل مع خيارات المنتج والمواصفات
	handleProductOptions = async function (product) {
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
	                "2": unitprice,
	                "3": newQuantity,
	                "4": (unitprice * newQuantity),
	                "5": `<button onclick="removeItem(this)" class="btn btn-app-row btn-xs bg-danger w-100 h-100 f-5"><i class="fas fa-trash"></i></button>`
	            }).draw(false);
	        } else {
	            // إذا لم يكن المنتج موجودًا، يتم إضافته كمنتج جديد
	            addtable.row.add({
	                "0": x.itemCode,
	                "1": `<div class='text-nowrap text-left'><div class=''>${x.itemName}</div><a class="badge text-info">${x.option}</a></div>`,
	                "2": unitprice,
	                "3": quantity,
	                "4": (unitprice * quantity),
	                "5":`<button onclick="removeItem(this)" class="btn btn-app-row btn-xs bg-danger w-100 h-100 f-5"><i class="fas fa-trash"></i></button>`
	            }).draw(false);
	        }
	
	        // إعادة تعيين الحقول
	        $('#quantity').val('');
	        $('#unitprice').val('');
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
	
	savAddNewStock = async function () {
	 	var addtable = $('#dtbl').DataTable();
	    const tableData = [];
	    // جمع البيانات من الجدول
	    addtable.rows().every(function() {
	        var data = this.data();
	        var quantity = parseInt(data[3]);
			var costPrice = data[2];
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
		            'PURCHASE_UNIT_PRICE': costPrice,
		            'QTY'				 : quantity,
		            'QTY_SUPPLIED'		 : qtySupp
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
	    if(!$('#suppliers').val() || $('#suppliers').val() ==''){
	    	Swal.fire({
	            icon: 'warning',
	            title: 'نعتذر',
	            text: 'برجاء اختيار المورد اولا!',
	        });
	        return false;
	    }
	
	    // إرسال البيانات إلى الخادم
	    try {
	    	var vrf = validateRequiredFields();
	    	let obj = {
	    		 'CONTROL'	  : 'AddStockAndPurchises',
	    		 'STOID'      : orderIssueStoId,
		         'SUPPLIER_ID': $('#suppliers').val(),
		         'REFERENCE'  : $('#reference').val(),
		         'COMMENT'    : $('#textbox').val(),
		         'PRODUCTS'	  : tableData
	    	}
	  
	    	if(vrf){
		        let response = await sendRequest(urlcontrol, obj);
		
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

	function saveShipApproved(parmm) {
		return $.ajax({
			url: urlship,
			type: 'POST',
			async:true,
			dataType: 'json',
			data: parmm
		});
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
	function createShipmentObject(orderId, parsed) {
		let { objApprov, objExApprov, objReject, objExReject, qtyApp, ExqtyApp, qtyDef, ExqtyDef, shipp_products} = parsed;
		// const { , , ,  } = parsed;
		let approvedBTN = true;
		let reapp = "";
		let qty_rejected = 0;
		let qty_returned = 0;
		let oExchange = null;
		if (qtyApp > 0 && qtyApp === qtyDef) {
			//reapp = "تسليم امر البيع بالكامل";
		} else if (qtyApp > 0) {
			//reapp = "تسليم امر البيع بشكل جزئي";
			//qty_rejected++;
		} else {
			//qty_rejected++;
		}
		if(ExqtyDef>0){
			//reapp +='<p>';
			//reapp += " و ";
		}
		if (ExqtyApp > 0 && ExqtyApp === ExqtyDef) {
			reapp += "مرتجع كامل لمنتجات امر البيع";
			qty_returned++;
		} else if (ExqtyApp > 0) {
			reapp += "مرتجع جزئي لمنتجات امر البيع";
			qty_returned++;
		} else if(ExqtyDef > 0){
			//reapp +='<p>';
		   // reapp += " و ";
		    approvedBTN = false;
			reapp +='رفض العميل تسليم المرتجعات';
			
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
				
				/*if (dQty > aQty) {
					objReject[code] = { 
						'oid': oid, 
						'code': code ,
						'defqty': dQty, 
						'appQty': aQty, 
						'proData': proName };
				}*/
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

	//تجهيز بيانات المنتجات لعرضها على العميل
	function renderProductsTable(data_products) {
		  index = 0
		  let products = data_products.products;
		  let returned = data_products.returned;
		  let html = "";

		  if (returned.length){
		
			  html += `
				<div class="bg-danger1 refhide">
					<h4 class="mt-2 bg-danger refhide">إستلام مرتجعات أمر البيع<h4>
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
			type: 'GET',
			async:true,
			dataType: 'json',
			data: {
				CONTROL: "ReturnOfProducts",
				order_id: orderId,
			}
		});
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
				let rowCount = 0;
				$(".swal2-cancel").hide();
				loadOrderDetails(orderId).done(response => {

					if (response.type == "success") {
						
						$(".swal2-title").html("تأكيد تسليم امر البيع");
						//$(".swal2-html-container").html(response[1]).show();
						renderProductsTable(response);
						$(".swal2-cancel").show();
						Swal.hideLoading();
					} else {

						Swal.fire(response.type, response.title, response.message);
						Swal.hideLoading();
					}
					rowCount = $('.swal2-html-container tbody tr').length;
					
				}).fail(() => { 
					Swal.fire('نأسف', 'حدث خطأ غير معروف أثناء التنفيذ', 'error');
				});
				
			
			},
			preConfirm: () => {
				const parsed = parseShipmentTable();
				console.log(parsed);
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
							CONTROL: "returnedOrderSales",
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







$(document).ready(function() {
	$('.btn-app-pade').append('<button id="btnSaveAddStock" class="btn btn-app-tools btnh text-dark btnSaveAddStock" onclick="sendTableDataToServer();" style="display:none"><i class="fas fa-save"></i><span class="btnname">حفظ</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>');
	$('.btn-app-pade').append(`<button id="btnPurchaseList" class="btn btn-app-tools btnh text-dark btnPurchaseList" onclick="viewPurchaseList();" style="display:none"><i class="fas fa-th-list"></i><span class="btnname">الاوامر</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>`);
	$('.btn-app-pade').append('<button id="btnBack"         class="btn btn-app-tools btnh text-dark btnBack" style="display:none" onclick="btnBack();" style="display:none;" ><i class="fas fa-arrow-left"></i><span class="btnname">الرجوع</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>');
    $('.btn-app-pade').append('<button id="btnCancel"       class="btn btn-app-tools btnh text-dark btnCancel" style="display:none" onclick="viewPurchaseList();" ><i class="fas fa-times"></i><span class="btnname">الغاء</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>');
	showSection('SelectOrderType');
try {

//===================add purchase order====================

		 
	$('#dtbl').DataTable({
	    	dom: '<"wrapper"t>',
	    	processing: true,
		    serverSide: false,
		    columns: [
		        { data: "0" },
		        { data: "1" },
		        { data: "2" },
		        { data: "3" },
		        { data: "4" },
		        { data: "5" }
		    ],
		    buttons: [],
	});
	
	// حقل اختيار المورد Select2
    $('#suppliers').select2({
        placeholder: 'اختر المورد',
        allowClear: true,
        language: 'ar', // دعم اللغة
        selectionCssClass: 'form-control',
	    width: '100%',
	    dir: 'rtl',
        ajax: {
            url: `${urlApi}?CONTROL=supplierslist`, // المسار إلى ملف PHP
            method: 'GET',
            dataType: 'json',
            delay: 250, // تأخير الطلب لتقليل التحميل
            
            data: function(params) {
                return {
                    query: params.term // مصطلح البحث
                };
            },
            processResults: function(data) {
                // معالجة البيانات وإرجاع النتائج إلى Select2
                return {
                    results: data.map(function(supplier) {
                        return {
                            id: supplier.id, // القيمة
                            text: supplier.name // النص المعروض
                        };
                    })
                };
            },
            cache: true
        }
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
	    		$('#itemForm .unitprice').val(result.costPrice).trigger('change');
			},400)
	    }
	    return 
	});

//===================Received purchase order====================



	var tablePU = $('#dputbl').DataTable({
	    ajax: {
	        url: urlpurchase,
	        data: datatableObj,
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
	            orderable: false,
	            render: (data, type, row, meta) => {
	                return `
	                <button id="btnOview1" type="button" data-id="${row['ORDER_ID']}" class="btn btn-app-row btn-xs bg-primary dt-control h-80 f-5 m-1">
	                    <i class="fas fa-eye"></i>
	                </button>`;
	            },
	            title: 'عرض',
	            className: 'text-center text-nowrap'
	        },
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
	       /* {
	            data: 'TOTAL_ORDER_PRICE',
	            orderable: true,
	            title: 'الاجمالى',
	            className: 'text-center text-nowrap'
	        },*/
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
	                <button id="ReceiveOrder" type="button" data-id="${row['ORDER_ID']}" data-suppid="${row['SUPPLIER_ID']}" class="btn btn-app-row btn-xs bg-info h-80 f-5 m-1" ${disabled}>
	                    <i class="fas fa-hand-holding-medical"></i>
	                </button>`;
	            },
	            title: 'استلام',
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
		// هذه سيتم تشغيله بعد اكتمال عرض البيانات
		if (!$('#Olhbtn li a.active.show').length) {
			$('#OLUnderReceipt').addClass('active show');
		}
		tablePU.columns.adjust(); // إعادة ضبط الأعمدة بعد الته
	});
    
    tablePU.on('click', '.dt-control', function (e) {
		let tr = e.target.closest('tr');
		let row = tablePU.row(tr);
		// Hide all open rows
		tablePU.rows().every(function() {
		    if (this.child.isShown() && this.index() !== row.index()) {
		        this.child.hide();
		    }
		});
		if (row.child.isShown()) {
		        // This row is already open - close it
		        row.child.hide();
		} else {
		        // Open this row
		        row.child(viewOrderPurchase(row.data())).show();
		}
		    
	});
	
	tablePU.on('click', '#ReceiveOrder', function (e) {
		let tr = e.target.closest('tr');
		let row = tablePU.row(tr);
		$('#ReceiveOrderPurchase .tableROPU').attr('data-id',$(this).attr('data-id'));
		$('#ReceiveOrderPurchase .tableROPU').attr('data-suppid',$(this).attr('data-suppid'));
		ReceiveOrder(row.data());
	});
    
    // عند الضغط على زر تحرير
	tableROPU.on('click', '.btnEdit', function () {
	    // الحصول على بيانات الصف
	    var rowData = tableROPU.row($(this).closest('tr')).data();
		
	    // تحديد الكمية قيد الاستلام
	    var waitingQty = parseInt(rowData['QTY'] - rowData['QTY_SUPPLIED']).toFixed(2);
	
	    // عرض مربع الحوار بـ SweetAlert
	    Swal.fire({
	        title: 'تعديل الكمية المستلمة',
	        input: 'number',
	        inputLabel: `الكمية قيد الاستلام: ${waitingQty}`,
	        inputAttributes: {
	            min: 1,
	            step: 1
	        },
	        inputPlaceholder: 'أدخل الكمية المستلمة',
	        showCancelButton: true,
	        confirmButtonText: 'موافق',
	        cancelButtonText: 'إلغاء',
	        preConfirm: (inputValue) => {
	            if (inputValue === '' || inputValue === null) {
	                Swal.showValidationMessage('الرجاء إدخال الكمية المستلمة');
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
	            rowData['RECEIVE_QTY'] = parseInt(newQty);
	            //rowData['WAITING_QTY'] -= newQty;
	
	            // إعادة رسم الجدول لعرض التعديلات
	            tableROPU.row($(this).closest('tr')).data(rowData).draw();
	
	            // تحديث البيانات في الخادم (مثال)
	            //updateOrderQuantity(rowData['ORDER_ID'], newQty);
	        }
	    });
	});
	
	$(document).on("click", ".OLF", function () {
	    let tabId = $(this).attr('id'); // حفظ قيمة ID الخاصة بالعنصر الذي تم النقر عليه
	    // تحديث URL
	    tablePU.ajax.url(urlpurchase);
	    // تحديث البيانات التي يتم إرسالها
	    tablePU.settings()[0].ajax.data = function (d) {
	        d.tab = tabId; // استخدام القيمة المحفوظة
	        d.CONTROL = 'listPurchasesDT';
	        return d;
	    };
	    // إعادة تحميل الجدول
	    tablePU.ajax.reload(null, false);
	});
}catch(error){
	console.log(error);
}finally {
    // Optional: Code that will always execute, regardless of an error
}

//===================SalesReturn=======================
	tableSalesReturn = $('#tableOrdersReturn').DataTable({
	    ajax: {
	        url: urlvo,
	        data: {'CONTROL':'SalesReturn','ptype':1},
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
	                data: 'subOid', 
	                //title: 'الطلب',
	                className: 'text-center text-nowrap',
	                render: (data, type, row) => {
	                    let badgcolor = " badge-light ";
	                    badgeColor = row['CUST_STAT'] === "FREEZE" ? "badge-red50" :
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
	                //title: 'الحاله',
	                className: 'text-center text-nowrap',
	                render: (data, type, row) => {
	                	let badgcolor = " badge-light ";
	                	badgeColor = row['CUST_STAT'] === "FREEZE" ? "badge-red50" :
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
	               // title: 'العميل',
	                className: 'text-left text-nowrap',
	                render: (data, type, row) => {
	                	let badgcolor = " badge-light ";
	                	badgeColor = row['CUST_STAT'] === "FREEZE" ? "badge-red50" :
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
	               // title: 'تواريخ',
	                className: 'text-center text-nowrap',
	                render: (data, type, row) => {
	                	let badgcolor = " badge-light ";
	                	badgeColor = row['CUST_STAT'] === "FREEZE" ? "badge-red50" :
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
		            text: 'تحديث البيانات',
		            action: function ( e, dt, node, config ) {
		                dt.ajax.reload();
		            }
		        }
		    ],
	    /*fixedColumns: {
	        leftColumns: 0
	    },*/
	   // scroller:true,
	    pageLength: 25,
		order  : [[1,'desc']]
	}).on('draw', function() {
		
		tableSalesReturn.columns.adjust(); // إعادة ضبط الأعمدة بعد الته
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
	//$(document).on('click','#returnProduct',function(){
	//	alert('هذا الاجراء قيد الانشاء')
	//});
	$(document).on('click', '.returnProduct', function () {
		if ($(this).hasClass('text-secondary')) {
		    return;
		}
		// tr الخاص بالجدول الداخلي
		let innerTr = $(this).closest('tr');
		// tr الخاص بالداتا تيبلز الأساسي
		let mainTr = innerTr.closest('table').closest('tr');
		let data = tableSalesReturn.row(mainTr).data();
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
		ShipmentDelivery(obj);
	});
	
});