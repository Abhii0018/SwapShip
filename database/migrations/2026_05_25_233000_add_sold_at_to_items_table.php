<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->timestamp('sold_at')->nullable()->after('bill_url');
            $table->index('sold_at');
        });

        // Backfill: mark items sold when an existing order has committed payment.
        // Escrow: upfront_paid_at is set.
        // COD: an order exists (buyer committed to pay on delivery).
        try {
            DB::statement(<<<'SQL'
                UPDATE items SET sold_at = sub.committed_at
                FROM (
                    SELECT i.id AS item_id, MIN(
                        COALESCE(o.upfront_paid_at, o.paid_at, o.created_at)
                    ) AS committed_at
                    FROM items i
                    INNER JOIN exchange_requests er ON er.item_id = i.id
                    INNER JOIN shipments s ON s.exchange_request_id = er.id
                    INNER JOIN orders o ON o.shipment_id = s.id
                    WHERE (
                        (o.payment_method = 'escrow' AND o.upfront_paid_at IS NOT NULL)
                        OR (o.payment_method = 'cod')
                    )
                    GROUP BY i.id
                ) sub
                WHERE items.id = sub.item_id AND items.sold_at IS NULL
            SQL);
        } catch (\Throwable $e) {
            // Backfill is best effort. Schema is what matters.
        }
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['sold_at']);
            $table->dropColumn('sold_at');
        });
    }
};
