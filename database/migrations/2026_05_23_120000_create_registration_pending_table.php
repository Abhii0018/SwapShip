<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_pending', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('email')->index();
            $table->text('payload');
            $table->string('otp_hash');
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->timestamp('otp_expires_at');
            $table->timestamp('otp_last_sent_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_pending');
    }
};
