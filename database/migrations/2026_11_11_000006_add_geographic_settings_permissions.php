<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Support\\Facades\\DB;

return new class extends Migration {
    public function up(): void
    {
        $permissions = [];
        foreach (['countries' => 'الدول', 'governorates' => 'المحافظات والمقاطعات', 'cities' => 'المدن'] as $module => $label) {
            foreach (['view' => 'مشاهدة', 'create' => 'إضافة', 'update' => 'تعديل', 'activate' => 'تفعيل أو إيقاف'] as $action => $description) {
                $permissions[] = [
                    'key' => 'geography.'.$module.'.'.$action,
                    'module' => 'geography.'.$module,
                    'action' => $action,
                    'description' => $description.' '.$label,
                ];
            }
        }
        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $permission['key']],
                $permission + ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('key', [
            'geography.countries.view','geography.countries.create','geography.countries.update','geography.countries.activate',
            'geography.governorates.view','geography.governorates.create','geography.governorates.update','geography.governorates.activate',
            'geography.cities.view','geography.cities.create','geography.cities.update','geography.cities.activate',
        ])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
