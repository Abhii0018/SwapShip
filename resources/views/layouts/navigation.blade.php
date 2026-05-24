<nav class="nv-nav">
    <div class="nv-nav-inner">
        <a href="{{ auth()->check() && auth()->user()->isAdmin() ? route('admin.dashboard') : route('home') }}" class="nv-logo" aria-label="SwapShip home">
            <span class="nv-logo-badge" aria-hidden="true">
                <svg class="nv-logo-mark" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <rect x="4" y="6" width="28" height="24" rx="5" stroke="currentColor" stroke-width="2.2"/>
                    <path d="M10 14H20" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                    <path d="M17 11L20 14L17 17" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M26 22H16" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                    <path d="M19 19L16 22L19 25" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span class="nv-logo-spark" aria-hidden="true"></span>
            </span>
            <span class="nv-logo-word">
                <span class="nv-logo-word-swap">SWAP</span><span class="nv-logo-word-ship">SHIP</span>
                <i class="nv-logo-shimmer" aria-hidden="true"></i>
            </span>
        </a>
        <div class="nv-nav-links">
            @auth
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}">ADMIN HOME</a>
                    <a href="{{ route('admin.users.index') }}">USERS</a>
                    <a href="{{ route('admin.items.index') }}">ALL ITEMS</a>
                    <a href="{{ route('admin.transactions.index') }}">TRANSACTIONS</a>
                    <a href="{{ route('items.index') }}" target="_blank" rel="noopener">EXPLORE</a>
                @else
                    <a href="{{ route('home') }}">HOME</a>
                    <a href="{{ route('items.index') }}">EXPLORE ITEMS</a>
                    <a href="{{ route('items.dashboard') }}">MY DASHBOARD</a>
                    <a href="{{ route('exchanges.index') }}">MY EXCHANGES</a>
                    <a href="{{ route('chat.index') }}">CHAT</a>
                    <a href="{{ route('dashboard') }}">DASHBOARD</a>
                @endif
            @else
                <a href="{{ route('home') }}">HOME</a>
                <a href="{{ route('items.index') }}">EXPLORE ITEMS</a>
            @endauth
        </div>
        <div class="nv-nav-actions">
            @guest
                <a href="{{ route('login') }}" class="nv-auth-btn">LOGIN</a>
                <a href="{{ route('register') }}" class="nv-auth-btn">REGISTER</a>
            @else
                @unless(auth()->user()->isAdmin())
                    <a href="{{ route('items.create') }}" class="nv-laser-btn">ADD ITEM</a>
                    <button type="button" class="nv-bell-link js-nav-bell-button" aria-label="Open notifications" aria-expanded="false" aria-controls="nv-notification-popover">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 3a6 6 0 0 0-6 6v3.9l-1.75 2.8a1 1 0 0 0 .85 1.53h13.8a1 1 0 0 0 .85-1.53L18 12.9V9a6 6 0 0 0-6-6Zm0 18a2.75 2.75 0 0 0 2.58-1.8h-5.16A2.75 2.75 0 0 0 12 21Z"/>
                        </svg>
                        <span class="nv-bell-dot {{ ($navNotificationCount ?? 0) > 0 ? '' : 'is-hidden' }}" aria-hidden="true"></span>
                    </button>
                @endunless
                <a href="{{ route('profile.edit') }}" class="nv-profile-link" aria-label="Open profile page" data-nav-profile>
                    <span class="nv-profile-avatar">
                        @if (optional(auth()->user())->profilePhotoUrl())
                            <img src="{{ optional(auth()->user())->profilePhotoUrl() }}" alt="{{ optional(auth()->user())->name }}">
                        @else
                            <span>{{ optional(auth()->user())->initials() }}</span>
                        @endif
                    </span>
                </a>
            @endguest
        </div>
    </div>
</nav>

@auth
    @unless(auth()->user()->isAdmin())
    <aside id="nv-notification-popover" class="nv-notification-popover">
        <div class="nv-notification-head">
            <strong>Notifications</strong>
            <button type="button" class="nv-note-close js-nav-bell-close" aria-label="Close notifications">Close</button>
        </div>
        <div class="nv-notification-list" id="nv-notification-list">
            @forelse(($navNotificationItems ?? collect()) as $note)
                <article class="nv-note-item">
                    <p class="nv-note-title">{{ $note['title'] ?? 'Notification' }}</p>
                    <p class="nv-note-sub">{{ $note['subtitle'] ?? '' }}</p>
                    <p class="nv-note-meta">{{ $note['meta'] ?? '' }}</p>
                </article>
            @empty
                <p class="nv-note-empty" id="nv-note-empty">No new notifications.</p>
            @endforelse
        </div>
        <div class="nv-notification-foot">
            <a href="{{ route('chat.index') }}" class="btn">Open chat</a>
        </div>
    </aside>
    @endunless
@endauth

<nav class="nv-mobile-dock" aria-label="Mobile navigation">
    @guest
        <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>HOME</a>
        <a href="{{ route('items.index') }}" @if(request()->routeIs('items.index')) aria-current="page" @endif>EXPLORE</a>
        <a href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>LOGIN</a>
        <a href="{{ route('register') }}" @if(request()->routeIs('register')) aria-current="page" @endif>REGISTER</a>
    @else
        @if(auth()->user()->isAdmin())
            <a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>ADMIN</a>
            <a href="{{ route('admin.users.index') }}" @if(request()->routeIs('admin.users.*')) aria-current="page" @endif>USERS</a>
            <a href="{{ route('admin.items.index') }}" @if(request()->routeIs('admin.items.*')) aria-current="page" @endif>ITEMS</a>
            <a href="{{ route('admin.transactions.index') }}" @if(request()->routeIs('admin.transactions.*')) aria-current="page" @endif>PAYMENTS</a>
            <a href="{{ route('profile.edit') }}" @if(request()->routeIs('profile.*')) aria-current="page" @endif>PROFILE</a>
        @else
            <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>HOME</a>
            <a href="{{ route('items.index') }}" @if(request()->routeIs('items.index')) aria-current="page" @endif>EXPLORE</a>
            <a href="{{ route('items.dashboard') }}" @if(request()->routeIs('items.dashboard')) aria-current="page" @endif>MY DASHBOARD</a>
            <a href="{{ route('chat.index') }}" @if(request()->routeIs('chat.*')) aria-current="page" @endif>CHAT</a>
            <a href="{{ route('exchanges.index') }}" @if(request()->routeIs('exchanges.*')) aria-current="page" @endif>EXCHANGE</a>
            <a href="{{ route('items.create') }}" @if(request()->routeIs('items.create')) aria-current="page" @endif>ADD</a>
        @endif
    @endguest
</nav>

@auth
    @unless(auth()->user()->isAdmin())
<script>
    (() => {
        const popover = document.getElementById('nv-notification-popover');
        const bellButtons = document.querySelectorAll('.js-nav-bell-button');
        const closeButton = document.querySelector('.js-nav-bell-close');
        const bellDots = document.querySelectorAll('.nv-bell-dot');
        const notificationList = document.getElementById('nv-notification-list');
        if (!popover || !bellButtons.length) return;

        const setOpen = (open) => {
            popover.classList.toggle('is-open', open);
            bellButtons.forEach((button) => button.setAttribute('aria-expanded', open ? 'true' : 'false'));
        };

        bellButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                const willOpen = !popover.classList.contains('is-open');
                setOpen(willOpen);
            });
        });

        closeButton?.addEventListener('click', (event) => {
            event.preventDefault();
            setOpen(false);
        });

        document.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof Element)) return;
            if (target.closest('.js-nav-bell-button') || target.closest('#nv-notification-popover')) return;
            setOpen(false);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') setOpen(false);
        });

        const renderItems = (items) => {
            if (!notificationList) return;
            if (!Array.isArray(items) || items.length === 0) {
                notificationList.innerHTML = '<p class="nv-note-empty" id="nv-note-empty">No new notifications.</p>';
                return;
            }
            notificationList.innerHTML = items.map((note) => `
                <article class="nv-note-item">
                    <p class="nv-note-title">${String(note.title || 'Notification')}</p>
                    <p class="nv-note-sub">${String(note.subtitle || '')}</p>
                    <p class="nv-note-meta">${String(note.meta || '')}</p>
                </article>
            `).join('');
        };

        const setDotVisible = (visible) => {
            bellDots.forEach((dot) => dot.classList.toggle('is-hidden', !visible));
        };

        const refreshNotifications = () => {
            fetch(@json(route('notifications.summary')), {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
            .then((res) => {
                if (!res.ok) return null;
                const ct = res.headers.get('Content-Type') || '';
                if (!ct.includes('application/json')) return null;
                return res.json();
            })
            .then((data) => {
                if (!data) return;
                setDotVisible(Number(data.count || 0) > 0);
                renderItems(data.items || []);
            })
            .catch(() => {});
        };

        refreshNotifications();
        setInterval(refreshNotifications, 30000);
    })();
</script>
    @endunless
@endauth
