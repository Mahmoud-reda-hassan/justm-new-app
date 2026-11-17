(function () {
	const url = `../../inc/controller/shippingCompanies.control.php`;
	const canAdmin = !!window.SC_CAN_ADMIN;
	const esc = (v) => $('<div>').text(v == null ? '' : v).html();
	const policyName = {SINGLE: 'إذن واحد', PARTIAL: 'دفعات'};
	const methodName = {NET_OF_FEE: 'خصم الرسوم وتوريد الصافي', GROSS_THEN_FEE: 'توريد كامل القيمة والرسوم منفصلة'};
	const feeName = (c) => c.fee_mode === 'FIXED' ? 'ثابت ' + Number(c.fixed_fee).toFixed(2)
		: (c.fee_mode === 'ZONE_RATE' ? 'حسب المنطقة (' + c.rates_count + ' منطقة)' : 'نفس شحن الأمر');

	const call = (ctl, data) => new Promise((resolve, reject) => {
		$.post(url, Object.assign({CONTROL: ctl}, data || {}), function (r) {
			if (Array.isArray(r) && r[0] === 'success') { resolve(r[3]); } else { reject(new Error((r && r[2]) || 'خطأ')); }
		}, 'json').fail(x => reject(new Error('تعذر الاتصال بالخادم (' + (x.status || 'بلا رد') + ')')));
	});
	const fail = (e) => Swal.fire({icon: 'error', title: 'خطأ', text: e.message});

	let rows = [];
	let currentRatesCompany = 0;

	function render() {
		const $tb = $('#scTable tbody').empty();
		if (!rows.length) {
			$tb.append('<tr><td colspan="7" class="text-center text-muted">لا توجد شركات شحن بعد</td></tr>');
			return;
		}
		rows.forEach(c => {
			const active = Number(c.is_active) === 1;
			let btns = '<button class="btn btn-sm btn-outline-secondary sc-rates" data-id="' + c.id + '"><i class="fa fa-map-marker-alt"></i> الأسعار</button>';
			if (canAdmin) {
				btns = '<button class="btn btn-sm btn-outline-primary sc-edit" data-id="' + c.id + '"><i class="fa fa-edit"></i> تعديل</button> ' + btns +
					' <button class="btn btn-sm ' + (active ? 'btn-outline-danger' : 'btn-outline-success') + ' sc-toggle" data-id="' + c.id + '" data-active="' + (active ? 0 : 1) + '">' + (active ? 'إيقاف' : 'تفعيل') + '</button>';
			}
			$tb.append('<tr><td>' + esc(c.name) + (c.note ? '<br><small class="text-muted">' + esc(c.note) + '</small>' : '') + '</td>' +
				'<td>' + esc(policyName[c.receiving_policy] || c.receiving_policy) + '</td>' +
				'<td>' + esc(methodName[c.settlement_method] || c.settlement_method) + '</td>' +
				'<td>' + esc(feeName(c)) + '</td>' +
				'<td>' + (Number(c.has_import) ? '<span class="badge badge-success">مضبوط</span>' : '<span class="badge badge-warning">غير مضبوط</span>') + '</td>' +
				'<td>' + (active ? '<span class="badge badge-success">نشطة</span>' : '<span class="badge badge-secondary">موقوفة</span>') + '</td>' +
				'<td class="text-nowrap">' + btns + '</td></tr>');
		});
	}
	function load() { return call('scList').then(r => { rows = r; render(); }).catch(fail); }

	function openForm(c) {
		c = c || {id: 0, name: '', receiving_policy: 'SINGLE', settlement_method: 'NET_OF_FEE', fee_mode: 'ORDER_SHIPPING', fixed_fee: 0, note: ''};
		$('#scModalTitle').text(c.id ? 'تعديل شركة شحن' : 'شركة شحن جديدة');
		$('#sc-id').val(c.id); $('#sc-name').val(c.name); $('#sc-policy').val(c.receiving_policy);
		$('#sc-method').val(c.settlement_method); $('#sc-feemode').val(c.fee_mode);
		$('#sc-fixed').val(Number(c.fixed_fee || 0).toFixed(2)); $('#sc-note').val(c.note || '');
		$('#sc-fixed-box').toggle(c.fee_mode === 'FIXED');
		$('#scModal').modal('show');
		$(document).trigger('sc:open', [c]);
	}

	$(document).on('change', '#sc-feemode', function () { $('#sc-fixed-box').toggle($(this).val() === 'FIXED'); });
	$(document).on('click', '#scAdd', () => openForm());
	$(document).on('click', '.sc-edit', function () {
		call('scGet', {id: $(this).data('id')}).then(openForm).catch(fail);
	});
	$(document).on('click', '#scSave', function () {
		const name = $.trim($('#sc-name').val());
		if (!name) { Swal.fire({icon: 'warning', title: 'اسم الشركة مطلوب'}); return; }
		call('scSave', {
			id: $('#sc-id').val(), name: name, receiving_policy: $('#sc-policy').val(), settlement_method: $('#sc-method').val(),
			fee_mode: $('#sc-feemode').val(), fixed_fee: $('#sc-fixed').val(), note: $('#sc-note').val()
		}).then(r => (window.scImportSaveHook ? window.scImportSaveHook(r && r.id) : null)).then(() => { $('#scModal').modal('hide'); return load(); }).catch(fail);
	});
	$(document).on('click', '.sc-toggle', function () {
		const id = $(this).data('id'), active = $(this).data('active');
		Swal.fire({icon: 'question', title: active ? 'تفعيل الشركة؟' : 'إيقاف الشركة؟', showCancelButton: true, confirmButtonText: 'نعم', cancelButtonText: 'إلغاء'})
			.then(r => { if (r.isConfirmed) { call('scSetActive', {id: id, active: active}).then(load).catch(fail); } });
	});

	$(document).on('click', '.sc-rates', function () {
		currentRatesCompany = Number($(this).data('id'));
		const c = rows.find(x => Number(x.id) === currentRatesCompany);
		$('#scRatesTitle').text('أسعار المناطق — ' + (c ? c.name : ''));
		$('#scRatesSave').toggle(canAdmin);
		call('scZones', {id: currentRatesCompany}).then(z => {
			const $tb = $('#scRatesTable tbody').empty();
			z.forEach(r => {
				$tb.append('<tr><td>' + esc(r.zone_name) + '</td><td>' + Number(r.customer_rate).toFixed(2) + '</td><td><input type="number" min="0" step="0.01" class="form-control form-control-sm sc-rate" data-zone="' + r.zone_id + '" value="' + (r.fee === null ? '' : Number(r.fee).toFixed(2)) + '"' + (canAdmin ? '' : ' disabled') + '></td></tr>');
			});
			$('#scRatesModal').modal('show');
		}).catch(fail);
	});
	$(document).on('click', '#scRatesSave', function () {
		const rates = {};
		$('.sc-rate').each(function () { rates[$(this).data('zone')] = $(this).val(); });
		call('scRatesSave', {id: currentRatesCompany, rates: rates}).then(() => { $('#scRatesModal').modal('hide'); return load(); }).catch(fail);
	});

	$(function () {
		if (canAdmin) { $('#scAdd').show(); }
		load();
	});
})();
