var timer = 0;
$(".Code").inputmask(['0-9']);
$('#btnSave').addClass('ProductAdd');
$('#btnCancel').addClass('ProductAdd');
$('#btnNew').addClass('ProductView');
$('#btnDelete').addClass('ProductView');
$('#btnSave').hide();
//===================FUNCTION'S=====================
//ارسال البيانات المدخله من المستخد  (الاسم والكود ) للتأكد من عدم وجوده بقواعد البيانات
function sendrequest2(url = urlControl, el, msg, params = {}) {
	var data_return,
	rq,
	val = el.val();
	el.parent().children().remove('a');
	$.ajaxSetup({
		cache: false
	});
	rq = $.ajax({
		url: url,
		headers: {
			//'Authorization':'Basic xxxxxxxxxxxxx',
			//'X-CSRF-TOKEN':'xxxxxxxxxxxxxxxxxxxx',
			'Content-Type': 'application/json'
		},
		async: false,
		type: 'GET',
		data: params,
		dataType: 'json'
	});
	rq.done(function (response, statuse) {
		var dataindex = el.parents('tr').attr('id');
		el.parent().children().remove('div');
		el.parent().children().remove('a');
		if (response > 0 && el.val() === val) {
			el.after('<a class="text-danger err code" style="font-size:12px;">'+msg+'</a>');
			el.addClass("is-invalid err");
			el.removeClass('is-valid');
		} else {
			el.removeClass('is-invalid err');
			el.addClass('is-valid');
			var arr = [];
			let err = 0;
			$("tr td input.code").each(function() {
				let code = ''+$(this).val();
				if ($(this).parents('tr').attr('id') !== dataindex) {
					arr.push(code);
				}
			});
			var value = ''+el.val();
			if (arr.indexOf(value) === -1) {
				el.removeClass('is-invalid err');
				el.addClass('is-valid');
			} else {
				//check double code in input elements true
				if (el.val() !== '') {
					err++
					el.parent().children().remove('a');

					el.after('<a class="text-danger err code" style="font-size:12px;">الكود الذى ادخلته مكرر ومستخدم بحقل سابق</a>');
					el.addClass("is-invalid err");
				}
			}
		}

	});
	rq.fail(function (xhr, textStatus) {
		let rq_msg = '';
		if (textStatus == 'timeout') {
			rq_msg = 'حدث خطاء : غير قادر على الوصل لقواعد البيانات'
		}
		el.parent().children().remove('a');
		el.parent().children().remove('div');
		el.after('<a class="text-danger err code" style="font-size:12px;">'+rq_msg+'</a>');
		el.addClass("is-invalid err");
		console.log("Send Request ERROR: " + textStatus);
	});
	if (data_return != undefined) {
		return data_return;
	};
};
//التأكد من ملئ جميع الحقول المطلوبة
function checkElementEmpty() {
	let err = 0;
	$('.tblreq , .req').each(function() {
		let me = $(this)
		if (me.val() == '' && !me.is(':Hidden') && me.attr('id') !== 'optionid') {
			me.addClass('is-invalid');
		} else {
			if (!me.hasClass('err')) {
				me.removeClass('is-invalid err');
			}
		}
	});
	return err;
}

function openProductInputDialog() {
    Swal.fire({
        title: 'إدخال البيانات',
        html: `
        <div class ="form-group">
            <input type="number" id="costPrice" class="swal2-input form-control" placeholder="سعر التكلفة">
            <input type="number" id="wholesalePrice" class="swal2-input form-control" placeholder="سعر الجملة">
            <input type="number" id="salePrice" class="swal2-input form-control" placeholder="سعر البيع">
            <input type="number" id="minStock" class="swal2-input form-control" placeholder="الحد الأدنى للمخزون">
            <input type="number" id="maxStock" class="swal2-input form-control" placeholder="الحد الأقصى للمخزون">
        </div>`,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'إدخال',
        cancelButtonText: 'إلغاء',
        preConfirm: () => {
            let costPrice = document.getElementById('costPrice').value;
            let wholesalePrice = document.getElementById('wholesalePrice').value;
            let salePrice = document.getElementById('salePrice').value;
            let minStock = document.getElementById('minStock').value;
            let maxStock = document.getElementById('maxStock').value;

            if (!costPrice || !wholesalePrice || !salePrice || !minStock || !maxStock) {
                Swal.showValidationMessage('يجب ملء جميع الحقول!');
                return false;
            }
			// تحديد الحقول الفارغة التي تحتاج إلى كود
		    $('.tblreq , .req').each(function() {
		        let me = $(this);
		        if (!me.is(':hidden') && me.attr('id') == 'ProductCost') {
		            me.val(costPrice).keyup() // تخزين المرجع إلى العنصر
		        }
		        if (!me.is(':hidden') && me.attr('id') == 'minProductPrice') {
		            me.val(wholesalePrice).keyup() // تخزين المرجع إلى العنصر
		        }
		        if (!me.is(':hidden') && me.attr('id') == 'ProductPrice') {
		            me.val(salePrice).keyup()  // تخزين المرجع إلى العنصر
		        }
		        if (!me.is(':hidden') && me.attr('id') == 'ProductMin') {
		            me.val(minStock).keyup()  // تخزين المرجع إلى العنصر
		        }
		        if (!me.is(':hidden') && me.attr('id') == 'ProductMax') {
		            me.val(maxStock).keyup()  // تخزين المرجع إلى العنصر
		        }
		        
		    });
			
			
			
           // return { costPrice, wholesalePrice, salePrice, minStock, maxStock };
        }
    })
}

function getlistProCodes(){
    let emptyFields = [];

    // تحديد الحقول الفارغة التي تحتاج إلى كود
    $('.tblreq , .req').each(function() {
        let me = $(this);
        if (me.val() == '' && !me.is(':hidden') && me.attr('id') == 'ProductCode') {
            emptyFields.push(me); // تخزين المرجع إلى العنصر
        }
    });

    if (emptyFields.length === 0) return; // إذا لم تكن هناك خانات فارغة، لا تفعل شيئًا

    // طلب الأكواد من السيرفر
    let codes = sendRequest(urlControl, {CONTROL: 'getNewProCodes', 'count': emptyFields.length});

    // ملء الحقول الفارغة بالأكواد المستلمة
    if (Array.isArray(codes) && codes.length === emptyFields.length) {
        emptyFields.forEach((field, index) => { 
            field.val(codes[index]); // إدخال الكود في الحقل
        });
    }
}
//التأكد من طول الكود وعدم تكرارة فى الشاشه وايضا من عدم وجوده فى قواعد البيانات  وايضا ااسم المنتج من عدم وجوده بقواعد البيانات
function elch(me) {
	let b;
	me.val();
	me.parent().children().remove('a');
	me.removeClass('text-danger err');
	me.removeClass('is-invalid err');
	me.removeClass('is-invalid');
	me.removeClass('is-valid');

	if (me.hasClass('code') && (me.val().length < 12 || me.val().length > 13)) {
		//check length value false
		if (!me.hasClass('is-invalid err')) {
			me.addClass('is-invalid err');
		}

		me.parent().children().remove('a');
		me.parent().children().remove('div');
		me.after('<a class="text-danger err code" style="font-size:12px;">*مطلوب 12 رقم او 13 كحد اقصى</a>');
	} else if (me.hasClass('code')) {
		//check length value true
		if (!me.parent().children('.containerload').is('div') && (me.attr('id') === 'ProductName' || me.attr('id') === 'ProductCode')) {
			me.parent().children().remove('a');
			me.parent().children().remove('div');
			me.after('<div class="containerload"><div class="dots"><div class="dot dot-1"></div><div class="dot dot-2"></div><div class="dot dot-3"></div><div class="dot dot-4"></div><div class="dot dot-5"></div><div class="dot dot-6"></div><div class="dot dot-7"></div></div></div>');
		}
		clearTimeout(timer);
		timer = setTimeout(function () {
			sendrequest2(urlControl, me, 'الكود اللذى ادخلته مسجل بالفعل بقواع البيانات', {
				CONTROL: 'getProductCodes', CODE_ID: me.attr('data-codeid'), CODE: ''+me.val()});
		}, 1500);
	} else if (me.attr('id') == "ProductName") {
		if (!me.parent().children('.containerload').is('div') && (me.attr('id') === 'ProductName' || me.attr('id') === 'ProductCode')) {
			me.parent().children().remove('a');
			me.parent().children().remove('div');
			me.after('<div class="containerload"><div class="dots"><div class="dot dot-1"></div><div class="dot dot-2"></div><div class="dot dot-3"></div><div class="dot dot-4"></div><div class="dot dot-5"></div><div class="dot dot-6"></div><div class="dot dot-7"></div></div></div>');
		}
		clearTimeout(timer);
		timer = setTimeout(function () {
			sendrequest2(urlApi+'?CONTROL=viewProductName&ProductName='+me.val()+'&Pro_id='+me.attr('data-id'), me, 'الاسم الذى ادخلته مسجل بالفعل بقواعد البيانات');
		}, 1500);
	} else if (me.attr('id') === "ProductPrice") {
		b = me.parent().prev().children('input.cost');
		if (Number(me.val()) < 0) {
			me.after('<a class="text-danger err code" style="font-size:12px;">لايمكن ادخال قيمه اقل من صفر</a>');
			me.addClass('is-invalid err');
		} else if (Number(me.val()) < Number(b.val())) {
			me.after('<a class="text-danger err code" style="font-size:12px;">خطاء : سعر البيع اقل من سعر الشراء</a>');
			me.addClass('is-invalid err');
		}
	} else if (me.attr('id') === "ProductCost") {
		b = me.parent().next().children('input.price');
		if (Number(me.val()) < 0) {
			me.after('<a class="text-danger err code" style="font-size:12px;">لايمكن ادخال قيمه اقل من صفر</a>');
			me.addClass('is-invalid err');
		} else if (Number(me.val()) > Number(b.val()) && b.val() !== '' && me.val() !== '') {
			if (!b.parent().children('.price').is('a')) {
				b.after('<a class="text-danger err price" style="font-size:12px;">خطاء : سعر البيع اقل من سعر الشراء</a>');
			}
			b.addClass('is-invalid err');
		} else {
			b.parent().children().remove('a');
			b.removeClass('is-invalid err');
		}
	} else if (me.attr('id') === "ProductMax") {
		b = me.parent().prev().children('input.min');
		if (Number(me.val()) < 0) {
			me.after('<a class="text-danger err code" style="font-size:12px;">لايمكن ادخال قيمه اقل من صفر</a>');
			me.addClass('is-invalid err');
		} else if (Number(me.val()) < Number(b.val())) {
			me.after('<a class="text-danger err price" style="font-size:12px;">خطاء : اقصى كمية للمخزون اقل من الحد الادنى  </a>');
			me.addClass('is-invalid err');
		}
	} else if (me.attr('id') === "ProductMin") {
		b = me.parent().next().children('input.max');
		if (Number(me.val()) < 0) {
			me.after('<a class="text-danger err code" style="font-size:12px;">لايمكن ادخال قيمه اقل من صفر</a>');
			me.addClass('is-invalid err');
		} else if (Number(me.val()) > Number(b.val()) && b.val() !== '' && me.val() !== '') {
			if (!b.parent().children('.price').is('a')) {
				b.after('<a class="text-danger err price" style="font-size:12px;">خطاء : اقصى كمية للمخزون اقل من الحد الادنى  </a>');
			}
			b.addClass('is-invalid err');
		} else {
			b.parent().children().remove('a');
			b.removeClass('is-invalid err');
		}
	}

}
//---------------------------------------------
function checkProductCodeAndName() {

	$('.tblreq , .req').change(function () {
		if ($(this).attr('id') !== 'PER_BOOKING' || $(this).attr('id') !== 'PRO_VISBLE') {
			elch($(this));
		}
	})
	$('.tblreq , .req').keyup(function() {
		//عند الضغط على ادخال النص فى اى حقل يتم حذف كلاس الخطاء
		if ($(this).attr('id') !== 'PER_BOOKING' || $(this).attr('id') !== 'PRO_VISBLE') {
			$('#btnSave').attr('disabled', false)
			elch($(this));
		}

	});
	return $('.is-invalid').length;
}
//public function
$(function () {
	$('.pro-h1').hide();
	// Summernote
	$('.textareadescription').summernote();
	$('.select2').select2({
		width: '100%',
		dir: 'rtl'
	});
	//============ select-type chenge ============================
	$('#ProductType').change(function() {
		// let option = $();
		if ($(this).val() == 2) {
			$(this).parent().removeClass('col-sm-6');
			$(this).parent().addClass('col-sm-12');
			$(this).removeClass('is-invalid');
			$(this).removeClass('is-valid');
			$('.one_product').hide(); // اخفاء كل الحقول المخصصه للمنتج الواحد
			$('#btnSave').attr('disabled', true);
			let op = '<div id="fpop" class="form-group fpop col-sm-12">';
			op += '<label for="Productoption">اختار صفات المنتج</label>';
			op += `<select id="Productoption" onchange="elmentoption('#Productoption')" class="form-control fpop select2" multiple>`;
			op += '</select></div>';
			$('#selectoption').append(op);
			let optgroup = dropdownData({
				'datatbl': 'getdropProductOptionGroup'
			});
			$('#Productoption').html(optgroup);
			$('.select2').select2({
				width: '100%',
				dir: 'rtl'
			});
		} else {
			$(this).parent().removeClass('col-sm-12');
			$(this).parent().addClass('col-sm-6');
			$('.one_product').show(); // اظهار كل الحقول المخصصة للمنتج الواحد
			//$('.option').remove(); //حذف الحقول المخصصه لخصائص المنتج
			$('#selectoption').html('');
			$('#btnSave').attr('disabled', false);
		}


	})

})
//function elmentoption chenge
function elmentoption(x) {
	let a = [], b, c = {}, d = [], tdarr = [], data = '', elmentoption = $('#fpop');
	//elmentoption.html('');
	//$('.btntbl15').remove();
	function dataarr(c) {
		b = sendRequest(urlControl, {CONTROL: 'getdropProductOptionValue', ID: c})
	}
	for (let i = 0; i < $(x).children("option:selected").length; i++) {
		a[i] = [$(x).children("option:selected").eq(i).val(),
			$(x).children("option:selected").eq(i).text()];
	};
	for (var row = 0; row < a.length; row++) {
		data += '<div class="form-group col-sm-12  option fpop">';
		data += '<label class="form-group dataoption">'+ a[row][1] +'</label>';
		data += '<select id="option'+row+'"data-option="option" data-id ="' + a[row][0] + '" class="form-control select3 item" multiple>';
		dataarr(a[row][0])
		for (var col = 0; col < b.length; col++) {
			data += '<option data-name="'+a[row][1]+'" data-id="'+b[col][1]+'" value="'+b[col][0]+'" selected>'+b[col][2]+'</option>';
		}
		d[row] = b;
		data += '</select>';
	}
	//data += '</div>';
	data += '<div id="tblcontant1" class="form-group text-center mt-3 option">'
	data += '<button id="addtbl" onclick="btnCreateTbl()" type="button" class="btn btn-block btn-info">انشاء جدول بالبيانات المحدده</button>';
	//data += '<button id="addtble" onclick="btnCreateTbl()" type="button" class="btn btn-block btn-info">انشاء جدول بالبيانات المحدده</button>';
	
	data += '</div>';
	$('.option').remove();
	elmentoption.after(data);

	$('.select3').select2({
		width: '100%',
		dir: 'rtl'
	});
	$('.select3').css('z-index', '0');
};
//function btnAddRowTbl
function btnAddRowTbl() {
	let a = [],
	b,
	c = {},
	d = [],
	tdarr = [],
	data = '',
	elmentoption = $('#fpop'),
	checki = 0;
	b = sendRequest(urlControl, {
		CONTROL: 'getdropProductOptionValue', PRO_ID: $("#ProductName").attr("data-id")});
	for (let v = 0; v < b[0].length; v++) {
		if (a.indexOf(b[0][v][0]) == -1) {
			a.push(b[0][v][0]);
			d.push(b[0][v][1])
		}
	}
	for (var row = 0; row < a.length; row++) {
		data += '<div class="form-group col-sm-12  option fpop">';
		data += '<label class="form-group dataoption">'+ d[row] +'</label>';
		data += '<select id="option'+row+'"data-option="option" data-id ="' + a[row] + '" class="form-control select3 item" multiple>';
		for (var col = 0; col < b[1].length; col++) {
			if (b[1][col][1] == a[row]) {
				data += '<option data-name="'+d[row]+'" data-id="'+b[1][col][1]+'" value="'+b[1][col][0]+'" selected>'+b[1][col][2]+'</option>';
			}
		}
		data += '</select></div>';
	}
	Swal.fire({
		title: 'أضافة صفة جديدة للمنتج',
		html: data,
		allowOutsideClick: true,
		showCancelButton: true,
		confirmButtonColor: '#3085d6',
		cancelButtonColor: '#d33',
		confirmButtonText: 'التالى',
		cancelButtonText: 'إلغاء',
		focusConfirm: true,
		didOpen: function() {
			$('.select3').select2({
				width: '100%',
				dir: 'rtl'
			});
			$('.select3').css('z-index', '0');

		},
		preConfirm: function () {

			$('.item').each(function() {
				if ($(this).val() == '') {
					checki++;
					$(this).next('span').children('span').addClass('is-invalid');
				} else {
					$(this).removeClass('is-invalid');
				}
			});

			let row_select_option = [], // set data one row
			data_array = [],

			datatbl = ''; // var html
			$('#btnSave').attr('disabled',
				false); // disable bottun Save
			$('tblreq').each(function () {
				if ($(this).attr('id') == 'optionid') {}
			});
			$('.item').each(function() {
				// loop  rows item tr in table
				row_select_option = []; //Resett data array
				for (let i = 0; i < $(this).children("option:selected").length; i++) {
					if (data_array.length !== 0) {
						for (let x = 0; x < data_array.length; x++) {
							let oid,
							oname;
							oid = data_array[x][0].trim() + ',' + $(this).children("option:selected").eq(i).val().trim();
							oname = data_array[x][1].trim() +' - ' +$(this).children("option:selected").eq(i).text().trim();
							row_select_option.push([oid, oname]);
						}
					} else {
						row_select_option.push([$(this).children("option:selected").eq(i).val(), $(this).children("option:selected").eq(i).text()]);
					}
				}
				data_array = row_select_option; // set data in var all rows
			});
			//alert(checki);
			if (checki > 0) {
				Swal.showValidationMessage('جميع الحقول مطلوبة');
			}

			return data_array;

		}
	}).then((result) => {
		let vf = '';

		if (result.isConfirmed) {
			for (let z = 0; z < result['value'].length; z++) {
				let double = 0
				$('.opid').each(function() {
					if ($(this).attr('data-optionid') == result['value'][z][0]) {
						double++;
					}
				});
				if (double == 0) {
					vf += '<tr id="'+z+'" class="tbltr">';
					vf += '<td class="col-4 "><input type="Number" class="form-control pro-data new code tblreq" id="ProductCode" style="width:100% !important;"></td>';
					vf += '<td ><a id="optionid" data-optionid="'+ result['value'][z][0] +'" class=" pro-data new optionid opid tblreq">' + result['value'][z][1] + '</a></td>';
					vf += '<td ><input type="Number" class="form-control pro-data new tblreq cost" id="ProductCost" ></td>';
					vf += '<td ><input type="Number" class="form-control pro-data new tblreq minprice" id="minProductPrice"></td>';
					vf += '<td ><input type="Number" class="form-control pro-data new tblreq price" id="ProductPrice"></td>';
					vf += '<td ><input type="Number" class="form-control pro-data new tblreq min" id="ProductMin"></td>';
					vf += '<td ><input type="Number" class="form-control pro-data new tblreq max" id="ProductMax"></td>';
					vf += '<td ><button id="btnDelRow" type="button" data-id="new" class="btn btn-danger btn-xs Edit"><i class="fas fa-trash"></i></button></td>';
					vf += '</tr>';
				}
			}

			//alert($('#addtbl').parents('tr'));
			$('#addtbl').parents('tr').before(vf);
			checkProductCodeAndName();
		}
	});
}
//function btnCreateTbl
function btnCreateTbl() {
	$('.fpop').hide();

	let row_select_option = [], // set data one row
	data_array = [], //var all rows
	datatbl = ''; // var html

	$('#tblcard').remove(); // remove table items
	$('#btnSave').attr('disabled',false); // disable bottun Save
	
	$('.item').each(function() {
		// loop  rows item tr in table
		row_select_option = []; //Resett data array
		for (let i = 0; i < $(this).children("option:selected").length; i++) {
			if (data_array.length !== 0) {
				for (let x = 0; x < data_array.length; x++) {
					let oid,
					oname;
					oid = data_array[x][0].trim() + ',' + $(this).children("option:selected").eq(i).val().trim();
					oname = data_array[x][1].trim() +' - ' +$(this).children("option:selected").eq(i).text().trim();
					row_select_option.push([oid, oname]);
				}
			} else {
				row_select_option.push([$(this).children("option:selected").eq(i).val(), $(this).children("option:selected").eq(i).text()]);
			}
		}
		data_array = row_select_option; // set data in var all rows
	});
	//$('#tblcard').remove();
	datatbl = `<div class="form-group text-center mt-3">
				    <button id="btnGenerateCodes" class="btn btn-pulse m-2" onclick="getlistProCodes()">
				        <i class="fas fa-barcode"></i> تكويد تلقائى
				    </button>
				    <button id="btnCreatePrice" class="btn btn-pulse m-2" onclick="openProductInputDialog()">
				        <i class="fas fa-tags"></i> ملئ البيانات دفعه واحده
				    </button>
				</div>`;

	datatbl += '<div id="tblcard" class="card card-primary card-outline" style="display: block;">';
	datatbl += '<div id="tblbody" class="card-body">';
	datatbl += '<table class="cell-border table table-striped table-bordered table-responsive text-center text-nowrap" style="width:100%"><thead><tr>';
	datatbl += '<th>كود الصنف</th>';
	datatbl += '<th>صفات المنتج</th>';
	datatbl += '<th>سعر التكلفة</th>';
	datatbl += '<th>سعر الجمله</th>';
	datatbl += '<th>سعر البيع</th>';
	datatbl += '<th>اقل مخزون</th>';
	datatbl += '<th>اعلى مخزون</th>';
	datatbl += '<th>حذف</th>';
	datatbl += '</tr></thead><tbody id="itemtbody">';
	for (let z = 0; z < data_array.length; z++) {
		datatbl += '<tr id="'+z+'" class="tbltr">';
		datatbl += '<td class="col-4"><input type="Number" class="form-control pro-data code tblreq" id="ProductCode" style="width:100% !important;"></td>';
		datatbl += '<td ><a id="optionid" data-optionid="'+ data_array[z][0] +'" class=" pro-data new optionid tblreq">' + data_array[z][1] + '</a></td>';
		datatbl += '<td ><input type="Number" class="form-control pro-data tblreq cost" id="ProductCost" ></td>';
		datatbl += '<td ><input type="Number" class="form-control pro-data tblreq minprice" id="minProductPrice"></td>';
		datatbl += '<td ><input type="Number" class="form-control pro-data tblreq price" id="ProductPrice"></td>';
		datatbl += '<td ><input type="Number" class="form-control pro-data tblreq min" id="ProductMin"></td>';
		datatbl += '<td ><input type="Number" class="form-control pro-data tblreq max" id="ProductMax"></td>';
		datatbl += '<td ><button id="btnDelRow" type="button" data-id="new" class="btn btn-danger btn-xs btnDelRow"><i class="fas fa-trash"></i></button></td>';
		datatbl += '</tr>';
	}
	datatbl += '</tbody></table></div></div>';
	$('#selectoption').append(datatbl);
	//$('.dataoption').hide();
	$('.btnhide').hide();
	$('.item').hide();
	$('#addtbl').addClass('btn-danger');
	$('#addtbl').removeClass('btn-info');
	$('#addtbl').text('حذف الجدول الحالى والرجوع للتعديل');
	$('#addtbl').attr('onclick', 'btnCancelTbl()');
	//---------------
	checkProductCodeAndName();

}
//function btnCancelTbl
function btnCancelTbl() {
	$('.fpop').show();
	$('.req ,.q').each(function () {
		if ($(this).attr('id') !== "ProductCat") {
			$(this).val('');
		}
		$(this).removeClass('text-danger');
	});
	$('#addtbl').addClass('btn-info');
	$('#addtbl').removeClass('btn-danger');
	$('#addtbl').text('انشاء جدول بالبيانات المحدده');
	$('#addtbl').attr('onclick',
		'btnCreateTbl()');
	$('#tblcard').remove();
	$('#btnSave').attr('disabled',
		true);
	//$('.note-editable').html('');
}
//=================Save btn function==============
// دالة لإعادة ضبط حالة الزر
function resetButtonState(button) {
	button.removeClass('is-valid is-invalid').attr('disabled',
		true);
}
// دالة تهيئة بارامترات الـ Ajax
function initAjaxParams() {
	return {
		'ProductItems': [],
		'ProNewItems': [],
		'ProductOption': []
	};
}
// دالة لتحديد نوع العملية (إضافة أو تحديث)
function setControlAction(ajaxpar1) {
	if ($('#ProductName').attr('data-id') !== 'new') {
		ajaxpar1['CONTROL'] = 'updateProduct';
		ajaxpar1['Pro_id'] = $('#ProductName').attr('data-id');
	} else {
		ajaxpar1['CONTROL'] = 'addProduct';
	}
}
// دالة لجمع بيانات المنتج من الحقول
function collectProductData(ajaxpar1) {
	ajaxpar1['ProductName'] = $("#ProductName").val();
	ajaxpar1['ProductCat'] = $("#ProductCat").val();
	ajaxpar1['ProductLength'] = $("#ProductLength").val();
	ajaxpar1['ProductWidth'] = $("#ProductWidth").val();
	ajaxpar1['ProductHeight'] = $("#ProductHeight").val();
	ajaxpar1['ProductWeight'] = $("#ProductWeight").val();
	ajaxpar1['ProductDescription'] = $("#ProductDescription").summernote('code');
	ajaxpar1['PRO_VISBLE'] = $('#PRO_VISBLE').is(':checked') ? 1: 0;
	ajaxpar1['PER_BOOKING'] = $('#PER_BOOKING').is(':checked') ? 1: 0;
}
// دالة لجمع بيانات عناصر المنتج وخياراته
function collectProductItems(ajaxpar1, arritem, arroption) {
	if (!$('.one_product').is(':Hidden')) {
		arritem = [
			$('#ProductCode').val(),
			$('#ProductCost').val(),
			$('#minProductPrice').val(),
			$('#ProductPrice').val(),
			$('#ProductMin').val(),
			$('#ProductMax').val(),
			$('#ProductCode').attr('data-codeid')
		];
		console.log(arritem)
		handleNewOrExistingItems(ajaxpar1, arritem);
	} else {
		collectProductTableData(ajaxpar1, arritem, arroption);
	}
}
// دالة للتعامل مع البيانات الجديدة أو المحدثة
function handleNewOrExistingItems(ajaxpar1, arritem) {
	if (ajaxpar1['CONTROL'] === 'addProduct') {
		ajaxpar1['ProNewItems'].push(arritem);
	} else {
		ajaxpar1['ProductItems'].push(arritem);
	}
}
// دالة لجمع بيانات الجدول الخاصة بالمنتج
function collectProductTableData(ajaxpar1, arritem, arroption) {
	let codeid;
	$('.pro-data').each(function () {
		let e = $(this);
		if (e.is('#ProductCode, #ProductCost, #minProductPrice, #ProductPrice, #ProductMin, #ProductMax')) {
			arritem.push(e.val());
			if (e.attr('data-codeid')) {
				codeid = e.attr('data-codeid');
			}

			if (arritem.length === 6) {
				handleTableItems(ajaxpar1, arritem, codeid);
				codeid = null;
			}
		} else if (e.is('#optionid')) {

			handleOptionItems(e, arroption, ajaxpar1);
		}
	});
}
// دالة للتعامل مع عناصر الجدول
function handleTableItems(ajaxpar1, arritem, codeid) {
	if (codeid) {
		const newItem = [...arritem]; // نسخ المصفوفة
		newItem.push(codeid);
		ajaxpar1['ProductItems'].push(newItem); // إضافة النسخة إلى ajaxpar1

	} else {
		ajaxpar1['ProNewItems'].push([...arritem]); // نسخ المصفوفة بدلاً من المرجع
	}
	arritem.length = 0; // إفراغ المصفوفة

}
// دالة للتعامل مع عناصر الخيار
function handleOptionItems(e, arroption, ajaxpar1) {
	let code = e.parents('tr').find('td:eq(0) input').val();
	arroption.push(code, e.attr('data-optionid').split(','));
	ajaxpar1['ProductOption'].push([...arroption]);
	arroption.length = 0; // إفراغ المصفوفة
}
// دالة لعرض رسالة الخطأ
function showError(msg, x, button) {
	button.attr('disabled', false);
	if (x > 0) {
		msg += '<li>جميع الحقول المحدده بالون الاحمر مطلوبة</li>';
	}
	if (msg) {
		Toast.fire({
			position: 'top-end',
			type: 'warning',
			html: '<ul class="text-danger">' + msg + '</ul>',
			customClass: {
				text: 'text-danger'
			},
			//showConfirmButton: true,
			timer: 2500
		});
	}
}
// دالة لإرسال الطلب إلى الخادم
function sendAjaxRequest(urlControl, ajaxpar1) {
	console.log(ajaxpar1)
	let req = sendRequest(urlControl, ajaxpar1);
	handleRequestResponse(req);
}
// دالة للتعامل مع استجابة الطلب
function handleRequestResponse(req) {
	function showToast(type, title, msg, timer = 2500) {
		Toast.fire({
			position: 'top-end',
			type: type,
			title: title,
			html: '<ul class="border-success">' + msg + '</ul>',
			customClass: {
				popup: 'border-success'
			},
			//showConfirmButton: true,
			timer: timer
		});
	}

	if ((typeof req === 'number' && req > 0) || (typeof req === 'Object' && req[0] == 'success')) {
		$("#custom-tabs-three-productdata-tab").click();
		showToast('success', 'نجح', '<li class="text-success">تم حفظ الصنف بنجاح</li>');
		table.ajax.reload();
		$('#btnCancel').click();
	} else if (req === 'error23000') {
		showToast('warning', 'خطاء', 'اسم الصنف مسجل بالفعل بقواعد البيانات');
	} else {
		showToast('warning', 'خطاء', req);
	}
}
//==================document ready==================
$(document).ready(function () {
	//عرض بيانات المنتجات بالجدول
	table = $('#tbl').DataTable({
		scrollY: '70vh',
		scrollX: true,
		processing: true,
		serverSide: true,
		columns: [{
			targets: 0, orderable: false, searchable: false, title: '<input id=select-all type=checkbox>'
		},
			{
				targets: 1, orderable: false, className: 'col fild f1', title: 'الصورة'
			},
			{
				targets: 2, orderable: true, className: 'col fild f2', title: 'المنتج'
			},
			{
				targets: 3, orderable: true, className: 'col fild f3', title: 'القسم'
			},
			{
				targets: 4, orderable: true, className: 'col fild f4', title: 'المخزون'
			},
			{
				targets: 5, orderable: true, className: 'col fild f5', title: 'الحالة'
			},
			{
				targets: 6, orderable: true, className: 'col fild f6', title: 'حجز مسبق'
			},
			{
				targets: 7, orderable: true, className: 'col fild f7', title: 'السعر'
			},
			{
				targets: 8, orderable: false, title: 'تحرير', orderable: false
			}],
		columnDefs: [{
			className: "text-nowarp text-center", targets: "_all"
		}],
		ajax: {
			url: urlApi+'?CONTROL=getlistProducts',
			type: 'post',
			async: true,
			dataType: 'json',

		},
	});
	//on click btn new
	$(document).on('click',
		'#btnNew',
		function () {
			const Proid = $(this).attr('data-id') ;
			$('.tblreq ,.req').each(function () {
				$(this).addClass('new');
				$(this).parent().children().remove('a');
				$(this).removeClass("is-invalid");
				$(this).removeClass('is-valid');
			});
			$('#ProductCode').parent().addClass('col-sm-6');
			$('#ProductCode').parent().removeClass('col-sm-12');
			btnCancelTbl();
			$('#selectoption').html(''); // delete data table  item
			let category = '<option value="">اختار القسم</option>' + dropdownData({
				'datatbl': 'getdropProduct-Categories'
			});
			$('#ProductCat').html(category);
			$('.ProductAdd').show();
			$('.ProductView').hide();
			$('#ProductView').hide();
			$('#ProductView').removeClass('show');
			$('#ProductAdd').show();
			$('#ProductAdd').addClass('show');
			//$('#ProductAdd').children('.nav-link').removeClass('active');
			$('custom-tabs-three-productdata-tab').addClass('active')

			$('.note-editable').html('');
			$('custom-tabs-three-productdata-tab').addClass('active')
			if ($(this).attr('data-id') === 'new') {
				//new product
				$('#ProductName').attr('data-id', Proid);
				$('.one_product').show();
				$('#ProductType').val(1);
				$('#PRO_VISBLE').attr('checked', true);
				$('#PER_BOOKING').attr('checked', true);
				//remove class
				$('#ProductType').parent().removeClass('col-sm-12');
				$('#ProductView').removeClass('show');
				//add class
				$('#ProductAdd').addClass('show');
				$('#ProductType').parent().addClass('col-sm-6');
				//Hidden elements
				$('#btnDelete').hide(); //Hidden button delete
				$('.pro-h1').hide();
				$('#ProductView').hide();
				$('#btnSave').attr('disabled', false); // undisable button save

			} else {
				//edit product
				$('#ProductTy').hide();
				let getProduct = sendRequest(urlControl, {
					CONTROL: 'viewProduct', Pro_id: $(this).attr('data-id')});
				//set data form view
				$('#ProductName').attr('data-id', Proid);
				$('#ProductName').val(getProduct['PRODUCT'][Proid]['PRO_NAME']);
				$('#ProductCat').val(getProduct['PRODUCT'][Proid]['CAT_ID']);
				if (getProduct['PRODUCT'][Proid]['PRO_VISBLE'] == 1) {
					$('#PRO_VISBLE').prop('checked', true);
				} else {
					$('#PRO_VISBLE').prop('checked', false);
				}
				if (getProduct['PRODUCT'][Proid]['PER_BOOKING'] == 1) {
					$('#PER_BOOKING').prop('checked', true);
				} else {
					$('#PER_BOOKING').prop('checked', false);
				}
				$('.note-editable').html(getProduct['PRODUCT'][Proid]['PRO_DESCRIPTION']);
				$('#ProductLength').val(getProduct['PRODUCT'][Proid]['LENGTH']);
				$('#ProductWidth').val(getProduct['PRODUCT'][Proid]['WIDTH']);
				$('#ProductHeight').val(getProduct['PRODUCT'][Proid]['HEIGHT']);
				$('#ProductWeight').val(getProduct['PRODUCT'][Proid]['WEIGHT']);
				//alert(getProduct['CODES'][$(this).attr('data-id')][0]['OPTION']);
				if (getProduct['CODES'][Proid].length === 1 && getProduct['CODES'][Proid][0]['OPTION'] === null && getProduct['CODES'][Proid]['OPTION'] == null) {
					// this edit view one item in product
					$('.one_product').show()
					$('#ProductCode').val(getProduct['CODES'][Proid][0]['PRO_CODE']);
					$('#ProductCode').attr('data-codeid', getProduct['CODES'][Proid][0]['PRODUCT_CODE_ID']);
					$('#ProductCode').parent().addClass('col-sm-12');
					$('#ProductCode').parent().removeClass('col-sm-6');
					$('#ProductCost').val(getProduct['CODES'][Proid][0]['CODE_COST_PRICE']);
					$('#minProductPrice').val(getProduct['CODES'][Proid][0]['LOWEST_PRICE']);
					$('#ProductPrice').val(getProduct['CODES'][Proid][0]['CODE_PRICE']);
					$('#ProductMin').val(getProduct['CODES'][Proid][0]['CODE_MIN_STOCK']);
					$('#ProductMax').val(getProduct['CODES'][Proid][0]['CODE_MAX_STOCK']);
					$('.tblreq ,.req').each(function () {
						$(this).addClass('text-danger')
					});
					$('.tblreq ,.req').change(function() {
						$(this).addClass('chenhed');
						$(this).removeClass('text-danger');
						$('#btnSave').attr('disabled', false);
					});
					$('input :checkbox').click(function() {
						$('#btnsave').attr('disabled', false)
					});
				} else {
					//this view all items in product
					$('.one_product').hide()
					$('#tblcard').remove(); // remove any data
					$('.item').hide();
					//----------------create data table view items edite--------------------------
					datatbl = `
					<div class="form-group text-center mt-3">
					    <button id="btnGenerateCodes" class="btn btn-pulse m-2" onclick="getlistProCodes()">
					        <i class="fas fa-barcode"></i> تكويد تلقائى
					    </button>
					    <button id="btnCreatePrice" class="btn btn-pulse m-2" onclick="openProductInputDialog()">
					        <i class="fas fa-tags"></i> ملئ البيانات دفعه واحده
					    </button>
					</div>
					<div id="tblcard" class="card card-primary card-outline" style="display: block;">
					<div id="tblbody" class="card-body">
					<table id="tbledit" class="cell-border table table-striped table-bordered table-responsive text-center text-nowrap" style="width:100%">
					<thead>
					<tr>
					<th>كود الصنف</th>
					<th>صفات المنتج</th>
					<th>سعر التكلفة</th>
					<th>سعر الجمله</th>
					<th>سعر البيع</th>
					<th>اقل مخزون</th>
					<th>اعلى مخزون</th>
					<th>حذف</th>
					</tr>
					</thead>
					<tbody id="itemtbody">`;
					for (let z = 0; z < getProduct['CODES'][Proid].length; z++) {
						datatbl += `<tr id="${getProduct['CODES'][Proid][z]['PRODUCT_CODE_ID']}" data-code="${getProduct['CODES'][Proid][z]['PRO_CODE']}" data-option="${getProduct['CODES'][Proid][z]['OPTION']}" class="tbltr">
						<td class="col-3">
						<input type="Number" class="form-control pro-data code text-danger tblreq" id="ProductCode" data-codeid="${getProduct['CODES'][Proid][z]['PRODUCT_CODE_ID']}" value="${getProduct['CODES'][Proid][z]['PRO_CODE']}" style="width:150px !important;">
						</td>
						<td>
						<a id="optionid" data-optionid="${getProduct['CODES'][Proid][z]['OPTION_VALUE_ID']}" data-CODEID="" class=" pro-data opid tblreq">${getProduct['CODES'][Proid][z]['OPTION']}</a>
						</td>
						<td>
						<input data-input="barcode" type="Number" class="form-control pro-data text-danger tblreq" id="ProductCost" value="${getProduct['CODES'][Proid][z]['CODE_COST_PRICE']}" >
						</td>
						<td>
						<input type="Number" class="form-control pro-data text-danger tblreq" id="minProductPrice" value="${getProduct['CODES'][Proid][z]['LOWEST_PRICE']}">
						</td>
						<td>
						<input type="Number" class="form-control pro-data text-danger tblreq" id="ProductPrice" value="${getProduct['CODES'][Proid][z]['CODE_PRICE']}">
						</td>
						<td>
						<input type="Number" class="form-control pro-data text-danger tblreq" id="ProductMin" value="${getProduct['CODES'][Proid][z]['CODE_MIN_STOCK']}">
						</td>
						<td>
						<input type="Number" class="form-control pro-data text-danger tblreq" id="ProductMax" value="${getProduct['CODES'][Proid][z]['CODE_MAX_STOCK']}">
						</td>
						<td >
						<button id="btnDelRow" type="button" data-id="${getProduct['CODES'][Proid][z]['PRODUCT_CODE_ID']}" class="btn btn-danger btn-xs Edit"><i class="fas fa-trash"></i></button>
						</td>
						</tr>`;
					}
					datatbl += `</tbody>
					<tfoot>
					<tr>
					<td colspan="7">
					<button id="addtbl" onclick="btnAddRowTbl()" type="button" class="btn btn-block btn-info">اضافة صفة جديدة للمنتج </button>
					</td>
					</tr>
					</tfoot>
					</table>
					</div>
					</div>`;
					$('#selectoption').append(datatbl);
					//----------------end create data table edite--------------------------

				}
				$('.pro-data').change(function() {
					$('#btnSave').prop('disabled', false)
				});
			}

			checkProductCodeAndName();
		});
	// on click btn cancel
	$(document).on('click',
		'#btnCancel',
		function () {
			$('.tblreq ,.req').each(function () {
				$(this).parent().children().remove('a');
				$(this).removeClass("is-invalid");
				$(this).removeClass('is-valid');
			});
			$('#btnDelete').attr('disabled', true);
			$('#ProductTy').show(); $(this).hide();
			$('#btnNew').show();
			$('#btnSave').hide();
			$('#btnSave').attr('disabled', false)
			$('#btnDelete').show();
			$('#ProductAdd').removeClass('show');
			$('#ProductAdd').hide();
			$('#ProductView').show();
			$('#ProductView').addClass('show');
			$('#selectoption').html('');
			$('.req').each(function() {
				$(this).val('');
			});
			$('.req,.tblreq').each(function () {
				$(this).removeClass('new');
			});
			table.ajax.reload();
		});
	//on click btn save
	$(document).on('click',
		'#btnSave',
		function () {
			resetButtonState($(this));

			let ajaxpar1 = initAjaxParams(),
			arritem = [],
			arroption = [],
			msg = '',
			x = checkElementEmpty();

			// تحديد إذا كان المنتج تحديث أو إضافة جديدة
			setControlAction(ajaxpar1);

			// جمع بيانات المنتج
			collectProductData(ajaxpar1);

			// التحقق من حالة الصنف والعناصر المرتبطة
			collectProductItems(ajaxpar1, arritem, arroption);

			ajaxpar1['ProductItems'] = JSON.stringify(ajaxpar1['ProductItems']);
			ajaxpar1['ProNewItems'] = JSON.stringify(ajaxpar1['ProNewItems']);
			ajaxpar1['ProductOption'] = JSON.stringify(ajaxpar1['ProductOption']);

			// التحقق من صحة بيانات المنتج
			x = checkProductCodeAndName();
			if (x > 0 || msg !== '') {
				showError(msg, x, $(this));
			} else {
				// إرسال الطلب إلى الخادم
				sendAjaxRequest(urlControl, ajaxpar1);
			}

			msg = '';
			x = 0;
		});
	//on click btn delete
	$(document).on('click',
		'#btnDelete',
		function () {
			let data_return;
			let id = [];
			var el = '';
			$('tbody td input:checkbox:checked').each(function () {
				if ($(this).is(':checked') && $(this).attr('id') !== 'select-all') {
					id.push($(this).attr('id'));
					el += '<li class=text-left>' +$(this).parents('tr').children().eq(2).text() +'</li>';
					// el += ' - ' + $(this).parents('tr').children().eq(2).text() + '</li>';
				};
			});
			var delParams = {}
			delParams['CONTROL'] = 'delete'+Go;
			delParams[Go+'_id'] = id.join();
			Swal.fire({
				title: 'هل انت واثق من حذف البيانات التالية؟',
				html: '<div class=card-body><ul>' + el + '</ul></div><div class=swal2-validation-message id=swal2-validation-message style=font-size:14px; display: flex; margin-left: -20px; margin-right: -20px;> فى حال تأكيد الحذف لم يتم استعادة البيانات مرة اخرى</div>',
				icon: 'question',
				allowOutsideClick: false,
				showCancelButton: true,
				confirmButtonColor: '#3085d6',
				cancelButtonColor: '#d33',
				confirmButtonText: 'تأكيد الحذف',
				cancelButtonText: 'إغلاق',
				footer: ''
			}).then((result) => {
				if (result.value) {
					let data_return = sendRequest(urlControl, delParams);
					if (typeof data_return == 'number' && data_return > 0) {
						table.ajax.reload();
						Toast.fire({
							position: 'top-end',
							type: 'success',
							title: '<spen class=bg-warning>نجح تنفيذ الاجراء</spen>',
							html: '<spen class=text-white6>تم حذف البيانات المحدده </spen>',
							customClass: {
								//popup:' text-white',
								text: 'text-black'
							},
							showConfirmButton: false,
							timer: 5000
						});
					} else if (typeof data_return == 'string' && data_return == 'error23000') {
						Toast.fire({
							position: 'top-end',
							type: 'erorr',
							title: '<spen class=text-white>حدث خطاء<spen>',
							html: '<spen class=text-white>لم يتم الحذف ERROR : المنتج له ارصده افتتاحية بالمخزن او مرتبط بأوامر بيع ولا يمكن حذفه<spen>',
							//  text : 'لم ننجح فى تنفيذ اجراء حذف البيانات كود الخطاء : '+ data_return,
							customClass: {
								popup: 'bg-danger bg-gradient text-white',
								text: 'text-white'
							},
							showConfirmButton: false,
							timer: 7500
						});
					} else {
						Toast.fire({
							position: 'top-end',
							type: 'erorr',
							title: '<spen class=text-white>حدث خطاء<spen>',
							html: '<spen class=text-white>لم يتم الحذف ERROR : '+data_return+'<spen>',
							//  text : 'لم ننجح فى تنفيذ اجراء حذف البيانات كود الخطاء : '+ data_return,
							customClass: {
								popup: 'bg-danger bg-gradient text-white',
								text: 'text-white'
							},
							showConfirmButton: false,
							timer: 7500
						});
					}
				} else {
					Toast.fire({
						position: 'top-end',
						title: '<spen class="">تم الإلغاء<spen>',
						html: '<spen class="">جميع البيانات امنه<spen>',
						customClass: {
							popup: 'border-warning'
						},
						showConfirmButton: false,
						timer: 7500
					})
				}
			})
			$('tr :checkbox').each(function() {
				this.checked = false;
				$('#btnDelete').prop('disabled', true);
			});
		});
	//on click btn delrow
	$(document).on('click',
		'#btnDelRow',
		function () {
			var Params = {};
			const element = $(this).parents('tbody');
			const childrenCount = element.children().length;
			if ($(this).attr('data-id') !== 'new' && childrenCount > 1) {
				Params['CONTROL'] = 'chDelOptionRow';
				Params['Pro_code'] = $(this).parents('tr').attr('data-code');
				const data_return1 = sendRequest(urlControl, Params);
				if (data_return1[0] === 's') {
					Swal.fire({
						title: 'هل انت واثق من حذف البيانات التالية؟',
						html: `<table class="table table-bordered text-nowrap" style="font-size:13px;">
						<tbody>
						<tr>
						<td>${$(this).parents('tr').attr('data-code')}</td>
						<td>${$(this).parents('tr').attr('data-option')}</td>
						</tr>
						</tbody>
						</table>
						<div class=swal2-validation-message id=swal2-validation-message style=font-size:14px; display: flex; margin-left: -20px; margin-right: -20px;> فى حال تأكيد الحذف لم يتم استعادة البيانات مرة اخرى</div>`,
						icon: 'question',
						allowOutsideClick: false,
						showCancelButton: true,
						confirmButtonColor: '#3085d6',
						cancelButtonColor: '#d33',
						confirmButtonText: 'تأكيد الحذف',
						cancelButtonText: 'إغلاق',
						footer: ''
					}).then((result) => {

						if (result.isConfirmed) {
							alert();
							Params['CONTROL'] = 'DelOptionRow';
							Params['Pro_code'] = $(this).parents('tr').attr('data-code');
							Params['Pro_cid'] = $(this).parents('tr').attr('id');
							const data_return = sendRequest(urlControl, Params);
							if (data_return[0] == 's') {
								$(this).parents('tr').remove();
								Swal.fire({
									title: data_return[1],
									text: data_return[2],
									icon: "success"
								});
							} else {
								Swal.fire({
									title: data_return[1],
									text: data_return[2],
									icon: "warning",
									allowOutsideClick: true,
									confirmButtonText: 'إغلاق',
								});
							}
						}
					});
				} else {
					Swal.fire({
						title: data_return1[1],
						html: data_return1[2],
						icon: 'error',
						allowOutsideClick: true,
						confirmButtonText: 'إغلاق',
					});
				}
			} else if ($(this).attr('data-id') !== 'new' && childrenCount <= 1) {
				Swal.fire({
					title: 'خطاء',
					html: 'لايمكن حذف هذا الكود .. لابد ان يحتوى المنتج على باركود واحد على الاقل',
					icon: 'error',
					allowOutsideClick: true,
					confirmButtonText: 'إغلاق',
				});
			} else {
				// const element = $(this).parents('tbody');
				if ($(this).attr('data-id') == 'new') {
					$(this).parents('tr').remove();

				}
				if (childrenCount === 1 && $(this).attr('data-id') == 'new') {
					btnCancelTbl();
				}
			}
		});
});