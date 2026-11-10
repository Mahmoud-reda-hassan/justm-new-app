<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>أوامر البيع | Just-M</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f6fb;color:#17233b;font:14px/1.8 Tahoma,Arial,sans-serif}.shell{max-width:1250px;margin:auto;padding:24px}.head{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:20px}.head h1{margin:0;font-size:26px}.muted{color:#718096;font-size:12px}.btn{display:inline-block;border:0;border-radius:9px;padding:10px 15px;background:#3157d5;color:#fff;text-decoration:none;font-weight:bold}.panel{background:#fff;border:1px solid #e7ebf2;border-radius:14px;padding:18px;overflow:auto}.alert{padding:10px 14px;background:#eaf8f1;color:#146a49;border-radius:9px;margin-bottom:14px}table{width:100%;border-collapse:collapse;min-width:650px}th,td{text-align:right;padding:11px;border-bottom:1px solid #edf0f5}th{color:#718096;font-size:12px}small{color:#718096}.status{display:inline-block;border-radius:20px;padding:3px 9px;background:#fff5df;color:#8a5b00;font-size:11px}
</style></head>
<body><main class="shell">
<header class="head"><div><h1>أوامر البيع</h1><div class="muted">الأوامر المحفوظة كمسودات — لا يوجد حجز مخزون أو ترحيل مالي في هذه المرحلة.</div></div>@if(auth()->user()->hasPermission('sales.orders.create'))<a class="btn" href="{{ route('admin.sales.orders.create') }}">إنشاء أمر بيع جديد</a>@endif</header>
@if(session('status'))<div class="alert">{{ session('status') }}</div>@endif
<section class="panel"><table><thead><tr><th>رقم الأمر</th><th>العميل</th><th>منشئ الأمر</th><th>الإجمالي</th><th>الحالة</th><th>التاريخ</th></tr></thead><tbody>
@forelse($orders as $order)
<tr><td><strong>{{ $order->order_number }}</strong></td><td>{{ $order->customer?->name }}</td><td>{{ $order->creatorParty?->name ?? $order->creator?->name }}</td><td>{{ number_format((float) $order->total, 2) }}</td><td><span class="status">مسودة</span></td><td>{{ $order->created_at?->format('Y-m-d H:i') }}</td></tr>
@empty<tr><td colspan="6" class="muted">لا توجد أوامر بيع حتى الآن.</td></tr>@endforelse
</tbody></table><div>{{ $orders->links() }}</div></section>
</main></body></html>
