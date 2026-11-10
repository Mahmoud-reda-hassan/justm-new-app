@extends('layouts.app')

@section('title', 'مجموعات الصلاحيات')
@section('page-title', 'مجموعات الصلاحيات')
@section('breadcrumb', 'مجموعات الصلاحيات')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <p class="text-muted mb-2">أنشئ مجموعات الصلاحيات وحدد مفاتيح الوصول الممنوحة لكل مجموعة.</p>
    <a class="btn btn-default btn-sm mb-2" href="{{ route('admin.access.users') }}"><i class="fas fa-users ml-1"></i> إدارة المستخدمين</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

@if(auth()->user()->hasPermission('permissions.groups.create'))
<div class="card card-outline card-primary">
    <div class="card-header"><h3 class="card-title">إنشاء مجموعة صلاحيات</h3></div>
    <form method="post" action="{{ route('admin.access.roles.store') }}">@csrf
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-4"><label>اسم المجموعة</label><input class="form-control" name="name" placeholder="مثال: مسؤول المخزن" required maxlength="100"></div>
                <div class="form-group col-md-4"><label>مفتاح ثابت بالإنجليزية</label><input class="form-control" name="key" placeholder="warehouse-operator" required maxlength="100"></div>
                <div class="form-group col-md-4"><label>الوصف</label><input class="form-control" name="description" placeholder="وصف مختصر للمجموعة"></div>
            </div>
        </div>
        <div class="card-footer"><button class="btn btn-primary" type="submit"><i class="fas fa-plus ml-1"></i> إنشاء المجموعة</button></div>
    </form>
</div>
@endif

@forelse($roles as $role)
    <div class="card card-outline card-secondary">
        <div class="card-header">
            <h3 class="card-title">{{ $role->name }} <small class="text-muted">({{ $role->key }})</small></h3>
            <div class="card-tools"><span class="badge {{ $role->is_active ? 'badge-success' : 'badge-danger' }}">{{ $role->is_active ? 'نشطة' : 'موقوفة' }}</span></div>
        </div>
        <div class="card-body">
            @if(auth()->user()->hasPermission('permissions.groups.update'))
                <form method="post" action="{{ route('admin.access.roles.update', $role) }}" class="border rounded p-3 mb-3">
                    @csrf @method('put')
                    <h5 class="mb-3">بيانات المجموعة</h5>
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-4"><label>اسم المجموعة</label><input class="form-control" name="name" value="{{ $role->name }}" required maxlength="100"></div>
                        <div class="form-group col-md-5"><label>الوصف</label><input class="form-control" name="description" value="{{ $role->description }}"></div>
                        <div class="form-group col-md-2"><div class="form-check"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" id="role-active-{{ $role->id }}" name="is_active" value="1" @checked($role->is_active)><label class="form-check-label" for="role-active-{{ $role->id }}">مجموعة نشطة</label></div></div>
                        <div class="form-group col-md-1"><button class="btn btn-primary btn-block" type="submit">حفظ</button></div>
                    </div>
                </form>
            @endif

            @if(auth()->user()->hasPermission('permissions.groups.assign'))
                <form method="post" action="{{ route('admin.access.roles.permissions', $role) }}">
                    @csrf @method('put')
                    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                        <h5 class="mb-2">الصلاحيات المتاحة</h5>
                        <span class="text-muted small">حدد الصلاحيات المطلوبة فقط؛ الصفة الوظيفية لا تمنح صلاحيات تلقائية.</span>
                    </div>
                    @if($currentUserRoleIds->contains($role->id) && $role->key === 'system-access-admin')
                        <div class="alert alert-info">
                            يمكنك إضافة صلاحيات التشغيل لمجموعتك. صلاحيات إدارة المستخدمين والوصول الأساسية محمية ولا يمكن حذفها أو إضافة صلاحيات إدارية حساسة جديدة من هذه المجموعة.
                        </div>
                    @endif
                    <div class="row">
                        @foreach($permissions->groupBy('module') as $module => $items)
                            <div class="col-xl-4 col-md-6">
                                <div class="border rounded p-3 mb-3 h-100">
                                    <h6 class="font-weight-bold border-bottom pb-2">{{ $module }}</h6>
                                    @foreach($items as $permission)
                                        @php($protectedForCurrentAdmin = $currentUserRoleIds->contains($role->id) && $role->key === 'system-access-admin' && $protectedPermissionIds->contains($permission->id))
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="role-{{ $role->id }}-permission-{{ $permission->id }}" name="permissions[]" value="{{ $permission->id }}" @checked($role->permissions->contains('id', $permission->id)) @disabled($protectedForCurrentAdmin)>
                                            <label class="form-check-label" for="role-{{ $role->id }}-permission-{{ $permission->id }}">
                                                {{ $permission->key }} @if($protectedForCurrentAdmin)<span class="badge badge-warning">محمية</span>@endif
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fas fa-save ml-1"></i> حفظ صلاحيات المجموعة</button>
                </form>
            @endif
        </div>
    </div>
@empty
    <div class="card"><div class="card-body text-center text-muted">لا توجد مجموعات صلاحيات.</div></div>
@endforelse
<div class="mt-3">{{ $roles->links() }}</div>
@endsection
