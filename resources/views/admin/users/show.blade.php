<x-app-layout>
    <div class="admin-shell">
        @include('admin.partials.hero', [
            'title' => $user->name,
            'subtitle' => $user->email.' · Joined '.$user->created_at?->format('d M Y'),
            'backUrl' => route('admin.users.index'),
            'backLabel' => 'All users',
        ])
        @include('admin.partials.nav')
        @include('admin.partials.alerts')

        <div class="admin-actions-bar admin-anim-in admin-anim-delay-2">
            <form method="POST" action="{{ route('admin.users.ban', $user) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn {{ $user->isBanned() ? 'btn-primary' : 'btn-danger' }}">
                    {{ $user->isBanned() ? 'Reactivate user' : 'Suspend user' }}
                </button>
            </form>
        </div>

        <section class="card admin-panel admin-anim-in admin-anim-delay-3">
            <div class="admin-panel-head">
                <h2>Published items</h2>
                <span class="admin-meta-pill">{{ $publishedItems->count() }}</span>
            </div>
            <div class="admin-list">
                @forelse($publishedItems as $item)
                    <article class="admin-list-item admin-list-item-static admin-item-row">
                        <span class="admin-list-copy">
                            <strong>{{ $item->title }}</strong>
                            <small>₹{{ number_format((float) $item->price, 2) }} · {{ $item->category }} · {{ $item->location }}</small>
                        </span>
                        <form method="POST" action="{{ route('admin.items.destroy', $item) }}" onsubmit="return confirm('Delete this listing?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </article>
                @empty
                    <p class="muted admin-empty">No published items.</p>
                @endforelse
            </div>
        </section>

        <section class="card admin-panel admin-anim-in admin-anim-delay-4">
            <div class="admin-panel-head">
                <h2>Purchase / exchange activity</h2>
                <span class="admin-meta-pill">{{ $purchaseExchanges->count() }}</span>
            </div>
            <div class="admin-list">
                @forelse($purchaseExchanges as $exchange)
                    <div class="admin-list-item admin-list-item-static">
                        <span class="admin-list-copy">
                            <strong>{{ $exchange->item?->title ?? 'Item removed' }}</strong>
                            <small>{{ $exchange->status }} · Seller: {{ $exchange->receiver?->name ?? 'N/A' }}</small>
                        </span>
                    </div>
                @empty
                    <p class="muted admin-empty">No activity yet.</p>
                @endforelse
            </div>
        </section>

        @if($ordersAsBuyer->isNotEmpty())
            <section class="card admin-panel admin-anim-in admin-anim-delay-5">
                <div class="admin-panel-head">
                    <h2>Orders as buyer</h2>
                    <span class="admin-meta-pill">{{ $ordersAsBuyer->count() }}</span>
                </div>
                <div class="admin-list">
                    @foreach($ordersAsBuyer as $order)
                        <div class="admin-list-item admin-list-item-static">
                            <span class="admin-list-copy">
                                <strong>Order #{{ $order->id }}</strong>
                                <small>
                                    {{ $order->payment_status }} · ₹{{ number_format((float) $order->total_amount, 2) }}
                                    @if($order->shipment?->exchangeRequest?->item)
                                        · {{ $order->shipment->exchangeRequest->item->title }}
                                    @endif
                                </small>
                            </span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
