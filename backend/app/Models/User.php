<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\LoyaltyTier;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property LoyaltyTier $loyalty_tier
 * @property Carbon|null $date_of_birth
 * @property Carbon|null $premier_expiry
 * @property Carbon $created_at
 */
#[Fillable(['name', 'email', 'password', 'phone', 'date_of_birth', 'avatar_url', 'loyalty_points', 'loyalty_tier', 'premier_expiry', 'stripe_customer_id'])]
#[Hidden(['password', 'remember_token', 'stripe_customer_id'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'loyalty_tier' => LoyaltyTier::class,
            'premier_expiry' => 'date',
            'loyalty_points' => 'integer',
        ];
    }

    /**
     * Get the bookings for the user.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Admin-applied loyalty adjustments for the user.
     */
    public function loyaltyAdjustments(): HasMany
    {
        return $this->hasMany(LoyaltyAdjustment::class);
    }
}
