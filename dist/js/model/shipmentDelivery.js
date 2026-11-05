
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
                if (response[0] === "success") {
                    $(".swal2-title").html("تأكيد تسليم امر البيع");
                    $(".swal2-html-container").html(response[1]).show();
                    $(".swal2-cancel").show();
                    Swal.hideLoading();
                } else if (response[0] === "warning") {
                    responseMsg(response);
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