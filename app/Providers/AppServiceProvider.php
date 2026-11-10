<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        try {
            if (Schema::hasTable('system_settings')) {
                $siteName = DB::table('system_settings')->where('setting_key', 'site_name')->value('setting_value');
                if (is_string($siteName) && trim($siteName) !== '') {
                    config(['app.name' => $siteName]);
                }
            }
        } catch (Throwable $exception) {
            // The application must still boot before the database is installed.
        }

        RateLimiter::for('login', function (Request $request): Limit {
            $key = strtolower((string) $request->input('username')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });
    }
}
