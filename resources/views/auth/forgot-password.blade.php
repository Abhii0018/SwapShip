<x-guest-layout>
    @php
        $resetEmail = old('email', session('password_reset_email'));
        $hasOtpResetRoute = \Illuminate\Support\Facades\Route::has('password.otp.reset');
        $otpStepVisible = session('otp_sent') || session()->has('password_reset_email') || $errors->has('otp') || $errors->has('password');
    @endphp

    @if (session('status'))
        <div class="auth-status">
            {{ session('status') }}
        </div>
    @endif

    <div class="auth-head">
        <h1>Reset password</h1>
        <p>Enter your email, get OTP, then set a new password.</p>
    </div>

    <form method="POST" action="{{ route('password.email') }}" class="auth-form">
        @csrf
        <div class="auth-field">
            <label for="email" class="auth-label">Email Address</label>
            <input id="email" class="auth-input" type="email" name="email" value="{{ old('email', $resetEmail) }}" required autofocus autocomplete="email" />
            @error('email')
                <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="btn btn-primary auth-submit-btn">Send OTP</button>
    </form>

    @if($otpStepVisible && $hasOtpResetRoute)
        <form method="POST" action="{{ route('password.otp.reset') }}" class="auth-form" style="margin-top: 14px;">
            @csrf
            <input type="hidden" name="email" value="{{ $resetEmail }}">

            <div class="auth-field">
                <label for="otp" class="auth-label">OTP</label>
                <input id="otp" class="auth-input" type="text" name="otp" value="{{ old('otp') }}" maxlength="6" inputmode="numeric" pattern="[0-9]*" required />
                @error('otp')
                    <p class="auth-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="auth-field">
                <label for="password" class="auth-label">New Password</label>
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
            </div>

            <button type="submit" class="btn btn-primary auth-submit-btn">Verify OTP & Reset Password</button>
        </form>
    @elseif($otpStepVisible && ! $hasOtpResetRoute)
        <p class="auth-error" style="margin-top: 12px;">Password reset route not ready yet. Please refresh in a few seconds.</p>
    @endif

    <div class="auth-footer-box">
        <p class="auth-footnote">
            Remember your password?
            <a href="{{ route('login') }}">Log in</a>
        </p>
    </div>

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
