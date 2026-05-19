<x-guest-layout>
    @php($initialCooldown = (int) ($resendCooldownSeconds ?? 0))

    @if (session('status'))
        <div class="auth-status">
            {{ session('status') }}
        </div>
    @endif

    <div class="auth-head">
        <h1>Verify your email</h1>
        <p>
            Enter the 6-digit OTP sent to <strong>{{ $email }}</strong>.
            Check spam if you do not see it within a minute.
        </p>
    </div>

    <form method="POST" action="{{ route('otp.verify.submit') }}" class="auth-form">
        @csrf
        <div class="auth-field">
            <label for="otp" class="auth-label">OTP code</label>
            <input id="otp" class="auth-input" type="text" name="otp" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autofocus>
            @error('otp')
                <p class="auth-error">{{ $message }}</p>
            @enderror
            @error('email')
                <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary auth-submit-btn">Verify OTP</button>
    </form>

    <div class="auth-footer-box" data-otp-resend data-cooldown="{{ $initialCooldown }}">
        <form method="POST" action="{{ route('otp.verify.resend') }}">
            @csrf
            <button
                type="submit"
                class="btn auth-submit-btn"
                data-resend-button
                @disabled($initialCooldown > 0)
                aria-disabled="{{ $initialCooldown > 0 ? 'true' : 'false' }}"
            >
                {{ $initialCooldown > 0 ? 'Resend OTP in '.$initialCooldown.'s' : 'Resend OTP' }}
            </button>
        </form>
        <p class="auth-footnote" data-resend-hint>
            {{ $initialCooldown > 0 ? 'You can request a new OTP shortly.' : 'Did not receive it? Request a new OTP.' }}
        </p>
        <p class="auth-footnote">
            <a href="{{ route('login') }}">Back to login</a>
        </p>
    </div>

    <script>
        (() => {
            const box = document.querySelector('[data-otp-resend]');
            if (!box) return;

            const button = box.querySelector('[data-resend-button]');
            const hint = box.querySelector('[data-resend-hint]');
            let seconds = Number.parseInt(box.dataset.cooldown || '0', 10);

            if (!button || Number.isNaN(seconds) || seconds <= 0) return;

            const update = () => {
                if (seconds > 0) {
                    button.disabled = true;
                    button.setAttribute('aria-disabled', 'true');
                    button.textContent = `Resend OTP in ${seconds}s`;
                    if (hint) hint.textContent = 'You can request a new OTP shortly.';
                    return;
                }

                button.disabled = false;
                button.setAttribute('aria-disabled', 'false');
                button.textContent = 'Resend OTP';
                if (hint) hint.textContent = 'Did not receive it? Request a new OTP.';
            };

            update();
            const timer = window.setInterval(() => {
                seconds -= 1;
                update();
                if (seconds <= 0) {
                    window.clearInterval(timer);
                }
            }, 1000);
        })();
    </script>
</x-guest-layout>
