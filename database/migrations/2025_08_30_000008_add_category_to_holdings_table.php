<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            // Optional per-holding override of the asset's type, used only for
            // display / allocation grouping (e.g. flag a UCITS ETF as an index
            // fund). Pricing still follows the asset's real type.
            $table->string('category', 20)->nullable()->after('asset_id');
        });
    }

    public function down(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
