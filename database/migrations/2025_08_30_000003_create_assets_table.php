<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16); // crypto, stock, etf, fund, cash
            $table->string('symbol', 32);
            $table->string('provider_id')->nullable(); // e.g. coingecko id "bitcoin"
            $table->string('name');
            $table->string('exchange')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('logo_url')->nullable();
            $table->decimal('current_price', 24, 8)->nullable();
            $table->decimal('previous_close', 24, 8)->nullable();
            $table->decimal('change_pct', 12, 4)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('price_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'symbol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
