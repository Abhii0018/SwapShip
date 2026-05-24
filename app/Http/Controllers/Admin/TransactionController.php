<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(): View
    {
        $buyerTransactions = Order::query()
            ->with([
                'buyer:id,name,email',
                'seller:id,name,email',
                'shipment.exchangeRequest.item:id,title,price',
            ])
            ->whereNotNull('buyer_id')
            ->latest()
            ->paginate(20);

        $summary = [
            'total_transactions' => Order::query()->count(),
            'completed_by_buyers' => Order::query()->whereNotNull('delivery_verified_at')->count(),
            'total_amount' => (float) Order::query()->sum('total_amount'),
            'platform_fees' => (float) Order::query()->sum('platform_fee'),
        ];

        return view('admin.transactions.index', compact('buyerTransactions', 'summary'));
    }
}
