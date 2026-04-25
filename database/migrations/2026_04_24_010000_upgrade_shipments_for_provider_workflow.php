<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('provider')->default('mock')->after('receiver_address');
            $table->string('awb_number')->nullable()->index()->after('provider');
            $table->string('tracking_url')->nullable()->after('awb_number');
            $table->string('label_url')->nullable()->after('tracking_url');
            $table->string('status_code')->nullable()->after('status');
            $table->string('status_label')->nullable()->after('status_code');
            $table->timestamp('estimated_delivery_at')->nullable()->after('status_label');
            $table->timestamp('pickup_scheduled_at')->nullable()->after('estimated_delivery_at');
            $table->json('meta')->nullable()->after('pickup_scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'provider',
                'awb_number',
                'tracking_url',
                'label_url',
                'status_code',
                'status_label',
                'estimated_delivery_at',
                'pickup_scheduled_at',
                'meta',
            ]);
        });
    }
};
