const obj = {
	stoid: null,
	stoname :'',
	proid: [],
	catid: [],
	proData: []
},

urlinv	= `../../inc/controller/inventory.control.php`,
inventoriesData =  sendRequestGET(`${urlApi}?CONTROL=getdropWarehouse`);
var row,
countingInterval = null;
formCategory = false,
formProduct = false,
title = document.title;

//DataTable
var groupColumn1 = 0; // العمود الأول BR_NAME
var groupColumn2 = 1; // العمود الثاني STO_NAME
const table = $('#products-table').DataTable({
		dom: '<"wrapper"Bt>',
    	searching: true,
    	ordering: false,
    	processing: true,
    	serverSide: false,
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
            { title: "الكميه الفعلية" , data: "SETQTY",className:"text-nowrap" }, // العمود السابع
			{
				orderable: false,
				className: 'text-center col-12 text-nowrap',
				render: (data, type, row, meta) => {
					return `
					<button id="btnEditRow" type="button" data-oid="${row['ORDER_ID']}" class="btn btn-app-row btn-xs bg-primary btnEditRow h-80 f-5 m-1">
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
async function setStockAudit() {
	 	var addtable = $('#products-table').DataTable();
	    
	    const tableData = [];
	    // جمع البيانات من الجدول
	    addtable.rows().every(function() {
	        var data   = this.data();
	        var STOQTY = parseInt(data['STO_QTY']);
			var SETQTY = parseInt(data['SETQTY']);
		console.log(data)
	        // التحقق من أن الكمية صالحة
	        if (isNaN(SETQTY) || SETQTY < 0) {
	            Toast_fire([
	                'e',
	                'تأكد من إدخال كميات صحيحة لكل منتج'
	            ]);
	            return false; // إنهاء التكرار
	        }
			
			tableData.push({
				'PRO_CODE'	: data['PRO_CODE'],
				'QTY'	: data['SETQTY'] 
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
	    if(!obj.stoid && obj.stoid >0){
	    	Swal.fire({
	            icon:	'warning',
	            title:	'نعتذر',
	            text:	'برجاء اختيار المخزن اولاً!',
	        });
	        return false;
	    }
	
	    // إرسال البيانات إلى الخادم
	    try {
	    	let objreq = {
	    		'CONTROL'	: 'setStockAudit',
	    		'STO_ID'	: obj.stoid,
		        'PRODUCTS'	: tableData
	    	}
	  
	    	
		        let response = await sendRequest(urlinv, objreq);
		
		        // التعامل مع الرد
		        responseMsg(response);
	
		        if (response[0] === 'success') {
		        	clearInterval(countingInterval);
		            addtable.clear();
		            addtable.draw();
		            btnCancel();
		            
		        }else{
		        	btnCancel();
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
	 
function showSection(id) {
	$('.form-section').hide();
	$('#' + id).fadeIn();
}
function confirmSelection(type, val) {
	if (type == 'product') {
		obj.proid = val;
		$('#product').val(null).trigger('change');
	} else if (type == 'category') {
		obj.catid = val;
		$('#category').val(null).trigger('change');
	}
	if (val == '') {
		Swal.fire({
			title: 'خطأ!', text: 'يرجى اختيار فئة او منتج واحد على الاقل', icon: 'error', confirmButtonText: 'حسنًا'
		});
		return false;
	}
	obj['CONTROL']= 'StockAudit';
	
	const req = sendRequest(urlinv ,obj);
	console.log(req);
	if(typeof req === 'string'){
		Swal.fire({
		    icon: 'info',
		    title: 'عذرا',
		    text: 'هناك مستخدم اخر يعمل على تحديث بيانات المخزن المطلوب يمكنك الانتظار حتى ينتهى من تحديث بيانات المخزن والمحاولة مرة اخرى'
		});
		return;
	}
	obj.proData = req;
	table.clear();
	table.rows.add(req);
	table.draw(); 
	table.page.len(Object.keys(req).length).draw();
	$('#selectType').hide();
	$('#PurchaseList').show();
	$('.btn-app-pade .btnBack').hide();
	$('.btn-app-pade .btnSave').show();
	$('.btn-app-pade .btnCancel').show();
	countingInterval = setInterval(function () {
	    $.post(urlinv , {
		    CONTROL: 'refreshStoreCountingLock',
		    store_id: obj.stoid
		});
	}, 30000);
	
}

function btnBack () {
	if ($('#selectType').is(':visible')) {
		obj.catid = [];
		obj.proid = [];
		$('.btn-app-pade .btnBack').hide();
		$('#selectType').hide();
		$('#SelectInventory').show();
		$('.form-section').hide();
	}
}
function btnCancel () {
	clearInterval(countingInterval);
	$.post(urlinv , {
		    CONTROL: 'unlockStore',
		    store_id: obj.stoid
	});
	obj.catid = '';
	obj.proid = [];
	obj.stoid = [];
	$('#SelectInventory').show();
	$('.btn-app-pade .btnBack').hide();
	$('.btn-app-pade .btnSave').hide();
	$('.btn-app-pade .btnCancel').hide();
	$('#PurchaseList').hide();
	$('.form-section').hide();
	
}

$(document).ready(function () {
	$('.btn-app-pade').append('<button id="btnSave" class="btn btn-app-tools btnh text-dark btnSave" style="display:none" onclick="setStockAudit();" ><i class="fas fa-save"></i><span class="btnname">حفظ</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>');
	$('.btn-app-pade').append('<button id="btnBack"         class="btn btn-app-tools btnh text-dark btnBack" style="display:none" onclick="btnBack();" ><i class="fas fa-arrow-left"></i><span class="btnname">الرجوع</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>');
	$('.btn-app-pade').append('<button id="btnCancel"       class="btn btn-app-tools btnh text-dark btnCancel" style="display:none" onclick="btnCancel();" ><i class="fas fa-times"></i><span class="btnname">الغاء</span><span class="badge badge-danger navbar-badge onumBtnPay mr-2" ></span></button>');

	//document.getElementById('by-location').addEventListener('click', () => showSection('form-location'));
	document.getElementById('by-category').addEventListener('click', () => showSection('form-category'));
	document.getElementById('by-product').addEventListener('click', () => showSection('form-product'));

	const container = document.getElementById("SelectInventory");
	//انشاء عرض المخازن
	Object.keys(inventoriesData).forEach((key, index) => {
		const [id, nun, name] = inventoriesData[key];
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
	//اختيار المخزن
	$('.inv-select').click(function() {
		obj.stoid = $(this).attr('id');
		title = document.title +' | '+$(this).text();
		$('.btn-app-pade .btnBack').show();
		$('#SelectInventory').hide();
		$('#selectType').show();
		$('#category').val(null).trigger('change');
		$('#product').val(null).trigger('change');
	})
	// حقل اختيار فئة المنتجات select2
	$('#category').select2({
		placeholder: 'اختار فئة المنتجات',
		allowClear: true,
		language: 'ar',
		selectionCssClass: 'form-control',
		width: '50%',
		dir: 'rtl',
		ajax: {
			url: `${urlApi}?CONTROL=getdropProduct-Categories`,
			dataType: 'json',
			processResults: function(data) {
				return {
					results: data.map(function(product) {
						return {
							id: product[1],
							text: product[2], // استخدام المفتاح الصحيح للنص
						};
					})
				};
			}
		},
		cache: true,
	});
	// حقل اختيار المنتج select2
	$('#product').select2({
		placeholder: 'ابحث عن المنتج',
		allowClear: true,
		language: 'ar',
		selectionCssClass: 'form-control',
		width: '50%',
		dir: 'rtl',
		ajax: {
			url: `${urlApi}?CONTROL=Productlist`,
			dataType: 'json',
			processResults: function(data) {
				return {
					results: data.map(function(product) {
						return {
							id: product.id,
							text: product.name, // استخدام المفتاح الصحيح للنص
						};
					})
				};
			}
		},
		cache: true,
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
	                Swal.showValidationMessage('الرجاء إدخال الكمية الفعليه');
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

	});

})