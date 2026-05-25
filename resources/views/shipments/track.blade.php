<x-app-layout>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    <div class="track-topbar">
        <a class="track-back-btn" href="{{ route('shipments.index') }}">
            <span aria-hidden="true">&larr;</span>
            <span>Back to shipments</span>
        </a>
        <span class="track-awb">AWB: {{ $tracking['awb_number'] ?: 'Pending' }}</span>
    </div>

    <section class="track-shell">
        <header class="track-header">
            <div class="track-header-left">
                <p class="track-kicker"><span class="track-live-dot"></span>Live tracking</p>
                <h2>{{ $shipment->exchangeRequest->item->title ?? 'Shipment' }}</h2>
                <div class="track-sub">
                    <span class="track-status-badge" data-status-badge data-status-code="{{ $tracking['status_code'] }}">{{ $tracking['status_display'] }}</span>
                </div>
            </div>
            <div class="track-eta">
                <span>ETA</span>
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
            <button type="button" class="track-fs-exit" data-action="exit-fullscreen" aria-label="Exit fullscreen" title="Exit fullscreen">
                <span aria-hidden="true">&larr;</span>
                <span>Back</span>
            </button>
            <div id="track-map" class="track-map" role="region" aria-label="Shipment route map"></div>
            @if(empty($tracking['sender']['lat']) || empty($tracking['receiver']['lat']))
                <div class="track-map-note" id="track-map-note">
                    Map will appear after both addresses are geocoded. We use OpenStreetMap (free).
                </div>
            @endif
        </div>

        <div class="track-stats" id="track-stats">
            <div class="track-stat">
                <span>Total distance</span>
                <strong id="track-total-dist">—</strong>
            </div>
            <div class="track-stat">
                <span>Distance remaining</span>
                <strong id="track-dist">—</strong>
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
                        <span class="track-time-muted">{{ optional($event->occurred_at)->diffForHumans() }}</span>
                    </li>
                @empty
                    <li class="track-empty">No events yet. Updates will appear here as the courier progresses.</li>
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
        const SHIPPED_STATUSES = ['picked_up', 'in_transit', 'out_for_delivery'];

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
            const svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="#ffffff"><path d="M3 17V7a2 2 0 0 1 2-2h10v4h3l4 4v5h-2a2 2 0 1 1-4 0H9a2 2 0 1 1-4 0H3zm14-5h4l-3-3h-1v3z"/></svg>';
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

            L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                maxZoom: 19,
                subdomains: 'abcd',
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
            }).addTo(map);

            senderMarker = L.marker([s.sender.lat, s.sender.lng], { icon: endpointIcon('#0ea5e9', 'A'), title: 'Pickup' })
                .addTo(map).bindPopup('<strong>Pickup</strong><br><small>' + escapeHtml(s.sender.address || '') + '</small>');
            receiverMarker = L.marker([s.receiver.lat, s.receiver.lng], { icon: endpointIcon('#22c55e', 'B'), title: 'Delivery' })
                .addTo(map).bindPopup('<strong>Delivery</strong><br><small>' + escapeHtml(s.receiver.address || '') + '</small>');

            const sameSpot = Math.abs(s.sender.lat - s.receiver.lat) < 0.0005 && Math.abs(s.sender.lng - s.receiver.lng) < 0.0005;
            if (sameSpot) {
                map.setView([s.sender.lat, s.sender.lng], 14);
            } else {
                map.fitBounds(L.latLngBounds([[s.sender.lat, s.sender.lng], [s.receiver.lat, s.receiver.lng]]), { padding: [50, 50] });
            }

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
                traveledLine = L.polyline(beforeLatLngs, { color: '#16a34a', weight: 5, opacity: 0.9 }).addTo(map);
            } else {
                traveledLine.setLatLngs(beforeLatLngs);
            }
            if (!remainingLine) {
                remainingLine = L.polyline(afterLatLngs, {
                    color: '#94a3b8',
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
            if (meters < 1) return '0 m';
            if (meters >= 1000) return (meters / 1000).toFixed(meters >= 10000 ? 0 : 1) + ' km';
            return Math.round(meters) + ' m';
        }

        function updateCountdownLabel() {
            if (!state) return;
            const etaIso = state.estimated_delivery_at;
            const el = document.getElementById('track-countdown');
            const etaEl = document.getElementById('track-eta-value');
            if (state.status_code === 'delivered') {
                if (el) el.textContent = 'Delivered';
                if (etaEl) etaEl.textContent = 'Delivered';
                return;
            }
            if (!SHIPPED_STATUSES.includes(state.status_code)) {
                if (el) el.textContent = 'Awaiting pickup';
                if (etaEl && etaIso) etaEl.textContent = 'after pickup';
                return;
            }
            if (!etaIso) {
                if (el) el.textContent = '—';
                return;
            }
            const diff = Date.parse(etaIso) - Date.now();
            if (diff <= 0) {
                if (el) el.textContent = 'Arriving any moment';
                if (etaEl) etaEl.textContent = 'Arriving any moment';
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
            const liveProgress = progressFromTime(state, now);
            state._liveProgress = liveProgress;

            const fillEl = document.querySelector('[data-progress-fill]');
            if (fillEl) fillEl.style.width = Math.max(0, Math.min(100, liveProgress * 100)) + '%';

            if (!polyline || !polylineLengths) return;
            const pt = pointAtProgress(polyline, polylineLengths, polylineTotal, liveProgress);
            if (!pt) return;

            startCourierAnimation(pt.lat, pt.lng, pt.bearing);
            drawTrails(pt);

            const totalEl = document.getElementById('track-total-dist');
            const distEl = document.getElementById('track-dist');
            const speedEl = document.getElementById('track-speed');

            const totalMeters = polylineTotal;
            if (totalEl) totalEl.textContent = totalMeters > 0 ? formatDistance(totalMeters) : 'Same location';

            if (distEl) {
                if (state.status_code === 'delivered') {
                    distEl.textContent = 'Delivered';
                } else if (!SHIPPED_STATUSES.includes(state.status_code)) {
                    distEl.textContent = totalMeters > 0 ? formatDistance(totalMeters) + ' (full route)' : '—';
                } else if (totalMeters > 0) {
                    const remainingMeters = totalMeters * (1 - liveProgress);
                    distEl.textContent = formatDistance(remainingMeters);
                } else {
                    distEl.textContent = 'Same location';
                }
            }

            if (speedEl) {
                if (!SHIPPED_STATUSES.includes(state.status_code) && state.status_code !== 'delivered') {
                    speedEl.textContent = 'Not yet shipped';
                } else if (state.status_code === 'delivered') {
                    speedEl.textContent = 'Delivered';
                } else if (totalMeters > 0 && state.estimated_delivery_at && state.pickup_scheduled_at) {
                    const totalH = (Date.parse(state.estimated_delivery_at) - Date.parse(state.pickup_scheduled_at)) / 3600000;
                    if (totalH > 0) speedEl.textContent = ((totalMeters / 1000) / totalH).toFixed(0) + ' km/h avg';
                    else speedEl.textContent = '—';
                } else {
                    speedEl.textContent = '—';
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

            const created = ensureMap(next);
            if (created) applyPolyline(next);

            const badgeEl = document.querySelector('[data-status-badge]');
            if (badgeEl) {
                badgeEl.textContent = next.status_display || next.status_label || badgeEl.textContent;
                badgeEl.setAttribute('data-status-code', next.status_code || '');
            }

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
                        return '<li><strong>' + escapeHtml(ev.label || ev.code || 'Event') + '</strong> <span class="track-time-muted">' + escapeHtml(when) + '</span></li>';
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

        function syncFullscreenState() {
            const wrap = document.querySelector('[data-map-wrap]');
            const isFs = !!document.fullscreenElement;
            if (wrap) wrap.classList.toggle('is-fullscreen', isFs);
            setTimeout(() => map?.invalidateSize(), 200);
        }

        function exitFullscreen() {
            if (document.exitFullscreen) document.exitFullscreen().catch(() => {});
        }

        function wireControls() {
            document.querySelectorAll('.track-ctrl-btn, .track-fs-exit').forEach(btn => {
                btn.addEventListener('click', () => {
                    const action = btn.getAttribute('data-action');
                    if (action === 'recenter') {
                        if (map && courierMarker) {
                            map.setView(courierMarker.getLatLng(), Math.max(map.getZoom(), 11), { animate: true });
                        } else if (map && state?.sender?.lat != null && state?.receiver?.lat != null) {
                            map.fitBounds([[state.sender.lat, state.sender.lng], [state.receiver.lat, state.receiver.lng]], { padding: [50, 50] });
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
                            wrap.requestFullscreen?.().catch(() => {});
                        } else {
                            exitFullscreen();
                        }
                    } else if (action === 'exit-fullscreen') {
                        exitFullscreen();
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

            document.addEventListener('fullscreenchange', syncFullscreenState);
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && document.fullscreenElement) exitFullscreen();
            });
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
        .track-topbar { max-width: 1100px; margin: 6px auto 12px; display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 0 6px; }
        .track-back-btn { display: inline-flex; align-items: center; gap: 8px; border: 1px solid rgba(191,255,0,.48); border-radius: 999px; padding: 8px 12px; color: #f0ffd3; background: linear-gradient(165deg, rgba(191,255,0,.18), rgba(191,255,0,.08)); text-decoration: none; font-weight: 600; font-size: 13px; min-height: 38px; }
        .track-awb { font-size: 11px; color: rgba(255,255,255,.85); letter-spacing: .06em; text-transform: uppercase; background: rgba(255,255,255,.07); padding: 7px 12px; border-radius: 999px; border: 1px solid rgba(255,255,255,.18); white-space: nowrap; }

        /* White light theme card */
        .track-shell {
            padding: 22px;
            max-width: 1100px;
            margin: 0 auto;
            background: #ffffff;
            color: #0f172a;
            border-radius: 18px;
            box-shadow: 0 20px 60px rgba(0,0,0,.35);
            border: 1px solid rgba(255,255,255,.18);
        }

        .track-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 18px; margin-bottom: 18px; flex-wrap: wrap; }
        .track-header-left { min-width: 0; flex: 1 1 240px; }
        .track-kicker { margin: 0 0 6px; font-size: 11px; letter-spacing: .12em; text-transform: uppercase; color: #16a34a; font-weight: 800; display: inline-flex; align-items: center; gap: 7px; }
        .track-live-dot { width: 8px; height: 8px; border-radius: 50%; background: #ef4444; box-shadow: 0 0 0 0 rgba(239,68,68,.55); animation: liveDot 1.6s ease-out infinite; }
        @keyframes liveDot { 0% { box-shadow: 0 0 0 0 rgba(239,68,68,.55); } 70% { box-shadow: 0 0 0 8px rgba(239,68,68,0); } 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); } }
        .track-header h2 { margin: 0 0 8px; font-size: clamp(1.25rem, 2.4vw, 1.6rem); color: #0f172a; font-weight: 700; word-break: break-word; }
        .track-sub { margin: 0; display: flex; flex-wrap: wrap; gap: 6px; }
        .track-status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; letter-spacing: .02em; background: #e2e8f0; color: #334155; border: 1px solid #cbd5e1; }
        .track-status-badge[data-status-code="order_placed"], .track-status-badge[data-status-code="pickup_scheduled"] { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
        .track-status-badge[data-status-code="picked_up"] { background: #dbeafe; color: #1e40af; border-color: #93c5fd; }
        .track-status-badge[data-status-code="in_transit"] { background: #ede9fe; color: #5b21b6; border-color: #c4b5fd; }
        .track-status-badge[data-status-code="out_for_delivery"] { background: #ffedd5; color: #9a3412; border-color: #fdba74; }
        .track-status-badge[data-status-code="delivered"] { background: #dcfce7; color: #166534; border-color: #86efac; }
        .track-status-badge[data-status-code="cancelled"], .track-status-badge[data-status-code="failed"] { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
        .track-eta { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px 18px; background: linear-gradient(165deg, #f0fdf4, #ecfeff); min-width: 150px; }
        .track-eta span { font-size: 11px; letter-spacing: .1em; text-transform: uppercase; color: #475569; font-weight: 800; }
        .track-eta strong { font-size: 1.05rem; color: #15803d; font-weight: 800; font-variant-numeric: tabular-nums; }

        .track-progress { margin-bottom: 18px; }
        .track-progress-line { position: relative; height: 8px; background: #e2e8f0; border-radius: 999px; overflow: hidden; margin-bottom: 14px; }
        .track-progress-fill { height: 100%; background: linear-gradient(90deg, #22c55e, #16a34a, #0ea5e9); background-size: 200% 100%; transition: width .6s cubic-bezier(.16,1,.3,1); animation: progressShimmer 3s linear infinite; }
        @keyframes progressShimmer { 0% { background-position: 0% 0; } 100% { background-position: -200% 0; } }
        .track-steps { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; }
        .track-step { display: flex; flex-direction: column; align-items: center; gap: 6px; font-size: 12px; color: #94a3b8; text-align: center; line-height: 1.2; }
        .track-step-dot { width: 32px; height: 32px; border-radius: 50%; background: #f1f5f9; border: 1.5px solid #cbd5e1; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; color: #64748b; transition: all .25s ease; }
        .track-step.is-active { color: #0f172a; font-weight: 600; }
        .track-step.is-active .track-step-dot { background: #dcfce7; border-color: #16a34a; color: #15803d; }
        .track-step.is-current .track-step-dot { background: linear-gradient(135deg, #22c55e, #16a34a); color: #ffffff; box-shadow: 0 0 0 6px rgba(34,197,94,.2); transform: scale(1.08); }

        .track-map-controls { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
        .track-ctrl-btn { padding: 9px 14px; font-size: 12px; font-weight: 600; letter-spacing: .03em; border-radius: 999px; border: 1px solid #cbd5e1; background: #ffffff; color: #0f172a; cursor: pointer; transition: all .2s ease; box-shadow: 0 1px 0 rgba(0,0,0,.02); min-height: 38px; white-space: nowrap; }
        .track-ctrl-btn:hover { border-color: #16a34a; background: #f0fdf4; color: #15803d; transform: translateY(-1px); }
        .track-ctrl-btn:active { transform: translateY(0); }

        .track-map-wrap { position: relative; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; margin-bottom: 14px; background: #f8fafc; }
        .track-map-wrap:fullscreen, .track-map-wrap.is-fullscreen { border-radius: 0; border: none; }
        .track-map-wrap:fullscreen .track-map, .track-map-wrap.is-fullscreen .track-map { height: 100vh; }
        .track-map { height: 440px; width: 100%; background: #e2e8f0; }
        .track-map .leaflet-control-attribution { background: rgba(255,255,255,.85) !important; color: #475569 !important; font-size: 10px; }
        .track-map .leaflet-control-attribution a { color: #16a34a !important; }
        .track-map-note { position: absolute; bottom: 12px; left: 12px; right: 12px; padding: 10px 12px; border-radius: 10px; background: rgba(15,23,42,.85); color: #ffffff; font-size: 12px; pointer-events: none; }

        .track-fs-exit { display: none; position: absolute; top: 14px; left: 14px; z-index: 1000; align-items: center; gap: 6px; padding: 10px 16px; font-size: 13px; font-weight: 700; border: none; border-radius: 999px; background: rgba(15,23,42,.92); color: #ffffff; cursor: pointer; box-shadow: 0 8px 20px rgba(0,0,0,.4); min-height: 42px; }
        .track-fs-exit:hover { background: #16a34a; }
        .track-map-wrap:fullscreen .track-fs-exit, .track-map-wrap.is-fullscreen .track-fs-exit { display: inline-flex; }

        .track-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; margin-bottom: 16px; }
        .track-stat { display: flex; flex-direction: column; gap: 4px; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 12px; background: linear-gradient(165deg, #ffffff, #f8fafc); transition: transform .2s ease, box-shadow .2s ease; }
        .track-stat:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(15,23,42,.08); }
        .track-stat span { font-size: 11px; letter-spacing: .07em; text-transform: uppercase; color: #64748b; font-weight: 700; }
        .track-stat strong { font-size: 15px; color: #0f172a; font-variant-numeric: tabular-nums; font-weight: 700; }

        .track-route-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; margin-bottom: 18px; }
        .track-route-card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; background: linear-gradient(165deg, #ffffff, #f8fafc); position: relative; overflow: hidden; }
        .track-route-card::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: #0ea5e9; }
        .track-route-card + .track-route-card::before { background: #16a34a; }
        .track-route-label { margin: 0 0 6px; font-size: 11px; letter-spacing: .07em; text-transform: uppercase; color: #64748b; font-weight: 700; }
        .track-route-address { margin: 0; font-size: 14px; line-height: 1.45; color: #0f172a; word-break: break-word; }

        .track-events { border-top: 1px solid #e2e8f0; padding-top: 16px; }
        .track-events-title { margin: 0 0 10px; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: #64748b; font-weight: 700; }
        #track-events-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 6px; }
        #track-events-list li { padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 10px; background: #ffffff; display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; color: #0f172a; transition: border-color .2s ease; }
        #track-events-list li:hover { border-color: #16a34a; }
        .track-time-muted { color: #64748b; font-size: 12px; }
        .track-empty { color: #64748b !important; }

        .courier-pin { background: transparent; border: none; }
        .courier-pin-inner { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #16a34a, #15803d); display: flex; align-items: center; justify-content: center; box-shadow: 0 0 0 4px rgba(34,197,94,.25), 0 8px 24px rgba(22,163,74,.5); transition: transform .4s linear; }
        .courier-pin-inner::after { content: ''; position: absolute; inset: -7px; border-radius: 50%; border: 2px solid rgba(34,197,94,.5); animation: courierRing 1.8s ease-out infinite; }
        @keyframes courierRing {
            0% { transform: scale(.7); opacity: .9; }
            100% { transform: scale(1.7); opacity: 0; }
        }

        .endpoint-pin { background: transparent; border: none; }
        .endpoint-pin-inner { width: 26px; height: 26px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); display: flex; align-items: center; justify-content: center; color: #ffffff; font-weight: 800; font-size: 12px; border: 2px solid #ffffff; box-shadow: 0 4px 14px rgba(0,0,0,.4); }
        .endpoint-pin-inner > * { transform: rotate(45deg); }

        .mylocation-pin { background: transparent; border: none; }
        .mylocation-dot { width: 12px; height: 12px; border-radius: 50%; background: #2563eb; border: 2px solid #fff; position: absolute; top: 5px; left: 5px; z-index: 2; box-shadow: 0 0 0 2px rgba(37,99,235,.4); }
        .mylocation-ring { position: absolute; inset: 0; border-radius: 50%; background: rgba(37,99,235,.25); animation: myPulse 2s ease-out infinite; }
        @keyframes myPulse { 0% { transform: scale(.4); opacity: .9; } 100% { transform: scale(1.6); opacity: 0; } }

        .track-toast { position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%) translateY(20px); background: #0f172a; color: #ffffff; border: 1px solid #16a34a; padding: 12px 20px; border-radius: 999px; font-size: 13px; font-weight: 600; opacity: 0; transition: all .25s ease; z-index: 9999; pointer-events: none; box-shadow: 0 12px 30px rgba(0,0,0,.5); max-width: calc(100vw - 32px); text-align: center; }
        .track-toast.is-show { opacity: 1; transform: translateX(-50%) translateY(0); }

        /* Tablet */
        @media (max-width: 900px) {
            .track-shell { padding: 18px; }
            .track-eta { min-width: 130px; padding: 10px 14px; }
            .track-stats { grid-template-columns: repeat(2, 1fr); }
        }

        /* Mobile */
        @media (max-width: 600px) {
            .track-topbar { margin: 4px auto 10px; }
            .track-back-btn { padding: 8px 12px; font-size: 12px; }
            .track-awb { font-size: 10px; padding: 6px 10px; }
            .track-shell { padding: 14px; border-radius: 14px; }
            .track-header { gap: 12px; }
            .track-header h2 { font-size: 1.15rem; }
            .track-eta { width: 100%; flex-direction: row; align-items: center; justify-content: space-between; padding: 10px 14px; min-width: 0; }
            .track-map { height: 340px; }
            .track-steps { gap: 2px; }
            .track-step { font-size: 9px; gap: 4px; }
            .track-step-dot { width: 26px; height: 26px; font-size: 11px; }
            .track-map-controls { gap: 6px; margin-left: -14px; margin-right: -14px; padding: 0 14px 4px; overflow-x: auto; flex-wrap: nowrap; scrollbar-width: none; -webkit-overflow-scrolling: touch; }
            .track-map-controls::-webkit-scrollbar { display: none; }
            .track-ctrl-btn { padding: 9px 14px; font-size: 12px; min-height: 40px; flex: 0 0 auto; }
            .track-stats { grid-template-columns: repeat(2, 1fr); gap: 8px; }
            .track-stat { padding: 10px 12px; }
            .track-stat strong { font-size: 14px; }
            .track-route-grid { grid-template-columns: 1fr; }
            .track-fs-exit { top: 10px; left: 10px; padding: 9px 14px; font-size: 12px; min-height: 40px; }
            #track-events-list li { padding: 10px 12px; font-size: 13px; }
        }

        /* Small phones */
        @media (max-width: 360px) {
            .track-stats { grid-template-columns: 1fr; }
            .track-step span { display: none; }
        }

        @media print {
            .track-topbar, .track-map-controls, .track-events, .track-back-btn, .track-fs-exit { display: none !important; }
            .track-shell { box-shadow: none !important; border: 1px solid #cbd5e1 !important; }
            .track-map { height: 320px !important; }
            .track-live-dot { animation: none !important; }
        }
    </style>
</x-app-layout>
