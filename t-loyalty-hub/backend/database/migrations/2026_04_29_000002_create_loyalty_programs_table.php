<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->bigInteger('loyalty_program_id')->primary();
            $table->string('loyalty_program_name', 100);
            $table->string('cashback_currency', 20);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_programs');
    }
};
