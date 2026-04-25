<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/', 'max:25'],
            'address' => ['nullable', 'string', 'max:1024'],
            'age' => ['required', 'integer', 'between:13,100'],
            'gender' => ['required', 'in:male,female,others'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'pincode' => ['required', 'string', 'max:20'],
            'location' => ['required', 'string', 'max:255'],
            'profile_photo' => ['nullable', 'image', 'max:4096'],
            'avatar_preset' => ['nullable', 'string', Rule::in(self::AVATAR_PRESETS)],
        ];
    }
}
