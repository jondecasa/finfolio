<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_runs', function (Blueprint $table) {
            // Accumulated rent on the holding after a `rent` plan movement.
            $table->decimal('resulting_rent', 24, 8)->nullable()->after('resulting_value');
        });
    }

    public function down(): void
    {
        Schema::table('plan_runs', function (Blueprint $table) {
            $table->dropColumn('resulting_rent');
        });
    }
};
