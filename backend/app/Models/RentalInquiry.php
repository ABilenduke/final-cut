<?php

namespace App\Models;

use App\Enums\InquiryStatus;
use App\Enums\RentalEventType;
use Database\Factories\RentalInquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'event_type', 'preferred_date', 'guest_count', 'name',
    'email', 'phone', 'message', 'status',
])]
class RentalInquiry extends Model
{
    /** @use HasFactory<RentalInquiryFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'event_type' => RentalEventType::class,
            'status' => InquiryStatus::class,
            'preferred_date' => 'date',
            'guest_count' => 'integer',
        ];
    }
}
