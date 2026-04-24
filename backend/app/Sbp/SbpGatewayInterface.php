<?php

declare(strict_types=1);

namespace App\Sbp;

/**
 * Contract for SBP integrations. In MVP only `MockSbpGateway` is provided —
 * production environments will swap in a real partner adapter without
 * touching callers.
 */
interface SbpGatewayInterface
{
    /**
     * Hand off an outbound transfer to SBP. Returns a provider-side id that
     * subsequent webhook callbacks will reference.
     *
     * @param  array<string, mixed>  $payload
     * @return array{provider_id: string, status: string}
     */
    public function initiateTransfer(array $payload): array;

    /**
     * Poll SBP for the current status of a previously-initiated transfer.
     *
     * @return array{provider_id: string, status: string}
     */
    public function getStatus(string $providerId): array;
}
