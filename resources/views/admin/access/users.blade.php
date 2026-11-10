@extends('layouts.app')

@section('title', 'إدارة المستخدمين')
@section('page-title', 'إدارة المستخدمين')
@section('breadcrumb', 'إدارة المستخدمين')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
    <p class="text-muted mb-2">استعرض الحسابات وابحث عنها حسب الاسم أو الصفة أو مجموعة الصلاحيات، ثم افتح ملف الحساب لإدارته.</p>
    <div class="mb-2">
        @if(auth()->user()->hasPermission('permissions.groups.view'))<a class="btn btn-default btn-sm" href="{{ route('admin.access.roles') }}">مجموعات الصلاحيات</a>@endif
        @if(auth()->user()->hasPermission('users.create'))<button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createUserModal"><i class="fas fa-user-plus ml-1"></i> إضافة مستخدم</button>@endif
    </div>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="card card-solid card-primary">
    <div class="card-header">
        <h3 class="card-title">دليل المستخدمين</h3>
        <div class="card-tools"><span class="badge badge-light">{{ number_format($users->total()) }} مستخدم</span></div>
    </div>
    <div class="card-body pb-0">
        <form method="get" action="{{ route('admin.access.users') }}" class="border rounded p-3 mb-3">
            <div class="form-row align-items-end">
                <div class="form-group col-lg-4 col-md-6">
                    <label for="user-search">بحث</label>
                    <input id="user-search" class="form-control" type="search" name="q" value="{{ $filters['q'] }}" placeholder="الاسم أو اسم الدخول أو البريد أو الهاتف">
                </div>
                <div class="form-group col-lg-2 col-md-6">
                    <label for="user-party-role">صفة الطرف</label>
                    <select id="user-party-role" class="form-control" name="party_role">
                        <option value="">كل الصفات</option>
                        @foreach($partyRoles as $key => $label)
                            <option value="{{ $key }}" @selected($filters['party_role'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-lg-2 col-md-4">
                    <label for="user-account">نوع الارتباط</label>
                    <select id="user-account" class="form-control" name="account">
                        <option value="">الكل</option>
                        <option value="internal" @selected($filters['account'] === 'internal')>حساب داخلي</option>
                        <option value="linked" @selected($filters['account'] === 'linked')>مرتبط بطرف</option>
                    </select>
                </div>
                <div class="form-group col-lg-2 col-md-4">
                    <label for="user-status">الحالة</label>
                    <select id="user-status" class="form-control" name="status">
                        <option value="">كل الحالات</option>
                        <option value="active" @selected($filters['status'] === 'active')>نشط</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>موقوف</option>
                    </select>
                </div>
                <div class="form-group col-lg-2 col-md-4">
                    <label for="user-role">مجموعة الصلاحيات</label>
                    <select id="user-role" class="form-control" name="role_id">
                        <option value="">كل المجموعات</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" @selected((string)$filters['role_id'] === (string)$role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-12 mb-0">
                    <button class="btn btn-primary"><i class="fas fa-search ml-1"></i> بحث</button>
                    <a class="btn btn-default" href="{{ route('admin.access.users') }}">مسح الفلاتر</a>
                </div>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-muted small">النتائج المعروضة: {{ number_format($users->total()) }}</span>
            <span class="text-muted small">الحساب لا يحصل على صلاحية تشغيل أو لوحة عمل إلا من خلال الإسناد الصريح.</span>
        </div>
        <div class="row" id="userView">
            @forelse($users as $user)
                @php($assignedPartyRoles = $user->party?->assignedRoles() ?? [])
                <div class="col-xl-4 col-md-6 d-flex">
                    <div class="card card-outline card-secondary w-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="font-weight-bold">{{ $user->party ? 'حساب مرتبط بطرف' : 'حساب داخلي' }}</span>
                            <span class="badge {{ $user->is_active ? 'badge-success' : 'badge-danger' }}">{{ $user->is_active ? 'نشط' : 'موقوف' }}</span>
                        </div>
                        <div class="card-body">
                            <h5 class="mb-1">{{ $user->name }}</h5>
                            <div class="text-muted mb-3">{{ '@'.$user->username }}</div>
                            @if($user->party)
                                <div class="mb-2"><i class="fas fa-address-card text-muted ml-1"></i><strong>{{ $user->party->name }}</strong></div>
                                <div class="mb-2">
                                    @foreach($assignedPartyRoles as $partyRole)
                                        <span class="badge badge-info">{{ $partyRoleLabels[$partyRole] ?? $partyRole }}</span>
                                    @endforeach
                                </div>
                                @if(auth()->user()->hasPermission('users.view_details'))
                                    @if($user->party->phone || $user->party->mobile)<div class="small text-muted mb-1"><i class="fas fa-phone ml-1"></i>{{ $user->party->phone }} {{ $user->party->mobile }}</div>@endif
                                    @if($user->party->address)<div class="small text-muted mb-1"><i class="fas fa-map-marker-alt ml-1"></i>{{ $user->party->address }}</div>@endif
                                @endif
                            @else
                                <div class="text-muted mb-2">غير مرتبط بصفة طرف.</div>
                            @endif
                            @if(auth()->user()->hasPermission('users.view_details') && $user->email)
                                <div class="small text-muted mb-2"><i class="fas fa-envelope ml-1"></i>{{ $user->email }}</div>
                            @endif
                            <hr>
                            <div class="small mb-2"><strong>مجموعات الصلاحيات:</strong>
                                @forelse($user->roles as $role)<span class="badge badge-secondary">{{ $role->name }}</span>@empty<span class="text-muted">لا توجد</span>@endforelse
                            </div>
                            <div class="small text-muted"><i class="fas fa-sitemap ml-1"></i> {{ $user->dataScopes->count() }} نطاق بيانات مباشر</div>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex flex-wrap">
                                @if(auth()->user()->hasPermission('users.update') && $user->id !== auth()->id())<button class="btn btn-sm btn-primary ml-1 mb-1" data-toggle="modal" data-target="#editUserModal{{ $user->id }}"><i class="fas fa-edit ml-1"></i> تعديل</button>@endif
                                @if(auth()->user()->hasPermission('users.assign_groups') && $user->id !== auth()->id())<button class="btn btn-sm btn-info ml-1 mb-1" data-toggle="modal" data-target="#userRolesModal{{ $user->id }}"><i class="fas fa-user-shield ml-1"></i> المجموعات</button>@endif
                                @if(auth()->user()->hasPermission('users.assign_scopes'))<button class="btn btn-sm btn-secondary ml-1 mb-1" data-toggle="modal" data-target="#userScopesModal{{ $user->id }}"><i class="fas fa-sitemap ml-1"></i> النطاق</button>@endif
                                @if(auth()->user()->hasPermission('users.reset_password') && $user->id !== auth()->id())<button class="btn btn-sm btn-warning ml-1 mb-1" data-toggle="modal" data-target="#userPasswordModal{{ $user->id }}"><i class="fas fa-key ml-1"></i> كلمة المرور</button>@endif
                                @if(auth()->user()->hasPermission('users.activate') && $user->id !== auth()->id())
                                    <form method="post" action="{{ route('admin.access.users.active', $user) }}" onsubmit="return confirm('{{ $user->is_active ? 'هل تريد إيقاف الحساب؟' : 'هل تريد تفعيل الحساب؟' }}')" class="mb-1">
                                        @csrf @method('patch')<input type="hidden" name="is_active" value="{{ $user->is_active ? 0 : 1 }}">
                                        <button class="btn btn-sm {{ $user->is_active ? 'btn-danger' : 'btn-success' }}"><i class="fas {{ $user->is_active ? 'fa-user-slash' : 'fa-user-check' }} ml-1"></i>{{ $user->is_active ? 'إيقاف' : 'تفعيل' }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="alert alert-info text-center">لا توجد حسابات مطابقة لخيارات البحث ضمن نطاق الوصول.</div></div>
            @endforelse
        </div>
    </div>
    <div class="card-footer">{{ $users->links() }}</div>
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
