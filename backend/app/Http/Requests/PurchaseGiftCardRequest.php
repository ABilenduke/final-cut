<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseGiftCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:500', 'max:50000'],
            'recipientEmail' => ['required', 'email'],
            'recipientName' => ['required', 'string', 'max:255'],
            'senderName' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:1000'],
            'paymentMethodId' => ['required', 'string'],
        ];
    }
}
