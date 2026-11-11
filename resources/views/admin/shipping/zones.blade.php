@extends('layouts.app')

@section('title', 'مناطق الشحن')
@section('page-title', 'مناطق الشحن')
@section('breadcrumb', 'إعداد الشحن')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <p class="text-muted mb-2">إدارة مناطق الشحن المحلي والتغطية الدولية من شاشة واحدة.</p>
    @if(auth()->user()->hasPermission('shipping_zones.create'))
        <button class="btn btn-primary btn-sm mb-2" data-toggle="modal" data-target="#createShippingZoneModal">
            <i class="fas fa-plus ml-1"></i> إضافة منطقة شحن
        </button>
    @endif
</div>

@if(session('status'))<div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>@endif
@if($errors->any())<div class="alert alert-danger"><strong>تعذر حفظ البيانات:</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title">مناطق الشحن</h3>
        <div class="card-tools"><span class="badge badge-secondary">{{ number_format($zones->total()) }} منطقة</span></div>
    </div>
    <div class="card-body">
        <form method="get" class="form-row mb-3">
            <div class="col-md-6 mb-2"><input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="ابحث باسم المنطقة أو المدينة أو الدولة أو المخزن"></div>
            <div class="col-auto mb-2"><button class="btn btn-default" type="submit"><i class="fas fa-search ml-1"></i> بحث</button> <a class="btn btn-outline-secondary" href="{{ route('admin.shipping.zones.index') }}">مسح</a></div>
        </form>
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped">
                <thead><tr><th>المنطقة</th><th>النطاق</th><th>الرسوم</th><th>المدة</th><th>نوع الشحن</th><th>مخزن الصرف</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
                <tbody>
                @forelse($zones as $zone)
                    <tr>
                        <td><strong>{{ $zone->name_ar }}</strong><small class="d-block text-muted">{{ $zone->name_en }}</small></td>
                        <td style="min-width:190px">
                            @if($zone->zone_type === 'international')
                                @foreach($zone->countries as $country)<span class="badge badge-info mb-1">{{ $country->name_ar }}</span>@endforeach
                                <small class="d-block text-muted">تغطية دولية · {{ $zone->countries->count() }} دولة</small>
                            @else
                                @foreach($zone->cities as $city)<span class="badge badge-light border mb-1">{{ $city->name_ar }}</span>@endforeach
                                <small class="d-block text-muted">{{ $zone->cities_count }} مدينة</small>
                            @endif
                        </td>
                        <td class="text-nowrap">
                            @if($zone->shipping_fee_min !== null && $zone->shipping_fee_max !== null)
                                {{ number_format((float)$zone->shipping_fee_min, 2) }} – {{ number_format((float)$zone->shipping_fee_max, 2) }} ج.م
                            @else <span class="text-muted">لم تحدد</span> @endif
                        </td>
                        <td>{{ $zone->delivery_days !== null ? $zone->delivery_days.' يوم' : 'لم تحدد' }}</td>
                        <td>{{ $zone->zone_type === 'international' ? 'دولي' : ($zone->shipping_type === 'internal' ? 'شحن داخلي' : 'شحن خارجي') }}</td>
                        <td>
                            @if($zone->warehouse)
                                {{ $zone->warehouse->branch->company->name }} — {{ $zone->warehouse->branch->name }} — {{ $zone->warehouse->name }}
                            @else <span class="text-muted">لا ينطبق</span> @endif
                        </td>
                        <td><span class="badge {{ $zone->is_active ? 'badge-success' : 'badge-danger' }}">{{ $zone->is_active ? 'نشطة' : 'موقوفة' }}</span></td>
                        <td class="text-nowrap">
                            @if(auth()->user()->hasPermission('shipping_zones.update') && ($zone->zone_type === 'local' || auth()->user()->hasPermission('structure.scope.manage_all')))
                                <button class="btn btn-primary btn-xs" data-toggle="modal" data-target="#editShippingZoneModal{{ $zone->id }}">تعديل</button>
                            @endif
                            @if(auth()->user()->hasPermission('shipping_zones.activate') && ($zone->zone_type === 'local' || auth()->user()->hasPermission('structure.scope.manage_all')))
                                <form class="d-inline" method="post" action="{{ route('admin.shipping.zones.active', $zone) }}" onsubmit="return confirm('{{ $zone->is_active ? 'هل تريد إيقاف منطقة الشحن؟' : 'هل تريد تفعيل منطقة الشحن؟' }}')">
                                    @csrf @method('patch')
                                    <input type="hidden" name="is_active" value="{{ $zone->is_active ? 0 : 1 }}">
                                    <button class="btn {{ $zone->is_active ? 'btn-danger' : 'btn-success' }} btn-xs">{{ $zone->is_active ? 'إيقاف' : 'تفعيل' }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">لا توجد مناطق شحن ضمن نطاق الوصول.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $zones->links() }}
    </div>
</div>

@if(auth()->user()->hasPermission('shipping_zones.create'))
<div class="modal fade" id="createShippingZoneModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
        <form method="post" action="{{ route('admin.shipping.zones.store') }}">@csrf
            <div class="modal-header"><h5 class="modal-title">إضافة منطقة شحن</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group col-12"><label>نوع المنطقة</label><select class="form-control zone-type-select" name="zone_type" required><option value="local">شحن محلي</option>@if(auth()->user()->hasPermission('structure.scope.manage_all'))<option value="international">تغطية شحن دولي</option>@endif</select></div>
                    <div class="form-group col-md-6"><label>اسم المنطقة بالعربية</label><input class="form-control" name="name_ar" required maxlength="100"></div>
                    <div class="form-group col-md-6"><label>اسم المنطقة بالإنجليزية</label><input class="form-control" name="name_en" required maxlength="100" dir="ltr"></div>
                    <div class="local-zone-fields form-group col-md-3"><label>أقل رسوم شحن (ج.م)</label><input class="form-control" type="number" name="shipping_fee_min" min="0" step="0.01" value="0"></div>
                    <div class="local-zone-fields form-group col-md-3"><label>أعلى رسوم شحن (ج.م)</label><input class="form-control" type="number" name="shipping_fee_max" min="0" step="0.01"></div>
                    <div class="local-zone-fields form-group col-md-3"><label>مدة التوصيل (يوم)</label><input class="form-control" type="number" name="delivery_days" min="0" max="365" value="1"></div>
                    <div class="local-zone-fields form-group col-md-3"><label>نوع الشحن</label><select class="form-control" name="shipping_type"><option value="internal">شحن داخلي</option><option value="external">شحن خارجي</option></select></div>
                    <div class="local-zone-fields form-group col-12"><label>مخزن البيع المباشر</label><select class="form-control select2" name="warehouse_id"><option value="">اختر المخزن</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->branch->company->name }} — {{ $warehouse->branch->name }} — {{ $warehouse->name }}</option>@endforeach</select></div>
                    <div class="local-zone-fields form-group col-12"><label>المدن التابعة للمنطقة</label><select class="form-control select2" name="city_ids[]" multiple data-placeholder="اختر مدينة واحدة أو أكثر">@foreach($cities->whereNull('shipping_zone_id') as $city)<option value="{{ $city->id }}">{{ $city->governorate?->country?->name_ar }} — {{ $city->governorate?->name_ar }} — {{ $city->name_ar }}</option>@endforeach</select><small class="form-text text-muted">المدينة لا يمكن ربطها بأكثر من منطقة شحن في الوقت نفسه.</small></div>
                    <div class="international-zone-fields form-group col-12" style="display:none"><label>دول الوجهة</label><select class="form-control select2" name="country_ids[]" multiple data-placeholder="اختر دولة أو أكثر">@foreach($countries as $country)<option value="{{ $country->id }}">{{ $country->name_ar }} — {{ $country->name_en }}</option>@endforeach</select><small class="form-text text-warning">هذه المرحلة تحدد نطاق الدول فقط. تعرفة الشحن الدولي والعملة والمدة لم تُعتمد بعد، ولن تُستخدم هذه المنطقة في حساب تكلفة أمر البيع.</small></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button><button class="btn btn-primary">حفظ المنطقة</button></div>
        </form>
    </div></div>
</div>
@endif

@if(auth()->user()->hasPermission('shipping_zones.update'))
@foreach($zones as $zone)
@if($zone->zone_type === 'local' || auth()->user()->hasPermission('structure.scope.manage_all'))
<div class="modal fade" id="editShippingZoneModal{{ $zone->id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
        <form method="post" action="{{ route('admin.shipping.zones.update', $zone) }}">@csrf @method('put')
            <input type="hidden" name="zone_type" value="{{ $zone->zone_type }}">
            <div class="modal-header"><h5 class="modal-title">تعديل منطقة الشحن: {{ $zone->name_ar }}</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group col-md-6"><label>اسم المنطقة بالعربية</label><input class="form-control" name="name_ar" value="{{ $zone->name_ar }}" required maxlength="100"></div>
                    <div class="form-group col-md-6"><label>اسم المنطقة بالإنجليزية</label><input class="form-control" name="name_en" value="{{ $zone->name_en }}" required maxlength="100" dir="ltr"></div>
                    @if($zone->zone_type === 'local')
                        <div class="form-group col-md-3"><label>أقل رسوم شحن (ج.م)</label><input class="form-control" type="number" name="shipping_fee_min" min="0" step="0.01" value="{{ $zone->shipping_fee_min }}" required></div>
                        <div class="form-group col-md-3"><label>أعلى رسوم شحن (ج.م)</label><input class="form-control" type="number" name="shipping_fee_max" min="0" step="0.01" value="{{ $zone->shipping_fee_max }}" required></div>
                        <div class="form-group col-md-3"><label>مدة التوصيل (يوم)</label><input class="form-control" type="number" name="delivery_days" min="0" max="365" value="{{ $zone->delivery_days }}" required></div>
                        <div class="form-group col-md-3"><label>نوع الشحن</label><select class="form-control" name="shipping_type" required><option value="internal" @selected($zone->shipping_type === 'internal')>شحن داخلي</option><option value="external" @selected($zone->shipping_type === 'external')>شحن خارجي</option></select></div>
                        <div class="form-group col-12"><label>مخزن البيع المباشر</label><select class="form-control select2" name="warehouse_id" required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected($zone->warehouse_id == $warehouse->id)>{{ $warehouse->branch->company->name }} — {{ $warehouse->branch->name }} — {{ $warehouse->name }}</option>@endforeach</select></div>
                        <div class="form-group col-12"><label>المدن التابعة للمنطقة</label><select class="form-control select2" name="city_ids[]" multiple required>@foreach($cities->filter(fn ($city) => $city->shipping_zone_id === null || $city->shipping_zone_id === $zone->id) as $city)<option value="{{ $city->id }}" @selected($city->shipping_zone_id == $zone->id)>{{ $city->governorate?->country?->name_ar }} — {{ $city->governorate?->name_ar }} — {{ $city->name_ar }}</option>@endforeach</select><small class="form-text text-muted">يمكن إضافة مدن غير مرتبطة أو مدن هذه المنطقة فقط.</small></div>
                    @else
                        <div class="form-group col-12"><label>دول الوجهة</label><select class="form-control select2" name="country_ids[]" multiple required>@foreach($countries as $country)<option value="{{ $country->id }}" @selected($zone->countries->contains('id', $country->id))>{{ $country->name_ar }} — {{ $country->name_en }}</option>@endforeach</select></div>
                        <div class="alert alert-warning col-12 mb-0">يتم حاليًا تعريف نطاق الدول فقط. لا توجد تعرفة أو عملة أو مدة دولية معتمدة، ولن تُستخدم هذه المنطقة في حساب تكلفة أمر البيع.</div>
                    @endif
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button><button class="btn btn-primary">حفظ التعديلات</button></div>
        </form>
    </div></div>
</div>
@endif
@endforeach
@endif
@endsection

@push('scripts')
<script>
(function () {
    function toggleZoneFields(form) {
        var select = form.querySelector('.zone-type-select');
        if (!select) return;
        var international = select.value === 'international';
        form.querySelectorAll('.local-zone-fields').forEach(function (el) {
            el.style.display = international ? 'none' : '';
            el.querySelectorAll('input,select').forEach(function (field) {
                field.disabled = international;
                field.required = !international && (field.name === 'warehouse_id' || field.name === 'city_ids[]' || field.name === 'shipping_fee_min' || field.name === 'shipping_fee_max' || field.name === 'delivery_days' || field.name === 'shipping_type');
            });
        });
        form.querySelectorAll('.international-zone-fields').forEach(function (el) {
            el.style.display = international ? '' : 'none';
            el.querySelectorAll('input,select').forEach(function (field) {
                field.disabled = !international;
                field.required = international;
            });
        });
    }
    document.querySelectorAll('#createShippingZoneModal form').forEach(function (form) {
        var select = form.querySelector('.zone-type-select');
        if (select) {
            select.addEventListener('change', function () { toggleZoneFields(form); });
            toggleZoneFields(form);
        }
    });
})();
</script>
@endpush
