<?php

use App\Http\Middleware\EnsureDosenAffiliation;
use App\Http\Middleware\EnsureDosenPendingApproval;
use App\Http\Middleware\EnsureEmailVerified;
use App\Http\Middleware\UpdateLastActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'ensure.dosen.affiliation' => EnsureDosenAffiliation::class,
            'ensure.email.verified' => EnsureEmailVerified::class,
            'ensure.dosen.decision' => EnsureDosenPendingApproval::class,
        ]);

        // Catat waktu terakhir aktif user pada setiap request web.
        // Dipasang di grup `web` (bukan global) karena butuh session untuk
        // membaca Auth::user(); middleware global berjalan sebelum StartSession.
        $middleware->appendToGroup('web', UpdateLastActive::class);

        // Percaya reverse-proxy (bila dikonfigurasi via TRUSTED_PROXIES) agar
        // header X-Forwarded-Proto diteruskan -> Laravel tahu skema https.
        // Default kosong = tidak memercayai header forwarded (aman) — menghindari
        // TypeError Symfony IpUtils saat X-Forwarded-For kosong/null (Bug #1).
        // Catatan: jangan pakai config() helper di sini — closure withMiddleware
        // jalan saat aplikasi masih di-construct, sebelum binding `config` di
        // container ter-resolve (=> "Target class [config] does not exist").
        // env() aman karena hanya membaca environment, tanpa membutuhkan container.
        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', ''))
        )));
        $middleware->trustProxies(at: $trustedProxies);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
