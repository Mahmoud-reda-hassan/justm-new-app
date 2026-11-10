@extends('layouts.app')

@section('title', 'إدارة المستخدمين')
@section('page-title', 'إدارة المستخدمين')
@section('breadcrumb', 'إدارة المستخدمين')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <p class="text-muted mb-2">إدارة حسابات الدخول وربطها بالأطراف ومجموعات الصلاحيات ونطاقات البيانات.</p>
    <div class="mb-2">
        @if(auth()->user()->hasPermission('permissions.groups.view'))<a class="btn btn-default btn-sm" href="{{ route('admin.access.roles') }}">مجموعات الصلاحيات</a>@endif
        @if(auth()->user()->hasPermission('users.create'))<button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createUserModal"><i class="fas fa-user-plus ml-1"></i> إضافة مستخدم</button>@endif
    </div>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="card card-outline card-primary">
    <div class="card-header"><h3 class="card-title">دليل المستخدمين</h3><div class="card-tools"><span class="badge badge-secondary">{{ number_format($users->total()) }} مستخدم</span></div></div>
    <div class="card-body"><div class="table-responsive">
        <table class="table table-bordered table-hover table-striped">
            <thead><tr><th>المستخدم</th><th>الطرف المرتبط</th><th>الحالة</th><th>مجموعات الصلاحيات</th><th>نطاق البيانات</th><th>الإجراءات</th></tr></thead>
            <tbody>
            @forelse($users as $user)
                <tr>
                    <td><strong>{{ $user->name }}</strong><div class="text-muted small">{{ $user->username }}</div><div class="text-muted small">{{ $user->email ?: '—' }}</div></td>
                    <td>
                        {{ $user->party?->name ?? 'حساب داخلي' }}
                        @if($user->party)<div class="mt-1">@foreach($user->party->assignedRoles() as $role)<span class="badge badge-info">{{ $partyRoleLabels[$role] ?? $role }}</span>@endforeach</div>@endif
                    </td>
                    <td><span class="badge {{ $user->is_active ? 'badge-success' : 'badge-danger' }}">{{ $user->is_active ? 'نشط' : 'موقوف' }}</span></td>
                    <td>
                        @forelse($user->roles as $role)<span class="badge badge-secondary">{{ $role->name }}</span>@empty<span class="text-muted">لا توجد مجموعات</span>@endforelse
                    </td>
                    <td>
                        <span class="text-muted small">{{ $user->dataScopes->count() }} نطاق مباشر</span>
                        @if($user->dataScopes->isNotEmpty())
                            <button type="button" class="btn btn-link btn-sm p-0 d-block" data-toggle="collapse" data-target="#user-scopes-{{ $user->id }}">عرض التفاصيل</button>
                            <div class="collapse mt-1" id="user-scopes-{{ $user->id }}">
                                @foreach($user->dataScopes as $scope)
                                    <div class="small">{{ $scope->scope_type }} #{{ $scope->scope_id }}</div>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td><div class="btn-group btn-group-sm">
                        @if(auth()->user()->hasPermission('users.update') && $user->id !== auth()->id())<button class="btn btn-primary" title="تعديل" data-toggle="modal" data-target="#editUserModal{{ $user->id }}"><i class="fas fa-edit"></i></button>@endif
                        @if(auth()->user()->hasPermission('users.assign_groups') && $user->id !== auth()->id())<button class="btn btn-info" title="مجموعات الصلاحيات" data-toggle="modal" data-target="#userRolesModal{{ $user->id }}"><i class="fas fa-user-shield"></i></button>@endif
                        @if(auth()->user()->hasPermission('users.assign_scopes'))<button class="btn btn-secondary" title="نطاق البيانات" data-toggle="modal" data-target="#userScopesModal{{ $user->id }}"><i class="fas fa-sitemap"></i></button>@endif
                        @if(auth()->user()->hasPermission('users.reset_password') && $user->id !== auth()->id())<button class="btn btn-warning" title="إعادة تعيين كلمة المرور" data-toggle="modal" data-target="#userPasswordModal{{ $user->id }}"><i class="fas fa-key"></i></button>@endif
                        @if(auth()->user()->hasPermission('users.activate') && $user->id !== auth()->id())
                            <form method="post" action="{{ route('admin.access.users.active', $user) }}" onsubmit="return confirm('{{ $user->is_active ? 'هل تريد إيقاف الحساب؟' : 'هل تريد تفعيل الحساب؟' }}')">
                                @csrf @method('patch')<input type="hidden" name="is_active" value="{{ $user->is_active ? 0 : 1 }}">
                                <button class="btn {{ $user->is_active ? 'btn-danger' : 'btn-success' }}" title="{{ $user->is_active ? 'إيقاف' : 'تفعيل' }}"><i class="fas {{ $user->is_active ? 'fa-user-slash' : 'fa-user-check' }}"></i></button>
                            </form>
                        @endif
                    </div></td>
                </tr>
            @empty<tr><td colspan="6" class="text-center text-muted py-4">لا يوجد مستخدمون ضمن نطاق الوصول.</td></tr>@endforelse
            </tbody>
        </table>
    </div>{{ $users->links() }}</div>
</div>

@if(auth()->user()->hasPermission('users.create'))
<div class="modal fade" id="createUserModal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <form method="post" action="{{ route('admin.access.users.store') }}">@csrf
        <div class="modal-header"><h5 class="modal-title">إضافة مستخدم</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group col-md-6"><label>الاسم</label><input class="form-control" name="name" required maxlength="150"></div>
                <div class="form-group col-md-6"><label>اسم الدخول</label><input class="form-control" name="username" required maxlength="100"></div>
                <div class="form-group col-md-6"><label>البريد الإلكتروني</label><input class="form-control" type="email" name="email"></div>
                <div class="form-group col-md-6"><label>الطرف المرتبط</label><select class="form-control" name="party_id"><option value="">حساب داخلي غير مرتبط بطرف</option>@foreach($parties as $party)<option value="{{ $party->id }}" @selected((string)old('party_id', $selectedPartyId) === (string)$party->id)>{{ $party->name }}{{ $party->is_active ? '' : ' — موقوف' }}</option>@endforeach</select></div>
                <div class="form-group col-md-6"><label>كلمة المرور</label><input class="form-control" type="password" name="password" required minlength="12" autocomplete="new-password"></div>
                <div class="form-group col-md-6"><label>تأكيد كلمة المرور</label><input class="form-control" type="password" name="password_confirmation" required minlength="12" autocomplete="new-password"></div>
            </div>
            <div class="alert alert-info mb-0">يمكن ربط حساب العميل بالمتجر، أو ربطه بمسوق / وسيط أو مندوب. المورد وشركة الشحن لا يتحولان إلى حساب دخول تشغيلي لمجرد تسجيل الصفة. الصلاحيات تُسند بشكل منفصل من مجموعات الصلاحيات.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button><button class="btn btn-primary">إنشاء المستخدم</button></div>
    </form>
</div></div></div>
@endif

@foreach($users as $user)
    @if(auth()->user()->hasPermission('users.update') && $user->id !== auth()->id())
    <div class="modal fade" id="editUserModal{{ $user->id }}" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-lg" role="document"><div class="modal-content">
        <form method="post" action="{{ route('admin.access.users.update', $user) }}">@csrf @method('put')
            <div class="modal-header"><h5 class="modal-title">تعديل المستخدم: {{ $user->name }}</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
            <div class="modal-body"><div class="form-row">
                <div class="form-group col-md-6"><label>الاسم</label><input class="form-control" name="name" value="{{ $user->name }}" required></div>
                <div class="form-group col-md-6"><label>اسم الدخول</label><input class="form-control" name="username" value="{{ $user->username }}" required></div>
                <div class="form-group col-md-6"><label>البريد الإلكتروني</label><input class="form-control" type="email" name="email" value="{{ $user->email }}"></div>
                <div class="form-group col-md-6"><label>الطرف المرتبط</label><select class="form-control" name="party_id"><option value="">بدون طرف مرتبط</option>@foreach($parties as $party)<option value="{{ $party->id }}" @selected((string)$user->party_id === (string)$party->id)>{{ $party->name }}{{ $party->is_active ? '' : ' — موقوف' }}</option>@endforeach</select></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button><button class="btn btn-primary">حفظ التعديلات</button></div>
        </form>
    </div></div></div>
    @endif

    @if(auth()->user()->hasPermission('users.assign_groups') && $user->id !== auth()->id())
    <div class="modal fade" id="userRolesModal{{ $user->id }}" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-lg" role="document"><div class="modal-content">
        <form method="post" action="{{ route('admin.access.users.roles', $user) }}">@csrf @method('put')
            <div class="modal-header"><h5 class="modal-title">مجموعات صلاحيات: {{ $user->name }}</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
            <div class="modal-body"><div class="row">
                @foreach($roles as $role)
                    <div class="col-md-6"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="user-{{ $user->id }}-role-{{ $role->id }}" name="roles[]" value="{{ $role->id }}" @checked($user->roles->contains('id', $role->id))><label class="form-check-label" for="user-{{ $user->id }}-role-{{ $role->id }}">{{ $role->name }} @if(!$role->is_active)<span class="badge badge-danger">موقوفة</span>@endif</label></div></div>
                @endforeach
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button><button class="btn btn-primary">حفظ المجموعات</button></div>
        </form>
    </div></div></div>
    @endif

    @if(auth()->user()->hasPermission('users.assign_scopes'))
    <div class="modal fade" id="userScopesModal{{ $user->id }}" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-lg" role="document"><div class="modal-content">
        <form method="post" action="{{ route('admin.access.users.scopes', $user) }}">@csrf @method('put')
            <div class="modal-header"><h5 class="modal-title">نطاق البيانات: {{ $user->name }}</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
            <div class="modal-body">
                <div class="alert alert-info">نطاق الشركة يشمل فروعها ومخازنها، ونطاق الفرع يشمل مخازنه. لا تمنح صلاحية الشاشة وحدها وصولًا إلى كل البيانات.</div>
                <div class="form-row">
                    <div class="form-group col-md-4"><label>الشركات</label><select class="form-control" name="company_ids[]" multiple size="6">@foreach($companies as $company)<option value="{{ $company->id }}" @selected($user->dataScopes->contains(fn($scope) => $scope->scope_type === 'company' && $scope->scope_id == $company->id))>{{ $company->name }}</option>@endforeach</select></div>
                    <div class="form-group col-md-4"><label>الفروع</label><select class="form-control" name="branch_ids[]" multiple size="6">@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($user->dataScopes->contains(fn($scope) => $scope->scope_type === 'branch' && $scope->scope_id == $branch->id))>{{ $branch->company->name }} — {{ $branch->name }}</option>@endforeach</select></div>
                    <div class="form-group col-md-4"><label>المخازن</label><select class="form-control" name="warehouse_ids[]" multiple size="6">@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected($user->dataScopes->contains(fn($scope) => $scope->scope_type === 'warehouse' && $scope->scope_id == $warehouse->id))>{{ $warehouse->name }} — {{ $warehouse->branch->name }}</option>@endforeach</select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button><button class="btn btn-primary">حفظ النطاق</button></div>
        </form>
    </div></div></div>
    @endif

    @if(auth()->user()->hasPermission('users.reset_password') && $user->id !== auth()->id())
    <div class="modal fade" id="userPasswordModal{{ $user->id }}" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog" role="document"><div class="modal-content">
        <form method="post" action="{{ route('admin.access.users.password', $user) }}">@csrf @method('put')
            <div class="modal-header"><h5 class="modal-title">إعادة تعيين كلمة المرور: {{ $user->name }}</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
            <div class="modal-body">
                <div class="form-group"><label>كلمة المرور الجديدة</label><input class="form-control" type="password" name="password" required minlength="12" autocomplete="new-password"></div>
                <div class="form-group"><label>تأكيد كلمة المرور</label><input class="form-control" type="password" name="password_confirmation" required minlength="12" autocomplete="new-password"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button><button class="btn btn-warning">حفظ كلمة المرور</button></div>
        </form>
    </div></div></div>
    @endif
@endforeach
@endsection
