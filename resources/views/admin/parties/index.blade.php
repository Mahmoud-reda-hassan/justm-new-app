@extends('layouts.app')

@section('title', 'دليل الأطراف')
@section('page-title', 'دليل الأطراف')
@section('breadcrumb', 'دليل الأطراف')

@section('content')
@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('status') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger" role="alert">
        <strong>راجع البيانات التالية:</strong>
        <ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner"><h3>{{ number_format($stats['total']) }}</h3><p>إجمالي الأطراف</p></div>
            <div class="icon"><i class="fas fa-address-book"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner"><h3>{{ number_format($stats['active']) }}</h3><p>أطراف نشطة</p></div>
            <div class="icon"><i class="fas fa-user-check"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-primary">
            <div class="inner"><h3>{{ number_format($stats['marketers']) }}</h3><p>مسوق / وسيط</p></div>
            <div class="icon"><i class="fas fa-bullhorn"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-secondary">
            <div class="inner"><h3>{{ number_format($stats['deliveryAgents']) }}</h3><p>مندوبون داخليون</p></div>
            <div class="icon"><i class="fas fa-shipping-fast"></i></div>
        </div>
    </div>
</div>

<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title">دليل الأطراف</h3>
        <div class="card-tools">
            @if(auth()->user()->hasPermission('parties.create'))
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createPartyModal">
                    <i class="fas fa-plus ml-1"></i> إضافة طرف
                </button>
            @endif
            @if(auth()->user()->hasPermission('users.view'))
                <a class="btn btn-default btn-sm" href="{{ route('admin.access.users') }}">
                    <i class="fas fa-users ml-1"></i> حسابات الدخول
                </a>
            @endif
        </div>
    </div>
    <div class="card-body">
        <form method="get" action="{{ route('admin.parties.index') }}" class="mb-3">
            <div class="form-row align-items-end">
                <div class="form-group col-lg-5 col-md-6">
                    <label for="party-search">بحث</label>
                    <input id="party-search" class="form-control" name="q" value="{{ request('q') }}" placeholder="الاسم أو الكود أو الهاتف أو البريد">
                </div>
                <div class="form-group col-lg-3 col-md-3">
                    <label for="party-role">صفة الطرف</label>
                    <select id="party-role" class="form-control" name="role">
                        <option value="">كل الصفات</option>
                        @foreach($partyRoles as $key => $label)
                            <option value="{{ $key }}" @selected(request('role') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-lg-2 col-md-3">
                    <label for="party-status">الحالة</label>
                    <select id="party-status" class="form-control" name="status">
                        <option value="">كل الحالات</option>
                        <option value="active" @selected(request('status') === 'active')>نشط</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>موقوف</option>
                    </select>
                </div>
                <div class="form-group col-lg-2 col-md-12">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search ml-1"></i> بحث</button>
                    <a class="btn btn-default" href="{{ route('admin.parties.index') }}">مسح</a>
                </div>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted">عدد النتائج: {{ number_format($parties->total()) }}</span>
            <span class="text-muted small">الطرف يظهر مرة واحدة مهما تعددت صفاته.</span>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped text-nowrap">
                <thead>
                    <tr>
                        <th>الكود</th>
                        <th>الاسم</th>
                        <th>النوع والصفات</th>
                        <th>بيانات الاتصال</th>
                        <th>المدينة</th>
                        <th>حسابات الدخول</th>
                        <th>الحالة</th>
                        <th style="width:190px">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($parties as $party)
                    @php($assignedRoles = $party->assignedRoles())
                    @php($hasLoginRole = count(array_intersect($assignedRoles, $loginRoles)) > 0)
                    <tr>
                        <td>{{ $party->code ?: '—' }}</td>
                        <td>
                            <strong>{{ $party->name }}</strong>
                            @if($party->contact_person)<div class="text-muted small">المسؤول: {{ $party->contact_person }}</div>@endif
                        </td>
                        <td>
                            <div class="mb-1">{{ $party->entity_type === 'company' ? 'شركة / مؤسسة' : 'فرد' }}</div>
                            @foreach($assignedRoles as $role)
                                <span class="badge badge-info">{{ $partyRoles[$role] ?? $role }}</span>
                            @endforeach
                        </td>
                        <td>
                            <div>{{ $party->phone ?: '—' }}</div>
                            @if($party->mobile)<div class="text-muted small">{{ $party->mobile }}</div>@endif
                            @if($party->email)<div class="text-muted small">{{ $party->email }}</div>@endif
                        </td>
                        <td>{{ $party->city?->name_ar ?: '—' }}</td>
                        <td>
                            @if($party->users->isNotEmpty())
                                <button class="btn btn-link btn-sm p-0" type="button" data-toggle="collapse" data-target="#party-accounts-{{ $party->id }}" aria-expanded="false">
                                    عرض {{ $party->users->count() }} حساب
                                </button>
                                <div class="collapse mt-2" id="party-accounts-{{ $party->id }}">
                                    <ul class="list-unstyled mb-0">
                                        @foreach($party->users as $account)
                                            <li class="mb-1">
                                                {{ $account->name }} <small class="text-muted">({{ $account->username }})</small>
                                                <span class="badge {{ $account->is_active ? 'badge-success' : 'badge-danger' }}">{{ $account->is_active ? 'نشط' : 'موقوف' }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @else
                                <span class="text-muted">لا يوجد</span>
                            @endif
                        </td>
                        <td><span class="badge {{ $party->is_active ? 'badge-success' : 'badge-danger' }}">{{ $party->is_active ? 'نشط' : 'موقوف' }}</span></td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group" aria-label="إجراءات الطرف">
                                @if($canCreateUsers && $party->is_active && $hasLoginRole)
                                    <a class="btn btn-info" title="إنشاء حساب دخول" href="{{ route('admin.access.users', ['party_id' => $party->id]) }}"><i class="fas fa-user-plus"></i></a>
                                @endif
                                @if(auth()->user()->hasPermission('parties.update'))
                                    <button type="button" class="btn btn-primary" title="تعديل" data-toggle="modal" data-target="#editPartyModal{{ $party->id }}"><i class="fas fa-edit"></i></button>
                                @endif
                                @if(auth()->user()->hasPermission('parties.activate'))
                                    <form method="post" action="{{ route('admin.parties.active', $party) }}" onsubmit="return confirm('{{ $party->is_active ? 'هل تريد إيقاف هذا الطرف؟' : 'هل تريد تفعيل هذا الطرف؟' }}')">
                                        @csrf @method('patch')
                                        <input type="hidden" name="is_active" value="{{ $party->is_active ? 0 : 1 }}">
                                        <button class="btn {{ $party->is_active ? 'btn-danger' : 'btn-success' }}" type="submit" title="{{ $party->is_active ? 'إيقاف' : 'تفعيل' }}">
                                            <i class="fas {{ $party->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">لا توجد أطراف مطابقة لخيارات البحث الحالية.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $parties->links() }}</div>
    </div>
</div>

@if(auth()->user()->hasPermission('parties.create'))
<div class="modal fade" id="createPartyModal" tabindex="-1" role="dialog" aria-labelledby="createPartyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="post" action="{{ route('admin.parties.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="createPartyModalLabel">إضافة طرف جديد</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-4"><label>كود الطرف</label><input class="form-control" name="code" value="{{ old('code') }}" maxlength="50" placeholder="اختياري"></div>
                        <div class="form-group col-md-4"><label>نوع الطرف</label><select class="form-control" name="entity_type" required><option value="individual" @selected(old('entity_type', 'individual') === 'individual')>فرد</option><option value="company" @selected(old('entity_type') === 'company')>شركة / مؤسسة</option></select></div>
                        <div class="form-group col-md-4"><label>الاسم</label><input class="form-control" name="name" value="{{ old('name') }}" required maxlength="180"></div>
                        <div class="form-group col-md-6"><label>الشخص المسؤول</label><input class="form-control" name="contact_person" value="{{ old('contact_person') }}" maxlength="150"></div>
                        <div class="form-group col-md-3"><label>الهاتف</label><input class="form-control" name="phone" value="{{ old('phone') }}" maxlength="40"></div>
                        <div class="form-group col-md-3"><label>المحمول</label><input class="form-control" name="mobile" value="{{ old('mobile') }}" maxlength="40"></div>
                        <div class="form-group col-md-6"><label>البريد الإلكتروني</label><input class="form-control" name="email" type="email" value="{{ old('email') }}" maxlength="190"></div>
                        <div class="form-group col-md-6"><label>المدينة</label><select class="form-control" name="city_id"><option value="">اختر المدينة</option>@foreach($cities as $city)<option value="{{ $city->id }}" @selected((string) old('city_id') === (string) $city->id)>{{ $city->name_ar }}</option>@endforeach</select></div>
                        <div class="form-group col-md-6"><label>الرقم الضريبي</label><input class="form-control" name="tax_number" value="{{ old('tax_number') }}" maxlength="80"></div>
                        <div class="form-group col-md-6"><label>السجل التجاري</label><input class="form-control" name="commercial_register" value="{{ old('commercial_register') }}" maxlength="80"></div>
                        <div class="form-group col-12"><label>العنوان</label><input class="form-control" name="address" value="{{ old('address') }}" maxlength="4000"></div>
                        <div class="form-group col-12"><label>ملاحظات</label><textarea class="form-control" name="notes" rows="2">{{ old('notes') }}</textarea></div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="d-block">صفات الطرف — يمكن اختيار أكثر من صفة</label>
                        <div class="row">
                            @foreach($partyRoles as $key => $label)
                                <div class="col-md-4 col-6"><div class="form-check"><input class="form-check-input" type="checkbox" id="create-role-{{ $key }}" name="roles[]" value="{{ $key }}" @checked(in_array($key, old('roles', ['customer']), true))><label class="form-check-label" for="create-role-{{ $key }}">{{ $label }}</label></div></div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button><button type="submit" class="btn btn-primary">حفظ الطرف</button></div>
            </form>
        </div>
    </div>
</div>
@endif

@if(auth()->user()->hasPermission('parties.update'))
    @foreach($parties as $party)
        <div class="modal fade" id="editPartyModal{{ $party->id }}" tabindex="-1" role="dialog" aria-labelledby="editPartyModalLabel{{ $party->id }}" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <form method="post" action="{{ route('admin.parties.update', $party) }}">
                        @csrf @method('put')
                        <div class="modal-header">
                            <h5 class="modal-title" id="editPartyModalLabel{{ $party->id }}">تعديل بيانات: {{ $party->name }}</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-row">
                                <div class="form-group col-md-4"><label>كود الطرف</label><input class="form-control" name="code" value="{{ $party->code }}" maxlength="50"></div>
                                <div class="form-group col-md-4"><label>نوع الطرف</label><select class="form-control" name="entity_type" required><option value="individual" @selected($party->entity_type === 'individual')>فرد</option><option value="company" @selected($party->entity_type === 'company')>شركة / مؤسسة</option></select></div>
                                <div class="form-group col-md-4"><label>الاسم</label><input class="form-control" name="name" value="{{ $party->name }}" required maxlength="180"></div>
                                <div class="form-group col-md-6"><label>الشخص المسؤول</label><input class="form-control" name="contact_person" value="{{ $party->contact_person }}" maxlength="150"></div>
                                <div class="form-group col-md-3"><label>الهاتف</label><input class="form-control" name="phone" value="{{ $party->phone }}" maxlength="40"></div>
                                <div class="form-group col-md-3"><label>المحمول</label><input class="form-control" name="mobile" value="{{ $party->mobile }}" maxlength="40"></div>
                                <div class="form-group col-md-6"><label>البريد الإلكتروني</label><input class="form-control" name="email" type="email" value="{{ $party->email }}" maxlength="190"></div>
                                <div class="form-group col-md-6"><label>المدينة</label><select class="form-control" name="city_id"><option value="">اختر المدينة</option>@foreach($cities as $city)<option value="{{ $city->id }}" @selected($party->city_id == $city->id)>{{ $city->name_ar }}</option>@endforeach</select></div>
                                <div class="form-group col-md-6"><label>الرقم الضريبي</label><input class="form-control" name="tax_number" value="{{ $party->tax_number }}" maxlength="80"></div>
                                <div class="form-group col-md-6"><label>السجل التجاري</label><input class="form-control" name="commercial_register" value="{{ $party->commercial_register }}" maxlength="80"></div>
                                <div class="form-group col-12"><label>العنوان</label><input class="form-control" name="address" value="{{ $party->address }}" maxlength="4000"></div>
                                <div class="form-group col-12"><label>ملاحظات</label><textarea class="form-control" name="notes" rows="2">{{ $party->notes }}</textarea></div>
                            </div>
                            <div class="form-group mb-0">
                                <label class="d-block">صفات الطرف</label>
                                <div class="row">
                                    @foreach($partyRoles as $key => $label)
                                        <div class="col-md-4 col-6"><div class="form-check"><input class="form-check-input" type="checkbox" id="edit-role-{{ $party->id }}-{{ $key }}" name="roles[]" value="{{ $key }}" @checked(in_array($key, $party->assignedRoles(), true))><label class="form-check-label" for="edit-role-{{ $party->id }}-{{ $key }}">{{ $label }}</label></div></div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button><button type="submit" class="btn btn-primary">حفظ التعديلات</button></div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endif
@endsection
