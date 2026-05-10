<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchange_requests', function (Blueprint $table) {
            $table->timestamp('shipment_requested_at')->nullable()->after('receiver_confirmed_at');
            $table->foreignId('shipment_requested_by')->nullable()->after('shipment_requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('shipment_approved_at')->nullable()->after('shipment_requested_by');
            $table->foreignId('shipment_approved_by')->nullable()->after('shipment_approved_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exchange_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipment_approved_by');
            $table->dropColumn('shipment_approved_at');
            $table->dropConstrainedForeignId('shipment_requested_by');
            $table->dropColumn('shipment_requested_at');
        });
    }
};
