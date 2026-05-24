<section class="card admin-hero admin-anim-in">
    <div class="admin-hero-inner">
        <div class="admin-hero-copy">
            @if(!empty($backUrl))
                <a href="{{ $backUrl }}" class="admin-back-link">
                    <span aria-hidden="true">←</span> {{ $backLabel ?? 'Back' }}
                </a>
            @endif
            <p class="admin-eyebrow">Admin Panel</p>
            <h1 class="admin-title">{{ $title }}</h1>
            @if(!empty($subtitle))
                <p class="admin-subtitle">{{ $subtitle }}</p>
            @endif
        </div>
        <aside class="admin-hero-aside">
            <p class="admin-aside-label">Secure access</p>
            <p class="admin-aside-text">Password + email OTP required for admin sign-in.</p>
            <div class="admin-hero-glow" aria-hidden="true"></div>
        </aside>
    </div>
</section>
