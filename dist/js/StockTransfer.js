const urlinv = '../../inc/controller/inventory.control.php';
var newItemArray		={};
const obj = {
    from_stoid: null,
    to_stoid: null,
    proid: [],
    catid: [],
    proData: []
};
//var table = $('#table').DataTable();
let inventoriesData = sendRequestGET(`${urlApi}?CONTROL=getdropWarehouse`);
let title = document.title;
var groupColumn1 = 0; // العمود الأول BR_NAME
var groupColumn2 = 1; // العمود الثاني STO_NAME
/* =======================
   DataTable
======================= */
var table = $('#products-table').DataTable({
		dom: '<"wrapper"Bt>',
    	searching: true,
    	ordering: false,
    	processing: true,
    	serverSide: false,
    	paging: false,
        data: [],
		columnDefs: [
            { visible: false, targets: [groupColumn1, groupColumn2] },
			{ targets: [1], className: 'no-export' }, // تحديد الأعمدة التي لن تظهر في التصدير
            { targets: [6], className: 'no-export' } // تحديد الأعمدة التي لن تظهر في التصدير
        ],
        columns: [
           	{ title: "اسم المخزن", data: "STO_NAME" }, // العمود الثاني
            { title: "المنتج", data: "PRO_NAME" }, // العمود الأول
            { title: "كود المنتج", data: "PRO_CODE",className:"text-nowrap col-6" }, // العمود الرابع
            {
				orderable: false,
				className: 'text-left col-12 text-nowrap',
				render: (data, type, row, meta) => {
					return `
					<div class="tdrest tdproName">${row['PRO_NAME']}</div>
					<a class="tdrest text-nowrap text-info tdproOption">${row['OPT_JSON'] ?? ''}</a>`
				},
				title: 'المنتج'
			},            //{ title: "الحد الأدنى للمخزون", data: "CODE_MIN_STOCK",className:"text-nowrap" }, // العمود السادس
            { title: "الكميه بالمخزن", data: "STO_QTY",className:"text-nowrap" }, // العمود السابع
            { title: "الكميه" , data: "SETQTY",className:"text-nowrap" }, // العمود السابع
			{
				orderable: false,
				className: 'text-center col-12 text-nowrap',
				render: (data, type, row, meta) => {
					return `
					<button id="btnEditRow" type="button" data-oid="${row['PRO_CODE']}" class="btn btn-app-row btn-xs bg-primary btnEditRow h-80 f-5 m-1">
						<i class="fas fa-plus"></i>
					</button>`
				},
				title: 'تحرير'
			},
        ],
	    buttons: [
	        {
	            extend: 'print',
	            text: 'طباعة',
	            title: function () {
	                return title;
	            },
            	exportOptions: {
	                columns: ':visible:not(.no-export)'
	            }
	        },
	        { 
	            extend: 'excel',
	            text: 'تصدير إلى Excel',
	            title: function () {
	            	return title;
	            },
            	exportOptions: {
	                columns: ':visible:not(.no-export)'
	            }
	        },
	        {
	            extend: 'pdf',
	            text: 'تصدير إلى PDF',
	            title: function () {
	                return title;
	            },
            	exportOptions: {
	                columns: ':visible:not(.no-export)'
	            }
	        }
	    ],
        drawCallback: function (settings) {
            var api = this.api();
            var rows = api.rows({ page: 'current' }).nodes();
            var lastGroup1 = null;
            var lastGroup2 = null;
			var lastGroup3 = null;
            api.column(groupColumn1, { page: 'current' }).data().each(function (group1, i) {
                var group2 = api.column(groupColumn2, { page: 'current' }).data()[i];
				
                if (lastGroup1 !== group1) {
                    $(rows).eq(i).before(
                        `<tr class="group bg-secondary group-level-1">
                            <td class="text-left" colspan="${api.columns().nodes().length}"><strong>المخزن : ${group1}</strong></td>
                        </tr>`
                    );
                    lastGroup1 = group1;
                    lastGroup2 = null; // Reset lastGroup2
                }

                if (lastGroup2 !== group2) {
                    $(rows).eq(i).before(
                        `<tr class="group bg-secondary group-level-2">
                            <td class="text-left pl-5" colspan="${api.columns().nodes().length}">المنتج : ${group2}</td>
                        </tr>`
                    );
                    lastGroup2 = group2;
                }

            });
        }
    
	});
	
/* =======================
   اختيار المخازن
======================= */
function renderWarehouses(containerId) {
    const container = document.getElementById(containerId);
    container.innerHTML += `<div class="row"></div>`;
    const row = container.querySelector('.row');

    Object.values(inventoriesData).forEach(w => {
		const div = document.createElement('div');
		div.className = 'col-md-3 mb-3 inv-select';
		div.dataset.id = w[0];
			
		// توليد اسم الكلاس ديناميكيًا
		const classNumber = (w[0] % 10) || 10;
		const className = `warehouse-${classNumber}`;
			
		div.innerHTML = `
				<div class="warehouse-card ${className} text-center p-3">
					<i class="fas fa-warehouse"></i>
					<div>${w[2]}</div>
				</div>`;
		row.appendChild(div);
		
    });
}
renderWarehouses('SelectFromInventory');
renderWarehouses('SelectToInventory');

/* =======================
   Events
======================= */
$(document).on('click', '#SelectFromInventory .inv-select', function () {
    obj.from_stoid = $(this).data('id');
    $('#SelectFromInventory').hide(500);
    $('#SelectToInventory').show(200); 
});

$(document).on('click', '#SelectToInventory .inv-select', function () {
    obj.to_stoid = $(this).data('id');

    if (obj.to_stoid === obj.from_stoid) {
        Swal.fire('خطأ', 'لا يمكن التحويل لنفس المخزن', 'error');
        obj.to_stoid = null;
        return;
    }
	
	$('.btn-app-pade .btnSave').show();
	$('.btn-app-pade .btnBack').show();
    $('#SelectToInventory').hide();
    $('#addTransferPro').show();
});

/* =======================
   اختيار نوع الإدخال
======================= 
$('#by-category').on('click', () => {
    $('.form-section').hide();
    $('#form-category').show();
});

$('#by-product').on('click', () => {
    $('.form-section').hide();
    $('#form-product').show();
});
// عند الضغط على زر تحرير
table.on('click', '.btnEditRow', function () {
	    // الحصول على بيانات الصف
	    var rowData = table.row($(this).closest('tr')).data();
		
	    // عرض مربع الحوار بـ SweetAlert
	    Swal.fire({
	        title: 'ادخل الكميه الفعليه',
	        input: 'number',
	        inputLabel: `الكميه الفعليه:`,
	        inputAttributes: {
	            min: 0,
	            step: 1
	        },
	        inputPlaceholder: 'أدخل الكمية المستلمة',
	        showCancelButton: true,
	        confirmButtonText: 'موافق',
	        cancelButtonText: 'إلغاء',
	        preConfirm: (inputValue) => {
	            if (inputValue === '' || inputValue === null) {
	                Swal.showValidationMessage('الرجاء إدخال الكمية');
	                return false;
	            }
	            if (Number(rowData['STO_QTY']) < Number(inputValue)) {
	                Swal.showValidationMessage('الكمية المطلوبة غير متوفرة بالمخزن - المتوفر: '+rowData['STO_QTY']);
	                return false;
	            }
	            
	            return inputValue;
	        }
	    }).then((result) => {
	        if (result.isConfirmed) {
	            var newQty = result.value;
	
	            // تحديث البيانات في DataTable
	            rowData['SETQTY'] = parseInt(newQty);
	            //rowData['WAITING_QTY'] -= newQty;
	
	            // إعادة رسم الجدول لعرض التعديلات
	            table.row($(this).closest('tr')).data(rowData).draw();
			}
	    });

	});*/
	
/* =======================
   تأكيد الاختيار
======================= */
function confirmSelection(type) {

    if (type === 'category') obj.catid = $('#category').val();
    if (type === 'product') obj.proid = $('#product').val();

    if ((!obj.catid || !obj.catid.length) && (!obj.proid || !obj.proid.length)) {
        Swal.fire('تنبيه', 'اختر منتج واحد على الأقل', 'warning');
        return;
    }

    obj.CONTROL = 'Productlist_transfer';//'StockTransferPreview';

    let req = sendRequest(urlinv, obj);
    obj.proData = req;
    table.clear();
	table.rows.add(req);
	table.draw(); 
    $('#selectType').hide();
    $('.btn-app-pade .btnBack').hide();
	$('.btn-app-pade .btnSave').show();
	$('.btn-app-pade .btnCancel').show();
    $('#TransferList').show();
}

/* =======================
   تعديل الكمية
======================= */
$('#products-table').on('click', '.btnEditQty', function () {
    let row = table.row($(this).closest('tr'));
    let data = row.data();

    Swal.fire({
        title: 'الكمية المحولة',
        input: 'number',
        inputAttributes: { min: 1 },
        preConfirm: val => {
            if (!val || val <= 0) return Swal.showValidationMessage('كمية غير صحيحة');
            if (Number(val) > Number(data.STO_QTY)) {
			    return Swal.showValidationMessage('أكبر من الرصيد');
			}
            return val;
        }
    }).then(res => {
        if (res.isConfirmed) {
            data.SETQTY = parseInt(res.value);
            row.data(data).draw();
        }
    });
});

/* =======================
   حفظ التحويل
======================= */
async function saveTransfer() {

    const products = [];
	var addtable = $('#dtbl').DataTable();
    addtable.rows().every(function () {
        let d = this.data();
        if (d[2] > 0) {
            products.push({ 
                PRO_CODE: d[0],
                QTY: d[2]
            });
        }
    });

    if (!products.length) {
        Swal.fire('تنبيه', 'لم يتم تحديد كميات', 'warning');
        return;
    }

    let req = {
        'CONTROL': 'setInTransfer',
        'FROM_STO': obj.from_stoid,
        'TO_STO': obj.to_stoid,
		'COMMENT'    : $('#textbox').val(),
        'PRODUCTS': products
    };

    let res = await sendRequest(urlinv, req);
    responseMsg(res);

    if (res[0] === 'success') {
        addtable.clear().draw();
        btnBack ();
    }
}

/* =======================
   Select2
======================= */
$('#category').select2({
    placeholder: 'اختيار تصنيف',
    ajax: {
        url: `${urlApi}?CONTROL=getdropProduct-Categories`,
        dataType: 'json',
        processResults: d => ({
            results: d.map(i => ({ id: i[1], text: i[2] }))
        })
    }
});

$('#product').select2({
    placeholder: 'اختيار منتج',
    ajax: {
        url: `${urlApi}?CONTROL=Productlist`,
        dataType: 'json',
        processResults: d => ({
            results: d.map(i => ({ id: i.id, text: i.name }))
        })
    }
});
function btnBack () {
	//if ($('#selectType').is(':visible')) {
		obj.catid = [];
		obj.proid = [];
		$('.btn-app-pade .btnBack').hide();
		$('.btn-app-pade .btnSave').hide();
		$('#selectType').hide();
		$('#SelectFromInventory').show();
		$('#addTransferPro').hide();
		var addtable = $('#dtbl').DataTable();
		addtable.clear().draw();
//	}
}
function btnCancel () {
	obj.catid = '';
	obj.proid = [];
	obj.stoid = [];
	$('#selectType').show();
	$('.btn-app-pade .btnBack').show();
	$('.btn-app-pade .btnSave').hide();
	$('.btn-app-pade .btnCancel').hide();
//	$('#PurchaseList').hide();
	$('.TransferList').hide();
}









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
	/*saveTransfer = async function () {
	 	var addtable = $('#dtbl').DataTable();
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
	    	let obj = {
	    		 'CONTROL'	  : 'addTransferPro',
	    		 'from_stoid'      : obj['to_stoid'],
				 'to_stoid'      : obj['to_stoid'],
		         'COMMENT'    : $('#textbox').val(),
		         'PRODUCTS'	  : tableData
	    	}
	  
	    	if(vrf){
		        let response = ['success','ok','done'] //await sendRequest(urlcontrol, obj);
		
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
	 };*/








$(document).ready(function () {
	$('.btn-app-pade').append('<button id="btnSave" class="btn btn-app-tools btnh text-dark btnSave" style="display:none" onclick="saveTransfer();" ><i class="fas fa-save"></i><span class="btnname">حفظ</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>');
	$('.btn-app-pade').append('<button id="btnBack"         class="btn btn-app-tools btnh text-dark btnBack" style="display:none" onclick="btnBack();" ><i class="fas fa-arrow-left"></i><span class="btnname">الرجوع</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>');
	$('.btn-app-pade').append('<button id="btnCancel"       class="btn btn-app-tools btnh text-dark btnCancel" style="display:none" onclick="btnCancel();" ><i class="fas fa-times"></i><span class="btnname">الغاء</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>');
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

})