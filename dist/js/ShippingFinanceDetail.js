/*
 * الاستلام المالي: عند اختيار مندوب مربوط بشركة شحن يظهر تحت كل أمر سطر تفصيل مالي:
 *  - المطلوب تحصيله، ورسم الشركة المتوقع ومصدره، وطريقة التسوية، وصافي الصندوق، والمستحق للشركة، والعجز المفتوح
 *  - ما ورد في كشف الشركة (إن وُجد) والفرق عن المطلوب
 * لا يغيّر منطق الجدول ولا التسجيل، وهو للعرض فقط.
 */
(function () {
	if (window.__scFinLoaded) { return; }
	window.__scFinLoaded = true;
	// يعمل فقط في شاشة الاستلام المالي (وجود تبويب المرفوضات والمندوب)
	if (!$('#SelectUserDelivery').length || !$('#OLRejected').length) { return; }
	const url = '../../inc/controller/shippingCompanies.control.php';
	const esc = (v) => $('<div>').text(v == null ? '' : v).html();
	const call = (ctl, data) => new Promise((resolve, reject) => {
		$.post(url, Object.assign({CONTROL: ctl}, data || {}), function (r) {
			if (Array.isArray(r) && r[0] === 'success') { resolve(r[3]); } else { reject(new Error((r && r[2]) || 'خطأ')); }
		}, 'json').fail(() => reject(new Error('تعذر الاتصال بالخادم')));
	});
	const K = () => window.ScImport || {ACTIONS: {}, DERIVED: {}, FLAG: {}, money: (v) => v};
	const money = (v) => Number(v || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
	const SRC = {STATEMENT: 'من كشف الشركة', ORDER_SHIPPING: 'نفس شحن الأمر', FIXED: 'رسم ثابت', ZONE_RATE: 'سعر المنطقة', RETURN_FIXED: 'رسم المرتجع', MANUAL: 'تعديل يدوي', NONE: 'بدون رسم'};
	const METHOD = {NET_OF_FEE: 'الشركة تخصم رسمها من المحصّل', GROSS_THEN_FEE: 'الشركة تورّد الكامل ونحاسبها على الرسم'};

	let company = null, seq = 0;
	const getTable = () => ($.fn.DataTable && $.fn.DataTable.isDataTable('#tbl')) ? $('#tbl').DataTable() : null;
	const courierId = () => $('#SelectUserDelivery').val();
	const badge = (cls, html) => '<span class="badge ' + cls + ' p-2 mr-1 mb-1" style="font-size:.85rem;font-weight:600">' + html + '</span>';

	function block(required, fin, st, o) {
		const method = company.method;
		let h = '<div class="p-2 scfin" style="border-right:4px solid #28a745;background:#f4fbf6;text-align:right;white-space:normal"><div class="d-flex flex-wrap align-items-center">';
		h += badge('badge-success', '<i class="fa fa-truck"></i> ' + esc(company.name));
		h += badge('badge-light border', 'المطلوب تحصيله: ' + money(required));
		if (fin && fin.fee != null) {
			const fee = Number(fin.fee);
			const cash = method === 'NET_OF_FEE' ? Math.max(required - fee, 0) : required;
			const payable = method === 'NET_OF_FEE' ? Math.max(fee - required, 0) : fee;
			h += badge('badge-light border', 'رسم الشركة: ' + money(fee) + ' (' + esc(SRC[fin.source] || fin.source) + ')');
			h += badge('badge-info', 'صافي الصندوق: ' + money(cash));
			h += badge(payable > 0 ? 'badge-warning' : 'badge-light border', 'مستحق للشركة: ' + money(payable));
		} else if (fin && fin.note) {
			h += badge('badge-danger', esc(fin.note));
		}
		if (fin && fin.shortage > 0) { h += badge('badge-danger', 'عجز مفتوح على الشركة: ' + money(fin.shortage)); }
		h += badge('badge-secondary', esc(METHOD[method] || method));
		if (st) {
			const k = K(), flag = k.FLAG[st.flag] || ['badge-secondary', st.flag];
			const diff = Number(st.amount || 0) - Number(required || 0);
			h += badge('badge-info', '<i class="fa fa-file-excel"></i> المحصّل في الكشف: ' + money(st.amount));
			if (st.file_status) { h += badge('badge-dark', 'حالة الكشف: ' + esc(st.file_status)); }
			h += badge(Math.abs(diff) > 0.5 ? 'badge-danger' : 'badge-light border', 'فرق الكشف عن المطلوب: ' + money(diff));
			if (st.derived) { h += badge('badge-primary', 'الاستنتاج: ' + esc(k.DERIVED[st.derived] || st.derived)); }
			h += badge(flag[0], esc(flag[1]));
		} else {
			h += badge('badge-light border', 'لا يوجد كشف لهذا الأمر');
		}
		h += '</div>';
		if (st && (st.notes || []).length) { h += '<div class="small text-danger mt-1">' + st.notes.map(esc).join(' — ') + '</div>'; }
		return h + actions(arguments[3]) + '</div>';
	}

	// أزرار التسوية والتصحيح تحت كل أمر (القرار المالي)
	function actions(o) {
		if (!o) { return ''; }
		let h = '<div class="mt-2 scfin-actions">';
		if (company.id && Number(o.returns) === 1) {
			h += '<button type="button" class="btn btn-sm btn-outline-danger mr-1 scfin-short" data-id="' + o.id + '" data-no="' + esc(o.no) + '"><i class="fa fa-exclamation-triangle"></i> عجز مرتجع على الشركة</button>';
		}
		h += '<button type="button" class="btn btn-sm btn-outline-primary mr-1 scfin-calc" data-id="' + o.id + '" data-no="' + esc(o.no) + '"><i class="fa fa-calculator"></i> تسوية الشحن</button>';
		h += '<button type="button" class="btn btn-sm btn-outline-secondary scfin-reopen" data-id="' + o.id + '" data-no="' + esc(o.no) + '"><i class="fa fa-undo"></i> رد للمخزن للتصحيح</button>';
		return h + '</div>';
	}

	function clear() { $('#tbl tbody tr.scfin-row').remove(); $('#scfinMsg').remove(); }
	function showErr(msg) {
		$('#scfinMsg').remove();
		$('#tbl').closest('.dataTables_wrapper, .table-responsive, .card-body').first().before('<div id="scfinMsg" class="alert alert-danger py-1 small">تعذّر تحميل تفصيل شركة الشحن: ' + esc(msg) + '</div>');
		console.warn('ShippingFinanceDetail:', msg);
	}

	async function decorate() {
		const t = getTable(); clear();
		if (!t || !company) { return; }
		const ids = [], rows = {};
		t.rows({page: 'current'}).every(function () {
			const d = this.data(); if (!d) { return; }
			ids.push(Number(d[0])); rows[Number(d[0])] = {node: this.node(), required: Number(d[28] || 0), o: {id: Number(d[0]), no: d[1], returns: d[16]}};
		});
		if (!ids.length) { return; }
		const my = ++seq, cid = company.id;
		if (company.plain) {
			const cols0 = $('#tbl thead tr:first th').length || 1;
			ids.forEach(id => { const r = rows[id]; if (r && r.node) { $(r.node).after('<tr class="scfin-row"><td colspan="' + cols0 + '" class="p-0"><div class="p-2" style="border-right:4px solid #6c757d;background:#f8f9fa;text-align:right;white-space:normal">' + actions(r.o).replace('mt-2 ', '') + '</div></td></tr>'); } });
			return;
		}
		try {
			const [fin, st] = await Promise.all([
				call('scFinanceInfo', {user_id: courierId(), order_ids: JSON.stringify(ids)}),
				call('scStatementOrders', {company_id: cid, order_ids: JSON.stringify(ids)})
			]);
			if (my !== seq || !company || company.id !== cid) { return; }
			clear();
			const cols = $('#tbl thead tr:first th').length || 1;
			ids.forEach(id => {
				const r = rows[id]; if (!r || !r.node) { return; }
				$(r.node).after('<tr class="scfin-row"><td colspan="' + cols + '" class="p-0">' + block(r.required, (fin.orders || {})[id], (st || {})[id], r.o) + '</td></tr>');
			});
		} catch (e) { if (my === seq) { showErr(e.message); } 		}
	}

	function loadCompany() {
		const id = courierId(); company = null; window.ScFinCompany = null; clear();
		if (!id) { return; }
		call('scFinanceInfo', {user_id: id, order_ids: '[]'}).then(r => { if (id !== courierId()) { return; }
			if (r.company) { company = r.company; window.ScFinCompany = company; decorate(); }
			else { company = {id: 0, name: '', method: '', plain: true}; decorate(); } }).catch(e => showErr(e.message));
	}

	function reload() { const t = getTable(); if (t) { t.ajax.reload(null, false); } }
	const done = (m) => { if (window.Swal) { Swal.fire({icon: 'success', title: m, timer: 1500, showConfirmButton: false}); } reload(); };
	const fail = (e) => { if (window.Swal) { Swal.fire({icon: 'error', title: 'تعذّر التنفيذ', text: e.message}); } else { alert(e.message); } };

	// تسوية الشحن: يشرح للمستخدم كيف يُحسب شحن الأمر حسب إعدادات الشركة أو نسبة المندوب
	$(document).on('click', '.scfin-calc', async function () {
		const id = $(this).data('id'), no = $(this).data('no');
		try {
			const d = await call('scShippingCalc', {order_id: id, user_id: courierId()});
			const row = (k, v) => '<tr><td class="text-muted">' + k + '</td><td class="font-weight-bold">' + v + '</td></tr>';
			let t = '<table class="table table-sm text-right mb-0">';
			t += row('حالة الأمر', d.rejected ? 'مرفوض' : 'مسلَّم');
			t += row('شحن العميل في الأمر', money(d.order_shipping) + (d.customer_paid_shipping ? ' (مدفوع)' : ' (غير مدفوع)'));
			let note = '';
			if (d.type === 'COMPANY') {
				const c = d.company, M = {FIXED: 'رسم ثابت لكل أمر', ZONE_RATE: 'سعر حسب المنطقة', ORDER_SHIPPING: 'نفس شحن الأمر'}, R = {NONE: 'بدون رسم', SAME_AS_SHIPPING: 'نفس رسم الشحن', FIXED: 'رسم ثابت ' + money(c.return_fixed_fee), STATEMENT_ONLY: 'من الكشف فقط'};
				t += row('الشركة', esc(c.name));
				t += row('طريقة رسم الشركة', esc(M[c.fee_mode] || c.fee_mode) + (c.fee_mode === 'FIXED' ? ' (' + money(c.fixed_fee) + ')' : ''));
				if (d.rejected && !d.customer_paid_shipping) { t += row('رسم المرفوض غير المدفوع', esc(R[c.return_fee_mode] || c.return_fee_mode)); }
				if (d.statement) { t += row('في كشف الشركة', 'المحصّل ' + money(d.statement.amount) + (d.statement.fee != null ? ' — الرسم ' + money(d.statement.fee) : '')); }
				if (d.fee != null) {
					t += row('الرسم المعتمد', money(d.fee) + ' (' + esc(SRC[d.source] || d.source) + ')');
					const diff = Number(d.order_shipping) - Number(d.fee);
					t += row('الفرق عن شحن العميل', money(diff) + (diff > 0 ? ' لصالحنا' : diff < 0 ? ' لصالح الشركة' : ''));
				} else { note = d.note || ''; }
				t += row('طريقة التسوية', esc(METHOD[c.method] || c.method));
			} else {
				t += row('المندوب', 'عمولة نسبة من شحن الأمر');
				t += row('نسبة المندوب', money(d.percent) + '%');
				t += row('الأساس', money(d.commission_base));
				t += row('عمولة المندوب', money(d.commission));
				if (d.rejected && !d.customer_paid_shipping) { note = 'أمر مرفوض لم يدفع عميله الشحن: العمولة تُحسب على شحن النظام حسب النسبة المعتمدة.'; }
			}
			t += '</table>' + (note ? '<div class="alert alert-warning mt-2 mb-0 small">' + esc(note) + '</div>' : '');
			const cur = d.type === 'COMPANY' ? (d.fee != null ? d.fee : 0) : d.commission;
			const label = d.type === 'COMPANY' ? 'رسم الشركة' : 'عمولة المندوب';
			if (d.override) { t += '<div class="alert alert-info mt-2 mb-0 small">معدَّل يدويًا (الأصل: ' + money(d.type === 'COMPANY' ? d.auto_fee : d.auto_commission) + ') — السبب: ' + esc(d.override.reason) + '</div>'; }
			t += '<div class="mt-3 text-right"><label class="small mb-1">تعديل ' + label + ' لهذا الأمر</label><input id="ov-amt" type="number" min="0" step="0.01" class="form-control form-control-sm mb-1" value="' + Number(cur || 0).toFixed(2) + '"><input id="ov-reason" class="form-control form-control-sm" placeholder="سبب التعديل (مطلوب)"></div>';
			const r = await Swal.fire({title: 'تسوية الشحن — الأمر ' + no, html: t, width: 560, showCancelButton: true, showDenyButton: !!d.override, confirmButtonText: 'حفظ التعديل', denyButtonText: 'إرجاع للحساب التلقائي', cancelButtonText: 'إغلاق',
				preConfirm: () => { const a = $('#ov-amt').val(), why = ($('#ov-reason').val() || '').trim(); if (a === '' || Number(a) < 0) { Swal.showValidationMessage('المبلغ غير صحيح'); return false; } if (!why) { Swal.showValidationMessage('سبب التعديل مطلوب'); return false; } return {amount: a, reason: why}; }});
			if (r.isConfirmed) { await call('scOverrideSave', {order_id: id, user_id: courierId(), amount: r.value.amount, reason: r.value.reason}); done('تم حفظ التعديل'); }
			else if (r.isDenied) { await call('scOverrideClear', {order_id: id, user_id: courierId()}); done('تم الرجوع للحساب التلقائي'); }
		} catch (e) { fail(e); }
	});

	// رد الأمر للمخزن للتصحيح (نفس منطق شاشة رد أوامر المندوب: قيد مضاد، لا حذف، والصلاحية من السيرفر)
	$(document).on('click', '.scfin-reopen', async function () {
		const id = $(this).data('id'), no = $(this).data('no');
		const r = await Swal.fire({title: 'رد الأمر ' + no + ' للمخزن للتصحيح؟', input: 'text', inputLabel: 'السبب (مطلوب)', showCancelButton: true, confirmButtonText: 'رد للمخزن', cancelButtonText: 'إلغاء', inputValidator: v => v && v.trim() ? null : 'السبب مطلوب'});
		if (!r.isConfirmed) { return; }
		$.post('../../inc/controller/shipment.control.php', {CONTROL: 'reopenCourierOrder', order_id: id, reason: r.value}, function (res) {
			if (Array.isArray(res) && res[0] === 'success') { done('تم رد الأمر للمخزن'); } else { fail(new Error((res && res[2]) || 'خطأ')); }
		}, 'json').fail(() => fail(new Error('تعذر الاتصال بالخادم')));
	});

	// عجز مرتجع على الشركة (يخرج من المخزون ويُسجَّل مستحق على الشركة)
	$(document).on('click', '.scfin-short', async function () {
		const id = $(this).data('id'), no = $(this).data('no');
		try {
			const d = await call('scShortageLines', {order_id: id});
			const lines = (d.lines || []).filter(l => Number(l.remaining) > 0);
			if (!lines.length) { return fail(new Error('لا توجد كميات متبقية لتسجيل عجز عليها.')); }
			const html = '<table class="table table-sm text-right"><tr><th>الصنف</th><th>المتبقي</th><th>كمية العجز</th></tr>' + lines.map((l, i) => '<tr><td>' + esc(l.pro_code) + '</td><td>' + esc(l.remaining) + '</td><td><input type="number" min="0" max="' + esc(l.remaining) + '" value="0" class="form-control form-control-sm sh-q" data-i="' + i + '"></td></tr>').join('') + '</table><input id="sh-note" class="form-control" placeholder="ملاحظة (اختياري)">';
			const r = await Swal.fire({title: 'عجز على الشركة — الأمر ' + no, html: html, width: 600, showCancelButton: true, confirmButtonText: 'تسجيل العجز', cancelButtonText: 'إلغاء'});
			if (!r.isConfirmed) { return; }
			const out = [];
			$('.sh-q').each(function () { const q = Number($(this).val() || 0), i = $(this).data('i'); if (q > 0) { out.push([lines[i].pro_code, q]); } });
			if (!out.length) { return; }
			await call('scShortageRecord', {order_id: id, lines: JSON.stringify(out), note: $('#sh-note').val() || ''});
			done('تم تسجيل العجز على الشركة');
		} catch (e) { fail(e); }
	});

	$(document).on('draw.dt', function (e, settings) { if (settings && settings.nTable && settings.nTable.id === 'tbl') { decorate(); } });
	$(document).on('change', '#SelectUserDelivery', loadCompany);
	$(function () { if (courierId()) { loadCompany(); } });
})();
