<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->bigInteger('account_id')->primary();
            $table->bigInteger('user_id');
            $table->bigInteger('loyalty_program_id');
            $table->decimal('current_balance', 15, 2)->default(0);

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('loyalty_program_id')->references('loyalty_program_id')->on('loyalty_programs')->cascadeOnDelete();

            $table->index('user_id', 'idx_accounts_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
