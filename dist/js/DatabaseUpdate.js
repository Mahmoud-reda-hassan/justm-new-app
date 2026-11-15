(function () {
	const url = `../../inc/controller/shipment.control.php`;
	const esc = (v) => $('<div>').text(v == null ? '' : v).html();
	const stateText = {pending: ['لم يُنفَّذ', 'badge-warning'], applied: ['مُنفَّذ', 'badge-success'], changed: ['الملف تغيّر بعد التنفيذ', 'badge-info']};

	function render(d) {
		$('#db-update-table tbody').html(d.files.map(f => {
			const s = stateText[f.state] || [f.state, 'badge-secondary'];
			return `<tr><td dir="ltr" class="text-left">${esc(f.name)}</td><td>${esc(f.statements)}</td>
				<td><span class="badge ${s[1]}">${esc(s[0])}</span></td><td>${esc(f.applied_at || '-')}</td></tr>`;
		}).join(''));
		const allOk = d.checks.every(c => c.ok), needs = d.files.some(f => f.state !== 'applied') || !allOk;
		$('#db-checks').html(d.checks.map(c => `<li>${c.ok ? '<span class="text-success">&#10003;</span>' : '<span class="text-danger">&#10007;</span>'} ${esc(c.label)}</li>`).join(''));
		$('#btnDbUpdate').prop('disabled', !needs);
	}

	function load() {
		$.post(url, {CONTROL: 'dbUpdateStatus'}, function (res) {
			if (Array.isArray(res) && res[0] === 'success') { render(res[3]); }
			else { $('#db-checks').text((res && res[2]) || 'تعذر الفحص'); }
		}, 'json').fail(() => $('#db-checks').text('تعذر الاتصال بالسيرفر'));
	}

	$('#btnDbRecheck').on('click', load);
	$('#btnDbUpdate').on('click', function () {
		Swal.fire({
			icon: 'warning', title: 'تحديث قاعدة البيانات؟',
			text: 'هل أخذت نسخة احتياطية؟ التحديث لا يحذف بيانات ويمكن إعادته بأمان.',
			showCancelButton: true, confirmButtonText: 'نعم، حدّث', cancelButtonText: 'إلغاء'
		}).then(async (r) => {
			if (!r.isConfirmed) return;
			const files = $('#db-update-table tbody td[dir=ltr]').map((i, el) => $(el).text()).get();
			Swal.fire({title: 'جارٍ التحديث...', allowOutsideClick: false, didOpen: () => Swal.showLoading()});
			let lastChecks = null, msgs = [], failed = false;
			for (const f of files) {
				const res = await new Promise(resolve => $.post(url, {CONTROL: 'dbUpdateRun', file: f}, resolve, 'json').fail(xhr => resolve(['error', 'خطأ', 'انقطع الاتصال (' + (xhr.status || 'بلا رد') + ')، راجع الفحص ثم أعد المحاولة', []])));
				if (Array.isArray(res) && res[0] === 'success') {
					msgs.push(`${f}: نُفِّذ ${res[3].executed}، تُخطّي ${res[3].skipped} من ${res[3].total}`); lastChecks = res[3].checks;
				} else { msgs.push(`${f}: ${(res && res[2]) || 'فشل'}`); failed = true; break; }
			}
			Swal.fire({icon: failed ? 'error' : 'success', title: failed ? 'فشل التحديث' : 'تم التحديث', html: msgs.map(esc).join('<br>')});
			load();
		});
	});

	load();
})();
