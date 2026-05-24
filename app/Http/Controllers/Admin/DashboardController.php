<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRequest;
use App\Models\Item;
use App\Models\Order;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'users' => User::query()->whereNotIn('email', \App\Support\AdminAccount::adminEmails())->count(),
            'items' => Item::count(),
            'exchanges' => ExchangeRequest::count(),
            'orders' => Order::count(),
        ];

        $recentUsers = User::query()
            ->whereNotIn('email', \App\Support\AdminAccount::adminEmails())
            ->latest()
            ->take(5)
            ->get(['id', 'name', 'email', 'created_at']);

        $recentItems = Item::with('user:id,name')
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentUsers', 'recentItems'));
    }
}
