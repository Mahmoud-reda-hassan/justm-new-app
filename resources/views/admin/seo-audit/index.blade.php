@extends('layouts.app')

@section('title', 'مراجعة صفحات الموقع')
@section('page-title', 'مراجعة صفحات الموقع')
@section('breadcrumb', 'مراجعة صفحات الموقع')

@push('styles')
<style>
    .audit-page{--audit-bg:#fff;--audit-soft:#f8fafc;--audit-border:#e2e8f0;--audit-text:#0f172a;--audit-muted:#64748b;--audit-primary:#2563eb;--audit-shadow:0 1px 2px rgba(15,23,42,.06);color:var(--audit-text)}
    .audit-intro{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin:4px 0 22px}
    .audit-intro h2{font-size:24px;font-weight:700;margin:0 0 7px;line-height:1.4}
    .audit-intro p{font-size:13px;color:var(--audit-muted);margin:0;line-height:1.8}
    .audit-preview{display:inline-flex;align-items:center;gap:7px;padding:6px 10px;border-radius:6px;background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:600;white-space:nowrap}
    .audit-panel{background:var(--audit-bg);border:1px solid var(--audit-border);border-radius:10px;box-shadow:var(--audit-shadow);margin-bottom:16px}
    .audit-filters{padding:16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
    .audit-search{position:relative;flex:1 1 280px;max-width:420px}
    .audit-search i{position:absolute;right:13px;top:13px;color:#94a3b8}
    .audit-control{height:42px;border:1px solid var(--audit-border);border-radius:7px;background:var(--audit-bg);color:var(--audit-text);font-size:13px;padding:0 12px;width:100%;outline:0}
    .audit-search .audit-control{padding-right:38px}
    .audit-control:focus{border-color:#2563eb;box-shadow:0 0 0 2px rgba(37,99,235,.18)}
    .audit-select{flex:0 1 165px;min-width:145px}
    .audit-filter-label{font-size:11px;color:var(--audit-muted);display:block;margin:0 0 5px}
    .audit-table-wrap{overflow-x:auto}
    .audit-table{width:100%;border-collapse:separate;border-spacing:0;min-width:760px}
    .audit-table thead th{height:48px;padding:0 14px;background:var(--audit-soft);border-bottom:1px solid var(--audit-border);font-size:11px;font-weight:700;color:var(--audit-muted);white-space:nowrap;text-align:right}
    .audit-table tbody td{height:56px;padding:12px 14px;border-bottom:1px solid var(--audit-border);font-size:13px}
    .audit-table tbody tr:last-child td{border-bottom:0}
    .audit-table .url-cell{font-weight:600;direction:ltr;text-align:right}
    .audit-table .url-cell small{display:block;font-size:11px;font-weight:400;color:var(--audit-muted);margin-top:4px}
    .audit-empty{padding:58px 20px;text-align:center}
    .audit-empty-icon{width:56px;height:56px;margin:0 auto 15px;border-radius:14px;background:#eff6ff;color:#2563eb;display:grid;place-items:center;font-size:23px}
    .audit-empty h3{font-size:16px;font-weight:700;margin:0 0 7px}
    .audit-empty p{font-size:13px;line-height:1.8;color:var(--audit-muted);margin:0 auto;max-width:420px}
    .audit-table-foot{padding:13px 16px;border-top:1px solid var(--audit-border);display:flex;justify-content:space-between;align-items:center;color:var(--audit-muted);font-size:12px}
    .audit-muted-button{height:36px;padding:0 12px;border:1px solid var(--audit-border);border-radius:7px;background:var(--audit-bg);color:var(--audit-muted);font-size:12px}
    .audit-note{font-size:11px;color:var(--audit-muted);margin:10px 2px 0}
    .audit-severity{display:inline-flex;align-items:center;gap:6px;border-radius:6px;padding:4px 8px;font-size:11px;font-weight:600}
    .audit-severity:before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor}
    .audit-severity.critical{background:#fee2e2;color:#b91c1c}.audit-severity.high{background:#ffedd5;color:#c2410c}
    .audit-severity.medium{background:#fef3c7;color:#a16207}.audit-severity.low{background:#e0f2fe;color:#0369a1}
    .audit-page :focus-visible{outline:2px solid #2563eb;outline-offset:2px}
    @media(max-width:767.98px){
        .audit-intro{display:block}.audit-preview{margin-top:12px}
        .audit-filters{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .audit-search{grid-column:1/-1;max-width:none}
        .audit-select{min-width:0;width:100%}
        .audit-empty{padding:42px 16px}
        .audit-table-foot{align-items:flex-start;gap:10px;flex-direction:column}
    }
    @media(prefers-color-scheme:dark){
        .audit-page{--audit-bg:#111827;--audit-soft:#1e293b;--audit-border:#334155;--audit-text:#f8fafc;--audit-muted:#94a3b8;--audit-primary:#60a5fa}
        .audit-preview{background:#172554;color:#93c5fd}
        .audit-empty-icon{background:#172554;color:#93c5fd}
        .audit-control{color-scheme:dark}
        .audit-severity.critical{background:#450a0a;color:#fca5a5}.audit-severity.high{background:#431407;color:#fdba74}
        .audit-severity.medium{background:#422006;color:#fde68a}.audit-severity.low{background:#082f49;color:#7dd3fc}
    }
    [data-theme="dark"] .audit-page{--audit-bg:#111827;--audit-soft:#1e293b;--audit-border:#334155;--audit-text:#f8fafc;--audit-muted:#94a3b8}
</style>
@endpush

@section('content')
<div class="audit-page" dir="rtl">
    <div class="audit-intro">
        <div>
            <h2>مراجعة صفحات الموقع</h2>
            <p>واجهة موحدة لمعاينة مؤشرات تحسين محركات البحث وإمكانية الوصول للصفحات.</p>
        </div>
        <span class="audit-preview"><i class="fas fa-eye" aria-hidden="true"></i> معاينة تصميم فقط</span>
    </div>

    <div class="audit-panel">
        <div class="audit-filters" aria-label="شريط البحث والتصفية - معاينة">
            <div class="audit-search">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input class="audit-control" type="search" placeholder="ابحث باسم الصفحة أو URL" aria-label="البحث باسم الصفحة أو الرابط" disabled>
            </div>
            <div class="audit-select">
                <label class="audit-filter-label" for="audit-status">حالة الصفحة</label>
                <select id="audit-status" class="audit-control" disabled><option>كل الحالات</option></select>
            </div>
            <div class="audit-select">
                <label class="audit-filter-label" for="audit-seo">SEO</label>
                <select id="audit-seo" class="audit-control" disabled><option>كل النتائج</option></select>
            </div>
            <div class="audit-select">
                <label class="audit-filter-label" for="audit-accessibility">Accessibility</label>
                <select id="audit-accessibility" class="audit-control" disabled><option>كل النتائج</option></select>
            </div>
        </div>
    </div>

    <div class="audit-panel">
        <div class="d-flex align-items-center justify-content-between flex-wrap" style="padding:16px;gap:8px">
            <div>
                <h3 style="font-size:15px;font-weight:700;margin:0 0 5px">صفحات الموقع</h3>
                <p style="font-size:12px;color:var(--audit-muted);margin:0">عرض الصفحات ونتائج المراجعة في جدول موحد.</p>
            </div>
            <button type="button" class="audit-muted-button" disabled><i class="fas fa-sliders-h ml-1" aria-hidden="true"></i> خيارات العرض</button>
        </div>
        <div class="audit-table-wrap">
            <table class="audit-table" aria-label="جدول صفحات الموقع">
                <thead>
                    <tr>
                        <th style="width:34%">الصفحة / URL</th>
                        <th>SEO Score</th>
                        <th>Accessibility Score</th>
                        <th>Severity</th>
                        <th>آخر مراجعة</th>
                        <th>التفاصيل</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="6">
                            <div class="audit-empty">
                                <div class="audit-empty-icon"><i class="fas fa-file-search" aria-hidden="true"></i></div>
                                <h3>لا توجد صفحات لعرضها</h3>
                                <p>ستظهر الصفحات هنا عند ربط واجهة المراجعة بمصدر البيانات. هذه الشاشة حاليًا معاينة بصرية فقط ولا تعرض نتائج فحص فعلية.</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="audit-table-foot">
            <span>لا توجد سجلات في المعاينة</span>
            <div class="d-flex align-items-center" style="gap:8px">
                <button type="button" class="audit-muted-button" disabled aria-label="الصفحة السابقة"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                <span>0 من 0</span>
                <button type="button" class="audit-muted-button" disabled aria-label="الصفحة التالية"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
            </div>
        </div>
    </div>
    <p class="audit-note"><i class="fas fa-info-circle ml-1" aria-hidden="true"></i> عناصر البحث والتصفية والإجراءات معروضة لأغراض التصميم فقط، وغير مفعّلة في هذه المرحلة.</p>
</div>
@endsection
