<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchange_requests', function (Blueprint $table) {
            $table->index(['sender_id', 'status'], 'exchange_sender_status_idx');
            $table->index(['receiver_id', 'status'], 'exchange_receiver_status_idx');
            $table->index(['item_id', 'status'], 'exchange_item_status_idx');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->unique('exchange_request_id', 'shipments_exchange_unique');
            $table->unique('awb_number', 'shipments_awb_unique');
            $table->index(['status_code', 'status'], 'shipments_status_idx');
            $table->index(['provider', 'status_code'], 'shipments_provider_status_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['buyer_id', 'payment_status'], 'orders_buyer_payment_idx');
            $table->index(['seller_id', 'settlement_status'], 'orders_seller_settlement_idx');
            $table->index(['payment_method', 'payment_status'], 'orders_method_payment_idx');
        });

        Schema::table('delivery_otps', function (Blueprint $table) {
            $table->index(['order_id', 'verified_at'], 'otp_order_verified_idx');
            $table->index(['generated_for_user_id', 'verified_at'], 'otp_user_verified_idx');
            $table->index(['expires_at', 'verified_at'], 'otp_expiry_verified_idx');
        });

        Schema::table('sms_audit_logs', function (Blueprint $table) {
            $table->index(['order_id', 'status'], 'sms_order_status_idx');
            $table->index(['channel', 'status'], 'sms_channel_status_idx');
            $table->index('created_at', 'sms_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('exchange_requests', function (Blueprint $table) {
            $table->dropIndex('exchange_sender_status_idx');
            $table->dropIndex('exchange_receiver_status_idx');
            $table->dropIndex('exchange_item_status_idx');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropUnique('shipments_exchange_unique');
            $table->dropUnique('shipments_awb_unique');
            $table->dropIndex('shipments_status_idx');
            $table->dropIndex('shipments_provider_status_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_buyer_payment_idx');
            $table->dropIndex('orders_seller_settlement_idx');
            $table->dropIndex('orders_method_payment_idx');
        });

        Schema::table('delivery_otps', function (Blueprint $table) {
            $table->dropIndex('otp_order_verified_idx');
            $table->dropIndex('otp_user_verified_idx');
            $table->dropIndex('otp_expiry_verified_idx');
        });

        Schema::table('sms_audit_logs', function (Blueprint $table) {
            $table->dropIndex('sms_order_status_idx');
            $table->dropIndex('sms_channel_status_idx');
            $table->dropIndex('sms_created_idx');
        });
    }
};
