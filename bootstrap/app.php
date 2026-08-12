<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use NielsNumbers\LaravelLocalizer\Middleware\RedirectLocale;
use NielsNumbers\LaravelLocalizer\Middleware\SetLocale;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SetLocale/RedirectLocale must run before HandleInertiaRequests:
        // the alternateUrls shared prop depends on the locale already
        // being resolved for the current request.
        $middleware->web(append: [
            SetLocale::class,
            RedirectLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Stripe posts here without a Laravel session/CSRF token. The
        // request is authenticated instead by verifying its signature
        // inside StripeGateway::handleWebhook() — see CLAUDE.md.
        $middleware->preventRequestForgery(except: [
            'webhooks/stripe',
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // This app has no api/* routes — but it does have plain-web JSON
        // endpoints like admin/dashboard/stats (see CLAUDE.md: fetched by
        // the period filter, not a full Inertia visit). Restricting to
        // api/* alone made Laravel fall back to its default HTML/redirect
        // handling for those, even for a request sending
        // Accept: application/json — expectsJson() also has to count.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
