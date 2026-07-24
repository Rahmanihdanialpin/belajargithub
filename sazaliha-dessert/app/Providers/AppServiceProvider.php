<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route; // Import Fasad Route
use Illuminate\Validation\Rules\Password; // Import Rule Password
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 1. Alias Middleware (Menggunakan Fasad Route agar lebih rapi)
        Route::aliasMiddleware('permission', PermissionMiddleware::class);
        Route::aliasMiddleware('role', RoleMiddleware::class);
        Route::aliasMiddleware('role_or_permission', RoleOrPermissionMiddleware::class);
        Route::aliasMiddleware('role.can.toggle', \App\Http\Middleware\RoleCanToggleMiddleware::class);

        // 2. Bypass Super Admin Berbasis Role (AMAIN & Standard Best Practice)
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });

        // 3. (Opsional) Penguatan Aturan Password Global untuk Seluruh Aplikasi
        Password::defaults(function () {
            return Password::min(8)
                ->letters()
                ->numbers()
                ->symbols();
        });
    }
}