<x-app-layout>
    <section
        class="sell-shell"
        x-data="sellForm({
            categoryApi: '{{ route('items.suggest-categories') }}',
            locationApi: '{{ route('items.suggest-locations') }}',
            reverseApi: '{{ route('items.reverse-location') }}'
        })"
        x-init="init()"
    >
        <div class="sell-header card">
            <div>
                <p class="sell-eyebrow">Post Item</p>
                <h1 class="sell-title">Create your listing</h1>
                <p class="sell-subtitle">Fast. Clear. Ready to publish.</p>
            </div>
        </div>
        <nav class="sell-mobile-progress" aria-label="Listing steps">
            <a href="#sell-product">Product</a>
            <a href="#sell-setup">Setup</a>
            <a href="#sell-media">Media</a>
            <a href="#sell-submit">Publish</a>
        </nav>

        @if ($errors->any())
            <div class="card sell-errors">
                <h3>Please fix these fields</h3>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('items.store') }}" enctype="multipart/form-data" class="sell-form">
            @csrf
            <section class="card sell-card" id="sell-product">
                <h2>Product information</h2>
                <div class="sell-grid">
                    <div class="sell-field">
                        <label for="title">Ad title</label>
                        <input id="title" name="title" x-model="form.title" @blur="normalizeTitle()" required placeholder="e.g. iPhone 14 Pro 128GB, excellent condition">
                        <small class="muted" x-show="titleHint" x-text="titleHint"></small>
                    </div>

                    <div class="sell-field sell-autocomplete" @click.outside="closeSuggestions('category')">
                        <label for="category">Category</label>
                        <input
                            id="category"
                            name="category"
                            x-model="form.category"
                            @focus="openSuggestions('category')"
                            @input.debounce.250ms="fetchSuggestions('category')"
                            placeholder="Type category (Mobiles, Furniture, Books...)"
                            autocomplete="off"
                            required
                        >
                        <div class="sell-suggestions" x-show="isOpen('category')" x-transition.opacity.duration.120ms>
                            <template x-for="option in categorySuggestions" :key="'cat-' + option">
                                <button type="button" @click="pickSuggestion('category', option)" x-text="option"></button>
                            </template>
                            <p x-show="!categorySuggestions.length">No suggestions yet</p>
                        </div>
                    </div>

                    <div class="sell-field">
                        <label for="condition">Condition</label>
                        <select id="condition" name="condition" required>
                            @foreach($conditions as $condition)
                                <option value="{{ $condition }}" @selected(old('condition', 'used') === $condition)>{{ ucfirst($condition) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sell-field">
                        <label for="item_age">How old is the item?</label>
                        <input id="item_age" name="item_age" x-model="form.item_age" required placeholder="e.g. 8 months old, 2 years old">
                    </div>
                </div>
            </section>

            <section class="card sell-card" id="sell-setup">
                <h2>Listing setup</h2>
                <div class="sell-type-switch">
                    @foreach(['sell' => 'Sell only', 'exchange' => 'Exchange only', 'both' => 'Sell and Exchange'] as $value => $label)
                        <label class="sell-type-option">
                            <input type="radio" name="type" value="{{ $value }}" x-model="form.type" @checked(old('type', 'sell') === $value)>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="sell-grid">
                    <div class="sell-field" x-show="form.type === 'sell' || form.type === 'both'" x-transition>
                        <label for="price">Price (INR)</label>
                        <input id="price" name="price" x-model="form.price" type="number" min="0" step="0.01" :placeholder="pricePlaceholder()">
                    </div>

                    <div class="sell-field" x-show="form.type === 'exchange' || form.type === 'both'" x-transition>
                        <label for="exchange_preference">Exchange preference</label>
                        <input id="exchange_preference" name="exchange_preference" x-model="form.exchange_preference" placeholder="e.g. Android phone or gaming laptop">
                    </div>

                    <div class="sell-field sell-autocomplete" @click.outside="closeSuggestions('location')">
                        <label for="location">Location</label>
                        <div class="sell-location-mode">
                            <button
                                type="button"
                                class="sell-location-chip"
                                :class="{ 'is-active': locationMode === 'manual' }"
                                @click="setLocationMode('manual')"
                            >
                                Enter manually
                            </button>
                            <button
                                type="button"
                                class="sell-location-chip"
                                :class="{ 'is-active': locationMode === 'current' }"
                                @click="setLocationMode('current')"
                            >
                                Use current location
                            </button>
                        </div>
                        <input
                            id="location"
                            name="location"
                            x-model="form.location"
                            @focus="openSuggestions('location')"
                            @input.debounce.250ms="fetchSuggestions('location')"
                            placeholder="Search locality, city, area (New Delhi, Old Delhi...)"
                            autocomplete="off"
                            required
                        >
                        <small class="muted">You can type location manually or tap "Use current location".</small>
                        <input type="hidden" name="location_lat" :value="form.location_lat">
                        <input type="hidden" name="location_lng" :value="form.location_lng">
                        <div class="sell-suggestions" x-show="isOpen('location')" x-transition.opacity.duration.120ms>
                            <template x-for="option in locationSuggestions" :key="'loc-' + option">
                                <button type="button" @click="pickSuggestion('location', option)" x-text="option"></button>
                            </template>
                            <p x-show="!locationSuggestions.length">Start typing to search locations</p>
                        </div>
                    </div>
                </div>

                <div class="sell-map-wrap">
                    <div class="sell-map-head">
                        <p>Tap map to set exact area</p>
                        <button type="button" class="btn" @click="detectCurrentLocation()">Use my current location</button>
                    </div>
                    <div id="sell-location-map" class="sell-map"></div>
                    <p class="sell-map-note" x-text="mapStatus"></p>
                </div>
            </section>

            <section class="card sell-card" id="sell-media">
                <h2>Photos and description</h2>
                <div class="sell-field">
                    <label for="images">Item photos</label>
                    <div
                        class="sell-dropzone"
                        @dragover.prevent
                        @drop.prevent="handleDrop($event)"
                        @click="$refs.imageInput.click()"
                    >
                        <p>Drag and drop images or click to browse</p>
                        <small>First image becomes the cover photo. Drag thumbnails to reorder.</small>
                    </div>
                    <input x-ref="imageInput" id="images" name="images[]" type="file" multiple accept="image/*" @change="previewImages($event)" class="sell-file-hidden">
                    <small class="muted">Upload 1 to 3 images. First image becomes cover.</small>
                </div>

                <div class="sell-field">
                    <label for="bill">Upload bill (optional)</label>
                    <input id="bill" name="bill" type="file" accept=".pdf,.jpg,.jpeg,.png">
                    <small class="muted">Accepted: PDF, JPG, PNG. Max 5MB.</small>
                </div>

                <div class="sell-image-grid" x-show="selectedImages.length">
                    <template x-for="(img, index) in selectedImages" :key="img.id">
                        <article
                            class="sell-image-card"
                            draggable="true"
                            @dragstart="dragStart(index)"
                            @dragover.prevent
                            @drop="dropImage(index)"
                        >
                            <img :src="img.src" alt="Preview image">
                            <div class="sell-image-toolbar">
                                <span x-text="index === 0 ? 'Cover' : 'Photo ' + (index + 1)"></span>
                                <button type="button" @click="removeImage(index)">Remove</button>
                            </div>
                        </article>
                    </template>
                </div>

                <div class="sell-grid">
                    <div class="sell-field sell-field-full">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" x-model="form.description" rows="5" placeholder="Mention brand, model, age, usage, any defects, and what is included."></textarea>
                    </div>
                    <div class="sell-field sell-field-full">
                        <label for="notes">Additional notes</label>
                        <textarea id="notes" name="notes" x-model="form.notes" rows="3" placeholder="Pickup timings, negotiable price, preferred exchange terms, etc."></textarea>
                    </div>
                </div>
            </section>

            <div class="sell-actions" id="sell-submit">
                <button class="btn" type="reset" @click.prevent="resetEnhancements()">Reset</button>
                <button class="btn btn-primary" type="submit">Publish Listing</button>
            </div>
        </form>
    </section>
</x-app-layout>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('sellForm', ({ categoryApi, locationApi, reverseApi }) => ({
            categoryApi,
            locationApi,
            reverseApi,
            maxImages: 3,
            openFor: null,
            categorySuggestions: [],
            locationSuggestions: [],
            mapStatus: 'Pin the exact area on map for better trust.',
            locationMode: 'manual',
            titleHint: '',
            selectedImages: [],
            draggingIndex: null,
            map: null,
            marker: null,
            form: {
                title: @js(old('title', '')),
                category: @js(old('category', '')),
                location: @js(old('location', '')),
                location_lat: @js(old('location_lat', '')),
                location_lng: @js(old('location_lng', '')),
                item_age: @js(old('item_age', '')),
                type: @js(old('type', 'sell')),
                price: @js(old('price', '')),
                exchange_preference: @js(old('exchange_preference', '')),
                description: @js(old('description', '')),
                notes: @js(old('notes', '')),
            },

            init() {
                this.loadDraft();
                this.$watch('form', () => this.saveDraft());
                this.$nextTick(() => {
                    this.initMap();
                });
            },

            saveDraft() {
                localStorage.setItem('swapship_item_draft', JSON.stringify(this.form));
            },

            loadDraft() {
                const raw = localStorage.getItem('swapship_item_draft');
                if (!raw) {
                    return;
                }
                try {
                    const parsed = JSON.parse(raw);
                    this.form = { ...this.form, ...parsed };
                } catch (_) {
                    // ignore malformed draft
                }
            },

            openSuggestions(kind) {
                this.openFor = kind;
            },

            closeSuggestions(kind) {
                if (this.openFor === kind) {
                    this.openFor = null;
                }
            },

            isOpen(kind) {
                return this.openFor === kind;
            },

            async fetchSuggestions(kind) {
                const query = (this.form[kind] || '').trim();
                if (query.length < 2) {
                    if (kind === 'category') {
                        this.categorySuggestions = [];
                    } else {
                        this.locationSuggestions = [];
                    }
                    return;
                }

                const api = kind === 'category' ? this.categoryApi : this.locationApi;
                const response = await fetch(`${api}?q=${encodeURIComponent(query)}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
                if (!response.ok) {
                    return;
                }
                const data = await response.json();
                if (kind === 'category') {
                    this.categorySuggestions = data.suggestions || [];
                } else {
                    this.locationSuggestions = data.suggestions || [];
                }
            },

            pickSuggestion(kind, value) {
                this.form[kind] = value;
                this.closeSuggestions(kind);
            },

            normalizeTitle() {
                const rules = [
                    { wrong: /\bsumsung\b/gi, right: 'Samsung' },
                    { wrong: /\bsamsumg\b/gi, right: 'Samsung' },
                    { wrong: /\biphon\b/gi, right: 'iPhone' },
                    { wrong: /\bredmii\b/gi, right: 'Redmi' },
                ];

                let updated = this.form.title || '';
                let changed = false;

                for (const rule of rules) {
                    const next = updated.replace(rule.wrong, rule.right);
                    if (next !== updated) {
                        updated = next;
                        changed = true;
                    }
                }

                if (changed) {
                    this.form.title = updated;
                    this.titleHint = 'Title auto-corrected for common brand typos.';
                    window.setTimeout(() => { this.titleHint = ''; }, 2500);
                } else {
                    this.titleHint = '';
                }
            },

            setLocationMode(mode) {
                this.locationMode = mode;
                if (mode === 'current') {
                    this.detectCurrentLocation();
                } else {
                    this.mapStatus = 'Manual location mode active. You can type location or pin on map.';
                }
            },

            previewImages(event) {
                const incoming = Array.from(event.target.files || []).filter((file) => file.type.startsWith('image/'));
                if (!incoming.length) {
                    return;
                }

                const slotsLeft = Math.max(0, this.maxImages - this.selectedImages.length);
                const files = incoming.slice(0, slotsLeft);
                if (!files.length) {
                    this.mapStatus = `You can upload maximum ${this.maxImages} images.`;
                    return;
                }

                const additions = files.map((file, idx) => ({
                    id: `${Date.now()}-${idx}-${Math.random().toString(36).slice(2)}`,
                    file,
                    src: URL.createObjectURL(file),
                }));
                this.selectedImages = [...this.selectedImages, ...additions];
                this.syncImageInput();
            },

            handleDrop(event) {
                const files = Array.from(event.dataTransfer?.files || []).filter((file) => file.type.startsWith('image/'));
                if (!files.length) {
                    return;
                }
                this.previewImages({ target: { files } });
            },

            dragStart(index) {
                this.draggingIndex = index;
            },

            dropImage(targetIndex) {
                if (this.draggingIndex === null || this.draggingIndex === targetIndex) {
                    return;
                }
                const moved = this.selectedImages.splice(this.draggingIndex, 1)[0];
                this.selectedImages.splice(targetIndex, 0, moved);
                this.draggingIndex = null;
                this.syncImageInput();
            },

            removeImage(index) {
                const removed = this.selectedImages[index];
                if (removed?.src?.startsWith('blob:')) {
                    URL.revokeObjectURL(removed.src);
                }
                this.selectedImages.splice(index, 1);
                this.syncImageInput();
            },

            filesToFileList(files) {
                const dt = new DataTransfer();
                files.forEach((file) => dt.items.add(file));
                return dt.files;
            },

            syncImageInput() {
                const files = this.selectedImages.map((img) => img.file);
                this.$refs.imageInput.files = this.filesToFileList(files);
            },

            pricePlaceholder() {
                if (this.form.type === 'sell') {
                    return 'Set your selling price instantly (e.g. 14999)';
                }
                if (this.form.type === 'both') {
                    return 'Optional price if you also want to sell (e.g. 14999)';
                }
                return 'Price not required for exchange only';
            },

            initMap() {
                if (typeof L === 'undefined') {
                    this.mapStatus = 'Map failed to load. You can still type your location.';
                    return;
                }

                this.map = L.map('sell-location-map').setView([28.6139, 77.2090], 10);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(this.map);

                this.map.on('click', async (event) => {
                    const { lat, lng } = event.latlng;
                    this.form.location_lat = Number(lat).toFixed(7);
                    this.form.location_lng = Number(lng).toFixed(7);
                    if (!this.marker) {
                        this.marker = L.marker([lat, lng]).addTo(this.map);
                    } else {
                        this.marker.setLatLng([lat, lng]);
                    }
                    this.mapStatus = 'Fetching location details...';
                    await this.reverseLookup(lat, lng);
                });
            },

            async reverseLookup(lat, lng) {
                try {
                    const response = await fetch(`${this.reverseApi}?lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });
                    if (!response.ok) {
                        await this.browserReverseFallback(lat, lng);
                        return;
                    }
                    const data = await response.json();
                    if (data.location) {
                        this.form.location = data.location;
                        this.mapStatus = `Pinned: ${data.location}`;
                    } else {
                        await this.browserReverseFallback(lat, lng);
                    }
                } catch (_) {
                    await this.browserReverseFallback(lat, lng);
                }
            },

            async browserReverseFallback(lat, lng) {
                try {
                    const response = await fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${encodeURIComponent(lat)}&longitude=${encodeURIComponent(lng)}&localityLanguage=en`);
                    if (response.ok) {
                        const data = await response.json();
                        const parts = [data.locality, data.city, data.principalSubdivision, data.countryName]
                            .filter((value) => typeof value === 'string' && value.trim() !== '');
                        const uniqueParts = [...new Set(parts)].slice(0, 4);
                        if (uniqueParts.length) {
                            this.form.location = uniqueParts.join(', ');
                            this.mapStatus = `Pinned: ${this.form.location}`;
                            return;
                        }
                    }
                } catch (_) {
                    // Ignore and use coordinate fallback below.
                }

                const fallback = `${Number(lat).toFixed(5)}, ${Number(lng).toFixed(5)}`;
                this.form.location = fallback;
                this.mapStatus = `Pinned coordinates: ${fallback}`;
            },

            detectCurrentLocation() {
                if (!navigator.geolocation || !this.map) {
                    this.mapStatus = 'Geolocation is not available in this browser.';
                    return;
                }
                this.mapStatus = 'Detecting your current location...';

                navigator.geolocation.getCurrentPosition(async (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    const accuracy = Math.round(position.coords.accuracy || 0);

                    this.form.location_lat = Number(lat).toFixed(7);
                    this.form.location_lng = Number(lng).toFixed(7);
                    this.map.setView([lat, lng], 15);

                    if (!this.marker) {
                        this.marker = L.marker([lat, lng]).addTo(this.map);
                    } else {
                        this.marker.setLatLng([lat, lng]);
                    }

                    await this.reverseLookup(lat, lng);
                    if (accuracy > 0) {
                        this.mapStatus = `Location detected (approx ${accuracy}m accuracy).`;
                    }
                }, (error) => {
                    if (error && error.code === 1) {
                        this.mapStatus = 'Location permission denied. Allow location for this site in mobile browser settings, then tap again.';
                    } else if (error && error.code === 2) {
                        this.mapStatus = 'Could not detect current GPS location. Please try again or pin manually on map.';
                    } else if (error && error.code === 3) {
                        this.mapStatus = 'Location request timed out. Please try again.';
                    } else {
                        this.mapStatus = 'Could not detect current location. Please enter manually or tap map.';
                    }
                }, {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 0,
                });
            },

            resetEnhancements() {
                this.selectedImages.forEach((img) => {
                    if (img?.src?.startsWith('blob:')) {
                        URL.revokeObjectURL(img.src);
                    }
                });
                this.form = {
                    title: '',
                    category: '',
                    location: '',
                    location_lat: '',
                    location_lng: '',
                    item_age: '',
                    type: 'sell',
                    price: '',
                    exchange_preference: '',
                    description: '',
                    notes: '',
                };
                this.categorySuggestions = [];
                this.locationSuggestions = [];
                this.selectedImages = [];
                this.$refs.imageInput.value = '';
                this.mapStatus = 'Pin the exact area on map for better trust.';
                localStorage.removeItem('swapship_item_draft');
            }
        }));
    });
</script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="anonymous">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin="anonymous"></script>
