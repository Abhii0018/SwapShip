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

    <section class="explore-header card explore-header-compact">
        <div class="explore-head-top">
            <div>
                <p class="explore-eyebrow">Explore</p>
                <h1 class="explore-title">Find what you need</h1>
            </div>
            <div class="explore-count"><span x-text="total"></span> items</div>
        </div>
        <p class="explore-subtitle explore-subtitle-desktop">Search by name, category, or keyword. Filter by type, location, and condition.</p>
    </section>

    <section class="card explore-searchbar">
        <div class="explore-searchbar-row">
            <div class="explore-search-input">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
                <input id="search" name="search" value="{{ request('search') }}" x-model="filters.search" @input.debounce.350ms="fetchItems()" placeholder="Search items, brands, categories...">
                <button type="button" class="explore-search-clear" x-show="filters.search" @click="filters.search=''; fetchItems()" aria-label="Clear">×</button>
            </div>
            <button type="button" class="explore-filter-trigger" @click="filterPanelOpen = true" aria-label="Open filters">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="6" y1="12" x2="18" y2="12"></line><line x1="9" y1="18" x2="15" y2="18"></line></svg>
                <span class="explore-filter-trigger-label">Filters</span>
                <span class="explore-filter-badge" x-show="activeFilterCount() > 0" x-text="activeFilterCount()" x-cloak></span>
            </button>
        </div>

        <div class="explore-quick-scroll">
            <button type="button" class="explore-quick-pill" :class="{ 'is-active': filters.sort === 'latest' }" @click="filters.sort = 'latest'; fetchItems()">Newest</button>
            <button type="button" class="explore-quick-pill" :class="{ 'is-active': filters.sort === 'price_low' }" @click="filters.sort = 'price_low'; fetchItems()">Price: low to high</button>
            <button type="button" class="explore-quick-pill" :class="{ 'is-active': filters.sort === 'price_high' }" @click="filters.sort = 'price_high'; fetchItems()">Price: high to low</button>
            <button type="button" class="explore-quick-pill" :class="{ 'is-active': !!filters.recommended_first }" @click="filters.recommended_first = filters.recommended_first ? '' : '1'; fetchItems()">Recommended</button>
        </div>

        <div class="explore-active-chips" x-show="activeFilterCount() > 0" x-cloak>
            <template x-for="chip in activeChips()" :key="'chip-' + chip.key">
                <button type="button" class="explore-active-chip" @click="clearFilter(chip.key)">
                    <span x-text="chip.label"></span>
                    <span aria-hidden="true">×</span>
                </button>
            </template>
            <button type="button" class="explore-active-chip explore-active-chip-clear" @click="reset()">Clear all</button>
        </div>
    </section>

    <div class="explore-filter-overlay" x-show="filterPanelOpen" x-transition.opacity.duration.200ms @click="filterPanelOpen = false" x-cloak></div>
    <aside class="explore-filter-panel" :class="{ 'is-open': filterPanelOpen }" x-cloak>
        <header class="explore-filter-head">
            <div>
                <p class="explore-filter-title">Filters</p>
                <p class="explore-filter-sub" x-text="`${total} items match`"></p>
            </div>
            <button type="button" class="explore-filter-close" @click="filterPanelOpen = false" aria-label="Close">×</button>
        </header>

        <form method="GET" action="{{ route('items.index') }}" class="explore-form" @submit.prevent="fetchItems(); filterPanelOpen = false">
            <div class="explore-block explore-field" @click.outside="closeSuggestions()">
                <label for="category">Category</label>
                <input
                    id="category"
                    name="category"
                    x-model="filters.category"
                    @focus="openSuggestions('category')"
                    @input="openSuggestions('category')"
                    @input.debounce.250ms="fetchItems()"
                    placeholder="Mobile, Electronics, Books..."
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
                    placeholder="City or area"
                    autocomplete="off"
                >
                <div class="explore-suggestion-box" x-show="isSuggestionOpen('location')" x-transition.opacity.duration.150ms>
                    <template x-for="option in filteredSuggestions('location')" :key="'location-' + option">
                        <button type="button" class="explore-suggestion-item" @click="selectSuggestion('location', option)" x-text="option"></button>
                    </template>
                    <p class="explore-suggestion-empty" x-show="!filteredSuggestions('location').length">No matching locations</p>
                </div>
            </div>

            <div class="explore-grid explore-grid-pair">
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
                    <label for="min_price">Min price (INR)</label>
                    <input id="min_price" name="min_price" type="number" min="0" step="0.01" x-model="filters.min_price" @input.debounce.350ms="fetchItems()">
                </div>

                <div class="explore-block">
                    <label for="max_price">Max price (INR)</label>
                    <input id="max_price" name="max_price" type="number" min="0" step="0.01" x-model="filters.max_price" @input.debounce.350ms="fetchItems()">
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
                    <label for="distance_km">Within</label>
                    <select id="distance_km" name="distance_km" x-model="filters.distance_km" @change="fetchItems()">
                        <option value="">Any distance</option>
                        <option value="2">2 km</option>
                        <option value="5">5 km</option>
                        <option value="10">10 km</option>
                        <option value="25">25 km</option>
                        <option value="50">50 km</option>
                    </select>
                </div>
            </div>

            @if($savedSearches->isNotEmpty())
                <div class="explore-block">
                    <label>Saved searches</label>
                    <div class="explore-saved-list">
                        <template x-for="saved in savedSearches" :key="saved.id">
                            <div class="explore-saved-row">
                                <button type="button" class="explore-quick-pill is-saved" @click="applySavedSearch(saved); filterPanelOpen = false" x-text="saved.name"></button>
                                <form method="POST" :action="saved.deleteUrl">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="explore-saved-del" aria-label="Delete saved search">×</button>
                                </form>
                            </div>
                        </template>
                    </div>
                </div>
            @endif

            <input type="hidden" name="user_lat" :value="filters.user_lat">
            <input type="hidden" name="user_lng" :value="filters.user_lng">
            <input type="hidden" name="recommended_first" :value="filters.recommended_first">
        </form>

        <footer class="explore-filter-foot">
            <button class="btn" type="button" @click="reset()">Reset</button>
            <button class="btn" type="button" @click="useMyLocation()">Near me</button>
            <button class="btn" type="button" @click="saveCurrentSearch()">Save</button>
            <button class="btn btn-primary" type="button" @click="filterPanelOpen = false">Show <span x-text="total"></span> items</button>
        </footer>
    </aside>

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
            filterPanelOpen: false,
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
            },

            activeFilterKeys() {
                const ignored = ['sort', 'recommended_first', 'user_lat', 'user_lng'];
                return Object.entries(this.filters)
                    .filter(([key, value]) => !ignored.includes(key) && value !== '' && value !== null && value !== undefined)
                    .map(([key]) => key);
            },

            activeFilterCount() {
                return this.activeFilterKeys().length;
            },

            activeChips() {
                const labels = {
                    search: 'Search',
                    category: 'Category',
                    location: 'Location',
                    type: 'Type',
                    condition: 'Condition',
                    min_price: 'Min',
                    max_price: 'Max',
                    distance_km: 'Within'
                };
                return this.activeFilterKeys().map((key) => {
                    let value = this.filters[key];
                    if (key === 'min_price' || key === 'max_price') {
                        value = '\u20B9' + value;
                    } else if (key === 'distance_km') {
                        value = value + ' km';
                    }
                    return { key, label: `${labels[key] || key}: ${value}` };
                });
            },

            clearFilter(key) {
                this.filters[key] = '';
                this.fetchItems();
            }
        }));
    });
</script>
