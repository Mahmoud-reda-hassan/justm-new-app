/*
 * عهدة المندوبين: عند اختيار مندوب مربوط بشركة شحن
 *  - زر رفع كشف الشركة (يُحفظ في قاعدة البيانات ويُربط بالأوامر، ولا حاجة لرفعه كل مرة)
 *  - سطر تحت كل أمر وارد في الكشف بعرض الجدول: شارات بيانات الكشف والتحليل
 *  - زر عرض "الواردة من الشركة فقط" أو "كل أوامر العهدة"
 * لا يغيّر منطق التبويبات ولا أي إجراء على الأمر.
 */
(function () {
	if (new URLSearchParams(window.location.search).get('Go') !== 'OrdersCustody') { return; }
	const url = '../../inc/controller/shippingCompanies.control.php';
	const esc = (v) => $('<div>').text(v == null ? '' : v).html();
	const call = (ctl, data) => new Promise((resolve, reject) => {
		$.post(url, Object.assign({CONTROL: ctl}, data || {}), function (r) {
			if (Array.isArray(r) && r[0] === 'success') { resolve(r[3]); } else { reject(new Error((r && r[2]) || 'خطأ')); }
		}, 'json').fail(x => reject(new Error('تعذر الاتصال بالخادم (' + (x.status || 'بلا رد') + ')')));
	});
	const fail = (e) => Swal.fire({icon: 'error', title: 'خطأ', text: e.message});
	const K = () => window.ScImport || {ACTIONS: {}, DERIVED: {}, FLAG: {}, money: (v) => v};

	let infoLoaded = false;      // انتهى تحديد هل المندوب شركة أم عادي
	let company = null;          // الشركة المرتبطة بالمندوب المختار
	let cache = {};              // order_id -> صف الكشف (أو null لو لا يوجد)
	let filterOn = false;        // عرض الواردة من الشركة فقط
	let stmtIds = null;          // مجموعة أرقام الأوامر الواردة (للوضع المحلي)
	let seq = 0;

	const getTable = () => ($.fn.DataTable && $.fn.DataTable.isDataTable('#tbl')) ? $('#tbl').DataTable() : null;
	const courierId = () => $('#SelectUserDelivery').val();

	function infoText() {
		if (!company) { return ''; }
		const li = company.last_import;
		const pol = company.receiving_policy === 'SINGLE' ? 'سياسة الاستلام: إذن واحد' : 'سياسة الاستلام: دفعات';
		const pend = ' — مرتجعات معلّقة على الشركة: ' + (company.pending_returns || 0);
		return (li ? 'آخر كشف: ' + (li.file_name || '') + ' — ' + li.uploaded_at + ' — أوامر مرتبطة: ' + company.linked_orders : 'لم يُرفع كشف لهذه الشركة بعد') + ' — ' + pol + pend;
	}
	function canReceive() { try { return userlogin_data['uScript']['Admin']['OrdersCustody']['ShipmentApproved'] == 'true'; } catch (e) { return false; } }
	function paintTools() {
		if (!company) { $('#scCustodyTools').hide(); paintPending(); return; }
		$('#scCustodyName').text(company.name);
		$('#scCustodyInfo').text(infoText());
		$('#scFilterGroup').toggle(company.linked_orders > 0);
		$('#scShortageBtn').toggle(canReceive());
		$('#scFilterGroup button').removeClass('active').filter('[data-f="' + (filterOn ? 'STMT' : 'ALL') + '"]').addClass('active');
		$('#scCustodyTools').show(); paintPending();
	}
	function loadCompany() {
		const id = courierId();
		company = null; infoLoaded = false; cache = {}; filterOn = false; stmtIds = null; paintTools();
		if (!id) { return Promise.resolve(); }
		loadPending();
		return call('scCustodyInfo', {user_id: id}).then(c => { if (id === courierId()) { company = c; infoLoaded = true; paintTools(); decorate(); loadPending(); } }).catch(() => {});
	}

	// ---------- السطر تحت الأمر ----------
	function badge(cls, html) { return '<span class="badge ' + cls + ' p-2 mr-1 mb-1" style="font-size:.85rem;font-weight:600">' + html + '</span>'; }
	function rowHtml(d) {
		const k = K(), m = k.money;
		const flag = k.FLAG[d.flag] || ['badge-secondary', d.flag];
		const done = d.custody_state && d.custody_state !== 'WAITING';
		let h = '<div class="scstmt p-2" style="border-right:4px solid #17a2b8;background:#f2fafc;text-align:right;white-space:normal">' +
			'<div class="d-flex flex-wrap align-items-center">' +
			badge('badge-info', '<i class="fa fa-file-excel"></i> كشف الشركة') +
			(d.file_status ? badge('badge-dark', 'حالة الكشف: ' + esc(d.file_status)) : '') +
			badge('badge-light border', 'المحصل: ' + m(d.amount)) +
			badge('badge-light border', 'قيمة الأمر: ' + m(d.order_value)) +
			badge('badge-light border', 'المتوقع: ' + m(d.expected)) +
			badge('badge-light border', 'شحن النظام: ' + m(d.shipping)) +
			(d.company_shipping != null ? badge('badge-light border', 'شحن الشركة: ' + m(d.company_shipping) + (d.shipping_diff != null ? ' (فرق ' + m(d.shipping_diff) + ')' : '')) : '') +
			(d.derived ? badge('badge-primary', 'الاستنتاج: ' + esc(k.DERIVED[d.derived] || d.derived)) : '') +
			(d.proposed ? badge('badge-success', 'المقترح: ' + esc(k.ACTIONS[d.proposed] || d.proposed)) : '') +
			badge(flag[0], esc(flag[1])) +
			(done ? badge('badge-secondary', '<i class="fa fa-check"></i> سُجّلت نتيجة العهدة') : '') +
			'</div>';
		const notes = (d.notes || []).concat((d.candidates || []).length ? ['التوزيع المحتمل للكميات: ' + d.candidates.join(' | ')] : []);
		if (notes.length) { h += '<div class="small text-danger mt-1">' + notes.map(esc).join(' — ') + '</div>'; }
		return h + '<div class="small text-muted mt-1">من ملف: ' + esc(d.file_name || '') + ' — ' + esc(d.uploaded_at || '') + '</div></div>';
	}
	function apply(t, ids) {
		t.rows({page: 'current'}).every(function () {
			const d = this.data(); const oid = d && Number(d.oid);
			const s = cache[oid];
			if (s) { this.child(rowHtml(s), 'p-0').show(); } else { this.child.hide(); }
		});
	}
	function decorate() {
		const t = getTable(); if (!t) { return; }
		if (!company || !company.linked_orders) { t.rows().every(function () { if (this.child.isShown()) { this.child.hide(); } }); return; }
		const ids = [];
		t.rows({page: 'current'}).every(function () { const d = this.data(); if (d) { ids.push(Number(d.oid)); } });
		const missing = ids.filter(i => !(i in cache));
		if (!missing.length) { apply(t, ids); return; }
		const my = ++seq, cid = company.id;
		call('scStatementOrders', {company_id: cid, order_ids: JSON.stringify(missing)}).then(map => {
			if (my !== seq || !company || company.id !== cid) { return; }
			missing.forEach(i => { cache[i] = map[i] || null; });
			apply(t, ids);
		}).catch(() => {});
	}
	$(document).on('draw.dt', function (e, settings) { if (settings && settings.nTable && settings.nTable.id === 'tbl') { decorate(); } });

	// ---------- فلتر الواردة من الشركة ----------
	$(document).on('preXhr.dt', function (e, settings, data) {
		if (settings.nTable.id === 'tbl' && filterOn && company) { data.stmt = 1; }
	});
	$.fn.dataTable.ext.search.push(function (settings, data, idx) {
		if (settings.nTable.id !== 'tbl' || !filterOn || !company || !stmtIds || settings.oFeatures.bServerSide) { return true; }
		const d = $('#tbl').DataTable().row(idx).data();
		return !!d && stmtIds.has(Number(d.oid));
	});
	$(document).on('click', '#scFilterGroup button', function () {
		const want = $(this).data('f') === 'STMT';
		if (want === filterOn || !company) { return; }
		const t = getTable(); if (!t) { return; }
		const go = () => {
			filterOn = want; paintTools();
			if (t.settings()[0].oFeatures.bServerSide) { t.ajax.reload(); } else { t.draw(); }
		};
		if (want && !stmtIds) {
			call('scStatementIds', {company_id: company.id}).then(ids => { stmtIds = new Set(ids.map(Number)); go(); }).catch(fail);
		} else { go(); }
	});

	// ---------- رفع الكشف ----------
	$(document).on('click', '#scUploadBtn', function () {
		if (!company) { return; }
		if (!company.import_settings) {
			Swal.fire({icon: 'warning', title: 'إعدادات ملف الشركة غير مضبوطة', text: 'من "شركات الشحن" ← تعديل الشركة، ارفع ملفًا نموذجيًا وحدد الأعمدة واحفظ.'});
			return;
		}
		$('#scUploadFile').val('').trigger('click');
	});
	$(document).on('change', '#scUploadFile', async function () {
		const file = this.files[0]; if (!file || !company) { return; }
		let st; try { st = JSON.parse(company.import_settings); } catch (e) { return fail(new Error('إعدادات ملف الشركة تالفة. أعد ضبطها من شركات الشحن.')); }
		try {
			if (!window.ScImport) { throw new Error('أدوات قراءة الكشف غير محمّلة. حدّث الصفحة (Ctrl+F5).'); }
			Swal.fire({title: 'جاري قراءة الكشف وحفظه...', allowOutsideClick: false, didOpen: () => Swal.showLoading()});
			const p = await window.ScImport.parse(file, st);
			const r = await call('scStatementSave', {company_id: company.id, rows: JSON.stringify(p.rows), status_map: JSON.stringify(st.status_map || {}),
				includes_shipping: Number(st.includes_shipping) ? 1 : 0, tolerance: st.tolerance == null ? 1 : st.tolerance, file_name: file.name, file_hash: p.hash});
			const s = r.summary, m = r.not_in_file;
			Swal.fire({title: 'جاري تسجيل نتائج الأوامر السليمة...', allowOutsideClick: false, didOpen: () => Swal.showLoading()});
			let ap = {applied: [], failed: [], problems: []};
			try { ap = await call('scApplyStatement', {company_id: company.id}); } catch (e) { ap.failed.push({order: '—', reason: e.message}); }
			const M = K().money;
			const lbl = {OK: 'سليم', REVIEW: 'يحتاج مراجعة', ERROR: 'خطأ'};
			const probRows = ap.problems.map(p => '<tr><td>' + esc(p.order) + '</td><td>' + esc(p.file_status || '—') + '</td><td>' + (p.amount == null ? '—' : M(p.amount)) + '</td><td>' + (p.company_shipping == null ? '—' : M(p.company_shipping)) + '</td><td>' +
				(p.order_value == null ? '—' : M(p.order_value)) + '</td><td>' + (p.expected == null ? '—' : M(p.expected)) + '</td><td><span class="badge ' + (p.flag === 'ERROR' ? 'badge-danger' : 'badge-warning') + '">' + lbl[p.flag] + '</span><div class="small text-danger" style="white-space:nowrap">' + esc((p.notes || []).join(' — ')) + '</div></td></tr>').join('');
			const failRows = ap.failed.map(p => '<tr><td>' + esc(p.order) + '</td><td colspan="5" class="text-danger">' + esc(p.reason) + '</td><td><span class="badge badge-danger">لم يُسجَّل</span></td></tr>').join('');
			const full = ap.applied.filter(x => x.action === 'تسليم كامل').length, rej = ap.applied.length - full;
			await Swal.fire({icon: (ap.problems.length || ap.failed.length) ? 'warning' : 'success', title: 'تم حفظ الكشف', width: 900, confirmButtonText: 'إغلاق', html:
				'<div class="text-right" dir="rtl">إجمالي الصفوف: <b>' + s.total + '</b><br>' +
				'سُجِّل تلقائيًا: <b class="text-success">' + ap.applied.length + '</b> (تسليم كامل فقط). المرفوض والمراجعة يدويًا.<br>' +
				'يحتاج مراجعتك: <b class="text-danger">' + (ap.problems.length + ap.failed.length) + '</b> — لم يُسجَّل لها شيء.<br>' +
				(m.count ? 'أوامر على العهدة ولم تظهر في الكشف: <b>' + m.count + '</b><br>' : '') +
				((probRows || failRows) ? '<div style="max-height:340px;overflow:auto;-webkit-overflow-scrolling:touch" class="mt-2"><table class="table table-sm table-bordered mb-0" style="white-space:nowrap;width:max-content;min-width:100%"><thead><tr><th>الأمر</th><th>حالة الكشف</th><th>المحصل</th><th>شحن الشركة</th><th>قيمة الأمر</th><th>المتوقع</th><th>المشكلة</th></tr></thead><tbody>' + failRows + probRows + '</tbody></table></div>' : '') + '</div>'});
			cache = {}; stmtIds = null;
			company = await call('scCustodyInfo', {user_id: courierId()});
			paintTools(); decorate();
		} catch (e) { Swal.close(); fail(e); }
	});

	// ---------- استلام كل المرتجعات (شركة أو مندوب عادي) ----------
	let pend = {count: 0, items: [], sample: [], total_qty: 0};
	let pendSeq = 0;
	function paintPending() {
		const n = pend.count || 0;
		const plain = !!courierId() && !company && infoLoaded;
		$('#scPendingCount, #scPendingCountPlain').text(n);
		$('#scReceiveAllBtn').toggle(!!company && canReceive() && n > 0);
		$('#scPlainTools').toggle(plain && canReceive() && n > 0);
		if (company) { company.pending_returns = n; $('#scCustodyInfo').text(infoText()); }
	}
	function loadPending() {
		const id = courierId(), my = ++pendSeq;
		if (!id) { pend = {count: 0, items: [], sample: [], total_qty: 0}; paintPending(); return Promise.resolve(); }
		return call('scPendingReturns', {user_id: id}).then(r => { if (my === pendSeq && id === courierId()) { pend = r; paintPending(); } }).catch(() => {});
	}
	// أي إجراء يغيّر المرتجعات المعلّقة: حدّث الزر والعدد فورًا بلا رفريش
	$(document).ajaxSuccess(function (e, xhr, opt) {
		const d = typeof opt.data === 'string' ? opt.data : '';
		if (/shipment\.control\.php/.test(opt.url || '') && /CONTROL=(shipDelivered|shipDelivery|approvedShip|companyReceiveAll|courierReceiveAll|reopenCourierOrder|shipConfirmed|setConfirmShipment)/.test(d)) {
			setTimeout(loadPending, 400);
		}
	});
	$(document).on('draw.dt', function (e, settings) { if (settings && settings.nTable && settings.nTable.id === 'tbl' && courierId()) { loadPending(); } });

	$(document).on('click', '#scReceiveAllBtn, #scReceiveAllPlainBtn', async function () {
		const id = courierId(); if (!id) { return; }
		try { await loadPending(); } catch (e) {}
		if (!pend.count) { return Swal.fire({icon: 'info', title: 'لا توجد مرتجعات معلّقة'}); }
		const who = company ? company.name : $('#SelectUserDelivery option:selected').text().trim();
		const rows = pend.items.map(i => '<tr><td>' + esc(i.name) + (i.options ? '<div class="small text-muted">' + esc(i.options) + '</div>' : '') + '</td><td><b>' + i.qty + '</b></td><td>' + i.orders + '</td></tr>').join('');
		const r = await Swal.fire({icon: 'question', title: 'استلام كل مرتجعات ' + who, width: 640, showCancelButton: true, confirmButtonText: 'تأكيد الاستلام', cancelButtonText: 'إلغاء',
			html: '<div class="text-right">سيُستلم مخزنيًا مرتجع <b>' + pend.count + '</b> أمر (<b>' + pend.total_qty + '</b> قطعة) بإذن واحد.' +
				'<div style="max-height:260px;overflow:auto" class="mt-2"><table class="table table-sm table-bordered"><thead><tr><th>الصنف / المواصفات</th><th>الكمية المرتجعة</th><th>عدد الأوامر</th></tr></thead><tbody>' + rows + '</tbody></table></div>' +
				'<small class="text-muted">الأوامر: ' + esc((pend.sample || []).join('، ')) + (pend.count > 15 ? ' …' : '') + '</small><br>' +
				'<span class="text-danger small">لا تؤكد إلا إذا الكميات أعلاه وصلتك فعليًا' + (company ? '؛ ما لم يصل يبقى عجزًا على الشركة' : '') + '.</span></div>'});
		if (!r.isConfirmed) { return; }
		$.post('../../inc/controller/shipment.control.php', {CONTROL: 'courierReceiveAll', delivery_id: id}, function (res) {
			Swal.fire({icon: res[0] === 'success' ? 'success' : (res[0] === 'warning' ? 'warning' : 'error'), title: res[1] || '', html: res[2] || ''});
			loadPending(); if (typeof updateOrders === 'function') { updateOrders(); }
		}, 'json').fail(() => fail(new Error('تعذر تنفيذ الاستلام.')));
	});

	// ---------- تسجيل عجز على الشركة (قطع لم تصل من أمر استُلم مخزنيًا) ----------
	$(document).on('click', '#scShortageBtn', async function () {
		if (!company) { return; }
		const ask = await Swal.fire({title: 'تسجيل عجز على ' + company.name, input: 'text', inputLabel: 'رقم الأمر (بعد استلام مرتجعه مخزنيًا)',
			showCancelButton: true, confirmButtonText: 'عرض القطع', cancelButtonText: 'إلغاء', inputValidator: v => !v && 'اكتب رقم الأمر'});
		if (!ask.isConfirmed) { return; }
		let info;
		try { info = await call('scShortageLines', {sub_order_id: ask.value, user_id: courierId()}); } catch (e) { return fail(e); }
		if (!info.lines.length) { return Swal.fire({icon: 'info', title: 'لا يوجد مرتجع متبقٍ', text: 'كل القطع المرتجعة في هذا الأمر مسجّلة أو لا يوجد مرتجع.'}); }
		const rows = info.lines.map((l, i) => '<tr><td>' + esc(l.pro_name || l.pro_code) + (l.pro_options ? '<div class="small text-muted">' + esc(l.pro_options) + '</div>' : '') + '</td><td>' + l.returned_qty + '</td><td>' + l.remaining + '</td>' +
			'<td><input type="number" min="0" max="' + l.remaining + '" value="0" class="form-control form-control-sm sc-short-qty" data-code="' + esc(l.pro_code) + '" style="width:80px"></td></tr>').join('');
		const r = await Swal.fire({title: 'القطع التي لم تصل', width: 640, showCancelButton: true, confirmButtonText: 'تسجيل العجز', cancelButtonText: 'إلغاء',
			html: '<div class="text-right"><table class="table table-sm"><thead><tr><th>المنتج</th><th>مرتجع</th><th>متبقٍ</th><th>لم يصل</th></tr></thead><tbody>' + rows + '</tbody></table>' +
				'<input id="scShortNote" class="form-control" placeholder="ملاحظة (اختياري)"><div class="small text-muted mt-2">يخرج من المخزون وتُرحِّل المالية قيده مستحقًا على الشركة بحسب إعداد التقييم لديها.</div></div>',
			preConfirm: () => {
				const lines = $('.sc-short-qty').map(function () { return [[$(this).data('code'), Number($(this).val() || 0)]]; }).get().filter(x => x[1] > 0);
				if (!lines.length) { Swal.showValidationMessage('اكتب كمية واحدة على الأقل'); return false; }
				return {lines: lines, note: $('#scShortNote').val()};
			}});
		if (!r.isConfirmed) { return; }
		try {
			const res = await call('scShortageRecord', {order_id: info.order_id, lines: JSON.stringify(r.value.lines), note: r.value.note});
			await Swal.fire({icon: 'success', title: 'تم تثبيت العجز', text: 'خرجت القطع من المخزون. قيمتها على الشركة ' + K().money(res.total) + ' وهي بانتظار ترحيل القيد من المالية.'});
		} catch (e) { fail(e); }
	});

	$(document).on('change', '#SelectUserDelivery', loadCompany);
	$(function () { if (courierId()) { loadCompany(); } });
})();
