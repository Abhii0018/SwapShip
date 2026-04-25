<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_otps', function (Blueprint $table) {
            $table->string('code_hash')->nullable()->after('code');
            $table->timestamp('sms_sent_at')->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_otps', function (Blueprint $table) {
            $table->dropColumn(['code_hash', 'sms_sent_at']);
        });
    }
};
