@extends('layouts.app')

@section('title', 'الشركات')
@section('page-title', 'الشركات')
@section('breadcrumb', 'الشركات')

@section('content')
<h1>إدارة الشركات</h1><p><a href="{{ route('dashboard') }}">الرئيسية</a> · <a href="{{ route('admin.structure.branches') }}">الفروع</a> · <a href="{{ route('admin.structure.warehouses') }}">المخازن</a></p>
@if(session('status'))<p class="status">{{ session('status') }}</p>@endif @if($errors->any())<section><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></section>@endif
@if(auth()->user()->hasPermission('companies.create'))<section><h2>إضافة شركة</h2><form method="post" action="{{ route('admin.structure.companies.store') }}">@csrf
<input name="name" required maxlength="150" placeholder="اسم الشركة"><input name="phone" maxlength="40" placeholder="الهاتف"><input name="mobile" maxlength="40" placeholder="المحمول"><input name="fax" maxlength="40" placeholder="الفاكس"><input name="email" type="email" placeholder="البريد">
<select name="city_id"><option value="">المدينة (اختياري)</option>@foreach($cities as $city)<option value="{{ $city->id }}">{{ $city->name_ar }}</option>@endforeach</select><input name="address" placeholder="العنوان"><button>إنشاء الشركة</button></form></section>@endif
<section><h2>الشركات</h2><table><thead><tr><th>الشركة</th><th>الهاتف</th><th>الفروع</th><th>الحالة</th><th>تعديل</th></tr></thead><tbody>
@forelse($companies as $company)<tr><td>{{ $company->name }}</td><td>{{ $company->phone }}</td><td>{{ $company->branches_count }} (نشط: {{ $company->active_branches_count }})</td><td>{{ $company->is_active?'نشطة':'موقوفة' }}</td><td>
@if(auth()->user()->hasPermission('companies.update'))<form class="inline" method="post" action="{{ route('admin.structure.companies.update',$company) }}">@csrf @method('put')
<input name="name" value="{{ $company->name }}" required><input name="phone" value="{{ $company->phone }}"><input name="mobile" value="{{ $company->mobile }}"><input name="fax" value="{{ $company->fax }}"><input name="email" type="email" value="{{ $company->email }}"><select name="city_id"><option value="">بدون مدينة</option>@foreach($cities as $city)<option value="{{ $city->id }}" @selected($company->city_id==$city->id)>{{ $city->name_ar }}</option>@endforeach</select><input name="address" value="{{ $company->address }}"><button>حفظ</button></form>@endif
@if(auth()->user()->hasPermission('companies.activate'))<form class="inline" method="post" action="{{ route('admin.structure.companies.active',$company) }}">@csrf @method('patch')<input type="hidden" name="is_active" value="{{ $company->is_active?0:1 }}"><button>{{ $company->is_active?'إيقاف':'تفعيل' }}</button></form>@endif
</td></tr>@empty<tr><td colspan="5">لا توجد شركات ضمن نطاق الوصول.</td></tr>@endforelse</tbody></table>{{ $companies->links() }}</section>
@endsection
