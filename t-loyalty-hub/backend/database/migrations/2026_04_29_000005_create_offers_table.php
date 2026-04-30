<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('partner_id');
            $table->string('partner_name');
            $table->text('short_description')->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->string('brand_color_hex', 7)->nullable();
            $table->decimal('cashback_percent', 5, 2);
            $table->string('financial_segment', 20);

            $table->unique(['partner_id', 'financial_segment'], 'uniq_offer_partner_segment');
            $table->index('financial_segment', 'idx_offers_financial_segment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
