<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>تحديث قاعدة البيانات · {{ config('app.name') }}</title>
<style>
*{box-sizing:border-box}body{margin:0;padding:24px;background:#f3f5f9;color:#202938;font-family:Tahoma,Arial,sans-serif}.wrap{max-width:900px;margin:auto}.panel{background:#fff;border:1px solid #e1e6ee;border-radius:12px;padding:22px;margin:16px 0}h1{font-size:25px}h2{font-size:18px;margin-top:0}.muted{color:#687386;font-size:14px}.meta{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.meta div{background:#f7f8fa;padding:12px;border-radius:8px;overflow-wrap:anywhere}.meta small{display:block;color:#687386;margin-bottom:5px}.status{padding:12px;border-radius:8px;margin:12px 0;background:#edf7f0;color:#176238}.error{background:#fff0f0;color:#9b2525}.row{display:flex;justify-content:space-between;gap:10px;padding:9px 0;border-bottom:1px solid #edf0f4;font-size:14px}.pending{color:#a35b00;font-weight:bold}.done{color:#176238}.field{display:block;margin:14px 0}input{display:block;width:100%;padding:12px;border:1px solid #cbd3df;border-radius:7px;margin-top:6px}button{border:0;border-radius:8px;background:#1f5fae;color:white;padding:13px 20px;font-weight:bold;cursor:pointer}button:disabled{opacity:.5}.warning{background:#fff8e8;border:1px solid #f0d89e;padding:12px;border-radius:8px;line-height:1.8}@media(max-width:650px){body{padding:12px}.meta{grid-template-columns:1fr}.panel{padding:16px}}
</style>
</head><body><main class="wrap">
<p><a href="{{ route('dashboard') }}">العودة للوحة التحكم</a></p>
<h1>تحديث قاعدة البيانات</h1><p class="muted">فحص migrations المسجلة وتشغيل التحديثات الجديدة فقط.</p>
@if(session('status'))<div class="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="status error">{{ $errors->first() }}</div>@endif
<section class="panel"><h2>قاعدة البيانات المتصلة حاليًا</h2>
<div class="meta"><div><small>نوع الاتصال</small>{{ $driver }}</div><div><small>الخادم</small>{{ $host }}</div><div><small>اسم قاعدة البيانات</small><strong>{{ $database }}</strong></div></div>
<p class="warning">تأكد بنفسك أن قاعدة البيانات المعروضة تخص بيئة الاختبار وليست قاعدة الإنتاج. التحديث ينفذ تغييرات بنيوية وقد لا يمكن التراجع عنها بمجرد تشغيلها.</p>
</section>
<section class="panel"><h2>حالة التحديثات</h2>
<p>إجمالي migrations: {{ count($migrations) }} — المنفذ: {{ count($migrations)-count($pending) }} — المعلق: {{ count($pending) }}</p>
@forelse($migrations as $migration)<div class="row"><span>{{ $migration['name'] }}</span><span class="{{ $migration['ran'] ? 'done' : 'pending' }}">{{ $migration['ran'] ? 'منفذ' : 'معلق' }}</span></div>@empty<p>لا توجد ملفات migrations.</p>@endforelse
</section>
@if(count($pending))
<section class="panel"><h2>تنفيذ التحديثات المعلقة</h2>
<p>سيتم تشغيل migrations المعلقة فقط بالترتيب. لن يتم تشغيل Seeders أو إنشاء قاعدة بيانات أو حذف بيانات.</p>
<form method="post" action="{{ route('admin.system.database-updates.run') }}" onsubmit="return confirm('تأكدت أن قاعدة البيانات المعروضة هي قاعدة الاختبار؟');">@csrf
<label class="field">اكتب اسم قاعدة البيانات كما يظهر أعلاه<input name="database_confirmation" required autocomplete="off"></label>
<label class="field">اكتب RUN MIGRATIONS للتأكيد<input name="run_confirmation" required autocomplete="off"></label>
<button type="submit">تنفيذ تحديث قاعدة البيانات</button>
</form></section>
@else<section class="panel"><div class="status">قاعدة البيانات محدثة بالفعل.</div></section>@endif
</main></body></html>
