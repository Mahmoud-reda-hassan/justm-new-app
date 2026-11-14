$(function () {
    'use strict';
    var $dashboard = $('#adminDashboard');
    if (!$dashboard.length) return;
    var endpoint = $dashboard.data('endpoint') || '/inc/controller/dashbord.control.php';
    var charts = {};
    var requestInProgress = false;
    var firstLoad = true;
    var refreshTimer = null;
    var dateFrom = moment().subtract(6, 'days').format('YYYY-MM-DD');
    var dateTo = moment().format('YYYY-MM-DD');

    function number(value) { return Number(value || 0).toLocaleString('ar-EG'); }
    function escapeHtml(value) {
        return String(value === null || typeof value === 'undefined' ? '-' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    function showError(message) { $('#dashboardError').removeClass('d-none').text(message || 'تعذر تحميل بيانات لوحة التحكم.'); }
    function setMetric(name, value) {
        $('[data-metric="' + name + '"]').text(value === null || typeof value === 'undefined' ? '—' : number(value));
    }
    function renderMetrics(metrics) { $.each(metrics || {}, function (key, value) { setMetric(key, value); }); }

    function updateChart(id, type, labels, values, label) {
        var canvas = document.getElementById(id);
        if (!canvas || typeof Chart === 'undefined') return;
        if (charts[id]) {
            charts[id].data.labels = labels;
            charts[id].data.datasets[0].data = values;
            charts[id].update();
            return;
        }
        charts[id] = new Chart(canvas.getContext('2d'), {
            type: type,
            data: { labels: labels, datasets: [{
                label: label || '', data: values,
                backgroundColor: type === 'doughnut' ? ['#3c8dbc','#00a65a','#f39c12','#dd4b39','#605ca8','#00c0ef','#d2d6de','#39cccc'] : 'rgba(60,141,188,0.18)',
                borderColor: type === 'doughnut' ? '#fff' : '#3c8dbc',
                borderWidth: type === 'doughnut' ? 1 : 2, pointRadius: 3, fill: type !== 'doughnut', lineTension: 0.25
            }]},
            options: {
                responsive: true, maintainAspectRatio: false,
                legend: { display: type === 'doughnut', position: 'bottom' },
                scales: type === 'doughnut' ? {} : {
                    yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }],
                    xAxes: [{ gridLines: { display: false } }]
                }
            }
        });
    }

    function renderTable(id, rows, columns, emptyMessage) {
        var $body = $('#' + id + ' tbody');
        if (!$body.length) return;
        rows = Array.isArray(rows) ? rows : [];
        if (!rows.length) {
            $body.html('<tr><td colspan="' + columns.length + '" class="text-center text-muted py-3">' + escapeHtml(emptyMessage || 'لا توجد بيانات') + '</td></tr>');
            return;
        }
        var html = rows.map(function (row) {
            return '<tr>' + columns.map(function (column) {
                var value = row[column.key];
                if (column.numeric) value = number(value);
                return '<td>' + escapeHtml(value) + '</td>';
            }).join('') + '</tr>';
        }).join('');
        $body.html(html);
    }

    function renderExistsMetrics(items) {
        var $container = $('#dashboardExistsMetrics');
        if (!$container.length) return;
        items = Array.isArray(items) ? items : [];
        if (!items.length) {
            $container.empty();
            return;
        }
        var statusCards = {
            delivery_custody_shipping: { color: 'bg-info', icon: 'fa-truck' },
            delivery_rejected: { color: 'bg-danger', icon: 'fa-times-circle' },
            delivery_partial: { color: 'bg-warning', icon: 'fa-box-open' },
            delivery_delivered: { color: 'bg-success', icon: 'fa-check-circle' },
            delivery_partial_return: { color: 'bg-secondary', icon: 'fa-undo' },
            delivery_full_return: { color: 'bg-indigo', icon: 'fa-undo-alt' }
        };
        var colors = ['bg-primary','bg-info','bg-success','bg-warning','bg-danger','bg-secondary','bg-teal','bg-indigo'];
        var html = items.map(function (item, index) {
            var style = statusCards[item.metric_key] || {};
            var color = style.color || colors[index % colors.length];
            var icon = style.icon || 'fa-clipboard-list';
            return '<div class="col-lg-3 col-6"><div class="small-box ' + color + '"><div class="inner"><h3>' +
                number(item.total) + '</h3><p>' + escapeHtml(item.exists_name || 'حالة غير محددة') +
                '</p></div><div class="icon"><i class="fas ' + icon + '"></i></div></div></div>';
        }).join('');
        $container.html(html);
    }

    function renderDashboard(data) {
        renderMetrics(data.metrics || {});
        renderExistsMetrics(data.exists_counts || []);
        var trend = data.orders_trend || [];
        updateChart('dashboardOrdersChart', 'line',
            trend.map(function (item) { return item.label; }),
            trend.map(function (item) { return Number(item.total || 0); }), 'أوامر البيع');
        var status = data.status_distribution || [];
        updateChart('dashboardStatusChart', 'doughnut',
            status.map(function (item) { return item.label || 'غير محدد'; }),
            status.map(function (item) { return Number(item.total || 0); }), 'حالات أوامر البيع');

        renderTable('dashboardOrdersTable', data.latest_orders, [
            {key:'SUB_ORDER_ID'}, {key:'created_at'}, {key:'exists_name'}, {key:'status_name'}, {key:'payment_status'}, {key:'delivery_name'}
        ]);
        renderTable('dashboardLowStockTable', data.low_stock, [
            {key:'store_name'}, {key:'product_name'}, {key:'product_code'}, {key:'minimum_qty',numeric:true}, {key:'current_qty',numeric:true}
        ]);
        renderTable('dashboardDeliveryTable', data.delivery_performance, [
            {key:'delivery_name'}, {key:'assigned_count',numeric:true}, {key:'delivered_count',numeric:true}, {key:'rejected_count',numeric:true}, {key:'returned_count',numeric:true}
        ]);
        renderTable('dashboardTopProductsTable', data.top_products, [
            {key:'product_name'}, {key:'sold_qty',numeric:true}, {key:'sales_value',numeric:true}
        ]);
    }

    function loadDashboard() {
        if (requestInProgress) return;
        requestInProgress = true;
        if (firstLoad) $('#dashboardLoading').removeClass('d-none');
        $('#dashboardError').addClass('d-none').text('');
        $.ajax({
            url: endpoint, type: 'POST', dataType: 'json',
            data: { CONTROL:'getDashboardData', start_date:dateFrom, end_date:dateTo }, timeout:30000
        }).done(function (response) {
            if (!response || response.success !== true) {
                showError(response && response.message ? response.message : 'تعذر تحميل بيانات لوحة التحكم.');
                return;
            }
            renderDashboard(response.data || {});
            $('#dashboardUpdatedAt').text('آخر تحديث ناجح: ' + new Date().toLocaleTimeString('ar-EG') + ' · تحديث تلقائي كل 10 ثوانٍ');
        }).fail(function (xhr) {
            var message = 'حدث خطأ أثناء الاتصال بالخادم.';
            if (xhr.responseJSON && xhr.responseJSON.message) message = xhr.responseJSON.message;
            showError(message);
        }).always(function () {
            requestInProgress = false;
            firstLoad = false;
            $('#dashboardLoading').addClass('d-none');
        });
    }

    if ($('#dashboardDateRange').length && $.fn.daterangepicker && typeof moment !== 'undefined') {
        $('#dashboardDateRange').daterangepicker({
            startDate:moment(dateFrom), endDate:moment(dateTo), maxDate:moment(), maxSpan:{days:90},
            opens:'left', autoUpdateInput:true,
            locale:{
                format:'YYYY-MM-DD', separator:' - ', applyLabel:'تطبيق', cancelLabel:'إلغاء',
                fromLabel:'من', toLabel:'إلى', customRangeLabel:'فترة مخصصة',
                daysOfWeek:['ح','ن','ث','ر','خ','ج','س'],
                monthNames:['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'],
                firstDay:6
            },
            ranges:{
                'اليوم':[moment(),moment()],
                'آخر 7 أيام':[moment().subtract(6,'days'),moment()],
                'آخر 30 يومًا':[moment().subtract(29,'days'),moment()],
                'هذا الشهر':[moment().startOf('month'),moment()]
            }
        }, function (start,end) {
            dateFrom = start.format('YYYY-MM-DD');
            dateTo = end.format('YYYY-MM-DD');
            loadDashboard();
        });
    } else {
        $('#dashboardDateRange').val(dateFrom + ' - ' + dateTo);
    }
    $('#dashboardRefresh').on('click', loadDashboard);
    loadDashboard();
    refreshTimer = window.setInterval(loadDashboard, 10000);
    $(window).on('beforeunload', function () { if (refreshTimer) window.clearInterval(refreshTimer); });
});
