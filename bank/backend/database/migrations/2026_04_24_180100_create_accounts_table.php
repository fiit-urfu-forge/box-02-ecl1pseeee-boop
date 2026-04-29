<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('account_number', 20)->unique();
            $table->decimal('balance', 19, 4)->default(0);
            $table->char('currency', 3);
            $table->string('type', 20);
            $table->string('status', 20)->default('active');
            $table->decimal('daily_limit', 19, 4)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->timestampTz('deleted_at')->nullable();

            // §3.6 — index used for "list accounts of user".
            $table->index('user_id', 'accounts_user_id_idx');
        });

        DB::statement("ALTER TABLE accounts ALTER COLUMN id SET DEFAULT gen_random_uuid()");
        DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_balance_nonnegative CHECK (balance >= 0)");
        DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_currency_check CHECK (currency IN ('RUB','USD'))");
        DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_type_check CHECK (type IN ('checking','savings'))");
        DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_status_check CHECK (status IN ('active','frozen','closed'))");

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER accounts_set_updated_at
                BEFORE UPDATE ON accounts
                FOR EACH ROW EXECUTE FUNCTION set_updated_at();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS accounts_set_updated_at ON accounts');
        Schema::dropIfExists('accounts');
    }
};
