<section class="profile-password-shell">
    <header class="profile-subhead">
        <h2>Update password</h2>
        <p>Use a strong password to keep your account secure.</p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="profile-subform">
        @csrf
        @method('put')

        <div class="profile-field">
            <label for="update_password_current_password">Current password</label>
            <input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div class="profile-field">
            <label for="update_password_password">New password</label>
            <input id="update_password_password" name="password" type="password" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div class="profile-field">
            <label for="update_password_password_confirmation">Confirm password</label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="profile-actions">
            <button class="btn btn-primary" type="submit">Save password</button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="profile-ok"
                >Saved.</p>
            @endif
        </div>
    </form>
</section>
