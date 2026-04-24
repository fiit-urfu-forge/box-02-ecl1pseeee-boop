<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ApiException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Single source of truth for the API envelope (§6.1 of SPEC).
 *
 *   success = { success: true,  data: ..., meta: { timestamp, request_id } }
 *   error   = { success: false, error: { code, message, details? },
 *               meta: { timestamp, request_id } }
 */
final class ApiResponse
{
    public const REQUEST_ID_ATTR = 'request_id';
    public const REQUEST_ID_HEADER = 'X-Request-Id';

    /**
     * @param  array<string, mixed>|\JsonSerializable|null  $data
     * @param  array<string, mixed>  $extraMeta
     */
    public static function success(
        mixed $data = null,
        int $status = 200,
        array $extraMeta = [],
        ?Request $request = null,
    ): JsonResponse {
        $payload = [
            'success' => true,
            'data' => $data,
            'meta' => self::meta($request) + $extraMeta,
        ];

        return self::json($payload, $status, $request);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function error(
        ErrorCode $code,
        ?string $message = null,
        array $details = [],
        ?int $status = null,
        ?Request $request = null,
    ): JsonResponse {
        $error = [
            'code' => $code->value,
            'message' => $message ?? $code->defaultMessage(),
        ];
        if ($details !== []) {
            $error['details'] = $details;
        }

        $payload = [
            'success' => false,
            'error' => $error,
            'meta' => self::meta($request),
        ];

        return self::json($payload, $status ?? $code->httpStatus(), $request);
    }

    public static function paginated(
        LengthAwarePaginator $paginator,
        ?callable $mapper = null,
        ?Request $request = null,
    ): JsonResponse {
        $items = $paginator->items();
        if ($mapper !== null) {
            $items = array_map($mapper, $items);
        }

        $payload = [
            'success' => true,
            'data' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'meta' => self::meta($request),
        ];

        return self::json($payload, 200, $request);
    }

    /**
     * Map any exception to the standard error envelope. Called from the
     * global exception handler so controllers never need to catch + wrap.
     */
    public static function fromException(\Throwable $e, Request $request): JsonResponse
    {
        if ($e instanceof ApiException) {
            return self::error($e->errorCode, $e->getMessage(), $e->details(), $e->httpStatus(), $request);
        }

        if ($e instanceof ValidationException) {
            return self::error(
                ErrorCode::VALIDATION_ERROR,
                'Ошибка валидации входных данных',
                ['fields' => $e->errors()],
                422,
                $request,
            );
        }

        if ($e instanceof AuthenticationException) {
            return self::error(ErrorCode::UNAUTHENTICATED, request: $request);
        }

        if ($e instanceof AuthorizationException) {
            return self::error(ErrorCode::FORBIDDEN, request: $request);
        }

        if ($e instanceof TokenMismatchException) {
            return self::error(ErrorCode::FORBIDDEN, 'Недействительный CSRF-токен', status: 419, request: $request);
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return self::error(ErrorCode::NOT_FOUND, request: $request);
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return self::error(ErrorCode::METHOD_NOT_ALLOWED, request: $request);
        }

        if ($e instanceof TooManyRequestsHttpException) {
            return self::error(ErrorCode::TOO_MANY_REQUESTS, request: $request);
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $code = match (true) {
                $status === 401 => ErrorCode::UNAUTHENTICATED,
                $status === 403 => ErrorCode::FORBIDDEN,
                $status === 404 => ErrorCode::NOT_FOUND,
                $status === 405 => ErrorCode::METHOD_NOT_ALLOWED,
                $status === 409 => ErrorCode::IDEMPOTENCY_CONFLICT,
                $status === 429 => ErrorCode::TOO_MANY_REQUESTS,
                default => ErrorCode::INTERNAL_ERROR,
            };
            return self::error($code, $e->getMessage() ?: null, status: $status, request: $request);
        }

        $debug = (bool) config('app.debug');
        return self::error(
            ErrorCode::INTERNAL_ERROR,
            $debug ? $e->getMessage() : null,
            $debug ? ['trace' => explode("\n", $e->getTraceAsString())] : [],
            500,
            $request,
        );
    }

    /** @return array{timestamp: string, request_id: string} */
    private static function meta(?Request $request): array
    {
        $rid = $request?->attributes->get(self::REQUEST_ID_ATTR)
            ?? request()->attributes->get(self::REQUEST_ID_ATTR)
            ?? (string) Str::uuid();

        return [
            'timestamp' => now()->toIso8601String(),
            'request_id' => $rid,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function json(array $payload, int $status, ?Request $request): JsonResponse
    {
        $rid = $payload['meta']['request_id'] ?? null;
        $response = new JsonResponse($payload, $status, [], JSON_UNESCAPED_UNICODE);
        if ($rid !== null) {
            $response->headers->set(self::REQUEST_ID_HEADER, $rid);
        }

        return $response;
    }
}
