(function () {
	if (!window.SC_CAN_ADMIN) { return; }
	const url = `../../inc/controller/shippingCompanies.control.php`;
	const esc = (v) => $('<div>').text(v == null ? '' : v).html();
	const call = (ctl, data) => new Promise((resolve, reject) => {
		$.post(url, Object.assign({CONTROL: ctl}, data || {}), function (r) {
			if (Array.isArray(r) && r[0] === 'success') { resolve(r[3]); } else { reject(new Error((r && r[2]) || 'خطأ')); }
		}, 'json').fail(x => reject(new Error('تعذر الاتصال بالخادم (' + (x.status || 'بلا رد') + ')')));
	});
	const fail = (e) => Swal.fire({icon: 'error', title: 'خطأ', text: e.message});
	const ACTIONS = {FULL: 'تسليم كامل', PARTIAL: 'تسليم جزئي', RETURN: 'مرتجع', RETURN_SHIPPING_PAID: 'مرتجع ودفع الشحن', IGNORE: 'لا تغيير'};
	const DERIVED = {FULL: 'مبلغ كامل', RETURN_SHIPPING_PAID: 'مبلغ = الشحن', NO_COLLECTION: 'بلا تحصيل', PARTIAL: 'جزئي', PARTIAL_NO_MATCH: 'جزئي (لا توزيع)', OVER: 'أكبر من القيمة'};
	const FLAG = {OK: ['badge-success', 'سليم'], REVIEW: ['badge-warning', 'مراجعة'], ERROR: ['badge-danger', 'خطأ']};

	let sheets = [];      // [{name, rows:[[..]]}]
	let grid = [];        // الصفوف الخام للورقة المختارة
	let statusMap = {};   // نص الحالة -> إجراء (تعديل المستخدم)
	let lastRes = null;

	// ---------- قراءة الملفات ----------
	const colIdx = (ref) => { let n = 0; const m = /^([A-Z]+)/.exec(ref || ''); if (!m) { return 0; } for (const ch of m[1]) { n = n * 26 + ch.charCodeAt(0) - 64; } return n - 1; };
	const xmlDoc = (t) => new DOMParser().parseFromString(t, 'application/xml');

	async function readXlsx(buf) {
		if (!window.JSZip) { throw new Error('مكتبة قراءة xlsx غير محمّلة. حدّث الصفحة (Ctrl+F5).'); }
		const zip = await JSZip.loadAsync(buf);
		const text = async (p) => { const f = zip.file(p); return f ? await f.async('string') : null; };
		const shared = [];
		const sst = await text('xl/sharedStrings.xml');
		if (sst) { xmlDoc(sst).querySelectorAll('si').forEach(si => { shared.push(Array.from(si.getElementsByTagName('t')).map(t => t.textContent).join('')); }); }
		const wb = xmlDoc(await text('xl/workbook.xml'));
		const rels = xmlDoc(await text('xl/_rels/workbook.xml.rels'));
		const relMap = {};
		rels.querySelectorAll('Relationship').forEach(r => { relMap[r.getAttribute('Id')] = r.getAttribute('Target'); });
		const out = [];
		for (const sh of Array.from(wb.getElementsByTagName('sheet'))) {
			const rid = sh.getAttribute('r:id') || sh.getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
			let target = relMap[rid] || '';
			target = target.replace(/^\/?(xl\/)?/, '');
			const xml = await text('xl/' + target);
			if (!xml) { continue; }
			const rows = [];
			xmlDoc(xml).querySelectorAll('sheetData > row').forEach(r => {
				const line = []; let last = -1;
				r.querySelectorAll('c').forEach(c => {
					let i = c.getAttribute('r') ? colIdx(c.getAttribute('r')) : last + 1; last = i;
					const t = c.getAttribute('t'); const v = c.querySelector('v');
					let val = '';
					if (t === 's') { val = v ? (shared[parseInt(v.textContent, 10)] || '') : ''; }
					else if (t === 'inlineStr') { val = Array.from(c.getElementsByTagName('t')).map(x => x.textContent).join(''); }
					else { val = v ? v.textContent : ''; }
					line[i] = val;
				});
				const rn = parseInt(r.getAttribute('r'), 10);
				if (rn) { while (rows.length < rn - 1) { rows.push([]); } rows[rn - 1] = line; } else { rows.push(line); }
			});
			out.push({name: sh.getAttribute('name'), rows});
		}
		return out;
	}

	function loadScript(src) { return new Promise((res, rej) => { const s = document.createElement('script'); s.src = src; s.onload = res; s.onerror = () => rej(new Error('تعذر تحميل مكتبة قراءة xls. احفظ الملف بصيغة xlsx أو csv وأعد الرفع.')); document.head.appendChild(s); }); }
	async function readXls(buf) {
		if (!window.XLSX) { await loadScript('https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js'); }
		const wb = XLSX.read(buf, {type: 'array'});
		return wb.SheetNames.map(n => ({name: n, rows: XLSX.utils.sheet_to_json(wb.Sheets[n], {header: 1, raw: false, defval: ''})}));
	}

	function parseCsv(text) {
		const first = text.split(/\r?\n/, 1)[0] || '';
		const delim = [',', ';', '\t'].map(d => [d, first.split(d).length]).sort((a, b) => b[1] - a[1])[0][0];
		const rows = []; let row = [], cur = '', q = false;
		for (let i = 0; i < text.length; i++) {
			const ch = text[i];
			if (q) { if (ch === '"') { if (text[i + 1] === '"') { cur += '"'; i++; } else { q = false; } } else { cur += ch; } }
			else if (ch === '"') { q = true; }
			else if (ch === delim) { row.push(cur); cur = ''; }
			else if (ch === '\n' || ch === '\r') { if (ch === '\r' && text[i + 1] === '\n') { i++; } row.push(cur); rows.push(row); row = []; cur = ''; }
			else { cur += ch; }
		}
		if (cur !== '' || row.length) { row.push(cur); rows.push(row); }
		return [{name: 'CSV', rows}];
	}
	function readCsv(buf) {
		let t = new TextDecoder('utf-8').decode(buf).replace(/^﻿/, '');
		if (t.indexOf('�') >= 0) { try { t = new TextDecoder('windows-1256').decode(buf); } catch (e) { /* يبقى كما هو */ } }
		return parseCsv(t);
	}

	// ---------- الواجهة ----------
	function guessHeaderRow(rows) {
		for (let i = 0; i < Math.min(rows.length, 15); i++) {
			if ((rows[i] || []).filter(x => String(x || '').trim() !== '').length >= 3) { return i + 1; }
		}
		return 1;
	}
	function fillSelect($s, headers, pattern, optional) {
		$s.empty(); if (optional) { $s.append('<option value="">— بدون —</option>'); }
		let pick = '';
		headers.forEach((h, i) => { $s.append('<option value="' + i + '">' + esc((i + 1) + ' - ' + (h || '(بلا عنوان)')) + '</option>'); if (pick === '' && pattern && pattern.test(h)) { pick = String(i); } });
		if (pick !== '') { $s.val(pick); } else if (!optional) { $s.val('0'); }
	}
	function headerCells() { const r = grid[parseInt($('#imp-hrow').val(), 10) - 1] || []; const n = Math.max(r.length, ...grid.slice(0, 30).map(x => (x || []).length)); return Array.from({length: n}, (_, i) => String(r[i] == null ? '' : r[i]).trim()); }
	function buildMapping(guess) {
		const h = headerCells();
		if (guess) {
			fillSelect($('#imp-c-order'), h, /sub_?order|parcode|barcode|كود|رقم\s*(ال)?(امر|أمر|شحنة)|الشحنة/i, false);
			fillSelect($('#imp-c-amount'), h, /المبلغ\s*المحصل|المدفوع|المحصل|collected|cod/i, true);
			fillSelect($('#imp-c-status'), h, /حالة|الموقف|status/i, true);
			fillSelect($('#imp-c-fee'), h, /^الشحن$|رسوم|fee/i, true);
		}
		const start = parseInt($('#imp-hrow').val(), 10);
		let html = '<thead class="thead-light"><tr>' + h.map((x, i) => '<th>' + esc(x || (i + 1)) + '</th>').join('') + '</tr></thead><tbody>';
		grid.slice(start, start + 3).forEach(r => { html += '<tr>' + h.map((x, i) => '<td>' + esc(r && r[i] != null ? r[i] : '') + '</td>').join('') + '</tr>'; });
		$('#imp-sample').html(html + '</tbody>');
	}
	function pickSheet(i) {
		grid = sheets[i].rows; $('#imp-hrow').val(guessHeaderRow(grid)); buildMapping(true);
		$('#imp-map').show(); $('#imp-result').hide();
	}

	function collectRows() {
		const start = parseInt($('#imp-hrow').val(), 10);
		const g = (v) => v === '' ? -1 : parseInt(v, 10);
		const co = g($('#imp-c-order').val()), ca = g($('#imp-c-amount').val()), cs = g($('#imp-c-status').val()), cf = g($('#imp-c-fee').val());
		const out = [];
		grid.forEach((r, i) => {
			if (i < start || !r) { return; }
			const o = String(r[co] == null ? '' : r[co]).trim();
			if (o === '' || !/\d/.test(o)) { return; }
			out.push({row: i + 1, order: o, amount: ca >= 0 ? r[ca] : null, status: cs >= 0 ? r[cs] : '', fee: cf >= 0 ? r[cf] : null});
		});
		return out;
	}

	function run() {
		const company = $('#imp-company').val();
		if (!company) { return fail(new Error('اختر شركة الشحن.')); }
		const rows = collectRows();
		if (!rows.length) { return fail(new Error('لا توجد صفوف بأرقام أوامر. راجع صف العناوين وعمود رقم الأمر.')); }
		const $b = $('#imp-run').prop('disabled', true);
		call('scPreview', {company_id: company, rows: JSON.stringify(rows), status_map: JSON.stringify(statusMap),
			includes_shipping: $('#imp-incl').is(':checked') ? 1 : 0, tolerance: $('#imp-tol').val()})
			.then(r => { lastRes = r; renderResult(); }).catch(fail).finally(() => $b.prop('disabled', false));
	}

	function renderResult() {
		const r = lastRes, s = r.summary;
		$('#imp-result').show();
		$('#imp-summary').html('<span class="badge badge-secondary p-2 ml-1">الإجمالي ' + s.total + '</span>' +
			'<span class="badge badge-success p-2 ml-1">سليم ' + s.OK + '</span>' +
			'<span class="badge badge-warning p-2 ml-1">يحتاج مراجعة ' + s.REVIEW + '</span>' +
			'<span class="badge badge-danger p-2 ml-1">خطأ ' + s.ERROR + '</span>');
		const keys = Object.keys(r.statuses || {});
		if (keys.length) {
			let h = '<div class="small font-weight-bold mb-1">فسّر كل حالة مكتوبة في الكشف (يمكنك التعديل ثم إعادة المعاينة):</div><div class="form-row">';
			keys.forEach((k, i) => {
				const cur = statusMap[k] || r.statuses[k].action;
				h += '<div class="col-md-4 mb-1"><div class="input-group input-group-sm"><div class="input-group-prepend"><span class="input-group-text">' + esc(k) + ' (' + r.statuses[k].count + ')</span></div>' +
					'<select class="form-control imp-st" data-k="' + esc(k) + '">' + Object.keys(ACTIONS).map(a => '<option value="' + a + '"' + (a === cur ? ' selected' : '') + '>' + ACTIONS[a] + '</option>').join('') + '</select></div></div>';
			});
			$('#imp-statuses').html(h + '</div>');
		} else { $('#imp-statuses').empty(); }
		renderTable();
		const m = r.not_in_file;
		$('#imp-missing').text(m.count ? 'أوامر على عهدة الشركة (بانتظار النتيجة) ولم تظهر في الكشف: ' + m.count + (m.sample.length ? ' — مثال: ' + m.sample.slice(0, 10).join('، ') : '') : 'كل أوامر عهدة الشركة الجارية ظهرت في الكشف.');
	}
	const money = (v) => v == null ? '—' : Number(v).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
	function renderTable() {
		const f = $('#imp-filter .active').data('f');
		let h = '<thead class="thead-dark"><tr><th>صف</th><th>رقم الأمر</th><th>حالة الكشف</th><th>المحصل</th><th>قيمة النظام</th><th>المتوقع</th><th>الاستنتاج</th><th>الإجراء المقترح</th><th>الحكم</th><th>ملاحظات</th></tr></thead><tbody>';
		lastRes.rows.filter(x => f === 'ALL' || x.flag === f).forEach(x => {
			const b = FLAG[x.flag];
			const notes = x.notes.concat(x.candidates.length ? ['التوزيع: ' + x.candidates.join(' | ')] : []);
			h += '<tr><td>' + x.row + '</td><td class="text-nowrap">' + esc(x.order) + '</td><td>' + esc(x.file_status) + '</td><td>' + money(x.amount) + '</td><td>' + money(x.order_value) + '</td><td>' + money(x.expected) + '</td>' +
				'<td>' + esc(DERIVED[x.derived] || '—') + '</td><td>' + esc(ACTIONS[x.proposed] || '—') + '</td><td><span class="badge ' + b[0] + '">' + b[1] + '</span></td><td class="small">' + esc(notes.join(' — ')) + '</td></tr>';
		});
		$('#imp-table').html(h + '</tbody>');
	}

	async function onFile(file) {
		if (!file) { return; }
		try {
			const buf = await file.arrayBuffer();
			const ext = (file.name.split('.').pop() || '').toLowerCase();
			sheets = ext === 'xlsx' ? await readXlsx(buf) : (ext === 'xls' ? await readXls(buf) : (ext === 'csv' ? readCsv(buf) : null));
			if (!sheets) { throw new Error('نوع الملف غير مدعوم. استخدم xlsx أو xls أو csv.'); }
			sheets = sheets.filter(s => s.rows.some(r => r && r.some(c => String(c || '').trim() !== '')));
			if (!sheets.length) { throw new Error('الملف لا يحتوي على بيانات.'); }
			statusMap = {};
			$('#imp-sheet').html(sheets.map((s, i) => '<option value="' + i + '">' + esc(s.name) + ' (' + s.rows.length + ')</option>').join(''));
			$('#imp-sheet-box').toggle(sheets.length > 1);
			// أكبر ورقة افتراضيًا
			let best = 0; sheets.forEach((s, i) => { if (s.rows.length > sheets[best].rows.length) { best = i; } });
			$('#imp-sheet').val(best); pickSheet(best);
		} catch (e) { fail(e); }
	}

	$(function () {
		if (!$('#scImportPage').length) { return; }
		$('#scImportPage').show();
		call('scList').then(list => {
			$('#imp-company').html('<option value="">— اختر —</option>' + list.filter(c => Number(c.is_active) === 1).map(c => '<option value="' + c.id + '">' + esc(c.name) + '</option>').join(''));
		}).catch(fail);
		$('#imp-file').on('change', function () { onFile(this.files[0]); });
		$('#imp-sheet').on('change', function () { pickSheet(parseInt(this.value, 10)); });
		$('#imp-hrow').on('change', () => buildMapping(true));
		$('#imp-run').on('click', run);
		$('#imp-filter').on('click', 'button', function () { $('#imp-filter button').removeClass('active'); $(this).addClass('active'); if (lastRes) { renderTable(); } });
		$('#imp-statuses').on('change', '.imp-st', function () { statusMap[$(this).data('k')] = this.value; run(); });
	});
})();
