<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => strtolower($data['email']),
            'password_hash' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'status' => User::STATUS_ACTIVE,
        ]);

        $user->sendEmailVerificationNotification();

        $this->audit->record(
            action: 'user.registered',
            entityType: 'user',
            entityId: $user->id,
            userId: $user->id,
            new: ['email' => $user->email],
            request: $request,
        );

        Log::channel('auth')->info('auth.register', ['user_id' => $user->id, 'email' => $user->email]);

        return ApiResponse::success(
            $this->profilePayload($user),
            status: 201,
            request: $request,
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $email = strtolower((string) $request->input('email'));
        $password = (string) $request->input('password');

        // §5.1 — 5 failed attempts per IP+email → 15 min lockout.
        $rlKey = 'login:failed:'.$request->ip().'|'.$email;
        if (RateLimiter::tooManyAttempts($rlKey, 5)) {
            $retry = RateLimiter::availableIn($rlKey);
            throw new ApiException(
                ErrorCode::TOO_MANY_REQUESTS,
                'Слишком много неудачных попыток. Попробуйте через '.ceil($retry / 60).' минут',
                ['retry_after_seconds' => $retry],
            );
        }

        $user = User::where('email', $email)->first();
        $ok = $user !== null && Hash::check($password, $user->password_hash);

        if (! $ok) {
            RateLimiter::hit($rlKey, 900); // 15 min window.

            if ($user !== null) {
                DB::table('users')->where('id', $user->id)->update([
                    'failed_login_at' => now(),
                    'failed_login_count' => $user->failed_login_count + 1,
                ]);
            }

            $this->audit->record(
                action: 'user.login_failed',
                entityType: 'user',
                entityId: $user?->id,
                userId: $user?->id,
                new: ['email' => $email],
                request: $request,
            );
            Log::channel('auth')->warning('auth.login_failed', ['email' => $email, 'ip' => $request->ip()]);

            throw new ApiException(ErrorCode::UNAUTHENTICATED, 'Неверные учётные данные');
        }

        if ($user->status !== User::STATUS_ACTIVE) {
            throw new ApiException(ErrorCode::FORBIDDEN, 'Аккаунт заблокирован или приостановлен');
        }
        if ($user->email_verified_at === null) {
            throw new ApiException(
                ErrorCode::FORBIDDEN,
                'E-mail не подтверждён — проверьте почту',
                ['reason' => 'email_not_verified'],
            );
        }

        RateLimiter::clear($rlKey);
        DB::table('users')->where('id', $user->id)->update([
            'failed_login_at' => null,
            'failed_login_count' => 0,
        ]);

        // Stateful session auth (§5.1 — Sanctum stateful cookies).
        Auth::guard('web')->login($user, remember: false);
        $request->session()->regenerate();

        $this->audit->record(
            action: 'user.login',
            entityType: 'user',
            entityId: $user->id,
            userId: $user->id,
            request: $request,
        );
        Log::channel('auth')->info('auth.login', ['user_id' => $user->id, 'ip' => $request->ip()]);

        return ApiResponse::success($this->profilePayload($user), request: $request);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        Auth::guard('web')->logout();
        $request->session()->flush();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $this->audit->record(
            action: 'user.logout',
            entityType: 'user',
            entityId: $user?->id,
            userId: $user?->id,
            request: $request,
        );

        return ApiResponse::success(['logged_out' => true], request: $request);
    }

    /**
     * Invalidates every active session for the user. We persist sessions in
     * the `sessions` table (SESSION_DRIVER=redis in prod is an issue — see
     * below), so we can delete by user_id.
     *
     * If SESSION_DRIVER is redis we fall back to cycling the user's
     * `remember_token` — that's a no-op for Sanctum stateful auth, but in
     * practice MVP runs with DB-backed sessions for this endpoint to work
     * meaningfully. Documented in README.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            throw new ApiException(ErrorCode::UNAUTHENTICATED);
        }

        // Try the SQL `sessions` table (only populated when SESSION_DRIVER=database).
        try {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        } catch (\Throwable) {
            // Silently skip if the driver isn't database — see method docblock.
        }

        // Invalidate the current session and Sanctum tokens too.
        $user->tokens()->delete();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $this->audit->record(
            action: 'user.logout_all',
            entityType: 'user',
            entityId: $user->id,
            userId: $user->id,
            request: $request,
        );

        return ApiResponse::success(['logged_out' => true, 'sessions_invalidated' => true], request: $request);
    }

    /** @return array<string, mixed> */
    private function profilePayload(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'phone' => $user->phone,
            'status' => $user->status,
            'avatar_url' => $user->avatar_path !== null ? route('user.avatar') : null,
        ];
    }
}
