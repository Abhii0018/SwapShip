<x-app-layout>
    @php
        $item = $exchangeRequest->item;
        $sender = $exchangeRequest->sender; // buyer
        $receiver = $exchangeRequest->receiver; // seller
        $hasOrder = (bool) $order;
        $upfrontPaid = $hasOrder && !empty($order->upfront_paid_at);
        $remainingRequired = $hasOrder && (float) ($order->remaining_amount ?? 0) > 0.0001;
        $remainingPaid = $hasOrder && !empty($order->remaining_paid_at);
        $effectivePaymentStatus = !$hasOrder
            ? 'awaiting terms'
            : (! $upfrontPaid
                ? 'pending'
                : ($remainingRequired && ! $remainingPaid ? 'partially paid' : 'paid'));
    @endphp

    <div class="shipment-topbar">
        <a class="shipment-back-btn" href="{{ route('chat.index', $exchangeRequest) }}">
            <span aria-hidden="true">&larr;</span>
            <span>Back to chat</span>
        </a>
    </div>

    <section class="card shipment-shell">
        <header class="shipment-head">
            <div>
                <p class="shipment-kicker">Deal Terms</p>
                <h2>{{ $item?->title ?? 'Listing' }}</h2>
            </div>
            <span class="shipment-status">{{ ucfirst($effectivePaymentStatus) }}</span>
        </header>

        @if(session('success'))
            <p class="alert alert-success">{{ session('success') }}</p>
        @endif
        @if(session('error'))
            <p class="alert alert-error">{{ session('error') }}</p>
        @endif

        <div class="shipment-meta-grid">
            <div class="shipment-meta-pill">
                <span>Buyer</span>
                <strong>{{ $sender?->name ?? 'Buyer' }}</strong>
            </div>
            <div class="shipment-meta-pill">
                <span>Seller</span>
                <strong>{{ $receiver?->name ?? 'Seller' }}</strong>
            </div>
            <div class="shipment-meta-pill">
                <span>Listed price</span>
                <strong>INR {{ number_format((float) ($item?->price ?? 0), 2) }}</strong>
            </div>
            @if($hasOrder)
                <div class="shipment-meta-pill">
                    <span>Order</span>
                    <strong>#{{ $order->id }}</strong>
                </div>
            @endif
        </div>

        @if($isSeller)
            <h3 style="margin-top: 24px;">Set or update deal terms</h3>
            <p class="muted">Choose the payment method and finalise the price the buyer will pay. Buyer is notified in chat when you save.</p>

            <form method="POST" action="{{ route('exchanges.deal-terms.store', $exchangeRequest) }}" class="shipment-form">
                @csrf

                <div class="shipment-form-row">
                    <label for="payment_method"><strong>Payment method</strong></label>
                    <select name="payment_method" id="payment_method" required>
                        <option value="escrow" @selected(old('payment_method', $order?->payment_method ?? 'escrow') === 'escrow')>Escrow (online split)</option>
                        <option value="cod" @selected(old('payment_method', $order?->payment_method ?? 'escrow') === 'cod')>Cash on Delivery</option>
                    </select>
                </div>

                <div class="shipment-form-row">
                    <label for="negotiated_item_amount"><strong>Final item price (INR)</strong></label>
                    <input type="number" min="1" step="0.01" name="negotiated_item_amount" id="negotiated_item_amount"
                        value="{{ old('negotiated_item_amount', $order?->negotiated_item_amount ?? $order?->item_amount ?? $item?->price) }}"
                        placeholder="e.g. {{ (float) ($item?->price ?? 0) }}">
                </div>

                <div class="shipment-form-row">
                    <label for="upfront_amount"><strong>Upfront amount (INR, escrow only)</strong></label>
                    <input type="number" min="1" step="0.01" name="upfront_amount" id="upfront_amount"
                        value="{{ old('upfront_amount', $order?->upfront_amount ?? $estimate['total_amount']) }}"
                        placeholder="Default: full total">
                    <small class="muted">Tip: keep upfront below total to leave a final doorstep payment.</small>
                </div>

                <div class="shipment-breakdown-grid">
                    <div class="shipment-breakdown-pill">
                        <span>Item</span>
                        <strong>INR {{ number_format((float) $estimate['item_amount'], 2) }}</strong>
                    </div>
                    <div class="shipment-breakdown-pill">
                        <span>Shipping est.</span>
                        <strong>INR {{ number_format((float) $estimate['shipping_amount'], 2) }}</strong>
                    </div>
                    <div class="shipment-breakdown-pill">
                        <span>Platform fee</span>
                        <strong>INR {{ number_format((float) $estimate['platform_fee'], 2) }}</strong>
                    </div>
                    <div class="shipment-breakdown-pill is-wide">
                        <span>Estimated total</span>
                        <strong>INR {{ number_format((float) $estimate['total_amount'], 2) }}</strong>
                    </div>
                </div>

                <div class="shipment-form-actions">
                    <button class="btn btn-primary" type="submit">{{ $hasOrder ? 'Update deal terms' : 'Save deal terms' }}</button>
                    <a class="btn" href="{{ route('chat.index', $exchangeRequest) }}">Cancel</a>
                </div>
            </form>
        @endif

        @if($hasOrder)
            <h3 style="margin-top: 24px;">Buyer summary</h3>
            <div class="shipment-breakdown-grid">
                <div class="shipment-breakdown-pill">
                    <span>Method</span>
                    <strong>{{ strtoupper($order->payment_method) }}</strong>
                </div>
                <div class="shipment-breakdown-pill">
                    <span>Item</span>
                    <strong>INR {{ number_format((float) $order->item_amount, 2) }}</strong>
                </div>
                <div class="shipment-breakdown-pill">
                    <span>Shipping</span>
                    <strong>INR {{ number_format((float) $order->shipping_amount, 2) }}</strong>
                </div>
                <div class="shipment-breakdown-pill">
                    <span>Platform fee</span>
                    <strong>INR {{ number_format((float) $order->platform_fee, 2) }}</strong>
                </div>
                <div class="shipment-breakdown-pill is-wide">
                    <span>Total payable</span>
                    <strong>INR {{ number_format((float) $order->total_amount, 2) }}</strong>
                </div>
                @if($order->payment_method === 'escrow')
                    <div class="shipment-breakdown-pill">
                        <span>Upfront</span>
                        <strong>INR {{ number_format((float) ($order->upfront_amount ?? 0), 2) }}</strong>
                    </div>
                    <div class="shipment-breakdown-pill">
                        <span>Doorstep due</span>
                        <strong>INR {{ number_format((float) ($order->remaining_amount ?? 0), 2) }}</strong>
                    </div>
                @endif
            </div>

            <div class="shipment-state-row" style="margin-top: 16px;">
                <span class="shipment-state-pill">Payment: {{ $effectivePaymentStatus }}</span>
                @if($order->payment_method === 'escrow')
                    <span class="shipment-state-pill">Upfront: {{ $upfrontPaid ? 'paid' : 'pending' }}</span>
                    <span class="shipment-state-pill">
                        Remaining: {{ ! $remainingRequired ? 'not required' : ($remainingPaid ? 'paid' : 'pending') }}
                    </span>
                @endif
            </div>

            @if(! $isSeller)
                <div class="shipment-form-actions" style="margin-top: 16px;">
                    @if($order->payment_method === 'escrow' && (! $upfrontPaid || ($remainingRequired && ! $remainingPaid)))
                        <a class="btn btn-primary" href="{{ route('payments.checkout', $order) }}">
                            {{ ! $upfrontPaid ? 'Pay upfront now' : 'Pay final doorstep amount' }}
                        </a>
                    @elseif($order->payment_method === 'escrow')
                        <span class="shipment-state-pill">All payments completed</span>
                    @else
                        <span class="shipment-state-pill">Pay on delivery</span>
                    @endif
                    <a class="btn" href="{{ route('shipments.index') }}">Open shipment</a>
                </div>
            @endif
        @else
            @if(! $isSeller)
                <p class="muted" style="margin-top: 16px;">Seller has not set deal terms yet. You will be notified in chat once they do.</p>
            @endif
        @endif
    </section>
</x-app-layout>
