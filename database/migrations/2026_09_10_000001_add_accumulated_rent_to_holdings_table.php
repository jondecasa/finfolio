<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            // Total rent actually collected on this property to date, in its
            // currency — real estate only. Counts as profit in ROE (return on
            // the cash actually put in), unlike the projected `monthly_rent`
            // that only feeds ROCE. Can be topped up by a periodic Plan.
            $table->decimal('accumulated_rent', 24, 8)->nullable()->after('monthly_rent');
        });
    }

    public function down(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            $table->dropColumn('accumulated_rent');
        });
    }
};
