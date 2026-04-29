<?php

declare(strict_types=1);

namespace App\Providers;

use App\Exceptions\ApiException;
use App\Support\ErrorCode;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            \App\Sbp\SbpGatewayInterface::class,
            fn () => match ((string) config('digitalbank.sbp.gateway')) {
                'mock' => new \App\Sbp\MockSbpGateway(),
                default => throw new \RuntimeException(
                    'Unsupported SBP gateway: '.config('digitalbank.sbp.gateway'),
                ),
            },
        );
    }

    public function boot(): void
    {
        $this->configureRateLimiters();
    }

    /**
     * Rate limiters from §7.4 of SPEC.
     *
     * Named limiters are applied per-route via the `throttle:<name>` middleware.
     * We also define a generic `api` limiter used as the fallback.
     */
    private function configureRateLimiters(): void
    {
        // Fallback for authenticated traffic.
        RateLimiter::for('api', function (Request $request) {
            $id = $request->user()?->id ?: $request->ip();

            return Limit::perMinute(60)->by($id)
                ->response(fn () => throw new ApiException(ErrorCode::TOO_MANY_REQUESTS));
        });

        RateLimiter::for('auth.login', function (Request $request) {
            $email = (string) $request->input('email', '');
            $key = $request->ip().'|'.strtolower($email);

            return Limit::perMinute(5)->by($key)
                ->response(fn () => throw new ApiException(ErrorCode::TOO_MANY_REQUESTS));
        });

        RateLimiter::for('auth.register', function (Request $request) {
            return Limit::perHour(3)->by($request->ip())
                ->response(fn () => throw new ApiException(ErrorCode::TOO_MANY_REQUESTS));
        });

        RateLimiter::for('transfers', function (Request $request) {
            $id = $request->user()?->id ?: $request->ip();

            return Limit::perMinute(30)->by($id)
                ->response(fn () => throw new ApiException(ErrorCode::TOO_MANY_REQUESTS));
        });

        RateLimiter::for('avatar', function (Request $request) {
            $id = $request->user()?->id ?: $request->ip();

            return Limit::perHour(5)->by($id)
                ->response(fn () => throw new ApiException(ErrorCode::TOO_MANY_REQUESTS));
        });
    }
}
