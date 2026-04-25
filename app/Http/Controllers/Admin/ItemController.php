<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $items = Item::with(['user','images'])
            ->latest()
            ->paginate(20);

        return view('admin.items.index', compact('items'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Item $item)
    {
        $item->load(['user','images']);
        return view('admin.items.show', compact('item'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Item $item)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Item $item)
    {
        $validated = $request->validate([
            'approved' => 'nullable|boolean',
            'locked' => 'nullable|boolean',
        ]);

        if (array_key_exists('approved', $validated)) {
            $item->approved = (bool) $validated['approved'];
        }

        if (array_key_exists('locked', $validated)) {
            $item->locked = (bool) $validated['locked'];
        }

        $item->save();

        return redirect()->route('admin.items.show', $item)->with('success', 'Item updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Item $item)
    {
        foreach ($item->images as $img) {
            $img->deleteFromCloud();
            $stored = str_replace('/storage/', '', $img->url);
            if (app('filesystem')->disk('public')->exists($stored)) {
                app('filesystem')->disk('public')->delete($stored);
            }
            $img->delete();
        }

        $item->delete();

        return redirect()->route('admin.items.index')->with('success', 'Item removed.');
    }
}
