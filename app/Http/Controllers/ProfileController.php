<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    private const AVATAR_PRESETS = [
        'https://api.dicebear.com/9.x/adventurer/svg?seed=Scout',
        'https://api.dicebear.com/9.x/adventurer/svg?seed=Nova',
        'https://api.dicebear.com/9.x/adventurer/svg?seed=Ranger',
        'https://api.dicebear.com/9.x/adventurer/svg?seed=Pixel',
        'https://api.dicebear.com/9.x/adventurer/svg?seed=Orbit',
        'https://api.dicebear.com/9.x/adventurer/svg?seed=Falcon',
    ];

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'avatarPresets' => self::AVATAR_PRESETS,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        unset($validated['profile_photo'], $validated['avatar_preset']);

        $user = $request->user();
        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            $user->profile_photo_path = $request->file('profile_photo')->store('profile-photos', 'public');
            $user->avatar_preset = null;
        } elseif (array_key_exists('avatar_preset', $validated)) {
            if ($validated['avatar_preset'] && in_array($validated['avatar_preset'], self::AVATAR_PRESETS, true)) {
                $user->avatar_preset = $validated['avatar_preset'];
            } elseif (! $validated['avatar_preset']) {
                $user->avatar_preset = null;
            }
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'profile_photo' => ['nullable', 'image', 'max:4096'],
            'avatar_preset' => ['nullable', 'string', Rule::in(self::AVATAR_PRESETS)],
        ]);

        $user = $request->user();

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            $user->profile_photo_path = $request->file('profile_photo')->store('profile-photos', 'public');
            $user->avatar_preset = null;
        } elseif (array_key_exists('avatar_preset', $validated)) {
            if (! empty($validated['avatar_preset'])) {
                $user->avatar_preset = $validated['avatar_preset'];
                $user->profile_photo_path = null;
            } else {
                $user->avatar_preset = null;
                $user->profile_photo_path = null;
            }
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-avatar-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
