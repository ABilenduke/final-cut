<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($this->user()->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'password' => ['sometimes', 'confirmed', Password::defaults()],
            // Re-authenticate for any security-sensitive change: a password change
            // OR an email change (email controls future password-reset links, so a
            // hijacked session must not be able to swap it silently).
            'current_password' => [Rule::requiredIf(fn (): bool => $this->filled('password') || $this->emailIsChanging()), 'current_password'],
        ];
    }

    /**
     * Whether the request actually changes the email to a different value.
     * prepareForValidation() has already lowercased the input, so compare
     * lowercased to avoid a false positive on a pure case change of the same address.
     */
    protected function emailIsChanging(): bool
    {
        return $this->filled('email')
            && strtolower((string) $this->input('email')) !== strtolower((string) $this->user()->email);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->email)) {
            $this->merge(['email' => strtolower($this->email)]);
        }
    }
}
