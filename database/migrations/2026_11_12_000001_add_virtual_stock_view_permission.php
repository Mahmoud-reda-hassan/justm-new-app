<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Support\\Facades\\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['key' => 'inventory.virtual_stock.view'],
            [
                'module' => 'inventory',
                'action' => 'virtual_stock_view',
                'description' => 'عرض أرصدة المخازن الوهمية الثلاثة',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('key', 'inventory.virtual_stock.view')->value('id');
        if ($id) {
            DB::table('permission_role')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
    }
};
