<x-app-layout>
    <section class="card admin-shell">
        <h2>Admin Dashboard</h2>
        <div class="admin-stats">
            @foreach($stats as $label => $value)
                <article class="card admin-stat-card">
                    <p class="muted">{{ ucwords(str_replace('_', ' ', $label)) }}</p>
                    <h3>{{ $value }}</h3>
                </article>
            @endforeach
        </div>
    </section>
    <section class="card admin-panel">
        <h2>Order and OTP Tracking</h2>
        @forelse($recentOrders as $order)
            <article class="admin-row">
                <p><strong>Order #{{ $order->id }}</strong> · {{ strtoupper($order->payment_method) }} · {{ $order->payment_status }} · settlement {{ $order->settlement_status }}</p>
                <p class="muted">Item: {{ $order->shipment?->exchangeRequest?->item?->title ?? 'Item removed' }} | Buyer: {{ $order->buyer?->name ?? 'N/A' }} | Seller: {{ $order->seller?->name ?? 'N/A' }}</p>
                @php $latestOtp = $order->deliveryOtps->sortByDesc('created_at')->first(); @endphp
                <p class="muted">OTP: {{ $latestOtp && $latestOtp->verified_at ? 'Verified' : 'Pending/Not generated' }}</p>
            </article>
        @empty
            <p class="muted">No orders yet.</p>
        @endforelse
    </section>
    <section class="card admin-panel">
        <h2>SMS Audit Logs</h2>
        @forelse($recentSmsLogs as $log)
            <article class="admin-row">
                <p><strong>{{ strtoupper($log->status) }}</strong> · {{ strtoupper($log->channel) }} · {{ $log->created_at->format('d M, h:i A') }}</p>
                <p class="muted">Order: {{ $log->order_id ?? 'N/A' }} | Phone: {{ $log->phone ?: 'N/A' }}</p>
                <p class="muted">{{ $log->message }}</p>
            </article>
        @empty
            <p class="muted">No SMS logs yet.</p>
        @endforelse
    </section>
</x-app-layout>

<style>
    .admin-shell { display: grid; gap: 12px; }
    .admin-stats { display: grid; gap: 10px; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); }
    .admin-stat-card { animation: uiFadeSlideIn .45s cubic-bezier(0.16, 1, 0.3, 1) both; }
    .admin-stat-card h3 { margin: 6px 0 0; font-size: 1.7rem; }
    .admin-panel { margin-top: 12px; display: grid; gap: 10px; }
    .admin-row {
        padding: 10px;
        border: 1px solid rgba(255,255,255,.14);
        border-radius: 10px;
        background: linear-gradient(160deg, rgba(255,255,255,.03), rgba(255,255,255,.01));
        transition: transform .28s cubic-bezier(0.16, 1, 0.3, 1), border-color .28s cubic-bezier(0.16, 1, 0.3, 1);
        animation: uiFadeSlideIn .4s cubic-bezier(0.16, 1, 0.3, 1) both;
    }
    .admin-row:hover {
        transform: translateY(-2px);
        border-color: rgba(191,255,0,.35);
    }
</style>
