<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            // Rent collected per month, in the property's currency — real
            // estate only. Feeds ROCE (unlevered return: appreciation + rent
            // over the full purchase price) alongside ROE (levered return on
            // the down payment). Null/0 means the property isn't rented out.
            $table->decimal('monthly_rent', 24, 8)->nullable()->after('ownership_pct');
        });
    }

    public function down(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            $table->dropColumn('monthly_rent');
        });
    }
};
