(function () {
	const url = `../../inc/controller/shipment.control.php`;
	const statusNames = {3: 'تم التسليم', 4: 'تسليم جزئي', 5: 'مرفوض'};
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

	function loadBackfill() {
		$.post(url, {CONTROL: 'listBackfillOrders'}, function (res) {
			if (Array.isArray(res) && res[0] === 'success') {
				bfTable.clear().rows.add(res[3] || []).draw();
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
})();
