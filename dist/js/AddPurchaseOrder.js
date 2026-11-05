
var newItemArray		={}; 
const urlcontrol		= `../../inc/controller/inventory.control.php`,
	 urlpurchase			= `../../inc/controller/purchases.control.php`,
	 urlApi     			= `../../inc/Api.php`,
	 datatableObj			= {'CONTROL':'listPurchasesDT'},
	 viewPurchaseList = function () {
		if (!$('#PurchaseList').is(':visible')) {
			$('#PurchaseList, .btnBack').show();
			$('.ReceiveOrderPurchase, .btnCancel, .btnPurchaseList, #addnewstock, .btnSaveAddStock').hide();
			var tablePUR = $('#dputbl').DataTable();
			tablePUR.ajax.reload();     
		} else {
			$('.btnBack').hide();
			$('.btnPurchaseList, .btnSaveAddStock').show();
			$('#PurchaseList').hide();
			$('#addnewstock').show();
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
			if(!dt['PRODUCT_ATTRIBUTES']){
				dt['PRODUCT_ATTRIBUTES'] = "";
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
/*	ReceiveOrder = async function  (d) {
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
		$('.PurchaseList ,.btnBack').hide();
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
	 },*/
//===================add purchase order====================
	// التعامل مع خيارات المنتج والمواصفات
	handleProductOptions = async function (product) {
	    const iGroup = product.product['OPTION_GROUP'];
	    const iCodes = product.product['CODES'];
	    if (Object.keys(iGroup).length) {
	        return renderOptionSelectors(iGroup, iCodes, product);
	    } else {
	        //setupDefaultProduct(iCodes, product);
	        return prepareProductData(typeof iCodes[0] === 'string'  ? JSON.parse(iCodes[0]):iCodes[0] , product);
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
						        let group = typeof iGroup[groupKey] === 'string' ? JSON.parse(iGroup[groupKey]):iGroup[groupKey];
						       // group = JSON.parse(group);
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
	    	let chCodeOption = typeof iCodes[codeKey] === 'string' ? JSON.parse(iCodes[codeKey])['VID']:iCodes[codeKey]['VID'];
	        const codeOptions = typeof chCodeOption ==='string' ? JSON.parse(chCodeOption):chCodeOption;
	        const matching = arraysMatch(codeOptions, selectedOptions);
	        if (matching) {
	            return typeof iCodes[codeKey] === 'string' ? JSON.parse(iCodes[codeKey]) : iCodes[codeKey];
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
	    		 'CONTROL'	  : 'AddPurchises',
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
	 
$(document).ready(function() {
	$('.btn-app-pade').append('<button id="btnSaveAddStock" class="btn btn-app-tools btnh text-dark btnSaveAddStock" onclick="sendTableDataToServer();" ><i class="fas fa-save"></i><span class="btnname">حفظ</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>');
    $('.btn-app-pade').append(`<button id="btnPurchaseList" class="btn btn-app-tools btnh text-dark btnPurchaseList" onclick="viewPurchaseList();" ><i class="fas fa-th-list"></i><span class="btnname">الاوامر</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>`);
    $('.btn-app-pade').append('<button id="btnBack"         class="btn btn-app-tools btnh text-dark btnBack" style="display:none" onclick="viewPurchaseList();" ><i class="fas fa-arrow-left"></i><span class="btnname">الرجوع</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>');
    $('.btn-app-pade').append('<button id="btnCancel"       class="btn btn-app-tools btnh text-dark btnCancel" style="display:none" onclick="viewPurchaseList();" ><i class="fas fa-times"></i><span class="btnname">الغاء</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>');
	
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
	        method: 'GET',
	        dataType: 'json',
	        delay: 250, // تأخير الطلب لتقليل التحميل
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
	                <button id="btnOview" type="button" data-id="${row['ORDER_ID']}" class="btn btn-app-row btn-xs bg-primary dt-control h-80 f-5 m-1">
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
	        {
	            data: 'TOTAL_ORDER_PRICE',
	            orderable: true,
	            
	            title: 'الاجمالى',
	            className: 'text-center text-nowrap'
	        },
	        {
	            //data: 'TOTAL_PAYMENTS',
	            orderable: true,
	            render: (data, type, row, meta) => {
	            	let value = row['TOTAL_PAYMENTS'];
					if (value == null || isNaN(value)) {
					  value = 0; // استبدال NaN بالصفر
					}
	            	return value;
	            },
	            title: 'المدفوع',
	            className: 'text-center text-nowrap'
	        },
	        {
	            data: 'TOTAL_SUP_PRICE',
	            orderable: true,
	            render: (data, type, row, meta) => {
	            	let value = row['TOTAL_SUP_PRICE'];
					if (value == null || isNaN(value)) {
					  value = 0; // استبدال NaN بالصفر
					}
	            	return parseInt(value).toFixed(2);
	            },
	            title: 'تكلفة المستلم',
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
		order: [[1,'desc']]
	}).on('draw', function() {
		// هذه سيتم تشغيله بعد اكتمال عرض البيانات
		if (!$('#Olhbtn li a.active.show').length) {
			$('#OLAll').addClass('active show');
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
					return parseInt(row['QTY'] - row['QTY_SUPPLIED']);
				},
				title: 'قيد الاستلام'
			},
            { 
            	data:'RECEIVE_QTY',
            	className: 'text-center col-12 text-nowrap', 
            	title: 'المستلم'
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

});



