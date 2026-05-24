<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(): View
    {
        $items = Item::query()
            ->with(['user:id,name,email', 'images'])
            ->latest()
            ->paginate(20);

        return view('admin.items.index', compact('items'));
    }

    public function destroy(Item $item): RedirectResponse
    {
        foreach ($item->images as $image) {
            $url = (string) $image->url;
            if (str_starts_with($url, '/storage/')) {
                $path = ltrim(str_replace('/storage/', '', $url), '/');
                if ($path !== '' && Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            } elseif (str_contains($url, 'res.cloudinary.com')) {
                Log::info('Admin deleted item with Cloudinary image', ['url' => $url]);
            }
            $image->delete();
        }

        if ($item->bill_url && str_starts_with((string) $item->bill_url, '/storage/')) {
            $billPath = ltrim(str_replace('/storage/', '', (string) $item->bill_url), '/');
            if ($billPath !== '' && Storage::disk('public')->exists($billPath)) {
                Storage::disk('public')->delete($billPath);
            }
        }

        $item->delete();

        return redirect()->route('admin.items.index')->with('success', 'Item deleted.');
    }
}
