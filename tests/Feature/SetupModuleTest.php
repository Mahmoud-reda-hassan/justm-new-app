<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Role;
use App\Models\User;
use App\Services\InitialOperatingSetup;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SetupModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_commands_are_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('justm:setup', $commands);
        $this->assertArrayHasKey('justm:provision-first-admin', $commands);
    }

    public function test_setup_seeding_is_repeatable_without_creating_duplicate_roles(): void
    {
        app(DatabaseSeeder::class)->run();

        app(DatabaseSeeder::class)->run();

        $this->assertSame(1, Role::query()->where('key', 'system-access-admin')->count());
        $this->assertDatabaseCount('countries', 1);
        $this->assertDatabaseCount('regions', 7);
        $this->assertDatabaseCount('governorates', 27);
        $this->assertDatabaseCount('cities', 516);
    }

    public function test_first_admin_can_be_created_once_with_only_setup_permissions(): void
    {
        Notification::fake();
        app(DatabaseSeeder::class)->run();
        $this->createOperatingData();

        $this->artisan('justm:provision-first-admin', [
            '--name' => 'First Admin',
            '--username' => 'first-admin',
            '--email' => 'first@example.test',
        ])
            ->expectsQuestion('كلمة المرور (12 حرفًا على الأقل)', 'correct-horse-battery')
            ->expectsQuestion('تأكيد كلمة المرور', 'correct-horse-battery')
            ->expectsOutputToContain('تم إنشاء أول مدير بنجاح.')
            ->assertExitCode(0);

        $user = User::query()->where('username', 'first-admin')->firstOrFail();

        $this->assertTrue(password_verify('correct-horse-battery', $user->password));
        $this->assertSame(['system-access-admin'], $user->roles()->pluck('key')->all());
        $this->assertSame(14, $user->roles()->first()->permissions()->count());
        $this->assertFalse($user->hasPermission('companies.view'));
        $this->assertFalse($user->hasPermission('branches.view'));
        $this->assertFalse($user->hasPermission('warehouses.view'));
        $this->assertFalse($user->hasPermission('sales.orders.view'));
        $this->assertFalse($user->hasPermission('sales.orders.create'));
    }

    private function createOperatingData(): void
    {
        app(InitialOperatingSetup::class)->configure([
            'site_name' => 'Just-M',
            'country_id' => Country::query()->where('name_ar', 'مصر')->value('id'),
            'governorate_id' => Governorate::query()->where('name_ar', 'القاهره')->value('id'),
            'city_id' => City::query()->where('name_ar', 'مدينة نصر')->value('id'),
            'company_name' => 'شركة Just-M',
            'branch_name' => 'المقر الرئيسي',
            'warehouse_name' => 'مخزن البيع',
            'warehouse_type' => 'sales_dispatch',
        ]);
    }

    public function test_first_admin_provisioning_is_refused_when_any_user_exists(): void
    {
        app(DatabaseSeeder::class)->run();
        $this->createOperatingData();
        User::create([
            'name' => 'Existing User',
            'username' => 'existing-user',
            'password' => 'existing-secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);

        $this->artisan('justm:provision-first-admin', [
            '--name' => 'Another Admin',
            '--username' => 'another-admin',
        ])
            ->expectsOutputToContain('تم رفض العملية: يوجد مستخدم بالفعل.')
            ->assertExitCode(1);

        $this->assertSame(1, User::query()->count());
    }
}
