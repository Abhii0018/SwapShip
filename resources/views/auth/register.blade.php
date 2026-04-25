<x-guest-layout>
    <div class="auth-head">
        <h1>Create account</h1>
        <p>Get started with secure swaps, chat, and shipment tracking.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="auth-form">
        @csrf

        <div class="auth-field">
            <label for="name" class="auth-label">First Name</label>
            <input id="name" class="auth-input" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="given-name" />
            @error('name')
                <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-field">
            <label for="email" class="auth-label">Email Address</label>
            <input id="email" class="auth-input" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" spellcheck="false" autocapitalize="none" />
            @error('email')
                <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-field">
            <label for="phone" class="auth-label">Phone</label>
            <input id="phone" class="auth-input" type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" inputmode="tel" pattern="[0-9+\-\s()]{7,20}" maxlength="20" required />
            @error('phone')
                <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-field">
            <label for="role" class="auth-label">Role</label>
            <select id="role" name="role" class="auth-input" required>
                <option value="user" selected>User</option>
            </select>
            <small class="auth-help">Currently only user registration is enabled.</small>
            @error('role')
                <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-field">
            <label for="password" class="auth-label">Password</label>
            <div class="auth-password-wrap">
                <input id="password" class="auth-input" type="password" name="password" required autocomplete="new-password" />
                <button type="button" class="auth-pass-toggle" data-pass-toggle="password">Show</button>
            </div>
            @error('password')
                <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-field">
            <label for="password_confirmation" class="auth-label">Confirm Password</label>
            <div class="auth-password-wrap">
                <input id="password_confirmation" class="auth-input" type="password" name="password_confirmation" required autocomplete="new-password" />
                <button type="button" class="auth-pass-toggle" data-pass-toggle="password_confirmation">Show</button>
            </div>
            @error('password_confirmation')
                <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary auth-submit-btn">Create Account</button>

        <div class="auth-divider"><span>OR</span></div>

        <div class="auth-footer-box">
            <a href="{{ route('auth.google.redirect') }}" class="auth-google-btn">
                <span>G</span>
                Continue with Google
            </a>
            <p class="auth-footnote">
                Already have an account? 
                <a href="{{ route('login') }}">Log in</a>
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
