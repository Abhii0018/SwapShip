<section class="profile-delete-shell">
    <header class="profile-subhead">
        <h2>Delete account</h2>
        <p class="profile-danger-copy">
            Once your account is deleted, all of its resources and data will be permanently deleted.
            Before deleting your account, please download any data or information that you wish to retain.
        </p>
    </header>

    <button
        class="btn profile-danger-btn"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >Delete Account</button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6 profile-delete-modal-form">
            @csrf
            @method('delete')

            <h2 class="profile-delete-modal-title">
                Are you sure you want to delete your account?
            </h2>

            <p class="profile-delete-modal-copy">
                Once your account is deleted, all of its resources and data will be permanently deleted.
                Please enter your password to confirm you would like to permanently delete your account.
            </p>

            <div class="mt-6 profile-field">
                <label for="password">Password confirmation</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    placeholder="Enter your password"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 profile-delete-actions">
                <button type="button" class="btn" x-on:click="$dispatch('close')">Cancel</button>
                <button class="btn profile-danger-btn" type="submit">Delete Account</button>
            </div>
        </form>
    </x-modal>
</section>
