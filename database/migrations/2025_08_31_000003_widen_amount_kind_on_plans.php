<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Room for 'percent' alongside 'units' / 'cash'…
            $table->string('amount_kind', 12)->change();
            // …and 'half_yearly' alongside the other frequencies.
            $table->string('frequency', 12)->change();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('amount_kind', 6)->change();
            $table->string('frequency', 10)->change();
        });
    }
};
