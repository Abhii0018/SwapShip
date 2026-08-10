<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'phone' => $request->phone,
            'role' => AdminAccount::isAdminEmail($request->email) ? 'admin' : 'user',
            'email_verified_at' => now(),
            'is_verified' => true,
        ]);

        AdminAccount::syncRole($user);

        Auth::login($user, true);
        $request->session()->regenerate();
        AdminAccount::markSessionStarted($request);

        return redirect()
            ->to(AdminAccount::homeRouteFor($user))
            ->with('success', 'Account created successfully.');
    }
}
