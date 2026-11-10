<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\City;
use App\Models\Company;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SetupWebTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.setup_token' => str_repeat('s', 40)]);
    }

    public function test_setup_page_requires_a_valid_temporary_token_before_actions(): void
    {
        $this->get('/setup')->assertOk()->assertSee('مفتاح التهيئة');

        $this->post('/setup/access', ['setup_token' => 'wrong-token'])->assertSessionHasErrors('setup_token');
        $this->post('/setup/connection')->assertForbidden();

        $this->post('/setup/access', ['setup_token' => str_repeat('s', 40)])
            ->assertRedirect(route('setup.show'));

        $this->withSession(['justm_setup_authorized_at' => now()->timestamp])
            ->post('/setup/connection')
            ->assertSessionHas('setup_status');
    }

    public function test_database_setup_runs_migrations_and_initial_seeds_after_authorization(): void
    {
        $this->withSession(['justm_setup_authorized_at' => now()->timestamp])
            ->post('/setup/database')
            ->assertSessionHas('setup_status');

        $this->assertDatabaseHas('roles', ['key' => 'system-access-admin']);
        $this->assertDatabaseHas('warehouse_types', ['key' => 'internal']);
        $this->assertDatabaseHas('warehouse_types', ['key' => 'sales_dispatch']);
        $this->assertDatabaseCount('countries', 1);
        $this->assertDatabaseCount('regions', 7);
        $this->assertDatabaseCount('governorates', 27);
        $this->assertDatabaseCount('cities', 516);
    }

    public function test_initial_operating_setup_creates_the_required_hierarchy_and_site_name(): void
    {
        $this->installDatabase();
        $this->submitOperatingData()
            ->assertSessionHas('setup_status');

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'site_name',
            'setting_value' => 'Just-M للتجارة',
        ]);
        $this->assertDatabaseHas('countries', ['name_ar' => 'مصر', 'name_en' => 'Egypt']);
        $this->assertDatabaseHas('governorates', ['name_ar' => 'القاهره', 'name_en' => 'Cairo']);
        $this->assertDatabaseHas('cities', ['name_ar' => 'مدينة نصر', 'name_en' => 'Nasr City']);
        $this->assertDatabaseHas('companies', ['name' => 'شركة Just-M']);
        $this->assertDatabaseHas('branches', ['name' => 'المقر الرئيسي', 'is_main' => true]);
        $this->assertDatabaseHas('warehouses', ['name' => 'مخزن البيع']);

        $this->assertSame(1, Company::query()->count());
        $this->assertSame(1, Branch::query()->count());
        $this->assertSame(1, Warehouse::query()->count());
        $this->assertSame(1, Country::query()->count());
        $this->assertSame(27, Governorate::query()->count());
        $this->assertSame(516, City::query()->count());
    }

    public function test_existing_first_admin_can_complete_operating_setup_before_setup_locks(): void
    {
        $this->installDatabase();

        User::create([
            'name' => 'Existing',
            'username' => 'existing-user',
            'password' => 'secure-password-123',
            'account_type' => 'admin',
            'is_active' => true,
        ]);

        $this->withSession(['justm_setup_authorized_at' => now()->timestamp])
            ->get('/setup')
            ->assertOk()
            ->assertSee('استكمال بيانات المؤسسة الحالية')
            ->assertDontSee('إنشاء أول مدير');

        $this->submitOperatingData()
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'تم استكمال بيانات التشغيل الأساسية. يمكنك تسجيل الدخول.');
        $this->get('/setup')->assertNotFound();
    }

    public function test_first_admin_form_requires_organization_and_branch_details_together(): void
    {
        $this->installDatabase();

        $this->withSession(['justm_setup_authorized_at' => now()->timestamp])
            ->post('/setup/admin', [
                'name' => 'مدير النظام',
                'username' => 'premature-admin',
                'email' => 'admin@example.test',
                'password' => 'secure-password-123',
                'password_confirmation' => 'secure-password-123',
            ])
            ->assertSessionHasErrors(['company_name', 'branch_name', 'branch_address', 'country_id', 'governorate_id', 'city_id']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('companies', 0);
    }

    public function test_first_admin_setup_creates_organization_branch_warehouse_and_admin_together(): void
    {
        Notification::fake();
        $this->installDatabase();

        $this->withSession(['justm_setup_authorized_at' => now()->timestamp])
            ->post('/setup/admin', [
                'name' => 'مدير النظام',
                'username' => 'first-admin',
                'email' => 'admin@example.test',
                'password' => 'secure-password-123',
                'password_confirmation' => 'secure-password-123',
                'company_name' => 'شركة Just-M',
                'company_phone' => '0222222222',
                'branch_name' => 'المقر الرئيسي',
                'branch_address' => 'شارع التحرير - القاهرة',
                'country_id' => Country::query()->where('name_ar', 'مصر')->value('id'),
                'governorate_id' => Governorate::query()->where('name_ar', 'القاهره')->value('id'),
                'city_id' => City::query()->where('name_ar', 'مدينة نصر')->value('id'),
                'warehouse_name' => 'المخزن الرئيسي',
                'warehouse_type' => 'sales_dispatch',
            ])
            ->assertRedirect(route('verification.notice'));

        $user = User::query()->where('username', 'first-admin')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->assertSame(['system-access-admin'], $user->roles()->pluck('key')->all());
        $this->assertDatabaseHas('companies', ['name' => 'شركة Just-M', 'phone' => '0222222222']);
        $this->assertDatabaseHas('branches', ['name' => 'المقر الرئيسي', 'is_main' => true, 'address' => 'شارع التحرير - القاهرة']);
        $this->assertDatabaseHas('countries', ['name_ar' => 'مصر']);
        $this->assertDatabaseHas('governorates', ['name_ar' => 'القاهرة']);
        $this->assertDatabaseHas('cities', ['name_ar' => 'مدينة نصر']);
        $this->assertDatabaseHas('warehouses', ['name' => 'المخزن الرئيسي']);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->get('/setup')->assertNotFound();
    }

    public function test_first_admin_is_created_only_after_operating_data_and_has_limited_role(): void
    {
        Notification::fake();
        $this->installDatabase();
        $this->submitOperatingData();

        $this->withSession(['justm_setup_authorized_at' => now()->timestamp])
            ->post('/setup/admin', [
                'name' => 'مدير النظام',
                'username' => 'first-admin',
                'email' => 'admin@example.test',
                'password' => 'secure-password-123',
                'password_confirmation' => 'secure-password-123',
            ])
            ->assertRedirect(route('verification.notice'));

        $user = User::query()->where('username', 'first-admin')->firstOrFail();
        $this->assertTrue(password_verify('secure-password-123', $user->password));
        $this->assertSame(['system-access-admin'], $user->roles()->pluck('key')->all());
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->get('/setup')->assertNotFound();
    }

    public function test_setup_is_locked_after_first_user_and_operating_data_are_created(): void
    {
        $this->installDatabase();
        $this->submitOperatingData();

        User::create([
            'name' => 'Existing',
            'username' => 'existing-user',
            'password' => 'secure-password-123',
            'account_type' => 'admin',
            'is_active' => true,
        ]);

        $this->get('/setup')->assertNotFound();
        $this->withSession(['justm_setup_authorized_at' => now()->timestamp])
            ->post('/setup/admin', [])
            ->assertNotFound();
    }

    public function test_setup_authorization_expires_after_fifteen_minutes(): void
    {
        $this->withSession(['justm_setup_authorized_at' => now()->subMinutes(16)->timestamp])
            ->post('/setup/connection')
            ->assertForbidden();
    }

    public function test_setup_rejects_a_city_that_does_not_belong_to_the_selected_governorate(): void
    {
        $this->installDatabase();

        $payload = [
            'site_name' => 'Just-M',
            'country_id' => Country::query()->where('name_ar', 'مصر')->value('id'),
            'governorate_id' => Governorate::query()->where('name_ar', 'القاهره')->value('id'),
            'city_id' => City::query()->where('name_ar', 'سموحة')->value('id'),
            'company_name' => 'شركة اختبار',
            'company_phone' => '0222222222',
            'branch_name' => 'الفرع الرئيسي',
            'branch_address' => 'عنوان الفرع',
            'warehouse_name' => 'المخزن الرئيسي',
            'warehouse_type' => 'sales_dispatch',
        ];

        $this->withSession(['justm_setup_authorized_at' => now()->timestamp])
            ->post('/setup/operating-data', $payload)
            ->assertSessionHasErrors('city_id');

        $this->assertDatabaseCount('companies', 0);
    }

    private function installDatabase(): void
    {
        $this->withSession(['justm_setup_authorized_at' => now()->timestamp])
            ->post('/setup/database')
            ->assertSessionHas('setup_status');
    }

    private function submitOperatingData()
    {
        return $this->withSession(['justm_setup_authorized_at' => now()->timestamp])
            ->post('/setup/operating-data', [
                'site_name' => 'Just-M للتجارة',
                'country_id' => Country::query()->where('name_ar', 'مصر')->value('id'),
                'governorate_id' => Governorate::query()->where('name_ar', 'القاهره')->value('id'),
                'city_id' => City::query()->where('name_ar', 'مدينة نصر')->value('id'),
                'company_name' => 'شركة Just-M',
                'company_phone' => '0222222222',
                'company_mobile' => '01000000000',
                'company_email' => 'office@example.test',
                'company_address' => 'عنوان الشركة',
                'branch_name' => 'المقر الرئيسي',
                'branch_address' => 'عنوان الفرع',
                'warehouse_name' => 'مخزن البيع',
                'warehouse_type' => 'sales_dispatch',
            ]);
    }
}
