<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class DatabaseUpdateController extends Controller
{
    private function ensureManager(Request $request): void
    {
        abort_unless($request->user()?->is_active
            && $request->user()->roles()->where('roles.key', 'system-access-admin')->where('roles.is_active', true)->exists(), 403);
    }

    private function migrationState(): array
    {
        $files = app('migrator')->getMigrationFiles(database_path('migrations'));
        $ran = Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration')->all() : [];
        $rows = [];

        foreach ($files as $name => $path) {
            $rows[] = ['name' => $name, 'ran' => in_array($name, $ran, true)];
        }

        return $rows;
    }

    public function index(Request $request): View
    {
        $this->ensureManager($request);
        $connection = config('database.default');
        $settings = config("database.connections.{$connection}", []);
        $migrations = $this->migrationState();

        return view('admin.system.database-updates', [
            'driver' => $settings['driver'] ?? $connection,
            'host' => $settings['host'] ?? ($settings['unix_socket'] ?? 'محلي'),
            'database' => $settings['database'] ?? '',
            'migrations' => $migrations,
            'pending' => collect($migrations)->where('ran', false)->values(),
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $this->ensureManager($request);
        $connection = config('database.default');
        $settings = config("database.connections.{$connection}", []);
        $database = (string) ($settings['database'] ?? '');

        $request->validate([
            'database_confirmation' => ['required', 'string', 'in:'.$database],
            'run_confirmation' => ['required', 'in:RUN MIGRATIONS'],
        ], [
            'database_confirmation.in' => 'اسم قاعدة البيانات لا يطابق الاتصال الحالي.',
            'run_confirmation.in' => 'أكد التنفيذ بكتابة RUN MIGRATIONS.',
        ]);

        abort_if($database === '', 409, 'اسم قاعدة البيانات غير مضبوط.');

        $lockName = 'justm_migrations_'.substr(sha1($database), 0, 24);
        $lockAcquired = false;

        try {
            DB::connection()->getPdo();

            if (in_array($settings['driver'] ?? null, ['mysql', 'mariadb'], true)) {
                $lock = DB::selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lockName]);
                $lockAcquired = (int) ($lock->acquired ?? 0) === 1;
                abort_unless($lockAcquired, 409, 'يوجد تحديث آخر قيد التنفيذ. حاول لاحقًا.');
            }

            if (collect($this->migrationState())->where('ran', false)->isEmpty()) {
                return back()->with('status', 'قاعدة البيانات محدثة بالفعل، لا توجد migrations معلقة.');
            }

            $exitCode = Artisan::call('migrate', [
                '--force' => true,
                '--no-interaction' => true,
            ]);

            if ($exitCode !== 0) {
                report(new \RuntimeException('Browser database migration command failed.'));
                return back()->withErrors(['update' => 'توقف تحديث قاعدة البيانات بسبب خطأ. راجع سجل التطبيق قبل إعادة المحاولة.']);
            }

            $after = collect($this->migrationState())->where('ran', false)->count();

            return back()->with('status', $after === 0
                ? 'تم تحديث قاعدة البيانات بنجاح. لا توجد migrations معلقة.'
                : 'انتهى الأمر، لكن ما زالت هناك migrations معلقة؛ راجع سجل التطبيق.');
        } catch (Throwable $exception) {
            report($exception);
            return back()->withErrors(['update' => 'تعذر تنفيذ التحديث. لم يتم تشغيل أي Seeder أو إنشاء قاعدة بيانات جديدة. راجع سجل التطبيق.']);
        } finally {
            if ($lockAcquired) {
                try {
                    DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }
    }
}
