<?php

use App\Http\Middleware\EnsureAlumniCanUsePortal;
use App\Http\Middleware\EnsureAlumniHasPermission;
use App\Http\Middleware\EnsureAlumniPasswordChanged;
use App\Http\Middleware\EnsureUserHasOrganization;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
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

        $middleware->web(append: [
            HandleAppearance::class,
            EnsureUserIsActive::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'organization' => EnsureUserHasOrganization::class,
            'super-admin' => EnsureUserIsSuperAdmin::class,
            'alumni' => EnsureAlumniCanUsePortal::class,
            'alumni-password' => EnsureAlumniPasswordChanged::class,
            'portal-can' => EnsureAlumniHasPermission::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request): string {
            if (! $request->is('portail*')) {
                return route('login');
            }

            $slug = $request->hasSession()
                ? $request->session()->get('portal_organization')
                : null;

            return is_string($slug)
                ? route('portal.login', ['organization' => $slug])
                : url('/');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
