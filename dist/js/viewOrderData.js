
displayOrderViewData = async (orderID, btnapp) => {
    try {
        // إظهار التحميل
        showLoadingState();
        
        // جلب البيانات
        const obj = await orderdataview(orderID);
        
        if (!obj || !obj[orderID]) {
            throw new Error("بيانات الطلب غير موجودة");
        }
        
        const order = obj[orderID];
        
        // بناء التصميم
        const html = createModernOrderDesign(order, btnapp);
        
        // العرض
        displayOrderWithAnimation(html, order.orderData?.order_status);
        
        // إعداد الأزرار
        setupModernButtons(btnapp);
        
        hideSpinner();
        
    } catch (error) {
        showModernError(error.message);
        hideSpinner();
    }
};

// ============ دوال التحميل والعرض ============

function showLoadingState() {
    $('.DOV').html(`
        <div class="modern-loading">
            <div class="spinner-container">
                <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>
                <div class="spinner-text mt-3">
                    <h5 class="text-primary">جاري تحميل بيانات الطلب</h5>
                    <p class="text-muted mb-0">يرجى الانتظار...</p>
                </div>
            </div>
        </div>
    `).show();
    $('.DOL, .DCU').hide();
}

function displayOrderWithAnimation(html, statusId) {
    $('.DOV').html(html).hide().fadeIn(500);
    $('.DOL, .DCU').hide();
    
    if (parseInt(statusId) < 3) {
        $('.hidecol').hide();
    } else {
        $('.hidecol').show();
    }
}

function showModernError(message) {
    $('.DOV').html(`
        <div class="modern-error">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle fa-4x text-danger"></i>
            </div>
            <div class="error-content">
                <h3 class="text-danger mb-3">حدث خطأ</h3>
                <p class="lead">${message}</p>
                <div class="error-actions mt-4">
                    <button class="btn btn-primary" onclick="window.location.reload()">
                        <i class="fas fa-redo me-2"></i>
                        إعادة المحاولة
                    </button>
                    <button class="btn btn-outline-secondary ms-2" onclick="history.back()">
                        <i class="fas fa-arrow-right me-2"></i>
                        العودة
                    </button>
                </div>
            </div>
        </div>
    `).show();
    $('.DOL, .DCU').hide();
}

// ============ دوال مساعدة عامة ============

function formatSafePrice(price) {
    try {
        if (price === null || price === undefined || price === '') return '0.00';
        
        const num = parseFloat(price);
        if (isNaN(num)) return '0.00';

let formatted = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
}).format(num);
// "1,234,567.89"
        return formatted; //num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    } catch (error) {
        console.warn('Error formatting price:', error);
        return '0.00';
    }
}

function calculateSafeTotal(products) {
    if (!products || typeof products !== 'object') return 0;
    
    let total = 0;
    for (let key in products) {
        if (products.hasOwnProperty(key)) {
            const p = products[key];
            if (p) {
                const price = parseFloat(p.CODE_PRICE) || 0;
                const discount = parseFloat(p.dis) || 0;
                const qty = parseInt(p.qty) || 0;
                total += (price - discount) * qty;
            }
        }
    }
    return total;
}

function getStatusBadgeClass(statusId) {
    const id = parseInt(statusId) || 0;
    
    const classes = {
        1: 'warning',
        2: 'info', 
        3: 'primary',
        4: 'secondary',
        5: 'success',
        6: 'dark',
        7: 'danger'
    };
    
    return classes[id] || 'secondary';
}

function getShippingDate(shipDate, shipWaiting) {
	alert(shipWaiting)
    if (shipWaiting) return '<span class="text-warning">انتظار</span>';
    return shipDate || 'غير محدد';
}

// ============ دالة بناء التصميم الرئيسي ============

function createModernOrderDesign(order, btnapp) {
    const orderData = order.orderData || {};
    const products = order.orderPro || {};
    const actions = order.orderAction || {};
    
    // الحسابات
    const subtotal = calculateSafeTotal(products);
    const shipping = parseFloat(orderData.shipval) || 0;
    const total = formatSafePrice(parseFloat(subtotal) + shipping);
    return `
        <div class="modern-order-container">
            <!-- Header -->
            ${createOrderHeader(orderData, total)}
            
            <div class="row mt-4">
                <!-- Left Column -->
                <div class="col-lg-8">
                    
                    ${createProductsTableSection(products)}
                    ${createOrderSummarySection(subtotal, shipping, total)}
                    
                </div>
                
                <!-- Right Column -->
                <div class="col-lg-4">
                    <div class="sidebar-sticky">
                        ${createStatusTimeline(orderData)}
                        ${createRecentActionsSection(actions)}
                    </div>
                </div>
            </div>
            
            <!-- Control Panel -->
            
            ${createControlPanelSection(btnapp)}
        </div>
    `;
}

// ============ إعداد الأزرار ============

function setupModernButtons(btnapp) {
   
    //if (!btnapp) return;
    
   //$('#btnhead').remove();
    
    $('#btnfilter').after('<div id="btnhead" class="btnhead btn-group">' + btnapp + '<button id="btnback" type="button" class="btn btn-app-row w-100 h-100 f-5 btn-primary btn-xs btnback"><i class="fas fa-arrow-left"></i></button></div>');
    //btnapp.appendTo("#btnhead");
    $('.btnhead .btn-xs').attr('style', '');
    $('.btnhead .btnOview').remove();
    const btnGroupHtml = $('.btnhead .btn-group-vertical').html();
    $('.btnhead .btn-group-vertical').remove();
    $('.btnhead .btnOedit').after(btnGroupHtml);
    //$('.btnhead .lastbtn').after('<button id="btnback" type="button" class="btn btn-primary btn-xs badge btnback"><i class="fas fa-arrow-left"></i></button>');
    $('.btnhead .btn-xs').attr('style', 'margin-right:3px');
    $('.btnh').hide();
    
    $('.btnh, #fiinfo').hide();
    
    $(document).off('click', '#btnback').on('click', '#btnback', function() {
        $('.DOV').hide();
        $('.DOL').show();
        //$('.btnh, #fiinfo').show();
        $('#btnhead').remove();
    });
}


// ============ Header Section ============
/*function createOrderHeader(orderData, total) {
    const statusColor = orderData.status_color; // getStatusBadgeClass(orderData.order_status);
    statusClass = statusColor.replace(/^alert/, "bg");
    const statusText = orderData.status_name;
    
    return `
        <div class="order-header-card" style="border: 1px solid #ddd; border-radius: 10px;">
            <div class="header-content">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-start mb-3 text-secondary">
                            <div class="header-icon">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <div class="header-text mr-3">
                                <h1 class="order-title">طلب #${orderData.sub_id || ''}</h1>
                                <div class="order-meta">
                                    <span class="meta-item">
                                        <i class="far fa-calendar"></i>
                                        ${orderData.orderDate || ''}
                                    </span>
                                    <span class="meta-divider">•</span>
                                    <span class="meta-item">
                                        <i class="fas fa-user"></i>
                                        ${orderData.uname || ''}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="info-card mb-3">
                                    <div class="info-label">نوع الطلب</div>
                                    <div class="info-value">${orderData.ordertype || 'عادي'}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-card mb-3">
                                    <div class="info-label">تاريخ الشحن</div>
                                    <div class="info-value">
                                        ${getShippingDate(orderData.shipDate, orderData.shipWaiting)}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-card mb-3">
                                    <div class="info-label">الموقف الإداري</div>
                                    <div class="info-value">${orderData.exists_name || ''}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 mt-4 mt-lg-0">
                        <div class="total-section">
                            <div class="status-badge ${statusClass}">
                                <i class="fas fa-flag me-2"></i>
                                ${statusText}
                            </div>
                            <div class="total-amount">
                                <div class="amount-text">${total}</div>
                                <div class="amount-label">القيمة الإجمالية</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}*/

function createOrderHeader(orderData, total) {

    const statusColor = orderData.status_color || "bg-secondary";
    const statusClass = statusColor.replace(/^alert/, "bg");
    const statusText  = orderData.status_name || "";

    return `
    <div class="order-header-card0 shadow-sm mb-4" 
         style="border-radius:5px; overflow:hidden; border:1px solid #f0f0f0;">

        <!-- Top Bar -->
        <div class="px-4 py-3 d-flex justify-content-between align-items-center"
             style="background: linear-gradient(135deg,#f8f9fa,#ffffff);">

            <div>
                <h4 class="mb-1 font-weight-bold">
                    طلب رقم #${orderData.sub_id || '-'}
                </h4>
                <div class="text-muted small">
                    <i class="far fa-calendar ml-1"></i>
                    ${orderData.orderDate || '-'}
                    <span class="mx-2">|</span>
                    <i class="fas fa-user ml-1"></i>
                    ${orderData.uorderby || '-'}
                </div>
            </div>

            <div class="text-right">
                <div class="badge ${statusClass} px-3 py-2" 
                     style="font-size:13px; border-radius:30px;">
                    <i class="fas fa-circle ml-1" style="font-size:8px;"></i>
                    ${statusText}
                </div>
            </div>

        </div>

        <!-- Body -->
        <div class="px-4 py-4 bg-white1" style="background-color: #ebf3f2;e;">

            <div class="row text-center">

                <div class="col-md-3 mb-3">
                    <div class="text-muted small">نوع الطلب</div>
                    <div class="font-weight-bold">
                        ${orderData.ordertype || 'عادي'}
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="text-muted small">تاريخ الشحن</div>
                    <div class="font-weight-bold">
                        ${orderData.shipDate}
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="text-muted small">الموقف الإداري</div>
                    <div class="font-weight-bold">
                        ${orderData.exists_name || '-'}
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="text-muted small">القيمة الإجمالية</div>
                    <div style="font-size:22px; font-weight:700; color:#28a745;">
                        ${total}
                    </div>
                </div>

            </div>
		 <!-- Order & Client Info -->
            <div class="row mb-3">
				<div class="col-md-6">
                    <div class="border1 rounded p-3 h-100 bg-light">
                        <h6 class="text-primary mb-3">بيانات العميل</h6>
                        <p class="mb-1"><strong>الاسم:</strong> ${orderData.uname}</p>
                        <p class="mb-1"><strong>الهاتف:</strong> <a href="tel:${orderData.telstr || ''}" class="phone-link">${orderData.telstr || ''}</a></p>
                        <p class="mb-0"><strong>البريد الالكترونى:</strong><a href="mailto:${orderData.umail || ''}" class="email-link">${orderData.umail || ''}</a></p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border1 rounded p-3 h-100 bg-light">
                        <h6 class="text-primary mb-3">بيانات الشحن</h6>
                    	<p class="mb-0"><strong>العنوان:</strong> ${orderData.addstr}</p>
                        <p class="mb-0"><strong>موعد الشحن:</strong>${shippingDateStr(orderData.shipDate, orderData.shipWaiting,orderData['DELIVERED_DATE'])}</p>
                    </div>
                </div>
            </div>
            ${orderData.cliComment ? `
            <div class="comment-section mt-4">
                <div class="comment-header">
                    <i class="fas fa-comment-dots me-2"></i>
                    ملاحظات العميل
                </div>
            	<div class="comment-text">${orderData.cliComment}</div>
            </div>` : ''}
        </div>
    </div>
    `;
    console.log(orderData);
}

// ============ Customer Info Section ============
function createCustomerInfoSection(orderData) {
    /*return `
        <div class="customer-info-card" style="border: 1px solid #ddd; border-radius: 10px;">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user-circle me-2"></i>
                    معلومات العميل
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="info-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">الاسم</div>
                                <div class="info-value">${orderData.uname || ''}</div>
                            </div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">الهاتف</div>
                                <div class="info-value">
                                    <a href="tel:${orderData.telstr || ''}" class="phone-link">
                                        ${orderData.telstr || ''}
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">البريد الإلكتروني</div>
                                <div class="info-value">
                                    <a href="mailto:${orderData.umail || ''}" class="email-link">
                                        ${orderData.umail || ''}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="address-section">
                            <div class="info-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="address-content">
                                <div class="info-label">العنوان</div>
                                <div class="address-text">${orderData.addstr || 'لا يوجد عنوان'}</div>
                            </div>
                        </div>
                        
                        ${orderData.cliComment ? `
                            <div class="comment-section mt-4">
                                <div class="comment-header">
                                    <i class="fas fa-comment-dots me-2"></i>
                                    ملاحظات العميل
                                </div>
                                <div class="comment-text">${orderData.cliComment}</div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;*/
}

// ============ Products Table Section ============
function createProductsTableSection(products) {
    if (!products || Object.keys(products).length === 0) {
        return `
            <div class="empty-products-card" style="border: 1px solid #ddd; border-radius: 10px;">
                <div class="empty-content">
                    <i class="fas fa-box-open fa-3x"></i>
                    <h5>لا توجد منتجات</h5>
                    <p class="text-muted">لم يتم إضافة أي منتجات لهذا الطلب</p>
                </div>
            </div>
        `;
    }
    
    const productCount = Object.keys(products).length;
    let tableRows = '';
    let rowNumber = 1;
    
    for (let key in products) {
        const p = products[key];
        if (!p) continue;
        
        // معالجة البيانات بشكل آمن
        const proName = p.PRO_NAME || 'منتج';
        const proCode = p.PRO_CODE || '';
        
        // التعامل مع OPTION_TEXT بشكل آمن
        let optionText = '';
        if (p.OPTION_TEXT) {
            try {
                if (typeof p.OPTION_TEXT === 'string') {
                    optionText = p.OPTION_TEXT;
                } else if (Array.isArray(p.OPTION_TEXT)) {
                    optionText = p.OPTION_TEXT.join(', ');
                } else if (typeof p.OPTION_TEXT === 'object') {
                    optionText = JSON.stringify(p.OPTION_TEXT);
                } else {
                    optionText = String(p.OPTION_TEXT);
                }
            } catch (error) {
                optionText = '';
            }
        }
        
        // الحسابات
        const qty = parseInt(p.qty) || 0;
        const deliveredQty = parseInt(p.deliveredQty) || 0;
        const returnedQty = parseInt(p.returnedQty) || 0;
        const price = parseFloat(p.CODE_PRICE) || 0;
        const discount = parseFloat(p.dis) || 0;
        const itemTotal = (price - discount) * qty;
        
        // حالة المخزون
        const stockQty = parseInt(p.STQTY) || 0;
        const hasStock = stockQty > 0;
        const stockClass = hasStock ? 'success' : 'danger';
        const stockText = hasStock ? stockQty : 'غير متوفر';
        
        // نسبة التسليم
        const deliveryPercent = qty > 0 ? Math.round((deliveredQty / qty) * 100) : 0;
        const deliveryClass = deliveryPercent === 100 ? 'success' : 
                             deliveryPercent >= 50 ? 'warning' : 'danger';
        
        tableRows += `
            <tr class="product-row text-nowrap">
                <td>
                    <div class="d-flex align-items-center">
                        <div class="product-image me-3">
                            ${p.PRO_IMG_LINK ? `
                                <img src="${p.PRO_IMG_LINK}" alt="${proName}" class="img-fluid">
                            ` : `
                                <div class="image-placeholder">
                                    <i class="fas fa-box"></i>
                                </div>
                            `}
                        </div>
                        <div class="product-details">
                            <h6 class="product-name">${proName}</h6>
                            <small class="text-muted d-block">${proCode}</small>
                            ${optionText ? `
                                <div class="product-options">
                                    <small class="text-muted">${optionText}</small>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </td>
                <td class="text-center">
                    <span class="quantity-badge">${qty}</span>
                </td>
                <td class="text-end">${formatSafePrice(price)}</td>
                <td class="text-end text-danger">${formatSafePrice(discount)}</td>
                <td class="text-end fw-bold text-primary">${formatSafePrice(itemTotal)}</td>
                <td class="text-center">
                    <span class="stock-badge ${stockClass}">${stockText}</span>
                </td>
                <td>
                    <div class="delivery-progress">
                        <div class="progress-bar-container">
                            <div class="progress-bar ${deliveryClass}" style="width: ${deliveryPercent}%"></div>
                        </div>
                        <small>${deliveredQty}/${qty}</small>
                    </div>
                </td>
                <td class="text-center">
                    <span class="return-badge">${returnedQty}</span>
                </td>
            </tr>
        `;
        
        rowNumber++;
    }
    
    return `
        <div class="products-table-card" style="border: 1px solid #ddd; border-radius: 10px; background-color: #ebf3f2;e;">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-boxes me-2"></i>
                        المنتجات
                    </h5>
                    <div class="products-count">
                        <span class="badge">${productCount} منتج</span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th width="25%">المنتج</th>
                                <th class="text-center">الكمية</th>
                                <th class="text-end">السعر</th>
                                <th class="text-end">الخصم</th>
                                <th class="text-end">الإجمالي</th>
                                <th class="text-center">المخزون</th>
                                <th>التسليم</th>
                                <th class="text-center">مرتجع</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${tableRows}
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="table-summary">
                            <i class="fas fa-info-circle me-2"></i>
                            عرض ${productCount} منتج
                        </div>
                    </div>
                    <div class="col-md-6 text-end">
                        <button class="btn btn-sm btn-outline-primary me-2">
                            <i class="fas fa-print"></i>
                        </button>
                        <button class="btn btn-sm btn-primary">
                            <i class="fas fa-file-export"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
		
    `;
}
 
 // ============ Status Timeline ============
function createStatusTimeline(orderData){
	
    let movements = [];
	let data = orderData.movement_history;
	let statusId = orderData.order_status;
	let statusName = orderData.status_name;
	let items = '';
	let percentage = 0;
	let statColor = getStatusBadgeClass(statusId);
	
    try {
    	
	    if(!data){
			items = `<div class="timeline-step">
	          <div class="timeline-dot bg-info"></div>
	          <div class="timeline-content">
	            <div class="timeline-time"></div>
	            <div class="timeline-label text-info">${statusName}</div>
	          </div>
	        </div>`;
	        
		} else {
			movements = JSON.parse(data);
			items = movements.map(entry => {
		      let [action, date, time] = entry.split(" | ");
		      time = time?.slice(0, 5); // HH:MM
		      const colorClass = getBootstrapColorClass(action);
		      const textColorClass = colorClass.replace(/^bg/, "text"); 
			  const precentInt = shippingPercent(action);
			  percentage = precentInt;
		      return `<div style="display: flex; margin-bottom: 25px; margin-right: -56px; align-items: flex-start;">
	                        <div  style="width: 40px; display: flex; justify-content: center; position: relative;">
	                            <div class="${colorClass}" style="width: 20px; height: 20px; border-radius: 50%; background: #007bff; border: 4px solid white; box-shadow: 0 0 0 1px #007bff40, 0 2px 5px rgba(0,0,0,0.2); position: relative; z-index: 2;"></div>
	                        </div>
	                        <div style="flex: 1; text-align: right; padding-right: 15px; margin-top:-13px ;">
	                            <div class="${textColorClass}" style="font-weight: bold; color: #007bff; margin-bottom: 5px; font-size: 1.1rem;">
	                                ${action}
	                            </div>
	                            <div style="color: #666; font-size: 0.85rem; margin-bottom: 5px;"><i class="fas fa-clock me-1"></i> ${time} - ${date} </div>
	                        </div>
	                    </div>
					`;
	    	}).join('');
	    
	    	
		}
    	return `<div class="status-timeline-card" style="background-color: #ebf3f2;e; height: 395px; display: flex; flex-direction: column; border: 1px solid #ddd; border-radius: 10px;">
					    <div class="card-header" style="padding: 15px; border-bottom: 1px solid #eee; background: #f8f9fa;">
					        <h5 class="mb-0">
					            <i class="fas fa-stream me-2"></i>
					            مراحل الشحن والتسليم
					        </h5>
					    </div>
					    <div style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">
					        <div style="padding: 15px; border-bottom: 1px solid #eee; background: white;">
					            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
					                <span>التقدم</span>
					                <span style="font-weight: bold;">${percentage}%</span>
					            </div>
					            <div style="height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
					                <div style="width: ${percentage}%; height: 100%; background: #ffc107;"></div>
					            </div>
					        </div>
					        <div style="flex: 1; overflow-y: auto; padding: 20px; position: relative;">
					            <div style="display: flex;">
					                <div style="width: 40px; display: flex; justify-content: center; position: relative;">
					                    <div style="width: 3px; background: linear-gradient(to bottom, #007bff, #28a745, #6c757d); border-radius: 3px; height: 100%;"></div>
					                </div>
					                <!-- العناصر -->
					                <div style="flex: 1; padding-right: 15px;">
					                    ${items}
					                </div>
					            </div>
					        </div>
					        <!-- الحالة الحالية -->
					        <div style="padding: 15px; border-top: 1px solid #eee; background: #f8f9fa;">
					            <div style="background: #fff3cd; border-right: 4px solid #ffc107; padding: 12px; border-radius: 6px; text-align: right;" class="${statColor.replace(/^alert/, "bg")}">
					                <i class="fas fa-info-circle me-2"></i>
					                <strong>الحالة الحالية:</strong> ${statusName}
					                <div style="color: #666; font-size: 0.85rem; margin-top: 5px;">
					                
					                </div>
					            </div>
					        </div>
					        
					    </div>
					</div>`;
    } catch (e) {
    	return`<div class="status-timeline-card" style="height: 395px; display: flex; flex-direction: column; border: 1px solid #ddd; border-radius: 10px;">
					    
					    <!-- الهيدر -->
					    <div class="card-header" style="padding: 15px; border-bottom: 1px solid #eee; background: #f8f9fa;">
					        <h5 class="mb-0">
					            <i class="fas fa-stream me-2"></i>
					            مراحل الشحن والتسليم
					        </h5>
					    </div>
					    
					    <!-- الجسم -->
					    <div style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">
					        
					        <!-- شريط التقدم -->
					        <div style="padding: 15px; border-bottom: 1px solid #eee; background: white;">
					            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
					                <span>التقدم</span>
					                <span style="font-weight: bold;">${percentage}%</span>
					            </div>
					            <div style="height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
					                <div style="width: ${percentage}%; height: 100%; background: #ffc107;"></div>
					            </div>
					        </div>
					        
					        <!-- منطقة التايم لاين مع سكرول -->
					        <div style="flex: 1; overflow-y: auto; padding: 20px; position: relative;">
					            
					            <!-- الحاوية الرئيسية للخط والعناصر -->
					            <div style="display: flex;">
					                
					                <!-- الخط العمودي (باستخدام border بدلاً من position) -->
					                <div style="width: 40px; display: flex; justify-content: center; position: relative;">
					                    <!-- الخط -->
					                    <div style="width: 3px; background: linear-gradient(to bottom, #007bff, #28a745, #6c757d); 
					                                border-radius: 3px; height: 100%;"></div>
					                </div>
					                
					                <!-- العناصر -->
					                <div style="flex: 1; padding-right: 15px;">
										<div style="display: flex; margin-bottom: 25px; margin-right: -56px; align-items: flex-start;">
					                        <div  style="width: 40px; display: flex; justify-content: center; position: relative;">
					                            <div class="bg-primary" style="width: 20px; height: 20px; border-radius: 50%; background: #007bff; border: 4px solid white; box-shadow: 0 0 0 1px #007bff40, 0 2px 5px rgba(0,0,0,0.2); position: relative; z-index: 2;"></div>
					                        </div>
					                        <div style="flex: 1; text-align: right; padding-right: 15px; margin-top:-13px ;">
					                            <div class="text-primary" style="font-weight: bold; color: #007bff; margin-bottom: 5px; font-size: 1.1rem;">
					                                لايوجد بيانات لعرضها
					                            </div>
					                            <div style="color: #666; font-size: 0.85rem; margin-bottom: 5px;"><i class="fas fa-clock me-1"></i> غير متوفر </div>
					                        </div>
					                    </div>
									
									</div>
					            </div>
					        </div>
					        
					        <!-- الحالة الحالية -->
					        <div style="padding: 15px; border-top: 1px solid #eee; background: #f8f9fa;">
					            <div style="background: #fff3cd; border-right: 4px solid #ffc107; padding: 12px; border-radius: 6px; text-align: right;" class="${statColor.replace(/^alert/, "bg")}">
					                <i class="fas fa-info-circle me-2"></i>
					                <strong>الحالة الحالية:</strong> ${statusName}
					                <div style="color: #666; font-size: 0.85rem; margin-top: 5px;">
					                    
					                </div>
					            </div>
					        </div>
					        
					    </div>
					</div>`;
    }
    
	
  }

// ============ Order Summary Section ============
function createOrderSummarySection(subtotal, shipping, total) {
    return `
        <div class="order-summary-card" style="border: 1px solid #ddd; border-radius: 10px; background-color: #ebf3f2;e;">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calculator me-2"></i>
                    الملخص المالي
                </h5>
            </div>
            <div class="card-body">
                <div class="summary-list">
                    <div class="summary-item">
                        <span class="item-label">مجموع المنتجات</span>
                        <span class="item-value">${formatSafePrice(subtotal)}</span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="item-label">قيمة الشحن</span>
                        <span class="item-value">${formatSafePrice(shipping)}</span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="item-label">الخصم</span>
                        <span class="item-value text-danger">-0.00</span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="item-label">الضريبة</span>
                        <span class="item-value">0.00</span>
                    </div>
                </div>
                
                <div class="summary-divider"></div>
                
                <div class="summary-total">
                    <div class="total-label">الإجمالي النهائي</div>
                    <div class="total-amount">${total}</div>
                </div>
                
                <div class="payment-info mt-4">
                    <div class="payment-method">
                        <i class="fas fa-credit-card me-2"></i>
                        طريقة الدفع: نقداً عند الاستلام
                    </div>
                </div>
            </div>
        </div>
    `;
}

// ============ Recent Actions Section ============
function createRecentActionsSection(actions) {
    if (!actions || Object.keys(actions).length === 0) {
        return `
            <div class="actions-empty-card" style="border: 1px solid #ddd; border-radius: 10px;">
                <div class="empty-content">
                    <i class="fas fa-history fa-2x"></i>
                    <h6>لا توجد إجراءات</h6>
                </div>
            </div>
        `;
    }
   // const reversedKeys = Object.keys(obj);
    const actionKeys = Object.keys(actions);//.reverse();
    const actionCount = actionKeys.length;
    let actionsHtml = '';
    let displayedCount = 0;
    let lastUpdateOrder = '';
    for (let key of actionKeys) {
        const action = actions[key];
        if (!action) continue;
        if(lastUpdateOrder == '') lastUpdateOrder = ' '+action.date+' | '+action.time;
        displayedCount++;
        let li ='';
        const actionType = action.action_type || 'general';
        const icon = getActionIcon(actionType);
        const color = getActionColor(actionType);
        const log = actions[key].log.split('*');
        for (let x = 1; x < log.length; x++) {
          li += '<li class="action-description text-info">' + log[x] + '</li>';
        }
        li = `<ul class="">${li}</ul>`;
        actionsHtml += `
            <div class="action-item">
              
                <div class="action-content" style="overflow-y: auto">
                   
                   <div class="d-flex justify-content-between">
                        <i class="action-icon fas ${icon}"></i>
                        <h6 class="action-title">${log[0] || 'إجراء'}</h6>
                    </div>
                    
                    <!-- <p class="action-description mr-1">${action.action_comment || 'بدون ملاحظات'}</p> -->
                    ${li}
                    <div class="action-meta">
                        <small>
                            <i class="fas fa-user me-1"></i>
                            ${action.user || 'غير معروف'} 
                        </small>
                        
                    </div>
                    <div class="action-meta">
                        <small>
                        <i class="fas fa-calendar me-1"></i>
                        	${action.date || ''}
                        </small>
                        <small>
                        	${' | '+action.time}
                        </small>
                    </div>
                </div>
            </div>
        `;
    }
    
   /* return `
        <div class="recent-actions-card" style="border: 1px solidl #ddd; border-radius: 10px;">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>
                        آخر الإجراءات
                    </h5>
                    <span class="badge">${actionCount}</span>
                </div>
            </div>
            <div class="card-body overflow-auto" style="max-height: 300px;">
                ${actionsHtml}
                
                ${actionCount > displayedCount ? `
                    <div class="view-more-section">
                        <button class="btn btn-sm btn-outline-primary w-100">
                            <i class="fas fa-eye me-1"></i>
                            عرض المزيد (${actionCount - displayedCount})
                        </button>
                    </div>
                ` : ''}
            </div>
        </div>
    `;*/
    return `
    <div class="recent-actions-card card" style="border: 1px solid #ddd; border-radius: 10px;">
        
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    آخر الإجراءات
                </h5>
                <span class="badge bg-primary">${actionCount}</span>
            </div>
        </div>

        <div id="logs-container" class="card-body overflow-auto" style="max-height: 530px; background-color: #ebf3f2;e;">
            ${actionsHtml}
        </div>
		<div class="card-footer bg-light text-center">
			<div style="background: #fff3cd; border-right: 4px solid #ffc107; padding: 12px; border-radius: 6px; text-align: right;" class="bg-dark">
				<i class="fas fa-info-circle me-2"></i>
				<strong>أخر تحديث :</strong> 
				${lastUpdateOrder}
				<div style="color: #666; font-size: 0.85rem; margin-top: 5px;">
					                
				</div>
			</div>
        </div>
    </div>
`;
}

// ============ Control Panel Section ============
function createControlPanelSection(btnapp) {
	
    return `
        <div class="control-panel-card" style="border: 1px solid #ddd; border-radius: 10px;  background-color: #ebf3f2;e;">
            <div class="card-body">
                <div class="row align-items-center">
                    
                    
                    <div class="col-md-8">
                        <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                            ${btnapp || ''}
                        </div>
                    </div>
                    <div class="btn col-md-4 mb-3 mb-md-0">
                        <button id="btnback" class="btn btn-back">
                            <i class="fas fa-arrow-left me-2"></i>
                            رجوع للقائمة
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    
}

// ============ دوال مساعدة للأيقونات ============

function getActionIcon(actionType) {
    const icons = {
        'create' : 'fa-plus',
        'update' : 'fa-edit',
        'delete' : 'fa-trash',
        'ship'   : 'fa-truck',
        'receive': 'fa-check',
        'cancel' : 'fa-times',
        'approve': 'fa-check-circle',
        'general': 'fa-history'
    };
    
    return icons[actionType] || 'fa-circle';
}

function getActionColor(actionType) {
    const colors = {
        'create' : 'bg-success',
        'update' : 'bg-warning',
        'delete' : 'bg-danger',
        'ship'   : 'bg-info',
        'receive': 'bg-primary',
        'cancel' : 'bg-danger',
        'approve': 'bg-success'
    };
    
    return colors[actionType] || 'bg-secondary';
}

// ============ إضافة الأنماط CSS ============


 
function getColor(action) {
	  if (action.includes("استلام")) return "#4caf50";      // أخضر
	  if (action.includes("استرداد") || action.includes("مرتجع")) return "#f44336"; // أحمر
	  if (action.includes("تسليم")) return "#2196f3";        // أزرق
	  return "#9e9e9e"; // رمادي افتراضي
}

function getBootstrapColorClass(action) {
	  if (action.includes("قيد التأكيد")) return "bg-";
	  if (action.includes("بدء الشحن")) return "bg-primary";
	  if (action.includes("تجميد")) return "bg-dark";
	  if (action.includes("استرداد")) return "bg-info";
	  if (action.includes("تم التسليم")) return "bg-success";
	  if (action.includes("تسليم جزئى")) return "bg-warning";
	  if (action.includes("مرفوض")) return "bg-danger";
	  if (action.includes("تسويه مخزنيه")) return "bg-info";
	  if (action.includes("تسويه ماليه")) return "bg-success";
	  return "bg-dark";
}

function shippingPercent(action){
	if (action.includes("تسويه ماليه")) return 100;
	if (action.includes("تسويه مخزنيه")) return 75;
	if (action.includes("تم التسليم")) return 50;
	if (action.includes("تسليم جزئى")) return 50;
	if (action.includes("مرفوض")) return 50;
	if (action.includes("تجميد")) return 25;
	if (action.includes("بدء الشحن")) return 25;
	if (action.includes("استرداد")) return 0;
	  
	  
	  return 0;
}
 
function getProgressBarClass(percentage) {
    if (percentage >= 70) return 'bg-success';
    if (percentage >= 40) return 'bg-warning';
    return 'bg-danger';
}

function getStatusBadgeClass(statusId) {
    // كودك الحالي
    if (statusId >= 0) return 'alert-dark';
    if (statusId >= 1) return 'alert-info';
    if (statusId >= 2) return 'alert-warning';
    if (statusId >= 3) return 'alert-success';
    if (statusId >= 4) return 'alert-success';
    if (statusId >= 5) return 'alert-danger';
    if (statusId >= 6) return 'alert-warning';
    if (statusId >= 7) return 'alert-danger';

    return 'alert-danger';
}

// ============ تهيئة عند التحميل ============

$(document).ready(function() {
    // إضافة حدث زر الرجوع العام
    $(document).on('click', '#btnback', function() {
        $('.DOV').hide();
        $('.DOL').show();
        //$('.btnh, #fiinfo').show();
        $('#btnhead').remove();
    });
});
 
 
