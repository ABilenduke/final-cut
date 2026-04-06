<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
