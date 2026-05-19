<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $email = mb_strtolower(trim((string) $request->input('email')));
        $request->merge(['email' => $email]);

        if ($email !== '' && User::query()->where('email', $email)->exists()) {
            return redirect()->route('login', ['email' => $email])
                ->with('status', 'Account already exists for this email. Please login.');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/', 'max:25'],
            'role' => ['required', 'in:user'],
        ]);

        $otpSent = EmailOtpVerificationController::beginPendingRegistration($request, [
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'phone' => $request->phone,
            'role' => 'user',
        ]);

        if (! $otpSent) {
            return redirect()->route('otp.verify.notice')
                ->withErrors(['otp' => 'Could not send OTP email right now. Please tap resend in a few seconds.']);
        }

        return redirect()->route('otp.verify.notice')->with('status', 'We sent an OTP to your email.');
    }
}
