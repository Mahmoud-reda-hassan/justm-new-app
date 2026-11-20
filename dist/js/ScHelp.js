/*
 * أيقونات (i) تشرح دور كل زر في شاشات شركات الشحن والاستلام المالي، وماذا يحدث عند الضغط عليه.
 * الاستخدام: ScHelp.icon('المفتاح') يرجع HTML أيقونة، والضغط عليها يفتح نافذة SweetAlert2 بالشرح.
 */
(function () {
	'use strict';
	if (window.ScHelp) { return; }

	const H = {
		scShortage: {
			title: 'تسجيل عجز على الشركة',
			role: 'يثبّت المخزن واقعة قطع مرتجعة اعتبرها النظام مستلَمة، لكنها لم تصل فعليًا من شركة الشحن.',
			steps: [
				'اكتب رقم الأمر (بعد أن يكون مرتجعه قد استُلم مخزنيًا).',
				'اختر الصنف وكمية القطع التي لم تصل (لا تزيد عن المتبقي).',
				'تخرج القطع من المخزون فورًا لأنها غير موجودة فعليًا.',
				'لا يُسجَّل أي قيد محاسبي هنا: يبقى العجز «بانتظار ترحيل المالية».'
			],
			note: 'لا يمكن تسجيل عجز قبل الاستلام المخزني للأمر.'
		},
		scReceiveAll: {
			title: 'استلام كل المرتجعات',
			role: 'يستلم المخزن كل المرتجعات المعلّقة على عهدة الشركة أو المندوب بإذن واحد، بعد أن حدّد حالة كل أمر (كلي / جزئي / مرفوض).',
			steps: [
				'تظهر لك قائمة بكل صنف ومواصفاته وكميته المرتجعة قبل التأكيد.',
				'تدخل قطع المرتجعات في المخزون.',
				'يُعلَّم كل أمر بأنه «استُلم مخزنيًا».',
				'الأمر المرفوض يبقى في عهدة الشركة ليظهر في الاستلام المالي (تبويب المرفوضات) ويُسوّى هناك.'
			],
			note: 'الاستلام بالباركود/QR لأمر واحد يعمل كما هو دون تغيير.'
		},
		scfinCalc: {
			title: 'تسوية الشحن',
			role: 'يشرح كيف يُحسب شحن هذا الأمر حسب إعدادات شركة الشحن أو نسبة المندوب، ويتيح تعديله قبل السداد.',
			steps: [
				'مندوب شركة شحن: يعرض طريقة الرسم، ورسم المرفوض غير المدفوع، وما ورد في كشف الشركة، والرسم المعتمد ومصدره، والفرق عن شحن العميل.',
				'مندوب عادي: يعرض النسبة وأساس الحساب والعمولة.',
				'يوضح سبب ظهور الشحن المطلوب صفرًا (إيقاف تسليم، أو مرفوض غير مدفوع، أو شحن الأمر صفر).',
				'يمكنك تعديل الرسم/العمولة بسبب مكتوب، فيعتمد السداد الرقم المعدَّل ويُسجَّل التعديل في سجل الأمر.'
			],
			note: 'لا يتم التسجيل المحاسبي من هذا الزر؛ التسجيل يتم عند زر «سداد».'
		},
		scfinReopen: {
			title: 'رد للمخزن للتصحيح',
			role: 'يعيد الأمر إلى المخزن لتصحيح نتيجة التسليم قبل الاستلام المالي.',
			steps: [
				'يُطلب منك سبب (إلزامي).',
				'تُعكس آثار التسليم بقيد مضاد دون حذف أي سجل.',
				'يعود الأمر للمخزن ليُعدَّل ويُؤكَّد من جديد.'
			],
			note: 'متاح للأوامر التي لم يتم سدادها بعد.'
		},
		scfinPost: {
			title: 'ترحيل قيد العجز',
			role: 'ترحّل المالية القيد المحاسبي لعجز ثبّته المخزن على شركة الشحن.',
			steps: [
				'يعرض القطع وقيمتها وإجمالي المستحق على الشركة.',
				'القيد: مدين مستحق على شركة الشحن، ودائن المخزون بالتكلفة.',
				'لو التقييم بسعر البيع، يذهب الفرق عن التكلفة إلى حساب فروق العجز.',
				'بعد الترحيل لا يظهر العجز كمعلّق.'
			],
			note: 'المخزون خرج فعلاً وقت تثبيت المخزن؛ هذا الزر للقيد المحاسبي فقط.'
		},
		settleNet: {
			title: 'خصم العمولة / الرسم من المحصَّل',
			role: 'يحدد كيف تُسوّى عمولة المندوب أو رسم شركة الشحن عند هذا السداد.',
			steps: [
				'مفعّل: يدخل الصندوق الصافي بعد خصم العمولة أو الرسم، ولا يبقى مستحق.',
				'غير مفعّل: يُحصَّل المبلغ كاملاً، ويُسجَّل المستحق في حساب المندوب أو الشركة ويُسدَّد لاحقًا بإذن صرف.',
				'يبدأ مفعّلاً إذا كانت إعدادات الشركة «تخصم رسمها من المحصَّل»، وغير مفعّل غير ذلك.',
				'الرقم الكبير أعلى النافذة يتغيّر ليعرض الصافي الذي سيُحصَّل فعليًا.'
			],
			note: 'الاختيار يسري على كل الأوامر في هذه العملية فقط، ولا يغيّر إعدادات الشركة.'
		}
	};

	const esc = (v) => String(v == null ? '' : v).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

	function show(key) {
		const h = H[key];
		if (!h || !window.Swal) { return; }
		const html = '<div dir="rtl" class="text-right" style="text-align:right;direction:rtl;font-size:.95rem;line-height:1.9">' +
			'<p class="font-weight-bold mb-2">' + esc(h.role) + '</p>' +
			'<div class="text-muted small mb-1">ماذا يحدث عند الضغط:</div>' +
			'<ol class="pr-3 mb-2">' + h.steps.map(s => '<li>' + esc(s) + '</li>').join('') + '</ol>' +
			(h.note ? '<div class="alert alert-info py-2 mb-0 small">' + esc(h.note) + '</div>' : '') + '</div>';
		Swal.fire({icon: 'info', title: h.title, html: html, width: 560, customClass: {popup: 'sc-help-rtl'}, confirmButtonText: 'فهمت'});
	}

	window.ScHelp = {
		icon: (key) => H[key] ? '<a href="#" class="sc-info mr-1" data-sc-info="' + esc(key) + '" title="شرح الزر" style="text-decoration:none"><i class="fa fa-info-circle text-info"></i></a>' : '',
		show: show
	};

	function body(h) {
		return '<div class="font-weight-bold mb-1">' + esc(h.role) + '</div><ol class="pr-3 mb-1">' + h.steps.map(s => '<li>' + esc(s) + '</li>').join('') + '</ol>' + (h.note ? '<div class="text-muted">' + esc(h.note) + '</div>' : '');
	}

	$(document).on('click', '.sc-info', function (e) {
		e.preventDefault(); e.stopPropagation();
		const key = $(this).data('sc-info');
		// داخل نافذة SweetAlert مفتوحة: اعرض الشرح داخلها حتى لا تُغلق النافذة الأصلية
		if ($(this).closest('.swal2-popup').length) {
			const box = $(this).closest('.custom-control').parent();
			const ex = box.find('.sc-info-inline');
			if (ex.length) { ex.toggle(); return; }
			if (H[key]) { box.append('<div dir="rtl" class="sc-info-inline alert alert-info small text-right mt-2 mb-0" style="line-height:1.8">' + body(H[key]) + '</div>'); }
			return;
		}
		show(key);
	});

	// أيقونات الأزرار الثابتة في شاشة العهدة: تظهر وتختفي مع الزر نفسه
	$(function () {
		const st = '<style>.sc-help-rtl,.sc-help-rtl *:not(.swal2-icon):not(.swal2-icon *){direction:rtl!important;text-align:right!important}.sc-help-rtl .swal2-actions{justify-content:center!important}.sc-help-rtl .swal2-icon{margin:1em auto!important}#scShortageBtn[style*="display: none"] + .sc-info,#scShortageBtn[style*="display:none"] + .sc-info,#scReceiveAllBtn[style*="display: none"] + .sc-info,#scReceiveAllBtn[style*="display:none"] + .sc-info{display:none}#scPlainTools[style*="display: none"] .sc-info{display:none}</style>';
		$('head').append(st);
		$('#scShortageBtn').after(window.ScHelp.icon('scShortage'));
		$('#scReceiveAllBtn').after(window.ScHelp.icon('scReceiveAll'));
		$('#scReceiveAllPlainBtn').after(window.ScHelp.icon('scReceiveAll'));
	});
})();
