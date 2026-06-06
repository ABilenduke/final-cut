<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users', 'max:255'],
            // max:72 — a sane upper bound (bcrypt silently truncates past 72
            // bytes, so anything longer is wasted input). Laravel's `max` counts
            // CHARACTERS via mb_strlen, which equals bytes for ASCII and stays a
            // reasonable DoS cap for multibyte input.
            'password' => ['required', 'confirmed', 'max:72', Password::defaults()],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->email)) {
            $this->merge(['email' => strtolower($this->email)]);
        }
    }
}
