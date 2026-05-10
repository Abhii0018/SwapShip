@php
    $hasInfoErrors = $errors->getBag('default')->isNotEmpty();
    $justSaved = session('status') === 'profile-updated';
    $profileComplete = auth()->user()->hasCompletedProfile();
    $shouldOpen = $hasInfoErrors || (! $justSaved && ! $profileComplete);
@endphp

<section class="card profile-card profile-main-card profile-info-shell"
    x-data="{ open: {{ $shouldOpen ? 'true' : 'false' }} }"
    :class="{ 'is-open': open, 'is-closed': !open }">
    <header class="profile-info-head">
        <div class="profile-info-head-left">
            <h2>Profile information</h2>
            <p>Use real details so your exchange and shipment flow stays smooth.</p>
        </div>
        <button type="button" class="profile-edit-btn" x-show="!open" @click="open = true" aria-label="Edit profile information">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z"></path></svg>
            <span>Edit</span>
        </button>
        <button type="button" class="profile-edit-btn profile-edit-btn-close" x-show="open" x-cloak @click="open = false" aria-label="Close form">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
    </header>

    <div class="profile-info-summary" x-show="!open" x-transition.opacity.duration.200ms>
        <div class="profile-summary-avatar">
            @if ($user->profilePhotoUrl())
                <img src="{{ $user->profilePhotoUrl() }}" alt="{{ $user->name }}">
            @else
                <span>{{ $user->initials() }}</span>
            @endif
        </div>
        <div class="profile-summary-meta">
            <p class="profile-summary-name">{{ $user->name }}</p>
            <p class="profile-summary-line"><span>Email</span>{{ $user->email }}</p>
            <p class="profile-summary-line"><span>Phone</span>{{ $user->phone ?: 'Not set' }}</p>
            <p class="profile-summary-line"><span>City</span>{{ trim(($user->city ?? '').($user->state ? ', '.$user->state : '')) ?: 'Not set' }}</p>
            <p class="profile-summary-line"><span>Pincode</span>{{ $user->pincode ?: 'Not set' }}</p>
            @if (session('status') === 'profile-updated')
                <p class="profile-summary-saved"
                   x-data="{ show: true }"
                   x-show="show"
                   x-transition
                   x-init="setTimeout(() => show = false, 3000)">Profile saved successfully.</p>
            @endif
        </div>
    </div>

    <div x-show="open" x-transition.opacity.duration.200ms x-cloak>
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
                        <span class="profile-file-btn">Upload &amp; crop</span>
                        <span id="profile-photo-file-name" class="profile-file-name">No file selected</span>
                    </label>
                    <input id="profile_photo" name="profile_photo" type="file" accept="image/*" class="profile-file-hidden">
                    <small>JPG/PNG/WebP up to 4MB. You'll crop it before saving.</small>
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

        <form method="post" action="{{ route('profile.update') }}" class="profile-form profile-info-form">
            @csrf
            @method('patch')

            <div class="profile-section-title">
                <span class="profile-section-dot"></span>
                <span>Personal details</span>
            </div>
            <div class="profile-field">
                <label for="name">First name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autocomplete="given-name" placeholder="Enter first name" />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div class="profile-field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="email" placeholder="Enter email address" />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />
            </div>

            <div class="profile-field">
                <label for="phone">Mobile number</label>
                <input id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone', $user->phone) }}" required autocomplete="tel" placeholder="10-digit mobile" />
                <x-input-error class="mt-2" :messages="$errors->get('phone')" />
            </div>

            <div class="profile-pair-row">
                <div class="profile-field">
                    <label for="age">Age</label>
                    <input id="age" name="age" type="number" min="13" max="100" value="{{ old('age', $user->age) }}" required placeholder="Age" />
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
            </div>

            <div class="profile-section-title">
                <span class="profile-section-dot"></span>
                <span>Where you're based</span>
            </div>
            <div class="profile-pair-row">
                <div class="profile-field">
                    <label for="city">City</label>
                    <input id="city" name="city" type="text" value="{{ old('city', $user->city) }}" required placeholder="City" />
                    <x-input-error class="mt-2" :messages="$errors->get('city')" />
                </div>

                <div class="profile-field">
                    <label for="state">State</label>
                    <input id="state" name="state" type="text" value="{{ old('state', $user->state) }}" required placeholder="State" />
                    <x-input-error class="mt-2" :messages="$errors->get('state')" />
                </div>
            </div>

            <div class="profile-field">
                <label for="pincode">Pincode</label>
                <input id="pincode" name="pincode" type="text" inputmode="numeric" value="{{ old('pincode', $user->pincode) }}" required placeholder="6-digit pincode" />
                <x-input-error class="mt-2" :messages="$errors->get('pincode')" />
            </div>

            <div class="profile-field">
                <label for="location">Location</label>
                <input id="location" name="location" type="text" value="{{ old('location', $user->location) }}" required placeholder="Area, city, state" />
                <x-input-error class="mt-2" :messages="$errors->get('location')" />
            </div>

            <div class="profile-field">
                <label for="address">Address (optional)</label>
                <textarea id="address" name="address" rows="3" placeholder="Street, landmark, building...">{{ old('address', $user->address) }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('address')" />
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

            <div class="profile-actions profile-actions-sticky">
                <button class="btn btn-primary profile-save-btn" type="submit">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span>Save profile</span>
                </button>
            </div>
        </form>
    </div>
</section>

<div id="profile-crop-modal" class="profile-crop-modal" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-label="Crop your profile photo">
    <div class="profile-crop-backdrop" data-crop-cancel></div>
    <div class="profile-crop-dialog" role="document">
        <header class="profile-crop-head">
            <div>
                <h3>Crop your photo</h3>
                <p>Drag the photo and pinch / scroll to zoom. The square area will become your circular avatar.</p>
            </div>
            <button type="button" class="profile-crop-close" data-crop-cancel aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </header>
        <div class="profile-crop-stage">
            <div class="profile-crop-canvas">
                <img id="profile-crop-image" alt="">
            </div>
        </div>
        <div class="profile-crop-controls">
            <button type="button" class="profile-crop-tool" data-crop-zoom-out aria-label="Zoom out">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="8" y1="11" x2="14" y2="11"></line><line x1="20" y1="20" x2="16.5" y2="16.5"></line></svg>
            </button>
            <button type="button" class="profile-crop-tool" data-crop-zoom-in aria-label="Zoom in">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="8" y1="11" x2="14" y2="11"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="20" y1="20" x2="16.5" y2="16.5"></line></svg>
            </button>
            <button type="button" class="profile-crop-tool" data-crop-rotate aria-label="Rotate 90 degrees">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15A9 9 0 1 1 18 6.36L23 10"></path></svg>
            </button>
            <button type="button" class="profile-crop-tool" data-crop-reset aria-label="Reset crop">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
            </button>
        </div>
        <footer class="profile-crop-foot">
            <button type="button" class="btn profile-crop-cancel" data-crop-cancel>Cancel</button>
            <button type="button" class="btn btn-primary profile-crop-apply" id="profile-crop-apply">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span>Use this photo</span>
            </button>
        </footer>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js" defer></script>
<script>
(() => {
    const fileInput = document.getElementById('profile_photo');
    const fileName = document.getElementById('profile-photo-file-name');
    const form = document.getElementById('profile-avatar-form');
    const modal = document.getElementById('profile-crop-modal');
    const cropImage = document.getElementById('profile-crop-image');
    const applyBtn = document.getElementById('profile-crop-apply');
    if (!fileInput || !fileName || !form || !modal || !cropImage || !applyBtn) return;

    let cropper = null;
    let lastObjectUrl = null;
    let originalFile = null;

    const closeModal = () => {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('profile-crop-open');
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        if (lastObjectUrl) {
            URL.revokeObjectURL(lastObjectUrl);
            lastObjectUrl = null;
        }
    };

    const openModal = () => {
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('profile-crop-open');
    };

    const startCropper = () => {
        if (!window.Cropper) {
            setTimeout(startCropper, 60);
            return;
        }
        cropper = new window.Cropper(cropImage, {
            aspectRatio: 1,
            viewMode: 1,
            dragMode: 'move',
            background: false,
            autoCropArea: 0.9,
            movable: true,
            zoomable: true,
            rotatable: true,
            scalable: false,
            cropBoxMovable: true,
            cropBoxResizable: true,
            responsive: true,
            minContainerHeight: 200,
            minContainerWidth: 200,
        });
    };

    fileInput.addEventListener('change', () => {
        const file = fileInput.files && fileInput.files[0];
        if (!file) {
            fileName.textContent = 'No file selected';
            return;
        }
        if (!/^image\//.test(file.type)) {
            alert('Please choose a valid image file.');
            fileInput.value = '';
            return;
        }
        if (file.size > 4 * 1024 * 1024) {
            alert('Image too large. Please pick a file under 4 MB.');
            fileInput.value = '';
            return;
        }
        originalFile = file;
        fileName.textContent = file.name;
        if (lastObjectUrl) URL.revokeObjectURL(lastObjectUrl);
        lastObjectUrl = URL.createObjectURL(file);
        cropImage.src = lastObjectUrl;
        openModal();
        cropImage.onload = () => {
            if (cropper) cropper.destroy();
            startCropper();
        };
    });

    modal.querySelectorAll('[data-crop-cancel]').forEach((el) => {
        el.addEventListener('click', () => {
            fileInput.value = '';
            fileName.textContent = 'No file selected';
            closeModal();
        });
    });

    modal.querySelector('[data-crop-zoom-in]')?.addEventListener('click', () => cropper?.zoom(0.1));
    modal.querySelector('[data-crop-zoom-out]')?.addEventListener('click', () => cropper?.zoom(-0.1));
    modal.querySelector('[data-crop-rotate]')?.addEventListener('click', () => cropper?.rotate(90));
    modal.querySelector('[data-crop-reset]')?.addEventListener('click', () => cropper?.reset());

    applyBtn.addEventListener('click', () => {
        if (!cropper || !originalFile) return;
        applyBtn.disabled = true;
        applyBtn.classList.add('is-loading');
        const canvas = cropper.getCroppedCanvas({
            width: 512,
            height: 512,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
            fillColor: '#fff',
        });
        if (!canvas) {
            applyBtn.disabled = false;
            applyBtn.classList.remove('is-loading');
            return;
        }
        const targetType = originalFile.type === 'image/png' ? 'image/png' : 'image/jpeg';
        const targetExt = targetType === 'image/png' ? 'png' : 'jpg';
        canvas.toBlob((blob) => {
            if (!blob) {
                applyBtn.disabled = false;
                applyBtn.classList.remove('is-loading');
                return;
            }
            const baseName = (originalFile.name || 'profile').replace(/\.[^.]+$/, '');
            const cropped = new File([blob], `${baseName}-cropped.${targetExt}`, { type: targetType });
            try {
                const dt = new DataTransfer();
                dt.items.add(cropped);
                fileInput.files = dt.files;
            } catch (e) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'profile_photo_data';
                hidden.value = canvas.toDataURL(targetType, 0.92);
                form.appendChild(hidden);
            }
            fileName.textContent = cropped.name;
            closeModal();
            form.submit();
        }, targetType, 0.92);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.hidden) {
            fileInput.value = '';
            fileName.textContent = 'No file selected';
            closeModal();
        }
    });

    const avatarInputs = document.querySelectorAll('#profile-avatar-form input[name="avatar_preset"]');
    avatarInputs.forEach((input) => {
        input.addEventListener('change', () => form.submit());
    });
})();
</script>