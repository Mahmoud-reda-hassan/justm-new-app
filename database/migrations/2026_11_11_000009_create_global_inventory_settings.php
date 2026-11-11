<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            $table->foreignId('updated_by')->nullable()->after('description')->constrained('users')->nullOnDelete();
        });
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'inventory.allow_negative_stock'],
            ['setting_value' => '0', 'description' => 'السماح بترحيل حركات مخزنية ينتج عنها رصيد فعلي سالب', 'created_at' => now(), 'updated_at' => now()],
        );
        foreach ([
            ['key' => 'inventory.settings.view', 'module' => 'inventory', 'action' => 'settings_view', 'description' => 'عرض إعدادات المخازن العامة'],
            ['key' => 'inventory.settings.update', 'module' => 'inventory', 'action' => 'settings_update', 'description' => 'تعديل إعدادات المخازن العامة'],
        ] as $permission) {
            DB::table('permissions')->updateOrInsert(['key' => $permission['key']], $permission + ['created_at' => now(), 'updated_at' => now()]);
        }
    }
    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('key', ['inventory.settings.view','inventory.settings.update'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        DB::table('system_settings')->where('setting_key', 'inventory.allow_negative_stock')->delete();
        Schema::table('system_settings', function (Blueprint $table): void { $table->dropConstrainedForeignId('updated_by'); });
    }
};
