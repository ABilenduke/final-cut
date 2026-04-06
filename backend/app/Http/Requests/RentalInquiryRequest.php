<?php

namespace App\Http\Requests;

use App\Enums\RentalEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RentalInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'eventType' => ['required', Rule::enum(RentalEventType::class)],
            'preferredDate' => ['required', 'date', 'after:today'],
            'guestCount' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'message' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
