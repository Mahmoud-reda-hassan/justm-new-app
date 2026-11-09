<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Throwable;

class SetupController extends Controller
{
    public function show()
    {
        abort_if($this->isCompleted(), 404);

        $databaseReady = false;
        $adminExists = false;

        try {
            $databaseReady = Schema::hasTable('users');
            $adminExists = $databaseReady && User::query()->exists();
        } catch (Throwable $exception) {
            // The setup page must remain reachable when the configured database is not created yet.
            report($exception);
        }

        return view('setup', [
            'authorized' => $this->isAuthorized(),
            'databaseReady' => $databaseReady,
            'adminExists' => $adminExists,
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
            DB::connection()->getPdo();

            $migrationExit = Artisan::call('migrate', ['--force' => true]);
            if ($migrationExit !== 0) {
                report(new \RuntimeException('Just-M setup migrations returned a non-zero exit code.'));
                return back()->withErrors(['setup' => 'لم تكتمل إنشاءات قاعدة البيانات. راجع سجل التطبيق.']);
            }

            $seedExit = Artisan::call('db:seed', ['--force' => true]);
            if ($seedExit !== 0) {
                report(new \RuntimeException('Just-M setup seeding returned a non-zero exit code.'));
                return back()->withErrors(['setup' => 'تم إنشاء الجداول لكن تعذر إدخال البيانات الأولية. راجع سجل التطبيق.']);
            }

            return back()->with('setup_status', 'تم إنشاء الجداول وإدخال البيانات الأولية بنجاح.');
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['setup' => 'تعذر إكمال التهيئة. راجع الاتصال وصلاحيات قاعدة البيانات وسجل التطبيق.']);
        }
    }

    public function createFirstAdmin(Request $request): RedirectResponse
    {
        $this->ensureAuthorized($request);
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
                // Lock the single bootstrap role row so concurrent first-admin requests serialize.
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

    private function isCompleted(): bool
    {
        try {
            return Schema::hasTable('users') && User::query()->exists();
        } catch (Throwable $exception) {
            // An unavailable or not-yet-created database must not prevent the setup page from opening.
            report($exception);

            return false;
        }
    }
}
