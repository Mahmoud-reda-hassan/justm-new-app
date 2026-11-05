let fiArr = {},
	rqFil = rqof ;
/*==========================================================
     التأكد من وجود المتغير filt بال كوكيز
==========================================================*/
 if (!$.cookie('filt_'+wls) || $.cookie('filt_'+wls) == '') {
	$.removeCookie('filt_'+wls); 
	$.cookie('filt_'+wls, JSON.stringify(filobj), {expires: 1, path: '/'});
}; 
document.addEventListener("DOMContentLoaded", function() {
	
	$(document).on("click", "#btnfilter", () => {
	    initializeFilterOptions();
	    const filterHtml = $('#filterhtml');
	    const swlFilterContent = `<div id="swlfilter" class="m-0 p-0">${filterHtml.html()}</div>`;

	    Swal.fire({
	        title: 'فرز حسب',
	        html: swlFilterContent,
	        position: 'top-end',
	        didOpen: () => { 
	        	setupFilterModal();
	        },
	        showClass: { popup: 'animate__animated animate__fadeInRight animate__faster' },
	        hideClass: { popup: 'animate__animated animate__fadeOutRight animate__faster' },
	        grow: 'column',
	        width: 300,
	        showConfirmButton: false,
	        showCloseButton: true
	    });
	});
});

// إعداد الخيارات المبدئية في قائمة الفلترة
function initializeFilterOptions() {
    $('#filterhtml #fiorderType').html(orderfilter['orderType']);
    $('#filterhtml #fishippType').html(orderfilter['shippType']);
    $('#filterhtml #fizones').html(orderfilter['zones']);
    $('#filterhtml #fiexists').html(orderfilter['exists']);
    $('#filterhtml #fistatuse').html(orderfilter['statuse']);
    $('#filterhtml #fiproCat').html('<option value="-1">جميع الفئات</option>' + orderfilter['proCat']);
    $('#filterhtml #fiproducts').html(orderfilter['products']);
}

// إعداد نافذة الفلترة عند الفتح
function setupFilterModal() {
    const filterData = getFilterDataFromCookie();
    populateFilterOptions(filterData);
    initializeDatePicker();
    initializeSelect2();
    handleFilterReset(filterData);
    bindFilterChangeEvents(filterData);
    
}

// استرجاع بيانات الفلترة من التخزين المحلي (الكوكيز)
function getFilterDataFromCookie() {
    return JSON.parse($.cookie(`filt_${wls}`)) || {};
}
// ملء الحقول بناءً على بيانات التخزين
function populateFilterOptions(filterData) {
    Object.entries(filterData).forEach(([key, value]) => {
        $(`#swlfilter #fi${key}`).val(value.id).change();
        if (key === 'fidateType' && value.id) {
            $('#swlfilter .fild').attr('disabled', false).val(filterData['dateRange']?.val || '').change();
        }
    });
}

// تهيئة حقول التاريخ باستخدام مكتبة التاريخ
function initializeDatePicker() {
    datepick($("#swlfilter .fild"), undefined, undefined, undefined, false, true, false);
    $("#swlfilter .fild").attr('disabled', true);
}


////////////
function filterOrderlist() {
    const cookieKey = `filt_${wls}`;
    const filterData = $.cookie(cookieKey) ? JSON.parse($.cookie(cookieKey)) : null;
    if (!filterData) {
        resetFilterUI();
        return;
    }

    let badges = generateBadges(filterData);

    if (badges) {
        updateFilterUI(badges);
        dataTableReload();
    } else {
        resetFilterUI();
    }
}

// إعادة ضبط واجهة الفلاتر
function resetFilterUI() {
    $('.fiinfo').html('');
    $('.fiinfo').parent('div').fadeOut(1000);
}

// تحديث واجهة الفلاتر بالبادجات
function updateFilterUI(badges) {
    $('.fiinfo').html(badges);
    $('.fiinfo').parent('div').fadeIn(1000);
}

// توليد البادجات بناءً على بيانات الفلترة
function generateBadges(filterData) {
    let badges = '';

    for (const [key, value] of Object.entries(filterData)) {
        if (key === 'products' && value['id']) {
            badges += generateProductBadges(value);
        } else if (key === 'dateRange' && value['id'].length > 0) {
        	alert(value['id'])
            badges += generateDateRangeBadge(value, filterData['fidateType']);
        } else if (value?.['val']) {
            badges += generateDefaultBadge(key, value);
        }
    }

    return badges;
}

// إنشاء بادجات للفلاتر العامة
function generateDefaultBadge(key, value) {
    return `
        <span id="${key}" 
              data-id="${key}" 
              onclick="clearfilter($(this))" 
              class="badge badge-danger ficlose" 
              style="margin:2px 0px 0 2px">
            ${value['val']} ×
        </span>`;
}

// إنشاء بادجات لفلاتر المنتجات
function generateProductBadges(products) {
    let badges = '';

    for (let i = 0; i < products['id'].length; i++) {
        badges += `
            <span id="${products['id'][i]}" 
                  data-name="${products['val'][i]}" 
                  data-id="products" 
                  onclick="clearfilter($(this))" 
                  class="badge badge-danger ficlose" 
                  style="margin:2px 0px 0 2px">
                ${products['val'][i]} ×
            </span>`;
    }

    return badges;
}

// إنشاء بادجات لفلترة النطاق الزمني
function generateDateRangeBadge(dateRange, dateType) {
    const [from, to] = dateRange['id'].split(" - ");;
    
    const label = dateType?.['val'] || 'تاريخ';

    if (from !== to) {
        return `
            <span data-id="dateRange" 
                  onclick="clearfilter($(this))" 
                  class="badge badge-danger ficlose" 
                  style="margin:2px 0px 0 2px">
                ${label} | من: ${from} الى: ${to} ×
            </span>`;
    }

    return `
        <span data-id="dateRange" 
              onclick="clearfilter($(this))" 
              class="badge badge-danger ficlose" 
              style="margin:2px 0px 0 2px">
            ${label} | ${to} ×
        </span>`;
}


// تهيئة حقول Select2
function initializeSelect2() {
    const select2Config = { dir: "rtl", allowClear: true, dropdownParent:$('.swal2-popup') };
    $('#swlfilter .select2ot').select2({ ...select2Config, placeholder: "اختار نوع الامر" });
    $('#swlfilter .select2st').select2({ ...select2Config, placeholder: "اختار الحالة" });
    $('#swlfilter .select2ex').select2({ ...select2Config, placeholder: "اختار الحالة الادارية" });
    $('#swlfilter .select2sht').select2({ ...select2Config, placeholder: "اختار نوع الشحن" });
    $('#swlfilter .select2zon').select2({ ...select2Config, placeholder: "اختار خط السير" });
    $('#swlfilter .select2pro').select2({ ...select2Config, placeholder: "اختار المنتج" });
    $('#swlfilter .select2proc').select2({ ...select2Config, placeholder: "اختار فئة المنتج" });
    $('#swlfilter .select2dt').select2({ ...select2Config, placeholder: "اختار نوع التاريخ" });
}

// التعامل مع إعادة تعيين الفلاتر
function handleFilterReset(filterData) {
    $('#btnCancel_filter').click(() => {
        // إعادة تعيين الحقول
        $('.fil').each(function () {
            $(this).val('').trigger('change'); // مسح الحقول مع التأثير
        });

        // تحديث البيانات المحلية
        Object.keys(filterData).forEach(key => {
            filterData[key] = { id: '', val: '' };
        });

        // تحديث الكوكيز
        updateFilterDataInCookie(filterData);

        // تحديث عرض الفلاتر
        filterOrderlist();

        // إعادة تحميل الجدول مع تحديث البيانات
        dataTableReload();
    });
}

// ربط الأحداث لتحديث الفلاتر عند التغيير
function bindFilterChangeEvents(filterData) {
    const fieldsToBind = [
        'fiorderType', 'fishippType', 'fizones', 'fiexists', 
        'fistatuse', 'fiproCat', 'fiproducts', 'fidateType', 'dateRange'
    ];

    fieldsToBind.forEach(field => {
        $(`#swlfilter #${field}`).change((e) => handleFilterChange($(e.target), filterData));
    });
}

// التعامل مع التغييرات في الفلاتر
function handleFilterChange(element, filterData) {
    const key = element.attr('name');
    const value = element.val();
    let id, val;

    if (key === 'dateType') {
        const isEnabled = !!value;
        $('#swlfilter #dateRange').attr('disabled', !isEnabled).val(isEnabled ? filterData['dateRange']?.val : '').change();
    }

    if (value) {
        val = (key === 'products') ? element.children(':selected').map((_, el) => $(el).text()).get() : element.children(':selected').text();
        id = value;
    } else {
        id = val = '';
    }
    
    filterData[key] = { id, val };
    updateFilterDataInCookie(filterData);
    filterOrderlist();
}

// تحديث بيانات الفلاتر في التخزين المحلي
function updateFilterDataInCookie(filterData) {
    $.removeCookie(`filt_${wls}`);
    $.cookie(`filt_${wls}`, JSON.stringify(filterData), { expires: 1, path: '/' });
}

// تحديث بيانات الفلتر في الكوكيز
/*function updateFilterDataInCookie(filterData) {
    $.removeCookie('filt_' + wls); // إزالة الكوكي الحالي
    $.cookie('filt_' + wls, JSON.stringify(filterData), { expires: 1, path: '/' }); // حفظ الكوكي الجديد
}*/

// إعادة تحميل الجدول مع تحديث البيانات
function dataTableReload1() {
    async function zx(){
			await table.clear();
			await fetchOrders() ;
    }
    zx();
	
}

/*=========================================================
	وظيفة الفيلتر
=========================================================*/
function clearfilter(x){
	let cookiesrray=JSON.parse($.cookie('filt_'+queryString.replace(/&/g,'_').replace(/=/g,'_').replace('?','_')));
	if(x){
	    let i = x.attr('data-id');
		let id = x.attr('id');
		let n = x.attr('data-name');
	    if(typeof cookiesrray[i]['id'] !=="string" && i !=='fidateType' &&  i !=='dateRange'){
			cookiesrray[i]['id'].splice(cookiesrray[i]['id'].indexOf(id),1);
			cookiesrray[i]['val'].splice(cookiesrray[i]['val'].indexOf(n),1);
		}else if(i =='dateType' ||  i =='dateRange'){
			cookiesrray['dateType']['id'] = "";
			cookiesrray['dateType']['val'] = "";
			cookiesrray['dateRange']['id'] = "";
			cookiesrray['dateRange']['val'] = "";
		} else {
			cookiesrray[i]['id'] = "";
			cookiesrray[i]['val'] = "";
		}
	    
	} else {
		for (const fi in cookiesrray) {
			cookiesrray[fi]['id']='';
			cookiesrray[fi]['val']='';
		}
	}
	$.removeCookie('filt_'+queryString.replace(/&/g,'_').replace(/=/g,'_').replace('?','_'));
	$.cookie('filt_'+queryString.replace(/&/g,'_').replace(/=/g,'_').replace('?','_'),JSON.stringify(cookiesrray),{ expires: 7, path: '/' });
	filterOrderlist();
	dataTableReload();
}