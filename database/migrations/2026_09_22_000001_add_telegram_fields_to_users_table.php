<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Set once the user completes the /start handshake with the bot.
            $table->string('telegram_chat_id')->nullable()->after('remember_token');
            // One-time code embedded in the "Connect Telegram" deep link, used
            // to match the /start message back to this user. Cleared once linked.
            $table->string('telegram_link_token')->nullable()->unique()->after('telegram_chat_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telegram_chat_id', 'telegram_link_token']);
        });
    }
};
