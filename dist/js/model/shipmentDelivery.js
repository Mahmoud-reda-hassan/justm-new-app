
function loadOrderDetails(orderId) {
    return $.ajax({
        url: urlship,
        type: 'POST',
        dataType: 'json',
        data: {
            CONTROL: "shipDelivery",
            order_id: orderId,
        }
    });
};

function escapeShipmentHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[char]);
}

function renderShipmentProducts(response) {
    const products = Array.isArray(response.products) ? response.products : [];
    if (!products.length) {
        return '<div class="alert alert-warning text-right">لا توجد منتجات مسجلة في عهدة هذا المندوب لهذا الطلب.</div>';
    }

    const rows = products.map(product => {
        const code = escapeShipmentHtml(product.code);
        const name = escapeShipmentHtml(product.name);
        const options = Array.isArray(product.option)
            ? product.option.map(escapeShipmentHtml).filter(Boolean).join('، ')
            : '';
        const requiredQty = Number(product.qty || 0);
        const deliveredQty = Number(product.delivered_qty || 0);

        return `<tr data-code="${code}">
            <td class="proname">${name}${options ? `<small class="d-block text-muted">${options}</small>` : ''}</td>
            <td class="default-Qty">${requiredQty}</td>
            <td class="approved-Qty" contenteditable="true" inputmode="decimal" role="textbox" aria-label="الكمية المسلمة">${deliveredQty}</td>
        </tr>`;
    }).join('');

    const shippingValue = Number(response.SHIPPING_VAL || 0);
    return `<div class="text-right">
        <div class="mb-2">رسوم الشحن الحالية: <strong>${shippingValue.toFixed(2)}</strong></div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-swel text-center">
                <thead><tr><th>المنتج</th><th>الكمية المطلوبة</th><th>الكمية المسلمة</th></tr></thead>
                <tbody>${rows}</tbody>
            </table>
        </div>
        <small class="text-muted">يمكن تعديل الكمية المسلمة بالضغط على الخانة الأخيرة. أدخل كمية من صفر وحتى الكمية المطلوبة.</small>
    </div>`;
}

function parseShipmentTable() {
    let objApprov = {}, objReject = {}, qtyApp = 0, qtyDef = 0, err = 0;

    document.querySelectorAll(".table-swel tbody tr").forEach(tr => {
        let code = tr.dataset.code;
        let dQty = parseFloat(tr.querySelector(".default-Qty").innerText);
        let aQty = parseFloat(tr.querySelector(".approved-Qty").innerText);
        let proName = tr.querySelector(".proname").innerText;

        qtyDef += dQty;
        qtyApp += aQty;

        if (aQty <= dQty && aQty >= 0 && code) {
            objApprov[code] = { defqty: dQty, appQty: aQty };
            if (dQty > aQty) {
                objReject[code] = { defqty: dQty, appQty: aQty, proData: proName };
            }
        } else {
            err++;
        }
    });

    return { objApprov, objReject, qtyApp, qtyDef, err };
};

function createShipmentObject(orderId, parsed) {
    const { objApprov, objReject, qtyApp, qtyDef } = parsed;
    let reapp = "رفض امر البيع بالكامل";
    let qty_rejected = 0;

    if (qtyApp > 0 && qtyApp === qtyDef) {
        reapp = "تسليم امر البيع بالكامل";
    } else if (qtyApp > 0) {
        reapp = "تسليم امر البيع بشكل جزئي";
        qty_rejected++;
    } else {
        qty_rejected++;
    }

    return {
        odata: orderId,
        oapp: objApprov,
        orej: objReject,
        reapp,
        utype: ptype,
        qty_rejected
    };
};

function shipmentRequestIdentity(orderId, payload) {
    const storageKey = 'shipment-confirm-request:' + orderId + ':' +
        JSON.stringify({ oapp: payload.oapp, orej: payload.orej, ptype: payload.utype });
    let requestKey = '';
    try {
        requestKey = sessionStorage.getItem(storageKey) || '';
        if (!requestKey) {
            requestKey = 'SHIP_CONFIRM:' + Date.now() + ':' + Math.random().toString(36).slice(2, 14);
            sessionStorage.setItem(storageKey, requestKey);
        }
    } catch (error) {
        requestKey = 'SHIP_CONFIRM:' + Date.now() + ':' + Math.random().toString(36).slice(2, 14);
    }
    return { requestKey, storageKey };
}

export function ShipmentDelivery(orderId) {
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
                if (response && response.type === "success") {
                    $(".swal2-title").text("تأكيد تسليم أمر البيع");
                    $(".swal2-html-container").html(renderShipmentProducts(response)).show();
                    $(".swal2-cancel").show();
                    Swal.hideLoading();
                } else if (response && (response.type === "warning" || response.type === "error")) {
                    Swal.fire(
                        response.title || "تنبيه",
                        response.message || "تعذر تحميل بيانات الطلب.",
                        response.type === "warning" ? "warning" : "error"
                    );
                } else {
                    Swal.fire("خطأ", "استجابة غير مفهومة من الخادم أثناء تحميل الطلب.", "error");
                }
            }).fail(() => {
                Swal.fire('نأسف', 'حدث خطأ غير معروف أثناء التنفيذ', 'error');
            });
        },
        preConfirm: () => {
            const parsed = parseShipmentTable();
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
                html: 'سوف يتم ' + result.value.reapp + '؟',
                showCancelButton: true,
                confirmButtonText: 'حفظ',
                cancelButtonText: 'إلغاء',
                preConfirm: () => {
                    const requestIdentity = shipmentRequestIdentity(result.value.odata, result.value);
                    return {
                        CONTROL: "shipDelivered",
                        request_key: requestIdentity.requestKey,
                        odata: result.value.odata,
                        oshipp: {
                            oapp: result.value.oapp,
                            orej: result.value.orej,
                            reapp: result.value.reapp,
                            ptype: result.value.utype
                        },
                        ShipApproved: false,
                        urlControl: Go
                    };
                }
            }).then(confirmRes => {
                if (confirmRes.isConfirmed) {
                    let finalData = confirmRes.value;
                    if (ptype === 1 && Go === "OrdersCustody" && result.value.qty_rejected > 0) {
                        Swal.fire({
                            title: 'تأكيد',
                            icon: 'question',
                            html: 'هل تريد استلام المرتجعات؟',
                            showCancelButton: true,
                            confirmButtonText: 'نعم',
                            cancelButtonText: 'لا',
                            preConfirm: () => { finalData.ShipApproved = true; }
                        }).then(() => {
                            let res = sendRequest(urlship, finalData);
                            responseMsg(res);
                            if (res && res[0] === 'success') {
                                try { sessionStorage.removeItem(shipmentRequestIdentity(result.value.odata, result.value).storageKey); } catch (error) {}
                            }
                            table.ajax.reload(null, false).on('draw', custodyTotal);
                        });
                    } else {
                        let res = sendRequest(urlship, finalData);
                        responseMsg(res);
                        if (res && res[0] === 'success') {
                            try { sessionStorage.removeItem(shipmentRequestIdentity(result.value.odata, result.value).storageKey); } catch (error) {}
                        }
                        table.ajax.reload(null, false).on('draw', custodyTotal);
                    }
                }
            });
        }
    });
};