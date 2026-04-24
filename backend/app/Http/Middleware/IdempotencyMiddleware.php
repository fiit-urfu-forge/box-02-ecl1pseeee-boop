<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\IdempotencyKey;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Implements §7.2 of SPEC.
 *
 * Contract:
 *   1. The header `X-Idempotency-Key` MUST be a valid UUID v4.
 *   2. The same key, from the same user, returning the same endpoint and
 *      the same request fingerprint, replays the saved response with HTTP 200.
 *   3. Same key, DIFFERENT request fingerprint (or different endpoint) ⇒
 *      `IDEMPOTENCY_CONFLICT` with HTTP 409.
 *   4. New key ⇒ handler runs. Only 2xx responses are cached. 4xx/5xx are
 *      NOT cached so the client can retry after fixing their input.
 *
 * The key row is written in a single INSERT ... ON CONFLICT DO NOTHING statement
 * to avoid TOCTOU races under concurrent retries.
 */
class IdempotencyMiddleware
{
    /**
     * Endpoints where the header is REQUIRED. Matches are done by route name
     * so URL changes don't silently disable the requirement.
     *
     * @var list<string>
     */
    private const REQUIRED_ROUTES = [
        'transfers.store',
        'sbp.transfer',
        'sbp.link-phone',
        'accounts.store',
    ];

    public function handle(Request $request, \Closure $next): Response
    {
        // Only mutating methods carry idempotency semantics.
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $header = $request->header('X-Idempotency-Key');
        $routeName = $request->route()?->getName() ?? '';
        $required = in_array($routeName, self::REQUIRED_ROUTES, true);

        if ($header === null || $header === '') {
            if ($required) {
                throw new ApiException(
                    ErrorCode::VALIDATION_ERROR,
                    'Заголовок X-Idempotency-Key обязателен для этой операции',
                    ['header' => 'X-Idempotency-Key'],
                );
            }

            return $next($request);
        }

        if (! Str::isUuid($header)) {
            throw new ApiException(
                ErrorCode::VALIDATION_ERROR,
                'X-Idempotency-Key должен быть UUID v4',
                ['value' => $header],
            );
        }

        $user = $request->user();
        if ($user === null) {
            // Auth middleware will already have 401'd; this is defence-in-depth.
            throw new ApiException(ErrorCode::UNAUTHENTICATED);
        }

        $endpoint = sprintf('%s %s', $request->method(), $request->path());
        $fingerprint = $this->fingerprint($request);

        // 1) Look for an existing record first — fast path for replays.
        /** @var IdempotencyKey|null $existing */
        $existing = IdempotencyKey::query()->find($header);
        if ($existing !== null) {
            return $this->handleExisting($existing, $user->id, $endpoint, $fingerprint, $request);
        }

        // 2) Reserve the key atomically. If INSERT wins, we run the handler.
        //    If another request inserted first, we fetch it and replay.
        $ttlHours = (int) config('digitalbank.idempotency.ttl_hours', 24);
        $now = now();
        $inserted = DB::table('idempotency_keys')->insertOrIgnore([
            'key' => $header,
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'response_status' => 0, // sentinel: "in-flight"
            'response_body' => json_encode([
                '_pending' => true,
                'fingerprint' => $fingerprint,
            ], JSON_UNESCAPED_UNICODE),
            'expires_at' => $now->copy()->addHours($ttlHours),
            'created_at' => $now,
        ]);

        if ($inserted === 0) {
            // Lost the race — fetch what the winner wrote.
            /** @var IdempotencyKey $winner */
            $winner = IdempotencyKey::query()->findOrFail($header);

            return $this->handleExisting($winner, $user->id, $endpoint, $fingerprint, $request);
        }

        // 3) We won the race — execute the handler and cache the result.
        try {
            /** @var Response $response */
            $response = $next($request);
        } catch (\Throwable $e) {
            // Roll back the reservation so the client can retry with a fresh run.
            DB::table('idempotency_keys')->where('key', $header)->delete();
            throw $e;
        }

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $body = $this->extractJsonBody($response);
            DB::table('idempotency_keys')->where('key', $header)->update([
                'response_status' => $response->getStatusCode(),
                'response_body' => json_encode([
                    'fingerprint' => $fingerprint,
                    'status' => $response->getStatusCode(),
                    'body' => $body,
                ], JSON_UNESCAPED_UNICODE),
            ]);
        } else {
            // Non-2xx ⇒ drop reservation so the client can retry.
            DB::table('idempotency_keys')->where('key', $header)->delete();
        }

        return $response;
    }

    private function handleExisting(
        IdempotencyKey $existing,
        string $userId,
        string $endpoint,
        string $fingerprint,
        Request $request,
    ): Response {
        if ($existing->user_id !== $userId || $existing->endpoint !== $endpoint) {
            throw new ApiException(ErrorCode::IDEMPOTENCY_CONFLICT);
        }

        $stored = $existing->response_body;

        // Check in-flight FIRST: a pending row is worthless for fingerprint
        // comparison (the fingerprint stored on it is the in-progress request,
        // not the completed one) and the correct outcome is always CONFLICT.
        if (($stored['_pending'] ?? false) === true || (int) $existing->response_status === 0) {
            throw new ApiException(
                ErrorCode::IDEMPOTENCY_CONFLICT,
                details: ['reason' => 'in_flight'],
            );
        }

        $storedFingerprint = $stored['fingerprint'] ?? null;
        if ($storedFingerprint !== null && $storedFingerprint !== $fingerprint) {
            throw new ApiException(
                ErrorCode::IDEMPOTENCY_CONFLICT,
                details: ['reason' => 'payload_mismatch'],
            );
        }

        $status = (int) ($stored['status'] ?? $existing->response_status);
        $body = $stored['body'] ?? [];

        return new JsonResponse($body, $status, [
            ApiResponse::REQUEST_ID_HEADER => (string) $request->attributes->get(
                ApiResponse::REQUEST_ID_ATTR,
                $existing->key,
            ),
            'X-Idempotent-Replayed' => '1',
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * A fingerprint binds a key to the exact payload it first saw. Two clients
     * sending the same key with different bodies must be rejected — otherwise
     * an attacker could replay an old success onto a new payload.
     */
    private function fingerprint(Request $request): string
    {
        // Sort JSON body keys so formatting differences don't matter.
        $body = $request->all();
        $this->recursiveKSort($body);

        return hash('sha256', json_encode([
            'path' => $request->path(),
            'method' => $request->method(),
            'body' => $body,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<array-key, mixed>  $input
     */
    private function recursiveKSort(array &$input): void
    {
        foreach ($input as &$v) {
            if (is_array($v)) {
                $this->recursiveKSort($v);
            }
        }
        if (array_is_list($input)) {
            return;
        }
        ksort($input);
    }

    /** @return array<string, mixed> */
    private function extractJsonBody(Response $response): array
    {
        $content = $response->getContent();
        if ($content === false || $content === '') {
            return [];
        }
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }
}
