<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('negotiated_item_amount', 10, 2)->nullable()->after('item_amount');
            $table->decimal('upfront_amount', 10, 2)->nullable()->after('total_amount');
            $table->decimal('remaining_amount', 10, 2)->nullable()->after('upfront_amount');
            $table->boolean('second_payment_required_before_otp')->default(false)->after('remaining_amount');

            $table->string('upfront_payment_reference')->nullable()->after('payment_reference');
            $table->string('remaining_payment_reference')->nullable()->after('upfront_payment_reference');
            $table->string('upfront_gateway_order_id')->nullable()->after('gateway_order_id');
            $table->string('remaining_gateway_order_id')->nullable()->after('upfront_gateway_order_id');

            $table->timestamp('upfront_paid_at')->nullable()->after('paid_at');
            $table->timestamp('remaining_paid_at')->nullable()->after('upfront_paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'negotiated_item_amount',
                'upfront_amount',
                'remaining_amount',
                'second_payment_required_before_otp',
                'upfront_payment_reference',
                'remaining_payment_reference',
                'upfront_gateway_order_id',
                'remaining_gateway_order_id',
                'upfront_paid_at',
                'remaining_paid_at',
            ]);
        });
    }
};
