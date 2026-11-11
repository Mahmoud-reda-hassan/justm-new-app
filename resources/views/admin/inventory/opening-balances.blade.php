@extends('layouts.app')

@section('title', 'الأرصدة الافتتاحية')
@section('page-title', 'الأرصدة الافتتاحية')
@section('breadcrumb', 'المخزون / الأرصدة الافتتاحية')

@section('content')
<div class="mb-3">
    <p class="text-muted mb-1">تسجيل الكميات الفعلية عند بدء تشغيل المخزون الجديد. لا يتم استيراد أي رصيد من النظام القديم.</p>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

@if(auth()->user()->hasPermission('inventory.opening_balances.create'))
<div class="card card-outline card-primary">
    <div class="card-header"><h3 class="card-title">مستند رصيد افتتاحي جديد</h3></div>
    <form method="post" action="{{ route('admin.inventory.opening-balances.store') }}" id="openingBalanceForm">
        @csrf
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="warehouse_id">المخزن</label>
                    <select class="form-control" name="warehouse_id" id="warehouse_id" required>
                        <option value="">اختر المخزن</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->branch->company->name }} — {{ $warehouse->branch->name }} — {{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label for="opening_notes">ملاحظات</label>
                    <input class="form-control" name="notes" id="opening_notes" value="{{ old('notes') }}" maxlength="2000" placeholder="سبب أو تفاصيل الجرد الافتتاحي">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered" id="openingItemsTable">
                    <thead><tr><th style="min-width:260px">الصنف / الكود</th><th style="width:160px">الكمية الفعلية</th><th style="width:180px">تكلفة الوحدة (اختياري)</th><th style="width:70px">حذف</th></tr></thead>
                    <tbody id="openingItemsBody">
                        <tr class="opening-item-row">
                            <td><select class="form-control variant-select" name="items[0][product_variant_id]" required><option value="">اختر الصنف</option>@foreach($variants as $variant)<option value="{{ $variant->id }}">{{ $variant->product->name }} — {{ $variant->variant_name ?: $variant->sku }} ({{ $variant->sku }})</option>@endforeach</select></td>
                            <td><input class="form-control" type="number" name="items[0][quantity]" min="0.001" step="0.001" required></td>
                            <td><input class="form-control" type="number" name="items[0][unit_cost]" min="0" step="0.0001" placeholder="0.0000"></td>
                            <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm remove-opening-row" disabled><i class="fas fa-trash"></i></button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-default btn-sm" id="addOpeningRow"><i class="fas fa-plus ml-1"></i> إضافة صنف</button>
        </div>
        <div class="card-footer text-left">
            <button type="submit" class="btn btn-primary" onclick="return confirm('سيتم ترحيل الكميات إلى أرصدة المخزن ولا يمكن تعديل المستند بعد الترحيل. هل تريد المتابعة؟')">حفظ وترحيل الرصيد الافتتاحي</button>
        </div>
    </form>
</div>
@endif

<div class="card card-outline card-secondary">
    <div class="card-header"><h3 class="card-title">مستندات الأرصدة الافتتاحية</h3></div>
    <div class="card-body table-responsive">
        <table class="table table-bordered table-hover">
            <thead><tr><th>رقم المستند</th><th>المخزن</th><th>التاريخ</th><th>عدد الأصناف</th><th>بواسطة</th><th>الحالة</th></tr></thead>
            <tbody>
            @forelse($movements as $movement)
                <tr>
                    <td>{{ $movement->document_no }}</td>
                    <td>{{ $movement->warehouse->name }}</td>
                    <td>{{ $movement->movement_date?->format('Y-m-d H:i') }}</td>
                    <td>{{ number_format($movement->items_count) }}</td>
                    <td>{{ $movement->creator->name }}</td>
                    <td><span class="badge badge-success">مرحّل</span></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">لا توجد مستندات أرصدة افتتاحية حتى الآن.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $movements->links() }}
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const body = document.getElementById('openingItemsBody');
    const addButton = document.getElementById('addOpeningRow');
    if (!body || !addButton) return;
    let rowIndex = 1;
    const options = @json($variants->map(fn ($variant) => ['id' => $variant->id, 'label' => $variant->product->name.' — '.($variant->variant_name ?: $variant->sku).' ('.$variant->sku.')'])->values());

    function refreshRemoveButtons() {
        const rows = body.querySelectorAll('.opening-item-row');
        rows.forEach(row => {
            const button = row.querySelector('.remove-opening-row');
            if (button) button.disabled = rows.length === 1;
        });
    }

    addButton.addEventListener('click', function () {
        const row = document.createElement('tr');
        row.className = 'opening-item-row';
        const select = document.createElement('select');
        select.className = 'form-control variant-select';
        select.name = 'items[' + rowIndex + '][product_variant_id]';
        select.required = true;
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = 'اختر الصنف';
        select.appendChild(empty);
        options.forEach(option => {
            const item = document.createElement('option');
            item.value = option.id;
            item.textContent = option.label;
            select.appendChild(item);
        });
        row.innerHTML = '<td></td><td><input class="form-control" type="number" min="0.001" step="0.001" required></td><td><input class="form-control" type="number" min="0" step="0.0001" placeholder="0.0000"></td><td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm remove-opening-row"><i class="fas fa-trash"></i></button></td>';
        row.cells[0].appendChild(select);
        row.cells[1].querySelector('input').name = 'items[' + rowIndex + '][quantity]';
        row.cells[2].querySelector('input').name = 'items[' + rowIndex + '][unit_cost]';
        body.appendChild(row);
        rowIndex++;
        refreshRemoveButtons();
    });

    body.addEventListener('click', function (event) {
        const button = event.target.closest('.remove-opening-row');
        if (!button) return;
        button.closest('tr').remove();
        refreshRemoveButtons();
    });
})();
</script>
@endpush
