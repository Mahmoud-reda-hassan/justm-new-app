@extends('layouts.app')

@section('title', 'أوامر البيع')
@section('page-title', 'أوامر البيع')
@section('breadcrumb', 'أوامر البيع')

@section('content')
@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('status') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
    </div>
@endif

<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title">قائمة أوامر البيع</h3>
        <div class="card-tools">
            @if(auth()->user()->hasPermission('sales.orders.create'))
                <a class="btn btn-primary btn-sm" href="{{ route('admin.sales.orders.create') }}">
                    <i class="fas fa-plus ml-1"></i> إنشاء أمر بيع
                </a>
            @endif
        </div>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle ml-1"></i>
            تعرض هذه المرحلة المسودات المحفوظة فقط؛ لا يتم حجز المخزون أو ترحيل أثر مالي عند إنشاء المسودة.
        </div>
        <form method="get" class="form-row mb-3">
            <div class="col-md-6 mb-2"><input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="ابحث برقم الأمر أو اسم العميل أو منشئ الأمر"></div>
            <div class="col-auto mb-2"><button class="btn btn-default" type="submit"><i class="fas fa-search ml-1"></i> بحث</button> <a class="btn btn-outline-secondary" href="{{ route('admin.sales.orders.index') }}">مسح</a></div>
        </form>
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped">
                <thead>
                    <tr>
                        <th>رقم الأمر</th>
                        <th>العميل</th>
                        <th>منشئ الأمر</th>
                        <th>الإجمالي</th>
                        <th>الحالة</th>
                        <th>تاريخ الإنشاء</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td><strong>{{ $order->order_number }}</strong></td>
                        <td>{{ $order->customer?->name ?: '—' }}</td>
                        <td>{{ $order->creatorParty?->name ?? $order->creator?->name ?? '—' }}</td>
                        <td class="text-nowrap">{{ number_format((float) $order->total, 2) }}</td>
                        <td><span class="badge badge-secondary">مسودة</span></td>
                        <td class="text-nowrap">{{ $order->created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">لا توجد أوامر بيع محفوظة حتى الآن.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $orders->links() }}</div>
    </div>
</div>
@endsection
