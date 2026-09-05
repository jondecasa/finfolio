<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            // Share of the position actually owned by the user (real estate
            // co-ownership, e.g. 50% of a jointly-owned flat). Scales gross
            // value, debt and invested equity down to the user's share.
            // Defaults to 100 (full ownership) for every existing row.
            $table->decimal('ownership_pct', 5, 2)->default(100)->after('mortgage_down_payment');
        });
    }

    public function down(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            $table->dropColumn('ownership_pct');
        });
    }
};
