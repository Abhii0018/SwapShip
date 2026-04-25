<section class="card profile-card profile-main-card">
    <header class="profile-head">
        <h2>Profile information</h2>
        <p>Use real details so your exchange and shipment flow stays smooth.</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.avatar.update') }}" class="profile-form" enctype="multipart/form-data" id="profile-avatar-form">
        @csrf
        @method('patch')

        <div class="profile-avatar-row">
            <div class="profile-avatar">
                @if ($user->profilePhotoUrl())
                    <img src="{{ $user->profilePhotoUrl() }}" alt="{{ $user->name }}">
                @else
                    <span>{{ $user->initials() }}</span>
                @endif
            </div>
            <div class="profile-avatar-copy">
                <label for="profile_photo">Profile photo</label>
                <label for="profile_photo" class="profile-file-upload">
                    <span class="profile-file-btn">Upload photo</span>
                    <span id="profile-photo-file-name" class="profile-file-name">No file selected</span>
                </label>
                <input id="profile_photo" name="profile_photo" type="file" accept="image/*" class="profile-file-hidden">
                <small>JPG/PNG/WebP up to 4MB.</small>
                <x-input-error class="mt-2" :messages="$errors->get('profile_photo')" />
            </div>
        </div>

        <div class="profile-avatar-presets">
            <p>Or choose an avatar</p>
            <div class="profile-avatar-preset-grid">
                @php($selectedAvatarPreset = old('avatar_preset', $user->avatar_preset))
                @foreach (($avatarPresets ?? []) as $preset)
                    <label class="profile-avatar-preset">
                        <input type="radio" name="avatar_preset" value="{{ $preset }}" @checked($selectedAvatarPreset === $preset)>
                        <img src="{{ $preset }}" alt="Avatar option">
                    </label>
                @endforeach
                <label class="profile-avatar-preset profile-avatar-preset-none">
                    <input type="radio" name="avatar_preset" value="" @checked($selectedAvatarPreset === null || $selectedAvatarPreset === '')>
                    <span>{{ $user->initials() }}</span>
                </label>
            </div>
            <div class="profile-avatar-actions">
                <button type="submit" class="btn">Apply Avatar</button>
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('avatar_preset')" />
        </div>
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="profile-form">
        @csrf
        @method('patch')

        <div class="profile-grid">
            <div class="profile-field">
                <label for="name">First name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autofocus autocomplete="given-name" placeholder="Enter first name" />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div class="profile-field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="username" placeholder="Enter email address" />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />
            </div>

            <div class="profile-field">
                <label for="phone">Mobile number</label>
                <input id="phone" name="phone" type="text" value="{{ old('phone', $user->phone) }}" required autocomplete="tel" placeholder="Enter mobile number" />
                <x-input-error class="mt-2" :messages="$errors->get('phone')" />
            </div>

            <div class="profile-field">
                <label for="age">Age</label>
                <input id="age" name="age" type="number" min="13" max="100" value="{{ old('age', $user->age) }}" required placeholder="Enter age" />
                <x-input-error class="mt-2" :messages="$errors->get('age')" />
            </div>

            <div class="profile-field">
                <label for="gender">Gender</label>
                <select id="gender" name="gender" required>
                    @foreach (['male' => 'Male', 'female' => 'Female', 'others' => 'Others'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('gender', $user->gender) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('gender')" />
            </div>

            <div class="profile-field">
                <label for="city">City</label>
                <input id="city" name="city" type="text" value="{{ old('city', $user->city) }}" required placeholder="Enter city" />
                <x-input-error class="mt-2" :messages="$errors->get('city')" />
            </div>

            <div class="profile-field">
                <label for="state">State</label>
                <input id="state" name="state" type="text" value="{{ old('state', $user->state) }}" required placeholder="Enter state" />
                <x-input-error class="mt-2" :messages="$errors->get('state')" />
            </div>

            <div class="profile-field">
                <label for="pincode">Pincode</label>
                <input id="pincode" name="pincode" type="text" value="{{ old('pincode', $user->pincode) }}" required placeholder="Enter pincode" />
                <x-input-error class="mt-2" :messages="$errors->get('pincode')" />
            </div>

            <div class="profile-field profile-field-full">
                <label for="location">Location</label>
                <input id="location" name="location" type="text" value="{{ old('location', $user->location) }}" required placeholder="Area, city, state" />
                <x-input-error class="mt-2" :messages="$errors->get('location')" />
            </div>

            <div class="profile-field profile-field-full">
                <label for="address">Address (optional)</label>
                <textarea id="address" name="address" rows="3">{{ old('address', $user->address) }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('address')" />
            </div>
        </div>

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="profile-verify-note">
                    <p>
                        Your email address is unverified.

                        <button form="send-verification" class="profile-link-btn">
                            Click here to re-send the verification email.
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="profile-ok">
                            A new verification link has been sent to your email address.
                        </p>
                    @endif
                </div>
            @endif

        <div class="profile-actions">
            <button class="btn btn-primary" type="submit">Save Profile</button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="profile-ok"
                >Saved.</p>
            @elseif (session('status') === 'profile-avatar-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="profile-ok"
                >Avatar updated.</p>
            @endif
        </div>
    </form>
    <script>
        (() => {
            const fileInput = document.getElementById('profile_photo');
            const fileName = document.getElementById('profile-photo-file-name');
            if (!fileInput || !fileName) return;
            fileInput.addEventListener('change', () => {
                fileName.textContent = fileInput.files && fileInput.files[0]
                    ? fileInput.files[0].name
                    : 'No file selected';
                if (fileInput.files && fileInput.files[0]) {
                    document.getElementById('profile-avatar-form')?.submit();
                }
            });

            const avatarInputs = document.querySelectorAll('#profile-avatar-form input[name="avatar_preset"]');
            avatarInputs.forEach((input) => {
                input.addEventListener('change', () => {
                    document.getElementById('profile-avatar-form')?.submit();
                });
            });
        })();
    </script>
</section>
