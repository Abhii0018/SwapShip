<?php

namespace App\Http\Controllers;

use App\Models\Exchange;
use Illuminate\Http\Request;

use App\Models\Item;

class ExchangeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user();
        $exchanges = Exchange::where('requester_id', $user->id)
            ->orWhere('accepter_id', $user->id)
            ->with(['requester', 'accepter', 'offeredItem', 'requestedItem'])
            ->latest()
            ->paginate(10);

        return view('exchanges.index', compact('exchanges'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = auth()->user();
        $userItems = Item::where('user_id', $user->id)->where('locked', false)->get();
        $otherItems = Item::where('user_id', '!=', $user->id)->where('locked', false)->get();

        return view('exchanges.create', compact('userItems', 'otherItems'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'offered_item_id' => 'required|exists:books,id',
            'requested_item_id' => 'required|exists:books,id',
            'cash_amount' => 'nullable|numeric|min:0',
        ]);

        $requester = $request->user();
        $offeredItem = Item::findOrFail($validated['offered_item_id']);
        $requestedItem = Item::findOrFail($validated['requested_item_id']);

        if ($offeredItem->user_id !== $requester->id) {
            return back()->with('error', 'You can only offer your own items.');
        }

        if ($requestedItem->user_id === $requester->id) {
            return back()->with('error', 'You cannot request your own item.');
        }

        $exchange = Exchange::create([
            'requester_id' => $requester->id,
            'accepter_id' => $requestedItem->user_id,
            'offered_book_id' => $offeredItem->id,
            'requested_book_id' => $requestedItem->id,
            'cash_amount' => $validated['cash_amount'],
            'status' => 'requested',
        ]);

        return redirect()->route('exchanges.index')->with('success', 'Exchange request sent.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Exchange $exchange)
    {
        $this->authorize('view', $exchange);
        $exchange->load(['requester', 'accepter', 'offeredItem', 'requestedItem', 'messages.sender']);
        return view('exchanges.show', compact('exchange'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Exchange $exchange)
    {
        $this->authorize('update', $exchange);

        $validated = $request->validate([
            'status' => 'required|in:accepted,rejected,shipped,delivered,completed',
        ]);

        $exchange->status = $validated['status'];

        if ($validated['status'] === 'accepted') {
            $exchange->offeredItem->update(['locked' => true]);
            $exchange->requestedItem->update(['locked' => true]);
        } elseif (in_array($validated['status'], ['rejected', 'completed'])) {
            $exchange->offeredItem->update(['locked' => false]);
            $exchange->requestedItem->update(['locked' => false]);
        }

        $exchange->save();

        return redirect()->route('exchanges.show', $exchange)->with('success', 'Exchange status updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Exchange $exchange)
    {
        $this->authorize('delete', $exchange);

        $exchange->offeredItem->update(['locked' => false]);
        $exchange->requestedItem->update(['locked' => false]);
        $exchange->delete();

        return redirect()->route('exchanges.index')->with('success', 'Exchange deleted.');
    }
}
