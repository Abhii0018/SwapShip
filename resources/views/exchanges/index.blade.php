<x-app-layout>
    <section class="exchange-shell">
        <div class="exchange-grid">
        <section class="card exchange-main">
            <h2>Received Requests</h2>
            @forelse($receivedRequests as $request)
                <article class="exchange-row">
                    <p><strong>{{ $request->item->title }}</strong> from {{ $request->sender->name }}</p>
                    <p class="muted">{{ $request->status }}</p>
                    <div class="exchange-confirm-pills">
                        <span class="exchange-pill {{ $request->sender_confirmed_at ? 'is-done' : 'is-pending' }}">
                            Sender: {{ $request->sender_confirmed_at ? 'Confirmed' : 'Pending' }}
                        </span>
                        <span class="exchange-pill {{ $request->receiver_confirmed_at ? 'is-done' : 'is-pending' }}">
                            Receiver: {{ $request->receiver_confirmed_at ? 'Confirmed' : 'Pending' }}
                        </span>
                    </div>
                    @php
                        $contactReady = filled($request->sender?->phone) && filled($request->sender?->address) && filled($request->receiver?->phone) && filled($request->receiver?->address);
                    @endphp
                    @if(!$contactReady)
                        <p class="muted">Both users must complete phone and address before acceptance/shipping.</p>
                    @endif
                    <div class="exchange-inline-form">
                        @if($request->status === 'Pending')
                            <form method="POST" action="{{ route('exchanges.update-status', $request) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="Accepted">
                                <button class="btn btn-primary" type="submit" @disabled(!$contactReady)>Accept</button>
                            </form>
                            <form method="POST" action="{{ route('exchanges.update-status', $request) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="Rejected">
                                <button class="btn" type="submit">Reject</button>
                            </form>
                        @elseif($request->status === 'Accepted')
                            @if($request->sender_confirmed_at && $request->receiver_confirmed_at)
                                <a class="btn btn-primary" href="{{ route('shipments.index') }}">Proceed to shipment</a>
                            @else
                                <p class="muted">Waiting for both users to confirm exchange in chat.</p>
                            @endif
                        @elseif(in_array($request->status, ['In Progress', 'Completed'], true))
                            <a class="btn" href="{{ route('shipments.index') }}">View shipment</a>
                        @else
                            <p class="muted">Request {{ strtolower($request->status) }}.</p>
                        @endif
                    </div>
                </article>
            @empty <p class="muted">No received requests.</p> @endforelse
        </section>
        <section class="card exchange-side">
            <h2>Sent Requests</h2>
            @forelse($sentRequests as $request)
                <article class="exchange-row">
                    <p><strong>{{ $request->item->title }}</strong> - <span class="muted">{{ $request->status }}</span></p>
                    <div class="exchange-confirm-pills">
                        <span class="exchange-pill {{ $request->sender_confirmed_at ? 'is-done' : 'is-pending' }}">
                            Sender: {{ $request->sender_confirmed_at ? 'Confirmed' : 'Pending' }}
                        </span>
                        <span class="exchange-pill {{ $request->receiver_confirmed_at ? 'is-done' : 'is-pending' }}">
                            Receiver: {{ $request->receiver_confirmed_at ? 'Confirmed' : 'Pending' }}
                        </span>
                    </div>
                    @if($request->status === 'Accepted' && $request->sender_confirmed_at && $request->receiver_confirmed_at)
                        <div class="exchange-inline-form">
                            <a class="btn btn-primary" href="{{ route('shipments.index') }}">Proceed to shipment</a>
                        </div>
                    @elseif(in_array($request->status, ['In Progress', 'Completed'], true))
                        <div class="exchange-inline-form">
                            <a class="btn btn-primary" href="{{ route('shipments.index') }}">View Shipment</a>
                        </div>
                    @endif
                </article>
            @empty <p class="muted">No sent requests.</p> @endforelse
        </section>
        </div>
    </section>
</x-app-layout>

<style>
    .exchange-shell { display: grid; gap: 12px; }
    .exchange-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 12px; }
    .exchange-main, .exchange-side { display: grid; gap: 10px; }
    .exchange-row {
        padding: 10px;
        border: 1px solid rgba(255,255,255,.14);
        border-radius: 10px;
        background: linear-gradient(160deg, rgba(255,255,255,.03), rgba(255,255,255,.01));
        transition: transform .28s cubic-bezier(0.16, 1, 0.3, 1), border-color .28s cubic-bezier(0.16, 1, 0.3, 1);
        animation: uiFadeSlideIn .4s cubic-bezier(0.16, 1, 0.3, 1) both;
    }
    .exchange-row:hover { transform: translateY(-2px); border-color: rgba(191,255,0,.34); }
    .exchange-confirm-pills { display: flex; gap: 6px; flex-wrap: wrap; margin: 8px 0; }
    .exchange-pill {
        border-radius: 999px;
        padding: 4px 8px;
        font-size: 11px;
        border: 1px solid rgba(255,255,255,.16);
        background: rgba(255,255,255,.03);
    }
    .exchange-pill.is-done { border-color: rgba(191,255,0,.5); background: rgba(191,255,0,.1); }
    .exchange-pill.is-pending { border-color: rgba(255,193,7,.35); background: rgba(255,193,7,.08); }
    .exchange-inline-form { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    @media (max-width: 980px) { .exchange-grid { grid-template-columns: 1fr; } }
</style>
