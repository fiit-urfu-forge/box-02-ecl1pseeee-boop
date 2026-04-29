<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLogEntry;
use Illuminate\Http\Request;

/**
 * Writes one row into `audit_log` per significant event. Called from
 * controllers / jobs; never throws on failure (an audit hiccup must not
 * take down a business operation), but any exception is logged via the
 * default `security` file channel so it's still visible.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function record(
        string $action,
        string $entityType,
        ?string $entityId = null,
        ?string $userId = null,
        ?array $old = null,
        ?array $new = null,
        ?Request $request = null,
    ): void {
        $request ??= request();

        try {
            AuditLogEntry::create([
                'user_id' => $userId ?? $request?->user()?->id,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            logger()->channel('stack')->warning('audit_log.write_failed', [
                'action' => $action,
                'entity_type' => $entityType,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
