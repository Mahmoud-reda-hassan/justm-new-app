$(function () {
    'use strict';

    var $dashboard = $('#adminDashboard');
    if (!$dashboard.length) return;

    var endpoint = $dashboard.data('endpoint') || '/inc/controller/dashbord.control.php';
    var ordersChart = null;

    function showError(message) {
        $('#dashboardError').removeClass('d-none').text(message || 'تعذر تحميل بيانات لوحة التحكم.');
    }

    function setMetric(name, value) {
        $('[data-metric="' + name + '"]').text(value === null || typeof value === 'undefined' ? '—' : Number(value).toLocaleString('ar-EG'));
    }

    function renderMetrics(metrics) {
        $.each(metrics || {}, function (key, value) {
            if (key !== 'orders_trend') setMetric(key, value);
        });
    }

    function renderOrdersChart(points) {
        var canvas = document.getElementById('dashboardOrdersChart');
        if (!canvas || typeof Chart === 'undefined' || !Array.isArray(points)) return;

        var labels = points.map(function (point) { return point.label; });
        var values = points.map(function (point) { return Number(point.total || 0); });

        if (ordersChart) ordersChart.destroy();
        ordersChart = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'عدد أوامر البيع',
                    data: values,
                    borderColor: '#3c8dbc',
                    backgroundColor: 'rgba(60,141,188,0.15)',
                    borderWidth: 2,
                    pointRadius: 3,
                    fill: true,
                    tension: 0.25
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                scales: {
                    yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }],
                    xAxes: [{ gridLines: { display: false } }]
                }
            }
        });
    }

    function loadDashboard() {
        $('#dashboardError').addClass('d-none').text('');
        $('#dashboardLoading').removeClass('d-none');

        $.ajax({
            url: endpoint,
            type: 'POST',
            dataType: 'json',
            data: { CONTROL: 'getDashboardData' },
            timeout: 30000
        }).done(function (response) {
            if (!response || response.success !== true) {
                showError(response && response.message ? response.message : 'تعذر تحميل بيانات لوحة التحكم.');
                return;
            }

            renderMetrics(response.data && response.data.metrics);
            renderOrdersChart(response.data && response.data.orders_trend);
            $('#dashboardUpdatedAt').text('آخر تحديث: ' + new Date().toLocaleTimeString('ar-EG'));
        }).fail(function (xhr) {
            var message = 'حدث خطأ أثناء الاتصال بالخادم.';
            if (xhr.responseJSON && xhr.responseJSON.message) message = xhr.responseJSON.message;
            showError(message);
        }).always(function () {
            $('#dashboardLoading').addClass('d-none');
        });
    }

    $('#dashboardRefresh').on('click', loadDashboard);
    loadDashboard();
});
