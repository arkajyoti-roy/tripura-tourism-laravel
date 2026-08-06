<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    // Register any application services.
    public function register(): void
    {
        //
    }

    // Bootstrap any application services.
    public function boot(): void
    {
        if (!app()->runningInConsole()) {
            config(['app.url' => request()->schemeAndHttpHost()]);
        }

        $conn = null;
        if (!request()->is('api/*')) {
            try {
                $conn = mysqli_connect(
                    config('database.connections.mysql.host', '127.0.0.1'),
                    config('database.connections.mysql.username', 'root'),
                    config('database.connections.mysql.password', ''),
                    config('database.connections.mysql.database', 'tripura_tourism'),
                    config('database.connections.mysql.port', '3306')
                );
            } catch (\Throwable $e) {
                // Log the error or handle it gracefully
                report($e);
            }
        }
        view()->share('conn', $conn);
    }
}
