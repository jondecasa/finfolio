<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            // Cash paid upfront at purchase (e.g. a mortgage down payment) — real
            // estate only. Used to compute invested equity (purchase price minus
            // this down payment); does not affect net worth or liabilities, which
            // still use the outstanding `debt` balance.
            $table->decimal('mortgage_down_payment', 24, 8)->nullable()->after('debt');
        });
    }

    public function down(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            $table->dropColumn('mortgage_down_payment');
        });
    }
};
