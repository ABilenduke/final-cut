<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Surface the Idempotency-Key header as a validatable field. Always
        // present (null when the header is absent) so the uuid rule guards the
        // format whenever a client does send one. The frontend always sends a
        // fresh UUID per checkout attempt; a retry reusing it replays the
        // original booking instead of double-charging. (ultrareview P0 #7)
        $this->merge([
            'idempotencyKey' => $this->header('Idempotency-Key'),
        ]);

        // Canonicalize the guest email so it is the stable source of truth for
        // per_user_limit enforcement: without this a guest defeats the cap with
        // 'A@x.com' vs 'a@x.com' vs ' a@x.com '. The count also matches
        // case-insensitively (PromoCodeService::countRedemptions), but the
        // stored value must be canonical too. Mirrors RegisterRequest/LoginRequest.
        if ($this->filled('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'idempotencyKey' => ['nullable', 'uuid'],
            'showtimeId' => ['required', 'uuid', 'exists:showtimes,id'],
            'seatIds' => ['required', 'array', 'min:1', 'max:10'],
            'seatIds.*' => ['required', 'uuid', 'distinct', 'exists:seats,id'],
            'foodItems' => ['sometimes', 'array'],
            'foodItems.*.itemId' => ['required', 'uuid', 'exists:menu_items,id'],
            'foodItems.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'paymentMethodId' => ['required_without:giftCardCode', 'nullable', 'string'],
            'promoCode' => ['sometimes', 'nullable', 'string', 'max:20'],
            'giftCardCode' => ['sometimes', 'nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'loyaltyOptIn' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->user() && ! $this->filled('email')) {
                $validator->errors()->add('email', 'Email is required for guest checkout.');
            }
        });
    }
}
