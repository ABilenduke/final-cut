<?php

namespace App\Models;

use App\Enums\InquiryStatus;
use App\Enums\RentalEventType;
use Carbon\CarbonInterface;
use Database\Factories\RentalInquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property RentalEventType $event_type
 * @property CarbonInterface $preferred_date
 * @property int $guest_count
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $message
 * @property InquiryStatus $status
 */
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
