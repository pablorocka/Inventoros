<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the reverse proxy (Caddy/Traefik/nginx, etc.) that terminates
        // TLS in front of the app. Without this, Request::isSecure() ignores
        // the X-Forwarded-Proto header and Laravel keeps generating http://
        // URLs (pagination links, redirects, asset URLs) behind an https
        // front door. Configurable via TRUSTED_PROXIES; defaults to trusting
        // all proxies, which is safe when the app isn't directly reachable
        // from the internet (the standard docker-deploy topology).
        $trustedProxies = env('TRUSTED_PROXIES', '*');

        $middleware->trustProxies(
            at: $trustedProxies === '*' ? '*' : array_map('trim', explode(',', $trustedProxies)),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->web(append: [
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\CheckInstallation::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'manager' => \App\Http\Middleware\EnsureUserIsManager::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'api.permission' => \App\Http\Middleware\CheckApiPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
