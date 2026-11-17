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

	// ---------- مشترك ----------
	async function readFile(file) {
		const buf = await file.arrayBuffer();
		const ext = (file.name.split('.').pop() || '').toLowerCase();
		let sh = ext === 'xlsx' ? await readXlsx(buf) : (ext === 'xls' ? await readXls(buf) : (ext === 'csv' ? readCsv(buf) : null));
		if (!sh) { throw new Error('نوع الملف غير مدعوم. استخدم xlsx أو xls أو csv.'); }
		sh = sh.filter(x => x.rows.some(r => r && r.some(c => String(c || '').trim() !== '')));
		if (!sh.length) { throw new Error('الملف لا يحتوي على بيانات.'); }
		return sh;
	}
	const largest = (sh) => { let b = 0; sh.forEach((x, i) => { if (x.rows.length > sh[b].rows.length) { b = i; } }); return b; };
	const guessHeaderRow = (rows) => { for (let i = 0; i < Math.min(rows.length, 15); i++) { if ((rows[i] || []).filter(x => String(x || '').trim() !== '').length >= 3) { return i + 1; } } return 1; };
	function headersOf(grid, hrow) {
		const r = grid[hrow - 1] || [];
		const n = Math.max(r.length, ...grid.slice(0, 30).map(x => (x || []).length));
		return Array.from({length: n}, (_, i) => String(r[i] == null ? '' : r[i]).trim());
	}
	function findCol(ref, headers) {
		if (!ref) { return ''; }
		if (ref.name) { const i = headers.findIndex(h => h === ref.name); if (i >= 0) { return String(i); } return ''; }
		return ref.index < headers.length ? String(ref.index) : '';
	}
	const money = (v) => v == null ? '—' : Number(v).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
	function defaultAction(t) {
		t = String(t || '').trim().replace(/[ً-ْ]/g, '');
		if (!t) { return 'IGNORE'; }
		if (/دفع\s*الشحن|دفع\s*شحن/.test(t) && !/لم\s*يدفع|ولم\s*يدفع|لا\s*يدفع/.test(t)) { return 'RETURN_SHIPPING_PAID'; }
		if ((/جزئ/.test(t) && !/مرتجع\s*جزئ/.test(t)) || /تم\s*التسليم\s*و/.test(t)) { return 'PARTIAL'; }
		if (/استبدال/.test(t)) { return 'IGNORE'; }
		if (/مرتجع|فشل|رفض|لاغي|الغاء|إلغاء|مرفوض/.test(t)) { return 'RETURN'; }
		if (/استلم|تم\s*التسليم|تسليم|سلم/.test(t) && !/مكرر|قبل/.test(t)) { return 'FULL'; }
		return 'IGNORE';
	}
	function fillSelect($s, headers, optional) {
		$s.empty(); if (optional) { $s.append('<option value="">— بدون —</option>'); }
		headers.forEach((h, i) => { $s.append('<option value="' + i + '">' + esc((i + 1) + ' - ' + (h || '(بلا عنوان)')) + '</option>'); });
	}
	function guessInto(sel, headers, re, optional) { const i = headers.findIndex(h => re.test(h)); $(sel).val(i >= 0 ? String(i) : (optional ? '' : '0')); }
	function actionSelect(k, cur, cls) { return '<select class="form-control ' + cls + '" data-k="' + esc(k) + '">' + Object.keys(ACTIONS).map(a => '<option value="' + a + '"' + (a === cur ? ' selected' : '') + '>' + ACTIONS[a] + '</option>').join('') + '</select>'; }

	// ---------- إعدادات الشركة (داخل نافذة تعديل الشركة) ----------
	const cs = {sheets: [], grid: [], saved: null, dirty: false, statusMap: {}};
	const csRef = ($s) => { const v = $s.val(); if (v === '' || v == null) { return null; } const i = parseInt(v, 10); return {name: headersOf(cs.grid, parseInt($('#cs-hrow').val(), 10) || 1)[i] || '', index: i}; };
	function csDescribe() {
		const st = cs.saved; const $d = $('#cs-current');
		if (!st) { $d.html('<span class="text-danger">لم تُضبط إعدادات ملف هذه الشركة بعد.</span>'); return; }
		const nm = (c) => c ? '«' + esc(c.name || ('عمود ' + (c.index + 1))) + '»' : '—';
		$d.html('<span class="text-success">الإعدادات المحفوظة:</span> الورقة ' + esc(st.sheet || 'الأولى') + ' — صف العناوين ' + st.header_row + ' — رقم الأمر ' + nm(st.order_col) + ' — المحصل ' + nm(st.amount_col) + ' — الحالة ' + nm(st.status_col) + ' — الرسوم ' + nm(st.fee_col) + ' — المبلغ ' + (Number(st.includes_shipping) ? 'شامل' : 'غير شامل') + ' الشحن — سماحية ' + st.tolerance);
	}
	function csRenderSample() {
		const h = headersOf(cs.grid, parseInt($('#cs-hrow').val(), 10) || 1), start = parseInt($('#cs-hrow').val(), 10) || 1;
		let html = '<thead class="thead-light"><tr>' + h.map((x, i) => '<th>' + esc(x || (i + 1)) + '</th>').join('') + '</tr></thead><tbody>';
		cs.grid.slice(start, start + 3).forEach(r => { html += '<tr>' + h.map((x, i) => '<td>' + esc(r && r[i] != null ? r[i] : '') + '</td>').join('') + '</tr>'; });
		$('#cs-sample').html(html + '</tbody>');
		csRenderStatuses();
	}
	function csRenderStatuses() {
		const c = $('#cs-c-status').val(); const start = parseInt($('#cs-hrow').val(), 10) || 1;
		if (c === '' || c == null) { $('#cs-statuses').empty(); return; }
		const counts = {};
		cs.grid.forEach((r, i) => { if (i < start || !r) { return; } const v = String(r[parseInt(c, 10)] == null ? '' : r[parseInt(c, 10)]).trim(); if (v) { counts[v] = (counts[v] || 0) + 1; } });
		const keys = Object.keys(counts).slice(0, 60);
		if (!keys.length) { $('#cs-statuses').empty(); return; }
		let h = '<div class="small font-weight-bold mb-1">تفسير الحالات الموجودة في الملف:</div><div class="form-row">';
		keys.forEach(k => { const cur = cs.statusMap[k] || defaultAction(k); cs.statusMap[k] = cur; h += '<div class="col-md-6 mb-1"><div class="input-group input-group-sm"><div class="input-group-prepend"><span class="input-group-text">' + esc(k) + ' (' + counts[k] + ')</span></div>' + actionSelect(k, cur, 'cs-st') + '</div></div>'; });
		$('#cs-statuses').html(h + '</div>');
	}
	function csHeaders(guess) {
		const h = headersOf(cs.grid, parseInt($('#cs-hrow').val(), 10) || 1);
		fillSelect($('#cs-c-order'), h, false); fillSelect($('#cs-c-amount'), h, false); fillSelect($('#cs-c-status'), h, true); fillSelect($('#cs-c-fee'), h, true);
		return h;
	}
	function csGuess() {
		const h = csHeaders();
		guessInto('#cs-c-order', h, /sub_?order|parcode|barcode|كود|رقم\s*(ال)?(امر|أمر|شحنة)|الشحنة/i, false);
		guessInto('#cs-c-amount', h, /المبلغ\s*المحصل|المدفوع|المحصل|collected|cod/i, false);
		guessInto('#cs-c-status', h, /حالة|الموقف|status/i, true);
		guessInto('#cs-c-fee', h, /^الشحن$|رسوم|fee/i, true);
	}
	function csApplySaved() {
		const st = cs.saved; $('#cs-hrow').val(st.header_row || 1);
		const h = csHeaders();
		const set = (sel, ref, opt) => { const v = findCol(ref, h); $(sel).val(v === '' ? (opt ? '' : '0') : v); return v !== ''; };
		const ok = set('#cs-c-order', st.order_col, false);
		set('#cs-c-amount', st.amount_col, false); set('#cs-c-status', st.status_col, true); set('#cs-c-fee', st.fee_col, true);
		$('#cs-incl').prop('checked', !!Number(st.includes_shipping)); $('#cs-tol').val(st.tolerance == null ? 1 : st.tolerance);
		cs.statusMap = Object.assign({}, st.status_map || {});
		return ok;
	}
	function csPick(i) {
		cs.grid = cs.sheets[i].rows;
		if (cs.saved && cs.saved.sheet === cs.sheets[i].name) { if (!csApplySaved()) { csGuess(); } } else { $('#cs-hrow').val(guessHeaderRow(cs.grid)); csGuess(); }
		$('#cs-map').show(); csRenderSample(); cs.dirty = true;
	}
	function csSettings() {
		if (!cs.dirty) { return null; }
		const order = csRef($('#cs-c-order')); if (!order) { throw new Error('حدد عمود رقم الأمر في إعدادات الملف.'); }
		if (!csRef($('#cs-c-amount'))) { throw new Error('حدد عمود المبلغ المحصل في إعدادات الملف.'); }
		const map = {}; $('.cs-st').each(function () { map[$(this).data('k')] = this.value; });
		return {sheet: cs.sheets[parseInt($('#cs-sheet').val() || 0, 10)].name, header_row: parseInt($('#cs-hrow').val(), 10) || 1, order_col: order,
			amount_col: csRef($('#cs-c-amount')), status_col: csRef($('#cs-c-status')), fee_col: csRef($('#cs-c-fee')),
			includes_shipping: $('#cs-incl').is(':checked') ? 1 : 0, tolerance: parseFloat($('#cs-tol').val()) || 0, status_map: Object.assign({}, cs.saved ? cs.saved.status_map : {}, map)};
	}
	window.scImportSaveHook = function (id) {
		const st = csSettings(); if (!st || !id) { return Promise.resolve(); }
		return call('scImportSave', {id, settings: JSON.stringify(st)});
	};

	// ---------- رفع الكشف (اختيار الشركة + الملف فقط) ----------
	let saved = null, lastRes = null, statusMap = {}, rowsCache = null;

	function gridRows(grid, st) {
		const h = headersOf(grid, st.header_row || 1);
		const co = parseInt(findCol(st.order_col, h), 10);
		if (isNaN(co)) { throw new Error('عمود رقم الأمر «' + ((st.order_col && st.order_col.name) || '') + '» غير موجود في هذا الملف. عدّل إعدادات الشركة بملف نموذجي جديد.'); }
		const g = (ref) => { const v = findCol(ref, h); return v === '' ? -1 : parseInt(v, 10); };
		const ca = g(st.amount_col), cst = g(st.status_col), cf = g(st.fee_col);
		const out = [];
		grid.forEach((r, i) => {
			if (i < (st.header_row || 1) || !r) { return; }
			const o = String(r[co] == null ? '' : r[co]).trim();
			if (o === '' || !/\d/.test(o)) { return; }
			out.push({row: i + 1, order: o, amount: ca >= 0 ? r[ca] : null, status: cst >= 0 ? r[cst] : '', fee: cf >= 0 ? r[cf] : null});
		});
		return out;
	}
	function run() {
		const company = $('#imp-company').val();
		if (!company || !saved || !rowsCache) { return; }
		call('scPreview', {company_id: company, rows: JSON.stringify(rowsCache), status_map: JSON.stringify(Object.assign({}, saved.status_map || {}, statusMap)),
			includes_shipping: Number(saved.includes_shipping) ? 1 : 0, tolerance: saved.tolerance == null ? 1 : saved.tolerance})
			.then(r => { lastRes = r; renderResult(); }).catch(fail);
	}
	function renderResult() {
		const r = lastRes, s = r.summary;
		$('#imp-result').show();
		$('#imp-summary').html('<span class="badge badge-secondary p-2 ml-1">الإجمالي ' + s.total + '</span><span class="badge badge-success p-2 ml-1">سليم ' + s.OK + '</span><span class="badge badge-warning p-2 ml-1">يحتاج مراجعة ' + s.REVIEW + '</span><span class="badge badge-danger p-2 ml-1">خطأ ' + s.ERROR + '</span>');
		const keys = Object.keys(r.statuses || {});
		if (keys.length) {
			let h = '<div class="small font-weight-bold mb-1">تفسير الحالات (تعديل مؤقت لهذا الرفع فقط؛ للتثبيت عدّل إعدادات الشركة):</div><div class="form-row">';
			keys.forEach(k => { const cur = statusMap[k] || r.statuses[k].action; h += '<div class="col-md-4 mb-1"><div class="input-group input-group-sm"><div class="input-group-prepend"><span class="input-group-text">' + esc(k) + ' (' + r.statuses[k].count + ')</span></div>' + actionSelect(k, cur, 'imp-st') + '</div></div>'; });
			$('#imp-statuses').html(h + '</div>');
		} else { $('#imp-statuses').empty(); }
		renderTable();
		const m = r.not_in_file;
		$('#imp-missing').text(m.count ? 'أوامر على عهدة الشركة (بانتظار النتيجة) ولم تظهر في الكشف: ' + m.count + (m.sample.length ? ' — مثال: ' + m.sample.slice(0, 10).join('، ') : '') : 'كل أوامر عهدة الشركة الجارية ظهرت في الكشف.');
	}
	function renderTable() {
		const f = $('#imp-filter .active').data('f');
		let h = '<thead class="thead-dark"><tr><th>صف</th><th>رقم الأمر</th><th>حالة الكشف</th><th>المحصل</th><th>قيمة النظام</th><th>شحن النظام</th><th>شحن الشركة</th><th>المتوقع</th><th>الاستنتاج</th><th>الإجراء المقترح</th><th>الحكم</th><th>ملاحظات</th></tr></thead><tbody>';
		lastRes.rows.filter(x => f === 'ALL' || x.flag === f).forEach(x => {
			const b = FLAG[x.flag], notes = x.notes.concat(x.candidates.length ? ['التوزيع: ' + x.candidates.join(' | ')] : []);
			h += '<tr><td>' + x.row + '</td><td class="text-nowrap">' + esc(x.order) + '</td><td>' + esc(x.file_status) + '</td><td>' + money(x.amount) + '</td><td>' + money(x.order_value) + '</td><td>' + money(x.shipping) + '</td><td>' + money(x.company_shipping) + '</td><td>' + money(x.expected) + '</td><td>' + esc(DERIVED[x.derived] || '—') + '</td><td>' + esc(ACTIONS[x.proposed] || '—') + '</td><td><span class="badge ' + b[0] + '">' + b[1] + '</span></td><td class="small">' + esc(notes.join(' — ')) + '</td></tr>';
		});
		$('#imp-table').html(h + '</tbody>');
	}
	function loadCompany() {
		saved = null; $('#imp-saved,#imp-nosaved').hide();
		const id = $('#imp-company').val(); if (!id) { return Promise.resolve(); }
		return call('scGet', {id}).then(c => {
			try { saved = c.import_settings ? JSON.parse(c.import_settings) : null; } catch (e) { saved = null; }
			$('#imp-saved').toggle(!!saved); $('#imp-nosaved').toggle(!saved);
		}).catch(fail);
	}
	async function onImpFile(file) {
		if (!file) { return; }
		try {
			if (!$('#imp-company').val()) { throw new Error('اختر شركة الشحن أولًا.'); }
			if (!saved) { throw new Error('لم تُضبط إعدادات ملف هذه الشركة. افتح "تعديل" للشركة وارفع ملفًا نموذجيًا.'); }
			const sh = await readFile(file);
			let k = sh.findIndex(x => x.name === saved.sheet); if (k < 0) { k = largest(sh); }
			statusMap = {}; rowsCache = gridRows(sh[k].rows, saved);
			if (!rowsCache.length) { throw new Error('لا توجد صفوف بأرقام أوامر في الورقة «' + sh[k].name + '».'); }
			run();
		} catch (e) { fail(e); }
	}

	$(function () {
		if (!$('#scImportPage').length) { return; }
		$('#scImportPage').show();
		call('scList').then(list => {
			$('#imp-company').html('<option value="">— اختر —</option>' + list.filter(c => Number(c.is_active) === 1).map(c => '<option value="' + c.id + '">' + esc(c.name) + '</option>').join(''));
		}).catch(fail);
		$('#imp-company').on('change', loadCompany);
		$('#imp-file').on('change', function () { onImpFile(this.files[0]); });
		$('#imp-filter').on('click', 'button', function () { $('#imp-filter button').removeClass('active'); $(this).addClass('active'); if (lastRes) { renderTable(); } });
		$('#imp-statuses').on('change', '.imp-st', function () { statusMap[$(this).data('k')] = this.value; run(); });

		// نافذة إعدادات الشركة
		$(document).on('sc:open', function (e, c) {
			cs.sheets = []; cs.grid = []; cs.saved = null; cs.dirty = false; cs.statusMap = {};
			$('#cs-file').val(''); $('#cs-map,#cs-sheet-box').hide(); $('#cs-statuses').empty(); csDescribe();
			if (!c || !Number(c.id)) { return; }
			call('scGet', {id: c.id}).then(r => { try { cs.saved = r.import_settings ? JSON.parse(r.import_settings) : null; } catch (x) { cs.saved = null; } csDescribe(); }).catch(fail);
		});
		$('#cs-file').on('change', async function () {
			const f = this.files[0]; if (!f) { return; }
			try {
				cs.sheets = await readFile(f);
				$('#cs-sheet').html(cs.sheets.map((x, i) => '<option value="' + i + '">' + esc(x.name) + ' (' + x.rows.length + ')</option>').join(''));
				$('#cs-sheet-box').toggle(cs.sheets.length > 1);
				let b = largest(cs.sheets); if (cs.saved && cs.saved.sheet) { const k = cs.sheets.findIndex(x => x.name === cs.saved.sheet); if (k >= 0) { b = k; } }
				$('#cs-sheet').val(b); csPick(b);
			} catch (e) { fail(e); }
		});
		$('#cs-sheet').on('change', function () { csPick(parseInt(this.value, 10)); });
		$('#cs-hrow').on('change', function () { csGuess(); csRenderSample(); cs.dirty = true; });
		$('#cs-c-order,#cs-c-amount,#cs-c-fee,#cs-incl,#cs-tol').on('change', function () { cs.dirty = true; });
		$('#cs-c-status').on('change', function () { cs.dirty = true; csRenderStatuses(); });
		$('#cs-statuses').on('change', '.cs-st', function () { cs.statusMap[$(this).data('k')] = this.value; cs.dirty = true; });
	});
})();
