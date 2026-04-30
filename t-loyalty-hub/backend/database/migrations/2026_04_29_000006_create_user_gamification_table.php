<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_gamification', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->unique();
            $table->integer('health_score')->default(0);
            $table->string('loyalty_tier', 50)->default('Новичок');
            $table->integer('streak_days')->default(0);
            $table->date('last_visit_date')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_gamification');
    }
};
