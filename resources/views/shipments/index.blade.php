<x-app-layout>
    <div class="shipment-topbar">
        <a class="shipment-back-btn" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard') }}">
            <span aria-hidden="true">←</span>
            <span>Back</span>
        </a>
    </div>
    <section class="card shipment-shell">
        <h2>My Shipments</h2>
        @forelse($shipments as $shipment)
            @php
                $exchange = $shipment->exchangeRequest;
                $isAdmin = auth()->check() && auth()->user()->isAdmin();
                $isBuyer = ($user->id ?? null) === ($exchange->sender_id ?? null);
                $isSeller = ($user->id ?? null) === ($exchange->receiver_id ?? null);
            @endphp
            <article class="shipment-card">
                <header class="shipment-head">
                    <div>
                        <p class="shipment-kicker">Shipment</p>
                        <h3>{{ $shipment->exchangeRequest->item->title }}</h3>
                    </div>
                    <span class="shipment-status">{{ $shipment->status }}</span>
                </header>
                @php
                    $quickPrice = (float) ($shipment->order?->item_amount ?? ($shipment->exchangeRequest->item?->price ?? 0));
                @endphp
                <div class="shipment-quick-summary">
                    <span>Price: INR {{ number_format($quickPrice, 2) }}</span>
                    <a class="shipment-link" href="{{ route('shipments.track', $shipment) }}">Track on map</a>
                </div>
                <details class="shipment-main-details">
                    <summary>View details</summary>
                    <div class="shipment-main-details-body">
                <div class="shipment-meta-grid">
                    <div class="shipment-meta-pill">
                        <span>Provider</span>
                        <strong>{{ strtoupper($shipment->provider ?? 'mock') }}</strong>
                    </div>
                    <div class="shipment-meta-pill">
                        <span>AWB</span>
                        <strong>{{ $shipment->awb_number ?? 'Pending' }}</strong>
                    </div>
                </div>
                @if($shipment->order)
                    @php
                        $latestOtp = $shipment->order->deliveryOtps->sortByDesc('created_at')->first();
                        $otpStatus = 'Not generated';
                        if ($latestOtp) {
                            if ($latestOtp->verified_at) {
                                $otpStatus = 'Verified';
                            } elseif ($latestOtp->expires_at && now()->greaterThan($latestOtp->expires_at)) {
                                $otpStatus = 'Expired';
                            } elseif (($latestOtp->attempts ?? 0) >= 3) {
                                $otpStatus = 'Locked';
                            } else {
                                $otpStatus = 'Active';
                            }
                        }
                        $isBuyer = ($user->id ?? null) === $shipment->order->buyer_id;
                        $buyerPhone = (string) ($shipment->order->buyer?->phone ?? '');
                        $maskedPhone = $buyerPhone ? str_repeat('x', max(strlen($buyerPhone) - 4, 0)).substr($buyerPhone, -4) : 'Not available';
                        $upfrontPaid = !empty($shipment->order->upfront_paid_at);
                        $remainingRequired = ((float) ($shipment->order->remaining_amount ?? 0) > 0.0001)
                            || (bool) ($shipment->order->second_payment_required_before_otp ?? false);
                        $remainingPaid = !empty($shipment->order->remaining_paid_at);
                        $pendingStageLabel = !$upfrontPaid ? 'Upfront payment pending' : (($remainingRequired && !$remainingPaid) ? 'Final doorstep payment pending' : 'All payments done');
                        if (! $upfrontPaid) {
                            $effectivePaymentStatus = 'pending';
                        } elseif ($remainingRequired && ! $remainingPaid) {
                            $effectivePaymentStatus = 'partially paid';
                        } else {
                            $effectivePaymentStatus = 'paid';
                        }
                        $upfrontStatusLabel = $upfrontPaid ? 'paid' : 'pending';
                        $remainingStatusLabel = ! $remainingRequired ? 'not required' : ($remainingPaid ? 'paid' : 'pending');
                    @endphp
                    <div class="shipment-order-block">
                        <p><strong>{{ $shipment->exchangeRequest->item->title }}</strong></p>
                        <p class="shipment-total-line">Price: INR {{ number_format((float)$shipment->order->item_amount, 2) }}</p>
                        <details class="shipment-details-toggle">
                            <summary>View details</summary>
                            <div class="shipment-details-body">
                                <div class="shipment-inline-head">
                                    <span class="shipment-tag">Order #{{ $shipment->order->id }}</span>
                                    <span class="shipment-tag">Method: {{ strtoupper($shipment->order->payment_method) }}</span>
                                </div>
                                <div class="shipment-breakdown-grid">
                                    <div class="shipment-breakdown-pill">
                                        <span>Shipping</span>
                                        <strong>INR {{ number_format((float)$shipment->order->shipping_amount, 2) }}</strong>
                                    </div>
                                    <div class="shipment-breakdown-pill">
                                        <span>Platform Fee</span>
                                        <strong>INR {{ number_format((float)$shipment->order->platform_fee, 2) }}</strong>
                                    </div>
                                    <div class="shipment-breakdown-pill is-wide">
                                        <span>Total payable</span>
                                        <strong>INR {{ number_format((float)$shipment->order->total_amount, 2) }}</strong>
                                    </div>
                                </div>
                            </div>
                        </details>
                        @if($shipment->order->payment_method === 'escrow')
                            @php
                                $upfrontAmount = (float) ($shipment->order->upfront_amount ?? $shipment->order->total_amount);
                                $remainingAmount = (float) ($shipment->order->remaining_amount ?? 0);
                            @endphp
                            <div class="shipment-payment-grid">
                                <div class="shipment-payment-cell {{ $upfrontPaid ? 'is-paid' : 'is-pending' }}">
                                    <span class="shipment-payment-label">Upfront</span>
                                    <strong>INR {{ number_format($upfrontAmount, 2) }}</strong>
                                    <span class="shipment-payment-status">
                                        @if($upfrontPaid)
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            Paid
                                        @else
                                            Pending
                                        @endif
                                    </span>
                                </div>
                                <div class="shipment-payment-cell {{ !$remainingRequired ? 'is-na' : ($remainingPaid ? 'is-paid' : 'is-pending') }}">
                                    <span class="shipment-payment-label">Remaining</span>
                                    <strong>INR {{ number_format($remainingAmount, 2) }}</strong>
                                    <span class="shipment-payment-status">
                                        @if(!$remainingRequired)
                                            Not required
                                        @elseif($remainingPaid)
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            Paid
                                        @else
                                            Pending
                                        @endif
                                    </span>
                                </div>
                            </div>
                        @endif
                        <div class="shipment-state-row">
                            <span class="shipment-state-pill is-state-{{ str_replace(' ', '-', $effectivePaymentStatus) }}">Payment: {{ ucfirst($effectivePaymentStatus) }}</span>
                            <span class="shipment-state-pill is-state-{{ $shipment->order->settlement_status === 'released' ? 'paid' : 'pending' }}">Settlement: {{ ucfirst($shipment->order->settlement_status) }}</span>
                            <span class="shipment-state-pill is-state-{{ $otpStatus === 'Verified' ? 'paid' : ($otpStatus === 'Active' ? 'partially-paid' : 'pending') }}">OTP: {{ $otpStatus }}</span>
                        </div>
                        @if($isBuyer && $shipment->order->payment_method === 'escrow' && (!$upfrontPaid || ($remainingRequired && !$remainingPaid)))
                            <div class="shipment-action-banner is-pay">
                                <div class="shipment-action-banner-body">
                                    <strong>{{ !$upfrontPaid ? 'Pay to start shipping' : 'Pay to unlock rider handover' }}</strong>
                                    <p>
                                        @if(!$upfrontPaid)
                                            Pay INR {{ number_format($upfrontAmount, 2) }} upfront to authorize pickup.
                                        @else
                                            Pay the remaining INR {{ number_format($remainingAmount, 2) }} so the rider can hand over the package.
                                        @endif
                                    </p>
                                </div>
                                <a class="btn btn-primary shipment-pay-btn" href="{{ route('payments.checkout', $shipment->order) }}">
                                    Pay now
                                </a>
                            </div>
                        @elseif($isSeller && $shipment->order->payment_method === 'escrow')
                            @if(!$upfrontPaid)
                                <div class="shipment-action-banner is-await">
                                    <div class="shipment-action-banner-body">
                                        <strong>Awaiting buyer upfront payment</strong>
                                        <p>Pickup will unlock after the buyer pays INR {{ number_format($upfrontAmount, 2) }}.</p>
                                    </div>
                                </div>
                            @elseif($remainingRequired && !$remainingPaid)
                                <div class="shipment-action-banner is-success">
                                    <div class="shipment-action-banner-body">
                                        <strong>Upfront received from buyer</strong>
                                        <p>INR {{ number_format($upfrontAmount, 2) }} held in escrow. Doorstep amount of INR {{ number_format($remainingAmount, 2) }} will be collected on delivery.</p>
                                    </div>
                                </div>
                            @else
                                <div class="shipment-action-banner is-success">
                                    <div class="shipment-action-banner-body">
                                        <strong>All buyer payments received</strong>
                                        <p>Funds will release after OTP verification at delivery.</p>
                                    </div>
                                </div>
                            @endif
                        @endif
                        @if($isBuyer)
                            <div class="buyer-otp-card">
                                <p style="margin:0 0 6px;"><strong>Delivery OTP Inbox (Buyer View)</strong></p>
                                <p class="muted" style="margin:0;">Phone: {{ $maskedPhone }}</p>
                                @if($otpStatus === 'Active')
                                    <p class="muted" style="margin:6px 0 0;">OTP sent to your phone. Share only with rider at handover.</p>
                                    @if($latestOtp?->expires_at)
                                        <p class="muted" style="margin:6px 0 0;">
                                            Expires in:
                                            <span class="otp-countdown" data-expires-at="{{ $latestOtp->expires_at->toIso8601String() }}">--:--</span>
                                        </p>
                                    @endif
                                @elseif($otpStatus === 'Verified')
                                    <p class="muted" style="margin:6px 0 0;">OTP verified. Delivery has been completed successfully.</p>
                                @elseif($otpStatus === 'Expired')
                                    <p class="muted" style="margin:6px 0 0;">OTP expired. Ask admin/support to generate a new OTP.</p>
                                @elseif($otpStatus === 'Locked')
                                    <p class="muted" style="margin:6px 0 0;">OTP locked after multiple failed attempts. Generate a new OTP.</p>
                                @else
                                    <p class="muted" style="margin:6px 0 0;">OTP not generated yet. Wait for out-for-delivery step.</p>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
                @if($isSeller || $isAdmin)
                    <section class="shipment-action-panel">
                        <p class="shipment-role-label">Seller actions</p>
                        @if(!$shipment->order)
                            <form method="POST" action="{{ route('shipments.initiate-payment', $shipment) }}" class="shipment-inline-form">
                                @csrf
                                <label class="shipment-field-label" for="payment-method-{{ $shipment->id }}">Payment mode</label>
                                <select id="payment-method-{{ $shipment->id }}" name="payment_method">
                                    <option value="escrow">Escrow</option>
                                    <option value="cod">Cash on delivery</option>
                                </select>
                                <label class="shipment-field-label" for="negotiated-amount-{{ $shipment->id }}">Negotiated item amount (optional)</label>
                                <input id="negotiated-amount-{{ $shipment->id }}" type="number" min="1" step="0.01" name="negotiated_item_amount" placeholder="e.g. 45000">
                                <label class="shipment-field-label" for="upfront-amount-{{ $shipment->id }}">Upfront amount for escrow (optional)</label>
                                <input id="upfront-amount-{{ $shipment->id }}" type="number" min="1" step="0.01" name="upfront_amount" placeholder="e.g. 25000">
                                <button class="btn shipment-btn-secondary" type="submit">Set Payment Method</button>
                            </form>
                        @else
                            <div class="shipment-state-row">
                                <span class="shipment-state-pill">Method: {{ strtoupper($shipment->order->payment_method) }}</span>
                                @if($shipment->order->payment_method === 'escrow')
                                    <span class="shipment-state-pill">Upfront: INR {{ number_format((float)($shipment->order->upfront_amount ?? $shipment->order->total_amount), 2) }}</span>
                                    <span class="shipment-state-pill">Remaining: INR {{ number_format((float)($shipment->order->remaining_amount ?? 0), 2) }}</span>
                                @endif
                            </div>
                        @endif
                        @if($shipment->order && $shipment->order->payment_method === 'escrow' && !$shipment->order->upfront_paid_at)
                            <p class="shipment-lock-note">Shipment status is locked until buyer pays upfront amount.</p>
                        @endif
                        <form method="POST" action="{{ route('shipments.update-status', $shipment) }}" class="shipment-inline-form">
                            @csrf @method('PATCH')
                            <label class="shipment-field-label" for="shipment-status-{{ $shipment->id }}">Shipment status</label>
                            <select id="shipment-status-{{ $shipment->id }}" name="status"><option>Order Placed</option><option>Picked Up</option><option>In Transit</option><option>Delivered</option></select>
                            <button class="btn shipment-btn-secondary" type="submit" @if($shipment->order && $shipment->order->payment_method === 'escrow' && !$shipment->order->upfront_paid_at) disabled @endif>Update Status</button>
                        </form>
                        <form method="POST" action="{{ route('shipments.schedule-pickup', $shipment) }}" style="margin-top:8px;">
                            @csrf
                            <button class="btn btn-primary shipment-btn-primary" type="submit" @if($shipment->order && $shipment->order->payment_method === 'escrow' && !$shipment->order->upfront_paid_at) disabled @endif>Schedule Pickup</button>
                        </form>
                    </section>
                @endif
                @if($isAdmin)
                    <form method="POST" action="{{ route('shipments.simulate-event', $shipment) }}" class="shipment-inline-form">
                        @csrf
                        <select name="status_code">
                            <option value="picked_up">picked_up</option>
                            <option value="in_transit">in_transit</option>
                            <option value="out_for_delivery">out_for_delivery</option>
                            <option value="delivered">delivered</option>
                        </select>
                        <button class="btn" type="submit">Simulate Event</button>
                    </form>
                @endif
                @auth
                    @if($isAdmin)
                        <form method="POST" action="{{ route('shipments.generate-otp', $shipment) }}" style="margin-top:8px;">
                            @csrf
                            <button class="btn" type="submit">Generate Delivery OTP</button>
                        </form>
                        @if($shipment->order && $shipment->order->smsAuditLogs->isNotEmpty())
                            <div class="shipment-sms-log">
                                <p class="muted" style="margin-bottom:6px;">SMS audit logs</p>
                                @foreach($shipment->order->smsAuditLogs->take(3) as $log)
                                    <p style="margin:4px 0;"><strong>{{ strtoupper($log->status) }}</strong> · {{ $log->phone ?: 'No phone' }} · {{ $log->created_at->diffForHumans() }}</p>
                                @endforeach
                            </div>
                        @endif
                    @endif
                @endauth
                @if($isBuyer || $isAdmin)
                    <section class="shipment-action-panel buyer-panel">
                        <p class="shipment-role-label">Buyer actions</p>
                        @php
                            $otpBlockedByRemaining = $shipment->order
                                && $shipment->order->payment_method === 'escrow'
                                && $shipment->order->second_payment_required_before_otp
                                && empty($shipment->order->remaining_paid_at);
                        @endphp
                        @if($otpBlockedByRemaining)
                            <p class="muted" style="margin:0 0 8px;">Rider handover lock active. Pay remaining amount before OTP verification.</p>
                        @endif
                        <form method="POST" action="{{ route('shipments.verify-otp', $shipment) }}" class="shipment-inline-form">
                            @csrf
                            <label class="shipment-field-label" for="shipment-otp-{{ $shipment->id }}">Delivery OTP</label>
                            <input id="shipment-otp-{{ $shipment->id }}" name="otp_code" maxlength="6" placeholder="Enter delivery OTP">
                            <button class="btn btn-primary shipment-btn-primary" type="submit" @if($otpBlockedByRemaining) disabled @endif>Verify OTP & Complete</button>
                        </form>
                    </section>
                @endif
                @if($shipment->events->isNotEmpty())
                    <div class="shipment-events">
                        <p class="muted" style="margin-bottom:6px;">Recent shipping events</p>
                        @foreach($shipment->events->take(4) as $event)
                            <p class="shipment-event-line"><strong>{{ $event->event_label }}</strong> · {{ optional($event->occurred_at)->diffForHumans() }}</p>
                        @endforeach
                    </div>
                @endif
                    </div>
                </details>
            </article>
        @empty
            <div class="shipment-empty-state">
                <p><strong>No shipments yet.</strong></p>
                <p class="muted">A shipment appears only after seller accepts the request and both users confirm exchange in chat.</p>
                <div class="shipment-empty-actions">
                    <a class="btn" href="{{ route('exchanges.index') }}">Open Exchanges</a>
                    <a class="btn" href="{{ route('chat.index') }}">Open Chat</a>
                </div>
            </div>
        @endforelse
        {{ $shipments->links() }}
    </section>
</x-app-layout>

<style>
    .shipment-topbar {
        max-width: 1100px;
        margin: 6px auto 10px;
    }
    .shipment-back-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: 1px solid rgba(191,255,0,.48);
        border-radius: 999px;
        padding: 8px 12px;
        color: #f0ffd3;
        background: linear-gradient(165deg, rgba(191,255,0,.18), rgba(191,255,0,.08));
        text-decoration: none;
        font-weight: 600;
    }
    .shipment-shell { overflow: hidden; }
    .shipment-shell h2 {
        margin: 0 0 12px;
        font-size: clamp(1.2rem, 2.3vw, 1.55rem);
        letter-spacing: -.01em;
    }
    .shipment-card {
        padding: 14px;
        border: 1px solid rgba(255,255,255,.14);
        border-radius: 14px;
        margin-bottom: 12px;
        background:
            radial-gradient(circle at 8% 10%, rgba(191,255,0,.08), transparent 34%),
            linear-gradient(165deg, rgba(255,255,255,.04), rgba(255,255,255,.01));
        animation: shipCardIn .45s cubic-bezier(0.16, 1, 0.3, 1) both;
        transition: transform .3s cubic-bezier(0.16, 1, 0.3, 1), border-color .3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow .3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .shipment-card:hover {
        transform: translateY(-2px);
        border-color: rgba(191,255,0,.35);
        box-shadow: 0 14px 28px rgba(0,0,0,.24);
    }
    .shipment-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 10px;
    }
    .shipment-kicker {
        margin: 0;
        font-size: 11px;
        letter-spacing: .09em;
        text-transform: uppercase;
        color: rgba(255,255,255,.58);
    }
    .shipment-head h3 {
        margin: 3px 0 0;
        font-size: clamp(1.02rem, 1.5vw, 1.2rem);
        letter-spacing: -.01em;
    }
    .shipment-meta-grid {
        margin-top: 10px;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }
    .shipment-meta-pill {
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 10px;
        padding: 7px 9px;
        background: rgba(255,255,255,.02);
        display: grid;
        gap: 2px;
    }
    .shipment-meta-pill span {
        font-size: 11px;
        color: rgba(255,255,255,.62);
        text-transform: uppercase;
        letter-spacing: .06em;
    }
    .shipment-meta-pill strong {
        font-size: 13px;
        font-weight: 600;
    }
    .shipment-track-line {
        margin: 9px 0 0;
    }
    .shipment-track-links {
        display: inline-flex;
        gap: 10px;
        align-items: center;
    }
    .shipment-quick-summary {
        margin-top: 8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 10px;
        padding: 8px 10px;
        background: rgba(255,255,255,.02);
        font-weight: 600;
    }
    .shipment-main-details {
        margin-top: 8px;
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 10px;
        background: rgba(255,255,255,.02);
        overflow: hidden;
    }
    .shipment-main-details > summary {
        cursor: pointer;
        list-style: none;
        padding: 9px 10px;
        font-size: 12px;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: rgba(191,255,0,.95);
        user-select: none;
    }
    .shipment-main-details > summary::-webkit-details-marker { display: none; }
    .shipment-main-details-body {
        padding: 0 10px 10px;
        border-top: 1px dashed rgba(255,255,255,.16);
    }
    .shipment-main-details-body.is-open {
        margin-top: 8px;
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 10px;
        background: rgba(255,255,255,.02);
    }
    .shipment-status {
        border: 1px solid rgba(191,255,0,.42);
        border-radius: 999px;
        padding: 3px 8px;
        font-size: 11px;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--accent);
        background: rgba(191,255,0,.07);
    }
    .shipment-link { color: var(--accent); text-decoration: underline; }
    .shipment-order-block {
        margin: 10px 0 0;
        padding: 10px;
        border: 1px solid rgba(191,255,0,.22);
        border-radius: 10px;
        background:
            radial-gradient(circle at 84% 10%, rgba(124,58,237,.12), transparent 48%),
            linear-gradient(165deg, rgba(255,255,255,.04), rgba(255,255,255,.02));
    }
    .shipment-total-line {
        margin: 6px 0;
        font-weight: 700;
        color: rgba(191,255,0,.95);
    }
    .shipment-details-toggle {
        margin-top: 8px;
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 10px;
        background: rgba(255,255,255,.02);
        overflow: hidden;
    }
    .shipment-details-toggle summary {
        cursor: pointer;
        padding: 8px 10px;
        font-size: 12px;
        letter-spacing: .07em;
        text-transform: uppercase;
        color: rgba(191,255,0,.92);
        user-select: none;
    }
    .shipment-details-body {
        padding: 0 10px 10px;
        border-top: 1px dashed rgba(255,255,255,.16);
    }
    .shipment-inline-head {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
    }
    .shipment-tag {
        border: 1px solid rgba(191,255,0,.35);
        border-radius: 999px;
        padding: 4px 8px;
        font-size: 11px;
        letter-spacing: .06em;
        text-transform: uppercase;
        background: rgba(191,255,0,.08);
        color: rgba(240,255,210,.95);
    }
    .shipment-breakdown-grid {
        margin-top: 8px;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 7px;
    }
    .shipment-breakdown-pill {
        border: 1px solid rgba(255,255,255,.14);
        border-radius: 10px;
        padding: 8px 9px;
        background: rgba(255,255,255,.02);
        display: grid;
        gap: 2px;
    }
    .shipment-breakdown-pill span {
        font-size: 10px;
        color: rgba(255,255,255,.62);
        text-transform: uppercase;
        letter-spacing: .08em;
    }
    .shipment-breakdown-pill strong {
        color: rgba(245,255,220,.96);
        font-size: 13px;
    }
    .shipment-breakdown-pill.is-wide {
        grid-column: 1 / -1;
        border-color: rgba(191,255,0,.3);
        background: rgba(191,255,0,.06);
    }
    .shipment-state-row {
        margin-top: 8px;
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .shipment-state-pill {
        border: 1px solid rgba(255,255,255,.14);
        border-radius: 999px;
        padding: 6px 11px;
        font-size: 13px;
        font-weight: 600;
        color: rgba(255,255,255,.85);
        background: rgba(255,255,255,.04);
    }
    .shipment-state-pill.is-state-paid {
        background: rgba(34,197,94,.14);
        border-color: rgba(34,197,94,.5);
        color: #bbf7d0;
    }
    .shipment-state-pill.is-state-pending {
        background: rgba(250,204,21,.12);
        border-color: rgba(250,204,21,.45);
        color: #fde68a;
    }
    .shipment-state-pill.is-state-partially-paid {
        background: rgba(56,189,248,.12);
        border-color: rgba(56,189,248,.45);
        color: #bae6fd;
    }
    .shipment-payment-grid {
        margin-top: 10px;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }
    .shipment-payment-cell {
        position: relative;
        padding: 10px 12px;
        border: 1px solid rgba(255,255,255,.16);
        border-radius: 12px;
        background: rgba(255,255,255,.04);
        display: grid;
        gap: 4px;
    }
    .shipment-payment-cell::before {
        content: '';
        position: absolute;
        left: 0;
        top: 8px;
        bottom: 8px;
        width: 3px;
        border-radius: 0 3px 3px 0;
        background: rgba(255,255,255,.2);
    }
    .shipment-payment-cell.is-paid {
        background: linear-gradient(165deg, rgba(34,197,94,.16), rgba(34,197,94,.06));
        border-color: rgba(34,197,94,.5);
    }
    .shipment-payment-cell.is-paid::before { background: #22c55e; }
    .shipment-payment-cell.is-paid .shipment-payment-status { color: #bbf7d0; }
    .shipment-payment-cell.is-pending {
        background: linear-gradient(165deg, rgba(250,204,21,.12), rgba(250,204,21,.04));
        border-color: rgba(250,204,21,.42);
    }
    .shipment-payment-cell.is-pending::before { background: #facc15; }
    .shipment-payment-cell.is-pending .shipment-payment-status { color: #fde68a; }
    .shipment-payment-cell.is-na {
        opacity: .65;
    }
    .shipment-payment-cell.is-na::before { background: rgba(255,255,255,.3); }
    .shipment-payment-label {
        font-size: 10px;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: rgba(255,255,255,.62);
        font-weight: 700;
    }
    .shipment-payment-cell strong {
        font-size: 15px;
        font-weight: 700;
        color: rgba(245,255,220,.96);
        font-variant-numeric: tabular-nums;
    }
    .shipment-payment-status {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .03em;
    }
    .shipment-action-banner {
        margin-top: 10px;
        padding: 12px 14px;
        border-radius: 12px;
        display: flex;
        gap: 12px;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        border: 1px solid transparent;
    }
    .shipment-action-banner-body { flex: 1 1 220px; min-width: 0; }
    .shipment-action-banner strong { display: block; font-size: 14px; margin-bottom: 4px; }
    .shipment-action-banner p { margin: 0; font-size: 13px; line-height: 1.4; }
    .shipment-action-banner.is-pay {
        background: linear-gradient(165deg, rgba(239,68,68,.22), rgba(239,68,68,.08));
        border-color: rgba(239,68,68,.55);
        color: #fecaca;
    }
    .shipment-action-banner.is-pay strong { color: #ffe2e2; }
    .shipment-action-banner.is-pay p { color: #fda4af; }
    .shipment-action-banner.is-success {
        background: linear-gradient(165deg, rgba(34,197,94,.2), rgba(34,197,94,.06));
        border-color: rgba(34,197,94,.55);
        color: #bbf7d0;
    }
    .shipment-action-banner.is-success strong { color: #dcfce7; }
    .shipment-action-banner.is-success p { color: #bbf7d0; }
    .shipment-action-banner.is-await {
        background: linear-gradient(165deg, rgba(250,204,21,.18), rgba(250,204,21,.05));
        border-color: rgba(250,204,21,.5);
        color: #fde68a;
    }
    .shipment-action-banner.is-await strong { color: #fef9c3; }
    .shipment-action-banner.is-await p { color: #fde68a; }
    .shipment-pay-alert {
        margin-top: 8px;
        border: 1px solid rgba(255,107,107,.55);
        background: linear-gradient(165deg, rgba(255,107,107,.2), rgba(255,107,107,.08));
        color: #ffd7d7;
        border-radius: 10px;
        padding: 9px 10px;
        font-size: 13px;
    }
    .shipment-pay-btn {
        min-height: 42px;
        padding: 0 18px;
        border-radius: 11px;
        min-width: 110px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        letter-spacing: .02em;
        white-space: nowrap;
    }
    .shipment-lock-note {
        margin: 8px 0 0;
        color: #ffb5b5;
        font-size: 12px;
        border-left: 3px solid rgba(255,107,107,.8);
        padding: 4px 8px;
        background: rgba(255,107,107,.08);
        border-radius: 6px;
    }
    .buyer-otp-card {
        margin-top: 8px;
        padding: 10px;
        border: 1px solid rgba(191,255,0,.35);
        border-radius: 10px;
        background: radial-gradient(circle at 12% 15%, rgba(191,255,0,.12), rgba(191,255,0,.05));
        animation: otpGlow 2.4s ease-in-out infinite;
    }
    .shipment-inline-form {
        margin-top: 8px;
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    .shipment-action-panel {
        margin-top: 10px;
        border: 1px solid rgba(255,255,255,.14);
        border-radius: 12px;
        padding: 10px;
        background:
            radial-gradient(circle at 85% 10%, rgba(124,58,237,.12), transparent 42%),
            linear-gradient(165deg, rgba(255,255,255,.03), rgba(255,255,255,.01));
    }
    .shipment-action-panel .shipment-inline-form {
        margin-top: 0;
    }
    .shipment-action-panel .shipment-inline-form + .shipment-inline-form {
        margin-top: 8px;
    }
    .shipment-field-label {
        font-size: 11px;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: rgba(255,255,255,.62);
        width: 100%;
    }
    .shipment-action-panel select,
    .shipment-action-panel input {
        min-height: 40px;
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,.14);
        background: rgba(11,14,20,.65);
        color: rgba(255,255,255,.93);
        padding: 0 10px;
    }
    .shipment-btn-secondary {
        border-radius: 10px;
        min-height: 40px;
        background: linear-gradient(165deg, rgba(255,255,255,.08), rgba(255,255,255,.02));
        border-color: rgba(255,255,255,.18);
    }
    .shipment-btn-primary {
        min-height: 42px;
        border-radius: 10px;
        font-weight: 600;
    }
    .shipment-btn-primary[disabled] {
        opacity: .55;
        cursor: not-allowed;
        filter: grayscale(.2);
    }
    .buyer-panel {
        background:
            radial-gradient(circle at 8% 12%, rgba(191,255,0,.12), transparent 45%),
            linear-gradient(165deg, rgba(255,255,255,.03), rgba(255,255,255,.01));
    }
    .shipment-role-label {
        margin: 0 0 8px;
        font-size: 11px;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: rgba(255,255,255,.62);
    }
    .shipment-empty-state {
        border: 1px dashed rgba(255,255,255,.2);
        border-radius: 12px;
        padding: 14px;
        background: rgba(255,255,255,.02);
        display: grid;
        gap: 8px;
    }
    .shipment-empty-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .shipment-sms-log {
        margin-top: 8px;
        border: 1px dashed rgba(191,255,0,.28);
        padding: 8px;
        border-radius: 8px;
        background: rgba(191,255,0,.04);
    }
    .shipment-events {
        margin-top: 10px;
        border-top: 1px dashed rgba(255,255,255,.2);
        padding-top: 10px;
    }
    .shipment-event-line {
        margin: 4px 0;
        padding-left: 12px;
        position: relative;
    }
    .shipment-event-line::before {
        content: '';
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: rgba(191,255,0,.78);
        position: absolute;
        left: 0;
        top: 8px;
    }
    .otp-countdown {
        display: inline-block;
        min-width: 56px;
        font-weight: 700;
        color: var(--accent);
        letter-spacing: .04em;
    }
    @keyframes shipCardIn {
        from { opacity: 0; transform: translateY(12px) scale(.99); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    @keyframes otpGlow {
        0%, 100% { box-shadow: 0 0 0 0 rgba(191,255,0,0); }
        50% { box-shadow: 0 0 0 4px rgba(191,255,0,.08); }
    }
    @media (max-width: 760px) {
        .shipment-topbar {
            margin: 6px 0 8px;
            padding: 0 4px;
        }
        .shipment-shell {
            padding-bottom: 118px;
        }
        .shipment-card {
            padding: 10px;
            border-radius: 10px;
        }
        .shipment-meta-grid {
            grid-template-columns: 1fr;
        }
        .shipment-order-block {
            padding: 8px;
            line-height: 1.5;
        }
        .shipment-breakdown-grid {
            grid-template-columns: 1fr;
        }
        .shipment-inline-form {
            display: grid;
            grid-template-columns: 1fr;
            align-items: stretch;
            gap: 8px;
        }
        .shipment-action-panel {
            padding: 9px;
        }
        .shipment-inline-form .btn,
        .shipment-inline-form select,
        .shipment-inline-form input {
            width: 100%;
        }
        .buyer-otp-card {
            padding: 9px;
        }
        .shipment-quick-summary {
            flex-direction: column;
            align-items: stretch;
            gap: 6px;
        }
        .shipment-quick-summary .shipment-link {
            text-align: center;
            padding: 8px 12px;
            background: rgba(191,255,0,.1);
            border: 1px solid rgba(191,255,0,.35);
            border-radius: 8px;
            font-weight: 700;
        }
        .shipment-action-banner {
            padding: 12px;
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }
        .shipment-action-banner .shipment-pay-btn {
            width: 100%;
            min-height: 44px;
        }
        .shipment-state-row {
            gap: 5px;
        }
        .shipment-state-pill {
            font-size: 12px;
            padding: 5px 9px;
        }
        .shipment-payment-grid {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 400px) {
        .shipment-card {
            padding: 9px;
        }
        .shipment-head h3 {
            font-size: 1rem;
        }
        .shipment-payment-cell strong {
            font-size: 14px;
        }
    }
</style>

<script>
(() => {
    const nodes = Array.from(document.querySelectorAll('.otp-countdown'));
    if (!nodes.length) return;

    const render = () => {
        const now = Date.now();
        nodes.forEach((el) => {
            const target = Date.parse(el.dataset.expiresAt || '');
            if (!target || Number.isNaN(target)) {
                el.textContent = '--:--';
                return;
            }
            const diff = Math.max(0, Math.floor((target - now) / 1000));
            const minutes = Math.floor(diff / 60);
            const seconds = diff % 60;
            el.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            if (diff === 0) {
                el.textContent = 'Expired';
            }
        });
    };

    render();
    setInterval(render, 1000);
})();
</script>
