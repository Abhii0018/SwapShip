<x-app-layout>
    <section
        class="sell-shell"
        x-data="sellForm({
            parentCategories: @js($parentCategories),
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

                    <div class="sell-field sell-field-full" id="category-fields">
                        <label for="parent_category">Category</label>
                        <select
                            id="parent_category"
                            x-model="selectedParent"
                            @change="updateSubcategories(); form.category = ''"
                        >
                            <option value="">Select category</option>
                            @foreach($parentCategories as $parent => $subs)
                                <option value="{{ $parent }}">{{ $parent }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sell-field sell-field-full" x-show="selectedParent" x-transition x-cloak>
                        <label for="category">Subcategory</label>
                        <select
                            id="category"
                            name="category"
                            x-model="form.category"
                            @change="form.category = $event.target.value"
                        >
                            <option value="">Select subcategory</option>
                            <template x-for="sub in subcategories" :key="sub">
                                <option :value="sub" x-text="sub"></option>
                            </template>
                        </select>
                        <small class="muted">Showing subcategories for "<span x-text="selectedParent"></span>"</small>
                    </div>

                    <div class="sell-field"></div>

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
                <div class="sell-field">
                        <label for="price">Price (INR)</label>
                        <input id="price" name="price" x-model="form.price" type="number" min="0" step="0.01" placeholder="Set your selling price (e.g. 14999)">
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
                    <div class="sell-permission-help" x-show="permissionHelp" x-cloak>
                        <p class="sell-permission-title">Enable precise location for accuracy</p>
                        <p class="sell-permission-desc" x-text="permissionHelp"></p>
                        <ol class="sell-permission-steps">
                            <template x-for="(step, idx) in permissionSteps" :key="'pstep-' + idx">
                                <li x-text="step"></li>
                            </template>
                        </ol>
                        <button type="button" class="btn btn-primary" @click="detectCurrentLocation()">Try GPS again</button>
                    </div>
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
        Alpine.data('sellForm', ({ parentCategories, categoryApi, locationApi, reverseApi }) => ({
            parentCategories,
            categoryApi,
            locationApi,
            reverseApi,
            maxImages: 3,
            openFor: null,
            selectedParent: '',
            subcategories: [],
            locationSuggestions: [],
            mapStatus: 'Pin the exact area on map for better trust.',
            permissionHelp: '',
            permissionSteps: [],
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
                price: @js(old('price', '')),
                description: @js(old('description', '')),
                notes: @js(old('notes', '')),
            },

            init() {
                this.loadDraft();
                this.$watch('form', () => this.saveDraft());
                this.$nextTick(() => {
                    this.initMap();
                    this.checkGeoPermission();
                    if (this.form.category) {
                        for (const [parent, subs] of Object.entries(this.parentCategories)) {
                            if (subs.includes(this.form.category)) {
                                this.selectedParent = parent;
                                this.subcategories = subs;
                                break;
                            }
                        }
                    }
                });
            },

            updateSubcategories() {
                if (this.selectedParent && this.parentCategories[this.selectedParent]) {
                    this.subcategories = this.parentCategories[this.selectedParent];
                } else {
                    this.subcategories = [];
                }
            },

            async checkGeoPermission() {
                try {
                    if (!navigator.permissions || !navigator.permissions.query) return;
                    const status = await navigator.permissions.query({ name: 'geolocation' });
                    if (status.state === 'denied') {
                        this.showPermissionHelp();
                    }
                    status.onchange = () => {
                        if (status.state === 'granted') {
                            this.permissionHelp = '';
                            this.permissionSteps = [];
                        } else if (status.state === 'denied') {
                            this.showPermissionHelp();
                        }
                    };
                } catch (_) {
                    // Permissions API not supported (older iOS Safari) — silently ignore.
                }
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
                if (kind !== 'location') return;
                const query = (this.form.location || '').trim();
                if (query.length < 2) {
                    this.locationSuggestions = [];
                    return;
                }

                const response = await fetch(`${this.locationApi}?q=${encodeURIComponent(query)}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
                if (!response.ok) {
                    return;
                }
                const data = await response.json();
                this.locationSuggestions = data.suggestions || [];
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
                if (!this.map) {
                    this.mapStatus = 'Map is still loading. Please wait a moment.';
                    return;
                }

                if (typeof window !== 'undefined' && window.location && window.location.protocol !== 'https:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
                    this.mapStatus = 'Browsers block GPS on insecure pages. Detecting approximate city...';
                    this.detectByIp(false);
                    return;
                }

                if (this.isInAppBrowser()) {
                    this.mapStatus = 'In-app browser blocks GPS. Detecting approximate city...';
                    this.permissionHelp = 'Open this page in your real browser for precise GPS:';
                    this.permissionSteps = [
                        'Tap the menu (three dots) at the top of the in-app browser.',
                        'Choose "Open in Chrome / Safari / your browser".',
                        'On the real browser, allow Location for this site, then come back here.',
                    ];
                    this.detectByIp(true);
                    return;
                }

                if (!navigator.geolocation) {
                    this.mapStatus = 'GPS not available. Detecting approximate city...';
                    this.detectByIp(false);
                    return;
                }
                this.mapStatus = 'Detecting your current location...';

                this.requestGeolocationWithFallback(true);
            },

            requestGeolocationWithFallback(highAccuracy) {
                navigator.geolocation.getCurrentPosition(
                    (position) => this.handleGeoSuccess(position),
                    (error) => this.handleGeoError(error, highAccuracy),
                    {
                        enableHighAccuracy: highAccuracy,
                        timeout: highAccuracy ? 25000 : 15000,
                        maximumAge: highAccuracy ? 0 : 60000,
                    }
                );
            },

            async handleGeoSuccess(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const accuracy = Math.round(position.coords.accuracy || 0);

                this.permissionHelp = '';
                this.permissionSteps = [];
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
            },

            async handleGeoError(error, wasHighAccuracy) {
                const code = error && error.code;
                if ((code === 2 || code === 3) && wasHighAccuracy) {
                    this.mapStatus = 'High-accuracy GPS failed. Retrying with normal accuracy...';
                    this.requestGeolocationWithFallback(false);
                    return;
                }

                const denied = code === 1;
                if (denied) {
                    this.mapStatus = 'GPS denied. Detecting approximate city from network...';
                    this.showPermissionHelp();
                } else if (code === 2) {
                    this.mapStatus = 'GPS signal unavailable. Detecting approximate city from network...';
                } else if (code === 3) {
                    this.mapStatus = 'GPS timed out. Detecting approximate city from network...';
                } else {
                    this.mapStatus = 'GPS failed. Detecting approximate city from network...';
                }
                await this.detectByIp(denied);
            },

            isInAppBrowser() {
                const ua = navigator.userAgent || '';
                return /Instagram|FBAN|FBAV|FB_IAB|Line\//i.test(ua)
                    || /Twitter|TwitterAndroid|MicroMessenger|WeChat|Snapchat|LinkedInApp/i.test(ua)
                    || /TikTok|musical_ly|GSA\//i.test(ua);
            },

            showPermissionHelp() {
                const ua = navigator.userAgent || '';
                const isAndroid = /Android/i.test(ua);
                const isIOS = /iPhone|iPad|iPod/i.test(ua);
                const isFirefox = /Firefox|FxiOS/i.test(ua);
                const isSamsung = /SamsungBrowser/i.test(ua);
                const isEdge = /Edg|EdgA|EdgiOS/i.test(ua);
                const isChromeAndroid = isAndroid && /Chrome/i.test(ua) && !/Edg|OPR|SamsungBrowser/i.test(ua);
                const isSafariIOS = isIOS && !/CriOS|FxiOS|EdgiOS|OPiOS/i.test(ua);
                const isChromeIOS = isIOS && /CriOS/i.test(ua);

                if (isSafariIOS || isChromeIOS) {
                    this.permissionHelp = 'iPhone is blocking precise GPS for this site. Enable it in two places:';
                    this.permissionSteps = [
                        'iPhone Settings → Privacy & Security → Location Services → ON.',
                        'Same screen → ' + (isChromeIOS ? 'Chrome' : 'Safari Websites') + ' → While Using the App.',
                        (isChromeIOS ? 'Chrome' : 'Safari') + ' → tap "AA" / "Aa" in URL bar → Website Settings → Location → Allow.',
                        'Reload this page and tap "Try GPS again" below.',
                    ];
                } else if (isAndroid && isSamsung) {
                    this.permissionHelp = 'Samsung Internet is blocking precise GPS. Enable it like this:';
                    this.permissionSteps = [
                        'Tap the lock icon next to the URL.',
                        'Tap "Permissions" → Location → Allow.',
                        'Phone Settings → Apps → Samsung Internet → Permissions → Location → Allow.',
                        'Reload and tap "Try GPS again" below.',
                    ];
                } else if (isAndroid && isFirefox) {
                    this.permissionHelp = 'Firefox on Android is blocking precise GPS. Enable it like this:';
                    this.permissionSteps = [
                        'Tap the menu (three dots) at top right.',
                        'Settings → Site permissions → Location → Allow.',
                        'Reload and tap "Try GPS again" below.',
                    ];
                } else if (isAndroid && isEdge) {
                    this.permissionHelp = 'Edge on Android is blocking precise GPS. Enable it like this:';
                    this.permissionSteps = [
                        'Tap the lock icon next to the URL.',
                        'Tap "Permissions" → Location → Allow.',
                        'Reload and tap "Try GPS again" below.',
                    ];
                } else if (isChromeAndroid) {
                    this.permissionHelp = 'Chrome on Android is blocking precise GPS. Enable it in 4 quick steps:';
                    this.permissionSteps = [
                        'Tap the lock icon next to the URL at the top.',
                        'Tap "Permissions" (or "Site settings").',
                        'Set "Location" to Allow.',
                        'Also: phone Settings → Location → ON.',
                        'Reload and tap "Try GPS again" below.',
                    ];
                } else if (isAndroid) {
                    this.permissionHelp = 'Your mobile browser is blocking precise GPS. Enable it like this:';
                    this.permissionSteps = [
                        'Tap the lock icon next to the URL at the top.',
                        'Open Site settings or Permissions.',
                        'Set Location to Allow.',
                        'Phone Settings → Location → ON.',
                        'Reload and tap "Try GPS again" below.',
                    ];
                } else {
                    this.permissionHelp = 'Your browser is blocking precise GPS. Enable it from the address bar lock icon → Site settings → Location → Allow, then reload.';
                    this.permissionSteps = [];
                }
            },

            async detectByIp(showPermissionTip = false) {
                const providers = [
                    { url: 'https://ipapi.co/json/', map: (d) => ({ lat: d.latitude, lng: d.longitude, city: d.city, region: d.region, country: d.country_name }) },
                    { url: 'https://ipwho.is/', map: (d) => d.success === false ? null : ({ lat: d.latitude, lng: d.longitude, city: d.city, region: d.region, country: d.country }) },
                    { url: 'https://api.bigdatacloud.net/data/client-ip', map: async (d) => {
                        if (!d || !d.ipString) return null;
                        const r = await fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?ipv4=${d.ipString}&localityLanguage=en`);
                        if (!r.ok) return null;
                        const j = await r.json();
                        return { lat: j.location?.latitude, lng: j.location?.longitude, city: j.city, region: j.principalSubdivision, country: j.countryName };
                    }},
                ];

                for (const p of providers) {
                    try {
                        const res = await fetch(p.url);
                        if (!res.ok) continue;
                        const json = await res.json();
                        const info = await p.map(json);
                        if (!info) continue;
                        const lat = Number(info.lat);
                        const lng = Number(info.lng);
                        if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue;

                        this.form.location_lat = lat.toFixed(7);
                        this.form.location_lng = lng.toFixed(7);
                        this.map.setView([lat, lng], 11);
                        if (!this.marker) {
                            this.marker = L.marker([lat, lng]).addTo(this.map);
                        } else {
                            this.marker.setLatLng([lat, lng]);
                        }

                        const parts = [info.city, info.region, info.country].filter(Boolean);
                        if (parts.length) {
                            this.form.location = parts.join(', ');
                        } else {
                            await this.reverseLookup(lat, lng);
                        }

                        const tip = showPermissionTip
                            ? ' (Approx city from network. For precise pin, allow location for this site in browser settings then tap again.)'
                            : ' (Approx city from network. Tap map to refine.)';
                        this.mapStatus = `Detected: ${this.form.location}${tip}`;
                        return;
                    } catch (_) {
                        // try next provider
                    }
                }

                this.mapStatus = showPermissionTip
                    ? 'Could not auto-detect. Allow location for this site in browser settings, or pin on map / type manually.'
                    : 'Could not auto-detect. Pin on map or type your area manually.';
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
                    price: '',
                    description: '',
                    notes: '',
                };
                this.selectedParent = '';
                this.subcategories = [];
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
