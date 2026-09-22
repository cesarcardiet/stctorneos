<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('stc:dispatch-scheduled-notifications')->everyMinute();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => \App\Http\Middleware\EnsurePermission::class,
            'admin.web' => \App\Http\Middleware\EnsureAdminWebAccess::class,
            'player.account' => \App\Http\Middleware\EnsurePlayerAccount::class,
            'tutor.account' => \App\Http\Middleware\EnsureTutorAccount::class,
            'staff.workspace' => \App\Http\Middleware\EnsureStaffWorkspace::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
