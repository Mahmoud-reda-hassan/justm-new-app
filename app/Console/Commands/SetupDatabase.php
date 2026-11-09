<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use PDO;
use RuntimeException;
use Throwable;

class SetupDatabase extends Command
{
    protected $signature = 'justm:setup
        {--create-database : Create the configured MariaDB/MySQL database if it does not exist}';

    protected $description = 'Create the Just-M database schema and insert the approved initial data';

    public function handle(): int
    {
        if ($this->option('create-database') && ! $this->createDatabaseIfMissing()) {
            return self::FAILURE;
        }

        try {
            $connection = config('database.default');
            $database = config("database.connections.{$connection}.database");

            if (! $database) {
                throw new RuntimeException('اسم قاعدة البيانات غير مضبوط في ملف البيئة.');
            }

            // Force a fresh connection after a possible CREATE DATABASE operation.
            app('db')->purge($connection);
            app('db')->connection($connection)->getPdo();

            $this->info("قاعدة البيانات جاهزة: {$database}");

            $migrationExit = Artisan::call('migrate', ['--force' => true]);
            $this->output->write(Artisan::output());

            if ($migrationExit !== self::SUCCESS) {
                return self::FAILURE;
            }

            $seedExit = Artisan::call('db:seed', ['--force' => true]);
            $this->output->write(Artisan::output());

            if ($seedExit !== self::SUCCESS) {
                return self::FAILURE;
            }

            $this->info('اكتمل إنشاء الجداول وإدخال البيانات الأولية.');
            $this->line('لإنشاء أول مدير استخدم: php artisan justm:provision-first-admin');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('تعذر إكمال التهيئة. راجع إعدادات الاتصال وصلاحيات قاعدة البيانات وسجل التطبيق.');

            return self::FAILURE;
        }
    }

    private function createDatabaseIfMissing(): bool
    {
        $connection = config('database.default');
        $settings = config("database.connections.{$connection}");

        if (! in_array($settings['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->error('إنشاء قاعدة البيانات تلقائيًا متاح لاتصال MySQL/MariaDB فقط.');

            return false;
        }

        $database = (string) ($settings['database'] ?? '');
        if (! preg_match('/^[A-Za-z0-9_\$]+$/', $database)) {
            $this->error('اسم قاعدة البيانات غير صالح أو غير مدعوم للإنشاء التلقائي.');

            return false;
        }

        try {
            $charset = $settings['charset'] ?? 'utf8mb4';
            $collation = $settings['collation'] ?? 'utf8mb4_unicode_ci';
            if (! preg_match('/^[A-Za-z0-9_]+$/', $charset) || ! preg_match('/^[A-Za-z0-9_]+$/', $collation)) {
                throw new RuntimeException('Invalid database charset or collation.');
            }

            $socket = $settings['unix_socket'] ?? '';
            $dsn = $socket !== ''
                ? "mysql:unix_socket={$socket};charset={$charset}"
                : sprintf('mysql:host=%s;port=%s;charset=%s', $settings['host'] ?? '127.0.0.1', $settings['port'] ?? 3306, $charset);

            $pdo = new PDO($dsn, $settings['username'] ?? '', $settings['password'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET {$charset} COLLATE {$collation}");
            $this->info("تم التأكد من وجود قاعدة البيانات: {$database}");

            return true;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('تعذر إنشاء قاعدة البيانات. تأكد من صلاحية CREATE DATABASE وبيانات الاتصال.');

            return false;
        }
    }
}
