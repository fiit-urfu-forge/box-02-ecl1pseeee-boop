<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sbp;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook endpoint §5.4 / §7.5. Verifies the partner signature
 * (HMAC-SHA256 of the raw request body) and records every attempt in
 * audit_log. Actual status processing is a TODO for post-MVP.
 */
class SbpWebhookController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        $secret = (string) config('digitalbank.sbp.webhook_secret');
        if ($secret === '') {
            // No secret configured → refuse to silently accept.
            throw new ApiException(ErrorCode::FORBIDDEN, 'Вебхук не настроен');
        }

        $signature = (string) $request->header('X-Sbp-Signature', '');
        $raw = (string) $request->getContent();
        $expected = hash_hmac('sha256', $raw, $secret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            $this->audit->record(
                action: 'sbp.webhook_bad_signature',
                entityType: 'webhook',
                entityId: null,
                userId: null,
                new: ['ip' => $request->ip(), 'body_bytes' => strlen($raw)],
                request: $request,
            );
            Log::channel('security')->error('sbp.webhook.bad_signature', [
                'ip' => $request->ip(),
                'bytes' => strlen($raw),
            ]);
            throw new ApiException(ErrorCode::UNAUTHENTICATED, 'Неверная подпись вебхука');
        }

        // MVP: accept and acknowledge. Real processing (tx state update,
        // sbp_in credit) is out of scope and will land with a real adapter.
        $payload = json_decode($raw, true);
        $this->audit->record(
            action: 'sbp.webhook_received',
            entityType: 'webhook',
            entityId: null,
            userId: null,
            new: is_array($payload) ? $payload : ['raw' => substr($raw, 0, 2000)],
            request: $request,
        );

        return ApiResponse::success(['accepted' => true], request: $request);
    }
}
