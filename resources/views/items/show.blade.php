<x-app-layout>
    @php($itemImages = $item->images->pluck('url')->filter()->values())
    @if($itemImages->isEmpty())
        @php($itemImages = collect(['https://via.placeholder.com/800x480']))
    @endif

    <div class="item-detail-topbar">
        <a href="{{ route('items.index') }}" class="btn item-detail-back-btn">
            <span aria-hidden="true">←</span>
            <span>Back</span>
        </a>
    </div>
    <section class="item-detail-shell">
        <section class="card item-detail-media">
            <div class="item-detail-media-main">
                <button type="button" class="item-detail-image-btn" id="item-detail-image-open-btn">
                    <img src="{{ $itemImages->first() }}" alt="{{ $item->title }}" class="item-detail-image" id="item-detail-main-image">
                </button>
                @if($itemImages->count() > 1)
                    <button type="button" class="item-gallery-nav item-gallery-prev" id="item-gallery-prev" aria-label="Previous image">&lt;</button>
                    <button type="button" class="item-gallery-nav item-gallery-next" id="item-gallery-next" aria-label="Next image">&gt;</button>
                @endif
                <span class="item-gallery-count" id="item-gallery-count">1 / {{ $itemImages->count() }}</span>
            </div>

            @if($itemImages->count() > 1)
                <div class="item-gallery-thumbs" id="item-gallery-thumbs">
                    @foreach($itemImages as $index => $imageUrl)
                        <button
                            type="button"
                            class="item-gallery-thumb {{ $index === 0 ? 'is-active' : '' }}"
                            data-index="{{ $index }}"
                            aria-label="Open image {{ $index + 1 }}"
                        >
                            <img src="{{ $imageUrl }}" alt="{{ $item->title }} image {{ $index + 1 }}">
                        </button>
                    @endforeach
                </div>
            @endif
        </section>
        <section class="card item-detail-content">
            <p class="item-detail-kicker">Item Details</p>
            <h1 class="item-detail-title">{{ $item->title }}</h1>
            @if($item->price)
                <p class="item-detail-price item-detail-price-top">₹{{ number_format((float) $item->price, 2) }}</p>
            @endif
            <p class="item-detail-desc">{{ $item->description }}</p>

            @if(filled($item->notes))
                <div class="item-detail-notes">
                    <strong>Additional information</strong>
                    <p>{{ $item->notes }}</p>
                </div>
            @endif

            <div class="item-detail-meta">
                <span class="item-detail-chip">Owner: {{ $item->user->name }}</span>
                <span class="item-detail-chip">{{ $item->location }}</span>
                <span class="item-detail-chip">Type: {{ ucfirst($item->type) }}</span>
                <span class="item-detail-chip">Condition: {{ ucfirst($item->condition) }}</span>
                <span class="item-detail-chip">Age: {{ $item->item_age ?: 'Not specified' }}</span>
            </div>

            @if($item->bill_url)
                <p><a class="btn" href="{{ $item->bill_url }}" target="_blank" rel="noopener">View bill</a></p>
            @endif
            @if(!auth()->check() || auth()->id() !== $item->user_id)
                <div class="item-detail-actions">
                    @php($activeConversation = $existingConversation ?? null)
                    @if($activeConversation)
                        <a class="btn" href="{{ route('chat.index', $activeConversation) }}">Chat with Owner</a>
                    @else
                        <form method="POST" action="{{ route('chat.start', $item) }}">
                            @csrf
                            <button class="btn" type="submit">Chat with Owner</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('exchanges.store', $item) }}">@csrf <button class="btn btn-primary">Request Exchange</button></form>
                </div>
            @else
                <a class="btn" href="{{ route('items.edit', $item) }}">Edit Item</a>
            @endif
        </section>
    </section>

    <div id="item-image-viewer" class="item-image-viewer" onclick="closeItemImageViewer()">
        <button type="button" class="item-image-viewer-close" onclick="closeItemImageViewer(event)">Close</button>
        <div class="item-image-viewer-content" onclick="event.stopPropagation()">
            <div class="item-image-viewer-media">
                <img id="item-image-viewer-img" src="" alt="Full image preview">
                @if($itemImages->count() > 1)
                    <button type="button" class="item-gallery-nav item-gallery-prev" id="item-viewer-prev" aria-label="Previous image">&lt;</button>
                    <button type="button" class="item-gallery-nav item-gallery-next" id="item-viewer-next" aria-label="Next image">&gt;</button>
                @endif
            </div>
            <section class="item-image-viewer-details">
                <h3 id="item-image-viewer-title"></h3>
                <p id="item-image-viewer-sub"></p>
                <div class="item-image-viewer-meta">
                    <span id="item-image-viewer-posted"></span>
                    <span>Recently listed</span>
                </div>
                <p class="item-image-viewer-price" id="item-image-viewer-price"></p>
                <p class="item-image-viewer-owner" id="item-image-viewer-owner"></p>
            </section>
        </div>
    </div>

    <script>
        const itemGalleryImages = @json($itemImages->values());
        let itemGalleryIndex = 0;
        const itemDetails = {
            title: @json($item->title),
            condition: @json(ucfirst($item->condition)),
            location: @json($item->location),
            posted: @json(optional($item->created_at)->diffForHumans()),
            price: @json($item->price ? '₹'.number_format((float) $item->price, 2) : 'Not listed'),
            owner: @json($item->user->name),
        };

        function updateMainImage(index) {
            if (!Array.isArray(itemGalleryImages) || itemGalleryImages.length === 0) return;
            const total = itemGalleryImages.length;
            itemGalleryIndex = ((index % total) + total) % total;
            const mainImage = document.getElementById('item-detail-main-image');
            const counter = document.getElementById('item-gallery-count');
            if (mainImage) mainImage.src = itemGalleryImages[itemGalleryIndex];
            if (counter) counter.textContent = `${itemGalleryIndex + 1} / ${total}`;
            document.querySelectorAll('.item-gallery-thumb').forEach((thumb) => {
                const thumbIndex = Number(thumb.getAttribute('data-index'));
                thumb.classList.toggle('is-active', thumbIndex === itemGalleryIndex);
            });
        }

        function openItemImageViewer(index = itemGalleryIndex, details = {}) {
            const viewer = document.getElementById('item-image-viewer');
            const image = document.getElementById('item-image-viewer-img');
            if (!viewer || !image || !itemGalleryImages.length) return;
            itemGalleryIndex = ((index % itemGalleryImages.length) + itemGalleryImages.length) % itemGalleryImages.length;
            image.src = itemGalleryImages[itemGalleryIndex];
            const title = document.getElementById('item-image-viewer-title');
            const sub = document.getElementById('item-image-viewer-sub');
            const posted = document.getElementById('item-image-viewer-posted');
            const price = document.getElementById('item-image-viewer-price');
            const owner = document.getElementById('item-image-viewer-owner');
            if (title) title.textContent = details.title || '';
            if (sub) sub.textContent = `${details.condition || ''} · ${details.location || ''}`.replace(/^ · | · $/g, '');
            if (posted) posted.textContent = details.posted ? `Posted ${details.posted}` : '';
            if (price) price.textContent = details.price || '';
            if (owner) owner.textContent = details.owner ? `Posted by ${details.owner}` : '';
            viewer.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }

        function closeItemImageViewer(event) {
            if (event) event.stopPropagation();
            const viewer = document.getElementById('item-image-viewer');
            const image = document.getElementById('item-image-viewer-img');
            if (!viewer || !image) return;
            viewer.classList.remove('is-open');
            image.src = '';
            document.body.style.overflow = '';
        }

        function stepGallery(delta) {
            updateMainImage(itemGalleryIndex + delta);
        }

        function stepViewer(delta) {
            if (!itemGalleryImages.length) return;
            itemGalleryIndex = ((itemGalleryIndex + delta) % itemGalleryImages.length + itemGalleryImages.length) % itemGalleryImages.length;
            const viewerImage = document.getElementById('item-image-viewer-img');
            const mainImage = document.getElementById('item-detail-main-image');
            if (viewerImage) viewerImage.src = itemGalleryImages[itemGalleryIndex];
            if (mainImage) mainImage.src = itemGalleryImages[itemGalleryIndex];
            updateMainImage(itemGalleryIndex);
        }

        document.addEventListener('DOMContentLoaded', () => {
            updateMainImage(0);

            document.getElementById('item-gallery-prev')?.addEventListener('click', () => stepGallery(-1));
            document.getElementById('item-gallery-next')?.addEventListener('click', () => stepGallery(1));
            document.getElementById('item-viewer-prev')?.addEventListener('click', () => stepViewer(-1));
            document.getElementById('item-viewer-next')?.addEventListener('click', () => stepViewer(1));

            document.getElementById('item-detail-image-open-btn')?.addEventListener('click', () => {
                openItemImageViewer(itemGalleryIndex, itemDetails);
            });

            document.querySelectorAll('.item-gallery-thumb').forEach((thumb) => {
                thumb.addEventListener('click', () => {
                    const targetIndex = Number(thumb.getAttribute('data-index') || 0);
                    updateMainImage(targetIndex);
                });
            });
        });

        document.addEventListener('keydown', (event) => {
            const viewer = document.getElementById('item-image-viewer');
            const isOpen = viewer?.classList.contains('is-open');
            if (event.key === 'Escape' && isOpen) closeItemImageViewer();
            if (!isOpen) return;
            if (event.key === 'ArrowLeft') stepViewer(-1);
            if (event.key === 'ArrowRight') stepViewer(1);
        });
    </script>
</x-app-layout>
