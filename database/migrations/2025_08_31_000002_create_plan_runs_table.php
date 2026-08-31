<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->date('ran_on');
            $table->string('status', 10); // applied | skipped

            // What moved.
            $table->decimal('units_delta', 32, 12)->nullable();  // signed change in quantity
            $table->decimal('cash_amount', 24, 8)->nullable();   // cash value moved, in cash_currency
            $table->string('cash_currency', 3)->nullable();
            $table->decimal('unit_price', 24, 8)->nullable();    // execution price, in asset_currency
            $table->string('asset_currency', 3)->nullable();

            // Position state after the movement.
            $table->decimal('resulting_quantity', 32, 12)->nullable();
            $table->decimal('resulting_avg_cost', 24, 8)->nullable();
            $table->decimal('resulting_debt', 24, 8)->nullable();
            $table->decimal('resulting_value', 24, 8)->nullable();

            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'ran_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_runs');
    }
};
