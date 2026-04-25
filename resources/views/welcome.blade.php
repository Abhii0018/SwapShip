<x-app-layout>
<div class="nv-page">
    <div class="nv-ambient nv-ambient-a"></div>
    <div class="nv-ambient nv-ambient-b"></div>

    <section class="nv-hero nv-reveal">
        <div class="nv-wrap">
            <div class="nv-hero-glass"></div>
            <div class="nv-hero-content">
                <div class="nv-hero-box">
                    <h1 class="nv-hero-kinetic">
                        <span class="nv-k-line nv-k-line-1">EXCHANGE <em>SMARTER.</em></span>
                        <span class="nv-k-line nv-k-line-2">SHIP <strong>FASTER.</strong></span>
                    </h1>
                </div>
                <div class="nv-meta-row">
                    <div class="nv-tag">SWAPSHIP NETWORK</div>
                    <div class="nv-countdown" id="nv-countdown" aria-live="polite">23:59:59</div>
                </div>
                <p class="nv-body">SwapShip connects item exchange with integrated logistics, creating a <span class="nv-inline-dynamic" id="nv-inline-dynamic">seamless request-to-delivery workflow</span> for every successful transaction.</p>
                <div class="nv-hero-actions">
                    <a href="{{ route('items.index') }}" class="nv-laser-btn">EXPLORE ITEMS</a>
                    <a href="{{ route('items.create') }}" class="nv-link">LIST YOUR ITEM</a>
                </div>
                <form class="nv-form nv-form-top" action="{{ route('items.index') }}" method="GET">
                    <input type="text" name="search" placeholder="SEARCH ITEMS, CATEGORIES, LOCATIONS">
                    <button type="submit" class="nv-laser-btn">GO</button>
                </form>
            </div>
        </div>
    </section>

    <section class="nv-mobile-quick-actions" aria-label="Quick actions">
        <div class="nv-wrap">
            <div class="nv-mobile-quick-grid">
                <a href="{{ route('items.index') }}" class="nv-mobile-quick-card">
                    <span>Explore</span>
                    <strong>Browse items</strong>
                </a>
                <a href="{{ route('items.create') }}" class="nv-mobile-quick-card">
                    <span>List</span>
                    <strong>Add your item</strong>
                </a>
                <a href="{{ route('chat.index') }}" class="nv-mobile-quick-card">
                    <span>Chat</span>
                    <strong>Open inbox</strong>
                </a>
                <a href="{{ route('shipments.index') }}" class="nv-mobile-quick-card">
                    <span>Shipments</span>
                    <strong>Track orders</strong>
                </a>
            </div>
        </div>
    </section>

    <section class="nv-section nv-reveal">
        <div class="nv-wrap">
            <div class="nv-bento">
                <article class="nv-card">
                    <span>01 / ETHOS</span>
                    <h3>EXCHANGE REQUEST SYSTEM</h3>
                    <p>Users can request, accept, reject, and complete exchanges through a clear state lifecycle.</p>
                </article>
                <article class="nv-card">
                    <span>02 / LOGISTICS</span>
                    <h3>SHIPMENT INTEGRATION</h3>
                    <p>Accepted requests auto-trigger shipment creation with live stage updates.</p>
                </article>
                <article class="nv-card">
                    <span>03 / COMMS</span>
                    <h3>DIRECT CHAT LOOP</h3>
                    <p>Participants communicate in context, linked directly to exchange records.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="nv-section nv-reveal">
        <div class="nv-wrap">
            <div class="nv-section-head">
                <span>04 / COMPONENT CARDS</span>
                <h2>VISUAL MODULES</h2>
                <button type="button" class="nv-shift-btn" id="nv-component-shift">SHIFT</button>
            </div>
            <div class="nv-component-grid nv-component-grid-lane" id="nv-component-grid">
                <article class="nv-component-card">
                    <img src="https://images.unsplash.com/photo-1592750475338-74b7b21085ab?auto=format&fit=crop&w=1200&q=90" alt="Phone listing">
                    <div class="nv-component-overlay">
                        <span>MOBILE DEVICES</span>
                        <h3>SELL OR SWAP IN SECONDS</h3>
                    </div>
                </article>
                <article class="nv-component-card">
                    <img src="https://images.unsplash.com/photo-1481627834876-b7833e8f5570?auto=format&fit=crop&w=1200&q=90" alt="Books exchange listing">
                    <div class="nv-component-overlay">
                        <span>BOOK EXCHANGE</span>
                        <h3>LISTINGS WITH SMART MATCHING</h3>
                    </div>
                </article>
                <article class="nv-component-card">
                    <img src="https://images.unsplash.com/photo-1561154464-82e9adf32764?auto=format&fit=crop&w=1200&q=90" alt="iPad listing">
                    <div class="nv-component-overlay">
                        <span>TABLETS</span>
                        <h3>TRACK REQUESTS IN REAL-TIME</h3>
                    </div>
                </article>
                <article class="nv-component-card">
                    <img src="https://images.unsplash.com/photo-1613040809024-b4ef7ba99bc3?auto=format&fit=crop&w=1200&q=90" alt="Headphones listing">
                    <div class="nv-component-overlay">
                        <span>AUDIO GEAR</span>
                        <h3>SHIPMENT READY WORKFLOW</h3>
                    </div>
                </article>
                <article class="nv-component-card">
                    <img src="https://images.unsplash.com/photo-1549298916-b41d501d3772?auto=format&fit=crop&w=1400&q=90" alt="Sneaker listing">
                    <div class="nv-component-overlay">
                        <span>SNEAKER DROP</span>
                        <h3>TRADE LIMITED EDITION PAIRS</h3>
                    </div>
                </article>
                <article class="nv-component-card">
                    <img src="https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=1400&q=90" alt="Camera listing">
                    <div class="nv-component-overlay">
                        <span>CAMERA GEAR</span>
                        <h3>EXCHANGE PRO PHOTO KITS</h3>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="nv-section nv-reveal">
        <div class="nv-wrap">
            <div class="nv-section-head">
                <span>05 / LIVE FLOW</span>
                <h2>HOW SWAPSHIP WORKS</h2>
            </div>
            <div class="nv-flow-board" id="nv-flow-board">
                <article class="nv-flow-step is-active">
                    <div class="nv-flow-index">01</div>
                    <div class="nv-step-signal"><span></span><span></span><span></span></div>
                    <h3>LIST ITEM</h3>
                    <p>User creates listing with title, photos, condition, and type.</p>
                </article>
                <article class="nv-flow-step">
                    <div class="nv-flow-index">02</div>
                    <div class="nv-step-signal"><span></span><span></span><span></span></div>
                    <h3>REQUEST SENT</h3>
                    <p>Another user sends request to exchange or purchase the item.</p>
                </article>
                <article class="nv-flow-step">
                    <div class="nv-flow-index">03</div>
                    <h3>ACCEPT + CHAT</h3>
                    <p>Owner accepts and both users coordinate through direct chat.</p>
                </article>
                <article class="nv-flow-step">
                    <div class="nv-flow-index">04</div>
                    <div class="nv-step-signal"><span></span><span></span><span></span></div>
                    <h3>SHIPMENT STARTS</h3>
                    <p>Shipment order is created and status transitions begin.</p>
                </article>
                <article class="nv-flow-step">
                    <div class="nv-flow-index">05</div>
                    <h3>COMPLETED</h3>
                    <p>Delivered shipment auto-marks exchange as completed.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="nv-section nv-reveal">
        <div class="nv-wrap">
            <div class="nv-section-head nv-value-head">
                <span>06 / VALUE PROPOSITION</span>
                <h2><em>WHY THIS</em> <strong>PLATFORM</strong></h2>
                <p>Built to turn messy peer-to-peer swaps into a fast, trackable, shipment-ready experience.</p>
            </div>
            <div class="nv-value-grid">
                <article class="nv-value-card">
                    <span>01</span>
                    <div class="nv-value-meter"><i></i></div>
                    <h3>SIMPLIFIES ITEM EXCHANGE</h3>
                    <p>Users exchange and sell through one guided workflow instead of fragmented steps.</p>
                </article>
                <article class="nv-value-card">
                    <span>02</span>
                    <h3>NO SEPARATE COURIER SEARCH</h3>
                    <p>Shipment is integrated into the exchange lifecycle, so logistics is handled in-platform.</p>
                </article>
                <article class="nv-value-card">
                    <span>03</span>
                    <div class="nv-value-meter"><i></i></div>
                    <h3>STRUCTURED PROCESS</h3>
                    <p>State-driven requests and shipment updates keep each transaction organized and trackable.</p>
                </article>
                <article class="nv-value-card">
                    <span>04</span>
                    <h3>SAVES TIME AND COST</h3>
                    <p>Fewer manual steps and built-in delivery orchestration reduce overhead for both users.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="nv-section nv-reveal">
        <div class="nv-wrap">
            <div class="nv-trust-shell">
                <div class="nv-trust-left">
                    <span>07 / TRUST LAYER</span>
                    <h2 class="nv-trust-title">
                        <span>RELIABILITY <em id="nv-trust-dynamic-word">LIVE</em></span>
                        <strong>ENGINE</strong>
                    </h2>
                    <p>Built for confidence at each stage of exchange and delivery.</p>
                    <div class="nv-trust-strips">
                        <div class="nv-trust-strip">
                            <strong>VERIFIED USERS</strong>
                            <small>IDENTITY-LED INTERACTIONS · <span class="nv-live-count" data-target="98">0</span>% TRUST SCORE</small>
                            <div class="nv-trust-mini-meter"><i data-target="98"></i></div>
                            <div class="nv-trust-signal"><span></span><span></span><span></span></div>
                        </div>
                        <div class="nv-trust-strip">
                            <strong>SECURE INTERACTION</strong>
                            <small>PROTECTED REQUEST FLOW · <span class="nv-live-count" data-target="94">0</span>% CLEAN TRANSACTIONS</small>
                            <div class="nv-trust-mini-meter"><i data-target="94"></i></div>
                            <div class="nv-trust-signal"><span></span><span></span><span></span></div>
                        </div>
                        <div class="nv-trust-strip">
                            <strong>SHIPMENT TRACKING</strong>
                            <small>LIVE STATUS VISIBILITY · <span class="nv-live-count" data-target="99">0</span>% TRACEABILITY</small>
                            <div class="nv-trust-mini-meter"><i data-target="99"></i></div>
                            <div class="nv-trust-signal"><span></span><span></span><span></span></div>
                        </div>
                        <div class="nv-trust-strip">
                            <strong>PLATFORM MODERATION</strong>
                            <small>ADMIN SAFETY CONTROLS · <span class="nv-live-count" data-target="96">0</span>% QUALITY INDEX</small>
                            <div class="nv-trust-mini-meter"><i data-target="96"></i></div>
                            <div class="nv-trust-signal"><span></span><span></span><span></span></div>
                        </div>
                    </div>
                </div>
                <div class="nv-trust-right">
                    <div class="nv-trust-core">TRUST CORE</div>
                    <div class="nv-trust-orbit orbit-a">VERIFIED USERS</div>
                    <div class="nv-trust-orbit orbit-b">SECURE INTERACTION</div>
                    <div class="nv-trust-orbit orbit-c">SHIPMENT TRACKING</div>
                    <div class="nv-trust-orbit orbit-d">PLATFORM MODERATION</div>
                </div>
            </div>
        </div>
    </section>

    <footer class="nv-footer">
        <div class="nv-wrap nv-footer-mobile-compact">
            <p>SwapShip &copy; {{ now()->year }}</p>
        </div>
        <div class="nv-wrap nv-footer-grid">
            <div>
                <h3>SWAPSHIP</h3>
                <p>Peer-to-peer exchange with integrated logistics workflow.</p>
                <div class="nv-footer-social">
                    <a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ" aria-label="X">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4l16 16M20 4L4 20"></path></svg>
                    </a>
                    <a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ" aria-label="Instagram">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="3.5" width="17" height="17" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.3" cy="6.7" r="1"></circle></svg>
                    </a>
                    <a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ" aria-label="LinkedIn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 10v8M7 6h.01M12 10v8M12 13a3 3 0 0 1 6 0v5"></path></svg>
                    </a>
                </div>
            </div>
            <div>
                <h4>Platform</h4>
                <a href="{{ route('items.index') }}">Explore Items</a>
                <a href="{{ route('items.create') }}">Add Item</a>
                <a href="{{ route('exchanges.index') }}">My Exchanges</a>
            </div>
            <div>
                <h4>Account</h4>
                <a href="{{ route('dashboard') }}">Dashboard</a>
                <a href="{{ auth()->check() ? route('profile.edit') : route('register') }}">Profile</a>
                <a href="{{ auth()->check() ? route('shipments.index') : route('login') }}">Shipments</a>
            </div>
            <div>
                <h4>Resources</h4>
                <a href="{{ route('home') }}">About</a>
                <a href="{{ route('home') }}">Contact</a>
                <a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ">Demo</a>
            </div>
        </div>
        <div class="nv-wrap nv-footer-logo-strip">
            <span>TRUSTED INTEGRATIONS</span>
            <div class="nv-footer-logos">
                <div>FEDEX</div>
                <div>DHL</div>
                <div>UPS</div>
                <div>DELHIVERY</div>
            </div>
        </div>
        <div class="nv-wrap nv-footer-bottom">
            <p>SwapShip &copy; {{ now()->year }}. All rights reserved.</p>
        </div>
    </footer>
</div>

<script>
    (() => {
        const el = document.getElementById('nv-countdown');
        if (!el) return;
        let total = 24 * 60 * 60 - 1;
        setInterval(() => {
            total = total <= 0 ? 24 * 60 * 60 - 1 : total - 1;
            const h = String(Math.floor(total / 3600)).padStart(2, '0');
            const m = String(Math.floor((total % 3600) / 60)).padStart(2, '0');
            const s = String(total % 60).padStart(2, '0');
            el.textContent = `${h}:${m}:${s}`;
        }, 1000);
    })();

    (() => {
        const board = document.getElementById('nv-flow-board');
        if (!board) return;
        const steps = Array.from(board.querySelectorAll('.nv-flow-step'));
        if (!steps.length) return;
        let active = 0;
        setInterval(() => {
            steps[active].classList.remove('is-active');
            active = (active + 1) % steps.length;
            steps[active].classList.add('is-active');
        }, 2400);
    })();

    (() => {
        const dynamic = document.getElementById('nv-inline-dynamic');
        const phrases = [
            'seamless request-to-delivery workflow',
            'state-driven exchange lifecycle',
            'shipment-ready transaction system',
            'trust-focused swap coordination'
        ];
        if (!dynamic) return;
        let i = 0;
        setInterval(() => {
            dynamic.classList.remove('is-in');
            setTimeout(() => {
                i = (i + 1) % phrases.length;
                dynamic.textContent = phrases[i];
                dynamic.classList.add('is-in');
            }, 220);
        }, 2400);
        dynamic.classList.add('is-in');
    })();

    (() => {
        const counters = Array.from(document.querySelectorAll('.nv-live-count'));
        const meterBars = Array.from(document.querySelectorAll('.nv-trust-mini-meter i'));
        const strips = Array.from(document.querySelectorAll('.nv-trust-strip'));
        if (!counters.length) return;
        const DURATION = 1700;

        const animateNumber = (el, target, delay = 0) => {
            const startAt = performance.now() + delay;
            const step = (ts) => {
                const elapsed = ts - startAt;
                if (elapsed < 0) {
                    requestAnimationFrame(step);
                    return;
                }
                const progress = Math.min(1, elapsed / DURATION);
                const eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = String(Math.round(target * eased));
                if (progress < 1) {
                    requestAnimationFrame(step);
                }
            };
            requestAnimationFrame(step);
        };

        const animateMeter = (bar, target, delay = 0) => {
            bar.style.transition = 'none';
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.transition = 'width 1.8s cubic-bezier(0.16, 1, 0.3, 1)';
                bar.style.width = `${target}%`;
            }, delay);
        };

        const runCycle = () => {
            strips.forEach((strip, idx) => {
                strip.classList.remove('is-live');
                setTimeout(() => strip.classList.add('is-live'), idx * 140);
            });

            counters.forEach((el, idx) => {
                const target = Math.max(0, Math.min(100, Number(el.dataset.target || 0)));
                el.textContent = '0';
                animateNumber(el, target, idx * 120);
            });

            meterBars.forEach((bar, idx) => {
                const target = Math.max(0, Math.min(100, Number(bar.dataset.target || 0)));
                animateMeter(bar, target, idx * 120);
            });
        };

        runCycle();
        setInterval(runCycle, 4600);
    })();

    (() => {
        const el = document.getElementById('nv-trust-dynamic-word');
        if (!el) return;
        const words = ['LIVE', 'SMART', 'SECURE', 'DYNAMIC'];
        let idx = 0;
        setInterval(() => {
            el.classList.remove('is-in');
            setTimeout(() => {
                idx = (idx + 1) % words.length;
                el.textContent = words[idx];
                el.classList.add('is-in');
            }, 140);
        }, 1800);
        el.classList.add('is-in');
    })();

    (() => {
        const shiftBtn = document.getElementById('nv-component-shift');
        const grid = document.getElementById('nv-component-grid');
        if (!shiftBtn || !grid) return;

        let isPaused = false;
        let resumeTimer = null;
        const STEP_PX = 1;
        const INTERVAL_MS = 26; // ~38px/sec

        const tick = () => {
            if (isPaused) return;
            const maxScrollLeft = Math.max(0, grid.scrollWidth - grid.clientWidth);
            if (maxScrollLeft <= 1) return;
            grid.scrollLeft += STEP_PX;
            if (grid.scrollLeft >= maxScrollLeft) {
                grid.scrollLeft = 0;
            }
        };

        const pauseThenResume = () => {
            isPaused = true;
            if (resumeTimer) clearTimeout(resumeTimer);
            resumeTimer = setTimeout(() => {
                isPaused = false;
            }, 1100);
        };

        const autoTimer = setInterval(tick, INTERVAL_MS);

        grid.addEventListener('touchstart', pauseThenResume, { passive: true });
        grid.addEventListener('touchmove', pauseThenResume, { passive: true });
        grid.addEventListener('touchend', pauseThenResume, { passive: true });
        grid.addEventListener('pointerdown', pauseThenResume, { passive: true });

        shiftBtn.addEventListener('click', () => {
            const firstCard = grid.querySelector('.nv-component-card');
            if (!firstCard) return;
            grid.appendChild(firstCard);
            pauseThenResume();
        });

        window.addEventListener('beforeunload', () => {
            clearInterval(autoTimer);
            if (resumeTimer) clearTimeout(resumeTimer);
        });
    })();
</script>
 </x-app-layout>
