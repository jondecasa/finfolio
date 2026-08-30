<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            // Currency the average_cost / purchase price is expressed in. When
            // null it falls back to the asset's trading currency (legacy rows).
            $table->string('cost_currency', 3)->nullable()->after('average_cost');
        });
    }

    public function down(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            $table->dropColumn('cost_currency');
        });
    }
};
