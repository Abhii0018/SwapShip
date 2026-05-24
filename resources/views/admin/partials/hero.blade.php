<section class="card admin-hero admin-anim-in">
    <div class="admin-hero-inner admin-hero-inner-single">
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
        <div class="admin-hero-glow admin-hero-glow-inline" aria-hidden="true"></div>
    </div>
</section>
