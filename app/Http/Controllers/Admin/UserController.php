<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRequest;
use App\Models\Item;
use App\Models\Order;
use App\Models\User;
use App\Support\AdminAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->whereNotIn('email', AdminAccount::adminEmails())
            ->latest()
            ->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function show(User $user): View
    {
        if ($user->isAdmin()) {
            abort(404);
        }

        $publishedItems = Item::query()
            ->with('images')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $purchaseExchanges = ExchangeRequest::query()
            ->with(['item', 'receiver'])
            ->where('sender_id', $user->id)
            ->latest()
            ->get();

        $ordersAsBuyer = Order::query()
            ->with(['shipment.exchangeRequest.item'])
            ->where('buyer_id', $user->id)
            ->latest()
            ->get();

        return view('admin.users.show', compact(
            'user',
            'publishedItems',
            'purchaseExchanges',
            'ordersAsBuyer'
        ));
    }

    public function toggleBan(User $user): RedirectResponse
    {
        if ($user->isAdmin()) {
            abort(403);
        }

        $user->is_banned = ! $user->isBanned();
        $user->save();

        return back()->with('success', $user->isBanned() ? 'User suspended.' : 'User reactivated.');
    }
}
