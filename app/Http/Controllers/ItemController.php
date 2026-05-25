<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\ExchangeRequest;
use App\Models\SavedSearch;
use App\Models\User;
use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ItemController extends Controller
{
    private const CATEGORIES = [
        'Electronics' => [
            'Mobiles',
            'Laptops',
            'Tablets',
            'Smart Watches',
            'Headphones',
            'Cameras',
            'TVs',
            'Gaming Consoles',
            'Home Appliances',
            'Speakers',
            'Accessories',
        ],
        'Fashion' => [
            "Men's Clothing",
            "Women's Clothing",
            'Footwear',
            'Bags & Wallets',
            'Accessories',
            'Watches',
            'Jewellery',
            'Sunglasses',
        ],
        'Books & Learning' => [
            'Books',
            'E-Books',
            'Study Materials',
            'Courses',
            'Stationery',
            'Educational Kits',
        ],
        'Home & Furniture' => [
            'Furniture',
            'Home Decor',
            'Kitchenware',
            'Bedding',
            'Bathroom Accessories',
            'Garden & Outdoor',
        ],
        'Sports & Fitness' => [
            'Gym Equipment',
            'Sports Gear',
            'Cycling',
            'Outdoor Activities',
            'Fitness Wear',
            'Yoga & Meditation',
        ],
        'Vehicles' => [
            'Cars',
            'Bikes',
            'Scooters',
            'Spare Parts',
            'Vehicle Accessories',
            'GPS & Electronics',
        ],
        'Toys & Games' => [
            'Board Games',
            'Video Games',
            'Action Figures',
            'Puzzles',
            'Remote Control',
            'Dolls & Plush',
        ],
        'Others' => [
            'Musical Instruments',
            'Art & Craft',
            'Pet Supplies',
            'Baby Products',
            'Health & Beauty',
            'Miscellaneous',
        ],
    ];

    public function landing(): View
    {
        try {
            $featuredItems = Item::with('images')
                ->latest()
                ->take(6)
                ->get();

            $stats = [
                'users' => User::count(),
                'items' => Item::count(),
                'exchanges' => ExchangeRequest::count(),
            ];
        } catch (\Throwable $exception) {
            report($exception);
            $featuredItems = collect();
            $stats = ['users' => 0, 'items' => 0, 'exchanges' => 0];
        }

        return view('welcome', compact('featuredItems', 'stats'));
    }

    public function index(Request $request): View|JsonResponse
    {
        $query = Item::with(['images', 'user']);

        $query->when($request->string('search')->isNotEmpty(), function ($q) use ($request) {
            $q->where(function ($inner) use ($request) {
                $inner->where('title', 'like', '%'.$request->search.'%')
                    ->orWhere('description', 'like', '%'.$request->search.'%')
                    ->orWhere('category', 'like', '%'.$request->search.'%')
                    ->orWhere('exchange_preference', 'like', '%'.$request->search.'%');
            });
        })->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('location'), fn ($q) => $q->where('location', $request->location))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('condition'), fn ($q) => $q->where('condition', $request->condition))
            ->when($request->filled('min_price'), fn ($q) => $q->whereNotNull('price')->where('price', '>=', (float) $request->input('min_price')))
            ->when($request->filled('max_price'), fn ($q) => $q->whereNotNull('price')->where('price', '<=', (float) $request->input('max_price')));

        $distanceKm = (float) $request->input('distance_km', 0);
        $userLat = $request->input('user_lat');
        $userLng = $request->input('user_lng');
        if ($distanceKm > 0 && is_numeric($userLat) && is_numeric($userLng)) {
            $lat = (float) $userLat;
            $lng = (float) $userLng;
            $distanceSql = '(6371 * acos(cos(radians(?)) * cos(radians(location_lat)) * cos(radians(location_lng) - radians(?)) + sin(radians(?)) * sin(radians(location_lat))))';
            $query->whereNotNull('location_lat')
                ->whereNotNull('location_lng')
                ->selectRaw("items.*, {$distanceSql} as distance_km", [$lat, $lng, $lat])
                ->whereRaw("{$distanceSql} <= ?", [$lat, $lng, $lat, $distanceKm]);
        }

        $recommendedFirst = $request->boolean('recommended_first', true);
        if ($recommendedFirst) {
            $this->applyRecommendedOrder($query, $request);
        }

        $sort = $request->input('sort', 'latest');
        if ($sort === 'price_low') {
            $query->orderBy('price')->orderByDesc('created_at');
        } elseif ($sort === 'price_high') {
            $query->orderByDesc('price')->orderByDesc('created_at');
        } elseif (! $recommendedFirst) {
            $query->latest();
        }

        $items = $query->paginate(12)->withQueryString();
        if ($recommendedFirst) {
            $context = $this->buildRecommendationContext($request);
            $items->setCollection($this->annotateRecommendationReasons($items->getCollection(), $context));
        }
        $categories = Item::query()->select('category')->distinct()->orderBy('category')->pluck('category');
        $locations = Item::query()->select('location')->distinct()->orderBy('location')->pluck('location');
        $conditions = ['new', 'like new', 'used'];
        $savedSearches = $this->getSavedSearches($request);

        if ($request->boolean('ajax')) {
            return response()->json([
                'itemsHtml' => view('items.partials.list', compact('items'))->render(),
                'total' => $items->total(),
            ]);
        }

        return view('items.index', compact('items', 'categories', 'locations', 'conditions', 'sort', 'savedSearches'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->route('login')->with('error', 'Please login to post an item.');
        }

        if (! $request->user()->hasCompletedProfile()) {
            return redirect()->route('profile.edit')
                ->with('error', 'Complete your profile before posting an item.');
        }

        $conditions = ['new', 'like new', 'used'];
        $parentCategories = self::CATEGORIES;

        return view('items.create', compact('conditions', 'parentCategories'));
    }

    public function suggestLocations(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));
        if (mb_strlen($query) < 2) {
            return response()->json(['suggestions' => []]);
        }

        $localMatches = Item::query()
            ->where('location', 'like', '%'.$query.'%')
            ->select('location')
            ->distinct()
            ->orderBy('location')
            ->limit(8)
            ->pluck('location')
            ->all();

        $remoteMatches = [];
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'SwapShip/1.0 (location-suggest)',
            ])->timeout(4)->get('https://nominatim.openstreetmap.org/search', [
                'q' => $query,
                'format' => 'jsonv2',
                'addressdetails' => 1,
                'limit' => 8,
                'countrycodes' => 'in',
            ]);

            if ($response->ok()) {
                $remoteMatches = collect($response->json())
                    ->map(function (array $row): string {
                        $parts = [];
                        $address = $row['address'] ?? [];
                        foreach (['suburb', 'city_district', 'city', 'state', 'country'] as $key) {
                            if (!empty($address[$key])) {
                                $parts[] = $address[$key];
                            }
                        }
                        if (empty($parts) && !empty($row['display_name'])) {
                            return (string) $row['display_name'];
                        }

                        return implode(', ', array_unique($parts));
                    })
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }
        } catch (\Throwable $e) {
            // Degrade gracefully to local suggestions only.
        }

        $merged = collect([...$localMatches, ...$remoteMatches])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->take(10)
            ->values()
            ->all();

        return response()->json(['suggestions' => $merged]);
    }

    public function suggestCategories(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));
        $qLen = mb_strlen($query);

        $results = [];

        if ($qLen === 0) {
            $results = array_keys(self::CATEGORIES);
        } else {
            foreach (self::CATEGORIES as $parent => $subs) {
                $parentMatch = str_contains(mb_strtolower($parent), mb_strtolower($query));
                $matchedSubs = [];
                foreach ($subs as $sub) {
                    if (str_contains(mb_strtolower($sub), mb_strtolower($query))) {
                        $matchedSubs[] = $sub;
                    }
                }
                if ($parentMatch || count($matchedSubs) > 0) {
                    if ($parentMatch) {
                        $results[] = $parent;
                    }
                    foreach ($matchedSubs as $m) {
                        if (!in_array($m, $results, true)) {
                            $results[] = $m;
                        }
                    }
                }
            }

            $fromItems = Item::query()
                ->select('category')
                ->distinct()
                ->where('category', 'like', '%'.$query.'%')
                ->pluck('category');
            foreach ($fromItems as $cat) {
                if (!in_array($cat, $results, true)) {
                    $results[] = $cat;
                }
            }
        }

        return response()->json([
            'suggestions' => array_slice($results, 0, 12),
        ]);
    }

    public function reverseGeocode(Request $request): JsonResponse
    {
        $lat = (float) $request->input('lat');
        $lng = (float) $request->input('lng');

        if (! $lat || ! $lng) {
            return response()->json(['location' => null], 422);
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'SwapShip/1.0 (reverse-geocode)',
            ])->timeout(4)->get('https://nominatim.openstreetmap.org/reverse', [
                'lat' => $lat,
                'lon' => $lng,
                'format' => 'jsonv2',
                'addressdetails' => 1,
            ]);

            if (! $response->ok()) {
                return response()->json(['location' => $this->reverseGeocodeFallback($lat, $lng)]);
            }

            $data = $response->json();
            $address = $data['address'] ?? [];
            $parts = [];
            // Different regions return different keys; keep this broad for reliable autofill.
            foreach ([
                'suburb',
                'neighbourhood',
                'village',
                'town',
                'city_district',
                'city',
                'county',
                'state_district',
                'state',
                'country',
            ] as $key) {
                if (! empty($address[$key])) {
                    $parts[] = (string) $address[$key];
                }
            }

            if (! empty($parts)) {
                $location = implode(', ', collect($parts)->unique()->take(4)->all());
            } else {
                $displayName = trim((string) ($data['display_name'] ?? ''));
                $location = $displayName !== ''
                    ? implode(', ', array_slice(array_map('trim', explode(',', $displayName)), 0, 4))
                    : '';
            }

            if ($location !== '') {
                return response()->json(['location' => $location]);
            }

            return response()->json(['location' => $this->reverseGeocodeFallback($lat, $lng)]);
        } catch (\Throwable $e) {
            return response()->json(['location' => $this->reverseGeocodeFallback($lat, $lng)]);
        }
    }

    protected function reverseGeocodeFallback(float $lat, float $lng): ?string
    {
        try {
            $response = Http::timeout(4)->get('https://api.bigdatacloud.net/data/reverse-geocode-client', [
                'latitude' => $lat,
                'longitude' => $lng,
                'localityLanguage' => 'en',
            ]);

            if (! $response->ok()) {
                return null;
            }

            $data = $response->json();
            $parts = collect([
                $data['locality'] ?? null,
                $data['city'] ?? null,
                $data['principalSubdivision'] ?? null,
                $data['countryName'] ?? null,
            ])
                ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                ->map(fn ($v) => trim((string) $v))
                ->unique()
                ->take(4)
                ->values()
                ->all();

            return empty($parts) ? null : implode(', ', $parts);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function store(Request $request)
    {
        if (! $request->user()) {
            return redirect()->route('login')->with('error', 'Please login to post an item.');
        }

        if (! $request->user()->hasCompletedProfile()) {
            return redirect()->route('profile.edit')
                ->with('error', 'Complete your profile before posting an item.');
        }

        $request->merge([
            'category' => $this->resolveListingCategory($request),
        ]);

        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:100',
            'condition' => 'required|string|max:100',
            'item_age' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'location' => 'required|string|max:255',
            'location_lat' => 'nullable|numeric|between:-90,90',
            'location_lng' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string',
            'images' => 'required|array|min:1|max:3',
            'images.*' => 'required|image|max:4096',
            'bill' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];

        $validated = $request->validate($rules, [
            'category.required' => 'Please select a category. Subcategory is optional.',
        ]);
        unset($validated['images'], $validated['bill']);
        $validated['user_id'] = $request->user()->id;
        $validated['type'] = 'sell';

        if ($request->hasFile('bill')) {
            $billPath = $request->file('bill')->store('item-bills', 'public');
            $validated['bill_url'] = '/storage/'.$billPath;
        }

        $item = Item::create($validated);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                ItemImage::create([
                    'item_id' => $item->id,
                    'url' => $this->uploadItemImageAndGetUrl($file),
                ]);
            }
        }

        return redirect()->route('items.show', $item)->with('success', 'Item listed successfully.');
    }

    public function show(Item $item): View
    {
        $item->load(['images', 'user']);
        $existingConversation = null;

        $viewer = request()->user();
        if ($viewer && $viewer->id !== $item->user_id) {
            $existingConversation = ExchangeRequest::query()
                ->where('item_id', $item->id)
                ->whereNotIn('status', ['Rejected', 'Completed'])
                ->where(function ($q) use ($viewer, $item) {
                    $q->where(function ($inner) use ($viewer, $item) {
                        $inner->where('sender_id', $viewer->id)
                            ->where('receiver_id', $item->user_id);
                    })->orWhere(function ($inner) use ($viewer, $item) {
                        $inner->where('sender_id', $item->user_id)
                            ->where('receiver_id', $viewer->id);
                    });
                })
                ->latest()
                ->first();
        }

        return view('items.show', compact('item', 'existingConversation'));
    }

    public function myItems(Request $request): View
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Please login to view your items.');
        }

        $items = Item::with('images')
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(12);

        return view('items.myitems', compact('items'));
    }

    public function myDashboard(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Please login to view your dashboard.');
        }

        try {
            $myItems = Item::with('images')
                ->where('user_id', $user->id)
                ->latest()
                ->paginate(12, ['*'], 'items_page');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Dashboard items query failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            $myItems = new \Illuminate\Pagination\LengthAwarePaginator(
                collect(), 0, 12, 1,
                ['path' => $request->url(), 'pageName' => 'items_page']
            );
        }

        try {
            $myPurchases = \App\Models\Order::with('shipment.exchangeRequest.item.images')
                ->where('buyer_id', $user->id)
                ->latest()
                ->paginate(12, ['*'], 'purchases_page');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Dashboard purchases query failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            $myPurchases = new \Illuminate\Pagination\LengthAwarePaginator(
                collect(), 0, 12, 1,
                ['path' => $request->url(), 'pageName' => 'purchases_page']
            );
        }

        return view('items.my-dashboard', compact('myItems', 'myPurchases'));
    }

    public function edit(Item $item): View
    {
        if ($this->resolveActorId(request()) !== $item->user_id) {
            abort(403);
        }

        $conditions = ['new', 'like new', 'used'];
        $parentCategories = self::CATEGORIES;

        return view('items.edit', compact('item', 'conditions', 'parentCategories'));
    }

    public function update(Request $request, Item $item)
    {
        if ($this->resolveActorId($request) !== $item->user_id) {
            abort(403);
        }

        $request->merge([
            'category' => $this->resolveListingCategory($request),
        ]);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:100',
            'condition' => 'required|string|max:100',
            'item_age' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'location' => 'required|string|max:255',
            'location_lat' => 'nullable|numeric|between:-90,90',
            'location_lng' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string',
            'bill' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);
        $validated['type'] = 'sell';
        if ($request->hasFile('bill')) {
            $this->deleteBillFromStorage($item->bill_url);
            $validated['bill_url'] = $this->uploadBillToCloudinary($request->file('bill'));
        }
        $item->update($validated);

        return redirect()->route('items.show', $item)->with('success', 'Item updated successfully.');
    }

    public function destroy(Request $request, Item $item)
    {
        if ($this->resolveActorId($request) !== $item->user_id) {
            abort(403);
        }

        // Validate password
        $request->validate([
            'password' => 'required|string',
        ]);

        if (! Hash::check($request->input('password'), $request->user()->password)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Invalid password. Please try again.'
                ], 422);
            }
            return redirect()->route('items.my')
                ->with('error', 'Invalid password. Please try again.');
        }

        foreach ($item->images as $image) {
            $url = (string) $image->url;
            if (str_starts_with($url, '/storage/')) {
                $path = ltrim(str_replace('/storage/', '', $url), '/');
                if ($path !== '') {
                    Storage::disk('public')->delete($path);
                }
                continue;
            }
            $this->deleteCloudinaryAssetByUrl($url);
        }
        $item->images()->delete();
        $item->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Item deleted successfully.'
            ]);
        }
        return redirect()->route('items.my')->with('success', 'Item deleted successfully.');
    }

    public function saveSearch(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'filters' => 'required|array',
        ]);

        $allowed = [
            'search', 'category', 'location', 'type', 'condition', 'sort',
            'min_price', 'max_price', 'distance_km', 'user_lat', 'user_lng', 'recommended_first',
        ];
        $filters = collect($validated['filters'])
            ->only($allowed)
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        SavedSearch::query()->create([
            'user_id' => $request->user()?->id,
            'session_key' => $request->user() ? null : $this->guestSessionKey($request),
            'name' => $validated['name'],
            'filters' => $filters,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true], 201);
        }

        return back()->with('success', 'Search saved.');
    }

    public function deleteSavedSearch(Request $request, SavedSearch $savedSearch)
    {
        $canDelete = $request->user()
            ? $savedSearch->user_id === $request->user()->id
            : $savedSearch->session_key === $this->guestSessionKey($request);

        abort_unless($canDelete, 403);
        $savedSearch->delete();

        return back()->with('success', 'Saved search removed.');
    }

    protected function resolveActorId(Request $request): int
    {
        if ($request->user()) {
            return $request->user()->id;
        }

        return $request->session()->has('guest_seller_user_id')
            ? (int) $request->session()->get('guest_seller_user_id')
            : $this->resolveGuestSellerId($request);
    }

    protected function guestSessionKey(Request $request): string
    {
        return hash('sha256', (string) $request->session()->getId());
    }

    protected function getSavedSearches(Request $request)
    {
        return SavedSearch::query()
            ->when($request->user(), fn ($q) => $q->where('user_id', $request->user()->id))
            ->when(! $request->user(), fn ($q) => $q->where('session_key', $this->guestSessionKey($request)))
            ->latest()
            ->limit(8)
            ->get();
    }

    protected function applyRecommendedOrder($query, Request $request): void
    {
        $query->addSelect('items.*');
        $context = $this->buildRecommendationContext($request);
        $actorId = $context['actor_id'];
        if (! $actorId) {
            $query->orderByDesc('created_at');
            return;
        }

        $scoreParts = ['0'];
        $scoreBindings = [];
        $categoryHints = $context['category_hints'];
        $locationPrefix = $context['location_prefix'];
        $priceHint = $context['price_hint'];
        $userLat = $context['user_lat'];
        $userLng = $context['user_lng'];
        if (! empty($categoryHints)) {
            $placeholders = implode(',', array_fill(0, count($categoryHints), '?'));
            $scoreParts[] = "CASE WHEN items.category IN ({$placeholders}) THEN 45 ELSE 0 END";
            $scoreBindings = [...$scoreBindings, ...$categoryHints];
        }
        if ($locationPrefix !== '') {
            $scoreParts[] = 'CASE WHEN items.location LIKE ? THEN 12 ELSE 0 END';
            $scoreBindings[] = "{$locationPrefix}%";
        }

        if ($priceHint) {
            $priceScale = max(500.0, (float) $priceHint * 0.25);
            $scoreParts[] = 'CASE WHEN items.price IS NULL THEN 0 WHEN (25 - (ABS(items.price - ?) / ?)) > 0 THEN (25 - (ABS(items.price - ?) / ?)) ELSE 0 END';
            $scoreBindings = [...$scoreBindings, (float) $priceHint, $priceScale, (float) $priceHint, $priceScale];
        }

        if ($userLat !== null && $userLng !== null) {
            $distanceSql = '(6371 * acos(cos(radians(?)) * cos(radians(items.location_lat)) * cos(radians(items.location_lng) - radians(?)) + sin(radians(?)) * sin(radians(items.location_lat))))';
            $scoreParts[] = "CASE WHEN items.location_lat IS NULL OR items.location_lng IS NULL THEN 0 WHEN {$distanceSql} <= 2 THEN 24 WHEN {$distanceSql} <= 10 THEN 14 WHEN {$distanceSql} <= 30 THEN 6 ELSE 0 END";
            $scoreBindings = [...$scoreBindings, $userLat, $userLng, $userLat, $userLat, $userLng, $userLat, $userLat, $userLng, $userLat];
        }

        $freshCutoff = now()->subDays(7)->toDateTimeString();
        $scoreParts[] = 'CASE WHEN items.created_at >= ? THEN 8 ELSE 0 END';
        $scoreBindings[] = $freshCutoff;

        $scoreSql = implode(' + ', $scoreParts);
        $query->selectRaw("({$scoreSql}) as recommendation_score", $scoreBindings)
            ->orderByDesc('recommendation_score')
            ->orderByDesc('items.created_at');
    }

    protected function buildRecommendationContext(Request $request): array
    {
        $actorId = $request->user()?->id;
        if (! $actorId && $request->session()->has('guest_seller_user_id')) {
            $actorId = (int) $request->session()->get('guest_seller_user_id');
        }
        if (! $actorId) {
            return [
                'actor_id' => null,
                'category_hints' => [],
                'location_prefix' => '',
                'price_hint' => null,
                'user_lat' => null,
                'user_lng' => null,
            ];
        }

        $listingCategoryHints = Item::query()
            ->where('user_id', $actorId)
            ->whereNotNull('category')
            ->latest()
            ->limit(5)
            ->pluck('category')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $exchangeCategoryHints = ExchangeRequest::query()
            ->join('items', 'items.id', '=', 'exchange_requests.item_id')
            ->where('exchange_requests.sender_id', $actorId)
            ->whereNotNull('items.category')
            ->latest('exchange_requests.created_at')
            ->limit(6)
            ->pluck('items.category')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $categoryHints = collect([...$listingCategoryHints, ...$exchangeCategoryHints])
            ->filter()
            ->unique()
            ->values()
            ->all();

        $locationHint = (string) Item::query()
            ->where('user_id', $actorId)
            ->whereNotNull('location')
            ->latest()
            ->value('location');
        $locationPrefix = trim((string) str($locationHint)->before(','));

        $priceHint = Item::query()
            ->where('user_id', $actorId)
            ->whereNotNull('price')
            ->latest()
            ->limit(8)
            ->avg('price');
        if (! $priceHint) {
            $priceHint = ExchangeRequest::query()
                ->join('items', 'items.id', '=', 'exchange_requests.item_id')
                ->where('exchange_requests.sender_id', $actorId)
                ->whereNotNull('items.price')
                ->latest('exchange_requests.created_at')
                ->limit(8)
                ->avg('items.price');
        }

        $userLat = is_numeric($request->input('user_lat')) ? (float) $request->input('user_lat') : null;
        $userLng = is_numeric($request->input('user_lng')) ? (float) $request->input('user_lng') : null;
        if ($userLat === null || $userLng === null) {
            $lastGeo = Item::query()
                ->where('user_id', $actorId)
                ->whereNotNull('location_lat')
                ->whereNotNull('location_lng')
                ->latest()
                ->first(['location_lat', 'location_lng']);
            if ($lastGeo) {
                $userLat = (float) $lastGeo->location_lat;
                $userLng = (float) $lastGeo->location_lng;
            }
        }

        return [
            'actor_id' => $actorId,
            'category_hints' => $categoryHints,
            'location_prefix' => $locationPrefix,
            'price_hint' => $priceHint ? (float) $priceHint : null,
            'user_lat' => $userLat,
            'user_lng' => $userLng,
        ];
    }

    protected function annotateRecommendationReasons($items, array $context)
    {
        $freshCutoff = now()->subDays(7);

        return $items->map(function (Item $item) use ($context, $freshCutoff) {
            $reasons = [];

            if (in_array((string) $item->category, $context['category_hints'], true)) {
                $reasons[] = 'Matches your category';
            }

            if ($context['location_prefix'] !== '' && str_starts_with((string) $item->location, $context['location_prefix'])) {
                $reasons[] = 'Near your area';
            }

            if ($context['price_hint'] && $item->price !== null) {
                $diff = abs((float) $item->price - (float) $context['price_hint']);
                if ($diff <= max(500.0, (float) $context['price_hint'] * 0.25)) {
                    $reasons[] = 'Price match';
                }
            }

            if ($context['user_lat'] !== null && $context['user_lng'] !== null && $item->location_lat && $item->location_lng) {
                $distance = $this->distanceKm((float) $context['user_lat'], (float) $context['user_lng'], (float) $item->location_lat, (float) $item->location_lng);
                if ($distance <= 10) {
                    $reasons[] = 'Near you';
                }
            }

            if ($item->created_at && $item->created_at->greaterThanOrEqualTo($freshCutoff)) {
                $reasons[] = 'Recently listed';
            }

            $item->recommendation_reasons = collect($reasons)->unique()->take(2)->values()->all();
            return $item;
        });
    }

    protected function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    protected function uploadItemImageAndGetUrl(UploadedFile $file): string
    {
        if (! $this->isCloudinaryConfigured()) {
            $path = $file->store('items', 'public');
            return '/storage/'.$path;
        }

        try {
            $folder = (string) config('cloudinary.upload.folder', 'swapship_items');
            $upload = $this->cloudinaryClient()
                ->uploadApi()
                ->upload($file->getRealPath(), [
                    'folder' => $folder,
                    'resource_type' => 'image',
                ]);

            $secureUrl = (string) ($upload['secure_url'] ?? '');
            if ($secureUrl !== '') {
                return $secureUrl;
            }
        } catch (\Throwable $e) {
            Log::warning('Cloudinary upload failed, falling back to local storage.', [
                'message' => $e->getMessage(),
            ]);
        }

        $path = $file->store('items', 'public');
        return '/storage/'.$path;
    }

    protected function deleteCloudinaryAssetByUrl(string $url): void
    {
        if (! $this->isCloudinaryConfigured()) {
            return;
        }

        $publicId = $this->extractCloudinaryPublicIdFromUrl($url);
        if ($publicId === null) {
            return;
        }

        try {
            $this->cloudinaryClient()->uploadApi()->destroy($publicId, [
                'resource_type' => 'image',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Cloudinary asset delete failed.', [
                'public_id' => $publicId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function extractCloudinaryPublicIdFromUrl(string $url): ?string
    {
        $cloudName = (string) config('cloudinary.cloud.cloud_name');
        if ($cloudName === '' || ! str_contains($url, "res.cloudinary.com/{$cloudName}/")) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        // Example path: /<cloud>/image/upload/v123/folder/file.jpg
        if (! preg_match('#/image/upload/(?:v\d+/)?(.+)$#', $path, $matches)) {
            return null;
        }

        $publicPath = $matches[1] ?? '';
        if ($publicPath === '') {
            return null;
        }

        return preg_replace('/\.[a-zA-Z0-9]+$/', '', $publicPath) ?: null;
    }

    protected function isCloudinaryConfigured(): bool
    {
        return (string) config('cloudinary.cloud.cloud_name') !== ''
            && (string) config('cloudinary.cloud.api_key') !== ''
            && (string) config('cloudinary.cloud.api_secret') !== '';
    }

    protected function cloudinaryClient(): Cloudinary
    {
        return new Cloudinary([
            'cloud' => [
                'cloud_name' => (string) config('cloudinary.cloud.cloud_name'),
                'api_key' => (string) config('cloudinary.cloud.api_key'),
                'api_secret' => (string) config('cloudinary.cloud.api_secret'),
            ],
            'url' => [
                'secure' => true,
            ],
        ]);
    }

    protected function uploadBillToCloudinary(UploadedFile $file): string
    {
        if (! $this->isCloudinaryConfigured()) {
            return '/storage/'.$file->store('item-bills', 'public');
        }

        try {
            $upload = $this->cloudinaryClient()
                ->uploadApi()
                ->upload($file->getRealPath(), [
                    'folder' => 'swapship_bills',
                    'resource_type' => 'auto',
                ]);

            return (string) ($upload['secure_url'] ?? '');
        } catch (\Throwable $e) {
            Log::warning('Cloudinary bill upload failed.', ['message' => $e->getMessage()]);
            return '/storage/'.$file->store('item-bills', 'public');
        }
    }

    protected function deleteBillFromStorage(?string $url): void
    {
        if (empty($url)) {
            return;
        }

        if (str_contains($url, 'res.cloudinary.com')) {
            if (! $this->isCloudinaryConfigured()) {
                return;
            }
            try {
                $publicId = $this->extractCloudinaryPublicIdFromUrl($url);
                if ($publicId) {
                    $this->cloudinaryClient()->uploadApi()->destroy($publicId, ['resource_type' => 'auto']);
                }
            } catch (\Throwable $e) {
                Log::warning('Cloudinary bill delete failed.', ['url' => $url, 'message' => $e->getMessage()]);
            }
        } elseif (str_starts_with($url, '/storage/')) {
            $path = ltrim(str_replace('/storage/', '', $url), '/');
            if ($path !== '') {
                Storage::disk('public')->delete($path);
            }
        }
    }

    /**
     * Use subcategory when provided; otherwise fall back to parent category.
     */
    protected function resolveListingCategory(Request $request): string
    {
        $category = trim((string) $request->input('category', ''));
        if ($category !== '') {
            return $category;
        }

        $parent = trim((string) $request->input('parent_category', ''));
        if ($parent !== '' && array_key_exists($parent, self::CATEGORIES)) {
            return $parent;
        }

        return '';
    }
}
