<?php

namespace Database\\Seeders;

use App\\Models\\Permission;
use App\\Models\\Role;
use Illuminate\\Database\\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed only the approved setup baseline. No sample customers, products,
     * warehouses, financial accounts, or default login credentials are created.
     */
    public function run(): void
    {
        $permissionKeys = [
            'users.view',
            'users.view_details',
            'users.create',
            'users.update',
            'users.activate',
            'users.reset_password',
            'users.assign_groups',
            'permissions.groups.view',
            'permissions.groups.create',
            'permissions.groups.update',
            'permissions.groups.assign',
        ];

        $permissions = Permission::query()->whereIn('key', $permissionKeys)->get();

        if ($permissions->count() !== count($permissionKeys)) {
            throw new RuntimeException('The approved access-control permissions are missing. Run migrations first.');
        }

        $role = Role::query()->firstOrCreate(
            ['key' => 'system-access-admin'],
            [
                'name' => 'مسؤول إدارة المستخدمين والصلاحيات',
                'description' => 'مجموعة تأسيسية لإدارة المستخدمين ومجموعات الصلاحيات فقط.',
                'is_active' => true,
            ],
        );

        if (! $role->is_active) {
            throw new RuntimeException('The initial access-management role is inactive; review it before seeding.');
        }

        $role->permissions()->syncWithoutDetaching($permissions->modelKeys());
    }
}
