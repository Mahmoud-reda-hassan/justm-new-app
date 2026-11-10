@extends('layouts.app')

@section('title', 'تحديث قاعدة البيانات')
@section('page-title', 'تحديث قاعدة البيانات')
@section('breadcrumb', 'تحديث قاعدة البيانات')

@section('content')
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
@endsection
