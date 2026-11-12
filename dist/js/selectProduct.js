// Data variables for products_list_view
var categories = '<a class="dropdown-item" onclick="showproduct(-1)">كل الفئات</a>';
var loadedCategories = [];
var productData = {}; // Assume `proRQ['PRODUCT']` structure

/////////////// start function \\\\\\\\\\\\\\\\\\
function handleAddProduct(id,code) {
	
        const loadingHtml = `
            <div id="swl-productes" data-id="${id}" data-code="${code}">
                <div class="spinner-grow" style="margin-top:25px;" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
                <div style="font-size:8px;">
                    <span>جارى عرض البيانات...</span>
                </div>
            </div>`;

        Swal.fire({
            title: 'إختار المنتج',
            html: loadingHtml,
            showCancelButton: true,
            confirmButtonText: 'تأكيد',
            cancelButtonText: 'إلغاء',
            didOpen: () => {
            	setTimeout(function (){
            		initializeProductSelection().then(function (){
                	if(code){
                		const prosuctOption = productData[id].CODES[code].OPTION_VAL_ID;
                		const orderPeoducts = JSON.parse(localStorage.getItem("itemarr"))
                		const thisProduct   = orderPeoducts[code]
                		handleProductClick(id).then(function (){
                			$("#swl-productes .op").each(function() {
							    let selectField = $(this); // الحقل الحالي
							    for (let i = 0; i < prosuctOption.length; i++) {
							        // التحقق مما إذا كانت القيمة موجودة ضمن الخيارات
							        if (selectField.find(`option[value="${prosuctOption[i]}"]`).length > 0) {
							            selectField.val(prosuctOption[i]).change(); // تعيين القيمة المناسبة
							            break; // الخروج من اللوب عند إيجاد أول قيمة مناسبة
							        }
							    }
							});
							$("#swl-productes").find(".qty").val(parseFloat(thisProduct.qty)).change();
							$("#swl-productes").find(".des").val(parseFloat(thisProduct.dis));             		
							$(".swal2-actions .back").remove();
							
                		});
                	}
                });
            	},500)
            },
            preConfirm: validateAndSaveProductData
        }).then((result) => {
        	
            if (result.value !== undefined) {
                addProductToList(result.value);
                highlightAddedProduct();
            }
        });
}

/*============ handleAddProduct didOpen() ============*/

async function initializeProductSelection() {
	
	if(!localStorage.getItem('swlitem')){
    	localStorage.setItem("swlitem", {});
	}
    Swal.getConfirmButton().setAttribute("disabled", "");
    await products_list_view($("#swl-productes"));
    
}

async function products_list_view(element) {
//	alert($('#product_option_stock').length)
    if(!$('#productslistView li').length){
    	
	    const toolsTemplate = `
	        <div id="product_view_tools" class="input-group mb-3" style="margin-top:18px;">
	            <div class="input-group-prepend">
	                <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">الفئة</button>
	                <div id="proCatTree" class="dropdown-menu">
	                    <a class="dropdown-item" onclick="showproduct(-1)">كل الفئات</a>
	                </div>
	            </div>
	            <input id="pro_search" type="text" class="form-control pro_search" placeholder="بحث">
	        </div>
	    `;
	    
	    const productsListTemplate = `
	        <div id="product_view_list" class="card" >
	            <div id="swl-proCard" class="card-body p-0" dir="rtl">
	                <ul id="productslistView" class="products-list product-list-in-card pl-2 pr-2 overflow-auto" style="max-height:450px"></ul>
	            </div>
	        </div>
	    `;
	    
	    const customerInputTemplate = `<div id="product_option_list" class="text-left" style="display:none"></div>`;
	    const statusTemplate = `<div id="product_option_status" class="text-left text-danger" style="font-size:0.8em; display:none"></div>`;
	    
	    const stockTemplate = `
	        <div id="product_option_stock" class="text-left" style="display:none">
	            <div class="row">
	                <div class="col-sm-6">
	                    <div class="form-group">
	                        <label>الكمية</label>
	                        <input type="number" min="0" class="form-control qty STO" placeholder="ادخل الكمية" disabled>
	                    	<span id="chStock" class="text-left text-danger" style="font-size:0.8em;"></span>
	                    </div>
	                </div>
	                <div class="col-sm-6">
	                    <div class="form-group">
	                        <label>سعر البيع</label>
	                        <input type="number" min="0" class="form-control price STO" placeholder="المبلغ المطلوب" disabled>
	                        <input type="number" min="0" class="form-control des STO" placeholder="ادخل الخصم" disabled style="display:none;">
	                    	<span id="chPrice" class="text-left text-danger" style="font-size:0.8em;"></span>
	                    </div>
	                </div>
	            </div>
	        </div>
	    `;
    
		$('#protemp').html(toolsTemplate + productsListTemplate + customerInputTemplate + statusTemplate + stockTemplate);
    }

    if(Object.keys(productData).length > 0){
    	if(!$("#protemp #productslistView li").length){
    		renderProductList();
    	}
    	element.html($('#protemp').html());
    	runEvents();
    	return;
    }
    // Initial load
    
    await loadProducts();
    // Inject templates into the element
    element.html($('#protemp').html());
    runEvents();
}

// تشغيل احداث البحث والمجموعات واختيار المنتج والمواصفات وغيرها
function runEvents(){
	// إضافة الحدث للنقر على المنتجات
    $('#swl-productes .proitem').on('click', function (){
    	handleProductClick($(this).attr('id'));
    });
	// الحدث: تصفية المنتجات حسب مدخلات البحث
	$(`#swl-productes #pro_search`).keyup( function () {
		const query = $(this).val().toLowerCase();
		$("#swl-productes .products-list li").each(function () {
			$(this).toggle($(this).text().toLowerCase().includes(query));
		});
	});

	// العودة إلى قائمة المنتجات
	$(document).on('click', '.swal2-actions .back', function () {
	    resetProductView();
	});
	
}

// تحميل المنتجات والفئات
function loadProducts(start = 0) {
	
	const response =  sendRequestGET(`${urlApi}?CONTROL=ProductsData&start=${start}`);
    productData = response.PRODUCT;
    renderProductList();
    updateCategoryDropdown();
}

// عرض قائمة المنتجات
function renderProductList() {
	let productHTML = "";
	const productsArray = Object.values(productData); // تحويل القيم إلى مصفوفة
	for (const product of productsArray) { 
		productHTML += `
            	<li id="${product.PRO_ID}" class="item proitem" data-catid="${product.CAT_ID}">
                    <div class="product-img float-left" style="margin-left:5px;">
                        <img src="${product.PRO_IMG_LINK || '/img/default-150x150.png'}" alt="Product Image" class="img-size-50">
                    </div>
                    <div class="product-info-left">
                        <a href="javascript:void(0)" class="product-title text-right">
                            ${product.PRO_NAME}
                            <span class="badge badge-warning float-right" style="font-size:12px">
                                ${product.MINPRICE} - ${product.MAXPRICE} ج.م
                            </span>
                        </a>
                        <span class="product-description mb-0 pb-0 pt-2" style="font-size:12px;">
		                   ${product.CAT_NAME}
		                </span>
                    </div>
                </li>`;
    }
    $(`#protemp #productslistView`).append(productHTML);
    // الحدث: التمرير اللانهائي لتحميل المزيد من المنتجات
	$('#swl-productes #productslistView').on("scroll", function () {
		const y = this.scrollHeight - this.scrollTop - $(this).outerHeight();
		if (y < 50) {
			loadProducts($('.proitem').length);
		}
	});
}

// Update category dropdown
function updateCategoryDropdown() {
	const productsArray = Object.values(productData); // تحويل القيم إلى مصفوفة
	for (const product of productsArray) { 
		if (!loadedCategories.includes(product.CAT_ID)) {
			categories += `<a class="dropdown-item" onclick="showproduct(${product.CAT_ID})">${product.CAT_NAME}</a>`;
			loadedCategories.push(product.CAT_ID);
		}
	}
	$('#protemp #proCatTree').html(categories);
}

// اضافة المنتجات فى جدول العرض
function addProductToList(productHtml) {
	$("#itemorder tr").not(":last").remove();
	$("#itemorder tr:last").before(productHtml);
}

/*=========== handleAddProduct preConfirm() ===========*/

// التحقق من صحة بيانات المنتج وحفظها
function validateAndSaveProductData() {
    const productData = JSON.parse(localStorage.getItem("swlitem")) || {};
    const errors = $("#swl-productes .is-invalid").length;

    // قراءة القيم من الحقول
    productData["qty"] = parseFloat($("#swl-productes .qty").val() || 0).toFixed(2);
    productData["dis"] = parseFloat($("#swl-productes .des").val() || 0).toFixed(2);

    // التحقق من صحة البيانات
    if (
        productData["PRO_CODE"] &&
        productData["PRO_NAME"] &&
        productData["CODE_COST_PRICE"] &&
        productData["CODE_PRICE"] &&
        parseFloat(productData["qty"]) > 0 &&
        productData["dis"] &&
        errors === 0
    ) {
    	
        localStorage.setItem("swlitem", JSON.stringify(productData));
        return addProductToCart(productData);
    } else {
        Swal.showValidationMessage("خطاء - جميع الحقول مطلوبه");
        return false;
    }
}

//----------validateAndSaveProductData---------------
function addProductToCart(peroductRow = null,st = 0) {
    const ulog = JSON.parse(localStorage.getItem("orderlog")) || [];
    let items = JSON.parse(localStorage.getItem("itemarr")) || {};
    const newOrder = JSON.parse(localStorage.getItem("newOrder")) || {};
    const selectedProduct = peroductRow; //JSON.parse(localStorage.getItem("swlitem"));
console.log(selectedProduct)
    if (!selectedProduct) {
    	const updatedHtml = renderCartItems(items, newOrder);
        return updatedHtml;
    }
	
    let changes = 0;
    const productId 	= $("#swl-productes").data("id");
    const productCode	= $("#swl-productes").data("code");
    if (items[productCode]) {
       	updateExistingProduct(items, selectedProduct, productCode, ulog);
    	delete items[productCode];
    	items[selectedProduct['PRO_CODE']] = selectedProduct;
    } else if (Object.keys(selectedProduct).length > 0 && parseInt(selectedProduct["qty"]) > 0) {
        // إضافة منتج جديد
        if(items[selectedProduct['PRO_CODE']]){
        	selectedProduct["qty"] = parseFloat(parseFloat(selectedProduct["qty"]) + parseFloat(items[selectedProduct['PRO_CODE']]['qty'])).toFixed(2);
        	delete items[selectedProduct['PRO_CODE']];
        }
        logProductAddition(ulog, selectedProduct);
        items[selectedProduct['PRO_CODE']] = selectedProduct;
    }

    // تحديث التخزين المحلي
    localStorage.setItem("itemarr", JSON.stringify(items));
    localStorage.setItem("orderlog", JSON.stringify(ulog));
    localStorage.removeItem("swlitem");

    // تحديث واجهة المستخدم
    const updatedHtml = renderCartItems(items, newOrder);
    return updatedHtml;
}

// Update current product if any
function updateExistingProduct(items, selectedProduct, productCode, ulog) {
    let changes = 0;
    const existingProduct = items[productCode];
    // مقارنة الخيارات
    if (
        Object.values(existingProduct["OPTION_TEXT"]).toString() !== Object.values(selectedProduct["OPTION_TEXT"]).toString()
    ) {
    	
        for (let i in existingProduct["OPTION_TEXT"]) {
            if (existingProduct["OPTION_TEXT"][i] !== selectedProduct["OPTION_TEXT"][i]) {
                changes++;
                ulog.push(
                    `* تم تغيير ${existingProduct["OPTION_TEXT"][i]} إلى ${selectedProduct["OPTION_TEXT"][i]} لـ ${existingProduct["PRO_NAME"]}`
                );
                //console.log(ulog)
            }
        }
    }

    // مقارنة الكمية
    if (parseFloat(existingProduct["qty"]).toFixed(2) !== selectedProduct["qty"]) {
        changes++;
        ulog.push(
            `* تم تغيير الكمية من ${parseFloat(existingProduct["qty"]).toFixed(2)} إلى ${parseFloat(selectedProduct["qty"]).toFixed(2)} لـ ${existingProduct["PRO_NAME"]}`
        );
    }

    // مقارنة الخصم
    if (existingProduct["dis"] !== selectedProduct["dis"]) {
        changes++;
        ulog.push(
            `* تم تغيير الخصم من ${existingProduct["dis"]} إلى ${selectedProduct["dis"]} لـ ${existingProduct["PRO_NAME"]}`
        );
    }
    
    if (changes === 0) {
        logProductAddition(ulog, selectedProduct);
    }

    return changes;
}

function logProductAddition(ulog, product) {
    ulog.push(
        `* تم إضافة المنتج ${product["PRO_NAME"]} - ${Object.values(product["OPTION_TEXT"]).toString()} - الكمية: ${product["qty"]} - الخصم: ${product["dis"]}`
    );
}

function renderCartItems(items, newOrder) {
    let html = "";
    let totalPrice = 0;

    for (let code in items) {
        const product = items[code];
        const options = product["OPTION_TEXT"] ? Object.values(product["OPTION_TEXT"]).join(" | ") : "";
        const price = (product["CODE_PRICE"] * product["qty"]) - (product["dis"] * product["qty"]);
        totalPrice += price;

        html += `
            <tr class="item itemorder dtrow bg-color">
                <td class="col-sm-2 text-center tblitemcode">${product["PRO_CODE"]}</td>
                <td class="text-nowrap text-left">
                    <div>${product["PRO_NAME"]}</div>
                    <a class="text-info">${options}</a>
                </td>
                <td class="col-sm-1 text-center">${parseInt(product["qty"])}</td>
                <td class="col-sm-1 text-center">${product["CODE_PRICE"]}</td>
                <td class="col-sm-1 text-center">${parseFloat(product["dis"]).toFixed(2)}</td>
                <td class="col-sm-1 text-center">${parseFloat(price).toFixed(2)}</td>
                <td class="col-sm-1 text-center">
                    <button id="addPorduct" type="button" data-id="${product["PRODUCT_ID"]}" data-code="${product["PRO_CODE"]}" class="btn btn-primary btn-md">
                        <i class="fa fa-edit fa-align-center"></i>
                    </button>
                </td>
                <td class="col-sm-1 text-center">
                    <button id="btnProDele" type="button" data-code="${product["PRO_CODE"]}" class="btn btn-danger btn-md delete">
                        <i class="fas fa-align-center fa-close"></i>
                    </button>
                </td>
            </tr>`;
    }

    const totalWithShipping = totalPrice + parseInt(newOrder["shipval"] || 0);
    $("#orderprice").text(parseFloat(totalPrice).toFixed(2));
    $("#ordertotal").text(parseFloat(totalWithShipping).toFixed(2));

    return html;
}

//================= sub function ======================

function highlightAddedProduct() {
    const lastIndex = $(".itemorder").length - 1;
    const $lastItem = $(".itemorder").eq(lastIndex);

    $lastItem.css("backgroundColor", "hsl(0,100%,50%)");
    let delay = 1000;
    for (let lightness = 50; lightness <= 100; lightness += 2.5) {
        delay += 10;
        setTimeout(() => {
            $lastItem.css("backgroundColor", `hsl(0,100%,${lightness}%)`);
        }, delay);
    }
}

// إعادة تعيين العرض إلى قائمة المنتجات
function resetProductView() {
    $("#swl-productes #product_option_list").html("").hide();
    $("#swl-productes #product_option_stock").hide();
    $("#swl-productes #product_option_status").hide();
    $("#swl-productes #product_view_tools").show();
    $("#swl-productes #product_view_list").show();
    localStorage.removeItem('swlitem');
    Swal.getTitle().textContent = "اختار المنتج";
}

// إعداد المنتج الافتراضي عند عدم وجود خيارات
function setupDefaultProduct(iCodes, product, stid) {
    const defaultCode = iCodes[Object.keys(iCodes)[0]];
    handleValidProductCode(defaultCode, product, stid);
    $("#swl-productes #product_view_tools").hide();
    $("#swl-productes #product_view_list").hide();
}

// التعامل مع النقر على المنتج
async function handleProductClick(productID) {
    const elId = productID //element.attr('id'); // الحصول على ID المنتج
    const product = productData[elId]; // بيانات المنتج
    const stid = await getStorageId(); // الحصول على Storage ID
    if (!product || !stid) {
        console.error("Invalid product or storage ID.");
        return;
    }

    setupSwalWindow(product); // إعداد نافذة المنتج
    await handleProductOptions(product, stid); // التعامل مع الخيارات والمواصفات
}

// الحصول على Store ID من localStorage
function getStorageId() {
    const orderData = JSON.parse(localStorage.getItem('newOrder'));
    return orderData ? orderData['stoid'] : null;
}

// إعداد نافذة المنتج باستخدام SweetAlert
function setupSwalWindow(product) {
    Swal.getTitle().textContent = product.PRO_NAME;
    if (!$(".back").is(':visible')) {
        $(".swal2-actions").append('<button id="proswback" type="button" class="swal2-cancel back swal2-styled" style="display: inline-block;">عودة</button>');
    }
}

// التعامل مع خيارات المنتج والمواصفات
function handleProductOptions(product, stid) {
    const iGroup = product['OPTION_GROUP'];
    const iCodes = product['CODES'];
    if (Object.keys(iGroup).length) {
        renderOptionSelectors(iGroup, iCodes, product, stid);
    } else {
        setupDefaultProduct(iCodes, product, stid);
    }
}

// عرض قائمة الخيارات عند وجود مواصفات
function renderOptionSelectors(iGroup, iCodes, product, stid) {
    let selectors = '';
    for (const groupKey in iGroup) {
        const group = iGroup[groupKey];
        
        selectors += `
            <div class="form-group">
                <label>${group.GROUP_NAME}</label>
                <select class="form-control op select2">
                    <option value="" selected>اختار ${group.GROUP_NAME}</option>
                    ${Object.values(group.OPTION).map(option => `<option value="${option.VAL_ID}">${option.VAL_NAME}</option>`).join('')}
                </select>
            </div>`;
    }
    $("#swl-productes #product_view_tools").hide();
    $("#swl-productes #product_view_list").hide();
    $("#swl-productes #product_option_list").html(selectors).show();

    // التعامل مع تغيير الخيارات
    $(document).on('change', '#swl-productes .op', function () {
        handleOptionChange(iCodes, product, stid);
    });
    
	
}

// التعامل مع تغيير الخيارات
function handleOptionChange(iCodes, product, stid) {
    const selectedOptions = $('#swl-productes .op').map((_, el) => parseInt($(el).val())).get();
    let err = 0;
    $('#swl-productes .op').each(function(){
    	if(!$(this).val()){err++;}
    })
    const productCode = findMatchingCode(iCodes, selectedOptions);
    if (productCode) {
        handleValidProductCode(productCode, product, stid);
    } else if(err==0){
        handleInvalidProductCode();
    }else{
    	disableProductSelection('');
    }
    
}

// البحث عن الكود المطابق للخيارات المحددة
function findMatchingCode(iCodes, selectedOptions) {
    for (const codeKey in iCodes) {
        const codeOptions = iCodes[codeKey]['OPTION_VAL_ID'];
        const matching = arraysMatch(codeOptions, selectedOptions);
        if (matching) {
            return iCodes[codeKey];
        }
    }
    return null;
}

// التحقق من تطابق المصفوفات
function arraysMatch(arr1, arr2) {
    if (arr1.length !== arr2.length) return false;
    const sortedArr1 = [...arr1].sort();
    const sortedArr2 = [...arr2].sort();
    return sortedArr1.every((val, index) => {
        // تطابق مع تجاهل المسافات وحساسية الحالة، وتحويل النوع
        return String(val).trim().toLowerCase() === String(sortedArr2[index]).trim().toLowerCase();
    });
}

// التعامل مع كود منتج صحيح
function handleValidProductCode(code, product, stid) {
    const productData = prepareProductData(code, product, stid);
    if (productData.STQTY <= 0 && productData.BOOKING == 0) {
        disableProductSelection("لا يمكن طلب هذا المنتج حاليًا - المنتج غير متوفر بالمخزن");
    } else {
        enableProductSelection(productData);
    }
}

// تعطيل الاختيار عند وجود مشكلة
function disableProductSelection(message) {
    $("#swl-productes .STO").attr("disabled", true);
    $('.swal2-confirm').attr('disabled', true);
    $("#swl-productes #product_option_stock").hide();
    $("#swl-productes #product_option_status").html(message).show();
    localStorage.removeItem('swlitem');
}

// التعامل مع كود منتج غير صحيح
function handleInvalidProductCode() {
    disableProductSelection("لم يتم العثور على المنتج بالمواصفات المحددة.");
}

// إعداد بيانات المنتج
function prepareProductData(code, product, stid) {
    return {
        ...code,
        qty: 0,
        dis: 0,
        PRO_NAME: product.PRO_NAME,
        CAT_NAME: product.CAT_NAME,
        STQTY: code['STORSQTY'][stid] - getOldQuantity(code['PRO_CODE']),
    };
}

// الحصول على الكمية القديمة من localStorage
function getOldQuantity(code) {
    const itemArray = JSON.parse(localStorage.getItem('itemarr'));
    return itemArray && itemArray[code] ? parseInt(itemArray[code]['qty']) : 0;
}

// تمكين الاختيار عند وجود كمية كافية
function enableProductSelection(productData) {
	$("#swl-productes .qty").keyup(function(){
		$('.swal2-confirm').attr('disabled', false);
		mesSt = ' الكميه المطلوبه غير متوفرة حاليا ';
		$('#swl-productes #chStock').html('');
		if(productData.STQTY < $(this).val()){			
			if(productData.STQTY < 20 && productData.STQTY >0){
				mesSt=' الكميه المتوفرة حاليا '+productData.STQTY + ' قطعه ';
			}
			if(productData.BOOKING == 1){
				mesSt = mesSt + 'سوف يتم الشحن حين توافر الكميه المطلوبة';
			}else{
				$('.swal2-confirm').attr('disabled', true);
				mesSt= 'لايمكن طلب المنتج حاليا - '+mesSt
			}
			$('#swl-productes #chStock').html(mesSt);
		}
	});
	
	$("#swl-productes .price").keyup(function(){
	//	console.log(productData)
		$('.swal2-confirm').attr('disabled', false);
		$('#swl-productes #chPrice').html('');
		if(parseInt(productData.CODE_PRICE) < parseInt($(this).val())){
			$('.swal2-confirm').attr('disabled', true);
			$('#swl-productes #chPrice').html('الحد الاقصى لسعر البيع '+productData.CODE_PRICE);
			$(this).addClass('is-invalid');
		}else if(parseInt(productData.LOWEST_PRICE) > $(this).val()){
			$('.swal2-confirm').attr('disabled', true);
			$('#swl-productes #chPrice').html('الحد الادنى لسعر البيع '+productData.LOWEST_PRICE);
			$(this).addClass('is-invalid');
		} else {
			$(this).removeClass('is-invalid , is-invalid');
		}
		$("#swl-productes .des").val(productData.CODE_PRICE - $(this).val());
	});
	$("#swl-productes #product_option_status").html('');
    $("#swl-productes .qty").val(1).keyup();
    $("#swl-productes .dis").val(0);
    $("#swl-productes .STO").attr("disabled", false);
    $("#swl-productes .price").val(productData.CODE_PRICE);
    $("#swl-productes #product_option_stock").show();
    localStorage.setItem('swlitem', JSON.stringify(productData));
}