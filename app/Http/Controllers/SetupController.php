<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\City;
use App\Models\Company;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InitialOperatingSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

class SetupController extends Controller
{
    public function show()
    {
        abort_if($this->isCompleted(), 404);

        $databaseReady = false;
        $adminExists = false;
        $siteName = null;

        try {
            $databaseReady = Schema::hasTable('users')
                && Schema::hasTable('system_settings')
                && Schema::hasTable('companies')
                && Schema::hasTable('warehouses');
            $adminExists = Schema::hasTable('users') && User::query()->exists();
            $siteName = Schema::hasTable('system_settings')
                ? SystemSetting::query()->where('setting_key', 'site_name')->value('setting_value')
                : null;
        } catch (Throwable $exception) {
            report($exception);
        }

        return view('setup', [
            'authorized' => $this->isAuthorized(),
            'databaseReady' => $databaseReady,
            'adminExists' => $adminExists,
            'operatingDataReady' => $this->isOperatingDataReady(),
            'siteName' => $siteName,
            'tokenConfigured' => is_string(config('app.setup_token')) && strlen(config('app.setup_token')) >= 32,
        ]);
    }

    public function authorizeSetup(Request $request): RedirectResponse
    {
        abort_if($this->isCompleted(), 404);

        $configuredToken = config('app.setup_token');
        $providedToken = $request->input('setup_token');

        if (! is_string($configuredToken) || strlen($configuredToken) < 32
            || ! is_string($providedToken)
            || ! hash_equals($configuredToken, $providedToken)) {
            return back()->withErrors(['setup_token' => 'مفتاح التثبيت غير صحيح أو غير مضبوط في ملف البيئة.']);
        }

        $request->session()->regenerate();
        $request->session()->put('justm_setup_authorized_at', now()->timestamp);

        return redirect()->route('setup.show');
    }

    public function testConnection(Request $request): RedirectResponse
    {
        $this->ensureAuthorized($request);

        try {
            DB::connection()->getPdo();

            return back()->with('setup_status', 'تم الاتصال بقاعدة البيانات بنجاح.');
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['setup' => 'فشل الاتصال. راجع إعدادات DB في ملف البيئة وصلاحيات المستخدم.']);
        }
    }

    public function installDatabase(Request $request): RedirectResponse
    {
        $this->ensureAuthorized($request);

        try {
            $driver = config('database.connections.'.config('database.default').'.driver');
            $setupExit = Artisan::call('justm:setup', [
                '--create-database' => in_array($driver, ['mysql', 'mariadb'], true),
                '--force' => true,
            ]);

            if ($setupExit !== 0) {
                report(new \RuntimeException('Just-M browser setup command returned a non-zero exit code.'));
                return back()->withErrors(['setup' => 'تعذر إنشاء قاعدة البيانات أو الجداول أو البيانات الأولية. راجع إعدادات الاتصال وصلاحية CREATE DATABASE وسجل التطبيق.']);
            }

            return back()->with('setup_status', 'تم تجهيز قاعدة البيانات والجداول والبيانات الثابتة بنجاح.');
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['setup' => 'تعذر إكمال التهيئة. راجع الاتصال وصلاحيات قاعدة البيانات وسجل التطبيق.']);
        }
    }

    public function saveOperatingData(Request $request, InitialOperatingSetup $setup): RedirectResponse
    {
        $this->ensureAuthorized($request);
        abort_unless(Schema::hasTable('companies') && Schema::hasTable('warehouses'), 409);
        abort_if($this->isOperatingDataReady(), 404);

        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:150'],
            'country_name_ar' => ['required', 'string', 'max:100'],
            'country_name_en' => ['required', 'string', 'max:100'],
            'region_name' => ['nullable', 'string', 'max:100'],
            'governorate_name_ar' => ['required', 'string', 'max:100'],
            'governorate_name_en' => ['required', 'string', 'max:100'],
            'city_name_ar' => ['required', 'string', 'max:120'],
            'city_name_en' => ['required', 'string', 'max:120'],
            'company_name' => ['required', 'string', 'max:150'],
            'company_phone' => ['nullable', 'string', 'max:40'],
            'company_mobile' => ['nullable', 'string', 'max:40'],
            'company_email' => ['nullable', 'email', 'max:190'],
            'company_address' => ['nullable', 'string', 'max:2000'],
            'branch_name' => ['required', 'string', 'max:120'],
            'branch_address' => ['nullable', 'string', 'max:255'],
            'warehouse_name' => ['required', 'string', 'max:150'],
            'warehouse_type' => [
                'required',
                Rule::exists('warehouse_types', 'key')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ], [
            'site_name.required' => 'اسم الموقع مطلوب.',
            'country_name_ar.required' => 'اسم الدولة بالعربية مطلوب.',
            'country_name_en.required' => 'اسم الدولة بالإنجليزية مطلوب.',
            'governorate_name_ar.required' => 'اسم المحافظة بالعربية مطلوب.',
            'governorate_name_en.required' => 'اسم المحافظة بالإنجليزية مطلوب.',
            'city_name_ar.required' => 'اسم المدينة بالعربية مطلوب.',
            'city_name_en.required' => 'اسم المدينة بالإنجليزية مطلوب.',
            'company_name.required' => 'اسم الشركة مطلوب.',
            'branch_name.required' => 'اسم الفرع الأول مطلوب.',
            'warehouse_name.required' => 'اسم المخزن الأول مطلوب.',
            'warehouse_type.required' => 'نوع المخزن مطلوب.',
            'warehouse_type.exists' => 'نوع المخزن المحدد غير متاح.',
        ]);

        try {
            $setup->configure($validated);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors(['setup' => 'تعذر حفظ بيانات التشغيل الأولية. لم يتم اعتماد أي جزء منها؛ راجع البيانات وسجل التطبيق.']);
        }

        if (User::query()->exists()) {
            return redirect()->route('login')->with('status', 'تم استكمال بيانات التشغيل الأساسية. يمكنك تسجيل الدخول.');
        }

        return back()->with('setup_status', 'تم حفظ بيانات الموقع والدولة والمحافظة والمدينة والشركة والفرع والمخزن بنجاح.');
    }

    public function createFirstAdmin(Request $request): RedirectResponse
    {
        $this->ensureAuthorized($request);
        abort_unless($this->isOperatingDataReady(), 409);
        abort_unless(Schema::hasTable('users') && Schema::hasTable('roles'), 409);
        abort_if(User::query()->exists(), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(12)],
        ], [
            'name.required' => 'اسم المدير مطلوب.',
            'username.required' => 'اسم المستخدم مطلوب.',
            'username.alpha_dash' => 'اسم المستخدم يقبل الحروف والأرقام والشرطة فقط.',
            'username.unique' => 'اسم المستخدم مستخدم بالفعل.',
            'email.email' => 'البريد الإلكتروني غير صحيح.',
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل.',
            'password.confirmed' => 'تأكيد كلمة المرور غير مطابق.',
            'password.min' => 'كلمة المرور يجب ألا تقل عن 12 حرفًا.',
        ]);

        try {
            DB::transaction(function () use ($validated): void {
                $role = Role::query()
                    ->where('key', 'system-access-admin')
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (User::query()->exists()) {
                    throw new \RuntimeException('First administrator setup is already closed.');
                }

                User::create([
                    'name' => $validated['name'],
                    'username' => $validated['username'],
                    'email' => $validated['email'] ?: null,
                    'password' => Hash::make($validated['password']),
                    'account_type' => 'admin',
                    'is_active' => true,
                ])->roles()->attach($role);
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['setup' => 'تعذر إنشاء المدير. تأكد من اكتمال التهيئة وعدم وجود مستخدم سابق.']);
        }

        $request->session()->forget('justm_setup_authorized_at');
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'تم إنشاء أول مدير. يمكنك تسجيل الدخول الآن.');
    }

    private function ensureAuthorized(Request $request): void
    {
        abort_if($this->isCompleted(), 404);
        abort_unless($this->isAuthorized(), 403);
    }

    private function isAuthorized(): bool
    {
        $authorizedAt = session('justm_setup_authorized_at');

        return is_numeric($authorizedAt)
            && now()->timestamp - (int) $authorizedAt <= 900
            && now()->timestamp >= (int) $authorizedAt;
    }

    private function isOperatingDataReady(): bool
    {
        try {
            return Schema::hasTable('system_settings')
                && Schema::hasTable('countries')
                && Schema::hasTable('governorates')
                && Schema::hasTable('cities')
                && Schema::hasTable('companies')
                && Schema::hasTable('branches')
                && Schema::hasTable('warehouses')
                && Country::query()->exists()
                && Governorate::query()->exists()
                && City::query()->exists()
                && Company::query()->exists()
                && Branch::query()->where('is_main', true)->exists()
                && Warehouse::query()->exists()
                && filled(SystemSetting::query()->where('setting_key', 'site_name')->value('setting_value'));
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function isCompleted(): bool
    {
        try {
            return Schema::hasTable('users')
                && User::query()->exists()
                && $this->isOperatingDataReady();
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
