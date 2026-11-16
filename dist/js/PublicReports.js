$(function () {
	const fmt = n => (parseFloat(n) || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
	const pad = n => String(n).padStart(2, '0');
	const d = new Date();
	$('#pr-from').val(d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-01');
	$('#pr-to').val(d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()));
	function rows(list) {
		if (!list.length) { return '<tr><td class="text-muted text-center">لا توجد حركة في الفترة</td></tr>'; }
		return list.map(r => '<tr><td>' + r.account_code + '</td><td>' + r.account_name + '</td><td class="text-left">' + fmt(r.amount) + '</td></tr>').join('');
	}
	function load() {
		$.ajax({
			url: '../../inc/controller/reports.control.php', type: 'POST', dataType: 'json',
			data: {CONTROL: 'PublicReport', date_from: $('#pr-from').val(), date_to: $('#pr-to').val()},
			success: function (res) {
				if (!Array.isArray(res) || res[0] !== 's') {
					Swal.fire({icon: 'error', title: 'تعذر عرض التقرير', text: (res && res[2]) || (res && res[1]) || ''});
					return;
				}
				const r = res[2], b = r.balances;
				$('#pr-rev').text(fmt(r.total_revenue));
				$('#pr-exp').text(fmt(r.total_expense));
				$('#pr-net').text(fmt(r.net_profit)).toggleClass('text-danger', r.net_profit < 0).toggleClass('text-success', r.net_profit >= 0);
				$('#pr-cash').text(fmt(b.cash));
				$('#pr-cust').text(fmt(b.customers_owe));
				$('#pr-inv').text(fmt(b.inventory));
				$('#pr-sup').text(fmt(b.suppliers_owed));
				$('#pr-comm').text(fmt(b.courier_payable + b.marketer_payable));
				$('#pr-rev-rows').html(rows(r.revenue));
				$('#pr-exp-rows').html(rows(r.expense));
			},
			error: function () { Swal.fire({icon: 'error', title: 'تعذر الاتصال بالخادم'}); }
		});
	}
	$('#pr-run').on('click', load);
	load();
});
