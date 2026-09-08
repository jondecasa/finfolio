<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            // Was varchar(255) — notes were getting cut off. TEXT holds up to 64KB.
            $table->text('notes')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('holdings', function (Blueprint $table) {
            $table->string('notes')->nullable()->change();
        });
    }
};
