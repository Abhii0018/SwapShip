<nav class="admin-tabs admin-anim-in admin-anim-delay-1" aria-label="Admin sections">
    <a href="{{ route('admin.dashboard') }}" class="admin-tab {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}">
        <span class="admin-tab-icon" aria-hidden="true">◆</span>
        Overview
    </a>
    <a href="{{ route('admin.users.index') }}" class="admin-tab {{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}">
        <span class="admin-tab-icon" aria-hidden="true">◎</span>
        Users
    </a>
    <a href="{{ route('admin.items.index') }}" class="admin-tab {{ request()->routeIs('admin.items.*') ? 'is-active' : '' }}">
        <span class="admin-tab-icon" aria-hidden="true">▣</span>
        All Items
    </a>
    <a href="{{ route('admin.transactions.index') }}" class="admin-tab {{ request()->routeIs('admin.transactions.*') ? 'is-active' : '' }}">
        <span class="admin-tab-icon" aria-hidden="true">₹</span>
        Transactions
    </a>
    <a href="{{ route('items.index') }}" class="admin-tab admin-tab-muted" target="_blank" rel="noopener">
        <span class="admin-tab-icon" aria-hidden="true">↗</span>
        View Site
    </a>
</nav>
