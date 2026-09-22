<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The asset's own (shared) live price is what's being watched, not
            // any one holding of it — an alert can exist for a symbol the user
            // doesn't even hold yet.
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('condition', 10); // 'above' | 'below'
            $table->decimal('target_price', 24, 8);
            // Fires once, then deactivates — a still-true condition on every
            // future hourly refresh would otherwise renotify forever. Can be
            // re-armed from the Alerts page instead of recreated from scratch.
            $table->boolean('active')->default(true);
            $table->timestamp('triggered_at')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_alerts');
    }
};
