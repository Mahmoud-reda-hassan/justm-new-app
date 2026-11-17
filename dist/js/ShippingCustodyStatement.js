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
		return li ? 'آخر كشف: ' + (li.file_name || '') + ' — ' + li.uploaded_at + ' — أوامر مرتبطة: ' + company.linked_orders : 'لم يُرفع كشف لهذه الشركة بعد';
	}
	function paintTools() {
		if (!company) { $('#scCustodyTools').hide(); return; }
		$('#scCustodyName').text(company.name);
		$('#scCustodyInfo').text(infoText());
		$('#scFilterGroup').toggle(company.linked_orders > 0);
		$('#scFilterGroup button').removeClass('active').filter('[data-f="' + (filterOn ? 'STMT' : 'ALL') + '"]').addClass('active');
		$('#scCustodyTools').show();
	}
	function loadCompany() {
		const id = courierId();
		company = null; cache = {}; filterOn = false; stmtIds = null; paintTools();
		if (!id) { return Promise.resolve(); }
		return call('scCustodyInfo', {user_id: id}).then(c => { if (id === courierId()) { company = c; paintTools(); decorate(); } }).catch(() => {});
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
			await Swal.fire({icon: 'success', title: 'تم حفظ الكشف وربطه بالأوامر', html:
				'<div class="text-right">إجمالي الصفوف: <b>' + s.total + '</b><br>سليم: <b class="text-success">' + s.OK + '</b> — يحتاج مراجعة: <b class="text-warning">' + s.REVIEW + '</b> — خطأ: <b class="text-danger">' + s.ERROR + '</b><br>' +
				(m.count ? 'أوامر على العهدة ولم تظهر في الكشف: <b>' + m.count + '</b>' : 'كل أوامر العهدة الجارية ظهرت في الكشف.') + '</div>'});
			cache = {}; stmtIds = null;
			company = await call('scCustodyInfo', {user_id: courierId()});
			paintTools(); decorate();
		} catch (e) { Swal.close(); fail(e); }
	});

	$(document).on('change', '#SelectUserDelivery', loadCompany);
	$(function () { if (courierId()) { loadCompany(); } });
})();
