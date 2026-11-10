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
use Illuminate\Support\Facades\Auth;
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
            'site_name' => ['nullable', 'string', 'max:150'],
            'country_name_ar' => ['required', 'string', 'max:100'],
            'country_name_en' => ['required', 'string', 'max:100'],
            'region_name' => ['nullable', 'string', 'max:100'],
            'governorate_name_ar' => ['required', 'string', 'max:100'],
            'governorate_name_en' => ['required', 'string', 'max:100'],
            'city_name_ar' => ['required', 'string', 'max:120'],
            'city_name_en' => ['required', 'string', 'max:120'],
            'company_name' => ['required', 'string', 'max:150'],
            'company_phone' => ['required', 'string', 'max:40'],
            'company_mobile' => ['nullable', 'string', 'max:40'],
            'company_email' => ['nullable', 'email', 'max:190'],
            'company_address' => ['nullable', 'string', 'max:2000'],
            'branch_name' => ['required', 'string', 'max:120'],
            'branch_address' => ['required', 'string', 'max:255'],
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

        $validated['site_name'] = $validated['site_name'] ?? $validated['company_name'];
        $validated['company_address'] = $validated['branch_address'] ?? null;

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

    public function createFirstAdmin(Request $request, InitialOperatingSetup $setup): RedirectResponse
    {
        $this->ensureAuthorized($request);
        abort_unless(Schema::hasTable('users') && Schema::hasTable('roles'), 409);
        abort_if(User::query()->exists(), 404);

        $needsOperatingData = ! $this->isOperatingDataReady();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(12)],
            'company_name' => [Rule::requiredIf($needsOperatingData), 'nullable', 'string', 'max:150'],
            'company_phone' => [Rule::requiredIf($needsOperatingData), 'nullable', 'string', 'max:40'],
            'branch_name' => [Rule::requiredIf($needsOperatingData), 'nullable', 'string', 'max:120'],
            'branch_address' => [Rule::requiredIf($needsOperatingData), 'nullable', 'string', 'max:255'],
            'country_name_ar' => [Rule::requiredIf($needsOperatingData), 'nullable', 'string', 'max:100'],
            'country_name_en' => ['nullable', 'string', 'max:100'],
            'region_name' => ['nullable', 'string', 'max:100'],
            'governorate_name_ar' => [Rule::requiredIf($needsOperatingData), 'nullable', 'string', 'max:100'],
            'governorate_name_en' => ['nullable', 'string', 'max:100'],
            'city_name_ar' => [Rule::requiredIf($needsOperatingData), 'nullable', 'string', 'max:120'],
            'city_name_en' => ['nullable', 'string', 'max:120'],
            'warehouse_name' => [Rule::requiredIf($needsOperatingData), 'nullable', 'string', 'max:150'],
            'warehouse_type' => [
                Rule::requiredIf($needsOperatingData),
                'nullable',
                Rule::exists('warehouse_types', 'key')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ], [
            'name.required' => 'اسم المدير مطلوب.',
            'username.required' => 'اسم المستخدم مطلوب.',
            'username.alpha_dash' => 'اسم المستخدم يقبل الحروف والأرقام والشرطة فقط.',
            'username.unique' => 'اسم المستخدم مستخدم بالفعل.',
            'email.required' => 'البريد الإلكتروني للمدير مطلوب لاستعادة كلمة المرور.',
            'email.email' => 'البريد الإلكتروني غير صحيح.',
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل.',
            'password.confirmed' => 'تأكيد كلمة المرور غير مطابق.',
            'password.min' => 'كلمة المرور يجب ألا تقل عن 12 حرفًا.',
            'company_name.required' => 'اسم الشركة أو المؤسسة مطلوب.',
            'company_phone.required' => 'رقم هاتف المؤسسة الأول مطلوب.',
            'branch_name.required' => 'اسم الفرع الرئيسي مطلوب.',
            'branch_address.required' => 'عنوان الفرع الرئيسي مطلوب.',
            'country_name_ar.required' => 'الدولة مطلوبة.',
            'governorate_name_ar.required' => 'المحافظة مطلوبة.',
            'city_name_ar.required' => 'المدينة مطلوبة.',
            'warehouse_name.required' => 'اسم المخزن الأول مطلوب.',
            'warehouse_type.required' => 'نوع المخزن مطلوب.',
        ]);

        $user = null;

        try {
            DB::transaction(function () use ($validated, $setup, $needsOperatingData, &$user): void {
                $role = Role::query()
                    ->where('key', 'system-access-admin')
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (User::query()->exists()) {
                    throw new \RuntimeException('First administrator setup is already closed.');
                }

                if ($needsOperatingData) {
                    $setup->configure([
                        'site_name' => $validated['company_name'],
                        'country_name_ar' => $validated['country_name_ar'],
                        'country_name_en' => ($validated['country_name_en'] ?? null) ?: $validated['country_name_ar'],
                        'region_name' => $validated['region_name'] ?? null,
                        'governorate_name_ar' => $validated['governorate_name_ar'],
                        'governorate_name_en' => ($validated['governorate_name_en'] ?? null) ?: $validated['governorate_name_ar'],
                        'city_name_ar' => $validated['city_name_ar'],
                        'city_name_en' => ($validated['city_name_en'] ?? null) ?: $validated['city_name_ar'],
                        'company_name' => $validated['company_name'],
                        'company_phone' => $validated['company_phone'],
                        'company_address' => $validated['branch_address'],
                        'branch_name' => $validated['branch_name'],
                        'branch_address' => $validated['branch_address'],
                        'warehouse_name' => $validated['warehouse_name'],
                        'warehouse_type' => $validated['warehouse_type'],
                    ]);
                }

                $user = User::create([
                    'name' => $validated['name'],
                    'username' => $validated['username'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'account_type' => 'admin',
                    'is_active' => true,
                ]);

                $user->roles()->attach($role);
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors(['setup' => 'تعذر إكمال التهيئة وإنشاء المدير. لم يتم اعتماد أي جزء من البيانات؛ راجع المدخلات وسجل التطبيق.']);
        }

        $request->session()->forget('justm_setup_authorized_at');
        $request->session()->regenerateToken();

        Auth::login($user);
        $request->session()->regenerate();

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('verification.notice')
                ->with('warning', 'تم إنشاء الحساب، لكن تعذر إرسال رسالة التحقق. راجع إعدادات البريد ثم أعد الإرسال.');
        }

        return redirect()->route('verification.notice')
            ->with('status', 'تم إنشاء المدير والمؤسسة والفرع الرئيسي والمخزن. أرسلنا رسالة تحقق إلى بريدك الإلكتروني.');
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
