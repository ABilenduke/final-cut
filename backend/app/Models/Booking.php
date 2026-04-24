<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use Carbon\CarbonInterface;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Sqids\Sqids;

/**
 * @property string $id
 * @property string $confirmation_code
 * @property string $showtime_id
 * @property string|null $user_id
 * @property string|null $guest_email
 * @property BookingStatus $status
 * @property CarbonInterface|null $flagged_at
 * @property string|null $flag_reason
 * @property string|null $notes
 * @property int $subtotal
 * @property int $discount
 * @property int $total
 * @property PaymentMethod|null $payment_method
 * @property string|null $stripe_payment_intent_id
 * @property Showtime $showtime
 * @property User|null $user
 */
#[Fillable([
    'confirmation_code', 'showtime_id', 'user_id', 'guest_email',
    'status', 'flagged_at', 'flag_reason', 'notes',
    'subtotal', 'discount', 'total', 'payment_method',
    'stripe_payment_intent_id',
])]
#[Hidden(['stripe_payment_intent_id'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, HasUuids;

    private static ?Sqids $sqids = null;

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            $booking->confirmation_code ??= static::generateConfirmationCode();
        });
    }

    public static function generateConfirmationCode(): string
    {
        $sequence = DB::selectOne("SELECT nextval('booking_code_seq') AS val")->val;

        self::$sqids ??= new Sqids(alphabet: 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', minLength: 6);

        return 'CVF-'.self::$sqids->encode([$sequence]);
    }

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'payment_method' => PaymentMethod::class,
            'flagged_at' => 'datetime',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
        ];
    }

    public function showtime(): BelongsTo
    {
        return $this->belongsTo(Showtime::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(BookingSeat::class);
    }

    public function foodItems(): HasMany
    {
        return $this->hasMany(BookingFoodItem::class);
    }
}
