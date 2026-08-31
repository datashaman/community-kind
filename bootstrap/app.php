<?php

use App\Http\Middleware\EnforceAbsoluteSessionLifetime;
use App\Http\Middleware\EnforceDemoSandbox;
use App\Http\Middleware\EnforcePortalSession;
use App\Http\Middleware\EnsureInstallationAccess;
use App\Http\Middleware\EnsureTrustedApplicationHost;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ProtectSensitiveFortifyRoutes;
use App\Http\Middleware\SetOrganisationUrlDefaults;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(prepend: [
            EnsureTrustedApplicationHost::class,
        ], append: [
            EnsureInstallationAccess::class,
            EnforceAbsoluteSessionLifetime::class,
            EnforceDemoSandbox::class,
            EnforcePortalSession::class,
            ProtectSensitiveFortifyRoutes::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetOrganisationUrlDefaults::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
