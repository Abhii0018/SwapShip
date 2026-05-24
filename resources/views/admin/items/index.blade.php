<x-app-layout>
    <div class="admin-shell">
        @include('admin.partials.hero', [
            'title' => 'All items',
            'subtitle' => 'Review and remove listings that break platform rules.',
        ])
        @include('admin.partials.nav')
        @include('admin.partials.alerts')

        <section class="card admin-panel admin-anim-in admin-anim-delay-2">
            <div class="admin-panel-head">
                <h2>Marketplace listings</h2>
                <span class="admin-meta-pill">{{ $items->total() }} total</span>
            </div>

            <div class="admin-item-grid">
                @forelse($items as $item)
                    <article class="card explore-item-card admin-item-card admin-anim-in">
                        @if($item->images->first())
                            <div class="admin-item-card-image">
                                <img src="{{ $item->images->first()->url }}" alt="{{ $item->title }}" class="explore-item-image">
                                <div class="explore-image-glow" aria-hidden="true"></div>
                            </div>
                        @endif
                        <div class="explore-item-content">
                            <div class="explore-item-top">
                                <h3>{{ $item->title }}</h3>
                                <span class="explore-type-pill">{{ $item->category }}</span>
                            </div>
                            <p class="muted">{{ $item->user?->name ?? 'Unknown' }} · {{ $item->user?->email }}</p>
                            <p class="explore-price">₹{{ number_format((float) $item->price, 2) }}</p>
                            <p class="muted">{{ \Illuminate\Support\Str::limit((string) $item->description, 100) }}</p>
                            <div class="explore-item-actions">
                                <a href="{{ route('items.show', $item) }}" class="btn btn-sm" target="_blank" rel="noopener">View</a>
                                <a href="{{ route('admin.users.show', $item->user_id) }}" class="btn btn-sm">Owner</a>
                                <form method="POST" action="{{ route('admin.items.destroy', $item) }}" onsubmit="return confirm('Delete this item permanently?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </div>
                    </article>
                @empty
                    <p class="muted admin-empty">No items listed.</p>
                @endforelse
            </div>

            <div class="admin-pagination">{{ $items->links() }}</div>
        </section>
    </div>
</x-app-layout>
