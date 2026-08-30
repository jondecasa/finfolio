<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            // Per-holding current value for manually-valued assets (real estate,
            // cash, other) so two holdings never share a price via the asset row.
            $table->decimal('manual_value', 24, 8)->nullable()->after('average_cost');
            // Outstanding debt tied to this holding (e.g. a mortgage). Shown under
            // Liabilities and subtracted from the holding's net worth value.
            $table->decimal('debt', 24, 8)->default(0)->after('manual_value');
        });
    }

    public function down(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            $table->dropColumn(['manual_value', 'debt']);
        });
    }
};
