<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /**
     * Verify a signed URL of the form:
     *   /api/auth/email/verify/{id}/{hash}?expires=...&signature=...
     *
     * `hash` is sha1(email) — exactly what Laravel's VerifyEmail notification
     * embeds. We validate the signature, then mark email_verified_at.
     */
    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        if (! $request->hasValidSignature()) {
            throw new ApiException(ErrorCode::FORBIDDEN, 'Неверная или просроченная ссылка');
        }

        /** @var User|null $user */
        $user = User::find($id);
        if ($user === null) {
            throw new ApiException(ErrorCode::USER_NOT_FOUND);
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw new ApiException(ErrorCode::FORBIDDEN, 'Подпись ссылки не соответствует пользователю');
        }

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success(['already_verified' => true], request: $request);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        $this->audit->record(
            action: 'user.email_verified',
            entityType: 'user',
            entityId: $user->id,
            userId: $user->id,
            request: $request,
        );

        return ApiResponse::success(['verified' => true], request: $request);
    }

    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            throw new ApiException(ErrorCode::UNAUTHENTICATED);
        }
        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success(['already_verified' => true], request: $request);
        }

        $user->sendEmailVerificationNotification();
        return ApiResponse::success(['sent' => true], request: $request);
    }
}
