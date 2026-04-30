<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('loyalty_history', function (Blueprint $table) {
            $table->bigInteger('transaction_id')->primary();
            $table->bigInteger('account_id');
            $table->decimal('cashback_amount', 10, 2);
            $table->date('payout_date');

            $table->foreign('account_id')->references('account_id')->on('accounts')->cascadeOnDelete();

            $table->index('account_id', 'idx_loyalty_history_account_id');
            $table->index('payout_date', 'idx_loyalty_history_payout_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_history');
    }
};
