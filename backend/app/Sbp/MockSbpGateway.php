<?php

declare(strict_types=1);

namespace App\Sbp;

use Illuminate\Support\Str;

/**
 * MVP stub. Returns deterministic "accepted" responses without any
 * network I/O. The transaction stays `pending` until a real gateway
 * adapter replaces this class in Stage-post-MVP.
 */
class MockSbpGateway implements SbpGatewayInterface
{
    public function initiateTransfer(array $payload): array
    {
        return [
            'provider_id' => 'mock-'.Str::uuid()->toString(),
            'status' => 'accepted',
        ];
    }

    public function getStatus(string $providerId): array
    {
        return [
            'provider_id' => $providerId,
            'status' => 'accepted',
        ];
    }
}
