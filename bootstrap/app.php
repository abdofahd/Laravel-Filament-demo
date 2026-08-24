<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // TLS terminates at Coolify's reverse proxy, so the request reaching
        // PHP is plain HTTP. Trusting the proxy makes Laravel read
        // X-Forwarded-Proto, which matters beyond cosmetics: signed URLs are
        // verified against $request->getSchemeAndHttpHost(), so without this a
        // link generated as https is checked as http and rejected with a 401 —
        // which is exactly how Livewire file uploads fail.
        // It also restores the real client IP for logging and rate limiting.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
