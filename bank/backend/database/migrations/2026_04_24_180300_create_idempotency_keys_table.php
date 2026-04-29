<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            // PK = X-Idempotency-Key header value.
            $table->uuid('key')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('endpoint', 100);
            $table->smallInteger('response_status');
            $table->jsonb('response_body');
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at')->useCurrent();

            // §3.6 — cron cleanup relies on this.
            $table->index('expires_at', 'idempotency_keys_expires_idx');
            $table->index('user_id', 'idempotency_keys_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
