<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Warehouse;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class AccessControlController extends Controller
{
    public function __construct(private readonly PermissionAuditLogger $audit)
    {
    }

    public function users(Request $request): View
    {
        $actor = $request->user();
        $globalScope = $actor->hasPermission('structure.scope.manage_all');
        $companyIds = $actor->dataScopes()->where('scope_type', 'company')->pluck('scope_id');
        $branchIds = $actor->dataScopes()->where('scope_type', 'branch')->pluck('scope_id');
        $warehouseIds = $actor->dataScopes()->where('scope_type', 'warehouse')->pluck('scope_id');

        return view('admin.access.users', [
            'users' => User::query()->with(['roles', 'dataScopes'])->orderByDesc('id')->paginate(20),
            'roles' => Role::query()->where('is_active', true)->orderBy('name')->get(),
            'companies' => Company::query()->when(! $globalScope, fn ($q) => $q->whereIn('id', $companyIds))->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::query()->with('company')->when(! $globalScope, fn ($q) => $q->whereIn('company_id', $companyIds)->orWhereIn('id', $branchIds))->orderBy('name')->get(['id', 'company_id', 'name']),
            'warehouses' => Warehouse::query()->with('branch')->when(! $globalScope, function ($q) use ($companyIds, $branchIds, $warehouseIds): void {
                $q->where(fn ($inner) => $inner->whereIn('id', $warehouseIds)
                    ->orWhereIn('branch_id', $branchIds)
                    ->orWhereHas('branch', fn ($branch) => $branch->whereIn('company_id', $companyIds)));
            })->orderBy('name')->get(['id', 'branch_id', 'name']),
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $user = User::create($data + [
            'account_type' => 'admin',
            'is_active' => true,
        ]);

        $this->audit->record('users.created', $user, [
            'username' => $user->username,
            'account_type' => $user->account_type,
        ]);

        return back()->with('status', 'تم إنشاء المستخدم.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'لا يمكن تعديل حسابك من هذه الشاشة.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $before = $user->only(['name', 'username', 'email']);
        $user->update($data);

        $this->audit->record('users.updated', $user, [
            'before' => $before,
            'after' => $user->only(['name', 'username', 'email']),
        ]);

        return back()->with('status', 'تم تحديث بيانات المستخدم.');
    }

    public function setUserActive(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'لا يمكن إيقاف حسابك الحالي.');

        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $user->is_active = (bool) $data['is_active'];
        $user->save();

        $this->audit->record($user->is_active ? 'users.activated' : 'users.disabled', $user, [
            'is_active' => $user->is_active,
        ]);

        return back()->with('status', 'تم تحديث حالة المستخدم.');
    }

    public function resetUserPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $user->password = Hash::make($data['password']);
        $user->save();

        $this->audit->record('users.password_reset', $user);

        return back()->with('status', 'تم تغيير كلمة المرور.');
    }

    public function assignUserRoles(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['integer', 'distinct', Rule::exists('roles', 'id')->where('is_active', true)],
        ]);

        abort_if($request->user()->is($user), 422, 'لا يمكن تغيير مجموعات حسابك الحالي من هذه الشاشة.');

        $roleIds = $data['roles'] ?? [];
        $roles = Role::query()->with('permissions')->whereIn('id', $roleIds)->get();
        $actorPermissions = $request->user()->roles()
            ->where('roles.is_active', true)
            ->with('permissions:id,key')
            ->get()->pluck('permissions')->flatten()->pluck('key')->unique()->all();

        if (! $request->user()->hasPermission('permissions.catalog.assign')) {
            foreach ($roles as $role) {
                foreach ($role->permissions as $permission) {
                    abort_unless(in_array($permission->key, $actorPermissions, true), 403,
                        'لا يمكن إسناد مجموعة تتضمن صلاحيات لا تملكها.');
                }
            }
        }

        $before = $user->roles()->pluck('roles.key')->all();
        $user->roles()->sync($roles->pluck('id')->all());
        $this->audit->record('users.groups_assigned', $user, [
            'before' => $before,
            'after' => $roles->pluck('key')->all(),
        ]);

        return back()->with('status', 'تم تحديث مجموعات المستخدم.');
    }

    public function assignUserScopes(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'company_ids' => ['sometimes', 'array'],
            'company_ids.*' => ['integer', 'distinct', 'exists:companies,id'],
            'branch_ids' => ['sometimes', 'array'],
            'branch_ids.*' => ['integer', 'distinct', 'exists:branches,id'],
            'warehouse_ids' => ['sometimes', 'array'],
            'warehouse_ids.*' => ['integer', 'distinct', 'exists:warehouses,id'],
        ]);

        $scopes = collect([
            ...collect($data['company_ids'] ?? [])->map(fn ($id) => ['scope_type' => 'company', 'scope_id' => (int) $id]),
            ...collect($data['branch_ids'] ?? [])->map(fn ($id) => ['scope_type' => 'branch', 'scope_id' => (int) $id]),
            ...collect($data['warehouse_ids'] ?? [])->map(fn ($id) => ['scope_type' => 'warehouse', 'scope_id' => (int) $id]),
        ]);

        if (! $request->user()->hasPermission('structure.scope.manage_all')) {
            foreach ($user->dataScopes as $existingScope) {
                abort_unless($request->user()->hasDataScope($existingScope->scope_type, (int) $existingScope->scope_id), 403,
                    'لا يمكنك تعديل نطاق بيانات خارج نطاقك.');
            }
            foreach ($scopes as $scope) {
                abort_unless($request->user()->hasDataScope($scope['scope_type'], $scope['scope_id']), 403,
                    'لا يمكنك إسناد نطاق بيانات خارج نطاقك.');
            }
        }

        $before = $user->dataScopes()->get(['scope_type', 'scope_id'])->toArray();
        DB::transaction(function () use ($user, $scopes): void {
            $user->dataScopes()->delete();
            foreach ($scopes as $scope) {
                $user->dataScopes()->create($scope);
            }
        });

        $this->audit->record('users.data_scopes_assigned', $user, [
            'before' => $before,
            'after' => $user->dataScopes()->get(['scope_type', 'scope_id'])->toArray(),
        ]);

        return back()->with('status', 'تم تحديث نطاق وصول المستخدم.');
    }

    public function roles(): View
    {
        return view('admin.access.roles', [
            'roles' => Role::query()->with('permissions')->orderBy('name')->paginate(20),
            'permissions' => Permission::query()->orderBy('module')->orderBy('key')->get(),
        ]);
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'key' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:roles,key'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $role = Role::create($data + ['is_active' => true]);
        $this->audit->record('permissions.groups.created', null, [
            'role_id' => $role->id,
            'key' => $role->key,
            'name' => $role->name,
        ]);

        return back()->with('status', 'تم إنشاء مجموعة الصلاحيات.');
    }

    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ]);
        abort_if(! $data['is_active'] && $request->user()->roles()->whereKey($role->id)->exists(), 422,
            'لا يمكن إيقاف مجموعة صلاحيات مسندة إلى حسابك الحالي.');

        $before = $role->only(['name', 'description', 'is_active']);
        $role->update($data);
        $this->audit->record('permissions.groups.updated', null, [
            'role_id' => $role->id,
            'before' => $before,
            'after' => $role->only(['name', 'description', 'is_active']),
        ]);

        return back()->with('status', 'تم تحديث مجموعة الصلاحيات.');
    }

    public function assignRolePermissions(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ]);

        abort_if($request->user()->roles()->whereKey($role->id)->exists(), 422, 'لا يمكن تعديل صلاحيات مجموعة مسندة لحسابك الحالي.');

        $selected = Permission::query()->whereIn('id', $data['permissions'] ?? [])->get();
        $actorPermissions = $request->user()->roles()
            ->where('roles.is_active', true)
            ->with('permissions:id,key')
            ->get()->pluck('permissions')->flatten()->pluck('key')->unique()->all();

        if (! $request->user()->hasPermission('permissions.catalog.assign')) {
            foreach ($selected as $permission) {
                abort_unless(in_array($permission->key, $actorPermissions, true), 403,
                    'لا يمكن إسناد صلاحية لا تملكها.');
            }
        }

        $before = $role->permissions()->pluck('permissions.key')->all();
        $role->permissions()->sync($selected->pluck('id')->all());
        $this->audit->record('permissions.groups.permissions_assigned', null, [
            'role_id' => $role->id,
            'before' => $before,
            'after' => $selected->pluck('key')->all(),
        ]);

        return back()->with('status', 'تم تحديث صلاحيات المجموعة.');
    }
}
