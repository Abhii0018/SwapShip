<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_request_id')->constrained()->cascadeOnDelete();
            $table->text('sender_address');
            $table->text('receiver_address');
            $table->enum('status', ['Order Placed', 'Picked Up', 'In Transit', 'Delivered'])->default('Order Placed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
