$(function () {
    'use strict';

    var $dashboard = $('#adminDashboard');
    if (!$dashboard.length) return;

    var endpoint = $dashboard.data('endpoint') || '/inc/controller/dashbord.control.php';
    var charts = {};
    var tables = {};
    var requestInProgress = false;
    var firstLoad = true;
    var refreshTimer = null;
    var dateFrom = moment().subtract(6, 'days').format('YYYY-MM-DD');
    var dateTo = moment().format('YYYY-MM-DD');

    function number(value) {
        return Number(value || 0).toLocaleString('ar-EG');
    }

    function showError(message) {
        $('#dashboardError').removeClass('d-none').text(message || 'تعذر تحميل بيانات لوحة التحكم.');
    }

    function setMetric(name, value) {
        $('[data-metric="' + name + '"]').text(value === null || typeof value === 'undefined' ? '—' : number(value));
    }

    function renderMetrics(metrics) {
        $.each(metrics || {}, function (key, value) {
            setMetric(key, value);
        });
    }

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
            data: {
                labels: labels,
                datasets: [{
                    label: label || '',
                    data: values,
                    backgroundColor: type === 'doughnut'
                        ? ['#3c8dbc', '#00a65a', '#f39c12', '#dd4b39', '#605ca8', '#00c0ef', '#d2d6de', '#39cccc']
                        : 'rgba(60,141,188,0.18)',
                    borderColor: type === 'doughnut' ? '#ffffff' : '#3c8dbc',
                    borderWidth: type === 'doughnut' ? 1 : 2,
                    pointRadius: 3,
                    fill: type !== 'doughnut',
                    lineTension: 0.25
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: type === 'doughnut', position: 'bottom' },
                scales: type === 'doughnut' ? {} : {
                    yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }],
                    xAxes: [{ gridLines: { display: false } }]
                }
            }
        });
    }

    function renderTable(id, rows, columns, emptyMessage) {
        var selector = '#' + id;
        if (!$(selector).length || !$.fn.DataTable) return;

        if (!tables[id]) {
            tables[id] = $(selector).DataTable({
                data: rows || [],
                columns: columns,
                pageLength: 10,
                lengthChange: false,
                searching: true,
                ordering: true,
                autoWidth: false,
                language: {
                    emptyTable: emptyMessage || 'لا توجد بيانات',
                    zeroRecords: 'لا توجد نتائج مطابقة',
                    search: 'بحث:',
                    info: 'عرض _START_ إلى _END_ من _TOTAL_',
                    infoEmpty: 'لا توجد بيانات',
                    paginate: { first: 'الأول', last: 'الأخير', next: 'التالي', previous: 'السابق' }
                }
            });
        } else {
            tables[id].clear();
            tables[id].rows.add(rows || []);
            tables[id].draw(false);
        }
    }

    function renderDashboard(data) {
        renderMetrics(data.metrics || {});

        var trend = data.orders_trend || [];
        updateChart(
            'dashboardOrdersChart',
            'line',
            trend.map(function (item) { return item.label; }),
            trend.map(function (item) { return Number(item.total || 0); }),
            'أوامر البيع'
        );

        var status = data.status_distribution || [];
        updateChart(
            'dashboardStatusChart',
            'doughnut',
            status.map(function (item) { return item.label || 'غير محدد'; }),
            status.map(function (item) { return Number(item.total || 0); }),
            'حالات أوامر البيع'
        );

        renderTable('dashboardOrdersTable', data.latest_orders || [], [
            { data: 'SUB_ORDER_ID', defaultContent: '-' },
            { data: 'created_at', defaultContent: '-' },
            { data: 'exists_name', defaultContent: '-' },
            { data: 'status_name', defaultContent: '-' },
            { data: 'payment_status', defaultContent: '-' },
            { data: 'delivery_name', defaultContent: '-' }
        ]);

        renderTable('dashboardLowStockTable', data.low_stock || [], [
            { data: 'store_name', defaultContent: '-' },
            { data: 'product_name', defaultContent: '-' },
            { data: 'product_code', defaultContent: '-' },
            { data: 'minimum_qty', defaultContent: '0' },
            { data: 'current_qty', defaultContent: '0' }
        ]);

        renderTable('dashboardDeliveryTable', data.delivery_performance || [], [
            { data: 'delivery_name', defaultContent: '-' },
            { data: 'assigned_count', defaultContent: '0' },
            { data: 'delivered_count', defaultContent: '0' },
            { data: 'rejected_count', defaultContent: '0' },
            { data: 'returned_count', defaultContent: '0' }
        ]);
    }

    function loadDashboard() {
        if (requestInProgress) return;
        requestInProgress = true;

        if (firstLoad) {
            $('#dashboardLoading').removeClass('d-none');
        }
        $('#dashboardError').addClass('d-none').text('');

        $.ajax({
            url: endpoint,
            type: 'POST',
            dataType: 'json',
            data: {
                CONTROL: 'getDashboardData',
                start_date: dateFrom,
                end_date: dateTo
            },
            timeout: 30000
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
            startDate: moment(dateFrom),
            endDate: moment(dateTo),
            maxDate: moment(),
            maxSpan: { days: 90 },
            opens: 'left',
            autoUpdateInput: true,
            locale: {
                format: 'YYYY-MM-DD',
                separator: ' - ',
                applyLabel: 'تطبيق',
                cancelLabel: 'إلغاء',
                fromLabel: 'من',
                toLabel: 'إلى',
                customRangeLabel: 'فترة مخصصة',
                daysOfWeek: ['ح', 'ن', 'ث', 'ر', 'خ', 'ج', 'س'],
                monthNames: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'],
                firstDay: 6
            },
            ranges: {
                'اليوم': [moment(), moment()],
                'آخر 7 أيام': [moment().subtract(6, 'days'), moment()],
                'آخر 30 يومًا': [moment().subtract(29, 'days'), moment()],
                'هذا الشهر': [moment().startOf('month'), moment()]
            }
        }, function (start, end) {
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

    $(window).on('beforeunload', function () {
        if (refreshTimer) window.clearInterval(refreshTimer);
    });
});
