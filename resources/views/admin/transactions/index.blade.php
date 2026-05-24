<x-app-layout>
    <div class="admin-shell">
        @include('admin.partials.hero', [
            'title' => 'Buyer transactions',
            'subtitle' => 'Every payment by buyers — name, item, and amount.',
        ])
        @include('admin.partials.nav')
        @include('admin.partials.alerts')

        <div class="admin-stats">
            @foreach([
                ['label' => 'All transactions', 'value' => $summary['total_transactions'], 'delay' => 2],
                ['label' => 'Completed', 'value' => $summary['completed_by_buyers'], 'delay' => 3],
                ['label' => 'Total volume', 'value' => '₹'.number_format($summary['total_amount'], 0), 'delay' => 4],
                ['label' => 'Platform fees', 'value' => '₹'.number_format($summary['platform_fees'], 2), 'delay' => 5],
            ] as $stat)
                <article class="card admin-stat-card admin-anim-in admin-anim-delay-{{ $stat['delay'] }}">
                    <p class="admin-stat-label">{{ $stat['label'] }}</p>
                    <h3 class="admin-stat-value">{{ $stat['value'] }}</h3>
                    <span class="admin-stat-shine" aria-hidden="true"></span>
                </article>
            @endforeach
        </div>

        <section class="card admin-panel admin-anim-in admin-anim-delay-3">
            <div class="admin-panel-head">
                <h2>Transactions by buyers</h2>
            </div>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Buyer name</th>
                            <th>Email</th>
                            <th>Item</th>
                            <th>Seller</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($buyerTransactions as $order)
                            <tr class="admin-table-row">
                                <td>
                                    <span class="admin-table-user">
                                        <span class="admin-avatar admin-avatar-sm">{{ $order->buyer?->initials() ?? '?' }}</span>
                                        <strong>{{ $order->buyer?->name ?? 'Unknown' }}</strong>
                                    </span>
                                </td>
                                <td class="muted">{{ $order->buyer?->email ?? '—' }}</td>
                                <td>{{ $order->shipment?->exchangeRequest?->item?->title ?? 'N/A' }}</td>
                                <td>{{ $order->seller?->name ?? 'N/A' }}</td>
                                <td><span class="admin-amount">₹{{ number_format((float) $order->total_amount, 2) }}</span></td>
                                <td>
                                    @if($order->delivery_verified_at)
                                        <span class="admin-badge admin-badge-ok">Completed</span>
                                    @elseif(in_array($order->payment_status, ['paid', 'collected'], true))
                                        <span class="admin-badge">Paid</span>
                                    @else
                                        <span class="admin-badge admin-badge-warn">{{ ucfirst($order->payment_status) }}</span>
                                    @endif
                                </td>
                                <td class="muted">{{ $order->created_at?->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="muted admin-empty">No buyer transactions yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="admin-pagination">{{ $buyerTransactions->links() }}</div>
        </section>
    </div>
</x-app-layout>
