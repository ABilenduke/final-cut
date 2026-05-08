<?php

namespace App\Http\Requests;

use App\Enums\GiftCardDeliveryMethod;
use App\Enums\GiftCardEdition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseGiftCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotencyKey' => $this->header('Idempotency-Key'),
            // Backward-compat defaults so existing API consumers that omit the
            // new design fields continue to work. The customer-facing redesign
            // sends these explicitly.
            'edition' => $this->input('edition', GiftCardEdition::default()->value),
            'deliveryMethod' => $this->input('deliveryMethod', GiftCardDeliveryMethod::default()->value),
        ]);
    }

    public function rules(): array
    {
        return [
            'idempotencyKey' => ['required', 'uuid'],
            'amount' => ['required', 'integer', 'min:500', 'max:50000'],
            'recipientEmail' => ['required', 'email'],
            'recipientName' => ['required', 'string', 'max:255'],
            'senderName' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:1000'],
            'edition' => ['required', Rule::enum(GiftCardEdition::class)],
            'deliveryMethod' => ['required', Rule::enum(GiftCardDeliveryMethod::class)],
            'scheduledSendAt' => ['nullable', 'date', 'after:now'],
            'paymentMethodId' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'idempotencyKey.required' => 'The Idempotency-Key header is required.',
            'idempotencyKey.uuid' => 'The Idempotency-Key header must be a valid UUID.',
        ];
    }
}
