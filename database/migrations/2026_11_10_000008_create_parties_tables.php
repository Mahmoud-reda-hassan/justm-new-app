<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->nullable()->unique();
            $table->string('entity_type', 20)->default('individual')->index();
            $table->string('name', 180)->index();
            $table->string('contact_person', 150)->nullable();
            $table->string('phone', 40)->nullable()->index();
            $table->string('mobile', 40)->nullable()->index();
            $table->string('email', 190)->nullable();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->text('address')->nullable();
            $table->string('tax_number', 80)->nullable();
            $table->string('commercial_register', 80)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('party_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->string('role', 40);
            $table->timestamps();
            $table->unique(['party_id', 'role']);
            $table->index('role');
        });

        $permissions = [
            ['key' => 'parties.view', 'module' => 'parties', 'action' => 'view', 'description' => 'مشاهدة الأطراف'],
            ['key' => 'parties.create', 'module' => 'parties', 'action' => 'create', 'description' => 'إضافة طرف'],
            ['key' => 'parties.update', 'module' => 'parties', 'action' => 'update', 'description' => 'تعديل بيانات طرف'],
            ['key' => 'parties.activate', 'module' => 'parties', 'action' => 'activate', 'description' => 'تفعيل أو إيقاف طرف'],
        ];

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
            'parties.view', 'parties.create', 'parties.update', 'parties.activate',
        ])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        Schema::dropIfExists('party_roles');
        Schema::dropIfExists('parties');
    }
};
