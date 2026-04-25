<x-app-layout>
    <div
        x-data="exploreItems({
            endpoint: '{{ route('items.index') }}',
            saveSearchEndpoint: '{{ route('saved-searches.store') }}',
            categories: @js($categories),
            locations: @js($locations),
            savedSearches: @js($savedSearches->map(fn($search) => [
                'id' => $search->id,
                'name' => $search->name,
                'filters' => $search->filters,
                'deleteUrl' => route('saved-searches.destroy', $search),
            ])),
            initialFilters: @js([
                'search' => request('search'),
                'category' => request('category'),
                'location' => request('location'),
                'type' => request('type'),
                'condition' => request('condition'),
                'min_price' => request('min_price'),
                'max_price' => request('max_price'),
                'distance_km' => request('distance_km'),
                'user_lat' => request('user_lat'),
                'user_lng' => request('user_lng'),
                'recommended_first' => request()->boolean('recommended_first', true) ? '1' : '',
                'sort' => $sort,
            ]),
            initialTotal: {{ $items->total() }}
        })"
        class="explore-shell"
    >
    <div class="explore-ambient explore-ambient-a" aria-hidden="true"></div>
    <div class="explore-ambient explore-ambient-b" aria-hidden="true"></div>
    <section class="explore-header card">
        <div>
            <p class="explore-eyebrow">Explore Items</p>
            <h1 class="explore-title">Find the right item faster</h1>
            <p class="explore-subtitle">Search by name, category, or keyword. Filter by type, location, and condition.</p>
            <div class="explore-quick-pills">
                <button type="button" class="explore-quick-pill" @click="filters.sort = 'latest'; fetchItems()">Newest first</button>
                <button type="button" class="explore-quick-pill" @click="filters.type = 'exchange'; fetchItems()">Exchange only</button>
                <button type="button" class="explore-quick-pill" @click="filters.type = 'sell'; fetchItems()">For sale</button>
                <button type="button" class="explore-quick-pill" @click="filters.recommended_first = filters.recommended_first ? '' : '1'; fetchItems()">
                    <span x-text="filters.recommended_first ? 'Recommended ON' : 'Recommended OFF'"></span>
                </button>
            </div>
        </div>
        <div class="explore-count"><span x-text="total"></span> items</div>
    </section>

    <section class="card explore-controls">
        <form method="GET" action="{{ route('items.index') }}" class="explore-form" @submit.prevent="fetchItems()">
            <div class="explore-block">
                <label for="search">Search</label>
                <input id="search" name="search" value="{{ request('search') }}" x-model="filters.search" @input.debounce.350ms="fetchItems()" placeholder="Search item name, category, keyword">
            </div>

            <div class="explore-grid">
                <div class="explore-block explore-field" @click.outside="closeSuggestions()">
                    <label for="category">Category</label>
                    <input
                        id="category"
                        name="category"
                        x-model="filters.category"
                        @focus="openSuggestions('category')"
                        @input="openSuggestions('category')"
                        @input.debounce.250ms="fetchItems()"
                        placeholder="Type category (e.g. Mobile, Electronics, Books)"
                        autocomplete="off"
                    >
                    <div class="explore-suggestion-box" x-show="isSuggestionOpen('category')" x-transition.opacity.duration.150ms>
                        <template x-for="option in filteredSuggestions('category')" :key="'category-' + option">
                            <button type="button" class="explore-suggestion-item" @click="selectSuggestion('category', option)" x-text="option"></button>
                        </template>
                        <p class="explore-suggestion-empty" x-show="!filteredSuggestions('category').length">No matching categories</p>
                    </div>
                </div>

                <div class="explore-block explore-field" @click.outside="closeSuggestions()">
                    <label for="location">Location</label>
                    <input
                        id="location"
                        name="location"
                        x-model="filters.location"
                        @focus="openSuggestions('location')"
                        @input="openSuggestions('location')"
                        @input.debounce.250ms="fetchItems()"
                        placeholder="Type city or area (e.g. New Delhi, Old Delhi)"
                        autocomplete="off"
                    >
                    <div class="explore-suggestion-box" x-show="isSuggestionOpen('location')" x-transition.opacity.duration.150ms>
                        <template x-for="option in filteredSuggestions('location')" :key="'location-' + option">
                            <button type="button" class="explore-suggestion-item" @click="selectSuggestion('location', option)" x-text="option"></button>
                        </template>
                        <p class="explore-suggestion-empty" x-show="!filteredSuggestions('location').length">No matching locations</p>
                    </div>
                </div>

                <div class="explore-block">
                    <label for="type">Type</label>
                    <select id="type" name="type" x-model="filters.type" @change="fetchItems()">
                        <option value="">All types</option>
                        @foreach(['sell', 'exchange', 'both'] as $type)
                            <option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="explore-block">
                    <label for="condition">Condition</label>
                    <select id="condition" name="condition" x-model="filters.condition" @change="fetchItems()">
                        <option value="">All conditions</option>
                        @foreach($conditions as $condition)
                            <option value="{{ $condition }}" @selected(request('condition') === $condition)>{{ ucfirst($condition) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="explore-block">
                    <label for="sort">Sort by</label>
                    <select id="sort" name="sort" x-model="filters.sort" @change="fetchItems()">
                        <option value="latest" @selected($sort === 'latest')>Latest</option>
                        <option value="price_low" @selected($sort === 'price_low')>Price: Low to high</option>
                        <option value="price_high" @selected($sort === 'price_high')>Price: High to low</option>
                    </select>
                </div>

                <div class="explore-block">
                    <label for="min_price">Min price (INR)</label>
                    <input id="min_price" name="min_price" type="number" min="0" step="0.01" x-model="filters.min_price" @input.debounce.350ms="fetchItems()">
                </div>

                <div class="explore-block">
                    <label for="max_price">Max price (INR)</label>
                    <input id="max_price" name="max_price" type="number" min="0" step="0.01" x-model="filters.max_price" @input.debounce.350ms="fetchItems()">
                </div>

                <div class="explore-block">
                    <label for="distance_km">Distance radius (km)</label>
                    <select id="distance_km" name="distance_km" x-model="filters.distance_km" @change="fetchItems()">
                        <option value="">Any distance</option>
                        <option value="2">Within 2 km</option>
                        <option value="5">Within 5 km</option>
                        <option value="10">Within 10 km</option>
                        <option value="25">Within 25 km</option>
                        <option value="50">Within 50 km</option>
                    </select>
                </div>
            </div>

            <div class="explore-actions">
                <button class="btn btn-primary" type="submit">Apply</button>
                <button class="btn" type="button" @click="useMyLocation()">Use my location</button>
                <button class="btn" type="button" @click="saveCurrentSearch()">Save this search</button>
                <button class="btn" type="button" @click="reset()">Reset</button>
            </div>
            <input type="hidden" name="user_lat" :value="filters.user_lat">
            <input type="hidden" name="user_lng" :value="filters.user_lng">
            <input type="hidden" name="recommended_first" :value="filters.recommended_first">
        </form>
    </section>

    @if($savedSearches->isNotEmpty())
        <section class="card explore-controls" style="margin-top:12px;">
            <div class="explore-block">
                <label>Saved searches</label>
                <div class="explore-quick-pills">
                    <template x-for="saved in savedSearches" :key="saved.id">
                        <div style="display:flex; gap:6px; align-items:center;">
                            <button type="button" class="explore-quick-pill" @click="applySavedSearch(saved)" x-text="saved.name"></button>
                            <form method="POST" :action="saved.deleteUrl">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn" style="padding:4px 8px;">x</button>
                            </form>
                        </div>
                    </template>
                </div>
            </div>
        </section>
    @endif

    <div class="explore-live-wrap" @click="handlePaginationClick($event)">
        <template x-if="loading">
            <section class="explore-loading-grid">
                <article class="card explore-skeleton-card"></article>
                <article class="card explore-skeleton-card"></article>
                <article class="card explore-skeleton-card"></article>
            </section>
        </template>

        <div x-show="!loading" x-ref="results" class="explore-results" x-transition.opacity.duration.300ms>
            @include('items.partials.list', ['items' => $items])
        </div>
    </div>
    <noscript>
        <p class="muted" style="margin-top:12px;">Live filtering needs JavaScript, but search/filter and pagination still work with normal page reload.</p>
    </noscript>
    </div>
</x-app-layout>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('exploreItems', ({ endpoint, saveSearchEndpoint, categories, locations, savedSearches, initialFilters, initialTotal }) => ({
            endpoint,
            saveSearchEndpoint,
            categories,
            locations,
            savedSearches: savedSearches || [],
            total: initialTotal,
            loading: false,
            activeSuggestion: null,
            filters: { ...initialFilters },
            baseFilters: {
                ...initialFilters,
                search: '',
                category: '',
                location: '',
                type: '',
                condition: '',
                min_price: '',
                max_price: '',
                distance_km: '',
                user_lat: '',
                user_lng: '',
                recommended_first: '1',
                sort: 'latest'
            },
            currentPage: null,
            locationSeeds: ['New Delhi', 'Old Delhi', 'North Delhi', 'South Delhi', 'East Delhi', 'West Delhi', 'Delhi NCR'],

            buildUrl() {
                const params = new URLSearchParams();
                Object.entries(this.filters).forEach(([key, value]) => {
                    if (value) {
                        params.set(key, value);
                    }
                });
                if (this.currentPage) {
                    params.set('page', this.currentPage);
                }
                params.set('ajax', '1');
                return `${this.endpoint}?${params.toString()}`;
            },

            async fetchItems() {
                this.loading = true;
                this.currentPage = null;
                await this.load();
            },

            suggestionPool(kind) {
                if (kind === 'category') {
                    return this.categories || [];
                }
                const mergedLocations = [...(this.locations || []), ...this.locationSeeds];
                return [...new Set(mergedLocations.map((item) => String(item).trim()).filter(Boolean))];
            },

            filteredSuggestions(kind) {
                const query = (this.filters[kind] || '').toLowerCase().trim();
                const pool = this.suggestionPool(kind);

                const ranked = pool
                    .filter((item) => !query || item.toLowerCase().includes(query))
                    .sort((a, b) => {
                        const aStarts = a.toLowerCase().startsWith(query) ? 0 : 1;
                        const bStarts = b.toLowerCase().startsWith(query) ? 0 : 1;
                        if (aStarts !== bStarts) {
                            return aStarts - bStarts;
                        }
                        return a.localeCompare(b);
                    });

                return ranked.slice(0, 8);
            },

            openSuggestions(kind) {
                this.activeSuggestion = kind;
            },

            isSuggestionOpen(kind) {
                return this.activeSuggestion === kind;
            },

            closeSuggestions() {
                this.activeSuggestion = null;
            },

            selectSuggestion(kind, value) {
                this.filters[kind] = value;
                this.closeSuggestions();
                this.fetchItems();
            },

            async load() {
                const response = await fetch(this.buildUrl(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
                if (!response.ok) {
                    this.loading = false;
                    return;
                }
                const data = await response.json();
                this.$refs.results.innerHTML = data.itemsHtml;
                this.total = data.total;
                this.loading = false;

                const urlParams = new URLSearchParams();
                Object.entries(this.filters).forEach(([key, value]) => {
                    if (value) {
                        urlParams.set(key, value);
                    }
                });
                if (this.currentPage) {
                    urlParams.set('page', this.currentPage);
                }
                const nextUrl = urlParams.toString() ? `${this.endpoint}?${urlParams.toString()}` : this.endpoint;
                history.replaceState({}, '', nextUrl);
            },

            async handlePaginationClick(event) {
                const link = event.target.closest('.explore-pagination a');
                if (!link) {
                    return;
                }
                event.preventDefault();
                const targetUrl = new URL(link.href);
                this.currentPage = targetUrl.searchParams.get('page');
                this.loading = true;
                await this.load();
            },

            reset() {
                this.filters = { ...this.baseFilters };
                this.closeSuggestions();
                this.fetchItems();
            },

            async useMyLocation() {
                if (!navigator.geolocation) {
                    return;
                }
                navigator.geolocation.getCurrentPosition((position) => {
                    this.filters.user_lat = Number(position.coords.latitude).toFixed(7);
                    this.filters.user_lng = Number(position.coords.longitude).toFixed(7);
                    if (!this.filters.distance_km) {
                        this.filters.distance_km = '10';
                    }
                    this.fetchItems();
                });
            },

            async saveCurrentSearch() {
                const name = window.prompt('Name this search');
                if (!name) {
                    return;
                }
                const response = await fetch(this.saveSearchEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        name,
                        filters: this.filters
                    })
                });
                if (response.ok) {
                    window.location.reload();
                }
            },

            applySavedSearch(saved) {
                this.filters = { ...this.baseFilters, ...(saved.filters || {}) };
                this.fetchItems();
            }
        }));
    });
</script>
