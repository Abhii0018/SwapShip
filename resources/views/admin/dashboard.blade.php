<x-app-layout>
    <div class="admin-shell">
        @include('admin.partials.hero', [
            'title' => 'SwapShip Control Center',
            'subtitle' => 'Manage users, listings, and buyer transactions from one place.',
        ])

        @include('admin.partials.nav')
        @include('admin.partials.alerts')

        <div class="admin-actions-bar admin-anim-in admin-anim-delay-2">
            <a href="{{ route('admin.transactions.index') }}" class="btn btn-primary admin-cta-btn">
                View buyer transactions
            </a>
        </div>

        <div class="admin-stats">
            @foreach([
                ['label' => 'Users', 'value' => $stats['users'], 'delay' => 2],
                ['label' => 'Items listed', 'value' => $stats['items'], 'delay' => 3],
                ['label' => 'Exchanges', 'value' => $stats['exchanges'], 'delay' => 4],
                ['label' => 'Orders', 'value' => $stats['orders'], 'delay' => 5],
            ] as $stat)
                <article class="card admin-stat-card admin-anim-in admin-anim-delay-{{ $stat['delay'] }}">
                    <p class="admin-stat-label">{{ $stat['label'] }}</p>
                    <h3 class="admin-stat-value">{{ $stat['value'] }}</h3>
                    <span class="admin-stat-shine" aria-hidden="true"></span>
                </article>
            @endforeach
        </div>

        <div class="admin-grid-2">
            <section class="card admin-panel admin-anim-in admin-anim-delay-3">
                <div class="admin-panel-head">
                    <h2>Recent users</h2>
                    <a href="{{ route('admin.users.index') }}" class="admin-link">View all</a>
                </div>
                <div class="admin-list">
                    @forelse($recentUsers as $user)
                        <a href="{{ route('admin.users.show', $user) }}" class="admin-list-item">
                            <span class="admin-avatar">{{ $user->initials() }}</span>
                            <span class="admin-list-copy">
                                <strong>{{ $user->name }}</strong>
                                <small>{{ $user->email }}</small>
                            </span>
                            <span class="admin-chevron" aria-hidden="true">›</span>
                        </a>
                    @empty
                        <p class="muted admin-empty">No users yet.</p>
                    @endforelse
                </div>
            </section>

            <section class="card admin-panel admin-anim-in admin-anim-delay-4">
                <div class="admin-panel-head">
                    <h2>Recent listings</h2>
                    <a href="{{ route('admin.items.index') }}" class="admin-link">View all</a>
                </div>
                <div class="admin-list">
                    @forelse($recentItems as $item)
                        <div class="admin-list-item admin-list-item-static">
                            <span class="admin-list-copy">
                                <strong>{{ $item->title }}</strong>
                                <small>{{ $item->user?->name ?? 'Unknown' }} · ₹{{ number_format((float) $item->price, 0) }}</small>
                            </span>
                        </div>
                    @empty
                        <p class="muted admin-empty">No items yet.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
