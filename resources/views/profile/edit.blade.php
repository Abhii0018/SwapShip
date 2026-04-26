<x-app-layout>
    <section class="profile-shell">
        <div class="profile-ambient profile-ambient-a" aria-hidden="true"></div>
        <div class="profile-ambient profile-ambient-b" aria-hidden="true"></div>
        <div class="card profile-hero">
            <div class="profile-hero-top">
                <div class="profile-hero-headline">
                    <p class="profile-eyebrow">Account setup</p>
                    <h1>Hello, {{ auth()->user()->firstName() }}</h1>
                    <p class="profile-hero-subtitle">Finish your identity details once to unlock posting items and sending purchase requests.</p>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="profile-logout-form">
                    @csrf
                    <button class="btn profile-logout-btn" type="submit">Logout</button>
                </form>
            </div>
            <div class="profile-hero-progress">
                <div class="profile-hero-progress-top">
                    <span>Completion</span>
                    <strong>{{ auth()->user()->profileCompletionPercent() }}%</strong>
                </div>
                <div class="profile-hero-progress-bar">
                    <i style="width: {{ auth()->user()->profileCompletionPercent() }}%"></i>
                </div>
            </div>
            <div class="profile-hero-tags">
                <span>Identity Ready</span>
                <span>Sell &amp; Purchase Unlock</span>
                <span>Trusted Account</span>
            </div>
        </div>

        @include('profile.partials.update-profile-information-form')

        <div class="profile-stack">
            <section class="card profile-card profile-card-secondary">
                @include('profile.partials.update-password-form')
            </section>
            <section class="card profile-card profile-card-secondary">
                @include('profile.partials.delete-user-form')
            </section>
        </div>
    </section>
</x-app-layout>
