
/*==============================================================
 * تهيئة مسودة أمر البيع من الواجهة فقط مع الحفاظ على بياناتها
 * لا نحول BRANCH_ID إلى STO_ID؛ فهما معرفان مختلفان.
 *==============================================================*/

function jmReadOrderDraft() {
    try {
        const value = JSON.parse(localStorage.getItem('newOrder') || 'null');
        return value && typeof value === 'object' && !Array.isArray(value) && Object.keys(value).length ? value : null;
    } catch (error) {
        console.error('تعذر قراءة مسودة أمر البيع:', error);
        return null;
    }
}

function jmInitializeNewOrderDraft() {
    if (typeof Go !== 'undefined' && Go !== 'NewOrder') return;
    const current = jmReadOrderDraft();
    const userId = (typeof userlogin_data !== 'undefined' && userlogin_data) ? userlogin_data.ui : null;

    // لا نلمس مسودة موجودة، خصوصًا إذا كانت تحتوي على أصناف أو تخص أمرًا قيد التعديل.
    if (current) return;

    const draft = {
        uid: '', uname: '', umail: '', telid: '', telstr: '', addid: '', addstr: '',
        orderby: userId || '', oty: (typeof ordertype !== 'undefined' ? ordertype : ''),
        stoid: null, shipval: 0, shiplow: 0, shipmax: 0,
        shipDays: 0, shipWaiting: 1, shipDate: null, cliComment: ''
    };
    localStorage.setItem('newOrder', JSON.stringify(draft));
    localStorage.setItem('itemarr', '{}');
    localStorage.setItem('orderlog', '[]');
}

$(function () {
    jmInitializeNewOrderDraft();
});

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
            title: 'اختار المنتج وأضفه إلى الطلب',
            html: loadingHtml,
            width: 'min(1100px, 96vw)',
            customClass: { popup: 'justm-product-picker' },
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
    const response = sendRequestGET(`${urlApi}?CONTROL=ProductsData&start=${start}`);
    if (!response || typeof response !== 'object' || !response.PRODUCT || typeof response.PRODUCT !== 'object') {
        throw new Error('لم تُرجع خدمة المنتجات بيانات صالحة.');
    }

    // أول تحميل يجهز كتالوج المنتجات كاملًا في الذاكرة؛ لا ينتظر فتح خطوة المنتجات.
    productData = response.PRODUCT;
    renderProductList();
    updateCategoryDropdown();
    renderStorefrontCatalog();
    return productData;
}

// واجهة كتالوج المتجر المدمجة في شاشة إنشاء الطلب
let jmStoreCategory = 'all';
let jmStoreCatalogLoading = false;
function initializeStorefrontCatalog() {
    if (!document.getElementById('jmStoreProductGrid')) return;
    if (Object.keys(productData || {}).length) {
        renderStorefrontCategories();
        renderStorefrontCatalog();
        return;
    }
    if (jmStoreCatalogLoading) return;
    jmStoreCatalogLoading = true;
    try {
        loadProducts(0);
        if (!Object.keys(productData || {}).length) {
            $('#jmProductCount').text('0 منتج');
            $('#jmStoreProductGrid').html('<div class="jm-store-loading">لا توجد منتجات متاحة للعرض.</div>');
        }
    } catch (error) {
        $('#jmProductCount').text('تعذر التحميل');
        $('#jmStoreProductGrid').html('<div class="jm-store-loading">تعذر تحميل المنتجات. تحقق من الاتصال ثم أعد تحميل الصفحة.</div>');
        console.error('Storefront catalog error:', error);
    } finally {
        jmStoreCatalogLoading = false;
    }
}
function renderStorefrontCategories() {
    const holder = $('#jmStoreCategories');
    if (!holder.length) return;
    const categoriesMap = new Map();
    Object.values(productData || {}).forEach(p => {
        const id = String(p.CAT_ID ?? '').trim();
        if (id && !categoriesMap.has(id)) categoriesMap.set(id, String(p.CAT_NAME || 'قسم'));
    });
    const categoriesList = [...categoriesMap.entries()].sort((a,b) => a[1].localeCompare(b[1], 'ar'));
    const safe = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
    holder.html('<button type="button" data-category="all" class="active">كل الأقسام</button>' +
        categoriesList.map(([id,name]) => `<button type="button" data-category="${safe(id)}">${safe(name)}</button>`).join(''));
}
function renderStorefrontCatalog() {
    const grid = $('#jmStoreProductGrid');
    if (!grid.length) return;
    const products = Object.values(productData || {});
    if (!products.length) {
        grid.html('<div class="jm-store-loading">لا توجد منتجات متاحة للعرض.</div>');
        $('#jmProductCount').text('0 منتج');
        return;
    }
    const normalize = value => String(value ?? '').toLocaleLowerCase('ar').trim();
    const query = normalize($('#jmStoreSearch').val());
    const filtered = products.filter(p => {
        const catMatch = jmStoreCategory === 'all' || String(p.CAT_ID ?? '') === String(jmStoreCategory);
        const codeRows = Object.values(p.CODES || {});
        const codes = codeRows.map(c => [c.PRO_CODE, ...(Object.values(c.OPTION_TEXT || {})), ...(c.OPTION_VAL_ID || [])].join(' ')).join(' ');
        const haystack = normalize([p.PRO_NAME, p.PRO_ID, p.CAT_ID, p.CAT_NAME, codes].join(' '));
        return catMatch && (!query || haystack.includes(query));
    });
    $('#jmProductCount').text(`${filtered.length} من ${products.length} منتج`);
    if (!filtered.length) {
        grid.html('<div class="jm-store-empty-results"><i class="fas fa-search"></i><strong>لا توجد نتائج مطابقة</strong><span>جرّب اسمًا آخر أو اختر قسمًا مختلفًا.</span></div>');
        return;
    }
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
    grid.html(filtered.map(p => {
        const name = escapeHtml(p.PRO_NAME || 'منتج');
        const category = escapeHtml(p.CAT_NAME || 'بدون قسم');
        const image = escapeHtml(p.PRO_IMG_LINK || '/img/product-default-3d.svg');
        const id = escapeHtml(p.PRO_ID);
        const price = p.MINPRICE == p.MAXPRICE ? escapeHtml(p.MINPRICE) : `${escapeHtml(p.MINPRICE)} - ${escapeHtml(p.MAXPRICE)}`;
        const codeCount = Object.keys(p.CODES || {}).length;
        return `
      <article class="jm-store-product">
        <div class="jm-store-product-image"><img src="${image}" alt="${name}" loading="lazy" onerror="this.onerror=null;this.src='/img/product-default-3d.svg';"></div>
        <div class="jm-store-product-body">
          <div class="jm-store-product-name">${name}</div>
          <div class="jm-store-product-category">${category}</div>
          <div class="jm-store-product-meta"><span>${codeCount} اختيار متاح</span><span class="jm-store-product-price">${price} <small>ج.م</small></span></div>
          <button type="button" class="jm-store-add" data-product-id="${id}"><i class="fas fa-cart-plus"></i> تفاصيل وإضافة</button>
        </div>
      </article>`;
    }).join(''));
}
function updateStorefrontCartCount() {
    const items = JSON.parse(localStorage.getItem('itemarr') || '{}');
    const count = Object.values(items).reduce((sum, p) => sum + (parseFloat(p.qty) || 0), 0);
    $('#jmCartCount').text(Number.isInteger(count) ? count : count.toFixed(2));
    $('#jmStep1CartCount').text(Number.isInteger(count) ? count : count.toFixed(2));
    $('#jmCartEmpty').toggle(Object.keys(items).length === 0);
}

// Step 2: تفاصيل المنتج داخل نفس الصفحة (بدون نافذة اختيار قديمة).
let jmCurrentDetailProduct = null;
let jmCurrentDetailCode = null;
let jmCurrentDetailEditCode = null;
let jmCurrentGalleryImages = [];
function jmEscape(value) { return String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch])); }
function jmProductImages(product) {
    // يدعم مصفوفة الصور إذا أتاحها مصدر البيانات مستقبلاً، مع الصورة الأساسية الحالية كحل افتراضي.
    const candidates = product.PRO_IMAGES || product.PRODUCT_IMAGES || product.IMAGES || product.GALLERY_IMAGES || [];
    const list = Array.isArray(candidates) ? candidates.map(x => typeof x === 'string' ? x : (x.url || x.IMAGE_URL || x.PRO_IMG_LINK)).filter(Boolean) : [];
    const primary = product.PRO_IMG_LINK;
    if (primary && !list.includes(primary)) list.unshift(primary);
    return [...new Set(list)].length ? [...new Set(list)] : ['/img/product-default-3d.svg'];
}
async function openProductDetails(productId, editCode = null) {
    const product = productData[String(productId)];
    if (!product) return;
    jmCurrentDetailProduct = product; jmCurrentDetailCode = null; jmCurrentDetailEditCode = editCode;
    jmCurrentGalleryImages = jmProductImages(product);
    $('#jmStoreProductGrid, .jm-store-tools, .jm-store-section-head').hide();
    $('#jmProductDetails').prop('hidden', false).show().html('<div class="jm-store-loading"><i class="fas fa-spinner fa-spin"></i> جارٍ تجهيز بيانات المنتج...</div>');
    const groups = product.OPTION_GROUP || {};
    const oldItems = JSON.parse(localStorage.getItem('itemarr') || '{}');
    const oldItem = editCode ? oldItems[editCode] : null;
    let optionHtml = '';
    Object.values(groups).forEach(group => {
        const opts = Object.values(group.OPTION || {});
        optionHtml += `<div class="form-group jm-variant-group"><label>${jmEscape(group.GROUP_NAME)}</label><select class="form-control jm-variant-select" data-group="${jmEscape(group.GROUP_ID)}"><option value="">اختر ${jmEscape(group.GROUP_NAME)}</option>${opts.map(o => `<option value="${jmEscape(o.VAL_ID)}">${jmEscape(o.VAL_NAME)}</option>`).join('')}</select></div>`;
    });
    const image = jmEscape(jmCurrentGalleryImages[0]);
    $('#jmProductDetails').html(`
      <div class="jm-detail-top"><button type="button" class="btn btn-default jm-detail-back"><i class="fas fa-arrow-right"></i> رجوع للمنتجات</button><span class="jm-detail-step">تفاصيل المنتج</span></div>
      <div class="row jm-detail-layout" dir="rtl">
        <div class="col-lg-6"><div class="jm-gallery"><div class="jm-gallery-main"><button type="button" class="jm-gallery-nav jm-gallery-prev" aria-label="الصورة السابقة"><i class="fas fa-chevron-right"></i></button><img id="jmGalleryMain" src="${image}" alt="${jmEscape(product.PRO_NAME)}" onerror="this.onerror=null;this.src='/img/product-default-3d.svg'"><button type="button" class="jm-gallery-nav jm-gallery-next" aria-label="الصورة التالية"><i class="fas fa-chevron-left"></i></button></div><div class="jm-gallery-thumbs" id="jmGalleryThumbs">${jmCurrentGalleryImages.map((src,i)=>`<button type="button" class="jm-gallery-thumb ${i===0?'active':''}" data-index="${i}"><img src="${jmEscape(src)}" alt="صورة ${i+1}"></button>`).join('')}</div></div></div>
        <div class="col-lg-6"><div class="jm-detail-info"><div class="jm-detail-category">${jmEscape(product.CAT_NAME || '')}</div><h3>${jmEscape(product.PRO_NAME)}</h3><p class="jm-detail-description">${jmEscape(product.PRO_DESCRIPTION || '')}</p><div class="jm-detail-divider"></div><h5>اختر مواصفات المنتج</h5><div class="jm-detail-variants">${optionHtml || '<div class="text-muted">هذا المنتج لا يحتوي على مواصفات متعددة.</div>'}</div><div id="jmVariantStatus" class="jm-variant-status" role="status">${Object.keys(groups).length ? 'اختر جميع المواصفات لعرض السعر والمخزون.' : ''}</div>
        <div class="jm-detail-controls" id="jmDetailControls" hidden><div class="jm-price-range-info" id="jmPriceRangeInfo" role="note"></div><div class="row"><div class="col-12"><label for="jmDetailPrice">سعر البيع النهائي للوحدة</label><input id="jmDetailPrice" class="form-control" type="number" min="0" step="0.01" inputmode="decimal" aria-describedby="jmPriceRangeInfo jmVariantStatus"></div><div class="col-6 mt-3"><label>الكمية المطلوبة</label><div class="input-group"><div class="input-group-prepend"><button type="button" class="btn btn-outline-secondary" id="jmQtyMinus">−</button></div><input id="jmDetailQty" class="form-control text-center" type="number" min="1" step="1" value="1"><div class="input-group-append"><button type="button" class="btn btn-outline-secondary" id="jmQtyPlus">+</button></div></div></div><div class="col-6 mt-3"><label>المتاح بالمخزن</label><div class="jm-stock-value" id="jmDetailStock">—</div></div></div><div class="jm-detail-total"><span>إجمالي الصنف بعد الخصم</span><strong id="jmDetailTotal">0.00 ج.م</strong></div></div>
        <div class="jm-detail-actions"><button type="button" id="jmAddConfiguredProduct" class="btn btn-primary btn-lg" disabled><i class="fas fa-cart-plus"></i> ${oldItem?'حفظ التعديلات':'إضافة إلى السلة'}</button></div></div></div></div>`);
    if (oldItem) {
        const code = product.CODES && product.CODES[editCode];
        if (code) { Object.values(groups).forEach((g,idx)=>{ const val=(code.OPTION_VAL_ID||[])[idx]; $(`#jmProductDetails .jm-variant-select`).eq(idx).val(val); }); }
    }
    if (!Object.keys(groups).length) {
        const codes=product.CODES||{}; const code=editCode && codes[editCode] ? codes[editCode] : codes[Object.keys(codes)[0]];
        if (code) jmActivateVariant(code, product, editCode);
    } else if (oldItem) { jmResolveVariant(); }
}
function jmResolveVariant() {
    const product=jmCurrentDetailProduct; if(!product) return;
    const selects=$('#jmProductDetails .jm-variant-select'); let missing=false; const chosen=[];
    selects.each(function(){ if(!this.value) missing=true; chosen.push(Number(this.value)); });
    if(missing){ $('#jmDetailControls').prop('hidden',true); $('#jmVariantStatus').text('اختر جميع المواصفات لعرض السعر والمخزون.').removeClass('text-danger'); $('#jmAddConfiguredProduct').prop('disabled',true); return; }
    const code=findMatchingCode(product.CODES||{},chosen);
    if(!code){ $('#jmDetailControls').prop('hidden',true); $('#jmVariantStatus').text('التركيبة المحددة غير متاحة.').addClass('text-danger'); $('#jmAddConfiguredProduct').prop('disabled',true); return; }
    jmActivateVariant(code,product,jmCurrentDetailEditCode);
}
async function jmActivateVariant(code, product, editCode) {
    // أمر البيع مرتبط بمخزن واحد يتم اختياره عند بدء الطلب ويحفظ في newOrder.stoid.
    const stid = getStorageId();
    if (stid === null || stid === undefined || String(stid).trim() === '') {
        jmCurrentDetailCode = null;
        $('#jmDetailControls').prop('hidden', true);
        $('#jmDetailStock').text('—');
        $('#jmVariantStatus').text('لم يتم تحديد مخزن لهذا العنوان. ارجع إلى بيانات العميل واختر عنوان توصيل مرتبطًا بمخزن.').addClass('text-danger');
        $('#jmAddConfiguredProduct').prop('disabled', true);
        return;
    }

    const data = prepareProductData(code, product, stid);
    // عند تعديل الصنف، الكمية الموجودة بالفعل ضمن الطلب تخصمها prepareProductData؛
    // نعيد إضافتها للمتاح حتى يستطيع المستخدم زيادة الكمية دون احتسابها مرتين.
    if (editCode && String(editCode) === String(code.PRO_CODE)) {
        const currentItems = JSON.parse(localStorage.getItem('itemarr') || '{}') || {};
        const currentQty = Number(currentItems[editCode] && currentItems[editCode].qty) || 0;
        data.STQTY = Math.max(0, Number(data.STQTY) + currentQty);
    }
    data.SELECTED_STO_ID = String(stid);
    jmCurrentDetailCode = data;

    if (data.STQTY <= 0 && Number(data.BOOKING) !== 1) {
        $('#jmDetailControls').prop('hidden', true);
        $('#jmDetailStock').text('0 قطعة');
        $('#jmVariantStatus').text('هذه التركيبة غير متاحة في مخزن الطلب ولا تسمح بالحجز المسبق.').addClass('text-danger');
        $('#jmAddConfiguredProduct').prop('disabled', true);
        return;
    }

    const existing = editCode ? (JSON.parse(localStorage.getItem('itemarr') || '{}')[editCode]) : null;
    $('#jmDetailStock').text(`${data.STQTY} قطعة متاحة`);
    $('#jmVariantStatus')
        .text(data.BOOKING == 1 && data.STQTY <= 0
            ? 'متاح للحجز المسبق، وسيتم الشحن عند توافر الكمية.'
            : `المخزون المعروض خاص بمخزن الطلب رقم ${stid}.`)
        .removeClass('text-danger');
    $('#jmDetailControls').prop('hidden', false);
    const basePrice = Number(data.CODE_PRICE);
    const minPrice = Number(data.LOWEST_PRICE);
    const existingNetPrice = existing
        ? Number(existing.CODE_PRICE || 0) - Number(existing.dis || 0)
        : basePrice;
    $('#jmDetailPrice').attr({min: minPrice, max: basePrice}).val(existing ? existingNetPrice : basePrice);
    $('#jmPriceRangeInfo').text(`السعر المسموح به لهذا المنتج من ${minPrice.toFixed(2)} إلى ${basePrice.toFixed(2)} جنيه. لن يتم قبول سعر خارج هذا النطاق.`);
    $('#jmDetailQty').val(existing ? existing.qty : 1);
    $('#jmAddConfiguredProduct').prop('disabled', false);
    jmUpdateDetailTotal();
}
function jmValidateDetailPrice(showMessage = true){
    const code = jmCurrentDetailCode;
    if(!code) return false;
    const raw = $('#jmDetailPrice').val();
    const price = Number(raw);
    const min = Number(code.LOWEST_PRICE);
    const max = Number(code.CODE_PRICE);
    const valid = raw !== '' && Number.isFinite(price) && Number.isFinite(min) && Number.isFinite(max) && price >= min && price <= max;
    if(!valid && showMessage){
        let message = 'أدخل سعر بيع صحيحًا.';
        if(raw !== '' && Number.isFinite(price)){
            if(price < min) message = `السعر أقل من الحد الأدنى المسموح (${min.toFixed(2)} جنيه).`;
            else if(price > max) message = `السعر أكبر من الحد الأقصى المسموح (${max.toFixed(2)} جنيه).`;
        }
        $('#jmVariantStatus').text(message).addClass('text-danger');
        $('#jmDetailPrice').addClass('is-invalid');
    } else if(valid){
        $('#jmDetailPrice').removeClass('is-invalid');
        $('#jmVariantStatus').removeClass('text-danger').text(`السعر مقبول. الحدود المسموحة: ${min.toFixed(2)} إلى ${max.toFixed(2)} جنيه.`);
    }
    return valid;
}
function jmUpdateDetailTotal(){
    const price=Number($('#jmDetailPrice').val());
    const qty=Number($('#jmDetailQty').val())||0;
    const valid=jmValidateDetailPrice(true);
    $('#jmDetailTotal').text((valid && Number.isFinite(qty) ? price*qty : 0).toFixed(2)+' ج.م');
    $('#jmAddConfiguredProduct').prop('disabled', !valid || !Number.isFinite(qty) || qty<=0);
}
function jmCommitConfiguredProduct(){
    if(!jmCurrentDetailCode) return;
    const price=Number($('#jmDetailPrice').val()), qty=Number($('#jmDetailQty').val());
    const code=jmCurrentDetailCode;
    if(!jmValidateDetailPrice(true)) return;
    if(!Number.isFinite(qty)||qty<=0){ $('#jmVariantStatus').text('أدخل كمية صحيحة أكبر من صفر.').addClass('text-danger'); return; }
    // الخصم يحسب تلقائيًا من السعر الأساسي؛ PRICE يظل CODE_PRICE كما هو في قاعدة البيانات.
    const discount = Number(code.CODE_PRICE) - price;
    if(discount < 0){ $('#jmVariantStatus').text('سعر البيع لا يمكن أن يتجاوز السعر الأساسي.').addClass('text-danger'); return; }
    // الطلب ينفذ من مخزن واحد محدد مسبقًا في newOrder.stoid.
    if(!code.SELECTED_STO_ID){ $('#jmVariantStatus').text('مخزن الطلب غير محدد. ارجع واختر المخزن أولاً.').addClass('text-danger'); return; }
    if(qty>Number(code.STQTY||0) && Number(code.BOOKING)!==1){ $('#jmVariantStatus').text(`الكمية المتاحة في مخزن الطلب ${code.STQTY} فقط.`).addClass('text-danger'); return; }
    const selected={...code,qty:qty.toFixed(2),dis:discount.toFixed(2),CODE_PRICE:Number(code.CODE_PRICE),PRO_NAME:jmCurrentDetailProduct.PRO_NAME,CAT_NAME:jmCurrentDetailProduct.CAT_NAME,PRO_IMG_LINK:jmCurrentDetailProduct.PRO_IMG_LINK || '/img/product-default-3d.svg',PRODUCT_ID:jmCurrentDetailProduct.PRO_ID};
    const oldCode=jmCurrentDetailEditCode;
    const html=addProductToCart(selected, 0, oldCode || ''); addProductToList(html);
    $('#jmProductDetails').prop('hidden',true).hide().empty(); $('#jmStoreProductGrid, .jm-store-tools, .jm-store-section-head').show();
    jmCurrentDetailProduct=null; jmCurrentDetailCode=null; jmCurrentDetailEditCode=null;
}
$(document).on('change','.jm-variant-select',jmResolveVariant);
$(document).on('input','#jmDetailPrice,#jmDetailQty',jmUpdateDetailTotal);
$(document).on('click','#jmAddConfiguredProduct',jmCommitConfiguredProduct);
$(document).on('click','.jm-detail-back',function(){ $('#jmProductDetails').prop('hidden',true).hide().empty(); $('#jmStoreProductGrid, .jm-store-tools, .jm-store-section-head').show(); jmCurrentDetailProduct=null; jmCurrentDetailCode=null; });
$(document).on('click','.jm-gallery-thumb',function(){ const i=Number($(this).data('index')); $('#jmGalleryMain').attr('src',jmCurrentGalleryImages[i]); $('.jm-gallery-thumb').removeClass('active'); $(this).addClass('active'); });
$(document).on('click','.jm-gallery-prev,.jm-gallery-next',function(){ if(jmCurrentGalleryImages.length<2)return; let i=jmCurrentGalleryImages.indexOf($('#jmGalleryMain').attr('src')); i=(i+( $(this).hasClass('jm-gallery-next')?1:-1)+jmCurrentGalleryImages.length)%jmCurrentGalleryImages.length; $('#jmGalleryMain').attr('src',jmCurrentGalleryImages[i]); $('.jm-gallery-thumb').removeClass('active').eq(i).addClass('active'); });
$(document).on('click','#jmQtyMinus',()=>$('#jmDetailQty').val(Math.max(1,(Number($('#jmDetailQty').val())||1)-1)).trigger('input'));
$(document).on('click','#jmQtyPlus',()=>$('#jmDetailQty').val((Number($('#jmDetailQty').val())||0)+1).trigger('input'));
$(document).on('input', '#jmStoreSearch', renderStorefrontCatalog);
$(document).on('click', '#jmStoreCategories button', function () {
    jmStoreCategory = String($(this).data('category'));
    $('#jmStoreCategories button').removeClass('active');
    $(this).addClass('active');
    renderStorefrontCatalog();
});
$(document).on('click', '.jm-store-add', function () {
    openProductDetails($(this).data('product-id'));
});
$(document).on('click', '.jm-edit-cart-product', function () {
    const code = String($(this).data('code') || '');
    let productId = $(this).data('id');
    if (!productId) {
        productId = Object.keys(productData || {}).find(id => productData[id] && productData[id].CODES && productData[id].CODES[code]);
    }
    if (!productId) { Toast_fire(['e', 'تعذر العثور على بيانات المنتج لتعديله.']); return; }
    openProductDetails(productId, code);
});
$(function () {
    const orderForm = document.getElementById('addorderform');
    if (orderForm && window.MutationObserver) {
        const observer = new MutationObserver(() => {
            if ($('#addorderform').is(':visible')) initializeStorefrontCatalog();
        });
        observer.observe(orderForm, {attributes:true, attributeFilter:['style','class']});
    }
    updateStorefrontCartCount();
    jmBuildOrderWizard();

    // تحميل وتجهيز بيانات الكتالوج فور فتح صفحة إنشاء الطلب، بينما تظل خطوة العميل هي الظاهرة أولًا.
    // بيانات المخزون الفعلية تظل مرتبطة بالمخزن الذي يُحدد لاحقًا من عنوان العميل عند اختيار الصنف.
    if (typeof Go !== 'undefined' && (Go === 'NewOrder' || Go === 'EditOrder')) {
        initializeStorefrontCatalog();
    }
});

/*==============================================================
 * معالج خطوات إنشاء أمر البيع الجديد.
 * يحرك عناصر العميل الأصلية بدل إعادة بناء وظائف البحث/الإضافة.
 *==============================================================*/
var jmOrderWizardStep = 1;
function jmBuildOrderWizard(){
    if (typeof Go !== 'undefined' && Go !== 'NewOrder') return;
    const $root = $('.jm-order-storefront-page');
    const $form = $('#addorderform');
    // أزل وضع الواجهة القديم الذي كان يخفي تفاصيل العميل والسلة والملخص.
    $form.removeClass('jm-step1-active');
    const $layout = $form.find('.jm-store-layout').first();
    if (!$root.length || !$form.length || !$layout.length || $('#jmStepPanel1').length) return;

    const $products = $layout.find('.jm-store-products').first();
    const $cart = $layout.find('.jm-store-cart').first();
    const $summary = $form.find('.jm-store-bottom').first();
    const $clientInfo = $form.find('.invoice-info').first();
    const $panel1 = $('<section id="jmStepPanel1" class="jm-step-panel jm-client-step" aria-label="اختيار العميل"></section>');
    const $panel2 = $('<section id="jmStepPanel2" class="jm-step-panel" aria-label="اختيار المنتجات" hidden></section>');
    const $panel3 = $('<section id="jmStepPanel3" class="jm-step-panel jm-review-step" aria-label="مراجعة السلة وإتمام الحفظ" hidden></section>');

    // النسخة الحالية من Neworder.php لا تحتوي على .jm-order-steps؛
    // لذلك نثبت لوحات الخطوات داخل حاوية الفاتورة مباشرة حتى لا تبقى Detached.
    const $invoice = $form.find('.invoice').first();
    if (!$invoice.length) return;
    $invoice.prepend($panel1, $panel2, $panel3);

    // المرحلة الأولى: بيانات العميل وعنوانه. تبقى النماذج الأصلية وأحداثها كما هي.
    $panel1.append($('#sreachform'), $('#addClientform'), $('#editClientData'));
    $panel2.append($products);
    $panel3.append($clientInfo, $cart, $summary);
    // دمج موعد الشحن وملاحظات العميل داخل بطاقة بيانات العميل بدل عرضهما كبطاقتين منفصلتين.
    const $clientCol = $clientInfo.find('.invoice-col').first();
    const $extraCards = $summary.find('.jm-order-extra').detach();
    if ($extraCards.length) {
        const $clientExtras = $('<div class="jm-client-extra-grid" dir="rtl"></div>');
        $clientExtras.append($extraCards);
        $clientCol.append($clientExtras);
    }
    $panel3.after($form.find('.order-foot').first());
    $layout.remove();

    $panel1.prepend('<div class="jm-client-step-intro"><h5>اختيار العميل</h5><p>اختر العميل ورقم الهاتف وعنوان التوصيل أولًا؛ سيتم تحديد خط السير والشحن وموعده والمخزن من بيانات العنوان.</p></div><div id="jmClientStepSummary" class="jm-client-review" hidden></div>');
    $panel2.prepend('<div class="jm-client-step-intro"><h5>اختيار المنتجات</h5><p>تم تحديد المخزن وبيانات الشحن بناءً على عنوان العميل. أضف المنتجات المطلوبة.</p></div>');
    $panel3.prepend('<div class="jm-client-step-intro"><h5>مراجعة السلة وإتمام الحفظ</h5><p>راجع المنتجات والكميات والأسعار وقيمة الشحن وموعده قبل حفظ أمر البيع.</p></div>');
    // زر واحد يتغير حسب المرحلة: اختيار المنتجات ثم مراجعة أمر البيع.
    const $navigation = $(`
      <div class="jm-wizard-navigation" dir="rtl">
        <span id="jmWizardHint" role="status">ابدأ باختيار العميل وعنوان التوصيل.</span>
        <button type="button" id="jmWizardNext" class="btn btn-primary">اختيار المنتجات <i class="fas fa-arrow-left"></i></button>
      </div>
    `);
    $panel3.after($navigation);
    // شريط الإجراءات يحتفظ بأزرار الحفظ الأصلية دون أزرار السابق/التالي.
    const $taskbar = $('<div class="jm-wizard-taskbar" role="region" aria-label="إجراءات أمر البيع"></div>');
    $form.append($taskbar);
    $taskbar.append($navigation, $form.find('.order-foot').first());
    $panel3.find('.invoice-info').show();
    $form.find('.order-foot').show();
    $panel3.append('<div id="jmClientReview" class="jm-client-review"></div>');
    $panel3.find('.invoice-info .invoice-col .row').first().find('.text-right').append('<button type="button" id="jmChangeClient" class="btn btn-sm btn-outline-primary ml-2">تغيير العميل</button>');

    jmSetOrderWizardStep(1);
    const draft = jmReadOrderDraft();
    if (draft && draft.uid && draft.telid && draft.addid && draft.stoid) jmShowSelectedClient();
}
function jmWizardItemCount(){
    try { return Object.keys(JSON.parse(localStorage.getItem('itemarr') || '{}') || {}).length; }
    catch (_) { return 0; }
}
function jmSetOrderWizardStep(step){
    if (!$('#jmStepPanel1').length) return;
    jmOrderWizardStep = Math.max(1, Math.min(3, Number(step)||1));
    $('#jmStepPanel1,#jmStepPanel2,#jmStepPanel3').prop('hidden', true).hide();
    $(`#jmStepPanel${jmOrderWizardStep}`).prop('hidden', false).show();
    $('.jm-order-step').each(function(index){
        const n=index+1;
        $(this).toggleClass('active',n===jmOrderWizardStep).toggleClass('done',n<jmOrderWizardStep);
        if(n===jmOrderWizardStep) $(this).attr('aria-current','step'); else $(this).removeAttr('aria-current');
    });
    $('#jmWizardPrev').toggle(jmOrderWizardStep>1);
    $('#jmWizardNext').toggle(jmOrderWizardStep<3);
    $('.order-foot').show();
    const labels = {
        1: ['اختيار المنتجات <i class="fas fa-arrow-left"></i>', 'اختر العميل ورقم الهاتف وعنوان التوصيل أولًا.'],
        2: ['مراجعة أمر البيع <i class="fas fa-arrow-left"></i>', 'بعد الانتهاء من اختيار المنتجات، راجع تفاصيل الأمر قبل الحفظ.'],
        3: ['', 'راجع تفاصيل الطلب ثم أتمم الحفظ.']
    };
    $('#jmWizardNext').html(labels[jmOrderWizardStep][0]);
    $('#jmWizardHint').text(labels[jmOrderWizardStep][1]);
    const selectedClient = jmHasSelectedClient();
    $('#edit_shipping,#edit_shippingDate,#edit_clientComment,#del_clientComment').prop('disabled', !selectedClient);
    $('#btnSave_order').toggle(jmOrderWizardStep===3 && selectedClient);
    if (jmOrderWizardStep===3) {
        // إعادة بناء تفاصيل المراجعة من المسودة والسلة الفعلية في كل دخول للخطوة.
        const currentOrder = jmReadOrderDraft() || {};
        const currentItems = JSON.parse(localStorage.getItem('itemarr') || '{}') || {};
        $('#itemorder').html(renderCartItems(currentItems, currentOrder));
        $('#jmStepPanel3 .invoice-info').toggle(selectedClient);
        $('#jmChangeClient').toggle(selectedClient);
        $('#jmStepPanel3 .jm-store-cart,#jmStepPanel3 .jm-store-bottom').show();
        $('#jmStepPanel3 .order-foot').show();
        if(selectedClient) {
            jmShowSelectedClient();
            jmRenderClientReview();
        }
    }
    updateStorefrontCartCount();
}
function jmHasSelectedClient(){
    const order=jmReadOrderDraft()||{};
    return !!(order.uid && order.telid && order.addid && order.telstr && order.addstr && order.stoid && order.shipval !== undefined && order.shipDate);
}
function jmShowSelectedClient(){
    if(!jmHasSelectedClient()) return;
    $('#sreachform,#addClientform,#editClientData').hide();
    $('#jmStepPanel3 .invoice-info').show();
    $('#jmChangeClient').show();
    $('#btnSave_order').toggle(jmOrderWizardStep===3);
    jmRenderClientReview();
    $('#jmClientReview').show();
    $('#jmClientStepSummary').prop('hidden', false);
}
function jmRenderClientReview(){
    const order=jmReadOrderDraft()||{};
    if(!order.uid) return;
    const items=JSON.parse(localStorage.getItem('itemarr')||'{}');
    const rows=Object.values(items);
    const pieces=rows.reduce((sum,item)=>sum+(Number(item.qty)||0),0);
    const subtotal=rows.reduce((sum,item)=>sum+((Number(item.CODE_PRICE)||0)-(Number(item.dis)||0))*(Number(item.qty)||0),0);
    const shipping=Number(order.shipval)||0;
    $('#jmClientStepSummary').prop('hidden', false).html(`
      <div class="jm-client-review-head"><h5>العميل المحدد</h5><button type="button" id="jmEditClientFromStep1" class="btn btn-sm btn-outline-primary">تغيير العميل</button></div>
      <p><strong>${order.uname || ''}</strong><br>${order.telstr || ''}<br>${order.addstr || ''}</p>
      <small>المخزن: ${order.stoid || '—'} · الشحن: ${Number(order.shipval || 0).toFixed(2)} ج.م</small>
    `);
    $('#jmClientReview').html(`
      <div class="jm-client-review-head"><h5>ملخص الطلب</h5><span>${rows.length} صنف · ${pieces} قطعة</span></div>
      <div class="jm-client-review-grid">
        <div><small>قيمة المنتجات بعد الخصم</small><strong>${subtotal.toFixed(2)} ج.م</strong></div>
        <div><small>الشحن</small><strong>${shipping.toFixed(2)} ج.م</strong></div>
        <div class="total"><small>الإجمالي النهائي</small><strong>${(subtotal+shipping).toFixed(2)} ج.م</strong></div>
      </div>
      <p class="jm-client-review-note">المخزن وخط السير وموعد الشحن مأخوذة من عنوان العميل المختار. يُحتسب الشحن مرة واحدة على الطلب.</p>
    `);
}
$(document).on('click','#jmWizardNext',function(){
    if(jmOrderWizardStep===1){
        if(!jmHasSelectedClient()) { if(typeof Toast_fire==='function') Toast_fire(['w','اختر العميل ورقم الهاتف وعنوان التوصيل أولًا']); return; }
        jmSetOrderWizardStep(2);
        initializeStorefrontCatalog();
        return;
    }
    if(jmOrderWizardStep===2){
        if(jmWizardItemCount()<1){ if(typeof Toast_fire==='function') Toast_fire(['w','أضف منتجًا واحدًا على الأقل قبل مراجعة السلة']); return; }
        $('#itemorder').html(renderCartItems(JSON.parse(localStorage.getItem('itemarr')||'{}'),jmReadOrderDraft()||{}));
        jmSetOrderWizardStep(3);
        jmShowSelectedClient();
    }
});
$(document).on('click', '.jm-task-step', function(){
    const target = Number($(this).index('.jm-task-step')) + 1;
    if (target === 2 && !jmHasSelectedClient()) { if(typeof Toast_fire==='function') Toast_fire(['w','اختر العميل والعنوان أولًا']); return; }
    if (target === 3 && (!jmHasSelectedClient() || jmWizardItemCount() < 1)) { if(typeof Toast_fire==='function') Toast_fire(['w','أكمل بيانات العميل وأضف منتجًا واحدًا على الأقل أولًا']); return; }
    jmSetOrderWizardStep(target);
});
$(document).on('keydown', '.jm-task-step', function(e){ if(e.key==='Enter' || e.key===' '){ e.preventDefault(); $(this).trigger('click'); } });

$(document).on('click','#jmEditClientFromStep1,#jmChangeClient',function(){
    $('#jmStepPanel3 .invoice-info,#jmClientReview').hide();
    $('#btnSave_order').hide();
    $('#editClientData').hide();
    $('#jmClientStepSummary').prop('hidden', true).empty();
    $('#sreachform').show();
    jmSetOrderWizardStep(1);
    $('#clisreach').focus();
});


// عرض قائمة المنتجات
function renderProductList() {
	let productHTML = "";
	const productsArray = Object.values(productData); // تحويل القيم إلى مصفوفة
	for (const product of productsArray) { 
		productHTML += `
                <li id="${product.PRO_ID}" class="item proitem" data-catid="${product.CAT_ID}" role="button" tabindex="0">
                    <div class="justm-product-image">
                        <img src="${product.PRO_IMG_LINK || '/img/product-default-3d.svg'}" alt="${product.PRO_NAME}" loading="lazy">
                    </div>
                    <div class="justm-product-info">
                        <div class="justm-product-name">${product.PRO_NAME}</div>
                        <div class="justm-product-category">${product.CAT_NAME || ''}</div>
                        <div class="justm-product-price">${product.MINPRICE == product.MAXPRICE ? product.MINPRICE : `${product.MINPRICE} - ${product.MAXPRICE}`} <small>ج.م</small></div>
                        <span class="justm-product-add">اختيار المنتج <i class="fas fa-arrow-left"></i></span>
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
    renderStorefrontCategories();
    renderStorefrontCatalog();
}

// اضافة المنتجات فى جدول العرض
function addProductToList(productHtml) {
    // renderCartItems يرجع السلة كاملة؛ استبدال tbody يمنع تكرار آخر صف عند الرجوع بين الخطوات.
    $("#itemorder").html(productHtml || '');
    updateStorefrontCartCount();
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
function addProductToCart(peroductRow = null,st = 0,editCode = null) {
    const ulog = JSON.parse(localStorage.getItem("orderlog") || '[]') || [];
    const items = JSON.parse(localStorage.getItem("itemarr") || '{}') || {};
    const newOrder = JSON.parse(localStorage.getItem("newOrder") || '{}') || {};
    const selectedProduct = peroductRow;
    if (!selectedProduct) return renderCartItems(items, newOrder);

    // واجهة الكتالوج الجديدة لا تنشئ نافذة #swl-productes؛ لذلك يمرر مسار التعديل كود الصنف مباشرة.
    // مسار نافذة SweetAlert القديمة يظل مدعومًا عبر data-code.
    const oldCode = editCode !== null ? String(editCode || '') : String($("#swl-productes").attr("data-code") || '');
    const newCode = String(selectedProduct.PRO_CODE || '');
    if (!newCode || !(Number(selectedProduct.qty) > 0)) return renderCartItems(items, newOrder);

    // تعديل صنف موجود: نحتفظ بالنسخة القديمة حتى نسجل الفروق ثم ننقلها للكود الجديد عند تغيير المواصفة.
    if (oldCode && items[oldCode]) {
        updateExistingProduct(items, selectedProduct, oldCode, ulog);
        delete items[oldCode];

        // إذا كانت المواصفة الجديدة موجودة بالفعل، ندمج الكمية لتجنب تكرار نفس كود الصنف.
        if (newCode !== oldCode && items[newCode]) {
            const targetQty = Number(items[newCode].qty) || 0;
            selectedProduct.qty = (Number(selectedProduct.qty) + targetQty).toFixed(2);
            ulog.push(`* تم دمج كمية الصنف ${selectedProduct.PRO_NAME} مع الصنف الموجود بنفس الكود ${newCode}`);
            delete items[newCode];
        }
        items[newCode] = selectedProduct;
    } else {
        // إضافة صنف جديد أو دمجه مع نفس الكود الموجود بالفعل.
        if (items[newCode]) {
            selectedProduct.qty = (Number(selectedProduct.qty) + (Number(items[newCode].qty) || 0)).toFixed(2);
            delete items[newCode];
        }
        logProductAddition(ulog, selectedProduct);
        items[newCode] = selectedProduct;
    }

    localStorage.setItem("itemarr", JSON.stringify(items));
    localStorage.setItem("orderlog", JSON.stringify(ulog));
    localStorage.removeItem("swlitem");
    updateStorefrontCartCount();
    return renderCartItems(items, newOrder);
}

// مقارنة الصنف القديم بالجديد وتسجيل التغييرات في سجل الطلب.
function updateExistingProduct(items, selectedProduct, productCode, ulog) {
    const existingProduct = items[productCode] || {};
    let changes = 0;
    const oldOptions = existingProduct.OPTION_TEXT && typeof existingProduct.OPTION_TEXT === 'object' ? existingProduct.OPTION_TEXT : {};
    const newOptions = selectedProduct.OPTION_TEXT && typeof selectedProduct.OPTION_TEXT === 'object' ? selectedProduct.OPTION_TEXT : {};
    const optionKeys = new Set([...Object.keys(oldOptions), ...Object.keys(newOptions)]);

    optionKeys.forEach(key => {
        const oldValue = oldOptions[key] ?? '';
        const newValue = newOptions[key] ?? '';
        if (String(oldValue) !== String(newValue)) {
            changes++;
            ulog.push(`* تم تغيير مواصفة ${existingProduct.PRO_NAME || selectedProduct.PRO_NAME} من ${oldValue || 'بدون'} إلى ${newValue || 'بدون'}`);
        }
    });

    const oldQty = Number(existingProduct.qty) || 0;
    const newQty = Number(selectedProduct.qty) || 0;
    if (oldQty !== newQty) {
        changes++;
        ulog.push(`* تم تغيير الكمية من ${oldQty.toFixed(2)} إلى ${newQty.toFixed(2)} لـ ${existingProduct.PRO_NAME || selectedProduct.PRO_NAME}`);
    }

    const oldDiscount = Number(existingProduct.dis) || 0;
    const newDiscount = Number(selectedProduct.dis) || 0;
    if (oldDiscount !== newDiscount) {
        changes++;
        ulog.push(`* تم تغيير الخصم من ${oldDiscount.toFixed(2)} إلى ${newDiscount.toFixed(2)} لـ ${existingProduct.PRO_NAME || selectedProduct.PRO_NAME}`);
    }

    if (String(existingProduct.PRO_CODE || productCode) !== String(selectedProduct.PRO_CODE || productCode)) {
        changes++;
        ulog.push(`* تم تغيير كود/مواصفة الصنف من ${productCode} إلى ${selectedProduct.PRO_CODE}`);
    }

    if (changes === 0) logProductAddition(ulog, selectedProduct);
    return changes;
}

function logProductAddition(ulog, product) {
    ulog.push(
        `* تم إضافة المنتج ${product["PRO_NAME"]} - ${Object.values(product["OPTION_TEXT"] || {}).toString()} - الكمية: ${product["qty"]} - الخصم: ${product["dis"]}`
    );
}

function renderCartItems(items, newOrder) {
    let html = '';
    let totalPrice = 0;
    const entries = Object.entries(items || {});
    for (const [code, product] of entries) {
        const options = product.OPTION_TEXT ? Object.values(product.OPTION_TEXT).join(' · ') : '';
        const qty = Number(product.qty) || 0;
        const basePrice = Number(product.CODE_PRICE) || 0;
        const discount = Number(product.dis) || 0;
        const unitPrice = basePrice - discount;
        const lineTotal = unitPrice * qty;
        totalPrice += lineTotal;
        const image = jmEscape(product.PRO_IMG_LINK || '/img/product-default-3d.svg');
        const productId = product.PRODUCT_ID || product.PRO_ID || Object.keys(productData || {}).find(id => {
            const source = productData[id];
            return source && source.CODES && source.CODES[code];
        }) || '';
        html += `
          <article class="jm-cart-item itemorder" data-code="${jmEscape(code)}">
            <div class="jm-cart-item-image"><img src="${image}" alt="${jmEscape(product.PRO_NAME || 'منتج')}" loading="lazy" onerror="this.onerror=null;this.src='/img/product-default-3d.svg';"></div>
            <div class="jm-cart-item-main">
              <div class="jm-cart-item-title-row"><div><strong>${jmEscape(product.PRO_NAME || 'منتج')}</strong><small>${jmEscape(product.CAT_NAME || '')}</small></div><span class="jm-cart-item-code">${jmEscape(code)}</span></div>
              ${options ? `<div class="jm-cart-item-options">${jmEscape(options)}</div>` : ''}
              <div class="jm-cart-item-data">
                <span>الكمية <b>${qty}</b></span>
                <span>سعر الوحدة <b>${unitPrice.toFixed(2)} ج.م</b></span>
                ${discount > 0 ? `<span>خصم الوحدة <b>${discount.toFixed(2)} ج.م</b></span>` : ''}
                <strong class="jm-cart-item-total">${lineTotal.toFixed(2)} ج.م</strong>
              </div>
              <div class="jm-cart-item-actions">
                <button type="button" data-id="${jmEscape(productId)}" data-code="${jmEscape(code)}" class="btn jm-edit-cart-product"><i class="fas fa-pen"></i> تعديل</button>
                <button type="button" data-code="${jmEscape(code)}" class="btn jm-delete-cart-product"><i class="fas fa-trash-alt"></i> حذف</button>
              </div>
            </div>
          </article>`;
    }
    const totalWithShipping = totalPrice + (Number(newOrder && newOrder.shipval) || 0);
    $('#orderprice').text(totalPrice.toFixed(2));
    $('#ordertotal').text(totalWithShipping.toFixed(2));
    return html || '<div class="jm-cart-empty-inline"><i class="fas fa-basket-shopping"></i><strong>السلة فارغة</strong><span>ارجع لاختيار المنتجات وأضف الأصناف المطلوبة.</span></div>';
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
    const orderData = jmReadOrderDraft();
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
    // قد لا يرجع الاستعلام سجل مخزون للصنف داخل المخزن المحدد؛ اعتبر الكمية صفرًا بدل إيقاف اختيار الصنف بخطأ JavaScript.
    const storeStocks = code && code.STORSQTY && typeof code.STORSQTY === 'object'
        ? code.STORSQTY
        : {};
    const storeStockValue = Object.prototype.hasOwnProperty.call(storeStocks, stid)
        ? Number(storeStocks[stid])
        : 0;
    const storeStock = Number.isFinite(storeStockValue) ? storeStockValue : 0;
    const oldQuantity = Number(getOldQuantity(code && code.PRO_CODE)) || 0;

    return {
        ...code,
        qty: 0,
        dis: 0,
        PRO_NAME: product.PRO_NAME,
        CAT_NAME: product.CAT_NAME,
        STQTY: Math.max(0, storeStock - oldQuantity),
    };
}

// الحصول على الكمية القديمة من localStorage
function getOldQuantity(code) {
    const itemArray = JSON.parse(localStorage.getItem('itemarr'));
    return itemArray && itemArray[code] ? (parseFloat(itemArray[code]['qty']) || 0) : 0;
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