<x-app-layout>
    <section class="checkout-wrap">
        <article class="checkout-card">
            <header class="checkout-head">
                <div>
                    <p class="checkout-kicker">Secure Checkout</p>
                    <h2>Payment for Order #{{ $order->id }}</h2>
                    <p class="muted">Gateway: {{ strtoupper($order->gateway ?? config('payments.default_gateway')) }} · Stage: {{ $stageLabel }}</p>
                </div>
                @if(($order->gateway ?? config('payments.default_gateway')) === 'razorpay')
                    <div class="checkout-gateway-chip" aria-label="Razorpay payment gateway">
                        <img src="https://razorpay.com/assets/razorpay-logo.svg" alt="Razorpay logo">
                    </div>
                @endif

                {{-- Payment status badges (clean, prominent) --}}
                <div class="checkout-status-badges">
                    @php
                        $upfrontPaid = !empty($order->upfront_paid_at);
                        $remainingPaid = !empty($order->remaining_paid_at) || !empty($order->paid_at);
                    @endphp
                    <span class="status-badge {{ $upfrontPaid ? 'is-done' : 'is-pending' }}" aria-hidden="true">
                        @if($upfrontPaid)
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 6L9 17l-5-5" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Upfront: Paid
                        @else
                            Upfront: Pending
                        @endif
                    </span>

                    <span class="status-badge {{ $remainingPaid ? 'is-done' : 'is-pending' }}" aria-hidden="true">
                        @if($remainingPaid)
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 6L9 17l-5-5" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Remaining: {{ $remainingPaid ? 'Paid' : 'Pending' }}
                        @else
                            Remaining: Pending
                        @endif
                    </span>
                </div>
            </header>

            <section class="checkout-amounts">
                <div class="checkout-row">
                    <span>Item price (seller set)</span>
                    <strong>INR {{ number_format((float)$order->item_amount, 2) }}</strong>
                </div>
                <div class="checkout-row">
                    <span>Shipping charge</span>
                    <strong>INR {{ number_format((float)$order->shipping_amount, 2) }}</strong>
                </div>
                <div class="checkout-row">
                    <span>Platform fee</span>
                    <strong>INR {{ number_format((float)$order->platform_fee, 2) }}</strong>
                </div>
                <div class="checkout-row is-total">
                    <span>Total deal amount</span>
                    <strong>INR {{ number_format((float)$order->total_amount, 2) }}</strong>
                </div>
                @if($order->payment_method === 'escrow')
                    <div class="checkout-row {{ !empty($order->upfront_paid_at) ? 'is-paid' : '' }}">
                        <span>Upfront amount</span>
                        <strong>INR {{ number_format((float)($order->upfront_amount ?? $order->total_amount), 2) }}</strong>
                    </div>
                    <div class="checkout-row {{ (!empty($order->remaining_paid_at) || !empty($order->paid_at)) ? 'is-paid' : '' }}">
                        <span>Remaining doorstep amount</span>
                        <strong>INR {{ number_format((float)($order->remaining_amount ?? 0), 2) }}</strong>
                    </div>
                    <div class="checkout-row is-total">
                        <span>Pay now</span>
                        <strong>INR {{ number_format((float)$amountToPay, 2) }}</strong>
                    </div>
                @endif
            </section>

            <div class="checkout-note">
                <span>Escrow protected split payment.</span>
                <span>For doorstep stage, rider handover is unlocked only after successful final payment.</span>
            </div>

                @if($order->payment_method === 'escrow')
                @if(($order->gateway ?? config('payments.default_gateway')) === 'razorpay')
                    @if($stage === 'none')
                        <p class="muted checkout-info">No pending payment for this order. You can return to shipments.</p>
                    @elseif(!empty($razorpayKey))
                        <form id="razorpay-pay-form" method="POST" action="{{ route('payments.pay', $order) }}" class="checkout-form">
                            @csrf
                            <input type="hidden" name="razorpay_payment_id" id="rzp_payment_id">
                            <input type="hidden" name="razorpay_order_id" id="rzp_order_id" value="{{ $gatewayOrderId }}">
                            <input type="hidden" name="razorpay_signature" id="rzp_signature">
                            <input type="hidden" name="razorpay_direct_mode" id="rzp_direct_mode" value="0">
                                <button class="btn btn-primary checkout-pay-btn" type="button" id="rzp-pay-btn">Pay {{ $stageLabel }} with Razorpay</button>
                            </form>
                    @else
                        @if(!empty($gatewayInitFailed))
                            <p class="muted checkout-info">Unable to initialize Razorpay order right now. Please retry in a moment, or verify gateway API keys/permissions.</p>
                            <form method="GET" action="{{ route('payments.checkout', $order) }}" class="checkout-form">
                                <button class="btn btn-primary checkout-pay-btn checkout-retry-btn" type="submit">Retry Pay {{ $stageLabel }}</button>
                            </form>
                        @else
                            <p class="muted checkout-info">Razorpay is not configured yet. Add `RAZORPAY_KEY_ID` and `RAZORPAY_KEY_SECRET` in `.env`.</p>
                        @endif
                    @endif
                @else
                    <form method="POST" action="{{ route('payments.pay', $order) }}" class="checkout-form">
                        @csrf
                        <button class="btn btn-primary checkout-pay-btn" type="submit">Pay Now</button>
                    </form>
                @endif
            @else
                <p class="muted checkout-info">COD selected. Buyer will pay rider at delivery, then OTP verification closes order.</p>
            @endif
        </article>
    </section>
</x-app-layout>

<style>
    .checkout-wrap {
        max-width: 860px;
        margin: 0 auto;
    }
    .checkout-card {
        border: 1px solid rgba(255,255,255,.16);
        border-radius: 16px;
        padding: 18px;
        background:
            radial-gradient(circle at 12% 14%, rgba(124,58,237,.16), transparent 36%),
            radial-gradient(circle at 88% 10%, rgba(191,255,0,.1), transparent 34%),
            linear-gradient(165deg, rgba(255,255,255,.04), rgba(255,255,255,.01));
        box-shadow: 0 18px 40px rgba(0,0,0,.3);
    }
    .checkout-head {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: flex-start;
    }
    .checkout-kicker {
        margin: 0;
        font-size: 11px;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: rgba(255,255,255,.62);
    }
    .checkout-head h2 {
        margin: 5px 0;
        font-size: clamp(1.24rem, 2.2vw, 1.65rem);
    }
    .checkout-gateway-chip {
        border: 1px solid rgba(255,255,255,.18);
        border-radius: 12px;
        padding: 8px 10px;
        background: rgba(255,255,255,.96);
    }
    .checkout-gateway-chip img {
        display: block;
        height: 20px;
        width: auto;
    }
    .checkout-amounts {
        margin-top: 14px;
        border: 1px solid rgba(255,255,255,.14);
        border-radius: 12px;
        background: rgba(12,14,19,.55);
        overflow: hidden;
    }
    .checkout-row {
        padding: 11px 12px;
        display: flex;
        justify-content: space-between;
        gap: 8px;
        border-bottom: 1px solid rgba(255,255,255,.08);
    }
    .checkout-row:last-child {
        border-bottom: 0;
    }
    .checkout-row span {
        color: rgba(255,255,255,.74);
    }
    .checkout-row strong {
        font-weight: 700;
    }
    .checkout-row.is-total {
        background: rgba(191,255,0,.07);
    }
    .checkout-row.is-total strong {
        color: var(--accent);
        font-size: 1.03rem;
    }
    .checkout-row.is-paid {
        background: linear-gradient(90deg, rgba(191,255,0,.06), rgba(124,58,237,.02));
        border-left: 3px solid var(--accent);
    }
    .checkout-row.is-paid strong {
        color: var(--accent);
    }
    .checkout-status-badges {
        display: flex;
        gap: 8px;
        align-items: center;
        margin-top: 12px;
    }
    .status-badge {
        display: inline-flex;
        gap: 8px;
        align-items: center;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: .86rem;
        font-weight: 600;
        color: rgba(0,0,0,.9);
        background: rgba(255,255,255,.9);
    }
    .status-badge.is-done {
        background: linear-gradient(90deg, #7c3aed, #16a34a);
        color: #fff;
    }
    .status-badge svg { display: inline-block; vertical-align: middle; }
    .checkout-note {
        margin-top: 12px;
        border: 1px dashed rgba(191,255,0,.28);
        border-radius: 10px;
        padding: 9px 11px;
        display: grid;
        gap: 3px;
        color: rgba(255,255,255,.84);
        font-size: .93rem;
        background: rgba(191,255,0,.04);
    }
    .checkout-form {
        margin-top: 14px;
    }
    .checkout-pay-btn {
        width: 100%;
        min-height: 46px;
        border-radius: 12px;
        font-size: .96rem;
        letter-spacing: .01em;
    }
    .checkout-info {
        margin-top: 14px;
    }
    .checkout-retry-btn {
        background: linear-gradient(135deg, #dc2626, #ef4444);
        border-color: rgba(248, 113, 113, 0.75);
    }
    @media (max-width: 680px) {
        .checkout-card {
            border-radius: 13px;
            padding: 12px;
        }
        .checkout-head {
            flex-direction: column;
        }
        .checkout-gateway-chip {
            width: fit-content;
        }
        .checkout-row {
            padding: 10px;
            font-size: .93rem;
        }
    }
</style>

@if(($order->gateway ?? config('payments.default_gateway')) === 'razorpay' && !empty($razorpayKey) && $order->payment_method === 'escrow' && $stage !== 'none')
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        (() => {
            const button = document.getElementById('rzp-pay-btn');
            if (!button) return;
            const hiddenOrderInput = document.getElementById('rzp_order_id');
            const hiddenDirectInput = document.getElementById('rzp_direct_mode');
            const statusNode = document.querySelector('.checkout-info');
            const initUrl = @json(route('payments.init-razorpay', $order));
            let cachedOrderId = @json($gatewayOrderId);
            let opening = false;

            button.addEventListener('click', async () => {
                if (opening) return;
                opening = true;
                button.disabled = true;
                const originalLabel = button.textContent;
                button.textContent = 'Preparing secure checkout...';

                try {
                    const res = await fetch(initUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({})
                    });
                    const payload = await res.json().catch(() => null);
                    if (res.ok && payload && payload.ok && payload.order_id) {
                        cachedOrderId = payload.order_id;
                        if (statusNode) statusNode.textContent = 'Secure checkout is ready. Tap Pay to continue.';
                    } else if (payload && payload.message && statusNode) {
                        statusNode.textContent = payload.message;
                    }
                } catch (_) {
                    if (statusNode) statusNode.textContent = 'Network issue while preparing checkout. Continuing with secure fallback mode.';
                }

                const usingDirect = !cachedOrderId;
                if (hiddenDirectInput) hiddenDirectInput.value = usingDirect ? '1' : '0';
                if (hiddenOrderInput) hiddenOrderInput.value = cachedOrderId || '';

                const options = {
                    key: @json($razorpayKey),
                    amount: @json((int) round(((float) $amountToPay) * 100)),
                    currency: 'INR',
                    name: 'SwapShip',
                    description: 'Order #{{ $order->id }} · {{ $stageLabel }}',
                    callback_url: @json(route('payments.razorpay-callback', $order)),
                    redirect: true,
                    handler: function (response) {
                        document.getElementById('rzp_payment_id').value = response.razorpay_payment_id || '';
                        document.getElementById('rzp_order_id').value = response.razorpay_order_id || '';
                        document.getElementById('rzp_signature').value = response.razorpay_signature || '';
                        if (!response.razorpay_order_id || !response.razorpay_signature) {
                            if (hiddenDirectInput) hiddenDirectInput.value = '1';
                        } else {
                            if (hiddenDirectInput) hiddenDirectInput.value = '0';
                        }
                        document.getElementById('razorpay-pay-form').submit();
                    },
                    theme: { color: '#7c3aed' }
                };
                if (cachedOrderId) {
                    options.order_id = cachedOrderId;
                }
                const rzp = new Razorpay(options);
                rzp.open();
                button.textContent = originalLabel;
                button.disabled = false;
                opening = false;
            });
        })();
    </script>
@endif
