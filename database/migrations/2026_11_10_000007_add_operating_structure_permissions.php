<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $permissions = [
            ['key' => 'companies.view', 'module' => 'companies', 'action' => 'view', 'description' => 'مشاهدة الشركات'],
            ['key' => 'companies.create', 'module' => 'companies', 'action' => 'create', 'description' => 'إنشاء شركة'],
            ['key' => 'companies.update', 'module' => 'companies', 'action' => 'update', 'description' => 'تعديل شركة'],
            ['key' => 'companies.activate', 'module' => 'companies', 'action' => 'activate', 'description' => 'تفعيل أو إيقاف شركة'],
            ['key' => 'branches.view', 'module' => 'branches', 'action' => 'view', 'description' => 'مشاهدة الفروع'],
            ['key' => 'branches.create', 'module' => 'branches', 'action' => 'create', 'description' => 'إنشاء فرع'],
            ['key' => 'branches.update', 'module' => 'branches', 'action' => 'update', 'description' => 'تعديل فرع'],
            ['key' => 'branches.activate', 'module' => 'branches', 'action' => 'activate', 'description' => 'تفعيل أو إيقاف فرع'],
            ['key' => 'warehouses.view', 'module' => 'warehouses', 'action' => 'view', 'description' => 'مشاهدة المخازن'],
            ['key' => 'warehouses.create', 'module' => 'warehouses', 'action' => 'create', 'description' => 'إنشاء مخزن'],
            ['key' => 'warehouses.update', 'module' => 'warehouses', 'action' => 'update', 'description' => 'تعديل مخزن'],
            ['key' => 'warehouses.activate', 'module' => 'warehouses', 'action' => 'activate', 'description' => 'تفعيل أو إيقاف مخزن'],
            ['key' => 'users.assign_scopes', 'module' => 'users', 'action' => 'assign_scopes', 'description' => 'تحديد نطاق بيانات المستخدم'],
            ['key' => 'permissions.catalog.assign', 'module' => 'permissions', 'action' => 'catalog_assign', 'description' => 'تفويض صلاحيات النظام المتاحة لمجموعات الصلاحيات'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $permission['key']],
                $permission + ['created_at' => now(), 'updated_at' => now()],
            );
        }

        // The initial access administrator may delegate catalog permissions,
        // but receives no operating permission and no implicit data scope.
        $roleId = DB::table('roles')->where('key', 'system-access-admin')->value('id');
        $permissionIds = DB::table('permissions')
            ->whereIn('key', ['permissions.catalog.assign', 'users.assign_scopes'])
            ->pluck('id');

        if ($roleId && $permissionIds->isNotEmpty()) {
            DB::table('permission_role')->insertOrIgnore(
                $permissionIds->map(fn ($id) => ['role_id' => $roleId, 'permission_id' => $id])->all(),
            );
        }
    }

    public function down(): void
    {
        $keys = [
            'companies.view', 'companies.create', 'companies.update', 'companies.activate',
            'branches.view', 'branches.create', 'branches.update', 'branches.activate',
            'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.activate',
            'users.assign_scopes', 'permissions.catalog.assign',
        ];

        $ids = DB::table('permissions')->whereIn('key', $keys)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
