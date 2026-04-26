<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\LoyaltyTier;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property LoyaltyTier $loyalty_tier
 * @property Carbon|null $date_of_birth
 * @property Carbon|null $premier_expiry
 * @property Carbon $created_at
 * @property AdminProfile|null $adminProfile
 */
#[Fillable(['name', 'email', 'password', 'phone', 'date_of_birth', 'avatar_url', 'loyalty_points', 'loyalty_tier', 'premier_expiry', 'stripe_customer_id'])]
#[Hidden(['password', 'remember_token', 'stripe_customer_id'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable;

    /**
     * Spatie roles/permissions are scoped to the `admin` guard. A customer
     * User has no role assignments and `assignRole(...)` is intentionally
     * unused on the customer surface; the admin guard is the only place
     * authorization checks fire (Filament resources call `auth('admin')->user()->can(...)`).
     */
    protected string $guard_name = 'admin';

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

    /**
     * Admin profile / entitlement. Present only for users who have admin access;
     * `null` for ordinary customers.
     */
    public function adminProfile(): HasOne
    {
        return $this->hasOne(AdminProfile::class, 'user_id');
    }

    /**
     * True iff this user has an admin profile that is not disabled.
     * Source of truth for "is this user an admin right now?" — used by
     * canAccessPanel() and by anything filtering causers to admin-only.
     */
    public function isAdmin(): bool
    {
        return $this->adminProfile()->whereNull('disabled_at')->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->isAdmin();
    }
}
