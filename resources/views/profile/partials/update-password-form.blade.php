<section class="profile-password-shell profile-collapse" x-data="{ open: {{ $errors->updatePassword->isNotEmpty() || session('status') === 'password-updated' ? 'true' : 'false' }} }" :class="{ 'is-open': open }">
    <button type="button" class="profile-collapse-head" @click="open = !open" :aria-expanded="open ? 'true' : 'false'">
        <div class="profile-collapse-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
        </div>
        <div class="profile-collapse-text">
            <h2>Update password</h2>
            <p>Use a strong password to keep your account secure.</p>
        </div>
        <span class="profile-collapse-chevron" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
        </span>
    </button>

    <div class="profile-collapse-body" x-show="open" x-transition.opacity.duration.250ms x-cloak>
        <form method="post" action="{{ route('password.update') }}" class="profile-subform">
            @csrf
            @method('put')

            <div class="profile-field">
                <label for="update_password_current_password">Current password</label>
                <input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password" placeholder="Enter current password" />
                <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
            </div>

            <div class="profile-field">
                <label for="update_password_password">New password</label>
                <input id="update_password_password" name="password" type="password" autocomplete="new-password" placeholder="At least 8 characters" />
                <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
            </div>

            <div class="profile-field">
                <label for="update_password_password_confirmation">Confirm password</label>
                <input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Repeat new password" />
                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
            </div>

            <div class="profile-actions">
                <button class="btn btn-primary" type="submit">Save password</button>
                <button type="button" class="btn" @click="open = false">Cancel</button>

                @if (session('status') === 'password-updated')
                    <p
                        x-data="{ show: true }"
                        x-show="show"
                        x-transition
                        x-init="setTimeout(() => show = false, 2500)"
                        class="profile-ok"
                    >Password updated.</p>
                @endif
            </div>
        </form>
    </div>
</section>
