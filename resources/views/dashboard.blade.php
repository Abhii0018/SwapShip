<x-app-layout>
    <section class="dash-shell" x-data="dashboardMobileUI()" x-init="init()">
        <section class="card dash-hero">
            <div>
                <p class="dash-kicker">Control Center</p>
                <h1>Exchange dashboard</h1>
                <p class="dash-hero-copy">Manage listings, requests, conversations, and shipment progress from one place.</p>
            </div>
            <div class="dash-hero-actions">
                <a class="btn btn-primary" href="{{ route('items.create') }}">Add Item</a>
                <a class="btn" href="{{ route('chat.index') }}">Open Chat</a>
                <a class="btn" href="{{ route('shipments.index') }}">Shipments</a>
            </div>
        </section>
        <nav class="dash-mobile-jump" aria-label="Dashboard sections">
            <button type="button" :class="{ 'is-active': activeTab === 'listings' }" @click="activeTab = 'listings'">Listings</button>
            <button type="button" :class="{ 'is-active': activeTab === 'requests' }" @click="activeTab = 'requests'">Requests</button>
            <button type="button" :class="{ 'is-active': activeTab === 'active' }" @click="activeTab = 'active'">Active</button>
            <button type="button" :class="{ 'is-active': activeTab === 'more' }" @click="activeTab = 'more'">More</button>
            <button type="button" :class="{ 'is-active': activeTab === 'actions' }" @click="activeTab = 'actions'">Actions</button>
        </nav>
        <section class="dash-summary" x-show="showSection('listings')">
            <article class="card dash-stat">
                <p>Total Items Listed</p>
                <h2>{{ $stats['listed_items'] }}</h2>
                <small>Listings currently in your portfolio</small>
            </article>
            <article class="card dash-stat">
                <p>Active Exchanges</p>
                <h2>{{ $stats['active_exchanges'] }}</h2>
                <small>Live trades requiring your action</small>
            </article>
            <article class="card dash-stat">
                <p>Completed Exchanges</p>
                <h2>{{ $stats['completed_exchanges'] }}</h2>
                <small>Closed and successfully delivered</small>
            </article>
        </section>

        <section class="card dash-block" id="dash-listings" x-show="showSection('listings')">
            <div class="dash-head">
                <p class="dash-kicker">Inventory</p>
                <h2>My Listings</h2>
            </div>
            <div class="dash-table">
                @forelse($myListings as $item)
                    <article class="dash-row">
                        <div>
                            <strong>{{ $item->title }}</strong>
                            <p>{{ ucfirst($item->type) }} · {{ ucfirst($item->condition) }}</p>
                        </div>
                        <div class="dash-status">{{ in_array($item->id, $activeExchanges->pluck('item_id')->all(), true) ? 'In Exchange' : 'Active' }}</div>
                        <div class="dash-actions">
                            <a class="btn" href="{{ route('items.show', $item) }}">View</a>
                            <a class="btn" href="{{ route('items.edit', $item) }}">Edit</a>
                            <form method="POST" action="{{ route('items.destroy', $item) }}">
                                @csrf @method('DELETE')
                                <button class="btn" type="submit">Delete</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <p class="muted">No listings yet.</p>
                @endforelse
            </div>
        </section>

        <section class="dash-grid-2" id="dash-requests" x-show="showSection('requests')">
            <section class="card dash-block">
                <div class="dash-head">
                    <p class="dash-kicker">Outbound</p>
                    <h2>Sent Requests</h2>
                </div>
                @forelse($sentRequests as $request)
                    <article class="dash-row">
                        <div>
                            <strong>{{ $request->item?->title ?? 'Item removed' }}</strong>
                            <p>To: {{ $request->receiver?->name ?? 'User' }}</p>
                        </div>
                        <div class="dash-status">{{ $request->status }}</div>
                        <div class="dash-actions">
                            @auth
                                <a class="btn" href="{{ route('exchanges.show', $request) }}">View details</a>
                            @else
                                <span class="muted">Login for details</span>
                            @endauth
                        </div>
                    </article>
                @empty
                    <p class="muted">No sent requests.</p>
                @endforelse
            </section>
            <section class="card dash-block">
                <div class="dash-head">
                    <p class="dash-kicker">Inbound</p>
                    <h2>Received Requests</h2>
                </div>
                @forelse($receivedRequests as $request)
                    <article class="dash-row">
                        <div>
                            <strong>{{ $request->item?->title ?? 'Item removed' }}</strong>
                            <p>From: {{ $request->sender?->name ?? 'User' }}</p>
                        </div>
                        <div class="dash-status">{{ $request->status }}</div>
                        <div class="dash-actions">
                            @if(auth()->check() && $request->status === 'Pending')
                                <form method="POST" action="{{ route('exchanges.update-status', $request) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="Accepted">
                                    <button class="btn btn-primary" type="submit">Accept</button>
                                </form>
                                <form method="POST" action="{{ route('exchanges.update-status', $request) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="Rejected">
                                    <button class="btn" type="submit">Reject</button>
                                </form>
                            @endif
                            @auth
                                <a class="btn" href="{{ route('exchanges.show', $request) }}">View details</a>
                            @else
                                <span class="muted">Login for actions</span>
                            @endauth
                        </div>
                    </article>
                @empty
                    <p class="muted">No received requests.</p>
                @endforelse
            </section>
        </section>

        <section class="card dash-block" id="dash-active" x-show="showSection('active')">
            <div class="dash-head">
                <p class="dash-kicker">Execution</p>
                <h2>Active Exchanges</h2>
            </div>
            @forelse($activeExchanges as $exchange)
                @php
                    $other = auth()->id() === $exchange->sender_id ? $exchange->receiver : $exchange->sender;
                    $exchangeOrder = $exchange->shipment?->order;
                    $isBuyerHere = auth()->id() === $exchange->sender_id;
                    $upfrontPaid = $exchangeOrder && !empty($exchangeOrder->upfront_paid_at);
                    $remainingRequired = $exchangeOrder && (float) ($exchangeOrder->remaining_amount ?? 0) > 0.0001;
                    $remainingPaid = $exchangeOrder && !empty($exchangeOrder->remaining_paid_at);
                    if (! $exchangeOrder) {
                        $paymentLabel = 'Awaiting deal terms';
                    } elseif (! $upfrontPaid) {
                        $paymentLabel = 'Awaiting upfront payment';
                    } elseif ($remainingRequired && ! $remainingPaid) {
                        $paymentLabel = 'Final doorstep payment due';
                    } else {
                        $paymentLabel = 'Paid';
                    }
                @endphp
                <article class="dash-row">
                    <div>
                        <strong>{{ $exchange->item?->title ?? 'Item removed' }}</strong>
                        <p>{{ $other?->name ?? 'User' }} · {{ $exchange->status }} · Shipment: {{ $exchange->shipment?->status ?? 'Not started' }}</p>
                        <p class="dash-payment-line"><span class="shipment-state-pill">{{ $paymentLabel }}</span></p>
                        <div class="dash-confirmation-progress">
                            <div class="dash-confirm-pill {{ $exchange->sender_confirmed_at ? 'is-done' : 'is-pending' }}">
                                <strong>Sender</strong>
                                <span>{{ $exchange->sender_confirmed_at ? 'Confirmed' : 'Pending' }}</span>
                            </div>
                            <div class="dash-confirm-pill {{ $exchange->receiver_confirmed_at ? 'is-done' : 'is-pending' }}">
                                <strong>Receiver</strong>
                                <span>{{ $exchange->receiver_confirmed_at ? 'Confirmed' : 'Pending' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="dash-actions">
                        <a class="btn" href="{{ route('chat.index', $exchange) }}">Open chat</a>
                        @auth
                            @if($isBuyerHere && $exchangeOrder && $exchangeOrder->payment_method === 'escrow' && (! $upfrontPaid || ($remainingRequired && ! $remainingPaid)))
                                <a class="btn btn-primary" href="{{ route('payments.checkout', $exchangeOrder) }}">{{ ! $upfrontPaid ? 'Pay upfront' : 'Pay final amount' }}</a>
                            @elseif(! $isBuyerHere && ! $exchangeOrder)
                                <a class="btn btn-primary" href="{{ route('exchanges.deal-terms', $exchange) }}">Set deal terms</a>
                            @else
                                <a class="btn" href="{{ route('exchanges.deal-terms', $exchange) }}">View deal</a>
                            @endif
                            <a class="btn" href="{{ route('shipments.index') }}">View shipment</a>
                        @else
                            <span class="muted">Login to track shipment</span>
                        @endauth
                    </div>
                </article>
            @empty
                <p class="muted">No active exchanges.</p>
            @endforelse
        </section>

        <section class="dash-grid-2" id="dash-completed" x-show="showSection('more')">
            <section class="card dash-block">
                <div class="dash-head">
                    <p class="dash-kicker">History</p>
                    <h2>Completed Exchanges</h2>
                </div>
                <button type="button" class="btn dash-more-toggle" x-show="isMobile" @click="showInsights = !showInsights" x-text="showInsights ? 'Hide history' : 'Show history'"></button>
                <div x-show="!isMobile || showInsights" x-transition.opacity.duration.200ms>
                @forelse($completedExchanges as $exchange)
                    @php
                        $completedOrder = $exchange->shipment?->order;
                        $completedPaid = $completedOrder && (string) $completedOrder->payment_status === 'paid';
                    @endphp
                    <article class="dash-row">
                        <div>
                            <strong>{{ $exchange->item?->title ?? 'Item removed' }}</strong>
                            <p>Completed {{ optional($exchange->updated_at)->format('d M Y') }} · <span class="shipment-state-pill">{{ $completedOrder ? ($completedPaid ? 'Paid' : ucfirst($completedOrder->payment_status ?? 'pending')) : 'No order' }}</span></p>
                        </div>
                    </article>
                @empty
                    <p class="muted">No completed exchanges yet.</p>
                @endforelse
                </div>
            </section>
            <section class="card dash-block">
                <div class="dash-head">
                    <p class="dash-kicker">Updates</p>
                    <h2>Notifications</h2>
                </div>
                <button type="button" class="btn dash-more-toggle" x-show="isMobile" @click="showNotifications = !showNotifications" x-text="showNotifications ? 'Hide notifications' : 'Show notifications'"></button>
                <div x-show="!isMobile || showNotifications" x-transition.opacity.duration.200ms>
                @forelse($recentNotifications as $note)
                    <article class="dash-note">
                        <strong>{{ $note['text'] }}</strong>
                        <p>{{ $note['time'] }}</p>
                    </article>
                @empty
                    <p class="muted">No recent updates.</p>
                @endforelse
                </div>
            </section>
        </section>

        <section class="card dash-block" id="dash-actions" x-show="showSection('actions')">
            <div class="dash-head">
                <p class="dash-kicker">Tools</p>
                <h2>Quick Actions</h2>
            </div>
            <div class="dash-cta-grid">
                <a class="btn btn-primary" href="{{ route('items.create') }}">Add Item</a>
                <a class="btn dash-cta-emphasis" href="{{ route('items.index') }}">Explore Items</a>
                <a class="btn dash-cta-emphasis" href="{{ route('exchanges.index') }}">View Exchanges</a>
            </div>
            <div class="dash-actions">
                <a class="btn" href="{{ route('chat.index') }}">Open Chat</a>
                @auth
                    <a class="btn" href="{{ route('shipments.index') }}">Track Shipment</a>
                @else
                    <span class="muted">Login to track shipment</span>
                @endauth
                <form method="POST" action="{{ route('demo.generate-exchange-data') }}">
                    @csrf
                    <button class="btn" type="submit">Generate Demo Exchange Data</button>
                </form>
            </div>
        </section>
    </section>
</x-app-layout>


<style>
    .dash-shell { display:grid; gap:14px; }
    .dash-hero {
        display: flex;
        justify-content: space-between;
        gap: 14px;
        align-items: flex-end;
        background:
            radial-gradient(circle at 10% 10%, rgba(191,255,0,.13), transparent 36%),
            radial-gradient(circle at 90% 85%, rgba(120,94,255,.16), transparent 40%),
            linear-gradient(150deg, rgba(255,255,255,.06), rgba(255,255,255,.02));
        border-color: rgba(255,255,255,.18);
    }
    .dash-hero h1 {
        margin: 0;
        font-size: clamp(1.45rem, 3vw, 2.2rem);
        letter-spacing: -.02em;
    }
    .dash-hero-copy {
        margin: 8px 0 0;
        max-width: 56ch;
        color: rgba(255,255,255,.68);
        line-height: 1.45;
    }
    .dash-hero-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .dash-cta-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
    }
    .dash-cta-emphasis {
        border-color: rgba(191,255,0,.35);
        background: linear-gradient(145deg, rgba(191,255,0,.14), rgba(191,255,0,.05));
        box-shadow: 0 8px 20px rgba(191,255,0,.12);
    }
    .dash-more-toggle {
        margin: 2px 0 4px;
        border-style: dashed;
    }
    .dash-kicker {
        margin: 0 0 6px;
        font-family: 'Geist Mono', monospace;
        font-size: 10px;
        letter-spacing: .16em;
        text-transform: uppercase;
        color: rgba(255,255,255,.62);
    }
    .dash-mobile-jump { display: none; }
    .dash-summary { display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:12px; }
    .dash-stat p { margin:0; color:rgba(255,255,255,.6); font-size:12px; text-transform:uppercase; letter-spacing:.08em; }
    .dash-stat h2 { margin:8px 0 0; font-size:2rem; }
    .dash-stat small {
        display: block;
        margin-top: 8px;
        color: rgba(255,255,255,.58);
        font-size: 12px;
        line-height: 1.35;
    }
    .dash-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .dash-block h2 { margin:0; font-size:1.1rem; }
    .dash-table, .dash-block { display:grid; gap:10px; }
    .dash-row { border:1px solid rgba(255,255,255,.1); border-radius:12px; padding:10px; display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap; background:rgba(255,255,255,.02); }
    .dash-row strong { display:block; }
    .dash-row p { margin:4px 0 0; color:rgba(255,255,255,.6); font-size:13px; }
    .dash-status { border:1px solid rgba(191,255,0,.45); color:var(--accent); border-radius:999px; padding:6px 9px; font-size:11px; text-transform:uppercase; letter-spacing:.08em; }
    .dash-actions { display:flex; gap:8px; flex-wrap:wrap; }
    .dash-note { border:1px dashed rgba(191,255,0,.32); border-radius:10px; padding:8px 10px; background:rgba(191,255,0,.06); }
    .dash-note strong { font-size:13px; }
    .dash-note p { margin:4px 0 0; color:rgba(255,255,255,.58); font-size:12px; }
    .dash-confirmation-progress { margin-top:8px; display:flex; gap:6px; flex-wrap:wrap; }
    .dash-confirm-pill { border:1px solid rgba(255,255,255,.14); border-radius:999px; padding:5px 9px; display:flex; gap:6px; align-items:center; font-size:11px; background:rgba(255,255,255,.02); }
    .dash-confirm-pill strong { font-size:10px; letter-spacing:.08em; text-transform:uppercase; color:rgba(255,255,255,.76); }
    .dash-confirm-pill span { color:rgba(255,255,255,.7); }
    .dash-confirm-pill.is-done { border-color: rgba(191,255,0,.5); background: rgba(191,255,0,.1); }
    .dash-confirm-pill.is-done span { color: rgba(191,255,0,.95); }
    .dash-confirm-pill.is-pending { border-color: rgba(255,193,7,.35); background: rgba(255,193,7,.08); }
    @media (max-width: 980px) {
        .dash-hero { flex-direction: column; align-items: flex-start; }
        .dash-summary, .dash-grid-2 { grid-template-columns:1fr; }
    }
    @media (max-width: 760px) {
        .dash-shell {
            gap: 12px;
            padding-bottom: 84px; /* keep content above bottom dock */
        }
        .dash-hero {
            padding: 12px;
            border-radius: 14px;
        }
        .dash-hero h1 {
            font-size: clamp(1.35rem, 7vw, 1.85rem);
            line-height: 1.08;
        }
        .dash-hero-copy {
            margin-top: 8px;
            font-size: 13px;
            max-width: 32ch;
        }
        .dash-hero-actions {
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .dash-hero-actions .btn:first-child {
            grid-column: 1 / -1;
        }
        .dash-mobile-jump {
            position: sticky;
            top: 64px;
            z-index: 25;
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 6px 0 8px;
            -webkit-overflow-scrolling: touch;
            background: linear-gradient(to bottom, rgba(5,5,5,.88), rgba(5,5,5,.44), transparent);
            backdrop-filter: blur(6px);
        }
        .dash-mobile-jump button {
            position: relative;
            white-space: nowrap;
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 999px;
            padding: 8px 11px;
            font-size: 10px;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: rgba(255,255,255,.78);
            background: linear-gradient(145deg, rgba(24,24,24,.95), rgba(10,10,10,.93));
            cursor: pointer;
            overflow: hidden;
            transition: transform .25s ease, border-color .25s ease, color .25s ease, box-shadow .25s ease, background .25s ease;
        }
        .dash-mobile-jump button::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, transparent 20%, rgba(191,255,0,.18), transparent 60%);
            transform: translateX(-140%);
            transition: transform .55s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .dash-mobile-jump button:hover,
        .dash-mobile-jump button.is-active {
            border-color: rgba(191,255,0,.4);
            color: var(--accent);
            box-shadow: 0 8px 22px rgba(0,0,0,.32), inset 0 0 0 1px rgba(191,255,0,.16);
            transform: translateY(-1px);
        }
        .dash-mobile-jump button:hover::before,
        .dash-mobile-jump button.is-active::before {
            transform: translateX(115%);
        }
        .dash-mobile-jump button.is-active {
            background:
                radial-gradient(circle at 20% 10%, rgba(191,255,0,.2), transparent 45%),
                linear-gradient(145deg, rgba(34,48,10,.95), rgba(14,14,14,.94));
            animation: dashTabGlow 2.2s ease-in-out infinite;
        }
        .dash-mobile-jump button:nth-child(2).is-active {
            border-color: rgba(107,191,255,.45);
            color: #9dd9ff;
            background:
                radial-gradient(circle at 20% 10%, rgba(107,191,255,.2), transparent 45%),
                linear-gradient(145deg, rgba(10,30,45,.95), rgba(14,14,14,.94));
        }
        .dash-mobile-jump button:nth-child(4).is-active {
            border-color: rgba(255,176,76,.45);
            color: #ffbe7a;
            background:
                radial-gradient(circle at 20% 10%, rgba(255,176,76,.2), transparent 45%),
                linear-gradient(145deg, rgba(42,26,8,.95), rgba(14,14,14,.94));
        }
        .dash-mobile-jump button:nth-child(5).is-active {
            border-color: rgba(183,152,255,.45);
            color: #cbb4ff;
            background:
                radial-gradient(circle at 20% 10%, rgba(183,152,255,.2), transparent 45%),
                linear-gradient(145deg, rgba(28,18,44,.95), rgba(14,14,14,.94));
        }
        .dash-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            padding-bottom: 0;
        }
        .dash-stat {
            border-radius: 14px;
            padding: 12px;
            border: 1px solid rgba(255,255,255,.14);
            background:
                radial-gradient(circle at 90% 0%, rgba(191,255,0,.08), transparent 52%),
                linear-gradient(155deg, rgba(255,255,255,.04), rgba(255,255,255,.01));
        }
        .dash-summary .dash-stat:last-child { grid-column: 1 / -1; }
        .dash-stat p {
            font-size: 10px;
            letter-spacing: .14em;
            color: rgba(255,255,255,.62);
        }
        .dash-stat h2 {
            margin-top: 6px;
            font-size: 1.7rem;
            line-height: 1;
            color: #fff;
        }
        .dash-stat small { font-size: 11px; }
        .dash-block {
            border-radius: 14px;
            padding: 12px;
            background:
                linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.01)),
                radial-gradient(circle at 100% 0%, rgba(191,255,0,.05), transparent 42%);
            border: 1px solid rgba(255,255,255,.14);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.04);
        }
        .dash-head {
            border-bottom: 1px dashed rgba(255,255,255,.14);
            padding-bottom: 8px;
            margin-bottom: 2px;
        }
        .dash-head h2 {
            font-size: 1rem;
            letter-spacing: .01em;
        }
        .dash-row {
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
            padding: 11px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,.1);
            background: linear-gradient(165deg, rgba(255,255,255,.03), rgba(255,255,255,.01));
        }
        .dash-row strong { font-size: 1rem; }
        .dash-row p { font-size: 13px; line-height: 1.45; }
        .dash-status {
            width: fit-content;
            font-size: 10px;
            padding: 5px 8px;
        }
        .dash-actions {
            display: grid;
            grid-template-columns: 1fr;
            width: 100%;
        }
        .dash-actions .btn,
        .dash-actions form,
        .dash-actions form .btn {
            width: 100%;
        }
        .dash-actions .btn {
            min-height: 42px;
            border-radius: 10px;
        }
        .dash-confirmation-progress {
            display: grid;
            grid-template-columns: 1fr;
        }
        .dash-confirm-pill {
            border-radius: 10px;
            padding: 7px 10px;
        }
        .dash-note {
            border-radius: 10px;
            padding: 10px;
            background: linear-gradient(145deg, rgba(191,255,0,.08), rgba(255,255,255,.01));
            border-color: rgba(191,255,0,.24);
        }
        .dash-grid-2 {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }
        .dash-actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
        }
        .dash-actions > .btn,
        .dash-actions > form,
        .dash-actions > form .btn {
            width: 100%;
        }
        .dash-cta-grid {
            grid-template-columns: 1fr;
        }
        .dash-cta-emphasis {
            animation: dashCtaPulse 2.2s ease-in-out infinite;
        }
    }
    @keyframes dashCtaPulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(191,255,0,0); }
        50% { box-shadow: 0 0 0 4px rgba(191,255,0,.1); }
    }
    @keyframes dashTabGlow {
        0%, 100% { box-shadow: 0 8px 22px rgba(0,0,0,.32), inset 0 0 0 1px rgba(191,255,0,.16); }
        50% { box-shadow: 0 10px 26px rgba(0,0,0,.4), inset 0 0 0 1px rgba(191,255,0,.28), 0 0 0 3px rgba(191,255,0,.08); }
    }
</style>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('dashboardMobileUI', () => ({
        activeTab: 'listings',
        isMobile: false,
        showInsights: false,
        showNotifications: false,
        init() {
            const update = () => {
                this.isMobile = window.matchMedia('(max-width: 760px)').matches;
                if (!this.isMobile) {
                    this.showInsights = true;
                    this.showNotifications = true;
                }
            };
            update();
            window.addEventListener('resize', update);
        },
        showSection(name) {
            if (!this.isMobile) return true;
            return this.activeTab === name;
        }
    }));
});
</script>
