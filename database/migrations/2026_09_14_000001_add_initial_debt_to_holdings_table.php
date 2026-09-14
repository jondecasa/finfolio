<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            // The mortgage amount as originally taken out — fixed once set, never
            // moved by a debt-paydown Plan. Powers the /debts progress bar
            // (paid off = initial_debt - debt; 100% once debt hits 0).
            $table->decimal('initial_debt', 24, 8)->nullable()->after('debt');
        });

        // Best-effort backfill for existing mortgages: treat "now" as the
        // starting line. Users can correct it to the true original amount.
        DB::table('holdings')->where('debt', '>', 0)->whereNull('initial_debt')
            ->update(['initial_debt' => DB::raw('debt')]);
    }

    public function down(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            $table->dropColumn('initial_debt');
        });
    }
};
