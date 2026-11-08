<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ProtectDemoAccounts;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // `locale` stays readable outside the web middleware (e.g. branded 404 pages).
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'locale']);

        // Only the cloudflared container reaches Nginx. Nginx restores the visitor IP
        // from CF-Connecting-IP and passes the original scheme to PHP as HTTPS, so
        // X-Forwarded-* headers are only trusted from private addresses (local runs).
        $middleware->trustProxies(at: ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);

        $middleware->web(append: [
            SecurityHeaders::class,
            SetLocale::class,
            ProtectDemoAccounts::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Branded Inertia error pages (outside local debugging and the test suite).
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            if ($request->expectsJson() || app()->environment(['local', 'testing'])
                || ! in_array($status, [403, 404, 419, 429, 500, 503], true)) {
                return $response;
            }

            // Unmatched routes never run the web middleware, so resolve the
            // language and the shared props (auth, locale, demo…) here.
            App::setLocale(SetLocale::resolve($request));
            Inertia::share(app(HandleInertiaRequests::class)->share($request));

            return Inertia::render('Error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
