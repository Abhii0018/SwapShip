<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_key', 64)->nullable();
            $table->string('name');
            $table->json('filters');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['session_key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
