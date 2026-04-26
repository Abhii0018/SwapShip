<section class="profile-delete-shell profile-collapse profile-collapse-danger" x-data="{ open: {{ $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }} }" :class="{ 'is-open': open }">
    <button type="button" class="profile-collapse-head" @click="open = !open" :aria-expanded="open ? 'true' : 'false'">
        <div class="profile-collapse-icon profile-collapse-icon-danger" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path></svg>
        </div>
        <div class="profile-collapse-text">
            <h2>Delete account</h2>
            <p>Permanently remove your SwapShip account.</p>
        </div>
        <span class="profile-collapse-chevron" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
        </span>
    </button>

    <div class="profile-collapse-body" x-show="open" x-transition.opacity.duration.250ms x-cloak>
        <p class="profile-danger-copy">
            Once your account is deleted, all of its resources and data will be permanently removed.
            Please download any data you wish to keep before continuing.
        </p>

        <form method="post" action="{{ route('profile.destroy') }}" class="profile-subform">
            @csrf
            @method('delete')

            <div class="profile-field">
                <label for="delete_password">Confirm with current password</label>
                <input
                    id="delete_password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    placeholder="Enter your current password"
                />
                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="profile-actions">
                <button class="btn profile-danger-btn" type="submit">Delete my account</button>
                <button type="button" class="btn" @click="open = false">Cancel</button>
            </div>
        </form>
    </div>
</section>
