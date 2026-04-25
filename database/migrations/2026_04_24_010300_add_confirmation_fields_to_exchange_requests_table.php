<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchange_requests', function (Blueprint $table) {
            $table->timestamp('sender_confirmed_at')->nullable()->after('status');
            $table->timestamp('receiver_confirmed_at')->nullable()->after('sender_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('exchange_requests', function (Blueprint $table) {
            $table->dropColumn(['sender_confirmed_at', 'receiver_confirmed_at']);
        });
    }
};
