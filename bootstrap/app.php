<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'ensure.dosen.affiliation' => \App\Http\Middleware\EnsureDosenAffiliation::class,
            'ensure.email.verified' => \App\Http\Middleware\EnsureEmailVerified::class,
        ]);

        // Catat waktu terakhir aktif user pada setiap request.
        $middleware->append(\App\Http\Middleware\UpdateLastActive::class);

        // Percaya reverse-proxy (nginx di docker network) agar header
        // X-Forwarded-Proto diteruskan -> Laravel tahu skema https.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
