@extends('layouts.app')

@section('title', 'المخازن')
@section('page-title', 'المخازن')
@section('breadcrumb', 'المخازن')

@section('content')
<h1>إدارة المخازن</h1><p><a href="{{ route('dashboard') }}">الرئيسية</a> · <a href="{{ route('admin.structure.companies') }}">الشركات</a> · <a href="{{ route('admin.structure.branches') }}">الفروع</a></p>
@if(session('status'))<p class="status">{{ session('status') }}</p>@endif @if($errors->any())<section><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></section>@endif
@if(auth()->user()->hasPermission('warehouses.create'))<section><h2>إضافة مخزن</h2><form method="post" action="{{ route('admin.structure.warehouses.store') }}">@csrf
<select name="branch_id" required><option value="">الفرع</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->company->name }} — {{ $branch->name }}</option>@endforeach</select><select name="warehouse_type_id" required><option value="">نوع المخزن</option>@foreach($types as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select><input name="name" required placeholder="اسم المخزن"><button>إنشاء المخزن</button></form></section>@endif
<section><h2>المخازن</h2><table><thead><tr><th>المخزن</th><th>الفرع / الشركة</th><th>النوع</th><th>الحالة</th><th>إجراءات</th></tr></thead><tbody>
@forelse($warehouses as $warehouse)<tr><td>{{ $warehouse->name }}</td><td>{{ $warehouse->branch->name }} / {{ $warehouse->branch->company->name }}</td><td>{{ $warehouse->type->name }}</td><td>{{ $warehouse->is_active?'نشط':'موقوف' }}</td><td>
@if(auth()->user()->hasPermission('warehouses.update'))<form class="inline" method="post" action="{{ route('admin.structure.warehouses.update',$warehouse) }}">@csrf @method('put')<select name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($warehouse->branch_id==$branch->id)>{{ $branch->company->name }} — {{ $branch->name }}</option>@endforeach</select><select name="warehouse_type_id" required>@foreach($types as $type)<option value="{{ $type->id }}" @selected($warehouse->warehouse_type_id==$type->id)>{{ $type->name }}</option>@endforeach</select><input name="name" value="{{ $warehouse->name }}" required><button>حفظ</button></form>@endif
@if(auth()->user()->hasPermission('warehouses.activate'))<form class="inline" method="post" action="{{ route('admin.structure.warehouses.active',$warehouse) }}">@csrf @method('patch')<input type="hidden" name="is_active" value="{{ $warehouse->is_active?0:1 }}"><button>{{ $warehouse->is_active?'إيقاف':'تفعيل' }}</button></form>@endif</td></tr>@empty<tr><td colspan="5">لا توجد مخازن ضمن نطاق الوصول.</td></tr>@endforelse</tbody></table>{{ $warehouses->links() }}</section>
@endsection
