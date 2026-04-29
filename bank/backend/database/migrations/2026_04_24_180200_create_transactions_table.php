<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // NULL for incoming SBP credits where sender is external.
            $table->foreignUuid('sender_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            // NULL for outgoing external transfers.
            $table->foreignUuid('receiver_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->decimal('amount', 19, 4);
            $table->decimal('fee_amount', 19, 4)->default(0);
            $table->char('currency', 3);
            $table->string('status', 20);
            $table->string('type', 20);
            $table->uuid('idempotency_key')->unique();
            $table->string('description', 255)->nullable();
            $table->string('error_code', 50)->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            // §3.6 indexes.
            $table->index(['sender_account_id', 'created_at'], 'transactions_sender_created_idx');
            $table->index(['receiver_account_id', 'created_at'], 'transactions_receiver_created_idx');
            $table->index(['status', 'created_at'], 'transactions_status_created_idx');
        });

        DB::statement("ALTER TABLE transactions ALTER COLUMN id SET DEFAULT gen_random_uuid()");
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_amount_positive CHECK (amount > 0)");
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_fee_nonnegative CHECK (fee_amount >= 0)");
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_currency_check CHECK (currency IN ('RUB','USD'))");
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_status_check CHECK (status IN ('pending','processing','success','failed','cancelled'))");
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_type_check CHECK (type IN ('internal','sbp_out','sbp_in'))");
        // An internal transfer MUST have both sides; external legs have exactly one.
        DB::statement(<<<'SQL'
            ALTER TABLE transactions ADD CONSTRAINT transactions_parties_by_type CHECK (
                (type = 'internal' AND sender_account_id IS NOT NULL AND receiver_account_id IS NOT NULL)
                OR (type = 'sbp_out' AND sender_account_id IS NOT NULL)
                OR (type = 'sbp_in'  AND receiver_account_id IS NOT NULL)
            )
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER transactions_set_updated_at
                BEFORE UPDATE ON transactions
                FOR EACH ROW EXECUTE FUNCTION set_updated_at();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS transactions_set_updated_at ON transactions');
        Schema::dropIfExists('transactions');
    }
};
