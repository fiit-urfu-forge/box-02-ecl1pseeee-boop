<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table): void {
            // BIGSERIAL per §3.5 — high-volume append-only table.
            $table->bigIncrements('id');
            // NULL for system events.
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('entity_type', 50);
            $table->uuid('entity_id')->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            // INET column is not in the Laravel schema builder DSL; use raw.
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            // §3.6 — audit per user, newest first.
            $table->index(['user_id', 'created_at'], 'audit_log_user_created_idx');
            $table->index(['entity_type', 'entity_id'], 'audit_log_entity_idx');
        });

        // Narrow the ip_address column type to INET for proper IPv4/IPv6 handling.
        DB::statement('ALTER TABLE audit_log ALTER COLUMN ip_address TYPE INET USING ip_address::inet');

        // Enforce append-only semantics at the DB level: UPDATE and DELETE are forbidden.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_log_immutable() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit_log is append-only: % is forbidden', TG_OP
                  USING ERRCODE = 'restrict_violation';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER audit_log_no_update
                BEFORE UPDATE ON audit_log
                FOR EACH ROW EXECUTE FUNCTION audit_log_immutable();

            CREATE TRIGGER audit_log_no_delete
                BEFORE DELETE ON audit_log
                FOR EACH ROW EXECUTE FUNCTION audit_log_immutable();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS audit_log_no_delete ON audit_log');
        DB::statement('DROP TRIGGER IF EXISTS audit_log_no_update ON audit_log');
        DB::statement('DROP FUNCTION IF EXISTS audit_log_immutable()');
        Schema::dropIfExists('audit_log');
    }
};
