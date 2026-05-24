<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\AdminAccount;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $email = (string) $this->input('email');
        $password = (string) $this->input('password');

        /** @var User|null $user */
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user || ! Hash::check($password, (string) $user->password)) {
            RateLimiter::hit($this->throttleKey());

            $message = ($user && filled($user->google_id))
                ? 'This email uses Google sign-in. Tap Continue with Google, or use Forgot password to set a login password.'
                : trans('auth.failed');

            throw ValidationException::withMessages([
                'email' => $message,
            ]);
        }

        if ($user->email !== $email) {
            $user->forceFill(['email' => $email])->saveQuietly();
        }

        AdminAccount::syncRole($user);

        if ($user->isBanned()) {
            throw ValidationException::withMessages([
                'email' => 'This account has been suspended. Contact support.',
            ]);
        }

        Auth::login($user, false);

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
