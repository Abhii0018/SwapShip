<x-guest-layout>
    @if (session('status'))
        <div class="auth-status">
            {{ session('status') }}
        </div>
    @endif

    <div class="auth-head">
        <h1 class="auth-welcome-title">Welcome <span>back</span></h1>
    </div>

    <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf

        <div class="auth-field">
            <label for="email" class="auth-label">Email Address</label>
            <input id="email" class="auth-input" type="email" name="email" value="{{ $prefillEmail ?? old('email') }}" required autofocus autocomplete="username" />
            @error('email')
                <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-field">
            <label for="password" class="auth-label">Password</label>
            <div class="auth-password-wrap">
                <input id="password" class="auth-input" type="password" name="password" required autocomplete="current-password" />
                <button type="button" class="auth-pass-toggle" data-pass-toggle="password">Show</button>
            </div>
            @error('password')
                <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-remember-row">
            <label for="remember_me" class="auth-remember">
                <input id="remember_me" type="checkbox" name="remember">
                <span>Remember me</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="auth-muted-link">
                    Forgot password?
                </a>
            @endif
        </div>

        <button type="submit" class="btn btn-primary auth-submit-btn">Log in</button>

        <div class="auth-divider"><span>OR</span></div>

        <div class="auth-footer-box">
            <a href="{{ route('auth.google.redirect') }}" class="auth-google-btn">
                <span>G</span>
                Continue with Google
            </a>
            <p class="auth-footnote">
                Don't have an account? 
                <a href="{{ route('register') }}">Register</a>
            </p>
        </div>
    </form>

    <script>
        (() => {
            document.querySelectorAll('[data-pass-toggle]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-pass-toggle');
                    const input = document.getElementById(id);
                    if (!input) return;
                    const show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    btn.textContent = show ? 'Hide' : 'Show';
                });
            });
        })();
    </script>
</x-guest-layout>
