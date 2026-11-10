@extends('layouts.app')

@section('title', 'مجموعات الصلاحيات')
@section('page-title', 'مجموعات الصلاحيات')
@section('breadcrumb', 'مجموعات الصلاحيات')

@section('content')
<h1>مجموعات الصلاحيات</h1>
    <p><a href="{{ route('admin.access.users') }}">إدارة المستخدمين</a> | <a href="{{ route('dashboard') }}">الرئيسية</a></p>
    @if (session('status')) <div class="status">{{ session('status') }}</div> @endif
    @if ($errors->any()) <section><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section> @endif
    <section><h2>إنشاء مجموعة</h2>
        <form method="post" action="{{ route('admin.access.roles.store') }}">@csrf
            <input name="name" placeholder="اسم المجموعة" required maxlength="100">
            <input name="key" placeholder="مفتاح ثابت بالإنجليزية" required maxlength="100">
            <textarea name="description" placeholder="الوصف"></textarea>
            <button>إنشاء المجموعة</button>
        </form>
    </section>
    @foreach ($roles as $role)
        <section>
            <h2>{{ $role->name }} <small>({{ $role->key }})</small></h2>
            <form method="post" action="{{ route('admin.access.roles.update', $role) }}">@csrf @method('put')
                <input name="name" value="{{ $role->name }}" required>
                <input name="description" value="{{ $role->description }}">
                <input type="hidden" name="is_active" value="0">
                <label><input type="checkbox" name="is_active" value="1" @checked($role->is_active)> نشطة</label>
                <button>حفظ بيانات المجموعة</button>
            </form>
            <form method="post" action="{{ route('admin.access.roles.permissions', $role) }}">@csrf @method('put')
                <h3>الصلاحيات المتاحة</h3>
                @if ($currentUserRoleIds->contains($role->id) && $role->key === 'system-access-admin')
                    <div class="notice">
                        يمكنك إضافة صلاحيات التشغيل لمجموعتك. صلاحيات إدارة المستخدمين والوصول الأساسية محمية ولا يمكن حذفها أو إضافة صلاحيات إدارية حساسة جديدة من هذه المجموعة.
                    </div>
                @endif
                @foreach ($permissions->groupBy('module') as $module => $items)
                    <div class="module"><strong>{{ $module }}</strong><br>
                    @foreach ($items as $permission)
                        @php($protectedForCurrentAdmin = $currentUserRoleIds->contains($role->id) && $role->key === 'system-access-admin' && $protectedPermissionIds->contains($permission->id))
                        <label><input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->permissions->contains('id', $permission->id)) @disabled($protectedForCurrentAdmin)>
                            {{ $permission->key }} @if ($protectedForCurrentAdmin) (محمية) @endif</label><br>
                    @endforeach
                    </div>
                @endforeach
                <button>حفظ الصلاحيات</button>
            </form>
        </section>
    @endforeach
    {{ $roles->links() }}
@endsection
