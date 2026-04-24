<?php

declare(strict_types=1);

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\IdempotencyMiddleware;
use App\Support\ApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'idempotency' => IdempotencyMiddleware::class,
        ]);

        $middleware->api(prepend: [
            AssignRequestId::class,
            ForceJsonResponse::class,
            EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }
            return ApiResponse::fromException($e, $request);
        });
    })
    ->create();
