<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Sanctum middleware
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

// Custom middlewares
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\VendorMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->alias([

            /*
            |--------------------------------------------------------------------------
            | Sanctum Ability Middleware
            |--------------------------------------------------------------------------
            */

            'abilities' => CheckAbilities::class,
            'ability'   => CheckForAnyAbility::class,

            /*
            |--------------------------------------------------------------------------
            | Application Middleware
            |--------------------------------------------------------------------------
            */

            'admin'      => EnsureUserIsAdmin::class,
            'superadmin' => EnsureSuperAdmin::class,
            'vendor'     => VendorMiddleware::class,

            /*
            |--------------------------------------------------------------------------
            | Optional Laravel Middleware
            |--------------------------------------------------------------------------
            */

            // 'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
            // 'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,

        ]);

    })

    ->withExceptions(function (Exceptions $exceptions): void {

        //

    })

    ->create();