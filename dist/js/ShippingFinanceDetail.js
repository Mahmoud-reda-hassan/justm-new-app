/*
 * الاستلام المالي: عند اختيار مندوب مربوط بشركة شحن يظهر تحت كل أمر سطر تفصيل مالي:
 *  - المطلوب تحصيله، ورسم الشركة المتوقع ومصدره، وطريقة التسوية، وصافي الصندوق، والمستحق للشركة، والعجز المفتوح
 *  - ما ورد في كشف الشركة (إن وُجد) والفرق عن المطلوب
 * لا يغيّر منطق الجدول ولا التسجيل، وهو للعرض فقط.
 */
(function () {
	if (new URLSearchParams(window.location.search).get('Go') !== 'ReceivePayments') { return; }
	const url = '../../inc/controller/shippingCompanies.control.php';
	const esc = (v) => $('<div>').text(v == null ? '' : v).html();
	const call = (ctl, data) => new Promise((resolve, reject) => {
		$.post(url, Object.assign({CONTROL: ctl}, data || {}), function (r) {
			if (Array.isArray(r) && r[0] === 'success') { resolve(r[3]); } else { reject(new Error((r && r[2]) || 'خطأ')); }
		}, 'json').fail(() => reject(new Error('تعذر الاتصال بالخادم')));
	});
	const K = () => window.ScImport || {ACTIONS: {}, DERIVED: {}, FLAG: {}, money: (v) => v};
	const money = (v) => Number(v || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
	const SRC = {STATEMENT: 'من كشف الشركة', ORDER_SHIPPING: 'نفس شحن الأمر', FIXED: 'رسم ثابت', ZONE_RATE: 'سعر المنطقة', RETURN_FIXED: 'رسم المرتجع', NONE: 'بدون رسم'};
	const METHOD = {NET_OF_FEE: 'الشركة تخصم رسمها من المحصّل', GROSS_THEN_FEE: 'الشركة تورّد الكامل ونحاسبها على الرسم'};

	let company = null, seq = 0;
	const getTable = () => ($.fn.DataTable && $.fn.DataTable.isDataTable('#tbl')) ? $('#tbl').DataTable() : null;
	const courierId = () => $('#SelectUserDelivery').val();
	const badge = (cls, html) => '<span class="badge ' + cls + ' p-2 mr-1 mb-1" style="font-size:.85rem;font-weight:600">' + html + '</span>';

	function block(required, fin, st) {
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
			ids.push(Number(d[0])); rows[Number(d[0])] = {node: this.node(), required: Number(d[28] || 0)};
		});
		if (!ids.length) { return; }
		const my = ++seq, cid = company.id;
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
				$(r.node).after('<tr class="scfin-row"><td colspan="' + cols + '" class="p-0">' + block(r.required, (fin.orders || {})[id], (st || {})[id]) + '</td></tr>');
			});
		} catch (e) { if (my === seq) { showErr(e.message); } 		}
	}

	function loadCompany() {
		const id = courierId(); company = null; clear();
		if (!id) { return; }
		call('scFinanceInfo', {user_id: id, order_ids: '[]'}).then(r => { if (id !== courierId()) { return; }
			if (r.company) { company = r.company; decorate(); }
			else { $('#scfinMsg').remove(); $('#tbl').closest('.dataTables_wrapper, .table-responsive, .card-body').first().before('<div id="scfinMsg" class="alert alert-light border py-1 small text-muted">المندوب المختار ليس مربوطًا بشركة شحن، فلا يظهر تفصيل الشركة.</div>'); } }).catch(e => showErr(e.message));
	}

	$(document).on('draw.dt', function (e, settings) { if (settings && settings.nTable && settings.nTable.id === 'tbl') { decorate(); } });
	$(document).on('change', '#SelectUserDelivery', loadCompany);
	$(function () { if (courierId()) { loadCompany(); } });
})();
