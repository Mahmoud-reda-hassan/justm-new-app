@extends('layouts.app')

@section('title', $config['title'])
@section('page-title', $config['title'])
@section('breadcrumb', 'الإعدادات الجغرافية / '.$config['title'])

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <p class="text-muted mb-2">إدارة البيانات الجغرافية وربط كل مستوى بالمستوى الأعلى دون حذف السجلات المستخدمة.</p>
    <div class="btn-group btn-group-sm mb-2">
        @foreach(['countries' => 'الدول', 'governorates' => 'المحافظات', 'cities' => 'المدن'] as $key => $label)
            @if(auth()->user()->hasPermission('geography.'.$key.'.view'))
                <a class="btn {{ $entity === $key ? 'btn-primary' : 'btn-default' }}" href="{{ route('admin.geography.index', $key) }}">{{ $label }}</a>
            @endif
        @endforeach
        @if(auth()->user()->hasPermission('shipping_zones.view'))<a class="btn btn-default" href="{{ route('admin.shipping.zones.index') }}">مناطق الشحن</a>@endif
    </div>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="card card-outline card-primary">
    <div class="card-header"><h3 class="card-title">{{ $config['title'] }}</h3><div class="card-tools"><span class="badge badge-secondary">{{ number_format($rows->total()) }} سجل</span></div></div>
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between mb-3">
            <form method="get" class="form-inline mb-2"><input class="form-control form-control-sm ml-2" name="q" value="{{ request('q') }}" placeholder="بحث بالاسم"><button class="btn btn-default btn-sm">بحث</button><a class="btn btn-outline-secondary btn-sm mr-2" href="{{ route('admin.geography.index', $entity) }}">مسح</a></form>
            @if(auth()->user()->hasPermission($config['permission'].'.create'))<button class="btn btn-primary btn-sm mb-2" data-toggle="modal" data-target="#createGeographyModal"><i class="fas fa-plus ml-1"></i> إضافة {{ $config['singular'] }}</button>@endif
        </div>
        <div class="table-responsive"><table class="table table-bordered table-hover table-striped">
            <thead><tr>@if($entity === 'governorates')<th>الدولة</th>@elseif($entity === 'cities')<th>المحافظة / الدولة</th>@endif<th>الاسم بالعربية</th><th>الاسم بالإنجليزية</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    @if($entity === 'governorates')<td>{{ $row->country?->name_ar ?? '—' }}</td>@elseif($entity === 'cities')<td>{{ $row->governorate?->name_ar ?? 'بدون محافظة' }} <small class="text-muted d-block">{{ $row->governorate?->country?->name_ar }}</small></td>@endif
                    <td>{{ $row->name_ar }}</td><td dir="ltr" class="text-right">{{ $row->name_en }}</td>
                    <td><span class="badge {{ $row->is_active ? 'badge-success' : 'badge-secondary' }}">{{ $row->is_active ? 'نشط' : 'موقوف' }}</span></td>
                    <td class="text-nowrap">
                        @if(auth()->user()->hasPermission($config['permission'].'.update'))<button class="btn btn-primary btn-xs" data-toggle="modal" data-target="#editGeo{{ $row->id }}">تعديل</button>@endif
                        @if(auth()->user()->hasPermission($config['permission'].'.activate'))
                            <form class="d-inline" method="post" action="{{ route('admin.geography.active', [$entity, $row->id]) }}" onsubmit="return confirm('تأكيد تغيير حالة السجل؟')">@csrf @method('patch')<input type="hidden" name="is_active" value="{{ $row->is_active ? 0 : 1 }}"><button class="btn btn-xs {{ $row->is_active ? 'btn-danger' : 'btn-success' }}">{{ $row->is_active ? 'إيقاف' : 'تفعيل' }}</button></form>
                        @endif
                    </td>
                </tr>
            @empty<tr><td colspan="{{ $entity === 'countries' ? 4 : 5 }}" class="text-center text-muted py-4">لا توجد سجلات.</td></tr>@endforelse
            </tbody>
        </table></div>
        {{ $rows->links() }}
    </div>
</div>

@if(auth()->user()->hasPermission($config['permission'].'.create'))
<div class="modal fade" id="createGeographyModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<form method="post" action="{{ route('admin.geography.store', $entity) }}">@csrf
<div class="modal-header"><h5 class="modal-title">إضافة {{ $config['singular'] }}</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
<div class="modal-body"><div class="form-row">
@if($entity === 'governorates')<div class="form-group col-12"><label>الدولة</label><select class="form-control" name="country_id" required><option value="">اختر الدولة</option>@foreach($countries as $country)<option value="{{ $country->id }}">{{ $country->name_ar }}</option>@endforeach</select></div>@endif
@if($entity === 'cities')<div class="form-group col-12"><label>المحافظة / المقاطعة</label><select class="form-control" name="governorate_id"><option value="">بدون محافظة</option>@foreach($governorates as $governorate)<option value="{{ $governorate->id }}">{{ $governorate->country?->name_ar }} — {{ $governorate->name_ar }}</option>@endforeach</select></div>@endif
<div class="form-group col-md-6"><label>الاسم بالعربية</label><input class="form-control" name="name_ar" maxlength="{{ $entity === 'cities' ? 120 : 100 }}" required></div>
<div class="form-group col-md-6"><label>الاسم بالإنجليزية</label><input class="form-control" name="name_en" dir="ltr" maxlength="{{ $entity === 'cities' ? 120 : 100 }}" required></div>
</div></div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button><button class="btn btn-primary">حفظ</button></div>
</form></div></div></div>
@endif

@if(auth()->user()->hasPermission($config['permission'].'.update'))
@foreach($rows as $row)
<div class="modal fade" id="editGeo{{ $row->id }}" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<form method="post" action="{{ route('admin.geography.update', [$entity, $row->id]) }}">@csrf @method('put')
<div class="modal-header"><h5 class="modal-title">تعديل {{ $config['singular'] }}</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
<div class="modal-body"><div class="form-row">
@if($entity === 'governorates')<div class="form-group col-12"><label>الدولة</label><select class="form-control" name="country_id" required>@foreach($countries as $country)<option value="{{ $country->id }}" @selected($row->country_id == $country->id)>{{ $country->name_ar }}</option>@endforeach</select></div>@endif
@if($entity === 'cities')<div class="form-group col-12"><label>المحافظة / المقاطعة</label><select class="form-control" name="governorate_id"><option value="">بدون محافظة</option>@foreach($governorates as $governorate)<option value="{{ $governorate->id }}" @selected($row->governorate_id == $governorate->id)>{{ $governorate->country?->name_ar }} — {{ $governorate->name_ar }}</option>@endforeach</select></div>@endif
<div class="form-group col-md-6"><label>الاسم بالعربية</label><input class="form-control" name="name_ar" value="{{ $row->name_ar }}" required maxlength="{{ $entity === 'cities' ? 120 : 100 }}"></div>
<div class="form-group col-md-6"><label>الاسم بالإنجليزية</label><input class="form-control" name="name_en" value="{{ $row->name_en }}" dir="ltr" required maxlength="{{ $entity === 'cities' ? 120 : 100 }}"></div>
</div></div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button><button class="btn btn-primary">حفظ التعديلات</button></div>
</form></div></div></div>
@endforeach
@endif
@endsection
