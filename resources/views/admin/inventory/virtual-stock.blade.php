@extends('layouts.app')

@section('title', 'المخازن الوهمية')
@section('page-title', 'المخازن الوهمية')
@section('breadcrumb', 'المخزون / المخازن الوهمية')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title">اختيار المخزن الوهمي</h3>
            </div>
            <div class="card-body pb-2">
                <div class="nav nav-pills flex-wrap virtual-stock-tabs">
                    @foreach($types as $key => $label)
                        <a class="nav-link mb-2 ml-2 @if($type === $key) active @endif"
                           href="{{ route('admin.inventory.virtual-stock', ['type' => $key, 'warehouse_id' => $warehouse?->id]) }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
                <div class="alert alert-info mb-0">
                    الكميات هنا محسوبة من رصيد المخزن الحقيقي مطروحًا منه كميات حجوزات أوامر البيع الواقعة في الحالات المحددة لهذا المخزن الوهمي. العرض لا ينشئ رصيدًا فعليًا ولا حركة مخزنية.
                </div>
            </div>
        </div>

        <div class="card card-outline card-secondary">
            <form method="GET" action="{{ route('admin.inventory.virtual-stock') }}">
                <input type="hidden" name="type" value="{{ $type }}">
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-lg-4 col-md-6 form-group">
                            <label for="warehouse_id">المخزن الحقيقي</label>
                            <select class="form-control" name="warehouse_id" id="warehouse_id" required>
                                @foreach($warehouses as $item)
                                    <option value="{{ $item->id }}" @selected($warehouse?->id === $item->id)>
                                        {{ $item->name }} — {{ $item->branch?->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @if($type === 'customer_service')
                            <div class="col-lg-3 col-md-6 form-group">
                                <label for="status">حالة أمر البيع المفتوح</label>
                                <select class="form-control" name="status" id="status">
                                    @foreach($statusOptions as $key => $label)
                                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="col-lg-3 col-md-6 form-group">
                            <label for="q">بحث عن منتج أو كود</label>
                            <input class="form-control" type="search" name="q" id="q" value="{{ $search }}" placeholder="اسم المنتج أو SKU">
                        </div>
                        <div class="col-lg-2 col-md-6 form-group">
                            <button class="btn btn-primary btn-block" type="submit">عرض المخزون</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        @if($type === 'customer_service')
            <div class="alert alert-secondary">
                حالة الشاشة المختارة: <strong>{{ $statusOptions[$status] }}</strong>.
                الحالات التي تخصم من المتاح وفق المصفوفة:
                @forelse($countedStatuses as $counted)
                    <span class="badge badge-info">{{ $statusOptions[$counted] ?? $counted }}</span>
                @empty
                    <strong>لا توجد حالات مفعّلة للخصم.</strong>
                @endforelse
                <div class="small mt-1">عند فتح أمر قائم، يستثنى هذا الأمر وحده من الحساب التشغيلي لذلك الطلب؛ الشاشة العامة تعرض إجمالي الحجز النشط.</div>
            </div>
        @else
            <div class="alert alert-secondary">
                الحالات التي تخصم من المتاح:
                @forelse($countedStatuses as $counted)
                    <span class="badge badge-info">{{ $statusOptions[$counted] ?? $counted }}</span>
                @empty
                    <strong>لا توجد حالات مفعّلة للخصم.</strong>
                @endforelse
            </div>
        @endif

        <div class="row">
            <div class="col-md-4">
                <div class="small-box bg-light">
                    <div class="inner"><h3>{{ number_format($totals['on_hand'], 3) }}</h3><p>إجمالي المخزون الحقيقي</p></div>
                    <div class="icon"><i class="fas fa-boxes"></i></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="small-box bg-light">
                    <div class="inner"><h3>{{ number_format($totals['reserved'], 3) }}</h3><p>إجمالي الكميات المحسوبة</p></div>
                    <div class="icon"><i class="fas fa-lock"></i></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="small-box bg-light">
                    <div class="inner"><h3>{{ number_format($totals['available'], 3) }}</h3><p>إجمالي المتاح في المخزن الوهمي</p></div>
                    <div class="icon"><i class="fas fa-box-open"></i></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">كميات المنتجات — {{ $types[$type] }}</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-bordered table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>المنتج</th>
                            <th>SKU</th>
                            <th>المواصفة</th>
                            <th>المخزون الحقيقي</th>
                            <th>المحجوز حسب الحالات</th>
                            <th>المتاح الوهمي</th>
                            <th>تفصيل الخصم</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['variant']->product?->name }}</td>
                                <td dir="ltr" class="text-right">{{ $row['variant']->sku }}</td>
                                <td>{{ $row['variant']->variant_name ?: '—' }}</td>
                                <td>{{ number_format($row['on_hand'], 3) }}</td>
                                <td>{{ number_format($row['reserved'], 3) }}</td>
                                <td class="font-weight-bold text-success">{{ number_format($row['available'], 3) }}</td>
                                <td>
                                    @forelse($row['breakdown'] as $state => $quantity)
                                        @if($quantity > 0)
                                            <div class="small">{{ $statusOptions[$state] ?? $state }}: {{ number_format($quantity, 3) }}</div>
                                        @endif
                                    @empty
                                        <span class="text-muted">لا يوجد حجز</span>
                                    @endforelse
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted p-4">لا توجد منتجات مطابقة أو لم يتم اختيار مخزن حقيقي.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-muted small">
                المتاح الوهمي لكل منتج = المخزون الحقيقي − مجموع الكميات المحجوزة في الحالات المحددة، وبحد أدنى صفر.
            </div>
        </div>
    </div>
</div>
@endsection
