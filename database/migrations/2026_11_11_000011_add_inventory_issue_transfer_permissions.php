<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach ([
            ['key' => 'inventory.issues.create', 'module' => 'inventory', 'action' => 'issues_create', 'description' => 'ترحيل مستندات صرف المخزون'],
            ['key' => 'inventory.transfers.create', 'module' => 'inventory', 'action' => 'transfers_create', 'description' => 'ترحيل التحويلات بين المخازن'],
        ] as $permission) {
            DB::table('permissions')->updateOrInsert(['key' => $permission['key']], $permission + ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('key', ['inventory.issues.create', 'inventory.transfers.create'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
