<x-app-layout>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    <div class="track-topbar">
        <a class="track-back-btn" href="{{ route('shipments.index') }}">
            <span aria-hidden="true">&larr;</span>
            <span>Back to shipments</span>
        </a>
        <span class="track-awb">AWB: {{ $tracking['awb_number'] ?: 'Pending' }}</span>
    </div>

    <section class="card track-shell">
        <header class="track-header">
            <div>
                <p class="track-kicker">Live tracking</p>
                <h2>{{ $shipment->exchangeRequest->item->title ?? 'Shipment' }}</h2>
                <p class="track-sub muted" id="track-status-label">
                    Status: <strong data-status-label>{{ $tracking['status_display'] }}</strong>
                </p>
            </div>
            <div class="track-eta">
                <span class="muted">ETA</span>
                <strong id="track-eta-value" data-eta-iso="{{ $tracking['eta'] }}">
                    {{ $tracking['eta'] ? \Carbon\Carbon::parse($tracking['eta'])->diffForHumans() : 'TBD' }}
                </strong>
            </div>
        </header>

        <div class="track-progress">
            @php
                $steps = [
                    ['code' => 'order_placed', 'label' => 'Order Placed'],
                    ['code' => 'picked_up', 'label' => 'Picked Up'],
                    ['code' => 'in_transit', 'label' => 'In Transit'],
                    ['code' => 'out_for_delivery', 'label' => 'Out for Delivery'],
                    ['code' => 'delivered', 'label' => 'Delivered'],
                ];
                $statusOrder = ['order_placed' => 0, 'pickup_scheduled' => 0, 'picked_up' => 1, 'in_transit' => 2, 'out_for_delivery' => 3, 'delivered' => 4];
                $currentIndex = $statusOrder[$tracking['status_code']] ?? 0;
            @endphp
            <div class="track-progress-line">
                <div class="track-progress-fill" data-progress-fill style="width: {{ max(0, min(100, $tracking['progress'] * 100)) }}%"></div>
            </div>
            <div class="track-steps">
                @foreach($steps as $i => $step)
                    <div class="track-step {{ $i <= $currentIndex ? 'is-active' : '' }} {{ $i === $currentIndex ? 'is-current' : '' }}" data-step-code="{{ $step['code'] }}">
                        <div class="track-step-dot">{{ $i + 1 }}</div>
                        <span>{{ $step['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="track-map-controls">
            <button type="button" class="track-ctrl-btn" data-action="recenter" title="Recenter map on courier">Recenter</button>
            <button type="button" class="track-ctrl-btn" data-action="mylocation" title="Show my device location">My location</button>
            <button type="button" class="track-ctrl-btn" data-action="fullscreen" title="Toggle fullscreen map">Fullscreen</button>
            <button type="button" class="track-ctrl-btn" data-action="share" title="Copy tracking link">Share link</button>
            <button type="button" class="track-ctrl-btn" data-action="notify" title="Enable browser notifications">Notify me</button>
            <button type="button" class="track-ctrl-btn track-print-btn" data-action="print" title="Print tracking summary">Print</button>
        </div>

        <div class="track-map-wrap" data-map-wrap>
            <div id="track-map" class="track-map" role="region" aria-label="Shipment route map"></div>
            @if(empty($tracking['sender']['lat']) || empty($tracking['receiver']['lat']))
                <div class="track-map-note" id="track-map-note">
                    Map will appear after both addresses are geocoded. We use OpenStreetMap (free).
                </div>
            @endif
        </div>

        <div class="track-stats" id="track-stats">
            <div class="track-stat">
                <span>Distance remaining</span>
                <strong id="track-dist">{{ $tracking['route_distance_km'] !== null ? $tracking['route_distance_km'].' km' : '—' }}</strong>
            </div>
            <div class="track-stat">
                <span>Average speed</span>
                <strong id="track-speed">—</strong>
            </div>
            <div class="track-stat">
                <span>Time remaining</span>
                <strong id="track-countdown">—</strong>
            </div>
            <div class="track-stat" id="track-mydist-wrap" hidden>
                <span>You are</span>
                <strong id="track-mydist">—</strong>
            </div>
        </div>

        <div class="track-route-grid">
            <div class="track-route-card">
                <p class="track-route-label">From (pickup)</p>
                <p class="track-route-address">{{ $tracking['sender']['address'] ?: 'Address not set' }}</p>
            </div>
            <div class="track-route-card">
                <p class="track-route-label">To (delivery)</p>
                <p class="track-route-address">{{ $tracking['receiver']['address'] ?: 'Address not set' }}</p>
            </div>
        </div>

        <div class="track-events">
            <p class="track-events-title">Recent events</p>
            <ul id="track-events-list">
                @forelse($shipment->events->take(8) as $event)
                    <li>
                        <strong>{{ $event->event_label }}</strong>
                        <span class="muted">{{ optional($event->occurred_at)->diffForHumans() }}</span>
                    </li>
                @empty
                    <li class="muted">No events yet. Updates will appear here as the courier progresses.</li>
                @endforelse
            </ul>
        </div>
    </section>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
    (function() {
        const initialState = @json($tracking);
        const stateUrl = @json(route('shipments.track.state', $shipment));
        const trackUrl = @json(url()->current());
        const pollIntervalMs = {{ (int) $pollIntervalSeconds * 1000 }};
        const shipmentId = {{ (int) $shipment->id }};
        const pusherKey = @json($pusherKey);
        const itemTitle = @json($shipment->exchangeRequest->item->title ?? 'Your shipment');

        const STATUS_ANCHORS = {
            order_placed:     { min: 0.00, max: 0.05 },
            pickup_scheduled: { min: 0.02, max: 0.08 },
            picked_up:        { min: 0.08, max: 0.20 },
            in_transit:       { min: 0.20, max: 0.85 },
            out_for_delivery: { min: 0.85, max: 0.97 },
            delivered:        { min: 1.00, max: 1.00 },
            failed:           { min: 0.50, max: 0.50 },
            cancelled:        { min: 0.00, max: 0.00 },
        };
        const STEP_ORDER = { order_placed: 0, pickup_scheduled: 0, picked_up: 1, in_transit: 2, out_for_delivery: 3, delivered: 4 };

        let map = null;
        let senderMarker = null;
        let receiverMarker = null;
        let courierMarker = null;
        let myMarker = null;
        let traveledLine = null;
        let remainingLine = null;
        let polyline = null;
        let polylineLengths = null;
        let polylineTotal = 0;
        let state = null;
        let lastStatusCode = null;
        let lastFetchAt = Date.now();
        let myLatLng = null;
        let courierAnimFrom = null;
        let courierAnimTo = null;
        let courierAnimStart = 0;
        let courierAnimDuration = 0;
        let courierBearing = 0;

        const ESC_MAP = { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' };
        const escapeHtml = (str) => String(str || '').replace(/[&<>"']/g, m => ESC_MAP[m]);

        function showToast(msg) {
            let el = document.getElementById('track-toast');
            if (!el) {
                el = document.createElement('div');
                el.id = 'track-toast';
                el.className = 'track-toast';
                document.body.appendChild(el);
            }
            el.textContent = msg;
            el.classList.add('is-show');
            clearTimeout(el._t);
            el._t = setTimeout(() => el.classList.remove('is-show'), 2400);
        }

        function haversine(a, b) {
            const R = 6371000;
            const toRad = d => d * Math.PI / 180;
            const dLat = toRad(b.lat - a.lat);
            const dLng = toRad(b.lng - a.lng);
            const lat1 = toRad(a.lat);
            const lat2 = toRad(b.lat);
            const h = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
            return 2 * R * Math.asin(Math.min(1, Math.sqrt(h)));
        }

        function bearingBetween(from, to) {
            const toRad = d => d * Math.PI / 180;
            const toDeg = r => r * 180 / Math.PI;
            const φ1 = toRad(from.lat), φ2 = toRad(to.lat);
            const Δλ = toRad(to.lng - from.lng);
            const y = Math.sin(Δλ) * Math.cos(φ2);
            const x = Math.cos(φ1) * Math.sin(φ2) - Math.sin(φ1) * Math.cos(φ2) * Math.cos(Δλ);
            return (toDeg(Math.atan2(y, x)) + 360) % 360;
        }

        function buildPolylineFromState(s) {
            if (s.route_polyline && s.route_polyline.length > 1) return s.route_polyline.map(p => ({ lat: p.lat, lng: p.lng }));
            if (s.sender?.lat != null && s.receiver?.lat != null) {
                return [{ lat: s.sender.lat, lng: s.sender.lng }, { lat: s.receiver.lat, lng: s.receiver.lng }];
            }
            return null;
        }

        function computePolylineLengths(pts) {
            const lens = [];
            let total = 0;
            for (let i = 1; i < pts.length; i++) {
                const len = haversine(pts[i - 1], pts[i]);
                lens.push(len);
                total += len;
            }
            return { lens, total };
        }

        function pointAtProgress(pts, lens, total, progress) {
            if (!pts || pts.length === 0) return null;
            if (pts.length === 1 || progress <= 0) return { lat: pts[0].lat, lng: pts[0].lng, bearing: 0, segmentIndex: 0 };
            if (progress >= 1) {
                const last = pts[pts.length - 1], prev = pts[pts.length - 2] || last;
                return { lat: last.lat, lng: last.lng, bearing: bearingBetween(prev, last), segmentIndex: pts.length - 2 };
            }
            const target = total * progress;
            let accum = 0;
            for (let i = 0; i < lens.length; i++) {
                if (accum + lens[i] >= target || i === lens.length - 1) {
                    const segLen = lens[i] || 1;
                    const t = Math.max(0, Math.min(1, (target - accum) / segLen));
                    const a = pts[i], b = pts[i + 1];
                    return {
                        lat: a.lat + (b.lat - a.lat) * t,
                        lng: a.lng + (b.lng - a.lng) * t,
                        bearing: bearingBetween(a, b),
                        segmentIndex: i,
                    };
                }
                accum += lens[i];
            }
            const last = pts[pts.length - 1];
            return { lat: last.lat, lng: last.lng, bearing: 0, segmentIndex: pts.length - 2 };
        }

        function splitPolylineAt(pts, segmentIndex, lat, lng) {
            const before = pts.slice(0, segmentIndex + 1).concat([{ lat, lng }]);
            const after = [{ lat, lng }].concat(pts.slice(segmentIndex + 1));
            return { before, after };
        }

        function progressFromTime(s, now) {
            const code = s.status_code;
            const anchor = STATUS_ANCHORS[code] || STATUS_ANCHORS.order_placed;
            if (anchor.min === anchor.max) return anchor.min;
            const startIso = s.pickup_scheduled_at || s.updated_at;
            const endIso = s.estimated_delivery_at;
            const start = startIso ? Date.parse(startIso) : null;
            const end = endIso ? Date.parse(endIso) : null;
            if (!start || !end || end <= start) return anchor.min;
            const ratio = Math.max(0, Math.min(1, (now - start) / (end - start)));
            return anchor.min + (anchor.max - anchor.min) * ratio;
        }

        function courierIcon(rotationDeg) {
            const svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="#0b0d12"><path d="M3 17V7a2 2 0 0 1 2-2h10v4h3l4 4v5h-2a2 2 0 1 1-4 0H9a2 2 0 1 1-4 0H3zm14-5h4l-3-3h-1v3z"/></svg>';
            return L.divIcon({
                className: 'courier-pin',
                html: '<div class="courier-pin-inner" style="transform: rotate(' + (rotationDeg || 0) + 'deg);">' + svg + '</div>',
                iconSize: [38, 38],
                iconAnchor: [19, 19],
            });
        }

        function endpointIcon(color, label) {
            return L.divIcon({
                className: 'endpoint-pin',
                html: '<div class="endpoint-pin-inner" style="background:' + color + '">' + label + '</div>',
                iconSize: [22, 22],
                iconAnchor: [11, 22],
            });
        }

        function myLocationIcon() {
            return L.divIcon({
                className: 'mylocation-pin',
                html: '<div class="mylocation-ring"></div><div class="mylocation-dot"></div>',
                iconSize: [22, 22],
                iconAnchor: [11, 11],
            });
        }

        function ensureMap(s) {
            if (map) return true;
            if (s.sender?.lat == null || s.receiver?.lat == null) return false;
            if (typeof L === 'undefined') return false;

            map = L.map('track-map', { zoomControl: true, scrollWheelZoom: false, attributionControl: true })
                .setView([s.sender.lat, s.sender.lng], 6);

            L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                maxZoom: 19,
                subdomains: 'abcd',
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
            }).addTo(map);

            senderMarker = L.marker([s.sender.lat, s.sender.lng], { icon: endpointIcon('#22d3ee', 'A'), title: 'Pickup' })
                .addTo(map).bindPopup('Pickup<br><small>' + escapeHtml(s.sender.address || '') + '</small>');
            receiverMarker = L.marker([s.receiver.lat, s.receiver.lng], { icon: endpointIcon('#bfff00', 'B'), title: 'Delivery' })
                .addTo(map).bindPopup('Delivery<br><small>' + escapeHtml(s.receiver.address || '') + '</small>');

            map.fitBounds(L.latLngBounds([[s.sender.lat, s.sender.lng], [s.receiver.lat, s.receiver.lng]]), { padding: [40, 40] });

            const note = document.getElementById('track-map-note');
            if (note) note.style.display = 'none';
            return true;
        }

        function applyPolyline(s) {
            polyline = buildPolylineFromState(s);
            if (!polyline) return;
            const meta = computePolylineLengths(polyline);
            polylineLengths = meta.lens;
            polylineTotal = meta.total;
        }

        function drawTrails(progressPoint) {
            if (!map || !polyline) return;
            const { before, after } = splitPolylineAt(polyline, progressPoint.segmentIndex, progressPoint.lat, progressPoint.lng);
            const beforeLatLngs = before.map(p => [p.lat, p.lng]);
            const afterLatLngs = after.map(p => [p.lat, p.lng]);
            const isStraight = !(state && state.route_polyline && state.route_polyline.length > 1);

            if (!traveledLine) {
                traveledLine = L.polyline(beforeLatLngs, { color: '#bfff00', weight: 5, opacity: 0.95 }).addTo(map);
            } else {
                traveledLine.setLatLngs(beforeLatLngs);
            }
            if (!remainingLine) {
                remainingLine = L.polyline(afterLatLngs, {
                    color: '#5b6071',
                    weight: 4,
                    opacity: 0.85,
                    dashArray: isStraight ? '8 12' : '6 10',
                }).addTo(map);
            } else {
                remainingLine.setLatLngs(afterLatLngs);
                remainingLine.setStyle({ dashArray: isStraight ? '8 12' : '6 10' });
            }
        }

        function startCourierAnimation(targetLat, targetLng, bearing) {
            const targetLatLng = L.latLng(targetLat, targetLng);
            if (!courierMarker) {
                courierMarker = L.marker(targetLatLng, { icon: courierIcon(bearing), zIndexOffset: 1000 }).addTo(map);
                courierAnimFrom = courierAnimTo = { lat: targetLat, lng: targetLng };
                courierBearing = bearing;
                return;
            }
            const current = courierMarker.getLatLng();
            courierAnimFrom = { lat: current.lat, lng: current.lng };
            courierAnimTo = { lat: targetLat, lng: targetLng };
            courierAnimStart = performance.now();
            const distance = haversine(courierAnimFrom, courierAnimTo);
            courierAnimDuration = Math.min(2400, Math.max(400, distance / 30));
            courierBearing = bearing;
        }

        function animationFrame() {
            if (map && courierMarker && courierAnimTo && courierAnimDuration > 0) {
                const elapsed = performance.now() - courierAnimStart;
                const t = Math.min(1, elapsed / courierAnimDuration);
                const eased = t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
                const lat = courierAnimFrom.lat + (courierAnimTo.lat - courierAnimFrom.lat) * eased;
                const lng = courierAnimFrom.lng + (courierAnimTo.lng - courierAnimFrom.lng) * eased;
                courierMarker.setLatLng([lat, lng]);
                const el = courierMarker.getElement();
                if (el) {
                    const inner = el.querySelector('.courier-pin-inner');
                    if (inner) inner.style.transform = 'rotate(' + courierBearing + 'deg)';
                }
            }
            updateMyDistance();
            updateCountdownLabel();
            requestAnimationFrame(animationFrame);
        }

        function updateMyDistance() {
            if (!myLatLng || !courierMarker) return;
            const c = courierMarker.getLatLng();
            const dist = haversine(myLatLng, { lat: c.lat, lng: c.lng });
            const el = document.getElementById('track-mydist');
            if (el) el.textContent = formatDistance(dist);
        }

        function formatDistance(meters) {
            if (meters >= 1000) return (meters / 1000).toFixed(meters >= 10000 ? 0 : 1) + ' km away';
            return Math.round(meters) + ' m away';
        }

        function updateCountdownLabel() {
            if (!state) return;
            const etaIso = state.estimated_delivery_at;
            const el = document.getElementById('track-countdown');
            const etaEl = document.getElementById('track-eta-value');
            if (!etaIso) {
                if (el) el.textContent = '—';
                return;
            }
            const diff = Date.parse(etaIso) - Date.now();
            if (diff <= 0) {
                const text = state.status_code === 'delivered' ? 'Delivered' : 'Arriving any moment';
                if (el) el.textContent = text;
                if (etaEl) etaEl.textContent = text;
                return;
            }
            const totalSec = Math.floor(diff / 1000);
            const h = Math.floor(totalSec / 3600);
            const m = Math.floor((totalSec % 3600) / 60);
            const s = totalSec % 60;
            const text = (h > 0 ? h + 'h ' : '') + String(m).padStart(2, '0') + 'm ' + String(s).padStart(2, '0') + 's';
            if (el) el.textContent = text;
            if (etaEl) etaEl.textContent = 'in ' + text;
        }

        function tick() {
            if (!state) return;
            const now = Date.now();
            const livePrev = state._liveProgress ?? state.progress ?? 0;
            const liveProgress = progressFromTime(state, now);
            state._liveProgress = liveProgress;

            const fillEl = document.querySelector('[data-progress-fill]');
            if (fillEl) fillEl.style.width = Math.max(0, Math.min(100, liveProgress * 100)) + '%';

            if (!polyline || !polylineLengths) return;
            const pt = pointAtProgress(polyline, polylineLengths, polylineTotal, liveProgress);
            if (!pt) return;

            startCourierAnimation(pt.lat, pt.lng, pt.bearing);
            drawTrails(pt);

            const distEl = document.getElementById('track-dist');
            if (distEl) {
                const remainingMeters = polylineTotal * (1 - liveProgress);
                distEl.textContent = formatDistance(remainingMeters).replace(' away', '');
            }
            const speedEl = document.getElementById('track-speed');
            if (speedEl && state.estimated_delivery_at && state.pickup_scheduled_at) {
                const totalH = (Date.parse(state.estimated_delivery_at) - Date.parse(state.pickup_scheduled_at)) / 3600000;
                if (totalH > 0) {
                    const speed = (polylineTotal / 1000) / totalH;
                    speedEl.textContent = speed.toFixed(0) + ' km/h avg';
                }
            }
        }

        function maybeNotifyStatusChange(s) {
            if (!('Notification' in window)) return;
            if (Notification.permission !== 'granted') return;
            if (lastStatusCode !== null && lastStatusCode !== s.status_code) {
                try {
                    new Notification(itemTitle + ' — ' + (s.status_label || s.status_display || s.status_code), {
                        body: 'Tap to view live tracking.',
                        tag: 'shipment-' + shipmentId,
                    });
                } catch (e) { /* noop */ }
            }
            lastStatusCode = s.status_code;
        }

        function applyState(next) {
            if (!next) return;
            const firstApply = state === null;
            state = next;
            lastFetchAt = Date.now();

            const created = ensureMap(next);
            if (created) applyPolyline(next);

            const labelEl = document.querySelector('[data-status-label]');
            if (labelEl) labelEl.textContent = next.status_display || next.status_label || labelEl.textContent;

            const idx = STEP_ORDER[next.status_code] ?? 0;
            document.querySelectorAll('.track-step').forEach((el, i) => {
                el.classList.toggle('is-active', i <= idx);
                el.classList.toggle('is-current', i === idx);
            });

            if (Array.isArray(next.events)) {
                const list = document.getElementById('track-events-list');
                if (list && next.events.length) {
                    list.innerHTML = next.events.map(ev => {
                        const when = ev.occurred_at ? new Date(ev.occurred_at).toLocaleString() : '';
                        return '<li><strong>' + escapeHtml(ev.label || ev.code || 'Event') + '</strong> <span class="muted">' + escapeHtml(when) + '</span></li>';
                    }).join('');
                }
            }

            tick();
            updateCountdownLabel();

            if (!firstApply) maybeNotifyStatusChange(next);
            else lastStatusCode = next.status_code;
        }

        async function fetchState() {
            try {
                const res = await fetch(stateUrl, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) return;
                const data = await res.json();
                applyState(data);
            } catch (e) { /* swallow */ }
        }

        function wireControls() {
            document.querySelectorAll('.track-ctrl-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const action = btn.getAttribute('data-action');
                    if (action === 'recenter') {
                        if (map && courierMarker) {
                            map.setView(courierMarker.getLatLng(), Math.max(map.getZoom(), 11), { animate: true });
                        } else if (map && state?.sender?.lat != null && state?.receiver?.lat != null) {
                            map.fitBounds([[state.sender.lat, state.sender.lng], [state.receiver.lat, state.receiver.lng]], { padding: [40, 40] });
                        }
                    } else if (action === 'mylocation') {
                        if (!navigator.geolocation) { showToast('Geolocation not available'); return; }
                        showToast('Asking for location permission…');
                        navigator.geolocation.getCurrentPosition((pos) => {
                            myLatLng = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                            if (map) {
                                if (myMarker) myMarker.setLatLng([myLatLng.lat, myLatLng.lng]);
                                else myMarker = L.marker([myLatLng.lat, myLatLng.lng], { icon: myLocationIcon() }).addTo(map).bindPopup('You');
                                document.getElementById('track-mydist-wrap')?.removeAttribute('hidden');
                                updateMyDistance();
                                showToast('Showing your location');
                            }
                        }, (err) => {
                            showToast('Location denied: ' + (err.message || ''));
                        }, { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 });
                    } else if (action === 'fullscreen') {
                        const wrap = document.querySelector('[data-map-wrap]');
                        if (!wrap) return;
                        if (!document.fullscreenElement) {
                            wrap.requestFullscreen?.().then(() => setTimeout(() => map?.invalidateSize(), 200)).catch(() => {});
                        } else {
                            document.exitFullscreen?.().then(() => setTimeout(() => map?.invalidateSize(), 200)).catch(() => {});
                        }
                    } else if (action === 'share') {
                        const url = trackUrl;
                        if (navigator.share) {
                            navigator.share({ title: itemTitle, text: 'Track this shipment live', url }).catch(() => {});
                        } else if (navigator.clipboard) {
                            navigator.clipboard.writeText(url).then(() => showToast('Tracking link copied')).catch(() => showToast('Copy failed'));
                        } else {
                            window.prompt('Copy this tracking link', url);
                        }
                    } else if (action === 'notify') {
                        if (!('Notification' in window)) { showToast('Notifications not supported'); return; }
                        if (Notification.permission === 'granted') { showToast('Notifications already enabled'); return; }
                        Notification.requestPermission().then((perm) => {
                            showToast(perm === 'granted' ? 'Notifications enabled' : 'Notifications denied');
                        });
                    } else if (action === 'print') {
                        window.print();
                    }
                });
            });

            document.addEventListener('fullscreenchange', () => setTimeout(() => map?.invalidateSize(), 200));
        }

        wireControls();
        applyState(initialState);
        setInterval(fetchState, pollIntervalMs);
        setInterval(tick, 2000);
        requestAnimationFrame(animationFrame);

        if (pusherKey && window.Echo && typeof window.Echo.private === 'function') {
            try {
                window.Echo.private('shipment.' + shipmentId).listen('.shipment.tracked', () => fetchState());
            } catch (e) { /* polling covers it */ }
        }
    })();
    </script>

    <style>
        .track-topbar { max-width: 1100px; margin: 6px auto 10px; display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 0 6px; }
        .track-back-btn { display: inline-flex; align-items: center; gap: 8px; border: 1px solid rgba(191,255,0,.48); border-radius: 999px; padding: 8px 12px; color: #f0ffd3; background: linear-gradient(165deg, rgba(191,255,0,.18), rgba(191,255,0,.08)); text-decoration: none; font-weight: 600; }
        .track-awb { font-size: 12px; color: rgba(255,255,255,.7); letter-spacing: .06em; text-transform: uppercase; }
        .track-shell { padding: 18px; max-width: 1100px; margin: 0 auto; }
        .track-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 18px; margin-bottom: 18px; flex-wrap: wrap; }
        .track-kicker { margin: 0 0 4px; font-size: 11px; letter-spacing: .09em; text-transform: uppercase; color: rgba(191,255,0,.85); }
        .track-header h2 { margin: 0 0 6px; font-size: clamp(1.2rem, 2.2vw, 1.55rem); }
        .track-sub { margin: 0; font-size: 14px; }
        .track-eta { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; border: 1px solid rgba(255,255,255,.16); border-radius: 12px; padding: 8px 14px; background: rgba(255,255,255,.03); min-width: 140px; }
        .track-eta strong { font-size: 1rem; color: #f0ffd3; }
        .track-progress { margin-bottom: 14px; }
        .track-progress-line { position: relative; height: 6px; background: rgba(255,255,255,.08); border-radius: 999px; overflow: hidden; margin-bottom: 12px; }
        .track-progress-fill { height: 100%; background: linear-gradient(90deg, #bfff00, #7c3aed); transition: width .6s cubic-bezier(.16,1,.3,1); }
        .track-steps { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; }
        .track-step { display: flex; flex-direction: column; align-items: center; gap: 6px; font-size: 12px; color: rgba(255,255,255,.55); text-align: center; }
        .track-step-dot { width: 28px; height: 28px; border-radius: 50%; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.18); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; transition: all .25s ease; }
        .track-step.is-active { color: #f0ffd3; }
        .track-step.is-active .track-step-dot { background: rgba(191,255,0,.18); border-color: rgba(191,255,0,.6); color: #f0ffd3; }
        .track-step.is-current .track-step-dot { box-shadow: 0 0 0 6px rgba(191,255,0,.15); transform: scale(1.05); }
        .track-map-controls { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
        .track-ctrl-btn { padding: 7px 12px; font-size: 12px; font-weight: 600; letter-spacing: .04em; border-radius: 999px; border: 1px solid rgba(255,255,255,.18); background: rgba(255,255,255,.04); color: #e6e9f0; cursor: pointer; transition: all .2s ease; }
        .track-ctrl-btn:hover { border-color: rgba(191,255,0,.55); background: rgba(191,255,0,.08); color: #f0ffd3; }
        .track-map-wrap { position: relative; border: 1px solid rgba(255,255,255,.14); border-radius: 14px; overflow: hidden; margin-bottom: 14px; background: #0d1015; }
        .track-map-wrap:fullscreen { border-radius: 0; }
        .track-map-wrap:fullscreen .track-map { height: 100vh; }
        .track-map { height: 420px; width: 100%; background: #0d1015; }
        .track-map .leaflet-control-attribution { background: rgba(0,0,0,.55) !important; color: rgba(255,255,255,.75) !important; }
        .track-map .leaflet-control-attribution a { color: rgba(191,255,0,.85) !important; }
        .track-map-note { position: absolute; bottom: 12px; left: 12px; right: 12px; padding: 10px 12px; border-radius: 10px; background: rgba(0,0,0,.55); color: rgba(255,255,255,.85); font-size: 12px; pointer-events: none; }
        .track-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; margin-bottom: 14px; }
        .track-stat { display: flex; flex-direction: column; gap: 4px; padding: 10px 14px; border: 1px solid rgba(255,255,255,.14); border-radius: 12px; background: rgba(255,255,255,.03); }
        .track-stat span { font-size: 11px; letter-spacing: .07em; text-transform: uppercase; color: rgba(255,255,255,.6); }
        .track-stat strong { font-size: 15px; color: #f0ffd3; font-variant-numeric: tabular-nums; }
        .track-route-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; margin-bottom: 18px; }
        .track-route-card { border: 1px solid rgba(255,255,255,.14); border-radius: 12px; padding: 12px; background: rgba(255,255,255,.02); }
        .track-route-label { margin: 0 0 4px; font-size: 11px; letter-spacing: .07em; text-transform: uppercase; color: rgba(255,255,255,.6); }
        .track-route-address { margin: 0; font-size: 14px; line-height: 1.45; }
        .track-events { border-top: 1px dashed rgba(255,255,255,.18); padding-top: 14px; }
        .track-events-title { margin: 0 0 8px; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,.62); }
        #track-events-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 6px; }
        #track-events-list li { padding: 8px 10px; border: 1px solid rgba(255,255,255,.1); border-radius: 8px; background: rgba(255,255,255,.02); display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; }

        .courier-pin { background: transparent; border: none; }
        .courier-pin-inner { width: 38px; height: 38px; border-radius: 50%; background: radial-gradient(circle at 30% 30%, #bfff00, #7c3aed); display: flex; align-items: center; justify-content: center; box-shadow: 0 0 0 4px rgba(191,255,0,.25), 0 6px 18px rgba(0,0,0,.4); transition: transform .4s linear; }
        .courier-pin-inner::after { content: ''; position: absolute; inset: -6px; border-radius: 50%; border: 2px solid rgba(191,255,0,.35); animation: courierRing 1.8s ease-out infinite; }
        @keyframes courierRing {
            0% { transform: scale(.7); opacity: .9; }
            100% { transform: scale(1.6); opacity: 0; }
        }

        .endpoint-pin { background: transparent; border: none; }
        .endpoint-pin-inner { width: 22px; height: 22px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); display: flex; align-items: center; justify-content: center; color: #0b0d12; font-weight: 800; font-size: 11px; border: 2px solid rgba(255,255,255,.85); box-shadow: 0 4px 10px rgba(0,0,0,.45); }
        .endpoint-pin-inner > * { transform: rotate(45deg); }

        .mylocation-pin { background: transparent; border: none; }
        .mylocation-dot { width: 12px; height: 12px; border-radius: 50%; background: #3b82f6; border: 2px solid #fff; position: absolute; top: 5px; left: 5px; z-index: 2; box-shadow: 0 0 0 2px rgba(59,130,246,.4); }
        .mylocation-ring { position: absolute; inset: 0; border-radius: 50%; background: rgba(59,130,246,.25); animation: myPulse 2s ease-out infinite; }
        @keyframes myPulse { 0% { transform: scale(.4); opacity: .9; } 100% { transform: scale(1.6); opacity: 0; } }

        .track-toast { position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%) translateY(20px); background: rgba(11,13,18,.92); color: #f0ffd3; border: 1px solid rgba(191,255,0,.4); padding: 10px 16px; border-radius: 999px; font-size: 13px; font-weight: 600; opacity: 0; transition: all .25s ease; z-index: 9999; pointer-events: none; }
        .track-toast.is-show { opacity: 1; transform: translateX(-50%) translateY(0); }

        @media (max-width: 720px) {
            .track-map { height: 300px; }
            .track-steps { font-size: 10px; }
            .track-step-dot { width: 24px; height: 24px; font-size: 11px; }
            .track-ctrl-btn { padding: 6px 10px; font-size: 11px; }
        }

        @media print {
            .track-topbar, .track-map-controls, .track-events, .track-back-btn { display: none !important; }
            .track-shell { box-shadow: none !important; border: 1px solid #ccc !important; background: #fff !important; color: #000 !important; }
            .track-shell * { color: #000 !important; background: transparent !important; border-color: #ccc !important; }
            .track-map { height: 320px !important; }
            .track-progress-fill { background: #333 !important; }
        }
    </style>
</x-app-layout>
