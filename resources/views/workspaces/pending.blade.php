@extends('layouts.app')

@section('title', $workspace['title'])
@section('page-title', $workspace['title'])
@section('breadcrumb', 'مساحة العمل')

@section('content')
<div class="card">
    <div class="card-body text-center py-5">
        <div class="mb-3 text-primary" style="font-size:42px"><i class="{{ $workspace['icon'] }}" aria-hidden="true"></i></div>
        <h3 class="h5 mb-3">{{ $workspace['title'] }}</h3>
        <p class="text-muted mb-4">{{ $workspace['description'] }}</p>
        <div class="alert alert-info mx-auto" style="max-width:620px">
            تم التحقق من دورك بنجاح. لوحة العمل الخاصة بهذا الدور لم تُنفذ بعد، لذلك لم يتم تحويلك إلى لوحة أخرى أو عرض بيانات غير مخصصة لك.
        </div>
        <a href="{{ route('workspaces.index') }}" class="btn btn-outline-primary">العودة لاختيار الوظيفة</a>
    </div>
</div>
@endsection
