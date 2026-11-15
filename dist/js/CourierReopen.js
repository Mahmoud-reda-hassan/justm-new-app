(function () {
	const url = `../../inc/controller/shipment.control.php`;
	const statusNames = {3: 'تم التسليم', 4: 'تسليم جزئي', 5: 'مرفوض', 6: 'مرتجع', 7: 'مرتجع جزئي'};
	const esc = (v) => $('<div>').text(v == null ? '' : v).html();
	// نص للعرض (مهرّب) ونص خام للبحث/الفلترة
	const txt = (d, t) => t === 'display' ? esc(d) : (d == null ? '' : String(d));
	const escRe = (v) => v.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	const actionCol = (cls, icon, label) => ({
		data: null, className: 'text-center text-nowrap', orderable: false, searchable: false,
		render: (d, t, r) => `<button type="button" class="btn btn-sm ${cls.btn} ${cls.name}" data-oid="${esc(r.ORDER_ID)}"><i class="fa ${icon}"></i> ${label}</button>`
	});
	// أعمدة بيانات الأمر (مثل قائمة الأوامر). select = عمود قيمه قليلة فيُفلتر بقائمة منسدلة
	const orderCols = () => [
		{data: 'SUB_ORDER_ID', render: (d, t, r) => txt(d || r.ORDER_ID, t)},
		{data: 'ORDER_TYPE_NAME', render: txt, select: true},
		{data: 'CLIENT_NAME', render: txt},
		{data: 'TELEPHONE', render: txt},
		{data: 'ADDRESS', render: txt},
		{data: 'GOV_NAME', render: txt, select: true},
		{data: 'CITY_NAME', render: txt, select: true},
		{data: 'ZONE_NAME', render: txt, select: true},
		{data: 'DELIVERY_NAME', render: txt, select: true},
		{data: 'ORDERBY', render: txt, select: true},
		{data: 'STA_NAME', render: (d, t, r) => txt(d || statusNames[r.ORDER_STATUS] || r.ORDER_STATUS, t), select: true},
		{data: 'EXISTS_NAME', render: txt, select: true},
		{data: 'SUCCESS_STAT', render: txt, select: true},
		{data: 'PRO_QTY', render: txt},
		{data: 'ORDER_TOTAL', render: txt},
		{data: 'SHIPPING_VAL', render: txt},
		{data: 'DATE_CREATED', render: txt},
		{data: 'DELIVERED_DATE', render: txt},
		{data: 'LAST_UPDATE', render: txt}
	];
	const LAST_UPDATE_COL = 18; // ترتيب افتراضي: الأحدث تحديثًا أولًا

	// صف فلاتر تحت رأس الجدول: خانة بحث لكل عمود، وقائمة منسدلة للأعمدة محدودة القيم
	function addColumnFilters(dt, selectCols) {
		const cols = dt.settings()[0].aoColumns;
		const $head = $(dt.table().header());
		const $tr = $('<tr class="column-filters"></tr>');
		cols.forEach((c, i) => {
			const $th = $('<th class="p-1"></th>');
			if (c.searchable === false) { $tr.append($th); return; }
			if (selectCols.includes(i)) {
				$th.append('<select class="form-control form-control-sm cf-select" data-col="' + i + '"><option value="">الكل</option></select>');
			} else {
				$th.append('<input type="text" class="form-control form-control-sm cf-input" data-col="' + i + '" placeholder="بحث">');
			}
			$tr.append($th);
		});
		$head.append($tr);
		$head.on('keyup change input', '.cf-input', function () {
			dt.column(+$(this).data('col')).search(this.value).draw();
		});
		$head.on('change', '.cf-select', function () {
			const v = this.value;
			dt.column(+$(this).data('col')).search(v ? '^' + escRe(v) + '$' : '', true, false).draw();
		});
		dt.on('draw.cf', function () {}); // موضع تمديد
		return function refresh() {
			selectCols.forEach(i => {
				const $s = $head.find('.cf-select[data-col=' + i + ']'), cur = $s.val();
				const vals = [...new Set(dt.column(i).data().toArray().map(v => v == null ? '' : String(v)).filter(v => v !== ''))].sort();
				$s.html('<option value="">الكل</option>' + vals.map(v => `<option value="${esc(v)}">${esc(v)}</option>`).join(''));
				if (cur && vals.includes(cur)) { $s.val(cur); }
			});
		};
	}
	const selectIdx = (cols) => cols.map((c, i) => c.select ? i : -1).filter(i => i >= 0);
	const dtLang = (empty) => ({emptyTable: empty, zeroRecords: 'لا توجد نتائج مطابقة للفلاتر', search: 'بحث عام:', lengthMenu: 'عرض _MENU_', info: 'من _START_ إلى _END_ من _TOTAL_', paginate: {next: 'التالي', previous: 'السابق'}});


	const tableCols = orderCols().concat([]).concat([actionCol({btn: 'btn-warning', name: 'btnReopen'}, 'fa-undo', 'رد للمخزن')]);
	const table = $('#courier-reopen-table').DataTable({
		data: [],
		ordering: true,
		orderCellsTop: true,
		order: [[LAST_UPDATE_COL, 'desc']],
		pageLength: 25,
		language: dtLang('لا توجد أوامر قابلة للرد للمخزن'),
		columns: tableCols
	});
	const tableRefresh = addColumnFilters(table, selectIdx(tableCols));

	function load() {
		$.post(url, {CONTROL: 'listCourierReopen'}, function (res) {
			if (Array.isArray(res) && res[0] === 'success') {
				table.clear().rows.add(res[3] || []).draw(); tableRefresh();
			} else {
				Swal.fire({icon: 'error', title: 'خطأ', text: (res && res[2]) || 'تعذر تحميل القائمة'});
			}
		}, 'json').fail(() => Swal.fire({icon: 'error', title: 'خطأ', text: 'تعذر الاتصال بالخادم'}));
	}

	$('#courier-reopen-table').on('click', '.btnReopen', function () {
		const oid = $(this).data('oid');
		Swal.fire({
			icon: 'warning',
			title: 'رد الأمر للمخزن؟',
			text: 'ستُعكس القيود المالية وحركة المخزون بقيود مضادة، ويعود الأمر لأمين المخزن.',
			input: 'textarea',
			inputPlaceholder: 'سبب الرد (إلزامي)',
			inputValidator: (v) => (!v || !v.trim()) ? 'سبب الرد مطلوب' : null,
			showCancelButton: true,
			confirmButtonText: 'تأكيد الرد',
			cancelButtonText: 'إلغاء'
		}).then((r) => {
			if (!r.isConfirmed) return;
			$.post(url, {CONTROL: 'reopenCourierOrder', order_id: oid, reason: r.value.trim()}, function (res) {
				const ok = Array.isArray(res) && res[0] === 'success';
				Swal.fire({icon: ok ? 'success' : 'error', title: ok ? 'تم' : 'خطأ', text: (res && res[2]) || ''});
				if (ok) load();
			}, 'json').fail(() => Swal.fire({icon: 'error', title: 'خطأ', text: 'تعذر الاتصال بالخادم'}));
		});
	});

	load();

	// ---- ترحيل أوامر مُقفلة قبل تشغيل الأستاذ ----
	const bfTableCols = orderCols().concat([]).concat([actionCol({btn: 'btn-primary', name: 'btnBackfill'}, 'fa-book', 'ترحيل للأستاذ')]);
	const bfTable = $('#courier-backfill-table').DataTable({
		data: [],
		ordering: true,
		orderCellsTop: true,
		order: [[LAST_UPDATE_COL, 'desc']],
		pageLength: 25,
		language: dtLang('لا توجد أوامر مُقفلة بلا قيد'),
		columns: bfTableCols
	});
	const bfTableRefresh = addColumnFilters(bfTable, selectIdx(bfTableCols));

	let bfCount = 0, obCount = 0, urCount = 0;
	const fmt = (n) => Number(n || 0).toLocaleString('en-US', {maximumFractionDigits: 2});
	let summaryData = null;

	function summaryHtml(d) {
		let h = `<ul class="mb-0">
			<li>أوامر مُقفلة لم تُرحَّل: <b>${esc(d.closed_orders)}</b></li>
			<li>مرتجعات لم تُرحَّل: <b>${esc(d.returns)}</b> (قيمتها ${fmt(d.returns_value)})</li>
			<li>أرصدة بها فروق: <b>${esc(d.opening_total)}</b>`;
		const kinds = Object.keys(d.opening || {});
		if (kinds.length) {
			h += '<ul>' + kinds.map(k => `<li>${esc(kindNames[k] || k)}: ${esc(d.opening[k].count)} حساب، صافي الفرق ${fmt(d.opening[k].diff)} (إجمالي مطلق ${fmt(d.opening[k].abs)})</li>`).join('') + '</ul>';
		}
		return h + '</li></ul>';
	}

	function updateAlert() { loadSummary(); }

	function loadSummary() {
		$.post(url, {CONTROL: 'legacySummary'}, function (res) {
			const btn = $('#btnLegacyRunAll');
			if (Array.isArray(res) && res[0] === 'success') {
				summaryData = res[3];
				const d = summaryData;
				const total = d.closed_orders + d.returns + d.opening_total;
				$('#legacy-summary-body').html(summaryHtml(d));
				btn.prop('disabled', total === 0);
				$('#legacy-summary-card').toggleClass('d-none', total === 0);
			} else {
				$('#legacy-summary-card').removeClass('d-none');
				$('#legacy-summary-body').text((res && res[2]) || 'تعذر تحميل الملخص');
				btn.prop('disabled', true);
			}
		}, 'json');
	}

	$('#btnLegacyRunAll').on('click', function () {
		if (!summaryData) return;
		Swal.fire({
			icon: 'warning', title: 'ترحيل الكل؟',
			html: summaryHtml(summaryData) + '<p class="mt-2 mb-0">سيُنفَّذ بالترتيب: الأوامر ثم المرتجعات ثم الأرصدة. لا يُعدَّل أي بيان قديم.</p>',
			showCancelButton: true, confirmButtonText: 'نعم، رحّل الكل', cancelButtonText: 'إلغاء'
		}).then(function (r) {
			if (!r.isConfirmed) return;
			const btn = $('#btnLegacyRunAll').prop('disabled', true);
			const total = {orders: {done: 0, failed: 0}, returns: {done: 0, failed: 0}, opening: {done: 0, failed: 0}};
			const errors = [];
			const finish = function (fatal) {
				const failed = total.orders.failed + total.returns.failed + total.opening.failed;
				Swal.fire({
					icon: (failed || fatal) ? 'warning' : 'success', title: fatal ? 'توقف الترحيل' : 'اكتمل الترحيل',
					html: `أوامر: ${total.orders.done} تم / ${total.orders.failed} فشل<br>مرتجعات: ${total.returns.done} تم / ${total.returns.failed} فشل<br>أرصدة: ${total.opening.done} تم / ${total.opening.failed} فشل`
						+ (fatal ? `<br><b>${esc(fatal)}</b><br>ما تم ترحيله محفوظ، راجع الملخص ثم أعد المحاولة.` : '')
						+ (errors.length ? '<hr><div style="text-align:right;font-size:12px">' + [...new Set(errors)].slice(0, 30).map(esc).join('<br>') + '</div>' : '')
				});
				loadSummary(); loadBackfill(); loadReturns(); loadOpening();
			};
			const WORKERS = 4;
			const count = () => total.orders.done + total.returns.done + total.opening.done;
			const progress = () => Swal.update({html: `تم ترحيل ${count()} قيد، لا تغلق الصفحة.`});
			// عامل واحد: يرسل طلبًا، وعند وصول رده يرسل التالي فورًا، حتى لا يبقى شيء في نصيبه
			const worker = (phase, shards, shard, stopOnNoDone) => new Promise((resolve) => {
				const skip = []; let rounds = 0, retries = 0;
				const next = function () {
					rounds++;
					$.post(url, {CONTROL: 'legacyRunAll', phase: phase, shards: shards, shard: shard, skip: skip}, function (res) {
						if (!(Array.isArray(res) && res[0] === 'success')) { errors.push((res && res[2]) || 'رد غير متوقع'); return resolve(); }
						const d = res[3]; retries = 0;
						let doneNow = 0;
						['orders', 'returns', 'opening'].forEach(k => { total[k].done += d[k].done; total[k].failed += d[k].failed; doneNow += d[k].done; });
						(d.errors || []).forEach(e => errors.push(e));
						(d.failed_keys || []).forEach(k => skip.push(k));
						progress();
						if (d.processed === 0 || rounds > 3000 || (stopOnNoDone && doneNow === 0)) { return resolve(); }
						next();
					}, 'json').fail(function (xhr) {
						if (++retries <= 2) { return setTimeout(next, 1500); }
						errors.push('انقطع الاتصال (' + (xhr.status || 'بلا رد') + ')'); resolve();
					});
				};
				next();
			});
			const phaseRun = (phase) => Promise.all(Array.from({length: WORKERS}, (_, i) => worker(phase, WORKERS, i, false)));
			Swal.fire({title: 'جارٍ الترحيل...', html: 'تم ترحيل 0 قيد، لا تغلق الصفحة.', allowOutsideClick: false, didOpen: () => Swal.showLoading()});
			// 1) الأوامر والمرتجعات بالتوازي، 2) ثم الأرصدة (تُحسب بعد اكتمال الأولى)، 3) مرور أخير بعامل واحد لما تعطّل بسبب تزاحم
			phaseRun('orders')
				.then(() => phaseRun('opening'))
				.then(() => worker('all', 1, 0, true))
				.then(() => finish(null));
		});
	});

	function loadBackfill() {
		$.post(url, {CONTROL: 'listBackfillOrders'}, function (res) {
			if (Array.isArray(res) && res[0] === 'success') {
				bfTable.clear().rows.add(res[3] || []).draw(); bfTableRefresh();
				bfCount = (res[3] || []).length;
				updateAlert();
			}
		}, 'json');
	}

	$('#courier-backfill-table').on('click', '.btnBackfill', function () {
		const oid = $(this).data('oid');
		Swal.fire({
			icon: 'question',
			title: 'ترحيل الأمر للأستاذ؟',
			text: 'سيُسجَّل قيد التسليم وقيد التحصيل والعمولات بأرقام النظام القديم.',
			showCancelButton: true,
			confirmButtonText: 'ترحيل',
			cancelButtonText: 'إلغاء'
		}).then((r) => {
			if (!r.isConfirmed) return;
			$.post(url, {CONTROL: 'backfillCourierOrder', order_id: oid}, function (res) {
				const ok = Array.isArray(res) && res[0] === 'success';
				Swal.fire({icon: ok ? 'success' : 'error', title: ok ? 'تم' : 'خطأ', text: (res && res[2]) || ''});
				if (ok) loadBackfill();
			}, 'json').fail(() => Swal.fire({icon: 'error', title: 'خطأ', text: 'تعذر الاتصال بالخادم'}));
		});
	});

	loadBackfill();

	// ---- مطابقة أرصدة النظام القديم ----
	const kindNames = {CLIENT: 'عميل', DELIVERY: 'مندوب', MARKETER: 'مسوق', SUPPLIER: 'مورد', INVENTORY: 'مخزن'};
	const obCols = [
		{data: 'kind', render: (d, t) => txt(kindNames[d] || d, t), select: true},
		{data: 'name', render: txt},
		{data: 'legacy', render: txt},
		{data: 'gl', render: txt},
		{data: 'diff', render: txt},
		{
			data: null, className: 'text-center text-nowrap', searchable: false, orderable: false,
			render: (d, t, r) => `<button type="button" class="btn btn-sm btn-primary btnOpening" data-kind="${esc(r.kind)}" data-id="${esc(r.id)}">
				<i class="fa fa-book"></i> ترحيل</button>`
		}
	];
	const obTable = $('#opening-balance-table').DataTable({
		data: [],
		ordering: true,
		orderCellsTop: true,
		order: [],
		pageLength: 25,
		language: dtLang('الأستاذ مطابق للنظام القديم'),
		columns: obCols
	});
	const obTableRefresh = addColumnFilters(obTable, selectIdx(obCols));

	function loadOpening() {
		$.post(url, {CONTROL: 'listOpeningBalances'}, function (res) {
			if (Array.isArray(res) && res[0] === 'success') {
				obTable.clear().rows.add(res[3] || []).draw(); obTableRefresh();
				obCount = (res[3] || []).length;
				updateAlert();
			}
		}, 'json');
	}

	function postOne(kind, id) {
		return new Promise((resolve) => {
			$.post(url, {CONTROL: 'postOpeningBalance', kind: kind, id: id}, function (res) {
				resolve({ok: Array.isArray(res) && res[0] === 'success', msg: (res && res[2]) || ''});
			}, 'json').fail(() => resolve({ok: false, msg: 'تعذر الاتصال بالخادم'}));
		});
	}

	$('#opening-balance-table').on('click', '.btnOpening', async function () {
		const r = await postOne($(this).data('kind'), $(this).data('id'));
		Swal.fire({icon: r.ok ? 'success' : 'error', title: r.ok ? 'تم' : 'خطأ', text: r.msg});
		if (r.ok) loadOpening();
	});

	$('#btnPostAllOpening').on('click', function () {
		const rows = obTable.rows().data().toArray();
		if (!rows.length) return;
		Swal.fire({
			icon: 'warning', title: 'ترحيل كل التسويات؟',
			text: `سيُرحَّل ${rows.length} قيد تسوية مقابل حساب الأرصدة الافتتاحية. راجع الأرقام أولاً.`,
			showCancelButton: true, confirmButtonText: 'ترحيل الكل', cancelButtonText: 'إلغاء'
		}).then(async (r) => {
			if (!r.isConfirmed) return;
			let done = 0, failed = 0, lastMsg = '';
			for (const row of rows) {
				const res = await postOne(row.kind, row.id);
				if (res.ok) done++; else { failed++; lastMsg = res.msg; }
			}
			Swal.fire({icon: failed ? 'warning' : 'success', title: 'اكتمل', text: `تم ${done}، وفشل ${failed}${lastMsg ? ' - ' + lastMsg : ''}`});
			loadOpening();
		});
	});

	$('#btnBackfillAll').on('click', function () {
		const rows = bfTable.rows().data().toArray();
		if (!rows.length) return;
		Swal.fire({
			icon: 'warning', title: 'ترحيل كل الأوامر المُقفلة؟',
			text: `سيُرحَّل ${rows.length} أمر بقيد تسليم وقيد تحصيل لكل أمر.`,
			showCancelButton: true, confirmButtonText: 'ترحيل الكل', cancelButtonText: 'إلغاء'
		}).then(async (r) => {
			if (!r.isConfirmed) return;
			let done = 0, failed = 0, lastMsg = '';
			for (const row of rows) {
				const res = await new Promise((resolve) => {
					$.post(url, {CONTROL: 'backfillCourierOrder', order_id: row.ORDER_ID}, function (rs) {
						resolve({ok: Array.isArray(rs) && rs[0] === 'success', msg: (rs && rs[2]) || ''});
					}, 'json').fail(() => resolve({ok: false, msg: 'تعذر الاتصال بالخادم'}));
				});
				if (res.ok) done++; else { failed++; lastMsg = (row.SUB_ORDER_ID || row.ORDER_ID) + ': ' + res.msg; }
			}
			Swal.fire({icon: failed ? 'warning' : 'success', title: 'اكتمل', text: `تم ${done}، وفشل ${failed}${lastMsg ? ' - ' + lastMsg : ''}`});
			loadBackfill(); loadOpening();
		});
	});

	loadOpening();

	// ---- مرتجعات لم تُرحَّل ----
	const urTableCols = orderCols().concat([{data: 'RETURN_VALUE', render: txt}, {data: 'POSTED_VALUE', render: txt}]).concat([actionCol({btn: 'btn-primary', name: 'btnReturnEntry'}, 'fa-book', 'ترحيل')]);
	const urTable = $('#unposted-returns-table').DataTable({
		data: [],
		ordering: true,
		orderCellsTop: true,
		order: [[LAST_UPDATE_COL, 'desc']],
		pageLength: 25,
		language: dtLang('لا توجد مرتجعات غير مُرحَّلة'),
		columns: urTableCols
	});
	const urTableRefresh = addColumnFilters(urTable, selectIdx(urTableCols));

	function loadReturns() {
		$.post(url, {CONTROL: 'listUnpostedReturns'}, function (res) {
			if (Array.isArray(res) && res[0] === 'success') {
				urTable.clear().rows.add(res[3] || []).draw(); urTableRefresh();
				urCount = (res[3] || []).length;
				updateAlert();
			}
		}, 'json');
	}

	function postReturn(oid) {
		return new Promise((resolve) => {
			$.post(url, {CONTROL: 'postReturnEntry', order_id: oid}, function (res) {
				resolve({ok: Array.isArray(res) && res[0] === 'success', msg: (res && res[2]) || ''});
			}, 'json').fail(() => resolve({ok: false, msg: 'تعذر الاتصال بالخادم'}));
		});
	}

	$('#unposted-returns-table').on('click', '.btnReturnEntry', async function () {
		const r = await postReturn($(this).data('oid'));
		Swal.fire({icon: r.ok ? 'success' : 'error', title: r.ok ? 'تم' : 'خطأ', text: r.msg});
		if (r.ok) loadReturns();
	});

	$('#btnPostAllReturns').on('click', function () {
		const rows = urTable.rows().data().toArray();
		if (!rows.length) return;
		Swal.fire({
			icon: 'warning', title: 'ترحيل كل المرتجعات؟',
			text: `سيُرحَّل قيد مرتجع لـ ${rows.length} أمر.`,
			showCancelButton: true, confirmButtonText: 'ترحيل الكل', cancelButtonText: 'إلغاء'
		}).then(async (r) => {
			if (!r.isConfirmed) return;
			let done = 0, failed = 0, lastMsg = '';
			for (const row of rows) {
				const res = await postReturn(row.ORDER_ID);
				if (res.ok) done++; else { failed++; lastMsg = (row.SUB_ORDER_ID || row.ORDER_ID) + ': ' + res.msg; }
			}
			Swal.fire({icon: failed ? 'warning' : 'success', title: 'اكتمل', text: `تم ${done}، وفشل ${failed}${lastMsg ? ' - ' + lastMsg : ''}`});
			loadReturns(); loadOpening();
		});
	});

	loadReturns();
	loadSummary();
})();
