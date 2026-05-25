<x-app-layout>
    <div class="my-dashboard-shell" x-data="dashboardPage('{{ request()->query('tab', 'items') }}')">
        <!-- Header -->
        <section class="card my-dashboard-header">
            <div class="my-dashboard-head-top">
                <h1 class="my-dashboard-title">My Account</h1>
            </div>
        </section>

        <div class="my-dashboard-mobile-tabs">
            <div class="my-dashboard-tab-buttons" role="tablist" aria-label="My dashboard sections">
                <button
                    @click="setTab('items')"
                    :class="{ 'is-active': activeTab === 'items' }"
                    class="my-dashboard-tab-btn"
                    type="button"
                >
                    My Publish
                </button>
                <button
                    @click="setTab('purchases')"
                    :class="{ 'is-active': activeTab === 'purchases' }"
                    class="my-dashboard-tab-btn"
                    type="button"
                >
                    My Purchase
                </button>
            </div>

            <div class="my-dashboard-mobile-menu">
                <button class="my-dashboard-mobile-trigger" type="button" @click="mobileMenuOpen = !mobileMenuOpen" :aria-expanded="mobileMenuOpen ? 'true' : 'false'" aria-controls="my-dashboard-mobile-panel">
                    <span class="my-dashboard-mobile-trigger-icon" aria-hidden="true">
                        <i></i><i></i><i></i>
                    </span>
                    <span x-text="currentTabLabel()"></span>
                </button>
                <div id="my-dashboard-mobile-panel" class="my-dashboard-mobile-panel" x-cloak x-show="mobileMenuOpen" x-transition.origin.top.left @click.outside="mobileMenuOpen = false">
                    <button type="button" @click="setTab('items')" :class="{ 'is-active': activeTab === 'items' }">My Publish</button>
                    <button type="button" @click="setTab('purchases')" :class="{ 'is-active': activeTab === 'purchases' }">My Purchase</button>
                </div>
            </div>

            <!-- MY ITEMS TAB -->
            <div x-cloak x-show="activeTab === 'items'" class="my-dashboard-tab-content">
                @if($myItems->count() > 0)
                    <section class="explore-listing">
                        @foreach($myItems as $item)
                            @php($isSold = (bool) $item->sold_at)
                            <article class="card explore-item-card my-dashboard-card {{ $isSold ? 'explore-item-card--sold' : '' }}">
                                <a href="{{ route('items.show', $item) }}" class="explore-image-wrap" aria-label="View details of {{ $item->title }}">
                                    <img src="{{ $item->images->first()?->url ?? 'https://via.placeholder.com/600x450?text=SwapShip+Item' }}" alt="{{ $item->title }}" class="explore-item-image">
                                    <div class="explore-image-glow" aria-hidden="true"></div>
                                    @if($isSold)
                                        <span class="explore-sold-ribbon" aria-label="Sold">SOLD</span>
                                        <span class="explore-sold-overlay" aria-hidden="true"></span>
                                    @endif
                                </a>
                                <div class="explore-item-content">
                                    <div class="explore-item-top">
                                        <h3>{{ $item->title }}</h3>
                                        @if($isSold)
                                            <span class="explore-type-pill explore-type-pill--sold">Sold</span>
                                        @else
                                            <span class="explore-type-pill">{{ ucfirst($item->type) }}</span>
                                        @endif
                                    </div>
                                    <p class="muted">{{ ucfirst($item->condition) }} · {{ $item->location }}</p>
                                    <div class="explore-meta-line">
                                        <span class="explore-meta-chip">{{ $item->category ?: 'General' }}</span>
                                        <span class="explore-meta-chip">Posted {{ optional($item->created_at)->diffForHumans() }}</span>
                                        @if($isSold)
                                            <span class="explore-meta-chip explore-meta-chip--sold">Sold {{ optional($item->sold_at)->diffForHumans() }}</span>
                                        @endif
                                    </div>

                                    @if($item->price)
                                        <p class="explore-price">Price: ₹{{ number_format((float) $item->price, 2) }}</p>
                                    @endif

                                    <p class="explore-owner">{{ $item->exchange_preference ?? 'Open to exchanges' }}</p>

                                    <div class="explore-item-actions my-dashboard-actions-group">
                                        <a class="btn" href="{{ route('items.show', $item) }}">View</a>
                                        <a class="btn" href="{{ route('items.edit', $item) }}">Edit</a>
                                        <button class="btn btn-danger" @click="openDeleteModal({{ $item->id }}, '{{ addslashes($item->title) }}')" type="button">Delete</button>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </section>

                    @if($myItems->hasPages())
                        <section class="explore-pagination">
                            {{ $myItems->links() }}
                        </section>
                    @endif
                @else
                    <div class="card my-dashboard-empty">
                        <div class="my-dashboard-empty-content">
                            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="9" y1="9" x2="15" y2="9"></line>
                                <line x1="9" y1="15" x2="15" y2="15"></line>
                            </svg>
                            <h3>No items published yet</h3>
                            <p class="muted">Start your SwapShip journey by adding your first item.</p>
                            <a href="{{ route('items.create') }}" class="btn btn-primary">+ Add Your First Item</a>
                        </div>
                    </div>
                @endif
            </div>

            <!-- MY PURCHASES TAB -->
            <div x-cloak x-show="activeTab === 'purchases'" class="my-dashboard-tab-content">
                @if($myPurchases->count() > 0)
                    <section class="purchases-listing">
                        @foreach($myPurchases as $order)
                            @php
                                $item = $order->shipment?->exchangeRequest?->item;
                                $orderStatus = $order->payment_status === 'completed' ? 'completed' : ($order->payment_status ?? 'pending');
                            @endphp
                            <article class="card my-dashboard-purchase-card">
                                <div class="purchase-header">
                                    <div class="purchase-info">
                                        <h3>{{ $item?->title ?? 'Order #' . $order->id }}</h3>
                                        <p class="muted">Order ID: {{ $order->id }}</p>
                                    </div>
                                    <span class="purchase-status-badge" :style="{ background: '{{ $orderStatus === 'completed' ? '#BFFF00' : '#666' }}', color: '{{ $orderStatus === 'completed' ? '#000' : '#fff' }}' }">
                                        {{ ucfirst($orderStatus) }}
                                    </span>
                                </div>

                                <div class="purchase-details">
                                    @if($item)
                                        <div class="purchase-item">
                                            <img src="{{ $item->images->first()?->url ?? 'https://via.placeholder.com/150x150?text=Item' }}" alt="{{ $item->title }}" class="purchase-item-image">
                                            <div class="purchase-item-info">
                                                <p><strong>{{ ucfirst($item->condition) }}</strong></p>
                                                <p class="muted">{{ $item->location }}</p>
                                                <p class="muted">Category: {{ $item->category }}</p>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="purchase-amount">
                                        <p class="muted">Amount Paid</p>
                                        <p class="purchase-price">₹{{ number_format((float) $order->total_amount, 2) }}</p>
                                    </div>

                                    <p class="muted purchase-date">{{ optional($order->created_at)->format('d M Y, g:i A') }}</p>
                                </div>

                                <div class="purchase-actions">
                                    @if($item)
                                        <a class="btn" href="{{ route('items.show', $item) }}">View Item</a>
                                    @endif
                                    @if($order->shipment && in_array($orderStatus, ['completed']))
                                        <a class="btn" href="{{ route('shipments.index') }}">Track Shipment</a>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </section>

                    @if($myPurchases->hasPages())
                        <section class="explore-pagination">
                            {{ $myPurchases->links() }}
                        </section>
                    @endif
                @else
                    <div class="card my-dashboard-empty">
                        <div class="my-dashboard-empty-content">
                            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="9" cy="21" r="1"></circle>
                                <circle cx="20" cy="21" r="1"></circle>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                            </svg>
                            <h3>No purchases yet</h3>
                            <p class="muted">Explore items and make your first purchase on SwapShip.</p>
                            <a href="{{ route('items.index') }}" class="btn btn-primary">Explore Items</a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="my-dashboard-modal-overlay" :class="{ 'is-open': showModal }" @click.self="closeModal()">
        <div class="my-dashboard-modal" @click.stop>
            <header class="my-dashboard-modal-header">
                <h2>Delete Item?</h2>
                <button type="button" class="my-dashboard-modal-close" @click="closeModal()" aria-label="Close">&times;</button>
            </header>

            <div class="my-dashboard-modal-body">
                <p>Are you sure you want to delete <strong x-text="itemTitle"></strong>?</p>
                <p class="muted">This action cannot be undone. Please enter your password to confirm.</p>
            </div>

            <form method="POST" class="my-dashboard-modal-form" @submit="submitDelete">
                @csrf
                @method('DELETE')

                <div class="my-dashboard-form-group">
                    <label for="delete_password" class="my-dashboard-label">Confirm Password</label>
                    <input
                        id="delete_password"
                        type="password"
                        name="password"
                        class="my-dashboard-input"
                        placeholder="Enter your password"
                        x-model="password"
                        required
                        autocomplete="current-password"
                    >
                    <template x-if="errorMessage">
                        <p class="my-dashboard-error" x-text="errorMessage"></p>
                    </template>
                </div>

                <div class="my-dashboard-modal-actions">
                    <button type="button" class="btn" @click="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger" :disabled="isLoading">
                        <span x-show="!isLoading">Confirm Delete</span>
                        <span x-show="isLoading">Deleting...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function dashboardPage(initialTab) {
            return {
                activeTab: (initialTab === 'purchases' ? 'purchases' : 'items'),
                mobileMenuOpen: false,
                showModal: false,
                itemId: null,
                itemTitle: '',
                password: '',
                errorMessage: '',
                isLoading: false,

                setTab(tab) {
                    this.activeTab = tab === 'purchases' ? 'purchases' : 'items';
                    this.mobileMenuOpen = false;
                },

                currentTabLabel() {
                    return this.activeTab === 'purchases' ? 'My Purchase' : 'My Publish';
                },

                openDeleteModal(id, title) {
                    this.itemId = id;
                    this.itemTitle = title;
                    this.password = '';
                    this.errorMessage = '';
                    this.showModal = true;
                },

                closeModal() {
                    this.showModal = false;
                    this.password = '';
                    this.errorMessage = '';
                },

                async submitDelete(e) {
                    e.preventDefault();
                    if (!this.password.trim()) {
                        this.errorMessage = 'Password is required';
                        return;
                    }

                    this.isLoading = true;

                    try {
                        const response = await fetch(`/items/${this.itemId}`, {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({ password: this.password }),
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            this.errorMessage = data.message || 'Failed to delete item';
                            this.isLoading = false;
                            return;
                        }

                        window.location.reload();
                    } catch (error) {
                        this.errorMessage = 'An error occurred. Please try again.';
                        this.isLoading = false;
                    }
                },
            };
        }
    </script>

    <style>
        .my-dashboard-shell { display: grid; gap: 12px; padding: 12px; }
        [x-cloak] { display: none !important; }
        
        .my-dashboard-header {
            padding: 20px !important;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
            border: 1px solid var(--line);
        }

        .my-dashboard-title {
            font-size: 28px;
            font-weight: 800;
            margin: 0;
            color: var(--text);
        }

        /* Mobile Tab Navigation */
        .my-dashboard-mobile-tabs {
            display: grid;
            gap: 12px;
        }

        .my-dashboard-tab-buttons {
            display: flex;
            gap: 8px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 4px;
        }

        .my-dashboard-tab-btn {
            flex: 1;
            padding: 12px 16px;
            border: none;
            background: transparent;
            color: var(--muted);
            cursor: pointer;
            font-family: Inter, sans-serif;
            font-size: 14px;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .my-dashboard-tab-btn.is-active {
            background: var(--accent);
            color: #000;
            font-weight: 600;
        }

        .my-dashboard-mobile-menu {
            display: none;
            position: relative;
        }

        .my-dashboard-mobile-trigger {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            background: rgba(255,255,255,.04);
            border: 1px solid var(--line);
            border-radius: 10px;
            color: var(--text);
            font-family: Inter, sans-serif;
            font-size: 14px;
            font-weight: 600;
        }

        .my-dashboard-mobile-trigger-icon {
            display: grid;
            gap: 4px;
        }

        .my-dashboard-mobile-trigger-icon i {
            display: block;
            width: 18px;
            height: 2px;
            border-radius: 2px;
            background: var(--accent);
        }

        .my-dashboard-mobile-panel {
            position: absolute;
            z-index: 15;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            display: grid;
            gap: 6px;
            padding: 8px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: linear-gradient(180deg, rgba(17,17,17,.98), rgba(8,8,8,.98));
            box-shadow: 0 14px 28px rgba(0,0,0,.38);
        }

        .my-dashboard-mobile-panel button {
            text-align: left;
            padding: 10px 12px;
            border: 1px solid transparent;
            border-radius: 8px;
            background: rgba(255,255,255,.03);
            color: var(--text);
            font-family: Inter, sans-serif;
            font-size: 13px;
        }

        .my-dashboard-mobile-panel button.is-active {
            border-color: rgba(191,255,0,.42);
            color: var(--accent);
        }

        .my-dashboard-tab-content {
            animation: fadeIn 0.24s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Cards */
        .my-dashboard-card {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            animation: cardRise .34s cubic-bezier(0.16, 1, 0.3, 1) both;
            transition: transform .26s ease, border-color .26s ease;
        }

        .my-dashboard-card:hover,
        .my-dashboard-purchase-card:hover {
            transform: translateY(-2px);
            border-color: rgba(191,255,0,.32);
        }

        @keyframes cardRise {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .my-dashboard-actions-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .my-dashboard-actions-group .btn {
            flex: 1;
            min-width: 80px;
            font-size: 13px;
            padding: 10px 12px;
        }

        /* Purchase Card */
        .my-dashboard-purchase-card {
            display: grid;
            gap: 12px;
            padding: 16px !important;
            animation: cardRise .34s cubic-bezier(0.16, 1, 0.3, 1) both;
            transition: transform .26s ease, border-color .26s ease;
        }

        .purchase-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }

        .purchase-header h3 {
            margin: 0;
            font-size: 16px;
        }

        .purchase-status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .purchase-details {
            display: grid;
            gap: 12px;
        }

        .purchase-item {
            display: flex;
            gap: 12px;
        }

        .purchase-item-image {
            width: 60px;
            height: 60px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid var(--line);
        }

        .purchase-item-info {
            flex: 1;
        }

        .purchase-item-info p {
            margin: 4px 0;
            font-size: 13px;
        }

        .purchase-amount {
            text-align: center;
            padding: 12px;
            background: rgba(191, 255, 0, 0.05);
            border: 1px solid var(--line);
            border-radius: 6px;
        }

        .purchase-price {
            font-size: 20px;
            font-weight: 600;
            color: var(--accent) !important;
            margin: 4px 0 0 0;
        }

        .purchase-date {
            font-size: 12px;
        }

        .purchase-actions {
            display: flex;
            gap: 8px;
        }

        .purchase-actions .btn {
            flex: 1;
            font-size: 13px;
            padding: 10px 12px;
        }

        /* Empty State */
        .my-dashboard-empty {
            padding: 40px 20px !important;
            text-align: center;
            border: 2px dashed var(--line);
        }

        .my-dashboard-empty-content {
            display: grid;
            gap: 12px;
            align-items: center;
            justify-items: center;
        }

        .my-dashboard-empty-content svg {
            color: var(--muted);
        }

        .my-dashboard-empty-content h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .my-dashboard-empty-content p {
            margin: 0;
            font-size: 14px;
        }

        .my-dashboard-empty-content .btn {
            margin-top: 8px;
        }

        /* Modal */
        .my-dashboard-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            z-index: 50;
            padding: 12px;
        }

        .my-dashboard-modal-overlay.is-open {
            opacity: 1;
            pointer-events: auto;
        }

        .my-dashboard-modal {
            background: var(--bg);
            border: 1px solid var(--line);
            border-radius: 8px;
            max-width: 400px;
            width: 100%;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .my-dashboard-modal-header {
            padding: 16px;
            border-bottom: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .my-dashboard-modal-header h2 {
            margin: 0;
            font-size: 18px;
        }

        .my-dashboard-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--muted);
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .my-dashboard-modal-body {
            padding: 16px;
        }

        .my-dashboard-modal-body p {
            margin: 8px 0;
        }

        .my-dashboard-modal-form {
            padding: 16px;
            border-top: 1px solid var(--line);
        }

        .my-dashboard-form-group {
            margin-bottom: 16px;
        }

        .my-dashboard-label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 500;
        }

        .my-dashboard-input {
            width: 100%;
            padding: 10px 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--line);
            border-radius: 6px;
            color: var(--text);
            font-family: Inter, sans-serif;
            font-size: 14px;
        }

        .my-dashboard-input::placeholder {
            color: var(--muted);
        }

        .my-dashboard-input:focus {
            outline: none;
            border-color: var(--accent);
            background: rgba(191, 255, 0, 0.05);
        }

        .my-dashboard-error {
            color: #ff4444;
            font-size: 12px;
            margin-top: 6px;
        }

        .my-dashboard-modal-actions {
            display: flex;
            gap: 8px;
        }

        .my-dashboard-modal-actions .btn {
            flex: 1;
            font-size: 13px;
            padding: 10px 12px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .my-dashboard-shell {
                padding: 8px;
            }

            .my-dashboard-title {
                font-size: 24px;
            }

            .my-dashboard-tab-buttons {
                display: none;
            }

            .my-dashboard-mobile-menu {
                display: block;
            }

            .my-dashboard-actions-group {
                gap: 6px;
            }

            .my-dashboard-actions-group .btn {
                font-size: 12px;
                padding: 8px 10px;
            }

            .purchase-header {
                flex-direction: column;
            }

            .purchase-item-image {
                width: 50px;
                height: 50px;
            }
        }

        @media (max-width: 480px) {
            .my-dashboard-tab-btn {
                font-size: 12px;
                padding: 10px 12px;
            }

            .explore-item-content {
                padding: 12px;
            }

            .my-dashboard-modal {
                max-width: 90vw;
            }
        }
    </style>
</x-app-layout>
