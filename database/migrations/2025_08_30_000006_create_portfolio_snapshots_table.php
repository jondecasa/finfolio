<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete(); // null = aggregate
            $table->decimal('value', 24, 2);
            $table->decimal('invested', 24, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->unique(['user_id', 'account_id', 'captured_at']);
            $table->index(['user_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_snapshots');
    }
};
