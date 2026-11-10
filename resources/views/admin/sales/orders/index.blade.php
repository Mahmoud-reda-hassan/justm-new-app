@extends('layouts.app')

@section('title', 'أوامر البيع')
@section('page-title', 'أوامر البيع')
@section('breadcrumb', 'أوامر البيع')

@section('content')
<header class="head"><div><h1>أوامر البيع</h1><div class="muted">الأوامر المحفوظة كمسودات — لا يوجد حجز مخزون أو ترحيل مالي في هذه المرحلة.</div></div>@if(auth()->user()->hasPermission('sales.orders.create'))<a class="btn" href="{{ route('admin.sales.orders.create') }}">إنشاء أمر بيع جديد</a>@endif</header>
@if(session('status'))<div class="alert">{{ session('status') }}</div>@endif
<section class="panel"><table><thead><tr><th>رقم الأمر</th><th>العميل</th><th>منشئ الأمر</th><th>الإجمالي</th><th>الحالة</th><th>التاريخ</th></tr></thead><tbody>
@forelse($orders as $order)
<tr><td><strong>{{ $order->order_number }}</strong></td><td>{{ $order->customer?->name }}</td><td>{{ $order->creatorParty?->name ?? $order->creator?->name }}</td><td>{{ number_format((float) $order->total, 2) }}</td><td><span class="status">مسودة</span></td><td>{{ $order->created_at?->format('Y-m-d H:i') }}</td></tr>
@empty<tr><td colspan="6" class="muted">لا توجد أوامر بيع حتى الآن.</td></tr>@endforelse
</tbody></table><div>{{ $orders->links() }}</div></section>
@endsection
