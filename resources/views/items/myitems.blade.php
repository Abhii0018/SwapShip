<x-app-layout>
    <div class="myitems-shell">
        <section class="card myitems-header">
            <div class="myitems-head-top">
                <div>
                    <p class="myitems-eyebrow">My Items</p>
                    <h1 class="myitems-title">Your Published Items</h1>
                </div>
                <div class="myitems-count">{{ $items->total() }} items</div>
            </div>
            <div class="myitems-actions">
                <a href="{{ route('items.create') }}" class="btn btn-primary">+ Add New Item</a>
                <a href="{{ route('items.index') }}" class="btn">Browse Items</a>
            </div>
        </section>

        @if($items->count() > 0)
            <section class="explore-listing">
                @foreach($items as $item)
                    @php($isSold = (bool) $item->sold_at)
                    <article class="card explore-item-card myitems-card {{ $isSold ? 'explore-item-card--sold' : '' }}">
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

                            <div class="explore-item-actions myitems-actions-group">
                                <a class="btn" href="{{ route('items.show', $item) }}">View Details</a>
                                <a class="btn" href="{{ route('items.edit', $item) }}">Edit</a>
                                <button class="btn btn-danger" @click="openDeleteModal({{ $item->id }}, '{{ addslashes($item->title) }}')" type="button">Delete</button>
                            </div>
                        </div>
                    </article>
                @endforeach
            </section>

            <section class="explore-pagination">
                {{ $items->links() }}
            </section>
        @else
            <div class="card myitems-empty">
                <div class="myitems-empty-content">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M8 12h8M12 8v8"></path></svg>
                    <h3>No items published yet</h3>
                    <p class="muted">Start by adding your first item to SwapShip.</p>
                    <a href="{{ route('items.create') }}" class="btn btn-primary">Add Your First Item</a>
                </div>
            </div>
        @endif
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-data="deleteItemModal()" class="myitems-modal-overlay" :class="{ 'is-open': showModal }" @click.self="closeModal()">
        <div class="myitems-modal" @click.stop>
            <header class="myitems-modal-header">
                <h2>Delete Item?</h2>
                <button type="button" class="myitems-modal-close" @click="closeModal()" aria-label="Close">&times;</button>
            </header>

            <div class="myitems-modal-body">
                <p>Are you sure you want to delete <strong x-text="itemTitle"></strong>?</p>
                <p class="muted">This action cannot be undone. Please enter your password to confirm.</p>
            </div>

            <form method="POST" class="myitems-modal-form" @submit="submitDelete">
                @csrf
                @method('DELETE')

                <div class="myitems-form-group">
                    <label for="delete_password" class="myitems-label">Confirm Password</label>
                    <input
                        id="delete_password"
                        type="password"
                        name="password"
                        class="myitems-input"
                        placeholder="Enter your password"
                        x-model="password"
                        required
                        autocomplete="current-password"
                    >
                    <template x-if="errorMessage">
                        <p class="myitems-error" x-text="errorMessage"></p>
                    </template>
                </div>

                <div class="myitems-modal-actions">
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
        function deleteItemModal() {
            return {
                showModal: false,
                itemId: null,
                itemTitle: '',
                password: '',
                errorMessage: '',
                isLoading: false,

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

                submitDelete(e) {
                    e.preventDefault();
                    if (!this.password.trim()) {
                        this.errorMessage = 'Password is required';
                        return;
                    }

                    this.isLoading = true;

                    const formData = new FormData();
                    formData.append('password', this.password);
                    formData.append('_method', 'DELETE');
                    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

                    fetch(`/items/${this.itemId}`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: formData
                    })
                    .then(response => {
                        if (response.ok || response.status === 302) {
                            window.location.href = '{{ route('items.my') }}';
                        } else {
                            return response.text().then(text => {
                                this.isLoading = false;
                                this.errorMessage = 'Invalid password. Please try again.';
                            });
                        }
                    })
                    .catch(error => {
                        this.isLoading = false;
                        this.errorMessage = 'An error occurred. Please try again.';
                    });
                }
            }
        }
    </script>

    <style>
        .myitems-shell {
            display: grid;
            gap: 12px;
        }

        .myitems-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            flex-wrap: wrap;
        }

        .myitems-head-top {
            flex: 1;
            min-width: 200px;
        }

        .myitems-eyebrow {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(191, 255, 0, 0.7);
            margin-bottom: 4px;
            font-weight: 600;
        }

        .myitems-title {
            font-size: 28px;
            font-weight: 700;
            color: white;
            margin: 0;
        }

        .myitems-count {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .myitems-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .myitems-empty {
            display: grid;
            place-items: center;
            min-height: 400px;
            text-align: center;
            padding: 40px 20px;
            background: linear-gradient(160deg, rgba(255,255,255,.03), rgba(255,255,255,.01));
            border: 1px solid rgba(255,255,255,.14);
        }

        .myitems-empty-content {
            display: grid;
            gap: 16px;
            align-items: center;
        }

        .myitems-empty-content svg {
            margin: 0 auto;
            opacity: 0.5;
        }

        .myitems-empty-content h3 {
            margin: 0;
            font-size: 20px;
        }

        .myitems-card {
            position: relative;
        }

        .myitems-actions-group {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 8px;
        }

        .btn-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(239, 68, 68, 0.1));
            border: 1px solid rgba(239, 68, 68, 0.5);
            color: #ef4444;
            transition: all .28s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .btn-danger:hover:not(:disabled) {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.3), rgba(239, 68, 68, 0.2));
            border-color: rgba(239, 68, 68, 0.8);
        }

        /* Modal Styles */
        .myitems-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            display: grid;
            place-items: center;
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            transition: all .28s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 1000;
        }

        .myitems-modal-overlay.is-open {
            opacity: 1;
            visibility: visible;
        }

        .myitems-modal {
            background: rgba(10, 10, 10, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(20px);
            max-width: 400px;
            width: 100%;
            animation: modalSlideIn .28s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .myitems-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.14);
        }

        .myitems-modal-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .myitems-modal-close {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 28px;
            height: 28px;
            display: grid;
            place-items: center;
            border-radius: 6px;
            transition: all .2s ease;
        }

        .myitems-modal-close:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .myitems-modal-body {
            padding: 20px;
        }

        .myitems-modal-body p {
            margin: 0 0 12px 0;
            font-size: 14px;
            line-height: 1.5;
        }

        .myitems-modal-form {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.14);
        }

        .myitems-form-group {
            display: grid;
            gap: 8px;
            margin-bottom: 20px;
        }

        .myitems-label {
            font-size: 13px;
            font-weight: 600;
            color: white;
        }

        .myitems-input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 8px;
            color: white;
            padding: 10px 12px;
            font-size: 14px;
            transition: all .2s ease;
            font-family: inherit;
        }

        .myitems-input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(191, 255, 0, 0.5);
            box-shadow: 0 0 0 3px rgba(191, 255, 0, 0.1);
        }

        .myitems-error {
            color: #ef4444;
            font-size: 12px;
            margin: 4px 0 0 0;
        }

        .myitems-modal-actions {
            display: flex;
            gap: 10px;
        }

        .myitems-modal-actions .btn {
            flex: 1;
        }

        @media (max-width: 768px) {
            .myitems-header {
                flex-direction: column;
            }

            .myitems-title {
                font-size: 22px;
            }

            .myitems-actions {
                width: 100%;
            }

            .myitems-actions .btn {
                flex: 1;
            }

            .myitems-actions-group {
                grid-template-columns: 1fr;
            }

            .myitems-modal {
                max-width: 90vw;
            }
        }
    </style>
</x-app-layout>
