<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    /**
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

        try {
            $pendingToken = EmailOtpVerificationController::beginPendingRegistration($request, [
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'phone' => $request->phone,
                'role' => 'user',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['email' => 'Registration could not be started. Please try again in a moment.']);
        }

        $response = redirect()->route('otp.verify.notice')
            ->with('status', 'We sent a 6-digit OTP to your email. Check inbox and spam.');

        $cookie = EmailOtpVerificationController::pendingCookie($pendingToken);
        if ($cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    }
}
