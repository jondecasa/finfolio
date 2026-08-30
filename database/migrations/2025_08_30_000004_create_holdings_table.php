<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holdings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 32, 12)->default(0);
            $table->decimal('average_cost', 24, 8)->nullable(); // per unit, in asset currency
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holdings');
    }
};
