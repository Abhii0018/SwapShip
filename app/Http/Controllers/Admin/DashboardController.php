<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOtp;
use App\Models\ExchangeRequest;
use App\Models\Item;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\SmsAuditLog;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total_users' => User::count(),
            'total_items' => Item::count(),
            'total_exchanges' => ExchangeRequest::count(),
            'completed_exchanges' => ExchangeRequest::where('status', 'Completed')->count(),
            'total_shipments' => Shipment::count(),
            'total_orders' => Order::count(),
            'otp_generated' => DeliveryOtp::count(),
            'otp_verified' => DeliveryOtp::whereNotNull('verified_at')->count(),
            'otp_pending' => DeliveryOtp::whereNull('verified_at')->count(),
            'sms_sent_logs' => SmsAuditLog::count(),
        ];

        $recentOrders = Order::with(['shipment.exchangeRequest.item', 'buyer', 'seller', 'deliveryOtps'])
            ->latest()
            ->take(10)
            ->get();

        $recentSmsLogs = SmsAuditLog::query()
            ->latest()
            ->take(10)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentOrders', 'recentSmsLogs'));
    }
}
