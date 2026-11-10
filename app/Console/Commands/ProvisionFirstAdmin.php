<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Illuminate\Validation\Rules\Password;
use Throwable;

class ProvisionFirstAdmin extends Command
{
    protected $signature = 'justm:provision-first-admin
        {--name= : Administrator display name}
        {--username= : Unique login username}
        {--email= : Optional email address}';

    protected $description = 'Safely create the first Just-M administrator with setup-only permissions';

    public function handle(): int
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            $this->error('شغّل php artisan justm:setup أولًا.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('system_settings') || ! Schema::hasTable('companies') || ! Schema::hasTable('branches') || ! Schema::hasTable('warehouses')
            || ! Company::query()->exists() || ! Branch::query()->exists() || ! Warehouse::query()->exists()
            || ! filled(SystemSetting::query()->where('setting_key', 'site_name')->value('setting_value'))) {
            $this->error('أكمل بيانات الموقع والدولة والمحافظة والمدينة والشركة والفرع والمخزن من معالج /setup أولًا.');

            return self::FAILURE;
        }

        if (User::query()->exists()) {
            $this->error('تم رفض العملية: يوجد مستخدم بالفعل. لا يمكن استخدام الإعداد الأولي مرة أخرى.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?: $this->ask('اسم المدير');
        $username = $this->option('username') ?: $this->ask('اسم المستخدم');
        $email = $this->option('email') ?: $this->ask('البريد الإلكتروني (اختياري)');

        if ($email === '') {
            $email = null;
        }

        $password = $this->secret('كلمة المرور (12 حرفًا على الأقل)');
        $confirmation = $this->secret('تأكيد كلمة المرور');

        $validator = Validator::make([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(12)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($name, $username, $email, $password): void {
                // Re-check the bootstrap prerequisites and user state inside the transaction.
                if (! Company::query()->exists() || ! Branch::query()->exists() || ! Warehouse::query()->exists()
                    || ! filled(SystemSetting::query()->where('setting_key', 'site_name')->value('setting_value'))
                    || User::query()->lockForUpdate()->exists()) {
                    throw new RuntimeException('A user already exists; first-admin provisioning is closed.');
                }

                $role = Role::query()
                    ->where('key', 'system-access-admin')
                    ->where('is_active', true)
                    ->firstOrFail();

                $user = User::create([
                    'name' => $name,
                    'username' => $username,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'account_type' => 'admin',
                    'is_active' => true,
                ]);

                $user->roles()->attach($role);
            });
        } catch (Throwable $exception) {
            report($exception);
            $this->error('تعذر إنشاء المدير. تأكد من تنفيذ التهيئة الأولية وعدم وجود مستخدم سابق.');

            return self::FAILURE;
        }

        $this->info('تم إنشاء أول مدير بنجاح.');
        $this->warn('صلاحياته تقتصر على إدارة المستخدمين ومجموعات الصلاحيات، ولا تمنحه صلاحيات تشغيلية أو مالية.');

        return self::SUCCESS;
    }
}
