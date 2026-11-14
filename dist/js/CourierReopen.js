(function () {
	const url = `../../inc/controller/shipment.control.php`;
	const statusNames = {3: 'تم التسليم', 4: 'تسليم جزئي', 5: 'مرفوض', 6: 'مرتجع', 7: 'مرتجع جزئي'};
	const esc = (v) => $('<div>').text(v == null ? '' : v).html();

	const table = $('#courier-reopen-table').DataTable({
		data: [],
		ordering: false,
		language: {emptyTable: 'لا توجد أوامر قابلة للرد للمخزن'},
		columns: [
			{data: 'SUB_ORDER_ID', render: (d, t, r) => esc(d || r.ORDER_ID)},
			{data: 'DELIVERY_NAME', render: esc},
			{data: 'ORDER_STATUS', render: (d) => esc(statusNames[d] || d)},
			{data: 'ADDRESS', render: esc},
			{data: 'TELEPHONE', render: esc},
			{data: 'DELIVERED_DATE', render: esc},
			{
				data: null, className: 'text-center text-nowrap',
				render: (d, t, r) => `<button type="button" class="btn btn-sm btn-warning btnReopen" data-oid="${esc(r.ORDER_ID)}">
					<i class="fa fa-undo"></i> رد للمخزن</button>`
			}
		]
	});

	function load() {
		$.post(url, {CONTROL: 'listCourierReopen'}, function (res) {
			if (Array.isArray(res) && res[0] === 'success') {
				table.clear().rows.add(res[3] || []).draw();
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
	const bfTable = $('#courier-backfill-table').DataTable({
		data: [],
		ordering: false,
		language: {emptyTable: 'لا توجد أوامر مُقفلة بلا قيد'},
		columns: [
			{data: 'SUB_ORDER_ID', render: (d, t, r) => esc(d || r.ORDER_ID)},
			{data: 'DELIVERY_NAME', render: esc},
			{data: 'ORDER_STATUS', render: (d) => esc(statusNames[d] || d)},
			{data: 'SHIPPING_VAL', render: esc},
			{data: 'LAST_UPDATE', render: esc},
			{
				data: null, className: 'text-center text-nowrap',
				render: (d, t, r) => `<button type="button" class="btn btn-sm btn-primary btnBackfill" data-oid="${esc(r.ORDER_ID)}">
					<i class="fa fa-book"></i> ترحيل للأستاذ</button>`
			}
		]
	});

	let bfCount = 0, obCount = 0, urCount = 0;
	function updateAlert() {
		const el = $('#legacy-alert');
		if (bfCount + obCount + urCount > 0) {
			el.removeClass('d-none').text(`يوجد ${bfCount} أمر مُقفل و${urCount} مرتجع و${obCount} رصيد من النظام القديم لم تُرحَّل للأستاذ بعد.`);
		} else {
			el.addClass('d-none').text('');
		}
	}

	function loadBackfill() {
		$.post(url, {CONTROL: 'listBackfillOrders'}, function (res) {
			if (Array.isArray(res) && res[0] === 'success') {
				bfTable.clear().rows.add(res[3] || []).draw();
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
	const obTable = $('#opening-balance-table').DataTable({
		data: [],
		ordering: false,
		language: {emptyTable: 'الأستاذ مطابق للنظام القديم'},
		columns: [
			{data: 'kind', render: (d) => esc(kindNames[d] || d)},
			{data: 'name', render: esc},
			{data: 'legacy', render: esc},
			{data: 'gl', render: esc},
			{data: 'diff', render: esc},
			{
				data: null, className: 'text-center text-nowrap',
				render: (d, t, r) => `<button type="button" class="btn btn-sm btn-primary btnOpening" data-kind="${esc(r.kind)}" data-id="${esc(r.id)}">
					<i class="fa fa-book"></i> ترحيل</button>`
			}
		]
	});

	function loadOpening() {
		$.post(url, {CONTROL: 'listOpeningBalances'}, function (res) {
			if (Array.isArray(res) && res[0] === 'success') {
				obTable.clear().rows.add(res[3] || []).draw();
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
	const urTable = $('#unposted-returns-table').DataTable({
		data: [],
		ordering: false,
		language: {emptyTable: 'لا توجد مرتجعات غير مُرحَّلة'},
		columns: [
			{data: 'SUB_ORDER_ID', render: (d, t, r) => esc(d || r.ORDER_ID)},
			{data: 'ORDER_STATUS', render: (d) => esc(statusNames[d] || d)},
			{data: 'RETURN_VALUE', render: esc},
			{data: 'POSTED_VALUE', render: esc},
			{
				data: null, className: 'text-center text-nowrap',
				render: (d, t, r) => `<button type="button" class="btn btn-sm btn-primary btnReturnEntry" data-oid="${esc(r.ORDER_ID)}">
					<i class="fa fa-book"></i> ترحيل</button>`
			}
		]
	});

	function loadReturns() {
		$.post(url, {CONTROL: 'listUnpostedReturns'}, function (res) {
			if (Array.isArray(res) && res[0] === 'success') {
				urTable.clear().rows.add(res[3] || []).draw();
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
})();
