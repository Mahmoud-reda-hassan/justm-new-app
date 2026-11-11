@extends('layouts.app')

@section('title', 'إعدادات المخازن العامة')
@section('page-title', 'إعدادات المخازن العامة')
@section('breadcrumb', 'إعدادات المخازن')

@section('content')
<div class="row"><div class="col-lg-8">
    <div class="card card-outline card-primary">
        <div class="card-header"><h3 class="card-title">سياسة الرصيد السالب</h3></div>
        <form method="POST" action="{{ route('admin.inventory.settings.update') }}">
            @csrf @method('PUT')
            <div class="card-body">
                <div class="form-group">
                    <label>السماح بالرصيد السالب</label>
                    <select name="allow_negative_stock" class="form-control" required>
                        <option value="0" @selected(! $allowNegativeStock)>منع الرصيد السالب (الافتراضي)</option>
                        <option value="1" @selected($allowNegativeStock)>السماح بالرصيد السالب</option>
                    </select>
                    @error('allow_negative_stock')<span class="text-danger">{{ $message }}</span>@enderror
                </div>
                <div class="alert alert-info mb-0">إعداد عام يطبق على كل المخازن وعلى الحركات التي تخفض الرصيد الفعلي مثل الصرف والتحويل والتسوية السالبة. لا يمنح صلاحيات تنفيذ الحركات ولا يغير الحجوزات.</div>
                @if($updatedAt)<small class="text-muted d-block mt-3">آخر تحديث: {{ $updatedAt->format('Y-m-d H:i') }} @if($updatedBy) بواسطة {{ $updatedBy }} @endif</small>@endif
            </div>
            <div class="card-footer"><button type="submit" class="btn btn-primary">حفظ الإعدادات</button></div>
        </form>
    </div>
</div></div>
@endsection
