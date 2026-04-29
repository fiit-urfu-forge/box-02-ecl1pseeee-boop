<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UploadAvatarRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $this->mustHaveUser($request);

        return ApiResponse::success($this->payload($user), request: $request);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->mustHaveUser($request);
        $old = $user->only(['first_name', 'last_name', 'phone']);
        $user->fill($request->validated());
        $user->save();

        $this->audit->record(
            action: 'user.profile_updated',
            entityType: 'user',
            entityId: $user->id,
            userId: $user->id,
            old: $old,
            new: $user->only(['first_name', 'last_name', 'phone']),
            request: $request,
        );

        return ApiResponse::success($this->payload($user), request: $request);
    }

    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = $this->mustHaveUser($request);

        $file = $request->file('avatar');
        $ext = strtolower($file->extension() ?: $file->getClientOriginalExtension());
        $key = sprintf('avatars/%s/%s.%s', $user->id, (string) Str::uuid(), $ext);

        // Remove old file if any.
        if ($user->avatar_path !== null && Storage::disk($this->disk())->exists($user->avatar_path)) {
            Storage::disk($this->disk())->delete($user->avatar_path);
        }

        Storage::disk($this->disk())->putFileAs(
            dirname($key),
            $file,
            basename($key),
            ['visibility' => 'private'],
        );

        $user->avatar_path = $key;
        $user->save();

        $this->audit->record(
            action: 'user.avatar_uploaded',
            entityType: 'user',
            entityId: $user->id,
            userId: $user->id,
            new: ['avatar_path' => $key],
            request: $request,
        );

        return ApiResponse::success($this->payload($user), request: $request);
    }

    /**
     * Stream the current user's avatar back. For MVP we serve locally —
     * in Prod this endpoint would 302 to a signed S3 URL instead.
     */
    public function showAvatar(Request $request): StreamedResponse
    {
        $user = $this->mustHaveUser($request);
        if ($user->avatar_path === null) {
            throw new ApiException(ErrorCode::NOT_FOUND, 'Аватар не загружен');
        }

        $disk = Storage::disk($this->disk());
        if (! $disk->exists($user->avatar_path)) {
            throw new ApiException(ErrorCode::NOT_FOUND, 'Файл аватара отсутствует');
        }

        return $disk->response(
            $user->avatar_path,
            basename($user->avatar_path),
            ['Cache-Control' => 'private, max-age=300'],
        );
    }

    private function mustHaveUser(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new ApiException(ErrorCode::UNAUTHENTICATED);
        }
        return $user;
    }

    private function disk(): string
    {
        return (string) config('filesystems.default', 'local');
    }

    /** @return array<string, mixed> */
    private function payload(User $user): array
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
