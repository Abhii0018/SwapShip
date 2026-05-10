<section class="explore-listing">
    @forelse($items as $item)
        <article class="card explore-item-card">
            <a href="{{ route('items.show', $item) }}" class="explore-image-wrap" aria-label="View details of {{ $item->title }}">
                <img src="{{ $item->images->first()?->url ?? 'https://via.placeholder.com/600x450?text=SwapShip+Item' }}" alt="{{ $item->title }}" class="explore-item-image">
                <div class="explore-image-glow" aria-hidden="true"></div>
            </a>
            <div class="explore-item-content">
                <div class="explore-item-top">
                    <h3>{{ $item->title }}</h3>
                    <span class="explore-type-pill">Sell</span>
                </div>
                <p class="muted">{{ ucfirst($item->condition) }} · {{ $item->location }}</p>
                <div class="explore-meta-line">
                    <span class="explore-meta-chip">{{ $item->category ?: 'General' }}</span>
                    <span class="explore-meta-chip">Posted {{ optional($item->created_at)->diffForHumans() }}</span>
                </div>
                @if(!empty($item->recommendation_reasons))
                    <div class="explore-meta-line">
                        @foreach($item->recommendation_reasons as $reason)
                            <span class="explore-meta-chip">{{ $reason }}</span>
                        @endforeach
                    </div>
                @endif

                @if($item->price)
                    <p class="explore-price">Price: ₹{{ number_format((float) $item->price, 2) }}</p>
                @endif

                <p class="explore-owner">Posted by {{ $item->user?->name ?? 'User' }}</p>

                <div class="explore-item-actions">
                    <a class="btn" href="{{ route('items.show', $item) }}">View Details</a>
                    @if(!auth()->check() || auth()->id() !== $item->user_id)
                        <form method="POST" action="{{ route('exchanges.store', $item) }}">
                            @csrf
                            <button class="btn btn-primary" type="submit">Request Exchange</button>
                        </form>
                    @endif
                </div>
            </div>
        </article>
    @empty
        <div class="card explore-empty">
            <h3>No items found</h3>
            <p class="muted">Try changing your search or filters.</p>
            <a href="{{ route('items.index') }}" class="btn">Clear filters</a>
        </div>
    @endforelse
</section>

<section class="explore-pagination">
    {{ $items->links() }}
</section>
