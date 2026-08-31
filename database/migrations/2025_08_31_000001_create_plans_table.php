<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holding_id')->constrained()->cascadeOnDelete();

            // What the movement touches and which way it goes.
            $table->string('target', 10);      // quantity | debt | value
            $table->string('direction', 3);    // in | out
            $table->string('amount_kind', 6);  // units | cash  (units only when target = quantity)
            $table->decimal('amount', 32, 12); // always > 0; the sign comes from `direction`
            $table->string('currency', 3)->nullable(); // set when amount_kind = cash

            $table->string('frequency', 10);   // weekly | monthly | quarterly | yearly
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('next_run_on');
            $table->date('last_run_on')->nullable();
            $table->boolean('active')->default(true);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['active', 'next_run_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
