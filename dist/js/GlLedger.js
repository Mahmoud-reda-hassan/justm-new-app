(function () {
	const url = `../../inc/controller/gl.control.php`;
	const perms = window.GL_PERMS || {post: false, admin: false};
	const esc = (v) => $('<div>').text(v == null ? '' : v).html();
	const num = (v) => (v === null || v === undefined || v === '') ? '' : Number(v).toFixed(2);
	const txt = (d, t) => t === 'display' ? esc(d) : (d == null ? '' : String(d));
	const numr = (d, t) => t === 'display' ? esc(num(d)) : (d == null ? '' : Number(d));
	const escRe = (v) => v.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	const refNames = {COURIER_DELIVERY: 'تسليم مندوب', COURIER_COLLECTION: 'تحصيل مندوب', COURIER_SALES_RETURN: 'مرتجع مندوب',
		DELIVERY_PAYOUT: 'صرف مندوب', MARKETER_PAYOUT: 'صرف مسوق', PURCHASE_RECEIPT: 'استلام مشتريات', PURCHASE_RETURN: 'مرتجع مشتريات',
		SUPPLIER_PAYMENT: 'دفع مورد', SUPPLIER_REFUND: 'استرداد مورد', OPENING_BALANCE: 'رصيد افتتاحي', OPENING_CASH: 'افتتاحي نقدية',
		DIRECT_SALE: 'بيع مباشر', DIRECT_SALE_PAYMENT: 'تحصيل بيع مباشر', SALES_RETURN_PAYMENT: 'صرف مرتجع', JOURNAL_REVERSE: 'قيد عكسي'};
	const refName = (r) => refNames[r] || r || '';

	const call = (ctl, data) => new Promise((resolve, reject) => {
		$.post(url, Object.assign({CONTROL: ctl}, data || {}), function (r) {
			if (Array.isArray(r) && r[0] === 'success') { resolve(r[3]); } else { reject(new Error((r && r[2]) || 'خطأ')); }
		}, 'json').fail(x => reject(new Error('تعذر الاتصال بالخادم (' + (x.status || 'بلا رد') + ')')));
	});
	const fail = (e) => Swal.fire({icon: 'error', title: 'خطأ', text: e.message});

	const lang = (empty) => ({emptyTable: empty, zeroRecords: 'لا توجد نتائج مطابقة للفلاتر', search: 'بحث عام:', lengthMenu: 'عرض _MENU_',
		info: 'من _START_ إلى _END_ من _TOTAL_', paginate: {next: 'التالي', previous: 'السابق'}});

	// جدول بفلتر لكل عمود (نص) وترتيب بالضغط على العنوان
	function mk(sel, columns, opts) {
		opts = opts || {};
		const dt = $(sel).DataTable({
			data: [], columns: columns, ordering: true, orderCellsTop: true, order: opts.order || [], pageLength: opts.pageLength || 50,
			language: lang(opts.empty || 'لا توجد بيانات'), footerCallback: opts.footer
		});
		const $head = $(dt.table().header()), $tr = $('<tr class="column-filters"></tr>');
		dt.settings()[0].aoColumns.forEach((c, i) => {
			const $th = $('<th class="p-1"></th>');
			if (c.searchable !== false) { $th.append('<input type="text" class="form-control form-control-sm cf-input" data-col="' + i + '" placeholder="بحث">'); }
			$tr.append($th);
		});
		$head.append($tr);
		$head.on('keyup change input', '.cf-input', function () { dt.column(+$(this).data('col')).search(this.value).draw(); });
		return dt;
	}
	const load = (dt, rows) => { dt.clear().rows.add(rows || []).draw(); };

	// ---------- ميزان المراجعة ----------
	const tb = mk('#tb-table', [
		{data: 'account_code', render: txt}, {data: 'account_name', render: txt}, {data: 'account_type', render: txt},
		{data: 'opening_debit', render: numr}, {data: 'opening_credit', render: numr},
		{data: 'period_debit', render: numr}, {data: 'period_credit', render: numr},
		{data: 'closing_debit', render: numr}, {data: 'closing_credit', render: numr}
	], {order: [[0, 'asc']], pageLength: 100, empty: 'لا توجد حركة'});
	$('#tb-run').on('click', async function () {
		try {
			const rows = await call('glTrialBalance', {from: $('#tb-from').val(), to: $('#tb-to').val()});
			load(tb, rows);
			const s = (k) => rows.reduce((a, r) => a + Number(r[k]), 0);
			const dr = s('closing_debit'), cr = s('closing_credit');
			$('#tb-check').html(`إجمالي الختامي: مدين ${num(dr)} / دائن ${num(cr)} — ` +
				(Math.abs(dr - cr) < 0.01 ? '<span class="text-success">متوازن ✓</span>' : '<span class="text-danger">غير متوازن ✗ (' + num(dr - cr) + ')</span>'));
		} catch (e) { fail(e); }
	});

	// ---------- كشف حساب ----------
	const acc = mk('#acc-table', [
		{data: 'entry_date', render: txt}, {data: 'entry_no', render: txt}, {data: 'reference_type', render: (d, t) => t === 'display' ? esc(refName(d)) : (d || '')},
		{data: 'reference_id', render: txt}, {data: 'description', render: txt},
		{data: null, render: (d, t, r) => txt(r.partner_type ? r.partner_type + ' ' + r.partner_id : '', t)},
		{data: 'debit', render: numr}, {data: 'credit', render: numr}, {data: 'running', render: numr}
	], {empty: 'اختر حسابًا ثم اضغط عرض'});
	call('glAccountsList').then(rows => {
		$('#acc-id').append(rows.map(a => `<option value="${esc(a.id)}">${esc(a.account_code)} - ${esc(a.account_name)}</option>`).join(''));
	}).catch(fail);
	$('#acc-run').on('click', async function () {
		const id = $('#acc-id').val(); if (!id) { return Swal.fire({icon: 'info', title: 'اختر الحساب'}); }
		try {
			const d = await call('glAccountStatement', {account_id: id, from: $('#acc-from').val(), to: $('#acc-to').val()});
			load(acc, d.lines);
			$('#acc-sum').text('رصيد أول المدة: ' + num(d.opening) + ' — رصيد آخر المدة: ' + num(d.closing) + ' (مدين − دائن)');
		} catch (e) { fail(e); }
	});

	// ---------- كشف طرف ----------
	const pt = mk('#pt-table', [
		{data: 'entry_date', render: txt}, {data: 'entry_no', render: txt}, {data: 'reference_type', render: (d, t) => t === 'display' ? esc(refName(d)) : (d || '')},
		{data: 'reference_id', render: txt}, {data: null, render: (d, t, r) => txt(r.account_code + ' ' + r.account_name, t)}, {data: 'description', render: txt},
		{data: 'debit', render: numr}, {data: 'credit', render: numr}, {data: 'running', render: numr}
	], {empty: 'اختر طرفًا ثم اضغط عرض'});
	let ptTimer = null;
	const ptSearch = () => {
		clearTimeout(ptTimer);
		ptTimer = setTimeout(async () => {
			try {
				const rows = await call('glPartnerSearch', {type: $('#pt-type').val(), q: $('#pt-q').val()});
				$('#pt-id').html('<option value="">اختر الطرف</option>' + rows.map(r => `<option value="${esc(r.id)}">${esc(r.name)}</option>`).join(''));
			} catch (e) { fail(e); }
		}, 300);
	};
	$('#pt-q').on('input', ptSearch); $('#pt-type').on('change', ptSearch);
	$('#pt-run').on('click', async function () {
		const id = $('#pt-id').val(); if (!id) { return Swal.fire({icon: 'info', title: 'اختر الطرف'}); }
		try {
			const type = $('#pt-type').val();
			const d = await call('glPartnerStatement', {type: type, partner_id: id, from: $('#pt-from').val(), to: $('#pt-to').val()});
			load(pt, d.lines);
			const dir = type === 'CLIENT' ? 'موجب = على العميل' : 'موجب = مستحق له';
			const same = Math.abs(d.diff) < 0.01;
			$('#pt-sum').html(`رصيد أول المدة: <b>${num(d.opening)}</b> — آخر المدة: <b>${num(d.closing)}</b> (${dir})<br>` +
				`الرصيد الكلي في الأستاذ: <b>${num(d.gl_total)}</b> — في النظام القديم: <b>${num(d.legacy_total)}</b> — الفرق: ` +
				(same ? '<span class="text-success">0.00 ✓</span>' : `<span class="text-danger">${num(d.diff)} ✗</span>`));
		} catch (e) { fail(e); }
	});

	// ---------- دفتر اليومية ----------
	const jr = mk('#jr-table', [
		{data: 'entry_no', render: txt}, {data: 'entry_date', render: txt}, {data: 'reference_type', render: (d, t) => t === 'display' ? esc(refName(d)) : (d || '')},
		{data: 'reference_id', render: txt}, {data: 'description', render: txt}, {data: 'total_debit', render: numr}, {data: 'total_credit', render: numr}, {data: 'lines_count', render: txt}
	], {order: [[1, 'desc']], empty: 'اضغط عرض'});
	call('glRefTypes').then(rows => $('#jr-ref').append(rows.map(r => `<option value="${esc(r)}">${esc(refName(r))}</option>`).join(''))).catch(() => {});
	$('#jr-run').on('click', async function () {
		try { load(jr, await call('glJournal', {from: $('#jr-from').val(), to: $('#jr-to').val(), ref_type: $('#jr-ref').val()})); } catch (e) { fail(e); }
	});
	$('#jr-table tbody').on('click', 'tr', async function () {
		const row = jr.row(this).data(); if (!row) return;
		try {
			const lines = await call('glEntryLines', {entry_id: row.id});
			Swal.fire({
				title: 'قيد ' + esc(row.entry_no), width: 900,
				html: '<div class="table-responsive"><table class="table table-sm table-bordered text-right"><thead><tr><th>الحساب</th><th>مدين</th><th>دائن</th><th>الطرف</th></tr></thead><tbody>' +
					lines.map(l => `<tr><td>${esc(l.account_code)} ${esc(l.account_name)}</td><td>${esc(num(l.debit))}</td><td>${esc(num(l.credit))}</td><td>${esc(l.partner_type ? l.partner_type + ' ' + l.partner_id : '')}</td></tr>`).join('') +
					'</tbody></table></div><div class="text-right small">' + esc(row.description) + '</div>'
			});
		} catch (e) { fail(e); }
	});

	// ---------- افتتاحي النقدية ----------
	if (perms.post) {
		const cash = mk('#cash-table', [
			{data: 'kind', render: (d, t) => txt(d === 'BANK' ? 'بنك' : 'صندوق', t)}, {data: 'name', render: txt},
			{data: 'account_code', render: txt}, {data: 'opening', render: numr}, {data: 'gl_balance', render: numr},
			{data: null, searchable: false, orderable: false, render: (d, t, r) => `<input type="number" step="0.01" min="0" class="form-control form-control-sm cash-amt" data-acc="${esc(r.account_id)}" value="${esc(r.opening)}" style="width:150px">`},
			{data: null, searchable: false, orderable: false, render: (d, t, r) => `<button type="button" class="btn btn-sm btn-primary cash-save" data-acc="${esc(r.account_id)}">حفظ</button>`}
		], {empty: 'لا توجد صناديق أو بنوك'});
		const loadCash = () => call('glCashList').then(rows => load(cash, rows)).catch(fail);
		$('#cash-date').val(new Date().toISOString().slice(0, 10));
		$('#cash-table').on('click', '.cash-save', async function () {
			const acc = $(this).data('acc'), amt = $('.cash-amt[data-acc=' + acc + ']').val(), date = $('#cash-date').val();
			const ok = await Swal.fire({icon: 'question', title: 'تسجيل الرصيد الافتتاحي؟', text: 'المبلغ: ' + amt + ' بتاريخ ' + date, showCancelButton: true, confirmButtonText: 'نعم', cancelButtonText: 'إلغاء'});
			if (!ok.isConfirmed) return;
			try { await call('glCashSet', {account_id: acc, amount: amt, date: date}); Swal.fire({icon: 'success', title: 'تم'}); loadCash(); } catch (e) { fail(e); }
		});
		$('a[href="#tab-cash"]').one('shown.bs.tab', loadCash);

		// ---------- تصحيح تحصيل ----------
		$('#cor-find').on('click', async function () {
			try {
				const entries = await call('glCorrectionFind', {ref: $('#cor-ref').val()});
				if (!entries.length) { return $('#cor-result').html('<div class="alert alert-secondary">لا يوجد قيد تحصيل لهذا الأمر (لم يُحصَّل ماليًا بعد).</div>'); }
				$('#cor-result').html(entries.map(e => `<div class="card mb-3"><div class="card-header">قيد ${esc(e.entry_no)} — ${esc(e.entry_date)} — ${esc(num(e.total_debit))}
					${Number(e.reversed) ? '<span class="badge badge-secondary mr-2">معكوس</span>' : ''}</div><div class="card-body">
					<table class="table table-sm table-bordered"><thead><tr><th>الحساب</th><th>مدين</th><th>دائن</th><th>الطرف</th></tr></thead><tbody>` +
					e.lines.map(l => `<tr><td>${esc(l.account_code)} ${esc(l.account_name)}</td><td>${esc(num(l.debit))}</td><td>${esc(num(l.credit))}</td><td>${esc(l.partner_type ? l.partner_type + ' ' + l.partner_id : '')}</td></tr>`).join('') +
					`</tbody></table>` + (Number(e.reversed) ? '' : `<textarea class="form-control mb-2 cor-reason" rows="2" placeholder="سبب التصحيح (إلزامي)"></textarea>
					<button type="button" class="btn btn-danger cor-reverse" data-id="${esc(e.id)}">عكس هذا القيد</button>`) + '</div></div>').join(''));
			} catch (e) { fail(e); }
		});
		$('#cor-result').on('click', '.cor-reverse', async function () {
			const $b = $(this), reason = $b.closest('.card-body').find('.cor-reason').val();
			const ok = await Swal.fire({icon: 'warning', title: 'عكس قيد التحصيل؟', text: 'سيُسجَّل قيد عكسي على الأستاذ فقط ولا يُعاد فتح الأمر.', showCancelButton: true, confirmButtonText: 'نعم، اعكس', cancelButtonText: 'إلغاء'});
			if (!ok.isConfirmed) return;
			try { await call('glCorrectionPost', {entry_id: $b.data('id'), reason: reason}); Swal.fire({icon: 'success', title: 'تم عكس القيد'}); $('#cor-find').click(); } catch (e) { fail(e); }
		});
	}

	// ---------- الفترات والإعدادات ----------
	const per = mk('#per-table', [
		{data: 'date_from', render: txt}, {data: 'date_to', render: txt}, {data: 'note', render: txt},
		{data: 'is_closed', render: (d, t) => txt(Number(d) ? 'مقفولة' : 'مفتوحة', t)},
		{data: null, searchable: false, orderable: false, render: (d, t, r) => (perms.admin && Number(r.is_closed)) ? `<button type="button" class="btn btn-sm btn-outline-secondary per-open" data-id="${esc(r.id)}">إعادة فتح</button>` : ''}
	], {empty: 'لا توجد فترات مقفولة', pageLength: 25});
	const loadAdmin = async () => {
		try {
			load(per, await call('glPeriods'));
			const v = await call('glValuation');
			$('#adm-valuation').html(`قيمة المخزون في الأستاذ (1300): <b>${num(v.gl_inventory)}</b><br>بتكلفة الأصناف الحالية: <b>${num(v.current_cost_value)}</b><br>الفرق: <b class="${Math.abs(v.diff) < 0.01 ? 'text-success' : 'text-danger'}">${num(v.diff)}</b>`);
			$('#flag-source').val((await call('glFlagGet')).BALANCE_SOURCE);
		} catch (e) { $('#adm-valuation').text(e.message); }
	};
	$('a[href="#tab-admin"]').one('shown.bs.tab', loadAdmin);
	$('#per-close').on('click', async function () {
		const ok = await Swal.fire({icon: 'warning', title: 'إقفال الفترة؟', text: 'لن يُسمح بأي قيد بتاريخ داخلها حتى تُعاد فتحها.', showCancelButton: true, confirmButtonText: 'إقفال', cancelButtonText: 'إلغاء'});
		if (!ok.isConfirmed) return;
		try { await call('glPeriodClose', {from: $('#per-from').val(), to: $('#per-to').val(), note: $('#per-note').val()}); loadAdmin(); } catch (e) { fail(e); }
	});
	$('#per-table').on('click', '.per-open', async function () {
		try { await call('glPeriodOpen', {id: $(this).data('id')}); loadAdmin(); } catch (e) { fail(e); }
	});
	$('#flag-source').on('change', async function () {
		const v = this.value;
		const ok = await Swal.fire({icon: 'warning', title: 'تغيير مصدر الأرصدة؟', text: v === 'gl' ? 'شاشات صرف المستحقات ستقرأ الرصيد من الأستاذ بدل النظام القديم.' : 'الرجوع لقراءة الأرصدة من النظام القديم.', showCancelButton: true, confirmButtonText: 'نعم', cancelButtonText: 'إلغاء'});
		if (!ok.isConfirmed) { return loadAdmin(); }
		try { await call('glFlagSet', {key: 'BALANCE_SOURCE', value: v}); Swal.fire({icon: 'success', title: 'تم'}); } catch (e) { fail(e); loadAdmin(); }
	});
})();
