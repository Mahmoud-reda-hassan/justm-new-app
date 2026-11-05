
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
    const shippingEditor = response.shipping_edit_allowed === true && Number(response.order_type_id) !== 5
        ? `<div class="form-group text-right mt-3">
            <label for="shipmentShippingAmount">رسوم الشحن للطلب</label>
            <input id="shipmentShippingAmount" class="form-control" type="number" min="0" step="0.01" value="${shippingValue.toFixed(2)}">
            <small class="form-text text-muted">التعديل متاح حسب إعدادات النظام، وتُحفظ القيمة الجديدة مع محاولة التوصيل.</small>
        </div>`
        : `<div class="mb-2">رسوم الشحن: <strong>${shippingValue.toFixed(2)}</strong></div>`;
    return `<div class="text-right">
        ${shippingEditor}
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
        const defaultText = tr.querySelector(".default-Qty").innerText.trim();
        const approvedText = tr.querySelector(".approved-Qty").innerText.trim();
        let dQty = Number(defaultText);
        let aQty = Number(approvedText);
        let proName = tr.querySelector(".proname").innerText;

        if (!defaultText || !approvedText || !Number.isFinite(dQty) || !Number.isFinite(aQty)) {
            err++;
            return;
        }

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

    let shippingAmount = null;
    const shippingInput = document.getElementById("shipmentShippingAmount");
    if (shippingInput) {
        const rawShippingAmount = shippingInput.value.trim();
        const parsedShippingAmount = Number(rawShippingAmount);
        if (!rawShippingAmount || !Number.isFinite(parsedShippingAmount) || parsedShippingAmount < 0
            || Math.abs(parsedShippingAmount * 100 - Math.round(parsedShippingAmount * 100)) > 0.000001) {
            err++;
        } else {
            shippingAmount = parsedShippingAmount.toFixed(2);
        }
    }

    return { objApprov, objReject, qtyApp, qtyDef, err, shippingAmount };
};

function createShipmentObject(orderId, parsed) {
    const { objApprov, objReject, qtyApp, qtyDef, shippingAmount } = parsed;
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
        qty_rejected,
        shipping_amount: shippingAmount
    };
};

function shipmentRequestIdentity(orderId, payload) {
    const storageKey = 'shipment-confirm-request:' + orderId + ':' +
        JSON.stringify({
            oapp: payload.oapp,
            orej: payload.orej,
            ptype: payload.utype
        });
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
                preConfirm: () => ({
                    CONTROL: "shipDelivered",
                    odata: result.value.odata,
                    oshipp: {
                        oapp: result.value.oapp,
                        orej: result.value.orej,
                        reapp: result.value.reapp,
                        ptype: result.value.utype,
                        ...(result.value.shipping_amount !== null
                            ? { shipping_amount: result.value.shipping_amount }
                            : {})
                    },
                    ShipApproved: false,
                    urlControl: Go
                })
            }).then(confirmRes => {
                if (!confirmRes.isConfirmed) return;

                const finalData = confirmRes.value;

                const submitConfirmation = (customerPaidShipping = null) => {
                    if (customerPaidShipping !== null) {
                        finalData.oshipp.customer_paid_shipping = customerPaidShipping ? 1 : 0;
                    }

                    const identityPayload = {
                        ...result.value,
                        customer_paid_shipping: customerPaidShipping === null ? null : (customerPaidShipping ? 1 : 0)
                    };
                    const requestIdentity = shipmentRequestIdentity(result.value.odata, identityPayload);
                    finalData.request_key = requestIdentity.requestKey;

                    const response = sendRequest(urlship, finalData);
                    responseMsg(response);
                    if (response && response[0] === 'success') {
                        try { sessionStorage.removeItem(requestIdentity.storageKey); } catch (error) {}
                    }
                    table.ajax.reload(null, false).on('draw', custodyTotal);
                };

                const askShippingPayment = () => {
                    // سؤال الدفع يخص الرفض الكامل فقط؛ الرفض الجزئي لا ينشئ تحصيل رسوم شحن مستقلًا.
                    if (Number(result.value.qtyApp) === 0) {
                        Swal.fire({
                            title: 'رسوم الشحن',
                            icon: 'question',
                            text: 'هل دفع العميل رسوم الشحن؟',
                            showCancelButton: true,
                            confirmButtonText: 'نعم، دفع',
                            cancelButtonText: 'لا، لم يدفع',
                            allowOutsideClick: false,
                            allowEscapeKey: false
                        }).then(paymentResult => {
                            submitConfirmation(paymentResult.isConfirmed);
                        });
                    } else {
                        submitConfirmation(null);
                    }
                };

                const askInventoryReturn = () => {
                    if (ptype === 1 && Go === "OrdersCustody" && result.value.qty_rejected > 0) {
                        Swal.fire({
                            title: 'تأكيد',
                            icon: 'question',
                            html: 'هل تريد استلام المرتجعات؟',
                            showCancelButton: true,
                            confirmButtonText: 'نعم',
                            cancelButtonText: 'لا',
                            allowOutsideClick: false
                        }).then(returnResult => {
                            if (returnResult.isConfirmed) finalData.ShipApproved = true;
                            askShippingPayment();
                        });
                    } else {
                        askShippingPayment();
                    }
                };

                askInventoryReturn();
            });
        }
    });
};