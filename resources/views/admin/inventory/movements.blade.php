@extends('layouts.app')

@section('title', 'الصرف والتحويل')
@section('page-title', 'الصرف والتحويل')
@section('breadcrumb', 'المخزون / الصرف والتحويل')

@section('content')
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="row">
@if(auth()->user()->hasPermission('inventory.issues.create'))
<div class="col-lg-6"><div class="card card-outline card-primary">
<div class="card-header"><h3 class="card-title">مستند صرف مخزني</h3></div>
<form method="post" action="{{ route('admin.inventory.issues.store') }}">@csrf
<input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}">
<div class="card-body">
<div class="form-group"><label>المخزن</label><select name="warehouse_id" class="form-control" required><option value="">اختر المخزن</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->branch->company->name }} — {{ $warehouse->branch->name }} — {{ $warehouse->name }}</option>@endforeach</select></div>
<div class="table-responsive"><table class="table table-bordered"><thead><tr><th>الصنف</th><th>الكمية</th><th>تكلفة الوحدة</th></tr></thead><tbody>
@for($i=0;$i<3;$i++)<tr>
<td><select name="items[{{ $i }}][product_variant_id]" class="form-control" @required($i===0)><option value="">اختر الصنف</option>@foreach($variants as $variant)<option value="{{ $variant->id }}">{{ $variant->product->name }} — {{ $variant->variant_name ?: $variant->sku }} ({{ $variant->sku }})</option>@endforeach</select></td>
<td><input type="number" name="items[{{ $i }}][quantity]" class="form-control" min="0.001" step="0.001" @required($i===0)></td>
<td><input type="number" name="items[{{ $i }}][unit_cost]" class="form-control" min="0" step="0.0001" value="0"></td>
</tr>@endfor
</tbody></table></div>
<div class="form-group"><label>سبب الصرف / ملاحظات</label><textarea name="notes" class="form-control" rows="2" maxlength="2000"></textarea></div>
</div><div class="card-footer text-left"><button class="btn btn-primary" type="submit" onclick="return confirm('سيتم ترحيل الصرف وتخفيض رصيد المخزن. هل تريد المتابعة؟')">ترحيل الصرف</button></div>
</form></div></div>
@endif
@if(auth()->user()->hasPermission('inventory.transfers.create'))
<div class="col-lg-6"><div class="card card-outline card-info">
<div class="card-header"><h3 class="card-title">تحويل بين مخزنين</h3></div>
<form method="post" action="{{ route('admin.inventory.transfers.store') }}">@csrf
<input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}">
<div class="card-body">
<div class="form-group"><label>من مخزن</label><select name="source_warehouse_id" class="form-control" required><option value="">اختر المخزن المصدر</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->branch->company->name }} — {{ $warehouse->branch->name }} — {{ $warehouse->name }}</option>@endforeach</select></div>
<div class="form-group"><label>إلى مخزن</label><select name="destination_warehouse_id" class="form-control" required><option value="">اختر المخزن المستلم</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->branch->company->name }} — {{ $warehouse->branch->name }} — {{ $warehouse->name }}</option>@endforeach</select></div>
<div class="table-responsive"><table class="table table-bordered"><thead><tr><th>الصنف</th><th>الكمية</th><th>تكلفة الوحدة</th></tr></thead><tbody>
@for($i=0;$i<3;$i++)<tr>
<td><select name="items[{{ $i }}][product_variant_id]" class="form-control" @required($i===0)><option value="">اختر الصنف</option>@foreach($variants as $variant)<option value="{{ $variant->id }}">{{ $variant->product->name }} — {{ $variant->variant_name ?: $variant->sku }} ({{ $variant->sku }})</option>@endforeach</select></td>
<td><input type="number" name="items[{{ $i }}][quantity]" class="form-control" min="0.001" step="0.001" @required($i===0)></td>
<td><input type="number" name="items[{{ $i }}][unit_cost]" class="form-control" min="0" step="0.0001" value="0"></td>
</tr>@endfor
</tbody></table></div>
<div class="form-group"><label>ملاحظات</label><textarea name="notes" class="form-control" rows="2" maxlength="2000"></textarea></div>
</div><div class="card-footer text-left"><button class="btn btn-info" type="submit" onclick="return confirm('سيتم خصم الكمية من المصدر وإضافتها للمستلم في حركة واحدة. هل تريد المتابعة؟')">ترحيل التحويل</button></div>
</form></div></div>
@endif
</div>
<div class="card card-outline card-secondary"><div class="card-header"><h3 class="card-title">سجل مستندات الصرف والتحويل</h3></div>
<div class="card-body table-responsive"><table class="table table-bordered table-hover">
<thead><tr><th>رقم المستند</th><th>النوع</th><th>المخزن المصدر</th><th>المخزن المستلم</th><th>التاريخ</th><th>الأصناف</th><th>بواسطة</th><th>الحالة</th></tr></thead><tbody>
@forelse($movements as $movement)<tr>
<td>{{ $movement->document_no }}</td><td>{{ $movement->movement_type === 'issue' ? 'صرف' : 'تحويل' }}</td>
<td>{{ $movement->warehouse->name }}</td><td>{{ $movement->destinationWarehouse?->name ?? '—' }}</td>
<td>{{ $movement->movement_date?->format('Y-m-d H:i') }}</td><td>{{ $movement->items->count() }}</td><td>{{ $movement->creator?->name ?? '—' }}</td><td><span class="badge badge-success">مرحّل</span></td>
</tr>@empty<tr><td colspan="8" class="text-center text-muted py-4">لا توجد مستندات صرف أو تحويل حتى الآن.</td></tr>@endforelse
</tbody></table>{{ $movements->links() }}</div></div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('form[action*="/admin/inventory/issues"], form[action*="/admin/inventory/transfers"]').forEach(function (form) {
    form.addEventListener('submit', function () {
        form.querySelectorAll('tbody tr').forEach(function (row) {
            const select = row.querySelector('select[name$="[product_variant_id]"]');
            const quantity = row.querySelector('input[name$="[quantity]"]');
            if (select && quantity && !select.value && !quantity.value) row.remove();
        });
    });
});
</script>
@endpush
