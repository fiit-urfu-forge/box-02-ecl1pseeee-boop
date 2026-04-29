<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            // UUID PK generated on the DB side. See §3.1.
            $table->uuid('id')->primary();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255)->unique();
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password_hash', 255);
            $table->string('phone', 20)->nullable()->unique();
            $table->string('avatar_path', 500)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestampTz('failed_login_at')->nullable();
            $table->smallInteger('failed_login_count')->default(0);
            $table->rememberToken();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->timestampTz('deleted_at')->nullable();
        });

        DB::statement("ALTER TABLE users ALTER COLUMN id SET DEFAULT gen_random_uuid()");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active','suspended','blocked'))");

        // Trigger to keep updated_at fresh on every UPDATE.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION set_updated_at() RETURNS trigger AS $$
            BEGIN
                NEW.updated_at = NOW();
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER users_set_updated_at
                BEFORE UPDATE ON users
                FOR EACH ROW EXECUTE FUNCTION set_updated_at();
        SQL);

        // Default Laravel auth helper tables (password resets & sessions).
        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        DB::statement('DROP TRIGGER IF EXISTS users_set_updated_at ON users');
        Schema::dropIfExists('users');
        DB::statement('DROP FUNCTION IF EXISTS set_updated_at()');
    }
};
