<x-app-layout>
    <section class="container section" style="padding: 40px 20px;">
        <div class="card" style="max-width: 640px; margin: 0 auto; padding: 28px; text-align: center;">
            <h1 style="font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; letter-spacing: -0.02em; margin: 0 0 10px;">Dashboard temporarily unavailable</h1>
            <p class="muted" style="margin: 0 0 18px;">{{ $errorMessage ?? 'Please try again shortly.' }}</p>
            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                <a href="{{ url()->current() }}" class="btn">Retry</a>
                <a href="{{ route('items.index') }}" class="btn">Explore items</a>
                <a href="{{ route('home') }}" class="btn btn-primary">Back home</a>
            </div>
        </div>
    </section>
</x-app-layout>
